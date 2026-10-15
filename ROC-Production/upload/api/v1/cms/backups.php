<?php
/**
 * Run On Console (ROC) — CMS > Backups (administrators)
 *
 * GET                       the database backups (~/roc-db-backups, newest first)
 * GET ?download=FILE        downloads one backup (.sql.gz)
 * POST {action:'run'}       makes today's backup now
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/backup-lib.php';

$session = requireCmsAdmin();
$root = dirname(__DIR__, 3);

if (isset($_GET['download'])) {
    $f = basename((string)$_GET['download']);
    $path = rocBackupDir($root) . '/' . $f;
    if (!preg_match('/^roc-db-\d{4}-\d{2}-\d{2}\.sql\.gz$/', $f) || !is_file($path)) rocCmsDeny(404, 'Backup not found.');
    logCmsAudit('cms_backup_download', 'backup', $f);
    header_remove('Content-Type');
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="runonconsole-' . $f . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $pdo = getDBConnection();
    if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try { $r = rocBackupRun($pdo, $root); } catch (\Throwable $e) { rocCmsDeny(500, 'Backup failed: ' . $e->getMessage()); }
    logCmsAudit('cms_backup_run', 'backup', $r['file']);
    echo json_encode(['success' => true, 'message' => 'Backup made: ' . $r['file'] . ' (' . number_format($r['bytes'] / 1024) . ' KB).', 'backups' => rocBackupList($root)]);
    exit();
}

echo json_encode(['success' => true, 'keep' => ROC_BACKUP_KEEP, 'backups' => rocBackupList($root)]);
