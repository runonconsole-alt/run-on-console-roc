<?php
/**
 * Run On Console — puts "FPS Reports" in the footer menu (Deploy 169).
 *
 *   php cli-fps-footer.php            dry run: shows where the link goes
 *   php cli-fps-footer.php --apply    adds it to CMS > Menus & footer (after "Can I Run") and redraws the pages
 *   php cli-fps-footer.php --remove   takes it out again
 *
 * The footer comes from CMS > Menus & footer (site_nav), so the link is added there; the owner can move or rename
 * it in the CMS like any other link. The pages keep their dates (an automatic save).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
require_once $ROOT . '/api/v1/cms/site-layer-lib.php';

const ROC_FPS_LINK = ['label' => 'FPS Reports', 'url' => '/can-i-run/fps-reports/'];
$L = rocLayerFromDb($pdo, $ROOT);
$nav = (array)($L['nav'] ?? []);
if (!$nav) $nav = rocNavDefault();
$cols = (array)($nav['footer']['columns'] ?? []);
if (!$cols) exit("No footer columns found in CMS > Menus & footer.\n");

$has = false;
foreach ($cols as $c) foreach ((array)($c['links'] ?? []) as $l) if (($l['url'] ?? '') === ROC_FPS_LINK['url']) $has = true;
$remove = in_array('--remove', $args, true);

if ($remove) {
    foreach ($cols as &$c) $c['links'] = array_values(array_filter((array)($c['links'] ?? []), function ($l) { return ($l['url'] ?? '') !== ROC_FPS_LINK['url']; }));
    unset($c);
} elseif ($has) {
    exit("The footer already has FPS Reports. Nothing to do.\n");
} else {
    // After the "Can I Run" (or checker) link, else at the end of the first column.
    $ci = 0; $at = null;
    foreach ($cols as $i => $c) foreach ((array)($c['links'] ?? []) as $j => $l) {
        if ($at === null && in_array(rtrim((string)($l['url'] ?? ''), '/') . '/', ['/can-i-run/', '/compatibility/'], true)) { $ci = $i; $at = $j + 1; }
    }
    if ($at === null) $at = count((array)($cols[0]['links'] ?? []));
    $links = (array)($cols[$ci]['links'] ?? []);
    $link = ROC_FPS_LINK;
    if (isset($links[0]['icon'])) $link['icon'] = 'gamepad';
    array_splice($links, $at, 0, [$link]);
    $cols[$ci]['links'] = $links;
    echo 'Goes in "' . ($cols[$ci]['title'] ?? 'column ' . ($ci + 1)) . '" at place ' . ($at + 1) . "\n";
}
$nav['footer']['columns'] = $cols;
if (!in_array('--apply', $args, true) && !$remove) exit("Dry run: nothing changed. Run again with --apply.\n");
$n = rocLayerSave($pdo, $ROOT, 'nav', $nav, null, null, true);
echo ($remove ? 'FPS Reports removed from' : 'FPS Reports added to') . " the footer; {$n} page(s) redrawn.\n";
