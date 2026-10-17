<?php
/**
 * Run On Console — CMS > Off-page (Deploy 93): the server's own link researcher (off until turned on).
 *
 *   GET  offpage.php                          {auto, items, log}
 *   POST {action: "auto", on: true|false}     turns the daily run on or off (administrators)
 *   POST {action: "run"}                      one research run now (about 1-2 minutes; administrators)
 *   POST {action: "status", id, status}       Done / Skip / back to open
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/offpage-lib.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$session = $method === 'GET' ? requireCmsPermission('pages', 'view') : requireCmsPermission('pages', 'edit');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function rocOpOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }
$pdo = getDBConnection();
if (!$pdo) rocOpOut(503, ['success' => false, 'error' => 'Database connection unavailable.']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if ($method === 'GET') rocOpOut(200, ['success' => true, 'auto' => (string)rocBlogGet($pdo, 'offpage_auto', 'off') === 'on', 'items' => rocOpItems($pdo), 'log' => rocOpLog($pdo)]);
if ($method !== 'POST') rocOpOut(405, ['success' => false, 'error' => 'Use GET or POST.']);
$sentCsrf = (string)($_SERVER['HTTP_X_CMS_CSRF_TOKEN'] ?? '');
if ($sentCsrf === '' || (is_array($session) && !empty($session['csrf_token']) && !hash_equals((string)$session['csrf_token'], $sentCsrf)))
    rocOpOut(403, ['success' => false, 'error' => 'Invalid or missing CMS CSRF token.']);
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$admin = is_array($session) && ($session['role'] ?? '') === 'administrator';
switch ((string)($in['action'] ?? '')) {
    case 'auto':
        if (!$admin) rocOpOut(403, ['success' => false, 'error' => 'Only an administrator can turn it on or off.']);
        rocBlogSet($pdo, 'offpage_auto', !empty($in['on']) ? 'on' : 'off');
        rocOpOut(200, ['success' => true, 'auto' => !empty($in['on']), 'message' => !empty($in['on']) ? 'On: it runs every day after 12:00 (Pakistan time).' : 'Off.']);
    case 'run':
        if (!$admin) rocOpOut(403, ['success' => false, 'error' => 'Only an administrator can run it.']);
        $line = rocOpRun($pdo);
        rocOpOut(200, ['success' => true, 'message' => $line, 'items' => rocOpItems($pdo), 'log' => rocOpLog($pdo)]);
    case 'status':
        $items = rocOpItems($pdo); $id = (string)($in['id'] ?? ''); $st = in_array($in['status'] ?? '', ['done', 'skip'], true) ? $in['status'] : '';
        foreach ($items as &$it) if ($it['id'] === $id) $it['status'] = $st;
        unset($it);
        rocBlogSet($pdo, 'offpage_opps', $items);
        rocOpOut(200, ['success' => true]);
}
rocOpOut(422, ['success' => false, 'error' => 'Unknown action.']);
