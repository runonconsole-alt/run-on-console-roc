<?php
/**
 * Run On Console — give a picture to news and blog covers that show only the title (Deploy 53).
 *
 *   php cli-news-covers.php                  dry run: lists the covers without a picture
 *   php cli-news-covers.php --apply          new covers: an AI picture (Cloudflare) for each; when the free
 *                                            daily quota is used up, one of the site's own pictures for now
 *                                            (the cron job then swaps in an AI picture later)
 *   php cli-news-covers.php --slug=X --apply only that post (also when its cover already has a picture)
 *   php cli-news-covers.php --rollback=DIR   puts the old covers back
 *
 * A cover "without a picture" is the green title card: its right side is the plain green background.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$only = null; $rollback = null;
foreach ($args as $a) {
    if (strpos($a, '--slug=') === 0) $only = substr($a, 7);
    if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);
}

echo "Run On Console - covers without a picture\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/blog-engine-lib.php';
$cols = array_values(array_intersect(['image', 'og_image', 'twitter_image'], rocBlogCols($pdo, 'blogs')));

if ($rollback !== null) {
    $dir = rtrim($rollback, '/');
    $man = json_decode((string)@file_get_contents($dir . '/MANIFEST.json'), true);
    if (!is_array($man)) exit("No MANIFEST.json in {$dir}\n");
    $n = 0;
    foreach ($man['posts'] as $p) {
        $from = $dir . $p['old'];
        if (!is_file($from) || !copy($from, $ROOT . $p['old'])) continue;
        $pdo->prepare('UPDATE blogs SET ' . implode(' = ?, ', $cols) . ' = ? WHERE id = ?')
            ->execute(array_merge(array_fill(0, count($cols), $p['old']), [$p['id']]));
        if ($p['new'] !== $p['old'] && is_file($ROOT . $p['new'])) @unlink($ROOT . $p['new']);
        $n++;
    }
    exit("Put back {$n} cover(s).\n");
}

/** True when the cover is the green title card (no picture on its right side). */
function rocCoverTitleOnly(string $file): bool {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $im = $ext === 'webp' && function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : @imagecreatefromstring((string)@file_get_contents($file));
    if (!$im) return false;
    $W = imagesx($im); $H = imagesy($im);
    $sx = $W / 1200; $sy = $H / 630;
    $far = 0; $all = 0; $diff = 0; $pairs = 0;
    // The top and bottom of the right side: a picture or a product panel there, else only the background.
    // Two checks: the colour (close to the green background) and the texture (the background is smooth
    // apart from its grid lines, which are skipped; a dark photo is close in colour but never smooth).
    foreach (array_merge(range(8, 60, 4), range(585, 624, 4)) as $y) {
        $k = $y / 630;
        $bg = [3 + 4 * $k, 38 - 12 * $k, 33 - 6 * $k];                   // the gradient rocBlogCover() draws
        $prev = null;
        for ($x = 900; $x <= 1190; $x++) {
            if ($x % 40 < 4 || $x % 40 > 36 || $y % 40 < 3 || $y % 40 > 37) { $prev = null; continue; }
            $c = imagecolorat($im, (int)min($W - 1, $x * $sx), (int)min($H - 1, $y * $sy));
            $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
            if ($x % 9 === 0) { $all++; if (abs($r - $bg[0]) + abs($g - $bg[1]) + abs($b - $bg[2]) > 90) $far++; }
            if ($prev !== null) { $diff += abs($r + $g + $b - $prev); $pairs++; }
            $prev = $r + $g + $b;
        }
    }
    imagedestroy($im);
    return $all > 0 && $far / $all < 0.15 && $pairs > 0 && $diff / $pairs < 0.4;
}

