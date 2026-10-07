<?php
/**
 * Run On Console — replace an image in the Media library.
 *
 * POST (multipart) /api/v1/cms/media-replace.php
 *   file_url  the image to replace, e.g. /images/hero.jpg or /uploads/photo.webp
 *   file      the new image: JPG, PNG or WebP only (up to 16 MB)
 *
 * Site images and CMS uploads keep their URL: the new picture is written over the
 * old file in the old file's format, resized and compressed to stay under 100 KB
 * where the format allows, so every page and template that uses it keeps working.
 * The old file is kept in ~/roc-media-trash/replaced-<date>/ first.
 *
 * Product name cards (/images/products/{slug}.webp) are part of the website build
 * and are drawn again on every deploy, so for those the new picture is saved as the
 * product's own photo (/uploads/products/{slug}.webp) and set on the product.
 */

require_once __DIR__ . '/config.php';

$session = requireCmsPermission('media', 'upload');
header('Content-Type: application/json; charset=utf-8');

function rocReplaceOut(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') rocReplaceOut(405, ['success' => false, 'error' => 'Use POST.']);
// requireCmsPermission checks the CSRF token; a missing header is refused here as well.
$sentCsrf = (string)($_SERVER['HTTP_X_CMS_CSRF_TOKEN'] ?? '');
if ($sentCsrf === '' || (is_array($session) && !empty($session['csrf_token']) && !hash_equals((string)$session['csrf_token'], $sentCsrf))) {
    rocReplaceOut(403, ['success' => false, 'error' => 'Invalid or missing CMS CSRF token.']);
}

const ROC_MAX_BYTES = 102400;           // the site's 100 KB image budget
const ROC_MAX_WIDTH = 1600;
const ROC_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

$ROOT = dirname(__DIR__, 3);

/* ------------------------------------------------------------- the old image */

$url  = trim((string)($_POST['file_url'] ?? ''));
$path = (string)parse_url($url, PHP_URL_PATH);
if (!preg_match('#^/(images|uploads)/[A-Za-z0-9_.\/-]+\.(jpe?g|png|webp|svg)$#i', $path) || strpos($path, '..') !== false) {
    rocReplaceOut(422, ['success' => false, 'error' => 'Only images in /images/ or /uploads/ can be replaced.']);
}
$target = $ROOT . $path;
$real = realpath($target);
$allowed = array_filter([realpath($ROOT . '/images'), realpath($ROOT . '/uploads')]);
$inside = false;
foreach ($allowed as $dir) if ($real && strpos($real, $dir . DIRECTORY_SEPARATOR) === 0) $inside = true;
if (!$real || !is_file($real) || !$inside) rocReplaceOut(404, ['success' => false, 'error' => 'That image was not found on the server.']);

/* ------------------------------------------------------------- the new image */

$f = $_FILES['file'] ?? null;
if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
    rocReplaceOut(400, ['success' => false, 'error' => 'No image was received. Try again.']);
}
if ($f['size'] > 16 * 1024 * 1024) rocReplaceOut(413, ['success' => false, 'error' => 'The image is larger than 16 MB.']);
$info = @getimagesize($f['tmp_name']);
$mime = $info['mime'] ?? '';
if (!isset(ROC_TYPES[$mime]) || empty($info[0]) || empty($info[1]) || $info[0] > 12000 || $info[1] > 12000) {
    rocReplaceOut(415, ['success' => false, 'error' => 'Only JPG, PNG or WebP images can be used.']);
}

/** Image data in $format, resized to at most ROC_MAX_WIDTH and squeezed under 100 KB where possible. */
function rocEncode(string $file, string $mime, string $format, array &$notes): ?string {
    if (!function_exists('imagecreatetruecolor')) return null;
    $src = $mime === 'image/png' ? @imagecreatefrompng($file) : ($mime === 'image/webp' ? @imagecreatefromwebp($file) : @imagecreatefromjpeg($file));
    if (!$src) return null;
    $w = imagesx($src); $h = imagesy($src);
    $width = min($w, ROC_MAX_WIDTH);
    $data = null;
    for ($try = 0; $try < 6; $try++) {
        $height = (int)round($h * $width / $w);
        $im = imagecreatetruecolor($width, $height);
        if ($format === 'jpg') {
            imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));   // JPG has no transparency
        } else {
            imagealphablending($im, false); imagesavealpha($im, true);
        }
        imagecopyresampled($im, $src, 0, 0, 0, 0, $width, $height, $w, $h);
        foreach ($format === 'png' ? [9] : [82, 74, 66, 58, 50] as $q) {
            ob_start();
            if ($format === 'jpg') imagejpeg($im, null, $q);
            elseif ($format === 'webp') imagewebp($im, null, $q);
            else imagepng($im, null, $q);
            $data = (string)ob_get_clean();
            if (strlen($data) <= ROC_MAX_BYTES) break;
        }
        imagedestroy($im);
        if (strlen($data) <= ROC_MAX_BYTES || $width <= 600) break;
        $width = max(600, (int)round($width * 0.8));
    }
    imagedestroy($src);
    if ($width < $w) $notes[] = "Resized to {$width} px wide.";
    if (strlen($data) > ROC_MAX_BYTES) $notes[] = 'Still over 100 KB' . ($format === 'png' ? ' (PNG keeps every detail; a JPG or WebP version would be smaller).' : '.');
    return $data;
}

