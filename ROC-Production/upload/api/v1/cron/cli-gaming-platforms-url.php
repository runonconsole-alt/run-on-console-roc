<?php
/**
 * Run On Console — gaming platform pages move from /categories/ to /gaming-platforms/.
 *
 *   php cli-gaming-platforms-url.php                 dry run: shows what would change
 *   php cli-gaming-platforms-url.php --apply         makes the change, with a backup
 *   php cli-gaming-platforms-url.php --rollback=DIR  undoes an --apply using its backup
 *
 * .htaccess ("ROC gaming-platforms BEGIN/END", placed before the older platform rules):
 *   /gaming-platforms/                    -> category-render.php
 *   /gaming-platforms/{platform}/         -> category-render.php?slug=...
 *   /gaming-platforms/{platform}/{guide}/ -> page-render.php (CMS guides)
 *   /categories/...                       -> 301 to the same address under /gaming-platforms/
 *   /partnerships/                        -> 301 to /write-for-us/ (one page for writing and advertising)
 * Upload category-render.php, page-render.php and sitemap-render.php from the same deploy first.
 * The /categories/ folders stay on the server: they are the layout templates.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const BEGIN = '  # ROC gaming-platforms BEGIN (cli-gaming-platforms-url.php)';
const BLOCK = BEGIN . "\n"
    . "  # Gaming platforms live at /gaming-platforms/; the old /categories/ addresses redirect.\n"
    . '  RewriteRule ^categories/?$ /gaming-platforms/ [R=301,L]' . "\n"
    . '  RewriteRule ^categories/(.+)$ /gaming-platforms/$1 [R=301,L]' . "\n"
    . '  RewriteRule ^gaming-platforms$ /gaming-platforms/ [R=301,L]' . "\n"
    . '  RewriteRule ^gaming-platforms/$ category-render.php [L,QSA]' . "\n"
    . '  RewriteRule ^gaming-platforms/([a-z0-9-]+)$ /gaming-platforms/$1/ [R=301,L]' . "\n"
    . '  RewriteRule ^gaming-platforms/([a-z0-9-]+)/$ category-render.php?slug=$1 [L,QSA]' . "\n"
    . '  RewriteRule ^gaming-platforms/([a-z0-9-]+)/([a-z0-9]+(?:-[a-z0-9]+)*)$ /gaming-platforms/$1/$2/ [R=301,L]' . "\n"
    . '  RewriteRule ^gaming-platforms/([a-z0-9-]+)/([a-z0-9]+(?:-[a-z0-9]+)*)/$ page-render.php?cat=$1&slug=$2 [L,QSA]' . "\n"
    . "  # Advertising & partnerships is part of Write for us.\n"
    . '  RewriteRule ^partnerships/?$ /write-for-us/ [R=301,L]' . "\n"
    . "  # ROC gaming-platforms END\n";

echo "Run On Console - /gaming-platforms/ addresses\npublic_html: {$ROOT}\n\n";

if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    if (!is_file($dir . '/files/.htaccess')) exit("Backup not found: {$dir}\n");
    copy($dir . '/files/.htaccess', $ROOT . '/.htaccess');
    exit("Restored .htaccess\nDone.\n");
}

$ht = (string)@file_get_contents($ROOT . '/.htaccess');
if (strpos($ht, BEGIN) !== false) exit("Already installed. Nothing to do.\n");
$anchor = null;
foreach (['  # ROC platforms BEGIN', '  # Sitemaps are generated automatically', '  # Real file or folder'] as $a) {
    if (strpos($ht, $a) !== false) { $anchor = $a; break; }
}
$problems = [];
if (!$anchor) $problems[] = '.htaccess has no place for the rules.';
foreach (['category-render.php', 'page-render.php'] as $f) {
    $code = (string)@file_get_contents($ROOT . '/' . $f);
    if (strpos($code, '/gaming-platforms/') === false) $problems[] = "{$f} is the old version: upload the new one first.";
}
echo ".htaccess: add the /gaming-platforms/ rules before \"" . trim((string)$anchor) . "\"\n";
if ($problems) exit("\nSTOP:\n  - " . implode("\n  - ", $problems) . "\nNothing was changed.\n");
if (!$apply) exit("\nDRY RUN - nothing changed. Run again with --apply.\n");

$backup = $HOME . '/roc-platforms-url-backup-' . date('Ymd-His');
mkdir($backup . '/files', 0700, true);
copy($ROOT . '/.htaccess', $backup . '/files/.htaccess');
file_put_contents($ROOT . '/.htaccess.roc-tmp', str_replace($anchor, BLOCK . $anchor, $ht));
rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
echo "\nBackup: {$backup}\n.htaccess updated\n";
echo "\nDone. Check: https://runonconsole.com/categories/ should open https://runonconsole.com/gaming-platforms/\n";
echo "Undo: php " . __FILE__ . " --rollback={$backup}\n";
