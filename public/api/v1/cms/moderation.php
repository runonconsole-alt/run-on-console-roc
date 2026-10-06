<?php
/**
 * Run On Console (ROC) - CMS Moderation Queue API
 * GET  /api/v1/cms/moderation.php (List pending / approved / rejected submissions & comments)
 * POST /api/v1/cms/moderation.php (Update status: approve, reject, spam, delete)
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
    $status = $_GET['status'] ?? 'pending';

    // 1. Fetch Write For Us Submissions
    $stmtW = $pdo->prepare("SELECT id, name as author_name, email as author_email, short_pitch as content, status, 'write_for_us' as type, created_at FROM roc_write_for_us_submissions ORDER BY id DESC LIMIT 50");
    $stmtW->execute();
    $writeForUs = $stmtW->fetchAll();

    // 2. Fetch User Comments if table exists
    $comments = [];
    try {
        $stmtC = $pdo->prepare("SELECT id, user_id, comment_text as content, status, 'comment' as type, created_at FROM user_comments ORDER BY id DESC LIMIT 50");
        $stmtC->execute();
        $comments = $stmtC->fetchAll();
    } catch (\Throwable $e) {}

    $moderation = array_merge($writeForUs, $comments);
    echo json_encode(['success' => true, 'moderation' => $moderation]);
    exit();
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

    $id = (int)($data['id'] ?? 0);
    $type = $data['type'] ?? 'write_for_us';
    $status = in_array($data['status'] ?? '', ['approved', 'rejected', 'spam', 'pending'], true) ? $data['status'] : 'approved';

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid ID is required.']);
        exit();
    }

    if ($type === 'write_for_us') {
        $dbStatus = ($status === 'approved') ? 'approved' : (($status === 'rejected') ? 'rejected' : 'pending_review');
        $stmtUpd = $pdo->prepare("UPDATE roc_write_for_us_submissions SET status = ? WHERE id = ?");
        $stmtUpd->execute([$dbStatus, $id]);
    } else {
        $stmtUpd = $pdo->prepare("UPDATE user_comments SET status = ? WHERE id = ?");
        $stmtUpd->execute([$status, $id]);
    }

    logCmsAudit('cms_moderation_update', $type, (string)$id, ['status' => $status]);

    echo json_encode(['success' => true, 'message' => 'Moderation status updated.']);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
