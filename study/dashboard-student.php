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
  --navy:#0f1d2e; --navy-mid:#162436; --navy-light:#1e3248; --navy-card:rgba(255,255,255,0.04);
  --green:#3ecf78; --green-dark:#28a85c; --green-glow:rgba(62,207,120,0.15); --green-dim:rgba(62,207,120,0.1);
  --white:#ffffff; --muted:rgba(255,255,255,0.55); --muted2:rgba(255,255,255,0.50);
  --border:rgba(255,255,255,0.1); --border2:rgba(255,255,255,0.07);
  --yellow:#f5c542; --red:#e85d75; --blue:#5b9cf6;
  --font:'Sora',sans-serif; --font-body:'DM Sans',sans-serif; --font-ar:'Cairo',sans-serif;
  --sidebar-w:260px;
}
html { scroll-behavior: smooth; }
body { background:var(--navy); color:var(--white); font-family:var(--font-body); min-height:100vh; display:flex; overflow-x:hidden; }
body.ar { font-family:var(--font-ar); direction:rtl; }
body.ar .sidebar { left:auto; right:0; border-right:none; border-left:1px solid var(--border); }
body.ar .main { margin-left:0; margin-right:var(--sidebar-w); }
body.ar .nav-badge { margin-left:0; margin-right:auto; }

/* SIDEBAR */
.sidebar { width:var(--sidebar-w); background:var(--navy-mid); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:200; transition:transform .3s; }
.sidebar-logo { display:flex; align-items:center; gap:.6rem; padding:1.5rem 1.4rem; border-bottom:1px solid var(--border); }
.sidebar-logo span { font-family:var(--font); font-weight:600; font-size:1rem; }
body.ar .sidebar-logo span { font-family:var(--font-ar); }
.sidebar-logo em { color:var(--green); font-style:normal; }
.lang-toggle { display:flex; gap:.4rem; padding:.6rem 1.4rem; border-bottom:1px solid var(--border); }
.lang-pill { font-size:.7rem; font-family:var(--font); font-weight:600; padding:.25rem .65rem; border-radius:100px; border:1px solid var(--border); color:var(--muted); cursor:pointer; transition:all .2s; }
.lang-pill.active { background:var(--green-glow); border-color:rgba(62,207,120,.4); color:var(--green); }
.sidebar-user { padding:1.2rem 1.4rem; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:.8rem; }
body.ar .sidebar-user { flex-direction:row-reverse; }
.avatar { width:38px; height:38px; border-radius:50%; background:var(--green-glow); border:2px solid rgba(62,207,120,.4); display:flex; align-items:center; justify-content:center; font-family:var(--font); font-weight:700; font-size:.85rem; color:var(--green); flex-shrink:0; }
.user-info .name { font-family:var(--font); font-size:.85rem; font-weight:600; line-height:1.2; }
body.ar .user-info .name { font-family:var(--font-ar); }
.user-info .role-tag { font-size:.72rem; color:var(--green); background:var(--green-dim); padding:.1rem .5rem; border-radius:100px; margin-top:.2rem; display:inline-block; }
.sidebar-nav { flex:1; padding:1rem .8rem; overflow-y:auto; }
.nav-section-label { font-family:var(--font); font-size:.65rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muted2); padding:.5rem .6rem .3rem; margin-top:.5rem; }
body.ar .nav-section-label { letter-spacing:0; text-align:right; font-family:var(--font-ar); }
.nav-item { display:flex; align-items:center; gap:.75rem; padding:.65rem .9rem; border-radius:10px; cursor:pointer; color:var(--muted); font-size:.88rem; font-family:var(--font); font-weight:500; transition:all .2s; margin-bottom:.1rem; }
body.ar .nav-item { flex-direction:row-reverse; font-family:var(--font-ar); }
.nav-item svg { flex-shrink:0; opacity:.7; }
.nav-item:hover { background:rgba(255,255,255,.05); color:var(--white); }
.nav-item.active { background:var(--green-glow); color:var(--green); border:1px solid rgba(62,207,120,.25); }
.nav-item.active svg { opacity:1; }
.nav-badge { margin-left:auto; background:var(--green); color:var(--navy); font-size:.65rem; font-weight:700; padding:.15rem .45rem; border-radius:100px; font-family:var(--font); }
.sidebar-bottom { padding:1rem; border-top:1px solid var(--border); }
.btn-logout { display:flex; align-items:center; gap:.6rem; width:100%; padding:.65rem .9rem; border-radius:10px; background:transparent; border:1px solid var(--border); color:var(--muted); font-family:var(--font); font-size:.85rem; cursor:pointer; transition:all .2s; }
body.ar .btn-logout { flex-direction:row-reverse; font-family:var(--font-ar); }
.btn-logout:hover { border-color:var(--red); color:var(--red); background:rgba(232,93,117,.08); }

