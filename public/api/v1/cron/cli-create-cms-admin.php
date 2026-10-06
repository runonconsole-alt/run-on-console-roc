<?php
/**
 * Run On Console (ROC) - CLI CMS Administrator Account Provisioning Utility
 * CLI-only script creating initial CMS administrator user with secure password input.
 *
 * Usage via CLI:
 *   php public/api/v1/cron/cli-create-cms-admin.php --username=admin --email=admin@runonconsole.com
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. CLI execution required.']);
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';

function rocCmsCliPromptPassword(string $prompt = "Enter Password: "): string {
    echo $prompt;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows CLI password prompt
        $pass = trim((string)shell_exec('powershell -Command "$p = read-host -assecurestring; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"'));
        echo "\n";
        return $pass;
    } else {
        // Unix/Linux hidden password prompt
        system('stty -echo');
        $pass = trim((string)fgets(STDIN));
        system('stty echo');
        echo "\n";
        return $pass;
    }
}

function rocCmsCronCreateAdmin(): void {
    global $argv;
    $args = $argv ?? [];

    echo "============================================================\n";
    echo "RUN ON CONSOLE (ROC) - CLI CMS ADMIN PROVISIONING\n";
    echo "============================================================\n\n";

    $username = null;
    $email = null;
    $role = 'administrator';

    foreach ($args as $arg) {
        if (str_starts_with($arg, '--username=')) {
            $username = trim(substr($arg, 11));
        } elseif (str_starts_with($arg, '--email=')) {
            $email = trim(substr($arg, 8));
        } elseif (str_starts_with($arg, '--role=')) {
            $role = trim(substr($arg, 7));
        }
    }

    if (empty($username)) {
        echo "Enter Username: ";
        $username = trim((string)fgets(STDIN));
    }

    if (empty($email)) {
        echo "Enter Email Address: ";
        $email = trim((string)fgets(STDIN));
    }

    if (empty($username) || empty($email)) {
        echo "❌ Error: Username and Email are required.\n";
        exit(1);
    }

    // Password acquisition: Environment variable fallback or interactive prompt
    $password = getenv('ROC_ADMIN_PASS') ?: '';
    if (empty($password)) {
        $password = rocCmsCliPromptPassword("Enter Password for '{$username}': ");
        $confirm = rocCmsCliPromptPassword("Confirm Password: ");
        if ($password !== $confirm) {
            echo "❌ Error: Password confirmation does not match.\n";
            exit(1);
        }
    }

    if (strlen($password) < 8) {
        echo "❌ Error: Password must be at least 8 characters long.\n";
        exit(1);
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        echo "❌ Error: Database connection unavailable.\n";
        exit(1);
    }

    try {
        $dbStmt = $pdo->query("SELECT DATABASE()");
        $currentDb = (string)$dbStmt->fetchColumn();

        if ($currentDb !== 'runoncon_rocstage') {
            echo "❌ Error: Target database must be 'runoncon_rocstage'. Connected database is '{$currentDb}'.\n";
            exit(1);
        }

        $stmtChk = $pdo->prepare("SELECT id FROM cms_users WHERE username = ? OR email = ?");
        $stmtChk->execute([$username, $email]);
        $existing = $stmtChk->fetch();

        $passHash = password_hash($password, PASSWORD_DEFAULT);

        if ($existing) {
            $stmtUpd = $pdo->prepare("UPDATE cms_users SET password_hash = ?, role = ?, status = 'active', updated_at = NOW() WHERE id = ?");
            $stmtUpd->execute([$passHash, $role, $existing['id']]);
            echo "  ✓ Updated password & role for existing CMS user '{$username}' (ID: {$existing['id']}).\n";
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO cms_users (username, email, password_hash, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', NOW(), NOW())");
            $stmtIns->execute([$username, $email, $passHash, $role]);
            $newId = $pdo->lastInsertId();
            echo "  ✓ Created new CMS Administrator user '{$username}' (ID: {$newId}, Role: {$role}).\n";
        }

        echo "\n✅ Administrator provisioning completed successfully.\n";
        exit(0);

    } catch (\Throwable $e) {
        echo "❌ Error provisioning CMS admin: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocCmsCronCreateAdmin();
}
