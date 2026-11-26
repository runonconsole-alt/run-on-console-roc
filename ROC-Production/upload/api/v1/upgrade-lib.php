<?php
/**
 * Run On Console — PC upgrade planner (Deploy 190): /can-i-run/upgrade-planner/
 *
 * Owner, 2026-11-25: "PC Upgrade Planner: what should I upgrade first?". From the player's graphics card, processor and
 * RAM, across every PC game on Run On Console (official Steam requirements, the same rules as the checker):
 *   - how many games run now on high, medium to high, low settings or not at all (stacked bar);
 *   - every upgrade still sold new (graphics cards, processors, 16/32 GB RAM), each alone: games gained on high settings;
 *   - the best three-step plan (best upgrade, then the best next one on top of it), as a staircase chart;
 *   - for each upgrade, the matching part from our product guides (its page has the Amazon button), and the popular games
 *     the first step unlocks.
 * No prices (they change; the product page links to the current price) and no invented FPS.
 * Data: /can-i-run/my-pc/?data=1 (mypc-lib.php), loaded by the page. game-render.php calls rocGpUpgradePlanner().
 */

const ROC_GP_UPGRADE = '/can-i-run/upgrade-planner/';

/** Graphics cards, processors and RAM kits from our product guides with their part score: [cat, title, url, score]. */
function rocUpProducts(?PDO $pdo): array {
    if (!$pdo) return [];
    $out = [];
    try {
        $st = $pdo->query("SELECT slug, title, category_slug FROM products WHERE status = 'published' AND category_slug IN ('gpu', 'cpu', 'ram')");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $t = (string)$r['title']; $c = (string)$r['category_slug'];
            $s = $c === 'gpu' ? rocAgentGpuScore($t) : ($c === 'cpu' ? rocAgentCpuScore($t) : (preg_match('/(\d{1,3})\s*GB/i', $t, $m) ? (float)$m[1] : null));
            if ($s !== null) $out[] = [$c, $t, '/products/' . $r['slug'] . '/', (float)$s];
        }
    } catch (\Throwable $e) {}
    return $out;
}

