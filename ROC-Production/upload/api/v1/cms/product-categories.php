<?php
/**
 * Run On Console (ROC) - CMS Product Categories API
 * GET  /api/v1/cms/product-categories.php            all categories (with product counts)
 * POST /api/v1/cms/product-categories.php            {action: 'save', slug?, fields{...}, status?}
 *
 * Categories live in product_categories (created by cron/cli-product-routing.php).
 * Hiding a category hides its products on the website too; their own status is kept.
 */

require_once __DIR__ . '/config.php';

$session = requireCmsPermission('products', 'view');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    rocCmsAuthorize($session, 'products', 'edit');
}
$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit();
}

function rocCatTableReady(PDO $pdo): bool {
    try { $pdo->query('SELECT 1 FROM product_categories LIMIT 1'); return true; }
    catch (\Throwable $e) { return false; }
}

function rocCatSlug(string $s): string {
    $s = (string)preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($s)));
    return substr(trim($s, '-'), 0, 80);
}

function rocCatClip($v, int $max): string {
    return mb_substr(trim(is_scalar($v) ? (string)$v : ''), 0, $max);
}

if (!rocCatTableReady($pdo)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Product categories are not set up yet. Run api/v1/cron/cli-product-routing.php --apply on the server first.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $cats = $pdo->query('SELECT * FROM product_categories ORDER BY sort_rank, name')->fetchAll(PDO::FETCH_ASSOC);
    $counts = [];
    foreach ($pdo->query('SELECT category_slug, status, COUNT(*) AS n FROM products GROUP BY category_slug, status')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $s = (string)$r['category_slug'];
        $counts[$s] = $counts[$s] ?? ['live' => 0, 'hidden' => 0];
        $counts[$s][$r['status'] === 'published' ? 'live' : 'hidden'] += (int)$r['n'];
    }
    foreach ($cats as &$c) $c['products'] = $counts[$c['slug']] ?? ['live' => 0, 'hidden' => 0];
    unset($c);
    echo json_encode(['success' => true, 'categories' => $cats]);
    exit();
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (($data['action'] ?? '') !== 'save') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    exit();
}

$in = is_array($data['fields'] ?? null) ? $data['fields'] : [];
$errors = [];
$f = [];
$f['name'] = rocCatClip($in['name'] ?? '', 160);
if ($f['name'] === '') $errors[] = 'Category name is required.';
foreach (['description' => 1000, 'meta_title' => 255, 'meta_description' => 500] as $k => $max) {
    if (array_key_exists($k, $in)) $f[$k] = rocCatClip($in[$k], $max);
}
if (array_key_exists('image', $in)) {
    $img = trim((string)$in['image']);
    $ok = $img === ''
        || ($img[0] === '/' && strpos($img, '//') !== 0)
        || (strtolower((string)parse_url($img, PHP_URL_SCHEME)) === 'https' && parse_url($img, PHP_URL_HOST));
    if (!$ok || preg_match('/[\s"<>]/', $img)) $errors[] = 'The image must be an https:// link or an image from the Media library.';
    else $f['image'] = $img;
}
if (array_key_exists('sort_rank', $in)) $f['sort_rank'] = max(0, min(9999, (int)$in['sort_rank']));
if (array_key_exists('is_noindex', $in)) $f['is_noindex'] = !empty($in['is_noindex']) ? 1 : 0;

$status = $data['status'] ?? null;
if ($status !== null) {
    $status = $status === 'published' ? 'published' : 'draft';
    rocCmsAuthorize($session, 'products', 'publish');
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors), 'errors' => $errors]);
    exit();
}

$now = date('Y-m-d H:i:s');
$slug = rocCatSlug((string)($data['slug'] ?? ''));
$exists = false;
if ($slug !== '') {
    $st = $pdo->prepare('SELECT COUNT(*) FROM product_categories WHERE slug = ?');
    $st->execute([$slug]);
    $exists = (bool)$st->fetchColumn();
}

try {
    if ($exists) {
        $set = $f + ['updated_at' => $now, 'content_modified_at' => $now];
        if ($status !== null) $set['status'] = $status;
        $sql = 'UPDATE product_categories SET ' . implode(', ', array_map(function ($c) { return "{$c} = ?"; }, array_keys($set))) . ' WHERE slug = ?';
        $pdo->prepare($sql)->execute([...array_values($set), $slug]);
        // Products listed under the old name keep showing the new one.
        if (isset($f['name'])) $pdo->prepare('UPDATE products SET category = ? WHERE category_slug = ?')->execute([$f['name'], $slug]);
        $log = 'cms_product_category_update';
    } else {
        // New category: the address /products/{slug}/ is set once from the name.
        $base = $slug !== '' ? $slug : rocCatSlug($f['name']);
        if ($base === '') $base = 'category';
        $slug = $base;
        // Category pages share /products/{slug}/ with product pages and the two group pages.
        $chk = $pdo->prepare('SELECT (SELECT COUNT(*) FROM product_categories WHERE slug = ?) + (SELECT COUNT(*) FROM products WHERE slug = ?)');
        $reserved = ['category', 'pc-hardware', 'gaming-hardware'];
        for ($n = 2; ; $n++) {
            $chk->execute([$slug, $slug]);
            if (!(int)$chk->fetchColumn() && !in_array($slug, $reserved, true)) break;
            $slug = $base . '-' . $n;
        }
        if (!isset($f['sort_rank'])) $f['sort_rank'] = (int)$pdo->query('SELECT COALESCE(MAX(sort_rank), 0) + 1 FROM product_categories')->fetchColumn();
        $row = $f + ['slug' => $slug, 'status' => $status ?? 'draft', 'created_at' => $now, 'updated_at' => $now, 'content_modified_at' => $now];
        $names = array_keys($row);
        $pdo->prepare('INSERT INTO product_categories (' . implode(', ', $names) . ') VALUES (' . implode(', ', array_fill(0, count($names), '?')) . ')')
            ->execute(array_values($row));
        $log = 'cms_product_category_create';
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Save failed: ' . $e->getMessage()]);
    exit();
}

logCmsAudit($log, 'product_category', $slug, ['name' => $f['name'], 'status' => $status]);
$st = $pdo->prepare('SELECT * FROM product_categories WHERE slug = ?');
$st->execute([$slug]);
echo json_encode(['success' => true, 'category' => $st->fetch(PDO::FETCH_ASSOC)]);
