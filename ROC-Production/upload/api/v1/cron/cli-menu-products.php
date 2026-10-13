<?php
/**
 * Run On Console — a PRODUCTS item in the header menu (Deploy 41).
 *
 *   php cli-menu-products.php                  dry run: shows what would change
 *   php cli-menu-products.php --apply          makes the change, with a backup
 *   php cli-menu-products.php --rollback=FILE  puts the old menu back
 *
 * CATEGORY holds the two categories (PC Hardware, Gaming Hardware -> /category/...);
 * PRODUCTS (/products/) lists every product. The item goes right after CATEGORY in the menu
 * saved in the CMS (CMS > Menus & footer). Nothing changes when the menu already has /products/.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

echo "Run On Console - PRODUCTS in the menu\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/site-layer-lib.php';

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b) || !isset($b['nav'])) exit("No backup found in {$rollback}\n");
    $k = rocLayerSave($pdo, $ROOT, 'nav', (array)$b['nav'], null);
    exit("Menu put back ({$k} built page(s) redrawn).\n");
}

$raw = rocLayerSettingGet($pdo, 'site_nav');
$nav = $raw ? json_decode($raw, true) : null;
if (!is_array($nav) || empty($nav['header'])) exit("  =  The built-in menu is used; it already has PRODUCTS.\n");
foreach ($nav['header'] as $it) {
    if (rtrim((string)($it['url'] ?? ''), '/') === '/products') exit("  =  The menu already has " . ($it['label'] ?? 'PRODUCTS') . " (/products/).\n");
}
$at = 1;
foreach ($nav['header'] as $i => $it) if (rtrim((string)($it['url'] ?? ''), '/') === '/category') $at = $i + 1;
$new = $nav;
array_splice($new['header'], $at, 0, [['label' => 'PRODUCTS', 'url' => '/products/']]);
echo '  +  Menu: ' . implode(' | ', array_map(function ($it) { return (string)($it['label'] ?? ''); }, $new['header'])) . "\n";
if (!$apply) { echo "Dry run: nothing changed. Run again with --apply.\n"; exit(0); }

$file = $HOME . '/roc-menu-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'nav' => $nav], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$k = rocLayerSave($pdo, $ROOT, 'nav', $new, null);
echo "Menu updated ({$k} built page(s) redrawn)\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
