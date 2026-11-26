<?php
/**
 * Run On Console — "Rate my PC" badge for blogs and forums (Deploy 191).
 *   /api/v1/pc-badge.php?s=78&t=High-end  ->  a 240x64 SVG with the score and the tier.
 * Only a number (0-100) and one of the tier words are drawn, so the badge cannot be used to show other text.
 */
$s = max(0, min(100, (int)($_GET['s'] ?? 0)));
$tiers = ['Elite', 'High-end', 'Strong', 'Mid-range', 'Entry level', 'Low-end'];
$t = in_array((string)($_GET['t'] ?? ''), $tiers, true) ? (string)$_GET['t'] : ($s >= 90 ? 'Elite' : ($s >= 75 ? 'High-end' : ($s >= 60 ? 'Strong' : ($s >= 45 ? 'Mid-range' : ($s >= 25 ? 'Entry level' : 'Low-end')))));
$col = $s >= 75 ? '#10b981' : ($s >= 45 ? '#84cc16' : ($s >= 30 ? '#f59e0b' : '#ef4444'));
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
$w = 2.4 * $s;
echo '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="64" viewBox="0 0 240 64" role="img" aria-label="Gaming PC score ' . $s . ' out of 100, ' . $t . ', Run On Console">'
    . '<defs><linearGradient id="g" x1="0" x2="1"><stop offset="0" stop-color="#064e3b"/><stop offset="1" stop-color="#0d9488"/></linearGradient></defs>'
    . '<rect width="240" height="64" rx="12" fill="url(#g)"/>'
    . '<text x="14" y="40" font-family="Arial,Helvetica,sans-serif" font-size="28" font-weight="700" fill="#fff">' . $s . '</text>'
    . '<text x="' . ($s >= 100 ? 66 : ($s >= 10 ? 52 : 36)) . '" y="40" font-family="Arial,Helvetica,sans-serif" font-size="12" fill="#a7f3d0">/100</text>'
    . '<text x="96" y="24" font-family="Arial,Helvetica,sans-serif" font-size="13" font-weight="700" fill="#fff">' . htmlspecialchars($t) . ' gaming PC</text>'
    . '<text x="96" y="40" font-family="Arial,Helvetica,sans-serif" font-size="10" fill="#d1fae5">Rate my PC · runonconsole.com</text>'
    . '<rect x="14" y="50" width="212" height="5" rx="2.5" fill="rgba(255,255,255,.2)"/><rect x="14" y="50" width="' . round(212 * $s / 100, 1) . '" height="5" rx="2.5" fill="' . $col . '"/>'
    . '</svg>';
