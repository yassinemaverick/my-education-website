<?php
/**
 * ONE-TIME student seeder — visit once, then it deletes itself.
 * Default password for all students: Upskill2026
 */
require_once __DIR__ . '/db.php';
$pdo = db();

$students = [
    ['yasmine.alaoui',  'Yasmine Alaoui'],
    ['karim.benali',    'Karim Benali'],
    ['fatima.ezzahra',  'Fatima Ezzahra Idrissi'],
    ['mehdi.tazi',      'Mehdi Tazi'],
    ['nadia.cherkaoui', 'Nadia Cherkaoui'],
    ['omar.fassi',      'Omar El Fassi'],
    ['salma.berrada',   'Salma Berrada'],
    ['youssef.naciri',  'Youssef Naciri'],
    ['hind.lahlou',     'Hind Lahlou'],
    ['amine.kettani',   'Amine Kettani'],
];

$hash = password_hash('Upskill2026', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT IGNORE INTO users (username, full_name, role, password) VALUES (?, ?, 'student', ?)");

$inserted = 0;
$skipped  = 0;
foreach ($students as [$username, $full_name]) {
    $stmt->execute([$username, $full_name, $hash]);
    if ($stmt->rowCount() > 0) $inserted++;
    else $skipped++;
}

// Self-destruct
@unlink(__FILE__);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Seed done</title>
<style>
  body { font-family: sans-serif; background: #0f1d2e; color: #fff; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
  .box { background: #162436; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 2rem 3rem; text-align: center; }
  h2 { color: #3ecf78; margin-bottom: 0.5rem; }
  p { color: rgba(255,255,255,0.6); }
  table { margin: 1.5rem auto; border-collapse: collapse; text-align: left; }
  td, th { padding: 0.4rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.08); font-size: 0.9rem; }
  th { color: #3ecf78; font-weight: 600; }
</style>
</head>
<body>
<div class="box">
  <h2>✓ Étudiants ajoutés</h2>
  <p><?= $inserted ?> insérés · <?= $skipped ?> déjà existants · fichier auto-supprimé</p>
  <table>
    <tr><th>Identifiant</th><th>Nom complet</th><th>Mot de passe</th></tr>
    <?php foreach ($students as [$u, $n]): ?>
    <tr><td><?= htmlspecialchars($u) ?></td><td><?= htmlspecialchars($n) ?></td><td>Upskill2026</td></tr>
    <?php endforeach; ?>
  </table>
</div>
</body>
</html>
