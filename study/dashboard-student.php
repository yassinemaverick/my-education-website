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

    // ── Ensure assignments tables exist ─────────────────────────────────────
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

    // ── Course info ──────────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.group_name_fr, c.group_name_ar,
                   c.subject_fr, c.subject_ar, c.level,
                   c.students_count, c.schedule_json,
                   u.full_name AS teacher_name
            FROM   student_courses sc
            JOIN   courses c ON c.id = sc.course_id
            LEFT   JOIN teacher_courses tc ON tc.course_id = c.id
            LEFT   JOIN users u ON u.id = tc.teacher_id
            WHERE  sc.student_id = ? LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $liveData['course'] = $stmt->fetch() ?: null;
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
                   sub.submitted_at
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
            // Format due date nicely
            $row['due_fmt'] = '';
            if ($row['due_date']) {
                $due = new DateTime($row['due_date']);
                $diff = (int)$now->diff($due)->days;
                $past = $due < $now;
                if (!$past && $diff === 0) $row['due_fmt'] = 'Aujourd\'hui';
                elseif (!$past && $diff === 1) $row['due_fmt'] = 'Demain';
                elseif (!$past) $row['due_fmt'] = $due->format('d M');
                else $row['due_fmt'] = $due->format('d M') . ' (en retard)';
            }
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
                'label_ar'=> 'جلسة حضرت',
                'detail_fr'=> 'Séance n°' . $r['session_num'],
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
                'label_ar'=> 'واجب مُسلَّم',
                'detail_fr'=> $r['title_fr'],
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
                'label_ar'=> 'واجب متأخر',
                'detail_fr'=> $r['title_fr'],
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
    $courseId = (int)$liveData['course']['id'];
    try {
        $sStmt = $pdo->prepare("
            SELECT u.full_name, u.id,
                   COUNT(a.id) AS total_sessions,
                   COALESCE(SUM(a.present), 0) AS present_sessions
            FROM   student_courses sc
            JOIN   users u ON u.id = sc.student_id
            LEFT   JOIN attendance a ON a.student_id = sc.student_id
            WHERE  sc.course_id = ?
            GROUP  BY u.id, u.full_name
            ORDER  BY (COALESCE(SUM(a.present),0) / GREATEST(COUNT(a.id),1)) DESC,
                      present_sessions DESC
            LIMIT  5
        ");
        $sStmt->execute([$courseId]);
        $starsOfMonth = $sStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    try {
        $caStmt = $pdo->prepare("
            SELECT u.full_name, sub.submitted_at, a.title_fr, a.title_ar
            FROM   assignment_submissions sub
            JOIN   users u ON u.id = sub.student_id
            JOIN   assignments a ON a.id = sub.assignment_id
            WHERE  a.course_id = ? AND sub.status = 'submitted'
            ORDER  BY sub.submitted_at DESC
            LIMIT  8
        ");
        $caStmt->execute([$courseId]);
        $classActivity = $caStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}
$jsStars    = json_encode($starsOfMonth, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$jsClassAct = json_encode($classActivity, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

$jsData = json_encode($liveData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
<title>Upskill – Tableau de bord Étudiant</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
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
html { scroll-behavior: smooth; }
body { background:var(--navy); color:var(--white); font-family:var(--font-body); min-height:100vh; display:flex; overflow-x:hidden; }
body.ar { font-family:var(--font-ar); direction:rtl; }
body.ar .main { margin-left:0; margin-right:var(--sidebar-w); }
body.ar .nav-badge { margin-left:0; margin-right:auto; }

/* SIDEBAR — deep purple, overrides vars locally */
.sidebar {
  --navy:#2e2a7a; --navy-mid:#3d3890; --navy-light:#4a4499; --navy-card:rgba(255,255,255,0.07);
  --white:#ffffff; --muted:rgba(255,255,255,0.62); --muted2:rgba(255,255,255,0.45);
  --border:rgba(255,255,255,0.1); --border2:rgba(255,255,255,0.06);
  width:var(--sidebar-w); background:var(--navy-mid); border-right:1px solid var(--border);
  display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:200; transition:transform .3s;
}
body.ar .sidebar { left:auto; right:0; border-right:none; border-left:1px solid var(--border); }
.sidebar-logo { display:flex; align-items:center; gap:.6rem; padding:1.4rem 1.3rem; border-bottom:1px solid var(--border); }
.sidebar-logo span { font-family:var(--font); font-weight:700; font-size:1rem; color:#fff; }
body.ar .sidebar-logo span { font-family:var(--font-ar); }
.sidebar-logo em { color:#f59e0b; font-style:normal; }
.lang-toggle { display:flex; gap:.4rem; padding:.55rem 1.3rem; border-bottom:1px solid var(--border); }
.lang-pill { font-size:.7rem; font-family:var(--font); font-weight:600; padding:.22rem .6rem; border-radius:100px; border:1px solid var(--border); color:var(--muted); cursor:pointer; transition:all .2s; }
.lang-pill.active { background:rgba(245,158,11,.2); border-color:rgba(245,158,11,.5); color:#f59e0b; }
.sidebar-user { padding:1.1rem 1.3rem; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:.75rem; }
body.ar .sidebar-user { flex-direction:row-reverse; }
.avatar { width:38px; height:38px; border-radius:50%; background:rgba(245,158,11,.18); border:2px solid rgba(245,158,11,.4); display:flex; align-items:center; justify-content:center; font-family:var(--font); font-weight:700; font-size:.85rem; color:#f59e0b; flex-shrink:0; }
.user-info .name { font-family:var(--font); font-size:.84rem; font-weight:600; line-height:1.2; color:#fff; }
body.ar .user-info .name { font-family:var(--font-ar); }
.user-info .role-tag { font-size:.7rem; color:#f59e0b; background:rgba(245,158,11,.15); padding:.1rem .5rem; border-radius:100px; margin-top:.2rem; display:inline-block; }
.sidebar-nav { flex:1; padding:.9rem .75rem; overflow-y:auto; }
.nav-item { display:flex; align-items:center; gap:.72rem; padding:.62rem .85rem; border-radius:10px; cursor:pointer; color:var(--muted); font-size:.87rem; font-family:var(--font); font-weight:500; transition:all .2s; margin-bottom:.15rem; border:1px solid transparent; }
body.ar .nav-item { flex-direction:row-reverse; font-family:var(--font-ar); }
.nav-item svg { flex-shrink:0; opacity:.7; }
.nav-item:hover { background:rgba(255,255,255,.08); color:#fff; }
.nav-item.active { background:rgba(245,158,11,.18); color:#f59e0b; border-color:rgba(245,158,11,.3); }
.nav-item.active svg { opacity:1; }
.nav-badge { margin-left:auto; background:#f59e0b; color:#1e1b4b; font-size:.62rem; font-weight:700; padding:.12rem .42rem; border-radius:100px; font-family:var(--font); }
.sidebar-bottom { padding:.9rem; border-top:1px solid var(--border); }
.btn-logout { display:flex; align-items:center; gap:.6rem; width:100%; padding:.62rem .85rem; border-radius:10px; background:transparent; border:1px solid var(--border); color:var(--muted); font-family:var(--font); font-size:.84rem; cursor:pointer; transition:all .2s; }
body.ar .btn-logout { flex-direction:row-reverse; font-family:var(--font-ar); }
.btn-logout:hover { border-color:#ef4444; color:#ef4444; background:rgba(239,68,68,.08); }

/* MAIN */
.main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; background:var(--navy); }
.topbar { background:rgba(214,238,255,0.92); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); padding:.9rem 2rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
body.ar .topbar { flex-direction:row-reverse; }
.topbar-title { font-family:var(--font); font-size:1rem; font-weight:600; color:var(--white); }
body.ar .topbar-title { font-family:var(--font-ar); }
.topbar-actions { display:flex; align-items:center; gap:.75rem; }
.btn-icon { width:36px; height:36px; border-radius:9px; background:rgba(30,27,75,.06); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--muted); transition:all .2s; position:relative; }
.btn-icon:hover { border-color:var(--blue); color:var(--blue); background:rgba(59,130,246,.08); }
.page { padding:2rem; display:none; animation:fadeIn .25s ease; }
.page.active { display:block; }
@keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

/* CARDS */
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
.grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:1.5rem; }
.grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; }
.card { background:var(--navy-card); border:1px solid var(--border); border-radius:16px; padding:1.5rem; transition:border-color .2s; box-shadow:0 2px 8px rgba(30,27,75,.06); }
.card:hover { border-color:rgba(59,130,246,.2); }
.card-title { font-family:var(--font); font-size:.8rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); margin-bottom:1rem; }
body.ar .card-title { font-family:var(--font-ar); letter-spacing:0; text-align:right; }

/* STAT CARDS */
.stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; margin-bottom:1rem; font-size:1.3rem; }
.stat-icon.green { background:rgba(16,185,129,.1); }
.stat-icon.yellow { background:rgba(245,158,11,.1); }
.stat-icon.red { background:rgba(239,68,68,.1); }
.stat-icon.blue { background:rgba(59,130,246,.1); }
.stat-value { font-family:var(--font); font-size:2rem; font-weight:700; letter-spacing:-.03em; margin-bottom:.25rem; color:var(--white); }
.stat-label { font-size:.83rem; color:var(--muted); }
body.ar .stat-label { text-align:right; font-family:var(--font-ar); }

/* PROGRESS */
.progress-bar { height:8px; background:rgba(30,27,75,.08); border-radius:100px; overflow:hidden; margin:.5rem 0; }
.progress-fill { height:100%; border-radius:100px; background:var(--green); transition:width .8s cubic-bezier(.4,0,.2,1); }
.progress-fill.yellow { background:var(--yellow); }
.module-row { display:flex; align-items:center; gap:1rem; padding:.75rem 0; border-bottom:1px solid var(--border2); }
body.ar .module-row { flex-direction:row-reverse; }
.module-row:last-child { border-bottom:none; }
.module-name { flex:1; font-size:.88rem; font-family:var(--font); font-weight:500; color:var(--white); }
body.ar .module-name { font-family:var(--font-ar); text-align:right; }
.module-pct { font-family:var(--font); font-size:.82rem; font-weight:600; color:var(--green); min-width:38px; text-align:right; }
.module-bar { flex:2; }

/* ACTIVITY */
.activity-item { display:flex; gap:1rem; padding:.9rem 0; border-bottom:1px solid var(--border2); }
body.ar .activity-item { flex-direction:row-reverse; }
.activity-item:last-child { border-bottom:none; }
.activity-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:.3rem; }
.activity-dot.green { background:var(--green); }
.activity-dot.yellow { background:var(--yellow); }
.activity-dot.blue { background:var(--blue); }
.activity-dot.red { background:var(--red); }
.activity-text { font-size:.86rem; color:var(--muted); line-height:1.5; }
body.ar .activity-text { text-align:right; font-family:var(--font-ar); }
.activity-text strong { color:var(--white); font-weight:500; }
.activity-time { font-size:.75rem; color:var(--muted2); margin-top:.2rem; }
body.ar .activity-time { text-align:right; }

/* HERO WELCOME */
.hero-section { text-align:center; padding:2.5rem 1rem 1.75rem; }
.mascot-wrap { display:inline-flex; align-items:center; justify-content:center; width:110px; height:110px; border-radius:50%; background:linear-gradient(135deg,#bfdbfe,#ddd6fe); margin-bottom:1.25rem; font-size:3.2rem; box-shadow:0 8px 32px rgba(59,130,246,.18); }
.hero-hello { font-family:var(--font); font-size:2.6rem; font-weight:800; letter-spacing:-.04em; margin-bottom:.4rem; background:linear-gradient(135deg,#3b82f6,#7c3aed); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
body.ar .hero-hello { font-family:var(--font-ar); letter-spacing:0; font-size:2.2rem; }
.hero-sub { color:var(--muted); font-size:1rem; }
body.ar .hero-sub { font-family:var(--font-ar); }

/* HOME CARDS */
.home-cards { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; max-width:860px; margin:0 auto; }

/* STARS OF MONTH */
.stars-card { background:linear-gradient(150deg,#2d2a6e,#3d3a9f); border-radius:20px; padding:1.6rem; color:#fff; box-shadow:0 8px 32px rgba(45,42,110,.28); }
.stars-header { display:flex; align-items:center; gap:.75rem; margin-bottom:1.25rem; }
.stars-crown { font-size:1.4rem; }
.stars-title { font-family:var(--font); font-size:1rem; font-weight:700; color:#fff; }
.stars-month { font-size:.73rem; color:rgba(255,255,255,.6); margin-top:.1rem; }
body.ar .stars-title, body.ar .stars-month { font-family:var(--font-ar); }
.star-item { display:flex; align-items:center; gap:.7rem; background:linear-gradient(135deg,#f59e0b,#f97316); border-radius:12px; padding:.8rem .95rem; margin-bottom:.6rem; }
.star-item:last-child { margin-bottom:0; }
.star-rank { font-size:.95rem; min-width:22px; text-align:center; }
.star-av { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.28); display:flex; align-items:center; justify-content:center; font-family:var(--font); font-weight:700; font-size:.8rem; color:#fff; flex-shrink:0; }
.star-name { flex:1; font-family:var(--font); font-weight:600; font-size:.88rem; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
body.ar .star-name { font-family:var(--font-ar); }
.star-pct { font-family:var(--font); font-size:.78rem; font-weight:700; color:rgba(255,255,255,.9); }
.stars-empty { text-align:center; padding:2rem 1rem; color:rgba(255,255,255,.55); font-size:.85rem; }

/* LEADERBOARD */
.leaderboard-card { background:linear-gradient(150deg,#f97316,#fbbf24); border-radius:20px; padding:1.6rem; color:#fff; box-shadow:0 8px 32px rgba(249,115,22,.22); }
.lb-header { display:flex; align-items:center; gap:.75rem; margin-bottom:1.25rem; }
.lb-trophy { font-size:1.4rem; }
.lb-title { font-family:var(--font); font-size:1rem; font-weight:700; color:#fff; }
.lb-sub { font-size:.73rem; color:rgba(255,255,255,.7); margin-top:.1rem; }
body.ar .lb-title, body.ar .lb-sub { font-family:var(--font-ar); }
.lb-item { display:flex; align-items:center; gap:.7rem; background:rgba(255,255,255,.22); border-radius:12px; padding:.7rem .95rem; margin-bottom:.55rem; }
.lb-item:last-child { margin-bottom:0; }
.lb-av { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.32); display:flex; align-items:center; justify-content:center; font-family:var(--font); font-weight:700; font-size:.78rem; color:#fff; flex-shrink:0; }
.lb-info { flex:1; min-width:0; }
.lb-name { font-family:var(--font); font-weight:600; font-size:.86rem; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
body.ar .lb-name { font-family:var(--font-ar); }
.lb-action { font-size:.73rem; color:rgba(255,255,255,.82); margin-top:.1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.lb-empty { text-align:center; padding:2rem 1rem; color:rgba(255,255,255,.6); font-size:.85rem; }

/* MY CLASS */
.classmate-item { display:flex; align-items:center; gap:.75rem; padding:.7rem 0; border-bottom:1px solid var(--border2); }
body.ar .classmate-item { flex-direction:row-reverse; }
.classmate-item:last-child { border-bottom:none; }
.cm-av { width:34px; height:34px; border-radius:50%; background:rgba(59,130,246,.1); border:2px solid rgba(59,130,246,.2); display:flex; align-items:center; justify-content:center; font-family:var(--font); font-weight:700; font-size:.78rem; color:var(--blue); flex-shrink:0; }
.cm-name { flex:1; font-family:var(--font); font-size:.87rem; font-weight:500; color:var(--white); }
body.ar .cm-name { font-family:var(--font-ar); text-align:right; }
.cm-pct { font-family:var(--font); font-size:.8rem; font-weight:600; color:var(--green); }

/* BADGE */
.badge { display:inline-flex; align-items:center; padding:.2rem .65rem; border-radius:100px; font-size:.72rem; font-weight:700; font-family:var(--font); flex-shrink:0; }
.badge.pending { background:rgba(245,158,11,.12); color:var(--yellow); border:1px solid rgba(245,158,11,.3); }
.badge.submitted { background:rgba(16,185,129,.12); color:var(--green); border:1px solid rgba(16,185,129,.3); }
.badge.overdue { background:rgba(239,68,68,.12); color:var(--red); border:1px solid rgba(239,68,68,.3); }

/* MODAL */
.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:none;align-items:flex-start;justify-content:center;padding:2rem 1rem;overflow-y:auto;pointer-events:none; }
.modal-overlay.open { display:flex;pointer-events:auto; }
.modal { background:#fff;border:1px solid var(--border);border-radius:20px;padding:2rem;max-width:520px;width:100%;margin:auto;animation:slideUp .25s ease;box-shadow:0 20px 60px rgba(30,27,75,.12); }
@keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
.modal-header { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;gap:1rem; }
.modal-header h3 { font-family:var(--font);font-size:1.1rem;font-weight:700;color:var(--white); }
.modal-footer { display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem; }
.btn-close { background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.3rem;line-height:1;transition:color .2s;padding:0; }
.btn-close:hover { color:var(--white); }
.btn-secondary { padding:.75rem 1.4rem;background:rgba(30,27,75,.06);border:1px solid var(--border);border-radius:10px;color:var(--muted);font-family:var(--font);font-size:.88rem;font-weight:600;cursor:pointer;transition:.2s; }
.btn-secondary:hover { color:var(--white);border-color:rgba(30,27,75,.25); }
.assign-item { border:1px solid var(--border); border-radius:14px; padding:1.2rem 1.4rem; margin-bottom:.75rem; transition:border-color .2s,background .2s; cursor:pointer; background:var(--navy-card); box-shadow:0 1px 4px rgba(30,27,75,.05); }
.assign-item:hover { border-color:rgba(59,130,246,.3); }
.assign-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:.5rem; }
body.ar .assign-header { flex-direction:row-reverse; }
.assign-title { font-family:var(--font); font-size:.95rem; font-weight:600; color:var(--white); }
body.ar .assign-title { font-family:var(--font-ar); text-align:right; }
.assign-desc { font-size:.83rem; color:var(--muted); margin-bottom:.6rem; line-height:1.5; }
body.ar .assign-desc { text-align:right; font-family:var(--font-ar); }
.assign-meta { display:flex; align-items:center; gap:1rem; font-size:.78rem; color:var(--muted2); }
body.ar .assign-meta { flex-direction:row-reverse; }

/* TABS */
.tabs { display:flex; gap:.5rem; margin-bottom:1.5rem; border-bottom:1px solid var(--border); padding-bottom:0; }
.tab { padding:.6rem 1rem; font-family:var(--font); font-size:.85rem; font-weight:500; color:var(--muted); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .2s; }
body.ar .tab { font-family:var(--font-ar); }
.tab.active { color:var(--blue); border-bottom-color:var(--blue); }
.tab:hover:not(.active) { color:var(--white); }

/* QUIZ */
.quiz-card { background:var(--navy-card); border:1px solid var(--border); border-radius:16px; padding:1.5rem; transition:all .2s; cursor:pointer; display:flex; flex-direction:column; box-shadow:0 2px 8px rgba(30,27,75,.06); }
.quiz-card:hover { border-color:rgba(59,130,246,.35); transform:translateY(-2px); box-shadow:0 8px 24px rgba(30,27,75,.1); }
.quiz-icon { font-size:2rem; margin-bottom:.8rem; }
.quiz-title { font-family:var(--font); font-size:1rem; font-weight:700; margin-bottom:.4rem; color:var(--white); }
body.ar .quiz-title { font-family:var(--font-ar); text-align:right; }
.quiz-desc { font-size:.83rem; color:var(--muted); margin-bottom:1rem; line-height:1.5; flex:1; }
body.ar .quiz-desc { text-align:right; font-family:var(--font-ar); }
.quiz-meta { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem; }
.quiz-meta span { font-size:.75rem; background:rgba(30,27,75,.06); border:1px solid var(--border); padding:.2rem .6rem; border-radius:100px; color:var(--muted); font-family:var(--font); }

/* BUTTONS */
.btn-primary { background:linear-gradient(135deg,#3b82f6,#7c3aed); color:#fff; font-family:var(--font); font-weight:700; font-size:.9rem; padding:.75rem 1.5rem; border:none; border-radius:10px; cursor:pointer; transition:opacity .2s,transform .15s; display:inline-flex; align-items:center; gap:.5rem; }
body.ar .btn-primary { font-family:var(--font-ar); }
.btn-primary:hover { opacity:.9; transform:translateY(-1px); }

/* TOAST */
.toast { position:fixed; bottom:2rem; right:2rem; background:#fff; border:1px solid var(--border); border-radius:12px; padding:.9rem 1.4rem; font-family:var(--font); font-size:.85rem; color:var(--white); z-index:9999; transform:translateY(100px); opacity:0; transition:all .3s; display:flex; align-items:center; gap:.6rem; box-shadow:0 8px 24px rgba(30,27,75,.12); }
body.ar .toast { right:auto; left:2rem; font-family:var(--font-ar); }
.toast.show { transform:translateY(0); opacity:1; }
.toast-dot { width:8px; height:8px; border-radius:50%; background:var(--green); }

.hamburger { display:none; width:36px; height:36px; border-radius:9px; background:rgba(30,27,75,.06); border:1px solid var(--border); align-items:center; justify-content:center; cursor:pointer; color:var(--muted); flex-shrink:0; }
.sidebar-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:199; pointer-events:none; }
.sidebar-backdrop.open { display:block; pointer-events:auto; }

@media(max-width:768px){
  .hamburger { display:flex; }
  .sidebar { transform:translateX(-100%); }
  body.ar .sidebar { transform:translateX(100%); }
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
.notif-panel { position:fixed; top:64px; right:1rem; width:320px; background:#fff; border:1px solid var(--border); border-radius:16px; box-shadow:0 8px 32px rgba(30,27,75,.14); z-index:9000; overflow:hidden; display:none; animation:fadeIn .15s ease; }
body.ar .notif-panel { right:auto; left:1rem; }
.notif-panel.open { display:block; }
.notif-panel-header { display:flex; align-items:center; justify-content:space-between; padding:.9rem 1.1rem; border-bottom:1px solid var(--border); }
.notif-panel-title { font-family:var(--font); font-size:.82rem; font-weight:700; color:var(--white); }
.notif-mark-all { font-family:var(--font); font-size:.72rem; color:var(--blue); cursor:pointer; background:none; border:none; padding:0; }
.notif-mark-all:hover { text-decoration:underline; }
.notif-list { max-height:320px; overflow-y:auto; }
.notif-item { display:flex; gap:.75rem; padding:.85rem 1.1rem; border-bottom:1px solid rgba(30,27,75,.05); cursor:pointer; transition:background .15s; align-items:flex-start; }
.notif-item:hover { background:rgba(30,27,75,.03); }
.notif-item.unread { background:rgba(59,130,246,.04); }
.notif-icon { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
.notif-icon.new_assignment { background:rgba(59,130,246,.1); }
.notif-icon.overdue { background:rgba(245,158,11,.1); }
.notif-icon.submission { background:rgba(16,185,129,.1); }
.notif-icon.info { background:rgba(30,27,75,.06); }
.notif-content { flex:1; min-width:0; }
.notif-title { font-family:var(--font); font-size:.8rem; font-weight:600; margin-bottom:.15rem; color:var(--white); }
.notif-body { font-size:.78rem; color:var(--muted); line-height:1.4; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:220px; }
.notif-time { font-size:.7rem; color:var(--muted2); margin-top:.2rem; }
.notif-unread-dot { width:7px; height:7px; border-radius:50%; background:var(--blue); flex-shrink:0; margin-top:6px; }
.notif-empty { padding:2rem; text-align:center; color:var(--muted); font-size:.85rem; }
.notif-badge { position:absolute; top:3px; right:3px; min-width:16px; height:16px; background:var(--red); color:#fff; font-size:.58rem; font-weight:700; border-radius:100px; display:flex; align-items:center; justify-content:center; font-family:var(--font); padding:0 3px; border:2px solid var(--navy); }

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
body.ar .howto-card-title { font-family:var(--font-ar); text-align:right; }
.howto-card-desc { font-size:.82rem; color:var(--muted); line-height:1.55; margin-bottom:1rem; }
body.ar .howto-card-desc { font-family:var(--font-ar); text-align:right; }
.howto-card-btn { display:inline-flex; align-items:center; gap:.4rem; font-size:.8rem; font-family:var(--font); font-weight:600; color:var(--blue); background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.2); padding:.38rem .9rem; border-radius:8px; cursor:pointer; transition:all .2s; }
.howto-card-btn:hover { background:rgba(59,130,246,.16); }

/* AVATAR UPLOAD */
.settings-av-wrap { position:relative; }
.av-upload-btn { position:absolute; bottom:-3px; right:-3px; width:24px; height:24px; background:#3b82f6; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#fff; border:2px solid var(--navy); transition:background .2s; }
.av-upload-btn:hover { background:#2563eb; }

</style>
</head>
<body id="body">
<a href="#main-content" class="skip-link" style="position:absolute;top:-40px;left:0;background:var(--green);color:#0f1d2e;padding:.5rem 1rem;font-family:var(--font);font-weight:700;font-size:.85rem;z-index:9999;border-radius:0 0 8px 0;transition:top .2s;text-decoration:none;">Aller au contenu</a>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Menu principal">
  <div class="sidebar-logo">
    <svg width="26" height="26" viewBox="0 0 28 28" fill="none"><rect width="28" height="28" rx="8" fill="#3ecf78"/><path d="M8 14l4 4 8-8" stroke="#0f1d2e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Up<em>skill</em></span>
  </div>
  <div class="lang-toggle">
    <div class="lang-pill active" id="pill-fr" onclick="setLang('fr')">🇫🇷 FR</div>
    <div class="lang-pill" id="pill-ar" onclick="setLang('ar')">🇲🇦 AR</div>
  </div>
  <div class="sidebar-user">
    <div class="avatar" id="sidebar-avatar"><?php $parts=explode(" ",trim($full_name));echo strtoupper(substr($parts[0],0,1).substr($parts[1]??$parts[0],0,1)); ?></div>
    <div class="user-info">
      <div class="name" id="sidebar-name"><?= $full_name ?></div>
      <div class="role-tag" id="role-label">Étudiante</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-item active" onclick="navigate('home',this)" id="nav-home">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span id="nav-home-lbl">Dashboard</span>
    </div>
    <div class="nav-item" onclick="navigate('myclass',this)" id="nav-myclass">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span id="nav-myclass-lbl">Ma classe</span>
    </div>
    <div class="nav-item" onclick="navigate('assignments',this)" id="nav-assign">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <span id="nav-assign-lbl">Devoirs</span>
      <span class="nav-badge" id="assign-nav-badge" style="display:none;"></span>
    </div>
    <div class="nav-item" onclick="navigate('whiteboard',this)" id="nav-wb">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
      <span id="nav-wb-lbl">Tableau blanc</span>
    </div>
    <div class="nav-item" onclick="navigate('quizzes',this)" id="nav-quiz">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
      <span id="nav-quiz-lbl">Challenge</span>
    </div>
    <div class="nav-item" onclick="navigate('howto',this)" id="nav-howto">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span id="nav-howto-lbl">How-to</span>
    </div>
    <div class="nav-item" onclick="navigate('settings',this)" id="nav-set">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span id="nav-set-lbl">Paramètres</span>
    </div>
  </nav>
  <div class="sidebar-bottom">
    <button class="btn-logout" onclick="logout()" aria-label="Se déconnecter">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span id="logout-lbl">Déconnexion</span>
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
      <div class="topbar-title" id="topbar-title">Tableau de bord</div>
    </div>
    <div class="topbar-actions">
      <div class="btn-icon" id="notif-btn" onclick="toggleNotifPanel()" title="Notifications" style="cursor:pointer;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="notif-badge" id="notif-badge" style="display:none;"></span>
      </div>
      <div class="avatar" id="topbar-avatar" style="cursor:default;"><?php $parts=explode(" ",trim($full_name));echo strtoupper(substr($parts[0],0,1).substr($parts[1]??$parts[0],0,1)); ?></div>
    </div>
  </div>

  <!-- HOME PAGE -->
  <div class="page active" id="page-home">
    <!-- Hero welcome -->
    <div class="hero-section">
      <div class="mascot-wrap" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 110 120" width="72" height="78">
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
      <p class="hero-sub" id="welcome-sub">Votre tableau de bord d'apprentissage</p>
    </div>

    <!-- Stars of the Month + Leaderboard -->
    <div class="home-cards">
      <div class="stars-card">
        <div class="stars-header">
          <div class="stars-crown">👑</div>
          <div>
            <div class="stars-title" id="stars-title">Étudiants du mois</div>
            <div class="stars-month" id="stars-month">Ce mois-ci</div>
          </div>
        </div>
        <div id="stars-list"><div class="stars-empty">Chargement…</div></div>
      </div>

      <div class="leaderboard-card">
        <div class="lb-header">
          <div class="lb-trophy">🏆</div>
          <div>
            <div class="lb-title" id="lb-title">Activité de la classe</div>
            <div class="lb-sub" id="lb-sub">Activité récente</div>
          </div>
        </div>
        <div id="leaderboard-list"><div class="lb-empty">Chargement…</div></div>
      </div>
    </div>
  </div>

  <!-- MY CLASS PAGE -->
  <div class="page" id="page-myclass">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;color:var(--white);" id="myclass-title">Ma classe</h2>
      <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="myclass-sub">Votre cours et vos camarades</p>
    </div>
    <div class="grid-2">
      <div class="card" style="background:linear-gradient(135deg,rgba(59,130,246,.08),rgba(124,58,237,.05));border-color:rgba(59,130,246,.2);">
        <div class="card-title" id="myclass-course-label">Cours actuel</div>
        <div id="myclass-assigned">
          <div style="font-family:var(--font);font-size:1.1rem;font-weight:700;color:var(--white);margin-bottom:.5rem;" id="myclass-course-name">—</div>
          <div style="color:var(--muted);font-size:.84rem;margin-bottom:.35rem;">👨‍🏫 <span id="myclass-teacher">—</span></div>
          <div style="color:var(--muted);font-size:.84rem;margin-bottom:1.2rem;">📅 <span id="myclass-schedule">—</span></div>
          <div style="display:flex;justify-content:space-between;align-items:center;font-size:.8rem;color:var(--muted);margin-bottom:.4rem;"><span id="myclass-att-lbl">Taux de présence</span><strong style="font-family:var(--font);color:var(--green);" id="myclass-att-pct">–</strong></div>
          <div class="progress-bar"><div class="progress-fill" id="myclass-att-bar" style="width:0%"></div></div>
          <div style="font-size:.77rem;color:var(--muted);margin-top:.5rem;" id="myclass-att-detail">—</div>
        </div>
        <div id="myclass-empty" style="display:none;text-align:center;padding:1.5rem 0;">
          <div style="font-size:2rem;margin-bottom:.6rem;">📚</div>
          <div style="font-family:var(--font);font-size:.9rem;font-weight:600;color:var(--white);" id="myclass-empty-txt">Aucun cours assigné</div>
        </div>
      </div>
      <div class="card">
        <div class="card-title" id="myclass-mates-lbl">Camarades</div>
        <div id="myclass-mates"><div style="text-align:center;padding:2rem;color:var(--muted);font-size:.85rem;">Chargement…</div></div>
      </div>
    </div>
  </div>

  <!-- ASSIGNMENTS PAGE -->
  <div class="page" id="page-assignments">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;letter-spacing:-.02em;" id="assign-page-title">Devoirs</h2>
        <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="assign-page-sub">3 en attente · 1 en retard · 2 soumis</p>
      </div>
    </div>
    <div class="tabs">
      <div class="tab active" onclick="filterTab(this,'all','assign')" id="tab-all-assign">Tous</div>
      <div class="tab" onclick="filterTab(this,'pending','assign')" id="tab-pending-assign">En attente</div>
      <div class="tab" onclick="filterTab(this,'submitted','assign')" id="tab-done-assign">Soumis</div>
    </div>
    <div id="assign-list"></div>
  </div>

  <!-- QUIZZES PAGE -->
  <div class="page" id="page-quizzes">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="quiz-page-title">Quiz</h2>
      <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="quiz-page-sub">Testez vos connaissances avec des quiz chronométrés</p>
    </div>
    <div class="tabs">
      <div class="tab active" onclick="filterTab(this,'all','quiz')" id="tab-all-quiz">Tous</div>
      <div class="tab" onclick="filterTab(this,'available','quiz')" id="tab-avail-quiz">Disponibles</div>
      <div class="tab" onclick="filterTab(this,'done','quiz')" id="tab-done-quiz">Complétés</div>
    </div>
    <div id="quiz-list" class="grid-3"></div>
  </div>

  <!-- WHITEBOARD PAGE -->
  <div class="page" id="page-whiteboard">
    <div class="coming-soon">
      <div class="cs-emoji">🖼️</div>
      <h3 id="wb-cs-title">Tableau blanc</h3>
      <p id="wb-cs-sub">Les notes de cours, captures de tableau et récapitulatifs de séance seront publiés ici par votre professeur après chaque cours Zoom.</p>
    </div>
  </div>

  <!-- HOW-TO PAGE -->
  <div class="page" id="page-howto">
    <div style="margin-bottom:1.75rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;color:var(--white);" id="howto-title">Comment utiliser la plateforme ?</h2>
      <p style="color:var(--muted);font-size:.88rem;margin-top:.3rem;" id="howto-sub">Regardez ces courtes vidéos pour découvrir chaque fonctionnalité.</p>
    </div>
    <div class="howto-grid" id="howto-grid">
      <!-- Cards injected by renderHowTo() -->
    </div>
  </div>

  <!-- PROGRESS PAGE -->
  <div class="page" id="page-progress">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="prog-page-title">Progression</h2>
      <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="prog-page-sub">Suivez votre parcours d'apprentissage module par module</p>
    </div>
    <div class="grid-2" style="margin-bottom:1.5rem;">
      <div class="card">
        <div class="card-title" id="overall-title">Progression globale</div>
        <div style="display:flex;align-items:center;gap:1.5rem;margin-bottom:1rem;">
          <div style="position:relative;width:90px;height:90px;flex-shrink:0;">
            <svg viewBox="0 0 90 90" width="90" height="90">
              <circle cx="45" cy="45" r="38" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="9"/>
              <circle id="prog-circle" cx="45" cy="45" r="38" fill="none" stroke="#3ecf78" stroke-width="9" stroke-linecap="round" stroke-dasharray="238.76" stroke-dashoffset="76.4" transform="rotate(-90 45 45)"/>
            </svg>
            <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
              <span style="font-family:var(--font);font-size:1.2rem;font-weight:700;" id="prog-circle-pct">68%</span>
              <span style="font-size:.6rem;color:var(--muted);" id="done-lbl">fait</span>
            </div>
          </div>
          <div>
            <div style="font-family:var(--font);font-size:1.5rem;font-weight:700;">20 <span style="font-size:1rem;color:var(--muted);font-weight:400;" id="hrs-of">/ 29 hrs</span></div>
            <div style="color:var(--muted);font-size:.83rem;margin:.3rem 0;" id="course-session">Anglais Général · Session 2</div>
            <div style="font-size:.78rem;background:var(--green-glow);color:var(--green);border:1px solid rgba(62,207,120,.3);padding:.25rem .65rem;border-radius:100px;display:inline-block;font-family:var(--font);font-weight:600;" id="on-track">En bonne voie 🎯</div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-title" id="scores-title">Scores des quiz</div>
        <div class="module-row"><div class="module-name" id="q-gram">Grammaire de base</div><div class="module-bar"><div class="progress-bar"><div class="progress-fill" style="width:85%"></div></div></div><div class="module-pct">85%</div></div>
        <div class="module-row"><div class="module-name" id="q-voc">Vocabulaire Unité 2</div><div class="module-bar"><div class="progress-bar"><div class="progress-fill" style="width:72%"></div></div></div><div class="module-pct">72%</div></div>
        <div class="module-row"><div class="module-name" id="q-list">Compréhension orale</div><div class="module-bar"><div class="progress-bar"><div class="progress-fill yellow" style="width:55%"></div></div></div><div class="module-pct" style="color:var(--yellow)">55%</div></div>
      </div>
    </div>
    <div class="card">
      <div class="card-title" id="modules-title">Progression par module</div>
      <div class="module-row"><div class="module-name" id="m1">Module 1 – Introduction</div><div class="module-bar"><div class="progress-bar"><div class="progress-fill" style="width:100%"></div></div></div><div class="module-pct">100%</div></div>
      <div class="module-row"><div class="module-name" id="m2">Module 2 – Grammaire</div><div class="module-bar"><div class="progress-bar"><div class="progress-fill" style="width:85%"></div></div></div><div class="module-pct">85%</div></div>
      <div class="module-row"><div class="module-name" id="m3">Module 3 – Vocabulaire</div><div class="module-bar"><div class="progress-bar"><div class="progress-fill" style="width:70%"></div></div></div><div class="module-pct">70%</div></div>
      <div class="module-row"><div class="module-name" id="m4">Module 4 – Expression orale</div><div class="module-bar"><div class="progress-bar"><div class="progress-fill yellow" style="width:40%"></div></div></div><div class="module-pct" style="color:var(--yellow)">40%</div></div>
      <div class="module-row"><div class="module-name" id="m5">Module 5 – Compréhension</div><div class="module-bar"><div class="progress-bar"><div class="progress-fill" style="width:20%"></div></div></div><div class="module-pct">20%</div></div>
    </div>
  </div>

  <!-- SETTINGS PAGE -->
  <div class="page" id="page-settings">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="settings-title">Paramètres</h2>
    </div>
    <div class="grid-2">
      <div class="card">
        <div class="card-title" id="profile-title">Profil</div>

        <!-- Avatar upload row -->
        <div style="display:flex;align-items:center;gap:1.2rem;margin-bottom:1.75rem;">
          <div style="position:relative;flex-shrink:0;">
            <div class="settings-av-wrap" id="settings-av-wrap">
              <div class="avatar" style="width:64px;height:64px;font-size:1.3rem;" id="settings-avatar"><?php $parts=explode(" ",trim($full_name));echo strtoupper(substr($parts[0],0,1).substr($parts[1]??$parts[0],0,1)); ?></div>
              <img id="settings-av-img" src="" alt="" style="display:none;width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid rgba(245,158,11,.4);">
            </div>
            <label for="avatar-input" class="av-upload-btn" title="Changer la photo">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            </label>
            <input type="file" id="avatar-input" accept="image/*" style="display:none;" onchange="handleAvatarUpload(this)">
          </div>
          <div>
            <div style="font-family:var(--font);font-weight:600;font-size:.95rem;color:var(--white);" id="settings-name"><?= $full_name ?></div>
            <div style="color:var(--muted);font-size:.82rem;margin-top:.2rem;" id="settings-role">Étudiante · Anglais Général S2</div>
            <label for="avatar-input" style="display:inline-block;margin-top:.5rem;font-size:.75rem;color:var(--blue);cursor:pointer;font-family:var(--font);font-weight:500;" id="lbl-change-photo">Changer la photo</label>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:1.1rem;">
          <label style="display:block;font-family:var(--font);font-size:.73rem;font-weight:600;color:var(--muted);letter-spacing:.07em;text-transform:uppercase;margin-bottom:.45rem;" id="lbl-fullname">Nom complet</label>
          <input type="text" id="pref-name" style="width:100%;padding:.8rem 1rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;transition:border-color .2s;"
            onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--border)'"
            value="<?= htmlspecialchars($full_name) ?>">
        </div>
        <div id="save-profile-error" style="display:none;color:var(--red);font-size:.82rem;margin-bottom:.6rem;"></div>
        <button class="btn-primary" onclick="saveProfile()" id="save-btn">
          <span id="save-btn-text">Enregistrer</span>
        </button>
      </div>
      <div class="card">
        <div class="card-title" id="pref-title">Préférences</div>
        <p style="color:var(--muted);font-size:.85rem;line-height:1.6;" id="pref-txt">Utilisez le sélecteur de langue dans la barre latérale pour basculer entre le Français et l'Arabe.</p>
      </div>
    </div>
  </div>
</main>

<!-- TOAST -->
<div class="toast" id="toast"><div class="toast-dot"></div><span id="toast-msg"></span></div>

<!-- SUBMIT MODAL -->
<div class="modal-overlay" id="modal-submit" role="dialog" aria-modal="true" aria-labelledby="submit-modal-title">
  <div class="modal">
    <div class="modal-header">
      <h3 id="submit-modal-title">Soumettre le devoir</h3>
      <button class="btn-close" onclick="closeSubmitModal()" aria-label="Fermer">✕</button>
    </div>
    <div class="modal-body">
      <div id="submit-assign-info" style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:10px;padding:.9rem 1rem;margin-bottom:1.25rem;">
        <div style="font-family:var(--font);font-weight:600;font-size:.9rem;" id="submit-assign-title">—</div>
        <div style="color:var(--muted);font-size:.78rem;margin-top:.3rem;" id="submit-assign-due">—</div>
      </div>
      <div class="form-group">
        <label for="submit-comment" style="display:block;font-family:var(--font);font-size:.73rem;font-weight:600;color:var(--muted);letter-spacing:.07em;text-transform:uppercase;margin-bottom:.45rem;" id="submit-comment-lbl">Commentaire (optionnel)</label>
        <textarea id="submit-comment" maxlength="2000" rows="5"
          placeholder="Décrivez votre travail, ajoutez des notes pour le professeur…"
          style="width:100%;padding:.85rem 1rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.88rem;outline:none;resize:vertical;transition:border-color .2s;"
          onfocus="this.style.borderColor='var(--green)'" onblur="this.style.borderColor='var(--border)'"></textarea>
        <div style="text-align:right;font-size:.73rem;color:var(--muted);margin-top:.3rem;"><span id="submit-char-count">0</span>/2000</div>
      </div>
      <div id="submit-error" style="display:none;color:#f87171;font-size:.85rem;margin-bottom:.5rem;"></div>
      <input type="hidden" id="submit-assign-id">
    </div>
    <div class="modal-footer" style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem;">
      <button class="btn-secondary" onclick="closeSubmitModal()" id="submit-cancel-btn">Annuler</button>
      <button class="btn-primary" onclick="confirmSubmit()" id="submit-confirm-btn">
        <span id="submit-btn-text">Soumettre →</span>
      </button>
    </div>
  </div>
</div>

<script>
/* ── LIVE DATA FROM PHP ── */
const LIVE      = <?= $jsData ?>;
const STARS     = <?= $jsStars ?>;
const CLASS_ACT = <?= $jsClassAct ?>;
const HAS_EMAIL = <?= json_encode(!empty($studentEmail)) ?>;

/* ── HTML escape helper ── */
function e(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }

/* ── Null-safe text setter ── */
function st(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }

/* ── DATA ── */
// Assignments come from LIVE.assignments (PHP → DB)
// QUIZZES stays static until quiz table is built
const QUIZZES = [
  { id:1, title_fr:'Quiz Grammaire Unité 1', title_ar:'اختبار القواعد – الوحدة 1', desc_fr:'Testez vos connaissances en grammaire de base.', desc_ar:'اختبر معرفتك في القواعد الأساسية.', qs:15, min:20, status:'available' },
  { id:2, title_fr:'Vocabulaire Unité 3', title_ar:'المفردات – الوحدة 3', desc_fr:'50 mots essentiels du quotidien.', desc_ar:'50 كلمة أساسية يومية.', qs:20, min:25, status:'available' },
  { id:3, title_fr:'Compréhension écrite #2', title_ar:'الفهم القرائي #2', desc_fr:'Quiz basé sur un texte.', desc_ar:'اختبار مبني على نص.', qs:10, min:15, status:'done', score:72 },
  { id:4, title_fr:'Grammaire – Temps passés', title_ar:'القواعد – الأزمنة الماضية', desc_fr:'Maîtriser le passé simple et composé.', desc_ar:'إتقان الأزمنة الماضية.', qs:12, min:18, status:'done', score:85 },
];

let currentLang = 'fr';
let currentAssignFilter = 'all';
let currentQuizFilter = 'all';
let activePage = 'home';

/* ── TRANSLATIONS ── */
const T = {
  fr: {
    topbarTitle: { home:'Tableau de bord', myclass:'Ma classe', assignments:'Devoirs', whiteboard:'Tableau blanc', quizzes:'Challenge', progress:'Progression', howto:'How-to', settings:'Paramètres' },
    navHome:'Dashboard', navMyclass:'Ma classe', navAssign:'Devoirs', navWb:'Tableau blanc', navQuiz:'Challenge', navHowto:'How-to', navSet:'Paramètres',
    roleLabel:'Étudiant(e)', logout:'Déconnexion',
    welcomeSub:'Votre tableau de bord d\'apprentissage',
    starsTitle:'Étudiants du mois', starsMonth:'Ce mois-ci', starsEmpty:'Pas encore de classement ce mois-ci.',
    lbTitle:'Activité de la classe', lbSub:'Activité récente', lbEmpty:'Aucune activité récente dans la classe.',
    lbSubmitted:'a rendu un devoir',
    myclassTitle:'Ma classe', myclassSub:'Votre cours et vos camarades',
    myclassCourseLbl:'Cours actuel', myclassMatesLbl:'Camarades',
    myclassTeacher:'Professeur :', myclassSchedule:'Horaire :',
    myclassAttLbl:'Taux de présence', myclassEmptyTxt:'Aucun cours assigné',
    myclassNoMates:'Aucun camarade trouvé.',
    wbTitle:'Tableau blanc', wbSub:'Les notes de cours, captures de tableau et récapitulatifs de séance seront publiés ici par votre professeur après chaque cours Zoom.',
    assignPageTitle:'Devoirs', assignPageSub:'3 en attente · 1 en retard · 2 soumis',
    tabAll:'Tous', tabPending:'En attente', tabDone:'Soumis',
    tabAllQ:'Tous', tabAvailQ:'Disponibles', tabDoneQ:'Complétés',
    quizPageTitle:'Quiz', quizPageSub:'Testez vos connaissances avec des quiz chronométrés',
    progPageTitle:'Progression', progPageSub:'Suivez votre parcours module par module',
    overallTitle:'Progression globale', doneLbl:'fait', hrsOf:'/ 29 hrs', courseSession:'Anglais Général · Session 2', onTrack:'En bonne voie 🎯',
    scoresTitle:'Scores des quiz', qGram:'Grammaire de base', qVoc:'Vocabulaire Unité 2', qList:'Compréhension orale',
    modulesTitle:'Progression par module', m1:'Module 1 – Introduction', m2:'Module 2 – Grammaire', m3:'Module 3 – Vocabulaire', m4:'Module 4 – Expression orale', m5:'Module 5 – Compréhension',
    howtoTitle:'Comment utiliser la plateforme ?', howtoSub:'Regardez ces courtes vidéos pour découvrir chaque fonctionnalité.',
    howtoCards:[
      { icon:'🏠', from:'#3b82f6', to:'#7c3aed', title:'Tableau de bord', desc:'Découvrez le leaderboard, les étudiants du mois et la vue d\'accueil.', btn:'Voir la vidéo' },
      { icon:'📋', from:'#10b981', to:'#059669', title:'Vos devoirs', desc:'Comment consulter, soumettre et suivre l\'état de vos devoirs.', btn:'Voir la vidéo' },
      { icon:'👥', from:'#f59e0b', to:'#f97316', title:'Ma classe', desc:'Voir votre cours, votre taux de présence et vos camarades.', btn:'Voir la vidéo' },
      { icon:'🏆', from:'#8b5cf6', to:'#6d28d9', title:'Challenge', desc:'Testez vos connaissances avec les quiz chronométrés.', btn:'Voir la vidéo' },
      { icon:'🖼️', from:'#ec4899', to:'#db2777', title:'Tableau blanc', desc:'Retrouvez ici les notes et captures de cours publiées par votre prof.', btn:'Voir la vidéo' },
      { icon:'⚙️', from:'#64748b', to:'#475569', title:'Paramètres', desc:'Changez votre nom, ajoutez une photo de profil et choisissez la langue.', btn:'Voir la vidéo' },
    ],
    settingsTitle:'Paramètres', profileTitle:'Profil', lblChangePhoto:'Changer la photo', settingsRole:'Étudiante · Anglais Général S2', lblFullname:'Nom complet', saveBtn:'Enregistrer', prefTitle:'Préférences', prefTxt:'Utilisez le sélecteur de langue dans la barre latérale pour basculer entre le Français et l\'Arabe.',
    badgePending:'En attente', badgeSubmitted:'Soumis', badgeOverdue:'En retard',
    startQuiz:'Commencer le quiz', retakeQuiz:'Refaire', doneLabel:'Complété',
    toastSaved:'Profil mis à jour !',
    qsLabel:'questions', minLabel:'min',
    dueLbl:'Échéance :', subjectLbl:'Matière :',
  },
  ar: {
    topbarTitle: { home:'لوحة التحكم', myclass:'صفي', assignments:'الواجبات', whiteboard:'السبورة', quizzes:'تحدي', progress:'التقدم', howto:'المساعدة', settings:'الإعدادات' },
    navHome:'الرئيسية', navMyclass:'صفي', navAssign:'الواجبات', navWb:'السبورة', navQuiz:'تحدي', navHowto:'المساعدة', navSet:'الإعدادات',
    roleLabel:'طالب/ة', logout:'تسجيل الخروج',
    welcomeSub:'لوحة تحكم التعلم الخاصة بك',
    starsTitle:'طلاب الشهر', starsMonth:'هذا الشهر', starsEmpty:'لا يوجد تصنيف هذا الشهر بعد.',
    lbTitle:'نشاط الصف', lbSub:'النشاط الأخير', lbEmpty:'لا يوجد نشاط حديث في الصف.',
    lbSubmitted:'سلّم واجباً',
    myclassTitle:'صفي', myclassSub:'دورتك وزملاؤك',
    myclassCourseLbl:'الدورة الحالية', myclassMatesLbl:'الزملاء',
    myclassTeacher:'الأستاذ:', myclassSchedule:'الجدول:',
    myclassAttLbl:'نسبة الحضور', myclassEmptyTxt:'لم يتم تعيين دورة بعد',
    myclassNoMates:'لا يوجد زملاء.',
    wbTitle:'السبورة', wbSub:'ستُنشر هنا ملاحظات الدروس والتلخيصات من قِبَل أستاذك بعد كل حصة Zoom.',
    assignPageTitle:'الواجبات', assignPageSub:'3 معلقة · 1 متأخرة · 2 مُسلَّمة',
    tabAll:'الكل', tabPending:'معلقة', tabDone:'مُسلَّمة',
    tabAllQ:'الكل', tabAvailQ:'متاحة', tabDoneQ:'مكتملة',
    quizPageTitle:'الاختبارات', quizPageSub:'اختبر معلوماتك باختبارات موقوتة',
    progPageTitle:'التقدم', progPageSub:'تابع مسيرتك التعلمية وحدة بوحدة',
    overallTitle:'التقدم الإجمالي', doneLbl:'منجز', hrsOf:'/ 29 ساعة', courseSession:'الإنجليزية العامة · جلسة 2', onTrack:'على المسار الصحيح 🎯',
    scoresTitle:'نتائج الاختبارات', qGram:'القواعد الأساسية', qVoc:'المفردات الوحدة 2', qList:'الفهم الشفهي',
    modulesTitle:'التقدم حسب الوحدة', m1:'الوحدة 1 – مقدمة', m2:'الوحدة 2 – القواعد', m3:'الوحدة 3 – المفردات', m4:'الوحدة 4 – التعبير الشفهي', m5:'الوحدة 5 – الفهم',
    howtoTitle:'كيف تستخدم المنصة؟', howtoSub:'شاهد هذه الفيديوهات القصيرة لاكتشاف كل ميزة.',
    howtoCards:[
      { icon:'🏠', from:'#3b82f6', to:'#7c3aed', title:'لوحة التحكم', desc:'اكتشف لوحة المتصدرين ونجوم الشهر وصفحة الرئيسية.', btn:'شاهد الفيديو' },
      { icon:'📋', from:'#10b981', to:'#059669', title:'الواجبات', desc:'كيف تطلع على الواجبات وتُسلّمها وتتابع حالتها.', btn:'شاهد الفيديو' },
      { icon:'👥', from:'#f59e0b', to:'#f97316', title:'صفي', desc:'اطلع على دورتك ونسبة حضورك وزملائك.', btn:'شاهد الفيديو' },
      { icon:'🏆', from:'#8b5cf6', to:'#6d28d9', title:'التحدي', desc:'اختبر معلوماتك باختبارات موقوتة.', btn:'شاهد الفيديو' },
      { icon:'🖼️', from:'#ec4899', to:'#db2777', title:'السبورة', desc:'هنا تجد ملاحظات وصور الدروس التي ينشرها أستاذك.', btn:'شاهد الفيديو' },
      { icon:'⚙️', from:'#64748b', to:'#475569', title:'الإعدادات', desc:'غيّر اسمك وأضف صورة شخصية واختر اللغة.', btn:'شاهد الفيديو' },
    ],
    settingsTitle:'الإعدادات', profileTitle:'الملف الشخصي', lblChangePhoto:'تغيير الصورة', settingsRole:'طالبة · الإنجليزية العامة ج2', lblFullname:'الاسم الكامل', saveBtn:'حفظ', prefTitle:'التفضيلات', prefTxt:'استخدم محدد اللغة في الشريط الجانبي للتبديل بين الفرنسية والعربية.',
    badgePending:'معلق', badgeSubmitted:'مُسلَّم', badgeOverdue:'متأخر',
    startQuiz:'ابدأ الاختبار', retakeQuiz:'أعد المحاولة', doneLabel:'مكتمل',
    toastSaved:'تم تحديث الملف الشخصي!',
    qsLabel:'سؤال', minLabel:'دقيقة',
    dueLbl:'الموعد:', subjectLbl:'المادة:',
  }
};

function t(key) { return T[currentLang][key] || key; }

function setLang(lang) {
  currentLang = lang;
  sessionStorage.setItem('upskill_lang', lang);
  document.getElementById('body').className = lang === 'ar' ? 'ar' : '';
  document.documentElement.setAttribute('lang', lang);
  document.documentElement.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
  document.getElementById('pill-fr').className = 'lang-pill' + (lang === 'fr' ? ' active' : '');
  document.getElementById('pill-ar').className = 'lang-pill' + (lang === 'ar' ? ' active' : '');
  applyTranslations();
  updateEmailPopupLang();
  renderStars();
  renderLeaderboard();
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
  st('nav-wb-lbl', tr.navWb);
  st('nav-quiz-lbl', tr.navQuiz);
  st('nav-howto-lbl', tr.navHowto);
  st('nav-set-lbl', tr.navSet);
  st('role-label', tr.roleLabel);
  st('logout-lbl', tr.logout);
  // Home page
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
  // Whiteboard page
  st('wb-cs-title', tr.wbTitle);
  st('wb-cs-sub', tr.wbSub);










  document.getElementById('assign-page-title').textContent = tr.assignPageTitle;
  document.getElementById('assign-page-sub').textContent = tr.assignPageSub;
  document.getElementById('tab-all-assign').textContent = tr.tabAll;
  document.getElementById('tab-pending-assign').textContent = tr.tabPending;
  document.getElementById('tab-done-assign').textContent = tr.tabDone;
  document.getElementById('quiz-page-title').textContent = tr.quizPageTitle;
  document.getElementById('quiz-page-sub').textContent = tr.quizPageSub;
  document.getElementById('tab-all-quiz').textContent = tr.tabAllQ;
  document.getElementById('tab-avail-quiz').textContent = tr.tabAvailQ;
  document.getElementById('tab-done-quiz').textContent = tr.tabDoneQ;
  document.getElementById('prog-page-title').textContent = tr.progPageTitle;
  document.getElementById('prog-page-sub').textContent = tr.progPageSub;
  document.getElementById('overall-title').textContent = tr.overallTitle;
  document.getElementById('done-lbl').textContent = tr.doneLbl;
  document.getElementById('hrs-of').textContent = tr.hrsOf;
  document.getElementById('course-session').textContent = tr.courseSession;
  document.getElementById('on-track').textContent = tr.onTrack;
  document.getElementById('scores-title').textContent = tr.scoresTitle;
  document.getElementById('q-gram').textContent = tr.qGram;
  document.getElementById('q-voc').textContent = tr.qVoc;
  document.getElementById('q-list').textContent = tr.qList;
  document.getElementById('modules-title').textContent = tr.modulesTitle;
  document.getElementById('m1').textContent = tr.m1;
  document.getElementById('m2').textContent = tr.m2;
  document.getElementById('m3').textContent = tr.m3;
  document.getElementById('m4').textContent = tr.m4;
  document.getElementById('m5').textContent = tr.m5;
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
  renderAssignments();
  renderQuizzes();
  renderStars();
  renderLeaderboard();
}

function navigate(page, el) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const pg = document.getElementById('page-' + page);
  if (pg) pg.classList.add('active');
  if (el) el.classList.add('active');
  activePage = page;
  st('topbar-title', T[currentLang].topbarTitle[page] || T[currentLang].topbarTitle.home);
  if (page === 'assignments') renderAssignments();
  if (page === 'quizzes')     renderQuizzes();
  if (page === 'myclass')     renderMyClass();
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
    const title = lang === 'ar' ? (item.title_ar || item.title_fr) : (item.title_fr || item.title_ar);
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
function renderMyClass() {
  const tr   = T[currentLang];
  const lang = currentLang;
  const c    = LIVE.course;
  const assignedEl = document.getElementById('myclass-assigned');
  const emptyEl    = document.getElementById('myclass-empty');

  if (c) {
    if (assignedEl) assignedEl.style.display = '';
    if (emptyEl)    emptyEl.style.display    = 'none';
    const name = lang === 'ar' ? (c.group_name_ar || c.group_name_fr) : (c.group_name_fr || c.group_name_ar);
    st('myclass-course-name', name || '—');
    st('myclass-teacher', (c.teacher_name ? ((lang === 'ar' ? 'أ. ' : 'Prof. ') + c.teacher_name) : '—'));
    if (c.schedule_json) {
      try {
        const sched = JSON.parse(c.schedule_json);
        if (Array.isArray(sched) && sched.length) {
          st('myclass-schedule', sched.map(s => lang === 'ar' ? (s.day_ar || s.day_fr) : (s.day_fr || s.day_ar)).join(' – '));
        }
      } catch(e) {}
    }
    const rate = LIVE.att_rate;
    if (rate !== null) {
      st('myclass-att-pct', rate + '%');
      const bar = document.getElementById('myclass-att-bar');
      if (bar) bar.style.width = rate + '%';
      const det = document.getElementById('myclass-att-detail');
      if (det) det.textContent = lang === 'ar'
        ? `${LIVE.att_present} / ${LIVE.att_total} جلسة`
        : `${LIVE.att_present} / ${LIVE.att_total} séances`;
    }
  } else {
    if (assignedEl) assignedEl.style.display = 'none';
    if (emptyEl)    emptyEl.style.display    = '';
  }

  // Render classmates from STARS data (same course)
  const matesEl = document.getElementById('myclass-mates');
  if (matesEl) {
    if (!STARS || STARS.length === 0) {
      matesEl.innerHTML = `<div style="text-align:center;padding:2rem;color:var(--muted);font-size:.85rem;">${e(tr.myclassNoMates)}</div>`;
    } else {
      matesEl.innerHTML = STARS.map(s => {
        const parts = (s.full_name || '').trim().split(/\s+/);
        const init  = ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '?';
        const total = parseInt(s.total_sessions) || 0;
        const pres  = parseInt(s.present_sessions) || 0;
        const pct   = total > 0 ? Math.round(pres / total * 100) + '%' : '–';
        return `<div class="classmate-item">
          <div class="cm-av">${e(init)}</div>
          <div class="cm-name">${e(s.full_name)}</div>
          <div class="cm-pct">${pct}</div>
        </div>`;
      }).join('');
    }
  }
}

function renderAssignments() {
  const list = document.getElementById('assign-list');
  if (!list) return;
  const tr = T[currentLang];
  const rows = LIVE.assignments || [];

  if (rows.length === 0) {
    list.innerHTML = `<div style="text-align:center;padding:3rem 1rem;color:var(--muted);">
      <div style="font-size:2rem;margin-bottom:.75rem;">📭</div>
      <div style="font-family:var(--font);font-size:.95rem;">${currentLang === 'ar' ? 'لا توجد واجبات حتى الآن' : 'Aucun devoir pour le moment'}</div>
    </div>`;
    return;
  }

  const items = rows.filter(a => {
    if (currentAssignFilter === 'all') return true;
    if (currentAssignFilter === 'pending') return a.status === 'pending' || a.status === 'overdue';
    if (currentAssignFilter === 'submitted') return a.status === 'submitted';
    return true;
  });

  const badgeMap = {
    pending:   `<span class="badge pending">${tr.badgePending}</span>`,
    submitted: `<span class="badge submitted">${tr.badgeSubmitted}</span>`,
    overdue:   `<span class="badge overdue">${tr.badgeOverdue}</span>`
  };

  list.innerHTML = items.length === 0
    ? `<div style="text-align:center;padding:2rem;color:var(--muted);font-size:.88rem;">${currentLang === 'ar' ? 'لا توجد نتائج' : 'Aucun résultat'}</div>`
    : items.map(a => {
        const title   = e(currentLang === 'ar' ? (a.title_ar || a.title_fr) : (a.title_fr || a.title_ar));
        const desc    = e(currentLang === 'ar' ? (a.description_ar || a.description_fr || '') : (a.description_fr || a.description_ar || ''));
        const subject = e(currentLang === 'ar' ? (a.subject_ar || a.subject_fr) : (a.subject_fr || a.subject_ar));
        const due     = e(a.due_fmt || (a.due_date ? a.due_date : '—'));
        const isAr    = currentLang === 'ar';

        // Action button based on status
        let actionBtn = '';
        if (a.status === 'pending' || a.status === 'overdue') {
          actionBtn = `<button class="btn-primary" style="margin-top:1rem;padding:.6rem 1.2rem;font-size:.83rem;"
            onclick="openSubmitModal(${a.id},'${title.replace(/'/g,"\\'")}','${due.replace(/'/g,"\\'")}')">
            ${isAr ? '← تسليم الواجب' : 'Soumettre →'}
          </button>`;
        } else if (a.status === 'submitted') {
          actionBtn = `<div style="display:flex;align-items:center;gap:.75rem;margin-top:1rem;flex-wrap:wrap;">
            <div style="font-size:.8rem;color:var(--green);display:flex;align-items:center;gap:.35rem;">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              ${isAr ? 'تم التسليم' : 'Soumis'}
              ${a.submitted_at ? `<span style="color:var(--muted);font-weight:400;">· ${formatRelativeTime(a.submitted_at, currentLang)}</span>` : ''}
            </div>
            <button onclick="retractSubmission(${a.id})"
              style="font-size:.75rem;padding:.3rem .7rem;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;font-family:var(--font);"
              onmouseenter="this.style.color='var(--white)'" onmouseleave="this.style.color='var(--muted)'"
              title="${isAr ? 'سحب التسليم' : 'Rétracter la soumission'}">
              ${isAr ? 'سحب' : 'Rétracter'}
            </button>
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
      feed.innerHTML = `<div style="color:var(--muted);font-size:.85rem;padding:1rem 0;text-align:center;">${lang === 'ar' ? 'لا يوجد نشاط حتى الآن' : 'Aucune activité pour le moment'}</div>`;
      return;
    }
    feed.innerHTML = fallback.map(a => {
      const color = a.status === 'overdue' ? 'red' : a.status === 'submitted' ? 'green' : 'yellow';
      const label = a.status === 'overdue'
        ? (lang === 'ar' ? 'واجب متأخر' : 'Devoir en retard')
        : a.status === 'submitted'
          ? (lang === 'ar' ? 'واجب مُسلَّم' : 'Devoir soumis')
          : (lang === 'ar' ? 'واجب معلق' : 'Devoir en attente');
      const title = e(lang === 'ar' ? (a.title_ar || a.title_fr) : (a.title_fr || a.title_ar));
      const due   = a.due_fmt ? `${lang === 'ar' ? 'الموعد: ' : 'Échéance : '}${e(a.due_fmt)}` : '';
      return `<div class="activity-item">
        <div class="activity-dot ${color}"></div>
        <div><div class="activity-text"><strong>${label}</strong> — <span>${title}</span></div>
        ${due ? `<div class="activity-time">${due}</div>` : ''}</div>
      </div>`;
    }).join('');
    return;
  }

  feed.innerHTML = items.map(item => {
    const label  = e(lang === 'ar' ? item.label_ar  : item.label_fr);
    const detail = e(lang === 'ar' ? item.detail_ar : item.detail_fr);
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
  if (diff < 60)   return lang === 'ar' ? 'الآن' : "À l'instant";
  if (diff < 3600) return lang === 'ar' ? `منذ ${Math.floor(diff/60)} د` : `Il y a ${Math.floor(diff/60)} min`;
  if (diff < 86400)return lang === 'ar' ? `منذ ${Math.floor(diff/3600)} س` : `Il y a ${Math.floor(diff/3600)} h`;
  const days = Math.floor(diff/86400);
  if (days === 1)  return lang === 'ar' ? 'أمس' : 'Hier';
  return lang === 'ar' ? `منذ ${days} أيام` : `Il y a ${days} jours`;
}

function renderQuizzes() {
  const list = document.getElementById('quiz-list');
  if (!list) return;
  const tr = T[currentLang];
  const items = QUIZZES.filter(q => {
    if (currentQuizFilter === 'all') return true;
    if (currentQuizFilter === 'available') return q.status === 'available';
    if (currentQuizFilter === 'done') return q.status === 'done';
    return true;
  });
  list.innerHTML = items.map(q => `
    <div class="quiz-card">
      <div class="quiz-icon">${q.status === 'done' ? '✅' : '🧠'}</div>
      <div class="quiz-title">${currentLang === 'ar' ? q.title_ar : q.title_fr}</div>
      <div class="quiz-desc">${currentLang === 'ar' ? q.desc_ar : q.desc_fr}</div>
      <div class="quiz-meta">
        <span>📝 ${q.qs} ${tr.qsLabel}</span>
        <span>⏱ ${q.min} ${tr.minLabel}</span>
      </div>
      ${q.status === 'done'
        ? `<div style="display:flex;align-items:center;gap:.5rem;margin-top:auto;"><div style="width:44px;height:44px;border-radius:50%;border:3px solid var(--green);display:flex;align-items:center;justify-content:center;font-family:var(--font);font-size:.75rem;font-weight:700;color:var(--green)">${q.score}%</div><span style="font-size:.83rem;color:var(--muted)">${tr.doneLabel}</span></div>`
        : `<button class="btn-primary" style="margin-top:auto;" onclick="showToast('${tr.startQuiz} – ${currentLang === 'ar' ? q.title_ar : q.title_fr}')">${tr.startQuiz}</button>`
      }
    </div>
  `).join('');
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
    if (!data.ok) throw new Error(data.error || 'Erreur serveur');

    // Update all name displays
    const parts = name.trim().split(/\s+/);
    const init  = ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '?';
    // Only update initials in places that show initials (not where photo is shown)
    ['sidebar-avatar','topbar-avatar','settings-avatar'].forEach(id => {
      const el = document.getElementById(id);
      if (el && el.tagName !== 'IMG' && el.style.display !== 'none') el.textContent = init;
    });
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

function handleAvatarUpload(input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 2 * 1024 * 1024) {
    showToast(currentLang === 'ar' ? 'الصورة أكبر من 2 ميغا' : 'Image trop grande (max 2 Mo)');
    return;
  }
  const reader = new FileReader();
  reader.onload = function(e) {
    const dataUrl = e.target.result;
    // Show in settings
    const img = document.getElementById('settings-av-img');
    const initDiv = document.getElementById('settings-avatar');
    if (img)     { img.src = dataUrl; img.style.display = ''; }
    if (initDiv) initDiv.style.display = 'none';
    // Show in sidebar
    applyAvatarToSidebar(dataUrl);
    // Show in topbar
    applyAvatarToTopbar(dataUrl);
    // Persist in localStorage (cross-page, same device)
    try { localStorage.setItem('upskill_avatar', dataUrl); } catch(e) {}
    showToast(currentLang === 'ar' ? 'تم تحديث الصورة ✓' : 'Photo mise à jour ✓');
  };
  reader.readAsDataURL(file);
}

function applyAvatarToSidebar(dataUrl) {
  const wrap = document.getElementById('sidebar-user');
  if (!wrap) return;
  let img = document.getElementById('sidebar-av-img');
  if (!img) {
    img = document.createElement('img');
    img.id = 'sidebar-av-img';
    img.alt = '';
    img.style.cssText = 'width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid rgba(245,158,11,.4);flex-shrink:0;';
    const av = document.getElementById('sidebar-avatar');
    if (av) { av.style.display = 'none'; wrap.insertBefore(img, av); }
    else wrap.prepend(img);
  }
  img.src = dataUrl;
  img.style.display = '';
}

function applyAvatarToTopbar(dataUrl) {
  const topbar = document.querySelector('.topbar-actions');
  if (!topbar) return;
  let img = document.getElementById('topbar-av-img');
  if (!img) {
    img = document.createElement('img');
    img.id = 'topbar-av-img';
    img.alt = '';
    img.style.cssText = 'width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(245,158,11,.4);';
    const av = document.getElementById('topbar-avatar');
    if (av) { av.style.display = 'none'; topbar.appendChild(img); }
    else topbar.appendChild(img);
  }
  img.src = dataUrl;
  img.style.display = '';
}

function loadSavedAvatar() {
  try {
    const dataUrl = localStorage.getItem('upskill_avatar');
    if (!dataUrl) return;
    applyAvatarToSidebar(dataUrl);
    applyAvatarToTopbar(dataUrl);
    // settings page
    const img = document.getElementById('settings-av-img');
    const initDiv = document.getElementById('settings-avatar');
    if (img)     { img.src = dataUrl; img.style.display = ''; }
    if (initDiv) initDiv.style.display = 'none';
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
  showToast(currentLang === 'ar' ? 'قريباً — ستُضاف الفيديوهات قريباً!' : 'Bientôt disponible — vidéos en cours de préparation !');
}

function showToast(msg) {
  const toast = document.getElementById('toast');
  document.getElementById('toast-msg').textContent = msg;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 2800);
}

function logout() {
  sessionStorage.clear();
  window.location.href = 'logout.php';
}

/* INIT */
document.addEventListener('DOMContentLoaded', () => {
  const savedLang = sessionStorage.getItem('upskill_lang') || 'fr';
  setLang(savedLang);
  hydrateLiveData();
  renderStars();
  renderLeaderboard();
  renderAssignments();
  renderQuizzes();
  renderHowTo();
  loadSavedAvatar();
});

/* ── LIVE DATA HYDRATION ── */
function hydrateLiveData() {
  const c    = LIVE.course;
  const lang = currentLang;

  // ── 1. User name & initials ──────────────────────────────────────────────
  const fn = <?= json_encode($_SESSION['full_name'] ?? $_SESSION['username'] ?? '') ?>;
  if (fn) {
    const parts = fn.trim().split(/\s+/);
    const init  = ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '?';
    ['sidebar-avatar','topbar-avatar','settings-avatar'].forEach(id => {
      const el = document.getElementById(id); if (el) el.textContent = init;
    });
    const sn = document.getElementById('sidebar-name'); if (sn) sn.textContent = fn;
    st('welcome-name', fn.split(' ')[0]);
    st('settings-name', fn);
    const pi = document.getElementById('pref-name'); if (pi) pi.value = fn;
  }

  // ── 2. Attendance ────────────────────────────────────────────────────────
  const attRate = LIVE.att_rate;

  // ── 3. Progress page — circle & labels ──────────────────────────────────
  if (attRate !== null) {
    // SVG circle: circumference = 2π×38 ≈ 238.76; dashoffset = circ × (1 - pct/100)
    const circ = 238.76;
    const offset = circ * (1 - attRate / 100);
    const circle = document.getElementById('prog-circle');
    if (circle) circle.setAttribute('stroke-dashoffset', offset.toFixed(1));
    const pct = document.getElementById('prog-circle-pct');
    if (pct) pct.textContent = attRate + '%';
    // hrs label on progress page
    const hrs = document.getElementById('hrs-of');
    if (hrs) hrs.textContent = lang === 'ar'
      ? `/ ${LIVE.att_total} جلسة`
      : `/ ${LIVE.att_total} séances`;
    const hrsVal = hrs?.previousSibling;
    const hrsParent = document.getElementById('hrs-of')?.parentElement;
    if (hrsParent) {
      const firstText = hrsParent.childNodes[0];
      if (firstText && firstText.nodeType === 3) {
        firstText.textContent = LIVE.att_present + ' ';
      }
    }
  }

  // ── 5. Course name on progress page ─────────────────────────────────────
  if (c) {
    const courseName = lang === 'ar'
      ? (c.group_name_ar || c.group_name_fr)
      : (c.group_name_fr || c.group_name_ar);
    const cs = document.getElementById('course-session');
    if (cs) cs.textContent = courseName;
    const sr = document.getElementById('settings-role');
    if (sr) sr.textContent = (lang === 'ar' ? 'طالب · ' : 'Étudiant · ') + courseName;
  }

  // ── 6. Assignment page subtitle ──────────────────────────────────────────
  const sub = document.getElementById('assign-page-sub');
  if (sub) {
    const p = LIVE.pending_count || 0;
    const o = LIVE.overdue_count || 0;
    const s = LIVE.submitted_count || 0;
    sub.textContent = lang === 'ar'
      ? `${p} معلقة · ${o} متأخرة · ${s} مُسلَّمة`
      : `${p} en attente · ${o} en retard · ${s} soumis`;
  }

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
  document.getElementById('submit-assign-due').textContent =
    (currentLang === 'ar' ? 'الموعد: ' : 'Échéance : ') + due;
  document.getElementById('submit-comment').value = '';
  document.getElementById('submit-char-count').textContent = '0';
  document.getElementById('submit-error').style.display = 'none';
  document.getElementById('submit-btn-text').textContent =
    currentLang === 'ar' ? '← تسليم' : 'Soumettre →';
  document.getElementById('submit-modal-title').textContent =
    currentLang === 'ar' ? 'تسليم الواجب' : 'Soumettre le devoir';
  document.getElementById('submit-comment-lbl').textContent =
    currentLang === 'ar' ? 'ملاحظة (اختياري)' : 'Commentaire (optionnel)';
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
    const res = await fetch('api_submit.php?action=submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrf },
      body: JSON.stringify({ assignment_id: parseInt(aid), comment })
    });
    const data = await res.json();

    if (!data.ok) throw new Error(data.error || 'Erreur serveur');

    // Update local LIVE data
    const a = LIVE.assignments.find(x => x.id == aid);
    if (a) {
      a.status       = 'submitted';
      a.submitted_at = data.submitted_at;
      LIVE.submitted_count = (LIVE.submitted_count || 0) + 1;
      if (a.status === 'pending')  LIVE.pending_count  = Math.max(0, (LIVE.pending_count  || 1) - 1);
      if (a.status === 'overdue')  LIVE.overdue_count  = Math.max(0, (LIVE.overdue_count  || 1) - 1);
    }

    closeSubmitModal();
    renderAssignments();
    hydrateLiveData();
    showToast(currentLang === 'ar' ? 'تم تسليم الواجب ✓' : 'Devoir soumis ✓', 'success');

  } catch(err) {
    errEl.textContent = err.message;
    errEl.style.display = '';
  } finally {
    btnText.textContent = currentLang === 'ar' ? '← تسليم' : 'Soumettre →';
    btn.disabled = false;
  }
}

async function retractSubmission(aid) {
  const isAr = currentLang === 'ar';
  if (!confirm(isAr ? 'هل تريد سحب هذا التسليم؟' : 'Rétracter cette soumission ?')) return;

  try {
    const res = await fetch('api_submit.php?action=unsubmit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrf },
      body: JSON.stringify({ assignment_id: aid })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Erreur');

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
    showToast(isAr ? 'تم سحب التسليم' : 'Soumission rétractée', 'default');

  } catch(err) {
    showToast(err.message, 'error');
  }
}
</script>
<!-- NOTIF PANEL (outside topbar to avoid stacking context clipping) -->
<div class="notif-panel" id="notif-panel" onclick="event.stopPropagation()" style="position:fixed;top:64px;right:1rem;z-index:9000;">
  <div class="notif-panel-header">
    <span class="notif-panel-title" id="notif-panel-title">Notifications</span>
    <button class="notif-mark-all" onclick="markAllRead()" id="notif-mark-all-btn">Tout lire</button>
  </div>
  <div class="notif-list" id="notif-list">
    <div class="notif-empty" id="notif-loading">Chargement…</div>
  </div>
</div>

<!-- ── EMAIL COLLECTION POPUP ── -->
<div id="email-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:8000;pointer-events:none;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#162436;border:1px solid rgba(62,207,120,.25);border-radius:20px;padding:2rem;width:100%;max-width:440px;position:relative;animation:slideUp .3s ease;">
    <!-- Icon -->
    <div style="width:52px;height:52px;background:rgba(62,207,120,.12);border-radius:14px;display:flex;align-items:center;justify-content:center;margin-bottom:1.25rem;">
      <svg width="24" height="24" fill="none" stroke="#3ecf78" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    </div>
    <div id="eml-title" style="font-family:'Sora',sans-serif;font-size:1.15rem;font-weight:700;margin-bottom:.4rem;">Ajoutez votre adresse e-mail</div>
    <div id="eml-sub" style="color:rgba(255,255,255,.55);font-size:.88rem;margin-bottom:1.5rem;line-height:1.5;">Pour recevoir vos rappels de cours, devoirs et identifiants de connexion, ajoutez votre e-mail. Vous ne verrez ce message qu'une seule fois.</div>
    <div style="margin-bottom:1.25rem;">
      <label id="eml-label" style="display:block;font-family:'Sora',sans-serif;font-size:.72rem;font-weight:600;color:rgba(255,255,255,.5);letter-spacing:.06em;text-transform:uppercase;margin-bottom:.4rem;">ADRESSE E-MAIL</label>
      <input id="eml-input" type="email" maxlength="180"
        placeholder="vous@exemple.com"
        style="width:100%;padding:.85rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;transition:border-color .2s;"
        oninput="document.getElementById('eml-error').style.display='none'"
        onkeydown="if(event.key==='Enter')submitEmail()">
    </div>
    <div id="eml-error" style="display:none;color:#f87171;font-size:.82rem;margin-bottom:.75rem;"></div>
    <button id="eml-btn" onclick="submitEmail()"
      style="width:100%;padding:.9rem;background:#3ecf78;color:#0f1d2e;font-family:'Sora',sans-serif;font-weight:700;font-size:.95rem;border:none;border-radius:12px;cursor:pointer;margin-bottom:.75rem;">
      <span id="eml-btn-lbl">Enregistrer mon e-mail →</span>
    </button>
    <button onclick="dismissEmail()"
      style="width:100%;padding:.6rem;background:none;border:none;color:rgba(255,255,255,.3);font-family:'DM Sans',sans-serif;font-size:.83rem;cursor:pointer;">
      <span id="eml-skip-lbl">Me le rappeler plus tard</span>
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
  ar: {
    title:    'أضف بريدك الإلكتروني',
    sub:      'لتلقي تذكيرات الدروس والواجبات وبيانات الدخول، أضف بريدك الإلكتروني. لن تظهر هذه الرسالة إلا مرة واحدة.',
    label:    'البريد الإلكتروني',
    ph:       'example@mail.com',
    btn:      'حفظ البريد الإلكتروني ←',
    skip:     'تذكيري لاحقاً',
    errEmpty: 'يرجى إدخال بريد إلكتروني.',
    errInvalid:'بريد إلكتروني غير صالح.',
    errTaken: 'هذا البريد مستخدم بالفعل.',
    errFail:  'حدث خطأ. يرجى المحاولة.',
    saving:   '…جارٍ الحفظ',
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
      showToast(currentLang === 'ar' ? '✅ تم حفظ بريدك الإلكتروني' : '✅ E-mail enregistré !');
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
};
const NOTIF_T = {
  fr: { title:'Notifications', markAll:'Tout lire', empty:'Aucune notification', justNow:'À l\'instant', minAgo:'min', hrsAgo:'h', daysAgo:'j' },
  ar: { title:'الإشعارات', markAll:'تحديد الكل كمقروء', empty:'لا توجد إشعارات', justNow:'الآن', minAgo:'د', hrsAgo:'س', daysAgo:'ي' },
};

let notifOpen = false;
let notifData = [];

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

// Close panel when clicking outside
document.addEventListener('click', function(e) {
  if (notifOpen && !document.getElementById('notif-btn').contains(e.target)) {
    notifOpen = false;
    document.getElementById('notif-panel').classList.remove('open');
  }
});

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
    const title = lang === 'ar' ? n.title_ar : n.title_fr;
    const body  = lang === 'ar' ? n.body_ar  : n.body_fr;
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

async function markAllRead() {
  try {
    await fetch('api_notifications.php', { method:'POST', body: new URLSearchParams({ action:'mark_read' }) });
    notifData.forEach(n => n.is_read = 1);
    renderNotifList();
    updateNotifBadge(0);
  } catch(e) {}
}

async function markOneRead(id, el) {
  try {
    await fetch('api_notifications.php', { method:'POST', body: new URLSearchParams({ action:'mark_one', id }) });
    el.classList.remove('unread');
    const dot = el.querySelector('.notif-unread-dot');
    if (dot) dot.remove();
    const n = notifData.find(x => x.id == id);
    if (n) n.is_read = 1;
    const unread = notifData.filter(x => x.is_read == 0).length;
    updateNotifBadge(unread);
  } catch(e) {}
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
