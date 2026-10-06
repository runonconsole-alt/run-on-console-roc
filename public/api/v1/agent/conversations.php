<?php
/**
 * Run On Console (ROC) - ROC Agent Conversation History Endpoint
 * GET  /api/v1/agent/conversations (Read only. Does NOT create conversation rows)
 * POST /api/v1/agent/conversations (Validated write. Creates conversation rows)
 *
 * Enforces strict user & guest session isolation:
 * - Guests access ONLY conversations tied to their secure $_SESSION['roc_guest_agent_session_id'].
 * - Logged-in users access ONLY conversations tied to their $_SESSION['user_id'].
 * - CSRF verification, whitelist metadata sanitization, and atomic rate-limiting enforced.
 */

require_once __DIR__ . '/config-agent.php';

function rocAgentSanitizeComponentResults($rawCompResults): ?array {
    if (empty($rawCompResults) || !is_array($rawCompResults)) {
        return null;
    }
    $sanitized = [];
    foreach (array_slice($rawCompResults, 0, 20) as $key => $item) {
        if (!is_array($item)) {
            $compName = is_string($key) ? $key : 'Component';
            $statusStr = (string)$item;
            $sanitized[] = [
                'name' => rocAgentSanitizeString($compName, 100),
                'status' => rocAgentSanitizeString($statusStr, 50)
            ];
            continue;
        }

        // Allow ONLY component name and normalized status.
        // Never store un-whitelisted fields, CPU, GPU, RAM, OS, CSRF or form values
        $name = rocAgentSanitizeString($item['name'] ?? $item['component'] ?? $item['componentName'] ?? (is_string($key) ? $key : ''), 100);
        $status = rocAgentSanitizeString($item['status'] ?? $item['verdict'] ?? (!empty($item['pass']) || !empty($item['met']) ? 'Met' : 'Unmet'), 50);

        $cleanItem = [
            'name' => $name,
            'status' => $status
        ];

        if (isset($item['componentResults']) && is_array($item['componentResults'])) {
            $cleanItem['componentResults'] = rocAgentSanitizeComponentResults($item['componentResults']);
        }

        $sanitized[] = $cleanItem;
    }
    return !empty($sanitized) ? $sanitized : null;
}

