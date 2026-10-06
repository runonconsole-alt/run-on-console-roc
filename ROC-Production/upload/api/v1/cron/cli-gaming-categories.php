<?php
/**
 * Run On Console — serve the gaming platform pages (/categories/...) from the CMS database.
 *
 *   php cli-gaming-categories.php                 dry run: shows what would change
 *   php cli-gaming-categories.php --apply         makes the changes, with a backup
 *   php cli-gaming-categories.php --rollback=DIR  undoes an --apply using its backup
 *
 * What --apply does:
 *   1. Creates gaming_categories and gaming_devices and fills them from
 *      roc-gaming-catalog.json (the 15 platforms, their devices and FAQs that the
 *      website shows today). Existing rows are never overwritten.
 *   2. Saves the current prerendered /categories/ page and one platform page as
 *      layout templates in public_html/cms-templates/.
 *   3. .htaccess: routes /categories/ and /categories/{slug}/ and the categories
 *      sitemap to category-render.php ("ROC platforms BEGIN/END" markers).
 *      CMS guide pages at /categories/{cat}/{slug}/ are not touched.
 *
 * Upload first: category-render.php and roc-nav.js (public_html/), this file and
 * roc-gaming-catalog.json (public_html/api/v1/cron/).
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const MARK_BEGIN = '  # ROC platforms BEGIN (cli-gaming-categories.php)';
const MARK_END   = '  # ROC platforms END';
const HTACCESS_BLOCK = MARK_BEGIN . "\n"
    . "  # Gaming platform pages come from the CMS database (category-render.php)\n"
    . "  RewriteRule ^categories/index\\.html$ /categories/ [R=301,L,NC]\n"
    . "  RewriteRule ^categories/([a-z0-9-]+)/index\\.html$ /categories/\$1/ [R=301,L,NC]\n"
    . "  RewriteRule ^categories/$ category-render.php [L,QSA]\n"
    . "  RewriteRule ^categories/([a-z0-9-]+)/$ category-render.php?slug=\$1 [L,QSA]\n"
    . "  RewriteRule ^sitemaps/categories-sitemap\\.xml$ category-render.php?sitemap=1 [L]\n"
    . MARK_END . "\n";

echo "Run On Console - gaming platform pages from the CMS\n";
echo "public_html: {$ROOT}\n\n";

ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("STOP: could not connect to the database.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function tableExists(PDO $pdo, string $t): bool {
    try { $pdo->query("SELECT 1 FROM {$t} LIMIT 1"); return true; } catch (\Throwable $e) { return false; }
}

/* -------------------------------------------------------------- rollback */
if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    $man = is_file($dir . '/MANIFEST.json') ? json_decode((string)file_get_contents($dir . '/MANIFEST.json'), true) : null;
    if (!$man) exit("Backup folder not found or has no MANIFEST.json: {$dir}\n");
    if (!empty($man['htaccess']) && is_file($dir . '/files/.htaccess')) { copy($dir . '/files/.htaccess', $ROOT . '/.htaccess'); echo "Restored .htaccess\n"; }
    foreach ($man['templates_created'] ?? [] as $t) {
        if (is_file($ROOT . '/cms-templates/' . $t)) { unlink($ROOT . '/cms-templates/' . $t); echo "Removed cms-templates/{$t}\n"; }
    }
    foreach (['gaming_devices', 'gaming_categories'] as $t) {
        if (in_array($t, $man['tables_created'] ?? [], true)) { $pdo->exec("DROP TABLE {$t}"); echo "Dropped {$t}\n"; }
    }
    echo "\nDone.\n";
    exit(0);
}

/* ---------------------------------------------------------------- checks */
$problems = [];
foreach (['category-render.php', 'blog-render.php', 'roc-nav.js', '.htaccess'] as $f) {
    if (!is_file($ROOT . '/' . $f)) $problems[] = "public_html/{$f} is missing.";
}
if (is_file($ROOT . '/roc-nav.js') && strpos((string)file_get_contents($ROOT . '/roc-nav.js'), 'categories') === false) {
    $problems[] = 'public_html/roc-nav.js is the old version (it does not send /categories links to the server). Upload the new one.';
}
$catalog = json_decode((string)@file_get_contents(__DIR__ . '/roc-gaming-catalog.json'), true);
if (!is_array($catalog) || empty($catalog['categories'])) $problems[] = 'api/v1/cron/roc-gaming-catalog.json is missing or unreadable.';

