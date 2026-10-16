<?php
/**
 * Run On Console — CMS > Games: changes to the "Can I run" game pages (Deploy 85).
 *
 *   GET  games.php                      the saved changes: {edits: {slug: {...}}}
 *   POST games.php {slug, title, description, intro, text, hidden, no_picture}
 *        saves the changes of one game (empty fields = the automatic text); {slug, reset: true} removes them
 *
 * The game pages are drawn from roc-steam-games.json by game-render.php; these changes sit on top
 * (cms_settings game_edits). Hidden games give 404 and leave the sitemap and the lists.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/blog-engine-lib.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$session = requireCmsPermission('pages', $method === 'GET' ? 'view' : 'edit');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function rocGeOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }

$pdo = getDBConnection();
if (!$pdo) rocGeOut(503, ['success' => false, 'error' => 'Database connection unavailable.']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$edits = rocBlogGet($pdo, 'game_edits', []);
$edits = is_array($edits) ? $edits : [];

$ROOT = dirname(__DIR__, 3);
require_once $ROOT . '/api/v1/game-pages-lib.php';
if ($method === 'GET') {
    // Steam app id -> page address of every game (hidden ones too, so they can be shown again).
    $slugs = [];
    foreach (rocGpAll($ROOT) as $g) $slugs[(int)$g['appid']] = $g['slug'];
    rocGeOut(200, ['success' => true, 'edits' => (object)$edits, 'slugs' => (object)$slugs]);
}
if ($method !== 'POST') rocGeOut(405, ['success' => false, 'error' => 'Use GET or POST.']);

$sentCsrf = (string)($_SERVER['HTTP_X_CMS_CSRF_TOKEN'] ?? '');
if ($sentCsrf === '' || (is_array($session) && !empty($session['csrf_token']) && !hash_equals((string)$session['csrf_token'], $sentCsrf)))
    rocGeOut(403, ['success' => false, 'error' => 'Invalid or missing CMS CSRF token.']);

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$slug = strtolower(trim((string)($in['slug'] ?? '')));
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) rocGeOut(422, ['success' => false, 'error' => 'Unknown game.']);

// Only games that have a page.
if (!isset(rocGpAll($ROOT)[$slug])) rocGeOut(404, ['success' => false, 'error' => 'This game has no page.']);

$clip = function ($v, int $n) { return mb_substr(trim(str_replace("\r", '', (string)$v)), 0, $n); };
if (!empty($in['reset'])) {
    unset($edits[$slug]);
} else {
    $e = array_filter([
        'title' => $clip($in['title'] ?? '', 120), 'description' => $clip($in['description'] ?? '', 300),
        'intro' => $clip($in['intro'] ?? '', 800), 'text' => $clip($in['text'] ?? '', 8000),
        'hidden' => !empty($in['hidden']) ? 1 : 0, 'no_picture' => !empty($in['no_picture']) ? 1 : 0,
    ], function ($v) { return $v !== '' && $v !== 0; });
    if ($e) { $e['t'] = time(); $edits[$slug] = $e; } else unset($edits[$slug]);
}
rocBlogSet($pdo, 'game_edits', $edits, is_array($session) ? (int)($session['user_id'] ?? 0) ?: null : null);
if (function_exists('logCmsAudit')) logCmsAudit('cms_game_edit', 'game', $slug, ['fields' => array_keys((array)($edits[$slug] ?? []))]);
rocGeOut(200, ['success' => true, 'edit' => $edits[$slug] ?? null, 'message' => 'Saved. The game page shows it now.']);
