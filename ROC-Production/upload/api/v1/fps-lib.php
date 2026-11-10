<?php
/**
 * Run On Console — players' FPS reports on the game pages (Deploy 160).
 *
 * A player sends the frame rate they get (graphics card, processor, RAM, resolution, preset, FPS) from the form on a
 * game page; it is a roc_messages row (kind "fps", messages.php) and appears on the page only after the owner presses
 * Publish in the notification email (review-publish.php, the same signed links as the reviews). These are players'
 * own numbers, shown as such: we do not measure FPS ourselves (How we check games).
 */

/** Published reports of one game, newest first: [name, gpu, cpu, ram, res, preset, fps, upscale, time]. */
function rocFpsReports(?PDO $pdo, string $slug): array {
    if (!$pdo) return [];
    try {
        $q = $pdo->prepare("SELECT name, meta_json, created_at FROM roc_messages WHERE kind = 'fps' AND meta_json LIKE ? ORDER BY created_at DESC LIMIT 200");
        $q->execute(['%"game":"' . $slug . '"%']);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) {
        $m = json_decode((string)$r['meta_json'], true) ?: [];
        if (empty($m['published']) || ($m['game'] ?? '') !== $slug) continue;
        $out[] = ['name' => (string)$r['name'], 'gpu' => (string)$m['gpu'], 'cpu' => (string)$m['cpu'], 'ram' => (int)$m['ram'], 'res' => (string)$m['res'],
                  'preset' => (string)$m['preset'], 'fps' => (int)$m['fps'], 'up' => ($m['upscale'] ?? '0') === '1', 't' => strtotime((string)$r['created_at']) ?: time()];
    }
    return $out;
}

