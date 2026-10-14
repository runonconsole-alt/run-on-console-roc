<?php
/**
 * Run On Console — let ROC's microphone button work (Deploy 58).
 *
 *   php cli-mic-permission.php                  dry run
 *   php cli-mic-permission.php --apply          .htaccess: Permissions-Policy microphone=() -> microphone=(self), with a backup
 *   php cli-mic-permission.php --rollback=FILE  puts the old .htaccess back
 *
 * The header from cli-speed-headers.php switched the microphone off for the whole site, so the browser
 * refused ROC's voice input. (self) allows it on runonconsole.com only; camera, location, payment and
 * USB stay off.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

echo "Run On Console - microphone for ROC\n\n";
if ($rollback !== null) {
    $old = (string)@file_get_contents($rollback);
    if ($old === '') exit("No backup in {$rollback}\n");
    file_put_contents($ROOT . '/.htaccess', $old);
    exit("Restored .htaccess\n");
}
$f = $ROOT . '/.htaccess';
$ht = (string)@file_get_contents($f);
if ($ht === '') exit("STOP: .htaccess not found.\n");
$n = preg_match_all('/microphone=\(\)/', $ht);
if (!$n) {
    echo strpos($ht, 'microphone=(self)') !== false ? "  =  The microphone is already allowed for this site.\nNothing to do.\n"
                                                    : "  =  No Permissions-Policy blocks the microphone.\nNothing to do.\n";
    exit(0);
}
echo "  +  Permissions-Policy: microphone=() -> microphone=(self) ({$n} place(s))\n";
if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }
$bak = $HOME . '/roc-htaccess-mic-' . date('Ymd-His') . '.bak';
file_put_contents($bak, $ht);
file_put_contents($f . '.roc-tmp', str_replace('microphone=()', 'microphone=(self)', $ht));
rename($f . '.roc-tmp', $f);
echo "\n.htaccess updated.\nBackup: {$bak}\nRollback: php " . __FILE__ . " --rollback={$bak}\n";
