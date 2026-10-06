<?php
/**
 * Run On Console (ROC) - CLI Database Diagnostic Tool
 * Strictly CLI execution required. Never exposes passwords, API keys, or full credentials.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "CLI execution required."]);
    exit();
}

require_once dirname(__DIR__) . '/config.php';

$pdo = getDBConnection();

if (!$pdo) {
    echo "[DIAGNOSTIC FAILED] Unable to establish MySQL PDO connection. Check /config/env.php database credentials.\n";
    exit(1);
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT version FROM schema_migrations ORDER BY applied_at DESC LIMIT 1");
    $latestMigration = $stmt->fetchColumn() ?: 'none';

    echo "[DIAGNOSTIC SUCCESS] MySQL PDO connected successfully.\n";
    echo "  - Total Users Count: {$userCount}\n";
    echo "  - Latest Applied Migration: {$latestMigration}\n";
    exit(0);

} catch (\PDOException $e) {
    echo "[DIAGNOSTIC FAILED] Query execution error: Code " . $e->getCode() . "\n";
    exit(1);
}
