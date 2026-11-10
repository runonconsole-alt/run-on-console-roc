<?php
/**
 * Run On Console — new popular Steam games join the "Can I run" pages by themselves (Deploy 87).
 *
 * Once a month (Pakistan time) the list of candidates is made from SteamSpy (top 100 of the last two
 * weeks and of all time) and the Steam store (top sellers, new releases). Every scheduler run then asks
 * the Steam store about a few of them (a few seconds, kind to Steam's limits) and adds the games that
 * have official PC requirements, are released and are not adult-only. Each new game gets its page,
 * its sitemap entry and the checker at once. roc-steam-games.json is saved with a backup first.
 *
 *   rocSteamUpdateTick($pdo, $root, $n)   the scheduler: at most $n store requests this run
 *
 * Deploy 171: Steam's 100 most played games right now come first (on 10 November 32 of them were missing, for
 * example Resident Evil Requiem), and a game that is not out yet is asked again next month instead of being
 * skipped for good.
 *
 * State in cms_settings: steam_update {month, queue, added, checked, last}. steam_skip: app ids checked and
 * not kept (never asked again). Deploys must not carry roc-steam-games.json any more (the server's copy grows).
 */
if (defined('ROC_STEAM_UPDATE_LIB')) return;
define('ROC_STEAM_UPDATE_LIB', 1);
require_once __DIR__ . '/steam-req-lib.php';
require_once __DIR__ . '/cms/blog-engine-lib.php';

const ROC_STEAM_FROM_YEAR = 2013;
const ROC_STEAM_ADULT = [3, 4];   // Steam content descriptors: adult-only sexual content, frequent nudity

function rocSteamGet(string $url): ?array {
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'RunOnConsole-compatibility-list/1.0 (+https://runonconsole.com)']);
    $raw = (string)curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    if ($code !== 200) return null;
    $j = json_decode($raw, true);
    return is_array($j) ? $j : null;
}

/** Popular app ids from SteamSpy and the Steam store, most popular first. */
function rocSteamCandidates(): array {
    $ids = [];
    // Deploy 171: what people play on Steam now, most played first.
    $mp = rocSteamGet('https://api.steampowered.com/ISteamChartsService/GetMostPlayedGames/v1/');
    foreach ((array)($mp['response']['ranks'] ?? []) as $a) if (!empty($a['appid'])) $ids[(int)$a['appid']] = 1;
    foreach (['top100in2weeks', 'top100forever'] as $r) {
        $d = rocSteamGet('https://steamspy.com/api.php?request=' . $r);
        foreach ((array)$d as $a) if (!empty($a['appid'])) $ids[(int)$a['appid']] = 1;
    }
    $f = rocSteamGet('https://store.steampowered.com/api/featuredcategories?cc=us&l=english');
    foreach (['top_sellers', 'new_releases'] as $k) foreach ((array)($f[$k]['items'] ?? []) as $a) if (!empty($a['id'])) $ids[(int)$a['id']] = 1;
    return array_keys($ids);
}

function rocSteamYear(array $app): int { return preg_match('/(\d{4})/', (string)($app['release_date']['date'] ?? ''), $m) ? (int)$m[1] : 0; }

/**
 * Deploy 174: games not out yet. Steam's most popular upcoming games (store search "popular coming soon", first 50)
 * join the list when Steam already shows their PC requirements; they carry "soon" (Steam's release date text) and
 * "soon_t" (the date as a timestamp when Steam gives a real day). Every scheduler run checks two of them again:
 * new requirements are taken, and once the game is out "soon" goes and "out" holds the day it came out.
 */
function rocSteamUpcoming(): array {
    $d = rocSteamGet('https://store.steampowered.com/search/results/?filter=popularcomingsoon&json=1&count=50&category1=998&cc=us&l=english');
    $ids = [];
    foreach ((array)($d['items'] ?? []) as $it) if (preg_match('#/apps/(\d+)/#', (string)($it['logo'] ?? ''), $m)) $ids[] = (int)$m[1];
    return array_values(array_unique($ids));
}

