<?php
/**
 * config/mail.php
 * SMTP & Email Configuration
 */

return [
    'smtp' => [
        'host'       => $_ENV['MAIL_HOST'] ?? 'localhost',
        'port'       => (int)($_ENV['MAIL_PORT'] ?? 587),
        'username'   => $_ENV['MAIL_USERNAME'] ?? 'your-email@example.com',
        'password'   => $_ENV['MAIL_PASSWORD'] ?? 'your-password',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls', // 'tls' or 'ssl'
        'from_email' => $_ENV['MAIL_FROM_EMAIL'] ?? 'noreply@arcabank.com',
        'from_name'  => $_ENV['MAIL_FROM_NAME'] ?? 'ArcaBank',
    ],
    
    'otp' => [
        'subject' => 'Your ArcaBank OTP Code',
        'expiry_minutes' => 10,
    ]
];
?>
