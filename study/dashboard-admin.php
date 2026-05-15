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
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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
<title>Upskill – Admin Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&family=Cairo:wght@300;400;500;600;700&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&family=Cairo:wght@300;400;500;600;700&display=swap"></noscript>
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --navy:#0f1d2e; --navy-mid:#162436; --navy-light:#1e3248; --navy-card:rgba(255,255,255,0.04);
  --green:#3ecf78; --green-dark:#28a85c; --green-glow:rgba(62,207,120,0.15); --green-dim:rgba(62,207,120,0.1);
  --white:#ffffff; --muted:rgba(255,255,255,0.55); --muted2:rgba(255,255,255,0.50);
  --border:rgba(255,255,255,0.1); --border2:rgba(255,255,255,0.07);
  --yellow:#f5c542; --red:#e85d75; --blue:#5b9cf6; --purple:#a78bfa;
  --orange:#fb923c;
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
.sidebar { width:var(--sidebar-w); background:var(--navy-mid); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:200; }
.sidebar-logo { display:flex; align-items:center; gap:.6rem; padding:1.5rem 1.4rem; border-bottom:1px solid var(--border); }
.sidebar-logo span { font-family:var(--font); font-weight:600; font-size:1rem; }
body.ar .sidebar-logo span { font-family:var(--font-ar); }
.sidebar-logo em { color:var(--green); font-style:normal; }
.admin-chip { margin-left:auto; background:rgba(251,146,60,.15); color:var(--orange); border:1px solid rgba(251,146,60,.3); font-family:var(--font); font-size:.65rem; font-weight:700; padding:.2rem .55rem; border-radius:100px; }
body.ar .admin-chip { margin-left:0; margin-right:auto; }
.lang-toggle { display:flex; gap:.4rem; padding:.6rem 1.4rem; border-bottom:1px solid var(--border); }
.lang-pill { font-size:.7rem; font-family:var(--font); font-weight:600; padding:.25rem .65rem; border-radius:100px; border:1px solid var(--border); color:var(--muted); cursor:pointer; transition:all .2s; }
.lang-pill.active { background:var(--green-glow); border-color:rgba(62,207,120,.4); color:var(--green); }
.sidebar-user { padding:1.2rem 1.4rem; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:.8rem; }
body.ar .sidebar-user { flex-direction:row-reverse; }
.avatar { width:38px; height:38px; border-radius:50%; background:rgba(251,146,60,.15); border:2px solid rgba(251,146,60,.4); display:flex; align-items:center; justify-content:center; font-family:var(--font); font-weight:700; font-size:.85rem; color:var(--orange); flex-shrink:0; }
.avatar.large { width:56px; height:56px; font-size:1.2rem; }
.user-info .name { font-family:var(--font); font-size:.85rem; font-weight:600; line-height:1.2; }
body.ar .user-info .name { font-family:var(--font-ar); }
.user-info .role-tag { font-size:.72rem; color:var(--orange); background:rgba(251,146,60,.15); padding:.1rem .5rem; border-radius:100px; margin-top:.2rem; display:inline-block; }
.sidebar-nav { flex:1; padding:1rem .8rem; overflow-y:auto; }
.nav-section-label { font-family:var(--font); font-size:.65rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muted2); padding:.5rem .6rem .3rem; margin-top:.5rem; }
body.ar .nav-section-label { letter-spacing:0; text-align:right; font-family:var(--font-ar); }
.nav-item { display:flex; align-items:center; gap:.75rem; padding:.65rem .9rem; border-radius:10px; cursor:pointer; color:var(--muted); font-size:.88rem; font-family:var(--font); font-weight:500; transition:all .2s; margin-bottom:.1rem; }
body.ar .nav-item { flex-direction:row-reverse; font-family:var(--font-ar); }
.nav-item svg { flex-shrink:0; opacity:.7; }
.nav-item:hover { background:rgba(255,255,255,.05); color:var(--white); }
.nav-item.active { background:rgba(251,146,60,.1); color:var(--orange); border:1px solid rgba(251,146,60,.25); }
.nav-item.active svg { opacity:1; }
.nav-badge { margin-left:auto; background:var(--yellow); color:var(--navy); font-size:.65rem; font-weight:700; padding:.15rem .45rem; border-radius:100px; font-family:var(--font); }
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
.btn-icon:hover { border-color:var(--orange); color:var(--orange); background:rgba(251,146,60,.1); }
.page { padding:2rem; display:none; animation:fadeIn .25s ease; }
.page.active { display:block; }
@keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

/* GRID & CARDS */
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
.grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:1.5rem; }
.grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; }
.card { background:var(--navy-card); border:1px solid var(--border); border-radius:16px; padding:1.5rem; transition:border-color .2s; }
.card:hover { border-color:rgba(251,146,60,.2); }
.card-title { font-family:var(--font); font-size:.8rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); margin-bottom:1rem; }
body.ar .card-title { font-family:var(--font-ar); letter-spacing:0; text-align:right; }

/* STAT CARDS */
.stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; margin-bottom:1rem; font-size:1.3rem; }
.stat-icon.orange { background:rgba(251,146,60,.1); }
.stat-icon.green  { background:var(--green-dim); }
.stat-icon.blue   { background:rgba(91,156,246,.1); }
.stat-icon.purple { background:rgba(167,139,250,.1); }
.stat-value { font-family:var(--font); font-size:2rem; font-weight:700; letter-spacing:-.03em; margin-bottom:.25rem; }
.stat-label { font-size:.83rem; color:var(--muted); }
body.ar .stat-label { text-align:right; font-family:var(--font-ar); }

/* PROGRESS */
.progress-bar { height:8px; background:rgba(255,255,255,.08); border-radius:100px; overflow:hidden; margin:.5rem 0; }
.progress-fill { height:100%; border-radius:100px; background:var(--green); transition:width .8s; }
.progress-fill.orange { background:var(--orange); }

/* WELCOME BANNER */
.welcome-banner { background:linear-gradient(135deg,rgba(22,36,54,1) 0%,rgba(40,24,10,1) 100%); border:1px solid rgba(251,146,60,.2); border-radius:20px; padding:2rem 2.5rem; margin-bottom:2rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; position:relative; overflow:hidden; }
body.ar .welcome-banner { flex-direction:row-reverse; }
.welcome-banner::before { content:''; position:absolute; top:-60px; right:-60px; width:200px; height:200px; background:radial-gradient(circle,rgba(251,146,60,.1),transparent 70%); }
.welcome-text h2 { font-family:var(--font); font-size:1.6rem; font-weight:700; letter-spacing:-.03em; margin-bottom:.4rem; }
body.ar .welcome-text h2 { font-family:var(--font-ar); letter-spacing:0; text-align:right; }
.welcome-text h2 span { color:var(--orange); }
.welcome-text p { color:var(--muted); font-size:.9rem; }
body.ar .welcome-text p { text-align:right; font-family:var(--font-ar); }

/* BUTTONS */
.btn-primary { background:var(--orange); color:var(--white); font-family:var(--font); font-weight:700; font-size:.88rem; padding:.65rem 1.3rem; border:none; border-radius:10px; cursor:pointer; transition:background .2s,transform .15s; display:inline-flex; align-items:center; gap:.4rem; }
body.ar .btn-primary { font-family:var(--font-ar); }
.btn-primary:hover { background:#ea7c22; transform:translateY(-1px); }
.btn-primary.danger { background:var(--red); }
.btn-primary.danger:hover { background:#c94060; }
.btn-secondary { background:rgba(255,255,255,.06); color:var(--muted); font-family:var(--font); font-weight:500; font-size:.88rem; padding:.65rem 1.3rem; border:1px solid var(--border); border-radius:10px; cursor:pointer; transition:all .2s; }
body.ar .btn-secondary { font-family:var(--font-ar); }
.btn-secondary:hover { border-color:rgba(255,255,255,.2); color:var(--white); }
.btn-sm { padding:.4rem .9rem; font-size:.78rem; border-radius:8px; }

/* TABLES */
.data-table { width:100%; border-collapse:collapse; }
.data-table th { font-family:var(--font); font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--muted2); padding:.75rem 1rem; text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
body.ar .data-table th { text-align:right; font-family:var(--font-ar); letter-spacing:0; }
.data-table td { padding:.85rem 1rem; border-bottom:1px solid var(--border2); font-size:.88rem; vertical-align:middle; }
body.ar .data-table td { text-align:right; }
.data-table tr:last-child td { border-bottom:none; }
.data-table tr:hover td { background:rgba(251,146,60,.03); }

/* BADGES */
.badge { display:inline-flex; align-items:center; padding:.2rem .65rem; border-radius:100px; font-size:.72rem; font-weight:700; font-family:var(--font); flex-shrink:0; }
.badge.assigned   { background:rgba(62,207,120,.12); color:var(--green); border:1px solid rgba(62,207,120,.3); }
.badge.unassigned { background:rgba(245,197,66,.12); color:var(--yellow); border:1px solid rgba(245,197,66,.3); }
.badge.level      { background:rgba(91,156,246,.12); color:var(--blue); border:1px solid rgba(91,156,246,.3); }


/* MODAL */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:1000; display:none; align-items:flex-start; justify-content:center; backdrop-filter:blur(4px); padding:2rem 1rem; overflow-y:auto; }
.modal-overlay.open { display:flex; }
.modal { background:var(--navy-mid); border:1px solid var(--border); border-radius:20px; padding:2rem; max-width:580px; width:100%; margin:auto; animation:slideUp .25s ease; }
.modal.sm { max-width:400px; }
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
  width:100%; padding:.8rem 1rem; background:rgba(255,255,255,.05); border:1px solid var(--border);
  border-radius:10px; color:var(--white); font-family:var(--font-body); font-size:.9rem;
  outline:none; transition:border-color .2s; resize:vertical;
}
body.ar .form-group input, body.ar .form-group select { font-family:var(--font-ar); text-align:right; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color:var(--orange); background:rgba(251,146,60,.04); }
.form-group select option { background:var(--navy-mid); }
.modal-footer { display:flex; gap:.75rem; justify-content:flex-end; margin-top:1.5rem; }
body.ar .modal-footer { flex-direction:row-reverse; }

/* SCHEDULE BUILDER */
.schedule-row { display:grid; grid-template-columns:1fr 1fr 1fr 1fr auto; gap:.4rem; align-items:center; margin-bottom:.4rem; }
.schedule-row input { padding:.5rem .7rem; font-size:.82rem; }

/* SPINNER */
.spinner { display:inline-block; width:16px; height:16px; border:2px solid rgba(255,255,255,.3); border-top-color:var(--white); border-radius:50%; animation:spin .6s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

/* LOADING STATE */
.loading-overlay { display:flex; align-items:center; justify-content:center; padding:3rem; color:var(--muted); gap:.75rem; font-size:.9rem; }

/* EMPTY STATE */
.empty-state { text-align:center; padding:3rem 1rem; color:var(--muted); }
.empty-state .empty-icon { font-size:2.5rem; margin-bottom:.75rem; }
.empty-state p { font-size:.9rem; }

/* ACTIVITY DOT */
.activity-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:.3rem; }
.activity-dot.green  { background:var(--green); }
.activity-dot.orange { background:var(--orange); }
.activity-dot.blue   { background:var(--blue); }
.activity-dot.red    { background:var(--red); }
.activity-item { display:flex; gap:1rem; padding:.85rem 0; border-bottom:1px solid var(--border2); }
body.ar .activity-item { flex-direction:row-reverse; }
.activity-item:last-child { border-bottom:none; }
.activity-text { font-size:.86rem; color:var(--muted); line-height:1.5; }
body.ar .activity-text { text-align:right; font-family:var(--font-ar); }
.activity-text strong { color:var(--white); font-weight:500; }
.activity-time { font-size:.75rem; color:var(--muted2); margin-top:.15rem; }

/* TOAST */
.toast { position:fixed; bottom:2rem; right:2rem; background:var(--navy-light); border:1px solid var(--border); border-radius:12px; padding:.9rem 1.4rem; font-family:var(--font); font-size:.85rem; color:var(--white); z-index:9999; transform:translateY(100px); opacity:0; transition:all .3s; display:flex; align-items:center; gap:.6rem; max-width:360px; }
body.ar .toast { right:auto; left:2rem; font-family:var(--font-ar); }
.toast.show { transform:translateY(0); opacity:1; }
.toast-dot { width:8px; height:8px; border-radius:50%; background:var(--orange); flex-shrink:0; }
.toast-dot.success { background:var(--green); }
.toast-dot.error   { background:var(--red); }

/* TABS */
.tabs { display:flex; gap:.4rem; flex-wrap:wrap; }
.tab { padding:.5rem 1.1rem; border-radius:100px; font-family:var(--font); font-size:.82rem; font-weight:600; color:var(--muted); border:1px solid var(--border); cursor:pointer; transition:all .2s; }
.tab:hover { color:var(--white); border-color:rgba(255,255,255,.2); }
.tab.active { background:rgba(251,146,60,.12); color:var(--orange); border-color:rgba(251,146,60,.35); }

/* CLASS CARDS */
.class-type-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; }
.class-type-card { background:var(--navy-card); border:1px solid var(--border); border-radius:16px; padding:1.25rem 1.4rem; cursor:pointer; transition:border-color .2s,transform .15s; }
.class-type-card:hover { border-color:rgba(251,146,60,.4); transform:translateY(-2px); }
.class-type-card .ct-name { font-family:var(--font); font-size:1rem; font-weight:700; margin-bottom:.3rem; }
.class-type-card .ct-sub  { font-size:.78rem; color:var(--muted); }
.level-cards { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; }
.level-card { background:var(--navy-card); border:1px solid var(--border); border-radius:14px; padding:1.1rem 1.25rem; cursor:pointer; transition:border-color .2s,transform .15s; }
.level-card:hover { border-color:rgba(91,156,246,.4); transform:translateY(-2px); }
.level-card .lc-title { font-family:var(--font); font-size:.95rem; font-weight:700; margin-bottom:.25rem; }
.level-card .lc-sub   { font-size:.78rem; color:var(--muted); }
.group-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:1rem; }
.group-card { background:var(--navy-card); border:1px solid var(--border); border-radius:14px; padding:1.1rem 1.25rem; cursor:pointer; transition:border-color .2s,transform .15s; position:relative; }
.group-card:hover { border-color:rgba(62,207,120,.45); transform:translateY(-2px); }
.group-card .gc-letter { font-family:var(--font); font-size:1.3rem; font-weight:800; color:var(--green); margin-bottom:.5rem; }
.group-card .gc-teacher { font-size:.78rem; color:var(--text); margin-bottom:.3rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.group-card .gc-students { font-size:.74rem; color:var(--muted); line-height:1.5; }
.group-card .gc-students.gc-empty { font-style:italic; }
.group-card .gc-del { position:absolute; top:.55rem; right:.6rem; background:none; border:none; color:var(--muted); cursor:pointer; font-size:.85rem; line-height:1; padding:.2rem; border-radius:6px; transition:color .15s,background .15s; }
.group-card .gc-del:hover { color:var(--red); background:rgba(232,93,117,.1); }
.breadcrumb { display:flex; align-items:center; gap:.5rem; font-size:.82rem; color:var(--muted); margin-bottom:1.5rem; flex-wrap:wrap; }
.breadcrumb span.bc-link { cursor:pointer; transition:color .15s; }
.breadcrumb span.bc-link:hover { color:var(--orange); }
.breadcrumb span.bc-sep { color:var(--border); }
.breadcrumb span.bc-cur { color:var(--white); font-weight:600; }

/* MEMBER LIST in modal */
.member-row { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.7rem 0; border-bottom:1px solid var(--border2); }
.member-row:last-child { border-bottom:none; }
.member-info { display:flex; align-items:center; gap:.6rem; }
.member-init { width:32px; height:32px; border-radius:50%; background:rgba(91,156,246,.15); border:1px solid rgba(91,156,246,.3); display:flex; align-items:center; justify-content:center; font-family:var(--font); font-weight:700; font-size:.72rem; color:var(--blue); flex-shrink:0; }
.member-init.teacher { background:rgba(62,207,120,.12); border-color:rgba(62,207,120,.3); color:var(--green); }

/* HAMBURGER */
.hamburger { display:none; width:36px; height:36px; border-radius:9px; background:rgba(255,255,255,.05); border:1px solid var(--border); align-items:center; justify-content:center; cursor:pointer; color:var(--muted); flex-shrink:0; }
.sidebar-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:199; }
.sidebar-backdrop.open { display:block; }

