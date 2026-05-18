<?php
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/rate_limit.php';

// Ensure password_resets table and email column exist
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(180) DEFAULT NULL");
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        token      VARCHAR(100) NOT NULL,
        expires_at DATETIME NOT NULL,
        used       TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_token (token),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

$step    = 'form';   // form | sent | error
$error   = '';
$lang    = $_GET['lang'] ?? 'fr';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    api_rate_limit('forgot_pw:ip:' . $ip, 5, 900);

    $identifier = trim($_POST['identifier'] ?? '');
    $email_in   = trim($_POST['email']      ?? '');

    if ($identifier === '' || $email_in === '') {
        $error = 'empty';
    } elseif (!filter_var($email_in, FILTER_VALIDATE_EMAIL) || mb_strlen($email_in) > 180) {
        $error = 'invalid_email';
    } elseif (mb_strlen($identifier) > 80) {
        $error = 'invalid';
    } else {
        // Look up user by username or existing email
        $stmt = $pdo->prepare("SELECT id, username, full_name, email FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && !empty($user['email']) && strtolower($user['email']) === strtolower($email_in)) {
            // Only proceed if the submitted email matches the one already on file
            $sendTo = $user['email'];

            // Invalidate old tokens
            $pdo->prepare("UPDATE password_resets SET used=1 WHERE user_id=? AND used=0")->execute([$user['id']]);

            // Generate secure token — store only the SHA-256 hash; send the raw token in the URL
            $token       = bin2hex(random_bytes(32));
            $tokenHash   = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)")
                ->execute([$user['id'], $tokenHash, $expires]);

            // Build reset URL
            $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'];
            $resetUrl = "{$protocol}://{$host}/reset-password.php?token={$token}&lang={$lang}";

            // Send email
            $name    = $user['full_name'] ?: $user['username'];
            $subject = 'Réinitialisation de votre mot de passe – Upskill Education';
            $body    = "Bonjour {$name},\r\n\r\n"
                     . "Vous avez demandé la réinitialisation de votre mot de passe.\r\n\r\n"
                     . "Cliquez sur ce lien (valable 1 heure) :\r\n{$resetUrl}\r\n\r\n"
                     . "Si vous n'avez pas fait cette demande, ignorez cet email.\r\n\r\n"
                     . "— L'équipe Upskill Education";
            $headers = "From: noreply@upskill-edu.com\r\nReply-To: noreply@upskill-edu.com\r\nX-Mailer: PHP/" . phpversion();

            mail($sendTo, $subject, $body, $headers);
        }
        // Always show "sent" — never reveal whether user exists
        $step = 'sent';
    }
}

