<?php
/**
 * Run On Console — graphics card comparisons for games (Deploy 176): /can-i-run/rtx-3060-vs-gtx-1660-super/
 *
 * Owner, 2026-11-10: competitor research (technical.city, gpucheck ...) shows "card vs card" searches bring much of
 * their traffic. These pages compare two cards with our own data only: how many of the PC games on Run On Console
 * each card runs on high, medium to high and low settings, from the official Steam minimum and recommended graphics
 * of every game, with the same rule as the card pages (game-render.php). No FPS is invented: the page says so.
 * Pairs: each card against the next three stronger cards in ROC_GP_GPUS (integrated graphics left out), so every
 * comparison is a real upgrade question. The stronger card is named first; the other order redirects.
 * game-render.php calls rocGpVsPage() / rocGpVsHub() (functions of game-render.php are in scope).
 */

const ROC_GP_VS_HUB = '/can-i-run/gpu-compare/';

function rocGpVsCardSlug(string $name): string { return trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower(rocGpCardShort($name))), '-'); }

/** All pairs as [stronger, weaker], weakest pairs first. */
function rocGpVsPairs(): array {
    static $pairs = null;
    if ($pairs !== null) return $pairs;
    $cards = array_values(array_filter(array_keys(rocGpRefs('gpu')), function ($n) { return strpos($n, 'integrated') === false; }));
    $pairs = [];
    foreach ($cards as $i => $weak) for ($j = $i + 1; $j <= min($i + 3, count($cards) - 1); $j++) $pairs[] = [$cards[$j], $weak];
    return $pairs;
}

function rocGpVsPath(array $p): string { return '/can-i-run/' . rocGpVsCardSlug($p[0]) . '-vs-' . rocGpVsCardSlug($p[1]) . '/'; }

/** [pair, canonical?] for a slug, or null. The reverse order is found too (it redirects). */
function rocGpVsBySlug(string $slug): ?array {
    if (strpos($slug, '-vs-') === false) return null;
    foreach (rocGpVsPairs() as $p) {
        $a = rocGpVsCardSlug($p[0]); $b = rocGpVsCardSlug($p[1]);
        if ($slug === $a . '-vs-' . $b) return [$p, true];
        if ($slug === $b . '-vs-' . $a) return [$p, false];
    }
    return null;
}

/** The tier of a game for a card score: the rule of the card pages. */
function rocGpVsTier(array $g, float $score): string {
    $gm = (float)($g['req']['min']['gpu'] ?? 0); $gr = max($gm, (float)($g['req']['rec']['gpu'] ?? $gm));
    return $gm > 0 && $score < $gm ? 'no' : ($gr <= 0 || $score >= $gr ? (empty($g['req']['nr']) || $score >= $gm + 2 ? 'high' : 'mid') : ($score >= 0.75 * $gr ? 'mid' : 'low'));
}

/** Product pages of a card (title contains its short name), for "specs and price". */
function rocGpVsProducts(?PDO $pdo, string $card): array {
    if (!$pdo) return [];
    try {
        $st = $pdo->prepare("SELECT slug, title FROM products WHERE status = 'published' AND title LIKE ? ORDER BY id LIMIT 2");
        $st->execute(['%' . rocGpCardShort($card) . '%']);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) { return []; }
}

