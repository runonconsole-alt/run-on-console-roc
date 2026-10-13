<?php
/**
 * Run On Console (ROC) — announcement bar and site code (administrators)
 *
 * GET  /api/v1/cms/site-layer.php
 * POST {part:'announcement', value:{enabled, icon, title, text, link_url, link_text, bg, fg, dismissible, ends_at}}
 * POST {part:'code', value:{css, head_html, body_top_html, body_end_html}}
 *
 * Saving publishes cms-templates/site-layer.json and rewrites the built pages, so the
 * change is live on every page right away (see site-layer-lib.php).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/site-layer-lib.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');
$ROOT = realpath(__DIR__ . '/../../..') ?: dirname(__DIR__, 3);

function rocSlOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $L = rocLayerFromDb($pdo, $ROOT);
    rocSlOut(200, ['success' => true, 'announcement' => $L['announcement'], 'code' => $L['code'],
        'live' => rocLayerAnnouncementOn($L['announcement']), 'pages' => count(rocLayerStaticFiles($ROOT))]);
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$part = (string)($in['part'] ?? '');
$v = is_array($in['value'] ?? null) ? $in['value'] : [];
$errors = [];

if ($part === 'announcement') {
    $s = function ($k, $max) use ($v) { return trim(mb_substr(preg_replace('/[\r\n\t]+/', ' ', (string)($v[$k] ?? '')), 0, $max)); };
    $a = [
        'enabled' => !empty($v['enabled']), 'icon' => $s('icon', 4), 'title' => $s('title', 120), 'text' => $s('text', 300),
        'link_url' => $s('link_url', 500), 'link_text' => $s('link_text', 40), 'bg' => strtolower($s('bg', 7)), 'fg' => strtolower($s('fg', 7)),
        'dismissible' => !empty($v['dismissible']), 'ends_at' => $s('ends_at', 25),
    ];
    foreach (['bg', 'fg'] as $c) if (!preg_match('/^#[0-9a-f]{6}$/', $a[$c])) $errors[$c] = 'Use a colour like #e4ff1a.';
    if ($a['link_url'] !== '' && !preg_match('#^(https?://[^\s"<>]+|/[^\s"<>]*)$#i', $a['link_url'])) $errors['link_url'] = 'Use a full link (https://…) or a page on this site (/products/).';
    if ($a['ends_at'] !== '' && strtotime($a['ends_at']) === false) $errors['ends_at'] = 'Not a valid date.';
    if ($a['enabled'] && $a['title'] === '' && $a['text'] === '') $errors['text'] = 'Write a title or a message.';
    if ($errors) rocSlOut(422, ['success' => false, 'error' => 'Check the highlighted fields.', 'errors' => $errors]);
    $n = rocLayerSave($pdo, $ROOT, 'announcement', $a, (int)$session['user_id']);
    logCmsAudit('cms_announcement_update', 'settings', 'site_announcement', ['enabled' => $a['enabled'], 'title' => $a['title']]);
    rocSlOut(200, ['success' => true, 'announcement' => $a, 'live' => rocLayerAnnouncementOn($a), 'files' => $n,
        'message' => rocLayerAnnouncementOn($a) ? 'Saved. The announcement is now on every page.' : 'Saved. The announcement is not shown on the website.']);
}

if ($part === 'code') {
    $c = [];
    foreach (['css' => 50000, 'head_html' => 50000, 'body_top_html' => 50000, 'body_end_html' => 50000] as $k => $max) {
        $c[$k] = (string)($v[$k] ?? '');
        if (strlen($c[$k]) > $max) $errors[$k] = 'Too long (50 KB at most).';
    }
    if (preg_match('#</?(html|head|body)\b#i', $c['head_html'] . $c['body_top_html'] . $c['body_end_html'])) {
        $errors['head_html'] = 'Do not include <html>, <head> or <body> tags: only what goes inside them.';
    }
    if ($errors) rocSlOut(422, ['success' => false, 'error' => 'Check the highlighted fields.', 'errors' => $errors]);
    $n = rocLayerSave($pdo, $ROOT, 'code', $c, (int)$session['user_id']);
    logCmsAudit('cms_site_code_update', 'settings', 'site_code', ['sizes' => array_map('strlen', $c)]);
    rocSlOut(200, ['success' => true, 'code' => $c, 'files' => $n, 'message' => 'Saved. The code is now on every page.']);
}

rocSlOut(400, ['success' => false, 'error' => 'Unknown request.']);
