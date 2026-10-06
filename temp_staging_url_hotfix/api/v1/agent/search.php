<?php
/**
 * Run On Console (ROC) - ROC Agent Intent Recognition & Unified Search Router
 * GET /api/v1/agent/search?q=
 *
 * Queries actual database columns (without non-existent products.slug column).
 * Filters published blogs using status = 'published'.
 */

require_once __DIR__ . '/config-agent.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/games.php';
require_once __DIR__ . '/blogs.php';

function rocAgentUnifiedSearch(string $query): array {
    if (rocAgentCheckAndLogRateLimit('read_search', 60, 60)) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.'], 429);
    }

    $baseUrl = defined('ROC_SITE_URL') ? rtrim(ROC_SITE_URL, '/') : 'https://runonconsole.com';

    $rawQ = trim($query);
    // Sanitize trailing punctuation (e.g. "Cyberpunk 2077?" -> "Cyberpunk 2077")
    $rawQ = preg_replace('/[?!\.,:;"]+$/', '', $rawQ);
    $q = strtolower($rawQ);
    if ($q === '') {
        return [
            'success' => true,
            'intent' => 'general_greeting',
            'matchedProducts' => [],
            'matchedGames' => [],
            'matchedBlogs' => []
        ];
    }

    // Strict Intent Recognition using whole word boundary matching
    $intent = 'general';
    if (preg_match('/\b(write|guest post|author|submit article|pitch)\b/i', $q)) {
        $intent = 'write_for_us';
    } elseif (preg_match('/\b(can my pc run|run game|hardware check|compatibility|can i run)\b/i', $q) || (preg_match('/\brun\b/i', $q) && !preg_match('/run\s*on\s*console/i', $q))) {
        $intent = 'compatibility_check';
    } elseif (preg_match('/\b(mouse|mice|keyboard|headset|bungee|product|buy|price|accessory)\b/i', $q)) {
        $intent = 'products_search';
    } elseif (preg_match('/\b(guide|guides|blog|article|optimize|optimization|read)\b/i', $q)) {
        $intent = 'blog_search';
    }

    // Term Extraction & Plural Normalization ('mice' -> 'mouse')
    $cleanTerm = preg_replace('/\b(show me|show|latest|can my pc run|i want to|write for|runonconsole|run on console|best)\b/i', '', $q);
    $cleanTerm = trim(preg_replace('/\s+/', ' ', $cleanTerm));
    $cleanTerm = preg_replace('/[?!\.,:;"]+$/', '', $cleanTerm);
    if ($cleanTerm === '') {
        $cleanTerm = $q;
    }

    $searchKey = str_replace('mice', 'mouse', strtolower($cleanTerm));

    $pdo = getDBConnection();
    $matchedProducts = [];
    $matchedBlogs = [];
    $matchedGames = [];

    if ($pdo) {
        try {
            // Search ALL products in database using extracted term BEFORE limit
            $stmtP = $pdo->prepare("SELECT id, title, category, price, rating, image, summary FROM products WHERE LOWER(title) LIKE ? OR LOWER(category) LIKE ? OR LOWER(summary) LIKE ? ORDER BY id DESC LIMIT 3");
            $likeVal = '%' . $searchKey . '%';
            $stmtP->execute([$likeVal, $likeVal, $likeVal]);
            $pRows = $stmtP->fetchAll() ?: [];

            foreach ($pRows as $p) {
                $slug = rocAgentSlugify($p['title']);
                $matchedProducts[] = array_filter([
                    'id' => (string)$p['id'],
                    'slug' => $slug,
                    'name' => $p['title'],
                    'category' => $p['category'] ?? null,
                    'price' => $p['price'] ? (str_starts_with((string)$p['price'], '$') ? $p['price'] : '$' . number_format((float)$p['price'], 2)) : null,
                    'rating' => $p['rating'] !== null ? (float)$p['rating'] : null,
                    'image' => $p['image'] ?? null,
                    'description' => $p['summary'] ?? null,
                    'url' => "{$baseUrl}/products/{$slug}/"
                ], fn($v) => $v !== null);
            }

            // Search ALL published blogs in database using extracted term BEFORE limit
            $stmtB = $pdo->prepare("SELECT id, title, slug, category, read_time, author_name, image, excerpt, created_at FROM blogs WHERE status = 'published' AND (LOWER(title) LIKE ? OR LOWER(category) LIKE ? OR LOWER(excerpt) LIKE ?) ORDER BY id DESC LIMIT 3");
            $stmtB->execute([$likeVal, $likeVal, $likeVal]);
            $bRows = $stmtB->fetchAll() ?: [];

            foreach ($bRows as $b) {
                $slug = !empty($b['slug']) ? trim($b['slug']) : rocAgentSlugify($b['title']);
                $matchedBlogs[] = array_filter([
                    'id' => (string)$b['id'],
                    'slug' => $slug,
                    'title' => $b['title'],
                    'category' => $b['category'] ?? null,
                    'author' => $b['author_name'] ?? null,
                    'readTime' => $b['read_time'] ?? null,
                    'publishedAt' => $b['created_at'] ?? null,
                    'featuredImage' => $b['image'] ?? null,
                    'excerpt' => $b['excerpt'] ?? null,
                    'readUrl' => "{$baseUrl}/blogs/{$slug}/"
                ], fn($v) => $v !== null);
            }
        } catch (\Throwable $e) {}
    }

    $gamesRes = rocAgentGetGames(null, $searchKey);
    $matchedGames = $gamesRes['games'] ?? [];

    return [
        'success' => true,
        'query' => $rawQ,
        'cleanTerm' => $cleanTerm,
        'searchKey' => $searchKey,
        'intent' => $intent,
        'matchedProducts' => array_slice($matchedProducts, 0, 3),
        'matchedGames' => array_slice($matchedGames, 0, 3),
        'matchedBlogs' => array_slice($matchedBlogs, 0, 3)
    ];
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $q = $_GET['q'] ?? '';
    rocAgentJsonOutput(rocAgentUnifiedSearch($q));
}
