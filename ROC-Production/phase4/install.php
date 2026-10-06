<?php
/**
 * Run On Console — Phase 4 installer: new product catalog (122 products from the
 * product master sheet), rebuilt website pages, social profiles, initials avatars.
 *
 *   php ~/roc-phase4/install.php                     dry run (shows what would change)
 *   php ~/roc-phase4/install.php --apply             install, with a full backup first
 *   php ~/roc-phase4/install.php --rollback=DIR      undo, using the backup folder it printed
 *
 * Options for --apply:
 *   --keep-avatars      do not move the old avatar SVG files (images/avatars/) to the backup
 *   --root=PATH         public_html folder (default: ~/public_html)
 *
 * What --apply does (every replaced or moved file goes to ~/roc-phase4-backup-<time>/ first):
 *   1. Copies the rebuilt website pages and assets (site/) into public_html.
 *      Blogs are not touched: they stay served by blog-render.php from the CMS.
 *   2. Updates the blog/CMS-page layout templates (cms-templates/) to the new header/footer.
 *   3. Copies the updated CMS files (Social & Amazon tag, Products list, dashboard card).
 *   4. Old product pages that no longer exist get a permanent (301) redirect to the
 *      matching category or product, so Google and old links do not hit a 404.
 *   5. Database: saves the old products table as products_bak_phase4, then replaces
 *      the products with the 122 new ones (CMS list, activity feed, profiles).
 *   6. Saves the social profiles (Facebook, Instagram, Pinterest, X) and publishes /roc-site.json.
 *   7. Re-applies the SEO titles/descriptions saved in CMS → Website pages & SEO.
 *   8. Moves the old avatar pictures (images/avatars/*.svg) to the backup; the website
 *      now shows the first two letters of the name instead.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$PKG = __DIR__;
$args = array_slice($argv, 1);
$opt = function (string $name) use ($args): ?string {
    foreach ($args as $a) if (strpos($a, "--{$name}=") === 0) return substr($a, strlen($name) + 3);
    return null;
};
$apply = in_array('--apply', $args, true);
$keepAvatars = in_array('--keep-avatars', $args, true);
$rollback = $opt('rollback');
$HOME = getenv('HOME') ?: dirname($PKG);
$ROOT = rtrim($opt('root') ?? ($HOME . '/public_html'), '/');

if (!is_file($ROOT . '/api/v1/config.php')) exit("STOP: {$ROOT}/api/v1/config.php not found. Use --root=/path/to/public_html\n");
require_once $ROOT . '/api/v1/config.php';          // before any output (it sends HTTP headers)
echo "Run On Console - Phase 4 installer (products from the sheet)\n";
echo "public_html: {$ROOT}\n\n";

const SOCIAL_DEFAULT = [
    'facebook'  => 'https://www.facebook.com/profile.php?id=61594369295787',
    'instagram' => 'https://www.instagram.com/runonconsole/',
    'pinterest' => 'https://www.pinterest.com/runonconsole/',
    'twitter'   => 'https://x.com/RunOnConsole',
];
const BAK_TABLE = 'products_bak_phase4';

function db(string $root): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = getDBConnection();
    if (!$pdo) exit("STOP: database connection failed.\n");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function listFiles(string $dir): array {
    $out = [];
    if (!is_dir($dir)) return $out;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) $out[] = str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
    sort($out);
    return $out;
}

function ensureDir(string $d): void { if (!is_dir($d) && !mkdir($d, 0755, true) && !is_dir($d)) exit("STOP: cannot create {$d}\n"); }

/* ================================================================ rollback */
if ($rollback !== null) {
    $B = rtrim($rollback, '/');
    if (!is_file($B . '/MANIFEST.json')) exit("Backup not found (no MANIFEST.json): {$B}\n");
    $man = json_decode((string)file_get_contents($B . '/MANIFEST.json'), true);
    $n = 0;
    foreach ($man['replaced'] ?? [] as $rel) {                      // put back replaced files
        if (is_file($B . '/files/' . $rel)) { ensureDir(dirname($ROOT . '/' . $rel)); copy($B . '/files/' . $rel, $ROOT . '/' . $rel); $n++; }
    }
    foreach ($man['created'] ?? [] as $rel) {
        if (!is_file($ROOT . '/' . $rel)) continue;
        unlink($ROOT . '/' . $rel); $n++;
        for ($d = dirname($ROOT . '/' . $rel); strlen($d) > strlen($ROOT) && @rmdir($d); $d = dirname($d));   // drop folders left empty
    }
    foreach ($man['moved_dirs'] ?? [] as $rel) {                    // old product pages, avatars
        $dst = $ROOT . '/' . $rel;
        if (is_dir($B . '/moved/' . $rel)) {
            if (is_dir($dst)) { foreach (listFiles($dst) as $f) unlink($dst . '/' . $f); @rmdir($dst); }
            ensureDir(dirname($dst));
            rename($B . '/moved/' . $rel, $dst); $n++;
        }
    }
    foreach ($man['moved_files'] ?? [] as $rel) {
        if (is_file($B . '/moved/' . $rel)) { ensureDir(dirname($ROOT . '/' . $rel)); rename($B . '/moved/' . $rel, $ROOT . '/' . $rel); $n++; }
    }
    echo "Files restored: {$n}\n";
    $pdo = db($ROOT);
    if (!empty($man['db_products']) && $pdo->query("SHOW TABLES LIKE '" . BAK_TABLE . "'")->fetchColumn()) {
        $pdo->exec('DELETE FROM products');
        $pdo->exec('INSERT INTO products SELECT * FROM ' . BAK_TABLE);
        echo "Products table restored from " . BAK_TABLE . " (" . $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() . " rows)\n";
    }
    if (array_key_exists('social_links_before', $man)) {
        if ($man['social_links_before'] === null) $pdo->prepare("DELETE FROM cms_settings WHERE setting_key = 'social_links'")->execute();
        else $pdo->prepare("UPDATE cms_settings SET setting_value = ? WHERE setting_key = 'social_links'")->execute([$man['social_links_before']]);
        if (is_file($B . '/files/roc-site.json')) copy($B . '/files/roc-site.json', $ROOT . '/roc-site.json');
        elseif (in_array('roc-site.json', $man['created'] ?? [], true) && is_file($ROOT . '/roc-site.json')) unlink($ROOT . '/roc-site.json');
        echo "Social links restored.\n";
    }
    echo "\nRollback done.\n";
    exit(0);
}

