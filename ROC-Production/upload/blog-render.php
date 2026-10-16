<?php
/**
 * Run On Console — public blog pages rendered from the CMS database.
 *
 * Interim fix until the React app reads blogs from the API.
 *
 *   /blogs/                      -> listing          (blog-render.php)
 *   /blogs/{slug}/               -> single post      (blog-render.php?slug=...)
 *   /sitemaps/blogs-sitemap.xml  -> blog sitemap     (blog-render.php?sitemap=1)
 *   /news/                       -> news listing     (blog-render.php?news=1)
 *   /news/{slug}/                -> news story       (blog-render.php?news=1&slug=...)
 *   /sitemaps/news-sitemap.xml   -> Google News sitemap, last 2 days (blog-render.php?news=1&sitemap=1)
 *   /guides/                     -> guides, by type  (blog-render.php?guides=1, ?type=buying ...)
 *   /guides/{slug}/              -> one guide        (blog-render.php?guides=1&slug=...)
 *   /sitemaps/guides-sitemap.xml -> guides sitemap   (blog-render.php?guides=1&sitemap=1)
 *
 * Guides (Deploy 70) are posts the owner writes in the CMS with the category "Guides" or "Guides: <type>"
 * (Guides: Buying, Guides: How to use, Guides: Setup, Guides: Game setup ...). Routes: cli-guides.php.
 *
 * News stories are posts with the category "News" (written by api/v1/cms/news-engine-lib.php
 * or in the CMS). They live at /news/{slug}/; a /blogs/ address of a story (or a /news/
 * address of a blog post) sends the visitor to the right one with a 301.
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
const ROC_PER_PAGE   = 10;   // posts or news stories per page (owner: at most 10)

$ROOT = __DIR__;

// page-render.php includes this file only for its helper functions.
if (!defined('ROC_RENDER_LIB_ONLY')) {
/* ------------------------------------------------------------ request */

$slug     = isset($_GET['slug']) ? strtolower(trim((string)$_GET['slug'], "/ \t\n\r")) : '';
$isMap    = isset($_GET['sitemap']);
$query    = isset($_GET['q']) ? trim(mb_substr((string)$_GET['q'], 0, 80)) : '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$isNews   = isset($_GET['news']);
$isGuide  = isset($_GET['guides']);
$section  = $isNews ? 'news' : ($isGuide ? 'guides' : 'blogs');

if ($slug !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    rocNotFound($ROOT);
}

// List pages live at /news/page/2/ and /blogs/page/2/ (cli-news-pages.php): old ?page=2 links move there.
$reqPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
if ($slug === '' && !$isMap && !isset($_GET['json']) && isset($_GET['page'])) {
    $pretty = strpos($reqPath, '/page/') !== false;
    if (!$pretty || $page === 1) {
        header('Location: ' . rocPageUrl('/' . $section . '/', $page) . ($query !== '' ? '?q=' . rawurlencode($query) : ''), true, 301);
        exit;
    }
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
    if ($isNews) rocNewsSitemap($pdo);
    if ($isGuide) rocGuidesSitemap($pdo);
    rocSitemap($pdo);
}
if ($slug !== '') {
    $post = rocFetchPost($pdo, $slug);
    if (!$post) rocNotFound($ROOT);
    if (rocSectionOf($post) !== $section) {
        header('Location: ' . rocPostUrlOf($post), true, 301);
        exit;
    }
    rocRenderPost($ROOT, $post, $pdo);
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
                'u' => '/' . rocSectionOf($r) . '/' . $r['slug'] . '/', 'i' => (string)($r['image'] ?? ''), 'a' => (string)($r['image_alt'] ?? ''),
                'd' => $pub ? gmdate('Y-m-d', $pub) : ''];
    }, $rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($isGuide) rocRenderGuides($ROOT, $pdo, $page, (string)($_GET['type'] ?? ''));
rocRenderList($ROOT, $pdo, $query, $page, $isNews);
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

function rocPostUrl(string $slug, bool $news = false): string {
    return ROC_PUBLIC_URL . ($news ? '/news/' : '/blogs/') . $slug . '/';
}

/** Guides: the category "Guides" or "Guides: <type>" (written by the owner in the CMS). */
function rocGuideSql(): string { return "(LOWER(category) IN ('guides', 'guide') OR LOWER(category) LIKE 'guides:%' OR LOWER(category) LIKE 'guide:%')"; }
function rocNotGuideSql(): string { return "NOT (LOWER(COALESCE(category, '')) IN ('guides', 'guide') OR LOWER(COALESCE(category, '')) LIKE 'guides:%' OR LOWER(COALESCE(category, '')) LIKE 'guide:%')"; }
function rocIsGuide(array $p): bool { return (bool)preg_match('/^guides?\s*(:.*)?$/i', trim((string)($p['category'] ?? ''))); }
/** The guide's type ("Buying", "Game setup" ...) from "Guides: Buying"; '' when none. */
function rocGuideType(array $p): string { return preg_match('/^guides?\s*:\s*(.+)$/i', trim((string)($p['category'] ?? '')), $m) ? ucfirst(trim($m[1])) : ''; }
/** Where a post lives: news, guides or blogs. */
function rocSectionOf(array $p): string { return rocIsNews($p) ? 'news' : (rocIsGuide($p) ? 'guides' : 'blogs'); }
function rocPostUrlOf(array $p): string { return ROC_PUBLIC_URL . '/' . rocSectionOf($p) . '/' . $p['slug'] . '/'; }

