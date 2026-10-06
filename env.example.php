<?php
/**
 * Run On Console (ROC) - Environment Configuration Example Template
 * Place actual production values in /home/CPANEL_USERNAME/config/env.php
 */

return [
    'DB_HOST' => '',
    'DB_NAME' => '',
    'DB_USER' => '',
    'DB_PASSWORD' => '',
    
    'SMTP_HOST' => '',
    'SMTP_PORT' => 587,
    'SMTP_ENCRYPTION' => 'tls',
    'SMTP_USERNAME' => '',
    'SMTP_PASSWORD' => '',
    
    'MAIL_FROM_ADDRESS' => 'noreply@runonconsole.com',
    'MAIL_FROM_NAME' => 'Run On Console',
    'MAIL_REPLY_TO' => 'support@runonconsole.com'
];
