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
        $t = 'cms_media';
        if (isset($row['table'], $row['row'])) { $t = $row['table']; $row = $row['row']; }
        try { $pdo->prepare('INSERT INTO ' . $t . ' (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row)); } catch (\Throwable $e) {}
    }
    exit("Put back {$n} picture(s).\n");
}

/* ------------------------------------------- what the site uses (shared with the CMS Media library) */
require_once $ROOT . '/api/v1/cms/media-refs-lib.php';
$refs = rocMediaRefs($pdo, $ROOT);
rocMediaRefsCache($ROOT, $refs);                                   // the CMS Media library shows the same

/* Earlier clean-ups: put back any picture the site uses again (or that was moved by mistake). */
$back = 0;
foreach (glob($HOME . '/roc-media-trash/*/MANIFEST.json') ?: [] as $mf) {
    $dir = dirname($mf);
    $man = json_decode((string)file_get_contents($mf), true);
    if (!is_array($man)) continue;
    $keep = [];
    foreach ((array)$man['files'] as $f) {
        if (!isset($refs[$f]) || !is_file($dir . $f) || is_file($ROOT . $f)) { $keep[] = $f; continue; }
        if (!$apply) { echo "  +  put back (in use): {$f}\n"; $back++; continue; }
        if (!is_dir(dirname($ROOT . $f))) @mkdir(dirname($ROOT . $f), 0755, true);
        if (!@rename($dir . $f, $ROOT . $f)) { $keep[] = $f; continue; }
        $back++;
        foreach ((array)($man['media_rows'] ?? []) as $row) {
            $t = 'cms_media';
            if (isset($row['table'], $row['row'])) { $t = $row['table']; $row = $row['row']; }
            if (strpos(implode(' ', array_filter($row, 'is_string')), $f) === false) continue;
            try { $pdo->prepare('INSERT INTO ' . $t . ' (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row)); } catch (\Throwable $e) {}
        }
    }
    if ($apply && count($keep) !== count((array)$man['files'])) { $man['files'] = $keep; file_put_contents($mf, json_encode($man, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); }
}
echo $back ? "{$back} picture(s) in use " . ($apply ? 'put back' : 'to put back') . " from earlier clean-ups.\n\n" : '';

/* ----------------------------------------------------------------------- candidates */
$cands = rocMediaCandidates($ROOT);
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
/* Media library entries whose file is gone (moved or deleted earlier): they show as empty
   "not used" cards in CMS > Media library. Picked out after the move below too. */
$orphans = function () use ($pdo, $ROOT): array {
    $out = [];
    foreach (['cms_media', 'media'] as $t) {
        try { $all = $pdo->query('SELECT * FROM ' . $t)->fetchAll(PDO::FETCH_ASSOC); } catch (\Throwable $e) { continue; }
        foreach ($all as $row) {
            $paths = [];
            foreach ($row as $v) if (is_string($v) && preg_match('#(?:https?://[^/"\s]+)?(/(?:uploads|images)/[^"\'\s?]+?\.(?:jpe?g|png|webp|gif|svg|avif))#i', $v, $m)) $paths[] = rawurldecode($m[1]);
            if (!$paths) continue;
            $gone = true;
            foreach ($paths as $pth) if (is_file($ROOT . $pth)) { $gone = false; break; }
            if ($gone) $out[] = ['table' => $t, 'row' => $row, 'path' => $paths[0]];
        }
    }
    return $out;
};
$orph = $orphans();
if ($orph) {
    echo count($orph) . " media library entr" . (count($orph) === 1 ? 'y points' : 'ies point') . " to a picture that is no longer there.\n";
    if (in_array('--list', $args, true)) foreach ($orph as $o) echo "       {$o['path']}\n";
}
if (!$apply) { echo "Dry run: nothing moved. Add --list to see every file, --apply to move them to the trash folder.\n"; exit(0); }

$dir = $HOME . '/roc-media-trash/' . date('Ymd-His');
@mkdir($dir, 0755, true);
$moved = []; $rows = [];
foreach ($unused as $u) {
    $to = $dir . $u;
    if (!is_dir(dirname($to))) @mkdir(dirname($to), 0755, true);
    if (@rename($ROOT . $u, $to)) {
        $moved[] = $u;
    }
}
// Library entries of the moved pictures and of pictures already gone (the backup keeps them).
$gone = 0;
foreach ($orphans() as $o) {
    $row = $o['row'];
    try {
        if (isset($row['id'])) $pdo->prepare('DELETE FROM ' . $o['table'] . ' WHERE id = ?')->execute([$row['id']]);
        else continue;
        unset($row['id']);
        $rows[] = ['table' => $o['table'], 'row' => $row];
        $gone++;
    } catch (\Throwable $e) {}
}
if ($gone) echo "Removed {$gone} media library entr" . ($gone === 1 ? 'y' : 'ies') . " without a picture.\n";
// Posts: share pictures (og_image, twitter_image) that still name an old cover follow the cover.
try {
    foreach (['og_image', 'twitter_image'] as $col)
        $pdo->exec("UPDATE blogs SET {$col} = image WHERE {$col} LIKE '%/blog-covers/%' AND image LIKE '%/blog-covers/%' AND {$col} <> image");
} catch (\Throwable $e) {}
file_put_contents($dir . '/MANIFEST.json', json_encode(['made' => date('c'), 'files' => $moved, 'media_rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo 'Moved ' . count($moved) . " picture(s) to {$dir}\nPut back: php " . __FILE__ . " --rollback={$dir}\n";
