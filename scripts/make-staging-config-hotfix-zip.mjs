import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';

const ROOT_DIR = process.cwd();
const targetZip = path.join(ROOT_DIR, 'staging-config-hotfix.zip');
const tempDir = path.join(ROOT_DIR, 'temp_staging_config_hotfix');

console.log('🚀 Creating Staging Configuration Hotfix ZIP (staging-config-hotfix.zip)...');

// 1. Ensure temp directory clean state
if (fs.existsSync(tempDir)) {
  fs.rmSync(tempDir, { recursive: true, force: true });
}
if (fs.existsSync(targetZip)) {
  fs.unlinkSync(targetZip);
}

// 2. Prepare full, un-truncated api/v1/config.php content with staging settings and all 15 helper functions
const configPhpContent = `<?php
/**
 * Run On Console (ROC) - API v1 Configuration & Security Engine (Staging Isolated)
 */

// 1. Load staging settings BEFORE session_start()
$env = require __DIR__ . '/staging-settings.php';

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
$allowedOrigin = defined('ROC_SITE_URL') ? ROC_SITE_URL : 'https://staging.runonconsole.com';

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
        session_name('ROCSTAGINGSESSID');
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

// Define Database Constants directly from $env (no fallbacks or alternate paths)
define('DB_HOST', $env['DB_HOST']);
define('DB_NAME', $env['DB_NAME']);
define('DB_USER', $env['DB_USER']);
define('DB_PASS', $env['DB_PASSWORD']);

// Mail & Google OAuth strictly disabled on staging environment
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('MAIL_FROM_ADDRESS', 'noreply@staging.runonconsole.com');
define('MAIL_FROM_NAME', 'Run On Console Staging');
define('MAIL_REPLY_TO', 'support@staging.runonconsole.com');

define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI', 'https://staging.runonconsole.com/api/v1/oauth/google-callback.php');

define('CAPTCHA_SITE_KEY', '');
define('CAPTCHA_SECRET_KEY', '');
define('CAPTCHA_PROVIDER', 'disabled');

/**
 * Returns PDO Database Connection Instance (Fail-Closed)
 * Strictly verifies database is runoncon_rocstage. No searching or fallback to localhost.
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (empty(DB_NAME) || empty(DB_USER) || DB_NAME !== 'runoncon_rocstage') {
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
        if ($dbCheck !== 'runoncon_rocstage') {
            return null;
        }

        $pdo = $instance;
        return $pdo;
    } catch (\\Throwable $e) {
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
        } catch (\\Throwable $e) {}
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
            'header' => "Content-type: application/x-www-form-urlencoded\\r\\n",
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
    } catch (\\Throwable $e) {}

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
    } catch (\\Throwable $e) {}

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
    } catch (\\Throwable $e) {
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
    } catch (\\Throwable $e) {}
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
    } catch (\\Throwable $e) {
        error_log("[queueEmail Error] " . $e->getMessage());
        return false;
    }
}
`;

// Write to public/api/v1/config.php and dist/api/v1/config.php and tempDir
const publicConfigPath = path.join(ROOT_DIR, 'public/api/v1/config.php');
fs.mkdirSync(path.dirname(publicConfigPath), { recursive: true });
fs.writeFileSync(publicConfigPath, configPhpContent, 'utf-8');

const distConfigPath = path.join(ROOT_DIR, 'dist/api/v1/config.php');
fs.mkdirSync(path.dirname(distConfigPath), { recursive: true });
fs.writeFileSync(distConfigPath, configPhpContent, 'utf-8');

const tempConfigPath = path.join(tempDir, 'api/v1/config.php');
fs.mkdirSync(path.dirname(tempConfigPath), { recursive: true });
fs.writeFileSync(tempConfigPath, configPhpContent, 'utf-8');

// 3. Package staging-config-hotfix.zip with 100% forward slashes using PowerShell ZipArchive
const psCommand = `
$tempFolder = '${tempDir.replace(/\\/g, '\\\\')}';
$zipPath = '${targetZip.replace(/\\/g, '\\\\')}';

Add-Type -AssemblyName System.IO.Compression;
Add-Type -AssemblyName System.IO.Compression.FileSystem;

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create);

$fileToZip = Get-Item (Join-Path $tempFolder 'api\\v1\\config.php');
$relativePath = 'api/v1/config.php';

[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $fileToZip.FullName, $relativePath, [System.IO.Compression.CompressionLevel]::Optimal);

$zip.Dispose();
`;

execSync(`powershell -NoProfile -Command "${psCommand.replace(/\r?\n/g, ' ')}"`);

console.log(`✅ Staging Config Hotfix ZIP created successfully at: ${targetZip}`);

// 4. Verify ZIP contents
const verifyOutput = String(execSync(`powershell -NoProfile -Command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::OpenRead('${targetZip.replace(/\\/g, '\\\\')}').Entries | Select-Object -ExpandProperty FullName"`, { encoding: 'utf-8' }));
const entries = verifyOutput.split(/\r?\n/).filter(l => l.trim() !== '');

console.log('📦 ZIP Entries:', entries);
if (entries.length === 1 && entries[0] === 'api/v1/config.php') {
  console.log('✓ VERIFIED: staging-config-hotfix.zip contains EXACTLY one entry: api/v1/config.php with forward slashes.');
} else {
  console.error('❌ ZIP VERIFICATION FAILED! Unexpected entries:', entries);
  process.exit(1);
}
