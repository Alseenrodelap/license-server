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