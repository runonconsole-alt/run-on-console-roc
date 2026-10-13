<?php
/**
 * Run On Console — public product pages rendered from the CMS database.
 *
 *   /products/                       -> all products        (product-render.php)
 *   /category/                       -> all categories hub  (product-render.php?hub=1)
 *   /category/{category}/            -> one category or group (product-render.php?slug=...&catpage=1)
 *   /products/{category}/            -> 301 to /category/{category}/ (old address)
 *   /products/category/{slug}/       -> 301 to /category/{slug}/ (old address)
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

/* The two main categories, at /category/{group}/. These are the defaults: CMS > Product categories >
   "Main categories" saves names, descriptions, pictures and which category is in which group
   (cms_settings product_groups); rocGroups() returns the saved version. Categories not listed under
   pc-hardware belong to gaming-hardware. */
require_once __DIR__ . '/api/v1/cms/product-groups-lib.php';   // ROC_PRODUCT_GROUPS (defaults)

/* ------------------------------------------------------------ request */

$slug  = isset($_GET['slug']) ? strtolower(trim((string)$_GET['slug'], "/ \t\n\r")) : '';
$cat   = isset($_GET['cat']) ? strtolower(trim((string)$_GET['cat'], "/ \t\n\r")) : '';
$isMap = isset($_GET['sitemap']);
$query = isset($_GET['q']) ? trim(mb_substr((string)$_GET['q'], 0, 80)) : '';
$catPage = isset($_GET['catpage']);      // asked for at /category/{slug}/

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
$GLOBALS['ROC_GROUPS'] = rocLoadGroups($pdo);

if ($isMap) rocProductSitemap($pdo, $categories);

if (isset($_GET['json']) && $slug === '' && $cat === '') rocProductsJson($pdo, $categories);

if (isset($_GET['hub'])) rocRenderCategoryHub($ROOT, $pdo, $categories);

if ($cat !== '') {
    // Old address of a category page.
    if (!isset($categories[$cat])) rocNotFound($ROOT);
    header('Location: ' . rocCategoryUrl($cat) . ($query !== '' ? '?q=' . rawurlencode($query) : ''), true, 301);
    exit;
}

if ($slug !== '' && (isset(rocGroups()[$slug]) || isset($categories[$slug]))) {
    // Categories and the two groups live at /category/{slug}/; the old /products/{slug}/ address moves there.
    if (!$catPage) { header('Location: ' . rocCategoryUrl($slug) . ($query !== '' ? '?q=' . rawurlencode($query) : ''), true, 301); exit; }
    rocRenderProductList($ROOT, $pdo, $categories, isset(rocGroups()[$slug]) ? rocProductGroup($slug, $categories) : $categories[$slug], $query);
}

if ($slug !== '' && $catPage) {
    // /category/{x}/ that is not a category: a product address typed under /category/.
    if (rocFetchProduct($pdo, $slug)) { header('Location: ' . rocProductUrl($slug), true, 301); exit; }
    rocNotFound($ROOT);
}

