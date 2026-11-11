<?php
/**
 * Run On Console — saves a player's "Did it run?" answer from a game page (Deploy 181).
 *
 * POST JSON {game, vote: ok|slow|no, gpu, cpu, ram}. Only from runonconsole.com pages. One answer per game and
 * browser network (a second answer replaces the first), at most 30 answers a day from one address. No name or email;
 * the IP address is stored only as a keyed hash. See api/v1/game-votes-lib.php.
 */
header('Content-Type: application/json; charset=utf-8');
function rocVoteOut(int $code, array $data): void { http_response_code($code); header('Cache-Control: no-store'); echo json_encode($data); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') rocVoteOut(405, ['success' => false, 'error' => 'POST required.']);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
$host = strtolower((string)parse_url($origin, PHP_URL_HOST));
$self = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
if ($host === '' || ($host !== $self && $host !== 'runonconsole.com' && $host !== 'www.runonconsole.com')) rocVoteOut(403, ['success' => false, 'error' => 'Please answer on runonconsole.com.']);

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) rocVoteOut(422, ['success' => false, 'error' => 'Nothing was sent.']);
$game = strtolower(trim((string)($in['game'] ?? '')));
$vote = (string)($in['vote'] ?? '');
$part = function ($v) { $v = trim((string)$v); return preg_match('/^[A-Za-z0-9 .\/()+\-]{2,80}$/', $v) ? $v : ''; };
$gpu = $part($in['gpu'] ?? ''); $cpu = $part($in['cpu'] ?? ''); $ram = (int)($in['ram'] ?? 0);
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $game) || !in_array($vote, ['ok', 'slow', 'no'], true) || $gpu === '' || $cpu === '' || $ram < 2 || $ram > 128)
    rocVoteOut(422, ['success' => false, 'error' => 'Please run the check first, then answer.']);

$ROOT = dirname(__DIR__, 2);
require_once __DIR__ . '/game-pages-lib.php';
if (!isset(rocGpAll($ROOT)[$game])) rocVoteOut(404, ['success' => false, 'error' => 'Unknown game.']);

ob_start();
require_once __DIR__ . '/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) rocVoteOut(503, ['success' => false, 'error' => 'The server is busy. Please try again in a minute.']);
require_once __DIR__ . '/game-votes-lib.php';
require_once __DIR__ . '/client-ip.php';
require_once __DIR__ . '/reviews-lib.php';   // rocReviewSecret(): the site's secret key, kept outside public_html
$ipHash = hash_hmac('sha256', (string)rocClientIp(), rocReviewSecret($ROOT));
$now = date('Y-m-d H:i:s');
try {
    rocVotesTable($pdo);
    $st = $pdo->prepare('SELECT COUNT(*) FROM roc_game_votes WHERE ip_hash = ? AND updated_at > ?');
    $st->execute([$ipHash, date('Y-m-d H:i:s', time() - 86400)]);
    if ((int)$st->fetchColumn() >= 30) rocVoteOut(429, ['success' => false, 'error' => 'Thanks, that is enough answers for today.']);
    $st = $pdo->prepare('SELECT id FROM roc_game_votes WHERE game = ? AND ip_hash = ? LIMIT 1');
    $st->execute([$game, $ipHash]);
    $id = (int)$st->fetchColumn();
    if ($id) $pdo->prepare('UPDATE roc_game_votes SET vote = ?, gpu = ?, cpu = ?, ram = ?, updated_at = ? WHERE id = ?')->execute([$vote, $gpu, $cpu, $ram, $now, $id]);
    else $pdo->prepare('INSERT INTO roc_game_votes (game, vote, gpu, cpu, ram, ip_hash, hidden, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)')
             ->execute([$game, $vote, $gpu, $cpu, $ram, $ipHash, $now, $now]);
} catch (\Throwable $e) {
    error_log('[ROC game-vote] ' . get_class($e) . ': ' . $e->getMessage());
    rocVoteOut(500, ['success' => false, 'error' => 'Your answer could not be saved. Please try again later.']);
}
rocVoteOut(200, ['success' => true]);
