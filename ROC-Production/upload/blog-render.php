<?php
/**
 * Run On Console — public blog pages rendered from the CMS database.
 *
 * Interim fix until the React app reads blogs from the API.
 *
 *   /blogs/                      -> listing          (blog-render.php)
 *   /blogs/{slug}/               -> single post      (blog-render.php?slug=...)
 *   /sitemaps/blogs-sitemap.xml  -> blog sitemap     (blog-render.php?sitemap=1)
 *
 * The site's own design is reused: header, footer, fonts and CSS come from the
 * prerendered HTML saved in /cms-templates/ by cli-blog-routing.php. The React
 * bundle is NOT loaded on these pages, because the bundle only knows the blogs
 * it was built with and would replace a new post with its 404 screen.
 *
 * Only published posts are shown. Every published post is index, follow with a
 * self-referencing canonical; there is no per-post robots or canonical override.
 */

declare(strict_types=1);

const ROC_PUBLIC_URL = 'https://runonconsole.com';
const ROC_PER_PAGE   = 24;

$ROOT = __DIR__;

// page-render.php includes this file only for its helper functions.
if (!defined('ROC_RENDER_LIB_ONLY')) {
/* ------------------------------------------------------------ request */

$slug     = isset($_GET['slug']) ? strtolower(trim((string)$_GET['slug'], "/ \t\n\r")) : '';
$isMap    = isset($_GET['sitemap']);
$query    = isset($_GET['q']) ? trim(mb_substr((string)$_GET['q'], 0, 80)) : '';
$page     = max(1, (int)($_GET['page'] ?? 1));

if ($slug !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    rocNotFound($ROOT);
}

/* ----------------------------------------------------------- database */

$pdo = null;
try {
    ob_start();
    require_once $ROOT . '/api/v1/config.php';
    ob_end_clean();
    require_once $ROOT . '/api/v1/cms/cms-html.php';
    $pdo = function_exists('getDBConnection') ? getDBConnection() : null;
} catch (\Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    error_log('blog-render bootstrap: ' . $e->getMessage());
    $pdo = null;
}

// config.php is shared with the JSON API; undo anything it set for that.
if (!headers_sent()) {
    header_remove('X-Robots-Tag');
    header_remove('Content-Type');
    header_remove('Access-Control-Allow-Origin');
    header_remove('Access-Control-Allow-Credentials');
}

if (!$pdo) {
    // Database down: serve the old static file if one exists rather than an error.
    $static = $slug === '' ? $ROOT . '/blogs/index.html' : $ROOT . '/blogs/' . $slug . '/index.html';
    if (!$isMap && is_file($static)) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache');
        readfile($static);
        exit;
    }
    http_response_code(503);
    header('Retry-After: 120');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Temporarily unavailable. Please try again in a moment.';
    exit;
}

/* ------------------------------------------------------------ routing */

if ($isMap) {
    rocSitemap($pdo);
}
if ($slug !== '') {
    $post = rocFetchPost($pdo, $slug);
    if (!$post) rocNotFound($ROOT);
    rocRenderPost($ROOT, $post);
}
if (isset($_GET['json'])) {
    // Published posts for the header search box (/blogs/?json=1).
    // Also used by the home page's latest-articles section (image, date).
    $rows = $pdo->query("SELECT title, slug, category, excerpt, image, image_alt, published_at FROM blogs WHERE status = 'published' ORDER BY published_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo json_encode(array_map(function ($r) {
        $pub = rocDate($r['published_at'] ?? null);
        return ['t' => (string)$r['title'], 'c' => rocCategoryLabel((string)($r['category'] ?? '')), 'e' => mb_substr((string)($r['excerpt'] ?? ''), 0, 160),
                'u' => '/blogs/' . $r['slug'] . '/', 'i' => (string)($r['image'] ?? ''), 'a' => (string)($r['image_alt'] ?? ''),
                'd' => $pub ? gmdate('Y-m-d', $pub) : ''];
    }, $rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
rocRenderList($ROOT, $pdo, $query, $page);
}


/* ==================================================================== */
/*                               data                                   */
/* ==================================================================== */

