<?php
/**
 * Run On Console — blog agent endpoint (the writer runs on the owner's PC).
 *
 * Every request needs the header  X-ROC-Agent-Token: <token>  (made in CMS > Blog agent;
 * only its SHA-256 is stored, in cms_settings.blog_agent_token_hash).
 *
 * GET  /api/v1/blog-agent.php?action=context
 *      What the writer needs: recent posts (to avoid repeats), products, categories,
 *      platforms and main pages (for internal links), the free publishing slots.
 * POST /api/v1/blog-agent.php
 *      {title, focus_keyword, meta_title, meta_description, excerpt, category,
 *       content_html, image_alt?, publish_at}   publish_at = ISO time of a slot
 *      Creates the post as "scheduled". The server cron (cli-blog-scheduler.php)
 *      publishes it at that time, even if the PC is off by then. The cover image is
 *      drawn here (1200 x 630 JPG with the title), so nothing has to be uploaded.
 *
 * Slots: 12:00 and 18:00 Pakistan time (Asia/Karachi), one post per slot.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cms/cms-html.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const AGENT_TZ = 'Asia/Karachi';
const AGENT_SLOTS = ['12:00', '18:00'];
const AGENT_AUTHOR = 'ROC Team';

function agentOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; }
function agentSetting(PDO $pdo, string $key): ?string {
    try { $st = $pdo->prepare('SELECT setting_value FROM cms_settings WHERE setting_key = ? LIMIT 1'); $st->execute([$key]); $v = $st->fetchColumn(); }
    catch (\Throwable $e) { return null; }
    return ($v === false || $v === null) ? null : (string)$v;
}
function agentSettingSet(PDO $pdo, string $key, string $value): void {
    $st = $pdo->prepare('SELECT COUNT(*) FROM cms_settings WHERE setting_key = ?'); $st->execute([$key]);
    if ((int)$st->fetchColumn() > 0) $pdo->prepare('UPDATE cms_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?')->execute([$value, $key]);
    else $pdo->prepare('INSERT INTO cms_settings (setting_key, setting_value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)')->execute([$key, $value]);
}
function agentCols(PDO $pdo, string $t): array {
    try { $st = $pdo->query("SELECT * FROM {$t} LIMIT 0"); } catch (\Throwable $e) { return []; }
    $c = []; for ($i = 0; $i < $st->columnCount(); $i++) $c[] = strtolower((string)$st->getColumnMeta($i)['name']);
    return $c;
}
function agentSlug(string $s): string {
    $s = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s), '-'));
    return substr($s, 0, 90);
}

$pdo = getDBConnection();
if (!$pdo) agentOut(503, ['success' => false, 'error' => 'Database unavailable.']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ROOT = dirname(__DIR__, 2);

/* ------------------------------------------------------------------ auth */
$hash = agentSetting($pdo, 'blog_agent_token_hash');
$given = (string)($_SERVER['HTTP_X_ROC_AGENT_TOKEN'] ?? '');
if ($hash === null || $hash === '' || $given === '' || !hash_equals($hash, hash('sha256', $given))) {
    usleep(300000);
    agentOut(401, ['success' => false, 'error' => 'Agent token missing or wrong. Make one in CMS > Blog agent.']);
}

/** Free slots (UTC 'Y-m-d H:i:s' => label) for today and the next 2 days, after now + 5 minutes. */
function agentFreeSlots(PDO $pdo): array {
    $tz = new DateTimeZone(AGENT_TZ);
    $taken = [];
    $q = $pdo->query("SELECT published_at FROM blogs WHERE status IN ('scheduled', 'published') AND published_at >= '" . gmdate('Y-m-d H:i:s', time() - 86400) . "'");
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $t) $taken[] = strtotime($t . ' UTC');
    $out = [];
    for ($d = 0; $d < 3; $d++) {
        foreach (AGENT_SLOTS as $hm) {
            $local = new DateTime('today ' . $hm, $tz);
            $local->modify("+{$d} day");
            $ts = $local->getTimestamp();
            if ($ts < time() + 300) continue;
            foreach ($taken as $t) if (abs($t - $ts) < 1800) continue 2;    // a post within 30 min of the slot
            $out[gmdate('Y-m-d H:i:s', $ts)] = $local->format('D j M, H:i') . ' (Pakistan time)';
        }
    }
    return $out;
}