/* MAIN */
.main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; }
.topbar { background:rgba(15,29,46,.9); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); padding:1rem 2rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
body.ar .topbar { flex-direction:row-reverse; }
.topbar-title { font-family:var(--font); font-size:1rem; font-weight:600; }
body.ar .topbar-title { font-family:var(--font-ar); }
.topbar-actions { display:flex; align-items:center; gap:.75rem; }
.btn-icon { width:36px; height:36px; border-radius:9px; background:rgba(255,255,255,.05); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--muted); transition:all .2s; }
.btn-icon:hover { border-color:var(--green); color:var(--green); background:var(--green-glow); }
.notif-dot { position:relative; }
.notif-dot::after { content:''; position:absolute; top:6px; right:6px; width:7px; height:7px; border-radius:50%; background:var(--green); border:2px solid var(--navy-mid); }
.page { padding:2rem; display:none; animation:fadeIn .25s ease; }
.page.active { display:block; }
@keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

/* CARDS */
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
.grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:1.5rem; }
.grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; }
.card { background:var(--navy-card); border:1px solid var(--border); border-radius:16px; padding:1.5rem; transition:border-color .2s; }
.card:hover { border-color:rgba(62,207,120,.2); }
.card-title { font-family:var(--font); font-size:.8rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); margin-bottom:1rem; }
body.ar .card-title { font-family:var(--font-ar); letter-spacing:0; text-align:right; }

/* STAT CARDS */
.stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; margin-bottom:1rem; font-size:1.3rem; }
.stat-icon.green { background:var(--green-dim); }
.stat-icon.yellow { background:rgba(245,197,66,.1); }
.stat-icon.red { background:rgba(232,93,117,.1); }
.stat-icon.blue { background:rgba(91,156,246,.1); }
.stat-value { font-family:var(--font); font-size:2rem; font-weight:700; letter-spacing:-.03em; margin-bottom:.25rem; }
.stat-label { font-size:.83rem; color:var(--muted); }
body.ar .stat-label { text-align:right; font-family:var(--font-ar); }
.stat-change { font-size:.75rem; margin-top:.4rem; display:flex; align-items:center; gap:.3rem; }
.stat-change.up { color:var(--green); }

/* PROGRESS */
.progress-bar { height:8px; background:rgba(255,255,255,.08); border-radius:100px; overflow:hidden; margin:.5rem 0; }
.progress-fill { height:100%; border-radius:100px; background:var(--green); transition:width .8s cubic-bezier(.4,0,.2,1); }
.progress-fill.yellow { background:var(--yellow); }
.module-row { display:flex; align-items:center; gap:1rem; padding:.75rem 0; border-bottom:1px solid var(--border2); }
body.ar .module-row { flex-direction:row-reverse; }
.module-row:last-child { border-bottom:none; }
.module-name { flex:1; font-size:.88rem; font-family:var(--font); font-weight:500; }
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

/* WELCOME BANNER */
.welcome-banner { background:linear-gradient(135deg,#162436 0%,#1a3048 100%); border:1px solid var(--border); border-radius:20px; padding:2rem 2.5rem; margin-bottom:2rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; position:relative; overflow:hidden; }
body.ar .welcome-banner { flex-direction:row-reverse; }
.welcome-banner::before { content:''; position:absolute; top:-60px; right:-60px; width:200px; height:200px; background:radial-gradient(circle,rgba(62,207,120,.12),transparent 70%); }
.welcome-text h2 { font-family:var(--font); font-size:1.6rem; font-weight:700; letter-spacing:-.03em; margin-bottom:.4rem; }
body.ar .welcome-text h2 { font-family:var(--font-ar); letter-spacing:0; text-align:right; }
.welcome-text h2 span { color:var(--green); }
.welcome-text p { color:var(--muted); font-size:.9rem; }
body.ar .welcome-text p { text-align:right; font-family:var(--font-ar); }
.welcome-emoji { font-size:3rem; }

/* BADGE */
.badge { display:inline-flex; align-items:center; padding:.2rem .65rem; border-radius:100px; font-size:.72rem; font-weight:700; font-family:var(--font); flex-shrink:0; }
.badge.pending { background:rgba(245,197,66,.12); color:var(--yellow); border:1px solid rgba(245,197,66,.3); }
.badge.submitted { background:rgba(62,207,120,.12); color:var(--green); border:1px solid rgba(62,207,120,.3); }
.badge.overdue { background:rgba(232,93,117,.12); color:var(--red); border:1px solid rgba(232,93,117,.3); }

/* ASSIGN */
.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;display:none;align-items:center;justify-content:center;padding:1rem;pointer-events:none; }
.modal-overlay.open { display:flex;pointer-events:auto; }
.modal { background:var(--navy-mid);border:1px solid var(--border);border-radius:20px;padding:2rem;max-width:520px;width:100%;max-height:90vh;overflow-y:auto;animation:slideUp .25s ease; }
@keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
.modal-header { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;gap:1rem; }
.modal-header h3 { font-family:var(--font);font-size:1.1rem;font-weight:700; }
.modal-footer { display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem; }
.btn-close { background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.3rem;line-height:1;transition:color .2s;padding:0; }
.btn-close:hover { color:var(--white); }
.btn-secondary { padding:.75rem 1.4rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;color:var(--muted);font-family:var(--font);font-size:.88rem;font-weight:600;cursor:pointer;transition:.2s; }
.btn-secondary:hover { color:var(--white);border-color:rgba(255,255,255,.3); }
.assign-item { border:1px solid var(--border); border-radius:14px; padding:1.2rem 1.4rem; margin-bottom:.75rem; transition:border-color .2s,background .2s; cursor:pointer; background:var(--navy-card); }
.assign-item:hover { border-color:rgba(62,207,120,.3); background:rgba(62,207,120,.03); }
.assign-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:.5rem; }
body.ar .assign-header { flex-direction:row-reverse; }
.assign-title { font-family:var(--font); font-size:.95rem; font-weight:600; }
body.ar .assign-title { font-family:var(--font-ar); text-align:right; }
.assign-desc { font-size:.83rem; color:var(--muted); margin-bottom:.6rem; line-height:1.5; }
body.ar .assign-desc { text-align:right; font-family:var(--font-ar); }
.assign-meta { display:flex; align-items:center; gap:1rem; font-size:.78rem; color:var(--muted2); }
body.ar .assign-meta { flex-direction:row-reverse; }