function rocAgentValidateAndSanitizeMetadata($rawMetadata): ?array {
    if (empty($rawMetadata) || !is_array($rawMetadata)) {
        return null;
    }

    $encoded = json_encode($rawMetadata);
    if ($encoded === false || strlen($encoded) > 32768) {
        return null;
    }

    $type = (string)($rawMetadata['type'] ?? '');
    $allowedTypes = ['products', 'games', 'blogs', 'categories', 'search_results', 'compat_result', 'proposal_success'];
    if (!in_array($type, $allowedTypes, true)) {
        return null;
    }

    $clean = ['type' => $type];
    if (!empty($rawMetadata['showActions'])) {
        $clean['showActions'] = true;
    }

    if ($type === 'products' && isset($rawMetadata['products']) && is_array($rawMetadata['products'])) {
        $clean['products'] = array_map(function($p) {
            return [
                'id' => (string)($p['id'] ?? ''),
                'name' => rocAgentSanitizeString($p['name'] ?? $p['title'] ?? '', 150),
                'price' => rocAgentSanitizeString($p['price'] ?? '', 40),
                'rating' => isset($p['rating']) ? (float)$p['rating'] : null,
                'image' => rocAgentSanitizeString($p['image'] ?? '', 255),
                'slug' => rocAgentSanitizeString($p['slug'] ?? '', 150),
                'category' => rocAgentSanitizeString($p['category'] ?? '', 100)
            ];
        }, array_slice($rawMetadata['products'], 0, 10));
    }

    if ($type === 'games' && isset($rawMetadata['games']) && is_array($rawMetadata['games'])) {
        $clean['games'] = array_map(function($g) {
            return [
                'id' => (string)($g['id'] ?? ''),
                'name' => rocAgentSanitizeString($g['name'] ?? $g['gameTitle'] ?? '', 150),
                'platform' => rocAgentSanitizeString($g['platform'] ?? '', 100),
                'genre' => rocAgentSanitizeString($g['genre'] ?? '', 100),
                'slug' => rocAgentSanitizeString($g['slug'] ?? '', 150),
                'minSpecs' => rocAgentSanitizeString($g['minSpecs'] ?? '', 500),
                'recommendedSpecs' => rocAgentSanitizeString($g['recommendedSpecs'] ?? '', 500)
            ];
        }, array_slice($rawMetadata['games'], 0, 10));
    }

    if ($type === 'blogs' && isset($rawMetadata['blogs']) && is_array($rawMetadata['blogs'])) {
        $clean['blogs'] = array_map(function($b) {
            return [
                'id' => (string)($b['id'] ?? ''),
                'title' => rocAgentSanitizeString($b['title'] ?? '', 200),
                'category' => rocAgentSanitizeString($b['category'] ?? '', 100),
                'slug' => rocAgentSanitizeString($b['slug'] ?? '', 150),
                'excerpt' => rocAgentSanitizeString($b['excerpt'] ?? '', 400),
                'readUrl' => rocAgentSanitizeString($b['readUrl'] ?? '', 255)
            ];
        }, array_slice($rawMetadata['blogs'], 0, 10));
    }

    if ($type === 'categories') {
        if (isset($rawMetadata['productCategories']) && is_array($rawMetadata['productCategories'])) {
            $clean['productCategories'] = array_map(function($c) {
                return [
                    'id' => (string)($c['id'] ?? ''),
                    'name' => rocAgentSanitizeString($c['name'] ?? '', 100),
                    'count' => (int)($c['count'] ?? 0)
                ];
            }, array_slice($rawMetadata['productCategories'], 0, 20));
        }
        if (isset($rawMetadata['gameCategories']) && is_array($rawMetadata['gameCategories'])) {
            $clean['gameCategories'] = array_map(function($g) {
                return [
                    'id' => (string)($g['id'] ?? ''),
                    'name' => rocAgentSanitizeString($g['name'] ?? '', 100),
                    'count' => (int)($g['count'] ?? 0)
                ];
            }, array_slice($rawMetadata['gameCategories'], 0, 20));
        }
    }

    if ($type === 'search_results') {
        if (isset($rawMetadata['matchedProducts']) && is_array($rawMetadata['matchedProducts'])) {
            $clean['matchedProducts'] = array_map(function($p) {
                return [
                    'id' => (string)($p['id'] ?? ''),
                    'name' => rocAgentSanitizeString($p['name'] ?? $p['title'] ?? '', 150),
                    'price' => rocAgentSanitizeString($p['price'] ?? '', 40),
                    'rating' => isset($p['rating']) ? (float)$p['rating'] : null,
                    'image' => rocAgentSanitizeString($p['image'] ?? '', 255),
                    'slug' => rocAgentSanitizeString($p['slug'] ?? '', 150),
                    'category' => rocAgentSanitizeString($p['category'] ?? '', 100)
                ];
            }, array_slice($rawMetadata['matchedProducts'], 0, 10));
        }
        if (isset($rawMetadata['matchedGames']) && is_array($rawMetadata['matchedGames'])) {
            $clean['matchedGames'] = array_map(function($g) {
                return [
                    'id' => (string)($g['id'] ?? ''),
                    'name' => rocAgentSanitizeString($g['name'] ?? $g['gameTitle'] ?? '', 150),
                    'platform' => rocAgentSanitizeString($g['platform'] ?? '', 100),
                    'genre' => rocAgentSanitizeString($g['genre'] ?? '', 100),
                    'slug' => rocAgentSanitizeString($g['slug'] ?? '', 150)
                ];
            }, array_slice($rawMetadata['matchedGames'], 0, 10));
        }
        if (isset($rawMetadata['matchedBlogs']) && is_array($rawMetadata['matchedBlogs'])) {
            $clean['matchedBlogs'] = array_map(function($b) {
                return [
                    'id' => (string)($b['id'] ?? ''),
                    'title' => rocAgentSanitizeString($b['title'] ?? '', 200),
                    'category' => rocAgentSanitizeString($b['category'] ?? '', 100),
                    'slug' => rocAgentSanitizeString($b['slug'] ?? '', 150),
                    'excerpt' => rocAgentSanitizeString($b['excerpt'] ?? '', 400),
                    'readUrl' => rocAgentSanitizeString($b['readUrl'] ?? '', 255)
                ];
            }, array_slice($rawMetadata['matchedBlogs'], 0, 10));
        }
    }

    if ($type === 'compat_result' && isset($rawMetadata['result']) && is_array($rawMetadata['result'])) {
        $res = $rawMetadata['result'];
        $cleanComp = isset($res['componentResults']) ? rocAgentSanitizeComponentResults($res['componentResults']) : null;
        $clean['result'] = [
            'overallResult' => rocAgentSanitizeString($res['overallResult'] ?? $res['title'] ?? $res['verdict'] ?? '', 200),
            'title' => rocAgentSanitizeString($res['title'] ?? '', 200),
            'verdict' => rocAgentSanitizeString($res['verdict'] ?? '', 100),
            'success' => !empty($res['success']),
            'componentResults' => $cleanComp
        ];
    }

    return $clean;
}

