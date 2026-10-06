<?php
/**
 * Run On Console (ROC) - CMS API v1 Core Security & Session Engine
 */

require_once dirname(__DIR__) . '/config.php';

define('CMS_SESSION_COOKIE', 'ROCCMSSESSID');

/**
 * Returns current authenticated CMS user session or null
 */
function getCmsCurrentSession(): ?array {
    static $currentCmsUser = null;
    if ($currentCmsUser !== null) return $currentCmsUser;

    $rawToken = $_COOKIE[CMS_SESSION_COOKIE] ?? '';
    if (empty($rawToken)) return null;

    $pdo = getDBConnection();
    if (!$pdo) return null;

    try {
        $tokenHash = hash('sha256', $rawToken);
        $stmt = $pdo->prepare("SELECT s.id as session_id, s.user_id, s.csrf_token, u.username, u.email, u.role, u.status FROM cms_sessions s JOIN cms_users u ON s.user_id = u.id WHERE s.session_token_hash = ? AND s.expires_at > NOW() AND u.status = 'active' LIMIT 1");
        $stmt->execute([$tokenHash]);
        $sess = $stmt->fetch();

        if ($sess) {
            $currentCmsUser = [
                'session_id' => (int)$sess['session_id'],
                'user_id' => (int)$sess['user_id'],
                'username' => $sess['username'],
                'email' => $sess['email'],
                'role' => $sess['role'],
                'csrf_token' => $sess['csrf_token']
            ];
            return $currentCmsUser;
        }
    } catch (\Throwable $e) {
        error_log("CMS Session Check Error: " . $e->getMessage());
    }

    return null;
}

/**
 * Requires an active CMS session and optional role, exiting with HTTP 401/403 JSON on failure
 */
function requireCmsSession(?string $requiredRole = null): array {
    $session = getCmsCurrentSession();
    if (!$session) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized CMS access. Please log in.']);
        exit();
    }

    // CSRF Header check for mutating HTTP methods
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        $sentCsrf = $_SERVER['HTTP_X_CMS_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (empty($sentCsrf) || !hash_equals($session['csrf_token'], $sentCsrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid or missing CMS CSRF token.']);
            exit();
        }
    }

    if ($requiredRole !== null && $session['role'] !== $requiredRole && $session['role'] !== 'administrator') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden. Insufficient CMS permissions.']);
        exit();
    }

    return $session;
}

/**
 * Log administrative audit action
 */
function logCmsAudit(string $action, ?string $targetType = null, ?string $targetId = null, ?array $details = null): void {
    $session = getCmsCurrentSession();
    $userId = $session['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $pdo = getDBConnection();
    if (!$pdo) return;

    try {
        $detailsJson = $details !== null ? json_encode($details, JSON_UNESCAPED_SLASHES) : null;
        $stmt = $pdo->prepare("INSERT INTO cms_audit_logs (user_id, action, target_type, target_id, details_json, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $targetType, $targetId, $detailsJson, $ip]);
    } catch (\Throwable $e) {
        error_log("CMS Audit Log Error: " . $e->getMessage());
    }
}