/* ================================================================ plan */
$catalog = json_decode((string)file_get_contents($PKG . '/data/catalog.json'), true);
$redirects = json_decode((string)file_get_contents($PKG . '/data/redirects.json'), true);
$products = $catalog['products'] ?? [];
if (count($products) < 100) exit("STOP: data/catalog.json looks wrong (" . count($products) . " products).\n");
$newSlugs = array_flip(array_column($products, 'slug'));

// Sanity: the new pages must match the catalog.
$missing = [];
foreach ($products as $p) if (!is_file($PKG . '/site/products/' . $p['slug'] . '/index.html')) $missing[] = $p['slug'];
if ($missing) exit("STOP: package incomplete, missing pages for: " . implode(', ', array_slice($missing, 0, 5)) . "\n");

$copies = [];                                            // rel in public_html => source
foreach (listFiles($PKG . '/site') as $rel) $copies[$rel] = $PKG . '/site/' . $rel;
foreach (listFiles($PKG . '/server') as $rel) $copies[$rel] = $PKG . '/server/' . $rel;
foreach (listFiles($PKG . '/templates') as $rel) $copies['cms-templates/' . $rel] = $PKG . '/templates/' . $rel;
$replace = []; $create = [];
foreach ($copies as $rel => $src) {
    if (!is_file($ROOT . '/' . $rel)) $create[] = $rel;
    elseif (sha1_file($ROOT . '/' . $rel) !== sha1_file($src)) $replace[] = $rel;
}

// Old product pages: folders in products/ that are not in the new catalog.
$oldDirs = [];
foreach (glob($ROOT . '/products/*', GLOB_ONLYDIR) ?: [] as $d) {
    $slug = basename($d);
    if ($slug === 'category' || isset($newSlugs[$slug])) continue;
    if (!is_file($d . '/index.html')) continue;                       // already redirected
    $oldDirs[$slug] = $redirects[$slug] ?? '/products/';
}

$avatarFiles = $keepAvatars ? [] : array_map(function ($f) use ($ROOT) { return substr($f, strlen($ROOT) + 1); },
    glob($ROOT . '/images/avatars/*.svg') ?: []);

