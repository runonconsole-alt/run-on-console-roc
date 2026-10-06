<?php
/**
 * Run On Console — serve product pages from the CMS database (product-render.php).
 *
 *   php cli-product-routing.php                 dry run: shows what would change
 *   php cli-product-routing.php --apply         makes the changes, with a backup
 *   php cli-product-routing.php --rollback=DIR  undoes an --apply using its backup
 *
 * What --apply does:
 *   1. Database: adds the product fields the website shows but the table lacked
 *      (category_slug, brand, subtitle, badge, best_for, features_json, sort_rank)
 *      and fills them from roc-product-catalog.json. Only empty fields are filled;
 *      nothing already in the table is changed. A full copy of the products table
 *      is kept first (roc_products_bak_<date>).
 *   2. Database: creates product_categories (name, description, image, show/hide,
 *      order, SEO fields) from the same file, so categories can be edited in the CMS.
 *   3. Saves the current prerendered product page, listing and category page as
 *      layout templates in public_html/cms-templates/ (blocked from the web).
 *   4. .htaccess: routes /products/... and the products sitemap to
 *      product-render.php (block between "ROC products BEGIN/END" markers).
 *
 * Files to upload BEFORE running it: product-render.php (public_html/),
 * roc-nav.js (public_html/), this file and roc-product-catalog.json
 * (public_html/api/v1/cron/). The old static product folders are left in place:
 * product-render.php serves them only if the database is unreachable.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);                       // public_html
$HOME = dirname($ROOT);                            // /home2/runoncon
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const MARK_BEGIN = '  # ROC products BEGIN (cli-product-routing.php)';
const MARK_END   = '  # ROC products END';
const HTACCESS_BLOCK = MARK_BEGIN . "\n"
    . "  # Products come from the CMS database (product-render.php), not the old static folders\n"
    . "  RewriteRule ^products/index\\.html$ /products/ [R=301,L,NC]\n"
    . "  RewriteRule ^products/(.+)/index\\.html$ /products/\$1/ [R=301,L,NC]\n"
    . "  RewriteRule ^products/category/([a-z0-9-]+)$ /products/category/\$1/ [R=301,L]\n"
    . "  RewriteRule ^products/([a-z0-9-]+)$ /products/\$1/ [R=301,L]\n"
    . "  RewriteRule ^products/$ product-render.php [L,QSA]\n"
    . "  RewriteRule ^products/category/([a-z0-9-]+)/$ product-render.php?cat=\$1 [L,QSA]\n"
    . "  RewriteRule ^products/([a-z0-9-]+)/$ product-render.php?slug=\$1 [L,QSA]\n"
    . "  RewriteRule ^sitemaps/products-sitemap\\.xml$ product-render.php?sitemap=1 [L]\n"
    . MARK_END . "\n";

const NEW_COLUMNS = [
    'category_slug' => 'VARCHAR(80) NULL',
    'brand'         => 'VARCHAR(120) NULL',
    'subtitle'      => 'VARCHAR(255) NULL',
    'badge'         => 'VARCHAR(120) NULL',
    'best_for'      => 'VARCHAR(255) NULL',
    'features_json' => 'TEXT NULL',
    'sort_rank'     => 'INT NULL',
];

echo "Run On Console - product pages from the CMS\n";
echo "public_html: {$ROOT}\n\n";

/* ------------------------------------------------------------ database */
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("STOP: could not connect to the database.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function tableColumns(PDO $pdo, string $table): ?array {
    try { $st = $pdo->query("SELECT * FROM {$table} LIMIT 0"); }
    catch (\Throwable $e) { return null; }
    $cols = [];
    for ($i = 0; $i < $st->columnCount(); $i++) $cols[] = strtolower((string)$st->getColumnMeta($i)['name']);
    return $cols;
}
function now(): string { return date('Y-m-d H:i:s'); }

