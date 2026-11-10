<?php
/**
 * Run On Console — "Games for 4 / 8 / 16 GB RAM" pages (Deploy 172).
 *
 * Owner, 2026-11-10: Search Console shows India and Pakistan finding the old and light game pages (GTA San Andreas,
 * Tekken 7, GTX 1650 games): players with low-end PCs and laptops. These pages answer "which games run on my RAM"
 * from the official Steam requirements of every game on /can-i-run/: the games whose recommended RAM fits, the ones
 * whose minimum fits (low settings), and popular games that need more. Nothing is estimated beyond Steam's numbers.
 * game-render.php calls rocGpRamPage() for the slugs in ROC_GP_RAM_PAGES (functions of game-render.php are in scope).
 */

const ROC_GP_RAM_PAGES = [4 => '/can-i-run/4gb-ram-games/', 8 => '/can-i-run/8gb-ram-games/', 16 => '/can-i-run/16gb-ram-games/'];

/** The RAM size of a slug ("8gb-ram-games" => 8), or 0. */
function rocGpRamSlug(string $slug): int {
    foreach (ROC_GP_RAM_PAGES as $gb => $path) if ($path === '/can-i-run/' . $slug . '/') return $gb;
    return 0;
}

/** [good, low, more]: recommended RAM fits, only the minimum fits, needs more (most popular first, like $games). */
function rocGpRamSplit(array $games, int $gb): array {
    $good = []; $low = []; $more = [];
    foreach ($games as $g) {
        $min = (float)($g['req']['min']['ram'] ?? 0);
        $rec = (float)($g['req']['rec']['ram'] ?? 0) ?: $min;
        if ($min <= 0) continue;
        if ($rec <= $gb) $good[] = [$g, $min, $rec];
        elseif ($min <= $gb) $low[] = [$g, $min, $rec];
        else $more[] = [$g, $min, $rec];
    }
    return [$good, $low, $more];
}

/** Links to the other RAM pages (for the hub's card list and these pages). */
function rocGpRamLinks(string $here = ''): string {
    $h = '';
    foreach (ROC_GP_RAM_PAGES as $gb => $p) {
        if ($p === $here) continue;
        $h .= '<a href="' . $p . '"><strong>' . $gb . ' GB RAM games</strong><span>What runs on ' . $gb . ' GB of memory</span></a>';
    }
    return $h;
}

