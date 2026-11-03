<?php
/**
 * Run On Console — PC Game Requirements Report (Deploy 154): /can-i-run/report-2026/
 *
 * Original numbers from the game list (official Steam requirements of every game on /can-i-run/), worked out with
 * the checker's own rules, so they can be quoted: how many games integrated graphics run, how much of the list
 * each card class runs, how requirements grew year by year, the heaviest and lightest popular games. Everything is
 * computed on each visit from the live list, so the report updates itself when games are added.
 * game-render.php calls rocGpReport() for the slug "report-2026" (functions of game-render.php are in scope).
 */

const ROC_GP_REPORT = '/can-i-run/report-2026/';

function rocRpMedian(array $xs): ?float {
    $xs = array_values(array_filter($xs, function ($v) { return is_numeric($v) && $v > 0; }));
    if (!$xs) return null;
    sort($xs);
    $n = count($xs);
    return $n % 2 ? (float)$xs[intdiv($n, 2)] : ((float)$xs[$n / 2 - 1] + (float)$xs[$n / 2]) / 2;
}

function rocRpGb(string $s): ?float {
    if (preg_match('/(\d+(?:\.\d+)?)\s*GB/i', $s, $m)) return (float)$m[1];
    if (preg_match('/(\d+)\s*MB/i', $s, $m)) return round((int)$m[1] / 1024, 1);
    return null;
}

/**
 * Minimum and recommended scores of a game, with the recommended never below the minimum: when Steam lists only the
 * minimum, the stored "recommended" is an empty estimate (graphics 1, 2 GB) and the minimum is what counts.
 */
function rocRpReq(array $g): array {
    $min = (array)($g['req']['min'] ?? []); $rec = (array)($g['req']['rec'] ?? []);
    $out = $min;
    foreach (['gpu', 'cpu', 'ram'] as $k) {
        $r = (float)($rec[$k] ?? 0); $m = (float)($min[$k] ?? 0);
        $out[$k] = !empty($rec['est'][$k]) || $r < $m ? $m : $r;
    }
    $out['est'] = (array)($min['est'] ?? []);
    return [$min, $out];
}

/** The weakest card in our list with at least this tier score ("GeForce GTX 1650 class"). */
function rocRpCardFor(?float $score): string {
    if (!$score) return '-';
    foreach (rocGpRefs('gpu') as $name => $s) if ($s >= $score - 0.01) return strpos($name, 'integrated') !== false ? 'Integrated graphics' : $name;
    return 'GeForce RTX 4090';
}

