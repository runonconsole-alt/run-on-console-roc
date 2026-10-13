<?php
/**
 * Run On Console — CMS Media library: which pictures the website really uses.
 *
 *   GET media-usage.php            {used: {"/uploads/x.webp": 1, ...}, scanned: ISO date}
 *   GET media-usage.php?fresh=1    scans again now
 *
 * Same rules as the clean-up (api/v1/cms/media-refs-lib.php), so "not used" in the CMS is exactly
 * what api/v1/cron/cli-media-cleanup.php would move. The scan reads the whole site, so its result is
 * kept for 10 minutes in ~/roc-cms-cache/media-usage.json (cli-media-cleanup.php writes it too).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/media-refs-lib.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');

$ROOT = dirname(__DIR__, 3);
$dir = dirname($ROOT) . '/roc-cms-cache';
$file = $dir . '/media-usage.json';
$c = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
if (!is_array($c) || empty($c['used']) || isset($_GET['fresh']) || time() - (int)($c['t'] ?? 0) > 600) {
    @set_time_limit(180);                                    // reading the whole site can take a while
    $c = ['t' => time(), 'used' => rocMediaRefs($pdo, $ROOT)];
    rocMediaRefsCache($ROOT, $c['used']);
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['success' => true, 'used' => (object)$c['used'], 'scanned' => gmdate('c', (int)$c['t'])], JSON_UNESCAPED_SLASHES);
