<?php
/**
 * manage_courses.php — Interface d'administration des cours
 * ─────────────────────────────────────────────────────────
 * Auth : session active avec role === 'admin'
 * Appelle assign_courses.php pour toutes les mutations DB.
 */
require_once __DIR__ . '/session.php';
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index2.php?error=auth');
    exit;
}
$admin_name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upskill – Gestion des cours</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --navy: #0f1d2e; --navy-mid: #162436; --navy-light: #1e3248;
  --navy-card: rgba(255,255,255,0.04);
  --green: #3ecf78; --green-dark: #28a85c;
  --green-glow: rgba(62,207,120,0.15); --green-dim: rgba(62,207,120,0.1);
  --white: #ffffff; --muted: rgba(255,255,255,0.55); --muted2: rgba(255,255,255,0.35);
  --border: rgba(255,255,255,0.1); --border2: rgba(255,255,255,0.07);
  --yellow: #f5c542; --red: #e85d75; --blue: #5b9cf6; --purple: #a78bfa;
  --font: 'Sora', sans-serif; --font-body: 'DM Sans', sans-serif;
}
html { scroll-behavior: smooth; }
body { background: var(--navy); color: var(--white); font-family: var(--font-body);
       min-height: 100vh; }
/* ── TOPBAR ── */
.topbar {
  background: rgba(15,29,46,.95); backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
  padding: 1rem 2rem; display: flex; align-items: center;
  justify-content: space-between; position: sticky; top: 0; z-index: 100;
  gap: 1rem; flex-wrap: wrap;
}
.topbar-left { display: flex; align-items: center; gap: 1rem; }
.back-btn {
  display: inline-flex; align-items: center; gap: .5rem;
  background: rgba(255,255,255,.06); border: 1px solid var(--border);
  color: var(--muted); font-family: var(--font); font-size: .8rem; font-weight: 500;
  padding: .45rem .9rem; border-radius: 9px; cursor: pointer; text-decoration: none;
  transition: all .2s;
}
.back-btn:hover { border-color: rgba(255,255,255,.2); color: var(--white); }
.page-title { font-family: var(--font); font-size: 1rem; font-weight: 600; }
.topbar-right { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
.lang-toggle { display: flex; gap: .4rem; }
.lang-pill {
  font-size: .7rem; font-family: var(--font); font-weight: 600;
  padding: .25rem .65rem; border-radius: 100px; border: 1px solid var(--border);
  color: var(--muted); cursor: pointer; transition: all .2s;
}
.lang-pill.active { background: var(--green-glow); border-color: rgba(62,207,120,.4); color: var(--green); }
.admin-chip {
  background: rgba(62,207,120,.1); border: 1px solid rgba(62,207,120,.25);
  color: var(--green); font-size: .7rem; font-family: var(--font); font-weight: 700;
  padding: .2rem .6rem; border-radius: 100px;
}

/* ── MAIN ── */
.main { padding: 2rem; }

/* ── STATS BAR ── */
.stats-bar { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
.stat-card {
  background: var(--navy-card); border: 1px solid var(--border); border-radius: 14px;
  padding: 1.1rem 1.3rem; display: flex; align-items: center; gap: .9rem;
}
.stat-icon { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
.stat-icon.purple { background: rgba(167,139,250,.12); }
.stat-icon.green  { background: var(--green-dim); }
.stat-icon.yellow { background: rgba(245,197,66,.1); }
.stat-icon.blue   { background: rgba(91,156,246,.1); }
.stat-val { font-family: var(--font); font-size: 1.7rem; font-weight: 700; letter-spacing: -.03em; line-height: 1; }
.stat-lbl { font-size: .78rem; color: var(--muted); margin-top: .15rem; }

/* ── TOOLBAR ── */
.toolbar {
  display: flex; align-items: center; justify-content: space-between;
  gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;
}
.toolbar-left { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
.search-wrap { position: relative; }
.search-wrap svg { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; color: var(--muted); pointer-events: none; }
.search-input {
  background: rgba(255,255,255,.05); border: 1px solid var(--border);
  border-radius: 10px; color: var(--white); font-family: var(--font-body);
  font-size: .85rem; height: 38px; padding: 0 1rem 0 2.3rem; outline: none;
  transition: border-color .2s; width: 240px;
}
.search-input:focus { border-color: rgba(167,139,250,.4); }
.search-input::placeholder { color: var(--muted2); }
.filter-select {
  background: rgba(255,255,255,.05); border: 1px solid var(--border);
  border-radius: 10px; color: var(--white); font-family: var(--font-body);
  font-size: .82rem; height: 38px; padding: 0 .9rem; outline: none; cursor: pointer;
}
.filter-select option { background: var(--navy-mid); }
.toolbar-right { display: flex; gap: .6rem; flex-wrap: wrap; }

/* ── BUTTONS ── */
.btn {
  display: inline-flex; align-items: center; gap: .45rem;
  padding: .55rem 1.1rem; border-radius: 10px; font-family: var(--font);
  font-size: .82rem; font-weight: 600; cursor: pointer; border: 1px solid;
  transition: all .2s; height: 38px;
}
.btn-secondary { background: rgba(255,255,255,.06); border-color: var(--border); color: var(--muted); }
.btn-secondary:hover { border-color: rgba(255,255,255,.2); color: var(--white); }
.btn-primary { background: var(--purple); border-color: var(--purple); color: var(--white); }
.btn-primary:hover { background: #9061f9; }
.btn-assign { background: rgba(62,207,120,.1); border-color: rgba(62,207,120,.3); color: var(--green); }
.btn-assign:hover { background: rgba(62,207,120,.18); }
.btn-danger { background: rgba(232,93,117,.1); border-color: rgba(232,93,117,.3); color: var(--red); }
.btn-danger:hover { background: rgba(232,93,117,.18); }
.btn-sm { height: 30px; padding: .3rem .75rem; font-size: .75rem; border-radius: 8px; }

/* ── TABLE ── */
.table-card {
  background: var(--navy-card); border: 1px solid var(--border); border-radius: 16px; overflow: hidden;
}
.table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
table { width: 100%; border-collapse: collapse; min-width: 820px; }
thead th {
  padding: .7rem 1.2rem; text-align: left;
  font-family: var(--font); font-size: .68rem; font-weight: 700;
  letter-spacing: .07em; text-transform: uppercase; color: var(--muted2);
  background: rgba(255,255,255,.02); border-bottom: 1px solid var(--border);
}
tbody td {
  padding: .9rem 1.2rem; font-size: .86rem; border-bottom: 1px solid var(--border2);
  vertical-align: middle;
}
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: rgba(167,139,250,.03); }

/* ── LEVEL BADGES ── */
.lvl-badge {
  display: inline-flex; align-items: center; padding: .15rem .6rem;
  border-radius: 100px; font-size: .7rem; font-weight: 700;
  font-family: var(--font); border: 1px solid;
}
.lvl-a { background: rgba(91,156,246,.1); color: var(--blue); border-color: rgba(91,156,246,.3); }
.lvl-b { background: rgba(62,207,120,.1); color: var(--green); border-color: rgba(62,207,120,.3); }
.lvl-c { background: rgba(245,197,66,.1); color: var(--yellow); border-color: rgba(245,197,66,.3); }

/* ── TEACHER PILL ── */
.teacher-pill { display: inline-flex; align-items: center; gap: .45rem; }
.t-avatar {
  width: 24px; height: 24px; border-radius: 50%;
  background: rgba(167,139,250,.15); border: 1.5px solid rgba(167,139,250,.35);
  display: inline-flex; align-items: center; justify-content: center;
  font-family: var(--font); font-size: .65rem; font-weight: 700; color: var(--purple); flex-shrink: 0;
}
.unassigned-badge {
  display: inline-flex; align-items: center; gap: .3rem;
  background: rgba(232,93,117,.08); border: 1px solid rgba(232,93,117,.2);
  color: var(--red); font-size: .72rem; font-weight: 600;
  padding: .15rem .55rem; border-radius: 100px;
}

/* ── ACTION BTNS ── */
.action-cell { display: flex; align-items: center; gap: .35rem; justify-content: flex-end; }
.icon-btn {
  width: 30px; height: 30px; border-radius: 8px;
  background: rgba(255,255,255,.04); border: 1px solid var(--border2);
  color: var(--muted); cursor: pointer; display: inline-flex; align-items: center;
  justify-content: center; transition: all .15s; font-size: .9rem;
}
.icon-btn:hover { background: rgba(255,255,255,.08); color: var(--white); border-color: var(--border); }
.icon-btn.danger:hover { background: rgba(232,93,117,.12); color: var(--red); border-color: rgba(232,93,117,.3); }
.icon-btn:disabled { opacity: .3; cursor: default; pointer-events: none; }

/* ── SCHEDULE CHIPS ── */
.sched-chips { display: flex; flex-wrap: wrap; gap: .3rem; }
.sched-chip {
  font-size: .68rem; background: rgba(62,207,120,.06);
  border: 1px solid rgba(62,207,120,.18); color: var(--green);
  padding: .1rem .5rem; border-radius: 100px;
}

/* ── MODAL ── */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,.65);
  z-index: 1000; display: none; align-items: center; justify-content: center;
  backdrop-filter: blur(4px); padding: 1rem;
}
.modal-overlay.open { display: flex; }
.modal {
  background: var(--navy-mid); border: 1px solid var(--border);
  border-radius: 20px; padding: 0; max-width: 560px; width: 100%;
  max-height: 90vh; overflow-y: auto; animation: slideUp .22s ease;
}
.modal-sm { max-width: 420px; }
@keyframes slideUp { from{transform:translateY(18px);opacity:0} to{transform:translateY(0);opacity:1} }
.modal-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.3rem 1.6rem; border-bottom: 1px solid var(--border);
}
.modal-head h3 { font-family: var(--font); font-size: 1.05rem; font-weight: 700; }
.btn-x {
  background: none; border: none; color: var(--muted); cursor: pointer;
  font-size: 1.2rem; line-height: 1; transition: color .2s;
}
.btn-x:hover { color: var(--white); }
.modal-body { padding: 1.4rem 1.6rem; }
.modal-foot {
  display: flex; gap: .65rem; justify-content: flex-end;
  padding: 1rem 1.6rem; border-top: 1px solid var(--border);
}
.form-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem; }
.form-group { margin-bottom: .9rem; }
.form-group:last-child { margin-bottom: 0; }
.form-group label {
  display: block; font-family: var(--font); font-size: .7rem; font-weight: 700;
  letter-spacing: .07em; text-transform: uppercase; color: var(--muted);
  margin-bottom: .35rem;
}
.form-group input, .form-group select, .form-group textarea {
  width: 100%; padding: .7rem 1rem; background: rgba(255,255,255,.05);
  border: 1px solid var(--border); border-radius: 10px; color: var(--white);
  font-family: var(--font-body); font-size: .88rem; outline: none;
  transition: border-color .2s; resize: vertical;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
  border-color: var(--purple); background: rgba(167,139,250,.04);
}
.form-group select option { background: var(--navy-mid); }

