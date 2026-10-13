<?php
/**
 * Run On Console — draw the logo (public/favicon.svg) as PNG files, for places that
 * do not take SVG: the Organization logo in structured data (Google wants PNG/JPG,
 * at least 112 x 112) and the iPhone home-screen icon.
 *
 *   php scripts/render-logo.php public/images
 *
 * Writes logo-512.png (rounded tile on transparent) and apple-touch-icon.png
 * (180 x 180, full-bleed, as iOS rounds the corners itself). Drawn at 4x and
 * scaled down for smooth edges.
 */

if (PHP_SAPI !== 'cli') exit(1);
$out = rtrim($argv[1] ?? '', '/\\');
if ($out === '' || !is_dir($out)) exit("usage: php render-logo.php outdir\n");

/** Points along the controller outline (favicon.svg path), in the 100 x 100 grid. */
function outline(): array {
    $segs = [
        [[12, 28], [8, 28], [4, 36], [2, 54]],   [[2, 54], [0, 72], [8, 88], [22, 88]],
        [[22, 88], [30, 88], [36, 78], [44, 68]], [[44, 68], [56, 68]],
        [[56, 68], [64, 78], [70, 88], [78, 88]], [[78, 88], [92, 88], [100, 72], [98, 54]],
        [[98, 54], [96, 36], [92, 28], [88, 28]], [[88, 28], [76, 28], [68, 36], [50, 36]],
        [[50, 36], [32, 36], [24, 28], [12, 28]],
    ];
    $pts = [];
    foreach ($segs as $s) {
        for ($i = 0; $i <= 60; $i++) {
            $t = $i / 60; $u = 1 - $t;
            if (count($s) === 2) { $pts[] = [$s[0][0] + ($s[1][0] - $s[0][0]) * $t, $s[0][1] + ($s[1][1] - $s[0][1]) * $t]; continue; }
            $pts[] = [
                $u * $u * $u * $s[0][0] + 3 * $u * $u * $t * $s[1][0] + 3 * $u * $t * $t * $s[2][0] + $t * $t * $t * $s[3][0],
                $u * $u * $u * $s[0][1] + 3 * $u * $u * $t * $s[1][1] + 3 * $u * $t * $t * $s[2][1] + $t * $t * $t * $s[3][1],
            ];
        }
    }
    return $pts;
}

/** Draw the tile at $size px; $bleed = square tile without rounded corners. */
function logo(int $size, bool $bleed): GdImage {
    $S = $size * 4;
    $k = $S / 100;                                  // svg unit -> px
    $im = imagecreatetruecolor($S, $S);
    imagealphablending($im, false); imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);

    // Rounded tile (x 4..96, r 28) with a diagonal gradient #14B8A6 -> #059669.
    [$x0, $x1, $r] = $bleed ? [0, 100, 0] : [4, 96, 28];
    for ($py = 0; $py < $S; $py++) {
        for ($px = 0; $px < $S; $px++) {
            $x = $px / $k; $y = $py / $k;
            if ($x < $x0 || $x > $x1 || $y < $x0 || $y > $x1) continue;
            $cx = max($x0 + $r, min($x1 - $r, $x)); $cy = max($x0 + $r, min($x1 - $r, $y));
            if ($r && ($x - $cx) ** 2 + ($y - $cy) ** 2 > $r * $r) continue;
            $t = max(0, min(1, ($x + $y) / 200));
            imagesetpixel($im, $px, $py, imagecolorallocate($im,
                (int)(0x14 + (0x05 - 0x14) * $t), (int)(0xB8 + (0x96 - 0xB8) * $t), (int)(0xA6 + (0x69 - 0xA6) * $t)));
        }
    }

    // Controller: translate(20,22) scale(0.6), white stroke 7 with round caps and joins.
    $white = imagecolorallocate($im, 255, 255, 255);
    $map = function ($x, $y) use ($k) { return [(20 + $x * 0.6) * $k, (22 + $y * 0.6) * $k]; };
    $dot = function ($x, $y, $w) use ($im, $white, $map, $k) { [$a, $b] = $map($x, $y); $d = (int)round($w * 0.6 * $k); imagefilledellipse($im, (int)round($a), (int)round($b), $d, $d, $white); };
    foreach (outline() as [$x, $y]) $dot($x, $y, 7);
    for ($i = 0; $i <= 40; $i++) { $dot(26, 44 + $i / 2, 7); $dot(16 + $i / 2, 54, 7); }   // d-pad
    foreach ([[74, 48], [84, 58], [64, 58], [74, 68]] as [$x, $y]) $dot($x, $y, 9);        // buttons (r 4.5)

    $small = imagecreatetruecolor($size, $size);
    imagealphablending($small, false); imagesavealpha($small, true);
    imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
    imagecopyresampled($small, $im, 0, 0, 0, 0, $size, $size, $S, $S);
    imagedestroy($im);
    return $small;
}

$a = logo(512, false); imagepng($a, $out . '/logo-512.png', 9); imagedestroy($a);
$b = logo(180, true);  imagepng($b, $out . '/apple-touch-icon.png', 9); imagedestroy($b);
echo "Wrote {$out}/logo-512.png and {$out}/apple-touch-icon.png\n";
