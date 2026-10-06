<?php
/**
 * Run On Console (ROC) - Additive CLI Migration: Add metadata_json to roc_messages
 * CLI-only execution script adding metadata_json JSON NULL column if not exists.
 *
 * Usage via CLI:
 *   php api/v1/cron/cli-add-metadata-json-roc-messages.php           (Preview mode only)
 *   php api/v1/cron/cli-add-metadata-json-roc-messages.php --apply   (Execute migration)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. CLI execution required.']);
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';

function rocAgentCronAddMetadataJson(?array $args = null): void {
    global $argv;
    if ($args === null) {
        $args = $argv ?? [];
    }

    $isApply = in_array('--apply', $args, true);

    echo "============================================================\n";
    echo "RUN ON CONSOLE (ROC) - ADDITIVE MIGRATION: metadata_json\n";
    echo "============================================================\n\n";

    $pdo = getDBConnection();
    if (!$pdo) {
        echo "❌ Error: Database connection unavailable.\n";
        exit(1);
    }

    try {
        $dbStmt = $pdo->query("SELECT DATABASE()");
        $currentDb = (string)$dbStmt->fetchColumn();

        $colStmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roc_messages' AND COLUMN_NAME = 'metadata_json'");
        $alreadyExists = (int)$colStmt->fetchColumn() > 0;

        if (!$isApply) {
            echo "🔍 PREVIEW MODE (Default):\n";
            echo "   Target Table: roc_messages\n";
            echo "   Connected Database: {$currentDb}\n";
            echo "   Required Database: runoncon_rocstage\n";
            echo "   Column metadata_json Exists: " . ($alreadyExists ? "YES" : "NO") . "\n";
            echo "   Planned Action: " . ($alreadyExists ? "No schema change needed." : "ALTER TABLE roc_messages ADD COLUMN metadata_json JSON NULL AFTER intent") . "\n\n";
            echo "💡 Migration was NOT applied. To execute ALTER TABLE, pass the literal --apply flag:\n";
            echo "   php api/v1/cron/cli-add-metadata-json-roc-messages.php --apply\n";
            exit(0);
        }

        // Apply Mode - Strict Database Name Verification
        if ($currentDb !== 'runoncon_rocstage') {
            echo "❌ Error: Target database must be 'runoncon_rocstage'. Connected database is '{$currentDb}'. Migration aborted.\n";
            exit(1);
        }

        if (!$alreadyExists) {
            $pdo->exec("ALTER TABLE roc_messages ADD COLUMN metadata_json JSON NULL AFTER intent");
            echo "  ✓ Executed: ALTER TABLE roc_messages ADD COLUMN metadata_json JSON NULL AFTER intent\n";
        } else {
            echo "  ✓ Column 'metadata_json' already present in table 'roc_messages'.\n";
        }

        // Post-alteration verification
        $verifyStmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roc_messages' AND COLUMN_NAME = 'metadata_json'");
        $verified = (int)$verifyStmt->fetchColumn() > 0;

        if (!$verified) {
            echo "❌ Error: Column 'metadata_json' verification failed after alteration.\n";
            exit(1);
        }

        echo "  ✓ Verified: Column 'metadata_json' exists in table 'roc_messages'.\n";
        echo "\n✅ Additive migration completed successfully.\n";
        exit(0);

    } catch (\Throwable $e) {
        echo "❌ Error applying additive migration: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocAgentCronAddMetadataJson();
}