@media(max-width:768px){
  .hamburger { display:flex; }
  .sidebar { transform:translateX(-100%); }
  body.ar .sidebar { transform:translateX(100%); }
  .sidebar.open { transform:translateX(0)!important; }
  .main { margin-left:0!important; margin-right:0!important; }
  .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr 1fr; }
  .page { padding:1rem; }
  .schedule-row { grid-template-columns:1fr 1fr; }
  .schedule-row input:nth-child(3),.schedule-row input:nth-child(4) { grid-column:span 1; }
}
@media(max-width:480px){
  .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr; }
}
</style>
</head>
<body id="body">
<a href="#main-content" class="skip-link" style="position:absolute;top:-40px;left:0;background:var(--green);color:#0f1d2e;padding:.5rem 1rem;font-family:var(--font);font-weight:700;font-size:.85rem;z-index:9999;border-radius:0 0 8px 0;transition:top .2s;text-decoration:none;">Skip to content</a>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">
  <div class="sidebar-logo">
    <svg width="26" height="26" viewBox="0 0 28 28" fill="none"><rect width="28" height="28" rx="8" fill="#fb923c"/><path d="M8 14l4 4 8-8" stroke="#0f1d2e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Up<em style="color:var(--orange)">skill</em></span>
    <div class="admin-chip" id="admin-chip-lbl">Admin</div>
  </div>
  <div class="lang-toggle">
    <div class="lang-pill active" id="pill-fr" onclick="setLang('fr')">🇫🇷 FR</div>
    <div class="lang-pill"        id="pill-en" onclick="setLang('en')">🇬🇧 EN</div>
  </div>
  <div class="sidebar-user">
    <div class="avatar" id="sidebar-avatar">AD</div>
    <div class="user-info">
      <div class="name" id="sidebar-name"><?= $full_name ?: 'Administrateur' ?></div>
      <div class="role-tag" id="role-label">Administrateur</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label" id="nav-main-label">Principal</div>
    <div class="nav-item active" onclick="navigate('home',this)" id="nav-home">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span id="nav-home-lbl">Tableau de bord</span>
    </div>
    <div class="nav-item" onclick="navigate('users',this)" id="nav-users">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span id="nav-users-lbl">Utilisateurs</span>
      <span class="nav-badge" id="nav-users-badge" style="display:none"></span>
    </div>
    <div class="nav-item" onclick="navigate('inscriptions',this)" id="nav-inscriptions">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <span id="nav-inscriptions-lbl">Inscriptions</span>
      <span class="nav-badge" id="nav-inscriptions-badge" style="display:none"></span>
    </div>
    <div class="nav-item" onclick="navigate('classes',this)" id="nav-classes">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><rect x="9" y="13" width="6" height="9"/></svg>
      <span id="nav-classes-lbl">Classes</span>
    </div>
    <div class="nav-item" onclick="navigate('assigning-classes',this)" id="nav-assigning-classes">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="23" y2="8"/><line x1="21" y1="6" x2="21" y2="10"/></svg>
      <span id="nav-assigning-classes-lbl">Assignation des classes</span>
    </div>
    <div class="nav-item" onclick="navigate('schedule',this)" id="nav-schedule">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span id="nav-schedule-lbl">Horaires des groupes</span>
    </div>
    <div class="nav-section-label" id="nav-account-label">Compte</div>
    <div class="nav-item" onclick="navigate('settings',this)" id="nav-settings">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span id="nav-settings-lbl">Paramètres</span>
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
      <div class="topbar-title" id="topbar-title">Tableau de bord Admin</div>
    </div>
    <div class="topbar-actions">
      <div class="avatar" id="topbar-avatar" style="cursor:default;">AD</div>
    </div>
  </div>

  <!-- ── HOME PAGE ── -->
  <div class="page active" id="page-home">
    <div class="welcome-banner">
      <div class="welcome-text">
        <h2><span id="welcome-greeting">Bonjour,</span> <span id="welcome-name"><?= $full_name ?: 'Admin' ?></span> 👋</h2>
        <p id="welcome-sub">Gérez les classes et les groupes depuis ce tableau de bord.</p>
      </div>
      <div style="font-size:3rem;">🛠️</div>
    </div>
    <div class="grid-4" style="margin-bottom:1.5rem;">
      <div class="card"><div class="stat-icon blue"><span aria-hidden="true">👨‍🏫</span></div><div class="stat-value" id="stat-teachers">—</div><div class="stat-label" id="stat-teachers-lbl">Professeurs</div></div>
      <div class="card"><div class="stat-icon green"><span aria-hidden="true">🎓</span></div><div class="stat-value" id="stat-students">—</div><div class="stat-label" id="stat-students-lbl">Étudiants</div></div>
      <div class="card"><div class="stat-icon orange"><span aria-hidden="true">📋</span></div><div class="stat-value" id="stat-enrollments">—</div><div class="stat-label" id="stat-enrollments-lbl">Inscriptions</div></div>
      <div class="card"><div class="stat-icon purple"><span aria-hidden="true">🏫</span></div><div class="stat-value" id="stat-groups">—</div><div class="stat-label" id="stat-groups-lbl">Groupes actifs</div></div>
    </div>
    <div class="grid-2">
      <div class="card">
        <div class="card-title" id="recent-activity-title">Activité récente</div>
        <div id="activity-list">
          <div class="activity-item"><div class="activity-dot orange"></div><div><div class="activity-text"><strong id="activity-loading-lbl">Loading…</strong></div></div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── USERS PAGE ── -->
  <div class="page" id="page-users">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="users-page-title">Gestion des utilisateurs</h2>
        <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="users-page-sub">Gérez les emails et mots de passe des étudiants et professeurs</p>
      </div>
      <button class="btn-primary" onclick="openAddUserModal()" id="btn-add-user">
        + <span id="btn-add-user-lbl">Nouvel utilisateur</span>
      </button>
    </div>
    <!-- Filter tabs -->
    <div class="tabs" style="margin-bottom:1.5rem;">
      <div class="tab active" onclick="filterUsers('all',this)" id="tab-all-users">Tous</div>
      <div class="tab" onclick="filterUsers('student',this)" id="tab-students">Étudiants</div>
      <div class="tab" onclick="filterUsers('teacher',this)" id="tab-teachers">Professeurs</div>
    </div>
    <!-- Search -->
    <div style="margin-bottom:1rem;">
      <input type="text" id="users-search" placeholder="Rechercher par nom ou email…"
        oninput="renderUsersTable()"
        style="width:100%;max-width:380px;padding:.75rem 1rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.88rem;outline:none;">
    </div>
    <!-- Table -->
    <div class="card" style="padding:0;overflow:hidden;">
      <div style="overflow-x:auto;">
        <table id="users-table" style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:1px solid var(--border);">
              <th style="padding:.9rem 1.2rem;text-align:left;font-family:var(--font);font-size:.72rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="th-name">Nom</th>
              <th style="padding:.9rem 1.2rem;text-align:left;font-family:var(--font);font-size:.72rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="th-user">Identifiant</th>
              <th style="padding:.9rem 1.2rem;text-align:left;font-family:var(--font);font-size:.72rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="th-email">Email</th>
              <th style="padding:.9rem 1.2rem;text-align:left;font-family:var(--font);font-size:.72rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="th-role">Rôle</th>
              <th style="padding:.9rem 1.2rem;text-align:left;font-family:var(--font);font-size:.72rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="th-actions">Actions</th>
            </tr>
          </thead>
          <tbody id="users-tbody">
            <tr><td colspan="5" style="padding:2rem;text-align:center;color:var(--muted);">
              <div class="spinner" style="margin:0 auto;"></div>
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── INSCRIPTIONS PAGE ── -->
  <div class="page" id="page-inscriptions">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="inscriptions-title">Inscriptions</h2>
        <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="inscriptions-sub">Demandes reçues depuis le formulaire d'inscription</p>
      </div>
      <button class="btn-secondary btn-sm" onclick="exportEnrollmentsCSV()" id="btn-export-csv" style="display:flex;align-items:center;gap:.4rem;">
        ⬇ <span id="export-csv-lbl">Exporter CSV</span>
      </button>
    </div>

    <!-- Filter tabs -->
    <div class="tabs" style="margin-bottom:1.25rem;">
      <div class="tab active" id="enroll-tab-all"      onclick="filterEnrollments('all',this)">      <span id="etab-all-lbl">Toutes</span> <span class="nav-badge" id="enroll-count-all"  style="display:inline;position:static;margin-left:.35rem;"></span></div>
      <div class="tab"        id="enroll-tab-new"      onclick="filterEnrollments('new',this)">      <span id="etab-new-lbl">Nouvelles</span> <span class="nav-badge" id="enroll-count-new" style="display:inline;position:static;margin-left:.35rem;background:rgba(245,197,66,.2);color:var(--yellow);border-color:rgba(245,197,66,.4);"></span></div>
      <div class="tab"        id="enroll-tab-accepted" onclick="filterEnrollments('accepted',this)"> <span id="etab-accepted-lbl">Acceptées</span> <span class="nav-badge" id="enroll-count-accepted" style="display:inline;position:static;margin-left:.35rem;background:rgba(62,207,120,.12);color:var(--green);border-color:rgba(62,207,120,.3);"></span></div>
      <div class="tab"        id="enroll-tab-refused"  onclick="filterEnrollments('refused',this)">  <span id="etab-refused-lbl">Refusées</span> <span class="nav-badge" id="enroll-count-refused" style="display:inline;position:static;margin-left:.35rem;background:rgba(232,93,117,.12);color:var(--red);border-color:rgba(232,93,117,.3);"></span></div>
    </div>

    <!-- Search -->
    <div style="margin-bottom:1rem;">
      <input type="text" id="enroll-search" placeholder="Rechercher par nom, email, téléphone…" oninput="debounceEnrollSearch()"
        style="width:100%;max-width:360px;padding:.65rem 1rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.85rem;outline:none;">
    </div>

    <!-- Table -->
    <div class="card" style="padding:0;overflow:hidden;">
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:1px solid var(--border);">
              <th style="padding:.9rem 1.2rem;text-align:left;font-family:var(--font);font-size:.7rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="eth-name">Nom</th>
              <th style="padding:.9rem 1.2rem;text-align:left;font-family:var(--font);font-size:.7rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="eth-email">Email</th>
              <th style="padding:.9rem 1.2rem;text-align:left;font-family:var(--font);font-size:.7rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="eth-phone">Téléphone</th>
              <th style="padding:.9rem 1.2rem;text-align:left;font-family:var(--font);font-size:.7rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="eth-date">Date</th>
              <th style="padding:.9rem 1.2rem;text-align:left;font-family:var(--font);font-size:.7rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="eth-status">Statut</th>
              <th style="padding:.9rem 1.2rem;text-align:left;font-family:var(--font);font-size:.7rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;"></th>
            </tr>
          </thead>
          <tbody id="enrollments-tbody">
            <tr><td colspan="6" style="padding:2.5rem;text-align:center;color:var(--muted);">
              <div class="spinner" style="margin:0 auto;"></div>
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── CLASSES PAGE ── -->
  <div class="page" id="page-classes">
    <!-- Breadcrumb (hidden at root level) -->
    <div id="classes-breadcrumb" class="breadcrumb" style="display:none;"></div>

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="classes-page-title">Classes</h2>
        <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="classes-page-sub">Sélectionnez un type de classe</p>
      </div>
      <button class="btn-primary" id="btn-add-group" style="display:none;" onclick="openAddGroupModal()">
        + <span id="btn-add-group-lbl">Ajouter un groupe</span>
      </button>
    </div>

    <!-- View: type grid -->
    <div id="classes-view-types" class="class-type-grid"></div>

    <!-- View: level cards (for types with levels) -->
    <div id="classes-view-levels" style="display:none;" class="level-cards"></div>

    <!-- View: groups within a level/type -->
    <div id="classes-view-groups" style="display:none;">
      <div id="classes-group-cards" class="group-cards">
        <div class="loading-overlay"><div class="spinner"></div></div>
      </div>
    </div>
  </div>

  <!-- ── ASSIGNING CLASSES PAGE ── -->
  <div class="page" id="page-assigning-classes">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="assigning-page-title">Assignation des classes</h2>
        <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="assigning-page-sub">Aperçu des groupes assignés aux étudiants et professeurs</p>
      </div>
    </div>
    <div class="grid-2" style="gap:1.5rem;">
      <!-- Students -->
      <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:1.25rem 1.4rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.6rem;">
          <span style="font-size:1.1rem;">🎓</span>
          <span class="card-title" style="margin-bottom:0;" id="assigning-students-title">Étudiants</span>
        </div>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;" id="assigning-students-table">
            <thead>
              <tr style="border-bottom:1px solid var(--border);">
                <th style="padding:.75rem 1.2rem;text-align:left;font-family:var(--font);font-size:.7rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="ath-student">Étudiant</th>
                <th style="padding:.75rem 1.2rem;text-align:left;font-family:var(--font);font-size:.7rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="ath-group">Groupe(s)</th>
              </tr>
            </thead>
            <tbody id="assigning-students-tbody">
              <tr><td colspan="2" style="padding:2rem;text-align:center;color:var(--muted);"><div class="spinner" style="margin:0 auto;"></div></td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <!-- Teachers -->
      <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:1.25rem 1.4rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.6rem;">
          <span style="font-size:1.1rem;">👨‍🏫</span>
          <span class="card-title" style="margin-bottom:0;" id="assigning-teachers-title">Professeurs</span>
        </div>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;" id="assigning-teachers-table">
            <thead>
              <tr style="border-bottom:1px solid var(--border);">
                <th style="padding:.75rem 1.2rem;text-align:left;font-family:var(--font);font-size:.7rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="ath-teacher">Professeur</th>
                <th style="padding:.75rem 1.2rem;text-align:left;font-family:var(--font);font-size:.7rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;" id="ath-teacher-group">Groupe(s)</th>
              </tr>
            </thead>
            <tbody id="assigning-teachers-tbody">
              <tr><td colspan="2" style="padding:2rem;text-align:center;color:var(--muted);"><div class="spinner" style="margin:0 auto;"></div></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ── SCHEDULE PAGE ── -->
  <div class="page" id="page-schedule">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="schedule-page-title">Horaires des groupes</h2>
        <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="schedule-page-sub">Définissez les jours et horaires de sessions pour chaque groupe</p>
      </div>
    </div>
    <div id="schedule-cards-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1.25rem;">
      <div style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--muted);font-size:.88rem;">
        <div class="spinner" style="margin:0 auto 1rem;"></div><span id="sched-loading-lbl">Loading…</span>
      </div>
    </div>
  </div>

  <!-- ── SETTINGS PAGE ── -->
  <div class="page" id="page-settings">
    <div style="margin-bottom:1.5rem;">
      <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="settings-title">Paramètres</h2>
    </div>
    <div class="grid-2">
      <div class="card">
        <div class="card-title" id="profile-title">Profil administrateur</div>
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
          <div class="avatar large" id="settings-avatar">AD</div>
          <div>
            <div style="font-family:var(--font);font-weight:600;" id="settings-name"><?= $full_name ?: 'Administrateur' ?></div>
            <div style="color:var(--muted);font-size:.83rem;" id="settings-role">Administrateur · Upskill</div>
          </div>
        </div>
        <div class="form-group">
          <label id="lbl-fullname">Nom complet</label>
          <input type="text" id="pref-name" value="<?= $full_name ?: 'Administrateur' ?>">
        </div>
        <button class="btn-primary" onclick="saveProfile()" id="save-btn">Enregistrer</button>
      </div>
      <div class="card">
        <div class="card-title" id="pref-title">Préférences</div>
        <p style="color:var(--muted);font-size:.85rem;line-height:1.6;" id="pref-txt">Utilisez le sélecteur de langue dans la barre latérale pour basculer entre le Français et l'Arabe.</p>
        <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border);">
          <div style="font-family:var(--font);font-size:.78rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;margin-bottom:.75rem;" id="security-label">Sécurité</div>
          <a href="totp_setup.php" style="display:flex;align-items:center;gap:.75rem;padding:.8rem 1rem;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:12px;text-decoration:none;color:var(--white);transition:.2s;" onmouseenter="this.style.borderColor='rgba(251,146,60,.4)';this.style.background='rgba(251,146,60,.06)'" onmouseleave="this.style.borderColor='var(--border)';this.style.background='rgba(255,255,255,.04)'">
            <div style="width:36px;height:36px;border-radius:10px;background:rgba(251,146,60,.1);border:1px solid rgba(251,146,60,.25);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;">🔐</div>
            <div>
              <div style="font-family:var(--font);font-size:.87rem;font-weight:600;" id="2fa-link-title">Double authentification (2FA)</div>
              <div style="color:var(--muted);font-size:.75rem;margin-top:.1rem;" id="2fa-link-sub">Configurer Google Authenticator ou Authy</div>
            </div>
            <svg style="margin-left:auto;flex-shrink:0;color:var(--muted);" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- ── MODAL: ADD USER ── -->
