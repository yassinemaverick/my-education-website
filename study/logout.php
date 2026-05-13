<?php
require_once __DIR__ . '/session.php';

$pages = ['en' => 'index2.php', 'fr' => 'index2-fr.php', 'ar' => 'index2-ar.php'];
$lang  = $_SESSION['lang'] ?? 'fr';
$back  = $pages[$lang] ?? 'index2-fr.php';

$_SESSION = [];
session_destroy();
setcookie(session_name(), '', time() - 3600, '/', '', true, true);
header('Location: ' . $back);
exit;