function rocFetchPost(PDO $pdo, string $slug): ?array {
    $stmt = $pdo->prepare("SELECT * FROM blogs WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function rocOrderSql(): string {
    return 'ORDER BY COALESCE(published_at, created_at) DESC, id DESC';
}

/* ==================================================================== */
/*                              helpers                                 */
/* ==================================================================== */

function rocH($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function rocAbs(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    if (preg_match('#^https?://#i', $url)) return $url;
    if ($url[0] !== '/') $url = '/' . $url;
    return ROC_PUBLIC_URL . $url;
}

function rocPostUrl(string $slug): string {
    return ROC_PUBLIC_URL . '/blogs/' . $slug . '/';
}

function rocDate(?string $v): ?int {
    if (!$v || strpos($v, '0000-00-00') === 0) return null;
    $t = strtotime($v);
    return $t ?: null;
}

function rocPublished(array $p): ?int {
    return rocDate($p['published_at'] ?? null) ?? rocDate($p['created_at'] ?? null);
}

function rocModified(array $p): ?int {
    return rocDate($p['content_modified_at'] ?? null)
        ?? rocDate($p['updated_at'] ?? null)
        ?? rocPublished($p);
}

/** "guides-deals" -> "Guides Deals"; "Guides & Deals" stays as written. */
function rocCategoryLabel(?string $cat): string {
    $cat = trim((string)$cat);
    if ($cat === '' || strtolower($cat) === 'general') return 'Gaming Guide';
    if (strpos($cat, ' ') === false && preg_match('/^[a-z0-9-]+$/', $cat)) {
        return ucwords(str_replace('-', ' ', $cat));
    }
    return $cat;
}

function rocInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= mb_strtoupper(mb_substr($p, 0, 1));
    return $out !== '' ? $out : 'RC';
}

/** Body as safe HTML. CMS posts are HTML; older seeded posts are Markdown-style text. */
function rocBodyHtml(string $content): string {
    $content = trim($content);
    if ($content === '') return '';
    if (!preg_match('/<(p|h[1-6]|ul|ol|div|br|img|table|blockquote|figure|strong|em|a)\b/i', $content)) {
        $content = rocMarkdownLite($content);
    }
    return rocCmsSanitizeHtml($content);
}

function rocInline(string $text): string {
    $t = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safe = rocCmsSafeUrl($url);
        return $safe === null ? $m[1] : '<a href="' . rocH($safe) . '">' . $m[1] . '</a>';
    }, $t);
    $t = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $t);
    $t = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $t);
    $t = preg_replace('/`([^`]+)`/', '<code>$1</code>', $t);
    return $t;
}

function rocMarkdownLite(string $text): string {
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $html = '';
    $para = [];
    $list = null;       // 'ul' | 'ol'
    $flushPara = function () use (&$para, &$html) {
        if ($para) { $html .= '<p>' . rocInline(implode(' ', $para)) . '</p>'; $para = []; }
    };
    $flushList = function () use (&$list, &$html) {
        if ($list) { $html .= '</' . $list . '>'; $list = null; }
    };
    foreach ($lines as $raw) {
        $line = trim($raw);
        if ($line === '') { $flushPara(); $flushList(); continue; }
        if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
            $flushPara(); $flushList();
            $lvl = strlen($m[1]);
            $tag = $lvl <= 2 ? 'h2' : ($lvl === 3 ? 'h3' : 'h4');
            $html .= "<{$tag}>" . rocInline($m[2]) . "</{$tag}>";
            continue;
        }
        if (preg_match('/^[-*•]\s+(.*)$/', $line, $m) || preg_match('/^\d+[.)]\s+(.*)$/', $line, $m2)) {
            $flushPara();
            $want = isset($m[1]) ? 'ul' : 'ol';
            $item = isset($m[1]) ? $m[1] : $m2[1];
            if ($list !== $want) { $flushList(); $html .= '<' . $want . '>'; $list = $want; }
            $html .= '<li>' . rocInline($item) . '</li>';
            unset($m, $m2);
            continue;
        }
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            $flushPara(); $flushList();
            $html .= '<blockquote><p>' . rocInline($m[1]) . '</p></blockquote>';
            continue;
        }
        $flushList();
        $para[] = $line;
    }
    $flushPara(); $flushList();
    return $html;
}

function rocWordCount(string $html): int {
    $text = rocCmsTextFromHtml($html);
    return $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
}

