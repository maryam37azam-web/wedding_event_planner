<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('customer');

$connection = db();
$customerId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Load customer account
|--------------------------------------------------------------------------
*/

$customerStatement = $connection->prepare(
    'SELECT
        full_name,
        email,
        phone,
        profile_image
     FROM users
     WHERE id = ?
     AND role = ?
     LIMIT 1'
);

$customerStatement->execute([
    $customerId,
    'customer',
]);

$customer = $customerStatement->fetch();

if (!$customer) {
    redirect('/auth/logout.php');
}

$customerImage = !empty($customer['profile_image'])
    ? url(
        '/'
        . ltrim(
            (string) $customer['profile_image'],
            '/'
        )
    )
    : url('/assets/icons/icon-192.png');

/*
|--------------------------------------------------------------------------
| Booking statistics
|--------------------------------------------------------------------------
*/

$totalStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM bookings
     WHERE customer_id = ?'
);

$totalStatement->execute([$customerId]);

$totalBookings = (int) (
    $totalStatement->fetchColumn()
);

$pendingStatement = $connection->prepare(
    "SELECT COUNT(*)
     FROM bookings
     WHERE customer_id = ?
     AND booking_status = 'pending'"
);

$pendingStatement->execute([$customerId]);

$pendingBookings = (int) (
    $pendingStatement->fetchColumn()
);

$upcomingStatement = $connection->prepare(
    "SELECT COUNT(*)
     FROM bookings
     WHERE customer_id = ?
     AND event_date >= CURDATE()
     AND booking_status IN (
        'pending',
        'confirmed',
        'in_progress'
     )"
);

$upcomingStatement->execute([$customerId]);

$upcomingBookings = (int) (
    $upcomingStatement->fetchColumn()
);

$completedStatement = $connection->prepare(
    "SELECT COUNT(*)
     FROM bookings
     WHERE customer_id = ?
     AND booking_status = 'completed'"
);

$completedStatement->execute([$customerId]);

$completedBookings = (int) (
    $completedStatement->fetchColumn()
);

/*
|--------------------------------------------------------------------------
| Total booking value
|--------------------------------------------------------------------------
*/

$totalValueStatement = $connection->prepare(
    'SELECT COALESCE(
        SUM(total_amount),
        0
     )
     FROM bookings
     WHERE customer_id = ?
     AND booking_status <> ?'
);

$totalValueStatement->execute([
    $customerId,
    'cancelled',
]);

$totalBookingValue = (float) (
    $totalValueStatement->fetchColumn()
);

/*
|--------------------------------------------------------------------------
| Recent bookings
|--------------------------------------------------------------------------
*/

$recentBookingsStatement = $connection->prepare(
    'SELECT
        bookings.id,
        bookings.booking_code,
        bookings.event_type,
        bookings.event_date,
        bookings.event_time,
        bookings.guest_count,
        bookings.total_amount,
        bookings.booking_status,
        bookings.created_at,

        packages.name AS package_name,

        venues.name AS venue_name,
        venues.location AS venue_location

     FROM bookings

     LEFT JOIN packages
        ON packages.id = bookings.package_id

     LEFT JOIN venues
        ON venues.id = bookings.venue_id

     WHERE bookings.customer_id = ?

     ORDER BY bookings.created_at DESC

     LIMIT 5'
);

$recentBookingsStatement->execute([
    $customerId,
]);

$recentBookings =
    $recentBookingsStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Next upcoming event
|--------------------------------------------------------------------------
*/

$nextEventStatement = $connection->prepare(
    "SELECT
        bookings.id,
        bookings.booking_code,
        bookings.event_type,
        bookings.event_date,
        bookings.event_time,
        bookings.booking_status,

        packages.name AS package_name,

        venues.name AS venue_name,
        venues.location AS venue_location

     FROM bookings

     LEFT JOIN packages
        ON packages.id = bookings.package_id

     LEFT JOIN venues
        ON venues.id = bookings.venue_id

     WHERE bookings.customer_id = ?

     AND bookings.event_date >= CURDATE()

     AND bookings.booking_status IN (
        'pending',
        'confirmed',
        'in_progress'
     )

     ORDER BY
        bookings.event_date ASC,
        bookings.event_time ASC

     LIMIT 1"
);

