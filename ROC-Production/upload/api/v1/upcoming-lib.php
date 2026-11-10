<?php
/**
 * Run On Console — upcoming PC games and their requirements (Deploy 174): /can-i-run/upcoming/
 *
 * Steam's popular upcoming games join the game list once Steam shows their PC requirements (steam-update-lib.php).
 * This page lists them by release date, says plainly that the requirements can change before launch, and keeps
 * the games that came out in the last 60 days. game-render.php calls rocGpUpcomingPage() for the slug "upcoming"
 * (functions of game-render.php are in scope).
 */

const ROC_GP_UPCOMING = '/can-i-run/upcoming/';

/** [upcoming (soonest first, undated last), out in the last 60 days (newest first)]. */
function rocGpUpcomingSplit(array $games): array {
    $soon = []; $out = [];
    foreach ($games as $g) {
        if (!empty($g['soon'])) $soon[] = $g;
        elseif (!empty($g['out']) && time() - (int)$g['out'] < 60 * 86400) $out[] = $g;
    }
    usort($soon, function ($a, $b) {
        $x = (int)($a['soon_t'] ?? 0); $y = (int)($b['soon_t'] ?? 0);
        if ($x && $y) return $x <=> $y;
        if ($x || $y) return $x ? -1 : 1;
        return (int)($a['rank'] ?? 0) <=> (int)($b['rank'] ?? 0);
    });
    usort($out, function ($a, $b) { return (int)$b['out'] <=> (int)$a['out']; });
    return [$soon, $out];
}

function rocGpUpcomingPage(string $root, array $games, int $updated, string $headTop, string $bodyToMain, string $mainOpen, string $afterMain): void {
    [$soon, $out] = rocGpUpcomingSplit($games);
    $n = count($soon);
    $url = ROC_PUBLIC_URL . ROC_GP_UPCOMING;
    $crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/'], ['Upcoming games', ROC_GP_UPCOMING]];
    $yr = (int)gmdate('Y');
    $title = 'Upcoming PC Games ' . $yr . '-' . ($yr + 1) . ': System Requirements';
    $desc = 'The PC requirements of upcoming games as Steam lists them before launch, with release dates. Check if your PC is ready, and see the games out recently.';
    $faqs = [
        ['Can the requirements change before release?', 'Yes. Developers often update the minimum and recommended specs close to launch. We check every upcoming game on Steam again every few days and update its page when the requirements change.'],
        ['Where do these requirements come from?', 'From each game&rsquo;s official Steam store page. A game appears here once Steam shows its PC requirements; games without them yet are not listed.'],
        ['What happens when a game comes out?', 'Its page stays and the "not out yet" note goes. It stays in the "out recently" list below for 60 days.'],
    ];
    $faqs = array_map(function ($f) { return [$f[0], html_entity_decode($f[1], ENT_QUOTES, 'UTF-8')]; }, $faqs);
    $top = array_slice($soon, 0, 30);
    $ld = [rocGpCrumbs($crumbs), ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'Upcoming PC games and their system requirements', 'url' => $url, 'description' => $desc,
        'dateModified' => gmdate('c', $updated), 'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'],
        'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $n, 'itemListElement' => array_map(function ($g, $i) {
            return ['@type' => 'ListItem', 'position' => $i + 1, 'url' => ROC_PUBLIC_URL . rocGpPath($g), 'name' => $g['name']];
        }, $top, array_keys($top))]],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
            return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]];
        }, $faqs)]];
    $h = rocGpHead($title, $desc, $url, $ld, ($soon || $out) ? 'index, follow, max-image-preview:large' : 'noindex, follow', '', 'Upcoming PC games on Run On Console');

    $ram = function ($g) { $r = (float)($g['req']['min']['ram'] ?? 0); return $r > 0 ? rtrim(rtrim(number_format($r, 1), '0'), '.') . ' GB' : '—'; };
    $gpu = function ($g) { $t = trim(strip_tags((string)($g['min']['gpu'] ?? ''))); return $t === '' ? 'Not listed yet' : (mb_strlen($t) > 80 ? mb_substr($t, 0, 78) . '…' : $t); };
    $m  = $mainOpen . '<div class="max-w-6xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
    $wall = rocGpWall($soon);
    $m .= '<header class="gp-hero' . ($wall !== '' ? ' pic' : '') . '"><div><h1>Upcoming PC games: system requirements</h1>'
        . '<p>' . ($n ? number_format($n) . ' upcoming games already show their PC requirements on Steam. ' : 'No upcoming game shows its PC requirements on Steam right now. ')
        . 'Here they are with their release dates, so you can check your PC before launch day. Requirements can change before release: we check each game again every few days.</p>'
        . '<div class="gp-btns"><a class="gp-btn p" href="/can-i-run/my-pc/">Which games can my PC run? &rarr;</a><a class="gp-btn s" href="/can-i-run/">All ' . number_format(count($games)) . ' games</a></div></div>' . $wall . '</header>';
    if ($soon) {
        $m .= '<section class="gp-card"><h2>Coming soon (' . number_format($n) . ' games)</h2><div class="gp-wrap"><table class="gp-table"><thead><tr><th>Game</th><th>Release (Steam)</th><th>Minimum graphics</th><th>Minimum RAM</th></tr></thead><tbody>';
        foreach ($soon as $g) $m .= '<tr><td><a href="' . rocGpPath($g) . '">' . rocH($g['name']) . '</a></td><td>' . rocH((string)$g['soon']) . '</td><td>' . rocH($gpu($g)) . '</td><td>' . $ram($g) . '</td></tr>';
        $m .= '</tbody></table></div><p class="gp-src" style="margin-top:.5rem">Release dates and requirements as Steam shows them today; they can change.</p></section>';
    }
    $m .= rocAdUnit('list');
    if ($out) {
        $m .= '<section class="gp-card"><h2>Out recently</h2><ul class="gp-list">';
        foreach ($out as $g) $m .= '<li><a href="' . rocGpPath($g) . '">' . rocH($g['name']) . '</a><small>out ' . gmdate('j M', (int)$g['out']) . '</small></li>';
        $m .= '</ul></section>';
    }
    $m .= '<section class="gp-card gp-faq"><h2>Upcoming games: questions</h2>';
    foreach ($faqs as $i => $f) $m .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . rocH($f[0]) . '</summary><div class="a">' . rocH($f[1]) . '</div></details>';
    $m .= '</section>';
    $m .= '<section class="gp-card"><h2>More for PC players</h2><div class="gp-sim">'
        . '<a href="/can-i-run/report-2026/"><strong>PC requirements report</strong><span>How requirements grew year by year</span></a>'
        . '<a href="/can-i-run/fps-reports/"><strong>FPS reports</strong><span>Real frame rates from players</span></a>'
        . '<a href="/category/gpu/"><strong>Graphics cards</strong><span>Picks for every budget</span></a>'
        . (function_exists('rocGpRamLinks') ? rocGpRamLinks() : '') . '</div></section>';
    $m .= '<p class="gp-src">Requirements: the official <a href="https://store.steampowered.com/" target="_blank" rel="noopener">Steam store</a> page of each game.</p>';
    $m .= '</div>';
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}
