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

class DynamicSMTPMailerWithLogging {
    private $settings;
    private $log = [];
    
    public function __construct($smtpSettings) {
        $this->settings = $smtpSettings;
    }
    
    private function addLog($message, $type = 'info') {
        $this->log[] = [
            'timestamp' => date('H:i:s'),
            'type' => $type,
            'message' => $message
        ];
    }
    
    public function sendMailWithLogging($to, $subject, $body, $isHtml = false) {
        $this->log = []; // Reset log
        
        $this->addLog("Starting SMTP connection test...", 'info');
        $this->addLog("Target: " . $to, 'info');
        $this->addLog("Subject: " . $subject, 'info');
        
        if (!$this->settings['enabled']) {
            $this->addLog("SMTP is disabled, falling back to PHP mail()", 'warning');
            $result = $this->sendWithPHPMail($to, $subject, $body, $isHtml);
            return [
                'success' => $result,
                'log' => $this->log,
                'error' => $result ? null : 'PHP mail() failed'
            ];
        }
        
        try {
            $this->addLog("Connecting to SMTP server: " . $this->settings['host'] . ":" . $this->settings['port'], 'info');
            
            $socket = $this->connectToSMTP();
            
            if (!$socket) {
                throw new Exception('Could not connect to SMTP server');
            }
            
            $this->addLog("✓ Connected to SMTP server", 'success');
            
            $this->sendSMTPCommand($socket, '', '220', 'Initial server greeting');
            $this->sendSMTPCommand($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '250', 'EHLO handshake');
            
            // Start TLS if required
            if ($this->settings['security'] === 'tls') {
                $this->addLog("Starting TLS encryption...", 'info');
                $this->sendSMTPCommand($socket, 'STARTTLS', '220', 'STARTTLS command');
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->addLog("✓ TLS encryption established", 'success');
                $this->sendSMTPCommand($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '250', 'EHLO after TLS');
            }
            
            // Authentication
            if ($this->settings['username'] && $this->settings['password']) {
                $this->addLog("Authenticating with username: " . $this->settings['username'], 'info');
                $this->sendSMTPCommand($socket, 'AUTH LOGIN', '334', 'AUTH LOGIN command');
                $this->sendSMTPCommand($socket, base64_encode($this->settings['username']), '334', 'Username authentication');
                $this->sendSMTPCommand($socket, base64_encode($this->settings['password']), '235', 'Password authentication');
                $this->addLog("✓ Authentication successful", 'success');
            }
            
            // Send email
            $this->addLog("Sending email from: " . $this->settings['from_email'], 'info');
            $this->sendSMTPCommand($socket, 'MAIL FROM: <' . $this->settings['from_email'] . '>', '250', 'MAIL FROM command');
            $this->sendSMTPCommand($socket, 'RCPT TO: <' . $to . '>', '250', 'RCPT TO command');
            $this->sendSMTPCommand($socket, 'DATA', '354', 'DATA command');
            
            $headers = $this->buildHeaders($to, $subject, $isHtml);
            $message = $headers . "\r\n" . $body . "\r\n.";
            
            $this->addLog("Sending message content...", 'info');
            $this->sendSMTPCommand($socket, $message, '250', 'Message content');
            $this->addLog("✓ Message sent successfully", 'success');
            
            $this->sendSMTPCommand($socket, 'QUIT', '221', 'QUIT command');
            $this->addLog("✓ Connection closed cleanly", 'success');
            
            fclose($socket);
            
            $this->addLog("🎉 Email sent successfully!", 'success');
            
            return [
                'success' => true,
                'log' => $this->log
            ];
            
        } catch (Exception $e) {
            $this->addLog("❌ Error: " . $e->getMessage(), 'error');
            $this->addLog("Falling back to PHP mail()...", 'warning');
            
            // Fallback to PHP mail
            $result = $this->sendWithPHPMail($to, $subject, $body, $isHtml);
            
            if ($result) {
                $this->addLog("✓ PHP mail() fallback successful", 'success');
            } else {
                $this->addLog("❌ PHP mail() fallback also failed", 'error');
            }
            
            return [
                'success' => $result,
                'log' => $this->log,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function connectToSMTP() {
        $context = stream_context_create();
        
        if ($this->settings['security'] === 'ssl') {
            $host = 'ssl://' . $this->settings['host'];
            $this->addLog("Using SSL connection", 'info');
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
    
    private function sendSMTPCommand($socket, $command, $expectedCode, $description) {
        if ($command !== '') {
            fwrite($socket, $command . "\r\n");
            $this->addLog("→ " . $description, 'info');
        }
        
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        
        $code = substr($response, 0, 3);
        $responseMsg = trim(substr($response, 4));
        
        if ($code !== $expectedCode) {
            $this->addLog("← " . $code . " " . $responseMsg, 'error');
            throw new Exception("SMTP Error: Expected $expectedCode, got $code. Response: $response");
        } else {
            $this->addLog("← " . $code . " " . $responseMsg, 'success');
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