<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('booking_manager');

$connection = db();
$bookingManagerId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Load Booking Manager account
|--------------------------------------------------------------------------
*/

$managerStatement = $connection->prepare(
    'SELECT
        full_name,
        email,
        profile_image
     FROM users
     WHERE id = ?
     AND role = ?
     LIMIT 1'
);

$managerStatement->execute([
    $bookingManagerId,
    'booking_manager',
]);

$manager = $managerStatement->fetch();

if (!$manager) {
    redirect('/auth/logout.php');
}

$managerImage = !empty($manager['profile_image'])
    ? url(
        '/'
        . ltrim(
            (string) $manager['profile_image'],
            '/'
        )
    )
    : url('/assets/icons/icon-192.png');

/*
|--------------------------------------------------------------------------
| Notification count
|--------------------------------------------------------------------------
*/

$notificationStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE recipient_id = ?
     AND is_read = 0'
);

$notificationStatement->execute([
    $bookingManagerId,
]);

$unreadNotifications = (int) (
    $notificationStatement->fetchColumn()
);

/*
|--------------------------------------------------------------------------
| Summary statistics
|--------------------------------------------------------------------------
*/

$totalBookings = (int) $connection
    ->query(
        'SELECT COUNT(*)
         FROM bookings'
    )
    ->fetchColumn();

$pendingBookings = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM bookings
         WHERE booking_status = 'pending'"
    )
    ->fetchColumn();

$confirmedBookings = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM bookings
         WHERE booking_status = 'confirmed'"
    )
    ->fetchColumn();

$completedBookings = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM bookings
         WHERE booking_status = 'completed'"
    )
    ->fetchColumn();

/*
|--------------------------------------------------------------------------
| Recent bookings
|--------------------------------------------------------------------------
*/

$recentBookingStatement = $connection->query(
    'SELECT
        bookings.id,
        bookings.booking_code,
        bookings.event_type,
        bookings.event_date,
        bookings.booking_status,
        bookings.created_at,

        users.full_name AS customer_name,
        users.email AS customer_email,
        users.phone AS customer_phone,

        packages.name AS package_name,

        venues.name AS venue_name,
        venues.location AS venue_location,

        (
            SELECT payments.payment_status
            FROM payments
            WHERE payments.booking_id = bookings.id
            ORDER BY payments.id DESC
            LIMIT 1
        ) AS payment_status,

        (
            SELECT payments.amount
            FROM payments
            WHERE payments.booking_id = bookings.id
            ORDER BY payments.id DESC
            LIMIT 1
        ) AS payment_amount

     FROM bookings

     INNER JOIN users
        ON users.id = bookings.customer_id

     LEFT JOIN packages
        ON packages.id = bookings.package_id

     LEFT JOIN venues
        ON venues.id = bookings.venue_id

     ORDER BY bookings.created_at DESC

     LIMIT 5'
);

$recentBookings =
    $recentBookingStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Pending customer booking requests
|--------------------------------------------------------------------------
*/

$requestStatement = $connection->query(
    "SELECT
        bookings.id,
        bookings.booking_code,
        bookings.event_type,
        bookings.event_date,
        bookings.created_at,
        users.full_name AS customer_name

     FROM bookings

     INNER JOIN users
        ON users.id = bookings.customer_id

     WHERE bookings.booking_status = 'pending'

     ORDER BY bookings.created_at DESC

     LIMIT 3"
);

$customerRequests =
    $requestStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Upcoming events
|--------------------------------------------------------------------------
*/

$upcomingStatement = $connection->query(
    "SELECT
        bookings.event_type,
        bookings.event_date,
        users.full_name AS customer_name

     FROM bookings

     INNER JOIN users
        ON users.id = bookings.customer_id

     WHERE bookings.event_date >= CURDATE()

     AND bookings.booking_status NOT IN (
        'completed',
        'cancelled'
     )

     ORDER BY bookings.event_date ASC

     LIMIT 4"
);

$upcomingEvents =
    $upcomingStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Recent payment statuses
|--------------------------------------------------------------------------
*/

