<?php
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

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ── Auth guard ──
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php?error=auth');
    exit;
}

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

$full_name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? '');
$username  = htmlspecialchars($_SESSION['username'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<title>Upskill – Tableau de bord Professeur</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --navy:#d6eeff; --navy-mid:#ffffff; --navy-light:#eff6ff; --navy-card:#ffffff;
  --green:#10b981; --green-dark:#059669; --green-glow:rgba(16,185,129,0.12); --green-dim:rgba(16,185,129,0.08);
  --white:#1e1b4b; --muted:rgba(30,27,75,0.55); --muted2:rgba(30,27,75,0.40);
  --border:rgba(0,0,0,0.09); --border2:rgba(0,0,0,0.05);
  --yellow:#f59e0b; --red:#ef4444; --blue:#3b82f6; --purple:#a78bfa; --orange:#f59e0b;
  --font:'Sora',sans-serif; --font-body:'DM Sans',sans-serif; --font-ar:'Cairo',sans-serif;
  --sidebar-w:270px;
}
html { scroll-behavior:smooth; }
.skip-link:focus { top:0 !important; outline:3px solid var(--green); }
body { background:var(--navy); color:var(--white); font-family:var(--font-body); min-height:100vh; display:flex; overflow-x:hidden; }
body.ar { font-family:var(--font-ar); direction:rtl; }
body.ar .sidebar { left:auto; right:0; border-right:none; border-left:1px solid var(--border); }
body.ar .main { margin-left:0; margin-right:var(--sidebar-w); }
body.ar .nav-badge { margin-left:0; margin-right:auto; }

/* SIDEBAR */
.sidebar {
  --navy:#2e2a7a; --navy-mid:#3d3890; --navy-light:#4a4499; --navy-card:rgba(255,255,255,0.07);
  --white:#ffffff; --muted:rgba(255,255,255,0.62); --muted2:rgba(255,255,255,0.45);
  --border:rgba(255,255,255,0.1); --border2:rgba(255,255,255,0.06);
  width:var(--sidebar-w); background:var(--navy-mid); border-right:1px solid var(--border);
  display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:200; transition:transform .3s;
}
.sidebar-logo { display:flex; align-items:center; gap:.6rem; padding:1.5rem 1.4rem; border-bottom:1px solid var(--border); }
.sidebar-logo span { font-family:var(--font); font-weight:600; font-size:1rem; }
body.ar .sidebar-logo span { font-family:var(--font-ar); }
.sidebar-logo em { color:var(--green); font-style:normal; }
.sidebar-logo .teacher-chip { margin-left:auto; background:rgba(167,139,250,.15); color:var(--purple); border:1px solid rgba(167,139,250,.3); font-family:var(--font); font-size:.65rem; font-weight:700; padding:.2rem .55rem; border-radius:100px; }
body.ar .sidebar-logo .teacher-chip { margin-left:0; margin-right:auto; }
.lang-toggle { display:flex; gap:.4rem; padding:.6rem 1.4rem; border-bottom:1px solid var(--border); }
.lang-pill { font-size:.7rem; font-family:var(--font); font-weight:600; padding:.25rem .65rem; border-radius:100px; border:1px solid var(--border); color:var(--muted); cursor:pointer; transition:all .2s; }
.lang-pill.active { background:rgba(245,158,11,.2); border-color:rgba(245,158,11,.5); color:#f59e0b; }
.sidebar-user { padding:1.2rem 1.4rem; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:.8rem; }
body.ar .sidebar-user { flex-direction:row-reverse; }
.avatar { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#bfdbfe,#ddd6fe); border:2px solid rgba(245,158,11,.4); display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; position:relative; }
.av-dino { width:100%; height:100%; display:block; }
.av-photo { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius:50%; display:none; }
.user-info .name { font-family:var(--font); font-size:.85rem; font-weight:600; line-height:1.2; }
body.ar .user-info .name { font-family:var(--font-ar); }
.user-info .role-tag { font-size:.72rem; color:var(--purple); background:rgba(167,139,250,.15); padding:.1rem .5rem; border-radius:100px; margin-top:.2rem; display:inline-block; }
.sidebar-nav { flex:1; padding:1rem .8rem; overflow-y:auto; }
.nav-section-label { font-family:var(--font); font-size:.65rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muted2); padding:.5rem .6rem .3rem; margin-top:.5rem; }
body.ar .nav-section-label { letter-spacing:0; text-align:right; font-family:var(--font-ar); }
.nav-item { display:flex; align-items:center; gap:.75rem; padding:.65rem .9rem; border-radius:10px; cursor:pointer; color:var(--muted); font-size:.88rem; font-family:var(--font); font-weight:500; transition:all .2s; margin-bottom:.1rem; }
body.ar .nav-item { flex-direction:row-reverse; font-family:var(--font-ar); }
.nav-item svg { flex-shrink:0; opacity:.7; }
.nav-item:hover { background:rgba(255,255,255,.05); color:var(--white); }
.nav-item.active { background:rgba(245,158,11,.18); color:#f59e0b; border:1px solid rgba(245,158,11,.3); }
.nav-item.active svg { opacity:1; }
.nav-badge { margin-left:auto; background:var(--yellow); color:var(--navy); font-size:.65rem; font-weight:700; padding:.15rem .45rem; border-radius:100px; font-family:var(--font); }
.sidebar-bottom { padding:1rem; border-top:1px solid var(--border); }
.btn-logout { display:flex; align-items:center; gap:.6rem; width:100%; padding:.65rem .9rem; border-radius:10px; background:transparent; border:1px solid var(--border); color:var(--muted); font-family:var(--font); font-size:.85rem; cursor:pointer; transition:all .2s; }
body.ar .btn-logout { flex-direction:row-reverse; font-family:var(--font-ar); }
.btn-logout:hover { border-color:var(--red); color:var(--red); background:rgba(232,93,117,.08); }

/* MAIN */
.main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; background:var(--navy); }
.topbar { background:rgba(214,238,255,0.92); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); padding:1rem 2rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
body.ar .topbar { flex-direction:row-reverse; }
.topbar-title { font-family:var(--font); font-size:1rem; font-weight:600; }
body.ar .topbar-title { font-family:var(--font-ar); }
.topbar-actions { display:flex; align-items:center; gap:.75rem; }
.btn-icon { width:36px; height:36px; border-radius:9px; background:rgba(30,27,75,.06); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--muted); transition:all .2s; }
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

/* STAT */
.stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; margin-bottom:1rem; font-size:1.3rem; }
.stat-icon.purple { background:rgba(167,139,250,.1); }
.stat-icon.green { background:var(--green-dim); }
.stat-icon.yellow { background:rgba(245,197,66,.1); }
.stat-icon.blue { background:rgba(91,156,246,.1); }
.stat-value { font-family:var(--font); font-size:2rem; font-weight:700; letter-spacing:-.03em; margin-bottom:.25rem; }
.stat-label { font-size:.83rem; color:var(--muted); }
body.ar .stat-label { text-align:right; font-family:var(--font-ar); }

/* PROGRESS */
.progress-bar { height:8px; background:rgba(30,27,75,.08); border-radius:100px; overflow:hidden; margin:.5rem 0; }
.progress-fill { height:100%; border-radius:100px; background:var(--green); transition:width .8s; }
.progress-fill.purple { background:var(--purple); }
.progress-fill.yellow { background:var(--yellow); }

