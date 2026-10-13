<?php
/**
 * Run On Console (ROC) — CMS > Blog agent (administrators)
 *
 * GET                       status: is a token set, the agent's recent posts, free slots
 * POST {action:'new_token'} makes a new token (shown once; the old one stops working)
 * POST {action:'revoke'}    switches the agent off
 *
 * The writer on the PC sends posts to /api/v1/blog-agent.php with this token.
 */
require_once __DIR__ . '/config.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');

function rocBaOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }
function rocBaGet(PDO $pdo, string $k): ?string {
    try { $st = $pdo->prepare('SELECT setting_value, updated_at FROM cms_settings WHERE setting_key = ?'); $st->execute([$k]); $r = $st->fetch(PDO::FETCH_ASSOC); }
    catch (\Throwable $e) { return null; }
    return $r ? (string)$r['setting_value'] : null;
}
function rocBaSet(PDO $pdo, string $k, string $v, int $uid): void {
    $st = $pdo->prepare('SELECT COUNT(*) FROM cms_settings WHERE setting_key = ?'); $st->execute([$k]);
    if ((int)$st->fetchColumn() > 0) $pdo->prepare('UPDATE cms_settings SET setting_value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?')->execute([$v, $uid, $k]);
    else $pdo->prepare('INSERT INTO cms_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)')->execute([$k, $v, $uid]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $log = json_decode((string)rocBaGet($pdo, 'blog_agent_log'), true) ?: [];
    // Current status of each logged post.
    if ($log) {
        $st = $pdo->prepare('SELECT status FROM blogs WHERE id = ?');
        foreach ($log as &$l) { $st->execute([$l['id']]); $l['status'] = (string)($st->fetchColumn() ?: 'deleted'); }
        unset($l);
    }
    rocBaOut(200, ['success' => true, 'active' => (string)rocBaGet($pdo, 'blog_agent_token_hash') !== '',
        'created' => (string)rocBaGet($pdo, 'blog_agent_token_created'), 'posts' => $log]);
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$uid = (int)$session['user_id'];
if (($in['action'] ?? '') === 'new_token') {
    $token = 'roc_' . bin2hex(random_bytes(24));
    rocBaSet($pdo, 'blog_agent_token_hash', hash('sha256', $token), $uid);
    rocBaSet($pdo, 'blog_agent_token_created', gmdate('c'), $uid);
    logCmsAudit('cms_blog_agent_token', 'settings', 'blog_agent', ['action' => 'new']);
    rocBaOut(200, ['success' => true, 'token' => $token, 'message' => 'New token made. Copy it now: it is not shown again.']);
}
if (($in['action'] ?? '') === 'revoke') {
    rocBaSet($pdo, 'blog_agent_token_hash', '', $uid);
    logCmsAudit('cms_blog_agent_token', 'settings', 'blog_agent', ['action' => 'revoke']);
    rocBaOut(200, ['success' => true, 'message' => 'Blog agent switched off. It cannot send posts until you make a new token.']);
}
rocBaOut(400, ['success' => false, 'error' => 'Unknown request.']);
