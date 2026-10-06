<?php
/**
 * Run On Console — Public Products API (NO login required)
 * GET /api/v1/products-public.php              → all published products + categories
 * GET /api/v1/products-public.php?slug=xyz     → single product by slug
 * GET /api/v1/products-public.php?category=keyboards → products in a category
 *
 * Amazon tag from cms_settings is appended to every affiliate_amazon link automatically.
 * CORS headers allow the React SPA to call this from the browser.
 */

// ── Allow cross-origin calls from the same site (React SPA) ──────────────────
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://runonconsole.com', 'https://staging.runonconsole.com', 'http://localhost:5173', 'http://localhost:4173'];
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Load DB config ─────────────────────────────────────────────────────────────
$cfgFile = __DIR__ . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Server configuration missing.']);
    exit;
}
require_once $cfgFile;

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database unavailable.']);
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── Fetch Amazon tag (appended to every Amazon link) ─────────────────────────
function getAmazonTag(PDO $pdo): string {
    try {
        $st = $pdo->prepare("SELECT setting_value FROM cms_settings WHERE setting_key = 'amazon_tag' LIMIT 1");
        $st->execute();
        $v = $st->fetchColumn();
        return ($v && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,62}-\d{2}$/', $v)) ? (string)$v : '';
    } catch (\Throwable $e) {
        return '';
    }
}

// ── Append Amazon tag to a link ────────────────────────────────────────────────
function appendAmazonTag(string $url, string $tag): string {
    if ($tag === '' || $url === '') return $url;
    // Remove existing tag param if any
    $url = preg_replace('/([?&])tag=[^&]*/i', '$1', $url);
    $url = rtrim($url, '?&');
    $sep = str_contains($url, '?') ? '&' : '?';
    return $url . $sep . 'tag=' . urlencode($tag);
}

// ── Normalize a product row from DB into the shape React expects ───────────────
function normalizeProduct(array $row, string $amazonTag): array {
    $specsRaw  = $row['specs_json']         ?? '{}';
    $prosRaw   = $row['pros']               ?? '[]';
    $consRaw   = $row['cons']               ?? '[]';
    $affilRaw  = $row['affiliate_links_json'] ?? '{}';

    $specs   = json_decode($specsRaw,  true) ?: [];
    $pros    = json_decode($prosRaw,   true) ?: [];
    $cons    = json_decode($consRaw,   true) ?: [];
    $affil   = json_decode($affilRaw,  true) ?: [];

    // Amazon link: prefer affiliate_links_json, fall back to affiliate_amazon column
    $amazonLink = $affil['amazon'] ?? $row['affiliate_amazon'] ?? '';
    if ($amazonLink && $amazonTag) {
        $amazonLink = appendAmazonTag($amazonLink, $amazonTag);
    }
    $affil['amazon'] = $amazonLink;

    // Category slug: derive from category name if not stored
    $catName  = $row['category'] ?? '';
    $catSlug  = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $catName));
    $catSlug  = trim($catSlug, '-');
    // Map common names to the known slugs
    $catMap = [
        'gaming-keyboards'   => 'keyboards',
        'gaming-mice'        => 'mice',
        'gaming-headsets-audio' => 'audio',
        'gaming-headsets'    => 'audio',
        'speakers-soundbars' => 'speakers',
        'gaming-monitors'    => 'monitors',
        'graphics-cards-gpu' => 'gpu',
        'gpus'               => 'gpu',
    ];
    $catSlug = $catMap[$catSlug] ?? $catSlug;

    $slug    = $row['slug'] ?? '';
    $brand   = $row['brand'] ?? '';
    // Derive brand from title if not stored
    if (!$brand && isset($row['title'])) {
        $tl = strtolower($row['title']);
        if (str_contains($tl, 'logitech'))    $brand = 'Logitech';
        elseif (str_contains($tl, 'razer'))   $brand = 'Razer';
        elseif (str_contains($tl, 'asus') || str_contains($tl, 'rog'))  $brand = 'ASUS';
        elseif (str_contains($tl, 'steelseries')) $brand = 'SteelSeries';
        elseif (str_contains($tl, 'corsair'))  $brand = 'Corsair';
        elseif (str_contains($tl, 'hyperx'))   $brand = 'HyperX';
        elseif (str_contains($tl, 'samsung'))  $brand = 'Samsung';
        elseif (str_contains($tl, 'nvidia'))   $brand = 'NVIDIA';
        elseif (str_contains($tl, 'amd'))      $brand = 'AMD';
        elseif (str_contains($tl, 'intel'))    $brand = 'Intel';
        elseif (str_contains($tl, 'wooting'))  $brand = 'Wooting';
        elseif (str_contains($tl, 'keychron')) $brand = 'Keychron';
        elseif (str_contains($tl, 'msi'))      $brand = 'MSI';
        elseif (str_contains($tl, 'creative')) $brand = 'Creative';
        elseif (str_contains($tl, 'edifier'))  $brand = 'Edifier';
        elseif (str_contains($tl, 'audeze'))   $brand = 'Audeze';
        elseif (str_contains($tl, 'sennheiser')) $brand = 'Sennheiser';
        else $brand = 'Run On Console';
    }

    return [
        'id'            => $row['id']         ?? $slug,
        'slug'          => $slug,
        'title'         => $row['title']       ?? '',
        'subtitle'      => $row['summary']     ?? $row['short_desc'] ?? '',
        'shortDesc'     => $row['short_desc']  ?? $row['summary'] ?? '',
        'brand'         => $brand,
        'brandName'     => $brand,
        'category'      => $catName,
        'categorySlug'  => $catSlug,
        'badge'         => $row['badge']       ?? null,
        'bestFor'       => $row['best_for']    ?? null,
        'rank'          => isset($row['rank'])  ? (int)$row['rank'] : 999,
        'image'         => $row['image']       ?? '/images/cyber_keyboard.jpg',
        'affiliateLinks'=> $affil,
        'specs'         => $specs,
        'pros'          => $pros,
        'cons'          => $cons,
        'features'      => $pros, // features = pros for product detail page
        'price'         => $row['price']       ?? null,
        'rating'        => isset($row['rating']) ? (float)$row['rating'] : null,
        'status'        => $row['status']      ?? 'published',
        'lastmod'       => $row['updated_at']  ?? $row['content_modified_at'] ?? date('c'),
        'url'           => 'https://runonconsole.com/products/' . $slug . '/',
    ];
}

