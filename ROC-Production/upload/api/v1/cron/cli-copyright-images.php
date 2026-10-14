<?php
/**
 * Run On Console — replace site pictures that look like real games, characters or brands (Deploy 52).
 *
 *   php cli-copyright-images.php                  dry run: lists the pictures
 *   php cli-copyright-images.php --apply          makes new pictures (Cloudflare, the key from CMS > Blog & news
 *                                                 writer) and saves them under the same names, so every page that
 *                                                 shows them gets the new one; the old ones go to a backup folder
 *   php cli-copyright-images.php --rollback=DIR   puts the old pictures back
 *
 * Replaced: a Spider-Man-like hero, an Elden Ring-like knight, a Call of Duty-like soldier, a race car with a
 * real tyre brand and an esports arena showing a real game. The new pictures show no characters, no game
 * screens, no brands and no logos.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const ROC_COPYRIGHT_IMAGES = [
    'trending_spiderman' => ['a rainy neon city street at night seen from a high rooftop, wet reflections, glowing shop signs without any readable text, cinematic',
                             'A rainy neon city at night: open-world games'],
    'trending_elden'     => ['ruins of a stone castle at night under a starry sky with glowing embers floating in the air, dark fantasy landscape, no people',
                             'Castle ruins under a starry sky: fantasy role-playing games'],
    'hero_cod'           => ['a gamer\'s desk at night with a mechanical keyboard, a gaming mouse and a headset, a monitor glowing with abstract colorful light, cinematic',
                             'A gaming desk with keyboard, mouse and headset at night'],
    'trending_forza'     => ['a plain unbranded sports car without any logos, stickers or numbers, driving on a wet race track at dusk, motion blur, red tail lights',
                             'An unbranded sports car on a wet race track: racing games'],
    'trending_esports'   => ['an esports arena with rows of gaming PCs on a stage under blue and purple stage lights, a crowd seen from behind, large screens showing only abstract colors',
                             'An esports arena with gaming PCs on stage'],
];

echo "Run On Console - replace pictures that look like real games or brands\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
require_once $ROOT . '/api/v1/cms/blog-engine-lib.php';

if ($rollback !== null) {
    $n = 0;
    foreach (array_keys(ROC_COPYRIGHT_IMAGES) as $name) {
        $from = rtrim($rollback, '/') . '/images/' . $name . '.jpg';
        if (is_file($from) && copy($from, $ROOT . '/images/' . $name . '.jpg')) $n++;
    }
    exit("Put back {$n} picture(s).\n");
}

foreach (ROC_COPYRIGHT_IMAGES as $name => [, $alt]) echo '  -  /images/' . $name . '.jpg' . (is_file($ROOT . '/images/' . $name . '.jpg') ? '' : ' (missing, will be made)') . "\n";
if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }
$cf = rocBlogCf($pdo);
if (!$cf) exit("\nCloudflare is not set up (CMS > Blog & news writer).\n");

$dir = $HOME . '/roc-media-trash/copyright-' . date('Ymd-His');
@mkdir($dir . '/images', 0755, true);
$ok = 0;
foreach (ROC_COPYRIGHT_IMAGES as $name => [$prompt, $alt]) {
    $file = $ROOT . '/images/' . $name . '.jpg';
    $r = null;
    for ($try = 1; $try <= 2 && (!$r || !$r['ok']); $try++)
        $r = rocCfImage($cf, 'Photorealistic photo: ' . $prompt . '. Sharp focus, high detail. Absolutely no text, no letters, no words, no numbers, no logos, no brand names, no watermarks, no characters from games or films, no people faces.');
    if (!$r['ok']) { echo "  !  {$name}: {$r['error']}\n"; continue; }
    $src = @imagecreatefromstring($r['bytes']);
    if (!$src) { echo "  !  {$name}: unreadable picture\n"; continue; }
    // Same size as before (1200 x 670), so every page keeps its layout.
    [$W, $H] = is_file($file) ? array_slice((array)@getimagesize($file), 0, 2) : [1200, 670];
    $W = $W ?: 1200; $H = $H ?: 670;
    $sw = imagesx($src); $sh = imagesy($src);
    $scale = max($W / $sw, $H / $sh); $cw = (int)($W / $scale); $ch = (int)($H / $scale);
    $im = imagecreatetruecolor($W, $H);
    imagecopyresampled($im, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), $W, $H, $cw, $ch);
    imagedestroy($src);
    if (is_file($file)) copy($file, $dir . '/images/' . $name . '.jpg');
    $tmp = $file . '.tmp';
    for ($q = 82; $q >= 45; $q -= 7) { imagejpeg($im, $tmp, $q); clearstatcache(); if (filesize($tmp) <= 100000) break; }
    imagedestroy($im);
    rename($tmp, $file);
    rocMediaAlt($pdo, $ROOT, '/images/' . $name . '.jpg', $alt);
    echo "  ok /images/{$name}.jpg: " . (int)(filesize($file) / 1024) . " KB\n";
    $ok++;
}
echo "\nReplaced {$ok} picture(s). Old ones kept in {$dir}\nRollback: php " . __FILE__ . " --rollback={$dir}\n"
   . "Browsers may show the old picture until the page is reloaded with Ctrl + F5.\n";
