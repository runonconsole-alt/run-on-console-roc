<?php
/**
 * Run On Console — ROC, the site assistant (the chat box on every page, /roc-chat.js).
 *
 * GET  /api/v1/agent.php                 {on, signed_in, name}: is ROC on (CMS > ROC assistant), is the visitor signed in
 * Only signed-in members can chat (owner's rule); others are sent to /compatibility/ to sign in or sign up.
 * POST /api/v1/agent.php  {session, page, messages: [{role: 'user'|'assistant', content}, ...], image?: data URL}
 *      {reply, game?, products[]}: the answer to the last message (see api/v1/agent-lib.php).
 *
 * Limits: 30 messages an hour per member and a daily number of AI answers (CMS); after that ROC
 * still answers with its fixed sentences. Saved: the member's account ID, the question and the answer
 * without personal details, the page and a daily salted visitor hash (never the IP), kept 90 days.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cms/blog-engine-lib.php';
require_once __DIR__ . '/agent-lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function rocAgentOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; }

$pdo = getDBConnection();
if (!$pdo) rocAgentOut(503, ['success' => false, 'error' => 'ROC is resting for a moment. Please try again soon.']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ROOT = dirname(__DIR__, 2);
$S = rocAgentSettings($pdo);

// Signed-in member (the site's own account session, as /api/v1/auth.php?action=session).
$uid = 0; $first = '';
try {
    if (function_exists('validateDatabaseSession') && validateDatabaseSession()) {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $q = $pdo->prepare('SELECT name, username FROM users WHERE id = ?'); $q->execute([$uid]);
        $u = $q->fetch(PDO::FETCH_ASSOC) ?: [];
        $first = trim(explode(' ', trim((string)($u['name'] ?? '')))[0] ?: (string)($u['username'] ?? ''));
    }
} catch (\Throwable $e) { $uid = 0; }
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

// Pictures only when the Groq key has a picture model (from the cached model list; unknown = yes).
$am = rocBlogGet($pdo, 'agent_models', []);
$vision = !is_array($am) || empty($am['t']) || !empty($am['vision']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') rocAgentOut(200, ['success' => true, 'on' => $S['on'], 'signed_in' => $uid > 0, 'name' => $first, 'vision' => $vision]);
if ($uid <= 0) rocAgentOut(401, ['success' => false, 'login' => true, 'error' => 'Please sign in to chat with ROC. / ROC se baat karne ke liye login karein.']);
if (!$S['on']) rocAgentOut(200, ['success' => true, 'off' => true, 'reply' => 'ROC is switched off for now.', 'products' => []]);

$in = json_decode((string)file_get_contents('php://input', false, null, 0, 2500000), true);
// 👍 / 👎 on an answer of this member (CMS > ROC assistant lists the 👎 ones).
if (($in['action'] ?? '') === 'vote') {
    rocAgentTable($pdo);
    $v = (int)($in['vote'] ?? 0); $v = $v > 0 ? 1 : ($v < 0 ? -1 : 0);
    $pdo->prepare('UPDATE roc_agent_chats SET vote = ? WHERE id = ? AND user_id = ?')->execute([$v, (int)($in['id'] ?? 0), $uid]);
    rocAgentOut(200, ['success' => true]);
}
// A picture (resized in the browser): read by the vision model, never saved.
$image = (string)($in['image'] ?? '');
if ($image !== '' && (strlen($image) > 2200000 || !preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $image)))
    rocAgentOut(422, ['success' => false, 'error' => 'Please send a JPG, PNG or WebP picture under 1.5 MB.']);
$msgs = [];
foreach (array_slice((array)($in['messages'] ?? []), -8) as $i => $m) {
    if (!is_array($m)) continue;
    $c = trim(mb_substr(strip_tags((string)($m['content'] ?? '')), 0, 1200));   // a dxdiag report is longer than a question
    if ($c !== '' || ($image !== '' && $i === count(array_slice((array)$in['messages'], -8)) - 1)) $msgs[] = ['role' => ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user', 'content' => $c];
}
if (!$msgs || end($msgs)['role'] !== 'user') rocAgentOut(422, ['success' => false, 'error' => 'Type a question first.']);
$session = substr(preg_replace('/[^a-z0-9]/i', '', (string)($in['session'] ?? '')), 0, 16) ?: substr(bin2hex(random_bytes(8)), 0, 16);
$page = mb_substr((string)parse_url((string)($in['page'] ?? ''), PHP_URL_PATH), 0, 200);

rocAgentTable($pdo);
$ip = rocAgentIpHash();
$now = gmdate('Y-m-d H:i:s');
$c = $pdo->prepare('SELECT COUNT(*) FROM roc_agent_chats WHERE (user_id = ? OR ip_hash = ?) AND created_at > ?');
$c->execute([$uid, $ip, gmdate('Y-m-d H:i:s', time() - 3600)]);
if ((int)$c->fetchColumn() >= $S['hourly_ip'])
    rocAgentOut(429, ['success' => false, 'error' => 'That is a lot of questions! Please wait a little and ask again. / Thora sa intezar kar ke dobara poochein.']);
$c = $pdo->prepare("SELECT COUNT(*) FROM roc_agent_chats WHERE created_at > ? AND engine <> 'basic'");
$c->execute([gmdate('Y-m-d') . ' 00:00:00']);
$useAi = (int)$c->fetchColumn() < $S['daily'];

// The model only ever sees the visitor's words without personal details.
foreach ($msgs as &$m) if ($m['role'] === 'user') $m['content'] = rocAgentScrub($m['content']);
unset($m);

$t0 = microtime(true);
try {
    // What the visitor's browser reports (graphics card, CPU threads, memory class), for "analyze my PC".
    $dev = is_array($in['device'] ?? null) ? $in['device'] : [];
    $dev = ['gpu' => mb_substr(strip_tags((string)($dev['gpu'] ?? '')), 0, 120), 'threads' => (int)($dev['threads'] ?? 0), 'ram' => (float)($dev['ram'] ?? 0),
            'os' => mb_substr(strip_tags((string)($dev['os'] ?? '')), 0, 40)];
    $a = rocAgentAnswer($pdo, $ROOT, $msgs, $useAi, $image !== '' ? $image : null, $dev, $uid);
} catch (\Throwable $e) {
    error_log('agent: ' . $e->getMessage());
    rocAgentOut(500, ['success' => false, 'error' => 'ROC could not answer this one. Please try again.']);
}

try {
    $pdo->prepare('INSERT INTO roc_agent_chats (created_at, user_id, session, ip_hash, page, question, answer, game, products, engine, lang) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$now, $uid, $session, $ip, $page, mb_substr(trim(end($msgs)['content'] . ($image !== '' ? ' [picture]' : '')), 0, 600), mb_substr(rocAgentScrub($a['reply']), 0, 2000),
                   mb_substr((string)($a['game']['title'] ?? ''), 0, 200), mb_substr(implode(', ', array_column($a['products'], 'title')), 0, 500),
                   $a['engine'], $a['lang']]);
    $chatId = (int)$pdo->lastInsertId();
    if (random_int(1, 50) === 1) $pdo->prepare('DELETE FROM roc_agent_chats WHERE created_at < ?')->execute([gmdate('Y-m-d H:i:s', time() - 86400 * $S['keep_days'])]);
} catch (\Throwable $e) { error_log('agent log: ' . $e->getMessage()); }

rocAgentOut(200, ['success' => true, 'id' => $chatId ?? 0, 'reply' => $a['reply'], 'game' => $a['game'], 'products' => $a['products'], 'ms' => (int)((microtime(true) - $t0) * 1000)]);
