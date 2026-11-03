<?php
/**
 * Run On Console — /can-i-run/steam-deck/ (Deploy 157): Valve's Steam Deck ratings of the games on the list.
 * game-render.php includes it for the slug "steam-deck"; its helpers (rocGpHead, rocGpCrumbs, ...) are in scope there.
 * The ratings: steam-deck-lib.php.
 */

require_once __DIR__ . '/steam-deck-lib.php';

function rocGpDeckPage(string $root, array $games, int $updated, string $headTop, string $bodyToMain, string $mainOpen, string $afterMain): void {
    $apps = rocDeckLoad($root)['apps'];
    $by = [3 => [], 2 => [], 1 => [], 0 => []]; $rated = 0;
    foreach ($games as $g) { $a = $apps[(string)(int)$g['appid']] ?? null; if (!$a) continue; $by[(int)$a['c']][] = $g; $rated++; }
    $n = count($games);
    $url = ROC_PUBLIC_URL . ROC_DECK_PAGE;
    $crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/'], ['Steam Deck games', ROC_DECK_PAGE]];
    $title = 'Steam Deck Games: ' . number_format(count($by[3])) . ' Verified and ' . number_format(count($by[2])) . ' Playable PC Games';
    $desc = 'Which PC games run on Steam Deck: Valve\'s Verified, Playable and Unsupported ratings for ' . number_format($rated) . ' popular games, with what each rating means.';
    if (mb_strlen($desc) > 155) $desc = mb_substr($desc, 0, 152) . '…';
    $faqs = [
        ['What does Steam Deck Verified mean?', 'Valve tested the game on Steam Deck and it works great with the default controls, settings and screen: no extra steps needed.'],
        ['What does Steam Deck Playable mean?', 'The game runs on Steam Deck, but Valve found something that needs extra effort, such as small text, a launcher that needs the touchscreen, or controls you set up yourself.'],
        ['Why is a game Unsupported?', 'Usually because its anti-cheat or launcher does not run on SteamOS, or because it needs hardware the Deck does not have, such as a VR headset.'],
        ['Where do these ratings come from?', 'From Valve\'s own Steam Deck tests, the same rating the game\'s Steam store page shows. We check them again every two months.'],
    ];
    $top = array_slice($by[3], 0, 30);
    $ld = [rocGpCrumbs($crumbs), ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'Steam Deck games', 'url' => $url, 'description' => $desc,
        'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'],
        'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($by[3]), 'itemListElement' => array_map(function ($g, $i) {
            return ['@type' => 'ListItem', 'position' => $i + 1, 'url' => ROC_PUBLIC_URL . rocGpPath($g), 'name' => $g['name']]; }, $top, array_keys($top))]],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
            return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]]; }, $faqs)]];
    $og = rocGpOgCard($root, '_steam-deck', 'Steam Deck games', number_format(count($by[3])) . ' Verified, ' . number_format(count($by[2])) . ' Playable', $updated);
    // Until half the list is rated the page is thin: hidden from Google, still open and linked.
    $h = rocGpHead($title, $desc, $url, $ld, $rated >= 0.5 * $n ? 'index, follow, max-image-preview:large' : 'noindex, follow', $og, 'Steam Deck games on Run On Console');

    $item = function ($g) { return '<li data-n="' . rocH(strtolower($g['name'])) . '"><a href="' . rocGpPath($g) . '#steam-deck">' . rocH($g['name']) . '</a><small>' . (int)$g['y'] . '</small></li>'; };
    $m  = $mainOpen . '<div class="max-w-6xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
    $m .= '<header class="gp-hero"><div><h1>Steam Deck games: what runs on Steam Deck</h1>'
        . '<p>Valve\'s Steam Deck rating for ' . number_format($rated) . ' of the ' . number_format($n) . ' PC games on Run On Console: <b>' . number_format(count($by[3])) . ' Verified</b>, <b>'
        . number_format(count($by[2])) . ' Playable</b> and ' . number_format(count($by[1])) . ' Unsupported' . ($rated < $n ? ' (the rest are being checked)' : '') . '. '
        . 'Open a game to see the tests behind its rating and its full PC requirements.</p>'
        . '<div class="gp-btns"><a class="gp-btn p" href="/can-i-run/">All PC games &rarr;</a><a class="gp-btn s" href="/gaming-platforms/pc-handheld-pc/">Handheld PC guide</a></div></div></header>';
    $m .= '<section class="gp-card"><label for="gp-q" class="sr-only">Search a game</label><input id="gp-q" class="gp-search" type="search" placeholder="Search a game: does it run on Steam Deck?" autocomplete="off">'
        . '<p id="gp-none" class="gp-src" style="display:none;margin-top:.6rem">Not in these lists: it may not be rated yet. Search it on <a href="/can-i-run/">all games</a>.</p></section>';
    foreach ([[3, 'Steam Deck Verified', 'Works great with the default controls and settings.'], [2, 'Steam Deck Playable', 'Runs, with some extra steps (small text, touchscreen menus, controls to set up).'],
              [1, 'Unsupported on Steam Deck', 'Does not run on the Deck in Valve\'s tests (often anti-cheat or a launcher).']] as [$c, $h2, $sub]) {
        if (!$by[$c]) continue;
        $lab = rocDeckLabel($c);
        $m .= '<section class="gp-card gp-nogpu"><h2><span class="gp-deck ' . $lab['cls'] . '">' . $lab['label'] . '</span> ' . $h2 . ' (' . number_format(count($by[$c])) . ' games)</h2><p class="gp-src">' . $sub . ' Most popular first.</p>'
            . '<ul class="gp-list" style="margin-top:.6rem">' . implode('', array_map($item, $by[$c])) . '</ul></section>';
        if ($c === 3) $m .= rocAdUnit('list');
    }
    if (!$rated) $m .= '<section class="gp-card"><p>The ratings are being collected from Steam. Come back soon.</p></section>';
    $m .= '<section class="gp-card gp-faq"><h2>Steam Deck ratings: questions</h2>';
    foreach ($faqs as $i => $f) $m .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . rocH($f[0]) . '</summary><div class="a">' . rocH($f[1]) . '</div></details>';
    $m .= '</section>' . rocGpCardNav();
    $m .= '<p class="gp-src">Ratings: Valve\'s Steam Deck tests, as shown on each game\'s <a href="https://store.steampowered.com/" target="_blank" rel="noopener">Steam store</a> page, checked every two months. '
        . 'PC requirements and how we check them: <a href="/how-we-check/">How we check games</a>.</p></div>';
    $m .= "<script>(function(){var q=document.getElementById('gp-q'),none=document.getElementById('gp-none'),items=document.querySelectorAll('.gp-nogpu li[data-n]');"
        . "q.addEventListener('input',function(){var v=q.value.trim().toLowerCase(),hit=0;"
        . "for(var i=0;i<items.length;i++){var ok=!v||items[i].getAttribute('data-n').indexOf(v)>-1;items[i].style.display=ok?'':'none';if(ok)hit++;}"
        . "none.style.display=v&&!hit?'':'none';});})();</script>";
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}
