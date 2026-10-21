<?php
/**
 * Run On Console — AI discovery files and the home page date (Deploy 97).
 *
 *   php cli-ai-files.php            shows the files (nothing changed)
 *   php cli-ai-files.php --apply    writes /.well-known/ai.txt, /ai/summary.json, /ai/faq.json,
 *                                   /ai/service.json and dates the home page
 *
 * After this, the scheduler rewrites them by itself whenever a blog post or a news story goes live.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
require_once $ROOT . '/api/v1/cms/ai-files-lib.php';

if (!in_array('--apply', array_slice($argv, 1), true)) {
    foreach (rocAiFilesBuild($pdo) as $rel => $body) echo "===== /{$rel}\n{$body}\n";
    $mod = rocAiHomeModified($pdo);
    echo 'Home page dateModified: ' . ($mod ? gmdate('c', $mod) : '(no date found)') . "\n";
    echo "\nDry run: nothing changed. Run again with --apply to write the files.\n";
    exit(0);
}
$n = rocAiFilesWrite($pdo, $ROOT);
echo "{$n} of 4 files written: https://runonconsole.com/.well-known/ai.txt and https://runonconsole.com/ai/summary.json\n";
echo rocAiDateHome($pdo, $ROOT) ? "Home page dated.\n" : "Home page date unchanged.\n";