/* TABS */
.tabs { display:flex; gap:.5rem; margin-bottom:1.5rem; border-bottom:1px solid var(--border); padding-bottom:0; }
.tab { padding:.6rem 1rem; font-family:var(--font); font-size:.85rem; font-weight:500; color:var(--muted); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .2s; }
body.ar .tab { font-family:var(--font-ar); }
.tab.active { color:var(--green); border-bottom-color:var(--green); }
.tab:hover:not(.active) { color:var(--white); }

/* QUIZ */
.quiz-card { background:var(--navy-card); border:1px solid var(--border); border-radius:16px; padding:1.5rem; transition:all .2s; cursor:pointer; display:flex; flex-direction:column; }
.quiz-card:hover { border-color:rgba(62,207,120,.35); transform:translateY(-2px); }
.quiz-icon { font-size:2rem; margin-bottom:.8rem; }
.quiz-title { font-family:var(--font); font-size:1rem; font-weight:700; margin-bottom:.4rem; }
body.ar .quiz-title { font-family:var(--font-ar); text-align:right; }
.quiz-desc { font-size:.83rem; color:var(--muted); margin-bottom:1rem; line-height:1.5; flex:1; }
body.ar .quiz-desc { text-align:right; font-family:var(--font-ar); }
.quiz-meta { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem; }
.quiz-meta span { font-size:.75rem; background:rgba(255,255,255,.06); border:1px solid var(--border); padding:.2rem .6rem; border-radius:100px; color:var(--muted); font-family:var(--font); }

/* BUTTONS */
.btn-primary { background:var(--green); color:var(--navy); font-family:var(--font); font-weight:700; font-size:.9rem; padding:.75rem 1.5rem; border:none; border-radius:10px; cursor:pointer; transition:background .2s,transform .15s; display:inline-flex; align-items:center; gap:.5rem; }
body.ar .btn-primary { font-family:var(--font-ar); }
.btn-primary:hover { background:var(--green-dark); transform:translateY(-1px); }
.btn-secondary { background:rgba(255,255,255,.06); color:var(--muted); font-family:var(--font); font-weight:500; font-size:.9rem; padding:.75rem 1.5rem; border:1px solid var(--border); border-radius:10px; cursor:pointer; transition:all .2s; }
body.ar .btn-secondary { font-family:var(--font-ar); }
.btn-secondary:hover { border-color:rgba(255,255,255,.2); color:var(--white); }

/* TOAST */
.toast { position:fixed; bottom:2rem; right:2rem; background:var(--navy-light); border:1px solid var(--border); border-radius:12px; padding:.9rem 1.4rem; font-family:var(--font); font-size:.85rem; color:var(--white); z-index:9999; transform:translateY(100px); opacity:0; transition:all .3s; display:flex; align-items:center; gap:.6rem; }
body.ar .toast { right:auto; left:2rem; font-family:var(--font-ar); }
.toast.show { transform:translateY(0); opacity:1; }
.toast-dot { width:8px; height:8px; border-radius:50%; background:var(--green); }

.hamburger { display:none; width:36px; height:36px; border-radius:9px; background:rgba(255,255,255,.05); border:1px solid var(--border); align-items:center; justify-content:center; cursor:pointer; color:var(--muted); flex-shrink:0; }
.sidebar-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:199; pointer-events:none; }
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
  .welcome-banner { flex-direction:column; align-items:flex-start; padding:1.25rem; }
  .welcome-banner::before { display:none; }
}
@media(max-width:480px){
  .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr; }
  .card { padding:1rem; }
  .page { padding:.75rem; }
}

