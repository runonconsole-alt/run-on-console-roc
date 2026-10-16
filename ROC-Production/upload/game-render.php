<?php
/**
 * Run On Console — "Can I run it?" pages, one per PC game (Deploy 65).
 *
 *   /can-i-run/                  every game: search, the most popular, then A to Z   (game-render.php)
 *   /can-i-run/{slug}/           one game                                              (game-render.php?slug=...)
 *   /sitemaps/games-sitemap.xml  all game pages                                        (game-render.php?sitemap=1)
 *
 * The games come from roc-steam-games.json (official Steam requirements), see api/v1/game-pages-lib.php.
 * Header, footer and styles are the site's own (cms-templates/blog-post.html, as blog-render.php).
 * Installed by api/v1/cron/cli-game-pages.php (.htaccess rules and the sitemap).
 */

declare(strict_types=1);

define('ROC_RENDER_LIB_ONLY', true);
require __DIR__ . '/blog-render.php';          // helpers only: rocTemplate, rocH, rocJsonLd, rocSendHtml, …
require_once __DIR__ . '/api/v1/game-pages-lib.php';

$ROOT  = __DIR__;
$slug  = isset($_GET['slug']) ? strtolower(trim((string)$_GET['slug'], "/ \t\n\r")) : '';
$isMap = isset($_GET['sitemap']);
if ($slug !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) rocNotFound($ROOT);

$games   = rocGpAll($ROOT);
$updated = rocGpUpdated($ROOT);
if (!$games) { if ($isMap) { http_response_code(503); exit; } rocNotFound($ROOT); }

/* ------------------------------------------------------------- sitemap */
if ($isMap) {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    header('X-Robots-Tag: noindex');
    $out = rocSmOpen() . rocSmLine(ROC_PUBLIC_URL . '/can-i-run/', $updated, 'Can I run it? All PC games');
    foreach ($games as $g) $out .= rocSmLine(ROC_PUBLIC_URL . rocGpPath($g), $updated, 'Can I run ' . $g['name'] . '?');
    echo $out . "</urlset>\n";
    exit;
}

/* The database is only needed for the upgrade picks: the pages work without it. */
$pdo = null;
if ($slug !== '') {
    try {
        ob_start();
        require_once $ROOT . '/api/v1/config.php';
        ob_end_clean();
        $pdo = function_exists('getDBConnection') ? getDBConnection() : null;
    } catch (\Throwable $e) {
        while (ob_get_level() > 0) ob_end_clean();
        $pdo = null;
    }
    if (!headers_sent()) foreach (['X-Robots-Tag', 'Content-Type', 'Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials', 'Set-Cookie', 'Expires', 'Pragma', 'Cache-Control'] as $hh) header_remove($hh);
}

/* ---------------------------------------------------------- shared bits */
/**
 * The share picture of a game page (Deploy 82): a 1200x630 card with the game's name and what it needs,
 * drawn once with GD into /uploads/og-games/{slug}.png (no game art: those pictures belong to the
 * publishers). Facebook, WhatsApp and X do not show SVG, so the old logo never appeared.
 * Returns the address, or '' when GD or a font is missing.
 */
