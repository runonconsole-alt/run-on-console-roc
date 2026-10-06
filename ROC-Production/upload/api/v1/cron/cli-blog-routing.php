<?php
/**
 * Run On Console — install / roll back the interim blog routing (Phase 1).
 *
 *   php cli-blog-routing.php                 dry run: shows what would change
 *   php cli-blog-routing.php --apply         makes the changes, with a backup
 *   php cli-blog-routing.php --rollback=DIR  restores the static files from a backup
 *
 * What --apply does:
 *   1. Saves the current prerendered blog listing and one blog post as layout
 *      templates in public_html/cms-templates/ (blocked from the web).
 *   2. Adds <script src="/roc-nav.js" defer></script> to every prerendered page,
 *      so a click to /blogs/... loads the server-rendered page instead of the copy
 *      inside the React bundle. Each changed file is backed up first.
 *
 * It does not touch .htaccess, the database, /cms, /api or /agent.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);                       // public_html
$HOME = dirname($ROOT);                            // /home2/runoncon
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

$TAG = '<script src="/roc-nav.js" defer></script>';
$SKIP_DIRS = ['cms', 'api', 'agent', 'cms-templates', 'uploads', 'images', 'assets', '.well-known',
              'cgi-bin', 'STEP-B', 'migrations', 'sitemaps', 'blogs'];

echo "Run On Console - blog routing installer\n";
echo "public_html: {$ROOT}\n\n";

/* -------------------------------------------------------------- rollback */
if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    if (!is_dir($dir) || !is_file($dir . '/MANIFEST.txt')) exit("Backup folder not found or has no MANIFEST.txt: {$dir}\n");
    $n = 0;
    foreach (file($dir . '/MANIFEST.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $rel) {
        $src = $dir . '/files/' . $rel;
        if (!is_file($src)) { echo "  missing in backup: {$rel}\n"; continue; }
        copy($src, $ROOT . '/' . $rel);
        $n++;
    }
    echo "Restored {$n} file(s) from {$dir}\n";
    echo "cms-templates/ was left in place (harmless). Remove the Phase 1 lines from .htaccess separately.\n";
    exit(0);
}

/* ---------------------------------------------------------- 1. templates */
$listSrc = $ROOT . '/blogs/index.html';
$postSrc = null;
foreach (glob($ROOT . '/blogs/*/index.html') ?: [] as $f) { $postSrc = $f; break; }

$problems = [];
foreach (['listing' => $listSrc, 'post' => $postSrc] as $k => $f) {
    if (!$f || !is_file($f)) { $problems[] = "No prerendered blog {$k} found ({$f})."; continue; }
    $h = file_get_contents($f);
    foreach (['<title', '</head>', '<main', '</main>'] as $needle) {
        if (stripos($h, $needle) === false) $problems[] = basename(dirname($f)) . "/index.html has no {$needle}";
    }
}
if ($problems) { echo "STOP:\n  - " . implode("\n  - ", $problems) . "\n"; exit(1); }

$tplDir = $ROOT . '/cms-templates';
echo "1) Layout templates -> cms-templates/\n";
echo "   blog-list.html  <- blogs/index.html\n";
echo "   blog-post.html  <- " . substr($postSrc, strlen($ROOT) + 1) . "\n";
if (is_file($tplDir . '/blog-list.html')) echo "   (templates already exist; they will be kept, not overwritten)\n";

/* ------------------------------------------------------ 2. roc-nav.js tag */
if (!is_file($ROOT . '/roc-nav.js')) { echo "\nSTOP: public_html/roc-nav.js is missing. Upload it first.\n"; exit(1); }

$targets = [];
$it = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS),
        function ($f, $key, $iter) use ($ROOT, $SKIP_DIRS) {
            if ($iter->hasChildren()) {
                $rel = substr($f->getPathname(), strlen($ROOT) + 1);
                return strpos($rel, '/') !== false || !in_array($rel, $SKIP_DIRS, true);
            }
            return substr($f->getFilename(), -5) === '.html';
        }
    )
);
foreach ($it as $f) {
    $rel = substr($f->getPathname(), strlen($ROOT) + 1);
    if (strpos($rel, '/') === false && !in_array($rel, ['index.html', '404.html'], true)) continue; // root: only real pages
    $h = file_get_contents($f->getPathname());
    if (stripos($h, '</head>') === false || strpos($h, '/assets/index-') === false) continue;   // not a React page
    if (strpos($h, 'roc-nav.js') !== false) continue;                                          // done already
    $targets[] = $rel;
}
sort($targets);
echo "\n2) Add roc-nav.js to " . count($targets) . " prerendered page(s)\n";
foreach (array_slice($targets, 0, 8) as $t) echo "   {$t}\n";
if (count($targets) > 8) echo "   ... and " . (count($targets) - 8) . " more\n";

if (!$apply) {
    echo "\nDRY RUN - nothing changed. Run again with --apply.\n";
    exit(0);
}

/* ---------------------------------------------------------------- apply */
$backup = $HOME . '/roc-static-backup-' . date('Ymd-His');
if (!mkdir($backup . '/files', 0700, true)) exit("Could not create backup folder {$backup}\n");

if (!is_dir($tplDir)) mkdir($tplDir, 0755);
file_put_contents($tplDir . '/.htaccess', "Require all denied\n");
if (!is_file($tplDir . '/blog-list.html')) copy($listSrc, $tplDir . '/blog-list.html');
if (!is_file($tplDir . '/blog-post.html')) copy($postSrc, $tplDir . '/blog-post.html');
echo "\nTemplates saved in cms-templates/\n";

$manifest = [];
$changed = 0;
foreach ($targets as $rel) {
    $path = $ROOT . '/' . $rel;
    $dest = $backup . '/files/' . $rel;
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0700, true);
    if (!copy($path, $dest)) { echo "  backup failed, skipped: {$rel}\n"; continue; }
    $h = file_get_contents($path);
    $new = preg_replace('#</head>#i', '  ' . $TAG . "\n  </head>", $h, 1);
    if ($new === null || $new === $h) { echo "  unchanged: {$rel}\n"; continue; }
    file_put_contents($path, $new);
    $manifest[] = $rel;
    $changed++;
}
file_put_contents($backup . '/MANIFEST.txt', implode("\n", $manifest) . "\n");

echo "Updated {$changed} page(s).\n";
echo "Backup: {$backup}\n";
echo "Rollback: php " . __FILE__ . " --rollback={$backup}\n";
