<?php
/**
 * Run On Console — things the CMS adds to every page of the website:
 *
 *   1. Announcement bar   a strip above the header (sale, offer, news, maintenance)
 *   2. Site code          custom CSS, code in <head>, HTML at the top and bottom of <body>
 *   3. Meta overrides     title and description for pages that are not in the database
 *                         (home, about, contact, product groups, list pages, …)
 *
 * Everything is published to public_html/cms-templates/site-layer.json and applied on
 * the server, so visitors and search engines get it in the HTML itself:
 *   - pages rendered by PHP (blogs, products, platforms, CMS pages) apply it in
 *     rocSendHtml() (blog-render.php) on every request;
 *   - built pages (home, about, contact, …) are rewritten in place when the CMS saves,
 *     and again after every deploy (cli-refresh-templates.php --apply).
 *
 * Each addition sits between <!--roc-layer-X--> markers and is removed before it is
 * added again, so applying twice changes nothing. A built page whose title was
 * overridden keeps its original title and description in the marker block, so
 * removing an override puts the original back.
 *
 * This file only defines functions; it is included by blog-render.php (no database
 * needed) and by the CMS endpoints (which also store the settings in cms_settings).
 */

if (defined('ROC_SITE_LAYER_LIB')) return;
define('ROC_SITE_LAYER_LIB', 1);

const ROC_LAYER_FILE = '/cms-templates/site-layer.json';
/** Where each part is stored (cms_settings.setting_key). */
const ROC_LAYER_KEYS = ['announcement' => 'site_announcement', 'code' => 'site_code', 'meta' => 'meta_overrides', 'code_pages' => 'site_code_pages'];
/** Built pages live in these folders; the others are served by PHP or are not pages. */
const ROC_LAYER_SKIP_DIRS = ['api', 'cms', 'cms-templates', 'uploads', 'images', 'assets', 'fonts', 'products',
                             'categories', 'blogs', 'gaming-platforms', 'partnerships', 'policy', 'node_modules', 'cgi-bin', '.well-known'];
/** Pages served by PHP whose title and description can only be set here. */
const ROC_LAYER_PHP_PAGES = [
    '/blogs/'                    => 'Blogs: list of all posts',
    '/products/'                 => 'Products: all products',
    '/products/pc-hardware/'     => 'Products: PC Hardware group',
    '/products/gaming-hardware/' => 'Products: Gaming Hardware group',
    '/gaming-platforms/'         => 'Gaming platforms: all platforms',
];

function rocLayerDefaults(): array {
    return [
        'announcement' => ['enabled' => false, 'icon' => '📣', 'title' => '', 'text' => '', 'link_url' => '', 'link_text' => '',
                           'bg' => '#e4ff1a', 'fg' => '#111111', 'dismissible' => true, 'ends_at' => '', 'scroll' => true, 'speed' => 3],
        'code' => ['css' => '', 'head_html' => '', 'body_top_html' => '', 'body_end_html' => ''],
        'meta' => [],
        'code_pages' => [],
        'updated' => '',
    ];
}

/** Published settings (cached per request). */
function rocLayerLoad(string $root): array {
    static $cache = [];
    if (isset($cache[$root])) return $cache[$root];
    $d = rocLayerDefaults();
    $f = $root . ROC_LAYER_FILE;
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    if (is_array($j)) {
        $d['announcement'] = array_merge($d['announcement'], (array)($j['announcement'] ?? []));
        $d['code'] = array_merge($d['code'], (array)($j['code'] ?? []));
        $d['meta'] = (array)($j['meta'] ?? []);
        $d['code_pages'] = (array)($j['code_pages'] ?? []);
        $d['updated'] = (string)($j['updated'] ?? '');
    }
    return $cache[$root] = $d;
}