$paymentStatement = $connection->query(
    'SELECT
        bookings.booking_code,
        users.full_name AS customer_name,

        (
            SELECT payments.payment_status
            FROM payments
            WHERE payments.booking_id = bookings.id
            ORDER BY payments.id DESC
            LIMIT 1
        ) AS payment_status

     FROM bookings

     INNER JOIN users
        ON users.id = bookings.customer_id

     ORDER BY bookings.created_at DESC

     LIMIT 4'
);

$paymentStatuses =
    $paymentStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Display helpers
|--------------------------------------------------------------------------
*/

function booking_manager_status_label(
    string $status
): string {
    return match ($status) {
        'in_progress' => 'In Progress',

        default => ucwords(
            str_replace('_', ' ', $status)
        ),
    };
}

function booking_manager_status_class(
    string $status
): string {
    return match ($status) {
        'confirmed' => 'confirmed',
        'completed' => 'completed',
        'in_progress' => 'in-progress',
        'cancelled' => 'cancelled',
        default => 'pending',
    };
}

function booking_manager_payment_label(
    ?string $status
): string {
    return match ((string) $status) {
        'successful',
        'paid',
        'completed' => 'Paid',

        'failed',
        'cancelled' => 'Failed',

        default => 'Pending',
    };
}

function booking_manager_payment_class(
    ?string $status
): string {
    return match ((string) $status) {
        'successful',
        'paid',
        'completed' => 'paid',

        'failed',
        'cancelled' => 'failed',

        default => 'pending',
    };
}

$currentYear = date('Y');
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
        Booking Manager Dashboard | <?= e(APP_NAME) ?>
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
                '/assets/css/booking_manager_dashboard.css'
            )
        ) ?>"
    >
</head>

