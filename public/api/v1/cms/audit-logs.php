<?php
/**
 * Run On Console (ROC) - CMS Administrative Audit Logs API
 * GET /api/v1/cms/audit-logs.php (Paginated audit logs)
 */

require_once __DIR__ . '/config.php';

$session = requireCmsSession('administrator'); // Admin Only
$pdo = getDBConnection();

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit();
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$stmtCount = $pdo->query("SELECT COUNT(*) FROM cms_audit_logs");
$total = (int)$stmtCount->fetchColumn();

$stmt = $pdo->prepare("SELECT a.id, a.user_id, a.action, a.target_type, a.target_id, a.details_json, a.ip_address, a.created_at, u.username FROM cms_audit_logs a LEFT JOIN cms_users u ON a.user_id = u.id ORDER BY a.id DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'total' => $total,
    'page' => $page,
    'pages' => ceil($total / $limit),
    'logs' => $logs
]);
