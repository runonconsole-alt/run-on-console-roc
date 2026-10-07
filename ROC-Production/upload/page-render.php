<?php
/**
 * Run On Console — CMS pages at runonconsole.com/{slug}/
 *
 *   /{slug}/                        -> general, product and landing pages   (page-render.php?slug=...)
 *   /gaming-platforms/{cat}/{slug}/ -> category guides (old /categories/... redirects)                       (page-render.php?cat=...&slug=...)
 *   /sitemaps/cms-pages-sitemap.xml -> sitemap of CMS pages   (page-render.php?sitemap=1)
 *
 * .htaccess sends a one-segment URL here only when no real file or folder matched,
 * so existing pages can never be shadowed. Unknown slugs get the site's 404 page.
 * Layout, fonts and styles come from the site's own template (see blog-render.php).
 */

declare(strict_types=1);

define('ROC_RENDER_LIB_ONLY', true);
require __DIR__ . '/blog-render.php';          // helpers only: rocTemplate, rocH, rocJsonLd, …

$ROOT  = __DIR__;
$slug  = isset($_GET['slug']) ? strtolower(trim((string)$_GET['slug'], "/ \t\n\r")) : '';
$isMap = isset($_GET['sitemap']);
$reqCat = isset($_GET['cat']) ? strtolower(trim((string)$_GET['cat'])) : '';

if (!$isMap && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) rocNotFound($ROOT);
if ($reqCat !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $reqCat)) rocNotFound($ROOT);

function rocPagePathOf(array $p): string {
    if (($p['page_type'] ?? '') === 'category' && !empty($p['category_slug'])) {
        return '/gaming-platforms/' . $p['category_slug'] . '/' . $p['slug'] . '/';
    }
    return '/' . $p['slug'] . '/';
}

$pdo = null;
try {
    ob_start();
    require_once $ROOT . '/api/v1/config.php';
    ob_end_clean();
    require_once $ROOT . '/api/v1/cms/cms-html.php';
    $pdo = function_exists('getDBConnection') ? getDBConnection() : null;
} catch (\Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    error_log('page-render bootstrap: ' . $e->getMessage());
}
if (!headers_sent()) {
    header_remove('X-Robots-Tag');
    header_remove('Content-Type');
    header_remove('Access-Control-Allow-Origin');
    header_remove('Access-Control-Allow-Credentials');
}
if (!$pdo) {
    if ($isMap) { http_response_code(503); exit; }
    rocNotFound($ROOT);
}

