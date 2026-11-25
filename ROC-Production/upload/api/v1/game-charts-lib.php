<?php
/**
 * Run On Console — charts on every /can-i-run/{game}/ page (Deploy 189).
 *
 * Owner, 2026-11-25: show the answer as graphs, like the big PC benchmark sites, but honest:
 *   1. Graphics card chart: 20 common cards, each with the settings level it reaches in this game, from the same rules as
 *      the checker (rocAgentCheck: official Steam requirements, a Ryzen 5 5600 and 16 GB RAM). It is NOT an FPS number.
 *   2. Requirements ladder: where the minimum and the recommended graphics card, processor and RAM sit on a scale from the
 *      weakest to the strongest common part; after the quick check the player's own PC is marked on it.
 *   3. FPS chart: the frame rates players reported (fps-lib.php), as bars. Real reports only.
 * Plain HTML and CSS bars (no chart library), so the pages stay fast.
 */

const ROC_GC_GPUS = ['Intel UHD / Iris Xe (integrated)', 'GeForce GT 1030', 'GeForce GTX 1050 Ti', 'GeForce GTX 1650', 'GeForce GTX 1060 6GB', 'Radeon RX 580',
    'GeForce RTX 3050', 'GeForce GTX 1660 Super', 'GeForce RTX 2060', 'Radeon RX 6600', 'GeForce RTX 3060', 'GeForce RTX 4060', 'Radeon RX 7600', 'Intel Arc B580',
    'GeForce RTX 3070', 'GeForce RTX 4070', 'Radeon RX 7800 XT', 'GeForce RTX 5070', 'Radeon RX 9070 XT', 'GeForce RTX 4090'];
const ROC_GC_CPU = 'AMD Ryzen 5 5600';
const ROC_GC_GPU_TICKS = ['Intel UHD / Iris Xe (integrated)' => 'Intel UHD', 'GeForce GTX 1050 Ti' => 'GTX 1050 Ti', 'GeForce GTX 1660 Super' => 'GTX 1660 S',
    'GeForce RTX 3060' => 'RTX 3060', 'GeForce RTX 4070' => 'RTX 4070', 'GeForce RTX 4090' => 'RTX 4090'];
const ROC_GC_CPU_TICKS = ['Intel Core 2 Quad Q6600' => 'Core 2 Quad', 'Intel Core i3-6100' => 'i3-6100', 'AMD Ryzen 5 2600' => 'Ryzen 5 2600',
    'AMD Ryzen 5 5600' => 'Ryzen 5 5600', 'AMD Ryzen 7 7800X3D' => 'Ryzen 7 7800X3D'];

