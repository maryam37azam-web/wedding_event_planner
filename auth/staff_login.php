<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/role_check.php';

/*
|--------------------------------------------------------------------------
| Redirect already logged-in users
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['user_id'], $_SESSION['user_role'])
) {
    redirect(
        dashboard_path_for_role(
            (string) $_SESSION['user_role']
        )
    );
}

$errors = [];
$flash = get_flash();

$email = '';

$staffRoles = [
    'admin',
    'event_manager',
    'booking_manager',
];

/*
|--------------------------------------------------------------------------
| Process staff login
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $email = strtolower(
        trim((string) ($_POST['email'] ?? ''))
    );

    $password = (string) ($_POST['password'] ?? '');

    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your login session expired. Refresh and try again.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid staff email address.';
    }

    if ($password === '') {
        $errors[] = 'Enter your password.';
    }

    if ($errors === []) {
        try {
            $statement = db()->prepare(
                'SELECT
                    id,
                    full_name,
                    email,
                    password,
                    role,
                    is_verified,
                    is_active
                 FROM users
                 WHERE email = ?
                 AND role IN (?, ?, ?)
                 LIMIT 1'
            );

            $statement->execute([
                $email,
                'admin',
                'event_manager',
                'booking_manager',
            ]);

            $user = $statement->fetch();

            if (
                !$user
                || !password_verify(
                    $password,
                    (string) $user['password']
                )
            ) {
                $errors[] =
                    'The staff email or password is incorrect.';
            } elseif (
                !in_array(
                    (string) $user['role'],
                    $staffRoles,
                    true
                )
            ) {
                $errors[] =
                    'This account is not a staff account.';
            } elseif ((int) $user['is_active'] !== 1) {
                $errors[] =
                    'This staff account is currently inactive.';
            } elseif ((int) $user['is_verified'] !== 1) {
                $errors[] =
                    'This staff account has not been verified.';
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id'] =
                    (int) $user['id'];

                $_SESSION['user_role'] =
                    (string) $user['role'];

                $_SESSION['user_name'] =
                    (string) $user['full_name'];

                $_SESSION['user_email'] =
                    (string) $user['email'];

                /*
                 * Record the most recent login time.
                 */
                $updateStatement = db()->prepare(
                    'UPDATE users
                     SET last_login_at = NOW()
                     WHERE id = ?'
                );

                $updateStatement->execute([
                    (int) $user['id'],
                ]);

                $defaultDashboard =
                    dashboard_path_for_role(
                        (string) $user['role']
                    );

                $requestedPage = (string) (
                    $_SESSION['redirect_after_login']
                    ?? ''
                );

                unset($_SESSION['redirect_after_login']);

                /*
                 * Only allow redirection to the folder belonging
                 * to the logged-in staff role.
                 */
                $requiredPrefix = match (
                    (string) $user['role']
                ) {
                    'admin' => '/admin/',

                    'event_manager' =>
                        '/event_manager/',

                    'booking_manager' =>
                        '/booking_manager/',

                    default => '',
                };

                if (
                    $requiredPrefix !== ''
                    && str_starts_with(
                        $requestedPage,
                        $requiredPrefix
                    )
                ) {
                    redirect($requestedPage);
                }

                redirect($defaultDashboard);
            }
        } catch (Throwable $exception) {
            $errors[] = APP_DEBUG
                ? 'Staff login failed: '
                    . $exception->getMessage()
                : 'Staff login failed. Please try again.';
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

    <title>Staff Login | <?= e(APP_NAME) ?></title>

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
            Staff Login
        </div>

        <div class="auth-subtitle">
            Admin, Event Manager and Booking Manager access.
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
                    placeholder="Enter Staff Email"
                    maxlength="190"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="input-box">
                <i class="fa-solid fa-lock"></i>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter Password"
                    autocomplete="current-password"
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

            <div
                style="
                    margin-top: -6px;
                    margin-bottom: 20px;
                    text-align: right;
                "
            >
                <a
                    href="<?= e(url('/auth/forgot_password.php')) ?>"
                    style="
                        color: #ffffff;
                        font-size: 14px;
                        font-weight: 600;
                        text-decoration: underline;
                    "
                >
                    Forgot Password?
                </a>
            </div>

            <button class="auth-button" type="submit">
                Login
            </button>

        </form>

        <div class="auth-footer">
            Customer account?<br>

            <a href="<?= e(url('/auth/customer_login.php')) ?>">
                Customer Login
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