<div class="modal-overlay" id="modal-add-user">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <h3 id="modal-add-user-title">Nouvel utilisateur</h3>
      <button class="modal-close" onclick="closeModal('add-user')" aria-label="Close">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label id="lbl-au-fullname">Nom complet</label>
        <input type="text" id="au-fullname" maxlength="100" placeholder="Prénom Nom">
      </div>
      <div class="form-group">
        <label id="lbl-au-username">Identifiant (login)</label>
        <input type="text" id="au-username" maxlength="80" placeholder="ex: jean.dupont" autocomplete="off">
      </div>
      <div class="form-group">
        <label id="lbl-au-email">Email</label>
        <input type="email" id="au-email" maxlength="180" placeholder="email@example.com">
      </div>
      <div class="form-group">
        <label id="lbl-au-role">Rôle</label>
        <select id="au-role" style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.9rem;outline:none;">
          <option value="student" id="au-role-student">Étudiant</option>
          <option value="teacher" id="au-role-teacher">Professeur</option>
        </select>
      </div>
      <div class="form-group">
        <label id="lbl-au-password">Mot de passe initial</label>
        <input type="password" id="au-password" maxlength="200" placeholder="Minimum 8 caractères" autocomplete="new-password">
      </div>
      <div id="au-error" style="display:none;color:var(--red);font-size:.85rem;margin-bottom:.5rem;"></div>
      <input type="hidden" id="au-enrollment-id" value="">
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('add-user')" id="au-cancel-btn">Annuler</button>
      <button class="btn-primary" onclick="submitAddUser()" id="au-submit-btn">Créer l'utilisateur</button>
    </div>
  </div>
</div>

<!-- ── MODAL: RESET PASSWORD ── -->
<div class="modal-overlay" id="modal-reset-pw">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <h3 id="modal-reset-title">Réinitialiser le mot de passe</h3>
      <button class="modal-close" onclick="closeModal('reset-pw')" aria-label="Close">✕</button>
    </div>
    <div class="modal-body">
      <p style="color:var(--muted);font-size:.88rem;margin-bottom:1.25rem;" id="reset-pw-sub">
        Définir un nouveau mot de passe pour <strong id="reset-pw-name"></strong>
      </p>
      <div class="form-group">
        <label id="lbl-new-pw">Nouveau mot de passe</label>
        <input type="password" id="new-pw" maxlength="200" placeholder="Minimum 8 caractères" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label id="lbl-new-pw2">Confirmer</label>
        <input type="password" id="new-pw2" maxlength="200" placeholder="Répétez le mot de passe" autocomplete="new-password">
      </div>
      <div id="reset-pw-error" style="display:none;color:var(--red);font-size:.85rem;margin-bottom:.5rem;"></div>
      <input type="hidden" id="reset-pw-uid">
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('reset-pw')" id="rp-cancel-btn">Annuler</button>
      <button class="btn-primary" onclick="submitResetPassword()" id="rp-submit-btn">Enregistrer</button>
    </div>
  </div>
</div>
<!-- ── MODAL: ADD GROUP ── -->
<div class="modal-overlay" id="modal-add-group">
  <div class="modal sm">
    <div class="modal-header">
      <h3 id="modal-add-group-title">Nouveau groupe</h3>
      <button class="btn-close" onclick="closeModal('add-group')" aria-label="Close">✕</button>
    </div>
    <div class="form-group">
      <label id="lbl-group-letter">Lettre du groupe</label>
      <input type="text" id="group-letter-input" maxlength="3" placeholder="A, B, C…" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
    </div>
    <div id="add-group-error" style="display:none;color:var(--red);font-size:.82rem;margin-bottom:.5rem;"></div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('add-group')" id="add-group-cancel">Annuler</button>
      <button class="btn-primary" onclick="submitAddGroup()" id="add-group-submit">Créer</button>
    </div>
  </div>
</div>

<!-- ── MODAL: MANAGE GROUP MEMBERS ── -->
<div class="modal-overlay" id="modal-group-members">
  <div class="modal" style="max-width:600px;">
    <div class="modal-header">
      <h3 id="modal-members-title">Membres du groupe</h3>
      <button class="btn-close" onclick="closeModal('group-members')" aria-label="Close">✕</button>
    </div>

    <!-- Current members list -->
    <div class="card-title" style="margin-bottom:.5rem;" id="members-current-lbl">Membres actuels</div>
    <div id="members-list" style="margin-bottom:1.25rem;max-height:220px;overflow-y:auto;">
      <div class="loading-overlay"><div class="spinner"></div></div>
    </div>

    <!-- Add member -->
    <div class="card-title" style="margin-bottom:.5rem;" id="members-add-lbl">Ajouter un membre</div>
    <div style="display:flex;gap:.6rem;align-items:center;">
      <select id="member-user-select" style="flex:1;padding:.7rem 1rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.88rem;outline:none;">
        <option value="">— Sélectionner un utilisateur —</option>
      </select>
      <button class="btn-primary btn-sm" onclick="submitAddMember()" id="btn-add-member">Add</button>
    </div>
    <div id="member-add-error" style="display:none;color:var(--red);font-size:.82rem;margin-top:.4rem;"></div>
    <div class="modal-footer" style="margin-top:1.25rem;">
      <button class="btn-secondary" onclick="closeModal('group-members')" id="members-close-btn">Close</button>
    </div>
  </div>
</div>

<!-- SIDEBAR BACKDROP -->
<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleSidebar()"></div>
<!-- TOAST -->
<div class="toast" id="toast"><div class="toast-dot" id="toast-dot"></div><span id="toast-msg"></span></div>

<script>
/* ══════════════════════════════════════════════════════
   STATE
══════════════════════════════════════════════════════ */
let currentLang   = 'fr';
let activePage    = 'home';

