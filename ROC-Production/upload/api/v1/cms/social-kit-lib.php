<?php
/**
 * Run On Console — social media kit for every news story, guide and blog post (Deploy 72).
 *
 * For each post published in the last 3 days the scheduler cron makes (one post a run):
 *   - a 30-45 second script for TikTok / YouTube Shorts / Instagram Reels (hook, lines, call to action),
 *   - a caption for Facebook and Instagram with hashtags, and a short post for X,
 *   - two pictures from the post's cover with its title: 1080x1920 (Story, Shorts, Reels cover) and
 *     1080x1080 (feed post), in /uploads/social/.
 * The writer is the blog writer's AI key (Gemini, then Groq). CMS > Social posts lists the kits: copy the
 * texts, download the pictures, mark where it was posted. Kits are kept in cms_settings.social_kits (newest 80).
 */
if (defined('ROC_SOCIAL_KIT_LIB')) return;
define('ROC_SOCIAL_KIT_LIB', 1);
require_once __DIR__ . '/blog-engine-lib.php';

const ROC_KIT_KEEP = 80;
const ROC_KIT_PLATFORMS = ['tiktok' => 'TikTok', 'youtube' => 'YouTube Shorts', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X'];

function rocKitAll(PDO $pdo): array { $k = rocBlogGet($pdo, 'social_kits', []); return is_array($k) ? $k : []; }
function rocKitSaveAll(PDO $pdo, array $kits): void {
    uasort($kits, function ($a, $b) { return strcmp((string)($b['published'] ?? ''), (string)($a['published'] ?? '')); });
    rocBlogSet($pdo, 'social_kits', array_slice($kits, 0, ROC_KIT_KEEP, true));
}

/** Post section and label: news, guides or blogs. */
function rocKitSection(array $p): array {
    $c = trim((string)($p['category'] ?? ''));
    if (strcasecmp($c, 'News') === 0) return ['news', 'NEWS'];
    if (preg_match('/^guides?\s*(:.*)?$/i', $c)) return ['guides', 'GUIDE'];
    return ['blogs', 'BLOG'];
}

function rocKitFont(): ?string {
    foreach (array_merge([__DIR__ . '/fonts/Montserrat-ExtraBold.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                          'C:/Windows/Fonts/segoeuib.ttf'], glob('/usr/share/fonts/*/*Bold.ttf') ?: []) as $f) if (is_file($f)) return $f;
    return null;
}

/** Words wrapped to lines no wider than $max pixels. */
function rocKitWrap(string $text, string $font, float $size, int $max): array {
    $lines = []; $line = '';
    foreach (preg_split('/\s+/u', trim($text)) as $w) {
        $try = $line === '' ? $w : $line . ' ' . $w;
        $b = imagettfbbox($size, 0, $font, $try);
        if ($line !== '' && $b[2] - $b[0] > $max) { $lines[] = $line; $line = $w; } else $line = $try;
    }
    if ($line !== '') $lines[] = $line;
    return $lines;
}

/** A picture W x H from the post's cover (or the site's green background) with the title. Returns the public URL or ''. */
function rocKitImage(string $root, string $slug, string $cover, string $title, string $label, int $W, int $H, string $suffix): string {
    if (!function_exists('imagettftext') || !function_exists('imagejpeg') || !($font = rocKitFont())) return '';
    $im = imagecreatetruecolor($W, $H);
    // Background: the cover, cut to fill.
    $src = null;
    $path = $cover !== '' && $cover[0] === '/' ? $root . parse_url($cover, PHP_URL_PATH) : '';
    if ($path !== '' && is_file($path)) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($path);
        elseif ($ext === 'png') $src = @imagecreatefrompng($path);
        elseif (in_array($ext, ['jpg', 'jpeg'], true)) $src = @imagecreatefromjpeg($path);
    }
    if ($src) {
        $sw = imagesx($src); $sh = imagesy($src); $sx = 0;
        // Our own covers (rocBlogCover) carry the title on the left: only the picture on the right is used.
        if (strpos($cover, '/uploads/blog-covers/') === 0) { $sx = (int)($sw * 0.57); $sw -= $sx; }
        $scale = max($W / $sw, $H / $sh); $cw = (int)($W / $scale); $ch = (int)($H / $scale);
        imagecopyresampled($im, $src, 0, 0, $sx + (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), $W, $H, $cw, $ch);
        imagedestroy($src);
    } else {
        for ($y = 0; $y < $H; $y++) { $k = $y / $H; imageline($im, 0, $y, $W, $y, imagecolorallocate($im, (int)(3 + 4 * $k), (int)(60 - 30 * $k), (int)(48 - 20 * $k))); }
    }
    // Dark fade over the lower part so the title reads well.
    imagealphablending($im, true);
    $from = (int)($H * 0.35);
    for ($y = $from; $y < $H; $y++) {
        $a = (int)(127 - 112 * min(1, ($y - $from) / ($H * 0.45)));
        imageline($im, 0, $y, $W, $y, imagecolorallocatealpha($im, 2, 20, 16, max(15, $a)));
    }
    $white = imagecolorallocate($im, 255, 255, 255);
    $green = imagecolorallocate($im, 52, 211, 153);
    $dark = imagecolorallocate($im, 5, 46, 43);
    $pad = (int)($W * 0.07);
    // Label pill.
    $ls = $W * 0.026;
    $lb = imagettfbbox($ls, 0, $font, $label);
    $lw = $lb[2] - $lb[0] + 44; $lh = (int)($ls * 2.2);
    $ly = (int)($H * 0.08);
    imagefilledrectangle($im, $pad, $ly, $pad + $lw, $ly + $lh, $green);
    imagettftext($im, $ls, 0, $pad + 22, (int)($ly + $lh * 0.7), $dark, $font, $label);
    // Title, as big as fits (at most 6 lines).
    $size = $W * 0.075;
    do { $lines = rocKitWrap($title, $font, $size, $W - 2 * $pad); $size *= 0.92; } while (count($lines) > 6 && $size > 20);
    $size /= 0.92;
    $lineH = (int)($size * 1.32);
    $y = (int)($H * 0.86) - $lineH * count($lines);
    foreach ($lines as $l) { $y += $lineH; imagettftext($im, $size, 0, $pad, $y, $white, $font, $l); }
    // Brand line.
    $bs = $W * 0.028;
    imagettftext($im, $bs, 0, $pad, (int)($H * 0.94), $green, $font, 'RUN ON CONSOLE');
    $site = 'runonconsole.com';
    $sb = imagettfbbox($bs * 0.9, 0, $font, $site);
    imagettftext($im, $bs * 0.9, 0, $W - $pad - ($sb[2] - $sb[0]), (int)($H * 0.94), $white, $font, $site);
    $dir = $root . '/uploads/social';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $file = $slug . '-' . $suffix . '.jpg';
    imagejpeg($im, $dir . '/' . $file, 86);
    imagedestroy($im);
    return '/uploads/social/' . $file;
}

/** The texts, written by the AI. Returns the kit fields or ['error' => ...]. */
function rocKitTexts(PDO $pdo, array $p, string $url): array {
    [$sec] = rocKitSection($p);
    $body = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)$p['content']))), 0, 3500);
    $prompt = "You run the social media of Run On Console (runonconsole.com), a gaming and PC hardware site. Make a social media kit for this "
        . ($sec === 'news' ? 'news story' : ($sec === 'guides' ? 'guide' : 'blog post')) . ".\n\nTITLE: {$p['title']}\nSUMMARY: {$p['excerpt']}\nTEXT: {$body}\nLINK: {$url}\n\n"
        . "RULES: facts only from the text above, no prices, no invented numbers, simple English for gamers, no hashtags inside the script.\n"
        . "Answer with ONLY a JSON object:\n"
        . "{\"hook\": \"first 2 seconds of the video, max 12 words, makes people stop scrolling\", "
        . "\"script\": [\"4 to 6 short spoken lines for a 30-45 second TikTok / YouTube Shorts / Reels video, each max 20 words\"], "
        . "\"on_screen\": [\"3 to 5 very short text overlays, max 6 words each\"], "
        . "\"cta\": \"last spoken line, tells viewers the full story is on runonconsole.com (link in bio)\", "
        . "\"caption\": \"Facebook / Instagram caption, 2-4 sentences, ends with: Read more: {$url}\", "
        . "\"hashtags\": [\"8 to 12 relevant hashtags without #\"], "
        . "\"x_post\": \"post for X, max 240 characters including the link {$url}\"}";
    $used = null;
    $w = rocBlogWriteAny($pdo, $prompt, $used);
    if (!$w['ok']) return ['error' => $w['error']];
    $a = $w['article'];
    $clean = function ($v, $n) { return mb_substr(trim(strip_tags((string)$v)), 0, $n); };
    $list = function ($v, $n, $len) use ($clean) { return array_values(array_filter(array_map(function ($x) use ($clean, $len) { return $clean($x, $len); }, array_slice(is_array($v) ? $v : [], 0, $n)))); };
    $tags = array_values(array_unique(array_filter(array_map(function ($t) { return preg_replace('/[^\p{L}\p{N}_]/u', '', (string)$t); }, is_array($a['hashtags'] ?? null) ? $a['hashtags'] : []))));
    $kit = ['hook' => $clean($a['hook'] ?? '', 120), 'script' => $list($a['script'] ?? [], 6, 200), 'on_screen' => $list($a['on_screen'] ?? [], 5, 60),
            'cta' => $clean($a['cta'] ?? '', 200), 'caption' => $clean($a['caption'] ?? '', 900), 'hashtags' => array_slice(array_merge($tags, ['RunOnConsole']), 0, 13),
            'x_post' => $clean($a['x_post'] ?? '', 280), 'by' => (string)$used];
    if ($kit['hook'] === '' || !$kit['script'] || $kit['caption'] === '') return ['error' => 'The AI answer was incomplete.'];
    if (strpos($kit['caption'], $url) === false) $kit['caption'] .= "\n\nRead more: " . $url;
    if (strpos($kit['x_post'], $url) === false) $kit['x_post'] = rtrim(mb_substr($kit['x_post'], 0, 250 - mb_strlen($url))) . ' ' . $url;
    return $kit;
}

