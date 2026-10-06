<?php
/**
 * Run On Console (ROC) - CMS User Management API
 * GET    /api/v1/cms/users.php (List users)
 * POST   /api/v1/cms/users.php (Create / Edit / Disable user)
 */

require_once __DIR__ . '/config.php';

$session = requireCmsSession('administrator');
$pdo = getDBConnection();

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT id, username, email, role, status, created_at, updated_at FROM cms_users ORDER BY id ASC");
    $users = $stmt->fetchAll();
    echo json_encode(['success' => true, 'users' => $users]);
    exit();
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $role = in_array($data['role'] ?? '', ['administrator', 'editor'], true) ? $data['role'] : 'editor';

        if (empty($username) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username, Email, and Password are required.']);
            exit();
        }

        $stmtChk = $pdo->prepare("SELECT id FROM cms_users WHERE username = ? OR email = ?");
        $stmtChk->execute([$username, $email]);
        if ($stmtChk->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username or Email already exists.']);
            exit();
        }

        $passHash = password_hash($password, PASSWORD_DEFAULT);
        $stmtIns = $pdo->prepare("INSERT INTO cms_users (username, email, password_hash, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', NOW(), NOW())");
        $stmtIns->execute([$username, $email, $passHash, $role]);
        $newId = (int)$pdo->lastInsertId();

        logCmsAudit('cms_user_create', 'cms_user', (string)$newId, ['username' => $username, 'role' => $role]);

        echo json_encode(['success' => true, 'message' => 'CMS user created successfully.', 'id' => $newId]);
        exit();
    }

    if ($action === 'update') {
        $userId = (int)($data['id'] ?? 0);
        $status = in_array($data['status'] ?? '', ['active', 'disabled'], true) ? $data['status'] : 'active';
        $role = in_array($data['role'] ?? '', ['administrator', 'editor'], true) ? $data['role'] : 'editor';
        $newPassword = $data['password'] ?? '';

        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user ID.']);
            exit();
        }

        if (!empty($newPassword)) {
            $passHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtUpd = $pdo->prepare("UPDATE cms_users SET role = ?, status = ?, password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpd->execute([$role, $status, $passHash, $userId]);
        } else {
            $stmtUpd = $pdo->prepare("UPDATE cms_users SET role = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpd->execute([$role, $status, $userId]);
        }

        logCmsAudit('cms_user_update', 'cms_user', (string)$userId, ['role' => $role, 'status' => $status]);

        echo json_encode(['success' => true, 'message' => 'CMS user updated successfully.']);
        exit();
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
