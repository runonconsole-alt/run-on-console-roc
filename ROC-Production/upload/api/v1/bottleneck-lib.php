<?php
/**
 * Run On Console — bottleneck calculator for games (Deploy 180): /can-i-run/bottleneck-calculator/
 *
 * Owner, 2026-11-11 (competitor gap): "bottleneck calculator" is one of the most searched PC tools. Other sites show a
 * single invented "bottleneck %". This page answers the real question with our own data: with your graphics card,
 * processor and RAM, in how many of the PC games on Run On Console is each part the one that keeps a game below high
 * settings, and which single upgrade lifts the most games. Same rules and data as the game pages and
 * /can-i-run/my-pc/ (its ?data list). The part that holds a game back is the one furthest below what the game
 * recommends (its minimum when Steam lists no recommended).
 * game-render.php calls rocGpBottleneck() (functions of game-render.php are in scope).
 */

const ROC_GP_BOTTLENECK = '/can-i-run/bottleneck-calculator/';

/** The checker's rule (mypc-lib.php chk / agent-lib.php), and the part that holds the game back. [percent, part or ''] */
function rocBnCheck(array $m, array $r, bool $nr, array $h): array {
    $w = [.5, .3, .2]; $s = 0; $t = 0; $below = false; $miss = 0; $worst = ''; $low = 9.0;
    foreach ([0, 1, 2] as $i) {
        $need = $r[$i] ?: $m[$i];
        if ($need > 0) {
            $ratio = $h[$i] / $need;
            $s += $w[$i] * min(1, $ratio); $t += $w[$i];
            if ($h[$i] < $need) { $miss++; if ($ratio < $low) { $low = $ratio; $worst = ['gpu', 'cpu', 'ram'][$i]; } }
        }
        if ($m[$i] > 0 && $h[$i] < $m[$i]) $below = true;
    }
    $p = (int)round(100 * $s / ($t ?: 1));
    if ($below) $p = min($p, 40);
    $room = !$nr || ($h[0] >= $m[0] + 2 && $h[1] >= $m[1] + 2 && $h[2] >= 2 * $m[2]);
    if ($miss || !$room) $p = min($p, 89);
    return [max(5, $p), $worst];
}

/** For one PC: games on high settings, and how many each part holds back. */
function rocBnCount(array $games, array $h): array {
    $c = ['high' => 0, 'gpu' => 0, 'cpu' => 0, 'ram' => 0];
    foreach ($games as $g) {
        $mn = (array)($g['req']['min'] ?? []); $rc = (array)($g['req']['rec'] ?? $mn);
        $m = [(float)($mn['gpu'] ?? 0), (float)($mn['cpu'] ?? 0), (float)($mn['ram'] ?? 0)];
        $r = [(float)($rc['gpu'] ?? 0), (float)($rc['cpu'] ?? 0), (float)($rc['ram'] ?? 0)];
        [$p, $part] = rocBnCheck($m, $r, !empty($g['req']['nr']), $h);
        if ($p >= 90) $c['high']++; elseif ($part !== '') $c[$part]++;
    }
    return $c;
}

