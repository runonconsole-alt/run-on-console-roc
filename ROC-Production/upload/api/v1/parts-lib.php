<?php
/**
 * Run On Console — the long list of graphics cards and processors for the PC tools (Deploy 191).
 *
 * Owner, 2026-11-26: "bottleneck calculator" is searched 165,000 times a month and the tools offered only 32 graphics
 * cards and 20 processors. The tools (bottleneck calculator, upgrade planner, Rate my PC, Which games can my PC run?)
 * now offer about 160 parts: desktop cards from the GT 710 to the RTX 5090 / RX 9070 XT / Arc B580, laptop graphics,
 * and processors from the Core 2 Quad to the Ryzen 7 9800X3D and Core Ultra 9. Every score comes from the same rules
 * as the checker (rocAgentGpuScore / rocAgentCpuScore in agent-lib.php); a laptop graphics chip counts one step lower
 * than the desktop card with the same name (laptop versions run at lower power). The game pages keep their short list.
 */

const ROC_PARTS_GPU = [
    'Intel UHD / Iris Xe (integrated)', 'AMD Radeon Vega 8 (integrated)',
    'GeForce GT 710', 'GeForce GT 730', 'GeForce GT 1030',
    'GeForce GTX 650', 'GeForce GTX 660', 'GeForce GTX 750 Ti', 'GeForce GTX 760', 'GeForce GTX 770', 'GeForce GTX 780',
    'GeForce GTX 950', 'GeForce GTX 960', 'GeForce GTX 970', 'GeForce GTX 980', 'GeForce GTX 980 Ti',
    'GeForce GTX 1050', 'GeForce GTX 1050 Ti', 'GeForce GTX 1060 3GB', 'GeForce GTX 1060 6GB', 'GeForce GTX 1070', 'GeForce GTX 1070 Ti', 'GeForce GTX 1080', 'GeForce GTX 1080 Ti',
    'GeForce GTX 1630', 'GeForce GTX 1650', 'GeForce GTX 1650 Super', 'GeForce GTX 1660', 'GeForce GTX 1660 Super', 'GeForce GTX 1660 Ti',
    'GeForce RTX 2060', 'GeForce RTX 2060 Super', 'GeForce RTX 2070', 'GeForce RTX 2070 Super', 'GeForce RTX 2080', 'GeForce RTX 2080 Super', 'GeForce RTX 2080 Ti',
    'GeForce RTX 3050', 'GeForce RTX 3060', 'GeForce RTX 3060 Ti', 'GeForce RTX 3070', 'GeForce RTX 3070 Ti', 'GeForce RTX 3080', 'GeForce RTX 3080 Ti', 'GeForce RTX 3090', 'GeForce RTX 3090 Ti',
    'GeForce RTX 4060', 'GeForce RTX 4060 Ti', 'GeForce RTX 4070', 'GeForce RTX 4070 Super', 'GeForce RTX 4070 Ti', 'GeForce RTX 4070 Ti Super', 'GeForce RTX 4080', 'GeForce RTX 4080 Super', 'GeForce RTX 4090',
    'GeForce RTX 5050', 'GeForce RTX 5060', 'GeForce RTX 5060 Ti', 'GeForce RTX 5070', 'GeForce RTX 5070 Ti', 'GeForce RTX 5080', 'GeForce RTX 5090',
    'Radeon RX 460', 'Radeon RX 470', 'Radeon RX 480', 'Radeon RX 550', 'Radeon RX 560', 'Radeon RX 570', 'Radeon RX 580', 'Radeon RX 590',
    'Radeon RX 5500 XT', 'Radeon RX 5600 XT', 'Radeon RX 5700', 'Radeon RX 5700 XT',
    'Radeon RX 6400', 'Radeon RX 6500 XT', 'Radeon RX 6600', 'Radeon RX 6600 XT', 'Radeon RX 6650 XT', 'Radeon RX 6700 XT', 'Radeon RX 6750 XT', 'Radeon RX 6800', 'Radeon RX 6800 XT', 'Radeon RX 6900 XT', 'Radeon RX 6950 XT',
    'Radeon RX 7600', 'Radeon RX 7600 XT', 'Radeon RX 7700 XT', 'Radeon RX 7800 XT', 'Radeon RX 7900 GRE', 'Radeon RX 7900 XT', 'Radeon RX 7900 XTX',
    'Radeon RX 9060 XT', 'Radeon RX 9070', 'Radeon RX 9070 XT',
    'Intel Arc A310', 'Intel Arc A380', 'Intel Arc A580', 'Intel Arc A750', 'Intel Arc A770', 'Intel Arc B570', 'Intel Arc B580',
    // Laptop graphics (one step lower than the desktop card of the same name).
    'GeForce MX450 (laptop)', 'GeForce MX550 (laptop)', 'GeForce GTX 1650 (laptop)', 'GeForce GTX 1660 Ti (laptop)',
    'GeForce RTX 2050 (laptop)', 'GeForce RTX 3050 (laptop)', 'GeForce RTX 3060 (laptop)', 'GeForce RTX 3070 (laptop)', 'GeForce RTX 3080 (laptop)',
    'GeForce RTX 4050 (laptop)', 'GeForce RTX 4060 (laptop)', 'GeForce RTX 4070 (laptop)', 'GeForce RTX 4080 (laptop)', 'GeForce RTX 4090 (laptop)',
    'GeForce RTX 5060 (laptop)', 'GeForce RTX 5070 (laptop)', 'GeForce RTX 5070 Ti (laptop)', 'GeForce RTX 5080 (laptop)', 'GeForce RTX 5090 (laptop)',
    'Radeon RX 6600M (laptop)', 'Radeon RX 7600S (laptop)',
];

