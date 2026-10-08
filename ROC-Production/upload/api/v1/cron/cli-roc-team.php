<?php
/**
 * Run On Console — the author page becomes the ROC Team page.
 *
 *   php cli-roc-team.php                 dry run: shows what would change
 *   php cli-roc-team.php --apply         makes the changes, with a backup
 *   php cli-roc-team.php --rollback=DIR  undoes an --apply using its backup
 *
 * --apply:
 *   1. .htaccess: /author/omar-abobakar/ redirects (301) to /author/roc-team/
 *   2. the old built folder public_html/author/omar-abobakar/ is moved into the backup
 *      (the new page /author/roc-team/ comes with the deploy zip)
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const TEAM_BEGIN = '  # ROC team BEGIN (cli-roc-team.php)';
const TEAM_BLOCK = TEAM_BEGIN . "\n"
    . '  RewriteRule ^author/omar-abobakar/?$ /author/roc-team/ [R=301,L]' . "\n"
    . "  # ROC team END\n";
$OLD = $ROOT . '/author/omar-abobakar';

echo "Run On Console - ROC Team author page\npublic_html: {$ROOT}\n\n";

if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    if (!is_file($dir . '/files/.htaccess')) exit("No backup found in {$dir}\n");
    copy($dir . '/files/.htaccess', $ROOT . '/.htaccess');
    echo "Restored .htaccess\n";
    if (is_dir($dir . '/omar-abobakar') && !is_dir($OLD)) { rename($dir . '/omar-abobakar', $OLD); echo "Restored author/omar-abobakar/\n"; }
    echo "\nDone.\n";
    exit(0);
}

$ht = (string)@file_get_contents($ROOT . '/.htaccess');
$anchor = null;
foreach (['  # Sitemaps are generated automatically', '  # Real file or folder'] as $a) if (strpos($ht, $a) !== false) { $anchor = $a; break; }
$needRule = strpos($ht, TEAM_BEGIN) === false;
$needMove = is_dir($OLD);
echo '1) /author/omar-abobakar/ -> /author/roc-team/ (301): ' . ($needRule ? ($anchor ? "add\n" : "STOP - no place to add it\n") : "already there\n");
echo '2) Old folder author/omar-abobakar/: ' . ($needMove ? "move into the backup\n" : "already gone\n");
if (!is_file($ROOT . '/author/roc-team/index.html')) echo "   note: author/roc-team/index.html is missing - unzip the deploy first.\n";
if ($needRule && !$anchor) exit("\nSTOP: nothing was changed.\n");
if (!$needRule && !$needMove) exit("\nNothing to do.\n");
if (!$apply) exit("\nDRY RUN - nothing changed. Run again with --apply.\n");

$backup = $HOME . '/roc-team-backup-' . date('Ymd-His');
mkdir($backup . '/files', 0700, true);
copy($ROOT . '/.htaccess', $backup . '/files/.htaccess');
echo "\nBackup: {$backup}\n";
if ($needRule) {
    file_put_contents($ROOT . '/.htaccess.roc-tmp', str_replace($anchor, TEAM_BLOCK . $anchor, $ht));
    rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
    echo ".htaccess updated\n";
}
if ($needMove) { rename($OLD, $backup . '/omar-abobakar'); echo "Moved author/omar-abobakar/ into the backup\n"; }

echo "\nDone. Check: curl -sI https://runonconsole.com/author/omar-abobakar/ | grep -i location\n";
echo "Undo: php " . __FILE__ . " --rollback={$backup}\n";
