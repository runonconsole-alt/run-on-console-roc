<?php
/**
 * Run On Console — header menu and footer fixes (Deploys 41-42).
 *
 *   php cli-menu-products.php                  dry run: shows what would change
 *   php cli-menu-products.php --apply          makes the changes, with a backup
 *   php cli-menu-products.php --rollback=FILE  puts the old menu back
 *
 * On the menu saved in the CMS (CMS > Menus & footer):
 *   1. PRODUCTS (/products/, every product) right after CATEGORY (the two categories).
 *   2. NEWS leaves the header (it made the header too wide) ...
 *   3. ... and goes to the footer's PARTNERS column (once; removed from other columns).
 *   4. CATEGORY is a plain link to /category/ (no dropdown, no View all button).
 * Each step does nothing when it is already done.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

echo "Run On Console - header menu and footer\n\n";
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
if (!is_array($nav) || empty($nav['header'])) exit("  =  The built-in menu is used; it already has these changes.\n");
$isUrl = function ($it, string $u): bool { return rtrim((string)($it['url'] ?? ''), '/') === $u; };
$new = $nav;

// 1. PRODUCTS after CATEGORY
$has = false;
foreach ($new['header'] as $it) if ($isUrl($it, '/products')) $has = true;
if (!$has) {
    $at = 1;
    foreach ($new['header'] as $i => $it) if ($isUrl($it, '/category')) $at = $i + 1;
    array_splice($new['header'], $at, 0, [['label' => 'PRODUCTS', 'url' => '/products/']]);
    echo "  +  Header: PRODUCTS after CATEGORY\n";
}
// 2. NEWS out of the header
$before = count($new['header']);
$new['header'] = array_values(array_filter($new['header'], function ($it) use ($isUrl) { return !$isUrl($it, '/news'); }));
if (count($new['header']) !== $before) echo "  +  Header: NEWS removed\n";
// 3. News in the footer's PARTNERS column
if (!empty($new['footer']['columns']) && is_array($new['footer']['columns'])) {
    $pi = null;
    foreach ($new['footer']['columns'] as $i => $c) if (stripos((string)($c['title'] ?? ''), 'partner') !== false) $pi = $i;
    if ($pi === null) $pi = count($new['footer']['columns']) > 1 ? 1 : 0;
    $inPartners = false;
    foreach ($new['footer']['columns'] as $i => &$c) {
        $links = (array)($c['links'] ?? []);
        foreach ($links as $l) if ($i === $pi && $isUrl($l, '/news')) $inPartners = true;
        if ($i !== $pi) {
            $kept = array_values(array_filter($links, function ($l) use ($isUrl) { return !$isUrl($l, '/news'); }));
            if (count($kept) !== count($links)) { $c['links'] = $kept; echo "  +  Footer: News removed from " . ($c['title'] ?? 'a column') . "\n"; }
        }
    }
    unset($c);
    if (!$inPartners) {
        array_unshift($new['footer']['columns'][$pi]['links'], ['label' => 'News', 'url' => '/news/', 'icon' => 'newspaper']);
        echo '  +  Footer: News in ' . ($new['footer']['columns'][$pi]['title'] ?? 'PARTNERS') . "\n";
    }
}
// 4. CATEGORY without a dropdown
foreach ($new['header'] as &$it) {
    if (!$isUrl($it, '/category') || (empty($it['children']) && empty($it['button']))) continue;
    unset($it['children'], $it['button']);
    echo "  +  Header: CATEGORY dropdown removed (plain link to /category/)\n";
}
unset($it);
if ($new == $nav) exit("  =  Menu and footer already up to date.\n");
echo '     Header now: ' . implode(' | ', array_map(function ($it) { return (string)($it['label'] ?? ''); }, $new['header'])) . "\n";
if (!$apply) { echo "Dry run: nothing changed. Run again with --apply.\n"; exit(0); }

$file = $HOME . '/roc-menu-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'nav' => $nav], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$k = rocLayerSave($pdo, $ROOT, 'nav', $new, null);
echo "Menu updated ({$k} built page(s) redrawn)\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
