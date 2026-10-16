<?php
/**
 * Run On Console — new popular Steam games for the "Can I run" pages (Deploy 87).
 *
 *   php cli-steam-update.php            what the monthly update did and what is still in its queue
 *   php cli-steam-update.php --now=N    asks Steam about N candidates now (the scheduler does 6 a run by itself)
 *   php cli-steam-update.php --off | --on   stops / restarts the automatic update
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
$now = 0;
foreach ($args as $a) if (strpos($a, '--now=') === 0) $now = max(1, min(60, (int)substr($a, 6)));
if ($now) { $r = rocSteamUpdateTick($pdo, $ROOT, $now); echo ($r ?? 'Nothing new.') . "\n\n"; }
$d = json_decode((string)@file_get_contents($ROOT . '/roc-steam-games.json'), true);
$st = rocBlogGet($pdo, 'steam_update', []);
echo '  Games in the list: ' . count((array)($d['games'] ?? [])) . ' (updated ' . ($d['updated'] ?? '?') . ")\n";
echo '  This month (' . ($st['month'] ?? 'not started') . '): ' . count((array)($st['queue'] ?? [])) . ' to check, ' . (int)($st['checked'] ?? 0) . ' checked, ' . count((array)($st['added'] ?? [])) . " added\n";
if (!empty($st['added'])) echo '  Added: ' . implode(', ', array_slice((array)$st['added'], -15)) . "\n";
echo '  Automatic: ' . ((string)rocBlogGet($pdo, 'steam_update_auto', 'on') === 'off' ? 'off' : 'on (6 a scheduler run, every month)') . "\n";
