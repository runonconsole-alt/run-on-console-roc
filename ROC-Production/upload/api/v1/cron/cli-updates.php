<?php
/**
 * Run On Console — the Updates hub (Deploy 71).
 *
 *   php cli-updates.php                  dry run: shows what would change
 *   php cli-updates.php --apply          makes the changes, with a backup
 *   php cli-updates.php --rollback=FILE  puts .htaccess and the menu back (the new columns stay, they are harmless)
 *
 * What --apply does:
 *   1. blogs table: columns topic_group, topic_cat, tags (topic and tags of blogs, guides and news, set in the editor).
 *   2. .htaccess: /updates/ goes to blog-render.php (blogs, guides and news together, with filters).
 *   3. Menu saved in CMS > Menus & footer:
 *      - header: BLOGS, GUIDES and NEWS become one UPDATES menu (Blogs, Guides, News, All updates);
 *      - footer: the POLICIES column becomes "UPDATES & TERMS" with Blogs, News and Guides first,
 *        and they leave the other columns.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const UP_BEGIN = '  # ROC updates BEGIN (cli-updates.php)';
const UP_END   = '  # ROC updates END';
const UP_BLOCK = UP_BEGIN . "\n"
    . "  # Blogs, guides and news together, with filters (blog-render.php)\n"
    . "  RewriteRule ^updates$ /updates/ [R=301,L]\n"
    . "  RewriteRule ^updates/$ blog-render.php?updates=1 [L,QSA]\n"
    . UP_END . "\n";

const UP_MENU = ['label' => 'UPDATES', 'url' => '/updates/', 'children' => [
    ['title' => 'Blogs', 'desc' => 'Reviews, comparisons and buying advice', 'url' => '/blogs/', 'icon' => 'book'],
    ['title' => 'Guides', 'desc' => 'Step by step: buying, setup, game settings', 'url' => '/guides/', 'icon' => 'file-text'],
    ['title' => 'News', 'desc' => 'Gaming and hardware news, checked daily', 'url' => '/news/', 'icon' => 'newspaper'],
    ['title' => 'All updates', 'desc' => 'Everything new, with filters', 'url' => '/updates/', 'icon' => 'zap'],
]];

echo "Run On Console - Updates hub (/updates/)\npublic_html: {$ROOT}\n\n";

ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("STOP: could not connect to the database.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/site-layer-lib.php';

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b)) exit("No backup found in {$rollback}\n");
    if (is_string($b['htaccess'] ?? null) && $b['htaccess'] !== '') { file_put_contents($ROOT . '/.htaccess', $b['htaccess']); echo "Restored .htaccess\n"; }
    if (!empty($b['nav']) && is_array($b['nav'])) { rocLayerSave($pdo, $ROOT, 'nav', $b['nav'], null); echo "Restored the saved menu\n"; }
    exit("Done.\n");
}

$problems = [];
foreach (['blog-render.php', 'api/v1/cms/topics-lib.php', '.htaccess'] as $f) if (!is_file($ROOT . '/' . $f)) $problems[] = "public_html/{$f} is missing. Unzip the deploy again.";
if (is_file($ROOT . '/blog-render.php') && strpos((string)file_get_contents($ROOT . '/blog-render.php'), 'rocRenderUpdates') === false) $problems[] = 'blog-render.php is the old version. Unzip the deploy again.';
$ht = is_file($ROOT . '/.htaccess') ? (string)file_get_contents($ROOT . '/.htaccess') : '';
$done = strpos($ht, UP_BEGIN) !== false;
$anchor = null;
foreach (['  # ROC guides BEGIN', '  # ROC news BEGIN', '  # Blogs come from the CMS database', '  # Real file or folder'] as $a) if (strpos($ht, $a) !== false) { $anchor = $a; break; }
if (!$done && $anchor === null) $problems[] = '.htaccess has no place for the updates rules.';
if ($problems) { echo "STOP:\n  - " . implode("\n  - ", $problems) . "\n"; exit(1); }

// 1. Columns.
$cols = [];
foreach (['topic_group' => 'VARCHAR(40) NULL', 'topic_cat' => 'VARCHAR(80) NULL', 'tags' => 'VARCHAR(500) NULL'] as $c => $type) {
    try { $pdo->query("SELECT {$c} FROM blogs LIMIT 1"); } catch (\Throwable $e) { $cols[$c] = $type; }
}
echo $cols ? '  +  blogs: columns ' . implode(', ', array_keys($cols)) . "\n" : "  =  blogs already has the topic and tag columns\n";
echo $done ? "  =  .htaccess already has /updates/\n" : "  +  .htaccess: /updates/ goes to blog-render.php\n";

// 3. Menu.
$raw = rocLayerSettingGet($pdo, 'site_nav');
$nav = $raw ? json_decode($raw, true) : null;
$navNew = null;
if (is_array($nav) && !empty($nav['header'])) {
    $navNew = $nav; $changed = false;
    $u = function ($it) { return rtrim((string)($it['url'] ?? ''), '/'); };
    $hasUpd = false; $at = null; $keep = [];
    foreach ($navNew['header'] as $it) {
        if ($u($it) === '/updates') { $hasUpd = true; $keep[] = $it; continue; }
        if (in_array($u($it), ['/blogs', '/guides', '/news'], true) && empty($it['children'])) { if ($at === null) $at = count($keep); $changed = true; continue; }
        $keep[] = $it;
    }
    if (!$hasUpd) { array_splice($keep, $at ?? count($keep), 0, [UP_MENU]); $changed = true; }
    $navNew['header'] = $keep;
    if ($changed) echo "  +  Header: BLOGS, GUIDES and NEWS become one UPDATES menu\n";
    // Footer: the policies column becomes UPDATES & TERMS with Blogs, News, Guides first.
    $cols2 = (array)($navNew['footer']['columns'] ?? []);
    $pi = null;
    foreach ($cols2 as $ci => $c) {
        $t = strtolower((string)($c['title'] ?? ''));
        $urls = array_map($u, (array)($c['links'] ?? []));
        if (strpos($t, 'polic') !== false || strpos($t, 'terms') !== false || in_array('/privacy-policy', $urls, true)) { $pi = $ci; break; }
    }
    if ($pi !== null) {
        $first = [['label' => 'Blogs', 'url' => '/blogs/', 'icon' => 'book'], ['label' => 'News', 'url' => '/news/', 'icon' => 'newspaper'], ['label' => 'Guides', 'url' => '/guides/', 'icon' => 'file-text']];
        foreach ($cols2 as $ci => $c) {
            $cols2[$ci]['links'] = array_values(array_filter((array)($c['links'] ?? []), function ($l) use ($u) { return !in_array($u($l), ['/blogs', '/news', '/guides'], true); }));
        }
        $before = json_encode($navNew['footer']['columns'] ?? []);
        $cols2[$pi]['title'] = 'UPDATES & TERMS';
        $cols2[$pi]['links'] = array_merge($first, $cols2[$pi]['links']);
        $navNew['footer']['columns'] = $cols2;
        if (json_encode($cols2) !== $before) { $changed = true; echo "  +  Footer: \"UPDATES & TERMS\" (Blogs, News, Guides, then the policies)\n"; }
    } else echo "  =  Footer: no policies column found, not changed\n";
    if (!$changed) { $navNew = null; echo "  =  Menu already done\n"; }
} else echo "  =  Menu: the built-in one (comes with this deploy)\n";

if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }

$file = $HOME . '/roc-updates-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'htaccess' => $done ? '' : $ht, 'nav' => $navNew !== null ? $nav : null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
foreach ($cols as $c => $type) { $pdo->exec("ALTER TABLE blogs ADD COLUMN {$c} {$type}"); echo "Column {$c} added\n"; }
if (!$done) {
    file_put_contents($ROOT . '/.htaccess.roc-tmp', str_replace($anchor, UP_BLOCK . "\n" . $anchor, $ht));
    rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
    echo ".htaccess updated\n";
}
if ($navNew !== null) { $n = rocLayerSave($pdo, $ROOT, 'nav', $navNew, null); echo "Menu updated ({$n} built page(s) redrawn)\n"; }
echo "Backup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n\nDone. Open https://runonconsole.com/updates/ . In the CMS editor (picture tab) each post now has a topic and tags.\n";
