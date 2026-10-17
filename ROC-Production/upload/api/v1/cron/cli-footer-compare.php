<?php
/**
 * Run On Console — "Compare" in the footer's EXPLORE HUB column (Deploy 89), after "Can I Run".
 *
 *   php cli-footer-compare.php                 dry run
 *   php cli-footer-compare.php --apply         saves the menu (backup kept) and redraws the built pages
 *   php cli-footer-compare.php --rollback=FILE puts the old menu back
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);
echo "Run On Console - footer: Compare\n\n";
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
    exit('Put the old menu back (' . rocLayerSave($pdo, $ROOT, 'nav', $b['nav'], null) . " built page(s) redrawn).\n");
}
$nav = json_decode((string)rocLayerSettingGet($pdo, 'site_nav'), true);
if (!is_array($nav) || empty($nav['footer']['columns'])) exit("  !  No saved menu (CMS > Menus & footer). Nothing changed.\n");
$u = function ($l) { return rtrim((string)($l['url'] ?? ''), '/'); };
$col = null;
foreach ($nav['footer']['columns'] as $i => $c) {
    foreach ((array)($c['links'] ?? []) as $l) if ($u($l) === '/compare') exit("  =  The footer already has Compare. Nothing to do.\n");
    if ($col === null && (stripos((string)($c['title'] ?? ''), 'explore') !== false)) $col = $i;
}
if ($col === null) $col = 0;
$links = array_values((array)$nav['footer']['columns'][$col]['links']);
$at = count($links);
foreach ($links as $k => $l) if (in_array($u($l), ['/can-i-run', '/compatibility'], true)) $at = $k + 1;
array_splice($links, $at, 0, [['label' => 'Compare', 'url' => '/compare/', 'icon' => 'scale']]);
echo '  +  ' . ($nav['footer']['columns'][$col]['title'] ?? 'Column') . ': Compare (/compare/) at place ' . ($at + 1) . "\n";
if (!$apply) exit("\nDry run: nothing changed. Run again with --apply.\n");
$new = $nav; $new['footer']['columns'][$col]['links'] = $links;
$file = $HOME . '/roc-footer-compare-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'nav' => $nav], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo 'Footer saved (' . rocLayerSave($pdo, $ROOT, 'nav', $new, null) . " built page(s) redrawn).\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
