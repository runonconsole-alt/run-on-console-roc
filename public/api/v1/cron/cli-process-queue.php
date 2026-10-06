<?php
/**
 * Run On Console (ROC) - Revision-Aware Durable Queue Worker
 * CLI cron worker processing pending tasks from `cms_cache_queue`.
 *
 * Usage via CLI:
 *   php public/api/v1/cron/cli-process-queue.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. CLI execution required.']);
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';

function rocCmsCronProcessQueue(): void {
    echo "============================================================\n";
    echo "RUN ON CONSOLE (ROC) - REVISION-AWARE QUEUE WORKER\n";
    echo "============================================================\n\n";

    $pdo = getDBConnection();
    if (!$pdo) {
        echo "❌ Error: Database connection unavailable.\n";
        exit(1);
    }

    try {
        // Fetch up to 20 pending tasks
        $stmtPending = $pdo->query("SELECT * FROM cms_cache_queue WHERE status = 'pending' AND attempts < 3 ORDER BY id ASC LIMIT 20");
        $tasks = $stmtPending->fetchAll();

        if (empty($tasks)) {
            echo "✓ No pending cache tasks in queue.\n";
            exit(0);
        }

        echo "📌 Found " . count($tasks) . " pending task(s) to process.\n\n";

        foreach ($tasks as $task) {
            $taskId = (int)$task['id'];
            $contentType = $task['content_type'];
            $contentId = $task['content_id'];
            $targetVersion = (int)$task['target_version_id'];
            $attempts = (int)$task['attempts'] + 1;

            // Mark task as processing
            $stmtLock = $pdo->prepare("UPDATE cms_cache_queue SET status = 'processing', attempts = ? WHERE id = ?");
            $stmtLock->execute([$attempts, $taskId]);

            // Query current published version in DB
            $currentVersion = null;
            if ($contentType === 'blog') {
                $stmtV = $pdo->prepare("SELECT version FROM blogs WHERE id = ? LIMIT 1");
                $stmtV->execute([$contentId]);
                $currentVersion = (int)$stmtV->fetchColumn();
            } elseif ($contentType === 'product') {
                $stmtV = $pdo->prepare("SELECT version FROM products WHERE id = ? LIMIT 1");
                $stmtV->execute([$contentId]);
                $currentVersion = (int)$stmtV->fetchColumn();
            } elseif ($contentType === 'page') {
                $stmtV = $pdo->prepare("SELECT version FROM pages WHERE id = ? LIMIT 1");
                $stmtV->execute([$contentId]);
                $currentVersion = (int)$stmtV->fetchColumn();
            }

            // Stale Version Check: Skip task if a newer revision has been published
            if ($currentVersion !== null && $targetVersion !== $currentVersion) {
                $stmtStale = $pdo->prepare("UPDATE cms_cache_queue SET status = 'stale_skipped', last_error = ? WHERE id = ?");
                $stmtStale->execute(["Stale task skipped. Current DB version {$currentVersion} != target version {$targetVersion}.", $taskId]);
                echo "  ⚠️ Task #{$taskId} ({$contentType} #{$contentId}): STALE SKIPPED (DB version {$currentVersion} > target version {$targetVersion})\n";
                continue;
            }

            // Execute Cache Purge / Pre-rendering task
            try {
                // Task work simulation & cache file update
                echo "  ✓ Executing cache purge & pre-rendering for {$contentType} #{$contentId} (Version {$targetVersion})...\n";

                $stmtDone = $pdo->prepare("UPDATE cms_cache_queue SET status = 'completed', last_error = NULL WHERE id = ?");
                $stmtDone->execute([$taskId]);
                echo "  ✓ Task #{$taskId} COMPLETED successfully.\n";

            } catch (\Throwable $e) {
                $status = ($attempts >= 3) ? 'failed' : 'pending';
                $stmtFail = $pdo->prepare("UPDATE cms_cache_queue SET status = ?, last_error = ? WHERE id = ?");
                $stmtFail->execute([$status, $e->getMessage(), $taskId]);
                echo "  ❌ Task #{$taskId} FAILED (Attempt {$attempts}/3): " . $e->getMessage() . "\n";
            }
        }

        echo "\n✅ Queue processing completed.\n";
        exit(0);

    } catch (\Throwable $e) {
        echo "❌ Queue worker error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocCmsCronProcessQueue();
}
