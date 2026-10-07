<?php
/**
 * Run On Console — automatic sitemaps.
 *
 *   /sitemap.xml                          index of all sitemaps              (sitemap-render.php)
 *   /sitemaps/pages-sitemap.xml           main site pages                    (?map=pages)
 *   /sitemaps/products-sitemap.xml        every product page                 (?map=products)
 *   /sitemaps/categories-sitemap.xml      every category page                (?map=categories)
 *   /sitemaps/blogs-sitemap.xml           published blogs      -> blog-render.php
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
       . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:roc="https://runonconsole.com/ns/sitemap">' . "\n";
    foreach ($urls as $path => $mtime) {
        $t = $GLOBALS['ROC_SM_TITLES'][$path] ?? '';
        echo '  <url><loc>' . rocSmEsc(ROC_SM_BASE . $path) . '</loc><lastmod>' . gmdate('c', $mtime) . '</lastmod>'
           . ($t !== '' ? '<roc:title>' . rocSmEsc($t) . '</roc:title>' : '') . "</url>\n";
    }
    echo "</urlset>\n";
    exit;
}

if ($map === 'pages')      rocSmUrlset(rocSmBuilt($root, null));
if ($map === 'products')   rocSmUrlset(rocSmBuilt($root, 'products'));
if ($map === 'categories') rocSmUrlset(rocSmBuilt($root, 'categories'));

/* index */
$latest = function (array $urls): int { return $urls ? max($urls) : time(); };
// Once product-render.php / category-render.php serve these sitemaps (see .htaccess),
// their content changes in the CMS database, so the folder dates are no longer a guide.
$fromDb = function (string $renderer, string $dir) use ($root, $latest): int {
    return is_file($root . '/' . $renderer) ? time() : $latest(rocSmBuilt($root, $dir));
};
$children = [
    'pages'      => [$latest(rocSmBuilt($root, null)), 'Main pages'],
    'products'   => [$fromDb('product-render.php', 'products'), 'Products and product categories'],
    'categories' => [$fromDb('category-render.php', 'categories'), 'Gaming platforms'],
    'blogs'      => [time(), 'Blog posts'],
];
// CMS pages (About-style pages made in the CMS) are listed only once there is at least one.
try {
    ob_start();
    require_once $root . '/api/v1/config.php';
    ob_end_clean();
    $pdo = function_exists('getDBConnection') ? getDBConnection() : null;
    $n = $pdo ? (int)$pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'published' AND (is_noindex = 0 OR is_noindex IS NULL)")->fetchColumn() : 1;
    if ($n > 0) $children['cms-pages'] = [time(), 'CMS pages'];
} catch (\Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    $children['cms-pages'] = [time(), 'CMS pages'];
}
// config.php is shared with the API (session, no-cache headers): undo that for the sitemap.
if (!headers_sent()) {
    foreach (['Set-Cookie', 'Expires', 'Pragma', 'Cache-Control', 'Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials'] as $h) header_remove($h);
    header('Cache-Control: public, max-age=600');
}
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . ROC_SM_STYLE . "\n"
   . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:roc="https://runonconsole.com/ns/sitemap">' . "\n";
foreach ($children as $name => [$t, $label]) {
    echo '  <sitemap><loc>' . ROC_SM_BASE . '/sitemaps/' . $name . '-sitemap.xml</loc><lastmod>' . gmdate('c', $t) . '</lastmod>'
       . '<roc:title>' . rocSmEsc($label) . "</roc:title></sitemap>\n";
}
echo "</sitemapindex>\n";
