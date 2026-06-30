<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$weddingScriptName = str_replace(
    '\\',
    '/',
    (string) ($_SERVER['SCRIPT_NAME'] ?? '')
);

$weddingCurrentRole = (string) (
    $_SESSION['user_role']
    ?? ''
);

$weddingAdminUiEnabled =
    $weddingCurrentRole === 'admin'
    && str_contains(
        $weddingScriptName,
        '/admin/'
    );

$weddingEventManagerUiEnabled =
    $weddingCurrentRole === 'event_manager'
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
    $weddingCurrentRole === 'booking_manager'
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

$weddingCustomerUiEnabled =
    $weddingCurrentRole === 'customer'
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
        url('/assets/js/pwa.js')
    ) ?>"
    defer
></script>

<?php if (
    $weddingAdminUiEnabled
): ?>

    <script
        src="<?= e(
            url(
                '/assets/js/admin_consistency.js?v=20260628-1'
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
            )
        ) ?>"
        defer
    ></script>

<?php endif; ?>