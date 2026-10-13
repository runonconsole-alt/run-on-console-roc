<?php
/**
 * Run On Console — set up profile photos.
 *
 *   php cli-profile-photos.php           dry run: shows what would change
 *   php cli-profile-photos.php --apply   makes the changes
 *
 * 1. user_profiles.avatar_url: where the user's uploaded photo is (NULL = letter badge).
 * 2. public_html/uploads/avatars/ with an .htaccess that only serves images and never
 *    runs scripts.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$apply = in_array('--apply', array_slice($argv, 1), true);
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("STOP: could not connect to the database.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Run On Console - profile photos\n\n";
$st = $pdo->query('SELECT * FROM user_profiles LIMIT 0');
$cols = [];
for ($i = 0; $i < $st->columnCount(); $i++) $cols[] = strtolower((string)$st->getColumnMeta($i)['name']);
$needCol = !in_array('avatar_url', $cols, true);
$dir = $ROOT . '/uploads/avatars';
$needDir = !is_file($dir . '/.htaccess');
echo '1) user_profiles.avatar_url: ' . ($needCol ? "add\n" : "already there\n");
echo '2) uploads/avatars/: ' . ($needDir ? "create (images only)\n" : "already there\n");
if (!$needCol && !$needDir) exit("\nNothing to do.\n");
if (!$apply) exit("\nDRY RUN - nothing changed. Run again with --apply.\n");

if ($needCol) { $pdo->exec('ALTER TABLE user_profiles ADD COLUMN avatar_url VARCHAR(255) NULL'); echo "Added user_profiles.avatar_url\n"; }
if ($needDir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($dir . '/.htaccess', "# Profile photos: images only, nothing runs here.\n"
        . "Options -Indexes -ExecCGI\n"
        . "RemoveHandler .php .phtml .php3 .php4 .php5 .phar .pl .py .cgi\n"
        . "<FilesMatch \"\\.(?i:php\\d?|phtml|phar|pl|py|cgi|sh|html?)$\">\n  Require all denied\n</FilesMatch>\n");
    echo "Created uploads/avatars/\n";
}
echo "\nDone.\n";