/* ── SCHEDULE BUILDER ── */
.sched-row { display: flex; gap: .5rem; align-items: center; margin-bottom: .5rem; flex-wrap: wrap; }
.sched-row input { flex: 1; min-width: 70px; }
.btn-rm { background: none; border: 1px solid var(--border2); border-radius: 7px; color: var(--muted); cursor: pointer; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; transition: all .15s; }
.btn-rm:hover { border-color: rgba(232,93,117,.4); color: var(--red); background: rgba(232,93,117,.08); }
.btn-add-sched { background: none; border: none; color: var(--muted); font-family: var(--font-body); font-size: .8rem; cursor: pointer; padding: .2rem 0; transition: color .15s; }
.btn-add-sched:hover { color: var(--green); }

/* ── TOAST ── */
.toast {
  position: fixed; bottom: 2rem; right: 2rem; background: var(--navy-light);
  border: 1px solid var(--border); border-radius: 12px; padding: .85rem 1.3rem;
  font-family: var(--font); font-size: .84rem; color: var(--white);
  z-index: 9999; transform: translateY(80px); opacity: 0;
  transition: all .3s; display: flex; align-items: center; gap: .6rem;
}
.toast.show { transform: translateY(0); opacity: 1; }
.toast-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--green); flex-shrink: 0; }
.toast-dot.red { background: var(--red); }

