<?php
/**
 * Run On Console (ROC) — CMS > 2-step verification, for the signed-in user's own account.
 *
 * GET                                      {installed, enabled, backup_left}
 * POST {action:'start'}                    a new secret to add in the authenticator app (not on yet)
 * POST {action:'enable', code}             the first code switches it on; answers the 8 backup codes once
 * POST {action:'disable', password, code}  switches it off (password and a code, or a backup code)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/twofa-lib.php';

$session = requireCmsSession();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$uid = (int)$session['user_id'];

$installed = rocTwofaTable($pdo);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $r = $installed ? rocTwofaRow($pdo, $uid) : null;
    echo json_encode(['success' => true, 'installed' => $installed, 'enabled' => $r && (int)$r['enabled'] === 1,
                      'backup_left' => $r ? count(json_decode((string)$r['backup_json'], true) ?: []) : 0]);
    exit();
}
if (!$installed) rocCmsDeny(409, '2-step verification is not installed yet: run cli-cms-2fa.php --apply on the server.');

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$act = (string)($in['action'] ?? '');

if ($act === 'start') {
    if (rocTwofaEnabled($pdo, $uid)) rocCmsDeny(409, '2-step verification is already on. Turn it off first to set up a new phone.');
    echo json_encode(['success' => true] + rocTwofaStart($pdo, $session), JSON_UNESCAPED_SLASHES);
    exit();
}
if ($act === 'enable') {
    $codes = rocTwofaEnable($pdo, $uid, (string)($in['code'] ?? ''));
    if (!$codes) rocCmsDeny(422, 'That code is not right. Check that the phone\'s time is set automatically and type the newest code.');
    logCmsAudit('cms_2fa_enabled', 'cms_user', (string)$uid);
    echo json_encode(['success' => true, 'backup_codes' => $codes, 'message' => '2-step verification is on.']);
    exit();
}
if ($act === 'disable') {
    $st = $pdo->prepare('SELECT password_hash FROM cms_users WHERE id = ?'); $st->execute([$uid]);
    if (!password_verify((string)($in['password'] ?? ''), (string)$st->fetchColumn())) rocCmsDeny(400, 'Your password is not correct.');
    $v = rocTwofaVerify($pdo, $uid, (string)($in['code'] ?? ''));
    if (!$v['ok']) rocCmsDeny(422, $v['error']);
    rocTwofaDisable($pdo, $uid);
    logCmsAudit('cms_2fa_disabled', 'cms_user', (string)$uid);
    echo json_encode(['success' => true, 'message' => '2-step verification is off.']);
    exit();
}
rocCmsDeny(400, 'Unknown action.');
