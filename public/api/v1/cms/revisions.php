<?php
/**
 * Run On Console (ROC) - CMS Content Revisions & Rollback API
 * GET    /api/v1/cms/revisions.php?type=blog&id=blog-1 (Fetch revision history)
 * POST   /api/v1/cms/revisions.php (Restore revision)
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
    $type = $_GET['type'] ?? '';
    $id = $_GET['id'] ?? '';

    if (empty($type) || empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'content_type and content_id are required.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT r.id, r.version_num, r.data_json, r.created_at, u.username as created_by_user FROM cms_revisions r LEFT JOIN cms_users u ON r.created_by = u.id WHERE r.content_type = ? AND r.content_id = ? ORDER BY r.version_num DESC LIMIT 50");
    $stmt->execute([$type, $id]);
    $revisions = $stmt->fetchAll();

    echo json_encode(['success' => true, 'revisions' => $revisions]);
    exit();
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

    $revisionId = (int)($data['revision_id'] ?? 0);
    if ($revisionId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid revision_id is required.']);
        exit();
    }

    $stmtRev = $pdo->prepare("SELECT * FROM cms_revisions WHERE id = ? LIMIT 1");
    $stmtRev->execute([$revisionId]);
    $rev = $stmtRev->fetch();

    if (!$rev) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Revision record not found.']);
        exit();
    }

    $revData = json_decode($rev['data_json'], true);
    $type = $rev['content_type'];
    $id = $rev['content_id'];

    logCmsAudit('cms_revision_rollback', $type, $id, ['revision_id' => $revisionId, 'version_num' => $rev['version_num']]);

    echo json_encode([
        'success' => true,
        'message' => "Revision #{$rev['version_num']} restored into working draft.",
        'draft' => $revData
    ]);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