/* -------------------------------------------------------------- rollback */
if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    $manFile = $dir . '/MANIFEST.json';
    if (!is_file($manFile)) exit("Backup folder not found or has no MANIFEST.json: {$dir}\n");
    $man = json_decode((string)file_get_contents($manFile), true) ?: [];

    if (!empty($man['htaccess']) && is_file($dir . '/files/.htaccess')) {
        copy($dir . '/files/.htaccess', $ROOT . '/.htaccess');
        echo "Restored .htaccess\n";
    }
    foreach ($man['templates_created'] ?? [] as $t) {
        if (is_file($ROOT . '/cms-templates/' . $t)) { unlink($ROOT . '/cms-templates/' . $t); echo "Removed cms-templates/{$t}\n"; }
    }
    if (!empty($man['categories_table_created'])) {
        $pdo->exec('DROP TABLE product_categories');
        echo "Dropped product_categories\n";
    }
    foreach ($man['columns_added'] ?? [] as $c) {
        if (!array_key_exists($c, NEW_COLUMNS)) continue;          // only ever drop what this script adds
        $pdo->exec("ALTER TABLE products DROP COLUMN {$c}");
        echo "Dropped products.{$c}\n";
    }
    echo "\nDone. The products table copy {$man['backup_table']} was kept; drop it when you no longer need it.\n";
    exit(0);
}

/* ---------------------------------------------------------------- checks */
$problems = [];
foreach (['product-render.php', 'blog-render.php', 'roc-nav.js', '.htaccess'] as $f) {
    if (!is_file($ROOT . '/' . $f)) $problems[] = "public_html/{$f} is missing.";
}
if (is_file($ROOT . '/roc-nav.js') && strpos((string)file_get_contents($ROOT . '/roc-nav.js'), '/products') === false) {
    $problems[] = 'public_html/roc-nav.js is the old version (it does not send /products links to the server). Upload the new one.';
}

$catalogFile = __DIR__ . '/roc-product-catalog.json';
$catalog = is_file($catalogFile) ? json_decode((string)file_get_contents($catalogFile), true) : null;
if (!is_array($catalog) || empty($catalog['products']) || empty($catalog['categories'])) {
    $problems[] = 'api/v1/cron/roc-product-catalog.json is missing or unreadable.';
}

$productCols = tableColumns($pdo, 'products');
if ($productCols === null) $problems[] = 'The products table does not exist.';

$templates = [
    'product-list.html'     => $ROOT . '/products/index.html',
    'product-category.html' => null,
    'product-page.html'     => null,
];
if (is_array($catalog)) {
    foreach ($catalog['categories'] ?? [] as $c) {
        $f = $ROOT . '/products/category/' . $c['slug'] . '/index.html';
        if (is_file($f)) { $templates['product-category.html'] = $f; break; }
    }
    foreach ($catalog['products'] ?? [] as $p) {
        $f = $ROOT . '/products/' . $p['slug'] . '/index.html';
        if (is_file($f)) { $templates['product-page.html'] = $f; break; }
    }
}
$tplDir = $ROOT . '/cms-templates';
foreach ($templates as $name => $src) {
    if (is_file($tplDir . '/' . $name)) continue;
    if (!$src || !is_file($src)) { $problems[] = "No prerendered page to use for cms-templates/{$name}."; continue; }
    $h = (string)file_get_contents($src);
    foreach (['<title', '</head>', '<main', '</main>'] as $needle) {
        if (stripos($h, $needle) === false) $problems[] = substr($src, strlen($ROOT) + 1) . " has no {$needle}";
    }
}

$ht = is_file($ROOT . '/.htaccess') ? (string)file_get_contents($ROOT . '/.htaccess') : '';
$htDone = strpos($ht, MARK_BEGIN) !== false;
$anchor = null;
foreach (['  # Sitemaps are generated automatically', '  # Real file or folder'] as $a) {
    if (strpos($ht, $a) !== false) { $anchor = $a; break; }
}
if (!$htDone && $anchor === null) $problems[] = '.htaccess has no place to add the product rules (expected the "Sitemaps are generated" or "Real file or folder" comment).';

