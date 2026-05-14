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
<html lang="fr" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<title>Upskill – Tableau de bord Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

/* COURSE CARD (courses grid) */
.course-card { background:var(--navy-card); border:1px solid var(--border); border-radius:18px; padding:1.4rem; transition:border-color .2s,transform .15s; position:relative; display:flex; flex-direction:column; gap:.7rem; }
.course-card:hover { border-color:rgba(251,146,60,.3); transform:translateY(-2px); }
.course-card-header { display:flex; align-items:center; gap:.85rem; }
body.ar .course-card-header { flex-direction:row-reverse; }
.course-icon { width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
.ci1 { background:rgba(251,146,60,.12); }
.ci2 { background:var(--green-dim); }
.ci3 { background:rgba(245,197,66,.1); }
.ci4 { background:rgba(91,156,246,.1); }
.ci5 { background:rgba(167,139,250,.1); }
.course-group-name { font-family:var(--font); font-size:.95rem; font-weight:700; line-height:1.25; }
body.ar .course-group-name { font-family:var(--font-ar); }
.level-tag { display:inline-block; font-size:.68rem; font-weight:700; padding:.15rem .55rem; border-radius:100px; background:rgba(91,156,246,.12); color:var(--blue); border:1px solid rgba(91,156,246,.25); margin-top:.2rem; }
.course-meta-row { display:flex; flex-wrap:wrap; gap:.5rem 1rem; font-size:.78rem; color:var(--muted); }
body.ar .course-meta-row { flex-direction:row-reverse; }
.schedule-chips { display:flex; flex-wrap:wrap; gap:.4rem; }
.schedule-chip { font-size:.72rem; background:rgba(62,207,120,.08); border:1px solid rgba(62,207,120,.2); color:var(--green); padding:.2rem .6rem; border-radius:100px; font-family:var(--font); }
.course-card-actions { display:flex; gap:.4rem; padding-top:.4rem; border-top:1px solid var(--border2); }

/* ASSIGN PANEL */
.assign-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.9rem 0; border-bottom:1px solid var(--border2); }
body.ar .assign-row { flex-direction:row-reverse; }
.assign-row:last-child { border-bottom:none; }
.assign-info { flex:1; min-width:0; }
.assign-course-name { font-family:var(--font); font-size:.9rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
body.ar .assign-course-name { font-family:var(--font-ar); text-align:right; }
.assign-meta { font-size:.75rem; color:var(--muted2); margin-top:.15rem; display:flex; gap:.75rem; flex-wrap:wrap; }
body.ar .assign-meta { flex-direction:row-reverse; }

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
.group-chips { display:flex; flex-wrap:wrap; gap:.5rem; }
.group-chip { display:inline-flex; align-items:center; gap:.5rem; padding:.5rem .9rem; background:rgba(62,207,120,.07); border:1px solid rgba(62,207,120,.25); border-radius:100px; font-family:var(--font); font-size:.82rem; font-weight:600; color:var(--green); cursor:default; }
.group-chip .chip-del { cursor:pointer; color:var(--muted); font-size:.85rem; padding:.05rem; line-height:1; transition:color .15s; }
.group-chip .chip-del:hover { color:var(--red); }
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
<a href="#main-content" class="skip-link" style="position:absolute;top:-40px;left:0;background:var(--green);color:#0f1d2e;padding:.5rem 1rem;font-family:var(--font);font-weight:700;font-size:.85rem;z-index:9999;border-radius:0 0 8px 0;transition:top .2s;text-decoration:none;">Aller au contenu</a>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Menu principal">
  <div class="sidebar-logo">
    <svg width="26" height="26" viewBox="0 0 28 28" fill="none"><rect width="28" height="28" rx="8" fill="#fb923c"/><path d="M8 14l4 4 8-8" stroke="#0f1d2e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Up<em style="color:var(--orange)">skill</em></span>
    <div class="admin-chip" id="admin-chip-lbl">Admin</div>
  </div>
  <div class="lang-toggle">
    <div class="lang-pill active" id="pill-fr" onclick="setLang('fr')">🇫🇷 FR</div>
    <div class="lang-pill"        id="pill-ar" onclick="setLang('ar')">🇲🇦 AR</div>
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
    <div class="nav-item" onclick="navigate('courses',this)" id="nav-courses">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      <span id="nav-courses-lbl">Cours</span>
      <span class="nav-badge" aria-hidden="true">—</span>
    </div>
    <div class="nav-item" onclick="navigate('assign',this)" id="nav-assign">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span id="nav-assign-lbl">Assignation</span>
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
    <div class="nav-section-label" id="nav-account-label">Compte</div>
    <div class="nav-item" onclick="navigate('settings',this)" id="nav-settings">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span id="nav-settings-lbl">Paramètres</span>
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
        <h2>Bonjour, <span id="welcome-name"><?= $full_name ?: 'Admin' ?></span> 👋</h2>
        <p id="welcome-sub">Gérez les cours et assignez-les aux professeurs depuis ce tableau de bord.</p>
      </div>
      <div style="font-size:3rem;">🛠️</div>
    </div>
    <div class="grid-4" style="margin-bottom:1.5rem;">
      <div class="card"><div class="stat-icon orange"><span aria-hidden="true">📚</span></div><div class="stat-value" id="stat-courses">—</div><div class="stat-label" id="stat-courses-lbl">Cours au total</div></div>
      <div class="card"><div class="stat-icon green"><span aria-hidden="true">✅</span></div><div class="stat-value" id="stat-assigned">—</div><div class="stat-label" id="stat-assigned-lbl">Cours assignés</div></div>
      <div class="card"><div class="stat-icon blue"><span aria-hidden="true">👨‍🏫</span></div><div class="stat-value" id="stat-teachers">—</div><div class="stat-label" id="stat-teachers-lbl">Professeurs</div></div>
      <div class="card"><div class="stat-icon purple"><span aria-hidden="true">⚠️</span></div><div class="stat-value" id="stat-unassigned">—</div><div class="stat-label" id="stat-unassigned-lbl">Non assignés</div></div>
    </div>
    <div class="grid-2">
      <div class="card">
        <div class="card-title" id="recent-courses-title">Cours récents</div>
        <div id="recent-courses-list"><div class="loading-overlay"><div class="spinner"></div></div></div>
      </div>
      <div class="card">
        <div class="card-title" id="recent-activity-title">Activité récente</div>
        <div id="activity-list">
          <div class="activity-item"><div class="activity-dot orange"></div><div><div class="activity-text"><strong id="act1">Tableau de bord chargé</strong></div><div class="activity-time" id="act1-time">À l'instant</div></div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── COURSES PAGE ── -->
  <div class="page" id="page-courses">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="courses-page-title">Gestion des cours</h2>
        <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="courses-page-sub">Chargement…</p>
      </div>
      <button class="btn-primary" onclick="openCourseModal()" id="btn-add-course">
        + <span id="btn-add-course-lbl">Nouveau cours</span>
      </button>
    </div>
    <div class="grid-3" id="courses-grid">
      <div style="grid-column:1/-1;" class="loading-overlay"><div class="spinner"></div></div>
    </div>
  </div>

  <!-- ── ASSIGN PAGE ── -->
  <div class="page" id="page-assign">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
      <div>
        <h2 style="font-family:var(--font);font-size:1.4rem;font-weight:700;" id="assign-page-title">Assignation des cours</h2>
        <p style="color:var(--muted);font-size:.85rem;margin-top:.2rem;" id="assign-page-sub">Associez chaque cours à un professeur ou un étudiant</p>
      </div>
    </div>
    <!-- Tabs -->
    <div class="tabs" style="margin-bottom:1.5rem;">
      <div class="tab active" id="assign-tab-teachers" onclick="switchAssignTab('teachers',this)">👨‍🏫 Professeurs</div>
      <div class="tab"        id="assign-tab-students" onclick="switchAssignTab('students',this)">🎓 Étudiants</div>
    </div>

    <!-- TEACHERS PANEL -->
    <div id="assign-panel-teachers">
      <div class="grid-2">
        <div class="card">
          <div class="card-title" id="select-teacher-title">Sélectionner un professeur</div>
          <div id="teacher-list-assign">
            <div class="loading-overlay"><div class="spinner"></div></div>
          </div>
        </div>
        <div class="card">
          <div class="card-title" id="teacher-courses-title">Cours du professeur sélectionné</div>
          <div id="teacher-courses-panel">
            <div class="empty-state"><div class="empty-icon">👈</div><p id="select-teacher-hint">Sélectionnez un professeur pour voir et gérer ses cours.</p></div>
          </div>
        </div>
      </div>
    </div>

    <!-- STUDENTS PANEL -->
    <div id="assign-panel-students" style="display:none;">
      <div class="grid-2">
        <!-- Student list -->
        <div class="card">
          <div class="card-title">Sélectionner un étudiant</div>
          <div style="margin-bottom:.75rem;">
            <input type="text" id="student-search-assign" placeholder="Rechercher un étudiant…" oninput="filterStudentList()"
              style="width:100%;padding:.6rem .9rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.84rem;outline:none;">
          </div>
          <div id="student-list-assign">
            <div class="loading-overlay"><div class="spinner"></div></div>
          </div>
        </div>
        <!-- Courses for selected student -->
        <div class="card">
          <div class="card-title" id="student-courses-title">Cours de l'étudiant sélectionné</div>
          <div id="student-courses-panel">
            <div class="empty-state"><div class="empty-icon">👈</div><p>Sélectionnez un étudiant pour voir et gérer ses cours.</p></div>
          </div>
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
      <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-title" id="classes-groups-title">Groupes</div>
        <div id="classes-group-chips" class="group-chips">
          <div class="loading-overlay"><div class="spinner"></div></div>
        </div>
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
      <button class="modal-close" onclick="closeModal('add-user')" aria-label="Fermer">✕</button>
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
          <option value="student">Étudiant</option>
          <option value="teacher">Professeur</option>
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
      <button class="modal-close" onclick="closeModal('reset-pw')" aria-label="Fermer">✕</button>
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
<div class="modal-overlay" id="modal-course">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-course-title">Nouveau cours</h3>
      <button class="btn-close" onclick="closeModal('course')" aria-label="Fermer">✕</button>
    </div>
    <input type="hidden" id="course-edit-id">
    <div class="grid-2" style="gap:1rem;">
      <div class="form-group">
        <label>Nom du groupe (FR)</label>
        <input type="text" id="c-group-fr" placeholder="Ex: Groupe A – Débutant">
      </div>
      <div class="form-group">
        <label>اسم المجموعة (AR)</label>
        <input type="text" id="c-group-ar" placeholder="المجموعة أ – مبتدئ" dir="rtl">
      </div>
    </div>
    <div class="grid-2" style="gap:1rem;">
      <div class="form-group">
        <label>Matière (FR)</label>
        <input type="text" id="c-subject-fr" placeholder="Ex: Anglais Général">
      </div>
      <div class="form-group">
        <label>المادة (AR)</label>
        <input type="text" id="c-subject-ar" placeholder="الإنجليزية العامة" dir="rtl">
      </div>
    </div>
    <div class="grid-3" style="gap:1rem;">
      <div class="form-group">
        <label>Niveau</label>
        <select id="c-level">
          <option>A1</option><option>A1–A2</option><option>A2</option>
          <option>B1</option><option>B1–B2</option><option>B2</option>
          <option>B2–C1</option><option>C1</option><option>C2</option>
        </select>
      </div>
      <div class="form-group">
        <label>Icône</label>
        <select id="c-icon">
          <option value="📖">📖 Lecture</option>
          <option value="✍️">✍️ Écriture</option>
          <option value="🎙️">🎙️ Oral</option>
          <option value="🧠">🧠 Grammaire</option>
          <option value="💬">💬 Conversation</option>
          <option value="📚">📚 Général</option>
        </select>
      </div>
      <div class="form-group">
        <label>Nbre étudiants</label>
        <input type="number" id="c-students" value="15" min="0" max="200">
      </div>
    </div>
    <div class="form-group">
      <label>Séances / الحصص</label>
      <div id="schedule-rows"></div>
      <button class="btn-secondary btn-sm" style="margin-top:.5rem;" onclick="addScheduleRow()" type="button">+ Ajouter une séance</button>
    </div>
    <div id="course-modal-error" style="display:none;color:var(--red);font-size:.82rem;margin-top:.5rem;"></div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('course')" id="course-modal-cancel">Annuler</button>
      <button class="btn-primary" onclick="submitCourseModal()" id="course-modal-submit">Enregistrer</button>
    </div>
  </div>
</div>

<!-- ── MODAL: DELETE CONFIRM ── -->
<div class="modal-overlay" id="modal-delete">
  <div class="modal sm">
    <div class="modal-header">
      <h3 id="modal-delete-title">Supprimer ce cours ?</h3>
      <button class="btn-close" onclick="closeModal('delete')" aria-label="Fermer">✕</button>
    </div>
    <p style="color:var(--muted);font-size:.88rem;line-height:1.65;" id="modal-delete-body">
      Cette action est irréversible. Le cours et toutes ses assignations seront supprimés.
    </p>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('delete')" id="delete-cancel-btn">Annuler</button>
      <button class="btn-primary danger" onclick="confirmDelete()" id="delete-confirm-btn">Supprimer</button>
    </div>
  </div>
</div>

<!-- ── MODAL: ASSIGN COURSE TO TEACHER ── -->
<div class="modal-overlay" id="modal-assign-course">
  <div class="modal sm">
    <div class="modal-header">
      <h3 id="modal-assign-title">Assigner ce cours</h3>
      <button class="btn-close" onclick="closeModal('assign-course')" aria-label="Fermer">✕</button>
    </div>
    <div class="form-group">
      <label id="assign-teacher-label">Professeur</label>
      <select id="assign-teacher-select"></select>
    </div>
    <div id="assign-modal-error" style="display:none;color:var(--red);font-size:.82rem;margin-top:.4rem;"></div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('assign-course')" id="assign-modal-cancel">Annuler</button>
      <button class="btn-primary" onclick="submitAssignModal()" id="assign-modal-submit">Assigner</button>
    </div>
  </div>
</div>

<!-- ── MODAL: ADD GROUP ── -->
<div class="modal-overlay" id="modal-add-group">
  <div class="modal sm">
    <div class="modal-header">
      <h3 id="modal-add-group-title">Nouveau groupe</h3>
      <button class="btn-close" onclick="closeModal('add-group')" aria-label="Fermer">✕</button>
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
      <button class="btn-close" onclick="closeModal('group-members')" aria-label="Fermer">✕</button>
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
      <button class="btn-primary btn-sm" onclick="submitAddMember()" id="btn-add-member">Ajouter</button>
    </div>
    <div id="member-add-error" style="display:none;color:var(--red);font-size:.82rem;margin-top:.4rem;"></div>
    <div class="modal-footer" style="margin-top:1.25rem;">
      <button class="btn-secondary" onclick="closeModal('group-members')" id="members-close-btn">Fermer</button>
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
let allCourses    = [];
let allTeachers   = [];
let selectedTeacherId = null;
let pendingDeleteId   = null;
let pendingAssignCourseId = null;
let courseModalSaving = false;

/* ══════════════════════════════════════════════════════
   TRANSLATIONS
══════════════════════════════════════════════════════ */
const T = {
  fr: {
    adminChip:'Admin', roleLabel:'Administrateur',
    logout:'Déconnexion',
    navMain:'Principal', navAccount:'Compte',
    navHome:'Tableau de bord', navCourses:'Cours', navAssign:'Assignation', navSettings:'Paramètres',
    navUsers:'Utilisateurs', navInscriptions:'Inscriptions',
    navClasses:'Classes', navAssigningClasses:'Assignation des classes',
    classesPageTitle:'Classes', classesPageSub:'Sélectionnez un type de classe',
    assigningPageTitle:'Assignation des classes', assigningPageSub:'Aperçu des groupes assignés aux étudiants et professeurs',
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
    topbar:{ home:'Tableau de bord Admin', courses:'Gestion des cours', assign:'Assignation des cours', users:'Utilisateurs', inscriptions:'Inscriptions', settings:'Paramètres', classes:'Classes', 'assigning-classes':'Assignation des classes' },
    welcomeSub:'Gérez les cours et assignez-les aux professeurs depuis ce tableau de bord.',
    statCoursesLbl:'Cours au total', statAssignedLbl:'Cours assignés',
    statTeachersLbl:'Professeurs', statUnassignedLbl:'Non assignés',
    recentCoursesTitle:'Cours récents', recentActivityTitle:'Activité récente',
    coursesPageTitle:'Gestion des cours',
    coursesPageSub:(n) => n + ' cours enregistré' + (n>1?'s':''),
    btnAddCourse:'Nouveau cours',
    assignPageTitle:'Assignation des cours', assignPageSub:'Associez chaque cours à un professeur',
    selectTeacherTitle:'Sélectionner un professeur',
    teacherCoursesTitle:'Cours du professeur sélectionné',
    selectTeacherHint:'Sélectionnez un professeur pour voir et gérer ses cours.',
    settingsTitle:'Paramètres', profileTitle:'Profil administrateur',
    settingsRole:'Administrateur · Upskill',
    lblFullname:'Nom complet', saveBtn:'Enregistrer',
    prefTitle:'Préférences', prefTxt:'Utilisez le sélecteur de langue pour basculer entre le Français et l\'Arabe.',
    modalCourseAdd:'Nouveau cours', modalCourseEdit:'Modifier le cours',
    modalDeleteTitle:'Supprimer ce cours ?',
    modalDeleteBody:'Cette action est irréversible. Le cours et toutes ses assignations seront supprimés.',
    modalAssignTitle:'Assigner le cours',
    assignTeacherLabel:'Professeur',
    cancelBtn:'Annuler', saveEnregistrer:'Enregistrer', deleteBtn:'Supprimer', assignBtn:'Assigner', unassignBtn:'Retirer',
    errFillFields:'Veuillez remplir au moins le nom du groupe (FR ou AR).',
    errNetwork:'Erreur réseau. Vérifiez votre connexion.',
    toastCourseAdded:'Cours créé avec succès !',
    toastCourseUpdated:'Cours mis à jour !',
    toastCourseDeleted:'Cours supprimé.',
    toastAssigned:'Cours assigné avec succès !',
    toastUnassigned:'Assignation retirée.',
    toastProfileSaved:'Profil mis à jour !',
    loadingCourses:'Chargement des cours…',
    noCourses:'Aucun cours enregistré. Créez le premier cours !',
    noTeacherCourses:'Ce professeur n\'a aucun cours assigné.',
    studentsLabel:'étudiants', assignedTo:'Assigné à', unassigned:'Non assigné',
    btnEditCourse:'Modifier', btnDeleteCourse:'Supprimer', btnAssignCourse:'Assigner',
    inscriptionsTitle:'Inscriptions', inscriptionsSub:'Demandes reçues depuis le formulaire d\'inscription',
    exportCSV:'Exporter CSV',
    etabAll:'Toutes', etabNew:'Nouvelles demandes', etabAccepted:'Acceptées', etabRefused:'Refusées',
    ethName:'Nom', ethEmail:'Email', ethPhone:'Téléphone', ethDate:'Date', ethStatus:'Statut',
    statusNew:'Nouvelle demande', statusAccepted:'Acceptée', statusRefused:'Refusée',
    enrollSearchPlaceholder:'Rechercher par nom, email, téléphone…',
    toastStatusUpdated:'Statut mis à jour.',
    toastEnrollDeleted:'Demande supprimée.',
    confirmDeleteEnroll:'Supprimer cette demande définitivement ?',
  },
  ar: {
    adminChip:'مسؤول', roleLabel:'مسؤول النظام',
    logout:'تسجيل الخروج',
    navMain:'الرئيسية', navAccount:'الحساب',
    navHome:'لوحة التحكم', navCourses:'الدروس', navAssign:'التعيينات', navSettings:'الإعدادات',
    navUsers:'المستخدمون', navInscriptions:'التسجيلات',
    navClasses:'الفصول', navAssigningClasses:'تعيين الفصول',
    classesPageTitle:'الفصول', classesPageSub:'اختر نوع الفصل',
    assigningPageTitle:'تعيين الفصول', assigningPageSub:'نظرة عامة على المجموعات المعينة للطلاب والأساتذة',
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
    topbar:{ home:'لوحة تحكم المسؤول', courses:'إدارة الدروس', assign:'تعيين الدروس', users:'المستخدمون', inscriptions:'التسجيلات', settings:'الإعدادات', classes:'الفصول', 'assigning-classes':'تعيين الفصول' },
    welcomeSub:'أدر الدروس وعيّنها للأساتذة من لوحة التحكم هذه.',
    statCoursesLbl:'إجمالي الدروس', statAssignedLbl:'الدروس المعيّنة',
    statTeachersLbl:'الأساتذة', statUnassignedLbl:'غير معيّن',
    recentCoursesTitle:'الدروس الأخيرة', recentActivityTitle:'النشاط الأخير',
    coursesPageTitle:'إدارة الدروس',
    coursesPageSub:(n) => n + ' درس مسجّل',
    btnAddCourse:'درس جديد',
    assignPageTitle:'تعيين الدروس', assignPageSub:'ربط كل درس بأستاذ',
    selectTeacherTitle:'اختيار أستاذ',
    teacherCoursesTitle:'دروس الأستاذ المختار',
    selectTeacherHint:'اختر أستاذاً لعرض دروسه وإدارتها.',
    settingsTitle:'الإعدادات', profileTitle:'الملف الشخصي',
    settingsRole:'مسؤول النظام · Upskill',
    lblFullname:'الاسم الكامل', saveBtn:'حفظ',
    prefTitle:'التفضيلات', prefTxt:'استخدم محدد اللغة للتبديل بين الفرنسية والعربية.',
    modalCourseAdd:'درس جديد', modalCourseEdit:'تعديل الدرس',
    modalDeleteTitle:'حذف هذا الدرس؟',
    modalDeleteBody:'هذا الإجراء لا يمكن التراجع عنه. سيتم حذف الدرس وجميع تعييناته.',
    modalAssignTitle:'تعيين الدرس',
    assignTeacherLabel:'الأستاذ',
    cancelBtn:'إلغاء', saveEnregistrer:'حفظ', deleteBtn:'حذف', assignBtn:'تعيين', unassignBtn:'إلغاء التعيين',
    errFillFields:'يرجى ملء اسم المجموعة (فرنسي أو عربي) على الأقل.',
    errNetwork:'خطأ في الشبكة. تحقق من اتصالك.',
    toastCourseAdded:'تمت إضافة الدرس بنجاح!',
    toastCourseUpdated:'تم تحديث الدرس!',
    toastCourseDeleted:'تم حذف الدرس.',
    toastAssigned:'تم تعيين الدرس بنجاح!',
    toastUnassigned:'تم إلغاء التعيين.',
    toastProfileSaved:'تم تحديث الملف الشخصي!',
    loadingCourses:'جارِ تحميل الدروس…',
    noCourses:'لا توجد دروس بعد. أنشئ أول درس!',
    noTeacherCourses:'لا توجد دروس معيّنة لهذا الأستاذ.',
    studentsLabel:'طالب', assignedTo:'معيّن لـ', unassigned:'غير معيّن',
    btnEditCourse:'تعديل', btnDeleteCourse:'حذف', btnAssignCourse:'تعيين',
    inscriptionsTitle:'التسجيلات', inscriptionsSub:'الطلبات المستلمة من نموذج التسجيل',
    exportCSV:'تصدير CSV',
    etabAll:'الكل', etabNew:'طلبات جديدة', etabAccepted:'مقبولة', etabRefused:'مرفوضة',
    ethName:'الاسم', ethEmail:'البريد الإلكتروني', ethPhone:'الهاتف', ethDate:'التاريخ', ethStatus:'الحالة',
    statusNew:'طلب جديد', statusAccepted:'مقبول', statusRefused:'مرفوض',
    enrollSearchPlaceholder:'البحث بالاسم أو البريد أو الهاتف…',
    toastStatusUpdated:'تم تحديث الحالة.',
    toastEnrollDeleted:'تم حذف الطلب.',
    confirmDeleteEnroll:'حذف هذا الطلب نهائياً؟',
  }
};
const tr = () => T[currentLang];

/* ══════════════════════════════════════════════════════
   LANG & NAVIGATION
══════════════════════════════════════════════════════ */
function setLang(lang) {
  currentLang = lang;
  sessionStorage.setItem('upskill_admin_lang', lang);
  document.getElementById('body').className = lang === 'ar' ? 'ar' : '';
  document.documentElement.setAttribute('lang', lang);
  document.documentElement.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
  document.getElementById('pill-fr').className = 'lang-pill' + (lang==='fr'?' active':'');
  document.getElementById('pill-ar').className = 'lang-pill' + (lang==='ar'?' active':'');
  applyTranslations();
}

function applyTranslations() {
  const t = tr();
  const set = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
  set('admin-chip-lbl', t.adminChip);
  set('role-label', t.roleLabel);
  set('logout-lbl', t.logout);
  set('nav-main-label', t.navMain); set('nav-account-label', t.navAccount);
  set('nav-home-lbl', t.navHome); set('nav-courses-lbl', t.navCourses);
  set('nav-assign-lbl', t.navAssign); set('nav-settings-lbl', t.navSettings);
  set('nav-users-lbl', t.navUsers); set('nav-inscriptions-lbl', t.navInscriptions);
  set('nav-classes-lbl', t.navClasses); set('nav-assigning-classes-lbl', t.navAssigningClasses);
  set('classes-page-title', t.classesPageTitle);
  set('assigning-page-title', t.assigningPageTitle); set('assigning-page-sub', t.assigningPageSub);
  set('assigning-students-title', t.assigningStudentsTitle); set('assigning-teachers-title', t.assigningTeachersTitle);
  set('ath-student', t.ath_student); set('ath-group', t.ath_group);
  set('ath-teacher', t.ath_teacher); set('ath-teacher-group', t.ath_teacher_group);
  set('btn-add-group-lbl', t.btnAddGroup);
  set('modal-add-group-title', t.modalAddGroupTitle); set('lbl-group-letter', t.lblGroupLetter);
  set('members-current-lbl', t.membersCurrentLbl); set('members-add-lbl', t.membersAddLbl);
  set('inscriptions-title', t.inscriptionsTitle); set('inscriptions-sub', t.inscriptionsSub);
  set('export-csv-lbl', t.exportCSV);
  set('etab-all-lbl', t.etabAll); set('etab-new-lbl', t.etabNew);
  set('etab-accepted-lbl', t.etabAccepted); set('etab-refused-lbl', t.etabRefused);
  set('eth-name', t.ethName); set('eth-email', t.ethEmail);
  set('eth-phone', t.ethPhone); set('eth-date', t.ethDate); set('eth-status', t.ethStatus);
  const sph = document.getElementById('enroll-search'); if (sph) sph.placeholder = t.enrollSearchPlaceholder;
  set('topbar-title', t.topbar[activePage] || t.topbar.home);
  set('welcome-sub', t.welcomeSub);
  set('stat-courses-lbl', t.statCoursesLbl); set('stat-assigned-lbl', t.statAssignedLbl);
  set('stat-teachers-lbl', t.statTeachersLbl); set('stat-unassigned-lbl', t.statUnassignedLbl);
  set('recent-courses-title', t.recentCoursesTitle); set('recent-activity-title', t.recentActivityTitle);
  set('courses-page-title', t.coursesPageTitle);
  set('btn-add-course-lbl', t.btnAddCourse);
  set('assign-page-title', t.assignPageTitle); set('assign-page-sub', t.assignPageSub);
  set('select-teacher-title', t.selectTeacherTitle);
  set('teacher-courses-title', t.teacherCoursesTitle);
  set('select-teacher-hint', t.selectTeacherHint);
  set('settings-title', t.settingsTitle); set('profile-title', t.profileTitle);
  set('settings-role', t.settingsRole);
  set('lbl-fullname', t.lblFullname); set('save-btn', t.saveBtn);
  set('pref-title', t.prefTitle); set('pref-txt', t.prefTxt);
  set('modal-course-title', document.getElementById('course-edit-id')?.value ? t.modalCourseEdit : t.modalCourseAdd);
  set('modal-delete-title', t.modalDeleteTitle); set('modal-delete-body', t.modalDeleteBody);
  set('modal-assign-title', t.modalAssignTitle);
  set('assign-teacher-label', t.assignTeacherLabel);
  set('course-modal-cancel', t.cancelBtn); set('course-modal-submit', t.saveEnregistrer);
  set('delete-cancel-btn', t.cancelBtn); set('delete-confirm-btn', t.deleteBtn);
  set('assign-modal-cancel', t.cancelBtn); set('assign-modal-submit', t.assignBtn);
  renderCoursesGrid();
  renderAssignPage();
}

function navigate(page, el) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('page-' + page).classList.add('active');
  if (el) el.classList.add('active');
  activePage = page;
  document.getElementById('topbar-title').textContent = tr().topbar[page] || tr().topbar.home;
  if (page === 'users') loadUsers();
  if (page === 'inscriptions') loadEnrollments();
  if (page === 'assign') { renderAssignPage(); if (assignTab === 'students') renderStudentList(); }
  if (page === 'classes') { classesView = 'types'; classesTypeKey = null; classesLevel = null; classesGroupId = null; renderClassesPage(); }
  if (page === 'assigning-classes') loadAssigningClasses();
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
  if (!json.ok) throw new Error(json.error?.[currentLang] || 'Erreur');
  return json;
}

