<?php
/**
 * Run On Console (ROC) — CMS > Analytics: Google Analytics 4 and Search Console data.
 *
 * Reads the data with a Google service account (read-only scopes). Its JSON key is uploaded
 * once in the CMS and kept OUTSIDE the website (/home2/runoncon/roc-google-sa.json, mode 600);
 * it is never sent back to the browser.
 *
 * GET  ?days=7|28|90[&refresh=1]   setup state + the report (cached for 1 hour)
 * POST {action:'key', json}        saves the service account key
 * POST {action:'settings', ga4_property, gsc_site}
 * POST {action:'remove'}           deletes the saved key
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/site-layer-lib.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
define('ROC_GA_KEY', $HOME . '/roc-google-sa.json');
define('ROC_GA_CACHE', $HOME . '/roc-cms-cache');

function gaOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }
function gaKey(): ?array {
    $j = is_file(ROC_GA_KEY) ? json_decode((string)file_get_contents(ROC_GA_KEY), true) : null;
    return is_array($j) && !empty($j['client_email']) && !empty($j['private_key']) ? $j : null;
}
function gaB64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

/** Access token from the service account (signed JWT), cached until it expires. */
function gaToken(array $key, ?string &$err = null): ?string {
    if (!is_dir(ROC_GA_CACHE)) @mkdir(ROC_GA_CACHE, 0700, true);
    $cf = ROC_GA_CACHE . '/google-token.json';
    $c = is_file($cf) ? json_decode((string)file_get_contents($cf), true) : null;
    if (is_array($c) && ($c['email'] ?? '') === $key['client_email'] && ($c['exp'] ?? 0) > time() + 60) return $c['token'];
    $now = time();
    $claims = ['iss' => $key['client_email'], 'scope' => 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly',
               'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600];
    $unsigned = gaB64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . gaB64(json_encode($claims));
    $pk = openssl_pkey_get_private((string)$key['private_key']);
    if (!$pk || !openssl_sign($unsigned, $sig, $pk, OPENSSL_ALGO_SHA256)) { $err = 'The key file could not be read (private key).'; return null; }
    $r = gaHttp('POST', 'https://oauth2.googleapis.com/token', null,
        http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . gaB64($sig)]), 'application/x-www-form-urlencoded');
    if (!$r['ok'] || empty($r['data']['access_token'])) { $err = 'Google did not accept the key: ' . $r['error']; return null; }
    @file_put_contents($cf, json_encode(['email' => $key['client_email'], 'token' => $r['data']['access_token'], 'exp' => $now + (int)($r['data']['expires_in'] ?? 3600)]));
    @chmod($cf, 0600);
    return $r['data']['access_token'];
}

function gaHttp(string $method, string $url, ?string $token, $body = null, string $type = 'application/json'): array {
    $c = curl_init($url);
    $h = ['Accept: application/json'];
    if ($token) $h[] = 'Authorization: Bearer ' . $token;
    if ($body !== null) { $h[] = 'Content-Type: ' . $type; curl_setopt($c, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body)); }
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10]);
    $raw = (string)curl_exec($c);
    $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    $cerr = curl_error($c);
    curl_close($c);
    $j = json_decode($raw, true);
    if ($code < 200 || $code >= 300) {
        $m = is_array($j) ? (string)($j['error']['message'] ?? ($j['error_description'] ?? ($j['error'] ?? ''))) : '';
        return ['ok' => false, 'code' => $code, 'error' => $m !== '' ? $m : ($cerr ?: "HTTP {$code}")];
    }
    return ['ok' => true, 'data' => is_array($j) ? $j : []];
}

