<?php
/**
 * Run On Console (ROC) - PHP Server-Side Content & SEO Hydration Router
 */

require_once __DIR__ . '/api/v1/config.php';

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = strtolower(trim(parse_url($uri, PHP_URL_PATH), '/'));
$baseUrl = defined('ROC_SITE_URL') ? rtrim(ROC_SITE_URL, '/') : 'https://www.runonconsole.com';

$pdo = getDBConnection();

// 1. Check 301 Redirects Table
if ($pdo && !empty($path)) {
    try {
        $stmtR = $pdo->prepare("SELECT new_slug, http_code FROM cms_redirects WHERE old_slug = ? LIMIT 1");
        $stmtR->execute([$path]);
        $redir = $stmtR->fetch();
        if ($redir) {
            $code = (int)($redir['http_code'] ?: 301);
            header("Location: {$baseUrl}/" . ltrim($redir['new_slug'], '/'), true, $code);
            exit();
        }
    } catch (\Throwable $e) {}
}

// Data container for SSR Hydration & Head Tags
$pageData = null;
$metaTitle = "Run On Console | PC Gaming Accessories, Gear & Benchmark Reviews";
$metaDesc = "Independent performance benchmarks, objective hardware reviews, and compatibility guides for PC gamers.";
$ogImage = "{$baseUrl}/social-logo.svg";
$canonicalUrl = "{$baseUrl}/" . ($path ? "{$path}/" : "");
$htmlBody = "";
$is404 = false;
$structuredData = null;

if ($pdo) {
    try {
        // Product Page SSR
        if (str_starts_with($path, 'products/')) {
            $slug = trim(substr($path, 9), '/');
            if (!empty($slug)) {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE (slug = ? OR id = ?) AND status = 'published' LIMIT 1");
                $stmt->execute([$slug, $slug]);
                $prod = $stmt->fetch();
                if ($prod) {
                    $metaTitle = ($prod['meta_title'] ?: $prod['title']) . " | Run On Console";
                    $metaDesc = $prod['meta_description'] ?: ($prod['short_desc'] ?: $prod['summary']);
                    if (!empty($prod['image'])) $ogImage = $prod['image'];

                    $pageData = [
                        'pageType' => 'product',
                        'product' => [
                            'id' => (string)$prod['id'],
                            'slug' => $prod['slug'],
                            'name' => $prod['title'],
                            'category' => $prod['category'],
                            'price' => $prod['price'] ? '$' . number_format((float)$prod['price'], 2) : null,
                            'priceState' => $prod['price_state'],
                            'rating' => (float)$prod['rating'],
                            'image' => $prod['image'],
                            'shortDesc' => $prod['short_desc'],
                            'summary' => $prod['summary'],
                            'pros' => $prod['pros'],
                            'cons' => $prod['cons']
                        ]
                    ];

                    $structuredData = [
                        "@context" => "https://schema.org/",
                        "@type" => "Product",
                        "name" => $prod['title'],
                        "image" => [$ogImage],
                        "description" => $metaDesc,
                        "sku" => $prod['id'],
                        "offers" => [
                            "@type" => "Offer",
                            "url" => $canonicalUrl,
                            "priceCurrency" => "USD",
                            "price" => (float)($prod['price'] ?: 0),
                            "availability" => "https://schema.org/InStock"
                        ]
                    ];

                    $priceStr = $prod['price'] ? '$' . number_format((float)$prod['price'], 2) : 'Check Store';
                    $htmlBody = "<article class='ssr-content'><h1>" . htmlspecialchars($prod['title']) . "</h1><p class='price'>Price: " . htmlspecialchars($priceStr) . "</p><p>" . htmlspecialchars($prod['summary'] ?: $prod['short_desc']) . "</p></article>";
                } else {
                    $is404 = true;
                }
            }
        }

        // Blog Page SSR
        elseif (str_starts_with($path, 'blogs/')) {
            $slug = trim(substr($path, 6), '/');
            if (!empty($slug)) {
                $stmt = $pdo->prepare("SELECT * FROM blogs WHERE (slug = ? OR id = ?) AND status = 'published' LIMIT 1");
                $stmt->execute([$slug, $slug]);
                $blog = $stmt->fetch();
                if ($blog) {
                    $metaTitle = ($blog['meta_title'] ?: $blog['title']) . " | Run On Console";
                    $metaDesc = $blog['meta_description'] ?: $blog['excerpt'];
                    if (!empty($blog['image'])) $ogImage = $blog['image'];

                    $pageData = [
                        'pageType' => 'blog',
                        'blog' => [
                            'id' => (string)$blog['id'],
                            'slug' => $blog['slug'],
                            'title' => $blog['title'],
                            'category' => $blog['category'],
                            'author' => $blog['author_name'],
                            'excerpt' => $blog['excerpt'],
                            'content' => $blog['content'],
                            'image' => $blog['image']
                        ]
                    ];

                    $structuredData = [
                        "@context" => "https://schema.org",
                        "@type" => "BlogPosting",
                        "headline" => $blog['title'],
                        "image" => [$ogImage],
                        "author" => [
                            "@type" => "Person",
                            "name" => $blog['author_name'] ?: "Run On Console Team"
                        ],
                        "publisher" => [
                            "@type" => "Organization",
                            "name" => "Run On Console",
                            "logo" => [
                                "@type" => "ImageObject",
                                "url" => "{$baseUrl}/social-logo.svg"
                            ]
                        ],
                        "datePublished" => $blog['published_at'] ?: $blog['created_at']
                    ];

                    $htmlBody = "<article class='ssr-content'><h1>" . htmlspecialchars($blog['title']) . "</h1><p class='author'>By " . htmlspecialchars($blog['author_name'] ?: 'Editorial Team') . "</p><div>" . $blog['content'] . "</div></article>";
                } else {
                    $is404 = true;
                }
            }
        }

        // Category Page SSR
        elseif (str_starts_with($path, 'categories/')) {
            $slug = trim(substr($path, 11), '/');
            if (!empty($slug)) {
                $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? LIMIT 1");
                $stmt->execute([$slug]);
                $cat = $stmt->fetch();
                if ($cat) {
                    $metaTitle = ($cat['meta_title'] ?: $cat['name']) . " Gear & Reviews | Run On Console";
                    $metaDesc = $cat['meta_description'] ?: $cat['description'];

                    $pageData = [
                        'pageType' => 'category',
                        'category' => [
                            'id' => (string)$cat['id'],
                            'name' => $cat['name'],
                            'slug' => $cat['slug'],
                            'description' => $cat['description']
                        ]
                    ];

                    $structuredData = [
                        "@context" => "https://schema.org",
                        "@type" => "CollectionPage",
                        "name" => $cat['name'],
                        "description" => $metaDesc,
                        "url" => $canonicalUrl
                    ];

                    $htmlBody = "<section class='ssr-content'><h1>" . htmlspecialchars($cat['name']) . "</h1><p>" . htmlspecialchars($cat['description'] ?? '') . "</p></section>";
                } else {
                    $is404 = true;
                }
            }
        }

        // Author Page SSR
        elseif (str_starts_with($path, 'author/')) {
            $slug = trim(substr($path, 7), '/');
            if (!empty($slug)) {
                $stmt = $pdo->prepare("SELECT * FROM authors WHERE (slug = ? OR id = ?) AND status = 'published' AND is_noindex = 0 LIMIT 1");
                $stmt->execute([$slug, $slug]);
                $author = $stmt->fetch();
                if ($author) {
                    $metaTitle = htmlspecialchars($author['name']) . " - " . htmlspecialchars($author['title'] ?: 'Senior Hardware Columnist') . " | Run On Console";
                    $metaDesc = htmlspecialchars($author['bio'] ?: "Biography and articles published by {$author['name']}.");
                    if (!empty($author['avatar_image'])) $ogImage = $author['avatar_image'];

                    $pageData = [
                        'pageType' => 'author',
                        'author' => [
                            'id' => (int)$author['id'],
                            'name' => $author['name'],
                            'slug' => $author['slug'],
                            'title' => $author['title'],
                            'bio' => $author['bio'],
                            'avatar' => $author['avatar_image']
                        ]
                    ];

                    $structuredData = [
                        "@context" => "https://schema.org",
                        "@type" => "Person",
                        "name" => $author['name'],
                        "jobTitle" => $author['title'],
                        "description" => $author['bio'],
                        "image" => $ogImage
                    ];

                    $htmlBody = "<article class='ssr-content'><h1>" . htmlspecialchars($author['name']) . "</h1><p class='title'>" . htmlspecialchars($author['title']) . "</p><p>" . htmlspecialchars($author['bio'] ?? '') . "</p></article>";
                } else {
                    $is404 = true;
                }
            }
        }
    } catch (\Throwable $e) {
        error_log("PHP SSR Router Error: " . $e->getMessage());
    }
}

