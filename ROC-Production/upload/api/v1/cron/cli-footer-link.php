<?php
/**
 * Run On Console — adds one link to the footer menu (CMS > Menus & footer), Deploy 174.
 *
 *   php cli-footer-link.php --url=/can-i-run/upcoming/ --label="Upcoming PC Games"            dry run
 *   php cli-footer-link.php --url=/can-i-run/upcoming/ --label="Upcoming PC Games" --apply    adds it
 *   php cli-footer-link.php --url=/can-i-run/upcoming/ --remove                               takes it out
 *
 * It goes after the "Can I Run" link (else at the end of the first column). The pages are redrawn with a new date
 * (a footer change is a real change, and an old date kept the server's cached copy, Deploy 169).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
$url = ''; $label = ''; $move = 0;
foreach ($args as $a) {
    if (strpos($a, '--url=') === 0) $url = trim(substr($a, 6));
    if (strpos($a, '--label=') === 0) $label = trim(substr($a, 8), " \"'");
    // Deploy 182: --move-to-column=2 moves a link already in the footer to the end of that column (1 = first).
    if (strpos($a, '--move-to-column=') === 0) $move = max(1, (int)substr($a, 17));
}
if (!preg_match('#^/[a-z0-9/_-]*$#', $url)) exit("Give the page with --url=/path/\n");
$remove = in_array('--remove', $args, true);
if (!$remove && !$move && $label === '') exit("Give the link text with --label=\"...\"\n");

ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
require_once $ROOT . '/api/v1/cms/site-layer-lib.php';

$L = rocLayerFromDb($pdo, $ROOT);
$nav = (array)($L['nav'] ?? []);
if (!$nav) $nav = rocNavDefault();
$cols = (array)($nav['footer']['columns'] ?? []);
if (!$cols) exit("No footer columns found in CMS > Menus & footer.\n");

$has = false;
foreach ($cols as $c) foreach ((array)($c['links'] ?? []) as $l) if (($l['url'] ?? '') === $url) $has = true;
if ($move) {
    if (!$has) exit("The footer has no link to {$url}.\n");
    if ($move > count($cols)) exit("The footer has " . count($cols) . " columns.\n");
    $keep = null;   // the link as it is (its text and icon)
    foreach ($cols as &$c) $c['links'] = array_values(array_filter((array)($c['links'] ?? []), function ($l) use ($url, &$keep) { if (($l['url'] ?? '') === $url) { $keep = $l; return false; } return true; }));
    unset($c);
    $cols[$move - 1]['links'][] = $keep;
    echo '"' . ($keep['label'] ?? $url) . '" goes to the end of "' . ($cols[$move - 1]['title'] ?? 'column ' . $move) . "\"\n";
    if (!in_array('--apply', $args, true)) exit("Dry run: nothing changed. Run again with --apply.\n");
} elseif ($remove) {
    if (!$has) exit("The footer has no link to {$url}.\n");
    foreach ($cols as &$c) $c['links'] = array_values(array_filter((array)($c['links'] ?? []), function ($l) use ($url) { return ($l['url'] ?? '') !== $url; }));
    unset($c);
} else {
    if ($has) exit("The footer already links {$url}. Nothing to do.\n");
    $ci = 0; $at = null;
    foreach ($cols as $i => $c) foreach ((array)($c['links'] ?? []) as $j => $l) {
        if ($at === null && rtrim((string)($l['url'] ?? ''), '/') . '/' === '/can-i-run/') { $ci = $i; $at = $j + 1; }
    }
    if ($at === null) $at = count((array)($cols[0]['links'] ?? []));
    $links = (array)($cols[$ci]['links'] ?? []);
    $link = ['label' => $label, 'url' => $url];
    if (isset($links[0]['icon'])) $link['icon'] = 'gamepad';
    array_splice($links, $at, 0, [$link]);
    $cols[$ci]['links'] = $links;
    echo '"' . $label . '" goes in "' . ($cols[$ci]['title'] ?? 'column ' . ($ci + 1)) . '" at place ' . ($at + 1) . "\n";
    if (!in_array('--apply', $args, true)) exit("Dry run: nothing changed. Run again with --apply.\n");
}
$nav['footer']['columns'] = $cols;
$n = rocLayerSave($pdo, $ROOT, 'nav', $nav, null);
echo ($move ? 'Moved in' : ($remove ? 'Removed from' : 'Added to')) . " the footer; {$n} page(s) redrawn.\n";
