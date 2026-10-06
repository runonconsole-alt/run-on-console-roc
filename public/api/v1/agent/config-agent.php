<?php
/**
 * Run On Console (ROC) - ROC Agent Config & Security Engine
 * Shared utilities, rate limiting, session guest ID, CSRF validation, and input sanitizers.
 * NOTE: Automatic request-time migrations are disabled. Migrations must be run via CLI script.
 */

require_once dirname(__DIR__) . '/config.php';

function rocAgentGetGuestSessionId(): string {
    if (empty($_SESSION['roc_guest_agent_session_id'])) {
        $_SESSION['roc_guest_agent_session_id'] = 'guest_' . bin2hex(random_bytes(16));
    }
    return (string)$_SESSION['roc_guest_agent_session_id'];
}

function rocAgentGetUserId(): ?int {
    return (!empty($_SESSION['user_id']) && validateDatabaseSession()) ? (int)$_SESSION['user_id'] : null;
}

function rocAgentSanitizeString($str, int $maxLength = 255): string {
    $clean = trim((string)($str ?? ''));
    $clean = strip_tags($clean);
    return mb_substr($clean, 0, $maxLength, 'UTF-8');
}

/**
 * Genuinely Atomic IP & Action-Scoped Rate Limiter
 * Atomically inserts request attempt FIRST, then enforces window limits.
 * Protects concurrent requests across different sessions sharing one IP.
 */
function rocAgentCheckAndLogRateLimit(string $action = 'agent_query', int $maxAttempts = 20, int $windowSeconds = 60): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $pdo = getDBConnection();

    if ($pdo) {
        try {
            $emailHash = hashEmail('agent_ip_' . $ip);
            // 1. Atomically insert attempt FIRST
            $stmtInsert = $pdo->prepare("INSERT INTO request_rate_limits (ip_address, email_hash, action, attempted_at) VALUES (?, ?, ?, NOW())");
            $stmtInsert->execute([$ip, $emailHash, $action]);

            // 2. Check total attempts within window
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM request_rate_limits WHERE ip_address = ? AND action = ? AND attempted_at >= (NOW() - INTERVAL ? SECOND)");
            $stmtCount->execute([$ip, $action, $windowSeconds]);
            $attempts = (int)$stmtCount->fetchColumn();

            return $attempts > $maxAttempts; // Enforce limit
        } catch (\Throwable $e) {}
    }

    // Session fallback if DB unavailable
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $now = time();
    if (!isset($_SESSION['roc_rate_limits'][$action])) {
        $_SESSION['roc_rate_limits'][$action] = [];
    }
    $_SESSION['roc_rate_limits'][$action][] = $now;
    $_SESSION['roc_rate_limits'][$action] = array_filter(
        $_SESSION['roc_rate_limits'][$action],
        fn($timestamp) => ($now - $timestamp) < $windowSeconds
    );
    return count($_SESSION['roc_rate_limits'][$action]) > $maxAttempts;
}

function rocAgentValidateCsrf(): bool {
    return validateCsrfToken();
}

function rocAgentJsonOutput(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}
