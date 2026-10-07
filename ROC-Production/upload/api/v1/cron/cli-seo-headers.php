<?php
/**
 * Run On Console — SEO headers and small redirects (QA report, October 2026).
 *
 *   php cli-seo-headers.php                 dry run: shows what would change
 *   php cli-seo-headers.php --apply         makes the changes, with a backup
 *   php cli-seo-headers.php --rollback=DIR  undoes an --apply using its backup
 *
 * What --apply does (.htaccess only):
 *   1. /llms.txt/ (with a slash) redirects to /llms.txt.
 *   2. Public built pages (index.html) send "X-Robots-Tag: index, follow", the same
 *      rule as their robots meta tag. Private routes keep the existing
 *      "noindex, nofollow" header (PRIVATE_ROUTE). Pages rendered by PHP (blogs,
 *      products, platforms) send the header themselves.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const REWRITE_BEGIN = '  # ROC seo BEGIN (cli-seo-headers.php)';
const REWRITE_BLOCK = REWRITE_BEGIN . "\n"
    . '  RewriteRule ^llms\.txt/$ /llms.txt [R=301,L]' . "\n"
    . "  # ROC seo END\n";
const HEADER_BEGIN = '# ROC headers BEGIN (cli-seo-headers.php)';
const HEADER_BLOCK = "\n" . HEADER_BEGIN . "\n"
    . "<IfModule mod_headers.c>\n"
    . '  <FilesMatch "^index\.html$">' . "\n"
    . '    Header set X-Robots-Tag "index, follow" env=!PRIVATE_ROUTE' . "\n"
    . "  </FilesMatch>\n"
    . "</IfModule>\n"
    . "# ROC headers END\n";

echo "Run On Console - SEO headers\npublic_html: {$ROOT}\n\n";

/* -------------------------------------------------------------- rollback */
if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    $man = is_file($dir . '/MANIFEST.json') ? json_decode((string)file_get_contents($dir . '/MANIFEST.json'), true) : null;
    if (!$man) exit("Backup folder not found or has no MANIFEST.json: {$dir}\n");
    if (!empty($man['htaccess'])) { copy($dir . '/files/.htaccess', $ROOT . '/.htaccess'); echo "Restored .htaccess\n"; }
    echo "\nDone.\n";
    exit(0);
}

/* ---------------------------------------------------------------- checks */
$ht = (string)@file_get_contents($ROOT . '/.htaccess');
$anchor = null;
foreach (['  # Sitemaps are generated automatically', '  # Real file or folder'] as $a) if (strpos($ht, $a) !== false) { $anchor = $a; break; }
$needRewrite = strpos($ht, REWRITE_BEGIN) === false;
$needHeader = strpos($ht, HEADER_BEGIN) === false;

echo '1) /llms.txt/ redirect: ' . ($needRewrite ? ($anchor ? "add\n" : "STOP - no place to add it\n") : "already there\n");
echo '2) X-Robots-Tag on public built pages: ' . ($needHeader ? "add\n" : "already there\n");
if ($needRewrite && !$anchor) exit("\nSTOP: nothing was changed.\n");
if (!$needRewrite && !$needHeader) exit("\nNothing to do.\n");
if (!$apply) exit("\nDRY RUN - nothing changed. Run again with --apply.\n");

/* ----------------------------------------------------------------- apply */
$backup = $HOME . '/roc-seo-backup-' . date('Ymd-His');
mkdir($backup . '/files', 0700, true);
copy($ROOT . '/.htaccess', $backup . '/files/.htaccess');
file_put_contents($backup . '/MANIFEST.json', json_encode(['created' => date('c'), 'htaccess' => true], JSON_PRETTY_PRINT));
echo "\nBackup: {$backup}\n";

$new = $ht;
if ($needRewrite) $new = str_replace($anchor, REWRITE_BLOCK . $anchor, $new);
if ($needHeader) $new = rtrim($new, "\n") . "\n" . HEADER_BLOCK;
file_put_contents($ROOT . '/.htaccess.roc-tmp', $new);
rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
echo ".htaccess updated\n";

echo "\nDone. Check that https://runonconsole.com/ still opens, then: curl -sI https://runonconsole.com/ | grep -i robots\n";
echo "Undo: php " . __FILE__ . " --rollback={$backup}\n";
