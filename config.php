<?php
// Admin key for managing licenses
define('ADMIN_KEY', 'BOOTHK_li1!');

// Encryption secret for file storage (change this!)
define('ENCRYPTION_SECRET', 'WenL-SJPJ-tvx40LnI1CW084B_HaVKRiOTLQA5rTHXQ');

// License types
define('LICENSE_TYPES', [
    'trial' => 'Trial (30 dagen)',
    'standard' => 'Standard',
    'premium' => 'Premium',
    'enterprise' => 'Enterprise'
]);

// SMTP Configuration
define('SMTP_ENABLED', true); // Set to false to use PHP mail() function
define('SMTP_HOST', 'smtp.gmail.com'); // Your SMTP server
define('SMTP_PORT', 587); // SMTP port (587 for TLS, 465 for SSL, 25 for non-encrypted)
define('SMTP_SECURITY', 'tls'); // 'tls', 'ssl', or '' for no encryption
define('SMTP_USERNAME', 'your-email@gmail.com'); // SMTP username
define('SMTP_PASSWORD', 'your-app-password'); // SMTP password or app password
define('SMTP_FROM_EMAIL', 'noreply@boothkings.nl'); // From email address
define('SMTP_FROM_NAME', 'Photobooth License System'); // From name
define('SMTP_TO_EMAIL', 'info@boothkings.nl'); // Email address to receive tampering reports
?>