function rocGpOgCard(string $root, string $slug, string $title, string $line, int $updated): string {
    $rel = '/uploads/og-games/' . $slug . '.png';
    $file = $root . $rel;
    if (is_file($file) && filemtime($file) >= $updated) return $rel;
    if (!function_exists('imagettftext') || !function_exists('imagepng')) return '';
    $font = null;
    foreach (array_merge([$root . '/api/v1/cms/fonts/Montserrat-ExtraBold.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                          'C:/Windows/Fonts/segoeuib.ttf'], glob('/usr/share/fonts/*/*Bold.ttf') ?: []) as $f) if (is_file($f)) { $font = $f; break; }
    if (!$font) return '';
    $W = 1200; $H = 630;
    $im = imagecreatetruecolor($W, $H);
    // Dark green with a teal band, like the page header (flat colours keep the file small).
    imagefilledrectangle($im, 0, 0, $W, $H, imagecolorallocate($im, 2, 44, 34));
    imagefilledrectangle($im, 0, $H - 120, $W, $H, imagecolorallocate($im, 6, 95, 70));
    $white = imagecolorallocate($im, 255, 255, 255); $mint = imagecolorallocate($im, 167, 243, 208); $green = imagecolorallocate($im, 52, 211, 153);
    imagefilledrectangle($im, 0, 0, 14, $H, $green);
    $pad = 72;
    imagettftext($im, 26, 0, $pad, 112, $green, $font, 'CAN I RUN IT?');
    // The title, as big as fits in three lines.
    $size = 64; $lines = [];
    for (; $size >= 34; $size -= 4) {
        $lines = []; $cur = '';
        foreach (preg_split('/\s+/u', $title) as $w) {
            $try = $cur === '' ? $w : $cur . ' ' . $w;
            $b = imagettfbbox($size, 0, $font, $try);
            if ($b[2] - $b[0] > $W - 2 * $pad && $cur !== '') { $lines[] = $cur; $cur = $w; } else $cur = $try;
        }
        if ($cur !== '') $lines[] = $cur;
        if (count($lines) <= 3) break;
    }
    $y = 200;
    foreach (array_slice($lines, 0, 3) as $l) { $y += (int)($size * 1.25); imagettftext($im, $size, 0, $pad, $y, $white, $font, $l); }
    if ($line !== '') imagettftext($im, 24, 0, $pad, min($H - 130, $y + 70), $mint, $font, $line);
    imagettftext($im, 22, 0, $pad, $H - 56, $green, $font, 'RUN ON CONSOLE');
    $site = 'runonconsole.com/can-i-run';
    $b = imagettfbbox(20, 0, $font, $site);
    imagettftext($im, 20, 0, $W - $pad - ($b[2] - $b[0]), $H - 56, $white, $font, $site);
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $data = '';
    // A 32-colour PNG: about 15 KB each (2,000+ games, so small for the disk), drawn only when a page is first opened.
    imagetruecolortopalette($im, false, 32);
    ob_start(); imagepng($im, null, 9); $data = (string)ob_get_clean();
    imagedestroy($im);
    $tmp = $file . '.roc-tmp';
    if (@file_put_contents($tmp, $data) === false || !@rename($tmp, $file)) { @unlink($tmp); return ''; }
    return $rel;
}

function rocGpHead(string $title, string $desc, string $url, array $ld, string $robots = 'index, follow, max-image-preview:large', string $img = '', string $imgAlt = ''): string {
    // Share picture: the game's card, else the site picture (the SVG logo does not show on social sites).
    $img = ROC_PUBLIC_URL . ($img !== '' ? $img : '/uploads/site/home-hero.webp');
    $h  = '<title>' . rocH($title) . "</title>\n";
    $h .= '    <meta name="title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta name="description" content="' . rocH($desc) . "\" />\n";
    $h .= '    <meta name="robots" content="' . $robots . "\" />\n";
    $h .= '    <link rel="canonical" href="' . rocH($url) . "\" />\n";
    $h .= "    <meta property=\"og:type\" content=\"website\" />\n";
    $h .= '    <meta property="og:url" content="' . rocH($url) . "\" />\n";
    $h .= '    <meta property="og:title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta property="og:description" content="' . rocH($desc) . "\" />\n";
    $h .= "    <meta property=\"og:site_name\" content=\"Run On Console\" />\n";
    $h .= "    <meta property=\"og:locale\" content=\"en_US\" />\n";
    $h .= '    <meta property="og:image" content="' . rocH($img) . "\" />\n";
    if (strpos($img, '/uploads/og-games/') !== false) $h .= "    <meta property=\"og:image:type\" content=\"image/png\" />
    <meta property=\"og:image:width\" content=\"1200\" />
    <meta property=\"og:image:height\" content=\"630\" />
";
    $h .= '    <meta property="og:image:alt" content="' . rocH($imgAlt !== '' ? $imgAlt : $title) . "\" />\n";
    $h .= "    <meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
    $h .= "    <meta name=\"twitter:site\" content=\"@RunOnConsole\" />\n";
    $h .= '    <meta name="twitter:title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta name="twitter:description" content="' . rocH($desc) . "\" />\n";
    $h .= '    <meta name="twitter:image" content="' . rocH($img) . "\" />\n";
    foreach ($ld as $node) $h .= rocJsonLd($node);
    return $h . rocGpStyles() . "    <script src=\"/roc-nav.js\" defer></script>\n  ";
}

function rocGpCrumbs(array $crumbs): array {
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(function ($c, $i) {
        return ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => ROC_PUBLIC_URL . $c[1]];
    }, $crumbs, array_keys($crumbs))];
}

function rocGpCrumbHtml(array $crumbs): string {
    $last = count($crumbs) - 1;
    return '<nav aria-label="Breadcrumb" class="text-xs text-slate-500 font-medium">' . implode(' <span aria-hidden="true">›</span> ', array_map(function ($c, $i) use ($last) {
        return $i === $last ? '<span class="text-slate-700">' . rocH($c[0]) . '</span>' : '<a href="' . rocH($c[1]) . '" class="hover:text-emerald-600">' . rocH($c[0]) . '</a>';
    }, $crumbs, array_keys($crumbs))) . '</nav>';
}

function rocGpStyles(): string {
    return "<style>"
        . ".gp-hero{background:linear-gradient(135deg,#022c22,#065f46 55%,#0f766e);color:#fff;border-radius:1.5rem;padding:1.6rem 1.4rem;box-shadow:0 20px 40px -24px rgba(4,120,87,.7)}"
        . ".gp-hero h1{font-family:Outfit,Inter,sans-serif;font-weight:800;font-size:1.9rem;line-height:1.15;color:#fff}"
        . ".gp-hero p{color:#d1fae5;font-size:.95rem;line-height:1.6;margin-top:.6rem;max-width:46rem}"
        . ".gp-tags{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.9rem}.gp-tag{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:999px;padding:.2rem .7rem;font-size:.72rem;font-weight:700;color:#ecfdf5}"
        . ".gp-tag.w{background:#34d399;color:#052e2b;border-color:#34d399}"
        . ".gp-card{background:#fff;border:1px solid #e2e8f0;border-radius:1.25rem;padding:1.2rem 1.25rem}"
        . ".gp-card h2{font-family:Outfit,Inter,sans-serif;font-weight:800;font-size:1.35rem;color:#0f172a;margin-bottom:.8rem}"
        . ".gp-card p{color:#475569;line-height:1.7;font-size:.95rem}.gp-card p+p{margin-top:.6rem}"
        . ".gp-table{width:100%;border-collapse:collapse;font-size:.9rem}.gp-table th,.gp-table td{border-top:1px solid #e2e8f0;padding:.65rem .6rem;text-align:left;vertical-align:top}"
        . ".gp-table thead th{border-top:0;background:#f1f5f9;font-family:Outfit,Inter,sans-serif;font-weight:800;color:#0f172a}.gp-table tbody th{color:#334155;font-weight:700;width:7.5rem}"
        . ".gp-table td{color:#1e293b}.gp-wrap{overflow-x:auto}"
        . ".gp-check{display:grid;gap:.7rem;grid-template-columns:1fr}.gp-check label{display:flex;flex-direction:column;gap:.3rem;font-size:.75rem;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:.04em}"
        . ".gp-check select{border:2px solid #e2e8f0;border-radius:.8rem;padding:.6rem .7rem;font-size:.9rem;font-weight:600;color:#0f172a;background:#f8fafc;text-transform:none;letter-spacing:0}"
        . ".gp-check select:focus{outline:none;border-color:#10b981;background:#fff}"
        . ".gp-res{margin-top:1rem;border-radius:1rem;padding:1rem 1.1rem;background:#f1f5f9;color:#334155;font-size:.92rem;line-height:1.6}"
        . ".gp-res b{font-family:Outfit,Inter,sans-serif;font-size:1.6rem;font-weight:800;display:block;line-height:1.1}"
        . ".gp-res.ok{background:#ecfdf5;color:#065f46}.gp-res.mid{background:#fefce8;color:#854d0e}.gp-res.low{background:#fff7ed;color:#9a3412}.gp-res.no{background:#fef2f2;color:#991b1b}"
        . ".gp-bar{height:.55rem;border-radius:999px;background:rgba(15,23,42,.08);margin:.5rem 0;overflow:hidden}.gp-bar i{display:block;height:100%;border-radius:999px;background:currentColor}"
        . ".gp-btns{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}.gp-btn{display:inline-flex;align-items:center;gap:.3rem;font-weight:800;font-size:.82rem;padding:.6rem 1rem;border-radius:.8rem;text-decoration:none}"
        . ".gp-btn.p{background:#10b981;color:#052e2b}.gp-btn.p:hover{background:#34d399}.gp-btn.s{border:1px solid #cbd5e1;color:#334155;background:#fff}.gp-btn.s:hover{border-color:#10b981;color:#047857}"
        . ".gp-parts{display:grid;gap:.8rem;grid-template-columns:1fr}.gp-parts>div{border:1px solid #e2e8f0;border-radius:1rem;padding:.9rem 1rem;background:#f8fafc}"
        . ".gp-parts h3{font-family:Outfit,Inter,sans-serif;font-weight:800;color:#0f172a;font-size:1rem;margin-bottom:.4rem}.gp-parts ul{list-style:disc;padding-left:1.2rem;color:#334155;font-size:.9rem;line-height:1.7}"
        . ".gp-prod{display:grid;gap:1rem;grid-template-columns:repeat(auto-fill,minmax(210px,1fr))}"
        . ".gp-p{border:1px solid #e2e8f0;border-radius:1.1rem;background:#fff;overflow:hidden;display:flex;flex-direction:column}"
        . ".gp-p img{width:100%;height:150px;object-fit:contain;background:#fff;padding:.6rem}.gp-p .b{padding:.9rem;display:flex;flex-direction:column;gap:.35rem;flex:1}"
        . ".gp-p .k{font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#059669}"
        . ".gp-p a.t{font-family:Outfit,Inter,sans-serif;font-weight:800;color:#0f172a;line-height:1.3}.gp-p a.t:hover{color:#059669}.gp-p .d{font-size:.8rem;color:#64748b;line-height:1.5}"
        . ".gp-p .a{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:auto;padding-top:.4rem}.gp-p .a a{font-size:.72rem;font-weight:800;padding:.45rem .7rem;border-radius:.7rem}"
        . ".gp-p .a .m{border:1px solid #cbd5e1;color:#334155}.gp-p .a .y{background:#f59e0b;color:#0f172a}.gp-p .a .y:hover{background:#d97706}"
        . ".gp-note{font-size:.72rem;color:#94a3b8;margin-top:.8rem}"
        . ".gp-faq details{border:1px solid #e2e8f0;border-radius:1rem;background:#fff;padding:.85rem 1.05rem}.gp-faq details+details{margin-top:.6rem}"
        . ".gp-faq summary{cursor:pointer;font-weight:800;font-family:Outfit,Inter,sans-serif;color:#0f172a}.gp-faq details[open]{border-color:#10b981;background:#f0fdf4}"
        . ".gp-faq .a{margin-top:.5rem;color:#334155;line-height:1.7;font-size:.93rem}"
        . ".gp-sim{display:grid;gap:.6rem;grid-template-columns:repeat(auto-fill,minmax(200px,1fr))}"
        . ".gp-sim a{display:flex;flex-direction:column;border:1px solid #e2e8f0;border-radius:.9rem;padding:.7rem .85rem;background:#fff;text-decoration:none}"
        . ".gp-sim a:hover{border-color:#10b981;background:#f0fdf4}.gp-sim strong{font-weight:800;color:#0f172a;font-size:.9rem;line-height:1.3}.gp-sim span{font-size:.72rem;color:#64748b;margin-top:.2rem}"
        . ".gp-src{font-size:.78rem;color:#64748b}.gp-src a{color:#047857;font-weight:700;text-decoration:underline}"
        . ".gp-search{width:100%;border:2px solid #e2e8f0;border-radius:1rem;padding:.8rem 1rem;font-size:1rem;font-weight:600;color:#0f172a;background:#fff}"
        . ".gp-search:focus{outline:none;border-color:#10b981}"
        . ".gp-az{display:flex;flex-wrap:wrap;gap:.3rem}.gp-az a{min-width:2rem;text-align:center;border:1px solid #e2e8f0;border-radius:.6rem;padding:.3rem .45rem;font-weight:800;font-size:.8rem;color:#334155;background:#fff}"
        . ".gp-az a:hover{border-color:#10b981;color:#047857}"
        . ".gp-list{columns:1;column-gap:1.5rem}.gp-list li{break-inside:avoid;padding:.22rem 0;font-size:.9rem}.gp-list a{color:#0f172a;font-weight:600}.gp-list a:hover{color:#047857;text-decoration:underline}"
        . ".gp-list small{color:#94a3b8;font-size:.72rem;margin-left:.3rem}.gp-letter{font-family:Outfit,Inter,sans-serif;font-weight:800;font-size:1.3rem;color:#047857;margin:1.2rem 0 .4rem;scroll-margin-top:90px}"
        . "@media(min-width:640px){.gp-hero{padding:2.2rem 2.2rem}.gp-hero h1{font-size:2.6rem}.gp-check{grid-template-columns:repeat(3,1fr)}.gp-parts{grid-template-columns:1fr 1fr}.gp-list{columns:2}}"
        . "@media(min-width:1024px){.gp-list{columns:3}}"
        . "</style>\n";
}

[$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($ROOT, 'blog-post.html');

/* ------------------------------------------------------------ the list */
if ($slug === '') {
    $n = count($games);
    $url = ROC_PUBLIC_URL . '/can-i-run/';
    $crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/']];
    $title = 'Can I Run It? System Requirements for ' . number_format($n) . ' PC Games | Run On Console';
    $desc = 'Official minimum and recommended PC requirements for ' . number_format($n) . ' popular games, with a free check for your graphics card, processor and RAM.';
    $ld = [rocGpCrumbs($crumbs), ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'Can I run it? PC game system requirements', 'url' => $url, 'description' => $desc,
        'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'],
        'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $n, 'itemListElement' => array_map(function ($g, $i) {
            return ['@type' => 'ListItem', 'position' => $i + 1, 'url' => ROC_PUBLIC_URL . rocGpPath($g), 'name' => $g['name']];
        }, array_slice(array_values($games), 0, 30), range(0, min(29, $n - 1)))]]];
    $og = rocGpOgCard($ROOT, '_all', 'System requirements for ' . number_format($n) . ' PC games', 'Free check for your graphics card, processor and RAM', $updated);
    $h = rocGpHead($title, $desc, $url, $ld, 'index, follow, max-image-preview:large', $og, 'Can I run it? PC game system requirements on Run On Console');

    $byLetter = [];
    $sorted = array_values($games);
    usort($sorted, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    foreach ($sorted as $g) {
        $l = strtoupper(substr($g['slug'], 0, 1));
        $byLetter[preg_match('/^[A-Z]$/', $l) ? $l : '#'][] = $g;
    }
    ksort($byLetter);
    if (isset($byLetter['#'])) { $num = $byLetter['#']; unset($byLetter['#']); $byLetter = ['#' => $num] + $byLetter; }
    $item = function ($g) { return '<li data-n="' . rocH(strtolower($g['name'])) . '"><a href="' . rocGpPath($g) . '">' . rocH($g['name']) . '</a><small>' . (int)$g['y'] . '</small></li>'; };

    $m  = $mainOpen . '<div class="max-w-6xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
    $m .= '<header class="gp-hero"><h1>Can I run it? ' . number_format($n) . ' PC games</h1>'
        . '<p>Pick a game to see its official minimum and recommended requirements from Steam, which graphics cards and processors run it, '
        . 'and check your own PC in a few seconds. The full checker also covers consoles, handhelds and phones.</p>'
        . '<div class="gp-btns"><a class="gp-btn p" href="/compatibility/">Open the full checker &rarr;</a></div></header>';
    $m .= '<section class="gp-card"><label for="gp-q" class="sr-only">Search a game</label>'
        . '<input id="gp-q" class="gp-search" type="search" placeholder="Search ' . number_format($n) . ' games, e.g. GTA, Elden Ring, Valorant…" autocomplete="off">'
        . '<p id="gp-none" class="gp-src" style="display:none;margin-top:.6rem">No game found. Try another name, or ask ROC in the chat.</p></section>';
    $m .= '<section class="gp-card" id="gp-pop"><h2>Most popular</h2><ul class="gp-list">' . implode('', array_map($item, array_slice(array_values($games), 0, 60))) . '</ul></section>';
    $m .= rocAdUnit('list');
    $m .= '<section class="gp-card" id="gp-all"><h2>All games A to Z</h2><nav class="gp-az" aria-label="Letters">';
    foreach (array_keys($byLetter) as $l) $m .= '<a href="#l-' . ($l === '#' ? '0' : $l) . '">' . $l . '</a>';
    $m .= '</nav>';
    foreach ($byLetter as $l => $list) $m .= '<h3 class="gp-letter" id="l-' . ($l === '#' ? '0' : $l) . '">' . $l . '</h3><ul class="gp-list">' . implode('', array_map($item, $list)) . '</ul>';
    $m .= '</section>';
    $m .= '<p class="gp-src">Requirements: the official <a href="https://store.steampowered.com/" target="_blank" rel="noopener">Steam store</a> page of each game, updated ' . gmdate('F Y', $updated) . '.</p>';
    $m .= '</div>';
    $m .= "<script>(function(){var q=document.getElementById('gp-q'),pop=document.getElementById('gp-pop'),none=document.getElementById('gp-none'),"
        . "all=document.getElementById('gp-all'),items=all.querySelectorAll('li[data-n]'),heads=all.querySelectorAll('.gp-letter,.gp-az');"
        . "q.addEventListener('input',function(){var v=q.value.trim().toLowerCase(),hit=0;pop.style.display=v?'none':'';"
        . "for(var i=0;i<items.length;i++){var ok=!v||items[i].getAttribute('data-n').indexOf(v)>-1;items[i].style.display=ok?'':'none';if(ok)hit++;}"
        . "for(var j=0;j<heads.length;j++)heads[j].style.display=v?'none':'';none.style.display=v&&!hit?'':'none';});})();</script>";
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}

