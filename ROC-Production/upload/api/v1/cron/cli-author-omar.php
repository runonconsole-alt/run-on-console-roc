<?php
/**
 * Run On Console — the author page becomes Omar Abobakar's page again (Deploy 149).
 *
 *   php cli-author-omar.php                 dry run: shows what would change
 *   php cli-author-omar.php --apply         makes the changes, with a backup
 *   php cli-author-omar.php --rollback=DIR  undoes an --apply using its backup
 *
 * --apply:
 *   1. .htaccess: the old rule of cli-roc-team.php (/author/omar-abobakar/ -> /author/roc-team/) is removed
 *      and /author/roc-team/ redirects (301) to /author/omar-abobakar/
 *   2. the old built folder public_html/author/roc-team/ is moved into the backup
 *      (the new page /author/omar-abobakar/ comes with the deploy zip)
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const OLD_RE = '/  # ROC team BEGIN \(cli-roc-team\.php\)\n.*?  # ROC team END\n/s';
const OMAR_BEGIN = '  # Author page BEGIN (cli-author-omar.php)';
const OMAR_BLOCK = OMAR_BEGIN . "\n"
    . '  RewriteRule ^author/roc-team/?$ /author/omar-abobakar/ [R=301,L]' . "\n"
    . "  # Author page END\n";
$OLD = $ROOT . '/author/roc-team';

echo "Run On Console - author page /author/omar-abobakar/\npublic_html: {$ROOT}\n\n";

if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    if (!is_file($dir . '/files/.htaccess')) exit("No backup found in {$dir}\n");
    copy($dir . '/files/.htaccess', $ROOT . '/.htaccess');
    echo "Restored .htaccess\n";
    if (is_dir($dir . '/roc-team') && !is_dir($OLD)) { rename($dir . '/roc-team', $OLD); echo "Restored author/roc-team/\n"; }
    echo "\nDone.\n";
    exit(0);
}

$ht = (string)@file_get_contents($ROOT . '/.htaccess');
$eol = strpos($ht, "\r\n") !== false ? "\r\n" : "\n";
$ht = str_replace("\r\n", "\n", $ht);
$anchor = null;
foreach (['  # Sitemaps are generated automatically', '  # Real file or folder'] as $a) if (strpos($ht, $a) !== false) { $anchor = $a; break; }
$hasOld = (bool)preg_match(OLD_RE, $ht);
$needRule = strpos($ht, OMAR_BEGIN) === false;
$needMove = is_dir($OLD);
echo '1) Old rule /author/omar-abobakar/ -> /author/roc-team/: ' . ($hasOld ? "remove\n" : "not there\n");
echo '2) /author/roc-team/ -> /author/omar-abobakar/ (301): ' . ($needRule ? ($anchor ? "add\n" : "STOP - no place to add it\n") : "already there\n");
echo '3) Old folder author/roc-team/: ' . ($needMove ? "move into the backup\n" : "already gone\n");
if (!is_file($ROOT . '/author/omar-abobakar/index.html')) echo "   note: author/omar-abobakar/index.html is missing - unzip the deploy first.\n";
if ($needRule && !$anchor) exit("\nSTOP: nothing was changed.\n");
if (!$hasOld && !$needRule && !$needMove) exit("\nNothing to do.\n");
if (!$apply) exit("\nDRY RUN - nothing changed. Run again with --apply.\n");

$backup = $HOME . '/author-omar-backup-' . date('Ymd-His');
mkdir($backup . '/files', 0700, true);
copy($ROOT . '/.htaccess', $backup . '/files/.htaccess');
echo "\nBackup: {$backup}\n";
if ($hasOld || $needRule) {
    if ($hasOld) $ht = (string)preg_replace(OLD_RE, '', $ht);
    if ($needRule) $ht = str_replace($anchor, OMAR_BLOCK . $anchor, $ht);
    file_put_contents($ROOT . '/.htaccess.roc-tmp', str_replace("\n", $eol, $ht));
    rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
    echo ".htaccess updated\n";
}
if ($needMove) { rename($OLD, $backup . '/roc-team'); echo "Moved author/roc-team/ into the backup\n"; }

echo "\nDone. Check: curl -sI https://runonconsole.com/author/roc-team/ | grep -i location\n";
echo "Undo: php " . __FILE__ . " --rollback={$backup}\n";
