<?php
/**
 * Run On Console — social kit pictures under 100 KB, with alt text (Deploy 82).
 *
 * The pictures in /uploads/social/ (1080x1920 Story and 1080x1080 post, made by CMS > Social posts)
 * were saved at JPEG quality 86, often 120-300 KB. They are only downloaded for posting, never shown
 * on the website, but the Media library counted them as heavy images without alt text.
 * This saves each one again under 100 KB (same name and address) and fills in its alt text.
 *
 *   php cli-social-images.php           dry run: what would change
 *   php cli-social-images.php --apply   saves them again (the old files go to ~/roc-media-trash/social-<date>/)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$apply = in_array('--apply', array_slice($argv, 1), true);

echo "Run On Console - social kit pictures under 100 KB\n\n";
if (!function_exists('imagecreatefromjpeg')) exit("PHP has no GD (imagecreatefromjpeg). Nothing changed.\n");
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if ($pdo) $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/blog-engine-lib.php';
require_once $ROOT . '/api/v1/cms/social-kit-lib.php';

// The Media library table (the same lookup as media-replace.php).
$table = null; $cols = [];
if ($pdo) foreach (['cms_media', 'media', 'media_library', 'cms_media_library'] as $t) {
    try {
        $st = $pdo->query("SELECT * FROM {$t} LIMIT 0");
        $c = []; for ($i = 0; $i < $st->columnCount(); $i++) $c[] = strtolower((string)$st->getColumnMeta($i)['name']);
        if (in_array('file_url', $c, true)) { $table = $t; $cols = $c; break; }
    } catch (\Throwable $e) {}
}

// Post titles for the alt text: the file is {post slug}-{story|square}.jpg.
$titles = [];
if ($pdo) try { foreach ($pdo->query('SELECT slug, title FROM blogs')->fetchAll(PDO::FETCH_ASSOC) as $r) $titles[$r['slug']] = $r['title']; } catch (\Throwable $e) {}

$files = glob($ROOT . '/uploads/social/*.jpg') ?: [];
$trash = $HOME . '/roc-media-trash/social-' . date('Ymd-His');
$big = 0; $saved = 0; $before = 0; $after = 0; $alts = 0;
foreach ($files as $f) {
    $name = basename($f);
    $url = '/uploads/social/' . $name;
    $slug = (string)preg_replace('/-(story|square|post)\.jpg$/', '', $name);
    $kind = preg_match('/-story\.jpg$/', $name) ? 'Story picture (1080x1920)' : 'Feed post picture (1080x1080)';
    $alt = mb_substr('Social media ' . strtolower($kind) . ': ' . ($titles[$slug] ?? ucfirst(str_replace('-', ' ', $slug))), 0, 250);
    $size = (int)filesize($f);
    if ($size > 102400) {
        $big++;
        $im = @imagecreatefromjpeg($f);
        if (!$im) { echo "  !  {$name}: could not be read\n"; continue; }
        $data = rocKitJpeg($im);
        imagedestroy($im);
        echo '  ' . ($apply ? '~' : '+') . "  {$name}: " . round($size / 1024) . ' KB -> ' . round(strlen($data) / 1024) . " KB\n";
        if ($apply && $data !== '' && strlen($data) < $size) {
            if (!is_dir($trash)) @mkdir($trash, 0700, true);
            @copy($f, $trash . '/' . $name);
            $tmp = $f . '.roc-tmp';
            if (@file_put_contents($tmp, $data) !== false && @rename($tmp, $f)) { $saved++; $before += $size; $after += strlen($data); $size = strlen($data); }
            else { @unlink($tmp); echo "     !  could not be saved\n"; }
        }
    }
    if ($apply && $table) {
        $set = ['file_size' => $size];
        if (in_array('alt_text', $cols, true)) {
            $st = $pdo->prepare("SELECT alt_text FROM {$table} WHERE file_url IN (?, ?) LIMIT 1");
            $st->execute([$url, 'https://runonconsole.com' . $url]);
            $old = $st->fetchColumn();
            if ($old !== false && trim((string)$old) === '') { $set['alt_text'] = $alt; $alts++; }
        }
        if (in_array('updated_at', $cols, true)) $set['updated_at'] = date('Y-m-d H:i:s');
        $set = array_intersect_key($set, array_flip($cols));
        if ($set) $pdo->prepare("UPDATE {$table} SET " . implode(', ', array_map(function ($c) { return "{$c} = ?"; }, array_keys($set))) . ' WHERE file_url IN (?, ?)')
            ->execute([...array_values($set), $url, 'https://runonconsole.com' . $url]);
    }
}
echo "\n" . count($files) . " social picture(s), {$big} over 100 KB.\n";
if (!$apply) exit("Dry run: nothing changed. Run again with --apply.\n");
echo "Saved {$saved} again: " . round($before / 1024) . ' KB -> ' . round($after / 1024) . " KB. Alt text filled in: {$alts}.\n"
   . ($saved ? "Old files: {$trash}\n" : '') . "New social pictures are made under 100 KB from now on.\n";
