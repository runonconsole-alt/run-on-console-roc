<?php
/**
 * Run On Console — contact details in the footer (Deploy 55).
 *
 *   php cli-contact-info.php                  dry run: shows what would change
 *   php cli-contact-info.php --apply          the footer column with "Contact Us" becomes "CONTACT US" with the
 *                                             email, phone and address (clickable), with a backup
 *   php cli-contact-info.php --rollback=FILE  puts the old menu back
 *
 * Only needed when the menu was saved in CMS > Menus & footer; otherwise the built-in footer of this
 * deploy already has the details. The same column: nav-default.json (src/data/siteNav.js).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

echo "Run On Console - contact details in the footer\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/site-layer-lib.php';

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b) || !isset($b['nav'])) exit("No backup in {$rollback}\n");
    $n = rocLayerSave($pdo, $ROOT, 'nav', (array)$b['nav'], null);
    exit("Old menu put back ({$n} built page(s) redrawn).\n");
}

$def = json_decode((string)@file_get_contents($ROOT . '/api/v1/cms/nav-default.json'), true);
$col = null;
foreach ((array)($def['footer']['columns'] ?? []) as $c) if (($c['title'] ?? '') === 'CONTACT US') $col = $c;
if (!$col) exit("STOP: api/v1/cms/nav-default.json is the old version. Unzip the deploy first.\n");

$raw = rocLayerSettingGet($pdo, 'site_nav');
$nav = $raw ? json_decode($raw, true) : null;
if (!is_array($nav) || empty($nav['footer']['columns'])) { echo "  =  The menu is not saved in the CMS: the built-in footer (this deploy) has the details.\nNothing to do.\n"; exit(0); }
$at = null;
foreach ($nav['footer']['columns'] as $i => $c) {
    if (($c['title'] ?? '') === 'CONTACT US') { echo "  =  The footer already has the CONTACT US column.\nNothing to do.\n"; exit(0); }
    foreach ((array)($c['links'] ?? []) as $l) if (rtrim((string)($l['url'] ?? ''), '/') === '/contact') $at = $i;
}
$new = $nav;
if ($at === null) {
    if (count($new['footer']['columns']) >= 4) exit("STOP: the footer has 4 columns and none with Contact Us. Add the details in CMS > Menus & footer.\n");
    $new['footer']['columns'][] = $col;
    echo "  +  New footer column CONTACT US (email, phone, address, contact form)\n";
} else {
    // Keep the column's other links (News, Write for us ...) under the contact details.
    $have = array_map(function ($l) { return rtrim((string)($l['url'] ?? ''), '/'); }, $col['links']);
    foreach ((array)$nav['footer']['columns'][$at]['links'] as $l)
        if (!in_array(rtrim((string)($l['url'] ?? ''), '/'), $have, true)) $col['links'][] = $l;
    $new['footer']['columns'][$at] = $col;
    echo '  +  Footer column "' . ($nav['footer']['columns'][$at]['title'] ?? '') . "\" becomes CONTACT US: email, phone and address (clickable) on top\n";
}
foreach ($col['links'] as $l) echo '       ' . $l['label'] . '  ->  ' . $l['url'] . "\n";
if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }

$file = $HOME . '/roc-contact-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'nav' => $nav], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$n = rocLayerSave($pdo, $ROOT, 'nav', $new, null);
echo "\nFooter updated ({$n} built page(s) redrawn).\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
