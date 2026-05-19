<?php
/**
 * mailer.php — Minimal SMTP mailer (no external dependencies)
 * Reads MAIL_HOST / MAIL_PORT / MAIL_USER / MAIL_PASS from $_ENV
 */

function smtp_send(string $to, string $subject, string $textBody): bool {
    $host = $_ENV['MAIL_HOST'] ?? '';
    $port = (int)($_ENV['MAIL_PORT'] ?? 465);
    $user = $_ENV['MAIL_USER'] ?? '';
    $pass = $_ENV['MAIL_PASS'] ?? '';

    if (!$host || !$user || !$pass) {
        error_log('mailer: MAIL_HOST / MAIL_USER / MAIL_PASS not configured in .env');
        return false;
    }

    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
    ]);

    $prefix = ($port === 465) ? 'ssl://' : '';
    $sock   = @stream_socket_client("{$prefix}{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) {
        error_log("mailer: cannot connect to {$host}:{$port} — {$errstr}");
        return false;
    }
    stream_set_timeout($sock, 10);

    $r = fn() => fgets($sock, 1024);
    $w = function (string $cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };

    $greeting = $r();
    if (!str_starts_with(trim($greeting), '220')) { fclose($sock); return false; }

    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $w("EHLO {$domain}");
    do { $line = $r(); } while ($line && $line[3] !== ' ');

    if ($port === 587) {
        $w("STARTTLS");
        $r();
        stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
        $w("EHLO {$domain}");
        do { $line = $r(); } while ($line && $line[3] !== ' ');
    }

    $w("AUTH LOGIN");
    $r();
    $w(base64_encode($user));
    $r();
    $w(base64_encode($pass));
    $authReply = trim($r());
    if (!str_starts_with($authReply, '235')) {
        error_log("mailer: AUTH failed — {$authReply}");
        fclose($sock);
        return false;
    }

    $w("MAIL FROM:<{$user}>");
    $r();
    $w("RCPT TO:<{$to}>");
    $rcptReply = trim($r());
    if (!str_starts_with($rcptReply, '250')) {
        error_log("mailer: RCPT TO rejected — {$rcptReply}");
        fclose($sock);
        return false;
    }

    $w("DATA");
    $r();

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedBody    = chunk_split(base64_encode($textBody));

    $message = "From: Upskill Education <{$user}>\r\n"
             . "To: {$to}\r\n"
             . "Subject: {$encodedSubject}\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: base64\r\n"
             . "\r\n"
             . $encodedBody;

    $w($message);
    $w(".");
    $dataReply = trim($r());

    $w("QUIT");
    fclose($sock);

    if (!str_starts_with($dataReply, '250')) {
        error_log("mailer: DATA rejected — {$dataReply}");
        return false;
    }
    return true;
}
