<?php
/**
 * Run On Console — "X vs Y" comparison pages at /compare/ (Deploy 87).
 *
 *   php cli-compare-pages.php                 dry run: what would change, and how many verdicts are written
 *   php cli-compare-pages.php --apply         adds the /compare/ rules to .htaccess (backup kept)
 *   php cli-compare-pages.php --write=N       writes N comparison verdicts now (the scheduler writes 1 a run, 20 a day)
 *   php cli-compare-pages.php --rollback=FILE puts the old .htaccess back
 *
 * The pages come from product-render.php (?compare=...); a pair shows only once its verdict is written.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null; $write = 0;
foreach ($args as $a) {
    if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);
    if (strpos($a, '--write=') === 0) $write = max(1, min(40, (int)substr($a, 8)));
}

const CP_BEGIN = '  # ROC compare BEGIN (cli-compare-pages.php)';
const CP_END   = '  # ROC compare END';
const CP_BLOCK = CP_BEGIN . "\n"
    . "  # \"X vs Y\" comparisons (product-render.php)\n"
    . "  RewriteRule ^compare$ /compare/ [R=301,L]\n"
    . "  RewriteRule ^compare/$ product-render.php?compare= [L,QSA]\n"
    . "  RewriteRule ^compare/([a-z0-9-]+)$ /compare/$1/ [R=301,L]\n"
    . "  RewriteRule ^compare/([a-z0-9-]+)/$ product-render.php?compare=$1 [L,QSA]\n"
    . CP_END . "\n";

echo "Run On Console - comparison pages (/compare/)\npublic_html: {$ROOT}\n\n";
$htFile = $ROOT . '/.htaccess';
if ($rollback !== null) {
    if (!is_file($rollback)) exit("No backup found: {$rollback}\n");
    copy($rollback, $htFile);
    exit("Put the old .htaccess back.\n");
}

ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/compare-lib.php';

$pairs = rocComparePairs($pdo); $texts = rocCompareTexts($pdo);
$written = count(array_filter(array_keys($pairs), function ($s) use ($texts) { return !empty($texts[$s]['summary']); }));
echo '  Pairs: ' . count($pairs) . " (top 4 of each category), verdicts written: {$written}\n";

if ($write) {
    $todo = array_diff_key($pairs, $texts);
    $n = 0;
    foreach (array_slice($todo, 0, $write, true) as $slug => $pair) {
        if ($n++) sleep(15);
        $r = rocCompareWrite($pdo, (string)$slug, $pair);
        echo '  ' . ($r['ok'] ? 'ok ' : '!  ') . $slug . ($r['ok'] ? '' : ' - ' . $r['error']) . "\n";
        if (!$r['ok']) break;
    }
}

$ht = (string)@file_get_contents($htFile);
$done = strpos($ht, CP_BEGIN) !== false;
$anchor = null;
foreach (['  # ROC game pages BEGIN', '  # ROC updates BEGIN', '  # ROC news BEGIN', '  # Real file or folder'] as $a) if (strpos($ht, $a) !== false) { $anchor = $a; break; }
echo '  .htaccess /compare/ rules: ' . ($done ? "already there\n" : ($anchor ? "add\n" : "STOP - no place to add them\n"));
if ($done || !$apply) exit($done ? '' : "\nDry run: nothing changed. Run again with --apply.\n");
if ($anchor === null) exit("Nothing changed.\n");
$file = $HOME . '/roc-compare-' . date('Ymd-His') . '.htaccess';
file_put_contents($file, $ht);
$new = substr_replace($ht, CP_BLOCK, (int)strpos($ht, $anchor), 0);
file_put_contents($htFile . '.roc-tmp', $new);
rename($htFile . '.roc-tmp', $htFile);
echo "Done: /compare/ is live (pages appear as their verdicts are written).\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
