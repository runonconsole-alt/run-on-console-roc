<?php
/**
 * Run On Console (ROC) - API v1 Configuration & Security Engine
 *
 * Environment-aware. Everything that differs between production and staging now
 * comes from the settings file rather than being hard-coded, so the same config
 * runs in both places and only the settings file changes.
 *
 * Settings file, first one found wins:
 *   site-settings.php       <- use this on production
 *   staging-settings.php    <- existing staging file, still honoured
 *
 * Neither is in version control: they hold the database password.
 */

// 1. Load environment settings BEFORE session_start()
$__settingsFile = null;
foreach (['site-settings.php', 'staging-settings.php'] as $__candidate) {
    if (file_exists(__DIR__ . '/' . $__candidate)) { $__settingsFile = __DIR__ . '/' . $__candidate; break; }
}
if ($__settingsFile === null) {
    http_response_code(500);
    error_log('ROC config: no settings file found in ' . __DIR__);
    exit(json_encode(['success' => false, 'error' => 'Server configuration missing.']));
}
$env = require $__settingsFile;

// Fill in anything the settings file leaves out, so an older staging file keeps working.
$env += [
    'SITE_URL' => 'https://www.runonconsole.com',
    'SMTP_HOST' => '', 'SMTP_PORT' => 587, 'SMTP_ENCRYPTION' => 'tls',
    'SMTP_USERNAME' => '', 'SMTP_PASSWORD' => '',
    'MAIL_FROM_ADDRESS' => '', 'MAIL_FROM_NAME' => 'Run On Console', 'MAIL_REPLY_TO' => '',
    'GOOGLE_CLIENT_ID' => '', 'GOOGLE_CLIENT_SECRET' => '', 'GOOGLE_REDIRECT_URI' => '',
    'CAPTCHA_SITE_KEY' => '', 'CAPTCHA_SECRET_KEY' => '', 'CAPTCHA_PROVIDER' => 'disabled',
];

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
date_default_timezone_set('UTC');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);

// Security Header: Enforce Noindex, Nofollow for all API endpoints
header("X-Robots-Tag: noindex, nofollow", true);
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

// Strict CORS Security using ROC_SITE_URL
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = defined('ROC_SITE_URL') ? ROC_SITE_URL : rtrim($env['SITE_URL'], '/');