/* ------------------------------------------------------------- sitemap */
if ($isMap) {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $out = rocSmOpen();
    try {
        // Pages set to "hide from Google" stay out of the sitemap.
        $rows = $pdo->query("SELECT * FROM pages WHERE status = 'published' AND (is_noindex = 0 OR is_noindex IS NULL) ORDER BY published_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { $rows = []; }
    foreach ($rows as $r) $out .= rocSmLine(ROC_PUBLIC_URL . rocPagePathOf($r), rocModified($r), (string)($r['title'] ?? ''));
    echo $out . "</urlset>\n";
    exit;
}

/* ---------------------------------------------------------------- page */
try {
    $st = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published' LIMIT 1");
    $st->execute([$slug]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
} catch (\Throwable $e) { $p = null; }
if (!$p) rocNotFound($ROOT);

// One URL per page: send visitors (and Google) to the canonical address.
$canonicalPath = rocPagePathOf($p);
$requested = $reqCat !== '' ? '/gaming-platforms/' . $reqCat . '/' . $slug . '/' : '/' . $slug . '/';
if ($requested !== $canonicalPath) {
    $keep = $_GET; unset($keep['slug'], $keep['cat']);   // keep e.g. utm_ parameters
    header('Location: ' . $canonicalPath . ($keep ? '?' . http_build_query($keep) : ''), true, 301);
    exit;
}
$type = $p['page_type'] ?? 'general';

// Category name for breadcrumbs.
$catName = null;
if (!empty($p['category_slug'])) {
    try {
        $c = $pdo->prepare('SELECT name FROM categories WHERE slug = ? LIMIT 1');
        $c->execute([$p['category_slug']]);
        $catName = $c->fetchColumn() ?: null;
    } catch (\Throwable $e) {}
}

// Products chosen for this page, in the chosen order.
$products = [];
$ids = json_decode((string)($p['product_ids'] ?? ''), true) ?: [];
if ($ids) {
    try {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $q = $pdo->prepare("SELECT * FROM products WHERE id IN ({$in})");
        $q->execute(array_map('strval', $ids));
        $byId = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $byId[(string)$row['id']] = $row;
        foreach ($ids as $pid) if (isset($byId[(string)$pid])) $products[] = $byId[(string)$pid];
    } catch (\Throwable $e) {}
}

// Other guides in the same category.
$siblings = [];
if ($type === 'category' && !empty($p['category_slug'])) {
    try {
        $q = $pdo->prepare("SELECT title, slug, page_type, category_slug FROM pages
                             WHERE status = 'published' AND page_type = 'category' AND category_slug = ? AND id <> ?
                             ORDER BY published_at DESC LIMIT 6");
        $q->execute([$p['category_slug'], $p['id']]);
        $siblings = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {}
}

[$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($ROOT, 'blog-post.html');

$url      = ROC_PUBLIC_URL . $canonicalPath;
$title    = trim((string)$p['title']);
$metaT    = trim((string)($p['meta_title'] ?? '')) ?: ($title . ' | Run On Console');
$excerpt  = trim((string)($p['excerpt'] ?? ''));
$body     = rocBodyHtml((string)($p['content'] ?? ''));
$metaD    = trim((string)($p['meta_description'] ?? '')) ?: rocCmsTruncate($excerpt !== '' ? $excerpt : rocCmsTextFromHtml($body), 155);
$image    = trim((string)($p['image'] ?? ''));
$imageAbs = rocAbs($image);
$alt      = trim((string)($p['image_alt'] ?? '')) ?: $title;
$faqs     = json_decode((string)($p['faq_json'] ?? ''), true) ?: [];
$mod      = rocModified($p);
$pub      = rocDate($p['published_at'] ?? null) ?? rocDate($p['created_at'] ?? null);

/* head */
$h  = '<title>' . rocH($metaT) . "</title>\n";
$h .= '    <meta name="title" content="' . rocH($metaT) . "\" />\n";
$h .= '    <meta name="description" content="' . rocH($metaD) . "\" />\n";
$h .= '    <meta name="robots" content="' . (!empty($p['is_noindex']) ? 'noindex, follow' : 'index, follow, max-image-preview:large') . "\" />\n";
$h .= '    <link rel="canonical" href="' . rocH($url) . "\" />\n";
$h .= "    <meta property=\"og:type\" content=\"website\" />\n";
$h .= '    <meta property="og:url" content="' . rocH($url) . "\" />\n";
$h .= '    <meta property="og:title" content="' . rocH($metaT) . "\" />\n";
$h .= '    <meta property="og:description" content="' . rocH($metaD) . "\" />\n";
if ($imageAbs) $h .= '    <meta property="og:image" content="' . rocH($imageAbs) . "\" />\n    <meta property=\"og:image:alt\" content=\"" . rocH($alt) . "\" />\n";
$h .= "    <meta property=\"og:site_name\" content=\"Run On Console\" />\n";
$h .= "    <meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
$h .= '    <meta name="twitter:title" content="' . rocH($metaT) . "\" />\n";
$h .= '    <meta name="twitter:description" content="' . rocH($metaD) . "\" />\n";
if ($imageAbs) $h .= '    <meta name="twitter:image" content="' . rocH($imageAbs) . "\" />\n";
$crumbs = [['Home', ROC_PUBLIC_URL . '/']];
if ($type === 'category' && $catName) $crumbs[] = [$catName, ROC_PUBLIC_URL . '/gaming-platforms/' . $p['category_slug'] . '/'];
$crumbs[] = [$title, $url];
$h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(function ($c, $i) {
    return ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]];
}, $crumbs, array_keys($crumbs))]);
if ($products) {
    $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $title,
        'itemListElement' => array_map(function ($pr, $i) {
            return ['@type' => 'ListItem', 'position' => $i + 1, 'url' => ROC_PUBLIC_URL . '/products/' . $pr['slug'] . '/', 'name' => $pr['title']];
        }, $products, array_keys($products))]);
}
$web = ['@context' => 'https://schema.org', '@type' => 'WebPage', '@id' => $url, 'url' => $url, 'name' => $title,
        'description' => $metaD, 'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/']];