async function loadCourses() {
  const data = await api('courses_admin.php?action=list');
  allCourses  = data.courses || [];
}

async function loadTeachers() {
  const data  = await api('assign_courses.php?action=list_teachers');
  allTeachers = data.teachers || [];
}

/* ══════════════════════════════════════════════════════
   HOME STATS & RECENT
══════════════════════════════════════════════════════ */
function updateHomeStats() {
  const total      = allCourses.length;
  const assigned   = allCourses.filter(c => c.teacher_id).length;
  const unassigned = total - assigned;
  const teachers   = allTeachers.length;
  const set = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
  set('stat-courses', total);
  set('stat-assigned', assigned);
  set('stat-unassigned', unassigned);
  set('stat-teachers', teachers);
  const badge = document.getElementById('nav-courses-badge');
  if (badge) badge.textContent = total;

  // Recent courses list (last 4)
  const list  = document.getElementById('recent-courses-list');
  const slice = [...allCourses].slice(0, 4);
  if (!list) return;
  if (slice.length === 0) {
    list.innerHTML = '<div class="empty-state"><p>' + tr().noCourses + '</p></div>';
    return;
  }
  list.innerHTML = slice.map(c => {
    const name = currentLang==='ar' ? c.group_name_ar : c.group_name_fr;
    const sub  = currentLang==='ar' ? c.subject_ar    : c.subject_fr;
    const assigned = c.teacher_name
      ? '<span class="badge assigned">✓ ' + c.teacher_name + '</span>'
      : '<span class="badge unassigned">— ' + tr().unassigned + '</span>';
    return '<div class="assign-row">'
      + '<div class="assign-info">'
        + '<div class="assign-course-name">' + name + '</div>'
        + '<div class="assign-meta"><span>' + sub + '</span><span class="badge level">' + c.level + '</span></div>'
      + '</div>'
      + assigned
    + '</div>';
  }).join('');
}

