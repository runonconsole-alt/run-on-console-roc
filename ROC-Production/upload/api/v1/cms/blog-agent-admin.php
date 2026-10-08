<?php
/**
 * Run On Console (ROC) — CMS > Blog agent (administrators)
 *
 * GET                       status: is a token set, the agent's recent posts, free slots
 * POST {action:'new_token'} makes a new token (shown once; the old one stops working)
 * POST {action:'revoke'}    switches the agent off
 * POST {action:'settings', engine, slots, gemini_key?, model?}
 *      who writes (server = Google Gemini on the server, free key; pc = Claude app on the PC; off)
 *      and the publishing times (Pakistan time). An empty key keeps the saved one; "-" removes it.
 * POST {action:'test_key'}      checks the saved Gemini key and finds the newest free model
 * POST {action:'write_now'}     the server writes the next post now (for the next free slot)
 * POST {action:'plan_refresh'}  rebuilds the content plan from products/categories (keeps what is written)
 * POST {action:'topic', id, op: skip|plan|delete|save, title?, keyword?, stage?}
 * POST {action:'topic_add', pillar, stage, title, keyword}
 *
 * The writer on the PC sends posts to /api/v1/blog-agent.php with this token.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/blog-engine-lib.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');

function rocBaOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }
function rocBaGet(PDO $pdo, string $k): ?string {
    try { $st = $pdo->prepare('SELECT setting_value, updated_at FROM cms_settings WHERE setting_key = ?'); $st->execute([$k]); $r = $st->fetch(PDO::FETCH_ASSOC); }
    catch (\Throwable $e) { return null; }
    return $r ? (string)$r['setting_value'] : null;
}
function rocBaSet(PDO $pdo, string $k, string $v, int $uid): void {
    $st = $pdo->prepare('SELECT COUNT(*) FROM cms_settings WHERE setting_key = ?'); $st->execute([$k]);
    if ((int)$st->fetchColumn() > 0) $pdo->prepare('UPDATE cms_settings SET setting_value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?')->execute([$v, $uid, $k]);
    else $pdo->prepare('INSERT INTO cms_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)')->execute([$k, $v, $uid]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $log = json_decode((string)rocBaGet($pdo, 'blog_agent_log'), true) ?: [];
    // Current status of each logged post.
    if ($log) {
        $st = $pdo->prepare('SELECT status FROM blogs WHERE id = ?');
        foreach ($log as &$l) { $st->execute([$l['id']]); $l['status'] = (string)($st->fetchColumn() ?: 'deleted'); }
        unset($l);
    }
    $plan = rocBlogPlan($pdo);
    rocBlogPlanSync($pdo, $plan);
    $key = trim((string)rocBlogGet($pdo, 'blog_gemini_key', ''));
    $next = rocBlogNextTopic($pdo, $plan);
    $slots = [];
    foreach (rocBlogFreeSlots($pdo) as $utc => $label) $slots[] = $label;
    rocBaOut(200, ['success' => true, 'active' => (string)rocBaGet($pdo, 'blog_agent_token_hash') !== '',
        'created' => (string)rocBaGet($pdo, 'blog_agent_token_created'), 'posts' => $log,
        'engine' => rocBlogEngine($pdo), 'slots' => rocBlogSlots($pdo), 'free_slots' => $slots,
        'gemini' => ['set' => $key !== '', 'end' => $key !== '' ? substr($key, -4) : '', 'model' => (string)rocBlogGet($pdo, 'blog_gemini_model', ROC_GEMINI_MODEL)],
        'state' => rocBlogGet($pdo, 'blog_engine_state', []) ?: new stdClass(), 'plan' => $plan, 'next' => $next['id'] ?? null]);
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$uid = (int)$session['user_id'];
if (($in['action'] ?? '') === 'new_token') {
    $token = 'roc_' . bin2hex(random_bytes(24));
    rocBaSet($pdo, 'blog_agent_token_hash', hash('sha256', $token), $uid);
    rocBaSet($pdo, 'blog_agent_token_created', gmdate('c'), $uid);
    logCmsAudit('cms_blog_agent_token', 'settings', 'blog_agent', ['action' => 'new']);
    rocBaOut(200, ['success' => true, 'token' => $token, 'message' => 'New token made. Copy it now: it is not shown again.']);
}
if (($in['action'] ?? '') === 'revoke') {
    rocBaSet($pdo, 'blog_agent_token_hash', '', $uid);
    logCmsAudit('cms_blog_agent_token', 'settings', 'blog_agent', ['action' => 'revoke']);
    rocBaOut(200, ['success' => true, 'message' => 'Blog agent switched off. It cannot send posts until you make a new token.']);
}
$act = (string)($in['action'] ?? '');
if ($act === 'settings') {
    $engine = (string)($in['engine'] ?? 'pc');
    if (!in_array($engine, ['server', 'pc', 'off'], true)) rocBaOut(422, ['success' => false, 'error' => 'Unknown writer.']);
    $slots = [];
    foreach ((array)($in['slots'] ?? []) as $t) {
        $t = trim((string)$t); if ($t === '') continue;
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $t, $m)) rocBaOut(422, ['success' => false, 'error' => 'Times look like 16:00 or 23:00.']);
        $slots[] = sprintf('%02d:%02d', $m[1], $m[2]);
    }
    $slots = array_values(array_unique($slots)); sort($slots);
    if (!$slots || count($slots) > 6) rocBaOut(422, ['success' => false, 'error' => 'Choose 1 to 6 publishing times a day.']);
    $key = trim((string)($in['gemini_key'] ?? ''));
    if ($key === '-') rocBlogSet($pdo, 'blog_gemini_key', '', $uid);
    elseif ($key !== '') {
        if (!preg_match('/^[A-Za-z0-9_\-]{20,80}$/', $key)) rocBaOut(422, ['success' => false, 'error' => 'That does not look like a Gemini API key (it starts with AIza).']);
        rocBlogSet($pdo, 'blog_gemini_key', $key, $uid);
    }
    if ($engine === 'server' && trim((string)rocBlogGet($pdo, 'blog_gemini_key', '')) === '') rocBaOut(422, ['success' => false, 'error' => 'Paste your free Gemini key first (see the steps), then choose "Server".']);
    $model = trim((string)($in['model'] ?? ''));
    if ($model !== '') {
        if (!preg_match('/^[a-z0-9.\-]{3,60}$/', $model)) rocBaOut(422, ['success' => false, 'error' => 'The model name looks wrong.']);
        rocBlogSet($pdo, 'blog_gemini_model', $model, $uid);
    }
    rocBlogSet($pdo, 'blog_engine', $engine, $uid);
    rocBlogSet($pdo, 'blog_slots', $slots, $uid);
    logCmsAudit('cms_blog_engine', 'settings', 'blog_agent', ['engine' => $engine, 'slots' => $slots]);
    rocBaOut(200, ['success' => true, 'message' => 'Saved. Posts go live at ' . implode(' and ', $slots) . ' Pakistan time' .
        ($engine === 'server' ? '; the server writes them (your PC can be off).' : ($engine === 'pc' ? '; the PC writer (Claude app) writes them.' : '; the writer is off.'))]);
}
if ($act === 'test_key') {
    $key = trim((string)rocBlogGet($pdo, 'blog_gemini_key', ''));
    if ($key === '') rocBaOut(422, ['success' => false, 'error' => 'No key saved yet.']);
    $r = rocGeminiCall($key, 'GET', '/models?pageSize=200', null, 20);
    if (!$r['ok']) rocBaOut(422, ['success' => false, 'error' => 'Google said: ' . $r['error']]);
    $m = rocGeminiPickModel($key);
    rocBaOut(200, ['success' => true, 'message' => 'The key works.' . ($m ? ' Newest free flash model: ' . $m . '.' : '')]);
}
if ($act === 'write_now') {
    @set_time_limit(400);
    $msg = rocBlogServerTick($pdo, dirname(__DIR__, 3), true);
    $ok = $msg !== null && strpos($msg, 'Written:') === 0;
    rocBaOut($ok ? 200 : 422, ['success' => $ok, 'message' => $msg, 'error' => $ok ? null : ($msg ?: 'No free slot in the next 2 days.')]);
}
if ($act === 'plan_refresh') {
    $plan = rocBlogPlanSeed($pdo, rocBlogPlan($pdo));
    rocBlogSet($pdo, 'blog_plan', $plan, $uid);
    rocBaOut(200, ['success' => true, 'message' => 'Plan rebuilt: ' . count($plan['topics']) . ' topics in ' . count($plan['pillars']) . ' clusters.']);
}
if ($act === 'topic' || $act === 'topic_add') {
    $plan = rocBlogPlan($pdo);
    $clean = function ($x, $n) { return trim(mb_substr(strip_tags((string)$x), 0, $n)); };
    if ($act === 'topic_add') {
        $pillars = array_column($plan['pillars'], 'id');
        $t = ['pillar' => (string)($in['pillar'] ?? ''), 'stage' => (string)($in['stage'] ?? ''), 'title' => $clean($in['title'] ?? '', 140), 'keyword' => strtolower($clean($in['keyword'] ?? '', 100))];
        if (!in_array($t['pillar'], $pillars, true) || !in_array($t['stage'], ROC_BLOG_STAGES, true) || mb_strlen($t['title']) < 10 || $t['keyword'] === '')
            rocBaOut(422, ['success' => false, 'error' => 'Choose a cluster and a stage, and write a title (10+ letters) and a keyword.']);
        $t += ['id' => 'm-' . substr(rocBlogSlug($t['title']), 0, 60) . '-' . bin2hex(random_bytes(2)), 'secondary' => [], 'products' => [], 'status' => 'planned', 'manual' => 1];
        array_unshift($plan['topics'], $t);
        $msg = 'Topic added.';
    } else {
        $id = (string)($in['id'] ?? ''); $op = (string)($in['op'] ?? ''); $found = false;
        foreach ($plan['topics'] as $i => &$t) {
            if ($t['id'] !== $id) continue;
            $found = true;
            if ($op === 'skip') $t['status'] = 'skipped';
            elseif ($op === 'plan') $t['status'] = empty($t['post_id']) ? 'planned' : 'written';
            elseif ($op === 'delete') unset($plan['topics'][$i]);
            elseif ($op === 'save') {
                if (isset($in['title'])) $t['title'] = $clean($in['title'], 140) ?: $t['title'];
                if (isset($in['keyword'])) $t['keyword'] = strtolower($clean($in['keyword'], 100)) ?: $t['keyword'];
                if (in_array($in['stage'] ?? '', ROC_BLOG_STAGES, true)) $t['stage'] = $in['stage'];
                $t['manual'] = 1;
            }
            break;
        }
        unset($t);
        if (!$found) rocBaOut(404, ['success' => false, 'error' => 'Topic not found.']);
        $plan['topics'] = array_values($plan['topics']);
        $msg = 'Saved.';
    }
    rocBlogSet($pdo, 'blog_plan', $plan, $uid);
    rocBaOut(200, ['success' => true, 'message' => $msg]);
}
rocBaOut(400, ['success' => false, 'error' => 'Unknown request.']);
