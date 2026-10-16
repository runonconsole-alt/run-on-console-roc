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
 *   rocContentDeepenProduct($pdo, $row) adds "Who it is for", setup tips and buyer questions to a page
 *                                      written before Deploy 78 (new pages get them in the first write)
 *   rocContentTick($pdo, $n)           the next $n items, at most ROC_CONTENT_DAILY a day (scheduler)
 *
 * A page the writer fails on twice goes to the end of the line, so one bad answer never blocks the rest.
 */
if (defined('ROC_CONTENT_LIB')) return;
define('ROC_CONTENT_LIB', 1);
require_once __DIR__ . '/blog-engine-lib.php';

const ROC_CONTENT_DAILY = 40;   // a day, so the blog and news writers keep their share of the writer key
const ROC_CONTENT_BANNED = ['elevate', 'unleash', 'game-changer', 'game changer', 'in today\'s', 'fast-paced world', 'look no further',
    'take your gaming to the next level', 'whether you\'re a casual', 'delve', 'seamless', 'cutting-edge', 'unparalleled', 'revolutionary',
    'we tested', 'in our testing', 'our tests', 'hands-on', 'we used it', 'in real-world use', 'in real world use',
    // the writer talking about its instructions (Deploy 79: "isn't listed in the provided data")
    'provided data', 'the data provided', 'in the data', 'data does not', "data doesn't", 'the given data', 'information provided', 'provided information'];

/** A product page is thin when it has no About text of its own yet. */
function rocContentThin(array $p): bool {
    $s = trim((string)($p['summary'] ?? ''));
    return mb_strlen($s) < 400 || $s === trim((string)($p['short_desc'] ?? ''));
}

/** The buyer questions section inside the About text (Deploy 78); product-render.php shows it as the FAQ. */
const ROC_CONTENT_FAQ_HEAD = '## Common questions';

/** A written page from before Deploy 78: it has About text but no buyer questions yet. */
function rocContentShallow(array $p): bool {
    return !rocContentThin($p) && strpos((string)($p['summary'] ?? ''), ROC_CONTENT_FAQ_HEAD) === false;
}

/** How often the writer failed on a page: [slug => n]. $add = true counts one more failure. */
function rocContentFails(PDO $pdo, ?string $slug = null, bool $add = false): array {
    $f = rocBlogGet($pdo, 'content_fail', []);
    $f = is_array($f) ? $f : [];
    if ($slug !== null && $add) { $f[$slug] = (int)($f[$slug] ?? 0) + 1; rocBlogSet($pdo, 'content_fail', $f); }
    return $f;
}

/** Pages already written once (never written again by the queue; --slug writes one again). */
function rocContentDone(PDO $pdo, ?string $type = null, ?string $slug = null): array {
    $d = rocBlogGet($pdo, 'content_done', []);
    $d = is_array($d) ? $d + ['product' => [], 'platform' => []] : ['product' => [], 'platform' => []];
    if ($type !== null && $slug !== null) { $d[$type][$slug] = gmdate('Y-m-d'); rocBlogSet($pdo, 'content_done', $d); }
    return $d;
}

function rocContentQueue(PDO $pdo): array {
    $q = ['products' => [], 'platforms' => [], 'deepen' => []];
    $done = rocContentDone($pdo);
    $fails = rocContentFails($pdo);
    foreach ($pdo->query("SELECT * FROM products WHERE status = 'published' ORDER BY sort_rank, title")->fetchAll(PDO::FETCH_ASSOC) as $p) {
        if ((int)($fails[$p['slug']] ?? 0) >= 4) continue;   // the writer keeps failing on it: write it in the CMS (or --slug=)
        if (!isset($done['product'][$p['slug']]) && rocContentThin($p)) $q['products'][] = $p;
        elseif (rocContentShallow($p)) $q['deepen'][] = $p;
    }
    // Pages that failed before go last.
    $late = function (array $l) use ($fails) { usort($l, function ($a, $b) use ($fails) { return ((int)($fails[$a['slug']] ?? 0) >= 2) <=> ((int)($fails[$b['slug']] ?? 0) >= 2); }); return $l; };
    $q['products'] = $late($q['products']); $q['deepen'] = $late($q['deepen']);
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
    . "- Original wording only. Do not copy text from Amazon or the maker's site.\n"
    . "- Write to the reader. Never mention DATA, \"the data\", \"the information provided\" or these rules; when a detail is unknown, tell the reader to check the listing for it.\n";

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
        . "- best_for: a short phrase under 40 characters, like \"Competitive FPS players\".\n"
        . ROC_CONTENT_DEEP_KEYS;
}

