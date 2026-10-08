<?php
/**
 * Run On Console (ROC) — CMS > Settings (administrators)
 *
 * GET  /api/v1/cms/site-settings.php                      everything below
 * GET  ?history=tracking|noindex|sitemap|robots|llms|redirects   earlier versions (30 days)
 * POST {part, value}                                      save one part
 * POST {action:'restore', id, which:'before'|'after'}     put an earlier version back
 *
 * Parts
 *   tracking   {gtm, ga4, clarity, gsc, bing, pinterest, facebook, yandex, gmb_url, gmb_place_id}
 *              added to every page on the server (site-layer-lib.php)
 *   noindex    ["/path/", "/section/*"]   pages get robots noindex and leave the sitemaps
 *   sitemap    {exclude: [paths], extra: [{path, title}]}
 *   robots     text of public_html/robots.txt
 *   llms       text of public_html/llms.txt
 *   redirects  [{from, to, type: 301|302|410}]  written to .htaccess between
 *              "# ROC cms-redirects BEGIN/END"; the home page is checked after saving and
 *              the previous .htaccess is put back if the site stops answering.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/site-layer-lib.php';

$session = requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');
$ROOT = realpath(__DIR__ . '/../../..') ?: dirname(__DIR__, 3);

const SS_PATH_RE = '#^/[a-z0-9/_.\-]*\*?$#i';
const SS_RD_BEGIN = '  # ROC cms-redirects BEGIN (CMS > Settings > Redirects)';
const SS_RD_END = '  # ROC cms-redirects END';

function ssOut(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit(); }
function ssFile(string $ROOT, string $name): string { return (string)@file_get_contents($ROOT . '/' . $name); }
function ssWrite(string $file, string $text): bool {
    $tmp = $file . '.roc-tmp' . getmypid();
    if (file_put_contents($tmp, $text) === false) return false;
    return rename($tmp, $file);
}
function ssRedirects(PDO $pdo): array {
    $v = rocLayerSettingGet($pdo, 'redirect_rules');
    $d = $v !== null ? json_decode($v, true) : null;
    return is_array($d) ? $d : [];
}
/** Current value of one part. */
function ssGet(PDO $pdo, string $ROOT, string $part) {
    $L = rocLayerFromDb($pdo, $ROOT);
    switch ($part) {
        case 'tracking': return $L['tracking'];
        case 'noindex':  return array_values((array)$L['noindex']);
        case 'sitemap':  return $L['sitemap'];
        case 'robots':   return ssFile($ROOT, 'robots.txt');
        case 'llms':     return ssFile($ROOT, 'llms.txt');
        case 'redirects': return ssRedirects($pdo);
    }
    return null;
}

/** mod_rewrite lines for the redirect rules. */
function ssRewriteBlock(array $rules): string {
    $q = function (string $p) { return preg_replace('/([.+?()\[\]{}^$|\\\\])/', '\\\\$1', $p); };
    $out = [SS_RD_BEGIN];
    foreach ($rules as $r) {
        $from = ltrim((string)$r['from'], '/');
        $prefix = substr($from, -1) === '*';
        if ($prefix) $from = substr($from, 0, -1);
        $pattern = '^' . $q(rtrim($from, '/')) . ($prefix ? '(/.*)?$' : '/?$');
        if ((int)$r['type'] === 410) { $out[] = '  RewriteRule ' . $pattern . ' - [G,L]'; continue; }
        $out[] = '  RewriteRule ' . $pattern . ' ' . str_replace(' ', '%20', (string)$r['to']) . ' [R=' . (int)$r['type'] . ',L]';
    }
    $out[] = SS_RD_END;
    return implode("\n", $out) . "\n";
}

