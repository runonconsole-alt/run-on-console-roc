<?php
/**
 * Run On Console (ROC) - CMS OWASP Secure Media Manager API
 * GET    /api/v1/cms/media.php (List media / search)
 * POST   /api/v1/cms/media.php (Upload image / crop with presets / scoped replace)
 */

require_once __DIR__ . '/config.php';

$session = requireCmsSession(); // Administrator or Editor
$pdo = getDBConnection();

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uploadsDir = dirname(__DIR__, 3) . '/uploads';

if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM cms_media ORDER BY id DESC LIMIT 100");
    $media = $stmt->fetchAll();
    echo json_encode(['success' => true, 'media' => $media]);
    exit();
}

if ($method === 'POST') {
    // 1. Check file upload presence
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No valid file uploaded or upload error occurred.']);
        exit();
    }

    $tmpPath = $_FILES['file']['tmp_name'];
    $origName = basename($_FILES['file']['name']);
    $fileSize = $_FILES['file']['size'];

    // 2. Executable & Extension Security Checks
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $blockedExts = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'pl', 'py', 'jsp', 'asp', 'aspx', 'sh', 'cgi', 'exe', 'bat', 'cmd', 'svg'];

    if (in_array($ext, $blockedExts, true) || str_contains($origName, "\0") || str_contains(strtolower($origName), '.php')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Executable or SVG file format rejected for security.']);
        exit();
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimes, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file MIME type. Allowed: JPEG, PNG, WebP, GIF.']);
        exit();
    }

    // 3. Decompression Bomb & Memory Limit Safety Check
    $imgInfo = @getimagesize($tmpPath);
    if (!$imgInfo) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Failed to parse image headers. File corrupted.']);
        exit();
    }

    $width = $imgInfo[0];
    $height = $imgInfo[1];
    if (($width * $height) > 64000000) { // Max 8000x8000 pixels
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Image dimensions exceed maximum allowed limit (8000x8000).']);
        exit();
    }

    // 4. Secure Filename Generation & GD Re-Encoding to Strip EXIF/Polyglots
    $randomHex = bin2hex(random_bytes(10));
    $newFilename = "img_" . date('Ymd_His') . "_{$randomHex}.webp";
    $targetPath = $uploadsDir . '/' . $newFilename;
    $baseUrl = defined('ROC_SITE_URL') ? rtrim(ROC_SITE_URL, '/') : '';
    $fileUrl = "/uploads/" . $newFilename;

    $sourceImg = null;
    switch ($mimeType) {
        case 'image/jpeg': $sourceImg = @imagecreatefromjpeg($tmpPath); break;
        case 'image/png':  $sourceImg = @imagecreatefrompng($tmpPath);  break;
        case 'image/webp': $sourceImg = @imagecreatefromwebp($tmpPath); break;
        case 'image/gif':  $sourceImg = @imagecreatefromgif($tmpPath);  break;
    }

    if (!$sourceImg) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to process image binary safely.']);
        exit();
    }

    // Convert and save as WebP with iterative compression and scaling enforcing <= 100,000 bytes (100KB max)
    imagepalettetotruecolor($sourceImg);
    imagealphablending($sourceImg, true);
    imagesavealpha($sourceImg, true);

    $currentImg = $sourceImg;
    $currWidth = $width;
    $currHeight = $height;
    $maxBytes = 100000; // 100KB max limit
    $quality = 85;

    while ($quality >= 20) {
        imagewebp($currentImg, $targetPath, $quality);
        clearstatcache(true, $targetPath);
        if (filesize($targetPath) <= $maxBytes) {
            break;
        }
        $quality -= 15;
    }

    // Downscale if still over 100KB limit
    while (filesize($targetPath) > $maxBytes && ($currWidth > 100 && $currHeight > 100)) {
        $newW = (int)round($currWidth * 0.8);
        $newH = (int)round($currHeight * 0.8);
        $scaledImg = imagecreatetruecolor($newW, $newH);
        imagealphablending($scaledImg, false);
        imagesavealpha($scaledImg, true);
        imagecopyresampled($scaledImg, $currentImg, 0, 0, 0, 0, $newW, $newH, $currWidth, $currHeight);

        if ($currentImg !== $sourceImg) {
            imagedestroy($currentImg);
        }
        $currentImg = $scaledImg;
        $currWidth = $newW;
        $currHeight = $newH;

        $quality = 75;
        while ($quality >= 20) {
            imagewebp($currentImg, $targetPath, $quality);
            clearstatcache(true, $targetPath);
            if (filesize($targetPath) <= $maxBytes) {
                break;
            }
            $quality -= 15;
        }
    }

    if ($currentImg !== $sourceImg) {
        imagedestroy($currentImg);
    }
    imagedestroy($sourceImg);

    $finalSize = filesize($targetPath);
    $width = $currWidth;
    $height = $currHeight;

    // Save record to DB
    $stmtIns = $pdo->prepare("INSERT INTO cms_media (filename, original_name, filepath, file_url, mime_type, file_size, width, height, alt_text, created_at) VALUES (?, ?, ?, ?, 'image/webp', ?, ?, ?, ?, NOW())");
    $stmtIns->execute([$newFilename, $origName, $targetPath, $fileUrl, $finalSize, $width, $height, pathinfo($origName, PATHINFO_FILENAME)]);
    $mediaId = (int)$pdo->lastInsertId();

    logCmsAudit('cms_media_upload', 'media', (string)$mediaId, ['filename' => $newFilename, 'original_name' => $origName]);

    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded and re-encoded to WebP successfully.',
        'media' => [
            'id' => $mediaId,
            'filename' => $newFilename,
            'url' => $fileUrl,
            'width' => $width,
            'height' => $height,
            'size' => $finalSize
        ]
    ]);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
