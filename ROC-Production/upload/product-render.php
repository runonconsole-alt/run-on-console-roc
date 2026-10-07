<?php
/**
 * Run On Console — public product pages rendered from the CMS database.
 *
 *   /products/                       -> all products        (product-render.php)
 *   /products/category/{slug}/       -> one category        (product-render.php?cat=...)
 *   /products/{slug}/                -> one product         (product-render.php?slug=...)
 *   /sitemaps/products-sitemap.xml   -> products sitemap    (product-render.php?sitemap=1)
 *
 * Same approach as blog-render.php: the site's header, footer, fonts and CSS come
 * from prerendered pages saved in /cms-templates/ by cli-product-routing.php, and
 * the React bundle is not loaded, because it only knows the products it was built
 * with. Only published products and categories are shown; is_noindex rows are
 * shown but kept out of the sitemap and marked noindex.
 */

declare(strict_types=1);

$ROOT = __DIR__;

// Shared helpers (rocH, rocAbs, rocDate, rocTemplate, rocSendHtml, rocNotFound, rocJsonLd).
define('ROC_RENDER_LIB_ONLY', true);
require_once $ROOT . '/blog-render.php';

/** Photos the product sheet gave every product of a category (not real product photos). */
const ROC_SHARED_PRODUCT_PHOTOS = ['/images/cyber_keyboard.jpg', '/images/tactical_headset.jpg', '/images/apex_mouse.jpg',
                                   '/images/streaming_vr_gear.jpg', '/images/gaming_monitor.jpg', '/images/battlestation_pc.jpg'];

const ROC_PRODUCT_TEMPLATES = [
    'page'     => 'product-page.html',
    'list'     => 'product-list.html',
    'category' => 'product-category.html',
];

// Icons (same SVGs as the React pages). Defined before routing: top-level
// constants only exist once their line has run.
const ROC_ICON_CART = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shopping-cart w-4 h-4"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>';
const ROC_ICON_EXT  = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-external-link w-4 h-4 opacity-70"><path d="M15 3h6v6"></path><path d="M10 14 21 3"></path><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path></svg>';
const ROC_ICON_BACK = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left w-4 h-4"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>';
const ROC_ICON_NEXT = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right w-3.5 h-3.5"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>';

/* Product groups shown in the header's Products menu, at /products/{group}/.
   Categories not listed under a group belong to gaming-hardware. */
const ROC_PRODUCT_GROUPS = [
    'pc-hardware' => ['name' => 'PC Hardware', 'members' => ['gpu'],
        'description' => 'Graphics cards and PC components we recommend, each with what it is best for, key specs and a direct Amazon link.'],
    'gaming-hardware' => ['name' => 'Gaming Hardware', 'members' => null,
        'description' => 'Gaming monitors, mice, keyboards, headsets and speakers we recommend, each with what it is best for, key specs and a direct Amazon link.'],
];

/* ------------------------------------------------------------ request */

$slug  = isset($_GET['slug']) ? strtolower(trim((string)$_GET['slug'], "/ \t\n\r")) : '';
$cat   = isset($_GET['cat']) ? strtolower(trim((string)$_GET['cat'], "/ \t\n\r")) : '';
$isMap = isset($_GET['sitemap']);
$query = isset($_GET['q']) ? trim(mb_substr((string)$_GET['q'], 0, 80)) : '';

foreach ([$slug, $cat] as $s) {
    if ($s !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s)) rocNotFound($ROOT);
}

/* ----------------------------------------------------------- database */

$pdo = null;
try {
    ob_start();
    require_once $ROOT . '/api/v1/config.php';
    ob_end_clean();
    $pdo = function_exists('getDBConnection') ? getDBConnection() : null;
} catch (\Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    error_log('product-render bootstrap: ' . $e->getMessage());
    $pdo = null;
}

// config.php is shared with the JSON API; undo anything it set for that.
if (!headers_sent()) {
    header_remove('X-Robots-Tag');
    header_remove('Content-Type');
    header_remove('Access-Control-Allow-Origin');
    header_remove('Access-Control-Allow-Credentials');
}

if (!$pdo) {
    // Database down: serve the old static page if there is one rather than an error.
    $static = $ROOT . '/products/' . ($cat !== '' ? 'category/' . $cat . '/' : ($slug !== '' ? $slug . '/' : '')) . 'index.html';
    if (!$isMap && is_file($static)) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache');
        readfile($static);
        exit;
    }
    http_response_code(503);
    header('Retry-After: 120');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Temporarily unavailable. Please try again in a moment.';
    exit;
}

/* ------------------------------------------------------------ routing */

$categories = rocProductCategories($pdo);

if ($isMap) rocProductSitemap($pdo, $categories);

if (isset($_GET['json']) && $slug === '' && $cat === '') rocProductsJson($pdo, $categories);

