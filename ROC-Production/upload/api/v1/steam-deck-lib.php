<?php
/**
 * Run On Console — Steam Deck compatibility of every game (Deploy 157).
 *
 * Valve rates games on Steam Deck: Verified, Playable, Unsupported or Unknown, with the test results behind the
 * rating. The rating comes from the Steam store (the same report the store page shows), a few games per scheduler
 * run (cli-blog-scheduler.php) and all at once with cli-steam-deck.php. Saved in roc-steam-deck.json next to
 * roc-steam-games.json (the server's own copy grows: deploys never carry it).
 *
 *   { "updated": 1730600000, "apps": { "1245620": { "c": 3, "i": [[4, "DefaultControllerConfigFullyFunctional"], ...], "t": 1730600000 } } }
 *
 * Shown on each game page (quick answer, a Steam Deck box, a FAQ), on /can-i-run/steam-deck/ and in the report.
 */

const ROC_DECK_FILE = '/roc-steam-deck.json';
const ROC_DECK_PAGE = '/can-i-run/steam-deck/';
const ROC_DECK_REFRESH = 60 * 86400;   // a rating is asked again after 60 days (Valve re-tests games)

function rocDeckLoad(string $root, ?array $set = null): array {
    static $c = [];
    if ($set !== null) return $c[$root] = $set;   // after a save
    if (isset($c[$root])) return $c[$root];
    $d = json_decode((string)@file_get_contents($root . ROC_DECK_FILE), true);
    return $c[$root] = is_array($d) && is_array($d['apps'] ?? null) ? $d : ['updated' => 0, 'apps' => []];
}

/** [category 0-3 or null, label, css class, one sentence]. */
function rocDeckOf(string $root, int $appid): ?array {
    $a = rocDeckLoad($root)['apps'][(string)$appid] ?? null;
    if (!$a) return null;
    return rocDeckLabel((int)$a['c']) + ['items' => (array)($a['i'] ?? []), 't' => (int)($a['t'] ?? 0)];
}

function rocDeckLabel(int $c): array {
    switch ($c) {
        case 3: return ['c' => 3, 'label' => 'Verified', 'cls' => 'ok', 'say' => 'works great on Steam Deck: Valve tested it and it runs well with the default settings and controls'];
        case 2: return ['c' => 2, 'label' => 'Playable', 'cls' => 'mid', 'say' => 'runs on Steam Deck, but Valve found some extra steps, such as small text or a menu that needs the touchscreen'];
        case 1: return ['c' => 1, 'label' => 'Unsupported', 'cls' => 'no', 'say' => 'does not run on Steam Deck, according to Valve\'s tests'];
        default: return ['c' => 0, 'label' => 'Unknown', 'cls' => 'low', 'say' => 'has not been rated for Steam Deck by Valve yet'];
    }
}

/** A test result in plain words: "InterfaceTextIsNotLegible" -> "Interface text is not legible" (a few written out). */
function rocDeckItem(string $tok): string {
    static $own = [
        'DefaultControllerConfigFullyFunctional' => 'The default controls work fully on the Deck',
        'ControllerGlyphsMatchDeckDevice' => 'On-screen buttons match the Deck\'s buttons',
        'ControllerGlyphsDoNotMatchDeckDevice' => 'Some on-screen buttons show another controller or keyboard keys',
        'InterfaceTextIsLegible' => 'Text is easy to read on the Deck screen',
        'InterfaceTextIsNotLegible' => 'Some text is small and hard to read on the Deck screen',
        'DefaultConfigurationIsPerformant' => 'Runs well with the default settings',
        'TextInputDoesNotAutomaticallyInvokesKeyboard' => 'You may need to open the on-screen keyboard yourself',
        'LauncherInteractionIssues' => 'A launcher needs the touchscreen or a mouse',
        'NativeResolutionNotSupported' => 'Does not use the Deck\'s full screen resolution',
        'NativeResolutionNotDefault' => 'The Deck\'s resolution must be set in the options',
        'RequiresManualControllerConfig' => 'Some controls need to be set up by hand',
        'SingleplayerGameplayFunctional' => 'Single player works',
        'MultiplayerGameplayNotFunctional' => 'Multiplayer does not work on the Deck',
        'UnsupportedAntiCheat' => 'Its anti-cheat does not support the Deck',
        'GameOrLauncherDoesntWorkOnLinux' => 'The game or its launcher does not run on SteamOS',
        'TouchscreenRequiredForSomeAccess' => 'Some menus need the touchscreen',
        'DefaultControllerConfigNotFullyFunctional' => 'Some controls need a mouse, keyboard or the touchscreen',
        'ExternalControllersNotSupportedPrimaryPlayer' => 'An external controller cannot be used by the main player',
        'FirstTimeSetupRequiresActiveInternetConnection' => 'The first start needs an internet connection',
        'SingleplayerGameplayRequiresActiveInternetConnection' => 'Single player needs an internet connection',
        'SteamOSDoesNotSupport_Software' => 'Needs software that SteamOS does not run',
        'UnsupportedAntiCheatConfiguration' => 'Its anti-cheat does not support the Deck',
        'UnsupportedAntiCheat_Other' => 'Its anti-cheat does not support the Deck',
        'UnsupportedGraphicsPerformance' => 'Runs too slowly on the Deck',
    ];
    $t = (string)preg_replace('/^#?(?:SteamDeckVerified_TestResult_|SteamOS_TestResult_)/', '', $tok);
    if (isset($own[$t])) return $own[$t];
    $w = strtolower(trim((string)preg_replace('/(?<!^)([A-Z])/', ' $1', $t)));
    return $w !== '' ? ucfirst($w) : '';
}

