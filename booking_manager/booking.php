<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/package_helpers.php';

require_role('booking_manager');

$connection = db();
$bookingManagerId = (int) $_SESSION['user_id'];

$errors = [];
$flash = get_flash();

$allowedEventTypes = [
    'Wedding',
    'Nikah',
    'Mehndi',
    'Walima',
    'Engagement',
    'Reception',
];

$allowedBookingStatuses = [
    'pending',
    'confirmed',
];

/*
|--------------------------------------------------------------------------
| Load Booking Manager
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
| Load selectable records
|--------------------------------------------------------------------------
*/

$customers = $connection
    ->query(
        "SELECT
            id,
            full_name,
            email,
            phone
         FROM users
         WHERE role = 'customer'
         AND is_active = 1
         AND is_verified = 1
         ORDER BY full_name ASC"
    )
    ->fetchAll();

$packages = $connection
    ->query(
        "SELECT
            id,
            name,
            price,
            guest_capacity,
            main_image
         FROM packages
         WHERE status = 'active'
         ORDER BY price ASC, name ASC"
    )
    ->fetchAll();

/*
|--------------------------------------------------------------------------
| Load active venues
|--------------------------------------------------------------------------
*/

$venues = $connection
    ->query(
        "SELECT
            id,
            name,
            location,
            capacity,
            price
         FROM venues
         WHERE status = 'active'
         ORDER BY name ASC"
    )
    ->fetchAll();

$services = $connection
    ->query(
        "SELECT
            id,
            name,
            description,
            price
         FROM services
         WHERE status = 'active'
         ORDER BY name ASC"
    )
    ->fetchAll();

/*
|--------------------------------------------------------------------------
| URL selections
|--------------------------------------------------------------------------
*/

$requestedPackageId = max(
    0,
    (int) ($_GET['package_id'] ?? 0)
);

$requestedVenueId = max(
    0,
    (int) ($_GET['venue_id'] ?? 0)
);

$requestedEventDate = trim(
    (string) ($_GET['event_date'] ?? '')
);

$requestedDateObject =
    DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $requestedEventDate
    );

$requestedDateErrors =
    DateTimeImmutable::getLastErrors();

$requestedDateIsValid =
    $requestedDateObject !== false
    && (
        $requestedDateErrors === false
        || (
            $requestedDateErrors['warning_count'] === 0
            && $requestedDateErrors['error_count'] === 0
        )
    )
    && $requestedDateObject->format('Y-m-d')
        === $requestedEventDate;

if (
    !$requestedDateIsValid
    || $requestedEventDate < date('Y-m-d')
) {
    $requestedEventDate = '';
}

$formValues = [
    'customer_id' => '',

    'package_id' =>
        $requestedPackageId > 0
            ? (string) $requestedPackageId
            : '',

    'venue_id' =>
        $requestedVenueId > 0
            ? (string) $requestedVenueId
            : '',

    'event_type' => 'Wedding',
    'event_date' => $requestedEventDate,
    'event_time' => '',
    'guest_count' => '',
    'customer_address' => '',
    'special_instructions' => '',
    'booking_status' => 'confirmed',
    'service_ids' => [],
];

