<?php
/**
 * Run On Console — "Can I run it?" game pages in other languages (Deploy 177).
 *
 *   /de/can-i-run/<game>/   (de, es, pt, fr, ru)   one game: requirements, how common PCs do, FAQ
 *   /de/can-i-run/                                 the language's game list
 *   /sitemaps/games-de-sitemap.xml                 the language's sitemap
 *
 * Routes from cli-languages.php (.htaccess). The data and rules are the English pages' (game-render.php, loaded as
 * a library); the words come from api/v1/i18n-lib.php. Steam's requirement lines stay in English, as Steam writes them.
 */

$lang  = isset($_GET['lang']) ? (string)$_GET['lang'] : '';
$want  = isset($_GET['slug']) ? strtolower(trim((string)$_GET['slug'], "/ \t\n\r")) : '';
$asMap = isset($_GET['sitemap']);
unset($_GET['slug'], $_GET['sitemap'], $_GET['lang']);
define('ROC_GP_AS_LIB', true);
require __DIR__ . '/game-render.php';          // functions, $ROOT, $pdo, $games, $updated, $EDITS and the template
require_once __DIR__ . '/api/v1/i18n-lib.php';

if (!in_array($lang, ROC_I18N_LANGS, true) || ($want !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $want))) rocNotFound($ROOT);
$T = rocI18n($lang);
$t = function (string $k, ...$a) use ($T) { return rocI18nT($T, $k, ...$a); };
$headTop = (string)preg_replace('/<html\s+lang="en"/i', '<html lang="' . $lang . '"', $headTop, 1);
// Numbers the way each language writes them (2.456 / 2 456).
$nf = function ($n) use ($lang) { return number_format((float)$n, 0, ',', in_array($lang, ['fr', 'ru'], true) ? "\u{202F}" : '.'); };
$langLinks = function (string $enPath) use ($lang): string {
    $out = '<a hreflang="en" lang="en" href="' . $enPath . '">English</a>';
    foreach (ROC_I18N_LANGS as $l) if ($l !== $lang) $out .= ' · <a hreflang="' . $l . '" lang="' . $l . '" href="' . rocI18nPath($l, $enPath) . '">' . rocH(rocI18n($l)['name']) . '</a>';
    return $out;
};

/* ------------------------------------------------------------- sitemap */
if ($asMap) {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    header('X-Robots-Tag: noindex');
    $out = rocSmOpen() . rocSmLine(ROC_PUBLIC_URL . rocI18nPath($lang, '/can-i-run/'), $updated, $t('hub_h1', $nf(count($games))));
    foreach ($games as $g) if (!rocGpThin($g, (array)($EDITS[$g['slug']] ?? []))) $out .= rocSmLine(ROC_PUBLIC_URL . rocI18nPath($lang, rocGpPath($g)), rocGpPageTime($g, (array)($EDITS[$g['slug']] ?? [])), $g['name']);
    echo $out . "</urlset>\n";
    exit;
}

/* ------------------------------------------------------------- the list */
if ($want === '') {
    $n = count($games);
    $path = rocI18nPath($lang, '/can-i-run/');
    $crumbs = [[$t('home'), '/'], [$t('cir'), $path]];
    $title = $t('hub_title', $nf($n));
    $desc = $t('hub_desc', $nf($n));
    $ld = [rocGpCrumbs($crumbs), ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $title, 'url' => ROC_PUBLIC_URL . $path, 'description' => $desc, 'inLanguage' => $lang,
        'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/']]];
    $h = rocGpHead($title, $desc, ROC_PUBLIC_URL . $path, $ld) . rocI18nAlternates('/can-i-run/');
    $item = function ($g) use ($lang) { return '<li><a href="' . rocI18nPath($lang, rocGpPath($g)) . '">' . rocH($g['name']) . '</a><small>' . (int)$g['y'] . '</small></li>'; };
    $sorted = array_values($games);
    usort($sorted, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    $m  = $mainOpen . '<div class="max-w-6xl mx-auto py-6 space-y-6" lang="' . $lang . '">' . rocGpCrumbHtml($crumbs);
    $m .= '<header class="gp-hero"><div><h1>' . rocH($t('hub_h1', $nf($n))) . '</h1><p>' . rocH($t('hub_p')) . '</p></div></header>';
    $m .= '<section class="gp-card"><h2>' . rocH($t('pop')) . '</h2><ul class="gp-list">' . implode('', array_map($item, array_slice(array_values($games), 0, 60))) . '</ul></section>';
    $m .= '<section class="gp-card"><h2>' . rocH($t('all')) . '</h2><ul class="gp-list">' . implode('', array_map($item, $sorted)) . '</ul></section>';
    $m .= '<p class="gp-src">' . rocH($t('langs')) . ': ' . $langLinks('/can-i-run/') . '</p></div>';
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $updated);
}