/* ══════════════════════════════════════════════════════
   TRANSLATIONS
══════════════════════════════════════════════════════ */
const T = {
  fr: {
    adminChip:'Admin', roleLabel:'Administrateur',
    logout:'Déconnexion',
    navMain:'Principal', navAccount:'Compte',
    navHome:'Tableau de bord', navSettings:'Paramètres',
    navUsers:'Utilisateurs', navInscriptions:'Inscriptions',
    navClasses:'Classes', navAssigningClasses:'Assignation des classes', navSchedule:'Horaires des groupes',
    classesPageTitle:'Classes', classesPageSub:'Sélectionnez un type de classe',
    assigningPageTitle:'Assignation des classes', assigningPageSub:'Aperçu des groupes assignés aux étudiants et professeurs',
    schedulePageTitle:'Horaires des groupes', schedulePageSub:'Définissez les jours et horaires de sessions pour chaque groupe',
    schNoGroups:'Aucun groupe trouvé.', schAddSlot:'+ Ajouter une séance', schSave:'Enregistrer', schSaving:'…',
    schSaved:'✔ Enregistré', schTeacher:'Prof.', schStudents:'étudiant(s)',
    schDayLabel:'Jour', schTimeFromLabel:'De', schTimeToLabel:'À',
    schNoSlots:'Aucune séance configurée.',
    schDays:[
      {fr:'Lundi',en:'Monday',ar:'الاثنين'},{fr:'Mardi',en:'Tuesday',ar:'الثلاثاء'},{fr:'Mercredi',en:'Wednesday',ar:'الأربعاء'},
      {fr:'Jeudi',en:'Thursday',ar:'الخميس'},{fr:'Vendredi',en:'Friday',ar:'الجمعة'},{fr:'Samedi',en:'Saturday',ar:'السبت'}
    ],
    topbar:{ home:'Tableau de bord Admin', users:'Utilisateurs', inscriptions:'Inscriptions', settings:'Paramètres', classes:'Classes', 'assigning-classes':'Assignation des classes', schedule:'Horaires des groupes' },
    assigningStudentsTitle:'Étudiants', assigningTeachersTitle:'Professeurs',
    ath_student:'Étudiant', ath_group:'Groupe(s)', ath_teacher:'Professeur', ath_teacher_group:'Groupe(s)',
    classesGroupsOf:'Groupes de', noGroups:'Aucun groupe. Cliquez sur + pour en créer un.',
    btnAddGroup:'Ajouter un groupe', modalAddGroupTitle:'Nouveau groupe', lblGroupLetter:'Lettre du groupe',
    membersCurrentLbl:'Membres actuels', membersAddLbl:'Ajouter un membre',
    noMembers:'Aucun membre dans ce groupe.',
    toastGroupCreated:'Groupe créé !', toastGroupDeleted:'Groupe supprimé.', toastMemberAdded:'Membre ajouté !', toastMemberRemoved:'Membre retiré.',
    errGroupLetter:'Veuillez entrer une lettre de groupe.', errGroupExists:'Ce groupe existe déjà.',
    selectUser:'— Sélectionner un utilisateur —',
    confirmDeleteGroup:'Supprimer ce groupe et tous ses membres ?',
    noAssignments:'Aucun groupe assigné.',
    welcomeSub:'Gérez les classes et les groupes depuis ce tableau de bord.',
    statTeachersLbl:'Professeurs', statStudentsLbl:'Étudiants', statEnrollmentsLbl:'Inscriptions', statGroupsLbl:'Groupes actifs',
    recentActivityTitle:'Activité récente',
    settingsTitle:'Paramètres', profileTitle:'Profil administrateur',
    settingsRole:'Administrateur · Upskill',
    lblFullname:'Nom complet', saveBtn:'Enregistrer',
    prefTitle:'Préférences', prefTxt:'Utilisez le sélecteur de langue pour basculer entre le Français et l\'Arabe.',
    cancelBtn:'Annuler', saveEnregistrer:'Enregistrer', deleteBtn:'Supprimer',
    errNetwork:'Erreur réseau. Vérifiez votre connexion.',
    toastProfileSaved:'Profil mis à jour !',
    inscriptionsTitle:'Inscriptions', inscriptionsSub:'Demandes reçues depuis le formulaire d\'inscription',
    exportCSV:'Exporter CSV',
    etabAll:'Toutes', etabNew:'Nouvelles demandes', etabAccepted:'Acceptées', etabRefused:'Refusées',
    ethName:'Nom', ethEmail:'Email', ethPhone:'Téléphone', ethDate:'Date', ethStatus:'Statut',
    statusNew:'Nouvelle demande', statusAccepted:'Acceptée', statusRefused:'Refusée',
    enrollSearchPlaceholder:'Rechercher par nom, email, téléphone…',
    toastStatusUpdated:'Statut mis à jour.',
    toastEnrollDeleted:'Demande supprimée.',
    confirmDeleteEnroll:'Supprimer cette demande définitivement ?',
    greeting:'Bonjour,',
    schLoading:'Chargement…',
    membersCloseBtn:'Fermer', btnAddMember:'Ajouter', addGroupCancel:'Annuler', addGroupSubmit:'Créer',
    tabAll:'Tous', tabStudents:'Étudiants', tabTeachers:'Professeurs',
    thName:'Nom', thUser:'Identifiant', thEmail:'Email', thRole:'Rôle', thActions:'Actions',
    modalAddUserTitle:'Nouvel utilisateur',
    lblAuFullname:'Nom complet', lblAuUsername:'Identifiant (login)', lblAuEmail:'Email', lblAuRole:'Rôle',
    lblAuPassword:'Mot de passe initial', auPwPlaceholder:'Minimum 8 caractères',
    auCancelBtn:'Annuler', auSubmitBtn:'Créer l\'utilisateur',
    modalResetTitle:'Réinitialiser le mot de passe',
    resetPwSub:'Définir un nouveau mot de passe pour',
    lblNewPw:'Nouveau mot de passe', lblNewPw2:'Confirmer',
    newPwPlaceholder:'Minimum 8 caractères', newPw2Placeholder:'Répétez le mot de passe',
    rpCancelBtn:'Annuler', rpSubmitBtn:'Enregistrer',
    twoFaTitle:'Double authentification (2FA)', twoFaSub:'Configurer Google Authenticator ou Authy',
    securityLabel:'Sécurité',
    btnAddUser:'Nouvel utilisateur',
    usersSearchPlaceholder:'Rechercher par nom ou email…',
    usersPageTitle:'Gestion des utilisateurs', usersPageSub:'Gérez les emails et mots de passe des étudiants et professeurs',
    classesPageSub2:'Sélectionnez un type de classe',
  },
  ar: {
    adminChip:'مسؤول', roleLabel:'مسؤول النظام',
    logout:'تسجيل الخروج',
    navMain:'الرئيسية', navAccount:'الحساب',
    navHome:'لوحة التحكم', navSettings:'الإعدادات',
    navUsers:'المستخدمون', navInscriptions:'التسجيلات',
    navClasses:'الفصول', navAssigningClasses:'تعيين الفصول', navSchedule:'جداول المجموعات',
    classesPageTitle:'الفصول', classesPageSub:'اختر نوع الفصل',
    assigningPageTitle:'تعيين الفصول', assigningPageSub:'نظرة عامة على المجموعات المعينة للطلاب والأساتذة',
    schedulePageTitle:'جداول المجموعات', schedulePageSub:'حدد أيام وأوقات الجلسات لكل مجموعة',
    schNoGroups:'لم يُعثر على أي مجموعة.', schAddSlot:'+ إضافة جلسة', schSave:'حفظ', schSaving:'…',
    schSaved:'✔ تم الحفظ', schTeacher:'أ.', schStudents:'طالب/طلاب',
    schDayLabel:'اليوم', schTimeFromLabel:'من', schTimeToLabel:'إلى',
    schNoSlots:'لم يتم تكوين أي جلسة.',
    schDays:[
      {fr:'Lundi',en:'Monday',ar:'الاثنين'},{fr:'Mardi',en:'Tuesday',ar:'الثلاثاء'},{fr:'Mercredi',en:'Wednesday',ar:'الأربعاء'},
      {fr:'Jeudi',en:'Thursday',ar:'الخميس'},{fr:'Vendredi',en:'Friday',ar:'الجمعة'},{fr:'Samedi',en:'Saturday',ar:'السبت'}
    ],
    topbar:{ home:'لوحة تحكم المسؤول', users:'المستخدمون', inscriptions:'التسجيلات', settings:'الإعدادات', classes:'الفصول', 'assigning-classes':'تعيين الفصول', schedule:'جداول المجموعات' },
    assigningStudentsTitle:'الطلاب', assigningTeachersTitle:'الأساتذة',
    ath_student:'الطالب', ath_group:'المجموعة(ات)', ath_teacher:'الأستاذ', ath_teacher_group:'المجموعة(ات)',
    classesGroupsOf:'مجموعات', noGroups:'لا توجد مجموعات. انقر على + لإنشاء واحدة.',
    btnAddGroup:'إضافة مجموعة', modalAddGroupTitle:'مجموعة جديدة', lblGroupLetter:'حرف المجموعة',
    membersCurrentLbl:'الأعضاء الحاليون', membersAddLbl:'إضافة عضو',
    noMembers:'لا يوجد أعضاء في هذه المجموعة.',
    toastGroupCreated:'تم إنشاء المجموعة!', toastGroupDeleted:'تم حذف المجموعة.', toastMemberAdded:'تمت إضافة العضو!', toastMemberRemoved:'تم إزالة العضو.',
    errGroupLetter:'يرجى إدخال حرف المجموعة.', errGroupExists:'هذه المجموعة موجودة بالفعل.',
    selectUser:'— اختر مستخدماً —',
    confirmDeleteGroup:'حذف هذه المجموعة وجميع أعضائها؟',
    noAssignments:'لا توجد مجموعات معينة.',
    welcomeSub:'أدر الفصول والمجموعات من لوحة التحكم هذه.',
    statTeachersLbl:'الأساتذة', statStudentsLbl:'الطلاب', statEnrollmentsLbl:'التسجيلات', statGroupsLbl:'المجموعات النشطة',
    recentActivityTitle:'النشاط الأخير',
    settingsTitle:'الإعدادات', profileTitle:'الملف الشخصي',
    settingsRole:'مسؤول النظام · Upskill',
    lblFullname:'الاسم الكامل', saveBtn:'حفظ',
    prefTitle:'التفضيلات', prefTxt:'استخدم محدد اللغة للتبديل بين الفرنسية والعربية.',
    cancelBtn:'إلغاء', saveEnregistrer:'حفظ', deleteBtn:'حذف',
    errNetwork:'خطأ في الشبكة. تحقق من اتصالك.',
    toastProfileSaved:'تم تحديث الملف الشخصي!',
    inscriptionsTitle:'التسجيلات', inscriptionsSub:'الطلبات المستلمة من نموذج التسجيل',
    exportCSV:'تصدير CSV',
    etabAll:'الكل', etabNew:'طلبات جديدة', etabAccepted:'مقبولة', etabRefused:'مرفوضة',
    ethName:'الاسم', ethEmail:'البريد الإلكتروني', ethPhone:'الهاتف', ethDate:'التاريخ', ethStatus:'الحالة',
    statusNew:'طلب جديد', statusAccepted:'مقبول', statusRefused:'مرفوض',
    enrollSearchPlaceholder:'البحث بالاسم أو البريد أو الهاتف…',
    toastStatusUpdated:'تم تحديث الحالة.',
    toastEnrollDeleted:'تم حذف الطلب.',
    confirmDeleteEnroll:'حذف هذا الطلب نهائياً؟',
    greeting:'مرحباً،',
    schLoading:'جارٍ التحميل…',
    membersCloseBtn:'إغلاق', btnAddMember:'إضافة', addGroupCancel:'إلغاء', addGroupSubmit:'إنشاء',
    tabAll:'الكل', tabStudents:'الطلاب', tabTeachers:'الأساتذة',
    thName:'الاسم', thUser:'المعرّف', thEmail:'البريد الإلكتروني', thRole:'الدور', thActions:'إجراءات',
    modalAddUserTitle:'مستخدم جديد',
    lblAuFullname:'الاسم الكامل', lblAuUsername:'اسم المستخدم', lblAuEmail:'البريد الإلكتروني', lblAuRole:'الدور',
    lblAuPassword:'كلمة المرور الأولية', auPwPlaceholder:'8 أحرف على الأقل',
    auCancelBtn:'إلغاء', auSubmitBtn:'إنشاء المستخدم',
    modalResetTitle:'إعادة تعيين كلمة المرور',
    resetPwSub:'تعيين كلمة مرور جديدة لـ',
    lblNewPw:'كلمة المرور الجديدة', lblNewPw2:'تأكيد',
    newPwPlaceholder:'8 أحرف على الأقل', newPw2Placeholder:'أعد كلمة المرور',
    rpCancelBtn:'إلغاء', rpSubmitBtn:'حفظ',
    twoFaTitle:'المصادقة الثنائية (2FA)', twoFaSub:'إعداد Google Authenticator أو Authy',
    securityLabel:'الأمان',
    btnAddUser:'مستخدم جديد',
    usersSearchPlaceholder:'البحث بالاسم أو البريد…',
    usersPageTitle:'إدارة المستخدمين', usersPageSub:'أدر بريد الطلاب والأساتذة وكلمات مرورهم',
    classesPageSub2:'اختر نوع الفصل',
  },
  en: {
    adminChip:'Admin', roleLabel:'Administrator',
    logout:'Log out',
    navMain:'Main', navAccount:'Account',
    navHome:'Dashboard', navSettings:'Settings',
    navUsers:'Users', navInscriptions:'Enrollments',
    navClasses:'Classes', navAssigningClasses:'Class assignments', navSchedule:'Allocate dates & times',
    classesPageTitle:'Classes', classesPageSub:'Select a class type',
    assigningPageTitle:'Class assignments', assigningPageSub:'Overview of groups assigned to students and teachers',
    schedulePageTitle:'Allocate dates & times', schedulePageSub:'Set session days and times for each group',
    schNoGroups:'No groups found.', schAddSlot:'+ Add session', schSave:'Save', schSaving:'…',
    schSaved:'✔ Saved', schTeacher:'Teacher:', schStudents:'student(s)',
    schDayLabel:'Day', schTimeFromLabel:'From', schTimeToLabel:'To',
    schNoSlots:'No sessions configured.',
    schDays:[
      {fr:'Lundi',en:'Monday',ar:'الاثنين'},{fr:'Mardi',en:'Tuesday',ar:'الثلاثاء'},{fr:'Mercredi',en:'Wednesday',ar:'الأربعاء'},
      {fr:'Jeudi',en:'Thursday',ar:'الخميس'},{fr:'Vendredi',en:'Friday',ar:'الجمعة'},{fr:'Samedi',en:'Saturday',ar:'السبت'}
    ],
    topbar:{ home:'Admin Dashboard', users:'Users', inscriptions:'Enrollments', settings:'Settings', classes:'Classes', 'assigning-classes':'Class assignments', schedule:'Allocate dates & times' },
    assigningStudentsTitle:'Students', assigningTeachersTitle:'Teachers',
    ath_student:'Student', ath_group:'Group(s)', ath_teacher:'Teacher', ath_teacher_group:'Group(s)',
    classesGroupsOf:'Groups of', noGroups:'No groups. Click + to create one.',
    btnAddGroup:'Add group', modalAddGroupTitle:'New group', lblGroupLetter:'Group letter',
    membersCurrentLbl:'Current members', membersAddLbl:'Add a member',
    noMembers:'No members in this group.',
    toastGroupCreated:'Group created!', toastGroupDeleted:'Group deleted.', toastMemberAdded:'Member added!', toastMemberRemoved:'Member removed.',
    errGroupLetter:'Please enter a group letter.', errGroupExists:'This group already exists.',
    selectUser:'— Select a user —',
    confirmDeleteGroup:'Delete this group and all its members?',
    noAssignments:'No groups assigned.',
    welcomeSub:'Manage classes and groups from this dashboard.',
    statTeachersLbl:'Teachers', statStudentsLbl:'Students', statEnrollmentsLbl:'Enrollments', statGroupsLbl:'Active groups',
    recentActivityTitle:'Recent activity',
    settingsTitle:'Settings', profileTitle:'Admin profile',
    settingsRole:'Administrator · Upskill',
    lblFullname:'Full name', saveBtn:'Save',
    prefTitle:'Preferences', prefTxt:'Use the language selector to switch between French and English.',
    cancelBtn:'Cancel', saveEnregistrer:'Save', deleteBtn:'Delete',
    errNetwork:'Network error. Check your connection.',
    toastProfileSaved:'Profile updated!',
    inscriptionsTitle:'Enrollments', inscriptionsSub:'Requests received from the enrollment form',
    exportCSV:'Export CSV',
    etabAll:'All', etabNew:'New requests', etabAccepted:'Accepted', etabRefused:'Refused',
    ethName:'Name', ethEmail:'Email', ethPhone:'Phone', ethDate:'Date', ethStatus:'Status',
    statusNew:'New request', statusAccepted:'Accepted', statusRefused:'Refused',
    enrollSearchPlaceholder:'Search by name, email, phone…',
    toastStatusUpdated:'Status updated.',
    toastEnrollDeleted:'Request deleted.',
    confirmDeleteEnroll:'Permanently delete this request?',
    greeting:'Hello,',
    schLoading:'Loading…',
    membersCloseBtn:'Close', btnAddMember:'Add', addGroupCancel:'Cancel', addGroupSubmit:'Create',
    tabAll:'All', tabStudents:'Students', tabTeachers:'Teachers',
    thName:'Name', thUser:'Username', thEmail:'Email', thRole:'Role', thActions:'Actions',
    modalAddUserTitle:'New user',
    lblAuFullname:'Full name', lblAuUsername:'Username', lblAuEmail:'Email', lblAuRole:'Role',
    lblAuPassword:'Initial password', auPwPlaceholder:'Minimum 8 characters',
    auCancelBtn:'Cancel', auSubmitBtn:'Create user',
    modalResetTitle:'Reset password',
    resetPwSub:'Set a new password for',
    lblNewPw:'New password', lblNewPw2:'Confirm',
    newPwPlaceholder:'Minimum 8 characters', newPw2Placeholder:'Repeat password',
    rpCancelBtn:'Cancel', rpSubmitBtn:'Save',
    twoFaTitle:'Two-factor authentication (2FA)', twoFaSub:'Set up Google Authenticator or Authy',
    securityLabel:'Security',
    btnAddUser:'New user',
    usersSearchPlaceholder:'Search by name or email…',
    usersPageTitle:'User management', usersPageSub:'Manage student and teacher emails and passwords',
    classesPageSub2:'Select a class type',
  }
};
const tr = () => T[currentLang] || T.fr;

/* ══════════════════════════════════════════════════════
   LANG & NAVIGATION
══════════════════════════════════════════════════════ */
function setLang(lang) {
  // Admin panel supports fr and en; fall back to fr if unknown lang passed
  if (lang !== 'fr' && lang !== 'en') lang = 'fr';
  currentLang = lang;
  sessionStorage.setItem('upskill_admin_lang', lang);
  document.getElementById('body').className = '';
  document.documentElement.setAttribute('lang', lang);
  document.documentElement.setAttribute('dir', 'ltr');
  document.getElementById('pill-fr').className = 'lang-pill' + (lang==='fr'?' active':'');
  document.getElementById('pill-en').className = 'lang-pill' + (lang==='en'?' active':'');
  applyTranslations();
}

