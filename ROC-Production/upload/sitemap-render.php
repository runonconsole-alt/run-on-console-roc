<?php
/**
 * Run On Console — automatic sitemaps.
 *
 *   /sitemap.xml                          index of all sitemaps              (sitemap-render.php)
 *   /sitemaps/pages-sitemap.xml           main site pages                    (?map=pages)
 *   /sitemaps/products-sitemap.xml        every product page                 (?map=products)
 *   /sitemaps/categories-sitemap.xml      every category page                (?map=categories)
 *   /sitemaps/blogs-sitemap.xml           published blogs      -> blog-render.php
 *   /sitemaps/news-sitemap.xml            news, last 2 days    -> blog-render.php?news=1
 *   /sitemaps/cms-pages-sitemap.xml       CMS pages (indexed)  -> page-render.php
 *
 * Pages, products and categories are read from the built website folders, so a new
 * product or category page is listed as soon as it exists. Never listed:
 *   - login, sign-up, forgot/reset password, email verification (/auth/…)
 *   - profile, admin, CMS, API
 *   - any page whose HTML says noindex (e.g. the 404 page)
 */

declare(strict_types=1);

const ROC_SM_BASE = 'https://runonconsole.com';
const ROC_SM_PRIVATE = ['auth', 'profile', 'admin', 'cms', 'api', 'agent', 'cms-templates', 'uploads', 'images',
                        'assets', 'STEP-B', 'migrations', 'cgi-bin', '.well-known', 'sitemaps', '404', 'blogs'];

$root = __DIR__;
$map = (string)($_GET['map'] ?? '');

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=600');
header('X-Robots-Tag: noindex');   // the sitemap file itself should not appear in search results

