<?php
/**
 * Run On Console — the UPDATES menu shows only Blogs, Guides and News, A to Z (Deploy 81).
 *
 * Takes "All updates" out of the UPDATES dropdown in the saved menu (CMS > Menus & footer) and sorts
 * the rest by name. The /updates/ page itself stays (the UPDATES label still opens it).
 *
 *   php cli-menu-updates.php                 dry run
 *   php cli-menu-updates.php --apply         saves the menu (backup kept) and redraws the built pages
 *   php cli-menu-updates.php --rollback=FILE puts the old menu back
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

echo "Run On Console - UPDATES menu: Blogs, Guides, News\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/site-layer-lib.php';

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b) || empty($b['nav'])) exit("No menu found in {$rollback}\n");
    $n = rocLayerSave($pdo, $ROOT, 'nav', $b['nav'], null);
    exit("Put the old menu back ({$n} built page(s) redrawn).\n");
}

$raw = rocLayerSettingGet($pdo, 'site_nav');
$nav = $raw ? json_decode($raw, true) : null;
if (!is_array($nav) || empty($nav['header'])) exit("  !  No saved menu (CMS > Menus & footer). Nothing changed.\n");

$new = $nav; $changed = false;
foreach ($new['header'] as $i => $it) {
    if (rtrim((string)($it['url'] ?? ''), '/') !== '/updates' || empty($it['children'])) continue;
    $kids = array_values(array_filter((array)$it['children'], function ($c) { return rtrim((string)($c['url'] ?? ''), '/') !== '/updates'; }));
    usort($kids, function ($a, $b) { return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')); });
    if ($kids !== $it['children']) {
        $changed = true;
        echo '  ~  UPDATES: ' . implode(', ', array_map(function ($c) { return (string)($c['title'] ?? ''); }, $it['children']))
           . "\n     becomes: " . implode(', ', array_map(function ($c) { return (string)($c['title'] ?? ''); }, $kids)) . "\n";
    }
    $new['header'][$i]['children'] = $kids;
}
if (!$changed) exit("  =  The UPDATES menu is already Blogs, Guides, News. Nothing to do.\n");
if (!$apply) exit("\nDry run: nothing changed. Run again with --apply.\n");

$file = $HOME . '/roc-menu-updates-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'nav' => $nav], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$n = rocLayerSave($pdo, $ROOT, 'nav', $new, null);
echo "Menu saved ({$n} built page(s) redrawn).\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
