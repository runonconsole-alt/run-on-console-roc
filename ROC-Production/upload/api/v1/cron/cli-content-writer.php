<?php
/**
 * Run On Console — About texts for product pages and gaming platform pages (Deploy 47).
 *
 *   php cli-content-writer.php                    dry run: how many pages still have little text
 *   php cli-content-writer.php --apply            adds the platform text column, then writes 10 pages now
 *   php cli-content-writer.php --apply --limit=N  writes N pages now (the writer key has a daily limit)
 *   php cli-content-writer.php --apply --slug=X   writes one product or platform again
 *   php cli-content-writer.php --auto=off|on      stops / restarts the automatic writing (2 a run, 40 a day)
 *   php cli-content-writer.php --rollback=FILE    puts the old texts back
 *
 * The text is written with the blog writer key (CMS > Blog & news writer) and saved in the CMS:
 * products get About this product, pros, cons and (when empty) best for and a better description;
 * platforms get About text. Everything can be edited in the CMS. See content-writer-lib.php for the
 * writing rules (helpful, specific, honest, no prices, no hype, no invented numbers).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$limit = 10; $slug = null; $rollback = null; $auto = null;
foreach ($args as $a) {
    if (strpos($a, '--limit=') === 0) $limit = max(1, min(60, (int)substr($a, 8)));
    if (strpos($a, '--slug=') === 0) $slug = substr($a, 7);
    if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);
    if (strpos($a, '--auto=') === 0) $auto = substr($a, 7);
}

echo "Run On Console - About texts for products and gaming platforms\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/content-writer-lib.php';

if ($auto !== null) {
    rocBlogSet($pdo, 'content_auto', $auto === 'off' ? 'off' : 'on');
    exit('Automatic writing is ' . ($auto === 'off' ? 'off' : 'on (2 pages a run, at most ' . ROC_CONTENT_DAILY . ' a day)') . ".\n");
}

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b)) exit("No backup found in {$rollback}\n");
    $n = 0;
    foreach ((array)($b['products'] ?? []) as $s => $row) {
        $set = array_intersect_key($row, array_flip(['summary', 'pros', 'cons', 'short_desc', 'best_for']));
        $pdo->prepare('UPDATE products SET ' . implode(', ', array_map(function ($c) { return "{$c} = ?"; }, array_keys($set))) . ' WHERE slug = ?')->execute([...array_values($set), $s]);
        $n++;
    }
    foreach ((array)($b['platforms'] ?? []) as $s => $t) { $pdo->prepare('UPDATE gaming_categories SET about_text = ? WHERE slug = ?')->execute([$t, $s]); $n++; }
    exit("Put back the old text of {$n} page(s).\n");
}

// The platforms table needs a column for its About text.
$hasCol = in_array('about_text', rocBlogCols($pdo, 'gaming_categories'), true);
if (!$hasCol) {
    echo "  +  gaming_categories: new column about_text (the platform About text)\n";
    if ($apply) { $pdo->exec('ALTER TABLE gaming_categories ADD COLUMN about_text TEXT NULL'); echo "     added\n"; }
}

$writers = array_keys(rocBlogWriters($pdo));
echo '  Writer key: ' . ($writers ? implode(', ', $writers) : 'none saved (CMS > Blog & news writer)') . "\n";
$q = rocContentQueue($pdo);
echo '  Product pages with little text: ' . count($q['products']) . "\n";
echo '  Platform pages with little text: ' . ($hasCol ? count($q['platforms']) : 'all') . "\n";
echo '  Automatic writing: ' . ((string)rocBlogGet($pdo, 'content_auto', 'on') === 'off' ? 'off' : 'on (2 a run, at most ' . ROC_CONTENT_DAILY . ' a day)') . "\n\n";
if (!$apply) { echo "Dry run: nothing written. Run again with --apply (writes {$limit} pages now; the rest are written by the scheduler).\n"; exit(0); }
if (!$writers) exit("Save a writer key in CMS > Blog & news writer first.\n");
if (!$hasCol) $q = rocContentQueue($pdo);

$items = array_merge(array_map(function ($x) { return ['platform', $x]; }, $q['platforms']), array_map(function ($x) { return ['product', $x]; }, $q['products']));
if ($slug !== null) {
    $items = [];
    $st = $pdo->prepare('SELECT * FROM products WHERE slug = ?'); $st->execute([$slug]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) $items[] = ['product', $r];
    else { $st = $pdo->prepare('SELECT * FROM gaming_categories WHERE slug = ?'); $st->execute([$slug]); if ($r = $st->fetch(PDO::FETCH_ASSOC)) $items[] = ['platform', $r]; }
    if (!$items) exit("Nothing found with the address {$slug}.\n");
}
$items = array_slice($items, 0, $limit);
if (!$items) exit("Every page already has its text.\n");

$file = $HOME . '/roc-content-backup-' . date('Ymd-His') . '.json';
$backup = ['made' => date('c'), 'products' => [], 'platforms' => []];
foreach ($items as [$t, $row]) {
    if ($t === 'product') $backup['products'][$row['slug']] = array_intersect_key($row, array_flip(['summary', 'pros', 'cons', 'short_desc', 'best_for']));
    else $backup['platforms'][$row['slug']] = (string)($row['about_text'] ?? '');
}
file_put_contents($file, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$ok = 0;
foreach ($items as [$t, $row]) {
    if ($t === 'platform') $r = rocContentWritePlatform($pdo, $row);
    else { [$cn, $gn] = rocContentNames($pdo, $row); $r = rocContentWriteProduct($pdo, $row, $cn, $gn); }
    echo '  ' . ($r['ok'] ? 'ok ' : '!  ') . str_pad($t, 9) . $row['slug'] . ($r['ok'] ? '' : ' - ' . $r['error']) . "\n";
    if (!$r['ok']) { echo "Stopped: the writer did not answer well. Try again later (the free key has a limit per minute and per day).\n"; break; }
    $ok++;
}
$left = count($q['products']) + count($q['platforms']) - $ok;
echo "\nWrote {$ok} page(s). {$left} left: the scheduler writes 2 every run (at most " . ROC_CONTENT_DAILY . " a day), or run this again.\n"
   . "Backup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