/** News stories are posts with the category "News". */
function rocIsNews(array $p): bool {
    return strcasecmp(trim((string)($p['category'] ?? '')), 'News') === 0;
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

function rocRenderPost(string $root, array $p, ?PDO $pdo = null): void {
    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, 'blog-post.html');

    $slug     = (string)$p['slug'];
    $news     = rocIsNews($p);
    $guide    = rocIsGuide($p);
    $url      = rocPostUrlOf($p);
    $secBase  = $news ? '/news/' : ($guide ? '/guides/' : '/blogs/');
    $secName  = $news ? 'News' : ($guide ? 'Guides' : 'Blogs');
    $title    = trim((string)$p['title']);
    $metaT    = trim((string)($p['meta_title'] ?? '')) ?: ($title . ' | Run On Console');
    $excerpt  = trim((string)($p['excerpt'] ?? ''));
    $body     = rocBodyHtml((string)($p['content'] ?? ''));
    $metaD    = trim((string)($p['meta_description'] ?? ''))
                ?: rocCmsTruncate($excerpt !== '' ? $excerpt : rocCmsTextFromHtml($body), 155);
    $image    = trim((string)($p['image'] ?? ''));
    $imageAbs = rocAbs($image);
    $alt      = trim((string)($p['image_alt'] ?? '')) ?: $title;
    // Posts are signed "ROC" (a name typed in the CMS is shown only when it is
    // someone else, e.g. a guest writer).
    $author   = trim((string)($p['author_name'] ?? ''));
    if ($author === '' || in_array(strtolower($author), ['omar abobakar', 'omar', 'roc', 'roc team', 'run on console', 'run on console team', 'run on console editorial team'], true)) $author = 'ROC';
    $byPerson = $author !== 'ROC';
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
            ['@type' => 'ListItem', 'position' => 2, 'name' => $secName, 'item' => ROC_PUBLIC_URL . $secBase],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $url],
        ],
    ]);
    $article = [
        '@context' => 'https://schema.org',
        '@type' => $news ? 'NewsArticle' : 'BlogPosting',
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        'url' => $url,
        'headline' => mb_substr($title, 0, 110),
        'description' => $metaD,
    ];
    if ($imageAbs) $article['image'] = [$imageAbs];
    if ($pub) $article['datePublished'] = gmdate('c', $pub);
    if ($mod) $article['dateModified'] = gmdate('c', $mod);
    $article['author'] = $byPerson ? ['@type' => 'Person', 'name' => $author] : ['@type' => 'Organization', 'name' => 'ROC (Run On Console)', 'url' => ROC_PUBLIC_URL . '/author/roc-team/'];
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
    if ($news) $h .= rocNewsStyles();
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    /* ---------------- body (news: the story with the latest news and the ad on the right, Geo style) */
    $m  = $mainOpen;
    $m .= $news ? '<div class="rn-post"><article class="py-6 space-y-8 min-w-0">' : '<article class="max-w-4xl mx-auto py-6 space-y-8">';
    $m .= '<div class="flex items-center justify-between">'
        . '<a href="' . $secBase . '" class="bg-white border border-slate-300 hover:border-emerald-600 text-slate-700 font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-2 transition-colors shadow-sm no-underline">'
        . '<span aria-hidden="true">&larr;</span><span>Back to All ' . $secName . '</span></a>'
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
        .     ($pub ? '<time datetime="' . gmdate('c', $pub) . '">' . rocH($news ? rocPkt($pub, 'M j, Y, H:i') : date('M j, Y', $pub)) . '</time><span>•</span>' : '')
        .     '<span class="text-emerald-600 font-bold">' . rocH($readTime) . '</span>'
        .   '</div>'
        . '</div></header>';

    if ($image !== '') {
        $m .= '<div class="relative rounded-3xl overflow-hidden shadow-2xl max-h-[480px] bg-slate-900">'
            . '<a href="' . rocH($image) . '" target="_blank" rel="noopener" title="Open the full picture"><img width="1200" height="675" src="' . rocH($image) . '" alt="' . rocH($alt) . '" class="w-full h-full object-cover max-h-[480px]" fetchpriority="high"/></a></div>';
    }
    if ($excerpt !== '') {
        $m .= '<div class="bg-emerald-50/80 border-l-4 border-emerald-600 p-5 rounded-r-2xl shadow-sm">'
            . '<h2 class="font-display font-extrabold text-base text-emerald-950 mb-1">' . ($news ? 'The story in short' : 'Article Overview &amp; Key Takeaways') . '</h2>'
            . '<p class="text-xs sm:text-sm text-emerald-900 leading-relaxed">' . rocH($excerpt) . '</p></div>';
    }
    if ($news) $m .= rocAdUnit('article');
    $m .= '<div class="roc-article">' . $body . '</div>';
    $m .= '</article>';
    if ($news) {
        $m .= '<aside class="rn-aside"><div class="rn-sticky">' . rocAdSide()
            . ($pdo ? rocNewsSideList($pdo, true, 'Latest News', $slug, 6) : '') . '</div></aside></div>';
    }

    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $mod);
}

/* ==================================================================== */
/*                               listing                                */
/* ==================================================================== */