function rocMediaTable(PDO $pdo): ?array {
    foreach (['cms_media', 'media', 'media_library', 'cms_media_library'] as $t) {
        try {
            $st = $pdo->query("SELECT * FROM {$t} LIMIT 0");
            $cols = [];
            for ($i = 0; $i < $st->columnCount(); $i++) $cols[] = strtolower((string)$st->getColumnMeta($i)['name']);
            if (in_array('file_url', $cols, true) && in_array('id', $cols, true)) return [$t, $cols];
        } catch (\Throwable $e) {}
    }
    return null;
}

/** Copy the file that is about to be overwritten to ~/roc-media-trash/replaced-<date>/. */
function rocBackup(string $root, string $path): void {
    $file = $root . $path;
    if (!is_file($file)) return;
    $dir = dirname($root) . '/roc-media-trash/replaced-' . date('Ymd-His') . dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    @copy($file, $dir . '/' . basename($path));
}

function rocWriteAtomic(string $file, string $data): bool {
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
    $tmp = $dir . '/.roc-replace-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $data) === false) return false;
    @chmod($tmp, 0644);
    if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
    return true;
}

$notes = [];
$pdo = getDBConnection();

/* ------------------------------------------- product name card -> product photo */

if (preg_match('#^/images/products/([a-z0-9-]+)\.(webp|svg)$#', $path, $m) && $pdo) {
    $st = $pdo->prepare('SELECT id, version FROM products WHERE slug = ? LIMIT 1');
    $st->execute([$m[1]]);
    $product = $st->fetch(PDO::FETCH_ASSOC);
    if ($product) {
        $data = rocEncode($f['tmp_name'], $mime, 'webp', $notes);
        if ($data === null) rocReplaceOut(500, ['success' => false, 'error' => 'The server could not read this image.']);
        $newPath = '/uploads/products/' . $m[1] . '.webp';
        rocBackup($ROOT, $newPath);
        if (!rocWriteAtomic($ROOT . $newPath, $data)) rocReplaceOut(500, ['success' => false, 'error' => 'The image could not be saved on the server.']);
        $now = date('Y-m-d H:i:s');
        $cols = [];
        $q = $pdo->query('SELECT * FROM products LIMIT 0');
        for ($i = 0; $i < $q->columnCount(); $i++) $cols[] = strtolower((string)$q->getColumnMeta($i)['name']);
        $set = array_intersect_key(['image' => $newPath, 'updated_at' => $now, 'content_modified_at' => $now], array_flip($cols));
        $pdo->prepare('UPDATE products SET ' . implode(', ', array_map(function ($c) { return "{$c} = ?"; }, array_keys($set)))
                    . (in_array('version', $cols, true) ? ', version = version + 1' : '') . ' WHERE id = ?')
            ->execute([...array_values($set), $product['id']]);
        if (function_exists('logCmsAudit')) logCmsAudit('cms_media_replace', 'product', (string)$product['id'], ['card' => $path, 'image' => $newPath]);
        [$w, $h] = @getimagesize($ROOT . $newPath) ?: [null, null];
        rocReplaceOut(200, ['success' => true, 'file_url' => $newPath, 'product_photo' => true,
            'file_size' => strlen($data), 'width' => $w, 'height' => $h,
            'message' => 'Saved as this product\'s photo (' . $newPath . '). Product pages show it now; the name card stays as a fallback.',
            'notes' => $notes]);
    }
}

/* ------------------------------------------------- any other image: same URL */

$format = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$format = $format === 'jpeg' ? 'jpg' : $format;
if ($format === 'svg') rocReplaceOut(422, ['success' => false, 'error' => 'SVG images cannot be replaced here.']);

$data = rocEncode($f['tmp_name'], $mime, $format, $notes);
if ($data === null) {
    // No GD on the server: only a file already in the right format can be used as it is.
    if (ROC_TYPES[$mime] !== $format) rocReplaceOut(422, ['success' => false, 'error' => 'Upload a ' . strtoupper($format) . ' file to replace this ' . strtoupper($format) . ' image.']);
    $data = (string)file_get_contents($f['tmp_name']);
    if (strlen($data) > ROC_MAX_BYTES) $notes[] = 'Over 100 KB: a smaller file would load faster.';
}
rocBackup($ROOT, $path);
if (!rocWriteAtomic($real, $data)) rocReplaceOut(500, ['success' => false, 'error' => 'The image could not be saved on the server.']);
clearstatcache(true, $real);
[$w, $h] = @getimagesize($real) ?: [null, null];

if ($pdo && ($t = rocMediaTable($pdo))) {
    [$table, $cols] = $t;
    $set = ['file_size' => strlen($data), 'width' => $w, 'height' => $h, 'mime_type' => 'image/' . ($format === 'jpg' ? 'jpeg' : $format)];
    if (in_array('updated_at', $cols, true)) $set['updated_at'] = date('Y-m-d H:i:s');
    $set = array_intersect_key($set, array_flip($cols));
    if ($set) {
        $pdo->prepare("UPDATE {$table} SET " . implode(', ', array_map(function ($c) { return "{$c} = ?"; }, array_keys($set))) . ' WHERE file_url IN (?, ?)')
            ->execute([...array_values($set), $path, 'https://runonconsole.com' . $path]);
    }
}
if (function_exists('logCmsAudit')) logCmsAudit('cms_media_replace', 'media', $path, ['bytes' => strlen($data)]);

rocReplaceOut(200, ['success' => true, 'file_url' => $path, 'file_size' => strlen($data), 'width' => $w, 'height' => $h,
    'message' => 'Image replaced. Same URL, so every page using it shows the new picture (press Ctrl+F5 if your browser still shows the old one).',
    'notes' => $notes]);
