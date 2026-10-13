<?php
/**
 * Run On Console — gaming platform pages rendered from the CMS database.
 *
 *   /gaming-platforms/                 -> all platforms        (category-render.php)
 *   /gaming-platforms/{slug}/          -> one platform hub     (category-render.php?slug=...)
 *   /sitemaps/categories-sitemap.xml   -> platforms sitemap    (category-render.php?sitemap=1)
 *
 * Same approach as blog-render.php and product-render.php: layout from the
 * prerendered pages saved in /cms-templates/ by cli-gaming-categories.php,
 * content from gaming_categories / gaming_devices. Only published platforms and
 * devices are shown. CMS guide pages under /gaming-platforms/{cat}/{slug}/ are still
 * served by page-render.php.
 */

declare(strict_types=1);

$ROOT = __DIR__;

define('ROC_RENDER_LIB_ONLY', true);
require_once $ROOT . '/blog-render.php';

const ROC_CAT_TEMPLATES = ['list' => 'category-list.html', 'hub' => 'category-hub.html'];

/* ------------------------------------------------------------ request */

$slug  = isset($_GET['slug']) ? strtolower(trim((string)$_GET['slug'], "/ \t\n\r")) : '';
$isMap = isset($_GET['sitemap']);
if ($slug !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) rocNotFound($ROOT);

/* ----------------------------------------------------------- database */

$pdo = null;
try {
    ob_start();
    require_once $ROOT . '/api/v1/config.php';
    ob_end_clean();
    $pdo = function_exists('getDBConnection') ? getDBConnection() : null;
} catch (\Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    error_log('category-render bootstrap: ' . $e->getMessage());
    $pdo = null;
}
if (!headers_sent()) {
    header_remove('X-Robots-Tag');
    header_remove('Content-Type');
    header_remove('Access-Control-Allow-Origin');
    header_remove('Access-Control-Allow-Credentials');
}
if (!$pdo) {
    $static = $ROOT . '/categories/' . ($slug !== '' ? $slug . '/' : '') . 'index.html';
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

$cats = rocGamingCategories($pdo);
if ($isMap) rocGamingSitemap($pdo, $cats);
if ($slug !== '') {
    if (!isset($cats[$slug])) rocNotFound($ROOT);
    rocRenderHub($ROOT, $pdo, $cats[$slug], $cats);
}
rocRenderPlatformList($ROOT, $pdo, $cats);


/* ==================================================================== */
/*                               data                                   */
/* ==================================================================== */

function rocJsonList($v): array {
    if (is_array($v)) return $v;
    $d = is_string($v) && $v !== '' ? json_decode($v, true) : null;
    return is_array($d) ? $d : [];
}

function rocGamingCategories(PDO $pdo): array {
    $out = [];
    foreach ($pdo->query("SELECT * FROM gaming_categories WHERE status = 'published' ORDER BY sort_rank, title")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(string)$r['slug']] = $r;
    }
    return $out;
}

