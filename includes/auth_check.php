<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (
    empty($_SESSION['user_id'])
    || empty($_SESSION['user_role'])
) {
    $_SESSION['redirect_after_login'] =
        $_SERVER['REQUEST_URI']
        ?? '/customer/dashboard.php';

    set_flash(
        'error',
        'Please log in to continue.'
    );

    redirect('/auth/customer_login.php');
}