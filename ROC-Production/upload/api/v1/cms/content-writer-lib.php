<?php
/**
 * Run On Console — "About" texts for product pages and gaming platform pages, written on the server
 * with the blog writer key (Groq / Gemini, CMS > Blog & news writer).
 *
 * Written to be useful to a buyer, not to fill a page (Google: helpful, people-first content; no
 * scaled low-value pages): only facts from the product's own data or facts the writer is sure of for
 * that exact model, who it suits and who it does not, honest drawbacks, no prices, no hype words,
 * no claims of hands-on testing. Every text can be edited in the CMS afterwards.
 *
 *   rocContentQueue($pdo)              products / platforms that still have a thin page
 *   rocContentWriteProduct($pdo, $row) writes one product (About, pros, cons, short description, best for)
 *   rocContentWritePlatform($pdo, $row) writes one platform (about_text)
 *   rocContentTick($pdo, $n)           the next $n items, at most ROC_CONTENT_DAILY a day (scheduler)
 */
if (defined('ROC_CONTENT_LIB')) return;
define('ROC_CONTENT_LIB', 1);
require_once __DIR__ . '/blog-engine-lib.php';

const ROC_CONTENT_DAILY = 40;   // a day, so the blog and news writers keep their share of the writer key
const ROC_CONTENT_BANNED = ['elevate', 'unleash', 'game-changer', 'game changer', 'in today\'s', 'fast-paced world', 'look no further',
    'take your gaming to the next level', 'whether you\'re a casual', 'delve', 'seamless', 'cutting-edge', 'unparalleled', 'revolutionary',
    'we tested', 'in our testing', 'our tests', 'hands-on', 'we used it'];

/** A product page is thin when it has no About text of its own yet. */
function rocContentThin(array $p): bool {
    $s = trim((string)($p['summary'] ?? ''));
    return mb_strlen($s) < 400 || $s === trim((string)($p['short_desc'] ?? ''));
}

/** Pages already written once (never written again by the queue; --slug writes one again). */
function rocContentDone(PDO $pdo, ?string $type = null, ?string $slug = null): array {
    $d = rocBlogGet($pdo, 'content_done', []);
    $d = is_array($d) ? $d + ['product' => [], 'platform' => []] : ['product' => [], 'platform' => []];
    if ($type !== null && $slug !== null) { $d[$type][$slug] = gmdate('Y-m-d'); rocBlogSet($pdo, 'content_done', $d); }
    return $d;
}

function rocContentQueue(PDO $pdo): array {
    $q = ['products' => [], 'platforms' => []];
    $done = rocContentDone($pdo);
    foreach ($pdo->query("SELECT * FROM products WHERE status = 'published' ORDER BY sort_rank, title")->fetchAll(PDO::FETCH_ASSOC) as $p)
        if (!isset($done['product'][$p['slug']]) && rocContentThin($p)) $q['products'][] = $p;
    // Platforms only once their about_text column exists (cli-content-writer.php --apply adds it).
    if (in_array('about_text', rocBlogCols($pdo, 'gaming_categories'), true)) try {
        foreach ($pdo->query("SELECT * FROM gaming_categories WHERE status = 'published' ORDER BY sort_rank")->fetchAll(PDO::FETCH_ASSOC) as $c)
            if (!isset($done['platform'][$c['slug']]) && mb_strlen(trim((string)($c['about_text'] ?? ''))) < 400) $q['platforms'][] = $c;
    } catch (\Throwable $e) { /* about_text column not added yet (cli-content-writer.php --apply adds it) */ }
    return $q;
}

const ROC_CONTENT_RULES = "Writing rules (follow all):\n"
    . "- Write for a gamer deciding whether to buy. Plain, specific English. No hype, no filler, no marketing words (never use: elevate, unleash, game-changer, delve, seamless, cutting-edge, unparalleled, revolutionary, look no further).\n"
    . "- Facts: use the DATA below. Add a fact about this exact model only if you are certain it is true for this exact model. If you are not sure about a detail, do not state it; describe what matters in this kind of product and what to check on the listing instead. Never invent numbers.\n"
    . "- Never say you or we tested, used or reviewed it hands-on. Never write a price, a discount or a currency amount.\n"
    . "- Tips and gear advice stay general and safe. Do not claim a specific feature, port, mode, storage type or compatibility unless it is in DATA.
