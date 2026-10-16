<?php
/**
 * Run On Console — read a Steam "System Requirements" block and score it on the checker's scale
 * (Deploy 87). The PHP copy of scripts/steam-requirements.mjs, so the server can add new games itself
 * (cli-steam-update.php); both give the same result (checked on every cached game).
 *
 *   GPU  1.8 integrated · 4.5 GTX 1650 / 1060 / RX 580 · 7 RTX 3060 / 4060 / RX 6600 XT
 *        8.5 RTX 4070 / 3080 / RX 7800 XT · 10 RTX 4080 / 4090 / RX 7900
 *   CPU  2 dual-core · 4.5 older quad-core · 7 i5 10th-13th gen / Ryzen 5 5600X · 8.5 i7 12th gen+ · 10 i9 / Ryzen 9
 *   RAM  in GB
 */
if (defined('ROC_STEAM_REQ_LIB')) return;
define('ROC_STEAM_REQ_LIB', 1);

function rocSrDecode(string $s): string {
    $s = (string)preg_replace('/<br\s*\/?>/i', "\n", $s);
    $s = (string)preg_replace('/<\/li>/i', "\n", $s);
    $s = (string)preg_replace('/<[^>]+>/', '', $s);
    $s = str_replace(['&nbsp;', '&amp;', '&quot;', '&#39;', '&apos;', '&lt;', '&gt;'], [' ', '&', '"', "'", "'", '<', '>'], $s);
    return (string)preg_replace('/®|™|\(R\)|\(TM\)/iu', '', $s);
}

/** {os, cpu, ram, gpu, storage} from the HTML of one block (minimum or recommended). */
function rocSrReadBlock(string $html): array {
    $text = rocSrDecode($html);
    $field = function (string $label) use ($text): string {
        if (!preg_match('/(?:^|\n)\s*(?:' . $label . ')\s*:\s*([^\n]+)/iu', $text, $m)) return '';
        return mb_substr(trim((string)preg_replace('/\s+/u', ' ', trim($m[1]))), 0, 220);
    };
    return ['os' => $field('OS'), 'cpu' => $field('Processor'), 'ram' => $field('Memory'), 'gpu' => $field('Graphics|Video Card|Video'), 'storage' => $field('Storage|Hard Drive|Hard Disk Space')];
}

function rocSrMax(array $xs) { return $xs ? max($xs) : null; }