function rocLayerH(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

/* --------------------------------------------------------------- pieces */

function rocLayerAnnouncementOn(array $a): bool {
    if (empty($a['enabled']) || (trim((string)$a['title']) === '' && trim((string)$a['text']) === '')) return false;
    $end = trim((string)($a['ends_at'] ?? ''));
    return $end === '' || strtotime($end) === false || strtotime($end) > time();
}

function rocLayerAnnouncementCss(): string {
    // Sticky at the top (stays visible while scrolling); the site header (sticky top-0)
    // moves down by the bar's height (--roc-ann-h, set by the bar's script).
    return '.roc-ann{position:sticky;top:0;z-index:1001;background:var(--roc-ann-bg);color:var(--roc-ann-fg);'
        . 'font:500 13px/1.45 Inter,system-ui,-apple-system,"Segoe UI",sans-serif;padding:10px 46px 10px 16px;text-align:center;overflow:hidden}'
        . '.roc-ann[hidden]{display:none}.roc-ann p{margin:0}.roc-ann strong{font-weight:800}'
        . 'header.sticky{top:var(--roc-ann-h,0px)!important}'
        // overflow-x:hidden on html/body stops position:sticky from working; clip keeps the same look.
        . 'html,body{overflow-x:clip!important}'
        . '.roc-ann a{color:inherit;text-decoration:underline;text-underline-offset:2px;font-weight:700;margin-left:6px;white-space:nowrap}'
        . '.roc-ann-x{position:absolute;right:6px;top:50%;transform:translateY(-50%);background:var(--roc-ann-bg);border:0;color:inherit;'
        . 'font-size:22px;line-height:1;cursor:pointer;padding:6px 10px;border-radius:8px;z-index:1}.roc-ann-x:hover{filter:brightness(.92)}'
        // Running text: enters from the right, leaves on the left, starts again. Pauses under the mouse.
        . '.roc-ann-run{text-align:left;white-space:nowrap}'
        . '.roc-ann-run p{display:inline-block;padding-left:100%;animation:roc-ann-run var(--roc-ann-dur,20s) linear infinite}'
        . '.roc-ann-run:hover p{animation-play-state:paused}'
        . '@keyframes roc-ann-run{from{transform:translateX(0)}to{transform:translateX(-100%)}}'
        . '@media (prefers-reduced-motion:reduce){.roc-ann-run{text-align:center;white-space:normal}.roc-ann-run p{padding-left:0;animation:none}}';
}

function rocLayerAnnouncementHtml(array $a): string {
    $title = trim((string)$a['title']); $text = trim((string)$a['text']);
    $url = trim((string)$a['link_url']); $label = trim((string)$a['link_text']) ?: 'Learn more';
    $id = substr(md5($title . '|' . $text . '|' . $url), 0, 10);
    $end = strtotime((string)($a['ends_at'] ?? '')) ?: 0;
    $run = !empty($a['scroll']);
    // Speed: 1 slow … 5 fast. Longer messages get more time so they read at the same pace.
    $speed = max(1, min(5, (int)($a['speed'] ?? 3)));
    $dur = (int)round((12 + mb_strlen($title . $text . ($url !== '' ? $label : '')) * 0.18) * (1.6 - $speed * 0.2));
    $p = (trim((string)$a['icon']) !== '' ? '<span aria-hidden="true">' . rocLayerH((string)$a['icon']) . '</span> ' : '')
        . ($title !== '' ? '<strong>' . rocLayerH($title) . '</strong>' : '')
        . ($title !== '' && $text !== '' ? ' — ' : '')
        . rocLayerH($text)
        . ($url !== '' ? '<a href="' . rocLayerH($url) . '"' . (preg_match('#^https?://#i', $url) && stripos($url, 'runonconsole.com') === false ? ' target="_blank" rel="noopener"' : '') . '>' . rocLayerH($label) . '</a>' : '');
    $h = '<div class="roc-ann' . ($run ? ' roc-ann-run' : '') . '" id="roc-ann" data-ann="' . $id . '" data-end="' . ($end * 1000) . '" role="region" aria-label="Announcement"'
        . ' style="--roc-ann-bg:' . rocLayerH((string)$a['bg']) . ';--roc-ann-fg:' . rocLayerH((string)$a['fg']) . ($run ? ';--roc-ann-dur:' . $dur . 's' : '') . '"><p>' . $p . '</p>';
    if (!empty($a['dismissible'])) {
        $h .= '<button type="button" class="roc-ann-x" aria-label="Close announcement" onclick="var b=this.parentNode;b.hidden=true;document.documentElement.style.setProperty(\'--roc-ann-h\',\'0px\');try{localStorage.setItem(\'roc-ann-x\',b.getAttribute(\'data-ann\'))}catch(e){}">&times;</button>';
    }
    // Hide when closed or expired; otherwise tell the sticky header how far to move down.
    $h .= '</div><script>(function(){var b=document.getElementById("roc-ann");if(!b)return;var e=+b.getAttribute("data-end"),r=document.documentElement;'
        . 'try{if((e&&Date.now()>e)||localStorage.getItem("roc-ann-x")===b.getAttribute("data-ann"))b.hidden=true}catch(x){}'
        . 'function h(){r.style.setProperty("--roc-ann-h",(b.hidden?0:b.offsetHeight)+"px")}h();addEventListener("resize",h);addEventListener("load",h)})();</script>';
    return $h;
}

/* ---------------------------------------------------------------- apply */

/** Removes earlier additions; returns [html, originals|null]. */
function rocLayerStrip(string $html): array {
    $orig = null;
    if (preg_match('#<!--roc-orig:([A-Za-z0-9+/=]+)-->#', $html, $m)) {
        $o = json_decode((string)base64_decode($m[1]), true);
        if (is_array($o)) $orig = $o;
    }
    $html = (string)preg_replace('#<!--roc-layer-(head|top|end)-->.*?<!--/roc-layer-\1-->#s', '', $html);
    if ($orig) $html = rocLayerSetMeta($html, (string)($orig['t'] ?? ''), (string)($orig['d'] ?? ''));
    return [$html, $orig];
}

function rocLayerGetMeta(string $html): array {
    $t = preg_match('#<title>(.*?)</title>#is', $html, $m) ? html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
    $d = preg_match('#<meta\s+name="description"\s+content="([^"]*)"#i', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
    return [$t, $d];
}

/** Puts a title and/or description into <title> and the description, Open Graph and X tags. */
function rocLayerSetMeta(string $html, string $title, string $desc): string {
    $rep = function (string $html, string $attr, string $name, string $value): string {
        return (string)preg_replace_callback('#(<meta\s+' . $attr . '="' . preg_quote($name, '#') . '"\s+content=")[^"]*(")#i',
            function ($m) use ($value) { return $m[1] . rocLayerH($value) . $m[2]; }, $html, 1);
    };
    if ($title !== '') {
        $html = (string)preg_replace_callback('#<title>.*?</title>#is', function () use ($title) { return '<title>' . rocLayerH($title) . '</title>'; }, $html, 1);
        foreach ([['name', 'title'], ['property', 'og:title'], ['name', 'twitter:title']] as [$a, $n]) $html = $rep($html, $a, $n, $title);
    }
    if ($desc !== '') {
        foreach ([['name', 'description'], ['property', 'og:description'], ['name', 'twitter:description']] as [$a, $n]) $html = $rep($html, $a, $n, $desc);
    }
    return $html;
}

/**
 * Adds the announcement, site code and (when $metaOn) the meta override for $path.
 * Safe to run on HTML that already has them.
 */
function rocLayerApply(string $html, string $path, array $L, bool $metaOn = true): string {
    if (stripos($html, '</head>') === false || !preg_match('#<body\b[^>]*>#i', $html)) return $html;
    [$html, $orig] = rocLayerStrip($html);

    $head = '';
    $ov = $metaOn ? ($L['meta'][$path] ?? null) : null;
    if (is_array($ov) && (trim((string)($ov['title'] ?? '')) !== '' || trim((string)($ov['description'] ?? '')) !== '')) {
        [$t0, $d0] = rocLayerGetMeta($html);
        $head .= '<!--roc-orig:' . base64_encode((string)json_encode(['t' => $t0, 'd' => $d0])) . '-->'
            . '<meta name="roc-meta-override" content="1" />';
        $html = rocLayerSetMeta($html, trim((string)($ov['title'] ?? '')), trim((string)($ov['description'] ?? '')));
    }
    $ann = rocLayerAnnouncementOn($L['announcement']);
    // Whole-site code first, then code for page groups (/products/*) and for this page.
    $codes = [$L['code']];
    foreach ((array)($L['code_pages'] ?? []) as $scope => $c) if (is_array($c) && rocLayerScopeMatch((string)$scope, $path)) $codes[] = $c;
    $pick = function (string $k) use ($codes) { $o = ''; foreach ($codes as $c) $o .= (string)($c[$k] ?? ''); return $o; };
    $css = ($ann ? rocLayerAnnouncementCss() : '');
    foreach ($codes as $c) if (trim((string)($c['css'] ?? '')) !== '') $css .= ($css !== '' ? "\n" : '') . trim((string)$c['css']);
    if ($css !== '') $head .= '<style id="roc-site-css">' . str_ireplace('</style', '<\/style', $css) . '</style>';
    if (trim($pick('head_html')) !== '') $head .= "\n" . $pick('head_html') . "\n";
    $top = ($ann ? rocLayerAnnouncementHtml($L['announcement']) : '') . $pick('body_top_html');
    $end = $pick('body_end_html');

    if ($head !== '') {
        $at = stripos($html, '<title');
        if ($at === false || $at > stripos($html, '</head>')) $at = stripos($html, '</head>');
        $html = substr($html, 0, $at) . '<!--roc-layer-head-->' . $head . '<!--/roc-layer-head-->' . substr($html, $at);
    }
    if (trim($top) !== '') {
        $html = (string)preg_replace_callback('#<body\b[^>]*>#i', function ($m) use ($top) {
            return $m[0] . '<!--roc-layer-top-->' . $top . '<!--/roc-layer-top-->';
        }, $html, 1);
    }
    if (trim($end) !== '') {
        $at = strripos($html, '</body>');
        if ($at !== false) $html = substr($html, 0, $at) . '<!--roc-layer-end-->' . $end . '<!--/roc-layer-end-->' . substr($html, $at);
    }
    return $html;
}

/** Scope of page code: an exact address (/about/) or a group ending in * (/products/*). */
function rocLayerScopeMatch(string $scope, string $path): bool {
    if ($scope === '' || $scope === '*') return false;          // whole-site code lives in $L['code']
    if (substr($scope, -1) === '*') {
        $pre = substr($scope, 0, -1);
        return strpos($path, $pre) === 0 && $path !== $pre;     // /products/* = pages below /products/, not the list itself
    }
    return $scope === $path;
}

/* ---------------------------------------------------------- built pages */

/** Built pages served as files: [path => file]. */
function rocLayerStaticFiles(string $root): array {
    $out = [];
    if (is_file($root . '/index.html')) $out['/'] = $root . '/index.html';
    $walk = function (string $dir, string $url, int $depth) use (&$walk, &$out, $root) {
        foreach (scandir($dir) ?: [] as $n) {
            if ($n[0] === '.' || !is_dir($dir . '/' . $n)) continue;
            if ($depth === 0 && in_array($n, ROC_LAYER_SKIP_DIRS, true)) continue;
            $p = $url . $n . '/';
            if (is_file($dir . '/' . $n . '/index.html')) $out[$p] = $dir . '/' . $n . '/index.html';
            if ($depth < 2) $walk($dir . '/' . $n, $p, $depth + 1);
        }
    };
    $walk($root, '/', 0);
    if (is_file($root . '/404.html')) $out['/404'] = $root . '/404.html';
    return $out;
}

/** Rewrites built pages (all, or only $paths). Returns the number of files changed. */
function rocLayerApplyFiles(string $root, array $L, ?array $paths = null, bool $write = true): int {
    $n = 0;
    foreach (rocLayerStaticFiles($root) as $path => $file) {
        if ($paths !== null && !in_array($path, $paths, true)) continue;
        $html = (string)file_get_contents($file);
        $new = rocLayerApply($html, $path, $L, $path !== '/404');
        if ($new === $html) continue;
        $n++;
        if (!$write) continue;
        $tmp = $file . '.roc-tmp' . getmypid();
        if (file_put_contents($tmp, $new) !== false) rename($tmp, $file);
    }
    return $n;
}

/* ------------------------------------------------------ CMS (database) */

function rocLayerSettingGet(PDO $pdo, string $key): ?string {
    try {
        $st = $pdo->prepare('SELECT setting_value FROM cms_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([$key]);
        $v = $st->fetchColumn();
    } catch (\Throwable $e) { return null; }
    return ($v === false || $v === null) ? null : (string)$v;
}

function rocLayerSettingSet(PDO $pdo, string $key, string $value, ?int $userId): void {
    $st = $pdo->prepare('SELECT COUNT(*) FROM cms_settings WHERE setting_key = ?');
    $st->execute([$key]);
    if ((int)$st->fetchColumn() > 0) {
        $pdo->prepare('UPDATE cms_settings SET setting_value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?')
            ->execute([$value, $userId, $key]);
    } else {
        $pdo->prepare('INSERT INTO cms_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)')
            ->execute([$key, $value, $userId]);
    }
}

/** Settings as stored in the database (falls back to the published file). */
function rocLayerFromDb(PDO $pdo, string $root): array {
    $L = rocLayerLoad($root);
    foreach (ROC_LAYER_KEYS as $k => $key) {
        $v = rocLayerSettingGet($pdo, $key);
        $d = $v !== null ? json_decode($v, true) : null;
        if (is_array($d)) $L[$k] = in_array($k, ['meta', 'code_pages'], true) ? $d : array_merge(rocLayerDefaults()[$k], $d);
    }
    return $L;
}

/* -------------------------------------------------------------- history */
/*
 * Every change made from the Metas, Announcement bar and Template & code tabs is
 * kept for 30 days in /home2/runoncon/roc-cms-history/ (outside the website), one
 * JSON file per change: {id, kind, key, label, before, after, user, at}. Nothing
 * is ever lost: the CMS lists them and can put any earlier version back.
 */
const ROC_HISTORY_DAYS = 30;

function rocHistDir(string $root): string { return dirname($root) . '/roc-cms-history'; }

function rocHistAdd(string $root, string $kind, string $key, string $label, $before, $after, ?array $session): string {
    if ($before === $after) return '';
    $dir = rocHistDir($root);
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $row = ['id' => $id, 'kind' => $kind, 'key' => $key, 'label' => $label, 'before' => $before, 'after' => $after,
            'user' => (string)($session['username'] ?? ($session['user_id'] ?? '')), 'at' => gmdate('c')];
    @file_put_contents($dir . '/' . $id . '.json', json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    // Older than 30 days: removed.
    foreach (glob($dir . '/*.json') ?: [] as $f) if (filemtime($f) < time() - ROC_HISTORY_DAYS * 86400) @unlink($f);
    return $id;
}

/** Newest first; optionally only one kind and/or key. */
function rocHistList(string $root, ?string $kind = null, ?string $key = null, int $limit = 200): array {
    $files = glob(rocHistDir($root) . '/*.json') ?: [];
    rsort($files);
    $out = [];
    foreach ($files as $f) {
        if (filemtime($f) < time() - ROC_HISTORY_DAYS * 86400) continue;
        $r = json_decode((string)file_get_contents($f), true);
        if (!is_array($r) || ($kind !== null && $r['kind'] !== $kind) || ($key !== null && $r['key'] !== $key)) continue;
        $out[] = $r;
        if (count($out) >= $limit) break;
    }
    return $out;
}

function rocHistGet(string $root, string $id): ?array {
    if (!preg_match('/^\d{8}-\d{6}-[a-f0-9]{8}$/', $id)) return null;
    $f = rocHistDir($root) . '/' . $id . '.json';
    $r = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($r) ? $r : null;
}

/** Saves one part, publishes site-layer.json and rewrites the built pages. Returns files changed. */
function rocLayerSave(PDO $pdo, string $root, string $part, array $value, ?int $userId, ?array $onlyPaths = null): int {
    rocLayerSettingSet($pdo, ROC_LAYER_KEYS[$part], (string)json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $userId);
    $L = rocLayerFromDb($pdo, $root);
    $L[$part] = in_array($part, ['meta', 'code_pages'], true) ? $value : array_merge(rocLayerDefaults()[$part], $value);
    $L['updated'] = gmdate('c');
    $file = $root . ROC_LAYER_FILE;
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
    $tmp = $file . '.tmp' . getmypid();
    file_put_contents($tmp, json_encode($L, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    rename($tmp, $file);
    return rocLayerApplyFiles($root, $L, $onlyPaths);
}