function rocGcCss(): string {
    return '<style>'
        . '.gc-legend{display:flex;flex-wrap:wrap;gap:.4rem .9rem;font-size:.78rem;color:#475569;margin:.2rem 0 .8rem}.gc-legend i{display:inline-block;width:.7rem;height:.7rem;border-radius:.2rem;margin-right:.3rem;vertical-align:-1px}'
        . '.gc-rows{display:grid;gap:.35rem}.gc-row{display:grid;grid-template-columns:minmax(7.5rem,11rem) 1fr;gap:.6rem;align-items:center;font-size:.84rem}'
        . '.gc-fps .gc-row{grid-template-columns:minmax(9rem,17rem) 1fr}.gc-row a,.gc-row span.n{color:#0f172a;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.gc-track{position:relative;height:1.35rem;background:#f1f5f9;border-radius:.45rem;overflow:hidden}'
        . '.gc-fill{position:absolute;inset:0 auto 0 0;border-radius:.45rem;display:flex;align-items:center;justify-content:flex-end;padding-right:.45rem;color:#fff;font-size:.72rem;font-weight:800;white-space:nowrap;min-width:2.2rem}'
        . '.gc-no{background:#ef4444}.gc-low{background:#f59e0b}.gc-mid{background:#84cc16}.gc-ok{background:#10b981}'
        . '.gc-line{position:absolute;top:0;bottom:0;width:0;border-left:2px dashed rgba(15,23,42,.35)}'
        . '.gc-row.me{background:#ecfdf5;border-radius:.5rem;outline:2px solid #10b981;outline-offset:2px}.gc-row.me a:after{content:" (your card)";color:#047857;font-weight:800}'
        . '.gc-lad{margin:.4rem 0 1.4rem}.gc-lad h3{font-size:.9rem;font-weight:800;color:#0f172a;margin-bottom:2.9rem}.gc-axis{position:relative;height:.6rem;border-radius:99px;background:linear-gradient(90deg,#fecaca,#fde68a,#bbf7d0,#6ee7b7)}'
        . '.gc-tk{position:absolute;top:.85rem;transform:translateX(-50%);font-size:.68rem;color:#64748b;white-space:nowrap}.gc-tk:before{content:"";position:absolute;left:50%;top:-.95rem;height:.7rem;border-left:1px solid #94a3b8}'
        . '.gc-mk{position:absolute;bottom:1rem;transform:translateX(-50%);font-size:.68rem;font-weight:800;padding:.1rem .4rem;border-radius:.4rem;color:#fff;white-space:nowrap}'
        . '.gc-mk:after{content:"";position:absolute;left:50%;top:100%;transform:translateX(-50%);border:5px solid transparent;border-top-color:inherit}'
        . '.gc-mk.mn{background:#f59e0b;border-color:#f59e0b}.gc-mk.rc{background:#047857;border-color:#047857;bottom:2.4rem}.gc-mk.rc:after{border-width:5px 0 0;border-left:1px dashed #047857;height:1.5rem;border-top:0;top:100%}.gc-mk.me{background:#2563eb;border-color:#2563eb;bottom:auto;top:1.9rem}.gc-mk.me:after{top:auto;bottom:100%;border-top-color:transparent;border-bottom-color:#2563eb}'
        . '.gc-lad .gc-pad{height:2.6rem}@media(max-width:560px){.gc-row,.gc-fps .gc-row{grid-template-columns:6.4rem 1fr;font-size:.78rem;gap:.4rem}.gc-fill{font-size:.62rem;min-width:4.3rem;padding-right:.3rem}.gc-tk{font-size:.58rem}.gc-tk:nth-of-type(even){display:none}.gc-mk{font-size:.6rem}}'
        . 'html.roc-dark .gc-row a,html.roc-dark .gc-row span.n,html.roc-dark .gc-lad h3{color:#e2e8f0}html.roc-dark .gc-track{background:#1e293b}html.roc-dark .gc-row.me{background:rgba(16,185,129,.12)}'
        . 'html.roc-dark .gc-legend,html.roc-dark .gc-tk{color:#94a3b8}'
        . '</style>';
}

/** Level class and words for a checker percentage (the same steps as the checker). */
function rocGcLevel(int $p): array {
    return $p >= 90 ? ['gc-ok', 'High'] : ($p >= 70 ? ['gc-mid', 'Medium-High'] : ($p > 40 ? ['gc-low', 'Low'] : ['gc-no', 'Below min']));
}