function rocRenderList(string $root, PDO $pdo, string $query, int $page, bool $news = false): void {
    [$headTop, $bodyToMain, $mainOpen, $mainInner, $afterMain] = rocTemplate($root, 'blog-list.html');
    $base = $news ? '/news/' : '/blogs/';

    $where = "status = 'published' AND " . ($news ? "category = 'News'" : "(category IS NULL OR (category <> 'News' AND " . rocNotGuideSql() . "))");
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

    $allPublished = (int)$pdo->query("SELECT COUNT(*) FROM blogs WHERE status = 'published' AND " . ($news ? "category = 'News'" : "(category IS NULL OR (category <> 'News' AND " . rocNotGuideSql() . "))"))->fetchColumn();

    /* ---------------- head: keep the template's title/description */
    $tpl = (string)file_get_contents(is_file($root . '/cms-templates/blog-list.html')
        ? $root . '/cms-templates/blog-list.html' : $root . '/blogs/index.html');
    $title = 'Hardware Reviews & PC Build Guides | Run On Console';
    $desc  = 'Explore in-depth benchmark reviews, PC build guides, game performance analyses, and hardware buying advice from the Run On Console testing lab.';
    if (preg_match('#<title>(.*?)</title>#is', $tpl, $m1)) $title = html_entity_decode(trim($m1[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('#<meta\s+name="description"\s+content="([^"]*)"#i', $tpl, $m2)) $desc = html_entity_decode($m2[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($news) {
        $title = 'Gaming & PC Hardware News Today | Run On Console';
        $desc  = 'The latest gaming and PC hardware news: new GPUs, CPUs, handhelds, consoles and games, each story checked against trusted sources, with links to the products.';
    }

    $canonical = ROC_PUBLIC_URL . rocPageUrl($base, $page);
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
            ['@type' => 'ListItem', 'position' => 2, 'name' => $news ? 'News' : 'Blogs', 'item' => ROC_PUBLIC_URL . $base],
        ],
    ]);
    if ($posts && $query === '') {
        $items = [];
        foreach ($posts as $i => $p) {
            $items[] = ['@type' => 'ListItem', 'position' => $offset + $i + 1, 'url' => rocPostUrl((string)$p['slug'], $news)];
        }
        $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items]);
    }
    $h .= rocNewsStyles();
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    /* ---------------- body: hero from the template, cards from the database */
    $gridMarker = '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
    $g = strpos($mainInner, $gridMarker);
    $hero = $g !== false ? substr($mainInner, 0, $g) : '<div class="space-y-12 py-6">';
    $hero = preg_replace('/\d+(<!-- -->)?\s*Hardware Reviews &amp; Guides/', $allPublished . ' Hardware Reviews &amp; Guides', $hero, 1);
    $hero = preg_replace('#<canvas\b[^>]*></canvas>#i', '', $hero);
    if ($news) {
        // News: Geo style list (lead story, two beside it, the list; ad and blogs on the right).
        $latest = null;
        foreach ($posts as $p) { $t = rocModified($p); if ($t && (!$latest || $t > $latest)) $latest = $t; }
        rocSendHtml($headTop . $h . $bodyToMain . $mainOpen . rocNewsListMain($pdo, $posts, $page, $pages, $query, $total, $allPublished) . $afterMain, $latest);
    }
    $cards = '';
    if ($query !== '') {
        $cards .= '<div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">'
            . '<span>' . $total . ' result' . ($total === 1 ? '' : 's') . ' for <strong class="text-slate-900">&ldquo;' . rocH($query) . '&rdquo;</strong></span>'
            . '<a href="' . $base . '" class="text-emerald-600 font-bold">Clear search</a></div>';
    }
    $cards .= $gridMarker;
    foreach ($posts as $p) {
        $slug = (string)$p['slug'];
        $img  = trim((string)($p['image'] ?? ''));
        $alt  = trim((string)($p['image_alt'] ?? '')) ?: (string)$p['title'];
        $rt   = rocReadTime($p, rocBodyHtml((string)($p['content'] ?? '')));
        $by   = trim((string)($p['author_name'] ?? '')) ?: 'Run On Console';
        $cards .= '<a href="' . $base . rocH($slug) . '/" class="block no-underline">'
            . '<div class="game-card flex flex-col justify-between group cursor-pointer h-full">'
            . '<div class="relative bg-slate-900 overflow-hidden" style="aspect-ratio:1200 / 630">'
            . ($img !== '' ? '<img width="1200" height="675" src="' . rocH($img) . '" alt="' . rocH($alt) . '" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"/>' : '')
            // Our own covers already show the category; only other pictures get the label.
            . (strpos($img, '/uploads/blog-covers/') === 0 ? '' : '<span class="absolute top-3 left-3 bg-emerald-600 text-white text-[10px] font-extrabold px-2.5 py-1 rounded-md shadow-sm uppercase badge-glow">' . rocH(rocCategoryLabel($p['category'] ?? '')) . '</span>')
            . '<span class="absolute bottom-3 right-3 bg-slate-900/90 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg flex items-center gap-1 backdrop-blur-sm">' . rocH($rt) . '</span>'
            . '</div>'
            . '<div class="p-6 flex-1 flex flex-col justify-between space-y-4"><div>'
            . '<h2 class="font-display font-extrabold text-lg sm:text-xl text-slate-900 group-hover:text-emerald-600 transition-colors leading-snug line-clamp-2 mb-2">' . rocH($p['title']) . '</h2>'
            . '<p class="text-xs text-slate-500 line-clamp-3 leading-relaxed">' . rocH($p['excerpt'] ?? '') . '</p></div>'
            . '<div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs">'
            . '<div class="flex items-center gap-2 text-slate-600 font-medium"><span>By ' . rocH($by) . '</span></div>'
            . '<span class="text-emerald-600 font-bold flex items-center gap-1 group-hover:translate-x-1 transition-transform">' . ($news ? rocH(($pd = rocPublished($p)) ? rocPkt($pd) : 'Read') . ' &rarr;' : 'Read Article &rarr;') . '</span>'
            . '</div></div></div></a>';
    }
    $cards .= '</div>';
    if (!$posts) {
        $cards .= '<p class="text-center text-slate-500 text-sm py-10">'
            . ($query !== '' ? 'No articles match that search.' : ($news ? 'No news yet. New stories appear here as soon as they are checked.' : 'No articles published yet.')) . '</p>';
    }

    $cards .= rocPager($base, $page, $pages, $query);

    $main = $mainOpen . $hero . $cards . '</div>';

    $latest = null;
    foreach ($posts as $p) { $t = rocModified($p); if ($t && (!$latest || $t > $latest)) $latest = $t; }
    rocSendHtml($headTop . $h . $bodyToMain . $main . $afterMain, $latest);
}

