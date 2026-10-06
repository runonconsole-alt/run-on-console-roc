<?php
/**
 * Run On Console — update the Media library's file sizes and dimensions from disk.
 *
 *   php cli-media-refresh.php           dry run: lists what is out of date
 *   php cli-media-refresh.php --apply   writes the real size / width / height
 *
 * After replacing image files on the server with smaller versions (same names),
 * the CMS still shows the old sizes because they are stored in the database.
 * This reads each file the library points to and stores its current size.
 * Only file_size, width and height change. Files that are missing are skipped.
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

echo "Run On Console - refresh Media library sizes\n\n";

// Find the media table: the one with file_url and file_size columns.
$table = null;
foreach (['cms_media', 'media', 'media_library', 'cms_media_library'] as $t) {
    try {
        $st = $pdo->query("SELECT * FROM {$t} LIMIT 0");
        $cols = [];
        for ($i = 0; $i < $st->columnCount(); $i++) $cols[] = strtolower((string)$st->getColumnMeta($i)['name']);
        if (in_array('file_url', $cols, true) && in_array('file_size', $cols, true) && in_array('id', $cols, true)) { $table = $t; break; }
    } catch (\Throwable $e) {}
}
if (!$table) exit("STOP: no media table with id, file_url and file_size found.\n");
$hasDims = in_array('width', $cols, true) && in_array('height', $cols, true);
echo "Table: {$table}\n";

$rows = $pdo->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
$changes = [];
foreach ($rows as $r) {
    $url = (string)($r['file_url'] ?? '');
    $path = (string)parse_url($url, PHP_URL_PATH);
    if ($path === '' || $path[0] !== '/' || strpos($path, '..') !== false) continue;
    $file = $ROOT . $path;
    if (!is_file($file)) continue;
    $size = filesize($file);
    [$w, $h] = @getimagesize($file) ?: [null, null];
    $old = (int)($r['file_size'] ?? 0);
    $dimsChanged = $hasDims && $w && ((int)$r['width'] !== $w || (int)$r['height'] !== $h);
    if ($old === $size && !$dimsChanged) continue;
    $changes[] = [$r['id'], $path, $old, $size, $w, $h];
}

if (!$changes) { echo "\nEverything is up to date.\n"; exit(0); }
foreach ($changes as [$id, $path, $old, $size, $w, $h]) {
    printf("  %-40s %7s KB -> %5s KB%s\n", $path, number_format($old / 1024), number_format($size / 1024), $w ? "  ({$w}x{$h})" : '');
}
if (!$apply) { echo "\n" . count($changes) . " file(s). DRY RUN - nothing changed. Run again with --apply.\n"; exit(0); }

$updSize = $pdo->prepare("UPDATE {$table} SET file_size = ? WHERE id = ?");
$updDims = $hasDims ? $pdo->prepare("UPDATE {$table} SET file_size = ?, width = ?, height = ? WHERE id = ?") : null;
foreach ($changes as [$id, $path, $old, $size, $w, $h]) {
    // Keep the stored dimensions when the file's could not be read.
    if ($updDims && $w) $updDims->execute([$size, $w, $h, $id]);
    else $updSize->execute([$size, $id]);
}
echo "\nUpdated " . count($changes) . " file(s).\n";