/** 1. Which graphics cards run this game, and on which settings. */
function rocGcGpuChart(array $g, string $name): string {
    $gpus = rocGpRefs('gpu'); $cpus = rocGpRefs('cpu');
    if (!isset($cpus[ROC_GC_CPU]) || empty($g['req']['min']['gpu'])) return '';
    $rows = '';
    foreach (ROC_GC_GPUS as $n) {
        if (!isset($gpus[$n])) continue;
        $p = (int)rocAgentCheck($g, $gpus[$n], $cpus[ROC_GC_CPU], 16.0)['percent'];
        [$cls, $lvl] = rocGcLevel($p);
        $path = rocGpCardPath($n);
        $label = $path !== null ? '<a href="' . $path . '">' . rocH(rocGpCardShort($n)) . '</a>' : '<span class="n">' . rocH(rocGpCardShort($n)) . '</span>';
        $rows .= '<div class="gc-row" data-n="' . rocH($n) . '">' . $label . '<div class="gc-track" role="img" aria-label="' . rocH(rocGpCardShort($n) . ': ' . $lvl . ' (' . $p . '%)') . '">'
            . '<div class="gc-fill ' . $cls . '" style="width:' . max(8, min(100, $p)) . '%">' . $lvl . '</div>'
            . '<span class="gc-line" style="left:40%"></span><span class="gc-line" style="left:90%"></span></div></div>';
    }
    if ($rows === '') return '';
    return '<section class="gp-card" id="gpu-chart"><h2>' . rocH($name) . ': graphics card chart</h2>'
        . '<p>How far each common graphics card gets in ' . rocH($name) . ', with a Ryzen 5 5600 class processor and 16 GB of RAM. The same rules as the checker above, from the official Steam requirements.</p>'
        . '<div class="gc-legend"><span><i class="gc-ok"></i>High settings</span><span><i class="gc-mid"></i>Medium to high</span><span><i class="gc-low"></i>Low settings</span><span><i class="gc-no"></i>Below the minimum</span>'
        . '<span>Dashed lines: minimum (left) and recommended (right)</span></div>'
        . '<div class="gc-rows">' . $rows . '</div>'
        . '<p class="gp-src" style="margin-top:.7rem">A settings level, not a frame rate: we do not measure FPS. Real frame rates from players are under "' . rocH($name) . ' FPS" below. Laptop versions of a card are usually a little slower.</p></section>';
}

/** Where a score sits on the scale of the common parts (0-100). */
function rocGcPos(float $s, array $refs): float {
    $lo = min($refs); $hi = max($refs);
    return $hi > $lo ? max(1.5, min(98.5, 100 * ($s - $lo) / ($hi - $lo))) : 50.0;
}

/** 2. The requirements on a ladder from the weakest to the strongest common part, with the player's PC after a check. */
function rocGcLadder(array $g, string $name): string {
    $mn = (array)($g['req']['min'] ?? []); $rc = (array)($g['req']['rec'] ?? $mn);
    $out = '';
    $one = function (string $kind, string $title, array $refs, array $ticks, ?float $min, ?float $rec) {
        if (!$min && !$rec) return '';
        $h = '<div class="gc-lad" data-kind="' . $kind . '" data-lo="' . min($refs) . '" data-hi="' . max($refs) . '"><h3>' . $title . '</h3><div class="gc-axis">';
        foreach ($ticks as $n => $lbl) if (isset($refs[$n])) $h .= '<span class="gc-tk" style="left:' . round(rocGcPos($refs[$n], $refs), 1) . '%">' . rocH($lbl) . '</span>';
        if ($min) $h .= '<span class="gc-mk mn" style="left:' . round(rocGcPos($min, $refs), 1) . '%">Minimum</span>';
        if ($rec && $rec != $min) $h .= '<span class="gc-mk rc" style="left:' . round(rocGcPos($rec, $refs), 1) . '%">Recommended</span>';
        return $h . '</div><div class="gc-pad"></div></div>';
    };
    $out .= $one('gpu', 'Graphics card', rocGpRefs('gpu'), ROC_GC_GPU_TICKS, isset($mn['gpu']) ? (float)$mn['gpu'] : null, isset($rc['gpu']) ? (float)$rc['gpu'] : null);
    $out .= $one('cpu', 'Processor', rocGpRefs('cpu'), ROC_GC_CPU_TICKS, isset($mn['cpu']) ? (float)$mn['cpu'] : null, isset($rc['cpu']) ? (float)$rc['cpu'] : null);
    $ram = ['4 GB' => 4, '8 GB' => 8, '16 GB' => 16, '32 GB' => 32];
    $ramRefs = [0 => 2.0, 1 => 32.0];
    $rmn = isset($mn['ram']) ? (float)$mn['ram'] : null; $rrc = isset($rc['ram']) ? (float)$rc['ram'] : null;
    if ($rmn || $rrc) {
        $h = '<div class="gc-lad" data-kind="ram" data-lo="2" data-hi="32"><h3>RAM</h3><div class="gc-axis">';
        foreach ($ram as $lbl => $gb) $h .= '<span class="gc-tk" style="left:' . round(rocGcPos($gb, $ramRefs), 1) . '%">' . $lbl . '</span>';
        if ($rmn) $h .= '<span class="gc-mk mn" style="left:' . round(rocGcPos($rmn, $ramRefs), 1) . '%">Min ' . rtrim(rtrim(number_format($rmn, 1), '0'), '.') . ' GB</span>';
        if ($rrc && $rrc != $rmn) $h .= '<span class="gc-mk rc" style="left:' . round(rocGcPos($rrc, $ramRefs), 1) . '%">Rec ' . rtrim(rtrim(number_format($rrc, 1), '0'), '.') . ' GB</span>';
        $out .= $h . '</div><div class="gc-pad"></div></div>';
    }
    if ($out === '') return '';
    return '<section class="gp-card" id="req-chart"><h2>' . rocH($name) . ' requirements at a glance</h2>'
        . '<p>Where the minimum and the recommended parts sit, from the weakest to the strongest common part. Check your PC above and <b style="color:#2563eb">your PC</b> shows on each scale.</p>'
        . $out . '</section>';
}