/** The parts added in Deploy 78 (asked in the first write, or on their own for older pages). */
const ROC_CONTENT_DEEP_KEYS = "- who_for: 2-3 sentences (40-80 words): the player and setup this product suits best, and why (from DATA).\n"
    . "- setup_tips: 3-4 short practical items for the first days with it (setup, settings, care); general and safe, no feature that is not in DATA.\n"
    . "- faq: 3-4 objects {\"q\": ..., \"a\": ...}: questions buyers of this exact product really search (fit for a game type, a console or PC, size, noise, upgrade, a comparison with a well-known rival). Each answer 1-3 sentences (25-70 words). If DATA does not settle it, say what to check on the listing instead of guessing.\n";

function rocContentDeepenPrompt(array $p, string $catName, string $groupName): string {
    $data = ['name' => $p['title'], 'brand' => $p['brand'] ?? '', 'category' => $catName, 'group' => $groupName,
             'specs' => json_decode((string)($p['specs_json'] ?? ''), true) ?: new stdClass(),
             'features' => json_decode((string)($p['features_json'] ?? ''), true) ?: [],
             'best_for' => $p['best_for'] ?? '', 'description' => $p['short_desc'] ?? '',
             'text_already_on_the_page' => mb_substr((string)($p['summary'] ?? ''), 0, 2500)];
    return "Add to the buyer's guide of one product page on Run On Console, an independent gaming hardware site. Do not repeat what text_already_on_the_page says; add new, useful points.\n\n"
        . "DATA:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n" . ROC_CONTENT_RULES
        . "\nAnswer with one JSON object with these keys:\n" . ROC_CONTENT_DEEP_KEYS;
}

/** "Who it is for", setup tips and the buyer questions as About text sections ('' when the answer has none). */
function rocContentDeepText(array $a): string {
    $t = '';
    $who = trim((string)(is_array($a['who_for'] ?? '') ? implode(' ', $a['who_for']) : ($a['who_for'] ?? '')));
    if ($who !== '') $t .= "\n\n## Who it is for\n" . $who;
    $tips = rocContentList($a['setup_tips'] ?? [], 4, 220);
    if ($tips) $t .= "\n\n## Setup tips\n- " . implode("\n- ", $tips);
    $qa = [];
    foreach ((array)($a['faq'] ?? []) as $f) {
        $q = trim((string)(is_array($f) ? ($f['q'] ?? $f['question'] ?? '') : ''));
        $an = trim((string)(is_array($f) ? ($f['a'] ?? $f['answer'] ?? '') : ''));
        $q = (string)preg_replace('/\s+/', ' ', $q); $an = (string)preg_replace('/\s+/', ' ', $an);
        if ($q !== '' && $an !== '' && count($qa) < 4) $qa[] = 'Q: ' . mb_substr(rtrim($q, '?') . '?', 0, 160) . "\nA: " . mb_substr($an, 0, 600);
    }
    if ($qa) $t .= "\n\n" . ROC_CONTENT_FAQ_HEAD . "\n\n" . implode("\n\n", $qa);
    return $t;
}

