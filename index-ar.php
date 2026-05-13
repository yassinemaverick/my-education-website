<?php
/**
 * index-ar.php — Landing page (العربية)
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

$lang = 'ar';
$t = [
    'nav_courses'   => 'الدورات',
    'nav_how'       => 'كيف يعمل',
    'nav_enroll'    => 'التسجيل',
    'nav_login'     => '← بوابة الطلاب',
    'portal_url'    => 'https://study.upskill-edu.com/index2-ar.php',
    'footer_portal' => 'بوابة الطلاب',
    'footer_contact'=> 'تواصل معنا',
    'footer_copy'   => '© 2026 Upskill Education · upskill-edu.com · جميع الحقوق محفوظة',
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upskill Education – دورات اللغة الإنجليزية</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Cairo:wght@300;400;500;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --navy: #0f1d2e; --navy-mid: #162436; --navy-light: #1e3248;
    --green: #3ecf78; --green-dark: #28a85c; --green-glow: rgba(62,207,120,0.15);
    --white: #ffffff; --muted: rgba(255,255,255,0.55);
    --border: rgba(255,255,255,0.1); --card-bg: rgba(255,255,255,0.04);
    --font: 'Cairo', sans-serif; --font-body: 'Cairo', sans-serif;
  }
  html { scroll-behavior: smooth; }
  body { background: var(--navy); color: var(--white); font-family: var(--font-body); min-height: 100vh; overflow-x: hidden; direction: rtl; }
  nav { position: fixed; top: 0; left: 0; right: 0; z-index: 100; display: flex; align-items: center; justify-content: space-between; padding: 1rem 3rem; background: rgba(15,29,46,0.85); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); }
  .nav-logo { display: flex; align-items: center; gap: 0.6rem; text-decoration: none; }
  .nav-logo svg { height: 36px; }
  .nav-logo span { font-family: 'Sora', sans-serif; font-weight: 600; font-size: 1.1rem; color: var(--white); letter-spacing: -0.02em; }
  .nav-logo span em { color: var(--green); font-style: normal; }
  .nav-links { display: flex; align-items: center; gap: 2rem; flex-direction: row-reverse; }
  .nav-links a { color: var(--muted); text-decoration: none; font-size: 0.95rem; font-family: var(--font); font-weight: 500; transition: color 0.2s; }
  .nav-links a:hover { color: var(--white); }
  .nav-cta { background: var(--green); color: var(--navy) !important; font-weight: 700 !important; padding: 0.5rem 1.3rem; border-radius: 8px; }
  .nav-cta:hover { background: var(--green-dark) !important; }
  .hero { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 8rem 2rem 5rem; position: relative; overflow: hidden; }
  .hero::before { content: ''; position: absolute; top: -200px; left: 50%; transform: translateX(-50%); width: 700px; height: 700px; background: radial-gradient(circle, rgba(62,207,120,0.12) 0%, transparent 70%); pointer-events: none; }
  .hero-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: var(--green-glow); border: 1px solid rgba(62,207,120,0.3); color: var(--green); font-family: var(--font); font-size: 0.85rem; font-weight: 600; padding: 0.4rem 1rem; border-radius: 100px; margin-bottom: 2rem; }
  .hero-badge span { width: 6px; height: 6px; background: var(--green); border-radius: 50%; display: inline-block; animation: pulse 2s infinite; }
  @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
  .hero h1 { font-family: var(--font); font-size: clamp(2.4rem, 5.5vw, 4.5rem); font-weight: 700; line-height: 1.25; max-width: 800px; margin-bottom: 1.5rem; }
  .hero h1 span { color: var(--green); }
  .hero p { font-size: 1.1rem; color: var(--muted); max-width: 540px; line-height: 1.9; margin-bottom: 2.5rem; font-weight: 300; }
  .hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; flex-direction: row-reverse; }
  .btn-primary { background: var(--green); color: var(--navy); font-family: var(--font); font-weight: 700; font-size: 0.95rem; padding: 0.8rem 2rem; border-radius: 10px; border: none; cursor: pointer; text-decoration: none; transition: background 0.2s, transform 0.15s; display: inline-block; }
  .btn-primary:hover { background: var(--green-dark); transform: translateY(-2px); }
  .btn-outline { background: transparent; color: var(--white); font-family: var(--font); font-weight: 500; font-size: 0.95rem; padding: 0.8rem 2rem; border-radius: 10px; border: 1px solid var(--border); cursor: pointer; text-decoration: none; transition: border-color 0.2s, background 0.2s; display: inline-block; }
  .btn-outline:hover { border-color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.05); }
  .stats { display: flex; justify-content: center; gap: 4rem; padding: 3rem 2rem; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); background: var(--card-bg); flex-wrap: wrap; }
  .stat { text-align: center; }
  .stat-num { font-family: 'Sora', sans-serif; font-size: 2rem; font-weight: 700; color: var(--white); letter-spacing: -0.02em; }
  .stat-num span { color: var(--green); }
  .stat-label { color: var(--muted); font-size: 0.9rem; margin-top: 0.2rem; font-family: var(--font); }
  section { padding: 6rem 2rem; max-width: 1100px; margin: 0 auto; }
  .section-label { font-family: var(--font); font-size: 0.8rem; font-weight: 700; letter-spacing: 0.05em; color: var(--green); margin-bottom: 0.8rem; }
  .section-title { font-family: var(--font); font-size: clamp(1.8rem, 3.5vw, 2.8rem); font-weight: 700; line-height: 1.3; margin-bottom: 1rem; }
  .section-sub { color: var(--muted); font-size: 1rem; font-weight: 300; max-width: 480px; line-height: 1.8; }
  .courses-header { margin-bottom: 3rem; }
  .courses-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
  .course-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; padding: 2rem; cursor: pointer; transition: border-color 0.25s, transform 0.25s, background 0.25s; position: relative; overflow: hidden; display: flex; flex-direction: column; }
  .course-card::before { content: ''; position: absolute; top: 0; right: 0; left: 0; height: 2px; background: linear-gradient(270deg, var(--green), transparent); opacity: 0; transition: opacity 0.3s; }
  .course-card:hover { border-color: rgba(62,207,120,0.4); transform: translateY(-4px); background: rgba(255,255,255,0.06); }
  .course-card:hover::before { opacity: 1; }
  .course-card.selected { border-color: var(--green); background: rgba(62,207,120,0.06); }
  .course-card.selected::before { opacity: 1; }
  .course-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1.2rem; flex-direction: row-reverse; }
  .course-icon { width: 52px; height: 52px; border-radius: 14px; background: var(--green-glow); border: 1px solid rgba(62,207,120,0.25); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
  .course-check { width: 28px; height: 28px; border-radius: 50%; background: var(--green); display: none; align-items: center; justify-content: center; flex-shrink: 0; }
  .course-card.selected .course-check { display: flex; }
  .course-title { font-family: var(--font); font-size: 1.2rem; font-weight: 700; margin-bottom: 0.4rem; }
  .course-desc { color: var(--muted); font-size: 0.9rem; line-height: 1.8; font-weight: 300; margin-bottom: 1.3rem; }
  .course-price { display: inline-flex; align-items: baseline; gap: 0.3rem; background: rgba(62,207,120,0.1); border: 1px solid rgba(62,207,120,0.25); border-radius: 10px; padding: 0.5rem 0.9rem; margin-bottom: 1.3rem; flex-direction: row-reverse; }
  .price-amount { font-family: 'Sora', sans-serif; font-size: 1.3rem; font-weight: 700; color: var(--green); }
  .price-period { font-size: 0.82rem; color: rgba(62,207,120,0.75); font-family: var(--font); }
  .course-divider { border: none; border-top: 1px solid var(--border); margin-bottom: 1.2rem; }
  .features-label { font-family: var(--font); font-size: 0.75rem; font-weight: 700; color: rgba(255,255,255,0.3); margin-bottom: 0.75rem; }
  .course-features { list-style: none; display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1.5rem; flex: 1; }
  .course-features li { display: flex; align-items: flex-start; gap: 0.65rem; font-size: 0.88rem; color: rgba(255,255,255,0.75); line-height: 1.6; flex-direction: row-reverse; text-align: right; }
  .course-features li::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--green); flex-shrink: 0; margin-top: 0.5rem; opacity: 0.9; }
  .course-tags { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: auto; flex-direction: row-reverse; }
  .tag { font-size: 0.78rem; font-family: var(--font); padding: 0.25rem 0.7rem; border-radius: 100px; background: rgba(255,255,255,0.07); border: 1px solid var(--border); color: var(--muted); }
  .enroll-section { background: var(--navy-mid); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 6rem 2rem; }
  .enroll-inner { max-width: 640px; margin: 0 auto; }
  .form-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 24px; padding: 2.5rem; margin-top: 3rem; }
  .selected-course-pill { display: inline-flex; align-items: center; gap: 0.5rem; background: var(--green-glow); border: 1px solid rgba(62,207,120,0.3); color: var(--green); font-size: 0.85rem; font-family: var(--font); padding: 0.35rem 0.9rem; border-radius: 100px; margin-bottom: 1.5rem; flex-direction: row-reverse; }
  .form-group { margin-bottom: 1.25rem; }
  .form-group label { display: block; font-family: var(--font); font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 0.5rem; text-align: right; }
  .form-group input { width: 100%; padding: 0.85rem 1rem; background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: 10px; color: var(--white); font-family: var(--font); font-size: 0.95rem; outline: none; transition: border-color 0.2s, background 0.2s; text-align: right; direction: rtl; }
  .form-group input::placeholder { color: rgba(255,255,255,0.25); }
  .form-group input:focus { border-color: var(--green); background: rgba(62,207,120,0.05); }
  .form-group select { width: 100%; padding: 0.85rem 1rem; background: rgba(255,255,255,0.05); border: 1px solid var(--border); border-radius: 10px; color: var(--white); font-family: var(--font); font-size: 0.95rem; outline: none; appearance: none; transition: border-color 0.2s; cursor: pointer; text-align: right; direction: rtl; }
  .form-group select option { background: var(--navy-mid); color: var(--white); }
  .form-group select:focus { border-color: var(--green); }
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  .btn-submit { width: 100%; padding: 1rem; background: var(--green); color: var(--navy); font-family: var(--font); font-weight: 700; font-size: 1rem; border: none; border-radius: 12px; cursor: pointer; transition: background 0.2s, transform 0.15s; margin-top: 0.5rem; }
  .btn-submit:hover { background: var(--green-dark); transform: translateY(-2px); }
  .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
  .form-notice { font-size: 0.82rem; color: var(--muted); text-align: center; margin-top: 0.8rem; line-height: 1.7; font-family: var(--font); }
  .success-msg { display: none; text-align: center; padding: 2rem; }
  .success-icon { width: 60px; height: 60px; border-radius: 50%; background: var(--green-glow); border: 2px solid var(--green); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.2rem; font-size: 1.5rem; }
  .success-msg h3 { font-family: var(--font); font-size: 1.3rem; font-weight: 700; margin-bottom: 0.5rem; }
  .success-msg p { color: var(--muted); font-size: 0.9rem; line-height: 1.8; font-family: var(--font); }
  .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-top: 3rem; }
  .step { padding: 1.8rem; border: 1px solid var(--border); border-radius: 16px; background: var(--card-bg); text-align: right; }
  .step-num { font-family: 'Sora', sans-serif; font-size: 0.75rem; font-weight: 600; color: var(--green); letter-spacing: 0.05em; margin-bottom: 1rem; }
  .step h3 { font-family: var(--font); font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem; }
  .step p { color: var(--muted); font-size: 0.88rem; line-height: 1.7; font-weight: 300; }
  footer { border-top: 1px solid var(--border); padding: 2.5rem 3rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; background: var(--navy-mid); flex-direction: row-reverse; }
  .footer-logo { font-family: var(--font); font-size: 0.9rem; font-weight: 700; color: var(--muted); }
  .footer-logo em { color: var(--green); font-style: normal; font-family: 'Sora', sans-serif; }
  .footer-links { display: flex; gap: 1.5rem; flex-direction: row-reverse; }
  .footer-links a { color: var(--muted); text-decoration: none; font-size: 0.85rem; transition: color 0.2s; font-family: var(--font); }
  .footer-links a:hover { color: var(--white); }
  .footer-copy { color: rgba(255,255,255,0.2); font-size: 0.78rem; width: 100%; text-align: center; font-family: var(--font); }
  .text-center { text-align: center; }
  .text-center .section-sub { margin: 0 auto; }
  #toast { position: fixed; bottom: 2rem; left: 2rem; z-index: 999; background: #1a2f21; border: 1px solid var(--green); color: var(--white); font-family: var(--font); font-size: 0.85rem; padding: 0.8rem 1.2rem; border-radius: 12px; transform: translateY(100px); opacity: 0; transition: all 0.35s; max-width: 300px; direction: rtl; }
  #toast.show { transform: translateY(0); opacity: 1; }
  .hamburger { display: none; flex-direction: column; justify-content: center; align-items: center; gap: 5px; width: 36px; height: 36px; border-radius: 9px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); cursor: pointer; z-index: 200; flex-shrink: 0; }
  .hamburger span { display: block; width: 18px; height: 2px; background: var(--white); border-radius: 2px; transition: all 0.3s; }
  .hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
  .hamburger.open span:nth-child(2) { opacity: 0; }
  .hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }
  .nav-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 150; pointer-events: none; }
  .nav-backdrop.open { display: block; pointer-events: auto; }
  @media (max-width: 600px) {
    nav { padding: 1rem 1.2rem; }
    .nav-lang { display: none !important; }
    .hamburger { display: flex; }
    .nav-links { display: none; flex-direction: column; position: fixed; top: 0; left: 0; height: 100vh; width: 75vw; max-width: 280px; background: var(--navy-mid); border-right: 1px solid var(--border); padding: 5rem 2rem 2rem; gap: 1.5rem; z-index: 180; transform: translateX(-100%); transition: transform 0.3s ease; }
    .nav-links.open { display: flex !important; transform: translateX(0); }
    .nav-links a { font-size: 1rem; color: var(--white); text-align: right; }
    .nav-cta { text-align: center; }
    .form-grid { grid-template-columns: 1fr; }
    footer { flex-direction: column; }
  }
  .hero { background-image: url('assets/img/2.png'); background-size: cover; background-position: center 30%; background-repeat: no-repeat; }
  .hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(15,29,46,0.72) 0%, rgba(15,29,46,0.60) 50%, rgba(15,29,46,0.88) 100%); pointer-events: none; z-index: 0; }
  .hero > * { position: relative; z-index: 1; }
  .photo-strip { padding: 4rem 2rem; background: var(--navy-mid); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
  .photo-strip-inner { max-width: 1100px; margin: 0 auto; }
  .photo-strip-header { text-align: center; margin-bottom: 2rem; }
  .photo-strip-header .section-label { display: block; }
  .photo-strip-header .section-title { font-family: var(--font); font-size: clamp(1.4rem, 2.5vw, 2rem); font-weight: 700; margin-bottom: 0; }
  .photos-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; grid-template-rows: 220px 220px; gap: 1rem; }
  .photo-item { border-radius: 16px; overflow: hidden; position: relative; background: var(--navy-light); }
  .photo-item img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.5s ease, filter 0.4s ease; filter: brightness(0.88) saturate(0.9); }
  .photo-item:hover img { transform: scale(1.06); filter: brightness(1) saturate(1); }
  .photo-item::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, rgba(15,29,46,0.45) 0%, transparent 50%); pointer-events: none; }
  .photo-item.tall { grid-row: span 2; }
  .photo-item-caption { position: absolute; bottom: 0.75rem; right: 0.9rem; font-family: var(--font); font-size: 0.75rem; font-weight: 500; color: rgba(255,255,255,0.75); z-index: 2; }
  @media (max-width: 768px) { .photos-grid { grid-template-columns: 1fr 1fr; grid-template-rows: auto; } .photo-item.tall { grid-row: span 1; height: 180px; } .photo-item { height: 180px; } }
  @media (max-width: 500px) { .photos-grid { grid-template-columns: 1fr; } .photo-item, .photo-item.tall { height: 200px; } }
</style>
</head>
<body>

<nav>
  <div style="display:flex;align-items:center;gap:0.75rem;flex-direction:row-reverse;">
    <a href="index-ar.php" class="nav-logo">
      <svg viewBox="0 0 160 50" fill="none" xmlns="http://www.w3.org/2000/svg">
        <text x="36" y="36" font-family="Sora, sans-serif" font-size="28" font-weight="700" fill="white">pskill</text>
        <path d="M14 12 Q14 28 26 28 Q38 28 38 12" stroke="#3ecf78" stroke-width="3.5" fill="none" stroke-linecap="round"/>
        <path d="M22 6 L26 2 L30 6" stroke="#3ecf78" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="26" y1="2" x2="26" y2="18" stroke="#3ecf78" stroke-width="3.5" stroke-linecap="round"/>
      </svg>
      <span><em>Up</em>skill Education</span>
    </a>
    <a href="index-en.php" class="nav-lang" style="display:inline-flex;align-items:center;gap:0.3rem;background:transparent;border:1px solid var(--border);color:var(--muted);font-weight:500;font-family:var(--font);font-size:0.73rem;padding:0.3rem 0.75rem;border-radius:100px;text-decoration:none;">🇬🇧 EN</a>
    <a href="index-fr.php" class="nav-lang" style="display:inline-flex;align-items:center;gap:0.3rem;background:transparent;border:1px solid var(--border);color:var(--muted);font-weight:500;font-family:var(--font);font-size:0.73rem;padding:0.3rem 0.75rem;border-radius:100px;text-decoration:none;">🇫🇷 FR</a>
    <a href="index-ar.php" class="nav-lang" style="display:inline-flex;align-items:center;gap:0.3rem;background:var(--green-glow);border:1px solid rgba(62,207,120,0.4);color:var(--green);font-weight:600;font-family:var(--font);font-size:0.73rem;padding:0.3rem 0.75rem;border-radius:100px;text-decoration:none;">🇲🇦 عربي</a>
  </div>
  <button class="hamburger" id="hamburger" aria-label="فتح القائمة">
    <span></span><span></span><span></span>
  </button>
  <div class="nav-links" id="nav-links">
    <a target="_blank" rel="noopener noreferrer" href="https://study.upskill-edu.com/index2-ar.php" class="nav-cta">← بوابة الطلاب</a>
    <a href="#enroll">التسجيل</a>
    <a href="#how">كيف يعمل</a>
    <a href="#courses">الدورات</a>
    <div style="border-top:1px solid var(--border);padding-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;justify-content:flex-end;">
      <a href="index-en.php" style="display:inline-flex;align-items:center;gap:0.3rem;background:transparent;border:1px solid var(--border);color:var(--muted);font-family:var(--font);font-size:0.73rem;padding:0.3rem 0.75rem;border-radius:100px;text-decoration:none;">🇬🇧 EN</a>
      <a href="index-fr.php" style="display:inline-flex;align-items:center;gap:0.3rem;background:transparent;border:1px solid var(--border);color:var(--muted);font-family:var(--font);font-size:0.73rem;padding:0.3rem 0.75rem;border-radius:100px;text-decoration:none;">🇫🇷 FR</a>
      <a href="index-ar.php" style="display:inline-flex;align-items:center;gap:0.3rem;background:var(--green-glow);border:1px solid rgba(62,207,120,0.4);color:var(--green);font-family:var(--font);font-size:0.73rem;padding:0.3rem 0.75rem;border-radius:100px;text-decoration:none;">🇲🇦 عربي</a>
    </div>
  </div>
</nav>
<div class="nav-backdrop" id="nav-backdrop"></div>



<div class="hero" id="home">
  <div class="hero-badge"><span></span> التسجيل مفتوح الآن — 2026</div>
  <h1>أتقن الإنجليزية.<br><span>افتح كل الأبواب.</span></h1>
  <p>دورات إنجليزية يقودها خبراء متخصصون — سواء كنت تبني مسيرتك المهنية، تستعد للباكالوريا، أو ترغب في تطوير نفسك.</p>
  <div class="hero-actions">
    <a target="_blank" rel="noopener noreferrer" href="https://study.upskill-edu.com/index2-ar.php" class="btn-outline">← بوابة الطلاب</a>
    <a href="#courses" class="btn-primary">استكشف الدورات</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat-num">3<span>+</span></div><div class="stat-label">برامج متخصصة</div></div>
  <div class="stat"><div class="stat-num">599<span> د.م</span></div><div class="stat-label">لكل شهرين ونصف</div></div>
  <div class="stat"><div class="stat-num">30<span>س</span></div><div class="stat-label">ساعة تعليمية</div></div>
  <div class="stat"><div class="stat-num">9–12</div><div class="stat-label">طالب لكل فصل</div></div>
</div>

<div class="photo-strip">
  <div class="photo-strip-inner">
    <div class="photo-strip-header">
      <span class="section-label">التعلم من أي مكان</span>
      <div class="section-title">فصلك الدراسي، في أي مكان في العالم.</div>
    </div>
    <div class="photos-grid">
      <div class="photo-item tall">
        <img src="assets/img/2.png" alt="طلاب في درس أونلاين" loading="lazy">
        <span class="photo-item-caption">جلسات مباشرة</span>
      </div>
      <div class="photo-item">
        <img src="assets/img/3.png" alt="مجموعة طلاب" loading="lazy">
        <span class="photo-item-caption">تعلّم من أي مكان</span>
      </div>
      <div class="photo-item">
        <img src="assets/img/1.png" alt="طالب يتعلم على الحاسوب" loading="lazy">
        <span class="photo-item-caption">أساتذة متخصصون</span>
      </div>
      <div class="photo-item">
        <img src="assets/img/4.png" alt="درس على الشاشة" loading="lazy">
        <span class="photo-item-caption">دراسة مركّزة</span>
      </div>
      <div class="photo-item">
        <img src="assets/img/5.png" alt="مجموعات صغيرة" loading="lazy">
        <span class="photo-item-caption">مجموعات صغيرة</span>
      </div>
    </div>
  </div>
</div>

<section id="courses">
  <div class="courses-header">
    <div class="section-label">برامجنا</div>
    <div class="section-title">اختر الدورة<br>المناسبة لك.</div>
    <p class="section-sub">ثلاثة برامج متخصصة، كل منها مصمم لهدف محدد. اختر ما يناسب طموحاتك.</p>
  </div>
  <div class="courses-grid">

    <div class="course-card" data-course="إنجليزية الأعمال" onclick="selectCourse(this)">
      <div class="course-header">
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <div class="course-icon">💼</div>
      </div>
      <div class="course-title">إنجليزية الأعمال</div>
      <div class="course-desc">تواصل بثقة في البيئات المهنية. أتقن كتابة الإيميلات والعروض التقديمية والمفاوضات والمحادثات في مكان العمل.</div>
      <div class="course-price"><span class="price-period">/ حصة · ~2.5 شهر</span><span class="price-amount">599 د.م</span></div>
      <hr class="course-divider">
      <div class="features-label">ما يتضمنه البرنامج</div>
      <ul class="course-features">
        <li>~29 ساعة تعليمية (حصتان أسبوعيًا، كل حصة 90 دقيقة)</li>
        <li>دورات تواصل مركّزة على مهارات الكلام</li>
        <li>أساتذة ذوو كفاءة عالية</li>
        <li>من 9 إلى 12 طالبًا لكل فصل</li>
        <li>تعلّم من أي مكان دون الحاجة للتنقل</li>
        <li>تمارين وواجبات واختبارات</li>
        <li>مسابقات وتحديات</li>
        <li>شهادة عند إتمام الدورة</li>
      </ul>
      <div class="course-tags"><span class="tag">التواصل المهني</span><span class="tag">العروض التقديمية</span><span class="tag">الكتابة</span><span class="tag">التفاوض</span></div>
    </div>

    <div class="course-card" data-course="الإنجليزية العامة" onclick="selectCourse(this)">
      <div class="course-header">
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <div class="course-icon">🌍</div>
      </div>
      <div class="course-title">الإنجليزية العامة</div>
      <div class="course-desc">أسّس قاعدة متينة في الكلام والاستماع والقراءة والكتابة. مثالي لاكتساب الطلاقة والثقة في الحياة اليومية لجميع المستويات.</div>
      <div class="course-price"><span class="price-period">/ حصة · ~2.5 شهر</span><span class="price-amount">599 د.م</span></div>
      <hr class="course-divider">
      <div class="features-label">ما يتضمنه البرنامج</div>
      <ul class="course-features">
        <li>~29 ساعة تعليمية (حصتان أسبوعيًا، كل حصة 90 دقيقة)</li>
        <li>دورات تواصل مركّزة على مهارات الكلام</li>
        <li>أساتذة ذوو كفاءة عالية</li>
        <li>من 9 إلى 12 طالبًا لكل فصل</li>
        <li>تعلّم من أي مكان دون الحاجة للتنقل</li>
        <li>تمارين وواجبات واختبارات</li>
        <li>مسابقات وتحديات</li>
        <li>شهادة عند إتمام الدورة</li>
      </ul>
      <div class="course-tags"><span class="tag">جميع المستويات</span><span class="tag">المحادثة</span><span class="tag">القواعد</span><span class="tag">المفردات</span></div>
    </div>

    <div class="course-card" data-course="إنجليزية الباكالوريا" onclick="selectCourse(this)">
      <div class="course-header">
        <div class="course-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7L5.5 10.5L12 4" stroke="#0f1d2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <div class="course-icon">🎓</div>
      </div>
      <div class="course-title">إنجليزية الباكالوريا</div>
      <div class="course-desc">تحضير مكثف لامتحانات الباكالوريا. تدرّب على أوراق الامتحانات السابقة والمقالات المؤقتة وتمارين الفهم وتقنيات الشفهي.</div>
      <div class="course-price"><span class="price-period">/ حصة · ~3 أشهر</span><span class="price-amount">599 د.م</span></div>
      <hr class="course-divider">
      <div class="features-label">ما يتضمنه البرنامج</div>
      <ul class="course-features">
        <li>~30 ساعة تعليمية (حصتان أسبوعيًا، كل حصة 90 دقيقة)</li>
        <li>دورات تواصل مركّزة على مهارات الكلام</li>
        <li>أساتذة ذوو كفاءة عالية</li>
        <li>من 10 إلى 14 طالبًا لكل فصل</li>
        <li>تعلّم من أي مكان دون الحاجة للتنقل</li>
        <li>تمارين وواجبات واختبارات</li>
        <li>مسابقات وتحديات</li>
      </ul>
      <div class="course-tags"><span class="tag">التحضير للامتحان</span><span class="tag">أوراق سابقة</span><span class="tag">الشفهي</span><span class="tag">الكتابة</span></div>
    </div>

  </div>
</section>

<section id="how" style="background: var(--navy-mid); max-width: 100%; padding: 6rem 2rem;">
  <div style="max-width: 1100px; margin: 0 auto;">
    <div class="section-label text-center" style="text-align:center;">المسار</div>
    <div class="section-title text-center" style="text-align:center;">كيف يعمل</div>
    <p class="section-sub" style="margin: 0 auto; text-align:center;">من التسجيل إلى الطلاقة — إليك رحلتك.</p>
    <div class="steps">
      <div class="step"><div class="step-num">01</div><h3>اختر دورتك</h3><p>تصفح برامجنا الثلاثة واختر ما يناسب أهدافك ومستواك الحالي.</p></div>
      <div class="step"><div class="step-num">02</div><h3>أرسل بياناتك</h3><p>أدخل اسمك وبريدك الإلكتروني ورقم هاتفك. سيتواصل معك فريقنا لتأكيد تسجيلك.</p></div>
      <div class="step"><div class="step-num">03</div><h3>احصل على حسابك</h3><p>ستتلقى بيانات تسجيل الدخول الخاصة بك على المنصة من فريق الإدارة.</p></div>
      <div class="step"><div class="step-num">04</div><h3>ابدأ التعلم</h3><p>سجّل الدخول إلى لوحة التحكم، وصل إلى المواد والواجبات وتابع تقدمك مباشرة.</p></div>
    </div>
  </div>
</section>

<div class="enroll-section" id="enroll">
  <div class="enroll-inner">
    <div class="section-label text-center" style="text-align:center;">التسجيل</div>
    <div class="section-title text-center" style="text-align:center;">هل أنت مستعد للبدء؟</div>
    <p class="section-sub" style="margin:0 auto;text-align:center;">أدخل بياناتك وسنتواصل معك خلال 24 ساعة لتأكيد مكانك.</p>
    <div class="form-card">
      <div id="form-area">
        <div id="selected-pill" style="display:none;" class="selected-course-pill">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M1.5 6L4.5 9L10.5 3" stroke="#3ecf78" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span id="pill-text">إنجليزية الأعمال</span>
        </div>
        <div class="form-group">
          <label>اختر الدورة</label>
          <select id="course-select" required>
            <option value="">— اختر برنامجًا —</option>
            <option value="إنجليزية الأعمال">إنجليزية الأعمال</option>
            <option value="الإنجليزية العامة">الإنجليزية العامة</option>
            <option value="إنجليزية الباكالوريا">إنجليزية الباكالوريا</option>
          </select>
        </div>
        <div class="form-group">
          <label>الاسم الكامل</label>
          <input type="text" id="name" placeholder="أدخل اسمك الكامل" required>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>البريد الإلكتروني</label>
            <input type="email" id="email" placeholder="you@example.com" required dir="ltr" style="text-align:left;">
          </div>
          <div class="form-group">
            <label>رقم الهاتف</label>
            <input type="tel" id="phone" placeholder="+212 6XX XXX XXX" required dir="ltr" style="text-align:left;">
          </div>
        </div>
        <button class="btn-submit" onclick="submitForm()">إرسال طلب التسجيل ←</button>
        <p class="form-notice">معلوماتك تُحفظ بأمان وتُرسل مباشرة إلى فريق الإدارة. سنتواصل معك خلال 24 ساعة.</p>
      </div>
      <div class="success-msg" id="success-msg">
        <div class="success-icon">✓</div>
        <h3>تم استلام طلبك!</h3>
        <p>شكرًا لتسجيلك. سيراجع فريقنا طلبك ويتواصل معك خلال ٢٤ ساعة لتأكيد مكانك وإرسال بيانات تسجيل الدخول الخاصة بك.</p>
      </div>
    </div>
  </div>
</div>



<footer>
  <div class="footer-logo"><em>Up</em>skill Education</div>
  <div class="footer-links">
    <a href="#" onclick="openContact();return false;">تواصل معنا</a>
    <a target="_blank" rel="noopener noreferrer" href="https://study.upskill-edu.com/index2-ar.php">بوابة الطلاب</a>
    <a href="#enroll">التسجيل</a>
    <a href="#courses">الدورات</a>
  </div>
  <div class="footer-copy">© 2026 Upskill Education · upskill-edu.com · جميع الحقوق محفوظة</div>
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

    if (!course) { showToast('يرجى اختيار دورة أولاً.'); return; }
    if (!name)   { showToast('يرجى إدخال اسمك الكامل.'); return; }
    if (!email || !email.includes('@')) { showToast('يرجى إدخال بريد إلكتروني صالح.'); return; }
    if (!phone)  { showToast('يرجى إدخال رقم هاتفك.'); return; }

    const btn = document.querySelector('.btn-submit');
    btn.disabled = true;
    btn.textContent = 'جارٍ الإرسال…';

    try {
      const body = new URLSearchParams({ csrf_token: CSRF_TOKEN, name, email, phone, course, lang: 'ar' });
      const res  = await fetch('/enroll.php', { method: 'POST', body });
      const data = await res.json();

      if (data.ok) {
        document.getElementById('form-area').style.display = 'none';
        document.getElementById('success-msg').style.display = 'block';
      } else {
        showToast(data.error || 'حدث خطأ. يرجى المحاولة مرة أخرى.');
        btn.disabled = false;
        btn.textContent = 'إرسال طلب التسجيل ←';
      }
    } catch (err) {
      showToast('خطأ في الشبكة. تحقق من اتصالك وأعد المحاولة.');
      btn.disabled = false;
      btn.textContent = 'إرسال طلب التسجيل ←';
    }
  }
</script>

<!-- ── CONTACT MODAL ── -->
<div id="contact-overlay" onclick="if(event.target===this)closeContact()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#162436;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:2rem;width:100%;max-width:480px;margin:1rem;position:relative;direction:rtl;">
    <button onclick="closeContact()" style="position:absolute;top:1rem;left:1rem;background:none;border:none;color:rgba(255,255,255,.4);font-size:1.3rem;cursor:pointer;line-height:1;">✕</button>
    <div style="font-family:'Cairo',sans-serif;font-size:1.2rem;font-weight:700;margin-bottom:.3rem;">تواصل معنا</div>
    <div style="color:rgba(255,255,255,.55);font-size:.88rem;margin-bottom:1.5rem;font-family:'Cairo',sans-serif;">أرسل لنا رسالة وسنرد عليك خلال 24 ساعة.</div>
    <div style="margin-bottom:1rem;">
      <label style="display:block;font-family:'Cairo',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">الاسم الكامل</label>
      <input id="c-name" type="text" placeholder="اسمك الكامل" maxlength="120"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'Cairo',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;text-align:right;">
    </div>
    <div style="margin-bottom:1rem;">
      <label style="display:block;font-family:'Cairo',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">البريد الإلكتروني</label>
      <input id="c-email" type="email" placeholder="example@mail.com" maxlength="180"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'Cairo',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;text-align:right;">
    </div>
    <div style="margin-bottom:1rem;">
      <label style="display:block;font-family:'Cairo',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">
        رقم الهاتف <span style="font-weight:400;font-size:.7rem;color:rgba(255,255,255,.35);text-transform:none;letter-spacing:0;">(اختياري)</span>
      </label>
      <input id="c-phone" type="tel" placeholder="مثال: 0612345678" maxlength="30"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'Cairo',sans-serif;font-size:.95rem;outline:none;box-sizing:border-box;text-align:right;">
    </div>
    <div style="margin-bottom:1.5rem;">
      <label style="display:block;font-family:'Cairo',sans-serif;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;letter-spacing:.04em;text-transform:uppercase;">رسالتك</label>
      <textarea id="c-msg" placeholder="اكتب رسالتك هنا…" maxlength="2000" rows="4"
        style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;font-family:'Cairo',sans-serif;font-size:.95rem;outline:none;resize:vertical;box-sizing:border-box;text-align:right;"></textarea>
    </div>
    <div id="c-error" style="display:none;color:#f87171;font-size:.83rem;margin-bottom:.8rem;font-family:'Cairo',sans-serif;"></div>
    <button id="c-btn" onclick="sendContact()"
      style="width:100%;padding:.9rem;background:#3ecf78;color:#0f1d2e;font-family:'Cairo',sans-serif;font-weight:700;font-size:.95rem;border:none;border-radius:12px;cursor:pointer;transition:background .2s;">
      إرسال الرسالة ←
    </button>
    <div id="c-success" style="display:none;text-align:center;padding:1.5rem 0;">
      <div style="font-size:2.5rem;margin-bottom:.5rem;">✅</div>
      <div style="font-family:'Cairo',sans-serif;font-weight:600;margin-bottom:.3rem;">تم إرسال رسالتك!</div>
      <div style="color:rgba(255,255,255,.55);font-size:.88rem;font-family:'Cairo',sans-serif;">سنرد عليك قريباً.</div>
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
  document.getElementById('c-btn').textContent = 'إرسال الرسالة ←';
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

  if (!name)  { errEl.textContent = 'يرجى إدخال اسمك.'; errEl.style.display = ''; return; }
  if (!email || !email.includes('@')) { errEl.textContent = 'بريد إلكتروني غير صالح.'; errEl.style.display = ''; return; }
  if (!msg)   { errEl.textContent = 'يرجى كتابة رسالتك.'; errEl.style.display = ''; return; }

  const btn = document.getElementById('c-btn');
  btn.disabled = true; btn.textContent = '…جارٍ الإرسال';

  try {
    const body = new URLSearchParams({ csrf_token: CSRF_TOKEN, name, email, phone, message: msg, lang: 'ar' });
    const res  = await fetch('/contact.php', { method: 'POST', body });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('c-success').style.display = '';
      btn.style.display = 'none';
    } else {
      errEl.textContent = data.error || 'حدث خطأ. يرجى المحاولة مرة أخرى.';
      errEl.style.display = '';
      btn.disabled = false; btn.textContent = 'إرسال الرسالة ←';
    }
  } catch(e) {
    errEl.textContent = 'خطأ في الشبكة. تحقق من اتصالك.';
    errEl.style.display = '';
    btn.disabled = false; btn.textContent = 'إرسال الرسالة ←';
  }
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeContact(); closeNav(); } });
document.addEventListener('DOMContentLoaded', function() {
  var hamburger = document.getElementById('hamburger');
  var navLinks  = document.getElementById('nav-links');
  var backdrop  = document.getElementById('nav-backdrop');
  if (hamburger) {
    hamburger.addEventListener('click', function() {
      var isOpen = navLinks.style.display === 'flex';
      navLinks.style.display   = isOpen ? 'none' : 'flex';
      navLinks.style.transform = isOpen ? 'translateX(-100%)' : 'translateX(0)';
      backdrop.style.display   = isOpen ? 'none' : 'block';
    });
  }
  if (backdrop) {
    backdrop.addEventListener('click', function() { closeNav(); });
  }
  document.querySelectorAll('#nav-links a[href^="#"]').forEach(function(a) {
    a.addEventListener('click', function() { closeNav(); });
  });
});
function closeNav() {
  var navLinks  = document.getElementById('nav-links');
  var backdrop  = document.getElementById('nav-backdrop');
  if (navLinks) { navLinks.style.display = 'none'; navLinks.style.transform = 'translateX(-100%)'; }
  if (backdrop) { backdrop.style.display = 'none'; }
}
</script>

</body>
</html>