/* ── EMPTY / LOADING ── */
.empty-state { text-align: center; padding: 3rem 1rem; color: var(--muted); font-size: .88rem; }
.loading-row td { color: var(--muted); font-style: italic; text-align: center; padding: 2rem; }

/* ── CONFIRM ── */
.confirm-body { padding: 1.4rem 1.6rem; color: var(--muted); font-size: .88rem; line-height: 1.6; }
.confirm-body strong { color: var(--white); }

/* ── RESPONSIVE ── */
@media(max-width: 900px) {
  .stats-bar { grid-template-columns: 1fr 1fr; }
  .main { padding: 1rem; }
}
@media(max-width: 600px) {
  .stats-bar { grid-template-columns: 1fr 1fr; }
  .form-grid2 { grid-template-columns: 1fr; }
  .search-input { width: 160px; }
}
</style>
</head>
<body id="body">

<div class="topbar">
  <div class="topbar-left">
    <a href="dashboard-admin.php" class="back-btn">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
      <span id="back-lbl">Retour</span>
    </a>
    <div class="page-title" id="page-title-lbl">Gestion des cours</div>
  </div>
  <div class="topbar-right">
    <div class="lang-toggle">
      <div class="lang-pill active" id="pill-fr" onclick="setLang('fr')">🇫🇷 FR</div>
    </div>
    <div class="admin-chip" id="admin-chip-lbl">Admin</div>
  </div>
</div>

<div class="main">

  <!-- Stats bar -->
  <div class="stats-bar" id="stats-bar"></div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="toolbar-left">
      <div class="search-wrap">
        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="search-input" type="text" id="search-input" placeholder="Rechercher…" oninput="filterTable()">
      </div>
      <select class="filter-select" id="filter-level" onchange="filterTable()">
        <option value="">Tous les niveaux</option>
        <option>A1</option><option>A1–A2</option><option>A2</option>
        <option>B1</option><option>B1–B2</option><option>B2</option>
        <option>B2–C1</option><option>C1</option>
      </select>
      <select class="filter-select" id="filter-assign" onchange="filterTable()">
        <option value="">Tous</option>
        <option value="assigned" id="opt-assigned">Assignés</option>
        <option value="unassigned" id="opt-unassigned">Non assignés</option>
      </select>
    </div>
    <div class="toolbar-right">
      <button class="btn btn-assign" onclick="openAssignModal()" id="btn-assign-lbl">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        Assigner un prof
      </button>
      <button class="btn btn-primary" onclick="openAddModal()" id="btn-add-lbl">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouveau cours
      </button>
    </div>
  </div>

  <!-- Table -->
  <div class="table-card">
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th id="th-group">Groupe</th>
            <th id="th-subject">Matière</th>
            <th id="th-level">Niveau</th>
            <th id="th-teacher">Professeur</th>
            <th id="th-schedule">Horaire</th>
            <th id="th-students">Élèves</th>
            <th id="th-actions"></th>
          </tr>
        </thead>
        <tbody id="courses-tbody">
          <tr class="loading-row"><td colspan="7" id="loading-msg">Chargement…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- ── MODAL: ADD / EDIT COURSE ── -->