function rocGamingDevices(PDO $pdo, ?string $catSlug = null): array {
    if ($catSlug === null) {
        return $pdo->query("SELECT * FROM gaming_devices WHERE status = 'published' ORDER BY sort_rank, name")->fetchAll(PDO::FETCH_ASSOC);
    }
    $st = $pdo->prepare("SELECT * FROM gaming_devices WHERE status = 'published' AND category_slug = ? ORDER BY sort_rank, name");
    $st->execute([$catSlug]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function rocCatModified(array $c, array $devices): ?int {
    $t = rocDate($c['content_modified_at'] ?? null);
    foreach ($devices as $d) { $x = rocDate($d['content_modified_at'] ?? null); if ($x && (!$t || $x > $t)) $t = $x; }
    return $t;
}

function rocPlatformUrl(string $slug): string { return ROC_PUBLIC_URL . '/gaming-platforms/' . $slug . '/'; }

/** Published CMS category guides for a platform (served by page-render.php at /gaming-platforms/{cat}/{slug}/). */
function rocPlatformGuides(PDO $pdo, string $catSlug): array {
    try {
        $st = $pdo->prepare("SELECT title, slug, excerpt, image FROM pages
                              WHERE status = 'published' AND page_type = 'category' AND category_slug = ?
                              ORDER BY published_at DESC LIMIT 12");
        $st->execute([$catSlug]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { return []; }
}

/* ==================================================================== */
/*                               markup                                 */
/* ==================================================================== */

function rocCatHead(string $title, string $desc, string $url, string $image, string $robots): string {
    $h  = '<title>' . rocH($title) . "</title>\n";
    $h .= '    <meta name="title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta name="description" content="' . rocH($desc) . "\" />\n";
    $h .= '    <meta name="robots" content="' . rocH($robots) . "\" />\n";
    $h .= '    <link rel="canonical" href="' . rocH($url) . "\" />\n";
    $h .= "    <meta property=\"og:type\" content=\"website\" />\n";
    $h .= '    <meta property="og:url" content="' . rocH($url) . "\" />\n";
    $h .= '    <meta property="og:title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta property="og:description" content="' . rocH($desc) . "\" />\n";
    if ($image !== '') $h .= '    <meta property="og:image" content="' . rocH(rocAbs($image)) . "\" />\n";
    $h .= "    <meta property=\"og:site_name\" content=\"Run On Console\" />\n";
    $h .= "    <meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
    $h .= "    <meta name=\"twitter:site\" content=\"@RunOnConsole\" />\n";
    $h .= '    <meta name="twitter:title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta name="twitter:description" content="' . rocH($desc) . "\" />\n";
    if ($image !== '') $h .= '    <meta name="twitter:image" content="' . rocH(rocAbs($image)) . "\" />\n";
    return $h;
}

/** FAQ block (open <details>, no JavaScript needed) + FAQPage schema. */
function rocFaqBlock(string $heading, string $sub, array $faqs): array {
    $faqs = array_values(array_filter($faqs, function ($f) { return trim((string)($f['q'] ?? '')) !== '' && trim((string)($f['a'] ?? '')) !== ''; }));
    if (!$faqs) return ['', ''];
    $html = '<section class="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-10 my-10 shadow-sm relative overflow-hidden">'
        . '<div class="relative z-10 flex items-center gap-3 mb-2 pb-4 border-b border-emerald-100"><div>'
        . '<h3 class="font-display font-extrabold text-xl sm:text-2xl text-emerald-950 leading-tight">' . rocH($heading) . '</h3>'
        . '<p class="text-xs sm:text-sm text-emerald-700 font-semibold max-w-2xl mt-0.5">' . rocH($sub) . '</p></div></div>'
        . '<div class="relative z-10 space-y-3.5 mt-6">';
    $ld = [];
    foreach ($faqs as $i => $f) {
        $html .= '<details class="rounded-2xl border border-slate-200 bg-slate-50/80 shadow-xs"' . ($i === 0 ? ' open' : '') . '>'
            . '<summary class="p-4 sm:p-5 font-display font-bold text-sm sm:text-base text-slate-900 cursor-pointer flex items-center gap-3">'
            . '<span class="w-7 h-7 rounded-xl text-xs font-extrabold flex items-center justify-center shrink-0 bg-emerald-100/80 text-emerald-900 border border-emerald-300/60">Q' . ($i + 1) . '</span>'
            . '<span>' . rocH($f['q']) . '</span></summary>'
            . '<div class="px-5 pb-5 pt-2 text-xs sm:text-sm text-slate-700 leading-relaxed border-t border-emerald-200/60 bg-white/80"><p>' . rocH($f['a']) . '</p></div></details>';
        $ld[] = ['@type' => 'Question', 'name' => (string)$f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string)$f['a']]];
    }
    $html .= '</div></section>';
    return [$html, rocJsonLd(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ld])];
}

/* ==================================================================== */
/*                              platform hub                            */
/* ==================================================================== */

function rocRenderHub(string $root, PDO $pdo, array $c, array $cats): void {
    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, ROC_CAT_TEMPLATES['hub']);

    $slug    = (string)$c['slug'];
    $title   = trim((string)$c['title']);
    $url     = rocPlatformUrl($slug);
    $desc    = trim((string)($c['description'] ?? ''));
    $image   = trim((string)($c['image'] ?? ''));
    $badge   = trim((string)($c['badge'] ?? '')) ?: 'Platform Guide';
    $era     = trim((string)($c['era'] ?? '')) ?: 'Modern & Retro';
    $focus   = rocJsonList($c['focus_json'] ?? null);
    $devices = rocGamingDevices($pdo, $slug);
    $guides  = rocPlatformGuides($pdo, $slug);
    $mod     = rocCatModified($c, $devices);
    $metaT   = trim((string)($c['meta_title'] ?? ''));
    // Titles saved with the old automatic patterns count as automatic (they are often too long).
    if (in_array($metaT, [$title . ' Hardware & Specs Hub | Run On Console', $title . ' Hardware, Devices & Games | Run On Console'], true)) $metaT = '';
    if ($metaT === '') {
        // First pattern that fits in 60 characters (Google cuts longer titles).
        foreach ([$title . ' Hardware, Devices & Games | Run On Console', $title . ' Devices & Games | Run On Console', $title . ' | Run On Console', $title] as $metaT) {
            if (mb_strlen($metaT) <= 60) break;
        }
    }
    $metaD   = trim((string)($c['meta_description'] ?? '')) ?: rocCatTruncate($desc, 155);
    [$faqHtml, $faqLd] = rocFaqBlock('FAQs About ' . $title,
        'Common questions about hardware, games and buying for ' . $title . '.', rocJsonList($c['faq_json'] ?? null));

    $h  = rocCatHead($metaT, $metaD, $url, $image, !empty($c['is_noindex']) ? 'noindex, follow' : 'index, follow, max-image-preview:large');
    $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => ROC_PUBLIC_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Gaming Platforms', 'item' => ROC_PUBLIC_URL . '/gaming-platforms/'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $url],
    ]]);
    if ($devices) {
        $items = [];
        foreach ($devices as $i => $d) $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => (string)$d['name']];
        $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $title . ' devices', 'itemListElement' => $items]);
    }
    $h .= $faqLd;
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    $m  = $mainOpen . '<div class="space-y-10 py-6 animate-page-in">';
    $m .= '<div class="flex items-center justify-between">'
        . '<a href="/gaming-platforms/" class="bg-white border border-slate-300 hover:border-emerald-600 text-slate-700 font-bold text-xs px-4 py-2.5 rounded-xl flex items-center gap-2 transition-colors shadow-sm no-underline"><span aria-hidden="true">&larr;</span><span>Back to All ' . count($cats) . ' Platforms</span></a>'
        . '<nav aria-label="Breadcrumb" class="text-xs text-slate-500 font-semibold hidden sm:flex items-center gap-1.5"><a href="/gaming-platforms/" class="text-slate-500 no-underline">Platform Directory</a><span>/</span><span class="text-emerald-700 font-extrabold">' . rocH($title) . '</span></nav></div>';

    $m .= '<div class="gradient-hero-bg text-white rounded-3xl p-6 sm:p-12 shadow-2xl relative overflow-hidden border border-emerald-500/30">'
        . '<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10"><div class="lg:col-span-7 space-y-4">'
        . '<div class="flex flex-wrap items-center gap-2"><span class="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">DEDICATED PLATFORM HUB</span>'
        . '<span class="bg-emerald-950/80 text-emerald-300 border border-emerald-500/40 text-xs font-bold px-3 py-1 rounded-full">Era: ' . rocH($era) . '</span></div>'
        . '<h1 class="font-display font-extrabold text-3xl sm:text-5xl text-white leading-tight">' . rocH($title) . '</h1>'
        . ($desc !== '' ? '<p class="text-emerald-100 text-xs sm:text-sm sm:text-base leading-relaxed max-w-lg">' . rocH($desc) . '</p>' : '');
    if ($focus) {
        $m .= '<div class="flex flex-wrap gap-2 pt-2"><span class="text-xs font-bold text-emerald-200 self-center mr-1">Focus Areas:</span>';
        foreach ($focus as $f) $m .= '<span class="badge-holo-glow text-emerald-300 text-xs font-bold px-3 py-1 rounded-xl">' . rocH($f) . '</span>';
        $m .= '</div>';
    }
    $m .= '</div><div class="lg:col-span-5"><div class="bg-slate-900/85 border-2 border-emerald-400/40 rounded-3xl p-4 shadow-2xl">'
        . ($image !== '' ? '<img width="1200" height="675" src="' . rocH($image) . '" alt="' . rocH($title) . '" class="w-full h-56 sm:h-64 object-cover rounded-2xl" fetchpriority="high"/>' : '')
        . '<div class="pt-3 flex items-center justify-between text-xs"><span class="text-emerald-300 font-bold">&#10003; ' . rocH($badge) . '</span>'
        . '<span class="text-white font-extrabold bg-emerald-600 px-2.5 py-0.5 rounded-md">' . count($devices) . ' Hardware Models Listed</span></div>'
        . '</div></div></div></div>';

    if ($devices) {
        $m .= '<section class="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">'
            . '<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-emerald-100 pb-4"><div>'
            . '<div class="flex items-center gap-2 text-emerald-700 mb-1"><span class="font-display font-extrabold text-xs uppercase tracking-wider">HARDWARE ECOSYSTEM &amp; GENERATIONS</span></div>'
            . '<h2 class="font-display font-extrabold text-2xl text-slate-900">All ' . rocH($title) . ' Models &amp; Devices</h2></div></div>'
            . '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">';
        foreach ($devices as $d) {
            $games = rocJsonList($d['iconic_games_json'] ?? null);
            $m .= '<div class="bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-400 p-4 rounded-2xl transition-all flex flex-col justify-between group shadow-2xs"><div>'
                . '<div class="flex items-start justify-between gap-2 mb-2"><h3 class="font-display font-extrabold text-sm text-slate-900 group-hover:text-emerald-700 transition-colors line-clamp-1">' . rocH($d['name']) . '</h3>'
                . (trim((string)($d['year'] ?? '')) !== '' ? '<span class="text-[9px] font-extrabold bg-emerald-600 text-white px-2 py-0.5 rounded shadow-xs shrink-0">' . rocH($d['year']) . '</span>' : '') . '</div>'
                . (trim((string)($d['specs'] ?? '')) !== '' ? '<p class="text-xs text-slate-500 font-medium mb-3 line-clamp-2 leading-relaxed">' . rocH($d['specs']) . '</p>' : '');
            if ($games) {
                $m .= '<div class="space-y-1 pt-2 border-t border-slate-200/80"><span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Iconic Games:</span><div class="flex flex-wrap gap-1">';
                foreach (array_slice($games, 0, 3) as $g) $m .= '<span class="text-[10px] bg-white border border-slate-200 text-slate-700 font-semibold px-2 py-0.5 rounded truncate max-w-[140px]">' . rocH($g) . '</span>';
                $m .= '</div></div>';
            }
            $m .= '</div>'
                . (trim((string)($d['type'] ?? '')) !== '' || trim((string)($d['status_label'] ?? '')) !== ''
                    ? '<div class="pt-3 mt-3 border-t border-slate-200/60 flex items-center justify-between text-xs font-bold text-emerald-600"><span>' . rocH($d['type'] ?? '') . '</span><span class="text-slate-500">' . rocH($d['status_label'] ?? '') . '</span></div>' : '')
                . '</div>';
        }
        $m .= '</div></section>';
    }
    if ($guides) {
        $m .= '<section class="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">'
            . '<div class="border-b border-emerald-100 pb-4"><div class="flex items-center gap-2 text-emerald-700 mb-1"><span class="font-display font-extrabold text-xs uppercase tracking-wider">GUIDES</span></div>'
            . '<h2 class="font-display font-extrabold text-2xl text-slate-900">' . rocH($title) . ' Guides</h2></div>'
            . '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">';
        foreach ($guides as $g) {
            $href = '/gaming-platforms/' . $slug . '/' . $g['slug'] . '/';
            $img = trim((string)($g['image'] ?? ''));
            $m .= '<a href="' . rocH($href) . '" class="block no-underline"><div class="game-card group flex flex-col h-full">'
                . ($img !== '' ? '<div class="relative h-40 bg-slate-900 overflow-hidden"><img width="1200" height="675" src="' . rocH($img) . '" alt="' . rocH($g['title']) . '" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"/></div>' : '')
                . '<div class="p-5 space-y-2"><h3 class="font-display font-extrabold text-base text-slate-900 group-hover:text-emerald-700 transition-colors">' . rocH($g['title']) . '</h3>'
                . (trim((string)($g['excerpt'] ?? '')) !== '' ? '<p class="text-xs text-slate-500 leading-relaxed line-clamp-2">' . rocH($g['excerpt']) . '</p>' : '')
                . '<span class="text-xs font-bold text-emerald-600">Read guide &rarr;</span></div></div></a>';
        }
        $m .= '</div></section>';
    }
    // Links to every other platform, the checker and the gear picks.
    $others = array_filter($cats, function ($o) use ($slug) { return (string)$o['slug'] !== $slug; });
    if ($others) {
        $m .= '<section class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4" aria-labelledby="other-platforms">'
            . '<h2 id="other-platforms" class="font-display font-extrabold text-xl text-slate-900">Other gaming platforms</h2>'
            . '<ul class="flex flex-wrap gap-2 list-none p-0 m-0">';
        foreach ($others as $o) {
            $m .= '<li><a href="' . rocH('/gaming-platforms/' . $o['slug'] . '/') . '" class="inline-block text-xs font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:border-emerald-500 hover:text-emerald-700 px-3 py-1.5 rounded-xl no-underline">' . rocH($o['title']) . '</a></li>';
        }
        $m .= '</ul><p class="text-xs text-slate-500 pt-2">Playing on PC? <a href="/compatibility/" class="font-bold text-emerald-700">Check if your PC can run a game</a> or see our <a href="/products/" class="font-bold text-emerald-700">gaming gear picks</a>.</p></section>';
    }
    $m .= $faqHtml . '</div>';

    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $mod);
}