const ROC_PARTS_CPU = [
    'Intel Core 2 Quad Q6600', 'Intel Pentium G4560', 'AMD Athlon 3000G', 'AMD FX-6300', 'AMD FX-8350',
    'Intel Core i3-2100', 'Intel Core i3-4160', 'Intel Core i3-6100', 'Intel Core i3-8100', 'Intel Core i3-10100', 'Intel Core i3-12100', 'Intel Core i3-13100',
    'Intel Core i5-2500K', 'Intel Core i5-3470', 'Intel Core i5-4460', 'Intel Core i5-4690K', 'Intel Core i5-6500', 'Intel Core i5-7400', 'Intel Core i5-8400', 'Intel Core i5-9400F',
    'Intel Core i5-10400', 'Intel Core i5-11400', 'Intel Core i5-12400', 'Intel Core i5-12600K', 'Intel Core i5-13400', 'Intel Core i5-13600K', 'Intel Core i5-14400', 'Intel Core i5-14600K',
    'Intel Core i7-2600', 'Intel Core i7-3770', 'Intel Core i7-4770', 'Intel Core i7-6700', 'Intel Core i7-7700', 'Intel Core i7-8700', 'Intel Core i7-9700',
    'Intel Core i7-10700', 'Intel Core i7-11700', 'Intel Core i7-12700', 'Intel Core i7-13700', 'Intel Core i7-14700',
    'Intel Core i9-9900K', 'Intel Core i9-10900', 'Intel Core i9-12900K', 'Intel Core i9-13900K', 'Intel Core i9-14900K',
    'Intel Core Ultra 5 245K', 'Intel Core Ultra 7 265K', 'Intel Core Ultra 9 285K',
    'AMD Ryzen 3 1200', 'AMD Ryzen 3 2200G', 'AMD Ryzen 3 3100', 'AMD Ryzen 3 3200G', 'AMD Ryzen 3 4100',
    'AMD Ryzen 5 1600', 'AMD Ryzen 5 2600', 'AMD Ryzen 5 3600', 'AMD Ryzen 5 4500', 'AMD Ryzen 5 5500', 'AMD Ryzen 5 5600', 'AMD Ryzen 5 5600X',
    'AMD Ryzen 5 7500F', 'AMD Ryzen 5 7600', 'AMD Ryzen 5 7600X', 'AMD Ryzen 5 9600X',
    'AMD Ryzen 7 1700', 'AMD Ryzen 7 2700', 'AMD Ryzen 7 3700X', 'AMD Ryzen 7 5700X', 'AMD Ryzen 7 5800X', 'AMD Ryzen 7 5800X3D', 'AMD Ryzen 7 7700X', 'AMD Ryzen 7 7800X3D', 'AMD Ryzen 7 9700X', 'AMD Ryzen 7 9800X3D',
    'AMD Ryzen 9 3900X', 'AMD Ryzen 9 5900X', 'AMD Ryzen 9 5950X', 'AMD Ryzen 9 7900X', 'AMD Ryzen 9 7950X', 'AMD Ryzen 9 9950X',
];

/** [name => score] for every part of the long list (plus the reference parts), weakest first. */
function rocGpRefsAll(string $kind): array {
    static $c = [];
    if (isset($c[$kind])) return $c[$kind];
    $out = [];
    foreach ($kind === 'gpu' ? ROC_PARTS_GPU : ROC_PARTS_CPU as $n) {
        $q = str_replace([' (laptop)', ' (integrated)'], '', $n);
        $s = $kind === 'gpu' ? rocAgentGpuScore($q) : rocAgentCpuScore($q);
        if ($s === null && strpos($n, 'integrated') !== false) $s = 1.8;
        if ($s === null) continue;
        if (strpos($n, '(laptop)') !== false) $s = max(1.8, $s - 1.0);
        $out[$n] = (float)$s;
    }
    foreach (rocGpRefs($kind) as $n => $s) if (!isset($out[$n])) $out[$n] = $s;   // the reference names stay valid (saved PCs)
    uksort($out, function ($a, $b) use ($out) { return [$out[$a], $a] <=> [$out[$b], $b]; });
    return $c[$kind] = $out;
}

/** <option> list grouped by brand, with the chosen part selected. */
function rocPartsOptions(string $kind, string $pick): string {
    $groups = $kind === 'gpu'
        ? ['Integrated graphics' => '/integrated/', 'NVIDIA GeForce (desktop)' => '/^GeForce(?!.*laptop)/', 'AMD Radeon (desktop)' => '/^Radeon(?!.*laptop)/', 'Intel Arc' => '/^Intel Arc/', 'Laptop graphics' => '/laptop/']
        : ['Intel' => '/^Intel/', 'AMD' => '/^AMD/'];
    $all = rocGpRefsAll($kind); $o = ''; $done = [];
    foreach ($groups as $label => $re) {
        $items = '';
        foreach ($all as $n => $s) if (!isset($done[$n]) && preg_match($re, $n)) { $done[$n] = 1; $items .= '<option value="' . rocH($n) . '"' . ($n === $pick ? ' selected' : '') . '>' . rocH($n) . '</option>'; }
        if ($items !== '') $o .= '<optgroup label="' . rocH($label) . '">' . $items . '</optgroup>';
    }
    return $o;
}
