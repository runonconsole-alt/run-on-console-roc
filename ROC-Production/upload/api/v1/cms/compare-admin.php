<?php
/**
 * Run On Console — CMS > Comparisons (Deploy 88): the "X vs Y" pages.
 *
 *   GET  compare-admin.php                       every pair: products, category, written or not, hidden
 *   POST {slug, summary, differences[], pick_a, pick_b, faq[{q,a}], hidden}   saves the text of one page
 *   POST {slug, rewrite: true}                   the writer writes it again (about 20 seconds)
 *   POST {slug, hidden: true|false, only_hidden: true}   shows / hides a page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/compare-lib.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$session = requireCmsPermission('products', $method === 'GET' ? 'view' : 'edit');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function rocCaOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }

$pdo = getDBConnection();
if (!$pdo) rocCaOut(503, ['success' => false, 'error' => 'Database connection unavailable.']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pairs = rocComparePairs($pdo);
$texts = rocCompareTexts($pdo);

if ($method === 'GET') {
    $cats = [];
    try { foreach ($pdo->query('SELECT slug, name FROM product_categories')->fetchAll(PDO::FETCH_ASSOC) as $c) $cats[$c['slug']] = $c['name']; } catch (\Throwable $e) {}
    $rows = [];
    foreach ($pairs as $slug => [$a, $b, $c]) {
        $t = (array)($texts[$slug] ?? []);
        $rows[] = ['slug' => $slug, 'a' => $a['title'], 'b' => $b['title'], 'category' => $cats[$c] ?? $c, 'written' => !empty($t['summary']), 'hidden' => !empty($t['hidden']),
                   't' => (int)($t['t'] ?? 0), 'text' => $t ? array_intersect_key($t, array_flip(['summary', 'differences', 'pick_a', 'pick_b', 'faq'])) : null];
    }
    rocCaOut(200, ['success' => true, 'pairs' => $rows, 'daily' => ROC_COMPARE_DAILY]);
}
if ($method !== 'POST') rocCaOut(405, ['success' => false, 'error' => 'Use GET or POST.']);
$sentCsrf = (string)($_SERVER['HTTP_X_CMS_CSRF_TOKEN'] ?? '');
if ($sentCsrf === '' || (is_array($session) && !empty($session['csrf_token']) && !hash_equals((string)$session['csrf_token'], $sentCsrf)))
    rocCaOut(403, ['success' => false, 'error' => 'Invalid or missing CMS CSRF token.']);

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$slug = (string)($in['slug'] ?? '');
if (!isset($pairs[$slug])) rocCaOut(404, ['success' => false, 'error' => 'This comparison does not exist (its products may have changed order in the category).']);

if (!empty($in['rewrite'])) {
    @set_time_limit(180);
    $keepHidden = !empty($texts[$slug]['hidden']);
    $r = rocCompareWrite($pdo, $slug, $pairs[$slug]);
    if (!$r['ok']) rocCaOut(502, ['success' => false, 'error' => 'The writer could not write it now: ' . $r['error']]);
    $texts = rocCompareTexts($pdo);
    if ($keepHidden) { $texts[$slug]['hidden'] = 1; rocBlogSet($pdo, 'compare_texts', $texts); }
    rocCaOut(200, ['success' => true, 'text' => $texts[$slug], 'message' => 'Written again. The page shows the new text.']);
}

$t = (array)($texts[$slug] ?? []);
if (!empty($in['only_hidden'])) {
    if (empty($t['summary']) && empty($in['hidden'])) rocCaOut(422, ['success' => false, 'error' => 'This page has no text yet: write it first.']);
    if (!empty($in['hidden'])) $t['hidden'] = 1; else unset($t['hidden']);
} else {
    $clip = function ($v, int $n) { return mb_substr(trim(str_replace("\r", '', (string)$v)), 0, $n); };
    $summary = $clip($in['summary'] ?? '', 800);
    if ($summary === '') rocCaOut(422, ['success' => false, 'error' => 'The short answer (summary) cannot be empty.']);
    $list = array_values(array_filter(array_map(function ($x) use ($clip) { return $clip($x, 260); }, (array)($in['differences'] ?? [])), 'strlen'));
    $faq = [];
    foreach ((array)($in['faq'] ?? []) as $f) {
        $q = $clip($f['q'] ?? '', 160); $a = $clip($f['a'] ?? '', 600);
        if ($q !== '' && $a !== '') $faq[] = ['q' => $q, 'a' => $a];
    }
    $t = ['summary' => $summary, 'differences' => array_slice($list, 0, 8), 'pick_a' => $clip($in['pick_a'] ?? '', 400), 'pick_b' => $clip($in['pick_b'] ?? '', 400),
          'faq' => array_slice($faq, 0, 6), 't' => time(), 'edited' => 1] + (!empty($in['hidden']) ? ['hidden' => 1] : []);
}
$texts[$slug] = $t;
rocBlogSet($pdo, 'compare_texts', $texts, is_array($session) ? ((int)($session['user_id'] ?? 0) ?: null) : null);
if (function_exists('logCmsAudit')) logCmsAudit('cms_compare_edit', 'compare', $slug);
rocCaOut(200, ['success' => true, 'text' => $t, 'message' => !empty($t['hidden']) ? 'Saved: this page is hidden (404).' : 'Saved. The page shows it now.']);
