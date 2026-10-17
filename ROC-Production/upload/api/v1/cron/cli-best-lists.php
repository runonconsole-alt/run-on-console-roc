<?php
/**
 * Run On Console — "Best of" lists on the category pages (Deploy 90).
 *
 *   php cli-best-lists.php              which categories have their list
 *   php cli-best-lists.php --write=N    writes N lists now (the scheduler writes 1 a run by itself)
 *   php cli-best-lists.php --slug=mice  writes (again) the list of one category
 *   php cli-best-lists.php --off | --on stops / restarts the automatic writing
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$ROOT = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
echo "Run On Console - best-of lists (category pages)\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/best-lib.php';
if (in_array('--off', $args, true) || in_array('--on', $args, true)) { rocBlogSet($pdo, 'best_auto', in_array('--on', $args, true) ? 'on' : 'off'); exit("Done.\n"); }
$write = 0; $slug = null;
foreach ($args as $a) { if (strpos($a, '--write=') === 0) $write = max(1, min(20, (int)substr($a, 8))); if (strpos($a, '--slug=') === 0) $slug = substr($a, 7); }
$todo = [];
if ($slug !== null) $todo = [$slug];
elseif ($write) {
    $texts = rocBestTexts($pdo);
    foreach ($pdo->query("SELECT slug FROM product_categories WHERE status = 'published' ORDER BY sort_rank, name")->fetchAll(PDO::FETCH_COLUMN) as $s)
        if (!isset($texts[$s]) && count(rocBestProducts($pdo, (string)$s)) >= 3 && count($todo) < $write) $todo[] = (string)$s;
}
foreach ($todo as $i => $s) {
    if ($i) sleep(15);
    $r = rocBestWrite($pdo, $s);
    echo '  ' . ($r['ok'] ? 'ok ' : '!  ') . $s . ($r['ok'] ? '' : ' - ' . $r['error']) . "\n";
}
$texts = rocBestTexts($pdo);
echo "\n";
foreach ($pdo->query("SELECT slug, name FROM product_categories WHERE status = 'published' ORDER BY sort_rank, name")->fetchAll(PDO::FETCH_ASSOC) as $c)
    echo '  ' . (isset($texts[$c['slug']]) ? 'list ' . date('Y-m-d', (int)$texts[$c['slug']]['t']) . ' (' . count($texts[$c['slug']]['picks']) . ' picks)' : 'waiting         ') . '  ' . $c['name'] . "\n";