// Handle 404 Status Response for Unpublished or Non-Existent Pages
if ($is404) {
    http_response_code(404);
    $metaTitle = "Page Not Found | Run On Console";
    $metaDesc = "The requested page could not be found on Run On Console.";
    $htmlBody = "<section class='ssr-content'><h1>404 - Page Not Found</h1><p>The requested page or author profile is not available.</p></section>";
}

// Read index.html template
$indexPath = __DIR__ . '/index.html';
$html = file_exists($indexPath) ? file_get_contents($indexPath) : "<!DOCTYPE html><html><head></head><body><div id='root'></div></body></html>";

// Inject dynamic Head Tags
$headTags = "<title>" . htmlspecialchars($metaTitle) . "</title>\n";
$headTags .= '  <meta name="description" content="' . htmlspecialchars($metaDesc) . '" />' . "\n";
$headTags .= '  <link rel="canonical" href="' . htmlspecialchars($canonicalUrl) . '" />' . "\n";
$headTags .= '  <meta property="og:title" content="' . htmlspecialchars($metaTitle) . '" />' . "\n";
$headTags .= '  <meta property="og:description" content="' . htmlspecialchars($metaDesc) . '" />' . "\n";
$headTags .= '  <meta property="og:image" content="' . htmlspecialchars($ogImage) . '" />' . "\n";
$headTags .= '  <meta property="og:url" content="' . htmlspecialchars($canonicalUrl) . '" />' . "\n";

if ($structuredData !== null) {
    $sdJson = json_encode($structuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    $headTags .= "  <script type=\"application/ld+json\">{$sdJson}</script>\n";
}

if ($pageData !== null) {
    $jsonPayload = json_encode($pageData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    $headTags .= "  <script>window.__INITIAL_CONTENT__ = {$jsonPayload};</script>\n";
}

// Replace title & inject head tags
if (str_contains($html, '<title>')) {
    $html = preg_replace('/<title>.*?<\/title>/s', $headTags, $html, 1);
} else {
    $html = str_replace('</head>', "{$headTags}</head>", $html);
}

// Inject SSR HTML content into root div
if (!empty($htmlBody)) {
    $html = str_replace('<div id="root"></div>', "<div id=\"root\">{$htmlBody}</div>", $html);
}

header("Content-Type: text/html; charset=UTF-8");
echo $html;
