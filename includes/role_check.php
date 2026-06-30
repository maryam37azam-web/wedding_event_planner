<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Return the correct dashboard for a user role.
 */
function dashboard_path_for_role(string $role): string
{
    return match ($role) {
        'admin' => '/admin/dashboard.php',

        'event_manager' =>
            '/event_manager/dashboard.php',

        'booking_manager' =>
            '/booking_manager/dashboard.php',

        'customer' =>
            '/customer/dashboard.php',

        default =>
            '/auth/customer_login.php',
    };
}

/**
 * Protect a page and allow only selected roles.
 */
function require_role(string|array $allowedRoles): void
{
    if (
        empty($_SESSION['user_id'])
        || empty($_SESSION['user_role'])
    ) {
        $_SESSION['redirect_after_login'] =
            $_SERVER['REQUEST_URI']
            ?? '/auth/staff_login.php';

        set_flash(
            'error',
            'Please log in to access this page.'
        );

        redirect('/auth/staff_login.php');
    }

    $allowedRoles = is_array($allowedRoles)
        ? $allowedRoles
        : [$allowedRoles];

    $currentRole = (string) $_SESSION['user_role'];

    if (!in_array($currentRole, $allowedRoles, true)) {
        redirect(
            dashboard_path_for_role($currentRole)
        );
    }
}