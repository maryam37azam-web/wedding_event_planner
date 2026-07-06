<?php

declare(strict_types=1);

require_once __DIR__
    . '/functions.php';

$weddingScriptName = str_replace(
    '\\',
    '/',
    (string) (
        $_SERVER[
            'SCRIPT_NAME'
        ]
        ?? ''
    )
);

$weddingCurrentRole = (string) (
    $_SESSION['user_role']
    ?? $_SESSION['role']
    ?? ''
);

$weddingAdminUiEnabled =
    $weddingCurrentRole
        === 'admin'
    && str_contains(
        $weddingScriptName,
        '/admin/'
    );

$weddingAdminPackagesPage =
    $weddingAdminUiEnabled
    && str_ends_with(
        $weddingScriptName,
        '/admin/packages.php'
    );

$weddingEventManagerUiEnabled =
    $weddingCurrentRole
        === 'event_manager'
    && (
        str_contains(
            $weddingScriptName,
            '/event_manager/'
        )
        || str_contains(
            $weddingScriptName,
            '/gallery/'
        )
    );

$weddingBookingManagerUiEnabled =
    $weddingCurrentRole
        === 'booking_manager'
    && (
        str_contains(
            $weddingScriptName,
            '/booking_manager/'
        )
        || str_contains(
            $weddingScriptName,
            '/gallery/'
        )
    );

/*
|--------------------------------------------------------------------------
| Customer checkout uses its own complete page layout.
|--------------------------------------------------------------------------
|
| customer_consistency.js looks for customer dashboard sidebars.
| The booking checkout has its own two-column interface, so that script
| must not run on customer/booking.php.
|
*/

$weddingCustomerCheckoutPage =
    str_ends_with(
        $weddingScriptName,
        '/customer/booking.php'
    );

$weddingCustomerUiEnabled =
    !$weddingCustomerCheckoutPage
    && $weddingCurrentRole
        === 'customer'
    && (
        str_contains(
            $weddingScriptName,
            '/customer/'
        )
        || str_contains(
            $weddingScriptName,
            '/gallery/'
        )
    );
?>

<script
    src="<?= e(
        url(
            '/assets/js/pwa.js'
        )
    ) ?>"
    defer
></script>

<script
    src="<?= e(
        url(
            '/assets/js/public_booking_navigation.js'
            . '?v=20260705-2'
        )
    ) ?>"
    defer
></script>

<script
    src="<?= e(
        url(
            '/assets/js/image_file_clear.js'
            . '?v=20260702-1'
        )
    ) ?>"
    defer
></script>

<script
    src="<?= e(
        url(
            '/assets/js/view_all_back_button.js'
            . '?v=20260701-1'
        )
    ) ?>"
    data-current-role="<?= e(
        $weddingCurrentRole
    ) ?>"
    defer
></script>

<?php if (
    $weddingAdminUiEnabled
): ?>

    <script
        src="<?= e(
            url(
                '/assets/js/admin_consistency.js'
                . '?v=20260703-3'
            )
        ) ?>"
        defer
    ></script>

<?php endif; ?>

<?php if (
    $weddingAdminPackagesPage
): ?>

    <script
        src="<?= e(
            url(
                '/assets/js/admin_package_menu_selector.js'
                . '?v=20260705-1'
            )
        ) ?>"
        defer
    ></script>

<?php endif; ?>

<?php if (
    $weddingEventManagerUiEnabled
): ?>

    <script
        src="<?= e(
            url(
                '/assets/js/event_manager_consistency.js'
            )
        ) ?>"
        defer
    ></script>

<?php endif; ?>

<?php if (
    $weddingBookingManagerUiEnabled
): ?>

    <script
        src="<?= e(
            url(
                '/assets/js/booking_manager_consistency.js'
            )
        ) ?>"
        defer
    ></script>

<?php endif; ?>

<?php if (
    $weddingCustomerUiEnabled
): ?>

    <script
        src="<?= e(
            url(
                '/assets/js/customer_consistency.js'
                . '?v=20260705-2'
            )
        ) ?>"
        defer
    ></script>

<?php endif; ?>