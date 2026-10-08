<?php
/**
 * Run On Console — production name for the login cookie (SEO audit A30).
 *
 *   php cli-session-name.php                 dry run: shows what would change
 *   php cli-session-name.php --apply         makes the change, with a backup
 *   php cli-session-name.php --rollback=DIR  undoes an --apply using its backup
 *
 * The API named its session cookie ROCSTAGINGSESSID (left over from staging).
 * --apply:
 *   1. api/v1/config.php: the cookie is called ROCSESSID, and a browser that still
 *      has the old ROCSTAGINGSESSID cookie gets it removed;
 *   2. signs everyone out: all rows in the sessions table are deleted (website
 *      members and CMS users sign in again once).
 * Only that one line of config.php changes; nothing else in it is read or printed.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);
$CONFIG = $ROOT . '/api/v1/config.php';

const OLD_LINE = "session_name('ROCSTAGINGSESSID');";
const NEW_LINE = "session_name('ROCSESSID');\n"
    . "        // The staging-named cookie used before October 2026: remove it from the browser.\n"
    . "        if (isset(\$_COOKIE['ROCSTAGINGSESSID'])) setcookie('ROCSTAGINGSESSID', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);";

// Database connection first, before anything is printed (config.php sends headers).
$pdo = null;
if ($rollback === null && is_file($CONFIG)) {
    ob_start();
    require_once $CONFIG;
    ob_end_clean();
    $pdo = function_exists('getDBConnection') ? @getDBConnection() : null;
}

echo "Run On Console - login cookie name\n\n";

if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    if (!is_file($dir . '/config.php')) exit("No backup found in {$dir}\n");
    copy($dir . '/config.php', $CONFIG);
    echo "Restored api/v1/config.php (sign-ins made since then need to sign in again).\n\nDone.\n";
    exit(0);
}

$src = (string)@file_get_contents($CONFIG);
if ($src === '') exit("STOP: api/v1/config.php not found.\n");
$hasOld = substr_count($src, OLD_LINE);
$done = strpos($src, "session_name('ROCSESSID');") !== false;
if ($done) exit("1) Cookie name: already ROCSESSID.\n\nNothing to do.\n");
if ($hasOld !== 1) exit("STOP: the cookie line was not found as expected in api/v1/config.php. Nothing was changed.\n");
echo "1) Cookie name: ROCSTAGINGSESSID -> ROCSESSID (old cookie removed from browsers)\n";
echo "2) Sign everyone out (delete all rows in the sessions table)\n";
if (!$apply) exit("\nDRY RUN - nothing changed. Run again with --apply.\n");

$backup = $HOME . '/roc-session-backup-' . date('Ymd-His');
mkdir($backup, 0700, true);
copy($CONFIG, $backup . '/config.php');
chmod($backup . '/config.php', 0600);
echo "\nBackup: {$backup}\n";

$new = str_replace(OLD_LINE, NEW_LINE, $src);
$tmp = $CONFIG . '.roc-tmp';
file_put_contents($tmp, $new);
$lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1');
if (strpos((string)$lint, 'No syntax errors') === false) { @unlink($tmp); exit("STOP: the changed file did not pass a syntax check. Nothing was changed.\n"); }
@chmod($tmp, fileperms($CONFIG) & 0777);
rename($tmp, $CONFIG);
echo "api/v1/config.php updated\n";

if (defined('ROC_SESSION_NAME')) echo "note: the settings file sets its own cookie name (ROC_SESSION_NAME = " . ROC_SESSION_NAME . "), which stays in use.\n";
if ($pdo) {
    try { $n = $pdo->exec('DELETE FROM sessions'); echo "Signed everyone out ({$n} session(s) ended)\n"; }
    catch (\Throwable $e) { echo "Could not clear the sessions table: everyone is still signed out, because the old cookie is no longer read.\n"; }
} else {
    echo "No database connection: everyone is still signed out, because the old cookie is no longer read.\n";
}

echo "\nDone. Sign in again on the website and in the CMS.\n";
echo "Undo: php " . __FILE__ . " --rollback={$backup}\n";