/* ------------------------------------------------------------ one game */
$g = $games[$slug] ?? null;
if (!$g) {
    // An old address (a name that changed on Steam): the game with the same Steam id, if any.
    if (preg_match('/-(\d{3,8})$/', $slug, $mm) && ($x = rocGpByAppid($ROOT, (int)$mm[1]))) { header('Location: ' . rocGpPath($x), true, 301); exit; }
    rocNotFound($ROOT);
}

$name = $g['name'];
$url  = ROC_PUBLIC_URL . rocGpPath($g);
$min  = (array)($g['min'] ?? []);
$rec  = is_array($g['rec'] ?? null) ? $g['rec'] : [];
$rMin = (array)($g['req']['min'] ?? []);
$rRec = (array)($g['req']['rec'] ?? $rMin);
[$weight, $weightTxt] = rocGpWeight($g);
$year  = (int)([730 => 2023][(int)$g['appid']] ?? ($g['y'] ?? 0));   // Steam keeps CS:GO's 2012 date for Counter-Strike 2
$genre = trim((string)($g['g'] ?? ''));
$clean = function ($s) { return trim((string)preg_replace('/\s+/', ' ', str_replace(['™', '®'], '', (string)$s))); };
$ramGb = function ($v) { return is_numeric($v) && $v > 0 ? rtrim(rtrim(number_format((float)$v, 1), '0'), '.') . ' GB' : ''; };
$gpuMin = rocGpPartsFor('gpu', isset($rMin['gpu']) ? (float)$rMin['gpu'] : null);
$gpuRec = rocGpPartsFor('gpu', isset($rRec['gpu']) ? (float)$rRec['gpu'] : null);
$cpuMin = rocGpPartsFor('cpu', isset($rMin['cpu']) ? (float)$rMin['cpu'] : null);
$cpuRec = rocGpPartsFor('cpu', isset($rRec['cpu']) ? (float)$rRec['cpu'] : null);
$storage = $clean($rec['storage'] ?? '') ?: $clean($min['storage'] ?? '');
$storageGb = preg_match('/(\d+(?:\.\d+)?)\s*GB/i', $storage, $sm) ? (float)$sm[1] : (preg_match('/(\d+)\s*MB/i', $storage, $sm) ? round((int)$sm[1] / 1024, 1) : null);