/* ── NOTIFICATION PANEL ── */
.notif-panel { position:fixed; top:64px; right:1rem; width:320px; background:var(--navy-mid); border:1px solid var(--border); border-radius:16px; box-shadow:0 8px 32px rgba(0,0,0,.4); z-index:9000; overflow:hidden; display:none; animation:fadeIn .15s ease; }
body.ar .notif-panel { right:auto; left:1rem; }
.notif-panel.open { display:block; }
.notif-panel-header { display:flex; align-items:center; justify-content:space-between; padding:.9rem 1.1rem; border-bottom:1px solid var(--border); }
.notif-panel-title { font-family:var(--font); font-size:.82rem; font-weight:700; }
.notif-mark-all { font-family:var(--font); font-size:.72rem; color:var(--green); cursor:pointer; background:none; border:none; padding:0; }
.notif-mark-all:hover { text-decoration:underline; }
.notif-list { max-height:320px; overflow-y:auto; }
.notif-item { display:flex; gap:.75rem; padding:.85rem 1.1rem; border-bottom:1px solid rgba(255,255,255,.05); cursor:pointer; transition:background .15s; align-items:flex-start; }
.notif-item:hover { background:rgba(255,255,255,.04); }
.notif-item.unread { background:rgba(62,207,120,.04); }
.notif-icon { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
.notif-icon.new_assignment { background:rgba(91,156,246,.15); }
.notif-icon.overdue { background:rgba(245,197,66,.15); }
.notif-icon.submission { background:rgba(62,207,120,.12); }
.notif-icon.info { background:rgba(255,255,255,.07); }
.notif-content { flex:1; min-width:0; }
.notif-title { font-family:var(--font); font-size:.8rem; font-weight:600; margin-bottom:.15rem; }
.notif-body { font-size:.78rem; color:var(--muted); line-height:1.4; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:220px; }
.notif-time { font-size:.7rem; color:rgba(255,255,255,.3); margin-top:.2rem; }
.notif-unread-dot { width:7px; height:7px; border-radius:50%; background:var(--green); flex-shrink:0; margin-top:6px; }
.notif-empty { padding:2rem; text-align:center; color:var(--muted); font-size:.85rem; }
.notif-badge { position:absolute; top:3px; right:3px; min-width:16px; height:16px; background:var(--green); color:var(--navy); font-size:.58rem; font-weight:700; border-radius:100px; display:flex; align-items:center; justify-content:center; font-family:var(--font); padding:0 3px; border:2px solid var(--navy-mid); }
.btn-icon { position:relative; }

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
    <div class="nav-section-label" id="nav-main-label">Principal</div>
    <div class="nav-item active" onclick="navigate('home',this)" id="nav-home">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span id="nav-home-lbl">Accueil</span>
    </div>
    <div class="nav-item" onclick="navigate('assignments',this)" id="nav-assign">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <span id="nav-assign-lbl">Devoirs</span>
      <span class="nav-badge" aria-hidden="true">3</span>
    </div>
    <div class="nav-item" onclick="navigate('quizzes',this)" id="nav-quiz">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span id="nav-quiz-lbl">Quiz</span>
      <span class="nav-badge" aria-hidden="true">2</span>
    </div>
    <div class="nav-item" onclick="navigate('progress',this)" id="nav-prog">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span id="nav-prog-lbl">Progression</span>
    </div>
    <div class="nav-section-label" id="nav-account-label" style="margin-top:.5rem;">Compte</div>
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
    <div class="welcome-banner">
      <div class="welcome-text">
        <h2 id="welcome-msg">Bonjour, <span id="welcome-name"><?= htmlspecialchars(explode(" ", $full_name)[0]) ?></span> 👋</h2>
        <p id="welcome-sub">Vous avez 3 devoirs en attente et 2 quiz disponibles.</p>
      </div>
      <div class="welcome-emoji">🎓</div>
    </div>

    <div class="grid-4" style="margin-bottom:1.5rem;">
      <div class="card"><div class="stat-icon green"><span aria-hidden="true">📚</span></div><div class="stat-value" id="stat1-val">68%</div><div class="stat-label" id="stat1-lbl">Progression du cours</div></div>
      <div class="card"><div class="stat-icon yellow"><span aria-hidden="true">📝</span></div><div class="stat-value" id="stat2-val">3</div><div class="stat-label" id="stat2-lbl">Devoirs en attente</div></div>
      <div class="card"><div class="stat-icon blue"><span aria-hidden="true">🧠</span></div><div class="stat-value" id="stat3-val">2</div><div class="stat-label" id="stat3-lbl">Quiz disponibles</div></div>
      <div class="card"><div class="stat-icon red"><span aria-hidden="true">⭐</span></div><div class="stat-value" id="stat4-val">–</div><div class="stat-label" id="stat4-lbl">Devoirs soumis</div></div>
    </div>

    <div class="grid-2">
      <div>
        <!-- Current Course -->
        <div class="card" id="course-card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,rgba(62,207,120,.12),rgba(62,207,120,.04));border-color:rgba(62,207,120,.3);">
          <div id="course-assigned-content">
          <div style="display:inline-block;background:rgba(62,207,120,.15);color:var(--green);font-family:var(--font);font-size:.72rem;font-weight:600;padding:.25rem .7rem;border-radius:100px;margin-bottom:.8rem;" id="course-tag">Cours actuel</div>
          <div style="font-family:var(--font);font-size:1.2rem;font-weight:700;margin-bottom:.4rem;" id="course-name">Anglais Général – Session 2</div>
          <div style="color:var(--muted);font-size:.83rem;margin-bottom:1.2rem;display:flex;gap:1rem;">
            <span>👨‍🏫 <span id="teacher-name-label">Prof. Hassan</span></span>
            <span>📅 <span id="schedule-label">Lun–Mer–Ven</span></span>
          </div>
          </div>
          <div id="course-empty-state" style="display:none;text-align:center;padding:1.5rem 0;">
            <div style="font-size:2rem;margin-bottom:.75rem;">📚</div>
            <div style="font-family:var(--font);font-weight:600;margin-bottom:.4rem;" id="course-empty-title">Aucun cours assigné</div>
            <div style="color:var(--muted);font-size:.83rem;" id="course-empty-sub">Votre classe n'a pas encore été configurée. L'administrateur vous assignera un cours bientôt.</div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;font-size:.8rem;color:var(--muted);margin-bottom:.4rem;">
            <span id="progress-lbl">Progression</span><strong style="font-family:var(--font);color:var(--green);">68%</strong>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:68%"></div></div>
          <div style="font-size:.78rem;color:var(--muted);margin-top:.6rem;" id="hours-lbl">20 / 29 heures complétées</div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
          <div class="card-title" id="qa-title">Actions rapides</div>
          <div class="grid-2" style="gap:.75rem;">
            <div class="card" style="cursor:pointer;text-align:center;padding:1rem;" onclick="navigate('assignments', document.getElementById('nav-assign'))">
              <div style="font-size:1.5rem;margin-bottom:.4rem;">📝</div>
              <div style="font-family:var(--font);font-size:.85rem;font-weight:600;" id="qa-assign">Devoirs</div>
              <div style="font-size:.75rem;color:var(--yellow);margin-top:.2rem;" id="qa-assign-sub">3 en attente</div>
            </div>
            <div class="card" style="cursor:pointer;text-align:center;padding:1rem;" onclick="navigate('quizzes', document.getElementById('nav-quiz'))">
              <div style="font-size:1.5rem;margin-bottom:.4rem;">🧠</div>
              <div style="font-family:var(--font);font-size:.85rem;font-weight:600;" id="qa-quiz">Quiz</div>
              <div style="font-size:.75rem;color:var(--blue);margin-top:.2rem;" id="qa-quiz-sub">2 disponibles</div>
            </div>
            <div class="card" style="cursor:pointer;text-align:center;padding:1rem;" onclick="navigate('progress', document.getElementById('nav-prog'))">
              <div style="font-size:1.5rem;margin-bottom:.4rem;">📊</div>
              <div style="font-family:var(--font);font-size:.85rem;font-weight:600;" id="qa-prog">Progression</div>
              <div style="font-size:.75rem;color:var(--green);margin-top:.2rem;">68%</div>
            </div>
            <div class="card" style="cursor:pointer;text-align:center;padding:1rem;" onclick="navigate('settings', document.getElementById('nav-set'))">
              <div style="font-size:1.5rem;margin-bottom:.4rem;">⚙️</div>
              <div style="font-family:var(--font);font-size:.85rem;font-weight:600;" id="qa-set">Paramètres</div>
              <div style="font-size:.75rem;color:var(--muted);margin-top:.2rem;" id="qa-set-sub">Profil</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Activity Feed -->
      <div class="card">
        <div class="card-title" id="activity-title">Activité récente</div>
        <div id="activity-feed">
          <!-- populated by renderActivityFeed() -->
          <div style="color:var(--muted);font-size:.85rem;padding:1rem 0;text-align:center;">Chargement…</div>
        </div>
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
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
          <div class="avatar" style="width:56px;height:56px;font-size:1.2rem;" id="settings-avatar"><?php $parts=explode(" ",trim($full_name));echo strtoupper(substr($parts[0],0,1).substr($parts[1]??$parts[0],0,1)); ?></div>
          <div>
            <div style="font-family:var(--font);font-weight:600;" id="settings-name"><?= $full_name ?></div>
            <div style="color:var(--muted);font-size:.83rem;" id="settings-role">Étudiante · Anglais Général S2</div>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:1rem;">
          <label style="display:block;font-family:var(--font);font-size:.73rem;font-weight:600;color:var(--muted);letter-spacing:.07em;text-transform:uppercase;margin-bottom:.45rem;" id="lbl-fullname">Nom complet</label>
          <input type="text" id="pref-name" style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;" value="<?= htmlspecialchars($full_name) ?>">
        </div>
        <button class="btn-primary" onclick="saveProfile()" id="save-btn">Enregistrer</button>
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
const LIVE = <?= $jsData ?>;
const HAS_EMAIL = <?= json_encode(!empty($studentEmail)) ?>;

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
    topbarTitle: { home:'Tableau de bord', assignments:'Devoirs', quizzes:'Quiz', progress:'Progression', settings:'Paramètres' },
    navMain:'Principal', navAccount:'Compte',
    navHome:'Accueil', navAssign:'Devoirs', navQuiz:'Quiz', navProg:'Progression', navSet:'Paramètres',
    roleLabel:'Étudiante', logout:'Déconnexion',
    welcomeMsg:'Bonjour, ', welcomeSub:'Vous avez 3 devoirs en attente et 2 quiz disponibles.',
    stat1:'Présence au cours', stat2:'Devoirs en attente', stat3:'Quiz disponibles', stat4:'Devoirs soumis',
    courseTag:'Cours actuel', courseName:'Anglais Général – Session 2', teacherName:'Prof. Hassan', schedule:'Lun–Mer–Ven',
    progressLbl:'Progression', hoursLbl:'20 / 29 heures complétées',
    qaTitle:'Actions rapides', qaAssign:'Devoirs', qaAssignSub:'3 en attente', qaQuiz:'Quiz', qaQuizSub:'2 disponibles', qaProg:'Progression', qaSet:'Paramètres', qaSetSub:'Profil',
    activityTitle:'Activité récente',
    act1s:'Quiz soumis', act1t:'Quiz Grammaire de base', act1time:"Aujourd'hui, 10:32 · Score: 85%",
    act2s:'Devoir à rendre bientôt',
    act3s:'Nouveau quiz disponible',
    act4s:'Devoir noté',
    act5s:'Devoir en retard', act5t:"Exercice d'écoute #2",
    assignPageTitle:'Devoirs', assignPageSub:'3 en attente · 1 en retard · 2 soumis',
    tabAll:'Tous', tabPending:'En attente', tabDone:'Soumis',
    tabAllQ:'Tous', tabAvailQ:'Disponibles', tabDoneQ:'Complétés',
    quizPageTitle:'Quiz', quizPageSub:'Testez vos connaissances avec des quiz chronométrés',
    progPageTitle:'Progression', progPageSub:'Suivez votre parcours module par module',
    overallTitle:'Progression globale', doneLbl:'fait', hrsOf:'/ 29 hrs', courseSession:'Anglais Général · Session 2', onTrack:'En bonne voie 🎯',
    scoresTitle:'Scores des quiz', qGram:'Grammaire de base', qVoc:'Vocabulaire Unité 2', qList:'Compréhension orale',
    modulesTitle:'Progression par module', m1:'Module 1 – Introduction', m2:'Module 2 – Grammaire', m3:'Module 3 – Vocabulaire', m4:'Module 4 – Expression orale', m5:'Module 5 – Compréhension',
    settingsTitle:'Paramètres', profileTitle:'Profil', settingsRole:'Étudiante · Anglais Général S2', lblFullname:'Nom complet', saveBtn:'Enregistrer', prefTitle:'Préférences', prefTxt:'Utilisez le sélecteur de langue dans la barre latérale pour basculer entre le Français et l\'Arabe.',
    badgePending:'En attente', badgeSubmitted:'Soumis', badgeOverdue:'En retard',
    startQuiz:'Commencer le quiz', retakeQuiz:'Refaire', doneLabel:'Complété',
    toastSaved:'Profil mis à jour !',
    qsLabel:'questions', minLabel:'min',
    dueLbl:'Échéance :', subjectLbl:'Matière :',
  },
  ar: {
    topbarTitle: { home:'لوحة التحكم', assignments:'الواجبات', quizzes:'الاختبارات', progress:'التقدم', settings:'الإعدادات' },
    navMain:'الرئيسية', navAccount:'الحساب',
    navHome:'الرئيسية', navAssign:'الواجبات', navQuiz:'الاختبارات', navProg:'التقدم', navSet:'الإعدادات',
    roleLabel:'طالبة', logout:'تسجيل الخروج',
    welcomeMsg:'مرحباً، ', welcomeSub:'لديك 3 واجبات معلقة و2 اختبارات متاحة.',
    stat1:'نسبة الحضور', stat2:'واجبات معلقة', stat3:'اختبارات متاحة', stat4:'واجبات مُسلَّمة',
    courseTag:'الدورة الحالية', courseName:'الإنجليزية العامة – جلسة 2', teacherName:'أ. حسن', schedule:'الإث–الأرب–الجمعة',
    progressLbl:'التقدم', hoursLbl:'20 / 29 ساعة مكتملة',
    qaTitle:'إجراءات سريعة', qaAssign:'الواجبات', qaAssignSub:'3 معلقة', qaQuiz:'الاختبارات', qaQuizSub:'2 متاحة', qaProg:'التقدم', qaSet:'الإعدادات', qaSetSub:'الملف الشخصي',
    activityTitle:'النشاط الأخير',
    act1s:'اختبار مُسلَّم',
    act2s:'واجب قريب الموعد',
    act3s:'اختبار جديد متاح',
    act4s:'تم تصحيح الواجب',
    act5s:'واجب متأخر',
    assignPageTitle:'الواجبات', assignPageSub:'3 معلقة · 1 متأخرة · 2 مُسلَّمة',
    tabAll:'الكل', tabPending:'معلقة', tabDone:'مُسلَّمة',
    tabAllQ:'الكل', tabAvailQ:'متاحة', tabDoneQ:'مكتملة',
    quizPageTitle:'الاختبارات', quizPageSub:'اختبر معلوماتك باختبارات موقوتة',
    progPageTitle:'التقدم', progPageSub:'تابع مسيرتك التعلمية وحدة بوحدة',
    overallTitle:'التقدم الإجمالي', doneLbl:'منجز', hrsOf:'/ 29 ساعة', courseSession:'الإنجليزية العامة · جلسة 2', onTrack:'على المسار الصحيح 🎯',
    scoresTitle:'نتائج الاختبارات', qGram:'القواعد الأساسية', qVoc:'المفردات الوحدة 2', qList:'الفهم الشفهي',
    modulesTitle:'التقدم حسب الوحدة', m1:'الوحدة 1 – مقدمة', m2:'الوحدة 2 – القواعد', m3:'الوحدة 3 – المفردات', m4:'الوحدة 4 – التعبير الشفهي', m5:'الوحدة 5 – الفهم',
    settingsTitle:'الإعدادات', profileTitle:'الملف الشخصي', settingsRole:'طالبة · الإنجليزية العامة ج2', lblFullname:'الاسم الكامل', saveBtn:'حفظ', prefTitle:'التفضيلات', prefTxt:'استخدم محدد اللغة في الشريط الجانبي للتبديل بين الفرنسية والعربية.',
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
  document.getElementById('body').className = lang === 'ar' ? 'ar' : '';
  document.documentElement.setAttribute('lang', lang);
  document.documentElement.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
  document.getElementById('pill-fr').className = 'lang-pill' + (lang === 'fr' ? ' active' : '');
  document.getElementById('pill-ar').className = 'lang-pill' + (lang === 'ar' ? ' active' : '');
  applyTranslations();
  updateEmailPopupLang();
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
  document.getElementById('topbar-title').textContent = tr.topbarTitle[activePage] || tr.topbarTitle.home;
  document.getElementById('nav-main-label').textContent = tr.navMain;
  document.getElementById('nav-account-label').textContent = tr.navAccount;
  document.getElementById('nav-home-lbl').textContent = tr.navHome;
  document.getElementById('nav-assign-lbl').textContent = tr.navAssign;
  document.getElementById('nav-quiz-lbl').textContent = tr.navQuiz;
  document.getElementById('nav-prog-lbl').textContent = tr.navProg;
  document.getElementById('nav-set-lbl').textContent = tr.navSet;
  document.getElementById('role-label').textContent = tr.roleLabel;
  document.getElementById('logout-lbl').textContent = tr.logout;
  document.getElementById('welcome-msg').innerHTML = tr.welcomeMsg + '<span id="welcome-name">' + document.getElementById('sidebar-name').textContent.split(' ')[0] + '</span> 👋';
  document.getElementById('welcome-sub').textContent = tr.welcomeSub;
  document.getElementById('stat1-lbl').textContent = tr.stat1;
  document.getElementById('stat2-lbl').textContent = tr.stat2;
  document.getElementById('stat3-lbl').textContent = tr.stat3;
  document.getElementById('stat4-lbl').textContent = tr.stat4;
  document.getElementById('course-tag').textContent = tr.courseTag;
  document.getElementById('course-name').textContent = tr.courseName;
  document.getElementById('teacher-name-label').textContent = tr.teacherName;
  document.getElementById('schedule-label').textContent = tr.schedule;
  document.getElementById('progress-lbl').textContent = tr.progressLbl;
  document.getElementById('hours-lbl').textContent = tr.hoursLbl;
  document.getElementById('qa-title').textContent = tr.qaTitle;
  document.getElementById('qa-assign').textContent = tr.qaAssign;
  document.getElementById('qa-assign-sub').textContent = tr.qaAssignSub;
  document.getElementById('qa-quiz').textContent = tr.qaQuiz;
  document.getElementById('qa-quiz-sub').textContent = tr.qaQuizSub;
  document.getElementById('qa-prog').textContent = tr.qaProg;
  document.getElementById('qa-set').textContent = tr.qaSet;
  document.getElementById('qa-set-sub').textContent = tr.qaSetSub;
  document.getElementById('activity-title').textContent = tr.activityTitle;














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
  document.getElementById('settings-title').textContent = tr.settingsTitle;
  document.getElementById('profile-title').textContent = tr.profileTitle;
  document.getElementById('settings-role').textContent = tr.settingsRole;
  document.getElementById('lbl-fullname').textContent = tr.lblFullname;
  document.getElementById('save-btn').textContent = tr.saveBtn;
  document.getElementById('pref-title').textContent = tr.prefTitle;
  document.getElementById('pref-txt').textContent = tr.prefTxt;
  renderAssignments();
  renderQuizzes();
  renderActivityFeed();
}

