<?php
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
session_start();
require_once __DIR__ . '/config.php';

$lang  = $_GET['lang'] ?? 'fr';
$token = trim($_GET['token'] ?? '');
$step  = 'form'; // form | success | invalid
$error = '';

// Validate token
$tokenData = null;
if ($token !== '') {
    try {
        $stmt = $pdo->prepare("
            SELECT pr.*, u.username, u.full_name
            FROM   password_resets pr
            JOIN   users u ON u.id = pr.user_id
            WHERE  pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
            LIMIT  1
        ");
        $stmt->execute([hash('sha256', $token)]);
        $tokenData = $stmt->fetch() ?: null;
    } catch (Throwable $e) {}
}

if (!$tokenData) { $step = 'invalid'; }

if ($step === 'form' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw1 = $_POST['password']  ?? '';
    $pw2 = $_POST['password2'] ?? '';

    if ($pw1 === '' || $pw2 === '') {
        $error = 'empty';
    } elseif (mb_strlen($pw1) < 8) {
        $error = 'short';
    } elseif (mb_strlen($pw1) > 200) {
        $error = 'long';
    } elseif ($pw1 !== $pw2) {
        $error = 'mismatch';
    } else {
        $hash = password_hash($pw1, PASSWORD_BCRYPT, ['cost' => 12]);
        try {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $tokenData['user_id']]);
            $pdo->prepare("UPDATE password_resets SET used=1 WHERE token=?")->execute([$token]);
            // Invalidate all other tokens for this user
            $pdo->prepare("UPDATE password_resets SET used=1 WHERE user_id=? AND token != ?")->execute([$tokenData['user_id'], $token]);
            $step = 'success';
        } catch (Throwable $e) {
            $error = 'db';
        }
    }
}

$T = [
    'fr' => [
        'title'     => 'Nouveau mot de passe',
        'heading'   => 'Choisir un nouveau mot de passe',
        'sub'       => 'Votre lien est valide. Choisissez un mot de passe sécurisé.',
        'lbl_pw'    => 'Nouveau mot de passe',
        'lbl_pw2'   => 'Confirmer le mot de passe',
        'ph_pw'     => 'Minimum 8 caractères',
        'ph_pw2'    => 'Répétez le mot de passe',
        'btn'       => 'Enregistrer le mot de passe →',
        'back'      => '← Retour à la connexion',
        'ok_h'      => 'Mot de passe modifié !',
        'ok_p'      => 'Votre mot de passe a été mis à jour. Vous pouvez maintenant vous connecter.',
        'ok_btn'    => 'Se connecter →',
        'inv_h'     => 'Lien invalide ou expiré',
        'inv_p'     => 'Ce lien de réinitialisation n\'est plus valide. Demandez-en un nouveau.',
        'inv_btn'   => 'Nouvelle demande →',
        'err_empty' => 'Veuillez remplir les deux champs.',
        'err_short' => 'Le mot de passe doit contenir au moins 8 caractères.',
        'err_long'  => 'Mot de passe trop long.',
        'err_mismatch'=> 'Les mots de passe ne correspondent pas.',
        'err_db'    => 'Erreur serveur. Veuillez réessayer.',
        'strength'  => ['Très faible','Faible','Moyen','Fort','Très fort'],
    ],
    'ar' => [
        'title'     => 'كلمة مرور جديدة',
        'heading'   => 'اختر كلمة مرور جديدة',
        'sub'       => 'الرابط صالح. اختر كلمة مرور آمنة.',
        'lbl_pw'    => 'كلمة المرور الجديدة',
        'lbl_pw2'   => 'تأكيد كلمة المرور',
        'ph_pw'     => '8 أحرف على الأقل',
        'ph_pw2'    => 'أعد كتابة كلمة المرور',
        'btn'       => 'حفظ كلمة المرور ←',
        'back'      => 'العودة إلى تسجيل الدخول →',
        'ok_h'      => 'تم تغيير كلمة المرور !',
        'ok_p'      => 'تم تحديث كلمة مرورك. يمكنك الآن تسجيل الدخول.',
        'ok_btn'    => 'تسجيل الدخول ←',
        'inv_h'     => 'الرابط غير صالح أو منتهي الصلاحية',
        'inv_p'     => 'هذا الرابط لم يعد صالحاً. اطلب رابطاً جديداً.',
        'inv_btn'   => 'طلب جديد ←',
        'err_empty' => 'يرجى ملء الحقلين.',
        'err_short' => 'يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل.',
        'err_long'  => 'كلمة المرور طويلة جداً.',
        'err_mismatch'=> 'كلمتا المرور غير متطابقتين.',
        'err_db'    => 'خطأ في الخادم. حاول مرة أخرى.',
        'strength'  => ['ضعيف جداً','ضعيف','متوسط','قوي','قوي جداً'],
    ],
    'en' => [
        'title'     => 'New Password',
        'heading'   => 'Choose a new password',
        'sub'       => 'Your link is valid. Choose a strong password.',
        'lbl_pw'    => 'New password',
        'lbl_pw2'   => 'Confirm password',
        'ph_pw'     => 'Minimum 8 characters',
        'ph_pw2'    => 'Repeat password',
        'btn'       => 'Save password →',
        'back'      => '← Back to login',
        'ok_h'      => 'Password changed!',
        'ok_p'      => 'Your password has been updated. You can now log in.',
        'ok_btn'    => 'Log in →',
        'inv_h'     => 'Invalid or expired link',
        'inv_p'     => 'This reset link is no longer valid. Request a new one.',
        'inv_btn'   => 'Request new link →',
        'err_empty' => 'Please fill in both fields.',
        'err_short' => 'Password must be at least 8 characters.',
        'err_long'  => 'Password is too long.',
        'err_mismatch'=> 'Passwords do not match.',
        'err_db'    => 'Server error. Please try again.',
        'strength'  => ['Very weak','Weak','Fair','Strong','Very strong'],
    ],
];
$tr = $T[$lang] ?? $T['fr'];
$dir = $lang === 'ar' ? 'rtl' : 'ltr';
$loginPage   = $lang === 'en' ? 'index2.php' : 'index2-fr.php';
$forgotPage  = "forgot-password.php?lang={$lang}";