/** Writes the redirect block into .htaccess, checks the site still answers, else puts the old file back. */
function ssApplyRedirects(string $ROOT, array $rules): ?string {
    $file = $ROOT . '/.htaccess';
    $ht = (string)@file_get_contents($file);
    if ($ht === '') return '.htaccess not found.';
    $block = ssRewriteBlock($rules);
    $start = strpos($ht, SS_RD_BEGIN);
    if ($start !== false) {
        $end = strpos($ht, SS_RD_END, $start);
        if ($end === false) return 'The redirect block in .htaccess is damaged. Nothing was changed.';
        $end += strlen(SS_RD_END);
        if (substr($ht, $end, 1) === "\n") $end++;
        $new = substr($ht, 0, $start) . $block . substr($ht, $end);
    } else {
        $anchor = null;
        foreach (['  # ROC team BEGIN', '  # Sitemaps are generated automatically', '  # Real file or folder'] as $a) if (strpos($ht, $a) !== false) { $anchor = $a; break; }
        if (!$anchor) return 'Could not find where to add redirects in .htaccess. Nothing was changed.';
        $new = str_replace($anchor, $block . $anchor, $ht);
    }
    $backup = rocHistDir($ROOT) . '/htaccess-' . gmdate('Ymd-His') . '.bak';
    if (!is_dir(dirname($backup))) @mkdir(dirname($backup), 0700, true);
    @file_put_contents($backup, $ht);
    if (!ssWrite($file, $new)) return 'Could not write .htaccess.';
    // Health check: the home page must still answer.
    if (function_exists('curl_init')) {
        $c = curl_init('https://runonconsole.com/?roc-check=' . time());
        curl_setopt_array($c, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false]);
        curl_exec($c);
        $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        if ($code >= 500) { ssWrite($file, $ht); return "The site answered HTTP {$code} with these rules, so the previous .htaccess was put back. Check the rules."; }
    }
    return null;
}

/* ------------------------------------------------- already on the site */
/* Redirects and noindex pages that the website set up itself (installers, page code,
   database). Shown read-only next to the CMS rules. */

/** "^categories/(.+)$" -> "/categories/*" */
function ssHuman(string $p): string {
    $p = preg_replace('/^\^|\$$/', '', $p);
    $p = str_replace(['(/.*)?', '(.*)', '(.+)', '/?'], ['*', '*', '*', '/'], $p);
    $p = preg_replace('/\(\[a-z0-9-\]\+\)|\(\[a-z0-9\]\+\(\?:-\[a-z0-9\]\+\)\*\)/', '{name}', $p);
    $p = str_replace(['\\.', '\\'], ['.', ''], $p);
    if ($p === '*' || $p === '') return 'every address';
    return '/' . ltrim($p, '/');
}

function ssSystemRedirects(string $ROOT): array {
    $out = [];
    $section = ''; $inCms = false;
    $conds = [];
    foreach (preg_split('/\R/', (string)@file_get_contents($ROOT . '/.htaccess')) as $line) {
        $t = trim($line);
        if ($t === '') continue;
        if (strpos($t, '# ROC cms-redirects BEGIN') === 0) { $inCms = true; continue; }
        if (strpos($t, '# ROC cms-redirects END') === 0) { $inCms = false; continue; }
        if ($t[0] === '#') {
            $c = trim(ltrim($t, '# '));
            if (preg_match('/^(END|BEGIN cPanel)/i', $c) || preg_match('/ END$/', $c) || preg_match('/^=+$/', $c)) continue;
            $section = preg_replace('/\s*BEGIN\b/', '', $c);
            continue;
        }
        if ($inCms) continue;
        if (stripos($t, 'RewriteCond') === 0) { $conds[] = $t; continue; }
        if (!preg_match('/^RewriteRule\s+(\S+)\s+(\S+)(?:\s+\[([^\]]*)\])?/i', $t, $m)) { $conds = []; continue; }
        $flags = strtoupper($m[3] ?? '');
        $type = null;
        if (preg_match('/\bR=(30[1278])\b/', $flags, $r)) $type = (int)$r[1];
        elseif (preg_match('/(^|,)R(,|$)/', $flags)) $type = 302;
        elseif (preg_match('/(^|,)G(,|$)/', $flags)) $type = 410;
        if ($type !== null) {
            $to = $type === 410 ? '' : preg_replace('/\$\d/', '*', $m[2]);
            $from = ssHuman($m[1]);
            if ($from === 'every address') {   // host / https rules: describe them
                $c = strtolower(implode(' ', $conds));
                $from = strpos($c, 'www') !== false ? 'www.runonconsole.com/…' : (strpos($c, 'https') !== false || strpos($c, '443') !== false ? 'http://… (not secure)' : 'every address');
                $to = preg_replace('#https://%\{HTTP_HOST\}/\*#', 'https://runonconsole.com/…', $to);
                $to = preg_replace('#https://runonconsole\.com/\*#', 'https://runonconsole.com/…', $to);
            }
            $out[] = ['from' => $from, 'to' => $to, 'type' => $type, 'source' => $section ?: '.htaccess'];
        }
        $conds = [];
    }
    // Renamed products: old address folders that only redirect (products/{old}/index.php).
    foreach (glob($ROOT . '/products/*/index.php') ?: [] as $stub) {
        $s = (string)@file_get_contents($stub, false, null, 0, 4000);
        if (preg_match('#Location:\s*([^\'"\s]+)#i', $s, $m)) {
            $out[] = ['from' => '/products/' . basename(dirname($stub)) . '/', 'to' => $m[1], 'type' => 301, 'source' => 'Renamed product'];
        }
    }
    return $out;
}

