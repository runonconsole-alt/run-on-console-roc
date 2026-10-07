<?php
/**
 * Run On Console — CMS Messages (contact and write-for-us forms). Administrators only.
 *
 * GET  /api/v1/cms/messages.php                 list (newest first) + counts
 * GET  /api/v1/cms/messages.php?id=12           one message with its replies
 * POST {action:'reply', id, body}               email the visitor and keep the reply
 * POST {action:'set_status', id, status}        new | replied | closed | spam
 *
 * Replies go through the site's email queue (email_queue). The CMS then asks
 * /api/v1/messages.php to send queued mail right away.
 */

require_once __DIR__ . '/config.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
header('Content-Type: application/json; charset=utf-8');
if (!$pdo) { http_response_code(503); echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']); exit; }

const ROC_MSG_STATUSES = ['new', 'replied', 'closed', 'spam'];

function rocMsgJson(int $code, array $data): void { http_response_code($code); echo json_encode($data); exit; }

function rocMsgRow(PDO $pdo, int $id): array {
    $q = $pdo->prepare('SELECT * FROM roc_messages WHERE id = ? LIMIT 1');
    $q->execute([$id]);
    $m = $q->fetch(PDO::FETCH_ASSOC);
    if (!$m) rocMsgJson(404, ['success' => false, 'error' => 'Message not found.']);
    $m['meta'] = $m['meta_json'] ? (json_decode($m['meta_json'], true) ?: []) : [];
    unset($m['meta_json']);
    return $m;
}

try { $pdo->query('SELECT 1 FROM roc_messages LIMIT 1'); }
catch (\Throwable $e) { rocMsgJson(200, ['success' => true, 'installed' => false, 'messages' => [], 'counts' => []]); }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $m = rocMsgRow($pdo, (int)$_GET['id']);
        $q = $pdo->prepare('SELECT r.id, r.body, r.sent_by, r.created_at, q.status AS email_status
                            FROM roc_message_replies r LEFT JOIN email_queue q ON q.id = r.email_job_id
                            WHERE r.message_id = ? ORDER BY r.id');
        $q->execute([(int)$m['id']]);
        $m['replies'] = $q->fetchAll(PDO::FETCH_ASSOC);
        rocMsgJson(200, ['success' => true, 'message' => $m]);
    }
    $rows = $pdo->query('SELECT id, kind, name, email, subject, SUBSTR(message, 1, 220) AS preview, meta_json, status, created_at, replied_at
                         FROM roc_messages ORDER BY id DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) { $r['meta'] = $r['meta_json'] ? (json_decode($r['meta_json'], true) ?: []) : []; unset($r['meta_json']); }
    unset($r);
    $counts = [];
    foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM roc_messages GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $c) $counts[$c['status']] = (int)$c['n'];
    rocMsgJson(200, ['success' => true, 'installed' => true, 'messages' => $rows, 'counts' => $counts]);
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$action = (string)($in['action'] ?? '');
$id = (int)($in['id'] ?? 0);
$now = date('Y-m-d H:i:s');

if ($action === 'set_status') {
    $status = (string)($in['status'] ?? '');
    if (!in_array($status, ROC_MSG_STATUSES, true)) rocMsgJson(422, ['success' => false, 'error' => 'Unknown status.']);
    rocMsgRow($pdo, $id);
    $pdo->prepare('UPDATE roc_messages SET status = ?, updated_at = ? WHERE id = ?')->execute([$status, $now, $id]);
    if (function_exists('logCmsAudit')) logCmsAudit('cms_message_status', 'message', (string)$id, ['status' => $status]);
    rocMsgJson(200, ['success' => true]);
}

if ($action === 'reply') {
    $m = rocMsgRow($pdo, $id);
    $body = trim((string)($in['body'] ?? ''));
    if ($body === '' || mb_strlen($body) > 10000) rocMsgJson(422, ['success' => false, 'error' => 'Write a reply (up to 10,000 characters).']);
    $by = (string)($session['username'] ?? $session['email'] ?? 'Run On Console');

    $esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $html = '<!doctype html><html><body style="background:#eef7f4;font-family:Arial,sans-serif;padding:24px"><main style="max-width:600px;margin:auto;background:#fff;padding:28px;border-radius:16px">'
          . '<h2 style="color:#047857;margin-top:0">Run On Console</h2>'
          . '<p>Hi ' . $esc($m['name']) . ',</p>'
          . '<div style="white-space:pre-wrap;line-height:1.6">' . $esc($body) . '</div>'
          . '<p style="margin-top:24px">— The Run On Console team<br><a href="https://runonconsole.com/" style="color:#047857">runonconsole.com</a></p>'
          . '<div style="margin-top:24px;border-left:3px solid #d1fae5;padding-left:12px;color:#64748b;font-size:13px">'
          . '<p style="margin:0 0 6px">Your message on ' . $esc(date('j M Y', strtotime((string)$m['created_at']))) . ':</p>'
          . '<div style="white-space:pre-wrap">' . $esc(mb_substr((string)$m['message'], 0, 2000)) . '</div></div>'
          . '</main></body></html>';
    $subject = 'Re: ' . ($m['subject'] !== '' ? $m['subject'] : 'your message to Run On Console');

    $job = null;
    try {
        $q = $pdo->prepare("INSERT INTO email_queue (to_email, subject, html_body, status, attempts, next_attempt_at, priority) VALUES (?, ?, ?, 'pending', 0, NOW(), 5)");
        $q->execute([$m['email'], $subject, $html]);
        $job = (int)$pdo->lastInsertId();
    } catch (\Throwable $e) {
        rocMsgJson(500, ['success' => false, 'error' => 'The email could not be queued. Nothing was sent.']);
    }
    $pdo->prepare('INSERT INTO roc_message_replies (message_id, body, sent_by, email_job_id, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$id, $body, mb_substr($by, 0, 100), $job, $now]);
    $pdo->prepare("UPDATE roc_messages SET status = 'replied', replied_at = ?, updated_at = ? WHERE id = ?")->execute([$now, $now, $id]);
    if (function_exists('logCmsAudit')) logCmsAudit('cms_message_reply', 'message', (string)$id, ['job' => $job]);
    rocMsgJson(200, ['success' => true, 'job' => $job]);
}

rocMsgJson(400, ['success' => false, 'error' => 'Unknown action.']);