function rocSrGpu(string $s) {
    $s = mb_strtolower($s);
    $s = (string)preg_replace('/(?:nvidia|geforce)\s+(?=(?:20|30|40|50)\d0\b)/u', ' rtx ', $s);
    $s = (string)preg_replace('/(?:nvidia|geforce)\s+(?=(?:9[5-8]0|10[5-8]0|16[3-6]0)\b)/u', ' gtx ', $s);
    $s = (string)preg_replace('/geforce|nvidia|amd|radeon|intel|graphics card|®|™/u', ' ', $s);
    $f = [];
    $RTX = [2050 => 4.5, 2060 => 6, 2070 => 7, 2080 => 8, 3050 => 4.5, 3060 => 7, 3070 => 8, 3080 => 8.5, 3090 => 10,
            4050 => 6, 4060 => 7, 4070 => 8.5, 4080 => 10, 4090 => 10, 5050 => 6.5, 5060 => 8, 5070 => 9, 5080 => 10, 5090 => 10];
    $RTX_TI = [2080 => 8.5, 3060 => 8, 3070 => 8.5, 3080 => 9, 4060 => 8, 4070 => 9, 4080 => 10, 5060 => 8.5, 5070 => 9.5, 2060 => 6.5, 2070 => 7];
    if (preg_match_all('/rtx\s*(\d{4})\s*(ti|super)?/u', $s, $mm, PREG_SET_ORDER)) foreach ($mm as $m) { $n = (int)$m[1]; if (isset($RTX[$n])) $f[] = !empty($m[2]) ? ($RTX_TI[$n] ?? $RTX[$n]) : $RTX[$n]; }
    $GTX = [650 => 2, 660 => 2.5, 670 => 3, 680 => 3, 750 => 2.5, 760 => 3, 770 => 3, 780 => 3.5, 950 => 3, 960 => 3.5, 970 => 4, 980 => 4.5,
            1050 => 3, 1060 => 4.5, 1070 => 5, 1080 => 6, 1630 => 3, 1650 => 4.5, 1660 => 4.5];
    $GTX_TI = [750 => 2.5, 780 => 4, 980 => 5, 1050 => 3.5, 1070 => 5.5, 1080 => 7, 1650 => 4.5, 1660 => 5];
    if (preg_match_all('/gtx\s*(\d{3,4})\s*(ti|super)?/u', $s, $mm, PREG_SET_ORDER)) foreach ($mm as $m) { $n = (int)$m[1]; if (isset($GTX[$n])) $f[] = !empty($m[2]) ? ($GTX_TI[$n] ?? $GTX[$n]) : $GTX[$n]; }
    if (preg_match('/\bgt\s*(1030|730|710)\b/u', $s)) $f[] = 1.8;
    if (preg_match('/titan\s*x/u', $s)) $f[] = 5;
    $RX = [460 => 2.5, 470 => 4, 480 => 4, 550 => 2, 560 => 2.5, 570 => 4, 580 => 4.5, 590 => 4.5, 5300 => 3.5, 5500 => 4.5, 5600 => 6, 5700 => 6,
           6400 => 3, 6500 => 3.5, 6600 => 6.5, 6650 => 7, 6700 => 7.5, 6750 => 8, 6800 => 8.5, 6900 => 9.5, 6950 => 9.5,
           7600 => 7, 7700 => 8.5, 7800 => 8.5, 7900 => 10, 9060 => 8, 9070 => 9.5];
    $RX_XT = [5500 => 4.5, 5600 => 6, 5700 => 6.5, 6500 => 3.5, 6600 => 7, 6650 => 7, 6700 => 8, 6750 => 8, 6800 => 9, 6900 => 9.5, 7600 => 7, 7700 => 8.5, 7800 => 8.5, 7900 => 10, 9060 => 8, 9070 => 10];
    if (preg_match_all('/\brx\s*(\d{3,4})\s*(xtx|xt|gre)?/u', $s, $mm, PREG_SET_ORDER)) foreach ($mm as $m) {
        $n = (int)$m[1]; $t = $m[2] ?? '';
        if (isset($RX[$n])) $f[] = $t === 'gre' ? ($n === 7900 ? 9 : $RX[$n]) : ($t !== '' ? ($RX_XT[$n] ?? $RX[$n]) : $RX[$n]);
    }
    if (preg_match('/vega\s*56/u', $s)) $f[] = 5;
    if (preg_match('/vega\s*64/u', $s)) $f[] = 5.5;
    if (preg_match('/r9\s*(290|390|fury)/u', $s)) $f[] = 4;
    if (preg_match('/r9\s*(270|280|285|380)/u', $s)) $f[] = 3;
    if (preg_match('/\bhd\s*7\d{3}\b|r7\s*\d{3}/u', $s)) $f[] = 2;
    $ARC = ['a310' => 2.5, 'a380' => 3.5, 'a580' => 6, 'a750' => 6.5, 'a770' => 7, 'b570' => 7, 'b580' => 7.5];
    if (preg_match_all('/arc\s*([ab])\s*(\d{3})/u', $s, $mm, PREG_SET_ORDER)) foreach ($mm as $m) { $k = $m[1] . $m[2]; if (isset($ARC[$k])) $f[] = $ARC[$k]; }
    if (!$f && preg_match('/\b(uhd|iris|hd graphics|integrated|vega\s*(3|6|7|8|10|11)\b)/u', $s)) $f[] = 1.8;
    if (!$f) {
        if (preg_match('/\bgts\s*\d{3}|\bgtx\s*[45]\d{2}\b|\bgt\s*[4-7]\d{2}m?\b|\bhd\s*\d{3,4}\b|\bhd\d{4}\b/u', $s)) $f[] = 1.8;
        elseif (preg_match('/gtx\s*[67]00\s*series/u', $s)) $f[] = 2.5;
        else {
            $gb = null;
            if (preg_match('/(\d+(?:\.\d+)?)\s*(gb|mb)\b[^.;]*?(vram|dedicated|video memory)/u', $s, $v1)) $gb = $v1[2] === 'mb' ? (float)$v1[1] / 1024 : (float)$v1[1];
            elseif (preg_match('/(vram|dedicated)[^\d]{0,20}(\d+(?:\.\d+)?)\s*(gb|mb)/u', $s, $v2)) $gb = $v2[3] === 'mb' ? (float)$v2[2] / 1024 : (float)$v2[2];
            if ($gb !== null) $f[] = $gb >= 8 ? 6 : ($gb >= 6 ? 5 : ($gb >= 4 ? 4.5 : ($gb >= 2 ? 3 : 1.8)));
            elseif (preg_match('/directx|opengl|vulkan|dx1[0-2]|any (modern )?(graphics|video|card)|capable|compatible|\d+\s*mb/u', $s)) $f[] = 1.8;
        }
    }
    return rocSrMax($f);
}