/* ==================================================================== */
/*                     guides (/guides/, Deploy 70)                     */
/* ==================================================================== */

/**
 * /guides/: every guide, newest first, with a button per type (Buying, How to use, Setup, Game setup ...,
 * from the categories "Guides: <type>" the owner uses). ?type=game-setup shows one type.
 */
function rocRenderGuides(string $root, PDO $pdo, int $page, string $type): void {
    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, 'blog-list.html');
    $all = $pdo->query("SELECT id, title, slug, category, read_time, author_name, image, image_alt, excerpt, content, published_at, created_at, updated_at, content_modified_at
                          FROM blogs WHERE status = 'published' AND " . rocGuideSql() . ' ' . rocOrderSql())->fetchAll(PDO::FETCH_ASSOC);
    $slugOf = function (string $t) { return trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($t)), '-'); };
    $types = [];
    foreach ($all as $p) { $t = rocGuideType($p); if ($t !== '') $types[$slugOf($t)] = $t; }
    ksort($types);
    $type = $slugOf($type);
    if ($type !== '' && !isset($types[$type])) { header('Location: /guides/', true, 301); exit; }
    $list = $type === '' ? $all : array_values(array_filter($all, function ($p) use ($type, $slugOf) { return $slugOf(rocGuideType($p)) === $type; }));
    $total = count($list);
    $pages = max(1, (int)ceil($total / ROC_PER_PAGE));
    if ($page > $pages && $total > 0) rocNotFound($root);
    $posts = array_slice($list, ($page - 1) * ROC_PER_PAGE, ROC_PER_PAGE);

    $base = '/guides/';
    $typeName = $type !== '' ? $types[$type] : '';
    $title = ($typeName !== '' ? $typeName . ' Guides' : 'Gaming Guides: Buying, Setup & How-To') . ($page > 1 ? ' – Page ' . $page : '') . ' | Run On Console';
    $desc = $typeName !== '' ? $typeName . ' guides from Run On Console: clear, step by step help for gamers.'
                             : 'Step by step gaming guides: how to choose and buy gear, set up your PC, console and accessories, and get the best settings in your games.';
    $canonical = ROC_PUBLIC_URL . rocPageUrl($base, $page) . ($type !== '' ? '?type=' . $type : '');
    $h  = '<title>' . rocH($title) . "</title>\n";
    $h .= '    <meta name="title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta name="description" content="' . rocH($desc) . "\" />\n";
    $h .= '    <meta name="robots" content="index, follow, max-image-preview:large" />' . "\n";
    $h .= '    <link rel="canonical" href="' . rocH($canonical) . "\" />\n";
    $h .= "    <meta property=\"og:type\" content=\"website\" />\n";
    $h .= '    <meta property="og:url" content="' . rocH($canonical) . "\" />\n";
    $h .= '    <meta property="og:title" content="' . rocH($title) . "\" />\n";
    $h .= '    <meta property="og:description" content="' . rocH($desc) . "\" />\n";
    if (!empty($posts[0]['image'])) $h .= '    <meta property="og:image" content="' . rocH(rocAbs((string)$posts[0]['image'])) . "\" />\n";
    $h .= "    <meta property=\"og:site_name\" content=\"Run On Console\" />\n";
    $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => ROC_PUBLIC_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Guides', 'item' => ROC_PUBLIC_URL . $base]]]);
    if ($posts) $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => array_map(function ($p, $i) {
        return ['@type' => 'ListItem', 'position' => $i + 1, 'url' => rocPostUrlOf($p), 'name' => (string)$p['title']]; }, $posts, array_keys($posts))]);
    $h .= "<style>.gd-chips{display:flex;flex-wrap:wrap;gap:.5rem}.gd-chip{border:1px solid #cbd5e1;border-radius:999px;padding:.4rem .9rem;font-weight:700;font-size:.8rem;color:#334155;background:#fff;text-decoration:none}"
        . ".gd-chip:hover{border-color:#10b981;color:#047857}.gd-chip.on{background:#10b981;border-color:#10b981;color:#052e2b}</style>\n";
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    $m = $mainOpen . '<div class="space-y-8 py-6">'
        . '<div class="gradient-hero-bg text-white rounded-3xl p-8 sm:p-12 shadow-xl"><span class="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3 py-1 rounded-md tracking-wider">Guides</span>'
        . '<h1 class="font-display font-extrabold text-3xl sm:text-5xl text-white my-3">' . rocH($typeName !== '' ? $typeName . ' guides' : 'Gaming guides') . '</h1>'
        . '<p class="text-emerald-100 text-sm sm:text-base max-w-2xl">How to choose and buy gear, set up your PC, console and accessories, and get the best settings in your games, step by step.</p></div>';
    if ($types) {
        $m .= '<nav class="gd-chips" aria-label="Guide types"><a class="gd-chip' . ($type === '' ? ' on' : '') . '" href="/guides/">All guides (' . count($all) . ')</a>';
        foreach ($types as $k => $t) $m .= '<a class="gd-chip' . ($type === $k ? ' on' : '') . '" href="/guides/?type=' . rocH($k) . '">' . rocH($t) . '</a>';
        $m .= '</nav>';
    }
    $m .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
    foreach ($posts as $p) {
        $img = trim((string)($p['image'] ?? ''));
        $gt = rocGuideType($p);
        $m .= '<a href="/guides/' . rocH((string)$p['slug']) . '/" class="block no-underline"><div class="game-card flex flex-col justify-between group cursor-pointer h-full">'
            . '<div class="relative bg-slate-900 overflow-hidden" style="aspect-ratio:1200 / 630">'
            . ($img !== '' ? '<img width="1200" height="675" src="' . rocH($img) . '" alt="' . rocH(trim((string)($p['image_alt'] ?? '')) ?: (string)$p['title']) . '" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"/>' : '')
            . '<span class="absolute top-3 left-3 bg-emerald-600 text-white text-[10px] font-extrabold px-2.5 py-1 rounded-md shadow-sm uppercase">' . rocH($gt !== '' ? $gt : 'Guide') . '</span></div>'
            . '<div class="p-6 flex-1 flex flex-col justify-between space-y-4"><div>'
            . '<h2 class="font-display font-extrabold text-lg sm:text-xl text-slate-900 group-hover:text-emerald-600 transition-colors leading-snug line-clamp-2 mb-2">' . rocH($p['title']) . '</h2>'
            . '<p class="text-xs text-slate-500 line-clamp-3 leading-relaxed">' . rocH($p['excerpt'] ?? '') . '</p></div>'
            . '<div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs"><span class="text-slate-600">' . rocH(rocReadTime($p, rocBodyHtml((string)($p['content'] ?? '')))) . '</span>'
            . '<span class="text-emerald-600 font-bold">Read the guide &rarr;</span></div></div></div></a>';
    }
    $m .= '</div>';
    if (!$posts) $m .= '<p class="text-center text-slate-500 text-sm py-10">The first guides are on their way. Meanwhile, see our <a class="text-emerald-700 underline font-bold" href="/blogs/">articles</a>.</p>';
    $m .= rocPager($base, $page, $pages, '') . '</div>';

    $latest = null;
    foreach ($posts as $p) { $t = rocModified($p); if ($t && (!$latest || $t > $latest)) $latest = $t; }
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $latest);
}

