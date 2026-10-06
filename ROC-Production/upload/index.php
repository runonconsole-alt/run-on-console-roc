<?php
/**
 * Run On Console (ROC) — PHP Server-Side Content & SEO Hydration Router
 *
 * Changes from the previous version:
 *
 *  - The template's own meta tags are stripped before the route's tags are
 *    injected. Previously only <title> was replaced, so every CMS-served page
 *    carried the home page's og:title, og:image and canonical alongside its
 *    own — two canonicals on a page means Google trusts neither, and social
 *    scrapers take the first og:image they find, which was the home hero.
 *  - og:type, og:site_name, the four twitter:* tags and <meta name="robots">
 *    are emitted. prerender.mjs already writes these, so without them every
 *    CMS-served page was losing its Twitter card and robots directive relative
 *    to the static build.
 *  - is_noindex is honoured for blogs, products and categories, not only for
 *    authors. The column was being written by the CMS and ignored here, so a
 *    post marked noindex stayed indexable.
 *  - The Open Graph, Twitter, canonical and schema columns added by
 *    cli-migrate-seo-fields.php are read, with fallbacks when they are empty.
 */

require_once __DIR__ . '/api/v1/config.php';

// Shared sanitiser and schema helpers. Optional so the router still works if
// the file has not been deployed yet.
$rocHelpers = __DIR__ . '/api/v1/cms/cms-html.php';
if (file_exists($rocHelpers)) require_once $rocHelpers;

$uri  = $_SERVER['REQUEST_URI'] ?? '/';
$path = strtolower(trim(parse_url($uri, PHP_URL_PATH), '/'));
$baseUrl = defined('ROC_SITE_URL') ? rtrim(ROC_SITE_URL, '/') : 'https://www.runonconsole.com';
$siteHost = parse_url($baseUrl, PHP_URL_HOST) ?: 'runonconsole.com';

$pdo = getDBConnection();

/* ------------------------------------------------------ 301 redirects */

if ($pdo && !empty($path)) {
    try {
        $stmtR = $pdo->prepare('SELECT new_slug, http_code FROM cms_redirects WHERE old_slug = ? LIMIT 1');
        $stmtR->execute([$path]);
        $redir = $stmtR->fetch();
        if ($redir) {
            $code = (int)($redir['http_code'] ?: 301);
            header('Location: ' . $baseUrl . '/' . ltrim($redir['new_slug'], '/'), true, $code);
            exit();
        }
    } catch (\Throwable $e) { /* redirects table is optional */ }
}

/* ----------------------------------------------------------- defaults */

$pageData      = null;
$metaTitle     = 'Run On Console | PC Gaming Accessories, Gear & Benchmark Reviews';
$metaDesc      = 'Independent performance benchmarks, objective hardware reviews, and compatibility guides for PC gamers.';
$ogImage       = "{$baseUrl}/social-logo.svg";
$ogType        = 'website';
$ogTitle       = null;      // falls back to $metaTitle
$ogDesc        = null;      // falls back to $metaDesc
$twitterCard   = 'summary_large_image';
$twitterTitle  = null;
$twitterDesc   = null;
$twitterImage  = null;
$canonicalUrl  = $baseUrl . '/' . ($path ? "{$path}/" : '');
$robots        = 'index, follow';
$htmlBody      = '';
$is404         = false;
$structuredData = null;

/** Turn a stored image path into an absolute URL. */
$absolute = function (?string $value) use ($baseUrl): string {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (preg_match('#^https?://#i', $value)) return $value;
    return $baseUrl . '/' . ltrim($value, '/');
};

/** Build the robots directive from the row's flags. */
$robotsFrom = function (array $row): string {
    $index  = !empty($row['is_noindex'])  ? 'noindex'  : 'index';
    $follow = !empty($row['is_nofollow']) ? 'nofollow' : 'follow';
    return "{$index}, {$follow}";
};

