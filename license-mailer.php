<?php
require_once 'file-storage.php';

class LicenseMailer {
    private $storage;
    
    public function __construct() {
        $this->storage = new FileStorage();
    }
    
    public function sendLicenseEmail($licenseKey) {
        // Get license data
        $license = $this->storage->getLicense($licenseKey);
        if (!$license) {
            return false;
        }
        
        // Check if customer has email
        $customerEmail = $license['customer_email'] ?? '';
        if (empty($customerEmail)) {
            return false;
        }
        
        // Get email template
        $template = $this->storage->getEmailTemplate();
        if (!$template) {
            return false;
        }
        
        // Get SMTP settings
        $smtpSettings = $this->storage->getSMTPSettings();
        if (!$smtpSettings) {
            return false;
        }
        
        // Get license types for display name
        $licenseTypes = $this->storage->getLicenseTypes() ?? [];
        $licenseTypeDisplay = $licenseTypes[$license['license_type']] ?? $license['license_type'];
        
        // Prepare template variables
        $variables = [
            '{{customer_name}}' => $license['customer_name'] ?? 'Geachte klant',
            '{{license_key}}' => $license['license_key'],
            '{{license_type}}' => $licenseTypeDisplay,
            '{{status}}' => $license['status'] === 'active' ? 'Actief' : 'Inactief',
            '{{expires_at}}' => $license['expires_at'] ? date('d-m-Y H:i', strtotime($license['expires_at'])) : 'Geen',
            '{{created_at}}' => date('d-m-Y H:i', strtotime($license['created_at'])),
            '{{notes}}' => $license['notes'] ?? ''
        ];
        
        // Replace template variables
        $subject = str_replace(array_keys($variables), array_values($variables), $template['subject']);
        $body = str_replace(array_keys($variables), array_values($variables), $template['body']);
        
        // Send email using dynamic SMTP settings
        $mailer = new DynamicSMTPMailer($smtpSettings);
        return $mailer->sendMail($customerEmail, $subject, $body, false);
    }
}

class DynamicSMTPMailer {
    private $settings;
    
    public function __construct($smtpSettings) {
        $this->settings = $smtpSettings;
    }
    
    public function sendMail($to, $subject, $body, $isHtml = false) {
        if (!$this->settings['enabled']) {
            return $this->sendWithPHPMail($to, $subject, $body, $isHtml);
        }
        
        try {
            $socket = $this->connectToSMTP();
            
            if (!$socket) {
                throw new Exception('Could not connect to SMTP server');
            }
            
            $this->sendSMTPCommand($socket, '', '220');
            $this->sendSMTPCommand($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '250');
            
            // Start TLS if required
            if ($this->settings['security'] === 'tls') {
                $this->sendSMTPCommand($socket, 'STARTTLS', '220');
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendSMTPCommand($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '250');
            }
            
            // Authentication
            if ($this->settings['username'] && $this->settings['password']) {
                $this->sendSMTPCommand($socket, 'AUTH LOGIN', '334');
                $this->sendSMTPCommand($socket, base64_encode($this->settings['username']), '334');
                $this->sendSMTPCommand($socket, base64_encode($this->settings['password']), '235');
            }
            
            // Send email
            $this->sendSMTPCommand($socket, 'MAIL FROM: <' . $this->settings['from_email'] . '>', '250');
            $this->sendSMTPCommand($socket, 'RCPT TO: <' . $to . '>', '250');
            $this->sendSMTPCommand($socket, 'DATA', '354');
            
            $headers = $this->buildHeaders($to, $subject, $isHtml);
            $message = $headers . "\r\n" . $body . "\r\n.";
            
            $this->sendSMTPCommand($socket, $message, '250');
            $this->sendSMTPCommand($socket, 'QUIT', '221');
            
            fclose($socket);
            return true;
            
        } catch (Exception $e) {
            error_log('SMTP Error: ' . $e->getMessage());
            // Fallback to PHP mail
            return $this->sendWithPHPMail($to, $subject, $body, $isHtml);
        }
    }
    
    private function connectToSMTP() {
        $context = stream_context_create();
        
        if ($this->settings['security'] === 'ssl') {
            $host = 'ssl://' . $this->settings['host'];
        } else {
            $host = $this->settings['host'];
        }
        
        return stream_socket_client(
            $host . ':' . $this->settings['port'],
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
    }
    
    private function sendSMTPCommand($socket, $command, $expectedCode) {
        if ($command !== '') {
            fwrite($socket, $command . "\r\n");
        }
        
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        
        $code = substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new Exception("SMTP Error: Expected $expectedCode, got $code. Response: $response");
        }
        
        return $response;
    }
    
    private function buildHeaders($to, $subject, $isHtml) {
        $headers = [];
        $headers[] = 'From: ' . $this->settings['from_name'] . ' <' . $this->settings['from_email'] . '>';
        $headers[] = 'To: ' . $to;
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . uniqid() . '@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>';
        $headers[] = 'X-Mailer: InnoDIGI License System';
        $headers[] = 'MIME-Version: 1.0';
        
        if ($isHtml) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        
        return implode("\r\n", $headers);
    }
    
    private function sendWithPHPMail($to, $subject, $body, $isHtml) {
        $headers = [];
        $headers[] = 'From: ' . $this->settings['from_name'] . ' <' . $this->settings['from_email'] . '>';
        $headers[] = 'Reply-To: ' . $this->settings['from_email'];
        $headers[] = 'X-Mailer: InnoDIGI License System';
        $headers[] = 'MIME-Version: 1.0';
        
        if ($isHtml) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
}
?>