/** Adds the Deploy 78 parts to a page written before (the rest of its text stays as it is). */
function rocContentDeepenProduct(PDO $pdo, array $p, string $catName, string $groupName): array {
    $r = rocContentAsk($pdo, rocContentDeepenPrompt($p, $catName, $groupName), ['who_for', 'faq']);
    if (!$r['ok']) return $r;
    $add = rocContentDeepText($r['a']);
    if (strpos($add, ROC_CONTENT_FAQ_HEAD) === false) return ['ok' => false, 'error' => 'the writer gave no usable questions'];
    $now = gmdate('Y-m-d H:i:s');
    $set = ['summary' => rocContentClean(mb_substr(rtrim((string)$p['summary']) . $add, 0, 14000)), 'updated_at' => $now, 'content_modified_at' => $now];
    $set = array_intersect_key($set, array_flip(rocBlogCols($pdo, 'products')));
    $pdo->prepare('UPDATE products SET ' . implode(', ', array_map(function ($c) { return "{$c} = ?"; }, array_keys($set))) . ' WHERE slug = ?')
        ->execute([...array_values($set), $p['slug']]);
    return ['ok' => true];
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

/**
 * The database connection, opened again when the server closed it. The live MySQL closes an idle
 * connection while the writer waits for the free key's limit; reading the key then failed quietly
 * ("No writer key saved", Deploy 79).
 */
function rocContentDb(PDO $pdo): PDO {
    try { $pdo->query('SELECT 1'); return $pdo; } catch (\Throwable $e) {}
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) return $pdo;
    try {
        $n = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $n->exec("SET time_zone = '+00:00'");
        return $n;
    } catch (\Throwable $e) { return $pdo; }
}

/**
 * Groq models for the product and platform texts (Deploy 80). Groq counts the daily tokens of each
 * model on its own, so these texts no longer use up the blog and news model (openai/gpt-oss-120b,
 * 200k tokens a day). A model that is not on the key is skipped; one at its limit waits until Groq
 * says it is free again (content_model_wait).
 */
const ROC_CONTENT_MODELS = ['llama-3.3-70b-versatile', 'moonshotai/kimi-k2-instruct-0905', 'moonshotai/kimi-k2-instruct', 'meta-llama/llama-4-maverick-17b-128e-instruct'];

/** Seconds Groq asks to wait ("try again in 20m41.5s" -> 1242). */
function rocContentRetryIn(string $err): int {
    if (!preg_match('/try again in\s*((?:[\d.]+h)?(?:[\d.]+m(?!s))?(?:[\d.]+s)?(?:[\d.]+ms)?)/i', $err, $m) || $m[1] === '') return 60;
    $s = 0.0;
    if (preg_match('/([\d.]+)h/', $m[1], $x)) $s += (float)$x[1] * 3600;
    if (preg_match('/([\d.]+)m(?!s)/', $m[1], $x)) $s += (float)$x[1] * 60;
    if (preg_match('/([\d.]+)s/', (string)preg_replace('/[\d.]+ms/', '', $m[1]), $x)) $s += (float)$x[1];
    if (preg_match('/([\d.]+)ms/', $m[1], $x)) $s += (float)$x[1] / 1000;
    return (int)ceil($s) + 2;
}

/** One answer from the content models: ['ok' => true, 'article' => [...]] or an error ('limit' => seconds when all wait). */
function rocContentWrite(PDO $pdo, string $prompt): array {
    $key = trim((string)rocBlogGet($pdo, 'blog_groq_key', ''));
    if ($key === '') return rocBlogWriteAny($pdo, $prompt);   // Gemini only
    $wait = rocBlogGet($pdo, 'content_model_wait', []); $wait = is_array($wait) ? $wait : [];
    $now = time(); $soonest = null; $errors = [];
    foreach (ROC_CONTENT_MODELS as $model) {
        if ((int)($wait[$model] ?? 0) > $now) { $soonest = min($soonest ?? PHP_INT_MAX, (int)$wait[$model] - $now); continue; }
        $r = rocGroqCall($key, 'POST', '/chat/completions', ['model' => $model, 'temperature' => 0.6, 'max_tokens' => 4000, 'response_format' => ['type' => 'json_object'],
            'messages' => [['role' => 'system', 'content' => 'You are an expert gaming hardware writer. Answer with one JSON object only.'], ['role' => 'user', 'content' => $prompt]]]);
        if ($r['ok']) {
            $text = trim((string)preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim((string)($r['data']['choices'][0]['message']['content'] ?? ''))));
            $a = json_decode($text, true);
            if (is_array($a)) return ['ok' => true, 'article' => $a, 'model' => $model];
            $errors[] = "{$model}: no JSON"; continue;
        }
        $e = (string)$r['error'];
        if ($r['code'] === 429 || stripos($e, 'rate limit') !== false) {
            $s = rocContentRetryIn($e);
            if ($s > 90) { $wait[$model] = $now + $s; rocBlogSet($pdo, 'content_model_wait', $wait); }   // a daily limit: skip this model until then
            $soonest = min($soonest ?? PHP_INT_MAX, $s);
            $errors[] = "{$model}: rate limit"; continue;
        }
        $errors[] = "{$model}: {$e}";   // not on this key (404/400) or another error: the next model
    }
    $out = ['ok' => false, 'error' => 'Groq: ' . ($errors ? implode(' | ', $errors) : 'every content model is at its limit')];
    if ($soonest !== null) $out['limit'] = $soonest;
    return $out;
}

