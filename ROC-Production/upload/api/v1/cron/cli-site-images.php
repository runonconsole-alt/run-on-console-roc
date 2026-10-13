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
 *      for its page and each different: About (2 cards), Blogs (2 cards), Can I run it (hero), News (hero).
 *      Saved in /uploads/site/{name}.webp under 90 KB, with alt text in the media library.
 *   3. Blog covers and other pictures in /uploads/blog-covers get their alt text in the media library.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$force = in_array('--force', $args, true);

const ROC_SITE_IMAGES = [
    'about-1'     => [800, 450, 'close-up of two hands comparing two gaming mice side by side on a light wooden desk next to spec cards and a ruler, soft daylight',
                      'Comparing two gaming mice side by side: how we pick'],
    'about-2'     => [800, 450, 'a cozy living room at night with two game controllers resting on a sofa in front of a large TV glowing with abstract colors',
                      'Two game controllers on a sofa: made by gamers, for gamers'],
    'blog-1'      => [800, 450, 'top-down flat lay of a gaming headset, a wireless mouse and a compact keyboard neatly arranged on a light desk with a small notebook, soft daylight',
                      'Gaming headset, mouse and keyboard: buying guides'],
    'blog-2'      => [800, 450, 'a game controller and a smartphone lying on a dark desk lit by pink and teal neon light, glossy reflections',
                      'A game controller and a phone: the latest gaming news'],
    'compat-hero' => [1200, 675, 'a gaming PC with a glass side panel and a glowing graphics card beside a monitor showing abstract colorful motion, dark room',
                      'A gaming PC beside a monitor: check if your PC can run a game'],
    'category-hero' => [1200, 675, 'two neat groups of gaming gear on a dark desk: on the left a graphics card, a processor box and memory sticks, on the right a mechanical keyboard, a gaming mouse and a headset, soft green rim light, studio photo',
                      'PC hardware and gaming hardware side by side: product categories'],
    'news-hero'   => [1200, 675, 'a modern gaming desk at night with a new graphics card box, a handheld gaming console and a game controller under a soft spotlight, monitor glowing with abstract colors, cinematic',
                      'New gaming hardware on a desk: gaming and PC hardware news'],
];
/** Older page pictures, replaced by the ones above (removed when the new ones are made). */
const ROC_SITE_OLD = ['about-picks', 'about-gamers', 'blog-guides', 'blog-news'];

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
$plainv = [];
foreach ($pdo->query("SELECT slug, image FROM gaming_categories WHERE image LIKE '/uploads/platforms/%?v=%'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $plainv[$r['slug']] = (string)strtok((string)$r['image'], '?');
    echo "  +  platform {$r['slug']}: address without ?v= (the media library then sees it is used)\n";
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
foreach ($pdo->query("SELECT slug, title, image, image_alt FROM blogs WHERE image LIKE '/uploads/%' AND image NOT LIKE '%?v=%'")->fetchAll(PDO::FETCH_ASSOC) as $b) $covers[] = $b;
echo '  +  alt text for ' . count($covers) . " cover(s) in the media library\n";
if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }

$u = $pdo->prepare('UPDATE gaming_categories SET image = ? WHERE slug = ?');
foreach ($plainv as $slug => $img) $u->execute([$img, $slug]);
foreach ($plat as $r) {
    $src = @imagecreatefromjpeg($ROOT . $r['path']);
    if (!$src) { echo "  !  {$r['slug']}: could not read the picture\n"; continue; }
    $im = rocFitImage($src, 1200, 675); imagedestroy($src);
    $new = '/uploads/platforms/' . $r['slug'] . '.webp';
    if (!rocSaveWebp($im, $ROOT . $new)) { echo "  !  {$r['slug']}: could not save\n"; continue; }
    imagedestroy($im);
    $u->execute([$new, $r['slug']]);
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
            for ($try = 1; $try <= 2 && (!$r || !$r['ok']); $try++) $r = rocCfImage($cf, 'Photorealistic photo: ' . $prompt . '. Sharp focus, high detail. Any screen shows only abstract colors. Absolutely no text, no letters, no words, no numbers, no captions, no signs, no logos, no brand names, no watermarks, no people faces.');
            if (!$r['ok']) { echo "  !  {$name}: {$r['error']}\n"; continue; }
            $src = @imagecreatefromstring($r['bytes']);
            if (!$src) { echo "  !  {$name}: unreadable picture\n"; continue; }
            $im = rocFitImage($src, $W, $H); imagedestroy($src);
            $path = '/uploads/site/' . $name . '.webp';
            rocSaveWebp($im, $ROOT . $path); imagedestroy($im);
            rocMediaAlt($pdo, $ROOT, $path, $alt);
            echo "  ok {$path}: " . (int)(filesize($ROOT . $path) / 1024) . " KB\n";
        }
        foreach (ROC_SITE_OLD as $old) {
            $f = '/uploads/site/' . $old . '.webp';
            if (is_file($ROOT . $f)) { @unlink($ROOT . $f); try { $pdo->prepare('DELETE FROM cms_media WHERE file_url = ?')->execute([$f]); } catch (\Throwable $e) {} echo "  -  old picture {$f} removed\n"; }
        }
    }
}
foreach ($covers as $b) rocMediaAlt($pdo, $ROOT, (string)$b['image'], trim((string)$b['image_alt']) ?: $b['title'] . ' - Run On Console');
echo "\nDone.\n";