if ($slug !== '' && isset(ROC_PRODUCT_GROUPS[$slug])) {
    rocRenderProductList($ROOT, $pdo, $categories, rocProductGroup($slug, $categories), $query);
}

if ($slug !== '') {
    $product = rocFetchProduct($pdo, $slug);
    if (!$product) rocOldProductRedirect($ROOT, $slug);
    rocRenderProduct($ROOT, $pdo, $product, $categories);
}

if ($cat !== '') {
    if (!isset($categories[$cat])) rocNotFound($ROOT);
    rocRenderProductList($ROOT, $pdo, $categories, $categories[$cat], $query);
}

rocRenderProductList($ROOT, $pdo, $categories, null, $query);


/* ==================================================================== */
/*                               data                                   */
/* ==================================================================== */

/**
 * Published categories keyed by slug, in display order.
 * Uses product_categories when cli-product-routing.php has created it; before
 * that, derives categories from the products table so pages still work.
 */
function rocProductCategories(PDO $pdo): array {
    $out = [];
    try {
        $rows = $pdo->query("SELECT * FROM product_categories WHERE status = 'published' ORDER BY sort_rank, name")
                    ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) $out[(string)$r['slug']] = $r;
        if ($out) return $out;
    } catch (\Throwable $e) { /* table not created yet */ }

    $rows = $pdo->query("SELECT * FROM products WHERE status = 'published'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $name = trim((string)($r['category'] ?? ''));
        $s = rocProductCategorySlug($r);
        if ($s === '' || isset($out[$s])) continue;
        $out[$s] = ['slug' => $s, 'name' => $name, 'description' => '', 'image' => (string)($r['image'] ?? ''),
                    'sort_rank' => 0, 'meta_title' => '', 'meta_description' => '', 'is_noindex' => 0];
    }
    return $out;
}

function rocProductCategorySlug(array $p): string {
    $s = trim((string)($p['category_slug'] ?? ''));
    if ($s !== '') return $s;
    $s = strtolower(trim((string)($p['category'] ?? '')));
    return trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-');
}

/** Slugs of categories hidden in the CMS: their products are hidden with them. */
function rocHiddenCategories(PDO $pdo): array {
    static $hidden = null;
    if ($hidden !== null) return $hidden;
    $hidden = [];
    try {
        foreach ($pdo->query("SELECT slug FROM product_categories WHERE status <> 'published'")->fetchAll(PDO::FETCH_COLUMN) as $s) {
            $hidden[(string)$s] = true;
        }
    } catch (\Throwable $e) { /* table not created yet */ }
    return $hidden;
}

function rocFetchProduct(PDO $pdo, string $slug): ?array {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || isset(rocHiddenCategories($pdo)[rocProductCategorySlug($row)])) return null;
    return $row;
}

/** Published products, optionally one category, in the CMS display order. */
function rocFetchProducts(PDO $pdo, ?array $category, string $query = ''): array {
    $rows = $pdo->query("SELECT * FROM products WHERE status = 'published'")->fetchAll(PDO::FETCH_ASSOC);
    $q = mb_strtolower($query);
    $hidden = rocHiddenCategories($pdo);
    $rows = array_values(array_filter($rows, function ($p) use ($category, $q, $hidden) {
        if (isset($hidden[rocProductCategorySlug($p)])) return false;
        if ($category && isset($category['members'])) {
            if (!in_array(rocProductCategorySlug($p), $category['members'], true)) return false;
        } elseif ($category && rocProductCategorySlug($p) !== (string)$category['slug']
            && strcasecmp(trim((string)($p['category'] ?? '')), trim((string)$category['name'])) !== 0) return false;
        if ($q === '') return true;
        $hay = mb_strtolower(($p['title'] ?? '') . ' ' . ($p['brand'] ?? '') . ' ' . ($p['category'] ?? '') . ' ' . ($p['subtitle'] ?? ''));
        return rocFuzzyMatch($hay, $q);
    }));
    usort($rows, function ($a, $b) {
        $ra = (int)($a['sort_rank'] ?? 0) ?: PHP_INT_MAX;
        $rb = (int)($b['sort_rank'] ?? 0) ?: PHP_INT_MAX;
        return [rocProductCategorySlug($a), $ra, (string)$a['title']] <=> [rocProductCategorySlug($b), $rb, (string)$b['title']];
    });
    return $rows;
}

/**
 * Search that forgives small typos: every word of the query must appear in the text,
 * either as part of it or as a word one letter off (two for long words).
 */
