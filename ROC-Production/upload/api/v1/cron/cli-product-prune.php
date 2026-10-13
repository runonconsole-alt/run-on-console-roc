<?php
/**
 * Run On Console — delete the products that are off the site and have no photo.
 *
 *   php cli-product-prune.php                  dry run: lists them
 *   php cli-product-prune.php --apply          deletes them from the CMS (backup first)
 *   php cli-product-prune.php --rollback=FILE  puts them back
 *
 * What --apply does:
 *   1. Saves every row of those products in ~/roc-prune-backup-<date>.json.
 *   2. Deletes them from the products table (so no category shows "hidden" any more).
 *   3. Writes api/v1/cms/product-redirects.json: their old addresses keep sending visitors and
 *      Google to their category page (301), so no link breaks.
 *   4. Moves their old static folders (public_html/products/<slug>/) to ~/roc-media-trash/.
 * A product counts when its status is not "published" and its picture is not a real photo
 * (/images/products/photos/ or /uploads/).
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);
$MAP = $ROOT . '/api/v1/cms/product-redirects.json';

echo "Run On Console - delete hidden products without a photo\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b) || empty($b['rows'])) exit("No backup found in {$rollback}\n");
    $n = 0;
    foreach ($b['rows'] as $row) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = ?'); $st->execute([$row['slug']]);
        if ((int)$st->fetchColumn() > 0) continue;
        $pdo->prepare('INSERT INTO products (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
        $n++;
    }
    foreach ((array)($b['folders'] ?? []) as $f) if (is_dir($b['trash'] . $f) && !is_dir($ROOT . $f)) @rename($b['trash'] . $f, $ROOT . $f);
    $map = json_decode((string)@file_get_contents($MAP), true) ?: [];
    foreach ($b['rows'] as $row) unset($map[$row['slug']]);
    file_put_contents($MAP, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    exit("Put back {$n} product(s).\n");
}

$cats = [];
try { foreach ($pdo->query('SELECT slug, name FROM product_categories')->fetchAll(PDO::FETCH_ASSOC) as $c) { $cats[strtolower($c['name'])] = $c['slug']; $cats[$c['slug']] = $c['slug']; } } catch (\Throwable $e) {}
$rows = [];
foreach ($pdo->query("SELECT * FROM products WHERE status <> 'published'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $img = trim((string)$r['image']);
    if (preg_match('#^/(images/products/photos|uploads)/#', $img)) { echo "  =  {$r['slug']}: hidden but has a photo (kept)\n"; continue; }
    $rows[] = $r;
    $cs = (string)($r['category_slug'] ?? '') ?: ($cats[strtolower((string)$r['category'])] ?? '');
    echo '  -  ' . str_pad($r['slug'], 42) . ($cs !== '' ? "-> /category/{$cs}/" : '-> /products/') . "\n";
}
echo "\n" . count($rows) . " product(s) to delete.\n";
if (!$apply) { echo "Dry run: nothing changed. Run again with --apply.\n"; exit(0); }
if (!$rows) exit("Nothing to do.\n");

$trash = $HOME . '/roc-media-trash/products-' . date('Ymd-His');
$folders = [];
$map = json_decode((string)@file_get_contents($MAP), true) ?: [];
foreach ($rows as $r) {
    $cs = (string)($r['category_slug'] ?? '') ?: ($cats[strtolower((string)$r['category'])] ?? '');
    $map[$r['slug']] = $cs !== '' ? '/category/' . $cs . '/' : '/products/';
    $f = '/products/' . $r['slug'];
    if (is_dir($ROOT . $f)) { @mkdir(dirname($trash . $f), 0755, true); if (@rename($ROOT . $f, $trash . $f)) $folders[] = $f; }
}
$file = $HOME . '/roc-prune-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'rows' => $rows, 'folders' => $folders, 'trash' => $trash], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
file_put_contents($MAP, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$d = $pdo->prepare('DELETE FROM products WHERE slug = ?');
foreach ($rows as $r) $d->execute([$r['slug']]);
echo 'Deleted ' . count($rows) . ' product(s); ' . count($folders) . " old folder(s) moved to {$trash}\nTheir addresses now open their category (301).\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
