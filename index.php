<?php
// Set timezone to Amsterdam/Netherlands
date_default_timezone_set('Europe/Amsterdam');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';
require_once 'file-storage.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'verify':
        handleVerifyLicense($input);
        break;
    case 'admin':
        handleAdminAction($input);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function handleVerifyLicense($input) {
    $licenseKey = $input['license_key'] ?? '';
    
    if (empty($licenseKey)) {
        echo json_encode([
            'valid' => false,
            'message' => 'Geen licentie sleutel opgegeven'
        ]);
        return;
    }
    
    $storage = new FileStorage();
    
    // Update last checked timestamp
    $storage->updateLastChecked($licenseKey);
    
    // Check license validity
    $license = $storage->getLicense($licenseKey);
    
    if ($license && $license['status'] === 'active') {
        // Check expiration
        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            echo json_encode([
                'valid' => false,
                'message' => 'Licentie verlopen'
            ]);
            return;
        }
        
        echo json_encode([
            'valid' => true,
            'message' => 'Licentie geldig',
            'expires_at' => $license['expires_at'],
            'license_type' => $license['license_type'],
            'customer_name' => $license['customer_name'] ?? '',
            'customer_email' => $license['customer_email'] ?? ''
        ]);
    } else {
        if ($license) {
            if ($license['status'] !== 'active') {
                $message = 'Licentie gedeactiveerd';
            } else {
                $message = 'Licentie verlopen';
            }
        } else {
            $message = 'Onbekende licentie sleutel';
        }
        
        echo json_encode([
            'valid' => false,
            'message' => $message
        ]);
    }
}

