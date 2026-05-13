<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// If already logged in as admin, go straight to the dashboard
if (!empty($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: dashboard-admin.php');
    exit;
}

require 'config.php';

// Inline CSRF helpers (csrf.php cannot be required on this server)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_verify_inline(): void {
    $token    = $_SESSION['csrf_token'] ?? '';
    $received = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($token, (string)$received)) {
        http_response_code(403); exit('Invalid CSRF token.');
    }
}

$error = '';
$locked_until = null; // seconds remaining

// Rate-limiting: 3 attempts per IP per 15 minutes (stricter than student login)
$ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip, attempted_at)
    )");
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_inline();

    // Check rate limit before processing credentials
    try {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND attempted_at > NOW() - INTERVAL 15 MINUTE");
        $chk->execute([$ip]);
        if ((int)$chk->fetchColumn() >= 3) {
            $error = 'locked';
        }
    } catch (PDOException $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $username = trim($_POST['username'] ?? '');
    $password =      $_POST['password'] ?? '';

    // Input length validation
    if ($username === '' || $password === '') {
        $error = 'empty';
    } elseif (mb_strlen($username) > 80 || mb_strlen($password) > 200) {
        $error = 'invalid';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $hash  = $user['password'] ?? '$2y$10$invalidsaltpadding00000000000000000000000000000000000';
        $valid = password_verify($password, $hash);

        if (!$user || !$valid) {
            // Log failed attempt
            try { $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (?)")->execute([$ip]); } catch (PDOException $e) {}
            $error = 'invalid';
        } else {
            // Success — clear attempts, check 2FA
            try { $pdo->prepare("DELETE FROM login_attempts WHERE ip=?")->execute([$ip]); } catch (PDOException $e) {}

            // Check if TOTP is enabled for this admin
            $totpCheck = null;
            try {
                $tc = $pdo->prepare("SELECT id FROM admin_totp WHERE user_id=? AND enabled=1");
                $tc->execute([$user['id']]);
                $totpCheck = $tc->fetch();
            } catch (Throwable $e) {}

            if ($totpCheck) {
                // 2FA enabled — store pending state and redirect to verify page
                session_regenerate_id(true);
                $_SESSION['totp_pending_uid'] = $user['id'];
                header('Location: totp_verify.php');
                exit;
            }

            // No 2FA — complete login directly
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // rotate token on login
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = 'admin';
            header('Location: dashboard-admin.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upskill – Admin Access</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --navy:       #0f1d2e;
    --navy-mid:   #162436;
    --navy-light: #1e3248;
    --orange:     #fb923c;
    --orange-dark:#ea7c22;
    --orange-glow:rgba(251,146,60,0.12);
    --white:      #ffffff;
    --muted:      rgba(255,255,255,0.5);
    --border:     rgba(255,255,255,0.1);
    --font:       'Sora', sans-serif;
    --font-body:  'DM Sans', sans-serif;
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
    background: radial-gradient(circle, rgba(251,146,60,0.09) 0%, transparent 65%);
    pointer-events: none;
  }

  .left-logo { display: flex; align-items: center; gap: 0.6rem; }
  .left-logo span { font-family: var(--font); font-weight: 600; font-size: 1.05rem; }
  .left-logo em { color: var(--orange); font-style: normal; }

  .left-content { flex: 1; display: flex; flex-direction: column; justify-content: center; max-width: 380px; }
  .left-content h1 {
    font-family: var(--font); font-size: 2.2rem; font-weight: 700;
    line-height: 1.15; letter-spacing: -0.03em; margin-bottom: 1.2rem;
  }
  .left-content h1 span { color: var(--orange); }
  .left-content p { color: var(--muted); font-size: 0.93rem; line-height: 1.7; font-weight: 300; margin-bottom: 2.5rem; }

  .feature-list { display: flex; flex-direction: column; gap: 0.9rem; }
  .feature-item { display: flex; align-items: center; gap: 0.75rem; }
  .feature-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--orange); flex-shrink: 0; }
  .feature-item span { color: rgba(255,255,255,0.7); font-size: 0.88rem; }

  .left-bottom { color:rgba(255,255,255,0.50); font-size: 0.75rem; }
  .left-bottom a { color:rgba(255,255,255,0.50); text-decoration: none; }
  .left-bottom a:hover { color: var(--muted); }

  /* RIGHT PANEL */
  .right {
    display: flex; align-items: center; justify-content: center;
    padding: 2rem;
  }

  .login-box { width: 100%; max-width: 420px; }

  /* Admin badge */
  .admin-badge {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: rgba(251,146,60,0.12); border: 1px solid rgba(251,146,60,0.3);
    color: var(--orange); font-family: var(--font); font-size: 0.72rem;
    font-weight: 700; padding: 0.3rem 0.75rem; border-radius: 100px;
    margin-bottom: 1.5rem;
  }

  .login-title    { font-family: var(--font); font-size: 1.5rem; font-weight: 700; margin-bottom: 0.3rem; letter-spacing: -0.02em; }
  .login-subtitle { color: var(--muted); font-size: 0.85rem; margin-bottom: 2rem; }
  .login-subtitle span { color: var(--orange); }

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
  .form-group input:focus { border-color: rgba(251,146,60,0.5); background: rgba(251,146,60,0.04); }
  .form-group input.input-error { border-color: rgba(255,100,100,0.6); }

  .password-toggle {
    position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
    cursor: pointer; color: var(--muted); transition: color 0.2s;
    background: none; border: none; padding: 0.15rem;
    display: flex; align-items: center; justify-content: center; border-radius: 4px;
  }
  .password-toggle:hover { color: var(--white); }
  .eye-open  { display: block; }
  .eye-closed { display: none; }
  .pwd-visible .eye-open  { display: none; }
  .pwd-visible .eye-closed { display: block; }

  .error-msg {
    background: rgba(255,80,80,0.1); border: 1px solid rgba(255,80,80,0.25);
    border-radius: 10px; padding: 0.7rem 1rem;
    font-size: 0.83rem; color: #ff8080;
    margin-bottom: 1rem;
    display: none;
    align-items: flex-start; gap: 0.5rem;
  }

  .btn-login {
    width: 100%; padding: 0.9rem; background: var(--orange);
    color: var(--white); font-family: var(--font); font-weight: 700;
    font-size: 0.95rem; border: none; border-radius: 12px; cursor: pointer;
    transition: background 0.2s, transform 0.15s; letter-spacing: -0.01em;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    margin-top: 1.5rem;
  }
  .btn-login:hover  { background: var(--orange-dark); transform: translateY(-2px); }
  .btn-login:active { transform: translateY(0); }
  .btn-login .spinner { width:16px; height:16px; border:2px solid rgba(255,255,255,0.3); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; display:none; }
  .btn-login.loading .btn-text { display:none; }
  .btn-login.loading .spinner  { display:block; }
  @keyframes spin { to { transform: rotate(360deg); } }

  .back-link { text-align: center; margin-top: 1.8rem; font-size: 0.83rem; color: var(--muted); }
  .back-link a { color: rgba(255,255,255,0.35); text-decoration: none; transition: color 0.2s; }
  .back-link a:hover { color: var(--muted); }

  @media (max-width: 768px) {
    body { grid-template-columns: 1fr; }
    .left { display: none; }
    .right { padding: 1.5rem; }
  }
</style>
</head>
<body>
<a href="#login-form" class="skip-link" style="position:absolute;top:-40px;left:0;background:#3ecf78;color:#0f1d2e;padding:.5rem 1rem;font-weight:700;font-size:.85rem;z-index:9999;border-radius:0 0 8px 0;text-decoration:none;">Aller au formulaire</a>

<!-- LEFT PANEL -->
<div class="left" role="complementary" aria-hidden="false">
  <div class="left-logo">
    <svg width="26" height="26" viewBox="0 0 28 28" fill="none"><rect width="28" height="28" rx="8" fill="#fb923c"/><path d="M8 14l4 4 8-8" stroke="#0f1d2e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Up<em>skill</em></span>
  </div>

  <div class="left-content">
    <h1>Admin <span>Control</span><br>Panel</h1>
    <p>Restricted access. This page is for platform administrators only. Manage courses, teachers, and the full platform from here.</p>
    <div class="feature-list">
      <div class="feature-item"><div class="feature-dot"></div><span>Create &amp; manage courses</span></div>
      <div class="feature-item"><div class="feature-dot"></div><span>Assign courses to teachers</span></div>
      <div class="feature-item"><div class="feature-dot"></div><span>Full platform oversight</span></div>
      <div class="feature-item"><div class="feature-dot"></div><span>Teacher &amp; student management</span></div>
    </div>
  </div>

  <div class="left-bottom">
    © 2026 Upskill Education &nbsp;·&nbsp; <a href="https://upskill-edu.com/index-fr.php">Main site</a>
  </div>
</div>

<!-- RIGHT PANEL -->
<div class="right" role="main" id="login-form">
  <div class="login-box">

    <div class="admin-badge">🛠️ &nbsp;Administrator Access</div>

    <div class="login-title">Admin sign in</div>
    <div class="login-subtitle">Sign in to your <span>admin</span> account</div>

    <div class="error-msg" id="error-msg" role="alert" aria-live="polite"<?= $error ? ' style="display:flex"' : '' ?>>
      ⚠ &nbsp;<span id="error-text"><?php
        if     ($error === 'empty')   echo 'Please enter your username and password.';
        elseif ($error === 'locked')  echo 'Too many failed attempts. Please wait 15 minutes before trying again.';
        else                          echo 'Invalid credentials. Please try again.';
      ?></span>
    </div>

    <form method="POST" id="login-form" action="" onsubmit="return handleSubmit(event)">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <div class="form-group">
        <label>Username</label>
        <div class="input-wrap">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
          <input type="text" id="username" aria-required="true" name="username" placeholder="Admin username" autocomplete="username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Password</label>
        <div class="input-wrap">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          <input type="password" id="password" aria-required="true" name="password" placeholder="Admin password" autocomplete="current-password">
          <button class="password-toggle" type="button" onclick="togglePwd()" aria-label="Toggle password visibility">
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

      <button class="btn-login" id="login-btn" type="submit"<?= $error === 'locked' ? ' disabled style="opacity:0.5;cursor:not-allowed;transform:none"' : '' ?>>
        <span class="btn-text">Sign in →</span>
        <div class="spinner"></div>
      </button>
    </form>

    <div class="back-link">
      <a href="https://upskill-edu.com/index-fr.php">← Back to main site</a>
    </div>

  </div>
</div>

<script>
  let pwdVisible = false;

  <?php if ($error): ?>
  document.getElementById('username').classList.add('input-error');
  document.getElementById('password').classList.add('input-error');
  <?php endif; ?>

  function togglePwd() {
    pwdVisible = !pwdVisible;
    document.getElementById('password').type = pwdVisible ? 'text' : 'password';
    document.getElementById('pwd-toggle') && document.getElementById('pwd-toggle').classList.toggle('pwd-visible', pwdVisible);
    document.querySelector('.password-toggle').classList.toggle('pwd-visible', pwdVisible);
  }

  function handleSubmit(e) {
    const user = document.getElementById('username').value.trim();
    const pass = document.getElementById('password').value;
    document.getElementById('error-msg').style.display = 'none';
    document.getElementById('username').classList.remove('input-error');
    document.getElementById('password').classList.remove('input-error');

    if (!user || !pass) {
      document.getElementById('error-msg').style.display = 'flex';
      document.getElementById('error-text').textContent = 'Please enter your username and password.';
      if (!user) document.getElementById('username').classList.add('input-error');
      if (!pass) document.getElementById('password').classList.add('input-error');
      return false;
    }
    document.getElementById('login-btn').classList.add('loading');
    return true;
  }
</script>
</body>
</html>
