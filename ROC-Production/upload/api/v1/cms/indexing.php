<?php
/**
 * Run On Console — CMS > Indexing (Deploy 117): which pages Google has indexed (gsc-lib.php).
 *
 *   GET  indexing.php?filter=all|indexed|not|unchecked|new&kind=&q=&page=1
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
try { $out['stats'] = rocGscStats($pdo); } catch (\Throwable $e) { $out['stats'] = ['total' => 0, 'indexed' => 0, 'not_indexed' => 0, 'unchecked' => 0, 'by_kind' => []]; }

$filter = (string)($_GET['filter'] ?? 'new');
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
