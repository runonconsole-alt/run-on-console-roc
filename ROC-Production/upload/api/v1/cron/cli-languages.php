<?php
/**
 * Run On Console — switch on the game pages in other languages (Deploy 177).
 *
 *   php cli-languages.php                  dry run
 *   php cli-languages.php --apply          adds the routes to .htaccess (backup kept)
 *   php cli-languages.php --rollback=FILE  puts .htaccess back
 *
 * /de/, /es/, /pt/, /fr/ and /ru/ + /can-i-run/... go to game-i18n.php, and /sitemaps/games-<lang>-sitemap.xml too.
 * The rules go before the English "Can I run it?" rules. Once they are there, the English game pages list their
 * other languages (hreflang) and the sitemap index lists the language sitemaps.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const LG_BEGIN = '  # ROC i18n BEGIN (cli-languages.php)';
const LG_BLOCK = LG_BEGIN . "\n"
    . "  # The \"Can I run it?\" game pages in other languages (game-i18n.php)\n"
    . "  RewriteRule ^(de|es|pt|fr|ru)/?$ /\$1/can-i-run/ [R=301,L]\n"
    . "  RewriteRule ^(de|es|pt|fr|ru)/can-i-run$ /\$1/can-i-run/ [R=301,L]\n"
    . "  RewriteRule ^(de|es|pt|fr|ru)/can-i-run/([a-z0-9-]+)$ /\$1/can-i-run/\$2/ [R=301,L]\n"
    . "  RewriteRule ^(de|es|pt|fr|ru)/can-i-run/$ game-i18n.php?lang=\$1 [L,QSA]\n"
    . "  RewriteRule ^(de|es|pt|fr|ru)/can-i-run/([a-z0-9-]+)/$ game-i18n.php?lang=\$1&slug=\$2 [L,QSA]\n"
    . "  RewriteRule ^sitemaps/games-(de|es|pt|fr|ru)-sitemap\\.xml$ game-i18n.php?lang=\$1&sitemap=1 [L]\n"
    . "  # ROC i18n END\n";

echo "Run On Console - game pages in other languages\npublic_html: {$ROOT}\n\n";
if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b) || !is_string($b['htaccess'] ?? null) || $b['htaccess'] === '') exit("No backup found in {$rollback}\n");
    file_put_contents($ROOT . '/.htaccess', $b['htaccess']);
    exit("Restored .htaccess\n");
}
if (!is_file($ROOT . '/game-i18n.php') || !is_file($ROOT . '/api/v1/i18n-lib.php')) exit("STOP: upload the Deploy 177 zip first (game-i18n.php is missing).\n");
$ht = (string)@file_get_contents($ROOT . '/.htaccess');
if (strpos($ht, LG_BEGIN) !== false) exit("  =  .htaccess already has the language rules. Nothing to do.\n");
$anchor = '  # One "Can I run it?" page per PC game';
if (strpos($ht, $anchor) === false) exit("STOP: .htaccess has no \"Can I run it?\" rules to put the language rules before.\n");
echo "  +  .htaccess: /de/ /es/ /pt/ /fr/ /ru/ + /can-i-run/... and their sitemaps\n";
if (!$apply) exit("\nDry run: nothing changed. Run again with --apply.\n");
$file = $HOME . '/roc-languages-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'htaccess' => $ht]));
file_put_contents($ROOT . '/.htaccess.roc-tmp', str_replace($anchor, LG_BLOCK . "\n" . $anchor, $ht));
rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
echo ".htaccess updated\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n\nDone. Open https://runonconsole.com/de/can-i-run/\n";