$T = [
    'fr' => [
        'title'       => 'Mot de passe oublié',
        'heading'     => 'Réinitialiser votre mot de passe',
        'sub'         => 'Entrez votre identifiant et votre adresse email. Vous recevrez un lien de réinitialisation.',
        'lbl_id'      => 'Nom d\'utilisateur',
        'ph_id'       => 'Votre identifiant',
        'lbl_email'   => 'Adresse email',
        'ph_email'    => 'votre@email.com',
        'btn'         => 'Envoyer le lien →',
        'back'        => '← Retour à la connexion',
        'sent_h'      => 'Email envoyé !',
        'sent_p'      => 'Si un compte correspond à ces informations, un lien de réinitialisation a été envoyé. Vérifiez votre boîte de réception (et les spams).',
        'err_empty'   => 'Veuillez remplir tous les champs.',
        'err_email'   => 'Adresse email invalide.',
        'err_invalid' => 'Identifiant trop long.',
    ],
    'en' => [
        'title'       => 'Forgot Password',
        'heading'     => 'Reset your password',
        'sub'         => 'Enter your username and email address. You\'ll receive a reset link.',
        'lbl_id'      => 'Username',
        'ph_id'       => 'Your username',
        'lbl_email'   => 'Email address',
        'ph_email'    => 'your@email.com',
        'btn'         => 'Send reset link →',
        'back'        => '← Back to login',
        'sent_h'      => 'Email sent!',
        'sent_p'      => 'If an account matches those details, a reset link has been sent. Check your inbox (and spam folder).',
        'err_empty'   => 'Please fill in all fields.',
        'err_email'   => 'Invalid email address.',
        'err_invalid' => 'Username is too long.',
    ],
];
$tr = $T[$lang] ?? $T['fr'];
$loginPage = $lang === 'en' ? 'index2.php' : 'index2-fr.php';
$errMsg = $error === 'empty' ? $tr['err_empty'] : ($error === 'invalid_email' ? $tr['err_email'] : ($error ? $tr['err_invalid'] : ''));
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $tr['title'] ?> – Upskill Education</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --navy:#0f1d2e; --navy-mid:#162436; --green:#3ecf78; --green-dark:#28a85c;
    --green-glow:rgba(62,207,120,.12); --white:#fff; --muted:rgba(255,255,255,.5);
    --border:rgba(255,255,255,.1); --font:'Sora',sans-serif; --font-body:'DM Sans',sans-serif;
  }
  body { background:var(--navy); color:var(--white); font-family:var(--font-body);
         min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem; }
  .card {
    background:var(--navy-mid); border:1px solid var(--border); border-radius:20px;
    padding:2.5rem 2.5rem; width:100%; max-width:420px;
    box-shadow:0 20px 60px rgba(0,0,0,.4);
  }
  .logo { font-family:var(--font); font-size:1.1rem; font-weight:700; color:var(--green);
          margin-bottom:2rem; display:flex; align-items:center; gap:.5rem; }
  .logo-dot { width:8px; height:8px; background:var(--green); border-radius:50%; display:inline-block; }
  h1 { font-family:var(--font); font-size:1.5rem; font-weight:700; letter-spacing:-.02em; margin-bottom:.5rem; }
  .sub { color:var(--muted); font-size:.88rem; line-height:1.6; margin-bottom:2rem; }
  label { display:block; font-family:var(--font); font-size:.73rem; font-weight:600;
          color:var(--muted); letter-spacing:.07em; text-transform:uppercase; margin-bottom:.45rem; }
  input { width:100%; padding:.85rem 1rem; background:rgba(255,255,255,.06); border:1px solid var(--border);
          border-radius:10px; color:var(--white); font-family:var(--font-body); font-size:.92rem;
          outline:none; transition:border-color .2s; margin-bottom:1.25rem; }
  input:focus { border-color:var(--green); }
  input.err { border-color:#f87171; }
  .error-msg { background:rgba(248,113,113,.1); border:1px solid rgba(248,113,113,.3);
               border-radius:10px; padding:.75rem 1rem; font-size:.85rem; color:#f87171;
               margin-bottom:1.25rem; display:flex; align-items:center; gap:.5rem; }
  .btn { width:100%; padding:1rem; background:var(--green); color:#0f1d2e; font-family:var(--font);
         font-size:.95rem; font-weight:700; border:none; border-radius:12px; cursor:pointer;
         transition:background .2s, transform .15s; }
  .btn:hover { background:var(--green-dark); transform:translateY(-1px); }
  .back-link { display:block; text-align:center; margin-top:1.5rem; color:var(--green);
               font-size:.85rem; text-decoration:none; opacity:.85; }
  .back-link:hover { opacity:1; }
  /* Sent state */
  .sent-icon { width:64px; height:64px; border-radius:50%; background:var(--green-glow);
               border:2px solid rgba(62,207,120,.3); display:flex; align-items:center;
               justify-content:center; font-size:1.8rem; margin:0 auto 1.5rem; }
  .sent-card { text-align:center; }
  .sent-card h1 { margin-bottom:.75rem; }
</style>
</head>
<body>
<div class="card">
  <div class="logo"><span class="logo-dot"></span> Upskill Education</div>

  <?php if ($step === 'sent'): ?>
    <div class="sent-card">
      <div class="sent-icon">📬</div>
      <h1><?= $tr['sent_h'] ?></h1>
      <p class="sub"><?= $tr['sent_p'] ?></p>
      <a href="<?= $loginPage ?>" class="back-link"><?= $tr['back'] ?></a>
    </div>
  <?php else: ?>
    <h1><?= $tr['heading'] ?></h1>
    <p class="sub"><?= $tr['sub'] ?></p>

    <?php if ($errMsg): ?>
      <div class="error-msg">⚠ <?= htmlspecialchars($errMsg) ?></div>
    <?php endif; ?>

    <form method="POST" action="?lang=<?= urlencode($lang) ?>">
      <label for="identifier"><?= $tr['lbl_id'] ?></label>
      <input type="text" id="identifier" name="identifier"
             placeholder="<?= $tr['ph_id'] ?>"
             value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
             maxlength="80" autocomplete="username"
             class="<?= $error ? 'err' : '' ?>">

      <label for="email"><?= $tr['lbl_email'] ?></label>
      <input type="email" id="email" name="email"
             placeholder="<?= $tr['ph_email'] ?>"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             maxlength="180" autocomplete="email"
             class="<?= $error ? 'err' : '' ?>">

      <button type="submit" class="btn"><?= $tr['btn'] ?></button>
    </form>
    <a href="<?= $loginPage ?>" class="back-link"><?= $tr['back'] ?></a>
  <?php endif; ?>
</div>
</body>
</html>
