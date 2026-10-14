<?php
/**
 * Run On Console — footer with two columns (Deploy 56).
 *
 *   php cli-footer-columns.php                  dry run: shows what would change
 *   php cli-footer-columns.php --apply          the footer of the menu saved in the CMS gets two columns:
 *                                               EXPLORE HUB and POLICIES (with Contact Us, Write For Us and News);
 *                                               email, phone and the map are in the Run On Console part (built pages)
 *   php cli-footer-columns.php --rollback=FILE  puts the old menu back
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

echo "Run On Console - footer with two columns\n\n";
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

$raw = rocLayerSettingGet($pdo, 'site_nav');
$nav = $raw ? json_decode($raw, true) : null;
if (!is_array($nav) || empty($nav['footer']['columns'])) { echo "  =  The menu is not saved in the CMS: the built-in footer of this deploy is used.\nNothing to do.\n"; exit(0); }

$norm = function ($u) { return rtrim((string)$u, '/'); };
$explore = null; $policies = null; $moved = [];
foreach ($nav['footer']['columns'] as $c) {
    $t = strtoupper(trim((string)($c['title'] ?? '')));
    if ($t === 'POLICIES') { $policies = $c; continue; }
    if ($t === 'EXPLORE HUB' && $explore === null) { $explore = $c; continue; }
    // Any other column (CONTACT US, PARTNERS ...): its page links move to POLICIES; email, phone and map
    // links go (they are in the Run On Console part of the footer now).
    foreach ((array)($c['links'] ?? []) as $l) {
        $u = (string)($l['url'] ?? '');
        if (preg_match('#^(mailto:|tel:|https://www\.google\.com/maps)#i', $u)) continue;
        if ($norm($u) === '/contact') $l = ['label' => 'Contact Us', 'url' => '/contact/', 'icon' => 'mail'];
        if ($norm($u) === '/write-for-us') $l['icon'] = 'pen';
        $moved[] = $l;
    }
}
if ($explore === null) exit("STOP: the saved footer has no EXPLORE HUB column. Change it in CMS > Menus & footer.\n");
if ($policies === null) $policies = ['title' => 'POLICIES', 'icon' => 'file-text', 'links' => []];
$have = array_map(function ($l) use ($norm) { return $norm($l['url'] ?? ''); }, (array)$policies['links']);
$order = ['/contact' => 0, '/write-for-us' => 1, '/news' => 2];
usort($moved, function ($a, $b) use ($norm, $order) { return ($order[$norm($a['url'] ?? '')] ?? 9) <=> ($order[$norm($b['url'] ?? '')] ?? 9); });
foreach ($moved as $l) if (!in_array($norm($l['url'] ?? ''), $have, true)) { $policies['links'][] = $l; $have[] = $norm($l['url'] ?? ''); }
$new = $nav;
$new['footer']['columns'] = [$explore, $policies];

if (count($nav['footer']['columns']) === 2 && json_encode($nav['footer']['columns']) === json_encode($new['footer']['columns'])) { echo "  =  The footer already has the two columns.\nNothing to do.\n"; exit(0); }
echo "  +  Footer columns: EXPLORE HUB and POLICIES\n";
foreach ($policies['links'] as $l) echo '       POLICIES: ' . $l['label'] . '  ->  ' . $l['url'] . "\n";
echo "  +  Email, phone and the map: in the Run On Console part of the footer (comes with the built pages)\n";
if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }

$file = $HOME . '/roc-footer-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'nav' => $nav], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$n = rocLayerSave($pdo, $ROOT, 'nav', $new, null);
echo "\nFooter updated ({$n} built page(s) redrawn).\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