function rocFuzzyMatch(string $hay, string $q): bool {
    $norm = function (string $t): string { return trim((string)preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($t))); };
    $hay = $norm($hay);
    $words = array_filter(explode(' ', $hay));
    foreach (array_filter(explode(' ', $norm($q))) as $t) {
        if (mb_strpos($hay, $t) !== false) continue;
        $len = mb_strlen($t);
        if ($len < 4) return false;
        $max = $len >= 7 ? 2 : 1;
        $hit = false;
        foreach ($words as $w) {
            // Whole word, or its start ("superlite" ~ "superligh|t").
            if (levenshtein($t, $w) <= $max || levenshtein($t, mb_substr($w, 0, $len)) <= $max) { $hit = true; break; }
        }
        if (!$hit) return false;
    }
    return true;
}

/** A Products-menu group as a category-like array; 'members' lists its category slugs. */
function rocProductGroup(string $slug, array $categories): array {
    $g = ROC_PRODUCT_GROUPS[$slug];
    $pcMembers = ROC_PRODUCT_GROUPS['pc-hardware']['members'];
    $members = $g['members'] ?? array_values(array_filter(array_keys($categories), function ($c) use ($pcMembers) { return !in_array($c, $pcMembers, true); }));
    $first = $categories[$members[0] ?? ''] ?? null;
    return ['slug' => $slug, 'name' => $g['name'], 'description' => $g['description'], 'members' => $members,
            'image' => $first ? ($first['image'] ?? '') : '', 'group' => true];
}

