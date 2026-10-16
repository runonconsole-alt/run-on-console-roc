<?php
/**
 * Run On Console — "X vs Y" comparison pages (Deploy 87), at /compare/{a}-vs-{b}/.
 *
 * Pairs: in every product category, the top 4 products (CMS order) against each other = 6 a category.
 * Each pair gets a written verdict (the product writer's models, same honest rules as the product
 * texts: facts from the products' own data, no prices, no hands-on claims); a page is shown only once
 * its verdict is written. product-render.php draws the pages, the products sitemap lists them.
 *
 *   rocComparePairs($pdo)            [pairSlug => [a row, b row, category slug]]
 *   rocCompareTexts($pdo)            the written verdicts (cms_settings compare_texts)
 *   rocCompareTick($pdo, $n)         the scheduler: writes the next $n verdicts (at most 20 a day)
 */
if (defined('ROC_COMPARE_LIB')) return;
define('ROC_COMPARE_LIB', 1);
require_once __DIR__ . '/content-writer-lib.php';

const ROC_COMPARE_TOP = 4;
const ROC_COMPARE_DAILY = 20;

function rocCompareSlug(string $a, string $b): string { return $a . '-vs-' . $b; }

/** Every pair, best products first. */
function rocComparePairs(PDO $pdo): array {
    static $pairs = null;
    if ($pairs !== null) return $pairs;
    $pairs = [];
    $byCat = [];
    try {
        foreach ($pdo->query("SELECT * FROM products WHERE status = 'published' ORDER BY sort_rank, title")->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $c = (string)($p['category_slug'] ?? '');
            if ($c === '' || !empty($p['is_noindex'])) continue;
            if (count($byCat[$c] ?? []) < ROC_COMPARE_TOP) $byCat[$c][] = $p;
        }
    } catch (\Throwable $e) { return $pairs; }
    foreach ($byCat as $c => $list) {
        for ($i = 0; $i < count($list); $i++) for ($j = $i + 1; $j < count($list); $j++)
            $pairs[rocCompareSlug((string)$list[$i]['slug'], (string)$list[$j]['slug'])] = [$list[$i], $list[$j], $c];
    }
    return $pairs;
}

function rocCompareTexts(PDO $pdo): array {
    $t = rocBlogGet($pdo, 'compare_texts', []);
    return is_array($t) ? $t : [];
}

function rocComparePrompt(array $a, array $b, string $catName): string {
    $one = function (array $p) {
        return ['name' => $p['title'], 'brand' => $p['brand'] ?? '', 'specs' => json_decode((string)($p['specs_json'] ?? ''), true) ?: new stdClass(),
                'features' => json_decode((string)($p['features_json'] ?? ''), true) ?: [], 'best_for' => $p['best_for'] ?? '',
                'pros' => json_decode((string)($p['pros'] ?? ''), true) ?: [], 'cons' => json_decode((string)($p['cons'] ?? ''), true) ?: [],
                'about' => mb_substr(trim((string)preg_replace('/\n## Common questions.*$/s', '', (string)($p['summary'] ?? ''))), 0, 1500)];
    };
    return "Write the comparison of two products for a page on Run On Console, an independent gaming hardware site. The reader is choosing between them.\n\n"
        . "DATA:\n" . json_encode(['category' => $catName, 'product_a' => $one($a), 'product_b' => $one($b)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
        . ROC_CONTENT_RULES
        . "- Be fair to both. Do not invent a winner where DATA does not show one: say which kind of player each one suits.\n"
        . "\nAnswer with one JSON object with these keys:\n"
        . "- summary: 2-3 sentences (40-80 words), the short answer: how they differ and who should pick which.\n"
        . "- differences: 4-6 short items, the differences that matter, each naming both products.\n"
        . "- pick_a: 1-2 sentences, who should buy product_a (start with its name).\n"
        . "- pick_b: 1-2 sentences, who should buy product_b (start with its name).\n"
        . "- faq: 3 objects {\"q\": ..., \"a\": ...}: questions buyers comparing these two search (which is better for X, the main difference, worth the upgrade). Each answer 1-3 sentences; if DATA does not settle it, say what to check on the listing.\n";
}

/** Writes the verdict of one pair. */
function rocCompareWrite(PDO $pdo, string $slug, array $pair): array {
    [$a, $b, $cat] = $pair;
    $cn = '';
    try { $st = $pdo->prepare('SELECT name FROM product_categories WHERE slug = ?'); $st->execute([$cat]); $cn = (string)$st->fetchColumn(); } catch (\Throwable $e) {}
    $r = rocContentAsk($pdo, rocComparePrompt($a, $b, $cn !== '' ? $cn : $cat), ['summary', 'differences', 'pick_a', 'pick_b']);
    if (!$r['ok']) return $r;
    $x = $r['a'];
    $faq = [];
    foreach ((array)($x['faq'] ?? []) as $f) {
        $q = trim((string)(is_array($f) ? ($f['q'] ?? '') : '')); $an = trim((string)(is_array($f) ? ($f['a'] ?? '') : ''));
        if ($q !== '' && $an !== '' && count($faq) < 4) $faq[] = ['q' => rocContentClean(mb_substr(rtrim($q, '?') . '?', 0, 160)), 'a' => rocContentClean(mb_substr($an, 0, 600))];
    }
    $texts = rocCompareTexts($pdo);
    $old = (array)($texts[$slug] ?? []);
    $texts[$slug] = ['summary' => rocContentClean(mb_substr(trim((string)$x['summary']), 0, 800)),
        'differences' => array_map('rocContentClean', rocContentList($x['differences'], 6, 260)),
        'pick_a' => rocContentClean(mb_substr(trim((string)$x['pick_a']), 0, 400)), 'pick_b' => rocContentClean(mb_substr(trim((string)$x['pick_b']), 0, 400)),
        'faq' => $faq, 't' => time()] + array_intersect_key($old, ['hidden' => 1]);
    rocBlogSet($pdo, 'compare_texts', $texts);
    return ['ok' => true];
}

/** The scheduler: the next pairs without a verdict (2 a run, at most ROC_COMPARE_DAILY a day). */
function rocCompareTick(PDO $pdo, int $n = 2): ?string {
    if ((string)rocBlogGet($pdo, 'compare_auto', 'on') === 'off' || !rocBlogWriters($pdo)) return null;
    $day = gmdate('Y-m-d');
    $c = rocBlogGet($pdo, 'compare_daily', []); $c = (is_array($c) && ($c['day'] ?? '') === $day) ? $c : ['day' => $day, 'n' => 0];
    if ($c['n'] >= ROC_COMPARE_DAILY) return null;
    $texts = rocCompareTexts($pdo);
    $todo = array_diff_key(rocComparePairs($pdo), $texts);
    if (!$todo) return null;
    $done = [];
    foreach (array_slice($todo, 0, min($n, ROC_COMPARE_DAILY - $c['n']), true) as $slug => $pair) {
        $r = rocCompareWrite($pdo, (string)$slug, $pair);
        $c['n']++;
        if (!$r['ok']) { rocBlogSet($pdo, 'compare_daily', $c); return "compare: {$slug} not written ({$r['error']})"; }
        $done[] = $slug;
    }
    rocBlogSet($pdo, 'compare_daily', $c);
    return 'comparisons written: ' . implode(', ', $done) . ' (' . (count($todo) - count($done)) . ' left)';
}