function rocGpVsPage(string $root, ?PDO $pdo, array $games, array $pair, int $updated, string $headTop, string $bodyToMain, string $mainOpen, string $afterMain): void {
    [$A, $B] = $pair;
    $gpus = rocGpRefs('gpu');
    $sa = (float)$gpus[$A]; $sb = (float)$gpus[$B];
    $a = rocGpCardShort($A); $b = rocGpCardShort($B);
    $cnt = ['A' => ['high' => 0, 'mid' => 0, 'low' => 0, 'no' => 0], 'B' => ['high' => 0, 'mid' => 0, 'low' => 0, 'no' => 0]];
    $diff = []; $onlyA = []; $tooHeavy = []; $both = [];
    foreach ($games as $g) {
        $ta = rocGpVsTier($g, $sa); $tb = rocGpVsTier($g, $sb);
        $cnt['A'][$ta]++; $cnt['B'][$tb]++;
        if ($ta !== $tb) $diff[] = [$g, $ta, $tb];
        if ($tb === 'no' && $ta !== 'no') $onlyA[] = $g;
        if ($ta === 'no') $tooHeavy[] = $g;
        if ($ta === 'high' && $tb === 'high') $both[] = $g;
    }
    $n = count($games);
    $runs = function ($c) { return $c['high'] + $c['mid'] + $c['low']; };
    $label = ['high' => 'High', 'mid' => 'Medium to high', 'low' => 'Low', 'no' => 'Too heavy'];
    $path = rocGpVsPath($pair);
    $url = ROC_PUBLIC_URL . $path;
    $crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/'], ['GPU comparisons', ROC_GP_VS_HUB], [$a . ' vs ' . $b, $path]];
    $title = $a . ' vs ' . $b . ' for Gaming: Which Runs More Games?';
    if (mb_strlen($title) > 60) $title = $a . ' vs ' . $b . ': Which Runs More Games?';
    $desc = 'The ' . $a . ' runs ' . number_format($cnt['A']['high']) . ' PC games on high settings, the ' . $b . ' ' . number_format($cnt['B']['high']) . '. Compared game by game from official Steam requirements.';
    if (mb_strlen($desc) > 155) $desc = $a . ' vs ' . $b . ': ' . number_format($cnt['A']['high']) . ' against ' . number_format($cnt['B']['high']) . ' PC games on high settings, from official Steam requirements.';
    $up = $cnt['A']['high'] - $cnt['B']['high'];
    $popDiff = array_slice($diff, 0, 20);
    $names = function (array $list, int $k) { return implode(', ', array_map(function ($x) { return is_array($x) && isset($x[0]['name']) ? $x[0]['name'] : $x['name']; }, array_slice($list, 0, $k))); };
    $faqs = [
        ['Is the ' . $a . ' better than the ' . $b . ' for gaming?', ($sa > $sb && $cnt['A']['high'] > $cnt['B']['high'] ? 'Yes. ' : 'They are in the same class for games. ') . 'In our checker the ' . $a . ' runs ' . number_format($cnt['A']['high']) . ' of the ' . number_format($n) . ' PC games on Run On Console on high settings, the '
            . $b . ' ' . number_format($cnt['B']['high']) . '. Both are compared with each game\'s official minimum and recommended graphics card on Steam.'],
        ['Is it worth upgrading from the ' . $b . ' to the ' . $a . '?', $up > 0 ? 'It moves ' . number_format($up) . ($up === 1 ? ' game' : ' games') . ' up to high settings' . ($popDiff ? ', for example ' . $names($popDiff, 4) : '') . '. '
            . 'Check your processor and power supply first: a stronger card needs both to keep up.' : 'For the games on our list the difference is small; a bigger step up is worth more.'],
        ['Which games can the ' . $a . ' run that the ' . $b . ' cannot?', $onlyA ? number_format(count($onlyA)) . (count($onlyA) === 1 ? ' game is' : ' games are') . ' below the minimum for the ' . $b . ' but playable on the ' . $a . ', for example ' . $names($onlyA, 5) . '.'
            : 'None on our list: every game the ' . $a . ' runs, the ' . $b . ' runs too, only on lower settings.'],
        ['How is this compared?', 'Each card is checked against the minimum and recommended graphics card of every game, as listed on Steam. It is a comparison of what each card can run, not an FPS benchmark: frame rates also depend on the processor, RAM, resolution and the game\'s settings.'],
    ];
    $ld = [rocGpCrumbs($crumbs), ['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $a . ' vs ' . $b . ' for gaming', 'url' => $url, 'description' => $desc,
        'dateModified' => gmdate('c', $updated), 'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/']],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
            return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]];
        }, $faqs)]];
    $h = rocGpHead($title, $desc, $url, $ld, 'index, follow, max-image-preview:large', '', $a . ' vs ' . $b . ' on Run On Console');

    $tag = function (string $t) use ($label) { return '<span class="gp-tag ' . ($t === 'high' ? 'w' : '') . '" style="color:inherit">' . $label[$t] . '</span>'; };
    $m  = $mainOpen . '<div class="max-w-5xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
    $m .= '<header class="gp-hero"><div><h1>' . rocH($a) . ' vs ' . rocH($b) . ': which runs more games?</h1>'
        . '<p>Out of ' . number_format($n) . ' PC games, the ' . rocH($a) . ' runs ' . number_format($cnt['A']['high']) . ' on high settings and the ' . rocH($b) . ' ' . number_format($cnt['B']['high']) . '. '
        . 'Every game is checked against its official minimum and recommended graphics card on Steam, with the same rule as our checker. This compares what each card can run, not FPS.</p>'
        . '<div class="gp-btns"><a class="gp-btn p" href="/can-i-run/my-pc/">Which games can my PC run? &rarr;</a><a class="gp-btn s" href="' . ROC_GP_VS_HUB . '">All GPU comparisons</a></div></div></header>';
    $m .= '<section class="gp-card"><h2>Games each card runs</h2><div class="gp-wrap"><table class="gp-table"><thead><tr><th>Settings</th><th>' . rocH($a) . '</th><th>' . rocH($b) . '</th></tr></thead><tbody>';
    foreach (['high', 'mid', 'low', 'no'] as $t) $m .= '<tr><th scope="row">' . $label[$t] . '</th><td><b>' . number_format($cnt['A'][$t]) . '</b></td><td><b>' . number_format($cnt['B'][$t]) . '</b></td></tr>';
    $m .= '<tr><th scope="row">Runs in total</th><td><b>' . number_format($runs($cnt['A'])) . '</b></td><td><b>' . number_format($runs($cnt['B'])) . '</b></td></tr></tbody></table></div>'
        . '<p class="gp-src" style="margin-top:.5rem">High: meets the recommended graphics card. Medium to high: at least three quarters of it. Low: meets the minimum only. Too heavy: below the minimum.</p></section>';
    if ($popDiff) {
        $m .= '<section class="gp-card"><h2>Popular games where the ' . rocH($a) . ' makes the difference</h2><div class="gp-wrap"><table class="gp-table"><thead><tr><th>Game</th><th>' . rocH($a) . '</th><th>' . rocH($b) . '</th></tr></thead><tbody>';
        foreach ($popDiff as [$g, $ta, $tb]) $m .= '<tr><td><a href="' . rocGpPath($g) . '">' . rocH($g['name']) . '</a></td><td>' . $tag($ta) . '</td><td>' . $tag($tb) . '</td></tr>';
        $m .= '</tbody></table></div></section>';
    }
    if ($both) $m .= '<section class="gp-card"><h2>Both run these on high settings</h2><ul class="gp-list">'
        . implode('', array_map(function ($g) { return '<li><a href="' . rocGpPath($g) . '">' . rocH($g['name']) . '</a></li>'; }, array_slice($both, 0, 24))) . '</ul>'
        . '<p class="gp-src" style="margin-top:.6rem">' . number_format(count($both)) . ' games in all, most popular first.</p></section>';
    $m .= rocAdUnit('list');
    if ($tooHeavy) $m .= '<section class="gp-card"><h2>Too heavy for both</h2><ul class="gp-list">'
        . implode('', array_map(function ($g) { return '<li><a href="' . rocGpPath($g) . '">' . rocH($g['name']) . '</a></li>'; }, array_slice($tooHeavy, 0, 12))) . '</ul></section>';
    // The two cards: their games pages and product pages.
    $cards = '';
    foreach ([$A, $B] as $c) {
        $cp = rocGpCardPath($c);
        if ($cp) $cards .= '<a href="' . $cp . '"><strong>What games can ' . (preg_match('/^(?:RX|RTX|Arc|8)/', rocGpCardShort($c)) ? 'an ' : 'a ') . rocH(rocGpCardShort($c)) . ' run?</strong><span>Every game, sorted by settings</span></a>';
        foreach (rocGpVsProducts($pdo, $c) as $p) $cards .= '<a href="/products/' . rocH($p['slug']) . '/"><strong>' . rocH($p['title']) . '</strong><span>Specs and where to buy</span></a>';
    }
    if ($cards !== '') $m .= '<section class="gp-card"><h2>The two cards</h2><div class="gp-sim">' . $cards . '</div></section>';
    $m .= '<section class="gp-card gp-faq"><h2>' . rocH($a) . ' vs ' . rocH($b) . ': questions</h2>';
    foreach ($faqs as $i => $f) $m .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . rocH($f[0]) . '</summary><div class="a">' . rocH($f[1]) . '</div></details>';
    $m .= '</section>';
    $more = '';
    foreach (rocGpVsPairs() as $p) if ($p !== $pair && (in_array($A, $p, true) || in_array($B, $p, true)))
        $more .= '<a href="' . rocGpVsPath($p) . '"><strong>' . rocH(rocGpCardShort($p[0]) . ' vs ' . rocGpCardShort($p[1])) . '</strong><span>Which runs more games?</span></a>';
    if ($more !== '') $m .= '<section class="gp-card"><h2>More comparisons</h2><div class="gp-sim">' . $more . '</div></section>';
    $m .= '<p class="gp-src">Requirements: the official <a href="https://store.steampowered.com/" target="_blank" rel="noopener">Steam store</a> page of each game, updated ' . gmdate('F Y', $updated) . '.</p>';
    $m .= '</div>';
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}

