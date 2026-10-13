<?php
/**
 * Run On Console — category pages move to /category/{slug}/ (Deploy 40).
 *
 *   php cli-category-urls.php                  dry run: shows what would change
 *   php cli-category-urls.php --apply          makes the changes, with a backup
 *   php cli-category-urls.php --rollback=FILE  undoes an --apply
 *
 * Before: /products/gpu/, /products/pc-hardware/ (category and group pages under /products/).
 * After:  /category/gpu/, /category/pc-hardware/; the products stay at /products/{product}/.
 * The old addresses send visitors and Google to the new ones (301, done by product-render.php).
 *
 * What --apply does:
 *   1. .htaccess: /category/{x}/ shows that category (it used to redirect to /products/{x}/).
 *   2. CMS settings (menu, footer, metas, page code, announcement, noindex and sitemap rules,
 *      site edits): /products/{category}/ becomes /category/{category}/. The footer gets a
 *      "Can I Run It? PC Game Checker" link (the old "Systems" link is renamed).
 *   3. Blog posts and news: links to category pages point to /category/{x}/ (no extra redirect).
 *   4. api/v1/cms/product-redirects.json (deleted products): sends them to /category/{x}/.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);
$MAP = $ROOT . '/api/v1/cms/product-redirects.json';
const OLD_RULE = '  RewriteRule ^category/([a-z0-9-]+)/?$ /products/$1/ [R=301,L]';
const NEW_RULE = "  RewriteRule ^category/([a-z0-9-]+)$ /category/\$1/ [R=301,L]\n"
               . "  RewriteRule ^category/([a-z0-9-]+)/$ product-render.php?slug=\$1&catpage=1 [L,QSA]";

echo "Run On Console - category pages at /category/\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/site-layer-lib.php';

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b)) exit("No backup found in {$rollback}\n");
    if (!empty($b['htaccess'])) { file_put_contents($ROOT . '/.htaccess', $b['htaccess']); echo "Restored .htaccess\n"; }
    foreach ((array)($b['layer'] ?? []) as $part => $raw) { rocLayerSave($pdo, $ROOT, $part, (array)json_decode($raw, true), null); echo "Restored {$part}\n"; }
    $u = $pdo->prepare('UPDATE blogs SET content = ? WHERE id = ?');
    foreach ((array)($b['blogs'] ?? []) as $id => $c) $u->execute([$c, $id]);
    if (isset($b['map'])) file_put_contents($MAP, $b['map']);
    exit('Restored ' . count((array)($b['blogs'] ?? [])) . " post(s).\n");
}

/* Category and group slugs */
$slugs = ['pc-hardware', 'gaming-hardware'];
foreach ($pdo->query('SELECT slug FROM product_categories')->fetchAll(PDO::FETCH_COLUMN) as $s) if ($s !== '') $slugs[] = (string)$s;
$alt = implode('|', array_map(function ($s) { return preg_quote($s, '#'); }, array_unique($slugs)));
$move = function (string $text) use ($alt): string {
    $text = (string)preg_replace('#(https://runonconsole\.com)?/products/(?:category/)?(' . $alt . ')/#', '$1/category/$2/', $text);
    return (string)preg_replace('#(https:\\\\/\\\\/runonconsole\.com)?\\\\/products\\\\/(?:category\\\\/)?(' . $alt . ')\\\\/#', '$1\\/category\\/$2\\/', $text);
};

/* 1. .htaccess */
$ht = (string)@file_get_contents($ROOT . '/.htaccess');
$htNew = null;
if (strpos($ht, OLD_RULE) !== false) { $htNew = str_replace(OLD_RULE, NEW_RULE, $ht); echo "  +  .htaccess: /category/{x}/ shows the category page\n"; }
elseif (strpos($ht, 'catpage=1') !== false) echo "  =  .htaccess already shows category pages at /category/\n";
else {
    $anchor = '  RewriteRule ^category/$ product-render.php?hub=1 [L,QSA]';
    if (strpos($ht, $anchor) !== false) { $htNew = str_replace($anchor, $anchor . "\n" . NEW_RULE, $ht); echo "  +  .htaccess: /category/{x}/ shows the category page\n"; }
    else echo "  !  .htaccess: the /category/ block was not found. Run cli-site-fixes.php --apply first, then this again.\n";
}

