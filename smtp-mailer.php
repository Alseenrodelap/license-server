<?php
class SMTPMailer {
    private $host;
    private $port;
    private $security;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $this->host = SMTP_HOST;
        $this->port = SMTP_PORT;
        $this->security = SMTP_SECURITY;
        $this->username = SMTP_USERNAME;
        $this->password = SMTP_PASSWORD;
        $this->fromEmail = SMTP_FROM_EMAIL;
        $this->fromName = SMTP_FROM_NAME;
    }
    
    public function sendMail($to, $subject, $body, $isHtml = false) {
        if (!SMTP_ENABLED) {
            return $this->sendWithPHPMail($to, $subject, $body, $isHtml);
        }
        
        try {
            $socket = $this->connectToSMTP();
            
            if (!$socket) {
                throw new Exception('Could not connect to SMTP server');
            }
            
            $this->sendSMTPCommand($socket, '', '220');
            $this->sendSMTPCommand($socket, 'EHLO ' . $_SERVER['HTTP_HOST'], '250');
            
            // Start TLS if required
            if ($this->security === 'tls') {
                $this->sendSMTPCommand($socket, 'STARTTLS', '220');
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendSMTPCommand($socket, 'EHLO ' . $_SERVER['HTTP_HOST'], '250');
            }
            
            // Authentication
            if ($this->username && $this->password) {
                $this->sendSMTPCommand($socket, 'AUTH LOGIN', '334');
                $this->sendSMTPCommand($socket, base64_encode($this->username), '334');
                $this->sendSMTPCommand($socket, base64_encode($this->password), '235');
            }
            
            // Send email
            $this->sendSMTPCommand($socket, 'MAIL FROM: <' . $this->fromEmail . '>', '250');
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
        
        if ($this->security === 'ssl') {
            $host = 'ssl://' . $this->host;
        } else {
            $host = $this->host;
        }
        
        return stream_socket_client(
            $host . ':' . $this->port,
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
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'To: ' . $to;
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . uniqid() . '@' . $_SERVER['HTTP_HOST'] . '>';
        $headers[] = 'X-Mailer: Photobooth License System';
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
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'Reply-To: ' . $this->fromEmail;
        $headers[] = 'X-Mailer: Photobooth License System';
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