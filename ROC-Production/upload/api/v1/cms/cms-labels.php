<?php
/**
 * Run On Console — the names of the CMS sidebar items (Deploy 91; administrators rename them).
 *
 *   GET  cms-labels.php                 {labels: {view: name}}
 *   POST {view, label}                  saves one name; an empty label puts the built-in name back
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/blog-engine-lib.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$session = $method === 'GET' ? requireCmsPermission('blogs', 'view') : requireCmsAdmin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function rocClOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit(); }
$pdo = getDBConnection();
if (!$pdo) rocClOut(503, ['success' => false, 'error' => 'Database connection unavailable.']);
$labels = rocBlogGet($pdo, 'cms_nav_labels', []); $labels = is_array($labels) ? $labels : [];
if ($method === 'GET') rocClOut(200, ['success' => true, 'labels' => (object)$labels]);
if ($method !== 'POST') rocClOut(405, ['success' => false, 'error' => 'Use GET or POST.']);
$sentCsrf = (string)($_SERVER['HTTP_X_CMS_CSRF_TOKEN'] ?? '');
if ($sentCsrf === '' || (is_array($session) && !empty($session['csrf_token']) && !hash_equals((string)$session['csrf_token'], $sentCsrf)))
    rocClOut(403, ['success' => false, 'error' => 'Invalid or missing CMS CSRF token.']);
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$view = (string)($in['view'] ?? '');
if (!preg_match('/^[a-z0-9-]{2,40}$/', $view)) rocClOut(422, ['success' => false, 'error' => 'Unknown sidebar item.']);
$label = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($in['label'] ?? ''))));
if ($label === '') unset($labels[$view]); else $labels[$view] = mb_substr($label, 0, 40);
rocBlogSet($pdo, 'cms_nav_labels', $labels);
rocClOut(200, ['success' => true, 'labels' => (object)$labels, 'message' => $label === '' ? 'Name put back.' : 'Renamed.']);
