<?php
/**
 * Run On Console — draw the product name cards as WebP images.
 *
 *   php scripts/render-product-cards.php products.json public/images/products
 *
 * Called by scripts/generate-product-cards.mjs, which passes the catalog as JSON
 * ([{slug, title, brand, categorySlug}]). Needs PHP with GD (WebP + FreeType).
 * Fonts: Segoe UI Bold (Windows) or DejaVu Sans Bold (Linux), or set ROC_CARD_FONT.
 *
 * The layout matches the old SVG cards: everything that matters sits in the centre
 * band (about x 60-340, y 60-180 of 400x240), because cards are shown with
 * object-fit: cover in boxes of different shapes. Drawn at 2x for sharp screens.
 */

if (PHP_SAPI !== 'cli') exit(1);
[$self, $jsonFile, $outDir] = $argv + [null, null, null];
if (!$jsonFile || !$outDir) exit("usage: php render-product-cards.php products.json outdir\n");
if (!function_exists('imagewebp') || !function_exists('imagettftext')) exit("STOP: PHP GD with WebP and FreeType is needed.\n");

$font = getenv('ROC_CARD_FONT') ?: null;
foreach (['C:/Windows/Fonts/segoeuib.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf'] as $f) {
    if (!$font && is_file($f)) $font = $f;
}
if (!$font) exit("STOP: no bold font found; set ROC_CARD_FONT to a .ttf file.\n");

const S = 2;                    // drawing scale
const W = 400, H = 240;

$ACCENT = ['keyboards' => '34d399', 'audio' => '22d3ee', 'mice' => 'a3e635',
           'speakers' => 'fbbf24', 'monitors' => '60a5fa', 'gpu' => 'f472b6'];

function rgb($im, string $hex, int $alpha = 0) {
    return imagecolorallocatealpha($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha);
}

/** Faint category glyph, in the old SVG's 24x24 grid placed at (128,48) scaled 6x. */
function glyph($im, string $cat, $c): void {
    imagesetthickness($im, (int)round(1.2 * 6 * S));
    $p = function ($x, $y) { return [(int)round((128 + $x * 6) * S), (int)round((48 + $y * 6) * S)]; };
    $rect = function ($x, $y, $w, $h) use ($im, $c, $p) { [$a, $b] = $p($x, $y); [$d, $e] = $p($x + $w, $y + $h); imagerectangle($im, $a, $b, $d, $e, $c); };
    $line = function ($x1, $y1, $x2, $y2) use ($im, $c, $p) { [$a, $b] = $p($x1, $y1); [$d, $e] = $p($x2, $y2); imageline($im, $a, $b, $d, $e, $c); };
    $circle = function ($x, $y, $r) use ($im, $c, $p) {   // imageellipse ignores the thickness: draw rings
        [$a, $b] = $p($x, $y);
        for ($k = -7; $k <= 7; $k++) imageellipse($im, $a, $b, (int)($r * 12 * S) + $k, (int)($r * 12 * S) + $k, $c);
    };
    switch ($cat) {
        case 'audio':
            [$a, $b] = $p(12, 12);
            for ($k = -7; $k <= 7; $k++) imagearc($im, $a, $b, (int)(18 * 6 * S) + $k, (int)(18 * 6 * S) + $k, 180, 360, $c);
            $rect(2, 14, 5, 7); $rect(17, 14, 5, 7); break;
        case 'mice':     $rect(6, 2, 12, 20); $line(12, 6, 12, 10); break;
        case 'speakers': $rect(5, 2, 14, 20); $circle(12, 14, 4); break;
        case 'monitors': $rect(2, 3, 20, 14); $line(8, 21, 16, 21); $line(12, 17, 12, 21); break;
        case 'gpu':      $rect(2, 6, 20, 12); $circle(9, 12, 3); $circle(16, 12, 2); $line(6, 18, 6, 21); $line(10, 18, 10, 21); $line(14, 18, 14, 21); break;
        default:         $rect(2, 6, 20, 12); $line(7, 14, 17, 14); break;
    }
}

function textWidth(string $font, float $size, string $text, float $spacing = 0): float {
    $b = imagettfbbox($size, 0, $font, $text);
    return ($b[2] - $b[0]) + max(0, mb_strlen($text) - 1) * $spacing;
}

/** Greedy word wrap by measured width; the last line gets "…" when it does not fit. */
function wrap(string $font, float $size, string $text, float $max, int $maxLines): array {
    $words = preg_split('/\s+/', trim($text)) ?: [];
    $lines = []; $cur = '';
    foreach ($words as $w) {
        $next = $cur === '' ? $w : "$cur $w";
        if ($cur !== '' && textWidth($font, $size, $next) > $max) { $lines[] = $cur; $cur = $w; } else $cur = $next;
    }
    if ($cur !== '') $lines[] = $cur;
    if (count($lines) > $maxLines) {
        $lines = array_slice($lines, 0, $maxLines);
        $last = $lines[$maxLines - 1] . '…';
        while (textWidth($font, $size, $last) > $max && strpos($last, ' ') !== false) $last = preg_replace('/\s+\S+…$/u', '…', $last);
        $lines[$maxLines - 1] = $last;
    }
    return $lines;
}

