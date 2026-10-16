<?php
/**
 * Run On Console — switch on the Guides section (/guides/, Deploy 70).
 *
 *   php cli-guides.php                  dry run: shows what would change
 *   php cli-guides.php --apply          makes the changes, with a backup
 *   php cli-guides.php --rollback=FILE  undoes an --apply using its backup
 *
 * What --apply does:
 *   1. .htaccess: /guides/, /guides/page/N/, /guides/{slug}/ and /sitemaps/guides-sitemap.xml go to
 *      blog-render.php (block between "ROC guides BEGIN/END", before the news rules).
 *   2. Menu saved in CMS > Menus & footer: GUIDES after BLOGS in the header, "Guides" after "News" in the footer.
 * Guides are written in the CMS like a blog post, with the category "Guides: Buying", "Guides: How to use",
 * "Guides: Setup", "Guides: Game setup" (or any "Guides: <type>", or just "Guides").
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const GD_BEGIN = '  # ROC guides BEGIN (cli-guides.php)';
const GD_END   = '  # ROC guides END';
const GD_BLOCK = GD_BEGIN . "\n"
    . "  # Guides written in the CMS (blog posts with the category \"Guides\" or \"Guides: <type>\")\n"
    . "  RewriteRule ^guides$ /guides/ [R=301,L]\n"
    . "  RewriteRule ^guides/page/([0-9]+)$ /guides/page/\$1/ [R=301,L]\n"
    . "  RewriteRule ^guides/page/([0-9]+)/$ blog-render.php?guides=1&page=\$1 [L,QSA]\n"
    . "  RewriteRule ^guides/([a-z0-9-]+)$ /guides/\$1/ [R=301,L]\n"
    . "  RewriteRule ^guides/$ blog-render.php?guides=1 [L,QSA]\n"
    . "  RewriteRule ^guides/([a-z0-9-]+)/$ blog-render.php?guides=1&slug=\$1 [L,QSA]\n"
    . "  RewriteRule ^sitemaps/guides-sitemap\\.xml$ blog-render.php?guides=1&sitemap=1 [L]\n"
    . GD_END . "\n";

echo "Run On Console - Guides section (/guides/)\npublic_html: {$ROOT}\n\n";

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
foreach (['blog-render.php', '.htaccess'] as $f) if (!is_file($ROOT . '/' . $f)) $problems[] = "public_html/{$f} is missing.";
if (is_file($ROOT . '/blog-render.php') && strpos((string)file_get_contents($ROOT . '/blog-render.php'), 'rocRenderGuides') === false) $problems[] = 'blog-render.php is the old version. Unzip the deploy again.';
$ht = is_file($ROOT . '/.htaccess') ? (string)file_get_contents($ROOT . '/.htaccess') : '';
$done = strpos($ht, GD_BEGIN) !== false;
$anchor = null;
foreach (['  # ROC news BEGIN', '  # ROC pages BEGIN', '  # Blogs come from the CMS database', '  # Real file or folder'] as $a) if (strpos($ht, $a) !== false) { $anchor = $a; break; }
if (!$done && $anchor === null) $problems[] = '.htaccess has no place for the guides rules.';
if ($problems) { echo "STOP:\n  - " . implode("\n  - ", $problems) . "\n"; exit(1); }

echo $done ? "  =  .htaccess already has the guides rules\n" : "  +  .htaccess: /guides/ and the guides sitemap go to blog-render.php\n";

$raw = rocLayerSettingGet($pdo, 'site_nav');
$nav = $raw ? json_decode($raw, true) : null;
$navNew = null;
if (is_array($nav) && !empty($nav['header'])) {
    $navNew = $nav; $changed = false;
    $has = false; $at = count($navNew['header']);
    foreach ($navNew['header'] as $i => $it) { $u = rtrim((string)($it['url'] ?? ''), '/'); if ($u === '/guides') $has = true; if ($u === '/blogs') $at = $i + 1; }
    if (!$has) { array_splice($navNew['header'], $at, 0, [['label' => 'GUIDES', 'url' => '/guides/']]); $changed = true; echo "  +  Menu (saved in the CMS): GUIDES after BLOGS in the header\n"; }
    foreach ((array)($navNew['footer']['columns'] ?? []) as $ci => $col) {
        $links = (array)($col['links'] ?? []); $pos = null; $hasF = false;
        foreach ($links as $li => $l) { $u = rtrim((string)($l['url'] ?? ''), '/'); if ($u === '/news' || ($u === '/blogs' && $pos === null)) $pos = $li + 1; if ($u === '/guides') $hasF = true; }
        if ($pos !== null && !$hasF) { array_splice($links, $pos, 0, [['label' => 'Guides', 'url' => '/guides/', 'icon' => 'book']]); $navNew['footer']['columns'][$ci]['links'] = $links; $changed = true; echo "  +  Footer: Guides after News\n"; break; }
    }
    if (!$changed) { $navNew = null; echo "  =  Menu already has GUIDES\n"; }
} else echo "  =  Menu: the built-in one (GUIDES comes with this deploy)\n";

if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }

$file = $HOME . '/roc-guides-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'htaccess' => $done ? '' : $ht, 'nav' => $navNew !== null ? $nav : null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
if (!$done) {
    file_put_contents($ROOT . '/.htaccess.roc-tmp', str_replace($anchor, GD_BLOCK . "\n" . $anchor, $ht));
    rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
    echo ".htaccess updated\n";
}
if ($navNew !== null) { $n = rocLayerSave($pdo, $ROOT, 'nav', $navNew, null); echo "Menu updated ({$n} built page(s) redrawn)\n"; }
echo "Backup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n\nDone. Open https://runonconsole.com/guides/ . Write a guide in CMS > Blogs > New post with the category \"Guides: Buying\" (or How to use, Setup, Game setup).\n";