/* ------------------------------------------------------------- one game */
$g = $games[$want] ?? null;
if (!$g) rocNotFound($ROOT);
$E = (array)($EDITS[$want] ?? []);
$name = $g['name'];
$enPath = rocGpPath($g);
$path = rocI18nPath($lang, $enPath);
$url = ROC_PUBLIC_URL . $path;
$min = (array)($g['min'] ?? []); $rec = is_array($g['rec'] ?? null) ? $g['rec'] : null;
$rMin = (array)($g['req']['min'] ?? []); $rRec = (array)($g['req']['rec'] ?? $rMin);
$clean = function ($s) { return trim((string)preg_replace('/\s+/u', ' ', strip_tags((string)$s))); };
$gb = function ($v) use ($lang) { return is_numeric($v) && $v > 0 ? rtrim(rtrim(number_format((float)$v, 1), '0'), '.') . ($lang === 'ru' ? ' ГБ' : ($lang === 'fr' ? ' Go' : ' GB')) : ''; };
$gpuMin = rocGpPartsFor('gpu', isset($rMin['gpu']) ? (float)$rMin['gpu'] : null);
$gpuRec = rocGpPartsFor('gpu', isset($rRec['gpu']) ? (float)$rRec['gpu'] : null);
$igpu = $gpuMin && strpos($gpuMin[0], 'integrated') !== false;
$storage = (string)preg_replace('/\s*available space/i', '', $clean($rec['storage'] ?? '') ?: $clean($min['storage'] ?? ''));
$year = (int)($g['y'] ?? 0);
$tY = gmdate('Y');

// The same three PCs and rules as the English page.
$gpus = rocGpRefs('gpu'); $cpus = rocGpRefs('cpu');
$common = [['GeForce GTX 1650', 'Intel Core i5-8400', 16, 'GTX 1650 + Core i5-8400 + 16 GB'], ['GeForce RTX 3060', 'AMD Ryzen 5 5600', 16, 'RTX 3060 + Ryzen 5 5600 + 16 GB'],
           ['Intel UHD / Iris Xe (integrated)', 'Intel Core i5-8400', 8, $t('pc_laptop')]];
$tier = function (int $p) use ($t) { return $p >= 90 ? $t('t_high') : ($p >= 70 ? $t('t_mid') : ($p > 40 ? $t('t_low') : $t('t_no'))); };
$runs = [];
foreach ($common as [$gn, $cn, $ram, $lbl]) if (isset($gpus[$gn], $cpus[$cn])) $runs[] = [$lbl, (int)rocAgentCheck($g, $gpus[$gn], $cpus[$cn], (float)$ram)['percent']];
$igpuP = 0;
foreach ($runs as $r) if ($r[0] === $t('pc_laptop')) $igpuP = $r[1];

$minSum = $gpuMin ? ($igpu ? 'Intel UHD / Iris Xe' : rocGpCardShort($gpuMin[0])) . ($gb($rMin['ram'] ?? null) ? ', ' . $gb($rMin['ram']) . ' RAM' : '') : '';
$title = '';
foreach ([$t('title', $name, $tY), $t('title2', $name, $tY), $t('title3', $name), $name] as $try) if (mb_strlen($try) <= 62) { $title = $try; break; }
if ($title === '') $title = $t('title3', $name);
$desc = '';
foreach ([$minSum !== '' ? $t('desc', $name, $minSum) : '', $t('desc2', $name)] as $try) if ($try !== '' && mb_strlen($try) <= 160) { $desc = $try; break; }
if ($desc === '') $desc = $t('desc2', $name);

