<?php
// Settings Manager for InnoDIGI License Server
// This file handles advanced settings management for the admin interface

require_once 'file-storage.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$adminKey = $input['admin_key'] ?? '';

// Verify admin key
if ($adminKey !== ADMIN_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$storage = new FileStorage();

switch ($action) {
    case 'get_all_settings':
        getAllSettings($storage);
        break;
    case 'export_data':
        exportData($storage);
        break;
    case 'import_data':
        importData($storage, $input);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function getAllSettings($storage) {
    $settings = [
        'license_types' => $storage->getLicenseTypes(),
        'email_template' => $storage->getEmailTemplate(),
        'smtp_settings' => $storage->getSMTPSettings()
    ];
    
    // Hide password in SMTP settings
    if (isset($settings['smtp_settings']['password'])) {
        $settings['smtp_settings']['password'] = $settings['smtp_settings']['password'] ? '••••••••' : '';
    }
    
    echo json_encode(['settings' => $settings]);
}

function exportData($storage) {
    $licenses = $storage->getAllLicenses();
    $settings = [
        'license_types' => $storage->getLicenseTypes(),
        'email_template' => $storage->getEmailTemplate(),
        'smtp_settings' => $storage->getSMTPSettings()
    ];
    
    // Hide password in export
    if (isset($settings['smtp_settings']['password'])) {
        $settings['smtp_settings']['password'] = '';
    }
    
    $exportData = [
        'export_date' => date('Y-m-d H:i:s'),
        'system' => 'InnoDIGI License Server',
        'version' => '2.0',
        'licenses' => $licenses,
        'settings' => $settings
    ];
    
    echo json_encode(['export_data' => $exportData]);
}

function importData($storage, $input) {
    $importData = $input['import_data'] ?? [];
    
    if (empty($importData)) {
        http_response_code(400);
        echo json_encode(['error' => 'No import data provided']);
        return;
    }
    
    $results = [];
    
    // Import licenses
    if (isset($importData['licenses']) && is_array($importData['licenses'])) {
        $licenseCount = 0;
        foreach ($importData['licenses'] as $license) {
            if ($storage->saveLicense($license)) {
                $licenseCount++;
            }
        }
        $results['licenses'] = "$licenseCount licenties geïmporteerd";
    }
    
    // Import settings
    if (isset($importData['settings'])) {
        $settings = $importData['settings'];
        
        if (isset($settings['license_types'])) {
            $storage->saveLicenseTypes($settings['license_types']);
            $results['license_types'] = 'Licentietypes geïmporteerd';
        }
        
        if (isset($settings['email_template'])) {
            $storage->saveEmailTemplate($settings['email_template']);
            $results['email_template'] = 'E-mail template geïmporteerd';
        }
        
        if (isset($settings['smtp_settings'])) {
            // Don't import empty password
            if (empty($settings['smtp_settings']['password'])) {
                $currentSMTP = $storage->getSMTPSettings();
                if ($currentSMTP && isset($currentSMTP['password'])) {
                    $settings['smtp_settings']['password'] = $currentSMTP['password'];
                }
            }
            $storage->saveSMTPSettings($settings['smtp_settings']);
            $results['smtp_settings'] = 'SMTP instellingen geïmporteerd';
        }
    }
    
    echo json_encode(['success' => true, 'results' => $results]);
}
?>