/** Makes (or remakes) the kit of one post. */
function rocKitMake(PDO $pdo, string $root, array $p): array {
    [$sec, $label] = rocKitSection($p);
    $url = 'https://runonconsole.com/' . $sec . '/' . $p['slug'] . '/';
    $kit = rocKitTexts($pdo, $p, $url);
    if (isset($kit['error'])) return $kit;
    $kit += ['id' => (string)$p['id'], 'title' => (string)$p['title'], 'url' => $url, 'section' => $sec, 'published' => (string)$p['published_at'],
             'story' => rocKitImage($root, (string)$p['slug'], (string)$p['image'], (string)$p['title'], $label, 1080, 1920, 'story'),
             'square' => rocKitImage($root, (string)$p['slug'], (string)$p['image'], (string)$p['title'], $label, 1080, 1080, 'square'),
             'made' => gmdate('c'), 'posted' => []];
    $all = rocKitAll($pdo);
    if (isset($all[$kit['id']]['posted'])) $kit['posted'] = $all[$kit['id']]['posted'];
    $all[$kit['id']] = $kit;
    rocKitSaveAll($pdo, $all);
    return $kit;
}

/** Scheduler cron: the kit of one recent post that has none yet (news first). */
function rocKitTick(PDO $pdo, string $root): ?string {
    if ((string)rocBlogGet($pdo, 'social_kits_on', 'on') === 'off' || !rocBlogWriters($pdo)) return null;
    $all = rocKitAll($pdo);
    $st = $pdo->prepare("SELECT id, title, slug, excerpt, content, category, image, published_at FROM blogs WHERE status = 'published' AND published_at >= ? ORDER BY (category = 'News') DESC, published_at DESC LIMIT 30");
    $st->execute([gmdate('Y-m-d H:i:s', time() - 3 * 86400)]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
        if (isset($all[$p['id']])) continue;
        $fails = (array)rocBlogGet($pdo, 'social_kit_fails', []);
        if (($fails[$p['id']] ?? 0) >= 3) continue;
        $k = rocKitMake($pdo, $root, $p);
        if (isset($k['error'])) { $fails[$p['id']] = ($fails[$p['id']] ?? 0) + 1; rocBlogSet($pdo, 'social_kit_fails', array_slice($fails, -50, null, true)); return 'Social kit failed for "' . $p['title'] . '": ' . $k['error']; }
        return 'Social kit: ' . $p['title'];
    }
    return null;
}
