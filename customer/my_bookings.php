<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('customer');

$connection = db();
$customerId = (int) $_SESSION['user_id'];
$errors = [];
$flash = get_flash();

/*
|--------------------------------------------------------------------------
| Load logged-in customer
|--------------------------------------------------------------------------
*/

$customerStatement = $connection->prepare(
    "SELECT
        full_name,
        email,
        profile_image
     FROM users
     WHERE id = ?
     AND role = 'customer'
     LIMIT 1"
);

$customerStatement->execute([
    $customerId,
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
| Cancel a pending booking
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $action = trim(
        (string) ($_POST['action'] ?? '')
    );

    $bookingId = max(
        0,
        (int) ($_POST['booking_id'] ?? 0)
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

    if ($action !== 'cancel_booking') {
        $errors[] =
            'Invalid booking action.';
    }

    if ($bookingId < 1) {
        $errors[] =
            'Select a valid booking.';
    }

    if ($errors === []) {
        $bookingCheckStatement =
            $connection->prepare(
                "SELECT
                    id,
                    booking_code,
                    booking_status
                 FROM bookings
                 WHERE id = ?
                 AND customer_id = ?
                 LIMIT 1"
            );

        $bookingCheckStatement->execute([
            $bookingId,
            $customerId,
        ]);

        $bookingToCancel =
            $bookingCheckStatement->fetch();

        if (!$bookingToCancel) {
            $errors[] =
                'The selected booking was not found.';
        } elseif (
            (string) $bookingToCancel[
                'booking_status'
            ] !== 'pending'
        ) {
            $errors[] =
                'Only a pending booking can be cancelled by the customer.';
        } else {
            try {
                $cancelStatement =
                    $connection->prepare(
                        "UPDATE bookings
                         SET booking_status = 'cancelled'
                         WHERE id = ?
                         AND customer_id = ?
                         AND booking_status = 'pending'"
                    );

                $cancelStatement->execute([
                    $bookingId,
                    $customerId,
                ]);

                if (
                    $cancelStatement->rowCount()
                    !== 1
                ) {
                    $errors[] =
                        'The booking could not be cancelled because its status changed. Refresh the page and try again.';
                } else {
                    set_flash(
                        'success',
                        'Booking '
                        . (string) $bookingToCancel[
                            'booking_code'
                        ]
                        . ' was cancelled successfully.'
                    );

                    redirect(
                        '/customer/my_bookings.php?booking_id='
                        . $bookingId
                    );
                }
            } catch (Throwable $exception) {
                $errors[] = APP_DEBUG
                    ? 'Booking could not be cancelled: '
                        . $exception->getMessage()
                    : 'Booking could not be cancelled. Please try again.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Search and status filters
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'all',
    'pending',
    'confirmed',
    'in_progress',
    'completed',
    'cancelled',
];

$statusFilter = strtolower(
    trim(
        (string) ($_GET['status'] ?? 'all')
    )
);

if (
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {
    $statusFilter = 'all';
}

$search = trim(
    (string) ($_GET['search'] ?? '')
);

if (mb_strlen($search) > 100) {
    $search = mb_substr(
        $search,
        0,
        100
    );
}

$requestedBookingId = max(
    0,
    (int) ($_GET['booking_id'] ?? 0)
);

/*
|--------------------------------------------------------------------------
| Booking statistics
|--------------------------------------------------------------------------
*/

$statisticsStatement =
    $connection->prepare(
        "SELECT
            COUNT(*) AS total_bookings,

            COALESCE(
                SUM(
                    booking_status = 'pending'
                ),
                0
            ) AS pending_bookings,

            COALESCE(
                SUM(
                    booking_status = 'confirmed'
                ),
                0
            ) AS confirmed_bookings,

            COALESCE(
                SUM(
                    booking_status = 'completed'
                ),
                0
            ) AS completed_bookings

         FROM bookings

         WHERE customer_id = ?"
    );

$statisticsStatement->execute([
    $customerId,
]);

$statistics =
    $statisticsStatement->fetch();

$totalBookings = (int) (
    $statistics['total_bookings'] ?? 0
);

$pendingBookings = (int) (
    $statistics['pending_bookings'] ?? 0
);

$confirmedBookings = (int) (
    $statistics['confirmed_bookings'] ?? 0
);

$completedBookings = (int) (
    $statistics['completed_bookings'] ?? 0
);

/*
|--------------------------------------------------------------------------
| Load bookings
|--------------------------------------------------------------------------
*/

$bookingQuery =
    "SELECT
        bookings.id,
        bookings.booking_code,
        bookings.event_type,
        bookings.event_date,
        bookings.event_time,
        bookings.guest_count,
        bookings.customer_address,
        bookings.special_instructions,
        bookings.subtotal,
        bookings.total_amount,
        bookings.booking_status,
        bookings.created_at,

        packages.name AS package_name,

        venues.name AS venue_name,
        venues.location AS venue_location

     FROM bookings

     LEFT JOIN packages
        ON packages.id =
            bookings.package_id

     LEFT JOIN venues
        ON venues.id =
            bookings.venue_id

     WHERE bookings.customer_id = ?";

$bookingParameters = [
    $customerId,
];

if ($statusFilter !== 'all') {
    $bookingQuery .=
        ' AND bookings.booking_status = ?';

    $bookingParameters[] =
        $statusFilter;
}

if ($search !== '') {
    $bookingQuery .=
        " AND (
            bookings.booking_code LIKE ?
            OR bookings.event_type LIKE ?
            OR packages.name LIKE ?
            OR venues.name LIKE ?
            OR venues.location LIKE ?
        )";

    $searchValue =
        '%' . $search . '%';

    for (
        $index = 0;
        $index < 5;
        $index++
    ) {
        $bookingParameters[] =
            $searchValue;
    }
}

$bookingQuery .=
    " ORDER BY
        (
            bookings.event_date >= CURDATE()
            AND bookings.booking_status IN (
                'pending',
                'confirmed',
                'in_progress'
            )
        ) DESC,

        CASE
            WHEN bookings.event_date >= CURDATE()
            THEN bookings.event_date
        END ASC,

        bookings.created_at DESC";

$bookingStatement =
    $connection->prepare(
        $bookingQuery
    );

$bookingStatement->execute(
    $bookingParameters
);

$bookings =
    $bookingStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Load services for displayed bookings
|--------------------------------------------------------------------------
*/

$servicesByBooking = [];

$bookingIds = array_map(
    static fn (array $booking): int =>
        (int) $booking['id'],
    $bookings
);

if ($bookingIds !== []) {
    $bookingPlaceholders = implode(
        ',',
        array_fill(
            0,
            count($bookingIds),
            '?'
        )
    );

    $servicesStatement =
        $connection->prepare(
            "SELECT
                booking_services.booking_id,
                booking_services.quantity,
                booking_services.price,
                services.name

             FROM booking_services

             LEFT JOIN services
                ON services.id =
                    booking_services.service_id

             WHERE booking_services.booking_id
             IN ($bookingPlaceholders)

             ORDER BY
                booking_services.booking_id ASC,
                services.name ASC"
        );

    $servicesStatement->execute(
        $bookingIds
    );

    $bookingServices =
        $servicesStatement->fetchAll();

    foreach (
        $bookingServices as $service
    ) {
        $bookingId =
            (int) $service['booking_id'];

        if (
            !isset(
                $servicesByBooking[
                    $bookingId
                ]
            )
        ) {
            $servicesByBooking[
                $bookingId
            ] = [];
        }

        $servicesByBooking[
            $bookingId
        ][] = [
            'name' =>
                trim(
                    (string) (
                        $service['name']
                        ?? 'Additional Service'
                    )
                ),

            'quantity' =>
                max(
                    1,
                    (int) (
                        $service['quantity']
                        ?? 1
                    )
                ),

            'price' =>
                (float) (
                    $service['price']
                    ?? 0
                ),
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Display helpers
|--------------------------------------------------------------------------
*/

function my_booking_status_label(
    string $status
): string {
    return $status === 'in_progress'
        ? 'In Progress'
        : ucwords(
            str_replace(
                '_',
                ' ',
                $status
            )
        );
}

function my_booking_status_class(
    string $status
): string {
    return match ($status) {
        'confirmed' =>
            'confirmed',

        'in_progress' =>
            'in-progress',

        'completed' =>
            'completed',

        'cancelled' =>
            'cancelled',

        default =>
            'pending',
    };
}

function my_booking_time(
    mixed $time
): string {
    $time = trim(
        (string) $time
    );

    if ($time === '') {
        return 'Not selected';
    }

    $timestamp =
        strtotime($time);

    return $timestamp === false
        ? $time
        : date(
            'h:i A',
            $timestamp
        );
}

function my_booking_date(
    mixed $date
): string {
    $timestamp =
        strtotime(
            (string) $date
        );

    return $timestamp === false
        ? 'Not available'
        : date(
            'd F Y',
            $timestamp
        );
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
        My Bookings | <?= e(APP_NAME) ?>
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
                '/assets/css/customer_dashboard.css'
            )
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/customer_my_bookings.css'
            )
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
                <?= e(
                    (string) $customer[
                        'full_name'
                    ]
                ) ?>
            </h2>

            <p>Customer Account</p>

            <div class="customer-online">
                ● Online
            </div>

        </div>

        <nav class="customer-menu">

            <a
                href="<?= e(
                    url(
                        '/customer/dashboard.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a
                href="<?= e(
                    url(
                        '/customer/packages.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-gift"></i>
                Browse Packages
            </a>

            <a
                href="<?= e(
                    url(
                        '/customer/venues.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-hotel"></i>
                Browse Venues
            </a>

            <a
                href="<?= e(
                    url(
                        '/customer/gallery.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-images"></i>
                Wedding Gallery
            </a>

            <a
                href="<?= e(
                    url(
                        '/customer/booking.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-calendar-plus"></i>
                Book Event
            </a>

            <a
                class="active"
                href="<?= e(
                    url(
                        '/customer/my_bookings.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-calendar-check"></i>
                My Bookings
            </a>

            <a
                href="<?= e(
                    url(
                        '/customer/feedback.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-star"></i>
                Feedback
            </a>

            <a
                href="<?= e(
                    url(
                        '/customer/profile.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a
                class="customer-logout"
                href="<?= e(
                    url('/auth/logout.php')
                ) ?>"
            >
                <i
                    class="fa-solid fa-right-from-bracket"
                ></i>
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

                    <h1>My Bookings</h1>

                    <p>
                        View your wedding-event requests,
                        booking information and current
                        status.
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
                    href="<?= e(
                        url('/index.php')
                    ) ?>"
                    aria-label="Open public website"
                >
                    <i class="fa-solid fa-globe"></i>
                </a>

                <a
                    href="<?= e(
                        url(
                            '/customer/profile.php'
                        )
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

        <?php if ($flash): ?>

            <div
                class="customer-booking-alert <?= $flash['type'] === 'success'
                    ? 'success'
                    : 'danger' ?>"
            >
                <?= e($flash['message']) ?>
            </div>

        <?php endif; ?>

        <?php if ($errors !== []): ?>

            <div
                class="customer-booking-alert danger"
            >
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

        <section class="customer-booking-summary">

            <article
                class="customer-booking-summary-card"
            >

                <div
                    class="customer-booking-summary-icon total"
                >
                    <i class="fa-solid fa-calendar-days"></i>
                </div>

                <div>
                    <h4>Total Bookings</h4>

                    <h2>
                        <?= e(
                            (string) $totalBookings
                        ) ?>
                    </h2>
                </div>

            </article>

            <article
                class="customer-booking-summary-card"
            >

                <div
                    class="customer-booking-summary-icon pending"
                >
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div>
                    <h4>Pending</h4>

                    <h2>
                        <?= e(
                            (string) $pendingBookings
                        ) ?>
                    </h2>
                </div>

            </article>

            <article
                class="customer-booking-summary-card"
            >

                <div
                    class="customer-booking-summary-icon confirmed"
                >
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    <h4>Confirmed</h4>

                    <h2>
                        <?= e(
                            (string) $confirmedBookings
                        ) ?>
                    </h2>
                </div>

            </article>

            <article
                class="customer-booking-summary-card"
            >

                <div
                    class="customer-booking-summary-icon completed"
                >
                    <i class="fa-solid fa-trophy"></i>
                </div>

                <div>
                    <h4>Completed</h4>

                    <h2>
                        <?= e(
                            (string) $completedBookings
                        ) ?>
                    </h2>
                </div>

            </article>

        </section>

        <section class="customer-booking-filter-box">

            <form
                class="customer-booking-filter-form"
                method="get"
            >

                <div
                    class="customer-booking-filter-field"
                >

                    <label for="search">
                        Search Bookings
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Reference, event, package or venue"
                    >

                </div>

                <div
                    class="customer-booking-filter-field"
                >

                    <label for="status">
                        Booking Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <?php foreach (
                            $allowedStatuses as $status
                        ): ?>

                            <option
                                value="<?= e($status) ?>"

                                <?= $statusFilter === $status
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e(
                                    $status === 'all'
                                        ? 'All Statuses'
                                        : my_booking_status_label(
                                            $status
                                        )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <button
                    class="customer-booking-filter-button"
                    type="submit"
                >
                    Apply Filter
                </button>

                <a
                    class="customer-booking-clear-button"
                    href="<?= e(
                        url(
                            '/customer/my_bookings.php'
                        )
                    ) ?>"
                >
                    Clear
                </a>

            </form>

        </section>

        <section class="customer-my-bookings-box">

            <div class="customer-my-bookings-heading">

                <div>

                    <h2>Your Wedding Bookings</h2>

                    <p>
                        <?= e(
                            number_format(
                                count($bookings)
                            )
                        ) ?>
                        booking record(s) currently shown.
                    </p>

                </div>

                <a
                    class="customer-new-booking-button"
                    href="<?= e(
                        url(
                            '/customer/booking.php'
                        )
                    ) ?>"
                >
                    <i class="fa-solid fa-plus"></i>
                    New Booking
                </a>

            </div>

            <?php if ($bookings === []): ?>

                <div class="customer-my-bookings-empty">

                    <i
                        class="fa-regular fa-calendar-xmark"
                    ></i>

                    <h3>No bookings found</h3>

                    <p>
                        No booking matches the selected
                        search and status filters. Clear
                        the filters or create a new
                        wedding booking.
                    </p>

                    <a
                        href="<?= e(
                            url(
                                '/customer/booking.php'
                            )
                        ) ?>"
                    >
                        Create Booking
                    </a>

                </div>

            <?php else: ?>

                <div class="customer-my-bookings-list">

                    <?php foreach (
                        $bookings as $booking
                    ): ?>
                        <?php
                        $bookingId =
                            (int) $booking['id'];

                        $bookingStatus =
                            (string) (
                                $booking[
                                    'booking_status'
                                ]
                                ?? 'pending'
                            );

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

                        $address = trim(
                            (string) (
                                $booking[
                                    'customer_address'
                                ]
                                ?? ''
                            )
                        );

                        $instructions = trim(
                            (string) (
                                $booking[
                                    'special_instructions'
                                ]
                                ?? ''
                            )
                        );

                        $bookingServices =
                            $servicesByBooking[
                                $bookingId
                            ] ?? [];

                        $venueDisplay =
                            $venueName !== ''
                                ? $venueName
                                    . (
                                        $venueLocation !== ''
                                            ? ' — '
                                                . $venueLocation
                                            : ''
                                    )
                                : 'Venue unavailable';
                        ?>

                        <article
                            class="customer-my-booking-card"
                        >

                            <div>

                                <div
                                    class="customer-my-booking-top"
                                >

                                    <h3>
                                        <?= e($eventType) ?>
                                    </h3>

                                    <span
                                        class="customer-my-booking-code"
                                    >
                                        <?= e(
                                            (string) $booking[
                                                'booking_code'
                                            ]
                                        ) ?>
                                    </span>

                                </div>

                                <div
                                    class="customer-my-booking-details"
                                >

                                    <p>
                                        <i
                                            class="fa-solid fa-calendar"
                                        ></i>

                                        <?= e(
                                            my_booking_date(
                                                $booking[
                                                    'event_date'
                                                ]
                                            )
                                        ) ?>
                                    </p>

                                    <p>
                                        <i
                                            class="fa-solid fa-clock"
                                        ></i>

                                        <?= e(
                                            my_booking_time(
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
                                                : 'Package unavailable'
                                        ) ?>
                                    </p>

                                    <p>
                                        <i
                                            class="fa-solid fa-hotel"
                                        ></i>

                                        <?= e(
                                            $venueDisplay
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

                                    <p>
                                        <i
                                            class="fa-solid fa-calendar-plus"
                                        ></i>

                                        Created

                                        <?= e(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    (string) $booking[
                                                        'created_at'
                                                    ]
                                                )
                                            )
                                        ) ?>
                                    </p>

                                </div>

                            </div>

                            <div
                                class="customer-my-booking-side"
                            >

                                <span
                                    class="customer-my-booking-status <?= e(
                                        my_booking_status_class(
                                            $bookingStatus
                                        )
                                    ) ?>"
                                >
                                    <?= e(
                                        my_booking_status_label(
                                            $bookingStatus
                                        )
                                    ) ?>
                                </span>

                                <div
                                    class="customer-my-booking-total"
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

                                <button
                                    class="customer-my-booking-view"
                                    type="button"

                                    data-booking-details

                                    data-booking-id="<?= e(
                                        (string) $bookingId
                                    ) ?>"

                                    data-code="<?= e(
                                        (string) $booking[
                                            'booking_code'
                                        ]
                                    ) ?>"

                                    data-event-type="<?= e(
                                        $eventType
                                    ) ?>"

                                    data-event-date="<?= e(
                                        my_booking_date(
                                            $booking[
                                                'event_date'
                                            ]
                                        )
                                    ) ?>"

                                    data-event-time="<?= e(
                                        my_booking_time(
                                            $booking[
                                                'event_time'
                                            ]
                                            ?? null
                                        )
                                    ) ?>"

                                    data-package="<?= e(
                                        $packageName !== ''
                                            ? $packageName
                                            : 'Package unavailable'
                                    ) ?>"

                                    data-venue="<?= e(
                                        $venueDisplay
                                    ) ?>"

                                    data-guests="<?= e(
                                        number_format(
                                            (int) (
                                                $booking[
                                                    'guest_count'
                                                ]
                                                ?? 0
                                            )
                                        )
                                    ) ?>"

                                    data-address="<?= e(
                                        $address !== ''
                                            ? $address
                                            : 'Not provided'
                                    ) ?>"

                                    data-instructions="<?= e(
                                        $instructions !== ''
                                            ? $instructions
                                            : 'No special instructions.'
                                    ) ?>"

                                    data-status="<?= e(
                                        $bookingStatus
                                    ) ?>"

                                    data-status-label="<?= e(
                                        my_booking_status_label(
                                            $bookingStatus
                                        )
                                    ) ?>"

                                    data-can-cancel="<?= $bookingStatus === 'pending'
                                        ? '1'
                                        : '0' ?>"

                                    data-created="<?= e(
                                        date(
                                            'd F Y, h:i A',
                                            strtotime(
                                                (string) $booking[
                                                    'created_at'
                                                ]
                                            )
                                        )
                                    ) ?>"

                                    data-subtotal="<?= e(
                                        number_format(
                                            (float) (
                                                $booking[
                                                    'subtotal'
                                                ]
                                                ?? 0
                                            ),
                                            0
                                        )
                                    ) ?>"

                                    data-total="<?= e(
                                        number_format(
                                            (float) (
                                                $booking[
                                                    'total_amount'
                                                ]
                                                ?? 0
                                            ),
                                            0
                                        )
                                    ) ?>"

                                    data-services="<?= e(
                                        json_encode(
                                            $bookingServices,
                                            JSON_UNESCAPED_UNICODE
                                            | JSON_UNESCAPED_SLASHES
                                        )
                                    ) ?>"
                                >
                                    View Details
                                </button>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <footer class="customer-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <div
        class="customer-booking-modal"
        id="customerBookingModal"
    >

        <div class="customer-booking-modal-content">

            <button
                class="customer-booking-modal-close"
                id="customerBookingModalClose"
                type="button"
                aria-label="Close booking details"
            >
                &times;
            </button>

            <div class="customer-booking-modal-header">

                <h2 id="modalEventType">
                    Wedding Event
                </h2>

                <div
                    class="customer-booking-modal-code"
                    id="modalBookingCode"
                ></div>

            </div>

            <div
                class="customer-booking-modal-status-row"
            >

                <span>
                    Current Booking Status
                </span>

                <span
                    class="customer-my-booking-status pending"
                    id="modalBookingStatus"
                >
                    Pending
                </span>

            </div>

            <div class="customer-booking-modal-grid">

                <div
                    class="customer-booking-modal-item"
                >
                    <strong>Event Date</strong>
                    <span id="modalEventDate"></span>
                </div>

                <div
                    class="customer-booking-modal-item"
                >
                    <strong>Event Time</strong>
                    <span id="modalEventTime"></span>
                </div>

                <div
                    class="customer-booking-modal-item"
                >
                    <strong>Package</strong>
                    <span id="modalPackage"></span>
                </div>

                <div
                    class="customer-booking-modal-item"
                >
                    <strong>Venue</strong>
                    <span id="modalVenue"></span>
                </div>

                <div
                    class="customer-booking-modal-item"
                >
                    <strong>Total Guests</strong>
                    <span id="modalGuests"></span>
                </div>

                <div
                    class="customer-booking-modal-item"
                >
                    <strong>Booking Created</strong>
                    <span id="modalCreated"></span>
                </div>

            </div>

            <div
                class="customer-booking-modal-section"
            >

                <h3>Customer Address</h3>

                <div
                    class="customer-booking-modal-text"
                    id="modalAddress"
                ></div>

            </div>

            <div
                class="customer-booking-modal-section"
            >

                <h3>Special Instructions</h3>

                <div
                    class="customer-booking-modal-text"
                    id="modalInstructions"
                ></div>

            </div>

            <div
                class="customer-booking-modal-section"
            >

                <h3>Additional Services</h3>

                <ul
                    class="customer-booking-services-list"
                    id="modalServices"
                ></ul>

            </div>

            <div
                class="customer-booking-modal-price-box"
            >

                <div
                    class="customer-booking-modal-price-row"
                >
                    <span>Subtotal</span>

                    <span id="modalSubtotal">
                        Rs. 0
                    </span>
                </div>

                <div
                    class="customer-booking-modal-price-row total"
                >
                    <span>Total Amount</span>

                    <span id="modalTotal">
                        Rs. 0
                    </span>
                </div>

            </div>

            <div
                class="customer-booking-cancel-section hidden"
                id="modalCancelSection"
            >

                <div>

                    <h3>
                        Cancel Pending Booking
                    </h3>

                    <p>
                        You can cancel this booking while
                        its status is still Pending. This
                        action cannot be undone.
                    </p>

                </div>

                <form
                    method="post"
                    id="modalCancelForm"
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="cancel_booking"
                    >

                    <input
                        type="hidden"
                        name="booking_id"
                        id="modalCancelBookingId"
                        value=""
                    >

                    <button
                        class="customer-booking-cancel-button"
                        type="submit"
                    >
                        <i class="fa-solid fa-ban"></i>
                        Cancel Booking
                    </button>

                </form>

            </div>

        </div>

    </div>

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

        if (
            customerSidebar
            && customerSidebarOverlay
            && customerMenuButton
        ) {
            function closeCustomerSidebar() {
                customerSidebar.classList.remove(
                    "open"
                );

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
        }

        const bookingModal =
            document.getElementById(
                "customerBookingModal"
            );

        const bookingModalClose =
            document.getElementById(
                "customerBookingModalClose"
            );

        const modalCancelSection =
            document.getElementById(
                "modalCancelSection"
            );

        const modalCancelForm =
            document.getElementById(
                "modalCancelForm"
            );

        const modalCancelBookingId =
            document.getElementById(
                "modalCancelBookingId"
            );

        function formatServiceMoney(
            amount
        ) {
            return "Rs. "
                + Number(
                    amount || 0
                ).toLocaleString(
                    "en-PK",
                    {
                        maximumFractionDigits: 0
                    }
                );
        }

        function openBookingModal(
            button
        ) {
            document.getElementById(
                "modalEventType"
            ).textContent =
                button.dataset.eventType;

            document.getElementById(
                "modalBookingCode"
            ).textContent =
                "Booking Reference: "
                + button.dataset.code;

            document.getElementById(
                "modalEventDate"
            ).textContent =
                button.dataset.eventDate;

            document.getElementById(
                "modalEventTime"
            ).textContent =
                button.dataset.eventTime;

            document.getElementById(
                "modalPackage"
            ).textContent =
                button.dataset.package;

            document.getElementById(
                "modalVenue"
            ).textContent =
                button.dataset.venue;

            document.getElementById(
                "modalGuests"
            ).textContent =
                button.dataset.guests
                + " guests";

            document.getElementById(
                "modalCreated"
            ).textContent =
                button.dataset.created;

            document.getElementById(
                "modalAddress"
            ).textContent =
                button.dataset.address;

            document.getElementById(
                "modalInstructions"
            ).textContent =
                button.dataset.instructions;

            document.getElementById(
                "modalSubtotal"
            ).textContent =
                "Rs. "
                + button.dataset.subtotal;

            document.getElementById(
                "modalTotal"
            ).textContent =
                "Rs. "
                + button.dataset.total;

            const statusElement =
                document.getElementById(
                    "modalBookingStatus"
                );

            statusElement.textContent =
                button.dataset.statusLabel;

            statusElement.className =
                "customer-my-booking-status "
                + (
                    button.dataset.status
                    === "in_progress"
                        ? "in-progress"
                        : button.dataset.status
                );

            modalCancelBookingId.value =
                button.dataset.bookingId
                || "";

            if (
                button.dataset.canCancel
                === "1"
            ) {
                modalCancelSection.classList.remove(
                    "hidden"
                );
            } else {
                modalCancelSection.classList.add(
                    "hidden"
                );
            }

            const servicesList =
                document.getElementById(
                    "modalServices"
                );

            servicesList.innerHTML = "";

            let services = [];

            try {
                services = JSON.parse(
                    button.dataset.services
                    || "[]"
                );
            } catch (error) {
                services = [];
            }

            if (services.length === 0) {
                const item =
                    document.createElement(
                        "li"
                    );

                const name =
                    document.createElement(
                        "span"
                    );

                name.textContent =
                    "No additional services selected.";

                item.appendChild(name);

                servicesList.appendChild(
                    item
                );
            } else {
                services.forEach(
                    function (service) {
                        const item =
                            document.createElement(
                                "li"
                            );

                        const name =
                            document.createElement(
                                "span"
                            );

                        const price =
                            document.createElement(
                                "span"
                            );

                        const quantity =
                            Number(
                                service.quantity
                                || 1
                            );

                        name.textContent =
                            service.name
                            + (
                                quantity > 1
                                    ? " × "
                                        + quantity
                                    : ""
                            );

                        price.textContent =
                            formatServiceMoney(
                                Number(
                                    service.price
                                    || 0
                                )
                                * quantity
                            );

                        item.appendChild(name);
                        item.appendChild(price);

                        servicesList.appendChild(
                            item
                        );
                    }
                );
            }

            bookingModal.classList.add(
                "open"
            );

            document.body.style.overflow =
                "hidden";
        }

        document
            .querySelectorAll(
                "[data-booking-details]"
            )
            .forEach(
                function (button) {
                    button.addEventListener(
                        "click",
                        function () {
                            openBookingModal(
                                button
                            );
                        }
                    );
                }
            );

        function closeBookingModal() {
            bookingModal.classList.remove(
                "open"
            );

            document.body.style.overflow =
                "";

            modalCancelSection.classList.add(
                "hidden"
            );

            modalCancelBookingId.value =
                "";
        }

        modalCancelForm.addEventListener(
            "submit",
            function (event) {
                const confirmed =
                    window.confirm(
                        "Are you sure you want to cancel this pending booking? This action cannot be undone."
                    );

                if (!confirmed) {
                    event.preventDefault();
                }
            }
        );

        bookingModalClose.addEventListener(
            "click",
            closeBookingModal
        );

        bookingModal.addEventListener(
            "click",
            function (event) {
                if (
                    event.target
                    === bookingModal
                ) {
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

        const requestedBookingId =
            "<?= e(
                $requestedBookingId > 0
                    ? (string) $requestedBookingId
                    : ''
            ) ?>";

        if (
            requestedBookingId !== ""
        ) {
            const requestedButton =
                document.querySelector(
                    '[data-booking-id="'
                    + requestedBookingId
                    + '"]'
                );

            if (requestedButton) {
                openBookingModal(
                    requestedButton
                );
            }
        }
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>