/* ══════════════════════════════════════════════════════
   COURSES GRID RENDER
══════════════════════════════════════════════════════ */
const ICON_CLASS_MAP = {'📖':'ci1','✍️':'ci2','🎙️':'ci3','🧠':'ci4','💬':'ci5','📚':'ci1'};

function renderCoursesGrid() {
  const grid = document.getElementById('courses-grid'); if (!grid) return;
  const sub  = document.getElementById('courses-page-sub');
  if (sub) sub.textContent = tr().coursesPageSub(allCourses.length);

  if (allCourses.length === 0) {
    grid.innerHTML = '<div style="grid-column:1/-1;" class="empty-state"><div class="empty-icon">📭</div><p>' + tr().noCourses + '</p></div>';
    return;
  }

  grid.innerHTML = allCourses.map(c => {
    const name    = currentLang==='ar' ? c.group_name_ar : c.group_name_fr;
    const subject = currentLang==='ar' ? c.subject_ar    : c.subject_fr;
    const icon    = c.icon || '📚';
    const iconCls = ICON_CLASS_MAP[icon] || 'ci1';
    const chips   = (c.schedule||[]).map(s =>
      '<span class="schedule-chip">' + (currentLang==='ar'?s.day_ar:s.day_fr) + ' ' + s.time + '</span>'
    ).join('');
    const teacherBadge = c.teacher_name
      ? '<span class="badge assigned" style="font-size:.7rem;">👨‍🏫 ' + c.teacher_name + '</span>'
      : '<span class="badge unassigned" style="font-size:.7rem;">— ' + tr().unassigned + '</span>';

    return '<div class="course-card">'
      + '<div class="course-card-header">'
        + '<div class="course-icon ' + iconCls + '">' + icon + '</div>'
        + '<div><div class="course-group-name">' + name + '</div>'
        + '<span class="level-tag">' + c.level + '</span></div>'
      + '</div>'
      + '<div style="font-size:.82rem;color:var(--muted);">📚 ' + subject + '</div>'
      + '<div class="course-meta-row">'
        + '<span>👥 ' + c.students_count + ' ' + tr().studentsLabel + '</span>'
      + '</div>'
      + (chips ? '<div class="schedule-chips">' + chips + '</div>' : '')
      + '<div style="margin:.2rem 0;">' + teacherBadge + '</div>'
      + '<div class="course-card-actions">'
        + '<button class="btn-secondary btn-sm" style="flex:1;" onclick="openCourseModal(' + c.id + ')">✏️ ' + tr().btnEditCourse + '</button>'
        + '<button class="btn-secondary btn-sm" style="flex:1;color:var(--green);border-color:rgba(62,207,120,.3);" onclick="openAssignModal(' + c.id + ')">🔗 ' + tr().btnAssignCourse + '</button>'
        + '<button class="btn-secondary btn-sm" style="color:var(--red);border-color:rgba(232,93,117,.3);" onclick="promptDelete(' + c.id + ')">🗑️</button>'
      + '</div>'
    + '</div>';
  }).join('');
}

