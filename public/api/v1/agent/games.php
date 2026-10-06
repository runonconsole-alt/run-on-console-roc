<?php
/**
 * Run On Console (ROC) - ROC Agent Verified Games Endpoint
 * GET /api/v1/agent/games
 * GET /api/v1/agent/games/{id}
 */

require_once __DIR__ . '/config-agent.php';

function rocAgentGetGames($idOrSlug = null, ?string $query = null): array {
    if (rocAgentCheckAndLogRateLimit('read_games', 60, 60)) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.'], 429);
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database connection unavailable.'], 503);
    }

    try {
        if ($idOrSlug !== null && (string)$idOrSlug !== '') {
            $idStr = (string)$idOrSlug;
            $stmt = $pdo->prepare("SELECT * FROM games WHERE (id = ? OR slug = ?) AND status = 'active' LIMIT 1");
            $stmt->execute([$idStr, $idStr]);
            $game = $stmt->fetch();

            if ($game) {
                $stmtReq = $pdo->prepare("SELECT requirement_type, cpu, gpu, ram_gb, storage_gb, operating_system, notes FROM game_requirements WHERE game_id = ?");
                $stmtReq->execute([$game['id']]);
                $reqRows = $stmtReq->fetchAll();

                $requirements = [];
                foreach ($reqRows as $r) {
                    $requirements[$r['requirement_type']] = [
                        'cpu' => $r['cpu'],
                        'gpu' => $r['gpu'],
                        'ramGb' => (int)$r['ram_gb'],
                        'storageGb' => (int)$r['storage_gb'],
                        'os' => $r['operating_system'],
                        'notes' => $r['notes']
                    ];
                }

                return [
                    'success' => true,
                    'game' => [
                        'id' => (string)$game['id'],
                        'name' => $game['name'],
                        'slug' => $game['slug'],
                        'platform' => $game['platform'],
                        'genre' => $game['genre'],
                        'image' => $game['image'],
                        'description' => $game['description'],
                        'requirements' => $requirements
                    ]
                ];
            }

            rocAgentJsonOutput(['success' => false, 'error' => 'Game not found in database.'], 404);
        }

        if ($query !== null && trim($query) !== '') {
            $cleanQuery = preg_replace('/[?!\.,:;"]+$/', '', trim($query));
            $q = '%' . $cleanQuery . '%';
            $stmt = $pdo->prepare("SELECT * FROM games WHERE status = 'active' AND (name LIKE ? OR genre LIKE ? OR platform LIKE ?) ORDER BY name ASC LIMIT 20");
            $stmt->execute([$q, $q, $q]);
        } else {
            $stmt = $pdo->query("SELECT * FROM games WHERE status = 'active' ORDER BY name ASC LIMIT 30");
        }

        $rows = $stmt->fetchAll();
        $games = array_map(function($g) {
            return [
                'id' => (string)$g['id'],
                'name' => $g['name'],
                'slug' => $g['slug'],
                'platform' => $g['platform'],
                'genre' => $g['genre'],
                'image' => $g['image']
            ];
        }, $rows ?: []);

        return ['success' => true, 'games' => $games];

    } catch (\Throwable $e) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database query failure.'], 500);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $id = $_GET['id'] ?? null;
    $q = $_GET['q'] ?? null;
    rocAgentJsonOutput(rocAgentGetGames($id, $q));
}
