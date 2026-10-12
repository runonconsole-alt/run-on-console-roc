<?php
/**
 * Run On Console — new product categories (CPU, RAM, PC Cases, Gaming Chairs, Gaming Handhelds,
 * Gaming Laptops) and their products, from the owner's photos.
 *
 *   php cli-product-expand.php                  dry run: shows what would be added
 *   php cli-product-expand.php --apply          adds the missing categories and products
 *   php cli-product-expand.php --rollback=FILE  removes what an --apply added
 *
 * Data: roc-product-expand.json (scripts/products/build_expand.py). Only missing rows are
 * added: a category or product that already exists (same slug) is never changed.
 * Pictures: /images/products/photos/{slug}.webp (in the deploy zip).
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

function cols(PDO $pdo, string $t): array {
    $st = $pdo->query("SELECT * FROM {$t} LIMIT 0"); $c = [];
    for ($i = 0; $i < $st->columnCount(); $i++) $c[] = strtolower((string)$st->getColumnMeta($i)['name']);
    return $c;
}
function insertRow(PDO $pdo, string $t, array $row, array $cols): void {
    $row = array_filter($row, function ($k) use ($cols) { return in_array($k, $cols, true); }, ARRAY_FILTER_USE_KEY);
    $pdo->prepare("INSERT INTO {$t} (" . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')
        ->execute(array_values($row));
}

echo "Run On Console - new product categories and products\n\n";

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b)) exit("No backup found in {$rollback}\n");
    $dp = $pdo->prepare('DELETE FROM products WHERE slug = ?');
    foreach ((array)($b['products'] ?? []) as $s) $dp->execute([$s]);
    $dc = $pdo->prepare('DELETE FROM product_categories WHERE slug = ?');
    foreach ((array)($b['categories'] ?? []) as $s) $dc->execute([$s]);
    exit('Removed ' . count((array)($b['products'] ?? [])) . ' product(s) and ' . count((array)($b['categories'] ?? [])) . " categor(ies).\n");
}

$data = json_decode((string)@file_get_contents(__DIR__ . '/roc-product-expand.json'), true);
if (!is_array($data)) exit("roc-product-expand.json is missing.\n");
$pc = cols($pdo, 'products'); $cc = cols($pdo, 'product_categories');
$now = date('Y-m-d H:i:s');

$addCats = []; $addProds = [];
$hasCat = $pdo->prepare('SELECT COUNT(*) FROM product_categories WHERE slug = ?');
foreach ($data['categories'] as $c) {
    $hasCat->execute([$c['slug']]);
    if ((int)$hasCat->fetchColumn() > 0) { echo "  =  category {$c['slug']} already exists (kept)\n"; continue; }
    $addCats[] = $c; echo "  +  category {$c['slug']}: {$c['name']} ({$c['count']} products)\n";
}
$hasProd = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = ?');
$missingPhoto = 0;
foreach ($data['products'] as $p) {
    $hasProd->execute([$p['slug']]);
    if ((int)$hasProd->fetchColumn() > 0) continue;
    if (!is_file($ROOT . $p['image'])) $missingPhoto++;
    $addProds[] = $p;
}
echo '  +  ' . count($addProds) . " product(s)" . ($missingPhoto ? " ({$missingPhoto} photo file(s) missing: upload the deploy zip first)" : '') . "\n";
echo "\n" . count($addCats) . ' categor(ies) and ' . count($addProds) . " product(s) to add.\n";
if (!$apply) { echo "Dry run: nothing changed. Run again with --apply.\n"; exit(0); }
if (!$addCats && !$addProds) exit("Nothing to do.\n");

$pdo->beginTransaction();
try {
    foreach ($addCats as $c) {
        $title = 'Best ' . $c['name'] . ': ' . $c['count'] . ' Picks | Run On Console';
        insertRow($pdo, 'product_categories', ['slug' => $c['slug'], 'name' => $c['name'], 'description' => $c['desc'], 'image' => $c['image'],
            'status' => 'published', 'sort_rank' => $c['rank'], 'meta_title' => $title,
            'meta_description' => mb_substr($c['desc'] . ' ' . $c['count'] . ' picks with key details and Amazon links.', 0, 160),
            'is_noindex' => 0, 'created_at' => $now, 'updated_at' => $now, 'content_modified_at' => $now], $cc);
    }
    foreach ($addProds as $p) {
        $link = $p['affiliateLinks']['amazon'];
        insertRow($pdo, 'products', ['id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'category' => $p['category'],
            'category_slug' => $p['categorySlug'], 'brand' => $p['brand'], 'subtitle' => $p['subtitle'], 'badge' => $p['badge'], 'best_for' => $p['bestFor'],
            'short_desc' => $p['shortDesc'], 'summary' => $p['shortDesc'], 'specs_json' => json_encode($p['specs'], JSON_UNESCAPED_UNICODE),
            'features_json' => json_encode($p['features'], JSON_UNESCAPED_UNICODE), 'price_state' => 'unknown', 'image' => $p['image'], 'image_alt' => $p['title'],
            'pros' => '[]', 'cons' => '[]', 'affiliate_amazon' => $link, 'affiliate_links_json' => json_encode(['amazon' => $link], JSON_UNESCAPED_SLASHES),
            'meta_title' => mb_substr($p['title'] . ': Specs & Where to Buy | Run On Console', 0, 255), 'meta_description' => mb_substr($p['shortDesc'], 0, 160),
            'is_noindex' => 0, 'status' => 'published', 'draft_status' => 'none', 'version' => 1, 'sort_rank' => $p['rank'],
            'created_at' => $now, 'updated_at' => $now, 'content_modified_at' => $now], $pc);
    }
    $pdo->commit();
} catch (\Throwable $e) { $pdo->rollBack(); exit('Nothing added: ' . $e->getMessage() . "\n"); }

$file = $HOME . '/roc-expand-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'categories' => array_column($addCats, 'slug'), 'products' => array_column($addProds, 'slug')], JSON_PRETTY_PRINT));
echo 'Added ' . count($addCats) . ' categor(ies) and ' . count($addProds) . " product(s).\nUndo: php " . __FILE__ . " --rollback={$file}\n";
