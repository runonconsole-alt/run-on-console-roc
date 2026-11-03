<?php
/**
 * Run On Console — Steam Deck ratings for every game at once (Deploy 157).
 *
 *   php cli-steam-deck.php                  dry run: how many games are rated, how many are left
 *   php cli-steam-deck.php --apply          asks the Steam store about the games not rated yet (about 1 a second)
 *   php cli-steam-deck.php --apply --limit=500
 *
 * The scheduler (cli-blog-scheduler.php) also rates a few games a run, so this is only needed to fill the list fast.
 * Saved to public_html/roc-steam-deck.json (steam-deck-lib.php).
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$limit = 3000;
foreach ($args as $a) if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = max(1, (int)$m[1]);

define('ROC_RENDER_LIB_ONLY', true);
require $ROOT . '/blog-render.php';
require_once $ROOT . '/api/v1/game-pages-lib.php';
require_once $ROOT . '/api/v1/steam-deck-lib.php';

$games = rocGpAll($ROOT);
$c = rocDeckCounts($ROOT, $games);
echo "Run On Console - Steam Deck ratings\n";
echo count($games) . " games on the list, " . $c['rated'] . " rated (Verified " . $c[3] . ", Playable " . $c[2] . ", Unsupported " . $c[1] . ", Unknown " . $c[0] . ")\n";
$left = count($games) - $c['rated'];
if (!$apply) exit("\nDRY RUN - " . $left . " games to rate. Run again with --apply (about " . max(1, (int)ceil(min($left, $limit) / 60)) . " minutes).\n");

$done = 0;
while ($done < $limit) {
    $n = min(25, $limit - $done);
    $line = rocDeckTick($ROOT, $games, $n, 1.0);
    if ($line === null) break;
    echo date('H:i:s') . ' ' . $line . "\n";
    $done += $n;
    if (strpos($line, ' 0 saved') !== false) { echo "The store stopped answering; try again later.\n"; break; }
}
echo "\nDone.\n";
