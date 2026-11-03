<?php
/**
 * Run On Console — "Which games can my PC run?" (Deploy 156): /can-i-run/my-pc/
 *
 * One PC (graphics card, processor, RAM; detected or chosen, remembered in this browser as "roc_pc", the same key
 * the game pages use) against every game on the list, with the checker's rules (agent-lib.php rocAgentCheck,
 * including the Deploy 155 rule for games with only minimum requirements), and the upgrades that open the most
 * games. The games come from /can-i-run/my-pc/?data=1 (rocGpMyPcData, cached for an hour); the page works out
 * the results in the browser, so nothing about the visitor's PC reaches the server.
 * game-render.php calls these for the slug "my-pc" (its functions are in scope).
 */

const ROC_GP_MYPC = '/can-i-run/my-pc/';

/** The game list for the page: [slug, name, year, [min gpu, cpu, ram], [rec gpu, cpu, ram], nr], most popular first. */
function rocGpMyPcData(array $games): void {
    $num = function ($v) { return is_numeric($v) ? (float)$v : 0; };
    $out = ['gpu' => rocGpRefs('gpu'), 'cpu' => rocGpRefs('cpu'), 'shops' => ROC_GP_IN_SHOPS, 'g' => []];
    foreach ($games as $g) {
        $mn = (array)($g['req']['min'] ?? []); $rc = (array)($g['req']['rec'] ?? $mn);
        $out['g'][] = [$g['slug'], $g['name'], (int)($g['y'] ?? 0), [$num($mn['gpu'] ?? 0), $num($mn['cpu'] ?? 0), $num($mn['ram'] ?? 0)],
            [$num($rc['gpu'] ?? 0), $num($rc['cpu'] ?? 0), $num($rc['ram'] ?? 0)], !empty($g['req']['nr']) ? 1 : 0];
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    header('X-Robots-Tag: noindex');
    echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function rocGpMyPc(string $root, array $games, int $updated, string $headTop, string $bodyToMain, string $mainOpen, string $afterMain): void {
    $n = count($games);
    $url = ROC_PUBLIC_URL . ROC_GP_MYPC;
    $crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/'], ['Which games can my PC run?', ROC_GP_MYPC]];
    $title = 'Which Games Can My PC Run? Check ' . number_format($n) . ' PC Games at Once';
    $desc = 'Choose or detect your graphics card, processor and RAM once and see which of ' . number_format($n) . ' PC games run on high, medium or low settings, and which upgrade opens the most games.';
    if (mb_strlen($desc) > 155) $desc = mb_substr($desc, 0, 152) . '…';
    $faqs = [
        ['How do I know which games my PC can run?', 'Choose your graphics card, processor and RAM (or press Detect my graphics card), and this page checks all ' . number_format($n) . ' games against their official Steam requirements at once, with the same rules as each game page.'],
        ['Is my PC saved?', 'Only in this browser, so the game pages and this page remember it. Nothing about your PC is sent to Run On Console; press Forget my PC to remove it.'],
        ['Which upgrade helps most?', 'Under the results, the page counts how many more games each graphics card, processor or RAM upgrade still sold today would run on high settings or at all.'],
        ['Does it show FPS?', 'No. It shows the settings level from the official requirements (high, medium to high, low, or below the minimum); we do not measure frame rates. See how we check games.'],
    ];
    $ld = [rocGpCrumbs($crumbs),
        ['@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => 'Which games can my PC run? (Run On Console)', 'url' => $url, 'description' => $desc,
         'applicationCategory' => 'UtilitiesApplication', 'operatingSystem' => 'Any (web browser)', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
         'publisher' => ['@type' => 'Organization', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/']],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
            return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]]; }, $faqs)]];
    $og = rocGpOgCard($root, '_my-pc', 'Which games can my PC run?', number_format($n) . ' PC games checked at once', $updated);
    $h = rocGpHead($title, $desc, $url, $ld, 'index, follow, max-image-preview:large', $og, 'Which games can my PC run? on Run On Console');
    $h .= '<style>.mp-sum{display:grid;grid-template-columns:repeat(2,1fr);gap:.6rem}@media(min-width:640px){.mp-sum{grid-template-columns:repeat(4,1fr)}}'
        . '.mp-sum button{border:2px solid #e2e8f0;border-radius:1rem;padding:.8rem;text-align:left;background:#fff;cursor:pointer}.mp-sum button b{display:block;font:800 1.6rem Outfit,Inter,sans-serif}'
        . '.mp-sum button.on{border-color:#10b981;background:#ecfdf5}.mp-sum .ok b{color:#047857}.mp-sum .mid b{color:#0e7490}.mp-sum .low b{color:#b45309}.mp-sum .no b{color:#be123c}'
        . '.mp-list li small{margin-left:.35rem}.mp-up li{margin:.35rem 0}.mp-note{font-size:.8rem;color:#64748b}'
        . 'html.roc-dark .mp-sum button{background:#0b1220;border-color:#1e293b;color:#e2e8f0}html.roc-dark .mp-sum button.on{background:rgba(16,185,129,.1);border-color:#10b981}</style>';

    $opt = function (array $refs, string $pick) { $o = ''; foreach ($refs as $nm => $s) $o .= '<option value="' . rocH($nm) . '"' . ($nm === $pick ? ' selected' : '') . '>' . rocH($nm) . '</option>'; return $o; };
    $m  = $mainOpen . '<div class="max-w-5xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
    $m .= '<header class="gp-hero"><div><h1>Which games can my PC run?</h1>'
        . '<p>Set your PC once and see all ' . number_format($n) . ' PC games at once: which run on high, medium or low settings, which do not, and which upgrade opens the most games. '
        . 'Same rules as every game page, from the official Steam requirements. Your PC stays in this browser.</p></div></header>';
    $m .= '<section class="gp-card" id="check"><h2>Your PC</h2>'
        . '<div class="gp-check" style="margin-top:.6rem" id="mp-box">'
        . '<label>Graphics card<select data-k="gpu">' . $opt(rocGpRefs('gpu'), 'GeForce GTX 1650') . '</select></label>'
        . '<label>Processor<select data-k="cpu">' . $opt(rocGpRefs('cpu'), 'Intel Core i5-8400') . '</select></label>'
        . '<label>Memory (RAM)<select data-k="ram">' . implode('', array_map(function ($r) { return '<option value="' . $r . '"' . ($r === 16 ? ' selected' : '') . '>' . $r . ' GB</option>'; }, [4, 6, 8, 12, 16, 24, 32, 64])) . '</select></label>'
        . '</div><p style="margin-top:.7rem"><button type="button" class="gp-btn s" id="gp-detect">Detect my graphics card</button> <span class="gp-src" id="gp-detect-msg"></span></p>'
        . '<p class="mp-note" id="mp-saved"></p></section>';
    $m .= '<section class="gp-card"><h2 id="mp-h">Results</h2><div class="mp-sum" id="mp-sum"><p class="gp-src">Loading the games…</p></div>'
        . '<label for="mp-q" class="sr-only">Search a game</label><input id="mp-q" class="gp-search" type="search" placeholder="Search a game…" autocomplete="off" style="margin-top:.9rem">'
        . '<ul class="gp-list mp-list" id="mp-list" style="margin-top:.8rem"></ul><p class="gp-src" id="mp-more" style="margin-top:.6rem"></p></section>';
    $m .= '<section class="gp-card"><h2>Upgrades that open the most games</h2><p class="gp-src">Parts still sold new, counted against the whole list with your other parts unchanged. See them on '
        . '<a href="/category/gpu/">graphics cards</a> and <a href="/category/cpu/">processors</a>.</p><ul class="mp-up" id="mp-up" style="margin-top:.6rem"></ul></section>';
    $m .= '<section class="gp-card gp-faq"><h2>Questions</h2>';
    foreach ($faqs as $i => $f) $m .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . rocH($f[0]) . '</summary><div class="a">' . rocH($f[1]) . '</div></details>';
    $m .= '</section>';
    $m .= '<p class="gp-src">How the results are worked out: <a href="/how-we-check/">How we check games</a> · numbers across all games: <a href="/can-i-run/report-2026/">PC Game Requirements Report</a> · '
        . 'without a graphics card: <a href="' . ROC_GP_NOGPU . '">games for integrated graphics</a>.</p>';
    $m .= rocGpCardNav();
    $m .= '</div>';

    // The checker's rule (agent-lib.php rocAgentCheck): graphics 50%, processor 30%, RAM 20% of the recommended; below the
    // minimum at most 40%; below the recommended at most 89%; games with only minimum requirements (nr) at most 89%
    // unless the PC is well above the minimum (graphics and processor +2 tiers, twice the RAM).
    $m .= <<<'JS'
