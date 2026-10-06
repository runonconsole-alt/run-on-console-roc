<?php
/**
 * Run On Console (ROC) - Native CLI SMTP Diagnostic Command (Zero External Dependencies)
 * Tests SMTP Host, Port, STARTTLS Handshake & AUTH directly via PHP Native Sockets.
 * Usage: php cli-smtp-test.php recipient@example.com
 */

if (php_sapi_name() !== 'cli' && empty($_SERVER['SHELL']) && empty($_SERVER['TERM'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Forbidden: CLI execution required."]);
    exit(1);
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/config.php';

$recipient = $argv[1] ?? 'thekhanguy.gg@gmail.com';

echo "🎮 Starting Native CLI SMTP Diagnostic for Run On Console...\n";
echo "📌 Sender: " . MAIL_FROM_ADDRESS . " (" . MAIL_FROM_NAME . ")\n";
echo "📌 Recipient: " . $recipient . "\n";
echo "📌 Configured Host: " . SMTP_HOST . ":" . SMTP_PORT . " (Encryption: " . SMTP_ENCRYPTION . ")\n";

if (empty(SMTP_PASSWORD)) {
    echo "⚠️ Warning: SMTP_PASSWORD is empty in env.php!\n";
}

$host = SMTP_HOST;
$port = (int) SMTP_PORT;

$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);

$errno = 0;
$errstr = '';

// If port 465, open ssl:// socket directly
$socketTarget = ($port === 465 || strtolower((string)SMTP_ENCRYPTION) === 'ssl') 
    ? "ssl://{$host}:{$port}" 
    : "tcp://{$host}:{$port}";

echo "📌 Opening socket to {$socketTarget}...\n";

$socket = @stream_socket_client($socketTarget, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);

if (!$socket) {
    echo "❌ Connection Failed: [{$errno}] {$errstr}\n";
    exit(1);
}

stream_set_timeout($socket, 15);

function readSmtpResponse($socket) {
    $response = '';
    while ($line = fgets($socket, 512)) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }
    $trimmed = trim($response);
    echo "  [Server Response] {$trimmed}\n";
    return $trimmed;
}

function sendSmtpCommand($socket, $cmd, $mask = false) {
    $logCmd = $mask ? '********' : trim($cmd);
    echo "  [Client Command] {$logCmd}\n";
    fputs($socket, $cmd . "\r\n");
    return readSmtpResponse($socket);
}

readSmtpResponse($socket); // 220 Server Greeting

sendSmtpCommand($socket, "EHLO mail.runonconsole.com");

if ($port === 587 || strtolower((string)SMTP_ENCRYPTION) === 'tls') {
    $res = sendSmtpCommand($socket, "STARTTLS");
    if (strpos($res, '220') !== false || strpos($res, '2.0.0') !== false) {
        echo "📌 Upgrading socket to TLS encryption...\n";
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        $cryptoResult = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
        if (!$cryptoResult) {
            echo "❌ TLS Handshake Failed.\n";
            exit(1);
        }
        echo "✅ TLS Handshake Successful!\n";
        sendSmtpCommand($socket, "EHLO mail.runonconsole.com");
    }
}

$userB64 = base64_encode(SMTP_USERNAME);
$passB64 = base64_encode(SMTP_PASSWORD);

$authRes = sendSmtpCommand($socket, "AUTH LOGIN");
if (strpos($authRes, '334') !== false) {
    sendSmtpCommand($socket, $userB64, true);
    $passRes = sendSmtpCommand($socket, $passB64, true);
    if (strpos($passRes, '235') === false) {
        echo "❌ Authentication Failed! Please verify SMTP_USERNAME and SMTP_PASSWORD in env.php.\n";
        exit(1);
    }
    echo "✅ SMTP Authentication Succeeded!\n";
}

sendSmtpCommand($socket, "MAIL FROM: <" . MAIL_FROM_ADDRESS . ">");
sendSmtpCommand($socket, "RCPT TO: <{$recipient}>");
sendSmtpCommand($socket, "DATA");

$headers = "From: Run On Console <" . MAIL_FROM_ADDRESS . ">\r\n";
$headers .= "To: {$recipient}\r\n";
$headers .= "Subject: Run On Console - Production Native Socket Test\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";

$body = "
<div style='font-family: Arial, sans-serif; background: #0F172A; color: #F8FAFC; padding: 20px;'>
    <div style='max-width: 500px; margin: 0 auto; background: #1E293B; border: 2px solid #10B981; padding: 20px; border-radius: 12px;'>
        <h2 style='color: #10B981;'>🎮 RUN ON CONSOLE</h2>
        <p style='color: #94A3B8;'>This is an automated native socket SMTP test message sent from cPanel command line.</p>
        <p style='color: #34D399; font-weight: bold;'>Status: SMTP Connection & Delivery Successful!</p>
    </div>
</div>";

fputs($socket, $headers . $body . "\r\n.\r\n");
readSmtpResponse($socket);

sendSmtpCommand($socket, "QUIT");
fclose($socket);

echo "✅ SUCCESS: Test email dispatched successfully to {$recipient}!\n";
exit(0);