$pdo = db($ROOT);
$hasProducts = (bool)$pdo->query("SHOW TABLES LIKE 'products'")->fetchColumn();
$oldCount = $hasProducts ? (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() : 0;
$bakExists = (bool)$pdo->query("SHOW TABLES LIKE '" . BAK_TABLE . "'")->fetchColumn();
$socialBefore = null;
try {
    $st = $pdo->prepare("SELECT setting_value FROM cms_settings WHERE setting_key = 'social_links'");
    $st->execute(); $v = $st->fetchColumn(); $socialBefore = $v === false ? null : (string)$v;
} catch (\Throwable $e) { exit("STOP: cms_settings table missing. Run the Phase 3 upgrade first.\n"); }
$seoRows = [];
try { $seoRows = $pdo->query('SELECT path, meta_title, meta_description, og_image FROM cms_page_seo')->fetchAll(PDO::FETCH_ASSOC); }
catch (\Throwable $e) { $seoRows = []; }

// CMS pages (Pages section) that show product cards for products that will be gone.
$pageWarn = [];
try {
    foreach ($pdo->query("SELECT title, product_ids FROM pages WHERE product_ids IS NOT NULL AND product_ids <> '' AND product_ids <> '[]'") as $r) {
        $ids = json_decode((string)$r['product_ids'], true);
        if (!is_array($ids)) $ids = preg_split('/[\s,]+/', (string)$r['product_ids']);
        $gone = array_values(array_filter($ids, function ($id) use ($newSlugs) { return $id !== '' && !isset($newSlugs[(string)$id]); }));
        if ($gone) $pageWarn[] = $r['title'] . ' (' . count($gone) . ' old product' . (count($gone) > 1 ? 's' : '') . ')';
    }
} catch (\Throwable $e) { /* no pages table or column: nothing to check */ }

echo "1) Website pages and files:  " . count($create) . " new, " . count($replace) . " replaced, "
   . (count($copies) - count($create) - count($replace)) . " already up to date\n";
foreach (array_slice(array_merge($replace, $create), 0, 6) as $r) echo "     {$r}\n";
echo "2) Layout templates:           cms-templates/blog-list.html, blog-post.html\n";
echo "3) CMS files:                  cms/index.html, api/v1/cms/social.php, social-lib.php, products.php\n";
echo "4) Old product pages -> 301:   " . count($oldDirs) . "\n";
foreach (array_slice($oldDirs, 0, 5, true) as $s => $t) echo "     /products/{$s}/  ->  {$t}\n";
if (count($oldDirs) > 5) echo "     ... and " . (count($oldDirs) - 5) . " more\n";
echo "5) Database products:          {$oldCount} old -> " . count($products) . " new"
   . ($bakExists ? "   (backup table " . BAK_TABLE . " already exists, it is kept as the original)" : "   (old rows saved in " . BAK_TABLE . ")") . "\n";
echo "6) Social profiles:            Facebook, Instagram, Pinterest, X" . ($socialBefore !== null ? " (other saved links are kept)" : "") . "\n";
echo "7) SEO overrides re-applied:   " . count($seoRows) . "\n";
echo "8) Old avatar SVGs moved:      " . count($avatarFiles) . ($keepAvatars ? " (--keep-avatars)" : "") . "\n";

if ($pageWarn) {
    echo "\nNOTE: these CMS pages show product cards for old products; after install, open them in CMS → Pages\n"
       . "and pick the new products again:\n  - " . implode("\n  - ", $pageWarn) . "\n";
}
if (!$apply) { echo "\nDRY RUN - nothing changed. Run again with --apply.\n"; exit(0); }

/* ================================================================ apply */
$B = $HOME . '/roc-phase4-backup-' . date('Ymd-His');
ensureDir($B . '/files'); ensureDir($B . '/moved');
chmod($B, 0700);
$man = ['created' => [], 'replaced' => [], 'moved_dirs' => [], 'moved_files' => [], 'db_products' => false];
$saveManifest = function () use (&$man, $B) { file_put_contents($B . '/MANIFEST.json', json_encode($man, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); };
$saveManifest();
echo "\nBackup folder: {$B}\n";

// 1-3. copy files (site first, then templates and server files)
$done = 0;
foreach ($copies as $rel => $src) {
    $dst = $ROOT . '/' . $rel;
    $exists = is_file($dst);
    if ($exists && sha1_file($dst) === sha1_file($src)) continue;
    if ($exists) {
        ensureDir(dirname($B . '/files/' . $rel));
        if (!copy($dst, $B . '/files/' . $rel)) exit("STOP: backup failed for {$rel}\n");
        $man['replaced'][] = $rel;
    } else {
        $man['created'][] = $rel;
    }
    ensureDir(dirname($dst));
    $tmp = $dst . '.tmp-roc4';
    if (!copy($src, $tmp) || !rename($tmp, $dst)) { @unlink($tmp); $saveManifest(); exit("STOP: could not write {$rel}. Nothing else changed; use --rollback={$B}\n"); }
    $done++;
}
if (!is_file($ROOT . '/cms-templates/.htaccess')) { file_put_contents($ROOT . '/cms-templates/.htaccess', "Require all denied\n"); $man['created'][] = 'cms-templates/.htaccess'; }
$saveManifest();
echo "  files written: {$done}\n";

// 4. old product pages -> redirect
foreach ($oldDirs as $slug => $target) {
    $rel = 'products/' . $slug;
    ensureDir($B . '/moved/products');
    if (!rename($ROOT . '/' . $rel, $B . '/moved/' . $rel)) { echo "  could not move {$rel}, skipped\n"; continue; }
    $man['moved_dirs'][] = $rel;
    ensureDir($ROOT . '/' . $rel);
    $php = "<?php\n// Old product page (replaced " . date('Y-m-d') . "). Permanent redirect.\n"
         . "header('Location: https://runonconsole.com" . addslashes($target) . "', true, 301);\nexit;\n";
    file_put_contents($ROOT . '/' . $rel . '/index.php', $php);
    $man['created'][] = $rel . '/index.php';
}
$saveManifest();
echo "  old product pages redirected: " . count($oldDirs) . "\n";

// 5. database products
if ($hasProducts) {
    if (!$bakExists) $pdo->exec('CREATE TABLE ' . BAK_TABLE . ' LIKE products');
    if (!$bakExists) $pdo->exec('INSERT INTO ' . BAK_TABLE . ' SELECT * FROM products');
    $cols = array_map('strtolower', $pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_COLUMN));
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM products');
        foreach ($products as $p) {
            $row = [
                'id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug'], 'category' => $p['category'],
                'short_desc' => $p['shortDesc'], 'summary' => $p['shortDesc'],
                'specs_json' => json_encode($p['specs'], JSON_UNESCAPED_UNICODE),
                'price' => null, 'price_state' => 'unknown', 'rating' => null, 'image' => $p['image'],
                'pros' => '[]', 'cons' => '[]',
                'affiliate_amazon' => $p['affiliateLinks']['amazon'], 'affiliate_bestbuy' => null, 'affiliate_official' => null,
                'affiliate_links_json' => json_encode($p['affiliateLinks'], JSON_UNESCAPED_SLASHES),
                'meta_title' => $p['title'] . ': Specs & Where to Buy | Run On Console',
                'meta_description' => $p['shortDesc'], 'is_noindex' => 0,
                'status' => 'published', 'draft_status' => 'none', 'version' => 1,
                'image_alt' => $p['title'],
            ];
            $row = array_intersect_key($row, array_flip($cols));
            $names = array_keys($row);
            $sql = 'INSERT INTO products (`' . implode('`, `', $names) . '`' . (in_array('created_at', $cols, true) ? ', created_at' : '')
                 . (in_array('updated_at', $cols, true) ? ', updated_at' : '') . (in_array('content_modified_at', $cols, true) ? ', content_modified_at' : '')
                 . ') VALUES (' . implode(', ', array_fill(0, count($names), '?'))
                 . (in_array('created_at', $cols, true) ? ', NOW()' : '') . (in_array('updated_at', $cols, true) ? ', NOW()' : '')
                 . (in_array('content_modified_at', $cols, true) ? ', NOW()' : '') . ')';
            $pdo->prepare($sql)->execute(array_values($row));
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        echo "  DATABASE: products not changed (" . $e->getMessage() . ")\n";
        echo "  The website pages are installed; the CMS product list still shows the old products.\n";
        goto social;
    }
    $man['db_products'] = true;
    $saveManifest();
    echo "  products in database: " . $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() . "\n";
}

