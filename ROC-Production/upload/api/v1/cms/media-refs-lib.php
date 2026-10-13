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
 * Blog and news covers (/uploads/blog-covers/) are only shown by their post, so only the blogs table
 * image and text count for them (old covers left in og_image, twitter_image or a history table are not in use).
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
    // Covers: only what the posts use now.
    foreach (array_keys($refs) as $u) if (strpos($u, '/uploads/blog-covers/') === 0) unset($refs[$u]);
    try {
        // Only the picture the page shows (image) and pictures inside the text count; og_image and
        // twitter_image can still hold an older cover that no page shows.
        foreach ($pdo->query('SELECT image, content FROM blogs')->fetchAll(PDO::FETCH_ASSOC) as $b)
            foreach ($b as $v) if (is_string($v) && stripos($v, 'blog-covers/') !== false && preg_match_all('#uploads/blog-covers/[A-Za-z0-9_.%-]+?\.(?:jpe?g|png|webp|gif|avif)#i', $v, $m))
                foreach ($m[0] as $u) $refs['/' . rawurldecode($u)] = 1;
    } catch (\Throwable $e) {}
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

/** Saves the scan for the CMS Media library (api/v1/cms/media-usage.php reads it). */
function rocMediaRefsCache(string $root, array $refs): void {
    $dir = dirname($root) . '/roc-cms-cache';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/media-usage.json', json_encode(['t' => time(), 'used' => $refs], JSON_UNESCAPED_SLASHES));
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
