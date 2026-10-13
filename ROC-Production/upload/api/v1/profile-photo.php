<?php
/**
 * Run On Console — the signed-in user's own profile photo.
 *
 * POST (multipart) /api/v1/profile-photo.php          photo = JPG, PNG or WebP (up to 8 MB)
 * POST {action:'remove'} /api/v1/profile-photo.php    back to the letter badge
 *
 * The photo is cropped to a square, resized to 320 x 320 and saved as WebP in
 * /uploads/avatars/ under a random name; the old one is deleted. The address is kept
 * in user_profiles.avatar_url (added by cron/cli-profile-photos.php). Same session and
 * CSRF checks as profile.php.
 */

require_once __DIR__ . '/account-runtime.php';
require_once __DIR__ . '/gaming-profile.php';

header('Content-Type: application/json; charset=utf-8');

function rocPhotoOut(int $code, array $data): void {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    http_response_code($code);
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') rocPhotoOut(405, ['success' => false, 'error' => 'POST required.']);
$pdo = getDBConnection();
if (!$pdo) rocPhotoOut(503, ['success' => false, 'error' => 'The server is busy. Please try again in a minute.']);
if (!validateDatabaseSession()) rocPhotoOut(401, ['success' => false, 'error' => 'Please sign in again.']);
if (!validateCsrfToken()) rocPhotoOut(403, ['success' => false, 'error' => 'Invalid or missing CSRF token.']);
$userId = (int)$_SESSION['user_id'];

$dir = dirname(__DIR__, 2) . '/uploads/avatars';
$current = rocProfilePhotoUrl($pdo, $userId);
$deleteOld = function () use ($current, $dir) {
    if ($current !== '' && is_file($dir . '/' . basename($current))) @unlink($dir . '/' . basename($current));
};

$in = json_decode((string)file_get_contents('php://input'), true);
if (is_array($in) && ($in['action'] ?? '') === 'remove') {
    try { $pdo->prepare('UPDATE user_profiles SET avatar_url = NULL WHERE user_id = ?')->execute([$userId]); }
    catch (Throwable $e) { rocPhotoOut(500, ['success' => false, 'error' => 'Profile photos are not set up yet.']); }
    $deleteOld();
    rocPhotoOut(200, ['success' => true, 'avatarUrl' => '']);
}

$f = $_FILES['photo'] ?? null;
if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
    rocPhotoOut(400, ['success' => false, 'error' => 'No photo was received. Please try again.']);
}
if ($f['size'] > 8 * 1024 * 1024) rocPhotoOut(413, ['success' => false, 'error' => 'The photo is larger than 8 MB.']);
$info = @getimagesize($f['tmp_name']);
$mime = $info['mime'] ?? '';
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || empty($info[0]) || $info[0] > 8000 || $info[1] > 8000) {
    rocPhotoOut(415, ['success' => false, 'error' => 'Please choose a JPG, PNG or WebP photo.']);
}
if (!function_exists('imagewebp')) rocPhotoOut(500, ['success' => false, 'error' => 'Photo processing is not available on the server.']);

$src = $mime === 'image/png' ? @imagecreatefrompng($f['tmp_name']) : ($mime === 'image/webp' ? @imagecreatefromwebp($f['tmp_name']) : @imagecreatefromjpeg($f['tmp_name']));
if (!$src) rocPhotoOut(415, ['success' => false, 'error' => 'This photo could not be read. Please try another one.']);
// Phone photos: turn them the right way up.
if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
    $o = (int)(@exif_read_data($f['tmp_name'])['Orientation'] ?? 1);
    if ($o === 3) $src = imagerotate($src, 180, 0);
    elseif ($o === 6) $src = imagerotate($src, -90, 0);
    elseif ($o === 8) $src = imagerotate($src, 90, 0);
}
$w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
$out = imagecreatetruecolor(320, 320);
imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
imagecopyresampled($out, $src, 0, 0, (int)(($w - $side) / 2), (int)(($h - $side) / 2), 320, 320, $side, $side);
imagedestroy($src);

if (!is_dir($dir) && !@mkdir($dir, 0755, true)) rocPhotoOut(500, ['success' => false, 'error' => 'The photo could not be saved.']);
$name = bin2hex(random_bytes(16)) . '.webp';
$ok = imagewebp($out, $dir . '/' . $name, 82);
imagedestroy($out);
if (!$ok) rocPhotoOut(500, ['success' => false, 'error' => 'The photo could not be saved.']);
@chmod($dir . '/' . $name, 0644);

$url = '/uploads/avatars/' . $name;
try {
    $q = $pdo->prepare('UPDATE user_profiles SET avatar_url = ? WHERE user_id = ?');
    $q->execute([$url, $userId]);
    if (!$q->rowCount()) $pdo->prepare('INSERT INTO user_profiles (user_id, avatar_url) VALUES (?, ?)')->execute([$userId, $url]);
} catch (Throwable $e) {
    @unlink($dir . '/' . $name);
    rocPhotoOut(500, ['success' => false, 'error' => 'Profile photos are not set up yet.']);
}
$deleteOld();
rocPhotoOut(200, ['success' => true, 'avatarUrl' => $url]);
