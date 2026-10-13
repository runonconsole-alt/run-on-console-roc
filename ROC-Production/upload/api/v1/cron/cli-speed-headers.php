<?php
/**
 * Run On Console — browser caching and security headers (SEO audit A09, A11).
 *
 *   php cli-speed-headers.php                 dry run: shows what would change
 *   php cli-speed-headers.php --apply         makes the changes, with a backup
 *   php cli-speed-headers.php --rollback=DIR  undoes an --apply using its backup
 *
 * What --apply adds to the end of public_html/.htaccess:
 *   1. /assets/index-*.js and *.css (built files whose names change on every build)
 *      are cached by browsers for a year: "public, max-age=31536000, immutable".
 *   2. Security headers on every response:
 *        Strict-Transport-Security: max-age=31536000   (HTTPS only, this host only)
 *        Referrer-Policy: strict-origin-when-cross-origin
 *        Permissions-Policy: camera, microphone, geolocation, payment, usb off
 *   Images keep their current 7-day cache (the CMS can replace an image in place).
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const SPEED_BEGIN = '# ROC speed BEGIN (cli-speed-headers.php)';
const SPEED_BLOCK = "\n" . SPEED_BEGIN . "\n"
    . "<IfModule mod_headers.c>\n"
    . '  <FilesMatch "^index-[A-Za-z0-9_-]{6,}\.(js|css)$">' . "\n"
    . '    Header set Cache-Control "public, max-age=31536000, immutable"' . "\n"
    . "  </FilesMatch>\n"
    . '  Header always set Strict-Transport-Security "max-age=31536000"' . "\n"
    . '  Header always set Referrer-Policy "strict-origin-when-cross-origin"' . "\n"
    . '  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=()"' . "\n"
    . "</IfModule>\n"
    . "# ROC speed END\n";

echo "Run On Console - caching and security headers\npublic_html: {$ROOT}\n\n";

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
if ($ht === '') exit("STOP: {$ROOT}/.htaccess not found. Nothing was changed.\n");
$need = strpos($ht, SPEED_BEGIN) === false;
echo '1) Year-long cache for /assets/index-*.js and *.css: ' . ($need ? "add\n" : "already there\n");
echo '2) HSTS, Referrer-Policy, Permissions-Policy: ' . ($need ? "add\n" : "already there\n");
foreach (['Strict-Transport-Security', 'Referrer-Policy', 'Permissions-Policy'] as $h) {
    if ($need && stripos($ht, $h) !== false) echo "   note: .htaccess already mentions {$h}; the new line replaces its value\n";
}
if (!$need) exit("\nNothing to do.\n");
if (!$apply) exit("\nDRY RUN - nothing changed. Run again with --apply.\n");

/* ----------------------------------------------------------------- apply */
$backup = $HOME . '/roc-speed-backup-' . date('Ymd-His');
mkdir($backup . '/files', 0700, true);
copy($ROOT . '/.htaccess', $backup . '/files/.htaccess');
file_put_contents($backup . '/MANIFEST.json', json_encode(['created' => date('c'), 'htaccess' => true], JSON_PRETTY_PRINT));
echo "\nBackup: {$backup}\n";

file_put_contents($ROOT . '/.htaccess.roc-tmp', rtrim($ht, "\n") . "\n" . SPEED_BLOCK);
rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
echo ".htaccess updated\n";

echo "\nDone. Check that https://runonconsole.com/ still opens, then:\n";
echo "  curl -sI https://runonconsole.com/ | grep -iE 'strict|referrer|permissions'\n";
echo "Undo: php " . __FILE__ . " --rollback={$backup}\n";
