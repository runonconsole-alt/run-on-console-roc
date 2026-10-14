<?php
/**
 * Run On Console — page addresses /news/page/2/ and /blogs/page/2/ (Deploy 53).
 *
 *   php cli-news-pages.php                  dry run: shows what would change
 *   php cli-news-pages.php --apply          adds the rules to .htaccess (with a backup)
 *   php cli-news-pages.php --rollback=FILE  puts the old .htaccess back
 *
 * blog-render.php (Deploy 53) links the pages as /news/page/2/ and sends old /news/?page=2 links there
 * (301), so run this right after unzipping.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const PAGES_BEGIN = '  # ROC pages BEGIN (cli-news-pages.php)';
const PAGES_END   = '  # ROC pages END';
const PAGES_BLOCK = PAGES_BEGIN . "\n"
    . "  # News and blog list pages: /news/page/2/, /blogs/page/2/ (blog-render.php)\n"
    . "  RewriteRule ^(news|blogs)/page/([0-9]+)$ /\$1/page/\$2/ [R=301,L]\n"
    . "  RewriteRule ^news/page/([0-9]+)/$ blog-render.php?news=1&page=\$1 [L,QSA]\n"
    . "  RewriteRule ^blogs/page/([0-9]+)/$ blog-render.php?page=\$1 [L,QSA]\n"
    . PAGES_END . "\n";

echo "Run On Console - news and blog page addresses\n\n";

if ($rollback !== null) {
    $old = (string)@file_get_contents($rollback);
    if ($old === '' || stripos($old, 'RewriteEngine') === false) exit("No .htaccess backup in {$rollback}\n");
    file_put_contents($ROOT . '/.htaccess', $old);
    exit("Restored .htaccess\n");
}

$f = $ROOT . '/.htaccess';
$ht = is_file($f) ? (string)file_get_contents($f) : '';
if ($ht === '') exit("STOP: public_html/.htaccess is missing.\n");
if (strpos((string)@file_get_contents($ROOT . '/blog-render.php'), 'rocPageUrl') === false) exit("STOP: blog-render.php is the old version. Unzip the deploy first.\n");
if (strpos($ht, PAGES_BEGIN) !== false) { echo "  =  .htaccess already has the page rules\nNothing to do.\n"; exit(0); }
$anchor = null;
foreach (['  # ROC news BEGIN', '  # Blogs come from the CMS database', '  RewriteRule ^blogs$ /blogs/'] as $a) if (strpos($ht, $a) !== false) { $anchor = $a; break; }
if ($anchor === null) exit("STOP: .htaccess has no news or blog rules to put the page rules before.\n");
echo "  +  .htaccess: /news/page/2/ and /blogs/page/2/ go to blog-render.php\n";
echo "  +  old /news/?page=2 links move to /news/page/2/ (301, done by blog-render.php)\n";
if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }

$bak = $HOME . '/roc-htaccess-pages-' . date('Ymd-His') . '.bak';
file_put_contents($bak, $ht);
file_put_contents($f . '.roc-tmp', str_replace($anchor, PAGES_BLOCK . "\n" . $anchor, $ht));
rename($f . '.roc-tmp', $f);
echo "\n.htaccess updated.\nBackup: {$bak}\nRollback: php " . __FILE__ . " --rollback={$bak}\n"
   . "Check: https://runonconsole.com/news/page/2/\n";
