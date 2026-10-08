<?php
/**
 * Run On Console — refresh the layout templates used by the server-rendered pages.
 *
 *   php cli-refresh-templates.php                 dry run: shows what would change
 *   php cli-refresh-templates.php --apply         copies fresh templates, with a backup
 *   php cli-refresh-templates.php --rollback=DIR  puts the previous templates back
 *
 * Blogs, CMS pages, products and gaming platforms are rendered by PHP using a
 * prerendered page as their layout (header, footer, CSS). Those layouts are copied
 * once into public_html/cms-templates/. After uploading a new website build (for
 * example a changed header menu), run this so the PHP pages get the new layout too.
 *
 * It touches public_html/cms-templates/*.html, then puts the CMS announcement bar,
 * site code and meta overrides back into the built pages (see cms/site-layer-lib.php).
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);
$tplDir = $ROOT . '/cms-templates';

echo "Run On Console - refresh page templates\n";
echo "public_html: {$ROOT}\n\n";

if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    $files = glob($dir . '/*.html') ?: [];
    if (!$files) exit("No templates found in {$dir}\n");
    foreach ($files as $f) { copy($f, $tplDir . '/' . basename($f)); echo "Restored cms-templates/" . basename($f) . "\n"; }
    echo "\nDone.\n";
    exit(0);
}

// After the templates: put the CMS announcement bar, site code and meta overrides back
// into the built pages (a deploy replaces those files). Runs however this script ends.
register_shutdown_function(function () use ($ROOT, $apply) {
    $lib = $ROOT . '/api/v1/cms/site-layer-lib.php';
    if (!is_file($lib)) return;
    require_once $lib;
    $n = rocLayerApplyFiles($ROOT, rocLayerLoad($ROOT), null, $apply);
    echo "\nCMS announcement, site code and metas: " . ($n === 0 ? "all built pages are current.\n"
        : ($apply ? "re-applied to {$n} built page(s).\n" : "{$n} built page(s) need them - run with --apply.\n"));
});

function firstPage(string $root, string $pattern, array $skip = []): ?string {
    foreach (glob($root . '/' . $pattern, GLOB_NOSORT) ?: [] as $f) {
        $rel = substr($f, strlen($root) + 1);
        foreach ($skip as $s) if (strpos($rel, $s) === 0) continue 2;
        if (is_file($f)) return $f;
    }
    return null;
}

// template => prerendered page it is copied from (only templates that already exist are refreshed)
$sources = [
    'blog-list.html'        => $ROOT . '/blogs/index.html',
    'blog-post.html'        => firstPage($ROOT, 'blogs/*/index.html'),
    'product-list.html'     => $ROOT . '/products/index.html',
    'product-category.html' => firstPage($ROOT, 'products/category/*/index.html'),
    'product-page.html'     => firstPage($ROOT, 'products/*/index.html', ['products/category/']),
    'category-list.html'    => $ROOT . '/categories/index.html',
    'category-hub.html'     => firstPage($ROOT, 'categories/*/index.html'),
];

$plan = [];
$problems = [];
foreach ($sources as $name => $src) {
    if (!is_file($tplDir . '/' . $name)) continue;                       // not installed here: leave alone
    if (!$src || !is_file($src)) { $problems[] = "No prerendered page found for {$name}."; continue; }
    $h = (string)file_get_contents($src);
    foreach (['<title', '</head>', '<main', '</main>'] as $n) {
        if (stripos($h, $n) === false) $problems[] = substr($src, strlen($ROOT) + 1) . " has no {$n}";
    }
    if (strpos($h, '/assets/index-') === false) $problems[] = substr($src, strlen($ROOT) + 1) . ' does not look like a website page.';
    $same = md5_file($src) === md5_file($tplDir . '/' . $name);
    $plan[$name] = [$src, $same];
}
if ($problems) { echo "STOP:\n  - " . implode("\n  - ", $problems) . "\n"; exit(1); }
if (!$plan) exit("No templates installed in cms-templates/ - nothing to refresh.\n");

foreach ($plan as $name => [$src, $same]) {
    echo '  ' . str_pad($name, 24) . ($same ? '(already current)' : '<- ' . substr($src, strlen($ROOT) + 1)) . "\n";
}
$todo = array_filter($plan, function ($p) { return !$p[1]; });
if (!$todo) { echo "\nAll templates are current.\n"; exit(0); }
if (!$apply) { echo "\nDRY RUN - nothing changed. Run again with --apply.\n"; exit(0); }

$backup = $HOME . '/roc-templates-backup-' . date('Ymd-His');
if (!mkdir($backup, 0700, true)) exit("Could not create backup folder {$backup}\n");
foreach ($todo as $name => [$src]) {
    copy($tplDir . '/' . $name, $backup . '/' . $name);
    copy($src, $tplDir . '/' . $name . '.roc-tmp');
    rename($tplDir . '/' . $name . '.roc-tmp', $tplDir . '/' . $name);
}
echo "\nUpdated " . count($todo) . " template(s).\nBackup: {$backup}\n";
echo "Rollback: php " . __FILE__ . " --rollback={$backup}\n";
