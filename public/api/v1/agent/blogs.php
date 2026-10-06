<?php
/**
 * Run On Console (ROC) - ROC Agent Published Blogs Endpoint
 * GET /api/v1/agent/blogs
 *
 * Uses actual database schema:
 * Table: blogs
 * Columns: id (VARCHAR/STRING), title, slug, category, read_time, author_name, image, excerpt, content, views_count, status, created_at
 * Strictly filters published records (status = 'published').
 */

require_once __DIR__ . '/config-agent.php';

function rocAgentGetBlogs(?string $category = null, int $limit = 5): array {
    if (rocAgentCheckAndLogRateLimit('read_blogs', 60, 60)) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.'], 429);
    }

    $baseUrl = defined('ROC_SITE_URL') ? rtrim(ROC_SITE_URL, '/') : 'https://runonconsole.com';

    $pdo = getDBConnection();
    if (!$pdo) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database connection unavailable.'], 503);
    }

    try {
        if ($category !== null && $category !== '') {
            $stmt = $pdo->prepare("SELECT id, title, slug, category, read_time, author_name, image, excerpt, views_count, created_at FROM blogs WHERE status = 'published' AND LOWER(category) = LOWER(?) ORDER BY id DESC LIMIT ?");
            $stmt->execute([$category, $limit]);
        } else {
            $stmt = $pdo->prepare("SELECT id, title, slug, category, read_time, author_name, image, excerpt, views_count, created_at FROM blogs WHERE status = 'published' ORDER BY id DESC LIMIT ?");
            $stmt->execute([$limit]);
        }

        $rows = $stmt->fetchAll();
        $blogs = [];
        if ($rows) {
            foreach ($rows as $b) {
                $slug = $b['slug'] ?? "blog-{$b['id']}";
                $blogs[] = array_filter([
                    'id' => (string)$b['id'],
                    'slug' => $slug,
                    'title' => $b['title'],
                    'category' => $b['category'] ?? null,
                    'author' => $b['author_name'] ?? null,
                    'readTime' => $b['read_time'] ?? null,
                    'publishedAt' => $b['created_at'] ?? null,
                    'featuredImage' => $b['image'] ?? null,
                    'excerpt' => $b['excerpt'] ?? null,
                    'viewsCount' => isset($b['views_count']) ? (int)$b['views_count'] : 0,
                    'readUrl' => "{$baseUrl}/blogs/{$slug}/"
                ], fn($val) => $val !== null);
            }
        }

        return [
            'success' => true,
            'blogs' => $blogs
        ];

    } catch (\Throwable $e) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database query failure.'], 500);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $category = $_GET['category'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    rocAgentJsonOutput(rocAgentGetBlogs($category, $limit));
}
