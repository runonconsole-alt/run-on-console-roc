<?php
/**
 * Run On Console (ROC) - CMS Blog Posts Management API
 * GET    /api/v1/cms/blogs.php (List blogs / Get single blog with draft)
 * POST   /api/v1/cms/blogs.php (Save draft / Publish / Change status / Release lock)
 */

require_once __DIR__ . '/config.php';

$session = requireCmsSession(); // Administrator or Editor
$pdo = getDBConnection();

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    if ($id !== null && $id !== '') {
        $stmt = $pdo->prepare("SELECT * FROM blogs WHERE id = ? LIMIT 1");
        $stmt->execute([(string)$id]);
        $blog = $stmt->fetch();

        if (!$blog) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Blog post not found.']);
            exit();
        }

        // Lock check
        if (!empty($blog['locked_by']) && (int)$blog['locked_by'] !== $session['user_id']) {
            $lockTime = strtotime($blog['locked_at'] ?? '');
            if ((time() - $lockTime) < 900) { // 15 Min active lock
                $stmtL = $pdo->prepare("SELECT username FROM cms_users WHERE id = ? LIMIT 1");
                $stmtL->execute([$blog['locked_by']]);
                $lockUser = $stmtL->fetchColumn() ?: 'Another editor';
                $blog['lock_warning'] = "This post is currently being edited by {$lockUser}.";
            }
        }

        // Auto lock for current editor
        $stmtLock = $pdo->prepare("UPDATE blogs SET locked_by = ?, locked_at = NOW() WHERE id = ?");
        $stmtLock->execute([$session['user_id'], (string)$id]);

        echo json_encode(['success' => true, 'blog' => $blog]);
        exit();
    }

    $stmt = $pdo->query("SELECT id, title, slug, category, author_name, status, draft_status, version, is_noindex, created_at, updated_at, published_at FROM blogs ORDER BY updated_at DESC");
    $blogs = $stmt->fetchAll();
    echo json_encode(['success' => true, 'blogs' => $blogs]);
    exit();
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? 'save_draft';

    $id = (string)($data['id'] ?? '');
    if (empty($id) && $action !== 'create') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Blog ID is required.']);
        exit();
    }

    if ($action === 'create') {
        $title = trim($data['title'] ?? 'Untitled Blog Post');
        $idStr = 'blog-' . bin2hex(random_bytes(4));
        $slug = rocAgentSlugify($title) ?: $idStr;

        // Ensure unique slug
        $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM blogs WHERE slug = ?");
        $stmtChk->execute([$slug]);
        if ((int)$stmtChk->fetchColumn() > 0) {
            $slug .= '-' . time();
        }

        $stmtIns = $pdo->prepare("INSERT INTO blogs (id, title, slug, category, author_name, status, draft_status, version, created_at, updated_at) VALUES (?, ?, ?, 'general', ?, 'draft', 'draft_saved', 1, NOW(), NOW())");
        $stmtIns->execute([$idStr, $title, $slug, $session['username']]);

        logCmsAudit('cms_blog_create', 'blog', $idStr, ['title' => $title]);

        echo json_encode(['success' => true, 'id' => $idStr, 'slug' => $slug, 'version' => 1]);
        exit();
    }

    if ($action === 'save_draft') {
        $expectedVersion = (int)($data['version'] ?? 1);
        $draftPayload = $data['draft'] ?? $data;

        // Sanitize rich content tags
        if (isset($draftPayload['content'])) {
            $draftPayload['content'] = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $draftPayload['content']);
            $draftPayload['content'] = preg_replace('/on\w+="[^"]*"/i', '', $draftPayload['content']);
        }

        $draftJson = json_encode($draftPayload, JSON_UNESCAPED_SLASHES);

        // Atomic update with version check
        $stmtUpd = $pdo->prepare("UPDATE blogs SET draft_data_json = ?, draft_status = 'draft_saved', version = version + 1, updated_at = NOW() WHERE id = ? AND version = ?");
        $stmtUpd->execute([$draftJson, $id, $expectedVersion]);

        if ($stmtUpd->rowCount() === 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Content modification conflict. Another editor has modified this blog post. Please reload.']);
            exit();
        }

        $newVersion = $expectedVersion + 1;
        logCmsAudit('cms_blog_save_draft', 'blog', $id, ['version' => $newVersion]);

        echo json_encode(['success' => true, 'message' => 'Draft saved successfully.', 'version' => $newVersion]);
        exit();
    }

    if ($action === 'publish') {
        $expectedVersion = (int)($data['version'] ?? 1);
        $draftPayload = $data['draft'] ?? $data;

        $title = trim($draftPayload['title'] ?? '');
        $content = $draftPayload['content'] ?? '';
        $excerpt = $draftPayload['excerpt'] ?? '';
        $category = $draftPayload['category'] ?? 'general';
        $image = $draftPayload['image'] ?? $draftPayload['featuredImage'] ?? null;
        $metaTitle = $draftPayload['meta_title'] ?? $title;
        $metaDesc = $draftPayload['meta_description'] ?? $excerpt;
        $isNoindex = !empty($draftPayload['is_noindex']) ? 1 : 0;

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Title is required for publishing.']);
            exit();
        }

        // Sanitize rich content tags
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/on\w+="[^"]*"/i', '', $content);

        // Concurrency-Safe Durable Publish Transaction
        $pdo->beginTransaction();

        try {
            // Atomic update promoting draft to live columns
            $stmtPub = $pdo->prepare("UPDATE blogs SET title = ?, category = ?, excerpt = ?, content = ?, image = ?, meta_title = ?, meta_description = ?, is_noindex = ?, status = 'published', draft_status = 'none', published_at = IFNULL(published_at, NOW()), content_modified_at = NOW(), version = version + 1, updated_at = NOW() WHERE id = ? AND version = ?");
            $stmtPub->execute([$title, $category, $excerpt, $content, $image, $metaTitle, $metaDesc, $isNoindex, $id, $expectedVersion]);

            if ($stmtPub->rowCount() === 0) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Publishing conflict. Another editor has modified this blog post. Please reload.']);
                exit();
            }

            $newVersion = $expectedVersion + 1;

            // Save Revision Snapshot
            $stmtRev = $pdo->prepare("INSERT INTO cms_revisions (content_type, content_id, version_num, data_json, created_by, created_at) VALUES ('blog', ?, ?, ?, ?, NOW())");
            $stmtRev->execute([$id, $newVersion, json_encode($draftPayload, JSON_UNESCAPED_SLASHES), $session['user_id']]);

            // Enqueue Pending Cache Invalidation Task in Durable MySQL Table
            $stmtQ = $pdo->prepare("INSERT INTO cms_cache_queue (content_type, content_id, target_version_id, action, status, created_at) VALUES ('blog', ?, ?, 'purge_and_prerender', 'pending', NOW())");
            $stmtQ->execute([$id, $newVersion]);

            $pdo->commit();

            logCmsAudit('cms_blog_publish', 'blog', $id, ['version' => $newVersion, 'title' => $title]);

            echo json_encode(['success' => true, 'message' => 'Blog post published successfully.', 'version' => $newVersion]);
            exit();

        } catch (\Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Publishing failed: ' . $e->getMessage()]);
            exit();
        }
    }

    if ($action === 'unpublish' || $action === 'update') {
        $status = ($data['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $stmtUnpub = $pdo->prepare("UPDATE blogs SET status = 'draft', draft_status = 'draft_saved', version = version + 1, updated_at = NOW() WHERE id = ?");
        if ($status === 'published') {
            $stmtUnpub = $pdo->prepare("UPDATE blogs SET status = 'published', draft_status = 'none', version = version + 1, updated_at = NOW() WHERE id = ?");
        }
        $stmtUnpub->execute([$id]);

        logCmsAudit('cms_blog_update_status', 'blog', $id, ['status' => $status]);
        echo json_encode(['success' => true, 'message' => "Blog post status updated to {$status}."]);
        exit();
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
