<?php
/**
 * Run On Console — CMS > Social posts > Auto-post (Deploy 95; administrators). See social-auto-lib.php.
 *
 *   GET                                       status: connected Page and Instagram, on/off, last error, log (never the token)
 *   POST {action:'connect', token, page_id?}  saves the Page from a pasted token (Page token or long-lived user token)
 *   POST {action:'settings', on, fb, ig}      turns auto-posting and each platform on or off
 *   POST {action:'now'}                       posts the next waiting kit at once (skips the hourly gap)
 *   POST {action:'disconnect'}                forgets the token and turns auto-posting off
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/social-auto-lib.php';

$session = requireCmsAdmin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function rocSaOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }

$pdo = getDBConnection();
if (!$pdo) rocSaOut(503, ['success' => false, 'error' => 'Database connection unavailable.']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/** What the CMS may see: everything but the token. */
function rocSaStatus(array $a): array {
    $day = (new DateTime('now', new DateTimeZone('Asia/Karachi')))->format('Y-m-d');
    return ['connected' => !empty($a['page_token']), 'on' => !empty($a['on']), 'fb' => !empty($a['fb']), 'ig' => !empty($a['ig']),
            'page_id' => (string)($a['page_id'] ?? ''), 'page_name' => (string)($a['page_name'] ?? ''),
            'ig_id' => (string)($a['ig_id'] ?? ''), 'ig_name' => (string)($a['ig_name'] ?? ''), 'ig_ok' => !isset($a['ig_ok']) || !empty($a['ig_ok']),
            'expires' => (int)($a['expires'] ?? -1), 'error' => (string)($a['error'] ?? ''), 'today' => (object)($a['day'][$day] ?? []),
            'gap_minutes' => ROC_AUTO_GAP / 60, 'day_max' => ROC_AUTO_DAY_MAX, 'log' => array_values((array)($a['log'] ?? []))];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') rocSaOut(200, ['success' => true] + rocSaStatus(rocAutoGet($pdo)));
if ($method !== 'POST') rocSaOut(405, ['success' => false, 'error' => 'Use GET or POST.']);
$sentCsrf = (string)($_SERVER['HTTP_X_CMS_CSRF_TOKEN'] ?? '');
if ($sentCsrf === '' || (is_array($session) && !empty($session['csrf_token']) && !hash_equals((string)$session['csrf_token'], $sentCsrf)))
    rocSaOut(403, ['success' => false, 'error' => 'Invalid or missing CMS CSRF token.']);

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$uid = is_array($session) ? ((int)($session['user_id'] ?? 0) ?: null) : null;
$a = rocAutoGet($pdo);
$act = (string)($in['action'] ?? '');

if ($act === 'connect') {
    $token = trim((string)($in['token'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9]{40,600}$/', $token)) rocSaOut(422, ['success' => false, 'error' => 'Paste the access token from Meta (long letters and numbers, starts with EAA).']);
    $c = rocAutoConnect($token, preg_replace('/\D/', '', (string)($in['page_id'] ?? '')));
    if (isset($c['error'])) rocSaOut(422, ['success' => false, 'error' => $c['error'], 'pages' => $c['pages'] ?? []]);
    $samePage = ($a['page_id'] ?? '') === $c['page_id'];
    $a = array_merge($a, $c, ['fb' => $samePage ? !empty($a['fb']) : true, 'ig' => $c['ig_id'] !== '' && ($samePage ? !empty($a['ig']) || empty($a['ig_id']) : true), 'error' => '']);
    rocAutoSave($pdo, $a, $uid);
    if (function_exists('logCmsAudit')) logCmsAudit('cms_social_auto_connect', 'settings', 'social_auto', ['page' => $c['page_name']]);
    rocSaOut(200, ['success' => true, 'message' => 'Connected: ' . $c['page_name'] . ($c['ig_name'] !== '' ? ' and Instagram @' . $c['ig_name'] : ' (no Instagram account is linked to this Page)') . '.'] + rocSaStatus($a));
}
if ($act === 'settings') {
    if (empty($a['page_token'])) rocSaOut(409, ['success' => false, 'error' => 'Connect the Facebook Page first.']);
    $on = !empty($in['on']);
    // Turning it on starts from now: older kits stay for posting by hand, so the Page is not flooded.
    if ($on && empty($a['on'])) $a['from'] = time() - 6 * 3600;
    $a['on'] = $on; $a['fb'] = !empty($in['fb']); $a['ig'] = !empty($in['ig']) && !empty($a['ig_id']);
    if ($on) $a['error'] = '';
    rocAutoSave($pdo, $a, $uid);
    rocSaOut(200, ['success' => true, 'message' => $on ? 'Auto-posting is on: new kits go out by themselves, one post per platform an hour at most.' : 'Auto-posting is off.'] + rocSaStatus($a));
}
if ($act === 'now') {
    if (empty($a['on'])) rocSaOut(409, ['success' => false, 'error' => 'Turn auto-posting on first.']);
    @set_time_limit(120);
    $line = rocAutoTick($pdo, true);
    rocSaOut(200, ['success' => true, 'message' => $line ?? 'Nothing waiting: every kit made since auto-posting was turned on is already posted.'] + rocSaStatus(rocAutoGet($pdo)));
}
if ($act === 'disconnect') {
    $a = ['on' => false, 'log' => $a['log'] ?? []];
    rocAutoSave($pdo, $a, $uid);
    if (function_exists('logCmsAudit')) logCmsAudit('cms_social_auto_disconnect', 'settings', 'social_auto');
    rocSaOut(200, ['success' => true, 'message' => 'Disconnected: the token is deleted from the website.'] + rocSaStatus($a));
}
rocSaOut(400, ['success' => false, 'error' => 'Unknown action.']);
