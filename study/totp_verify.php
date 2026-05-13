<?php
/**
 * totp_verify.php — Second factor for admin login
 * Only accessible when $_SESSION['totp_pending'] is set
 * (set by panel-d7ea770eb4b4.php after successful password check)
 */
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
session_start();

// Must have a pending 2FA verification (password already verified)
if (empty($_SESSION['totp_pending_uid'])) {
    header('Location: panel-d7ea770eb4b4.php'); exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/totp.php';

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

$uid   = (int)$_SESSION['totp_pending_uid'];
$error = '';

// Fetch TOTP secret
$totpRow = $pdo->prepare("SELECT secret FROM admin_totp WHERE user_id=? AND enabled=1");
$totpRow->execute([$uid]);
$totp = $totpRow->fetch();

if (!$totp) {
    // No 2FA record found — let them through (shouldn't happen but safe fallback)
    goto finalize;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'CSRF invalide.';
    } else {
        $code = preg_replace('/\s+/', '', $_POST['code'] ?? '');

        // Check TOTP code
        if (TOTP::verify($totp['secret'], $code)) {
            goto finalize;
        }

        // Check recovery codes
        $recoveryCodes = json_decode($totp['recovery_codes'] ?? '[]', true) ?? [];
        $matched = false;
        foreach ($recoveryCodes as $i => $hash) {
            if (password_verify(strtoupper($code), $hash)) {
                // Invalidate used recovery code
                $recoveryCodes[$i] = password_hash('__used__' . random_bytes(8), PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE admin_totp SET recovery_codes=? WHERE user_id=?")
                    ->execute([json_encode($recoveryCodes), $uid]);
                $matched = true;
                break;
            }
        }

        if ($matched) goto finalize;

        // Log failed attempt
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);
        try { $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (?)")->execute([$ip]); } catch (Throwable $e) {}
        $error = 'Code incorrect. Réessayez ou utilisez un code de récupération.';
    }
}

goto render;

finalize:
// Complete login
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='admin'");
$userStmt->execute([$uid]);
$user = $userStmt->fetch();

if (!$user) { header('Location: panel-d7ea770eb4b4.php'); exit; }

session_regenerate_id(true);
$_SESSION['csrf_token']  = bin2hex(random_bytes(32));
$_SESSION['user_id']     = $user['id'];
$_SESSION['username']    = $user['username'];
$_SESSION['full_name']   = $user['full_name'];
$_SESSION['role']        = 'admin';
$_SESSION['totp_verified'] = true;
unset($_SESSION['totp_pending_uid'], $_SESSION['totp_pending_data']);

header('Location: dashboard-admin.php'); exit;

render:
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vérification 2FA – Upskill Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --navy:#0f1d2e; --navy-mid:#162436; --orange:#fb923c;
  --white:#fff; --muted:rgba(255,255,255,.55); --border:rgba(255,255,255,.1);
  --red:#f87171; --font:'Sora',sans-serif; --font-body:'DM Sans',sans-serif;
}
body { background:var(--navy); color:var(--white); font-family:var(--font-body); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem; }
.card { background:var(--navy-mid); border:1px solid var(--border); border-radius:20px; padding:2.5rem; width:100%; max-width:400px; box-shadow:0 20px 60px rgba(0,0,0,.4); text-align:center; }
.icon { width:64px; height:64px; border-radius:50%; background:rgba(251,146,60,.1); border:2px solid rgba(251,146,60,.3); display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 1.5rem; }
h1 { font-family:var(--font); font-size:1.35rem; font-weight:700; margin-bottom:.5rem; }
.sub { color:var(--muted); font-size:.88rem; line-height:1.6; margin-bottom:2rem; }
label { display:block; font-family:var(--font); font-size:.72rem; font-weight:600; color:var(--muted); letter-spacing:.07em; text-transform:uppercase; margin-bottom:.5rem; text-align:left; }
input { width:100%; padding:1rem; background:rgba(255,255,255,.06); border:1px solid var(--border); border-radius:12px; color:var(--white); font-family:var(--font); font-size:1.8rem; letter-spacing:.3em; outline:none; transition:border-color .2s; text-align:center; }
input:focus { border-color:var(--orange); }
input.err { border-color:var(--red); }
.btn { width:100%; padding:1rem; background:var(--orange); color:#0f1d2e; font-family:var(--font); font-size:.95rem; font-weight:700; border:none; border-radius:12px; cursor:pointer; margin-top:1.25rem; transition:.2s; }
.btn:hover { background:#ea7c22; transform:translateY(-1px); }
.error-box { background:rgba(248,113,113,.1); border:1px solid rgba(248,113,113,.3); border-radius:10px; padding:.75rem 1rem; color:var(--red); font-size:.85rem; margin-bottom:1rem; text-align:left; }
.back { display:block; margin-top:1.5rem; color:var(--muted); font-size:.82rem; text-decoration:none; }
.back:hover { color:var(--white); }
.recovery-hint { margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid var(--border); color:var(--muted); font-size:.8rem; line-height:1.6; }
</style>
</head>
<body>
<div class="card">
  <div class="icon">🔐</div>
  <h1>Vérification en deux étapes</h1>
  <p class="sub">Ouvrez votre application d'authentification et entrez le code à 6 chiffres affiché.</p>

  <?php if ($error): ?>
    <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" onsubmit="return validateCode()">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <label for="totp-code">Code de vérification</label>
    <input type="text" id="totp-code" name="code" maxlength="8"
      placeholder="000000" autocomplete="one-time-code"
      inputmode="numeric" autofocus
      class="<?= $error ? 'err' : '' ?>"
      oninput="this.value=this.value.replace(/[^0-9A-Za-z]/g,'').toUpperCase()">
    <button type="submit" class="btn">Vérifier →</button>
  </form>

  <div class="recovery-hint">
    Code perdu ? Entrez l'un de vos <strong>codes de récupération</strong> à 8 caractères ci-dessus.
  </div>

  <a href="panel-d7ea770eb4b4.php" class="back">← Retour à la connexion</a>
</div>
<script>
function validateCode() {
  const val = document.getElementById('totp-code').value.replace(/\s/g,'');
  if (val.length !== 6 && val.length !== 8) {
    document.getElementById('totp-code').classList.add('err');
    return false;
  }
  return true;
}
// Auto-submit when 6 digits entered
document.getElementById('totp-code').addEventListener('input', function() {
  const v = this.value.replace(/\s/g,'');
  if (v.length === 6 && /^\d{6}$/.test(v)) {
    this.closest('form').submit();
  }
});
</script>
</body>
</html>