/** All the report's numbers. */
function rocRpData(array $games): array {
    $gpus = rocGpRefs('gpu'); $cpus = rocGpRefs('cpu');
    $all = array_values($games);
    $n = count($all);
    $intGood = 0; $intLow = 0; $rec16 = 0; $sto100 = 0; $years = [];
    foreach ($all as $g) {
        $p = (int)rocAgentCheck($g, $gpus['Intel UHD / Iris Xe (integrated)'], $cpus['Intel Core i5-8400'], 8.0)['percent'];
        if ($p >= 70) $intGood++; elseif ($p > 40) $intLow++;
        [$min, $rec] = rocRpReq($g);
        if ((float)($rec['ram'] ?? 0) >= 16) $rec16++;
        $sto = rocRpGb((string)((is_array($g['rec'] ?? null) ? ($g['rec']['storage'] ?? '') : '') ?: ($g['min']['storage'] ?? '')));
        if ($sto !== null && $sto >= 100) $sto100++;
        $y = (int)($g['y'] ?? 0);
        if ($y < 2015 || $y > (int)date('Y')) continue;
        $b = &$years[$y];
        $b['n'] = ($b['n'] ?? 0) + 1;
        if (empty($min['est']['gpu'])) $b['gpu'][] = (float)($rec['gpu'] ?? 0);
        if (empty($min['est']['ram'])) $b['ram'][] = (float)($rec['ram'] ?? 0);
        if ($sto !== null) $b['sto'][] = $sto;
        $b['int'] = ($b['int'] ?? 0) + ((float)($min['gpu'] ?? 9) <= 1.8 ? 1 : 0);
        unset($b);
    }
    ksort($years);
    $trend = [];
    foreach ($years as $y => $b) {
        if (($b['n'] ?? 0) < 20) continue;   // too few games that year for a fair number
        $trend[$y] = ['n' => $b['n'], 'gpu' => rocRpMedian($b['gpu'] ?? []), 'ram' => rocRpMedian($b['ram'] ?? []),
                      'sto' => rocRpMedian($b['sto'] ?? []), 'int' => (int)round(100 * $b['int'] / $b['n'])];
    }
    // Card classes: the graphics card only, with the card pages' rule (game-render.php): at or above the recommended
    // graphics = high, at least 75% of it = medium to high.
    $cards = [];
    foreach (array_keys(ROC_GP_CARD_PAGES) as $cn) {
        $score = (float)$gpus[$cn]; $med = 0; $high = 0;
        foreach ($all as $g) {
            [$mn, $rc] = rocRpReq($g); $gm = (float)($mn['gpu'] ?? 0); $gr = max($gm, (float)($rc['gpu'] ?? $gm));
            if ($gm > 0 && $score < $gm) continue;
            if ($gr <= 0 || $score >= $gr) { $med++; if (empty($g['req']['nr']) || $score >= $gm + 2) $high++; } elseif ($score >= 0.75 * $gr) $med++;
        }
        $cards[$cn] = ['med' => $med, 'high' => $high];
    }
    // Popular games (the list is most popular first): heaviest and lightest of the top 300 by recommended graphics.
    $pop = array_slice($all, 0, 300);
    $known = array_values(array_filter($pop, function ($g) { return empty($g['req']['min']['est']); }));
    $w = function ($g) { [, $r] = rocRpReq($g); return [(float)($r['gpu'] ?? 0), (float)($r['cpu'] ?? 0), (float)($r['ram'] ?? 0)]; };
    $heavy = $known; usort($heavy, function ($a, $b) use ($w) { return $w($b) <=> $w($a); });
    $light = $known; usort($light, function ($a, $b) use ($w) { return $w($a) <=> $w($b); });
    return ['n' => $n, 'intGood' => $intGood, 'intLow' => $intLow, 'rec16' => $rec16, 'sto100' => $sto100, 'trend' => $trend, 'cards' => $cards,
            'heavy' => array_slice($heavy, 0, 10), 'light' => array_slice($light, 0, 10)];
}

