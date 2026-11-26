<?php
/**
 * Run On Console — Rate my PC (Deploy 191): /can-i-run/rate-my-pc/
 *
 * Owner, 2026-11-26 (competitor gap): PCGameBenchmark's "Rate My PC" earns most of its links because people share their
 * result. Ours: the player's graphics card, processor and RAM get a ROC score from 0 to 100 = how well the PC runs the
 * PC games on Run On Console (official Steam requirements, the same rules as the checker): each game counts 100 on high
 * settings, 75 on medium to high, 45 on low and 0 below the minimum, averaged over all games. Then:
 *   - a gauge, the tier (Low-end ... Elite) and the games per settings level;
 *   - the PC next to popular PCs (bar chart);
 *   - a picture to save or share (drawn in the browser), a link that opens the same PC, and an embed badge for blogs and
 *     forums (api/v1/pc-badge.php), so the result travels with a link back to the site.
 * No FPS and no invented benchmark: a settings level from the requirements.
 */

const ROC_GP_RATE = '/can-i-run/rate-my-pc/';

function rocGpRateMyPc(string $root, array $games, int $updated, string $headTop, string $bodyToMain, string $mainOpen, string $afterMain): void {
    $n = count($games);
    $url = ROC_PUBLIC_URL . ROC_GP_RATE;
    $q = function ($k) { return isset($_GET[$k]) ? mb_substr(trim((string)$_GET[$k]), 0, 80) : ''; };
    $all = ['gpu' => rocGpRefsAll('gpu'), 'cpu' => rocGpRefsAll('cpu')];
    $gp = isset($all['gpu'][$q('gpu')]) ? $q('gpu') : 'GeForce GTX 1650';
    $cp = isset($all['cpu'][$q('cpu')]) ? $q('cpu') : 'Intel Core i5-8400';
    $rm = in_array((int)$q('ram'), [4, 6, 8, 12, 16, 24, 32, 64], true) ? (int)$q('ram') : 16;
    $shared = $q('gpu') !== '';
    $crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/'], ['Rate my PC', ROC_GP_RATE]];
    $title = 'Rate My PC: Free Gaming PC Score (' . gmdate('Y') . ')';
    $desc = 'Rate your gaming PC for free: pick your graphics card, CPU and RAM and get a score out of 100 from ' . number_format($n) . ' PC games and their Steam requirements.';
    if (mb_strlen($desc) > 155) $desc = 'Rate your gaming PC for free: pick your graphics card, CPU and RAM and get a score out of 100 from the Steam requirements of thousands of games.';
    $faqs = [
        ['How is my PC rated?', 'Your graphics card, processor and RAM are checked against the official Steam minimum and recommended requirements of ' . number_format($n) . ' PC games, with the same rules as our game pages. Most games are light and run on almost any PC, so the score comes from the demanding ones (games that recommend an RTX 2060 or GTX 1080 class card or faster): each counts 100 points on high settings, 75 on medium to high, 45 on low and 0 below the minimum. That average makes 85 of the 100 points; the last 15 are for a PC faster than even the heaviest games ask for.'],
        ['What is a good PC score?', '90 or more is an elite gaming PC; 75 to 89 is high-end and runs nearly every demanding game on high settings; 60 to 74 is strong; 45 to 59 is mid-range. Below 45, many demanding games will be on low settings or below the minimum, while lighter games still run.'],
        ['Is the score a benchmark or FPS?', 'No. It is a settings level from the official requirements, not a measured frame rate. Players\' real FPS reports are on each game page.'],
        ['How can I raise my PC score?', 'The PC upgrade planner shows which single upgrade adds the most games on high settings, and the bottleneck calculator shows which part holds your games back.'],
        ['Can I share my score?', 'Yes: save the picture, share the link (it opens your PC), or put the badge on your blog or forum signature.'],
    ];
    $ld = [rocGpCrumbs($crumbs),
        ['@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => 'Rate my PC (Run On Console)', 'url' => $url, 'description' => $desc,
         'applicationCategory' => 'UtilitiesApplication', 'operatingSystem' => 'Any (web browser)', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
         'publisher' => ['@type' => 'Organization', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/']],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
            return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]]; }, $faqs)]];
    // A shared link (?gpu=...) is the same page: canonical without the query, not indexed on its own.
    $h = rocGpHead($title, $desc, $url, $ld, $shared ? 'noindex, follow' : 'index, follow, max-image-preview:large', '', 'Rate my PC on Run On Console');
    $h .= '<style>'
        . '.rm-top{display:grid;gap:1.2rem;align-items:center}@media(min-width:760px){.rm-top{grid-template-columns:260px 1fr}}'
        . '.rm-gauge{position:relative;width:240px;margin:0 auto}.rm-gauge svg{display:block;width:100%}.rm-score{position:absolute;left:0;right:0;top:46%;text-align:center}'
        . '.rm-score b{display:block;font:800 3.2rem/1 Outfit,Inter,sans-serif;color:#064e3b}.rm-score span{font-size:.8rem;color:#64748b}'
        . '.rm-tier{font:800 1.5rem Outfit,Inter,sans-serif;color:#047857}.rm-stack{display:flex;height:1.4rem;border-radius:.5rem;overflow:hidden;background:#e2e8f0;margin:.6rem 0}.rm-stack i{display:block;height:100%}'
        . '.rm-rows{display:grid;gap:.4rem}.rm-row{display:grid;grid-template-columns:minmax(8rem,15rem) 1fr 2.6rem;gap:.6rem;align-items:center;font-size:.85rem}.rm-row .tr{height:1.1rem;background:#f1f5f9;border-radius:.4rem;overflow:hidden}'
        . '.rm-row .tr i{display:block;height:100%;background:#94a3b8;border-radius:.4rem}.rm-row.me{font-weight:800}.rm-row.me .tr i{background:#10b981}'
        . '.rm-share{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.8rem}.rm-share button,.rm-share a{border:0;cursor:pointer;border-radius:.8rem;padding:.6rem 1rem;font-weight:800;font-size:.88rem;background:#047857;color:#fff;text-decoration:none}'
        . '.rm-share .s{background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7}.rm-embed textarea{width:100%;min-height:5.5rem;font:12px/1.4 ui-monospace,Consolas,monospace;border:1px solid #cbd5e1;border-radius:.7rem;padding:.6rem;margin-top:.5rem}'
        . 'html.roc-dark .rm-score b{color:#a7f3d0}html.roc-dark .rm-tier{color:#6ee7b7}html.roc-dark .rm-stack,html.roc-dark .rm-row .tr{background:#1e293b}html.roc-dark .rm-embed textarea{background:#0b1220;color:#e2e8f0;border-color:#1e293b}'
        . '@media(max-width:560px){.rm-row{grid-template-columns:6.5rem 1fr 2.4rem;font-size:.78rem}}'
        . '</style>';

    $m  = $mainOpen . '<div class="max-w-5xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
    $m .= '<header class="gp-hero" style="--hero-img:url(/images/battlestation_pc.jpg)"><div><h1>Rate my PC</h1>'
        . '<p>How good is your gaming PC? Pick your graphics card, processor and RAM and get a score out of 100 from ' . number_format($n) . ' PC games and their official Steam requirements. '
        . 'Share it, compare it with popular PCs, and see what to upgrade.</p></div></header>';
    $m .= '<section class="gp-card"><h2>Your PC</h2><div class="gp-check" style="margin-top:.6rem" id="rm-box">'
        . '<label>Graphics card<select data-k="gpu">' . rocPartsOptions('gpu', $gp) . '</select></label>'
        . '<label>Processor<select data-k="cpu">' . rocPartsOptions('cpu', $cp) . '</select></label>'
        . '<label>Memory (RAM)<select data-k="ram">' . implode('', array_map(function ($r) use ($rm) { return '<option value="' . $r . '"' . ($r === $rm ? ' selected' : '') . '>' . $r . ' GB</option>'; }, [4, 6, 8, 12, 16, 24, 32, 64])) . '</select></label>'
        . '</div><p class="gp-src" style="margin-top:.6rem">Not sure what is inside your PC? <a href="/guides/">See our guide to checking your PC specs</a>. Laptop graphics are listed separately.</p></section>';
    $m .= '<section class="gp-card" aria-live="polite"><div class="rm-top"><div class="rm-gauge"><svg viewBox="0 0 200 120" aria-hidden="true">'
        . '<path d="M20 110 A80 80 0 0 1 180 110" fill="none" stroke="#e2e8f0" stroke-width="18" stroke-linecap="round"/>'
        . '<path id="rm-arc" d="M20 110 A80 80 0 0 1 180 110" fill="none" stroke="#10b981" stroke-width="18" stroke-linecap="round" stroke-dasharray="0 252"/></svg>'
        . '<div class="rm-score"><b id="rm-num">–</b><span>out of 100</span></div></div>'
        . '<div><p class="rm-tier" id="rm-tier">Loading the games…</p><p id="rm-line"></p><div class="rm-stack" id="rm-stack"></div>'
        . '<p class="gp-src">Green: high settings · light green: medium to high · amber: low · red: below the minimum.</p></div></div>'
        . '<div class="rm-share"><button type="button" id="rm-png">Save the picture</button><button type="button" class="s" id="rm-sharebtn">Share</button><button type="button" class="s" id="rm-copy">Copy link</button>'
        . '<a class="s" href="' . ROC_GP_UPGRADE . '">Raise my score: upgrade planner</a></div><p class="gp-src" id="rm-msg" role="status"></p></section>';
    $m .= '<section class="gp-card"><h2>Your PC next to popular PCs</h2><div class="rm-rows" id="rm-cmp" style="margin-top:.6rem"></div></section>';
    $m .= '<section class="gp-card rm-embed"><h2>Put your score on your blog or forum</h2><p>Copy this code into your site, signature or profile. It shows your score and links to your PC on Run On Console.</p><textarea id="rm-code" readonly></textarea>'
        . '<p style="margin-top:.6rem"><img id="rm-badge" alt="PC score badge" width="240" height="64"></p></section>';
    $m .= rocAdUnit('list');
    $m .= '<section class="gp-card gp-faq"><h2>Rate my PC: questions</h2>';
    foreach ($faqs as $i => $f) $m .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . rocH($f[0]) . '</summary><div class="a">' . rocH($f[1]) . '</div></details>';
    $m .= '</section>';
    $m .= '<p class="gp-src">More: <a href="' . ROC_GP_UPGRADE . '">PC upgrade planner</a> · <a href="' . ROC_GP_BOTTLENECK . '">Bottleneck calculator</a> · <a href="/can-i-run/my-pc/">Which games can my PC run?</a> · <a href="/how-we-check/">How we check games</a></p>';
    $m .= rocGpCardNav(ROC_GP_RATE) . '</div>';
    $m .= '<script>window.ROC_RM={gpu:' . json_encode($all['gpu'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ',cpu:' . json_encode($all['cpu'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ',shared:' . ($shared ? 'true' : 'false') . '};</script>';
    $m .= <<<'JS'
<script>(function(){
var box=document.getElementById('rm-box'),R=window.ROC_RM,D=null,last=null,BASE='https://runonconsole.com/can-i-run/rate-my-pc/';
function sel(k){return box.querySelector('[data-k="'+k+'"]')}
function $(id){return document.getElementById(id)}
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function pct(m,r,nr,h){var w=[.5,.3,.2],s=0,t=0,below=false,miss=0;for(var i=0;i<3;i++){var need=r[i]||m[i];if(need>0){s+=w[i]*Math.min(1,h[i]/need);t+=w[i];if(h[i]<need)miss++}if(m[i]>0&&h[i]<m[i])below=true}
var p=Math.round(100*s/(t||1));if(below)p=Math.min(p,40);var room=!nr||(h[0]>=m[0]+2&&h[1]>=m[1]+2&&h[2]>=2*m[2]);if(miss||!room)p=Math.min(p,89);return Math.max(5,p)}
/* The score: 85% from the demanding games (they recommend an RTX 2060 / GTX 1080 class card or faster; most of the list
   is light and every PC runs it), 15% for headroom above the heaviest requirements. All games still show in the bar. */
function rate(h){var c={h:0,m:0,l:0,x:0},ds=0,dn=0;D.g.forEach(function(g){var p=pct(g[3],g[4],g[5],h);c[p>=90?'h':p>=70?'m':p>40?'l':'x']++;
if((g[4][0]||g[3][0])>=6){ds+=p>=90?100:p>=70?75:p>40?45:0;dn++}});var n=D.g.length;c.n=n;c.dn=dn;
var head=Math.max(0,Math.min(1,((h[0]+h[1])/2-7.5)/2.5));c.score=Math.round(0.85*(dn?ds/dn:0)+15*head);return c}
function tier(s){return s>=90?'Elite':s>=75?'High-end':s>=60?'Strong':s>=45?'Mid-range':s>=25?'Entry level':'Low-end'}
function short(n){return n.replace(/^(GeForce|Radeon|Intel Core|AMD Ryzen|AMD|Intel) /,function(m,b){return b==='AMD Ryzen'?'Ryzen ':''})}
function link(){return BASE+'?gpu='+encodeURIComponent(sel('gpu').value)+'&cpu='+encodeURIComponent(sel('cpu').value)+'&ram='+sel('ram').value}
var POP=[['GeForce GTX 1650','Intel Core i5-8400',8],['GeForce GTX 1060 6GB','AMD Ryzen 5 2600',16],['GeForce RTX 3060','AMD Ryzen 5 5600',16],['GeForce RTX 4060','Intel Core i5-12400',16],['GeForce RTX 4070','AMD Ryzen 7 7800X3D',32],['Intel UHD / Iris Xe (integrated)','Intel Core i5-8400',8]];
function render(){if(!D)return;var h=[R.gpu[sel('gpu').value],R.cpu[sel('cpu').value],+sel('ram').value],c=rate(h);last=c;
$('rm-num').textContent=c.score;$('rm-arc').setAttribute('stroke-dasharray',(2.51*c.score).toFixed(1)+' 252');$('rm-arc').setAttribute('stroke',c.score>=75?'#10b981':c.score>=45?'#84cc16':c.score>=30?'#f59e0b':'#ef4444');
$('rm-tier').textContent=tier(c.score)+' gaming PC';$('rm-line').innerHTML='Runs <b>'+c.h.toLocaleString()+'</b> of '+c.n.toLocaleString()+' PC games on high settings, '+c.m.toLocaleString()+' on medium to high, '+c.l.toLocaleString()+' on low and '+c.x.toLocaleString()+' below the minimum.';
var f=function(k,col){return '<i style="width:'+(100*c[k]/c.n)+'%;background:'+col+'"></i>'};$('rm-stack').innerHTML=f('h','#10b981')+f('m','#84cc16')+f('l','#f59e0b')+f('x','#ef4444');
var rows=POP.filter(function(p){return R.gpu[p[0]]&&R.cpu[p[1]]}).map(function(p){return [short(p[0])+' + '+short(p[1])+' + '+p[2]+' GB',rate([R.gpu[p[0]],R.cpu[p[1]],p[2]]).score,0]});
rows.push(['Your PC: '+short(sel('gpu').value)+' + '+short(sel('cpu').value)+' + '+sel('ram').value+' GB',c.score,1]);rows.sort(function(a,b){return b[1]-a[1]});
$('rm-cmp').innerHTML=rows.map(function(r){return '<div class="rm-row'+(r[2]?' me':'')+'"><span>'+esc(r[0])+'</span><span class="tr"><i style="width:'+r[1]+'%"></i></span><b>'+r[1]+'</b></div>'}).join('');
var b='https://runonconsole.com/api/v1/pc-badge.php?s='+c.score+'&t='+encodeURIComponent(tier(c.score));$('rm-badge').src=b;
$('rm-code').value='<a href="'+link()+'"><img src="'+b+'" alt="My gaming PC scores '+c.score+'/100 on Run On Console" width="240" height="64"></a>';}
function draw(){var cv=document.createElement('canvas');cv.width=1200;cv.height=630;var x=cv.getContext('2d'),c=last;var g=x.createLinearGradient(0,0,1200,630);g.addColorStop(0,'#064e3b');g.addColorStop(1,'#0d9488');x.fillStyle=g;x.fillRect(0,0,1200,630);
x.fillStyle='#a7f3d0';x.font='bold 34px Outfit, Arial, sans-serif';x.fillText('RATE MY PC · RUN ON CONSOLE',70,95);
x.fillStyle='#fff';x.font='bold 190px Outfit, Arial, sans-serif';x.fillText(String(c.score),70,300);x.font='bold 46px Outfit, Arial, sans-serif';x.fillText('/ 100 · '+tier(c.score)+' gaming PC',70+x.measureText(String(c.score)).width*0+0,370);
x.font='32px Inter, Arial, sans-serif';x.fillStyle='#ecfdf5';[sel('gpu').value,sel('cpu').value,sel('ram').value+' GB RAM'].forEach(function(t,i){x.fillText(t,70,440+i*44)});
var w=480,y=170,cols=[['h','#34d399'],['m','#bef264'],['l','#fbbf24'],['x','#f87171']],xx=660;x.fillStyle='rgba(255,255,255,.15)';x.fillRect(660,y,w,40);cols.forEach(function(k){var ww=w*c[k[0]]/c.n;x.fillStyle=k[1];x.fillRect(xx,y,ww,40);xx+=ww});
x.fillStyle='#fff';x.font='30px Inter, Arial, sans-serif';x.fillText(c.h.toLocaleString()+' of '+c.n.toLocaleString()+' PC games',660,260);x.fillText('on high settings',660,300);
x.fillStyle='#a7f3d0';x.font='bold 30px Inter, Arial, sans-serif';x.fillText('Rate yours: runonconsole.com/can-i-run/rate-my-pc',70,590);return cv}
$('rm-png').onclick=function(){if(!last)return;draw().toBlob(function(b){var a=document.createElement('a');a.href=URL.createObjectURL(b);a.download='my-pc-score-'+last.score+'.png';a.click();$('rm-msg').textContent='Picture saved.';if(window.rocTrack)rocTrack('roc_rate_pc','png',String(last.score))})};
$('rm-sharebtn').onclick=function(){if(!last)return;var text='My gaming PC scores '+last.score+'/100 ('+tier(last.score)+') on Run On Console. Rate yours:';
draw().toBlob(function(b){var file=null;try{file=new File([b],'my-pc-score.png',{type:'image/png'})}catch(e){}
if(navigator.canShare&&file&&navigator.canShare({files:[file]}))navigator.share({files:[file],text:text+' '+link()}).catch(function(){});
else if(navigator.share)navigator.share({title:'Rate my PC',text:text,url:link()}).catch(function(){});
else{try{navigator.clipboard.writeText(text+' '+link());$('rm-msg').textContent='Link copied: paste it anywhere.'}catch(e){}}
if(window.rocTrack)rocTrack('roc_rate_pc','share',String(last.score))})};
$('rm-copy').onclick=function(){try{navigator.clipboard.writeText(link());$('rm-msg').textContent='Link copied.'}catch(e){$('rm-msg').textContent=link()}};
function pick(s,name,score,refs){var o=[].filter.call(s.options,function(x){return x.value===name})[0];if(!o&&score){var best=null;[].forEach.call(s.options,function(x){var v=refs[x.value];if(v<=score&&(!best||v>refs[best.value]))best=x});o=best}if(o)s.value=o.value}
fetch('/can-i-run/my-pc/?data=1').then(function(r){return r.json()}).then(function(d){D=d;if(!R.shared){var p=null;try{p=JSON.parse(localStorage.getItem('roc_pc')||'null')}catch(e){}
if(p){pick(sel('gpu'),p.gpu,p.gs,R.gpu);pick(sel('cpu'),p.cpu,p.cs,R.cpu);if(p.ram)sel('ram').value=String(p.ram)}}render()}).catch(function(){$('rm-tier').textContent='The games could not be loaded. Please refresh the page.'});
box.addEventListener('change',function(){render();try{localStorage.setItem('roc_pc',JSON.stringify({gpu:sel('gpu').value,cpu:sel('cpu').value,ram:+sel('ram').value,gs:R.gpu[sel('gpu').value],cs:R.cpu[sel('cpu').value]}))}catch(e){}});
})();</script>
JS;
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}