function rocReadTime(array $p, string $bodyHtml): string {
    $rt = trim((string)($p['read_time'] ?? ''));
    if ($rt !== '' && $rt !== '5 min read') return $rt;
    $words = rocWordCount($bodyHtml);
    return max(1, (int)ceil($words / 200)) . ' min read';
}

function rocJsonLd(array $node): string {
    $json = json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    // Never let content close the script element.
    $json = str_replace('</', '<\/', (string)$json);
    return '<script type="application/ld+json">' . "\n" . $json . "\n</script>\n";
}

/* ==================================================================== */
/*                             template                                 */
/* ==================================================================== */

/**
 * Split a prerendered page into the parts we keep.
 * Returns [headTop, bodyToMain, mainOpenTag, mainInner, afterMain]
 */
function rocTemplate(string $root, string $name): array {
    $candidates = [
        $root . '/cms-templates/' . $name,
        $name === 'blog-list.html' ? $root . '/blogs/index.html' : '',
    ];
    $html = '';
    foreach ($candidates as $c) {
        if ($c !== '' && is_file($c)) { $html = (string)file_get_contents($c); break; }
    }
    if ($html === '') {
        error_log("blog-render: template {$name} missing; run cli-blog-routing.php --apply");
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Blog template missing.';
        exit;
    }

    $titlePos = stripos($html, '<title');
    $headEnd  = stripos($html, '</head>');
    $mainPos  = stripos($html, '<main');
    $mainEnd  = strripos($html, '</main>');
    if ($titlePos === false || $headEnd === false || $mainPos === false || $mainEnd === false) {
        error_log("blog-render: template {$name} has an unexpected structure");
        http_response_code(503);
        exit('Blog template unreadable.');
    }

    $headTop = substr($html, 0, $titlePos);
    $headTop = preg_replace('#<link[^>]+rel="modulepreload"[^>]*>\s*#i', '', $headTop);
    // Every renderer adds its own roc-nav.js tag; newer builds put the template's copy before <title>.
    $headTop = preg_replace('#<script\b[^>]*\bsrc="/roc-nav\.js"[^>]*>\s*</script>\s*#i', '', $headTop);
    $headTop = preg_replace('#<html\b([^>]*)>#i', '<html$1 data-roc-static="1">', $headTop, 1);

    $bodyToMain = substr($html, $headEnd, $mainPos - $headEnd);  // starts with </head>
    $openEnd    = strpos($html, '>', $mainPos);
    $mainOpen   = substr($html, $mainPos, $openEnd - $mainPos + 1);
    $mainInner  = substr($html, $openEnd + 1, $mainEnd - $openEnd - 1);
    $afterMain  = substr($html, $mainEnd);

    // Remove the React bundle so it cannot replace this page with its own copy.
    $afterMain = preg_replace('#<script\b[^>]*\bsrc="/assets/[^"]+\.js"[^>]*>\s*</script>\s*#i', '', $afterMain);
    $bodyToMain = preg_replace('#<script\b[^>]*\bsrc="/assets/[^"]+\.js"[^>]*>\s*</script>\s*#i', '', $bodyToMain);

    return [$headTop, $bodyToMain, $mainOpen, $mainInner, $afterMain];
}