/** Published products as JSON for the header search box (/products/?json=1). */
function rocProductsJson(PDO $pdo, array $categories): void {
    $out = [];
    foreach (rocFetchProducts($pdo, null) as $p) {
        $c = $categories[rocProductCategorySlug($p)] ?? null;
        $out[] = ['t' => (string)$p['title'], 'b' => (string)($p['brand'] ?? ''), 'c' => $c ? (string)$c['name'] : (string)($p['category'] ?? ''),
                  'u' => '/products/' . $p['slug'] . '/'];
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Amazon Associates tag saved in CMS -> Social & Amazon tag ('' when unset). */
function rocAmazonTag(PDO $pdo): string {
    static $tag = null;
    if ($tag !== null) return $tag;
    $tag = '';
    try {
        $st = $pdo->prepare("SELECT setting_value FROM cms_settings WHERE setting_key = 'amazon_tag' LIMIT 1");
        $st->execute();
        $v = trim((string)$st->fetchColumn());
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,62}-\d{2}$/', $v)) $tag = $v;
    } catch (\Throwable $e) {}
    return $tag;
}

/**
 * Amazon link with the store tag. roc-nav.js adds the tag in the browser too;
 * doing it here as well keeps the tag for visitors without JavaScript.
 */
function rocAmazonUrl(PDO $pdo, array $p): string {
    $url = trim((string)($p['affiliate_amazon'] ?? ''));
    if ($url === '') $url = 'https://www.amazon.com/s?k=' . rawurlencode((string)$p['title']);
    $tag = rocAmazonTag($pdo);
    $host = (string)parse_url($url, PHP_URL_HOST);
    if ($tag === '' || !preg_match('/(^|\.)amazon\.[a-z.]+$/i', $host)) return $url;
    $url = (string)preg_replace('/([?&])tag=[^&#]*&?/i', '$1', $url);
    $url = rtrim($url, '?&');
    return $url . (strpos($url, '?') === false ? '?' : '&') . 'tag=' . rawurlencode($tag);
}

function rocProductList(array $p, string $key): array {
    $v = $p[$key] ?? null;
    if (is_string($v) && $v !== '') { $d = json_decode($v, true); if (is_array($d)) return $d; }
    return is_array($v) ? $v : [];
}

function rocProductUrl(string $slug): string { return ROC_PUBLIC_URL . '/products/' . $slug . '/'; }
function rocCategoryUrl(string $slug): string { return ROC_PUBLIC_URL . '/products/category/' . $slug . '/'; }

/**
 * Image shown for a product: its own photo when one is set in the CMS; otherwise
 * its own name card (/images/products/{slug}.webp; older sites had .svg); otherwise the category image.
 */
function rocProductImage(array $p, array $categories): string {
    $img = trim((string)($p['image'] ?? ''));
    $c = $categories[rocProductCategorySlug($p)] ?? null;
    $catImg = $c ? trim((string)($c['image'] ?? '')) : '';
    if ($img !== '' && !in_array($img, ROC_SHARED_PRODUCT_PHOTOS, true) && $img !== $catImg) return $img;
    foreach (['.webp', '.svg'] as $ext) {
        $card = '/images/products/' . $p['slug'] . $ext;
        if (is_file(__DIR__ . $card)) return $card;
    }
    return $img !== '' ? $img : $catImg;
}

/** Social networks do not show SVG: use the category photo for link previews. */
function rocShareImage(string $image, array $p, array $categories): string {
    if (substr($image, -4) !== '.svg') return $image;
    $c = $categories[rocProductCategorySlug($p)] ?? null;
    return $c ? trim((string)($c['image'] ?? '')) : '';
}

/** Products whose old static folder only holds a redirect stub (Phase 4 renames). */
function rocOldProductRedirect(string $root, string $slug): void {
    $stub = $root . '/products/' . $slug . '/index.php';
    if (is_file($stub)) { require $stub; exit; }
    rocNotFound($root);
}

/* ==================================================================== */
/*                            shared markup                             */
/* ==================================================================== */

function rocProductCard(PDO $pdo, array $p, array $categories): string {
    $slug  = (string)$p['slug'];
    $title = (string)$p['title'];
    $img   = rocProductImage($p, $categories);
    $alt   = trim((string)($p['image_alt'] ?? '')) ?: $title;
    $badge = trim((string)($p['badge'] ?? ''));
    $brand = trim((string)($p['brand'] ?? ''));
    $catN  = (string)(($categories[rocProductCategorySlug($p)]['name'] ?? null) ?: ($p['category'] ?? ''));
    $sub   = trim((string)($p['subtitle'] ?? '')) ?: trim((string)($p['short_desc'] ?? ''));
    return '<div class="transition-shadow duration-300 will-change-transform game-card flex flex-col justify-between group p-5 space-y-4"><div class="space-y-3">'
        . '<a href="/products/' . rocH($slug) . '/" class="relative block h-44 rounded-2xl overflow-hidden bg-slate-950 border border-slate-800 shadow-sm">'
        . ($img !== '' ? '<img src="' . rocH($img) . '" alt="' . rocH($alt) . '" width="400" height="300" loading="lazy" decoding="async" class="w-full h-full object-cover opacity-90 group-hover:scale-105 transition-transform duration-500"/>' : '')
        . ($badge !== '' ? '<span class="absolute top-2.5 left-2.5 max-w-[80%] truncate bg-emerald-600 text-white text-[9px] font-extrabold px-2.5 py-0.5 rounded-full uppercase shadow-sm">' . rocH($badge) . '</span>' : '')
        . ($brand !== '' ? '<span class="absolute bottom-2.5 left-2.5 bg-slate-900/90 text-white text-[10px] font-extrabold px-2 py-0.5 rounded backdrop-blur-xs">' . rocH($brand) . '</span>' : '')
        . '</a><div>'
        . '<span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block">' . rocH($catN) . '</span>'
        . '<h3 class="font-display font-extrabold text-sm sm:text-base text-slate-900 leading-snug"><a href="/products/' . rocH($slug) . '/" class="text-slate-900 group-hover:text-emerald-600 transition-colors no-underline">' . rocH($title) . '</a></h3>'
        . ($sub !== '' ? '<p class="text-[11px] text-slate-500 line-clamp-2 mt-1">' . rocH($sub) . '</p>' : '')
        . '</div></div>'
        . '<div class="pt-3 border-t border-slate-100 space-y-2">'
        . '<a href="' . rocH(rocAmazonUrl($pdo, $p)) . '" target="_blank" rel="sponsored nofollow noopener noreferrer" aria-label="Check price of ' . rocH($title) . ' on Amazon" class="bg-emerald-600 hover:bg-emerald-700 text-white font-display font-extrabold text-xs py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20 transition-all hover:scale-102 no-underline">'
        . ROC_ICON_CART . '<span>Check price on Amazon</span>' . ROC_ICON_EXT . '</a>'
        . '</div></div>';
}

function rocCategoryNav(array $categories, ?string $current, int $total, array $counts): string {
    $pill = function (string $href, string $label, int $n, bool $on): string {
        return '<a href="' . rocH($href) . '"' . ($on ? ' aria-current="page"' : '')
            . ' class="text-xs font-bold px-4 py-2 rounded-full border transition-colors no-underline '
            . ($on ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-300 hover:border-emerald-600') . '">'
            . rocH($label) . ' <span class="' . ($on ? 'text-emerald-100' : 'text-slate-400') . '">(' . $n . ')</span></a>';
    };
    $h = '<nav aria-label="Product categories" class="flex flex-wrap gap-2">' . $pill('/products/', 'All products', $total, $current === null);
    foreach ($categories as $s => $c) {
        if (empty($counts[$s])) continue;
        $h .= $pill('/products/category/' . $s . '/', (string)$c['name'], $counts[$s], $current === $s);
    }
    return $h . '</nav>';
}

function rocBreadcrumbLd(array $items): string {
    $list = [];
    foreach ($items as $i => [$name, $url]) {
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
    }
    return rocJsonLd(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list]);
}

function rocHeadMeta(string $title, string $desc, string $url, string $image, string $robots, string $ogType = 'website'): string {
    $h  = '<title>' . rocH($title) . "</title>\n";
    $h .= '    <meta name="title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta name="description" content="' . rocH($desc) . "\" />\n";
    $h .= '    <meta name="robots" content="' . rocH($robots) . "\" />\n";
    $h .= '    <link rel="canonical" href="' . rocH($url) . "\" />\n";
    $h .= '    <meta property="og:type" content="' . rocH($ogType) . "\" />\n";
    $h .= '    <meta property="og:url" content="' . rocH($url) . "\" />\n";
    $h .= '    <meta property="og:title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta property="og:description" content="' . rocH($desc) . "\" />\n";
    if ($image !== '') $h .= '    <meta property="og:image" content="' . rocH(rocAbs($image)) . "\" />\n";
    $h .= "    <meta property=\"og:site_name\" content=\"Run On Console\" />\n";
    $h .= "    <meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
    $h .= '    <meta name="twitter:title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta name="twitter:description" content="' . rocH($desc) . "\" />\n";
    if ($image !== '') $h .= '    <meta name="twitter:image" content="' . rocH(rocAbs($image)) . "\" />\n";
    return $h;
}

function rocProductModified(array $p): ?int {
    return rocDate($p['content_modified_at'] ?? null) ?? rocDate($p['updated_at'] ?? null) ?? rocDate($p['created_at'] ?? null);
}

/* ==================================================================== */
/*                            single product                            */
/* ==================================================================== */

function rocRenderProduct(string $root, PDO $pdo, array $p, array $categories): void {
    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, ROC_PRODUCT_TEMPLATES['page']);

    $slug    = (string)$p['slug'];
    $url     = rocProductUrl($slug);
    $title   = trim((string)$p['title']);
    $catSlug = rocProductCategorySlug($p);
    $cat     = $categories[$catSlug] ?? ['slug' => $catSlug, 'name' => (string)($p['category'] ?? '')];
    $catName = (string)$cat['name'];
    $brand   = trim((string)($p['brand'] ?? ''));
    $badge   = trim((string)($p['badge'] ?? ''));
    $bestFor = trim((string)($p['best_for'] ?? ''));
    $desc    = trim((string)($p['short_desc'] ?? '')) ?: trim((string)($p['summary'] ?? ''));
    $image   = rocProductImage($p, $categories);
    $alt     = trim((string)($p['image_alt'] ?? '')) ?: $title;
    $specs   = rocProductList($p, 'specs_json');
    $feats   = rocProductList($p, 'features_json');
    $amazon  = rocAmazonUrl($pdo, $p);
    $mod     = rocProductModified($p);
    $noindex = !empty($p['is_noindex']);

    $metaT = trim((string)($p['meta_title'] ?? '')) ?: ($title . ': Specs & Where to Buy | Run On Console');
    $metaD = trim((string)($p['meta_description'] ?? '')) ?: rocCmsTruncatePlain($desc, 155);

    /* ---------------- head */
    $h  = rocHeadMeta($metaT, $metaD, $url, rocShareImage($image, $p, $categories), $noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large', 'product');
    $h .= rocBreadcrumbLd([
        ['Home', ROC_PUBLIC_URL . '/'],
        ['Products', ROC_PUBLIC_URL . '/products/'],
        [$catName, rocCategoryUrl($catSlug)],
        [$title, $url],
    ]);
    $ld = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $title, 'url' => $url, 'description' => $metaD, 'category' => $catName];
    if ($brand !== '') $ld['brand'] = ['@type' => 'Brand', 'name' => $brand];
    if ($image !== '') $ld['image'] = [rocAbs($image)];
    $h .= rocJsonLd($ld);
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    /* ---------------- body */
    $m  = $mainOpen . '<div class="max-w-6xl mx-auto py-6 space-y-8 animate-page-in">';
    $m .= '<nav aria-label="Breadcrumb" class="text-xs font-semibold text-slate-500 flex flex-wrap items-center gap-1.5">'
        . '<a href="/products/" class="hover:text-emerald-600 no-underline text-slate-500">Products</a><span>/</span>'
        . '<a href="/products/category/' . rocH($catSlug) . '/" class="hover:text-emerald-600 no-underline text-slate-500">' . rocH($catName) . '</a><span>/</span>'
        . '<span class="text-slate-800">' . rocH($title) . '</span></nav>';
    $m .= '<div class="flex items-center justify-between gap-3">'
        . '<a href="/products/category/' . rocH($catSlug) . '/" class="bg-white border border-slate-300 hover:border-emerald-600 text-slate-700 font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-2 transition-colors shadow-sm no-underline">'
        . ROC_ICON_BACK . '<span>All ' . rocH($catName) . '</span></a></div>';

    $m .= '<div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm grid grid-cols-1 lg:grid-cols-12 gap-8">'
        . '<div class="lg:col-span-5 flex flex-col justify-center"><div class="transition-shadow duration-300 will-change-transform relative rounded-2xl overflow-hidden bg-slate-950 p-4 border border-slate-800 shadow-xl group">'
        . ($image !== '' ? '<img src="' . rocH($image) . '" alt="' . rocH($alt) . '" class="w-full h-80 object-cover rounded-xl group-hover:scale-105 transition-transform duration-500" fetchpriority="high"/>' : '')
        . ($badge !== '' ? '<span class="absolute top-4 left-4 max-w-[85%] bg-emerald-600 text-white font-extrabold text-[10px] px-3 py-1 rounded-full uppercase tracking-wider badge-glow">' . rocH($badge) . '</span>' : '')
        . '</div></div>'
        . '<div class="lg:col-span-7 space-y-5"><div>'
        . '<span class="text-xs font-bold text-emerald-600 uppercase tracking-wider">' . rocH($catName) . '</span>'
        . '<h1 class="font-display font-extrabold text-2xl sm:text-3xl text-slate-900 leading-tight mt-1">' . rocH($title) . '</h1>'
        . ($brand !== '' ? '<p class="text-xs sm:text-sm text-slate-500 font-medium mt-1">by <strong class="text-slate-700">' . rocH($brand) . '</strong></p>' : '')
        . '</div>'
        . ($bestFor !== '' ? '<div class="flex flex-wrap items-center gap-3 border-y border-slate-100 py-3"><span class="bg-emerald-50 border border-emerald-200 text-emerald-800 font-extrabold text-xs px-2.5 py-1 rounded-lg flex items-center gap-1.5">' . rocH($bestFor) . '</span></div>' : '')
        . ($desc !== '' ? '<p class="text-xs sm:text-sm text-slate-600 leading-relaxed">' . rocH($desc) . '</p>' : '');
    if ($feats) {
        $m .= '<ul class="flex flex-wrap gap-2">';
        foreach ($feats as $f) $m .= '<li class="text-[11px] font-bold text-slate-700 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-lg">' . rocH($f) . '</li>';
        $m .= '</ul>';
    }
    $m .= '<div class="space-y-2 pt-2">'
        . '<a href="' . rocH($amazon) . '" target="_blank" rel="sponsored nofollow noopener noreferrer" aria-label="Check price of ' . rocH($title) . ' on Amazon" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-display font-extrabold text-sm py-3.5 px-6 rounded-2xl flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/30 transition-all hover:scale-102 no-underline">'
        . ROC_ICON_CART . '<span>Check price &amp; stock on Amazon</span>' . ROC_ICON_EXT . '</a>'
        . '<p class="text-[11px] text-slate-400">Prices and stock change often; Amazon shows the current price. We may earn a commission from qualifying purchases.</p>'
        . '</div></div></div>';

    if ($specs) {
        $m .= '<div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">'
            . '<h2 class="font-display font-extrabold text-base text-slate-900 uppercase tracking-wider flex items-center gap-2"><span>Key specifications</span></h2>'
            . '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">';
        foreach ($specs as $k => $v) {
            if (is_array($v)) $v = implode(', ', $v);
            $m .= '<div class="flex justify-between gap-4 p-3 bg-slate-50 rounded-xl border border-slate-100">'
                . '<span class="font-bold text-slate-500 shrink-0">' . rocH($k) . ':</span>'
                . '<span class="font-semibold text-slate-900 text-right">' . rocH($v) . '</span></div>';
        }
        $m .= '</div></div>';
    }

    $related = array_values(array_filter(rocFetchProducts($pdo, $cat), function ($r) use ($slug) { return $r['slug'] !== $slug; }));
    if ($related) {
        $m .= '<div class="space-y-4"><div class="flex items-center justify-between gap-3">'
            . '<h2 class="font-display font-extrabold text-base text-slate-900 uppercase tracking-wider">More ' . rocH($catName) . '</h2>'
            . '<a href="/products/category/' . rocH($catSlug) . '/" class="text-xs font-bold text-emerald-700 hover:text-emerald-900 flex items-center gap-1 no-underline">See all ' . (count($related) + 1) . ' ' . ROC_ICON_NEXT . '</a></div>'
            . '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">';
        foreach (array_slice($related, 0, 3) as $r) $m .= rocProductCard($pdo, $r, $categories);
        $m .= '</div></div>';
    }
    $m .= '</div>';

    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $mod);
}

