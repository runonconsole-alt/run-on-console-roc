<?php
/**
 * Run On Console (ROC) — counts visits that come from social media (Deploy 74).
 *
 * POST {src, path, camp}: roc-nav.js sends it once per visit when the visitor came from a social site
 * (the referrer) or a link with utm_medium=social. Only counts per day, platform and page are kept
 * (table roc_social_visits): no IP, no cookie, nothing about the person. CMS > Social posts shows them.
 */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

const ROC_SOCIAL_SOURCES = ['facebook', 'instagram', 'x', 'linkedin', 'pinterest', 'tiktok', 'youtube', 'reddit', 'quora', 'whatsapp', 'feed', 'other'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo '{"success":false}'; exit; }
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$src = strtolower(trim((string)($in['src'] ?? '')));
$path = (string)($in['path'] ?? '/');
if (!in_array($src, ROC_SOCIAL_SOURCES, true) || !preg_match('#^/[a-z0-9/_\-]{0,190}$#i', $path)) { http_response_code(422); echo '{"success":false}'; exit; }

$pdo = getDBConnection();
if (!$pdo) { http_response_code(503); echo '{"success":false}'; exit; }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try { $pdo->query('SELECT 1 FROM roc_social_visits LIMIT 1'); }
catch (\Throwable $e) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS roc_social_visits (day DATE NOT NULL, source VARCHAR(20) NOT NULL, path VARCHAR(200) NOT NULL, visits INT NOT NULL DEFAULT 0,
                PRIMARY KEY (day, source, path))' . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'));
}
$day = (new DateTime('now', new DateTimeZone('Asia/Karachi')))->format('Y-m-d');
$up = $pdo->prepare('UPDATE roc_social_visits SET visits = visits + 1 WHERE day = ? AND source = ? AND path = ?');
$up->execute([$day, $src, $path]);
if ($up->rowCount() === 0) {
    try { $pdo->prepare('INSERT INTO roc_social_visits (day, source, path, visits) VALUES (?, ?, ?, 1)')->execute([$day, $src, $path]); }
    catch (\Throwable $e) { $up->execute([$day, $src, $path]); }   // two visits at the same moment
}
echo '{"success":true}';
