<?php
/**
 * Run On Console (ROC) — meta title and description of every page (CMS "Metas" tab)
 *
 * GET  /api/v1/cms/page-metas.php
 *      rows: {key, type, group, path, label, status, title, description}
 *      title/description are the custom values ('' = automatic). The CMS reads the
 *      live page itself to show what is live now.
 * POST {key, title, description}
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
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$key = (string)($in['key'] ?? '');
$clean = function ($s, $max) { return trim(mb_substr((string)preg_replace('/\s+/u', ' ', strip_tags((string)$s)), 0, $max)); };
$title = $clean($in['title'] ?? '', 255);
$desc = $clean($in['description'] ?? '', 500);

if (strpos($key, 'page:') === 0) {
    if (!rocCmsCan($session, 'pages', 'edit')) rocCmsDeny(403, 'You cannot edit pages.');
    $path = substr($key, 5);
    $known = array_key_exists($path, ROC_LAYER_PHP_PAGES) || array_key_exists($path, rocLayerStaticFiles($ROOT));
    if (!$known || $path === '/404') rocPmOut(404, ['success' => false, 'error' => 'Unknown page.']);
    $L = rocLayerFromDb($pdo, $ROOT);
    $meta = $L['meta'];
    if ($title === '' && $desc === '') unset($meta[$path]); else $meta[$path] = ['title' => $title, 'description' => $desc];
    rocLayerSave($pdo, $ROOT, 'meta', $meta, (int)$session['user_id'], [$path]);
    logCmsAudit('cms_page_meta_update', 'page', $path, ['title' => $title, 'description' => $desc]);
    rocPmOut(200, ['success' => true, 'message' => 'Saved. The live page now uses ' . ($title === '' && $desc === '' ? 'its automatic title and description.' : 'this title and description.')]);
}

[$type, $id] = array_pad(explode(':', $key, 2), 2, '');
if (!isset($DB[$type]) || $id === '') rocPmOut(400, ['success' => false, 'error' => 'Unknown page.']);
$d = $DB[$type];
if (!rocCmsCan($session, $d['perm'], 'edit')) rocCmsDeny(403, 'You cannot edit these pages.');
$cols = rocPmCols($pdo, $d['table']);
$pk = in_array('id', $cols, true) ? 'id' : 'slug';
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
$q = $pdo->prepare("UPDATE {$d['table']} SET " . implode(', ', $set) . " WHERE {$pk} = ?");
$q->execute($args);
if (!$q->rowCount()) rocPmOut(404, ['success' => false, 'error' => 'Page not found.']);
logCmsAudit('cms_page_meta_update', $type, (string)$id, ['title' => $title, 'description' => $desc]);
rocPmOut(200, ['success' => true, 'message' => 'Saved. The live page now uses ' . ($title === '' && $desc === '' ? 'its automatic title and description.' : 'this title and description.')]);