$nextEventStatement->execute([
    $customerId,
]);

$nextEvent = $nextEventStatement->fetch();

/*
|--------------------------------------------------------------------------
| Display helpers
|--------------------------------------------------------------------------
*/

function customer_booking_status_label(
    string $status
): string {
    return match ($status) {
        'in_progress' => 'In Progress',

        default => ucwords(
            str_replace('_', ' ', $status)
        ),
    };
}

function customer_booking_status_class(
    string $status
): string {
    return match ($status) {
        'confirmed' => 'confirmed',
        'in_progress' => 'in-progress',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        default => 'pending',
    };
}

function customer_event_time(
    mixed $eventTime
): string {
    $eventTime = trim((string) $eventTime);

    if ($eventTime === '') {
        return 'Time not selected';
    }

    $timestamp = strtotime($eventTime);

    if ($timestamp === false) {
        return $eventTime;
    }

    return date('h:i A', $timestamp);
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
        Customer Dashboard | <?= e(APP_NAME) ?>
    </title>

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/customer_dashboard.css')
        ) ?>"
    >
</head>

<body class="customer-dashboard-page">

    <aside
        class="customer-sidebar"
        id="customerSidebar"
    >

        <div class="customer-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="customer-sidebar-profile">

            <img
                src="<?= e($customerImage) ?>"
                alt="Customer profile"
            >

            <h2>
                <?= e($customer['full_name']) ?>
            </h2>

            <p>Customer Account</p>

            <div class="customer-online">
                ● Online
            </div>

        </div>

        <nav class="customer-menu">

            <a
                class="active"
                href="<?= e(
                    url('/customer/dashboard.php')
                ) ?>"
            >
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a
                href="<?= e(
                    url('/customer/packages.php')
                ) ?>"
            >
                <i class="fa-solid fa-gift"></i>
                Browse Packages
            </a>

            <a
                href="<?= e(
                    url('/customer/venues.php')
                ) ?>"
            >
                <i class="fa-solid fa-hotel"></i>
                Browse Venues
            </a>

            <a
                href="<?= e(
                    url('/customer/gallery.php')
                ) ?>"
            >
                <i class="fa-solid fa-images"></i>
                Wedding Gallery
            </a>

            <a
                href="<?= e(
                    url('/customer/booking.php')
                ) ?>"
            >
                <i class="fa-solid fa-calendar-plus"></i>
                Book Event
            </a>

            <a
                href="<?= e(
                    url('/customer/my_bookings.php')
                ) ?>"
            >
                <i class="fa-solid fa-calendar-check"></i>
                My Bookings
            </a>
            <a href="<?= e(url('/customer/feedback.php')) ?>">
    <i class="fa-solid fa-star"></i>
    Feedback
