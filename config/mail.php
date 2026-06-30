<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

/**
 * Return SMTP and OTP configuration.
 */
function mail_config(): array
{
    return [
        'host' => (string) env_value(
            'MAIL_HOST',
            'smtp.gmail.com'
        ),

        'port' => (int) env_value('MAIL_PORT', 587),

        'encryption' => (string) env_value(
            'MAIL_ENCRYPTION',
            'tls'
        ),

        'username' => (string) env_value(
            'MAIL_USERNAME',
            ''
        ),

        'password' => (string) env_value(
            'MAIL_PASSWORD',
            ''
        ),

        'from_address' => (string) env_value(
            'MAIL_FROM_ADDRESS',
            ''
        ),

        'from_name' => (string) env_value(
            'MAIL_FROM_NAME',
            APP_NAME
        ),

        'otp_expiry_minutes' => (int) env_value(
            'OTP_EXPIRY_MINUTES',
            10
        ),
    ];
}