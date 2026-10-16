<?php
/**
 * Run On Console (ROC) — CMS > Social posts (Deploy 72). See social-kit-lib.php.
 *
 * GET                                          the kits, newest first, and recent posts without one
 * POST {action:'make', id}                     make (or remake) the kit of a post now
 * POST {action:'posted', id, platform, on}     mark a kit as posted (or not) on a platform
 * POST {action:'settings', on}                 make kits by themselves (on) or only by hand (off)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/social-kit-lib.php';

$session = requireCmsPermission('blogs', 'view');
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$root = dirname(__DIR__, 3);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    rocCmsAuthorize($session, 'blogs', 'edit');
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $act = (string)($in['action'] ?? '');
    if ($act === 'make') {
        $st = $pdo->prepare("SELECT id, title, slug, excerpt, content, category, image, published_at FROM blogs WHERE id = ? AND status = 'published'");
        $st->execute([(string)($in['id'] ?? '')]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) rocCmsDeny(404, 'Only published posts get a social kit.');
        if (!rocBlogWriters($pdo)) rocCmsDeny(409, 'No AI key saved: add the Groq or Gemini key in CMS > Blog writer.');
        @set_time_limit(180);
        $k = rocKitMake($pdo, $root, $p);
        if (isset($k['error'])) rocCmsDeny(502, 'The kit could not be made: ' . $k['error']);
        logCmsAudit('cms_social_kit_make', 'blog', $p['id'], ['title' => $p['title']]);
        echo json_encode(['success' => true, 'kit' => $k, 'message' => 'Social kit ready.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit;
    }
    if ($act === 'posted') {
        $all = rocKitAll($pdo); $id = (string)($in['id'] ?? ''); $pl = (string)($in['platform'] ?? '');
        if (!isset($all[$id]) || !isset(ROC_KIT_PLATFORMS[$pl])) rocCmsDeny(404, 'Unknown kit or platform.');
        $posted = (array)($all[$id]['posted'] ?? []);
        if (!empty($in['on'])) $posted[$pl] = gmdate('c'); else unset($posted[$pl]);
        $all[$id]['posted'] = $posted;
        rocKitSaveAll($pdo, $all);
        echo json_encode(['success' => true, 'posted' => (object)$posted]); exit;
    }
    if ($act === 'settings') {
        rocBlogSet($pdo, 'social_kits_on', !empty($in['on']) ? 'on' : 'off', (int)$session['user_id']);
        echo json_encode(['success' => true, 'message' => !empty($in['on']) ? 'Kits are made by themselves for every new post (one every 10 minutes).' : 'Kits are made only when you press Make kit.']); exit;
    }
    rocCmsDeny(400, 'Unknown action.');
}

$kits = array_values(rocKitAll($pdo));
$have = array_column($kits, 'id');
$recent = [];
foreach ($pdo->query("SELECT id, title, category, published_at FROM blogs WHERE status = 'published' ORDER BY published_at DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC) as $r)
    if (!in_array($r['id'], $have, true)) $recent[] = $r;
echo json_encode(['success' => true, 'on' => (string)rocBlogGet($pdo, 'social_kits_on', 'on') !== 'off', 'ai' => (bool)rocBlogWriters($pdo),
    'platforms' => ROC_KIT_PLATFORMS, 'kits' => $kits, 'without' => array_slice($recent, 0, 12)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
