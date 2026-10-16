<?php
/**
 * Run On Console — takes the RSS feed rules out of .htaccess again (owner, 2026-10-16: no Publer / RSS).
 *
 *   php cli-social-feed.php           shows whether the rules are there
 *   php cli-social-feed.php --apply   removes them (backup kept); safe to run when they are not there
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$apply = in_array('--apply', array_slice($argv, 1), true);

echo "Run On Console - remove the RSS feed rules\npublic_html: {$ROOT}\n\n";
$ht = (string)@file_get_contents($ROOT . '/.htaccess');
$new = (string)preg_replace('/  # ROC feeds BEGIN \(cli-social-feed\.php\).*?  # ROC feeds END\r?\n(\r?\n)?/s', '', $ht);
if ($new === $ht) exit("  =  .htaccess has no RSS feed rules. Nothing to do.\n");
echo "  -  .htaccess: the /feed.xml rules\n";
if (!$apply) exit("\nDry run: nothing changed. Run again with --apply.\n");
$file = $HOME . '/roc-feeds-removed-' . date('Ymd-His') . '.htaccess';
file_put_contents($file, $ht);
file_put_contents($ROOT . '/.htaccess.roc-tmp', $new);
rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
echo "Removed. The old .htaccess is in {$file}\n";
