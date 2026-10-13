<?php
/**
 * Run On Console — which pictures the website really uses.
 *
 * Shared by api/v1/cron/cli-media-cleanup.php (moves unused pictures to a trash folder) and
 * api/v1/cms/media-usage.php (the CMS Media library's "used / not used" badges), so both always agree.
 *
 * A picture counts as used when its address appears anywhere: in any database table (products, blogs,
 * news, pages, platforms, users, settings...), in a page file (*.html), in the site's JavaScript and
 * CSS (assets, roc-nav.js), in site-layer.json or in the CMS files. Addresses are compared without
 * "?v=" and URL encoding. Not counted: the media library's own list, backups, logs and installer data.
 *
 * Product name cards (/images/products/{slug}.webp) and share cards (/images/products/share/{slug}.jpg)
 * are only shown for a published product without a real photo, so that is the only time they count.
 */
if (defined('ROC_MEDIA_REFS_LIB')) return;
define('ROC_MEDIA_REFS_LIB', 1);

/** Every picture address the site refers to, as a set: ['/uploads/x.webp' => 1, ...]. */
function rocMediaRefs(PDO $pdo, string $root): array {
    $refs = [];
    $grab = function (string $text) use (&$refs) {
        $text = str_replace('\/', '/', $text);                                  // JSON-escaped addresses
        if (!preg_match_all('#(?:uploads|images)/[A-Za-z0-9_./%-]+?\.(?:jpe?g|png|webp|gif|svg|avif)#i', $text, $m)) return;
        foreach ($m[0] as $u) $refs['/' . rawurldecode($u)] = 1;
    };
    @ini_set('memory_limit', '512M');
    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $tables = $isSqlite ? $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN)
                        : $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        // Not use: the media library's own list, backups, logs, sessions, caches.
        // (Exact names: "blogs" ends in "logs" and must be read.)
        if (preg_match('/(_bak_|backup|audit|_logs?$|^logs?$|sessions?$|cache|^cms_media$|^media)/i', (string)$t)) continue;
        try { $st = $pdo->query('SELECT * FROM ' . $t); } catch (\Throwable $e) { continue; }
        while ($row = $st->fetch(PDO::FETCH_NUM)) foreach ($row as $v) if (is_string($v) && (stripos($v, 'uploads/') !== false || stripos($v, 'images/') !== false)) $grab($v);
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = $f->getPathname();
        if (strpos($p, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR) !== false || strpos($p, DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR) !== false) continue;
        if (!preg_match('/\.(html|js|css|json|php|txt|xml)$/i', $p) || $f->getSize() > 20000000) continue;
        if (preg_match('#(cli-media-cleanup|media-refs-lib)[.]php$#', $p)) continue;
        $q = str_replace(DIRECTORY_SEPARATOR, '/', $p);
        if (preg_match('#/cron/[^/]+[.]json$#i', $q) || stripos(basename($q), 'backup') !== false) continue;   // installer data and backups
        $grab((string)file_get_contents($p));
    }
    // Name cards and share cards: used only by a published product that has no real photo.
    foreach (array_keys($refs) as $u) if (preg_match('#^/images/products/(share/)?[a-z0-9-]+\.(webp|svg|jpg|png)$#', $u)) unset($refs[$u]);
    try {
        foreach ($pdo->query("SELECT slug, image FROM products WHERE status = 'published'")->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $img = trim((string)$p['image']);
            // Link previews use the share card unless the product has its own photo on this site
            // (Amazon pictures are small squares, so those products keep it too). See rocShareImage().
            if (!preg_match('#^(/images/products/photos/|/uploads/)#', $img)) $refs['/images/products/share/' . $p['slug'] . '.jpg'] = 1;
            if (preg_match('#^(/images/products/photos/|/uploads/|https?://)#', $img)) continue;
            foreach (['.webp', '.svg'] as $e) $refs['/images/products/' . $p['slug'] . $e] = 1;
        }
    } catch (\Throwable $e) {}
    return $refs;
}

/** Pictures the clean-up may move: /uploads/**, name and share cards, the old /images/*.jpg photos. */
function rocMediaCandidates(string $root): array {
    $cands = [];
    if (is_dir($root . '/uploads')) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/uploads', FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) if (preg_match('/\.(jpe?g|png|webp|gif|svg|avif)$/i', $f->getFilename())) $cands[] = '/uploads' . str_replace('\\', '/', substr($f->getPathname(), strlen($root . '/uploads')));
    }
    foreach (glob($root . '/images/products/*.{webp,svg,jpg,png}', GLOB_BRACE) ?: [] as $f) $cands[] = '/images/products/' . basename($f);
    foreach (glob($root . '/images/products/share/*.{jpg,png,webp}', GLOB_BRACE) ?: [] as $f) $cands[] = '/images/products/share/' . basename($f);
    foreach (glob($root . '/images/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $f) {
        if (preg_match('/(logo|icon|favicon|apple-touch|og-|android|maskable|manifest)/i', basename($f))) continue;
        $cands[] = '/images/' . basename($f);
    }
    return $cands;
}
