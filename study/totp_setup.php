<?php
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
session_start();

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php?error=auth'); exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/totp.php';

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

$adminId = (int)$_SESSION['user_id'];
$adminUser = $_SESSION['username'] ?? 'admin';

// Ensure totp table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_totp (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        user_id      INT NOT NULL UNIQUE,
        secret       VARCHAR(64) NOT NULL,
        enabled      TINYINT(1) NOT NULL DEFAULT 0,
        recovery_codes TEXT DEFAULT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// Fetch current TOTP state
$totpRow = $pdo->prepare("SELECT * FROM admin_totp WHERE user_id = ?");
$totpRow->execute([$adminId]);
$totp = $totpRow->fetch();
$totpEnabled = $totp && $totp['enabled'];

$step    = 'status'; // status | setup | verify | disable
$error   = '';
$success = '';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Start setup: generate new secret ────────────────────────────────────────
if ($action === 'start_setup' && !$totpEnabled) {
    $secret = TOTP::generateSecret();
    $_SESSION['totp_pending_secret'] = $secret;
    $step = 'setup';
}

// ── Restore setup from session ───────────────────────────────────────────────
if ($action === 'show_setup' && isset($_SESSION['totp_pending_secret'])) {
    $step = 'setup';
}

// ── Verify and enable ────────────────────────────────────────────────────────
if ($action === 'verify_enable') {
    $code   = preg_replace('/\s+/', '', $_POST['code'] ?? '');
    $secret = $_SESSION['totp_pending_secret'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'CSRF invalide.';
    } elseif (!$secret) {
        $error = 'Session expirée. Recommencez.';
    } elseif (!TOTP::verify($secret, $code)) {
        $error = 'Code incorrect. Vérifiez l\'heure de votre appareil et réessayez.';
        $step  = 'setup';
    } else {
        // Generate 8 recovery codes
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))); // e.g. A1B2C3D4
        }
        $codesHashed = array_map(fn($c) => password_hash($c, PASSWORD_BCRYPT), $codes);

        $pdo->prepare("INSERT INTO admin_totp (user_id, secret, enabled, recovery_codes)
            VALUES (?,?,1,?)
            ON DUPLICATE KEY UPDATE secret=VALUES(secret), enabled=1, recovery_codes=VALUES(recovery_codes)")
            ->execute([$adminId, $secret, json_encode($codesHashed)]);

        unset($_SESSION['totp_pending_secret']);
        $_SESSION['totp_verified'] = true;

        $step    = 'status';
        $success = 'totp_enabled';
        $totpEnabled = true;
        $totp = ['recovery_plain' => $codes]; // show once
    }
}

