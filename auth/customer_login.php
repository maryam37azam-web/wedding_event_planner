<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (
    isset(
        $_SESSION['user_id'],
        $_SESSION['user_role']
    )
    && $_SESSION['user_role']
        === 'customer'
) {
    redirect(
        '/customer/dashboard.php'
    );
}

$errors = [];
$flash = get_flash();

$email = '';

if (is_post()) {
    $email = strtolower(
        trim(
            (string) (
                $_POST['email']
                ?? ''
            )
        )
    );

    $password = (string) (
        $_POST['password']
        ?? ''
    );

    $submittedToken = (string) (
        $_POST['csrf_token']
        ?? ''
    );

    if (
        !verify_csrf(
            $submittedToken
        )
    ) {
        $errors[] =
            'Your login session expired. Refresh the page and try again.';
    }

    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $errors[] =
            'Enter a valid email address.';
    }

    if ($password === '') {
        $errors[] =
            'Enter your password.';
    }

    if ($errors === []) {
        try {
            $statement = db()->prepare(
                'SELECT
                    id,
                    full_name,
                    email,
                    phone,
                    password,
                    role,
                    is_active
                 FROM users
                 WHERE email = ?
                 AND role = ?
                 LIMIT 1'
            );

            $statement->execute([
                $email,
                'customer',
            ]);

            $user =
                $statement->fetch();

            if (
                !$user
                || !password_verify(
                    $password,
                    (string) (
                        $user['password']
                    )
                )
            ) {
                $errors[] =
                    'The email address or password is incorrect.';
            } elseif (
                (int) $user[
                    'is_active'
                ] !== 1
            ) {
                $errors[] =
                    'Your account is currently inactive. Please contact support.';
            } else {
                session_regenerate_id(
                    true
                );

                $_SESSION['user_id'] =
                    (int) $user['id'];

                $_SESSION['user_role'] =
                    'customer';

                $_SESSION['user_name'] =
                    (string) (
                        $user['full_name']
                    );

                $_SESSION['user_email'] =
                    (string) (
                        $user['email']
                    );

                unset(
                    $_SESSION[
                        'pending_verification_user_id'
                    ],
                    $_SESSION[
                        'pending_verification_email'
                    ],
                    $_SESSION[
                        'otp_last_sent_at'
                    ],
                    $_SESSION[
                        'recently_verified_email'
                    ]
                );

                /*
                 * Older accounts created before
                 * verification was removed are also
                 * activated as verified on login.
                 */
                $updateStatement =
                    db()->prepare(
                        'UPDATE users
                         SET last_login_at = NOW(),
                             is_verified = 1,
                             email_verified_at = COALESCE(
                                 email_verified_at,
                                 NOW()
                             )
                         WHERE id = ?'
                    );

                $updateStatement->execute([
                    (int) $user['id'],
                ]);

                /*
                 * If login was required during booking,
                 * return the customer to that page.
                 */
                $redirectAfterLogin =
                    (string) (
                        $_SESSION[
                            'redirect_after_login'
                        ]
                        ?? '/customer/dashboard.php'
                    );

                unset(
                    $_SESSION[
                        'redirect_after_login'
                    ]
                );

                if (
                    !str_starts_with(
                        $redirectAfterLogin,
                        '/'
                    )
                ) {
                    $redirectAfterLogin =
                        '/customer/dashboard.php';
                }

                redirect(
                    $redirectAfterLogin
                );
            }
        } catch (Throwable $exception) {
            $errors[] = APP_DEBUG
                ? 'Login failed: '
                    . $exception
                        ->getMessage()
                : 'Login failed. Please try again.';
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

    <title>
        Customer Login | <?= e(APP_NAME) ?>
    </title>

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/auth.css'
            )
        ) ?>"
    >
</head>

<body class="auth-page">

    <main class="auth-box">

        <div class="auth-logo">
            Wedding Planner
        </div>

        <div class="auth-title">
            Customer Login
        </div>

        <div class="auth-subtitle">
            Log in to manage your wedding bookings and profile.
        </div>

        <?php if ($flash): ?>

            <div
                class="alert <?= $flash['type'] === 'success'
                    ? 'alert-success'
                    : 'alert-danger' ?>"
            >
                <?= e(
                    $flash['message']
                ) ?>
            </div>

        <?php endif; ?>

        <?php if ($errors !== []): ?>

            <div class="alert alert-danger">

                <ul>

                    <?php foreach (
                        $errors as $error
                    ): ?>

                        <li>
                            <?= e($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>

        <form
            method="post"
            autocomplete="on"
        >

            <?= csrf_field() ?>

            <div class="input-box">

                <i class="fa-solid fa-envelope"></i>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($email) ?>"
                    placeholder="Enter Email Address"
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
                    margin-top:-6px;
                    margin-bottom:20px;
                    text-align:right;
                "
            >
                <a
                    href="<?= e(
                        url(
                            '/auth/forgot_password.php'
                        )
                    ) ?>"
                    style="
                        color:#ffffff;
                        font-size:14px;
                        font-weight:600;
                        text-decoration:underline;
                    "
                >
                    Forgot Password?
                </a>
            </div>

            <button
                class="auth-button"
                type="submit"
            >
                Login
            </button>

        </form>

        <div class="auth-footer">

            Do not have a customer account?<br>

            <a
                href="<?= e(
                    url(
                        '/auth/register.php'
                    )
                ) ?>"
            >
                Create Account
            </a>

            <br>

            <a
                href="<?= e(
                    url('/')
                ) ?>"
            >
                Return to Website
            </a>

        </div>

    </main>

    <script>
        document
            .querySelectorAll(
                "[data-password-target]"
            )
            .forEach(
                function (button) {
                    button.addEventListener(
                        "click",
                        function () {
                            const field =
                                document.getElementById(
                                    button.dataset
                                        .passwordTarget
                                );

                            if (!field) {
                                return;
                            }

                            const isHidden =
                                field.type
                                === "password";

                            field.type =
                                isHidden
                                    ? "text"
                                    : "password";

                            button.textContent =
                                isHidden
                                    ? "Hide"
                                    : "Show";
                        }
                    );
                }
            );
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>