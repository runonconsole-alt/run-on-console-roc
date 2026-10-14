<?php
/**
 * Run On Console (ROC) — CMS > ROC assistant (administrators)
 *
 * GET  ?q=word&page=N    settings, numbers (today, 7 days) and the saved chats, newest first (50 a page)
 * POST {settings: {on, daily, hourly_ip, keep_days}}   save the settings
 * POST {action: 'clear'}                                delete every saved chat
 * Chats are saved without personal details by api/v1/agent.php.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/blog-engine-lib.php';
require_once dirname(__DIR__) . '/agent-lib.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
rocAgentTable($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $d = json_decode((string)file_get_contents('php://input'), true) ?: [];
    if (($d['action'] ?? '') === 'clear') {
        $n = $pdo->exec('DELETE FROM roc_agent_chats');
        logCmsAudit('cms_agent_chats_clear', 'settings', 'agent', ['deleted' => $n]);
        echo json_encode(['success' => true, 'message' => 'All saved chats deleted.']); exit;
    }
    $v = (array)($d['settings'] ?? []);
    $s = ['on' => !empty($v['on']), 'daily' => max(0, min(5000, (int)($v['daily'] ?? 400))),
          'hourly_ip' => max(1, min(200, (int)($v['hourly_ip'] ?? 30))), 'keep_days' => max(7, min(365, (int)($v['keep_days'] ?? 90)))];
    rocBlogSet($pdo, 'agent_settings', $s, (int)$session['user_id']);
    logCmsAudit('cms_agent_settings', 'settings', 'agent', $s);
    echo json_encode(['success' => true, 'settings' => $s, 'message' => $s['on'] ? 'Saved. ROC is on.' : 'Saved. ROC is off: the chat button is hidden on the website.']); exit;
}

$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));
$page = max(1, (int)($_GET['page'] ?? 1));
$where = ''; $args = [];
$down = !empty($_GET['down']);   // only the answers a member marked 👎
if ($q !== '') { $where = 'WHERE c.question LIKE ? OR c.answer LIKE ? OR c.game LIKE ? OR c.products LIKE ? OR u.username LIKE ? OR u.name LIKE ?'; $like = '%' . $q . '%'; $args = [$like, $like, $like, $like, $like, $like]; }
// Who chatted: the member's name and username (the chats themselves hold no personal details).
$users = true;
try { $pdo->query('SELECT id, name, username FROM users LIMIT 1'); } catch (\Throwable $e) { $users = false; }
$from = $users ? 'roc_agent_chats c LEFT JOIN users u ON u.id = c.user_id' : 'roc_agent_chats c';
if (!$users && $where !== '') { $where = str_replace(' OR u.username LIKE ? OR u.name LIKE ?', '', $where); $args = array_slice($args, 0, 4); }
if ($down) { $where = ($where === '' ? 'WHERE ' : $where . ' AND ') . 'c.vote = -1'; }
$c = $pdo->prepare("SELECT COUNT(*) FROM {$from} {$where}"); $c->execute($args); $total = (int)$c->fetchColumn();
$st = $pdo->prepare('SELECT c.id, c.created_at, c.user_id, c.session, c.page, c.question, c.answer, c.game, c.products, c.engine, c.lang, c.vote'
    . ($users ? ', u.name AS user_name, u.username AS user_username' : '') . " FROM {$from} {$where} ORDER BY c.id DESC LIMIT 50 OFFSET " . (($page - 1) * 50));
$st->execute($args);
$count = function (string $sql, array $a) use ($pdo) { $x = $pdo->prepare($sql); $x->execute($a); return (int)$x->fetchColumn(); };
$today = gmdate('Y-m-d') . ' 00:00:00'; $week = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
echo json_encode([
    'success' => true,
    'settings' => rocAgentSettings($pdo),
    'groq' => trim((string)rocBlogGet($pdo, 'blog_groq_key', '')) !== '',
    'last_error' => rocBlogGet($pdo, 'agent_last_error', null),
    'models' => rocBlogGet($pdo, 'agent_models', null),
    'stats' => ['today' => $count('SELECT COUNT(*) FROM roc_agent_chats WHERE created_at >= ?', [$today]),
                'today_ai' => $count("SELECT COUNT(*) FROM roc_agent_chats WHERE created_at >= ? AND engine <> 'basic'", [$today]),
                'week' => $count('SELECT COUNT(*) FROM roc_agent_chats WHERE created_at >= ?', [$week]),
                'week_visitors' => $count('SELECT COUNT(DISTINCT user_id) FROM roc_agent_chats WHERE created_at >= ?', [$week]),
                'week_up' => $count('SELECT COUNT(*) FROM roc_agent_chats WHERE created_at >= ? AND vote = 1', [$week]),
                'week_down' => $count('SELECT COUNT(*) FROM roc_agent_chats WHERE created_at >= ? AND vote = -1', [$week])],
    'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / 50)),
    'chats' => $st->fetchAll(PDO::FETCH_ASSOC),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