function navigate(page, el) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('page-' + page).classList.add('active');
  if (el) el.classList.add('active');
  activePage = page;
  document.getElementById('topbar-title').textContent = T[currentLang].topbarTitle[page] || T[currentLang].topbarTitle.home;
  if (page === 'assignments') renderAssignments();
  if (page === 'quizzes') renderQuizzes();
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

function saveProfile() {
  const name = document.getElementById('pref-name').value.trim();
  if (!name) return;
  const parts = name.split(' ');
  const init = (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
  ['sidebar-avatar','topbar-avatar','settings-avatar'].forEach(id => { const el = document.getElementById(id); if(el) el.textContent = init; });
  document.getElementById('sidebar-name').textContent = name;
  document.getElementById('settings-name').textContent = name;
  applyTranslations();
  showToast(T[currentLang].toastSaved);
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
  setLang(savedLang);       // sets currentLang, calls applyTranslations
  hydrateLiveData();        // overrides with real DB values (uses currentLang)
  renderAssignments();
  renderQuizzes();
  renderActivityFeed();
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
    const wn = document.getElementById('welcome-name'); if (wn) wn.textContent = fn.split(' ')[0];
    const nm = document.getElementById('settings-name'); if (nm) nm.textContent = fn;
    const pi = document.getElementById('pref-name');    if (pi) pi.value = fn;
  }

  // ── 2. Stat cards ────────────────────────────────────────────────────────
  const attRate = LIVE.att_rate;

  // Card 1 — attendance rate (course progress proxy)
  const s1 = document.getElementById('stat1-val');
  if (s1) s1.textContent = attRate !== null ? attRate + '%' : '–';

  // Card 2 — pending + overdue assignments
  const s2 = document.getElementById('stat2-val');
  if (s2) s2.textContent = (LIVE.pending_count || 0) + (LIVE.overdue_count || 0);

  // Card 4 — submitted assignments (stat3 stays as quiz count = hardcoded for now)
  const s4 = document.getElementById('stat4-val');
  if (s4) s4.textContent = LIVE.submitted_count || 0;

  // Welcome subtitle with real counts
  const ws = document.getElementById('welcome-sub');
  if (ws) {
    const pending = (LIVE.pending_count || 0) + (LIVE.overdue_count || 0);
    if (lang === 'ar') {
      ws.textContent = pending > 0
        ? `لديك ${pending} واجب${pending > 1 ? 'ات' : ''} معلقة`
        : 'لا توجد واجبات معلقة حالياً ✅';
    } else {
      ws.textContent = pending > 0
        ? `Vous avez ${pending} devoir${pending > 1 ? 's' : ''} en attente`
        : 'Aucun devoir en attente pour le moment ✅';
    }
  }

  // ── 3. Course card progress bar ──────────────────────────────────────────
  if (attRate !== null) {
    const pf = document.querySelector('#page-home .progress-fill');
    if (pf) pf.style.width = attRate + '%';
    const pl = document.querySelector('#page-home [style*="color:var(--green)"]');
    if (pl && pl.tagName === 'STRONG') pl.textContent = attRate + '%';
    const hl = document.getElementById('hours-lbl');
    if (hl) hl.textContent = lang === 'ar'
      ? `${LIVE.att_present} / ${LIVE.att_total} جلسة`
      : `${LIVE.att_present} / ${LIVE.att_total} séances`;
  }

  // ── 4. Progress page — circle & labels ──────────────────────────────────
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

  // ── 5. Course name & teacher on progress page ────────────────────────────
  const courseAssigned = document.getElementById('course-assigned-content');
  const courseEmpty    = document.getElementById('course-empty-state');
  if (c) {
    if (courseAssigned) courseAssigned.style.display = '';
    if (courseEmpty)    courseEmpty.style.display    = 'none';

    const courseName = lang === 'ar'
      ? (c.group_name_ar || c.group_name_fr)
      : (c.group_name_fr || c.group_name_ar);

    const cnEl = document.getElementById('course-name');
    if (cnEl) cnEl.textContent = courseName;

    const tn = document.getElementById('teacher-name-label');
    if (tn && c.teacher_name) tn.textContent = (lang === 'ar' ? 'أ. ' : 'Prof. ') + c.teacher_name;

    const cs = document.getElementById('course-session');
    if (cs) cs.textContent = courseName;

    if (c.schedule_json) {
      try {
        const sched = JSON.parse(c.schedule_json);
        if (Array.isArray(sched) && sched.length > 0) {
          const days = sched.map(s => lang === 'ar'
            ? (s.day_ar || s.day_fr) : (s.day_fr || s.day_ar)).join(' – ');
          const sl = document.getElementById('schedule-label');
          if (sl) sl.textContent = days;
        }
      } catch(e) {}
    }

    const sr = document.getElementById('settings-role');
    if (sr) sr.textContent = (lang === 'ar' ? 'طالب · ' : 'Étudiant · ') + courseName;
  } else {
    if (courseAssigned) courseAssigned.style.display = 'none';
    if (courseEmpty)    courseEmpty.style.display    = '';
    const emptyTitle = document.getElementById('course-empty-title');
    const emptySub   = document.getElementById('course-empty-sub');
    if (emptyTitle) emptyTitle.textContent = lang === 'ar'
      ? 'لم يتم تعيين دورة بعد'
      : 'Aucun cours assigné';
    if (emptySub) emptySub.textContent = lang === 'ar'
      ? 'لم يتم إعداد فصلك بعد. سيقوم المشرف بتعيين دورة لك قريباً.'
      : 'Votre classe n\'a pas encore été configurée. L\'administrateur vous assignera un cours bientôt.';
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

  // ── 7. Quick action badges ───────────────────────────────────────────────
  const pending = (LIVE.pending_count || 0) + (LIVE.overdue_count || 0);
  const qa = document.getElementById('qa-assign-sub');
  if (qa) qa.textContent = lang === 'ar'
    ? (pending > 0 ? `${pending} معلقة` : 'لا شيء معلق ✅')
    : (pending > 0 ? `${pending} en attente` : 'Aucun en attente ✅');

  // Progress quick action
  const qp = document.querySelector('#page-home .grid-2 .grid-2 .card:nth-child(3) div:last-child');
  if (qp && attRate !== null) qp.textContent = attRate + '%';

  // ── 8. Nav badge on assignments ──────────────────────────────────────────
  const navBadge = document.querySelector('#nav-assign .nav-badge');
  if (navBadge) navBadge.textContent = pending > 0 ? pending : '';

  // ── 9. Activity feed ─────────────────────────────────────────────────────
  renderActivityFeed();
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