/* ==================================================================== */
/*                       all products / one category                    */
/* ==================================================================== */

function rocRenderProductList(string $root, PDO $pdo, array $categories, ?array $cat, string $query): void {
    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, ROC_PRODUCT_TEMPLATES[$cat ? 'category' : 'list']);

    $all = rocFetchProducts($pdo, null);
    $counts = [];
    foreach ($all as $p) { $s = rocProductCategorySlug($p); $counts[$s] = ($counts[$s] ?? 0) + 1; }
    $inScope = $cat ? rocFetchProducts($pdo, $cat) : $all;
    $shown   = $query !== '' ? rocFetchProducts($pdo, $cat, $query) : $inScope;

    if ($cat) {
        $name  = (string)$cat['name'];
        $url   = !empty($cat['group']) ? ROC_PUBLIC_URL . '/products/' . $cat['slug'] . '/' : rocCategoryUrl((string)$cat['slug']);
        $n     = count($inScope);
        $title = trim((string)($cat['meta_title'] ?? '')) ?: ('Best ' . $name . ': ' . $n . ' Picks | Run On Console');
        $desc  = trim((string)($cat['meta_description'] ?? ''))
              ?: trim((trim((string)($cat['description'] ?? '')) ?: ('Our ' . $name . ' picks.')) . ' ' . $n . ' picks with what each is best for, key specs and a direct Amazon link.');
        $heroTitle = 'Best ' . $name;
        $heroText  = trim((string)($cat['description'] ?? '')) ?: $desc;
        $heroBadge = count($inScope) . ' PICKS';
        $image = trim((string)($cat['image'] ?? ''));
        $crumbs = [['Home', ROC_PUBLIC_URL . '/'], ['Products', ROC_PUBLIC_URL . '/products/'], [$name, $url]];
        $noindex = !empty($cat['is_noindex']);
    } else {
        $url   = ROC_PUBLIC_URL . '/products/';
        [$title, $desc] = rocTemplateMeta($root, ROC_PRODUCT_TEMPLATES['list'], count($all),
            'Gaming Gear Picks by Category | Run On Console',
            'Keyboards, mice, headsets, speakers, monitors and graphics cards, each with what it is best for, key specs and a direct Amazon link.');
        $heroTitle = 'Gaming Gear Picks by Category';
        $heroText  = 'Keyboards, mice, headsets, speakers, monitors and graphics cards, each with what it is best for, key specs and a direct Amazon link.';
        $heroBadge = 'GAMING GEAR PICKS BY CATEGORY';
        $first = reset($categories);
        $image = $first ? trim((string)($first['image'] ?? '')) : '';
        $crumbs = [['Home', ROC_PUBLIC_URL . '/'], ['Products', $url]];
        $noindex = false;
    }
    $robots = ($query !== '' || $noindex) ? 'noindex, follow' : 'index, follow, max-image-preview:large';

    /* ---------------- head */
    $h  = rocHeadMeta($title, $desc, $url, $image, $robots);
    $h .= rocBreadcrumbLd($crumbs);
    if ($shown && $query === '') {
        $items = [];
        foreach ($shown as $i => $p) $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => rocProductUrl((string)$p['slug'])];
        $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items]);
    }
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    /* ---------------- body */
    $m  = $mainOpen . '<div class="space-y-10 py-6 animate-page-in">';
    $m .= '<div class="gradient-hero-bg text-white rounded-3xl p-6 sm:p-12 shadow-2xl relative overflow-hidden border border-emerald-500/30">'
        . '<div class="absolute -top-20 -left-20 w-80 h-80 bg-emerald-400/25 rounded-full blur-3xl pointer-events-none"></div>'
        . '<div class="absolute -bottom-20 -right-20 w-80 h-80 bg-teal-400/25 rounded-full blur-3xl pointer-events-none"></div>'
        . '<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10"><div class="lg:col-span-7 space-y-4">'
        . '<span class="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">' . rocH($heroBadge) . '</span>'
        . '<h1 class="font-display font-extrabold text-3xl sm:text-5xl text-white leading-tight">' . rocH($heroTitle) . '</h1>'
        . '<p class="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-lg">' . rocH($heroText) . '</p>'
        . '<div class="flex flex-wrap gap-2.5 pt-2"><span class="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5"><span>' . count($inScope) . ' products</span></span>'
        . '<span class="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5"><span>Direct Amazon links</span></span></div>'
        . '</div><div class="lg:col-span-5 grid grid-cols-2 gap-3">';
    $tiles = !empty($cat['group']) ? array_slice(array_intersect_key($categories, array_flip($cat['members'])), 0, 4, true)
        : ($cat ? [(string)$cat['slug'] => $cat] : array_slice($categories, 0, 4, true));
    foreach ($tiles as $s => $c) {
        if (empty($counts[$s])) continue;
        $img = trim((string)($c['image'] ?? ''));
        $m .= '<a href="/products/category/' . rocH($s) . '/" class="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group no-underline' . (count($tiles) === 1 ? ' col-span-2' : '') . '">'
            . ($img !== '' ? '<img src="' . rocH($img) . '" alt="' . rocH($c['name']) . '" class="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform"/>' : '')
            . '<div class="text-xs font-display font-bold text-white">' . rocH($c['name']) . '</div>'
            . '<div class="text-[10px] text-emerald-300 font-medium">' . $counts[$s] . ' picks</div></a>';
    }
    $m .= '</div></div></div>';

    $m .= rocCategoryNav($categories, $cat && empty($cat['group']) ? (string)$cat['slug'] : null, count($all), $counts);

    $action = !empty($cat['group']) ? '/products/' . rawurlencode((string)$cat['slug']) . '/'
        : ($cat ? '/products/category/' . rawurlencode((string)$cat['slug']) . '/' : '/products/');
    $m .= '<form method="get" action="' . rocH($action) . '" class="bg-white border border-slate-200 rounded-2xl p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-3 shadow-xs" role="search">'
        . '<div class="relative flex-1 max-w-md"><input type="search" name="q" value="' . rocH($query) . '" placeholder="Search ' . rocH($cat ? strtolower((string)$cat['name']) : 'products') . '…" aria-label="Search products" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:border-emerald-500"/></div>'
        . ($query !== '' ? '<a href="' . rocH($action) . '" class="text-[11px] font-bold text-slate-400 hover:text-emerald-600 flex items-center gap-1 px-2 no-underline">Reset</a>' : '')
        . '</form>';

    $m .= '<div class="space-y-4"><div class="text-xs font-bold text-slate-600">Showing <span class="text-emerald-600 font-extrabold">' . count($shown) . '</span> of ' . count($inScope) . ' products</div>'
        . '<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">';
    foreach ($shown as $p) $m .= rocProductCard($pdo, $p, $categories);
    $m .= '</div>';
    if (!$shown) $m .= '<p class="text-center text-slate-500 text-sm py-10">No products match that search.</p>';
    $m .= '</div></div>';

    $latest = null;
    foreach ($inScope as $p) { $t = rocProductModified($p); if ($t && (!$latest || $t > $latest)) $latest = $t; }
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $latest);
}

