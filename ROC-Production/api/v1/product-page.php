<?php
/**
 * Run On Console — Dynamic Product Page
 * Serves /products/{slug}/ from the database instead of static HTML.
 *
 * Setup (in public_html/.htaccess, AFTER the existing static-file rules):
 *
 *   # Dynamic product pages — serve from PHP when static index.html doesn't exist
 *   RewriteCond %{REQUEST_URI} ^/products/([a-z0-9][a-z0-9\-]*)/?$ [NC]
 *   RewriteCond %{DOCUMENT_ROOT}/products/%1/index.html !-f
 *   RewriteRule ^products/([a-z0-9][a-z0-9\-]*)/?$ /api/v1/product-page.php?slug=$1 [QSA,L]
 *
 * NOTE: Existing static product pages (already built) take priority — PHP only
 * kicks in for NEW products added via CMS that don't have a pre-built HTML page.
 */

require_once __DIR__ . '/config.php';

$slug = trim($_GET['slug'] ?? preg_replace('#.*/products/([a-z0-9-]+)/?.*#i', '$1', $_SERVER['REQUEST_URI'] ?? ''));
$slug = strtolower(preg_replace('/[^a-z0-9-]/', '', $slug));

if ($slug === '') {
    header('Location: /products/', true, 302);
    exit;
}

$pdo = getDBConnection();
if (!$pdo) { http_response_code(503); echo 'Service unavailable'; exit; }

// ── Load product ───────────────────────────────────────────────────────────────
$st = $pdo->prepare("SELECT * FROM products WHERE slug = ? AND status = 'published' LIMIT 1");
$st->execute([$slug]);
$prod = $st->fetch(PDO::FETCH_ASSOC);

if (!$prod) {
    http_response_code(301);
    header('Location: /products/');
    exit;
}

// ── Amazon tag ────────────────────────────────────────────────────────────────
function getTag(PDO $pdo): string {
    try {
        $st = $pdo->prepare("SELECT setting_value FROM cms_settings WHERE setting_key='amazon_tag' LIMIT 1");
        $st->execute();
        $v = $st->fetchColumn();
        return ($v && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,62}-\d{2}$/', (string)$v)) ? (string)$v : 'roc2602-20';
    } catch (\Throwable $e) { return 'roc2602-20'; }
}

function withTag(string $url, string $tag): string {
    if (!$url || !$tag) return $url;
    $url = preg_replace('/([?&])tag=[^&]*/i', '$1', $url);
    $url = rtrim($url, '?&');
    return $url . (str_contains($url, '?') ? '&' : '?') . 'tag=' . urlencode($tag);
}

$amazonTag  = getTag($pdo);
$affilJson  = $prod['affiliate_links_json'] ?? '{}';
$affil      = json_decode($affilJson, true) ?: [];
$amazonLink = $affil['amazon'] ?? $prod['affiliate_amazon'] ?? '';
if ($amazonLink && $amazonTag) $amazonLink = withTag($amazonLink, $amazonTag);

$specsRaw = json_decode($prod['specs_json'] ?? '{}', true) ?: [];
$pros     = json_decode($prod['pros']       ?? '[]', true) ?: [];
$cons     = json_decode($prod['cons']       ?? '[]', true) ?: [];

$title   = htmlspecialchars($prod['title'],     ENT_QUOTES, 'UTF-8');
$desc    = htmlspecialchars($prod['short_desc'] ?? $prod['summary'] ?? '', ENT_QUOTES, 'UTF-8');
$image   = htmlspecialchars($prod['image']      ?? '/images/cyber_keyboard.jpg', ENT_QUOTES, 'UTF-8');
$cat     = htmlspecialchars($prod['category']   ?? '', ENT_QUOTES, 'UTF-8');
$brand   = htmlspecialchars($prod['brand']      ?? '', ENT_QUOTES, 'UTF-8');
$badge   = htmlspecialchars($prod['badge']      ?? '', ENT_QUOTES, 'UTF-8');

$specsHtml = '';
foreach ($specsRaw as $k => $v) {
    $specsHtml .= '<tr><th>' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '</th>'
                . '<td>'    . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '</td></tr>';
}

$prosHtml = implode('', array_map(fn($p) => '<li>' . htmlspecialchars((string)$p, ENT_QUOTES, 'UTF-8') . '</li>', $pros));
$consHtml = implode('', array_map(fn($c) => '<li>' . htmlspecialchars((string)$c, ENT_QUOTES, 'UTF-8') . '</li>', $cons));