"
    . "- Be honest: name real trade-offs and say who should pick something else.\n"
    . "- Original wording only. Do not copy text from Amazon or the maker's site.\n";

function rocContentProductPrompt(array $p, string $catName, string $groupName): string {
    $data = ['name' => $p['title'], 'brand' => $p['brand'] ?? '', 'category' => $catName, 'group' => $groupName,
             'specs' => json_decode((string)($p['specs_json'] ?? ''), true) ?: new stdClass(),
             'features' => json_decode((string)($p['features_json'] ?? ''), true) ?: [],
             'best_for' => $p['best_for'] ?? '', 'short_line' => $p['subtitle'] ?? '', 'description' => $p['short_desc'] ?? ''];
    return "Write the buyer's guide text for one product page on Run On Console, an independent gaming hardware site.\n\n"
        . "DATA:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n" . ROC_CONTENT_RULES
        . "\nAnswer with one JSON object with these keys:\n"
        . "- short_desc: 1-2 sentences, 120-155 characters, what it is and who it is for (this is also the Google description).\n"
        . "- overview: 2 short paragraphs (80-140 words in total) on what the product is and what it does well. Use the category name \"{$catName}\" once.\n"
        . "- design: 1 paragraph (40-80 words) on how it looks and is built, only what you are sure of.\n"
        . "- uses: 3-5 short items, where and how it fits (setups, games, players).\n"
        . "- skip_if: 1-2 sentences, who should choose something else.\n"
        . "- before_you_buy: 1-2 sentences, what to check on the listing (version, size, compatibility).\n"
        . "- pros: 3-5 short items. cons: 2-3 short honest items.\n"
        . "- best_for: a short phrase under 40 characters, like \"Competitive FPS players\".\n";
}

function rocContentPlatformPrompt(array $c, array $devices, array $cats): string {
    $data = ['platform' => $c['title'], 'era' => $c['era'] ?? '', 'summary' => $c['description'] ?? '',
             'devices_on_the_page' => array_slice(array_column($devices, 'name'), 0, 20), 'focus' => json_decode((string)($c['focus_json'] ?? ''), true) ?: [],
             'gear_categories_on_the_site' => $cats];
    return "Write the guide text for one gaming platform page on Run On Console, an independent gaming hardware site.\n\n"
        . "DATA:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n" . ROC_CONTENT_RULES
        . "\nAnswer with one JSON object with these keys:\n"
        . "- overview: 2 short paragraphs (90-150 words) on what this platform is, its history in brief and why people play on it today.\n"
        . "- choosing: 1 paragraph (50-90 words) on how to choose a device on this platform (what differs between models).\n"
        . "- gear: 3-5 short items, accessories or gear that matter for this platform (use the gear categories given when they fit).\n"
        . "- tips: 2-4 short practical tips for players on this platform.\n";
}

/** Plain characters: the live database stores Western text only (a non-breaking hyphen became "?"). */
function rocContentClean(string $s): string {
    $s = strtr($s, ["\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-', "\u{2212}" => '-', "\u{00A0}" => ' ', "\u{202F}" => ' ', "\u{2009}" => ' ',
        "\u{2007}" => ' ', "\u{200B}" => '', "\u{2248}" => '~', "\u{2264}" => '<=', "\u{2265}" => '>=', "\u{2192}" => '->', "\u{2026}" => '...']);
    return (string)preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{00FF}\x{2013}\x{2014}\x{2018}\x{2019}\x{201C}\x{201D}\x{2022}\x{20AC}]/u', '', $s);
}

/** Checks a written text: no banned words, no prices. Returns an error or ''. */
function rocContentCheck(string $all): string {
    $l = mb_strtolower($all);
    foreach (ROC_CONTENT_BANNED as $w) if (strpos($l, $w) !== false) return "used \"{$w}\"";
    if (preg_match('/[$€£₹]\s?\d|\b\d+(\.\d+)?\s?(usd|dollars|rs|pkr)\b/i', $all)) return 'wrote a price';
    return '';
}

function rocContentList($v, int $max, int $len = 160): array {
    $l = is_array($v) ? $v : preg_split('/\n/', (string)$v);
    $l = array_values(array_filter(array_map(function ($x) use ($len) { return mb_substr(trim(ltrim(is_array($x) ? implode(' ', $x) : (string)$x, "-• \t")), 0, $len); }, $l), 'strlen'));
    return array_slice($l, 0, $max);
}

