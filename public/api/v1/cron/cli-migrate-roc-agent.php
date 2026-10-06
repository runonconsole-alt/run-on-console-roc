<?php
/**
 * Run On Console (ROC) - ROC Agent Phase 1 Private CLI Migration Script
 * Safe CLI-only migration creating empty agent tables, indexes, and foreign keys.
 *
 * Migration & Verification Features:
 * - Scopes INFORMATION_SCHEMA checks to TABLE_SCHEMA = DATABASE().
 * - Inspects `created_at` and `updated_at` columns INDEPENDENTLY and adds ONLY missing columns.
 * - Verifies unique index `idx_game_req_type` by `NON_UNIQUE = 0` and exact column sequence (`game_id`, `requirement_type`).
 * - Detects duplicate game_requirements before adding/repairing unique key.
 * - Adds `status ENUM('published', 'draft') NOT NULL DEFAULT 'draft'` to blogs if missing.
 * - NO automatic blog publication updates (publication backfill is separate via backfill-blog-status.php).
 *
 * Usage via CLI:
 *   php api/v1/cron/cli-migrate-roc-agent.php
 */

if (php_sapi_name() !== 'cli' && empty($_SERVER['ROC_CLI_RUN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. CLI execution required.']);
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';

function rocAgentCronCliMigrate(): void {
    echo "============================================================\n";
    echo "RUN ON CONSOLE (ROC) - PRIVATE CLI AGENT MIGRATION\n";
    echo "============================================================\n\n";

    $pdo = getDBConnection();
    if (!$pdo) {
        echo "❌ Error: Database connection unavailable.\n";
        exit(1);
    }

    $tables = [
        "roc_conversations" => "CREATE TABLE IF NOT EXISTS roc_conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            guest_session_id VARCHAR(128) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_user_id (user_id),
            KEY idx_guest_session (guest_session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "roc_messages" => "CREATE TABLE IF NOT EXISTS roc_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            role ENUM('user', 'assistant') NOT NULL,
            message TEXT NOT NULL,
            intent VARCHAR(64) NULL,
            metadata_json JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_conversation_id (conversation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "roc_user_devices" => "CREATE TABLE IF NOT EXISTS roc_user_devices (
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

        "roc_feedback" => "CREATE TABLE IF NOT EXISTS roc_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            message_id INT NULL,
            user_id INT NULL,
            rating TINYINT NOT NULL,
            feedback TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_conv_user (conversation_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "games" => "CREATE TABLE IF NOT EXISTS games (
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

        "game_requirements" => "CREATE TABLE IF NOT EXISTS game_requirements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            game_id INT NOT NULL,
            requirement_type ENUM('minimum', 'recommended') NOT NULL,
            cpu VARCHAR(150) NOT NULL,
            gpu VARCHAR(150) NOT NULL,
            ram_gb INT NOT NULL,
            storage_gb INT NOT NULL DEFAULT 50,
            operating_system VARCHAR(100) NOT NULL DEFAULT 'Windows 10 64-bit',
            notes VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_game_req_type (game_id, requirement_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "roc_hardware_mappings" => "CREATE TABLE IF NOT EXISTS roc_hardware_mappings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            component_type ENUM('gpu', 'cpu') NOT NULL,
            raw_name VARCHAR(150) NOT NULL,
            normalized_name VARCHAR(150) NOT NULL,
            tier INT NOT NULL,
            architecture VARCHAR(100) DEFAULT 'Standard',
            provenance VARCHAR(255) DEFAULT 'Database Verified',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_type_raw (component_type, raw_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "roc_write_for_us_submissions" => "CREATE TABLE IF NOT EXISTS roc_write_for_us_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(150) NOT NULL,
            proposed_topic VARCHAR(200) NOT NULL,
            short_pitch TEXT NOT NULL,
            experience TEXT NULL,
            portfolio_url VARCHAR(300) NULL,
            status ENUM('pending_review', 'approved', 'rejected') DEFAULT 'pending_review',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_status_email (status, email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    foreach ($tables as $name => $sql) {
        try {
            $pdo->exec($sql);
            echo "  ✓ Table '{$name}' created or verified.\n";
        } catch (\Throwable $e) {
            echo "❌ Fatal Error creating table '{$name}': " . $e->getMessage() . "\n";
            exit(1);
        }
    // Additive Inspection of `metadata_json` in `roc_messages`
    try {
        $stmtMeta = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roc_messages' AND COLUMN_NAME = 'metadata_json'");
        if ((int)$stmtMeta->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE roc_messages ADD COLUMN metadata_json JSON NULL AFTER intent");
            echo "  ✓ Added missing metadata_json column to 'roc_messages'.\n";
        } else {
            echo "  ✓ Column metadata_json in 'roc_messages' verified.\n";
        }
    } catch (\Throwable $e) {
        echo "❌ Fatal Error inspecting metadata_json column: " . $e->getMessage() . "\n";
        exit(1);
    }

    // 1. Independent Inspection of `created_at` and `updated_at` in `game_requirements`
    try {
        $stmtCreatedAt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_requirements' AND COLUMN_NAME = 'created_at'");
        $hasCreatedAt = (int)$stmtCreatedAt->fetchColumn() > 0;

        $stmtUpdatedAt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_requirements' AND COLUMN_NAME = 'updated_at'");
        $hasUpdatedAt = (int)$stmtUpdatedAt->fetchColumn() > 0;

        if (!$hasCreatedAt && !$hasUpdatedAt) {
            $pdo->exec("ALTER TABLE game_requirements ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP, ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            echo "  ✓ Added missing created_at and updated_at columns to 'game_requirements'.\n";
        } elseif (!$hasCreatedAt) {
            $pdo->exec("ALTER TABLE game_requirements ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
            echo "  ✓ Added missing created_at column to 'game_requirements'.\n";
        } elseif (!$hasUpdatedAt) {
            $pdo->exec("ALTER TABLE game_requirements ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            echo "  ✓ Added missing updated_at column to 'game_requirements'.\n";
        } else {
            echo "  ✓ Columns created_at and updated_at in 'game_requirements' independently verified.\n";
        }
    } catch (\Throwable $e) {
        echo "❌ Fatal Error inspecting timestamp columns: " . $e->getMessage() . "\n";
        exit(1);
    }

    // 2. Inspection of Duplicate Records & Deep Unique Index Verification (NON_UNIQUE = 0 & Column Order)
    try {
        // Detect duplicate (game_id, requirement_type) pairs FIRST
        $stmtDup = $pdo->query("SELECT game_id, requirement_type, COUNT(*) as cnt FROM game_requirements GROUP BY game_id, requirement_type HAVING cnt > 1");
        $dups = $stmtDup->fetchAll();

        if (!empty($dups)) {
            echo "❌ Fatal Error: Duplicate (game_id, requirement_type) records detected in 'game_requirements':\n";
            foreach ($dups as $d) {
                echo "   -> Game ID: {$d['game_id']}, Type: {$d['requirement_type']}, Count: {$d['cnt']}\n";
            }
            echo "Stopping migration for manual review. Duplicates will not be deleted automatically.\n";
            exit(1);
        }

        // Deep Index Inspection: Check NON_UNIQUE = 0 and column order
        $stmtIdxDetails = $pdo->query("SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_requirements' AND INDEX_NAME = 'idx_game_req_type' ORDER BY SEQ_IN_INDEX ASC");
        $idxCols = $stmtIdxDetails->fetchAll();

        $isUniqueIndexValid = false;
        if (count($idxCols) === 2) {
            $col1 = $idxCols[0];
            $col2 = $idxCols[1];
            if ((int)$col1['NON_UNIQUE'] === 0 && $col1['COLUMN_NAME'] === 'game_id' && (int)$col2['NON_UNIQUE'] === 0 && $col2['COLUMN_NAME'] === 'requirement_type') {
                $isUniqueIndexValid = true;
            }
        }

        if (!$isUniqueIndexValid) {
            if (!empty($idxCols)) {
                echo "  ⚠️ Found incorrectly defined index 'idx_game_req_type'. Dropping invalid index...\n";
                $pdo->exec("ALTER TABLE game_requirements DROP INDEX idx_game_req_type");
            }
            $pdo->exec("ALTER TABLE game_requirements ADD UNIQUE KEY idx_game_req_type (game_id, requirement_type)");
            echo "  ✓ Added valid UNIQUE KEY idx_game_req_type (game_id, requirement_type).\n";
        } else {
            echo "  ✓ UNIQUE KEY idx_game_req_type (NON_UNIQUE=0, cols: game_id, requirement_type) verified.\n";
        }
    } catch (\Throwable $e) {
        echo "❌ Fatal Error verifying unique index on 'game_requirements': " . $e->getMessage() . "\n";
        exit(1);
    }

    // 3. Additive Schema Migration for `blogs` Table (Column addition ONLY, NO automatic backfill)
    try {
        $stmtChk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blogs' AND COLUMN_NAME = 'status'");
        $hasStatusCol = (int)$stmtChk->fetchColumn() > 0;

        if (!$hasStatusCol) {
            // Default to 'draft' so existing and unclassified records preserve draft status
            $pdo->exec("ALTER TABLE blogs ADD COLUMN status ENUM('published', 'draft') NOT NULL DEFAULT 'draft'");
            echo "  ✓ Additive column 'status' (DEFAULT 'draft') added to 'blogs' table.\n";
            echo "  ℹ️  Note: Blog publication backfill is separate. Run 'php api/v1/cron/backfill-blog-status.php' to apply approved publication mapping.\n";
        } else {
            echo "  ✓ Column 'status' in 'blogs' table already verified.\n";
        }
    } catch (\Throwable $e) {
        echo "❌ Fatal Error altering 'blogs' table: " . $e->getMessage() . "\n";
        exit(1);
    }

    // 4. Strict Migration Verification Check
    $verificationOk = true;
    foreach (array_keys($tables) as $name) {
        $chk = $pdo->query("SHOW TABLES LIKE '{$name}'");
        if (!$chk->fetch()) {
            echo "❌ Schema Verification Failed: Table '{$name}' missing!\n";
            $verificationOk = false;
        }
    }

    // Verify UNIQUE constraint specifications on game_requirements
    $stmtKeyVerification = $pdo->query("SELECT NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_requirements' AND INDEX_NAME = 'idx_game_req_type' ORDER BY SEQ_IN_INDEX ASC");
    $vCols = $stmtKeyVerification->fetchAll();

    if (count($vCols) !== 2 || (int)$vCols[0]['NON_UNIQUE'] !== 0 || $vCols[0]['COLUMN_NAME'] !== 'game_id' || $vCols[1]['COLUMN_NAME'] !== 'requirement_type') {
        echo "❌ Constraint Verification Failed: idx_game_req_type index is non-unique or has invalid column sequence!\n";
        $verificationOk = false;
    }

    if (!$verificationOk) {
        echo "❌ Error: Migration failed strict schema verification check.\n";
        exit(1);
    }

    echo "\n============================================================\n";
    echo "✅ CLI MIGRATION SUCCESS: All tables, columns, and constraints verified!\n";
    echo "============================================================\n";
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocAgentCronCliMigrate();
}
