<?php
/**
 * Run On Console (ROC) — meta title and description of every page (CMS "Metas" tab)
 *
 * GET  /api/v1/cms/page-metas.php
 *      rows: {key, type, group, path, label, status, title, description}
 *      title/description are the custom values ('' = automatic). The CMS reads the
 *      live page itself to show what is live now.
 * POST {key, title, description}
 * GET  ?history=KEY|all                     earlier versions, kept 30 days
 * POST {action:'restore', id, which:'before'|'after'}
 *
 * Where a change is stored:
 *   page:/about/ and the PHP list pages   -> cms_settings.meta_overrides (site-layer-lib.php),
 *                                           built pages are rewritten right away
 *   product:ID, pcat:SLUG, platform:SLUG,
 *   blog:ID, cmspage:ID                   -> meta_title / meta_description of that row
 * Either way the live page changes on save.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/site-layer-lib.php';

$session = requireCmsPermission('pages', 'view');
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');
$ROOT = realpath(__DIR__ . '/../../..') ?: dirname(__DIR__, 3);

function rocPmOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }
function rocPmCols(PDO $pdo, string $t): array {
    try { $st = $pdo->query("SELECT * FROM {$t} LIMIT 0"); } catch (\Throwable $e) { return []; }
    $c = []; for ($i = 0; $i < $st->columnCount(); $i++) $c[] = strtolower((string)$st->getColumnMeta($i)['name']);
    return $c;
}

/** Database-backed page types: table, label column, URL, permission. */
$DB = [
    'product'  => ['table' => 'products',           'label' => 'title', 'group' => 'Products',            'perm' => 'products',
                   'url' => function ($r) { return '/products/' . $r['slug'] . '/'; }],
    'pcat'     => ['table' => 'product_categories', 'label' => 'name',  'group' => 'Product categories',  'perm' => 'products',
                   'url' => function ($r) { return '/products/' . $r['slug'] . '/'; }],
    'platform' => ['table' => 'gaming_categories',  'label' => 'title', 'group' => 'Gaming platforms',    'perm' => 'pages',
                   'url' => function ($r) { return '/gaming-platforms/' . $r['slug'] . '/'; }],
    'blog'     => ['table' => 'blogs',              'label' => 'title', 'group' => 'Blog posts',          'perm' => 'blogs',
                   'url' => function ($r) { return '/blogs/' . $r['slug'] . '/'; }],
    'cmspage'  => ['table' => 'pages',              'label' => 'title', 'group' => 'CMS pages',           'perm' => 'pages',
                   'url' => function ($r) {
                       return ($r['page_type'] ?? '') === 'category' && !empty($r['category_slug'])
                           ? '/gaming-platforms/' . $r['category_slug'] . '/' . $r['slug'] . '/' : '/' . $r['slug'] . '/';
                   }],
];

/* ------------------------------------------------------------------ list */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['history'])) {
    // Earlier titles/descriptions (30 days): one page (?history=page:/about/) or all (?history=all).
    $k = (string)$_GET['history'];
    rocPmOut(200, ['success' => true, 'days' => ROC_HISTORY_DAYS, 'history' => rocHistList($ROOT, 'meta', $k === 'all' ? null : $k)]);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $L = rocLayerFromDb($pdo, $ROOT);
    $rows = [];
    $groupOf = function (string $p): string {
        if ($p === '/' || $p === '/about/' || $p === '/contact/' || $p === '/compatibility/' || $p === '/write-for-us/' || strpos($p, '/author/') === 0) return 'Main pages';
        if (in_array($p, ['/privacy-policy/', '/terms-and-conditions/'], true)) return 'Policy pages';
        if (strpos($p, '/auth/') === 0 || $p === '/profile/') return 'Account pages';
        return 'Other pages';
    };
    foreach (rocLayerStaticFiles($ROOT) as $path => $file) {
        if ($path === '/404') continue;
        [$html] = rocLayerStrip((string)file_get_contents($file, false, null, 0, 60000));
        [$t] = rocLayerGetMeta($html);
        $ov = $L['meta'][$path] ?? [];
        $rows[] = ['key' => 'page:' . $path, 'type' => 'page', 'group' => $groupOf($path), 'path' => $path,
                   'label' => preg_replace('/\s*\|\s*Run On Console$/', '', $t) ?: $path, 'status' => '',
                   'noindex' => (bool)preg_match('#<meta\s+name="robots"\s+content="[^"]*noindex#i', $html),
                   'title' => (string)($ov['title'] ?? ''), 'description' => (string)($ov['description'] ?? '')];
    }
    foreach (ROC_LAYER_PHP_PAGES as $path => $label) {
        $ov = $L['meta'][$path] ?? [];
        $rows[] = ['key' => 'page:' . $path, 'type' => 'list', 'group' => 'List pages', 'path' => $path, 'label' => $label, 'status' => '',
                   'noindex' => false, 'title' => (string)($ov['title'] ?? ''), 'description' => (string)($ov['description'] ?? '')];
    }
    foreach ($DB as $type => $d) {
        $cols = rocPmCols($pdo, $d['table']);
        if (!$cols || !in_array('meta_title', $cols, true) || !in_array('slug', $cols, true)) continue;
        $order = in_array('sort_rank', $cols, true) ? 'sort_rank, ' . $d['label'] : $d['label'];
        $pk = in_array('id', $cols, true) ? 'id' : 'slug';   // categories are keyed by slug
        foreach ($pdo->query("SELECT * FROM {$d['table']} ORDER BY {$order}")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (($r['slug'] ?? '') === '') continue;
            // Titles saved with the old automatic patterns are treated as automatic by the renderers.
            $mt = (string)($r['meta_title'] ?? '');
            $name = (string)($r[$d['label']] ?? '');
            if (in_array($mt, [$name . ': Specs & Where to Buy | Run On Console', $name . ' Hardware & Specs Hub | Run On Console',
                               $name . ' Hardware, Devices & Games | Run On Console'], true)) $r['meta_title'] = '';
            $rows[] = ['key' => $type . ':' . $r[$pk], 'type' => $type, 'group' => $d['group'], 'path' => $d['url']($r),
                       'label' => (string)($r[$d['label']] ?? $r['slug']), 'status' => (string)($r['status'] ?? ''),
                       'noindex' => !empty($r['is_noindex']),
                       'title' => (string)($r['meta_title'] ?? ''), 'description' => (string)($r['meta_description'] ?? '')];
        }
    }
    rocPmOut(200, ['success' => true, 'rows' => $rows]);
}

