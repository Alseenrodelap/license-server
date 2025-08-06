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
            'license_type' => $license['license_type']
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

function generateLicenseKey() {
    return 'PB-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
}
?>