$rows = $pdo->query("SELECT id, slug, title, category, image, image_alt, status FROM blogs WHERE status <> 'archived' ORDER BY published_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$todo = [];
foreach ($rows as $r) {
    $img = (string)strtok((string)$r['image'], '?');
    if ($only !== null) { if ($r['slug'] === $only) $todo[] = $r; continue; }
    if (strpos($img, '/uploads/blog-covers/') !== 0 || !is_file($ROOT . $img)) continue;
    if (rocCoverTitleOnly($ROOT . $img)) $todo[] = $r;
}
foreach ($todo as $r) echo '  -  ' . ($r['category'] === 'News' ? 'news ' : 'blog ') . $r['title'] . "\n";
echo "\n" . count($todo) . ' of ' . count($rows) . " cover(s) have no picture.\n";
if ($only !== null && !$todo) exit("No post with the address {$only}.\n");
if (!$apply || !$todo) { echo $apply ? '' : "Dry run: nothing changed. Run again with --apply.\n"; exit(0); }

$cf = rocBlogCf($pdo);
$dir = $HOME . '/roc-media-trash/covers-' . date('Ymd-His');
@mkdir($dir . '/uploads/blog-covers', 0755, true);
$done = []; $ai = 0; $later = 0;
foreach ($todo as $r) {
    $old = (string)strtok((string)$r['image'], '?');
    if ($old !== '' && is_file($ROOT . $old)) { @mkdir(dirname($dir . $old), 0755, true); copy($ROOT . $old, $dir . $old); }
    $img = null;
    if ($cf && function_exists('imagecreatefromstring')) {
        $res = rocCfImage($cf, rocBlogImagePrompt((string)$r['title'], ''));
        // Cloudflare's filter sometimes refuses a harmless prompt: another random one usually passes.
        for ($t = 0; $t < 2 && !$res['ok'] && stripos($res['error'], 'NSFW') !== false; $t++) $res = rocCfImage($cf, rocBlogImagePrompt('', ''));
        if ($res['ok'] && ($probe = @imagecreatefromstring($res['bytes']))) {
            $tmp = tempnam(sys_get_temp_dir(), 'roccov') . '.jpg';
            imagejpeg($probe, $tmp, 92); imagedestroy($probe);
            $img = rocBlogCover($ROOT, (string)$r['slug'], (string)$r['title'], (string)$r['category'], $tmp, true);
            @unlink($tmp);
            $ai++;
        } elseif (!$res['ok'] && stripos($res['error'], 'NSFW') !== false) {
            echo "  !  AI picture refused by Cloudflare's filter: a site picture for now, the cron job tries again\n"; $quota = true;
        } elseif (!$res['ok']) { echo "  !  AI picture: {$res['error']} (the others get a site picture for now)\n"; $cf = null; $quota = true; }
    }
    if ($img === null) {
        $pic = rocBlogStockPhoto($ROOT, (string)$r['slug']);
        if (!$pic) { echo "  !  no picture to use for {$r['title']}\n"; continue; }
        $img = rocBlogCover($ROOT, (string)$r['slug'], (string)$r['title'], (string)$r['category'], $pic, true);
        if ($cf || !empty($quota)) { rocBlogCoverRetryAdd($pdo, (string)$r['slug']); $later++; }
    }
    if (strpos($img, '/uploads/blog-covers/') !== 0) { echo "  !  could not draw the cover of {$r['title']}\n"; continue; }
    $pdo->prepare('UPDATE blogs SET ' . implode(' = ?, ', $cols) . ' = ?, updated_at = ? WHERE id = ?')
        ->execute(array_merge(array_fill(0, count($cols), $img), [gmdate('Y-m-d H:i:s'), $r['id']]));
    rocMediaAlt($pdo, $ROOT, $img, trim((string)$r['image_alt']) ?: $r['title'] . ' - Run On Console');
    $done[] = ['id' => $r['id'], 'old' => $old, 'new' => $img];
    echo "  ok {$r['title']}\n";
}
file_put_contents($dir . '/MANIFEST.json', json_encode(['made' => date('c'), 'posts' => $done], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "\nNew covers: " . count($done) . " ({$ai} with an AI picture" . ($later ? ", {$later} with a site picture for now: the cron job gives them an AI picture after the free quota is back" : '') . ").\n"
   . "Old covers kept in {$dir}\nRollback: php " . __FILE__ . " --rollback={$dir}\n";