// What a few common PCs get (the same rules as the checker and ROC).
$common = [['GeForce GTX 1650', 'Intel Core i5-8400', 16, 'GTX 1650 + Core i5-8400 + 16 GB'], ['GeForce RTX 3060', 'AMD Ryzen 5 5600', 16, 'RTX 3060 + Ryzen 5 5600 + 16 GB'],
           ['Intel UHD / Iris Xe (integrated)', 'Intel Core i5-8400', 8, 'Laptop with integrated graphics + 8 GB']];
$gpus = rocGpRefs('gpu'); $cpus = rocGpRefs('cpu');
$runs = [];
foreach ($common as [$gn, $cn, $ram, $lbl]) $runs[$gn] = rocAgentCheck($g, $gpus[$gn], $cpus[$cn], (float)$ram) + ['label' => $lbl];
$short = function (int $p) { return $p >= 90 ? 'high settings' : ($p >= 70 ? 'medium to high settings' : ($p > 40 ? 'low settings only' : 'below the minimum, not playable well')); };

/* text */
$an = function (string $w) { return (preg_match('/^(?:[aeio]|u(?!ni)|RX\b|RTX|FX|8)/i', $w) ? 'an ' : 'a ') . $w; };
$genres = array_values(array_filter(array_map(function ($x) { $x = trim($x); return in_array($x, ['RPG', 'Indie'], true) ? ($x === 'Indie' ? 'indie' : $x) : strtolower($x); },
    explode(',', $genre)), function ($x) { return $x !== '' && !in_array($x, ['free to play', 'early access', 'violent', 'gore', 'massively multiplayer'], true); }));
