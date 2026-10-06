<?php
/**
 * Run On Console (ROC) - ROC Agent Write for Us Submission Endpoint
 * POST /api/v1/agent/write-for-us
 */

require_once __DIR__ . '/config-agent.php';

function rocAgentHandleWriteForUs(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        rocAgentJsonOutput(['success' => false, 'error' => 'POST method required.'], 405);
    }

    if (!rocAgentValidateCsrf()) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Invalid or missing CSRF token.'], 403);
    }

    if (rocAgentCheckAndLogRateLimit('write_for_us', 10, 300)) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Too many proposal submissions. Please try again later.'], 429);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $name = rocAgentSanitizeString($input['name'] ?? '', 120);
    $email = rocAgentSanitizeString($input['email'] ?? '', 150);
    $topic = rocAgentSanitizeString($input['proposedTopic'] ?? $input['proposed_topic'] ?? '', 200);
    $pitch = rocAgentSanitizeString($input['shortPitch'] ?? $input['short_pitch'] ?? '', 2000);
    $experience = rocAgentSanitizeString($input['experience'] ?? 'Gaming Hardware Writer', 1000);
    $portfolioUrl = rocAgentSanitizeString($input['portfolioUrl'] ?? $input['portfolio_url'] ?? '', 300);

    if ($name === '' || $email === '' || $topic === '' || $pitch === '') {
        rocAgentJsonOutput(['success' => false, 'error' => 'Please fill in your name, email, proposed topic, and short pitch.'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Please provide a valid email address.'], 400);
    }

    $pdo = getDBConnection();
    if (!$pdo) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database connection unavailable.'], 503);
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO roc_write_for_us_submissions (name, email, proposed_topic, short_pitch, experience, portfolio_url, status) VALUES (?, ?, ?, ?, ?, ?, 'pending_review')");
        $stmt->execute([$name, $email, $topic, $pitch, $experience, $portfolioUrl]);
        $id = (string)$pdo->lastInsertId();

        return [
            'success' => true,
            'message' => 'Thank you! Your article proposal has been submitted to the Run On Console editorial team for admin review.',
            'submissionId' => $id
        ];
    } catch (\Throwable $e) {
        rocAgentJsonOutput(['success' => false, 'error' => 'Database insert failure.'], 500);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocAgentJsonOutput(rocAgentHandleWriteForUs());
}