function handleAdminAction($input) {
    $adminKey = $input['admin_key'] ?? '';
    
    if ($adminKey !== ADMIN_KEY) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    $subAction = $input['sub_action'] ?? '';
    
    switch ($subAction) {
        case 'list':
            listLicenses();
            break;
        case 'create':
            createLicense($input);
            break;
        case 'update':
            updateLicense($input);
            break;
        case 'delete':
            deleteLicense($input);
            break;
        case 'get_license_types':
            getLicenseTypes();
            break;
        case 'save_license_types':
            saveLicenseTypes($input);
            break;
        case 'get_email_template':
            getEmailTemplate();
            break;
        case 'save_email_template':
            saveEmailTemplate($input);
            break;
        case 'get_smtp_settings':
            getSMTPSettings();
            break;
        case 'save_smtp_settings':
            saveSMTPSettings($input);
            break;
        case 'send_license_email':
            sendLicenseEmail($input);
            break;
        case 'send_test_email':
            sendTestEmail($input);
            break;
        case 'verify_login':
            verifyLogin($input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid sub action']);
            break;
    }
}

function listLicenses() {
    $storage = new FileStorage();
    $licenses = $storage->getAllLicenses();
    
    // Remove sensitive data for admin view
    $publicLicenses = array_map(function($license) {
        return [
            'license_key' => $license['license_key'],
            'license_type' => $license['license_type'],
            'customer_name' => $license['customer_name'] ?? '',
            'customer_email' => $license['customer_email'] ?? '',
            'status' => $license['status'],
            'created_at' => $license['created_at'],
            'expires_at' => $license['expires_at'],
            'last_checked' => $license['last_checked'],
            'notes' => $license['notes']
        ];
    }, $licenses);
    
    echo json_encode(['licenses' => $publicLicenses]);
}

function getLicenseTypes() {
    $storage = new FileStorage();
    $types = $storage->getLicenseTypes();
    echo json_encode(['license_types' => $types]);
}

function saveLicenseTypes($input) {
    $types = $input['license_types'] ?? [];
    
    if (empty($types) || !is_array($types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid license types data']);
        return;
    }
    
    $storage = new FileStorage();
    if ($storage->saveLicenseTypes($types)) {
        echo json_encode(['success' => true, 'message' => 'Licentietypes opgeslagen']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Kon licentietypes niet opslaan']);
    }
}

function getEmailTemplate() {
    $storage = new FileStorage();
    $template = $storage->getEmailTemplate();
    echo json_encode(['template' => $template]);
}

function saveEmailTemplate($input) {
    $template = [
        'subject' => $input['subject'] ?? '',
        'body' => $input['body'] ?? ''
    ];
    
    $storage = new FileStorage();
    if ($storage->saveEmailTemplate($template)) {
        echo json_encode(['success' => true, 'message' => 'E-mail template opgeslagen']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Kon e-mail template niet opslaan']);
    }
}

function getSMTPSettings() {
    $storage = new FileStorage();
    $settings = $storage->getSMTPSettings();
    // Don't return the password for security
    if ($settings && isset($settings['password'])) {
        $settings['password'] = $settings['password'] ? '••••••••' : '';
    }
    echo json_encode(['settings' => $settings]);
}

function saveSMTPSettings($input) {
    $storage = new FileStorage();
    $currentSettings = $storage->getSMTPSettings() ?? [];
    
    $settings = [
        'enabled' => $input['enabled'] ?? true,
        'host' => $input['host'] ?? '',
        'port' => (int)($input['port'] ?? 587),
        'security' => $input['security'] ?? 'tls',
        'username' => $input['username'] ?? '',
        'password' => $input['password'] === '••••••••' ? $currentSettings['password'] : $input['password'] ?? '',
        'from_email' => $input['from_email'] ?? '',
        'from_name' => $input['from_name'] ?? '',
        'test_email' => $input['test_email'] ?? ''
    ];
    
    if ($storage->saveSMTPSettings($settings)) {
        echo json_encode(['success' => true, 'message' => 'SMTP instellingen opgeslagen']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Kon SMTP instellingen niet opslaan']);
    }
}

function sendLicenseEmail($input) {
    $licenseKey = $input['license_key'] ?? '';
    
    if (empty($licenseKey)) {
        http_response_code(400);
        echo json_encode(['error' => 'License key required']);
        return;
    }
    
    require_once 'license-mailer.php';
    $mailer = new LicenseMailer();
    
    if ($mailer->sendLicenseEmail($licenseKey)) {
        echo json_encode(['success' => true, 'message' => 'E-mail verzonden']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Kon e-mail niet verzenden']);
    }
}

function createLicense($input) {
    $licenseKey = $input['license_key'] ?? generateLicenseKey();
    
    // Validate license key is not empty
    if (empty(trim($licenseKey))) {
        $licenseKey = generateLicenseKey();
    }
    
    $licenseType = $input['license_type'] ?? 'standard';
    $customerName = $input['customer_name'] ?? '';
    $customerEmail = $input['customer_email'] ?? '';
    $expiresAt = $input['expires_at'] ?? null;
    
    // For trial licenses, automatically set expiry to 30 days from now
    if ($licenseType === 'trial') {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
    }
    
    $notes = $input['notes'] ?? '';
    
    $storage = new FileStorage();
    
    if ($storage->getLicense($licenseKey)) {
        http_response_code(400);
        echo json_encode(['error' => 'Licentie sleutel bestaat al']);
        return;
    }
    
    $license = [
        'license_key' => $licenseKey,
        'license_type' => $licenseType,
        'customer_name' => $customerName,
        'customer_email' => $customerEmail,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'expires_at' => $expiresAt,
        'last_checked' => null,
        'notes' => $notes
    ];
    
    if ($storage->saveLicense($license)) {
        echo json_encode([
            'success' => true,
            'license_key' => $licenseKey,
            'message' => 'Licentie aangemaakt'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Kon licentie niet opslaan']);
    }
}

function updateLicense($input) {
    $licenseKey = $input['license_key'] ?? '';
    $status = $input['status'] ?? null;
    $licenseType = $input['license_type'] ?? null;
    $customerName = $input['customer_name'] ?? null;
    $customerEmail = $input['customer_email'] ?? null;
    $expiresAt = $input['expires_at'] ?? null;
    $notes = $input['notes'] ?? null;
    
    if (empty($licenseKey)) {
        http_response_code(400);
        echo json_encode(['error' => 'License key required']);
        return;
    }
    
    $storage = new FileStorage();
    $license = $storage->getLicense($licenseKey);
    
    if (!$license) {
        http_response_code(404);
        echo json_encode(['error' => 'Licentie niet gevonden']);
        return;
    }
    
    if ($status !== null) {
        $license['status'] = $status;
    }
    
    if ($licenseType !== null) {
        $license['license_type'] = $licenseType;
        
        // For trial licenses, automatically set expiry to 30 days from now
        if ($licenseType === 'trial') {
            $license['expires_at'] = date('Y-m-d H:i:s', strtotime('+30 days'));
        }
    }
    
    if ($customerName !== null) {
        $license['customer_name'] = $customerName;
    }
    
    if ($customerEmail !== null) {
        $license['customer_email'] = $customerEmail;
    }
    
    // Handle expires_at - this parameter is always sent from frontend
    if (array_key_exists('expires_at', $input)) {
        // Only set expires_at if not a trial license (trial is handled above)
        if ($license['license_type'] !== 'trial') {
            if ($expiresAt === null || $expiresAt === '' || trim($expiresAt) === '') {
                $license['expires_at'] = null;
                error_log("Setting expires_at to null for license: " . $licenseKey);
            } else {
                $license['expires_at'] = $expiresAt;
                error_log("Setting expires_at to: " . $expiresAt . " for license: " . $licenseKey);
            }
        }
    }
    
    if ($notes !== null) {
        $license['notes'] = $notes;
    }
    
    if ($storage->saveLicense($license)) {
        error_log("License updated successfully: " . $licenseKey . " with expires_at: " . ($license['expires_at'] ?? 'null'));
        echo json_encode(['success' => true, 'message' => 'Licentie bijgewerkt']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Kon licentie niet bijwerken']);
    }
}

function deleteLicense($input) {
    $licenseKey = $input['license_key'] ?? '';
    
    // Check if license_key parameter exists (can be empty string)
    if (!array_key_exists('license_key', $input)) {
        http_response_code(400);
        echo json_encode(['error' => 'License key required']);
        return;
    }
    
    $storage = new FileStorage();
    $license = $storage->getLicense($licenseKey);
    
    if (!$license) {
        http_response_code(404);
        echo json_encode(['error' => 'Licentie niet gevonden']);
        return;
    }
    
    if ($storage->deleteLicense($licenseKey)) {
        echo json_encode(['success' => true, 'message' => 'Licentie verwijderd']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Kon licentie niet verwijderen']);
    }
}

function sendTestEmail($input) {
    $storage = new FileStorage();
    $smtpSettings = $storage->getSMTPSettings();
    
    // Use test email from settings if available, otherwise from input
    $testEmail = $input['test_email'] ?? ($smtpSettings['test_email'] ?? '');
    
    if (empty($testEmail)) {
        http_response_code(400);
        echo json_encode(['error' => 'Test email address required']);
        return;
    }
    
    // Validate email format
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address format']);
        return;
    }
    
    require_once 'license-mailer.php';
    
    if (!$smtpSettings) {
        http_response_code(500);
        echo json_encode(['error' => 'SMTP settings not configured']);
        return;
    }
    
    // Create test email content
    $subject = "Test E-mail - InnoDIGI License System";
    $body = "Dit is een test e-mail van het InnoDIGI License System.\n\n";
    $body .= "SMTP Configuratie Test:\n";
    $body .= "- Host: " . $smtpSettings['host'] . "\n";
    $body .= "- Port: " . $smtpSettings['port'] . "\n";
    $body .= "- Security: " . $smtpSettings['security'] . "\n";
    $body .= "- Username: " . $smtpSettings['username'] . "\n";
    $body .= "- From: " . $smtpSettings['from_name'] . " <" . $smtpSettings['from_email'] . ">\n\n";
    $body .= "Tijdstip: " . date('d-m-Y H:i:s') . "\n\n";
    $body .= "Als u deze e-mail ontvangt, werkt de SMTP configuratie correct.\n\n";
    $body .= "Met vriendelijke groet,\nHet InnoDIGI License System";
    
    // Send test email using dynamic SMTP settings
    $mailer = new DynamicSMTPMailerWithLogging($smtpSettings);
    
    $result = $mailer->sendMailWithLogging($testEmail, $subject, $body, false);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true, 
            'message' => 'Test e-mail verzonden naar ' . $testEmail,
            'log' => $result['log']
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => $result['error'] ?? 'Kon test e-mail niet verzenden. Controleer SMTP instellingen.',
            'log' => $result['log']
        ]);
    }
}

function generateLicenseKey() {
    return 'ID-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function verifyLogin($input) {
    // Note: admin_key verification already done in handleAdminAction
    // If we reach this point, the admin key is correct
    echo json_encode(['success' => true, 'message' => 'Login successful']);
}
?>