<?php
/**
 * Run On Console — /how-we-check/ : how the "Can I run it" pages and the checker work (Deploy 153).
 *
 * page-render.php sends the slug "how-we-check" here. The numbers (games, estimated parts, card tiers) come from
 * the live game list (roc-steam-games.json through game-pages-lib.php) and the checker's own scores, so the page
 * never goes out of date. Linked from every game page (next to the Steam source), the checker and the author page.
 */

require_once __DIR__ . '/game-pages-lib.php';

function rocMethodPage(string $root): void {
    $games = rocGpAll($root);
    $n = count($games);
    $estG = 0; $estC = 0; $onlyMin = 0;
    foreach ($games as $g) {
        $e = (array)($g['req']['min']['est'] ?? []);
        if (!empty($e['gpu'])) $estG++;
        if (!empty($e['cpu'])) $estC++;
        if (!is_array($g['rec'] ?? null) || !$g['rec']) $onlyMin++;   // as the game page: no recommended block on Steam
    }
    $pct = function (int $x) use ($n) { return $n ? (int)round(100 * $x / $n) : 0; };
    $tiers = [];
    foreach (rocGpRefs('gpu') as $name => $s) $tiers[(string)$s][] = $name;
    $updated = (int)(@filemtime($root . '/roc-steam-games.json') ?: time());

    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, 'blog-post.html');
    $url = ROC_PUBLIC_URL . '/how-we-check/';
    $metaT = 'How We Check If a PC Can Run a Game | Run On Console';
    $metaD = 'How Run On Console checks ' . number_format($n) . ' PC games: official Steam requirements, a 1.8 to 10 tier score for each part and the 50/30/20 rule behind every percentage.';
    $answer = 'Run On Console compares your graphics card, processor and RAM with each game\'s official Steam requirements. Every part gets a tier score from 1.8 (integrated graphics) to 10 (the fastest), '
        . 'the graphics card counts for 50% of the result, the processor 30% and RAM 20%. Below the minimum, a PC never scores more than 40%. We do not measure FPS.';
    $faqs = [
        ['Where do the game requirements come from?', 'From each game\'s official Steam store page (minimum and recommended). Every game page links its Steam page and the month it was checked.'],
        ['Does Run On Console measure FPS?', 'No. We do not run benchmarks; the result says which settings a PC should manage (high, medium or low) from the official requirements, not a frame rate.'],
        ['Why does a game say "estimated"?', 'Some Steam pages name no graphics card or processor (for example "DirectX 11 card"). Then we estimate a low tier from the words and say so on the page.'],
        ['How does "Detect my graphics card" work?', 'Your browser shares the name of its graphics card through WebGL. We rate that name with the same tier scale and pick the closest card. Nothing is stored, and the processor and RAM stay your choice because browsers cannot read them reliably.'],
    ];

    $h  = '<title>' . rocH($metaT) . "</title>\n";
    $h .= '    <meta name="description" content="' . rocH($metaD) . "\" />\n";
    $h .= "    <meta name=\"robots\" content=\"index, follow, max-image-preview:large\" />\n";
    $h .= '    <link rel="canonical" href="' . $url . "\" />\n";
    $h .= "    <meta property=\"og:type\" content=\"article\" />\n";
    $h .= '    <meta property="og:url" content="' . $url . "\" />\n";
    $h .= '    <meta property="og:title" content="' . rocH($metaT) . "\" />\n";
    $h .= '    <meta property="og:description" content="' . rocH($metaD) . "\" />\n";
    $h .= "    <meta property=\"og:site_name\" content=\"Run On Console\" />\n";
    $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => ROC_PUBLIC_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Can I run it?', 'item' => ROC_PUBLIC_URL . '/can-i-run/'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => 'How we check', 'item' => $url]]]);
    $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => 'How Run On Console checks if a PC can run a game', 'url' => $url,
        'description' => $metaD, 'dateModified' => gmdate('c', $updated),
        'author' => ['@type' => 'Person', 'name' => ROC_OWNER, 'url' => ROC_PUBLIC_URL . ROC_OWNER_URL],
        'publisher' => ['@type' => 'Organization', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/', 'logo' => ['@type' => 'ImageObject', 'url' => ROC_PUBLIC_URL . '/images/logo-512.png']]]);
    $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
        return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]]; }, $faqs)]);
    $h .= rocArticleStyles();
    $h .= '<style>.hw-box{border:1px solid #a7f3d0;background:#ecfdf5;border-radius:1.25rem;padding:1.1rem 1.25rem;color:#064e3b;line-height:1.7}'
        . '.hw-t{width:100%;border-collapse:collapse;font-size:.9rem}.hw-t th,.hw-t td{border-bottom:1px solid #e2e8f0;padding:.55rem .6rem;text-align:left;vertical-align:top}.hw-t th{background:#f8fafc;color:#334155}'
        . 'html.roc-dark .hw-box{background:rgba(16,185,129,.08);border-color:rgba(52,211,153,.3);color:#a7f3d0}html.roc-dark .hw-t th{background:#0b1220;color:#cbd5e1}html.roc-dark .hw-t td,html.roc-dark .hw-t th{border-color:#1e293b}</style>';
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    $tierRows = '';
    foreach ($tiers as $s => $names) $tierRows .= '<tr><td><b>' . rocH($s) . '</b></td><td>' . rocH(implode(', ', $names)) . '</td></tr>';
    $m  = $mainOpen . '<article class="max-w-4xl mx-auto py-6 space-y-8">'
        . '<nav aria-label="Breadcrumb" class="text-xs text-slate-500 font-medium"><a href="/" class="hover:text-emerald-600">Home</a> <span aria-hidden="true">›</span> <a href="/can-i-run/" class="hover:text-emerald-600">Can I run it?</a> <span aria-hidden="true">›</span> <span class="text-slate-700">How we check</span></nav>'
        . '<header class="space-y-3"><h1 class="font-display font-extrabold text-3xl sm:text-5xl text-slate-900 leading-tight">How we check if a PC can run a game</h1>'
        . '<p class="text-sm text-slate-500">By <a href="' . ROC_OWNER_URL . '" rel="author" class="font-bold text-emerald-700">' . ROC_OWNER . '</a>, Founder of Run On Console · data updated ' . date('j F Y', $updated) . '</p></header>'
        . '<div class="hw-box">' . rocH($answer) . '</div>'
        . '<div class="roc-article">'
        . '<h2>1. Where the data comes from</h2>'
        . '<p>Every one of the <b>' . number_format($n) . ' PC games</b> on <a href="/can-i-run/">Can I run it?</a> uses the minimum and recommended requirements from the game\'s <b>official Steam store page</b>. '
        . 'Each game page links that Steam page and says the month it was checked. Every month new popular games and new releases join by themselves: we look at the most played and best-selling games on Steam '
        . 'and add those that are released, list PC requirements and are not adult-only.</p>'
        . '<p>' . number_format($onlyMin) . ' games (' . $pct($onlyMin) . '%) list only minimum requirements on Steam; their pages say so.</p>'
        . '<h2>2. How we read a requirement line</h2>'
        . '<p>Steam lines often name several parts, such as "NVIDIA GeForce GTX 1050 Ti or AMD Radeon RX 470". Any one of them is enough, so the <b>cheapest named part</b> counts. '
        . 'Extras like "for ray tracing" or "for VR" are left out, because they are not needed to play.</p>'
        . '<p>Some pages name no model at all ("DirectX 11 compatible card", "dual-core processor"). Then we give a low <b>estimated</b> tier from the words, and the game page says the part is estimated. '
        . 'Today that is the graphics card for ' . number_format($estG) . ' games (' . $pct($estG) . '%) and the processor for ' . number_format($estC) . ' games (' . $pct($estC) . '%), mostly older and lighter games.</p>'
        . '<h2>3. The tier scale</h2>'
        . '<p>Graphics cards and processors get a tier score from <b>1.8</b> (integrated graphics such as Intel UHD and Iris Xe) to <b>10</b> (the fastest cards and processors). Cards of about the same gaming speed share a score. '
        . 'The scores are our own judgement from the cards\' and processors\' class and generation, not a benchmark result. The graphics cards in our checker:</p>'
        . '<div class="gp-wrap" style="overflow-x:auto"><table class="hw-t"><thead><tr><th>Tier</th><th>Graphics cards</th></tr></thead><tbody>' . $tierRows . '</tbody></table></div>'
        . '<p>Each tier also has a page of the games that card class runs, for example <a href="/can-i-run/gtx-1650-games/">games a GTX 1650 can run</a> and <a href="/can-i-run/without-graphics-card/">games without a graphics card</a>.</p>'
        . '<h2>4. How the percentage is worked out</h2>'
        . '<ul><li>Each part is compared with the game\'s <b>recommended</b> requirement: the graphics card counts for <b>50%</b>, the processor <b>30%</b> and RAM <b>20%</b>. A part at or above the recommended gets its full share.</li>'
        . '<li>If any part is <b>below the minimum</b>, the result is at most <b>40%</b>.</li>'
        . '<li>If any part is below the recommended, the result is at most <b>89%</b>, so "high settings" only shows when every part meets the recommended specs.</li></ul>'
        . '<div class="gp-wrap" style="overflow-x:auto"><table class="hw-t"><thead><tr><th>Result</th><th>What it means</th></tr></thead><tbody>'
        . '<tr><td>90-100%</td><td>Runs on high settings: every part meets the recommended specs</td></tr>'
        . '<tr><td>70-89%</td><td>Playable on medium to high settings</td></tr>'
        . '<tr><td>41-69%</td><td>Playable on low settings only</td></tr>'
        . '<tr><td>40% or less</td><td>Below the minimum: it will not run well</td></tr></tbody></table></div>'
        . '<p>The same rules run on every game page, in the full <a href="/compatibility/">checker</a> and in ROC, our assistant. The full checker also asks for your storage: a hard disk instead of an SSD lowers the result a little. When a part falls short, the page names the part to upgrade first.</p>'
        . '<h2>5. What we do not do</h2>'
        . '<ul><li>We do not measure <b>FPS</b> and do not run lab tests; the result is a settings level from the official requirements.</li>'
        . '<li>Laptop versions of a graphics card are often a little slower than the desktop card with the same name.</li>'
        . '<li>Graphics memory (VRAM), storage speed, drivers and screen resolution are not part of the score; the requirements table on each game page shows what Steam lists.</li></ul>'
        . '<h2>6. Detect my graphics card</h2>'
        . '<p>On game pages and in the checker, "Detect my graphics card" asks your browser for the name of its graphics card (WebGL). We rate that name on the same tier scale and choose the closest card in the list. '
        . 'Nothing is stored. Browsers cannot read the processor and RAM reliably, so you choose those. Safari and some privacy settings hide the card; then pick it yourself.</p>'
        . '<h2>7. Who checks it, and corrections</h2>'
        . '<p>The Run On Console team keeps the data and the rules; <a href="' . ROC_OWNER_URL . '">' . ROC_OWNER . '</a>, the founder of Run On Console, approves changes to them. '
        . 'Found a game with wrong requirements or a result that looks off? Tell us on the <a href="/contact/">contact page</a> with the game\'s address and we will check it against Steam.</p>'
        . '<h2>Questions</h2>';
    foreach ($faqs as $f) $m .= '<h3>' . rocH($f[0]) . '</h3><p>' . rocH($f[1]) . '</p>';
    $m .= '</div></article>';
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}