if (!empty($origin)) {
    if (strtolower(rtrim($origin, '/')) !== strtolower(rtrim($allowedOrigin, '/'))) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "CORS origin forbidden."]);
        exit();
    }
    header("Access-Control-Allow-Origin: " . $allowedOrigin);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Session Security Cookie Parameters & Initialization
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    if (defined('ROC_SESSION_NAME')) {
        session_name(ROC_SESSION_NAME);
    } else {
        session_name('ROCSESSID');
        // The staging-named cookie used before October 2026: remove it from the browser.
        if (isset($_COOKIE['ROCSTAGINGSESSID'])) setcookie('ROCSTAGINGSESSID', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    }

    session_set_cookie_params([
        'lifetime' => 86400 * 30, // 30 Days
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ROC_SITE_URL is read by index.php, sitemap.xml.php, cms/media.php and the
// agent endpoints, but nothing ever defined it, so each fell back to its own
// hard-coded guess. Those guesses disagreed: sitemap used www, agent/blogs.php
// used the bare domain, and cms/media.php fell back to an empty string, which
// makes uploaded image URLs come out relative. Defining it once here settles it.
if (!defined('ROC_SITE_URL')) {
    define('ROC_SITE_URL', rtrim($env['SITE_URL'], '/'));
}

// Define Database Constants directly from $env (no fallbacks or alternate paths)
define('DB_HOST', $env['DB_HOST']);
define('DB_NAME', $env['DB_NAME']);
define('DB_USER', $env['DB_USER']);
define('DB_PASS', $env['DB_PASSWORD']);

// Mail, OAuth and CAPTCHA come from the settings file. Leaving a value empty
// keeps that feature off, which is how staging behaved before.
define('SMTP_HOST', $env['SMTP_HOST']);
define('SMTP_PORT', (int)$env['SMTP_PORT']);
define('SMTP_ENCRYPTION', $env['SMTP_ENCRYPTION']);
define('SMTP_USERNAME', $env['SMTP_USERNAME']);
define('SMTP_PASSWORD', $env['SMTP_PASSWORD']);
define('MAIL_FROM_ADDRESS', $env['MAIL_FROM_ADDRESS']);
define('MAIL_FROM_NAME', $env['MAIL_FROM_NAME']);
define('MAIL_REPLY_TO', $env['MAIL_REPLY_TO']);

define('GOOGLE_CLIENT_ID', $env['GOOGLE_CLIENT_ID']);
define('GOOGLE_CLIENT_SECRET', $env['GOOGLE_CLIENT_SECRET']);
define('GOOGLE_REDIRECT_URI', $env['GOOGLE_REDIRECT_URI']);

define('CAPTCHA_SITE_KEY', $env['CAPTCHA_SITE_KEY']);
define('CAPTCHA_SECRET_KEY', $env['CAPTCHA_SECRET_KEY']);
define('CAPTCHA_PROVIDER', $env['CAPTCHA_PROVIDER']);

/**
 * Returns PDO Database Connection Instance (Fail-Closed)
 * Still fail-closed: it connects only to the database named in the settings file
 * and verifies after connecting that it landed there. The database name is no
 * longer hard-coded, which is what stopped this running anywhere but staging.
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (empty(DB_NAME) || empty(DB_USER)) {
        error_log('ROC config: DB_NAME or DB_USER missing from the settings file.');
        return null;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        $instance->exec("SET time_zone = '+00:00'");

        // Execute SELECT DATABASE() after PDO creation
        $dbCheck = $instance->query("SELECT DATABASE()")->fetchColumn();
        if ($dbCheck !== DB_NAME) {
            error_log("ROC config: connected to '{$dbCheck}' but expected '" . DB_NAME . "'.");
            return null;
        }

        $pdo = $instance;
        return $pdo;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Handle Missing Database Connection (Returns HTTP 503 Service Unavailable)
 */
function handleDatabaseUnavailable() {
    http_response_code(503);
    echo json_encode([
        "success" => false,
        "error" => "Database connection unavailable."
    ]);
    exit();
}

/**
 * Argon2id Password Hashing with Bcrypt Fallback
 */
function hashPasswordSecure($password) {
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 1
        ]);
    }
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPasswordSecure($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Validate Unique Username Requirements
 */
function validateUsername($username) {
    $username = trim((string)$username);
    
    if (strlen($username) < 3 || strlen($username) > 30) {
        return [
            'valid' => false,
            'error' => 'Username must be between 3 and 30 characters.'
        ];
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return [
            'valid' => false,
            'error' => 'Username may only contain letters, numbers, and underscores.'
        ];
    }

    $reserved = ['admin', 'administrator', 'support', 'root', 'runonconsole', 'system', 'moderator', 'guest', 'api', 'help'];
    if (in_array(strtolower($username), $reserved, true)) {
        return [
            'valid' => false,
            'error' => 'This username is reserved and cannot be registered.'
        ];
    }

    $pdo = getDBConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                return [
                    'valid' => false,
                    'error' => 'This username is already taken by another gamer.'
                ];
            }
        } catch (\Throwable $e) {}
    }

    return ['valid' => true, 'username' => $username];
}

/**
 * Server-Verified CAPTCHA Engine (Fail-Closed)
 */
