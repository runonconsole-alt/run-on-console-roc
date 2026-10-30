<?php
/**
 * Run On Console (ROC) — CMS > Social posts (Deploy 72). See social-kit-lib.php.
 *
 * GET                                          the kits, newest first, and recent posts without one
 * POST {action:'make', id}                     make (or remake) the kit of a post now
 * POST {action:'posted', id, platform, on}     mark a kit as posted (or not) on a platform
 * POST {action:'settings', on}                 make kits by themselves (on) or only by hand (off)
 * POST {action:'answer', question, where}      a draft answer for a Reddit or Quora question, to post by hand (Deploy 74)
 * GET also: traffic = visits from social media in the last 30 days (api/v1/social-visit.php)
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
    if ($act === 'answer') {
        // Reddit and Quora do not allow bots: this only drafts a helpful answer that the owner reads, edits and posts by hand.
        $q = trim(mb_substr(strip_tags((string)($in['question'] ?? '')), 0, 2000));
        $where = ($in['where'] ?? '') === 'quora' ? 'Quora' : 'Reddit';
        if (mb_strlen($q) < 10) rocCmsDeny(422, 'Paste the question (and the post text if there is one).');
        if (!rocBlogWriters($pdo)) rocCmsDeny(409, 'No AI key saved: add the Groq or Gemini key in CMS > Updates writer.');
        // Our pages that match the words of the question (at most 4), so the AI can point to one if it really helps.
        $words = array_slice(array_filter(preg_split('/[^a-z0-9]+/', strtolower($q)), function ($w) { return strlen($w) >= 4 && !in_array($w, ['what', 'which', 'with', 'should', 'would', 'about', 'there', 'that', 'this', 'have', 'from', 'your', 'best', 'does', 'good'], true); }), 0, 8);
        $pages = [];
        if ($words) {
            $like = implode(' OR ', array_fill(0, count($words), 'LOWER(title) LIKE ?'));
            $st = $pdo->prepare("SELECT title, slug, category FROM blogs WHERE status = 'published' AND ({$like}) ORDER BY published_at DESC LIMIT 4");
            $st->execute(array_map(function ($w) { return '%' . $w . '%'; }, $words));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { [$sec] = rocKitSection($r); $pages[] = '- https://runonconsole.com/' . $sec . '/' . $r['slug'] . '/ (' . $r['title'] . ')'; }
        }
        $pages[] = '- https://runonconsole.com/can-i-run/ (PC requirements of 2,400+ games, a quick check)';
        $prompt = "Draft an answer for this {$where} question. A real person (a gamer who runs the site runonconsole.com) will read it, edit it and post it by hand.

"
            . "QUESTION:
{$q}

RULES
- Answer the question first, fully and honestly, like a helpful gamer: practical steps or a clear recommendation with the reason. 120-250 words.
"
            . "- Natural, friendly tone. No marketing words, no emojis spam, no hashtags, no ALL CAPS.
"
            . "- Do NOT add a link unless one of these pages answers the question better than text can; then at most ONE link, at the end, with a short honest note such as \"I wrote a step by step guide on this:\" (" . ($where === 'Reddit' ? 'Reddit asks people to say when they link their own site' : 'Quora asks people to disclose their own site') . ").
"
            . "- Never invent facts, prices, benchmarks or personal experiences.

OUR PAGES:
" . implode("
", $pages) . "

"
            . "Answer with ONLY a JSON object: {\"answer\": \"the answer text, plain text with line breaks\", \"link_used\": \"the link or empty\", \"tip\": \"one short tip for posting this on {$where} without looking like spam\"}";
        $used = null;
        $w = rocBlogWriteAny($pdo, $prompt, $used);
        if (!$w['ok']) rocCmsDeny(502, $w['error']);
        $a = $w['article'];
        echo json_encode(['success' => true, 'answer' => trim(strip_tags((string)($a['answer'] ?? ''))), 'link' => trim((string)($a['link_used'] ?? '')),
            'tip' => trim(strip_tags((string)($a['tip'] ?? ''))), 'where' => $where], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit;
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
// Visits from social media, last 30 days (api/v1/social-visit.php).
$traffic = ['by_source' => [], 'pages' => [], 'days' => 30];
try {
    $since = (new DateTime('-30 days', new DateTimeZone('Asia/Karachi')))->format('Y-m-d');
    $st = $pdo->prepare('SELECT source, SUM(visits) v FROM roc_social_visits WHERE day >= ? GROUP BY source ORDER BY v DESC'); $st->execute([$since]);
    $traffic['by_source'] = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare('SELECT path, source, SUM(visits) v FROM roc_social_visits WHERE day >= ? GROUP BY path, source ORDER BY v DESC LIMIT 15'); $st->execute([$since]);
    $traffic['pages'] = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}
echo json_encode(['success' => true, 'traffic' => $traffic, 'on' => (string)rocBlogGet($pdo, 'social_kits_on', 'on') !== 'off', 'ai' => (bool)rocBlogWriters($pdo),
    'platforms' => ROC_KIT_PLATFORMS, 'kits' => $kits, 'without' => array_slice($recent, 0, 12)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
