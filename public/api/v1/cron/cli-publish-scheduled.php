<?php
/**
 * Run On Console (ROC) - CLI Scheduled Publishing Worker
 * CLI cron script to publish scheduled blog posts whose `scheduled_at` timestamp has arrived.
 *
 * Usage via CLI:
 *   php public/api/v1/cron/cli-publish-scheduled.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. CLI execution required.']);
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';

function rocCmsCronPublishScheduled(): void {
    echo "============================================================\n";
    echo "RUN ON CONSOLE (ROC) - SCHEDULED PUBLISHING WORKER\n";
    echo "============================================================\n\n";

    $pdo = getDBConnection();
    if (!$pdo) {
        echo "❌ Error: Database connection unavailable.\n";
        exit(1);
    }

    try {
        $stmtSched = $pdo->query("SELECT id, title, version FROM blogs WHERE status = 'scheduled' AND (draft_scheduled_at <= NOW() OR scheduled_at <= NOW())");
        $posts = $stmtSched->fetchAll();

        if (empty($posts)) {
            echo "✓ No scheduled posts ready for publication.\n";
            exit(0);
        }

        echo "📌 Found " . count($posts) . " scheduled post(s) ready to publish.\n\n";

        foreach ($posts as $p) {
            $id = $p['id'];
            $title = $p['title'];
            $version = (int)$p['version'];

            $pdo->beginTransaction();

            try {
                // Promote draft to published
                $stmtPub = $pdo->prepare("UPDATE blogs SET status = 'published', draft_status = 'none', published_at = NOW(), content_modified_at = NOW(), version = version + 1, updated_at = NOW() WHERE id = ? AND version = ?");
                $stmtPub->execute([$id, $version]);

                if ($stmtPub->rowCount() > 0) {
                    $newVersion = $version + 1;
                    $stmtQ = $pdo->prepare("INSERT INTO cms_cache_queue (content_type, content_id, target_version_id, action, status, created_at) VALUES ('blog', ?, ?, 'purge_and_prerender', 'pending', NOW())");
                    $stmtQ->execute([$id, $newVersion]);

                    $pdo->commit();
                    echo "  ✓ Published scheduled blog post: '{$title}' (ID: {$id}, New Version: {$newVersion})\n";
                } else {
                    $pdo->rollBack();
                    echo "  ⚠️ Version conflict publishing scheduled blog post ID: {$id}.\n";
                }
            } catch (\Throwable $e) {
                $pdo->rollBack();
                echo "❌ Error publishing blog ID {$id}: " . $e->getMessage() . "\n";
            }
        }

        echo "\n✅ Scheduled publishing check completed.\n";
        exit(0);

    } catch (\Throwable $e) {
        echo "❌ Scheduled worker error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocCmsCronPublishScheduled();
}