function rocSrCpu(string $s) {
    $s = (string)preg_replace('/®|™/u', '', mb_strtolower($s));
    $f = [];
    if (preg_match_all('/\bi([3579])[\s-]*(\d{4,5})[a-z]*/u', $s, $mm, PREG_SET_ORDER)) foreach ($mm as $m) {
        $tier = (int)$m[1]; $num = $m[2];
        $gen = strlen($num) === 5 ? (int)substr($num, 0, 2) : (int)$num[0];
        if ($tier === 3) $v = $gen >= 12 ? 6 : ($gen >= 10 ? 5 : ($gen >= 8 ? 4.5 : 3));
        elseif ($tier === 5) $v = $gen >= 10 ? 7 : ($gen >= 8 ? 6 : ($gen >= 6 ? 5 : ($gen >= 4 ? 4.5 : 3.5)));
        elseif ($tier === 7) $v = $gen >= 12 ? 8.5 : ($gen >= 10 ? 8 : ($gen >= 8 ? 7 : ($gen >= 6 ? 6 : ($gen >= 4 ? 4.5 : 4))));
        else $v = $gen >= 12 ? 10 : 8.5;
        $f[] = $v;
    }
    if (preg_match_all('/core\s*ultra\s*([579])/u', $s, $mm, PREG_SET_ORDER)) foreach ($mm as $m) $f[] = [5 => 7, 7 => 8.5, 9 => 10][(int)$m[1]];
    if (preg_match_all('/(?:ryzen\s*|\br)([3579])\s*[\s-]*(\d{4})\s*(x3d)?/u', $s, $mm, PREG_SET_ORDER)) foreach ($mm as $m) {
        $tier = (int)$m[1]; $series = (int)$m[2][0]; $x3d = !empty($m[3]);
        if ($tier === 3) $v = $series >= 7 ? 6 : ($series >= 4 ? 5 : ($series >= 3 ? 4.5 : 3.5));
        elseif ($tier === 5) $v = $series >= 7 ? 8 : ($series >= 4 ? 7 : ($series >= 3 ? 6 : ($series >= 2 ? 5 : 4.5)));
        elseif ($tier === 7) $v = $x3d && $series >= 5 ? 10 : ($series >= 7 ? 9 : ($series >= 4 ? 8.5 : ($series >= 3 ? 7 : ($series >= 2 ? 5.5 : 5))));
        else $v = $series >= 7 ? 10 : ($series >= 5 ? 9.5 : 8.5);
        $f[] = $v;
    }
    if (preg_match('/\bfx[\s-]*\d{4}/u', $s)) $f[] = 3.5;
    if (preg_match('/phenom|athlon/u', $s)) $f[] = 2.5;
    if (preg_match('/core\s*2|pentium|celeron|atom/u', $s)) $f[] = 2;
    if (!$f && preg_match('/quad[\s-]*core|4[\s-]*core/u', $s)) $f[] = 3;
    if (!$f && preg_match('/dual[\s-]*core|2[\s-]*core/u', $s)) $f[] = 2;
    if (!$f) foreach ([['/\bi9\b/u', 6], ['/\bi7\b/u', 4.5], ['/\bi5\b/u', 4.5], ['/\bi3\b/u', 3], ['/ryzen\s*9/u', 6], ['/ryzen\s*7/u', 5], ['/ryzen\s*5/u', 4.5], ['/ryzen\s*3/u', 3.5]] as [$re, $v]) if (preg_match($re, $s)) $f[] = $v;
    if (!$f && preg_match('/\d(?:[.,]\d+)?\s*ghz|sse2|x64|64-bit|64 bit/u', $s)) $f[] = 2;
    return rocSrMax($f);
}