function rocSmEsc(string $s): string { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

/** Page names from each built page's <title> (shown by /sitemap.xsl; search engines ignore them). */
$ROC_SM_TITLES = [];
const ROC_SM_STYLE = '<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>';

/** Deploy 161: the database (once), for the real dates below. Null when it cannot be reached. */
function rocSmPdo(string $root): ?PDO {
    static $done = false, $pdo = null;
    if ($done) return $pdo;
    $done = true;
    try {
        ob_start();
        require_once $root . '/api/v1/config.php';
        ob_end_clean();
        $pdo = function_exists('getDBConnection') ? (getDBConnection() ?: null) : null;
    } catch (\Throwable $e) {
        while (ob_get_level() > 0) ob_end_clean();
        $pdo = null;
    }
    // config.php is shared with the API (session, no-cache headers): undo that for the sitemap.
    if (!headers_sent()) {
        foreach (['Set-Cookie', 'Expires', 'Pragma', 'Cache-Control', 'Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials'] as $h) header_remove($h);
        header('Cache-Control: public, max-age=600');
    }
    return $pdo;
}

/** The newest date a query returns (Unix time), 0 when there is none. */
function rocSmNewest(?PDO $pdo, string $sql): int {
    if (!$pdo) return 0;
    try { $v = (string)$pdo->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; }
    $t = $v !== '' && strpos($v, '0000-00-00') !== 0 ? (int)strtotime($v . ' UTC') : 0;
    return $t > 0 && $t <= time() ? $t : 0;
}

/** Built pages under $dir (or the site root), indexable ones only: [path => mtime]. */
function rocSmBuilt(string $root, ?string $dir): array {
    $base = $dir === null ? $root : $root . '/' . $dir;
    if (!is_dir($base)) return [];
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        function ($f, $k, $iter) use ($root) {
            if ($iter->hasChildren()) {
                $rel = substr($f->getPathname(), strlen($root) + 1);
                $first = explode('/', str_replace('\\', '/', $rel))[0];
                return !in_array($first, ROC_SM_PRIVATE, true);
            }
            return $f->getFilename() === 'index.html';
        }));
    foreach ($it as $f) {
        $path = str_replace('\\', '/', substr($f->getPathname(), strlen($root)));
        $path = substr($path, 0, -strlen('index.html'));
        if ($dir !== null && $path === '/' . $dir . '/') continue;   // the listing page is in pages-sitemap
        $first = explode('/', trim($path, '/'))[0] ?? '';
        if ($first !== '' && in_array($first, ROC_SM_PRIVATE, true)) continue;
        $head = (string)file_get_contents($f->getPathname(), false, null, 0, 20000);
        if (preg_match('#<meta\s+name=["\']robots["\']\s+content=["\'][^"\']*noindex#i', $head)) continue;
        if ($path === '/categories/') $path = '/gaming-platforms/';   // served by category-render.php
        if (in_array($path, ['/partnerships/', '/policy/', '/author/roc-team/'], true)) continue;   // these redirect (cli-gaming-platforms-url.php, cli-privacy-policy.php, cli-author-omar.php)
        $out[$path] = $f->getMTime();
        if ($path === '/') $GLOBALS['ROC_SM_TITLES'][$path] = 'Home';
        elseif (preg_match('#<title>([^<]+)</title>#i', $head, $tm)) {
            $GLOBALS['ROC_SM_TITLES'][$path] = trim(preg_replace('/\s*\|\s*Run On Console\s*$/', '', html_entity_decode($tm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
    }
    // Root page scan also found products/ and categories/ - they have their own sitemaps.
    if ($dir === null) {
        foreach (array_keys($out) as $p) {
            if (preg_match('#^/(products|categories)/.+/$#', $p)) unset($out[$p]);
        }
    }
    ksort($out);
    return $out;
}

function rocSmUrlset(array $urls): void {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . ROC_SM_STYLE . "\n"
       . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $lib = __DIR__ . '/api/v1/cms/site-layer-lib.php';
    if (is_file($lib)) require_once $lib;
    foreach ($urls as $path => $mtime) {
        if (function_exists('rocLayerSitemapSkip') && rocLayerSitemapSkip(__DIR__, $path)) continue;   // CMS > Sitemap
        $t = $GLOBALS['ROC_SM_TITLES'][$path] ?? '';
        echo '  <url><loc>' . rocSmEsc(ROC_SM_BASE . $path) . '</loc><lastmod>' . gmdate('c', $mtime) . '</lastmod>'
           . ($t !== '' && ($t = rtrim((string)preg_replace(['/[\x00-\x1F]+/', '/-{2,}/'], [' ', '-'], $t), '-')) !== '' ? '<!--t:' . $t . '-->' : '') . "</url>\n";   // name for the stylesheet (Deploy 77: not roc:title)
    }
    echo "</urlset>\n";
    exit;
}

if ($map === 'pages') {
    $urls = rocSmBuilt($root, null);
    // Extra addresses added in CMS > Sitemap (pages of this site that the scan cannot see).
    $lib = $root . '/api/v1/cms/site-layer-lib.php';
    if (is_file($lib)) {
        require_once $lib;
        foreach ((array)(rocLayerLoad($root)['sitemap']['extra'] ?? []) as $e) {
            $p = (string)($e['path'] ?? '');
            if (!preg_match('#^/[a-z0-9/_.\-]*$#i', $p) || isset($urls[$p])) continue;
            $urls[$p] = (int)(strtotime((string)(rocLayerLoad($root)['updated'] ?? '')) ?: time());
            if (trim((string)($e['title'] ?? '')) !== '') $GLOBALS['ROC_SM_TITLES'][$p] = trim((string)$e['title']);
        }
    }
    // Pages served from the CMS database by blog-render.php (no built folder to scan).
    $ht = (string)@file_get_contents($root . '/.htaccess');
    if (strpos($ht, 'ROC updates BEGIN') !== false && !isset($urls['/updates/'])) { $urls['/updates/'] = rocSmNewest(rocSmPdo($root), "SELECT MAX(COALESCE(content_modified_at, updated_at, published_at)) FROM blogs WHERE status = 'published'") ?: time(); $GLOBALS['ROC_SM_TITLES']['/updates/'] = 'Updates: blogs, news and guides'; }
    rocSmUrlset($urls);
}
if ($map === 'products')   rocSmUrlset(rocSmBuilt($root, 'products'));
if ($map === 'categories') rocSmUrlset(rocSmBuilt($root, 'categories'));

/* index */
$latest = function (array $urls): int { return $urls ? max($urls) : time(); };
// Deploy 161: real dates. time() here told Google every sitemap had changed at each visit, so it stopped trusting them.
$pdo = rocSmPdo($root);
$postTime = "MAX(COALESCE(content_modified_at, updated_at, published_at))";
$children = [
    'pages'      => [$latest(rocSmBuilt($root, null)), 'Main pages'],
    'products'   => [rocSmNewest($pdo, "SELECT MAX(COALESCE(content_modified_at, updated_at, created_at)) FROM products WHERE status = 'published'"), 'Products and product categories'],
    'categories' => [rocSmNewest($pdo, "SELECT MAX(updated_at) FROM gaming_categories WHERE status = 'published'"), 'Gaming platforms'],
    'blogs'      => [rocSmNewest($pdo, "SELECT $postTime FROM blogs WHERE status = 'published' AND (category IS NULL OR (category <> 'News' AND category NOT LIKE 'Guide%'))"), 'Blog posts'],
    'news'       => [rocSmNewest($pdo, "SELECT $postTime FROM blogs WHERE status = 'published' AND category = 'News'"), 'News stories'],
];
// "Can I run it?" game pages (game-render.php, switched on by cli-game-pages.php).
// Guides (/guides/, switched on by cli-guides.php).
if (strpos((string)@file_get_contents($root . '/.htaccess'), 'ROC guides BEGIN') !== false) $children['guides'] = [rocSmNewest($pdo, "SELECT $postTime FROM blogs WHERE status = 'published' AND category LIKE 'Guide%'"), 'Guides'];
if (is_file($root . '/game-render.php') && is_file($root . '/roc-steam-games.json')) $children['games'] = [(int)filemtime($root . '/roc-steam-games.json'), 'Can I run it? game pages'];
// Deploy 177: the game pages in other languages, once cli-languages.php has added their routes.
if (is_file($root . '/game-i18n.php') && strpos((string)@file_get_contents($root . '/.htaccess'), '# ROC i18n BEGIN') !== false)
    foreach (['de', 'es', 'pt', 'fr', 'ru'] as $lg) $children['games-' . $lg] = [(int)filemtime($root . '/roc-steam-games.json'), 'Game pages (' . $lg . ')'];
// CMS pages (About-style pages made in the CMS) are listed only once there is at least one.
try {
    $n = $pdo ? (int)$pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'published' AND (is_noindex = 0 OR is_noindex IS NULL)")->fetchColumn() : 1;
    if ($n > 0) $children['cms-pages'] = [rocSmNewest($pdo, "SELECT MAX(updated_at) FROM pages WHERE status = 'published'"), 'CMS pages'];
} catch (\Throwable $e) {
    $children['cms-pages'] = [0, 'CMS pages'];
}
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . ROC_SM_STYLE . "\n"
   . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
// Plain sitemap index: Google reports any extra tag here as "Invalid XML tag" (sitemap.xsl names them from the address).
foreach ($children as $name => [$t, $label]) {
    echo '  <sitemap><loc>' . ROC_SM_BASE . '/sitemaps/' . $name . '-sitemap.xml</loc>' . ($t > 0 ? '<lastmod>' . gmdate('c', $t) . '</lastmod>' : '') . "</sitemap>\n";
}
echo "</sitemapindex>\n";
