<?php
/**
 * Run On Console — GTA 6 posts today (Deploy 186).
 *
 *   php cli-gta6-now.php                            dry run: the times it would use today and the GTA 6 news it sees
 *   php cli-gta6-now.php --apply                    writes 8 GTA 6 blog posts for today + up to 3 GTA 6 news stories
 *   php cli-gta6-now.php --blogs=6 --news=2 --guides=4 --apply
 *
 * Owner, 2026-11-19 (GTA 6 launch day): at least 10 GTA 6 posts today, blogs and news together. The blog writer
 * normally has two times a day (12:00 and 18:00); this adds extra times for today only (45 minutes apart, from
 * 20 minutes from now until 23:15 Pakistan time), writes the next GTA 6 plan topics into them right away, each with
 * the GTA 6 fact sheet, and then puts the normal times back. News: only GTA 6 stories reported by 2+ trusted sources
 * (the usual news rules), with the fact sheet added to the news writer's brief. Run cli-gta6-plan.php --apply first.
 * Guides: --guides=N (default 4) GTA 6 how-to guides, published at once at /guides/, also with the fact sheet.
 * The AI is free (Groq/Gemini), so it waits --pause seconds (default 45) between articles.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$ROOT = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$nBlogs = 8; $nNews = 3; $nGuides = 4; $pause = 45;
foreach ($args as $a) {
    if (strpos($a, '--blogs=') === 0) $nBlogs = max(0, min(14, (int)substr($a, 8)));
    if (strpos($a, '--news=') === 0) $nNews = max(0, min(6, (int)substr($a, 7)));
    if (strpos($a, '--guides=') === 0) $nGuides = max(0, min(4, (int)substr($a, 9)));
    if (strpos($a, '--pause=') === 0) $pause = max(0, min(300, (int)substr($a, 8)));
}
@set_time_limit(0);

ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
require_once $ROOT . '/api/v1/cms/blog-engine-lib.php';
require_once $ROOT . '/api/v1/cms/news-engine-lib.php';
require_once $ROOT . '/api/v1/cms/guide-writer-lib.php';
require_once $ROOT . '/api/v1/gta6-lib.php';
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("No database connection.\n");
$ping = function () use ($pdo) { try { $pdo->query('SELECT 1'); } catch (\Throwable $e) {} };
echo "Run On Console - GTA 6 posts today\n\n";
if (rocAiEnded($pdo)) exit("The AI writing period has ended (" . rocAiLastDay($pdo) . ").\n");
if (!rocBlogWriters($pdo)) exit("No Groq or Gemini key saved (CMS > Blog writer).\n");

// Open GTA 6 topics.
$plan = rocBlogPlan($pdo);
$open = array_values(array_filter($plan['topics'], function ($t) { return strpos((string)$t['id'], 'gta6-') === 0 && ($t['status'] ?? '') === 'planned'; }));
echo 'GTA 6 topics still to write: ' . count($open) . "\n";
if (!$open && $nBlogs) echo "  (none: run cli-gta6-plan.php --apply first)\n";
$nBlogs = min($nBlogs, count($open));

// Extra times today, 45 minutes apart.
$tz = new DateTimeZone(ROC_BLOG_TZ);
$normal = rocBlogTimes($pdo);
$t = new DateTime('now', $tz); $t->modify('+20 minutes');
$mins = (int)ceil(((int)$t->format('G') * 60 + (int)$t->format('i')) / 15) * 15;   // next quarter hour
if ($mins >= 1440) $t->modify('+1 day');
$t->setTime(intdiv($mins % 1440, 60), $mins % 60);
$extra = [];
while (count($extra) < $nBlogs && $t->format('Y-m-d') === (new DateTime('now', $tz))->format('Y-m-d') && $t->format('H:i') <= '23:15') {
    $hm = $t->format('H:i');
    $near = false;
    foreach ($normal as $n) if (abs(strtotime('2000-01-01 ' . $n) - strtotime('2000-01-01 ' . $hm)) < 2700) $near = true;
    if (!$near) $extra[] = $hm;
    $t->modify('+45 minutes');
}
echo 'Extra blog times today (Pakistan time): ' . ($extra ? implode(', ', $extra) : 'none left today') . "\n";
echo 'Normal times stay: ' . implode(', ', $normal) . "\n";
foreach (array_slice($open, 0, count($extra)) as $i => $tp) printf("  %2d. %s\n", $i + 1, $tp['title']);

// GTA 6 news reported by 2+ trusted sources.
[$items] = rocNewsFetch($pdo);
$isGta = function (array $c) { return (bool)preg_match('/\b(gta ?(6|vi)|grand theft auto (6|vi))\b/i', implode(' ', array_column($c['items'], 'title'))); };
$cands = array_values(array_filter(rocNewsCandidates($pdo, $items), $isGta));
echo "\nGTA 6 news stories ready (2+ sources): " . count($cands) . "\n";
foreach (array_slice($cands, 0, $nNews) as $c) echo '  - ' . $c['items'][0]['title'] . ' (' . $c['sources'] . " sources)\n";
// GTA 6 guides: [key, title idea, type, hardware group, product category, focus keyword, extra rules].
$gx = rocGta6Facts() . "\nGUIDE RULES: link /gta-6/ once in the first two paragraphs (anchor \"GTA 6 release date, price and PC status\"). Explain only general console, TV and audio steps that are true for every game; never invent GTA 6 menus, graphics modes or settings names.";
$guides = [
    ['gta6-guide-preload', 'How to download and install GTA 6 on PS5 and Xbox Series X|S', 'Setup', 'gaming-hardware', 'handhelds', 'how to download gta 6', $gx],
    ['gta6-guide-space', 'How to free up 212 GB of space for GTA 6 on PS5 and Xbox', 'How to use', 'pc-hardware', 'monitors', 'gta 6 storage space', $gx],
    ['gta6-guide-tv', 'How to set up your TV or monitor for GTA 6 on PS5 and Xbox', 'Setup', 'pc-hardware', 'monitors', 'best tv settings for gta 6', $gx],
    ['gta6-guide-audio', 'How to set up a headset and sound for GTA 6 on PS5 and Xbox', 'Setup', 'gaming-hardware', 'audio', 'gta 6 headset setup', $gx],
];
$doneKeys = array_column(rocGuideLog($pdo), 'key');
$guides = array_slice(array_values(array_filter($guides, function ($g) use ($doneKeys) { return !in_array($g[0], $doneKeys, true); })), 0, $nGuides);
echo "\nGTA 6 guides to write: " . count($guides) . "\n";
foreach ($guides as $g) echo '  - ' . $g[1] . "\n";
if (!$apply) exit("\nDry run: nothing written. Run again with --apply.\n");

// --- blogs
$written = 0;
if ($extra) {
    rocBlogSet($pdo, 'blog_times', array_values(array_unique(array_merge($normal, $extra))));
    try {
        foreach ($extra as $i => $hm) {
            if ($i) { echo "  (waiting {$pause}s)\n"; sleep($pause); $ping(); }
            $st = rocBlogGet($pdo, 'blog_engine_state', []);
            if (is_array($st)) { unset($st['last_try']); rocBlogSet($pdo, 'blog_engine_state', $st); }
            $next = rocBlogBrief($pdo);
            if (!$next || strpos((string)$next['topic_id'], 'gta6-') !== 0) { echo "No GTA 6 topic left.\n"; break; }
            $r = rocBlogServerTick($pdo, $ROOT, true);
            echo '  Blog ' . ($i + 1) . ': ' . ($r ?? 'nothing written') . "\n";
            if ($r !== null && strpos($r, 'Written:') === 0) $written++;
        }
    } finally {
        $ping();
        rocBlogSet($pdo, 'blog_times', $normal);   // only today gets the extra times
    }
}

// --- news
$news = 0;
foreach (array_slice($cands, 0, $nNews) as $k => $c) {
    if ($k || $written) { echo "  (waiting {$pause}s)\n"; sleep($pause); $ping(); }
    $prompt = rocNewsPrompt($c) . "\n\n" . rocGta6Facts() . "\nIn this news story link /gta-6/ once only if the links above allow it; never contradict the fact sheet.";
    $msg = null;
    for ($try = 1; $try <= 2; $try++) {
        $model = null;
        $w = rocBlogWriteAny($pdo, $prompt, $model, true);
        if (!$w['ok']) { $msg = $w['error']; continue; }
        $r = rocNewsCreate($pdo, $ROOT, $w['article'], $c, 'server (' . $model . ')');
        if ($r['ok']) {
            rocNewsMarkDone($pdo, $c, 'published', $r['id']);
            try { rocNewsToBar($pdo, $ROOT, $r['title'], '/news/' . $r['slug'] . '/'); } catch (\Throwable $e) {}
            $msg = 'News published: ' . $r['url']; $news++;
            break;
        }
        $msg = 'Article not accepted: ' . rocSeoReasons($r['errors']);
        $prompt .= "\n\nYour previous answer was rejected: " . rocSeoReasons($r['errors']) . ' Fix this.';
    }
    if (strpos((string)$msg, 'News published') !== 0) rocNewsMarkDone($pdo, $c, 'failed');
    echo '  News ' . ($k + 1) . ': ' . $msg . "\n";
}
// --- guides
$gdone = 0;
foreach ($guides as $k => $g) {
    if ($k || $written || $news) { echo "  (waiting {$pause}s)\n"; sleep($pause); $ping(); }
    $r = rocGuideWrite($pdo, $ROOT, $g);
    echo '  Guide ' . ($k + 1) . ': ' . ($r['ok'] ? 'published ' . $r['url'] . ' (' . $r['words'] . ' words)' : $r['error']) . "\n";
    if ($r['ok']) $gdone++;
}
rocBlogLog($pdo, ['type' => 'plan', 'message' => "GTA 6 day: {$written} blog posts, {$news} news stories and {$gdone} guides written"]);
echo "\nDone: {$written} GTA 6 blog post(s) scheduled for today, {$news} news story(ies) and {$gdone} guide(s) published.\n";
echo "The normal 12:00 and 18:00 posts are GTA 6 topics too (cli-gta6-plan.php).\n";