function rocGpBottleneck(string $root, array $games, int $updated, string $headTop, string $bodyToMain, string $mainOpen, string $afterMain): void {
    $n = count($games);
    $url = ROC_PUBLIC_URL . ROC_GP_BOTTLENECK;
    $gpus = rocGpRefs('gpu'); $cpus = rocGpRefs('cpu');
    $crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/'], ['Bottleneck calculator', ROC_GP_BOTTLENECK]];
    $title = 'Bottleneck Calculator for Games: CPU or GPU? (' . gmdate('Y') . ')';
    $desc = 'Is your graphics card or processor holding your games back? Check your PC against the Steam requirements of ' . number_format($n) . ' games and see which upgrade helps most.';
    if (mb_strlen($desc) > 155) $desc = 'Is your graphics card or processor holding your games back? Check your PC against ' . number_format($n) . ' PC games and see which upgrade helps most.';
    // Common PCs, worked out on the server (the same numbers the calculator shows for them).
    $common = [['GeForce GTX 1650', 'Intel Core i5-8400', 16], ['GeForce GTX 1060 6GB', 'AMD Ryzen 5 2600', 16], ['GeForce RTX 3060', 'Intel Core i3-10100', 16],
               ['GeForce RTX 3060', 'AMD Ryzen 5 5600', 16], ['GeForce RTX 4060', 'Intel Core i5-12400', 16], ['Radeon RX 6600', 'AMD Ryzen 5 3600', 8]];
    $rows = [];
    foreach ($common as [$gn, $cn, $ram]) if (isset($gpus[$gn], $cpus[$cn])) $rows[] = [$gn, $cn, $ram, rocBnCount($games, [$gpus[$gn], $cpus[$cn], (float)$ram])];
    $faqs = [
        ['What is a bottleneck in gaming?', 'The part of your PC that stops games from running better. If the graphics card is far below what a game asks for while the processor is fine, the graphics card is the bottleneck, and a new processor would not help that game.'],
        ['How does this bottleneck calculator work?', 'It checks your graphics card, processor and RAM against the official Steam minimum and recommended requirements of ' . number_format($n) . ' PC games, with the same rules as our game pages. For every game not on high settings, the part furthest below the recommended is counted as the one holding it back.'],
        ['Why no bottleneck percentage?', 'A single percentage is not something that can be measured: it changes with every game, the resolution and the settings. Counting the games each part holds back shows where an upgrade pays off.'],
        ['Does a CPU bottleneck matter at 1440p or 4K?', 'Less. At higher resolutions the graphics card does more of the work, so a processor that is a bottleneck at 1080p often keeps up at 1440p or 4K. The requirements on Steam are mostly for 1080p.'],
        ['Is more RAM a fix for a bottleneck?', 'Only when RAM is the part below the recommended. 16 GB in two sticks covers almost every game; beyond that, the graphics card and processor decide.'],
    ];
    $ld = [rocGpCrumbs($crumbs),
        ['@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => 'Bottleneck calculator for games (Run On Console)', 'url' => $url, 'description' => $desc,
         'applicationCategory' => 'UtilitiesApplication', 'operatingSystem' => 'Any (web browser)', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
         'publisher' => ['@type' => 'Organization', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/']],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
            return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]]; }, $faqs)]];
    $h = rocGpHead($title, $desc, $url, $ld, 'index, follow, max-image-preview:large', '', 'Bottleneck calculator on Run On Console');
    $h .= '<style>.bn-bars{display:grid;gap:.6rem;margin-top:.6rem}.bn-row{display:grid;grid-template-columns:9rem 1fr 4.5rem;gap:.6rem;align-items:center;font-size:.9rem}'
        . '.bn-bar{height:.8rem;border-radius:99px;background:#e2e8f0;overflow:hidden}.bn-bar i{display:block;height:100%;border-radius:99px;background:#10b981}.bn-bar.gpu i{background:#f59e0b}.bn-bar.cpu i{background:#6366f1}.bn-bar.ram i{background:#ec4899}'
        . '.bn-verdict{font:800 1.25rem Outfit,Inter,sans-serif;color:#064e3b;margin-top:.4rem}.bn-up li{margin:.35rem 0}html.roc-dark .bn-verdict{color:#a7f3d0}html.roc-dark .bn-bar{background:#1e293b}</style>';

    $opt = function (array $refs, string $pick) { $o = ''; foreach ($refs as $nm => $s) $o .= '<option value="' . rocH($nm) . '"' . ($nm === $pick ? ' selected' : '') . '>' . rocH($nm) . '</option>'; return $o; };
    $m  = $mainOpen . '<div class="max-w-5xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
    $m .= '<header class="gp-hero"><div><h1>Bottleneck calculator for games</h1>'
        . '<p>Is it your graphics card or your processor? Choose your parts and see, across ' . number_format($n) . ' PC games and their official Steam requirements, how many games each part keeps below high settings, '
        . 'and which single upgrade lifts the most games. No invented bottleneck percentage: just the games.</p></div></header>';
    $m .= '<section class="gp-card"><h2>Your PC</h2><div class="gp-check" style="margin-top:.6rem" id="bn-box">'
        . '<label>Graphics card<select data-k="gpu">' . $opt($gpus, 'GeForce GTX 1650') . '</select></label>'
        . '<label>Processor<select data-k="cpu">' . $opt($cpus, 'Intel Core i5-8400') . '</select></label>'
        . '<label>Memory (RAM)<select data-k="ram">' . implode('', array_map(function ($r) { return '<option value="' . $r . '"' . ($r === 16 ? ' selected' : '') . '>' . $r . ' GB</option>'; }, [4, 6, 8, 12, 16, 24, 32, 64])) . '</select></label>'
        . '</div><p class="gp-src" style="margin-top:.6rem">Your PC from <a href="/can-i-run/my-pc/">Which games can my PC run?</a> and the game pages is filled in when you saved one.</p></section>';
    $m .= '<section class="gp-card" aria-live="polite"><h2>What holds your games back</h2><div id="bn-out"><p class="gp-src">Loading the games…</p></div></section>';
    $m .= '<section class="gp-card"><h2>Upgrades that lift the most games</h2><p class="gp-src">Parts still sold new, each counted alone with your other parts unchanged.</p><ul class="bn-up" id="bn-up" style="margin-top:.6rem"></ul></section>';
    $m .= '<section class="gp-card"><h2>Popular games your PC holds back</h2><ul class="gp-list" id="bn-games" style="margin-top:.6rem"></ul></section>';
    if ($rows) {
        $m .= '<section class="gp-card"><h2>Common PCs: what holds them back</h2><div class="gp-wrap"><table class="gp-table"><thead><tr><th>PC</th><th>High settings</th><th>Held back by the graphics card</th><th>By the processor</th><th>By RAM</th></tr></thead><tbody>';
        foreach ($rows as [$gn, $cn, $ram, $c]) $m .= '<tr><td>' . rocH(rocGpCardShort($gn) . ' + ' . preg_replace('/^(Intel Core|AMD) /', '', $cn) . ' + ' . $ram . ' GB') . '</td><td><b>' . number_format($c['high']) . '</b></td><td>' . number_format($c['gpu']) . '</td><td>' . number_format($c['cpu']) . '</td><td>' . number_format($c['ram']) . '</td></tr>';
        $m .= '</tbody></table></div><p class="gp-src" style="margin-top:.5rem">Out of ' . number_format($n) . ' PC games. A PC with an RTX 3060 and a Core i3-10100 shows how a processor can hold back a strong graphics card.</p></section>';
    }
    $m .= rocAdUnit('list');
    $m .= '<section class="gp-card gp-faq"><h2>Bottlenecks: questions</h2>';
    foreach ($faqs as $i => $f) $m .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . rocH($f[0]) . '</summary><div class="a">' . rocH($f[1]) . '</div></details>';
    $m .= '</section>';
    $m .= '<p class="gp-src">More: <a href="/can-i-run/my-pc/">Which games can my PC run?</a> · <a href="' . (defined('ROC_GP_VS_HUB') ? ROC_GP_VS_HUB : '/can-i-run/gpu-compare/') . '">GPU comparisons</a> · <a href="/category/gpu/">Graphics cards</a> · <a href="/category/cpu/">Processors</a> · <a href="/how-we-check/">How we check games</a></p>';
    $m .= rocGpCardNav(ROC_GP_BOTTLENECK) . '</div>';
    $m .= <<<'JS'
