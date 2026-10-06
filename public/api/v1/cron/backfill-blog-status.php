<?php
/**
 * Run On Console (ROC) - Standalone Optional Blog Publication Status Backfill CLI Command
 *
 * Displays proposed public blog mapping for owner review before applying changes.
 * Does NOT apply mapping automatically unless invoked with explicit --confirm parameter.
 * Independently rerunnable.
 *
 * Usage for Preview:
 *   php api/v1/cron/backfill-blog-status.php
 *
 * Usage for Execution:
 *   php api/v1/cron/backfill-blog-status.php --confirm
 */

if (php_sapi_name() !== 'cli' && empty($_SERVER['ROC_CLI_RUN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. CLI execution required.']);
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';

function rocAgentBackfillBlogStatus(): void {
    $args = $_SERVER['argv'] ?? [];
    $isConfirmed = in_array('--confirm', $args, true);

    echo "============================================================\n";
    echo "RUN ON CONSOLE (ROC) - BLOG PUBLICATION STATUS BACKFILL\n";
    echo "============================================================\n\n";

    $pdo = getDBConnection();
    if (!$pdo) {
        echo "❌ Error: Database connection unavailable.\n";
        exit(1);
    }

    // 1. Strict Database Guard: Must be runoncon_rocstage
    $dbStmt = $pdo->query("SELECT DATABASE()");
    $currentDb = $dbStmt ? $dbStmt->fetchColumn() : null;

    if ($currentDb !== 'runoncon_rocstage') {
        echo "❌ ABORTED: Script is strictly restricted to staging database 'runoncon_rocstage'.\n";
        exit(1);
    }
    echo "✓ Verified Database Isolation: Connected to '{$currentDb}'\n";

    if (!$isConfirmed) {
        echo "⚠️ PREVIEW MODE ONLY (No database writes performed).\n";
        echo "   To apply publication backfill, run with explicit approval flag:\n";
        echo "   php api/v1/cron/backfill-blog-status.php --confirm\n\n";
    } else {
        echo "🚀 CONFIRMED WRITE MODE ENABLED (--confirm flag provided).\n\n";
    }

    // Proposed Public Blog Mapping Candidates matching exact seeded IDs and canonical slugs
    $candidateList = [
        ['id' => 'blog-1', 'slug' => 'call-of-duty-black-ops-6-everything-we-know-so-far'],
        ['id' => 'blog-2', 'slug' => 'elden-ring-shadow-of-the-erdtree-full-hardware-boss-guide'],
        ['id' => 'blog-3', 'slug' => 'spider-man-2-on-ps5-performance-ray-tracing-masterclass'],
        ['id' => 'blog-4', 'slug' => 'best-gaming-laptops-under-1000-in-2024-buyers-guide']
    ];

    echo "🔍 QUERYING ACTUAL DATABASE FOR BLOG PUBLICATION BACKFILL MATCHES:\n";
    echo "------------------------------------------------------------\n";

    $matchedDbRecords = [];
    $missingCandidates = [];
    $conflictsFound = [];

    foreach ($candidateList as $cand) {
        $targetId = $cand['id'];
        $targetSlug = $cand['slug'];

        $stmtId = $pdo->prepare("SELECT id, slug, title, status FROM blogs WHERE id = ?");
        $stmtId->execute([$targetId]);
        $rowById = $stmtId->fetch(PDO::FETCH_ASSOC);

        $stmtSlug = $pdo->prepare("SELECT id, slug, title, status FROM blogs WHERE slug = ?");
        $stmtSlug->execute([$targetSlug]);
        $rowBySlug = $stmtSlug->fetch(PDO::FETCH_ASSOC);

        if (!$rowById && !$rowBySlug) {
            $missingCandidates[] = "Candidate (ID: {$targetId}, Slug: {$targetSlug}) -> NOT FOUND IN DATABASE";
            continue;
        }

        if ($rowById && $rowBySlug && $rowById['id'] !== $rowBySlug['id']) {
            $conflictsFound[] = "CRITICAL CONFLICT: Candidate (ID: {$targetId}, Slug: {$targetSlug}) matched TWO DIFFERENT RECORDS in DB!";
            continue;
        }

        if ($rowById && $rowBySlug && $rowById['id'] === $rowBySlug['id'] && $rowById['slug'] === $targetSlug) {
            $matchedDbRecords[$rowById['id']] = $rowById;
            echo "  ✓ MATCHED: [ID: {$rowById['id']}] Title: '{$rowById['title']}' | Current Status: '{$rowById['status']}' -> Proposed Target: 'published'\n";
        }
    }

    echo "------------------------------------------------------------\n";
    echo "Found " . count($matchedDbRecords) . " verified 1-to-1 match(es) out of " . count($candidateList) . " candidate(s).\n\n";

    if (!empty($missingCandidates)) {
        echo "⚠️ Missing Candidates:\n";
        foreach ($missingCandidates as $m) echo "  - {$m}\n";
        echo "\n";
    }

    if (!empty($conflictsFound)) {
        echo "❌ Conflicts Found:\n";
        foreach ($conflictsFound as $c) echo "  - {$c}\n";
        echo "\n";
        exit(1);
    }

    if (!$isConfirmed) {
        echo "✓ Preview completed successfully. No changes made to database.\n";
        return;
    }

    // Execution wrapped in PDO Transaction
    try {
        $pdo->beginTransaction();

        $updateStmt = $pdo->prepare("UPDATE blogs SET status = 'published' WHERE id = ? AND slug = ?");
        $updatedCount = 0;

        foreach ($matchedDbRecords as $record) {
            $updateStmt->execute([$record['id'], $record['slug']]);
            $updatedCount++;
        }

        $pdo->commit();
        echo "============================================================\n";
        echo "✅ SUCCESS: Blog Publication Backfill Completed Successfully.\n";
        echo "   - Blogs Published: {$updatedCount}\n";
        echo "============================================================\n";

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "❌ CRITICAL FAILURE during blog publication update: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocAgentBackfillBlogStatus();
}