/** The script that marks the player's PC on the charts after the quick check (the selects hold the part scores). */
function rocGcScript(): string {
    return "<script>(function(){var box=document.querySelector('.gp-check');if(!box)return;"
        . "function sel(k){var s=box.querySelector('[data-k=\"'+k+'\"]');return s&&s.selectedIndex>=0?s.options[s.selectedIndex]:null}"
        . "function pos(v,lo,hi){return Math.max(1.5,Math.min(98.5,100*(v-lo)/(hi-lo||1)))}"
        . "function mark(){var g=sel('gpu');[].forEach.call(document.querySelectorAll('.gc-row'),function(r){r.classList.toggle('me',!!g&&r.getAttribute('data-n')===g.text)});"
        . "[].forEach.call(document.querySelectorAll('.gc-lad'),function(l){var o=sel(l.getAttribute('data-kind'));var ax=l.querySelector('.gc-axis'),m=ax.querySelector('.gc-mk.me');if(!o){if(m)m.remove();return}"
        . "if(!m){m=document.createElement('span');m.className='gc-mk me';m.textContent='Your PC';ax.appendChild(m)}m.style.left=pos(parseFloat(o.value),+l.getAttribute('data-lo'),+l.getAttribute('data-hi'))+'%'})}"
        . "box.addEventListener('change',mark);var t=0,iv=setInterval(function(){mark();if(++t>3)clearInterval(iv)},700)})();</script>";
}

/** 3. Players' FPS as bars: the typical FPS per card and settings (2+ reports), else each report. */
function rocGcFpsChart(array $reps): string {
    if (!$reps) return '';
    $grp = [];
    foreach ($reps as $r) $grp[$r['gpu'] . ' · ' . $r['res'] . ' ' . $r['preset']][] = $r['fps'];
    $bars = [];
    foreach ($grp as $k => $v) { sort($v); $n = count($v); $bars[(string)preg_replace('/^(?:GeForce|Radeon)\s+/', '', $k)] = [$n % 2 ? $v[intdiv($n, 2)] : (int)round(($v[$n / 2 - 1] + $v[$n / 2]) / 2), $n]; }
    arsort($bars);
    $bars = array_slice($bars, 0, 12, true);
    $max = max(60, max(array_column($bars, 0)));
    $h = '<div class="gc-rows gc-fps" style="margin:.2rem 0 1rem" aria-label="FPS reported by players">';
    foreach ($bars as $k => [$fps, $n]) {
        $cls = $fps >= 60 ? 'gc-ok' : ($fps >= 45 ? 'gc-mid' : ($fps >= 30 ? 'gc-low' : 'gc-no'));
        $h .= '<div class="gc-row"><span class="n" title="' . rocH($k) . '">' . rocH($k) . '</span><div class="gc-track" role="img" aria-label="' . rocH($k . ': ' . $fps . ' FPS') . '">'
            . '<div class="gc-fill ' . $cls . '" style="width:' . max(10, round(100 * $fps / $max)) . '%">' . $fps . ' FPS' . ($n > 1 ? ' (' . $n . ')' : '') . '</div></div></div>';
    }
    return $h . '</div>';
}
