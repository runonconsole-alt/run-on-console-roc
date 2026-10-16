<?php
/**
 * Run On Console (ROC) — CMS editor helpers for blogs, guides and news (Deploy 71).
 *
 * GET                                          the hardware groups and their product categories (topic picker)
 * POST {action:'cover', title, slug, category, prompt?}   makes a cover picture now (Cloudflare AI when its key
 *                                              is saved in CMS > Blog writer, else a title cover); answers {image, image_alt}
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/topics-lib.php';

$session = requireCmsPermission('blogs', 'view');
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    rocCmsAuthorize($session, 'blogs', 'edit');
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    if (($in['action'] ?? '') !== 'cover') rocCmsDeny(400, 'Unknown action.');
    require_once __DIR__ . '/blog-engine-lib.php';
    $title = trim(mb_substr(strip_tags((string)($in['title'] ?? '')), 0, 200));
    if (mb_strlen($title) < 5) rocCmsDeny(422, 'Write the title first: the picture is made from it.');
    $slug = rocBlogSlug((string)($in['slug'] ?? '') ?: $title) ?: 'post-' . bin2hex(random_bytes(3));
    @set_time_limit(120);
    $img = rocBlogMakeCover($pdo, dirname(__DIR__, 3), $slug, $title, trim((string)($in['category'] ?? '')) ?: 'Guide', null,
                            trim(mb_substr((string)($in['prompt'] ?? ''), 0, 300)), $title);
    if ($img === '') rocCmsDeny(500, 'The picture could not be made. Try again or upload one.');
    logCmsAudit('cms_blog_cover_ai', 'blog', $slug, ['image' => $img]);
    echo json_encode(['success' => true, 'image' => $img, 'image_alt' => $title, 'ai' => (bool)rocBlogCf($pdo),
        'message' => rocBlogCf($pdo) ? 'Cover made with AI.' : 'Title cover made (add the Cloudflare key in CMS > Blog writer for AI pictures).'], JSON_UNESCAPED_SLASHES);
    exit();
}

$g = rocTopicGroups($pdo);
echo json_encode(['success' => true, 'installed' => rocTopicColumns($pdo), 'groups' => array_map(function ($k, $v) {
    return ['slug' => $k, 'name' => $v['name'], 'cats' => array_map(function ($s, $n) { return ['slug' => $s, 'name' => $n]; }, array_keys($v['cats']), array_values($v['cats']))];
}, array_keys($g), array_values($g))], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