// FAQ, from the same numbers as the English page.
$list = function (array $b) use ($clean) { return implode(', ', array_filter([$clean($b['os'] ?? ''), $clean($b['cpu'] ?? ''), $clean($b['ram'] ?? ''), $clean($b['gpu'] ?? ''), $clean($b['storage'] ?? '')])); };
$faqs = [];
if ($list($min) !== '') $faqs[] = [$t('q_min', $name), $t('a_min', $list($min))];
if ($rec && $list($rec) !== '') $faqs[] = [$t('q_rec', $name), $t('a_rec', $list($rec))];
if ($gpuMin && !$igpu) $faqs[] = [$t('q_gpu', $name), $gpuRec && $gpuRec[0] !== $gpuMin[0] ? $t('a_gpu', rocGpCardShort($gpuMin[0]), rocGpCardShort($gpuRec[0])) : $t('a_gpu1', rocGpCardShort($gpuMin[0]))];
$rmn = (float)($rMin['ram'] ?? 0); $rrc = (float)($rRec['ram'] ?? $rmn);
if ($rmn > 0) $faqs[] = [$t('q_8', $name), $rmn > 8 ? $t('a_8no', $name, $gb($rmn)) : ($rrc > 8 ? $t('a_8min', $gb($rrc)) : $t('a_8yes'))];
if ($storage !== '') $faqs[] = [$t('q_size', $name), $t('a_size', $name, $storage)];
$faqs[] = [$t('q_igpu', $name), $igpuP >= 70 ? $t('a_igpu_yes') : ($igpuP > 40 ? $t('a_igpu_low') : $t('a_igpu_no'))];

$crumbs = [[$t('home'), '/'], [$t('cir'), rocI18nPath($lang, '/can-i-run/')], [$name, $path]];
$ld = [rocGpCrumbs($crumbs),
    ['@context' => 'https://schema.org', '@type' => 'WebPage', '@id' => $url, 'url' => $url, 'name' => $t('h1', $name), 'description' => $desc, 'inLanguage' => $lang,
     'dateModified' => gmdate('c', rocGpPageTime($g, $E)), 'isPartOf' => ['@type' => 'WebSite', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'],
     'about' => array_filter(['@type' => 'VideoGame', 'name' => $name, 'gamePlatform' => 'PC', 'sameAs' => (string)($g['u'] ?? '') ?: null])],
    ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'inLanguage' => $lang, 'mainEntity' => array_map(function ($f) {
        return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]];
    }, $faqs)]];
$h = rocGpHead($title, $desc, $url, $ld, rocGpThin($g, $E) ? 'noindex, follow' : 'index, follow, max-image-preview:large', '', $t('h1', $name)) . rocI18nAlternates($enPath);

$m  = $mainOpen . '<article class="max-w-4xl mx-auto py-6 space-y-6" lang="' . $lang . '">' . rocGpCrumbHtml($crumbs);
$pic = empty($E['no_picture']) ? rocGpSteamImg((int)$g['appid']) : '';
$m .= '<header class="gp-hero' . ($pic !== '' ? ' pic' : '') . '"><div><h1>' . rocH($t('h1', $name)) . '</h1><p>' . rocH($t('intro', $name . ($year ? ' (' . $year . ')' : ''))) . '</p>'
    . (!empty($g['soon']) ? '<p style="background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.35);border-radius:.8rem;padding:.55rem .8rem;font-weight:600">' . rocH($t('soon', (string)$g['soon'])) . '</p>' : '')
    . '</div>' . ($pic !== '' ? '<figure class="gp-pic"><img src="' . rocH($pic) . '" alt="' . rocH($name) . '" width="460" height="215" fetchpriority="high" onerror="this.closest(\'figure\').remove()"></figure>' : '') . '</header>';
