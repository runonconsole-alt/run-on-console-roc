<?php
/**
 * Run On Console (ROC) — members: wishlist and weekly email (Deploy 70). See member-lib.php.
 *
 * GET  /api/v1/member.php                     {signed_in, items: [...], keys: ['product:slug', ...], weekly}
 * POST {action:'toggle', type: product|game, key}    save / remove (signed in)
 * POST {action:'weekly', on: true|false}             the weekly email on or off (signed in)
 * GET  /api/v1/member.php?unsub=TOKEN         one-click unsubscribe from the weekly email (from the email)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/member-lib.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function rocMemberOut(int $code, array $d): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDBConnection();
if (!$pdo) rocMemberOut(503, ['success' => false, 'error' => 'Please try again in a moment.']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ROOT = dirname(__DIR__, 2);

/* One-click unsubscribe (no sign-in needed: the link in the email carries the member's own token). */
if (isset($_GET['unsub'])) {
    rocMemberTables($pdo);
    $t = (string)$_GET['unsub'];
    $ok = false;
    if (preg_match('/^[a-f0-9]{40}$/', $t)) {
        $st = $pdo->prepare('UPDATE roc_member_mail SET weekly = 0 WHERE token = ?');
        $st->execute([$t]);
        $ok = $st->rowCount() > 0 || (bool)$pdo->query('SELECT 1 FROM roc_member_mail WHERE token = ' . $pdo->quote($t))->fetchColumn();
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
       . '<title>Unsubscribed | Run On Console</title><body style="font-family:Arial,sans-serif;background:#f1f5f9;margin:0;padding:40px 16px;color:#0f172a">'
       . '<div style="max-width:460px;margin:0 auto;background:#fff;border-radius:16px;padding:28px;text-align:center">'
       . ($ok ? '<h1 style="font-size:22px">You are unsubscribed</h1><p>You will not get the weekly Run On Console email any more. You can turn it on again in your profile.</p>'
              : '<h1 style="font-size:22px">Link not valid</h1><p>This unsubscribe link is not valid. Turn the weekly email off in your profile instead.</p>')
       . '<p><a href="https://runonconsole.com/" style="color:#047857;font-weight:bold">Back to Run On Console</a></p></div></body>';
    exit;
}

$uid = 0;
try { if (function_exists('validateDatabaseSession') && validateDatabaseSession()) $uid = (int)($_SESSION['user_id'] ?? 0); } catch (\Throwable $e) { $uid = 0; }
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    if ($uid <= 0) rocMemberOut(200, ['success' => true, 'signed_in' => false, 'items' => [], 'keys' => []]);
    $items = rocWishlist($pdo, $ROOT, $uid);
    rocMemberOut(200, ['success' => true, 'signed_in' => true, 'items' => $items,
        'keys' => array_map(function ($i) { return $i['type'] . ':' . $i['key']; }, $items), 'weekly' => rocMemberMail($pdo, $uid)['weekly']]);
}

// Same-site requests only (a JSON body cannot be sent by a form on another site; the Origin must be ours when given).
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === false
    || ($origin !== '' && strtolower((string)parse_url($origin, PHP_URL_HOST)) !== strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''))))) rocMemberOut(403, ['success' => false, 'error' => 'Not allowed.']);
if ($uid <= 0) rocMemberOut(401, ['success' => false, 'login' => true, 'error' => 'Please sign in to save items.']);

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$act = (string)($in['action'] ?? '');
if ($act === 'toggle') {
    $saved = rocWishlistToggle($pdo, $ROOT, $uid, (string)($in['type'] ?? ''), (string)($in['key'] ?? ''));
    if ($saved === null) rocMemberOut(422, ['success' => false, 'error' => 'This item cannot be saved.']);
    rocMemberOut(200, ['success' => true, 'saved' => $saved]);
}
if ($act === 'weekly') {
    rocMemberWeeklySet($pdo, $uid, !empty($in['on']));
    rocMemberOut(200, ['success' => true, 'weekly' => !empty($in['on']),
        'message' => !empty($in['on']) ? 'Done: you will get the weekly email every Friday.' : 'Done: the weekly email is off.']);
}
rocMemberOut(400, ['success' => false, 'error' => 'Unknown action.']);
