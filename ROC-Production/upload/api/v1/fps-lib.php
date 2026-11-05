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
        $h .= '</tbody></table></div><p class="gp-src" style="margin-top:.5rem">Reported by players (' . count($reps) . '), checked by us before they appear. Frame rates change with drivers, patches and the scene; we do not measure them ourselves.</p>';
    } else {
        $h .= '<p>No player has shared an FPS for ' . rocH($name) . ' yet. Played it? Your numbers help the next player decide.</p>';
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
        . ".then(function(r){return r.json().catch(function(){return {}})}).then(function(d){if(d&&d.success){f.reset();m.textContent='Thank you! We check every report and add it to this page soon.';if(window.rocTrack)rocTrack('roc_fps_report'," . json_encode($slug) . ",String(fps))}"
        . "else{m.textContent=(d&&d.error)||'Could not send. Please try again.'}}).catch(function(){m.textContent='No connection. Please try again.'}).then(function(){b.disabled=false})})})();</script>";
    return $h;
}