/** /can-i-run/gpu-compare/: every comparison, by card. */
function rocGpVsHub(string $root, array $games, int $updated, string $headTop, string $bodyToMain, string $mainOpen, string $afterMain): void {
    $pairs = rocGpVsPairs();
    $url = ROC_PUBLIC_URL . ROC_GP_VS_HUB;
    $crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/'], ['GPU comparisons', ROC_GP_VS_HUB]];
    $title = 'GPU Comparisons for Gaming: ' . count($pairs) . ' Graphics Card Matchups';
    $desc = 'Compare two graphics cards by the PC games each one runs on high, medium and low settings, from official Steam requirements of ' . number_format(count($games)) . ' games.';
    $gpus = rocGpRefs('gpu');
    $ld = [rocGpCrumbs($crumbs), ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'GPU comparisons for gaming', 'url' => $url, 'description' => $desc,
        'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'],
        'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($pairs), 'itemListElement' => array_map(function ($p, $i) {
            return ['@type' => 'ListItem', 'position' => $i + 1, 'url' => ROC_PUBLIC_URL . rocGpVsPath($p), 'name' => rocGpCardShort($p[0]) . ' vs ' . rocGpCardShort($p[1])];
        }, array_slice($pairs, 0, 50), array_keys(array_slice($pairs, 0, 50)))]]];
    $h = rocGpHead($title, $desc, $url, $ld, 'index, follow, max-image-preview:large', '', 'GPU comparisons on Run On Console');
    $m  = $mainOpen . '<div class="max-w-6xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
    $m .= '<header class="gp-hero"><div><h1>GPU comparisons for gaming</h1><p>Thinking of an upgrade? Each page compares two graphics cards by the PC games they run on high, medium and low settings, '
        . 'checked against the official Steam requirements of ' . number_format(count($games)) . ' games. Each card is compared with the next three faster cards.</p>'
        . '<div class="gp-btns"><a class="gp-btn p" href="/can-i-run/my-pc/">Which games can my PC run? &rarr;</a><a class="gp-btn s" href="/category/gpu/">Graphics cards we cover</a></div></div></header>';
    $by = [];
    foreach ($pairs as $p) $by[$p[1]][] = $p;
    $m .= '<section class="gp-card"><h2>Upgrade from your card</h2>';
    foreach ($by as $weak => $list) {
        $m .= '<h3 class="gp-letter">From the ' . rocH(rocGpCardShort($weak)) . '</h3><ul class="gp-list">';
        foreach ($list as $p) $m .= '<li><a href="' . rocGpVsPath($p) . '">' . rocH(rocGpCardShort($p[0]) . ' vs ' . rocGpCardShort($p[1])) . '</a></li>';
        $m .= '</ul>';
    }
    $m .= '</section>' . rocGpCardNav(ROC_GP_VS_HUB) . '</div>';
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}
