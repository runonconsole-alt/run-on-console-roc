<?php
/**
 * Run On Console — one "Can I run it?" page per PC game (Deploy 65).
 *
 *   /can-i-run/                 every game, most popular first, then A to Z   (game-render.php)
 *   /can-i-run/{slug}/          one game: official requirements, a quick check, the cards and
 *                               processors that run it, upgrade picks, FAQ    (game-render.php?slug=...)
 *   /sitemaps/games-sitemap.xml                                                (game-render.php?sitemap=1)
 *
 * The games are the checker's Steam list (roc-steam-games.json, official Steam requirements, written by
 * scripts/fetch-steam-games.mjs). Scores use the same scale as /compatibility/ and ROC (agent-lib.php).
 * Software on Steam (Blender, Godot, benchmark tools ...) and titles without Latin letters are left out.
 */
if (defined('ROC_GAME_PAGES_LIB')) return;
define('ROC_GAME_PAGES_LIB', 1);
require_once __DIR__ . '/agent-lib.php';

const ROC_GP_SOFTWARE = '/Utilities|Design & Illustration|Animation & Modeling|Video Production|Audio Production|Photo Editing|Software Training|Web Publishing|Game Development|Education/';

/** Graphics cards and processors people own, used for "which card runs it" and the quick check. */
const ROC_GP_GPUS = ['Intel UHD / Iris Xe (integrated)', 'GeForce GT 1030', 'GeForce GTX 750 Ti', 'GeForce GTX 960', 'GeForce GTX 1050 Ti',
    'Radeon RX 570', 'GeForce GTX 1650', 'GeForce GTX 1060 6GB', 'Radeon RX 580', 'GeForce RTX 3050', 'GeForce GTX 1660 Super',
    'GeForce GTX 1070', 'GeForce GTX 1080', 'GeForce RTX 2060', 'Radeon RX 5600 XT', 'Radeon RX 6600', 'Intel Arc A750',
    'GeForce RTX 3060', 'GeForce RTX 4060', 'Radeon RX 7600', 'Intel Arc B580', 'GeForce RTX 3070', 'Radeon RX 6700 XT',
    'GeForce RTX 5060', 'GeForce RTX 4070', 'Radeon RX 7800 XT', 'GeForce RTX 5070', 'GeForce RTX 3090', 'Radeon RX 9070 XT',
    'GeForce RTX 4080', 'GeForce RTX 5080', 'GeForce RTX 4090'];
const ROC_GP_CPUS = ['Intel Core 2 Quad Q6600', 'AMD FX-8350', 'Intel Core i3-6100', 'Intel Core i5-4460', 'AMD Ryzen 5 1600',
    'Intel Core i3-10100', 'AMD Ryzen 5 2600', 'Intel Core i5-8400', 'AMD Ryzen 5 3600', 'Intel Core i7-8700', 'Intel Core i5-10400',
    'AMD Ryzen 5 5600', 'Intel Core i5-12400', 'AMD Ryzen 7 3700X', 'Intel Core i7-10700', 'AMD Ryzen 5 7600', 'AMD Ryzen 7 5800X',
    'Intel Core i7-12700', 'Intel Core i9-12900K', 'AMD Ryzen 7 7800X3D'];

/** Parts of the lists above that are still sold new, for upgrade advice. */
const ROC_GP_IN_SHOPS = ['GeForce RTX 3050', 'GeForce RTX 3060', 'GeForce RTX 4060', 'GeForce RTX 5060', 'GeForce RTX 4070', 'GeForce RTX 5070',
    'GeForce RTX 5080', 'Radeon RX 6600', 'Radeon RX 7600', 'Radeon RX 6700 XT', 'Radeon RX 7800 XT', 'Radeon RX 9070 XT', 'Intel Arc B580',
    'Intel Core i3-10100', 'AMD Ryzen 5 5600', 'Intel Core i5-12400', 'AMD Ryzen 5 7600', 'AMD Ryzen 7 5800X', 'Intel Core i7-12700',
    'Intel Core i9-12900K', 'AMD Ryzen 7 7800X3D'];