/* ==================================================================== */
/*                               sitemap                                */
/* ==================================================================== */

function rocProductSitemap(PDO $pdo, array $categories): void {
    $all = array_values(array_filter(rocFetchProducts($pdo, null), function ($p) { return empty($p['is_noindex']); }));
    $latestByCat = [];
    $latest = null;
    foreach ($all as $p) {
        $t = rocProductModified($p);
        if (!$t) continue;
        $s = rocProductCategorySlug($p);
        if (!isset($latestByCat[$s]) || $t > $latestByCat[$s]) $latestByCat[$s] = $t;
        if (!$latest || $t > $latest) $latest = $t;
    }

    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $line = function (string $loc, ?int $mod): string {
        return '  <url><loc>' . rocH($loc) . '</loc>' . ($mod ? '<lastmod>' . gmdate('c', $mod) . '</lastmod>' : '') . "</url>\n";
    };
    // /products/ itself is listed in pages-sitemap.xml by sitemap-render.php.
    foreach ($categories as $s => $c) {
        if (!isset($latestByCat[$s]) || !empty($c['is_noindex'])) continue;
        // A category changes when it is edited or when any of its products changes.
        $own = rocDate($c['content_modified_at'] ?? null);
        $out .= $line(rocCategoryUrl($s), max($own ?? 0, $latestByCat[$s]) ?: null);
    }
    foreach (array_keys(ROC_PRODUCT_GROUPS) as $g) {
        $mods = array_intersect_key($latestByCat, array_flip(rocProductGroup($g, $categories)['members']));
        if ($mods) $out .= $line(ROC_PUBLIC_URL . '/products/' . $g . '/', max($mods));
    }
    foreach ($all as $p) $out .= $line(rocProductUrl((string)$p['slug']), rocProductModified($p));
    $out .= "</urlset>\n";
    echo $out;
    exit;
}

/**
 * Title and description of a saved layout template (the live page it was copied
 * from), so list pages keep the wording Google already has. The first number in
 * each (e.g. "Browse 122 …", "15 Gaming Platform Hubs") is set to the current count.
 */
function rocTemplateMeta(string $root, string $name, int $count, string $title, string $desc): array {
    $f = $root . '/cms-templates/' . $name;
    $h = is_file($f) ? (string)file_get_contents($f, false, null, 0, 20000) : '';
    if (preg_match('#<title>(.*?)</title>#is', $h, $m)) $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('#<meta\s+name="description"\s+content="([^"]*)"#i', $h, $m)) $desc = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $fix = function ($s) use ($count) { return (string)preg_replace('/\b\d+\b/', (string)$count, $s, 1); };
    return [$fix($title), $fix($desc)];
}

/** Plain-text truncation that does not depend on cms-html.php. */
function rocCmsTruncatePlain(string $text, int $max): string {
    $text = trim((string)preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($text) <= $max) return $text;
    $cut = mb_substr($text, 0, $max - 1);
    $sp = mb_strrpos($cut, ' ');
    return rtrim($sp !== false && $sp > $max * 0.6 ? mb_substr($cut, 0, $sp) : $cut, " ,.;:-") . '…';
}