if ($slug !== '') {
    $product = rocFetchProduct($pdo, $slug);
    if (!$product) rocOldProductRedirect($ROOT, $slug, $pdo);
    rocRenderProduct($ROOT, $pdo, $product, $categories);
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
    // Words people type that the catalog spells differently.
    $syn = ['mouse' => ['mice'], 'mice' => ['mouse'], 'headset' => ['headsets', 'audio'], 'headphone' => ['headset', 'audio'],
            'headphones' => ['headset', 'audio'], 'gpu' => ['graphics', 'rtx', 'radeon'], 'graphics' => ['gpu'], 'monitor' => ['monitors', 'display'],
            'display' => ['monitor'], 'screen' => ['monitor'], 'speaker' => ['speakers', 'soundbar'], 'mic' => ['microphone', 'wave', 'quadcast', 'seiren']];
    foreach (array_filter(explode(' ', $norm($q))) as $t) {
        $alts = array_merge([$t], $syn[$t] ?? []);
        foreach ($alts as $a) if (mb_strpos($hay, $a) !== false) continue 2;
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
/** A CMS setting (cms_settings), JSON-decoded; null when not set. */
function rocPrSetting(PDO $pdo, string $key) {
    try {
        $st = $pdo->prepare('SELECT setting_value FROM cms_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null || $v === '') ? null : json_decode((string)$v, true);
    } catch (\Throwable $e) { return null; }
}

/** The two main categories: the defaults above, with what is saved in the CMS on top. */
function rocLoadGroups(PDO $pdo): array {
    $g = ROC_PRODUCT_GROUPS;
    $saved = rocPrSetting($pdo, 'product_groups');
    if (!is_array($saved)) return $g;
    foreach ($g as $k => &$def) {
        $s = $saved[$k] ?? null;
        if (!is_array($s)) continue;
        foreach (['name', 'description', 'image'] as $f) if (isset($s[$f]) && trim((string)$s[$f]) !== '') $def[$f] = trim((string)$s[$f]);
    }
    unset($def);
    if (isset($saved['pc-hardware']['members']) && is_array($saved['pc-hardware']['members']))
        $g['pc-hardware']['members'] = array_values(array_filter(array_map('strval', $saved['pc-hardware']['members'])));
    return $g;
}
function rocGroups(): array { return $GLOBALS['ROC_GROUPS'] ?? ROC_PRODUCT_GROUPS; }

function rocProductGroup(string $slug, array $categories): array {
    $g = rocGroups()[$slug];
    $pcMembers = rocGroups()['pc-hardware']['members'];
    $members = $g['members'] ?? array_values(array_filter(array_keys($categories), function ($c) use ($pcMembers) { return !in_array($c, $pcMembers, true); }));
    $first = $categories[$members[0] ?? ''] ?? null;
    return ['slug' => $slug, 'name' => $g['name'], 'description' => $g['description'], 'members' => $members,
            'image' => ($g['image'] ?? '') ?: ($first ? ($first['image'] ?? '') : ''), 'group' => true];
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
function rocCategoryUrl(string $slug): string { return ROC_PUBLIC_URL . rocCatPath($slug); }
/** Address of a category or group page: /category/{slug}/ (the products themselves stay at /products/{slug}/). */
function rocCatPath(string $slug): string { return '/category/' . $slug . '/'; }

/**
 * Questions buyers ask, answered from this product's own data (specs, who it is for, the other
 * products of its category), with FAQPage structured data. No invented facts or prices.
 */
function rocProductFaq(string $title, string $catName, string $catSlug, string $bestFor, string $desc, array $specs, array $related, string $amazon): string {
    $qa = [];
    $list = [];
    foreach ($specs as $k => $v) { if (is_array($v)) $v = implode(', ', $v); $v = trim((string)$v); if ($v !== '' && count($list) < 6) $list[] = $k . ': ' . $v; }
    if ($list) $qa[] = ['What are the main specs of the ' . $title . '?', 'The ' . $title . ' comes with ' . implode('; ', $list) . '. The full list is in the specifications above.'];
    $first = trim((string)preg_replace('/(?<=[.!?])\s.*$/s', '', $desc));
    if ($bestFor !== '' || $first !== '') {
        $qa[] = ['Who is the ' . $title . ' best for?', ($bestFor !== '' ? 'It is a good pick for ' . rtrim(lcfirst($bestFor), '.') . '. ' : '') . $first];
    }
    $names = array_map(function ($r) { return (string)$r['title']; }, array_slice($related, 0, 3));
    $qa[] = ['What are alternatives to the ' . $title . '?', $names
        ? 'Other ' . $catName . ' worth comparing: the ' . implode(', the ', $names) . '. All ' . (count($related) + 1) . ' picks are on our ' . $catName . ' page, with their specs side by side.'
        : 'See our ' . $catName . ' page for the other picks in this category.'];
    $qa[] = ['Where can I buy the ' . $title . '?', 'The button on this page opens the ' . $title . ' on Amazon, where you can see the current price, stock and seller. Prices change often, so we do not list them here.'];
    $h = '<div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">'
        . '<h2 class="font-display font-extrabold text-base text-slate-900 uppercase tracking-wider">' . rocH($title) . ': questions buyers ask</h2>';
    foreach ($qa as [$q, $a]) {
        $ans = rocH($a);
        if ($names && strpos($q, 'alternatives') !== false) $ans = str_replace(rocH($catName) . ' page', '<a href="' . rocH(rocCatPath($catSlug)) . '" class="text-emerald-700 font-bold underline">' . rocH($catName) . ' page</a>', $ans);
        $h .= '<details class="group border border-slate-100 rounded-2xl p-4 bg-slate-50"' . ($q === $qa[0][0] ? ' open' : '') . '><summary class="font-bold text-sm text-slate-900 cursor-pointer">' . rocH($q) . '</summary>'
            . '<p class="text-xs sm:text-sm text-slate-600 leading-relaxed mt-2">' . $ans . '</p></details>';
    }
    $ld = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($x) {
        return ['@type' => 'Question', 'name' => $x[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $x[1]]];
    }, $qa)];
    $h .= '<script type="application/ld+json">' . str_replace('</', '<\/', (string)json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</script></div>';
    return $h;
}

/**
 * Click on the product photo: it opens full size over the page (Esc, a click or the X closes it;
 * "Open picture" shows the file itself). Works with the "Choose version" photos too.
 */
function rocZoomScript(): string {
    return '<script>(function(){var m=document.getElementById("roc-main-img");if(!m)return;m.style.cursor="zoom-in";m.title="Click to zoom";'
        . 'm.addEventListener("click",function(){var src=m.currentSrc||m.src,o=document.createElement("div");'
        . 'o.setAttribute("role","dialog");o.setAttribute("aria-label",m.alt||"Product photo");'
        . 'o.style.cssText="position:fixed;inset:0;z-index:9999;background:rgba(2,6,23,.92);display:flex;align-items:center;justify-content:center;padding:16px;cursor:zoom-out";'
        . 'o.innerHTML=\'<img alt="" style="max-width:100%;max-height:88vh;background:#fff;border-radius:16px;padding:12px;object-fit:contain">\''
        . '+\'<button type="button" aria-label="Close" style="position:absolute;top:14px;right:16px;background:#fff;border:0;border-radius:999px;width:40px;height:40px;font-size:22px;font-weight:700;cursor:pointer">&times;</button>\''
        . '+\'<a target="_blank" rel="noopener" style="position:absolute;bottom:16px;left:50%;transform:translateX(-50%);background:#059669;color:#fff;font:700 13px sans-serif;padding:8px 14px;border-radius:10px;text-decoration:none">Open picture</a>\';'
        . 'o.querySelector("img").src=src;o.querySelector("img").alt=m.alt;o.querySelector("a").href=src;'
        . 'var close=function(){o.remove();document.removeEventListener("keydown",esc);},esc=function(e){if(e.key==="Escape")close();};'
        . 'o.addEventListener("click",function(e){if(e.target.tagName!=="A")close();});document.addEventListener("keydown",esc);document.body.appendChild(o);});})();</script>';
}

/**
 * "Choose version" buttons for products that come in versions (8GB / 16GB, 512GB / 1TB, ...),
 * set in CMS > Products > Versions (or api/v1/cms/product-variants.json until then). A click shows that version's photo at once and opens
 * its own page (its own price link, details and address).
 */
function rocVariantPicker(PDO $pdo, string $slug, array $categories): string {
    static $groups = null;
    // CMS > Products > Versions (cms_settings product_variants); the file is the starting list.
    if ($groups === null) {
        $saved = rocPrSetting($pdo, 'product_variants');
        $groups = is_array($saved) && isset($saved['groups']) ? (array)$saved['groups']
            : (array)(json_decode((string)@file_get_contents(__DIR__ . '/api/v1/cms/product-variants.json'), true)['groups'] ?? []);
    }
    foreach ($groups as $vs) {
        if (!in_array($slug, array_column($vs, 'slug'), true)) continue;
        $h = ''; $n = 0;
        foreach ($vs as $v) {
            $row = rocFetchProduct($pdo, (string)$v['slug']);
            if (!$row) continue;                                   // version taken off the site
            $n++;
            $img = rocProductImage($row, $categories);
            $on = $v['slug'] === $slug;
            $h .= '<a href="/products/' . rocH((string)$v['slug']) . '/" data-roc-variant-img="' . rocH($img) . '"'
                . ($on ? ' aria-current="true"' : '')
                . ' class="no-underline text-xs font-extrabold px-3 py-1.5 rounded-xl border transition-colors '
                . ($on ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-slate-200 text-slate-700 hover:border-emerald-500 hover:text-emerald-700') . '">'
                . rocH((string)$v['label']) . '</a>';
        }
        if ($n < 2) return '';
        return '<div class="pt-1"><p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Choose version</p>'
            . '<div class="flex flex-wrap gap-2" data-roc-variants>' . $h . '</div></div>'
            . '<script>(function(){var b=document.querySelector("[data-roc-variants]"),m=document.getElementById("roc-main-img");if(!b||!m)return;'
            . 'b.addEventListener("click",function(e){var a=e.target.closest("a[data-roc-variant-img]");if(!a||a.getAttribute("aria-current"))return;'
            . 'm.removeAttribute("srcset");m.src=a.getAttribute("data-roc-variant-img");});'
            . '[].forEach.call(b.querySelectorAll("a[data-roc-variant-img]"),function(a){var i=new Image();i.src=a.getAttribute("data-roc-variant-img");});})();</script>';
    }
    return '';
}

/** Picture description: the one set in the CMS, or "Title - Category" (better than the bare name). */
function rocImageAlt(array $p, string $title, array $categories): string {
    $alt = trim((string)($p['image_alt'] ?? ''));
    if ($alt !== '' && $alt !== $title) return $alt;
    $cat = (string)(($categories[rocProductCategorySlug($p)]['name'] ?? null) ?: ($p['category'] ?? ''));
    return $cat !== '' ? $title . ' - ' . $cat : $title;
}

/** Amazon product photo (SiteStripe link): white background, shown whole rather than cropped. */
function rocIsAmazonImg(string $src): bool {
    return (bool)preg_match('#^(https://(ws-[a-z]+\.amazon-adsystem\.com|m\.media-amazon\.com)/|/images/products/photos/)#i', $src);
}

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

/**
 * Link preview image. A real product photo set in the CMS wins; otherwise the
 * 1200 x 630 JPG card (/images/products/share/{slug}.jpg), because WhatsApp,
 * LinkedIn and Facebook do not all show WebP or SVG.
 */
function rocShareImage(string $image, array $p, array $categories): string {
    $isCard = (strpos($image, '/images/products/') === 0 && strpos($image, '/images/products/photos/') !== 0) || preg_match('#^https://#', $image) && rocIsAmazonImg($image);   // Amazon pictures are small squares: use the share card
    if ($image !== '' && !$isCard && substr($image, -4) !== '.svg') return $image;
    $share = '/images/products/share/' . $p['slug'] . '.jpg';
    if (is_file(__DIR__ . $share)) return $share;
    if (substr($image, -4) !== '.svg') return $image;
    $c = $categories[rocProductCategorySlug($p)] ?? null;
    return $c ? trim((string)($c['image'] ?? '')) : '';
}

/** First title that fits in 60 characters (Google cuts longer ones). */
function rocFitTitle(array $options): string {
    // 55 characters stay inside Google's ~560 px title width.
    foreach ($options as $t) if (mb_strlen($t) <= 55) return $t;
    return end($options);
}

/** Products whose old static folder only holds a redirect stub (Phase 4 renames). */
function rocOldProductRedirect(string $root, string $slug, ?PDO $pdo = null): void {
    $stub = $root . '/products/' . $slug . '/index.php';
    if (is_file($stub)) { require $stub; exit; }
    // A product taken off the site (unpublished in the CMS) sends visitors to its category.
    if ($pdo) {
        try {
            $st = $pdo->prepare('SELECT * FROM products WHERE slug = ? LIMIT 1');
            $st->execute([$slug]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && ($cs = rocProductCategorySlug($row)) !== '') { header('Location: ' . rocCategoryUrl($cs), true, 301); exit; }
        } catch (\Throwable $e) {}
    }
    // A product deleted from the CMS (cli-product-prune.php): its category, so no old link breaks.
    $map = json_decode((string)@file_get_contents($root . '/api/v1/cms/product-redirects.json'), true);
    if (is_array($map) && !empty($map[$slug]) && preg_match('#^/(products|category)/[a-z0-9-]*/?$#', (string)$map[$slug])) {
        $to = (string)preg_replace('#^/products/([a-z0-9-]+)/$#', '/category/$1/', (string)$map[$slug]);
        header('Location: ' . ROC_PUBLIC_URL . $to, true, 301); exit;
    }
    rocNotFound($root);
}

/* ==================================================================== */
/*                            shared markup                             */
/* ==================================================================== */

function rocProductCard(PDO $pdo, array $p, array $categories): string {
    $slug  = (string)$p['slug'];
    $title = (string)$p['title'];
    $img   = rocProductImage($p, $categories);
    $alt   = rocImageAlt($p, $title, $categories);
    $badge = trim((string)($p['badge'] ?? ''));
    $brand = trim((string)($p['brand'] ?? ''));
    $catN  = (string)(($categories[rocProductCategorySlug($p)]['name'] ?? null) ?: ($p['category'] ?? ''));
    $sub   = trim((string)($p['subtitle'] ?? '')) ?: trim((string)($p['short_desc'] ?? ''));
    return '<div class="transition-shadow duration-300 will-change-transform game-card flex flex-col justify-between group p-5 space-y-4"><div class="space-y-3">'
        . '<a href="/products/' . rocH($slug) . '/" class="relative block h-44 rounded-2xl overflow-hidden bg-slate-950 border border-slate-800 shadow-sm">'
        . ($img !== '' ? '<img src="' . rocH($img) . '" alt="' . rocH($alt) . '" width="400" height="300" loading="lazy" decoding="async" class="w-full h-full ' . (rocIsAmazonImg($img) ? 'object-contain bg-white p-3' : 'object-cover opacity-90') . ' group-hover:scale-105 transition-transform duration-500"/>' : '')
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

/** The Products-menu group a category belongs to. */
function rocGroupOf(string $catSlug): string {
    return in_array($catSlug, rocGroups()['pc-hardware']['members'], true) ? 'pc-hardware' : 'gaming-hardware';
}

function rocBreadcrumbLd(array $items): string {
    $list = [];
    foreach ($items as $i => [$name, $url]) {
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $url];
    }
    return rocJsonLd(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list]);
}

function rocHeadMeta(string $title, string $desc, string $url, string $image, string $robots, string $ogType = 'website', string $imageAlt = ''): string {
    $h  = '<title>' . rocH($title) . "</title>\n";
    $h .= '    <meta name="title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta name="description" content="' . rocH($desc) . "\" />\n";
    $h .= '    <meta name="robots" content="' . rocH($robots) . "\" />\n";
    $h .= '    <link rel="canonical" href="' . rocH($url) . "\" />\n";
    $h .= '    <meta property="og:type" content="' . rocH($ogType) . "\" />\n";
    $h .= '    <meta property="og:url" content="' . rocH($url) . "\" />\n";
    $h .= '    <meta property="og:title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta property="og:description" content="' . rocH($desc) . "\" />\n";
    if ($image !== '') {
        $h .= '    <meta property="og:image" content="' . rocH(rocAbs($image)) . "\" />\n";
        $size = strpos($image, '/') === 0 && is_file(__DIR__ . $image) ? @getimagesize(__DIR__ . $image) : false;
        if ($size) $h .= '    <meta property="og:image:width" content="' . (int)$size[0] . "\" />\n" . '    <meta property="og:image:height" content="' . (int)$size[1] . "\" />\n";
        $h .= '    <meta property="og:image:alt" content="' . rocH($imageAlt !== '' ? $imageAlt : $title) . "\" />\n";
    }
    $h .= "    <meta property=\"og:site_name\" content=\"Run On Console\" />\n";
    $h .= "    <meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
    $h .= "    <meta name=\"twitter:site\" content=\"@RunOnConsole\" />\n";
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
    $alt     = rocImageAlt($p, $title, $categories);
    $specs   = rocProductList($p, 'specs_json');
    $feats   = rocProductList($p, 'features_json');
    $amazon  = rocAmazonUrl($pdo, $p);
    $mod     = rocProductModified($p);
    $noindex = !empty($p['is_noindex']);

    // A title saved with the old automatic pattern counts as automatic (it is often too long).
    $metaT = trim((string)($p['meta_title'] ?? ''));
    if ($metaT === $title . ': Specs & Where to Buy | Run On Console') $metaT = '';
    $metaT = $metaT ?: rocFitTitle([
        $title . ': Specs & Where to Buy | Run On Console',
        $title . ' Specs & Where to Buy | ROC',
        $title . ': Specs & Where to Buy',
        $title . ' Specs | Run On Console',
        $title . ' | Run On Console',
        $title,
    ]);
    $metaD = trim((string)($p['meta_description'] ?? '')) ?: rocCmsTruncatePlain($desc, 155);
    if (mb_strlen($metaD) > 155) $metaD = rocCmsTruncatePlain($metaD, 150);

    /* ---------------- head */
    $h  = rocHeadMeta($metaT, $metaD, $url, rocShareImage($image, $p, $categories), $noindex ? 'noindex, follow' : 'index, follow, max-image-preview:large', 'product', $alt);
    $h .= rocBreadcrumbLd([
        ['Home', ROC_PUBLIC_URL . '/'],
        ['Products', ROC_PUBLIC_URL . '/products/'],
        [$catName, rocCategoryUrl($catSlug)],
        [$title, $url],
    ]);
    // No price, rating or review on the page: Google reports a Product without
    // offers/review/aggregateRating as invalid, and we do not invent them. So the
    // page is an ItemPage about the product (no Product type).
    $ld = ['@context' => 'https://schema.org', '@type' => 'ItemPage', 'name' => $title, 'url' => $url, 'description' => $metaD,
           'inLanguage' => 'en', 'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'],
           'about' => ['@type' => 'Thing', 'name' => $title, 'description' => $catName . ($brand !== '' ? ' by ' . $brand : '')]];
    if ($image !== '') $ld['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => rocAbs($image)];
    if ($mod) $ld['dateModified'] = gmdate('c', $mod);
    $h .= rocJsonLd($ld);
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    /* ---------------- body */
    $m  = $mainOpen . '<div class="max-w-6xl mx-auto py-6 space-y-8 animate-page-in">';
    $m .= '<nav aria-label="Breadcrumb" class="text-xs font-semibold text-slate-500 flex flex-wrap items-center gap-1.5">'
        . '<a href="/products/" class="hover:text-emerald-600 no-underline text-slate-500">Products</a><span>/</span>'
        . '<a href="' . rocH(rocCatPath($catSlug)) . '" class="hover:text-emerald-600 no-underline text-slate-500">' . rocH($catName) . '</a><span>/</span>'
        . '<span class="text-slate-800">' . rocH($title) . '</span></nav>';
    $m .= '<div class="flex items-center justify-between gap-3">'
        . '<a href="' . rocH(rocCatPath($catSlug)) . '" class="bg-white border border-slate-300 hover:border-emerald-600 text-slate-700 font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-2 transition-colors shadow-sm no-underline">'
        . ROC_ICON_BACK . '<span>All ' . rocH($catName) . '</span></a></div>';

    $m .= '<div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm grid grid-cols-1 lg:grid-cols-12 gap-8">'
        . '<div class="lg:col-span-5 flex flex-col justify-center"><div class="transition-shadow duration-300 will-change-transform relative rounded-2xl overflow-hidden bg-slate-950 p-4 border border-slate-800 shadow-xl group">'
        . ($image !== '' ? '<img id="roc-main-img" width="800" height="480" src="' . rocH($image) . '" alt="' . rocH($alt) . '" class="w-full h-80 ' . (rocIsAmazonImg($image) ? 'object-contain bg-white p-4' : 'object-cover') . ' rounded-xl group-hover:scale-105 transition-transform duration-500" fetchpriority="high"/>' : '')
        . ($badge !== '' ? '<span class="absolute top-4 left-4 max-w-[85%] bg-emerald-600 text-white font-extrabold text-[10px] px-3 py-1 rounded-full uppercase tracking-wider badge-glow">' . rocH($badge) . '</span>' : '')
        . ($image !== '' ? '<span class="absolute bottom-3 right-3 bg-slate-900/80 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg pointer-events-none">Click to zoom</span>' : '')
        . '</div></div>' . ($image !== '' ? rocZoomScript() : '')
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
    $m .= rocVariantPicker($pdo, (string)$p['slug'], $categories);
    $m .= '<div class="space-y-2 pt-2">'
        . '<a href="' . rocH($amazon) . '" target="_blank" rel="sponsored nofollow noopener noreferrer" aria-label="Check price of ' . rocH($title) . ' on Amazon" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-display font-extrabold text-sm py-3.5 px-6 rounded-2xl flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/30 transition-all hover:scale-102 no-underline">'
        . ROC_ICON_CART . '<span>Check price &amp; stock on Amazon</span>' . ROC_ICON_EXT . '</a>'
        . '<p class="text-[11px] text-slate-400">Prices and stock change often; Amazon shows the current price. We may earn a commission from qualifying purchases.</p>'
        . '</div></div></div>';

    if ($specs) {
        $m .= '<div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">'
            . '<h2 class="font-display font-extrabold text-base text-slate-900 uppercase tracking-wider flex items-center gap-2"><span>' . rocH($title) . ' key specifications</span></h2>'
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
    $m .= rocProductFaq($title, $catName, $catSlug, $bestFor, $desc, $specs ?: [], $related, $amazon);
    if ($related) {
        $m .= '<div class="space-y-4"><div class="flex items-center justify-between gap-3">'
            . '<h2 class="font-display font-extrabold text-base text-slate-900 uppercase tracking-wider">More ' . rocH($catName) . '</h2>'
            . '<a href="' . rocH(rocCatPath($catSlug)) . '" class="text-xs font-bold text-emerald-700 hover:text-emerald-900 flex items-center gap-1 no-underline">See all ' . (count($related) + 1) . ' ' . ROC_ICON_NEXT . '</a></div>'
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

/**
 * /category/: every product category, in its group (PC Hardware, Gaming Hardware), with a real
 * product photo, the number of picks and a link to the category. The header's CATEGORY menu opens it.
 */
function rocRenderCategoryHub(string $root, PDO $pdo, array $categories): void {
    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, ROC_PRODUCT_TEMPLATES['list']);
    $all = rocFetchProducts($pdo, null);
    $counts = []; $photo = [];
    foreach ($all as $p) {
        $s = rocProductCategorySlug($p);
        $counts[$s] = ($counts[$s] ?? 0) + 1;
        $img = trim((string)($p['image'] ?? ''));
        if (!isset($photo[$s]) && strpos($img, '/images/products/photos/') === 0) $photo[$s] = [$img, (string)$p['title']];
    }
    $url = ROC_PUBLIC_URL . '/category/';
    $n = count(array_filter($counts));
    $title = 'Gaming & PC Hardware Categories | Run On Console';
    $desc = 'Shop gaming gear by category: PC Hardware (GPUs, CPUs, RAM, cases, monitors) and Gaming Hardware (keyboards, mice, headsets, chairs, laptops).';
    // Hero picture: the page's own picture (cli-site-images.php) or four product photos.
    $hero = is_file($root . '/uploads/site/category-hero.webp') ? '/uploads/site/category-hero.webp' : '';
    $heroAlt = 'Gaming and PC hardware categories on Run On Console';
    $tiles = [];
    foreach (['gpu', 'cpu', 'audio', 'chairs', 'keyboards', 'monitors', 'mice', 'laptops'] as $s) if (isset($photo[$s]) && count($tiles) < 4) $tiles[$s] = $photo[$s];
    foreach ($photo as $s => $ph) if (count($tiles) < 4 && !isset($tiles[$s])) $tiles[$s] = $ph;
    $first = reset($tiles);
    $h  = rocHeadMeta($title, rocCmsTruncatePlain($desc, 155), $url, $hero !== '' ? $hero : ($first ? $first[0] : ''), 'index, follow, max-image-preview:large', 'website', $hero !== '' ? $heroAlt : 'Product categories on Run On Console');
    $h .= rocBreadcrumbLd([['Home', ROC_PUBLIC_URL . '/'], ['Categories', $url]]);
    $items = []; $i = 0;
    foreach (rocGroups() as $g => $def) {
        $num = 0; foreach ($all as $p) if (rocGroupOf(rocProductCategorySlug($p)) === $g) $num++;
        $items[] = ['@type' => 'ListItem', 'position' => ++$i, 'url' => rocCategoryUrl($g), 'name' => $def['name'], 'description' => $def['description'] . ' ' . $num . ' products.'];
    }
    $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'Gaming & PC Hardware Categories', 'description' => $desc, 'url' => $url,
                     'inLanguage' => 'en', 'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'],
                     'primaryImageOfPage' => rocAbs($hero !== '' ? $hero : ($first ? $first[0] : '/images/logo.png')),
                     'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($items), 'itemListElement' => $items]]);
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    $m  = $mainOpen . '<div class="space-y-10 py-6 animate-page-in">';
    $m .= '<div class="gradient-hero-bg text-white rounded-3xl p-6 sm:p-12 shadow-2xl relative overflow-hidden border border-emerald-500/30">'
        . '<div class="absolute -top-20 -left-20 w-80 h-80 bg-emerald-400/25 rounded-full blur-3xl pointer-events-none"></div>'
        . '<div class="absolute -bottom-20 -right-20 w-80 h-80 bg-teal-400/25 rounded-full blur-3xl pointer-events-none"></div>'
        . '<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10"><div class="lg:col-span-7 space-y-4">'
        . '<span class="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5">Categories</span>'
        . '<h1 class="font-display font-extrabold text-3xl sm:text-5xl text-white leading-tight">Gaming &amp; PC Hardware Categories</h1>'
        . '<p class="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-lg">Two categories: PC Hardware for building or upgrading a gaming PC, and Gaming Hardware for the gear you play with. Pick one to see its products.</p>'
        . '<div class="flex flex-wrap gap-2.5 pt-2"><span class="badge-holo-glow badge-static text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl">2 categories</span>'
        . '<span class="badge-holo-glow badge-static text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl">' . count($all) . ' products</span></div>'
        . '</div><div class="lg:col-span-5">';
    if ($hero !== '') {
        $m .= '<div class="bg-slate-900/85 border-2 border-emerald-400/40 rounded-3xl p-3 shadow-2xl"><img width="1200" height="675" src="' . rocH($hero) . '" alt="' . rocH($heroAlt) . '" class="w-full rounded-2xl object-cover" style="aspect-ratio:16/9"/></div>';
    } else {
        $m .= '<div class="grid grid-cols-2 gap-3">';
        foreach ($tiles as $s => [$img, $t]) $m .= '<div class="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 shadow-2xl"><div class="bg-white rounded-xl h-28 overflow-hidden"><img width="400" height="300" src="' . rocH($img) . '" alt="' . rocH($t) . '" class="w-full h-full object-contain p-2"/></div>'
            . '<div class="text-[10px] text-emerald-300 font-bold mt-2">' . rocH((string)($categories[$s]['name'] ?? '')) . '</div></div>';
        $m .= '</div>';
    }
    $m .= '</div></div></div>';
    // The two categories, each a big card with four of its product photos.
    $m .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
    foreach (rocGroups() as $g => $def) {
        $num = 0; $pics = [];
        foreach ($all as $p) {
            if (rocGroupOf(rocProductCategorySlug($p)) !== $g) continue;
            $num++;
            $img = trim((string)($p['image'] ?? ''));
            $cs = rocProductCategorySlug($p);
            if (count($pics) < 4 && strpos($img, '/images/products/photos/') === 0 && !isset($pics[$cs])) $pics[$cs] = [$img, (string)$p['title']];
        }
        if (!$num) continue;
        $m .= '<a href="' . rocCatPath($g) . '" class="group bg-white border-2 border-slate-200 hover:border-emerald-500 rounded-3xl p-5 shadow-sm hover:shadow-xl transition-all no-underline flex flex-col gap-4">'
            . '<div class="grid gap-2" style="grid-template-columns:repeat(4,minmax(0,1fr))">';
        foreach ($pics as [$img, $t]) $m .= '<div class="bg-white border border-slate-100 rounded-xl overflow-hidden" style="aspect-ratio:1/1"><img width="200" height="200" loading="lazy" src="' . rocH($img) . '" alt="' . rocH($t) . '" class="w-full h-full object-contain p-1.5 group-hover:scale-105 transition-transform"/></div>';
        $m .= '</div><div><h2 class="font-display font-extrabold text-2xl text-slate-900 group-hover:text-emerald-700">' . rocH($def['name']) . '</h2>'
            . '<p class="text-xs sm:text-sm text-slate-500 mt-1">' . rocH($def['description']) . '</p></div>'
            . '<span class="self-start bg-emerald-600 group-hover:bg-emerald-700 text-white font-display font-extrabold text-xs py-2.5 px-4 rounded-xl">See ' . $num . ' products &rarr;</span></a>';
    }
    $m .= '</div></div>';
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, time());
}

function rocRenderProductList(string $root, PDO $pdo, array $categories, ?array $cat, string $query): void {
    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, ROC_PRODUCT_TEMPLATES[$cat ? 'category' : 'list']);

    $all = rocFetchProducts($pdo, null);
    $counts = [];
    foreach ($all as $p) { $s = rocProductCategorySlug($p); $counts[$s] = ($counts[$s] ?? 0) + 1; }
    $inScope = $cat ? rocFetchProducts($pdo, $cat) : $all;
    $shown   = $query !== '' ? rocFetchProducts($pdo, $cat, $query) : $inScope;

    // 24 products per page; "Load more" fetches the next 24 (?page=N&partial=1), and ?page=N works without JavaScript.
    $per = 24;
    $pages = max(1, (int)ceil(count($shown) / $per));
    $page = $query === '' ? min($pages, max(1, (int)($_GET['page'] ?? 1))) : 1;
    $pageItems = $query === '' ? array_slice($shown, ($page - 1) * $per, $per) : $shown;
    if (isset($_GET['partial'])) {
        $html = '';
        foreach ($pageItems as $p) $html .= rocProductCard($pdo, $p, $categories);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo json_encode(['html' => $html, 'next' => $page < $pages ? $page + 1 : null, 'left' => max(0, count($shown) - $page * $per)], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($cat) {
        $name  = (string)$cat['name'];
        $url   = rocCategoryUrl((string)$cat['slug']);
        $n     = count($inScope);
        $title = trim((string)($cat['meta_title'] ?? '')) ?: rocFitTitle(['Best ' . $name . ': ' . $n . ' Picks | Run On Console', 'Best ' . $name . ' | Run On Console', 'Best ' . $name]);
        $desc  = trim((string)($cat['meta_description'] ?? ''))
              ?: (function () use ($cat, $name, $n) {
                  // Description + count line when both fit in 155 characters, else the description alone.
                  $base = trim((string)($cat['description'] ?? '')) ?: ('Our ' . $name . ' picks.');
                  $full = $base . ' ' . $n . ' picks with what each is best for, key specs and a direct Amazon link.';
                  return mb_strlen($full) <= 155 ? $full : rocCmsTruncatePlain($base, 155);
              })();
        $heroTitle = 'Best ' . $name;
        $heroText  = trim((string)($cat['description'] ?? '')) ?: $desc;
        $heroBadge = count($inScope) . ' PICKS';
        $image = trim((string)($cat['image'] ?? ''));
        $crumbs = [['Home', ROC_PUBLIC_URL . '/'], ['Categories', ROC_PUBLIC_URL . '/category/']];
        if (empty($cat['group'])) { $g = rocGroupOf((string)$cat['slug']); $crumbs[] = [rocGroups()[$g]['name'], rocCategoryUrl($g)]; }
        $crumbs[] = [$name, $url];
        $noindex = !empty($cat['is_noindex']);
    } else {
        $url   = ROC_PUBLIC_URL . '/products/';
        [$title, $desc] = rocTemplateMeta($root, ROC_PRODUCT_TEMPLATES['list'], count($all),
            'Gaming Gear Picks by Category | Run On Console',
            'Keyboards, mice, headsets, speakers, monitors and graphics cards, each with what it is best for, key specs and a direct Amazon link.');
        $heroTitle = 'All Gaming & PC Hardware Picks';
        $heroText  = 'Every product on Run On Console in one place: graphics cards, processors, monitors, keyboards, mice, headsets, chairs, handhelds and laptops, each with what it is best for, key specs and a direct Amazon link.';
        $heroBadge = 'ALL PRODUCTS';
        $first = reset($categories);
        $image = $first ? trim((string)($first['image'] ?? '')) : '';
        $crumbs = [['Home', ROC_PUBLIC_URL . '/'], ['Products', $url]];
        $noindex = false;
    }
    $robots = ($query !== '' || $noindex || $page > 1) ? 'noindex, follow' : 'index, follow, max-image-preview:large';
    if ($page > 1) $url .= '?page=' . $page;

    /* ---------------- head */
    $h  = rocHeadMeta($title, $desc, $url, $image, $robots);
    $h .= rocBreadcrumbLd($crumbs);
    if ($shown && $query === '') {
        $items = [];
        foreach ($pageItems as $i => $p) $items[] = ['@type' => 'ListItem', 'position' => ($page - 1) * $per + $i + 1, 'url' => rocProductUrl((string)$p['slug'])];
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
        // Facts, not links (they only repeated what is on the page).
        . '<div class="flex flex-wrap gap-2.5 pt-2"><span class="badge-holo-glow badge-static text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5"><span>' . count($inScope) . ' products</span></span>'
        . '<span class="badge-holo-glow badge-static text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5"><span>Direct Amazon links</span></span></div>'
        . '</div><div class="lg:col-span-5 grid grid-cols-2 gap-3">';
    $tiles = !empty($cat['group']) ? array_slice(array_intersect_key($categories, array_flip($cat['members'])), 0, 4, true)
        : ($cat ? [(string)$cat['slug'] => $cat] : []);
    if (!$cat) {
        // /products/ is the products page (categories are under CATEGORY): four products with a real photo.
        $pick = []; $seen = [];
        foreach ($all as $p) {
            $cs = rocProductCategorySlug($p);
            if (isset($seen[$cs]) || strpos((string)($p['image'] ?? ''), '/images/products/photos/') !== 0) continue;
            $seen[$cs] = 1; $pick[] = $p;
            if (count($pick) === 4) break;
        }
        foreach ($pick as $p)
            $m .= '<a href="/products/' . rocH((string)$p['slug']) . '/" class="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group no-underline">'
                . '<div class="bg-white rounded-xl mb-2 h-28 overflow-hidden"><img width="400" height="300" src="' . rocH((string)$p['image']) . '" alt="' . rocH(rocImageAlt($p, (string)$p['title'], $categories)) . '" class="w-full h-full object-contain p-2 group-hover:scale-105 transition-transform"/></div>'
                . '<div class="text-xs font-display font-bold text-white line-clamp-1">' . rocH((string)$p['title']) . '</div>'
                . '<div class="text-[10px] text-emerald-300 font-medium">' . rocH((string)(($categories[rocProductCategorySlug($p)]['name'] ?? '') ?: ($p['category'] ?? ''))) . '</div></a>';
    }
    foreach ($tiles as $s => $c) {
        if (empty($counts[$s])) continue;
        $img = trim((string)($c['image'] ?? ''));
        $m .= '<a href="' . rocH(rocCatPath($s)) . '" class="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group no-underline' . (count($tiles) === 1 ? ' col-span-2' : '') . '">'
            . ($img !== '' ? '<img width="800" height="480" src="' . rocH($img) . '" alt="' . rocH($c['name']) . '" class="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform"/>' : '')
            . '<div class="text-xs font-display font-bold text-white">' . rocH($c['name']) . '</div>'
            . '<div class="text-[10px] text-emerald-300 font-medium">' . $counts[$s] . ' picks</div></a>';
    }
    $m .= '</div></div></div>';

    // No category buttons: /products/ lists every product; categories are in the CATEGORY menu (/category/...).

    $action = $cat ? rocCatPath(rawurlencode((string)$cat['slug'])) : '/products/';
    $m .= '<form method="get" action="' . rocH($action) . '" class="bg-white border border-slate-200 rounded-2xl p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-3 shadow-xs" role="search">'
        . '<div class="relative flex-1 max-w-md"><input type="search" name="q" value="' . rocH($query) . '" placeholder="Search ' . rocH($cat ? strtolower((string)$cat['name']) : 'products') . '…" aria-label="Search products" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:border-emerald-500"/></div>'
        . ($query !== '' ? '<a href="' . rocH($action) . '" class="text-[11px] font-bold text-slate-400 hover:text-emerald-600 flex items-center gap-1 px-2 no-underline">Reset</a>' : '')
        . '</form>';

    $listH = $cat ? 'All ' . (string)$cat['name'] : 'All gaming gear';
    $m .= '<div id="all-products" class="space-y-4 scroll-mt-24"><div class="flex flex-wrap items-baseline justify-between gap-2">'
        . '<h2 class="font-display font-extrabold text-lg text-slate-900">' . rocH($listH) . '</h2>'
        . '<div class="text-xs font-bold text-slate-600">Showing <span id="roc-shown" class="text-emerald-600 font-extrabold">' . (($page - 1) * $per + count($pageItems)) . '</span> of ' . count($shown) . ' products</div></div>'
        . '<div id="roc-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">';
    foreach ($pageItems as $p) $m .= rocProductCard($pdo, $p, $categories);
    $m .= '</div>';
    if (!$shown) $m .= '<p class="text-center text-slate-500 text-sm py-10">No products match that search.</p>';
    if ($page < $pages) {
        $left = count($shown) - $page * $per;
        $m .= '<div class="text-center pt-2"><a id="roc-more" href="' . rocH($action) . '?page=' . ($page + 1) . '" rel="next" data-roc-local data-base="' . rocH($action) . '" data-next="' . ($page + 1) . '"'
            . ' class="inline-block bg-emerald-600 hover:bg-emerald-700 text-white font-display font-extrabold text-sm py-3 px-6 rounded-xl shadow-md no-underline">'
            . 'Load more products <span data-left>(' . $left . ' more)</span></a></div>'
            . '<script>(function(){var a=document.getElementById("roc-more"),g=document.getElementById("roc-grid"),s=document.getElementById("roc-shown");if(!a||!g)return;'
            . 'a.addEventListener("click",function(e){e.preventDefault();if(a.dataset.busy)return;a.dataset.busy=1;a.style.opacity=".6";'
            . 'fetch(a.dataset.base+"?page="+a.dataset.next+"&partial=1",{headers:{Accept:"application/json"}}).then(function(r){return r.json();}).then(function(d){'
            . 'g.insertAdjacentHTML("beforeend",d.html);if(s)s.textContent=g.children.length;'
            . 'if(d.next){a.dataset.next=d.next;a.href=a.dataset.base+"?page="+d.next;a.querySelector("[data-left]").textContent="("+d.left+" more)";delete a.dataset.busy;a.style.opacity="";}'
            . 'else a.parentNode.remove();}).catch(function(){location.href=a.href;});});})();</script>';
    }
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
    $out  = rocSmOpen();
    $line = 'rocSmLine';
    // /products/ itself is listed in pages-sitemap.xml by sitemap-render.php; the categories page is here.
    if ($latest) $out .= $line(ROC_PUBLIC_URL . '/category/', $latest, 'Gaming & PC Hardware Categories');
    foreach ($categories as $s => $c) {
        if (!isset($latestByCat[$s]) || !empty($c['is_noindex'])) continue;
        // A category changes when it is edited or when any of its products changes.
        $own = rocDate($c['content_modified_at'] ?? null);
        $out .= $line(rocCategoryUrl($s), max($own ?? 0, $latestByCat[$s]) ?: null, (string)$c['name']);
    }
    foreach (array_keys(rocGroups()) as $g) {
        $mods = array_intersect_key($latestByCat, array_flip(rocProductGroup($g, $categories)['members']));
        if ($mods) $out .= $line(rocCategoryUrl($g), max($mods), rocGroups()[$g]['name']);
    }
    foreach ($all as $p) $out .= $line(rocProductUrl((string)$p['slug']), rocProductModified($p), (string)$p['title']);
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