function rocSteamKeep(array $app, bool $soon = false): bool {
    if (($app['type'] ?? '') !== 'game') return false;
    $coming = !empty($app['release_date']['coming_soon']);
    if ($coming && !$soon) return false;
    $req = $app['pc_requirements'] ?? null;
    if (!is_array($req) || empty($req['minimum'])) return false;
    foreach ((array)($app['content_descriptors']['ids'] ?? []) as $id) if (in_array((int)$id, ROC_STEAM_ADULT, true)) return false;
    return $coming || rocSteamYear($app) >= ROC_STEAM_FROM_YEAR;
}

/** One list entry from Steam's answer, with the upcoming fields when the game is not out yet. */
function rocSteamEntry(array $app, ?array $old = null): array {
    $coming = !empty($app['release_date']['coming_soon']);
    $year = rocSteamYear($app) ?: (int)gmdate('Y');
    $g = rocSrBuildGame($app, $year);
    if ($old) $g['added'] = (int)($old['added'] ?? $g['added']);
    if ($coming) {
        $txt = trim((string)($app['release_date']['date'] ?? ''));
        $g['soon'] = $txt !== '' ? $txt : 'Coming soon';
        $t = preg_match('/\b\d{1,2}\b.*\b\d{4}\b|\b\d{4}-\d{2}-\d{2}\b/', $txt) ? strtotime($txt . ' 12:00 UTC') : false;
        $g['soon_t'] = $t ?: 0;
    } elseif ($old && !empty($old['soon'])) {
        $g['out'] = time();
    } elseif ($old && !empty($old['out'])) {
        $g['out'] = (int)$old['out'];
    }
    $g['checked'] = time();
    return $g;
}

