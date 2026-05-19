<?php
/**
 * api_mobile.php — Token-based API for the Upskill mobile app
 * ─────────────────────────────────────────────────────────────
 * Auth : Bearer token in Authorization header (all routes except login)
 *
 * POST ?action=login          { username, password }  → { ok, token, user }
 * POST ?action=logout                                  → { ok }
 * GET  ?action=overview                                → { ok, full_name, att_*, pending_count, … }
 * GET  ?action=assignments                             → { ok, assignments }
 * GET  ?action=notifications                           → { ok, notifications, unread }
 * POST ?action=mark_read      { id? }                  → { ok }
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db.php';

$action = trim($_GET['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

// ── Rate limiter (simple, shared with web) ───────────────────────────────────
function mobile_rate_limit(string $key, int $max = 60, int $window = 60): void {
    try {
        $pdo = db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limit_buckets (
            bucket_key VARCHAR(120) PRIMARY KEY,
            hits       INT UNSIGNED NOT NULL DEFAULT 0,
            window_end BIGINT NOT NULL
        ) ENGINE=InnoDB");
        $now  = time();
        $stmt = $pdo->prepare("
            INSERT INTO rate_limit_buckets (bucket_key, hits, window_end)
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
                hits       = IF(window_end < ?, 1, hits + 1),
                window_end = IF(window_end < ?, ?, window_end)
        ");
        $stmt->execute([$key, $now + $window, $now, $now, $now + $window]);
        $row = $pdo->prepare("SELECT hits FROM rate_limit_buckets WHERE bucket_key=?");
        $row->execute([$key]);
        if ((int)($row->fetchColumn() ?? 0) > $max) {
            http_response_code(429);
            echo json_encode(['ok' => false, 'error' => 'Too many requests']);
            exit;
        }
    } catch (Throwable $e) {}
}

// ── Token helpers ─────────────────────────────────────────────────────────────
function ensure_tokens_table(): void {
    db()->exec("
        CREATE TABLE IF NOT EXISTS mobile_tokens (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            UNIQUE KEY uq_hash (token_hash),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function bearer_token(): ?string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strncasecmp($h, 'Bearer ', 7) === 0) return trim(substr($h, 7));
    return null;
}

function require_auth(): array {
    ensure_tokens_table();
    $raw = bearer_token();
    if (!$raw) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'No token']); exit; }
    $hash = hash('sha256', $raw);
    $stmt = db()->prepare("
        SELECT mt.user_id, u.username, u.full_name, u.role
        FROM   mobile_tokens mt
        JOIN   users u ON u.id = mt.user_id
        WHERE  mt.token_hash = ? AND mt.expires_at > NOW() AND u.role = 'student'
    ");
    $stmt->execute([$hash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Invalid or expired token']); exit; }
    return $user;
}

// ── LOGIN ─────────────────────────────────────────────────────────────────────
if ($action === 'login' && $method === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    mobile_rate_limit('mlogin:' . $ip, 10, 300);

    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'username and password required']);
        exit;
    }
    if (mb_strlen($username) > 80 || mb_strlen($password) > 200) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'Invalid credentials']);
        exit;
    }

    try {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'student'");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $hash  = $user['password'] ?? '$2y$10$invalidsaltpadding00000000000000000000000000000000000';
        $valid = password_verify($password, $hash);

        if (!$user || !$valid) {
            http_response_code(401);
            echo json_encode(['ok'=>false,'error'=>'Invalid username or password']);
            exit;
        }

        ensure_tokens_table();
        // Expire old tokens for this user (keep at most 5 devices)
        $pdo->prepare("DELETE FROM mobile_tokens WHERE user_id = ? AND id NOT IN (
            SELECT id FROM (SELECT id FROM mobile_tokens WHERE user_id = ? ORDER BY created_at DESC LIMIT 4) t
        )")->execute([$user['id'], $user['id']]);

        $raw   = bin2hex(random_bytes(32));
        $hash2 = hash('sha256', $raw);
        $pdo->prepare("INSERT INTO mobile_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 90 DAY))")
            ->execute([$user['id'], $hash2]);

        echo json_encode([
            'ok'    => true,
            'token' => $raw,
            'user'  => [
                'id'        => (int)$user['id'],
                'username'  => $user['username'],
                'full_name' => $user['full_name'],
            ],
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'Server error']);
    }
    exit;
}

// ── LOGOUT ────────────────────────────────────────────────────────────────────
if ($action === 'logout' && $method === 'POST') {
    $raw = bearer_token();
    if ($raw) {
        try {
            ensure_tokens_table();
            db()->prepare("DELETE FROM mobile_tokens WHERE token_hash=?")
                ->execute([hash('sha256', $raw)]);
        } catch (Throwable $e) {}
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── All routes below require auth ─────────────────────────────────────────────
$authUser  = require_auth();
$studentId = (int)$authUser['user_id'];

// ── OVERVIEW ─────────────────────────────────────────────────────────────────
if ($action === 'overview' && $method === 'GET') {
    mobile_rate_limit('moverview:' . $studentId, 60, 60);
    try {
        $pdo = db();

        // Course via class_groups (same query as dashboard-student.php)
        $course = null;
        try {
            $typeLabelsFr = ['beginners'=>'Débutants','pre_intermediate'=>'Pré-intermédiaire',
                'intermediate'=>'Intermédiaire','upper_intermediate'=>'Upper-intermédiaire',
                'advanced'=>'Avancé','baccalaureate'=>'Baccalauréat','business'=>'Business','kids'=>'Kids'];
            $typeLabelsEn = ['beginners'=>'Beginners','pre_intermediate'=>'Pre-intermediate',
                'intermediate'=>'Intermediate','upper_intermediate'=>'Upper-intermediate',
                'advanced'=>'Advanced','baccalaureate'=>'Baccalaureate','business'=>'Business','kids'=>'Kids'];

            $stmt = $pdo->prepare("
                SELECT g.id AS group_id, g.type_key, g.level_number, g.group_letter,
                       g.schedule_json, g.zoom_url,
                       (SELECT u2.full_name FROM class_group_members m2
                          JOIN users u2 ON u2.id = m2.user_id
                         WHERE m2.group_id = g.id AND u2.role = 'teacher' LIMIT 1) AS teacher_name
                FROM   class_group_members m
                JOIN   class_groups g ON g.id = m.group_id
                WHERE  m.user_id = ? LIMIT 1
            ");
            $stmt->execute([$studentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) {
                $tk = $row['type_key']; $lvl = $row['level_number']; $gl = $row['group_letter'];
                $row['label_fr'] = ($typeLabelsFr[$tk] ?? $tk) . ($lvl ? ' ' . $lvl : '') . ' – Groupe ' . $gl;
                $row['label_en'] = ($typeLabelsEn[$tk] ?? $tk) . ($lvl ? ' ' . $lvl : '') . ' – Group '  . $gl;
                $course = $row;
            }
        } catch (Throwable $e) {}

        // Attendance
        $total = 0; $present = 0; $attRate = null;
        try {
            ensureAttendanceTable();
            $att = $pdo->prepare("SELECT COUNT(*) AS total, SUM(present) AS present FROM attendance WHERE student_id=?");
            $att->execute([$studentId]);
            $r = $att->fetch(PDO::FETCH_ASSOC);
            $total   = (int)($r['total']   ?? 0);
            $present = (int)($r['present'] ?? 0);
            $attRate = $total > 0 ? round($present / $total * 100) : null;
        } catch (Throwable $e) {}

        // Assignment counts
        $pending = 0; $overdue = 0; $submitted = 0;
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS assignments (
                id INT AUTO_INCREMENT PRIMARY KEY, course_id INT NOT NULL,
                title_fr VARCHAR(200) NOT NULL DEFAULT '', due_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_course (course_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS assignment_submissions (
                id INT AUTO_INCREMENT PRIMARY KEY, assignment_id INT NOT NULL, student_id INT NOT NULL,
                status ENUM('pending','submitted','overdue') NOT NULL DEFAULT 'pending',
                submitted_at TIMESTAMP NULL,
                UNIQUE KEY uq_sub (assignment_id, student_id), INDEX idx_student (student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS student_courses (
                id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, course_id INT NOT NULL,
                enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sc (student_id, course_id), INDEX idx_student (student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $aStmt = $pdo->prepare("
                SELECT COALESCE(sub.status,'pending') AS status, a.due_date
                FROM   assignments a
                JOIN   student_courses sc ON sc.course_id = a.course_id AND sc.student_id = ?
                LEFT   JOIN assignment_submissions sub ON sub.assignment_id = a.id AND sub.student_id = ?
            ");
            $aStmt->execute([$studentId, $studentId]);
            $now = new DateTime();
            foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $s = $a['status'];
                if ($s === 'pending' && $a['due_date'] && new DateTime($a['due_date']) < $now) $s = 'overdue';
                if ($s === 'pending')   $pending++;
                elseif ($s === 'overdue')   $overdue++;
                elseif ($s === 'submitted') $submitted++;
            }
        } catch (Throwable $e) {}

        echo json_encode([
            'ok'            => true,
            'full_name'     => $authUser['full_name'],
            'course'        => $course,
            'att_total'     => $total,
            'att_present'   => $present,
            'att_rate'      => $attRate,
            'pending_count' => $pending,
            'overdue_count' => $overdue,
            'submitted_count' => $submitted,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']);
    }
    exit;
}

// ── ASSIGNMENTS ───────────────────────────────────────────────────────────────
if ($action === 'assignments' && $method === 'GET') {
    mobile_rate_limit('massign:' . $studentId, 60, 60);
    try {
        $pdo   = db();
        $items = [];
        try {
            $stmt = $pdo->prepare("
                SELECT a.id, a.title_fr AS title, a.description_fr AS description,
                       a.subject_fr AS subject, a.due_date,
                       COALESCE(sub.status,'pending') AS status,
                       sub.submitted_at, sub.score, sub.teacher_comment
                FROM   assignments a
                JOIN   student_courses sc ON sc.course_id = a.course_id AND sc.student_id = :s1
                LEFT   JOIN assignment_submissions sub
                         ON sub.assignment_id = a.id AND sub.student_id = :s2
                ORDER  BY FIELD(COALESCE(sub.status,'pending'),'overdue','pending','submitted'),
                          a.due_date ASC
            ");
            $stmt->execute([':s1' => $studentId, ':s2' => $studentId]);
            $now = new DateTime();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as &$row) {
                if ($row['status'] === 'pending' && $row['due_date'] && new DateTime($row['due_date']) < $now)
                    $row['status'] = 'overdue';
            }
            unset($row);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            // Re-fetch with corrected statuses
            $stmt->execute([':s1' => $studentId, ':s2' => $studentId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $now2  = new DateTime();
            foreach ($items as &$row) {
                if ($row['status'] === 'pending' && $row['due_date'] && new DateTime($row['due_date']) < $now2)
                    $row['status'] = 'overdue';
            }
            unset($row);
        } catch (Throwable $e) {}
        echo json_encode(['ok' => true, 'assignments' => $items], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']);
    }
    exit;
}

// ── NOTIFICATIONS ─────────────────────────────────────────────────────────────
if ($action === 'notifications' && $method === 'GET') {
    mobile_rate_limit('mnotif:' . $studentId, 60, 60);
    try {
        $pdo = db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
            type VARCHAR(40) NOT NULL DEFAULT 'info',
            title_en VARCHAR(200) NOT NULL DEFAULT '', body_en VARCHAR(400) NOT NULL DEFAULT '',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $pdo->prepare("
            SELECT id, type, title_en AS title, body_en AS body, is_read, created_at
            FROM   notifications
            WHERE  user_id = ?
            ORDER  BY created_at DESC
            LIMIT  50
        ");
        $stmt->execute([$studentId]);
        $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $unread = count(array_filter($notifs, fn($n) => !$n['is_read']));
        echo json_encode(['ok' => true, 'notifications' => $notifs, 'unread' => $unread], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']);
    }
    exit;
}

// ── MARK NOTIFICATION READ ────────────────────────────────────────────────────
if ($action === 'mark_read' && $method === 'POST') {
    try {
        $pdo  = db();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = isset($body['id']) ? (int)$body['id'] : null;
        if ($id) {
            $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id, $studentId]);
        } else {
            $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$studentId]);
        }
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']);
    }
    exit;
}

http_response_code(404);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
