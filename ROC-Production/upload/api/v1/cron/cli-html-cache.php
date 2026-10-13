<?php
/**
 * Run On Console — browsers check built pages for changes on every visit.
 *
 *   php cli-html-cache.php                 dry run: shows what would change
 *   php cli-html-cache.php --apply         makes the change, with a backup
 *   php cli-html-cache.php --rollback=DIR  undoes an --apply using its backup
 *
 * Built pages (home, about, contact, …) were sent without a Cache-Control header, so
 * browsers kept showing an old copy (a new announcement bar only appeared after
 * Ctrl+Shift+R). --apply adds to the end of public_html/.htaccess:
 *   .html files: Cache-Control "no-cache"  (the browser asks the server each time; an
 *   unchanged page is answered with a tiny "not modified", so the site stays fast)
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const HC_BEGIN = '# ROC html-cache BEGIN (cli-html-cache.php)';
const HC_BLOCK = "\n" . HC_BEGIN . "\n"
    . "<IfModule mod_headers.c>\n"
    . '  <FilesMatch "\.html$">' . "\n"
    . '    Header set Cache-Control "no-cache"' . "\n"
    . "  </FilesMatch>\n"
    . "</IfModule>\n"
    . "# ROC html-cache END\n";

echo "Run On Console - fresh built pages\npublic_html: {$ROOT}\n\n";

if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    if (!is_file($dir . '/files/.htaccess')) exit("No backup found in {$dir}\n");
    copy($dir . '/files/.htaccess', $ROOT . '/.htaccess');
    echo "Restored .htaccess\n\nDone.\n";
    exit(0);
}

$ht = (string)@file_get_contents($ROOT . '/.htaccess');
if ($ht === '') exit("STOP: {$ROOT}/.htaccess not found. Nothing was changed.\n");
$need = strpos($ht, HC_BEGIN) === false;
echo '1) Built pages (.html): Cache-Control "no-cache": ' . ($need ? "add\n" : "already there\n");
if (!$need) exit("\nNothing to do.\n");
if (!$apply) exit("\nDRY RUN - nothing changed. Run again with --apply.\n");

$backup = $HOME . '/roc-html-cache-backup-' . date('Ymd-His');
mkdir($backup . '/files', 0700, true);
copy($ROOT . '/.htaccess', $backup . '/files/.htaccess');
echo "\nBackup: {$backup}\n";

file_put_contents($ROOT . '/.htaccess.roc-tmp', rtrim($ht, "\n") . "\n" . HC_BLOCK);
rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
echo ".htaccess updated\n";

echo "\nDone. Check: curl -sI https://runonconsole.com/ | grep -i cache-control\n";
echo "Undo: php " . __FILE__ . " --rollback={$backup}\n";
