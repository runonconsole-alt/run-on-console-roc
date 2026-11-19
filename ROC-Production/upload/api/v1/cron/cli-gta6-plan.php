<?php
/**
 * Run On Console — GTA 6 month (Deploy 185).
 *
 *   php cli-gta6-plan.php           dry run: the GTA 6 topics and the publishing days it would set
 *   php cli-gta6-plan.php --apply   puts the topics first in the blog plan and writes on every day until AI ends
 *
 * Owner, 2026-11-18: GTA 6 is out on 19 November; from now to the end of the month the blog writer writes GTA 6
 * posts, two a day (12:00 and 18:00 Pakistan time) on every day of the week. The topics are "manual" plan topics, so
 * they go before everything else; each gets the checked GTA 6 fact sheet (gta6-lib.php). Topics already in the plan
 * are not added twice. --days=1,3,5,7 puts the old blog days back.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$ROOT = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$days = [1, 2, 3, 4, 5, 6, 7];
foreach ($args as $a) if (strpos($a, '--days=') === 0) $days = array_values(array_filter(array_map('intval', explode(',', substr($a, 7))), function ($d) { return $d >= 1 && $d <= 7; }));

ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
require_once $ROOT . '/api/v1/cms/blog-engine-lib.php';
require_once $ROOT . '/api/v1/gta6-lib.php';
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("No database connection.\n");

$plan = rocBlogPlan($pdo);
$pillars = array_column($plan['pillars'], 'id');
$have = array_flip(array_column($plan['topics'], 'id'));
$new = [];
foreach (rocGta6Topics() as [$pillar, $stage, $title, $kw, $sec]) {
    $id = 'gta6-' . substr(rocBlogSlug($title), 0, 70);
    if (isset($have[$id])) continue;
    if (!in_array($pillar, $pillars, true)) $pillar = 'games';
    $new[] = ['id' => $id, 'pillar' => $pillar, 'stage' => $stage, 'title' => $title, 'keyword' => $kw, 'secondary' => $sec,
              'products' => [], 'status' => 'planned', 'manual' => 1];
}
echo "Run On Console - GTA 6 blog plan\n\nNew GTA 6 topics: " . count($new) . " (already in the plan: " . (count(rocGta6Topics()) - count($new)) . ")\n";
foreach ($new as $i => $t) printf("  %2d. [%s] %s\n", $i + 1, $t['pillar'], $t['title']);
$names = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
echo "\nBlog days: " . implode(', ', array_map(function ($d) use ($names) { return $names[$d]; }, $days)) . ' at ' . rocBlogTime($pdo) . " Pakistan time\n";
echo 'The AI writes until ' . rocAiLastDay($pdo) . ".\n";
if (!$apply) exit("\nDry run: nothing changed. Run again with --apply.\n");

// Manual topics go first in plan order, so the GTA 6 ones go to the front in this order.
$plan['topics'] = array_merge($new, $plan['topics']);
rocBlogSet($pdo, 'blog_plan', $plan);
rocBlogSet($pdo, 'blog_days', $days);
rocBlogLog($pdo, ['type' => 'plan', 'message' => 'GTA 6: ' . count($new) . ' topics added first; blog days ' . implode(',', $days)]);
echo "\nDone: " . count($new) . " topics added at the front of the plan, blog days saved.\n";
echo "Next post: the next free time in CMS > Blog agent (it is written about 2 hours before).\n";
