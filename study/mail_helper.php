<?php
/**
 * mail_helper.php — HTML email sender for Upskill notifications
 * Uses PHP mail() which works on Hostinger shared hosting.
 */

function upskill_send_email(string $to, string $subject, string $bodyHtml): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $from = $_ENV['MAIL_USER'] ?? '';

    $headers  = "From: Upskill Education <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
          . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
          . '<body style="font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:20px;">'
          . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">'
          . '<div style="background:linear-gradient(135deg,#3b82f6,#7c3aed);padding:22px 32px;">'
          . '<span style="color:#fff;font-size:22px;font-weight:700;letter-spacing:-.5px;">Upskill</span>'
          . '</div>'
          . '<div style="padding:28px 32px;">'
          . $bodyHtml
          . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:28px 0 18px;">'
          . '<p style="color:#9ca3af;font-size:12px;margin:0;">Upskill Education &middot; study.upskill-edu.com<br>'
          . 'You received this email because you are enrolled in an Upskill course.</p>'
          . '</div></div></body></html>';

    return @mail($to, $subject, $html, $headers);
}

/**
 * Build a standard email body block (heading + rows + optional CTA button).
 *
 * @param string   $greeting   e.g. "Hi Ahmed,"
 * @param string   $intro      e.g. "A new assignment has been posted."
 * @param array    $rows       [['label'=>'Assignment','value'=>'Unit 5'], ...]
 * @param string   $ctaLabel   Button label (empty = no button)
 * @param string   $ctaUrl     Button URL
 */
function upskill_email_body(
    string $greeting,
    string $intro,
    array  $rows    = [],
    string $ctaLabel = '',
    string $ctaUrl   = 'https://study.upskill-edu.com/dashboard-student'
): string {
    $html  = '<p style="font-size:15px;color:#111827;margin:0 0 8px;">' . htmlspecialchars($greeting) . '</p>';
    $html .= '<p style="font-size:15px;color:#374151;margin:0 0 20px;">' . htmlspecialchars($intro) . '</p>';

    if ($rows) {
        $html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">';
        foreach ($rows as $r) {
            $html .= '<tr>'
                   . '<td style="padding:8px 12px;background:#f9fafb;border:1px solid #e5e7eb;font-size:13px;color:#6b7280;width:36%;font-weight:600;">'
                   . htmlspecialchars($r['label']) . '</td>'
                   . '<td style="padding:8px 12px;background:#fff;border:1px solid #e5e7eb;font-size:13px;color:#111827;">'
                   . htmlspecialchars($r['value']) . '</td>'
                   . '</tr>';
        }
        $html .= '</table>';
    }

    if ($ctaLabel) {
        $html .= '<p style="text-align:center;margin:0 0 8px;">'
               . '<a href="' . htmlspecialchars($ctaUrl) . '" '
               . 'style="display:inline-block;background:linear-gradient(135deg,#3b82f6,#7c3aed);color:#fff;'
               . 'text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px;">'
               . htmlspecialchars($ctaLabel) . '</a></p>';
    }

    return $html;
}
