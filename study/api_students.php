<?php
ob_start(); // buffer all output — prevents PHP notices/warnings corrupting JSON
/**
 * api_students.php — Returns students assigned to the logged-in teacher's courses
 * ─────────────────────────────────────────────────────────────────────────────
 * Auth : teacher session required
 * GET  ?action=list          → student list with attendance stats
 * GET  ?action=grades        → student list with per-quiz scores
 *
 * Schema assumed (from existing DDL in assign_courses.php + db.php):
 *   users(id, username, full_name, role)
 *   courses(id, group_name_fr, group_name_ar, subject_fr, subject_ar, level, students_count)
 *   teacher_courses(teacher_id, course_id)
 *   attendance(teacher_id, student_id, session_num, present)
 *
 * Students are users with role='student'. In the absence of a student_courses
 * junction table we fall back to returning all students — the teacher can see
 * everyone. When a student_courses table exists this query can be tightened.
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
header('Content-Type: application/json; charset=UTF-8');

$role   = $_SESSION['role'] ?? '';
$isAdmin   = $role === 'admin';
$isTeacher = $role === 'teacher';

if (empty($_SESSION['user_id']) || (!$isAdmin && !$isTeacher)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db.php';

$teacherId = (int) $_SESSION['user_id'];
$action    = trim($_GET['action'] ?? 'list');

function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $init  = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $init .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $init ?: '?';
}

try {
    $pdo = db();

    // ── Admin-only actions ───────────────────────────────────────────────────
    if ($isAdmin) {

        if ($action === 'all_users') {
            // Ensure email column exists
            try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(180) DEFAULT NULL"); } catch(Throwable $e){}
            $stmt = $pdo->prepare("SELECT id, full_name, username, email, role FROM users WHERE role IN ('student','teacher') ORDER BY role, full_name");
            $stmt->execute();
            ob_clean(); echo json_encode(['ok'=>true,'users'=>$stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'update_email') {
            csrf_verify();
            $raw     = json_decode(file_get_contents('php://input'), true) ?? [];
            $uid     = (int)($raw['user_id'] ?? 0);
            $email   = trim($raw['email'] ?? '');
            if (!$uid || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
                ob_clean(); echo json_encode(['ok'=>false,'error'=>'Invalid input']); exit;
            }
            // Check email not already taken by another user
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $uid]);
            if ($check->fetch()) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'Cet email est déjà utilisé.']); exit; }
            $pdo->prepare("UPDATE users SET email=? WHERE id=?")->execute([$email, $uid]);
            ob_clean(); echo json_encode(['ok'=>true]); exit;
        }

        if ($action === 'reset_password') {
            csrf_verify();
            $raw  = json_decode(file_get_contents('php://input'), true) ?? [];
            $uid  = (int)($raw['user_id'] ?? 0);
            $pw   = $raw['password'] ?? '';
            if (!$uid || mb_strlen($pw) < 8 || mb_strlen($pw) > 200) {
                ob_clean(); echo json_encode(['ok'=>false,'error'=>'Invalid input']); exit;
            }
            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12]);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
            // Invalidate any pending reset tokens
            try { $pdo->prepare("UPDATE password_resets SET used=1 WHERE user_id=?")->execute([$uid]); } catch(Throwable $e){}
            ob_clean(); echo json_encode(['ok'=>true]); exit;
        }

        if ($action === 'create_user') {
            csrf_verify();
            $raw      = json_decode(file_get_contents('php://input'), true) ?? [];
            $fullname = trim($raw['full_name'] ?? '');
            $username = trim($raw['username']  ?? '');
            $email    = trim($raw['email']     ?? '');
            $role_new = trim($raw['role']      ?? 'student');
            $pw       = $raw['password']       ?? '';
            if (!$fullname || !$username || mb_strlen($pw) < 8) {
                ob_clean(); echo json_encode(['ok'=>false,'error'=>'Champs requis manquants ou mot de passe trop court.']); exit;
            }
            if (!in_array($role_new, ['student','teacher'])) {
                ob_clean(); echo json_encode(['ok'=>false,'error'=>'Rôle invalide.']); exit;
            }
            if ($email && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180)) {
                ob_clean(); echo json_encode(['ok'=>false,'error'=>'Email invalide.']); exit;
            }
            // Check username uniqueness
            $chk = $pdo->prepare("SELECT id FROM users WHERE username=?"); $chk->execute([$username]);
            if ($chk->fetch()) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'Cet identifiant est déjà utilisé.']); exit; }
            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12]);
            try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(180) DEFAULT NULL"); } catch(Throwable $e){}
            $ins = $pdo->prepare("INSERT INTO users (full_name, username, email, password, role) VALUES (?,?,?,?,?)");
            $ins->execute([$fullname, $username, $email ?: null, $hash, $role_new]);
            ob_clean(); echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]); exit;
        }

        if ($action === 'registered_students') {
            // Safely add columns if missing — IF NOT EXISTS not supported on older MySQL
            try { $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(180) DEFAULT NULL"); } catch(Throwable $e){}
            try { $pdo->exec("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch(Throwable $e){}
            $search = substr(trim($_GET['search'] ?? ''), 0, 100);
            if ($search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
                $like = "%{$escaped}%";
                $stmt = $pdo->prepare("SELECT id, full_name, username,
                    COALESCE(email,'') AS email,
                    COALESCE(created_at,'') AS created_at
                    FROM users WHERE role='student'
                    AND (full_name LIKE ? OR username LIKE ? OR email LIKE ?)
                    ORDER BY full_name");
                $stmt->execute([$like, $like, $like]);
            } else {
                $stmt = $pdo->prepare("SELECT id, full_name, username,
                    COALESCE(email,'') AS email,
                    COALESCE(created_at,'') AS created_at
                    FROM users WHERE role='student' ORDER BY full_name");
                $stmt->execute();
            }
            $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
            ob_clean(); echo json_encode(['ok'=>true,'students'=>$stmt->fetchAll(),'total'=>$count], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($action === 'enrollments') {
            // Create table if not exists
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS enrollments (
                    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name       VARCHAR(200) NOT NULL,
                    email      VARCHAR(200) DEFAULT NULL,
                    phone      VARCHAR(50)  DEFAULT NULL,
                    course     VARCHAR(200) DEFAULT NULL,
                    message    TEXT         DEFAULT NULL,
                    status     VARCHAR(20)  NOT NULL DEFAULT 'new',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch(Throwable $e){}
            // Add missing columns safely
            foreach (['phone VARCHAR(50) DEFAULT NULL','course VARCHAR(200) DEFAULT NULL','message TEXT DEFAULT NULL'] as $col) {
                try { $pdo->exec("ALTER TABLE enrollments ADD COLUMN $col"); } catch(Throwable $e){}
            }
            // Migrate ENUM → VARCHAR if table was previously created by enroll.php
            try { $pdo->exec("ALTER TABLE enrollments MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'new'"); } catch(Throwable $e){}

            $status = trim($_GET['status'] ?? 'all');
            $search = substr(trim($_GET['search'] ?? ''), 0, 100);
            $where  = []; $params = [];
            if ($status === 'new_refused') {
                $where[] = "status IN ('new','refused')";
            } elseif ($status !== 'all') {
                $where[] = 'status=?'; $params[] = $status;
            }
            if ($search !== '') {
                $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
                $like = "%{$escaped}%";
                array_push($params, $like, $like, $like);
            }
            $sql = "SELECT * FROM enrollments" . ($where ? " WHERE " . implode(' AND ', $where) : '') . " ORDER BY created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            // Counts per status
            $counts = $pdo->query("SELECT status, COUNT(*) AS n FROM enrollments GROUP BY status")->fetchAll();
            $countMap = ['new'=>0,'refused'=>0,'accepted'=>0,'all'=>0];
            foreach ($counts as $c) { $countMap[$c['status']] = (int)$c['n']; $countMap['all'] += (int)$c['n']; }
            ob_clean(); echo json_encode(['ok'=>true,'rows'=>$stmt->fetchAll(),'counts'=>$countMap], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'update_enrollment_status') {
            csrf_verify();
            $raw    = json_decode(file_get_contents('php://input'), true) ?? [];
            $id     = (int)($raw['id']     ?? 0);
            $status = trim($raw['status']  ?? '');
            if (!$id || !in_array($status, ['new','refused','accepted'])) {
                ob_clean(); echo json_encode(['ok'=>false,'error'=>'Invalid input']); exit;
            }
            $pdo->prepare("UPDATE enrollments SET status=? WHERE id=?")->execute([$status, $id]);
            ob_clean(); echo json_encode(['ok'=>true]); exit;
        }

        if ($action === 'delete_enrollment') {
            csrf_verify();
            $raw = json_decode(file_get_contents('php://input'), true) ?? [];
            $id  = (int)($raw['id'] ?? 0);
            if (!$id) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'Invalid id']); exit; }
            $pdo->prepare("DELETE FROM enrollments WHERE id=?")->execute([$id]);
            ob_clean(); echo json_encode(['ok'=>true]); exit;
        }

        if ($action === 'accept_enrollment_with_user') {
            csrf_verify();
            $raw      = json_decode(file_get_contents('php://input'), true) ?? [];
            $enrollId = (int)(  $raw['enrollment_id'] ?? 0);
            $fullname = trim(   $raw['full_name']      ?? '');
            $username = trim(   $raw['username']       ?? '');
            $email    = trim(   $raw['email']          ?? '');
            $pw       =         $raw['password']       ?? '';

            if (!$enrollId || !$fullname || !$username || mb_strlen($pw) < 8) {
                ob_clean(); echo json_encode(['ok'=>false,'error'=>'Champs requis manquants ou mot de passe trop court (min. 8 caractères).']); exit;
            }
            if ($email && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180)) {
                ob_clean(); echo json_encode(['ok'=>false,'error'=>'Email invalide.']); exit;
            }

            // Verify enrollment exists and is not already accepted
            $enroll = $pdo->prepare("SELECT id, status FROM enrollments WHERE id = ?");
            $enroll->execute([$enrollId]);
            $enrollRow = $enroll->fetch(PDO::FETCH_ASSOC);
            if (!$enrollRow) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'Inscription introuvable.']); exit; }
            if ($enrollRow['status'] === 'accepted') { ob_clean(); echo json_encode(['ok'=>false,'error'=>'Cette inscription a déjà été acceptée.']); exit; }

            // Check username uniqueness
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetch()) { ob_clean(); echo json_encode(['ok'=>false,'error'=>'Cet identifiant est déjà utilisé.']); exit; }

            try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(180) DEFAULT NULL"); } catch(Throwable $e){}

            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12]);
            try {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO users (full_name, username, email, password, role) VALUES (?,?,?,?,?)")
                    ->execute([$fullname, $username, $email ?: null, $hash, 'student']);
                $newUserId = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE enrollments SET status='accepted' WHERE id=?")->execute([$enrollId]);
                $pdo->commit();
                ob_clean(); echo json_encode(['ok'=>true,'user_id'=>$newUserId]); exit;
            } catch(Throwable $e) {
                try { $pdo->rollBack(); } catch(Throwable $e2){}
                ob_clean(); echo json_encode(['ok'=>false,'error'=>'Erreur lors de la création du compte.']); exit;
            }
        }

    } // end $isAdmin

    // Non-admin must be teacher for the following actions
    if (!$isTeacher) { http_response_code(403); ob_clean(); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }
    // Only return students enrolled in this teacher's own courses
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.full_name, u.username
            FROM users u
            JOIN student_courses sc ON sc.student_id = u.id
            JOIN teacher_courses tc ON tc.course_id  = sc.course_id
            WHERE u.role = 'student' AND tc.teacher_id = ?
            ORDER BY u.full_name
        ");
        $stmt->execute([$teacherId]);
        $students = $stmt->fetchAll();
        // Empty result is legitimate — teacher may have no enrolled students yet
    } catch (Throwable $e) {
        error_log('api_students.php student query error: ' . $e->getMessage());
        ob_clean(); http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Server error']);
        exit;
    }

    // ── Attendance stats per student ─────────────────────────────────────────
    ensureAttendanceTable();
    $attStmt = $pdo->prepare("
        SELECT student_id,
               COUNT(*)                          AS total_sessions,
               SUM(present)                      AS present_count
        FROM   attendance
        WHERE  teacher_id = ?
        GROUP  BY student_id
    ");
    $attStmt->execute([$teacherId]);
    $attMap = [];
    foreach ($attStmt->fetchAll() as $row) {
        $attMap[(int)$row['student_id']] = $row;
    }

    if ($action === 'grades') {
        // Return students with placeholder quiz scores (real quiz table not yet in schema)
        $result = array_map(function($s) use ($attMap) {
            $sid  = (int)$s['id'];
            $att  = $attMap[$sid] ?? ['total_sessions' => 0, 'present_count' => 0];
            $rate = $att['total_sessions'] > 0
                ? round($att['present_count'] / $att['total_sessions'] * 100)
                : null;
            return [
                'id'        => $sid,
                'name'      => $s['full_name'] ?: $s['username'],
                'init'      => initials($s['full_name'] ?: $s['username']),
                'att_rate'  => $rate,
                // Quiz scores: null until quiz table is implemented
                'scores'    => [],
                'avg'       => null,
            ];
        }, $students);

        echo json_encode(['ok' => true, 'students' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Default: list with attendance-derived progress/status
    $result = array_map(function($s) use ($attMap) {
        $sid      = (int)$s['id'];
        $att      = $attMap[$sid] ?? ['total_sessions' => 0, 'present_count' => 0];
        $sessions = (int)$att['total_sessions'];
        $present  = (int)$att['present_count'];
        $rate     = $sessions > 0 ? round($present / $sessions * 100) : 0;
        $status   = $rate >= 75 ? 'good' : ($rate >= 55 ? 'warn' : 'low');
        return [
            'id'       => $sid,
            'name'     => $s['full_name'] ?: $s['username'],
            'init'     => initials($s['full_name'] ?: $s['username']),
            'progress' => $rate,
            'assigns'  => 0,   // populated once assignments table exists
            'avg'      => $rate,
            'status'   => $status,
            'sessions' => $sessions,
            'present'  => $present,
        ];
    }, $students);

    echo json_encode(['ok' => true, 'students' => $result], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('api_students.php error: ' . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
