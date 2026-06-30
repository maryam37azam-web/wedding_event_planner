<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/send_otp.php';

$errors = [];

$fullName = '';
$email = '';
$phone = '';

if (is_post()) {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));

    $email = strtolower(
        trim((string) ($_POST['email'] ?? ''))
    );

    $phone = trim((string) ($_POST['phone'] ?? ''));

    $password = (string) ($_POST['password'] ?? '');

    $confirmPassword = (string) (
        $_POST['confirm_password'] ?? ''
    );

    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

    if (
        strlen($fullName) < 3
        || strlen($fullName) > 120
    ) {
        $errors[] =
            'Full name must be between 3 and 120 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (
        $phone !== ''
        && !preg_match('/^[0-9+\-\s()]{7,30}$/', $phone)
    ) {
        $errors[] = 'Enter a valid phone number.';
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
        $errors[] = 'Password confirmation does not match.';
    }

    if ($errors === []) {
        $connection = db();

        try {
            $existingStatement = $connection->prepare(
                'SELECT id, role, is_verified
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );

            $existingStatement->execute([$email]);

            $existingUser = $existingStatement->fetch();

            if (
                $existingUser
                && $existingUser['role'] !== 'customer'
            ) {
                $errors[] =
                    'This email is already assigned to a staff account.';
            } elseif (
                $existingUser
                && (int) $existingUser['is_verified'] === 1
            ) {
                $errors[] =
                    'An account with this email already exists.';
            } else {
                $connection->beginTransaction();

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($existingUser) {
                    $userId = (int) $existingUser['id'];

                    $updateStatement = $connection->prepare(
                        'UPDATE users
                         SET full_name = ?,
                             phone = ?,
                             password = ?,
                             is_active = 1
                         WHERE id = ?'
                    );

                    $updateStatement->execute([
                        $fullName,
                        $phone !== '' ? $phone : null,
                        $passwordHash,
                        $userId,
                    ]);
                } else {
                    $insertStatement = $connection->prepare(
                        'INSERT INTO users (
                            full_name,
                            email,
                            phone,
                            password,
                            role,
                            is_verified,
                            is_active
                         ) VALUES (?, ?, ?, ?, ?, 0, 1)'
                    );

                    $insertStatement->execute([
                        $fullName,
                        $email,
                        $phone !== '' ? $phone : null,
                        $passwordHash,
                        'customer',
                    ]);

                    $userId = (int) $connection->lastInsertId();
                }

                $connection->commit();

                $sendResult = send_email_verification_otp(
                    $userId,
                    $email,
                    $fullName
                );

                if ($sendResult['success']) {
                    $_SESSION['pending_verification_user_id']
                        = $userId;

                    $_SESSION['pending_verification_email']
                        = $email;

                    $_SESSION['otp_last_sent_at'] = time();

                    set_flash(
                        'success',
                        'A six-digit verification code was sent to your email.'
                    );

                    redirect('/auth/verify_otp.php');
                }

                $errors[] = $sendResult['message'];
            }
        } catch (Throwable $exception) {
            if (
                isset($connection)
                && $connection->inTransaction()
            ) {
                $connection->rollBack();
            }

            $errors[] = APP_DEBUG
                ? 'Registration failed: '
                    . $exception->getMessage()
                : 'Registration failed. Please try again.';
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

    <title>Create Account | <?= e(APP_NAME) ?></title>
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

    <main class="auth-box register-box">

        <div class="auth-logo">
            Wedding Planner
        </div>

        <div class="auth-title">
            Customer Registration
        </div>

        <div class="auth-subtitle">
            Create your account to book and manage your wedding event.
        </div>

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

            <div class="form-grid">

                <div class="input-box full-width">
                    <i class="fa-solid fa-user"></i>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= e($fullName) ?>"
                        placeholder="Enter Full Name"
                        maxlength="120"
                        autocomplete="name"
                        required
                    >
                </div>

                <div class="input-box">
                    <i class="fa-solid fa-envelope"></i>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= e($email) ?>"
                        placeholder="Enter Email"
                        maxlength="190"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="input-box">
                    <i class="fa-solid fa-phone"></i>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="<?= e($phone) ?>"
                        placeholder="Enter Phone Number"
                        maxlength="30"
                        autocomplete="tel"
                    >
                </div>

                <div class="input-box">
                    <i class="fa-solid fa-lock"></i>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Create Password"
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
                        placeholder="Confirm Password"
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

            </div>

            <button class="auth-button" type="submit">
                Create Account
            </button>

        </form>

        <div class="auth-footer">
            Already have a customer account?<br>

            <a href="<?= e(url('/auth/customer_login.php')) ?>">
                Login Here
            </a>

            <br>

            <a href="<?= e(url('/')) ?>">
                Return to Website
            </a>
        </div>

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

                    const passwordIsHidden =
                        field.type === "password";

                    field.type = passwordIsHidden
                        ? "text"
                        : "password";

                    button.textContent = passwordIsHidden
                        ? "Hide"
                        : "Show";
                });
            });
    </script>
<?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>
</body>
</html>