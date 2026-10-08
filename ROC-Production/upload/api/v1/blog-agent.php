<?php
/**
 * Run On Console — blog agent endpoint (the writer that runs on the owner's PC).
 *
 * Every request needs the header  X-ROC-Agent-Token: <token>  (made in CMS > Blog agent;
 * only its SHA-256 is stored, in cms_settings.blog_agent_token_hash).
 *
 * GET  /api/v1/blog-agent.php?action=context
 *      writer (server | pc | off), free_slots, brief (the next topic from the content plan:
 *      cluster, funnel stage, keywords, the internal links it must carry), recent posts.
 *      When writer is "server" the server writes the posts itself (Gemini) and the PC stops.
 * POST /api/v1/blog-agent.php
 *      {title, focus_keyword, meta_title, meta_description, excerpt, category,
 *       content_html, image_alt?, publish_at, topic_id}
 *      Saved as "scheduled"; the server cron publishes it at that time.
 *
 * The plan, links and checks live in cms/blog-engine-lib.php.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cms/blog-engine-lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function agentOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; }

$pdo = getDBConnection();
if (!$pdo) agentOut(503, ['success' => false, 'error' => 'Database unavailable.']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ROOT = dirname(__DIR__, 2);

/* ------------------------------------------------------------------ auth */
$hash = rocLayerSettingGet($pdo, 'blog_agent_token_hash');
$given = (string)($_SERVER['HTTP_X_ROC_AGENT_TOKEN'] ?? '');
if ($hash === null || $hash === '' || $given === '' || !hash_equals($hash, hash('sha256', $given))) {
    usleep(300000);
    agentOut(401, ['success' => false, 'error' => 'Agent token missing or wrong. Make one in CMS > Blog agent.']);
}

/* --------------------------------------------------------------- context */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $slots = [];
    foreach (rocBlogFreeSlots($pdo) as $utc => $label) $slots[] = ['publish_at' => str_replace(' ', 'T', $utc) . 'Z', 'label' => $label];
    $brief = rocBlogBrief($pdo);
    $S = rocBlogSite($pdo);
    agentOut(200, ['success' => true, 'site' => 'https://runonconsole.com', 'now_utc' => gmdate('c'),
        'writer' => rocBlogEngine($pdo), 'free_slots' => $slots, 'brief' => $brief,
        'instructions' => $brief && $slots ? rocBlogPrompt($brief, $slots[0]['publish_at']) : '',
        'recent_posts' => array_slice(array_values(array_map(function ($b) { return ['title' => $b['title'], 'url' => $b['url'], 'status' => $b['status']]; }, $S['posts'])), 0, 60)]);
}

/* ----------------------------------------------------------------- create */
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) agentOut(400, ['success' => false, 'error' => 'Send JSON.']);
if (rocBlogEngine($pdo) !== 'pc') agentOut(409, ['success' => false, 'error' => 'The PC writer is switched off in CMS > Blog agent (the server writes the posts).']);
$r = rocBlogCreate($pdo, $ROOT, $in, 'pc');
if (!$r['ok']) agentOut(422, ['success' => false, 'error' => 'Fix the listed fields and send again.', 'errors' => $r['errors']]);
unset($r['ok']);
agentOut(201, ['success' => true] + $r);