function rocGpUpgradePlanner(string $root, ?PDO $pdo, array $games, int $updated, string $headTop, string $bodyToMain, string $mainOpen, string $afterMain): void {
    $n = count($games);
    $url = ROC_PUBLIC_URL . ROC_GP_UPGRADE;
    $gpus = rocGpRefs('gpu'); $cpus = rocGpRefs('cpu');
    $crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/'], ['PC upgrade planner', ROC_GP_UPGRADE]];
    $title = 'PC Upgrade Planner: What Should I Upgrade First? (' . gmdate('Y') . ')';
    $desc = 'Choose your graphics card, CPU and RAM and see which upgrade unlocks the most of ' . number_format($n) . ' PC games on high settings, step by step.';
    if (mb_strlen($desc) > 155) $desc = 'Choose your graphics card, CPU and RAM and see which upgrade unlocks the most PC games on high settings, step by step.';
    $faqs = [
        ['What should I upgrade first in my gaming PC?', 'The part that holds back the most of the games you want to play. The planner checks your graphics card, processor and RAM against the official Steam requirements of ' . number_format($n) . ' PC games and counts, for each upgrade on its own, how many more games reach high settings. In most gaming PCs that is the graphics card.'],
        ['Is the planner based on FPS benchmarks?', 'No. It uses the minimum and recommended requirements each game lists on Steam, with the same rules as our game pages. It shows settings levels (high, medium to high, low), not frame rates; real frame rates from players are on the game pages.'],
        ['Will a new processor fit my motherboard?', 'Not always. A processor from another brand or a newer generation often needs a new motherboard, and some new platforms need DDR5 RAM. Check the socket of your motherboard before you buy.'],
        ['Is 32 GB of RAM worth it for gaming?', 'Only when your games ask for more than 16 GB. The planner shows how many games 32 GB adds on high settings for your PC; for most PCs a faster graphics card adds far more.'],
        ['Can I upgrade a gaming laptop?', 'Usually only the RAM and the storage. The graphics card and processor of a laptop are soldered, so the planner\'s graphics card and processor steps are for desktop PCs.'],
    ];
    $ld = [rocGpCrumbs($crumbs),
        ['@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => 'PC upgrade planner (Run On Console)', 'url' => $url, 'description' => $desc,
         'applicationCategory' => 'UtilitiesApplication', 'operatingSystem' => 'Any (web browser)', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
         'publisher' => ['@type' => 'Organization', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/']],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
            return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]]; }, $faqs)]];
    $h = rocGpHead($title, $desc, $url, $ld, 'index, follow, max-image-preview:large', '', 'PC upgrade planner on Run On Console');
    $h .= '<style>'
        . '.up-stack{display:flex;height:1.5rem;border-radius:.5rem;overflow:hidden;background:#e2e8f0}.up-stack i{display:block;height:100%}.up-stack .h{background:#10b981}.up-stack .m{background:#84cc16}.up-stack .l{background:#f59e0b}.up-stack .x{background:#ef4444}'
        . '.up-legend{display:flex;flex-wrap:wrap;gap:.4rem .9rem;font-size:.78rem;color:#475569;margin:.4rem 0 .8rem}.up-legend i{display:inline-block;width:.7rem;height:.7rem;border-radius:.2rem;margin-right:.3rem;vertical-align:-1px}'
        . '.up-now{font:800 1.3rem Outfit,Inter,sans-serif;color:#064e3b;margin:.2rem 0 .5rem}'
        . '.up-steps{display:grid;gap:.8rem}.up-step{display:grid;grid-template-columns:7.5rem 1fr;gap:.7rem;align-items:center}.up-step b{font:800 .9rem Outfit,Inter,sans-serif;color:#0f172a}.up-step small{display:block;color:#64748b;font-size:.75rem}'
        . '.up-opts{display:grid;gap:.6rem}.up-opt{border:1px solid #e2e8f0;border-radius:1rem;padding:.8rem .9rem;display:grid;gap:.45rem}.up-opt .t{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.4rem;align-items:baseline}'
        . '.up-opt .t b{color:#0f172a}.up-gain{font-weight:800;color:#047857}.up-opt .k{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748b}.up-opt a.p{font-size:.82rem;font-weight:700;color:#047857}'
        . '.up-opt.best{border-color:#10b981;box-shadow:0 0 0 2px rgba(16,185,129,.25)}.up-badge{background:#10b981;color:#fff;border-radius:99px;padding:.1rem .55rem;font-size:.7rem;font-weight:800;margin-left:.4rem}'
        . 'html.roc-dark .up-now{color:#a7f3d0}html.roc-dark .up-step b,html.roc-dark .up-opt .t b{color:#e2e8f0}html.roc-dark .up-opt{border-color:#1e293b}html.roc-dark .up-stack{background:#1e293b}html.roc-dark .up-legend{color:#94a3b8}'
        . '@media(max-width:560px){.up-step{grid-template-columns:5.6rem 1fr}}'
        . '</style>';

    $opt = function (array $refs, string $pick) { $o = ''; foreach ($refs as $nm => $s) $o .= '<option value="' . rocH($nm) . '"' . ($nm === $pick ? ' selected' : '') . '>' . rocH($nm) . '</option>'; return $o; };
    $m  = $mainOpen . '<div class="max-w-5xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
    $m .= '<header class="gp-hero" style="--hero-img:url(/images/battlestation_pc.jpg)"><div><h1>PC upgrade planner</h1>'
        . '<p>What should you upgrade first? Choose your parts and see, across ' . number_format($n) . ' PC games and their official Steam requirements, which upgrade unlocks the most games on high settings, '
        . 'and the best order to do it in. Settings levels from the requirements, not invented FPS.</p></div></header>';
    $m .= '<section class="gp-card"><h2>Your PC now</h2><div class="gp-check" style="margin-top:.6rem" id="up-box">'
        . '<label>Graphics card<select data-k="gpu">' . rocPartsOptions('gpu', 'GeForce GTX 1650') . '</select></label>'
        . '<label>Processor<select data-k="cpu">' . rocPartsOptions('cpu', 'Intel Core i5-8400') . '</select></label>'
        . '<label>Memory (RAM)<select data-k="ram">' . implode('', array_map(function ($r) { return '<option value="' . $r . '"' . ($r === 16 ? ' selected' : '') . '>' . $r . ' GB</option>'; }, [4, 6, 8, 12, 16, 24, 32, 64])) . '</select></label>'
        . '</div><p class="gp-src" style="margin-top:.6rem">Your saved PC from the game pages is filled in. <a href="/can-i-run/my-pc/">Which games can my PC run?</a></p>'
        . '<div id="up-now" aria-live="polite" style="margin-top:1rem"><p class="gp-src">Loading the games…</p></div>'
        . '<div class="up-legend"><span><i style="background:#10b981"></i>High settings</span><span><i style="background:#84cc16"></i>Medium to high</span><span><i style="background:#f59e0b"></i>Low settings</span><span><i style="background:#ef4444"></i>Below the minimum</span></div></section>';
    $m .= '<section class="gp-card"><h2>Your upgrade plan</h2><p>One part at a time: the part that unlocks the most games first, and the smallest upgrade of it that gets most of the gain. Each bar is all the games on Run On Console.</p><div class="up-steps" id="up-plan" style="margin-top:.8rem"></div></section>';
    $m .= '<section class="gp-card"><h2>Every upgrade, one at a time</h2><p class="gp-src">Parts still sold new, each with your other parts unchanged. The link opens the matching part in our guides (current price and the Amazon button are there).</p><div class="up-opts" id="up-opts" style="margin-top:.8rem"></div></section>';
    $m .= '<section class="gp-card"><h2>Popular games the first step unlocks on high settings</h2><ul class="gp-list" id="up-games" style="margin-top:.6rem"></ul></section>';
    $m .= rocAdUnit('list');
    $m .= '<section class="gp-card gp-faq"><h2>Upgrading a gaming PC: questions</h2>';
    foreach ($faqs as $i => $f) $m .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . rocH($f[0]) . '</summary><div class="a">' . rocH($f[1]) . '</div></details>';
    $m .= '</section>';
    $m .= '<p class="gp-src">More: <a href="' . ROC_GP_BOTTLENECK . '">Bottleneck calculator</a> · <a href="/can-i-run/my-pc/">Which games can my PC run?</a> · <a href="' . (defined('ROC_GP_VS_HUB') ? ROC_GP_VS_HUB : '/can-i-run/gpu-compare/') . '">GPU comparisons</a> · <a href="/category/gpu/">Graphics cards</a> · <a href="/category/cpu/">Processors</a> · <a href="/how-we-check/">How we check games</a></p>';
    $m .= rocGpCardNav(ROC_GP_UPGRADE) . '</div>';
    $m .= '<script>window.ROC_UP_P=' . json_encode(rocUpProducts($pdo), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';
    $m .= <<<'JS'
<script>(function(){
var box=document.getElementById('up-box'),nowEl=document.getElementById('up-now'),planEl=document.getElementById('up-plan'),optEl=document.getElementById('up-opts'),gEl=document.getElementById('up-games'),D=null,P=window.ROC_UP_P||[];
function sel(k){return box.querySelector('[data-k="'+k+'"]')}
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
/* The checker's rule (bottleneck-lib.php, agent-lib.php). */
function pct(m,r,nr,h){var w=[.5,.3,.2],s=0,t=0,below=false,miss=0;for(var i=0;i<3;i++){var need=r[i]||m[i];if(need>0){s+=w[i]*Math.min(1,h[i]/need);t+=w[i];if(h[i]<need)miss++}if(m[i]>0&&h[i]<m[i])below=true}
var p=Math.round(100*s/(t||1));if(below)p=Math.min(p,40);var room=!nr||(h[0]>=m[0]+2&&h[1]>=m[1]+2&&h[2]>=2*m[2]);if(miss||!room)p=Math.min(p,89);return Math.max(5,p)}
function lvl(p){return p>=90?'h':p>=70?'m':p>40?'l':'x'}
function dist(h){var c={h:0,m:0,l:0,x:0},L=[];D.g.forEach(function(g){var v=lvl(pct(g[3],g[4],g[5],h));c[v]++;L.push(v)});c.L=L;return c}
function stack(c){var n=D.g.length,f=function(k){return '<i class="'+k+'" style="width:'+(100*c[k]/n)+'%" title="'+c[k]+'"></i>'};return '<div class="up-stack" role="img" aria-label="'+c.h+' high, '+c.m+' medium to high, '+c.l+' low, '+c.x+' below the minimum">'+f('h')+f('m')+f('l')+f('x')+'</div>'}
function have(){return[D.gpu[sel('gpu').value],D.cpu[sel('cpu').value],+sel('ram').value]}
/* Upgrades still sold new above the current part: the next few speed steps of each kind. */
function options(h){var o=[],seen={};
 [['gpu',0,5,'Graphics card'],['cpu',1,4,'Processor']].forEach(function(k){var refs=D[k[0]],by={};Object.keys(refs).forEach(function(nm){if(D.shops.indexOf(nm)>-1&&refs[nm]>h[k[1]])(by[refs[nm]]=by[refs[nm]]||[]).push(nm)});
  Object.keys(by).map(Number).sort(function(a,b){return a-b}).slice(0,k[2]).forEach(function(s){var nh=h.slice();nh[k[1]]=s;o.push({kind:k[0],label:k[3],name:by[s].join(' / '),score:s,h:nh})})});
 [16,32].forEach(function(r){if(r>h[2]){var nh=h.slice();nh[2]=r;o.push({kind:'ram',label:'Memory',name:r+' GB of RAM',score:r,h:nh})}});return o}
function product(o){var best=null;P.forEach(function(p){if(p[0]===o.kind&&p[3]>=o.score&&(!best||p[3]<best[3]))best=p});return best}
function render(){if(!D)return;var h=have(),now=dist(h),n=D.g.length;
 nowEl.innerHTML='<p class="up-now">'+now.h.toLocaleString()+' of '+n.toLocaleString()+' games on high settings now</p>'+stack(now)+'<p class="gp-src" style="margin-top:.4rem">'+now.m.toLocaleString()+' on medium to high, '+now.l.toLocaleString()+' on low, '+now.x.toLocaleString()+' below the minimum.</p>';
 var opts=options(h).map(function(o){o.d=dist(o.h);o.gain=o.d.h-now.h;o.play=(now.x-o.d.x);return o}).filter(function(o){return o.gain>0||o.play>0}).sort(function(a,b){return b.gain-a.gain||b.play-a.play});
 /* the plan: best step, then the best next step on top of it */
 var steps=[{t:'Now',s:'your PC',d:now,h:h}],cur=h,used={};
 /* Each step: the kind of part that adds the most games, then the smallest upgrade of that kind that gets 80% of its gain
    (an RTX 4070 always adds the most games; the planner looks for the step that is enough). */
 for(var i=0;i<3;i++){var base=steps[steps.length-1].d.h,all=[],top={};options(cur).forEach(function(o){if(used[o.kind])return;var d=dist(o.h),g=d.h-base;if(g>0){all.push({o:o,d:d,g:g});top[o.kind]=Math.max(top[o.kind]||0,g)}});
  if(!all.length)break;var kind=Object.keys(top).sort(function(a,b){return top[b]-top[a]})[0];
  var best=all.filter(function(x){return x.o.kind===kind&&x.g>=0.8*top[kind]}).sort(function(a,b){return a.o.score-b.o.score})[0];
  used[kind]=1;cur=best.o.h;steps.push({t:'Step '+(i+1),s:best.o.name,d:best.d,g:best.g,o:best.o})}
 planEl.innerHTML=steps.map(function(s){var p=s.o?product(s.o):null;return '<div class="up-step"><div><b>'+s.t+'</b><small>'+esc(s.s)+'</small></div><div>'+stack(s.d)+'<small class="gp-src">'+s.d.h.toLocaleString()+' games on high'+(s.g?' (<b class="up-gain">+'+s.g.toLocaleString()+'</b>)':'')+(p?' · <a href="'+p[2]+'">'+esc(p[1])+'</a>':'')+'</small></div></div>'}).join('')
  +(steps.length===1?'<p>No upgrade still sold new adds games on high settings: your PC is already strong for this list.</p>':'');
 optEl.innerHTML=opts.length?opts.slice(0,10).map(function(o,i){var p=product(o);return '<div class="up-opt'+(i===0?' best':'')+'"><div class="t"><span><span class="k">'+o.label+'</span><br><b>'+esc(o.name)+'</b>'+(i===0?'<span class="up-badge">Best first upgrade</span>':'')+'</span><span class="up-gain">+'+o.gain.toLocaleString()+' games on high'+(o.play>0?', +'+o.play.toLocaleString()+' playable':'')+'</span></div>'+stack(o.d)
  +(p?'<a class="p" href="'+p[2]+'">See '+esc(p[1])+' in our guides &rarr;</a>':'')+(o.kind==='cpu'?'<small class="gp-src">A new processor may need a new motherboard (and DDR5 RAM on new platforms).</small>':'')+'</div>'}).join(''):'<p>No single upgrade adds games: your PC already runs this list well.</p>';
 var first=steps[1],list=[];if(first){var A=now.L,B=first.d.L;for(var j=0;j<D.g.length&&list.length<18;j++)if(A[j]!=='h'&&B[j]==='h')list.push(D.g[j])}
 gEl.innerHTML=list.length?list.map(function(g){return '<li><a href="/can-i-run/'+g[0]+'/">'+esc(g[1])+'</a></li>'}).join(''):'<li>Nothing new: follow the plan above.</li>';}
function pick(s,name,score,refs){var o=[].filter.call(s.options,function(x){return x.value===name})[0];if(!o&&score){var best=null;[].forEach.call(s.options,function(x){var v=refs[x.value];if(v<=score&&(!best||v>refs[best.value]))best=x});o=best}if(o)s.value=o.value}
fetch('/can-i-run/my-pc/?data=1').then(function(r){return r.json()}).then(function(d){D=d;var p=null;try{p=JSON.parse(localStorage.getItem('roc_pc')||'null')}catch(e){}
if(p){pick(sel('gpu'),p.gpu,p.gs,D.gpu);pick(sel('cpu'),p.cpu,p.cs,D.cpu);if(p.ram)sel('ram').value=String(p.ram)}render()}).catch(function(){nowEl.innerHTML='<p class="gp-src">The games could not be loaded. Please refresh the page.</p>'});
box.addEventListener('change',function(){render();if(window.rocTrack)rocTrack('roc_upgrade_planner',sel('gpu').value,sel('cpu').value)});
})();</script>
JS;
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}
