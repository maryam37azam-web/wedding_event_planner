<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$errors = [];
$flash = get_flash();

$verificationComplete =
    isset($_GET['verified'])
    && $_GET['verified'] === '1'
    && !empty($_SESSION['recently_verified_email']);

if ($verificationComplete) {
    $verifiedEmail = (string) (
        $_SESSION['recently_verified_email']
    );

    unset($_SESSION['recently_verified_email']);
} else {
    $userId = (int) (
        $_SESSION['pending_verification_user_id'] ?? 0
    );

    $email = (string) (
        $_SESSION['pending_verification_email'] ?? ''
    );

    if ($userId < 1 || $email === '') {
        set_flash(
            'error',
            'Register an account before entering a verification code.'
        );

        redirect('/auth/register.php');
    }

    if (is_post()) {
        $submittedToken = (string) (
            $_POST['csrf_token'] ?? ''
        );

        $otp = preg_replace(
            '/\D/',
            '',
            (string) ($_POST['otp'] ?? '')
        );

        if (!verify_csrf($submittedToken)) {
            $errors[] =
                'Your form session expired. Refresh and try again.';
        }

        if (!is_string($otp) || strlen($otp) !== 6) {
            $errors[] =
                'Enter the complete six-digit verification code.';
        }

        if ($errors === []) {
            $connection = db();

            try {
                $otpStatement = $connection->prepare(
                    'SELECT id, otp_hash, expires_at, attempts
                     FROM otp_codes
                     WHERE user_id = ?
                     AND email = ?
                     AND purpose = ?
                     AND used_at IS NULL
                     ORDER BY id DESC
                     LIMIT 1'
                );

                $otpStatement->execute([
                    $userId,
                    $email,
                    'email_verification',
                ]);

                $otpRecord = $otpStatement->fetch();

                if (!$otpRecord) {
                    $errors[] =
                        'No active verification code was found. '
                        . 'Please request a new code.';
                } elseif ((int) $otpRecord['attempts'] >= 5) {
                    $errors[] =
                        'Too many incorrect attempts. '
                        . 'Please request a new verification code.';
                } elseif (
                    new DateTimeImmutable()
                    > new DateTimeImmutable(
                        (string) $otpRecord['expires_at']
                    )
                ) {
                    $errors[] =
                        'This verification code has expired. '
                        . 'Please request a new code.';
                } elseif (
                    !password_verify(
                        $otp,
                        (string) $otpRecord['otp_hash']
                    )
                ) {
                    $newAttempts =
                        (int) $otpRecord['attempts'] + 1;

                    $attemptStatement = $connection->prepare(
                        'UPDATE otp_codes
                         SET attempts = ?
                         WHERE id = ?'
                    );

                    $attemptStatement->execute([
                        $newAttempts,
                        $otpRecord['id'],
                    ]);

                    $remainingAttempts = max(
                        0,
                        5 - $newAttempts
                    );

                    $errors[] =
                        'Incorrect verification code. '
                        . $remainingAttempts
                        . ' attempt(s) remaining.';
                } else {
                    $connection->beginTransaction();

                    $useOtpStatement = $connection->prepare(
                        'UPDATE otp_codes
                         SET used_at = NOW()
                         WHERE id = ?'
                    );

                    $useOtpStatement->execute([
                        $otpRecord['id'],
                    ]);

                    $verifyUserStatement = $connection->prepare(
                        'UPDATE users
                         SET is_verified = 1,
                             email_verified_at = NOW()
                         WHERE id = ?
                         AND email = ?'
                    );

                    $verifyUserStatement->execute([
                        $userId,
                        $email,
                    ]);

                    $connection->commit();

                    unset(
                        $_SESSION['pending_verification_user_id'],
                        $_SESSION['pending_verification_email'],
                        $_SESSION['otp_last_sent_at']
                    );

                    $_SESSION['recently_verified_email'] = $email;

                    redirect(
                        '/auth/verify_otp.php?verified=1'
                    );
                }
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                $errors[] = APP_DEBUG
                    ? 'Verification failed: '
                        . $exception->getMessage()
                    : 'Verification failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Email Verification | <?= e(APP_NAME) ?></title>
    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="<?= e(url('/assets/css/auth.css')) ?>"
    >
</head>

<body class="auth-page">
    <?php if ($verificationComplete): ?>
        <main class="otp-content">
            <div class="success-icon">✓</div>

            <h1>Email Verified</h1>

            <p>
                Your email address
                <strong><?= e($verifiedEmail) ?></strong>
                has been verified successfully. Your customer
                account is now ready.
            </p>

            <a
                class="auth-button"
                style="
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    text-decoration:none;
                "
                href="<?= e(url('/auth/customer_login.php')) ?>"
            >
                Continue to Customer Login
            </a>
        </main>
    <?php else: ?>
        <main class="otp-content">
            <div class="otp-icon">✉</div>

            <h1>Verify Your Email</h1>

            <p>
                Enter the six-digit code sent to
                <strong><?= e(mask_email($email)) ?></strong>.
                The code expires in 10 minutes.
            </p>

            <?php if ($flash): ?>
                <div class="alert <?= $flash['type'] === 'success'
                    ? 'alert-success'
                    : 'alert-danger' ?>">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post">
                <?= csrf_field() ?>

                <input
                    class="otp-input"
                    type="text"
                    name="otp"
                    maxlength="6"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    autocomplete="one-time-code"
                    autofocus
                    required
                >

                <button class="auth-button" type="submit">
                    Verify Account
                </button>
            </form>

            <form
                class="resend-form"
                method="post"
                action="<?= e(url('/auth/resend_otp.php')) ?>"
            >
                <?= csrf_field() ?>

                <span>Did not receive the email?</span>

                <button class="resend-button" type="submit">
                    Resend Code
                </button>
            </form>

            <a
                class="back-link"
                href="<?= e(url('/auth/register.php')) ?>"
            >
                ← Return to registration
            </a>
        </main>
    <?php endif; ?>
    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>
</body>
</html>