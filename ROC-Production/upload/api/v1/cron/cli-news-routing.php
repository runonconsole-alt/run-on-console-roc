<?php
/**
 * Run On Console — switch on the live news section (/news/).
 *
 *   php cli-news-routing.php                  dry run: shows what would change
 *   php cli-news-routing.php --apply          makes the changes, with a backup
 *   php cli-news-routing.php --rollback=FILE  undoes an --apply using its backup
 *
 * What --apply does:
 *   1. .htaccess: /news/, /news/{slug}/ and /sitemaps/news-sitemap.xml go to blog-render.php
 *      (block between "ROC news BEGIN/END" markers, before the blog rules).
 *   2. Menu: when a menu was saved in CMS > Menus & footer, adds NEWS after BLOGS in the header
 *      and "News" after "Blogs" in the footer (the built-in menu already has them).
 *   3. Live news on (5 stories a day max, 09:00-23:00 Pakistan time, 2+ trusted sources),
 *      unless it was already set in the CMS. It uses the blog writer's Groq/Gemini key.
 *
 * Files needed first (in the deploy zip): blog-render.php, api/v1/cms/news-engine-lib.php,
 * api/v1/cms/news-admin.php, api/v1/cron/cli-blog-scheduler.php.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const NEWS_BEGIN = '  # ROC news BEGIN (cli-news-routing.php)';
const NEWS_END   = '  # ROC news END';
const NEWS_BLOCK = NEWS_BEGIN . "\n"
    . "  # Live news from the CMS database (blog-render.php, posts with the category News)\n"
    . "  RewriteRule ^news$ /news/ [R=301,L]\n"
    . "  RewriteRule ^news/index\\.html$ /news/ [R=301,L,NC]\n"
    . "  RewriteRule ^news/([a-z0-9-]+)$ /news/\$1/ [R=301,L]\n"
    . "  RewriteRule ^news/$ blog-render.php?news=1 [L,QSA]\n"
    . "  RewriteRule ^news/([a-z0-9-]+)/$ blog-render.php?news=1&slug=\$1 [L,QSA]\n"
    . "  RewriteRule ^sitemaps/news-sitemap\\.xml$ blog-render.php?news=1&sitemap=1 [L]\n"
    . NEWS_END . "\n";

echo "Run On Console - live news (/news/)\npublic_html: {$ROOT}\n\n";

ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("STOP: could not connect to the database.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/news-engine-lib.php';

/* -------------------------------------------------------------- rollback */
if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b)) exit("No backup found in {$rollback}\n");
    if (isset($b['htaccess']) && is_string($b['htaccess']) && $b['htaccess'] !== '') { file_put_contents($ROOT . '/.htaccess', $b['htaccess']); echo "Restored .htaccess\n"; }
    if (array_key_exists('nav', $b) && $b['nav'] !== null) { rocLayerSave($pdo, $ROOT, 'nav', (array)$b['nav'], null); echo "Restored the saved menu\n"; }
    if (array_key_exists('news_settings', $b)) { rocLayerSettingSet($pdo, 'news_settings', (string)$b['news_settings'], null); echo "Restored the news settings\n"; }
    exit("Done.\n");
}

/* ----------------------------------------------------------------- checks */
$problems = [];
foreach (['blog-render.php', 'api/v1/cms/news-engine-lib.php', 'api/v1/cms/news-admin.php', '.htaccess'] as $f) {
    if (!is_file($ROOT . '/' . $f)) $problems[] = "public_html/{$f} is missing.";
}
if (is_file($ROOT . '/blog-render.php') && strpos((string)file_get_contents($ROOT . '/blog-render.php'), 'rocNewsSitemap') === false) $problems[] = 'public_html/blog-render.php is the old version (no news). Unzip the deploy again.';
$ht = is_file($ROOT . '/.htaccess') ? (string)file_get_contents($ROOT . '/.htaccess') : '';
$htDone = strpos($ht, NEWS_BEGIN) !== false;
$anchor = null;
foreach (['  # Blogs come from the CMS database', '  RewriteRule ^blogs$ /blogs/', '  # Sitemaps are generated automatically', '  # Real file or folder'] as $a) {
    if (strpos($ht, $a) !== false) { $anchor = $a; break; }
}
if (!$htDone && $anchor === null) $problems[] = '.htaccess has no place for the news rules (expected the blog rules).';
if ($problems) { echo "STOP:\n  - " . implode("\n  - ", $problems) . "\n"; exit(1); }