// ── Disable 2FA ──────────────────────────────────────────────────────────────
if ($action === 'disable') {
    $code = preg_replace('/\s+/', '', $_POST['code'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'CSRF invalide.';
    } elseif (!$totp || !TOTP::verify($totp['secret'], $code)) {
        $error = 'Code incorrect.';
        $step  = 'disable';
    } else {
        $pdo->prepare("UPDATE admin_totp SET enabled=0 WHERE user_id=?")->execute([$adminId]);
        $totpEnabled = false;
        $success     = 'totp_disabled';
    }
}

if ($action === 'show_disable') { $step = 'disable'; }

// Reload totp row
if ($success === 'totp_enabled' || $success === 'totp_disabled') {
    $totpRow->execute([$adminId]);
    $totp = $totpRow->fetch() ?: $totp;
}

$pendingSecret = $_SESSION['totp_pending_secret'] ?? null;
$qrUrl = $pendingSecret ? TOTP::getQrUrl($pendingSecret, $adminUser) : null;
$otpUri = $pendingSecret ? TOTP::getOtpAuthUri($pendingSecret, $adminUser) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Double authentification – Upskill Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --navy:#0f1d2e; --navy-mid:#162436; --navy-light:#1e3248;
  --green:#3ecf78; --green-dark:#28a85c; --green-glow:rgba(62,207,120,.12);
  --orange:#fb923c; --orange-glow:rgba(251,146,60,.12);
  --white:#fff; --muted:rgba(255,255,255,.55); --border:rgba(255,255,255,.1);
  --red:#f87171; --font:'Sora',sans-serif; --font-body:'DM Sans',sans-serif;
}
body { background:var(--navy); color:var(--white); font-family:var(--font-body); min-height:100vh; display:flex; align-items:flex-start; justify-content:center; padding:3rem 1rem; }
.wrap { width:100%; max-width:560px; }
.back { display:inline-flex; align-items:center; gap:.4rem; color:var(--muted); font-size:.85rem; text-decoration:none; margin-bottom:2rem; transition:color .2s; }
.back:hover { color:var(--white); }
.back svg { flex-shrink:0; }
.page-title { font-family:var(--font); font-size:1.6rem; font-weight:700; letter-spacing:-.02em; margin-bottom:.4rem; }
.page-sub { color:var(--muted); font-size:.9rem; margin-bottom:2.5rem; }
.card { background:var(--navy-mid); border:1px solid var(--border); border-radius:16px; padding:1.8rem; margin-bottom:1.25rem; }
.card-title { font-family:var(--font); font-size:.75rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:var(--muted); margin-bottom:1rem; }
.status-row { display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; }
.status-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.status-dot.on  { background:var(--green); box-shadow:0 0 8px rgba(62,207,120,.5); }
.status-dot.off { background:rgba(255,255,255,.2); }
.status-text { font-family:var(--font); font-size:.95rem; font-weight:600; }
.status-sub { color:var(--muted); font-size:.82rem; margin-top:.15rem; }
label { display:block; font-family:var(--font); font-size:.72rem; font-weight:600; color:var(--muted); letter-spacing:.07em; text-transform:uppercase; margin-bottom:.4rem; }
input[type=text], input[type=number] {
  width:100%; padding:.85rem 1rem; background:rgba(255,255,255,.06); border:1px solid var(--border);
  border-radius:10px; color:var(--white); font-family:var(--font-body); font-size:1.1rem;
  letter-spacing:.2em; outline:none; transition:border-color .2s; text-align:center;
}
input:focus { border-color:var(--green); }
input.err { border-color:var(--red); }
.btn { display:inline-flex; align-items:center; gap:.5rem; padding:.85rem 1.6rem; border-radius:12px; font-family:var(--font); font-size:.9rem; font-weight:700; border:none; cursor:pointer; transition:.2s; }
.btn-green  { background:var(--green); color:#0f1d2e; }
.btn-green:hover { background:var(--green-dark); transform:translateY(-1px); }
.btn-orange { background:var(--orange-glow); color:var(--orange); border:1px solid rgba(251,146,60,.3); }
.btn-orange:hover { background:rgba(251,146,60,.2); }
.btn-ghost { background:transparent; color:var(--muted); border:1px solid var(--border); }
.btn-ghost:hover { color:var(--white); border-color:rgba(255,255,255,.3); }
.btn-red { background:rgba(248,113,113,.1); color:var(--red); border:1px solid rgba(248,113,113,.3); }
.btn-red:hover { background:rgba(248,113,113,.2); }
.qr-wrap { display:flex; flex-direction:column; align-items:center; gap:1rem; margin:1.5rem 0; }
.qr-img { width:200px; height:200px; border-radius:12px; background:white; padding:8px; }
.secret-box { background:rgba(255,255,255,.04); border:1px solid var(--border); border-radius:10px; padding:.75rem 1rem; font-family:monospace; font-size:1.1rem; letter-spacing:.15em; text-align:center; word-break:break-all; color:var(--white); user-select:all; cursor:copy; }
.secret-box:hover { border-color:rgba(62,207,120,.4); }
.steps { counter-reset:step; display:flex; flex-direction:column; gap:.75rem; margin-bottom:1.5rem; }
.step { display:flex; gap:.85rem; align-items:flex-start; font-size:.88rem; color:var(--muted); }
.step::before { counter-increment:step; content:counter(step); width:22px; height:22px; border-radius:50%; background:var(--green-glow); border:1px solid rgba(62,207,120,.3); color:var(--green); font-family:var(--font); font-size:.72rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:.1rem; }
.error-box { background:rgba(248,113,113,.1); border:1px solid rgba(248,113,113,.3); border-radius:10px; padding:.8rem 1rem; color:var(--red); font-size:.85rem; margin-bottom:1rem; }
.success-box { background:var(--green-glow); border:1px solid rgba(62,207,120,.3); border-radius:10px; padding:.8rem 1rem; color:var(--green); font-size:.85rem; margin-bottom:1rem; }
.recovery-grid { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin:1rem 0; }
.recovery-code { background:rgba(255,255,255,.04); border:1px solid var(--border); border-radius:8px; padding:.6rem .8rem; font-family:monospace; font-size:.9rem; letter-spacing:.1em; text-align:center; color:var(--white); }
.warning-box { background:rgba(245,197,66,.08); border:1px solid rgba(245,197,66,.25); border-radius:10px; padding:.9rem 1rem; color:#f5c542; font-size:.83rem; line-height:1.5; margin-bottom:1rem; display:flex; gap:.6rem; }
</style>
</head>
<body>
<div class="wrap">
  <a href="dashboard-admin.php" class="back">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    Retour au tableau de bord
  </a>
  <div class="page-title">Double authentification (2FA)</div>
  <p class="page-sub">Protégez votre compte administrateur avec un code à usage unique.</p>

  <?php if ($error): ?>
    <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success === 'totp_disabled'): ?>
    <div class="success-box">✓ La double authentification a été désactivée.</div>
  <?php endif; ?>

  <?php if ($success === 'totp_enabled'): ?>
    <!-- Show recovery codes ONCE -->
    <div class="card">
      <div class="card-title">✅ 2FA activée avec succès</div>
      <div class="warning-box">
        ⚠️ <span>Sauvegardez ces codes de récupération maintenant. Ils ne seront plus affichés. Utilisez-les si vous perdez accès à votre application d'authentification.</span>
      </div>
      <div class="recovery-grid">
        <?php foreach ($totp['recovery_plain'] as $rc): ?>
          <div class="recovery-code"><?= htmlspecialchars($rc) ?></div>
        <?php endforeach; ?>
      </div>
      <a href="dashboard-admin.php"><button class="btn btn-green" style="width:100%;justify-content:center;margin-top:.5rem;">Aller au tableau de bord →</button></a>
    </div>

  <?php elseif ($step === 'setup' && $pendingSecret): ?>
    <!-- Setup: scan QR -->
    <div class="card">
      <div class="card-title">Étape 1 — Scanner le QR code</div>
      <div class="steps">
        <div class="step">Installez <strong>Google Authenticator</strong>, <strong>Authy</strong> ou toute app TOTP compatible.</div>
        <div class="step">Scannez ce QR code, ou saisissez la clé manuellement.</div>
        <div class="step">Entrez le code à 6 chiffres affiché dans l'app pour confirmer.</div>
      </div>
      <div class="qr-wrap">
        <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code 2FA" class="qr-img" width="200" height="200">
        <div style="font-size:.78rem;color:var(--muted);text-align:center;">Ou saisir manuellement :</div>
        <div class="secret-box" title="Cliquez pour copier" onclick="navigator.clipboard.writeText(this.textContent.replace(/\s/g,'')).then(()=>this.style.borderColor='var(--green)')">
          <?= implode(' ', str_split(htmlspecialchars($pendingSecret), 4)) ?>
        </div>
        <div style="font-size:.72rem;color:var(--muted);">Cliquez sur la clé pour copier</div>
      </div>
    </div>
    <div class="card">
      <div class="card-title">Étape 2 — Confirmer avec le code</div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="verify_enable">
        <label for="totp-code">Code à 6 chiffres</label>
        <input type="text" id="totp-code" name="code" maxlength="6" placeholder="000000"
          autocomplete="one-time-code" inputmode="numeric" pattern="\d{6}"
          class="<?= $error ? 'err' : '' ?>" autofocus>
        <div style="display:flex;gap:.75rem;margin-top:1.25rem;">
          <button type="submit" class="btn btn-green" style="flex:1;justify-content:center;">Activer la 2FA →</button>
          <a href="totp_setup.php"><button type="button" class="btn btn-ghost">Annuler</button></a>
        </div>
      </form>
    </div>

  <?php elseif ($step === 'disable'): ?>
    <div class="card">
      <div class="card-title">Désactiver la 2FA</div>
      <p style="color:var(--muted);font-size:.88rem;margin-bottom:1.25rem;">Entrez votre code d'authentification actuel pour confirmer la désactivation.</p>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="disable">
        <label for="disable-code">Code à 6 chiffres</label>
        <input type="text" id="disable-code" name="code" maxlength="6" placeholder="000000"
          autocomplete="one-time-code" inputmode="numeric" autofocus
          class="<?= $error ? 'err' : '' ?>">
        <div style="display:flex;gap:.75rem;margin-top:1.25rem;">
          <button type="submit" class="btn btn-red" style="flex:1;justify-content:center;">Désactiver →</button>
          <a href="totp_setup.php"><button type="button" class="btn btn-ghost">Annuler</button></a>
        </div>
      </form>
    </div>

  <?php else: ?>
    <!-- Status page -->
    <div class="card">
      <div class="card-title">Statut actuel</div>
      <div class="status-row">
        <div class="status-dot <?= $totpEnabled ? 'on' : 'off' ?>"></div>
        <div>
          <div class="status-text"><?= $totpEnabled ? 'Double authentification activée' : 'Double authentification désactivée' ?></div>
          <div class="status-sub"><?= $totpEnabled ? 'Votre compte est protégé par un code TOTP.' : 'Votre compte utilise uniquement le mot de passe.' ?></div>
        </div>
      </div>

      <?php if ($totpEnabled): ?>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
          <form method="POST" style="flex:1;">
            <input type="hidden" name="action" value="show_disable">
            <button type="submit" class="btn btn-red" style="width:100%;justify-content:center;">Désactiver la 2FA</button>
          </form>
        </div>
      <?php else: ?>
        <form method="POST">
          <input type="hidden" name="action" value="start_setup">
          <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            Activer la double authentification →
          </button>
        </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-title">Comment ça marche</div>
      <div class="steps">
        <div class="step">Vous vous connectez avec votre identifiant et mot de passe.</div>
        <div class="step">L'app vous demande un code à 6 chiffres généré par votre téléphone.</div>
        <div class="step">Même si quelqu'un vole votre mot de passe, il ne peut pas se connecter.</div>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