if ($imageAbs) $web['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => $imageAbs];
if ($pub) $web['datePublished'] = gmdate('c', $pub);
if ($mod) $web['dateModified'] = gmdate('c', $mod);
$h .= rocJsonLd($web);
if ($faqs) {
    $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
        return ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => rocCmsTextFromHtml((string)$f['a'])]];
    }, $faqs)]);
}
$h .= rocArticleStyles();
$h .= "<style>.roc-faq details{border:1px solid #e2e8f0;border-radius:1rem;background:#fff;padding:.9rem 1.1rem}.roc-faq details+details{margin-top:.7rem}"
    . ".roc-faq summary{cursor:pointer;font-weight:800;font-family:Outfit,Inter,sans-serif;color:#0f172a}.roc-faq details[open]{border-color:#10b981;background:#f0fdf4}"
    . ".roc-faq .a{margin-top:.6rem;color:#334155;line-height:1.7}.roc-faq .a p+p{margin-top:.7em}.roc-faq .a a{color:#047857;text-decoration:underline}</style>\n";
$h .= "<style>.rp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:1rem}"
    . ".rp-card{border:1px solid #e2e8f0;border-radius:1.25rem;background:#fff;overflow:hidden;display:flex;flex-direction:column}"
    . ".rp-card img{width:100%;height:170px;object-fit:cover;background:#0f172a}.rp-body{padding:1rem;display:flex;flex-direction:column;gap:.4rem;flex:1}"
    . ".rp-title{font-family:Outfit,Inter,sans-serif;font-weight:800;color:#0f172a;line-height:1.3}.rp-title:hover{color:#059669}"
    . ".rp-price{font-weight:800;color:#059669}.rp-desc{font-size:.85rem;color:#64748b;line-height:1.5}"
    . ".rp-actions{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:auto;padding-top:.4rem}"
    . ".rp-more,.rp-buy{font-size:.75rem;font-weight:700;padding:.45rem .75rem;border-radius:.75rem}"
    . ".rp-more{border:1px solid #cbd5e1;color:#334155}.rp-buy{background:#f59e0b;color:#0f172a}.rp-buy:hover{background:#d97706}"
    . ".rp-note{font-size:.72rem;color:#94a3b8;margin-top:.8rem}.rp-links{display:flex;flex-direction:column;gap:.4rem}"
    . ".rp-links a{color:#047857;font-weight:600;text-decoration:underline}</style>\n";
$h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

/* body */
$m  = $mainOpen . '<article class="max-w-4xl mx-auto py-6 space-y-8">';
if (count($crumbs) > 2) {
    $m .= '<nav aria-label="Breadcrumb" class="text-xs text-slate-500 font-medium">'
        . implode(' <span aria-hidden="true">›</span> ', array_map(function ($c, $i) use ($crumbs) {
            $path = substr($c[1], strlen(ROC_PUBLIC_URL));
            return $i === count($crumbs) - 1 ? '<span class="text-slate-700">' . rocH($c[0]) . '</span>'
                 : '<a href="' . rocH($path) . '" class="hover:text-emerald-600">' . rocH($c[0]) . '</a>';
        }, $crumbs, array_keys($crumbs))) . '</nav>';
}
$m .= '<header class="space-y-4">'
    . '<h1 class="font-display font-extrabold text-3xl sm:text-5xl text-slate-900 leading-tight">' . rocH($title) . '</h1>'
    . ($excerpt !== '' ? '<p class="text-base sm:text-lg text-emerald-700 font-semibold">' . rocH($excerpt) . '</p>' : '')
    . '</header>';