/* 2. CMS settings */
$layer = []; $layerOld = [];
foreach (ROC_LAYER_KEYS as $part => $key) {
    if ($part === 'tracking') continue;
    $raw = rocLayerSettingGet($pdo, $key);
    if ($raw === null || $raw === '') continue;
    $new = $move($raw);
    if ($part === 'nav') {
        $nav = json_decode($new, true);
        if (is_array($nav) && !empty($nav['footer']['columns']) && is_array($nav['footer']['columns'])) {
            $has = false;
            foreach ($nav['footer']['columns'] as &$col) foreach ((array)($col['links'] ?? []) as $i => $l) {
                if (rtrim((string)($l['url'] ?? ''), '/') === '/compatibility') { $has = true; if (stripos((string)$l['label'], 'can i run') === false) $col['links'][$i]['label'] = 'Can I Run It? PC Game Checker'; }
            }
            unset($col);
            if (!$has) {
                $nav['footer']['columns'][0]['links'] = array_values((array)($nav['footer']['columns'][0]['links'] ?? []));
                array_splice($nav['footer']['columns'][0]['links'], min(2, count($nav['footer']['columns'][0]['links'])), 0, [['label' => 'Can I Run It? PC Game Checker', 'url' => '/compatibility/', 'icon' => 'gamepad']]);
            }
            $new = (string)json_encode($nav, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (json_decode($raw, true) == $nav) $new = $raw;
        }
    }
    if ($new !== $raw && json_decode($new, true) !== null) { $layer[$part] = $new; $layerOld[$part] = $raw; echo "  +  CMS {$part}: category links moved to /category/" . ($part === 'nav' ? ' (+ footer Can I Run It link)' : '') . "\n"; }
}
if (!isset($layer['nav'])) echo "  =  Menu and footer: nothing to change (the built-in menu already uses /category/)\n";

/* 3. posts */
$posts = [];
foreach ($pdo->query('SELECT id, slug, content FROM blogs')->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $c = (string)$b['content'];
    $n = (string)preg_replace('#href="(https://runonconsole\.com)?/products/(?:category/)?(' . $alt . ')/"#', 'href="/category/$2/"', $c);
    if ($n !== $c) { $posts[$b['id']] = [$c, $n]; echo "  +  post {$b['slug']}: category links\n"; }
}

/* 4. deleted products */
$mapRaw = (string)@file_get_contents($MAP);
$mapNew = $mapRaw !== '' ? $move($mapRaw) : '';
if ($mapNew !== $mapRaw) echo "  +  deleted products now open /category/{x}/\n";

echo "\n" . count($posts) . " post(s), " . count($layer) . " CMS setting(s) to change.\n";
if (!$apply) { echo "Dry run: nothing changed. Run again with --apply.\n"; exit(0); }

$file = $HOME . '/roc-category-urls-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'htaccess' => $htNew !== null ? $ht : '', 'layer' => $layerOld,
    'blogs' => array_map(function ($x) { return $x[0]; }, $posts), 'map' => $mapRaw], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
if ($htNew !== null) {
    file_put_contents($ROOT . '/.htaccess.roc-tmp', $htNew);
    rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
    echo ".htaccess updated\n";
}
foreach ($layer as $part => $raw) { $k = rocLayerSave($pdo, $ROOT, $part, (array)json_decode($raw, true), null); echo "Saved {$part} ({$k} built page(s) redrawn)\n"; }
$u = $pdo->prepare('UPDATE blogs SET content = ?, updated_at = ? WHERE id = ?');
foreach ($posts as $id => [, $n]) $u->execute([$n, gmdate('Y-m-d H:i:s'), $id]);
if ($mapNew !== $mapRaw) file_put_contents($MAP, $mapNew);
echo 'Fixed ' . count($posts) . " post(s).\nCheck: https://runonconsole.com/category/pc-hardware/\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