function card(array $p, string $font, array $ACCENT): GdImage {
    $im = imagecreatetruecolor(W * S, H * S);
    imagealphablending($im, true);
    // Diagonal gradient #022c22 -> #0f172a.
    [$r1, $g1, $b1] = [0x02, 0x2c, 0x22]; [$r2, $g2, $b2] = [0x0f, 0x17, 0x2a];
    for ($y = 0; $y < H * S; $y++) for ($x = 0; $x < W * S; $x += 4) {
        $t = ($x / (W * S) + $y / (H * S)) / 2;
        $c = imagecolorallocate($im, (int)($r1 + ($r2 - $r1) * $t), (int)($g1 + ($g2 - $g1) * $t), (int)($b1 + ($b2 - $b1) * $t));
        imagefilledrectangle($im, $x, $y, $x + 3, $y, $c);
    }
    $accent = $ACCENT[$p['categorySlug'] ?? ''] ?? '34d399';
    // Glyph drawn solid on its own layer, then blended in at 12% (overlapping strokes stay even).
    $layer = imagecreatetruecolor(W * S, H * S);
    $key = imagecolorallocate($layer, 255, 0, 255);
    imagefill($layer, 0, 0, $key);
    imagecolortransparent($layer, $key);
    glyph($layer, (string)($p['categorySlug'] ?? ''), rgb($layer, $accent));
    imagecopymerge($im, $layer, 0, 0, 0, 0, W * S, H * S, 12);
    imagedestroy($layer);

    $brand = strtoupper(trim((string)($p['brand'] ?? '')));
    $title = (string)$p['title'];
    $name = $brand !== '' ? preg_replace('/^' . preg_quote(trim((string)$p['brand']), '/') . '\s+/i', '', $title) : $title;
    if (trim((string)$name) === '') $name = $title;

    // Name: as large as fits 270px wide in up to 3 lines (34px for 1-2 lines, 28px for 3, at least 18px).
    $size = 34;
    do {
        $pt = $size * S * 0.75;   // GD sizes are in points
        $lines = wrap($font, $pt, $name, 270 * S, 3);
        $cap = count($lines) > 2 ? 28 : 34;
        $fits = $size <= $cap && max(array_map(function ($l) use ($font, $pt) { return textWidth($font, $pt, $l); }, $lines)) <= 270 * S;
        if ($fits || $size <= 18) break;
        $size--;
    } while (true);
    $lineH = $size * 1.15;
    $blockH = 22 + count($lines) * $lineH;
    $top = 120 - $blockH / 2;

    // Brand line, letter-spaced.
    if ($brand !== '') {
        $bpt = 13 * S * 0.75; $sp = 1.5 * S;
        $x = (W * S - textWidth($font, $bpt, $brand, $sp)) / 2;
        $col = rgb($im, $accent);
        $chars = preg_split('//u', $brand, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $i => $ch) {
            // Position each letter by the measured width of the text before it, plus the spacing.
            $before = $i ? textWidth($font, $bpt, implode('', array_slice($chars, 0, $i)) . 'x') - textWidth($font, $bpt, 'x') : 0;
            imagettftext($im, $bpt, 0, (int)round($x + $before + $i * $sp), (int)round(($top + 14) * S), $col, $font, $ch);
        }
    }
    $white = rgb($im, 'f8fafc');
    foreach ($lines as $i => $l) {
        $x = (W * S - textWidth($font, $pt, $l)) / 2;
        imagettftext($im, $pt, 0, (int)round($x), (int)round(($top + 22 + ($i + 0.8) * $lineH) * S), $white, $font, $l);
    }
    return $im;
}

$products = json_decode((string)file_get_contents($jsonFile), true);
if (!is_array($products)) exit("STOP: could not read $jsonFile\n");
if (!is_dir($outDir)) mkdir($outDir, 0775, true);
$written = 0;
foreach ($products as $p) {
    $im = card($p, $font, $ACCENT);
    ob_start(); imagewebp($im, null, 82); $data = ob_get_clean();
    imagedestroy($im);
    $file = rtrim($outDir, '/\\') . '/' . $p['slug'] . '.webp';
    if (!is_file($file) || file_get_contents($file) !== $data) { file_put_contents($file, $data); $written++; }
}
echo "Product cards: " . count($products) . " products, $written WebP file(s) written to $outDir\n";