function find_booking_record(
    array $records,
    int $recordId
): ?array {
    foreach ($records as $record) {
        if ((int) $record['id'] === $recordId) {
            return $record;
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| Create booking
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your form session expired. Refresh and try again.';
    }

    $customerId = (int) (
        $_POST['customer_id'] ?? 0
    );

    $packageId = (int) (
        $_POST['package_id'] ?? 0
    );

    $venueId = (int) (
        $_POST['venue_id'] ?? 0
    );

    $eventType = trim(
        (string) ($_POST['event_type'] ?? '')
    );

    $eventDate = trim(
        (string) ($_POST['event_date'] ?? '')
    );

    $eventTime = trim(
        (string) ($_POST['event_time'] ?? '')
    );

    $guestCount = (int) (
        $_POST['guest_count'] ?? 0
    );

    $customerAddress = trim(
        (string) (
            $_POST['customer_address']
            ?? ''
        )
    );

    $specialInstructions = trim(
        (string) (
            $_POST['special_instructions']
            ?? ''
        )
    );

    $bookingStatus = (string) (
        $_POST['booking_status']
        ?? 'confirmed'
    );

    $serviceIds =
        $_POST['service_ids'] ?? [];

    if (!is_array($serviceIds)) {
        $serviceIds = [];
    }

    $serviceIds = array_values(
        array_unique(
            array_filter(
                array_map(
                    'intval',
                    $serviceIds
                ),
                static fn (int $id): bool =>
                    $id > 0
            )
        )
    );

    $formValues = [
        'customer_id' =>
            $customerId > 0
                ? (string) $customerId
                : '',

        'package_id' =>
            $packageId > 0
                ? (string) $packageId
                : '',

        'venue_id' =>
            $venueId > 0
                ? (string) $venueId
                : '',

        'event_type' => $eventType,
        'event_date' => $eventDate,
        'event_time' => $eventTime,

        'guest_count' =>
            $guestCount > 0
                ? (string) $guestCount
                : '',

        'customer_address' =>
            $customerAddress,

        'special_instructions' =>
            $specialInstructions,

        'booking_status' =>
            $bookingStatus,

        'service_ids' =>
            $serviceIds,
    ];

    $selectedCustomer =
        find_booking_record(
            $customers,
            $customerId
        );

    $selectedPackage =
        find_booking_record(
            $packages,
            $packageId
        );

    $selectedVenue =
        find_booking_record(
            $venues,
            $venueId
        );

    if (!$selectedCustomer) {
        $errors[] =
            'Select a valid registered customer.';
    }

    if (!$selectedPackage) {
        $errors[] =
            'Select a valid active package.';
    }

    if (!$selectedVenue) {
        $errors[] =
            'Select a valid active venue.';
    }

    if (
        !in_array(
            $eventType,
            $allowedEventTypes,
            true
        )
    ) {
        $errors[] =
            'Select a valid event type.';
    }

    $eventDateObject =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $eventDate
        );

    $dateErrors =
        DateTimeImmutable::getLastErrors();

    $dateIsValid =
        $eventDateObject !== false
        && (
            $dateErrors === false
            || (
                $dateErrors['warning_count'] === 0
                && $dateErrors['error_count'] === 0
            )
        )
        && $eventDateObject->format('Y-m-d')
            === $eventDate;

    if (!$dateIsValid) {
        $errors[] =
            'Select a valid event date.';
    } elseif ($eventDate < date('Y-m-d')) {
        $errors[] =
            'The event date cannot be in the past.';
    }

    if (
        !preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            $eventTime
        )
    ) {
        $errors[] =
            'Select a valid event time.';
    }

    if ($guestCount < 1) {
        $errors[] =
            'The guest count must be at least 1.';
    }

    if (
        $selectedPackage
        && $guestCount
            > (int) $selectedPackage[
                'guest_capacity'
            ]
    ) {
        $errors[] =
            'The guest count exceeds the selected package capacity of '
            . number_format(
                (int) $selectedPackage[
                    'guest_capacity'
                ]
            )
            . '.';
    }

    if (
        $selectedVenue
        && $guestCount
            > (int) $selectedVenue['capacity']
    ) {
        $errors[] =
            'The guest count exceeds the selected venue capacity of '
            . number_format(
                (int) $selectedVenue['capacity']
            )
            . '.';
    }

    if (
        mb_strlen($customerAddress) < 5
        || mb_strlen($customerAddress) > 1000
    ) {
        $errors[] =
            'Customer address must contain between 5 and 1,000 characters.';
    }

    if (
        mb_strlen($specialInstructions) > 2000
    ) {
        $errors[] =
            'Special instructions cannot exceed 2,000 characters.';
    }

    if (
        !in_array(
            $bookingStatus,
            $allowedBookingStatuses,
            true
        )
    ) {
        $errors[] =
            'Select a valid booking status.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate services
    |--------------------------------------------------------------------------
    */

    $selectedServices = [];

    if ($serviceIds !== []) {
        $servicePlaceholders = implode(
            ',',
            array_fill(
                0,
                count($serviceIds),
                '?'
            )
        );

        $serviceStatement =
            $connection->prepare(
                "SELECT
                    id,
                    name,
                    price
                 FROM services
                 WHERE status = 'active'
                 AND id IN (
                    $servicePlaceholders
                 )"
            );

        $serviceStatement->execute(
            $serviceIds
        );

        $selectedServices =
            $serviceStatement->fetchAll();

        if (
            count($selectedServices)
            !== count($serviceIds)
        ) {
            $errors[] =
                'One or more selected services are unavailable.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check venue availability
    |--------------------------------------------------------------------------
    */

    if (
        $errors === []
        && $selectedVenue
        && $dateIsValid
    ) {
        $availabilityStatement =
            $connection->prepare(
                "SELECT COUNT(*)
                 FROM bookings
                 WHERE venue_id = ?
                 AND event_date = ?
                 AND booking_status IN (
                    'pending',
                    'confirmed',
                    'in_progress'
                 )"
            );

        $availabilityStatement->execute([
            $venueId,
            $eventDate,
        ]);

        $venueBookingCount = (int) (
            $availabilityStatement
                ->fetchColumn()
        );

        if ($venueBookingCount > 0) {
            $errors[] =
                'The selected venue already has an active booking on this date. Select another venue or date.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate total
    |--------------------------------------------------------------------------
    */

    $packagePrice =
        $selectedPackage
            ? (float) $selectedPackage['price']
            : 0.0;

    $venuePrice =
        $selectedVenue
            ? (float) $selectedVenue['price']
            : 0.0;

    $servicesTotal = 0.0;

    foreach ($selectedServices as $service) {
        $servicesTotal +=
            (float) $service['price'];
    }

    $subtotal =
        $packagePrice
        + $venuePrice
        + $servicesTotal;

    $totalAmount = $subtotal;

    /*
    |--------------------------------------------------------------------------
    | Save booking
    |--------------------------------------------------------------------------
    */

    if ($errors === []) {
        $bookingCode =
            'WEP-'
            . date('ymd')
            . '-'
            . strtoupper(
                bin2hex(random_bytes(3))
            );

        try {
            $connection->beginTransaction();

            $bookingStatement =
                $connection->prepare(
                    'INSERT INTO bookings (
                        booking_code,
                        customer_id,
                        package_id,
                        venue_id,
                        event_type,
                        event_date,
                        event_time,
                        guest_count,
                        customer_address,
                        special_instructions,
                        subtotal,
                        total_amount,
                        booking_status,
                        created_by
                     ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?
                     )'
                );

            $bookingStatement->execute([
                $bookingCode,
                $customerId,
                $packageId,
                $venueId,
                $eventType,
                $eventDate,
                $eventTime . ':00',
                $guestCount,
                $customerAddress,

                $specialInstructions !== ''
                    ? $specialInstructions
                    : null,

                $subtotal,
                $totalAmount,
                $bookingStatus,
                $bookingManagerId,
            ]);

            $bookingId = (int) (
                $connection->lastInsertId()
            );

            if ($selectedServices !== []) {
                $bookingServiceStatement =
                    $connection->prepare(
                        'INSERT INTO booking_services (
                            booking_id,
                            service_id,
                            quantity,
                            price
                         ) VALUES (?, ?, 1, ?)'
                    );

                foreach (
                    $selectedServices as $service
                ) {
                    $bookingServiceStatement
                        ->execute([
                            $bookingId,
                            (int) $service['id'],
                            (float) $service['price'],
                        ]);
                }
            }

            $connection->commit();

            set_flash(
                'success',
                'Booking created successfully. Booking reference: '
                . $bookingCode
            );

            redirect(
                '/booking_manager/booking.php?package_id='
                . $packageId
                . '&venue_id='
                . $venueId
            );
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            $errors[] = APP_DEBUG
                ? 'Booking could not be created: '
                    . $exception->getMessage()
                : 'Booking could not be created. Please try again.';
        }
    }
}

$currentPackageId = (int) (
    $formValues['package_id'] !== ''
        ? $formValues['package_id']
        : 0
);

$currentPackage =
    find_booking_record(
        $packages,
        $currentPackageId
    );

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
        Create Booking | <?= e(APP_NAME) ?>
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

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/booking_form.css')
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

            <h2>
                <?= e($manager['full_name']) ?>
            </h2>

            <p>Booking Manager</p>

            <div class="booking-online">
                ● Online
            </div>

        </div>

        <nav class="booking-menu">

            <a
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
                class="active"
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

                    <h1>Create Package Booking</h1>

                    <p>
                        Select a customer, venue, services
                        and wedding-event information.
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

                <a href="<?= e(url('/booking_manager/profile.php')) ?>">
                    <img
                        class="booking-profile-image"
                        src="<?= e($managerImage) ?>"
                        alt="Booking Manager profile"
                    >
                </a>

            </div>

        </header>

        <?php if ($flash): ?>

            <div
                class="booking-form-alert <?= $flash['type'] === 'success'
                    ? 'booking-form-alert-success'
                    : 'booking-form-alert-danger' ?>"
            >
                <?= e($flash['message']) ?>
            </div>

        <?php endif; ?>

        <?php if ($errors !== []): ?>

            <div class="booking-form-alert booking-form-alert-danger">

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>

            </div>

        <?php endif; ?>

        <?php if (
            $customers === []
            || $packages === []
            || $venues === []
        ): ?>

            <section class="booking-form-box">

                <div class="booking-data-empty">

                    <i class="fa-solid fa-triangle-exclamation"></i>

                    <h3>
                        Booking information is incomplete
                    </h3>

                    <p>
                        At least one verified customer,
                        active package and active venue are
                        required before creating a booking.
                    </p>

                </div>

            </section>

        <?php else: ?>

            <div class="booking-form-layout">

                <section class="booking-form-box">

                    <div class="booking-form-heading">

                        <h2>Wedding Booking Form</h2>

                        <p>
                            All prices and availability will
                            be validated again when the
                            booking is submitted.
                        </p>

                    </div>

                    <div
                        class="selected-package-preview"
                        id="selectedPackagePreview"
                    >

                        <img
                            id="selectedPackageImage"
                            src="<?= e(
                                $currentPackage
                                    ? package_image_url(
                                        $currentPackage['main_image']
                                        ?? null
                                    )
                                    : url(
                                        '/assets/icons/icon-512.png'
                                    )
                            ) ?>"
                            alt="Selected package"
                        >

                        <div>

                            <h3 id="selectedPackageName">
                                <?= e(
                                    $currentPackage
                                        ? (string) $currentPackage['name']
                                        : 'Select a package'
                                ) ?>
                            </h3>

                            <p id="selectedPackageCapacity">
                                <?= e(
                                    $currentPackage
                                        ? 'Capacity: '
                                            . number_format(
                                                (int) $currentPackage[
                                                    'guest_capacity'
                                                ]
                                            )
                                            . ' guests'
                                        : 'Choose an active wedding package below.'
                                ) ?>
                            </p>

                            <div
                                class="selected-package-price"
                                id="selectedPackagePrice"
                            >
                                <?= e(
                                    $currentPackage
                                        ? format_package_price(
                                            (float) $currentPackage['price']
                                        )
                                        : 'Rs. 0'
                                ) ?>
                            </div>

                        </div>

                    </div>

                    <form method="post">

                        <?= csrf_field() ?>

                        <div class="booking-form-grid">

                            <div class="booking-field full-width">

                                <label for="customer_id">
                                    Registered Customer
                                </label>

                                <select
                                    id="customer_id"
                                    name="customer_id"
                                    required
                                >
                                    <option value="">
                                        Select customer
                                    </option>

                                    <?php foreach (
                                        $customers as $customer
                                    ): ?>

                                        <option
                                            value="<?= e(
                                                (string) $customer['id']
                                            ) ?>"

                                            data-email="<?= e(
                                                (string) $customer['email']
                                            ) ?>"

                                            data-phone="<?= e(
                                                (string) (
                                                    $customer['phone']
                                                    ?: 'Not provided'
                                                )
                                            ) ?>"

                                            <?= (string) $customer['id']
                                                === $formValues['customer_id']
                                                    ? 'selected'
                                                    : '' ?>
                                        >
                                            <?= e(
                                                (string) $customer['full_name']
                                            ) ?>

                                            —
                                            <?= e(
                                                (string) $customer['email']
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <div
                                    class="booking-customer-preview"
                                    id="customerPreview"
                                >

                                    <div class="booking-customer-detail">
                                        <strong>Email</strong>
                                        <span id="customerEmail"></span>
                                    </div>

                                    <div class="booking-customer-detail">
                                        <strong>Phone</strong>
                                        <span id="customerPhone"></span>
                                    </div>

                                </div>

                            </div>

                            <div class="booking-field">

                                <label for="package_id">
                                    Wedding Package
                                </label>

                                <select
                                    id="package_id"
                                    name="package_id"
                                    required
                                >
                                    <option value="">
                                        Select package
                                    </option>

                                    <?php foreach ($packages as $package): ?>

                                        <option
                                            value="<?= e(
                                                (string) $package['id']
                                            ) ?>"

                                            <?= (string) $package['id']
                                                === $formValues['package_id']
                                                    ? 'selected'
                                                    : '' ?>
                                        >
                                            <?= e(
                                                (string) $package['name']
                                            ) ?>

                                            —
                                            Rs.
                                            <?= e(
                                                number_format(
                                                    (float) $package['price'],
                                                    0
                                                )
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="booking-field">

                                <label for="venue_id">
                                    Wedding Venue
                                </label>

                                <select
                                    id="venue_id"
                                    name="venue_id"
                                    required
                                >
                                    <option value="">
                                        Select wedding venue
                                    </option>

                                    <?php foreach ($venues as $venue): ?>

                                        <option
                                            value="<?= e(
                                                (string) $venue['id']
                                            ) ?>"

                                            <?= (string) $venue['id']
                                                === $formValues['venue_id']
                                                    ? 'selected'
                                                    : '' ?>
                                        >
                                            <?= e(
                                                (string) $venue['name']
                                            ) ?>

                                            —
                                            <?= e(
                                                (string) $venue['location']
                                            ) ?>

                                            —
                                            Rs.
                                            <?= e(
                                                number_format(
                                                    (float) $venue['price'],
                                                    0
                                                )
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <div
                                    class="venue-availability-message information"
                                    id="venueAvailabilityMessage"
                                >
                                    The system will verify
                                    that the venue has no
                                    active booking on the
                                    selected date.
                                </div>

                            </div>

                            <div class="booking-field">

                                <label for="event_type">
                                    Event Type
                                </label>

                                <select
                                    id="event_type"
                                    name="event_type"
                                    required
                                >
                                    <?php foreach (
                                        $allowedEventTypes as $type
                                    ): ?>

                                        <option
                                            value="<?= e($type) ?>"

                                            <?= $formValues['event_type']
                                                === $type
                                                    ? 'selected'
                                                    : '' ?>
                                        >
                                            <?= e($type) ?>
                                        </option>

                                    <?php endforeach; ?>
                                </select>

                            </div>

                            <div class="booking-field">

                                <label for="guest_count">
                                    Total Guests
                                </label>

                                <input
                                    type="number"
                                    id="guest_count"
                                    name="guest_count"
                                    value="<?= e(
                                        $formValues['guest_count']
                                    ) ?>"
                                    min="1"
                                    placeholder="Enter guest count"
                                    required
                                >

                                <span
                                    class="booking-field-help"
                                    id="guestCapacityHelp"
                                >
                                    Guest count must fit both
                                    the selected package and
                                    venue.
                                </span>

                            </div>

                            <div class="booking-field">

                                <label for="event_date">
                                    Event Date
                                </label>

                                <input
                                    type="date"
                                    id="event_date"
                                    name="event_date"
                                    value="<?= e(
                                        $formValues['event_date']
                                    ) ?>"
                                    min="<?= e(date('Y-m-d')) ?>"
                                    required
                                >

                            </div>

                            <div class="booking-field">

                                <label for="event_time">
                                    Event Time
                                </label>

                                <input
                                    type="time"
                                    id="event_time"
                                    name="event_time"
                                    value="<?= e(
                                        $formValues['event_time']
                                    ) ?>"
                                    required
                                >

                            </div>

                            <div class="booking-field">

                                <label for="booking_status">
                                    Booking Status
                                </label>

                                <select
                                    id="booking_status"
                                    name="booking_status"
                                    required
                                >
                                    <option
                                        value="pending"
                                        <?= $formValues['booking_status']
                                            === 'pending'
                                                ? 'selected'
                                                : '' ?>
                                    >
                                        Pending
                                    </option>

                                    <option
                                        value="confirmed"
                                        <?= $formValues['booking_status']
                                            === 'confirmed'
                                                ? 'selected'
                                                : '' ?>
                                    >
                                        Confirmed
                                    </option>
                                </select>

                            </div>

                            <div class="booking-field full-width">

                                <label for="customer_address">
                                    Customer Address
                                </label>

                                <textarea
                                    id="customer_address"
                                    name="customer_address"
                                    maxlength="1000"
                                    placeholder="Enter the customer's complete address"
                                    required
                                ><?= e(
                                    $formValues['customer_address']
                                ) ?></textarea>

                            </div>

                            <?php if ($services !== []): ?>

                                <div
                                    class="booking-field full-width booking-services-section"
                                >

                                    <label>
                                        Additional Services
                                    </label>

                                    <div class="booking-services-grid">

                                        <?php foreach (
                                            $services as $service
                                        ): ?>

                                            <div class="booking-service-option">

                                                <input
                                                    type="checkbox"

                                                    id="service_<?= e(
                                                        (string) $service['id']
                                                    ) ?>"

                                                    name="service_ids[]"

                                                    value="<?= e(
                                                        (string) $service['id']
                                                    ) ?>"

                                                    data-service-price="<?= e(
                                                        (string) (
                                                            (float) $service['price']
                                                        )
                                                    ) ?>"

                                                    <?= in_array(
                                                        (int) $service['id'],
                                                        $formValues['service_ids'],
                                                        true
                                                    )
                                                        ? 'checked'
                                                        : '' ?>
                                                >

                                                <label
                                                    class="booking-service-label"

                                                    for="service_<?= e(
                                                        (string) $service['id']
                                                    ) ?>"
                                                >

                                                    <span class="booking-service-check">
                                                        <i class="fa-solid fa-check"></i>
                                                    </span>

                                                    <span class="booking-service-information">

                                                        <strong>
                                                            <?= e(
                                                                (string) $service['name']
                                                            ) ?>
                                                        </strong>

                                                        <span>
                                                            Rs.
                                                            <?= e(
                                                                number_format(
                                                                    (float) $service['price'],
                                                                    0
                                                                )
                                                            ) ?>
                                                        </span>

                                                    </span>

                                                </label>

                                            </div>

                                        <?php endforeach; ?>

                                    </div>

                                </div>

                            <?php endif; ?>

                            <div class="booking-field full-width">

                                <label for="special_instructions">
                                    Special Instructions
                                </label>

                                <textarea
                                    id="special_instructions"
                                    name="special_instructions"
                                    maxlength="2000"
                                    placeholder="Write any customer requests or important event details"
                                ><?= e(
                                    $formValues['special_instructions']
                                ) ?></textarea>

                            </div>

                        </div>

                        <div class="booking-submit-row">

                            <button
                                class="booking-submit-button"
                                type="submit"
                            >
                                Create Booking
                            </button>

                            <a
                                class="booking-cancel-button"
                                href="<?= e(
                                    url(
                                        '/booking_manager/packages.php'
                                    )
                                ) ?>"
                            >
                                Cancel
                            </a>

                        </div>

                    </form>

                </section>

                <aside class="booking-summary-box">

                    <h2>Booking Summary</h2>

                    <div class="booking-price-row">
                        <span>Package</span>
                        <span id="summaryPackageName">
                            Not selected
                        </span>
                    </div>

                    <div class="booking-price-row">
                        <span>Package Price</span>
                        <span id="summaryPackagePrice">
                            Rs. 0
                        </span>
                    </div>

                    <div class="booking-price-row">
                        <span>Venue</span>
                        <span id="summaryVenueName">
                            Not selected
                        </span>
                    </div>

                    <div class="booking-price-row">
                        <span>Venue Price</span>
                        <span id="summaryVenuePrice">
                            Rs. 0
                        </span>
                    </div>

                    <div class="booking-price-row">
                        <span>Additional Services</span>
                        <span id="summaryServicesPrice">
                            Rs. 0
                        </span>
                    </div>

                    <div class="booking-price-total">
                        <span>Total Cost</span>
                        <span id="summaryTotal">
                            Rs. 0
                        </span>
                    </div>

                    <div class="booking-capacity-box">

                        Package capacity:

                        <strong id="summaryPackageCapacity">
                            0
                        </strong>

                        guests

                        <br>

                        Venue capacity:

                        <strong id="summaryVenueCapacity">
                            0
                        </strong>

                        guests

                    </div>

                    <div class="booking-summary-note">
                        The total is calculated securely
                        from the current database prices.
                        Venue availability and guest
                        capacity are checked again when the
                        booking is submitted.
                    </div>

                </aside>

            </div>

        <?php endif; ?>

        <footer class="booking-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <script>
        const bookingSidebar =
            document.getElementById(
                "bookingSidebar"
            );

        const bookingSidebarOverlay =
            document.getElementById(
                "bookingSidebarOverlay"
            );

        const bookingMenuButton =
            document.getElementById(
                "bookingMenuButton"
            );

        if (
            bookingSidebar
            && bookingSidebarOverlay
            && bookingMenuButton
        ) {
            function closeBookingSidebar() {
                bookingSidebar.classList.remove(
                    "open"
                );

                bookingSidebarOverlay.classList.remove(
                    "open"
                );
            }

            bookingMenuButton.addEventListener(
                "click",
                function () {
                    bookingSidebar.classList.toggle(
                        "open"
                    );

                    bookingSidebarOverlay.classList.toggle(
                        "open"
                    );
                }
            );

            bookingSidebarOverlay.addEventListener(
                "click",
                closeBookingSidebar
            );
        }

        const packageData = <?= json_encode(
            array_reduce(
                $packages,
                static function (
                    array $result,
                    array $package
                ): array {
                    $result[
                        (string) $package['id']
                    ] = [
                        'name' =>
                            (string) $package['name'],

                        'price' =>
                            (float) $package['price'],

                        'capacity' =>
                            (int) $package['guest_capacity'],

                        'image' =>
                            package_image_url(
                                $package['main_image']
                                ?? null
                            ),
                    ];

                    return $result;
                },
                []
            ),
            JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        ) ?>;

        const venueData = <?= json_encode(
            array_reduce(
                $venues,
                static function (
                    array $result,
                    array $venue
                ): array {
                    $result[
                        (string) $venue['id']
                    ] = [
                        'name' =>
                            (string) $venue['name'],

                        'location' =>
                            (string) $venue['location'],

                        'price' =>
                            (float) $venue['price'],

                        'capacity' =>
                            (int) $venue['capacity'],
                    ];

                    return $result;
                },
                []
            ),
            JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        ) ?>;

        const customerSelect =
            document.getElementById(
                "customer_id"
            );

        const packageSelect =
            document.getElementById(
                "package_id"
            );

        const venueSelect =
            document.getElementById(
                "venue_id"
            );

        const eventDateInput =
            document.getElementById(
                "event_date"
            );

        const guestCountInput =
            document.getElementById(
                "guest_count"
            );

        const serviceCheckboxes =
            document.querySelectorAll(
                "[data-service-price]"
            );

        function formatMoney(amount) {
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

        if (
            customerSelect
            && packageSelect
            && venueSelect
            && eventDateInput
            && guestCountInput
        ) {
            function updateCustomerPreview() {
                const selectedOption =
                    customerSelect.options[
                        customerSelect.selectedIndex
                    ];

                const preview =
                    document.getElementById(
                        "customerPreview"
                    );

                if (!preview) {
                    return;
                }

                if (
                    !selectedOption
                    || !customerSelect.value
                ) {
                    preview.classList.remove(
                        "visible"
                    );

                    return;
                }

                const customerEmail =
                    document.getElementById(
                        "customerEmail"
                    );

                const customerPhone =
                    document.getElementById(
                        "customerPhone"
                    );

                if (customerEmail) {
                    customerEmail.textContent =
                        selectedOption.dataset.email
                        || 'Not provided';
                }

                if (customerPhone) {
                    customerPhone.textContent =
                        selectedOption.dataset.phone
                        || 'Not provided';
                }

                preview.classList.add(
                    "visible"
                );
            }

            function calculateServicesTotal() {
                let servicesTotal = 0;

                serviceCheckboxes.forEach(
                    function (checkbox) {
                        if (checkbox.checked) {
                            servicesTotal += Number(
                                checkbox.dataset
                                    .servicePrice
                                || 0
                            );
                        }
                    }
                );

                return servicesTotal;
            }

            function updateBookingSummary() {
                const selectedPackage =
                    packageData[
                        packageSelect.value
                    ]
                    || null;

                const selectedVenue =
                    venueData[
                        venueSelect.value
                    ]
                    || null;

                const packagePrice =
                    selectedPackage
                        ? selectedPackage.price
                        : 0;

                const venuePrice =
                    selectedVenue
                        ? selectedVenue.price
                        : 0;

                const servicesPrice =
                    calculateServicesTotal();

                const total =
                    packagePrice
                    + venuePrice
                    + servicesPrice;

                const summaryPackageName =
                    document.getElementById(
                        "summaryPackageName"
                    );

                const summaryPackagePrice =
                    document.getElementById(
                        "summaryPackagePrice"
                    );

                const summaryVenueName =
                    document.getElementById(
                        "summaryVenueName"
                    );

                const summaryVenuePrice =
                    document.getElementById(
                        "summaryVenuePrice"
                    );

                const summaryServicesPrice =
                    document.getElementById(
                        "summaryServicesPrice"
                    );

                const summaryTotal =
                    document.getElementById(
                        "summaryTotal"
                    );

                const summaryPackageCapacity =
                    document.getElementById(
                        "summaryPackageCapacity"
                    );

                const summaryVenueCapacity =
                    document.getElementById(
                        "summaryVenueCapacity"
                    );

                if (summaryPackageName) {
                    summaryPackageName.textContent =
                        selectedPackage
                            ? selectedPackage.name
                            : 'Not selected';
                }

                if (summaryPackagePrice) {
                    summaryPackagePrice.textContent =
                        formatMoney(packagePrice);
                }

                if (summaryVenueName) {
                    summaryVenueName.textContent =
                        selectedVenue
                            ? selectedVenue.name
                                + ' — '
                                + selectedVenue.location
                            : 'Not selected';
                }

                if (summaryVenuePrice) {
                    summaryVenuePrice.textContent =
                        formatMoney(venuePrice);
                }

                if (summaryServicesPrice) {
                    summaryServicesPrice.textContent =
                        formatMoney(
                            servicesPrice
                        );
                }

                if (summaryTotal) {
                    summaryTotal.textContent =
                        formatMoney(total);
                }

                if (summaryPackageCapacity) {
                    summaryPackageCapacity.textContent =
                        selectedPackage
                            ? selectedPackage.capacity
                            : 0;
                }

                if (summaryVenueCapacity) {
                    summaryVenueCapacity.textContent =
                        selectedVenue
                            ? selectedVenue.capacity
                            : 0;
                }

                const selectedPackageImage =
                    document.getElementById(
                        "selectedPackageImage"
                    );

                const selectedPackageName =
                    document.getElementById(
                        "selectedPackageName"
                    );

                const selectedPackageCapacity =
                    document.getElementById(
                        "selectedPackageCapacity"
                    );

                const selectedPackagePrice =
                    document.getElementById(
                        "selectedPackagePrice"
                    );

                if (selectedPackage) {
                    if (selectedPackageImage) {
                        selectedPackageImage.src =
                            selectedPackage.image;
                    }

                    if (selectedPackageName) {
                        selectedPackageName.textContent =
                            selectedPackage.name;
                    }

                    if (selectedPackageCapacity) {
                        selectedPackageCapacity.textContent =
                            'Capacity: '
                            + selectedPackage.capacity
                            + ' guests';
                    }

                    if (selectedPackagePrice) {
                        selectedPackagePrice.textContent =
                            formatMoney(
                                selectedPackage.price
                            );
                    }
                } else {
                    if (selectedPackageImage) {
                        selectedPackageImage.src =
                            "<?= e(
                                url(
                                    '/assets/icons/icon-512.png'
                                )
                            ) ?>";
                    }

                    if (selectedPackageName) {
                        selectedPackageName.textContent =
                            'Select a package';
                    }

                    if (selectedPackageCapacity) {
                        selectedPackageCapacity.textContent =
                            'Choose an active wedding package.';
                    }

                    if (selectedPackagePrice) {
                        selectedPackagePrice.textContent =
                            'Rs. 0';
                    }
                }

                let capacityMessage =
                    'Guest count must fit both the selected package and venue.';

                if (
                    selectedPackage
                    && selectedVenue
                ) {
                    const allowedCapacity =
                        Math.min(
                            selectedPackage.capacity,
                            selectedVenue.capacity
                        );

                    capacityMessage =
                        'Maximum allowed guests for this combination: '
                        + allowedCapacity
                        + '.';

                    guestCountInput.max =
                        String(allowedCapacity);
                } else {
                    guestCountInput.removeAttribute(
                        'max'
                    );
                }

                const guestCapacityHelp =
                    document.getElementById(
                        "guestCapacityHelp"
                    );

                if (guestCapacityHelp) {
                    guestCapacityHelp.textContent =
                        capacityMessage;
                }
            }

            function updateAvailabilityMessage() {
                const message =
                    document.getElementById(
                        "venueAvailabilityMessage"
                    );

                if (!message) {
                    return;
                }

                if (
                    venueSelect.value
                    && eventDateInput.value
                ) {
                    message.textContent =
                        'The selected venue will be checked for '
                        + eventDateInput.value
                        + ' when you submit the booking.';
                } else {
                    message.textContent =
                        'Select a venue and date. The system will check for existing active bookings.';
                }

                message.classList.add(
                    'visible'
                );
            }

            customerSelect.addEventListener(
                'change',
                updateCustomerPreview
            );

            packageSelect.addEventListener(
                'change',
                updateBookingSummary
            );

            venueSelect.addEventListener(
                'change',
                function () {
                    updateBookingSummary();
                    updateAvailabilityMessage();
                }
            );

            eventDateInput.addEventListener(
                'change',
                updateAvailabilityMessage
            );

            serviceCheckboxes.forEach(
                function (checkbox) {
                    checkbox.addEventListener(
                        'change',
                        updateBookingSummary
                    );
                }
            );

            updateCustomerPreview();
            updateBookingSummary();
            updateAvailabilityMessage();
        }
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>