function rocArticleStyles(): string {
    return <<<CSS
<style>
.roc-article{color:#1e293b;font-size:1rem;line-height:1.8}
.roc-article>*+*{margin-top:1.1em}
.roc-article h2{font-family:Outfit,Inter,sans-serif;font-weight:800;font-size:1.6rem;line-height:1.3;color:#0f172a;margin-top:1.8em}
.roc-article h3{font-family:Outfit,Inter,sans-serif;font-weight:800;font-size:1.3rem;line-height:1.35;color:#0f172a;margin-top:1.6em}
.roc-article h4,.roc-article h5{font-family:Outfit,Inter,sans-serif;font-weight:700;font-size:1.1rem;color:#0f172a;margin-top:1.4em}
.roc-article a{color:#047857;font-weight:600;text-decoration:underline;text-underline-offset:2px}
.roc-article a:hover{color:#059669}
.roc-article ul{list-style:disc;padding-left:1.4em}
.roc-article ol{list-style:decimal;padding-left:1.4em}
.roc-article li+li{margin-top:.4em}
.roc-article strong{color:#0f172a;font-weight:700}
.roc-article blockquote{border-left:4px solid #10b981;background:#ecfdf5;padding:.8em 1.1em;border-radius:0 1rem 1rem 0;color:#064e3b}
.roc-article img{max-width:100%;height:auto;border-radius:1rem;margin-left:auto;margin-right:auto}
.roc-article figure figcaption{font-size:.85rem;color:#64748b;text-align:center;margin-top:.5em}
.roc-article table{width:100%;border-collapse:collapse;font-size:.92rem;display:block;overflow-x:auto}
.roc-article th,.roc-article td{border:1px solid #e2e8f0;padding:.55em .75em;text-align:left;vertical-align:top}
.roc-article th{background:#f1f5f9;font-weight:700}
.roc-article pre{background:#0f172a;color:#e2e8f0;padding:1em;border-radius:.75rem;overflow-x:auto;font-size:.85rem}
.roc-article code{font-family:'JetBrains Mono',monospace;font-size:.9em}
.roc-article hr{border:0;border-top:1px solid #e2e8f0}
@media (min-width:640px){.roc-article{font-size:1.05rem}}
</style>

CSS;
}

function rocSendHtml(string $html, ?int $modified = null): void {
    // Announcement bar, site code and meta overrides from the CMS (api/v1/cms/site-layer-lib.php).
    $lib = __DIR__ . '/api/v1/cms/site-layer-lib.php';
    if (is_file($lib)) {
        require_once $lib;
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');
        // Page 2, searches etc. keep their own titles.
        $L = rocLayerLoad(__DIR__);
        $html = rocLayerApply($html, $path, $L, (string)parse_url($uri, PHP_URL_QUERY) === '');
        // A new announcement, code or meta counts as a change of the page (browsers then fetch it again).
        $lu = $L['updated'] !== '' ? strtotime($L['updated']) : false;
        if ($modified && $lu && $lu > $modified) $modified = $lu;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: public, max-age=0, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    // Same rule as the page's robots meta tag, also as an HTTP header (some SEO tools look for it).
    if (preg_match('#<meta\s+name="robots"\s+content="([^"]+)"#i', $html, $rm)) header('X-Robots-Tag: ' . $rm[1]);
    if ($modified) header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
    echo $html;
    exit;
}

function rocNotFound(string $root): void {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    if (is_file($root . '/404.html')) { readfile($root . '/404.html'); }
    else { echo '<!doctype html><title>Page not found</title><h1>Page not found</h1><p><a href="/blogs/">All blogs</a></p>'; }
    exit;
}

/* ==================================================================== */
/*                             single post                              */
/* ==================================================================== */

function rocRenderPost(string $root, array $p): void {
    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, 'blog-post.html');

    $slug     = (string)$p['slug'];
    $url      = rocPostUrl($slug);
    $title    = trim((string)$p['title']);
    $metaT    = trim((string)($p['meta_title'] ?? '')) ?: ($title . ' | Run On Console');
    $excerpt  = trim((string)($p['excerpt'] ?? ''));
    $body     = rocBodyHtml((string)($p['content'] ?? ''));
    $metaD    = trim((string)($p['meta_description'] ?? ''))
                ?: rocCmsTruncate($excerpt !== '' ? $excerpt : rocCmsTextFromHtml($body), 155);
    $image    = trim((string)($p['image'] ?? ''));
    $imageAbs = rocAbs($image);
    $alt      = trim((string)($p['image_alt'] ?? '')) ?: $title;
    // Posts are signed by the ROC Team (a name typed in the CMS is shown only when it is
    // someone else, e.g. a guest writer).
    $author   = trim((string)($p['author_name'] ?? ''));
    if ($author === '' || in_array(strtolower($author), ['omar abobakar', 'omar', 'roc team', 'run on console', 'run on console team', 'run on console editorial team'], true)) $author = 'ROC Team';
    $byPerson = $author !== 'ROC Team';
    $authorUrl = $byPerson ? '' : '/author/roc-team/';
    $category = rocCategoryLabel($p['category'] ?? '');
    $pub      = rocPublished($p);
    $mod      = rocModified($p) ?? $pub;
    $readTime = rocReadTime($p, $body);

    /* ---------------- head */
    $h  = '<title>' . rocH($metaT) . "</title>\n";
    $h .= '    <meta name="title" content="' . rocH($metaT) . "\" />\n";
    $h .= '    <meta name="description" content="' . rocH($metaD) . "\" />\n";
    $h .= "    <meta name=\"robots\" content=\"index, follow, max-image-preview:large\" />\n";
    $h .= '    <link rel="canonical" href="' . rocH($url) . "\" />\n";
    $h .= "    <meta property=\"og:type\" content=\"article\" />\n";
    $h .= '    <meta property="og:url" content="' . rocH($url) . "\" />\n";
    $h .= '    <meta property="og:title" content="' . rocH($metaT) . "\" />\n";
    $h .= '    <meta property="og:description" content="' . rocH($metaD) . "\" />\n";
    if ($imageAbs) {
        $h .= '    <meta property="og:image" content="' . rocH($imageAbs) . "\" />\n";
        $h .= '    <meta property="og:image:alt" content="' . rocH($alt) . "\" />\n";
    }
    $h .= "    <meta property=\"og:site_name\" content=\"Run On Console\" />\n";
    if ($pub) $h .= '    <meta property="article:published_time" content="' . gmdate('c', $pub) . "\" />\n";
    if ($mod) $h .= '    <meta property="article:modified_time" content="' . gmdate('c', $mod) . "\" />\n";
    $h .= "    <meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
    $h .= "    <meta name=\"twitter:site\" content=\"@RunOnConsole\" />\n";
    $h .= '    <meta name="twitter:title" content="' . rocH($metaT) . "\" />\n";
    $h .= '    <meta name="twitter:description" content="' . rocH($metaD) . "\" />\n";
    if ($imageAbs) $h .= '    <meta name="twitter:image" content="' . rocH($imageAbs) . "\" />\n";

    $h .= rocJsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',  'item' => ROC_PUBLIC_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blogs', 'item' => ROC_PUBLIC_URL . '/blogs/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $url],
        ],
    ]);
    $article = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        'url' => $url,
        'headline' => mb_substr($title, 0, 110),
        'description' => $metaD,
    ];
    if ($imageAbs) $article['image'] = [$imageAbs];
    if ($pub) $article['datePublished'] = gmdate('c', $pub);
    if ($mod) $article['dateModified'] = gmdate('c', $mod);
    $article['author'] = $byPerson ? ['@type' => 'Person', 'name' => $author] : ['@type' => 'Organization', 'name' => 'ROC Team', 'url' => ROC_PUBLIC_URL . '/author/roc-team/'];
    if ($authorUrl !== '') $article['author']['url'] = ROC_PUBLIC_URL . $authorUrl;
    $article['publisher'] = [
        '@type' => 'Organization',
        'name' => 'Run On Console',
        'url' => ROC_PUBLIC_URL . '/',
        'logo' => ['@type' => 'ImageObject', 'url' => ROC_PUBLIC_URL . '/images/logo-512.png', 'width' => 512, 'height' => 512],
    ];
    $words = rocWordCount($body);
    if ($words) $article['wordCount'] = $words;
    $h .= rocJsonLd($article);
    $h .= rocArticleStyles();
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    /* ---------------- body */
    $m  = $mainOpen;
    $m .= '<article class="max-w-4xl mx-auto py-6 space-y-8">';
    $m .= '<div class="flex items-center justify-between">'
        . '<a href="/blogs/" class="bg-white border border-slate-300 hover:border-emerald-600 text-slate-700 font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-2 transition-colors shadow-sm no-underline">'
        . '<span aria-hidden="true">&larr;</span><span>Back to All Blogs</span></a>'
        . '<button type="button" data-roc-share class="p-2 bg-white border border-slate-300 rounded-xl text-slate-600 hover:text-emerald-600 text-xs font-semibold flex items-center gap-1.5 shadow-sm transition-colors">'
        . '<span>Share Article</span></button></div>';

    $m .= '<header class="space-y-4">'
        . '<span class="bg-emerald-600 text-white text-xs font-extrabold uppercase px-3 py-1 rounded-md tracking-wider shadow-sm badge-glow">' . rocH($category) . '</span>'
        . '<h1 class="font-display font-extrabold text-3xl sm:text-5xl text-slate-900 leading-tight">' . rocH($title) . '</h1>'
        . '<div class="flex flex-wrap items-center justify-between gap-4 py-3 border-y border-slate-200 text-xs text-slate-500 font-medium">'
        .   '<div class="flex items-center gap-3">'
        .     '<div class="w-9 h-9 rounded-full bg-gradient-to-tr from-emerald-600 to-teal-500 text-white font-bold flex items-center justify-center shadow-md">' . rocH(rocInitials($author)) . '</div>'
        .     '<div>' . ($authorUrl !== '' ? '<a href="' . $authorUrl . '" rel="author" class="font-bold text-slate-900 block no-underline hover:text-emerald-700">' . rocH($author) . '</a>' : '<span class="font-bold text-slate-900 block">' . rocH($author) . '</span>') . '</div>'
        .   '</div>'
        .   '<div class="flex items-center gap-3">'
        .     ($pub ? '<time datetime="' . gmdate('Y-m-d', $pub) . '">' . rocH(date('M j, Y', $pub)) . '</time><span>•</span>' : '')
        .     '<span class="text-emerald-600 font-bold">' . rocH($readTime) . '</span>'
        .   '</div>'
        . '</div></header>';

    if ($image !== '') {
        $m .= '<div class="relative rounded-3xl overflow-hidden shadow-2xl max-h-[480px] bg-slate-900">'
            . '<img width="1200" height="675" src="' . rocH($image) . '" alt="' . rocH($alt) . '" class="w-full h-full object-cover max-h-[480px]" fetchpriority="high"/></div>';
    }
    if ($excerpt !== '') {
        $m .= '<div class="bg-emerald-50/80 border-l-4 border-emerald-600 p-5 rounded-r-2xl shadow-sm">'
            . '<h2 class="font-display font-extrabold text-base text-emerald-950 mb-1">Article Overview &amp; Key Takeaways</h2>'
            . '<p class="text-xs sm:text-sm text-emerald-900 leading-relaxed">' . rocH($excerpt) . '</p></div>';
    }
    $m .= '<div class="roc-article">' . $body . '</div>';
    $m .= '</article>';

    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $mod);
}

/* ==================================================================== */
/*                               listing                                */
/* ==================================================================== */

function rocRenderList(string $root, PDO $pdo, string $query, int $page): void {
    [$headTop, $bodyToMain, $mainOpen, $mainInner, $afterMain] = rocTemplate($root, 'blog-list.html');

    $where = "status = 'published'";
    $args = [];
    if ($query !== '') {
        $where .= ' AND (title LIKE ? OR excerpt LIKE ? OR category LIKE ?)';
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';
        $args = [$like, $like, $like];
    }
    $count = $pdo->prepare("SELECT COUNT(*) FROM blogs WHERE {$where}");
    $count->execute($args);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / ROC_PER_PAGE));
    if ($page > $pages && $total > 0) rocNotFound($root);

    $offset = ($page - 1) * ROC_PER_PAGE;
    $stmt = $pdo->prepare(
        "SELECT id, title, slug, category, read_time, author_name, image, image_alt, excerpt, content,
                published_at, created_at, updated_at, content_modified_at
           FROM blogs WHERE {$where} " . rocOrderSql() . ' LIMIT ' . ROC_PER_PAGE . ' OFFSET ' . $offset
    );
    $stmt->execute($args);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $allPublished = (int)$pdo->query("SELECT COUNT(*) FROM blogs WHERE status = 'published'")->fetchColumn();

    /* ---------------- head: keep the template's title/description */
    $tpl = (string)file_get_contents(is_file($root . '/cms-templates/blog-list.html')
        ? $root . '/cms-templates/blog-list.html' : $root . '/blogs/index.html');
    $title = 'Hardware Reviews & PC Build Guides | Run On Console';
    $desc  = 'Explore in-depth benchmark reviews, PC build guides, game performance analyses, and hardware buying advice from the Run On Console testing lab.';
    if (preg_match('#<title>(.*?)</title>#is', $tpl, $m1)) $title = html_entity_decode(trim($m1[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('#<meta\s+name="description"\s+content="([^"]*)"#i', $tpl, $m2)) $desc = html_entity_decode($m2[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $canonical = ROC_PUBLIC_URL . '/blogs/' . ($page > 1 ? '?page=' . $page : '');
    if ($page > 1) $title = 'Page ' . $page . ' – ' . $title;
    $robots = $query !== '' ? 'noindex, follow' : 'index, follow, max-image-preview:large';

    $ogImage = ROC_PUBLIC_URL . '/images/trending_elden.jpg';
    if (!empty($posts[0]['image'])) $ogImage = rocAbs((string)$posts[0]['image']);

    $h  = '<title>' . rocH($title) . "</title>\n";
    $h .= '    <meta name="title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta name="description" content="' . rocH($desc) . "\" />\n";
    $h .= '    <meta name="robots" content="' . $robots . "\" />\n";
    $h .= '    <link rel="canonical" href="' . rocH($canonical) . "\" />\n";
    $h .= "    <meta property=\"og:type\" content=\"website\" />\n";
    $h .= '    <meta property="og:url" content="' . rocH($canonical) . "\" />\n";
    $h .= '    <meta property="og:title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta property="og:description" content="' . rocH($desc) . "\" />\n";
    $h .= '    <meta property="og:image" content="' . rocH($ogImage) . "\" />\n";
    $h .= "    <meta property=\"og:site_name\" content=\"Run On Console\" />\n";
    $h .= "    <meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
    $h .= "    <meta name=\"twitter:site\" content=\"@RunOnConsole\" />\n";
    $h .= '    <meta name="twitter:title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta name="twitter:description" content="' . rocH($desc) . "\" />\n";
    $h .= '    <meta name="twitter:image" content="' . rocH($ogImage) . "\" />\n";
    $h .= rocJsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',  'item' => ROC_PUBLIC_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blogs', 'item' => ROC_PUBLIC_URL . '/blogs/'],
        ],
    ]);
    if ($posts && $query === '') {
        $items = [];
        foreach ($posts as $i => $p) {
            $items[] = ['@type' => 'ListItem', 'position' => $offset + $i + 1, 'url' => rocPostUrl((string)$p['slug'])];
        }
        $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items]);
    }
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    /* ---------------- body: hero from the template, cards from the database */
    $gridMarker = '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
    $g = strpos($mainInner, $gridMarker);
    $hero = $g !== false ? substr($mainInner, 0, $g) : '<div class="space-y-12 py-6">';
    $hero = preg_replace('/\d+(<!-- -->)?\s*Hardware Reviews &amp; Guides/', $allPublished . ' Hardware Reviews &amp; Guides', $hero, 1);
    $hero = preg_replace('#<canvas\b[^>]*></canvas>#i', '', $hero);

    $cards = '';
    if ($query !== '') {
        $cards .= '<div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">'
            . '<span>' . $total . ' result' . ($total === 1 ? '' : 's') . ' for <strong class="text-slate-900">&ldquo;' . rocH($query) . '&rdquo;</strong></span>'
            . '<a href="/blogs/" class="text-emerald-600 font-bold">Clear search</a></div>';
    }
    $cards .= $gridMarker;
    foreach ($posts as $p) {
        $slug = (string)$p['slug'];
        $img  = trim((string)($p['image'] ?? ''));
        $alt  = trim((string)($p['image_alt'] ?? '')) ?: (string)$p['title'];
        $rt   = rocReadTime($p, rocBodyHtml((string)($p['content'] ?? '')));
        $by   = trim((string)($p['author_name'] ?? '')) ?: 'Run On Console';
        $cards .= '<a href="/blogs/' . rocH($slug) . '/" class="block no-underline">'
            . '<div class="game-card flex flex-col justify-between group cursor-pointer h-full">'
            . '<div class="relative h-60 bg-slate-900 overflow-hidden">'
            . ($img !== '' ? '<img width="1200" height="675" src="' . rocH($img) . '" alt="' . rocH($alt) . '" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"/>' : '')
            . '<span class="absolute top-3 left-3 bg-emerald-600 text-white text-[10px] font-extrabold px-2.5 py-1 rounded-md shadow-sm uppercase badge-glow">' . rocH(rocCategoryLabel($p['category'] ?? '')) . '</span>'
            . '<span class="absolute bottom-3 right-3 bg-slate-900/90 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg flex items-center gap-1 backdrop-blur-sm">' . rocH($rt) . '</span>'
            . '</div>'
            . '<div class="p-6 flex-1 flex flex-col justify-between space-y-4"><div>'
            . '<h2 class="font-display font-extrabold text-lg sm:text-xl text-slate-900 group-hover:text-emerald-600 transition-colors leading-snug line-clamp-2 mb-2">' . rocH($p['title']) . '</h2>'
            . '<p class="text-xs text-slate-500 line-clamp-3 leading-relaxed">' . rocH($p['excerpt'] ?? '') . '</p></div>'
            . '<div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs">'
            . '<div class="flex items-center gap-2 text-slate-600 font-medium"><span>By ' . rocH($by) . '</span></div>'
            . '<span class="text-emerald-600 font-bold flex items-center gap-1 group-hover:translate-x-1 transition-transform">Read Article &rarr;</span>'
            . '</div></div></div></a>';
    }
    $cards .= '</div>';
    if (!$posts) {
        $cards .= '<p class="text-center text-slate-500 text-sm py-10">'
            . ($query !== '' ? 'No articles match that search.' : 'No articles published yet.') . '</p>';
    }

    if ($pages > 1) {
        $cards .= '<nav class="flex items-center justify-center gap-2 text-sm font-bold" aria-label="Pagination">';
        $qs = $query !== '' ? '&q=' . rawurlencode($query) : '';
        if ($page > 1) {
            $prev = $page - 1 === 1 ? '/blogs/' . ($query !== '' ? '?q=' . rawurlencode($query) : '') : '/blogs/?page=' . ($page - 1) . $qs;
            $cards .= '<a class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-slate-700" href="' . rocH($prev) . '">&larr; Newer</a>';
        }
        $cards .= '<span class="px-3 text-slate-500">Page ' . $page . ' of ' . $pages . '</span>';
        if ($page < $pages) {
            $cards .= '<a class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-slate-700" href="/blogs/?page=' . ($page + 1) . rocH($qs) . '">Older &rarr;</a>';
        }
        $cards .= '</nav>';
    }

    $main = $mainOpen . $hero . $cards . '</div>';

    $latest = null;
    foreach ($posts as $p) { $t = rocModified($p); if ($t && (!$latest || $t > $latest)) $latest = $t; }
    rocSendHtml($headTop . $h . $bodyToMain . $main . $afterMain, $latest);
}

/* ==================================================================== */
/*                               sitemap                                */
/* ==================================================================== */

/* ==================================================================== */
/*                       sitemap helpers (all sitemaps)                 */
/* ==================================================================== */

/**
 * Start of a sitemap. The stylesheet shows it as a readable table (page name, address,
 * last change) when opened in a browser; search engines ignore it. Page names are in
 * roc:title, a namespaced extension that sitemap readers skip.
 */
function rocSmOpen(): string {
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
         . '<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>' . "\n"
         . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:roc="https://runonconsole.com/ns/sitemap">' . "\n";
}

function rocSmLine(string $loc, ?int $mod, string $title = ''): string {
    $x = function ($v) { return htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
    return '  <url><loc>' . $x($loc) . '</loc>' . ($mod ? '<lastmod>' . gmdate('c', $mod) . '</lastmod>' : '')
         . ($title !== '' ? '<roc:title>' . $x($title) . '</roc:title>' : '') . "</url>\n";
}


function rocSitemap(PDO $pdo): void {
    $rows = $pdo->query("SELECT slug, title, image, published_at, created_at, updated_at, content_modified_at
                           FROM blogs WHERE status = 'published' " . rocOrderSql())->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $out  = rocSmOpen();
    $latest = null;
    foreach ($rows as $r) { $t = rocModified($r); if ($t && (!$latest || $t > $latest)) $latest = $t; }
    $out .= rocSmLine(ROC_PUBLIC_URL . '/blogs/', $latest, 'Blogs');
    foreach ($rows as $r) $out .= rocSmLine(rocPostUrl((string)$r['slug']), rocModified($r), (string)$r['title']);
    $out .= "</urlset>\n";
    echo $out;
    exit;
}