function ssSystemNoindex(PDO $pdo, string $ROOT, array $L): array {
    $out = [];
    // Built pages that say noindex in their own code (not because of a CMS rule).
    foreach (rocLayerStaticFiles($ROOT) as $path => $file) {
        [$html] = rocLayerStrip((string)file_get_contents($file, false, null, 0, 60000));
        if (preg_match('#<meta\s+name="robots"\s+content="[^"]*noindex#i', $html)) {
            $out[] = $path === '/404' ? ['path' => '404 page (not found)', 'why' => 'Page code: error page']
                                      : ['path' => $path, 'why' => 'Page code: private or account page'];
        }
    }
    // Folders whose own .htaccess sends "X-Robots-Tag: noindex".
    foreach (['auth', 'profile', 'cms', 'api'] as $d) {
        $h = (string)@file_get_contents($ROOT . '/' . $d . '/.htaccess');
        if (stripos($h, 'noindex') !== false) $out[] = ['path' => '/' . $d . '/*', 'why' => 'Folder rule (.htaccess)'];
    }
    // Database rows switched to noindex in their editors.
    $tables = ['products' => ['Product', '/products/%s/'], 'product_categories' => ['Product category', '/products/%s/'],
               'gaming_categories' => ['Gaming platform', '/gaming-platforms/%s/'], 'blogs' => ['Blog post', '/blogs/%s/'], 'pages' => ['CMS page', '/%s/']];
    foreach ($tables as $t => [$label, $fmt]) {
        try { $rows = $pdo->query("SELECT slug FROM {$t} WHERE is_noindex = 1")->fetchAll(PDO::FETCH_COLUMN); } catch (\Throwable $e) { continue; }
        foreach ($rows as $slug) $out[] = ['path' => sprintf($fmt, $slug), 'why' => $label . ' set to noindex in its editor'];
    }
    return $out;
}

/** robots.txt "Disallow" lines (crawlers are asked not to visit these). */
function ssDisallow(string $ROOT): array {
    $out = []; $agent = '*';
    foreach (preg_split('/\R/', (string)@file_get_contents($ROOT . '/robots.txt')) as $l) {
        if (preg_match('/^\s*User-agent:\s*(.+)$/i', $l, $m)) $agent = trim($m[1]);
        elseif (preg_match('/^\s*Disallow:\s*(\S+)/i', $l, $m)) $out[] = ['path' => $m[1], 'agent' => $agent];
    }
    return $out;
}

