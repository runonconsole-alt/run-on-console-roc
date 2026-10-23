#!/usr/local/bin/php -q
<?php
/**
 * Run On Console — emails sent straight to the site's mailboxes are saved in CMS > Messages too.
 *
 * cPanel > Forwarders > Add Forwarder > "Pipe to a Program": public_html/api/v1/cron/mail-in.php
 * (one for each of contact@, support@, suggestions@, comments@, alerts@; keep the Gmail forwarders as well).
 * The file must be executable: chmod 755 public_html/api/v1/cron/mail-in.php
 *
 * The mail server hands the whole email to this script. It saves sender, subject and the plain text
 * (no attachments) in roc_messages with the mailbox's kind and meta {"via":"email","to":"..."}.
 * Skipped: the site's own notifications (from noreply@, already saved by the forms), mail marked as
 * spam and empty mail. It never prints anything: any output would bounce the email back to the sender.
 *
 *   php mail-in.php --test < some-email.eml    shows what would be saved, saves nothing
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
ob_start();
$TEST = in_array('--test', array_slice($argv ?? [], 1), true);
$KINDS = ['contact' => 'contact', 'support' => 'support', 'suggestions' => 'suggestion', 'comments' => 'comment', 'alerts' => 'alerts'];

function rocMiOut(int $code = 0): void {
    global $TEST;
    $o = ob_get_clean();
    if ($TEST) echo $o;
    exit($code === 0 || !$GLOBALS['TEST'] ? 0 : $code);   // a failure must not bounce the email either
}

try {
    $raw = (string)stream_get_contents(STDIN, 3 * 1024 * 1024);
    if (trim($raw) === '') rocMiOut();
    [$head, $body] = array_pad(preg_split("/\r?\n\r?\n/", $raw, 2), 2, '');
    $head = (string)preg_replace("/\r?\n[ \t]+/", ' ', $head);          // unfold long header lines
    $hdr = [];
    foreach (preg_split("/\r?\n/", $head) as $line) {
        if (preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $line, $m)) {
            $k = strtolower($m[1]);
            if (!isset($hdr[$k])) $hdr[$k] = $m[2];
        }
    }
    $dec = function (string $s): string {
        $d = function_exists('iconv_mime_decode') ? @iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') : false;
        return trim($d !== false ? $d : $s);
    };
    $from = $dec((string)($hdr['from'] ?? ''));
    $email = preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : (preg_match('/[^\s<>"]+@[^\s<>"]+/', $from, $m) ? $m[0] : '');
    $email = strtolower(trim($email));
    $name = trim((string)preg_replace('/<[^>]*>/', '', $from), " \t\"'") ?: strtok($email, '@');
    $subject = mb_substr($dec((string)($hdr['subject'] ?? '')), 0, 200);

    // Which mailbox: the original recipient (Delivered-To / X-Original-To / To).
    $to = strtolower((string)($hdr['x-original-to'] ?? $hdr['delivered-to'] ?? $hdr['envelope-to'] ?? $hdr['to'] ?? ''));
    $box = preg_match('/\b(contact|support|suggestions|comments|alerts)@runonconsole\.com/', $to, $m) ? $m[1] : 'contact';
    if (preg_match('/^(contact|support|suggestions|comments|alerts)@runonconsole\.com$/', strtolower((string)(getenv('RECIPIENT') ?: getenv('LOCAL_PART') . '@' . getenv('DOMAIN'))), $m)) $box = $m[1];

    // Not saved: our own notifications (the forms already saved them), spam, mail without a sender.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/@runonconsole\.com$/', $email) && strpos($email, 'noreply@') === 0) rocMiOut();
    if (preg_match('/^yes/i', (string)($hdr['x-spam-status'] ?? '')) || preg_match('/\*{5,}|^\s*yes/i', (string)($hdr['x-spam-flag'] ?? ''))) rocMiOut();
    if (preg_match('/^(mailer-daemon|postmaster)@/i', $email)) rocMiOut();

    // The plain text: text/plain part (or text/html without tags), decoded.
    $text = '';
    $part = function (string $ctype, string $cte, string $content) {
        $cte = strtolower(trim($cte));
        if ($cte === 'base64') $content = (string)base64_decode((string)preg_replace('/\s+/', '', $content));
        elseif ($cte === 'quoted-printable') $content = quoted_printable_decode($content);
        if (preg_match('/charset="?([A-Za-z0-9_-]+)/i', $ctype, $cm) && strtolower($cm[1]) !== 'utf-8' && function_exists('mb_convert_encoding')) {
            $conv = @mb_convert_encoding($content, 'UTF-8', $cm[1]); if (is_string($conv)) $content = $conv;
        }
        if (stripos($ctype, 'text/html') !== false) {
            $content = (string)preg_replace('#<(br|/p|/div|/li|/h[1-6])[^>]*>#i', "\n", $content);
            $content = html_entity_decode(strip_tags((string)preg_replace('#<(style|script)[^>]*>.*?</\1>#is', '', $content)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $content;
    };
    $ctype = (string)($hdr['content-type'] ?? 'text/plain');
    if (preg_match('/boundary="?([^";]+)"?/i', $ctype, $bm)) {
        $plain = ''; $html = '';
        $walk = function (string $b, string $boundary) use (&$walk, &$plain, &$html, $part) {
            foreach (explode('--' . $boundary, $b) as $chunk) {
                [$ph, $pb] = array_pad(preg_split("/\r?\n\r?\n/", ltrim($chunk, "\r\n"), 2), 2, '');
                $ph = (string)preg_replace("/\r?\n[ \t]+/", ' ', $ph);
                $pct = preg_match('/^content-type:\s*(.+)$/im', $ph, $x) ? $x[1] : '';
                $pte = preg_match('/^content-transfer-encoding:\s*(.+)$/im', $ph, $x) ? $x[1] : '';
                if (preg_match('/boundary="?([^";]+)"?/i', $pct, $nb)) { $walk($pb, $nb[1]); continue; }
                if (preg_match('/^content-disposition:\s*attachment/im', $ph)) continue;
                if ($plain === '' && stripos($pct, 'text/plain') !== false) $plain = $part($pct, $pte, $pb);
                elseif ($html === '' && stripos($pct, 'text/html') !== false) $html = $part($pct, $pte, $pb);
            }
        };
        $walk($body, $bm[1]);
        $text = $plain !== '' ? $plain : $html;
    } else {
        $text = $part($ctype, (string)($hdr['content-transfer-encoding'] ?? ''), $body);
    }
    $text = trim((string)preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text)));
    $text = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    $text = mb_substr($text, 0, 5000);
    if ($text === '' && $subject === '') rocMiOut();

    $row = ['kind' => $GLOBALS['KINDS'][$box], 'name' => mb_substr($name, 0, 120), 'email' => mb_substr($email, 0, 150),
            'subject' => $subject, 'message' => $text !== '' ? $text : '(no text)', 'meta' => ['via' => 'email', 'to' => $box . '@runonconsole.com']];
    if ($TEST) { print_r($row); rocMiOut(); }

    $ROOT = dirname(__DIR__, 3);
    require_once $ROOT . '/api/v1/config.php';
    $db = function_exists('getDBConnection') ? getDBConnection() : null;
    if (!$db) rocMiOut();
    $now = date('Y-m-d H:i:s');
    // The same email delivered twice (two forwarders) is saved once.
    $q = $db->prepare('SELECT id FROM roc_messages WHERE email = ? AND subject = ? AND created_at > ? LIMIT 1');
    $q->execute([$row['email'], $row['subject'], date('Y-m-d H:i:s', time() - 120)]);
    if ($q->fetchColumn()) rocMiOut();
    $db->prepare('INSERT INTO roc_messages (kind, name, email, subject, message, meta_json, status, ip, user_agent, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, ?, \'new\', ?, ?, ?, ?)')
       ->execute([$row['kind'], $row['name'], $row['email'], $row['subject'], $row['message'],
                  json_encode($row['meta'], JSON_UNESCAPED_UNICODE), '', 'email', $now, $now]);
} catch (\Throwable $e) {
    error_log('[ROC mail-in] ' . get_class($e) . ': ' . $e->getMessage());
}
rocMiOut();