/* --------------------------------------------------------------- context */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $recent = $pdo->query("SELECT title, slug, status, published_at FROM blogs ORDER BY COALESCE(published_at, created_at) DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC);
    $products = [];
    if (agentCols($pdo, 'products')) {
        foreach ($pdo->query("SELECT title, slug, category FROM products WHERE status = 'published' ORDER BY category, title")->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $products[] = ['title' => $p['title'], 'url' => '/products/' . $p['slug'] . '/', 'category' => $p['category']];
        }
    }
    $cats = []; $plats = [];
    if (agentCols($pdo, 'product_categories')) foreach ($pdo->query("SELECT name, slug FROM product_categories WHERE status = 'published'")->fetchAll(PDO::FETCH_ASSOC) as $c) $cats[] = ['name' => $c['name'], 'url' => '/products/' . $c['slug'] . '/'];
    if (agentCols($pdo, 'gaming_categories')) foreach ($pdo->query("SELECT title, slug FROM gaming_categories WHERE status = 'published'")->fetchAll(PDO::FETCH_ASSOC) as $c) $plats[] = ['name' => $c['title'], 'url' => '/gaming-platforms/' . $c['slug'] . '/'];
    $slots = [];
    foreach (agentFreeSlots($pdo) as $utc => $label) $slots[] = ['publish_at' => str_replace(' ', 'T', $utc) . 'Z', 'label' => $label];
    agentOut(200, ['success' => true, 'site' => 'https://runonconsole.com', 'now_utc' => gmdate('c'), 'free_slots' => $slots,
        'recent_posts' => $recent, 'products' => $products, 'product_categories' => $cats, 'gaming_platforms' => $plats,
        'main_pages' => [['name' => 'Can I run it? game requirements checker', 'url' => '/compatibility/'], ['name' => 'All products', 'url' => '/products/'],
                         ['name' => 'All gaming platforms', 'url' => '/gaming-platforms/'], ['name' => 'All blog posts', 'url' => '/blogs/']],
        'rules' => ['title' => '30-70 characters', 'meta_title' => 'up to 60 characters', 'meta_description' => '120-160 characters',
                    'content_html' => '900+ words; h2/h3 only (no h1); 3-6 internal links from this list; no prices; no invented test results']]);
}

/* ----------------------------------------------------------------- create */
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) agentOut(400, ['success' => false, 'error' => 'Send JSON.']);
$t = function ($k, $max) use ($in) { return trim(mb_substr((string)preg_replace('/\s+/u', ' ', strip_tags((string)($in[$k] ?? ''))), 0, $max)); };
$title = $t('title', 220); $metaT = $t('meta_title', 255); $metaD = $t('meta_description', 500);
$excerpt = $t('excerpt', 600); $category = $t('category', 100) ?: 'Guides'; $focus = $t('focus_keyword', 200);
$alt = $t('image_alt', 300) ?: $title;
$content = rocCmsSanitizeHtml((string)($in['content_html'] ?? ''));
$words = count(preg_split('/\s+/u', rocCmsTextFromHtml($content)) ?: []);

$errors = [];
if (mb_strlen($title) < 20) $errors['title'] = 'Title is too short.';
if ($metaT === '' || mb_strlen($metaT) > 60) $errors['meta_title'] = 'Meta title must be 1-60 characters.';
if (mb_strlen($metaD) < 70 || mb_strlen($metaD) > 160) $errors['meta_description'] = 'Meta description must be 70-160 characters.';
if ($excerpt === '') $errors['excerpt'] = 'Excerpt is required.';
if ($words < 600) $errors['content_html'] = "Content has {$words} words; at least 600 are needed.";

$slug = agentSlug((string)($in['slug'] ?? '') ?: $title);
if ($slug === '') $errors['slug'] = 'Slug needs letters or numbers.';
$st = $pdo->prepare('SELECT COUNT(*) FROM blogs WHERE slug = ? OR LOWER(title) = LOWER(?)');
$st->execute([$slug, $title]);
if ((int)$st->fetchColumn() > 0) $errors['title'] = 'A post with this title or address already exists. Choose another topic.';

// The time: one of the free slots.
$free = agentFreeSlots($pdo);
try { $when = new DateTime((string)($in['publish_at'] ?? '')); $when->setTimezone(new DateTimeZone('UTC')); }
catch (\Throwable $e) { $when = null; }
$at = $when ? $when->format('Y-m-d H:i:s') : '';
if (!isset($free[$at])) $errors['publish_at'] = 'Use one of the free slots from ?action=context: ' . implode(', ', array_map(function ($u) { return str_replace(' ', 'T', $u) . 'Z'; }, array_keys($free)));
if ($errors) agentOut(422, ['success' => false, 'error' => 'Fix the listed fields and send again.', 'errors' => $errors]);

