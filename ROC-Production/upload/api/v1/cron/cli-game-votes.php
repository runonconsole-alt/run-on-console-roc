<?php
/**
 * Run On Console — the players' "Did it run?" answers (Deploy 181).
 *
 *   php cli-game-votes.php               the newest 30 answers and the games with the most answers
 *   php cli-game-votes.php --game=SLUG   the answers of one game
 *   php cli-game-votes.php --hide=ID     hides an answer (spam, a joke); --show=ID puts it back
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$ROOT = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/game-votes-lib.php';
rocVotesTable($pdo);
echo "Run On Console - players' answers (Did it run?)\n\n";
foreach ($args as $a) {
    if (preg_match('/^--(hide|show)=(\d+)$/', $a, $m)) {
        $pdo->prepare('UPDATE roc_game_votes SET hidden = ? WHERE id = ?')->execute([$m[1] === 'hide' ? 1 : 0, (int)$m[2]]);
        echo "Answer {$m[2]} " . ($m[1] === 'hide' ? 'hidden' : 'shown again') . ". It shows on the game page after the cache clears.\n";
        exit;
    }
}
$game = null;
foreach ($args as $a) if (strpos($a, '--game=') === 0) $game = substr($a, 7);
$sql = 'SELECT id, game, vote, gpu, cpu, ram, hidden, updated_at FROM roc_game_votes' . ($game !== null ? ' WHERE game = ?' : '') . ' ORDER BY updated_at DESC LIMIT 30';
$st = $pdo->prepare($sql);
$st->execute($game !== null ? [$game] : []);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) exit("No answers yet.\n");
foreach ($rows as $r) printf("  %5d  %-16s %-38s %-6s %s + %s + %d GB%s\n", $r['id'], substr((string)$r['updated_at'], 0, 16), substr($r['game'], 0, 38), $r['vote'], $r['gpu'], $r['cpu'], $r['ram'], $r['hidden'] ? '  (hidden)' : '');
if ($game === null) {
    echo "\nGames with the most answers (shown on the page from " . ROC_VOTES_MIN . "):\n";
    foreach ($pdo->query('SELECT game, COUNT(*) n FROM roc_game_votes WHERE hidden = 0 GROUP BY game ORDER BY n DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r) printf("  %-40s %d\n", $r['game'], $r['n']);
}