function verifyCaptcha($responseToken, $ip, $action = 'signup') {
    if (empty(CAPTCHA_SECRET_KEY)) {
        // Fallback: If no secret key is set, check server math session CAPTCHA
        $serverMathAnswer = $_SESSION['captcha_answer'] ?? null;
        if (!empty($responseToken) && !empty($serverMathAnswer) && (string)$responseToken === (string)$serverMathAnswer) {
            unset($_SESSION['captcha_answer']);
            return true;
        }
        // If CAPTCHA is required but unconfigured on production signup, return false
        return false;
    }

    $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    if (CAPTCHA_PROVIDER === 'recaptcha') {
        $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    } else if (CAPTCHA_PROVIDER === 'hcaptcha') {
        $verifyUrl = 'https://hcaptcha.com/siteverify';
    }

    $postData = http_build_query([
        'secret' => CAPTCHA_SECRET_KEY,
        'response' => $responseToken,
        'remoteip' => $ip
    ]);

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 5
        ]
    ];

    try {
        $context = stream_context_create($opts);
        $result = @file_get_contents($verifyUrl, false, $context);
        if ($result) {
            $data = json_decode($result, true);
            return isset($data['success']) && $data['success'] === true;
        }
    } catch (\Throwable $e) {}

    return false;
}

function hashToken($token) {
    return hash('sha256', $token);
}

function hashEmail($email) {
    return hash('sha256', strtolower(trim($email)));
}

function generateSecureToken() {
    return bin2hex(random_bytes(32));
}

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($providedToken = null) {
    if ($providedToken === null) {
        $headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
        $providedToken = $headers['x-csrf-token'] ?? $_POST['csrf_token'] ?? '';
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken) || empty($providedToken)) {
        return false;
    }

    return hash_equals($sessionToken, $providedToken);
}

/**
 * Database Session Verification
 */
function validateDatabaseSession() {
    $dbSessionId = $_SESSION['db_session_id'] ?? null;
    $dbSessionToken = $_SESSION['db_session_token'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    if (!$dbSessionId || !$dbSessionToken || !$userId) {
        return false;
    }

    $pdo = getDBConnection();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("SELECT user_id FROM sessions WHERE id = ? AND session_token_hash = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$dbSessionId, hashToken($dbSessionToken)]);
        $sessionRecord = $stmt->fetch();

        if ($sessionRecord && (int)$sessionRecord['user_id'] === (int)$userId) {
            return true;
        }
    } catch (\Throwable $e) {}

    // Stale database session -> destroy stale session
    unset($_SESSION['user_id']);
    unset($_SESSION['db_session_id']);
    unset($_SESSION['db_session_token']);
    session_destroy();
    
    session_start();
    session_regenerate_id(true);
    getCsrfToken();
    return false;
}

/**
 * Dedicated Rate Limiter (Logs ALL Requests in request_rate_limits)
 */
function isRateLimited($ip, $email, $action = 'login', $maxAttempts = 5, $windowSeconds = 900) {
    $pdo = getDBConnection();
    if (!$pdo) return false;

    $emailHash = hashEmail($email);

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM request_rate_limits WHERE (ip_address = ? OR email_hash = ?) AND action = ? AND attempted_at >= (NOW() - INTERVAL ? SECOND)");
        $stmt->execute([$ip, $emailHash, $action, $windowSeconds]);
        $attempts = (int) $stmt->fetchColumn();
        return $attempts >= $maxAttempts;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Log Request Attempt in Dedicated Rate Limits Table
 */
function logAttempt($ip, $email, $action = 'login') {
    $pdo = getDBConnection();
    if (!$pdo) return;

    $emailHash = hashEmail($email);

    try {
        $stmt = $pdo->prepare("INSERT INTO request_rate_limits (ip_address, email_hash, action) VALUES (?, ?, ?)");
        $stmt->execute([$ip, $emailHash, $action]);
    } catch (\Throwable $e) {}
}

/**
 * Queue Email Helper with Failure Detection
 * Respects ROC_MAIL_ENABLED flag on staging environment
 */
function queueEmail($toEmail, $subject, $htmlBody) {
    if (!defined('ROC_MAIL_ENABLED') || ROC_MAIL_ENABLED !== true) {
        return false;
    }

    $pdo = getDBConnection();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("INSERT INTO email_queue (to_email, subject, html_body, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$toEmail, $subject, $htmlBody]);
        $id = $pdo->lastInsertId();
        return ($id !== false && $id !== null && $id !== '') ? (int)$id : true;
    } catch (\Throwable $e) {
        error_log("[queueEmail Error] " . $e->getMessage());
        return false;
    }
}
