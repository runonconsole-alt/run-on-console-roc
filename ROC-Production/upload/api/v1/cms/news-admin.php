<?php
/**
 * Run On Console (ROC) — CMS > Blog writer > News (administrators)
 *
 * GET                              settings, feeds, status, stories published, stories waiting
 * POST {action:'settings', on, per_day, gap_min, start, end, min_sources, feeds?:[{name,url,official}], notify_email?}
 * POST {action:'feeds_reset'}      back to the built-in list of trusted sources
 * POST {action:'preview'}          reads the feeds now and lists the stories that could be published (nothing is published)
 * POST {action:'publish_now'}      reads the feeds and publishes the best story now (ignores hours and limits)
 * POST {action:'test_email'}       sends a test email to the alert address
 *
 * The scheduler (api/v1/cron/cli-blog-scheduler.php) publishes the news by itself; see news-engine-lib.php.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/news-engine-lib.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');

function rocNaOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }

/** A story as the CMS shows it. */
function rocNaStory(array $c): array {
    $m = $c['match'];
    return ['title' => $c['items'][0]['title'], 'sources' => $c['sources'], 'official' => $c['official'], 'score' => $c['score'],
            'when' => gmdate('Y-m-d H:i:s', $c['ts']),
            'reports' => array_map(function ($it) { return ['src' => $it['src'], 'title' => $it['title'], 'link' => $it['link']]; }, array_slice($c['items'], 0, 6)),
            'links' => array_values(array_merge(
                array_map(function ($p) { return ['title' => $p['title'], 'url' => $p['url'], 'amazon' => !empty($p['dp'])]; }, array_values($m['products'])),
                array_map(function ($x) { return ['title' => 'Best ' . strtolower($x['name']), 'url' => $x['url']]; }, array_values($m['cats'])),
                array_map(function ($x) { return ['title' => $x['name'], 'url' => $x['url']]; }, array_values($m['platforms'])),
                $m['checker'] ? [['title' => 'Can I run it', 'url' => '/compatibility/']] : [])),
           ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $log = rocBlogGet($pdo, 'news_log', []);
    $log = is_array($log) ? $log : [];
    if ($log) {
        $st = $pdo->prepare('SELECT status FROM blogs WHERE id = ?');
        foreach ($log as &$l) { $st->execute([$l['id']]); $l['status'] = (string)($st->fetchColumn() ?: 'deleted'); }
        unset($l);
    }
    [$today] = rocNewsToday($pdo);
    $state = rocNewsState($pdo);
    $waiting = array_map('rocNaStory', array_slice(rocNewsCandidates($pdo, (array)rocBlogGet($pdo, 'news_items', [])), 0, 8));
    rocNaOut(200, ['success' => true, 'settings' => rocNewsSettings($pdo), 'feeds' => rocNewsFeeds($pdo), 'custom_feeds' => is_array(rocBlogGet($pdo, 'news_feeds', null)),
        'state' => $state ?: new stdClass(), 'today' => $today, 'log' => $log, 'waiting' => $waiting,
        'notify_email' => (string)rocBlogGet($pdo, 'notify_email', ''), 'writers' => array_keys(rocBlogWriters($pdo)), 'mail' => function_exists('mail'),
        'smtp' => (function () use ($pdo) { $m = rocBlogGet($pdo, 'notify_smtp', []); $m = is_array($m) ? $m : [];
            return ['set' => !empty($m['pass']), 'host' => (string)($m['host'] ?? ''), 'port' => (int)($m['port'] ?? 465), 'user' => (string)($m['user'] ?? '')]; })()]);
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$uid = (int)$session['user_id'];
$act = (string)($in['action'] ?? '');

if ($act === 'settings') {
    $s = rocNewsSettings($pdo);
    $s['on'] = !empty($in['on']);
    foreach (['per_day' => [1, 12], 'gap_min' => [30, 720], 'min_sources' => [1, 4]] as $k => [$lo, $hi]) {
        if (!isset($in[$k])) continue;
        $v = (int)$in[$k];
        if ($v < $lo || $v > $hi) rocNaOut(422, ['success' => false, 'error' => "Choose {$k} between {$lo} and {$hi}."]);
        $s[$k] = $v;
    }
    foreach (['start', 'end'] as $k) {
        if (!isset($in[$k])) continue;
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$in[$k])) rocNaOut(422, ['success' => false, 'error' => 'Times look like 09:00 or 23:00.']);
        $s[$k] = (string)$in[$k];
    }
    if ($s['start'] >= $s['end']) rocNaOut(422, ['success' => false, 'error' => 'The start time must be before the end time.']);
    if ($s['on'] && !rocBlogWriters($pdo)) rocNaOut(422, ['success' => false, 'error' => 'Save a free Groq or Gemini key in the Blog tab first: the news is written with the same key.']);
    if (isset($in['feeds']) && is_array($in['feeds'])) {
        $feeds = [];
        foreach ($in['feeds'] as $f) {
            $u = trim((string)($f['url'] ?? ''));
            if ($u === '') continue;
            if (!preg_match('#^https://[^\s"<>]{8,500}$#i', $u)) rocNaOut(422, ['success' => false, 'error' => 'Feed addresses start with https:// (' . mb_substr($u, 0, 60) . ').']);
            $feeds[] = ['name' => trim(mb_substr(strip_tags((string)($f['name'] ?? '')), 0, 60)) ?: (string)parse_url($u, PHP_URL_HOST), 'url' => $u, 'official' => !empty($f['official'])];
        }
        if (count($feeds) < 2) rocNaOut(422, ['success' => false, 'error' => 'Keep at least 2 sources: a story needs 2 sources (or 1 official one).']);
        rocBlogSet($pdo, 'news_feeds', array_slice($feeds, 0, 40), $uid);
    }
    if (isset($in['smtp']) && is_array($in['smtp'])) {
        $old = rocBlogGet($pdo, 'notify_smtp', []); $old = is_array($old) ? $old : [];
        $sm = $in['smtp'];
        if (($sm['remove'] ?? false)) rocBlogSet($pdo, 'notify_smtp', '', $uid);
        else {
            $host = trim((string)($sm['host'] ?? '')); $user = trim((string)($sm['user'] ?? '')); $pass = (string)($sm['pass'] ?? '');
            $port = (int)($sm['port'] ?? 465);
            if ($host !== '' || $user !== '' || $pass !== '') {
                if (!preg_match('/^[a-z0-9.-]{3,120}$/i', $host)) rocNaOut(422, ['success' => false, 'error' => 'The mail server looks like mail.runonconsole.com.']);
                if (!filter_var($user, FILTER_VALIDATE_EMAIL)) rocNaOut(422, ['success' => false, 'error' => 'The mailbox is a full email address, like alerts@runonconsole.com.']);
                if (!in_array($port, [465, 587], true)) rocNaOut(422, ['success' => false, 'error' => 'Use port 465 (SSL) or 587.']);
                if ($pass === '' && empty($old['pass'])) rocNaOut(422, ['success' => false, 'error' => 'Type the mailbox password.']);
                rocBlogSet($pdo, 'notify_smtp', ['host' => strtolower($host), 'port' => $port, 'user' => $user, 'pass' => $pass !== '' ? $pass : $old['pass']], $uid);
            }
        }
    }
    if (isset($in['notify_email'])) {
        $e = trim((string)$in['notify_email']);
        if ($e !== '' && !filter_var($e, FILTER_VALIDATE_EMAIL)) rocNaOut(422, ['success' => false, 'error' => 'That email address looks wrong.']);
        rocBlogSet($pdo, 'notify_email', $e, $uid);
    }
    rocBlogSet($pdo, 'news_settings', $s, $uid);
    if (function_exists('logCmsAudit')) logCmsAudit('cms_news_settings', 'settings', 'news', ['on' => $s['on'], 'per_day' => $s['per_day']]);
    rocNaOut(200, ['success' => true, 'message' => $s['on']
        ? 'Saved. Up to ' . $s['per_day'] . ' stories a day between ' . $s['start'] . ' and ' . $s['end'] . ' Pakistan time, at least ' . round($s['gap_min'] / 60, 1) . ' hours apart.'
        : 'Saved. Live news is off.']);
}
if ($act === 'feeds_reset') {
    rocBlogSet($pdo, 'news_feeds', '', $uid);
    rocNaOut(200, ['success' => true, 'message' => 'Back to the built-in list of ' . count(ROC_NEWS_FEEDS) . ' trusted sources.']);
}
if ($act === 'preview') {
    @set_time_limit(180);
    [$items, $errors] = rocNewsFetch($pdo);
    $s = rocNewsState($pdo); $s['last_fetch'] = gmdate('Y-m-d H:i:s'); $s['feed_errors'] = $errors; $s['items'] = count($items); rocNewsSaveState($pdo, $s);
    $c = rocNewsCandidates($pdo, $items);
    rocNaOut(200, ['success' => true, 'message' => count($items) . ' reports read from ' . (count(rocNewsFeeds($pdo)) - count($errors)) . ' sources; ' . count($c) . ' stories can be published.',
        'waiting' => array_map('rocNaStory', array_slice($c, 0, 8)), 'errors' => $errors]);
}
if ($act === 'publish_now') {
    @set_time_limit(400);
    $msg = rocNewsTick($pdo, dirname(__DIR__, 3), true);
    $ok = $msg !== null && strpos($msg, 'News published:') === 0;
    rocNaOut($ok ? 200 : 422, ['success' => $ok, 'message' => $msg, 'error' => $ok ? null : ($msg ?: 'Nothing to publish.')]);
}
if ($act === 'test_email') {
    $e = trim((string)rocBlogGet($pdo, 'notify_email', ''));
    if ($e === '') rocNaOut(422, ['success' => false, 'error' => 'Save an email address first.']);
    $ok = rocNotify($pdo, 'Test from Run On Console', '<p>This is a test. Alerts for new blog posts and news stories will come to this address.</p>');
    rocNaOut($ok ? 200 : 422, ['success' => $ok, 'message' => 'Test email sent to ' . $e . '. If it is not in the inbox in 5 minutes, look in Spam and mark it "Not spam".',
        'error' => $ok ? null : 'Not sent: ' . ((string)(rocNewsState($pdo)['last_email_error'] ?? '')) . '. Fill in "Send through a mailbox" below (a cPanel email account) and try again.']);
}
rocNaOut(400, ['success' => false, 'error' => 'Unknown request.']);