/** The section of a game page: the reports (with a typical FPS per card when a card has several) and the form. */
function rocFpsSection(?PDO $pdo, string $slug, string $name, array $gpus, array $cpus): string {
    $reps = rocFpsReports($pdo, $slug);
    $h = '<section class="gp-card" id="fps"><h2>' . rocH($name) . ' FPS: what players get</h2>';
    if ($reps) {
        // The median of each card + resolution + preset with two or more reports.
        $grp = [];
        foreach ($reps as $r) $grp[$r['gpu'] . '|' . $r['res'] . '|' . $r['preset']][] = $r['fps'];
        $typ = [];
        foreach ($grp as $k => $v) if (count($v) >= 2) { sort($v); $n = count($v); $typ[$k] = [$n % 2 ? $v[intdiv($n, 2)] : (int)round(($v[$n / 2 - 1] + $v[$n / 2]) / 2), $n]; }
        if ($typ) {
            $h .= '<ul class="gp-qa" style="margin-bottom:.8rem">';
            foreach ($typ as $k => $t) { [$g, $res, $pre] = explode('|', $k); $h .= '<li><b>' . rocH($g) . ', ' . rocH($res) . ' ' . rocH($pre) . ':</b> about ' . $t[0] . ' FPS (' . $t[1] . ' players)</li>'; }
            $h .= '</ul>';
        }
        $h .= '<div class="gp-wrap"><table class="gp-table"><thead><tr><th>Graphics card</th><th>Processor</th><th>RAM</th><th>Settings</th><th>FPS</th></tr></thead><tbody>';
        foreach (array_slice($reps, 0, 30) as $r)
            $h .= '<tr><td>' . rocH($r['gpu']) . '</td><td>' . rocH($r['cpu']) . '</td><td>' . $r['ram'] . ' GB</td><td>' . rocH($r['res'] . ' ' . $r['preset']) . ($r['up'] ? ' + upscaling' : '') . '</td><td><b>' . $r['fps'] . '</b></td></tr>';
        $h .= '</tbody></table></div><p class="gp-src" style="margin-top:.5rem">Reported by players (' . count($reps) . '), checked by us before they appear. Frame rates change with drivers, patches and the scene; we do not measure them ourselves. <a href="' . rocFpsPagePath() . '">See every FPS report</a></p>';
    } else {
        $h .= '<p>No player has shared an FPS for ' . rocH($name) . ' yet. Played it? Your numbers help the next player decide. <a href="' . rocFpsPagePath() . '">See FPS reports for other games</a></p>';
    }
    // Deploy 165: nothing is chosen for the player. The first report came with every default and 60 FPS, which says nothing.
    $sel = function (array $list, string $first) { $o = '<option value="">' . rocH($first) . '</option>'; foreach ($list as $n) $o .= '<option value="' . rocH($n) . '">' . rocH($n) . '</option>'; return $o; };
    $h .= '<details class="gp-fps-form" style="margin-top:1rem"' . ($reps ? '' : ' open') . '><summary class="gp-btn s" style="cursor:pointer;list-style:none;display:inline-flex">Share your FPS</summary>'
        . '<form id="fps-form" novalidate style="margin-top:.8rem"><input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">'
        . '<div class="gp-check">'
        . '<label>Graphics card<select name="gpu" required>' . $sel(array_keys($gpus), 'Choose your graphics card') . '</select></label>'
        . '<label>Processor<select name="cpu" required>' . $sel(array_keys($cpus), 'Choose your processor') . '</select></label>'
        . '<label>Memory (RAM)<select name="ram" required><option value="">Choose your RAM</option>' . implode('', array_map(function ($r) { return '<option value="' . $r . '">' . $r . ' GB</option>'; }, [4, 6, 8, 12, 16, 24, 32, 64])) . '</select></label>'
        . '<label>Resolution<select name="res" required>' . $sel(['720p', '900p', '1080p', '1440p', '4K', 'Steam Deck'], 'Choose the resolution') . '</select></label>'
        . '<label>Settings<select name="preset" required>' . $sel(['Low', 'Medium', 'High', 'Ultra'], 'Choose the preset') . '</select></label>'
        . '<label>Average FPS<input name="fps" type="number" min="1" max="500" inputmode="numeric" placeholder="e.g. 60" style="border:2px solid #e2e8f0;border-radius:.8rem;padding:.6rem .7rem;font-size:.9rem;font-weight:600"></label>'
        . '<label>Nickname<input name="name" maxlength="40" placeholder="Shown with your report" style="border:2px solid #e2e8f0;border-radius:.8rem;padding:.6rem .7rem;font-size:.9rem"></label>'
        . '<label>Email (optional)<input name="email" type="email" maxlength="150" placeholder="Never shown" style="border:2px solid #e2e8f0;border-radius:.8rem;padding:.6rem .7rem;font-size:.9rem"></label>'
        . '</div><label style="display:flex;gap:.4rem;align-items:center;margin-top:.6rem;font-size:.85rem"><input type="checkbox" name="upscale"> I used DLSS, FSR or XeSS upscaling</label>'
        . '<div class="gp-btns"><button type="submit" class="gp-btn p">Send my FPS</button></div><p class="gp-src" id="fps-msg" role="status" style="margin-top:.5rem"></p></form></details></section>';
    $h .= "<script>(function(){var f=document.getElementById('fps-form');if(!f)return;var m=document.getElementById('fps-msg');"
        // The PC from the quick check (or the saved PC) fills the form.
        . "try{var P=JSON.parse(localStorage.getItem('roc_pc')||'null');if(P){if(P.gpu)f.gpu.value=P.gpu;if(P.cpu)f.cpu.value=P.cpu;if(P.ram)f.ram.value=String(P.ram)}}catch(e){}"
        . "f.addEventListener('submit',function(e){e.preventDefault();var v=function(n){return (f.elements[n].value||'').trim()},fps=parseInt(v('fps'),10);"
        . "var left=[['gpu','graphics card'],['cpu','processor'],['ram','RAM'],['res','resolution'],['preset','settings']].filter(function(x){return !v(x[0])}).map(function(x){return x[1]});"
        . "if(left.length){m.textContent='Please choose your '+left.join(', ')+'.';return}"
        . "if(!(fps>=1&&fps<=500)){m.textContent='Please enter your average FPS (1 to 500).';return}"
        . "var b=f.querySelector('button');b.disabled=true;m.textContent='Sending…';"
        . "fetch('/api/v1/messages.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({kind:'fps',name:v('name'),email:v('email'),message:'',"
        . "meta:{game:" . json_encode($slug) . ",gpu:v('gpu'),cpu:v('cpu'),ram:v('ram'),res:v('res'),preset:v('preset'),fps:String(fps),upscale:f.upscale.checked?'1':'0'},website:v('website')})})"
        . ".then(function(r){return r.json().catch(function(){return {}})}).then(function(d){if(d&&d.success){f.reset();m.textContent='Thanks! Your '+fps+' FPS report for '+" . json_encode($name, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_TAG) . "+' reached us. We check every report and add it to this page soon.';if(window.rocTrack)rocTrack('roc_fps_report'," . json_encode($slug) . ",String(fps))}"
        . "else{m.textContent=(d&&d.error)||'Could not send. Please try again.'}}).catch(function(){m.textContent='No connection. Please try again.'}).then(function(){b.disabled=false})})})();</script>";
    return $h;
}


/* ------------------------------------------------ all FPS reports (Deploy 169) */

/** The address of the page with every report (a function, not a const: game-render.php loads this file late). */
function rocFpsPagePath(): string { return '/can-i-run/fps-reports/'; }