/** One game's rating from the Steam store, or null when the store did not answer. */
function rocDeckFetch(int $appid): ?array {
    if (!function_exists('curl_init')) return null;
    $c = curl_init('https://store.steampowered.com/saleaction/ajaxgetdeckappcompatibilityreport?nAppID=' . $appid . '&l=english');
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'RunOnConsole-compatibility-list/1.0 (+https://runonconsole.com)']);
    $body = curl_exec($c);
    $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    if ($code !== 200 || !is_string($body)) return null;
    $j = json_decode($body, true);
    if (!is_array($j) || (int)($j['success'] ?? 0) !== 1) return null;
    $r = (array)($j['results'] ?? []);
    $items = [];
    foreach ((array)($r['resolved_items'] ?? []) as $it) {
        $tok = (string)preg_replace('/^#?SteamDeckVerified_TestResult_/', '', (string)($it['loc_token'] ?? ''));
        if ($tok !== '' && preg_match('/^[A-Za-z0-9_]{3,80}$/', $tok)) $items[] = [(int)($it['display_type'] ?? 0), $tok];
    }
    return ['c' => max(0, min(3, (int)($r['resolved_category'] ?? 0))), 'i' => array_slice($items, 0, 8), 't' => time()];
}

/**
 * Asks the store about up to $n games: never asked first (most popular first), then the oldest ratings past 60 days.
 * Returns a line for the log, or null when there was nothing to do.
 */
function rocDeckTick(string $root, array $games, int $n, float $pause = 0.0): ?string {
    $d = rocDeckLoad($root);
    $now = time(); $todo = [];
    foreach ($games as $g) { $id = (string)(int)$g['appid']; if (!isset($d['apps'][$id])) $todo[] = $id; }
    if (count($todo) < $n) {
        $old = [];
        foreach ($d['apps'] as $id => $a) if ($now - (int)($a['t'] ?? 0) > ROC_DECK_REFRESH) $old[$id] = (int)($a['t'] ?? 0);
        asort($old);
        $todo = array_merge($todo, array_keys(array_slice($old, 0, $n - count($todo), true)));
    }
    $todo = array_slice($todo, 0, $n);
    if (!$todo) return null;
    $ok = 0; $fail = 0; $changed = false;
    foreach ($todo as $i => $id) {
        if ($i && $pause > 0) usleep((int)($pause * 1e6));
        $r = rocDeckFetch((int)$id);
        if ($r === null) { $fail++; if ($fail >= 3) break; continue; }
        if ((int)($d['apps'][(string)$id]['c'] ?? -1) !== (int)$r['c']) $changed = true;
        $d['apps'][(string)$id] = $r; $ok++;
    }
    if ($ok) {
        if ($changed) $d['updated'] = $now;   // Deploy 161: only a new or different rating dates the page
        $tmp = $root . ROC_DECK_FILE . '.tmp' . getmypid();
        if (file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_SLASHES)) !== false) rename($tmp, $root . ROC_DECK_FILE);
        rocDeckLoad($root, $d);
    }
    return 'Steam Deck ratings: ' . $ok . ' saved' . ($fail ? ', ' . $fail . ' not answered' : '') . ' (' . count($d['apps']) . ' games rated)';
}

/** Counts for the page and the report: [c => n] over the games on the list, plus 'rated'. */
function rocDeckCounts(string $root, array $games): array {
    $out = [3 => 0, 2 => 0, 1 => 0, 0 => 0, 'rated' => 0];
    $apps = rocDeckLoad($root)['apps'];
    foreach ($games as $g) {
        $a = $apps[(string)(int)$g['appid']] ?? null;
        if (!$a) continue;
        $out[(int)$a['c']]++; $out['rated']++;
    }
    return $out;
}