<div class="modal-overlay" id="modal-course">
  <div class="modal">
    <div class="modal-head">
      <h3 id="course-modal-title">Nouveau cours</h3>
      <button class="btn-x" onclick="closeModal('course')">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group"><label id="lbl-group-fr">Groupe (FR)</label>
        <input id="f-group-fr" placeholder="ex: Groupe A – Débutant"></div>
      <div class="form-group"><label id="lbl-subject-fr">Matière (FR)</label>
        <input id="f-subject-fr" placeholder="ex: Anglais Général"></div>
      <div class="form-grid2">
        <div class="form-group"><label id="lbl-level">Niveau</label>
          <select id="f-level">
            <option>A1</option><option>A1–A2</option><option>A2</option>
            <option>B1</option><option>B1–B2</option><option>B2</option>
            <option>B2–C1</option><option>C1</option>
          </select>
        </div>
        <div class="form-group"><label id="lbl-students">Nombre d'élèves</label>
          <input id="f-students" type="number" min="1" max="60" placeholder="ex: 18">
        </div>
      </div>
      <div class="form-group">
        <label id="lbl-schedule">Horaires
          <button type="button" class="btn-add-sched" onclick="addSchedRow()" id="btn-add-sched">+ Ajouter un créneau</button>
        </label>
        <div id="sched-container"></div>
      </div>
      <div class="form-group">
        <label id="lbl-teacher">Professeur assigné</label>
        <select id="f-teacher"><option value="">— Non assigné —</option></select>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" onclick="closeModal('course')" id="modal-cancel-lbl">Annuler</button>
      <button class="btn btn-primary" onclick="saveCourse()" id="modal-save-lbl">Enregistrer</button>
    </div>
  </div>
</div>

<!-- ── MODAL: ASSIGN ── -->
<div class="modal-overlay" id="modal-assign">
  <div class="modal modal-sm">
    <div class="modal-head">
      <h3 id="assign-modal-title">Assigner un professeur</h3>
      <button class="btn-x" onclick="closeModal('assign')">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label id="lbl-a-course">Cours</label>
        <select id="a-course-sel"><option value="">— Sélectionner —</option></select>
      </div>
      <div class="form-group">
        <label id="lbl-a-teacher">Professeur</label>
        <select id="a-teacher-sel"><option value="">— Sélectionner —</option></select>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" onclick="closeModal('assign')" id="assign-cancel-lbl">Annuler</button>
      <button class="btn btn-primary" onclick="doAssign()" id="assign-submit-lbl">Assigner</button>
    </div>
  </div>
</div>

<!-- ── MODAL: CONFIRM DELETE ── -->
<div class="modal-overlay" id="modal-delete">
  <div class="modal modal-sm">
    <div class="modal-head">
      <h3 id="del-modal-title">Supprimer le cours</h3>
      <button class="btn-x" onclick="closeModal('delete')">✕</button>
    </div>
    <div class="confirm-body" id="del-modal-body">Supprimer <strong id="del-course-name"></strong> ?</div>
    <div class="modal-foot">
      <button class="btn btn-secondary" onclick="closeModal('delete')" id="del-cancel-lbl">Annuler</button>
      <button class="btn btn-danger" onclick="confirmDelete()" id="del-confirm-lbl">Supprimer</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"><div class="toast-dot" id="toast-dot"></div><span id="toast-msg"></span></div>

<script>
/* ══════════════════════════════════════════════════
   DATA  (replace with API calls in production)
   ══════════════════════════════════════════════════ */
let courses = [
  { id:1, group_name_fr:'Groupe A – Débutant',
    subject_fr:'Anglais Général',
    level:'A1–A2', students_count:14, teacher_id:1,
    schedule:[{day_fr:'Lundi',time:'09:00–11:00',room:'Salle 12'},
              {day_fr:'Mercredi',time:'09:00–11:00',room:'Salle 12'}] },
  { id:2, group_name_fr:'Groupe B – Intermédiaire',
    subject_fr:'Rédaction & Communication',
    level:'B1', students_count:18, teacher_id:2,
    schedule:[{day_fr:'Mardi',time:'14:00–16:00',room:'Labo 3'},
              {day_fr:'Jeudi',time:'14:00–16:00',room:'Labo 3'}] },
  { id:3, group_name_fr:'Groupe C – Avancé',
    subject_fr:'Expression Orale & Présentation',
    level:'B2–C1', students_count:10, teacher_id:null,
    schedule:[{day_fr:'Vendredi',time:'10:00–12:00',room:'Amphi B'}] },
];