$tplDir = $ROOT . '/cms-templates';
$templates = ['category-list.html' => $ROOT . '/categories/index.html', 'category-hub.html' => null];
foreach (($catalog['categories'] ?? []) as $c) {
    $f = $ROOT . '/categories/' . $c['slug'] . '/index.html';
    if (is_file($f)) { $templates['category-hub.html'] = $f; break; }
}
foreach ($templates as $name => $src) {
    if (is_file($tplDir . '/' . $name)) continue;
    if (!$src || !is_file($src)) { $problems[] = "No prerendered page to use for cms-templates/{$name}."; continue; }
    $h = (string)file_get_contents($src);
    foreach (['<title', '</head>', '<main', '</main>'] as $n) if (stripos($h, $n) === false) $problems[] = substr($src, strlen($ROOT) + 1) . " has no {$n}";
}

$ht = (string)@file_get_contents($ROOT . '/.htaccess');
$htDone = strpos($ht, MARK_BEGIN) !== false;
$anchor = null;
foreach (['  # Sitemaps are generated automatically', '  # Real file or folder'] as $a) if (strpos($ht, $a) !== false) { $anchor = $a; break; }
if (!$htDone && $anchor === null) $problems[] = '.htaccess has no place to add the platform rules.';

if ($problems) { echo "STOP:\n  - " . implode("\n  - ", $problems) . "\n"; exit(1); }

/* ------------------------------------------------------------------ plan */
$haveCats = tableExists($pdo, 'gaming_categories');
$haveDevs = tableExists($pdo, 'gaming_devices');
echo "1) Database\n";
echo '   gaming_categories: ' . ($haveCats ? 'exists (missing platforms get added)' : 'create with ' . count($catalog['categories']) . ' platforms') . "\n";
echo '   gaming_devices:    ' . ($haveDevs ? 'exists (missing devices get added)' : 'create with ' . count($catalog['devices']) . ' devices') . "\n";
echo "2) Layout templates -> cms-templates/\n";
foreach ($templates as $name => $src) echo "   {$name}  " . (is_file($tplDir . '/' . $name) ? '(exists, kept)' : '<- ' . substr((string)$src, strlen($ROOT) + 1)) . "\n";
echo '3) .htaccess: ' . ($htDone ? "platform rules already present\n" : "add platform rules before \"" . trim($anchor) . "\"\n");

if (!$apply) { echo "\nDRY RUN - nothing changed. Run again with --apply.\n"; exit(0); }

/* ----------------------------------------------------------------- apply */
$backup = $HOME . '/roc-platforms-backup-' . date('Ymd-His');
if (!mkdir($backup . '/files', 0700, true)) exit("Could not create backup folder {$backup}\n");
$man = ['created' => date('c'), 'htaccess' => false, 'templates_created' => [], 'tables_created' => []];
$save = function () use (&$man, $backup) { file_put_contents($backup . '/MANIFEST.json', json_encode($man, JSON_PRETTY_PRINT)); };
copy($ROOT . '/.htaccess', $backup . '/files/.htaccess');
$save();
echo "\nBackup: {$backup}\n";

if (!$haveCats) {
    $pdo->exec('CREATE TABLE gaming_categories (
        slug VARCHAR(80) NOT NULL PRIMARY KEY,
        title VARCHAR(160) NOT NULL,
        description TEXT NULL,
        image VARCHAR(500) NULL,
        badge VARCHAR(120) NULL,
        era VARCHAR(120) NULL,
        focus_json TEXT NULL,
        faq_json TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT \'published\',
        sort_rank INT NOT NULL DEFAULT 0,
        meta_title VARCHAR(255) NULL,
        meta_description VARCHAR(500) NULL,
        is_noindex TINYINT NOT NULL DEFAULT 0,
        created_at DATETIME NULL,
        updated_at DATETIME NULL,
        content_modified_at DATETIME NULL
    )');
    $man['tables_created'][] = 'gaming_categories'; $save();
}
if (!$haveDevs) {
    $pdo->exec('CREATE TABLE gaming_devices (
        id VARCHAR(80) NOT NULL PRIMARY KEY,
        category_slug VARCHAR(80) NOT NULL,
        name VARCHAR(200) NOT NULL,
        year VARCHAR(40) NULL,
        era VARCHAR(80) NULL,
        type VARCHAR(120) NULL,
        specs VARCHAR(500) NULL,
        status_label VARCHAR(80) NULL,
        iconic_games_json TEXT NULL,
        image VARCHAR(500) NULL,
        status VARCHAR(20) NOT NULL DEFAULT \'published\',
        sort_rank INT NOT NULL DEFAULT 0,
        created_at DATETIME NULL,
        updated_at DATETIME NULL,
        content_modified_at DATETIME NULL
    )');
    $man['tables_created'][] = 'gaming_devices'; $save();
}

