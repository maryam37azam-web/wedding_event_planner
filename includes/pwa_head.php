<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$weddingSharedUi = (static function (): array {
    $scriptName = str_replace(
        '\\',
        '/',
        (string) ($_SERVER['SCRIPT_NAME'] ?? '')
    );

    $currentRole = (string) (
        $_SESSION['user_role']
        ?? ''
    );

    $isAdminArea =
        $currentRole === 'admin'
        && str_contains(
            $scriptName,
            '/admin/'
        );

    $isEventManagerArea =
        $currentRole === 'event_manager'
        && (
            str_contains(
                $scriptName,
                '/event_manager/'
            )
            || str_contains(
                $scriptName,
                '/gallery/'
            )
        );

    $isBookingManagerArea =
        $currentRole === 'booking_manager'
        && (
            str_contains(
                $scriptName,
                '/booking_manager/'
            )
            || str_contains(
                $scriptName,
                '/gallery/'
            )
        );

    $isCustomerArea =
        $currentRole === 'customer'
        && (
            str_contains(
                $scriptName,
                '/customer/'
            )
            || str_contains(
                $scriptName,
                '/gallery/'
            )
        );

    $adminAbout =
        'System Administrator';

    $eventManagerAbout =
        'Event Manager';

    $bookingManagerAbout =
        'Booking Manager';

    $customerAbout =
        'Customer Account';

    if (
        (
            $isAdminArea
            || $isEventManagerArea
            || $isBookingManagerArea
            || $isCustomerArea
        )
        && !empty($_SESSION['user_id'])
    ) {
        require_once __DIR__
            . '/../config/database.php';

        try {
            $statement = db()->prepare(
                'SELECT about
                 FROM users
                 WHERE id = ?
                 AND role = ?
                 LIMIT 1'
            );

            $statement->execute([
                (int) $_SESSION['user_id'],
                $currentRole,
            ]);

            $savedAbout = trim(
                (string) (
                    $statement->fetchColumn()
                    ?: ''
                )
            );

            if ($savedAbout !== '') {
                $savedAbout = (string) preg_replace(
                    '/\s+/u',
                    ' ',
                    $savedAbout
                );

                if ($currentRole === 'admin') {
                    $adminAbout =
                        $savedAbout;
                }

                if (
                    $currentRole
                    === 'event_manager'
                ) {
                    $eventManagerAbout =
                        $savedAbout;
                }

                if (
                    $currentRole
                    === 'booking_manager'
                ) {
                    $bookingManagerAbout =
                        $savedAbout;
                }

                if ($currentRole === 'customer') {
                    $customerAbout =
                        $savedAbout;
                }
            }
        } catch (Throwable $exception) {
            $adminAbout =
                'System Administrator';

            $eventManagerAbout =
                'Event Manager';

            $bookingManagerAbout =
                'Booking Manager';

            $customerAbout =
                'Customer Account';
        }
    }

    return [
        'admin_enabled' =>
            $isAdminArea,

        'admin_about' =>
            $adminAbout,

        'event_manager_enabled' =>
            $isEventManagerArea,

        'event_manager_about' =>
            $eventManagerAbout,

        'booking_manager_enabled' =>
            $isBookingManagerArea,

        'booking_manager_about' =>
            $bookingManagerAbout,

        'customer_enabled' =>
            $isCustomerArea,

        'customer_about' =>
            $customerAbout,
    ];
})();
?>

<meta
    name="theme-color"
    content="#a4004d"
>

<meta
    name="application-name"
    content="<?= e(APP_NAME) ?>"
>

<meta
    name="app-base-url"
    content="<?= e(APP_URL) ?>"
>

<meta
    name="mobile-web-app-capable"
    content="yes"
>

<meta
    name="apple-mobile-web-app-capable"
    content="yes"
>

<meta
    name="apple-mobile-web-app-status-bar-style"
    content="default"
>

<meta
    name="apple-mobile-web-app-title"
    content="Wedding Planner"
>

<link
    rel="manifest"
    href="<?= e(
        url('/manifest.json')
    ) ?>"
>

<link
    rel="icon"
    type="image/png"
    sizes="192x192"
    href="<?= e(
        url('/assets/icons/icon-192.png')
    ) ?>"
>

<link
    rel="icon"
    type="image/png"
    sizes="512x512"
    href="<?= e(
        url('/assets/icons/icon-512.png')
    ) ?>"
>

<link
    rel="apple-touch-icon"
    sizes="180x180"
    href="<?= e(
        url(
            '/assets/icons/apple-touch-icon.png'
        )
    ) ?>"
>

<?php if (
    $weddingSharedUi['admin_enabled']
): ?>

    <meta
        name="admin-sidebar-about"
        content="<?= e(
            $weddingSharedUi['admin_about']
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/admin_consistency.css?v=20260628-1'
            )
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/admin_venues_consistency.css'
            )
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/admin_listing_grid_fix.css'
            )
        ) ?>"
    >

<?php endif; ?>

<?php if (
    $weddingSharedUi[
        'event_manager_enabled'
    ]
): ?>

    <meta
        name="event-manager-sidebar-about"
        content="<?= e(
            $weddingSharedUi[
                'event_manager_about'
            ]
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/event_manager_consistency.css'
            )
        ) ?>"
    >

<?php endif; ?>

<?php if (
    $weddingSharedUi[
        'booking_manager_enabled'
    ]
): ?>

    <meta
        name="booking-manager-sidebar-about"
        content="<?= e(
            $weddingSharedUi[
                'booking_manager_about'
            ]
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/booking_manager_consistency.css'
            )
        ) ?>"
    >

<?php endif; ?>

<?php if (
    $weddingSharedUi[
        'customer_enabled'
    ]
): ?>

    <meta
        name="customer-sidebar-about"
        content="<?= e(
            $weddingSharedUi[
                'customer_about'
            ]
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/customer_consistency.css'
            )
        ) ?>"
    >

<?php endif; ?>