</a>

            <a
                href="<?= e(
                    url('/customer/profile.php')
                ) ?>"
            >
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a
                class="customer-logout"
                href="<?= e(url('/auth/logout.php')) ?>"
            >
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </nav>

    </aside>

    <div
        class="customer-sidebar-overlay"
        id="customerSidebarOverlay"
    ></div>

    <main class="customer-main">

        <header class="customer-topbar">

            <div class="customer-topbar-left">

                <button
                    class="customer-menu-button"
                    id="customerMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="customer-heading">

                    <h1>Customer Dashboard</h1>

                    <p>
                        View your wedding bookings and plan
                        your upcoming celebration.
                    </p>

                </div>

            </div>

            <div class="customer-topbar-right">

                <div class="customer-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="customer-home-link"
                    href="<?= e(url('/index.php')) ?>"
                    aria-label="Open public website"
                >
                    <i class="fa-solid fa-globe"></i>
                </a>

                <a
                    href="<?= e(
                        url('/customer/profile.php')
                    ) ?>"
                >
                    <img
                        class="customer-profile-image"
                        src="<?= e($customerImage) ?>"
                        alt="Customer profile"
                    >
                </a>

            </div>

        </header>

        <section class="customer-welcome-banner">

            <div class="customer-welcome-content">

                <h2>
                    Welcome,
                    <?= e($customer['full_name']) ?>!
                </h2>

                <p>
                    Explore wedding packages, choose your
                    favourite venue and manage all your
                    event bookings from one place.
                </p>

            </div>

            <div class="customer-welcome-actions">

                <a
                    class="customer-start-booking"
                    href="<?= e(
                        url('/customer/booking.php')
                    ) ?>"
                >
                    Book an Event
                </a>

                <a
                    class="customer-view-packages"
                    href="<?= e(
                        url('/customer/packages.php')
                    ) ?>"
                >
                    View Packages
                </a>

            </div>

        </section>

        <section class="customer-summary">

            <article class="customer-summary-card">

                <div class="customer-summary-icon pink">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>

                <div>
                    <h4>Total Bookings</h4>

                    <h2>
                        <?= e((string) $totalBookings) ?>
                    </h2>
                </div>

            </article>

            <article class="customer-summary-card">

                <div class="customer-summary-icon orange">
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div>
                    <h4>Pending Bookings</h4>

                    <h2>
                        <?= e((string) $pendingBookings) ?>
                    </h2>
                </div>

            </article>

            <article class="customer-summary-card">

                <div class="customer-summary-icon blue">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>

                <div>
                    <h4>Upcoming Events</h4>

                    <h2>
                        <?= e((string) $upcomingBookings) ?>
                    </h2>
                </div>

            </article>

            <article class="customer-summary-card">

                <div class="customer-summary-icon green">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    <h4>Completed Events</h4>

                    <h2>
                        <?= e((string) $completedBookings) ?>
                    </h2>
                </div>

            </article>

        </section>

        <section class="customer-dashboard-grid">

            <div>

                <section class="customer-dashboard-box">

                    <div class="customer-box-heading">

                        <div>
                            <h2>Recent Bookings</h2>

                            <p>
                                Your latest wedding-event
                                booking activity.
                            </p>
                        </div>

                        <a
                            class="customer-box-link"
                            href="<?= e(
                                url('/customer/my_bookings.php')
                            ) ?>"
                        >
                            View All
                        </a>

                    </div>

                    <?php if ($recentBookings === []): ?>

                        <div class="customer-empty">

                            <i
                                class="fa-regular fa-calendar-xmark"
                            ></i>

                            <h3>No bookings created yet</h3>

                            <p>
                                Start planning your wedding
                                by selecting a package,
                                venue and event date.
                            </p>

                            <a
                                href="<?= e(
                                    url('/customer/booking.php')
                                ) ?>"
                            >
                                Create First Booking
                            </a>

                        </div>

                    <?php else: ?>

                        <div class="customer-booking-list">

                            <?php foreach (
                                $recentBookings as $booking
                            ): ?>
                                <?php
                                $eventType = trim(
                                    (string) (
                                        $booking['event_type']
                                        ?? ''
                                    )
                                );

                                if ($eventType === '') {
                                    $eventType =
                                        'Wedding Event';
                                }

                                $bookingStatus = (string) (
                                    $booking['booking_status']
                                    ?? 'pending'
                                );

                                $packageName = trim(
                                    (string) (
                                        $booking['package_name']
                                        ?? ''
                                    )
                                );

                                $venueName = trim(
                                    (string) (
                                        $booking['venue_name']
                                        ?? ''
                                    )
                                );

                                $venueLocation = trim(
                                    (string) (
                                        $booking['venue_location']
                                        ?? ''
                                    )
                                );
                                ?>

                                <article class="customer-booking-card">

                                    <div>

                                        <div class="customer-booking-top">

                                            <h3>
                                                <?= e($eventType) ?>
                                            </h3>

                                            <span
                                                class="customer-booking-code"
                                            >
                                                <?= e(
                                                    (string) $booking[
                                                        'booking_code'
                                                    ]
                                                ) ?>
                                            </span>

                                        </div>

                                        <div
                                            class="customer-booking-details"
                                        >

                                            <p>
                                                <i
                                                    class="fa-solid fa-calendar"
                                                ></i>

                                                <?= e(
                                                    date(
                                                        'd F Y',
                                                        strtotime(
                                                            (string) $booking[
                                                                'event_date'
                                                            ]
                                                        )
                                                    )
                                                ) ?>
                                            </p>

                                            <p>
                                                <i
                                                    class="fa-solid fa-clock"
                                                ></i>

                                                <?= e(
                                                    customer_event_time(
                                                        $booking[
                                                            'event_time'
                                                        ]
                                                        ?? null
                                                    )
                                                ) ?>
                                            </p>

                                            <p>
                                                <i
                                                    class="fa-solid fa-gift"
                                                ></i>

                                                <?= e(
                                                    $packageName !== ''
                                                        ? $packageName
                                                        : 'Package not selected'
                                                ) ?>
                                            </p>

                                            <p>
                                                <i
                                                    class="fa-solid fa-hotel"
                                                ></i>

                                                <?= e(
                                                    $venueName !== ''
                                                        ? $venueName
                                                            . (
                                                                $venueLocation !== ''
                                                                    ? ' — '
                                                                        . $venueLocation
                                                                    : ''
                                                            )
                                                        : 'Venue not selected'
                                                ) ?>
                                            </p>

                                            <p>
                                                <i
                                                    class="fa-solid fa-users"
                                                ></i>

                                                <?= e(
                                                    number_format(
                                                        (int) (
                                                            $booking[
                                                                'guest_count'
                                                            ]
                                                            ?? 0
                                                        )
                                                    )
                                                ) ?>
                                                guests
                                            </p>

                                        </div>

                                    </div>

                                    <div class="customer-booking-side">

                                        <span
                                            class="customer-status <?= e(
                                                customer_booking_status_class(
                                                    $bookingStatus
                                                )
                                            ) ?>"
                                        >
                                            <?= e(
                                                customer_booking_status_label(
                                                    $bookingStatus
                                                )
                                            ) ?>
                                        </span>

                                        <div
                                            class="customer-booking-amount"
                                        >
                                            Rs.
                                            <?= e(
                                                number_format(
                                                    (float) (
                                                        $booking[
                                                            'total_amount'
                                                        ]
                                                        ?? 0
                                                    ),
                                                    0
                                                )
                                            ) ?>
                                        </div>

                                        <a
                                            class="customer-booking-view"
                                            href="<?= e(
                                                url(
                                                    '/customer/my_bookings.php?booking_id='
                                                    . (int) $booking[
                                                        'id'
                                                    ]
                                                )
                                            ) ?>"
                                        >
                                            View Details
                                        </a>

                                    </div>

                                </article>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </section>

                <section class="customer-dashboard-box">

                    <div class="customer-box-heading">

                        <div>
                            <h2>Booking Value</h2>

                            <p>
                                Total value of your active
                                and completed bookings.
                            </p>
                        </div>

                    </div>

                    <div class="customer-next-event">

                        <div class="customer-next-event-date">

                            <strong>
                                <?= e(
                                    number_format(
                                        $totalBookingValue / 1000,
                                        0
                                    )
                                ) ?>
                            </strong>

                            <span>Thousand</span>

                        </div>

                        <h3>
                            Rs.
                            <?= e(
                                number_format(
                                    $totalBookingValue,
                                    0
                                )
                            ) ?>
                        </h3>

                        <p>
                            This amount includes your
                            selected package, venue and
                            additional services.
                        </p>

                    </div>

                </section>

            </div>

            <div>

                <section class="customer-dashboard-box">

                    <div class="customer-box-heading">

                        <div>
                            <h2>Next Event</h2>

                            <p>
                                Your nearest upcoming
                                wedding event.
                            </p>
                        </div>

                    </div>

                    <?php if (!$nextEvent): ?>

                        <div class="customer-empty">

                            <i
                                class="fa-regular fa-calendar"
                            ></i>

                            <h3>No upcoming event</h3>

                            <p>
                                Your next confirmed or
                                pending event will appear
                                here.
                            </p>

                            <a
                                href="<?= e(
                                    url('/customer/booking.php')
                                ) ?>"
                            >
                                Book Event
                            </a>

                        </div>

                    <?php else: ?>
                        <?php
                        $nextEventType = trim(
                            (string) (
                                $nextEvent['event_type']
                                ?? ''
                            )
                        );

                        if ($nextEventType === '') {
                            $nextEventType =
                                'Wedding Event';
                        }

                        $nextVenueName = trim(
                            (string) (
                                $nextEvent['venue_name']
                                ?? ''
                            )
                        );

                        $nextVenueLocation = trim(
                            (string) (
                                $nextEvent['venue_location']
                                ?? ''
                            )
                        );

                        $nextPackageName = trim(
                            (string) (
                                $nextEvent['package_name']
                                ?? ''
                            )
                        );

                        $nextDateTimestamp = strtotime(
                            (string) $nextEvent[
                                'event_date'
                            ]
                        );
                        ?>

                        <article class="customer-next-event">

                            <div class="customer-next-event-date">

                                <strong>
                                    <?= e(
                                        date(
                                            'd',
                                            $nextDateTimestamp
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        date(
                                            'M',
                                            $nextDateTimestamp
                                        )
                                    ) ?>
                                </span>

                            </div>

                            <h3>
                                <?= e($nextEventType) ?>
                            </h3>

                            <p>
                                <i class="fa-solid fa-clock"></i>

                                <?= e(
                                    customer_event_time(
                                        $nextEvent[
                                            'event_time'
                                        ]
                                        ?? null
                                    )
                                ) ?>
                            </p>

                            <p>
                                <i class="fa-solid fa-hotel"></i>

                                <?= e(
                                    $nextVenueName !== ''
                                        ? $nextVenueName
                                            . (
                                                $nextVenueLocation !== ''
                                                    ? ' — '
                                                        . $nextVenueLocation
                                                    : ''
                                            )
                                        : 'Venue not selected'
                                ) ?>
                            </p>

                            <p>
                                <i class="fa-solid fa-gift"></i>

                                <?= e(
                                    $nextPackageName !== ''
                                        ? $nextPackageName
                                        : 'Package not selected'
                                ) ?>
                            </p>

                            <p>
                                <i class="fa-solid fa-file-lines"></i>

                                <?= e(
                                    (string) $nextEvent[
                                        'booking_code'
                                    ]
                                ) ?>
                            </p>

                            <a
                                class="customer-next-event-button"
                                href="<?= e(
                                    url(
                                        '/customer/my_bookings.php?booking_id='
                                        . (int) $nextEvent['id']
                                    )
                                ) ?>"
                            >
                                View Booking
                            </a>

                        </article>

                    <?php endif; ?>

                </section>

                <section class="customer-dashboard-box">

                    <div class="customer-box-heading">

                        <div>
                            <h2>Quick Actions</h2>

                            <p>
                                Browse and manage your
                                wedding plan.
                            </p>
                        </div>

                    </div>

                    <div class="customer-quick-links">

                        <a
                            class="customer-quick-link"
                            href="<?= e(
                                url('/customer/packages.php')
                            ) ?>"
                        >
                            <i class="fa-solid fa-gift"></i>
                            Browse Packages
                        </a>

                        <a
                            class="customer-quick-link"
                            href="<?= e(
                                url('/customer/venues.php')
                            ) ?>"
                        >
                            <i class="fa-solid fa-hotel"></i>
                            Browse Venues
                        </a>

                        <a
                            class="customer-quick-link"
                            href="<?= e(
                                url('/customer/gallery.php')
                            ) ?>"
                        >
                            <i class="fa-solid fa-images"></i>
                            View Gallery
                        </a>

                        <a
                            class="customer-quick-link"
                            href="<?= e(
                                url('/customer/booking.php')
                            ) ?>"
                        >
                            <i
                                class="fa-solid fa-calendar-plus"
                            ></i>
                            Book Event
                        </a>

                    </div>

                </section>

            </div>

        </section>

        <footer class="customer-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <script>
        const customerSidebar =
            document.getElementById(
                "customerSidebar"
            );

        const customerSidebarOverlay =
            document.getElementById(
                "customerSidebarOverlay"
            );

        const customerMenuButton =
            document.getElementById(
                "customerMenuButton"
            );

        function closeCustomerSidebar() {
            customerSidebar.classList.remove("open");

            customerSidebarOverlay.classList.remove(
                "open"
            );
        }

        customerMenuButton.addEventListener(
            "click",
            function () {
                customerSidebar.classList.toggle(
                    "open"
                );

                customerSidebarOverlay.classList.toggle(
                    "open"
                );
            }
        );

        customerSidebarOverlay.addEventListener(
            "click",
            closeCustomerSidebar
        );
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>