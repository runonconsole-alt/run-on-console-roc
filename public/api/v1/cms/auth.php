<?php
/**
 * Run On Console (ROC) - CMS Auth Endpoint
 * GET  /api/v1/cms/auth.php (Session check & CSRF token)
 * POST /api/v1/cms/auth.php (Login / Logout)
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = getDBConnection();

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit();
}

if ($method === 'GET') {
    $session = getCmsCurrentSession();
    if ($session) {
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'id' => $session['user_id'],
                'username' => $session['username'],
                'email' => $session['email'],
                'role' => $session['role']
            ],
            'csrf_token' => $session['csrf_token']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'authenticated' => false
        ]);
    }
    exit();
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? 'login';

    if ($action === 'logout') {
        $rawToken = $_COOKIE[CMS_SESSION_COOKIE] ?? '';
        if (!empty($rawToken)) {
            $tokenHash = hash('sha256', $rawToken);
            $stmt = $pdo->prepare("DELETE FROM cms_sessions WHERE session_token_hash = ?");
            $stmt->execute([$tokenHash]);
        }
        setcookie(CMS_SESSION_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
        exit();
    }

    // Default Action: Login
    $usernameOrEmail = trim($data['username'] ?? $data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($usernameOrEmail) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Username/Email and Password are required.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role, status FROM cms_users WHERE (username = ? OR email = ?) LIMIT 1");
    $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid CMS credentials.']);
        exit();
    }

    if ($user['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CMS account disabled.']);
        exit();
    }

    // Create session token and CSRF token
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $csrfToken = bin2hex(random_bytes(32));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 7)); // 7 Days

    $stmtSess = $pdo->prepare("INSERT INTO cms_sessions (user_id, session_token_hash, csrf_token, ip_address, user_agent, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmtSess->execute([$user['id'], $tokenHash, $csrfToken, $ip, $agent, $expiresAt]);

    setcookie(CMS_SESSION_COOKIE, $rawToken, [
        'expires' => time() + (86400 * 7),
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    logCmsAudit('cms_login_success', 'cms_user', (string)$user['id'], ['username' => $user['username']]);

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ],
        'csrf_token' => $csrfToken
    ]);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