if ($problems) { echo "STOP:\n  - " . implode("\n  - ", $problems) . "\n"; exit(1); }

/* ------------------------------------------------------------------ plan */
$missingCols = array_values(array_diff(array_keys(NEW_COLUMNS), $productCols));
$catCols = tableColumns($pdo, 'product_categories');
$bySlug = [];
foreach ($catalog['products'] as $p) $bySlug[$p['slug']] = $p;
$rows = $pdo->query('SELECT * FROM products')->fetchAll(PDO::FETCH_ASSOC);
$matched = 0;
foreach ($rows as $r) if (isset($bySlug[(string)$r['slug']])) $matched++;

echo "1) Database: products table\n";
echo '   new columns: ' . ($missingCols ? implode(', ', $missingCols) : 'none (already there)') . "\n";
echo '   products in table: ' . count($rows) . ", matched in catalog file: {$matched} (empty fields get filled, nothing is overwritten)\n";
echo "2) Database: product_categories\n";
echo '   ' . ($catCols === null ? 'create with ' . count($catalog['categories']) . ' categories' : 'already exists, missing categories get added') . "\n";
echo "3) Layout templates -> cms-templates/\n";
foreach ($templates as $name => $src) {
    echo "   {$name}  " . (is_file($tplDir . '/' . $name) ? '(exists, kept)' : '<- ' . substr((string)$src, strlen($ROOT) + 1)) . "\n";
}
echo "4) .htaccess: " . ($htDone ? "product rules already present\n" : "add product rules before \"" . trim($anchor) . "\"\n");

if (!$apply) {
    echo "\nDRY RUN - nothing changed. Run again with --apply.\n";
    exit(0);
}

/* ----------------------------------------------------------------- apply */
$stamp  = date('Ymd-His');
$backup = $HOME . '/roc-products-backup-' . $stamp;
if (!mkdir($backup . '/files', 0700, true)) exit("Could not create backup folder {$backup}\n");
$man = ['created' => date('c'), 'htaccess' => false, 'templates_created' => [], 'columns_added' => [],
        'categories_table_created' => false, 'backup_table' => 'roc_products_bak_' . str_replace('-', '', $stamp)];
$save = function () use (&$man, $backup) { file_put_contents($backup . '/MANIFEST.json', json_encode($man, JSON_PRETTY_PRINT)); };

copy($ROOT . '/.htaccess', $backup . '/files/.htaccess');
$pdo->exec("CREATE TABLE {$man['backup_table']} AS SELECT * FROM products");
$save();
echo "\nBackup: {$backup}\nProducts table copy: {$man['backup_table']}\n";

// 1. columns + backfill (DDL first: MySQL commits DDL implicitly)
foreach ($missingCols as $c) {
    $pdo->exec("ALTER TABLE products ADD COLUMN {$c} " . NEW_COLUMNS[$c]);
    $man['columns_added'][] = $c;
    $save();
}

$catBySlug = [];
foreach ($catalog['categories'] as $i => $c) $catBySlug[$c['slug']] = $c + ['rank' => $i + 1];

