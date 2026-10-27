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
    $h = rocLinkBoostCss() . '<section class="lb-box"><h2>' . $e($title) . '</h2><div class="lb-grid">';
    foreach ($items as $p) $h .= rocLinkBoostCard($p);
    return $h . '</div><a class="lb-more" href="/products/">All gaming gear &rarr;</a> &nbsp; <a class="lb-more" href="/compare/">All comparisons &rarr;</a></section>';
}

/** The style of the link boxes, once a page. */
function rocLinkBoostCss(): string {
    static $done = false;
    if ($done) return '';
    $done = true;
    return '<style>.lb-box{margin-top:2rem;border:1px solid #e2e8f0;border-radius:1.25rem;padding:1.1rem 1.25rem;background:#fff}'
       . '.lb-box h2{font:800 1.15rem Outfit,Inter,sans-serif;color:#0f172a;margin:0 0 .8rem}.lb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.6rem}'
       . '.lb-grid a{display:flex;gap:.7rem;align-items:center;border:1px solid #e2e8f0;border-radius:.9rem;padding:.55rem;text-decoration:none;color:#0f172a;transition:border-color .2s,background .2s;min-height:66px}'
       . '.lb-grid a:hover{border-color:#10b981;background:#f0fdf4}.lb-grid img{width:52px;height:52px;object-fit:contain;background:#fff;border-radius:.6rem;flex:none}'
       . '.lb-grid i{width:52px;height:52px;border-radius:.6rem;flex:none;display:grid;place-items:center;background:#ecfdf5;color:#047857;font:900 .8rem Outfit,sans-serif;font-style:normal}'
       . '.lb-grid b{font:700 .85rem/1.3 Inter,sans-serif;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.lb-more{display:inline-block;margin-top:.7rem;font-weight:800;font-size:.85rem;color:#047857}'
       . 'html.roc-dark .lb-box{background:#0f172a;border-color:#1e293b}html.roc-dark .lb-box h2,html.roc-dark .lb-grid a{color:#f8fafc}html.roc-dark .lb-grid a{border-color:#1e293b}'
       . 'html.roc-dark .lb-grid a:hover{background:rgba(16,185,129,.1);border-color:#10b981}html.roc-dark .lb-grid i{background:rgba(16,185,129,.15);color:#6ee7b7}html.roc-dark .lb-more{color:#6ee7b7}'
       . '.lb-top{margin:0;padding:.9rem 1rem}.lb-top h2{font-size:.95rem;margin-bottom:.6rem}.lb-top .lb-grid{grid-template-columns:repeat(auto-fill,minmax(200px,1fr))}</style>';
}

/** One card: [path, title, image, alt]. */
function rocLinkBoostCard(array $p): string {
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $pic = $p[2] !== '' ? '<img src="' . $e($p[2]) . '" alt="' . $e($p[3] !== '' ? $p[3] : $p[1]) . '" width="52" height="52" loading="lazy">'
                        : '<i aria-hidden="true">' . (strpos($p[0], '/compare/') === 0 ? 'VS' : 'TOP') . '</i>';
    return '<a href="' . $e($p[0]) . '">' . $pic . '<b>' . $e($p[1]) . '</b></a>';
}

/**
 * Owner, 2026-10-27 (Deploy 126): the products Google has not found yet go near the TOP of every blog, news and
 * guide post (under the summary), so a crawler that reads the start of the page reaches them. The October audit
 * found most product pages "URL is unknown to Google" or "Discovered - currently not indexed".
 * Products not indexed = every published product without a PASS in CMS > Indexing (roc_index_status), so the
 * ones not checked yet count too: [path => [path, title, image, alt, category slug, category name]].
 */
