<?php
/**
 * Run On Console — switch on the "Can I run it?" game pages (Deploy 65).
 *
 *   php cli-game-pages.php                  dry run: shows what would change
 *   php cli-game-pages.php --apply          makes the changes, with a backup
 *   php cli-game-pages.php --rollback=FILE  undoes an --apply using its backup
 *
 * What --apply does:
 *   1. .htaccess: /can-i-run/, /can-i-run/{game}/ and /sitemaps/games-sitemap.xml go to game-render.php
 *      (block between "ROC game pages BEGIN/END" markers, before the news and blog rules). The sitemap
 *      index (sitemap-render.php in this deploy) lists games-sitemap.xml by itself.
 *   2. Footer: when the menu is saved in CMS > Menus & footer, "PC Game Requirements A to Z" is added
 *      after the checker link (the built-in footer of this deploy has it already).
 *
 * Files needed first (in the deploy zip): game-render.php, api/v1/game-pages-lib.php, roc-steam-games.json.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const GP_BEGIN = '  # ROC game pages BEGIN (cli-game-pages.php)';
const GP_END   = '  # ROC game pages END';
const GP_BLOCK = GP_BEGIN . "\n"
    . "  # One \"Can I run it?\" page per PC game (game-render.php, data: roc-steam-games.json)\n"
    . "  RewriteRule ^can-i-run$ /can-i-run/ [R=301,L]\n"
    . "  RewriteRule ^can-i-run/([a-z0-9-]+)$ /can-i-run/\$1/ [R=301,L]\n"
    . "  RewriteRule ^can-i-run/$ game-render.php [L,QSA]\n"
    . "  RewriteRule ^can-i-run/([a-z0-9-]+)/$ game-render.php?slug=\$1 [L,QSA]\n"
    . "  RewriteRule ^sitemaps/games-sitemap\\.xml$ game-render.php?sitemap=1 [L]\n"
    . GP_END . "\n";

echo "Run On Console - game pages (/can-i-run/)\npublic_html: {$ROOT}\n\n";

// The database is only needed for the footer link saved in the CMS.
$pdo = null;
try {
    ob_start();
    require_once $ROOT . '/api/v1/config.php';
    ob_end_clean();
    $pdo = function_exists('getDBConnection') ? getDBConnection() : null;
} catch (\Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
}
if ($pdo) require_once $ROOT . '/api/v1/cms/site-layer-lib.php';

/* -------------------------------------------------------------- rollback */
if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b)) exit("No backup found in {$rollback}\n");
    if (is_string($b['htaccess'] ?? null) && $b['htaccess'] !== '') { file_put_contents($ROOT . '/.htaccess', $b['htaccess']); echo "Restored .htaccess\n"; }
    if (!empty($b['nav']) && is_array($b['nav']) && $pdo) { $n = rocLayerSave($pdo, $ROOT, 'nav', $b['nav'], null); echo "Restored the saved menu ({$n} built page(s) redrawn)\n"; }
    exit("Done.\n");
}

/* ----------------------------------------------------------------- checks */
$problems = [];
foreach (['game-render.php', 'api/v1/game-pages-lib.php', 'roc-steam-games.json', 'blog-render.php', '.htaccess'] as $f)
    if (!is_file($ROOT . '/' . $f)) $problems[] = "public_html/{$f} is missing. Unzip the deploy again.";
$ht = is_file($ROOT . '/.htaccess') ? (string)file_get_contents($ROOT . '/.htaccess') : '';
$done = strpos($ht, GP_BEGIN) !== false;
$anchor = null;
foreach (['  # ROC news BEGIN', '  # Blogs come from the CMS database', '  RewriteRule ^blogs$ /blogs/', '  # Real file or folder'] as $a)
    if (strpos($ht, $a) !== false) { $anchor = $a; break; }
if (!$done && $anchor === null) $problems[] = '.htaccess has no place for the game rules (expected the blog rules).';
if ($problems) { echo "STOP:\n  - " . implode("\n  - ", $problems) . "\n"; exit(1); }

/* ---------------------------------------------------------------- the plan */
require_once $ROOT . '/api/v1/game-pages-lib.php';
echo '  i  ' . count(rocGpAll($ROOT)) . " game pages at /can-i-run/{game}/\n";
echo $done ? "  =  .htaccess already has the game rules\n" : "  +  .htaccess: /can-i-run/ and the games sitemap go to game-render.php\n";

$nav = null; $navNew = null;
if ($pdo) { $raw = rocLayerSettingGet($pdo, 'site_nav'); $nav = $raw ? json_decode($raw, true) : null; }
if (is_array($nav) && !empty($nav['footer']['columns'])) {
    $has = false; $at = null;
    foreach ($nav['footer']['columns'] as $ci => $col)
        foreach ((array)($col['links'] ?? []) as $li => $l) {
            $u = rtrim((string)($l['url'] ?? ''), '/');
            if ($u === '/can-i-run') $has = true;
            if ($u === '/compatibility' && $at === null) $at = [$ci, $li + 1];
        }
    if (!$has && $at !== null) {
        $navNew = $nav;
        array_splice($navNew['footer']['columns'][$at[0]]['links'], $at[1], 0, [['label' => 'PC Game Requirements A to Z', 'url' => '/can-i-run/', 'icon' => 'monitor']]);
        echo "  +  Footer (menu saved in the CMS): \"PC Game Requirements A to Z\" after the checker link\n";
    } elseif ($has) echo "  =  Footer already links /can-i-run/\n";
    else echo "  =  Footer: the saved menu has no checker link, nothing added (CMS > Menus & footer)\n";
} else echo "  =  Footer: the built-in one (the link comes with this deploy)\n";

if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }

/* ------------------------------------------------------------------- apply */
$file = $HOME . '/roc-game-pages-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'htaccess' => $done ? '' : $ht, 'nav' => $navNew !== null ? $nav : null],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
if (!$done) {
    file_put_contents($ROOT . '/.htaccess.roc-tmp', str_replace($anchor, GP_BLOCK . "\n" . $anchor, $ht));
    rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
    echo ".htaccess updated\n";
}
if ($navNew !== null) { $n = rocLayerSave($pdo, $ROOT, 'nav', $navNew, null); echo "Footer link added ({$n} built page(s) redrawn)\n"; }
echo "Backup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
echo "\nDone. Open https://runonconsole.com/can-i-run/ and add https://runonconsole.com/sitemaps/games-sitemap.xml in Google Search Console.\n";
