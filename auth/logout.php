<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$previousRole = (string) (
    $_SESSION['user_role'] ?? ''
);

$wasStaff = in_array(
    $previousRole,
    [
        'admin',
        'event_manager',
        'booking_manager',
    ],
    true
);

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookieParameters =
        session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookieParameters['path'],
        $cookieParameters['domain'],
        (bool) $cookieParameters['secure'],
        (bool) $cookieParameters['httponly']
    );
}

session_destroy();

session_start();

set_flash(
    'success',
    'You have been logged out successfully.'
);

if ($wasStaff) {
    redirect('/auth/staff_login.php');
}

redirect('/auth/customer_login.php');