<?php
/**
 * Run On Console — publish or hide a review from the link in its notification email (Deploy 151).
 *
 *   GET /api/v1/review-publish.php?id=123&a=publish|hide&t=<signed token>
 *
 * The token is signed with the secret in roc-review-secret.txt (reviews-lib.php), so only the email's links work.
 * The review is the roc_messages row (kind "review"); "published" is kept in its meta_json.
 */

ob_start();
require_once __DIR__ . '/config.php';
ob_end_clean();
require_once __DIR__ . '/reviews-lib.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$root = dirname(__DIR__, 2);
$id = (int)($_GET['id'] ?? 0);
$a = (string)($_GET['a'] ?? '');
$t = (string)($_GET['t'] ?? '');
$page = function (string $msg, bool $ok) {
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Review</title></head>'
        . '<body style="font-family:Arial,sans-serif;background:#eef7f4;padding:40px 16px"><main style="max-width:480px;margin:auto;background:#fff;padding:28px;border-radius:16px">'
        . '<h2 style="color:' . ($ok ? '#047857' : '#be123c') . ';margin-top:0">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</h2>'
        . '<p><a href="https://runonconsole.com/reviews/" style="color:#047857;font-weight:bold">Open the reviews page</a></p></main></body></html>';
    exit;
};

if ($id <= 0 || !in_array($a, ['publish', 'hide'], true) || !hash_equals(rocReviewToken($root, $id, $a), $t)) $page('This link is not valid.', false);
$db = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$db) $page('The server is busy. Please try the link again in a minute.', false);

$q = $db->prepare("SELECT meta_json FROM roc_messages WHERE id = ? AND kind = 'review' LIMIT 1");
$q->execute([$id]);
$row = $q->fetch(PDO::FETCH_ASSOC);
if (!$row) $page('This review was not found (it may have been deleted in the CMS).', false);
$meta = json_decode((string)($row['meta_json'] ?? ''), true) ?: [];
$meta['published'] = $a === 'publish' ? 1 : 0;
$db->prepare('UPDATE roc_messages SET meta_json = ?, updated_at = ? WHERE id = ?')
   ->execute([json_encode($meta, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s'), $id]);
$page($a === 'publish' ? 'The review is published on /reviews/.' : 'The review is hidden from /reviews/.', true);
