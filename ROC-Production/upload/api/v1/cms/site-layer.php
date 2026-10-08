<?php
/**
 * Run On Console (ROC) — announcement bar and site code (administrators)
 *
 * GET  /api/v1/cms/site-layer.php
 * POST {part:'announcement', value:{enabled, icon, title, text, link_url, link_text, bg, fg, dismissible, ends_at}}
 * POST {part:'code', value:{css, head_html, body_top_html, body_end_html}}
 * GET  ?history=announcement|code          earlier versions (30 days)
 * POST {action:'restore', id, which:'before'|'after'}   put an earlier version back
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
    if (isset($_GET['history'])) {
        $kind = (string)$_GET['history'];
        if (!in_array($kind, ['announcement', 'code'], true)) rocSlOut(400, ['success' => false, 'error' => 'Unknown history.']);
        rocSlOut(200, ['success' => true, 'history' => rocHistList($ROOT, $kind), 'days' => ROC_HISTORY_DAYS]);
    }
    $L = rocLayerFromDb($pdo, $ROOT);
    rocSlOut(200, ['success' => true, 'announcement' => $L['announcement'], 'code' => $L['code'],
        'live' => rocLayerAnnouncementOn($L['announcement']), 'pages' => count(rocLayerStaticFiles($ROOT))]);
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];

/** Saves a part and keeps the previous version in the history. */
function rocSlSave(PDO $pdo, string $ROOT, string $part, array $value, array $session, string $label): array {
    $before = rocLayerFromDb($pdo, $ROOT)[$part];
    $n = rocLayerSave($pdo, $ROOT, $part, $value, (int)$session['user_id']);
    $hid = rocHistAdd($ROOT, $part, $part, $label, $before, $value, $session);
    return [$n, $hid];
}

if (($in['action'] ?? '') === 'restore') {
    $h = rocHistGet($ROOT, (string)($in['id'] ?? ''));
    if (!$h || !in_array($h['kind'], ['announcement', 'code'], true)) rocSlOut(404, ['success' => false, 'error' => 'That version is no longer in the archive.']);
    $which = ($in['which'] ?? 'before') === 'after' ? 'after' : 'before';
    $value = is_array($h[$which]) ? $h[$which] : [];
    [$n, $hid] = rocSlSave($pdo, $ROOT, $h['kind'], $value, $session, 'Restored version from ' . $h['at']);
    logCmsAudit('cms_' . $h['kind'] . '_restore', 'settings', $h['kind'], ['from' => $h['id'], 'which' => $which]);
    rocSlOut(200, ['success' => true, 'history_id' => $hid, $h['kind'] => $value, 'message' => 'Earlier version restored on the website.']);
}
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
    [$n, $hid] = rocSlSave($pdo, $ROOT, 'announcement', $a, $session, $a['title'] ?: $a['text']);
    logCmsAudit('cms_announcement_update', 'settings', 'site_announcement', ['enabled' => $a['enabled'], 'title' => $a['title']]);
    rocSlOut(200, ['success' => true, 'announcement' => $a, 'live' => rocLayerAnnouncementOn($a), 'files' => $n, 'history_id' => $hid,
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
    [$n, $hid] = rocSlSave($pdo, $ROOT, 'code', $c, $session, 'Template & code');
    logCmsAudit('cms_site_code_update', 'settings', 'site_code', ['sizes' => array_map('strlen', $c)]);
    rocSlOut(200, ['success' => true, 'code' => $c, 'files' => $n, 'history_id' => $hid, 'message' => 'Saved. The code is now on every page.']);
}

rocSlOut(400, ['success' => false, 'error' => 'Unknown request.']);