function rocAgentHandleConversations(): array {
    $userId = rocAgentGetUserId();
    $guestSessionId = ($userId === null) ? rocAgentGetGuestSessionId() : null;
    $pdo = getDBConnection();
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if (!$pdo) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database connection unavailable.'], 503);
    }

    try {
        if ($method === 'GET') {
            if (rocAgentCheckAndLogRateLimit('read_conversations', 60, 60)) {
                rocAgentJsonOutput(['success' => false, 'error' => 'Rate limit exceeded. Please try again later.'], 429);
            }

            $action = $_GET['action'] ?? '';
            $requestedConvId = $_GET['conversation_id'] ?? $_GET['id'] ?? null;

            if ($action === 'list') {
                if ($userId === null) {
                    return ['success' => true, 'conversations' => []];
                }
                $stmt = $pdo->prepare("
                    SELECT c.id, c.updated_at, 
                           (SELECT message FROM roc_messages m WHERE m.conversation_id = c.id ORDER BY id ASC LIMIT 1) as title
                    FROM roc_conversations c 
                    WHERE c.user_id = ? 
                    ORDER BY c.updated_at DESC 
                    LIMIT 50
                ");
                $stmt->execute([$userId]);
                $rows = $stmt->fetchAll() ?: [];
                $conversations = array_map(function($c) {
                    $rawTitle = trim((string)($c['title'] ?? ''));
                    $displayTitle = mb_strlen($rawTitle) > 40 ? mb_substr($rawTitle, 0, 40) . '...' : $rawTitle;
                    return [
                        'id' => (string)$c['id'],
                        'title' => $displayTitle !== '' ? $displayTitle : 'New Conversation',
                        'updatedAt' => $c['updated_at']
                    ];
                }, $rows);

                return [
                    'success' => true,
                    'conversations' => $conversations
                ];
            }

            if ($requestedConvId !== null) {
                if ($userId !== null) {
                    $stmt = $pdo->prepare("SELECT id FROM roc_conversations WHERE id = ? AND user_id = ?");
                    $stmt->execute([$requestedConvId, $userId]);
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM roc_conversations WHERE id = ? AND guest_session_id = ?");
                    $stmt->execute([$requestedConvId, $guestSessionId]);
                }
                $conv = $stmt->fetch();
            } else {
                if ($userId !== null) {
                    $stmt = $pdo->prepare("SELECT id FROM roc_conversations WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$userId]);
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM roc_conversations WHERE guest_session_id = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$guestSessionId]);
                }
                $conv = $stmt->fetch();
            }

            if (!$conv) {
                return [
                    'success' => true,
                    'conversationId' => null,
                    'messages' => []
                ];
            }

            $convId = (string)$conv['id'];

            // Query metadata_json directly (requires migration, no silent fallback to text-only)
            $selectSql = "SELECT id, role, message, intent, metadata_json, created_at FROM roc_messages WHERE conversation_id = ? ORDER BY id ASC LIMIT 100";

            $stmtMsgs = $pdo->prepare($selectSql);
            $stmtMsgs->execute([$convId]);
            $rows = $stmtMsgs->fetchAll() ?: [];

            $messages = array_map(function($m) {
                $item = [
                    'id' => (string)$m['id'],
                    'role' => $m['role'],
                    'message' => $m['message'],
                    'intent' => $m['intent'],
                    'createdAt' => $m['created_at']
                ];
                if (!empty($m['metadata_json'])) {
                    $item['metadata'] = json_decode($m['metadata_json'], true);
                }
                return $item;
            }, $rows);

            return [
                'success' => true,
                'conversationId' => $convId,
                'messages' => $messages
            ];
        }

        if ($method === 'POST') {
            if (!rocAgentValidateCsrf()) {
                rocAgentJsonOutput(['success' => false, 'error' => 'Invalid or missing CSRF token.'], 403);
            }

            if (rocAgentCheckAndLogRateLimit('conversation_post', 30, 60)) {
                rocAgentJsonOutput(['success' => false, 'error' => 'Too many conversation messages. Please try again later.'], 429);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $action = $input['action'] ?? '';

            if ($action === 'new' || $action === 'create') {
                if ($userId !== null) {
                    $pdo->prepare("INSERT INTO roc_conversations (user_id) VALUES (?)")->execute([$userId]);
                } else {
                    $pdo->prepare("INSERT INTO roc_conversations (guest_session_id) VALUES (?)")->execute([$guestSessionId]);
                }
                $newConvId = (string)$pdo->lastInsertId();

                return [
                    'success' => true,
                    'conversationId' => $newConvId,
                    'messages' => []
                ];
            }

            $role = ($input['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $messageText = rocAgentSanitizeString($input['message'] ?? '', 4000);
            $intent = rocAgentSanitizeString($input['intent'] ?? '', 64);
            $requestedConvId = $input['conversationId'] ?? $input['conversation_id'] ?? null;
            $rawMetadata = $input['metadata'] ?? null;
            $cleanMetadata = rocAgentValidateAndSanitizeMetadata($rawMetadata);
            $metadataJson = !empty($cleanMetadata) ? json_encode($cleanMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

            if ($messageText === '') {
                rocAgentJsonOutput(['success' => false, 'error' => 'Message text cannot be empty.'], 400);
            }

            $convId = null;
            if ($requestedConvId !== null) {
                if ($userId !== null) {
                    $stmtCheck = $pdo->prepare("SELECT id FROM roc_conversations WHERE id = ? AND user_id = ?");
                    $stmtCheck->execute([$requestedConvId, $userId]);
                } else {
                    $stmtCheck = $pdo->prepare("SELECT id FROM roc_conversations WHERE id = ? AND guest_session_id = ?");
                    $stmtCheck->execute([$requestedConvId, $guestSessionId]);
                }
                if ($row = $stmtCheck->fetch()) {
                    $convId = (string)$row['id'];
                }
            }

            if ($convId === null) {
                if ($userId !== null) {
                    $stmt = $pdo->prepare("SELECT id FROM roc_conversations WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$userId]);
                    $conv = $stmt->fetch();
                    if (!$conv) {
                        $pdo->prepare("INSERT INTO roc_conversations (user_id) VALUES (?)")->execute([$userId]);
                        $convId = (string)$pdo->lastInsertId();
                    } else {
                        $convId = (string)$conv['id'];
                    }
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM roc_conversations WHERE guest_session_id = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$guestSessionId]);
                    $conv = $stmt->fetch();
                    if (!$conv) {
                        $pdo->prepare("INSERT INTO roc_conversations (guest_session_id) VALUES (?)")->execute([$guestSessionId]);
                        $convId = (string)$pdo->lastInsertId();
                    } else {
                        $convId = (string)$conv['id'];
                    }
                }
            }

            // Directly insert metadata_json (requires migration, no silent fallback)
            $stmtMsg = $pdo->prepare("INSERT INTO roc_messages (conversation_id, role, message, intent, metadata_json) VALUES (?, ?, ?, ?, ?)");
            $stmtMsg->execute([$convId, $role, $messageText, $intent, $metadataJson]);

            $msgId = (string)$pdo->lastInsertId();

            $pdo->prepare("UPDATE roc_conversations SET updated_at = NOW() WHERE id = ?")->execute([$convId]);

            return [
                'success' => true,
                'messageId' => $msgId,
                'conversationId' => $convId
            ];
        }
    } catch (\Throwable $e) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database query failure.'], 500);
    }

    rocAgentJsonOutput(['success' => false, 'error' => 'Method not allowed.'], 405);
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocAgentJsonOutput(rocAgentHandleConversations());
}
