<?php
// Set timezone to Amsterdam/Netherlands
date_default_timezone_set('Europe/Amsterdam');

class FileStorage {
    private $dataDir;
    private $encryptionKey;
    
    public function __construct() {
        $this->dataDir = __DIR__ . '/data';
        $this->encryptionKey = hash('sha256', ENCRYPTION_SECRET . 'license_storage', true);
        
        // Create data directory if it doesn't exist
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
            
            // Create .htaccess to prevent direct access
            file_put_contents($this->dataDir . '/.htaccess', "Deny from all\n");
        }
        
        // Initialize default license types and email template if they don't exist
        $this->initializeDefaults();
    }
    
    private function initializeDefaults() {
        // Default license types
        if (!$this->getLicenseTypes()) {
            $defaultTypes = [
                'trial' => 'Trial (30 dagen)',
                'standard' => 'Standard',
                'premium' => 'Premium',
                'enterprise' => 'Enterprise'
            ];
            $this->saveLicenseTypes($defaultTypes);
        }
        
        // Default email template
        if (!$this->getEmailTemplate()) {
            $defaultTemplate = [
                'subject' => 'Uw InnoDIGI Licentie - {{license_key}}',
                'body' => "Beste {{customer_name}},\n\nHierbij ontvangt u uw InnoDIGI licentie details:\n\nLicentie Sleutel: {{license_key}}\nLicentie Type: {{license_type}}\nStatus: {{status}}\nVerloopt op: {{expires_at}}\n\nMet vriendelijke groet,\nHet InnoDIGI Team"
            ];
            $this->saveEmailTemplate($defaultTemplate);
        }
        
        // Default SMTP settings
        if (!$this->getSMTPSettings()) {
            $defaultSMTP = [
                'enabled' => true,
                'host' => 'mail.innodigi.nl',
                'port' => 587,
                'security' => 'tls',
                'username' => 'noreply@innodigi.nl',
                'password' => '',
                'from_email' => 'noreply@innodigi.nl',
                'from_name' => 'InnoDIGI License System',
                'test_email' => 'support@innodigi.nl'
            ];
            $this->saveSMTPSettings($defaultSMTP);
        }
    }
    
    public function getLicense($licenseKey) {
        $filename = $this->getLicenseFilename($licenseKey);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $encryptedData = file_get_contents($filename);
        $decryptedData = $this->decrypt($encryptedData);
        
        if ($decryptedData === false) {
            return null;
        }
        
        return json_decode($decryptedData, true);
    }
    
    public function saveLicense($license) {
        $filename = $this->getLicenseFilename($license['license_key']);
        $jsonData = json_encode($license);
        $encryptedData = $this->encrypt($jsonData);
        
        return file_put_contents($filename, $encryptedData) !== false;
    }
    
    public function deleteLicense($licenseKey) {
        $filename = $this->getLicenseFilename($licenseKey);
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return false;
    }
    
    public function getAllLicenses() {
        $licenses = [];
        $files = glob($this->dataDir . '/license_*.dat');
        
        foreach ($files as $file) {
            $encryptedData = file_get_contents($file);
            $decryptedData = $this->decrypt($encryptedData);
            
            if ($decryptedData !== false) {
                $license = json_decode($decryptedData, true);
                if ($license) {
                    $licenses[] = $license;
                }
            }
        }
        
        // Sort by created_at descending
        usort($licenses, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $licenses;
    }
    
    public function getLicenseTypes() {
        $filename = $this->dataDir . '/license_types.json';
        if (file_exists($filename)) {
            $encryptedData = file_get_contents($filename);
            $decryptedData = $this->decrypt($encryptedData);
            if ($decryptedData !== false) {
                return json_decode($decryptedData, true);
            }
        }
        return null;
    }
    
    public function saveLicenseTypes($types) {
        $filename = $this->dataDir . '/license_types.json';
        $jsonData = json_encode($types);
        $encryptedData = $this->encrypt($jsonData);
        return file_put_contents($filename, $encryptedData) !== false;
    }
    
    public function getEmailTemplate() {
        $filename = $this->dataDir . '/email_template.json';
        if (file_exists($filename)) {
            $encryptedData = file_get_contents($filename);
            $decryptedData = $this->decrypt($encryptedData);
            if ($decryptedData !== false) {
                return json_decode($decryptedData, true);
            }
        }
        return null;
    }
    
    public function saveEmailTemplate($template) {
        $filename = $this->dataDir . '/email_template.json';
        $jsonData = json_encode($template);
        $encryptedData = $this->encrypt($jsonData);
        return file_put_contents($filename, $encryptedData) !== false;
    }
    
    public function getSMTPSettings() {
        $filename = $this->dataDir . '/smtp_settings.json';
        if (file_exists($filename)) {
            $encryptedData = file_get_contents($filename);
            $decryptedData = $this->decrypt($encryptedData);
            if ($decryptedData !== false) {
                return json_decode($decryptedData, true);
            }
        }
        return null;
    }
    
    public function saveSMTPSettings($settings) {
        $filename = $this->dataDir . '/smtp_settings.json';
        $jsonData = json_encode($settings);
        $encryptedData = $this->encrypt($jsonData);
        return file_put_contents($filename, $encryptedData) !== false;
    }
    
    public function updateLastChecked($licenseKey) {
        $license = $this->getLicense($licenseKey);
        
        if ($license) {
            $license['last_checked'] = date('Y-m-d H:i:s');
            $this->saveLicense($license);
        }
    }
    
    private function getLicenseFilename($licenseKey) {
        // Create a hash of the license key for the filename
        $hash = hash('sha256', $licenseKey . ENCRYPTION_SECRET);
        return $this->dataDir . '/license_' . substr($hash, 0, 16) . '.dat';
    }
    
    private function encrypt($data) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    private function decrypt($encryptedData) {
        $data = base64_decode($encryptedData);
        
        if ($data === false || strlen($data) < 16) {
            return false;
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }
}
?>