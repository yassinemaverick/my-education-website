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
<html lang="<?= htmlspecialchars($_SESSION['lang'] ?? 'fr') ?>" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<meta name="robots" content="noindex,nofollow">
<title>Upskill – Teacher Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap"></noscript>
<style>
/* ── SHARED (inlined — /study/css/shared.css cannot load on the study subdomain) ── */
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
.activity-dot.green  { background:var(--green); }
.activity-dot.yellow { background:var(--yellow); }
.activity-dot.blue   { background:var(--blue); }
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
</style>
<style>
:root {
  --navy:#d6eeff; --navy-mid:#ffffff; --navy-light:#eff6ff; --navy-card:#ffffff;
  --green:#10b981; --green-dark:#059669; --green-glow:rgba(16,185,129,0.12); --green-dim:rgba(16,185,129,0.08);
  --white:#1e1b4b; --muted:rgba(30,27,75,0.55); --muted2:rgba(30,27,75,0.40);
  --border:rgba(0,0,0,0.09); --border2:rgba(0,0,0,0.05);
  --yellow:#f59e0b; --red:#ef4444; --blue:#3b82f6; --primary:#3b82f6; --purple:#a78bfa; --orange:#f59e0b;
  --font:'Sora',sans-serif; --font-body:'DM Sans',sans-serif;
  --sidebar-w:270px;
}
html { scroll-behavior:smooth; }
.skip-link:focus { top:0 !important; outline:3px solid var(--green); }
body { background:var(--navy); color:var(--white); font-family:var(--font-body); min-height:100vh; display:flex; overflow-x:hidden; }

