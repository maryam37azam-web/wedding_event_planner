<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/send_otp.php';

$errors = [];
$flash = get_flash();

$userId = (int) (
    $_SESSION['password_reset_user_id'] ?? 0
);

$email = (string) (
    $_SESSION['password_reset_email'] ?? ''
);

$role = (string) (
    $_SESSION['password_reset_role'] ?? ''
);

if ($userId < 1 || $email === '' || $role === '') {
    set_flash(
        'error',
        'Start a new password reset request.'
    );

    redirect('/auth/forgot_password.php');
}

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $action = (string) (
        $_POST['action'] ?? 'reset'
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your form session expired. Refresh and try again.';
    }

    /*
     * Resend the password reset OTP.
     */
    if ($action === 'resend' && $errors === []) {
        $lastSentAt = (int) (
            $_SESSION['password_reset_last_sent_at'] ?? 0
        );

        $secondsPassed = time() - $lastSentAt;

        if ($lastSentAt > 0 && $secondsPassed < 60) {
            $remainingSeconds = 60 - $secondsPassed;

            set_flash(
                'error',
                "Please wait {$remainingSeconds} seconds before requesting another code."
            );

            redirect('/auth/reset_password.php');
        }

        $userStatement = db()->prepare(
            'SELECT
                id,
                full_name,
                email,
                role,
                is_active
             FROM users
             WHERE id = ?
             AND email = ?
             LIMIT 1'
        );

        $userStatement->execute([
            $userId,
            $email,
        ]);

        $user = $userStatement->fetch();

        if (!$user || (int) $user['is_active'] !== 1) {
            set_flash(
                'error',
                'This account is unavailable.'
            );

            redirect('/auth/forgot_password.php');
        }

        $sendResult = send_password_reset_otp(
            (int) $user['id'],
            (string) $user['email'],
            (string) $user['full_name']
        );

        if ($sendResult['success']) {
            $_SESSION['password_reset_last_sent_at'] =
                time();

            set_flash(
                'success',
                'A new password reset code was sent.'
            );
        } else {
            set_flash(
                'error',
                $sendResult['message']
            );
        }

        redirect('/auth/reset_password.php');
    }

    if ($action === 'reset') {
        $otp = preg_replace(
            '/\D/',
            '',
            (string) ($_POST['otp'] ?? '')
        );

        $password = (string) (
            $_POST['password'] ?? ''
        );

        $confirmPassword = (string) (
            $_POST['confirm_password'] ?? ''
        );

        if (!is_string($otp) || strlen($otp) !== 6) {
            $errors[] =
                'Enter the complete six-digit reset code.';
        }

        if (strlen($password) < 8) {
            $errors[] =
                'Password must contain at least 8 characters.';
        }

        if (
            !preg_match('/[A-Za-z]/', $password)
            || !preg_match('/[0-9]/', $password)
        ) {
            $errors[] =
                'Password must contain at least one letter and one number.';
        }

        if ($password !== $confirmPassword) {
            $errors[] =
                'Password confirmation does not match.';
        }

        if ($errors === []) {
            $connection = db();

            try {
                $otpStatement = $connection->prepare(
                    'SELECT
                        id,
                        otp_hash,
                        expires_at,
                        attempts
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
                    'password_reset',
                ]);

                $otpRecord = $otpStatement->fetch();

                if (!$otpRecord) {
                    $errors[] =
                        'No active password reset code was found.';
                } elseif ((int) $otpRecord['attempts'] >= 5) {
                    $errors[] =
                        'Too many incorrect attempts. Request a new code.';
                } elseif (
                    new DateTimeImmutable()
                    > new DateTimeImmutable(
                        (string) $otpRecord['expires_at']
                    )
                ) {
                    $errors[] =
                        'This password reset code has expired.';
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
                        'Incorrect reset code. '
                        . $remainingAttempts
                        . ' attempt(s) remaining.';
                } else {
                    $connection->beginTransaction();

                    $updatePasswordStatement =
                        $connection->prepare(
                            'UPDATE users
                             SET password = ?
                             WHERE id = ?
                             AND email = ?'
                        );

                    $updatePasswordStatement->execute([
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        ),
                        $userId,
                        $email,
                    ]);

                    $disableOtpStatement =
                        $connection->prepare(
                            'UPDATE otp_codes
                             SET used_at = NOW()
                             WHERE user_id = ?
                             AND purpose = ?
                             AND used_at IS NULL'
                        );

                    $disableOtpStatement->execute([
                        $userId,
                        'password_reset',
                    ]);

                    $connection->commit();

                    unset(
                        $_SESSION['password_reset_user_id'],
                        $_SESSION['password_reset_email'],
                        $_SESSION['password_reset_role'],
                        $_SESSION['password_reset_last_sent_at']
                    );

                    set_flash(
                        'success',
                        'Your password was changed successfully. Log in using your new password.'
                    );

                    if ($role === 'customer') {
                        redirect(
                            '/auth/customer_login.php'
                        );
                    }

                    redirect('/auth/staff_login.php');
                }
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                $errors[] = APP_DEBUG
                    ? 'Password reset failed: '
                        . $exception->getMessage()
                    : 'Password reset failed.';
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

    <title>Reset Password | <?= e(APP_NAME) ?></title>

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(url('/assets/css/auth.css')) ?>"
    >
</head>

<body class="auth-page">

    <main class="auth-box otp-box">

        <div class="otp-icon">
            <i class="fa-solid fa-key"></i>
        </div>

        <div class="auth-logo">
            Reset Password
        </div>

        <p class="otp-description">
            Enter the six-digit code sent to
            <strong><?= e(mask_email($email)) ?></strong>
            and create your new password.
        </p>

        <?php if ($flash): ?>
            <div
                class="alert <?= $flash['type'] === 'success'
                    ? 'alert-success'
                    : 'alert-danger' ?>"
            >
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
                type="hidden"
                name="action"
                value="reset"
            >

            <input
                class="otp-input"
                type="text"
                name="otp"
                maxlength="6"
                inputmode="numeric"
                pattern="[0-9]{6}"
                autocomplete="one-time-code"
                placeholder="000000"
                required
            >

            <div class="input-box">
                <i class="fa-solid fa-lock"></i>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Create New Password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <button
                    class="password-toggle"
                    type="button"
                    data-password-target="password"
                >
                    Show
                </button>
            </div>

            <div class="input-box">
                <i class="fa-solid fa-lock"></i>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Confirm New Password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <button
                    class="password-toggle"
                    type="button"
                    data-password-target="confirm_password"
                >
                    Show
                </button>
            </div>

            <button class="auth-button" type="submit">
                Reset Password
            </button>

        </form>

        <form class="resend-form" method="post">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="resend"
            >

            <span>Did not receive the code?</span>

            <button class="resend-button" type="submit">
                Resend Code
            </button>

        </form>

        <a
            class="back-link"
            href="<?= e(url('/auth/forgot_password.php')) ?>"
        >
            ← Start a new request
        </a>

    </main>

    <script>
        document
            .querySelectorAll("[data-password-target]")
            .forEach(function (button) {
                button.addEventListener("click", function () {
                    const field = document.getElementById(
                        button.dataset.passwordTarget
                    );

                    if (!field) {
                        return;
                    }

                    const isHidden =
                        field.type === "password";

                    field.type = isHidden
                        ? "text"
                        : "password";

                    button.textContent = isHidden
                        ? "Hide"
                        : "Show";
                });
            });
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>