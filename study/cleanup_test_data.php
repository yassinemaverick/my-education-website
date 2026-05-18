<?php
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
session_start();

// Only admin can run this
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Log in as admin first.');
}

require_once __DIR__ . '/db.php';
$pdo = db();

$confirmed = ($_GET['confirm'] ?? '') === 'yes_delete_all_test_data';

if (!$confirmed) {
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:600px;margin:4rem auto;padding:1rem;">';
    echo '<h2 style="color:#c00;">⚠️ Delete all test students & teachers</h2>';
    echo '<p>This will permanently:</p><ul>';
    echo '<li>Delete all users with role <strong>student</strong> or <strong>teacher</strong></li>';
    echo '<li>Truncate: <code>class_group_members, attendance, assignment_submissions, notifications, student_courses, teacher_courses</code></li>';
    echo '</ul>';
    echo '<p><strong>Admin accounts and all class groups, courses, assignments, announcements are kept.</strong></p>';
    echo '<p><a href="?confirm=yes_delete_all_test_data" style="background:#c00;color:#fff;padding:.6rem 1.2rem;text-decoration:none;border-radius:6px;">Confirm — delete everything</a></p>';
    echo '</body></html>';
    exit;
}

try {
    $pdo->beginTransaction();

    // Truncate dependent tables first
    $pdo->exec("DELETE FROM class_group_members WHERE user_id IN (SELECT id FROM users WHERE role IN ('student','teacher'))");
    $pdo->exec("DELETE FROM attendance WHERE student_id IN (SELECT id FROM users WHERE role = 'student') OR teacher_id IN (SELECT id FROM users WHERE role = 'teacher')");
    $pdo->exec("DELETE FROM assignment_submissions WHERE student_id IN (SELECT id FROM users WHERE role = 'student')");
    $pdo->exec("DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE role IN ('student','teacher'))");
    $pdo->exec("DELETE FROM student_courses WHERE student_id IN (SELECT id FROM users WHERE role = 'student')");
    $pdo->exec("DELETE FROM teacher_courses WHERE teacher_id IN (SELECT id FROM users WHERE role = 'teacher')");

    // Delete the users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role IN ('student','teacher')");
    $stmt->execute();
    $count = $stmt->fetchColumn();

    $pdo->exec("DELETE FROM users WHERE role IN ('student','teacher')");

    $pdo->commit();

    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:600px;margin:4rem auto;padding:1rem;">';
    echo '<h2 style="color:#080;">Done</h2>';
    echo "<p>Deleted <strong>{$count} users</strong> (students &amp; teachers) and all related rows.</p>";
    echo '<p>Class groups, courses, assignments, and admin accounts are intact.</p>';
    echo '<p><strong>Delete this file now: <code>study/cleanup_test_data.php</code></strong></p>';
    echo '</body></html>';

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo '<pre style="color:red;">Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}