/** Every published report, newest first, with its game (only games that are on the site). */
function rocFpsAll(?PDO $pdo, array $games): array {
    if (!$pdo) return [];
    try {
        $rows = $pdo->query("SELECT name, meta_json, created_at FROM roc_messages WHERE kind = 'fps' ORDER BY created_at DESC LIMIT 1000")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) {
        $m = json_decode((string)$r['meta_json'], true) ?: [];
        $g = $games[(string)($m['game'] ?? '')] ?? null;
        if (empty($m['published']) || !$g) continue;
        $out[] = ['game' => $g, 'name' => trim((string)$r['name']), 'gpu' => (string)$m['gpu'], 'cpu' => (string)$m['cpu'], 'ram' => (int)$m['ram'],
                  'res' => (string)$m['res'], 'preset' => (string)$m['preset'], 'fps' => (int)$m['fps'], 'up' => ($m['upscale'] ?? '0') === '1',
                  't' => strtotime((string)$r['created_at']) ?: time()];
    }
    return $out;
}

/** /can-i-run/fps-reports/: every report players shared, how to measure FPS and how to share it. */
function rocFpsPage(string $root, ?PDO $pdo, array $games, int $updated, string $headTop, string $bodyToMain, string $mainOpen, string $afterMain): void {
    $reps = rocFpsAll($pdo, $games);
    $path = rocFpsPagePath();
    $url = ROC_PUBLIC_URL . $path;
    $nGames = count(array_unique(array_map(function ($r) { return $r['game']['slug']; }, $reps)));
    $nCards = count(array_unique(array_column($reps, 'gpu')));
    $mod = $reps ? max(array_column($reps, 't')) : $updated;
    $crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/'], ['FPS reports', $path]];
    $title = 'PC Game FPS Reports From Real Players | ROC';
    $desc = 'Real FPS from players: the game, graphics card, processor, RAM and settings of every report. Compare them with your PC or share your own FPS.';
    $faq = [
        ['Where do these FPS numbers come from?', 'Players send them with the Share your FPS form on each game page: their graphics card, processor, RAM, resolution, settings and average FPS. We check every report before it appears. We do not measure them ourselves.'],
        ['Why can my FPS be different?', 'Frame rates change with the scene, game patches, graphics drivers, background apps, laptop power limits and whether upscaling (DLSS, FSR or XeSS) is on. Use a report as a guide for similar parts, not as a promise.'],
        ['How do I see my FPS in a game?', 'Turn on an FPS counter: in Steam it is under Settings, In Game; the NVIDIA app and AMD Software have their own overlays, and many games show FPS in their settings. Play a normal part of the game for a few minutes and note the average, not the highest number.'],
        ['How do I share my FPS?', 'Open the game on Run On Console (Can I run it?), go to the FPS section and press Share your FPS. Choose your own parts and settings; a nickname is enough and the email is optional.'],
    ];
    $ld = [rocGpCrumbs($crumbs),
        ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'FPS reports from real players', 'url' => $url, 'description' => $desc,
         'dateModified' => gmdate('c', $mod), 'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'],
         'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $nGames, 'itemListElement' => array_values(array_map(function ($r, $i) {
             return ['@type' => 'ListItem', 'position' => $i + 1, 'url' => ROC_PUBLIC_URL . rocGpPath($r['game']), 'name' => $r['game']['name'] . ' FPS'];
         }, $uniq = array_values(array_filter($reps, function ($r) { static $seen = []; $k = $r['game']['slug']; if (isset($seen[$k])) return false; return $seen[$k] = true; })), array_keys($uniq)))]],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($q) {
            return ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]];
        }, $faq)]];
    // An empty page has nothing for Google yet: it is indexed (and in the sitemap) from the first published report.
    $h = rocGpHead($title, $desc, $url, $ld, $reps ? 'index, follow, max-image-preview:large' : 'noindex, follow', '', 'FPS reports from real players on Run On Console');

    $m = $mainOpen . '<div class="max-w-6xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
    $m .= '<header class="gp-hero"><div><h1>FPS reports from real players</h1>'
        . '<p>How PC games really run on the parts players own: the graphics card, processor, RAM, resolution and settings of every report, with the average FPS they saw. '
        . 'Find a PC like yours, then check the game&rsquo;s official requirements.</p>'
        . ($reps ? '<p class="gp-src"><b>' . count($reps) . '</b> report' . (count($reps) === 1 ? '' : 's') . ' · <b>' . $nGames . '</b> game' . ($nGames === 1 ? '' : 's')
            . ' · <b>' . $nCards . '</b> graphics card' . ($nCards === 1 ? '' : 's') . ' · last report ' . gmdate('j M Y', $mod) . '</p>' : '')
        . '<div class="gp-btns"><a class="gp-btn p" href="/can-i-run/">Find your game and share your FPS</a><a class="gp-btn s" href="/can-i-run/my-pc/">Which games can my PC run?</a></div></div></header>';

    if ($reps) {
        $m .= '<section class="gp-card"><h2>All FPS reports</h2>'
            . '<label for="fps-q" class="sr-only">Filter the reports</label><input id="fps-q" class="gp-search" type="search" placeholder="Filter by game or part, e.g. GTX 1650, Ryzen 5, Elden Ring" autocomplete="off" style="margin-bottom:.8rem">'
            . '<div class="gp-wrap"><table class="gp-table" id="fps-all"><thead><tr><th>Game</th><th>Graphics card</th><th>Processor</th><th>RAM</th><th>Settings</th><th>FPS</th><th>Date</th></tr></thead><tbody>';
        foreach (array_slice($reps, 0, 300) as $r) {
            $m .= '<tr><td><a href="' . rocGpPath($r['game']) . '#fps">' . rocH($r['game']['name']) . '</a></td><td>' . rocH($r['gpu']) . '</td><td>' . rocH($r['cpu']) . '</td><td>' . $r['ram'] . ' GB</td>'
                . '<td>' . rocH($r['res'] . ' ' . $r['preset']) . ($r['up'] ? ' + upscaling' : '') . '</td><td><b>' . $r['fps'] . '</b></td><td>' . gmdate('j M Y', $r['t']) . '</td></tr>';
        }
        $m .= '</tbody></table></div><p class="gp-src" style="margin-top:.5rem">Reported by players and checked by us before they appear. We do not measure them ourselves.</p></section>';
        // The graphics cards in the reports that have their own page (what games a card runs).
        $cardLinks = [];
        foreach (array_count_values(array_column($reps, 'gpu')) as $card => $n) {
            if (defined('ROC_GP_CARD_PAGES') && isset(ROC_GP_CARD_PAGES[$card])) $cardLinks[] = '<li><a href="' . rocGpCardPath($card) . '">What games can a ' . rocH(rocGpCardShort($card)) . ' run?</a> <small>' . $n . ' report' . ($n === 1 ? '' : 's') . ' here</small></li>';
        }
        if ($cardLinks) $m .= '<section class="gp-card"><h2>Graphics cards in these reports</h2><ul class="gp-list">' . implode('', $cardLinks) . '</ul></section>';
    } else {
        $m .= '<section class="gp-card"><h2>No reports yet</h2><p>Be the first: open a game below, play it for a few minutes and share your average FPS.</p></section>';
    }

    $m .= '<section class="gp-card"><h2>How to measure your FPS</h2><ol class="gp-qa">'
        . '<li><b>Turn on an FPS counter.</b> In Steam: Settings, In Game. The NVIDIA app and AMD Software have their own overlays, and many games show FPS in their own settings.</li>'
        . '<li><b>Pick your usual settings.</b> Note the resolution (1080p, 1440p, 4K) and the preset (Low, Medium, High, Ultra), and whether DLSS, FSR or XeSS is on.</li>'
        . '<li><b>Play a normal part of the game for a few minutes.</b> Note the average FPS, not the highest number in a menu or an empty room.</li>'
        . '<li><b>Share it.</b> Open the game on Run On Console, go to the FPS section and press Share your FPS.</li></ol></section>';
    $pop = array_slice(array_values($games), 0, 12);
    $m .= '<section class="gp-card"><h2>Popular games to report</h2><ul class="gp-list">'
        . implode('', array_map(function ($g) { return '<li><a href="' . rocGpPath($g) . '#fps">' . rocH($g['name']) . ' FPS</a></li>'; }, $pop)) . '</ul>'
        . '<p class="gp-src" style="margin-top:.6rem">Every other game: <a href="/can-i-run/">all ' . number_format(count($games)) . ' PC games A to Z</a>.</p></section>';
    $m .= '<section class="gp-card"><h2>FPS reports: questions</h2><div class="gp-qa">';
    foreach ($faq as $q) $m .= '<h3>' . rocH($q[0]) . '</h3><p>' . rocH($q[1]) . '</p>';
    $m .= '</div></section>';
    $m .= '<p class="gp-src">More: <a href="/can-i-run/report-2026/">PC game requirements report</a> · <a href="/can-i-run/without-graphics-card/">Games without a graphics card</a> · <a href="/compatibility/">Can I run it? checker</a></p>';
    $m .= '</div>';
    if ($reps) $m .= "<script>(function(){var q=document.getElementById('fps-q'),rows=document.querySelectorAll('#fps-all tbody tr');if(!q)return;"
        . "q.addEventListener('input',function(){var v=q.value.trim().toLowerCase();for(var i=0;i<rows.length;i++)rows[i].style.display=!v||rows[i].textContent.toLowerCase().indexOf(v)>-1?'':'none';});})();</script>";
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $mod);
}