let teachers = [
  { id:1, full_name:'M. Hassan',   username:'mhassan' },
  { id:2, full_name:'Mme Alami',   username:'malami'  },
  { id:3, full_name:'M. Benali',   username:'mbenali' },
  { id:4, full_name:'Mme Ouali',   username:'mouali'  },
];

/* ══════════════════════════════════════════════════
   API WRAPPERS  (uncomment + adapt for production)
   ══════════════════════════════════════════════════

async function apiGet(action, params = {}) {
  const qs = new URLSearchParams({ action, ...params });
  const r = await fetch('assign_courses.php?' + qs);
  return r.json();
}
async function apiPost(action, body) {
  const r = await fetch('assign_courses.php?action=' + action, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return r.json();
}

async function loadData() {
  const [cRes, tRes] = await Promise.all([
    apiGet('list_courses'), apiGet('list_teachers')
  ]);
  if (cRes.ok) courses = cRes.courses;
  if (tRes.ok) teachers = tRes.teachers;
}

// In production, replace the DOMContentLoaded handler below with:
// document.addEventListener('DOMContentLoaded', async () => {
//   const lang = sessionStorage.getItem('upskill_lang') || 'fr';
//   await loadData();
//   setLang(lang);
//   refresh();
// });
*/

/* ══════════════════════════════════════════════════
   STATE
   ══════════════════════════════════════════════════ */
let lang = 'fr';
let editingId = null;
let deleteId  = null;
let schedN    = 0;

/* ══════════════════════════════════════════════════
   TRANSLATIONS
   ══════════════════════════════════════════════════ */
const TR = {
  fr: {
    pageTitle: 'Gestion des cours',
    back: 'Retour', adminChip: 'Admin',
    statTotal: 'Cours total', statAssigned: 'Assignés',
    statUnassigned: 'Non assignés', statTeachers: 'Professeurs',
    searchPlaceholder: 'Rechercher…',
    allLevels: 'Tous les niveaux', allAssign: 'Tous',
    optAssigned: 'Assignés', optUnassigned: 'Non assignés',
    btnAssign: 'Assigner un prof', btnNew: 'Nouveau cours',
    thGroup: 'Groupe', thSubject: 'Matière', thLevel: 'Niveau',
    thTeacher: 'Professeur', thSchedule: 'Horaire',
    thStudents: 'Élèves',
    addTitle: 'Nouveau cours', editTitle: 'Modifier le cours',
    lblGroupFr: 'Groupe', lblSubjectFr: 'Matière',
    lblLevel: 'Niveau', lblStudents: "Nombre d'élèves",
    lblSchedule: 'Horaires', btnAddSched: '+ Ajouter un créneau',
    lblTeacher: 'Professeur assigné', lblNoTeacher: '— Non assigné —',
    cancel: 'Annuler', save: 'Enregistrer', update: 'Mettre à jour',
    assignTitle: 'Assigner un professeur',
    lblACourse: 'Cours', lblATeacher: 'Professeur',
    selectCourse: '— Sélectionner un cours —',
    selectTeacher: '— Sélectionner un professeur —',
    assignBtn: 'Assigner',
    delTitle: 'Supprimer le cours',
    delBody: 'Supprimer définitivement le cours ',
    delBody2: ' ? Cette action est irréversible.',
    delConfirm: 'Supprimer',
    loading: 'Chargement…', noResults: 'Aucun cours trouvé.',
    toastCreated: 'Cours créé avec succès.',
    toastUpdated: 'Cours mis à jour.',
    toastDeleted: 'Cours supprimé.',
    toastAssigned: 'Professeur assigné avec succès.',
    toastUnassigned: 'Assignation retirée.',
    toastError: 'Erreur. Vérifiez les champs.',
    unassigned: 'Non assigné',
    placeholderDay: 'Lun/Mar…',
    placeholderTime: '09:00–11:00',
    placeholderRoom: 'Salle 12',
    dayLabel: 'Jour', timeLabel: 'Heure', roomLabel: 'Salle',
  },
};
const t = k => TR[lang][k] || k;

/* ══════════════════════════════════════════════════
   LANG
   ══════════════════════════════════════════════════ */
function setLang(l) {
  if (l !== 'fr' && l !== 'en') l = 'fr';
  lang = l;
  sessionStorage.setItem('upskill_lang', l);
  document.documentElement.setAttribute('lang', l);
  document.getElementById('pill-fr').className = 'lang-pill' + (l === 'fr' ? ' active' : '');
  applyTr();
}

