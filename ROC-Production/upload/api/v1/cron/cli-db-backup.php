<?php
/**
 * Run On Console — make a database backup now (Deploy 69).
 *
 *   php cli-db-backup.php          writes today's backup to ~/roc-db-backups/ (replaces today's), keeps 7
 *   php cli-db-backup.php --list   lists the backups
 *
 * The blog scheduler cron makes one every day by itself; CMS > Backups downloads them.
 * Restore (only if the database is lost): cPanel > phpMyAdmin > the database > Import > the .sql.gz file.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$ROOT = dirname(__DIR__, 3);
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
require_once $ROOT . '/api/v1/cms/backup-lib.php';
if (in_array('--list', $argv, true)) {
    foreach (rocBackupList($ROOT) as $b) printf("  %s  %8s KB  %s\n", date('Y-m-d H:i', $b['time']), number_format($b['size'] / 1024), $b['file']);
    exit(0);
}
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("STOP: could not connect to the database.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$r = rocBackupRun($pdo, $ROOT);
echo "Backup written: " . rocBackupDir($ROOT) . '/' . $r['file'] . ' (' . number_format($r['bytes'] / 1024) . ' KB, ' . $r['tables'] . " tables)\n";
echo "Download it in CMS > Backups and keep a copy on your PC or Google Drive.\n";