/** Asks the writer twice at most (the second time with the reason the first answer was refused). */
function rocContentAsk(PDO &$pdo, string $prompt, array $keys): array {
    $why = ''; $waits = 0;
    for ($try = 0; $try < 2; $try++) {
        $pdo = rocContentDb($pdo);
        $r = rocContentWrite($pdo, $prompt . ($why !== '' ? "\nYour last answer was refused because it {$why}. Fix that." : ''));
        $pdo = rocContentDb($pdo);   // the answer can take a minute: save with a live connection
        // Free key: a limit per minute. On the command line, wait as long as the writer asks (at most a minute) and ask again.
        // A limit per minute: wait (at most a minute) and ask again. A daily limit: stop, the scheduler goes on later.
        if (!$r['ok'] && PHP_SAPI === 'cli' && $waits < 3 && isset($r['limit']) && $r['limit'] <= 65) {
            sleep(max(2, (int)$r['limit'])); $waits++; $try--; continue;
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
    $about .= rocContentDeepText($a);
    $set = ['summary' => rocContentClean(mb_substr($about, 0, 14000)),
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

/** The queue as one list: platforms (few), then thin product pages, then older pages to deepen. */
function rocContentItems(array $q): array {
    return array_merge(array_map(function ($x) { return ['platform', $x]; }, $q['platforms']),
        array_map(function ($x) { return ['product', $x]; }, $q['products']), array_map(function ($x) { return ['deepen', $x]; }, $q['deepen'] ?? []));
}

function rocContentRun(PDO &$pdo, string $type, array $row): array {
    $pdo = rocContentDb($pdo);
    if ($type === 'platform') return rocContentWritePlatform($pdo, $row);
    [$cn, $gn] = rocContentNames($pdo, $row);
    return $type === 'deepen' ? rocContentDeepenProduct($pdo, $row, $cn, $gn) : rocContentWriteProduct($pdo, $row, $cn, $gn);
}

/** Writes the next $n thin pages (platforms first: there are few). Returns a log line or null. */
function rocContentTick(PDO $pdo, int $n): ?string {
    if ((string)rocBlogGet($pdo, 'content_auto', 'on') === 'off' || !rocBlogWriters($pdo)) return null;
    $day = gmdate('Y-m-d');
    $c = rocBlogGet($pdo, 'content_daily', []);
    $c = (is_array($c) && ($c['day'] ?? '') === $day) ? $c : ['day' => $day, 'n' => 0];
    if ($c['n'] >= ROC_CONTENT_DAILY) return null;
    $q = rocContentQueue($pdo);
    $items = rocContentItems($q);
    if (!$items) return null;
    $done = [];
    foreach (array_slice($items, 0, min($n, ROC_CONTENT_DAILY - $c['n'])) as [$type, $row]) {
        $r = rocContentRun($pdo, $type, $row);
        $c['n']++;
        if (!$r['ok']) { rocBlogSet($pdo, 'content_daily', $c); if (!isset($r['limit'])) rocContentFails($pdo, (string)$row['slug'], true); return "content: {$row['slug']} failed ({$r['error']}); stopped for now"; }
        $done[] = $row['slug'];
    }
    rocBlogSet($pdo, 'content_daily', $c);
    return 'content written: ' . implode(', ', $done) . ' (' . (count($items) - count($done)) . ' left)';
}