function rocGpReport(string $root, array $games, int $updated, string $headTop, string $bodyToMain, string $mainOpen, string $afterMain): void {
    $D = rocRpData($games);
    $n = $D['n'];
    $pc = function (int $x) use ($n) { return $n ? (int)round(100 * $x / $n) : 0; };
    $yr = (int)date('Y');
    $url = ROC_PUBLIC_URL . ROC_GP_REPORT;
    $crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/'], ['Requirements report ' . $yr, ROC_GP_REPORT]];
    $title = 'PC Game Requirements Report ' . $yr . ': What ' . number_format($n) . ' Games Need';
    $desc = 'Original data from the official Steam requirements of ' . number_format($n) . ' PC games: what integrated graphics and each graphics card run, and how requirements grew.';
    $t = $D['trend']; $ys = array_keys($t);
    $first = $ys ? $t[$ys[0]] : null;
    // The newest year with enough games, and the one before it when the current year is still young.
    $last = $ys ? $t[end($ys)] : null; $lastY = $ys ? end($ys) : null;
    $c1650 = $D['cards']['GeForce GTX 1650']['med'] ?? 0; $c3060 = $D['cards']['GeForce RTX 3060']['high'] ?? 0;
    $find = [
        number_format($D['intGood']) . ' of ' . number_format($n) . ' PC games (' . $pc($D['intGood']) . '%) run on medium to high settings with integrated graphics (Intel UHD / Iris Xe class), and another ' . number_format($D['intLow']) . ' (' . $pc($D['intLow']) . '%) on low settings.',
        'A GeForce GTX 1650 class card meets at least three quarters of the recommended graphics of ' . $pc($c1650) . '% of the games (medium settings or better); an RTX 3060 class card meets the recommended graphics of ' . $pc($c3060) . '%.',
    ];
    if ($first && $last) {
        $find[] = 'Games released in ' . $ys[0] . ' could play on integrated graphics at their minimum settings ' . $first['int'] . '% of the time; for ' . $lastY . ' releases it is ' . $last['int'] . '%.';
        $find[] = 'The typical (median) recommended RAM went from ' . (int)$first['ram'] . ' GB for ' . $ys[0] . ' games to ' . (int)$last['ram'] . ' GB for ' . $lastY . ' games; ' . $pc($D['rec16']) . '% of all games now recommend 16 GB or more.';
    }
    $find[] = number_format($D['sto100']) . ' games (' . $pc($D['sto100']) . '%) ask for 100 GB of free space or more.';
    $cite = 'Run On Console, "PC Game Requirements Report ' . $yr . '", ' . $url . ', data from the official Steam requirements of ' . number_format($n) . ' PC games, updated ' . gmdate('j F Y', $updated) . '.';

    $ld = [rocGpCrumbs($crumbs),
        ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $title, 'url' => $url, 'description' => $desc, 'dateModified' => gmdate('c', $updated),
         'author' => ['@type' => 'Person', 'name' => ROC_OWNER, 'url' => ROC_PUBLIC_URL . ROC_OWNER_URL],
         'publisher' => ['@type' => 'Organization', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/', 'logo' => ['@type' => 'ImageObject', 'url' => ROC_PUBLIC_URL . '/images/logo-512.png']]],
        ['@context' => 'https://schema.org', '@type' => 'Dataset', 'name' => 'PC game system requirements (Run On Console)', 'url' => $url, 'description' => $desc,
         'creator' => ['@type' => 'Organization', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'], 'dateModified' => gmdate('c', $updated),
         'isBasedOn' => 'https://store.steampowered.com/', 'variableMeasured' => ['Minimum and recommended graphics tier', 'Recommended RAM', 'Storage', 'Release year']]];
    $og = rocGpOgCard($root, '_report', 'PC Game Requirements Report ' . $yr, number_format($n) . ' games: what your PC needs', $updated);
    $h = rocGpHead($title, $desc, $url, $ld, 'index, follow, max-image-preview:large', $og, 'PC Game Requirements Report ' . $yr . ' by Run On Console');
    $h .= '<style>.rp-find li{margin:.45rem 0;line-height:1.6}.rp-t{width:100%;border-collapse:collapse;font-size:.9rem}.rp-t th,.rp-t td{border-bottom:1px solid #e2e8f0;padding:.5rem .55rem;text-align:left}'
        . '.rp-t th{background:#f8fafc;color:#334155}.rp-bar{height:.55rem;border-radius:99px;background:#e2e8f0;overflow:hidden;min-width:80px}.rp-bar i{display:block;height:100%;background:#10b981}'
        . '.rp-cite{border:1px dashed #94a3b8;border-radius:1rem;padding:1rem;font-size:.85rem;color:#334155;background:#f8fafc}'
        . 'html.roc-dark .rp-t th{background:#0b1220;color:#cbd5e1}html.roc-dark .rp-t td,html.roc-dark .rp-t th{border-color:#1e293b}html.roc-dark .rp-cite{background:#0b1220;color:#cbd5e1;border-color:#334155}</style>';

    $m  = $mainOpen . '<article class="max-w-4xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
    $m .= '<header class="gp-hero"><div><h1>PC Game Requirements Report ' . $yr . '</h1>'
        . '<p>What ' . number_format($n) . ' PC games need, from their official Steam requirements, worked out with the same rules as our <a href="/compatibility/" style="color:inherit;text-decoration:underline">checker</a>. '
        . 'By <a href="' . ROC_OWNER_URL . '" style="color:inherit;text-decoration:underline">' . ROC_OWNER . '</a>, Founder of Run On Console. Updated ' . gmdate('j F Y', $updated) . '; the numbers update themselves when games are added.</p></div></header>';
    $m .= '<section class="gp-card"><h2>Key findings</h2><ul class="rp-find">' . implode('', array_map(function ($f) { return '<li>' . rocH($f) . '</li>'; }, $find)) . '</ul></section>';

    $m .= '<section class="gp-card"><h2>How much of the list each graphics card runs</h2><p class="gp-src">Graphics card only, as on our card pages: high = the card meets the game\'s recommended graphics, medium or better = at least three quarters of it. Integrated graphics are in the findings above.</p>'
        . '<div class="gp-wrap" style="overflow-x:auto"><table class="rp-t"><thead><tr><th>Graphics card class</th><th>Medium or better</th><th></th><th>High settings</th></tr></thead><tbody>';
    foreach ($D['cards'] as $cn => $c) {
        $path = rocGpCardPath($cn);
        $label = strpos($cn, 'integrated') !== false ? 'Integrated graphics (Intel UHD / Iris Xe)' : $cn;
        $m .= '<tr><td>' . ($path !== null ? '<a href="' . $path . '">' . rocH($label) . '</a>' : rocH($label)) . '</td><td>' . $pc($c['med']) . '% <small>(' . number_format($c['med']) . ')</small></td>'
            . '<td><div class="rp-bar"><i style="width:' . $pc($c['med']) . '%"></i></div></td><td>' . $pc($c['high']) . '%</td></tr>';
    }
    $m .= '</tbody></table></div></section>';

    if ($t) {
        $m .= '<section class="gp-card"><h2>How requirements grew, year by year</h2><p class="gp-src">Games on Run On Console by Steam release year (years with at least 20 games). Medians: half the games of that year need more, half less. '
            . 'Graphics are shown as the weakest card in our list that meets the median recommended tier; games whose graphics card Steam does not name are left out of that column.</p>'
            . '<div class="gp-wrap" style="overflow-x:auto"><table class="rp-t"><thead><tr><th>Year</th><th>Games</th><th>Minimum runs on integrated graphics</th><th>Recommended graphics (median)</th><th>Recommended RAM (median)</th><th>Storage (median)</th></tr></thead><tbody>';
        foreach ($t as $y => $r) $m .= '<tr><td>' . $y . '</td><td>' . number_format($r['n']) . '</td><td>' . $r['int'] . '%</td><td>' . rocH(rocRpCardFor($r['gpu'])) . '</td><td>'
            . ($r['ram'] ? rtrim(rtrim(number_format($r['ram'], 1), '0'), '.') . ' GB' : '-') . '</td><td>' . ($r['sto'] ? rtrim(rtrim(number_format($r['sto'], 1), '0'), '.') . ' GB' : '-') . '</td></tr>';
        $m .= '</tbody></table></div>' . ($lastY === $yr ? '<p class="gp-src" style="margin-top:.5rem">' . $yr . ' is still under way: its row changes as the year\'s big releases join the list.</p>' : '') . '</section>';
    }

    $rec = function ($g) { [, $r] = rocRpReq($g); $gb = (float)($r['ram'] ?? 0); return rocRpCardFor((float)($r['gpu'] ?? 0)) . ', ' . ($gb > 0 && $gb < 1 ? (int)round($gb * 1024) . ' MB' : rtrim(rtrim(number_format($gb, 1), '0'), '.') . ' GB') . ' RAM'; };
    $li = function ($g) use ($rec) { return '<li><a href="' . rocGpPath($g) . '">' . rocH($g['name']) . '</a> <small>(' . (int)$g['y'] . ') · recommended: ' . rocH($rec($g)) . '</small></li>'; };
    $m .= '<section class="gp-card"><h2>The most demanding popular games</h2><p class="gp-src">Of the 300 most popular games on the list, by recommended graphics, then processor and RAM.</p><ol style="margin-top:.6rem;padding-left:1.2rem">'
        . implode('', array_map($li, $D['heavy'])) . '</ol></section>';
    $m .= '<section class="gp-card"><h2>The lightest popular games</h2><p class="gp-src">Of the same 300: they run on almost any PC or laptop. More on <a href="' . ROC_GP_NOGPU . '">games without a graphics card</a>.</p><ol style="margin-top:.6rem;padding-left:1.2rem">'
        . implode('', array_map($li, $D['light'])) . '</ol></section>';

    $m .= '<section class="gp-card"><h2>Method and how to cite</h2><p>Every number comes from the minimum and recommended requirements on each game\'s official Steam store page, scored with the tier scale and the 50/30/20 rule explained on '
        . '<a href="/how-we-check/">How we check games</a>. We do not measure FPS. Games Steam lists without a named graphics card or processor are estimated, as each game page says.</p>'
        . '<p style="margin-top:.6rem">You are welcome to use these numbers with a link to this page:</p><div class="rp-cite" style="margin-top:.4rem">' . rocH($cite) . '</div></section>';
    $m .= rocGpCardNav();
    $m .= '</article>';
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}
