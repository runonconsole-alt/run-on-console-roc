<?php
/**
 * Run On Console — the owner's product photos (Products Images zip, 2026-10-12).
 *
 *   php cli-product-photos.php                  dry run: shows what would change
 *   php cli-product-photos.php --apply          sets the photos, with a backup
 *   php cli-product-photos.php --apply --covers also draws new covers for the blog posts
 *                                               (title + product photo)
 *   php cli-product-photos.php --rollback=FILE  puts the old pictures back
 *
 * Data: roc-product-photos.json; the photos are /images/products/photos/{slug}.webp
 * (1000 x 750 on white, from the deploy zip).
 * A product's picture is changed only while it is a shared placeholder photo, a name card,
 * an Amazon widget link or empty, so a picture set by hand in the CMS is kept.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$covers = in_array('--covers', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$hasUpd = false;
$st = $pdo->query('SELECT * FROM products LIMIT 0');
for ($i = 0; $i < $st->columnCount(); $i++) if (strtolower((string)$st->getColumnMeta($i)['name']) === 'updated_at') $hasUpd = true;

echo "Run On Console - product photos\n\n";

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b) || empty($b['products'])) exit("No backup found in {$rollback}\n");
    $u = $pdo->prepare('UPDATE products SET image = ? WHERE slug = ?');
    foreach ($b['products'] as $slug => $img) $u->execute([$img, $slug]);
    exit('Restored ' . count($b['products']) . " picture(s) from {$rollback}\n");
}

$data = json_decode((string)@file_get_contents(__DIR__ . '/roc-product-photos.json'), true);
if (!is_array($data) || empty($data['products'])) exit("roc-product-photos.json is missing.\n");
$placeholders = ['/images/cyber_keyboard.jpg', '/images/tactical_headset.jpg', '/images/apex_mouse.jpg',
                 '/images/streaming_vr_gear.jpg', '/images/gaming_monitor.jpg', '/images/battlestation_pc.jpg'];

$get = $pdo->prepare('SELECT image FROM products WHERE slug = ?');
$changes = []; $backup = [];
foreach ($data['products'] as $slug => $p) {
    if (!is_file($ROOT . $p['image'])) { echo "  !  {$slug}: photo file missing ({$p['image']})\n"; continue; }
    $get->execute([$slug]);
    $img = $get->fetchColumn();
    if ($img === false) { echo "  ?  {$slug}: not in the database\n"; continue; }
    $img = trim((string)$img);
    if ($img === $p['image']) continue;
    $replace = $img === '' || in_array($img, $placeholders, true) || strpos($img, '/images/products/') === 0 || strpos($img, 'amazon-adsystem.com') !== false;
    if (!$replace) { echo "  =  {$slug}: picture set by hand in the CMS (kept)\n"; continue; }
    $changes[$slug] = $p['image']; $backup[$slug] = $img;
    echo '  +  ' . str_pad($slug, 46) . " {$p['image']}\n";
}
echo "\n" . count($changes) . " product(s) to update.\n";
if (!$apply) { echo "Dry run: nothing changed. Run again with --apply" . ($covers ? ' --covers' : '') . ".\n"; exit(0); }

if ($changes) {
    $file = $HOME . '/roc-photos-backup-' . date('Ymd-His') . '.json';
    file_put_contents($file, json_encode(['made' => date('c'), 'products' => $backup], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $u = $pdo->prepare('UPDATE products SET image = ?' . ($hasUpd ? ', updated_at = ?' : '') . ' WHERE slug = ?');
    foreach ($changes as $slug => $img) $u->execute($hasUpd ? [$img, date('Y-m-d H:i:s'), $slug] : [$img, $slug]);
    echo 'Updated ' . count($changes) . " product(s).\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
}

if ($covers) {
    require_once $ROOT . '/api/v1/cms/blog-engine-lib.php';
    $n = 0;
    foreach (rocBlogPlan($pdo)['topics'] as $t) {
        if (empty($t['post_id'])) continue;
        $img = rocBlogRecover($pdo, $ROOT, $t['post_id']);
        if ($img !== null) { $n++; echo "  cover: {$t['slug']}\n"; }
    }
    echo "New covers: {$n}\n";
}