function rocSteamUpdateTick(PDO $pdo, string $root, int $n = 6): ?string {
    if ((string)rocBlogGet($pdo, 'steam_update_auto', 'on') === 'off') return null;
    $file = $root . '/roc-steam-games.json';
    $data = json_decode((string)@file_get_contents($file), true);
    if (!is_array($data) || empty($data['games'])) return null;
    $month = (new DateTime('now', new DateTimeZone('Asia/Karachi')))->format('Y-m');
    $st = rocBlogGet($pdo, 'steam_update', []); $st = is_array($st) ? $st : [];
    $skip = rocBlogGet($pdo, 'steam_skip', []); $skip = is_array($skip) ? array_flip(array_map('intval', $skip)) : [];
    $have = [];
    foreach ($data['games'] as $g) $have[(int)($g['appid'] ?? 0)] = 1;

    // A new month: make the list of candidates (three small requests).
    if (($st['month'] ?? '') !== $month) {
        $q = array_values(array_filter(rocSteamCandidates(), function ($id) use ($have, $skip) { return !isset($have[$id]) && !isset($skip[$id]); }));
        $st = ['month' => $month, 'queue' => $q, 'added' => [], 'checked' => 0, 'last' => time(), 'soon_ids' => (array)($st['soon_ids'] ?? []), 'soon_at' => (int)($st['soon_at'] ?? 0)];
        rocBlogSet($pdo, 'steam_update', $st);
        return 'Steam update ' . $month . ': ' . count($q) . ' new candidate(s)';
    }
    // Deploy 174: once a week, Steam's popular upcoming games go to the front of the queue.
    if (time() - (int)($st['soon_at'] ?? 0) > 7 * 86400) {
        $up = rocSteamUpcoming();
        if ($up) {
            $new = array_values(array_filter($up, function ($id) use ($have) { return !isset($have[$id]); }));
            $st['queue'] = array_values(array_unique(array_merge($new, array_map('intval', (array)($st['queue'] ?? [])))));
            $st['soon_ids'] = array_slice(array_values(array_unique(array_merge($up, array_map('intval', (array)($st['soon_ids'] ?? []))))), 0, 300);
            $st['soon_at'] = time();
        }
    }
    $soonIds = array_flip(array_map('intval', (array)($st['soon_ids'] ?? [])));

    $added = []; $changed = []; $asked = 0;
    while (!empty($st['queue']) && $asked < $n) {
        $id = (int)array_shift($st['queue']); $asked++;
        if (isset($have[$id])) continue;
        $d = rocSteamGet('https://store.steampowered.com/api/appdetails?appids=' . $id . '&cc=us&l=english');
        if ($d === null) { array_unshift($st['queue'], $id); break; }    // Steam busy: next run
        $app = !empty($d[$id]['success']) ? (array)$d[$id]['data'] : null;
        $st['checked'] = (int)($st['checked'] ?? 0) + 1;
        if ($app && rocSteamKeep($app, isset($soonIds[$id]))) {
            $data['games'][] = rocSteamEntry($app);
            $have[$id] = 1; $added[] = (string)$app['name'] . (!empty($app['release_date']['coming_soon']) ? ' (upcoming)' : '');
        } elseif (!$app || empty($app['release_date']['coming_soon'])) $skip[$id] = 1;   // not out yet: asked again later
        usleep(1500000);
        if ($asked % 10 === 0) { try { $pdo->query('SELECT 1'); } catch (\Throwable $e) {} }   // Deploy 173: keeps the connection open on long runs
    }
    // Deploy 174: two upcoming games checked again (the oldest check first, at most every 3 days each).
    $due = [];
    foreach ($data['games'] as $i => $g) if (!empty($g['soon']) && time() - (int)($g['checked'] ?? 0) > 3 * 86400) $due[$i] = (int)($g['checked'] ?? 0);
    asort($due);
    foreach (array_slice(array_keys($due), 0, 2) as $i) {
        $old = $data['games'][$i]; $id = (int)$old['appid'];
        $d = rocSteamGet('https://store.steampowered.com/api/appdetails?appids=' . $id . '&cc=us&l=english');
        if ($d === null) break;
        $app = !empty($d[$id]['success']) ? (array)$d[$id]['data'] : null;
        if ($app && !empty($app['pc_requirements']['minimum'])) {
            $data['games'][$i] = rocSteamEntry($app, $old);
            if (empty($data['games'][$i]['soon'])) $changed[] = (string)$app['name'] . ' is out';
            elseif (json_encode($data['games'][$i]['min']) !== json_encode($old['min']) || ($data['games'][$i]['soon'] ?? '') !== ($old['soon'] ?? '')) $changed[] = (string)$app['name'] . ' updated';
        } else $data['games'][$i]['checked'] = time();
        usleep(1500000);
    }
    $changed = array_values(array_filter($changed));
    if ($added || $due) {
        $bdir = dirname($root) . '/roc-steam-backups';
        if ($added) {
            if (!is_dir($bdir)) @mkdir($bdir, 0700, true);
            @copy($file, $bdir . '/roc-steam-games-' . date('Ymd-His') . '.json');
            foreach (glob($bdir . '/roc-steam-games-*.json') ?: [] as $i => $old) if ($i < count(glob($bdir . '/roc-steam-games-*.json')) - 6) @unlink($old);   // 6 kept
        }
        if ($added || $changed) $data['updated'] = gmdate('Y-m-d');
        $tmp = $file . '.roc-tmp';
        if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false) @rename($tmp, $file);
        if ($added) $st['added'] = array_slice(array_merge((array)($st['added'] ?? []), $added), -200);
    }
    $st['last'] = time();
    rocBlogSet($pdo, 'steam_update', $st);
    rocBlogSet($pdo, 'steam_skip', array_slice(array_keys($skip), -5000));
    $msg = [];
    if ($added) $msg[] = 'added ' . implode(', ', $added) . ' (' . count($st['queue']) . ' left this month)';
    if ($changed) $msg[] = implode(', ', $changed);
    return $msg ? 'Steam update: ' . implode('; ', $msg) : null;
}