$errMsgMap = ['empty'=>'err_empty','short'=>'err_short','long'=>'err_long','mismatch'=>'err_mismatch','db'=>'err_db'];
$errMsg = $error ? ($tr[$errMsgMap[$error]] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
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
    --red:#f87171; --yellow:#fbbf24;
  }
  body { background:var(--navy); color:var(--white); font-family:var(--font-body);
         min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem; }
  .card { background:var(--navy-mid); border:1px solid var(--border); border-radius:20px;
          padding:2.5rem; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,.4); }
  .logo { font-family:var(--font); font-size:1.1rem; font-weight:700; color:var(--green);
          margin-bottom:2rem; display:flex; align-items:center; gap:.5rem; }
  .logo-dot { width:8px; height:8px; background:var(--green); border-radius:50%; display:inline-block; }
  h1 { font-family:var(--font); font-size:1.5rem; font-weight:700; letter-spacing:-.02em; margin-bottom:.5rem; }
  .sub { color:var(--muted); font-size:.88rem; line-height:1.6; margin-bottom:2rem; }
  label { display:block; font-family:var(--font); font-size:.73rem; font-weight:600;
          color:var(--muted); letter-spacing:.07em; text-transform:uppercase; margin-bottom:.45rem; }
  .input-wrap { position:relative; margin-bottom:1.25rem; }
  input[type=password], input[type=text] {
    width:100%; padding:.85rem 2.8rem .85rem 1rem; background:rgba(255,255,255,.06);
    border:1px solid var(--border); border-radius:10px; color:var(--white);
    font-family:var(--font-body); font-size:.92rem; outline:none; transition:border-color .2s;
  }
  input:focus { border-color:var(--green); }
  input.err { border-color:var(--red); }
  .toggle { position:absolute; right:.85rem; top:50%; transform:translateY(-50%);
            background:none; border:none; color:var(--muted); cursor:pointer; padding:0; }
  .toggle:hover { color:var(--white); }
  /* Strength meter */
  .strength-wrap { margin-top:-.75rem; margin-bottom:1.25rem; }
  .strength-bar { display:flex; gap:3px; height:4px; }
  .strength-seg { flex:1; border-radius:2px; background:rgba(255,255,255,.1); transition:background .3s; }
  .strength-label { font-size:.75rem; color:var(--muted); margin-top:.35rem; }
  /* Error */
  .error-msg { background:rgba(248,113,113,.1); border:1px solid rgba(248,113,113,.3);
               border-radius:10px; padding:.75rem 1rem; font-size:.85rem; color:var(--red);
               margin-bottom:1.25rem; }
  .btn { width:100%; padding:1rem; background:var(--green); color:#0f1d2e; font-family:var(--font);
         font-size:.95rem; font-weight:700; border:none; border-radius:12px; cursor:pointer;
         transition:background .2s, transform .15s; }
  .btn:hover { background:var(--green-dark); transform:translateY(-1px); }
  .btn-outline { width:100%; padding:1rem; background:transparent; color:var(--green);
                 border:1px solid rgba(62,207,120,.4); font-family:var(--font); font-size:.95rem;
                 font-weight:700; border-radius:12px; cursor:pointer; transition:.2s; }
  .btn-outline:hover { background:var(--green-glow); }
  .back-link { display:block; text-align:center; margin-top:1.5rem; color:var(--green);
               font-size:.85rem; text-decoration:none; opacity:.85; }
  .back-link:hover { opacity:1; }
  .center { text-align:center; }
  .big-icon { font-size:2.5rem; margin-bottom:1rem; }
