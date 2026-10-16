<?php
/**
 * Run On Console — CMS "Clear cache" button (Dashboard and Site builder, Deploy 85; administrators).
 *
 *   GET  cache-clear.php                 status: is Cloudflare set up, when was the cache last cleared,
 *                                        does a new deploy or a CMS change wait for it (the button lights up)
 *   POST {action: 'clear'}               clears everything: Cloudflare (purge everything), the server's
 *                                        PHP code cache (OPcache) and the CMS cache folder (~/roc-cms-cache)
 *   POST {action: 'cloudflare', zone_id, token}   saves the Cloudflare zone and API token (token "Cache Purge"
 *                                        only); an empty token keeps the saved one. The token is never sent back.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/blog-engine-lib.php';

$session = requireCmsAdmin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function rocCcOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }

$pdo = getDBConnection();
if (!$pdo) rocCcOut(503, ['success' => false, 'error' => 'Database connection unavailable.']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);

$cf = rocBlogGet($pdo, 'cf_cache', []); $cf = is_array($cf) ? $cf : [];
$state = rocBlogGet($pdo, 'cache_cleared', []); $state = is_array($state) ? $state : [];

/** The newest deploy on this server (roc-releases/deploy-NN.json). */
function rocCcLatestDeploy(string $home): int {
    $n = 0;
    foreach (glob($home . '/roc-releases/deploy-*.json') ?: [] as $f) if (preg_match('/deploy-(\d+)\.json$/', $f, $m)) $n = max($n, (int)$m[1]);
    return $n;
}
/**
 * When the CMS last changed what every page loads (Site builder: menus, site code, metas, announcement;
 * the Amazon tag in roc-site.json). Posts and products are not counted: pages are never kept by
 * Cloudflare, so they show at once. The writers' own settings (saved every 10 minutes) are not counted.
 */
function rocCcLastChange(PDO $pdo): int {
    try {
        $v = $pdo->query("SELECT MAX(updated_at) FROM cms_settings WHERE setting_key LIKE 'site\\_%' OR setting_key IN ('amazon_tag', 'social_links')")->fetchColumn();
        return $v ? (int)strtotime((string)$v . ' UTC') : 0;
    } catch (\Throwable $e) { return 0; }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $latest = rocCcLatestDeploy($HOME);
    $change = rocCcLastChange($pdo);
    $at = (int)($state['at'] ?? 0);
    rocCcOut(200, ['success' => true,
        'cloudflare' => !empty($cf['zone_id']) && !empty($cf['token']), 'zone_id' => (string)($cf['zone_id'] ?? ''),
        'cleared_at' => $at ?: null, 'latest_deploy' => $latest, 'cleared_deploy' => (int)($state['deploy'] ?? 0),
        // Light the button up: a deploy since the last clear, or a CMS change since then (a minute of slack:
        // clearing the cache itself saves a setting).
        'needed' => $latest > (int)($state['deploy'] ?? 0) || ($change > $at + 60),
        'reason' => $latest > (int)($state['deploy'] ?? 0) ? 'deploy' : ($change > $at + 60 ? 'change' : '')]);
}
if ($method !== 'POST') rocCcOut(405, ['success' => false, 'error' => 'Use GET or POST.']);
$sentCsrf = (string)($_SERVER['HTTP_X_CMS_CSRF_TOKEN'] ?? '');
if ($sentCsrf === '' || (is_array($session) && !empty($session['csrf_token']) && !hash_equals((string)$session['csrf_token'], $sentCsrf)))
    rocCcOut(403, ['success' => false, 'error' => 'Invalid or missing CMS CSRF token.']);

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$uid = is_array($session) ? ((int)($session['user_id'] ?? 0) ?: null) : null;

