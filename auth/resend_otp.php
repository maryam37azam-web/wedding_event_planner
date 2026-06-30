<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/send_otp.php';

if (!is_post()) {
    redirect('/auth/verify_otp.php');
}

$submittedToken = (string) (
    $_POST['csrf_token'] ?? ''
);

if (!verify_csrf($submittedToken)) {
    set_flash(
        'error',
        'Your form session expired. Please try again.'
    );

    redirect('/auth/verify_otp.php');
}

$userId = (int) (
    $_SESSION['pending_verification_user_id'] ?? 0
);

$email = (string) (
    $_SESSION['pending_verification_email'] ?? ''
);

if ($userId < 1 || $email === '') {
    set_flash(
        'error',
        'Your verification session expired. Register again.'
    );

    redirect('/auth/register.php');
}

$lastSentAt = (int) (
    $_SESSION['otp_last_sent_at'] ?? 0
);

$secondsSinceLastSend = time() - $lastSentAt;

if ($lastSentAt > 0 && $secondsSinceLastSend < 60) {
    $remainingSeconds = 60 - $secondsSinceLastSend;

    set_flash(
        'error',
        "Please wait {$remainingSeconds} seconds before requesting another code."
    );

    redirect('/auth/verify_otp.php');
}

$userStatement = db()->prepare(
    'SELECT id, full_name, email, is_verified, is_active
     FROM users
     WHERE id = ?
     AND email = ?
     AND role = ?
     LIMIT 1'
);

$userStatement->execute([
    $userId,
    $email,
    'customer',
]);

$user = $userStatement->fetch();

if (!$user || (int) $user['is_active'] !== 1) {
    set_flash(
        'error',
        'This customer account is unavailable.'
    );

    redirect('/auth/register.php');
}

if ((int) $user['is_verified'] === 1) {
    unset(
        $_SESSION['pending_verification_user_id'],
        $_SESSION['pending_verification_email'],
        $_SESSION['otp_last_sent_at']
    );

    set_flash(
        'success',
        'Your email is already verified. You can log in.'
    );

    redirect('/auth/customer_login.php');
}

$result = send_email_verification_otp(
    (int) $user['id'],
    (string) $user['email'],
    (string) $user['full_name']
);

if ($result['success']) {
    $_SESSION['otp_last_sent_at'] = time();

    set_flash(
        'success',
        'A new six-digit verification code was sent.'
    );
} else {
    set_flash(
        'error',
        $result['message']
    );
}

redirect('/auth/verify_otp.php');