// ── Fetch product categories from DB ──────────────────────────────────────────
function getProductCategories(PDO $pdo): array {
    // Try cms_product_categories table first; fall back to aggregating from products
    try {
        $st = $pdo->query("SHOW TABLES LIKE 'cms_product_categories'");
        if ($st->fetchColumn()) {
            $rows = $pdo->query("SELECT slug, name, image, description as `desc`, count FROM cms_product_categories ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) return $rows;
        }
    } catch (\Throwable $e) {}

    // Aggregate from products table
    try {
        $rows = $pdo->query("SELECT category, COUNT(*) as cnt FROM products WHERE status='published' GROUP BY category")->fetchAll(PDO::FETCH_ASSOC);
        $catMap = [
            'Gaming Keyboards'       => ['slug'=>'keyboards',  'image'=>'/images/cyber_keyboard.jpg'],
            'Gaming Mice'            => ['slug'=>'mice',       'image'=>'/images/apex_mouse.jpg'],
            'Gaming Headsets & Audio'=> ['slug'=>'audio',      'image'=>'/images/tactical_headset.jpg'],
            'Speakers & Soundbars'   => ['slug'=>'speakers',   'image'=>'/images/streaming_vr_gear.jpg'],
            'Gaming Monitors'        => ['slug'=>'monitors',   'image'=>'/images/gaming_monitor.jpg'],
            'Graphics Cards (GPU)'   => ['slug'=>'gpu',        'image'=>'/images/battlestation_pc.jpg'],
        ];
        $out = [];
        foreach ($rows as $r) {
            $name = $r['category'];
            $m    = $catMap[$name] ?? ['slug' => strtolower(preg_replace('/[^a-z0-9]+/i','-',$name)), 'image'=>''];
            $out[] = ['slug'=>$m['slug'], 'name'=>$name, 'image'=>$m['image'], 'desc'=>'', 'count'=>(int)$r['cnt']];
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

// ── ROUTE: single product by slug ─────────────────────────────────────────────
$slug     = trim($_GET['slug']     ?? '');
$category = trim($_GET['category'] ?? '');

$amazonTag = getAmazonTag($pdo);

if ($slug !== '') {
    try {
        $st = $pdo->prepare("SELECT * FROM products WHERE slug = ? AND status = 'published' LIMIT 1");
        $st->execute([$slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Product not found.']);
            exit;
        }
        echo json_encode(['success' => true, 'product' => normalizeProduct($row, $amazonTag)]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Query error.']);
    }
    exit;
}

// ── ROUTE: all products (optionally filtered by category slug) ─────────────────
try {
    if ($category !== '') {
        // Map slug → category name
        $nameMap = [
            'keyboards' => 'Gaming Keyboards',
            'mice'      => 'Gaming Mice',
            'audio'     => 'Gaming Headsets & Audio',
            'speakers'  => 'Speakers & Soundbars',
            'monitors'  => 'Gaming Monitors',
            'gpu'       => 'Graphics Cards (GPU)',
        ];
        $catName = $nameMap[$category] ?? null;
        if ($catName) {
            $st = $pdo->prepare("SELECT * FROM products WHERE category = ? AND status = 'published' ORDER BY `rank`, title");
            $st->execute([$catName]);
        } else {
            // Try partial match
            $st = $pdo->prepare("SELECT * FROM products WHERE LOWER(category) LIKE ? AND status = 'published' ORDER BY `rank`, title");
            $st->execute(['%' . strtolower($category) . '%']);
        }
    } else {
        $st = $pdo->query("SELECT * FROM products WHERE status = 'published' ORDER BY `rank`, category, title");
    }

    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $products   = array_map(fn($r) => normalizeProduct($r, $amazonTag), $rows);
    $categories = getProductCategories($pdo);

    echo json_encode([
        'success'    => true,
        'products'   => $products,
        'categories' => $categories,
        'amazon_tag' => $amazonTag,
        'count'      => count($products),
        'cached_at'  => date('c'),
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load products.']);
}
