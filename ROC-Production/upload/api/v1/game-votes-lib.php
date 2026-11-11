<?php
/**
 * Run On Console — "Did it run on your PC?" answers from players (Deploy 181).
 *
 * Owner, 2026-11-11 (competitor gap: data no other site has): after the quick check on a game page, a player can say
 * whether the game ran well, ran but slow, or did not run, with the parts chosen in the check. No name, no email; the
 * IP address is kept only as a keyed hash to stop repeat votes. A game shows the answers once it has ROC_VOTES_MIN of
 * them, so one or two votes never paint a picture. Votes can be hidden with cron/cli-game-votes.php.
 *
 * Table roc_game_votes (made on the first vote): id, game, vote (ok | slow | no), gpu, cpu, ram, ip_hash, hidden,
 * created_at, updated_at.
 */

const ROC_VOTES_MIN = 5;
const ROC_VOTE_LABEL = ['ok' => 'ran well', 'slow' => 'ran, but slow', 'no' => 'did not run'];

function rocVotesTable(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS roc_game_votes (id INTEGER PRIMARY KEY AUTOINCREMENT, game TEXT NOT NULL, vote TEXT NOT NULL, gpu TEXT, cpu TEXT, ram INTEGER,
                    ip_hash TEXT, hidden INTEGER NOT NULL DEFAULT 0, created_at TEXT, updated_at TEXT)");
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS roc_game_votes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, game VARCHAR(160) NOT NULL, vote VARCHAR(8) NOT NULL,
                gpu VARCHAR(80) NULL, cpu VARCHAR(80) NULL, ram SMALLINT NULL, ip_hash CHAR(64) NULL, hidden TINYINT NOT NULL DEFAULT 0,
                created_at DATETIME NULL, updated_at DATETIME NULL, INDEX idx_game (game), INDEX idx_ip (ip_hash)) DEFAULT CHARSET=utf8mb4");
}

/** The answers of one game: counts, total and the most common graphics cards. */
function rocVotesFor(?PDO $pdo, string $game): array {
    $out = ['ok' => 0, 'slow' => 0, 'no' => 0, 'total' => 0, 'gpus' => []];
    if (!$pdo) return $out;
    try {
        $st = $pdo->prepare('SELECT vote, gpu FROM roc_game_votes WHERE game = ? AND hidden = 0');
        $st->execute([$game]);
        $gpus = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (!isset($out[$r['vote']])) continue;
            $out[$r['vote']]++; $out['total']++;
            $g = trim((string)$r['gpu']);
            if ($g !== '') $gpus[$g] = ($gpus[$g] ?? 0) + 1;
        }
        arsort($gpus);
        $out['gpus'] = array_slice(array_keys($gpus), 0, 3);
    } catch (\Throwable $e) {}   // no table yet: no answers
    return $out;
}

/** The line on the game page, once there are enough answers. */
function rocVotesHtml(array $v): string {
    if ($v['total'] < ROC_VOTES_MIN) return '';
    $short = function ($n) { return (string)preg_replace('/^(?:GeForce|Radeon|Intel)\s+/', '', $n); };
    return '<p class="gp-votes"><b>Players with this game (' . $v['total'] . ' answers):</b> ' . $v['ok'] . ' ran well · ' . $v['slow'] . ' ran, but slow · ' . $v['no'] . ' did not run.'
        . ($v['gpus'] ? ' Most common graphics cards: ' . htmlspecialchars(implode(', ', array_map($short, $v['gpus'])), ENT_QUOTES, 'UTF-8') . '.' : '') . '</p>';
}