/** Asks the writer twice at most (the second time with the reason the first answer was refused). */
function rocContentAsk(PDO $pdo, string $prompt, array $keys): array {
    $why = ''; $waits = 0;
    for ($try = 0; $try < 2; $try++) {
        $r = rocBlogWriteAny($pdo, $prompt . ($why !== '' ? "\nYour last answer was refused because it {$why}. Fix that." : ''));
        // Free key: a limit per minute. On the command line, wait as long as the writer asks (at most a minute) and ask again.
        if (!$r['ok'] && PHP_SAPI === 'cli' && $waits < 3 && preg_match('/rate limit|try again in/i', (string)$r['error'])) {
            $s = 20;
            if (preg_match('/try again in ([\d.]+)\s*(ms|s|m)/i', (string)$r['error'], $mm)) $s = (int)ceil((float)$mm[1] / ($mm[2] === 'ms' ? 1000 : 1) * ($mm[2] === 'm' ? 60 : 1)) + 2;
            sleep(min(65, max(2, $s))); $waits++; $try--; continue;
        }
        if (!$r['ok']) return $r;
        $a = $r['article'];
        foreach ($keys as $k) if (empty($a[$k])) { $why = "had no {$k}"; continue 2; }
        $why = rocContentCheck(json_encode($a, JSON_UNESCAPED_UNICODE));
        if ($why === '') return ['ok' => true, 'a' => $a];
    }
    return ['ok' => false, 'error' => 'the writer ' . $why];
}

function rocContentWriteProduct(PDO $pdo, array $p, string $catName, string $groupName): array {
    $r = rocContentAsk($pdo, rocContentProductPrompt($p, $catName, $groupName), ['short_desc', 'overview', 'pros', 'cons']);
    if (!$r['ok']) return $r;
    $a = $r['a'];
    $about = trim((string)(is_array($a['overview']) ? implode("\n\n", $a['overview']) : $a['overview']));
    if (!empty($a['design'])) $about .= "\n\n## Design\n" . trim((string)$a['design']);
    $uses = rocContentList($a['uses'] ?? [], 5);
    if ($uses) $about .= "\n\n## Where it fits\n- " . implode("\n- ", $uses);
    $skip = trim((string)($a['skip_if'] ?? '')); $tip = trim((string)($a['before_you_buy'] ?? ''));
    if ($skip !== '' || $tip !== '') $about .= "\n\n## Before you buy\n" . trim($tip . ($tip !== '' && $skip !== '' ? "\n\n" : '') . $skip);
    $set = ['summary' => rocContentClean(mb_substr($about, 0, 12000)),
            'pros' => json_encode(array_map('rocContentClean', rocContentList($a['pros'], 5)), JSON_UNESCAPED_UNICODE),
            'cons' => json_encode(array_map('rocContentClean', rocContentList($a['cons'], 3)), JSON_UNESCAPED_UNICODE)];
    $sd = rocContentClean(trim((string)$a['short_desc']));
    $old = trim((string)($p['short_desc'] ?? ''));
    // Replace the old automatic sentence ("... See the full specifications ...") or an empty one only.
    if ($sd !== '' && ($old === '' || stripos($old, 'See the full specifications') !== false || mb_strlen($old) < 60)) $set['short_desc'] = mb_substr($sd, 0, 300);
    if (trim((string)($p['best_for'] ?? '')) === '' && !empty($a['best_for'])) $set['best_for'] = rocContentClean(mb_substr(trim((string)$a['best_for']), 0, 60));
    $set['updated_at'] = gmdate('Y-m-d H:i:s');
    $set['content_modified_at'] = $set['updated_at'];
    $cols = rocBlogCols($pdo, 'products');
    $set = array_intersect_key($set, array_flip($cols));
    $pdo->prepare('UPDATE products SET ' . implode(', ', array_map(function ($c) { return "{$c} = ?"; }, array_keys($set))) . ' WHERE slug = ?')
        ->execute([...array_values($set), $p['slug']]);
    rocContentDone($pdo, 'product', (string)$p['slug']);
    return ['ok' => true];
}

