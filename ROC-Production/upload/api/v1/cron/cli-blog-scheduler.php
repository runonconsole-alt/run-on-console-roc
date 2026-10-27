<?php
/**
 * Run On Console — publish scheduled blog posts whose time has come.
 *
 * Run by a cPanel cron job every 10 minutes (it runs on the server, so it works
 * while your computer is off). The cron line is written below the comment.
 *
 * A post is scheduled from the CMS editor ("Schedule"): it is checked and prepared
 * exactly like "Publish" but kept with status 'scheduled' and its publish time in
 * published_at. This script only switches those posts to 'published'.
 *
 *   php cli-blog-scheduler.php            publish what is due
 *   php cli-blog-scheduler.php --list     show the queue, change nothing
 *   php cli-blog-scheduler.php --write    let the server writer write the next post now
 *   php cli-blog-scheduler.php --news     read the news feeds and publish one story now
 *
 * Each run also:
 *   - refreshes the "Keep reading" links of a post that went live and of its sister
 *     posts in the same topic cluster (internal links both ways);
 *   - when CMS > Blog agent > Writer is "Server", writes the next post with Google Gemini
 *     (free key) about 2 hours before the next free publishing time. No PC needed.
 *   - when the writer and the news have nothing to do: writes the About text of 2 product or
 *     platform pages that still have little text (content-writer-lib.php, at most 40 a day);
 *   - live news (CMS > Blog writer > News): reads the trusted news feeds every 20 minutes and
 *     publishes a story at /news/ when 2+ trusted sources (or an official one) report it;
 *   - emails the address in CMS > Blog writer > Email alerts when a post or a story goes live.
 */
// cPanel -> Cron Jobs, "Every 10 minutes":
// */10 * * * *  php /home2/runoncon/public_html/api/v1/cron/cli-blog-scheduler.php >> /home2/runoncon/logs/blog-scheduler.log 2>&1

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) { fwrite(STDERR, date('c') . " blog-scheduler: database unavailable\n"); exit(1); }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

require_once $ROOT . '/api/v1/cms/blog-engine-lib.php';
require_once $ROOT . '/api/v1/cms/news-engine-lib.php';
$now = date('Y-m-d H:i:s');

if (in_array('--write', array_slice($argv, 1), true)) {
    echo date('c') . ' writer: ' . (rocBlogServerTick($pdo, $ROOT, true) ?? 'nothing to do') . "\n";
    exit(0);
}

if (in_array('--news', array_slice($argv, 1), true)) {
    echo date('c') . ' news: ' . (rocNewsTick($pdo, $ROOT, true) ?? 'nothing to do') . "\n";
    exit(0);
}

if (in_array('--list', array_slice($argv, 1), true)) {
    $rows = $pdo->query("SELECT id, title, published_at FROM blogs WHERE status = 'scheduled' ORDER BY published_at")->fetchAll(PDO::FETCH_ASSOC);
    echo "Server time: {$now}\n";
    if (!$rows) { echo "No scheduled posts.\n"; exit(0); }
    foreach ($rows as $r) echo '  ' . $r['published_at'] . '  ' . ($r['published_at'] <= $now ? '(due) ' : '      ') . $r['title'] . "\n";
    exit(0);
}

$due = $pdo->prepare("SELECT id, title, version, published_at FROM blogs WHERE status = 'scheduled' AND published_at <= ? ORDER BY published_at");
$due->execute([$now]);
$rows = $due->fetchAll(PDO::FETCH_ASSOC);