function applyTr() {
  const s = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  const p = (id, v) => { const el = document.getElementById(id); if (el) el.placeholder = v; };
  s('page-title-lbl',   t('pageTitle'));
  s('back-lbl',         t('back'));
  s('admin-chip-lbl',   t('adminChip'));
  s('btn-assign-lbl',   t('btnAssign'));
  s('btn-add-lbl',      t('btnNew'));
  s('th-group',         t('thGroup'));
  s('th-subject',       t('thSubject'));
  s('th-level',         t('thLevel'));
  s('th-teacher',       t('thTeacher'));
  s('th-schedule',      t('thSchedule'));
  s('th-students',      t('thStudents'));
  s('lbl-group-fr',     t('lblGroupFr'));
  s('lbl-subject-fr',   t('lblSubjectFr'));
  s('lbl-level',        t('lblLevel'));
  s('lbl-students',     t('lblStudents'));
  s('lbl-schedule',     t('lblSchedule'));
  s('btn-add-sched',    t('btnAddSched'));
  s('lbl-teacher',      t('lblTeacher'));
  s('lbl-a-course',     t('lblACourse'));
  s('lbl-a-teacher',    t('lblATeacher'));
  s('assign-modal-title', t('assignTitle'));
  s('del-modal-title',  t('delTitle'));
  s('modal-cancel-lbl', t('cancel'));
  s('assign-cancel-lbl',t('cancel'));
  s('del-cancel-lbl',   t('cancel'));
  s('modal-save-lbl',   editingId ? t('update') : t('save'));
  s('assign-submit-lbl',t('assignBtn'));
  s('del-confirm-lbl',  t('delConfirm'));
  s('opt-assigned',     t('optAssigned'));
  s('opt-unassigned',   t('optUnassigned'));
  p('search-input',     t('searchPlaceholder'));
  renderStats();
  renderTable();
}

/* ══════════════════════════════════════════════════
   STATS
   ══════════════════════════════════════════════════ */
function renderStats() {
  const total      = courses.length;
  const assigned   = courses.filter(c => c.teacher_id).length;
  const unassigned = total - assigned;
  const tCount     = teachers.length;
  const icons = [
    { icon: '📚', cls: 'purple', val: total,      lbl: t('statTotal')      },
    { icon: '✅', cls: 'green',  val: assigned,   lbl: t('statAssigned')   },
    { icon: '⚠️', cls: 'yellow', val: unassigned, lbl: t('statUnassigned') },
    { icon: '👩‍🏫', cls: 'blue',  val: tCount,     lbl: t('statTeachers')   },
  ];
  document.getElementById('stats-bar').innerHTML = icons.map(i =>
    '<div class="stat-card">'
    + '<div class="stat-icon ' + i.cls + '">' + i.icon + '</div>'
    + '<div><div class="stat-val">' + i.val + '</div><div class="stat-lbl">' + i.lbl + '</div></div>'
    + '</div>'
  ).join('');
}

/* ══════════════════════════════════════════════════
   TABLE
   ══════════════════════════════════════════════════ */
function levelClass(l) {
  if (l.startsWith('A')) return 'lvl-a';
  if (l.startsWith('B')) return 'lvl-b';
  return 'lvl-c';
}
function teacherInit(name) {
  const p = name.split(' ').filter(Boolean);
  return (p[0][0] + (p[1] ? p[1][0] : '')).toUpperCase();
}

function renderTable() {
  const q   = (document.getElementById('search-input').value || '').toLowerCase();
  const lvl = document.getElementById('filter-level').value;
  const asg = document.getElementById('filter-assign').value;

  let list = courses.filter(c => {
    const gfr = c.group_name_fr.toLowerCase();
    const sfr = c.subject_fr.toLowerCase();
    const tch = teachers.find(t => t.id === c.teacher_id);
    const tname = tch ? tch.full_name.toLowerCase() : '';
    const matchQ   = !q || gfr.includes(q) || sfr.includes(q) || tname.includes(q) || c.level.toLowerCase().includes(q);
    const matchLvl = !lvl || c.level === lvl;
    const matchAsg = !asg || (asg === 'assigned' ? c.teacher_id : !c.teacher_id);
    return matchQ && matchLvl && matchAsg;
  });

  const tbody = document.getElementById('courses-tbody');
  if (!list.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--muted);">' + t('noResults') + '</td></tr>';
    return;
  }

  tbody.innerHTML = list.map(c => {
    const gname   = c.group_name_fr;
    const subject = c.subject_fr;
    const tch     = teachers.find(tt => tt.id === c.teacher_id);
    const sched   = c.schedule.slice(0, 2).map(s =>
      s.day_fr + ' ' + (s.time ? s.time.split('–')[0] : '')
    ).join(', ') + (c.schedule.length > 2 ? '…' : '');

    const teacherCell = tch
      ? '<span class="teacher-pill"><div class="t-avatar">' + teacherInit(tch.full_name) + '</div>' + tch.full_name + '</span>'
      : '<span class="unassigned-badge">⚠ ' + t('unassigned') + '</span>';

    return '<tr>'
      + '<td>'
        + '<div style="font-family:var(--font);font-size:.88rem;font-weight:600;">' + gname + '</div>'
      + '</td>'
      + '<td>' + subject + '</td>'
      + '<td><span class="lvl-badge ' + levelClass(c.level) + '">' + c.level + '</span></td>'
      + '<td>' + teacherCell + '</td>'
      + '<td><div class="sched-chips">'
        + (sched ? '<span class="sched-chip">' + sched + '</span>' : '<span style="color:var(--muted2);font-size:.78rem;">—</span>')
        + '</div></td>'
      + '<td style="text-align:center;font-family:var(--font);font-weight:600;">' + (c.students_count || 0) + '</td>'
      + '<td>'
        + '<div class="action-cell">'
          + '<button class="icon-btn" onclick="openEditModal(' + c.id + ')" title="Modifier" aria-label="Modifier">'
            + '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>'
          + '</button>'
          + '<button class="icon-btn" onclick="openUnassignPrompt(' + c.id + ')" title="Retirer le professeur" aria-label="Retirer" ' + (!c.teacher_id ? 'disabled' : '') + '>'
            + '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="18" y1="8" x2="23" y2="13"/><line x1="23" y1="8" x2="18" y2="13"/></svg>'
          + '</button>'
          + '<button class="icon-btn danger" onclick="openDeleteModal(' + c.id + ')" title="Supprimer" aria-label="Supprimer">'
            + '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>'
          + '</button>'
        + '</div>'
      + '</td>'
    + '</tr>';
  }).join('');
}