/** "Baldur's Gate 3" -> "baldurs-gate-3", "Sid Meier’s Civilization® VI" -> "sid-meiers-civilization-vi". */
function rocGpSlug(string $t): string {
    $t = str_replace(['™', '®', '©', "'", '’', '‘', '`'], '', $t);
    // é -> e, ö -> o (the same as the checker page's JavaScript). Not iconv: on the server it turns them into "?".
    if (class_exists('Normalizer')) { $n = \Normalizer::normalize($t, \Normalizer::FORM_KD); if (is_string($n)) $t = (string)preg_replace('/\p{Mn}+/u', '', $n); }
    else $t = strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'É' => 'E', 'Ö' => 'O', 'Ü' => 'U', 'Ä' => 'A']);
    $t = str_replace(["'", '"', '`', '^', '~'], '', strtolower($t));
    $t = str_replace('&', ' and ', $t);
    return trim((string)preg_replace('/[^a-z0-9]+/', '-', $t), '-');
}

/** The game's name as shown on the page (no ™ ® ©). */
function rocGpName(string $t): string {
    return trim((string)preg_replace('/\s+/u', ' ', str_replace(['™', '®', '©'], '', $t)));
}

/** Every game with a page: [slug => game], most popular first; 'updated' is the data date. */
function rocGpAll(string $root): array {
    static $all = null;
    if ($all !== null) return $all;
    $all = [];
    $d = json_decode((string)@file_get_contents($root . '/roc-steam-games.json'), true);
    $seen = [];
    foreach ((array)($d['games'] ?? []) as $g) {
        $id = (int)($g['appid'] ?? 0);
        if ($id <= 0 || isset($seen[$id]) || empty($g['req']) || empty($g['min'])) continue;
        if (preg_match(ROC_GP_SOFTWARE, (string)($g['g'] ?? '')) || preg_match('/benchmark tool/i', (string)$g['t'])) continue;
        $slug = rocGpSlug((string)$g['t']);
        if (!preg_match('/[a-z]/', $slug) || strlen($slug) < 2) continue;   // only non-Latin letters
        $first = $slug;
        if (isset($all[$slug])) $slug .= '-' . $id;
        $seen[$id] = 1;
        $g['slug'] = $slug;
        $g['name'] = rocGpName((string)$g['t']);
        $g['rank'] = count($all) + 1;
        // Two Steam games with one name (DOOM 3 and its re-release): both names get their Steam year,
        // so the pages do not share a title.
        if ($slug !== $first && !empty($g['y']) && !empty($all[$first]['y']) && (int)$g['y'] !== (int)$all[$first]['y']) {
            if (strpos($all[$first]['name'], ' (') === false) $all[$first]['name'] .= ' (' . (int)$all[$first]['y'] . ')';
            $g['name'] .= ' (' . (int)$g['y'] . ')';
        }
        $all[$slug] = $g;
    }
    // Games hidden in CMS > Games (Deploy 85): no page, not in the lists, the sitemap or "similar games".
    foreach ((array)($GLOBALS['ROC_GP_HIDDEN'] ?? []) as $h) unset($all[$h]);
    return $all;
}

function rocGpUpdated(string $root): int {
    $t = 0;
    if (preg_match('/"updated"\s*:\s*"([^"]+)"/', (string)@file_get_contents($root . '/roc-steam-games.json', false, null, 0, 600), $m)) $t = (int)strtotime($m[1]);
    return $t ?: (int)@filemtime($root . '/roc-steam-games.json');
}

function rocGpPath(array $g): string { return '/can-i-run/' . $g['slug'] . '/'; }

/** The page of a Steam app id, or null. */
function rocGpByAppid(string $root, int $appid): ?array {
    foreach (rocGpAll($root) as $g) if ((int)$g['appid'] === $appid) return $g;
    return null;
}

/** [name => score] for the reference graphics cards or processors, weakest first. */
function rocGpRefs(string $kind): array {
    static $c = [];
    if (isset($c[$kind])) return $c[$kind];
    $out = [];
    foreach ($kind === 'gpu' ? ROC_GP_GPUS : ROC_GP_CPUS as $n) {
        $s = $kind === 'gpu' ? rocAgentGpuScore($n) : rocAgentCpuScore($n);
        if ($s !== null) $out[$n] = $s;
    }
    asort($out);
    return $c[$kind] = $out;
}

