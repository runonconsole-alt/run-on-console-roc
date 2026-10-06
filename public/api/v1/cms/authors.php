<?php
/**
 * Run On Console (ROC) - CMS Editorial Author Management API
 * GET    /api/v1/cms/authors.php (List authors / Get author details)
 * POST   /api/v1/cms/authors.php (Create / Update / Publish / Unpublish author profile)
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
        $stmt = $pdo->prepare("SELECT * FROM authors WHERE (id = ? OR slug = ?) LIMIT 1");
        $stmt->execute([(string)$id, (string)$id]);
        $author = $stmt->fetch();

        if (!$author) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Author profile not found.']);
            exit();
        }

        echo json_encode(['success' => true, 'author' => $author]);
        exit();
    }

    $stmt = $pdo->query("SELECT * FROM authors ORDER BY name ASC");
    $authors = $stmt->fetchAll();
    echo json_encode(['success' => true, 'authors' => $authors]);
    exit();
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? 'save';

    $id = (int)($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $slug = rocAgentSlugify($data['slug'] ?? $name);
    $title = trim($data['title'] ?? 'Senior Hardware Columnist');
    $bio = $data['bio'] ?? null;
    $avatarImage = $data['avatar_image'] ?? null;
    $status = ($data['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $isNoindex = !empty($data['is_noindex']) ? 1 : 0;
    $expectedVersion = (int)($data['version'] ?? 1);

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Author name is required.']);
        exit();
    }

    if ($action === 'create' || $id <= 0) {
        $stmtIns = $pdo->prepare("INSERT INTO authors (name, slug, title, bio, avatar_image, status, is_noindex, version, content_modified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())");
        $stmtIns->execute([$name, $slug, $title, $bio, $avatarImage, $status, $isNoindex]);
        $newId = (int)$pdo->lastInsertId();

        logCmsAudit('cms_author_create', 'author', (string)$newId, ['name' => $name, 'slug' => $slug, 'status' => $status]);

        echo json_encode(['success' => true, 'message' => 'Editorial author profile created.', 'id' => $newId, 'slug' => $slug, 'version' => 1]);
        exit();
    }

    if ($action === 'save' || $action === 'update' || $action === 'publish' || $action === 'unpublish') {
        if ($action === 'unpublish') {
            $status = 'draft';
        } elseif ($action === 'publish') {
            $status = 'published';
        }

        $stmtUpd = $pdo->prepare("UPDATE authors SET name = ?, slug = ?, title = ?, bio = ?, avatar_image = ?, status = ?, is_noindex = ?, content_modified_at = NOW(), version = version + 1, updated_at = NOW() WHERE id = ? AND version = ?");
        $stmtUpd->execute([$name, $slug, $title, $bio, $avatarImage, $status, $isNoindex, $id, $expectedVersion]);

        if ($stmtUpd->rowCount() === 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Conflict: Author profile modified by another user. Please reload.']);
            exit();
        }

        $newVersion = $expectedVersion + 1;
        logCmsAudit('cms_author_update', 'author', (string)$id, ['name' => $name, 'status' => $status, 'is_noindex' => $isNoindex]);

        echo json_encode(['success' => true, 'message' => 'Author profile updated successfully.', 'version' => $newVersion]);
        exit();
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