$now = date('Y-m-d H:i:s');
$faq = json_encode($catalog['faqs'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$haveC = array_flip($pdo->query('SELECT slug FROM gaming_categories')->fetchAll(PDO::FETCH_COLUMN));
$insC = $pdo->prepare('INSERT INTO gaming_categories (slug, title, description, image, badge, era, focus_json, faq_json, status, sort_rank, meta_title, meta_description, created_at, updated_at, content_modified_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'published\', ?, ?, ?, ?, ?, ?)');
$addedC = 0;
$pdo->beginTransaction();
foreach ($catalog['categories'] as $c) {
    if (isset($haveC[$c['slug']])) continue;
    // lastmod starts at the date of the page visitors see today, not the install date.
    $f = $ROOT . '/categories/' . $c['slug'] . '/index.html';
    $since = is_file($f) ? date('Y-m-d H:i:s', (int)filemtime($f)) : null;
    // Keep the title and description Google already has for this page.
    $mt = $md = '';
    if (is_file($f)) {
        $h = (string)file_get_contents($f);
        if (preg_match('#<title>(.*?)</title>#is', $h, $m1)) $mt = html_entity_decode(trim($m1[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('#<meta\s+name="description"\s+content="([^"]*)"#i', $h, $m2)) $md = html_entity_decode($m2[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $insC->execute([$c['slug'], $c['title'], $c['description'] ?? '', $c['image'] ?? '', $c['badge'] ?? '', $c['era'] ?? '',
                    json_encode($c['focus'] ?? [], JSON_UNESCAPED_UNICODE), $faq, (int)$c['sort_rank'], $mt, $md, $now, $now, $since]);
    $addedC++;
}
$haveD = array_flip($pdo->query('SELECT id FROM gaming_devices')->fetchAll(PDO::FETCH_COLUMN));
$insD = $pdo->prepare('INSERT INTO gaming_devices (id, category_slug, name, year, era, type, specs, status_label, iconic_games_json, image, status, sort_rank, created_at, updated_at, content_modified_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'published\', ?, ?, ?, NULL)');
$addedD = 0;
foreach ($catalog['devices'] as $d) {
    if (isset($haveD[$d['id']])) continue;
    $insD->execute([$d['id'], $d['category_slug'], $d['name'], $d['year'] ?? '', $d['era'] ?? '', $d['type'] ?? '', $d['specs'] ?? '',
                    $d['status_label'] ?? '', json_encode($d['iconic_games'] ?? [], JSON_UNESCAPED_UNICODE), $d['image'] ?? '', (int)$d['sort_rank'], $now, $now]);
    $addedD++;
}
$pdo->commit();
echo "Platforms added: {$addedC}, devices added: {$addedD}\n";

if (!is_dir($tplDir)) mkdir($tplDir, 0755);
if (!is_file($tplDir . '/.htaccess')) file_put_contents($tplDir . '/.htaccess', "Require all denied\n");
foreach ($templates as $name => $src) {
    if (is_file($tplDir . '/' . $name)) continue;
    copy($src, $tplDir . '/' . $name);
    $man['templates_created'][] = $name; $save();
}
echo "Templates saved in cms-templates/\n";

if (!$htDone) {
    file_put_contents($ROOT . '/.htaccess.roc-tmp', str_replace($anchor, HTACCESS_BLOCK . "\n" . $anchor, $ht));
    rename($ROOT . '/.htaccess.roc-tmp', $ROOT . '/.htaccess');
    $man['htaccess'] = true; $save();
    echo ".htaccess updated\n";
}

echo "\nDone.\nCheck: https://runonconsole.com/categories/  and  https://runonconsole.com/sitemaps/categories-sitemap.xml\n";
echo "Rollback: php " . __FILE__ . " --rollback={$backup}\n";