function applyTranslations() {
  const t = tr();
  const set = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
  set('admin-chip-lbl', t.adminChip);
  set('role-label', t.roleLabel);
  set('logout-lbl', t.logout);
  set('nav-main-label', t.navMain); set('nav-account-label', t.navAccount);
  set('nav-home-lbl', t.navHome); set('nav-settings-lbl', t.navSettings);
  set('nav-users-lbl', t.navUsers); set('nav-inscriptions-lbl', t.navInscriptions);
  set('nav-classes-lbl', t.navClasses); set('nav-assigning-classes-lbl', t.navAssigningClasses); set('nav-schedule-lbl', t.navSchedule);
  set('classes-page-title', t.classesPageTitle);
  set('schedule-page-title', t.schedulePageTitle); set('schedule-page-sub', t.schedulePageSub);
  set('assigning-page-title', t.assigningPageTitle); set('assigning-page-sub', t.assigningPageSub);
  set('assigning-students-title', t.assigningStudentsTitle); set('assigning-teachers-title', t.assigningTeachersTitle);
  set('ath-student', t.ath_student); set('ath-group', t.ath_group);
  set('ath-teacher', t.ath_teacher); set('ath-teacher-group', t.ath_teacher_group);
  set('btn-add-group-lbl', t.btnAddGroup);
  set('modal-add-group-title', t.modalAddGroupTitle); set('lbl-group-letter', t.lblGroupLetter);
  set('members-current-lbl', t.membersCurrentLbl); set('members-add-lbl', t.membersAddLbl);
  set('members-close-btn', t.membersCloseBtn); set('btn-add-member', t.btnAddMember);
  set('add-group-cancel', t.addGroupCancel); set('add-group-submit', t.addGroupSubmit);
  set('inscriptions-title', t.inscriptionsTitle); set('inscriptions-sub', t.inscriptionsSub);
  set('export-csv-lbl', t.exportCSV);
  set('etab-all-lbl', t.etabAll); set('etab-new-lbl', t.etabNew);
  set('etab-accepted-lbl', t.etabAccepted); set('etab-refused-lbl', t.etabRefused);
  set('eth-name', t.ethName); set('eth-email', t.ethEmail);
  set('eth-phone', t.ethPhone); set('eth-date', t.ethDate); set('eth-status', t.ethStatus);
  const sph = document.getElementById('enroll-search'); if (sph) sph.placeholder = t.enrollSearchPlaceholder;
  set('topbar-title', t.topbar[activePage] || t.topbar.home);
  set('welcome-sub', t.welcomeSub);
  set('stat-teachers-lbl', t.statTeachersLbl); set('stat-students-lbl', t.statStudentsLbl);
  set('stat-enrollments-lbl', t.statEnrollmentsLbl); set('stat-groups-lbl', t.statGroupsLbl);
  set('recent-activity-title', t.recentActivityTitle);
  set('settings-title', t.settingsTitle); set('profile-title', t.profileTitle);
  set('settings-role', t.settingsRole);
  set('lbl-fullname', t.lblFullname); set('save-btn', t.saveBtn);
  set('pref-title', t.prefTitle); set('pref-txt', t.prefTxt);
  // Greeting
  set('welcome-greeting', t.greeting);
  // Schedule loading placeholder
  set('sched-loading-lbl', t.schLoading);
  // Users page
  set('tab-all-users', t.tabAll); set('tab-students', t.tabStudents); set('tab-teachers', t.tabTeachers);
  set('th-name', t.thName); set('th-user', t.thUser); set('th-email', t.thEmail); set('th-role', t.thRole); set('th-actions', t.thActions);
  set('users-page-title', t.usersPageTitle); set('users-page-sub', t.usersPageSub);
  set('btn-add-user-lbl', t.btnAddUser);
  const usp = document.getElementById('users-search'); if (usp) usp.placeholder = t.usersSearchPlaceholder;
  // Add User modal
  set('modal-add-user-title', t.modalAddUserTitle);
  set('lbl-au-fullname', t.lblAuFullname); set('lbl-au-username', t.lblAuUsername);
  set('lbl-au-email', t.lblAuEmail); set('lbl-au-role', t.lblAuRole);
  set('lbl-au-password', t.lblAuPassword);
  const auPw = document.getElementById('au-password'); if (auPw) auPw.placeholder = t.auPwPlaceholder;
  set('au-cancel-btn', t.auCancelBtn); set('au-submit-btn', t.auSubmitBtn);
  set('au-role-student', currentLang==='en'?'Student':currentLang==='ar'?'طالب':'Étudiant');
  set('au-role-teacher', currentLang==='en'?'Teacher':currentLang==='ar'?'أستاذ':'Professeur');
  // Reset password modal
  set('modal-reset-title', t.modalResetTitle);
  // reset-pw-sub contains a <strong> child — update only the text node, not innerHTML
  const rpSubEl = document.getElementById('reset-pw-sub');
  if (rpSubEl) {
    const strongEl = rpSubEl.querySelector('strong');
    rpSubEl.childNodes.forEach(n => { if (n.nodeType === Node.TEXT_NODE) n.textContent = t.resetPwSub + ' '; });
    if (!rpSubEl.querySelector('strong') && strongEl) rpSubEl.appendChild(strongEl);
  }
  const npw = document.getElementById('new-pw'); if (npw) npw.placeholder = t.newPwPlaceholder;
  const npw2 = document.getElementById('new-pw2'); if (npw2) npw2.placeholder = t.newPw2Placeholder;
  set('lbl-new-pw', t.lblNewPw); set('lbl-new-pw2', t.lblNewPw2);
  set('rp-cancel-btn', t.rpCancelBtn); set('rp-submit-btn', t.rpSubmitBtn);
  // Settings page
  set('2fa-link-title', t.twoFaTitle); set('2fa-link-sub', t.twoFaSub);
  set('security-label', t.securityLabel);
  // Re-render dynamic tables to pick up new language
  if (allUsers.length > 0) renderUsersTable();
}

function navigate(page, el) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('page-' + page).classList.add('active');
  if (el) el.classList.add('active');
  activePage = page;
  sessionStorage.setItem('upskill_admin_page', page);
  document.getElementById('topbar-title').textContent = tr().topbar[page] || tr().topbar.home;
  if (page === 'users') loadUsers();
  if (page === 'inscriptions') loadEnrollments();
  if (page === 'classes') { classesView = 'types'; classesTypeKey = null; classesLevel = null; classesGroupId = null; renderClassesPage(); }
  if (page === 'assigning-classes') loadAssigningClasses();
  if (page === 'schedule') loadSchedulePage();
  if (window.innerWidth <= 768) toggleSidebar();
}

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebar-backdrop').classList.toggle('open');
}

/* ══════════════════════════════════════════════════════
   API CALLS
══════════════════════════════════════════════════════ */
const _csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function api(url, method='GET', body=null) {
  const opts = { method, headers:{
    'Content-Type':  'application/json',
    'X-CSRF-Token':  _csrfToken,
  }};
  if (body) opts.body = JSON.stringify(body);
  const res  = await fetch(url, opts);
  const json = await res.json();
  if (!json.ok) throw new Error(json.error?.[currentLang] || json.error || (currentLang==='en'?'Error':currentLang==='ar'?'خطأ':'Erreur'));
  return json;
}

/* ══════════════════════════════════════════════════════
   USERS PAGE
══════════════════════════════════════════════════════ */
let allUsers = [];
let currentUserFilter = 'all';

