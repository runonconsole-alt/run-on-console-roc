<?php
/**
 * Run On Console — messages from the website's forms.
 *
 * POST /api/v1/messages.php  (JSON)
 *   kind      'contact' | 'write_for_us'
 *   name, email, subject, message
 *   meta      optional object (write for us: plan, niche, title, contact)
 *   website   must stay empty (hidden field that only bots fill in)
 *
 * The message is stored in roc_messages (CMS -> Messages, where it can be answered)
 * and a notification email goes to support@ (contact) or comments@ (write for us)
 * through the site's existing email queue. Tables: api/v1/cron/cli-messages.php.
 */

require_once __DIR__ . '/account-runtime.php';   // config, database, email queue (rocQueue / rocRespond)

const ROC_MSG_INBOX = ['contact' => 'support@runonconsole.com', 'write_for_us' => 'comments@runonconsole.com'];
const ROC_MSG_LABEL = ['contact' => 'Contact form', 'write_for_us' => 'Write for us'];

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

function rocMsgOut(int $code, array $data): void {
    http_response_code($code);
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') rocMsgOut(405, ['success' => false, 'error' => 'POST required.']);

// Only this website's own pages may post here.
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
$host = strtolower((string)parse_url($origin, PHP_URL_HOST));
$self = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
if ($host === '' || ($host !== $self && $host !== 'runonconsole.com' && $host !== 'www.runonconsole.com')) {
    rocMsgOut(403, ['success' => false, 'error' => 'Please send the form from runonconsole.com.']);
}

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;

// The CMS calls this after queueing a reply, so it is sent now (it only sends mail that is already queued).
if (!empty($in['dispatch'])) {
    $db = getDBConnection();
    $sent = 0;
    if ($db) { while ($sent < 3 && rocDispatch($db)) $sent++; }
    rocMsgOut(200, ['success' => true, 'processed' => $sent]);
}

$clip = function ($v, int $max): string {
    $v = is_scalar($v) ? trim((string)$v) : '';
    $v = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    return mb_substr($v, 0, $max);
};
$kind    = isset(ROC_MSG_INBOX[$in['kind'] ?? '']) ? (string)$in['kind'] : 'contact';
$name    = $clip($in['name'] ?? '', 120);
$email   = strtolower($clip($in['email'] ?? '', 150));
$subject = $clip($in['subject'] ?? '', 200);
$message = $clip($in['message'] ?? '', 5000);
$meta = [];
foreach ((is_array($in['meta'] ?? null) ? $in['meta'] : []) as $k => $v) {
    if (count($meta) >= 10 || !preg_match('/^[a-z_]{1,30}$/', (string)$k)) continue;
    $meta[$k] = $clip($v, 500);
}

// Bots fill in every field, including the hidden one: pretend it worked.
if ($clip($in['website'] ?? '', 200) !== '') rocMsgOut(200, ['success' => true, 'reference' => 'MSG-' . random_int(100000, 999999)]);

if ($name === '' || $message === '') rocMsgOut(422, ['success' => false, 'error' => 'Please fill in your name and message.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) rocMsgOut(422, ['success' => false, 'error' => 'Please enter a valid email address.']);

$db = getDBConnection();
if (!$db) rocMsgOut(503, ['success' => false, 'error' => 'The server is busy. Please try again in a minute.']);

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
try {
    $q = $db->prepare('SELECT COUNT(*) FROM roc_messages WHERE (ip = ? OR email = ?) AND created_at > ?');
    $q->execute([$ip, $email, date('Y-m-d H:i:s', time() - 600)]);
    if ((int)$q->fetchColumn() >= 5) rocMsgOut(429, ['success' => false, 'error' => 'Too many messages. Please wait a few minutes and try again.']);

    $q = $db->prepare('INSERT INTO roc_messages (kind, name, email, subject, message, meta_json, status, ip, user_agent, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, \'new\', ?, ?, ?, ?)');
    $now = date('Y-m-d H:i:s');
    $q->execute([$kind, $name, $email, $subject, $message, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                 $ip, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), $now, $now]);
    $id = (int)$db->lastInsertId();
} catch (\Throwable $e) {
    error_log('[ROC messages] insert failed: ' . get_class($e));
    rocMsgOut(500, ['success' => false, 'error' => 'Your message could not be saved. Please email ' . ROC_MSG_INBOX[$kind] . ' instead.']);
}

/* Notification to the inbox; the reply itself is written in the CMS. */
$esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$rows = ['Name' => $name, 'Email' => $email] + ($subject !== '' ? ['Subject' => $subject] : []);
foreach ($meta as $k => $v) $rows[ucfirst(str_replace('_', ' ', $k))] = $v;
$html = '<!doctype html><html><body style="background:#eef7f4;font-family:Arial,sans-serif;padding:24px"><main style="max-width:600px;margin:auto;background:#fff;padding:28px;border-radius:16px">'
      . '<h2 style="color:#047857;margin-top:0">New message: ' . $esc(ROC_MSG_LABEL[$kind]) . '</h2><table style="border-collapse:collapse;width:100%">';
foreach ($rows as $k => $v) $html .= '<tr><td style="padding:6px 10px;color:#64748b;width:120px;vertical-align:top">' . $esc($k) . '</td><td style="padding:6px 10px">' . $esc($v) . '</td></tr>';
$html .= '</table><div style="white-space:pre-wrap;background:#f8fafc;border-radius:10px;padding:14px;margin:16px 0">' . $esc($message) . '</div>'
       . '<p><a style="display:inline-block;padding:12px 20px;background:#047857;color:#fff;border-radius:8px;text-decoration:none" href="https://runonconsole.com/cms/#messages">Reply in the CMS</a></p>'
       . '<p style="color:#64748b;font-size:12px">Message #' . $id . '</p></main></body></html>';
$job = null;
try { $job = rocQueue($db, ROC_MSG_INBOX[$kind], '[' . ROC_MSG_LABEL[$kind] . '] ' . ($subject !== '' ? $subject : $name), $html, 5); }
catch (\Throwable $e) { error_log('[ROC messages] queue failed: ' . get_class($e)); }

$data = ['success' => true, 'reference' => 'MSG-' . $id,
         'message' => 'Thank you! Your message has been received. We usually reply within 2 working days.'];
if ($job) rocRespond(201, $data, $job);   // sends the notification right away
rocMsgOut(201, $data);
