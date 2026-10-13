<?php
/**
 * Run On Console — CMS: main categories, product versions and old product addresses.
 *
 *   GET                                     everything below, as the CMS shows it
 *   POST {action:'groups',   groups:{...}}  PC Hardware / Gaming Hardware: name, description, picture,
 *                                           and the categories in PC Hardware (the rest are Gaming Hardware)
 *   POST {action:'variants', groups:{...}}  "Choose version" sets: {set-name: [{slug, label}, ...]}
 *   POST {action:'redirects', map:{...}}    old product address -> page it opens: {slug: "/category/x/"}
 *
 * Saved in cms_settings (product_groups, product_variants) and api/v1/cms/product-redirects.json;
 * product-render.php reads them on every page.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/blog-engine-lib.php';
require_once __DIR__ . '/product-groups-lib.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');

$REDIRECTS = __DIR__ . '/product-redirects.json';
function rocCaOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }

function rocCaGroups(PDO $pdo): array {
    $g = ROC_PRODUCT_GROUPS;
    $saved = rocBlogGet($pdo, 'product_groups', null);
    foreach ($g as $k => &$def) {
        $def['image'] = '';
        foreach (['name', 'description', 'image'] as $f) if (is_array($saved) && isset($saved[$k][$f]) && trim((string)$saved[$k][$f]) !== '') $def[$f] = trim((string)$saved[$k][$f]);
    }
    unset($def);
    if (is_array($saved) && isset($saved['pc-hardware']['members']) && is_array($saved['pc-hardware']['members'])) $g['pc-hardware']['members'] = array_values($saved['pc-hardware']['members']);
    return $g;
}
function rocCaVariants(PDO $pdo): array {
    $saved = rocBlogGet($pdo, 'product_variants', null);
    if (is_array($saved) && isset($saved['groups'])) return (array)$saved['groups'];
    return (array)(json_decode((string)@file_get_contents(__DIR__ . '/product-variants.json'), true)['groups'] ?? []);
}

$cats = [];
try { $cats = $pdo->query('SELECT slug, name FROM product_categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC); } catch (\Throwable $e) {}
$catSlugs = array_column($cats, 'slug');
$products = [];
try { foreach ($pdo->query("SELECT slug, title, status FROM products ORDER BY title")->fetchAll(PDO::FETCH_ASSOC) as $p) $products[$p['slug']] = ['title' => $p['title'], 'status' => $p['status']]; } catch (\Throwable $e) {}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    rocCaOut(200, ['success' => true, 'groups' => rocCaGroups($pdo), 'categories' => $cats, 'variants' => rocCaVariants($pdo) ?: new stdClass(),
        'redirects' => (json_decode((string)@file_get_contents($REDIRECTS), true) ?: new stdClass()),
        'products' => $products ?: new stdClass()]);
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$uid = (int)$session['user_id'];
$act = (string)($in['action'] ?? '');

if ($act === 'groups') {
    $g = (array)($in['groups'] ?? []);
    $out = [];
    foreach (array_keys(ROC_PRODUCT_GROUPS) as $k) {
        $x = (array)($g[$k] ?? []);
        $name = trim(mb_substr((string)($x['name'] ?? ''), 0, 60));
        $desc = trim(mb_substr((string)($x['description'] ?? ''), 0, 300));
        $img = trim((string)($x['image'] ?? ''));
        if ($name === '') rocCaOut(422, ['success' => false, 'error' => 'Each main category needs a name.']);
        if ($img !== '' && !preg_match('#^(/[^\s"<>]+|https://[^\s"<>]+)$#', $img)) rocCaOut(422, ['success' => false, 'error' => 'Picture: use an address from the media library (starts with /).']);
        $out[$k] = ['name' => $name, 'description' => $desc, 'image' => $img];
    }
    $out['pc-hardware']['members'] = array_values(array_intersect((array)($g['pc-hardware']['members'] ?? []), $catSlugs));
    rocBlogSet($pdo, 'product_groups', $out, $uid);
    rocCaOut(200, ['success' => true, 'message' => 'Saved. The category pages use it now.', 'groups' => rocCaGroups($pdo)]);
}

if ($act === 'variants') {
    $out = [];
    foreach ((array)($in['groups'] ?? []) as $name => $vs) {
        $name = strtolower(trim(preg_replace('/[^a-z0-9-]+/i', '-', (string)$name), '-'));
        $list = [];
        foreach ((array)$vs as $v) {
            $slug = trim((string)($v['slug'] ?? '')); $label = trim(mb_substr((string)($v['label'] ?? ''), 0, 30));
            if ($slug === '' || $label === '') continue;
            if (!isset($products[$slug])) rocCaOut(422, ['success' => false, 'error' => "No product with the address {$slug}."]);
            $list[] = ['slug' => $slug, 'label' => $label];
        }
        if ($name !== '' && count($list) >= 2) $out[$name] = $list;
    }
    rocBlogSet($pdo, 'product_variants', ['groups' => $out], $uid);
    rocCaOut(200, ['success' => true, 'message' => 'Saved. Product pages show these versions now.', 'variants' => $out ?: new stdClass()]);
}

if ($act === 'redirects') {
    $out = [];
    foreach ((array)($in['map'] ?? []) as $slug => $to) {
        $slug = strtolower(trim((string)$slug, "/ \t")); $to = trim((string)$to);
        if ($slug === '' || $to === '') continue;
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) rocCaOut(422, ['success' => false, 'error' => "Old address \"{$slug}\": use only the last part, like logitech-z906."]);
        if (!preg_match('#^/(products|category)/[a-z0-9-]*/?$#', $to)) rocCaOut(422, ['success' => false, 'error' => "Opens: \"{$to}\" must be /category/... or /products/... on this site."]);
        if (isset($products[$slug]) && $products[$slug]['status'] === 'published') rocCaOut(422, ['success' => false, 'error' => "{$slug} is a live product; its page would stop opening."]);
        $out[$slug] = rtrim($to, '/') . '/';
    }
    if (@file_put_contents($REDIRECTS, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) rocCaOut(500, ['success' => false, 'error' => 'Could not save product-redirects.json.']);
    rocCaOut(200, ['success' => true, 'message' => 'Saved. Old addresses open their new page (301).', 'redirects' => $out ?: new stdClass()]);
}
rocCaOut(400, ['success' => false, 'error' => 'Unknown request.']);