/* Cover image: 1200 x 630 JPG with the title (falls back to a site photo). */
function agentCover(string $root, string $slug, string $title, string $category): string {
    $fallback = '/images/hero_cod.jpg';
    if (!function_exists('imagettftext') || !function_exists('imagejpeg')) return $fallback;
    $font = null;
    foreach (array_merge(['/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                          '/usr/share/fonts/liberation/LiberationSans-Bold.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
                          'C:/Windows/Fonts/segoeuib.ttf'], glob('/usr/share/fonts/*/*Bold.ttf') ?: [], glob('/usr/share/fonts/*/*/*Bold.ttf') ?: []) as $f) {
        if (is_file($f)) { $font = $f; break; }
    }
    if (!$font) return $fallback;
    $W = 1200; $H = 630;
    $im = imagecreatetruecolor($W, $H);
    for ($y = 0; $y < $H; $y++) for ($x = 0; $x < $W; $x += 6) {
        $k = ($x / $W + $y / $H) / 2;
        imagefilledrectangle($im, $x, $y, $x + 5, $y, imagecolorallocate($im, (int)(2 + 13 * $k), (int)(44 - 21 * $k), (int)(34 + 8 * $k)));
    }
    $green = imagecolorallocate($im, 52, 211, 153); $white = imagecolorallocate($im, 248, 250, 252); $soft = imagecolorallocate($im, 167, 243, 208);
    imagefilledrectangle($im, 80, 96, 86, 534, $green);
    imagettftext($im, 22, 0, 116, 140, $green, $font, strtoupper(mb_substr($category, 0, 40)));
    // Title: largest size that fits 4 lines in 1000 px.
    for ($size = 56; $size >= 30; $size -= 2) {
        $lines = []; $cur = '';
        foreach (preg_split('/\s+/', $title) as $w) {
            $try = $cur === '' ? $w : "$cur $w";
            $b = imagettfbbox($size, 0, $font, $try);
            if ($cur !== '' && $b[2] - $b[0] > 1000) { $lines[] = $cur; $cur = $w; } else $cur = $try;
        }
        if ($cur !== '') $lines[] = $cur;
        if (count($lines) <= 4) break;
    }
    $lines = array_slice($lines, 0, 4);
    foreach ($lines as $i => $l) imagettftext($im, $size, 0, 116, 220 + $i * (int)($size * 1.35), $white, $font, $l);
    imagettftext($im, 20, 0, 116, 560, $soft, $font, 'runonconsole.com');
    $dir = $root . '/uploads/blog-covers';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ok = imagejpeg($im, $dir . '/' . $slug . '.jpg', 84);
    imagedestroy($im);
    return $ok ? '/uploads/blog-covers/' . $slug . '.jpg' : $fallback;
}
$image = agentCover($ROOT, $slug, $title, $category);

/* The post, as "scheduled". */
$cols = agentCols($pdo, 'blogs');
$id = 'blog-' . bin2hex(random_bytes(4));
$now = gmdate('Y-m-d H:i:s');
$row = ['id' => $id, 'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'content' => $content, 'category' => $category,
        'author_name' => AGENT_AUTHOR, 'image' => $image, 'image_alt' => $alt, 'meta_title' => $metaT, 'meta_description' => $metaD,
        'focus_keyword' => $focus, 'read_time' => max(1, (int)ceil($words / 200)) . ' min read', 'status' => 'scheduled', 'version' => 1,
        'published_at' => $at, 'content_modified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        'og_title' => $metaT, 'og_description' => $metaD, 'og_image' => $image, 'og_type' => 'article', 'twitter_card' => 'summary_large_image',
        'twitter_title' => $metaT, 'twitter_description' => $metaD, 'twitter_image' => $image, 'is_noindex' => 0, 'is_nofollow' => 0];
$row = array_filter($row, function ($k) use ($cols) { return in_array($k, $cols, true); }, ARRAY_FILTER_USE_KEY);
$pdo->prepare('INSERT INTO blogs (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')
    ->execute(array_values($row));

// Short log for CMS > Blog agent (last 100).
$log = json_decode((string)agentSetting($pdo, 'blog_agent_log'), true) ?: [];
array_unshift($log, ['id' => $id, 'title' => $title, 'slug' => $slug, 'publish_at' => $at, 'words' => $words, 'created' => $now]);
agentSettingSet($pdo, 'blog_agent_log', (string)json_encode(array_slice($log, 0, 100), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$local = (new DateTime($at . ' UTC'))->setTimezone(new DateTimeZone(AGENT_TZ));
agentOut(201, ['success' => true, 'id' => $id, 'url' => 'https://runonconsole.com/blogs/' . $slug . '/', 'image' => $image, 'words' => $words,
    'publish_at' => $at . ' UTC', 'publish_local' => $local->format('D j M, H:i') . ' Pakistan time',
    'message' => 'Scheduled. It goes live at ' . $local->format('H:i') . ' Pakistan time (the server publishes it, the PC can be off).']);