if (($in['action'] ?? '') === 'cloudflare') {
    $zone = strtolower(trim((string)($in['zone_id'] ?? '')));
    $token = trim((string)($in['token'] ?? ''));
    if (!preg_match('/^[a-f0-9]{32}$/', $zone)) rocCcOut(422, ['success' => false, 'error' => 'The Zone ID is 32 letters and numbers (Cloudflare > the site > Overview, right side).']);
    if ($token === '') $token = (string)($cf['token'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_-]{30,120}$/', $token)) rocCcOut(422, ['success' => false, 'error' => 'Paste the API token (Cloudflare > My Profile > API Tokens).']);
    // Check it once: the token must be able to read this zone.
    $r = rocCcCloudflare('GET', '/zones/' . $zone, $token);
    if (!$r['ok']) rocCcOut(422, ['success' => false, 'error' => 'Cloudflare refused this token: ' . $r['error']]);
    rocBlogSet($pdo, 'cf_cache', ['zone_id' => $zone, 'token' => $token, 'saved' => time()], $uid);
    if (function_exists('logCmsAudit')) logCmsAudit('cms_cache_cloudflare', 'settings', 'cloudflare');
    rocCcOut(200, ['success' => true, 'message' => 'Cloudflare is connected. The Clear cache button now purges it too.']);
}

if (($in['action'] ?? '') !== 'clear') rocCcOut(422, ['success' => false, 'error' => 'Unknown action.']);
$done = []; $warn = [];

// 1. Cloudflare: everything (pages, pictures, roc-nav.js, CSS) is fetched fresh from the server again.
if (!empty($cf['zone_id']) && !empty($cf['token'])) {
    $r = rocCcCloudflare('POST', '/zones/' . $cf['zone_id'] . '/purge_cache', (string)$cf['token'], ['purge_everything' => true]);
    if ($r['ok']) $done[] = 'Cloudflare cache purged';
    else $warn[] = 'Cloudflare: ' . $r['error'];
} else $warn[] = 'Cloudflare is not connected yet (Dashboard > Website cache > Connect Cloudflare): only the server cache was cleared';

// 2. PHP code cache: new PHP files from a deploy are used at once.
if (function_exists('opcache_reset') && @opcache_reset()) $done[] = 'Server code cache (OPcache) cleared';

// 3. The CMS cache folder (analytics, media scan, ...): built again on the next visit.
$dir = $HOME . '/roc-cms-cache';
$n = 0;
if (is_dir($dir)) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
    if ($f->isFile() && @unlink($f->getPathname())) $n++;
}
$done[] = "CMS cache cleared ({$n} file(s))";
// The model lists of the writers and ROC are read again from Groq.
foreach (['agent_models', 'content_models'] as $k) { try { rocBlogSet($pdo, $k, [], $uid); } catch (\Throwable $e) {} }

rocBlogSet($pdo, 'cache_cleared', ['at' => time(), 'deploy' => rocCcLatestDeploy($HOME)], $uid);
if (function_exists('logCmsAudit')) logCmsAudit('cms_cache_clear', 'settings', 'cache', ['done' => $done, 'warn' => $warn]);
rocCcOut(200, ['success' => true, 'done' => $done, 'warnings' => $warn,
    'message' => implode('. ', $done) . '.' . ($warn ? ' ' . implode('. ', $warn) . '.' : '') . ' Reload the website (Ctrl+F5) to see it.']);

/** One Cloudflare API call: ['ok' => bool, 'error' => string, 'data' => array]. */
function rocCcCloudflare(string $method, string $path, string $token, ?array $body = null): array {
    $c = curl_init('https://api.cloudflare.com/client/v4' . $path);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']]);
    if ($body !== null) curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = (string)curl_exec($c); $err = curl_error($c); curl_close($c);
    $j = json_decode($raw, true);
    if (is_array($j) && !empty($j['success'])) return ['ok' => true, 'error' => '', 'data' => $j];
    $msg = is_array($j) && !empty($j['errors'][0]['message']) ? (string)$j['errors'][0]['message'] : ($err ?: 'no answer');
    return ['ok' => false, 'error' => $msg, 'data' => []];
}