$gPhrase = count($genres) > 1 ? implode(', ', array_slice($genres, 0, -1)) . ' and ' . end($genres) : ($genres[0] ?? '');
$intro = $name . ($year ? ' (' . $year . ')' : '') . ' is ' . ($gPhrase !== '' ? $an($gPhrase) . ' game' : 'a PC game')
    . ' and ' . $an($weight) . ' game for a PC. ' . $weightTxt;
$cardTxt = function (array $l, string $tail) use ($an) { return strpos($l[0], 'integrated') !== false ? 'integrated graphics (Intel UHD / Iris Xe) or any graphics card' : $an($l[0]) . $tail; };
$needLine = 'To play it you need at least ' . ($gpuMin ? $cardTxt($gpuMin, ' class graphics card') : 'the graphics card on the list below')
    . ($cpuMin ? ', ' . $an($cpuMin[0]) . ' class processor' : '') . ($ramGb($rMin['ram'] ?? null) ? ' and ' . $ramGb($rMin['ram'] ?? null) . ' of RAM' : '') . '.';
if ($rec) $needLine .= ' For the recommended (high) settings: ' . ($gpuRec ? $cardTxt($gpuRec, ' or faster') : 'a faster card')
    . ($cpuRec ? ', ' . $an($cpuRec[0]) . ' class processor' : '') . ($ramGb($rRec['ram'] ?? null) ? ' and ' . $ramGb($rRec['ram'] ?? null) . ' of RAM' : '') . '.';

$metaT = 'Can I Run ' . $name . '? Minimum & Recommended PC Requirements | Run On Console';
$metaD = 'Can your PC run ' . $name . '? Official minimum and recommended specs'
    . ($gpuMin ? ' (from ' . (strpos($gpuMin[0], 'integrated') !== false ? 'integrated graphics' : $an($gpuMin[0])) . ($ramGb($rMin['ram'] ?? null) ? ' and ' . $ramGb($rMin['ram'] ?? null) . ' RAM' : '') . ')' : '')
    . '. Free check for your graphics card, CPU and RAM.';
if (mb_strlen($metaD) > 155) $metaD = mb_substr($metaD, 0, 152) . '…';   // 155: what Google and SEO tools show in full

/* FAQ (each answer from the data) */
$faqs = [];
$faqs[] = ['What are the minimum system requirements for ' . $name . '?',
    'Minimum: ' . implode('; ', array_filter([$clean($min['os'] ?? '') ? 'OS ' . $clean($min['os']) : '', $clean($min['cpu'] ?? '') ? 'processor ' . $clean($min['cpu']) : '',
        $clean($min['ram'] ?? '') ? $clean($min['ram']) : '', $clean($min['gpu'] ?? '') ? 'graphics ' . $clean($min['gpu']) : '', $clean($min['storage'] ?? '') ? $clean($min['storage']) : ''])) . '.'];