/* ---------------------------------------------------------------- the plan */
echo $htDone ? "  =  .htaccess already has the news rules\n" : "  +  .htaccess: /news/ and the news sitemap go to blog-render.php\n";

$navRaw = rocLayerSettingGet($pdo, 'site_nav');
$nav = $navRaw ? json_decode($navRaw, true) : null;
$navNew = null;
if (is_array($nav) && !empty($nav['header'])) {
    $hasNews = false;
    foreach ($nav['header'] as $it) if (rtrim((string)($it['url'] ?? ''), '/') === '/news') $hasNews = true;
    if (!$hasNews) {
        $navNew = $nav;
        $at = count($navNew['header']);
        foreach ($navNew['header'] as $i => $it) if (rtrim((string)($it['url'] ?? ''), '/') === '/blogs') $at = $i + 1;
        array_splice($navNew['header'], $at, 0, [['label' => 'NEWS', 'url' => '/news/']]);
        foreach ((array)($navNew['footer']['columns'] ?? []) as $ci => $col) {
            $links = (array)($col['links'] ?? []); $pos = null; $has = false;
            foreach ($links as $li => $l) { $u = rtrim((string)($l['url'] ?? ''), '/'); if ($u === '/blogs') $pos = $li + 1; if ($u === '/news') $has = true; }
            if ($pos !== null && !$has) { array_splice($links, $pos, 0, [['label' => 'News', 'url' => '/news/', 'icon' => 'newspaper']]); $navNew['footer']['columns'][$ci]['links'] = $links; }
        }
        echo "  +  Menu (saved in the CMS): NEWS after BLOGS in the header, News in the footer\n";
    } else echo "  =  Menu already has NEWS\n";
} else echo "  =  Menu: the built-in one (NEWS comes with this deploy)\n";

$setRaw = rocLayerSettingGet($pdo, 'news_settings');
$setNew = null;
if ($setRaw === null || $setRaw === '') {
    $setNew = ['on' => true, 'per_day' => 5, 'gap_min' => 120, 'start' => '09:00', 'end' => '23:00', 'min_sources' => 2];
    echo "  +  Live news ON: up to 5 stories a day, 09:00-23:00 Pakistan time, 2+ trusted sources\n";
    if (!rocBlogWriters($pdo)) echo "     (no Groq or Gemini key is saved yet: nothing is written until one is added in the CMS)\n";
} else echo "  =  News settings already set in the CMS (kept)\n";

if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }

/* ------------------------------------------------------------------- apply */
$file = $HOME . '/roc-news-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'htaccess' => $htDone ? null : $ht, 'nav' => $navNew !== null ? $nav : null,
                                      'news_settings' => $setNew !== null ? (string)$setRaw : null], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
if (!$htDone) {
    $new = str_replace($anchor, NEWS_BLOCK . "\n" . $anchor, $ht);
    file_put_contents($ROOT . '/.htaccess.roc-tmp', $new);
    rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
    echo ".htaccess updated\n";
}
if ($navNew !== null) { $n = rocLayerSave($pdo, $ROOT, 'nav', $navNew, null); echo "Menu updated ({$n} built page(s) redrawn)\n"; }
if ($setNew !== null) { rocBlogSet($pdo, 'news_settings', $setNew); echo "Live news switched on\n"; }

echo "\nDone. Backup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
echo "Check: https://runonconsole.com/news/  and  https://runonconsole.com/sitemaps/news-sitemap.xml\n";
echo "First story now (optional): php {$ROOT}/api/v1/cron/cli-blog-scheduler.php --news\n";
