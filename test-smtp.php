<?php
require_once 'config.php';
require_once 'smtp-mailer.php';

// Test script voor SMTP configuratie
echo "<h1>SMTP Test</h1>";

if ($_POST['test_email'] ?? false) {
    $testEmail = $_POST['email'] ?? SMTP_TO_EMAIL;
    
    $mailer = new SMTPMailer();
    $subject = "SMTP Test - InnoDIGI License System";
    $body = "Dit is een test email om te controleren of de SMTP configuratie correct werkt.\n\n";
    $body .= "Configuratie:\n";
    $body .= "- SMTP Host: " . SMTP_HOST . "\n";
    $body .= "- SMTP Port: " . SMTP_PORT . "\n";
    $body .= "- SMTP Security: " . SMTP_SECURITY . "\n";
    $body .= "- SMTP Username: " . SMTP_USERNAME . "\n";
    $body .= "- Timestamp: " . date('Y-m-d H:i:s') . "\n";
    
    $result = $mailer->sendMail($testEmail, $subject, $body);
    
    if ($result) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "✅ Test email succesvol verzonden naar: " . htmlspecialchars($testEmail);
        echo "</div>";
    } else {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "❌ Test email kon niet worden verzonden. Controleer de SMTP configuratie.";
        echo "</div>";
    }
}

echo "<h2>Huidige SMTP Configuratie:</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><td><strong>SMTP Enabled:</strong></td><td>" . (SMTP_ENABLED ? 'Ja' : 'Nee') . "</td></tr>";
echo "<tr><td><strong>SMTP Host:</strong></td><td>" . SMTP_HOST . "</td></tr>";
echo "<tr><td><strong>SMTP Port:</strong></td><td>" . SMTP_PORT . "</td></tr>";
echo "<tr><td><strong>SMTP Security:</strong></td><td>" . SMTP_SECURITY . "</td></tr>";
echo "<tr><td><strong>SMTP Username:</strong></td><td>" . SMTP_USERNAME . "</td></tr>";
echo "<tr><td><strong>From Email:</strong></td><td>" . SMTP_FROM_EMAIL . "</td></tr>";
echo "<tr><td><strong>To Email:</strong></td><td>" . SMTP_TO_EMAIL . "</td></tr>";
echo "</table>";

echo "<h2>Test Email Verzenden:</h2>";
echo "<form method='post'>";
echo "<p>";
echo "<label>Email adres: </label>";
echo "<input type='email' name='email' value='" . SMTP_TO_EMAIL . "' required style='width: 300px; padding: 5px;'>";
echo "</p>";
echo "<p>";
echo "<input type='submit' name='test_email' value='Test Email Verzenden' style='padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer;'>";
echo "</p>";
echo "</form>";

echo "<h2>Troubleshooting:</h2>";
echo "<ul>";
echo "<li><strong>Gmail:</strong> Gebruik een app-specific password, niet je gewone wachtwoord</li>";
echo "<li><strong>Outlook:</strong> Zorg dat 'Less secure app access' is ingeschakeld</li>";
echo "<li><strong>Firewall:</strong> Controleer of uitgaande SMTP poorten niet geblokkeerd zijn</li>";
echo "<li><strong>SSL/TLS:</strong> Controleer of je server SSL/TLS ondersteunt</li>";
echo "</ul>";
?>