if ($rec) $faqs[] = ['What are the recommended requirements for ' . $name . '?',
    'Recommended: ' . implode('; ', array_filter([$clean($rec['cpu'] ?? '') ? 'processor ' . $clean($rec['cpu']) : '', $clean($rec['ram'] ?? '') ? $clean($rec['ram']) : '',
        $clean($rec['gpu'] ?? '') ? 'graphics ' . $clean($rec['gpu']) : '', $clean($rec['storage'] ?? '') ? $clean($rec['storage']) : ''])) . '.'];
if ($gpuMin) $faqs[] = ['What graphics card do I need for ' . $name . '?',
    'At least a ' . implode(', ', $gpuMin) . ' or a card of the same speed for the minimum settings' . ($gpuRec && $rec ? '; for the recommended settings a ' . implode(', ', $gpuRec) . ' or faster.' : '.')];
$r1650 = $runs['GeForce GTX 1650'];
$c1650 = $gpus['GeForce GTX 1650'];
$faqs[] = ['Can a GTX 1650 run ' . $name . '?',
    ((float)($rMin['gpu'] ?? 0) <= $c1650 ? 'Yes. The GTX 1650 reaches the minimum graphics' . ((float)($rRec['gpu'] ?? 0) <= $c1650 ? ' and the recommended graphics too.' : ', not the recommended.')
        : 'Not well: the GTX 1650 is below the minimum graphics card for ' . $name . '.')
    . ' A whole PC with a GTX 1650, a Core i5-8400 and 16 GB of RAM gets ' . $r1650['percent'] . '% in our checker (' . $short((int)$r1650['percent']) . ').'];
$rInt = $runs['Intel UHD / Iris Xe (integrated)'];
$faqs[] = ['Can ' . $name . ' run without a graphics card (integrated graphics)?',
    ((float)($rMin['gpu'] ?? 9) <= 1.8 ? 'Yes. The minimum graphics are integrated graphics level, so a laptop or PC without a graphics card can run it, usually on low to medium settings.'
        : 'Not well. The minimum is a dedicated graphics card' . ($gpuMin ? ' (' . $gpuMin[0] . ' class)' : '') . '; with integrated graphics our checker gives ' . $rInt['percent'] . '% (' . $short((int)$rInt['percent']) . ').')];
$rmn = (float)($rMin['ram'] ?? 0); $rrc = (float)($rRec['ram'] ?? $rmn);
if ($rmn > 0) $faqs[] = ['Is 8 GB of RAM enough for ' . $name . '?',
    $rmn > 8 ? 'No. ' . $name . ' needs at least ' . $ramGb($rmn) . ' of RAM.' : ($rrc > 8 ? 'Yes for the minimum settings; ' . $ramGb($rrc) . ' is recommended for smooth play.' : 'Yes. 8 GB meets the ' . ($rec ? 'recommended' : 'minimum') . ' memory for ' . $name . '.')];
if ($storage !== '') $faqs[] = ['How much storage does ' . $name . ' need?', $name . ' needs ' . preg_replace('/\s*available space/i', '', $storage) . ' of free space' . ($storageGb !== null && $storageGb >= 50 ? '. An SSD is a good idea for faster loading.' : '.')];

/* upgrade picks */
$picks = [];
if ($pdo) {
    $picks['gpu'] = ['Graphics card for the recommended settings', rocGpProducts($pdo, 'gpu', isset($rRec['gpu']) ? (float)$rRec['gpu'] : null, 3)];
    $picks['cpu'] = ['Processor for the recommended settings', rocGpProducts($pdo, 'cpu', isset($rRec['cpu']) ? (float)$rRec['cpu'] : null, 2)];
    if ($rrc >= 16) $picks['ram'] = ['Memory (' . $ramGb($rrc) . ' or more)', rocGpProducts($pdo, 'ram', $rrc, 2)];
    if ($storageGb !== null && $storageGb >= 60) $picks['storage'] = ['Storage', rocGpProducts($pdo, 'storage', null, 1)];
}
$picks = array_filter($picks, function ($p) { return !empty($p[1]); });

/* head */
$crumbs = [['Home', '/'], ['Can I run it?', '/can-i-run/'], [$name, rocGpPath($g)]];
$ld = [rocGpCrumbs($crumbs),
    ['@context' => 'https://schema.org', '@type' => 'WebPage', '@id' => $url, 'url' => $url, 'name' => 'Can I run ' . $name . '?', 'description' => $metaD,
     'dateModified' => gmdate('c', $updated), 'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'],
     'about' => array_filter(['@type' => 'VideoGame', 'name' => $name, 'gamePlatform' => 'PC', 'genre' => $genre !== '' ? array_map('trim', explode(',', $genre)) : null,
                              'datePublished' => $year ? (string)$year : null, 'sameAs' => (string)($g['u'] ?? '') ?: null,
                              // Its official minimum requirements (VideoGame is a SoftwareApplication).
                              'applicationCategory' => 'Game', 'operatingSystem' => $clean($min['os'] ?? '') ?: null,
                              'processorRequirements' => $clean($min['cpu'] ?? '') ?: null, 'memoryRequirements' => $clean($min['ram'] ?? '') ?: null,
                              'storageRequirements' => $clean($min['storage'] ?? '') ?: null])],
    ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
        return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]];
    }, $faqs)]];
$ogLine = implode(' · ', array_filter(['Minimum: ' . ($gpuMin ? (strpos($gpuMin[0], 'integrated') !== false ? 'integrated graphics' : $gpuMin[0]) : 'see the list'),
    $ramGb($rMin['ram'] ?? null) ? $ramGb($rMin['ram'] ?? null) . ' RAM' : '']));