function rocGuidesSitemap(PDO $pdo): void {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=600');
    $out = rocSmOpen() . rocSmLine(ROC_PUBLIC_URL . '/guides/', time(), 'Gaming guides');
    foreach ($pdo->query("SELECT title, slug, category, published_at, updated_at, content_modified_at, created_at FROM blogs WHERE status = 'published' AND " . rocGuideSql() . ' ' . rocOrderSql())->fetchAll(PDO::FETCH_ASSOC) as $r)
        $out .= rocSmLine(rocPostUrlOf($r), rocModified($r), (string)$r['title']);
    echo $out . "</urlset>\n";
    exit;
}

/* ==================================================================== */
/*                news layout (Geo style), pages, ad places             */
/* ==================================================================== */

/** Address of list page $n: /news/, /news/page/2/ ... (rules from cli-news-pages.php). */
function rocPageUrl(string $base, int $n): string {
    return $n <= 1 ? $base : $base . 'page/' . $n . '/';
}

/** Page numbers with "Previous" and "Next": 1 ... 4 5 6 ... 12. */
function rocPager(string $base, int $page, int $pages, string $query = ''): string {
    if ($pages <= 1) return '';
    $qs = $query !== '' ? '?q=' . rawurlencode($query) : '';
    $u = function (int $n) use ($base, $qs) { return rocH(rocPageUrl($base, $n) . $qs); };
    $o = '<nav class="rn-pg" aria-label="Pages">';
    if ($page > 1) $o .= '<a href="' . $u($page - 1) . '" rel="prev">&larr; Previous</a>';
    $last = 0;
    for ($n = 1; $n <= $pages; $n++) {
        if ($n !== 1 && $n !== $pages && abs($n - $page) > 1) continue;
        if ($last && $n - $last > 1) $o .= '<span class="gap">&hellip;</span>';
        $o .= $n === $page ? '<span class="on" aria-current="page">' . $n . '</span>' : '<a href="' . $u($n) . '">' . $n . '</a>';
        $last = $n;
    }
    if ($page < $pages) $o .= '<a href="' . $u($page + 1) . '" rel="next">Next &rarr;</a>';
    return $o . '</nav>';
}