/* ------------------------------------------------------------------ save */
/**
 * Saves a title and description for one page and keeps the previous ones in the
 * 30-day history. Returns [before, after] (each {title, description}).
 */
function rocPmSave(PDO $pdo, string $ROOT, array $DB, array $session, string $key, string $title, string $desc, string $label): array {
    if (strpos($key, 'page:') === 0) {
        if (!rocCmsCan($session, 'pages', 'edit')) rocCmsDeny(403, 'You cannot edit pages.');
        $path = substr($key, 5);
        $known = array_key_exists($path, ROC_LAYER_PHP_PAGES) || array_key_exists($path, rocLayerStaticFiles($ROOT));
        if (!$known || $path === '/404') rocPmOut(404, ['success' => false, 'error' => 'Unknown page.']);
        $meta = rocLayerFromDb($pdo, $ROOT)['meta'];
        $before = ['title' => (string)($meta[$path]['title'] ?? ''), 'description' => (string)($meta[$path]['description'] ?? '')];
        if ($title === '' && $desc === '') unset($meta[$path]); else $meta[$path] = ['title' => $title, 'description' => $desc];
        rocLayerSave($pdo, $ROOT, 'meta', $meta, (int)$session['user_id'], [$path]);
        $after = ['title' => $title, 'description' => $desc];
        rocHistAdd($ROOT, 'meta', $key, $label !== '' ? $label : $path, $before, $after, $session);
        logCmsAudit('cms_page_meta_update', 'page', $path, $after);
        return [$before, $after];
    }

    [$type, $id] = array_pad(explode(':', $key, 2), 2, '');
    if (!isset($DB[$type]) || $id === '') rocPmOut(400, ['success' => false, 'error' => 'Unknown page.']);
    $d = $DB[$type];
    if (!rocCmsCan($session, $d['perm'], 'edit')) rocCmsDeny(403, 'You cannot edit these pages.');
    $cols = rocPmCols($pdo, $d['table']);
    $pk = in_array('id', $cols, true) ? 'id' : 'slug';
    $st = $pdo->prepare("SELECT * FROM {$d['table']} WHERE {$pk} = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) rocPmOut(404, ['success' => false, 'error' => 'Page not found.']);
    $before = ['title' => (string)($row['meta_title'] ?? ''), 'description' => (string)($row['meta_description'] ?? '')];
    $set = ['meta_title = ?', 'meta_description = ?'];
    $args = [$title, $desc];
    if ($type === 'blog') {   // the blog editor keeps the sharing fields equal to the meta fields
        foreach (['og_title' => $title, 'twitter_title' => $title, 'og_description' => $desc, 'twitter_description' => $desc] as $c => $val) {
            if (in_array($c, $cols, true)) { $set[] = "{$c} = ?"; $args[] = $val; }
        }
    }
    if (in_array('version', $cols, true)) $set[] = 'version = version + 1';
    if (in_array('updated_at', $cols, true)) $set[] = 'updated_at = CURRENT_TIMESTAMP';
    $args[] = $id;
    $pdo->prepare("UPDATE {$d['table']} SET " . implode(', ', $set) . " WHERE {$pk} = ?")->execute($args);
    $after = ['title' => $title, 'description' => $desc];
    rocHistAdd($ROOT, 'meta', $key, $label !== '' ? $label : (string)($row[$d['label']] ?? $id), $before, $after, $session);
    logCmsAudit('cms_page_meta_update', $type, (string)$id, $after);
    return [$before, $after];
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$clean = function ($s, $max) { return trim(mb_substr((string)preg_replace('/\s+/u', ' ', strip_tags((string)$s)), 0, $max)); };

if (($in['action'] ?? '') === 'restore') {
    $h = rocHistGet($ROOT, (string)($in['id'] ?? ''));
    if (!$h || $h['kind'] !== 'meta') rocPmOut(404, ['success' => false, 'error' => 'That version is no longer in the archive.']);
    $v = (array)$h[($in['which'] ?? 'before') === 'after' ? 'after' : 'before'];
    [, $after] = rocPmSave($pdo, $ROOT, $DB, $session, (string)$h['key'], $clean($v['title'] ?? '', 255), $clean($v['description'] ?? '', 500), (string)$h['label']);
    rocPmOut(200, ['success' => true, 'key' => $h['key'], 'title' => $after['title'], 'description' => $after['description'],
                   'message' => 'Earlier version restored on the live page.']);
}

$key = (string)($in['key'] ?? '');
$title = $clean($in['title'] ?? '', 255);
$desc = $clean($in['description'] ?? '', 500);
rocPmSave($pdo, $ROOT, $DB, $session, $key, $title, $desc, $clean($in['label'] ?? '', 200));
rocPmOut(200, ['success' => true, 'message' => 'Saved. The live page now uses ' . ($title === '' && $desc === '' ? 'its automatic title and description.' : 'this title and description.')
    . ' The previous version is kept for ' . ROC_HISTORY_DAYS . ' days.']);
