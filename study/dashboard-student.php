<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ── Auth guard ──
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php?error=auth');
    exit;
}

$full_name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? '');
$username  = htmlspecialchars($_SESSION['username'] ?? '');
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// ── Fetch live data from DB ──────────────────────────────────────────────────
require_once __DIR__ . '/db.php';

$studentId = (int) $_SESSION['user_id'];

// ── Check for server-stored avatar ──────────────────────────────────────────
$avatarUrl = null;
foreach (['jpg','png','webp','gif'] as $_ext) {
    $_path = __DIR__ . '/uploads/avatars/' . $studentId . '.' . $_ext;
    if (file_exists($_path)) {
        $avatarUrl = '/study/uploads/avatars/' . $studentId . '.' . $_ext . '?v=' . filemtime($_path);
        break;
    }
}
unset($_ext, $_path);

// Fetch student email (to show collection popup if missing)
try {
    $eStmt = db()->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    $eStmt->execute([$studentId]);
    $studentEmail = $eStmt->fetchColumn() ?: '';
} catch (Throwable $e) {
    $studentEmail = '';
}

$liveData  = [
    'course'       => null,
    'att_total'    => 0,
    'att_present'  => 0,
    'att_rate'     => null,
    'assignments'  => [],
    'pending_count'=> 0,
    'overdue_count'=> 0,
    'submitted_count'=> 0,
    'activity'     => [],
];