/** The AdSense IDs saved in CMS > Settings > Tracking & verification. */
function rocAdIds(): array {
    static $t = null;
    if ($t !== null) return $t;
    $t = [];
    $lib = __DIR__ . '/api/v1/cms/site-layer-lib.php';
    if (is_file($lib)) { require_once $lib; $t = (array)(rocLayerLoad(__DIR__)['tracking'] ?? []); }
    return $t;
}

/** One AdSense unit ($place: side, list or article), or '' while that place has no unit number. */
function rocAdUnit(string $place): string {
    $t = rocAdIds();
    $pub = trim((string)($t['adsense'] ?? '')); $slot = trim((string)($t['ad_' . $place] ?? ''));
    if (!preg_match('/^ca-pub-\d{10,20}$/', $pub) || !preg_match('/^\d{6,14}$/', $slot)) return '';
    return '<div class="rn-ad rn-ad-' . $place . '"><span class="rn-ad-l">Advertisement</span>'
        . '<ins class="adsbygoogle" style="display:block" data-ad-client="' . $pub . '" data-ad-slot="' . $slot . '" data-ad-format="auto" data-full-width-responsive="true"></ins>'
        . '<script>(adsbygoogle=window.adsbygoogle||[]).push({});</script></div>';
}

/** Right side box: the ad, or our own box (checker and products) while there is no ad unit. */
function rocAdSide(): string {
    $ad = rocAdUnit('side');
    if ($ad !== '') return $ad;
    return '<a class="rn-house" href="/compatibility/"><span class="rn-house-k">Free tool</span>'
        . '<strong>Can your PC run it?</strong><span class="rn-house-p">Check 2,000+ PC games against your graphics card, processor and memory.</span>'
        . '<span class="rn-house-b">Check a game &rarr;</span></a>'
        . '<a class="rn-house rn-house-2" href="/products/"><span class="rn-house-k">Gaming gear</span>'
        . '<strong>Our product picks</strong><span class="rn-house-p">Mice, keyboards, headsets, monitors, GPUs and more.</span>'
        . '<span class="rn-house-b">See the products &rarr;</span></a>';
}

/** A time in Pakistan time (the owner's readers): "Oct 13, 15:10 PKT". */
function rocPkt(int $ts, string $fmt = 'M j, H:i'): string {
    $d = new DateTime('@' . $ts);
    $d->setTimezone(new DateTimeZone('Asia/Karachi'));
    return $d->format($fmt) . ' PKT';
}

/** Category line over a story: "News · Oct 13, 15:10 PKT". */
function rocNewsMeta(array $p): string {
    $pd = rocPublished($p);
    return '<span class="rn-cat">' . rocH(rocCategoryLabel($p['category'] ?? '')) . ($pd ? ' &middot; ' . rocH(rocPkt($pd)) : '') . '</span>';
}

/** One story: picture, category line, title (and the summary when $excerpt). */
function rocNewsItem(array $p, string $cls, string $base, bool $excerpt = false, bool $lazy = true): string {
    $img = trim((string)($p['image'] ?? ''));
    $alt = trim((string)($p['image_alt'] ?? '')) ?: (string)$p['title'];
    return '<a class="rn-a ' . $cls . '" href="' . $base . rocH((string)$p['slug']) . '/">'
        . '<div class="rn-pic">' . ($img !== '' ? '<img width="1200" height="630" src="' . rocH($img) . '" alt="' . rocH($alt) . '"' . ($lazy ? ' loading="lazy"' : ' fetchpriority="high"') . '/>' : '') . '</div>'
        . '<div class="rn-txt">' . rocNewsMeta($p) . '<h2 class="rn-t">' . rocH($p['title']) . '</h2>'
        . ($excerpt && trim((string)($p['excerpt'] ?? '')) !== '' ? '<p class="rn-ex">' . rocH($p['excerpt']) . '</p>' : '') . '</div></a>';
}