$pub = $pdo->prepare("UPDATE blogs SET status = 'published', content_modified_at = ?, updated_at = ?, version = version + 1
                       WHERE id = ? AND status = 'scheduled' AND version = ?");
foreach ($rows as $r) {
    $pub->execute([$now, $now, $r['id'], $r['version']]);
    if ($pub->rowCount() === 0) { echo date('c') . " skipped (changed meanwhile): {$r['title']}\n"; continue; }
    try {
        $pdo->prepare("INSERT INTO cms_cache_queue (content_type, content_id, target_version_id, action, status, created_at)
                       VALUES ('blog', ?, ?, 'purge_and_prerender', 'pending', ?)")
            ->execute([$r['id'], (int)$r['version'] + 1, $now]);
    } catch (\Throwable $e) { /* optional table */ }
    if (function_exists('logCmsAudit')) {
        try { logCmsAudit('cms_blog_scheduled_publish', 'blog', $r['id'], ['title' => $r['title']]); } catch (\Throwable $e) {}
    }
    echo date('c') . " published: {$r['title']} (scheduled for {$r['published_at']})\n";
    try { $n = rocBlogRelink($pdo, $r['id']); if ($n) echo date('c') . "   linked inside its cluster: {$n} post(s) updated\n"; } catch (\Throwable $e) {}
    try { rocNotifyBlog($pdo, $r['id']); } catch (\Throwable $e) {}
}

// The server writer (quiet unless it writes or fails).
try {
    $w = rocBlogServerTick($pdo, $ROOT);
    if ($w !== null) echo date('c') . ' writer: ' . $w . "\n";
} catch (\Throwable $e) { echo date('c') . ' writer error: ' . $e->getMessage() . "\n"; }

// Live news (quiet unless it publishes or fails).
try {
    $n = rocNewsTick($pdo, $ROOT);
    if ($n !== null) echo date('c') . ' news: ' . $n . "\n";
} catch (\Throwable $e) { echo date('c') . ' news error: ' . $e->getMessage() . "\n"; }

// Covers made while the free picture quota was used up get their own AI picture (one a run).
try {
    $cv = rocBlogRetryCovers($pdo, $ROOT);
    if ($cv !== null) echo date('c') . ' ' . $cv . "\n";
} catch (\Throwable $e) { echo date('c') . ' cover error: ' . $e->getMessage() . "\n"; }

// Product and platform About texts (cli-content-writer.php): 2 a run, at most 40 a day, only when
// the writer and the news did nothing this run, so they always come first.
try {
    if ((!isset($w) || $w === null) && (!isset($n) || $n === null)) {
        require_once $ROOT . '/api/v1/cms/content-writer-lib.php';
        $ct = rocContentTick($pdo, 2);
        if ($ct !== null) echo date('c') . ' ' . $ct . "\n";
    }
    // "X vs Y" comparison verdicts (Deploy 87): one a run, at most 20 a day, after the news and the writer.
    if ((!isset($w) || $w === null) && (!isset($n) || $n === null)) {
        require_once $ROOT . '/api/v1/cms/compare-lib.php';
        $cp = rocCompareTick($pdo, 1);
        if ($cp !== null) echo date('c') . ' ' . $cp . "\n";
        // "Best of" lists on the category pages (Deploy 90): when no comparison was written this run.
        if ($cp === null) {
            require_once $ROOT . '/api/v1/cms/best-lib.php';
            $bl = rocBestTick($pdo);
            if ($bl !== null) echo date('c') . ' ' . $bl . "\n";
        }
    }
} catch (\Throwable $e) { echo date('c') . ' content error: ' . $e->getMessage() . "\n"; }

// Something new went live (a post or a news story): /llms.txt lists it.
try {
    require_once $ROOT . '/api/v1/cms/llms-lib.php';
    if ($rows || (isset($n) && is_string($n) && strpos($n, 'News published:') === 0)) {
        if (rocLlmsWrite($pdo, $ROOT)) echo date('c') . " llms.txt updated\n";
        // Deploy 97: the AI discovery files (/ai/*.json, /.well-known/ai.txt) and the home page date too.
        require_once $ROOT . '/api/v1/cms/ai-files-lib.php';
        if (rocAiFilesWrite($pdo, $ROOT)) echo date('c') . " AI files updated\n";
        if (rocAiDateHome($pdo, $ROOT)) echo date('c') . " home page dated\n";
    }
} catch (\Throwable $e) { echo date('c') . ' llms.txt error: ' . $e->getMessage() . "\n"; }

// IndexNow (Deploy 86): pages published or changed since the last run go to Bing, Yandex, Seznam and Naver.
try {
    require_once $ROOT . '/api/v1/cms/indexnow-lib.php';
    $ix = rocIndexNowTick($pdo, $ROOT);
    if ($ix !== null) echo date('c') . ' ' . $ix . "
";
} catch (\Throwable $e) { echo date('c') . ' IndexNow error: ' . $e->getMessage() . "
"; }

// Indexing (Deploy 117): a few pages a run are checked with Google's URL Inspection API (CMS > Indexing);
// newly indexed pages are emailed once a day. Runs only when ~/config/gsc-key.json exists.
try {
    require_once $ROOT . '/api/v1/cms/gsc-lib.php';
    $gx = rocGscTick($pdo);
    if ($gx !== null) echo date('c') . ' ' . $gx . "
";
} catch (\Throwable $e) { echo date('c') . ' indexing error: ' . $e->getMessage() . "
"; }

// 12:30 Pakistan time on a day a blog post or guide went live: the sitemap goes to Google again (Deploy 126).
try {
    require_once $ROOT . '/api/v1/cms/gsc-lib.php';
    $sm = rocGscSitemapTick($pdo);
    if ($sm !== null) echo date('c') . ' ' . $sm . "\n";
} catch (\Throwable $e) { echo date('c') . ' sitemap submit error: ' . $e->getMessage() . "\n"; }

// Logins for CMS > Live activity (Deploy 120): copied before a logout deletes the session row.
try {
    require_once $ROOT . '/api/v1/cms/form-activity-lib.php';
    rocFaSync($pdo);
} catch (\Throwable $e) { echo date('c') . ' activity error: ' . $e->getMessage() . "
"; }

// New popular Steam games for the Can I run pages (Deploy 87): a few store requests a run, once a month.
try {
    require_once $ROOT . '/api/v1/steam-update-lib.php';
    $su = rocSteamUpdateTick($pdo, $ROOT, 6);
    if ($su !== null) echo date('c') . ' ' . $su . "
";
} catch (\Throwable $e) { echo date('c') . ' Steam update error: ' . $e->getMessage() . "
"; }

// Off-page researcher (Deploy 93): only when turned on in CMS > Off-page; once a day after 12:00 Pakistan time.
try {
    require_once $ROOT . '/api/v1/cms/offpage-lib.php';
    $op = rocOffpageTick($pdo);
    if ($op !== null) echo date('c') . ' ' . $op . "
";
} catch (\Throwable $e) { echo date('c') . ' off-page error: ' . $e->getMessage() . "
"; }

// Daily database backup (api/v1/cms/backup-lib.php): the first run of each Pakistan day makes it, 7 kept.
try {
    require_once $ROOT . '/api/v1/cms/backup-lib.php';
    $bk = rocBackupDaily($pdo, $ROOT);
    if ($bk !== null) echo date('c') . ' ' . $bk . "\n";
} catch (\Throwable $e) { echo date('c') . ' backup error: ' . $e->getMessage() . "\n"; }

// Weekly email to members who turned it on (api/v1/member-lib.php): Friday from 18:00 Pakistan time, once a week.
try {
    require_once $ROOT . '/api/v1/member-lib.php';
    $dg = rocDigestTick($pdo, $ROOT);
    if ($dg !== null) echo date('c') . ' ' . $dg . "\n";
} catch (\Throwable $e) { echo date('c') . ' weekly email error: ' . $e->getMessage() . "\n"; }

// Guides (api/v1/cms/guide-writer-lib.php): from 2 November one on Tuesday, Friday and Saturday for 12:00 (Deploy 126).
try {
    require_once $ROOT . '/api/v1/cms/guide-writer-lib.php';
    $gw = rocGuideTick($pdo, $ROOT);
    if ($gw !== null) echo date('c') . ' ' . $gw . "\n";
} catch (\Throwable $e) { echo date('c') . ' guide writer error: ' . $e->getMessage() . "\n"; }

// Social media kit (api/v1/cms/social-kit-lib.php): script, captions and two pictures for one new post a run,
// only when the writer and the news did nothing this run.
try {
    if ((!isset($w) || $w === null) && (!isset($n) || $n === null)) {
        require_once $ROOT . '/api/v1/cms/social-kit-lib.php';
        $sk = rocKitTick($pdo, $ROOT);
        if ($sk !== null) echo date('c') . ' ' . $sk . "\n";
    }
} catch (\Throwable $e) { echo date('c') . ' social kit error: ' . $e->getMessage() . "\n"; }