/** Validates and saves one part. Returns [savedValue, message]. */
function ssSave(PDO $pdo, string $ROOT, string $part, $v, array $session): array {
    $before = ssGet($pdo, $ROOT, $part);
    $err = [];
    switch ($part) {
        case 'tracking':
            $v = is_array($v) ? $v : [];
            // A whole <meta ... content="..."> may be pasted: keep only the code.
            $code = function ($k) use ($v) {
                $s = trim((string)($v[$k] ?? ''));
                if (preg_match('/content=["\']([^"\']+)["\']/i', $s, $m)) $s = $m[1];
                if (preg_match('/[?&]id=([A-Z0-9-]+)/i', $s, $m)) $s = $m[1];
                return trim($s);
            };
            $t = ['gtm' => strtoupper($code('gtm')), 'ga4' => strtoupper($code('ga4')), 'clarity' => strtolower($code('clarity')),
                  'gsc' => $code('gsc'), 'bing' => $code('bing'), 'pinterest' => $code('pinterest'), 'facebook' => $code('facebook'),
                  'yandex' => $code('yandex'), 'gmb_url' => trim((string)($v['gmb_url'] ?? '')), 'gmb_place_id' => trim((string)($v['gmb_place_id'] ?? ''))];
            $rules = ['gtm' => ['/^GTM-[A-Z0-9]{4,12}$/', 'Use the container ID, like GTM-ABC1234.'],
                      'ga4' => ['/^G-[A-Z0-9]{4,15}$/', 'Use the measurement ID, like G-ABC123XYZ.'],
                      'clarity' => ['/^[a-z0-9]{6,20}$/', 'Use the Clarity project ID (letters and numbers).'],
                      'gsc' => ['/^[A-Za-z0-9_-]{10,120}$/', 'Paste the content value of the google-site-verification tag.'],
                      'bing' => ['/^[A-Za-z0-9]{10,64}$/', 'Paste the content value of the msvalidate.01 tag.'],
                      'pinterest' => ['/^[A-Za-z0-9]{10,64}$/', 'Paste the content value of the p:domain_verify tag.'],
                      'facebook' => ['/^[A-Za-z0-9]{10,64}$/', 'Paste the content value of the facebook-domain-verification tag.'],
                      'yandex' => ['/^[A-Za-z0-9]{10,64}$/', 'Paste the content value of the yandex-verification tag.'],
                      'gmb_url' => ['#^https://[^\s"<>]+$#', 'Use the full https:// link of your Google Business Profile.'],
                      'gmb_place_id' => ['/^[A-Za-z0-9_-]{10,200}$/', 'Use the Place ID (starts with ChIJ…).']];
            foreach ($rules as $k => [$re, $msg]) if ($t[$k] !== '' && !preg_match($re, $t[$k])) $err[$k] = $msg;
            if ($err) ssOut(422, ['success' => false, 'error' => 'Check the highlighted codes.', 'errors' => $err]);
            rocLayerSave($pdo, $ROOT, 'tracking', $t, (int)$session['user_id']);
            $v = $t; $msg = 'Saved. The codes are on every page now.';
            break;
        case 'noindex':
            $list = [];
            foreach (preg_split('/\R/', is_array($v) ? implode("\n", $v) : (string)$v) as $line) {
                $line = trim($line); if ($line === '') continue;
                if (!preg_match(SS_PATH_RE, $line)) $err['noindex'] = 'Not a site address: ' . $line . ' (start with /, end with * for a whole section).';
                elseif ($line === '/' || $line === '/*') $err['noindex'] = 'The home page / the whole site cannot be set to noindex here.';
                else $list[] = $line;
            }
            if ($err) ssOut(422, ['success' => false, 'error' => $err['noindex'], 'errors' => $err]);
            $list = array_values(array_unique($list));
            rocLayerSave($pdo, $ROOT, 'noindex', $list, (int)$session['user_id']);
            $v = $list; $msg = 'Saved. ' . count($list) . ' rule(s): those pages now say noindex and are left out of the sitemaps.';
            break;
        case 'sitemap':
            $v = is_array($v) ? $v : [];
            $ex = [];
            foreach (preg_split('/\R/', is_array($v['exclude'] ?? null) ? implode("\n", $v['exclude']) : (string)($v['exclude'] ?? '')) as $l) {
                $l = trim($l); if ($l === '') continue;
                if (!preg_match(SS_PATH_RE, $l)) $err['exclude'] = 'Not a site address: ' . $l; else $ex[] = $l;
            }
            $extra = [];
            foreach ((array)($v['extra'] ?? []) as $e) {
                $p = trim((string)($e['path'] ?? '')); if ($p === '') continue;
                if (!preg_match('#^/[a-z0-9/_.\-]*$#i', $p)) { $err['extra'] = 'Not a site address: ' . $p; continue; }
                $extra[] = ['path' => $p, 'title' => mb_substr(trim((string)($e['title'] ?? '')), 0, 120)];
            }
            if ($err) ssOut(422, ['success' => false, 'error' => reset($err), 'errors' => $err]);
            $v = ['exclude' => array_values(array_unique($ex)), 'extra' => $extra];
            rocLayerSave($pdo, $ROOT, 'sitemap', $v, (int)$session['user_id']);
            $msg = 'Saved. The sitemaps change right away.';
            break;
        case 'robots':
        case 'llms':
            $text = str_replace("\r\n", "\n", (string)$v);
            if (strlen($text) > ($part === 'robots' ? 20000 : 200000)) ssOut(422, ['success' => false, 'error' => 'The text is too long.']);
            if (preg_match('/<\s*(script|\?php|html)/i', $text)) ssOut(422, ['success' => false, 'error' => 'This file is plain text: no HTML or code.']);
            if ($part === 'robots') {
                // Refuse a robots.txt that hides the whole site from every search engine.
                if (preg_match('/User-agent:\s*\*\s*\n(?:[^\n]*\n)*?\s*Disallow:\s*\/\s*(\n|$)/i', $text)) {
                    ssOut(422, ['success' => false, 'error' => '"Disallow: /" for all robots would remove the whole site from Google. Not saved.']);
                }
            }
            if (!ssWrite($ROOT . '/' . ($part === 'robots' ? 'robots.txt' : 'llms.txt'), rtrim($text) . "\n")) ssOut(500, ['success' => false, 'error' => 'Could not write the file.']);
            $v = rtrim($text) . "\n"; $msg = 'Saved. https://runonconsole.com/' . ($part === 'robots' ? 'robots.txt' : 'llms.txt') . ' is updated.';
            break;
        case 'redirects':
            $rules = [];
            foreach ((array)$v as $i => $r) {
                $from = trim((string)($r['from'] ?? '')); $to = trim((string)($r['to'] ?? '')); $type = (int)($r['type'] ?? 301);
                if ($from === '' && $to === '') continue;
                if (!preg_match(SS_PATH_RE, $from) || $from === '/' || $from === '/*') { $err["from{$i}"] = 'Row ' . ($i + 1) . ': "from" must be an address on this site, like /old-page/.'; continue; }
                if (preg_match('#^/(api|cms|assets|images|uploads)(/|$)#', $from)) { $err["from{$i}"] = 'Row ' . ($i + 1) . ': system folders cannot be redirected.'; continue; }
                if (!in_array($type, [301, 302, 410], true)) $type = 301;
                if ($type !== 410 && !preg_match('#^(/[^\s"<>]*|https://[^\s"<>]+)$#', $to)) { $err["to{$i}"] = 'Row ' . ($i + 1) . ': "to" must be /a-page/ or https://…'; continue; }
                if ($type !== 410 && rtrim($to, '/') === rtrim($from, '/*')) { $err["to{$i}"] = 'Row ' . ($i + 1) . ': a page cannot redirect to itself.'; continue; }
                $rules[] = ['from' => $from, 'to' => $type === 410 ? '' : $to, 'type' => $type, 'note' => mb_substr(trim((string)($r['note'] ?? '')), 0, 120)];
            }
            if ($err) ssOut(422, ['success' => false, 'error' => reset($err), 'errors' => $err]);
            if (count($rules) > 500) ssOut(422, ['success' => false, 'error' => 'At most 500 redirects.']);
            $fail = ssApplyRedirects($ROOT, $rules);
            if ($fail) ssOut(500, ['success' => false, 'error' => $fail]);
            rocLayerSettingSet($pdo, 'redirect_rules', (string)json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int)$session['user_id']);
            $v = $rules; $msg = 'Saved. ' . count($rules) . ' redirect(s) are live.';
            break;
        default:
            ssOut(400, ['success' => false, 'error' => 'Unknown part.']);
    }
    rocHistAdd($ROOT, 'settings', $part, ucfirst($part), $before, $v, $session);
    logCmsAudit('cms_settings_' . $part, 'settings', $part, []);
    return [$v, $msg];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if (isset($_GET['history'])) ssOut(200, ['success' => true, 'days' => ROC_HISTORY_DAYS, 'history' => rocHistList($ROOT, 'settings', (string)$_GET['history'])]);
    $out = ['success' => true];
    foreach (['tracking', 'noindex', 'sitemap', 'robots', 'llms', 'redirects'] as $p) $out[$p] = ssGet($pdo, $ROOT, $p);
    $out['system'] = ['redirects' => ssSystemRedirects($ROOT), 'noindex' => ssSystemNoindex($pdo, $ROOT, rocLayerFromDb($pdo, $ROOT)), 'disallow' => ssDisallow($ROOT)];
    ssOut(200, $out);
}

$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
if (($in['action'] ?? '') === 'restore') {
    $h = rocHistGet($ROOT, (string)($in['id'] ?? ''));
    if (!$h || $h['kind'] !== 'settings') ssOut(404, ['success' => false, 'error' => 'That version is no longer in the archive.']);
    [$v, $msg] = ssSave($pdo, $ROOT, (string)$h['key'], $h[($in['which'] ?? 'before') === 'after' ? 'after' : 'before'], $session);
    ssOut(200, ['success' => true, 'part' => $h['key'], 'value' => $v, 'message' => 'Earlier version restored. ' . $msg]);
}
[$v, $msg] = ssSave($pdo, $ROOT, (string)($in['part'] ?? ''), $in['value'] ?? null, $session);
ssOut(200, ['success' => true, 'value' => $v, 'message' => $msg]);
