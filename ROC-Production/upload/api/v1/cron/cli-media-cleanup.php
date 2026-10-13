<?php
/**
 * Run On Console — take pictures that nothing uses off the site (moved to a trash folder, not deleted).
 *
 *   php cli-media-cleanup.php                  dry run: lists every picture that is not used anywhere
 *   php cli-media-cleanup.php --apply          moves them to /home2/runoncon/roc-media-trash/<date>/
 *   php cli-media-cleanup.php --rollback=DIR   puts them back (and their media library rows)
 *
 * A picture counts as used when its address appears anywhere: in any database table (products,
 * blogs, news, pages, platforms, users, settings...), in a page file (*.html), in the site's
 * JavaScript and CSS (assets, roc-nav.js), in site-layer.json or in the CMS files. Addresses are
 * compared without "?v=" and URL encoding.
 *
 * Looked at: /uploads/** and the site pictures in /images/ (the old product name cards in
 * /images/products/, the share cards in /images/products/share/ and the old /images/*.jpg photos).
 * Never touched: /images/products/photos/ (the real product photos), logos and icons.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

echo "Run On Console - unused pictures\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    $man = json_decode((string)@file_get_contents($dir . '/MANIFEST.json'), true);
    if (!is_array($man)) exit("No MANIFEST.json in {$dir}\n");
    $n = 0;
    foreach ($man['files'] as $f) {
        $from = $dir . $f; $to = $ROOT . $f;
        if (!is_file($from)) continue;
        if (!is_dir(dirname($to))) @mkdir(dirname($to), 0755, true);
        if (rename($from, $to)) $n++;
    }
    foreach ((array)($man['media_rows'] ?? []) as $row) {
        try { $pdo->prepare('INSERT INTO cms_media (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row)); } catch (\Throwable $e) {}
    }
    exit("Put back {$n} picture(s).\n");
}

/* ----------------------------------------------------- everything that can refer to a picture */
$refs = [];
$grab = function (string $text) use (&$refs) {
    $text = str_replace('\/', '/', $text);                                  // JSON-escaped addresses
    if (!preg_match_all('#(?:uploads|images)/[A-Za-z0-9_./%-]+?\.(?:jpe?g|png|webp|gif|svg|avif)#i', $text, $m)) return;
    foreach ($m[0] as $u) $refs['/' . rawurldecode($u)] = 1;
};
@ini_set('memory_limit', '512M');
$isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
$tables = $isSqlite ? $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN)
                    : $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    if (preg_match('/(_bak_|backup|audit|log$|logs$|sessions?$|cache)/i', (string)$t)) continue;
    try { $st = $pdo->query('SELECT * FROM ' . $t); } catch (\Throwable $e) { continue; }
    while ($row = $st->fetch(PDO::FETCH_NUM)) foreach ($row as $v) if (is_string($v) && (stripos($v, 'uploads/') !== false || stripos($v, 'images/') !== false)) $grab($v);
}
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (strpos($p, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR) !== false || strpos($p, DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR) !== false) continue;
    if (!preg_match('/\.(html|js|css|json|php|txt|xml)$/i', $p) || $f->getSize() > 20000000) continue;
    if (preg_match('#cli-media-cleanup[.]php$#', $p)) continue;
    $grab((string)file_get_contents($p));
}

/* ----------------------------------------------------------------------- candidates */
$cands = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT . '/uploads', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) if (preg_match('/\.(jpe?g|png|webp|gif|svg|avif)$/i', $f->getFilename())) $cands[] = '/uploads' . str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT . '/uploads')));
foreach (glob($ROOT . '/images/products/*.{webp,svg,jpg,png}', GLOB_BRACE) ?: [] as $f) $cands[] = '/images/products/' . basename($f);
foreach (glob($ROOT . '/images/products/share/*.{jpg,png,webp}', GLOB_BRACE) ?: [] as $f) $cands[] = '/images/products/share/' . basename($f);
foreach (glob($ROOT . '/images/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $f) {
    if (preg_match('/(logo|icon|favicon|apple-touch|og-|android|maskable|manifest)/i', basename($f))) continue;
    $cands[] = '/images/' . basename($f);
}
$unused = []; $bytes = 0;
foreach ($cands as $c) {
    if (isset($refs[$c])) continue;
    $unused[] = $c; $bytes += (int)filesize($ROOT . $c);
}
$groups = [];
foreach ($unused as $u) { $g = dirname($u); $groups[$g] = ($groups[$g] ?? 0) + 1; }
ksort($groups);
foreach ($groups as $g => $n) echo '  -  ' . str_pad($g . '/', 34) . $n . " picture(s) not used\n";
if (in_array('--list', $args, true)) foreach ($unused as $u) echo "       {$u}\n";
echo "\n" . count($unused) . ' of ' . count($cands) . ' picture(s) are not used anywhere (' . round($bytes / 1048576, 1) . " MB).\n";
if (!$apply) { echo "Dry run: nothing moved. Add --list to see every file, --apply to move them to the trash folder.\n"; exit(0); }

$dir = $HOME . '/roc-media-trash/' . date('Ymd-His');
@mkdir($dir, 0755, true);
$moved = []; $rows = [];
foreach ($unused as $u) {
    $to = $dir . $u;
    if (!is_dir(dirname($to))) @mkdir(dirname($to), 0755, true);
    if (@rename($ROOT . $u, $to)) {
        $moved[] = $u;
        try {
            $st = $pdo->prepare('SELECT * FROM cms_media WHERE file_url = ?'); $st->execute([$u]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { unset($r['id']); $rows[] = $r; }
            $pdo->prepare('DELETE FROM cms_media WHERE file_url = ?')->execute([$u]);
        } catch (\Throwable $e) {}
    }
}
file_put_contents($dir . '/MANIFEST.json', json_encode(['made' => date('c'), 'files' => $moved, 'media_rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo 'Moved ' . count($moved) . " picture(s) to {$dir}\nPut back: php " . __FILE__ . " --rollback={$dir}\n";
