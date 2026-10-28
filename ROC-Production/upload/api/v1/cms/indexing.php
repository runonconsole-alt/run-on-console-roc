<?php
/**
 * Run On Console — CMS > Indexing (Deploy 117): which pages Google has indexed (gsc-lib.php).
 *
 *   GET  indexing.php?filter=all|indexed|not|unchecked|new|queries&kind=&q=&page=1
 *        {key, stats, rows, error, email, checked_today}
 *   POST {action: "email", email}   where the daily "newly indexed" email goes (administrators)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/blog-engine-lib.php';
require_once __DIR__ . '/gsc-lib.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$session = $method === 'GET' ? requireCmsPermission('pages', 'view') : requireCmsPermission('pages', 'edit');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function rocIxOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }
$pdo = getDBConnection();
if (!$pdo) rocIxOut(503, ['success' => false, 'error' => 'Database connection unavailable.']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($method === 'POST') {
    $sentCsrf = (string)($_SERVER['HTTP_X_CMS_CSRF_TOKEN'] ?? '');
    if ($sentCsrf === '' || (is_array($session) && !empty($session['csrf_token']) && !hash_equals((string)$session['csrf_token'], $sentCsrf)))
        rocIxOut(403, ['success' => false, 'error' => 'Invalid or missing CMS CSRF token.']);
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    if (($session['role'] ?? '') !== 'administrator') rocIxOut(403, ['success' => false, 'error' => 'Only an administrator can change this.']);
    if (($in['action'] ?? '') === 'email') {
        $e = trim((string)($in['email'] ?? ''));
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) rocIxOut(422, ['success' => false, 'error' => 'Enter a valid email address.']);
        rocBlogSet($pdo, 'gsc_email', $e);
        rocIxOut(200, ['success' => true, 'message' => 'Saved: the daily email goes to ' . $e . '.']);
    }
    rocIxOut(422, ['success' => false, 'error' => 'Unknown action.']);
}

$key = rocGscKey();
$out = ['success' => true, 'key' => $key ? (string)$key['client_email'] : '', 'email' => (string)rocBlogGet($pdo, 'gsc_email', 'runonconsole@gmail.com')];
$err = rocBlogGet($pdo, 'gsc_error', null);
$out['error'] = is_array($err) ? $err : null;
$c = rocBlogGet($pdo, 'gsc_daily', []);
$out['checked_today'] = (is_array($c) && ($c['day'] ?? '') === gmdate('Y-m-d')) ? (int)$c['n'] : 0;
// The day's 10 products (Deploy 128, link-boost-lib.php): with a link to Search Console's URL Inspection.
try {
    require_once dirname(__DIR__) . '/link-boost-lib.php';
    $pool = rocLinkBoostUnindexed($pdo);
    $out['daily'] = array_map(function ($p) use ($pool) {
        $u = 'https://runonconsole.com' . $p;
        return ['url' => $u, 'name' => $pool[$p][1] ?? $p,
                'inspect' => 'https://search.google.com/search-console/inspect?resource_id=' . rawurlencode('https://runonconsole.com/') . '&id=' . rawurlencode($u)];
    }, rocDailyPicks($pdo));
} catch (\Throwable $e) { $out['daily'] = []; }
try { $out['stats'] = rocGscStats($pdo); } catch (\Throwable $e) { $out['stats'] = ['total' => 0, 'indexed' => 0, 'not_indexed' => 0, 'unchecked' => 0, 'by_kind' => []]; }

$filter = (string)($_GET['filter'] ?? 'new');
// Search queries (Deploy 120): what people searched, the page Google showed, clicks, impressions, position.
if ($filter === 'queries') {
    $d = rocBlogGet($pdo, 'gsc_queries', []);
    $rows = is_array($d) && is_array($d['rows'] ?? null) ? $d['rows'] : [];
    $q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
    if ($q !== '') $rows = array_values(array_filter($rows, function ($r) use ($q) { return strpos($r['q'], $q) !== false || strpos($r['p'], $q) !== false; }));
    $plan = rocBlogGet($pdo, 'blog_plan', []);
    $topics = [];
    foreach ((array)(is_array($plan) ? ($plan['topics'] ?? []) : []) as $t) if (($t['source'] ?? '') === 'gsc')
        $topics[] = ['title' => $t['title'], 'status' => $t['status'] ?? 'planned', 'added' => $t['added'] ?? '', 'impressions' => $t['impressions'] ?? 0];
    $out['queries'] = array_slice($rows, 0, 300);
    $out['queries_total'] = count($rows);
    $out['queries_range'] = is_array($d) && $d ? ['from' => $d['from'] ?? '', 'to' => $d['to'] ?? '', 'day' => $d['day'] ?? '', 'skipped' => $d['skipped'] ?? 0] : null;
    $out['gsc_topics'] = array_reverse($topics);
    // Deploy 126: how many searches are remembered, and the last 12:30 sitemap submit.
    $mem = rocBlogGet($pdo, 'gsc_query_mem', []);
    $out['remembered'] = is_array($mem) ? count($mem) : 0;
    $out['sitemap_submit'] = rocBlogGet($pdo, 'gsc_sitemap', null);
    $out['rows'] = [];
    rocIxOut(200, $out);
}
$kind = preg_replace('/[^a-z]/', '', (string)($_GET['kind'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$where = []; $args = [];
if ($filter === 'indexed') $where[] = "verdict = 'PASS'";
elseif ($filter === 'not') $where[] = "checked_at IS NOT NULL AND COALESCE(verdict, '') <> 'PASS'";
elseif ($filter === 'unchecked') $where[] = 'checked_at IS NULL';
elseif ($filter === 'new') { $where[] = 'indexed_at IS NOT NULL AND indexed_at > ?'; $args[] = gmdate('Y-m-d H:i:s', time() - 14 * 86400); }
if ($kind !== '') { $where[] = 'kind = ?'; $args[] = $kind; }
if ($q !== '') { $where[] = 'url LIKE ?'; $args[] = '%' . $q . '%'; }
$sql = 'SELECT url, kind, verdict, coverage, last_crawl, checked_at, indexed_at FROM roc_index_status' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY ' . ($filter === 'new' ? 'indexed_at DESC' : 'checked_at IS NULL, checked_at DESC') . ' LIMIT 100 OFFSET ' . (($page - 1) * 100);
try { $st = $pdo->prepare($sql); $st->execute($args); $out['rows'] = $st->fetchAll(PDO::FETCH_ASSOC); }
catch (\Throwable $e) { $out['rows'] = []; }
rocIxOut(200, $out);
