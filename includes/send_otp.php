<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/functions.php';

/**
 * Create, store and email an OTP code.
 */
function create_and_send_otp(
    int $userId,
    string $email,
    string $fullName,
    string $purpose
): array {
    $allowedPurposes = [
        'email_verification',
        'password_reset',
    ];

    if (!in_array($purpose, $allowedPurposes, true)) {
        return [
            'success' => false,
            'message' => 'Invalid OTP purpose.',
        ];
    }

    $config = mail_config();

    if (
        $config['username'] === ''
        || $config['password'] === ''
        || $config['from_address'] === ''
    ) {
        return [
            'success' => false,
            'message' => 'Gmail SMTP credentials are missing.',
        ];
    }

    $otp = (string) random_int(100000, 999999);

    $expiryMinutes = max(
        1,
        (int) $config['otp_expiry_minutes']
    );

    $expiresAt = (new DateTimeImmutable())
        ->modify("+{$expiryMinutes} minutes")
        ->format('Y-m-d H:i:s');

    $connection = db();
    $otpId = 0;

    try {
        $connection->beginTransaction();

        /*
         * Disable previous unused OTP codes for the same purpose.
         */
        $disableStatement = $connection->prepare(
            'UPDATE otp_codes
             SET used_at = NOW()
             WHERE user_id = ?
             AND purpose = ?
             AND used_at IS NULL'
        );

        $disableStatement->execute([
            $userId,
            $purpose,
        ]);

        $insertStatement = $connection->prepare(
            'INSERT INTO otp_codes (
                user_id,
                email,
                otp_hash,
                purpose,
                expires_at
             ) VALUES (?, ?, ?, ?, ?)'
        );

        $insertStatement->execute([
            $userId,
            $email,
            password_hash($otp, PASSWORD_DEFAULT),
            $purpose,
            $expiresAt,
        ]);

        $otpId = (int) $connection->lastInsertId();

        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        return [
            'success' => false,
            'message' => APP_DEBUG
                ? 'Could not create OTP: ' . $exception->getMessage()
                : 'Could not create the verification code.',
        ];
    }

    if ($purpose === 'password_reset') {
        $subject = 'Reset Your Wedding Planner Password';
        $heading = 'Reset Your Password';

        $message =
            'Use the verification code below to reset your '
            . 'Wedding Event Planner account password.';
    } else {
        $subject = 'Verify Your Wedding Planner Account';
        $heading = 'Verify Your Email';

        $message =
            'Use the verification code below to complete '
            . 'your Wedding Event Planner registration.';
    }

    $safeName = e($fullName);

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

        $mail->Host = $config['host'];
        $mail->Port = $config['port'];
        $mail->SMTPAuth = true;

        $mail->Username = $config['username'];
        $mail->Password = $config['password'];

        if (strtolower($config['encryption']) === 'ssl') {
            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 20;

        $mail->setFrom(
            $config['from_address'],
            $config['from_name']
        );

        $mail->addAddress($email, $fullName);

        $mail->isHTML(true);
        $mail->Subject = $subject;

        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
</head>
<body style="margin:0;background:#f8edf2;font-family:Arial,sans-serif;">
    <div style="max-width:580px;margin:30px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(0,0,0,0.10);">
        <div style="padding:28px;background:linear-gradient(135deg,#7a0038,#d81b60);color:#ffffff;text-align:center;">
            <h1 style="margin:0;font-size:25px;">
                Wedding Event Planner
            </h1>
        </div>

        <div style="padding:34px;text-align:center;color:#333333;">
            <h2 style="margin-top:0;color:#a4004d;">
                {$heading}
            </h2>

            <p style="font-size:16px;line-height:1.7;">
                Hello {$safeName}, {$message}
            </p>

            <div style="display:inline-block;margin:20px 0;padding:18px 28px;background:#fff1f6;border:2px dashed #a4004d;border-radius:12px;color:#a4004d;font-size:34px;font-weight:bold;letter-spacing:8px;">
                {$otp}
            </div>

            <p style="font-size:15px;line-height:1.7;">
                This code will expire in
                <strong>{$expiryMinutes} minutes</strong>.
            </p>

            <p style="font-size:13px;color:#777777;">
                Do not share this code with anyone.
            </p>
        </div>
    </div>
</body>
</html>
HTML;

        $mail->AltBody =
            "Hello {$fullName}, your Wedding Event Planner "
            . "verification code is {$otp}. "
            . "It expires in {$expiryMinutes} minutes.";

        $mail->send();

        return [
            'success' => true,
            'message' => 'Verification code sent successfully.',
        ];
    } catch (Throwable $exception) {
        if ($otpId > 0) {
            $deleteStatement = db()->prepare(
                'DELETE FROM otp_codes WHERE id = ?'
            );

            $deleteStatement->execute([$otpId]);
        }

        return [
            'success' => false,
            'message' => APP_DEBUG
                ? 'Email could not be sent: '
                    . $exception->getMessage()
                : 'Verification email could not be sent.',
        ];
    }
}

/**
 * Send account verification OTP.
 */
function send_email_verification_otp(
    int $userId,
    string $email,
    string $fullName
): array {
    return create_and_send_otp(
        $userId,
        $email,
        $fullName,
        'email_verification'
    );
}

/**
 * Send password reset OTP.
 */
function send_password_reset_otp(
    int $userId,
    string $email,
    string $fullName
): array {
    return create_and_send_otp(
        $userId,
        $email,
        $fullName,
        'password_reset'
    );
}