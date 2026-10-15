<?php
/**
 * Run On Console (ROC) — social profiles + Amazon affiliate tag (administrators)
 * GET  /api/v1/cms/social.php
 * POST {links: {facebook: 'https://…', youtube: '', …}}   save social profiles
 * POST {amazon_tag: 'runonconsole-20'}                      save the Amazon tag ('' removes it)
 * POST {daraz: {on, who: pk|all, template}, daraz_links: {slug: url}}   Daraz for visitors in Pakistan
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/social-lib.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');

$platforms = [];
foreach (ROC_SOCIAL_PLATFORMS as $k => $d) $platforms[] = ['key' => $k, 'label' => $d['label'], 'example' => 'https://' . $d['host'] . '/…'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo json_encode([
        'success' => true,
        'platforms' => $platforms,
        'links' => rocSocialLoad($pdo) ?? array_fill_keys(array_keys(ROC_SOCIAL_PLATFORMS), ''),
        'amazon_tag' => rocAmazonTagLoad($pdo),
        'daraz' => rocDarazLoad($pdo),
        'daraz_links' => (object)rocDarazLinksLoad($pdo),
        'products' => $pdo->query("SELECT slug, title, category FROM products WHERE status = 'published' ORDER BY category, title")->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

if (array_key_exists('amazon_tag', $data)) {
    [$tag, $err] = rocAmazonTagClean((string)$data['amazon_tag']);
    if ($err) { http_response_code(422); echo json_encode(['success' => false, 'error' => $err, 'errors' => ['amazon_tag' => $err]]); exit(); }
    rocAmazonTagSave($pdo, $tag, (int)$session['user_id']);
    logCmsAudit('cms_amazon_tag_update', 'settings', 'amazon_tag', ['amazon_tag' => $tag]);
    echo json_encode(['success' => true, 'amazon_tag' => $tag,
        'message' => $tag === '' ? 'Amazon tag removed.' : 'Saved. Every Amazon link on the website now carries tag=' . $tag . '.'], JSON_UNESCAPED_SLASHES);
    exit();
}

if (array_key_exists('daraz', $data)) {
    $slugs = $pdo->query("SELECT slug FROM products WHERE status = 'published'")->fetchAll(PDO::FETCH_COLUMN);
    [$set, $links, $err] = rocDarazClean((array)$data['daraz'], is_array($data['daraz_links'] ?? null) ? $data['daraz_links'] : [], $slugs);
    if ($err) { http_response_code(422); echo json_encode(['success' => false, 'error' => 'Check the highlighted boxes.', 'errors' => $err]); exit(); }
    rocDarazSave($pdo, $set, $links, (int)$session['user_id']);
    logCmsAudit('cms_daraz_update', 'settings', 'daraz', $set + ['links' => count($links)]);
    echo json_encode(['success' => true, 'daraz' => $set, 'daraz_links' => (object)$links,
        'message' => $set['on'] ? 'Saved. ' . ($set['who'] === 'all' ? 'Every visitor' : 'Visitors in Pakistan') . ' now see a Check on Daraz button next to Amazon (' . count($links) . ' product link(s), the rest open a Daraz search).'
                                : 'Saved. Daraz buttons are off.'], JSON_UNESCAPED_SLASHES);
    exit();
}

[$clean, $errors] = rocSocialClean(is_array($data['links'] ?? null) ? $data['links'] : []);
if ($errors) { http_response_code(422); echo json_encode(['success' => false, 'error' => 'Check the highlighted links.', 'errors' => $errors]); exit(); }
rocSocialSave($pdo, $clean, (int)$session['user_id']);
logCmsAudit('cms_social_links_update', 'settings', 'social_links', $clean);
echo json_encode(['success' => true, 'message' => 'Saved. The website footer now uses these links.', 'links' => $clean], JSON_UNESCAPED_SLASHES);
