<?php
/**
 * Run On Console — tables for messages from the website's forms (CMS -> Messages).
 *
 *   php cli-messages.php           dry run: shows what would change
 *   php cli-messages.php --apply   creates the tables (existing ones are never changed)
 *
 * roc_messages          one row per contact / write-for-us message
 * roc_message_replies   replies sent from the CMS
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

echo "Run On Console - message tables\n\n";

$tables = [
    'roc_messages' => "CREATE TABLE roc_messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kind VARCHAR(20) NOT NULL DEFAULT 'contact',
        name VARCHAR(120) NOT NULL,
        email VARCHAR(150) NOT NULL,
        subject VARCHAR(200) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        meta_json TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'new',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        user_agent VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        replied_at DATETIME NULL,
        INDEX roc_messages_status (status, created_at),
        INDEX roc_messages_ip (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'roc_message_replies' => "CREATE TABLE roc_message_replies (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        message_id INT UNSIGNED NOT NULL,
        body TEXT NOT NULL,
        sent_by VARCHAR(100) NOT NULL DEFAULT '',
        email_job_id INT NULL,
        created_at DATETIME NOT NULL,
        INDEX roc_message_replies_msg (message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$todo = [];
foreach ($tables as $t => $sql) {
    try { $pdo->query("SELECT 1 FROM {$t} LIMIT 1"); echo "  {$t}: already there\n"; }
    catch (\Throwable $e) { echo "  {$t}: create\n"; $todo[$t] = $sql; }
}
try { $pdo->query('SELECT 1 FROM email_queue LIMIT 1'); echo "  email_queue: found (notifications and replies are sent through it)\n"; }
catch (\Throwable $e) { echo "  email_queue: MISSING - messages are saved, but no emails can be sent\n"; }

if (!$todo) exit("\nNothing to do.\n");
if (!$apply) exit("\nDRY RUN - nothing changed. Run again with --apply.\n");
foreach ($todo as $t => $sql) { $pdo->exec($sql); echo "Created {$t}\n"; }
echo "\nDone.\n";
