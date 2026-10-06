<?php
/**
 * Run On Console (ROC) - CMS Category Management API
 * GET    /api/v1/cms/categories.php (List categories)
 * POST   /api/v1/cms/categories.php (Create / Update category with 301 redirect logging)
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
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
    echo json_encode(['success' => true, 'categories' => $categories]);
    exit();
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? 'save';

    $id = trim($data['id'] ?? '');
    $name = trim($data['name'] ?? '');
    $slug = rocAgentSlugify($data['slug'] ?? $name);
    $parentId = !empty($data['parent_id']) ? trim($data['parent_id']) : null;
    $description = $data['description'] ?? null;
    $iconName = $data['icon_name'] ?? 'tag';
    $metaTitle = $data['meta_title'] ?? $name;
    $metaDesc = $data['meta_description'] ?? $description;
    $status = ($data['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
    $isNoindex = !empty($data['is_noindex']) ? 1 : 0;
    $expectedVersion = (int)($data['version'] ?? 1);

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category name is required.']);
        exit();
    }

    // Circular parent prevention
    if (!empty($id) && !empty($parentId)) {
        if ($id === $parentId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Category cannot be its own parent.']);
            exit();
        }
        $curr = $parentId;
        while ($curr) {
            $stmtP = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
            $stmtP->execute([$curr]);
            $curr = $stmtP->fetchColumn();
            if ($curr === $id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Circular category hierarchy detected.']);
                exit();
            }
        }
    }

    if ($action === 'create' || empty($id)) {
        $idStr = !empty($id) ? $id : rocAgentSlugify($name);
        $stmtIns = $pdo->prepare("INSERT INTO categories (id, name, slug, parent_id, description, icon_name, meta_title, meta_description, status, is_noindex, version, content_modified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())");
        $stmtIns->execute([$idStr, $name, $slug, $parentId, $description, $iconName, $metaTitle, $metaDesc, $status, $isNoindex]);

        logCmsAudit('cms_category_create', 'category', $idStr, ['name' => $name]);

        echo json_encode(['success' => true, 'message' => 'Category created successfully.', 'id' => $idStr, 'version' => 1]);
        exit();
    }

    if ($action === 'save' || $action === 'update') {
        // Fetch existing category to check for slug change
        $stmtOld = $pdo->prepare("SELECT slug, version FROM categories WHERE id = ?");
        $stmtOld->execute([$id]);
        $oldCat = $stmtOld->fetch();

        if (!$oldCat) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Category not found.']);
            exit();
        }

        $oldSlug = $oldCat['slug'];
        $dbVersion = (int)$oldCat['version'];

        if ($dbVersion !== $expectedVersion) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Conflict: Category modified by another user. Please reload.']);
            exit();
        }

        $pdo->beginTransaction();

        try {
            $stmtUpd = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, parent_id = ?, description = ?, icon_name = ?, meta_title = ?, meta_description = ?, status = ?, is_noindex = ?, content_modified_at = NOW(), version = version + 1, updated_at = NOW() WHERE id = ? AND version = ?");
            $stmtUpd->execute([$name, $slug, $parentId, $description, $iconName, $metaTitle, $metaDesc, $status, $isNoindex, $id, $expectedVersion]);

            // Create 301 Redirect entry if slug changed
            if ($oldSlug !== $slug && !empty($oldSlug)) {
                $stmtR = $pdo->prepare("INSERT INTO cms_redirects (old_slug, new_slug, target_type, http_code, created_at) VALUES (?, ?, 'category', 301, NOW())");
                $stmtR->execute(["categories/{$oldSlug}", "categories/{$slug}"]);
            }

            $pdo->commit();

            $newVersion = $expectedVersion + 1;
            logCmsAudit('cms_category_update', 'category', $id, ['name' => $name, 'old_slug' => $oldSlug, 'new_slug' => $slug]);

            echo json_encode(['success' => true, 'message' => 'Category updated successfully.', 'version' => $newVersion]);
            exit();

        } catch (\Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Category update failed: ' . $e->getMessage()]);
            exit();
        }
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
