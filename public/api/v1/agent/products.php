<?php
/**
 * Run On Console (ROC) - ROC Agent Products Endpoint
 * GET /api/v1/agent/products
 * GET /api/v1/agent/products/{id}
 *
 * Uses actual production products table schema:
 * Columns: id (VARCHAR/STRING), title, category, price, rating, image, summary, pros, cons, affiliate_amazon, affiliate_bestbuy, affiliate_official, clicks_count, created_at
 * Queries existing title data and generates canonical URLs matching frontend routeRegistry.js without querying non-existent slug column.
 */

require_once __DIR__ . '/config-agent.php';

function rocAgentSlugify(string $text): string {
    $clean = strtolower(trim($text));
    $clean = preg_replace('/[\s_]+/', '-', $clean);
    $clean = preg_replace('/[^\w\-]+/', '', $clean);
    $clean = preg_replace('/\-+/', '-', $clean);
    return trim($clean, '-');
}

function rocAgentGetProducts($id = null, ?string $category = null, int $limit = 3): array {
    if (rocAgentCheckAndLogRateLimit('read_products', 60, 60)) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.'], 429);
    }

    $baseUrl = defined('ROC_SITE_URL') ? rtrim(ROC_SITE_URL, '/') : 'https://runonconsole.com';

    $pdo = getDBConnection();
    if (!$pdo) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database connection unavailable.'], 503);
    }

    try {
        if ($id !== null && (string)$id !== '') {
            $idStr = (string)$id;
            $stmt = $pdo->prepare("SELECT id, title, category, price, rating, image, summary, pros, cons, affiliate_amazon, affiliate_bestbuy, affiliate_official, clicks_count, created_at FROM products WHERE id = ? LIMIT 1");
            $stmt->execute([$idStr]);
            $p = $stmt->fetch();
            if ($p) {
                $slug = rocAgentSlugify($p['title']);
                return [
                    'success' => true,
                    'product' => [
                        'id' => (string)$p['id'],
                        'slug' => $slug,
                        'name' => $p['title'],
                        'category' => $p['category'] ?? null,
                        'price' => $p['price'] ? (str_starts_with((string)$p['price'], '$') ? $p['price'] : '$' . number_format((float)$p['price'], 2)) : null,
                        'rating' => $p['rating'] !== null ? (float)$p['rating'] : null,
                        'image' => $p['image'] ?? null,
                        'description' => $p['summary'] ?? null,
                        'pros' => $p['pros'] ?? null,
                        'cons' => $p['cons'] ?? null,
                        'affiliateAmazon' => $p['affiliate_amazon'] ?? null,
                        'affiliateBestBuy' => $p['affiliate_bestbuy'] ?? null,
                        'affiliateOfficial' => $p['affiliate_official'] ?? null,
                        'clicksCount' => isset($p['clicks_count']) ? (int)$p['clicks_count'] : 0,
                        'url' => "{$baseUrl}/products/{$slug}/"
                    ]
                ];
            }
            rocAgentJsonOutput(['success' => false, 'error' => 'Product not found.'], 404);
        }

        if ($category !== null && $category !== '') {
            $stmt = $pdo->prepare("SELECT id, title, category, price, rating, image, summary, pros, cons, affiliate_amazon, affiliate_bestbuy, affiliate_official, clicks_count, created_at FROM products WHERE LOWER(category) = LOWER(?) ORDER BY id DESC LIMIT ?");
            $stmt->execute([$category, $limit]);
        } else {
            $stmt = $pdo->prepare("SELECT id, title, category, price, rating, image, summary, pros, cons, affiliate_amazon, affiliate_bestbuy, affiliate_official, clicks_count, created_at FROM products ORDER BY id DESC LIMIT ?");
            $stmt->execute([$limit]);
        }

        $rows = $stmt->fetchAll();
        $products = [];
        if ($rows) {
            foreach ($rows as $p) {
                $slug = rocAgentSlugify($p['title']);
                $products[] = array_filter([
                    'id' => (string)$p['id'],
                    'slug' => $slug,
                    'name' => $p['title'],
                    'category' => $p['category'] ?? null,
                    'price' => $p['price'] ? (str_starts_with((string)$p['price'], '$') ? $p['price'] : '$' . number_format((float)$p['price'], 2)) : null,
                    'rating' => $p['rating'] !== null ? (float)$p['rating'] : null,
                    'image' => $p['image'] ?? null,
                    'description' => $p['summary'] ?? null,
                    'clicksCount' => isset($p['clicks_count']) ? (int)$p['clicks_count'] : 0,
                    'url' => "{$baseUrl}/products/{$slug}/"
                ], fn($val) => $val !== null);
            }
        }

        return ['success' => true, 'products' => $products];

    } catch (\Throwable $e) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database query failure.'], 500);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $id = $_GET['id'] ?? null;
    $category = $_GET['category'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 3;
    rocAgentJsonOutput(rocAgentGetProducts($id, $category, $limit));
}