/** Side list: the newest stories (news) or posts (blogs) with small pictures. */
function rocNewsSideList(PDO $pdo, bool $news, string $title, string $skipSlug = '', int $n = 6): string {
    $st = $pdo->prepare("SELECT title, slug, category, image, image_alt, published_at, created_at FROM blogs WHERE status = 'published' AND "
        . ($news ? "category = 'News'" : "(category IS NULL OR (category <> 'News' AND " . rocNotGuideSql() . "))") . ' AND slug <> ? ' . rocOrderSql() . ' LIMIT ' . $n);
    $st->execute([$skipSlug]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return '';
    $base = $news ? '/news/' : '/blogs/';
    $o = '<div class="rn-box"><h2 class="rn-h2">' . rocH($title) . '</h2>';
    foreach ($rows as $p) $o .= rocNewsItem($p, 'rn-side-item', $base);
    return $o . '<a class="rn-more" href="' . $base . '">' . ($news ? 'All news' : 'All articles') . ' &rarr;</a></div>';
}

/** The news list page: lead story with two beside it, then the list; ad and blogs on the right. */
function rocNewsListMain(PDO $pdo, array $posts, int $page, int $pages, string $query, int $total, int $allPublished): string {
    $base = '/news/';
    $o = '<div class="rn-wrap"><div class="rn-head"><h1>' . ($query !== '' ? 'News search' : 'Latest News') . '</h1>'
        . '<span class="rn-live"><i></i>Live</span>'
        . '<span class="rn-count">' . ($page > 1 ? 'Page ' . $page . ' of ' . $pages . ' &middot; ' : '') . $allPublished . ' stor' . ($allPublished === 1 ? 'y' : 'ies') . ', each checked against 2+ sources</span></div>'
        . '<div class="rn-grid"><div class="rn-main">';
    if ($query !== '') {
        $o .= '<p class="rn-found">' . $total . ' result' . ($total === 1 ? '' : 's') . ' for <strong>&ldquo;' . rocH($query) . '&rdquo;</strong> &middot; <a href="' . $base . '">Clear search</a></p>';
    }
    $rest = $posts;
    if ($page === 1 && $query === '' && count($posts) >= 1) {
        // The newest story on its own, full width; the ones before it below, newest first.
        $lead = array_shift($rest);
        $o .= '<div class="rn-top">' . rocNewsItem($lead, 'rn-lead', $base, true, false) . '</div>';
    }
    $o .= '<div class="rn-list">';
    foreach (array_values($rest) as $i => $p) {
        $o .= rocNewsItem($p, 'rn-row', $base, true, $page > 1 || $query !== '' ? $i > 1 : true);
        if ($i === 3) $o .= rocAdUnit('list');
    }
    $o .= '</div>';
    if (!$posts) $o .= '<p class="rn-found">' . ($query !== '' ? 'No news matches that search.' : 'No news yet. New stories appear here as soon as they are checked.') . '</p>';
    $o .= rocPager($base, $page, $pages, $query) . '</div>';
    $o .= '<aside class="rn-aside"><div class="rn-sticky">' . rocAdSide() . rocNewsSideList($pdo, false, 'From our blogs', '', 5) . '</div></aside>';
    return $o . '</div></div>';
}

/** Styles of the news pages and the page numbers (plain CSS: these pages have no React styles of their own). */
function rocNewsStyles(): string {
    return <<<CSS
<style>
.rn-wrap{padding:24px 0 8px}
.rn-head{display:flex;align-items:flex-end;flex-wrap:wrap;gap:10px 14px;border-bottom:3px solid #047857;padding-bottom:10px;margin-bottom:22px}
.rn-head h1{font-family:Outfit,Inter,sans-serif;font-weight:900;font-style:italic;text-transform:uppercase;font-size:1.7rem;line-height:1;color:#064e3b;margin:0}
.rn-live{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#be123c;background:#fff1f2;border-radius:999px;padding:4px 10px}
.rn-live i{width:7px;height:7px;border-radius:50%;background:#e11d48;animation:rnp 1.4s infinite}
@keyframes rnp{50%{opacity:.25}}
.rn-count{margin-left:auto;font-size:12px;color:#64748b;font-weight:600}
.rn-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:32px}
@media(min-width:1024px){.rn-grid{grid-template-columns:minmax(0,1fr) 300px}}
.rn-main{min-width:0}
a.rn-a{display:block;text-decoration:none;color:inherit}
.rn-pic{position:relative;overflow:hidden;background:#0f172a;border-radius:12px;aspect-ratio:1200/630}
.rn-pic img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .4s}
a.rn-a:hover .rn-pic img{transform:scale(1.04)}
.rn-cat{display:block;font-size:10.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#047857;margin:9px 0 4px}
.rn-t{font-family:Outfit,Inter,sans-serif;font-weight:800;color:#0f172a;line-height:1.3;margin:0;font-size:1rem}
a.rn-a:hover .rn-t{color:#047857}
.rn-ex{font-size:.86rem;color:#475569;line-height:1.55;margin:6px 0 0;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.rn-top{display:grid;grid-template-columns:minmax(0,1fr);gap:20px;padding-bottom:22px;border-bottom:1px solid #e2e8f0}
.rn-lead .rn-t{font-size:1.45rem}
.rn-two{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px}
.rn-small .rn-t{font-size:.92rem}
@media(min-width:768px){.rn-lead .rn-t{font-size:1.9rem}}
a.rn-row{display:grid;grid-template-columns:128px minmax(0,1fr);gap:14px;align-items:start;padding:16px 0;border-bottom:1px solid #e2e8f0}
.rn-row .rn-cat{margin-top:0}
.rn-row .rn-ex{display:none}
@media(min-width:640px){a.rn-row{grid-template-columns:240px minmax(0,1fr);gap:18px}.rn-row .rn-t{font-size:1.12rem}.rn-row .rn-ex{display:-webkit-box;-webkit-line-clamp:2}}
.rn-found{font-size:.9rem;color:#475569;margin:0 0 12px}
.rn-found a{color:#047857;font-weight:700}
.rn-aside{min-width:0}
.rn-sticky{display:flex;flex-direction:column;gap:22px}
@media(min-width:1024px){.rn-sticky{position:sticky;top:96px}}
.rn-ad{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:8px;min-height:100px;overflow:hidden}
.rn-ad-l{display:block;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.14em;color:#94a3b8;text-align:center;margin-bottom:4px}
.rn-ad-list{margin:16px 0}
a.rn-house{display:flex;flex-direction:column;gap:6px;border-radius:16px;padding:20px;background:linear-gradient(135deg,#064e3b,#047857);color:#fff;text-decoration:none;box-shadow:0 10px 24px -12px rgba(4,120,87,.6)}
a.rn-house-2{background:linear-gradient(135deg,#0f172a,#134e4a)}
.rn-house-k{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:#6ee7b7}
a.rn-house strong{font-family:Outfit,Inter,sans-serif;font-size:1.2rem;font-weight:800;line-height:1.2}
.rn-house-p{font-size:.8rem;color:#d1fae5;line-height:1.5}
.rn-house-b{align-self:flex-start;margin-top:6px;background:#34d399;color:#052e2b;font-weight:800;font-size:.78rem;padding:7px 12px;border-radius:10px}
a.rn-house:hover .rn-house-b{background:#6ee7b7}
.rn-box{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px}
.rn-h2{font-family:Outfit,Inter,sans-serif;font-weight:900;font-style:italic;text-transform:uppercase;font-size:1rem;color:#064e3b;margin:0 0 4px;padding-bottom:8px;border-bottom:2px solid #047857}
a.rn-side-item{display:grid;grid-template-columns:96px minmax(0,1fr);gap:10px;align-items:start;padding:11px 0;border-bottom:1px solid #f1f5f9}
.rn-side-item .rn-pic{border-radius:8px}
.rn-side-item .rn-cat{margin:0 0 2px;font-size:9.5px}
.rn-side-item .rn-t{font-size:.8rem;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.rn-more{display:inline-block;margin-top:10px;font-size:.8rem;font-weight:800;color:#047857;text-decoration:none}
.rn-pg{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;align-items:center;margin-top:28px}
.rn-pg a,.rn-pg span{min-width:40px;text-align:center;padding:9px 14px;border-radius:12px;border:1px solid #cbd5e1;background:#fff;color:#334155;font-weight:700;font-size:.85rem;text-decoration:none}
.rn-pg a:hover{border-color:#059669;color:#047857}
.rn-pg .on{background:#047857;border-color:#047857;color:#fff}
.rn-pg .gap{border:0;background:none;min-width:0;padding:9px 2px}
.rn-post{display:grid;grid-template-columns:minmax(0,1fr);gap:32px;max-width:1200px;margin:0 auto}
@media(min-width:1024px){.rn-post{grid-template-columns:minmax(0,1fr) 320px}.rn-post .rn-aside{padding-top:24px}}
.rn-ad-article{margin:0}
</style>

CSS;
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
    // CMS > Sitemap can leave addresses out (and noindex pages never belong in a sitemap).
    $lib = __DIR__ . '/api/v1/cms/site-layer-lib.php';
    if (is_file($lib)) { require_once $lib; if (rocLayerSitemapSkip(__DIR__, $loc)) return ''; }
    $x = function ($v) { return htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
    return '  <url><loc>' . $x($loc) . '</loc>' . ($mod ? '<lastmod>' . gmdate('c', $mod) . '</lastmod>' : '')
         . ($title !== '' ? '<roc:title>' . $x($title) . '</roc:title>' : '') . "</url>\n";
}


function rocSitemap(PDO $pdo): void {
    $rows = $pdo->query("SELECT slug, title, category, image, published_at, created_at, updated_at, content_modified_at
                           FROM blogs WHERE status = 'published' " . rocOrderSql())->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $out  = rocSmOpen();
    $latest = null; $latestNews = null;
    foreach ($rows as $r) {
        $t = rocModified($r);
        if (rocIsNews($r)) { if ($t && (!$latestNews || $t > $latestNews)) $latestNews = $t; }
        elseif ($t && (!$latest || $t > $latest)) $latest = $t;
    }
    // Blog posts only: news stories are in /sitemaps/news-sitemap.xml.
    $out .= rocSmLine(ROC_PUBLIC_URL . '/blogs/', $latest, 'Blogs');
    foreach ($rows as $r) if (!rocIsNews($r) && !rocIsGuide($r)) $out .= rocSmLine(rocPostUrl((string)$r['slug'], false), rocModified($r), (string)$r['title']);
    $out .= "</urlset>\n";
    echo $out;
    exit;
}

/** Google News sitemap: stories of the last 2 days (https://developers.google.com/search/docs/crawling-indexing/sitemaps/news-sitemap). */
function rocNewsSitemap(PDO $pdo): void {
    // Every news story (and /news/); the ones of the last 2 days also carry the Google News tags.
    $rows = $pdo->query("SELECT slug, title, published_at, created_at, updated_at, content_modified_at FROM blogs WHERE status = 'published' AND category = 'News' " . rocOrderSql() . ' LIMIT 5000')->fetchAll(PDO::FETCH_ASSOC);
    $x = function ($v) { return htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
         . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
    $latest = null;
    foreach ($rows as $r) { $t = rocModified($r); if ($t && (!$latest || $t > $latest)) $latest = $t; }
    $out .= '  <url><loc>' . ROC_PUBLIC_URL . '/news/</loc>' . ($latest ? '<lastmod>' . gmdate('c', $latest) . '</lastmod>' : '') . "</url>\n";
    foreach ($rows as $r) {
        $pub = rocDate($r['published_at']) ?: time();
        $mod = rocModified($r);
        $out .= '  <url><loc>' . $x(rocPostUrl((string)$r['slug'], true)) . '</loc>' . ($mod ? '<lastmod>' . gmdate('c', $mod) . '</lastmod>' : '');
        if ($pub >= time() - 2 * 86400) {
            $out .= '<news:news><news:publication><news:name>Run On Console</news:name><news:language>en</news:language></news:publication>'
                  . '<news:publication_date>' . gmdate('c', $pub) . '</news:publication_date><news:title>' . $x($r['title']) . '</news:title></news:news>';
        }
        $out .= "</url>\n";
    }
    echo $out . "</urlset>\n";
    exit;
}
