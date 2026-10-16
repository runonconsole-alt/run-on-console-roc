<?php
/**
 * Run On Console — /guides-sitemap.xml and /games-sitemap.xml redirect to /sitemaps/ too (Deploy 79).
 *
 * Search Console reads a sitemap submitted as "games-sitemap.xml" at the site root. The .htaccess
 * rule that sends those names to /sitemaps/ was written before the guides and games sitemaps existed,
 * so both gave 404 ("Couldn't fetch").
 *
 *   php cli-sitemap-aliases.php           dry run
 *   php cli-sitemap-aliases.php --apply   adds guides and games to the rule (backup kept)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$apply = in_array('--apply', array_slice($argv, 1), true);

echo "Run On Console - sitemap names at the site root\npublic_html: {$ROOT}\n\n";
$ht = (string)@file_get_contents($ROOT . '/.htaccess');
if (!preg_match('/^(\s*RewriteRule \^\()([a-z|-]+)(\)-sitemap\\\\\.xml\$ \/sitemaps\/\$1-sitemap\.xml \[R=301,L\])/m', $ht, $m))
    exit("  !  The rule for sitemap names without /sitemaps/ was not found in .htaccess. Nothing changed.\n");
$names = explode('|', $m[2]);
$add = array_values(array_diff(['guides', 'games'], $names));
if (!$add) exit("  =  guides and games are already in the rule. Nothing to do.\n");
echo '  +  .htaccess: ' . implode(', ', array_map(function ($n) { return "/{$n}-sitemap.xml"; }, $add)) . " -> /sitemaps/\n";
if (!$apply) exit("\nDry run: nothing changed. Run again with --apply.\n");

$new = str_replace($m[0], $m[1] . implode('|', array_merge($names, $add)) . $m[3], $ht);
$file = $HOME . '/roc-sitemap-aliases-' . date('Ymd-His') . '.htaccess';
file_put_contents($file, $ht);
file_put_contents($ROOT . '/.htaccess.roc-tmp', $new);
rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
echo "Done. The old .htaccess is in {$file}\nRollback: cp {$file} {$ROOT}/.htaccess\n";