$qa = '';
if ($gpuMin) $qa .= '<li>' . rocH($igpu ? $t('min_gpu_igpu') : $t('min_gpu', rocGpCardShort($gpuMin[0]))) . '</li>';
if ($gpuRec && (!$gpuMin || $gpuRec[0] !== $gpuMin[0])) $qa .= '<li>' . rocH($t('rec_gpu', rocGpCardShort($gpuRec[0]))) . '</li>';
if ($gb($rmn)) $qa .= '<li>' . rocH($t('ram_min', $gb($rmn))) . ($rrc > $rmn ? ' · ' . rocH($t('ram_rec', $gb($rrc))) : '') . '</li>';
if ($storage !== '') $qa .= '<li>' . rocH($t('size', $storage)) . '</li>';
if ($qa !== '') $m .= '<section class="gp-card"><h2>' . rocH($t('quick')) . '</h2><ul class="gp-qa">' . $qa . '</ul></section>';
if ($runs) {
    $m .= '<section class="gp-card"><h2>' . rocH($t('pcs')) . '</h2><div class="gp-wrap"><table class="gp-table"><tbody>';
    foreach ($runs as [$lbl, $p]) $m .= '<tr><th scope="row">' . rocH($lbl) . '</th><td><b>' . $p . '%</b></td><td>' . rocH($tier($p)) . '</td></tr>';
    $m .= '</tbody></table></div></section>';
}
$row = function (string $label, $a, $b) use ($clean, $rec, $t) {
    $a = $clean($a); $b = $clean($b);
    if ($a === '' && $b === '') return '';
    return '<tr><th scope="row">' . rocH($label) . '</th><td>' . ($a !== '' ? rocH($a) : rocH($t('not_listed'))) . '</td>' . ($rec ? '<td>' . ($b !== '' ? rocH($b) : rocH($t('not_listed'))) . '</td>' : '') . '</tr>';
};
$m .= '<section class="gp-card"><h2>' . rocH($t('req', $name)) . '</h2><div class="gp-wrap"><table class="gp-table"><thead><tr><th>' . rocH($t('part')) . '</th><th>' . rocH($t('min')) . '</th>' . ($rec ? '<th>' . rocH($t('rec')) . '</th>' : '') . '</tr></thead><tbody>'
    . $row($t('l_os'), $min['os'] ?? '', $rec['os'] ?? '') . $row($t('l_cpu'), $min['cpu'] ?? '', $rec['cpu'] ?? '') . $row($t('l_ram'), $min['ram'] ?? '', $rec['ram'] ?? '')
    . $row($t('l_gpu'), $min['gpu'] ?? '', $rec['gpu'] ?? '') . $row($t('l_storage'), $min['storage'] ?? '', $rec['storage'] ?? '')
    . '</tbody></table></div><p class="gp-src" style="margin-top:.5rem">' . rocH($t('req_note')) . '</p></section>';
$m .= rocAdUnit('list');
$m .= '<section class="gp-card gp-faq"><h2>' . rocH($t('faq', $name)) . '</h2>';
foreach ($faqs as $i => $f) $m .= '<details' . ($i === 0 ? ' open' : '') . '><summary>' . rocH($f[0]) . '</summary><div class="a">' . rocH($f[1]) . '</div></details>';
$m .= '</section>';
$m .= '<section class="gp-card"><h2>' . rocH($t('full')) . '</h2><p>' . rocH($t('full_p')) . '</p><div class="gp-btns" style="margin-top:.6rem"><a class="gp-btn p" hreflang="en" href="' . $enPath . '">' . rocH($t('full_btn')) . ' &rarr;</a></div></section>';
// Similar games: the same first genre, nearest in popularity.
$genre = trim(explode(',', (string)($g['g'] ?? ''))[0]);
$sim = [];
foreach ($games as $x) if ($x['slug'] !== $g['slug'] && $genre !== '' && strpos((string)($x['g'] ?? ''), $genre) === 0) $sim[] = $x;
usort($sim, function ($a, $b) use ($g) { return abs((int)$a['rank'] - (int)$g['rank']) <=> abs((int)$b['rank'] - (int)$g['rank']); });
if ($sim) $m .= '<section class="gp-card"><h2>' . rocH($t('similar')) . '</h2><ul class="gp-list">'
    . implode('', array_map(function ($x) use ($lang) { return '<li><a href="' . rocI18nPath($lang, rocGpPath($x)) . '">' . rocH($x['name']) . '</a></li>'; }, array_slice($sim, 0, 8))) . '</ul></section>';
$m .= '<p class="gp-src">' . rocH($t('langs')) . ': ' . $langLinks($enPath) . '</p>';
$m .= '<p class="gp-src">' . rocH($t('src', gmdate('m/Y', $updated))) . '</p>';
$m .= '</article>';
rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, rocGpPageTime($g, $E));