$canonicalUrl  = 'https://runonconsole.com/products/' . $slug . '/';
$metaTitle     = $title . ': Specs & Where to Buy | Run On Console';
$ogImage       = str_starts_with($image, 'http') ? $image : 'https://runonconsole.com' . $image;
$amazonBtn     = $amazonLink
    ? '<a href="' . htmlspecialchars($amazonLink, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="sponsored nofollow noopener noreferrer" class="roc-btn-amazon">🛒 Check price on Amazon</a>'
    : '';
$badgeHtml     = $badge ? '<span class="roc-badge">' . $badge . '</span>' : '';
$updatedAt     = $prod['updated_at'] ?? $prod['content_modified_at'] ?? date('c');

header('Content-Type: text/html; charset=utf-8');
header('X-ROC-Dynamic: 1');

// ── Load the base index.html to reuse the same JS bundle and global styles ────
$indexFile = __DIR__ . '/../../index.html';
$baseHtml  = is_file($indexFile) ? file_get_contents($indexFile) : '';

// Patch canonical, title, meta description, og tags in the base HTML
if ($baseHtml) {
    // Title
    $baseHtml = preg_replace('#<title>[^<]*</title>#i',
        '<title>' . htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') . '</title>', $baseHtml, 1);
    // Description
    $baseHtml = preg_replace('#<meta\s[^>]*name=["\']description["\'][^>]*>#i',
        '<meta name="description" content="' . $desc . '" />', $baseHtml, 1);
    // OG title
    $baseHtml = preg_replace('#<meta\s[^>]*property=["\']og:title["\'][^>]*>#i',
        '<meta property="og:title" content="' . htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') . '" />', $baseHtml, 1);
    // OG description
    $baseHtml = preg_replace('#<meta\s[^>]*property=["\']og:description["\'][^>]*>#i',
        '<meta property="og:description" content="' . $desc . '" />', $baseHtml, 1);
    // OG image
    $baseHtml = preg_replace('#<meta\s[^>]*property=["\']og:image["\'][^>]*>#i',
        '<meta property="og:image" content="' . htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') . '" />', $baseHtml, 1);
    // Canonical
    $baseHtml = preg_replace('#<link\s[^>]*rel=["\']canonical["\'][^>]*>#i',
        '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') . '" />', $baseHtml, 1);

    // Inject structured data + initial product data before </head>
    $structuredData = json_encode([
        '@context' => 'https://schema.org',
        '@type'    => 'Product',
        'name'     => $prod['title'],
        'image'    => $ogImage,
        'description' => $prod['short_desc'] ?? '',
        'brand'    => ['@type' => 'Brand', 'name' => $prod['brand'] ?? 'Run On Console'],
        'offers'   => ['@type' => 'Offer', 'availability' => 'https://schema.org/InStock', 'url' => $amazonLink ?: $canonicalUrl],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Pass product data to React so it can hydrate instantly (no flash)
    $productJson = json_encode([
        'id'           => $prod['id'],
        'slug'         => $slug,
        'title'        => $prod['title'],
        'subtitle'     => $prod['summary']    ?? $prod['short_desc'] ?? '',
        'shortDesc'    => $prod['short_desc'] ?? '',
        'brand'        => $prod['brand']      ?? '',
        'brandName'    => $prod['brand']      ?? '',
        'category'     => $prod['category']   ?? '',
        'categorySlug' => $prod['category_slug'] ?? '',
        'badge'        => $prod['badge']      ?? null,
        'image'        => $prod['image']      ?? '/images/cyber_keyboard.jpg',
        'affiliateLinks' => ['amazon' => $amazonLink],
        'specs'        => $specsRaw,
        'pros'         => $pros,
        'cons'         => $cons,
        'features'     => $pros,
        'status'       => 'published',
        'lastmod'      => $updatedAt,
        'url'          => $canonicalUrl,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $inject = "\n  <script type=\"application/ld+json\">{$structuredData}</script>"
            . "\n  <script>window.__ROC_PRODUCT__={$productJson};window.__ROC_DYNAMIC__=true;</script>";

    $baseHtml = preg_replace('#</head>#i', $inject . "\n</head>", $baseHtml, 1);

    echo $baseHtml;
    exit;
}

// ── Fallback: standalone product page (if no index.html found) ────────────────
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="<?= $desc ?>" />
  <meta property="og:title"       content="<?= htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:description" content="<?= $desc ?>" />
  <meta property="og:image"       content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:system-ui,sans-serif;background:#F8FAFC;color:#0F172A;padding:0}
    .roc-wrap{max-width:900px;margin:0 auto;padding:32px 16px}
    .roc-breadcrumb{font-size:12px;color:#64748b;margin-bottom:24px}
    .roc-breadcrumb a{color:#64748b;text-decoration:none}
    .roc-breadcrumb a:hover{color:#059669}
    .roc-grid{display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:start}
    @media(max-width:640px){.roc-grid{grid-template-columns:1fr}}
    .roc-img{width:100%;border-radius:16px;object-fit:cover;max-height:340px;background:#0f172a}
    .roc-badge{display:inline-block;background:#059669;color:#fff;font-size:10px;font-weight:800;padding:4px 12px;border-radius:999px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px}
    .roc-cat{font-size:11px;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:.05em}
    h1{font-size:clamp(1.4rem,4vw,2rem);font-weight:900;line-height:1.2;margin:6px 0 10px}
    .roc-desc{font-size:14px;color:#475569;line-height:1.6;margin-bottom:20px}
    table{width:100%;border-collapse:collapse;font-size:13px;margin:16px 0}
    table th{text-align:left;background:#f1f5f9;padding:8px 12px;font-weight:700;width:40%;border-bottom:1px solid #e2e8f0}
    table td{padding:8px 12px;border-bottom:1px solid #e2e8f0}
    .roc-btn-amazon{display:inline-flex;align-items:center;gap:8px;background:#059669;color:#fff;font-weight:800;font-size:14px;padding:12px 24px;border-radius:12px;text-decoration:none;margin-top:16px;box-shadow:0 4px 12px rgba(5,150,105,.3)}
    .roc-btn-amazon:hover{background:#047857}
    .roc-back{display:inline-block;margin-bottom:20px;font-size:13px;color:#059669;text-decoration:none;font-weight:700}
    .roc-section{margin-top:24px}
    .roc-section h3{font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;color:#0f172a}
    ul.roc-list{padding-left:20px;font-size:13px;color:#475569;line-height:1.8}
    .roc-updated{font-size:11px;color:#94a3b8;margin-top:24px}
  </style>
  <script type="application/ld+json">
  {
    "@context":"https://schema.org","@type":"Product",
    "name":"<?= addslashes($prod['title']) ?>",
    "image":"<?= addslashes($ogImage) ?>",
    "description":"<?= addslashes($prod['short_desc'] ?? '') ?>",
    "brand":{"@type":"Brand","name":"<?= addslashes($prod['brand'] ?? 'Run On Console') ?>"},
    "offers":{"@type":"Offer","availability":"https://schema.org/InStock","url":"<?= addslashes($amazonLink ?: $canonicalUrl) ?>"}
  }
  </script>
</head>
<body>
<div class="roc-wrap">
  <nav class="roc-breadcrumb">
    <a href="/">Home</a> / <a href="/products/">Products</a> / <?= $title ?>
  </nav>
  <a href="/products/" class="roc-back">← Back to all products</a>
  <div class="roc-grid">
    <div>
      <img src="<?= $image ?>" alt="<?= $title ?>" class="roc-img" loading="eager" />
    </div>
    <div>
      <?= $badgeHtml ?>
      <div class="roc-cat"><?= $cat ?></div>
      <h1><?= $title ?></h1>
      <p class="roc-desc"><?= $desc ?></p>
      <?= $amazonBtn ?>
      <?php if ($specsHtml): ?>
        <div class="roc-section">
          <h3>Specifications</h3>
          <table><tbody><?= $specsHtml ?></tbody></table>
        </div>
      <?php endif; ?>
      <?php if ($prosHtml): ?>
        <div class="roc-section"><h3>Key Features</h3><ul class="roc-list"><?= $prosHtml ?></ul></div>
      <?php endif; ?>
      <?php if ($consHtml): ?>
        <div class="roc-section"><h3>Cons</h3><ul class="roc-list"><?= $consHtml ?></ul></div>
      <?php endif; ?>
      <p class="roc-updated">Last updated: <?= date('F j, Y', strtotime($updatedAt)) ?></p>
    </div>
  </div>
</div>
</body>
</html>