function rocSrRam(string $s) {
    if (!preg_match('/(\d+(?:\.\d+)?)\s*(gb|mb)/iu', $s, $m)) return null;
    $v = strtolower($m[2]) === 'mb' ? (float)$m[1] / 1024 : (float)$m[1];
    return round($v * 10) / 10;
}

function rocSrScore(?array $b): ?array { return $b ? ['cpu' => rocSrCpu($b['cpu']), 'gpu' => rocSrGpu($b['gpu']), 'ram' => rocSrRam($b['ram'])] : null; }

/** Light games without model names: a low "estimated" score from the words (same as estimate() in the .mjs). */
function rocSrEstimate(?array $b, ?array $sc): ?array {
    if (!$b || !$sc) return $sc;
    $est = [];
    if ($sc['gpu'] === null) {
        $g = mb_strtolower($b['gpu']);
        if (preg_match('/(\d+(?:\.\d+)?)\s*(gb|mb)\b/u', $g, $mb)) { $gb = $mb[2] === 'gb' ? (float)$mb[1] : (float)$mb[1] / 1024; $v = $gb <= 0.5 ? 1 : ($gb <= 1 ? 1.5 : ($gb <= 2 ? 2.5 : ($gb <= 4 ? 3.5 : 4.5))); }
        elseif (preg_match('/discrete|dedicated/u', $g)) $v = 2;
        elseif (preg_match('/dx\s*9|directx\s*9|directx\s*10|open\s*gl|shader model|intel|gma|integrated|hardware accelerated|any|low budget|high color|onboard/u', $g) || trim($g) === '') $v = 1;
        elseif (preg_match('/rtx series/u', $g)) $v = 4.5;
        else $v = 2;
        $sc['gpu'] = $v; $est['gpu'] = true;
    }
    if ($sc['cpu'] === null) {
        $c = mb_strtolower($b['cpu']);
        if (preg_match('/quad|4 cores?|i5/u', $c)) $v = 3;
        elseif (preg_match('/dual|2 cores?|i3|ghz|mhz|pentium|atom|athlon|core 2|yes/u', $c) || trim($c) === '') $v = 1.5;
        else $v = 2;
        $sc['cpu'] = $v; $est['cpu'] = true;
    }
    if ($sc['ram'] === null) { $sc['ram'] = 2; $est['ram'] = true; }
    if ($est) $sc['est'] = $est;
    return $sc;
}

/** Entry for roc-steam-games.json from the Steam store's appdetails data. */
function rocSrBuildGame(array $app, int $year): array {
    $min = rocSrReadBlock((string)($app['pc_requirements']['minimum'] ?? ''));
    $recHtml = (string)($app['pc_requirements']['recommended'] ?? '');
    $rec = $recHtml !== '' ? rocSrReadBlock($recHtml) : null;
    $sMin = rocSrEstimate($min, rocSrScore($min));
    $sRec = rocSrEstimate($rec, rocSrScore($rec)) ?: $sMin;
    foreach (['cpu', 'gpu', 'ram'] as $k) if (!isset($sRec[$k]) || $sRec[$k] === null) $sRec[$k] = $sMin[$k];
    return ['id' => 'steam-' . $app['steam_appid'], 'appid' => (int)$app['steam_appid'], 't' => (string)$app['name'], 'y' => $year,
        'g' => implode(', ', array_slice(array_map(function ($x) { return (string)($x['description'] ?? ''); }, (array)($app['genres'] ?? [])), 0, 3)),
        'u' => 'https://store.steampowered.com/app/' . $app['steam_appid'] . '/', 'min' => $min, 'rec' => $rec, 'req' => ['min' => $sMin, 'rec' => $sRec]];
}
