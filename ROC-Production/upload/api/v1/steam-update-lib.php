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
    foreach (['top100in2weeks', 'top100forever'] as $r) {
        $d = rocSteamGet('https://steamspy.com/api.php?request=' . $r);
        foreach ((array)$d as $a) if (!empty($a['appid'])) $ids[(int)$a['appid']] = 1;
    }
    $f = rocSteamGet('https://store.steampowered.com/api/featuredcategories?cc=us&l=english');
    foreach (['top_sellers', 'new_releases'] as $k) foreach ((array)($f[$k]['items'] ?? []) as $a) if (!empty($a['id'])) $ids[(int)$a['id']] = 1;
    return array_keys($ids);
}

function rocSteamYear(array $app): int { return preg_match('/(\d{4})/', (string)($app['release_date']['date'] ?? ''), $m) ? (int)$m[1] : 0; }

function rocSteamKeep(array $app): bool {
    if (($app['type'] ?? '') !== 'game' || !empty($app['release_date']['coming_soon'])) return false;
    $req = $app['pc_requirements'] ?? null;
    if (!is_array($req) || empty($req['minimum'])) return false;
    foreach ((array)($app['content_descriptors']['ids'] ?? []) as $id) if (in_array((int)$id, ROC_STEAM_ADULT, true)) return false;
    return rocSteamYear($app) >= ROC_STEAM_FROM_YEAR;
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
        $st = ['month' => $month, 'queue' => $q, 'added' => [], 'checked' => 0, 'last' => time()];
        rocBlogSet($pdo, 'steam_update', $st);
        return 'Steam update ' . $month . ': ' . count($q) . ' new candidate(s)';
    }
    if (empty($st['queue'])) return null;

    $added = []; $asked = 0;
    while ($st['queue'] && $asked < $n) {
        $id = (int)array_shift($st['queue']); $asked++;
        if (isset($have[$id])) continue;
        $d = rocSteamGet('https://store.steampowered.com/api/appdetails?appids=' . $id . '&cc=us&l=english');
        if ($d === null) { array_unshift($st['queue'], $id); break; }    // Steam busy: next run
        $app = !empty($d[$id]['success']) ? (array)$d[$id]['data'] : null;
        $st['checked'] = (int)($st['checked'] ?? 0) + 1;
        if ($app && rocSteamKeep($app)) {
            $data['games'][] = rocSrBuildGame($app, rocSteamYear($app));
            $have[$id] = 1; $added[] = (string)$app['name'];
        } else $skip[$id] = 1;
        usleep(1500000);
    }
    if ($added) {
        $bdir = dirname($root) . '/roc-steam-backups';
        if (!is_dir($bdir)) @mkdir($bdir, 0700, true);
        @copy($file, $bdir . '/roc-steam-games-' . date('Ymd-His') . '.json');
        foreach (glob($bdir . '/roc-steam-games-*.json') ?: [] as $i => $old) if ($i < count(glob($bdir . '/roc-steam-games-*.json')) - 6) @unlink($old);   // 6 kept
        $data['updated'] = gmdate('Y-m-d');
        $tmp = $file . '.roc-tmp';
        if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false) @rename($tmp, $file);
        $st['added'] = array_slice(array_merge((array)($st['added'] ?? []), $added), -200);
    }
    $st['last'] = time();
    rocBlogSet($pdo, 'steam_update', $st);
    rocBlogSet($pdo, 'steam_skip', array_slice(array_keys($skip), -5000));
    return $added ? 'Steam update: added ' . implode(', ', $added) . ' (' . count($st['queue']) . ' left this month)' : null;
}