async function loadUsers() {
  try {
    const data = await api('api_students.php?action=all_users');
    allUsers = data.users || [];
    const badge = document.getElementById('nav-users-badge');
    if (badge) { badge.textContent = allUsers.length; badge.style.display = ''; }
    renderUsersTable();
  } catch(e) {
    const tbody = document.getElementById('users-tbody');
    const loadErrMsg = currentLang==='en'?'Loading error':currentLang==='ar'?'خطأ في التحميل':'Erreur de chargement';
    if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="padding:2rem;text-align:center;color:var(--muted);">${loadErrMsg}</td></tr>`;
  }
}

function filterUsers(role, el) {
  currentUserFilter = role;
  el.closest('.tabs').querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  renderUsersTable();
}

function renderUsersTable() {
  const tbody  = document.getElementById('users-tbody'); if (!tbody) return;
  const search = (document.getElementById('users-search')?.value || '').toLowerCase();
  const lang   = currentLang;

  let rows = allUsers.filter(u => {
    if (currentUserFilter !== 'all' && u.role !== currentUserFilter) return false;
    if (search) {
      return (u.full_name||'').toLowerCase().includes(search)
          || (u.username||'').toLowerCase().includes(search)
          || (u.email||'').toLowerCase().includes(search);
    }
    return true;
  });

  if (rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" style="padding:2rem;text-align:center;color:var(--muted);">'
      + (lang==='en' ? 'No users found' : lang==='ar' ? 'لا توجد نتائج' : 'Aucun utilisateur trouvé') + '</td></tr>';
    return;
  }

  const roleLabel = { student: lang==='en'?'Student':lang==='ar'?'طالب':'Étudiant', teacher: lang==='en'?'Teacher':lang==='ar'?'أستاذ':'Professeur', admin: lang==='ar'?'مشرف':'Admin' };
  const roleColor = { student:'var(--blue)', teacher:'var(--green)', admin:'var(--orange)' };

  tbody.innerHTML = rows.map(u => {
    const initials = ((u.full_name||u.username||'?').trim().split(/\s+/).map(w=>w[0]).join('').slice(0,2)).toUpperCase();
    const emailHtml = `<span class="email-cell" data-uid="${u.id}" data-email="${e(u.email||'')}"
      onclick="editEmailInline(this)"
      style="cursor:pointer;color:${u.email ? 'var(--white)' : 'var(--muted)'};border-bottom:1px dashed var(--border);padding-bottom:1px;"
      title="${lang==='en'?'Click to edit':lang==='ar'?'انقر للتعديل':'Cliquer pour modifier'}">
      ${u.email ? e(u.email) : (lang==='en'?'+ Add email':lang==='ar'?'+ إضافة بريد':'+ Ajouter email')}
    </span>`;
    return `<tr style="border-bottom:1px solid var(--border2);transition:background .15s;" onmouseenter="this.style.background='rgba(255,255,255,.03)'" onmouseleave="this.style.background=''">
      <td style="padding:.85rem 1.2rem;">
        <div style="display:flex;align-items:center;gap:.75rem;">
          <div style="width:34px;height:34px;border-radius:50%;background:rgba(91,156,246,.15);border:1px solid rgba(91,156,246,.3);display:flex;align-items:center;justify-content:center;font-family:var(--font);font-size:.72rem;font-weight:700;color:var(--blue);flex-shrink:0;">${initials}</div>
          <div style="font-family:var(--font);font-size:.88rem;font-weight:600;">${e(u.full_name||'—')}</div>
        </div>
      </td>
      <td style="padding:.85rem 1.2rem;color:var(--muted);font-size:.85rem;font-family:monospace;">${e(u.username)}</td>
      <td style="padding:.85rem 1.2rem;font-size:.85rem;">${emailHtml}</td>
      <td style="padding:.85rem 1.2rem;">
        <span style="font-size:.75rem;font-family:var(--font);font-weight:600;padding:.2rem .6rem;border-radius:100px;background:${roleColor[u.role]||'var(--muted)'}22;color:${roleColor[u.role]||'var(--muted)'};">${roleLabel[u.role]||u.role}</span>
      </td>
      <td style="padding:.85rem 1.2rem;">
        <button onclick="openResetPw(${u.id},'${e(u.full_name||u.username)}')"
          style="font-size:.78rem;font-family:var(--font);font-weight:600;padding:.3rem .7rem;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;transition:.2s;"
          onmouseenter="this.style.color='var(--white)';this.style.borderColor='rgba(255,255,255,.3)'"
          onmouseleave="this.style.color='var(--muted)';this.style.borderColor='var(--border)'"
          title="${lang==='en'?'Reset password':lang==='ar'?'إعادة تعيين كلمة المرور':'Réinitialiser le mot de passe'}">
          🔑 ${lang==='en'?'Password':lang==='ar'?'كلمة المرور':'Mot de passe'}
        </button>
      </td>
    </tr>`;
  }).join('');
}

function e(s) { // HTML escape helper
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function editEmailInline(span) {
  const uid   = span.dataset.uid;
  const email = span.dataset.email;
  const input = document.createElement('input');
  input.type  = 'email';
  input.value = email;
  input.maxLength = 180;
  input.style.cssText = 'background:rgba(255,255,255,.08);border:1px solid var(--green);border-radius:6px;padding:.2rem .5rem;color:var(--white);font-family:var(--font-body);font-size:.85rem;outline:none;width:200px;';
  span.replaceWith(input);
  input.focus();

  const save = async () => {
    const newEmail = input.value.trim();
    if (!newEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
      input.style.borderColor = 'var(--red)'; return;
    }
    try {
      await api('api_students.php?action=update_email', 'POST', { user_id: uid, email: newEmail });
      // Update local cache
      const u = allUsers.find(u => String(u.id) === String(uid));
      if (u) u.email = newEmail;
      showToast(currentLang==='en'?'Email updated ✓':currentLang==='ar'?'تم تحديث البريد ✓':'Email mis à jour ✓', 'success');
      renderUsersTable();
    } catch(err) {
      showToast(err.message || (currentLang==='en'?'Error':currentLang==='ar'?'خطأ':'Erreur'), 'error');
      renderUsersTable();
    }
  };

  input.addEventListener('keydown', ev => { if (ev.key === 'Enter') save(); if (ev.key === 'Escape') renderUsersTable(); });
  input.addEventListener('blur', save);
}

function openResetPw(uid, name) {
  document.getElementById('reset-pw-uid').value  = uid;
  document.getElementById('reset-pw-name').textContent = name;
  document.getElementById('new-pw').value  = '';
  document.getElementById('new-pw2').value = '';
  document.getElementById('reset-pw-error').style.display = 'none';
  openModal('reset-pw');
}

async function submitResetPassword() {
  const uid  = document.getElementById('reset-pw-uid').value;
  const pw1  = document.getElementById('new-pw').value;
  const pw2  = document.getElementById('new-pw2').value;
  const errEl= document.getElementById('reset-pw-error');

  const _rlang = currentLang;
  if (!pw1 || pw1.length < 8) { errEl.textContent = _rlang==='en'?'Password must be at least 8 characters.':_rlang==='ar'?'يجب أن تكون كلمة المرور 8 أحرف على الأقل.':'Le mot de passe doit contenir au moins 8 caractères.'; errEl.style.display=''; return; }
  if (pw1 !== pw2)             { errEl.textContent = _rlang==='en'?'Passwords do not match.':_rlang==='ar'?'كلمتا المرور غير متطابقتين.':'Les mots de passe ne correspondent pas.'; errEl.style.display=''; return; }

  try {
    document.getElementById('rp-submit-btn').textContent = '…';
    await api('api_students.php?action=reset_password', 'POST', { user_id: uid, password: pw1 });
    closeModal('reset-pw');
    showToast(currentLang==='en'?'Password updated ✓':currentLang==='ar'?'تم تحديث كلمة المرور ✓':'Mot de passe mis à jour ✓', 'success');
    addActivity((currentLang==='en'?'Password reset — ':currentLang==='ar'?'إعادة تعيين كلمة المرور — ':'Mot de passe réinitialisé — ') + document.getElementById('reset-pw-name').textContent);
  } catch(err) {
    errEl.textContent = err.message || (currentLang==='en'?'Server error':currentLang==='ar'?'خطأ في الخادم':'Erreur serveur'); errEl.style.display = '';
  } finally {
    document.getElementById('rp-submit-btn').textContent = tr().rpSubmitBtn;
  }
}

function openAddUserModal(prefill) {
  ['au-fullname','au-username','au-email','au-password'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('au-role').value = 'student';
  document.getElementById('au-error').style.display = 'none';
  document.getElementById('au-enrollment-id').value = '';
  if (prefill) {
    if (prefill.name)  document.getElementById('au-fullname').value = prefill.name;
    if (prefill.email) document.getElementById('au-email').value    = prefill.email;
    if (prefill.id)    document.getElementById('au-enrollment-id').value = prefill.id;
    // Auto-generate a username suggestion from the name
    if (prefill.name && !document.getElementById('au-username').value) {
      const suggested = prefill.name.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/\s+/g,'.').replace(/[^a-z0-9.]/g,'');
      document.getElementById('au-username').value = suggested;
    }
  }
  openModal('add-user');
}

async function submitAddUser() {
  const fullname = document.getElementById('au-fullname').value.trim();
  const username = document.getElementById('au-username').value.trim();
  const email    = document.getElementById('au-email').value.trim();
  const role     = document.getElementById('au-role').value;
  const password = document.getElementById('au-password').value;
  const errEl    = document.getElementById('au-error');

  const _lang = currentLang;
  if (!fullname || !username || !password) { errEl.textContent=_lang==='en'?'Name, username and password are required.':_lang==='ar'?'الاسم والمعرف وكلمة المرور مطلوبة.':'Nom, identifiant et mot de passe sont requis.'; errEl.style.display=''; return; }
  if (password.length < 8) { errEl.textContent=_lang==='en'?'Password must be at least 8 characters.':_lang==='ar'?'يجب أن تكون كلمة المرور 8 أحرف على الأقل.':'Le mot de passe doit contenir au moins 8 caractères.'; errEl.style.display=''; return; }
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { errEl.textContent=_lang==='en'?'Invalid email.':_lang==='ar'?'بريد إلكتروني غير صالح.':'Email invalide.'; errEl.style.display=''; return; }

  try {
    document.getElementById('au-submit-btn').textContent = '…';
    await api('api_students.php?action=create_user', 'POST', { full_name:fullname, username, email, role, password });
    // If opened from an enrollment row, mark it as accepted
    const enrollId = parseInt(document.getElementById('au-enrollment-id').value || '0');
    if (enrollId) {
      try { await api('api_students.php?action=update_enrollment_status', 'POST', { id: enrollId, status: 'accepted' }); } catch(e){}
    }
    closeModal('add-user');
    showToast(currentLang==='en'?'User created ✓':currentLang==='ar'?'تم إنشاء المستخدم ✓':'Utilisateur créé ✓', 'success');
    addActivity((currentLang==='en'?'New user created — ':currentLang==='ar'?'مستخدم جديد — ':'Nouvel utilisateur créé — ') + fullname);
    await loadUsers();
    if (enrollId) await loadEnrollments(); // refresh inscriptions tab too
  } catch(err) {
    errEl.textContent = err.message || (currentLang==='en'?'Server error':currentLang==='ar'?'خطأ في الخادم':'Erreur serveur'); errEl.style.display='';
  } finally {
    document.getElementById('au-submit-btn').textContent = tr().auSubmitBtn;
  }
}
function openModal(id)  { document.getElementById('modal-' + id).classList.add('open'); }
function closeModal(id) { document.getElementById('modal-' + id).classList.remove('open'); }

async function refreshData() {
}

function showToast(msg, type='default') {
  const toast = document.getElementById('toast');
  const dot   = document.getElementById('toast-dot');
  document.getElementById('toast-msg').textContent = msg;
  dot.className = 'toast-dot' + (type==='success'?' success':type==='error'?' error':'');
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 2800);
}

const activityLog = [];
function addActivity(msg) {
  const now  = new Date();
  const time = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
  activityLog.unshift({ msg, time });
  const list = document.getElementById('activity-list'); if (!list) return;
  const colors = ['orange','green','blue'];
  list.innerHTML = activityLog.slice(0,5).map((a,i) =>
    '<div class="activity-item">'
    + '<div class="activity-dot ' + colors[i%colors.length] + '"></div>'
    + '<div><div class="activity-text"><strong>' + a.msg + '</strong></div>'
    + '<div class="activity-time">' + a.time + '</div></div>'
    + '</div>'
  ).join('');
}

/* ══════════════════════════════════════════════════════
   INSCRIPTIONS (ENROLLMENTS)
══════════════════════════════════════════════════════ */
let enrollmentsData       = [];
let enrollmentsFilter     = 'all';
let enrollmentsSearch     = '';
let enrollDebounceTimer   = null;
let registeredStudents    = []; // from users table

function debounceEnrollSearch() {
  clearTimeout(enrollDebounceTimer);
  enrollDebounceTimer = setTimeout(() => {
    enrollmentsSearch = document.getElementById('enroll-search')?.value.trim() || '';
    loadEnrollments();
  }, 300);
}

function filterEnrollments(status, el) {
  document.querySelectorAll('#page-inscriptions .tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  enrollmentsFilter = status;
  loadEnrollments();
}

async function loadEnrollments() {
  const tbody = document.getElementById('enrollments-tbody'); if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="6" style="padding:2rem;text-align:center;color:var(--muted);"><div class="spinner" style="margin:0 auto;"></div></td></tr>';

  try {
    // All tabs (including accepted) now use the enrollments table
    const apiStatus = enrollmentsFilter === 'all' ? 'new_refused' : enrollmentsFilter;
    const params = new URLSearchParams({ action:'enrollments', status: apiStatus, search:enrollmentsSearch });
    const data   = await api('api_students.php?' + params);
    enrollmentsData    = data.rows || [];
    registeredStudents = [];
    // Update count badges
    const c = data.counts || {};
    ['new','refused','accepted'].forEach(s => {
      const el = document.getElementById('enroll-count-' + s);
      if (el) { el.textContent = c[s] || 0; el.style.display = (c[s]||0) > 0 ? 'inline' : 'none'; }
    });
    // "All" tab = new + refused
    const allEl = document.getElementById('enroll-count-all');
    if (allEl) { const tot = (c.new||0)+(c.refused||0); allEl.textContent = tot; allEl.style.display = tot>0?'inline':'none'; }
    // sidebar badge = new demands
    const badge = document.getElementById('nav-inscriptions-badge');
    if (badge) { badge.textContent = c.new||0; badge.style.display = (c.new||0)>0?'':'none'; }
    renderEnrollmentsTable();
  } catch(e) {
    const errLbl = currentLang==='en'?'Error':currentLang==='ar'?'خطأ':'Erreur';
    tbody.innerHTML = `<tr><td colspan="6" style="padding:1.5rem;color:var(--red);">${errLbl}: ${e.message||''}</td></tr>`;
  }
}

function renderEnrollmentsTable() {
  const tbody = document.getElementById('enrollments-tbody'); if (!tbody) return;
  const t = tr();

  // All tabs now use enrollmentsData (enrollment form submissions)
  if (!enrollmentsData.length) {
    const noReqMsg = currentLang==='en' ? 'No requests found.' : currentLang==='ar' ? 'لم يُعثر على أي طلب.' : 'Aucune demande trouvée.';
    tbody.innerHTML = `<tr><td colspan="6" style="padding:2.5rem;text-align:center;color:var(--muted);">${noReqMsg}</td></tr>`;
    return;
  }
  const statusConfig = {
    new:      { label: t.statusNew,      bg: 'rgba(245,197,66,.12)',  color: 'var(--yellow)', border: 'rgba(245,197,66,.35)' },
    accepted: { label: t.statusAccepted, bg: 'rgba(62,207,120,.10)',  color: 'var(--green)',  border: 'rgba(62,207,120,.3)'  },
    refused:  { label: t.statusRefused,  bg: 'rgba(232,93,117,.10)',  color: 'var(--red)',    border: 'rgba(232,93,117,.3)'  },
  };
  tbody.innerHTML = enrollmentsData.map(row => {
    const sc  = statusConfig[row.status] || statusConfig.new;
    const dt  = new Date(row.created_at).toLocaleDateString(currentLang==='ar'?'ar-MA':'fr-FR',{day:'2-digit',month:'short',year:'numeric'});
    const initials = row.name.trim().split(/\s+/).map(w=>w[0]).join('').slice(0,2).toUpperCase()||'?';
    return `<tr style="border-bottom:1px solid var(--border2);"
              onmouseenter="this.style.background='rgba(255,255,255,.02)'"
              onmouseleave="this.style.background=''">
      <td style="padding:.85rem 1.2rem;">
        <div style="display:flex;align-items:center;gap:.75rem;">
          <div style="width:34px;height:34px;border-radius:50%;background:rgba(167,139,250,.12);border:1.5px solid rgba(167,139,250,.3);display:flex;align-items:center;justify-content:center;font-family:var(--font);font-size:.72rem;font-weight:700;color:var(--purple);flex-shrink:0;">${initials}</div>
          <div>
            <div style="font-family:var(--font);font-size:.88rem;font-weight:600;">${escE(row.name)}</div>
            ${row.course?`<div style="font-size:.74rem;color:var(--muted2);">📚 ${escE(row.course)}</div>`:''}
          </div>
        </div>
      </td>
      <td style="padding:.85rem 1.2rem;font-size:.84rem;">
        ${row.email?`<a href="mailto:${escE(row.email)}" style="color:var(--blue);text-decoration:none;">${escE(row.email)}</a>`:'<span style="color:var(--muted2);">—</span>'}
      </td>
      <td style="padding:.85rem 1.2rem;font-size:.84rem;color:var(--muted);">${escE(row.phone||'—')}</td>
      <td style="padding:.85rem 1.2rem;font-size:.82rem;color:var(--muted2);white-space:nowrap;">${dt}</td>
      <td style="padding:.85rem 1.2rem;">
        ${row.status === 'accepted'
          ? `<span style="background:rgba(62,207,120,.10);color:var(--green);border:1px solid rgba(62,207,120,.3);font-size:.76rem;font-weight:700;padding:.2rem .65rem;border-radius:100px;font-family:var(--font);">✓ ${t.statusAccepted}</span>`
          : `<select onchange="updateEnrollmentStatus(${row.id}, this.value)"
              style="padding:.35rem .6rem;background:${sc.bg};border:1px solid ${sc.border};border-radius:8px;color:${sc.color};font-family:var(--font);font-size:.78rem;font-weight:600;cursor:pointer;outline:none;">
              <option value="new"     ${row.status==='new'    ?'selected':''} style="background:var(--navy-mid);color:var(--white);">${t.statusNew}</option>
              <option value="refused" ${row.status==='refused'?'selected':''} style="background:var(--navy-mid);color:var(--white);">${t.statusRefused}</option>
            </select>`
        }
      </td>
      <td style="padding:.85rem 1.2rem;">
        ${row.status === 'accepted'
          ? `<span style="color:var(--muted2);font-size:.8rem;">—</span>`
          : `<div style="display:flex;gap:.4rem;align-items:center;">
              <button onclick="acceptEnrollment(${row.id})"
                style="background:rgba(62,207,120,.1);border:1px solid rgba(62,207,120,.3);color:var(--green);border-radius:8px;padding:.3rem .7rem;font-size:.78rem;font-weight:600;cursor:pointer;transition:.2s;white-space:nowrap;"
                onmouseenter="this.style.background='rgba(62,207,120,.22)'"
                onmouseleave="this.style.background='rgba(62,207,120,.1)'">✓ ${currentLang==='en'?'Accept':currentLang==='ar'?'قبول':'Accepter'}</button>
              <button onclick="deleteEnrollment(${row.id})"
                style="background:transparent;border:1px solid rgba(232,93,117,.3);color:var(--red);border-radius:8px;padding:.3rem .6rem;font-size:.78rem;cursor:pointer;transition:.2s;"
                onmouseenter="this.style.background='rgba(232,93,117,.1)'"
                onmouseleave="this.style.background='transparent'">🗑</button>
            </div>`
        }
      </td>
    </tr>`;
  }).join('');
}

async function updateEnrollmentStatus(id, status) {
  try {
    await api('api_students.php?action=update_enrollment_status', 'POST', { id, status });
    showToast(tr().toastStatusUpdated, 'success');
    addActivity((currentLang==='en'?'Enrollment #':currentLang==='ar'?'تسجيل #':'Inscription #') + id + ' → ' + status);
    await loadEnrollments(); // reload to refresh counts
  } catch(e) {
    showToast(e.message || tr().errNetwork, 'error');
  }
}

async function acceptEnrollment(id) {
  try {
    await api('api_students.php?action=update_enrollment_status', 'POST', { id, status: 'accepted' });
    showToast(currentLang==='en'?'Request accepted ✓':currentLang==='ar'?'تم قبول الطلب ✓':'Demande acceptée ✓', 'success');
    addActivity((currentLang==='en'?'Enrollment #':currentLang==='ar'?'تسجيل #':'Inscription #') + id + (currentLang==='en'?' accepted':currentLang==='ar'?' مقبول':' acceptée'));
    // Switch to "Acceptées" tab and reload
    const tab = document.getElementById('enroll-tab-accepted');
    if (tab) filterEnrollments('accepted', tab);
    else await loadEnrollments();
  } catch(e) {
    showToast(e.message || tr().errNetwork, 'error');
  }
}

async function deleteEnrollment(id) {
  if (!confirm(tr().confirmDeleteEnroll)) return;
  try {
    await api('api_students.php?action=delete_enrollment', 'POST', { id });
    showToast(tr().toastEnrollDeleted, 'success');
    await loadEnrollments();
  } catch(e) {
    showToast(e.message || tr().errNetwork, 'error');
  }
}

function exportEnrollmentsCSV() {
  if (!enrollmentsData.length) return;
  const t = tr();
  const headers = [t.ethName, t.ethEmail, t.ethPhone, 'Cours', t.ethDate, t.ethStatus];
  const rows = enrollmentsData.map(r => [
    r.name, r.email||'', r.phone||'', r.course||'',
    new Date(r.created_at).toLocaleDateString('fr-FR'),
    r.status==='new'?t.statusNew:r.status==='accepted'?t.statusAccepted:t.statusRefused
  ]);
  const csv = [headers, ...rows].map(r => r.map(v => '"' + String(v).replace(/"/g,'""') + '"').join(',')).join('\n');
  const blob = new Blob(['\ufeff' + csv], {type:'text/csv;charset=utf-8;'});
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a'); a.href=url; a.download='inscriptions.csv'; a.click();
  URL.revokeObjectURL(url);
}

function escE(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* ══════════════════════════════════════════════════════
   CLASSES PAGE
══════════════════════════════════════════════════════ */
let classesView      = 'types'; // 'types' | 'levels' | 'groups'
let classesTypeKey   = null;
let classesTypeMeta  = null;
let classesLevel     = null;
let classesGroupId   = null;
let allClassTypes    = [];
let classesAllUsers  = []; // separate from allUsers (Users page)

// Fixed type definitions (mirrors PHP CLASS_TYPES)
const CLASS_TYPE_DEFS = [
  {key:'beginners',          label_fr:'Débutants',           label_en:'Beginners',          label_ar:'مبتدئون',         levels:3, icon:'🌱'},
  {key:'pre_intermediate',   label_fr:'Pré-intermédiaire',   label_en:'Pre-intermediate',   label_ar:'ما قبل المتوسط',  levels:3, icon:'📗'},
  {key:'intermediate',       label_fr:'Intermédiaire',       label_en:'Intermediate',       label_ar:'متوسط',            levels:3, icon:'📘'},
  {key:'upper_intermediate', label_fr:'Upper-intermédiaire', label_en:'Upper-intermediate', label_ar:'فوق المتوسط',     levels:3, icon:'📙'},
  {key:'advanced',           label_fr:'Avancé',              label_en:'Advanced',           label_ar:'متقدم',            levels:3, icon:'🔥'},
  {key:'baccalaureate',      label_fr:'Baccalauréat',        label_en:'Baccalaureate',      label_ar:'البكالوريا',       levels:0, icon:'🎓'},
  {key:'business',           label_fr:'Business',            label_en:'Business',           label_ar:'الأعمال',          levels:0, icon:'💼'},
  {key:'kids',               label_fr:'Kids',                label_en:'Kids',               label_ar:'أطفال',            levels:0, icon:'🧒'},
];

async function renderClassesPage() {
  const btnAdd = document.getElementById('btn-add-group');
  const bc     = document.getElementById('classes-breadcrumb');

  document.getElementById('classes-view-types' ).style.display = 'none';
  document.getElementById('classes-view-levels').style.display = 'none';
  document.getElementById('classes-view-groups').style.display = 'none';
  bc.style.display = 'none';
  if (btnAdd) btnAdd.style.display = 'none';

  if (classesView === 'types') {
    document.getElementById('classes-page-sub').textContent = tr().classesPageSub;
    document.getElementById('classes-view-types').style.display = '';
    await renderTypesGrid();

  } else if (classesView === 'levels') {
    const typeNameL = currentLang==='en' ? (classesTypeMeta.label_en||classesTypeMeta.label_fr) : currentLang==='ar' ? classesTypeMeta.label_ar : classesTypeMeta.label_fr;
    document.getElementById('classes-page-sub').textContent = typeNameL;
    bc.style.display = '';
    bc.innerHTML = `<span class="bc-link" onclick="classesGoTypes()">Classes</span>`
      + `<span class="bc-sep">›</span>`
      + `<span class="bc-cur">${typeNameL}</span>`;
    document.getElementById('classes-view-levels').style.display = '';
    renderLevelCards();

  } else if (classesView === 'groups') {
    const label = buildGroupLabel();
    document.getElementById('classes-page-sub').textContent = label;
    bc.style.display = '';
    let bcHtml = `<span class="bc-link" onclick="classesGoTypes()">Classes</span><span class="bc-sep">›</span>`;
    if (classesTypeMeta.levels > 0) {
      const typeNameG = currentLang==='en' ? (classesTypeMeta.label_en||classesTypeMeta.label_fr) : currentLang==='ar' ? classesTypeMeta.label_ar : classesTypeMeta.label_fr;
      bcHtml += `<span class="bc-link" onclick="classesGoLevels()">${typeNameG}</span><span class="bc-sep">›</span>`;
    }
    bcHtml += `<span class="bc-cur">${label}</span>`;
    bc.innerHTML = bcHtml;
    document.getElementById('classes-view-groups').style.display = '';
    if (btnAdd) btnAdd.style.display = '';
    await loadGroupChips();
  }
}

function buildGroupLabel() {
  const name = currentLang==='en' ? (classesTypeMeta.label_en||classesTypeMeta.label_fr) : currentLang==='ar' ? classesTypeMeta.label_ar : classesTypeMeta.label_fr;
  return classesLevel ? name + ' ' + classesLevel : name;
}

async function renderTypesGrid() {
  const grid = document.getElementById('classes-view-types');

  // Render immediately from local definitions so cards are always clickable
  const render = (counts) => {
    const niveauxWord = currentLang==='en' ? 'level(s)' : currentLang==='ar' ? 'مستوى' : 'niveaux';
    const groupsWord  = currentLang==='en' ? 'group(s)' : currentLang==='ar' ? 'مجموعة' : 'groupe(s)';
    grid.innerHTML = CLASS_TYPE_DEFS.map(def => {
      const name = currentLang === 'en' ? (def.label_en||def.label_fr) : currentLang === 'ar' ? def.label_ar : def.label_fr;
      let sub = def.levels > 0 ? def.levels + ' ' + niveauxWord : '';
      if (counts) {
        const t = counts.find(c => c.key === def.key);
        let tally = 0;
        if (t) {
          tally = def.levels > 0
            ? (t.level_groups || []).reduce((s, lg) => s + lg.group_count, 0)
            : (t.group_count || 0);
        }
        sub = def.levels > 0
          ? def.levels + ' ' + niveauxWord + ' · ' + tally + ' ' + groupsWord
          : tally + ' ' + groupsWord;
      }
      return `<div class="class-type-card" onclick="classesSelectType('${def.key}')">
        <div style="font-size:1.8rem;margin-bottom:.6rem;">${def.icon}</div>
        <div class="ct-name">${name}</div>
        <div class="ct-sub">${sub}</div>
      </div>`;
    }).join('');
  };

  render(null); // show cards immediately

  try {
    const data = await api('api_classes.php?action=list_types');
    allClassTypes = data.types || [];
    render(allClassTypes); // re-render with group counts
  } catch(e) {
    // cards stay visible without counts — API error doesn't block the UI
  }
}

function renderLevelCards() {
  const container = document.getElementById('classes-view-levels');
  const baseName  = currentLang === 'en' ? (classesTypeMeta.label_en||classesTypeMeta.label_fr) : currentLang === 'ar' ? classesTypeMeta.label_ar : classesTypeMeta.label_fr;
  const levels    = classesTypeMeta.levels || 0;
  // Build level list: use API data if available, otherwise fall back to count from def
  const apiType   = allClassTypes.find(t => t.key === classesTypeKey);
  const rows      = Array.from({length: levels}, (_, i) => {
    const lvl  = i + 1;
    const gc   = apiType ? ((apiType.level_groups || []).find(lg => lg.level === lvl)?.group_count ?? 0) : null;
    return { level: lvl, group_count: gc };
  });
  const groupsWord2 = currentLang==='en' ? 'group(s)' : currentLang==='ar' ? 'مجموعة' : 'groupe(s)';
  container.innerHTML = rows.map(lg => {
    const name = baseName + ' ' + lg.level;
    const sub  = lg.group_count !== null ? lg.group_count + ' ' + groupsWord2 : '';
    return `<div class="level-card" onclick="classesSelectLevel(${lg.level})">
      <div class="lc-title">${name}</div>
      <div class="lc-sub">${sub}</div>
    </div>`;
  }).join('');
}

async function loadGroupChips() {
  const container = document.getElementById('classes-group-cards');
  container.innerHTML = '<div class="loading-overlay" style="grid-column:1/-1;"><div class="spinner"></div></div>';

  let url = `api_classes.php?action=list_groups&type_key=${classesTypeKey}`;
  if (classesLevel) url += `&level=${classesLevel}`;
  let data;
  const loadErrMsg2 = currentLang==='en'?'Loading error':currentLang==='ar'?'خطأ في التحميل':'Erreur de chargement';
  try { data = await api(url); } catch(e) { container.innerHTML = `<p style="color:var(--red);font-size:.85rem;grid-column:1/-1;">${loadErrMsg2}</p>`; return; }

  const groups = data.groups || [];
  if (groups.length === 0) {
    container.innerHTML = `<p style="color:var(--muted);font-size:.85rem;grid-column:1/-1;">${tr().noGroups}</p>`;
    return;
  }
  const groupWord   = currentLang==='en' ? 'Group' : currentLang==='ar' ? 'مجموعة' : 'Groupe';
  const noStudents  = currentLang==='en' ? 'No students' : currentLang==='ar' ? 'لا طلاب' : 'Aucun élève';
  const deleteTitle = currentLang==='en' ? 'Delete' : currentLang==='ar' ? 'حذف' : 'Supprimer';
  container.innerHTML = groups.map(g => {
    const members  = g.members || [];
    const teachers = members.filter(m => m.role === 'teacher');
    const students = members.filter(m => m.role === 'student');
    const teacherHtml = teachers.length
      ? `<div class="gc-teacher">👨‍🏫 ${teachers.map(t => t.name).join(', ')}</div>`
      : '';
    const studentHtml = students.length
      ? `<div class="gc-students">${students.map(s => s.name).join(' · ')}</div>`
      : `<div class="gc-students gc-empty">${noStudents}</div>`;
    return `<div class="group-card" onclick="openManageGroupModal(${g.id}, '${g.group_letter}')">
      <button class="gc-del" title="${deleteTitle}" onclick="event.stopPropagation();deleteGroup(${g.id})">✕</button>
      <div class="gc-letter">${groupWord} ${g.group_letter}</div>
      ${teacherHtml}
      ${studentHtml}
    </div>`;
  }).join('');
}

function classesSelectType(key) {
  classesTypeKey  = key;
  classesTypeMeta = CLASS_TYPE_DEFS.find(d => d.key === key) || {};
  if (classesTypeMeta.levels > 0) {
    classesView  = 'levels';
    classesLevel = null;
  } else {
    classesView  = 'groups';
    classesLevel = null;
  }
  renderClassesPage();
}

function classesSelectLevel(level) {
  classesLevel = level;
  classesView  = 'groups';
  renderClassesPage();
}

function classesGoTypes() {
  classesView     = 'types';
  classesTypeKey  = null;
  classesTypeMeta = null;
  classesLevel    = null;
  classesGroupId  = null;
  renderClassesPage();
}

function classesGoLevels() {
  classesView    = 'levels';
  classesLevel   = null;
  classesGroupId = null;
  renderClassesPage();
}

// Add group modal
function openAddGroupModal() {
  document.getElementById('group-letter-input').value = '';
  const err = document.getElementById('add-group-error');
  if (err) { err.style.display='none'; err.textContent=''; }
  document.getElementById('modal-add-group').classList.add('open');
}

async function submitAddGroup() {
  const letter = document.getElementById('group-letter-input').value.trim().toUpperCase();
  const errEl  = document.getElementById('add-group-error');
  errEl.style.display='none';
  if (!letter) { errEl.textContent = tr().errGroupLetter; errEl.style.display=''; return; }
  const btn = document.getElementById('add-group-submit');
  btn.disabled = true;
  try {
    await api('api_classes.php', 'POST', {
      action: 'create_group',
      type_key: classesTypeKey,
      level: classesLevel,
      group_letter: letter,
    });
    closeModal('add-group');
    showToast(tr().toastGroupCreated, 'success');
    await loadGroupChips();
    // Refresh type list counts
    const data = await api('api_classes.php?action=list_types');
    allClassTypes = data.types || [];
  } catch(e) {
    errEl.textContent = e.message.includes('existe') ? tr().errGroupExists : e.message;
    errEl.style.display = '';
  } finally { btn.disabled = false; }
}

async function deleteGroup(groupId) {
  if (!confirm(tr().confirmDeleteGroup)) return;
  try {
    await api('api_classes.php', 'POST', {action:'delete_group', group_id:groupId});
    showToast(tr().toastGroupDeleted);
    await loadGroupChips();
  } catch(e) { showToast(e.message, 'error'); }
}

// Manage members modal
async function openManageGroupModal(groupId, groupLetter) {
  classesGroupId = groupId;
  const gWord = currentLang==='en' ? 'Group' : currentLang==='ar' ? 'مجموعة' : 'Groupe';
  const mWord = currentLang==='en' ? 'Members' : currentLang==='ar' ? 'الأعضاء' : 'Membres';
  document.getElementById('modal-members-title').textContent = gWord + ' ' + groupLetter + ' – ' + mWord;
  document.getElementById('member-add-error').style.display = 'none';
  document.getElementById('modal-group-members').classList.add('open');
  await Promise.all([loadMembersList(), loadUsersForSelect()]);
}

async function loadMembersList() {
  const list = document.getElementById('members-list');
  list.innerHTML = '<div class="loading-overlay"><div class="spinner"></div></div>';
  try {
    const data = await api(`api_classes.php?action=list_members&group_id=${classesGroupId}`);
    const members = data.members || [];
    if (members.length === 0) { list.innerHTML = `<p style="color:var(--muted);font-size:.85rem;padding:.5rem 0;">${tr().noMembers}</p>`; return; }
    list.innerHTML = members.map(m => {
      const init = (m.name||m.username||'?').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
      const isTeacher = m.role === 'teacher';
      const teacherLabel  = currentLang==='en'?'Teacher':currentLang==='ar'?'أستاذ':'Prof';
      const studentLabel  = currentLang==='en'?'Student':currentLang==='ar'?'طالب':'Étudiant';
      const removeLabel   = currentLang==='en'?'Remove':currentLang==='ar'?'إزالة':'Retirer';
      const roleBadge = isTeacher
        ? `<span style="font-size:.7rem;background:rgba(62,207,120,.12);color:var(--green);border:1px solid rgba(62,207,120,.3);padding:.1rem .45rem;border-radius:100px;font-family:var(--font);font-weight:700;">${teacherLabel}</span>`
        : `<span style="font-size:.7rem;background:rgba(91,156,246,.12);color:var(--blue);border:1px solid rgba(91,156,246,.3);padding:.1rem .45rem;border-radius:100px;font-family:var(--font);font-weight:700;">${studentLabel}</span>`;
      return `<div class="member-row">
        <div class="member-info">
          <div class="member-init${isTeacher?' teacher':''}">${init}</div>
          <div>
            <div style="font-family:var(--font);font-size:.87rem;font-weight:600;">${m.name||m.username}</div>
            <div style="font-size:.75rem;color:var(--muted);">${m.username}</div>
          </div>
          ${roleBadge}
        </div>
        <button class="btn-secondary btn-sm" onclick="removeMember(${m.id})">${removeLabel}</button>
      </div>`;
    }).join('');
  } catch(e) { list.innerHTML = `<p style="color:var(--red);font-size:.85rem;">${currentLang==='en'?'Error':currentLang==='ar'?'خطأ':'Erreur'}</p>`; }
}

async function loadUsersForSelect() {
  if (classesAllUsers.length === 0) {
    try { const d = await api('api_classes.php?action=list_all_users'); classesAllUsers = d.users || []; }
    catch(e) { return; }
  }
  const sel = document.getElementById('member-user-select');
  sel.innerHTML = `<option value="">${tr().selectUser}</option>`
    + classesAllUsers.map(u => {
        const role = u.role==='teacher' ? '👨‍🏫' : '🎓';
        return `<option value="${u.id}">${role} ${u.name||u.username} (${u.username})</option>`;
      }).join('');
}

async function submitAddMember() {
  const uid   = parseInt(document.getElementById('member-user-select').value);
  const errEl = document.getElementById('member-add-error');
  errEl.style.display = 'none';
  if (!uid) return;
  const btn = document.getElementById('btn-add-member'); btn.disabled = true;
  try {
    await api('api_classes.php', 'POST', {action:'add_member', group_id:classesGroupId, user_id:uid});
    classesAllUsers = [];
    showToast(tr().toastMemberAdded, 'success');
    await loadMembersList();
    await loadUsersForSelect();
    await loadGroupChips();
  } catch(e) { errEl.textContent = e.message; errEl.style.display = ''; }
  finally { btn.disabled = false; }
}

async function removeMember(userId) {
  try {
    await api('api_classes.php', 'POST', {action:'remove_member', group_id:classesGroupId, user_id:userId});
    classesAllUsers = [];
    showToast(tr().toastMemberRemoved);
    await loadMembersList();
    await loadUsersForSelect();
    await loadGroupChips();
  } catch(e) { showToast(e.message, 'error'); }
}

/* ══════════════════════════════════════════════════════
   ASSIGNING CLASSES PAGE
══════════════════════════════════════════════════════ */
async function loadAssigningClasses() {
  const sTbody = document.getElementById('assigning-students-tbody');
  const tTbody = document.getElementById('assigning-teachers-tbody');
  sTbody.innerHTML = '<tr><td colspan="2" style="padding:2rem;text-align:center;"><div class="spinner" style="margin:0 auto;"></div></td></tr>';
  tTbody.innerHTML = sTbody.innerHTML;

  let data;
  try { data = await api('api_classes.php?action=list_assignments'); }
  catch(e) { sTbody.innerHTML = `<tr><td colspan="2" style="padding:1.5rem;color:var(--red);text-align:center;">${e.message}</td></tr>`; tTbody.innerHTML = sTbody.innerHTML; return; }

  const noRow = `<tr><td colspan="2" style="padding:1.5rem;text-align:center;color:var(--muted);">${tr().noAssignments}</td></tr>`;

  const renderRows = (users, role) => users.length === 0 ? noRow : users.map(u => {
    const init = (u.name||u.username||'?').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
    const groupTags = u.groups.map(g =>
      `<span style="display:inline-block;margin:.15rem .2rem;padding:.15rem .55rem;background:rgba(62,207,120,.08);border:1px solid rgba(62,207,120,.2);border-radius:100px;font-size:.72rem;color:var(--green);font-family:var(--font);font-weight:600;">${currentLang==='ar'?g.label_ar:(g.label_fr||g.label_ar)}</span>`
    ).join('');
    return `<tr>
      <td style="padding:.85rem 1.2rem;">
        <div style="display:flex;align-items:center;gap:.6rem;">
          <div style="width:30px;height:30px;border-radius:50%;background:rgba(91,156,246,.15);border:1px solid rgba(91,156,246,.3);display:flex;align-items:center;justify-content:center;font-family:var(--font);font-weight:700;font-size:.7rem;color:var(--blue);flex-shrink:0;">${init}</div>
          <div>
            <div style="font-family:var(--font);font-size:.87rem;font-weight:600;">${u.name||u.username}</div>
            <div style="font-size:.75rem;color:var(--muted);">${u.username}</div>
          </div>
        </div>
      </td>
      <td style="padding:.85rem 1.2rem;">${groupTags}</td>
    </tr>`;
  }).join('');

  sTbody.innerHTML = renderRows(data.students||[], 'student');
  tTbody.innerHTML = renderRows(data.teachers||[], 'teacher');
}

function saveProfile() {
  const name = document.getElementById('pref-name').value.trim(); if (!name) return;
  const init = name.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
  ['sidebar-avatar','topbar-avatar','settings-avatar'].forEach(id => {
    const el = document.getElementById(id); if (el) el.textContent = init;
  });
  document.getElementById('sidebar-name').textContent  = name;
  document.getElementById('settings-name').textContent = name;
  showToast(tr().toastProfileSaved, 'success');
}

function logout() {
  sessionStorage.clear();
  window.location.href = 'logout.php';
}

/* ══════════════════════════════════════════════════════
   SCHEDULE PAGE
══════════════════════════════════════════════════════ */
let _schedCourses = []; // cached after first load

async function loadSchedulePage() {
  const grid = document.getElementById('schedule-cards-grid');
  if (!grid) return;
  grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--muted);font-size:.88rem;"><div class="spinner" style="margin:0 auto 1rem;"></div>${tr().schLoading}</div>`;
  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res  = await fetch('api_classes.php?action=list_all_groups', {
      headers: { 'X-CSRF-Token': csrf }
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || (currentLang==='en'?'Error':currentLang==='ar'?'خطأ':'Erreur'));
    // Parse schedule_json string → schedule array for each group
    _schedCourses = (data.groups || []).map(g => ({
      ...g,
      schedule: g.schedule_json ? (JSON.parse(g.schedule_json) || []) : []
    }));
    renderScheduleCards();
  } catch(e) {
    grid.innerHTML = `<div style="grid-column:1/-1;color:var(--red);padding:1rem;">${e.message}</div>`;
  }
}

function renderScheduleCards() {
  const grid = document.getElementById('schedule-cards-grid');
  if (!grid) return;
  const t    = tr();
  const lang = currentLang;

  if (_schedCourses.length === 0) {
    grid.innerHTML = `<div style="grid-column:1/-1;color:var(--muted);text-align:center;padding:2rem;">${t.schNoGroups}</div>`;
    return;
  }

  grid.innerHTML = _schedCourses.map(c => {
    const gid   = c.group_id;
    const name  = lang === 'en' ? (c.label_en || c.label_fr || c.label_ar) : lang === 'ar' ? (c.label_ar || c.label_fr) : (c.label_fr || c.label_ar);
    const slots = c.schedule || [];
    const slotHtml = slots.length === 0
      ? `<div style="color:var(--muted);font-size:.82rem;font-style:italic;padding:.4rem 0 .6rem;">${t.schNoSlots}</div>`
      : slots.map((s, i) => schedSlotRow(gid, i, s, t)).join('');

    return `
    <div class="card" style="display:flex;flex-direction:column;gap:0;" id="sched-card-${gid}">
      <!-- Card header -->
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:1rem;">
        <div>
          <div style="font-family:var(--font);font-weight:700;font-size:1rem;color:var(--white);margin-bottom:.2rem;">${escHtmlA(name)}</div>
          <div style="font-size:.78rem;color:var(--muted);">
            <span>${c.student_count || 0} ${t.schStudents}</span>
            ${c.teacher_name ? `<span style="margin-left:.5rem;">· ${t.schTeacher} ${escHtmlA(c.teacher_name)}</span>` : ''}
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem;flex-shrink:0;">
          <span id="sched-status-${gid}" style="display:none;font-size:.75rem;color:var(--green);font-family:var(--font);font-weight:600;"></span>
          <button onclick="saveSchedule(${gid})" id="sched-save-${gid}"
            style="padding:.45rem .9rem;background:var(--blue);border:none;border-radius:8px;color:white;font-family:var(--font);font-size:.78rem;font-weight:600;cursor:pointer;transition:opacity .15s;"
            onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">${t.schSave}</button>
        </div>
      </div>

      <!-- Column headers -->
      <div style="display:grid;grid-template-columns:1fr 90px 90px 28px;gap:.4rem;margin-bottom:.35rem;padding:0 .1rem;">
        <div style="font-family:var(--font);font-size:.65rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;">${t.schDayLabel}</div>
        <div style="font-family:var(--font);font-size:.65rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;">${t.schTimeFromLabel}</div>
        <div style="font-family:var(--font);font-size:.65rem;font-weight:600;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;">${t.schTimeToLabel}</div>
        <div></div>
      </div>

      <!-- Slots -->
      <div id="sched-slots-${gid}">${slotHtml}</div>

      <!-- Add session button -->
      <button onclick="addScheduleSlot(${gid})"
        style="margin-top:.6rem;display:flex;align-items:center;gap:.4rem;background:none;border:1px dashed var(--border);border-radius:8px;color:var(--muted);font-family:var(--font);font-size:.8rem;font-weight:500;padding:.45rem .75rem;cursor:pointer;transition:all .2s;width:100%;justify-content:center;"
        onmouseover="this.style.borderColor='rgba(91,156,246,.5)';this.style.color='var(--blue)'"
        onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">${t.schAddSlot}</button>
    </div>`;
  }).join('');
}

function schedSlotRow(courseId, idx, slot, t) {
  const days = (t.schDays || []).map(d => {
    const label = currentLang === 'en' ? (d.en || d.fr) : currentLang === 'ar' ? (d.ar || d.fr) : d.fr;
    return `<option value="${d.fr}" data-ar="${d.ar}" ${d.fr === slot.day_fr ? 'selected' : ''}>${label}</option>`;
  }).join('');
  const timeStyle = `width:100%;padding:.5rem .4rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--white);font-family:var(--font-body);font-size:.83rem;outline:none;transition:border-color .2s;color-scheme:dark;`;
  return `
  <div id="slot-row-${courseId}-${idx}" style="display:grid;grid-template-columns:1fr 90px 90px 28px;gap:.4rem;margin-bottom:.4rem;align-items:center;">
    <select id="slot-day-${courseId}-${idx}"
      style="padding:.5rem .65rem;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;color:var(--white);font-family:var(--font-body);font-size:.83rem;outline:none;"
      onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--border)'">
      <option value="">—</option>${days}
    </select>
    <input type="time" id="slot-time-${courseId}-${idx}" value="${escHtmlA(slot.time||'')}"
      style="${timeStyle}" title="${currentLang==='en'?'From':currentLang==='ar'?'من':'De'}"
      onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--border)'">
    <input type="time" id="slot-time-end-${courseId}-${idx}" value="${escHtmlA(slot.time_end||'')}"
      style="${timeStyle}" title="${currentLang==='en'?'To':currentLang==='ar'?'إلى':'À'}"
      onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='var(--border)'">
    <button onclick="removeScheduleSlot(${courseId},${idx})"
      style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:1rem;padding:.2rem;border-radius:6px;transition:color .15s,background .15s;text-align:center;"
      onmouseover="this.style.color='var(--red)';this.style.background='rgba(232,93,117,.1)'"
      onmouseout="this.style.color='var(--muted)';this.style.background='none'"
      title="${currentLang==='en'?'Delete':currentLang==='ar'?'حذف':'Supprimer'}">✕</button>
  </div>`;
}

function addScheduleSlot(courseId) {
  const c    = _schedCourses.find(x => x.group_id == courseId);
  if (!c) return;
  if (!c.schedule) c.schedule = [];
  const idx  = c.schedule.length;
  c.schedule.push({ day_fr:'', day_ar:'', time:'', time_end:'' });
  const container = document.getElementById(`sched-slots-${courseId}`);
  if (!container) return;
  // Remove the "no slots" placeholder if present
  if (container.querySelector('div[style*="font-style:italic"]')) container.innerHTML = '';
  const t = tr();
  container.insertAdjacentHTML('beforeend', schedSlotRow(courseId, idx, c.schedule[idx], t));
}

function removeScheduleSlot(courseId, idx) {
  const row = document.getElementById(`slot-row-${courseId}-${idx}`);
  if (row) row.remove();
  // Re-index remaining rows so saves work correctly
  const container = document.getElementById(`sched-slots-${courseId}`);
  if (!container) return;
  const rows = container.querySelectorAll('[id^="slot-row-"]');
  rows.forEach((r, newIdx) => {
    r.id = `slot-row-${courseId}-${newIdx}`;
    const sel     = r.querySelector('select'); if (sel) sel.id = `slot-day-${courseId}-${newIdx}`;
    const time    = r.querySelector(`[id^="slot-time-${courseId}-"]`); if (time) time.id = `slot-time-${courseId}-${newIdx}`;
    const timeEnd = r.querySelector(`[id^="slot-time-end-${courseId}-"]`); if (timeEnd) timeEnd.id = `slot-time-end-${courseId}-${newIdx}`;
    const btn     = r.querySelector('button'); if (btn) btn.setAttribute('onclick', `removeScheduleSlot(${courseId},${newIdx})`);
  });
  const t = tr();
  if (rows.length === 0) container.innerHTML = `<div style="color:var(--muted);font-size:.82rem;font-style:italic;padding:.4rem 0 .6rem;">${t.schNoSlots}</div>`;
}

async function saveSchedule(courseId) {
  const t       = tr();
  const btn     = document.getElementById(`sched-save-${courseId}`);
  const statusEl= document.getElementById(`sched-status-${courseId}`);
  const csrf    = document.querySelector('meta[name="csrf-token"]')?.content || '';

  // Collect slots from DOM
  const container = document.getElementById(`sched-slots-${courseId}`);
  const rows      = container ? container.querySelectorAll('[id^="slot-row-"]') : [];
  const schedule  = [];
  let dayNames    = t.schDays || [];
  rows.forEach((r, i) => {
    const dayFr   = (document.getElementById(`slot-day-${courseId}-${i}`)?.value || '').trim();
    const time    = (document.getElementById(`slot-time-${courseId}-${i}`)?.value || '').trim();
    const timeEnd = (document.getElementById(`slot-time-end-${courseId}-${i}`)?.value || '').trim();
    if (!dayFr) return;
    const match = dayNames.find(d => d.fr === dayFr);
    schedule.push({ day_fr: dayFr, day_ar: match?.ar || '', time, time_end: timeEnd });
  });

  if (btn) { btn.textContent = t.schSaving; btn.disabled = true; }
  statusEl.style.display = 'none';

  try {
    const res  = await fetch('api_classes.php?action=update_schedule', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ group_id: courseId, schedule })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || (currentLang==='en'?'Error':currentLang==='ar'?'خطأ':'Erreur'));

    // Warn if server rejected all slots (e.g. missing day or invalid time)
    if (data.saved === 0 && schedule.length > 0) {
      const noSaveMsg = currentLang==='en' ? 'No sessions saved — ensure each row has a day and time selected.' : currentLang==='ar' ? 'لم يتم حفظ أي جلسة — تأكد من تحديد يوم وساعة لكل صف.' : 'Aucune séance sauvegardée — vérifiez que chaque ligne a un jour et une heure sélectionnés.';
      throw new Error(noSaveMsg);
    }

    // Update cached schedule
    const c = _schedCourses.find(x => x.group_id == courseId);
    if (c) c.schedule = schedule;

    statusEl.textContent   = t.schSaved;
    statusEl.style.color   = 'var(--green)';
    statusEl.style.display = '';
    setTimeout(() => { statusEl.style.display = 'none'; }, 3000);
  } catch(e) {
    statusEl.textContent   = '❌ ' + e.message;
    statusEl.style.color   = 'var(--red)';
    statusEl.style.display = '';
  } finally {
    if (btn) { btn.textContent = t.schSave; btn.disabled = false; }
  }
}

