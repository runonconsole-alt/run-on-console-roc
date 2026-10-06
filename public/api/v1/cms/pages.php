<?php
/**
 * Run On Console (ROC) - CMS Block Page Builder API
 * GET    /api/v1/cms/pages.php (List pages / Get single page content blocks)
 * POST   /api/v1/cms/pages.php (Save draft / Publish page blocks)
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
    $slug = $_GET['slug'] ?? $_GET['id'] ?? null;
    if ($slug !== null && $slug !== '') {
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE (slug = ? OR id = ?) LIMIT 1");
        $stmt->execute([(string)$slug, (string)$slug]);
        $page = $stmt->fetch();

        if (!$page) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Page not found.']);
            exit();
        }

        echo json_encode(['success' => true, 'page' => $page]);
        exit();
    }

    $stmt = $pdo->query("SELECT id, title, slug, meta_title, is_noindex, status, draft_status, version, updated_at FROM pages ORDER BY title ASC");
    $pages = $stmt->fetchAll();
    echo json_encode(['success' => true, 'pages' => $pages]);
    exit();
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? 'save_draft';

    $id = (int)($data['id'] ?? 0);
    $slug = rocAgentSlugify($data['slug'] ?? '');

    if ($action === 'create') {
        $title = trim($data['title'] ?? 'New Page');
        $slug = rocAgentSlugify($data['slug'] ?? $title);

        $stmtIns = $pdo->prepare("INSERT INTO pages (title, slug, status, draft_status, version, content_blocks_json, created_at, updated_at) VALUES (?, ?, 'draft', 'draft_saved', 1, '[]', NOW(), NOW())");
        $stmtIns->execute([$title, $slug]);
        $newId = (int)$pdo->lastInsertId();

        logCmsAudit('cms_page_create', 'page', (string)$newId, ['title' => $title, 'slug' => $slug]);

        echo json_encode(['success' => true, 'id' => $newId, 'slug' => $slug, 'version' => 1]);
        exit();
    }

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid page ID is required.']);
        exit();
    }

    $expectedVersion = (int)($data['version'] ?? 1);
    $draftPayload = $data['draft'] ?? $data;

    if ($action === 'save_draft') {
        $draftJson = json_encode($draftPayload, JSON_UNESCAPED_SLASHES);
        $stmtUpd = $pdo->prepare("UPDATE pages SET draft_data_json = ?, draft_status = 'draft_saved', version = version + 1, updated_at = NOW() WHERE id = ? AND version = ?");
        $stmtUpd->execute([$draftJson, $id, $expectedVersion]);

        if ($stmtUpd->rowCount() === 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Conflict: Page modified by another user. Please reload.']);
            exit();
        }

        $newVersion = $expectedVersion + 1;
        logCmsAudit('cms_page_save_draft', 'page', (string)$id, ['version' => $newVersion]);

        echo json_encode(['success' => true, 'message' => 'Page draft saved successfully.', 'version' => $newVersion]);
        exit();
    }

    if ($action === 'publish') {
        $title = trim($draftPayload['title'] ?? '');
        $metaTitle = $draftPayload['meta_title'] ?? $title;
        $metaDesc = $draftPayload['meta_description'] ?? '';
        $canonical = $draftPayload['canonical_url'] ?? null;
        $ogImage = $draftPayload['og_image'] ?? null;
        $isNoindex = !empty($draftPayload['is_noindex']) ? 1 : 0;
        $blocksJson = json_encode($draftPayload['blocks'] ?? $draftPayload['content_blocks'] ?? [], JSON_UNESCAPED_SLASHES);

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Page title is required.']);
            exit();
        }

        $pdo->beginTransaction();

        try {
            $stmtPub = $pdo->prepare("UPDATE pages SET title = ?, meta_title = ?, meta_description = ?, canonical_url = ?, og_image = ?, is_noindex = ?, content_blocks_json = ?, status = 'published', draft_status = 'none', content_modified_at = NOW(), version = version + 1, updated_at = NOW() WHERE id = ? AND version = ?");
            $stmtPub->execute([$title, $metaTitle, $metaDesc, $canonical, $ogImage, $isNoindex, $blocksJson, $id, $expectedVersion]);

            if ($stmtPub->rowCount() === 0) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Conflict: Page modified by another user. Please reload.']);
                exit();
            }

            $newVersion = $expectedVersion + 1;

            // Revision snapshot
            $stmtRev = $pdo->prepare("INSERT INTO cms_revisions (content_type, content_id, version_num, data_json, created_by, created_at) VALUES ('page', ?, ?, ?, ?, NOW())");
            $stmtRev->execute([(string)$id, $newVersion, json_encode($draftPayload, JSON_UNESCAPED_SLASHES), $session['user_id']]);

            // Queue task
            $stmtQ = $pdo->prepare("INSERT INTO cms_cache_queue (content_type, content_id, target_version_id, action, status, created_at) VALUES ('page', ?, ?, 'purge_and_prerender', 'pending', NOW())");
            $stmtQ->execute([(string)$id, $newVersion]);

            $pdo->commit();

            logCmsAudit('cms_page_publish', 'page', (string)$id, ['version' => $newVersion, 'title' => $title]);

            echo json_encode(['success' => true, 'message' => 'Page blocks published successfully.', 'version' => $newVersion]);
            exit();

        } catch (\Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Publish failed: ' . $e->getMessage()]);
            exit();
        }
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