$og = rocGpOgCard($ROOT, (string)$g['slug'], $name, $ogLine, $updated);
if ($og !== '') $ld[1]['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => ROC_PUBLIC_URL . $og, 'width' => 1200, 'height' => 630];
$h = rocGpHead($metaT, $metaD, $url, $ld, 'index, follow, max-image-preview:large', $og, 'Can I run ' . $name . '? System requirements card');

/* body */
$row = function ($label, $a, $b) use ($clean, $rec) {
    $a = $clean($a); $b = $clean($b);
    if ($a === '' && $b === '') return '';
    return '<tr><th scope="row">' . $label . '</th><td>' . ($a !== '' ? rocH($a) : '<span class="text-slate-400">Not listed</span>') . '</td>'
        . ($rec ? '<td>' . ($b !== '' ? rocH($b) : '<span class="text-slate-400">Not listed</span>') . '</td>' : '') . '</tr>';
};
$m  = $mainOpen . '<article class="max-w-4xl mx-auto py-6 space-y-6">' . rocGpCrumbHtml($crumbs);
$m .= '<header class="gp-hero"><h1>Can I run ' . rocH($name) . '?</h1><p>' . rocH($intro) . '</p>'
    . '<div class="gp-tags"><span class="gp-tag w">' . rocH(ucfirst($weight)) . '</span>' . ($year ? '<span class="gp-tag">' . $year . '</span>' : '')
    . implode('', array_map(function ($x) { return '<span class="gp-tag">' . rocH(trim($x)) . '</span>'; }, array_slice(array_filter(explode(',', $genre)), 0, 3)))
    . '<span class="gp-tag">PC (Windows)</span></div></header>';

// Quick check
$opt = function (array $refs, string $pick) { $o = ''; foreach ($refs as $n => $s) $o .= '<option value="' . $s . '"' . ($n === $pick ? ' selected' : '') . '>' . rocH($n) . '</option>'; return $o; };
$m .= '<section class="gp-card" id="check"><h2>Check your PC</h2>'
    . '<p>Choose the graphics card, processor and memory closest to yours. Not sure? On Windows press Win + R, type <b>dxdiag</b> and look at "Processor", "Memory" and the Display tab.</p>'
    . '<div class="gp-check" style="margin-top:.9rem" data-req="' . rocH(json_encode(['min' => $rMin, 'rec' => $rRec])) . '">'
    . '<label>Graphics card<select data-k="gpu">' . $opt($gpus, 'GeForce GTX 1650') . '</select></label>'
    . '<label>Processor<select data-k="cpu">' . $opt($cpus, 'Intel Core i5-8400') . '</select></label>'
    . '<label>Memory (RAM)<select data-k="ram">' . implode('', array_map(function ($r) { return '<option value="' . $r . '"' . ($r === 16 ? ' selected' : '') . '>' . $r . ' GB</option>'; }, [4, 6, 8, 12, 16, 24, 32, 64])) . '</select></label>'
    . '</div><div class="gp-res" id="gp-res" aria-live="polite"></div>'
    . '<div class="gp-btns"><a class="gp-btn p" href="/compatibility/?steam=' . (int)$g['appid'] . '">Full check (laptops, consoles, more parts) &rarr;</a>'
    . '<a class="gp-btn s" href="#requirements">See the requirements</a></div></section>';

// Requirements
$m .= '<section class="gp-card" id="requirements"><h2>' . rocH($name) . ' system requirements</h2><div class="gp-wrap"><table class="gp-table">'
    . '<thead><tr><th></th><th>Minimum</th>' . ($rec ? '<th>Recommended</th>' : '') . '</tr></thead><tbody>'
    . $row('OS', $min['os'] ?? '', $rec['os'] ?? '') . $row('Processor', $min['cpu'] ?? '', $rec['cpu'] ?? '') . $row('Memory', $min['ram'] ?? '', $rec['ram'] ?? '')
    . $row('Graphics', $min['gpu'] ?? '', $rec['gpu'] ?? '') . $row('Storage', $min['storage'] ?? '', $rec['storage'] ?? '')
    . '</tbody></table></div>'
    . ($rec ? '' : '<p class="gp-src" style="margin-top:.6rem">The game\'s Steam page lists only the minimum requirements.</p>')
    . '<p class="gp-src" style="margin-top:.6rem">Source: the official <a href="' . rocH((string)$g['u']) . '" target="_blank" rel="noopener">' . rocH($name) . ' Steam page</a>, checked ' . gmdate('F Y', $updated) . '.</p></section>';

$m .= rocAdUnit('article');

// Which parts run it
$li = function (array $xs) { return '<ul>' . implode('', array_map(function ($x) { return '<li>' . rocH($x) . '</li>'; }, $xs)) . '</ul>'; };
$m .= '<section class="gp-card"><h2>Which PC runs ' . rocH($name) . '?</h2><p>' . rocH($needLine) . '</p><div class="gp-parts" style="margin-top:.9rem">';
if ($gpuMin) $m .= '<div><h3>Graphics card: minimum</h3>' . $li($gpuMin) . '</div>';
if ($gpuRec && $rec) $m .= '<div><h3>Graphics card: high settings</h3>' . $li($gpuRec) . '</div>';
if ($cpuMin) $m .= '<div><h3>Processor: minimum</h3>' . $li($cpuMin) . '</div>';
if ($cpuRec && $rec) $m .= '<div><h3>Processor: high settings</h3>' . $li($cpuRec) . '</div>';
$m .= '</div><p style="margin-top:.9rem">How common PCs do: ' . implode('; ', array_map(function ($c) use ($short) {
        return '<b>' . rocH($c['label']) . '</b>: ' . (int)$c['percent'] . '%, ' . rocH($short((int)$c['percent']));
    }, array_values($runs))) . '.</p>'
    . '<p class="gp-src" style="margin-top:.6rem">Parts with the same speed (and newer, faster ones) work too. Laptop versions of a graphics card are usually a little slower.</p></section>';

// Upgrade picks
if ($picks) {
    $m .= '<section class="gp-card"><h2>Upgrade picks for ' . rocH($name) . '</h2><p>Parts from our product guides that reach the recommended specs.</p><div class="gp-prod" style="margin-top:.9rem">';
    foreach ($picks as [$label, $rows]) foreach ($rows as $pr) {
        $buy = trim((string)($pr['affiliate_amazon'] ?? ''));
        $m .= '<div class="gp-p">' . (!empty($pr['image']) ? '<a href="/products/' . rocH($pr['slug']) . '/"><img src="' . rocH($pr['image']) . '" alt="' . rocH($pr['image_alt'] ?: $pr['title']) . '" loading="lazy"></a>' : '')
            . '<div class="b"><span class="k">' . rocH($label) . '</span><a class="t" href="/products/' . rocH($pr['slug']) . '/">' . rocH($pr['title']) . '</a>'
            . (trim((string)($pr['best_for'] ?: $pr['short_desc'])) !== '' ? '<p class="d">' . rocH(mb_substr(strip_tags((string)($pr['best_for'] ?: $pr['short_desc'])), 0, 120)) . '</p>' : '')
            . '<div class="a"><a class="m" href="/products/' . rocH($pr['slug']) . '/">Our review</a>'
            . (preg_match('#^https?://#i', $buy) ? '<a class="y" href="' . rocH($buy) . '" target="_blank" rel="sponsored nofollow noopener">Check on Amazon</a>' : '') . '</div></div></div>';
    }
    $m .= '</div><p class="gp-note">As an Amazon Associate we earn from qualifying purchases.</p></section>';
}

// FAQ
$m .= '<section class="gp-card gp-faq"><h2>' . rocH($name) . ': questions</h2>';
foreach ($faqs as $i => $f) $m .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . rocH($f[0]) . '</summary><div class="a">' . rocH($f[1]) . '</div></details>';
$m .= '</section>';

// Similar games
$sim = rocGpSimilar($ROOT, $g, 8);
if ($sim) {
    $m .= '<section class="gp-card"><h2>Similar games: can you run them?</h2><div class="gp-sim">';
    foreach ($sim as $s) { [$sw] = rocGpWeight($s); $m .= '<a href="' . rocGpPath($s) . '"><strong>' . rocH($s['name']) . '</strong><span>' . (int)$s['y'] . ' · ' . rocH($sw) . '</span></a>'; }
    $m .= '</div><div class="gp-btns"><a class="gp-btn s" href="/can-i-run/">All ' . number_format(count($games)) . ' games &rarr;</a></div></section>';
}
// More on the site (Deploy 82): the pages a player checking requirements needs next.
$more = [['/compatibility/?steam=' . (int)$g['appid'], 'Full PC check', 'Laptops, consoles, handhelds and more parts'],
         ['/category/gpu/', 'Graphics cards', 'Picks for every budget'], ['/category/cpu/', 'Processors', 'Gaming CPUs from entry to high end'],
         ['/category/ram/', 'Memory (RAM)', 'DDR4 and DDR5 kits'], ['/gaming-platforms/pc-handheld-pc/', 'PC and handheld PC guide', 'Gaming platform guide'],
         ['/guides/', 'Gaming guides', 'Buying, setup and game settings, step by step'], ['/can-i-run/', 'All PC games', number_format(count($games)) . ' games with their requirements']];
$m .= '<section class="gp-card"><h2>More for PC players</h2><div class="gp-sim">'
    . implode('', array_map(function ($l) { return '<a href="' . rocH($l[0]) . '"><strong>' . rocH($l[1]) . '</strong><span>' . rocH($l[2]) . '</span></a>'; }, $more)) . '</div></section>';
$m .= '<p class="gp-src">Ask <b>ROC</b>, our gaming assistant (the chat button), "can my PC run ' . rocH($name) . '?" and tell it your parts, or upload your dxdiag file.</p>';
$m .= '</article>';

// The quick check: the same rules as /compatibility/ and ROC (graphics 50%, processor 30%, RAM 20% of the recommended; under the minimum = at most 40%).
$m .= "<script>(function(){var box=document.querySelector('.gp-check'),res=document.getElementById('gp-res');if(!box)return;var R=JSON.parse(box.getAttribute('data-req'));"
    . "function v(k){return parseFloat(box.querySelector('[data-k=\"'+k+'\"]').value)}"
    . "function run(){var have={gpu:v('gpu'),cpu:v('cpu'),ram:v('ram')},mn=R.min||{},rc=R.rec||mn,w={gpu:.5,cpu:.3,ram:.2},s=0,t=0,below=false,miss=[];"
    . "for(var k in w){var need=rc[k]||mn[k];if(need>0){s+=w[k]*Math.min(1,have[k]/need);t+=w[k];if(have[k]<need)miss.push(k);}if(mn[k]>0&&have[k]<mn[k])below=true;}"
    . "var p=Math.round(100*s/(t||1));if(below)p=Math.min(p,40);p=Math.max(5,p);"
    . "var c=p>=90?'ok':p>=70?'mid':p>40?'low':'no',txt=p>=90?'Runs on high settings (meets the recommended specs).':p>=70?'Playable on medium to high settings.':p>40?'Playable on low settings only.':'Below the minimum specs: it will not run well.';"
    . "var names={gpu:'graphics card',cpu:'processor',ram:'RAM'},up=miss.map(function(k){return names[k]});"
    . "res.className='gp-res '+c;res.innerHTML='<b>'+p+'%</b><div class=\"gp-bar\"><i style=\"width:'+p+'%\"></i></div>'+txt+(up.length?' Upgrade first: '+up.join(', ')+'.':'');}"
    . "box.addEventListener('change',run);run();})();</script>";

rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