social:
// 6. social profiles + /roc-site.json
require_once $ROOT . '/api/v1/cms/social-lib.php';
$man['social_links_before'] = $socialBefore;
if (is_file($ROOT . '/roc-site.json')) { copy($ROOT . '/roc-site.json', $B . '/files/roc-site.json'); }
else $man['created'][] = 'roc-site.json';
$links = rocSocialLoad($pdo) ?? array_fill_keys(array_keys(ROC_SOCIAL_PLATFORMS), '');
foreach (SOCIAL_DEFAULT as $k => $u) $links[$k] = $u;
rocSettingSet($pdo, 'social_links', json_encode($links, JSON_UNESCAPED_SLASHES), null);
rocSitePublish($pdo);
$saveManifest();
echo "  social profiles saved; roc-site.json published\n";

// 7. SEO overrides from the CMS on the rebuilt pages
$MARK = '<!--roc-seo-->';
$setMeta = function (string $html, string $attr, string $key, string $value): string {
    $esc = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $re = '#<meta\b[^>]*\b' . $attr . '=["\']' . preg_quote($key, '#') . '["\'][^>]*>#i';
    if (preg_match($re, $html, $m)) {
        $tag = $m[0];
        $newTag = preg_match('#\bcontent=["\'][^"\']*["\']#i', $tag)
            ? preg_replace('#\bcontent=["\'][^"\']*["\']#i', 'content="' . str_replace('$', '\$', $esc) . '"', $tag, 1)
            : str_replace('<meta', '<meta content="' . $esc . '"', $tag);
        return str_replace($tag, $newTag, $html);
    }
    return preg_replace('#</head>#i', '    <meta ' . $attr . '="' . $key . '" content="' . str_replace('$', '\$', $esc) . "\" />\n  </head>", $html, 1);
};
$applied = 0;
foreach ($seoRows as $o) {
    $path = (string)$o['path'];
    if (!preg_match('#^/(?:[a-z0-9][a-z0-9-]*/)*$#', $path)) continue;
    $file = $ROOT . $path . 'index.html';
    if (!is_file($file)) continue;
    $html = (string)file_get_contents($file);
    if (strpos($html, $MARK) !== false) continue;                     // not rebuilt, still patched
    ensureDir($ROOT . '/cms-templates/seo-originals');
    file_put_contents($ROOT . '/cms-templates/seo-originals/' . sha1($path) . '.html', $html);   // new original for "reset"
    $o = array_map(function ($v) { return (string)$v; }, $o);
    if ($o['meta_title'] !== '') {
        $t = htmlspecialchars($o['meta_title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('#<title>.*?</title>#is', '<title>' . str_replace('$', '\$', $t) . '</title>', $html, 1);
        foreach ([['name', 'title'], ['property', 'og:title'], ['name', 'twitter:title']] as [$a, $k]) $html = $setMeta($html, $a, $k, $o['meta_title']);
    }
    if ($o['meta_description'] !== '') {
        foreach ([['name', 'description'], ['property', 'og:description'], ['name', 'twitter:description']] as [$a, $k]) $html = $setMeta($html, $a, $k, $o['meta_description']);
    }
    if ($o['og_image'] !== '') {
        $img = $o['og_image'][0] === '/' ? 'https://runonconsole.com' . $o['og_image'] : $o['og_image'];
        foreach ([['property', 'og:image'], ['name', 'twitter:image']] as [$a, $k]) $html = $setMeta($html, $a, $k, $img);
    }
    $map = [$path => array_filter(['title' => $o['meta_title'], 'description' => $o['meta_description']])];
    $script = $MARK . '<script>window.__ROC_SEO_MAP__=Object.assign(window.__ROC_SEO_MAP__||{},'
            . str_replace('</', '<\/', json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . ');</script>';
    $html = preg_replace('#</head>#i', '  ' . str_replace('$', '\$', $script) . "\n  </head>", $html, 1);
    file_put_contents($file, $html);
    $applied++;
}
echo "  SEO overrides re-applied: {$applied}\n";

// 8. avatars
foreach ($avatarFiles as $rel) {
    ensureDir(dirname($B . '/moved/' . $rel));
    if (rename($ROOT . '/' . $rel, $B . '/moved/' . $rel)) $man['moved_files'][] = $rel;
}
$saveManifest();
echo "  avatar SVGs moved to backup: " . count($man['moved_files']) . "\n";

echo "\nDone.\n";
echo "Undo everything with:\n  php {$PKG}/install.php --rollback={$B}\n";