$filled = 0;
$pdo->beginTransaction();
try {
    $rows = $pdo->query('SELECT * FROM products')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $src = $bySlug[(string)$r['slug']] ?? null;
        $want = [];
        if ($src) {
            $want = [
                'category_slug' => $src['categorySlug'] ?? '',
                'brand'         => $src['brand'] ?? '',
                'subtitle'      => $src['subtitle'] ?? '',
                'badge'         => $src['badge'] ?? '',
                'best_for'      => $src['bestFor'] ?? '',
                'features_json' => !empty($src['features']) ? json_encode(array_values($src['features']), JSON_UNESCAPED_UNICODE) : '',
                'sort_rank'     => isset($src['rank']) ? (string)(int)$src['rank'] : '',
            ];
        } else {
            // Added in the CMS later: at least link it to its category by name.
            foreach ($catBySlug as $s => $c) {
                if (strcasecmp(trim((string)($r['category'] ?? '')), (string)$c['name']) === 0) { $want['category_slug'] = $s; break; }
            }
        }
        $set = [];
        foreach ($want as $col => $val) {
            if ($val === '' || trim((string)($r[$col] ?? '')) !== '') continue;   // never overwrite
            $set[$col] = $val;
        }
        if (!$set) continue;
        $sql = 'UPDATE products SET ' . implode(', ', array_map(function ($c) { return "{$c} = ?"; }, array_keys($set))) . ' WHERE id = ?';
        $pdo->prepare($sql)->execute([...array_values($set), $r['id']]);
        $filled++;
    }
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    exit("STOP: filling product fields failed (" . $e->getMessage() . "). Nothing was filled.\n"
       . "Undo the new columns with: php " . __FILE__ . " --rollback={$backup}\n");
}
echo "Products updated: {$filled}\n";

// 2. categories
if ($catCols === null) {
    $pdo->exec('CREATE TABLE product_categories (
        slug VARCHAR(80) NOT NULL PRIMARY KEY,
        name VARCHAR(160) NOT NULL,
        description TEXT NULL,
        image VARCHAR(500) NULL,
        status VARCHAR(20) NOT NULL DEFAULT \'published\',
        sort_rank INT NOT NULL DEFAULT 0,
        meta_title VARCHAR(255) NULL,
        meta_description VARCHAR(500) NULL,
        is_noindex TINYINT NOT NULL DEFAULT 0,
        created_at DATETIME NULL,
        updated_at DATETIME NULL,
        content_modified_at DATETIME NULL
    )');
    $man['categories_table_created'] = true;
    $save();
}
$have = array_flip($pdo->query('SELECT slug FROM product_categories')->fetchAll(PDO::FETCH_COLUMN));
$ins = $pdo->prepare('INSERT INTO product_categories (slug, name, description, image, status, sort_rank, created_at, updated_at, content_modified_at)
                      VALUES (?, ?, ?, ?, \'published\', ?, ?, ?, NULL)');
$added = 0;
foreach ($catBySlug as $s => $c) {
    if (isset($have[$s])) continue;
    $t = now();
    // content_modified_at stays empty until the category is edited in the CMS, so
    // its sitemap lastmod follows its products instead of the install date.
    $ins->execute([$s, $c['name'], $c['desc'] ?? '', $c['image'] ?? '', $c['rank'], $t, $t]);
    $added++;
}
echo "Categories added: {$added}\n";

// 3. templates
if (!is_dir($tplDir)) mkdir($tplDir, 0755);
if (!is_file($tplDir . '/.htaccess')) file_put_contents($tplDir . '/.htaccess', "Require all denied\n");
foreach ($templates as $name => $src) {
    if (is_file($tplDir . '/' . $name)) continue;
    copy($src, $tplDir . '/' . $name);
    $man['templates_created'][] = $name;
    $save();
}
echo "Templates saved in cms-templates/\n";

// 4. .htaccess (last, so the site only switches once everything above is ready)
if (!$htDone) {
    $new = str_replace($anchor, HTACCESS_BLOCK . "\n" . $anchor, $ht);
    file_put_contents($ROOT . '/.htaccess.roc-tmp', $new);
    rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
    $man['htaccess'] = true;
    $save();
    echo ".htaccess updated\n";
}

echo "\nDone.\n";
echo "Check: https://runonconsole.com/products/  and  https://runonconsole.com/sitemaps/products-sitemap.xml\n";
echo "Rollback: php " . __FILE__ . " --rollback={$backup}\n";