function rocContentWritePlatform(PDO $pdo, array $c): array {
    $devices = [];
    try { $st = $pdo->prepare("SELECT name FROM gaming_devices WHERE category_slug = ? AND status = 'published'"); $st->execute([$c['slug']]); $devices = $st->fetchAll(PDO::FETCH_ASSOC); } catch (\Throwable $e) {}
    $cats = [];
    try { $cats = $pdo->query("SELECT name FROM product_categories WHERE status = 'published'")->fetchAll(PDO::FETCH_COLUMN); } catch (\Throwable $e) {}
    $r = rocContentAsk($pdo, rocContentPlatformPrompt($c, $devices, $cats), ['overview', 'choosing']);
    if (!$r['ok']) return $r;
    $a = $r['a'];
    $t = trim((string)(is_array($a['overview']) ? implode("\n\n", $a['overview']) : $a['overview']));
    $t .= "\n\n## How to choose a " . $c['title'] . " device\n" . trim((string)$a['choosing']);
    $gear = rocContentList($a['gear'] ?? [], 5); if ($gear) $t .= "\n\n## Gear that matters\n- " . implode("\n- ", $gear);
    $tips = rocContentList($a['tips'] ?? [], 4); if ($tips) $t .= "\n\n## Tips for players\n- " . implode("\n- ", $tips);
    $pdo->prepare('UPDATE gaming_categories SET about_text = ?, content_modified_at = ?, updated_at = ? WHERE slug = ?')
        ->execute([rocContentClean(mb_substr($t, 0, 12000)), gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'), $c['slug']]);
    rocContentDone($pdo, 'platform', (string)$c['slug']);
    return ['ok' => true];
}

/** Category name and main-category name of a product. */
function rocContentNames(PDO $pdo, array $p): array {
    static $cats = null;
    if ($cats === null) { $cats = []; foreach ($pdo->query('SELECT slug, name FROM product_categories')->fetchAll(PDO::FETCH_ASSOC) as $c) $cats[$c['slug']] = $c['name']; }
    $cs = (string)($p['category_slug'] ?? '');
    require_once __DIR__ . '/product-groups-lib.php';
    $saved = rocBlogGet($pdo, 'product_groups', null);
    $pc = (is_array($saved) && isset($saved['pc-hardware']['members'])) ? (array)$saved['pc-hardware']['members'] : ROC_PRODUCT_GROUPS['pc-hardware']['members'];
    $g = in_array($cs, $pc, true) ? 'pc-hardware' : 'gaming-hardware';
    $gName = (is_array($saved) && !empty($saved[$g]['name'])) ? $saved[$g]['name'] : ROC_PRODUCT_GROUPS[$g]['name'];
    return [$cats[$cs] ?? (string)($p['category'] ?? ''), $gName];
}

/** Writes the next $n thin pages (platforms first: there are few). Returns a log line or null. */
function rocContentTick(PDO $pdo, int $n): ?string {
    if ((string)rocBlogGet($pdo, 'content_auto', 'on') === 'off' || !rocBlogWriters($pdo)) return null;
    $day = gmdate('Y-m-d');
    $c = rocBlogGet($pdo, 'content_daily', []);
    $c = (is_array($c) && ($c['day'] ?? '') === $day) ? $c : ['day' => $day, 'n' => 0];
    if ($c['n'] >= ROC_CONTENT_DAILY) return null;
    $q = rocContentQueue($pdo);
    $items = array_merge(array_map(function ($x) { return ['platform', $x]; }, $q['platforms']), array_map(function ($x) { return ['product', $x]; }, $q['products']));
    if (!$items) return null;
    $done = [];
    foreach (array_slice($items, 0, min($n, ROC_CONTENT_DAILY - $c['n'])) as [$type, $row]) {
        if ($type === 'platform') $r = rocContentWritePlatform($pdo, $row);
        else { [$cn, $gn] = rocContentNames($pdo, $row); $r = rocContentWriteProduct($pdo, $row, $cn, $gn); }
        $c['n']++;
        if (!$r['ok']) { rocBlogSet($pdo, 'content_daily', $c); return "content: {$row['slug']} failed ({$r['error']}); stopped for now"; }
        $done[] = $row['slug'];
    }
    rocBlogSet($pdo, 'content_daily', $c);
    return 'content written: ' . implode(', ', $done) . ' (' . (count($items) - count($done)) . ' left)';
}
