<?php
/**
 * Run On Console — links from the pages Google already indexes (news, blogs, guides, "Can I run" pages)
 * to the pages it has not indexed yet (Deploy 116). Search Console (26 Oct 2026) showed about half of the
 * product pages and most comparison pages as "URL is unknown to Google".
 *
 * Every page shows 4 product pages and 2 "boost" pages chosen from its own address, so the links are spread
 * over thousands of indexed pages and every target is linked from many of them. Boost pages: the paths in the
 * CMS setting 'link_boost' (JSON list, e.g. ["/products/aoc-24g2/", "/category/gpu/"]) when it is set, else
 * every live comparison page and product category page.
 */

/** Published products by path: [path, title, image, alt]. */
function rocLinkBoostProducts(PDO $pdo): array {
    static $pool = null;
    if ($pool !== null) return $pool;
    $pool = [];
    try {
        $rows = $pdo->query("SELECT slug, title, image, image_alt FROM products WHERE status = 'published' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) $pool['/products/' . $r['slug'] . '/'] = ['/products/' . $r['slug'] . '/', (string)$r['title'], (string)($r['image'] ?? ''), (string)($r['image_alt'] ?? '')];
    } catch (\Throwable $e) {}
    return $pool;
}

function rocLinkBoostSetting(PDO $pdo, string $key) {
    try {
        $st = $pdo->prepare('SELECT setting_value FROM cms_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([$key]);
        return json_decode((string)$st->fetchColumn(), true);
    } catch (\Throwable $e) { return null; }
}

/** The boost pages: [path, title, image, alt]. */
function rocLinkBoostTargets(PDO $pdo): array {
    static $list = null;
    if ($list !== null) return $list;
    $list = [];
    $prod = rocLinkBoostProducts($pdo);
    $name = function (string $path) use ($prod) {
        if (isset($prod[$path])) return $prod[$path];
        if (preg_match('#^/compare/(.+)-vs-(.+)/$#', $path, $m)) {
            $a = $prod['/products/' . $m[1] . '/'] ?? null; $b = $prod['/products/' . $m[2] . '/'] ?? null;
            if ($a && $b) return [$path, $a[1] . ' vs ' . $b[1], $a[2], $a[1] . ' and ' . $b[1]];
            return null;
        }
        $t = ucwords(str_replace('-', ' ', trim(basename($path), '/')));
        return [$path, $t, '', $t];
    };
    $paths = array_filter((array)rocLinkBoostSetting($pdo, 'link_boost'), function ($p) { return is_string($p) && preg_match('#^/[a-z0-9/_-]+/$#i', $p); });
    if (!$paths) {
        // Default: every live comparison (a written verdict that is not hidden) and every product category.
        foreach ((array)rocLinkBoostSetting($pdo, 'compare_texts') as $slug => $t) {
            if (is_array($t) && !empty($t['summary']) && empty($t['hidden'])) $paths[] = '/compare/' . $slug . '/';
        }
        try {
            foreach ($pdo->query("SELECT slug, name FROM product_categories WHERE status = 'published' ORDER BY sort_rank, name")->fetchAll(PDO::FETCH_ASSOC) as $c)
                $list['/category/' . $c['slug'] . '/'] = ['/category/' . $c['slug'] . '/', 'Best ' . $c['name'], '', (string)$c['name']];
        } catch (\Throwable $e) {}
    }
    foreach (array_unique($paths) as $p) { $x = $name($p); if ($x) $list[$p] = $x; }
    return $list;
}

/** For the page $seed (its path): 4 products and 2 boost pages, a different set on every page. */
function rocLinkBoostPick(PDO $pdo, string $seed, int $products = 4, int $boost = 2): array {
    $h = (int)sprintf('%u', crc32($seed));
    $out = [];
    $t = array_values(rocLinkBoostTargets($pdo));
    for ($i = 0; $i < min($boost, count($t)); $i++) { $x = $t[($h + $i * 3) % count($t)]; if ($x[0] !== $seed) $out[$x[0]] = $x; }
    $p = array_values(rocLinkBoostProducts($pdo));
    $c = count($p);
    $want = count($out) + $products;
    for ($i = 0; count($out) < $want && $i < $c * 2; $i++) {
        $x = $p[($h + $i * 7) % $c];          // steps of 7: products of one category do not all land on one page
        if ($x[0] !== $seed) $out[$x[0]] = $x;
    }
    return array_values($out);
}

/** The box: small cards with picture and name, styled for day and night. */
function rocLinkBoostBox(PDO $pdo, string $seed, string $title = 'Gaming gear on Run On Console'): string {
    $items = rocLinkBoostPick($pdo, $seed);
    if (!$items) return '';
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $h = '<style>.lb-box{margin-top:2rem;border:1px solid #e2e8f0;border-radius:1.25rem;padding:1.1rem 1.25rem;background:#fff}'
       . '.lb-box h2{font:800 1.15rem Outfit,Inter,sans-serif;color:#0f172a;margin:0 0 .8rem}.lb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.6rem}'
       . '.lb-grid a{display:flex;gap:.7rem;align-items:center;border:1px solid #e2e8f0;border-radius:.9rem;padding:.55rem;text-decoration:none;color:#0f172a;transition:border-color .2s,background .2s;min-height:66px}'
       . '.lb-grid a:hover{border-color:#10b981;background:#f0fdf4}.lb-grid img{width:52px;height:52px;object-fit:contain;background:#fff;border-radius:.6rem;flex:none}'
       . '.lb-grid i{width:52px;height:52px;border-radius:.6rem;flex:none;display:grid;place-items:center;background:#ecfdf5;color:#047857;font:900 .8rem Outfit,sans-serif;font-style:normal}'
       . '.lb-grid b{font:700 .85rem/1.3 Inter,sans-serif;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.lb-more{display:inline-block;margin-top:.7rem;font-weight:800;font-size:.85rem;color:#047857}'
       . 'html.roc-dark .lb-box{background:#0f172a;border-color:#1e293b}html.roc-dark .lb-box h2,html.roc-dark .lb-grid a{color:#f8fafc}html.roc-dark .lb-grid a{border-color:#1e293b}'
       . 'html.roc-dark .lb-grid a:hover{background:rgba(16,185,129,.1);border-color:#10b981}html.roc-dark .lb-grid i{background:rgba(16,185,129,.15);color:#6ee7b7}html.roc-dark .lb-more{color:#6ee7b7}</style>'
       . '<section class="lb-box"><h2>' . $e($title) . '</h2><div class="lb-grid">';
    foreach ($items as $p) {
        $pic = $p[2] !== '' ? '<img src="' . $e($p[2]) . '" alt="' . $e($p[3] !== '' ? $p[3] : $p[1]) . '" width="52" height="52" loading="lazy">'
                            : '<i aria-hidden="true">' . (strpos($p[0], '/compare/') === 0 ? 'VS' : 'TOP') . '</i>';
        $h .= '<a href="' . $e($p[0]) . '">' . $pic . '<b>' . $e($p[1]) . '</b></a>';
    }
    return $h . '</div><a class="lb-more" href="/products/">All gaming gear &rarr;</a> &nbsp; <a class="lb-more" href="/compare/">All comparisons &rarr;</a></section>';
}
