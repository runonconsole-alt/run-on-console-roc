<?php
/**
 * Run On Console — daily database backup (Deploy 69).
 *
 * One compressed SQL file a day in ~/roc-db-backups/ (outside public_html, never reachable from the web),
 * the last ROC_BACKUP_KEEP days kept. Made by the blog scheduler cron (every 10 minutes it checks
 * whether today's backup exists), or by hand: php api/v1/cron/cli-db-backup.php.
 * CMS > Backups lists them for download (administrators only).
 * Not in the file: the rows of sessions and login tokens (only their table structure).
 */
if (defined('ROC_BACKUP_LIB')) return;
define('ROC_BACKUP_LIB', 1);

const ROC_BACKUP_KEEP = 7;
const ROC_BACKUP_NO_ROWS = ['cms_sessions', 'user_sessions', 'sessions', 'password_resets', 'email_verifications', 'login_attempts'];

function rocBackupDir(string $root): string { return dirname($root) . '/roc-db-backups'; }

/** Backups, newest first: [['file', 'size', 'time']]. */
function rocBackupList(string $root): array {
    $out = [];
    foreach (glob(rocBackupDir($root) . '/roc-db-*.sql.gz') ?: [] as $f) $out[] = ['file' => basename($f), 'size' => (int)filesize($f), 'time' => (int)filemtime($f)];
    usort($out, function ($a, $b) { return $b['time'] <=> $a['time']; });
    return $out;
}

/** Writes today's backup (Pakistan date). Returns [file, bytes, tables] or throws. */
function rocBackupRun(PDO $pdo, string $root): array {
    @set_time_limit(600);
    $dir = rocBackupDir($root);
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n"); }
    $day = (new DateTime('now', new DateTimeZone('Asia/Karachi')))->format('Y-m-d');
    $file = $dir . '/roc-db-' . $day . '.sql.gz';
    $tmp = $file . '.part';
    $gz = gzopen($tmp, 'wb6');
    if (!$gz) throw new RuntimeException('Cannot write ' . $tmp);
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    gzwrite($gz, "-- Run On Console database backup, " . gmdate('c') . " UTC\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    $tables = $driver === 'sqlite' ? $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN)
                                   : $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $q = $driver === 'sqlite' ? '"' . str_replace('"', '""', $t) . '"' : '`' . str_replace('`', '``', $t) . '`';
        $create = $driver === 'sqlite' ? (string)$pdo->query("SELECT sql FROM sqlite_master WHERE name = " . $pdo->quote($t))->fetchColumn()
                                       : (string)($pdo->query('SHOW CREATE TABLE ' . $q)->fetch(PDO::FETCH_NUM)[1] ?? '');
        gzwrite($gz, "DROP TABLE IF EXISTS {$q};\n{$create};\n");
        if (in_array($t, ROC_BACKUP_NO_ROWS, true)) { gzwrite($gz, "\n"); continue; }
        $st = $pdo->query('SELECT * FROM ' . $q, PDO::FETCH_NUM);
        $batch = [];
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $batch[] = '(' . implode(',', array_map(function ($v) use ($pdo) { return $v === null ? 'NULL' : $pdo->quote((string)$v); }, $row)) . ')';
            if (count($batch) >= 100) { gzwrite($gz, "INSERT INTO {$q} VALUES\n" . implode(",\n", $batch) . ";\n"); $batch = []; }
        }
        if ($batch) gzwrite($gz, "INSERT INTO {$q} VALUES\n" . implode(",\n", $batch) . ";\n");
        gzwrite($gz, "\n");
    }
    gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
    gzclose($gz);
    rename($tmp, $file);
    @chmod($file, 0600);
    // Keep the newest ROC_BACKUP_KEEP.
    foreach (array_slice(rocBackupList($root), ROC_BACKUP_KEEP) as $old) @unlink($dir . '/' . $old['file']);
    return ['file' => basename($file), 'bytes' => (int)filesize($file), 'tables' => count($tables)];
}

/** Today's backup when it is missing (called by the scheduler cron). Never throws. */
function rocBackupDaily(PDO $pdo, string $root): ?string {
    $day = (new DateTime('now', new DateTimeZone('Asia/Karachi')))->format('Y-m-d');
    if (is_file(rocBackupDir($root) . '/roc-db-' . $day . '.sql.gz')) return null;
    try { $r = rocBackupRun($pdo, $root); return 'Backup: ' . $r['file'] . ' (' . round($r['bytes'] / 1024) . ' KB, ' . $r['tables'] . ' tables)'; }
    catch (\Throwable $e) { error_log('roc backup: ' . $e->getMessage()); return 'Backup failed: ' . $e->getMessage(); }
}
