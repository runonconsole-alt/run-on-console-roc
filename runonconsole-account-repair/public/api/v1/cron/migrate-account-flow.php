<?php
// Backup first. Explicit CLI-only migration; never runs inside a web request.
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
if (!in_array('--apply',$argv,true)) { echo "Backup the database, then run with --apply. Existing users are not deleted.\n"; exit(1); }
require_once dirname(__DIR__).'/config.php';
$db=getDBConnection(); if (!$db) throw new RuntimeException('Database unavailable');
$columns=function($table) use($db) { return array_column($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(),'Field'); };
$u=$columns('users');
if (!in_array('email_verified',$u,true)) {
    $db->exec('ALTER TABLE users ADD email_verified TINYINT(1) NOT NULL DEFAULT 0');
    // Preserve current legacy state only on first addition; never mass-verify accounts.
    $db->exec('UPDATE users SET email_verified=is_verified');
}
if (!in_array('is_admin',$u,true)) $db->exec('ALTER TABLE users ADD is_admin TINYINT(1) NOT NULL DEFAULT 0');
$q=$columns('email_queue');
foreach (['locked_by'=>'VARCHAR(64) NULL','locked_at'=>'DATETIME NULL','next_attempt_at'=>'DATETIME NULL','sent_at'=>'DATETIME NULL','priority'=>'INT NOT NULL DEFAULT 0'] as $name=>$type)
    if (!in_array($name,$q,true)) $db->exec('ALTER TABLE email_queue ADD `'.$name.'` '.$type);
$db->exec("CREATE TABLE IF NOT EXISTS roc_auth_challenges (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,purpose VARCHAR(10) NOT NULL,
 code_hash VARCHAR(255) NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,attempts INT NOT NULL DEFAULT 0,
 code_expires_at DATETIME NOT NULL,link_expires_at DATETIME NOT NULL,consumed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX roc_challenge_user(user_id,purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS roc_auth_limits (
 bucket CHAR(64) PRIMARY KEY,attempts INT NOT NULL DEFAULT 0,window_start DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS roc_gaming_profiles (
 user_id INT PRIMARY KEY,city VARCHAR(100) NOT NULL DEFAULT '',date_of_birth DATE NULL,
 avatar_data MEDIUMTEXT NULL,favorite_games TEXT NULL,gaming_ids TEXT NULL,social_links TEXT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Account schema ready. Existing users and content preserved. Old verification/reset links need a fresh resend.\n";