function filterTable() { renderTable(); }

/* ══════════════════════════════════════════════════
   SCHEDULE BUILDER
   ══════════════════════════════════════════════════ */
function addSchedRow(dayFr = '', time = '', room = '') {
  const id = 'sr-' + (schedN++);
  const cont = document.getElementById('sched-container');
  const div = document.createElement('div');
  div.className = 'sched-row'; div.id = id;
  div.innerHTML =
    '<input placeholder="' + t('placeholderDay') + '" value="' + dayFr + '" style="max-width:80px" title="Jour">'
    + '<input placeholder="' + t('placeholderTime') + '" value="' + time + '" style="max-width:120px" title="Heure">'
    + '<input placeholder="' + t('placeholderRoom') + '" value="' + room + '" style="max-width:90px" title="Salle">'
    + '<button type="button" class="btn-rm" onclick="this.parentElement.remove()" aria-label="Supprimer">×</button>';
  cont.appendChild(div);
}

function getSchedFromForm() {
  return Array.from(document.getElementById('sched-container').children).map(row => {
    const inputs = row.querySelectorAll('input');
    return { day_fr: inputs[0].value.trim(),
             time: inputs[1].value.trim(), room: inputs[2].value.trim() };
  }).filter(s => s.day_fr || s.time);
}

/* ══════════════════════════════════════════════════
   MODALS
   ══════════════════════════════════════════════════ */
function openModal(id)  { document.getElementById('modal-' + id).classList.add('open'); }
function closeModal(id) { document.getElementById('modal-' + id).classList.remove('open'); }

function populateTeacherSelect(selId, currentId) {
  const sel = document.getElementById(selId);
  sel.innerHTML = '<option value="">' + t('lblNoTeacher') + '</option>'
    + teachers.map(tc => '<option value="' + tc.id + '"' + (tc.id === currentId ? ' selected' : '') + '>' + tc.full_name + '</option>').join('');
}