function rocGpRamPage(string $root, array $games, int $gb, int $updated, string $headTop, string $bodyToMain, string $mainOpen, string $afterMain): void {
    [$good, $low, $more] = rocGpRamSplit($games, $gb);
    $all = count($good) + count($low) + count($more);
    $n = count($good) + count($low);
    $pct = function (int $x) use ($all) { return $all ? (int)round(100 * $x / $all) : 0; };
    $path = ROC_GP_RAM_PAGES[$gb];
    $url = ROC_PUBLIC_URL . $path;
    $crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/'], [$gb . ' GB RAM games', $path]];
    $title = 'Games for ' . $gb . ' GB RAM: ' . number_format($n) . ' PC Games That Run';
    // The longest description that fits in 155 characters, in whole sentences.
    $desc = '';
    foreach ([number_format($n) . ' PC games that run on ' . $gb . ' GB of RAM, from official Steam requirements: ' . number_format(count($good)) . ' at the recommended RAM. Check your PC too.',
              number_format($n) . ' PC games that run on ' . $gb . ' GB of RAM, from official Steam requirements. Check your graphics card and processor too.',
              number_format($n) . ' PC games that run on ' . $gb . ' GB of RAM, from official Steam requirements.'] as $try) {
        if (mb_strlen($try) <= 155) { $desc = $try; break; }
    }
    $next = $gb < 8 ? 8 : 16;
    [$g16] = rocGpRamSplit($games, 16);
    $faqs = [
        ['Is ' . $gb . ' GB of RAM enough for gaming?', $gb . ' GB meets the minimum RAM of ' . $pct($n) . '% and the recommended RAM of ' . $pct(count($good)) . '% of the '
            . number_format($all) . ' PC games on Run On Console (official Steam requirements). '
            . ($gb >= 16 ? '16 GB is the sweet spot for gaming today; only the heaviest new games recommend more.' : 'It is enough for lighter and older games; many new games recommend ' . $next . ' GB or 16 GB.')],
        ['Does more RAM give more FPS?', 'Only up to what a game needs. Below the recommended RAM a game can stutter or load slowly; above it, the graphics card and processor decide the FPS. '
            . 'Two sticks (dual channel) are faster than one, and that matters most for laptops with integrated graphics.'],
        ['What else decides if a game runs?', 'The graphics card first, then the processor. A game can fit your RAM and still need a stronger graphics card: open any game below to check all three parts, or see which games your PC runs at once.'],
    ];
    if ($gb < 16) $faqs[] = ['Should I upgrade to ' . $next . ' GB of RAM?', $next . ' GB covers the recommended RAM of ' . $pct(count(rocGpRamSplit($games, $next)[0])) . '% of these games'
        . ($next < 16 ? ' and 16 GB covers ' . $pct(count($g16)) . '%' : '') . '. Add a stick of the same type and speed as the one you have (DDR4 or DDR5), so the two run in dual channel.'];
    $top = array_slice($good, 0, 30);
    $ld = [rocGpCrumbs($crumbs), ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'Games for ' . $gb . ' GB RAM', 'url' => $url, 'description' => $desc,
        'dateModified' => gmdate('c', $updated), 'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'],
        'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $n, 'itemListElement' => array_map(function ($x, $i) {
            return ['@type' => 'ListItem', 'position' => $i + 1, 'url' => ROC_PUBLIC_URL . rocGpPath($x[0]), 'name' => $x[0]['name']];
        }, $top, array_keys($top))]],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
            return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]];
        }, $faqs)]];
    $og = rocGpOgCard($root, '_ram-' . $gb, 'Games for ' . $gb . ' GB RAM', number_format($n) . ' PC games that run on ' . $gb . ' GB', $updated);
    if ($og !== '') $ld[1]['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => ROC_PUBLIC_URL . $og, 'width' => 1200, 'height' => 630];
    $h = rocGpHead($title, $desc, $url, $ld, 'index, follow, max-image-preview:large', $og, 'Games for ' . $gb . ' GB RAM on Run On Console');

    $gbTxt = function (float $v) { return rtrim(rtrim(number_format($v, 1), '0'), '.') . ' GB'; };
    $item = function ($x, bool $both) use ($gbTxt) {
        return '<li data-n="' . rocH(strtolower($x[0]['name'])) . '"><a href="' . rocGpPath($x[0]) . '">' . rocH($x[0]['name']) . '</a><small>' . (int)$x[0]['y']
            . ' · min ' . $gbTxt($x[1]) . ($both && $x[2] > $x[1] ? ', rec ' . $gbTxt($x[2]) : '') . '</small></li>';
    };
    $m  = $mainOpen . '<div class="max-w-6xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
    $wall = rocGpWall(array_column($good, 0));
    $m .= '<header class="gp-hero' . ($wall !== '' ? ' pic' : '') . '"><div><h1>Games for ' . $gb . ' GB RAM</h1>'
        . '<p>' . number_format($n) . ' of the ' . number_format($all) . ' PC games on Run On Console run on ' . $gb . ' GB of RAM: ' . number_format(count($good)) . ' at their recommended RAM, '
        . 'and ' . number_format(count($low)) . ' at their minimum (low settings). Every number comes from the game&rsquo;s official Steam requirements. '
        . 'RAM is one of three parts: open a game to check your graphics card and processor too.</p>'
        . '<div class="gp-btns"><a class="gp-btn p" href="/can-i-run/my-pc/">Which games can my PC run? &rarr;</a><a class="gp-btn s" href="/compatibility/">Full PC check</a></div></div>' . $wall . '</header>';
    $m .= '<section class="gp-card"><label for="gp-q" class="sr-only">Search a game</label>'
        . '<input id="gp-q" class="gp-search" type="search" placeholder="Search these ' . number_format($n) . ' games…" autocomplete="off">'
        . '<p id="gp-none" class="gp-src" style="display:none;margin-top:.6rem">Not on this list: it needs more than ' . $gb . ' GB of RAM, or it is not in our list yet. Search it on <a href="/can-i-run/">all games</a>.</p></section>';
    $m .= '<section class="gp-card gp-nogpu"><h2>Recommended RAM is ' . $gb . ' GB or less (' . number_format(count($good)) . ' games)</h2>'
        . '<p class="gp-src">' . $gb . ' GB is all these games ask for, most popular first.</p><ul class="gp-list" style="margin-top:.6rem">' . implode('', array_map(function ($x) use ($item) { return $item($x, false); }, $good)) . '</ul></section>';
    $m .= rocAdUnit('list');
    if ($low) $m .= '<section class="gp-card gp-nogpu"><h2>Runs at the minimum: low settings (' . number_format(count($low)) . ' games)</h2>'
        . '<p class="gp-src">' . $gb . ' GB meets the minimum but not the recommended RAM: expect low settings, longer loading or some stutter.</p><ul class="gp-list" style="margin-top:.6rem">'
        . implode('', array_map(function ($x) use ($item) { return $item($x, true); }, $low)) . '</ul></section>';
    if ($more) $m .= '<section class="gp-card"><h2>Popular games that need more than ' . $gb . ' GB</h2><ul class="gp-list">'
        . implode('', array_map(function ($x) use ($item) { return $item($x, true); }, array_slice($more, 0, 30))) . '</ul></section>';
    $m .= '<section class="gp-card gp-faq"><h2>' . $gb . ' GB RAM for gaming: questions</h2>';
    foreach ($faqs as $i => $f) $m .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . rocH($f[0]) . '</summary><div class="a">' . rocH($f[1]) . '</div></details>';
    $m .= '</section>';
    $m .= '<section class="gp-card"><h2>Games by RAM and graphics card</h2><div class="gp-sim">' . rocGpRamLinks($path)
        . '<a href="' . ROC_GP_NOGPU . '"><strong>No graphics card</strong><span>Games for laptops with integrated graphics</span></a>'
        . '<a href="/category/ram/"><strong>Memory (RAM)</strong><span>DDR4 and DDR5 kits we cover</span></a>'
        . '<a href="/can-i-run/fps-reports/"><strong>FPS reports</strong><span>Real frame rates from players</span></a></div></section>';
    $m .= rocGpCardNav($path);
    $m .= '<p class="gp-src">Requirements: the official <a href="https://store.steampowered.com/" target="_blank" rel="noopener">Steam store</a> page of each game, updated ' . gmdate('F Y', $updated) . '.</p>';
    $m .= '</div>';
    $m .= "<script>(function(){var q=document.getElementById('gp-q'),none=document.getElementById('gp-none'),items=document.querySelectorAll('.gp-nogpu li[data-n]');if(!q)return;"
        . "q.addEventListener('input',function(){var v=q.value.trim().toLowerCase(),hit=0;for(var i=0;i<items.length;i++){var ok=!v||items[i].getAttribute('data-n').indexOf(v)>-1;items[i].style.display=ok?'':'none';if(ok)hit++;}"
        . "none.style.display=v&&!hit?'':'none';});})();</script>";
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}
