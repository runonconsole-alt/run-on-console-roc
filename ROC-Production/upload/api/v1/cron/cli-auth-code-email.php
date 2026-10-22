<?php
/**
 * Run On Console — sign-up emails carry a 6-digit code instead of a link.
 *
 *   php cli-auth-code-email.php                 dry run: shows what would change
 *   php cli-auth-code-email.php --apply         makes the change, with a backup
 *   php cli-auth-code-email.php --rollback=DIR  undoes an --apply using its backup
 *
 * --apply:
 *   1. api/v1/account-runtime.php: rocIssueChallenge() sends the code email from
 *      api/v1/auth-code-email.php for sign-up verification (one line added before its
 *      return). Password reset emails are not touched.
 *   2. api/v1/auth.php: the messages say "code" instead of "code or verification link".
 * Links in emails sent before this keep working until they expire.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);
$RT   = $ROOT . '/api/v1/account-runtime.php';
$AUTH = $ROOT . '/api/v1/auth.php';
const MARK = '// ROC code-only email (cli-auth-code-email.php)';
const HOOK = "    if (\$purpose==='verify') { require_once __DIR__.'/auth-code-email.php'; return rocQueue(\$db,\$u['email'],rocCodeEmailSubject(\$code),rocCodeEmail(\$code,\$u),10); }   " . MARK . "\n";
$MSGS = [
    'Check your email for your code or verification link.' => 'Check your email for your 6-digit code.',
    'Only the newest code or link works.' => 'Only the newest code works.',
];

echo "Run On Console - sign-up code email\n\n";

if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    if (!is_file($dir . '/account-runtime.php')) exit("No backup found in {$dir}\n");
    copy($dir . '/account-runtime.php', $RT);
    if (is_file($dir . '/auth.php')) copy($dir . '/auth.php', $AUTH);
    echo "Restored api/v1/account-runtime.php and api/v1/auth.php.\n\nDone.\n";
    exit(0);
}

if (!is_file($ROOT . '/api/v1/auth-code-email.php')) exit("STOP: api/v1/auth-code-email.php is missing. Unzip the deploy first.\n");
$rt = (string)@file_get_contents($RT);
if ($rt === '') exit("STOP: api/v1/account-runtime.php not found.\n");

// 1) the hook, inside rocIssueChallenge(), before its first return
$newRt = $rt;
if (strpos($rt, MARK) !== false) {
    echo "1) account-runtime.php: already sends the code email.\n";
} else {
    $f = strpos($rt, 'function rocIssueChallenge(');
    $r = $f === false ? false : strpos($rt, 'return rocQueue(', $f);
    $next = $f === false ? false : strpos($rt, "\nfunction ", $f + 10);
    if ($f === false || $r === false || ($next !== false && $r > $next) || strpos(substr($rt, $f, $r - $f), '$code=') === false)
        exit("STOP: rocIssueChallenge() does not look as expected in api/v1/account-runtime.php. Nothing was changed.\n");
    $ls = strrpos(substr($rt, 0, $r), "\n") + 1;          // start of the return line
    $newRt = substr($rt, 0, $ls) . HOOK . substr($rt, $ls);
    echo "1) account-runtime.php: sign-up verification sends the 6-digit code email (no link)\n";
}

// 2) wording in auth.php (optional: missing texts are skipped)
$auth = (string)@file_get_contents($AUTH);
$newAuth = $auth; $n = 0;
foreach ($MSGS as $a => $b) { $c = substr_count($newAuth, $a); if ($c) { $newAuth = str_replace($a, $b, $newAuth); $n += $c; } }
echo "2) auth.php: {$n} message(s) changed to say \"code\"\n";

if ($newRt === $rt && $newAuth === $auth) exit("\nNothing to do.\n");
if (!$apply) exit("\nDRY RUN - nothing changed. Run again with --apply.\n");

$dir = $HOME . '/roc-auth-code-email-' . date('Ymd-His');
if (!@mkdir($dir, 0700, true)) exit("STOP: could not make the backup folder {$dir}\n");
copy($RT, $dir . '/account-runtime.php');
if ($auth !== '') copy($AUTH, $dir . '/auth.php');
file_put_contents($RT, $newRt);
if ($newAuth !== $auth) file_put_contents($AUTH, $newAuth);

// The changed file must still load: if PHP reports an error, put the old one back.
$php = PHP_BINARY ?: 'php';
foreach ([$RT, $AUTH] as $file) {
    $out = []; $code = 0;
    @exec(escapeshellarg($php) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    if ($code !== 0 && $out) {
        copy($dir . '/account-runtime.php', $RT);
        if (is_file($dir . '/auth.php')) copy($dir . '/auth.php', $AUTH);
        exit("STOP: " . basename($file) . " did not pass the PHP check, the old files are back. Nothing changed.\n");
    }
}
echo "\nBackup: {$dir}\nUndo:   php cli-auth-code-email.php --rollback={$dir}\n\nDone. New sign-ups get a 6-digit code email.\n";