/** The weakest reference parts that reach $need (up to $n, at most one per brand while possible). */
function rocGpPartsFor(string $kind, ?float $need, int $n = 3, bool $inShops = false): array {
    if (!$need) return [];
    $ok = array_filter(rocGpRefs($kind), function ($s) use ($need) { return $s >= $need; });
    // Upgrades: only parts still sold new (not a GTX 1080 or a Core i7-8700).
    if ($inShops) $ok = array_intersect_key($ok, array_flip(ROC_GP_IN_SHOPS));
    // The weakest of each big brand first (NVIDIA and AMD cards, Intel and AMD processors), then the next weakest;
    // a brand only when its weakest part is near the need (not an RX 570 for a game that runs on integrated graphics).
    $pick = [];
    $low = $ok ? min($ok) : 0;
    foreach ($kind === 'gpu' ? ['GeForce', 'Radeon'] : ['Intel', 'AMD'] as $b)
        foreach ($ok as $name => $s) if (strpos($name, $b) === 0) { if ($s <= $low + 1) $pick[] = $name; break; }
    foreach ($ok as $name => $s) if (!in_array($name, $pick, true) && count($pick) < $n) $pick[] = $name;
    $order = array_keys($ok);
    usort($pick, function ($a, $b) use ($order) { return array_search($a, $order, true) <=> array_search($b, $order, true); });
    return array_slice($pick, 0, $n);
}

/** How heavy the game is, from the recommended graphics card score. */
function rocGpWeight(array $g): array {
    $r = (float)(($g['req']['rec']['gpu'] ?? null) ?: ($g['req']['min']['gpu'] ?? 0));
    if ($r <= 2) return ['very light', 'It even runs on integrated graphics, so most laptops and office PCs can play it.'];
    if ($r <= 3.5) return ['light', 'An entry-level graphics card from the last ten years is enough for good settings.'];
    if ($r <= 5) return ['medium', 'A mid-range graphics card such as a GTX 1650 or RX 580 class card plays it well.'];
    if ($r <= 7) return ['demanding', 'For high settings it wants a modern mid-range graphics card (RTX 2060 or RTX 3060 class).'];
    return ['very demanding', 'This is one of the heaviest PC games: high settings need a fast, recent graphics card.'];
}

/** Similar games: same main genre, closest in how heavy they are, popular ones first. */
function rocGpSimilar(string $root, array $g, int $n = 8): array {
    $genre = trim(explode(',', (string)($g['g'] ?? ''))[0] ?? '');
    $w = (float)($g['req']['rec']['gpu'] ?? $g['req']['min']['gpu'] ?? 0);
    $c = [];
    foreach (rocGpAll($root) as $x) {
        if ($x['slug'] === $g['slug']) continue;
        $xg = array_map('trim', explode(',', (string)($x['g'] ?? '')));
        $score = ($genre !== '' && in_array($genre, $xg, true) ? 0 : 5) + abs((float)($x['req']['rec']['gpu'] ?? $x['req']['min']['gpu'] ?? 0) - $w) + $x['rank'] / 400;
        $c[] = [$score, $x];
    }
    usort($c, function ($a, $b) { return $a[0] <=> $b[0]; });
    return array_map(function ($p) { return $p[1]; }, array_slice($c, 0, $n));
}

/** Published products of a category for the upgrade box (graphics cards and processors: the weakest that reach $min). */
function rocGpProducts(PDO $pdo, string $cat, ?float $min, int $n = 3): array {
    try {
        $st = $pdo->prepare("SELECT slug, title, image, image_alt, short_desc, best_for, affiliate_amazon FROM products WHERE status = 'published' AND category_slug = ?");
        $st->execute([$cat]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { return []; }
    foreach ($rows as &$r) $r['_s'] = $cat === 'gpu' ? rocAgentGpuScore((string)$r['title']) : ($cat === 'cpu' ? rocAgentCpuScore((string)$r['title'])
        : ($cat === 'ram' && preg_match('/(\d{1,3})\s*GB/i', (string)$r['title'], $gb) ? (float)$gb[1] : null));   // memory kits: their size in GB
    unset($r);
    if ($min !== null && in_array($cat, ['gpu', 'cpu', 'ram'], true)) {
        $ok = array_values(array_filter($rows, function ($r) use ($min) { return $r['_s'] !== null && $r['_s'] >= $min; }));
        if (!$ok) { $ok = array_values(array_filter($rows, function ($r) { return $r['_s'] !== null; })); usort($ok, function ($a, $b) { return $b['_s'] <=> $a['_s']; }); }
        else usort($ok, function ($a, $b) { return [$a['_s'], $a['title']] <=> [$b['_s'], $b['title']]; });
        $rows = $ok;
    } else {
        usort($rows, function ($a, $b) { return strcmp((string)$a['title'], (string)$b['title']); });
    }
    return array_slice($rows, 0, $n);
}
