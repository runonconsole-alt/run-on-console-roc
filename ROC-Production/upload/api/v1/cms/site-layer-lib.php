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
                           'bg' => '#e4ff1a', 'fg' => '#111111', 'dismissible' => true, 'ends_at' => ''],
        'code' => ['css' => '', 'head_html' => '', 'body_top_html' => '', 'body_end_html' => ''],
        'meta' => [],
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
    return '.roc-ann{position:relative;z-index:60;background:var(--roc-ann-bg);color:var(--roc-ann-fg);'
        . 'font:500 13px/1.45 Inter,system-ui,-apple-system,"Segoe UI",sans-serif;padding:10px 46px 10px 16px;text-align:center}'
        . '.roc-ann[hidden]{display:none}.roc-ann p{margin:0}.roc-ann strong{font-weight:800}'
        . '.roc-ann a{color:inherit;text-decoration:underline;text-underline-offset:2px;font-weight:700;margin-left:6px;white-space:nowrap}'
        . '.roc-ann-x{position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:0;color:inherit;'
        . 'font-size:22px;line-height:1;cursor:pointer;padding:6px 10px;border-radius:8px}.roc-ann-x:hover{background:rgba(0,0,0,.08)}';
}

function rocLayerAnnouncementHtml(array $a): string {
    $title = trim((string)$a['title']); $text = trim((string)$a['text']);
    $url = trim((string)$a['link_url']); $label = trim((string)$a['link_text']) ?: 'Learn more';
    $id = substr(md5($title . '|' . $text . '|' . $url), 0, 10);
    $end = strtotime((string)($a['ends_at'] ?? '')) ?: 0;
    $p = (trim((string)$a['icon']) !== '' ? '<span aria-hidden="true">' . rocLayerH((string)$a['icon']) . '</span> ' : '')
        . ($title !== '' ? '<strong>' . rocLayerH($title) . '</strong>' : '')
        . ($title !== '' && $text !== '' ? ' — ' : '')
        . rocLayerH($text)
        . ($url !== '' ? '<a href="' . rocLayerH($url) . '"' . (preg_match('#^https?://#i', $url) && stripos($url, 'runonconsole.com') === false ? ' target="_blank" rel="noopener"' : '') . '>' . rocLayerH($label) . '</a>' : '');
    $h = '<div class="roc-ann" id="roc-ann" data-ann="' . $id . '" data-end="' . ($end * 1000) . '" role="region" aria-label="Announcement"'
        . ' style="--roc-ann-bg:' . rocLayerH((string)$a['bg']) . ';--roc-ann-fg:' . rocLayerH((string)$a['fg']) . '"><p>' . $p . '</p>';
    if (!empty($a['dismissible'])) {
        $h .= '<button type="button" class="roc-ann-x" aria-label="Close announcement" onclick="var b=this.parentNode;b.hidden=true;try{localStorage.setItem(\'roc-ann-x\',b.getAttribute(\'data-ann\'))}catch(e){}">&times;</button>';
    }
    $h .= '</div><script>(function(){var b=document.getElementById("roc-ann");if(!b)return;var e=+b.getAttribute("data-end");'
        . 'try{if((e&&Date.now()>e)||localStorage.getItem("roc-ann-x")===b.getAttribute("data-ann"))b.hidden=true}catch(x){}})();</script>';
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
    $css = ($ann ? rocLayerAnnouncementCss() : '') . trim((string)$L['code']['css']);
    if ($css !== '') $head .= '<style id="roc-site-css">' . str_ireplace('</style', '<\/style', $css) . '</style>';
    if (trim((string)$L['code']['head_html']) !== '') $head .= "\n" . $L['code']['head_html'] . "\n";
    $top = ($ann ? rocLayerAnnouncementHtml($L['announcement']) : '') . (string)$L['code']['body_top_html'];
    $end = (string)$L['code']['body_end_html'];

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
    foreach (['announcement' => 'site_announcement', 'code' => 'site_code', 'meta' => 'meta_overrides'] as $k => $key) {
        $v = rocLayerSettingGet($pdo, $key);
        $d = $v !== null ? json_decode($v, true) : null;
        if (is_array($d)) $L[$k] = $k === 'meta' ? $d : array_merge(rocLayerDefaults()[$k], $d);
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
    $keys = ['announcement' => 'site_announcement', 'code' => 'site_code', 'meta' => 'meta_overrides'];
    rocLayerSettingSet($pdo, $keys[$part], (string)json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $userId);
    $L = rocLayerFromDb($pdo, $root);
    $L[$part] = $part === 'meta' ? $value : array_merge(rocLayerDefaults()[$part], $value);
    $L['updated'] = gmdate('c');
    $file = $root . ROC_LAYER_FILE;
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
    $tmp = $file . '.tmp' . getmypid();
    file_put_contents($tmp, json_encode($L, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    rename($tmp, $file);
    return rocLayerApplyFiles($root, $L, $onlyPaths);
}
