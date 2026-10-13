<?php
/**
 * Run On Console — fixes from the site audit (Deploy 35).
 *
 *   php cli-site-fixes.php                  dry run: shows what would change
 *   php cli-site-fixes.php --apply          makes the changes, with a backup
 *   php cli-site-fixes.php --rollback=FILE  undoes an --apply
 *
 * What --apply does:
 *   1. .htaccess: /category/ (the menu link) -> /products/, /category/{x}/ -> /products/{x}/ (301).
 *   2. Menu saved in the CMS: a link to /category/ becomes /products/.
 *   2b. .htaccess: a Content-Security-Policy header that blocks framing by other sites, plugins and
 *       mixed content (it does not limit scripts, so analytics and the site keep working).
 *   3. Blog posts and news: links to /products/category/{x}/ (old address, 301) become /products/{x}/;
 *      links to a product that is no longer on the site go to its category page.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const FIX_BEGIN = '  # ROC fixes BEGIN (cli-site-fixes.php)';
const FIX_BLOCK = FIX_BEGIN . "\n"
    . "  # Old menu address: the product categories are at /products/\n"
    . "  RewriteRule ^category/?$ /products/ [R=301,L]\n"
    . "  RewriteRule ^category/([a-z0-9-]+)/?$ /products/\$1/ [R=301,L]\n"
    . "  # ROC fixes END\n";
// Blocks framing by other sites, plugins and mixed content; scripts are not limited, so analytics keep working.
const CSP_BEGIN = '# ROC csp BEGIN (cli-site-fixes.php)';
const CSP_BLOCK = "\n" . CSP_BEGIN . "\n<IfModule mod_headers.c>\n"
    . "  Header always set Content-Security-Policy \"upgrade-insecure-requests; frame-ancestors 'self'; object-src 'none'; base-uri 'self'\"\n"
    . "</IfModule>\n# ROC csp END\n";

echo "Run On Console - audit fixes\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/blog-engine-lib.php';

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b)) exit("No backup found in {$rollback}\n");
    if (!empty($b['htaccess'])) { file_put_contents($ROOT . '/.htaccess', $b['htaccess']); echo "Restored .htaccess\n"; }
    if (isset($b['nav'])) { rocLayerSave($pdo, $ROOT, 'nav', (array)$b['nav'], null); echo "Restored the menu\n"; }
    $u = $pdo->prepare('UPDATE blogs SET content = ? WHERE id = ?');
    foreach ((array)($b['blogs'] ?? []) as $id => $c) $u->execute([$c, $id]);
    exit('Restored ' . count((array)($b['blogs'] ?? [])) . " post(s).\n");
}

/* 1. .htaccess */
$ht = (string)@file_get_contents($ROOT . '/.htaccess');
$htDone = strpos($ht, FIX_BEGIN) !== false;
$anchor = null;
foreach (['  # ROC news BEGIN', '  # Blogs come from the CMS database', '  RewriteRule ^blogs$ /blogs/', '  # Real file or folder'] as $a) if (strpos($ht, $a) !== false) { $anchor = $a; break; }
$cspDone = strpos($ht, CSP_BEGIN) !== false;
echo $cspDone ? "  =  .htaccess already sends a Content-Security-Policy\n" : "  +  .htaccess: Content-Security-Policy header\n";
echo $htDone ? "  =  .htaccess already redirects /category/\n" : ($anchor ? "  +  .htaccess: /category/ -> /products/ (301)\n" : "  !  .htaccess: no place found for the redirect (skipped)\n");

/* 2. menu */
$navRaw = rocLayerSettingGet($pdo, 'site_nav');
$nav = $navRaw ? json_decode($navRaw, true) : null;
$navNew = null;
if (is_array($nav)) {
    $fix = function (&$node) use (&$fix, &$changed) {
        if (!is_array($node)) return;
        foreach ($node as $k => &$v) {
            if ($k === 'url' && is_string($v) && preg_match('#^/category(/([a-z0-9-]+))?/?$#', $v, $m)) { $v = '/products/' . (!empty($m[2]) ? $m[2] . '/' : ''); $changed = true; }
            elseif (is_array($v)) $fix($v);
        }
    };
    $changed = false; $copy = $nav; $fix($copy);
    if ($changed) { $navNew = $copy; echo "  +  Menu: /category/ link -> /products/\n"; } else echo "  =  Menu has no /category/ link\n";
} else echo "  =  Menu: the built-in one\n";

/* 3. posts */
$S = rocBlogSite($pdo, true);
$catOf = [];
foreach ($pdo->query('SELECT slug, category, status FROM products')->fetchAll(PDO::FETCH_ASSOC) as $p) {
    if ($p['status'] === 'published') continue;
    $cs = '';
    foreach ($S['cats'] as $c) if (strcasecmp($c['name'], (string)$p['category']) === 0 || $c['slug'] === $p['category']) $cs = $c['slug'];
    $catOf[$p['slug']] = $cs !== '' ? '/products/' . $cs . '/' : '/products/';
}
$posts = [];
foreach ($pdo->query('SELECT id, slug, content FROM blogs')->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $c = (string)$b['content'];
    $n = str_replace(['href="/products/category/', 'href="https://runonconsole.com/products/category/'], ['href="/products/', 'href="/products/'], $c);
    $n = (string)preg_replace_callback('#href="(?:https://runonconsole\.com)?/products/([a-z0-9-]+)/"#', function ($m) use ($catOf) {
        return isset($catOf[$m[1]]) ? 'href="' . $catOf[$m[1]] . '"' : $m[0];
    }, $n);
    if ($n !== $c) { $posts[$b['id']] = [$c, $n]; echo "  +  post {$b['slug']}: old links fixed\n"; }
}
echo "\n" . count($posts) . " post(s) to fix.\n";
if (!$apply) { echo "Dry run: nothing changed. Run again with --apply.\n"; exit(0); }

$file = $HOME . '/roc-fixes-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'htaccess' => ((!$htDone && $anchor) || !$cspDone) ? $ht : '', 'nav' => $navNew !== null ? $nav : null,
    'blogs' => array_map(function ($x) { return $x[0]; }, $posts)], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
if (!$htDone && $anchor) {
    file_put_contents($ROOT . '/.htaccess.roc-tmp', str_replace($anchor, FIX_BLOCK . "\n" . $anchor, $ht));
    rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
    echo ".htaccess updated\n";
}
if (!$cspDone) {
    file_put_contents($ROOT . '/.htaccess', rtrim((string)file_get_contents($ROOT . '/.htaccess')) . "\n" . CSP_BLOCK);
    echo "Content-Security-Policy added\n";
}
if ($navNew !== null) { $k = rocLayerSave($pdo, $ROOT, 'nav', $navNew, null); echo "Menu updated ({$k} built page(s) redrawn)\n"; }
$u = $pdo->prepare('UPDATE blogs SET content = ?, updated_at = ? WHERE id = ?');
foreach ($posts as $id => [, $n]) $u->execute([$n, gmdate('Y-m-d H:i:s'), $id]);
echo 'Fixed ' . count($posts) . " post(s).\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
