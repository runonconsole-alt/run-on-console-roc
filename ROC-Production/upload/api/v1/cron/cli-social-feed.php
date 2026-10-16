<?php
/**
 * Run On Console — switch on the RSS feeds for social media tools (Deploy 74).
 *
 *   php cli-social-feed.php                  dry run
 *   php cli-social-feed.php --apply          adds the rules to .htaccess (backup kept)
 *   php cli-social-feed.php --rollback=FILE  puts .htaccess back
 *
 * /feed.xml (all), /news/feed.xml, /blogs/feed.xml and /guides/feed.xml go to blog-render.php?feed=...
 * Give one of them to Publer or Buffer: every new post is shared on the connected accounts by itself.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const SF_BEGIN = '  # ROC feeds BEGIN (cli-social-feed.php)';
const SF_BLOCK = SF_BEGIN . "\n"
    . "  # RSS feeds of the newest posts, for social media tools (blog-render.php)\n"
    . "  RewriteRule ^feed\\.xml$ blog-render.php?feed=all [L]\n"
    . "  RewriteRule ^(news|blogs|guides)/feed\\.xml$ blog-render.php?feed=\$1 [L]\n"
    . "  # ROC feeds END\n";

echo "Run On Console - RSS feeds (/feed.xml)\npublic_html: {$ROOT}\n\n";
if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b) || !is_string($b['htaccess'] ?? null) || $b['htaccess'] === '') exit("No backup found in {$rollback}\n");
    file_put_contents($ROOT . '/.htaccess', $b['htaccess']);
    exit("Restored .htaccess\n");
}
if (!is_file($ROOT . '/blog-render.php') || strpos((string)file_get_contents($ROOT . '/blog-render.php'), 'function rocFeed') === false) exit("STOP: blog-render.php is the old version. Unzip the deploy again.\n");
$ht = (string)@file_get_contents($ROOT . '/.htaccess');
if (strpos($ht, SF_BEGIN) !== false) exit("  =  .htaccess already has the feed rules. Nothing to do.\n");
$anchor = null;
foreach (['  # ROC updates BEGIN', '  # ROC guides BEGIN', '  # ROC news BEGIN', '  # Blogs come from the CMS database', '  # Real file or folder'] as $a) if (strpos($ht, $a) !== false) { $anchor = $a; break; }
if ($anchor === null) exit("STOP: .htaccess has no place for the feed rules.\n");
echo "  +  .htaccess: /feed.xml, /news/feed.xml, /blogs/feed.xml, /guides/feed.xml\n";
if (!$apply) exit("\nDry run: nothing changed. Run again with --apply.\n");
$file = $HOME . '/roc-feeds-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'htaccess' => $ht]));
file_put_contents($ROOT . '/.htaccess.roc-tmp', str_replace($anchor, SF_BLOCK . "\n" . $anchor, $ht));
rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
echo ".htaccess updated\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n\nDone. Open https://runonconsole.com/feed.xml\n";
