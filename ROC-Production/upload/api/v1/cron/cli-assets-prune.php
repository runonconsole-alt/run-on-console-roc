<?php
/**
 * Run On Console — old website files in public_html/assets (Deploy 184).
 *
 *   php cli-assets-prune.php           dry run: what is still used, what can go, how much space it frees
 *   php cli-assets-prune.php --apply   deletes the unused files
 *
 * Every website deploy brings a new index-*.js / index-*.css and leaves the old ones (89 MB on 12 November).
 * A file is KEPT when any page, template or another kept file names it (followed through the scripts, so lazy
 * parts of the app stay), or when it changed in the last 7 days (a page cached by a browser or Cloudflare may
 * still ask for it). Everything else in assets/ goes. Nothing outside public_html/assets is touched.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$ROOT = dirname(__DIR__, 3);
$apply = in_array('--apply', array_slice($argv, 1), true);
$dir = $ROOT . '/assets';
if (!is_dir($dir)) exit("No assets folder.\n");
echo "Run On Console - unused files in public_html/assets\n\n";

$all = [];
foreach (scandir($dir) ?: [] as $n) if ($n !== '.' && $n !== '..' && is_file($dir . '/' . $n)) $all[$n] = [(int)filesize($dir . '/' . $n), (int)filemtime($dir . '/' . $n)];
if (!$all) exit("The assets folder is empty.\n");

// Every page and template that can name an asset.
$sources = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (strpos($p, $dir . DIRECTORY_SEPARATOR) === 0 || strpos($p, $dir . '/') === 0) continue;
    if (preg_match('/\.(html?|php|js|json|css)$/i', $p) && $f->getSize() < 8 * 1048576) $sources[] = $p;
}
$keep = [];
$scan = function (string $text) use (&$keep, $all): array {
    $new = [];
    foreach (array_keys($all) as $n) if (!isset($keep[$n]) && strpos($text, $n) !== false) { $keep[$n] = 1; $new[] = $n; }
    return $new;
};
foreach ($sources as $p) $scan((string)@file_get_contents($p));
// Files the kept scripts and styles load (lazy parts, fonts), until nothing new turns up.
$queue = array_keys($keep);
while ($queue) {
    $n = array_shift($queue);
    if (!preg_match('/\.(js|css|mjs)$/', $n)) continue;
    foreach ($scan((string)@file_get_contents($dir . '/' . $n)) as $x) $queue[] = $x;
}
// Recent files stay.
foreach ($all as $n => [$s, $t]) if (time() - $t < 7 * 86400) $keep[$n] = 1;

$go = array_diff_key($all, $keep);
$free = array_sum(array_column($go, 0));
printf("Files in assets: %d (%.1f MB)\nKept (used or newer than 7 days): %d\nUnused: %d, %.1f MB\n", count($all), array_sum(array_column($all, 0)) / 1048576, count($keep), count($go), $free / 1048576);
foreach (array_slice(array_keys($go), 0, 10) as $n) echo "  - {$n}\n";
if (count($go) > 10) echo '  ... and ' . (count($go) - 10) . " more\n";
if (!$apply) exit("\nDry run: nothing deleted. Run again with --apply.\n");
$n = 0;
foreach (array_keys($go) as $f) if (@unlink($dir . '/' . $f)) $n++;
printf("\nDeleted %d file(s), %.1f MB freed.\n", $n, $free / 1048576);
