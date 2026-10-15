<?php
/**
 * Run On Console — what fills the hosting disk, and a safe clean-up (Deploy 67).
 *
 *   php cli-disk-usage.php           report only: the biggest folders and files, and what can go
 *   php cli-disk-usage.php --apply   deletes ONLY the safe items listed under "Can be removed"
 *
 * Can be removed (never anything the website needs):
 *   - deploy zips in the home folder (ROC-Deploy-*.zip ...), except the newest one;
 *   - the backups our installers made (~/roc-*-backup-*), older than 14 days, keeping the newest of each kind;
 *   - CMS media trash older than 30 days (~/roc-media-trash);
 *   - PHP error logs bigger than 5 MB are emptied (the file stays).
 * Only reported, never deleted here: cPanel full backups (backup-*.tar.gz: download them first, then
 * delete them in cPanel > File Manager), e-mail (~/mail), the database, public_html (assets included).
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);          // public_html
$HOME = dirname($ROOT);
$apply = in_array('--apply', array_slice($argv, 1), true);
$now = time();

function duSize(string $p): int {
    if (is_link($p)) return 0;
    if (is_file($p)) return (int)@filesize($p);
    $t = 0;
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($it as $f) if (!$f->isLink()) $t += (int)$f->getSize();
    } catch (\Throwable $e) {}
    return $t;
}
function duMb(int $b): string { return number_format($b / 1048576, 1) . ' MB'; }
function duRm(string $p): void {
    if (is_link($p) || is_file($p)) { @unlink($p); return; }
    if (!is_dir($p)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($p);
}

echo "Run On Console - disk usage\nhome: {$HOME}\n\n";

/* -------------------------------------------------------------- report */
$top = [];
foreach (scandir($HOME) ?: [] as $n) {
    if ($n === '.' || $n === '..') continue;
    $top[$n] = duSize($HOME . '/' . $n);
}
arsort($top);
echo "Biggest items in the home folder:\n";
foreach (array_slice($top, 0, 15, true) as $n => $b) printf("  %10s  %s\n", duMb($b), $n);
echo '  ' . str_repeat('-', 30) . "\n  " . str_pad(duMb(array_sum($top)), 10, ' ', STR_PAD_LEFT) . "  total\n\n";

$in = [];
foreach (scandir($ROOT) ?: [] as $n) if ($n !== '.' && $n !== '..') $in[$n] = duSize($ROOT . '/' . $n);
arsort($in);
echo "Biggest items in public_html (the website, nothing here is removed):\n";
foreach (array_slice($in, 0, 10, true) as $n => $b) printf("  %10s  %s\n", duMb($b), $n);
echo "\n";

/* -------------------------------------------------------- what can go */
$del = []; $empty = [];
// 1. Deploy zips (keep the newest).
$zips = glob($HOME . '/*.zip') ?: [];
usort($zips, function ($a, $b) { return filemtime($b) <=> filemtime($a); });
foreach (array_slice($zips, 1) as $z) $del[] = [$z, 'old deploy zip'];
// 2. Installer backups: older than 14 days, the newest of each kind kept.
$kinds = [];
foreach (glob($HOME . '/roc-*-backup-*') ?: [] as $b) {
    $k = preg_replace('/-backup-.*$/', '', basename($b));
    $kinds[$k][] = $b;
}
foreach ($kinds as $k => $list) {
    usort($list, function ($a, $b) { return filemtime($b) <=> filemtime($a); });
    foreach (array_slice($list, 1) as $b) if ($now - filemtime($b) > 14 * 86400) $del[] = [$b, 'old installer backup'];
}
// 3. Media trash older than 30 days.
foreach (glob($HOME . '/roc-media-trash/*') ?: [] as $t) if ($now - filemtime($t) > 30 * 86400) $del[] = [$t, 'CMS media trash'];
// 4. Big PHP error logs: emptied.
$logs = array_merge(glob($ROOT . '/error_log') ?: [], glob($ROOT . '/*/error_log') ?: [], glob($ROOT . '/api/v1/*/error_log') ?: [], glob($ROOT . '/api/v1/error_log') ?: [],
                    glob($HOME . '/logs/*.log') ?: [], glob($HOME . '/logs/*error*') ?: []);
foreach (array_unique($logs) as $l) if (is_file($l) && filesize($l) > 5 * 1048576) $empty[] = $l;

$free = 0;
echo "Can be removed safely:\n";
if (!$del && !$empty) echo "  (nothing)\n";
foreach ($del as [$p, $why]) { $s = duSize($p); $free += $s; printf("  %10s  %s  (%s)\n", duMb($s), substr($p, strlen($HOME) + 1), $why); }
foreach ($empty as $l) { $s = (int)filesize($l); $free += $s; printf("  %10s  %s  (error log, emptied)\n", duMb($s), substr($l, strlen($HOME) + 1)); }
echo '  => frees about ' . duMb($free) . "\n\n";

echo "Only for you to decide (not removed here):\n";
$any = false;
foreach (glob($HOME . '/*.tar.gz') ?: [] as $b) {
    $any = true; printf("  %10s  %s  (cPanel full backup: download it to your PC, then delete it in File Manager)\n", duMb((int)filesize($b)), basename($b));
}
if (is_dir($HOME . '/.trash') && ($tb = $top['.trash'] ?? 0) > 1048576) { $any = true; printf("  %10s  .trash  (cPanel File Manager trash: open File Manager > View Trash > Empty Trash)
", duMb($tb)); }
if (is_dir($HOME . '/mail')) { $any = true; printf("  %10s  mail  (e-mails: empty Trash/Spam in Webmail)\n", duMb($top['mail'] ?? duSize($HOME . '/mail'))); }
if (!$any) echo "  (nothing)\n";

if (!$apply) { echo "\nReport only: nothing changed. Run again with --apply to remove the items under \"Can be removed safely\".\n"; exit(0); }

foreach ($del as [$p]) duRm($p);
foreach ($empty as $l) @file_put_contents($l, '');
clearstatcache();
echo "\nDone: removed " . count($del) . ' item(s), emptied ' . count($empty) . ' log(s), about ' . duMb($free) . " freed.\n";