<body class="booking-manager-page">

    <aside
        class="booking-sidebar"
        id="bookingSidebar"
    >

        <div class="booking-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="booking-sidebar-profile">

            <img
                src="<?= e($managerImage) ?>"
                alt="Booking Manager profile"
            >

            <h2><?= e($manager['full_name']) ?></h2>

            <p>Booking Manager</p>

            <div class="booking-online">
                ● Online
            </div>

        </div>

        <nav class="booking-menu">

            <a
                class="active"
                href="<?= e(
                    url('/booking_manager/dashboard.php')
                ) ?>"
            >
                <i class="fa-solid fa-gauge"></i>
                Dashboard
            </a>

            <a
                href="<?= e(
                    url('/booking_manager/bookings.php')
                ) ?>"
            >
                <i class="fa-solid fa-calendar-check"></i>
                Manage Bookings
            </a>

            <a
                href="<?= e(
                    url('/booking_manager/booking.php')
                ) ?>"
            >
                <i class="fa-solid fa-calendar-plus"></i>
                Create Booking
            </a>

            <a
                href="<?= e(
                    url('/booking_manager/services.php')
                ) ?>"
            >
                <i class="fa-solid fa-bell-concierge"></i>
                View Services
            </a>

            <a
                href="<?= e(
                    url('/booking_manager/gallery.php')
                ) ?>"
            >
                <i class="fa-solid fa-images"></i>
                View Gallery
            </a>

            <a
                href="<?= e(
                    url('/booking_manager/packages.php')
                ) ?>"
            >
                <i class="fa-solid fa-gift"></i>
                View Packages
            </a>

            <a
                href="<?= e(
                    url('/booking_manager/venues.php')
                ) ?>"
            >
                <i class="fa-solid fa-hotel"></i>
                View Venues
            </a>

            <a
                href="<?= e(
                    url('/booking_manager/profile.php')
                ) ?>"
            >
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a
                href="<?= e(
                    url(
                        '/booking_manager/notifications.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-bell"></i>
                View Notifications
            </a>

            <a
                class="booking-logout"
                href="<?= e(
                    url('/auth/logout.php')
                ) ?>"
            >
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </nav>

    </aside>

    <div
        class="booking-sidebar-overlay"
        id="bookingSidebarOverlay"
    ></div>

    <main class="booking-main">

        <header class="booking-topbar">

            <div class="booking-topbar-left">

                <button
                    class="booking-menu-button"
                    id="bookingMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="booking-heading">

                    <h1>Booking Manager Dashboard</h1>

                    <p>
                        Review customer bookings, requests,
                        events and payment information.
                    </p>

                </div>

            </div>

            <div class="booking-topbar-right">

                <div class="booking-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="booking-notification"
                    href="<?= e(
                        url(
                            '/booking_manager/notifications.php'
                        )
                    ) ?>"
                    aria-label="Open notifications"
                >
                    <i class="fa-solid fa-bell"></i>

                    <?php if ($unreadNotifications > 0): ?>
                        <span>
                            <?= e(
                                $unreadNotifications > 99
                                    ? '99+'
                                    : (string) $unreadNotifications
                            ) ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a
                    href="<?= e(
                        url('/booking_manager/profile.php')
                    ) ?>"
                >
                    <img
                        class="booking-profile-image"
                        src="<?= e($managerImage) ?>"
                        alt="Booking Manager profile"
                    >
                </a>

            </div>

        </header>

        <section class="booking-summary">

            <article class="booking-summary-card">

                <div>
                    <h3>Total Bookings</h3>
                    <h2><?= e((string) $totalBookings) ?></h2>
                </div>

                <i class="fa-solid fa-calendar-days"></i>

            </article>

            <article class="booking-summary-card">

                <div>
                    <h3>Pending</h3>
                    <h2><?= e((string) $pendingBookings) ?></h2>
                </div>

                <i class="fa-solid fa-clock"></i>

            </article>

            <article class="booking-summary-card">

                <div>
                    <h3>Confirmed</h3>
                    <h2><?= e((string) $confirmedBookings) ?></h2>
                </div>

                <i class="fa-solid fa-circle-check"></i>

            </article>

            <article class="booking-summary-card">

                <div>
                    <h3>Completed</h3>
                    <h2><?= e((string) $completedBookings) ?></h2>
                </div>

                <i class="fa-solid fa-award"></i>

            </article>

        </section>

        <section class="booking-dashboard-grid">

            <div>

                <section class="booking-box">

                    <div class="booking-box-heading">
                        <h2>Recent Bookings</h2>

                        <span>
                            Latest
                            <?= e(
                                (string) count($recentBookings)
                            ) ?>
                        </span>
                    </div>

                    <?php if ($recentBookings === []): ?>

                        <div class="booking-empty">

                            <i
                                class="fa-regular fa-calendar-xmark"
                            ></i>

                            <h3>No bookings found</h3>

                            <p>
                                Recent customer bookings will
                                appear here automatically.
                            </p>

                        </div>

                    <?php else: ?>

                        <div class="booking-table-wrapper">

                            <table class="booking-table">

                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach (
                                        $recentBookings as $booking
                                    ): ?>
                                        <?php
                                        $eventType = trim(
                                            (string) (
                                                $booking[
                                                    'event_type'
                                                ]
                                                ?? ''
                                            )
                                        );

                                        if ($eventType === '') {
                                            $eventType =
                                                'Wedding Event';
                                        }

                                        $status = (string) (
                                            $booking[
                                                'booking_status'
                                            ]
                                            ?? 'pending'
                                        );

                                        $packageName = trim(
                                            (string) (
                                                $booking[
                                                    'package_name'
                                                ]
                                                ?? ''
                                            )
                                        );

                                        $venueName = trim(
                                            (string) (
                                                $booking[
                                                    'venue_name'
                                                ]
                                                ?? ''
                                            )
                                        );

                                        $venueLocation = trim(
                                            (string) (
                                                $booking[
                                                    'venue_location'
                                                ]
                                                ?? ''
                                            )
                                        );

                                        $paymentStatus =
                                            booking_manager_payment_label(
                                                $booking[
                                                    'payment_status'
                                                ]
                                                ?? null
                                            );

                                        $paymentAmount =
                                            isset(
                                                $booking[
                                                    'payment_amount'
                                                ]
                                            )
                                            && $booking[
                                                'payment_amount'
                                            ] !== null
                                                ? 'Rs. '
                                                    . number_format(
                                                        (float) $booking[
                                                            'payment_amount'
                                                        ],
                                                        0
                                                    )
                                                : 'Not recorded';
                                        ?>

                                        <tr>

                                            <td>
                                                <div
                                                    class="booking-customer-name"
                                                >
                                                    <?= e(
                                                        (string) $booking[
                                                            'customer_name'
                                                        ]
                                                    ) ?>
                                                </div>

                                                <div
                                                    class="booking-code"
                                                >
                                                    <?= e(
                                                        (string) $booking[
                                                            'booking_code'
                                                        ]
                                                    ) ?>
                                                </div>
                                            </td>

                                            <td><?= e($eventType) ?></td>

                                            <td>
                                                <?= e(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            (string) $booking[
                                                                'event_date'
                                                            ]
                                                        )
                                                    )
                                                ) ?>
                                            </td>

                                            <td>
                                                <span
                                                    class="booking-status <?= e(
                                                        booking_manager_status_class(
                                                            $status
                                                        )
                                                    ) ?>"
                                                >
                                                    <?= e(
                                                        booking_manager_status_label(
                                                            $status
                                                        )
                                                    ) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <button
                                                    class="booking-view-button"
                                                    type="button"

                                                    data-booking-modal

                                                    data-code="<?= e(
                                                        (string) $booking[
                                                            'booking_code'
                                                        ]
                                                    ) ?>"

                                                    data-customer="<?= e(
                                                        (string) $booking[
                                                            'customer_name'
                                                        ]
                                                    ) ?>"

                                                    data-email="<?= e(
                                                        (string) $booking[
                                                            'customer_email'
                                                        ]
                                                    ) ?>"

                                                    data-phone="<?= e(
                                                        (string) (
                                                            $booking[
                                                                'customer_phone'
                                                            ]
                                                            ?: 'Not provided'
                                                        )
                                                    ) ?>"

                                                    data-event="<?= e(
                                                        $eventType
                                                    ) ?>"

                                                    data-date="<?= e(
                                                        date(
                                                            'd F Y',
                                                            strtotime(
                                                                (string) $booking[
                                                                    'event_date'
                                                                ]
                                                            )
                                                        )
                                                    ) ?>"

                                                    data-package="<?= e(
                                                        $packageName !== ''
                                                            ? $packageName
                                                            : 'Not selected'
                                                    ) ?>"

                                                    data-venue="<?= e(
                                                        $venueName !== ''
                                                            ? $venueName
                                                                . (
                                                                    $venueLocation !== ''
                                                                        ? ' — '
                                                                            . $venueLocation
                                                                        : ''
                                                                )
                                                            : 'Not selected'
                                                    ) ?>"

                                                    data-status="<?= e(
                                                        booking_manager_status_label(
                                                            $status
                                                        )
                                                    ) ?>"

                                                    data-payment="<?= e(
                                                        $paymentStatus
                                                        . ' — '
                                                        . $paymentAmount
                                                    ) ?>"
                                                >
                                                    View
                                                </button>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </section>

                <section class="booking-box">

                    <div class="booking-box-heading">
                        <h2>Customer Requests</h2>

                        <span>
                            Pending booking requests
                        </span>
                    </div>

                    <?php if ($customerRequests === []): ?>

                        <div class="booking-empty">

                            <i
                                class="fa-regular fa-comments"
                            ></i>

                            <h3>No pending requests</h3>

                            <p>
                                New pending customer bookings
                                will appear here for review.
                            </p>

                        </div>

                    <?php else: ?>

                        <?php foreach (
                            $customerRequests as $request
                        ): ?>
                            <?php
                            $requestEvent = trim(
                                (string) (
                                    $request['event_type']
                                    ?? ''
                                )
                            );

                            if ($requestEvent === '') {
                                $requestEvent =
                                    'Wedding Event';
                            }
                            ?>

                            <article class="booking-request">

                                <h4>New Booking Request</h4>

                                <p>
                                    <?= e(
                                        (string) $request[
                                            'customer_name'
                                        ]
                                    ) ?>

                                    requested a

                                    <?= e($requestEvent) ?>

                                    booking for

                                    <?= e(
                                        date(
                                            'd F Y',
                                            strtotime(
                                                (string) $request[
                                                    'event_date'
                                                ]
                                            )
                                        )
                                    ) ?>.
                                </p>

                                <div
                                    class="booking-request-meta"
                                >
                                    Reference:
                                    <?= e(
                                        (string) $request[
                                            'booking_code'
                                        ]
                                    ) ?>
                                </div>

                                <button
                                    class="booking-request-button"
                                    type="button"

                                    data-booking-modal

                                    data-code="<?= e(
                                        (string) $request[
                                            'booking_code'
                                        ]
                                    ) ?>"

                                    data-customer="<?= e(
                                        (string) $request[
                                            'customer_name'
                                        ]
                                    ) ?>"

                                    data-email="Open Recent Bookings for full customer details"

                                    data-phone="Not shown in request summary"

                                    data-event="<?= e(
                                        $requestEvent
                                    ) ?>"

                                    data-date="<?= e(
                                        date(
                                            'd F Y',
                                            strtotime(
                                                (string) $request[
                                                    'event_date'
                                                ]
                                            )
                                        )
                                    ) ?>"

                                    data-package="Review booking details"

                                    data-venue="Review booking details"

                                    data-status="Pending"

                                    data-payment="Pending"
                                >
                                    View Request
                                </button>

                            </article>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </section>

            </div>

            <div>

                <section class="booking-box">

                    <div class="booking-box-heading">
                        <h2>Upcoming Events</h2>
                    </div>

                    <?php if ($upcomingEvents === []): ?>

                        <div class="booking-empty">

                            <i
                                class="fa-regular fa-calendar"
                            ></i>

                            <h3>No upcoming events</h3>

                            <p>
                                Upcoming confirmed and pending
                                events will appear here.
                            </p>

                        </div>

                    <?php else: ?>

                        <?php foreach (
                            $upcomingEvents as $event
                        ): ?>
                            <?php
                            $upcomingEventType = trim(
                                (string) (
                                    $event['event_type']
                                    ?? ''
                                )
                            );

                            if ($upcomingEventType === '') {
                                $upcomingEventType =
                                    'Wedding Event';
                            }
                            ?>

                            <div class="booking-event-row">

                                <div>
                                    <h4>
                                        <?= e(
                                            (string) $event[
                                                'customer_name'
                                            ]
                                        ) ?>

                                        —
                                        <?= e($upcomingEventType) ?>
                                    </h4>

                                    <span>
                                        <?= e(
                                            date(
                                                'd F Y',
                                                strtotime(
                                                    (string) $event[
                                                        'event_date'
                                                    ]
                                                )
                                            )
                                        ) ?>
                                    </span>
                                </div>

                                <div class="booking-event-icon">
                                    <i
                                        class="fa-solid fa-heart"
                                    ></i>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </section>

                <section class="booking-box">

                    <div class="booking-box-heading">
                        <h2>Payment Status</h2>
                    </div>

                    <?php if ($paymentStatuses === []): ?>

                        <div class="booking-empty">

                            <i
                                class="fa-solid fa-credit-card"
                            ></i>

                            <h3>No payment records</h3>

                            <p>
                                Booking payment statuses will
                                appear here.
                            </p>

                        </div>

                    <?php else: ?>

                        <?php foreach (
                            $paymentStatuses as $payment
                        ): ?>
                            <?php
                            $paymentLabel =
                                booking_manager_payment_label(
                                    $payment[
                                        'payment_status'
                                    ]
                                    ?? null
                                );

                            $paymentClass =
                                booking_manager_payment_class(
                                    $payment[
                                        'payment_status'
                                    ]
                                    ?? null
                                );
                            ?>

                            <div class="booking-payment-row">

                                <div>
                                    <div
                                        class="booking-payment-customer"
                                    >
                                        <?= e(
                                            (string) $payment[
                                                'customer_name'
                                            ]
                                        ) ?>
                                    </div>

                                    <div class="booking-payment-code">
                                        <?= e(
                                            (string) $payment[
                                                'booking_code'
                                            ]
                                        ) ?>
                                    </div>
                                </div>

                                <span
                                    class="payment-status <?= e(
                                        $paymentClass
                                    ) ?>"
                                >
                                    <?= e($paymentLabel) ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </section>

            </div>

        </section>

        <footer class="booking-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <div
        class="booking-modal"
        id="bookingModal"
    >

        <div class="booking-modal-content">

            <button
                class="booking-modal-close"
                id="bookingModalClose"
                type="button"
                aria-label="Close booking details"
            >
                &times;
            </button>

            <h2>Booking Details</h2>

            <div
                class="booking-modal-reference"
                id="modalBookingCode"
            ></div>

            <div class="booking-modal-grid">

                <div class="booking-modal-detail">
                    <strong>Customer</strong>
                    <span id="modalCustomer"></span>
                </div>

                <div class="booking-modal-detail">
                    <strong>Event Type</strong>
                    <span id="modalEvent"></span>
                </div>

                <div class="booking-modal-detail">
                    <strong>Email</strong>
                    <span id="modalEmail"></span>
                </div>

                <div class="booking-modal-detail">
                    <strong>Phone</strong>
                    <span id="modalPhone"></span>
                </div>

                <div class="booking-modal-detail">
                    <strong>Event Date</strong>
                    <span id="modalDate"></span>
                </div>

                <div class="booking-modal-detail">
                    <strong>Booking Status</strong>
                    <span id="modalStatus"></span>
                </div>

                <div
                    class="booking-modal-detail full-width"
                >
                    <strong>Selected Package</strong>
                    <span id="modalPackage"></span>
                </div>

                <div
                    class="booking-modal-detail full-width"
                >
                    <strong>Selected Venue</strong>
                    <span id="modalVenue"></span>
                </div>

                <div
                    class="booking-modal-detail full-width"
                >
                    <strong>Payment</strong>
                    <span id="modalPayment"></span>
                </div>

            </div>

        </div>

    </div>

    <script>
        const bookingSidebar =
            document.getElementById("bookingSidebar");

        const bookingSidebarOverlay =
            document.getElementById(
                "bookingSidebarOverlay"
            );

        const bookingMenuButton =
            document.getElementById(
                "bookingMenuButton"
            );

        function closeBookingSidebar() {
            bookingSidebar.classList.remove("open");

            bookingSidebarOverlay.classList.remove(
                "open"
            );
        }

        bookingMenuButton.addEventListener(
            "click",
            function () {
                bookingSidebar.classList.toggle("open");

                bookingSidebarOverlay.classList.toggle(
                    "open"
                );
            }
        );

        bookingSidebarOverlay.addEventListener(
            "click",
            closeBookingSidebar
        );

        const bookingModal =
            document.getElementById("bookingModal");

        const bookingModalClose =
            document.getElementById(
                "bookingModalClose"
            );

        const modalFields = {
            code: document.getElementById(
                "modalBookingCode"
            ),

            customer: document.getElementById(
                "modalCustomer"
            ),

            email: document.getElementById(
                "modalEmail"
            ),

            phone: document.getElementById(
                "modalPhone"
            ),

            event: document.getElementById(
                "modalEvent"
            ),

            date: document.getElementById(
                "modalDate"
            ),

            package: document.getElementById(
                "modalPackage"
            ),

            venue: document.getElementById(
                "modalVenue"
            ),

            status: document.getElementById(
                "modalStatus"
            ),

            payment: document.getElementById(
                "modalPayment"
            )
        };

        document
            .querySelectorAll("[data-booking-modal]")
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        modalFields.code.textContent =
                            button.dataset.code;

                        modalFields.customer.textContent =
                            button.dataset.customer;

                        modalFields.email.textContent =
                            button.dataset.email;

                        modalFields.phone.textContent =
                            button.dataset.phone;

                        modalFields.event.textContent =
                            button.dataset.event;

                        modalFields.date.textContent =
                            button.dataset.date;

                        modalFields.package.textContent =
                            button.dataset.package;

                        modalFields.venue.textContent =
                            button.dataset.venue;

                        modalFields.status.textContent =
                            button.dataset.status;

                        modalFields.payment.textContent =
                            button.dataset.payment;

                        bookingModal.classList.add("open");
                    }
                );
            });

        function closeBookingModal() {
            bookingModal.classList.remove("open");
        }

        bookingModalClose.addEventListener(
            "click",
            closeBookingModal
        );

        bookingModal.addEventListener(
            "click",
            function (event) {
                if (event.target === bookingModal) {
                    closeBookingModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (event.key === "Escape") {
                    closeBookingModal();
                }
            }
        );
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>