function rocLinkBoostUnindexed(PDO $pdo): array {
    static $list = null;
    if ($list !== null) return $list;
    $list = []; $ok = [];
    try {
        foreach ($pdo->query("SELECT url FROM roc_index_status WHERE kind = 'product' AND verdict = 'PASS'")->fetchAll(PDO::FETCH_COLUMN) as $u)
            $ok[(string)parse_url((string)$u, PHP_URL_PATH)] = 1;
    } catch (\Throwable $e) {}
    try {
        foreach ($pdo->query("SELECT slug, title, image, image_alt, category_slug, category FROM products WHERE status = 'published' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $path = '/products/' . $r['slug'] . '/';
            if (!isset($ok[$path])) $list[$path] = [$path, (string)$r['title'], (string)($r['image'] ?? ''), (string)($r['image_alt'] ?? ''),
                                                    (string)($r['category_slug'] ?? ''), mb_strtolower((string)($r['category'] ?? ''))];
        }
    } catch (\Throwable $e) {}
    return $list;
}

/**
 * The top strip for a post: ROC_TOP_LINKS products Google has not indexed, the post's own product category
 * first (topic_cat), then the ones whose name or category shares a word with the title, then a different set
 * on every post (so all of them get links). Products the text already links are left out.
 */
const ROC_TOP_LINKS = 3;
function rocLinkBoostTop(PDO $pdo, string $seed, array $post, string $body = ''): string {
    $pool = rocLinkBoostUnindexed($pdo);
    if (!$pool) return '';
    $cat = (string)($post['topic_cat'] ?? '');
    $words = [];
    foreach (preg_split('/[^a-z0-9]+/', mb_strtolower((string)($post['title'] ?? '') . ' ' . (string)($post['focus_keyword'] ?? ''))) as $w)
        if (strlen($w) > 3 && !in_array($w, ['best', 'gaming', 'review', 'worth', 'buying', 'which', 'should', 'with', 'your', 'from', 'that', 'this', 'what', 'guide', '2026', '2027'], true)) $words[rtrim($w, 's')] = 1;
    $h = (int)sprintf('%u', crc32($seed));
    $rank = [];
    foreach ($pool as $path => $x) {
        if ($body !== '' && strpos($body, $path . '"') !== false) continue;
        $score = ($cat !== '' && $x[4] === $cat) ? 10 : 0;
        $name = mb_strtolower($x[1]) . ' ' . $x[5];
        foreach ($words as $w => $_) if (strpos($name, $w) !== false) $score += 2;
        $rank[$path] = [$score, (int)sprintf('%u', crc32($path . $h))];
    }
    uksort($rank, function ($a, $b) use ($rank) { return [$rank[$b][0], $rank[$a][1]] <=> [$rank[$a][0], $rank[$b][1]]; });
    $items = array_slice(array_keys($rank), 0, ROC_TOP_LINKS);
    if (!$items) return '';
    $out = rocLinkBoostCss() . '<section class="lb-box lb-top"><h2>Gaming gear to check</h2><div class="lb-grid">';
    foreach ($items as $path) $out .= rocLinkBoostCard($pool[$path]);
    return $out . '</div></section>';
}

/**
 * Links inside the text of blog, news and guide posts (Deploy 120): the first time a post names one of our
 * pages, the words become a link to it, at most ROC_TOPIC_LINKS a post, each page once, never the post itself
 * and never inside a heading or another link. In this order:
 *   1. pages Google has not indexed yet (link_boost, CMS > Indexing), by their searches, name or category words;
 *   2. what people search on Google for a page (Search Console, gsc-lib.php): the search becomes the anchor text;
 *   3. product names, then category words ("gaming monitors" -> /category/monitors/).
 */
const ROC_TOPIC_LINKS = 5;
const ROC_TOPIC_CAT_WORDS = ['gpu' => ['graphics cards', 'graphics card'], 'monitors' => ['gaming monitors', 'gaming monitor'],
    'mice' => ['gaming mice', 'gaming mouse'], 'keyboards' => ['gaming keyboards', 'gaming keyboard', 'mechanical keyboard'],
    'audio' => ['gaming headsets', 'gaming headset'], 'speakers' => ['pc speakers', 'gaming speakers'], 'cpu' => ['gaming cpus', 'gaming cpu', 'gaming processor'],
    'ram' => ['ram kits', 'ram kit', 'ddr5 ram', 'ddr4 ram'], 'cases' => ['pc cases', 'pc case'], 'chairs' => ['gaming chairs', 'gaming chair'],
    'handhelds' => ['gaming handhelds', 'handheld gaming pc'], 'laptops' => ['gaming laptops', 'gaming laptop']];

/** [phrase (lower case) => path] lists, in priority order. */
function rocTopicPhrases(PDO $pdo): array {
    static $groups = null;
    if ($groups !== null) return $groups;
    $ok = function (string $p) { return (bool)preg_match('#^/(products|category|compare|blogs|guides|can-i-run|gaming-platforms)/[a-z0-9/_-]*$#', $p); };
    $anchors = [];
    // The searches each page is shown for (gsc_queries, written daily by cms/gsc-lib.php, then the remembered ones
    // in gsc_query_mem, Deploy 126): 2-6 words, 4 a page.
    $gq = rocLinkBoostSetting($pdo, 'gsc_queries');
    $gm = rocLinkBoostSetting($pdo, 'gsc_query_mem');
    $qrows = array_merge((is_array($gq) && is_array($gq['rows'] ?? null)) ? $gq['rows'] : [], is_array($gm) ? array_values($gm) : []);
    foreach ($qrows as $r) {
        if (!is_array($r) || !isset($r['p'])) continue;
        if (in_array((string)($r['q'] ?? ''), $anchors[(string)$r['p']] ?? [], true)) continue;
        $q = (string)($r['q'] ?? ''); $w = count(preg_split('/\s+/', trim($q)));
        if ($w < 2 || $w > 6 || preg_match('/run ?on ?console|\broc\b|site:|https?:|\.com/', $q)) continue;
        if (count($anchors[$r['p']] ?? []) < 4) $anchors[(string)$r['p']][] = $q;
    }
    $byPath = [];
    foreach ($anchors as $path => $qs) if ($ok($path)) foreach ($qs as $q) $byPath[$path][] = $q;
    foreach (rocLinkBoostProducts($pdo) as $path => $p) if (mb_strlen($p[1]) >= 8) $byPath[$path][] = mb_strtolower($p[1]);
    foreach (ROC_TOPIC_CAT_WORDS as $slug => $words) foreach ($words as $w) $byPath['/category/' . $slug . '/'][] = $w;
    $boost = array_flip(array_filter((array)rocLinkBoostSetting($pdo, 'link_boost'), 'is_string'));
    $g = [[], [], [], []];
    foreach ($byPath as $path => $phrases) foreach ($phrases as $i => $ph) {
        $ph = trim((string)preg_replace('/\s+/', ' ', $ph));
        if (mb_strlen($ph) < 5) continue;
        $isQuery = isset($anchors[$path]) && in_array($ph, $anchors[$path], true);
        $k = isset($boost[$path]) ? 0 : ($isQuery ? 1 : (strpos($path, '/products/') === 0 ? 2 : 3));
        if (!isset($g[$k][$ph])) $g[$k][$ph] = $path;
    }
    // Longest first inside a group, so "rtx 4060 ti" wins over "rtx 4060"; in chunks (regex size).
    $groups = [];
    foreach ($g as $list) {
        uksort($list, function ($a, $b) { return mb_strlen($b) <=> mb_strlen($a); });
        foreach (array_chunk($list, 200, true) as $chunk)
            $groups[] = ['/(?<![\w\-])(' . implode('|', array_map(function ($x) { return preg_quote($x, '/'); }, array_keys($chunk))) . ')(?![\w\-])/iu', $chunk];
    }
    return $groups;
}

function rocTopicLinks(PDO $pdo, string $html, string $self): string {
    if ($html === '') return $html;
    $groups = rocTopicPhrases($pdo);
    if (!$groups) return $html;
    preg_match_all('~href="(?:https://runonconsole\.com)?(/[^"#?]*)~', $html, $m);
    $used = array_flip($m[1]); $used[$self] = 1;
    $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $count = 0; $links = [];
    foreach ($groups as [$re, $chunk]) {
        if ($count >= ROC_TOPIC_LINKS) break;
        $skip = 0;
        foreach ($parts as $k => $part) {
            if ($part === '') continue;
            if ($part[0] === '<') {
                if (preg_match('#^<(/?)(a|h[1-6]|code|pre|figcaption|button|script|style|table|blockquote)\b#i', $part, $t)) $skip = max(0, $skip + ($t[1] === '/' ? -1 : 1));
                continue;
            }
            if ($skip > 0 || $count >= ROC_TOPIC_LINKS) continue;
            $parts[$k] = (string)preg_replace_callback($re, function ($x) use ($chunk, &$used, &$count, &$links) {
                $path = $chunk[mb_strtolower($x[1])] ?? null;
                if ($path === null || isset($used[$path]) || $count >= ROC_TOPIC_LINKS) return $x[0];
                $used[$path] = 1; $count++;
                $links[] = '<a href="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '">' . $x[1] . '</a>';
                return "\x01" . (count($links) - 1) . "\x02";      // a placeholder: later phrases must not match inside it
            }, $part);
        }
    }
    if (!$links) return $html;
    return (string)preg_replace_callback('/\x01(\d+)\x02/', function ($x) use ($links) { return $links[(int)$x[1]]; }, implode('', $parts));
}
