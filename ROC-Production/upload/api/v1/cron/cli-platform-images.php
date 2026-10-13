<?php
/**
 * Run On Console — a picture of its own for every gaming platform (/gaming-platforms/).
 *
 *   php cli-platform-images.php                  dry run: lists the platforms whose picture is shared or does not fit
 *   php cli-platform-images.php --apply          makes the pictures with Cloudflare Workers AI (FLUX, the key from
 *                                                CMS > Blog & news writer) and saves them, with a backup
 *   php cli-platform-images.php --apply --only=sony-playstation   one platform
 *   php cli-platform-images.php --apply --force  also replaces pictures uploaded in the CMS
 *   php cli-platform-images.php --rollback=FILE  puts the old pictures back
 *
 * Pictures: public_html/uploads/platforms/{slug}.webp, 1200 x 675, under 90 KB, with alt text. The prompts describe the
 * hardware without brand names or logos. A picture set by hand in the CMS (anything that is not
 * one of the old shared /images/*.jpg pictures) is kept unless --force.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$force = in_array('--force', $args, true);
$only = null; $rollback = null;
foreach ($args as $a) {
    if (strpos($a, '--only=') === 0) $only = substr($a, 7);
    if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);
}

const ROC_PLATFORM_PROMPTS = [
    'pc-handheld-pc'        => 'a sleek handheld gaming PC with a bright screen in front of a compact gaming desktop tower with soft RGB lighting on a dark desk',
    'sony-playstation'      => 'a modern white and black home video game console standing upright beside a white wireless gamepad, living room, cool blue ambient light',
    'microsoft-xbox'        => 'a matte black rectangular tower video game console with a soft green glow and a black wireless controller on a wooden TV stand',
    'nintendo-home'         => 'a hybrid video game console docked under a TV with two small detachable controllers in red and blue, cozy bright living room',
    'sony-handhelds'        => 'a glossy black widescreen portable game console from the 2000s next to a slim OLED portable console on a table, soft studio light',
    'nintendo-handhelds'    => 'a collection of colorful pocket handheld game consoles including a clamshell dual-screen handheld and a classic grey brick handheld, warm light',
    'sega-consoles'         => 'a vintage black 16-bit home video game console with a cartridge and a three-button controller on a carpet in front of a glowing CRT television',
    'retro-other'           => 'assorted vintage 1980s home video game consoles, joysticks and game cartridges on a wooden shelf, nostalgic warm light',
    'mobile'                => 'a smartphone held sideways in a clip-on game controller showing a colorful action game, dark background with neon reflections',
    'cloud-gaming'          => 'a video game streamed from glowing data center servers to a big TV and a laptop, light trails of network data between them',
    'vr-ar'                 => 'a white virtual reality headset and two motion controllers resting on a desk with purple and teal neon light, no person',
    'retro-handheld-brands' => 'several small colorful retro emulation handheld consoles with D-pads and bright pixel-art screens arranged on a desk',
    'arcade'                => 'a row of classic arcade cabinets and a pinball machine glowing in a dark arcade hall',
    'smart-tv-streaming'    => 'a large smart TV showing a grid of game covers, a small streaming stick and a gamepad on a sofa, evening light',
    'misc-other'            => 'an eclectic collection of niche gaming devices: a mini retro console, a light gun, a racing wheel and a dance mat',
];
const ROC_OLD_SHARED = ['/images/battlestation_pc.jpg', '/images/trending_spiderman.jpg', '/images/trending_forza.jpg', '/images/hero_cod.jpg',
    '/images/handheld_console.jpg', '/images/trending_elden.jpg', '/images/gaming_cables_accessories.jpg', '/images/pro_controller.jpg',
    '/images/trending_esports.jpg', '/images/streaming_vr_gear.jpg', '/images/gaming_monitor.jpg', '/images/cyber_keyboard.jpg', ''];

echo "Run On Console - gaming platform pictures\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/blog-engine-lib.php';
$hasUpd = in_array('updated_at', rocBlogCols($pdo, 'gaming_categories'), true);

if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b) || empty($b['platforms'])) exit("No backup found in {$rollback}\n");
    $u = $pdo->prepare('UPDATE gaming_categories SET image = ? WHERE slug = ?');
    foreach ($b['platforms'] as $slug => $img) $u->execute([$img, $slug]);
    exit('Restored ' . count($b['platforms']) . " picture(s).\n");
}

$rows = $pdo->query('SELECT slug, title, image FROM gaming_categories')->fetchAll(PDO::FETCH_ASSOC);
$count = [];
foreach ($rows as $r) $count[$r['image']] = ($count[$r['image']] ?? 0) + 1;
$todo = [];
foreach ($rows as $r) {
    if ($only !== null && $r['slug'] !== $only) continue;
    if (!isset(ROC_PLATFORM_PROMPTS[$r['slug']])) { echo "  ?  {$r['slug']}: no prompt for this platform (kept)\n"; continue; }
    $img = trim((string)$r['image']);
    if (!$force && !in_array($img, ROC_OLD_SHARED, true)) { echo "  =  {$r['slug']}: own picture ({$img}) kept\n"; continue; }
    $todo[$r['slug']] = $img;
    echo '  +  ' . str_pad($r['slug'], 24) . ($img === '' ? 'no picture' : $img . ($count[$img] > 1 ? ' (shared by ' . $count[$img] . ' platforms)' : '')) . "\n";
}
echo "\n" . count($todo) . " picture(s) to make.\n";
if (!$apply) { echo "Dry run: nothing changed. Run again with --apply.\n"; exit(0); }
$cf = rocBlogCf($pdo);
if (!$cf) exit("STOP: Cloudflare is not set up (CMS > Blog & news writer > Cover pictures with AI).\n");
if (!function_exists('imagecreatefromstring')) exit("STOP: the GD image library is missing.\n");

$dir = $ROOT . '/uploads/platforms';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$file = $HOME . '/roc-platform-images-backup-' . date('Ymd-His') . '.json';
$done = [];
$u = $pdo->prepare('UPDATE gaming_categories SET image = ?' . ($hasUpd ? ', updated_at = ?' : '') . ' WHERE slug = ?');
foreach ($todo as $slug => $old) {
    $prompt = 'Photorealistic wide editorial photo: ' . ROC_PLATFORM_PROMPTS[$slug] . '. Cinematic lighting, sharp focus, high detail. '
            . 'No text, no letters, no words, no logos, no brand names, no watermarks, no people faces.';
    $r = null;
    for ($try = 1; $try <= 2 && (!$r || !$r['ok']); $try++) $r = rocCfImage($cf, $prompt);
    if (!$r['ok']) { echo "  !  {$slug}: {$r['error']}\n"; continue; }
    $src = @imagecreatefromstring($r['bytes']);
    if (!$src) { echo "  !  {$slug}: the picture could not be read\n"; continue; }
    $W = 1200; $H = 675; $sw = imagesx($src); $sh = imagesy($src);
    $scale = max($W / $sw, $H / $sh); $cw = (int)($W / $scale); $ch = (int)($H / $scale);
    $im = imagecreatetruecolor($W, $H);
    imagecopyresampled($im, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), $W, $H, $cw, $ch);
    imagedestroy($src);
    // WebP under 90 KB (JPG when the server cannot write WebP).
    $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
    for ($q = 82; $q >= 35; $q -= 7) {
        $ext === 'webp' ? imagewebp($im, $dir . '/' . $slug . '.webp', $q) : imagejpeg($im, $dir . '/' . $slug . '.jpg', $q);
        clearstatcache(); if (filesize($dir . '/' . $slug . '.' . $ext) <= 90000) break;
    }
    imagedestroy($im);
    $url = '/uploads/platforms/' . $slug . '.' . $ext;
    $title = (string)$pdo->query('SELECT title FROM gaming_categories WHERE slug = ' . $pdo->quote($slug))->fetchColumn();
    rocMediaAlt($pdo, $ROOT, $url, $title . ' gaming platform - Run On Console');
    $done[$slug] = $old;
    file_put_contents($file, json_encode(['made' => date('c'), 'platforms' => $done], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $u->execute($hasUpd ? [$url, date('Y-m-d H:i:s'), $slug] : [$url, $slug]);
    echo "  ok {$slug}: {$url}\n";
}
echo "\nMade " . count($done) . ' picture(s).' . ($done ? "\nBackup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}" : '') . "\n";
