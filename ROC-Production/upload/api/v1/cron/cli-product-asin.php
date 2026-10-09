<?php
/**
 * Run On Console — real Amazon links and SiteStripe pictures for the products.
 *
 *   php cli-product-asin.php                  dry run: shows what would change
 *   php cli-product-asin.php --apply          makes the changes, with a backup
 *   php cli-product-asin.php --rollback=FILE  puts the old values back from a backup
 *
 * Data: roc-product-asins.json (made by scripts/asin/build_asin.py from scripts/asin/asins.txt).
 *
 * For each product with an ASIN:
 *   - the Amazon link becomes https://www.amazon.com/dp/ASIN, but only while it is still an
 *     Amazon search link or empty (a link set by hand in the CMS is kept). The affiliate tag
 *     from CMS > Social & Amazon tag is added when the page is shown, as before;
 *   - the picture becomes the SiteStripe image link (Amazon serves it; nothing is copied),
 *     but only while it is still a shared placeholder photo, a name card or empty
 *     (a picture set in the CMS is kept). The name card stays as the fallback after "#fb=".
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Run On Console - Amazon links and pictures for the products\n\n";

$cols = [];
$st = $pdo->query('SELECT * FROM products LIMIT 0');
for ($i = 0; $i < $st->columnCount(); $i++) $cols[] = strtolower((string)$st->getColumnMeta($i)['name']);
$hasJson = in_array('affiliate_links_json', $cols, true);
$hasUpd = in_array('updated_at', $cols, true);

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b) || empty($b['products'])) exit("No backup found in {$rollback}\n");
    $n = 0;
    foreach ($b['products'] as $slug => $old) {
        $set = ['affiliate_amazon = ?', 'image = ?']; $val = [$old['affiliate_amazon'], $old['image']];
        if ($hasJson) { $set[] = 'affiliate_links_json = ?'; $val[] = $old['affiliate_links_json']; }
        $val[] = $slug;
        $pdo->prepare('UPDATE products SET ' . implode(', ', $set) . ' WHERE slug = ?')->execute($val);
        $n++;
    }
    exit("Restored {$n} product(s) from {$rollback}\n");
}

$data = json_decode((string)@file_get_contents(__DIR__ . '/roc-product-asins.json'), true);
if (!is_array($data) || empty($data['products'])) exit("roc-product-asins.json is missing.\n");

$tag = '';
try {
    $t = $pdo->prepare("SELECT setting_value FROM cms_settings WHERE setting_key = 'amazon_tag' LIMIT 1");
    $t->execute(); $tag = trim((string)$t->fetchColumn());
} catch (\Throwable $e) {}
if ($tag !== '' && !preg_match('/^[A-Za-z0-9_-]{2,40}$/', $tag)) $tag = '';

$placeholders = ['/images/cyber_keyboard.jpg', '/images/tactical_headset.jpg', '/images/apex_mouse.jpg',
                 '/images/streaming_vr_gear.jpg', '/images/gaming_monitor.jpg', '/images/battlestation_pc.jpg'];

$get = $pdo->prepare('SELECT slug, title, image, affiliate_amazon' . ($hasJson ? ', affiliate_links_json' : '') . ' FROM products WHERE slug = ?');
$changes = []; $backup = [];
foreach ($data['products'] as $slug => $a) {
    $get->execute([$slug]);
    $p = $get->fetch(PDO::FETCH_ASSOC);
    if (!$p) { echo "  ?  {$slug}: not in the database (skipped)\n"; continue; }
    $link = trim((string)$p['affiliate_amazon']);
    $img = trim((string)$p['image']);
    $newLink = ($link === '' || preg_match('#amazon\.[a-z.]+/s\?#i', $link)) ? $a['link'] : null;
    $newImg = null;
    if ($img === '' || in_array($img, $placeholders, true) || strpos($img, '/images/products/') === 0) {
        $newImg = $a['image'];
        if ($tag !== '') $newImg = str_replace('&language=en_US', '&tag=' . rawurlencode($tag) . '&language=en_US', $newImg);
    }
    if ($newLink === null && $newImg === null) { echo "  =  {$slug}: already set by hand (kept)\n"; continue; }
    $changes[$slug] = ['link' => $newLink, 'image' => $newImg, 'json' => $hasJson ? $p['affiliate_links_json'] : null];
    $backup[$slug] = ['affiliate_amazon' => $p['affiliate_amazon'], 'image' => $p['image'], 'affiliate_links_json' => $hasJson ? $p['affiliate_links_json'] : null];
    echo '  +  ' . str_pad($slug, 46) . ' ' . $a['asin'] . ($newLink ? '  link' : '') . ($newImg ? '  picture' : '') . "\n";
}

echo "\n" . count($changes) . ' product(s) to update' . ($tag !== '' ? " (tag {$tag} on pictures)" : '') . ".\n";
if (!$apply) { echo "Dry run: nothing changed. Run again with --apply.\n"; exit(0); }
if (!$changes) exit("Nothing to do.\n");

$file = $HOME . '/roc-asin-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'products' => $backup], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$now = date('Y-m-d H:i:s');
foreach ($changes as $slug => $c) {
    $set = []; $val = [];
    if ($c['link'] !== null) {
        $set[] = 'affiliate_amazon = ?'; $val[] = $c['link'];
        if ($hasJson) {
            $j = json_decode((string)$c['json'], true);
            $j = is_array($j) ? $j : [];
            $j['amazon'] = $c['link'];
            $set[] = 'affiliate_links_json = ?'; $val[] = json_encode($j, JSON_UNESCAPED_SLASHES);
        }
    }
    if ($c['image'] !== null) { $set[] = 'image = ?'; $val[] = $c['image']; }
    if ($hasUpd) { $set[] = 'updated_at = ?'; $val[] = $now; }
    $val[] = $slug;
    $pdo->prepare('UPDATE products SET ' . implode(', ', $set) . ' WHERE slug = ?')->execute($val);
}
echo "Updated " . count($changes) . " product(s).\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
