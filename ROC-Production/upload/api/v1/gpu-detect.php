<?php
/**
 * Run On Console — "Detect my graphics card" (Deploy 153).
 *
 *   GET /api/v1/gpu-detect.php?r=<WebGL renderer string>
 *   -> {success, name, score, laptop}   score on the checker's 1.8-10 scale, null when the card is unknown
 *
 * The browser reads the graphics card's name through WebGL (public/roc-detect.js); this file only scores it with the
 * same rules as the Steam requirements (steam-req-lib.php), so the game pages and the checker pick the right tier.
 * Nothing is stored. The processor and RAM cannot be read reliably by a browser, so the visitor picks those.
 */

require_once __DIR__ . '/steam-req-lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('X-Robots-Tag: noindex');

$raw = mb_substr(trim((string)($_GET['r'] ?? '')), 0, 200);
$raw = (string)preg_replace('/[\x00-\x1F\x7F]/u', '', $raw);

/** "ANGLE (NVIDIA, NVIDIA GeForce RTX 3060 Laptop GPU (0x00002520) Direct3D11 vs_5_0 ps_5_0, D3D11)" -> "NVIDIA GeForce RTX 3060 Laptop GPU" */
function rocGdName(string $r): string {
    if (preg_match('/^ANGLE \(([^,]+),\s*(.+?)(?:\s*\(0x[0-9a-f]+\))?\s*(?:Direct3D|OpenGL|Vulkan|Metal|,|\)$)/i', $r, $m)) $r = $m[2];
    $r = (string)preg_replace('/\((?:R|TM)\)|®|™|\s*\/PCIe\/SSE2|\s*Direct3D.*$|\s*OpenGL.*$/i', '', $r);
    return trim((string)preg_replace('/\s+/', ' ', $r));
}

$name = rocGdName($raw);
$score = null;
if ($name !== '' && !preg_match('/swiftshader|llvmpipe|software|microsoft basic render/i', $name)) {
    $l = strtolower($name);
    $score = rocSrGpuOne($l);
    // Integrated AMD graphics in laptop and desktop processors (no model number in the name).
    if ($score === null && preg_match('/radeon\s*(?:\(tm\)\s*)?(\d{3})m\b/', $l, $m)) $score = (int)$m[1] >= 760 ? 3.5 : ((int)$m[1] >= 660 ? 3 : 1.8);
    if ($score === null && preg_match('/radeon\s*(?:\(tm\)\s*)?graphics|radeon vega|vega\s*\d+\s*graphics/', $l)) $score = 1.8;
    if ($score === null && preg_match('/intel.*(?:arc\s*graphics)/', $l)) $score = 3;   // Core Ultra built-in Arc
}

echo json_encode(['success' => true, 'name' => $name, 'score' => $score !== null ? (float)$score : null,
                  'laptop' => (bool)preg_match('/laptop|mobile|max-q|\bm\b|notebook/i', $name)], JSON_UNESCAPED_SLASHES);
