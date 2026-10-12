<?php
/**
 * Run On Console — the site's own pictures: light, with alt text, and one for every place.
 *
 *   php cli-site-images.php            dry run: lists what would change
 *   php cli-site-images.php --apply    does it
 *   php cli-site-images.php --apply --force   makes the page pictures again (new ones)
 *
 * What --apply does:
 *   1. Gaming platform pictures (/uploads/platforms/*.jpg, made by cli-platform-images.php) become
 *      WebP under 90 KB; the platforms point to them; alt text "<platform> gaming platform".
 *   2. Page pictures made with Cloudflare Workers AI (the key from CMS > Blog & news writer), each
 *      for its page and each different: About (2 cards), Blogs (2 cards), Can I run it (hero).
 *      Saved in /uploads/site/{name}.webp under 90 KB, with alt text in the media library.
 *   3. Blog covers and other pictures in /uploads/blog-covers get their alt text in the media library.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$force = in_array('--force', $args, true);

const ROC_SITE_IMAGES = [
    'about-picks'  => [800, 450, 'an editor desk comparing two gaming mice and a mechanical keyboard, printed spec sheets and a laptop with comparison charts, warm desk lamp',
                       'Comparing gaming gear specs for our independent picks'],
    'about-gamers' => [800, 450, 'a cozy home gaming room with two gaming chairs, a large monitor, a console and two controllers, evening ambient light',
                       'A gaming room: Run On Console is made by gamers, for gamers'],
    'blog-guides'  => [800, 450, 'an open laptop showing a long article with headings next to a coffee mug, a notebook with handwritten notes and a gaming headset on a wooden desk',
                       'Reading a gaming buying guide on a laptop'],
    'blog-news'    => [800, 450, 'a tablet showing breaking gaming news headlines and thumbnails, a game controller beside it, soft newsroom lights in the background',
                       'Latest gaming news on a tablet'],
    'compat-hero'  => [1200, 675, 'a gaming PC with a glass side panel showing a graphics card and RGB fans next to a monitor running a fast racing game, glowing performance meters',
                       'A gaming PC running a game: check if your PC can run it'],
];

echo "Run On Console - site pictures\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/blog-engine-lib.php';

/** Saves a GD image as WebP under $max bytes. */
function rocSaveWebp($im, string $file, int $max = 90000): bool {
    $ok = false;
    for ($q = 82; $q >= 35; $q -= 7) { $ok = imagewebp($im, $file, $q); clearstatcache(); if (!$ok || filesize($file) <= $max) break; }
    return $ok;
}
function rocFitImage($src, int $W, int $H) {
    $sw = imagesx($src); $sh = imagesy($src);
    $scale = max($W / $sw, $H / $sh); $cw = (int)($W / $scale); $ch = (int)($H / $scale);
    $im = imagecreatetruecolor($W, $H);
    imagecopyresampled($im, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), $W, $H, $cw, $ch);
    return $im;
}
if (!function_exists('imagewebp')) exit("STOP: this server's PHP cannot write WebP.\n");

/* 1. platforms */
$plat = [];
foreach ($pdo->query('SELECT slug, title, image FROM gaming_categories')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $img = (string)strtok((string)$r['image'], '?');
    if (preg_match('#^/uploads/platforms/([a-z0-9-]+)\.jpg$#', $img) && is_file($ROOT . $img)) {
        $plat[] = $r + ['path' => $img];
        echo '  +  platform ' . str_pad($r['slug'], 24) . 'JPG ' . (int)(filesize($ROOT . $img) / 1024) . " KB -> WebP, alt text\n";
    }
}
/* 2. page pictures */
$make = [];
foreach (ROC_SITE_IMAGES as $name => $def) {
    $f = $ROOT . '/uploads/site/' . $name . '.webp';
    if (is_file($f) && !$force) { echo "  =  /uploads/site/{$name}.webp exists\n"; continue; }
    $make[$name] = $def; echo "  +  /uploads/site/{$name}.webp: new picture ({$def[3]})\n";
}
/* 3. covers */
$covers = [];
foreach ($pdo->query("SELECT slug, title, image, image_alt FROM blogs WHERE image LIKE '/uploads/%'")->fetchAll(PDO::FETCH_ASSOC) as $b) $covers[] = $b;
echo '  +  alt text for ' . count($covers) . " cover(s) in the media library\n";
if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }

$u = $pdo->prepare('UPDATE gaming_categories SET image = ? WHERE slug = ?');
foreach ($plat as $r) {
    $src = @imagecreatefromjpeg($ROOT . $r['path']);
    if (!$src) { echo "  !  {$r['slug']}: could not read the picture\n"; continue; }
    $im = rocFitImage($src, 1200, 675); imagedestroy($src);
    $new = '/uploads/platforms/' . $r['slug'] . '.webp';
    if (!rocSaveWebp($im, $ROOT . $new)) { echo "  !  {$r['slug']}: could not save\n"; continue; }
    imagedestroy($im);
    $u->execute([$new . '?v=' . time(), $r['slug']]);
    rocMediaAlt($pdo, $ROOT, $new, $r['title'] . ' gaming platform - Run On Console');
    @unlink($ROOT . $r['path']);
    try { $pdo->prepare('DELETE FROM cms_media WHERE file_url = ?')->execute([$r['path']]); } catch (\Throwable $e) {}
    echo "  ok platform {$r['slug']}: " . (int)(filesize($ROOT . $new) / 1024) . " KB\n";
}
if ($make) {
    $cf = rocBlogCf($pdo);
    if (!$cf) echo "  !  Cloudflare is not set up (CMS > Blog & news writer): page pictures skipped\n";
    else {
        if (!is_dir($ROOT . '/uploads/site')) @mkdir($ROOT . '/uploads/site', 0755, true);
        foreach ($make as $name => [$W, $H, $prompt, $alt]) {
            $r = null;
            for ($try = 1; $try <= 2 && (!$r || !$r['ok']); $try++) $r = rocCfImage($cf, 'Photorealistic wide editorial photo: ' . $prompt . '. Cinematic lighting, sharp focus, high detail. No text, no letters, no words, no logos, no brand names, no watermarks, no people faces.');
            if (!$r['ok']) { echo "  !  {$name}: {$r['error']}\n"; continue; }
            $src = @imagecreatefromstring($r['bytes']);
            if (!$src) { echo "  !  {$name}: unreadable picture\n"; continue; }
            $im = rocFitImage($src, $W, $H); imagedestroy($src);
            $path = '/uploads/site/' . $name . '.webp';
            rocSaveWebp($im, $ROOT . $path); imagedestroy($im);
            rocMediaAlt($pdo, $ROOT, $path, $alt);
            echo "  ok {$path}: " . (int)(filesize($ROOT . $path) / 1024) . " KB\n";
        }
    }
}
foreach ($covers as $b) rocMediaAlt($pdo, $ROOT, (string)$b['image'], trim((string)$b['image_alt']) ?: $b['title'] . ' - Run On Console');
echo "\nDone.\n";