function escHtmlA(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ══════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', async () => {
  const _sl = sessionStorage.getItem('upskill_admin_lang');
  const lang = (_sl === 'fr' || _sl === 'en') ? _sl : 'fr';

  // Init avatar initials from PHP name
  const name = document.getElementById('sidebar-name')?.textContent.trim() || 'AD';
  const init = name.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
  ['sidebar-avatar','topbar-avatar','settings-avatar'].forEach(id => {
    const el = document.getElementById(id); if (el) el.textContent = init;
  });

  // Preload enrollment counts, student count, group count for stat cards + sidebar badge
  try {
    const [enrollData, studentsData, usersData, groupsData] = await Promise.all([
      api('api_students.php?action=enrollments&status=all'),
      api('api_students.php?action=registered_students'),
      api('api_students.php?action=all_users'),
      api('api_classes.php?action=list_types')
    ]);

    // Sidebar inscription badge
    const badge = document.getElementById('nav-inscriptions-badge');
    const newCount = enrollData.counts?.new || 0;
    if (badge && newCount > 0) { badge.textContent = newCount; badge.style.display = ''; }

    // Accepted student badge on inscriptions page
    const acceptedBadge = document.getElementById('enroll-count-accepted');
    if (acceptedBadge) { acceptedBadge.textContent = studentsData.total || 0; acceptedBadge.style.display = 'inline'; }

    // Home stat cards
    const teachers = (usersData.users || []).filter(u => u.role === 'teacher').length;
    const students = (usersData.users || []).filter(u => u.role === 'student').length;
    const totalEnroll = (enrollData.counts?.new || 0) + (enrollData.counts?.accepted || 0) + (enrollData.counts?.rejected || 0);
    const totalGroups = (groupsData.types || []).reduce((s, t) => s + (parseInt(t.group_count) || 0), 0);

    const el = id => document.getElementById(id);
    if (el('stat-teachers'))    el('stat-teachers').textContent    = teachers;
    if (el('stat-students'))    el('stat-students').textContent    = students;
    if (el('stat-enrollments')) el('stat-enrollments').textContent = totalEnroll;
    if (el('stat-groups'))      el('stat-groups').textContent      = totalGroups;

    // Live activity feed: show recent enrollments
    const actList = document.getElementById('activity-list');
    const enrollments = enrollData.enrollments || [];
    if (actList && enrollments.length > 0) {
      const colors = ['green','blue','orange','purple','green'];
      actList.innerHTML = enrollments.slice(0, 5).map((e, i) => {
        const name = e.full_name || e.name || (currentLang==='en'?'Student':currentLang==='ar'?'طالب':'Étudiant');
        const course = e.course || e.program || '';
        const when = e.submitted_at ? new Date(e.submitted_at).toLocaleDateString('fr-FR') : '';
        return `<div class="activity-item">
          <div class="activity-dot ${colors[i%5]}"></div>
          <div><div class="activity-text"><strong>${name}</strong>${course ? ' — ' + course : ''}</div>
          ${when ? `<div class="activity-time">${when}</div>` : ''}</div>
        </div>`;
      }).join('');
    } else if (actList) {
      const noRecentMsg = currentLang==='en' ? 'No recent enrollments' : currentLang==='ar' ? 'لا توجد تسجيلات حديثة' : 'Aucune inscription récente';
      actList.innerHTML = `<div class="activity-item"><div class="activity-dot orange"></div><div><div class="activity-text" style="color:var(--muted)">${noRecentMsg}</div></div></div>`;
    }

  } catch(e) { console.warn('Admin stats load error:', e); }

  setLang(lang);

  // Restore last active page after refresh
  const validPages = ['home','users','inscriptions','classes','assigning-classes','schedule','settings'];
  const savedPage  = sessionStorage.getItem('upskill_admin_page');
  if (savedPage && validPages.includes(savedPage) && savedPage !== 'home') {
    const navEl = document.getElementById('nav-' + savedPage);
    navigate(savedPage, navEl);
  }
});
</script>
</body>
</html>
