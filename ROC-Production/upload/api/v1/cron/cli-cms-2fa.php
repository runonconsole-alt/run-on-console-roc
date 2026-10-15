<?php
/**
 * Run On Console — switch on 2-step verification for CMS sign-in (Deploy 69).
 *
 *   php cli-cms-2fa.php                    dry run: shows what would change
 *   php cli-cms-2fa.php --apply            creates the table and adds the code check to api/v1/cms/auth.php (backup kept)
 *   php cli-cms-2fa.php --rollback=FILE    puts auth.php back from the backup
 *   php cli-cms-2fa.php --disable=NAME     turns 2-step verification off for that CMS user (lost phone and backup codes)
 *
 * Nothing changes for anyone until they turn it on in CMS > 2-step verification.
 * Files needed first (in the deploy zip): api/v1/cms/twofa-lib.php, api/v1/cms/twofa.php, cms/index.html.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null; $disable = null;
foreach ($args as $a) {
    if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);
    if (strpos($a, '--disable=') === 0) $disable = substr($a, 10);
}

const TF_MARK = '// 2-step verification (cli-cms-2fa.php)';
const TF_HOOK = "    " . TF_MARK . "\n    require_once __DIR__ . '/twofa-lib.php';\n    rocTwofaLoginGate(\$pdo, \$user, \$data);\n\n";
const TF_ANCHOR = '    // Create session token and CSRF token';

echo "Run On Console - CMS 2-step verification\npublic_html: {$ROOT}\n\n";
$authFile = $ROOT . '/api/v1/cms/auth.php';

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b) || !is_string($b['auth'] ?? null) || $b['auth'] === '') exit("No backup found in {$rollback}\n");
    file_put_contents($authFile, $b['auth']);
    exit("Restored api/v1/cms/auth.php. Sign-in no longer asks for a code.\n");
}

ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("STOP: could not connect to the database.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/twofa-lib.php';

if ($disable !== null) {
    $st = $pdo->prepare('SELECT id FROM cms_users WHERE username = ? OR email = ? LIMIT 1');
    $st->execute([$disable, $disable]);
    $id = (int)$st->fetchColumn();
    if (!$id) exit("No CMS user called {$disable}.\n");
    rocTwofaDisable($pdo, $id);
    exit("2-step verification is off for {$disable}. Sign in with the password, then turn it on again with the new phone.\n");
}

$problems = [];
foreach (['api/v1/cms/twofa-lib.php', 'api/v1/cms/twofa.php', 'api/v1/cms/auth.php'] as $f)
    if (!is_file($ROOT . '/' . $f)) $problems[] = "public_html/{$f} is missing. Unzip the deploy again.";
$auth = is_file($authFile) ? (string)file_get_contents($authFile) : '';
$hooked = strpos($auth, TF_MARK) !== false;
$nl = strpos($auth, "\r\n") !== false ? "\r\n" : "\n";
if (!$hooked && strpos(str_replace("\r\n", "\n", $auth), TF_ANCHOR) === false) $problems[] = 'auth.php does not look like the CMS sign-in file this tool knows (no "Create session token" line). Nothing changed; tell the developer.';
if ($problems) { echo "STOP:\n  - " . implode("\n  - ", $problems) . "\n"; exit(1); }

$table = rocTwofaTable($pdo);
echo $table ? "  =  table cms_user_2fa exists\n" : "  +  table cms_user_2fa (one row per user who turns it on)\n";
echo $hooked ? "  =  auth.php already checks the code\n" : "  +  auth.php: after the password, ask for the 6-digit code (only for users who turned it on)\n";
if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }

if (!$table) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_user_2fa (
        user_id INT NOT NULL PRIMARY KEY,
        secret VARCHAR(64) NOT NULL DEFAULT '',
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        backup_json TEXT,
        fails INT NOT NULL DEFAULT 0,
        locked_until DATETIME NULL,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Table created\n";
}
if (!$hooked) {
    $file = $HOME . '/roc-2fa-backup-' . date('Ymd-His') . '.json';
    file_put_contents($file, json_encode(['made' => date('c'), 'auth' => $auth], JSON_UNESCAPED_SLASHES));
    $new = str_replace("\r\n", "\n", $auth);
    $new = str_replace(TF_ANCHOR, rtrim(TF_HOOK, "\n") . "\n\n" . TF_ANCHOR, $new);
    $new = str_replace("\n", $nl, $new);
    file_put_contents($authFile . '.roc-tmp', $new);
    // Never leave a broken sign-in: check the new file before it replaces the old one.
    $php = PHP_BINARY ?: 'php'; $out = []; $rc = 0;
    if (function_exists('exec')) @exec(escapeshellarg($php) . ' -l ' . escapeshellarg($authFile . '.roc-tmp') . ' 2>&1', $out, $rc);
    if ($rc !== 0) { @unlink($authFile . '.roc-tmp'); exit("STOP: the changed auth.php did not pass the PHP check; nothing changed.\n" . implode("\n", $out) . "\n"); }
    rename($authFile . '.roc-tmp', $authFile);
    echo "auth.php updated\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
}
echo "\nDone. Now in the CMS: menu > 2-step verification > Turn on.\n";
