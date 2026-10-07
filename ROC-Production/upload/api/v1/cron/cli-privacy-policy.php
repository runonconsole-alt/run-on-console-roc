<?php
/**
 * Run On Console — one Privacy Policy page.
 *
 *   php cli-privacy-policy.php                 dry run: shows what would change
 *   php cli-privacy-policy.php --apply         makes the changes, with a backup
 *   php cli-privacy-policy.php --rollback=DIR  undoes an --apply using its backup
 *
 * The old "Affiliate Disclosure" page (/policy/) is now part of /privacy-policy/.
 * What --apply does:
 *   1. .htaccess: /policy/ and /affiliate-disclosure/ redirect (301) to /privacy-policy/
 *      ("ROC redirects BEGIN/END" markers), so old links and Google keep working.
 *   2. Moves the old public_html/policy/ folder into the backup, so the pages
 *      sitemap stops listing /policy/.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const MARK_BEGIN = '  # ROC redirects BEGIN (cli-privacy-policy.php)';
const MARK_END   = '  # ROC redirects END';
const HTACCESS_BLOCK = MARK_BEGIN . "\n"
    . "  # The affiliate disclosure is part of the Privacy Policy page\n"
    . "  RewriteRule ^policy(/.*)?$ /privacy-policy/ [R=301,L,NC]\n"
    . "  RewriteRule ^affiliate-disclosure(/.*)?$ /privacy-policy/ [R=301,L,NC]\n"
    . MARK_END . "\n";

echo "Run On Console - one Privacy Policy page\n";
echo "public_html: {$ROOT}\n\n";

/* -------------------------------------------------------------- rollback */
if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    $man = is_file($dir . '/MANIFEST.json') ? json_decode((string)file_get_contents($dir . '/MANIFEST.json'), true) : null;
    if (!$man) exit("Backup folder not found or has no MANIFEST.json: {$dir}\n");
    if (!empty($man['htaccess']) && is_file($dir . '/files/.htaccess')) { copy($dir . '/files/.htaccess', $ROOT . '/.htaccess'); echo "Restored .htaccess\n"; }
    if (!empty($man['policy_moved']) && is_dir($dir . '/files/policy') && !file_exists($ROOT . '/policy')) {
        rename($dir . '/files/policy', $ROOT . '/policy'); echo "Restored policy/\n";
    }
    echo "\nDone.\n";
    exit(0);
}

/* ---------------------------------------------------------------- checks */
$ht = (string)@file_get_contents($ROOT . '/.htaccess');
$htDone = strpos($ht, MARK_BEGIN) !== false;
$anchor = null;
foreach (['  # Sitemaps are generated automatically', '  # Real file or folder'] as $a) if (strpos($ht, $a) !== false) { $anchor = $a; break; }
$hasPolicy = is_dir($ROOT . '/policy');
$hasPrivacy = is_file($ROOT . '/privacy-policy/index.html');

echo '1) .htaccess: ' . ($htDone ? "redirects already present\n" : ($anchor ? "add /policy/ -> /privacy-policy/ before \"" . trim($anchor) . "\"\n" : "STOP - no place to add the redirect\n"));
echo '2) policy/ folder: ' . ($hasPolicy ? "move into the backup\n" : "already gone\n");
echo '   privacy-policy/index.html: ' . ($hasPrivacy ? "present\n" : "MISSING - upload the new website files first\n");
if ((!$htDone && !$anchor) || !$hasPrivacy) exit("\nSTOP: fix the problem above first. Nothing was changed.\n");
if ($htDone && !$hasPolicy) exit("\nNothing to do.\n");
if (!$apply) exit("\nDRY RUN - nothing changed. Run again with --apply.\n");

/* ----------------------------------------------------------------- apply */
$backup = $HOME . '/roc-privacy-backup-' . date('Ymd-His');
mkdir($backup . '/files', 0700, true);
$man = ['created' => date('c'), 'htaccess' => false, 'policy_moved' => false];
$save = function () use ($backup, &$man) { file_put_contents($backup . '/MANIFEST.json', json_encode($man, JSON_PRETTY_PRINT)); };
copy($ROOT . '/.htaccess', $backup . '/files/.htaccess');
$save();
echo "\nBackup: {$backup}\n";

if (!$htDone) {
    file_put_contents($ROOT . '/.htaccess.roc-tmp', str_replace($anchor, HTACCESS_BLOCK . $anchor, $ht));
    rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
    $man['htaccess'] = true; $save();
    echo ".htaccess updated\n";
}
if ($hasPolicy) {
    rename($ROOT . '/policy', $backup . '/files/policy');
    $man['policy_moved'] = true; $save();
    echo "policy/ moved to the backup\n";
}
echo "\nDone. Check: https://runonconsole.com/policy/ should open the Privacy Policy.\n";
echo "Undo: php " . __FILE__ . " --rollback={$backup}\n";