if ($image !== '') {
    $m .= '<div class="relative rounded-3xl overflow-hidden shadow-2xl max-h-[480px] bg-slate-900">'
        . '<img src="' . rocH($image) . '" alt="' . rocH($alt) . '" class="w-full h-full object-cover max-h-[480px]" fetchpriority="high"/></div>';
}
$m .= '<div class="roc-article">' . $body . '</div>';

if ($products) {
    $m .= '<section class="roc-products border-t border-slate-200 pt-8"><h2 class="font-display font-extrabold text-2xl text-slate-900 mb-5">'
        . ($type === 'product' ? 'Products in this guide' : 'Recommended gear') . '</h2><div class="rp-grid">';
    foreach ($products as $pr) {
        $buy = '';
        foreach ([['affiliate_amazon', 'Amazon'], ['affiliate_bestbuy', 'Best Buy'], ['affiliate_official', 'Official store']] as [$col, $label]) {
            $link = trim((string)($pr[$col] ?? ''));
            if ($link !== '' && preg_match('#^https?://#i', $link)) {
                $buy .= '<a class="rp-buy" href="' . rocH($link) . '" target="_blank" rel="sponsored nofollow noopener">Buy on ' . rocH($label) . '</a>';
            }
        }
        $price = (($pr['price_state'] ?? 'set') !== 'unknown' && $pr['price'] !== null && $pr['price'] !== '') ? '$' . number_format((float)$pr['price'], 2) : '';
        $m .= '<div class="rp-card">'
            . (!empty($pr['image']) ? '<a href="/products/' . rocH($pr['slug']) . '/"><img src="' . rocH($pr['image']) . '" alt="' . rocH($pr['image_alt'] ?? $pr['title']) . '" loading="lazy"></a>' : '')
            . '<div class="rp-body"><a class="rp-title" href="/products/' . rocH($pr['slug']) . '/">' . rocH($pr['title']) . '</a>'
            . ($price ? '<div class="rp-price">' . $price . '</div>' : '')
            . (!empty($pr['short_desc']) ? '<p class="rp-desc">' . rocH(rocCmsTruncate(strip_tags((string)$pr['short_desc']), 140)) . '</p>' : '')
            . '<div class="rp-actions"><a class="rp-more" href="/products/' . rocH($pr['slug']) . '/">Full review</a>' . $buy . '</div></div></div>';
    }
    $m .= '</div><p class="rp-note">As an Amazon Associate we earn from qualifying purchases.</p></section>';
}
if ($faqs) {
    $m .= '<section class="roc-faq border-t border-slate-200 pt-8"><h2 class="font-display font-extrabold text-2xl text-slate-900 mb-5">Frequently asked questions</h2>';
    foreach ($faqs as $i => $f) {
        $m .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . rocH($f['q']) . '</summary><div class="a">' . rocCmsSanitizeHtml((string)$f['a']) . '</div></details>';
    }
    $m .= '</section>';
}
if ($siblings || ($type === 'category' && $catName)) {
    $m .= '<section class="border-t border-slate-200 pt-8"><h2 class="font-display font-extrabold text-xl text-slate-900 mb-4">More in ' . rocH((string)$catName) . '</h2><ul class="rp-links">';
    foreach ($siblings as $sb) $m .= '<li><a href="' . rocH(rocPagePathOf($sb)) . '">' . rocH($sb['title']) . '</a></li>';
    $m .= '<li><a href="/gaming-platforms/' . rocH($p['category_slug']) . '/">All ' . rocH((string)$catName) . ' &rarr;</a></li></ul></section>';
}
$m .= '</article>';

rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $mod);