/* ══════════════════════════════════════════════════════
   ASSIGN PAGE
══════════════════════════════════════════════════════ */
function renderAssignPage() {
  renderTeacherList();
  if (selectedTeacherId) renderTeacherCourses(selectedTeacherId);
}

function renderTeacherList() {
  const list = document.getElementById('teacher-list-assign'); if (!list) return;
  if (allTeachers.length === 0) {
    list.innerHTML = '<div class="empty-state"><p>Aucun professeur trouvé.</p></div>';
    return;
  }
  list.innerHTML = allTeachers.map(t => {
    const active   = selectedTeacherId === t.id;
    const count    = allCourses.filter(c => c.teacher_id === t.id).length;
    const initials = t.full_name.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
    return '<div onclick="selectTeacher(' + t.id + ')" style="display:flex;align-items:center;gap:.85rem;padding:.85rem;border-radius:12px;cursor:pointer;transition:background .15s;background:' + (active?'rgba(251,146,60,.08)':'transparent') + ';border:1px solid ' + (active?'rgba(251,146,60,.3)':'transparent') + ';margin-bottom:.3rem;">'
      + '<div style="width:38px;height:38px;border-radius:50%;background:rgba(251,146,60,.15);border:2px solid rgba(251,146,60,.3);display:flex;align-items:center;justify-content:center;font-family:var(--font);font-weight:700;font-size:.8rem;color:var(--orange);flex-shrink:0;">' + initials + '</div>'
      + '<div style="flex:1;min-width:0;">'
        + '<div style="font-family:var(--font);font-size:.88rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + t.full_name + '</div>'
        + '<div style="font-size:.75rem;color:var(--muted2);">@' + t.username + ' · ' + count + ' cours</div>'
      + '</div>'
      + (active ? '<div style="width:8px;height:8px;border-radius:50%;background:var(--orange);flex-shrink:0;"></div>' : '')
    + '</div>';
  }).join('');
}

