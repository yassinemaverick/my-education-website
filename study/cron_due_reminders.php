<?php
/**
 * cron_due_reminders.php — Daily cron script for assignment due-date reminders
 *
 * Sends an email to each student who has a pending assignment due tomorrow
 * and hasn't submitted yet. Uses an email_reminders table to prevent
 * duplicate sends.
 *
 * Hostinger cron setup (hPanel → Advanced → Cron Jobs):
 *   Schedule : 0 8 * * *   (daily at 08:00)
 *   Command  : php /home/<username>/public_html/study/cron_due_reminders.php
 *
 * Can also be triggered manually: php cron_due_reminders.php
 */

define('UPSKILL_CRON', true);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_helper.php';

$pdo = db();

// Ensure reminders log table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS email_reminders (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT UNSIGNED NOT NULL,
        student_id    INT UNSIGNED NOT NULL,
        sent_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_reminder (assignment_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Find assignments due tomorrow where students haven't yet submitted
$stmt = $pdo->prepare("
    SELECT
        a.id          AS assignment_id,
        a.title_fr    AS title,
        a.subject_fr  AS subject,
        a.due_date,
        u.id          AS student_id,
        u.email,
        u.full_name,
        u.username
    FROM   assignments a
    JOIN   student_courses sc ON sc.course_id = a.course_id
    JOIN   users u            ON u.id = sc.student_id
    LEFT   JOIN assignment_submissions sub
             ON sub.assignment_id = a.id AND sub.student_id = u.id
    LEFT   JOIN email_reminders er
             ON er.assignment_id = a.id AND er.student_id = u.id
    WHERE  DATE(a.due_date) = CURDATE() + INTERVAL 1 DAY
      AND  (sub.id IS NULL OR sub.status = 'pending')
      AND  u.email IS NOT NULL AND u.email != ''
      AND  er.id IS NULL
");
$stmt->execute();
$pending = $stmt->fetchAll();

$sent = 0;
foreach ($pending as $row) {
    $name   = $row['full_name'] ?: $row['username'];
    $dueStr = date('D, d M Y', strtotime($row['due_date']));

    $infoRows = array_filter([
        ['label' => 'Assignment', 'value' => $row['title']],
        $row['subject'] ? ['label' => 'Subject', 'value' => $row['subject']] : null,
        ['label' => 'Due',        'value' => $dueStr . ' (tomorrow)'],
    ]);

    $body = upskill_email_body(
        "Hi {$name},",
        "Just a reminder — you have an assignment due tomorrow!",
        array_values($infoRows),
        'Submit now →'
    );

    $ok = upskill_send_email(
        $row['email'],
        "Reminder: \"{$row['title']}\" is due tomorrow",
        $body
    );

    if ($ok) {
        // Log to prevent duplicate sends
        try {
            $pdo->prepare("INSERT IGNORE INTO email_reminders (assignment_id, student_id) VALUES (?, ?)")
                ->execute([$row['assignment_id'], $row['student_id']]);
        } catch (Throwable $e) {}
        $sent++;
    }
}

$total = count($pending);
echo date('Y-m-d H:i:s') . " — Due reminders: {$sent}/{$total} sent.\n";
