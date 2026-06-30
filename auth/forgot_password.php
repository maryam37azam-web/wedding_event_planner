<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/send_otp.php';

$errors = [];
$flash = get_flash();

$email = '';

if (is_post()) {
    $email = strtolower(
        trim((string) ($_POST['email'] ?? ''))
    );

    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your form session expired. Refresh and try again.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if ($errors === []) {
        try {
            $userStatement = db()->prepare(
                'SELECT
                    id,
                    full_name,
                    email,
                    role,
                    is_verified,
                    is_active
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );

            $userStatement->execute([$email]);

            $user = $userStatement->fetch();

            if (!$user) {
                $errors[] =
                    'No account was found with this email address.';
            } elseif ((int) $user['is_active'] !== 1) {
                $errors[] =
                    'This account is currently inactive.';
            } elseif ((int) $user['is_verified'] !== 1) {
                $errors[] =
                    'This email address has not been verified.';
            } else {
                $sendResult = send_password_reset_otp(
                    (int) $user['id'],
                    (string) $user['email'],
                    (string) $user['full_name']
                );

                if ($sendResult['success']) {
                    $_SESSION['password_reset_user_id'] =
                        (int) $user['id'];

                    $_SESSION['password_reset_email'] =
                        (string) $user['email'];

                    $_SESSION['password_reset_role'] =
                        (string) $user['role'];

                    $_SESSION['password_reset_last_sent_at'] =
                        time();

                    set_flash(
                        'success',
                        'A six-digit password reset code was sent to your email.'
                    );

                    redirect('/auth/reset_password.php');
                }

                $errors[] = $sendResult['message'];
            }
        } catch (Throwable $exception) {
            $errors[] = APP_DEBUG
                ? 'Password reset request failed: '
                    . $exception->getMessage()
                : 'Password reset request failed.';
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

    <title>Forgot Password | <?= e(APP_NAME) ?></title>

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

    <main class="auth-box">

        <div class="auth-logo">
            Wedding Planner
        </div>

        <div class="auth-title">
            Forgot Password
        </div>

        <div class="auth-subtitle">
            Enter your registered email address and we will send
            a password reset code.
        </div>

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

        <form method="post" autocomplete="on">

            <?= csrf_field() ?>

            <div class="input-box">
                <i class="fa-solid fa-envelope"></i>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($email) ?>"
                    placeholder="Enter Registered Email"
                    maxlength="190"
                    autocomplete="email"
                    required
                >
            </div>

            <button class="auth-button" type="submit">
                Send Reset Code
            </button>

        </form>

        <div class="auth-footer">
            <a href="<?= e(url('/auth/customer_login.php')) ?>">
                Customer Login
            </a>

            <br>

            <a href="<?= e(url('/auth/staff_login.php')) ?>">
                Staff Login
            </a>
        </div>

    </main>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>