function selectTeacher(id) {
  selectedTeacherId = id;
  renderTeacherList();
  renderTeacherCourses(id);
}

function renderTeacherCourses(tid) {
  const panel = document.getElementById('teacher-courses-panel'); if (!panel) return;
  const assigned = allCourses.filter(c => c.teacher_id === tid);
  const teacher  = allTeachers.find(t => t.id === tid);
  if (assigned.length === 0) {
    panel.innerHTML = '<div class="empty-state"><div class="empty-icon">📭</div><p>' + tr().noTeacherCourses + '</p></div>';
    return;
  }
  panel.innerHTML = assigned.map(c => {
    const name = currentLang==='ar' ? c.group_name_ar : c.group_name_fr;
    const sub  = currentLang==='ar' ? c.subject_ar    : c.subject_fr;
    return '<div class="assign-row">'
      + '<div class="assign-info">'
        + '<div class="assign-course-name">' + name + '</div>'
        + '<div class="assign-meta"><span>' + sub + '</span><span class="badge level">' + c.level + '</span></div>'
      + '</div>'
      + '<button class="btn-secondary btn-sm" style="color:var(--red);border-color:rgba(232,93,117,.3);flex-shrink:0;" onclick="unassignCourse(' + c.id + ',' + tid + ')">' + tr().unassignBtn + '</button>'
    + '</div>';
  }).join('');
}

async function unassignCourse(courseId, teacherId) {
  try {
    await api('assign_courses.php?action=unassign', 'POST', { teacher_id:teacherId, course_id:courseId });
    await refreshData();
    renderTeacherCourses(teacherId);
    showToast(tr().toastUnassigned, 'success');
  } catch(e) {
    showToast(e.message || tr().errNetwork, 'error');
  }
}

/* ══════════════════════════════════════════════════════
   ASSIGN PAGE — TAB SWITCHER
══════════════════════════════════════════════════════ */
let assignTab = 'teachers';

