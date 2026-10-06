<?php
/**
 * Run On Console (ROC) - Email Queue Status & Diagnostic Endpoint
 * Safe diagnostic tool for inspecting email queue statuses without exposing secrets.
 */

require_once dirname(__DIR__) . '/config.php';

$pdo = getDBConnection();
if (!$pdo) {
    handleDatabaseUnavailable();
}

$summary = [
    'pending' => 0,
    'processing' => 0,
    'sent' => 0,
    'failed' => 0
];

try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM email_queue GROUP BY status");
    while ($row = $stmt->fetch()) {
        $summary[$row['status']] = (int) $row['count'];
    }

    $stmt = $pdo->query("SELECT id, to_email, subject, status, attempts, error_message, created_at, sent_at FROM email_queue ORDER BY id DESC LIMIT 10");
    $recentJobs = $stmt->fetchAll();

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to query email queue status."]);
    exit();
}

http_response_code(200);
echo json_encode([
    "success" => true,
    "smtp_configured" => !empty(SMTP_PASSWORD) && !empty(SMTP_HOST),
    "smtp_host" => SMTP_HOST,
    "smtp_port" => SMTP_PORT,
    "smtp_encryption" => SMTP_ENCRYPTION,
    "smtp_user" => SMTP_USERNAME,
    "queue_summary" => $summary,
    "recent_jobs" => array_map(function($job) {
        return [
            "id" => $job['id'],
            "to" => substr($job['to_email'], 0, 3) . '***@' . explode('@', $job['to_email'])[1],
            "subject" => $job['subject'],
            "status" => $job['status'],
            "attempts" => $job['attempts'],
            "error_message" => $job['error_message'] ?: null,
            "created_at" => $job['created_at'],
            "sent_at" => $job['sent_at']
        ];
    }, $recentJobs)
]);
