<?php
/**
 * Run On Console (ROC) - Dynamic XML Sitemap Engine
 * Serves /sitemap.xml and /sitemaps/{pages|blogs|products|categories}-sitemap.xml
 */

require_once __DIR__ . '/api/v1/config.php';

header("Content-Type: application/xml; charset=utf-8");

$baseUrl = defined('ROC_SITE_URL') ? rtrim(ROC_SITE_URL, '/') : 'https://www.runonconsole.com';
$map = $_GET['map'] ?? '';

$pdo = getDBConnection();

if (!$pdo) {
    echo '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>';
    exit();
}

// Helper to calculate genuine lastmod timestamp without refreshing on every request
function getGenuineLastmod(?string ...$dates): string {
    foreach ($dates as $d) {
        if (!empty($d) && $d !== '0000-00-00 00:00:00') {
            return date('c', strtotime($d));
        }
    }
    return '2026-09-17T00:00:00+00:00';
}

// 1. Child Sitemap: Pages
if (str_contains($map, 'pages')) {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    $staticPages = ['/', '/about/', '/contact/', '/compatibility/', '/write-for-us/', '/partnerships/', '/terms-and-conditions/', '/privacy-policy/'];
    foreach ($staticPages as $p) {
        $loc = htmlspecialchars($baseUrl . $p);
        $lastmod = '2026-09-17T00:00:00+00:00';
        echo "  <url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>\n";
    }

    // Dynamic Published & Indexable Block Pages from DB
    try {
        $stmtP = $pdo->query("SELECT slug, content_modified_at, updated_at, created_at FROM pages WHERE status = 'published' AND is_noindex = 0 ORDER BY id DESC");
        $pubPages = $stmtP->fetchAll();
        foreach ($pubPages as $p) {
            $loc = htmlspecialchars("{$baseUrl}/page/{$p['slug']}/");
            $date = getGenuineLastmod($p['content_modified_at'] ?? null, $p['updated_at'] ?? null, $p['created_at'] ?? null);
            echo "  <url><loc>{$loc}</loc><lastmod>{$date}</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>\n";
        }
    } catch (\Throwable $e) {}

    // Dynamic Published & Indexable Authors
    try {
        $stmtA = $pdo->query("SELECT slug, content_modified_at, updated_at, created_at FROM authors WHERE status = 'published' AND is_noindex = 0 ORDER BY updated_at DESC");
        $pubAuthors = $stmtA->fetchAll();
        foreach ($pubAuthors as $a) {
            $loc = htmlspecialchars("{$baseUrl}/author/{$a['slug']}/");
            $date = getGenuineLastmod($a['content_modified_at'] ?? null, $a['updated_at'] ?? null, $a['created_at'] ?? null);
            echo "  <url><loc>{$loc}</loc><lastmod>{$date}</lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>\n";
        }
    } catch (\Throwable $e) {}

    echo '</urlset>';
    exit();
}

// 2. Child Sitemap: Blogs
if (str_contains($map, 'blogs')) {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    $stmt = $pdo->query("SELECT slug, content_modified_at, published_at, updated_at, created_at FROM blogs WHERE status = 'published' AND is_noindex = 0 ORDER BY updated_at DESC");
    $blogs = $stmt->fetchAll();
    foreach ($blogs as $b) {
        $loc = htmlspecialchars("{$baseUrl}/blogs/{$b['slug']}/");
        $date = getGenuineLastmod($b['content_modified_at'] ?? null, $b['published_at'] ?? null, $b['updated_at'] ?? null, $b['created_at'] ?? null);
        echo "  <url><loc>{$loc}</loc><lastmod>{$date}</lastmod><changefreq>daily</changefreq><priority>0.7</priority></url>\n";
    }

    echo '</urlset>';
    exit();
}

// 3. Child Sitemap: Products
if (str_contains($map, 'products')) {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    $stmt = $pdo->query("SELECT slug, content_modified_at, updated_at, created_at FROM products WHERE status = 'published' AND is_noindex = 0 ORDER BY updated_at DESC");
    $products = $stmt->fetchAll();
    foreach ($products as $p) {
        $loc = htmlspecialchars("{$baseUrl}/products/{$p['slug']}/");
        $date = getGenuineLastmod($p['content_modified_at'] ?? null, $p['updated_at'] ?? null, $p['created_at'] ?? null);
        echo "  <url><loc>{$loc}</loc><lastmod>{$date}</lastmod><changefreq>daily</changefreq><priority>0.9</priority></url>\n";
    }

    echo '</urlset>';
    exit();
}

// 4. Child Sitemap: Categories
if (str_contains($map, 'categories')) {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    $stmt = $pdo->query("SELECT slug, content_modified_at, updated_at, created_at FROM categories WHERE status = 'published' AND is_noindex = 0 ORDER BY updated_at DESC");
    $categories = $stmt->fetchAll();
    foreach ($categories as $c) {
        $loc = htmlspecialchars("{$baseUrl}/categories/{$c['slug']}/");
        $date = getGenuineLastmod($c['content_modified_at'] ?? null, $c['updated_at'] ?? null, $c['created_at'] ?? null);
        echo "  <url><loc>{$loc}</loc><lastmod>{$date}</lastmod><changefreq>weekly</changefreq><priority>0.6</priority></url>\n";
    }

    echo '</urlset>';
    exit();
}

// Default: Sitemap Index
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$latestMod = '2026-09-17T00:00:00+00:00';
try {
    $maxStmt = $pdo->query("SELECT MAX(mod_date) FROM (
        SELECT MAX(COALESCE(content_modified_at, updated_at)) as mod_date FROM products WHERE status='published' AND is_noindex=0
        UNION ALL
        SELECT MAX(COALESCE(content_modified_at, published_at, updated_at)) as mod_date FROM blogs WHERE status='published' AND is_noindex=0
    ) as mods");
    $maxVal = $maxStmt->fetchColumn();
    if ($maxVal) {
        $latestMod = date('c', strtotime($maxVal));
    }
} catch (\Throwable $e) {}

echo "  <sitemap><loc>{$baseUrl}/sitemaps/pages-sitemap.xml</loc><lastmod>{$latestMod}</lastmod></sitemap>\n";
echo "  <sitemap><loc>{$baseUrl}/sitemaps/blogs-sitemap.xml</loc><lastmod>{$latestMod}</lastmod></sitemap>\n";
echo "  <sitemap><loc>{$baseUrl}/sitemaps/products-sitemap.xml</loc><lastmod>{$latestMod}</lastmod></sitemap>\n";
echo "  <sitemap><loc>{$baseUrl}/sitemaps/categories-sitemap.xml</loc><lastmod>{$latestMod}</lastmod></sitemap>\n";
echo '</sitemapindex>';