function switchAssignTab(tab, el) {
  document.querySelectorAll('#page-assign .tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  assignTab = tab;
  document.getElementById('assign-panel-teachers').style.display = tab === 'teachers' ? '' : 'none';
  document.getElementById('assign-panel-students').style.display = tab === 'students'  ? '' : 'none';
  if (tab === 'students') renderStudentList();
}

/* ══════════════════════════════════════════════════════
   ASSIGN PAGE — STUDENTS
══════════════════════════════════════════════════════ */
let allStudents        = [];
let selectedStudentId  = null;
let studentCoursesList = []; // courses currently enrolled for selectedStudent

async function loadStudents() {
  try {
    const data = await api('assign_courses.php?action=list_students');
    allStudents = data.students || [];
  } catch(e) { allStudents = []; }
}

function filterStudentList() {
  renderStudentList();
}

function renderStudentList() {
  const list = document.getElementById('student-list-assign'); if (!list) return;
  const q = (document.getElementById('student-search-assign')?.value || '').toLowerCase();
  const filtered = q ? allStudents.filter(s =>
    (s.full_name||'').toLowerCase().includes(q) || (s.username||'').toLowerCase().includes(q)
  ) : allStudents;

  if (filtered.length === 0) {
    list.innerHTML = '<div class="empty-state"><p>Aucun étudiant trouvé.</p></div>';
    return;
  }
  list.innerHTML = filtered.map(s => {
    const active    = selectedStudentId === s.id;
    const initials  = (s.full_name||s.username).trim().split(/\s+/).map(w=>w[0]).join('').slice(0,2).toUpperCase()||'?';
    return '<div onclick="selectStudent(' + s.id + ')" style="display:flex;align-items:center;gap:.85rem;padding:.85rem;border-radius:12px;cursor:pointer;transition:background .15s;background:' + (active?'rgba(91,156,246,.08)':'transparent') + ';border:1px solid ' + (active?'rgba(91,156,246,.3)':'transparent') + ';margin-bottom:.3rem;">'
      + '<div style="width:38px;height:38px;border-radius:50%;background:rgba(91,156,246,.15);border:2px solid rgba(91,156,246,.3);display:flex;align-items:center;justify-content:center;font-family:var(--font);font-weight:700;font-size:.8rem;color:var(--blue);flex-shrink:0;">' + initials + '</div>'
      + '<div style="flex:1;min-width:0;">'
        + '<div style="font-family:var(--font);font-size:.88rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + (s.full_name||s.username) + '</div>'
        + '<div style="font-size:.75rem;color:var(--muted2);">@' + s.username + '</div>'
      + '</div>'
      + (active ? '<div style="width:8px;height:8px;border-radius:50%;background:var(--blue);flex-shrink:0;"></div>' : '')
    + '</div>';
  }).join('');
}

async function selectStudent(id) {
  selectedStudentId = id;
  renderStudentList();
  await renderStudentCourses(id);
}

async function renderStudentCourses(sid) {
  const panel = document.getElementById('student-courses-panel'); if (!panel) return;
  panel.innerHTML = '<div class="loading-overlay"><div class="spinner"></div></div>';
  try {
    const data = await api('assign_courses.php?action=student_courses&student_id=' + sid);
    studentCoursesList = data.courses || [];
    const enrolledIds  = studentCoursesList.map(c => c.id);
    const available    = allCourses.filter(c => !enrolledIds.includes(c.id));

    let html = '';

    // Enrolled courses
    if (studentCoursesList.length === 0) {
      html += '<div style="color:var(--muted);font-size:.85rem;padding:.5rem 0 1rem;">Aucun cours assigné.</div>';
    } else {
      html += studentCoursesList.map(c => {
        const name = currentLang==='ar' ? c.group_name_ar : c.group_name_fr;
        const sub  = currentLang==='ar' ? c.subject_ar    : c.subject_fr;
        return '<div class="assign-row">'
          + '<div class="assign-info">'
            + '<div class="assign-course-name">' + name + '</div>'
            + '<div class="assign-meta"><span>' + sub + '</span><span class="badge level">' + c.level + '</span></div>'
          + '</div>'
          + '<button class="btn-secondary btn-sm" style="color:var(--red);border-color:rgba(232,93,117,.3);flex-shrink:0;" onclick="unenrollStudent(' + sid + ',' + c.id + ')">Retirer</button>'
        + '</div>';
      }).join('');
    }

    // Enroll new course
    if (available.length > 0) {
      html += '<div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);display:flex;gap:.5rem;align-items:center;">'
        + '<select id="enroll-course-select-' + sid + '" style="flex:1;padding:.55rem .75rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;color:var(--white);font-family:var(--font-body);font-size:.84rem;outline:none;">'
        + available.map(c => '<option value="' + c.id + '">' + (currentLang==='ar'?c.group_name_ar:c.group_name_fr) + ' — ' + (currentLang==='ar'?c.subject_ar:c.subject_fr) + '</option>').join('')
        + '</select>'
        + '<button class="btn-primary btn-sm" onclick="enrollStudent(' + sid + ')">+ Inscrire</button>'
      + '</div>';
    } else {
      html += '<div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid var(--border);font-size:.82rem;color:var(--muted);">Tous les cours sont déjà assignés.</div>';
    }

    panel.innerHTML = html;
  } catch(e) {
    panel.innerHTML = '<div style="color:var(--red);font-size:.85rem;">Erreur: ' + (e.message||'') + '</div>';
  }
}

async function enrollStudent(sid) {
  const sel = document.getElementById('enroll-course-select-' + sid);
  if (!sel) return;
  const cid = parseInt(sel.value);
  if (!cid) return;
  try {
    await api('assign_courses.php?action=enroll_student', 'POST', { student_id:sid, course_id:cid });
    showToast('Étudiant inscrit ✓', 'success');
    addActivity('Étudiant #' + sid + ' inscrit au cours #' + cid);
    await renderStudentCourses(sid);
  } catch(e) {
    showToast(e.message || tr().errNetwork, 'error');
  }
}

async function unenrollStudent(sid, cid) {
  try {
    await api('assign_courses.php?action=unenroll_student', 'POST', { student_id:sid, course_id:cid });
    showToast('Inscription retirée.', 'success');
    addActivity('Étudiant #' + sid + ' retiré du cours #' + cid);
    await renderStudentCourses(sid);
  } catch(e) {
    showToast(e.message || tr().errNetwork, 'error');
  }
}

/* ══════════════════════════════════════════════════════
   COURSE MODAL (ADD / EDIT)
══════════════════════════════════════════════════════ */
function openCourseModal(id) {
  const c = id ? allCourses.find(x => x.id === id) : null;
  document.getElementById('course-edit-id').value  = c ? c.id : '';
  document.getElementById('modal-course-title').textContent = c ? tr().modalCourseEdit : tr().modalCourseAdd;
  document.getElementById('c-group-fr').value   = c ? c.group_name_fr : '';
  document.getElementById('c-group-ar').value   = c ? c.group_name_ar : '';
  document.getElementById('c-subject-fr').value = c ? c.subject_fr    : '';
  document.getElementById('c-subject-ar').value = c ? c.subject_ar    : '';
  document.getElementById('c-level').value      = c ? c.level         : 'A1';
  document.getElementById('c-icon').value       = c ? (c.icon||'📚')  : '📚';
  document.getElementById('c-students').value   = c ? c.students_count : 15;
  document.getElementById('course-modal-error').style.display = 'none';

  const schedRows = document.getElementById('schedule-rows');
  schedRows.innerHTML = '';
  const schedule = c ? (c.schedule || []) : [];
  if (schedule.length > 0) schedule.forEach(s => addScheduleRow(s));
  else addScheduleRow();

  document.getElementById('course-modal-submit').textContent = tr().saveEnregistrer;
  document.getElementById('course-modal-cancel').textContent = tr().cancelBtn;
  openModal('course');
}

function addScheduleRow(data) {
  const row = document.createElement('div');
  row.className = 'schedule-row';
  row.innerHTML =
    '<input type="text" placeholder="Ex: Lundi"   value="' + (data?.day_fr||'') + '" class="sched-day-fr" style="padding:.5rem .7rem;font-size:.82rem;">'
  + '<input type="text" placeholder="الاثنين"     value="' + (data?.day_ar||'') + '" class="sched-day-ar" style="padding:.5rem .7rem;font-size:.82rem;" dir="rtl">'
  + '<input type="text" placeholder="09:00–11:00" value="' + (data?.time||'')   + '" class="sched-time-v" style="padding:.5rem .7rem;font-size:.82rem;">'
  + '<input type="text" placeholder="Salle 12"    value="' + (data?.room||'')   + '" class="sched-room-v" style="padding:.5rem .7rem;font-size:.82rem;">'
  + '<button type="button" onclick="this.parentElement.remove()" style="background:rgba(232,93,117,.12);border:1px solid rgba(232,93,117,.3);color:var(--red);border-radius:8px;padding:.45rem .6rem;cursor:pointer;font-size:.85rem;white-space:nowrap;">✕</button>';
  document.getElementById('schedule-rows').appendChild(row);
}

function getScheduleFromForm() {
  return Array.from(document.querySelectorAll('#schedule-rows .schedule-row')).map(row => ({
    day_fr: row.querySelector('.sched-day-fr').value.trim(),
    day_ar: row.querySelector('.sched-day-ar').value.trim(),
    time:   row.querySelector('.sched-time-v').value.trim(),
    room:   row.querySelector('.sched-room-v').value.trim()
  })).filter(s => s.day_fr || s.day_ar || s.time);
}

async function submitCourseModal() {
  if (courseModalSaving) return;
  const errEl  = document.getElementById('course-modal-error');
  const grFr   = document.getElementById('c-group-fr').value.trim();
  const grAr   = document.getElementById('c-group-ar').value.trim();
  if (!grFr && !grAr) {
    errEl.textContent = tr().errFillFields;
    errEl.style.display = 'block'; return;
  }
  errEl.style.display = 'none';

  const editId = document.getElementById('course-edit-id').value;
  const payload = {
    group_name_fr:  grFr,
    group_name_ar:  grAr,
    subject_fr:     document.getElementById('c-subject-fr').value.trim(),
    subject_ar:     document.getElementById('c-subject-ar').value.trim(),
    level:          document.getElementById('c-level').value,
    icon:           document.getElementById('c-icon').value,
    students_count: parseInt(document.getElementById('c-students').value) || 0,
    schedule:       getScheduleFromForm()
  };
  if (editId) payload.id = parseInt(editId);

  const btn = document.getElementById('course-modal-submit');
  btn.innerHTML = '<span class="spinner"></span>';
  courseModalSaving = true;

  try {
    const action = editId ? 'update' : 'create';
    await api('courses_admin.php?action=' + action, 'POST', payload);
    await refreshData();
    closeModal('course');
    renderCoursesGrid();
    updateHomeStats();
    showToast(editId ? tr().toastCourseUpdated : tr().toastCourseAdded, 'success');
    addActivity(editId ? tr().toastCourseUpdated : tr().toastCourseAdded);
  } catch(e) {
    errEl.textContent = e.message || tr().errNetwork;
    errEl.style.display = 'block';
  } finally {
    courseModalSaving = false;
    btn.textContent = tr().saveEnregistrer;
  }
}

/* ══════════════════════════════════════════════════════
   DELETE
══════════════════════════════════════════════════════ */
function promptDelete(id) {
  pendingDeleteId = id;
  document.getElementById('modal-delete-title').textContent = tr().modalDeleteTitle;
  document.getElementById('modal-delete-body').textContent  = tr().modalDeleteBody;
  document.getElementById('delete-confirm-btn').textContent = tr().deleteBtn;
  document.getElementById('delete-cancel-btn').textContent  = tr().cancelBtn;
  openModal('delete');
}

async function confirmDelete() {
  if (!pendingDeleteId) return;
  const btn = document.getElementById('delete-confirm-btn');
  btn.innerHTML = '<span class="spinner"></span>';
  try {
    await api('courses_admin.php?action=delete', 'POST', { id: pendingDeleteId });
    pendingDeleteId = null;
    await refreshData();
    closeModal('delete');
    renderCoursesGrid();
    updateHomeStats();
    showToast(tr().toastCourseDeleted, 'success');
    addActivity(tr().toastCourseDeleted);
  } catch(e) {
    showToast(e.message || tr().errNetwork, 'error');
    closeModal('delete');
  } finally {
    btn.textContent = tr().deleteBtn;
  }
}

/* ══════════════════════════════════════════════════════
   ASSIGN MODAL
══════════════════════════════════════════════════════ */
function openAssignModal(courseId) {
  pendingAssignCourseId = courseId;
  const course  = allCourses.find(c => c.id === courseId);
  const titleEl = document.getElementById('modal-assign-title');
  if (titleEl && course) {
    const name = currentLang==='ar' ? course.group_name_ar : course.group_name_fr;
    titleEl.textContent = tr().modalAssignTitle + ' — ' + name;
  }
  const sel = document.getElementById('assign-teacher-select');
  sel.innerHTML = allTeachers.map(t =>
    '<option value="' + t.id + '"' + (course?.teacher_id===t.id?' selected':'') + '>' + t.full_name + ' (@' + t.username + ')</option>'
  ).join('');
  document.getElementById('assign-modal-error').style.display = 'none';
  document.getElementById('assign-modal-cancel').textContent  = tr().cancelBtn;
  document.getElementById('assign-modal-submit').textContent  = tr().assignBtn;
  document.getElementById('assign-teacher-label').textContent = tr().assignTeacherLabel;
  openModal('assign-course');
}

async function submitAssignModal() {
  const teacherId = parseInt(document.getElementById('assign-teacher-select').value);
  const errEl = document.getElementById('assign-modal-error');
  if (!teacherId || !pendingAssignCourseId) return;
  const btn = document.getElementById('assign-modal-submit');
  btn.innerHTML = '<span class="spinner"></span>';
  try {
    await api('assign_courses.php?action=assign', 'POST', { teacher_id:teacherId, course_id:pendingAssignCourseId });
    pendingAssignCourseId = null;
    await refreshData();
    closeModal('assign-course');
    renderCoursesGrid();
    updateHomeStats();
    if (selectedTeacherId === teacherId) renderTeacherCourses(teacherId);
    showToast(tr().toastAssigned, 'success');
    addActivity(tr().toastAssigned);
  } catch(e) {
    errEl.textContent = e.message || tr().errNetwork;
    errEl.style.display = 'block';
  } finally {
    btn.textContent = tr().assignBtn;
  }
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
    if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="padding:2rem;text-align:center;color:var(--muted);">Erreur de chargement</td></tr>';
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
      + (lang === 'ar' ? 'لا توجد نتائج' : 'Aucun utilisateur trouvé') + '</td></tr>';
    return;
  }

  const roleLabel = { student: lang==='ar'?'طالب':'Étudiant', teacher: lang==='ar'?'أستاذ':'Professeur', admin: lang==='ar'?'مشرف':'Admin' };
  const roleColor = { student:'var(--blue)', teacher:'var(--green)', admin:'var(--orange)' };

  tbody.innerHTML = rows.map(u => {
    const initials = ((u.full_name||u.username||'?').trim().split(/\s+/).map(w=>w[0]).join('').slice(0,2)).toUpperCase();
    const emailHtml = `<span class="email-cell" data-uid="${u.id}" data-email="${e(u.email||'')}"
      onclick="editEmailInline(this)"
      style="cursor:pointer;color:${u.email ? 'var(--white)' : 'var(--muted)'};border-bottom:1px dashed var(--border);padding-bottom:1px;"
      title="${lang==='ar'?'انقر للتعديل':'Cliquer pour modifier'}">
      ${u.email ? e(u.email) : (lang==='ar'?'+ إضافة بريد':'+ Ajouter email')}
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
          title="${lang==='ar'?'إعادة تعيين كلمة المرور':'Réinitialiser le mot de passe'}">
          🔑 ${lang==='ar'?'كلمة المرور':'Mot de passe'}
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
      showToast('Email mis à jour ✓', 'success');
      renderUsersTable();
    } catch(err) {
      showToast(err.message || 'Erreur', 'error');
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

  if (!pw1 || pw1.length < 8) { errEl.textContent = 'Le mot de passe doit contenir au moins 8 caractères.'; errEl.style.display=''; return; }
  if (pw1 !== pw2)             { errEl.textContent = 'Les mots de passe ne correspondent pas.'; errEl.style.display=''; return; }

  try {
    document.getElementById('rp-submit-btn').textContent = '…';
    await api('api_students.php?action=reset_password', 'POST', { user_id: uid, password: pw1 });
    closeModal('reset-pw');
    showToast('Mot de passe mis à jour ✓', 'success');
    addActivity('Mot de passe réinitialisé — ' + document.getElementById('reset-pw-name').textContent);
  } catch(err) {
    errEl.textContent = err.message || 'Erreur serveur'; errEl.style.display = '';
  } finally {
    document.getElementById('rp-submit-btn').textContent = 'Enregistrer';
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

  if (!fullname || !username || !password) { errEl.textContent='Nom, identifiant et mot de passe sont requis.'; errEl.style.display=''; return; }
  if (password.length < 8) { errEl.textContent='Le mot de passe doit contenir au moins 8 caractères.'; errEl.style.display=''; return; }
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { errEl.textContent='Email invalide.'; errEl.style.display=''; return; }

  try {
    document.getElementById('au-submit-btn').textContent = '…';
    await api('api_students.php?action=create_user', 'POST', { full_name:fullname, username, email, role, password });
    // If opened from an enrollment row, mark it as accepted
    const enrollId = parseInt(document.getElementById('au-enrollment-id').value || '0');
    if (enrollId) {
      try { await api('api_students.php?action=update_enrollment_status', 'POST', { id: enrollId, status: 'accepted' }); } catch(e){}
    }
    closeModal('add-user');
    showToast('Utilisateur créé ✓', 'success');
    addActivity('Nouvel utilisateur créé — ' + fullname);
    await loadUsers();
    if (enrollId) await loadEnrollments(); // refresh inscriptions tab too
  } catch(err) {
    errEl.textContent = err.message || 'Erreur serveur'; errEl.style.display='';
  } finally {
    document.getElementById('au-submit-btn').textContent = 'Créer l\'utilisateur';
  }
}
function openModal(id)  { document.getElementById('modal-' + id).classList.add('open'); }
function closeModal(id) { document.getElementById('modal-' + id).classList.remove('open'); }

async function refreshData() {
  await Promise.all([loadCourses(), loadTeachers(), loadStudents()]);
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
    tbody.innerHTML = '<tr><td colspan="6" style="padding:1.5rem;color:var(--red);">Erreur: ' + (e.message||'') + '</td></tr>';
  }
}

function renderEnrollmentsTable() {
  const tbody = document.getElementById('enrollments-tbody'); if (!tbody) return;
  const t = tr();

  // All tabs now use enrollmentsData (enrollment form submissions)
  if (!enrollmentsData.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="padding:2.5rem;text-align:center;color:var(--muted);">Aucune demande trouvée.</td></tr>';
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
                onmouseleave="this.style.background='rgba(62,207,120,.1)'">✓ Accepter</button>
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
    addActivity('Inscription #' + id + ' → ' + status);
    await loadEnrollments(); // reload to refresh counts
  } catch(e) {
    showToast(e.message || tr().errNetwork, 'error');
  }
}

async function acceptEnrollment(id) {
  try {
    await api('api_students.php?action=update_enrollment_status', 'POST', { id, status: 'accepted' });
    showToast('Demande acceptée ✓', 'success');
    addActivity('Inscription #' + id + ' acceptée');
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
let allUsers         = [];

// Fixed type definitions (mirrors PHP CLASS_TYPES)
const CLASS_TYPE_DEFS = [
  {key:'beginners',          label_fr:'Débutants',           label_ar:'مبتدئون',         levels:3, icon:'🌱'},
  {key:'pre_intermediate',   label_fr:'Pré-intermédiaire',   label_ar:'ما قبل المتوسط',  levels:3, icon:'📗'},
  {key:'intermediate',       label_fr:'Intermédiaire',       label_ar:'متوسط',            levels:3, icon:'📘'},
  {key:'upper_intermediate', label_fr:'Upper-intermédiaire', label_ar:'فوق المتوسط',     levels:3, icon:'📙'},
  {key:'advanced',           label_fr:'Avancé',              label_ar:'متقدم',            levels:3, icon:'🔥'},
  {key:'baccalaureate',      label_fr:'Baccalauréat',        label_ar:'البكالوريا',       levels:0, icon:'🎓'},
  {key:'business',           label_fr:'Business',            label_ar:'الأعمال',          levels:0, icon:'💼'},
  {key:'kids',               label_fr:'Kids',                label_ar:'أطفال',            levels:0, icon:'🧒'},
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
    document.getElementById('classes-page-sub').textContent = currentLang==='ar' ? classesTypeMeta.label_ar : classesTypeMeta.label_fr;
    bc.style.display = '';
    bc.innerHTML = `<span class="bc-link" onclick="classesGoTypes()">Classes</span>`
      + `<span class="bc-sep">›</span>`
      + `<span class="bc-cur">${currentLang==='ar' ? classesTypeMeta.label_ar : classesTypeMeta.label_fr}</span>`;
    document.getElementById('classes-view-levels').style.display = '';
    renderLevelCards();

  } else if (classesView === 'groups') {
    const label = buildGroupLabel();
    document.getElementById('classes-page-sub').textContent = label;
    bc.style.display = '';
    let bcHtml = `<span class="bc-link" onclick="classesGoTypes()">Classes</span><span class="bc-sep">›</span>`;
    if (classesTypeMeta.levels > 0) {
      bcHtml += `<span class="bc-link" onclick="classesGoLevels()">${currentLang==='ar' ? classesTypeMeta.label_ar : classesTypeMeta.label_fr}</span><span class="bc-sep">›</span>`;
    }
    bcHtml += `<span class="bc-cur">${label}</span>`;
    bc.innerHTML = bcHtml;
    document.getElementById('classes-view-groups').style.display = '';
    if (btnAdd) btnAdd.style.display = '';
    document.getElementById('classes-groups-title').textContent = tr().classesGroupsOf + ' ' + label;
    await loadGroupChips();
  }
}

function buildGroupLabel() {
  const name = currentLang==='ar' ? classesTypeMeta.label_ar : classesTypeMeta.label_fr;
  return classesLevel ? name + ' ' + classesLevel : name;
}

async function renderTypesGrid() {
  let data;
  try { data = await api('api_classes.php?action=list_types'); }
  catch(e) { return; }
  allClassTypes = data.types || [];

  const grid = document.getElementById('classes-view-types');
  grid.innerHTML = allClassTypes.map(t => {
    const def  = CLASS_TYPE_DEFS.find(d => d.key === t.key) || {};
    const name = currentLang==='ar' ? t.label_ar : t.label_fr;
    let groupTally = 0;
    if (t.levels > 0) {
      groupTally = (t.level_groups || []).reduce((s, lg) => s + lg.group_count, 0);
    } else {
      groupTally = t.group_count || 0;
    }
    const sub = t.levels > 0 ? t.levels + ' niveaux · ' + groupTally + ' groupe(s)' : groupTally + ' groupe(s)';
    return `<div class="class-type-card" onclick="classesSelectType(${JSON.stringify(t.key)})">
      <div style="font-size:1.8rem;margin-bottom:.6rem;">${def.icon||'📚'}</div>
      <div class="ct-name">${name}</div>
      <div class="ct-sub">${sub}</div>
    </div>`;
  }).join('');
}

function renderLevelCards() {
  const type = allClassTypes.find(t => t.key === classesTypeKey);
  if (!type) return;
  const container = document.getElementById('classes-view-levels');
  container.innerHTML = (type.level_groups || []).map(lg => {
    const name = (currentLang==='ar' ? classesTypeMeta.label_ar : classesTypeMeta.label_fr) + ' ' + lg.level;
    return `<div class="level-card" onclick="classesSelectLevel(${lg.level})">
      <div class="lc-title">${name}</div>
      <div class="lc-sub">${lg.group_count} groupe(s)</div>
    </div>`;
  }).join('');
}

async function loadGroupChips() {
  const chips = document.getElementById('classes-group-chips');
  chips.innerHTML = '<div class="loading-overlay"><div class="spinner"></div></div>';

  let url = `api_classes.php?action=list_groups&type_key=${classesTypeKey}`;
  if (classesLevel) url += `&level=${classesLevel}`;
  let data;
  try { data = await api(url); } catch(e) { chips.innerHTML = '<p style="color:var(--red);font-size:.85rem;">Erreur de chargement</p>'; return; }

  const groups = data.groups || [];
  if (groups.length === 0) {
    chips.innerHTML = `<p style="color:var(--muted);font-size:.85rem;">${tr().noGroups}</p>`;
    return;
  }
  chips.innerHTML = groups.map(g =>
    `<div class="group-chip" onclick="openManageGroupModal(${g.id}, ${JSON.stringify(g.group_letter)})">
      Groupe ${g.group_letter}
      <span style="color:var(--muted);font-size:.75rem;">(${g.member_count})</span>
      <span class="chip-del" title="Supprimer" onclick="event.stopPropagation();deleteGroup(${g.id})">✕</span>
    </div>`
  ).join('');
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
  document.getElementById('modal-members-title').textContent = 'Groupe ' + groupLetter + ' – Membres';
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
      const roleBadge = isTeacher
        ? '<span style="font-size:.7rem;background:rgba(62,207,120,.12);color:var(--green);border:1px solid rgba(62,207,120,.3);padding:.1rem .45rem;border-radius:100px;font-family:var(--font);font-weight:700;">Prof</span>'
        : '<span style="font-size:.7rem;background:rgba(91,156,246,.12);color:var(--blue);border:1px solid rgba(91,156,246,.3);padding:.1rem .45rem;border-radius:100px;font-family:var(--font);font-weight:700;">Étudiant</span>';
      return `<div class="member-row">
        <div class="member-info">
          <div class="member-init${isTeacher?' teacher':''}">${init}</div>
          <div>
            <div style="font-family:var(--font);font-size:.87rem;font-weight:600;">${m.name||m.username}</div>
            <div style="font-size:.75rem;color:var(--muted);">${m.username}</div>
          </div>
          ${roleBadge}
        </div>
        <button class="btn-secondary btn-sm" onclick="removeMember(${m.id})">Retirer</button>
      </div>`;
    }).join('');
  } catch(e) { list.innerHTML = '<p style="color:var(--red);font-size:.85rem;">Erreur</p>'; }
}

async function loadUsersForSelect() {
  if (allUsers.length === 0) {
    try { const d = await api('api_classes.php?action=list_all_users'); allUsers = d.users || []; }
    catch(e) { return; }
  }
  const sel = document.getElementById('member-user-select');
  sel.innerHTML = `<option value="">${tr().selectUser}</option>`
    + allUsers.map(u => {
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
    showToast(tr().toastMemberAdded, 'success');
    await loadMembersList();
    await loadGroupChips();
  } catch(e) { errEl.textContent = e.message; errEl.style.display = ''; }
  finally { btn.disabled = false; }
}

async function removeMember(userId) {
  try {
    await api('api_classes.php', 'POST', {action:'remove_member', group_id:classesGroupId, user_id:userId});
    showToast(tr().toastMemberRemoved);
    await loadMembersList();
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
      `<span style="display:inline-block;margin:.15rem .2rem;padding:.15rem .55rem;background:rgba(62,207,120,.08);border:1px solid rgba(62,207,120,.2);border-radius:100px;font-size:.72rem;color:var(--green);font-family:var(--font);font-weight:600;">${currentLang==='ar'?g.label_ar:g.label_fr}</span>`
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
   INIT
══════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', async () => {
  const lang = sessionStorage.getItem('upskill_admin_lang') || 'fr';

  // Init avatar initials from PHP name
  const name = document.getElementById('sidebar-name')?.textContent.trim() || 'AD';
  const init = name.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
  ['sidebar-avatar','topbar-avatar','settings-avatar'].forEach(id => {
    const el = document.getElementById(id); if (el) el.textContent = init;
  });

  try {
    await refreshData();
  } catch(e) {
    showToast(tr().errNetwork, 'error');
  }
  // Preload enrollment counts for sidebar badge
  try {
    const [enrollData, studentsData] = await Promise.all([
      api('api_students.php?action=enrollments&status=all'),
      api('api_students.php?action=registered_students')
    ]);
    const badge = document.getElementById('nav-inscriptions-badge');
    const newCount = enrollData.counts?.new || 0;
    if (badge && newCount > 0) { badge.textContent = newCount; badge.style.display = ''; }
    const acceptedBadge = document.getElementById('enroll-count-accepted');
    if (acceptedBadge) { acceptedBadge.textContent = studentsData.total || 0; acceptedBadge.style.display = 'inline'; }
  } catch(e) {}

  setLang(lang);
  updateHomeStats();
  addActivity(currentLang==='ar' ? 'تم تحميل لوحة التحكم' : 'Tableau de bord chargé');
});
</script>
</body>
</html>
