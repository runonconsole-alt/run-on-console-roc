<?php
/**
 * Run On Console (ROC) - ROC Agent Phase 1 Private CLI Migration Script
 * Safe CLI-only migration creating empty agent tables, indexes, and foreign keys.
 * NO automatic seeding of games or hardware specs.
 *
 * Usage via CLI:
 *   php scripts/cli-migrate-roc-agent.php
 *   php public/api/v1/cron/cli-migrate-roc-agent.php
 */

if (php_sapi_name() !== 'cli' && empty($_SERVER['ROC_CLI_RUN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. CLI execution required.']);
    exit(1);
}

// Locate config.php relative to file location
$configPath = __DIR__ . '/../public/api/v1/config.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__) . '/config.php';
}
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/config.php';
}

require_once $configPath;

function rocAgentCliMigrate(): void {
    echo "============================================================\n";
    echo "RUN ON CONSOLE (ROC) - PRIVATE CLI AGENT MIGRATION\n";
    echo "============================================================\n\n";

    $pdo = getDBConnection();
    if (!$pdo) {
        echo "❌ Error: Database connection unavailable.\n";
        exit(1);
    }

    $queries = [
        // 1. roc_conversations
        "CREATE TABLE IF NOT EXISTS roc_conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            guest_session_id VARCHAR(128) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_user_id (user_id),
            KEY idx_guest_session (guest_session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 2. roc_messages
        "CREATE TABLE IF NOT EXISTS roc_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            role ENUM('user', 'assistant') NOT NULL,
            message TEXT NOT NULL,
            intent VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_conversation_id (conversation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 3. roc_user_devices
        "CREATE TABLE IF NOT EXISTS roc_user_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            device_name VARCHAR(120) NOT NULL,
            device_type VARCHAR(60) NOT NULL DEFAULT 'Desktop PC',
            cpu VARCHAR(150) NOT NULL,
            gpu VARCHAR(150) NOT NULL,
            ram_gb INT NOT NULL,
            storage VARCHAR(100) NULL,
            operating_system VARCHAR(100) NOT NULL DEFAULT 'Windows 11',
            resolution VARCHAR(50) NULL DEFAULT '1080p',
            is_default TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_user_id (user_id),
            KEY idx_user_default (user_id, is_default)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 4. roc_feedback
        "CREATE TABLE IF NOT EXISTS roc_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            message_id INT NULL,
            user_id INT NULL,
            rating TINYINT NOT NULL,
            feedback TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_conv_user (conversation_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 5. games
        "CREATE TABLE IF NOT EXISTS games (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL UNIQUE,
            platform VARCHAR(100) NOT NULL DEFAULT 'PC',
            genre VARCHAR(100) NULL,
            image VARCHAR(255) NULL,
            description TEXT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_status_slug (status, slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 6. game_requirements
        "CREATE TABLE IF NOT EXISTS game_requirements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            game_id INT NOT NULL,
            requirement_type ENUM('minimum', 'recommended') NOT NULL,
            cpu VARCHAR(150) NOT NULL,
            gpu VARCHAR(150) NOT NULL,
            ram_gb INT NOT NULL,
            storage_gb INT NOT NULL DEFAULT 50,
            operating_system VARCHAR(100) NOT NULL DEFAULT 'Windows 10',
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_game_req (game_id, requirement_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 7. roc_write_for_us_submissions
        "CREATE TABLE IF NOT EXISTS roc_write_for_us_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(150) NOT NULL,
            proposed_topic VARCHAR(200) NOT NULL,
            short_pitch TEXT NOT NULL,
            experience TEXT NOT NULL,
            portfolio_url VARCHAR(300) NULL,
            status ENUM('pending_review', 'approved', 'rejected') DEFAULT 'pending_review',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_status_email (status, email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        // 8. roc_hardware_mappings (Database-backed hardware tier mapping with provenance)
        "CREATE TABLE IF NOT EXISTS roc_hardware_mappings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            component_type ENUM('cpu', 'gpu') NOT NULL,
            raw_name VARCHAR(150) NOT NULL,
            normalized_name VARCHAR(150) NOT NULL,
            tier TINYINT NOT NULL,
            architecture VARCHAR(100) NULL,
            provenance VARCHAR(255) NOT NULL DEFAULT 'manual_verification',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_type_raw (component_type, raw_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    try {
        foreach ($queries as $sql) {
            $pdo->exec($sql);
        }
        echo "✓ Agent database tables and indexes created/verified cleanly.\n";
        echo "✓ NO automatic seeding was performed. Seeding must be run separately via seed-roc-agent-games.php.\n";
        echo "\n✅ Migration finished cleanly.\n";
    } catch (\Throwable $e) {
        echo "❌ Migration failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

rocAgentCliMigrate();