try {
    $pdo = db();

    // ── Ensure assignments tables exist (once per session) ──────────────────
    if (empty($_SESSION['student_schema_ok'])) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS assignments (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                course_id      INT NOT NULL,
                title_fr       VARCHAR(200) NOT NULL DEFAULT '',
                title_ar       VARCHAR(200) NOT NULL DEFAULT '',
                description_fr TEXT,
                description_ar TEXT,
                subject_fr     VARCHAR(100) NOT NULL DEFAULT '',
                subject_ar     VARCHAR(100) NOT NULL DEFAULT '',
                due_date       DATE,
                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_course (course_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS assignment_submissions (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                assignment_id INT NOT NULL,
                student_id    INT NOT NULL,
                status        ENUM('pending','submitted','overdue') NOT NULL DEFAULT 'pending',
                submitted_at  TIMESTAMP NULL,
                UNIQUE KEY uq_sub (assignment_id, student_id),
                INDEX idx_student (student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS student_courses (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                course_id  INT NOT NULL,
                enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sc (student_id, course_id),
                INDEX idx_student (student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $_SESSION['student_schema_ok'] = true;
    }

    // ── Course info (from class_groups — new system) ────────────────────────
    try {
        $stmt = $pdo->prepare("
            SELECT g.id AS group_id, g.type_key, g.level_number, g.group_letter,
                   g.schedule_json, g.zoom_url,
                   (SELECT u2.full_name
                      FROM class_group_members m2
                      JOIN users u2 ON u2.id = m2.user_id
                     WHERE m2.group_id = g.id AND u2.role = 'teacher'
                     LIMIT 1) AS teacher_name
            FROM   class_group_members m
            JOIN   class_groups g ON g.id = m.group_id
            WHERE  m.user_id = ?
            LIMIT  1
        ");
        $stmt->execute([$studentId]);
        $row = $stmt->fetch() ?: null;
        if ($row) {
            $typeLabelsFr = [
                'beginners'=>'Débutants','pre_intermediate'=>'Pré-intermédiaire',
                'intermediate'=>'Intermédiaire','upper_intermediate'=>'Upper-intermédiaire',
                'advanced'=>'Avancé','baccalaureate'=>'Baccalauréat','business'=>'Business','kids'=>'Kids'
            ];
            $typeLabelsAr = [
                'beginners'=>'مبتدئون','pre_intermediate'=>'ما قبل المتوسط',
                'intermediate'=>'متوسط','upper_intermediate'=>'فوق المتوسط',
                'advanced'=>'متقدم','baccalaureate'=>'البكالوريا','business'=>'الأعمال','kids'=>'أطفال'
            ];
            $typeLabelsEn = [
                'beginners'=>'Beginners','pre_intermediate'=>'Pre-intermediate',
                'intermediate'=>'Intermediate','upper_intermediate'=>'Upper-intermediate',
                'advanced'=>'Advanced','baccalaureate'=>'Baccalaureate','business'=>'Business','kids'=>'Kids'
            ];
            $lvl  = $row['level_number'];
            $tk   = $row['type_key'];
            $gl   = $row['group_letter'];
            $row['label_fr'] = ($typeLabelsFr[$tk] ?? $tk) . ($lvl ? ' ' . $lvl : '') . ' – Groupe ' . $gl;
            $row['label_ar'] = ($typeLabelsAr[$tk] ?? $tk) . ($lvl ? ' ' . $lvl : '') . ' – مجموعة ' . $gl;
            $row['label_en'] = ($typeLabelsEn[$tk] ?? $tk) . ($lvl ? ' ' . $lvl : '') . ' – Group ' . $gl;
        }
        $liveData['course'] = $row;
    } catch (Throwable $e) {}

    // ── Attendance ───────────────────────────────────────────────────────────
    try {
        ensureAttendanceTable();
        $attStmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(present) AS present FROM attendance WHERE student_id = ?");
        $attStmt->execute([$studentId]);
        $att = $attStmt->fetch();
        $liveData['att_total']   = (int)($att['total']   ?? 0);
        $liveData['att_present'] = (int)($att['present'] ?? 0);
        $liveData['att_rate']    = $liveData['att_total'] > 0
            ? round($liveData['att_present'] / $liveData['att_total'] * 100) : null;
    } catch (Throwable $e) {}

    // ── Assignments ──────────────────────────────────────────────────────────
    try {
        $aStmt = $pdo->prepare("
            SELECT a.id, a.title_fr, a.title_ar,
                   a.description_fr, a.description_ar,
                   a.subject_fr, a.subject_ar, a.due_date,
                   COALESCE(sub.status, 'pending') AS status,
                   sub.submitted_at, sub.comment AS my_comment,
                   sub.score, sub.teacher_comment, sub.graded_at
            FROM   assignments a
            JOIN   student_courses sc ON sc.course_id = a.course_id AND sc.student_id = :sid
            LEFT   JOIN assignment_submissions sub
                     ON sub.assignment_id = a.id AND sub.student_id = :sid2
            ORDER  BY FIELD(COALESCE(sub.status,'pending'),'overdue','pending','submitted'),
                      a.due_date ASC
        ");
        $aStmt->execute([':sid' => $studentId, ':sid2' => $studentId]);
        $rows = $aStmt->fetchAll();

        $now = new DateTime();
        foreach ($rows as &$row) {
            // Auto-mark as overdue if past due_date and not submitted
            if ($row['status'] === 'pending' && $row['due_date']) {
                $due = new DateTime($row['due_date']);
                if ($due < $now) $row['status'] = 'overdue';
            }
            // due_fmt is computed client-side (see fmtDue() in JS) so it is language-aware
            $row['due_fmt'] = '';
        }
        unset($row);

        $liveData['assignments']     = $rows;
        $liveData['pending_count']   = count(array_filter($rows, fn($r) => $r['status'] === 'pending'));
        $liveData['overdue_count']   = count(array_filter($rows, fn($r) => $r['status'] === 'overdue'));
        $liveData['submitted_count'] = count(array_filter($rows, fn($r) => $r['status'] === 'submitted'));
    } catch (Throwable $e) {}

    // ── Activity feed (last 5 events from attendance + assignments) ──────────
    try {
        $activity = [];

        // Recent attendance sessions (present)
        $actAtt = $pdo->prepare("
            SELECT 'attendance' AS type, updated_at AS ts, session_num, present
            FROM   attendance
            WHERE  student_id = ? AND present = 1
            ORDER  BY updated_at DESC LIMIT 3
        ");
        $actAtt->execute([$studentId]);
        foreach ($actAtt->fetchAll() as $r) {
            $activity[] = [
                'type'    => 'attendance',
                'color'   => 'green',
                'label_fr'=> 'Séance assistée',
                'label_en'=> 'Session attended',
                'label_ar'=> 'جلسة حضرت',
                'detail_fr'=> 'Séance n°' . $r['session_num'],
                'detail_en'=> 'Session #' . $r['session_num'],
                'detail_ar'=> 'جلسة رقم ' . $r['session_num'],
                'time'    => $r['ts'],
            ];
        }

        // Recent assignment submissions
        $actSub = $pdo->prepare("
            SELECT 'submission' AS type, sub.submitted_at AS ts,
                   a.title_fr, a.title_ar
            FROM   assignment_submissions sub
            JOIN   assignments a ON a.id = sub.assignment_id
            WHERE  sub.student_id = ? AND sub.status = 'submitted'
            ORDER  BY sub.submitted_at DESC LIMIT 3
        ");
        $actSub->execute([$studentId]);
        foreach ($actSub->fetchAll() as $r) {
            $activity[] = [
                'type'    => 'submission',
                'color'   => 'green',
                'label_fr'=> 'Devoir soumis',
                'label_en'=> 'Assignment submitted',
                'label_ar'=> 'واجب مُسلَّم',
                'detail_fr'=> $r['title_fr'],
                'detail_en'=> $r['title_fr'],
                'detail_ar'=> $r['title_ar'],
                'time'    => $r['ts'],
            ];
        }

        // Overdue assignments
        foreach (array_filter($liveData['assignments'], fn($r) => $r['status'] === 'overdue') as $r) {
            $activity[] = [
                'type'    => 'overdue',
                'color'   => 'red',
                'label_fr'=> 'Devoir en retard',
                'label_en'=> 'Overdue assignment',
                'label_ar'=> 'واجب متأخر',
                'detail_fr'=> $r['title_fr'],
                'detail_en'=> $r['title_fr'],
                'detail_ar'=> $r['title_ar'],
                'time'    => null,
            ];
        }

        // Sort by time desc, nulls last
        usort($activity, function($a, $b) {
            if (!$a['time'] && !$b['time']) return 0;
            if (!$a['time']) return 1;
            if (!$b['time']) return -1;
            return strcmp($b['time'], $a['time']);
        });

        $liveData['activity'] = array_slice($activity, 0, 5);
    } catch (Throwable $e) {}

} catch (Throwable $e) {}

// ── Stars of the Month + Class Activity ─────────────────────────────────────
$starsOfMonth = [];
$classActivity = [];
if (!empty($liveData['course'])) {
    $groupId = (int)$liveData['course']['group_id'];
    try {
        $sStmt = $pdo->prepare("
            SELECT u.full_name, u.id,
                   COUNT(att.id) AS total_sessions,
                   COALESCE(SUM(att.present), 0) AS present_sessions
            FROM   class_group_members m
            JOIN   users u ON u.id = m.user_id AND u.role = 'student'
            LEFT   JOIN attendance att
                     ON att.student_id = m.user_id
                    AND YEAR(att.updated_at)  = YEAR(CURDATE())
                    AND MONTH(att.updated_at) = MONTH(CURDATE())
            WHERE  m.group_id = ?
            GROUP  BY u.id, u.full_name
            ORDER  BY (COALESCE(SUM(att.present),0) / GREATEST(COUNT(att.id),1)) DESC,
                      present_sessions DESC
            LIMIT  5
        ");
        $sStmt->execute([$groupId]);
        $starsOfMonth = $sStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    try {
        $caStmt = $pdo->prepare("
            SELECT u.full_name, sub.submitted_at, a.title_fr, a.title_ar
            FROM   assignment_submissions sub
            JOIN   users u ON u.id = sub.student_id
            JOIN   assignments a ON a.id = sub.assignment_id
            JOIN   class_group_members m ON m.user_id = sub.student_id AND m.group_id = ?
            WHERE  sub.status = 'submitted'
            ORDER  BY sub.submitted_at DESC
            LIMIT  8
        ");
        $caStmt->execute([$groupId]);
        $classActivity = $caStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}
$jsStars    = json_encode($starsOfMonth, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$jsClassAct = json_encode($classActivity, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

$jsData = json_encode($liveData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

// Mini dino SVG used as the default avatar (face-crop viewBox)
$dinoAvatarSvg = '<svg class="av-dino" id="%ID%" xmlns="http://www.w3.org/2000/svg" viewBox="22 14 76 90" aria-hidden="true">'
  . '<path d="M32 56 Q8 76 14 100 Q30 88 42 68 Q46 86 42 102 Q60 90 56 70" fill="#dc2626"/>'
  . '<path d="M32 56 Q22 72 30 88 Q38 78 42 68" fill="#ef4444" opacity="0.55"/>'
  . '<ellipse cx="60" cy="74" rx="22" ry="21" fill="#4ade80"/>'
  . '<rect x="52" y="50" width="14" height="18" rx="5" fill="#4ade80"/>'
  . '<ellipse cx="59" cy="40" rx="20" ry="17" fill="#4ade80"/>'
  . '<ellipse cx="59" cy="51" rx="13" ry="8" fill="#86efac"/>'
  . '<path d="M48 51 Q59 61 70 51" fill="none" stroke="#16a34a" stroke-width="1.5"/>'
  . '<rect x="52" y="51" width="4" height="5" rx="1.5" fill="white"/>'
  . '<rect x="58" y="51" width="4" height="5" rx="1.5" fill="white"/>'
  . '<rect x="64" y="51" width="4" height="5" rx="1.5" fill="white"/>'
  . '<circle cx="47" cy="35" r="7" fill="white"/>'
  . '<circle cx="48.5" cy="35" r="4.5" fill="#1e1b4b"/>'
  . '<circle cx="50" cy="33.5" r="1.5" fill="white"/>'
  . '<circle cx="71" cy="35" r="7" fill="white"/>'
  . '<circle cx="72.5" cy="35" r="4.5" fill="#1e1b4b"/>'
  . '<circle cx="74" cy="33.5" r="1.5" fill="white"/>'
  . '<path d="M41 29 Q48 25 54 27" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" fill="none"/>'
  . '<path d="M64 27 Q70 25 77 29" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" fill="none"/>'
  . '<polygon points="59,61 51,68 53,82 59,80 65,82 67,68" fill="#dc2626"/>'
  . '<text x="59" y="76" text-anchor="middle" font-size="12" font-weight="900" fill="#fbbf24" font-family="Georgia,serif">S</text>'
  . '</svg>'
  . '<img class="av-photo" id="%IMGID%" src="" alt="">';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<title>Upskill – Student Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap"></noscript>
<style>
/* ── SHARED (inlined from shared.css) ── */
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
html { scroll-behavior:smooth; }
@keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
@keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
.page { padding:2rem; display:none; animation:fadeIn .25s ease; }
.page.active { display:block; }
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
.grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:1.5rem; }
.grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; }
.card { background:var(--navy-card); border:1px solid var(--border); border-radius:16px; padding:1.5rem; transition:border-color .2s; box-shadow:0 2px 8px rgba(30,27,75,.06); }
.card:hover { border-color:rgba(59,130,246,.2); }
.card-title { font-family:var(--font); font-size:.8rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); margin-bottom:1rem; }
.stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; margin-bottom:1rem; font-size:1.3rem; }
.stat-value { font-family:var(--font); font-size:2rem; font-weight:700; letter-spacing:-.03em; margin-bottom:.25rem; }
.stat-label { font-size:.83rem; color:var(--muted); }
.progress-bar { height:8px; background:rgba(30,27,75,.08); border-radius:100px; overflow:hidden; margin:.5rem 0; }
.progress-fill { height:100%; border-radius:100px; background:var(--green); transition:width .8s; }
.activity-item { display:flex; gap:1rem; padding:.9rem 0; border-bottom:1px solid var(--border2); }
.activity-item:last-child { border-bottom:none; }
.activity-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:.3rem; }
.activity-text { font-size:.86rem; color:var(--muted); line-height:1.5; }
.activity-text strong { color:var(--white); font-weight:500; }
.activity-time { font-size:.75rem; color:var(--muted2); margin-top:.2rem; }
.tab { padding:.6rem 1rem; font-family:var(--font); font-size:.85rem; font-weight:500; color:var(--muted); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .2s; }
.tab.active { color:var(--blue); border-bottom-color:var(--blue); }
.tab:hover:not(.active) { color:var(--white); }
.modal-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.5rem; gap:1rem; }
.btn-close { background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.3rem; line-height:1; transition:color .2s; padding:0; }
.btn-close:hover { color:var(--white); }
.form-group { margin-bottom:1.1rem; }
.form-group label { display:block; font-family:var(--font); font-size:.73rem; font-weight:600; color:var(--muted); letter-spacing:.07em; text-transform:uppercase; margin-bottom:.4rem; }
.form-group input, .form-group textarea, .form-group select { width:100%; padding:.8rem 1rem; background:rgba(30,27,75,.04); border:1px solid var(--border); border-radius:10px; color:var(--white); font-family:var(--font-body); font-size:.9rem; outline:none; transition:border-color .2s; resize:vertical; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color:var(--blue); background:rgba(59,130,246,.04); }
.modal-footer { display:flex; gap:.75rem; justify-content:flex-end; margin-top:1.5rem; }
.btn-sm { padding:.4rem .9rem; font-size:.78rem; border-radius:8px; }
.badge { display:inline-flex; align-items:center; padding:.2rem .65rem; border-radius:100px; font-size:.72rem; font-weight:700; font-family:var(--font); flex-shrink:0; }
.hamburger { display:none; width:36px; height:36px; border-radius:9px; background:rgba(30,27,75,.06); border:1px solid var(--border); align-items:center; justify-content:center; cursor:pointer; color:var(--muted); flex-shrink:0; }
.profile-menu { position:fixed; top:64px; right:1rem; width:210px; background:#fff; border:1px solid var(--border); border-radius:14px; box-shadow:0 8px 32px rgba(30,27,75,.16); z-index:9001; overflow:hidden; display:none; animation:fadeIn .15s ease; }
.profile-menu.open { display:block; }
.profile-menu-item { display:flex; align-items:center; gap:.75rem; padding:.78rem 1.1rem; font-family:var(--font); font-size:.85rem; font-weight:500; color:var(--white); cursor:pointer; transition:background .15s; border:none; background:none; width:100%; text-align:left; }
.profile-menu-item:hover { background:rgba(30,27,75,.05); }
.profile-menu-item svg { flex-shrink:0; color:var(--muted); }
.profile-menu-item:hover svg { color:var(--white); }
.profile-menu-sep { border:none; border-top:1px solid var(--border); margin:0; }
.profile-menu-item.danger { color:var(--red); }
.profile-menu-item.danger svg { color:var(--red); opacity:.7; }
.profile-menu-item.danger:hover { background:rgba(239,68,68,.06); }
@media(max-width:768px){
  .hamburger { display:flex; }
  .sidebar { transform:translateX(-100%); }
  .sidebar.open { transform:translateX(0)!important; }
  .main { margin-left:0!important; margin-right:0!important; }
  .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr 1fr; }
  .page { padding:1rem; }
}
@media(max-width:480px){
  .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr; }
}
/* ── END SHARED ── */

:root {
  /* Light sky-blue theme for main content */
  --navy:#d6eeff; --navy-mid:#ffffff; --navy-light:#eff6ff; --navy-card:#ffffff;
  --green:#10b981; --green-dark:#059669; --green-glow:rgba(16,185,129,0.12); --green-dim:rgba(16,185,129,0.08);
  --white:#1e1b4b; --muted:rgba(30,27,75,0.55); --muted2:rgba(30,27,75,0.40);
  --border:rgba(0,0,0,0.09); --border2:rgba(0,0,0,0.05);
  --yellow:#f59e0b; --red:#ef4444; --blue:#3b82f6; --orange:#f59e0b;
  --font:'Sora',sans-serif; --font-body:'DM Sans',sans-serif; --font-ar:'Cairo',sans-serif;
  --sidebar-w:240px;
}
body { background:var(--navy); color:var(--white); font-family:var(--font-body); min-height:100vh; display:flex; overflow-x:hidden; }

/* SIDEBAR — deep purple, overrides vars locally */
.sidebar {
  --navy:#2e2a7a; --navy-mid:#3d3890; --navy-light:#4a4499; --navy-card:rgba(255,255,255,0.07);
  --white:#ffffff; --muted:rgba(255,255,255,0.62); --muted2:rgba(255,255,255,0.45);
  --border:rgba(255,255,255,0.1); --border2:rgba(255,255,255,0.06);
  width:var(--sidebar-w); background:var(--navy-mid); border-right:1px solid var(--border);
  display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:200; transition:transform .3s;
}
.sidebar-logo { display:flex; align-items:center; gap:.6rem; padding:1.4rem 1.3rem; border-bottom:1px solid var(--border); }
.sidebar-logo span { font-family:var(--font); font-weight:700; font-size:1rem; color:#fff; }
.sidebar-logo em { color:#f59e0b; font-style:normal; }
.lang-toggle { display:flex; gap:.4rem; padding:.55rem 1.3rem; border-bottom:1px solid var(--border); }
.lang-pill { font-size:.7rem; font-family:var(--font); font-weight:600; padding:.22rem .6rem; border-radius:100px; border:1px solid var(--border); color:var(--muted); cursor:pointer; transition:all .2s; }
.lang-pill.active { background:rgba(245,158,11,.2); border-color:rgba(245,158,11,.5); color:#f59e0b; }
.sidebar-user { padding:1.1rem 1.3rem; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:.75rem; }
.avatar { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#bfdbfe,#ddd6fe); border:2px solid rgba(245,158,11,.4); display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; position:relative; }
.av-dino { width:100%; height:100%; display:block; }
.av-photo { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius:50%; display:none; }
.user-info .name { font-family:var(--font); font-size:.84rem; font-weight:600; line-height:1.2; color:#fff; }
.user-info .role-tag { font-size:.7rem; color:#f59e0b; background:rgba(245,158,11,.15); padding:.1rem .5rem; border-radius:100px; margin-top:.2rem; display:inline-block; }
.sidebar-nav { flex:1; padding:.9rem .75rem; overflow-y:auto; }
.nav-item { display:flex; align-items:center; gap:.72rem; padding:.62rem .85rem; border-radius:10px; cursor:pointer; color:var(--muted); font-size:.87rem; font-family:var(--font); font-weight:500; transition:all .2s; margin-bottom:.15rem; border:1px solid transparent; }
.nav-item svg { flex-shrink:0; opacity:.7; }
.nav-item:hover { background:rgba(255,255,255,.08); color:#fff; }
.nav-item.active { background:rgba(245,158,11,.18); color:#f59e0b; border-color:rgba(245,158,11,.3); }
.nav-item.active svg { opacity:1; }
.nav-badge { margin-left:auto; background:#f59e0b; color:#1e1b4b; font-size:.62rem; font-weight:700; padding:.12rem .42rem; border-radius:100px; font-family:var(--font); }
.sidebar-bottom { padding:.9rem; border-top:1px solid var(--border); }
.btn-logout { display:flex; align-items:center; gap:.6rem; width:100%; padding:.62rem .85rem; border-radius:10px; background:transparent; border:1px solid var(--border); color:var(--muted); font-family:var(--font); font-size:.84rem; cursor:pointer; transition:all .2s; }
.btn-logout:hover { border-color:#ef4444; color:#ef4444; background:rgba(239,68,68,.08); }

/* MAIN */
.main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; background:var(--navy); }
.topbar { background:rgba(214,238,255,0.92); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); padding:.9rem 2rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
.topbar-title { font-family:var(--font); font-size:1rem; font-weight:600; color:var(--white); }
.topbar-actions { display:flex; align-items:center; gap:.75rem; }
.btn-icon { width:36px; height:36px; border-radius:9px; background:rgba(30,27,75,.06); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--muted); transition:all .2s; position:relative; }
.btn-icon:hover { border-color:var(--blue); color:var(--blue); background:rgba(59,130,246,.08); }

/* CARDS */

/* STAT CARDS */
.stat-icon.green { background:rgba(16,185,129,.1); }
.stat-icon.yellow { background:rgba(245,158,11,.1); }
.stat-icon.red { background:rgba(239,68,68,.1); }
.stat-icon.blue { background:rgba(59,130,246,.1); }

/* PROGRESS */
.progress-fill.yellow { background:var(--yellow); }
.module-row { display:flex; align-items:center; gap:1rem; padding:.75rem 0; border-bottom:1px solid var(--border2); }
.module-row:last-child { border-bottom:none; }
.module-name { flex:1; font-size:.88rem; font-family:var(--font); font-weight:500; color:var(--white); }
.module-pct { font-family:var(--font); font-size:.82rem; font-weight:600; color:var(--green); min-width:38px; text-align:right; }
.module-bar { flex:2; }

/* ACTIVITY */
.activity-dot.green { background:var(--green); }
.activity-dot.blue { background:var(--blue); }
.activity-dot.red { background:var(--red); }

/* HERO WELCOME */
.hero-section { text-align:center; padding:2.5rem 1rem 1.75rem; }
.mascot-wrap { display:inline-flex; align-items:center; justify-content:center; width:110px; height:110px; border-radius:50%; background:linear-gradient(135deg,#bfdbfe,#ddd6fe); margin-bottom:1.25rem; font-size:3.2rem; box-shadow:0 8px 32px rgba(59,130,246,.18); overflow:hidden; position:relative; }
#mascot-av-img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius:50%; display:none; }
.hero-hello { font-family:var(--font); font-size:2.6rem; font-weight:800; letter-spacing:-.04em; margin-bottom:.4rem; background:linear-gradient(135deg,#3b82f6,#7c3aed); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
.hero-sub { color:var(--muted); font-size:1rem; }

/* HOME CARDS */
.home-cards { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; max-width:860px; margin:0 auto; }

/* STARS OF MONTH */
.stars-card { background:linear-gradient(150deg,#2d2a6e,#3d3a9f); border-radius:20px; padding:1.6rem; color:#fff; box-shadow:0 8px 32px rgba(45,42,110,.28); }
.stars-header { display:flex; align-items:center; gap:.75rem; margin-bottom:1.25rem; }
.stars-crown { font-size:1.4rem; }
.stars-title { font-family:var(--font); font-size:1rem; font-weight:700; color:#fff; }
.stars-month { font-size:.73rem; color:rgba(255,255,255,.6); margin-top:.1rem; }
.star-item { display:flex; align-items:center; gap:.7rem; background:linear-gradient(135deg,#f59e0b,#f97316); border-radius:12px; padding:.8rem .95rem; margin-bottom:.6rem; }
.star-item:last-child { margin-bottom:0; }
.star-rank { font-size:.95rem; min-width:22px; text-align:center; }
.star-av { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.28); display:flex; align-items:center; justify-content:center; font-family:var(--font); font-weight:700; font-size:.8rem; color:#fff; flex-shrink:0; }
.star-name { flex:1; font-family:var(--font); font-weight:600; font-size:.88rem; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.star-pct { font-family:var(--font); font-size:.78rem; font-weight:700; color:rgba(255,255,255,.9); }
.stars-empty { text-align:center; padding:2rem 1rem; color:rgba(255,255,255,.55); font-size:.85rem; }

/* LEADERBOARD */
.leaderboard-card { background:linear-gradient(150deg,#f97316,#fbbf24); border-radius:20px; padding:1.6rem; color:#fff; box-shadow:0 8px 32px rgba(249,115,22,.22); }
.lb-header { display:flex; align-items:center; gap:.75rem; margin-bottom:1.25rem; }
.lb-trophy { font-size:1.4rem; }
.lb-title { font-family:var(--font); font-size:1rem; font-weight:700; color:#fff; }
.lb-sub { font-size:.73rem; color:rgba(255,255,255,.7); margin-top:.1rem; }
.lb-item { display:flex; align-items:center; gap:.7rem; background:rgba(255,255,255,.22); border-radius:12px; padding:.7rem .95rem; margin-bottom:.55rem; }
.lb-item:last-child { margin-bottom:0; }
.lb-av { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.32); display:flex; align-items:center; justify-content:center; font-family:var(--font); font-weight:700; font-size:.78rem; color:#fff; flex-shrink:0; }
.lb-info { flex:1; min-width:0; }
.lb-name { font-family:var(--font); font-weight:600; font-size:.86rem; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.lb-action { font-size:.73rem; color:rgba(255,255,255,.82); margin-top:.1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.lb-empty { text-align:center; padding:2rem 1rem; color:rgba(255,255,255,.6); font-size:.85rem; }

/* MY CLASS */
.classmate-item { display:flex; align-items:center; gap:.75rem; padding:.7rem 0; border-bottom:1px solid var(--border2); }
.classmate-item:last-child { border-bottom:none; }
.cm-av { width:34px; height:34px; border-radius:50%; background:rgba(59,130,246,.1); border:2px solid rgba(59,130,246,.2); display:flex; align-items:center; justify-content:center; font-family:var(--font); font-weight:700; font-size:.78rem; color:var(--blue); flex-shrink:0; }
.cm-name { flex:1; font-family:var(--font); font-size:.87rem; font-weight:500; color:var(--white); }
.cm-pct { font-family:var(--font); font-size:.8rem; font-weight:600; color:var(--green); }

/* BADGE */
.badge.pending { background:rgba(245,158,11,.12); color:var(--yellow); border:1px solid rgba(245,158,11,.3); }
.badge.submitted { background:rgba(16,185,129,.12); color:var(--green); border:1px solid rgba(16,185,129,.3); }
.badge.overdue { background:rgba(239,68,68,.12); color:var(--red); border:1px solid rgba(239,68,68,.3); }

/* MODAL */
.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:none;align-items:flex-start;justify-content:center;padding:2rem 1rem;overflow-y:auto;pointer-events:none; }
.modal-overlay.open { display:flex;pointer-events:auto; }
.modal { background:#fff;border:1px solid var(--border);border-radius:20px;padding:2rem;max-width:520px;width:100%;margin:auto;animation:slideUp .25s ease;box-shadow:0 20px 60px rgba(30,27,75,.12); }
.modal-header { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;gap:1rem; }
.modal-header h3 { font-family:var(--font);font-size:1.1rem;font-weight:700;color:var(--white); }
.modal-footer { display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem; }
.btn-close { background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.3rem;line-height:1;transition:color .2s;padding:0; }
.btn-secondary { padding:.75rem 1.4rem;background:rgba(30,27,75,.06);border:1px solid var(--border);border-radius:10px;color:var(--muted);font-family:var(--font);font-size:.88rem;font-weight:600;cursor:pointer;transition:.2s; }
.btn-secondary:hover { color:var(--white);border-color:rgba(30,27,75,.25); }
.assign-item { border:1px solid var(--border); border-radius:14px; padding:1.2rem 1.4rem; margin-bottom:.75rem; transition:border-color .2s,background .2s; cursor:pointer; background:var(--navy-card); box-shadow:0 1px 4px rgba(30,27,75,.05); }
.assign-item:hover { border-color:rgba(59,130,246,.3); }
.assign-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:.5rem; }
.assign-title { font-family:var(--font); font-size:.95rem; font-weight:600; color:var(--white); }
.assign-desc { font-size:.83rem; color:var(--muted); margin-bottom:.6rem; line-height:1.5; }
.assign-meta { display:flex; align-items:center; gap:1rem; font-size:.78rem; color:var(--muted2); }

/* TABS */
.tabs { display:flex; gap:.5rem; margin-bottom:1.5rem; border-bottom:1px solid var(--border); padding-bottom:0; }

/* QUIZ */
.quiz-card { background:var(--navy-card); border:1px solid var(--border); border-radius:16px; padding:1.5rem; transition:all .2s; cursor:pointer; display:flex; flex-direction:column; box-shadow:0 2px 8px rgba(30,27,75,.06); }
.quiz-card:hover { border-color:rgba(59,130,246,.35); transform:translateY(-2px); box-shadow:0 8px 24px rgba(30,27,75,.1); }
.quiz-icon { font-size:2rem; margin-bottom:.8rem; }
.quiz-title { font-family:var(--font); font-size:1rem; font-weight:700; margin-bottom:.4rem; color:var(--white); }
.quiz-desc { font-size:.83rem; color:var(--muted); margin-bottom:1rem; line-height:1.5; flex:1; }
.quiz-meta { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem; }
.quiz-meta span { font-size:.75rem; background:rgba(30,27,75,.06); border:1px solid var(--border); padding:.2rem .6rem; border-radius:100px; color:var(--muted); font-family:var(--font); }

/* BUTTONS */
.btn-primary { background:linear-gradient(135deg,#3b82f6,#7c3aed); color:#fff; font-family:var(--font); font-weight:700; font-size:.9rem; padding:.75rem 1.5rem; border:none; border-radius:10px; cursor:pointer; transition:opacity .2s,transform .15s; display:inline-flex; align-items:center; gap:.5rem; }
.btn-primary:hover { opacity:.9; transform:translateY(-1px); }

/* TOAST */
.toast { position:fixed; bottom:2rem; right:2rem; background:#fff; border:1px solid var(--border); border-radius:12px; padding:.9rem 1.4rem; font-family:var(--font); font-size:.85rem; color:var(--white); z-index:9999; transform:translateY(100px); opacity:0; transition:all .3s; display:flex; align-items:center; gap:.6rem; box-shadow:0 8px 24px rgba(30,27,75,.12); }
.toast.show { transform:translateY(0); opacity:1; }
.toast-dot { width:8px; height:8px; border-radius:50%; background:var(--green); }
.toast.error { border-color:var(--red); }
.toast.error .toast-dot { background:var(--red); }
.toast.warn  { border-color:var(--yellow); }
.toast.warn  .toast-dot  { background:var(--yellow); }

.sidebar-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:199; pointer-events:none; }
.sidebar-backdrop.open { display:block; pointer-events:auto; }

@media(max-width:768px){
  .hamburger { display:flex; }
  .sidebar { transform:translateX(-100%); }
  .sidebar.open { transform:translateX(0)!important; }
  .main { margin-left:0!important; margin-right:0!important; width:100%; }
  .page { padding:1rem; }
  .topbar { padding:.75rem 1rem; }
  .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr 1fr; }
  .home-cards { grid-template-columns:1fr; }
  .hero-hello { font-size:2rem; }
  .mascot-wrap { width:90px; height:90px; font-size:2.6rem; }
}
@media(max-width:480px){
  .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr; }
  .card { padding:1rem; }
  .page { padding:.75rem; }
}

/* NOTIFICATION PANEL */
.notif-panel { position:fixed; top:64px; right:1rem; width:320px; background:#fff; border:1px solid var(--border); border-radius:16px; box-shadow:0 8px 32px rgba(30,27,75,.14); z-index:9100; overflow:hidden; display:none; animation:fadeIn .15s ease; }
.notif-panel.open { display:block; }
.notif-panel-header { display:flex; align-items:center; justify-content:space-between; padding:.9rem 1.1rem; border-bottom:1px solid var(--border); }
.notif-panel-title { font-family:var(--font); font-size:.82rem; font-weight:700; color:var(--white); }
.notif-mark-all { font-family:var(--font); font-size:.72rem; color:var(--blue); cursor:pointer; background:none; border:none; padding:0; user-select:none; }
.notif-mark-all:hover { text-decoration:underline; }
.notif-list { max-height:320px; overflow-y:auto; }
.notif-item { display:flex; gap:.75rem; padding:.85rem 1.1rem; border-bottom:1px solid rgba(30,27,75,.05); cursor:pointer; transition:background .15s; align-items:flex-start; user-select:none; -webkit-user-select:none; }
.notif-item:hover { background:rgba(59,130,246,.08); }
.notif-item:active { background:rgba(59,130,246,.15); }
.notif-item.unread { background:rgba(59,130,246,.06); border-left:3px solid var(--blue); }
.notif-icon { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
.notif-icon.new_assignment { background:rgba(59,130,246,.1); }
.notif-icon.overdue { background:rgba(245,158,11,.1); }
.notif-icon.submission { background:rgba(16,185,129,.1); }
.notif-icon.info { background:rgba(30,27,75,.06); }
.notif-icon.announcement { background:rgba(251,146,60,.1); }
.notif-icon.quiz { background:rgba(167,139,250,.1); }
.notif-icon.message { background:rgba(62,207,120,.1); }
.notif-icon.lesson_note { background:rgba(99,102,241,.1); }
.notif-content { flex:1; min-width:0; }
.notif-title { font-family:var(--font); font-size:.8rem; font-weight:600; margin-bottom:.15rem; color:var(--white); }
.notif-body { font-size:.78rem; color:var(--muted); line-height:1.4; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:220px; }
.notif-time { font-size:.7rem; color:var(--muted2); margin-top:.2rem; }
.notif-unread-dot { width:7px; height:7px; border-radius:50%; background:var(--blue); flex-shrink:0; margin-top:6px; }
.notif-empty { padding:2rem; text-align:center; color:var(--muted); font-size:.85rem; }
.notif-badge { position:absolute; top:3px; right:3px; min-width:16px; height:16px; background:var(--red); color:#fff; font-size:.58rem; font-weight:700; border-radius:100px; display:flex; align-items:center; justify-content:center; font-family:var(--font); padding:0 3px; border:2px solid var(--navy); }

/* PROFILE MENU */

/* COMING SOON */
.coming-soon { text-align:center; padding:5rem 2rem; }
.coming-soon .cs-emoji { font-size:3.5rem; margin-bottom:1rem; }
.coming-soon h3 { font-family:var(--font); font-size:1.3rem; font-weight:700; color:var(--white); margin-bottom:.5rem; }
.coming-soon p { color:var(--muted); font-size:.9rem; max-width:400px; margin:0 auto; line-height:1.6; }

/* HOW-TO GRID */
.howto-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.25rem; }
.howto-card { background:var(--navy-card); border:1px solid var(--border); border-radius:18px; overflow:hidden; transition:all .2s; box-shadow:0 2px 10px rgba(30,27,75,.07); }
.howto-card:hover { border-color:rgba(59,130,246,.3); transform:translateY(-3px); box-shadow:0 10px 28px rgba(30,27,75,.12); }
.howto-thumb { position:relative; background:linear-gradient(135deg,var(--from),var(--to)); aspect-ratio:16/9; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.howto-play { width:52px; height:52px; background:rgba(255,255,255,.25); backdrop-filter:blur(4px); border-radius:50%; display:flex; align-items:center; justify-content:center; transition:transform .2s,background .2s; }
.howto-card:hover .howto-play { background:rgba(255,255,255,.38); transform:scale(1.1); }
.howto-play svg { margin-left:4px; }
.howto-thumb-icon { position:absolute; top:10px; right:12px; font-size:1.6rem; opacity:.6; }
.howto-body { padding:1.1rem 1.2rem 1.3rem; }
.howto-card-title { font-family:var(--font); font-size:.97rem; font-weight:700; color:var(--white); margin-bottom:.35rem; }
.howto-card-desc { font-size:.82rem; color:var(--muted); line-height:1.55; margin-bottom:1rem; }
.howto-card-btn { display:inline-flex; align-items:center; gap:.4rem; font-size:.8rem; font-family:var(--font); font-weight:600; color:var(--blue); background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.2); padding:.38rem .9rem; border-radius:8px; cursor:pointer; transition:all .2s; }
.howto-card-btn:hover { background:rgba(59,130,246,.16); }

/* AVATAR UPLOAD */
.settings-av-wrap { position:relative; }
.av-upload-btn { position:absolute; bottom:-3px; right:-3px; width:24px; height:24px; background:#3b82f6; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#fff; border:2px solid var(--navy); transition:background .2s; }
.av-upload-btn:hover { background:#2563eb; }

</style>
<style id="lang-hide">body{visibility:hidden}</style>
</head>
<body id="body">
<a href="#main-content" class="skip-link" style="position:absolute;top:-40px;left:0;background:var(--green);color:#0f1d2e;padding:.5rem 1rem;font-family:var(--font);font-weight:700;font-size:.85rem;z-index:9999;border-radius:0 0 8px 0;transition:top .2s;text-decoration:none;">Skip to content</a>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">
  <div class="sidebar-logo">
    <svg width="26" height="26" viewBox="0 0 28 28" fill="none"><rect width="28" height="28" rx="8" fill="#3ecf78"/><path d="M8 14l4 4 8-8" stroke="#0f1d2e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Up<em>skill</em></span>
  </div>
  <div class="lang-toggle">
    <div class="lang-pill active" id="pill-fr" onclick="setLang('fr')">🇫🇷 FR</div>
    <div class="lang-pill" id="pill-en" onclick="setLang('en')">🇬🇧 EN</div>
  </div>
  <div class="sidebar-user">
    <div class="avatar" id="sidebar-avatar"><?= str_replace(['%ID%','%IMGID%'], ['sidebar-dino-svg','sidebar-av-img'], $dinoAvatarSvg) ?></div>
    <div class="user-info">
      <div class="name" id="sidebar-name"><?= $full_name ?></div>
      <div class="role-tag" id="role-label">Student</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-item active" role="button" tabindex="0" onclick="navigate('home',this)" id="nav-home">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span id="nav-home-lbl">Dashboard</span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('myclass',this)" id="nav-myclass">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span id="nav-myclass-lbl">My Class</span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('assignments',this)" id="nav-assign">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <span id="nav-assign-lbl">Assignments</span>
      <span class="nav-badge" id="assign-nav-badge" style="display:none;"></span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('feed',this)" id="nav-feed">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      <span id="nav-feed-lbl">Lesson Notes</span>
    </div>

    <div class="nav-item" role="button" tabindex="0" onclick="navigate('progress',this)" id="nav-progress">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span id="nav-progress-lbl">Progress</span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('quizzes',this)" id="nav-quiz">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
      <span id="nav-quiz-lbl">Challenge</span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('announcements',this)" id="nav-announcements">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M11 5.882V19.24a1.76 1.76 0 0 1-3.417.592l-2.147-6.15M18 13a3 3 0 1 0 0-6M5.436 13.683A4.001 4.001 0 0 1 7 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 0 1-1.564-.317z"/></svg>
      <span id="nav-ann-lbl">Announcements</span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('howto',this)" id="nav-howto">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span id="nav-howto-lbl">How-to</span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('settings',this)" id="nav-set">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span id="nav-set-lbl">Settings</span>
    </div>
  </nav>
  <div class="sidebar-bottom">
    <button class="btn-logout" onclick="logout()" aria-label="Log out">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span id="logout-lbl">Log out</span>
    </button>
  </div>
</aside>

<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleSidebar()"></div>

<!-- MAIN -->
<main class="main" role="main" id="main-content">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:.75rem;">
      <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="topbar-title" id="topbar-title">Dashboard</div>
    </div>
    <div class="topbar-actions">
      <div class="btn-icon" id="notif-btn" onclick="toggleNotifPanel()" title="Notifications" style="cursor:pointer;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="notif-badge" id="notif-badge" style="display:none;"></span>
      </div>
      <div class="avatar" id="topbar-avatar" style="cursor:pointer;" onclick="toggleProfileMenu(event)" title="My profile"><?= str_replace(['%ID%','%IMGID%'], ['topbar-dino-svg','topbar-av-img'], $dinoAvatarSvg) ?></div>
    </div>
  </div>

  <!-- HOME PAGE -->
  <div class="page active" id="page-home">
    <!-- Hero welcome -->
    <div class="hero-section">
      <div class="mascot-wrap" aria-hidden="true">
        <img id="mascot-av-img" src="" alt="">
        <svg id="mascot-dino-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 110 120" width="72" height="78">
          <!-- Red cape -->
          <path d="M32 56 Q8 76 14 100 Q30 88 42 68 Q46 86 42 102 Q60 90 56 70" fill="#dc2626"/>
          <path d="M32 56 Q22 72 30 88 Q38 78 42 68" fill="#ef4444" opacity="0.55"/>
          <!-- Cape yellow trim -->
          <path d="M32 56 Q26 68 30 76" stroke="#fbbf24" stroke-width="2" fill="none" stroke-linecap="round"/>
          <!-- Body -->
          <ellipse cx="60" cy="74" rx="22" ry="21" fill="#4ade80"/>
          <!-- Neck -->
          <rect x="52" y="50" width="14" height="18" rx="5" fill="#4ade80"/>
          <!-- Head -->
          <ellipse cx="59" cy="40" rx="20" ry="17" fill="#4ade80"/>
          <!-- Snout -->
          <ellipse cx="59" cy="51" rx="13" ry="8" fill="#86efac"/>
          <!-- Mouth line -->
          <path d="M48 51 Q59 61 70 51" fill="none" stroke="#16a34a" stroke-width="1.5"/>
          <!-- Teeth -->
          <rect x="52" y="51" width="4" height="5" rx="1.5" fill="white"/>
          <rect x="58" y="51" width="4" height="5" rx="1.5" fill="white"/>
          <rect x="64" y="51" width="4" height="5" rx="1.5" fill="white"/>
          <!-- Left eye -->
          <circle cx="47" cy="35" r="7" fill="white"/>
          <circle cx="48.5" cy="35" r="4.5" fill="#1e1b4b"/>
          <circle cx="50" cy="33.5" r="1.5" fill="white"/>
          <!-- Right eye -->
          <circle cx="71" cy="35" r="7" fill="white"/>
          <circle cx="72.5" cy="35" r="4.5" fill="#1e1b4b"/>
          <circle cx="74" cy="33.5" r="1.5" fill="white"/>
          <!-- Heroic eyebrows -->
          <path d="M41 29 Q48 25 54 27" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" fill="none"/>
          <path d="M64 27 Q70 25 77 29" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" fill="none"/>
          <!-- Tiny arm -->
          <path d="M39 72 Q27 68 29 76 Q32 82 40 79" fill="#4ade80"/>
          <!-- Arm claws -->
          <line x1="28" y1="77" x2="25" y2="82" stroke="#22c55e" stroke-width="1.5" stroke-linecap="round"/>
          <line x1="31" y1="80" x2="29" y2="85" stroke="#22c55e" stroke-width="1.5" stroke-linecap="round"/>
          <!-- Tail -->
          <path d="M40 88 Q22 94 18 106 Q14 114 20 116" stroke="#4ade80" stroke-width="8" stroke-linecap="round" fill="none"/>
          <!-- Legs -->
          <rect x="47" y="93" width="11" height="16" rx="4" fill="#4ade80"/>
          <rect x="62" y="93" width="11" height="16" rx="4" fill="#4ade80"/>
          <!-- Feet -->
          <ellipse cx="52" cy="109" rx="10" ry="4" fill="#22c55e"/>
          <ellipse cx="67" cy="109" rx="10" ry="4" fill="#22c55e"/>
          <!-- Toe claws -->
          <line x1="45" y1="111" x2="42" y2="116" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round"/>
          <line x1="51" y1="113" x2="50" y2="117" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round"/>
          <line x1="57" y1="111" x2="59" y2="115" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round"/>
          <line x1="60" y1="111" x2="57" y2="116" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round"/>
          <line x1="66" y1="113" x2="65" y2="117" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round"/>
          <line x1="72" y1="111" x2="74" y2="115" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round"/>
          <!-- Superman chest shield -->
          <polygon points="59,61 51,68 53,82 59,80 65,82 67,68" fill="#dc2626"/>
          <text x="59" y="76" text-anchor="middle" font-size="12" font-weight="900" fill="#fbbf24" font-family="Georgia,serif">S</text>
        </svg>
      </div>
      <h1 class="hero-hello"><span id="welcome-name"><?= htmlspecialchars(explode(' ', $full_name)[0]) ?></span> !</h1>
      <p class="hero-sub" id="welcome-sub">Your learning dashboard</p>
    </div>

    <!-- Stars of the Month + Leaderboard -->
    <div class="home-cards">
      <div class="stars-card">
        <div class="stars-header">
          <div class="stars-crown">👑</div>
          <div>
            <div class="stars-title" id="stars-title">Students of the month</div>
            <div class="stars-month" id="stars-month">This month</div>
          </div>
        </div>
        <div id="stars-list"><div class="stars-empty">Loading…</div></div>
      </div>

      <div class="leaderboard-card">
        <div class="lb-header">
          <div class="lb-trophy">🏆</div>
          <div>
            <div class="lb-title" id="lb-title">Class activity</div>
            <div class="lb-sub" id="lb-sub">Recent activity</div>
          </div>
        </div>
        <div id="leaderboard-list"><div class="lb-empty">Loading…</div></div>
      </div>
    </div>

    <!-- RECENT ACTIVITY -->
    <div style="max-width:860px;margin:1.5rem auto 0;">
      <div class="card">
        <div class="card-title" id="activity-feed-title">Recent activity</div>
        <div id="activity-feed"></div>
      </div>
    </div>
  </div>

  <!-- MY CLASS PAGE -->
  <div class="page" id="page-myclass">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;color:var(--white);" id="myclass-title">My Class</h2>
      <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="myclass-sub">Your course and classmates</p>
    </div>

    <!-- Group info banner (loaded from api_classes.php) -->
    <div id="myclass-group-section" style="margin-bottom:1.25rem;">
      <div class="loading-overlay"><div class="spinner"></div><span style="margin-left:.5rem;font-size:.88rem;color:var(--muted);" id="group-loading-lbl">Loading group…</span></div>
    </div>

    <div class="grid-2">
      <div class="card" style="background:linear-gradient(135deg,rgba(59,130,246,.08),rgba(124,58,237,.05));border-color:rgba(59,130,246,.2);">
        <div class="card-title" id="myclass-course-label">Current course</div>
        <div id="myclass-assigned">
          <div style="font-family:var(--font);font-size:1.1rem;font-weight:700;color:var(--white);margin-bottom:.5rem;" id="myclass-course-name">—</div>
          <div style="color:var(--muted);font-size:.84rem;margin-bottom:.35rem;">👨‍🏫 <span id="myclass-teacher">—</span></div>
          <div style="color:var(--muted);font-size:.84rem;margin-bottom:1.2rem;">📅 <span id="myclass-schedule">—</span></div>
          <div style="display:flex;justify-content:space-between;align-items:center;font-size:.8rem;color:var(--muted);margin-bottom:.4rem;"><span id="myclass-att-lbl">Taux de présence</span><strong style="font-family:var(--font);color:var(--green);" id="myclass-att-pct">–</strong></div>
          <div class="progress-bar"><div class="progress-fill" id="myclass-att-bar" style="width:0%"></div></div>
          <div style="font-size:.77rem;color:var(--muted);margin-top:.5rem;" id="myclass-att-detail">—</div>
          <div id="myclass-zoom-wrap" style="display:none;margin-top:1.1rem;">
            <a id="myclass-zoom-link" href="#" target="_blank" rel="noopener noreferrer"
              style="display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.1rem;background:rgba(59,130,246,.12);border:1px solid rgba(59,130,246,.3);border-radius:10px;color:var(--blue);font-family:var(--font);font-size:.85rem;font-weight:600;text-decoration:none;transition:background .2s;"
              onmouseover="this.style.background='rgba(59,130,246,.2)'" onmouseout="this.style.background='rgba(59,130,246,.12)'">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
              <span id="myclass-zoom-lbl">Join class →</span>
            </a>
          </div>
        </div>
        <div id="myclass-empty" style="display:none;text-align:center;padding:1.5rem 0;">
          <div style="font-size:2rem;margin-bottom:.6rem;">📚</div>
          <div style="font-family:var(--font);font-size:.9rem;font-weight:600;color:var(--white);" id="myclass-empty-txt">No course assigned yet</div>
        </div>
      </div>
      <div class="card">
        <div class="card-title" id="myclass-mates-lbl">Classmates</div>
        <div id="myclass-mates"><div style="text-align:center;padding:2rem;color:var(--muted);font-size:.85rem;">Loading…</div></div>
      </div>
    </div>
  </div>

  <!-- ASSIGNMENTS PAGE -->
  <div class="page" id="page-assignments">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;letter-spacing:-.02em;" id="assign-page-title">Assignments</h2>
        <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="assign-page-sub">3 pending · 1 overdue · 2 submitted</p>
      </div>
    </div>
    <div class="tabs">
      <div class="tab active" onclick="filterTab(this,'all','assign')" id="tab-all-assign">All</div>
      <div class="tab" onclick="filterTab(this,'pending','assign')" id="tab-pending-assign">Pending</div>
      <div class="tab" onclick="filterTab(this,'overdue','assign')" id="tab-overdue-assign">Overdue</div>
      <div class="tab" onclick="filterTab(this,'submitted','assign')" id="tab-done-assign">Submitted</div>
    </div>
    <div id="assign-list"></div>
  </div>

  <!-- QUIZZES PAGE -->
  <div class="page" id="page-quizzes">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="quiz-page-title">Quizzes</h2>
      <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="quiz-page-sub">Test your knowledge with timed quizzes</p>
    </div>
    <div class="tabs">
      <div class="tab active" onclick="filterTab(this,'all','quiz')" id="tab-all-quiz">All</div>
      <div class="tab" onclick="filterTab(this,'available','quiz')" id="tab-avail-quiz">Available</div>
      <div class="tab" onclick="filterTab(this,'done','quiz')" id="tab-done-quiz">Completed</div>
    </div>
    <div id="quiz-list" class="grid-3"></div>
  </div>

  <!-- LESSON FEED PAGE -->
  <div class="page" id="page-feed">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.6rem;font-weight:800;letter-spacing:-.03em;" id="feed-title">Lesson Notes</h2>
      <p style="color:var(--muted);font-size:.9rem;margin-top:.3rem;" id="feed-sub">Summaries posted by your teacher after each Zoom session</p>
    </div>
    <div id="feed-list"><p style="color:var(--muted);font-size:.85rem;">Loading…</p></div>
  </div>

  <!-- HOW-TO PAGE -->
  <div class="page" id="page-howto">
    <div style="margin-bottom:1.75rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;color:var(--white);" id="howto-title">How to use the platform?</h2>
      <p style="color:var(--muted);font-size:.88rem;margin-top:.3rem;" id="howto-sub">Watch these short videos to discover each feature.</p>
    </div>
    <div class="howto-grid" id="howto-grid">
      <!-- Cards injected by renderHowTo() -->
    </div>
  </div>

  <!-- PROGRESS PAGE -->
  <div class="page" id="page-progress">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="prog-page-title">Progress</h2>
      <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="prog-page-sub">Track your learning journey module by module</p>
    </div>
    <div class="grid-2" style="margin-bottom:1.5rem;">
      <div class="card">
        <div class="card-title" id="overall-title">Overall progress</div>
        <div style="display:flex;align-items:center;gap:1.5rem;margin-bottom:1rem;">
          <div style="position:relative;width:90px;height:90px;flex-shrink:0;">
            <svg viewBox="0 0 90 90" width="90" height="90">
              <circle cx="45" cy="45" r="38" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="9"/>
              <circle id="prog-circle" cx="45" cy="45" r="38" fill="none" stroke="#3ecf78" stroke-width="9" stroke-linecap="round" stroke-dasharray="238.76" stroke-dashoffset="76.4" transform="rotate(-90 45 45)"/>
            </svg>
            <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
              <span style="font-family:var(--font);font-size:1.2rem;font-weight:700;" id="prog-circle-pct">68%</span>
              <span style="font-size:.6rem;color:var(--muted);" id="done-lbl">done</span>
            </div>
          </div>
          <div>
            <div style="font-family:var(--font);font-size:1.5rem;font-weight:700;"><span id="sessions-present">0</span> <span style="font-size:1rem;color:var(--muted);font-weight:400;" id="hrs-of"></span></div>
            <div style="color:var(--muted);font-size:.83rem;margin:.3rem 0;" id="course-session">General English · Session 2</div>
            <div style="font-size:.78rem;background:var(--green-glow);color:var(--green);border:1px solid rgba(62,207,120,.3);padding:.25rem .65rem;border-radius:100px;display:inline-block;font-family:var(--font);font-weight:600;" id="on-track">On track 🎯</div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-title" id="scores-title">Assignment performance</div>
        <div id="progress-assign-stats">
          <!-- Populated by updateProgress() -->
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-title" id="modules-title">Progress by subject</div>
      <div id="modules-by-subject"></div>
    </div>
  </div>

  <!-- SETTINGS PAGE -->
  <div class="page" id="page-settings">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="settings-title">Settings</h2>
    </div>
    <div class="grid-2">
      <div class="card">
        <div class="card-title" id="profile-title">Profile</div>

        <!-- Avatar upload row -->
        <div style="display:flex;align-items:center;gap:1.2rem;margin-bottom:1.75rem;">
          <div style="position:relative;flex-shrink:0;">
            <div class="settings-av-wrap" id="settings-av-wrap">
              <div class="avatar" style="width:64px;height:64px;" id="settings-avatar"><?= str_replace(['%ID%','%IMGID%'], ['settings-dino-svg','settings-av-img'], $dinoAvatarSvg) ?></div>
              <img id="settings-av-img" src="" alt="" style="display:none;width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid rgba(245,158,11,.4);">
            </div>
            <label for="avatar-input" class="av-upload-btn" title="Change photo">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            </label>
            <input type="file" id="avatar-input" accept="image/*" style="display:none;" onchange="handleAvatarUpload(this)">
          </div>
          <div>
            <div style="font-family:var(--font);font-weight:600;font-size:.95rem;color:var(--white);" id="settings-name"><?= $full_name ?></div>
            <div style="color:var(--muted);font-size:.82rem;margin-top:.2rem;" id="settings-role">Student · General English S2</div>
            <label for="avatar-input" style="display:inline-block;margin-top:.5rem;font-size:.75rem;color:var(--blue);cursor:pointer;font-family:var(--font);font-weight:500;" id="lbl-change-photo">Change photo</label>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:1.1rem;">
          <label style="display:block;font-family:var(--font);font-size:.73rem;font-weight:600;color:var(--muted);letter-spacing:.07em;text-transform:uppercase;margin-bottom:.45rem;" id="lbl-fullname">Full name</label>
          <input type="text" id="pref-name" style="width:100%;padding:.8rem 1rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;transition:border-color .2s;"
            onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--border)'"
            value="<?= htmlspecialchars($full_name) ?>">
        </div>
        <div id="save-profile-error" style="display:none;color:var(--red);font-size:.82rem;margin-bottom:.6rem;"></div>
        <button class="btn-primary" onclick="saveProfile()" id="save-btn">
          <span id="save-btn-text">Save</span>
        </button>
      </div>
      <div class="card">
        <div class="card-title" id="pref-title">Preferences</div>
        <p style="color:var(--muted);font-size:.85rem;line-height:1.6;" id="pref-txt">Use the language selector in the sidebar to switch between French and English.</p>
      </div>
    </div>

    <!-- Password change card (full width below) -->
    <div class="card" style="margin-top:1.25rem;">
      <div class="card-title" id="pwd-card-title">Change password</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:.9rem;">
        <div class="form-group" style="margin-bottom:0;">
          <label style="display:block;font-family:var(--font);font-size:.73rem;font-weight:600;color:var(--muted);letter-spacing:.07em;text-transform:uppercase;margin-bottom:.45rem;" id="lbl-pwd-current">Current password</label>
          <input type="password" id="pwd-current" autocomplete="current-password"
            style="width:100%;padding:.8rem 1rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;transition:border-color .2s;"
            onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--border)'">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label style="display:block;font-family:var(--font);font-size:.73rem;font-weight:600;color:var(--muted);letter-spacing:.07em;text-transform:uppercase;margin-bottom:.45rem;" id="lbl-pwd-new">New password</label>
          <input type="password" id="pwd-new" autocomplete="new-password"
            style="width:100%;padding:.8rem 1rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;transition:border-color .2s;"
            onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--border)'">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label style="display:block;font-family:var(--font);font-size:.73rem;font-weight:600;color:var(--muted);letter-spacing:.07em;text-transform:uppercase;margin-bottom:.45rem;" id="lbl-pwd-confirm">Confirm password</label>
          <input type="password" id="pwd-confirm" autocomplete="new-password"
            style="width:100%;padding:.8rem 1rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;transition:border-color .2s;"
            onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--border)'">
        </div>
      </div>
      <div id="pwd-error" style="display:none;color:var(--red);font-size:.82rem;margin-bottom:.6rem;"></div>
      <button class="btn-primary" onclick="changePassword()" id="pwd-btn">
        <span id="pwd-btn-text">Change password</span>
      </button>
    </div>
  </div>
  <!-- ANNOUNCEMENTS PAGE -->
  <div class="page" id="page-announcements">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="ann-page-title">Announcements</h2>
      <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="ann-page-sub">Messages from the school</p>
    </div>
    <div id="ann-list"></div>
  </div>
</main>

<!-- TOAST -->
<div class="toast" id="toast"><div class="toast-dot"></div><span id="toast-msg"></span></div>

<!-- RETRACT CONFIRM MODAL -->
<div class="modal-overlay" id="modal-retract" role="dialog" aria-modal="true" aria-labelledby="retract-modal-title">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <h3 id="retract-modal-title">Retract submission</h3>
      <button class="btn-close" onclick="closeRetractModal()" aria-label="Close">✕</button>
    </div>
    <div class="modal-body">
      <p id="retract-modal-body" style="color:var(--muted);font-size:.9rem;margin:0;">Retract this submission?</p>
    </div>
    <div class="modal-footer" style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem;">
      <button class="btn-secondary" onclick="closeRetractModal()" id="retract-cancel-btn">Cancel</button>
      <button onclick="confirmRetract()" id="retract-confirm-btn"
        style="padding:.55rem 1.1rem;border-radius:10px;border:none;background:#ef4444;color:#fff;font-family:var(--font);font-size:.85rem;font-weight:600;cursor:pointer;">
        <span id="retract-btn-text">Retract</span>
      </button>
    </div>
  </div>
</div>

<!-- SUBMIT MODAL -->
<div class="modal-overlay" id="modal-submit" role="dialog" aria-modal="true" aria-labelledby="submit-modal-title">
  <div class="modal">
    <div class="modal-header">
      <h3 id="submit-modal-title">Submit assignment</h3>
      <button class="btn-close" onclick="closeSubmitModal()" aria-label="Close">✕</button>
    </div>
    <div class="modal-body">
      <div id="submit-assign-info" style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;padding:.9rem 1rem;margin-bottom:1.25rem;">
        <div style="font-family:var(--font);font-weight:600;font-size:.9rem;" id="submit-assign-title">—</div>
        <div style="color:var(--muted);font-size:.78rem;margin-top:.3rem;" id="submit-assign-due">—</div>
      </div>
      <div class="form-group">
        <label for="submit-comment" style="display:block;font-family:var(--font);font-size:.73rem;font-weight:600;color:var(--muted);letter-spacing:.07em;text-transform:uppercase;margin-bottom:.45rem;" id="submit-comment-lbl">Comment (optional)</label>
        <textarea id="submit-comment" maxlength="2000" rows="5"
          placeholder="Describe your work, add notes for the teacher…"
          style="width:100%;padding:.85rem 1rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.88rem;outline:none;resize:vertical;transition:border-color .2s;"
          onfocus="this.style.borderColor='var(--green)'" onblur="this.style.borderColor='var(--border)'"></textarea>
        <div style="text-align:right;font-size:.73rem;color:var(--muted);margin-top:.3rem;"><span id="submit-char-count">0</span>/2000</div>
      </div>
      <div class="form-group" style="margin-top:1rem;">
        <label for="submit-file" style="display:block;font-family:var(--font);font-size:.73rem;font-weight:600;color:var(--muted);letter-spacing:.07em;text-transform:uppercase;margin-bottom:.45rem;" id="submit-file-lbl">File (optional)</label>
        <input type="file" id="submit-file"
          accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.gif,.zip,.mp3,.mp4"
          style="width:100%;padding:.6rem .8rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;color:var(--muted);font-family:var(--font-body);font-size:.84rem;outline:none;cursor:pointer;">
        <div style="font-size:.72rem;color:var(--muted2);margin-top:.3rem;" id="submit-file-hint">Max 10 MB — PDF, Word, Excel, images, ZIP</div>
      </div>
      <div id="submit-error" style="display:none;color:#f87171;font-size:.85rem;margin-bottom:.5rem;"></div>
      <input type="hidden" id="submit-assign-id">
    </div>
    <div class="modal-footer" style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem;">
      <button class="btn-secondary" onclick="closeSubmitModal()" id="submit-cancel-btn">Cancel</button>
      <button class="btn-primary" onclick="confirmSubmit()" id="submit-confirm-btn">
        <span id="submit-btn-text">Submit →</span>
      </button>
    </div>
  </div>
</div>

<script>
/* ── LIVE DATA FROM PHP ── */
const LIVE      = <?= $jsData ?>;
const STARS     = <?= $jsStars ?>;
const CLASS_ACT = <?= $jsClassAct ?>;
const HAS_EMAIL     = <?= json_encode(!empty($studentEmail)) ?>;
const SERVER_AVATAR = <?= json_encode($avatarUrl) ?>;

/* ── HTML escape helper ── */
function e(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }

/* ── Client-side due-date formatter (language-aware, replaces server-rendered French strings) ── */
function fmtDue(dateStr, lang) {
  if (!dateStr) return '';
  const due  = new Date(dateStr + 'T00:00:00');
  const now  = new Date(); now.setHours(0, 0, 0, 0);
  const diff = Math.round((due - now) / 86400000);
  const past = due < now;
  const locale = lang === 'fr' ? 'fr-FR' : 'en-GB';
  const short  = due.toLocaleDateString(locale, { day: '2-digit', month: 'short' });
  if (!past && diff === 0) return lang === 'fr' ? 'Aujourd\'hui' : 'Today';
  if (!past && diff === 1) return lang === 'fr' ? 'Demain' : 'Tomorrow';
  if (!past) return short;
  return short + ' ' + (lang === 'fr' ? '(en retard)' : '(overdue)');
}

/* ── Null-safe text setter ── */
function st(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }

/* ── DATA ── */
// Assignments come from LIVE.assignments (PHP → DB)
// Quizzes loaded dynamically from api_quiz.php
let _studentQuizzes = [];

/* ── PAGE PERSISTENCE — synchronous, runs before first paint ── */
(function() {
  const _valid = ['home','myclass','assignments','feed','quizzes','progress','howto','settings','announcements'];
  const _saved = sessionStorage.getItem('upskill_page_s');
  if (_saved && _valid.includes(_saved) && _saved !== 'home') {
    const _home   = document.getElementById('page-home');
    const _target = document.getElementById('page-' + _saved);
    if (_home)   _home.classList.remove('active');
    if (_target) _target.classList.add('active');
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    const _nav = document.getElementById('nav-' + _saved);
    if (_nav) _nav.classList.add('active');
  }
})();

let currentLang = 'en';
let currentAssignFilter = 'all';
let currentQuizFilter = 'all';
let activePage = sessionStorage.getItem('upskill_page_s') || 'home';
let _cachedFeedPosts = null;

/* ── TRANSLATIONS ── */
const T = {
  fr: {
    topbarTitle: { home:'Tableau de bord', myclass:'Ma classe', assignments:'Devoirs', feed:'Notes de cours', quizzes:'Challenge', progress:'Progression', howto:'How-to', settings:'Paramètres' },
    navHome:'Dashboard', navMyclass:'Ma classe', navAssign:'Devoirs', navFeed:'Notes de cours', navProgress:'Progression', navQuiz:'Challenge', navHowto:'How-to', navSet:'Paramètres',
    feedTitle:'Notes de cours', feedSub:'Les résumés publiés par votre professeur après chaque session',
    feedEmptyTitle:'Aucune note publiée pour l\'instant', feedEmptyTxt:'Votre professeur publiera ses résumés et liens ici après chaque cours Zoom.',
    feedOpenLink:'Ouvrir le lien',
    roleLabel:'Étudiant(e)', logout:'Déconnexion',
    welcomeSub:'Votre tableau de bord d\'apprentissage',
    starsTitle:'Étudiants du mois', starsMonth:'Ce mois-ci', starsEmpty:'Pas encore de classement ce mois-ci.',
    lbTitle:'Activité de la classe', lbSub:'Activité récente', lbEmpty:'Aucune activité récente dans la classe.',
    lbSubmitted:'a rendu un devoir',
    myclassTitle:'Ma classe', myclassSub:'Votre cours et vos camarades',
    myclassCourseLbl:'Cours actuel', myclassMatesLbl:'Camarades',
    myclassTeacher:'Professeur :', myclassSchedule:'Horaire :',
    myclassAttLbl:'Taux de présence', myclassEmptyTxt:'Aucun cours assigné',
    myclassNoMates:'Aucun camarade trouvé.', myclassJoinZoom:'Rejoindre le cours',
    myclassYourGroup:'Votre groupe',

    assignPageTitle:'Devoirs', assignPageSub:'3 en attente · 1 en retard · 2 soumis',
    tabAll:'Tous', tabPending:'En attente', tabDone:'Soumis',
    tabAllQ:'Tous', tabAvailQ:'Disponibles', tabDoneQ:'Complétés',
    noQuizAvail:'Aucun quiz disponible pour l\'instant.', noQuizDone:'Aucun quiz complété pour l\'instant.',
    quizPageTitle:'Quiz', quizPageSub:'Testez vos connaissances avec des quiz chronométrés',
    progPageTitle:'Progression', progPageSub:'Suivez votre parcours module par module',
    overallTitle:'Progression globale', doneLbl:'fait',
    scoresTitle:'Performance des devoirs', progSubmitted:'devoirs soumis', progGraded:'corrigé(s)', progAvgScore:'Score moyen', progNoData:'Aucun devoir pour l\'instant.',
    modulesTitle:'Progression par matière', noSubjects:'Aucun devoir avec matière pour l\'instant.',
    howtoTitle:'Comment utiliser la plateforme ?', howtoSub:'Regardez ces courtes vidéos pour découvrir chaque fonctionnalité.',
    howtoCards:[
      { icon:'🏠', from:'#3b82f6', to:'#7c3aed', title:'Tableau de bord', desc:'Découvrez le leaderboard, les étudiants du mois et la vue d\'accueil.', btn:'Voir la vidéo' },
      { icon:'📋', from:'#10b981', to:'#059669', title:'Vos devoirs', desc:'Comment consulter, soumettre et suivre l\'état de vos devoirs.', btn:'Voir la vidéo' },
      { icon:'👥', from:'#f59e0b', to:'#f97316', title:'Ma classe', desc:'Voir votre cours, votre taux de présence et vos camarades.', btn:'Voir la vidéo' },
      { icon:'🏆', from:'#8b5cf6', to:'#6d28d9', title:'Challenge', desc:'Testez vos connaissances avec les quiz chronométrés.', btn:'Voir la vidéo' },

      { icon:'⚙️', from:'#64748b', to:'#475569', title:'Paramètres', desc:'Changez votre nom, ajoutez une photo de profil et choisissez la langue.', btn:'Voir la vidéo' },
    ],
    settingsTitle:'Paramètres', profileTitle:'Profil', lblChangePhoto:'Changer la photo', settingsRole:'Étudiant(e) · Anglais Général S2', lblFullname:'Nom complet', saveBtn:'Enregistrer', prefTitle:'Préférences', prefTxt:'Utilisez le sélecteur de langue dans la barre latérale pour basculer entre le Français et l\'Anglais.',
    pwdCardTitle:'Changer le mot de passe', lblPwdCurrent:'Mot de passe actuel', lblPwdNew:'Nouveau mot de passe', lblPwdConfirm:'Confirmer le mot de passe', pwdBtn:'Changer le mot de passe', toastPwdChanged:'Mot de passe mis à jour !',
    badgePending:'En attente', badgeSubmitted:'Soumis', badgeOverdue:'En retard',
    startQuiz:'Commencer le quiz', retakeQuiz:'Refaire', doneLabel:'Complété',
    toastSaved:'Profil mis à jour !',
    qsLabel:'questions', minLabel:'min',
    dueLbl:'Échéance :', subjectLbl:'Matière :',
    teacherLbl:'Professeur', classmatesLbl:'Camarades',
    noAssignments:'Aucun devoir pour le moment', noResults:'Aucun résultat',
    submitBtn:'Soumettre →', retractBtn:'Rétracter', retractTitle:'Sécuriser la soumission',
    retractConfirm:'Rétracter cette soumission ?', pendingStatus:'Devoir en attente',
    dueLblPre:'Échéance : ',
    modalTitle:'Soumettre le devoir', cancelBtn:'Annuler',
    loading:'Chargement…', errorPrefix:'Erreur : ', serverError:'Erreur serveur',
    networkError:'❌ Erreur réseau', toastErrorPrefix:'Erreur : ',
    feedLoading:'Chargement…',
    gradeLbl:'Note :', teacherFeedback:'Commentaire du professeur :',
    activityTitle:'Activité récente',
    tabOverdue:'En retard',
    navAnn:'Annonces',
    annPageTitle:'Annonces', annPageSub:'Messages de l\'école',
    annEmpty:'Aucune annonce pour l\'instant.', annLoading:'Chargement…',
    annBy:'Par', annTarget:{ all:'Tout le monde', students:'Étudiants', teachers:'Professeurs' },
  },
  en: {
    topbarTitle: { home:'Dashboard', myclass:'My Class', assignments:'Assignments', feed:'Lesson Notes', quizzes:'Challenge', progress:'Progress', howto:'How-to', settings:'Settings' },
    navHome:'Dashboard', navMyclass:'My Class', navAssign:'Assignments', navFeed:'Lesson Notes', navProgress:'Progress', navQuiz:'Challenge', navHowto:'How-to', navSet:'Settings',
    feedTitle:'Lesson Notes', feedSub:'Summaries posted by your teacher after each Zoom session',
    feedEmptyTitle:'No notes published yet', feedEmptyTxt:'Your teacher will post summaries and links here after each Zoom session.',
    feedOpenLink:'Open link',
    roleLabel:'Student', logout:'Log out',
    welcomeSub:'Your learning dashboard',
    starsTitle:'Students of the month', starsMonth:'This month', starsEmpty:'No ranking this month yet.',
    lbTitle:'Class activity', lbSub:'Recent activity', lbEmpty:'No recent activity in the class.',
    lbSubmitted:'submitted an assignment',
    myclassTitle:'My Class', myclassSub:'Your course and classmates',
    myclassCourseLbl:'Current course', myclassMatesLbl:'Classmates',
    myclassTeacher:'Teacher:', myclassSchedule:'Schedule:',
    myclassAttLbl:'Attendance rate', myclassEmptyTxt:'No course assigned yet',
    myclassNoMates:'No classmates found.', myclassJoinZoom:'Join class →',
    myclassYourGroup:'Your group',

    assignPageTitle:'Assignments', assignPageSub:'3 pending · 1 overdue · 2 submitted',
    tabAll:'All', tabPending:'Pending', tabDone:'Submitted',
    tabAllQ:'All', tabAvailQ:'Available', tabDoneQ:'Completed',
    noQuizAvail:'No quizzes available yet.', noQuizDone:'No completed quizzes yet.',
    quizPageTitle:'Quizzes', quizPageSub:'Test your knowledge with timed quizzes',
    progPageTitle:'Progress', progPageSub:'Track your learning journey module by module',
    overallTitle:'Overall progress', doneLbl:'done',
    scoresTitle:'Assignment performance', progSubmitted:'assignments submitted', progGraded:'graded', progAvgScore:'Average score', progNoData:'No assignments yet.',
    modulesTitle:'Progress by subject', noSubjects:'No assignments with subjects yet.',
    howtoTitle:'How to use the platform?', howtoSub:'Watch these short videos to discover each feature.',
    howtoCards:[
      { icon:'🏠', from:'#3b82f6', to:'#7c3aed', title:'Dashboard', desc:'Discover the leaderboard, students of the month and the home view.', btn:'Watch video' },
      { icon:'📋', from:'#10b981', to:'#059669', title:'Assignments', desc:'How to view, submit and track the status of your assignments.', btn:'Watch video' },
      { icon:'👥', from:'#f59e0b', to:'#f97316', title:'My Class', desc:'View your course, attendance rate and classmates.', btn:'Watch video' },
      { icon:'🏆', from:'#8b5cf6', to:'#6d28d9', title:'Challenge', desc:'Test your knowledge with timed quizzes.', btn:'Watch video' },
      { icon:'⚙️', from:'#64748b', to:'#475569', title:'Settings', desc:'Change your name, add a profile photo and choose your language.', btn:'Watch video' },
    ],
    settingsTitle:'Settings', profileTitle:'Profile', lblChangePhoto:'Change photo', settingsRole:'Student · General English S2', lblFullname:'Full name', saveBtn:'Save', prefTitle:'Preferences', prefTxt:'Use the language selector in the sidebar to switch between French and English.',
    pwdCardTitle:'Change password', lblPwdCurrent:'Current password', lblPwdNew:'New password', lblPwdConfirm:'Confirm password', pwdBtn:'Change password', toastPwdChanged:'Password updated!',
    badgePending:'Pending', badgeSubmitted:'Submitted', badgeOverdue:'Overdue',
    startQuiz:'Start quiz', retakeQuiz:'Retake', doneLabel:'Completed',
    toastSaved:'Profile updated!',
    qsLabel:'questions', minLabel:'min',
    dueLbl:'Due:', subjectLbl:'Subject:',
    teacherLbl:'Teacher', classmatesLbl:'Classmates',
    noAssignments:'No assignments yet', noResults:'No results',
    submitBtn:'Submit →', retractBtn:'Retract', retractTitle:'Retract submission',
    retractConfirm:'Retract this submission?', pendingStatus:'Pending assignment',
    dueLblPre:'Due: ',
    modalTitle:'Submit assignment', cancelBtn:'Cancel',
    loading:'Loading…', errorPrefix:'Error: ', serverError:'Server error',
    networkError:'❌ Network error', toastErrorPrefix:'Error: ',
    feedLoading:'Loading…',
    gradeLbl:'Grade:', teacherFeedback:'Teacher feedback:',
    activityTitle:'Recent activity',
    tabOverdue:'Overdue',
    navAnn:'Announcements',
    annPageTitle:'Announcements', annPageSub:'Messages from the school',
    annEmpty:'No announcements yet.', annLoading:'Loading…',
    annBy:'By', annTarget:{ all:'Everyone', students:'Students', teachers:'Teachers' },
  }
};

function t(key) { return T[currentLang][key] || T.fr[key] || key; }

function setLang(lang) {
  currentLang = lang;
  sessionStorage.setItem('upskill_lang', lang);
  document.documentElement.setAttribute('lang', lang);
  document.getElementById('pill-fr').className = 'lang-pill' + (lang === 'fr' ? ' active' : '');
  document.getElementById('pill-en').className = 'lang-pill' + (lang === 'en' ? ' active' : '');
  applyTranslations();
  updateEmailPopupLang();
  // Re-render dynamic content that doesn't use data-t attributes
  if (activePage === 'myclass') {
    renderMyClass();
    renderGroupSection();
    renderGroupMates();
  }
  if (activePage === 'announcements') loadAnnouncements();
  // Refresh notification panel if open
  if (notifData.length) renderNotifList();
}

function toggleSidebar() {
  var s = document.getElementById('sidebar');
  var b = document.getElementById('sidebar-backdrop');
  s.classList.toggle('open');
  b.classList.toggle('open');
}

function applyTranslations() {
  const lang = currentLang;
  const tr = T[lang];
  st('topbar-title', tr.topbarTitle[activePage] || tr.topbarTitle.home);
  // Nav labels
  st('nav-home-lbl', tr.navHome);
  st('nav-myclass-lbl', tr.navMyclass);
  st('nav-assign-lbl', tr.navAssign);
  st('nav-feed-lbl', tr.navFeed);
  st('nav-progress-lbl', tr.navProgress);
  st('nav-quiz-lbl', tr.navQuiz);
  st('nav-howto-lbl', tr.navHowto);
  st('nav-set-lbl', tr.navSet);
  st('role-label', tr.roleLabel);
  st('logout-lbl', tr.logout);
  // Feed page
  st('feed-title', tr.feedTitle); st('feed-sub', tr.feedSub);
  // Home page
  st('activity-feed-title', tr.activityTitle);
  st('welcome-sub', tr.welcomeSub);
  st('stars-title', tr.starsTitle);
  st('stars-month', tr.starsMonth);
  st('lb-title', tr.lbTitle);
  st('lb-sub', tr.lbSub);
  // My class page
  st('myclass-title', tr.myclassTitle);
  st('myclass-sub', tr.myclassSub);
  st('myclass-course-label', tr.myclassCourseLbl);
  st('myclass-mates-lbl', tr.myclassMatesLbl);
  st('myclass-att-lbl', tr.myclassAttLbl);
  st('myclass-empty-txt', tr.myclassEmptyTxt);
  st('myclass-zoom-lbl', tr.myclassJoinZoom);








  document.getElementById('assign-page-title').textContent = tr.assignPageTitle;
  updateAssignSubtitle();
  document.getElementById('tab-all-assign').textContent    = tr.tabAll;
  document.getElementById('tab-pending-assign').textContent = tr.tabPending;
  document.getElementById('tab-overdue-assign').textContent = tr.tabOverdue;
  document.getElementById('tab-done-assign').textContent   = tr.tabDone;
  st('nav-ann-lbl',    tr.navAnn);
  st('ann-page-title', tr.annPageTitle);
  st('ann-page-sub',   tr.annPageSub);
  document.getElementById('quiz-page-title').textContent = tr.quizPageTitle;
  document.getElementById('quiz-page-sub').textContent = tr.quizPageSub;
  document.getElementById('tab-all-quiz').textContent = tr.tabAllQ;
  document.getElementById('tab-avail-quiz').textContent = tr.tabAvailQ;
  document.getElementById('tab-done-quiz').textContent = tr.tabDoneQ;
  document.getElementById('prog-page-title').textContent = tr.progPageTitle;
  document.getElementById('prog-page-sub').textContent = tr.progPageSub;
  document.getElementById('overall-title').textContent = tr.overallTitle;
  document.getElementById('done-lbl').textContent = tr.doneLbl;
  updateProgressHeader();
  document.getElementById('scores-title').textContent = tr.scoresTitle;
  document.getElementById('modules-title').textContent = tr.modulesTitle;
  // How-to page
  st('howto-title', tr.howtoTitle);
  st('howto-sub', tr.howtoSub);
  renderHowTo();
  // Settings page
  st('settings-title', tr.settingsTitle);
  st('profile-title', tr.profileTitle);
  st('settings-role', tr.settingsRole);
  st('lbl-fullname', tr.lblFullname);
  st('save-btn-text', tr.saveBtn);
  st('pref-title', tr.prefTitle);
  st('pref-txt', tr.prefTxt);
  st('lbl-change-photo', tr.lblChangePhoto);
  st('pwd-card-title', tr.pwdCardTitle);
  st('lbl-pwd-current', tr.lblPwdCurrent);
  st('lbl-pwd-new', tr.lblPwdNew);
  st('lbl-pwd-confirm', tr.lblPwdConfirm);
  st('pwd-btn-text', tr.pwdBtn);
  renderAssignments();
  renderQuizzes();
  renderStars();
  renderLeaderboard();
  renderActivityFeed();
  const _lh = document.getElementById('lang-hide'); if (_lh) _lh.remove();
}

function navigate(page, el) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const pg = document.getElementById('page-' + page);
  if (pg) pg.classList.add('active');
  if (el) el.classList.add('active');
  else {
    const navIdMap = {assignments:'nav-assign',quizzes:'nav-quiz',settings:'nav-set',myclass:'nav-myclass',feed:'nav-feed',home:'nav-home',howto:'nav-howto',progress:'nav-progress',announcements:'nav-announcements'};
    const navEl = document.getElementById(navIdMap[page] || 'nav-' + page);
    if (navEl) navEl.classList.add('active');
  }
  activePage = page;
  sessionStorage.setItem('upskill_page_s', page);
  st('topbar-title', T[currentLang].topbarTitle[page] || T[currentLang].topbarTitle.home);
  if (page === 'assignments') refreshAssignments();
  if (page === 'quizzes')     renderQuizzes();
  if (page === 'myclass')     { renderMyClass(); loadMyGroup(); }
  if (page === 'feed')          { if (_cachedFeedPosts !== null) renderFeed(_cachedFeedPosts); else loadFeed(); }
  if (page === 'progress')      updateProgress();
  if (page === 'announcements') loadAnnouncements();
  if (window.innerWidth <= 768) {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-backdrop').classList.remove('open');
  }
}

function filterTab(el, filter, type) {
  el.closest('.tabs').querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  if (type === 'assign') { currentAssignFilter = filter; renderAssignments(); }
  if (type === 'quiz') { currentQuizFilter = filter; renderQuizzes(); }
}

/* ── STARS OF THE MONTH ── */
function renderStars() {
  const el = document.getElementById('stars-list');
  if (!el) return;
  const tr = T[currentLang];
  const ranks = ['🥇','🥈','🥉','4️⃣','5️⃣'];
  if (!STARS || STARS.length === 0) {
    el.innerHTML = `<div class="stars-empty">${e(tr.starsEmpty)}</div>`;
    return;
  }
  el.innerHTML = STARS.map((s, i) => {
    const parts = (s.full_name || '').trim().split(/\s+/);
    const init  = ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '?';
    const total = parseInt(s.total_sessions) || 0;
    const pres  = parseInt(s.present_sessions) || 0;
    const pct   = total > 0 ? Math.round(pres / total * 100) + '%' : '–';
    return `<div class="star-item">
      <div class="star-rank">${ranks[i] || (i+1)}</div>
      <div class="star-av">${e(init)}</div>
      <div class="star-name">${e(s.full_name)}</div>
      <div class="star-pct">${pct}</div>
    </div>`;
  }).join('');
}

/* ── CLASS LEADERBOARD ── */
function renderLeaderboard() {
  const el = document.getElementById('leaderboard-list');
  if (!el) return;
  const tr  = T[currentLang];
  const lang = currentLang;
  if (!CLASS_ACT || CLASS_ACT.length === 0) {
    el.innerHTML = `<div class="lb-empty">${e(tr.lbEmpty)}</div>`;
    return;
  }
  el.innerHTML = CLASS_ACT.map(item => {
    const parts = (item.full_name || '').trim().split(/\s+/);
    const init  = ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '?';
    const title = item.title_fr || item.title_ar;
    const time  = item.submitted_at ? formatRelativeTime(item.submitted_at, lang) : '';
    return `<div class="lb-item">
      <div class="lb-av">${e(init)}</div>
      <div class="lb-info">
        <div class="lb-name">${e(item.full_name)}</div>
        <div class="lb-action">${e(tr.lbSubmitted)} — ${e(title)}</div>
      </div>
      ${time ? `<div style="font-size:.7rem;color:rgba(255,255,255,.65);white-space:nowrap;">${e(time)}</div>` : ''}
    </div>`;
  }).join('');
}

/* ── MY CLASS ── */
async function loadMyGroup() {
  // Use cached data on repeat navigations (reset on full page reload)
  if (LIVE.myGroups !== undefined) {
    renderGroupSection();
    renderGroupMates();
    return;
  }

  const section = document.getElementById('myclass-group-section');
  if (!section) return;
  section.innerHTML = '<div class="loading-overlay"><div class="spinner"></div></div>';

  let groupData;
  try {
    groupData = await fetch('api_classes.php?action=my_group').then(r => r.json());
  } catch(e) { section.innerHTML = ''; return; }

  LIVE.myGroups = (groupData.ok && groupData.groups) ? groupData.groups : [];

  if (LIVE.myGroups.length === 0) { section.innerHTML = ''; return; }

  renderGroupSection();

  try {
    const mdata = await fetch(`api_classes.php?action=group_classmates&group_id=${LIVE.myGroups[0].group_id}`).then(r => r.json());
    LIVE.myGroupMembers = (mdata.ok && mdata.members) ? mdata.members : [];
  } catch(e) { LIVE.myGroupMembers = []; }

  renderGroupMates();
}

/* Pure render from cached LIVE.myGroups — re-called on lang change */
function renderGroupSection() {
  const section = document.getElementById('myclass-group-section');
  if (!section || !LIVE.myGroups || !LIVE.myGroups.length) return;
  const lang = currentLang;
  const fmtDate = (d) => {
    if (!d) return '';
    const [y, m, day] = d.split('-');
    return lang === 'en' ? `${m}/${day}/${y}` : `${day}/${m}/${y}`;
  };
  const startLbl = lang === 'en' ? 'Start' : 'Début';
  const endLbl   = lang === 'en' ? 'End'   : 'Fin';
  const badges = LIVE.myGroups.map(g => {
    const label     = lang === 'en' ? (g.label_en || g.label_fr) : g.label_fr;
    const startDate = fmtDate(g.start_date);
    const endDate   = fmtDate(g.end_date);
    const dateHtml  = (startDate || endDate) ? `
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.55rem;">
        ${startDate ? `<span style="font-size:.75rem;color:var(--white);"><span style="font-weight:600;">${startLbl}:</span> ${startDate}</span>` : ''}
        ${endDate   ? `<span style="font-size:.75rem;color:var(--white);"><span style="font-weight:600;">${endLbl}:</span> ${endDate}</span>` : ''}
      </div>` : '';
    return `<div>
      <span style="display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .9rem;background:rgba(62,207,120,.1);border:1px solid rgba(62,207,120,.3);border-radius:100px;font-family:var(--font);font-size:.85rem;font-weight:700;color:var(--green);">
        🏫 ${label}
      </span>
      ${dateHtml}
    </div>`;
  }).join('');
  section.innerHTML = `<div class="card" style="padding:1rem 1.25rem;background:linear-gradient(135deg,rgba(62,207,120,.05),rgba(91,156,246,.03));border-color:rgba(62,207,120,.2);">
    <div style="font-family:var(--font);font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);margin-bottom:.5rem;">${T[lang].myclassYourGroup}</div>
    <div style="display:flex;flex-direction:column;gap:.6rem;">${badges}</div>
  </div>`;
}

/* Pure render from cached LIVE.myGroupMembers — re-called on lang change */
function renderGroupMates() {
  const matesEl = document.getElementById('myclass-mates');
  if (!matesEl) return;
  const members = LIVE.myGroupMembers || [];
  const lang = currentLang;
  const students = members.filter(m => m.role === 'student');

  if (students.length === 0) {
    matesEl.innerHTML = `<div style="text-align:center;padding:2rem;color:var(--muted);font-size:.85rem;">${lang==='en'?'No classmates in this group yet.':'Aucun camarade dans ce groupe pour l\'instant.'}</div>`;
    return;
  }
  matesEl.innerHTML = students.map(s => {
    const parts = (s.name||s.username||'').trim().split(/\s+/);
    const init  = ((parts[0]?.[0]??'') + (parts[1]?.[0]??'')).toUpperCase() || '?';
    return `<div style="display:flex;align-items:center;gap:.7rem;padding:.65rem 0;border-bottom:1px solid var(--border2);">
      <div style="width:34px;height:34px;border-radius:50%;background:rgba(91,156,246,.15);border:1px solid rgba(91,156,246,.3);display:flex;align-items:center;justify-content:center;font-family:var(--font);font-weight:700;font-size:.75rem;color:var(--blue);flex-shrink:0;">${init}</div>
      <div style="font-size:.88rem;">${s.name||s.username}</div>
    </div>`;
  }).join('').replace(/border-bottom[^;]+;(?=[^<]*<\/div>\s*$)/,'');

  const teachers = members.filter(m => m.role === 'teacher');
  if (teachers.length > 0) {
    const teacherHtml = teachers.map(t => {
      const parts = (t.name||t.username||'').trim().split(/\s+/);
      const init  = ((parts[0]?.[0]??'') + (parts[1]?.[0]??'')).toUpperCase() || '?';
      return `<div style="display:flex;align-items:center;gap:.7rem;padding:.65rem 0;border-bottom:1px solid var(--border2);">
        <div style="width:34px;height:34px;border-radius:50%;background:rgba(62,207,120,.12);border:1px solid rgba(62,207,120,.3);display:flex;align-items:center;justify-content:center;font-family:var(--font);font-weight:700;font-size:.75rem;color:var(--green);flex-shrink:0;">${init}</div>
        <div>
          <div style="font-size:.88rem;">${t.name||t.username}</div>
          <div style="font-size:.72rem;color:var(--green);">${T[lang].teacherLbl}</div>
        </div>
      </div>`;
    }).join('');
    matesEl.innerHTML = `<div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:.5rem;">${T[lang].teacherLbl}</div>${teacherHtml}<div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin:.75rem 0 .5rem;">${T[lang].classmatesLbl}</div>` + matesEl.innerHTML;
  }
}

function renderMyClass() {
  const tr   = T[currentLang];
  const lang = currentLang;
  const c    = LIVE.course;
  const assignedEl = document.getElementById('myclass-assigned');
  const emptyEl    = document.getElementById('myclass-empty');

  if (c) {
    if (assignedEl) assignedEl.style.display = '';
    if (emptyEl)    emptyEl.style.display    = 'none';
    const name = lang === 'en' ? (c.label_en || c.label_fr) : c.label_fr;
    st('myclass-course-name', name || '—');
    st('myclass-teacher', (c.teacher_name ? ((lang === 'en' ? '' : 'Prof. ') + c.teacher_name) : '—'));
    if (c.schedule_json) {
      try {
        const sched = JSON.parse(c.schedule_json);
        if (Array.isArray(sched) && sched.length) {
          const DAY_EN = {Lundi:'Monday',Mardi:'Tuesday',Mercredi:'Wednesday',Jeudi:'Thursday',Vendredi:'Friday',Samedi:'Saturday',Dimanche:'Sunday'};
          st('myclass-schedule', sched.map(s => {
            const day = lang === 'en' ? (DAY_EN[s.day_fr] || s.day_fr) : s.day_fr;
            const timeStr = s.time ? (s.time_end ? s.time + ' – ' + s.time_end : s.time) : '';
            return timeStr ? day + ' ' + timeStr : day;
          }).join(' · '));
        }
      } catch(e) {}
    }
    const rate = LIVE.att_rate;
    if (rate !== null) {
      st('myclass-att-pct', rate + '%');
      const bar = document.getElementById('myclass-att-bar');
      if (bar) bar.style.width = rate + '%';
      const det = document.getElementById('myclass-att-detail');
      if (det) det.textContent = lang === 'en'
        ? `${LIVE.att_present} / ${LIVE.att_total} sessions`
        : `${LIVE.att_present} / ${LIVE.att_total} séances`;
    }
    // H9: Zoom link
    const zoomWrap = document.getElementById('myclass-zoom-wrap');
    const zoomLink = document.getElementById('myclass-zoom-link');
    if (zoomWrap && c.zoom_url) {
      zoomLink.href = c.zoom_url;
      zoomWrap.style.display = '';
    } else if (zoomWrap) {
      zoomWrap.style.display = 'none';
    }
  } else {
    if (assignedEl) assignedEl.style.display = 'none';
    if (emptyEl)    emptyEl.style.display    = '';
  }

  // Classmates are loaded exclusively by loadMyGroup() (async).
  // Set a loading placeholder here so loadMyGroup() always wins with real data.
  const matesEl = document.getElementById('myclass-mates');
  if (matesEl) {
    matesEl.innerHTML = `<div style="text-align:center;padding:1.5rem;color:var(--muted);font-size:.85rem;">${e(tr.loading)}</div>`;
  }
}

async function refreshAssignments() {
  try {
    const res  = await fetch('api_submit.php?action=list');
    const data = await res.json();
    if (data.ok && Array.isArray(data.assignments)) {
      LIVE.assignments     = data.assignments;
      LIVE.pending_count   = data.assignments.filter(a => a.status === 'pending').length;
      LIVE.overdue_count   = data.assignments.filter(a => a.status === 'overdue').length;
      LIVE.submitted_count = data.assignments.filter(a => a.status === 'submitted').length;
    }
  } catch(e) {}
  renderAssignments();
}

function renderAssignments() {
  const list = document.getElementById('assign-list');
  if (!list) return;
  const tr = T[currentLang];
  const rows = LIVE.assignments || [];

  if (rows.length === 0) {
    list.innerHTML = `<div style="text-align:center;padding:3rem 1rem;color:var(--muted);">
      <div style="font-size:2rem;margin-bottom:.75rem;">📭</div>
      <div style="font-family:var(--font);font-size:.95rem;">${tr.noAssignments}</div>
    </div>`;
    return;
  }

  const items = rows.filter(a => {
    if (currentAssignFilter === 'all') return true;
    if (currentAssignFilter === 'pending')  return a.status === 'pending';
    if (currentAssignFilter === 'overdue')  return a.status === 'overdue';
    if (currentAssignFilter === 'submitted') return a.status === 'submitted';
    return true;
  });

  const badgeMap = {
    pending:   `<span class="badge pending">${tr.badgePending}</span>`,
    submitted: `<span class="badge submitted">${tr.badgeSubmitted}</span>`,
    overdue:   `<span class="badge overdue">${tr.badgeOverdue}</span>`
  };

  list.innerHTML = items.length === 0
    ? `<div style="text-align:center;padding:2rem;color:var(--muted);font-size:.88rem;">${tr.noResults}</div>`
    : items.map(a => {
        const title   = e(a.title_fr || a.title_ar);
        const desc    = e(a.description_fr || a.description_ar || '');
        const subject = e(a.subject_fr || a.subject_ar);
        const due     = e(fmtDue(a.due_date, currentLang) || (a.due_date ? a.due_date : '—'));

        // Action button based on status
        let actionBtn = '';
        if (a.status === 'pending' || a.status === 'overdue') {
          actionBtn = `<button class="btn-primary" style="margin-top:1rem;padding:.6rem 1.2rem;font-size:.83rem;"
            onclick="openSubmitModal(${a.id},'${title.replace(/'/g,"\\'")}','${due.replace(/'/g,"\\'")}')">
            ${tr.submitBtn}
          </button>`;
        } else if (a.status === 'submitted') {
          const gradeBlock = a.score !== null
            ? `<div style="margin-top:.75rem;padding:.65rem .9rem;background:rgba(62,207,120,.08);border:1px solid rgba(62,207,120,.2);border-radius:10px;">
                <div style="display:flex;align-items:center;gap:.5rem;font-size:.83rem;font-weight:600;color:var(--green);">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                  ${tr.gradeLbl} <span style="font-size:1rem;">${a.score}/100</span>
                </div>
                ${a.teacher_comment ? `<div style="margin-top:.4rem;font-size:.8rem;color:var(--muted);">${tr.teacherFeedback} <em>${e(a.teacher_comment)}</em></div>` : ''}
              </div>`
            : '';
          actionBtn = `${gradeBlock}<div style="display:flex;align-items:center;gap:.75rem;margin-top:.75rem;flex-wrap:wrap;">
            <div style="font-size:.8rem;color:var(--green);display:flex;align-items:center;gap:.35rem;">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              ${tr.badgeSubmitted}
              ${a.submitted_at ? `<span style="color:var(--muted);font-weight:400;">· ${formatRelativeTime(a.submitted_at, currentLang)}</span>` : ''}
            </div>
            ${a.score === null ? `<button onclick="openRetractModal(${a.id})"
              style="font-size:.75rem;padding:.3rem .7rem;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;font-family:var(--font);"
              onmouseenter="this.style.color='var(--white)'" onmouseleave="this.style.color='var(--muted)'"
              title="${tr.retractTitle}">
              ${tr.retractBtn}
            </button>` : ''}
          </div>`;
        }

        return `<div class="assign-item" style="${a.status==='overdue'?'border-color:rgba(248,113,113,.25);':a.status==='submitted'?'border-color:rgba(62,207,120,.2);':''}">
          <div class="assign-header">
            <div class="assign-title">${title}</div>
            ${badgeMap[a.status] || ''}
          </div>
          ${desc ? `<div class="assign-desc">${desc}</div>` : ''}
          <div class="assign-meta">
            <span>📅 ${tr.dueLbl} ${due}</span>
            ${subject ? `<span>📚 ${tr.subjectLbl} ${subject}</span>` : ''}
          </div>
          ${actionBtn}
        </div>`;
      }).join('');
}

function renderActivityFeed() {
  const feed = document.getElementById('activity-feed');
  if (!feed) return;
  const lang = currentLang;
  const items = LIVE.activity || [];

  if (items.length === 0) {
    // Fall back: show pending/overdue assignments as activity
    const fallback = (LIVE.assignments || []).slice(0, 5);
    if (fallback.length === 0) {
      feed.innerHTML = `<div style="color:var(--muted);font-size:.85rem;padding:1rem 0;text-align:center;">${T[lang].lbEmpty}</div>`;
      return;
    }
    const tr2 = T[lang];
    feed.innerHTML = fallback.map(a => {
      const color = a.status === 'overdue' ? 'red' : a.status === 'submitted' ? 'green' : 'yellow';
      const label = a.status === 'overdue'
        ? tr2.badgeOverdue
        : a.status === 'submitted'
          ? tr2.badgeSubmitted
          : tr2.pendingStatus;
      const title = e(a.title_fr || a.title_ar);
      const due   = a.due_date ? `${tr2.dueLblPre}${e(fmtDue(a.due_date, currentLang))}` : '';
      return `<div class="activity-item">
        <div class="activity-dot ${color}"></div>
        <div><div class="activity-text"><strong>${label}</strong> — <span>${title}</span></div>
        ${due ? `<div class="activity-time">${due}</div>` : ''}</div>
      </div>`;
    }).join('');
    return;
  }

  feed.innerHTML = items.map(item => {
    const label  = e(lang === 'en' ? (item.label_en  || item.label_fr)  : item.label_fr);
    const detail = e(lang === 'en' ? (item.detail_en || item.detail_fr) : item.detail_fr);
    const time   = item.time ? formatRelativeTime(item.time, lang) : '';
    return `<div class="activity-item">
      <div class="activity-dot ${item.color}"></div>
      <div><div class="activity-text"><strong>${label}</strong> — <span>${detail}</span></div>
      ${time ? `<div class="activity-time">${time}</div>` : ''}</div>
    </div>`;
  }).join('');
}

function formatRelativeTime(ts, lang) {
  const d = new Date(ts.replace(' ', 'T'));
  if (isNaN(d)) return '';
  const diff = Math.floor((Date.now() - d) / 1000);
  if (diff < 60)   return lang === 'en' ? 'Just now'                      : "À l'instant";
  if (diff < 3600) return lang === 'en' ? `${Math.floor(diff/60)} min ago`  : `Il y a ${Math.floor(diff/60)} min`;
  if (diff < 86400)return lang === 'en' ? `${Math.floor(diff/3600)} h ago`  : `Il y a ${Math.floor(diff/3600)} h`;
  const days = Math.floor(diff/86400);
  if (days === 1)  return lang === 'en' ? 'Yesterday'                       : 'Hier';
  return lang === 'en' ? `${days} days ago` : `Il y a ${days} jours`;
}

async function renderQuizzes() {
  const list = document.getElementById('quiz-list');
  if (!list) return;
  const tr = T[currentLang];
  list.innerHTML = '<div class="loading-overlay" style="position:static;height:100px;border-radius:16px;grid-column:1/-1;"><div class="spinner"></div></div>';
  try {
    const d = await fetch('api_quiz.php?action=list_quizzes').then(r=>r.json());
    _studentQuizzes = d.quizzes || [];
  } catch(e) {
    list.innerHTML = `<p style="color:var(--red);font-size:.85rem;grid-column:1/-1;">Error loading quizzes</p>`;
    return;
  }
  const items = _studentQuizzes.filter(q => {
    if (currentQuizFilter === 'all') return true;
    if (currentQuizFilter === 'available') return !q.attempted_at;
    if (currentQuizFilter === 'done') return !!q.attempted_at;
    return true;
  });
  if (!items.length) {
    const msg = currentQuizFilter==='done' ? tr.noQuizDone : tr.noQuizAvail;
    list.innerHTML = `<p style="color:var(--muted);font-size:.85rem;grid-column:1/-1;">${msg}</p>`;
    return;
  }
  list.innerHTML = items.map(q => {
    const done = !!q.attempted_at;
    const pct  = done && q.total > 0 ? Math.round(q.score / q.total * 100) : (q.score != null ? q.score : 0);
    return `<div class="quiz-card">
      <div class="quiz-icon">${done ? '✅' : '🧠'}</div>
      <div class="quiz-title">${e(q.title)}</div>
      <div class="quiz-desc">${q.description ? e(q.description) : ''}</div>
      <div class="quiz-meta">
        <span>📝 ${q.question_count||0} ${tr.qsLabel||'q'}</span>
        ${q.time_limit_min>0?`<span>⏱ ${q.time_limit_min} ${tr.minLabel||'min'}</span>`:''}
      </div>
      ${done
        ? `<div style="display:flex;align-items:center;gap:.75rem;margin-top:auto;">
            <div style="width:48px;height:48px;border-radius:50%;border:3px solid ${pct>=60?'var(--green)':'var(--red)'};display:flex;align-items:center;justify-content:center;font-family:var(--font);font-size:.78rem;font-weight:700;color:${pct>=60?'var(--green)':'var(--red)'};">${pct}%</div>
            <div><div style="font-size:.83rem;color:var(--muted);">${tr.doneLabel||'Completed'}</div><div style="font-size:.78rem;color:var(--muted2);">${q.score}/${q.total}</div></div>
           </div>`
        : `<button class="btn-primary" style="margin-top:auto;" onclick="startQuiz(${q.id},'${e(q.title).replace(/'/g,"&#39;")}')">${tr.startQuiz||'Start'}</button>`
      }
    </div>`;
  }).join('');
}

/* ── Quiz taking ─────────────────────────────────────────────────────── */
let _activeQuizId = null;
let _quizTimer    = null;

async function startQuiz(quizId, title) {
  const lang = currentLang; const tr = T[lang];
  let d;
  try { d = await fetch(`api_quiz.php?action=get_quiz&id=${quizId}`).then(r=>r.json()); } catch(e) { showToast('Error loading quiz'); return; }
  if (d.already_done) { showToast(lang==='en'?'You already completed this quiz':lang==='ar'?'لقد أكملت هذا الاختبار بالفعل':'Vous avez déjà complété ce quiz'); return; }
  if (!d.ok) { showToast(d.error||'Error'); return; }
  _activeQuizId = quizId;
  const questions = d.questions || [];
  const timeLimit = parseInt(d.quiz?.time_limit_min) || 0;
  const overlay = document.createElement('div');
  overlay.id = 'quiz-overlay';
  overlay.style.cssText = 'position:fixed;inset:0;background:var(--navy);z-index:10000;overflow-y:auto;padding:2rem 1rem;';
  const optStyle = `display:block;width:100%;text-align:left;padding:.75rem 1rem;margin-bottom:.4rem;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;cursor:pointer;color:var(--white);font-family:var(--font-body);font-size:.88rem;transition:all .2s;`;
  overlay.innerHTML = `
    <div style="max-width:640px;margin:0 auto;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem;">
        <div>
          <h2 style="font-family:var(--font);font-size:1.25rem;font-weight:700;">🧠 ${e(title)}</h2>
          <p style="color:var(--muted);font-size:.85rem;">${questions.length} ${tr.qsLabel||'questions'}</p>
        </div>
        ${timeLimit>0?`<div id="quiz-timer" style="font-family:var(--font);font-size:1.1rem;font-weight:700;color:var(--yellow);">⏱ ${timeLimit}:00</div>`:''}
      </div>
      <form id="quiz-form">
        ${questions.map((q,i)=>`
          <div style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:14px;padding:1.25rem;margin-bottom:1rem;">
            <div style="font-family:var(--font);font-size:.82rem;font-weight:700;color:var(--muted);margin-bottom:.5rem;">${lang==='en'?'Q':'Q'}${i+1}</div>
            <div style="font-size:.95rem;font-weight:600;margin-bottom:.9rem;line-height:1.5;">${e(q.question)}</div>
            ${q.options.map(opt=>`
              <label style="${optStyle}">
                <input type="radio" name="q_${q.id}" value="${opt.id}" style="accent-color:var(--green);margin-right:.65rem;">
                ${e(opt.option_text)}
              </label>`).join('')}
          </div>`).join('')}
        <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem;flex-wrap:wrap;">
          <button type="button" onclick="closeQuizOverlay()" style="padding:.7rem 1.4rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;color:var(--muted);cursor:pointer;font-family:var(--font);">${lang==='en'?'Cancel':lang==='ar'?'إلغاء':'Annuler'}</button>
          <button type="button" onclick="submitStudentQuiz()" style="padding:.7rem 1.6rem;background:var(--green);border:none;border-radius:10px;color:var(--navy);font-family:var(--font);font-weight:700;cursor:pointer;">${lang==='en'?'Submit':lang==='ar'?'إرسال':'Soumettre'}</button>
        </div>
        <div id="quiz-submit-error" style="color:var(--red);font-size:.82rem;text-align:right;min-height:1rem;margin-top:.5rem;"></div>
      </form>
    </div>`;
  document.body.appendChild(overlay);
  // Radio option style on select
  overlay.querySelectorAll('input[type=radio]').forEach(radio => {
    radio.addEventListener('change', () => {
      const name = radio.name;
      overlay.querySelectorAll(`input[name="${name}"]`).forEach(r => {
        r.closest('label').style.background = r.checked ? 'rgba(62,207,120,.1)' : 'rgba(255,255,255,.04)';
        r.closest('label').style.borderColor = r.checked ? 'rgba(62,207,120,.4)' : 'var(--border)';
      });
    });
  });
  // Timer
  if (timeLimit > 0) {
    let remaining = timeLimit * 60;
    const timerEl = overlay.querySelector('#quiz-timer');
    _quizTimer = setInterval(() => {
      remaining--;
      if (remaining <= 0) { clearInterval(_quizTimer); submitStudentQuiz(); return; }
      const m = Math.floor(remaining/60), s = remaining%60;
      if (timerEl) { timerEl.textContent = `⏱ ${m}:${String(s).padStart(2,'0')}`; timerEl.style.color = remaining<60?'var(--red)':'var(--yellow)'; }
    }, 1000);
  }
}

function closeQuizOverlay() {
  if (_quizTimer) { clearInterval(_quizTimer); _quizTimer = null; }
  document.getElementById('quiz-overlay')?.remove();
  _activeQuizId = null;
}

async function submitStudentQuiz() {
  const lang = currentLang;
  const form = document.getElementById('quiz-form');
  const errEl = document.getElementById('quiz-submit-error');
  if (!form || !_activeQuizId) return;
  const answers = {};
  form.querySelectorAll('input[type=radio]:checked').forEach(r => {
    const qid = r.name.replace('q_','');
    answers[qid] = parseInt(r.value);
  });
  if (_quizTimer) { clearInterval(_quizTimer); _quizTimer = null; }
  try {
    const d = await fetch('api_quiz.php', {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':_csrf},
      body: JSON.stringify({action:'submit_quiz', quiz_id:_activeQuizId, answers})
    }).then(r=>r.json());
    if (!d.ok) throw new Error(d.error||'Error');
    closeQuizOverlay();
    // Show score dialog
    const dlg = document.createElement('div');
    dlg.className = 'modal-overlay'; dlg.style.zIndex='10001';
    const color = d.pct>=60?'var(--green)':'var(--red)';
    dlg.innerHTML = `<div class="modal" style="text-align:center;padding:2.5rem 2rem;max-width:360px;">
      <div style="font-size:3.5rem;margin-bottom:.75rem;">${d.pct>=60?'🎉':'💪'}</div>
      <h3 style="font-family:var(--font);font-size:1.3rem;margin-bottom:.5rem;">${lang==='en'?'Quiz Complete!':lang==='ar'?'اكتمل الاختبار!':'Quiz terminé !'}</h3>
      <div style="font-size:3rem;font-weight:800;font-family:var(--font);color:${color};margin:1rem 0;">${d.pct}%</div>
      <div style="color:var(--muted);font-size:.9rem;margin-bottom:1.5rem;">${d.score} / ${d.total} ${lang==='en'?'correct':lang==='ar'?'إجابة صحيحة':'correct(s)'}</div>
      <button class="btn-primary" onclick="this.closest('.modal-overlay').remove();renderQuizzes();" style="width:100%;">${lang==='en'?'Done':lang==='ar'?'تم':'Terminer'}</button>
    </div>`;
    dlg.addEventListener('click', e=>{ if(e.target===dlg){ dlg.remove(); renderQuizzes(); }});
    document.body.appendChild(dlg); dlg.classList.add('open');
  } catch(e) { if(errEl) errEl.textContent = e.message; }
}

async function saveProfile() {
  const name    = document.getElementById('pref-name').value.trim();
  const errEl   = document.getElementById('save-profile-error');
  const btnText = document.getElementById('save-btn-text');
  if (!name) return;
  errEl.style.display = 'none';
  btnText.textContent = '…';
  document.getElementById('save-btn').disabled = true;

  try {
    const res = await fetch('api_update_profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrf },
      body: JSON.stringify({ action: 'update_name', name })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || T[currentLang].serverError);

    // Update all name displays
    const parts = name.trim().split(/\s+/);
    const sn = document.getElementById('sidebar-name'); if (sn) sn.textContent = name;
    const dn = document.getElementById('settings-name'); if (dn) dn.textContent = name;
    st('welcome-name', parts[0]);
    showToast(T[currentLang].toastSaved);
  } catch(err) {
    errEl.textContent = err.message;
    errEl.style.display = '';
  } finally {
    btnText.textContent = T[currentLang].saveBtn;
    document.getElementById('save-btn').disabled = false;
  }
}

function updateProgress() {
  const tr = T[currentLang];
  const assignments = LIVE.assignments || [];
  const total    = assignments.length;
  const submitted = assignments.filter(a => a.status === 'submitted' || a.status === 'graded' || a.score !== null).length;
  const graded    = assignments.filter(a => a.score !== null);
  const pct       = total > 0 ? Math.round(submitted / total * 100) : 0;

  // Update circle
  const circumference = 238.76;
  const offset = circumference * (1 - pct / 100);
  const circle = document.getElementById('prog-circle');
  if (circle) circle.setAttribute('stroke-dashoffset', offset.toFixed(1));
  const pctEl = document.getElementById('prog-circle-pct');
  if (pctEl) pctEl.textContent = pct + '%';

  // Update assignment performance card
  const statsEl = document.getElementById('progress-assign-stats');
  if (!statsEl) return;
  if (total === 0) {
    statsEl.innerHTML = `<p style="color:var(--muted);font-size:.85rem;">${tr.progNoData}</p>`;
    return;
  }

  // Submission rate bar
  const subPct = pct;
  const subColor = subPct >= 80 ? 'var(--green)' : subPct >= 50 ? 'var(--yellow)' : 'var(--red)';

  // Average score
  let avgHtml = '';
  if (graded.length > 0) {
    const avg = Math.round(graded.reduce((s, a) => s + Number(a.score), 0) / graded.length);
    const avgColor = avg >= 70 ? 'var(--green)' : avg >= 50 ? 'var(--yellow)' : 'var(--red)';
    avgHtml = `<div class="module-row" style="margin-top:.75rem;">
      <div class="module-name">${tr.progAvgScore}</div>
      <div class="module-bar"><div class="progress-bar"><div class="progress-fill" style="width:${avg}%;background:${avgColor};"></div></div></div>
      <div class="module-pct" style="color:${avgColor};">${avg}/100</div>
    </div>`;
  }

  statsEl.innerHTML = `
    <div class="module-row">
      <div class="module-name">${submitted}/${total} ${tr.progSubmitted}</div>
      <div class="module-bar"><div class="progress-bar"><div class="progress-fill" style="width:${subPct}%;background:${subColor};"></div></div></div>
      <div class="module-pct" style="color:${subColor};">${subPct}%</div>
    </div>
    ${graded.length > 0 ? `<div style="font-size:.75rem;color:var(--muted);margin:.4rem 0 0;">${graded.length} ${tr.progGraded}</div>` : ''}
    ${avgHtml}`;

  // ── Subject breakdown ────────────────────────────────────────────────────
  const subjEl = document.getElementById('modules-by-subject');
  if (!subjEl) return;
  const lang = currentLang;
  // Group assignments by subject
  const bySubject = {};
  assignments.forEach(a => {
    const subj = (a.subject_fr || a.subject_ar || '').trim();
    if (!subj) return;
    if (!bySubject[subj]) bySubject[subj] = { total: 0, submitted: 0 };
    bySubject[subj].total++;
    if (a.status === 'submitted' || a.score !== null) bySubject[subj].submitted++;
  });
  const subjects = Object.entries(bySubject);
  if (subjects.length === 0) {
    subjEl.innerHTML = `<p style="color:var(--muted);font-size:.85rem;padding:.5rem 0;">${tr.noSubjects}</p>`;
    return;
  }
  subjEl.innerHTML = subjects.map(([subj, d]) => {
    const pct   = d.total > 0 ? Math.round(d.submitted / d.total * 100) : 0;
    const color = pct >= 80 ? 'var(--green)' : pct >= 50 ? 'var(--yellow)' : 'var(--red)';
    return `<div class="module-row">
      <div class="module-name">${e(subj)}</div>
      <div class="module-bar"><div class="progress-bar"><div class="progress-fill" style="width:${pct}%;background:${color};"></div></div></div>
      <div class="module-pct" style="color:${color};">${pct}%</div>
    </div>`;
  }).join('');
}

async function changePassword() {
  const cur     = document.getElementById('pwd-current').value;
  const nw      = document.getElementById('pwd-new').value;
  const conf    = document.getElementById('pwd-confirm').value;
  const errEl   = document.getElementById('pwd-error');
  const btnText = document.getElementById('pwd-btn-text');
  errEl.style.display = 'none';
  if (!cur || !nw || !conf) {
    errEl.textContent = currentLang === 'fr' ? 'Veuillez remplir tous les champs.' : 'Please fill in all fields.';
    errEl.style.display = ''; return;
  }
  btnText.textContent = '…';
  document.getElementById('pwd-btn').disabled = true;
  try {
    const res  = await fetch('api_update_profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrf },
      body: JSON.stringify({ action: 'change_password', current_password: cur, new_password: nw, confirm_password: conf })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || T[currentLang].serverError);
    document.getElementById('pwd-current').value = '';
    document.getElementById('pwd-new').value = '';
    document.getElementById('pwd-confirm').value = '';
    showToast(T[currentLang].toastPwdChanged);
  } catch(err) {
    errEl.textContent = err.message;
    errEl.style.display = '';
  } finally {
    btnText.textContent = T[currentLang].pwdBtn;
    document.getElementById('pwd-btn').disabled = false;
  }
}

/* ── LESSON FEED ── */
async function loadFeed() {
  const list = document.getElementById('feed-list');
  list.innerHTML = `<p style="color:var(--muted);font-size:.85rem;">${T[currentLang].feedLoading}</p>`;
  try {
    const res  = await fetch('api_lesson_posts.php?action=feed');
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);
    _cachedFeedPosts = data.posts;
    renderFeed(_cachedFeedPosts);
  } catch(e) {
    list.innerHTML = `<p style="color:var(--red);font-size:.85rem;">${T[currentLang].errorPrefix + e.message}</p>`;
  }
}

function renderFeed(posts) {
  const list = document.getElementById('feed-list');
  const tr   = T[currentLang];
  if (!posts || posts.length === 0) {
    list.innerHTML = `<div style="text-align:center;padding:3rem 1rem;">
      <div style="font-size:2.5rem;margin-bottom:.75rem;">📖</div>
      <div style="font-family:var(--font);font-weight:700;font-size:1.05rem;margin-bottom:.4rem;">${tr.feedEmptyTitle}</div>
      <p style="color:var(--muted);font-size:.88rem;max-width:340px;margin:0 auto;">${tr.feedEmptyTxt}</p>
    </div>`;
    return;
  }
  list.innerHTML = posts.map(p => {
    const lang       = currentLang;
    const courseName = p.group_name_fr || p.group_name_ar;
    const dateStr    = p.session_date ? new Date(p.session_date + 'T00:00:00').toLocaleDateString(lang === 'en' ? 'en-GB' : 'fr-FR', { day:'numeric', month:'long', year:'numeric' }) : '';
    const linkBtn    = p.link ? `<a href="${e(p.link)}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:.45rem;margin-top:.75rem;padding:.5rem 1rem;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);border-radius:8px;font-size:.82rem;font-weight:600;color:var(--blue);text-decoration:none;font-family:var(--font);transition:background .2s;" onmouseover="this.style.background='rgba(59,130,246,.18)'" onmouseout="this.style.background='rgba(59,130,246,.1)'"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>${e(tr.feedOpenLink)}</a>` : '';
    const notesHtml  = p.notes ? `<div style="margin-top:.75rem;padding:.85rem 1rem;background:rgba(30,27,75,.04);border-radius:10px;border-left:3px solid rgba(59,130,246,.4);"><p style="color:var(--muted);font-size:.87rem;white-space:pre-wrap;line-height:1.7;margin:0;">${e(p.notes)}</p></div>` : '';
    return `<div style="background:#fff;border:1px solid var(--border);border-radius:16px;padding:1.25rem 1.5rem;margin-bottom:1rem;box-shadow:0 2px 8px rgba(30,27,75,.05);">
      <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.6rem;">
        <span style="font-size:.72rem;font-weight:700;background:rgba(245,158,11,.12);color:#f59e0b;border:1px solid rgba(245,158,11,.3);padding:.15rem .6rem;border-radius:100px;font-family:var(--font);">${e(courseName)}</span>
        <span style="font-size:.78rem;color:var(--muted);">📅 ${dateStr}</span>
      </div>
      <div style="font-family:var(--font);font-weight:700;font-size:1rem;color:var(--white);">${e(p.title)}</div>
      ${linkBtn}${notesHtml}
    </div>`;
  }).join('');
}

function handleAvatarUpload(input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 2 * 1024 * 1024) {
    showToast(currentLang === 'en' ? 'Image too large (max 2 MB)' : 'Image trop grande (max 2 Mo)', 'error');
    return;
  }
  const reader = new FileReader();
  reader.onload = function(ev) {
    const dataUrl = ev.target.result;
    applyAvatarEverywhere(dataUrl); // instant local preview
    // Upload to server
    const fd = new FormData();
    fd.append('avatar', file);
    fd.append('csrf_token', _csrf);
    fetch('api_update_profile.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.ok) {
          applyAvatarEverywhere(d.url);
          try { localStorage.removeItem('upskill_avatar'); } catch(e2) {}
          showToast(currentLang === 'en' ? 'Photo updated ✓' : 'Photo mise à jour ✓');
        } else {
          try { localStorage.setItem('upskill_avatar', dataUrl); } catch(e2) {}
          showToast(currentLang === 'en' ? 'Photo saved locally' : 'Photo sauvegardée localement', 'warn');
        }
      })
      .catch(() => {
        try { localStorage.setItem('upskill_avatar', dataUrl); } catch(e2) {}
        showToast(currentLang === 'en' ? 'Photo saved locally (offline)' : 'Photo sauvegardée localement (hors ligne)', 'warn');
      });
  };
  reader.readAsDataURL(file);
}

function applyAvatarEverywhere(dataUrl) {
  // Each pair: [photo img id, dino svg id]
  const pairs = [
    ['mascot-av-img',   'mascot-dino-svg'],
    ['sidebar-av-img',  'sidebar-dino-svg'],
    ['topbar-av-img',   'topbar-dino-svg'],
    ['settings-av-img', 'settings-dino-svg'],
  ];
  pairs.forEach(([imgId, svgId]) => {
    const img = document.getElementById(imgId);
    const svg = document.getElementById(svgId);
    if (img) { img.src = dataUrl; img.style.display = 'block'; }
    if (svg) svg.style.display = 'none';
  });
}

function loadSavedAvatar() {
  // Prefer server-stored avatar (persists across devices/browsers)
  if (typeof SERVER_AVATAR === 'string' && SERVER_AVATAR) {
    applyAvatarEverywhere(SERVER_AVATAR);
    return;
  }
  // Fall back to localStorage (legacy / offline)
  try {
    const dataUrl = localStorage.getItem('upskill_avatar');
    if (!dataUrl) return;
    applyAvatarEverywhere(dataUrl);
  } catch(e) {}
}

/* ── HOW-TO CARDS ── */
function renderHowTo() {
  const grid = document.getElementById('howto-grid');
  if (!grid) return;
  const cards = T[currentLang].howtoCards || [];
  grid.innerHTML = cards.map((c, i) => `
    <div class="howto-card">
      <div class="howto-thumb" style="--from:${e(c.from)};--to:${e(c.to)};">
        <div class="howto-play" onclick="playHowTo(${i})">
          <svg width="22" height="22" fill="white" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        </div>
        <div class="howto-thumb-icon">${c.icon}</div>
      </div>
      <div class="howto-body">
        <div class="howto-card-title">${e(c.title)}</div>
        <div class="howto-card-desc">${e(c.desc)}</div>
        <button class="howto-card-btn" onclick="playHowTo(${i})">
          <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
          ${e(c.btn)}
        </button>
      </div>
    </div>
  `).join('');
}

function playHowTo(idx) {
  showToast(currentLang === 'en' ? 'Coming soon — videos are being prepared!' : 'Bientôt disponible — vidéos en cours de préparation !');
}

function showToast(msg, type) {
  const toast = document.getElementById('toast');
  document.getElementById('toast-msg').textContent = msg;
  toast.className = 'toast show' + (type === 'error' ? ' error' : type === 'warn' ? ' warn' : '');
  setTimeout(() => { toast.className = 'toast'; }, 2800);
}

function logout() {
  sessionStorage.clear();
  window.location.href = 'logout.php';
}

/* INIT */
document.addEventListener('DOMContentLoaded', () => {
  const _sl = sessionStorage.getItem('upskill_lang');
  const savedLang = (_sl === 'fr' || _sl === 'en') ? _sl : 'en';
  setLang(savedLang);
  hydrateLiveData();
  updateProgress();
  renderAssignments();
  renderHowTo();
  loadSavedAvatar();
  const validPages = ['home','myclass','assignments','feed','quizzes','progress','howto','settings','announcements'];
  const savedPage = sessionStorage.getItem('upskill_page_s');
  if (savedPage && validPages.includes(savedPage) && savedPage !== 'home') {
    navigate(savedPage);
  }
  // Keyboard accessibility for sidebar nav items
  document.querySelectorAll('.nav-item').forEach(el => {
    el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); el.click(); } });
  });
});

/* ── ASSIGN SUBTITLE (always computed from live counts, never from static T string) ── */
function updateAssignSubtitle() {
  const sub = document.getElementById('assign-page-sub');
  if (!sub) return;
  const tr  = T[currentLang];
  const p   = LIVE.pending_count   || 0;
  const o   = LIVE.overdue_count   || 0;
  const s   = LIVE.submitted_count || 0;
  sub.textContent = `${p} ${tr.tabPending.toLowerCase()} · ${o} ${tr.badgeOverdue.toLowerCase()} · ${s} ${tr.badgeSubmitted.toLowerCase()}`;
}

/* ── PROGRESS PAGE HEADER ── */
function updateProgressHeader() {
  const lang    = currentLang;
  const present = LIVE.att_present || 0;
  const total   = LIVE.att_total   || 0;
  const c       = LIVE.course;

  const sp = document.getElementById('sessions-present');
  if (sp) sp.textContent = present;

  const hrsEl = document.getElementById('hrs-of');
  if (hrsEl) hrsEl.textContent = total > 0
    ? `/ ${total} ${lang === 'fr' ? 'séances' : 'sessions'}`
    : '';

  const ot = document.getElementById('on-track');
  if (ot) {
    let txt, color, bg, border;
    if (total === 0) {
      txt = lang === 'fr' ? 'Aucune séance' : 'No sessions yet';
      color = 'var(--muted)'; bg = 'rgba(255,255,255,.06)'; border = 'var(--border)';
    } else {
      const rate = present / total;
      if (rate >= 0.8) {
        txt = lang === 'fr' ? 'En bonne voie 🎯' : 'On track 🎯';
        color = 'var(--green)'; bg = 'var(--green-glow)'; border = 'rgba(62,207,120,.3)';
      } else if (rate >= 0.6) {
        txt = lang === 'fr' ? 'Continuez 💪' : 'Keep it up 💪';
        color = '#f59e0b'; bg = 'rgba(245,158,11,.15)'; border = 'rgba(245,158,11,.3)';
      } else {
        txt = lang === 'fr' ? 'À améliorer ⚠️' : 'Needs improvement ⚠️';
        color = '#f87171'; bg = 'rgba(248,113,113,.15)'; border = 'rgba(248,113,113,.3)';
      }
    }
    ot.textContent = txt;
    ot.style.color = color;
    ot.style.background = bg;
    ot.style.borderColor = border;
  }

  const cs = document.getElementById('course-session');
  if (cs && c) {
    cs.textContent = lang === 'en'
      ? (c.label_en || c.label_fr)
      : (c.label_fr || c.label_en);
  }
}

/* ── LIVE DATA HYDRATION ── */
function hydrateLiveData() {
  const c    = LIVE.course;
  const lang = currentLang;

  // ── 1. User name & initials ──────────────────────────────────────────────
  const fn = <?= json_encode($_SESSION['full_name'] ?? $_SESSION['username'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  if (fn) {
    const parts = fn.trim().split(/\s+/);
    const sn = document.getElementById('sidebar-name'); if (sn) sn.textContent = fn;
    st('welcome-name', fn.split(' ')[0]);
    st('settings-name', fn);
    const pi = document.getElementById('pref-name'); if (pi) pi.value = fn;
  }

  // ── 2. Attendance ────────────────────────────────────────────────────────
  // (attendance rate is shown on My Class page; progress circle is driven
  //  by updateProgress() which uses real assignment completion data)

  // ── 5. Settings role + progress header ──────────────────────────────────
  if (c) {
    const courseName = lang === 'en'
      ? (c.label_en || c.label_fr)
      : (c.label_fr || c.label_en);
    const sr = document.getElementById('settings-role');
    if (sr) sr.textContent = T[lang].roleLabel + ' · ' + courseName;
  }
  updateProgressHeader();

  // ── 6. Assignment page subtitle ──────────────────────────────────────────
  updateAssignSubtitle();

  // ── 7. Nav badge on assignments ──────────────────────────────────────────
  const pending = (LIVE.pending_count || 0) + (LIVE.overdue_count || 0);
  const navBadge = document.getElementById('assign-nav-badge');
  if (navBadge) {
    if (pending > 0) { navBadge.textContent = pending; navBadge.style.display = ''; }
    else { navBadge.style.display = 'none'; }
  }
}/* ══════════════════════════════════════════════════════
   ASSIGNMENT SUBMISSION
══════════════════════════════════════════════════════ */
const _csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function openSubmitModal(assignId, title, due) {
  document.getElementById('submit-assign-id').value = assignId;
  document.getElementById('submit-assign-title').textContent = title;
  document.getElementById('submit-assign-due').textContent = T[currentLang].dueLblPre + due;
  document.getElementById('submit-comment').value = '';
  document.getElementById('submit-char-count').textContent = '0';
  document.getElementById('submit-error').style.display = 'none';
  document.getElementById('submit-btn-text').textContent = T[currentLang].submitBtn;
  document.getElementById('submit-modal-title').textContent = T[currentLang].modalTitle;
  const commentLbl = document.getElementById('submit-comment-lbl');
  if (commentLbl) commentLbl.textContent = currentLang === 'fr' ? 'Commentaire (optionnel)' : 'Comment (optional)';
  const cancelBtn = document.getElementById('submit-cancel-btn');
  if (cancelBtn) cancelBtn.textContent = T[currentLang].cancelBtn;
  const commentInput = document.getElementById('submit-comment');
  if (commentInput) commentInput.placeholder = currentLang === 'fr' ? 'Décrivez votre travail, ajoutez des notes pour le professeur…' : 'Describe your work, add notes for the teacher…';
  const fileLbl = document.getElementById('submit-file-lbl');
  if (fileLbl) fileLbl.textContent = currentLang === 'fr' ? 'Fichier (optionnel)' : 'File (optional)';
  const fileInput = document.getElementById('submit-file');
  if (fileInput) fileInput.value = '';
  document.getElementById('modal-submit').classList.add('open');
  document.getElementById('submit-comment').focus();
}

function closeSubmitModal() {
  document.getElementById('modal-submit').classList.remove('open');
}

document.getElementById('submit-comment')?.addEventListener('input', function() {
  document.getElementById('submit-char-count').textContent = this.value.length;
});

// Close modal on backdrop click
document.getElementById('modal-submit')?.addEventListener('click', function(e) {
  if (e.target === this) closeSubmitModal();
});

async function confirmSubmit() {
  const aid     = document.getElementById('submit-assign-id').value;
  const comment = document.getElementById('submit-comment').value.trim();
  const errEl   = document.getElementById('submit-error');
  const btn     = document.getElementById('submit-confirm-btn');
  const btnText = document.getElementById('submit-btn-text');

  errEl.style.display = 'none';
  btnText.textContent = '…';
  btn.disabled = true;

  try {
    const fd = new FormData();
    fd.append('assignment_id', aid);
    fd.append('comment', comment);
    const fileEl = document.getElementById('submit-file');
    if (fileEl && fileEl.files[0]) fd.append('file', fileEl.files[0]);

    const res = await fetch('api_submit.php?action=submit', {
      method: 'POST',
      headers: { 'X-CSRF-Token': _csrf },
      body: fd
    });
    const data = await res.json();

    if (!data.ok) throw new Error(data.error || T[currentLang].serverError);

    // Update local LIVE data
    const a = LIVE.assignments.find(x => x.id == aid);
    if (a) {
      const prevStatus = a.status;
      a.status       = 'submitted';
      a.submitted_at = data.submitted_at;
      LIVE.submitted_count = (LIVE.submitted_count || 0) + 1;
      if (prevStatus === 'pending') LIVE.pending_count = Math.max(0, (LIVE.pending_count || 1) - 1);
      if (prevStatus === 'overdue') LIVE.overdue_count = Math.max(0, (LIVE.overdue_count || 1) - 1);
    }

    closeSubmitModal();
    renderAssignments();
    hydrateLiveData();
    showToast(T[currentLang].badgeSubmitted + ' ✓', 'success');

  } catch(err) {
    errEl.textContent = err.message;
    errEl.style.display = '';
  } finally {
    btnText.textContent = T[currentLang].submitBtn;
    btn.disabled = false;
  }
}

/* ── ANNOUNCEMENTS ── */
async function loadAnnouncements() {
  const list = document.getElementById('ann-list');
  if (!list) return;
  const tr = T[currentLang] || T.fr;
  list.innerHTML = `<p style="color:var(--muted);font-size:.85rem;">${tr.annLoading}</p>`;
  try {
    const res  = await fetch('api_announcements.php?action=list');
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    const items = (data.announcements || []).filter(a => a.target === 'all' || a.target === 'students');
    if (!items.length) {
      list.innerHTML = `<div style="text-align:center;padding:3rem 1rem;color:var(--muted);">
        <div style="font-size:2rem;margin-bottom:.75rem;">📢</div>
        <div style="font-family:var(--font);font-size:.95rem;">${tr.annEmpty}</div>
      </div>`;
      return;
    }
    list.innerHTML = items.map(a => {
      const date = a.created_at ? new Date(a.created_at.replace(' ','T')).toLocaleDateString(
        currentLang === 'en' ? 'en-GB' : 'fr-FR',
        { day:'numeric', month:'long', year:'numeric' }) : '';
      const targetLabel = (tr.annTarget || {})[a.target] || a.target;
      return `<div style="background:#fff;border:1px solid var(--border);border-radius:16px;padding:1.25rem 1.5rem;margin-bottom:1rem;box-shadow:0 2px 8px rgba(30,27,75,.05);">
        <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.6rem;">
          <span style="font-size:.72rem;font-weight:700;background:rgba(249,115,22,.1);color:#f97316;border:1px solid rgba(249,115,22,.25);padding:.15rem .6rem;border-radius:100px;font-family:var(--font);">📢 ${e(targetLabel)}</span>
          <span style="font-size:.78rem;color:var(--muted);">📅 ${date}</span>
          <span style="font-size:.78rem;color:var(--muted2);">${tr.annBy || 'By'}: ${e(a.author_name)}</span>
        </div>
        <div style="font-family:var(--font);font-weight:700;font-size:1rem;color:var(--white);margin-bottom:.5rem;">${e(a.title)}</div>
        <div style="font-size:.88rem;color:var(--muted);line-height:1.65;white-space:pre-wrap;">${e(a.body)}</div>
      </div>`;
    }).join('');
  } catch(err) {
    list.innerHTML = `<p style="color:var(--red);font-size:.85rem;">${e(err.message)}</p>`;
  }
}

let _retractAid = null;
function openRetractModal(aid) {
  _retractAid = aid;
  const tr = T[currentLang];
  document.getElementById('retract-modal-title').textContent = tr.retractTitle;
  document.getElementById('retract-modal-body').textContent  = tr.retractConfirm;
  document.getElementById('retract-btn-text').textContent    = tr.retractBtn;
  document.getElementById('retract-cancel-btn').textContent  = tr.cancelBtn;
  document.getElementById('modal-retract').classList.add('open');
}
function closeRetractModal() {
  document.getElementById('modal-retract').classList.remove('open');
  _retractAid = null;
}
document.getElementById('modal-retract')?.addEventListener('click', function(e) {
  if (e.target === this) closeRetractModal();
});
function confirmRetract() {
  const aid = _retractAid;
  closeRetractModal();
  if (aid !== null) retractSubmission(aid);
}

async function retractSubmission(aid) {

  try {
    const res = await fetch('api_submit.php?action=unsubmit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrf },
      body: JSON.stringify({ assignment_id: aid })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || T[currentLang].serverError);

    const a = LIVE.assignments.find(x => x.id == aid);
    if (a) {
      a.status = a.due_date && new Date(a.due_date) < new Date() ? 'overdue' : 'pending';
      a.submitted_at = null;
      LIVE.submitted_count = Math.max(0, (LIVE.submitted_count || 1) - 1);
      if (a.status === 'pending') LIVE.pending_count = (LIVE.pending_count || 0) + 1;
      if (a.status === 'overdue') LIVE.overdue_count = (LIVE.overdue_count || 0) + 1;
    }

    renderAssignments();
    hydrateLiveData();
    showToast(T[currentLang].retractBtn + ' ✓', 'default');

  } catch(err) {
    showToast(err.message, 'error');
  }
}
</script>
<!-- NOTIF PANEL (outside topbar to avoid stacking context clipping) -->
<div class="notif-panel" id="notif-panel" onclick="event.stopPropagation()" style="position:fixed;top:64px;right:1rem;z-index:9100;">
  <div class="notif-panel-header">
    <span class="notif-panel-title" id="notif-panel-title">Notifications</span>
    <div style="display:flex;align-items:center;gap:.5rem;">
      <button class="notif-mark-all" onclick="markAllRead()" id="notif-mark-all-btn">Mark all read</button>
      <button onclick="closeNotifPanel()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:1rem;padding:0 .2rem;line-height:1;" title="Close">✕</button>
    </div>
  </div>
  <div class="notif-list" id="notif-list">
    <div class="notif-empty" id="notif-loading">Loading…</div>
  </div>
</div>

<!-- PROFILE MENU DROPDOWN -->
<div class="profile-menu" id="profile-menu" onclick="event.stopPropagation()">
  <button class="profile-menu-item" onclick="profileMenuAction('name')">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <span id="pm-name-lbl">Change first name</span>
  </button>
  <button class="profile-menu-item" onclick="profileMenuAction('avatar')">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    <span id="pm-avatar-lbl">Change avatar</span>
  </button>
  <hr class="profile-menu-sep">
  <button class="profile-menu-item danger" onclick="profileMenuAction('logout')">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    <span id="pm-logout-lbl">Log out</span>
  </button>
</div>

<!-- ── EMAIL COLLECTION POPUP ── -->
<div id="email-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:8000;pointer-events:none;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#162436;border:1px solid rgba(62,207,120,.25);border-radius:20px;padding:2rem;width:100%;max-width:440px;position:relative;animation:slideUp .3s ease;">
    <!-- Icon -->
    <div style="width:52px;height:52px;background:rgba(62,207,120,.12);border-radius:14px;display:flex;align-items:center;justify-content:center;margin-bottom:1.25rem;">
      <svg width="24" height="24" fill="none" stroke="#3ecf78" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    </div>
    <div id="eml-title" style="font-family:'Sora',sans-serif;font-size:1.15rem;font-weight:700;margin-bottom:.4rem;">Add your email address</div>
    <div id="eml-sub" style="color:rgba(255,255,255,.55);font-size:.88rem;margin-bottom:1.5rem;line-height:1.5;">To receive your class reminders, assignments and login credentials, add your email. You will only see this message once.</div>
    <div style="margin-bottom:1.25rem;">
      <label id="eml-label" style="display:block;font-family:'Sora',sans-serif;font-size:.72rem;font-weight:600;color:rgba(255,255,255,.5);letter-spacing:.06em;text-transform:uppercase;margin-bottom:.4rem;">EMAIL ADDRESS</label>
      <input id="eml-input" type="email" maxlength="180"
        placeholder="you@example.com"
        style="width:100%;padding:.85rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;transition:border-color .2s;"
        oninput="document.getElementById('eml-error').style.display='none'"
        onkeydown="if(event.key==='Enter')submitEmail()">
    </div>
    <div id="eml-error" style="display:none;color:#f87171;font-size:.82rem;margin-bottom:.75rem;"></div>
    <button id="eml-btn" onclick="submitEmail()"
      style="width:100%;padding:.9rem;background:#3ecf78;color:#0f1d2e;font-family:'Sora',sans-serif;font-weight:700;font-size:.95rem;border:none;border-radius:12px;cursor:pointer;margin-bottom:.75rem;">
      <span id="eml-btn-lbl">Save my email →</span>
    </button>
    <button onclick="dismissEmail()"
      style="width:100%;padding:.6rem;background:none;border:none;color:rgba(255,255,255,.3);font-family:'DM Sans',sans-serif;font-size:.83rem;cursor:pointer;">
      <span id="eml-skip-lbl">Remind me later</span>
    </button>
  </div>
</div>

<script>
// ── Email popup translations ──────────────────────────────────────────────────
const EMAIL_T = {
  fr: {
    title:    'Ajoutez votre adresse e-mail',
    sub:      'Pour recevoir vos rappels de cours, devoirs et identifiants de connexion, ajoutez votre e-mail. Vous ne verrez ce message qu\'une seule fois.',
    label:    'ADRESSE E-MAIL',
    ph:       'vous@exemple.com',
    btn:      'Enregistrer mon e-mail →',
    skip:     'Me le rappeler plus tard',
    errEmpty: 'Veuillez saisir une adresse e-mail.',
    errInvalid:'Adresse e-mail invalide.',
    errTaken: 'Cet e-mail est déjà utilisé.',
    errFail:  'Erreur. Veuillez réessayer.',
    saving:   'Enregistrement…',
  },
  en: {
    title:    'Add your email address',
    sub:      'To receive your class reminders, assignments and login credentials, add your email. You will only see this message once.',
    label:    'EMAIL ADDRESS',
    ph:       'you@example.com',
    btn:      'Save my email →',
    skip:     'Remind me later',
    errEmpty: 'Please enter an email address.',
    errInvalid:'Invalid email address.',
    errTaken: 'This email is already in use.',
    errFail:  'An error occurred. Please try again.',
    saving:   'Saving…',
  },
};

function updateEmailPopupLang() {
  const t = EMAIL_T[currentLang] || EMAIL_T.fr;
  const set = (id, val) => { const el = document.getElementById(id); if(el) el.textContent = val; };
  set('eml-title', t.title);
  set('eml-sub', t.sub);
  set('eml-label', t.label);
  set('eml-btn-lbl', t.btn);
  set('eml-skip-lbl', t.skip);
  const inp = document.getElementById('eml-input');
  if (inp) inp.placeholder = t.ph;
}

function showEmailPopup() {
  updateEmailPopupLang();
  const o = document.getElementById('email-overlay');
  o.style.display = 'flex';
  o.style.pointerEvents = 'auto';
  setTimeout(() => document.getElementById('eml-input').focus(), 100);
}

function dismissEmail() {
  const eo = document.getElementById('email-overlay');
  eo.style.display = 'none';
  eo.style.pointerEvents = 'none';
  // Remember dismissal for this session so it doesn't reappear on page refresh
  sessionStorage.setItem('email_popup_dismissed', '1');
}

async function submitEmail() {
  const t     = EMAIL_T[currentLang] || EMAIL_T.fr;
  const input = document.getElementById('eml-input');
  const email = input.value.trim();
  const errEl = document.getElementById('eml-error');
  const btn   = document.getElementById('eml-btn');
  const lbl   = document.getElementById('eml-btn-lbl');
  errEl.style.display = 'none';

  if (!email) { errEl.textContent = t.errEmpty; errEl.style.display = ''; return; }
  if (!email.includes('@') || !email.includes('.')) { errEl.textContent = t.errInvalid; errEl.style.display = ''; return; }

  btn.disabled = true; lbl.textContent = t.saving;

  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const body = new URLSearchParams({ action: 'save_email', email, csrf_token: csrf });
    const res  = await fetch('api_students.php', { method: 'POST', body });
    const data = await res.json();

    if (data.ok) {
      const eo = document.getElementById('email-overlay');
  eo.style.display = 'none';
  eo.style.pointerEvents = 'none';
      showToast(currentLang === 'en' ? '✅ Email saved!' : '✅ E-mail enregistré !');
    } else {
      errEl.textContent = data.error || t.errFail;
      errEl.style.display = '';
      btn.disabled = false; lbl.textContent = t.btn;
    }
  } catch(e) {
    errEl.textContent = t.errFail;
    errEl.style.display = '';
    btn.disabled = false; lbl.textContent = t.btn;
  }
}

// Show popup on load if student has no email and hasn't dismissed this session
document.addEventListener('DOMContentLoaded', () => {
  if (!HAS_EMAIL && !sessionStorage.getItem('email_popup_dismissed')) {
    // Small delay so dashboard loads first
    setTimeout(showEmailPopup, 800);
  }
});
</script>


<script>
/* ── NOTIFICATIONS ── */
const NOTIF_ICONS = {
  new_assignment: '📚',
  overdue: '⚠️',
  submission: '✅',
  info: '🔔',
  announcement: '📢',
  quiz: '🧠',
  message: '💬',
  lesson_note: '📝',
};
const NOTIF_T = {
  fr: { title:'Notifications', markAll:'Tout lire', empty:'Aucune notification', justNow:'À l\'instant', minAgo:'min', hrsAgo:'h', daysAgo:'j' },
  en: { title:'Notifications', markAll:'Mark all read', empty:'No notifications', justNow:'Just now', minAgo:'min', hrsAgo:'h', daysAgo:'d' },
};

let notifOpen = false;
let notifData = [];

function closeNotifPanel() {
  notifOpen = false;
  document.getElementById('notif-panel').classList.remove('open');
}

function toggleNotifPanel() {
  notifOpen = !notifOpen;
  const panel = document.getElementById('notif-panel');
  const btn   = document.getElementById('notif-btn');
  if (notifOpen) {
    const rect = btn.getBoundingClientRect();
    panel.style.top  = (rect.bottom + 8) + 'px';
    panel.style.right = (window.innerWidth - rect.right) + 'px';
    panel.style.left  = 'auto';
    if (document.body.classList.contains('ar')) {
      panel.style.left  = rect.left + 'px';
      panel.style.right = 'auto';
    }
    loadNotifications();
  }
  panel.classList.toggle('open', notifOpen);
}

// Close panels when clicking outside
let profileMenuOpen = false;
document.addEventListener('click', function(e) {
  if (notifOpen && !document.getElementById('notif-btn').contains(e.target)) {
    closeNotifPanel();
  }
  if (profileMenuOpen && !document.getElementById('topbar-avatar').contains(e.target)) {
    profileMenuOpen = false;
    document.getElementById('profile-menu').classList.remove('open');
  }
});

function toggleProfileMenu(e) {
  e.stopPropagation();
  profileMenuOpen = !profileMenuOpen;
  document.getElementById('profile-menu').classList.toggle('open', profileMenuOpen);
  // Close notif panel if open
  if (profileMenuOpen && notifOpen) {
    closeNotifPanel();
  }
  applyProfileMenuTranslations();
}

function applyProfileMenuTranslations() {
  const PM = {
    fr: { name:"Changer le prénom", avatar:"Changer l'avatar", logout:"Déconnexion" },
    en: { name:"Change first name",  avatar:"Change avatar",     logout:"Log out" },
  };
  const t = PM[currentLang] || PM.fr;
  const s = (id, v) => { const el = document.getElementById(id); if(el) el.textContent = v; };
  s('pm-name-lbl',   t.name);
  s('pm-avatar-lbl', t.avatar);
  s('pm-logout-lbl', t.logout);
}

function profileMenuAction(action) {
  profileMenuOpen = false;
  document.getElementById('profile-menu').classList.remove('open');
  if (action === 'name') {
    // Go to settings and focus the name field
    const settingsNav = document.getElementById('nav-set');
    navigate('settings', settingsNav);
    setTimeout(() => {
      const inp = document.getElementById('pref-name');
      if (inp) { inp.focus(); inp.select(); }
    }, 120);
  } else if (action === 'avatar') {
    document.getElementById('avatar-input').click();
  } else if (action === 'logout') {
    logout();
  }
}

function timeAgo(ageMin) {
  const t = NOTIF_T[currentLang] || NOTIF_T.fr;
  if (ageMin < 2)  return t.justNow;
  if (ageMin < 60) return ageMin + ' ' + t.minAgo;
  const h = Math.floor(ageMin / 60);
  if (h < 24) return h + t.hrsAgo;
  return Math.floor(h / 24) + t.daysAgo;
}

async function loadNotifications() {
  try {
    const res  = await fetch('api_notifications.php?action=list');
    const data = await res.json();
    if (!data.ok) return;
    notifData = data.notifications || [];
    renderNotifList();
    updateNotifBadge(data.unread);
  } catch(e) {}
}

function renderNotifList() {
  const list = document.getElementById('notif-list');
  const t    = NOTIF_T[currentLang] || NOTIF_T.fr;
  document.getElementById('notif-panel-title').textContent = t.title;
  document.getElementById('notif-mark-all-btn').textContent = t.markAll;

  if (!notifData.length) {
    list.innerHTML = '<div class="notif-empty">' + t.empty + '</div>';
    return;
  }
  const lang = currentLang;
  list.innerHTML = notifData.map(n => {
    const icon  = NOTIF_ICONS[n.type] || '🔔';
    const title = lang === 'en' ? (n.title_en || n.title_fr) : n.title_fr;
    const body  = lang === 'en' ? (n.body_en  || n.body_fr)  : n.body_fr;
    // Strip the internal #id reference from display
    const bodyClean = body.replace(/ #\d+$/, '');
    const ago   = timeAgo(parseInt(n.age_min) || 0);
    return `<div class="notif-item ${n.is_read == 0 ? 'unread' : ''}" onclick="markOneRead(${n.id}, this)">
      <div class="notif-icon ${n.type}">${icon}</div>
      <div class="notif-content">
        <div class="notif-title">${title}</div>
        <div class="notif-body">${bodyClean}</div>
        <div class="notif-time">${ago}</div>
      </div>
      ${n.is_read == 0 ? '<div class="notif-unread-dot"></div>' : ''}
    </div>`;
  }).join('');
}

function updateNotifBadge(count) {
  const badge = document.getElementById('notif-badge');
  if (!badge) return;
  if (count > 0) {
    badge.textContent = count > 9 ? '9+' : count;
    badge.style.display = 'flex';
  } else {
    badge.style.display = 'none';
  }
}

function markAllRead() {
  // Update UI immediately — don't wait for the server round-trip
  notifData.forEach(n => n.is_read = 1);
  renderNotifList();
  updateNotifBadge(0);
  // Fire-and-forget server sync
  fetch('api_notifications.php', {
    method:'POST',
    headers:{'X-CSRF-Token':_csrf},
    body: new URLSearchParams({ action:'mark_read' })
  }).catch(() => {});
}

function markOneRead(id, el) {
  // Update UI immediately
  el.classList.remove('unread');
  const dot = el.querySelector('.notif-unread-dot');
  if (dot) dot.remove();
  const n = notifData.find(x => x.id == id);
  if (n) n.is_read = 1;
  const unread = notifData.filter(x => x.is_read == 0).length;
  updateNotifBadge(unread);
  // Fire-and-forget server sync
  fetch('api_notifications.php', {
    method:'POST',
    headers:{'X-CSRF-Token':_csrf},
    body: new URLSearchParams({ action:'mark_one', id })
  }).catch(() => {});
  // Navigate to relevant page and close panel
  closeNotifPanel();
  const typeNav = {
    new_assignment:'assignments', overdue:'assignments',
    lesson_note:'feed', announcement:'announcements',
    quiz:'quizzes', message:'home'
  };
  const dest = n ? (typeNav[n.type] || 'home') : 'home';
  navigate(dest);
}

// Poll for new notifications every 60 seconds
setInterval(() => {
  if (!notifOpen) {
    fetch('api_notifications.php?action=list')
      .then(r => r.json())
      .then(d => { if (d.ok) updateNotifBadge(d.unread); })
      .catch(() => {});
  }
}, 60000);

// Load badge count on page load
document.addEventListener('DOMContentLoaded', () => {
  fetch('api_notifications.php?action=list')
    .then(r => r.json())
    .then(d => { if (d.ok) { notifData = d.notifications; updateNotifBadge(d.unread); } })
    .catch(() => {});
});
</script>

</body>
</html>
