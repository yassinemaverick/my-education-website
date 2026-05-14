<?php
/**
 * index-fr.php — Landing page (Français)
 */
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true, 'samesite' => 'Lax',
]);
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$lang = 'fr';
$t = [
    'nav_courses'   => 'Cours',
    'nav_how'       => 'Comment ça marche',
    'nav_enroll'    => 'Inscription',
    'nav_login'     => 'Espace étudiant →',
    'portal_url'    => 'https://study.upskill-edu.com/index2-fr.php',
    'footer_portal' => 'Espace étudiant',
    'footer_contact'=> 'Contact',
    'footer_copy'   => '© 2026 Upskill Education · upskill-edu.com · Tous droits réservés.',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upskill Education – Cours d'anglais en ligne</title>
<meta name="description" content="Apprenez l'anglais en ligne avec Upskill Education. Cours en direct sur Zoom, petits groupes, suivi personnalisé. 599 DH/session. Inscrivez-vous dès maintenant.">
<link rel="canonical" href="https://upskill-edu.com/fr">
<meta property="og:type"        content="website">
<meta property="og:url"         content="https://upskill-edu.com/fr">
<meta property="og:title"       content="Upskill Education – Cours d'anglais en ligne">
<meta property="og:description" content="Cours d'anglais en direct sur Zoom, petits groupes, suivi personnalisé. 599 DH/session.">
<meta property="og:image"       content="https://upskill-edu.com/assets/img/1.png">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">
<link rel="alternate" hreflang="en" href="https://upskill-edu.com/en">
<link rel="alternate" hreflang="fr" href="https://upskill-edu.com/fr">
<link rel="alternate" hreflang="x-default" href="https://upskill-edu.com/en">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --navy: #0f1d2e; --navy-mid: #162436; --navy-light: #1e3248;
    --green: #3ecf78; --green-dark: #28a85c; --green-glow: rgba(62,207,120,0.15);
    --white: #ffffff; --muted: rgba(255,255,255,0.55);
    --border: rgba(255,255,255,0.1); --card-bg: rgba(255,255,255,0.04);
    --font: 'Sora', sans-serif; --font-body: 'DM Sans', sans-serif;
  }
  html { scroll-behavior: smooth; scroll-padding-top: 80px; overflow-x: hidden; }
  body { background: var(--navy); color: var(--white); font-family: var(--font-body); min-height: 100vh; }
  nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; display: flex; align-items: center; justify-content: space-between; padding: 1rem 3rem; background: rgba(15,29,46,0.85); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); }
  .nav-logo { display: flex; align-items: center; gap: 0.6rem; text-decoration: none; }
  .nav-logo svg { height: 36px; }
  .nav-logo span { font-family: var(--font); font-weight: 600; font-size: 1.1rem; color: var(--white); letter-spacing: -0.02em; }
  .nav-logo span em { color: var(--green); font-style: normal; }
  .nav-links { display: flex; align-items: center; gap: 2rem; }
  .nav-links a { color: var(--muted); text-decoration: none; font-size: 0.9rem; font-family: var(--font); font-weight: 400; transition: color 0.2s; }
  .nav-links a:hover { color: var(--white); }
  .nav-cta { background: var(--green); color: var(--navy) !important; font-weight: 600 !important; padding: 0.5rem 1.3rem; border-radius: 8px; }
  .nav-cta:hover { background: var(--green-dark) !important; }
  .hero { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 8rem 2rem 5rem; position: relative; overflow: hidden; }
  .hero::before { content: ''; position: absolute; top: -200px; left: 50%; transform: translateX(-50%); width: 700px; height: 700px; background: radial-gradient(circle, rgba(62,207,120,0.12) 0%, transparent 70%); pointer-events: none; }
  .hero-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: var(--green-glow); border: 1px solid rgba(62,207,120,0.3); color: var(--green); font-family: var(--font); font-size: 0.78rem; font-weight: 500; padding: 0.4rem 1rem; border-radius: 100px; margin-bottom: 2rem; letter-spacing: 0.05em; text-transform: uppercase; }
  .hero-badge span { width: 6px; height: 6px; background: var(--green); border-radius: 50%; display: inline-block; animation: pulse 2s infinite; }
  @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
  .hero h1 { font-family: var(--font); font-size: clamp(2.8rem, 6vw, 5rem); font-weight: 700; line-height: 1.1; letter-spacing: -0.03em; max-width: 800px; margin-bottom: 1.5rem; }
  .hero h1 span { color: var(--green); }
  .hero p { font-size: 1.1rem; color: var(--muted); max-width: 520px; line-height: 1.7; margin-bottom: 2.5rem; font-weight: 300; }
  .hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }
  .btn-primary { background: var(--green); color: var(--navy); font-family: var(--font); font-weight: 600; font-size: 0.95rem; padding: 0.8rem 2rem; border-radius: 10px; border: none; cursor: pointer; text-decoration: none; transition: background 0.2s, transform 0.15s; display: inline-block; }
  .btn-primary:hover { background: var(--green-dark); transform: translateY(-2px); }
  .btn-outline { background: transparent; color: var(--white); font-family: var(--font); font-weight: 500; font-size: 0.95rem; padding: 0.8rem 2rem; border-radius: 10px; border: 1px solid var(--border); cursor: pointer; text-decoration: none; transition: border-color 0.2s, background 0.2s; display: inline-block; }
  .btn-outline:hover { border-color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.05); }
  .stats { display: flex; justify-content: center; gap: 4rem; padding: 3rem 2rem; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); background: var(--card-bg); flex-wrap: wrap; }
  .stat { text-align: center; }
  .stat-num { font-family: var(--font); font-size: 2rem; font-weight: 700; color: var(--white); letter-spacing: -0.02em; }
  .stat-num span { color: var(--green); }
  .stat-label { color: var(--muted); font-size: 0.85rem; margin-top: 0.2rem; }
  section { padding: 6rem 2rem; max-width: 1100px; margin: 0 auto; }
  .section-label { font-family: var(--font); font-size: 0.75rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--green); margin-bottom: 0.8rem; }
  .section-title { font-family: var(--font); font-size: clamp(1.8rem, 3.5vw, 2.8rem); font-weight: 700; letter-spacing: -0.03em; line-height: 1.15; margin-bottom: 1rem; }
  .section-sub { color: var(--muted); font-size: 1rem; font-weight: 300; max-width: 480px; line-height: 1.7; }
  .courses-header { margin-bottom: 3rem; }
  .courses-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
  .course-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; padding: 2rem; cursor: pointer; transition: border-color 0.25s, transform 0.25s, background 0.25s; position: relative; overflow: hidden; display: flex; flex-direction: column; }
  .course-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, var(--green), transparent); opacity: 0; transition: opacity 0.3s; }
  .course-card:hover { border-color: rgba(62,207,120,0.4); transform: translateY(-4px); background: rgba(255,255,255,0.06); }
  .course-card:hover::before { opacity: 1; }
  .course-card.selected { border-color: var(--green); background: rgba(62,207,120,0.06); }
  .course-card.selected::before { opacity: 1; }
  .course-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1.2rem; }
  .course-icon { width: 52px; height: 52px; border-radius: 14px; background: var(--green-glow); border: 1px solid rgba(62,207,120,0.25); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
  .course-check { width: 28px; height: 28px; border-radius: 50%; background: var(--green); display: none; align-items: center; justify-content: center; flex-shrink: 0; }
  .course-card.selected .course-check { display: flex; }
  .course-title { font-family: var(--font); font-size: 1.2rem; font-weight: 600; margin-bottom: 0.4rem; letter-spacing: -0.02em; }
  .course-desc { color: var(--muted); font-size: 0.88rem; line-height: 1.65; font-weight: 300; margin-bottom: 1.3rem; }
  .course-price { display: inline-flex; align-items: baseline; gap: 0.3rem; background: rgba(62,207,120,0.1); border: 1px solid rgba(62,207,120,0.25); border-radius: 10px; padding: 0.5rem 0.9rem; margin-bottom: 1.3rem; }
  .price-amount { font-family: var(--font); font-size: 1.3rem; font-weight: 700; color: var(--green); }
  .price-period { font-size: 0.78rem; color: rgba(62,207,120,0.75); }
  .course-divider { border: none; border-top: 1px solid var(--border); margin-bottom: 1.2rem; }
  .features-label { font-family: var(--font); font-size: 0.7rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255,255,255,0.3); margin-bottom: 0.75rem; }
  .course-features { list-style: none; display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1.5rem; flex: 1; }
  .course-features li { display: flex; align-items: flex-start; gap: 0.65rem; font-size: 0.86rem; color: rgba(255,255,255,0.75); line-height: 1.45; }
  .course-features li::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--green); flex-shrink: 0; margin-top: 0.42rem; opacity: 0.9; }
  .course-tags { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: auto; }
  .tag { font-size: 0.72rem; font-family: var(--font); padding: 0.25rem 0.7rem; border-radius: 100px; background: rgba(255,255,255,0.07); border: 1px solid var(--border); color: var(--muted); }
  .enroll-section { background: var(--navy-mid); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 6rem 2rem; }
  .enroll-inner { max-width: 640px; margin: 0 auto; }
  .form-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 24px; padding: 2.5rem; margin-top: 3rem; }
  .selected-course-pill { display: inline-flex; align-items: center; gap: 0.5rem; background: var(--green-glow); border: 1px solid rgba(62,207,120,0.3); color: var(--green); font-size: 0.82rem; font-family: var(--font); padding: 0.35rem 0.9rem; border-radius: 100px; margin-bottom: 1.5rem; }
  .form-group { margin-bottom: 1.25rem; }
  .form-group label { display: block; font-family: var(--font); font-size: 0.82rem; font-weight: 500; color: var(--muted); margin-bottom: 0.5rem; letter-spacing: 0.02em; }
  .form-group input { width: 100%; padding: 0.85rem 1rem; background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: 10px; color: var(--white); font-family: var(--font-body); font-size: 0.95rem; outline: none; transition: border-color 0.2s, background 0.2s; }
  .form-group input::placeholder { color: rgba(255,255,255,0.25); }
  .form-group input:focus { border-color: var(--green); background: rgba(62,207,120,0.05); }
  .form-group select { width: 100%; padding: 0.85rem 1rem; background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: 10px; color: var(--white); font-family: var(--font-body); font-size: 0.95rem; outline: none; appearance: none; transition: border-color 0.2s; cursor: pointer; }
  .form-group select option { background: var(--navy-mid); color: var(--white); }
  .form-group select:focus { border-color: var(--green); }
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  .btn-submit { width: 100%; padding: 1rem; background: var(--green); color: var(--navy); font-family: var(--font); font-weight: 700; font-size: 1rem; border: none; border-radius: 12px; cursor: pointer; transition: background 0.2s, transform 0.15s; margin-top: 0.5rem; letter-spacing: -0.01em; }
  .btn-submit:hover { background: var(--green-dark); transform: translateY(-2px); }
  .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
  .form-notice { font-size: 0.78rem; color: var(--muted); text-align: center; margin-top: 0.8rem; line-height: 1.5; }
  .success-msg { display: none; text-align: center; padding: 2rem; }
  .success-icon { width: 60px; height: 60px; border-radius: 50%; background: var(--green-glow); border: 2px solid var(--green); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.2rem; font-size: 1.5rem; }
  .success-msg h3 { font-family: var(--font); font-size: 1.3rem; font-weight: 600; margin-bottom: 0.5rem; }
  .success-msg p { color: var(--muted); font-size: 0.9rem; line-height: 1.6; }
  .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-top: 3rem; }
  .step { padding: 1.8rem; border: 1px solid var(--border); border-radius: 16px; background: var(--card-bg); }
  .step-num { font-family: var(--font); font-size: 0.75rem; font-weight: 600; color: var(--green); letter-spacing: 0.05em; margin-bottom: 1rem; }
  .step h3 { font-family: var(--font); font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; }
  .step p { color: var(--muted); font-size: 0.85rem; line-height: 1.6; font-weight: 300; }
  footer { border-top: 1px solid var(--border); padding: 2.5rem 3rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; background: var(--navy-mid); }
  .footer-logo { font-family: var(--font); font-size: 0.9rem; font-weight: 600; color: var(--muted); }
  .footer-logo em { color: var(--green); font-style: normal; }
  .footer-links { display: flex; gap: 1.5rem; }
  .footer-links a { color: var(--muted); text-decoration: none; font-size: 0.82rem; transition: color 0.2s; }
  .footer-links a:hover { color: var(--white); }
  .footer-copy { color: rgba(255,255,255,0.2); font-size: 0.78rem; width: 100%; }
  .text-center { text-align: center; }
  .text-center .section-sub { margin: 0 auto; }
  #toast { position: fixed; bottom: 2rem; right: 2rem; z-index: 999; background: #1a2f21; border: 1px solid var(--green); color: var(--white); font-family: var(--font); font-size: 0.85rem; padding: 0.8rem 1.2rem; border-radius: 12px; transform: translateY(100px); opacity: 0; transition: all 0.35s; max-width: 300px; }
  #toast.show { transform: translateY(0); opacity: 1; }
  .hamburger-btn { display:none; background:none; border:none; cursor:pointer; padding:.35rem; color:var(--white); flex-direction:column; gap:5px; align-items:center; justify-content:center; }
  .hamburger-btn span { display:block; width:22px; height:2px; background:var(--white); border-radius:2px; transition:transform .2s,opacity .2s; }
  @media (max-width: 600px) {
    nav { padding: 1rem 1.2rem; }
    .hamburger-btn { display: flex; }
    .nav-links { display:none; flex-direction:column; position:absolute; top:65px; left:0; right:0; background:rgba(15,29,46,.97); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); padding:1rem 1.5rem; gap:1rem; z-index:99; }
    .nav-links.open { display:flex; }
    .form-grid { grid-template-columns: 1fr; }
    footer { flex-direction: column; }
    .form-card { padding: 1.5rem; }
  }
  .hero {
    background-image: url('assets/img/2.png');
    background-size: cover; background-position: center 30%; background-repeat: no-repeat;
  }
  .hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(15,29,46,0.72) 0%, rgba(15,29,46,0.60) 50%, rgba(15,29,46,0.88) 100%); pointer-events: none; z-index: 0; }
  .hero > * { position: relative; z-index: 1; }
  .photo-strip { padding: 4rem 2rem; background: var(--navy-mid); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
  .photo-strip-inner { max-width: 1100px; margin: 0 auto; }
  .photo-strip-header { text-align: center; margin-bottom: 2rem; }
  .photo-strip-header .section-label { display: block; }
  .photo-strip-header .section-title { font-family: var(--font); font-size: clamp(1.4rem, 2.5vw, 2rem); font-weight: 700; letter-spacing: -0.03em; margin-bottom: 0; }
  .photos-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; grid-template-rows: 220px 220px; gap: 1rem; }
  .photo-item { border-radius: 16px; overflow: hidden; position: relative; background: var(--navy-light); }
  .photo-item img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.5s ease, filter 0.4s ease; filter: brightness(0.88) saturate(0.9); }
  .photo-item:hover img { transform: scale(1.06); filter: brightness(1) saturate(1); }
  .photo-item::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, rgba(15,29,46,0.45) 0%, transparent 50%); pointer-events: none; }
  .photo-item.tall { grid-row: span 2; }
  .photo-item-caption { position: absolute; bottom: 0.75rem; left: 0.9rem; font-family: var(--font); font-size: 0.7rem; font-weight: 500; color: rgba(255,255,255,0.75); letter-spacing: 0.04em; text-transform: uppercase; z-index: 2; }
  @media (max-width: 768px) { .photos-grid { grid-template-columns: 1fr 1fr; grid-template-rows: auto; } .photo-item.tall { grid-row: span 1; height: 180px; } .photo-item { height: 180px; } }
  @media (max-width: 500px) { .photos-grid { grid-template-columns: 1fr; } .photo-item, .photo-item.tall { height: 200px; } }
  /* ── TESTIMONIALS — section lumineuse ── */
  .testimonials { padding: 6rem 2rem; background: #eef6ff; }
  .testimonials-inner { max-width: 1100px; margin: 0 auto; }
  .testimonials .section-label { color: #1a9a52; }
  .testimonials .section-title { color: #0f1d2e; }
  .testimonials-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 3rem; }
  .testimonial-card { background: #ffffff; border: none; border-radius: 20px; padding: 1.75rem 2rem; display: flex; flex-direction: column; gap: 0.9rem; box-shadow: 0 4px 28px rgba(15,29,46,0.09); transition: transform .2s, box-shadow .2s; }
  .testimonial-card:hover { transform: translateY(-5px); box-shadow: 0 12px 40px rgba(15,29,46,0.15); }
  .testimonial-header { display: flex; align-items: center; gap: 0.85rem; }
  .testimonial-avatar { width: 54px; height: 54px; border-radius: 50%; flex-shrink: 0; object-fit: cover; border: 2px solid #e2eaf4; }
  .testimonial-author { display: flex; flex-direction: column; gap: 0.22rem; }
  .testimonial-name { font-family: var(--font); font-size: 0.9rem; font-weight: 700; color: #0f1d2e; }
  .testimonial-badge { font-size: 0.7rem; font-family: var(--font); padding: 0.18rem 0.6rem; border-radius: 100px; background: rgba(62,207,120,0.12); border: 1px solid rgba(62,207,120,0.35); color: #1a9a52; display: inline-block; width: fit-content; }
  .testimonial-stars { color: #f59e0b; font-size: 0.85rem; letter-spacing: 2px; }
  .testimonial-quote { font-size: 2.4rem; color: #3ecf78; font-family: Georgia, serif; line-height: 0.85; margin-bottom: -0.3rem; opacity: 0.55; }
  .testimonial-text { color: #374151; font-size: 0.93rem; line-height: 1.75; font-style: italic; }
</style>
</head>
<body>

<nav>
  <div style="display:flex;align-items:center;gap:0.75rem;">
    <a href="/fr" class="nav-logo">
      <svg viewBox="0 0 160 50" fill="none" xmlns="http://www.w3.org/2000/svg">
        <text x="36" y="36" font-family="Sora, sans-serif" font-size="28" font-weight="700" fill="white">pskill</text>
        <path d="M14 12 Q14 28 26 28 Q38 28 38 12" stroke="#3ecf78" stroke-width="3.5" fill="none" stroke-linecap="round"/>
        <path d="M22 6 L26 2 L30 6" stroke="#3ecf78" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="26" y1="2" x2="26" y2="18" stroke="#3ecf78" stroke-width="3.5" stroke-linecap="round"/>
      </svg>
      <span><em>Up</em>skill Education</span>
    </a>
    <a href="/en" style="display:inline-flex;align-items:center;gap:0.3rem;background:transparent;border:1px solid var(--border);color:var(--muted);font-weight:500;font-family:var(--font);font-size:0.73rem;padding:0.3rem 0.75rem;border-radius:100px;text-decoration:none;">🇬🇧 EN</a>
    <a href="/fr" style="display:inline-flex;align-items:center;gap:0.3rem;background:var(--green-glow);border:1px solid rgba(62,207,120,0.4);color:var(--green);font-weight:600;font-family:var(--font);font-size:0.73rem;padding:0.3rem 0.75rem;border-radius:100px;text-decoration:none;">🇫🇷 FR</a>
  </div>
  <button class="hamburger-btn" id="hamburger" onclick="toggleNav()" aria-label="Ouvrir le menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
  <div class="nav-links" id="nav-links">
    <a href="#courses">Cours</a>
    <a href="#how">Comment ça marche</a>
    <a href="#enroll">Inscription</a>
    <a target="_blank" rel="noopener noreferrer" href="https://study.upskill-edu.com/index2-fr.php" class="nav-cta">Espace étudiant →</a>
  </div>
</nav>



<div class="hero" id="home">
  <div class="hero-badge"><span></span> Inscriptions ouvertes — 2026</div>
  <h1>Maîtrisez l'anglais.<br><span>Ouvrez toutes les portes.</span></h1>
  <p>Des cours d'anglais animés par des experts, conçus pour des résultats concrets — que vous construisiez votre carrière, prépariez le Baccalauréat ou souhaitiez simplement progresser.</p>
  <div class="hero-actions">
    <a href="#courses" class="btn-primary">Découvrir les cours</a>
    <a target="_blank" rel="noopener noreferrer" href="https://study.upskill-edu.com/index2-fr.php" class="btn-outline">Espace étudiant →</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat-num">3<span>+</span></div><div class="stat-label">Programmes spécialisés</div></div>
  <div class="stat"><div class="stat-num">599<span> Dh</span></div><div class="stat-label">Par session</div></div>
  <div class="stat"><div class="stat-num">29<span>h</span></div><div class="stat-label">Heures d'enseignement</div></div>
  <div class="stat"><div class="stat-num">9–12</div><div class="stat-label">Étudiants par classe</div></div>
</div>

<div class="photo-strip">
  <div class="photo-strip-inner">
    <div class="photo-strip-header">
      <span class="section-label">L'apprentissage réinventé</span>
      <div class="section-title">Votre salle de classe, où que vous soyez.</div>
    </div>
    <div class="photos-grid">
      <div class="photo-item tall">
        <img src="assets/img/5.png" alt="Petit groupe en ligne" loading="lazy">
        <span class="photo-item-caption">Petits groupes</span>
      </div>
      <div class="photo-item">
        <img src="assets/img/3.png" alt="Groupe d'étudiants" loading="lazy">
        <span class="photo-item-caption">Apprenez partout</span>
      </div>
      <div class="photo-item">
        <img src="assets/img/1.png" alt="Étudiant avec ordinateur" loading="lazy">
        <span class="photo-item-caption">Professeurs experts</span>
      </div>
      <div class="photo-item">
        <img src="assets/img/4.png" alt="Cours en ligne sur écran" loading="lazy">
        <span class="photo-item-caption">Étude ciblée</span>
      </div>
      <div class="photo-item">
        <img src="assets/img/2.png" alt="Étudiants en cours en ligne" loading="lazy">
        <span class="photo-item-caption">Séances en direct</span>
      </div>
    </div>
  </div>
</div>

<section id="courses">
  <div class="courses-header">
    <div class="section-label">Nos programmes</div>
    <div class="section-title">Trouvez le cours<br>qui vous correspond.</div>
    <p class="section-sub">Trois programmes ciblés, chacun conçu autour d'un objectif précis. Choisissez celui qui correspond à votre projet.</p>
  </div>
  <div class="courses-grid">

    <div class="course-card" data-course="Anglais des affaires" onclick="selectCourse(this)" tabindex="0" role="button" onkeydown="if(event.key==='Enter'||event.key===' '){selectCourse(this);event.preventDefault();}">
      <div class="course-header">
        <div class="course-icon">💼</div>
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      </div>
      <div class="course-title">Anglais des affaires</div>
      <div class="course-desc">Communiquez avec assurance dans un cadre professionnel. Maîtrisez les e-mails, présentations, négociations et conversations en milieu de travail.</div>
      <div class="course-price"><span class="price-amount">599 Dh</span><span class="price-period">/ session &nbsp;·&nbsp; ~2,5 mois</span></div>
      <hr class="course-divider">
      <div class="features-label">Ce qui est inclus</div>
      <ul class="course-features">
        <li>~29 heures d'enseignement (deux séances de 1h30 par semaine)</li>
        <li>Cours de communication axés sur l'expression orale</li>
        <li>Professeurs hautement qualifiés</li>
        <li>9 à 12 étudiants par classe</li>
        <li>Apprenez de n'importe où — sans déplacement</li>
        <li>Exercices, devoirs et évaluations</li>
        <li>Concours et défis</li>
        <li>Certificat à l'issue du cours</li>
      </ul>
      <div class="course-tags"><span class="tag">Communication pro</span><span class="tag">Présentations</span><span class="tag">Rédaction</span><span class="tag">Négociation</span></div>
    </div>

    <div class="course-card" data-course="Anglais général" onclick="selectCourse(this)" tabindex="0" role="button" onkeydown="if(event.key==='Enter'||event.key===' '){selectCourse(this);event.preventDefault();}">
      <div class="course-header">
        <div class="course-icon">🌍</div>
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      </div>
      <div class="course-title">Anglais général</div>
      <div class="course-desc">Construisez de solides bases à l'oral, à l'écoute, en lecture et en écriture. Idéal pour gagner en aisance et en confiance au quotidien.</div>
      <div class="course-price"><span class="price-amount">599 Dh</span><span class="price-period">/ session &nbsp;·&nbsp; ~2,5 mois</span></div>
      <hr class="course-divider">
      <div class="features-label">Ce qui est inclus</div>
      <ul class="course-features">
        <li>~29 heures d'enseignement (deux séances de 1h30 par semaine)</li>
        <li>Cours de communication axés sur l'expression orale</li>
        <li>Professeurs hautement qualifiés</li>
        <li>9 à 12 étudiants par classe</li>
        <li>Apprenez de n'importe où — sans déplacement</li>
        <li>Exercices, devoirs et évaluations</li>
        <li>Concours et défis</li>
        <li>Certificat à l'issue du cours</li>
      </ul>
      <div class="course-tags"><span class="tag">Tous niveaux</span><span class="tag">Expression orale</span><span class="tag">Grammaire</span><span class="tag">Vocabulaire</span></div>
    </div>

    <div class="course-card" data-course="Anglais Baccalauréat" onclick="selectCourse(this)" tabindex="0" role="button" onkeydown="if(event.key==='Enter'||event.key===' '){selectCourse(this);event.preventDefault();}">
      <div class="course-header">
        <div class="course-icon">🎓</div>
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      </div>
      <div class="course-title">Anglais Baccalauréat</div>
      <div class="course-desc">Préparation ciblée à l'examen du Bac. Entraînez-vous sur de vrais sujets d'examens, rédactions chronométrées et techniques d'oral.</div>
      <div class="course-price"><span class="price-amount">599 Dh</span><span class="price-period">/ session &nbsp;·&nbsp; ~2,5 mois</span></div>
      <hr class="course-divider">
      <div class="features-label">Ce qui est inclus</div>
      <ul class="course-features">
        <li>~29 heures d'enseignement (deux séances de 1h30 par semaine)</li>
        <li>Cours de communication axés sur l'expression orale</li>
        <li>Professeurs hautement qualifiés</li>
        <li>9 à 12 étudiants par classe</li>
        <li>Apprenez de n'importe où — sans déplacement</li>
        <li>Exercices, devoirs et évaluations</li>
        <li>Concours et défis</li>
        <li>Certificat à l'issue du cours</li>
      </ul>
      <div class="course-tags"><span class="tag">Prépa examen</span><span class="tag">Sujets corrigés</span><span class="tag">Oral</span><span class="tag">Rédaction</span></div>
    </div>

  </div>
</section>

<section id="how" style="background: var(--navy-mid); max-width: 100%; padding: 6rem 2rem;">
  <div style="max-width: 1100px; margin: 0 auto;">
    <div class="section-label text-center" style="text-align:center;">La démarche</div>
    <div class="section-title text-center" style="text-align:center;">Comment ça marche</div>
    <p class="section-sub" style="margin: 0 auto; text-align:center;">De l'inscription à la maîtrise — voici votre parcours.</p>
    <div class="steps">
      <div class="step"><div class="step-num">01</div><h3>Choisissez votre cours</h3><p>Parcourez nos trois programmes et sélectionnez celui qui correspond à vos objectifs et à votre niveau actuel.</p></div>
      <div class="step"><div class="step-num">02</div><h3>Soumettez vos informations</h3><p>Renseignez votre nom, e-mail et téléphone. Notre équipe vous contactera pour confirmer votre inscription.</p></div>
      <div class="step"><div class="step-num">03</div><h3>Recevez vos identifiants</h3><p>Vous recevrez vos identifiants personnels pour accéder à la plateforme étudiant depuis notre équipe administrative.</p></div>
      <div class="step"><div class="step-num">04</div><h3>Commencez à apprendre</h3><p>Connectez-vous à votre tableau de bord, accédez aux ressources, devoirs et suivez votre progression en temps réel.</p></div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<div class="testimonials">
  <div class="testimonials-inner">
    <div class="section-label text-center" style="text-align:center;">Ce que disent nos étudiants</div>
    <div class="section-title text-center" style="text-align:center;">Des résultats réels, des personnes réelles.</div>
    <div class="testimonials-grid">
      <div class="testimonial-card">
        <div class="testimonial-header">
          <img class="testimonial-avatar" src="/assets/img/testimonial-yasmine.png" alt="Yasmine B.">
          <div class="testimonial-author">
            <span class="testimonial-name">Yasmine B.</span>
            <span class="testimonial-badge">Étudiante Baccalauréat</span>
          </div>
        </div>
        <div class="testimonial-stars">★★★★★</div>
        <div class="testimonial-quote">"</div>
        <p class="testimonial-text">Ma note d'anglais au Bac est passée de 8/20 à 16/20 après une seule session avec Upskill. Le petit groupe a tout changé.</p>
      </div>
      <div class="testimonial-card">
        <div class="testimonial-header">
          <img class="testimonial-avatar" src="/assets/img/testimonial-karim.jpg" alt="Karim M.">
          <div class="testimonial-author">
            <span class="testimonial-name">Karim M.</span>
            <span class="testimonial-badge">Étudiant Anglais des affaires</span>
          </div>
        </div>
        <div class="testimonial-stars">★★★★★</div>
        <div class="testimonial-quote">"</div>
        <p class="testimonial-text">Je me sens enfin à l'aise en réunion. Le cours d'anglais des affaires m'a donné du vocabulaire réel que j'utilise chaque jour.</p>
      </div>
      <div class="testimonial-card">
        <div class="testimonial-header">
          <img class="testimonial-avatar" src="/assets/img/testimonial-nadia.png" alt="Nadia F.">
          <div class="testimonial-author">
            <span class="testimonial-name">Nadia F.</span>
            <span class="testimonial-badge">Parent · Anglais général</span>
          </div>
        </div>
        <div class="testimonial-stars">★★★★★</div>
        <div class="testimonial-quote">"</div>
        <p class="testimonial-text">La prononciation de ma fille s'est nettement améliorée. Les professeurs sont patients et la plateforme facilite le suivi de sa progression.</p>
      </div>
    </div>
  </div>
</div>

<div class="enroll-section" id="enroll">
  <div class="enroll-inner">
    <div class="section-label text-center" style="text-align:center;">Inscription</div>
    <div class="section-title text-center" style="text-align:center;">Prêt à commencer ?</div>
    <p class="section-sub" style="margin:0 auto;text-align:center;">Remplissez le formulaire et nous vous recontacterons sous 24 heures pour confirmer votre place.</p>
    <div class="form-card">
      <div id="form-area">
        <div id="selected-pill" style="display:none;" class="selected-course-pill">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M1.5 6L4.5 9L10.5 3" stroke="#3ecf78" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span id="pill-text">Anglais des affaires</span>
        </div>
        <div class="form-group">
          <label>CHOISIR UN COURS</label>
          <select id="course-select" required>
            <option value="">— Choisissez un programme —</option>
            <option value="Anglais des affaires">Anglais des affaires</option>
            <option value="Anglais général">Anglais général</option>
            <option value="Anglais Baccalauréat">Anglais Baccalauréat</option>
          </select>
        </div>
        <div class="form-group">
          <label>NOM COMPLET</label>
          <input type="text" id="name" placeholder="Votre nom complet" required>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>ADRESSE E-MAIL</label>
            <input type="email" id="email" placeholder="vous@exemple.com" required>
          </div>
          <div class="form-group">
            <label>NUMÉRO DE TÉLÉPHONE</label>
            <input type="tel" id="phone" placeholder="+212 6XX XXX XXX" required>
          </div>
        </div>
        <button class="btn-submit" onclick="submitForm()">Envoyer ma demande d'inscription →</button>
        <p class="form-notice">Vos informations sont enregistrées de manière sécurisée et transmises directement à notre équipe. Nous vous contacterons sous 24 heures.</p>
      </div>
      <div class="success-msg" id="success-msg">
        <div class="success-icon">✓</div>
        <h3>Demande reçue !</h3>
        <p>Merci pour votre inscription. Notre équipe examinera votre demande et vous contactera sous 24 heures pour confirmer votre place et vous envoyer vos identifiants de connexion.</p>
      </div>
    </div>
  </div>
</div>



<footer>
  <div class="footer-logo"><em>Up</em>skill Education</div>
  <div class="footer-links">
    <a href="#courses">Cours</a>
    <a href="#enroll">Inscription</a>
    <a target="_blank" rel="noopener noreferrer" href="https://study.upskill-edu.com/index2-fr.php">Espace étudiant</a>
    <a href="#" onclick="openContact();return false;">Contact</a>
  </div>
  <div class="footer-copy">© 2026 Upskill Education · upskill-edu.com · Tous droits réservés.</div>
</footer>

<div id="toast"></div>

<script>
  const CSRF_TOKEN = <?= json_encode($csrf) ?>;
  let selectedCourse = '';

  function selectCourse(card) {
    document.querySelectorAll('.course-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    selectedCourse = card.dataset.course;
    document.getElementById('course-select').value = selectedCourse;
    document.getElementById('pill-text').textContent = selectedCourse;
    document.getElementById('selected-pill').style.display = 'inline-flex';
    document.getElementById('enroll').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  document.getElementById('course-select').addEventListener('change', function() {
    selectedCourse = this.value;
    if (selectedCourse) {
      document.getElementById('pill-text').textContent = selectedCourse;
      document.getElementById('selected-pill').style.display = 'inline-flex';
      document.querySelectorAll('.course-card').forEach(c => {
        c.classList.toggle('selected', c.dataset.course === selectedCourse);
      });
    }
  });

  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3500);
  }

  async function submitForm() {
    const name   = document.getElementById('name').value.trim();
    const email  = document.getElementById('email').value.trim();
    const phone  = document.getElementById('phone').value.trim();
    const course = document.getElementById('course-select').value;

    if (!course) { showToast('Veuillez d\'abord choisir un cours.'); return; }
    if (!name)   { showToast('Veuillez saisir votre nom complet.'); return; }
    if (!email || !email.includes('@')) { showToast('Veuillez saisir une adresse e-mail valide.'); return; }
    if (!phone)  { showToast('Veuillez saisir votre numéro de téléphone.'); return; }

    const btn = document.querySelector('.btn-submit');
    btn.disabled = true;
    btn.textContent = 'Envoi en cours…';

    try {
      const body = new URLSearchParams({ csrf_token: CSRF_TOKEN, name, email, phone, course, lang: 'fr' });
      const res  = await fetch('/enroll.php', { method: 'POST', body });
      const data = await res.json();

      if (data.ok) {
        document.getElementById('form-area').style.display = 'none';
        document.getElementById('success-msg').style.display = 'block';
      } else {
        showToast(data.error || 'Une erreur s\'est produite. Veuillez réessayer.');
        btn.disabled = false;
        btn.textContent = 'Envoyer ma demande d\'inscription →';
      }
    } catch (err) {
      showToast('Erreur réseau. Vérifiez votre connexion et réessayez.');
      btn.disabled = false;
      btn.textContent = 'Envoyer ma demande d\'inscription →';
    }
  }
</script>

<!-- ── CONTACT MODAL ── -->
<div id="contact-overlay" onclick="if(event.target===this)closeContact()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#162436;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:2rem;width:100%;max-width:480px;margin:1rem;position:relative;">
    <button onclick="closeContact()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:rgba(255,255,255,.4);font-size:1.3rem;cursor:pointer;line-height:1;">✕</button>
    <div style="font-family:'Sora',sans-serif;font-size:1.2rem;font-weight:700;margin-bottom:.3rem;">Nous contacter</div>
    <div style="color:rgba(255,255,255,.55);font-size:.88rem;margin-bottom:1.5rem;font-family:'DM Sans',sans-serif;">Envoyez-nous un message, nous vous répondrons sous 24 heures.</div>
    <div style="margin-bottom:1rem;">
      <label style="display:block;font-family:'Sora',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">VOTRE NOM</label>
      <input id="c-name" type="text" placeholder="Votre nom complet" maxlength="120"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;">
    </div>
    <div style="margin-bottom:1rem;">
      <label style="display:block;font-family:'Sora',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">ADRESSE E-MAIL</label>
      <input id="c-email" type="email" placeholder="vous@exemple.com" maxlength="180"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;">
    </div>
    <div style="margin-bottom:1rem;">
      <label style="display:block;font-family:'Sora',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">
        NUMÉRO DE TÉLÉPHONE <span style="font-weight:400;font-size:.7rem;color:rgba(255,255,255,.35);text-transform:none;letter-spacing:0;">(optionnel)</span>
      </label>
      <input id="c-phone" type="tel" placeholder="Ex: 0612345678" maxlength="30"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;">
    </div>
    <div style="margin-bottom:1.5rem;">
      <label style="display:block;font-family:'Sora',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">VOTRE MESSAGE</label>
      <textarea id="c-msg" placeholder="Écrivez votre message ici…" maxlength="2000" rows="4"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;resize:vertical;box-sizing:border-box;"></textarea>
    </div>
    <div id="c-error" style="display:none;color:#f87171;font-size:.83rem;margin-bottom:.8rem;font-family:'DM Sans',sans-serif;"></div>
    <button id="c-btn" onclick="sendContact()"
      style="width:100%;padding:.9rem;background:#3ecf78;color:#0f1d2e;font-family:'Sora',sans-serif;font-weight:700;font-size:.95rem;border:none;border-radius:12px;cursor:pointer;transition:background .2s;">
      Envoyer le message →
    </button>
    <div id="c-success" style="display:none;text-align:center;padding:1.5rem 0;">
      <div style="font-size:2.5rem;margin-bottom:.5rem;">✅</div>
      <div style="font-family:'Sora',sans-serif;font-weight:600;margin-bottom:.3rem;">Message envoyé !</div>
      <div style="color:rgba(255,255,255,.55);font-size:.88rem;font-family:'DM Sans',sans-serif;">Nous vous répondrons bientôt.</div>
    </div>
  </div>
</div>

<script>
function openContact() {
  const o = document.getElementById('contact-overlay');
  o.style.display = 'flex';
  document.getElementById('c-name').focus();
  document.getElementById('c-error').style.display = 'none';
  document.getElementById('c-success').style.display = 'none';
  document.getElementById('c-btn').style.display = '';
  document.getElementById('c-btn').disabled = false;
  document.getElementById('c-btn').textContent = 'Envoyer le message →';
}
function closeContact() {
  document.getElementById('contact-overlay').style.display = 'none';
  ['c-name','c-email','c-phone','c-msg'].forEach(id => document.getElementById(id).value = '');
}
async function sendContact() {
  const name  = document.getElementById('c-name').value.trim();
  const email = document.getElementById('c-email').value.trim();
  const phone = document.getElementById('c-phone').value.trim();
  const msg   = document.getElementById('c-msg').value.trim();
  const errEl = document.getElementById('c-error');
  errEl.style.display = 'none';

  if (!name)  { errEl.textContent = 'Veuillez saisir votre nom.'; errEl.style.display = ''; return; }
  if (!email || !email.includes('@')) { errEl.textContent = 'Adresse e-mail invalide.'; errEl.style.display = ''; return; }
  if (!msg)   { errEl.textContent = 'Veuillez saisir votre message.'; errEl.style.display = ''; return; }

  const btn = document.getElementById('c-btn');
  btn.disabled = true; btn.textContent = 'Envoi…';

  try {
    const body = new URLSearchParams({ csrf_token: CSRF_TOKEN, name, email, phone, message: msg, lang: 'fr' });
    const res  = await fetch('/contact.php', { method: 'POST', body });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('c-success').style.display = '';
      btn.style.display = 'none';
    } else {
      errEl.textContent = data.error || 'Erreur. Veuillez réessayer.';
      errEl.style.display = '';
      btn.disabled = false; btn.textContent = 'Envoyer le message →';
    }
  } catch(e) {
    errEl.textContent = 'Erreur réseau. Vérifiez votre connexion.';
    errEl.style.display = '';
    btn.disabled = false; btn.textContent = 'Envoyer le message →';
  }
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeContact(); });

function toggleNav() {
  const links = document.getElementById('nav-links');
  const btn   = document.getElementById('hamburger');
  const open  = links.classList.toggle('open');
  btn.setAttribute('aria-expanded', open);
}
document.addEventListener('click', e => {
  const links = document.getElementById('nav-links');
  const btn   = document.getElementById('hamburger');
  if (links && btn && !links.contains(e.target) && !btn.contains(e.target)) {
    links.classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
  }
});
</script>

<!-- Bouton WhatsApp — remplacer 212XXXXXXXXX par votre vrai numéro (sans le signe +) -->
<a href="https://wa.me/212XXXXXXXXX" target="_blank" rel="noopener noreferrer"
   style="position:fixed;bottom:2rem;left:2rem;z-index:998;background:#25d366;color:white;width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(37,211,102,.45);text-decoration:none;transition:transform .2s;"
   onmouseenter="this.style.transform='scale(1.12)'" onmouseleave="this.style.transform=''"
   title="Contacter sur WhatsApp">
  <svg width="26" height="26" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
    <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.656 1.438 5.168L2 22l4.975-1.395A9.96 9.96 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm5.093 13.677c-.22.616-1.287 1.174-1.762 1.214-.476.04-.923.214-3.1-.648-2.606-1.038-4.267-3.7-4.396-3.872-.128-.172-1.053-1.402-1.053-2.676 0-1.273.665-1.9.9-2.16.236-.258.516-.322.687-.322.172 0 .344.002.494.008.159.006.37-.06.58.458.215.518.731 1.79.795 1.921.064.13.107.282.021.452-.086.172-.13.28-.258.43-.13.15-.272.337-.386.45-.13.13-.264.27-.113.527.15.258.666 1.098 1.43 1.778.982.874 1.815 1.143 2.072 1.273.257.13.408.108.558-.065.15-.172.63-.737.797-.99.168-.257.336-.215.565-.13.23.086 1.452.685 1.702.81.25.128.416.192.479.3.063.107.063.579-.157 1.2z"/>
  </svg>
</a>

</body>
</html>