</style>
</head>
<body>
<div class="card">
  <div class="logo"><span class="logo-dot"></span> Upskill Education</div>

  <?php if ($step === 'invalid'): ?>
    <div class="center">
      <div class="big-icon">🔗</div>
      <h1><?= $tr['inv_h'] ?></h1>
      <p class="sub"><?= $tr['inv_p'] ?></p>
      <a href="<?= $forgotPage ?>"><button class="btn"><?= $tr['inv_btn'] ?></button></a>
    </div>

  <?php elseif ($step === 'success'): ?>
    <div class="center">
      <div class="big-icon">✅</div>
      <h1><?= $tr['ok_h'] ?></h1>
      <p class="sub"><?= $tr['ok_p'] ?></p>
      <a href="<?= $loginPage ?>"><button class="btn"><?= $tr['ok_btn'] ?></button></a>
    </div>

  <?php else: ?>
    <h1><?= $tr['heading'] ?></h1>
    <p class="sub"><?= $tr['sub'] ?></p>

    <?php if ($errMsg): ?>
      <div class="error-msg">⚠ <?= htmlspecialchars($errMsg) ?></div>
    <?php endif; ?>

    <form method="POST" action="?token=<?= urlencode($token) ?>&lang=<?= urlencode($lang) ?>" onsubmit="return validateForm()">
      <label for="password"><?= $tr['lbl_pw'] ?></label>
      <div class="input-wrap">
        <input type="password" id="password" name="password"
               placeholder="<?= $tr['ph_pw'] ?>" maxlength="200"
               autocomplete="new-password" oninput="checkStrength(this.value)"
               class="<?= $error ? 'err' : '' ?>">
        <button type="button" class="toggle" onclick="toggleVis('password',this)" aria-label="Toggle visibility">
          <svg id="eye-pw" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
      <div class="strength-wrap">
        <div class="strength-bar">
          <div class="strength-seg" id="s1"></div><div class="strength-seg" id="s2"></div>
          <div class="strength-seg" id="s3"></div><div class="strength-seg" id="s4"></div>
        </div>
        <div class="strength-label" id="strength-lbl"></div>
      </div>

      <label for="password2"><?= $tr['lbl_pw2'] ?></label>
      <div class="input-wrap">
        <input type="password" id="password2" name="password2"
               placeholder="<?= $tr['ph_pw2'] ?>" maxlength="200"
               autocomplete="new-password"
               class="<?= $error === 'mismatch' ? 'err' : '' ?>">
        <button type="button" class="toggle" onclick="toggleVis('password2',this)" aria-label="Toggle visibility">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>

      <button type="submit" class="btn"><?= $tr['btn'] ?></button>
    </form>
    <a href="<?= $loginPage ?>" class="back-link"><?= $tr['back'] ?></a>
  <?php endif; ?>
</div>
<script>
const STRENGTH = <?= json_encode($tr['strength']) ?>;
const colors   = ['#f87171','#fbbf24','#fbbf24','#3ecf78','#3ecf78'];

function checkStrength(pw) {
  let score = 0;
  if (pw.length >= 8)  score++;
  if (pw.length >= 12) score++;
  if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  score = Math.min(score, 4);
  const col = colors[score];
  for (let i = 1; i <= 4; i++) {
    document.getElementById('s'+i).style.background = i <= score+1 ? col : 'rgba(255,255,255,.1)';
  }
  document.getElementById('strength-lbl').textContent = pw.length > 0 ? STRENGTH[score] : '';
  document.getElementById('strength-lbl').style.color = col;
}

function toggleVis(id, btn) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

function validateForm() {
  const pw1 = document.getElementById('password').value;
  const pw2 = document.getElementById('password2').value;
  if (pw1 !== pw2) {
    document.getElementById('password2').classList.add('err');
    return false;
  }
  return true;
}
</script>
</body>
</html>