function openAddModal() {
  editingId = null;
  document.getElementById('course-modal-title').textContent = t('addTitle');
  document.getElementById('modal-save-lbl').textContent     = t('save');
  ['f-group-fr','f-subject-fr','f-students'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('f-level').value = 'A1';
  document.getElementById('sched-container').innerHTML = '';
  schedN = 0;
  addSchedRow();
  populateTeacherSelect('f-teacher', null);
  openModal('course');
}

function openEditModal(id) {
  const c = courses.find(x => x.id === id);
  if (!c) return;
  editingId = id;
  document.getElementById('course-modal-title').textContent = t('editTitle');
  document.getElementById('modal-save-lbl').textContent     = t('update');
  document.getElementById('f-group-fr').value   = c.group_name_fr;
  document.getElementById('f-subject-fr').value = c.subject_fr;
  document.getElementById('f-level').value      = c.level;
  document.getElementById('f-students').value   = c.students_count;
  document.getElementById('sched-container').innerHTML = '';
  schedN = 0;
  if (c.schedule && c.schedule.length) {
    c.schedule.forEach(s => addSchedRow(s.day_fr, s.time, s.room));
  } else {
    addSchedRow();
  }
  populateTeacherSelect('f-teacher', c.teacher_id);
  openModal('course');
}

function saveCourse() {
  const gfr = document.getElementById('f-group-fr').value.trim();
  const sfr = document.getElementById('f-subject-fr').value.trim();
  if (!gfr || !sfr) { showToast(t('toastError'), true); return; }

  const data = {
    group_name_fr: gfr,
    subject_fr:    sfr,
    level:         document.getElementById('f-level').value,
    students_count: parseInt(document.getElementById('f-students').value) || 0,
    teacher_id:    parseInt(document.getElementById('f-teacher').value) || null,
    schedule:      getSchedFromForm(),
  };

  /* ── In production, use API calls here ──
  if (editingId) {
    // PUT / custom endpoint or update course then reassign teacher
    apiPost('assign', { teacher_id: data.teacher_id, course_id: editingId }).then(r => {
      if (r.ok) { showToast(t('toastUpdated')); } else { showToast(r.error.fr, true); }
    });
  } else {
    // POST create course endpoint then assign
  }
  */

  if (editingId) {
    const idx = courses.findIndex(c => c.id === editingId);
    if (idx > -1) courses[idx] = { ...courses[idx], ...data };
    showToast(t('toastUpdated'));
  } else {
    data.id = Date.now();
    courses.push(data);
    showToast(t('toastCreated'));
  }
  closeModal('course');
  refresh();
}

function openAssignModal() {
  const sel = document.getElementById('a-course-sel');
  sel.innerHTML = '<option value="">' + t('selectCourse') + '</option>'
    + courses.map(c => '<option value="' + c.id + '">' + c.group_name_fr + '</option>').join('');
  const tsel = document.getElementById('a-teacher-sel');
  tsel.innerHTML = '<option value="">' + t('selectTeacher') + '</option>'
    + teachers.map(tt => '<option value="' + tt.id + '">' + tt.full_name + '</option>').join('');
  openModal('assign');
}

function doAssign() {
  const cid = parseInt(document.getElementById('a-course-sel').value);
  const tid = parseInt(document.getElementById('a-teacher-sel').value);
  if (!cid || !tid) { showToast(t('toastError'), true); return; }

  /* ── In production:
  apiPost('assign', { teacher_id: tid, course_id: cid }).then(r => {
    if (r.ok) { courses.find(c => c.id===cid).teacher_id = tid; closeModal('assign'); refresh(); showToast(t('toastAssigned')); }
    else showToast(r.error[lang], true);
  });
  */

  const idx = courses.findIndex(c => c.id === cid);
  if (idx > -1) courses[idx].teacher_id = tid;
  closeModal('assign');
  refresh();
  showToast(t('toastAssigned'));
}

function openUnassignPrompt(id) {
  /* ── In production:
  const c = courses.find(x => x.id === id);
  if (c && c.teacher_id) {
    apiPost('unassign', { teacher_id: c.teacher_id, course_id: id }).then(r => {
      if (r.ok) { c.teacher_id = null; refresh(); showToast(t('toastUnassigned')); }
      else showToast(r.error[lang], true);
    });
  }
  */
  const idx = courses.findIndex(c => c.id === id);
  if (idx > -1 && courses[idx].teacher_id) {
    courses[idx].teacher_id = null;
    refresh();
    showToast(t('toastUnassigned'));
  }
}

function openDeleteModal(id) {
  deleteId = id;
  const c = courses.find(x => x.id === id);
  const name = c ? c.group_name_fr : '';
  document.getElementById('del-modal-body').innerHTML = t('delBody') + '<strong>' + name + '</strong>' + t('delBody2');
  document.getElementById('del-modal-title').textContent = t('delTitle');
  document.getElementById('del-confirm-lbl').textContent = t('delConfirm');
  document.getElementById('del-cancel-lbl').textContent  = t('cancel');
  openModal('delete');
}

function confirmDelete() {
  /* ── In production: apiPost('unassign', { ... }) then delete course via separate endpoint ── */
  courses = courses.filter(c => c.id !== deleteId);
  closeModal('delete');
  refresh();
  showToast(t('toastDeleted'));
}

/* ══════════════════════════════════════════════════
   TOAST
   ══════════════════════════════════════════════════ */
let toastTimer;
function showToast(msg, isError = false) {
  const toast = document.getElementById('toast');
  const dot   = document.getElementById('toast-dot');
  document.getElementById('toast-msg').textContent = msg;
  dot.className = 'toast-dot' + (isError ? ' red' : '');
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
}

/* ══════════════════════════════════════════════════
   CLOSE MODALS ON BACKDROP CLICK
   ══════════════════════════════════════════════════ */
['modal-course','modal-assign','modal-delete'].forEach(id => {
  document.getElementById(id).addEventListener('click', function(e) {
    if (e.target === this) closeModal(id.replace('modal-', ''));
  });
});

/* ══════════════════════════════════════════════════
   INIT
   ══════════════════════════════════════════════════ */
function refresh() { renderStats(); renderTable(); }

document.addEventListener('DOMContentLoaded', () => {
  const _sl = sessionStorage.getItem('upskill_lang');
  const savedLang = (_sl === 'fr' || _sl === 'en') ? _sl : 'fr';
  setLang(savedLang);
  refresh();
});
</script>
</body>
</html>