/** GA4 report rows as [[dims..., metrics...]]. */
function gaRun(string $token, string $prop, array $req): array {
    $r = gaHttp('POST', 'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode($prop) . ':runReport', $token, $req);
    if (!$r['ok']) return ['ok' => false, 'error' => $r['error']];
    $rows = [];
    foreach ((array)($r['data']['rows'] ?? []) as $row) {
        $o = [];
        foreach ((array)($row['dimensionValues'] ?? []) as $d) $o[] = (string)($d['value'] ?? '');
        foreach ((array)($row['metricValues'] ?? []) as $m) $o[] = (float)($m['value'] ?? 0);
        $rows[] = $o;
    }
    return ['ok' => true, 'rows' => $rows];
}

function gscRun(string $token, string $site, array $req): array {
    $r = gaHttp('POST', 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/searchAnalytics/query', $token, $req);
    if (!$r['ok']) return ['ok' => false, 'error' => $r['error']];
    return ['ok' => true, 'rows' => (array)($r['data']['rows'] ?? [])];
}

function gaReport(array $key, string $prop, string $site, int $days): array {
    $err = null;
    $token = gaToken($key, $err);
    if (!$token) return ['error' => $err];
    $out = ['days' => $days, 'made' => gmdate('c')];
    $cur = ['startDate' => $days . 'daysAgo', 'endDate' => 'yesterday'];
    $prev = ['startDate' => (2 * $days) . 'daysAgo', 'endDate' => ($days + 1) . 'daysAgo'];

    // Google Analytics 4
    if ($prop !== '') {
        $m = [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews'], ['name' => 'averageSessionDuration'], ['name' => 'engagementRate']];
        $t = gaRun($token, $prop, ['dateRanges' => [$cur, $prev], 'metrics' => $m]);
        if (!$t['ok']) $out['ga'] = ['error' => $t['error']];
        else {
            $now = $t['rows'][0] ?? []; $was = $t['rows'][1] ?? [];
            // With two date ranges GA4 adds a "dateRange" dimension as the first column.
            $pick = function ($row) { return array_values(array_filter($row, 'is_float')); };
            $now = $pick($now); $was = $pick($was);
            if (($t['rows'][0][0] ?? '') === 'date_range_1') { [$now, $was] = [$was, $now]; }
            $names = ['users', 'sessions', 'views', 'avg_time', 'engagement'];
            $tot = [];
            foreach ($names as $i => $n) $tot[$n] = ['now' => $now[$i] ?? 0, 'before' => $was[$i] ?? 0];
            $daily = gaRun($token, $prop, ['dateRanges' => [$cur], 'dimensions' => [['name' => 'date']], 'metrics' => [['name' => 'activeUsers'], ['name' => 'screenPageViews']],
                                           'orderBys' => [['dimension' => ['dimensionName' => 'date']]]]);
            $pages = gaRun($token, $prop, ['dateRanges' => [$cur], 'dimensions' => [['name' => 'pagePath']], 'metrics' => [['name' => 'screenPageViews'], ['name' => 'activeUsers']],
                                           'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]], 'limit' => 15]);
            $src = gaRun($token, $prop, ['dateRanges' => [$cur], 'dimensions' => [['name' => 'sessionDefaultChannelGroup']], 'metrics' => [['name' => 'sessions']],
                                         'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => 10]);
            $cty = gaRun($token, $prop, ['dateRanges' => [$cur], 'dimensions' => [['name' => 'country']], 'metrics' => [['name' => 'activeUsers']],
                                         'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]], 'limit' => 10]);
            $dev = gaRun($token, $prop, ['dateRanges' => [$cur], 'dimensions' => [['name' => 'deviceCategory']], 'metrics' => [['name' => 'activeUsers']]]);
            $out['ga'] = ['totals' => $tot, 'daily' => $daily['rows'] ?? [], 'pages' => $pages['rows'] ?? [], 'sources' => $src['rows'] ?? [],
                          'countries' => $cty['rows'] ?? [], 'devices' => $dev['rows'] ?? []];
        }
    }

    // Search Console (its data is about 2-3 days behind)
    if ($site !== '') {
        $end = gmdate('Y-m-d', strtotime('-3 days'));
        $start = gmdate('Y-m-d', strtotime('-' . ($days + 2) . ' days'));
        $pEnd = gmdate('Y-m-d', strtotime('-' . ($days + 3) . ' days'));
        $pStart = gmdate('Y-m-d', strtotime('-' . (2 * $days + 2) . ' days'));
        $tot = gscRun($token, $site, ['startDate' => $start, 'endDate' => $end]);
        if (!$tot['ok']) $out['gsc'] = ['error' => $tot['error']];
        else {
            $before = gscRun($token, $site, ['startDate' => $pStart, 'endDate' => $pEnd]);
            $a = $tot['rows'][0] ?? []; $b = ($before['rows'] ?? [])[0] ?? [];
            $mk = function ($k) use ($a, $b) { return ['now' => (float)($a[$k] ?? 0), 'before' => (float)($b[$k] ?? 0)]; };
            $daily = gscRun($token, $site, ['startDate' => $start, 'endDate' => $end, 'dimensions' => ['date']]);
            $q = gscRun($token, $site, ['startDate' => $start, 'endDate' => $end, 'dimensions' => ['query'], 'rowLimit' => 25]);
            $pg = gscRun($token, $site, ['startDate' => $start, 'endDate' => $end, 'dimensions' => ['page'], 'rowLimit' => 15]);
            $slim = function ($rows) { return array_map(function ($r) { return [(string)($r['keys'][0] ?? ''), (int)$r['clicks'], (int)$r['impressions'], round((float)$r['ctr'] * 100, 1), round((float)$r['position'], 1)]; }, (array)$rows); };
            $out['gsc'] = ['from' => $start, 'to' => $end, 'totals' => ['clicks' => $mk('clicks'), 'impressions' => $mk('impressions'), 'ctr' => $mk('ctr'), 'position' => $mk('position')],
                           'daily' => $slim($daily['rows'] ?? []), 'queries' => $slim($q['rows'] ?? []), 'pages' => $slim($pg['rows'] ?? [])];
        }
    }
    return $out;
}

$prop = (string)(rocLayerSettingGet($pdo, 'ga4_property_id') ?? '');
$site = (string)(rocLayerSettingGet($pdo, 'gsc_site_url') ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $key = gaKey();
    $res = ['success' => true, 'key' => $key ? ['email' => $key['client_email'], 'project' => (string)($key['project_id'] ?? '')] : null,
            'ga4_property' => $prop, 'gsc_site' => $site];
    if ($key && isset($_GET['sites'])) {
        $e = null; $tk = gaToken($key, $e);
        $r = $tk ? gaHttp('GET', 'https://www.googleapis.com/webmasters/v3/sites', $tk) : ['ok' => false, 'error' => $e];
        $res['sites'] = $r['ok'] ? array_column((array)($r['data']['siteEntry'] ?? []), 'siteUrl') : [];
        if (!$r['ok']) $res['sites_error'] = $r['error'];
        gaOut(200, $res);
    }
    if ($key && ($prop !== '' || $site !== '')) {
        $days = in_array((int)($_GET['days'] ?? 28), [7, 28, 90], true) ? (int)$_GET['days'] : 28;
        if (!is_dir(ROC_GA_CACHE)) @mkdir(ROC_GA_CACHE, 0700, true);
        $cf = ROC_GA_CACHE . '/analytics-' . $days . '-' . md5($prop . '|' . $site) . '.json';
        $c = is_file($cf) ? json_decode((string)file_get_contents($cf), true) : null;
        if (!is_array($c) || !empty($_GET['refresh']) || filemtime($cf) < time() - 3600) {
            @set_time_limit(90);
            $c = gaReport($key, $prop, $site, $days);
            if (empty($c['error'])) { @file_put_contents($cf, json_encode($c)); @chmod($cf, 0600); }
        }
        $res['report'] = $c;
    }
    gaOut(200, $res);
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$act = (string)($in['action'] ?? '');
$uid = (int)$session['user_id'];

if ($act === 'key') {
    $j = json_decode((string)($in['json'] ?? ''), true);
    if (!is_array($j) || ($j['type'] ?? '') !== 'service_account' || empty($j['client_email']) || empty($j['private_key']))
        gaOut(422, ['success' => false, 'error' => 'This is not a Google service account key file (JSON).']);
    if (!openssl_pkey_get_private((string)$j['private_key'])) gaOut(422, ['success' => false, 'error' => 'The private key in this file cannot be read.']);
    $keep = array_intersect_key($j, array_flip(['type', 'project_id', 'private_key_id', 'private_key', 'client_email', 'client_id', 'token_uri']));
    $tmp = ROC_GA_KEY . '.tmp';
    if (file_put_contents($tmp, json_encode($keep)) === false || !rename($tmp, ROC_GA_KEY)) gaOut(500, ['success' => false, 'error' => 'Could not save the key on the server.']);
    @chmod(ROC_GA_KEY, 0600);
    @unlink(ROC_GA_CACHE . '/google-token.json');
    $e = null;
    $ok = gaToken($keep, $e);
    logCmsAudit('cms_analytics_key', 'settings', 'analytics', ['email' => $keep['client_email']]);
    gaOut(200, ['success' => true, 'email' => $keep['client_email'],
        'message' => $ok ? 'Key saved and accepted by Google. Now add ' . $keep['client_email'] . ' to GA4 and Search Console.' : 'Key saved, but Google said: ' . $e]);
}
if ($act === 'settings') {
    $p = trim((string)($in['ga4_property'] ?? ''));
    if ($p !== '' && !preg_match('/^\d{6,12}$/', $p)) gaOut(422, ['success' => false, 'error' => 'The GA4 Property ID is a number of 6-12 digits (GA4 → Admin → Property details). It is not the G-XXXX measurement ID.']);
    $s = trim((string)($in['gsc_site'] ?? ''));
    if ($s !== '' && !preg_match('#^(sc-domain:[a-z0-9.\-]+|https?://[^\s"<>]+/)$#i', $s)) gaOut(422, ['success' => false, 'error' => 'Search Console property looks like sc-domain:runonconsole.com or https://runonconsole.com/']);
    rocLayerSettingSet($pdo, 'ga4_property_id', $p, $uid);
    rocLayerSettingSet($pdo, 'gsc_site_url', $s, $uid);
    foreach (glob(ROC_GA_CACHE . '/analytics-*.json') ?: [] as $f) @unlink($f);
    gaOut(200, ['success' => true, 'message' => 'Saved.']);
}
if ($act === 'remove') {
    @unlink(ROC_GA_KEY);
    foreach (glob(ROC_GA_CACHE . '/*.json') ?: [] as $f) @unlink($f);
    logCmsAudit('cms_analytics_key', 'settings', 'analytics', ['action' => 'remove']);
    gaOut(200, ['success' => true, 'message' => 'Key removed from the server.']);
}
gaOut(400, ['success' => false, 'error' => 'Unknown request.']);
