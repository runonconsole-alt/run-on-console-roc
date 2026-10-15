<?php
/**
 * Run On Console (ROC) — CMS > Site users > Weekly email (administrators)
 *
 * GET                                   {on, subscribers, wishlists, last}
 * POST {action:'settings', on}          the weekly email on or off for everyone
 * POST {action:'test', email}           this week's email (with an empty wishlist) to that address now
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/blog-engine-lib.php';
require_once dirname(__DIR__) . '/member-lib.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
rocMemberTables($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    if (($in['action'] ?? '') === 'settings') {
        rocBlogSet($pdo, 'digest_on', !empty($in['on']) ? 'on' : 'off', (int)$session['user_id']);
        logCmsAudit('cms_digest_settings', 'settings', 'digest', ['on' => !empty($in['on'])]);
        echo json_encode(['success' => true, 'message' => !empty($in['on']) ? 'The weekly email goes out every Friday from 18:00 Pakistan time.' : 'The weekly email is off.']); exit;
    }
    if (($in['action'] ?? '') === 'test') {
        $to = trim((string)($in['email'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) rocCmsDeny(422, 'Enter a valid email address.');
        [$subj, $html] = rocDigestEmail(rocDigestContent($pdo), [], 'there', 'https://runonconsole.com/profile/?tab=wishlist');
        if (!rocDigestQueue($pdo, $to, '[Test] ' . $subj, $html)) rocCmsDeny(500, 'The email could not be queued (email_queue).');
        echo json_encode(['success' => true, 'message' => 'Test email queued for ' . $to . ': it arrives within a few minutes.']); exit;
    }
    rocCmsDeny(400, 'Unknown action.');
}

$c = function (string $sql) use ($pdo) { try { return (int)$pdo->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; } };
echo json_encode(['success' => true, 'on' => (string)rocBlogGet($pdo, 'digest_on', 'on') !== 'off',
    'subscribers' => $c('SELECT COUNT(*) FROM roc_member_mail WHERE weekly = 1'),
    'wishlists' => $c('SELECT COUNT(DISTINCT user_id) FROM roc_wishlist'), 'saved_items' => $c('SELECT COUNT(*) FROM roc_wishlist'),
    'last' => rocBlogGet($pdo, 'digest_last', null), 'email' => (string)($session['email'] ?? '')], JSON_UNESCAPED_SLASHES);