<script>(function(){
var box=document.getElementById('bn-box'),out=document.getElementById('bn-out'),up=document.getElementById('bn-up'),gl=document.getElementById('bn-games'),D=null;
function sel(k){return box.querySelector('[data-k="'+k+'"]')}
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
/* The checker's rule, and the part furthest below what the game recommends. */
function chk(m,r,nr,h){var w=[.5,.3,.2],s=0,t=0,below=false,miss=0,worst='',low=9;for(var i=0;i<3;i++){var need=r[i]||m[i];if(need>0){var q=h[i]/need;s+=w[i]*Math.min(1,q);t+=w[i];if(h[i]<need){miss++;if(q<low){low=q;worst=['gpu','cpu','ram'][i]}}}if(m[i]>0&&h[i]<m[i])below=true;}
var p=Math.round(100*s/(t||1));if(below)p=Math.min(p,40);var room=!nr||(h[0]>=m[0]+2&&h[1]>=m[1]+2&&h[2]>=2*m[2]);if(miss||!room)p=Math.min(p,89);return[Math.max(5,p),worst]}
function count(h,list){var c={high:0,gpu:0,cpu:0,ram:0};D.g.forEach(function(g){var x=chk(g[3],g[4],g[5],h);if(x[0]>=90)c.high++;else if(x[1]){c[x[1]]++;if(list&&list.length<24)list.push([g,x[1]])}});return c}
function have(){return[D.gpu[sel('gpu').value],D.cpu[sel('cpu').value],+sel('ram').value]}
var NAMES={gpu:'Graphics card',cpu:'Processor',ram:'RAM'};
function groups(refs,cur,n){var by={};Object.keys(refs).forEach(function(k){if(D.shops.indexOf(k)>-1&&refs[k]>cur)(by[refs[k]]=by[refs[k]]||[]).push(k)});
return Object.keys(by).map(Number).sort(function(a,b){return a-b}).slice(0,n).map(function(s){return[by[s].join(' / '),s]})}
function render(){if(!D)return;var h=have(),held=[],c=count(h,held),n=D.g.length,back=c.gpu+c.cpu+c.ram;
var top=back?['gpu','cpu','ram'].sort(function(a,b){return c[b]-c[a]})[0]:'';
var verdict=!back?'No bottleneck: your PC runs these games on high settings.':(c[top]>=2*(back-c[top])?'Your '+NAMES[top].toLowerCase()+' is the bottleneck.':'Fairly balanced: no single part holds most games back.');
var bar=function(k,label,v){return '<div class="bn-row"><span>'+label+'</span><span class="bn-bar '+k+'"><i style="width:'+Math.round(100*v/n)+'%"></i></span><b>'+v.toLocaleString()+'</b></div>'};
out.innerHTML='<p class="bn-verdict">'+verdict+'</p><p class="gp-src">Out of '+n.toLocaleString()+' PC games:</p><div class="bn-bars">'+bar('ok','High settings',c.high)+bar('gpu','Graphics card holds back',c.gpu)+bar('cpu','Processor holds back',c.cpu)+bar('ram','RAM holds back',c.ram)+'</div>';
var ups=[];groups(D.gpu,h[0],3).forEach(function(x){ups.push([count([x[1],h[1],h[2]]).high-c.high,x[0],'Graphics card'])});
groups(D.cpu,h[1],2).forEach(function(x){ups.push([count([h[0],x[1],h[2]]).high-c.high,x[0],'Processor'])});
[16,32].forEach(function(r){if(r>h[2])ups.push([count([h[0],h[1],r]).high-c.high,r+' GB of RAM','Memory'])});
ups=ups.filter(function(u){return u[0]>0}).sort(function(a,b){return b[0]-a[0]});
up.innerHTML=ups.length?ups.slice(0,5).map(function(u){return '<li><b>'+esc(u[1])+'</b> <small>('+u[2]+')</small>: +'+u[0].toLocaleString()+' games on high settings</li>'}).join(''):'<li>No single upgrade adds games on high settings: your PC is already strong for this list.</li>';
gl.innerHTML=held.length?held.map(function(x){return '<li><a href="/can-i-run/'+x[0][0]+'/">'+esc(x[0][1])+'</a><small>'+NAMES[x[1]]+'</small></li>'}).join(''):'<li>None: every game runs on high settings.</li>';}
function pick(s,name,score,refs){var o=[].filter.call(s.options,function(x){return x.value===name})[0];if(!o&&score){var best=null;[].forEach.call(s.options,function(x){var v=refs[x.value];if(v<=score&&(!best||v>refs[best.value]))best=x});o=best}if(o)s.value=o.value}
fetch('/can-i-run/my-pc/?data=1').then(function(r){return r.json()}).then(function(d){D=d;var p=null;try{p=JSON.parse(localStorage.getItem('roc_pc')||'null')}catch(e){}
if(p){pick(sel('gpu'),p.gpu,p.gs,D.gpu);pick(sel('cpu'),p.cpu,p.cs,D.cpu);if(p.ram)sel('ram').value=String(p.ram)}render()}).catch(function(){out.innerHTML='<p class="gp-src">The games could not be loaded. Please refresh the page.</p>'});
box.addEventListener('change',function(){render();if(window.rocTrack)rocTrack('roc_bottleneck',sel('gpu').value,sel('cpu').value)});
})();</script>
JS;
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}
