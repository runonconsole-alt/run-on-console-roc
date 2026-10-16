<?php
/**
 * Run On Console (ROC) — CMS > Updates writer > Guides (administrators, Deploy 73). See guide-writer-lib.php.
 *
 * GET                                       settings, why it waits, the next topic, the topics and what was written
 * POST {action:'settings', on, per_day, until}
 * POST {action:'write_now'}                 writes and publishes the next guide now
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/guide-writer-lib.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$root = dirname(__DIR__, 3);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $act = (string)($in['action'] ?? '');
    if ($act === 'settings') {
        $u = trim((string)($in['until'] ?? ''));
        if ($u !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $u)) rocCmsDeny(422, 'Choose a date.');
        $s = ['on' => !empty($in['on']), 'per_day' => max(0, min(6, (int)($in['per_day'] ?? 2))), 'until' => $u];
        rocBlogSet($pdo, 'guide_writer', $s, (int)$session['user_id']);
        logCmsAudit('cms_guide_writer_settings', 'settings', 'guide_writer', $s);
        echo json_encode(['success' => true, 'message' => $s['on'] ? "Saved: {$s['per_day']} guide(s) a day" . ($u !== '' ? " until {$u} (not on that day)" : '') . '.' : 'Saved: the guide writer is off.']); exit;
    }
    if ($act === 'write_now') {
        @set_time_limit(400);
        $r = rocGuideWrite($pdo, $root);
        if (!$r['ok']) rocCmsDeny(422, $r['error']);
        logCmsAudit('cms_guide_write_now', 'blog', $r['id'], ['title' => $r['title']]);
        echo json_encode(['success' => true, 'message' => 'Published: ' . $r['title'] . ' (' . $r['words'] . ' words).', 'url' => $r['url']]); exit;
    }
    rocCmsDeny(400, 'Unknown action.');
}

$next = rocGuideNext($pdo);
$done = array_column(rocGuideLog($pdo), 'key');
echo json_encode(['success' => true, 'settings' => rocGuideSettings($pdo), 'wait' => rocGuideWait($pdo), 'ai' => (bool)rocBlogWriters($pdo),
    'state' => rocBlogGet($pdo, 'guide_state', []) ?: new stdClass(), 'next' => $next ? ['title' => $next[1], 'type' => $next[2]] : null,
    'topics' => array_map(function ($t) use ($done) { return ['title' => $t[1], 'type' => $t[2], 'done' => in_array($t[0], $done, true)]; }, ROC_GUIDE_TOPICS),
    'log' => array_slice(rocGuideLog($pdo), 0, 30)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