if ($pdo) {
    try {
        /* ------------------------------------------------ product page */
        if (str_starts_with($path, 'products/')) {
            $slug = trim(substr($path, 9), '/');
            if ($slug !== '') {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE (slug = ? OR id = ?) AND status = 'published' LIMIT 1");
                $stmt->execute([$slug, $slug]);
                $prod = $stmt->fetch();

                if ($prod) {
                    $metaTitle = ($prod['meta_title'] ?: $prod['title']) . ' | Run On Console';
                    $metaDesc  = $prod['meta_description'] ?: ($prod['short_desc'] ?: $prod['summary']);
                    $robots    = $robotsFrom($prod);
                    if (!empty($prod['canonical_url'])) $canonicalUrl = $prod['canonical_url'];

                    $ogImage      = $absolute($prod['og_image'] ?? '') ?: ($absolute($prod['image'] ?? '') ?: $ogImage);
                    $ogType       = 'product';
                    $ogTitle      = $prod['og_title'] ?? '';
                    $ogDesc       = $prod['og_description'] ?? '';
                    $twitterCard  = $prod['twitter_card'] ?: 'summary_large_image';
                    $twitterTitle = $prod['twitter_title'] ?? '';
                    $twitterDesc  = $prod['twitter_description'] ?? '';
                    $twitterImage = $absolute($prod['twitter_image'] ?? '');

                    $pageData = [
                        'pageType' => 'product',
                        'product'  => [
                            'id'         => (string)$prod['id'],
                            'slug'       => $prod['slug'],
                            'name'       => $prod['title'],
                            'category'   => $prod['category'],
                            'price'      => $prod['price'] ? '$' . number_format((float)$prod['price'], 2) : null,
                            'priceState' => $prod['price_state'] ?? null,
                            'rating'     => (float)($prod['rating'] ?? 0),
                            'image'      => $absolute($prod['image'] ?? ''),
                            'imageAlt'   => $prod['image_alt'] ?? '',
                            'shortDesc'  => $prod['short_desc'] ?? '',
                            'summary'    => $prod['summary'] ?? '',
                            'pros'       => $prod['pros'] ?? '',
                            'cons'       => $prod['cons'] ?? '',
                        ],
                    ];

                    if (!empty($prod['schema_json'])) {
                        $decoded = json_decode($prod['schema_json'], true);
                        if (is_array($decoded)) $structuredData = $decoded;
                    }
                    if ($structuredData === null) {
                        $structuredData = [
                            '@context' => 'https://schema.org/',
                            '@type'    => $prod['schema_type'] ?: 'Product',
                            'name'     => $prod['title'],
                            'image'    => [$ogImage],
                            'description' => $metaDesc,
                            'sku'      => (string)$prod['id'],
                            'offers'   => [
                                '@type'         => 'Offer',
                                'url'           => $canonicalUrl,
                                'priceCurrency' => 'USD',
                                'price'         => (float)($prod['price'] ?: 0),
                                'availability'  => 'https://schema.org/InStock',
                            ],
                        ];
                        if (!empty($prod['rating']) && (float)$prod['rating'] > 0) {
                            $structuredData['aggregateRating'] = [
                                '@type'       => 'AggregateRating',
                                'ratingValue' => (string)(float)$prod['rating'],
                                'bestRating'  => '5',
                                'ratingCount' => '1',
                            ];
                        }
                    }

                    $priceStr = $prod['price'] ? '$' . number_format((float)$prod['price'], 2) : 'Check Store';
                    $htmlBody = "<article class='ssr-content'><h1>" . htmlspecialchars($prod['title'])
                              . "</h1><p class='price'>Price: " . htmlspecialchars($priceStr) . '</p><p>'
                              . htmlspecialchars($prod['summary'] ?: $prod['short_desc']) . '</p></article>';
                } else {
                    $is404 = true;
                }
            }
        }

        /* --------------------------------------------------- blog page */
        elseif (str_starts_with($path, 'blogs/')) {
            $slug = trim(substr($path, 6), '/');
            if ($slug !== '') {
                $stmt = $pdo->prepare("SELECT * FROM blogs WHERE (slug = ? OR id = ?) AND status = 'published' LIMIT 1");
                $stmt->execute([$slug, $slug]);
                $blog = $stmt->fetch();

                if ($blog) {
                    $metaTitle = ($blog['meta_title'] ?: $blog['title']) . ' | Run On Console';
                    $metaDesc  = $blog['meta_description'] ?: $blog['excerpt'];
                    $robots    = $robotsFrom($blog);
                    if (!empty($blog['canonical_url'])) $canonicalUrl = $blog['canonical_url'];

                    $ogImage      = $absolute($blog['og_image'] ?? '') ?: ($absolute($blog['image'] ?? '') ?: $ogImage);
                    $ogType       = $blog['og_type'] ?: 'article';
                    $ogTitle      = $blog['og_title'] ?? '';
                    $ogDesc       = $blog['og_description'] ?? '';
                    $twitterCard  = $blog['twitter_card'] ?: 'summary_large_image';
                    $twitterTitle = $blog['twitter_title'] ?? '';
                    $twitterDesc  = $blog['twitter_description'] ?? '';
                    $twitterImage = $absolute($blog['twitter_image'] ?? '');

                    $pageData = [
                        'pageType' => 'blog',
                        'blog'     => [
                            'id'       => (string)$blog['id'],
                            'slug'     => $blog['slug'],
                            'title'    => $blog['title'],
                            'category' => $blog['category'],
                            'author'   => $blog['author_name'],
                            'excerpt'  => $blog['excerpt'],
                            'content'  => $blog['content'],
                            'image'    => $absolute($blog['image'] ?? ''),
                            'imageAlt' => $blog['image_alt'] ?? '',
                            'publishedAt' => $blog['published_at'] ?? null,
                        ],
                    ];

                    $structuredData = function_exists('rocCmsBlogSchema')
                        ? rocCmsBlogSchema($blog, $baseUrl)
                        : [
                            '@context'  => 'https://schema.org',
                            '@type'     => $blog['schema_type'] ?: 'BlogPosting',
                            'headline'  => $blog['title'],
                            'image'     => [$ogImage],
                            'author'    => ['@type' => 'Person', 'name' => $blog['author_name'] ?: 'Run On Console Team'],
                            'publisher' => [
                                '@type' => 'Organization',
                                'name'  => 'Run On Console',
                                'logo'  => ['@type' => 'ImageObject', 'url' => "{$baseUrl}/social-logo.svg"],
                            ],
                            'datePublished' => $blog['published_at'] ?: $blog['created_at'],
                        ];

                    // Body HTML comes from the CMS, which sanitises on save. It is
                    // printed unescaped on purpose; re-sanitise here when the helper
                    // is present, in case a row predates the sanitiser.
                    $body = $blog['content'];
                    if (function_exists('rocCmsSanitizeHtml')) {
                        $body = rocCmsSanitizeHtml($body, $siteHost);
                    }
                    $htmlBody = "<article class='ssr-content'><h1>" . htmlspecialchars($blog['title'])
                              . "</h1><p class='author'>By "
                              . htmlspecialchars($blog['author_name'] ?: 'Editorial Team')
                              . "</p><div>{$body}</div></article>";
                } else {
                    $is404 = true;
                }
            }
        }

        /* ----------------------------------------------- category page */
        elseif (str_starts_with($path, 'categories/')) {
            $slug = trim(substr($path, 11), '/');
            if ($slug !== '') {
                $stmt = $pdo->prepare('SELECT * FROM categories WHERE slug = ? LIMIT 1');
                $stmt->execute([$slug]);
                $cat = $stmt->fetch();

                // A category that is not published should 404 like anything else.
                if ($cat && isset($cat['status']) && $cat['status'] !== 'published') $cat = null;

                if ($cat) {
                    $metaTitle = ($cat['meta_title'] ?: $cat['name']) . ' Gear & Reviews | Run On Console';
                    $metaDesc  = $cat['meta_description'] ?: $cat['description'];
                    $robots    = $robotsFrom($cat);
                    if (!empty($cat['canonical_url'])) $canonicalUrl = $cat['canonical_url'];
                    $ogType = 'website';

                    $pageData = [
                        'pageType' => 'category',
                        'category' => [
                            'id'          => (string)$cat['id'],
                            'name'        => $cat['name'],
                            'slug'        => $cat['slug'],
                            'description' => $cat['description'],
                        ],
                    ];

                    $structuredData = [
                        '@context'    => 'https://schema.org',
                        '@type'       => 'CollectionPage',
                        'name'        => $cat['name'],
                        'description' => $metaDesc,
                        'url'         => $canonicalUrl,
                    ];

                    $htmlBody = "<section class='ssr-content'><h1>" . htmlspecialchars($cat['name'])
                              . '</h1><p>' . htmlspecialchars($cat['description'] ?? '') . '</p></section>';
                } else {
                    $is404 = true;
                }
            }
        }

        /* ------------------------------------------------- author page */
        elseif (str_starts_with($path, 'author/')) {
            $slug = trim(substr($path, 7), '/');
            if ($slug !== '') {
                $stmt = $pdo->prepare("SELECT * FROM authors WHERE (slug = ? OR id = ?) AND status = 'published' AND is_noindex = 0 LIMIT 1");
                $stmt->execute([$slug, $slug]);
                $author = $stmt->fetch();

                if ($author) {
                    $metaTitle = $author['name'] . ' - ' . ($author['title'] ?: 'Senior Hardware Columnist') . ' | Run On Console';
                    $metaDesc  = $author['bio'] ?: "Biography and articles published by {$author['name']}.";
                    $ogImage   = $absolute($author['avatar_image'] ?? '') ?: $ogImage;
                    $ogType    = 'profile';

                    $pageData = [
                        'pageType' => 'author',
                        'author'   => [
                            'id'     => (int)$author['id'],
                            'name'   => $author['name'],
                            'slug'   => $author['slug'],
                            'title'  => $author['title'],
                            'bio'    => $author['bio'],
                            'avatar' => $ogImage,
                        ],
                    ];

                    $structuredData = [
                        '@context'    => 'https://schema.org',
                        '@type'       => 'Person',
                        'name'        => $author['name'],
                        'jobTitle'    => $author['title'],
                        'description' => $author['bio'],
                        'image'       => $ogImage,
                        'url'         => $canonicalUrl,
                    ];

                    $htmlBody = "<article class='ssr-content'><h1>" . htmlspecialchars($author['name'])
                              . "</h1><p class='title'>" . htmlspecialchars($author['title'] ?? '')
                              . '</p><p>' . htmlspecialchars($author['bio'] ?? '') . '</p></article>';
                } else {
                    $is404 = true;
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('PHP SSR Router Error: ' . $e->getMessage());
    }
}

/* ---------------------------------------------------------------- 404 */

if ($is404) {
    http_response_code(404);
    $metaTitle = 'Page Not Found | Run On Console';
    $metaDesc  = 'The requested page could not be found on Run On Console.';
    $robots    = 'noindex, follow';
    $canonicalUrl = '';
    $structuredData = null;
    $pageData = null;
    $htmlBody = "<section class='ssr-content'><h1>404 - Page Not Found</h1>"
              . '<p>The requested page or author profile is not available.</p></section>';
}

/* ------------------------------------------------------------ render */

// Pick the template. .htaccess sends several purely static routes here too
// (/about/, /contact/, /policy/ and friends), and none of them have a database
// branch above. Falling back to the home page's index.html would serve the home
// page under their URLs and wipe their prerendered meta, so use each route's own
// prerendered file when one exists and the CMS had nothing to say about it.
$homeTemplate = __DIR__ . '/index.html';
$routeTemplate = $homeTemplate;

if ($path !== '' && !$is404) {
    $candidate = __DIR__ . '/' . $path . '/index.html';
    $real = realpath($candidate);
    // realpath() also rules out any ../ traversal in the request path.
    if ($real && str_starts_with($real, realpath(__DIR__)) && is_file($real)) {
        $routeTemplate = $real;
    }
}

// When the CMS supplied nothing for this route and a prerendered page exists,
// serve that file untouched. It already carries correct meta from prerender.mjs.
$servedFromPrerender = ($pageData === null && $structuredData === null
                        && $htmlBody === '' && $routeTemplate !== $homeTemplate);

if ($servedFromPrerender) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($routeTemplate);
    exit();
}

$indexPath = $routeTemplate;
$html = file_exists($indexPath)
    ? file_get_contents($indexPath)
    : "<!DOCTYPE html><html><head></head><body><div id='root'></div></body></html>";

// The template is the prerendered home page, so it arrives with a full set of
// meta tags. Remove them before adding this route's, or the page ends up with
// two canonicals and the home page's share image.
$html = preg_replace('/<title>[\s\S]*?<\/title>/i', '', $html, 1);
$html = preg_replace('/[ \t]*<meta\s+name=["\'](?:title|description|robots|canonical)["\'][^>]*>\s*/i', '', $html);
$html = preg_replace('/[ \t]*<meta\s+property=["\']og:[^"\']*["\'][^>]*>\s*/i', '', $html);
$html = preg_replace('/[ \t]*<meta\s+name=["\']twitter:[^"\']*["\'][^>]*>\s*/i', '', $html);
$html = preg_replace('/[ \t]*<link\s+rel=["\']canonical["\'][^>]*>\s*/i', '', $html);
$html = preg_replace('/<script\s+type=["\']application\/ld\+json["\']>[\s\S]*?<\/script>\s*/i', '', $html);

$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$finalOgTitle      = $ogTitle      ?: $metaTitle;
$finalOgDesc       = $ogDesc       ?: $metaDesc;
$finalTwitterTitle = $twitterTitle ?: $finalOgTitle;
$finalTwitterDesc  = $twitterDesc  ?: $finalOgDesc;
$finalTwitterImage = $twitterImage ?: $ogImage;

$headTags  = '<title>' . $e($metaTitle) . "</title>\n";
$headTags .= '  <meta name="title" content="' . $e($metaTitle) . "\" />\n";
$headTags .= '  <meta name="description" content="' . $e($metaDesc) . "\" />\n";
$headTags .= '  <meta name="robots" content="' . $e($robots) . "\" />\n";
if ($canonicalUrl !== '') {
    $headTags .= '  <link rel="canonical" href="' . $e($canonicalUrl) . "\" />\n";
}
$headTags .= '  <meta property="og:type" content="' . $e($ogType) . "\" />\n";
$headTags .= '  <meta property="og:site_name" content="Run On Console" />' . "\n";
$headTags .= '  <meta property="og:title" content="' . $e($finalOgTitle) . "\" />\n";
$headTags .= '  <meta property="og:description" content="' . $e($finalOgDesc) . "\" />\n";
$headTags .= '  <meta property="og:image" content="' . $e($ogImage) . "\" />\n";
if ($canonicalUrl !== '') {
    $headTags .= '  <meta property="og:url" content="' . $e($canonicalUrl) . "\" />\n";
}
$headTags .= '  <meta name="twitter:card" content="' . $e($twitterCard) . "\" />\n";
$headTags .= '  <meta name="twitter:title" content="' . $e($finalTwitterTitle) . "\" />\n";
$headTags .= '  <meta name="twitter:description" content="' . $e($finalTwitterDesc) . "\" />\n";
$headTags .= '  <meta name="twitter:image" content="' . $e($finalTwitterImage) . "\" />\n";
if ($canonicalUrl !== '') {
    $headTags .= '  <meta name="twitter:url" content="' . $e($canonicalUrl) . "\" />\n";
}

if ($structuredData !== null) {
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
    $headTags .= '  <script type="application/ld+json">' . json_encode($structuredData, $flags) . "</script>\n";
}

if ($pageData !== null) {
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
    $headTags .= '  <script>window.__INITIAL_CONTENT__ = ' . json_encode($pageData, $flags) . ";</script>\n";
}

// The template no longer has a <title>, so insert before </head>.
$html = preg_replace('#</head>#i', "  {$headTags}</head>", $html, 1);

if ($htmlBody !== '') {
    $html = preg_replace('#<div id="root">\s*</div>#i', "<div id=\"root\">{$htmlBody}</div>", $html, 1);
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: ' . $robots);
echo $html;
