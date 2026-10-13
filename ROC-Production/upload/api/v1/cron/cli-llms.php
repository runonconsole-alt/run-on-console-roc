<?php
/**
 * Run On Console — rebuild /llms.txt from the live site.
 *
 *   php cli-llms.php            shows the new llms.txt (nothing changed)
 *   php cli-llms.php --apply    writes it (the old one is kept as ~/roc-llms-backup-<date>.txt)
 *
 * After this, the scheduler rewrites it by itself whenever a blog post or a news story goes live.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
require_once $ROOT . '/api/v1/cms/llms-lib.php';

if (!in_array('--apply', array_slice($argv, 1), true)) {
    echo rocLlmsBuild($pdo), "\nDry run: nothing changed. Run again with --apply to write public_html/llms.txt.\n";
    exit(0);
}
if (is_file($ROOT . '/llms.txt')) {
    $bak = $HOME . '/roc-llms-backup-' . date('Ymd-His') . '.txt';
    copy($ROOT . '/llms.txt', $bak);
    echo "Old llms.txt kept: {$bak}\n";
}
$n = rocLlmsWrite($pdo, $ROOT);
echo $n ? "llms.txt written ({$n} bytes): https://runonconsole.com/llms.txt\n" : "Could not write llms.txt.\n";
