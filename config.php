<?php
// Admin key for managing licenses
define('ADMIN_KEY', 'INNODIGI_admin2025!');

// Encryption secret for file storage (change this!)
define('ENCRYPTION_SECRET', 'InnoDIGI-2025-Secret-Key-Change-This-Value');

// SMTP Configuration
define('SMTP_ENABLED', true); // Set to false to use PHP mail() function
define('SMTP_HOST', 'mail.innodigi.nl'); // Your SMTP server
define('SMTP_PORT', 587); // SMTP port (587 for TLS, 465 for SSL, 25 for non-encrypted)
define('SMTP_SECURITY', 'tls'); // 'tls', 'ssl', or '' for no encryption
define('SMTP_USERNAME', 'noreply@innodigi.nl'); // SMTP username
define('SMTP_PASSWORD', 'your-smtp-password'); // SMTP password or app password
define('SMTP_FROM_EMAIL', 'noreply@innodigi.nl'); // From email address
define('SMTP_FROM_NAME', 'InnoDIGI License System'); // From name
define('SMTP_TO_EMAIL', 'support@innodigi.nl'); // Email address to receive tampering reports
?>