/* SIDEBAR */
.sidebar {
  --navy:#2e2a7a; --navy-mid:#3d3890; --navy-light:#4a4499; --navy-card:rgba(255,255,255,0.07);
  --white:#ffffff; --muted:rgba(255,255,255,0.62); --muted2:rgba(255,255,255,0.45);
  --border:rgba(255,255,255,0.1); --border2:rgba(255,255,255,0.06);
  width:var(--sidebar-w); background:var(--navy-mid); border-right:1px solid var(--border);
  display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:200; transition:transform .3s;
  flex:0 0 0; min-width:0;
}
.sidebar-logo { display:flex; align-items:center; gap:.6rem; padding:1.5rem 1.4rem; border-bottom:1px solid var(--border); }
.sidebar-logo span { font-family:var(--font); font-weight:600; font-size:1rem; }
.sidebar-logo em { color:var(--green); font-style:normal; }
.sidebar-logo .teacher-chip { margin-left:auto; background:rgba(167,139,250,.15); color:var(--purple); border:1px solid rgba(167,139,250,.3); font-family:var(--font); font-size:.65rem; font-weight:700; padding:.2rem .55rem; border-radius:100px; }
.lang-toggle { display:flex; gap:.4rem; padding:.6rem 1.4rem; border-bottom:1px solid var(--border); }
.lang-pill { font-size:.7rem; font-family:var(--font); font-weight:600; padding:.25rem .65rem; border-radius:100px; border:1px solid var(--border); color:var(--muted); cursor:pointer; transition:all .2s; }
.lang-pill.active { background:rgba(245,158,11,.2); border-color:rgba(245,158,11,.5); color:#f59e0b; }
.sidebar-user { padding:1.2rem 1.4rem; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:.8rem; }
.avatar { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#bfdbfe,#ddd6fe); border:2px solid rgba(245,158,11,.4); display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; position:relative; }
.av-dino { width:100%; height:100%; display:block; }
.av-photo { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius:50%; display:none; }
.user-info .name { font-family:var(--font); font-size:.85rem; font-weight:600; line-height:1.2; }
.user-info .role-tag { font-size:.72rem; color:var(--purple); background:rgba(167,139,250,.15); padding:.1rem .5rem; border-radius:100px; margin-top:.2rem; display:inline-block; }
.sidebar-nav { flex:1; padding:1rem .8rem; overflow-y:auto; }
.nav-section-label { font-family:var(--font); font-size:.65rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muted2); padding:.5rem .6rem .3rem; margin-top:.5rem; }
.nav-item { display:flex; align-items:center; gap:.75rem; padding:.65rem .9rem; border-radius:10px; cursor:pointer; color:var(--muted); font-size:.88rem; font-family:var(--font); font-weight:500; transition:all .2s; margin-bottom:.1rem; }
.nav-item svg { flex-shrink:0; opacity:.7; }
.nav-item:hover { background:rgba(255,255,255,.05); color:var(--white); }
.nav-item.active { background:rgba(245,158,11,.18); color:#f59e0b; border:1px solid rgba(245,158,11,.3); }
.nav-item.active svg { opacity:1; }
.nav-badge { margin-left:auto; background:var(--yellow); color:var(--navy); font-size:.65rem; font-weight:700; padding:.15rem .45rem; border-radius:100px; font-family:var(--font); }
.sidebar-bottom { padding:1rem; border-top:1px solid var(--border); }
.btn-logout { display:flex; align-items:center; gap:.6rem; width:100%; padding:.65rem .9rem; border-radius:10px; background:transparent; border:1px solid var(--border); color:var(--muted); font-family:var(--font); font-size:.85rem; cursor:pointer; transition:all .2s; }
.btn-logout:hover { border-color:var(--red); color:var(--red); background:rgba(232,93,117,.08); }

/* MAIN */
.main { flex:1; margin-left:var(--sidebar-w); min-height:100vh; display:flex; flex-direction:column; background:var(--navy); }
.topbar { background:rgba(214,238,255,0.92); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); padding:1rem 2rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
.topbar-title { font-family:var(--font); font-size:1rem; font-weight:600; }
.topbar-actions { display:flex; align-items:center; gap:.75rem; }
.btn-icon { width:36px; height:36px; border-radius:9px; background:rgba(30,27,75,.06); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--muted); transition:all .2s; }
.btn-icon:hover { border-color:var(--blue); color:var(--blue); background:rgba(59,130,246,.08); }
#page-attendance { padding-top:1rem !important; padding-left:0 !important; padding-right:1rem !important; }
#page-attendance .att-toolbar { margin-bottom:.6rem; padding-left:1rem; }
#page-attendance .att-summary-bar { margin-bottom:.6rem; padding-left:1rem; }
#page-attendance #att-group-selector-wrap { padding-left:1rem; }
#page-attendance .att-save-banner { padding-left:1rem; }
#page-attendance .att-card { border-radius:0 16px 16px 0; margin-left:0; }

/* CARDS */

/* STAT */
.stat-icon.purple { background:rgba(167,139,250,.1); }
.stat-icon.green { background:var(--green-dim); }
.stat-icon.yellow { background:rgba(245,197,66,.1); }
.stat-icon.blue { background:rgba(91,156,246,.1); }

/* PROGRESS */
.progress-fill.purple { background:var(--purple); }
.progress-fill.yellow { background:var(--yellow); }

/* WELCOME BANNER */
.welcome-banner { background:linear-gradient(135deg,#2e2a7a,#4a4499); border:1px solid rgba(255,255,255,.12); border-radius:20px; padding:2rem 2.5rem; margin-bottom:2rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; position:relative; overflow:hidden; color:#fff; }
.welcome-banner::before { content:''; position:absolute; top:-60px; right:-60px; width:200px; height:200px; background:radial-gradient(circle,rgba(245,158,11,.15),transparent 70%); }
.welcome-text h2 { font-family:var(--font); font-size:1.6rem; font-weight:700; letter-spacing:-.03em; margin-bottom:.4rem; color:#fff; }
.welcome-text h2 span { color:#f59e0b; }
.welcome-text p { color:rgba(255,255,255,.7); font-size:.9rem; }

/* STUDENT TABLE */
.student-table { width:100%; border-collapse:collapse; }
.student-table th { font-family:var(--font); font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--muted2); padding:.75rem 1rem; text-align:left; border-bottom:1px solid var(--border); }
.student-table td { padding:.85rem 1rem; border-bottom:1px solid var(--border2); font-size:.88rem; vertical-align:middle; }
.student-table tr:last-child td { border-bottom:none; }
.student-table tr:hover td { background:rgba(59,130,246,.04); }
.student-avatar-sm { width:32px; height:32px; border-radius:50%; background:rgba(59,130,246,.1); border:1.5px solid rgba(59,130,246,.2); display:inline-flex; align-items:center; justify-content:center; font-family:var(--font); font-size:.72rem; font-weight:700; color:var(--blue); }
.badge.good { background:rgba(62,207,120,.12); color:var(--green); border:1px solid rgba(62,207,120,.3); }
.badge.warn { background:rgba(245,197,66,.12); color:var(--yellow); border:1px solid rgba(245,197,66,.3); }
.badge.low { background:rgba(232,93,117,.12); color:var(--red); border:1px solid rgba(232,93,117,.3); }

/* ASSIGN TEACHER */
.assign-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 0; border-bottom:1px solid var(--border2); }
.assign-row:last-child { border-bottom:none; }
.assign-info { flex:1; }
.assign-title-t { font-family:var(--font); font-size:.92rem; font-weight:600; margin-bottom:.2rem; }
.assign-meta-t { font-size:.78rem; color:var(--muted2); display:flex; gap:1rem; }
.assign-actions { display:flex; gap:.5rem; flex-shrink:0; }

/* BUTTONS */
.btn-primary { background:linear-gradient(135deg,#3b82f6,#7c3aed); color:#fff; font-family:var(--font); font-weight:700; font-size:.88rem; padding:.65rem 1.3rem; border:none; border-radius:10px; cursor:pointer; transition:opacity .2s,transform .15s; display:inline-flex; align-items:center; gap:.4rem; }
.btn-primary:hover { opacity:.9; transform:translateY(-1px); }
.btn-secondary { background:rgba(30,27,75,.06); color:var(--muted); font-family:var(--font); font-weight:500; font-size:.88rem; padding:.65rem 1.3rem; border:1px solid var(--border); border-radius:10px; cursor:pointer; transition:all .2s; }
.btn-secondary:hover { border-color:rgba(30,27,75,.25); color:var(--white); }

/* ACTIVITY */
.activity-dot.green { background:var(--green); }
.activity-dot.blue { background:var(--blue); }
.activity-dot.purple { background:var(--purple); }

/* TABS */
.tabs { display:flex; gap:.5rem; margin-bottom:1.5rem; border-bottom:1px solid var(--border); }

/* MODAL */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:1000; display:none; align-items:flex-start; justify-content:center; backdrop-filter:blur(4px); padding:2rem 1rem; overflow-y:auto; }
.modal-overlay.open { display:flex; }
.modal { background:#fff; border:1px solid var(--border); border-radius:20px; padding:2rem; max-width:520px; width:100%; margin:auto; animation:slideUp .25s ease; }
.modal-header h3 { font-family:var(--font); font-size:1.1rem; font-weight:700; }
.form-group input, .form-group textarea, .form-group select {
  width:100%; padding:.8rem 1rem; background:rgba(30,27,75,.04); border:1px solid var(--border);
  border-radius:10px; color:var(--white); font-family:var(--font-body); font-size:.9rem;
  outline:none; transition:border-color .2s; resize:vertical;
}

/* TOAST */
.toast { position:fixed; bottom:2rem; right:2rem; background:#fff; border:1px solid var(--border); border-radius:12px; padding:.9rem 1.4rem; font-family:var(--font); font-size:.85rem; color:var(--white); z-index:9999; transform:translateY(100px); opacity:0; transition:all .3s; display:flex; align-items:center; gap:.6rem; }
.toast.show { transform:translateY(0); opacity:1; }
.toast-dot { width:8px; height:8px; border-radius:50%; background:#f59e0b; }

/* ── ATTENDANCE ── */
.att-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; }
.att-toolbar-left h2 { font-family:var(--font); font-size:1.4rem; font-weight:700; letter-spacing:-.02em; }
.att-toolbar-left p  { color:var(--muted); font-size:.85rem; margin-top:.2rem; }
.att-actions { display:flex; gap:.6rem; flex-wrap:wrap; }

.att-summary-bar { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
.att-stat { background:var(--navy-card); border:1px solid var(--border); border-radius:14px; padding:.7rem 1rem; display:flex; align-items:center; gap:.75rem; }
.att-stat-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.att-stat-icon.green  { background:rgba(62,207,120,.12); }
.att-stat-icon.yellow { background:rgba(245,197,66,.1); }
.att-stat-icon.red    { background:rgba(232,93,117,.1); }
.att-stat-icon.blue   { background:rgba(91,156,246,.1); }
.att-stat-val { font-family:var(--font); font-size:1.4rem; font-weight:700; letter-spacing:-.02em; line-height:1; }
.att-stat-lbl { font-size:.75rem; color:var(--muted); margin-top:.15rem; }

/* Group selector */
#att-group-selector-wrap { background:var(--navy-card); border:1px solid var(--border); border-radius:12px; padding:.75rem 1rem; }
#att-group-select { padding:.5rem .85rem; background:var(--navy-light,#eff6ff); border:1px solid var(--border); border-radius:8px; color:var(--white); font-family:var(--font-body); font-size:.88rem; outline:none; min-width:220px; cursor:pointer; }
#att-group-select:focus { border-color:var(--primary,#5b9cf6); }

.att-card { background:var(--navy-card); border:1px solid var(--border); border-radius:16px; overflow:clip; }
.att-table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; padding-bottom:.5rem; }
.att-table { width:100%; border-collapse:collapse; min-width:900px; }

/* Header rows */
.att-table thead tr:first-child th { background:rgba(245,158,11,.06); border-bottom:1px solid var(--border); }
.att-table thead tr:last-child th  { background:rgba(245,158,11,.03); border-bottom:2px solid rgba(245,158,11,.18); }

.att-th-name { font-family:var(--font); font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--muted2); padding:.75rem 1.2rem; text-align:left; white-space:nowrap; min-width:180px; position:sticky; left:0; z-index:2; background:rgba(245,158,11,.06); }
.att-th-sess { font-family:var(--font); font-size:.68rem; font-weight:700; color:var(--muted2); padding:.5rem .35rem; text-align:center; white-space:nowrap; min-width:34px; }
.att-th-total { font-family:var(--font); font-size:.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--muted2); padding:.5rem 1rem; text-align:center; white-space:nowrap; position:sticky; right:0; z-index:2; background:rgba(245,158,11,.06); }

/* Group-of-5 visual separators */
.att-th-sess.grp-start, .att-td-box.grp-start { border-left:2px solid rgba(91,156,246,.2); }

/* Body rows */
.att-table tbody tr { border-bottom:1px solid var(--border2); transition:background .15s; }
.att-table tbody tr:last-child { border-bottom:none; }
.att-table tbody tr:hover { background:rgba(59,130,246,.04); }

.att-td-name { padding:.85rem 1.2rem; white-space:nowrap; position:sticky; left:0; z-index:1; background:var(--navy-card); border-right:1px solid var(--border2); }
.att-student { display:flex; align-items:center; gap:.7rem; }
.att-avatar { width:32px; height:32px; border-radius:50%; background:rgba(59,130,246,.1); border:1.5px solid rgba(59,130,246,.2); display:flex; align-items:center; justify-content:center; font-family:var(--font); font-size:.7rem; font-weight:700; color:var(--blue); flex-shrink:0; }
.att-name { font-family:var(--font); font-size:.88rem; font-weight:600; }

.att-td-box { padding:.55rem .35rem; text-align:center; vertical-align:middle; }
.att-box { width:22px; height:22px; border-radius:5px; border:1.5px solid rgba(30,27,75,.22); background:rgba(30,27,75,.03); cursor:pointer; appearance:none; -webkit-appearance:none; display:inline-block; vertical-align:middle; transition:background .15s, border-color .15s, transform .1s; flex-shrink:0; }
.att-box:hover { border-color:var(--green); background:rgba(62,207,120,.08); transform:scale(1.12); }
.att-box:checked { background:var(--green); border-color:var(--green); background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' fill='none'%3E%3Cpath d='M2 6l3 3 5-5' stroke='%23fff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:center; background-size:10px; }
.att-box.absent:checked { background:var(--red); border-color:var(--red); background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' fill='none'%3E%3Cpath d='M9 3L3 9M3 3l6 6' stroke='%23fff' stroke-width='2' stroke-linecap='round'/%3E%3C/svg%3E"); }

.att-td-total { padding:.6rem 1rem; text-align:center; white-space:nowrap; position:sticky; right:0; z-index:1; background:var(--navy-card); border-left:1px solid var(--border2); }
.att-pct-wrap { display:flex; flex-direction:column; align-items:center; gap:.25rem; }
.att-pct { font-family:var(--font); font-size:.9rem; font-weight:700; }
.att-pct.high { color:var(--green); }
.att-pct.mid  { color:#d97706; }
.att-pct.low  { color:var(--red); }
.att-mini-bar { width:48px; height:5px; background:rgba(30,27,75,.08); border-radius:100px; overflow:hidden; }
.att-mini-fill { height:100%; border-radius:100px; transition:width .4s; }
.att-mini-fill.high { background:var(--green); }
.att-mini-fill.mid  { background:#d97706; }
.att-mini-fill.low  { background:var(--red); }

/* Session header labels */
.sess-num-chip { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:6px; background:rgba(245,158,11,.12); color:#b45309; font-family:var(--font); font-size:.65rem; font-weight:700; }

/* Save banner */
.att-save-banner { display:none; align-items:center; justify-content:space-between; background:rgba(62,207,120,.08); border:1px solid rgba(62,207,120,.25); border-radius:12px; padding:.8rem 1.2rem; margin-bottom:1rem; }
.att-save-banner.visible { display:flex; }
.att-save-txt { font-size:.85rem; color:var(--green); font-family:var(--font); }

@media(max-width:768px){
  .att-summary-bar { grid-template-columns:1fr 1fr; }
}
@media(max-width:480px){
  .att-summary-bar { grid-template-columns:1fr 1fr; }
}

/* ── HAMBURGER ── */
.hamburger:hover { border-color:var(--blue); color:var(--blue); }
.sidebar-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:199; }
.sidebar-backdrop.open { display:block; }

@media(max-width:768px){
  .hamburger { display:flex; }
  .sidebar{transform:translateX(-100%);}
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
.course-icon { width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
.course-icon.c1 { background:rgba(245,158,11,.1); }
.course-icon.c2 { background:var(--green-dim); }
.course-icon.c3 { background:rgba(245,197,66,.1); }
.course-icon.c4 { background:rgba(91,156,246,.1); }
.course-group-name { font-family:var(--font); font-size:1rem; font-weight:700; line-height:1.2; }
.course-level-tag { display:inline-block; font-size:.68rem; font-weight:700; padding:.15rem .55rem; border-radius:100px; background:rgba(245,158,11,.1); color:#f59e0b; border:1px solid rgba(245,158,11,.25); margin-top:.2rem; }
.course-meta-row { display:flex; flex-wrap:wrap; gap:.5rem 1rem; font-size:.78rem; color:var(--muted); }
.course-schedule-chips { display:flex; flex-wrap:wrap; gap:.4rem; }
.schedule-chip { font-size:.72rem; background:rgba(62,207,120,.08); border:1px solid rgba(62,207,120,.2); color:var(--green); padding:.2rem .6rem; border-radius:100px; font-family:var(--font); }
.tc-breadcrumb { display:flex; align-items:center; gap:.35rem; margin-bottom:1.1rem; flex-wrap:wrap; }
.tc-crumb { font-size:.82rem; color:var(--green); cursor:pointer; font-family:var(--font); font-weight:600; }
.tc-crumb:hover { text-decoration:underline; }
.tc-sep { color:var(--muted); font-size:.72rem; }
.tc-crumb-cur { font-size:.82rem; color:var(--muted); font-family:var(--font); }
.cd-student-row { display:flex; align-items:center; gap:.75rem; padding:.7rem 0; border-bottom:1px solid var(--border2); }
.cd-student-row:last-child { border-bottom:none; }
.cd-sched-row { display:flex; align-items:center; gap:.75rem; padding:.6rem 0; border-bottom:1px solid var(--border2); }
.cd-sched-row:last-child { border-bottom:none; }
.sched-day { font-family:var(--font); font-size:.82rem; font-weight:700; min-width:90px; }
.sched-time { font-size:.8rem; color:var(--muted); }
.sched-room { font-size:.72rem; background:rgba(91,156,246,.1); border:1px solid rgba(91,156,246,.25); color:var(--blue); padding:.15rem .5rem; border-radius:100px; margin-left:auto; }

/* PROFILE MENU */
/* AVATAR UPLOAD */
.settings-av-wrap { position:relative; display:inline-block; }
.av-upload-btn { position:absolute; bottom:-3px; right:-3px; width:24px; height:24px; background:#3b82f6; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#fff; border:2px solid var(--navy); transition:background .2s; }
.av-upload-btn:hover { background:#2563eb; }
#mascot-av-img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius:50%; display:none; }

/* NOTIFICATION PANEL */
.notif-panel { position:fixed; top:64px; right:1rem; width:340px; max-height:460px; overflow-y:auto; background:#fff; border:1px solid var(--border); border-radius:16px; box-shadow:0 8px 32px rgba(30,27,75,.16); z-index:9001; display:none; animation:fadeIn .15s ease; }
.notif-panel.open { display:block; }
.notif-header { display:flex; align-items:center; justify-content:space-between; padding:.9rem 1.25rem .7rem; border-bottom:1px solid var(--border); }
.notif-header h4 { font-family:var(--font); font-size:.9rem; font-weight:700; }
.notif-item { display:flex; gap:.8rem; padding:.8rem 1.25rem; border-bottom:1px solid var(--border2); font-size:.83rem; line-height:1.45; cursor:pointer; transition:background .15s; user-select:none; -webkit-user-select:none; }
.notif-item:hover { background:rgba(59,130,246,.08); }
.notif-item:active { background:rgba(59,130,246,.15); }
.notif-item.unread { background:rgba(59,130,246,.06); border-left:3px solid var(--blue); }
.notif-item:last-child { border-bottom:none; }
.notif-dot { width:8px; height:8px; border-radius:50%; background:var(--blue); flex-shrink:0; margin-top:.35rem; }
.notif-dot-placeholder { width:8px; flex-shrink:0; }
.notif-time { font-size:.72rem; color:var(--muted2); margin-top:.2rem; }
#notif-badge { position:absolute; top:-4px; right:-4px; min-width:16px; height:16px; border-radius:100px; background:var(--red); color:#fff; font-family:var(--font); font-size:.6rem; font-weight:700; display:none; align-items:center; justify-content:center; padding:0 3px; }
#notif-badge.show { display:flex; }
@keyframes spin { to { transform:rotate(360deg); } }
.spinner { display:inline-block; width:20px; height:20px; border:2px solid rgba(30,27,75,.12); border-top-color:var(--blue); border-radius:50%; animation:spin .7s linear infinite; }
.loading-overlay { display:flex; align-items:center; justify-content:center; padding:3rem; color:var(--muted); gap:.75rem; font-size:.9rem; }
</style>
<style id="lang-hide">body{visibility:hidden}</style>
</head>
<body id="body">
<a href="#main-content" class="skip-link" style="position:absolute;top:-40px;left:0;background:var(--green);color:#0f1d2e;padding:.5rem 1rem;font-family:var(--font);font-weight:700;font-size:.85rem;z-index:9999;border-radius:0 0 8px 0;transition:top .2s;text-decoration:none;" id="skip-link">Skip to content</a>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">
  <div class="sidebar-logo">
    <svg width="26" height="26" viewBox="0 0 28 28" fill="none"><rect width="28" height="28" rx="8" fill="#a78bfa"/><path d="M8 14l4 4 8-8" stroke="#0f1d2e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Up<em style="color:var(--purple)">skill</em></span>
    <div class="teacher-chip" id="teacher-chip-lbl">Prof</div>
  </div>
  <div class="lang-toggle">
    <div class="lang-pill active" id="pill-fr" onclick="setLang('fr')">🇫🇷 FR</div>
    <div class="lang-pill" id="pill-en" onclick="setLang('en')">🇬🇧 EN</div>
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
    <div class="nav-item active" role="button" tabindex="0" onclick="navigate('home',this)" id="nav-home">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span id="nav-home-lbl">Tableau de bord</span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('courses',this)" id="nav-students">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      <span id="nav-students-lbl">Classes</span>
      <span class="nav-badge" id="badge-classes" aria-hidden="true" style="display:none;"></span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('assignments',this)" id="nav-assign">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      <span id="nav-assign-lbl">Devoirs</span>
      <span class="nav-badge" id="badge-assignments" aria-hidden="true" style="display:none;"></span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('posts',this)" id="nav-posts">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
      <span id="nav-posts-lbl">Notes de cours</span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('quizzes',this)" id="nav-quiz">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span id="nav-quiz-lbl">Quiz</span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('grades',this)" id="nav-grades">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <span id="nav-grades-lbl">Notes & Résultats</span>
    </div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('attendance',this)" id="nav-attendance">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      <span id="nav-attendance-lbl">Présences</span>
    </div>
    <div class="nav-section-label" id="nav-account-label">Compte</div>
    <div class="nav-item" role="button" tabindex="0" onclick="navigate('settings',this)" id="nav-set">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span id="nav-set-lbl">Paramètres</span>
    </div>
  </nav>
  <div class="sidebar-bottom">
    <button class="btn-logout" onclick="logout()" aria-label="Log out">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span id="logout-lbl">Déconnexion</span>
    </button>
  </div>
</aside>

<!-- MAIN -->
<main class="main" role="main" id="main-content">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:.75rem;">
      <button class="hamburger" onclick="toggleSidebar()" aria-label="Open menu" aria-expanded="false">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="topbar-title" id="topbar-title">Tableau de bord Professeur</div>
    </div>
    <div class="topbar-actions">
      <button class="btn-primary btn-sm" onclick="openAssignModal()" id="btn-new-assign">+ Nouveau devoir</button>
      <button class="btn-icon" id="notif-btn" onclick="toggleNotifPanel()" aria-label="Notifications" style="position:relative;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span id="notif-badge" aria-live="polite"></span>
      </button>
      <div class="avatar" id="topbar-avatar" style="cursor:pointer;" onclick="toggleProfileMenu(event)" title="My profile"><?= str_replace(['%ID%','%IMGID%'], ['topbar-dino-svg','topbar-av-img'], $dinoAvatarSvg) ?></div>
    </div>
  </div>

  <!-- HOME PAGE -->
  <div class="page active" id="page-home">
    <div class="welcome-banner">
      <div class="welcome-text">
        <h2 id="welcome-msg">Bonjour, <span id="welcome-name"><?= htmlspecialchars(explode(" ", $full_name)[0]) ?></span> 👋</h2>
        <p id="welcome-sub"></p>
      </div>
      <div style="font-size:3rem;">👨‍🏫</div>
    </div>

    <div class="grid-4" style="margin-bottom:1.5rem;">
      <div class="card"><div class="stat-icon purple"><span aria-hidden="true">👥</span></div><div class="stat-value" id="stat1-val">—</div><div class="stat-label" id="stat1-lbl">Étudiants actifs</div></div>
      <div class="card"><div class="stat-icon yellow"><span aria-hidden="true">📝</span></div><div class="stat-value" id="stat2-val">—</div><div class="stat-label" id="stat2-lbl">Devoirs à corriger</div></div>
      <div class="card"><div class="stat-icon green"><span aria-hidden="true">📊</span></div><div class="stat-value" id="stat3-val">—</div><div class="stat-label" id="stat3-lbl">Moyenne de la classe</div></div>
      <div class="card"><div class="stat-icon blue"><span aria-hidden="true">📋</span></div><div class="stat-value" id="stat4-val">—</div><div class="stat-label" id="stat4-lbl">Devoirs publiés</div></div>
    </div>

    <div class="grid-2">
      <div>
        <!-- Class Progress -->
        <div class="card" style="margin-bottom:1.5rem;">
          <div class="card-title" id="class-prog-title">Progression de la classe</div>
          <div style="margin-bottom:1rem;">
            <div style="display:flex;justify-content:space-between;font-size:.83rem;color:var(--muted);margin-bottom:.4rem;"><span id="cp-label1">Progression générale</span><span id="cp-val1" style="color:var(--green);font-family:var(--font);font-weight:700;">—</span></div>
            <div class="progress-bar"><div class="progress-fill" id="cp-bar1" style="width:0%"></div></div>
          </div>
          <div style="margin-bottom:1rem;">
            <div style="display:flex;justify-content:space-between;font-size:.83rem;color:var(--muted);margin-bottom:.4rem;"><span id="cp-label2">Taux de soumission devoirs</span><span id="cp-val2" style="color:var(--yellow);font-family:var(--font);font-weight:700;">—</span></div>
            <div class="progress-bar"><div class="progress-fill yellow" id="cp-bar2" style="width:0%"></div></div>
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;font-size:.83rem;color:var(--muted);margin-bottom:.4rem;"><span id="cp-label3">Moyenne quiz</span><span id="cp-val3" style="color:var(--purple);font-family:var(--font);font-weight:700;">—</span></div>
            <div class="progress-bar"><div class="progress-fill purple" id="cp-bar3" style="width:0%"></div></div>
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
        <div id="activity-list"><div style="color:var(--muted);font-size:.85rem;padding:.5rem 0;">Chargement…</div></div>
      </div>
    </div>

    <!-- CLASSES TODAY CARD -->
    <div class="card" style="margin-top:1.5rem;" id="today-classes-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div class="card-title" style="margin-bottom:0;" id="today-classes-title">📅 Cours d'aujourd'hui</div>
        <span id="today-date-lbl" style="font-size:.78rem;color:var(--muted);font-family:var(--font);"></span>
      </div>
      <div id="today-classes-list">
        <div style="color:var(--muted);font-size:.85rem;padding:.5rem 0;">Chargement…</div>
      </div>
    </div>
  </div>

  <!-- COURSES PAGE -->
  <div class="page" id="page-courses">
    <div id="courses-list-view">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:1rem;">
        <div>
          <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="courses-page-title">Classes</h2>
          <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="courses-page-sub">3 groupes · 2024-2025</p>
        </div>
        <div style="display:flex;gap:.3rem;background:var(--border2,#eef0f6);border-radius:10px;padding:.25rem;">
          <button id="tab-btn-groups" onclick="switchCoursesTab('groups')" style="padding:.35rem 1rem;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;background:var(--primary);color:#fff;transition:all .18s;" id="tab-groups-lbl">Groups</button>
          <button id="tab-btn-schedule" onclick="switchCoursesTab('schedule')" style="padding:.35rem 1rem;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;background:transparent;color:var(--muted);transition:all .18s;">Schedule</button>
        </div>
      </div>
      <!-- Groups tab -->
      <div id="tc-groups-content">
        <div id="teacher-assigned-groups" style="margin-bottom:1.5rem;">
          <div class="loading-overlay"><div class="spinner"></div></div>
        </div>
      </div>
      <!-- Schedule tab -->
      <div id="tc-schedule-content" style="display:none;">
        <div id="teacher-schedule-view" style="margin-bottom:1.5rem;"></div>
      </div>
    </div>
    <div id="courses-detail-view" style="display:none;">
      <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        <button class="btn-secondary btn-sm" onclick="closeCourseDetail()">
          <span id="back-courses-lbl">← Retour</span>
        </button>
        <div>
          <h2 style="font-family:var(--font);font-size:1.3rem;font-weight:700;" id="course-detail-title">—</h2>
          <p style="color:var(--muted);font-size:.82rem;margin-top:.15rem;" id="course-detail-meta">—</p>
        </div>
      </div>
      <div class="card">
        <div class="card-title" id="cd-students-title">Étudiants du groupe</div>
        <div id="cd-students-list" style="margin-top:.75rem;"></div>
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
      <button class="btn-primary" onclick="openQuizModal()" id="btn-new-quiz">+ <span id="new-quiz-lbl">Créer un quiz</span></button>
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
      <div class="card-title" id="quiz-scores-title">Notes par devoir</div>
      <table class="student-table">
        <thead><tr><th id="gth-student">Étudiant</th><th id="gth-assign">Devoir</th><th id="gth-score">Note</th><th id="gth-date">Date</th></tr></thead>
        <tbody id="grades-tbody"><tr><td colspan="4" style="color:var(--muted);text-align:center;padding:1rem;">Chargement…</td></tr></tbody>
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
            <label for="avatar-input" class="av-upload-btn" id="avatar-upload-btn" title="Change photo">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            </label>
            <input type="file" id="avatar-input" accept="image/*" style="display:none;" onchange="handleAvatarUpload(this)">
          </div>
          <div>
            <div style="font-family:var(--font);font-weight:600;font-size:.95rem;color:var(--white);" id="settings-name"><?= $full_name ?></div>
            <div style="color:var(--muted);font-size:.82rem;margin-top:.2rem;" id="settings-role">Professeur · Anglais Général</div>
            <label for="avatar-input" id="change-photo-lbl" style="display:inline-block;margin-top:.5rem;font-size:.75rem;color:var(--blue);cursor:pointer;font-family:var(--font);font-weight:500;">Change photo</label>
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
        <p style="color:var(--muted);font-size:.85rem;line-height:1.6;" id="pref-txt">Use the language selector in the sidebar to switch between French and English.</p>
      </div>
    </div>

    <!-- Password change card -->
    <div class="card" style="margin-top:1.25rem;max-width:480px;">
      <div class="card-title" id="pwd-card-title">Changer le mot de passe</div>
      <div class="form-group">
        <label id="lbl-pwd-current">Mot de passe actuel</label>
        <input type="password" id="pwd-current" autocomplete="current-password">
      </div>
      <div class="form-group">
        <label id="lbl-pwd-new">Nouveau mot de passe</label>
        <input type="password" id="pwd-new" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label id="lbl-pwd-confirm">Confirmer le mot de passe</label>
        <input type="password" id="pwd-confirm" autocomplete="new-password">
      </div>
      <div id="pwd-error" style="display:none;color:var(--red);font-size:.82rem;margin-bottom:.6rem;"></div>
      <button class="btn-primary" onclick="changePassword()" id="pwd-btn">
        <span id="pwd-btn-text">Changer le mot de passe</span>
      </button>
    </div>
  </div>
</main>

<!-- MODAL: CONFIRM DELETE -->
<div class="modal-overlay" id="modal-confirm" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <h3 id="confirm-modal-title">Confirm</h3>
      <button class="btn-close" onclick="closeConfirmModal()" aria-label="Close">✕</button>
    </div>
    <div class="modal-body">
      <p id="confirm-modal-body" style="color:var(--muted);font-size:.9rem;margin:0;"></p>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" id="confirm-cancel-btn" onclick="closeConfirmModal()">Cancel</button>
      <button onclick="confirmAction()" id="confirm-ok-btn"
        style="padding:.55rem 1.1rem;border-radius:10px;border:none;background:#ef4444;color:#fff;font-family:var(--font);font-size:.85rem;font-weight:600;cursor:pointer;">
        <span id="confirm-ok-text">Delete</span>
      </button>
    </div>
  </div>
</div>

<!-- MODAL: NEW ASSIGN -->
<div class="modal-overlay" id="modal-assign">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-assign-title">Nouveau devoir</h3>
      <button class="btn-close" onclick="closeModal('assign')" aria-label="Close">✕</button>
    </div>
    <div class="form-group">
      <label id="mlbl-course">Classe <span style="color:var(--red)">*</span></label>
      <select id="new-assign-course" style="width:100%;padding:.8rem 1rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;transition:border-color .2s;">
        <option value="" id="assign-course-loading-opt">— Loading classes… —</option>
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
          <option data-fr="Écriture"         data-en="Writing">Écriture</option>
          <option data-fr="Grammaire"        data-en="Grammar">Grammaire</option>
          <option data-fr="Vocabulaire"      data-en="Vocabulary">Vocabulaire</option>
          <option data-fr="Expression orale" data-en="Speaking">Expression orale</option>
          <option data-fr="Écoute"           data-en="Listening">Écoute</option>
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
  <div class="modal" style="max-width:680px;max-height:90vh;overflow-y:auto;">
    <div class="modal-header">
      <h3 id="modal-quiz-title">Créer un quiz</h3>
      <button class="btn-close" onclick="closeModal('quiz')" aria-label="Close">✕</button>
    </div>
    <!-- Quiz info -->
    <div class="grid-2" style="gap:1rem;margin-bottom:.75rem;">
      <div class="form-group" style="grid-column:1/-1;">
        <label id="qmlbl-title">Titre du quiz</label>
        <input type="text" id="new-quiz-title" placeholder="Ex: Grammaire – Unité 5">
      </div>
      <div class="form-group">
        <label id="qmlbl-group">Groupe</label>
        <select id="new-quiz-group" style="width:100%;padding:.65rem 1rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.85rem;outline:none;"></select>
      </div>
      <div class="form-group">
        <label id="qmlbl-time">Durée (min, 0 = illimité)</label>
        <input type="number" id="new-quiz-time" value="0" min="0" max="120">
      </div>
    </div>
    <!-- Questions builder -->
    <div style="border-top:1px solid var(--border);padding-top:.75rem;margin-bottom:.75rem;">
      <div style="font-family:var(--font);font-size:.8rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.65rem;" id="qmlbl-questions">Questions</div>
      <div id="quiz-questions-builder" style="display:flex;flex-direction:column;gap:1rem;"></div>
      <button onclick="addQuizQuestion()" style="margin-top:.75rem;background:rgba(255,255,255,.05);border:1px dashed var(--border);border-radius:10px;color:var(--muted);cursor:pointer;width:100%;padding:.65rem;font-family:var(--font-body);font-size:.85rem;transition:all .2s;" id="btn-add-question">+ Ajouter une question</button>
    </div>
    <div style="color:var(--red);font-size:.8rem;min-height:1.2rem;" id="quiz-create-error"></div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('quiz')" id="quiz-modal-cancel">Annuler</button>
      <button class="btn-primary" onclick="submitNewQuiz()" id="quiz-modal-submit">Créer</button>
    </div>
  </div>
</div>

<!-- MESSAGE MODAL -->
<div class="modal-overlay" id="modal-message">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <h3 id="msg-modal-title">Message</h3>
      <button class="btn-close" onclick="closeModal('message')" aria-label="Close">✕</button>
    </div>
    <p style="color:var(--muted);font-size:.83rem;margin-bottom:.75rem;" id="msg-modal-to"></p>
    <div class="form-group">
      <label id="msg-lbl">Message</label>
      <textarea id="msg-text" rows="4" maxlength="500" placeholder="Écrivez votre message…"
        style="width:100%;padding:.75rem 1rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.88rem;outline:none;resize:vertical;box-sizing:border-box;"></textarea>
    </div>
    <div style="color:var(--red);font-size:.8rem;min-height:1rem;" id="msg-error"></div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('message')" id="msg-cancel">Annuler</button>
      <button class="btn-primary" onclick="sendMessage()" id="msg-submit">Envoyer</button>
    </div>
  </div>
</div>

  <!-- LESSON POSTS PAGE -->
  <div class="page" id="page-posts">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="posts-page-title">Notes de cours</h2>
      <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="posts-page-sub">Publiez vos résumés et liens après chaque session Zoom</p>
    </div>

    <!-- Create form -->
    <div class="card" style="margin-bottom:1.5rem;">
      <div class="card-title" id="posts-form-title">Nouvelle note de cours</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
        <div class="form-group">
          <label id="posts-lbl-course">Classe</label>
          <select id="post-course-select" style="width:100%;padding:.75rem 1rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;cursor:pointer;">
            <option value="">— Choisir la classe —</option>
          </select>
        </div>
        <div class="form-group">
          <label id="posts-lbl-date">Date de la séance</label>
          <input type="date" id="post-date" style="width:100%;padding:.75rem 1rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;">
        </div>
      </div>
      <div class="form-group" style="margin-bottom:1rem;">
        <label id="posts-lbl-title">Titre de la séance</label>
        <input type="text" id="post-title" placeholder="ex: Session 12 – Passé composé" style="width:100%;padding:.75rem 1rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;">
      </div>
      <div class="form-group" style="margin-bottom:1rem;">
        <label id="posts-lbl-link">Lien (tableau blanc, slides…)</label>
        <input type="url" id="post-link" placeholder="https://..." style="width:100%;padding:.75rem 1rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;">
      </div>
      <div class="form-group" style="margin-bottom:1.25rem;">
        <label id="posts-lbl-notes">Notes / Résumé (optionnel)</label>
        <textarea id="post-notes" rows="4" placeholder="Points clés de la séance, rappels, vocabulaire…" style="width:100%;padding:.75rem 1rem;background:rgba(30,27,75,.04);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;resize:vertical;"></textarea>
      </div>
      <div id="post-form-error" style="display:none;color:var(--red);font-size:.82rem;margin-bottom:.75rem;"></div>
      <button class="btn-primary" onclick="submitPost()" id="post-submit-btn">
        <span id="post-submit-lbl">Publier la note</span>
      </button>
    </div>

    <!-- Posts list -->
    <div id="posts-list"></div>
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

    <!-- Group selector (populated from api_classes.php) -->
    <div id="att-group-selector-wrap" style="margin-bottom:.5rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
      <label style="font-family:var(--font);font-size:.78rem;font-weight:700;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;white-space:nowrap;" id="att-group-lbl">Groupe</label>
      <select id="att-group-select" onchange="attSelectGroup(this.value)">
        <option value="">— Tous les étudiants —</option>
      </select>
      <span id="att-group-loading" style="display:none;font-size:.8rem;color:var(--muted);">Loading…</span>
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

<!-- NOTIFICATION PANEL -->
<div class="notif-panel" id="notif-panel" onclick="event.stopPropagation()">
  <div class="notif-header">
    <h4 id="notif-panel-title">Notifications</h4>
    <button class="btn-close" onclick="closeNotifPanel()" aria-label="Close">✕</button>
  </div>
  <div id="notif-list"><div style="padding:1.5rem;text-align:center;color:var(--muted);font-size:.85rem;">Chargement…</div></div>
</div>

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
  { id:1, title_fr:'Dissertation : Mes objectifs professionnels', title_en:'Essay: My Career Goals', due_fr:'9 mai', submitted:18, total:24, subject_fr:'Écriture', subject_en:'Writing' },
  { id:2, title_fr:'Exercice de compréhension orale #3', title_en:'Listening Exercise #3', due_fr:'12 mai', submitted:10, total:24, subject_fr:'Écoute', subject_en:'Listening' },
  { id:3, title_fr:'Présentation orale – Sujet libre', title_en:'Oral Presentation – Free Topic', due_fr:'15 mai', submitted:5, total:24, subject_fr:'Expression', subject_en:'Speaking' },
  { id:4, title_fr:'Rédaction Email professionnel', title_en:'Professional Email Writing', due_fr:'1 mai', submitted:22, total:24, subject_fr:'Écriture', subject_en:'Writing' },
  { id:5, title_fr:'Quiz de grammaire de base', title_en:'Basic Grammar Quiz', due_fr:'28 avril', submitted:24, total:24, subject_fr:'Grammaire', subject_en:'Grammar' },
];
const QUIZZES_T = [
  { id:1, title_fr:'Quiz Grammaire Unité 1', title_en:'Grammar Quiz Unit 1', qs:15, min:20, attempts:22, avg:78 },
  { id:2, title_fr:'Vocabulaire Unité 3', title_en:'Vocabulary Unit 3', qs:20, min:25, attempts:18, avg:74 },
  { id:3, title_fr:'Compréhension écrite #2', title_en:'Reading Comprehension #2', qs:10, min:15, attempts:24, avg:82 },
  { id:4, title_fr:'Grammaire – Temps passés', title_en:'Grammar – Past Tenses', qs:12, min:18, attempts:20, avg:71 },
  { id:5, title_fr:'Expression orale – Évaluation', title_en:'Speaking – Assessment', qs:8, min:12, attempts:16, avg:68 },
];

/* ── PAGE PERSISTENCE — synchronous, runs before first paint ── */
(function() {
  const _valid = ['home','students','courses','assignments','posts','quizzes','grades','settings','attendance'];
  const _saved = sessionStorage.getItem('upskill_page_t');
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
let activePage = sessionStorage.getItem('upskill_page_t') || 'home';

/* ── TRANSLATIONS ── */
const T = {
  fr: {
    topbarTitle: { home:'Tableau de bord Professeur', students:'Étudiants', courses:'Classes', assignments:'Devoirs', posts:'Notes de cours', quizzes:'Quiz', grades:'Notes & Résultats', settings:'Paramètres', attendance:'Présences' },
    navMain:'Principal', navAccount:'Compte',
    navHome:'Tableau de bord', navStudents:'Classes', navAssign:'Devoirs', navPosts:'Notes de cours', navQuiz:'Quiz', navGrades:'Notes & Résultats', navSet:'Paramètres',
    postsPageTitle:'Notes de cours', postsPageSub:'Publiez vos résumés et liens après chaque session Zoom',
    postsFormTitle:'Nouvelle note de cours',
    postsLblCourse:'Classe', postsLblTitle:'Titre de la séance', postsLblDate:'Date de la séance',
    postsLblLink:'Lien (tableau blanc, slides…)', postsLblNotes:'Notes / Résumé (optionnel)',
    postsSubmitBtn:'Publier la note', postsEmptyTitle:'Aucune note publiée',
    postsEmptyTxt:'Publiez votre premier résumé de cours ci-dessus.',
    postsDeleteConfirm:'Supprimer cette note ?', toastPostPublished:'Note publiée !', toastPostDeleted:'Note supprimée.', editPost:'Mettre à jour',
    teacherChip:'Prof', roleLabel:'Professeur', logout:'Déconnexion',
    welcomeMsg:'Bonjour, ', welcomeSub:'',
    stat1:'Étudiants actifs', stat2:'Devoirs à corriger', stat3:'Moyenne de la classe', stat4:'Devoirs publiés',
    btnNewAssign:'+ Nouveau devoir', btnNewQuiz:'+ Créer un quiz',
    classProgTitle:'Progression de la classe', cp1:'Taux de soumission', cp2:'Taux de correction', cp3:'Score moyen',
    attentionTitle:'Étudiants à surveiller ⚠️',
    activityTitle:'Activité récente', activityEmpty:'Aucune activité récente.',
    todayClassesTitle:"📅 Cours d'aujourd'hui", todayNoClasses:'Aucun cours prévu aujourd\'hui.', todayZoomPlaceholder:'https://zoom.us/j/...', todayZoomLbl:'Lien Zoom', todayZoomHint:'Ce lien sera visible par vos étudiants dans "Ma classe".', todayZoomSave:'Enregistrer', todaySaving:'…', todayZoomSaved:'✔ Enregistré', todayStudents:'étudiant(s)',
    studentsPageTitle:'Étudiants', studentsPageSub:'24 étudiants inscrits · Session 2',
    thName:'Étudiant', thProgress:'Progression', thAssigns:'Devoirs', thAvg:'Moyenne', thStatus:'Statut',
    statusGood:'Bon niveau', statusWarn:'À surveiller', statusLow:'En difficulté',
    assignPageTitle:'Devoirs', assignPageSub:'5 devoirs actifs · 8 soumissions en attente de correction',
    newAssignLbl:'Nouveau devoir',
    quizPageTitle:'Quiz', quizPageSub:'Gérez et créez des quiz pour vos étudiants',
    newQuizLbl:'Créer un quiz',
    gradesPageTitle:'Notes & Résultats', gradesPageSub:"Vue d'ensemble des performances de la classe",
    distTitle:'Distribution des notes', topTitle:'Top étudiants',
    quizScoresTitle:'Notes par devoir',
    gthStudent:'Étudiant', gthAssign:'Devoir', gthScore:'Note', gthDate:'Date',
    noGradesYet:'Aucune note pour le moment.',
    notifEmpty:'Aucune notification',
    settingsTitle:'Paramètres', profileTitle:'Profil professeur', settingsRole:'Professeur · Anglais Général',
    lblFullname:'Nom complet', saveBtn:'Enregistrer', prefTitle:'Préférences', prefTxt:"Utilisez le sélecteur de langue dans la barre latérale pour basculer entre le Français et l'Anglais.",
    lblZoomUrl:'Lien Zoom (cours en ligne)', lblZoomHint:'Ce lien sera affiché dans le tableau de bord de vos étudiants.',
    pwdCardTitle:'Changer le mot de passe', lblPwdCurrent:'Mot de passe actuel', lblPwdNew:'Nouveau mot de passe', lblPwdConfirm:'Confirmer le mot de passe', pwdBtn:'Changer le mot de passe', toastPwdChanged:'Mot de passe mis à jour !',
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
    attGroupLbl:'Groupe',
    attSavingLbl:'Enregistrement…', toastAttError:'Erreur lors de la sauvegarde. Réessayez.',
    noAssignments:'Aucun devoir pour le moment.', noAssignmentsSub:'Aucun devoir',
    pendingBadge:'à corriger', gradedBadge:'Corrigé', deleteTitle:'Supprimer',
    deleteConfirm:'Supprimer ce devoir ?', toastAssignDeleted:'Devoir supprimé',
    toastNetError:'❌ Erreur réseau', toastServerError:'Erreur serveur',
    toastErrorPrefix:'Erreur : ',
    loading:'Chargement…', errorLoading:'Erreur de chargement',
    subBack:'← Retour aux devoirs', dueLbl2:'Échéance : ', noText:'(Pas de texte)',
    scorePlaceholder:'Note /100', commentPlaceholder:"Commentaire pour l'étudiant…",
    yourComment:'Feedback enregistré :', saveGrade:'Enregistrer', gradeSectionLbl:'Corriger :',
    toastGraded:'✅ Correction enregistrée', noSubmissions:'Aucune soumission pour le moment.',
    noClassAssigned:'— Aucune classe assignée —', chooseClass:'— Choisir la classe —',
    classLabel:'Classe', validateChooseClass:'Veuillez choisir une classe.',
    validateTitleRequired:'Le titre est requis.', networkError:'❌ Erreur réseau',
    backCourses2:'← Retour', groupStudentsTitle:'Étudiants du groupe',
    studentUnit:'étudiant(s)', noStudentsInGroup:'Aucun étudiant dans ce groupe',
    levelWord:'Niveau', groupWord:'Groupe', allClasses:'Classes',
    allStudents:'— Tous les étudiants —', studentSuffix:' étudiant(s)',
    niveauSuffix:(n) => n + (n > 1 ? ' niveaux' : ' niveau'),
    groupeSuffix:(n) => n + (n > 1 ? ' groupes' : ' groupe'),
    changePhotoLbl:'Changer la photo',
    imageTooLarge:'Image trop grande (max 2 Mo)', photoUpdated:'Photo mise à jour ✓',
    postsLoading:'Chargement…', postsErrorPrefix:'Erreur : ',
    openLink:'Ouvrir le lien', allGraded:'Tout corrigé ✅',
    phAssignTitle:'Ex : Dissertation – Unité 4',
    phQuizTitle:'Ex : Grammaire – Unité 5',
    phAssignDesc:'Instructions pour les étudiants…',
    phPostTitle:'ex: Séance 12 – Passé composé',
    phPostNotes:'Points clés, rappels, vocabulaire…',
  },
  en: {
    topbarTitle: { home:'Teacher Dashboard', students:'Students', courses:'Classes', assignments:'Assignments', posts:'Lesson Notes', quizzes:'Quizzes', grades:'Grades & Results', settings:'Settings', attendance:'Attendance' },
    navMain:'Main', navAccount:'Account',
    navHome:'Dashboard', navStudents:'Classes', navAssign:'Assignments', navPosts:'Lesson Notes', navQuiz:'Quizzes', navGrades:'Grades & Results', navSet:'Settings',
    postsPageTitle:'Lesson Notes', postsPageSub:'Post your summaries and links after each Zoom session',
    postsFormTitle:'New lesson note',
    postsLblCourse:'Class', postsLblTitle:'Session title', postsLblDate:'Session date',
    postsLblLink:'Link (whiteboard, slides…)', postsLblNotes:'Notes / Summary (optional)',
    postsSubmitBtn:'Publish note', postsEmptyTitle:'No notes published yet',
    postsEmptyTxt:'Publish your first lesson summary above.',
    postsDeleteConfirm:'Delete this note?', toastPostPublished:'Note published!', toastPostDeleted:'Note deleted.', editPost:'Update note',
    teacherChip:'Teacher', roleLabel:'Teacher', logout:'Log out',
    welcomeMsg:'Hello, ', welcomeSub:'',
    stat1:'Active students', stat2:'Assignments to grade', stat3:'Class average', stat4:'Assignments posted',
    btnNewAssign:'+ New assignment', btnNewQuiz:'+ Create quiz',
    classProgTitle:'Class progress', cp1:'Submission rate', cp2:'Grading rate', cp3:'Average score',
    attentionTitle:'Students to watch ⚠️',
    activityTitle:'Recent activity', activityEmpty:'No recent activity.',
    todayClassesTitle:'📅 Classes Today', todayNoClasses:'No classes scheduled for today.', todayZoomPlaceholder:'https://zoom.us/j/...', todayZoomLbl:'Zoom link', todayZoomHint:"This link will be visible to your students in 'My Class'.", todayZoomSave:'Save', todaySaving:'…', todayZoomSaved:'✔ Saved', todayStudents:'student(s)',
    studentsPageTitle:'Students', studentsPageSub:'24 enrolled students · Session 2',
    thName:'Student', thProgress:'Progress', thAssigns:'Assignments', thAvg:'Average', thStatus:'Status',
    statusGood:'On track', statusWarn:'Needs attention', statusLow:'Struggling',
    assignPageTitle:'Assignments', assignPageSub:'5 active assignments · 8 submissions pending review',
    newAssignLbl:'New assignment',
    quizPageTitle:'Quizzes', quizPageSub:'Manage and create quizzes for your students',
    newQuizLbl:'Create quiz',
    gradesPageTitle:'Grades & Results', gradesPageSub:'Overview of class performance',
    distTitle:'Grade distribution', topTitle:'Top students',
    quizScoresTitle:'Grades by assignment',
    gthStudent:'Student', gthAssign:'Assignment', gthScore:'Score', gthDate:'Date',
    noGradesYet:'No grades yet.',
    notifEmpty:'No notifications',
    settingsTitle:'Settings', profileTitle:'Teacher profile', settingsRole:'Teacher · General English',
    lblFullname:'Full name', saveBtn:'Save', prefTitle:'Preferences', prefTxt:'Use the language selector in the sidebar to switch between French and English.',
    lblZoomUrl:'Zoom link (online class)', lblZoomHint:"This link will appear in your students' dashboard.",
    pwdCardTitle:'Change password', lblPwdCurrent:'Current password', lblPwdNew:'New password', lblPwdConfirm:'Confirm password', pwdBtn:'Change password', toastPwdChanged:'Password updated!',
    modalAssignTitle:'New assignment', mlblTitle:'Assignment title', mlblDesc:'Description', mlblDue:'Due date', mlblSubject:'Subject', modalCancel:'Cancel', modalSubmit:'Publish',
    modalQuizTitle:'Create quiz', qmlblTitle:'Quiz title', qmlblQs:'Number of questions', qmlblTime:'Duration (min)', quizCancel:'Cancel', quizSubmit:'Create',
    toastAssignPublished:'Assignment published!', toastQuizCreated:'Quiz created!', toastSaved:'Profile updated!',
    toastAttSaved:'Attendance saved successfully!',
    submittedLbl:'submitted out of', viewGradeBtn:'Grade', attemptsLbl:'attempts', avgLbl:'avg.',
    subjectLbl:'Subject:', dueLbl:'Due:',
    gradeRanges: ['90-100%', '75-89%', '60-74%', '<60%'],
    navAttendance:'Attendance',
    topbarAttendance:'Attendance',
    attPageTitle:'Attendance', attPageSub:'Check boxes to mark attendance for each session',
    attMarkAllLbl:'Mark all present', attClearAllLbl:'Clear all', attSaveLbl:'Save',
    attStatPresentLbl:'Total present', attStatAbsentLbl:'Total absent',
    attStatRateLbl:'Attendance rate', attStatAtRiskLbl:'At-risk students (<70%)',
    attUnsavedTxt:'⚡ Unsaved changes', attSaveNowLbl:'Save now',
    attThStudent:'Student', attThSessions:'Sessions (1–20)', attThTotal:'Attendance',
    attGroupLbl:'Group',
    attSavingLbl:'Saving…', toastAttError:'Error saving. Please try again.',
    noAssignments:'No assignments yet.', noAssignmentsSub:'No assignments',
    pendingBadge:'to grade', gradedBadge:'Graded', deleteTitle:'Delete',
    deleteConfirm:'Delete this assignment?', toastAssignDeleted:'Assignment deleted',
    toastNetError:'❌ Network error', toastServerError:'Server error',
    toastErrorPrefix:'Error: ',
    loading:'Loading…', errorLoading:'Error loading',
    subBack:'← Back to assignments', dueLbl2:'Due: ', noText:'(No text)',
    scorePlaceholder:'Score /100', commentPlaceholder:'Comment for student…',
    yourComment:'Saved feedback:', saveGrade:'Save', gradeSectionLbl:'Grade:',
    toastGraded:'✅ Grade saved', noSubmissions:'No submissions yet.',
    noClassAssigned:'— No class assigned —', chooseClass:'— Choose a class —',
    classLabel:'Class', validateChooseClass:'Please choose a class.',
    validateTitleRequired:'Title is required.', networkError:'❌ Network error',
    backCourses2:'← Back', groupStudentsTitle:'Group students',
    studentUnit:'student(s)', noStudentsInGroup:'No students in this group',
    levelWord:'Level', groupWord:'Group', allClasses:'Classes',
    allStudents:'— All students —', studentSuffix:' student(s)',
    niveauSuffix:(n) => n + (n > 1 ? ' levels' : ' level'),
    groupeSuffix:(n) => n + (n > 1 ? ' groups' : ' group'),
    changePhotoLbl:'Change photo',
    imageTooLarge:'Image too large (max 2 MB)', photoUpdated:'Photo updated ✓',
    postsLoading:'Loading…', postsErrorPrefix:'Error: ',
    openLink:'Open link', allGraded:'All graded ✅',
    phAssignTitle:'e.g. Essay – Unit 4',
    phQuizTitle:'e.g. Grammar – Unit 5',
    phAssignDesc:'Instructions for students…',
    phPostTitle:'e.g. Session 12 – Present Perfect',
    phPostNotes:'Key points, reminders, vocabulary…',
  }
};

function t(key) { return T[currentLang][key] || key; }

function setLang(lang) {
  currentLang = lang;
  sessionStorage.setItem('upskill_lang', lang);
  document.documentElement.setAttribute('lang', lang);
  document.getElementById('pill-fr').className = 'lang-pill' + (lang === 'fr' ? ' active' : '');
  document.getElementById('pill-en').className = 'lang-pill' + (lang === 'en' ? ' active' : '');
  applyTranslations();
  updateHomeStats();
}

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebar-backdrop').classList.toggle('open');
}

function applyTranslations() {
  const tr = T[currentLang];
  const set = (id, val) => { const el = document.getElementById(id); if(el) el.textContent = val; };
  // Update skip link text
  const skipLink = document.getElementById('skip-link');
  if (skipLink) skipLink.textContent = currentLang==='en'?'Skip to content':'Aller au contenu';
  set('topbar-title', tr.topbarTitle[activePage] || tr.topbarTitle.home);
  set('teacher-chip-lbl', tr.teacherChip);
  set('nav-main-label', tr.navMain); set('nav-account-label', tr.navAccount);
  set('nav-home-lbl', tr.navHome); set('nav-students-lbl', tr.navStudents);
  set('nav-assign-lbl', tr.navAssign); set('nav-posts-lbl', tr.navPosts); set('nav-quiz-lbl', tr.navQuiz);
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
  set('today-classes-title', tr.todayClassesTitle);
  if (_cachedTodayGroups !== null) renderTodayClasses(); else loadTodayClasses();
  set('students-page-title', tr.studentsPageTitle); set('students-page-sub', tr.studentsPageSub);
  set('th-name', tr.thName); set('th-progress', tr.thProgress); set('th-assigns', tr.thAssigns); set('th-avg', tr.thAvg); set('th-status', tr.thStatus);
  set('assign-page-title', tr.assignPageTitle); set('assign-page-sub', tr.assignPageSub);
  set('quiz-page-title', tr.quizPageTitle); set('quiz-page-sub', tr.quizPageSub);
  set('grades-page-title', tr.gradesPageTitle); set('grades-page-sub', tr.gradesPageSub);
  set('dist-title', tr.distTitle); set('top-title', tr.topTitle); set('quiz-scores-title', tr.quizScoresTitle);
  set('gth-student', tr.gthStudent); set('gth-assign', tr.gthAssign); set('gth-score', tr.gthScore); set('gth-date', tr.gthDate);
  set('settings-title', tr.settingsTitle); set('profile-title', tr.profileTitle); set('settings-role', tr.settingsRole);
  set('change-photo-lbl', tr.changePhotoLbl);
  const avUploadBtn = document.getElementById('avatar-upload-btn');
  if (avUploadBtn) avUploadBtn.title = tr.changePhotoLbl;
  set('lbl-fullname', tr.lblFullname); set('save-btn', tr.saveBtn); set('pref-title', tr.prefTitle); set('pref-txt', tr.prefTxt);
  set('pwd-card-title', tr.pwdCardTitle); set('lbl-pwd-current', tr.lblPwdCurrent); set('lbl-pwd-new', tr.lblPwdNew); set('lbl-pwd-confirm', tr.lblPwdConfirm); set('pwd-btn-text', tr.pwdBtn);
  set('modal-assign-title', tr.modalAssignTitle); set('mlbl-title', tr.mlblTitle); set('mlbl-desc', tr.mlblDesc); set('mlbl-due', tr.mlblDue); set('mlbl-subject', tr.mlblSubject);
  const lbl = document.getElementById('mlbl-course');
  if (lbl) lbl.innerHTML = T[currentLang].classLabel + ' <span style="color:var(--red)">*</span>';
  populateCourseSelect(); // repopulate with correct language
  set('modal-cancel', tr.modalCancel); set('modal-submit', tr.modalSubmit);
  set('modal-quiz-title', tr.modalQuizTitle); set('qmlbl-title', tr.qmlblTitle); set('qmlbl-qs', tr.qmlblQs); set('qmlbl-time', tr.qmlblTime);
  set('quiz-modal-cancel', tr.quizCancel); set('quiz-modal-submit', tr.quizSubmit);
  // Posts page
  set('posts-page-title', tr.postsPageTitle); set('posts-page-sub', tr.postsPageSub);
  set('posts-form-title', tr.postsFormTitle);
  set('posts-lbl-course', tr.postsLblCourse); set('posts-lbl-title', tr.postsLblTitle);
  set('posts-lbl-date', tr.postsLblDate); set('posts-lbl-link', tr.postsLblLink);
  set('posts-lbl-notes', tr.postsLblNotes); set('post-submit-lbl', tr.postsSubmitBtn);
  populatePostCourseSelect();
  const phAssignTitleEl = document.getElementById('new-assign-title'); if(phAssignTitleEl) phAssignTitleEl.placeholder = tr.phAssignTitle || '';
  const phQuizTitleEl = document.getElementById('new-quiz-title'); if(phQuizTitleEl) phQuizTitleEl.placeholder = tr.phQuizTitle || '';
  const phDesc = document.getElementById('new-assign-desc'); if(phDesc) phDesc.placeholder = tr.phAssignDesc;
  const phPostTitle = document.getElementById('post-title'); if(phPostTitle) phPostTitle.placeholder = tr.phPostTitle;
  const phPostNotes = document.getElementById('post-notes'); if(phPostNotes) phPostNotes.placeholder = tr.phPostNotes;
  // Attendance
  set('nav-attendance-lbl', tr.navAttendance);
  set('att-page-title', tr.attPageTitle); set('att-page-sub', tr.attPageSub);
  set('att-mark-all-lbl', tr.attMarkAllLbl); set('att-clear-all-lbl', tr.attClearAllLbl); set('att-save-lbl', tr.attSaveLbl);
  set('att-stat-present-lbl', tr.attStatPresentLbl); set('att-stat-absent-lbl', tr.attStatAbsentLbl);
  set('att-stat-rate-lbl', tr.attStatRateLbl); set('att-stat-at-risk-lbl', tr.attStatAtRiskLbl);
  set('att-unsaved-txt', tr.attUnsavedTxt); set('att-save-now-lbl', tr.attSaveNowLbl);
  set('att-th-student', tr.attThStudent); set('att-th-sessions', tr.attThSessions); set('att-th-total', tr.attThTotal);
  set('att-group-lbl', tr.attGroupLbl);
  renderAttention(); renderAssignments(); renderQuizzes(); renderGrades();
  renderAttendance(); renderCourses(); renderTeacherGroups(); renderTeacherSchedule();
  if (_cachedPosts !== null) renderPostsList(_cachedPosts);
  loadActivityFeed();
  // Translate subject dropdown options
  document.querySelectorAll('#new-assign-subject option').forEach(opt => {
    const txt = opt.getAttribute('data-' + currentLang) || opt.getAttribute('data-fr');
    if (txt) opt.textContent = txt;
  });
  const _lh = document.getElementById('lang-hide'); if (_lh) _lh.remove();
}

function navigate(page, el) {
  // Close any open overlays so they don't obscure the destination page
  closeNotifPanel(false);
  profileMenuOpen = false;
  document.getElementById('profile-menu').classList.remove('open');
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('page-' + page).classList.add('active');
  if(el) el.classList.add('active');
  else {
    const navIdMap = {courses:'nav-students',assignments:'nav-assign',quizzes:'nav-quiz',settings:'nav-set',posts:'nav-posts',grades:'nav-grades',attendance:'nav-attendance',home:'nav-home'};
    const navEl = document.getElementById(navIdMap[page] || 'nav-' + page);
    if (navEl) navEl.classList.add('active');
  }
  activePage = page;
  sessionStorage.setItem('upskill_page_t', page);
  document.getElementById('topbar-title').textContent = T[currentLang].topbarTitle[page] || T[currentLang].topbarTitle.home;
  /* Show "+ New assignment" only on relevant pages */
  const btnNA = document.getElementById('btn-new-assign');
  if (btnNA) btnNA.style.display = (page === 'assignments' || page === 'home') ? '' : 'none';

  if (page === 'courses')    { teacherClassView='types'; teacherSelType=null; teacherSelLevel=null; _coursesTabUI('groups'); renderCourses(); loadTeacherGroups(); }
  if (page === 'attendance') { if (teacherGroups.length === 0) loadTeacherGroups(); }
  if (page === 'posts')      loadPosts();
  if (page === 'grades')     loadGrades();
  if (page === 'home')       { loadActivityFeed(); loadTodayClasses(); }
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
      <div class="student-avatar-sm">${escHtml(s.init)}</div>
      <div style="flex:1">
        <div style="font-family:var(--font);font-size:.88rem;font-weight:600;">${escHtml(s.name)}</div>
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
      <td><div style="display:flex;align-items:center;gap:.75rem;"><div class="student-avatar-sm">${escHtml(s.init)}</div><span>${escHtml(s.name)}</span></div></td>
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
  // Update Assignments sidebar badge (shows pending-review count)
  const ba = document.getElementById('badge-assignments');
  if (ba) {
    const pending = ASSIGNMENTS_LIVE.reduce((s,a) => s + Math.max(0,(parseInt(a.submitted_count)||0)-(parseInt(a.graded_count)||0)), 0);
    ba.textContent = pending; ba.style.display = pending ? '' : 'none';
  }
  renderAssignments();
}

/* ── Update home-page stat cards with live data ── */
function updateHomeStats() {
  const tr = T[currentLang];

  // Stat 1: total students
  const el1 = document.getElementById('stat1-val');
  if (el1) el1.textContent = STUDENTS.length > 0 ? STUDENTS.length : '0';

  // Stat 2: submissions pending review (submitted but not yet graded)
  const pendingReview = ASSIGNMENTS_LIVE.reduce((acc, a) => {
    return acc + Math.max(0, parseInt(a.submitted_count || 0) - parseInt(a.graded_count || 0));
  }, 0);
  const el2 = document.getElementById('stat2-val');
  if (el2) el2.textContent = pendingReview;

  // Stat 3: average score from GRADES_LIVE if available
  const el3 = document.getElementById('stat3-val');
  if (el3) {
    const scored = GRADES_LIVE.filter(g => g.score !== null && g.score !== undefined);
    el3.textContent = scored.length ? Math.round(scored.reduce((s,g)=>s+parseInt(g.score),0)/scored.length)+'%' : '—';
  }

  // Stat 4: total active assignments
  const el4 = document.getElementById('stat4-val');
  if (el4) el4.textContent = ASSIGNMENTS_LIVE.length;

  // Update welcome subtitle with live numbers
  const subEl = document.getElementById('welcome-sub');
  if (subEl) {
    const classes = teacherGroups.length;
    const scoredGrades = (GRADES_LIVE || []).filter(g => g.score !== null && g.score !== undefined);
    const avgScore = scoredGrades.length ? Math.round(scoredGrades.reduce((s,g) => s + parseInt(g.score), 0) / scoredGrades.length) : null;
    const avgPart = avgScore !== null ? ` · ${currentLang==='fr'?'Moyenne classe':'Class avg'}: ${avgScore}%` : '';
    const groupsPart = currentLang === 'fr' ? `${classes} groupe${classes!==1?'s':''} actif${classes!==1?'s':''}` : `${classes} active group${classes!==1?'s':''}`;
    subEl.textContent = `${pendingReview} ${tr.stat2.toLowerCase()} · ${groupsPart}${avgPart}`;
  }

  // Update class progress bars from real assignment data
  const totalSlots    = ASSIGNMENTS_LIVE.reduce((s,a) => s + Math.max(0, parseInt(a.total_students||a.submitted_count||0)), 0);
  const totalSubmitted= ASSIGNMENTS_LIVE.reduce((s,a) => s + Math.max(0, parseInt(a.submitted_count||0)), 0);
  const totalGraded   = ASSIGNMENTS_LIVE.reduce((s,a) => s + Math.max(0, parseInt(a.graded_count||0)), 0);

  const subRate   = totalSlots   > 0 ? Math.round((totalSubmitted / totalSlots)   * 100) : 0;
  const gradeRate = totalSubmitted > 0 ? Math.round((totalGraded   / totalSubmitted) * 100) : 0;

  const v1 = document.getElementById('cp-val1'); const b1 = document.getElementById('cp-bar1');
  const v2 = document.getElementById('cp-val2'); const b2 = document.getElementById('cp-bar2');
  const v3 = document.getElementById('cp-val3'); const b3 = document.getElementById('cp-bar3');

  if (v1) v1.textContent = subRate   + '%'; if (b1) b1.style.width = subRate   + '%';
  if (v2) v2.textContent = gradeRate + '%'; if (b2) b2.style.width = gradeRate + '%';
  const scoredG = (GRADES_LIVE || []).filter(g => g.score !== null && g.score !== undefined);
  const avgG = scoredG.length ? Math.round(scoredG.reduce((s,g) => s + parseInt(g.score), 0) / scoredG.length) : null;
  if (v3) v3.textContent = avgG !== null ? avgG + '%' : '—';
  if (b3) b3.style.width = avgG !== null ? avgG + '%' : '0%';
}

function renderAssignments() {
  const list = document.getElementById('assign-teacher-list');
  if (!list) return;
  const tr   = T[currentLang];
  const lang = currentLang;

  if (ASSIGNMENTS_LIVE.length === 0) {
    list.innerHTML = `<div style="color:var(--muted);font-size:.88rem;padding:1rem 0;text-align:center;">${tr.noAssignments}</div>`;
    const sub = document.getElementById('assign-page-sub'); if (sub) sub.textContent = tr.noAssignmentsSub;
    return;
  }

  const pending = ASSIGNMENTS_LIVE.reduce((s,a) => s + Math.max(0,(parseInt(a.submitted_count)||0)-(parseInt(a.graded_count)||0)), 0);
  const sub = document.getElementById('assign-page-sub');
  if (sub) sub.textContent = `${ASSIGNMENTS_LIVE.length} ${tr.newAssignLbl.replace('+ ','')} · ${pending>0?pending+' '+tr.pendingBadge:tr.allGraded}`;
  const s2 = document.getElementById('stat2-val'); if (s2) s2.textContent = pending;

  list.innerHTML = ASSIGNMENTS_LIVE.map(a => {
    const title     = lang==='en' ? (a.title_en||a.title_fr) : a.title_fr;
    const subject   = lang==='en' ? (a.subject_en||a.subject_fr||'') : (a.subject_fr||'');
    const className = lang==='en' ? (a.group_name_en||a.group_name_fr||'') : (a.group_name_fr||'');
    const submitted = parseInt(a.submitted_count)||0;
    const total     = parseInt(a.total_students)||0;
    const graded    = parseInt(a.graded_count)||0;
    const ungraded  = submitted - graded;
    const dueStr    = a.due_date ? new Date(a.due_date).toLocaleDateString(lang==='fr'?'fr-FR':'en-GB',{day:'numeric',month:'short'}) : '—';
    const pendingBadge = ungraded > 0
      ? `<span style="background:rgba(245,197,66,.15);color:var(--yellow);border:1px solid rgba(245,197,66,.3);font-size:.7rem;font-weight:700;padding:.15rem .5rem;border-radius:100px;font-family:var(--font);">${ungraded} ${tr.pendingBadge}</span>`
      : `<span style="background:rgba(62,207,120,.1);color:var(--green);border:1px solid rgba(62,207,120,.25);font-size:.7rem;font-weight:700;padding:.15rem .5rem;border-radius:100px;font-family:var(--font);">✓ ${tr.gradedBadge}</span>`;
    return `<div class="assign-row">
      <div class="assign-info">
        <div class="assign-title-t">${escHtml(title)} ${pendingBadge}</div>
        <div class="assign-meta-t">
          ${className ? `<span>👥 ${escHtml(className)}</span>` : ''}
          ${dueStr!=='—' ? `<span>📅 ${tr.dueLbl} ${dueStr}</span>` : ''}
          ${subject   ? `<span>📚 ${tr.subjectLbl} ${escHtml(subject)}</span>` : ''}
          <span style="color:${submitted===0?'var(--muted)':submitted/Math.max(total,1)>.7?'var(--green)':'var(--yellow)'}">
            📨 ${submitted}${total?'/'+total:''} ${tr.submittedLbl}
          </span>
        </div>
      </div>
      <div class="assign-actions">
        <button class="btn-primary btn-sm" onclick="openSubmissions(${a.id})">${tr.viewGradeBtn}</button>
        <button class="btn-secondary btn-sm" onclick="deleteAssignment(${a.id})" style="color:var(--red);border-color:rgba(232,93,117,.3);"
          title="${tr.deleteTitle}">🗑</button>
      </div>
    </div>`;
  }).join('');
}

function deleteAssignment(id) {
  openConfirmModal(T[currentLang].deleteConfirm, async () => {
    try {
      const res  = await fetch('api_assignments.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':_csrfToken}, body:JSON.stringify({action:'delete',id}) });
      const data = await res.json();
      if (data.ok) { await loadAssignments(); showToast(T[currentLang].toastAssignDeleted); }
      else showToast('❌ '+(data.error||T[currentLang].toastServerError));
    } catch(e) { showToast(T[currentLang].toastNetError); }
  });
}

/* ── Submissions sub-view ── */
async function openSubmissions(assignId) {
  const listView = document.getElementById('assign-list-view');
  const subView  = document.getElementById('assign-sub-view');
  if (!subView) { showToast(currentLang==='en'?'Sub-view not found':'Sous-vue introuvable'); return; }
  if (listView) listView.style.display = 'none';
  subView.style.display = 'block';
  const titleEl = document.getElementById('sub-view-title');
  const metaEl  = document.getElementById('sub-view-meta');
  const subList = document.getElementById('submissions-list');
  if (titleEl) titleEl.textContent = '…';
  if (subList) subList.innerHTML = `<div style="color:var(--muted);padding:1rem;">${T[currentLang].loading}</div>`;
  try {
    const res  = await fetch(`api_assignments.php?action=submissions&id=${assignId}`);
    const data = await res.json();
    if (!data.ok) { if(subList) subList.innerHTML = `<div style="color:var(--red);">${T[currentLang].errorLoading}</div>`; return; }
    const a    = data.assignment;
    const lang = currentLang;
    const title = a.title_fr;
    const cn    = (a.group_name_fr||'');
    if (titleEl) titleEl.textContent = title;
    if (metaEl)  metaEl.textContent  = cn + (a.due_date ? ' · ' + T[lang].dueLbl2 + new Date(a.due_date).toLocaleDateString(lang==='fr'?'fr-FR':'en-GB',{day:'numeric',month:'long',year:'numeric'}) : '');
    const backLbl = document.getElementById('back-assign-lbl');
    if (backLbl) backLbl.textContent = T[lang].subBack;
    renderSubmissions(data.submissions);
  } catch(e) { if(subList) subList.innerHTML = `<div style="color:var(--red);">${T[currentLang].toastNetError}</div>`; }
}

function renderSubmissions(submissions) {
  const subList = document.getElementById('submissions-list');
  if (!subList) return;
  const lang = currentLang;
  const tr2 = T[lang];
  if (!submissions.length) {
    subList.innerHTML = `<div style="color:var(--muted);text-align:center;padding:2rem;">${tr2.noSubmissions}</div>`;
    return;
  }
  subList.innerHTML = submissions.map(s => {
    const date = new Date(s.submitted_at).toLocaleDateString(lang==='fr'?'fr-FR':'en-GB',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});
    const scoreBadge = s.score!=null ? `<span style="background:rgba(167,139,250,.12);color:var(--purple);border:1px solid rgba(167,139,250,.3);font-size:.75rem;font-weight:700;padding:.2rem .7rem;border-radius:100px;font-family:var(--font);">📊 ${s.score}/100</span>` : '';
    const fbBlock = s.teacher_comment ? `<div style="margin-top:.4rem;padding:.5rem .75rem;background:rgba(167,139,250,.06);border:1px solid rgba(167,139,250,.2);border-radius:8px;font-size:.8rem;"><strong style="color:var(--purple);font-size:.72rem;display:block;margin-bottom:.2rem;">${tr2.yourComment}</strong>${escHtml(s.teacher_comment)}</div>` : '';
    const fileName = s.file_path ? s.file_path.split('/').pop() : '';
    const fileLink = s.file_path ? `<div style="margin-top:.4rem;"><a href="${escHtml(s.file_path)}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:.4rem;font-size:.78rem;color:var(--blue);text-decoration:none;font-family:var(--font);font-weight:600;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">📎 ${escHtml(fileName)}</a></div>` : '';
    return `<div style="display:flex;align-items:flex-start;gap:.85rem;padding:1.1rem 0;border-bottom:1px solid var(--border2);" id="sub-row-${s.id}">
      <div style="width:32px;height:32px;border-radius:50%;background:rgba(167,139,250,.15);border:1.5px solid rgba(167,139,250,.35);display:inline-flex;align-items:center;justify-content:center;font-family:var(--font);font-size:.72rem;font-weight:700;color:var(--purple);flex-shrink:0;">${escHtml(s.initials)}</div>
      <div style="flex:1;min-width:0;">
        <div style="font-family:var(--font);font-size:.9rem;font-weight:600;margin-bottom:.2rem;">${escHtml(s.student_name||s.username)} ${scoreBadge}</div>
        ${s.comment ? `<div style="font-size:.82rem;color:var(--muted);line-height:1.5;margin-bottom:.4rem;white-space:pre-wrap;">${escHtml(s.comment)}</div>` : `<div style="font-size:.82rem;color:var(--muted2);font-style:italic;margin-bottom:.4rem;">${tr2.noText}</div>`}
        ${fileLink}
        <div style="font-size:.74rem;color:var(--muted2);">📅 ${date}</div>
        ${fbBlock}
        <div style="margin-top:.75rem;padding:.7rem .85rem;background:rgba(59,130,246,.04);border:1px solid rgba(59,130,246,.15);border-radius:10px;">
          <div style="font-size:.7rem;font-weight:700;color:var(--blue);letter-spacing:.06em;text-transform:uppercase;margin-bottom:.55rem;">${tr2.gradeSectionLbl}</div>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
            <input type="number" min="0" max="100" id="score-${s.id}" placeholder="${tr2.scorePlaceholder}" value="${s.score!=null?s.score:''}"
              style="width:95px;padding:.4rem .6rem;background:#fff;border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:var(--font);font-size:.85rem;text-align:center;outline:none;">
            <input type="text" id="comment-${s.id}" placeholder="${tr2.commentPlaceholder}" value="${escHtml(s.teacher_comment||'')}"
              style="flex:1;min-width:160px;padding:.4rem .7rem;background:#fff;border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:var(--font-body);font-size:.83rem;outline:none;">
            <button class="btn-primary btn-sm" onclick="gradeSubmission(${s.id})">${tr2.saveGrade}</button>
          </div>
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
      showToast(T[currentLang].toastGraded);
      // Update badge inline
      const row = document.getElementById('sub-row-'+subId);
      if (row && score!=='') { let b=row.querySelector('span[style*="var(--purple)"]'); if(!b){b=document.createElement('span');b.style.cssText='background:rgba(167,139,250,.12);color:var(--purple);border:1px solid rgba(167,139,250,.3);font-size:.75rem;font-weight:700;padding:.2rem .7rem;border-radius:100px;';row.querySelector('div[style*="font-weight:600"]').appendChild(b);} b.textContent='📊 '+score+'/100'; }
    } else if (data.error === 'session_expired') {
      showToast(currentLang==='fr' ? '⚠️ Session expirée — rechargez la page.' : '⚠️ Session expired — please refresh the page.');
    } else showToast('❌ '+(data.error||T[currentLang].toastServerError));
  } catch(e) { showToast(T[currentLang].toastNetError); }
  finally { btn.textContent=orig; btn.disabled=false; }
}

let _teacherQuizzes = [];

async function renderQuizzes() {
  const list = document.getElementById('quiz-teacher-list');
  if (!list) return;
  list.innerHTML = '<div class="loading-overlay" style="position:static;height:100px;border-radius:16px;"><div class="spinner"></div></div>';
  try {
    const d = await fetch('api_quiz.php?action=list_quizzes').then(r=>r.json());
    _teacherQuizzes = d.quizzes || [];
  } catch(e) {
    list.innerHTML = `<p style="color:var(--red);font-size:.85rem;grid-column:1/-1;">${T[currentLang].errorLoading||'Error'}</p>`;
    return;
  }
  const tr = T[currentLang];
  if (!_teacherQuizzes.length) {
    list.innerHTML = `<p style="color:var(--muted);font-size:.85rem;grid-column:1/-1;">${currentLang==='en'?'No quizzes yet. Click + Create quiz to start.':'Aucun quiz. Cliquez sur + Créer un quiz.'}</p>`;
    return;
  }
  list.innerHTML = _teacherQuizzes.map(q => {
    const avg  = q.avg_score != null ? parseInt(q.avg_score) : 0;
    const acts = q.is_active == 1;
    return `<div class="card" style="display:flex;flex-direction:column;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem;">
        <div style="font-size:2rem;">🧠</div>
        <div style="display:flex;gap:.4rem;">
          <span style="font-size:.7rem;padding:.15rem .5rem;border-radius:100px;border:1px solid ${acts?'rgba(62,207,120,.3)':'var(--border)'};color:${acts?'var(--green)':'var(--muted)'};">${acts?(currentLang==='en'?'Active':'Actif'):(currentLang==='en'?'Off':'Désactivé')}</span>
          <button onclick="toggleQuiz(${q.id},this)" style="font-size:.7rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;padding:.15rem .5rem;cursor:pointer;color:var(--muted);">${acts?(currentLang==='en'?'Disable':'Désactiver'):(currentLang==='en'?'Enable':'Activer')}</button>
          <button onclick="deleteQuiz(${q.id},this)" style="font-size:.7rem;background:rgba(232,93,117,.08);border:1px solid rgba(232,93,117,.2);border-radius:8px;padding:.15rem .5rem;cursor:pointer;color:var(--red);">🗑</button>
        </div>
      </div>
      <div style="font-family:var(--font);font-size:.95rem;font-weight:700;margin-bottom:.5rem;">${escHtml(q.title)}</div>
      <div style="font-size:.75rem;color:var(--muted);margin-bottom:.5rem;">${escHtml((q.type_key||'')+(q.group_letter?' · Gr.'+q.group_letter:''))}</div>
      <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.75rem;">
        <span style="font-size:.72rem;background:rgba(30,27,75,.06);border:1px solid var(--border);padding:.2rem .6rem;border-radius:100px;color:var(--muted);">📝 ${q.question_count||0} q</span>
        ${q.time_limit_min>0?`<span style="font-size:.72rem;background:rgba(30,27,75,.06);border:1px solid var(--border);padding:.2rem .6rem;border-radius:100px;color:var(--muted);">⏱ ${q.time_limit_min} min</span>`:''}
        <span style="font-size:.72rem;background:rgba(30,27,75,.06);border:1px solid var(--border);padding:.2rem .6rem;border-radius:100px;color:var(--muted);">👥 ${q.attempt_count||0}</span>
      </div>
      ${q.attempt_count>0?`<div style="font-size:.82rem;color:var(--muted);margin-bottom:.4rem;">${tr.avgLbl||'Avg'} <strong style="color:var(--purple)">${avg}%</strong></div>
      <div class="progress-bar"><div class="progress-fill purple" style="width:${avg}%"></div></div>`:''}
      <button onclick="viewQuizResults(${q.id},'${escHtml(q.title).replace(/'/g,"&#39;")}')" style="margin-top:auto;padding-top:.75rem;font-size:.78rem;color:var(--blue);background:none;border:none;cursor:pointer;text-align:left;font-family:var(--font);">${currentLang==='en'?'View results →':'Voir les résultats →'}</button>
    </div>`;
  }).join('');
}

/* ── Quiz create helpers ───────────────────────────────────────────────── */
let _quizQuestionCount = 0;

function addQuizQuestion() {
  _quizQuestionCount++;
  const n = _quizQuestionCount;
  const lang = currentLang;
  const qLabel = lang==='en'?'Question':'Question';
  const optLabel = lang==='en'?'Option':'Option';
  const correctLabel = lang==='en'?'Correct answer':'Bonne réponse';
  const div = document.createElement('div');
  div.className = 'quiz-q-block';
  div.dataset.n = n;
  div.style.cssText = 'background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:12px;padding:1rem;position:relative;';
  div.innerHTML = `
    <button onclick="this.closest('.quiz-q-block').remove()" style="position:absolute;top:.6rem;right:.6rem;background:none;border:none;color:var(--muted);cursor:pointer;font-size:.9rem;">✕</button>
    <div style="font-family:var(--font);font-size:.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">${qLabel} ${n}</div>
    <input type="text" class="q-text" placeholder="${qLabel}…" maxlength="400"
      style="width:100%;padding:.6rem .9rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:9px;color:var(--white);font-family:var(--font-body);font-size:.86rem;outline:none;box-sizing:border-box;margin-bottom:.75rem;">
    <div style="font-size:.72rem;color:var(--muted);margin-bottom:.4rem;font-family:var(--font);">${correctLabel}</div>
    ${[0,1,2,3].map(i=>`
      <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem;">
        <input type="radio" name="correct-${n}" value="${i}" ${i===0?'checked':''} style="accent-color:var(--green);cursor:pointer;">
        <input type="text" class="q-opt" data-opt="${i}" placeholder="${optLabel} ${i+1}" maxlength="200"
          style="flex:1;padding:.5rem .8rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--white);font-family:var(--font-body);font-size:.83rem;outline:none;">
      </div>`).join('')}`;
  document.getElementById('quiz-questions-builder').appendChild(div);
}

async function submitNewQuiz() {
  const title   = document.getElementById('new-quiz-title').value.trim();
  const groupId = parseInt(document.getElementById('new-quiz-group').value);
  const time    = parseInt(document.getElementById('new-quiz-time').value) || 0;
  const errEl   = document.getElementById('quiz-create-error');
  const btn     = document.getElementById('quiz-modal-submit');
  errEl.textContent = '';
  if (!title)   { errEl.textContent = currentLang==='en'?'Title required':'Titre requis'; return; }
  if (!groupId) { errEl.textContent = currentLang==='en'?'Select a group':'Sélectionnez un groupe'; return; }
  const blocks = document.querySelectorAll('.quiz-q-block');
  if (!blocks.length) { errEl.textContent = currentLang==='en'?'Add at least 1 question':'Ajoutez au moins 1 question'; return; }
  const questions = [];
  for (const b of blocks) {
    const qText = b.querySelector('.q-text').value.trim();
    if (!qText) { errEl.textContent = currentLang==='en'?'Fill all question texts':'Remplissez tous les textes de question'; return; }
    const selected = b.querySelector('input[type=radio]:checked')?.value ?? '0';
    const opts = [...b.querySelectorAll('.q-opt')].map((inp, i) => ({ text: inp.value.trim(), correct: String(i) === selected }));
    if (opts.some(o => !o.text)) { errEl.textContent = currentLang==='en'?'Fill all option fields':'Remplissez tous les champs de réponse'; return; }
    questions.push({ question: qText, options: opts });
  }
  btn.disabled = true;
  try {
    await fetch('api_quiz.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':_csrfToken}, body:JSON.stringify({action:'create_quiz',title,group_id:groupId,time_limit_min:time,questions}) }).then(r=>r.json()).then(d=>{ if(!d.ok) throw new Error(d.error||'Error'); });
    closeModal('quiz');
    await renderQuizzes();
    showToast(currentLang==='en'?'Quiz created!':'Quiz créé !');
  } catch(e) { errEl.textContent = e.message; }
  finally { btn.disabled = false; }
}

async function toggleQuiz(id, btn) {
  btn.disabled = true;
  try {
    await fetch('api_quiz.php', {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':_csrfToken},body:JSON.stringify({action:'toggle_quiz',id})}).then(r=>r.json());
    await renderQuizzes();
  } catch(e) { showToast(e.message); } finally { btn.disabled = false; }
}

function deleteQuiz(id, btn) {
  const lang = currentLang;
  openConfirmModal(lang==='en'?'Delete this quiz and all its attempts?':'Supprimer ce quiz et toutes ses tentatives ?', async () => {
    btn.disabled = true;
    try {
      await fetch('api_quiz.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':_csrfToken},body:JSON.stringify({action:'delete_quiz',id})}).then(r=>r.json());
      await renderQuizzes();
      showToast(lang==='en'?'Quiz deleted':'Quiz supprimé');
    } catch(e) { showToast(e.message); } finally { btn.disabled = false; }
  });
}

async function viewQuizResults(quizId, title) {
  const lang = currentLang;
  let d;
  try { d = await fetch(`api_quiz.php?action=quiz_results&id=${quizId}`).then(r=>r.json()); } catch(e) { showToast(e.message); return; }
  const results = d.results || [];
  if (!results.length) { showToast(lang==='en'?'No attempts yet':'Aucune tentative'); return; }
  const rows = results.map(r=>`<tr><td style="padding:.6rem 1rem;">${escHtml(r.full_name||r.username)}</td><td style="padding:.6rem 1rem;text-align:center;">${r.score}/${r.total}</td><td style="padding:.6rem 1rem;text-align:center;"><strong style="color:${r.pct>=60?'var(--green)':'var(--red)'};">${r.pct}%</strong></td><td style="padding:.6rem 1rem;color:var(--muted);font-size:.78rem;">${new Date(r.finished_at).toLocaleDateString()}</td></tr>`).join('');
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay'; overlay.style.zIndex = '9999';
  overlay.innerHTML = `<div class="modal" style="max-width:560px;max-height:80vh;overflow-y:auto;">
    <div class="modal-header"><h3>📊 ${escHtml(title)}</h3><button class="btn-close" onclick="this.closest('.modal-overlay').remove()">✕</button></div>
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
      <thead><tr style="border-bottom:1px solid var(--border);"><th style="padding:.6rem 1rem;text-align:left;color:var(--muted);">${lang==='en'?'Student':'Étudiant'}</th><th style="padding:.6rem 1rem;color:var(--muted);">${lang==='en'?'Score':'Score'}</th><th style="padding:.6rem 1rem;color:var(--muted);">%</th><th style="padding:.6rem 1rem;color:var(--muted);">${lang==='en'?'Date':'Date'}</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>
  </div>`;
  overlay.addEventListener('click', e=>{ if(e.target===overlay) overlay.remove(); });
  document.body.appendChild(overlay);
  overlay.classList.add('open');
}

/* ── Open quiz modal ───────────────────────────────────────────────────── */
async function openQuizModal() {
  // Populate group select
  const sel = document.getElementById('new-quiz-group');
  sel.innerHTML = '';
  try {
    const d = await fetch('api_classes.php?action=teacher_groups').then(r=>r.json());
    const groups = d.groups || [];
    if (!groups.length) { sel.innerHTML = `<option value="">${currentLang==='en'?'No groups':'Aucun groupe'}</option>`; }
    else { sel.innerHTML = groups.map(g=>`<option value="${g.id}">${escHtml(g.type_key||'')} Gr.${escHtml(g.group_letter||'')} ${g.student_count?'('+g.student_count+' st.)':''}</option>`).join(''); }
  } catch(e) { sel.innerHTML = '<option value="">Error</option>'; }
  // Reset builder
  document.getElementById('quiz-questions-builder').innerHTML = '';
  document.getElementById('new-quiz-title').value = '';
  document.getElementById('new-quiz-time').value  = '0';
  document.getElementById('quiz-create-error').textContent = '';
  _quizQuestionCount = 0;
  addQuizQuestion(); // start with 1
  openModal('quiz');
}

/* ── MESSAGING ─────────────────────────────────────────────────────────── */
let _msgTargetId = null;

function openMessageModal(userId, userName) {
  _msgTargetId = userId;
  document.getElementById('msg-modal-to').textContent = (currentLang==='en'?'To: ':'À : ') + userName;
  document.getElementById('msg-text').value = '';
  document.getElementById('msg-error').textContent = '';
  openModal('message');
}

async function sendMessage() {
  const text = document.getElementById('msg-text').value.trim();
  const errEl = document.getElementById('msg-error');
  const btn   = document.getElementById('msg-submit');
  if (!text) { errEl.textContent = currentLang==='en'?'Write a message first':'Écrivez un message'; return; }
  btn.disabled = true;
  try {
    const res = await fetch('api_notifications.php?action=send_message', {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':_csrfToken},
      body: JSON.stringify({action:'send_message', user_id:_msgTargetId, message:text})
    }).then(r=>r.json());
    if (!res.ok) throw new Error(res.error||'Error');
    closeModal('message');
    showToast(currentLang==='en'?'Message sent!':'Message envoyé !');
  } catch(e) { errEl.textContent = e.message; }
  finally { btn.disabled = false; }
}

let GRADES_LIVE = [];

async function loadGrades() {
  const tbody = document.getElementById('grades-tbody');
  if (!tbody) return;
  const lang = currentLang;
  tbody.innerHTML = `<tr><td colspan="4" style="color:var(--muted);text-align:center;padding:1rem;">${T[lang].loading}</td></tr>`;
  try {
    const res  = await fetch('api_assignments.php?action=grades_overview');
    const data = await res.json();
    if (!data.ok) {
      tbody.innerHTML = `<tr><td colspan="4" style="color:var(--red);text-align:center;">${T[lang].errorLoading}</td></tr>`;
      return;
    }
    GRADES_LIVE = data.grades || [];
    renderGrades();
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="4" style="color:var(--red);text-align:center;">${T[lang].toastNetError}</td></tr>`;
  }
}

function renderGrades() {
  const tbody = document.getElementById('grades-tbody');
  if (!tbody) return;
  const lang = currentLang;
  const tr2  = T[lang];

  if (!GRADES_LIVE.length) {
    tbody.innerHTML = `<tr><td colspan="4" style="color:var(--muted);text-align:center;padding:1.5rem;">${tr2.noGradesYet}</td></tr>`;
  } else {
    tbody.innerHTML = GRADES_LIVE.map(g => {
      const name  = g.student_name || g.username || '?';
      const title = lang === 'en' ? (g.title_en || g.title_fr) : g.title_fr;
      const cn    = (g.group_name_fr || '');
      const score = parseInt(g.score);
      const color = score >= 75 ? 'var(--green)' : score >= 50 ? 'var(--yellow)' : 'var(--red)';
      const date  = g.graded_at ? new Date(g.graded_at).toLocaleDateString(lang === 'fr' ? 'fr-FR' : 'en-GB', {day:'numeric', month:'short'}) : '—';
      const initials = name.split(' ').map(w => w[0] || '').join('').slice(0, 2).toUpperCase();
      return `<tr>
        <td><div style="display:flex;align-items:center;gap:.75rem;"><div class="student-avatar-sm">${escHtml(initials)}</div><span>${escHtml(name)}</span></div></td>
        <td style="font-size:.82rem;">${escHtml(title)}${cn ? ` <span style="font-size:.7rem;color:var(--muted2);">(${escHtml(cn)})</span>` : ''}</td>
        <td style="font-family:var(--font);font-weight:700;color:${color};">${score}/100</td>
        <td style="font-size:.8rem;color:var(--muted);">${date}</td>
      </tr>`;
    }).join('');
  }

  // Grade distribution using GRADES_LIVE scores
  const dist = document.getElementById('grade-dist');
  if (dist && GRADES_LIVE.length) {
    const scores = GRADES_LIVE.map(g => parseInt(g.score));
    const ranges = [[90,100],[75,89],[60,74],[0,59]];
    const colors = ['var(--green)','var(--blue)','var(--yellow)','var(--red)'];
    const labels = tr2.gradeRanges;
    dist.innerHTML = ranges.map((r, i) => {
      const count = scores.filter(v => v >= r[0] && v <= r[1]).length;
      const pct   = scores.length ? Math.round(count / scores.length * 100) : 0;
      return `<div style="display:flex;align-items:center;gap:1rem;margin-bottom:.8rem;">
        <div style="font-size:.78rem;color:var(--muted);min-width:55px;font-family:var(--font);">${labels[i]}</div>
        <div class="progress-bar" style="flex:1"><div class="progress-fill" style="width:${pct}%;background:${colors[i]}"></div></div>
        <div style="font-family:var(--font);font-size:.82rem;font-weight:600;min-width:40px;text-align:right;color:${colors[i]}">${count}</div>
      </div>`;
    }).join('');
  } else if (dist) {
    dist.innerHTML = `<div style="color:var(--muted);font-size:.85rem;padding:.5rem 0;">${tr2.noGradesYet}</div>`;
  }

  // Top students by average score
  const top = document.getElementById('top-students');
  if (top && GRADES_LIVE.length) {
    const byStudent = {};
    GRADES_LIVE.forEach(g => {
      const key = g.student_name || g.username;
      if (!byStudent[key]) byStudent[key] = [];
      byStudent[key].push(parseInt(g.score));
    });
    const sorted = Object.entries(byStudent)
      .map(([name, scores]) => ({ name, avg: Math.round(scores.reduce((a,b) => a+b, 0) / scores.length) }))
      .sort((a, b) => b.avg - a.avg).slice(0, 3);
    const medals = ['🥇','🥈','🥉'];
    top.innerHTML = sorted.map((s, i) => `
      <div style="display:flex;align-items:center;gap:.8rem;padding:.75rem 0;border-bottom:1px solid var(--border2);">
        <div style="font-size:1.4rem;">${medals[i]}</div>
        <div class="student-avatar-sm">${escHtml(s.name.split(' ').map(w=>w[0]||'').join('').slice(0,2).toUpperCase())}</div>
        <div style="flex:1;font-family:var(--font);font-size:.88rem;font-weight:600;">${escHtml(s.name)}</div>
        <div style="font-family:var(--font);font-size:1rem;font-weight:700;color:var(--green);">${s.avg}/100</div>
      </div>
    `).join('');
  } else if (top) {
    top.innerHTML = `<div style="color:var(--muted);font-size:.85rem;padding:.5rem 0;">${tr2.noGradesYet}</div>`;
  }
}

function openModal(type) {
  document.getElementById('modal-' + type).classList.add('open');
}
function closeModal(type) {
  document.getElementById('modal-' + type).classList.remove('open');
}

let _confirmCallback = null;
function openConfirmModal(msg, onConfirm) {
  _confirmCallback = onConfirm;
  const lang = currentLang;
  document.getElementById('confirm-modal-title').textContent = lang === 'fr' ? 'Confirmer' : 'Confirm';
  document.getElementById('confirm-modal-body').textContent  = msg;
  document.getElementById('confirm-cancel-btn').textContent  = T[lang].modalCancel;
  document.getElementById('confirm-ok-text').textContent     = lang === 'fr' ? 'Supprimer' : 'Delete';
  document.getElementById('modal-confirm').classList.add('open');
}
function closeConfirmModal() {
  document.getElementById('modal-confirm').classList.remove('open');
  _confirmCallback = null;
}
function confirmAction() {
  const cb = _confirmCallback;
  closeConfirmModal();
  if (cb) cb();
}
document.getElementById('modal-confirm')?.addEventListener('click', function(e) {
  if (e.target === this) closeConfirmModal();
});

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
    ? `<option value="">${T[lang].noClassAssigned}</option>`
    : `<option value="">${T[lang].chooseClass}</option>`
      + TEACHER_COURSES.map(c => {
          const name = lang === 'en'
            ? (c.group_name_en || c.group_name_fr)
            : c.group_name_fr;
          const subject = lang === 'en'
            ? (c.subject_en || c.subject_fr)
            : c.subject_fr;
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
  if (lbl) lbl.innerHTML = T[lang].classLabel + ' <span style="color:var(--red)">*</span>';
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
    if (errEl) { errEl.textContent = T[lang].validateChooseClass; errEl.style.display = ''; }
    return;
  }
  if (!title) {
    if (errEl) { errEl.textContent = T[lang].validateTitleRequired; errEl.style.display = ''; }
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
      if (errEl) { errEl.textContent = '❌ ' + (data.error || T[lang].toastServerError); errEl.style.display = ''; }
      else showToast('❌ ' + (data.error || T[lang].toastServerError));
    }
  } catch(e) {
    if (errEl) { errEl.textContent = T[lang].networkError; errEl.style.display = ''; }
  } finally {
    if (btn) { btn.textContent = origText; btn.disabled = false; }
  }
}

async function saveProfile() {
  const name = document.getElementById('pref-name').value.trim();
  const btn  = document.getElementById('save-btn');
  if (!name) return;
  if (btn) btn.textContent = '…';
  if (btn) btn.disabled = true;
  try {
    const res  = await fetch('api_update_profile.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrfToken },
      body:    JSON.stringify({ action: 'update_name', name })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || T[currentLang].toastServerError);
    document.getElementById('sidebar-name').textContent = name;
    document.getElementById('settings-name').textContent = name;
    showToast(T[currentLang].toastSaved);
  } catch(e) {
    showToast(T[currentLang].toastErrorPrefix + e.message);
  } finally {
    if (btn) { btn.textContent = T[currentLang].saveBtn; btn.disabled = false; }
  }
}

async function changePassword() {
  const cur     = document.getElementById('pwd-current').value;
  const nw      = document.getElementById('pwd-new').value;
  const conf    = document.getElementById('pwd-confirm').value;
  const errEl   = document.getElementById('pwd-error');
  const btnText = document.getElementById('pwd-btn-text');
  errEl.style.display = 'none';
  if (!cur || !nw || !conf) { errEl.textContent = currentLang==='en'?'All fields are required.':'Tous les champs sont requis.'; errEl.style.display=''; return; }
  if (nw !== conf) { errEl.textContent = currentLang==='en'?'Passwords do not match.':'Les mots de passe ne correspondent pas.'; errEl.style.display=''; return; }
  if (nw.length < 8) { errEl.textContent = currentLang==='en'?'Password must be at least 8 characters.':'Le mot de passe doit contenir au moins 8 caractères.'; errEl.style.display=''; return; }
  btnText.textContent = '…';
  document.getElementById('pwd-btn').disabled = true;
  try {
    const res  = await fetch('api_update_profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrfToken },
      body: JSON.stringify({ action: 'change_password', current_password: cur, new_password: nw, confirm_password: conf })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || T[currentLang].toastServerError);
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

  // Session header numbers (visual separator every 5)
  let headerCells = '';
  for (let i = 1; i <= ATT_SESSIONS; i++) {
    const sepCls = (i % 5 === 1 && i > 1) ? ' grp-start' : '';
    headerCells += `<th class="att-th-sess${sepCls}"><span class="sess-num-chip">${i}</span></th>`;
  }
  sessHeaderRow.innerHTML = `<th class="att-th-name" style="background:transparent;border-right:1px solid var(--border2);"></th>${headerCells}<th class="att-th-total" style="background:transparent;"></th>`;

  // Student rows
  tbody.innerHTML = STUDENTS.map(s => {
    if (!attData[s.id]) attData[s.id] = {};
    const data = attData[s.id];
    let boxes = '';
    for (let i = 1; i <= ATT_SESSIONS; i++) {
      const checked = data[i] ? 'checked' : '';
      const sepCls = (i % 5 === 1 && i > 1) ? ' grp-start' : '';
      boxes += `<td class="att-td-box${sepCls}"><input type="checkbox" class="att-box" data-sid="${s.id}" data-sess="${i}" ${checked} onchange="attToggle(this)"></td>`;
    }
    const { pct, cls } = attPct(s.id);
    return `<tr>
      <td class="att-td-name">
        <div class="att-student">
          <div class="att-avatar">${escHtml(s.init)}</div>
          <span class="att-name">${escHtml(s.name)}</span>
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
  if (!attData[sid]) attData[sid] = {};
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
      showToast(T[currentLang].toastAttError || (currentLang==='en'?'Error saving. Please try again.':'Erreur lors de la sauvegarde'));
    }
  } catch (e) {
    showToast(T[currentLang].toastAttError || (currentLang==='en'?'Network error':'Erreur réseau'));
    console.error('Save attendance error:', e);
  } finally {
    attSaving = false;
    if (btn) btn.innerHTML = origText;
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  const _sl = sessionStorage.getItem('upskill_lang');
  const savedLang = (_sl === 'fr' || _sl === 'en') ? _sl : 'en';
  // Load everything in parallel
  await loadLiveStudents();
  await Promise.all([initAttData(), loadTeacherCourses(), loadAssignments()]);
  const postDateEl = document.getElementById('post-date');
  if (postDateEl) postDateEl.value = new Date().toISOString().slice(0, 10);
  setLang(savedLang);
  updateHomeStats();
  renderAttention(); renderAssignments(); renderQuizzes();
  renderAttendance(); renderCourses();
  hydrateTeacherInfo();
  loadSavedAvatar();
  loadActivityFeed();
  loadTodayClasses();
  loadNotifBadge();
  const validPages = ['home','students','courses','assignments','posts','quizzes','grades','settings','attendance'];
  const savedPage = sessionStorage.getItem('upskill_page_t');
  if (savedPage && validPages.includes(savedPage) && savedPage !== 'home') {
    navigate(savedPage);
  }
  // Keyboard accessibility for sidebar nav items
  document.querySelectorAll('.nav-item').forEach(el => {
    el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); el.click(); } });
  });
});

/* ── LIVE STUDENT FETCH ── */
async function loadLiveStudents() {
  try {
    const res = await fetch('api_classes.php?action=teacher_all_students');
    if (!res.ok) return;
    const data = await res.json();
    if (data.ok && Array.isArray(data.students)) {
      STUDENTS = data.students.map(s => ({
        id:   s.id,
        name: s.name || s.username,
        init: (s.name || s.username || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
        progress: 0, assigns: 0, avg: 0, status: 'good', sessions: 0, present: 0,
      }));
      const sv = document.getElementById('stat1-val');
      if (sv) sv.textContent = STUDENTS.length;
    }
  } catch(e) { /* silently keep STUDENTS as [] */ }
}

/* ── TEACHER INFO HYDRATION ── */
function hydrateTeacherInfo() {
  const fn = <?= json_encode($_SESSION['full_name'] ?? $_SESSION['username'] ?? '') ?>;
  if (!fn) return;
  const sn = document.getElementById('sidebar-name'); if (sn) sn.textContent = fn;
  const nm = document.getElementById('settings-name'); if (nm) nm.textContent = fn;
  const pi = document.getElementById('pref-name'); if (pi) pi.value = fn;
}

/* COURSES DATA & LOGIC */
const COURSES = [];
let activeCourse = null;
const CT = {
  fr:{ coursesPageTitle:'Classes',
       coursesPageSub:(n)=>n+' groupes assignés · Année 2024-2025',
       studentsLabel:'étudiants', backCourses:'← Retour aux cours',
       cdStudentsTitle:'Étudiants du groupe', cdScheduleTitle:'Emploi du temps',
       cdProgTitle:'Progression du groupe',
       cdStatStudentsLbl:'Étudiants', cdStatAvgLbl:'Moyenne',
       cdStatAssignsLbl:'Devoirs actifs', cdStatAttLbl:'Taux de présence',
       statusGood:'Bon niveau', statusWarn:'À surveiller', statusLow:'En difficulté',
       thAvg:'Moy.', thAtt:'Présence',
       progGeneral:'Moyenne générale', progAtt:'Taux de présence', progAssigns:'Taux de soumission' },
  en:{ coursesPageTitle:'Classes',
       coursesPageSub:(n)=>n+' groups assigned · Year 2024-2025',
       studentsLabel:'students', backCourses:'← Back to courses',
       cdStudentsTitle:'Group students', cdScheduleTitle:'Schedule',
       cdProgTitle:'Group progress',
       cdStatStudentsLbl:'Students', cdStatAvgLbl:'Average',
       cdStatAssignsLbl:'Active assignments', cdStatAttLbl:'Attendance rate',
       statusGood:'On track', statusWarn:'Needs attention', statusLow:'Struggling',
       thAvg:'Avg.', thAtt:'Attendance',
       progGeneral:'Overall average', progAtt:'Attendance rate', progAssigns:'Submission rate' }
};
let teacherGroups    = [];
let teacherClassView = 'types'; // 'types' | 'levels' | 'groups'
let teacherSelType   = null;
let teacherSelLevel  = null;
let _cachedPosts     = null;   // cached for lang-switch re-render
let _cachedTodayGroups = null; // cached for lang-switch re-render

async function loadTeacherGroups() {
  teacherGroups = [];
  // Load for courses page
  const agEl = document.getElementById('teacher-assigned-groups');
  if (agEl) agEl.innerHTML = '<div class="loading-overlay"><div class="spinner"></div></div>';

  try {
    const data = await fetch('api_classes.php?action=teacher_groups').then(r=>r.json());
    teacherGroups = (data.ok && data.groups) ? data.groups : [];
  } catch(e) { teacherGroups = []; }

  // Update Classes sidebar badge
  const bc = document.getElementById('badge-classes');
  if (bc) { bc.textContent = teacherGroups.length; bc.style.display = teacherGroups.length ? '' : 'none'; }

  renderTeacherGroups();
  renderCourses();
  populateAttGroupSelect();
}

const TYPE_ICONS = {
  beginners:'🌱', pre_intermediate:'📘', intermediate:'📗',
  upper_intermediate:'📙', advanced:'🚀', baccalaureate:'🎓',
  business:'💼', kids:'🧒'
};
const TYPE_LABELS = {
  beginners:          {fr:'Débutants',          en:'Beginners'},
  pre_intermediate:   {fr:'Pré-intermédiaire',  en:'Pre-Intermediate'},
  intermediate:       {fr:'Intermédiaire',      en:'Intermediate'},
  upper_intermediate: {fr:'Upper-intermédiaire',en:'Upper-Intermediate'},
  advanced:           {fr:'Avancé',             en:'Advanced'},
  baccalaureate:      {fr:'Baccalauréat',       en:'Baccalaureate'},
  business:           {fr:'Business',           en:'Business'},
  kids:               {fr:'Kids',               en:'Kids'},
};
const TYPE_ICON_CLASS = {
  beginners:'c2', pre_intermediate:'c1', intermediate:'c2',
  upper_intermediate:'c3', advanced:'c4', baccalaureate:'c1',
  business:'c3', kids:'c4'
};

function renderTeacherGroups() {
  if (teacherClassView === 'types')  _tcRenderTypes();
  else if (teacherClassView === 'levels') _tcRenderLevels();
  else _tcRenderGroups();
}

function _tcBreadcrumb(crumbs, current) {
  const parts = crumbs.map(c => `<span class="tc-crumb" onclick="${c.fn}">${c.label}</span><span class="tc-sep">›</span>`).join('');
  return `<div class="tc-breadcrumb">${parts}<span class="tc-crumb-cur">${current}</span></div>`;
}

function _tcRenderTypes() {
  const lang = currentLang;
  const el   = document.getElementById('teacher-assigned-groups');
  if (!el) return;
  if (teacherGroups.length === 0) { el.innerHTML = ''; return; }

  const byType = {};
  const order  = [];
  teacherGroups.forEach(g => {
    if (!byType[g.type_key]) { byType[g.type_key] = []; order.push(g.type_key); }
    byType[g.type_key].push(g);
  });

  const tr2 = T[lang];
  const studentLbl = tr2.studentUnit;

  const cards = order.map(typeKey => {
    const groups   = byType[typeKey];
    const icon     = TYPE_ICONS[typeKey]      || '🏫';
    const iconCls  = TYPE_ICON_CLASS[typeKey] || 'c1';
    const labels   = TYPE_LABELS[typeKey]     || {fr: typeKey, en: typeKey};
    const typeName = lang === 'en' ? (labels.en || labels.fr) : labels.fr;
    const levels   = [...new Set(groups.map(g => g.level_number).filter(l => l !== null))];
    const hasLvl   = levels.length > 0;
    const subLbl   = hasLvl
      ? tr2.niveauSuffix(levels.length)
      : tr2.groupeSuffix(groups.length);
    const total    = groups.reduce((s, g) => s + (g.student_count || 0), 0);
    return `<div class="course-card" onclick="tcSelectType('${typeKey}')">
      <div class="course-card-header">
        <div class="course-icon ${iconCls}">${icon}</div>
        <div>
          <div class="course-group-name">${typeName}</div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:.15rem;">${subLbl}</div>
        </div>
      </div>
      <div class="course-meta-row"><span>👥 ${total} ${studentLbl}</span></div>
    </div>`;
  }).join('');

  el.innerHTML = `<div class="grid-3">${cards}</div>`;
}

function tcSelectType(typeKey) {
  teacherSelType  = typeKey;
  const groups    = teacherGroups.filter(g => g.type_key === typeKey);
  const hasLevels = groups.some(g => g.level_number !== null);
  teacherClassView = hasLevels ? 'levels' : 'groups';
  teacherSelLevel  = null;
  renderTeacherGroups();
}

function _tcRenderLevels() {
  const lang    = currentLang;
  const el      = document.getElementById('teacher-assigned-groups');
  if (!el) return;
  const groups  = teacherGroups.filter(g => g.type_key === teacherSelType);
  const labels  = TYPE_LABELS[teacherSelType] || {fr: teacherSelType, en: teacherSelType};
  const typeName = lang === 'en' ? (labels.en || labels.fr) : labels.fr;
  const icon    = TYPE_ICONS[teacherSelType]      || '🏫';
  const iconCls = TYPE_ICON_CLASS[teacherSelType] || 'c1';

  const levelMap = {};
  groups.forEach(g => {
    const l = g.level_number;
    if (!levelMap[l]) levelMap[l] = [];
    levelMap[l].push(g);
  });
  const levels = Object.keys(levelMap).map(Number).sort((a, b) => a - b);

  const tr3 = T[lang];
  const studentLbl = tr3.studentUnit;
  const levelWord  = tr3.levelWord;
  const groupWord  = tr3.groupWord;

  const cards = levels.map(lvl => {
    const gs    = levelMap[lvl];
    const total = gs.reduce((s, g) => s + (g.student_count || 0), 0);
    const gc    = gs.length;
    const gcLbl = tr3.groupeSuffix(gc);
    return `<div class="course-card" onclick="tcSelectLevel(${lvl})">
      <div class="course-card-header">
        <div class="course-icon ${iconCls}">${icon}</div>
        <div>
          <div class="course-group-name">${levelWord} ${lvl}</div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:.15rem;">${gcLbl}</div>
        </div>
      </div>
      <div class="course-meta-row"><span>👥 ${total} ${studentLbl}</span></div>
    </div>`;
  }).join('');

  const crumbHome = T[lang].allClasses;
  el.innerHTML = _tcBreadcrumb(
    [{label: crumbHome, fn: 'tcGoTypes()'}],
    typeName
  ) + `<div class="grid-3">${cards}</div>`;
}

function tcSelectLevel(level) {
  teacherSelLevel  = level;
  teacherClassView = 'groups';
  renderTeacherGroups();
}

function _tcRenderGroups() {
  const lang   = currentLang;
  const el     = document.getElementById('teacher-assigned-groups');
  if (!el) return;
  const groups = teacherGroups.filter(g =>
    g.type_key === teacherSelType &&
    (teacherSelLevel === null || g.level_number === teacherSelLevel)
  );
  const labels   = TYPE_LABELS[teacherSelType] || {fr: teacherSelType, en: teacherSelType};
  const typeName = lang === 'en' ? (labels.en || labels.fr) : labels.fr;
  const icon     = TYPE_ICONS[teacherSelType]      || '🏫';
  const iconCls  = TYPE_ICON_CLASS[teacherSelType] || 'c1';
  const tr5 = T[lang];
  const studentLbl = tr5.studentUnit;
  const groupWord  = tr5.groupWord;
  const levelWord  = tr5.levelWord;

  const DAY_EN_GC = {Lundi:'Monday',Mardi:'Tuesday',Mercredi:'Wednesday',Jeudi:'Thursday',Vendredi:'Friday',Samedi:'Saturday',Dimanche:'Sunday'};

  const cards = groups.map(g => {
    // Parse schedule
    let schedSlots = [];
    try { schedSlots = JSON.parse(g.schedule_json || '[]'); } catch(e) {}
    const slotCount = schedSlots.length;

    // Build day + time line
    const schedLines = schedSlots.map(s => {
      const day = lang === 'en' ? (DAY_EN_GC[s.day_fr] || s.day_fr) : s.day_fr;
      const timeStr = s.time ? (s.time_end ? `${s.time} – ${s.time_end}` : s.time) : '';
      return timeStr ? `${day} · ${timeStr}` : day;
    });

    const schedHtml = schedLines.length
      ? `<div style="font-size:.73rem;color:var(--primary);margin-top:.3rem;font-weight:600;display:flex;flex-wrap:wrap;gap:.2rem .5rem;">${schedLines.map(l => `<span>📅 ${escHtml(l)}</span>`).join('')}</div>`
      : '';

    const sessionsHtml = slotCount
      ? `<div style="font-size:.72rem;color:var(--muted);margin-top:.25rem;">🔁 ${slotCount} ${lang==='en'?(slotCount===1?'session/week':'sessions/week'):(slotCount===1?'séance/semaine':'séances/semaine')}</div>`
      : '';

    return `<div class="course-card" onclick="openGroupDetail(${g.group_id})">
      <div class="course-card-header">
        <div class="course-icon ${iconCls}">${icon}</div>
        <div style="flex:1;min-width:0;">
          <div class="course-group-name">${groupWord} ${g.group_letter}</div>
          <div style="font-size:.75rem;color:var(--muted);margin-top:.15rem;">👥 ${g.student_count || 0} ${studentLbl}</div>
          ${schedHtml}${sessionsHtml}
        </div>
      </div>
    </div>`;
  }).join('');

  const crumbs = [{label: T[lang].allClasses, fn: 'tcGoTypes()'}];
  if (teacherSelLevel !== null)
    crumbs.push({label: typeName, fn: 'tcGoLevels()'});
  const current = teacherSelLevel !== null ? `${levelWord} ${teacherSelLevel}` : typeName;

  el.innerHTML = _tcBreadcrumb(crumbs, current) + `<div class="grid-3">${cards}</div>`;
}

function tcGoTypes() {
  teacherClassView = 'types'; teacherSelType = null; teacherSelLevel = null;
  renderTeacherGroups();
}
function tcGoLevels() {
  teacherClassView = 'levels'; teacherSelLevel = null;
  renderTeacherGroups();
}

function populateAttGroupSelect() {
  const sel = document.getElementById('att-group-select');
  if (!sel) return;
  const lang = currentLang;
  const noGroup = T[lang].allStudents;
  sel.innerHTML = `<option value="">${noGroup}</option>`
    + teacherGroups.map(g => {
        let label;
        if (lang === 'en') {
          const tl = TYPE_LABELS[g.type_key] || {en: g.type_key, fr: g.type_key};
          label = (tl.en || tl.fr) + (g.level_number ? ' ' + g.level_number : '') + (g.group_letter ? ' ' + g.group_letter : '');
        } else {
          label = g.label_fr;
        }
        return `<option value="${g.group_id}">${label}</option>`;
      }).join('');
}

async function attSelectGroup(groupId) {
  const sel = document.getElementById('att-group-select');
  if (sel) sel.value = groupId || '';
  const loadingEl = document.getElementById('att-group-loading');
  if (loadingEl) loadingEl.style.display = '';

  if (!groupId) {
    // Restore all students
    await loadLiveStudents();
    renderAttendance();
    if (loadingEl) loadingEl.style.display = 'none';
    return;
  }

  try {
    const data = await fetch(`api_classes.php?action=group_students&group_id=${groupId}`).then(r=>r.json());
    const apiStudents = (data.ok && data.students) ? data.students : [];
    STUDENTS = apiStudents.map(s => ({
      id:       s.id,
      name:     s.name || s.username,
      init:     (s.name||s.username||'?').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase(),
      progress: 0, assigns: 0, avg: 0, status: 'good', sessions: 0, present: 0,
    }));
    renderAttendance();
  } catch(e) {
    console.error(e);
  } finally {
    if (loadingEl) loadingEl.style.display = 'none';
  }
}

function renderCourses(){
  const tr=CT[currentLang];
  const n=teacherGroups.length;
  const sub=document.getElementById('courses-page-sub'); if(sub)sub.textContent=tr.coursesPageSub(n);
  const ttl=document.getElementById('courses-page-title'); if(ttl)ttl.textContent=tr.coursesPageTitle;
  const badge=document.getElementById('nav-courses-badge'); if(badge){ badge.textContent=n; badge.style.display=n>0?'':'none'; }
  /* update tab button labels for current language */
  const lang = currentLang;
  const gBtn = document.getElementById('tab-btn-groups');
  const sBtn = document.getElementById('tab-btn-schedule');
  if (gBtn) gBtn.textContent = lang==='en'?'Groups':'Groupes';
  if (sBtn) sBtn.textContent = lang==='en'?'Schedule':'Horaires';
}

/* ── Courses tab switcher ── */
function _coursesTabUI(tab) {
  const gc = document.getElementById('tc-groups-content');
  const sc = document.getElementById('tc-schedule-content');
  const gb = document.getElementById('tab-btn-groups');
  const sb = document.getElementById('tab-btn-schedule');
  if (tab === 'schedule') {
    if (gc) gc.style.display = 'none';
    if (sc) sc.style.display = '';
    if (gb) { gb.style.background = 'transparent'; gb.style.color = 'var(--muted)'; }
    if (sb) { sb.style.background = 'var(--primary)'; sb.style.color = '#fff'; }
  } else {
    if (gc) gc.style.display = '';
    if (sc) sc.style.display = 'none';
    if (gb) { gb.style.background = 'var(--primary)'; gb.style.color = '#fff'; }
    if (sb) { sb.style.background = 'transparent'; sb.style.color = 'var(--muted)'; }
  }
}
function switchCoursesTab(tab) {
  _coursesTabUI(tab);
  if (tab === 'schedule') renderTeacherSchedule();
}

/* ── Weekly schedule view ── */
function renderTeacherSchedule() {
  const el = document.getElementById('teacher-schedule-view');
  if (!el) return;
  const lang = currentLang;

  const DAY_ORDER = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
  const DAY_LABELS = {
    fr: {Lundi:'Lundi',Mardi:'Mardi',Mercredi:'Mercredi',Jeudi:'Jeudi',Vendredi:'Vendredi',Samedi:'Samedi'},
    en: {Lundi:'Monday',Mardi:'Tuesday',Mercredi:'Wednesday',Jeudi:'Thursday',Vendredi:'Friday',Samedi:'Saturday'}
  };

  /* Collect all slots grouped by day_fr */
  const byDay = {};
  DAY_ORDER.forEach(d => { byDay[d] = []; });
  teacherGroups.forEach(g => {
    let slots = [];
    try { slots = JSON.parse(g.schedule_json || '[]'); } catch(e) {}
    slots.forEach(s => {
      if (s.day_fr && byDay[s.day_fr] !== undefined) byDay[s.day_fr].push({g, s});
    });
  });
  /* Sort each day by start time */
  DAY_ORDER.forEach(d => byDay[d].sort((a,b) => (a.s.time||'').localeCompare(b.s.time||'')));

  const hasAny = DAY_ORDER.some(d => byDay[d].length > 0);
  if (!hasAny) {
    el.innerHTML = `<div style="text-align:center;padding:3rem 1rem;color:var(--muted);font-size:.9rem;">${lang==='en'?'No schedule configured yet.':'Aucun horaire configuré pour l\'instant.'}</div>`;
    return;
  }

  const dayLabels = DAY_LABELS[lang] || DAY_LABELS.fr;
  const studentWord = lang==='en'?'student(s)':'étudiant(s)';

  el.innerHTML = DAY_ORDER.filter(d => byDay[d].length > 0).map(d => {
    const rows = byDay[d].map(({g, s}) => {
      const name = lang==='en' ? (g.label_en||g.label_fr) : g.label_fr;
      const timeStr = s.time ? (s.time_end ? `${s.time} – ${s.time_end}` : s.time) : '—';
      const icon    = TYPE_ICONS[g.type_key]      || '🏫';
      const iconCls = TYPE_ICON_CLASS[g.type_key] || 'c1';
      return `<div onclick="openGroupDetail(${g.group_id})" role="button" tabindex="0"
        style="display:flex;align-items:center;gap:.85rem;padding:.7rem 1rem;border-radius:10px;cursor:pointer;background:var(--card-bg,#fff);border:1px solid var(--border2);margin-bottom:.45rem;transition:box-shadow .15s;"
        onmouseover="this.style.boxShadow='0 2px 12px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='none'">
        <div style="min-width:90px;font-family:var(--font);font-size:.9rem;font-weight:700;color:var(--primary);flex-shrink:0;">${escHtml(timeStr)}</div>
        <div class="course-icon ${iconCls}" style="flex-shrink:0;width:32px;height:32px;font-size:.85rem;display:flex;align-items:center;justify-content:center;">${icon}</div>
        <div style="flex:1;min-width:0;">
          <div style="font-family:var(--font);font-size:.88rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(name)}</div>
          <div style="font-size:.73rem;color:var(--muted);margin-top:.1rem;">👥 ${g.student_count||0} ${studentWord}</div>
        </div>
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="color:var(--muted);flex-shrink:0;"><polyline points="9 18 15 12 9 6"/></svg>
      </div>`;
    }).join('');

    return `<div style="margin-bottom:1.75rem;">
      <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.7rem;">
        <span style="font-family:var(--font);font-size:.82rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--primary);">${escHtml(dayLabels[d]||d)}</span>
        <span style="flex:1;height:2px;background:linear-gradient(to right,var(--primary),transparent);border-radius:2px;"></span>
        <span style="font-size:.72rem;color:var(--muted);font-weight:600;">${byDay[d].length} ${lang==='en'?(byDay[d].length===1?'class':'classes'):(byDay[d].length===1?'groupe':'groupes')}</span>
      </div>
      ${rows}
    </div>`;
  }).join('');
}

async function openGroupDetail(groupId) {
  const g    = teacherGroups.find(g => g.group_id === groupId);
  if (!g) return;
  const lang = currentLang;
  const typeKey = g.type_key;
  const typeLabels = TYPE_LABELS[typeKey] || {fr: typeKey, en: typeKey};
  const labelEn = (typeLabels.en || typeLabels.fr) + (g.level_number ? ' ' + g.level_number : '') + (g.group_letter ? ' ' + g.group_letter : '');
  const label = lang === 'en' ? labelEn : g.label_fr;

  document.getElementById('courses-list-view').style.display = 'none';
  document.getElementById('courses-detail-view').style.display = 'block';
  document.getElementById('course-detail-title').textContent = label;
  document.getElementById('course-detail-meta').textContent = g.student_count + T[lang].studentSuffix;
  document.getElementById('cd-students-title').textContent = T[lang].groupStudentsTitle;
  document.getElementById('back-courses-lbl').textContent = T[lang].backCourses2;

  /* ── Schedule timing ── */
  const DAY_EN_T = {Lundi:'Monday',Mardi:'Tuesday',Mercredi:'Wednesday',Jeudi:'Thursday',Vendredi:'Friday',Samedi:'Saturday',Dimanche:'Sunday'};
  let schedHtml = '';
  if (g.schedule_json) {
    try {
      const sched = JSON.parse(g.schedule_json);
      if (Array.isArray(sched) && sched.length) {
        schedHtml = sched.map(s => {
          const day = lang === 'en' ? (DAY_EN_T[s.day_fr] || s.day_fr) : s.day_fr;
          const timeStr = s.time ? (s.time_end ? s.time + ' – ' + s.time_end : s.time) : '';
          return `<span style="display:inline-flex;align-items:center;gap:.3rem;background:var(--tag-bg,#f0f4ff);color:var(--primary);border-radius:6px;padding:.25rem .6rem;font-size:.8rem;font-weight:600;">🕐 ${escHtml(day)}${timeStr ? ' · ' + timeStr : ''}</span>`;
        }).join(' ');
      }
    } catch(e) {}
  }
  let schedEl = document.getElementById('cd-schedule-row');
  if (!schedEl) {
    schedEl = document.createElement('div');
    schedEl.id = 'cd-schedule-row';
    schedEl.style.cssText = 'display:flex;flex-wrap:wrap;gap:.4rem;margin:.5rem 0 .8rem;';
    const metaEl = document.getElementById('course-detail-meta');
    if (metaEl && metaEl.parentNode) metaEl.parentNode.insertBefore(schedEl, metaEl.nextSibling);
  }
  schedEl.innerHTML = schedHtml;

  const listEl = document.getElementById('cd-students-list');
  listEl.innerHTML = '<div class="loading-overlay" style="position:relative;height:60px;"><div class="spinner"></div></div>';

  try {
    const data = await fetch(`api_classes.php?action=group_students&group_id=${groupId}`).then(r => r.json());
    const students = data.students || [];
    if (students.length === 0) {
      listEl.innerHTML = `<p style="color:var(--muted);font-size:.85rem;">${lang==='en'?'No students in this group':'Aucun étudiant dans ce groupe'}</p>`;
      return;
    }
    listEl.innerHTML = students.map((s, i) => {
      const init = escHtml((s.name || s.username || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase());
      return `<div class="cd-student-row" style="${i > 0 ? 'border-top:1px solid var(--border2);' : ''}padding:.65rem 0;">
        <div class="student-avatar-sm">${init}</div>
        <div style="flex:1;">
          <div style="font-family:var(--font);font-size:.88rem;font-weight:600;">${escHtml(s.name || s.username)}</div>
          <div style="font-size:.75rem;color:var(--muted);">@${escHtml(s.username)}</div>
        </div>
        <button onclick="openMessageModal(${s.id},'${escHtml(s.name||s.username).replace(/'/g,"&#39;")}')"
          style="background:rgba(91,156,246,.12);border:1px solid rgba(91,156,246,.25);color:var(--blue);border-radius:8px;padding:.3rem .65rem;cursor:pointer;font-size:.75rem;font-family:var(--font);white-space:nowrap;"
          title="Send message">✉️</button>
      </div>`;
    }).join('');
    document.getElementById('course-detail-meta').textContent = students.length + T[lang].studentSuffix;
  } catch(e) {
    listEl.innerHTML = `<p style="color:var(--red);font-size:.85rem;"></p>`;
  }
}

function closeCourseDetail() {
  document.getElementById('courses-list-view').style.display = 'block';
  document.getElementById('courses-detail-view').style.display = 'none';
}

/* ── AVATAR UPLOAD ── */
function handleAvatarUpload(input) {
  const file = input.files[0];
  if (!file) return;
  if (file.size > 2 * 1024 * 1024) {
    showToast(T[currentLang].imageTooLarge);
    return;
  }
  const reader = new FileReader();
  reader.onload = function(e) {
    const dataUrl = e.target.result;
    applyAvatarEverywhere(dataUrl);
    try { localStorage.setItem('upskill_avatar_t', dataUrl); } catch(e) {}
    showToast(T[currentLang].photoUpdated);
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

/* ── LESSON POSTS ── */
async function loadPosts() {
  if (teacherGroups.length === 0) await loadTeacherGroups();
  populatePostCourseSelect();
  const list = document.getElementById('posts-list');
  list.innerHTML = `<p style="color:var(--muted);font-size:.85rem;"></p>`;
  try {
    const res  = await fetch('api_lesson_posts.php?action=list');
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);
    _cachedPosts = data.posts;
    renderPostsList(_cachedPosts);
  } catch(e) {
    list.innerHTML = `<p style="color:var(--red);font-size:.85rem;"></p>`;
  }
}

function populatePostCourseSelect() {
  const sel = document.getElementById('post-course-select');
  if (!sel) return;
  const lang = currentLang;
  const groups = teacherGroups;
  sel.innerHTML = groups.length === 0
    ? `<option value="">${T[lang].noClassAssigned}</option>`
    : `<option value="">${T[lang].chooseClass}</option>`
      + groups.map(g => {
          const name = lang === 'en' ? (g.label_en || g.label_fr) : g.label_fr;
          return `<option value="${g.group_id}" data-gid="${g.group_id}">${name}</option>`;
        }).join('');
}

function renderPostsList(posts) {
  const list = document.getElementById('posts-list');
  if (!posts || posts.length === 0) {
    const t = T[currentLang];
    list.innerHTML = `<div class="card" style="text-align:center;padding:3rem;">
      <div style="font-size:2.5rem;margin-bottom:.75rem;">📝</div>
      <div style="font-family:var(--font);font-weight:600;margin-bottom:.4rem;" id="posts-empty-title">${t.postsEmptyTitle}</div>
      <p style="color:var(--muted);font-size:.85rem;" id="posts-empty-txt">${t.postsEmptyTxt}</p>
    </div>`;
    return;
  }
  // Build group label from type_key/level/letter if the post came from the new system
  const TYPE_LABELS = { en:{}, fr:{} };
  if (typeof CLASS_TYPE_MAP !== 'undefined') {
    Object.entries(CLASS_TYPE_MAP).forEach(([k,v]) => { TYPE_LABELS.en[k] = v.en; TYPE_LABELS.fr[k] = v.fr; });
  }
  list.innerHTML = posts.map(p => {
    const lang = currentLang;
    let courseName;
    if (p.group_id && p.type_key) {
      // New system: build label from class_groups fields
      const g = teacherGroups.find(x => x.group_id === parseInt(p.group_id));
      if (g) {
        courseName = lang === 'en' ? (g.label_en || g.label_fr) : g.label_fr;
      } else {
        // Fallback: rebuild from raw fields
        const lvl = p.level_number ? ' ' + p.level_number : '';
        const grpWord = lang === 'en' ? 'Group' : 'Groupe';
        courseName = (p.type_key || '') + lvl + ' – ' + grpWord + ' ' + (p.group_letter || '');
      }
    } else {
      courseName = p.group_name_fr;
    }
    const dateStr = p.session_date ? new Date(p.session_date + 'T00:00:00').toLocaleDateString(lang === 'fr' ? 'fr-FR' : 'en-GB', { day:'numeric', month:'long', year:'numeric' }) : '';
    const linkBtn = p.link ? `<a href="${escHtml(p.link)}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:.4rem;font-size:.8rem;color:var(--blue);text-decoration:none;font-weight:500;margin-top:.5rem;"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg> ${T[lang].openLink}</a>` : '';
    const notesHtml = p.notes ? `<p style="color:var(--muted);font-size:.85rem;margin-top:.6rem;white-space:pre-wrap;line-height:1.6;">${escHtml(p.notes)}</p>` : '';
    return `<div class="card" style="margin-bottom:1rem;">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;">
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.5rem;">
            <span style="font-size:.72rem;font-weight:700;background:rgba(245,158,11,.15);color:#f59e0b;border:1px solid rgba(245,158,11,.3);padding:.15rem .55rem;border-radius:100px;font-family:var(--font);">${escHtml(courseName)}</span>
            <span style="font-size:.78rem;color:var(--muted);">📅 ${dateStr}</span>
          </div>
          <div style="font-family:var(--font);font-weight:600;font-size:.95rem;">${escHtml(p.title)}</div>
          ${linkBtn}${notesHtml}
        </div>
        <div style="display:flex;gap:.4rem;flex-shrink:0;">
          <button onclick="editPost(${p.id},'${escHtml(p.title).replace(/'/g,"\\'")}','${p.session_date}','${escHtml(p.link||'').replace(/'/g,"\\'")}','${escHtml(p.notes||'').replace(/\n/g,'\\n').replace(/'/g,"\\'")}',${p.course_id})" style="background:none;border:1px solid var(--border);border-radius:8px;padding:.35rem .6rem;cursor:pointer;color:var(--muted);transition:all .2s;" onmouseover="this.style.borderColor='var(--blue)';this.style.color='var(--blue)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'" title="${T[currentLang]?.editPost||(lang==='en'?'Edit':'Modifier')}">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <button onclick="deletePost(${p.id})" style="background:none;border:1px solid var(--border);border-radius:8px;padding:.35rem .6rem;cursor:pointer;color:var(--muted);transition:all .2s;" onmouseover="this.style.borderColor='var(--red)';this.style.color='var(--red)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'" title="${T[currentLang]?.postsDeleteConfirm||(lang==='en'?'Delete':'Supprimer')}">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          </button>
        </div>
      </div>
    </div>`;
  }).join('');
}

async function submitPost() {
  if (_editPostId) { await updatePost(); return; }
  const groupId  = document.getElementById('post-course-select').value;
  const title    = document.getElementById('post-title').value.trim();
  const date     = document.getElementById('post-date').value;
  const link     = document.getElementById('post-link').value.trim();
  const notes    = document.getElementById('post-notes').value.trim();
  const errEl    = document.getElementById('post-form-error');
  const btnLbl   = document.getElementById('post-submit-lbl');
  errEl.style.display = 'none';

  if (!groupId || !title || !date) {
    errEl.textContent = currentLang === 'en' ? 'Please fill in the required fields.' : 'Veuillez remplir les champs requis.';
    errEl.style.display = '';
    return;
  }
  btnLbl.textContent = '…';
  document.getElementById('post-submit-btn').disabled = true;
  try {
    const res  = await fetch('api_lesson_posts.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrfToken },
      body:    JSON.stringify({ action: 'create', group_id: parseInt(groupId), title, session_date: date, link: link || null, notes: notes || null })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);
    document.getElementById('post-title').value = '';
    document.getElementById('post-link').value  = '';
    document.getElementById('post-notes').value = '';
    showToast(T[currentLang].toastPostPublished);
    loadPosts();
  } catch(e) {
    errEl.textContent = e.message;
    errEl.style.display = '';
  } finally {
    btnLbl.textContent = T[currentLang].postsSubmitBtn;
    document.getElementById('post-submit-btn').disabled = false;
  }
}

function deletePost(id) {
  openConfirmModal(T[currentLang].postsDeleteConfirm, async () => {
  try {
    const res  = await fetch('api_lesson_posts.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrfToken },
      body:    JSON.stringify({ action: 'delete', id })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);
    showToast(T[currentLang].toastPostDeleted);
    loadPosts();
  } catch(e) { showToast((currentLang==='en'?'Error: ':'Erreur : ') + e.message); }
  });
}

let _editPostId = null;
function editPost(id, title, date, link, notes, courseId) {
  _editPostId = id;
  // Reuse the create form fields
  document.getElementById('post-title').value = title;
  document.getElementById('post-date').value  = date;
  document.getElementById('post-link').value  = link;
  document.getElementById('post-notes').value = notes.replace(/\\n/g, '\n');
  // Mark form as editing mode
  const btn = document.getElementById('post-submit-lbl');
  if (btn) btn.textContent = T[currentLang]?.editPost || (currentLang==='en'?'Update note':'Mettre à jour');
  // Scroll form into view
  document.getElementById('posts-form-title')?.scrollIntoView({ behavior: 'smooth' });
}

async function updatePost() {
  const title = document.getElementById('post-title').value.trim();
  const date  = document.getElementById('post-date').value;
  const link  = document.getElementById('post-link').value.trim();
  const notes = document.getElementById('post-notes').value.trim();
  const errEl = document.getElementById('post-form-error');
  const btnLbl = document.getElementById('post-submit-lbl');
  errEl.style.display = 'none';
  if (!title || !date) {
    errEl.textContent = currentLang==='en'?'Please fill in the required fields.':'Veuillez remplir les champs requis.';
    errEl.style.display = ''; return;
  }
  btnLbl.textContent = '…';
  document.getElementById('post-submit-btn').disabled = true;
  try {
    const res  = await fetch('api_lesson_posts.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrfToken },
      body: JSON.stringify({ action: 'update', id: _editPostId, title, session_date: date, link: link || null, notes: notes || null })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);
    _editPostId = null;
    ['post-title','post-link','post-notes'].forEach(id => { document.getElementById(id).value = ''; });
    showToast(currentLang === 'en' ? 'Note updated!' : 'Note mise à jour !');
    loadPosts();
  } catch(e) { errEl.textContent = e.message; errEl.style.display = ''; }
  finally {
    btnLbl.textContent = T[currentLang].postsSubmitBtn;
    document.getElementById('post-submit-btn').disabled = false;
  }
}



/* ── ACTIVITY FEED ── */
const ACT_DOT_COLORS = { assignment_created:'blue', submission_graded:'green', assignment_graded:'purple' };

/* ── TODAY'S CLASSES ── */
// French weekday names matching schedule_json day_fr values
const DAY_NAMES_FR = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
const DAY_NAMES_EN = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

async function loadTodayClasses() {
  const container = document.getElementById('today-classes-list');
  if (!container) return;

  const lang = currentLang;
  const tr   = T[lang];
  container.innerHTML = `<div style="color:var(--muted);font-size:.85rem;padding:.5rem 0;">${tr.loading || (lang==='en'?'Loading…':'Chargement…')}</div>`;

  const today   = new Date();
  const todayFr = DAY_NAMES_FR[today.getDay()];

  let groups = [];
  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res  = await fetch('api_classes.php?action=teacher_groups', { headers: { 'X-CSRF-Token': csrf } });
    const data = await res.json();
    groups = data.ok ? (data.groups || []) : [];
  } catch(e) {
    container.innerHTML = `<div style="color:var(--red);font-size:.84rem;">${T[currentLang].toastNetError}</div>`;
    return;
  }

  _cachedTodayGroups = groups.filter(c => {
    if (!c.schedule_json) return false;
    try {
      const sched = JSON.parse(c.schedule_json);
      return Array.isArray(sched) && sched.some(s => s.day_fr && s.day_fr.toLowerCase() === todayFr.toLowerCase());
    } catch(e) { return false; }
  });

  renderTodayClasses();
}

/* Pure render from cached _cachedTodayGroups — re-called on lang switch */
function renderTodayClasses() {
  const container = document.getElementById('today-classes-list');
  const dateLbl   = document.getElementById('today-date-lbl');
  if (!container) return;

  const lang         = currentLang;
  const tr           = T[lang];
  const today        = new Date();
  const todayCourses = _cachedTodayGroups || [];

  if (dateLbl) {
    dateLbl.textContent = today.toLocaleDateString(lang === 'fr' ? 'fr-FR' : 'en-GB', { weekday:'long', day:'numeric', month:'long' });
  }

  if (todayCourses.length === 0 && _cachedTodayGroups !== null) {
    container.innerHTML = `<div style="color:var(--muted);font-size:.85rem;padding:.75rem 0;text-align:center;">${tr.todayNoClasses}</div>`;
    return;
  }
  if (!todayCourses.length) return; // still loading

  const todayFr = DAY_NAMES_FR[today.getDay()];

  container.innerHTML = todayCourses.map((c, idx) => {
    const gid  = c.group_id;
    const name = lang === 'en' ? (c.label_en || c.label_fr) : (c.label_fr || c.label_en);

    // Parse schedule for time/room of today's session
    let sessionInfo = '';
    try {
      const sched = JSON.parse(c.schedule_json || '[]');
      const todaySlot = sched.find(s =>
        s.day_fr && s.day_fr.toLowerCase() === todayFr.toLowerCase()
      );
      if (todaySlot) {
        if (todaySlot.time) sessionInfo += todaySlot.time;
        if (todaySlot.time_end) sessionInfo += ' – ' + todaySlot.time_end;
      }
    } catch(e) {}

    const hasZoom   = c.zoom_url && c.zoom_url.trim() !== '';
    const inputId   = `zoom-input-${gid}`;
    const btnId     = `zoom-btn-${gid}`;
    const statusId  = `zoom-status-${gid}`;
    const detailId  = `today-detail-${gid}`;

    return `
    <div style="border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:.85rem;">
      <!-- Class header — click to expand -->
      <button onclick="toggleTodayDetail('${gid}')"
        style="width:100%;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 1.1rem;background:rgba(255,255,255,.03);border:none;cursor:pointer;text-align:left;transition:background .15s;"
        onmouseover="this.style.background='rgba(255,255,255,.07)'" onmouseout="this.style.background='rgba(255,255,255,.03)'"
        aria-expanded="false" id="today-hdr-${gid}">
        <div>
          <div style="font-family:var(--font);font-weight:700;font-size:.95rem;color:var(--white);">${name}</div>
          <div style="font-size:.78rem;color:var(--muted);margin-top:.2rem;">
            ${sessionInfo ? `<span>🕐 ${sessionInfo}</span>` : ''}
            <span style="${sessionInfo?'margin-left:.75rem':''}">👥 ${c.student_count || 0} ${tr.todayStudents}</span>
            ${hasZoom ? `<span style="margin-left:.75rem;color:var(--green);font-size:.73rem;">● Zoom ✓</span>` : `<span style="margin-left:.75rem;color:var(--yellow);font-size:.73rem;">${lang==='en'?'● Zoom link missing':'● Zoom manquant'}</span>`}
          </div>
        </div>
        <svg id="today-chevron-${gid}" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="flex-shrink:0;color:var(--muted);transition:transform .2s;"><polyline points="6 9 12 15 18 9"/></svg>
      </button>

      <!-- Expanded Zoom URL editor -->
      <div id="${detailId}" style="display:none;padding:1rem 1.1rem;background:rgba(30,27,75,.06);border-top:1px solid var(--border);">
        <label style="display:block;font-family:var(--font);font-size:.72rem;font-weight:600;color:var(--muted);letter-spacing:.07em;text-transform:uppercase;margin-bottom:.45rem;">${tr.todayZoomLbl}</label>
        <div style="display:flex;gap:.65rem;align-items:center;flex-wrap:wrap;">
          <input type="url" id="${inputId}" value="${c.zoom_url ? escHtml(c.zoom_url) : ''}"
            placeholder="${tr.todayZoomPlaceholder}"
            style="flex:1;min-width:200px;padding:.75rem 1rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.88rem;outline:none;transition:border-color .2s;"
            onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--border)'"
            onkeydown="if(event.key==='Enter'){saveTodayZoom(${gid})}">
          <button id="${btnId}" onclick="saveTodayZoom(${gid})"
            style="padding:.73rem 1.1rem;background:var(--blue);border:none;border-radius:10px;color:white;font-family:var(--font);font-size:.85rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:opacity .15s;"
            onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            ${tr.todayZoomSave}
          </button>
        </div>
        <div id="${statusId}" style="display:none;font-size:.78rem;margin-top:.5rem;"></div>
        <div style="font-size:.72rem;color:var(--muted);margin-top:.45rem;">${tr.todayZoomHint}</div>
        ${hasZoom ? `<div style="margin-top:.85rem;">
          <a href="${escHtml(c.zoom_url)}" target="_blank" rel="noopener noreferrer"
            style="display:inline-flex;align-items:center;gap:.4rem;font-size:.83rem;color:var(--blue);text-decoration:none;font-family:var(--font);font-weight:600;"
            onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
            ${lang==='en'?'Join class →':'Rejoindre le cours →'}
          </a>
        </div>` : ''}
      </div>
    </div>`;
  }).join('');
}

function toggleTodayDetail(courseId) {
  const detail  = document.getElementById(`today-detail-${courseId}`);
  const chevron = document.getElementById(`today-chevron-${courseId}`);
  const hdr     = document.getElementById(`today-hdr-${courseId}`);
  if (!detail) return;
  const open = detail.style.display === 'none';
  detail.style.display  = open ? '' : 'none';
  if (chevron) chevron.style.transform = open ? 'rotate(180deg)' : '';
  if (hdr) hdr.setAttribute('aria-expanded', String(open));
  if (open) {
    const inp = document.getElementById(`zoom-input-${courseId}`);
    if (inp) setTimeout(() => inp.focus(), 80);
  }
}

async function saveTodayZoom(courseId) {
  const input    = document.getElementById(`zoom-input-${courseId}`);
  const btn      = document.getElementById(`zoom-btn-${courseId}`);
  const statusEl = document.getElementById(`zoom-status-${courseId}`);
  const tr       = T[currentLang];
  if (!input) return;
  const zoomUrl = input.value.trim();
  const origBtn = btn ? btn.textContent : '';
  if (btn) { btn.textContent = tr.todaySaving; btn.disabled = true; }
  statusEl.style.display = 'none';
  try {
    const res  = await fetch('api_classes.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrfToken },
      body: JSON.stringify({ action: 'set_group_zoom_url', group_id: courseId, zoom_url: zoomUrl })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || tr.toastServerError);

    statusEl.textContent    = tr.todayZoomSaved;
    statusEl.style.color    = 'var(--green)';
    statusEl.style.display  = '';
    // Update the header badge
    const hdr = document.getElementById(`today-hdr-${courseId}`);
    if (hdr) {
      const badge = hdr.querySelector('.zoom-status-dot');
      if (!badge) {
        // Refresh the whole card to reflect new zoom state
        loadTodayClasses();
        return;
      }
    }
    loadTodayClasses(); // re-render to update ✓ / missing badge & join link
  } catch(err) {
    statusEl.textContent   = '❌ ' + err.message;
    statusEl.style.color   = 'var(--red)';
    statusEl.style.display = '';
    if (btn) { btn.textContent = origBtn; btn.disabled = false; }
  }
}

async function loadActivityFeed() {
  const list = document.getElementById('activity-list');
  if (!list) return;
  try {
    const res  = await fetch('api_assignments.php?action=activity');
    const data = await res.json();
    if (!data.ok || !data.activity || !data.activity.length) {
      list.innerHTML = `<div style="color:var(--muted);font-size:.85rem;padding:.5rem 0;">${T[currentLang].activityEmpty}</div>`;
      return;
    }
    list.innerHTML = data.activity.map(item => {
      const dotCol = ACT_DOT_COLORS[item.type] || 'blue';
      const date   = new Date(item.created_at + 'Z').toLocaleDateString(currentLang === 'fr' ? 'fr-FR' : 'en-GB', {day:'numeric', month:'short', hour:'2-digit', minute:'2-digit'});
      return `<div class="activity-item">
        <div class="activity-dot ${dotCol}"></div>
        <div>
          <div class="activity-text">${escHtml(item.description)}</div>
          <div class="activity-time">${date}</div>
        </div>
      </div>`;
    }).join('');
  } catch(e) {
    const list2 = document.getElementById('activity-list');
    if (list2) list2.innerHTML = `<div style="color:var(--muted);font-size:.85rem;padding:.5rem 0;">—</div>`;
  }
}

/* ── NOTIFICATIONS ── */
let notifPanelOpen = false;

/* ── FOCUS TRAP UTILITY ── */
function getFocusable(container) {
  return Array.from(container.querySelectorAll(
    'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'
  )).filter(el => !el.closest('[hidden]') && el.offsetParent !== null);
}
function makeTrapHandler(containerEl, closeFn) {
  return function(e) {
    if (e.key === 'Escape') { e.preventDefault(); closeFn(); return; }
    if (e.key !== 'Tab') return;
    const focusable = getFocusable(containerEl);
    if (focusable.length === 0) return;
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (e.shiftKey) {
      if (document.activeElement === first) { e.preventDefault(); last.focus(); }
    } else {
      if (document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  };
}
let _notifTrapHandler = null;
let _profileTrapHandler = null;

function toggleNotifPanel() {
  notifPanelOpen = !notifPanelOpen;
  const panel = document.getElementById('notif-panel');
  panel.classList.toggle('open', notifPanelOpen);
  if (notifPanelOpen) {
    loadNotifications();
    if (!_notifTrapHandler) _notifTrapHandler = makeTrapHandler(panel, () => closeNotifPanel(true));
    panel.addEventListener('keydown', _notifTrapHandler);
    setTimeout(() => { const f = getFocusable(panel); if (f.length) f[0].focus(); }, 50);
  } else {
    if (_notifTrapHandler) panel.removeEventListener('keydown', _notifTrapHandler);
    document.getElementById('notif-btn')?.focus();
  }
}

function closeNotifPanel(returnFocus = false) {
  notifPanelOpen = false;
  const panel = document.getElementById('notif-panel');
  panel.classList.remove('open');
  if (_notifTrapHandler) panel.removeEventListener('keydown', _notifTrapHandler);
  // Only return focus to the trigger button when the user explicitly closed the panel
  // (e.g. via Escape key). Never steal focus when closing programmatically from navigate().
  if (returnFocus) document.getElementById('notif-btn')?.focus();
}

async function loadNotifBadge() {
  try {
    const res  = await fetch('api_notifications.php?action=list');
    const data = await res.json();
    if (!data.ok) return;
    const badge = document.getElementById('notif-badge');
    if (badge) {
      const n = data.unread || 0;
      badge.textContent = n > 9 ? '9+' : (n > 0 ? n : '');
      badge.classList.toggle('show', n > 0);
    }
  } catch(e) {}
}

const TEACHER_NOTIF_ICONS = {
  new_assignment:'📚', overdue:'⚠️', submission:'✅', info:'🔔',
  announcement:'📢', quiz:'🧠', message:'💬',
};

async function loadNotifications() {
  const list = document.getElementById('notif-list');
  if (!list) return;
  list.innerHTML = `<div style="padding:1.5rem;text-align:center;color:var(--muted);font-size:.85rem;">${T[currentLang].loading}</div>`;
  try {
    const res  = await fetch('api_notifications.php?action=list');
    const data = await res.json();
    if (!data.ok) return;

    // Mark all as read
    if (data.unread > 0) {
      fetch('api_notifications.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':_csrfToken}, body:JSON.stringify({action:'mark_read'}) });
      const badge = document.getElementById('notif-badge');
      if (badge) { badge.textContent = ''; badge.classList.remove('show'); }
    }

    if (!data.notifications || !data.notifications.length) {
      list.innerHTML = `<div style="padding:1.5rem;text-align:center;color:var(--muted);font-size:.85rem;">${T[currentLang].notifEmpty}</div>`;
      return;
    }

    const lang = currentLang;
    list.innerHTML = data.notifications.map(n => {
      const title = lang === 'en' ? (n.title_en || n.title_fr) : n.title_fr;
      const body  = lang === 'en' ? (n.body_en  || n.body_fr)  : n.body_fr;
      const icon  = TEACHER_NOTIF_ICONS[n.type] || '🔔';
      const age   = parseInt(n.age_min) || 0;
      const timeStr = age < 1 ? (lang==='en'?'Just now':'À l\'instant')
                    : age < 60 ? `${age} min`
                    : age < 1440 ? `${Math.floor(age/60)}h`
                    : `${Math.floor(age/1440)}d`;
      return `<div class="notif-item${parseInt(n.is_read) ? '' : ' unread'}" data-type="${n.type}" onclick="markOneRead(${n.id},this)">
        <div style="width:34px;height:34px;border-radius:10px;background:rgba(30,27,75,.06);display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;">${icon}</div>
        <div style="flex:1;min-width:0;">
          <div style="font-family:var(--font);font-size:.8rem;font-weight:600;margin-bottom:.1rem;">${escHtml(title)}</div>
          ${body ? `<div style="font-size:.77rem;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:240px;">${escHtml(body)}</div>` : ''}
          <div style="font-size:.7rem;color:var(--muted2);margin-top:.15rem;">${timeStr}</div>
        </div>
      </div>`;
    }).join('');
  } catch(e) {
    if (list) list.innerHTML = `<div style="padding:1rem;color:var(--red);font-size:.83rem;">${T[currentLang].toastNetError}</div>`;
  }
}

function markOneRead(id, el) {
  // Update UI immediately
  el.classList.remove('unread');
  el.style.borderLeft = '';
  // Fire-and-forget server sync
  fetch('api_notifications.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-Token':_csrfToken},
    body:JSON.stringify({action:'mark_one', id})
  }).catch(() => {});
  // Close panel and navigate to relevant page
  closeNotifPanel(false);
  const type = el.dataset.type || '';
  const typeNav = {
    submission:'assignments', new_assignment:'assignments', overdue:'assignments',
    quiz:'quizzes', announcement:'home', message:'home', lesson_note:'posts'
  };
  const dest = typeNav[type] || 'home';
  const navIdMap = {home:'nav-home',assignments:'nav-assign',quizzes:'nav-quiz',posts:'nav-posts'};
  navigate(dest, document.getElementById(navIdMap[dest]));
}

/* ── PROFILE DROPDOWN ── */
let profileMenuOpen = false;

function toggleProfileMenu(e) {
  e.stopPropagation();
  profileMenuOpen = !profileMenuOpen;
  const menu = document.getElementById('profile-menu');
  menu.classList.toggle('open', profileMenuOpen);
  if (profileMenuOpen) {
    applyProfileMenuTranslations();
    if (!_profileTrapHandler) _profileTrapHandler = makeTrapHandler(menu, () => {
      profileMenuOpen = false;
      menu.classList.remove('open');
      if (_profileTrapHandler) menu.removeEventListener('keydown', _profileTrapHandler);
      document.getElementById('topbar-avatar')?.focus();
    });
    menu.addEventListener('keydown', _profileTrapHandler);
    setTimeout(() => { const f = getFocusable(menu); if (f.length) f[0].focus(); }, 50);
  } else {
    if (_profileTrapHandler) menu.removeEventListener('keydown', _profileTrapHandler);
    document.getElementById('topbar-avatar')?.focus();
  }
}

function applyProfileMenuTranslations() {
  const PM = {
    fr: { name:"Changer le prénom", avatar:"Changer l'avatar", logout:"Déconnexion" },
    en: { name:"Change first name",  avatar:"Change avatar",    logout:"Log out" },
  };
  const t = PM[currentLang] || PM.en;
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
  if (notifPanelOpen && !document.getElementById('notif-btn').contains(e.target) && !document.getElementById('notif-panel').contains(e.target)) {
    notifPanelOpen = false;
    document.getElementById('notif-panel').classList.remove('open');
  }
});
</script>
</body>
</html>
