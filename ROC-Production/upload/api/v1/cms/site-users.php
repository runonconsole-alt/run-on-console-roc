<?php
/**
 * Run On Console — website members (people who signed up on runonconsole.com) for the CMS.
 * Administrators only. Read-only, except suspending / restoring an account.
 *
 * GET  /api/v1/cms/site-users.php                         list (newest first) + counts
 * POST {action:'set_status', id, status:'active'|'suspended'}
 *
 * Passwords, tokens and session data are never sent.
 */

require_once __DIR__ . '/config.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
header('Content-Type: application/json; charset=utf-8');
if (!$pdo) { http_response_code(503); echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']); exit; }

function rocSuOut(int $code, array $d): void { http_response_code($code); echo json_encode($d); exit; }
function rocSuCols(PDO $pdo, string $t): array {
    try { $st = $pdo->query("SELECT * FROM {$t} LIMIT 0"); } catch (\Throwable $e) { return []; }
    $c = []; for ($i = 0; $i < $st->columnCount(); $i++) $c[] = strtolower((string)$st->getColumnMeta($i)['name']);
    return $c;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    if (($in['action'] ?? '') !== 'set_status') rocSuOut(400, ['success' => false, 'error' => 'Unknown action.']);
    $status = (string)($in['status'] ?? '');
    if (!in_array($status, ['active', 'suspended'], true)) rocSuOut(422, ['success' => false, 'error' => 'Unknown status.']);
    $id = (int)($in['id'] ?? 0);
    $q = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
    $q->execute([$status, $id]);
    if (!$q->rowCount()) rocSuOut(404, ['success' => false, 'error' => 'User not found.']);
    if ($status === 'suspended') {
        // Sign them out everywhere.
        try { $pdo->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$id]); } catch (\Throwable $e) {}
    }
    if (function_exists('logCmsAudit')) logCmsAudit('cms_site_user_status', 'user', (string)$id, ['status' => $status]);
    rocSuOut(200, ['success' => true]);
}

$u = rocSuCols($pdo, 'users');
$p = rocSuCols($pdo, 'user_profiles');
$g = rocSuCols($pdo, 'roc_gaming_profiles');
$pick = function (array $have, string $alias, array $want) {
    $out = [];
    foreach ($want as $col => $as) $out[] = in_array($col, $have, true) ? "{$alias}.{$col} AS {$as}" : "NULL AS {$as}";
    return implode(', ', $out);
};
$sql = 'SELECT u.id, ' . $pick($u, 'u', ['name' => 'name', 'username' => 'username', 'email' => 'email', 'email_verified' => 'email_verified',
            'status' => 'status', 'created_at' => 'created_at', 'is_admin' => 'is_admin'])
     . ($p ? ', ' . $pick($p, 'p', ['country' => 'country', 'bio' => 'bio', 'avatar_url' => 'avatar_url', 'avatar_icon' => 'avatar_icon']) : ', NULL AS country, NULL AS bio, NULL AS avatar_url, NULL AS avatar_icon')
     . ($g ? ', ' . $pick($g, 'g', ['city' => 'city', 'favorite_games' => 'favorite_games']) : ', NULL AS city, NULL AS favorite_games')
     . ' FROM users u'
     . ($p ? ' LEFT JOIN user_profiles p ON p.user_id = u.id' : '')
     . ($g ? ' LEFT JOIN roc_gaming_profiles g ON g.user_id = u.id' : '')
     . ' ORDER BY u.id DESC LIMIT 1000';
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Last sign-in, when the sessions table has a creation time.
$last = [];
if (in_array('created_at', rocSuCols($pdo, 'sessions'), true)) {
    foreach ($pdo->query('SELECT user_id, MAX(created_at) AS t FROM sessions GROUP BY user_id')->fetchAll(PDO::FETCH_ASSOC) as $r) $last[(int)$r['user_id']] = $r['t'];
}
$week = date('Y-m-d H:i:s', time() - 7 * 86400);
$counts = ['all' => count($rows), 'verified' => 0, 'complete' => 0, 'new_week' => 0, 'suspended' => 0];
foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['email_verified'] = (bool)$r['email_verified'];
    $r['is_admin'] = (bool)$r['is_admin'];
    $r['avatar_url'] = preg_match('#^/uploads/avatars/[a-f0-9]{32}\.webp$#', (string)$r['avatar_url']) ? $r['avatar_url'] : '';
    $r['favorite_games'] = json_decode((string)($r['favorite_games'] ?? '[]'), true) ?: [];
    $r['last_login'] = $last[$r['id']] ?? null;
    $r['complete'] = mb_strlen(trim((string)$r['bio'])) >= 20 && trim((string)$r['city']) !== '' && trim((string)$r['country']) !== '';
    if ($r['email_verified']) $counts['verified']++;
    if ($r['complete']) $counts['complete']++;
    if ((string)$r['created_at'] >= $week) $counts['new_week']++;
    if ($r['status'] === 'suspended') $counts['suspended']++;
}
unset($r);
rocSuOut(200, ['success' => true, 'users' => $rows, 'counts' => $counts]);