/* WELCOME BANNER */
.welcome-banner { background:linear-gradient(135deg,#2e2a7a,#4a4499); border:1px solid rgba(255,255,255,.12); border-radius:20px; padding:2rem 2.5rem; margin-bottom:2rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; position:relative; overflow:hidden; color:#fff; }
body.ar .welcome-banner { flex-direction:row-reverse; }
.welcome-banner::before { content:''; position:absolute; top:-60px; right:-60px; width:200px; height:200px; background:radial-gradient(circle,rgba(245,158,11,.15),transparent 70%); }
.welcome-text h2 { font-family:var(--font); font-size:1.6rem; font-weight:700; letter-spacing:-.03em; margin-bottom:.4rem; color:#fff; }
body.ar .welcome-text h2 { font-family:var(--font-ar); letter-spacing:0; text-align:right; }
.welcome-text h2 span { color:#f59e0b; }
.welcome-text p { color:rgba(255,255,255,.7); font-size:.9rem; }
body.ar .welcome-text p { text-align:right; font-family:var(--font-ar); }

/* STUDENT TABLE */
.student-table { width:100%; border-collapse:collapse; }
.student-table th { font-family:var(--font); font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--muted2); padding:.75rem 1rem; text-align:left; border-bottom:1px solid var(--border); }
body.ar .student-table th { text-align:right; font-family:var(--font-ar); letter-spacing:0; }
.student-table td { padding:.85rem 1rem; border-bottom:1px solid var(--border2); font-size:.88rem; vertical-align:middle; }
body.ar .student-table td { text-align:right; font-family:var(--font-ar); }
.student-table tr:last-child td { border-bottom:none; }
.student-table tr:hover td { background:rgba(59,130,246,.04); }
.student-avatar-sm { width:32px; height:32px; border-radius:50%; background:rgba(59,130,246,.1); border:1.5px solid rgba(59,130,246,.2); display:inline-flex; align-items:center; justify-content:center; font-family:var(--font); font-size:.72rem; font-weight:700; color:var(--blue); }
.badge { display:inline-flex; align-items:center; padding:.2rem .65rem; border-radius:100px; font-size:.72rem; font-weight:700; font-family:var(--font); flex-shrink:0; }
.badge.good { background:rgba(62,207,120,.12); color:var(--green); border:1px solid rgba(62,207,120,.3); }
.badge.warn { background:rgba(245,197,66,.12); color:var(--yellow); border:1px solid rgba(245,197,66,.3); }
.badge.low { background:rgba(232,93,117,.12); color:var(--red); border:1px solid rgba(232,93,117,.3); }

/* ASSIGN TEACHER */
.assign-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 0; border-bottom:1px solid var(--border2); }
body.ar .assign-row { flex-direction:row-reverse; }
.assign-row:last-child { border-bottom:none; }
.assign-info { flex:1; }
.assign-title-t { font-family:var(--font); font-size:.92rem; font-weight:600; margin-bottom:.2rem; }
body.ar .assign-title-t { font-family:var(--font-ar); text-align:right; }
.assign-meta-t { font-size:.78rem; color:var(--muted2); display:flex; gap:1rem; }
body.ar .assign-meta-t { flex-direction:row-reverse; }
.assign-actions { display:flex; gap:.5rem; flex-shrink:0; }

/* BUTTONS */
.btn-primary { background:linear-gradient(135deg,#3b82f6,#7c3aed); color:#fff; font-family:var(--font); font-weight:700; font-size:.88rem; padding:.65rem 1.3rem; border:none; border-radius:10px; cursor:pointer; transition:opacity .2s,transform .15s; display:inline-flex; align-items:center; gap:.4rem; }
body.ar .btn-primary { font-family:var(--font-ar); }
.btn-primary:hover { opacity:.9; transform:translateY(-1px); }
.btn-secondary { background:rgba(30,27,75,.06); color:var(--muted); font-family:var(--font); font-weight:500; font-size:.88rem; padding:.65rem 1.3rem; border:1px solid var(--border); border-radius:10px; cursor:pointer; transition:all .2s; }
body.ar .btn-secondary { font-family:var(--font-ar); }
.btn-secondary:hover { border-color:rgba(30,27,75,.25); color:var(--white); }
.btn-sm { padding:.4rem .9rem; font-size:.78rem; border-radius:8px; }

/* ACTIVITY */
.activity-item { display:flex; gap:1rem; padding:.9rem 0; border-bottom:1px solid var(--border2); }
body.ar .activity-item { flex-direction:row-reverse; }
.activity-item:last-child { border-bottom:none; }
.activity-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:.3rem; }
.activity-dot.green { background:var(--green); }
.activity-dot.yellow { background:var(--yellow); }
.activity-dot.blue { background:var(--blue); }
.activity-dot.purple { background:var(--purple); }
.activity-text { font-size:.86rem; color:var(--muted); line-height:1.5; }
body.ar .activity-text { text-align:right; font-family:var(--font-ar); }
.activity-text strong { color:var(--white); font-weight:500; }
.activity-time { font-size:.75rem; color:var(--muted2); margin-top:.2rem; }
body.ar .activity-time { text-align:right; }

/* TABS */
.tabs { display:flex; gap:.5rem; margin-bottom:1.5rem; border-bottom:1px solid var(--border); }
.tab { padding:.6rem 1rem; font-family:var(--font); font-size:.85rem; font-weight:500; color:var(--muted); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .2s; }
body.ar .tab { font-family:var(--font-ar); }
.tab.active { color:var(--blue); border-bottom-color:var(--blue); }
.tab:hover:not(.active) { color:var(--white); }

/* MODAL */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:1000; display:none; align-items:flex-start; justify-content:center; backdrop-filter:blur(4px); padding:2rem 1rem; overflow-y:auto; }
.modal-overlay.open { display:flex; }
.modal { background:#fff; border:1px solid var(--border); border-radius:20px; padding:2rem; max-width:520px; width:100%; margin:auto; animation:slideUp .25s ease; }
@keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
.modal-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.5rem; gap:1rem; }
body.ar .modal-header { flex-direction:row-reverse; }
.modal-header h3 { font-family:var(--font); font-size:1.1rem; font-weight:700; }
body.ar .modal-header h3 { font-family:var(--font-ar); }
.btn-close { background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.3rem; line-height:1; transition:color .2s; padding:0; }
.btn-close:hover { color:var(--white); }
.form-group { margin-bottom:1.1rem; }
.form-group label { display:block; font-family:var(--font); font-size:.73rem; font-weight:600; color:var(--muted); letter-spacing:.07em; text-transform:uppercase; margin-bottom:.4rem; }
body.ar .form-group label { font-family:var(--font-ar); letter-spacing:0; text-align:right; }
.form-group input, .form-group textarea, .form-group select {
  width:100%; padding:.8rem 1rem; background:rgba(30,27,75,.04); border:1px solid var(--border);
  border-radius:10px; color:var(--white); font-family:var(--font-body); font-size:.9rem;
  outline:none; transition:border-color .2s; resize:vertical;
}
body.ar .form-group input, body.ar .form-group textarea, body.ar .form-group select { font-family:var(--font-ar); text-align:right; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color:var(--blue); background:rgba(59,130,246,.04); }
.form-group select option { background:#fff; }
.modal-footer { display:flex; gap:.75rem; justify-content:flex-end; margin-top:1.5rem; }
body.ar .modal-footer { flex-direction:row-reverse; }

/* TOAST */
.toast { position:fixed; bottom:2rem; right:2rem; background:#fff; border:1px solid var(--border); border-radius:12px; padding:.9rem 1.4rem; font-family:var(--font); font-size:.85rem; color:var(--white); z-index:9999; transform:translateY(100px); opacity:0; transition:all .3s; display:flex; align-items:center; gap:.6rem; }
body.ar .toast { right:auto; left:2rem; font-family:var(--font-ar); }
.toast.show { transform:translateY(0); opacity:1; }
.toast-dot { width:8px; height:8px; border-radius:50%; background:#f59e0b; }

/* ── ATTENDANCE ── */
.att-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; }
body.ar .att-toolbar { flex-direction:row-reverse; }
.att-toolbar-left h2 { font-family:var(--font); font-size:1.4rem; font-weight:700; letter-spacing:-.02em; }
body.ar .att-toolbar-left h2 { font-family:var(--font-ar); }
.att-toolbar-left p  { color:var(--muted); font-size:.85rem; margin-top:.2rem; }
body.ar .att-toolbar-left p { font-family:var(--font-ar); }
.att-actions { display:flex; gap:.6rem; flex-wrap:wrap; }

.att-summary-bar { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
.att-stat { background:var(--navy-card); border:1px solid var(--border); border-radius:14px; padding:1rem 1.2rem; display:flex; align-items:center; gap:.85rem; }
.att-stat-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.att-stat-icon.green  { background:var(--green-dim); }
.att-stat-icon.yellow { background:rgba(245,197,66,.1); }
.att-stat-icon.red    { background:rgba(232,93,117,.1); }
.att-stat-icon.blue   { background:rgba(91,156,246,.1); }
.att-stat-val { font-family:var(--font); font-size:1.4rem; font-weight:700; letter-spacing:-.02em; line-height:1; }
.att-stat-lbl { font-size:.75rem; color:var(--muted); margin-top:.15rem; }
body.ar .att-stat-lbl { font-family:var(--font-ar); }

.att-card { background:var(--navy-card); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
.att-table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.att-table { width:100%; border-collapse:collapse; min-width:900px; }

/* Header rows */
.att-table thead tr:first-child th { background:rgba(245,158,11,.06); border-bottom:1px solid var(--border); }
.att-table thead tr:last-child th  { background:rgba(255,255,255,.02); border-bottom:1px solid rgba(245,158,11,.2); }

.att-th-name { font-family:var(--font); font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--muted2); padding:.75rem 1.2rem; text-align:left; white-space:nowrap; min-width:170px; }
body.ar .att-th-name { text-align:right; font-family:var(--font-ar); letter-spacing:0; }
.att-th-sess { font-family:var(--font); font-size:.65rem; font-weight:700; color:var(--muted2); padding:.5rem .3rem; text-align:center; white-space:nowrap; min-width:28px; }
.att-th-total { font-family:var(--font); font-size:.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--muted2); padding:.5rem 1rem; text-align:center; white-space:nowrap; }
body.ar .att-th-total { font-family:var(--font-ar); letter-spacing:0; }

/* Body rows */
.att-table tbody tr { border-bottom:1px solid var(--border2); transition:background .15s; }
.att-table tbody tr:last-child { border-bottom:none; }
.att-table tbody tr:hover { background:rgba(59,130,246,.04); }

.att-td-name { padding:.85rem 1.2rem; white-space:nowrap; }
body.ar .att-td-name { text-align:right; }
.att-student { display:flex; align-items:center; gap:.7rem; }
body.ar .att-student { flex-direction:row-reverse; }
.att-avatar { width:30px; height:30px; border-radius:50%; background:rgba(59,130,246,.1); border:1.5px solid rgba(59,130,246,.2); display:flex; align-items:center; justify-content:center; font-family:var(--font); font-size:.68rem; font-weight:700; color:var(--blue); flex-shrink:0; }
.att-name { font-family:var(--font); font-size:.88rem; font-weight:600; }
body.ar .att-name { font-family:var(--font-ar); }

.att-td-box { padding:.6rem .3rem; text-align:center; vertical-align:middle; }
.att-box { width:22px; height:22px; border-radius:5px; border:1.5px solid rgba(255,255,255,.18); background:transparent; cursor:pointer; appearance:none; -webkit-appearance:none; display:inline-block; vertical-align:middle; transition:background .15s, border-color .15s, transform .1s; flex-shrink:0; }
.att-box:hover { border-color:var(--green); transform:scale(1.1); }
.att-box:checked { background:var(--green); border-color:var(--green); background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' fill='none'%3E%3Cpath d='M2 6l3 3 5-5' stroke='%230f1d2e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:center; background-size:10px; }
.att-box.absent:checked { background:var(--red); border-color:var(--red); background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' fill='none'%3E%3Cpath d='M9 3L3 9M3 3l6 6' stroke='%23fff' stroke-width='2' stroke-linecap='round'/%3E%3C/svg%3E"); }

.att-td-total { padding:.6rem 1rem; text-align:center; white-space:nowrap; }
.att-pct-wrap { display:flex; flex-direction:column; align-items:center; gap:.25rem; }
.att-pct { font-family:var(--font); font-size:.9rem; font-weight:700; }
.att-pct.high { color:var(--green); }
.att-pct.mid  { color:var(--yellow); }
.att-pct.low  { color:var(--red); }
.att-mini-bar { width:44px; height:5px; background:rgba(30,27,75,.08); border-radius:100px; overflow:hidden; }
.att-mini-fill { height:100%; border-radius:100px; transition:width .4s; }
.att-mini-fill.high { background:var(--green); }
.att-mini-fill.mid  { background:var(--yellow); }
.att-mini-fill.low  { background:var(--red); }

/* Session header labels */
.sess-num-chip { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:rgba(245,158,11,.12); color:#f59e0b; font-family:var(--font); font-size:.62rem; font-weight:700; }

/* Save banner */
.att-save-banner { display:none; align-items:center; justify-content:space-between; background:rgba(62,207,120,.08); border:1px solid rgba(62,207,120,.25); border-radius:12px; padding:.8rem 1.2rem; margin-bottom:1rem; }
body.ar .att-save-banner { flex-direction:row-reverse; }
.att-save-banner.visible { display:flex; }
.att-save-txt { font-size:.85rem; color:var(--green); font-family:var(--font); }
body.ar .att-save-txt { font-family:var(--font-ar); }

@media(max-width:768px){
  .att-summary-bar { grid-template-columns:1fr 1fr; }
}
@media(max-width:480px){
  .att-summary-bar { grid-template-columns:1fr 1fr; }
}

/* ── HAMBURGER ── */
.hamburger { display:none; width:36px; height:36px; border-radius:9px; background:rgba(30,27,75,.06); border:1px solid var(--border); align-items:center; justify-content:center; cursor:pointer; color:var(--muted); flex-shrink:0; }
.hamburger:hover { border-color:var(--blue); color:var(--blue); }
.sidebar-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:199; }
.sidebar-backdrop.open { display:block; }

@media(max-width:768px){
  .hamburger { display:flex; }
  .sidebar{transform:translateX(-100%);}
  body.ar .sidebar{transform:translateX(100%);}
  .sidebar.open{transform:translateX(0)!important;}
  .main{margin-left:0!important;margin-right:0!important;}
  .grid-2,.grid-3,.grid-4{grid-template-columns:1fr 1fr;}
  .page { padding:1rem; }
}
@media(max-width:480px){
  .grid-2,.grid-3,.grid-4{grid-template-columns:1fr;}
}

/* COURSE CARDS */
.course-card { background:var(--navy-card); border:1px solid var(--border); border-radius:18px; padding:1.5rem; cursor:pointer; transition:border-color .2s, transform .15s; display:flex; flex-direction:column; gap:.75rem; }
.course-card:hover { border-color:rgba(245,158,11,.4); transform:translateY(-2px); }
.course-card-header { display:flex; align-items:center; gap:.85rem; }
body.ar .course-card-header { flex-direction:row-reverse; }
.course-icon { width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
.course-icon.c1 { background:rgba(245,158,11,.1); }
.course-icon.c2 { background:var(--green-dim); }
.course-icon.c3 { background:rgba(245,197,66,.1); }
.course-icon.c4 { background:rgba(91,156,246,.1); }
.course-group-name { font-family:var(--font); font-size:1rem; font-weight:700; line-height:1.2; }
body.ar .course-group-name { font-family:var(--font-ar); }
.course-level-tag { display:inline-block; font-size:.68rem; font-weight:700; padding:.15rem .55rem; border-radius:100px; background:rgba(245,158,11,.1); color:#f59e0b; border:1px solid rgba(245,158,11,.25); margin-top:.2rem; }
.course-meta-row { display:flex; flex-wrap:wrap; gap:.5rem 1rem; font-size:.78rem; color:var(--muted); }
body.ar .course-meta-row { flex-direction:row-reverse; }
.course-schedule-chips { display:flex; flex-wrap:wrap; gap:.4rem; }
.schedule-chip { font-size:.72rem; background:rgba(62,207,120,.08); border:1px solid rgba(62,207,120,.2); color:var(--green); padding:.2rem .6rem; border-radius:100px; font-family:var(--font); }
.cd-student-row { display:flex; align-items:center; gap:.75rem; padding:.7rem 0; border-bottom:1px solid var(--border2); }
body.ar .cd-student-row { flex-direction:row-reverse; }
.cd-student-row:last-child { border-bottom:none; }
.cd-sched-row { display:flex; align-items:center; gap:.75rem; padding:.6rem 0; border-bottom:1px solid var(--border2); }
body.ar .cd-sched-row { flex-direction:row-reverse; }
.cd-sched-row:last-child { border-bottom:none; }
.sched-day { font-family:var(--font); font-size:.82rem; font-weight:700; min-width:90px; }
body.ar .sched-day { font-family:var(--font-ar); }
.sched-time { font-size:.8rem; color:var(--muted); }
.sched-room { font-size:.72rem; background:rgba(91,156,246,.1); border:1px solid rgba(91,156,246,.25); color:var(--blue); padding:.15rem .5rem; border-radius:100px; margin-left:auto; }
body.ar .sched-room { margin-left:0; margin-right:auto; }

/* PROFILE MENU */
.profile-menu { position:fixed; top:64px; right:1rem; width:210px; background:#fff; border:1px solid var(--border); border-radius:14px; box-shadow:0 8px 32px rgba(30,27,75,.16); z-index:9001; overflow:hidden; display:none; animation:fadeIn .15s ease; }
body.ar .profile-menu { right:auto; left:1rem; }
.profile-menu.open { display:block; }
.profile-menu-item { display:flex; align-items:center; gap:.75rem; padding:.78rem 1.1rem; font-family:var(--font); font-size:.85rem; font-weight:500; color:var(--white); cursor:pointer; transition:background .15s; border:none; background:none; width:100%; text-align:left; }
body.ar .profile-menu-item { flex-direction:row-reverse; text-align:right; font-family:var(--font-ar); }
.profile-menu-item:hover { background:rgba(30,27,75,.05); }
.profile-menu-item svg { flex-shrink:0; color:var(--muted); }
.profile-menu-item:hover svg { color:var(--white); }
.profile-menu-sep { border:none; border-top:1px solid var(--border); margin:0; }
.profile-menu-item.danger { color:var(--red); }
.profile-menu-item.danger svg { color:var(--red); opacity:.7; }
.profile-menu-item.danger:hover { background:rgba(239,68,68,.06); }
/* AVATAR UPLOAD */
.settings-av-wrap { position:relative; display:inline-block; }
.av-upload-btn { position:absolute; bottom:-3px; right:-3px; width:24px; height:24px; background:#3b82f6; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#fff; border:2px solid var(--navy); transition:background .2s; }
.av-upload-btn:hover { background:#2563eb; }
#mascot-av-img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius:50%; display:none; }
</style>
</head>
<body id="body">
<a href="#main-content" class="skip-link" style="position:absolute;top:-40px;left:0;background:var(--green);color:#0f1d2e;padding:.5rem 1rem;font-family:var(--font);font-weight:700;font-size:.85rem;z-index:9999;border-radius:0 0 8px 0;transition:top .2s;text-decoration:none;">Aller au contenu</a>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Menu principal">
  <div class="sidebar-logo">
    <svg width="26" height="26" viewBox="0 0 28 28" fill="none"><rect width="28" height="28" rx="8" fill="#a78bfa"/><path d="M8 14l4 4 8-8" stroke="#0f1d2e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Up<em style="color:var(--purple)">skill</em></span>
    <div class="teacher-chip" id="teacher-chip-lbl">Prof</div>
  </div>
  <div class="lang-toggle">
    <div class="lang-pill active" id="pill-fr" onclick="setLang('fr')">🇫🇷 FR</div>
    <div class="lang-pill" id="pill-ar" onclick="setLang('ar')">🇲🇦 AR</div>
  </div>
  <div class="sidebar-user">
    <div class="avatar" id="sidebar-avatar"><?= str_replace(['%ID%','%IMGID%'], ['sidebar-dino-svg','sidebar-av-img'], $dinoAvatarSvg) ?></div>
    <div class="user-info">
      <div class="name" id="sidebar-name"><?= $full_name ?></div>
      <div class="role-tag" id="role-label">Professeur</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label" id="nav-main-label">Principal</div>
    <div class="nav-item active" onclick="navigate('home',this)" id="nav-home">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span id="nav-home-lbl">Tableau de bord</span>
    </div>
    <div class="nav-item" onclick="navigate('courses',this)" id="nav-students">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      <span id="nav-students-lbl">Les cours / الدروس</span>
      <span class="nav-badge" aria-hidden="true">3</span>
    </div>
    <div class="nav-item" onclick="navigate('assignments',this)" id="nav-assign">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      <span id="nav-assign-lbl">Devoirs</span>
      <span class="nav-badge" aria-hidden="true">8</span>
    </div>
    <div class="nav-item" onclick="navigate('quizzes',this)" id="nav-quiz">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span id="nav-quiz-lbl">Quiz</span>
    </div>
    <div class="nav-item" onclick="navigate('grades',this)" id="nav-grades">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <span id="nav-grades-lbl">Notes & Résultats</span>
    </div>
    <div class="nav-item" onclick="navigate('attendance',this)" id="nav-attendance">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      <span id="nav-attendance-lbl">Présences</span>
    </div>
    <div class="nav-section-label" id="nav-account-label">Compte</div>
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

<!-- MAIN -->
<main class="main" role="main" id="main-content">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:.75rem;">
      <button class="hamburger" onclick="toggleSidebar()" aria-label="Ouvrir le menu" aria-expanded="false">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="topbar-title" id="topbar-title">Tableau de bord Professeur</div>
    </div>
    <div class="topbar-actions">
      <button class="btn-primary btn-sm" onclick="openAssignModal()" id="btn-new-assign">+ Nouveau devoir</button>
      <div class="btn-icon" title="Notifications">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      </div>
      <div class="avatar" id="topbar-avatar" style="cursor:pointer;" onclick="toggleProfileMenu(event)" title="Mon profil"><?= str_replace(['%ID%','%IMGID%'], ['topbar-dino-svg','topbar-av-img'], $dinoAvatarSvg) ?></div>
    </div>
  </div>

  <!-- HOME PAGE -->
  <div class="page active" id="page-home">
    <div class="welcome-banner">
      <div class="welcome-text">
        <h2 id="welcome-msg">Bonjour, <span id="welcome-name"><?= htmlspecialchars(explode(" ", $full_name)[0]) ?></span> 👋</h2>
        <p id="welcome-sub">8 devoirs à corriger · 3 étudiants en difficulté · Moyenne de classe : 74%</p>
      </div>
      <div style="font-size:3rem;">👨‍🏫</div>
    </div>

    <div class="grid-4" style="margin-bottom:1.5rem;">
      <div class="card"><div class="stat-icon purple"><span aria-hidden="true">👥</span></div><div class="stat-value" id="stat1-val">24</div><div class="stat-label" id="stat1-lbl">Étudiants actifs</div></div>
      <div class="card"><div class="stat-icon yellow"><span aria-hidden="true">📝</span></div><div class="stat-value" id="stat2-val">8</div><div class="stat-label" id="stat2-lbl">Devoirs à corriger</div></div>
      <div class="card"><div class="stat-icon green"><span aria-hidden="true">📊</span></div><div class="stat-value" id="stat3-val">74%</div><div class="stat-label" id="stat3-lbl">Moyenne de la classe</div></div>
      <div class="card"><div class="stat-icon blue"><span aria-hidden="true">🧠</span></div><div class="stat-value" id="stat4-val">5</div><div class="stat-label" id="stat4-lbl">Quiz actifs</div></div>
    </div>

    <div class="grid-2">
      <div>
        <!-- Class Progress -->
        <div class="card" style="margin-bottom:1.5rem;">
          <div class="card-title" id="class-prog-title">Progression de la classe</div>
          <div style="margin-bottom:1rem;">
            <div style="display:flex;justify-content:space-between;font-size:.83rem;color:var(--muted);margin-bottom:.4rem;"><span id="cp-label1">Progression générale</span><span style="color:var(--green);font-family:var(--font);font-weight:700;">68%</span></div>
            <div class="progress-bar"><div class="progress-fill" style="width:68%"></div></div>
          </div>
          <div style="margin-bottom:1rem;">
            <div style="display:flex;justify-content:space-between;font-size:.83rem;color:var(--muted);margin-bottom:.4rem;"><span id="cp-label2">Taux de soumission devoirs</span><span style="color:var(--yellow);font-family:var(--font);font-weight:700;">82%</span></div>
            <div class="progress-bar"><div class="progress-fill yellow" style="width:82%"></div></div>
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;font-size:.83rem;color:var(--muted);margin-bottom:.4rem;"><span id="cp-label3">Moyenne quiz</span><span style="color:var(--purple);font-family:var(--font);font-weight:700;">74%</span></div>
            <div class="progress-bar"><div class="progress-fill purple" style="width:74%"></div></div>
          </div>
        </div>

        <!-- Students needing attention -->
        <div class="card">
          <div class="card-title" id="attention-title">Étudiants à surveiller ⚠️</div>
          <div id="attention-list"></div>
        </div>
      </div>

      <!-- Activity -->
      <div class="card">
        <div class="card-title" id="activity-title">Activité récente</div>
        <div class="activity-item">
          <div class="activity-dot green"></div>
          <div><div class="activity-text"><strong id="act1-s">Devoir soumis</strong> — <span id="act1-t">Amina K. a rendu son devoir</span></div><div class="activity-time" id="act1-time">Aujourd'hui, 11:15</div></div>
        </div>
        <div class="activity-item">
          <div class="activity-dot yellow"></div>
          <div><div class="activity-text"><strong id="act2-s">Devoir en retard</strong> — <span id="act2-t">Karim B. – Exercice écoute #2</span></div><div class="activity-time" id="act2-time">Était dû le 3 mai</div></div>
        </div>
        <div class="activity-item">
          <div class="activity-dot purple"></div>
          <div><div class="activity-text"><strong id="act3-s">Quiz complété</strong> — <span id="act3-t">15 étudiants ont passé Quiz Grammaire</span></div><div class="activity-time" id="act3-time">Hier, 14:30</div></div>
        </div>
        <div class="activity-item">
          <div class="activity-dot blue"></div>
          <div><div class="activity-text"><strong id="act4-s">Nouveau devoir publié</strong> — <span id="act4-t">Présentation orale – Sujet libre</span></div><div class="activity-time" id="act4-time">Il y a 2 jours</div></div>
        </div>
        <div class="activity-item">
          <div class="activity-dot green"></div>
          <div><div class="activity-text"><strong id="act5-s">Note publiée</strong> — <span id="act5-t">Rédaction Email – 22 étudiants notés</span></div><div class="activity-time" id="act5-time">Il y a 3 jours</div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- COURSES PAGE -->
  <div class="page" id="page-courses">
    <div id="courses-list-view">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
          <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="courses-page-title">Les cours / الدروس</h2>
          <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="courses-page-sub">3 groupes · 2024-2025</p>
        </div>
      </div>
      <div class="grid-3" id="courses-grid"></div>
    </div>
    <div id="courses-detail-view" style="display:none;">
      <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        <button class="btn-secondary btn-sm" onclick="closeCourseDetail()" id="btn-back-courses">
          <span id="back-courses-lbl">← Retour aux cours</span>
        </button>
        <div>
          <h2 style="font-family:var(--font);font-size:1.3rem;font-weight:700;" id="course-detail-title">—</h2>
          <p style="color:var(--muted);font-size:.82rem;margin-top:.15rem;" id="course-detail-meta">—</p>
        </div>
      </div>
      <div class="att-summary-bar" style="margin-bottom:1.5rem;">
        <div class="att-stat"><div class="att-stat-icon purple">👥</div>
          <div><div class="att-stat-val" id="cd-stat-students">—</div><div class="att-stat-lbl" id="cd-stat-students-lbl">Étudiants</div></div></div>
        <div class="att-stat"><div class="att-stat-icon green">📊</div>
          <div><div class="att-stat-val" id="cd-stat-avg">—</div><div class="att-stat-lbl" id="cd-stat-avg-lbl">Moyenne</div></div></div>
        <div class="att-stat"><div class="att-stat-icon yellow">📝</div>
          <div><div class="att-stat-val" id="cd-stat-assigns">—</div><div class="att-stat-lbl" id="cd-stat-assigns-lbl">Devoirs actifs</div></div></div>
        <div class="att-stat"><div class="att-stat-icon blue">✅</div>
          <div><div class="att-stat-val" id="cd-stat-att">—</div><div class="att-stat-lbl" id="cd-stat-att-lbl">Présence</div></div></div>
      </div>
      <div class="grid-2">
        <div class="card">
          <div class="card-title" id="cd-students-title">Étudiants du groupe</div>
          <div id="cd-students-list"></div>
        </div>
        <div>
          <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-title" id="cd-schedule-title">Emploi du temps / الجدول</div>
            <div id="cd-schedule-list"></div>
          </div>
          <div class="card">
            <div class="card-title" id="cd-prog-title">Progression du groupe</div>
            <div id="cd-prog-bars"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ASSIGNMENTS PAGE -->
  <div class="page" id="page-assignments">

    <!-- LIST VIEW -->
    <div id="assign-list-view">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
          <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="assign-page-title">Devoirs</h2>
          <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="assign-page-sub">Chargement…</p>
        </div>
        <button class="btn-primary" onclick="openAssignModal()" id="btn-new-assign2">+ <span id="new-assign-lbl">Nouveau devoir</span></button>
      </div>
      <div class="card">
        <div id="assign-teacher-list"><div style="color:var(--muted);font-size:.88rem;padding:.5rem 0;">Chargement…</div></div>
      </div>
    </div>

    <!-- SUBMISSIONS SUB-VIEW -->
    <div id="assign-sub-view" style="display:none;">
      <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        <button class="btn-secondary btn-sm" onclick="closeSubmissions()">← <span id="back-assign-lbl">Retour aux devoirs</span></button>
        <div>
          <h2 style="font-family:var(--font);font-size:1.3rem;font-weight:700;" id="sub-view-title">—</h2>
          <p style="color:var(--muted);font-size:.82rem;margin-top:.15rem;" id="sub-view-meta">—</p>
        </div>
      </div>
      <div class="card">
        <div id="submissions-list"><div style="color:var(--muted);font-size:.88rem;">Chargement…</div></div>
      </div>
    </div>

  </div>

  <!-- QUIZZES PAGE -->
  <div class="page" id="page-quizzes">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="quiz-page-title">Quiz</h2>
        <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="quiz-page-sub">Gérez et créez des quiz pour vos étudiants</p>
      </div>
      <button class="btn-primary" onclick="openModal('quiz')" id="btn-new-quiz">+ <span id="new-quiz-lbl">Créer un quiz</span></button>
    </div>
    <div class="grid-3" id="quiz-teacher-list"></div>
  </div>

  <!-- GRADES PAGE -->
  <div class="page" id="page-grades">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="grades-page-title">Notes & Résultats</h2>
      <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="grades-page-sub">Vue d'ensemble des performances de la classe</p>
    </div>
    <div class="grid-2" style="margin-bottom:1.5rem;">
      <div class="card">
        <div class="card-title" id="dist-title">Distribution des notes</div>
        <div id="grade-dist"></div>
      </div>
      <div class="card">
        <div class="card-title" id="top-title">Top étudiants</div>
        <div id="top-students"></div>
      </div>
    </div>
    <div class="card">
      <div class="card-title" id="quiz-scores-title">Résultats des quiz par étudiant</div>
      <table class="student-table">
        <thead><tr><th id="gth-name">Étudiant</th><th id="gth-q1">Quiz Grammaire</th><th id="gth-q2">Vocabulaire U3</th><th id="gth-q3">Compréhension</th><th id="gth-avg">Moyenne</th></tr></thead>
        <tbody id="grades-tbody"></tbody>
      </table>
    </div>
  </div>

  <!-- SETTINGS PAGE -->
  <div class="page" id="page-settings">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="settings-title">Paramètres</h2>
    </div>
    <div class="grid-2">
      <div class="card">
        <div class="card-title" id="profile-title">Profil professeur</div>
        <div style="display:flex;align-items:center;gap:1.2rem;margin-bottom:1.75rem;">
          <div style="position:relative;flex-shrink:0;">
            <div class="settings-av-wrap" id="settings-av-wrap">
              <div class="avatar" style="width:64px;height:64px;" id="settings-avatar"><?= str_replace(['%ID%','%IMGID%'], ['settings-dino-svg','settings-av-img'], $dinoAvatarSvg) ?></div>
            </div>
            <label for="avatar-input" class="av-upload-btn" title="Changer la photo">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            </label>
            <input type="file" id="avatar-input" accept="image/*" style="display:none;" onchange="handleAvatarUpload(this)">
          </div>
          <div>
            <div style="font-family:var(--font);font-weight:600;font-size:.95rem;color:var(--white);" id="settings-name"><?= $full_name ?></div>
            <div style="color:var(--muted);font-size:.82rem;margin-top:.2rem;" id="settings-role">Professeur · Anglais Général</div>
            <label for="avatar-input" style="display:inline-block;margin-top:.5rem;font-size:.75rem;color:var(--blue);cursor:pointer;font-family:var(--font);font-weight:500;">Changer la photo</label>
          </div>
        </div>
        <div class="form-group">
          <label id="lbl-fullname">Nom complet</label>
          <input type="text" id="pref-name" value="<?= htmlspecialchars($full_name) ?>">
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

<!-- MODAL: NEW ASSIGN -->
<div class="modal-overlay" id="modal-assign">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-assign-title">Nouveau devoir</h3>
      <button class="btn-close" onclick="closeModal('assign')" aria-label="Fermer">✕</button>
    </div>
    <div class="form-group">
      <label id="mlbl-course">Classe <span style="color:var(--red)">*</span></label>
      <select id="new-assign-course" style="width:100%;padding:.8rem 1rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;transition:border-color .2s;">
        <option value="">— Chargement des classes… —</option>
      </select>
    </div>
    <div class="form-group">
      <label id="mlbl-title">Titre du devoir</label>
      <input type="text" id="new-assign-title" placeholder="Ex: Dissertation – Unité 4">
    </div>
    <div class="form-group">
      <label id="mlbl-desc">Description</label>
      <textarea rows="3" id="new-assign-desc" placeholder="Instructions pour les étudiants..."></textarea>
    </div>
    <div class="grid-2" style="gap:1rem;">
      <div class="form-group">
        <label id="mlbl-due">Date limite</label>
        <input type="date" id="new-assign-due">
      </div>
      <div class="form-group">
        <label id="mlbl-subject">Matière</label>
        <select id="new-assign-subject">
          <option>Écriture</option><option>Grammaire</option><option>Vocabulaire</option><option>Expression orale</option><option>Écoute</option>
        </select>
      </div>
    </div>
    <div id="assign-modal-error" style="display:none;color:var(--red);font-size:.82rem;margin-bottom:.5rem;"></div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('assign')" id="modal-cancel">Annuler</button>
      <button class="btn-primary" onclick="submitNewAssign()" id="modal-submit">Publier</button>
    </div>
  </div>
</div>

<!-- MODAL: NEW QUIZ -->
<div class="modal-overlay" id="modal-quiz">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-quiz-title">Créer un quiz</h3>
      <button class="btn-close" onclick="closeModal('quiz')" aria-label="Fermer">✕</button>
    </div>
    <div class="form-group">
      <label id="qmlbl-title">Titre du quiz</label>
      <input type="text" id="new-quiz-title" placeholder="Ex: Grammaire – Unité 5">
    </div>
    <div class="grid-2" style="gap:1rem;">
      <div class="form-group">
        <label id="qmlbl-qs">Nombre de questions</label>
        <input type="number" id="new-quiz-qs" value="10" min="1" max="50">
      </div>
      <div class="form-group">
        <label id="qmlbl-time">Durée (min)</label>
        <input type="number" id="new-quiz-time" value="15" min="5" max="120">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('quiz')" id="quiz-modal-cancel">Annuler</button>
      <button class="btn-primary" onclick="submitNewQuiz()" id="quiz-modal-submit">Créer</button>
    </div>
  </div>
</div>

  <!-- ATTENDANCE PAGE -->
  <div class="page" id="page-attendance">
    <div class="att-toolbar">
      <div class="att-toolbar-left">
        <h2 id="att-page-title">Présences</h2>
        <p id="att-page-sub">Cochez les cases pour marquer la présence à chaque session</p>
      </div>
      <div class="att-actions">
        <button class="btn-secondary btn-sm" onclick="attMarkAll(true)" id="btn-mark-all">✓ <span id="att-mark-all-lbl">Tous présents</span></button>
        <button class="btn-secondary btn-sm" onclick="attMarkAll(false)" id="btn-clear-all">✕ <span id="att-clear-all-lbl">Effacer tout</span></button>
        <button class="btn-primary btn-sm" onclick="attSave()" id="btn-save-att">💾 <span id="att-save-lbl">Enregistrer</span></button>
      </div>
    </div>

    <!-- Summary stats -->
    <div class="att-summary-bar">
      <div class="att-stat">
        <div class="att-stat-icon green">✅</div>
        <div><div class="att-stat-val" id="att-stat-present">—</div><div class="att-stat-lbl" id="att-stat-present-lbl">Présences totales</div></div>
      </div>
      <div class="att-stat">
        <div class="att-stat-icon red">❌</div>
        <div><div class="att-stat-val" id="att-stat-absent">—</div><div class="att-stat-lbl" id="att-stat-absent-lbl">Absences totales</div></div>
      </div>
      <div class="att-stat">
        <div class="att-stat-icon blue">📊</div>
        <div><div class="att-stat-val" id="att-stat-rate">—</div><div class="att-stat-lbl" id="att-stat-rate-lbl">Taux de présence</div></div>
      </div>
      <div class="att-stat">
        <div class="att-stat-icon yellow">⚠️</div>
        <div><div class="att-stat-val" id="att-stat-at-risk">—</div><div class="att-stat-lbl" id="att-stat-at-risk-lbl">Étudiants à risque (&lt;70%)</div></div>
      </div>
    </div>

    <!-- Unsaved changes banner -->
    <div class="att-save-banner" id="att-save-banner">
      <span class="att-save-txt" id="att-unsaved-txt">⚡ Modifications non enregistrées</span>
      <button class="btn-primary btn-sm" onclick="attSave()"><span id="att-save-now-lbl">Enregistrer maintenant</span></button>
    </div>

    <!-- Grid -->
    <div class="att-card">
      <div class="att-table-wrap">
        <table class="att-table" id="att-table">
          <thead>
            <tr>
              <th class="att-th-name" id="att-th-student">Étudiant</th>
              <th class="att-th-sess" colspan="20" style="text-align:center;padding:.6rem 0;font-size:.7rem;color:#f59e0b;letter-spacing:.06em;text-transform:uppercase;" id="att-th-sessions">Sessions (1–20)</th>
              <th class="att-th-total" id="att-th-total">Présence</th>
            </tr>
            <tr id="att-sess-header-row">
              <th class="att-th-name" style="background:transparent;"></th>
              <!-- session number headers injected by JS -->
              <th class="att-th-total" style="background:transparent;"></th>
            </tr>
          </thead>
          <tbody id="att-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>

<!-- SIDEBAR BACKDROP (mobile) -->
<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleSidebar()"></div>

<!-- TOAST -->
<div class="toast" id="toast"><div class="toast-dot"></div><span id="toast-msg"></span></div>

<!-- PROFILE MENU DROPDOWN -->
<div class="profile-menu" id="profile-menu" onclick="event.stopPropagation()">
  <button class="profile-menu-item" onclick="profileMenuAction('name')">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <span id="pm-name-lbl">Changer le prénom</span>
  </button>
  <button class="profile-menu-item" onclick="profileMenuAction('avatar')">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    <span id="pm-avatar-lbl">Changer l'avatar</span>
  </button>
  <hr class="profile-menu-sep">
  <button class="profile-menu-item danger" onclick="profileMenuAction('logout')">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    <span id="pm-logout-lbl">Déconnexion</span>
  </button>
</div>

<script>
/* ── DATA ── */
let STUDENTS = []; // populated from api_students.php on load
const ASSIGNS_T = [
  { id:1, title_fr:'Dissertation : Mes objectifs professionnels', title_ar:'مقال: أهدافي المهنية', due_fr:'9 mai', due_ar:'9 مايو', submitted:18, total:24, subject_fr:'Écriture', subject_ar:'الكتابة' },
  { id:2, title_fr:'Exercice de compréhension orale #3', title_ar:'تمرين الاستماع #3', due_fr:'12 mai', due_ar:'12 مايو', submitted:10, total:24, subject_fr:'Écoute', subject_ar:'الاستماع' },
  { id:3, title_fr:'Présentation orale – Sujet libre', title_ar:'عرض شفهي – موضوع حر', due_fr:'15 mai', due_ar:'15 مايو', submitted:5, total:24, subject_fr:'Expression', subject_ar:'التعبير' },
  { id:4, title_fr:'Rédaction Email professionnel', title_ar:'كتابة بريد إلكتروني مهني', due_fr:'1 mai', due_ar:'1 مايو', submitted:22, total:24, subject_fr:'Écriture', subject_ar:'الكتابة' },
  { id:5, title_fr:'Quiz de grammaire de base', title_ar:'اختبار القواعد الأساسية', due_fr:'28 avril', due_ar:'28 أبريل', submitted:24, total:24, subject_fr:'Grammaire', subject_ar:'القواعد' },
];
const QUIZZES_T = [
  { id:1, title_fr:'Quiz Grammaire Unité 1', title_ar:'اختبار القواعد – الوحدة 1', qs:15, min:20, attempts:22, avg:78 },
  { id:2, title_fr:'Vocabulaire Unité 3', title_ar:'المفردات – الوحدة 3', qs:20, min:25, attempts:18, avg:74 },
  { id:3, title_fr:'Compréhension écrite #2', title_ar:'الفهم القرائي #2', qs:10, min:15, attempts:24, avg:82 },
  { id:4, title_fr:'Grammaire – Temps passés', title_ar:'القواعد – الأزمنة الماضية', qs:12, min:18, attempts:20, avg:71 },
  { id:5, title_fr:'Expression orale – Évaluation', title_ar:'التعبير الشفهي – تقييم', qs:8, min:12, attempts:16, avg:68 },
];

let currentLang = 'fr';
let activePage = 'home';

/* ── TRANSLATIONS ── */
const T = {
  fr: {
    topbarTitle: { home:'Tableau de bord Professeur', students:'Étudiants', courses:'Les cours / الدروس', assignments:'Devoirs', quizzes:'Quiz', grades:'Notes & Résultats', settings:'Paramètres', attendance:'Présences' },
    navMain:'Principal', navAccount:'Compte',
    navHome:'Tableau de bord', navStudents:'Les cours / الدروس', navAssign:'Devoirs', navQuiz:'Quiz', navGrades:'Notes & Résultats', navSet:'Paramètres',
    teacherChip:'Prof', roleLabel:'Professeur', logout:'Déconnexion',
    welcomeMsg:'Bonjour, ', welcomeSub:"8 devoirs à corriger · 3 étudiants en difficulté · Moyenne de classe : 74%",
    stat1:'Étudiants actifs', stat2:'Devoirs à corriger', stat3:'Moyenne de la classe', stat4:'Quiz actifs',
    btnNewAssign:'+ Nouveau devoir', btnNewQuiz:'+ Créer un quiz',
    classProgTitle:'Progression de la classe', cp1:'Progression générale', cp2:'Taux de soumission devoirs', cp3:'Moyenne quiz',
    attentionTitle:'Étudiants à surveiller ⚠️',
    activityTitle:'Activité récente',
    act1s:'Devoir soumis', act1t:"Amina K. a rendu son devoir", act1time:"Aujourd'hui, 11:15",
    act2s:'Devoir en retard', act2t:'Karim B. – Exercice écoute #2', act2time:'Était dû le 3 mai',
    act3s:'Quiz complété', act3t:'15 étudiants ont passé Quiz Grammaire', act3time:'Hier, 14:30',
    act4s:'Nouveau devoir publié', act4t:'Présentation orale – Sujet libre', act4time:'Il y a 2 jours',
    act5s:'Note publiée', act5t:'Rédaction Email – 22 étudiants notés', act5time:'Il y a 3 jours',
    studentsPageTitle:'Étudiants', studentsPageSub:'24 étudiants inscrits · Session 2',
    thName:'Étudiant', thProgress:'Progression', thAssigns:'Devoirs', thAvg:'Moyenne', thStatus:'Statut',
    statusGood:'Bon niveau', statusWarn:'À surveiller', statusLow:'En difficulté',
    assignPageTitle:'Devoirs', assignPageSub:'5 devoirs actifs · 8 soumissions en attente de correction',
    newAssignLbl:'Nouveau devoir',
    quizPageTitle:'Quiz', quizPageSub:'Gérez et créez des quiz pour vos étudiants',
    newQuizLbl:'Créer un quiz',
    gradesPageTitle:'Notes & Résultats', gradesPageSub:"Vue d'ensemble des performances de la classe",
    distTitle:'Distribution des notes', topTitle:'Top étudiants',
    quizScoresTitle:'Résultats des quiz par étudiant',
    gthName:'Étudiant', gthQ1:'Quiz Grammaire', gthQ2:'Vocabulaire U3', gthQ3:'Compréhension', gthAvg:'Moyenne',
    settingsTitle:'Paramètres', profileTitle:'Profil professeur', settingsRole:'Professeur · Anglais Général',
    lblFullname:'Nom complet', saveBtn:'Enregistrer', prefTitle:'Préférences', prefTxt:"Utilisez le sélecteur de langue dans la barre latérale pour basculer entre le Français et l'Arabe.",
    modalAssignTitle:'Nouveau devoir', mlblTitle:'Titre du devoir', mlblDesc:'Description', mlblDue:'Date limite', mlblSubject:'Matière', modalCancel:'Annuler', modalSubmit:'Publier',
    modalQuizTitle:'Créer un quiz', qmlblTitle:'Titre du quiz', qmlblQs:'Nombre de questions', qmlblTime:'Durée (min)', quizCancel:'Annuler', quizSubmit:'Créer',
    toastAssignPublished:'Devoir publié avec succès !', toastQuizCreated:'Quiz créé avec succès !', toastSaved:'Profil mis à jour !',
    toastAttSaved:'Présences enregistrées avec succès !',
    submittedLbl:'soumis sur', viewGradeBtn:'Corriger', attemptsLbl:'tentatives', avgLbl:'moy.',
    subjectLbl:'Matière :', dueLbl:'Échéance :',
    gradeRanges: ['90-100%', '75-89%', '60-74%', '<60%'],
    navAttendance:'Présences',
    topbarAttendance:'Présences',
    attPageTitle:'Présences', attPageSub:'Cochez les cases pour marquer la présence à chaque session',
    attMarkAllLbl:'Tous présents', attClearAllLbl:'Effacer tout', attSaveLbl:'Enregistrer',
    attStatPresentLbl:'Présences totales', attStatAbsentLbl:'Absences totales',
    attStatRateLbl:'Taux de présence', attStatAtRiskLbl:'Étudiants à risque (<70%)',
    attUnsavedTxt:'⚡ Modifications non enregistrées', attSaveNowLbl:'Enregistrer maintenant',
    attThStudent:'Étudiant', attThSessions:'Sessions (1–20)', attThTotal:'Présence',
    attSavingLbl:'Enregistrement…', toastAttError:'Erreur lors de la sauvegarde. Réessayez.',
  },
  ar: {
    topbarTitle: { home:'لوحة تحكم الأستاذ', students:'الطلاب', courses:'الدروس / Les cours', assignments:'الواجبات', quizzes:'الاختبارات', grades:'النتائج والدرجات', settings:'الإعدادات', attendance:'الحضور والغياب' },
    navMain:'الرئيسية', navAccount:'الحساب',
    navHome:'لوحة التحكم', navStudents:'الدروس / Les cours', navAssign:'الواجبات', navQuiz:'الاختبارات', navGrades:'النتائج', navSet:'الإعدادات',
    teacherChip:'أستاذ', roleLabel:'أستاذ', logout:'تسجيل الخروج',
    welcomeMsg:'مرحباً، ', welcomeSub:'8 واجبات للتصحيح · 3 طلاب يحتاجون دعماً · معدل الفصل: 74%',
    stat1:'الطلاب النشطون', stat2:'واجبات للتصحيح', stat3:'معدل الفصل', stat4:'اختبارات نشطة',
    btnNewAssign:'+ واجب جديد', btnNewQuiz:'+ إنشاء اختبار',
    classProgTitle:'تقدم الفصل', cp1:'التقدم العام', cp2:'نسبة تسليم الواجبات', cp3:'معدل الاختبارات',
    attentionTitle:'طلاب يحتاجون متابعة ⚠️',
    activityTitle:'النشاط الأخير',
    act1s:'واجب مُسلَّم', act1t:'أمينة ك. سلّمت واجبها', act1time:'اليوم، 11:15',
    act2s:'واجب متأخر', act2t:'كريم ب. – تمرين الاستماع #2', act2time:'كان موعده 3 مايو',
    act3s:'اختبار مكتمل', act3t:'15 طالباً أجروا اختبار القواعد', act3time:'أمس، 14:30',
    act4s:'واجب جديد نُشر', act4t:'عرض شفهي – موضوع حر', act4time:'منذ يومين',
    act5s:'درجة نُشرت', act5t:'البريد الإلكتروني – 22 طالباً تم تصحيحهم', act5time:'منذ 3 أيام',
    studentsPageTitle:'الطلاب', studentsPageSub:'24 طالباً مسجلاً · الجلسة 2',
    thName:'الطالب', thProgress:'التقدم', thAssigns:'الواجبات', thAvg:'المعدل', thStatus:'الحالة',
    statusGood:'مستوى جيد', statusWarn:'تحت المراقبة', statusLow:'في صعوبة',
    assignPageTitle:'الواجبات', assignPageSub:'5 واجبات نشطة · 8 تسليمات تنتظر التصحيح',
    newAssignLbl:'واجب جديد',
    quizPageTitle:'الاختبارات', quizPageSub:'أدر وأنشئ اختبارات لطلابك',
    newQuizLbl:'إنشاء اختبار',
    gradesPageTitle:'النتائج والدرجات', gradesPageSub:'نظرة عامة على أداء الفصل',
    distTitle:'توزيع الدرجات', topTitle:'أفضل الطلاب',
    quizScoresTitle:'نتائج الاختبارات حسب الطالب',
    gthName:'الطالب', gthQ1:'اختبار القواعد', gthQ2:'المفردات و3', gthQ3:'الفهم', gthAvg:'المعدل',
    settingsTitle:'الإعدادات', profileTitle:'الملف المهني', settingsRole:'أستاذ · الإنجليزية العامة',
    lblFullname:'الاسم الكامل', saveBtn:'حفظ', prefTitle:'التفضيلات', prefTxt:'استخدم محدد اللغة في الشريط الجانبي للتبديل بين الفرنسية والعربية.',
    modalAssignTitle:'واجب جديد', mlblTitle:'عنوان الواجب', mlblDesc:'الوصف', mlblDue:'تاريخ الاستحقاق', mlblSubject:'المادة', modalCancel:'إلغاء', modalSubmit:'نشر',
    modalQuizTitle:'إنشاء اختبار', qmlblTitle:'عنوان الاختبار', qmlblQs:'عدد الأسئلة', qmlblTime:'المدة (دقيقة)', quizCancel:'إلغاء', quizSubmit:'إنشاء',
    toastAssignPublished:'تم نشر الواجب بنجاح!', toastQuizCreated:'تم إنشاء الاختبار بنجاح!', toastSaved:'تم تحديث الملف الشخصي!',
    toastAttSaved:'تم حفظ الحضور بنجاح!',
    submittedLbl:'مُسلَّم من', viewGradeBtn:'تصحيح', attemptsLbl:'محاولة', avgLbl:'مع.',
    subjectLbl:'المادة:', dueLbl:'الموعد:',
    gradeRanges: ['90-100%', '75-89%', '60-74%', '<60%'],
    navAttendance:'الحضور',
    topbarAttendance:'الحضور والغياب',
    attPageTitle:'الحضور والغياب', attPageSub:'ضع علامة في المربعات لتسجيل حضور الطلاب في كل جلسة',
    attMarkAllLbl:'تحضير الكل', attClearAllLbl:'مسح الكل', attSaveLbl:'حفظ',
    attStatPresentLbl:'إجمالي الحضور', attStatAbsentLbl:'إجمالي الغياب',
    attStatRateLbl:'نسبة الحضور', attStatAtRiskLbl:'طلاب في خطر (<70%)',
    attUnsavedTxt:'⚡ تغييرات غير محفوظة', attSaveNowLbl:'حفظ الآن',
    attThStudent:'الطالب', attThSessions:'الجلسات (1–20)', attThTotal:'الحضور',
    attSavingLbl:'جارِ الحفظ…', toastAttError:'خطأ في الحفظ. حاول مجدداً.',
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
}

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebar-backdrop').classList.toggle('open');
}

function applyTranslations() {
  const tr = T[currentLang];
  const set = (id, val) => { const el = document.getElementById(id); if(el) el.textContent = val; };
  set('topbar-title', tr.topbarTitle[activePage] || tr.topbarTitle.home);
  set('teacher-chip-lbl', tr.teacherChip);
  set('nav-main-label', tr.navMain); set('nav-account-label', tr.navAccount);
  set('nav-home-lbl', tr.navHome); set('nav-students-lbl', tr.navStudents);
  set('nav-assign-lbl', tr.navAssign); set('nav-quiz-lbl', tr.navQuiz);
  set('nav-grades-lbl', tr.navGrades); set('nav-set-lbl', tr.navSet);
  set('role-label', tr.roleLabel); set('logout-lbl', tr.logout);
  set('btn-new-assign', tr.btnNewAssign); set('btn-new-assign2', '');
  const btn2 = document.getElementById('btn-new-assign2'); if(btn2) btn2.innerHTML = '+ <span id="new-assign-lbl">' + tr.newAssignLbl + '</span>';
  const btnQ = document.getElementById('btn-new-quiz'); if(btnQ) btnQ.innerHTML = '+ <span id="new-quiz-lbl">' + tr.newQuizLbl + '</span>';
  const wn = document.getElementById('sidebar-name');
  set('welcome-msg', ''); const wm = document.getElementById('welcome-msg');
  if(wm) wm.innerHTML = tr.welcomeMsg + '<span id="welcome-name">' + (wn ? wn.textContent.split(' ')[0] : '') + '</span> 👋';
  set('welcome-sub', tr.welcomeSub);
  set('stat1-lbl', tr.stat1); set('stat2-lbl', tr.stat2); set('stat3-lbl', tr.stat3); set('stat4-lbl', tr.stat4);
  set('class-prog-title', tr.classProgTitle); set('cp-label1', tr.cp1); set('cp-label2', tr.cp2); set('cp-label3', tr.cp3);
  set('attention-title', tr.attentionTitle); set('activity-title', tr.activityTitle);
  set('act1-s', tr.act1s); set('act1-t', tr.act1t); set('act1-time', tr.act1time);
  set('act2-s', tr.act2s); set('act2-t', tr.act2t); set('act2-time', tr.act2time);
  set('act3-s', tr.act3s); set('act3-t', tr.act3t); set('act3-time', tr.act3time);
  set('act4-s', tr.act4s); set('act4-t', tr.act4t); set('act4-time', tr.act4time);
  set('act5-s', tr.act5s); set('act5-t', tr.act5t); set('act5-time', tr.act5time);
  set('students-page-title', tr.studentsPageTitle); set('students-page-sub', tr.studentsPageSub);
  set('th-name', tr.thName); set('th-progress', tr.thProgress); set('th-assigns', tr.thAssigns); set('th-avg', tr.thAvg); set('th-status', tr.thStatus);
  set('assign-page-title', tr.assignPageTitle); set('assign-page-sub', tr.assignPageSub);
  set('quiz-page-title', tr.quizPageTitle); set('quiz-page-sub', tr.quizPageSub);
  set('grades-page-title', tr.gradesPageTitle); set('grades-page-sub', tr.gradesPageSub);
  set('dist-title', tr.distTitle); set('top-title', tr.topTitle); set('quiz-scores-title', tr.quizScoresTitle);
  set('gth-name', tr.gthName); set('gth-q1', tr.gthQ1); set('gth-q2', tr.gthQ2); set('gth-q3', tr.gthQ3); set('gth-avg', tr.gthAvg);
  set('settings-title', tr.settingsTitle); set('profile-title', tr.profileTitle); set('settings-role', tr.settingsRole);
  set('lbl-fullname', tr.lblFullname); set('save-btn', tr.saveBtn); set('pref-title', tr.prefTitle); set('pref-txt', tr.prefTxt);
  set('modal-assign-title', tr.modalAssignTitle); set('mlbl-title', tr.mlblTitle); set('mlbl-desc', tr.mlblDesc); set('mlbl-due', tr.mlblDue); set('mlbl-subject', tr.mlblSubject);
  const lbl = document.getElementById('mlbl-course');
  if (lbl) lbl.innerHTML = (currentLang === 'ar' ? 'الفصل' : 'Classe') + ' <span style="color:var(--red)">*</span>';
  populateCourseSelect(); // repopulate with correct language
  set('modal-cancel', tr.modalCancel); set('modal-submit', tr.modalSubmit);
  set('modal-quiz-title', tr.modalQuizTitle); set('qmlbl-title', tr.qmlblTitle); set('qmlbl-qs', tr.qmlblQs); set('qmlbl-time', tr.qmlblTime);
  set('quiz-modal-cancel', tr.quizCancel); set('quiz-modal-submit', tr.quizSubmit);
  // Attendance
  set('nav-attendance-lbl', tr.navAttendance);
  set('att-page-title', tr.attPageTitle); set('att-page-sub', tr.attPageSub);
  set('att-mark-all-lbl', tr.attMarkAllLbl); set('att-clear-all-lbl', tr.attClearAllLbl); set('att-save-lbl', tr.attSaveLbl);
  set('att-stat-present-lbl', tr.attStatPresentLbl); set('att-stat-absent-lbl', tr.attStatAbsentLbl);
  set('att-stat-rate-lbl', tr.attStatRateLbl); set('att-stat-at-risk-lbl', tr.attStatAtRiskLbl);
  set('att-unsaved-txt', tr.attUnsavedTxt); set('att-save-now-lbl', tr.attSaveNowLbl);
  set('att-th-student', tr.attThStudent); set('att-th-sessions', tr.attThSessions); set('att-th-total', tr.attThTotal);
  renderAttention(); renderAssignments(); renderQuizzes(); renderGrades();
  renderAttendance(); renderCourses();
  if (activeCourse) openCourseDetail(activeCourse.id);
}

function navigate(page, el) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('page-' + page).classList.add('active');
  if(el) el.classList.add('active');
  activePage = page;
  document.getElementById('topbar-title').textContent = T[currentLang].topbarTitle[page] || T[currentLang].topbarTitle.home;
  if (page === 'courses') renderCourses();
  if (window.innerWidth <= 768) {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-backdrop').classList.remove('open');
  }
}

function renderAttention() {
  const list = document.getElementById('attention-list');
  if(!list) return;
  const tr = T[currentLang];
  const atRisk = STUDENTS.filter(s => s.status !== 'good');
  list.innerHTML = atRisk.map(s => `
    <div style="display:flex;align-items:center;gap:.8rem;padding:.75rem 0;border-bottom:1px solid var(--border2);">
      <div class="student-avatar-sm">${s.init}</div>
      <div style="flex:1">
        <div style="font-family:var(--font);font-size:.88rem;font-weight:600;">${s.name}</div>
        <div style="font-size:.75rem;color:var(--muted2);">${tr.thProgress}: ${s.progress}% · ${tr.thAvg}: ${s.avg}%</div>
      </div>
      <span class="badge ${s.status === 'low' ? 'low' : 'warn'}">${s.status === 'low' ? tr.statusLow : tr.statusWarn}</span>
    </div>
  `).join('');
}

function renderStudents() {
  const tbody = document.getElementById('students-tbody');
  if(!tbody) return;
  const tr = T[currentLang];
  tbody.innerHTML = STUDENTS.map(s => `
    <tr>
      <td><div style="display:flex;align-items:center;gap:.75rem;"><div class="student-avatar-sm">${s.init}</div><span>${s.name}</span></div></td>
      <td>
        <div style="display:flex;align-items:center;gap:.75rem;">
          <div class="progress-bar" style="flex:1;min-width:80px;"><div class="progress-fill" style="width:${s.progress}%;background:${s.progress>=75?'var(--green)':s.progress>=55?'var(--yellow)':'var(--red)'}"></div></div>
          <span style="font-family:var(--font);font-size:.82rem;font-weight:600;color:${s.progress>=75?'var(--green)':s.progress>=55?'var(--yellow)':'var(--red)'}">${s.progress}%</span>
        </div>
      </td>
      <td>${s.assigns}/5</td>
      <td style="font-family:var(--font);font-weight:700;color:${s.avg>=75?'var(--green)':s.avg>=55?'var(--yellow)':'var(--red)'}">${s.avg}%</td>
      <td><span class="badge ${s.status === 'good' ? 'good' : s.status === 'warn' ? 'warn' : 'low'}">${s.status === 'good' ? tr.statusGood : s.status === 'warn' ? tr.statusWarn : tr.statusLow}</span></td>
    </tr>
  `).join('');
}

/* ══════════════════════════════════════════════════════
   ASSIGNMENTS — live API
══════════════════════════════════════════════════════ */
let ASSIGNMENTS_LIVE = [];
const _csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

async function loadAssignments() {
  try {
    const res  = await fetch('api_assignments.php?action=list');
    const data = await res.json();
    if (data.ok && Array.isArray(data.assignments)) ASSIGNMENTS_LIVE = data.assignments;
  } catch(e) { console.warn('Could not load assignments:', e); }
  renderAssignments();
}

function renderAssignments() {
  const list = document.getElementById('assign-teacher-list');
  if (!list) return;
  const tr   = T[currentLang];
  const lang = currentLang;

  if (ASSIGNMENTS_LIVE.length === 0) {
    list.innerHTML = `<div style="color:var(--muted);font-size:.88rem;padding:1rem 0;text-align:center;">${lang==='ar'?'لا توجد واجبات بعد.':'Aucun devoir pour le moment.'}</div>`;
    const sub = document.getElementById('assign-page-sub'); if (sub) sub.textContent = lang==='ar'?'لا توجد واجبات':'Aucun devoir';
    return;
  }

  const pending = ASSIGNMENTS_LIVE.reduce((s,a) => s + Math.max(0,(parseInt(a.submitted_count)||0)-(parseInt(a.graded_count)||0)), 0);
  const sub = document.getElementById('assign-page-sub');
  if (sub) sub.textContent = lang==='ar'
    ? `${ASSIGNMENTS_LIVE.length} واجبات · ${pending>0?pending+' تسليمات تنتظر':'جميعها مصححة ✅'}`
    : `${ASSIGNMENTS_LIVE.length} devoir(s) · ${pending>0?pending+' à corriger':'Tout corrigé ✅'}`;
  const s2 = document.getElementById('stat2-val'); if (s2) s2.textContent = pending;

  list.innerHTML = ASSIGNMENTS_LIVE.map(a => {
    const title     = lang==='ar' ? (a.title_ar||a.title_fr) : (a.title_fr||a.title_ar);
    const subject   = lang==='ar' ? (a.subject_ar||a.subject_fr||'') : (a.subject_fr||a.subject_ar||'');
    const className = lang==='ar' ? (a.group_name_ar||a.group_name_fr||'') : (a.group_name_fr||a.group_name_ar||'');
    const submitted = parseInt(a.submitted_count)||0;
    const total     = parseInt(a.total_students)||0;
    const graded    = parseInt(a.graded_count)||0;
    const ungraded  = submitted - graded;
    const dueStr    = a.due_date ? new Date(a.due_date).toLocaleDateString(lang==='ar'?'ar-MA':'fr-FR',{day:'numeric',month:'short'}) : '—';
    const pendingBadge = ungraded > 0
      ? `<span style="background:rgba(245,197,66,.15);color:var(--yellow);border:1px solid rgba(245,197,66,.3);font-size:.7rem;font-weight:700;padding:.15rem .5rem;border-radius:100px;font-family:var(--font);">${ungraded} ${lang==='ar'?'تنتظر':'à corriger'}</span>`
      : `<span style="background:rgba(62,207,120,.1);color:var(--green);border:1px solid rgba(62,207,120,.25);font-size:.7rem;font-weight:700;padding:.15rem .5rem;border-radius:100px;font-family:var(--font);">✓ ${lang==='ar'?'مصحح':'Corrigé'}</span>`;
    return `<div class="assign-row">
      <div class="assign-info">
        <div class="assign-title-t">${escHtml(title)} ${pendingBadge}</div>
        <div class="assign-meta-t">
          ${className ? `<span>👥 ${escHtml(className)}</span>` : ''}
          ${dueStr!=='—' ? `<span>📅 ${tr.dueLbl} ${dueStr}</span>` : ''}
          ${subject   ? `<span>📚 ${tr.subjectLbl} ${escHtml(subject)}</span>` : ''}
          <span style="color:${submitted===0?'var(--muted)':submitted/Math.max(total,1)>.7?'var(--green)':'var(--yellow)'}">
            📨 ${submitted}${total?'/'+total:''} ${lang==='ar'?'تسليم':'soumis'}
          </span>
        </div>
      </div>
      <div class="assign-actions">
        <button class="btn-primary btn-sm" onclick="openSubmissions(${a.id})">${tr.viewGradeBtn}</button>
        <button class="btn-secondary btn-sm" onclick="deleteAssignment(${a.id})" style="color:var(--red);border-color:rgba(232,93,117,.3);"
          title="${lang==='ar'?'حذف':'Supprimer'}">🗑</button>
      </div>
    </div>`;
  }).join('');
}

async function deleteAssignment(id) {
  if (!confirm(currentLang==='ar'?'حذف هذا الواجب؟':'Supprimer ce devoir ?')) return;
  try {
    const res  = await fetch('api_assignments.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':_csrfToken}, body:JSON.stringify({action:'delete',id}) });
    const data = await res.json();
    if (data.ok) { await loadAssignments(); showToast(currentLang==='ar'?'تم الحذف':'Devoir supprimé'); }
    else showToast('❌ '+(data.error||'Erreur'));
  } catch(e) { showToast('❌ Erreur réseau'); }
}

/* ── Submissions sub-view ── */
async function openSubmissions(assignId) {
  const listView = document.getElementById('assign-list-view');
  const subView  = document.getElementById('assign-sub-view');
  if (!subView) { showToast('Sous-vue introuvable'); return; }
  if (listView) listView.style.display = 'none';
  subView.style.display = 'block';
  const titleEl = document.getElementById('sub-view-title');
  const metaEl  = document.getElementById('sub-view-meta');
  const subList = document.getElementById('submissions-list');
  if (titleEl) titleEl.textContent = '…';
  if (subList) subList.innerHTML = `<div style="color:var(--muted);padding:1rem;">${currentLang==='ar'?'جارِ التحميل…':'Chargement…'}</div>`;
  try {
    const res  = await fetch(`api_assignments.php?action=submissions&id=${assignId}`);
    const data = await res.json();
    if (!data.ok) { if(subList) subList.innerHTML = '<div style="color:var(--red);">Erreur</div>'; return; }
    const a    = data.assignment;
    const lang = currentLang;
    const title = lang==='ar' ? (a.title_ar||a.title_fr) : (a.title_fr||a.title_ar);
    const cn    = lang==='ar' ? (a.group_name_ar||a.group_name_fr||'') : (a.group_name_fr||a.group_name_ar||'');
    if (titleEl) titleEl.textContent = title;
    if (metaEl)  metaEl.textContent  = cn + (a.due_date ? ' · ' + (lang==='ar'?'الموعد: ':'Échéance : ') + new Date(a.due_date).toLocaleDateString(lang==='ar'?'ar-MA':'fr-FR',{day:'numeric',month:'long',year:'numeric'}) : '');
    const backLbl = document.getElementById('back-assign-lbl');
    if (backLbl) backLbl.textContent = lang==='ar'?'→ العودة':'← Retour aux devoirs';
    renderSubmissions(data.submissions);
  } catch(e) { if(subList) subList.innerHTML = '<div style="color:var(--red);">Erreur réseau</div>'; }
}

function renderSubmissions(submissions) {
  const subList = document.getElementById('submissions-list');
  if (!subList) return;
  const lang = currentLang;
  if (!submissions.length) {
    subList.innerHTML = `<div style="color:var(--muted);text-align:center;padding:2rem;">${lang==='ar'?'لا توجد تسليمات.':'Aucune soumission pour le moment.'}</div>`;
    return;
  }
  subList.innerHTML = submissions.map(s => {
    const date = new Date(s.submitted_at).toLocaleDateString(lang==='ar'?'ar-MA':'fr-FR',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});
    const scoreBadge = s.score!=null ? `<span style="background:rgba(167,139,250,.12);color:var(--purple);border:1px solid rgba(167,139,250,.3);font-size:.75rem;font-weight:700;padding:.2rem .7rem;border-radius:100px;font-family:var(--font);">📊 ${s.score}/100</span>` : '';
    const fbBlock = s.teacher_comment ? `<div style="margin-top:.4rem;padding:.5rem .75rem;background:rgba(167,139,250,.06);border:1px solid rgba(167,139,250,.2);border-radius:8px;font-size:.8rem;"><strong style="color:var(--purple);font-size:.72rem;display:block;margin-bottom:.2rem;">${lang==='ar'?'تعليقك:':'Votre commentaire :'}</strong>${escHtml(s.teacher_comment)}</div>` : '';
    return `<div style="display:flex;align-items:flex-start;gap:.85rem;padding:1.1rem 0;border-bottom:1px solid var(--border2);" id="sub-row-${s.id}">
      <div style="width:32px;height:32px;border-radius:50%;background:rgba(167,139,250,.15);border:1.5px solid rgba(167,139,250,.35);display:inline-flex;align-items:center;justify-content:center;font-family:var(--font);font-size:.72rem;font-weight:700;color:var(--purple);flex-shrink:0;">${escHtml(s.initials)}</div>
      <div style="flex:1;min-width:0;">
        <div style="font-family:var(--font);font-size:.9rem;font-weight:600;margin-bottom:.2rem;">${escHtml(s.student_name||s.username)} ${scoreBadge}</div>
        ${s.comment ? `<div style="font-size:.82rem;color:var(--muted);line-height:1.5;margin-bottom:.4rem;white-space:pre-wrap;">${escHtml(s.comment)}</div>` : `<div style="font-size:.82rem;color:var(--muted2);font-style:italic;margin-bottom:.4rem;">${lang==='ar'?'(لا نص)':'(Pas de texte)'}</div>`}
        <div style="font-size:.74rem;color:var(--muted2);">📅 ${date}</div>
        ${fbBlock}
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.65rem;align-items:center;">
          <input type="number" min="0" max="100" id="score-${s.id}" placeholder="${lang==='ar'?'/100':'Note /100'}" value="${s.score!=null?s.score:''}"
            style="width:80px;padding:.4rem .6rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:8px;color:var(--white);font-family:var(--font);font-size:.85rem;text-align:center;outline:none;">
          <input type="text" id="comment-${s.id}" placeholder="${lang==='ar'?'تعليق للطالب…':'Commentaire pour l\'étudiant…'}" value="${escHtml(s.teacher_comment||'')}"
            style="flex:1;min-width:160px;padding:.4rem .7rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:8px;color:var(--white);font-family:var(--font-body);font-size:.83rem;outline:none;">
          <button class="btn-primary btn-sm" onclick="gradeSubmission(${s.id})">${lang==='ar'?'حفظ':'Enregistrer'}</button>
        </div>
      </div>
    </div>`;
  }).join('');
}

function closeSubmissions() {
  const subView  = document.getElementById('assign-sub-view');
  const listView = document.getElementById('assign-list-view');
  if (subView)  subView.style.display  = 'none';
  if (listView) listView.style.display = 'block';
}

async function gradeSubmission(subId) {
  const score   = document.getElementById('score-'  +subId)?.value.trim();
  const comment = document.getElementById('comment-'+subId)?.value.trim();
  const btn = event.target; const orig = btn.textContent;
  btn.textContent = '…'; btn.disabled = true;
  try {
    const res  = await fetch('api_assignments.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':_csrfToken}, body:JSON.stringify({action:'grade',submission_id:subId,score:score!==''?parseInt(score):null,teacher_comment:comment}) });
    const data = await res.json();
    if (data.ok) {
      showToast(currentLang==='ar'?'✅ تم الحفظ':'✅ Correction enregistrée');
      // Update badge inline
      const row = document.getElementById('sub-row-'+subId);
      if (row && score!=='') { let b=row.querySelector('span[style*="var(--purple)"]'); if(!b){b=document.createElement('span');b.style.cssText='background:rgba(167,139,250,.12);color:var(--purple);border:1px solid rgba(167,139,250,.3);font-size:.75rem;font-weight:700;padding:.2rem .7rem;border-radius:100px;';row.querySelector('div[style*="font-weight:600"]').appendChild(b);} b.textContent='📊 '+score+'/100'; }
    } else showToast('❌ '+(data.error||'Erreur'));
  } catch(e) { showToast('❌ Erreur réseau'); }
  finally { btn.textContent=orig; btn.disabled=false; }
}

function renderQuizzes() {
  const list = document.getElementById('quiz-teacher-list');
  if(!list) return;
  const tr = T[currentLang];
  list.innerHTML = QUIZZES_T.map(q => `
    <div class="card" style="display:flex;flex-direction:column;">
      <div style="font-size:2rem;margin-bottom:.8rem;">🧠</div>
      <div style="font-family:var(--font);font-size:1rem;font-weight:700;margin-bottom:.4rem;">${currentLang === 'ar' ? q.title_ar : q.title_fr}</div>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;">
        <span style="font-size:.75rem;background:rgba(30,27,75,.06);border:1px solid var(--border);padding:.2rem .6rem;border-radius:100px;color:var(--muted);">📝 ${q.qs} q</span>
        <span style="font-size:.75rem;background:rgba(30,27,75,.06);border:1px solid var(--border);padding:.2rem .6rem;border-radius:100px;color:var(--muted);">⏱ ${q.min} min</span>
      </div>
      <div style="font-size:.83rem;color:var(--muted);margin-bottom:.4rem;">${q.attempts} ${tr.attemptsLbl} · ${tr.avgLbl} <strong style="color:var(--purple)">${q.avg}%</strong></div>
      <div class="progress-bar"><div class="progress-fill purple" style="width:${q.avg}%"></div></div>
    </div>
  `).join('');
}

function renderGrades() {
  const tbody = document.getElementById('grades-tbody');
  if(!tbody) return;
  const scores = [
    [85,78,82], [70,65,72], [92,88,95], [55,48,60], [78,82,75], [45,50,42], [89,91,87], [74,70,78]
  ];
  tbody.innerHTML = STUDENTS.map((s,i) => {
    const sc = scores[i]; const avg = Math.round(sc.reduce((a,b)=>a+b,0)/sc.length);
    return `<tr>
      <td><div style="display:flex;align-items:center;gap:.75rem;"><div class="student-avatar-sm">${s.init}</div><span>${s.name}</span></div></td>
      ${sc.map(v => `<td style="font-family:var(--font);font-weight:600;color:${v>=75?'var(--green)':v>=55?'var(--yellow)':'var(--red)'}">${v}%</td>`).join('')}
      <td style="font-family:var(--font);font-weight:700;color:${avg>=75?'var(--green)':avg>=55?'var(--yellow)':'var(--red)'}">${avg}%</td>
    </tr>`;
  }).join('');

  const dist = document.getElementById('grade-dist');
  if(dist) {
    const ranges = [[90,100],[75,89],[60,74],[0,59]];
    const colors = ['var(--green)','var(--blue)','var(--yellow)','var(--red)'];
    const labels = T[currentLang].gradeRanges;
    dist.innerHTML = ranges.map((r,i) => {
      const count = STUDENTS.filter(s=>s.avg>=r[0]&&s.avg<=r[1]).length;
      const pct = Math.round(count/STUDENTS.length*100);
      return `<div style="display:flex;align-items:center;gap:1rem;margin-bottom:.8rem;">
        <div style="font-size:.78rem;color:var(--muted);min-width:55px;font-family:var(--font);">${labels[i]}</div>
        <div class="progress-bar" style="flex:1"><div class="progress-fill" style="width:${pct}%;background:${colors[i]}"></div></div>
        <div style="font-family:var(--font);font-size:.82rem;font-weight:600;min-width:40px;text-align:right;color:${colors[i]}">${count} ét.</div>
      </div>`;
    }).join('');
  }

  const top = document.getElementById('top-students');
  if(top) {
    const sorted = [...STUDENTS].sort((a,b)=>b.avg-a.avg).slice(0,3);
    const medals = ['🥇','🥈','🥉'];
    top.innerHTML = sorted.map((s,i) => `
      <div style="display:flex;align-items:center;gap:.8rem;padding:.75rem 0;border-bottom:1px solid var(--border2);">
        <div style="font-size:1.4rem;">${medals[i]}</div>
        <div class="student-avatar-sm">${s.init}</div>
        <div style="flex:1;font-family:var(--font);font-size:.88rem;font-weight:600;">${s.name}</div>
        <div style="font-family:var(--font);font-size:1rem;font-weight:700;color:var(--green);">${s.avg}%</div>
      </div>
    `).join('');
  }
}

function openModal(type) {
  document.getElementById('modal-' + type).classList.add('open');
}
function closeModal(type) {
  document.getElementById('modal-' + type).classList.remove('open');
}

/* ── Teacher course selector ── */
let TEACHER_COURSES = [];

async function loadTeacherCourses() {
  try {
    const res  = await fetch('api_assignments.php?action=my_courses');
    const data = await res.json();
    if (data.ok && Array.isArray(data.courses)) {
      TEACHER_COURSES = data.courses;
    }
  } catch(e) { console.warn('Could not load teacher courses:', e); }
}

function populateCourseSelect() {
  const sel = document.getElementById('new-assign-course');
  if (!sel) return;
  const lang = typeof currentLang !== 'undefined' ? currentLang : 'fr';
  sel.innerHTML = TEACHER_COURSES.length === 0
    ? `<option value="">${lang === 'ar' ? '— لا توجد فصول مسندة —' : '— Aucune classe assignée —'}</option>`
    : `<option value="">${lang === 'ar' ? '— اختر الفصل —' : '— Choisir la classe —'}</option>`
      + TEACHER_COURSES.map(c => {
          const name = lang === 'ar'
            ? (c.group_name_ar || c.group_name_fr)
            : (c.group_name_fr || c.group_name_ar);
          const subject = lang === 'ar'
            ? (c.subject_ar || c.subject_fr)
            : (c.subject_fr || c.subject_ar);
          return `<option value="${c.id}">${name}${subject ? ' – ' + subject : ''}${c.level ? ' ('+c.level+')' : ''}</option>`;
        }).join('');
}

async function openAssignModal() {
  // Reload courses each time in case assignments changed
  if (TEACHER_COURSES.length === 0) await loadTeacherCourses();
  populateCourseSelect();
  // Update label language
  const lang = typeof currentLang !== 'undefined' ? currentLang : 'fr';
  const lbl = document.getElementById('mlbl-course');
  if (lbl) lbl.innerHTML = (lang === 'ar' ? 'الفصل' : 'Classe') + ' <span style="color:var(--red)">*</span>';
  const errEl = document.getElementById('assign-modal-error');
  if (errEl) errEl.style.display = 'none';
  openModal('assign');
}

async function submitNewAssign() {
  const courseId = document.getElementById('new-assign-course')?.value;
  const title    = document.getElementById('new-assign-title').value.trim();
  const desc     = document.getElementById('new-assign-desc').value.trim();
  const due      = document.getElementById('new-assign-due').value;
  const subject  = document.getElementById('new-assign-subject').value;
  const errEl    = document.getElementById('assign-modal-error');
  const btn      = document.getElementById('modal-submit');
  const lang     = typeof currentLang !== 'undefined' ? currentLang : 'fr';

  // Validation
  if (!courseId) {
    if (errEl) { errEl.textContent = lang === 'ar' ? 'يرجى اختيار الفصل.' : 'Veuillez choisir une classe.'; errEl.style.display = ''; }
    return;
  }
  if (!title) {
    if (errEl) { errEl.textContent = lang === 'ar' ? 'العنوان مطلوب.' : 'Le titre est requis.'; errEl.style.display = ''; }
    return;
  }
  if (errEl) errEl.style.display = 'none';

  const origText = btn ? btn.textContent : '';
  if (btn) { btn.textContent = '…'; btn.disabled = true; }

  try {
    const res = await fetch('api_assignments.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrfToken },
      body: JSON.stringify({
        action:      'create',
        course_id:   parseInt(courseId),
        title,
        description: desc,
        due_date:    due,
        subject,
      })
    });
    const data = await res.json();
    if (data.ok) {
      closeModal('assign');
      document.getElementById('new-assign-title').value = '';
      document.getElementById('new-assign-desc').value  = '';
      document.getElementById('new-assign-due').value   = '';
      if (errEl) errEl.style.display = 'none';
      // Reload assignments list if it exists
      if (typeof loadAssignments === 'function') await loadAssignments();
      showToast(T[lang].toastAssignPublished);
    } else {
      if (errEl) { errEl.textContent = '❌ ' + (data.error || 'Erreur'); errEl.style.display = ''; }
      else showToast('❌ ' + (data.error || 'Erreur'));
    }
  } catch(e) {
    if (errEl) { errEl.textContent = '❌ Erreur réseau'; errEl.style.display = ''; }
  } finally {
    if (btn) { btn.textContent = origText; btn.disabled = false; }
  }
}

function submitNewQuiz() {
  const title = document.getElementById('new-quiz-title').value.trim();
  if(!title) return;
  closeModal('quiz');
  document.getElementById('new-quiz-title').value = '';
  showToast(T[currentLang].toastQuizCreated);
}

function saveProfile() {
  const name = document.getElementById('pref-name').value.trim();
  if(!name) return;
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

/* ── ATTENDANCE ── */
const ATT_SESSIONS = 20;
const attData = {};
let attDirty = false;
let attSaving = false;

async function initAttData() {
  STUDENTS.forEach(s => {
    attData[s.id] = {};
    for (let i = 1; i <= ATT_SESSIONS; i++) attData[s.id][i] = false;
  });
  try {
    const res  = await fetch('load_attendance.php');
    const json = await res.json();
    if (json.ok && json.attendance) {
      for (const [sid, sessions] of Object.entries(json.attendance)) {
        const id = parseInt(sid);
        if (!attData[id]) attData[id] = {};
        for (const [sess, present] of Object.entries(sessions)) {
          attData[id][parseInt(sess)] = !!present;
        }
      }
    }
  } catch (e) {
    console.warn('Could not load attendance from server:', e);
  }
}

function renderAttendance() {
  const tbody = document.getElementById('att-tbody');
  const sessHeaderRow = document.getElementById('att-sess-header-row');
  if (!tbody || !sessHeaderRow) return;

  // Session header numbers
  let headerCells = '';
  for (let i = 1; i <= ATT_SESSIONS; i++) {
    headerCells += `<th class="att-th-sess"><span class="sess-num-chip">${i}</span></th>`;
  }
  sessHeaderRow.innerHTML = `<th class="att-th-name" style="background:transparent;"></th>${headerCells}<th class="att-th-total" style="background:transparent;"></th>`;

  // Student rows
  tbody.innerHTML = STUDENTS.map(s => {
    const data = attData[s.id] || {};
    let boxes = '';
    for (let i = 1; i <= ATT_SESSIONS; i++) {
      const checked = data[i] ? 'checked' : '';
      boxes += `<td class="att-td-box"><input type="checkbox" class="att-box" data-sid="${s.id}" data-sess="${i}" ${checked} onchange="attToggle(this)"></td>`;
    }
    const { pct, cls } = attPct(s.id);
    return `<tr>
      <td class="att-td-name">
        <div class="att-student">
          <div class="att-avatar">${s.init}</div>
          <span class="att-name">${s.name}</span>
        </div>
      </td>
      ${boxes}
      <td class="att-td-total">
        <div class="att-pct-wrap">
          <span class="att-pct ${cls}" id="att-pct-${s.id}">${pct}%</span>
          <div class="att-mini-bar"><div class="att-mini-fill ${cls}" id="att-bar-${s.id}" style="width:${pct}%"></div></div>
        </div>
      </td>
    </tr>`;
  }).join('');

  attUpdateStats();
}

function attPct(sid) {
  const data = attData[sid] || {};
  const present = Object.values(data).filter(Boolean).length;
  const pct = Math.round((present / ATT_SESSIONS) * 100);
  const cls = pct >= 80 ? 'high' : pct >= 60 ? 'mid' : 'low';
  return { pct, cls, present };
}

function attToggle(el) {
  const sid = parseInt(el.dataset.sid);
  const sess = parseInt(el.dataset.sess);
  attData[sid][sess] = el.checked;
  // Update that student's percentage live
  const { pct, cls } = attPct(sid);
  const pctEl = document.getElementById('att-pct-' + sid);
  const barEl = document.getElementById('att-bar-' + sid);
  if (pctEl) { pctEl.textContent = pct + '%'; pctEl.className = 'att-pct ' + cls; }
  if (barEl) { barEl.style.width = pct + '%'; barEl.className = 'att-mini-fill ' + cls; }
  attUpdateStats();
  if (!attDirty) {
    attDirty = true;
    document.getElementById('att-save-banner').classList.add('visible');
  }
}

function attUpdateStats() {
  let totalPresent = 0, totalCells = STUDENTS.length * ATT_SESSIONS, atRisk = 0;
  STUDENTS.forEach(s => {
    const { present, pct } = attPct(s.id);
    totalPresent += present;
    if (pct < 70) atRisk++;
  });
  const totalAbsent = totalCells - totalPresent;
  const rate = Math.round((totalPresent / totalCells) * 100);
  const s = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  s('att-stat-present', totalPresent);
  s('att-stat-absent', totalAbsent);
  s('att-stat-rate', rate + '%');
  s('att-stat-at-risk', atRisk);
}

function attMarkAll(val) {
  STUDENTS.forEach(s => {
    for (let i = 1; i <= ATT_SESSIONS; i++) attData[s.id][i] = val;
  });
  renderAttendance();
  attDirty = true;
  document.getElementById('att-save-banner').classList.add('visible');
}

async function attSave() {
  if (attSaving) return;
  attSaving = true;

  // Update button to show saving state
  const btn = document.getElementById('btn-save-att');
  const origText = btn ? btn.innerHTML : '';
  if (btn) btn.innerHTML = '⏳ <span>' + (T[currentLang].attSavingLbl || '...') + '</span>';

  try {
    const res = await fetch('save_attendance.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      },
      body: JSON.stringify({ attendance: attData })
    });
    const json = await res.json();

    if (json.ok) {
      attDirty = false;
      document.getElementById('att-save-banner').classList.remove('visible');
      showToast(T[currentLang].toastAttSaved);
    } else {
      showToast(T[currentLang].toastAttError || 'Erreur lors de la sauvegarde');
    }
  } catch (e) {
    showToast(T[currentLang].toastAttError || 'Erreur réseau');
    console.error('Save attendance error:', e);
  } finally {
    attSaving = false;
    if (btn) btn.innerHTML = origText;
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  const savedLang = sessionStorage.getItem('upskill_lang') || 'fr';
  // Load everything in parallel
  await Promise.all([initAttData(), loadLiveStudents(), loadTeacherCourses(), loadAssignments()]);
  setLang(savedLang);
  renderAttention(); renderAssignments(); renderQuizzes(); renderGrades();
  renderAttendance(); renderCourses();
  hydrateTeacherInfo();
  loadSavedAvatar();
});

/* ── LIVE STUDENT FETCH ── */
async function loadLiveStudents() {
  try {
    const res = await fetch('api_students.php?action=list');
    if (!res.ok) return;
    const data = await res.json();
    if (data.ok && Array.isArray(data.students) && data.students.length > 0) {
      STUDENTS = data.students.map(s => ({
        id:       s.id,
        name:     s.name,
        init:     s.init,
        progress: s.progress ?? 0,
        assigns:  s.assigns  ?? 0,
        avg:      s.avg      ?? s.progress ?? 0,
        status:   s.status   ?? 'good',
        sessions: s.sessions ?? 0,
        present:  s.present  ?? 0,
      }));
      // Update stat card: active students count
      const sv = document.getElementById('stat1-val');
      if (sv) sv.textContent = STUDENTS.length;
      // Class average attendance
      const avg = Math.round(STUDENTS.reduce((sum, s) => sum + s.avg, 0) / STUDENTS.length);
      const sa = document.getElementById('stat3-val');
      if (sa) sa.textContent = avg + '%';
      // At-risk students count
      const atRisk = STUDENTS.filter(s => s.status !== 'good').length;
      const sr = document.getElementById('stat4-val');
      if (sr) sr.textContent = atRisk;
    }
  } catch(e) { /* keep mock-empty fallback */ }
}

/* ── TEACHER INFO HYDRATION ── */
function hydrateTeacherInfo() {
  const fn = <?= json_encode($_SESSION['full_name'] ?? $_SESSION['username'] ?? '') ?>;
  if (!fn) return;
  const parts = fn.trim().split(/\s+/);
  const init = ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '?';
  ['sidebar-avatar','topbar-avatar','settings-avatar'].forEach(id => {
    const el = document.getElementById(id); if (el) el.textContent = init;
  });
  const sn = document.getElementById('sidebar-name'); if (sn) sn.textContent = fn;
  const nm = document.getElementById('settings-name'); if (nm) nm.textContent = fn;
  const pi = document.getElementById('pref-name'); if (pi) pi.value = fn;
}

/* COURSES DATA & LOGIC */
const COURSES = [
  { id:1, icon:'📖', iconClass:'c1',
    group_fr:'Groupe A – Débutant', group_ar:'المجموعة أ – مبتدئ',
    level_fr:'A1–A2', level_ar:'ر0–ر0ر2',
    subject_fr:'Anglais Général', subject_ar:'الإنجليزية العامة',
    students:14, avg:72, assigns:3, att:85,
    schedule:[
      {day_fr:'Lundi',    day_ar:'الاثنين',  time:'09:00–11:00', room:'Salle 12'},
      {day_fr:'Mercredi', day_ar:'الأربعاء', time:'09:00–11:00', room:'Salle 12'}
    ],
    studentList:[
      {name:'Amina Karimi',    init:'AK', avg:85, att:90, status:'good'},
      {name:'Karim Benali',    init:'KB', avg:70, att:78, status:'warn'},
      {name:'Fatima Zahra',    init:'FZ', avg:92, att:95, status:'good'},
      {name:'Youssef Idrissi', init:'YI', avg:55, att:60, status:'low'},
      {name:'Salma Ouali',     init:'SO', avg:78, att:88, status:'good'},
      {name:'Omar Khalil',     init:'OK', avg:45, att:55, status:'low'}
    ]
  },
  { id:2, icon:'✍️', iconClass:'c2',
    group_fr:'Groupe B – Intermédiaire', group_ar:'المجموعة ب – متوسط',
    level_fr:'B1', level_ar:'بر1',
    subject_fr:'Rédaction & Communication', subject_ar:'الكتابة والتواصل',
    students:18, avg:78, assigns:4, att:91,
    schedule:[
      {day_fr:'Mardi', day_ar:'الثلاثاء', time:'14:00–16:00', room:'Labo 3'},
      {day_fr:'Jeudi', day_ar:'الخميس',   time:'14:00–16:00', room:'Labo 3'}
    ],
    studentList:[
      {name:'Nadia Berrada',   init:'NB', avg:89, att:96, status:'good'},
      {name:'Hassan Tazi',     init:'HT', avg:74, att:83, status:'good'},
      {name:'Leila Mansouri',  init:'LM', avg:81, att:90, status:'good'},
      {name:'Rachid Ouazzani', init:'RO', avg:65, att:72, status:'warn'},
      {name:'Imane Alaoui',    init:'IA', avg:88, att:94, status:'good'},
      {name:'Mehdi Chraibi',   init:'MC', avg:59, att:65, status:'warn'}
    ]
  },
  { id:3, icon:'🎙️', iconClass:'c3',
    group_fr:'Groupe C – Avancé', group_ar:'المجموعة ج – متقدم',
    level_fr:'B2–C1', level_ar:'بر2–جر1',
    subject_fr:'Expression Orale & Présentation', subject_ar:'التعبير الشفهي والعرض',
    students:10, avg:84, assigns:2, att:88,
    schedule:[{day_fr:'Vendredi', day_ar:'الجمعة', time:'10:00–12:00', room:'Amphi B'}],
    studentList:[
      {name:'Sara Elhajji',    init:'SE', avg:91, att:92, status:'good'},
      {name:'Amine Benkiran',  init:'AB', avg:83, att:85, status:'good'},
      {name:'Zineb Qacemi',    init:'ZQ', avg:77, att:90, status:'good'},
      {name:'Tariq Fassi',     init:'TF', avg:86, att:88, status:'good'}
    ]
  }
];
let activeCourse = null;
const CT = {
  fr:{ coursesPageTitle:'Les cours / الدروس',
       coursesPageSub:(n)=>n+' groupes assignés · Année 2024-2025',
       studentsLabel:'étudiants', backCourses:'← Retour aux cours',
       cdStudentsTitle:'Étudiants du groupe', cdScheduleTitle:'Emploi du temps / الجدول',
       cdProgTitle:'Progression du groupe',
       cdStatStudentsLbl:'Étudiants', cdStatAvgLbl:'Moyenne',
       cdStatAssignsLbl:'Devoirs actifs', cdStatAttLbl:'Taux de présence',
       statusGood:'Bon niveau', statusWarn:'À surveiller', statusLow:'En difficulté',
       thAvg:'Moy.', thAtt:'Présence',
       progGeneral:'Moyenne générale', progAtt:'Taux de présence', progAssigns:'Taux de soumission' },
  ar:{ coursesPageTitle:'الدروس / Les cours',
       coursesPageSub:(n)=>'تم تعيين '+n+' مجموعات · السنة 2024-2025',
       studentsLabel:'طالب', backCourses:'→ العودة إلى الدروس',
       cdStudentsTitle:'طلاب المجموعة', cdScheduleTitle:'الجدول / Emploi du temps',
       cdProgTitle:'تقدم المجموعة',
       cdStatStudentsLbl:'الطلاب', cdStatAvgLbl:'المعدل',
       cdStatAssignsLbl:'الواجبات النشطة', cdStatAttLbl:'نسبة الحضور',
       statusGood:'مستوى جيد', statusWarn:'تحت المراقبة', statusLow:'في صعوبة',
       thAvg:'معدل', thAtt:'الحضور',
       progGeneral:'المعدل العام', progAtt:'نسبة الحضور', progAssigns:'نسبة التسليم' }
};
function renderCourses(){
  const grid=document.getElementById('courses-grid'); if(!grid)return;
  const tr=CT[currentLang];
  const sub=document.getElementById('courses-page-sub'); if(sub)sub.textContent=tr.coursesPageSub(COURSES.length);
  const ttl=document.getElementById('courses-page-title'); if(ttl)ttl.textContent=tr.coursesPageTitle;
  const badge=document.getElementById('nav-courses-badge'); if(badge)badge.textContent=COURSES.length;
  grid.innerHTML=COURSES.map(c=>{
    const gname=currentLang==='ar'?c.group_ar:c.group_fr;
    const level=currentLang==='ar'?c.level_ar:c.level_fr;
    const subject=currentLang==='ar'?c.subject_ar:c.subject_fr;
    const chips=c.schedule.map(s=>'<span class="schedule-chip">'+(currentLang==='ar'?s.day_ar:s.day_fr)+' '+s.time+'</span>').join('');
    const ac=c.avg>=75?'var(--green)':c.avg>=55?'var(--yellow)':'var(--red)';
    return '<div class="course-card" onclick="openCourseDetail('+c.id+')">'
      +'<div class="course-card-header">'
        +'<div class="course-icon '+c.iconClass+'">'+c.icon+'</div>'
        +'<div><div class="course-group-name">'+gname+'</div><span class="course-level-tag">'+level+'</span></div>'
      +'</div>'
      +'<div style="font-size:.82rem;color:var(--muted);">📚 '+subject+'</div>'
      +'<div class="course-meta-row">'
        +'<span>👥 '+c.students+' '+tr.studentsLabel+'</span>'
        +'<span style="color:'+ac+'">📊 '+c.avg+'%</span>'
        +'<span>📝 '+c.assigns+'</span>'
        +'<span style="color:var(--green)">✅ '+c.att+'%</span>'
      +'</div>'
      +'<div class="course-schedule-chips">'+chips+'</div>'
    +'</div>';
  }).join('');
}
function openCourseDetail(id){
  activeCourse=COURSES.find(c=>c.id===id); if(!activeCourse)return;
  const c=activeCourse, tr=CT[currentLang];
  document.getElementById('courses-list-view').style.display='none';
  document.getElementById('courses-detail-view').style.display='block';
  const gname=currentLang==='ar'?c.group_ar:c.group_fr;
  const subject=currentLang==='ar'?c.subject_ar:c.subject_fr;
  const level=currentLang==='ar'?c.level_ar:c.level_fr;
  document.getElementById('course-detail-title').textContent=gname;
  document.getElementById('course-detail-meta').textContent=subject+' · '+level;
  document.getElementById('cd-stat-students').textContent=c.students;
  document.getElementById('cd-stat-avg').textContent=c.avg+'%';
  document.getElementById('cd-stat-assigns').textContent=c.assigns;
  document.getElementById('cd-stat-att').textContent=c.att+'%';
  document.getElementById('cd-stat-students-lbl').textContent=tr.cdStatStudentsLbl;
  document.getElementById('cd-stat-avg-lbl').textContent=tr.cdStatAvgLbl;
  document.getElementById('cd-stat-assigns-lbl').textContent=tr.cdStatAssignsLbl;
  document.getElementById('cd-stat-att-lbl').textContent=tr.cdStatAttLbl;
  document.getElementById('cd-students-title').textContent=tr.cdStudentsTitle;
  document.getElementById('cd-schedule-title').textContent=tr.cdScheduleTitle;
  document.getElementById('cd-prog-title').textContent=tr.cdProgTitle;
  document.getElementById('back-courses-lbl').textContent=tr.backCourses;
  document.getElementById('cd-students-list').innerHTML=c.studentList.map(s=>{
    const sk='status'+s.status.charAt(0).toUpperCase()+s.status.slice(1);
    return '<div class="cd-student-row">'
      +'<div class="student-avatar-sm">'+s.init+'</div>'
      +'<div style="flex:1"><div style="font-family:var(--font);font-size:.88rem;font-weight:600;">'+s.name+'</div>'
      +'<div style="font-size:.75rem;color:var(--muted2);">'+tr.thAvg+': '+s.avg+'% · '+tr.thAtt+': '+s.att+'%</div></div>'
      +'<span class="badge '+(s.status==='good'?'good':s.status==='warn'?'warn':'low')+'">'+tr[sk]+'</span>'
    +'</div>';
  }).join('');
  document.getElementById('cd-schedule-list').innerHTML=c.schedule.map(s=>
    '<div class="cd-sched-row">'
    +'<span class="sched-day">'+(currentLang==='ar'?s.day_ar:s.day_fr)+'</span>'
    +'<span class="sched-time">⏰ '+s.time+'</span>'
    +'<span class="sched-room">'+s.room+'</span>'
    +'</div>'
  ).join('');
  const bars=[
    {label:tr.progGeneral, val:c.avg,  color:c.avg>=75?'var(--green)':c.avg>=55?'var(--yellow)':'var(--red)'},
    {label:tr.progAtt,     val:c.att,  color:c.att>=80?'var(--green)':c.att>=60?'var(--yellow)':'var(--red)'},
    {label:tr.progAssigns, val:Math.round(c.assigns/5*100), color:'var(--purple)'}
  ];
  document.getElementById('cd-prog-bars').innerHTML=bars.map(b=>
    '<div style="margin-bottom:.9rem;">'
    +'<div style="display:flex;justify-content:space-between;font-size:.82rem;color:var(--muted);margin-bottom:.35rem;">'
    +'<span>'+b.label+'</span><span style="font-family:var(--font);font-weight:700;color:'+b.color+'">'+b.val+'%</span></div>'
    +'<div class="progress-bar"><div class="progress-fill" style="width:'+b.val+'%;background:'+b.color+'"></div></div>'
    +'</div>'
  ).join('');
}
function closeCourseDetail(){
  document.getElementById('courses-list-view').style.display='block';
  document.getElementById('courses-detail-view').style.display='none';
  activeCourse=null;
}

/* ── AVATAR UPLOAD ── */
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
    applyAvatarEverywhere(dataUrl);
    try { localStorage.setItem('upskill_avatar_t', dataUrl); } catch(e) {}
    showToast(currentLang === 'ar' ? 'تم تحديث الصورة ✓' : 'Photo mise à jour ✓');
  };
  reader.readAsDataURL(file);
}

function applyAvatarEverywhere(dataUrl) {
  const pairs = [
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
  try {
    const dataUrl = localStorage.getItem('upskill_avatar_t');
    if (!dataUrl) return;
    applyAvatarEverywhere(dataUrl);
  } catch(e) {}
}

/* ── PROFILE DROPDOWN ── */
let profileMenuOpen = false;

function toggleProfileMenu(e) {
  e.stopPropagation();
  profileMenuOpen = !profileMenuOpen;
  document.getElementById('profile-menu').classList.toggle('open', profileMenuOpen);
  if (profileMenuOpen) applyProfileMenuTranslations();
}

function applyProfileMenuTranslations() {
  const PM = {
    fr: { name:"Changer le prénom", avatar:"Changer l'avatar", logout:"Déconnexion" },
    ar: { name:"تغيير الاسم",       avatar:"تغيير الصورة",      logout:"تسجيل الخروج" },
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
    const settingsNav = document.getElementById('nav-set');
    navigate('settings', settingsNav);
    setTimeout(() => { const inp = document.getElementById('pref-name'); if(inp) { inp.focus(); inp.select(); } }, 120);
  } else if (action === 'avatar') {
    document.getElementById('avatar-input').click();
  } else if (action === 'logout') {
    logout();
  }
}

document.addEventListener('click', function(e) {
  if (profileMenuOpen && !document.getElementById('topbar-avatar').contains(e.target)) {
    profileMenuOpen = false;
    document.getElementById('profile-menu').classList.remove('open');
  }
});
</script>
</body>
</html>
