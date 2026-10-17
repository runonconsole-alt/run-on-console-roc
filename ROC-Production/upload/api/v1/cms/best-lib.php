<?php
/**
 * Run On Console — "Best of" lists on the category pages (Deploy 90).
 *
 * The category pages are already "Best {category}: N picks" in Google, so they become the real best-of
 * list (no new /best/ pages competing with them): "Our top picks" with an award each (Best overall,
 * Best budget, Best for FPS ...) and why, "How to choose", and the questions buyers ask (FAQPage).
 * The writer picks only from the category's own products and uses their own data (the product
 * writer's honest rules: no prices, no hands-on claims). Saved in cms_settings best_texts {cat: {...}}.
 *
 *   rocBestTexts($pdo)             the written lists
 *   rocBestWrite($pdo, $catSlug)   writes the list of one category
 *   rocBestTick($pdo)              the scheduler: the next category without a list (1 a run), rewritten after 30 days
 */
if (defined('ROC_BEST_LIB')) return;
define('ROC_BEST_LIB', 1);
require_once __DIR__ . '/content-writer-lib.php';

const ROC_BEST_REFRESH = 30 * 86400;   // a list is written again after 30 days (new products, new order)

function rocBestTexts(PDO $pdo): array { $t = rocBlogGet($pdo, 'best_texts', []); return is_array($t) ? $t : []; }

/** Published products of a category, CMS order. */
function rocBestProducts(PDO $pdo, string $cat): array {
    try {
        $st = $pdo->prepare("SELECT * FROM products WHERE status = 'published' AND category_slug = ? ORDER BY sort_rank, title");
        $st->execute([$cat]);
        return array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC), function ($p) { return empty($p['is_noindex']); }));
    } catch (\Throwable $e) { return []; }
}

function rocBestPrompt(string $catName, array $products): string {
    $list = array_map(function ($p) {
        return ['id' => $p['slug'], 'name' => $p['title'], 'brand' => $p['brand'] ?? '', 'best_for' => $p['best_for'] ?? '',
                'specs' => json_decode((string)($p['specs_json'] ?? ''), true) ?: new stdClass(),
                'pros' => array_slice(json_decode((string)($p['pros'] ?? ''), true) ?: [], 0, 3), 'cons' => array_slice(json_decode((string)($p['cons'] ?? ''), true) ?: [], 0, 2)];
    }, array_slice($products, 0, 30));
    return "Write the \"best {$catName}\" buying list for a category page on Run On Console, an independent gaming hardware site.\n\n"
        . "DATA (the only products you may pick, by id):\n" . json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n" . ROC_CONTENT_RULES
        . "- Pick only products from DATA, by their exact id. Each product at most once. Awards must fit what DATA says about the product.\n"
        . "\nAnswer with one JSON object with these keys:\n"
        . "- intro: 2-3 sentences (40-80 words) on what makes a good {$catName} for gamers and how this list is made (from each product's specs and listing, no hands-on testing).\n"
        . "- picks: 3-5 objects {\"id\": ..., \"award\": a short award like \"Best overall\", \"Best budget\", \"Best for FPS\", \"Best wireless\" (each award different), \"why\": 2 sentences (30-60 words) why it earns it}.\n"
        . "- how_to_choose: 4-5 short items, what to look at when buying (general and true for this category).\n"
        . "- faq: 3 objects {\"q\": ..., \"a\": ...}: questions people search about choosing {$catName}. Each answer 1-3 sentences.\n";
}

function rocBestWrite(PDO $pdo, string $cat): array {
    $products = rocBestProducts($pdo, $cat);
    if (count($products) < 3) return ['ok' => false, 'error' => 'fewer than 3 products'];
    $name = $cat;
    try { $st = $pdo->prepare('SELECT name FROM product_categories WHERE slug = ?'); $st->execute([$cat]); $name = (string)$st->fetchColumn() ?: $cat; } catch (\Throwable $e) {}
    $r = rocContentAsk($pdo, rocBestPrompt($name, $products), ['intro', 'picks']);
    if (!$r['ok']) return $r;
    $x = $r['a'];
    $ids = array_flip(array_map(function ($p) { return (string)$p['slug']; }, $products));
    $picks = []; $seen = []; $awards = [];
    foreach ((array)$x['picks'] as $p) {
        $id = (string)(is_array($p) ? ($p['id'] ?? '') : '');
        $award = trim((string)(is_array($p) ? ($p['award'] ?? '') : ''));
        if (!isset($ids[$id]) || isset($seen[$id]) || $award === '' || isset($awards[strtolower($award)])) continue;   // only real products of this category
        $seen[$id] = 1; $awards[strtolower($award)] = 1;
        $picks[] = ['id' => $id, 'award' => rocContentClean(mb_substr($award, 0, 40)), 'why' => rocContentClean(mb_substr(trim((string)($p['why'] ?? '')), 0, 400))];
        if (count($picks) >= 5) break;
    }
    if (count($picks) < 2) return ['ok' => false, 'error' => 'the writer picked products that are not in this category'];
    $faq = [];
    foreach ((array)($x['faq'] ?? []) as $f) {
        $q = trim((string)(is_array($f) ? ($f['q'] ?? '') : '')); $a = trim((string)(is_array($f) ? ($f['a'] ?? '') : ''));
        if ($q !== '' && $a !== '' && count($faq) < 4) $faq[] = ['q' => rocContentClean(mb_substr(rtrim($q, '?') . '?', 0, 160)), 'a' => rocContentClean(mb_substr($a, 0, 600))];
    }
    $texts = rocBestTexts($pdo);
    $texts[$cat] = ['intro' => rocContentClean(mb_substr(trim((string)$x['intro']), 0, 700)), 'picks' => $picks,
                    'how' => array_map('rocContentClean', rocContentList($x['how_to_choose'] ?? [], 5, 220)), 'faq' => $faq, 't' => time()]
                 + array_intersect_key((array)($texts[$cat] ?? []), ['hidden' => 1]);
    rocBlogSet($pdo, 'best_texts', $texts);
    return ['ok' => true];
}

/** The scheduler: one category a run (a new one first, then the oldest list after 30 days). */
function rocBestTick(PDO $pdo): ?string {
    if ((string)rocBlogGet($pdo, 'best_auto', 'on') === 'off' || !rocBlogWriters($pdo)) return null;
    $day = gmdate('Y-m-d');
    $c = rocBlogGet($pdo, 'best_daily', []); $c = (is_array($c) && ($c['day'] ?? '') === $day) ? $c : ['day' => $day, 'n' => 0];
    if ($c['n'] >= 8) return null;
    $texts = rocBestTexts($pdo);
    $cats = [];
    try { $cats = $pdo->query("SELECT slug FROM product_categories WHERE status = 'published' ORDER BY sort_rank, name")->fetchAll(PDO::FETCH_COLUMN); } catch (\Throwable $e) {}
    $next = null;
    foreach ($cats as $s) if (!isset($texts[$s]) && count(rocBestProducts($pdo, (string)$s)) >= 3) { $next = (string)$s; break; }
    if ($next === null) foreach ($cats as $s) if (isset($texts[$s]) && empty($texts[$s]['edited']) && time() - (int)($texts[$s]['t'] ?? 0) > ROC_BEST_REFRESH) { $next = (string)$s; break; }
    if ($next === null) return null;
    $r = rocBestWrite($pdo, $next);
    $c['n']++; rocBlogSet($pdo, 'best_daily', $c);
    return $r['ok'] ? "best-of list written: {$next}" : "best-of {$next} not written ({$r['error']})";
}
