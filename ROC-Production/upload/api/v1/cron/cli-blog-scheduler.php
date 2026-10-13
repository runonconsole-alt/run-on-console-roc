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
 *
 * Each run also:
 *   - refreshes the "Keep reading" links of a post that went live and of its sister
 *     posts in the same topic cluster (internal links both ways);
 *   - when CMS > Blog agent > Writer is "Server", writes the next post with Google Gemini
 *     (free key) about 2 hours before the next free publishing time. No PC needed.
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
$now = date('Y-m-d H:i:s');

if (in_array('--write', array_slice($argv, 1), true)) {
    echo date('c') . ' writer: ' . (rocBlogServerTick($pdo, $ROOT, true) ?? 'nothing to do') . "\n";
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
}

// The server writer (quiet unless it writes or fails).
try {
    $w = rocBlogServerTick($pdo, $ROOT);
    if ($w !== null) echo date('c') . ' writer: ' . $w . "\n";
} catch (\Throwable $e) { echo date('c') . ' writer error: ' . $e->getMessage() . "\n"; }
