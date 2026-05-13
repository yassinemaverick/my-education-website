<?php
require_once __DIR__ . '/session.php';
$_error = htmlspecialchars($_GET['error'] ?? '', ENT_QUOTES);
$_role  = htmlspecialchars($_GET['role']  ?? 'student', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upskill Platform – Sign In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --navy: #0f1d2e;
    --navy-mid: #162436;
    --navy-light: #1e3248;
    --green: #3ecf78;
    --green-dark: #28a85c;
    --green-glow: rgba(62,207,120,0.12);
    --white: #ffffff;
    --muted: rgba(255,255,255,0.5);
    --border: rgba(255,255,255,0.1);
    --font: 'Sora', sans-serif;
    --font-body: 'DM Sans', sans-serif;
  }

  body {
    background: var(--navy);
    color: var(--white);
    font-family: var(--font-body);
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1fr 1fr;
    overflow: hidden;
  }

  /* LEFT PANEL */
  .left {
    background: var(--navy-mid);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    justify-content: space-between;
    padding: 2.5rem 3rem;
    position: relative; overflow: hidden;
  }
  .left::before {
    content: '';
    position: absolute; bottom: -100px; left: -100px;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(62,207,120,0.1) 0%, transparent 65%);
    pointer-events: none;
  }

  .left-logo { display: flex; align-items: center; gap: 0.6rem; }
  .left-logo span { font-family: var(--font); font-weight: 600; font-size: 1.05rem; }
  .left-logo em { color: var(--green); font-style: normal; }

  .left-content { flex: 1; display: flex; flex-direction: column; justify-content: center; max-width: 380px; }
  .left-content h1 {
    font-family: var(--font); font-size: 2.4rem; font-weight: 700;
    line-height: 1.15; letter-spacing: -0.03em; margin-bottom: 1.2rem;
  }
  .left-content h1 span { color: var(--green); }
  .left-content p { color: var(--muted); font-size: 0.95rem; line-height: 1.7; font-weight: 300; margin-bottom: 2.5rem; }

  .feature-list { display: flex; flex-direction: column; gap: 0.9rem; }
  .feature-item { display: flex; align-items: center; gap: 0.75rem; }
  .feature-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--green); flex-shrink: 0; }
  .feature-item span { color: rgba(255,255,255,0.7); font-size: 0.88rem; }

  .left-bottom { color:rgba(255,255,255,0.50); font-size: 0.75rem; }
  .left-bottom a { color:rgba(255,255,255,0.50); text-decoration: none; }
  .left-bottom a:hover { color: var(--muted); }

  /* RIGHT PANEL */
  .right {
    display: flex; align-items: center; justify-content: center;
    padding: 2rem;
  }

  .login-box {
    width: 100%; max-width: 420px;
  }

  .role-tabs {
    display: grid; grid-template-columns: 1fr 1fr;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    border-radius: 14px; padding: 4px;
    margin-bottom: 2rem;
  }
  .role-tab {
    padding: 0.65rem; text-align: center;
    font-family: var(--font); font-size: 0.85rem; font-weight: 500;
    color: var(--muted); cursor: pointer; border-radius: 11px;
    transition: all 0.2s; user-select: none;
  }
  .role-tab.active {
    background: var(--navy-light);
    color: var(--white);
    border: 1px solid var(--border);
  }

  .login-title { font-family: var(--font); font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; letter-spacing: -0.02em; }
  .login-subtitle { color: var(--muted); font-size: 0.85rem; margin-bottom: 2rem; }
  .login-subtitle span { color: var(--green); }

  .form-group { margin-bottom: 1.1rem; }
  .form-group label {
    display: block; font-family: var(--font); font-size: 0.75rem; font-weight: 600;
    color: rgba(255,255,255,0.45); letter-spacing: 0.07em; text-transform: uppercase;
    margin-bottom: 0.45rem;
  }
  .input-wrap { position: relative; }
  .input-wrap > svg:first-child { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
  .form-group input {
    width: 100%; padding: 0.85rem 2.8rem 0.85rem 2.6rem;
    background: rgba(255,255,255,0.05); border: 1px solid var(--border);
    border-radius: 10px; color: var(--white); font-family: var(--font-body);
    font-size: 0.95rem; outline: none;
    transition: border-color 0.2s, background 0.2s;
  }
  .form-group input::placeholder { color:rgba(255,255,255,0.50); }
  .form-group input:focus { border-color: rgba(62,207,120,0.5); background: rgba(62,207,120,0.04); }
  .form-group input.input-error { border-color: rgba(255,100,100,0.6); }

  .password-toggle {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    cursor: pointer; color: var(--muted); transition: color 0.2s;
    background: none; border: none; padding: 0.15rem;
    display: flex; align-items: center; justify-content: center; border-radius: 4px;
  }
  .password-toggle:hover { color: var(--white); }
  .password-toggle svg { pointer-events: none; display: block; }
  .eye-open  { display: block; }
  .eye-closed { display: none; }
  .pwd-visible .eye-open  { display: none; }
  .pwd-visible .eye-closed { display: block; }

  .form-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
  .checkbox-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
  .checkbox-label input { display: none; }
  .checkbox-box {
    width: 16px; height: 16px; border-radius: 4px;
    border: 1px solid var(--border); background: rgba(255,255,255,0.05);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    transition: all 0.2s;
  }
  .checkbox-label input:checked + .checkbox-box { background: var(--green); border-color: var(--green); }
  .checkbox-label input:checked + .checkbox-box::after { content: '✓'; color: var(--navy); font-size: 10px; font-weight: 700; }
  .checkbox-label span { font-size: 0.83rem; color: var(--muted); }
  .forgot { color: var(--green); font-size: 0.83rem; text-decoration: none; opacity: 0.85; }
  .forgot:hover { opacity: 1; }

  .btn-login {
    width: 100%; padding: 0.9rem; background: var(--green);
    color: var(--navy); font-family: var(--font); font-weight: 700;
    font-size: 0.95rem; border: none; border-radius: 12px; cursor: pointer;
    transition: background 0.2s, transform 0.15s; letter-spacing: -0.01em;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
  }
  .btn-login:hover { background: var(--green-dark); transform: translateY(-2px); }
  .btn-login:active { transform: translateY(0); }

  .btn-login .spinner {
    width: 16px; height: 16px; border: 2px solid rgba(15,29,46,0.3);
    border-top-color: var(--navy); border-radius: 50%;
    animation: spin 0.7s linear infinite; display: none;
  }
  .btn-login.loading .btn-text { display: none; }
  .btn-login.loading .spinner { display: block; }

  @keyframes spin { to { transform: rotate(360deg); } }

  .error-msg {
    background: rgba(255,80,80,0.1); border: 1px solid rgba(255,80,80,0.25);
    border-radius: 10px; padding: 0.7rem 1rem;
    font-size: 0.83rem; color: #ff8080;
    margin-bottom: 1rem; display: none;
  }

  .divider { display: flex; align-items: center; gap: 0.75rem; margin: 1.5rem 0; }
  .divider hr { flex: 1; border: none; border-top: 1px solid var(--border); }
  .divider span { color: var(--muted); font-size: 0.78rem; }

  .back-link { text-align: center; margin-top: 1.5rem; }
  .back-link a { color: var(--muted); font-size: 0.83rem; text-decoration: none; transition: color 0.2s; }
  .back-link a:hover { color: var(--white); }
  .back-link a span { color: var(--green); }

  /* Decorative grid */
  .grid-bg {
    position: fixed; inset: 0; pointer-events: none; opacity: 0.04;
    background-image: linear-gradient(var(--white) 1px, transparent 1px), linear-gradient(90deg, var(--white) 1px, transparent 1px);
    background-size: 40px 40px;
  }

  @media (max-width: 768px) {
    body { grid-template-columns: 1fr; }
    .left { display: none; }
    .right { padding: 3rem 1.5rem; align-items: flex-start; padding-top: 4rem; }
  }
</style>
</head>
<body>
<a href="#login-section" class="skip-link" style="position:absolute;top:-40px;left:0;background:#3ecf78;color:#0f1d2e;padding:.5rem 1rem;font-weight:700;font-size:.85rem;z-index:9999;border-radius:0 0 8px 0;text-decoration:none;">Aller au formulaire</a>

<div class="grid-bg"></div>

<!-- LEFT PANEL -->
<div class="left" role="complementary" aria-hidden="false">
  <div class="left-logo">
    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
      <path d="M8 6 Q8 20 16 20 Q24 20 24 6" stroke="#3ecf78" stroke-width="2.5" fill="none" stroke-linecap="round"/>
      <path d="M13 3 L16 0 L19 3" stroke="#3ecf78" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
      <line x1="16" y1="0" x2="16" y2="11" stroke="#3ecf78" stroke-width="2.5" stroke-linecap="round"/>
    </svg>
    <span><em>Up</em>skill Education</span>
  </div>

  <div class="left-content">
    <h1>Your learning<br>journey<br><span>starts here.</span></h1>
    <p>Access your personalised dashboard, assignments, whiteboards and progress tracking — all in one place.</p>
    <div class="feature-list">
      <div class="feature-item"><div class="feature-dot"></div><span>Course resources &amp; materials</span></div>
      <div class="feature-item"><div class="feature-dot"></div><span>Interactive whiteboard access</span></div>
      <div class="feature-item"><div class="feature-dot"></div><span>Assignments &amp; quizzes</span></div>
      <div class="feature-item"><div class="feature-dot"></div><span>Live progress tracking</span></div>
      <div class="feature-item"><div class="feature-dot"></div><span>Teacher-managed content</span></div>
    </div>
  </div>

  <div class="left-bottom">
    <a href="https://upskill-edu.com/index-fr.php">← Back to main site</a> &nbsp;·&nbsp; © 2026 Upskill Education
  </div>
</div>

<!-- RIGHT PANEL -->
<div class="right" role="main" id="login-section">
  <div class="login-box">

    <!-- Language switcher -->
    <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-bottom:1.2rem;">
      <a href="index2.php"    style="display:inline-flex;align-items:center;gap:0.3rem;background:var(--green-glow);border:1px solid rgba(62,207,120,0.4);color:var(--green);font-family:var(--font);font-size:0.73rem;font-weight:600;padding:0.3rem 0.75rem;border-radius:100px;text-decoration:none;">🇬🇧 EN</a>
      <a href="index2-fr.php" style="display:inline-flex;align-items:center;gap:0.3rem;background:transparent;border:1px solid var(--border);color:var(--muted);font-family:var(--font);font-size:0.73rem;font-weight:500;padding:0.3rem 0.75rem;border-radius:100px;text-decoration:none;transition:all 0.2s;">🇫🇷 FR</a>
      <a href="index2-ar.php" style="display:inline-flex;align-items:center;gap:0.3rem;background:transparent;border:1px solid var(--border);color:var(--muted);font-family:var(--font);font-size:0.73rem;font-weight:500;padding:0.3rem 0.75rem;border-radius:100px;text-decoration:none;transition:all 0.2s;">🇲🇦 عربي</a>
    </div>

    <!-- Role tabs -->
    <div class="role-tabs" role="tab" role="tablist" aria-label="Sélectionner votre rôle">
      <div class="role-tab active" role="tab" id="tab-student" onclick="setRole('student', this)">👩‍🎓 &nbsp;Student</div>
      <div class="role-tab" role="tab" id="tab-teacher" onclick="setRole('teacher', this)">👨‍🏫 &nbsp;Teacher</div>
    </div>

    <div class="login-title" id="login-title">Welcome back</div>
    <div class="login-subtitle" id="login-subtitle">Sign in to your <span>student</span> account</div>

    <div class="error-msg" id="error-msg" role="alert" aria-live="polite" style="display:none;">
      <span id="error-icon">⚠</span> <span id="error-text"></span>
    </div>

    <form id="login-form" action="login.php" method="POST" onsubmit="return handleSubmit(event)">
    <input type="hidden" name="role" id="role-input" value="student">
    <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
    <input type="hidden" name="lang" value="en">
    <div class="form-group">
      <label>Username</label>
      <div class="input-wrap">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
        <input type="text" id="username" aria-required="true" name="username" placeholder="Your username" autocomplete="username">
      </div>
    </div>

    <div class="form-group">
      <label>Password</label>
      <div class="input-wrap">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        <input type="password" id="password" aria-required="true" name="password" placeholder="Your password" autocomplete="current-password">
        <button class="password-toggle" type="button" id="pwd-toggle" onclick="togglePwd()" aria-label="Toggle password visibility">
          <svg class="eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
          </svg>
          <svg class="eye-closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
            <line x1="1" y1="1" x2="23" y2="23"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="form-row">
      <label class="checkbox-label">
        <input type="checkbox" id="remember">
        <div class="checkbox-box"></div>
        <span>Remember me</span>
      </label>
      <a href="mailto:Admin@upskill-edu.com?subject=Password Reset Request" class="forgot">Forgot password?</a>
    </div>

    <button class="btn-login" id="login-btn" type="submit">
      <span class="btn-text">Sign in →</span>
      <div class="spinner"></div>
    </button>
    </form>

    <div class="back-link">
      <a href="https://upskill-edu.com/index-fr.php">← Back to <span>upskill-edu.com</span></a>
    </div>

  </div>
</div>

<script>
  let currentRole = 'student';
  let pwdVisible  = false;

  // ── Read error code set by login.php redirect ──
  (function() {
    const p    = new URLSearchParams(window.location.search);
    const err  = p.get('error');
    const role = p.get('role');
    if (role === 'teacher') setRole('teacher', document.getElementById('tab-teacher'));
    if (!err) return;
    const msgs = {
      empty:   'Please enter your username and password.',
      invalid: 'Incorrect username or password. Please try again.',
      'locked': 'Too many failed attempts. Please wait 15 minutes before trying again.',
      csrf:    'Your session expired. The page has been refreshed — please try again.',
      role:    'This account does not have ' + (role === 'teacher' ? 'teacher' : 'student') + ' access. Please select the correct role.',
    };
    showError(msgs[err] || 'Login failed. Please check your details and try again.');
    if (err === 'invalid' || err === 'role') {
      document.getElementById('username').classList.add('input-error');
      document.getElementById('password').classList.add('input-error');
    }
    window.history.replaceState({}, '', window.location.pathname);
  })();

  function showError(msg) {
    const box = document.getElementById('error-msg');
    document.getElementById('error-text').textContent = msg;
    box.style.display = 'flex';
    box.style.alignItems = 'flex-start';
    box.style.gap = '0.5rem';
  }
  function hideError() {
    document.getElementById('error-msg').style.display = 'none';
    document.getElementById('username').classList.remove('input-error');
    document.getElementById('password').classList.remove('input-error');
  }

  function setRole(role, tab) {
    currentRole = role;
    document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('role-input').value = role;
    document.getElementById('login-subtitle').innerHTML =
      'Sign in to your <span>' + (role === 'teacher' ? 'teacher' : 'student') + '</span> account';
    hideError();
    document.getElementById('password').value = '';
  }

  function togglePwd() {
    pwdVisible = !pwdVisible;
    document.getElementById('password').type = pwdVisible ? 'text' : 'password';
    document.getElementById('pwd-toggle').classList.toggle('pwd-visible', pwdVisible);
  }

  function handleSubmit(e) {
    e.preventDefault();
    hideError();
    const user = document.getElementById('username').value.trim();
    const pass = document.getElementById('password').value;
    if (!user && !pass) { showError('Please enter your username and password.'); document.getElementById('username').classList.add('input-error'); document.getElementById('password').classList.add('input-error'); return false; }
    if (!user) { showError('Please enter your username.'); document.getElementById('username').classList.add('input-error'); return false; }
    if (!pass) { showError('Please enter your password.'); document.getElementById('password').classList.add('input-error'); return false; }
    document.getElementById('login-btn').classList.add('loading');
    document.getElementById('login-form').submit();
    return true;
  }
</script>
</body>
</html>
