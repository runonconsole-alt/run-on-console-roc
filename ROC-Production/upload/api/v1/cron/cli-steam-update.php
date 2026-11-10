<?php
/**
 * Run On Console — new popular Steam games for the "Can I run" pages (Deploy 87).
 *
 *   php cli-steam-update.php            what the monthly update did and what is still in its queue
 *   php cli-steam-update.php --now=N    asks Steam about N candidates now (the scheduler does 6 a run by itself)
 *   php cli-steam-update.php --off | --on   stops / restarts the automatic update
 *   php cli-steam-update.php --refresh      makes this month's list again now, with Steam's most played games (Deploy 171)
 *   php cli-steam-update.php --add=APPID[,APPID]   puts these Steam games first in the queue (asked again even if skipped before)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$ROOT = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
echo "Run On Console - new Steam games\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/steam-update-lib.php';
if (in_array('--off', $args, true) || in_array('--on', $args, true)) {
    rocBlogSet($pdo, 'steam_update_auto', in_array('--on', $args, true) ? 'on' : 'off');
    exit('Automatic Steam update is ' . (in_array('--on', $args, true) ? 'on' : 'off') . ".\n");
}
// Deploy 171: a new list now, or chosen games first.
$add = [];
foreach ($args as $a) if (strpos($a, '--add=') === 0) $add = array_values(array_filter(array_map('intval', explode(',', substr($a, 6)))));
if (in_array('--refresh', $args, true) || $add) {
    $st = rocBlogGet($pdo, 'steam_update', []); $st = is_array($st) ? $st : [];
    $skip = rocBlogGet($pdo, 'steam_skip', []); $skip = is_array($skip) ? array_map('intval', $skip) : [];
    $haveIds = [];
    foreach ((array)(json_decode((string)@file_get_contents($ROOT . '/roc-steam-games.json'), true)['games'] ?? []) as $g) $haveIds[(int)($g['appid'] ?? 0)] = 1;
    $queue = array_map('intval', (array)($st['queue'] ?? []));
    if (in_array('--refresh', $args, true)) {
        $cand = rocSteamCandidates();
        if (!$cand) exit("Steam did not answer. Try again in a few minutes.\n");
        $queue = array_merge($cand, $queue);
    }
    $queue = array_merge($add, $queue);
    $skip = array_values(array_diff($skip, $add));
    $skipSet = array_flip($skip);
    $queue = array_values(array_unique(array_filter($queue, function ($id) use ($haveIds, $skipSet) { return $id > 0 && !isset($haveIds[$id]) && !isset($skipSet[$id]); })));
    $st['month'] = (new DateTime('now', new DateTimeZone('Asia/Karachi')))->format('Y-m');
    $st['queue'] = $queue; $st['added'] = (array)($st['added'] ?? []); $st['checked'] = (int)($st['checked'] ?? 0); $st['last'] = time();
    rocBlogSet($pdo, 'steam_update', $st);
    rocBlogSet($pdo, 'steam_skip', $skip);
    echo count($queue) . " game(s) in the queue now; the scheduler adds 6 every 10 minutes. Faster: --now=30\n\n";
}
$now = 0;
foreach ($args as $a) if (strpos($a, '--now=') === 0) $now = max(1, min(60, (int)substr($a, 6)));
if ($now) { $r = rocSteamUpdateTick($pdo, $ROOT, $now); echo ($r ?? 'Nothing new.') . "\n\n"; }
$d = json_decode((string)@file_get_contents($ROOT . '/roc-steam-games.json'), true);
$st = rocBlogGet($pdo, 'steam_update', []);
echo '  Games in the list: ' . count((array)($d['games'] ?? [])) . ' (updated ' . ($d['updated'] ?? '?') . ")\n";
echo '  This month (' . ($st['month'] ?? 'not started') . '): ' . count((array)($st['queue'] ?? [])) . ' to check, ' . (int)($st['checked'] ?? 0) . ' checked, ' . count((array)($st['added'] ?? [])) . " added\n";
if (!empty($st['added'])) echo '  Added: ' . implode(', ', array_slice((array)$st['added'], -15)) . "\n";
echo '  Automatic: ' . ((string)rocBlogGet($pdo, 'steam_update_auto', 'on') === 'off' ? 'off' : 'on (6 a scheduler run, every month)') . "\n";