/* ==================================================================== */
/*                            all platforms                             */
/* ==================================================================== */

function rocRenderPlatformList(string $root, PDO $pdo, array $cats): void {
    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, ROC_CAT_TEMPLATES['list']);
    $all = rocGamingDevices($pdo);
    $byCat = [];
    foreach ($all as $d) $byCat[(string)$d['category_slug']][] = $d;
    $n = count($cats);
    $url = ROC_PUBLIC_URL . '/gaming-platforms/';
    [$title, $desc] = rocTemplateMeta($root, ROC_CAT_TEMPLATES['list'], $n,
        'Gaming Platforms & Hardware Ecosystems | Run On Console',
        'Explore all ' . $n . ' gaming platform categories across every generation: PC and handhelds, PlayStation, Xbox, Nintendo, retro consoles, VR/AR, cloud and more.');
    $first = reset($cats);

    $h  = rocCatHead($title, $desc, $url, $first ? (string)($first['image'] ?? '') : '', 'index, follow, max-image-preview:large');
    $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => ROC_PUBLIC_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Gaming Platforms', 'item' => $url],
    ]]);
    $items = [];
    $i = 0;
    foreach ($cats as $s => $c) $items[] = ['@type' => 'ListItem', 'position' => ++$i, 'url' => rocPlatformUrl($s)];
    if ($items) $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items]);
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    $m  = $mainOpen . '<div class="space-y-12 py-6 animate-page-in">';
    $m .= '<div class="gradient-hero-bg text-white rounded-3xl p-6 sm:p-12 shadow-2xl relative overflow-hidden border border-emerald-500/30">'
        . '<div class="absolute -top-20 -left-20 w-80 h-80 bg-emerald-400/25 rounded-full blur-3xl pointer-events-none"></div>'
        . '<div class="absolute -bottom-20 -right-20 w-80 h-80 bg-teal-400/25 rounded-full blur-3xl pointer-events-none"></div>'
        . '<div class="relative z-10 space-y-4 max-w-3xl">'
        . '<span class="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">' . $n . ' GAMING PLATFORM CATEGORIES &bull; ' . count($all) . ' DEVICES</span>'
        . '<h1 class="font-display font-extrabold text-3xl sm:text-5xl text-white leading-tight">Gaming Platforms &amp; Hardware Ecosystems</h1>'
        . '<p class="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-lg">Explore all ' . $n . ' gaming platform categories across every generation: PC and handhelds, PlayStation, Xbox, Nintendo, retro consoles, VR/AR, cloud and more.</p></div></div>';

    $m .= '<section class="space-y-6"><div><h2 class="font-display font-extrabold text-2xl text-slate-900">' . $n . ' Gaming Platform Categories</h2>'
        . '<p class="text-xs sm:text-sm text-slate-500">Select any platform hub to explore its full hardware lineup and game library.</p></div>'
        . '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">';
    foreach ($cats as $s => $c) {
        $devs = $byCat[$s] ?? [];
        $img = trim((string)($c['image'] ?? ''));
        $m .= '<a href="/gaming-platforms/' . rocH($s) . '/" class="block no-underline"><div class="game-card group cursor-pointer flex flex-col justify-between h-full">'
            . '<div class="relative h-48 bg-slate-900 overflow-hidden">'
            . ($img !== '' ? '<img width="1200" height="675" src="' . rocH($img) . '" alt="' . rocH($c['title']) . '" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"/>' : '')
            . (trim((string)($c['badge'] ?? '')) !== '' ? '<span class="absolute top-3 left-3 bg-emerald-600 text-white text-[10px] font-extrabold px-2.5 py-1 rounded shadow-sm badge-glow">' . rocH($c['badge']) . '</span>' : '')
            . '<span class="absolute bottom-3 right-3 bg-slate-900/90 text-white text-[10px] font-bold px-2 py-0.5 rounded backdrop-blur-sm">' . count($devs) . ' Models Listed</span></div>'
            . '<div class="p-5 flex-1 flex flex-col justify-between space-y-4"><div>'
            . (trim((string)($c['era'] ?? '')) !== '' ? '<div class="flex items-center justify-between mb-1"><span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">' . rocH($c['era']) . '</span></div>' : '')
            . '<h3 class="font-display font-extrabold text-lg text-slate-900 group-hover:text-emerald-700 transition-colors mb-1.5">' . rocH($c['title']) . '</h3>'
            . '<p class="text-xs text-slate-500 leading-relaxed mb-3 line-clamp-2">' . rocH($c['description'] ?? '') . '</p>';
        if ($devs) {
            $m .= '<div class="flex flex-wrap gap-1.5 pt-2 border-t border-slate-100">';
            foreach (array_slice($devs, 0, 4) as $d) $m .= '<span class="bg-slate-100 text-slate-600 text-[10px] font-semibold px-2 py-0.5 rounded group-hover:bg-emerald-50 group-hover:text-emerald-700 transition-colors truncate max-w-[130px]">' . rocH($d['name']) . '</span>';
            if (count($devs) > 4) $m .= '<span class="text-[10px] text-emerald-600 font-bold px-1 py-0.5">+' . (count($devs) - 4) . ' more</span>';
            $m .= '</div>';
        }
        $m .= '</div><div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-emerald-600 group-hover:text-emerald-700"><span>Enter ' . rocH($c['title']) . ' Hub</span><span aria-hidden="true">&rarr;</span></div></div></div></a>';
    }
    $m .= '</div></section></div>';

    $latest = null;
    foreach ($cats as $s => $c) { $t = rocCatModified($c, $byCat[$s] ?? []); if ($t && (!$latest || $t > $latest)) $latest = $t; }
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $latest);
}

/* ==================================================================== */
/*                               sitemap                                */
/* ==================================================================== */

function rocGamingSitemap(PDO $pdo, array $cats): void {
    $byCat = [];
    foreach (rocGamingDevices($pdo) as $d) $byCat[(string)$d['category_slug']][] = $d;
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $out  = rocSmOpen();
    foreach ($cats as $s => $c) {
        if (!empty($c['is_noindex'])) continue;
        $out .= rocSmLine(rocPlatformUrl($s), rocCatModified($c, $byCat[$s] ?? []), (string)$c['title']);
    }
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

function rocCatTruncate(string $text, int $max): string {
    $text = trim((string)preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($text) <= $max) return $text;
    $cut = mb_substr($text, 0, $max - 1);
    $sp = mb_strrpos($cut, ' ');
    return rtrim($sp !== false && $sp > $max * 0.6 ? mb_substr($cut, 0, $sp) : $cut, " ,.;:-") . '…';
}
