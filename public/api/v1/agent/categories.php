<?php
/**
 * Run On Console (ROC) - ROC Agent Categories Endpoint
 * GET /api/v1/agent/categories
 */

require_once __DIR__ . '/config-agent.php';

function rocAgentGetCategories(): array {
    if (rocAgentCheckAndLogRateLimit('read_categories', 60, 60)) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.'], 429);
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database connection unavailable.'], 503);
    }

    $productCategories = [];
    $gameCategories = [];

    try {
        $stmtProd = $pdo->query("SELECT category as name, COUNT(*) as count FROM products WHERE category IS NOT NULL AND TRIM(category) != '' GROUP BY category ORDER BY count DESC");
        $rowsProd = $stmtProd->fetchAll();
        if ($rowsProd) {
            foreach ($rowsProd as $r) {
                $productCategories[] = [
                    'id' => strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($r['name']))),
                    'name' => trim($r['name']),
                    'count' => (int)$r['count']
                ];
            }
        }

        $stmtGame = $pdo->query("SELECT genre as name, COUNT(*) as count FROM games WHERE genre IS NOT NULL AND TRIM(genre) != '' GROUP BY genre ORDER BY count DESC");
        $rowsGame = $stmtGame->fetchAll();
        if ($rowsGame) {
            foreach ($rowsGame as $r) {
                $gameCategories[] = [
                    'id' => strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($r['name']))),
                    'name' => trim($r['name']),
                    'count' => (int)$r['count']
                ];
            }
        }

        return [
            'success' => true,
            'productCategories' => $productCategories,
            'gameCategories' => $gameCategories
        ];
    } catch (\Throwable $e) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database query failure.'], 500);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocAgentJsonOutput(rocAgentGetCategories());
}