<script>(function(){
var box=document.getElementById('mp-box'),sum=document.getElementById('mp-sum'),list=document.getElementById('mp-list'),more=document.getElementById('mp-more'),up=document.getElementById('mp-up'),q=document.getElementById('mp-q'),sv=document.getElementById('mp-saved');
var D=null,filter='ok',KEY='roc_pc';
function sel(k){return box.querySelector('[data-k="'+k+'"]')}
function load(){try{return JSON.parse(localStorage.getItem(KEY)||'null')}catch(e){return null}}
function save(){try{localStorage.setItem(KEY,JSON.stringify({gpu:sel('gpu').value,cpu:sel('cpu').value,ram:+sel('ram').value,gs:D?D.gpu[sel('gpu').value]:null,cs:D?D.cpu[sel('cpu').value]:null}));showSaved(true)}catch(e){}}
function showSaved(on){sv.innerHTML=on?'Saved in this browser: the game pages use this PC too. <a href="#" id="mp-forget">Forget my PC</a>':'';var f=document.getElementById('mp-forget');if(f)f.onclick=function(e){e.preventDefault();try{localStorage.removeItem(KEY)}catch(x){}showSaved(false)}}
function chk(m,r,nr,h){var w=[.5,.3,.2],s=0,t=0,below=false,miss=0;for(var i=0;i<3;i++){var need=r[i]||m[i];if(need>0){s+=w[i]*Math.min(1,h[i]/need);t+=w[i];if(h[i]<need)miss++;}if(m[i]>0&&h[i]<m[i])below=true;}
var p=Math.round(100*s/(t||1));if(below)p=Math.min(p,40);var room=!nr||(h[0]>=m[0]+2&&h[1]>=m[1]+2&&h[2]>=2*m[2]);if(miss||!room)p=Math.min(p,89);return Math.max(5,p)}
function tier(p){return p>=90?'ok':p>=70?'mid':p>40?'low':'no'}
var NAMES={ok:'High settings',mid:'Medium to high',low:'Low settings only',no:'Below the minimum'};
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function have(){return[D.gpu[sel('gpu').value],D.cpu[sel('cpu').value],+sel('ram').value]}
function count(h){var c={ok:0,mid:0,low:0,no:0};D.g.forEach(function(g){c[tier(chk(g[3],g[4],g[5],h))]++});return c}
function render(){if(!D)return;var h=have(),c={ok:0,mid:0,low:0,no:0},rows={ok:[],mid:[],low:[],no:[]};
D.g.forEach(function(g){var p=chk(g[3],g[4],g[5],h),t=tier(p);c[t]++;rows[t].push([g,p])});
sum.innerHTML=['ok','mid','low','no'].map(function(k){return '<button type="button" class="'+k+(k===filter?' on':'')+'" data-f="'+k+'"><b>'+c[k].toLocaleString()+'</b>'+NAMES[k]+'</button>'}).join('');
[].forEach.call(sum.querySelectorAll('button'),function(b){b.onclick=function(){filter=b.getAttribute('data-f');render()}});
var v=q.value.trim().toLowerCase(),src=v?[].concat(rows.ok,rows.mid,rows.low,rows.no).filter(function(x){return x[0][1].toLowerCase().indexOf(v)>-1}):rows[filter],shown=src.slice(0,300);
document.getElementById('mp-h').textContent=v?'Results for "'+q.value.trim()+'"':NAMES[filter]+' ('+rows[filter].length.toLocaleString()+' games, most popular first)';
list.innerHTML=shown.map(function(x){return '<li><a href="/can-i-run/'+x[0][0]+'/">'+esc(x[0][1])+'</a><small>'+(x[0][2]||'')+' · '+x[1]+'%'+(v?' · '+NAMES[tier(x[1])]:'')+'</small></li>'}).join('')||'<li>No game found.</li>';
more.textContent=src.length>300?'Showing 300 of '+src.length.toLocaleString()+': search a game to find the rest.':'';
upgrades(h,c)}
function upgrades(h,c){var out=[],base=c.ok,play=c.ok+c.mid+c.low;
function add(label,h2,kind){var c2=count(h2),g1=c2.ok-base,g2=(c2.ok+c2.mid+c2.low)-play;if(g1>0||g2>0)out.push([g1,g2,label,kind])}
// Parts of the same speed share one line ("GeForce RTX 3060 / GeForce RTX 4060 / Radeon RX 7600").
function groups(refs,cur,n){var by={};Object.keys(refs).forEach(function(k){if(D.shops.indexOf(k)>-1&&refs[k]>cur)(by[refs[k]]=by[refs[k]]||[]).push(k)});
return Object.keys(by).map(Number).sort(function(a,b){return a-b}).slice(0,n).map(function(s){return [by[s].join(' / '),s]})}
groups(D.gpu,h[0],4).forEach(function(x){add(x[0],[x[1],h[1],h[2]],'Graphics card')});
groups(D.cpu,h[1],2).forEach(function(x){add(x[0],[h[0],x[1],h[2]],'Processor')});
[16,32].forEach(function(r){if(r>h[2])add(r+' GB of RAM',[h[0],h[1],r],'Memory')});
out.sort(function(a,b){return b[0]-a[0]||b[1]-a[1]});
up.innerHTML=out.length?out.slice(0,6).map(function(u){return '<li><b>'+esc(u[2])+'</b> <small>('+u[3]+')</small>: +'+u[0].toLocaleString()+' games on high settings'+(u[1]>0?', +'+u[1].toLocaleString()+' more playable':'')+'</li>'}).join(''):'<li>Your PC already runs almost every game on the list well.</li>'}
function pick(s,name,score,refs){var o=[].filter.call(s.options,function(x){return x.value===name})[0];if(!o&&score){var best=null;[].forEach.call(s.options,function(x){var v=refs[x.value];if(v<=score&&(!best||v>refs[best.value]))best=x});o=best}if(o)s.value=o.value}
fetch('/can-i-run/my-pc/?data=1').then(function(r){return r.json()}).then(function(d){D=d;var p=load();
if(p){pick(sel('gpu'),p.gpu,p.gs,D.gpu);pick(sel('cpu'),p.cpu,p.cs,D.cpu);if(p.ram)sel('ram').value=String(p.ram);showSaved(true)}render()}).catch(function(){sum.innerHTML='<p class="gp-src">The games could not be loaded. Please refresh the page.</p>'});
box.addEventListener('change',function(){save();render();if(window.rocTrack)rocTrack('roc_my_pc',sel('gpu').value,'')});
q.addEventListener('input',render);
var db=document.getElementById('gp-detect'),dm=document.getElementById('gp-detect-msg');
db.addEventListener('click',function(){if(!D){dm.textContent='One moment, the games are still loading.';return}db.disabled=true;dm.textContent='Reading your graphics card…';
var go=function(){window.rocDetectGpu().then(function(d){db.disabled=false;if(!d||d.score===null){dm.textContent=d&&d.name?'Your browser shows "'+d.name+'", which we cannot rate yet: please choose the closest card.':'Your browser does not share the graphics card: please choose it.';return}
pick(sel('gpu'),'',d.score,D.gpu);dm.textContent='Found: '+d.name+(d.laptop?' (laptop versions are often a little slower)':'')+'. Now choose your processor and RAM.';box.dispatchEvent(new Event('change'))})};
if(window.rocDetectGpu)go();else{var sc=document.createElement('script');sc.src='/roc-detect.js?v=153';sc.onload=go;sc.onerror=function(){db.disabled=false;dm.textContent='Could not load the detector. Please choose your card.'};document.head.appendChild(sc)}});
})();</script>
JS;
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}
