<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
require_once 'smtp-mailer.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || $input['action'] !== 'report_tampering') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Extract tampering report data
$licenseKey = $input['license_key'] ?? 'unknown';
$adminEmail = $input['admin_email'] ?? 'unknown';
$triggerType = $input['trigger_type'] ?? 'unknown';
$details = $input['details'] ?? 'No details provided';
$timestamp = $input['timestamp'] ?? date('c');
$userAgent = $input['user_agent'] ?? 'unknown';
$url = $input['url'] ?? 'unknown';

// Create email content
$subject = "🚨 InnoDIGI Tampering Detected - License: " . substr($licenseKey, 0, 16) . "...";

$emailBody = "
INNODIGI TAMPERING DETECTION ALERT
===================================

Een mogelijke tampering poging is gedetecteerd in een InnoDIGI applicatie.

DETAILS:
--------
Licentie Sleutel: {$licenseKey}
Admin E-mail: {$adminEmail}
Trigger Type: {$triggerType}
Details: {$details}
Tijdstip: {$timestamp}
URL: {$url}
User Agent: {$userAgent}

ACTIE VEREIST:
--------------
1. Controleer de geldigheid van de licentie
2. Neem contact op met de admin ({$adminEmail})
3. Onderzoek mogelijke ongeautoriseerde wijzigingen

Dit is een automatisch gegenereerd bericht van het InnoDIGI Licentie Systeem.
";

// Send email using SMTP or PHP mail
$mailer = new SMTPMailer();
$emailSent = $mailer->sendMail(SMTP_TO_EMAIL, $subject, $emailBody, false);

// Log the tampering attempt (optional - for debugging)
$logEntry = [
    'timestamp' => $timestamp,
    'license_key' => $licenseKey,
    'admin_email' => $adminEmail,
    'trigger_type' => $triggerType,
    'details' => $details,
    'user_agent' => $userAgent,
    'url' => $url,
    'email_sent' => $emailSent
];

// Create logs directory if it doesn't exist
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0700, true);
    // Protect logs directory
    file_put_contents($logsDir . '/.htaccess', "Deny from all\n");
}

// Write to log file (one file per day)
$logFile = $logsDir . '/tampering-' . date('Y-m-d') . '.log';
file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Tampering report received',
    'email_sent' => $emailSent
]);
?>