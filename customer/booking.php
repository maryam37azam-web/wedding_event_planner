<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/package_helpers.php';

require_role('customer');

$connection = db();
$customerId = (int) $_SESSION['user_id'];

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

/*
|--------------------------------------------------------------------------
| Load logged-in customer
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
     AND is_active = 1
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
| Load active packages
|--------------------------------------------------------------------------
*/

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
|
| Admin controls whether a venue appears using the status field.
| Actual availability is checked against bookings for the event date.
|
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

/*
|--------------------------------------------------------------------------
| Load active services
|--------------------------------------------------------------------------
*/

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
    'service_ids' => [],
];

/*
|--------------------------------------------------------------------------
| Find record by ID
|--------------------------------------------------------------------------
*/

function find_customer_booking_record(
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
| Create customer booking
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

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

        'service_ids' =>
            $serviceIds,
    ];

    $selectedPackage =
        find_customer_booking_record(
            $packages,
            $packageId
        );

    $selectedVenue =
        find_customer_booking_record(
            $venues,
            $venueId
        );

    if (!$selectedPackage) {
        $errors[] =
            'Select a valid active wedding package.';
    }

    if (!$selectedVenue) {
        $errors[] =
            'Select a valid active wedding venue.';
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

    /*
    |--------------------------------------------------------------------------
    | Validate date
    |--------------------------------------------------------------------------
    */

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
            . ' guests.';
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
            . ' guests.';
    }

    if (
        mb_strlen($customerAddress) < 5
        || mb_strlen($customerAddress) > 1000
    ) {
        $errors[] =
            'Your address must contain between 5 and 1,000 characters.';
    }

    if (
        mb_strlen($specialInstructions) > 2000
    ) {
        $errors[] =
            'Special instructions cannot exceed 2,000 characters.';
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
                'One or more selected services are no longer available.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check venue date availability
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
                'pending',
                $customerId,
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
                'Your booking request was submitted successfully. Booking reference: '
                . $bookingCode
                . '. Its current status is Pending.'
            );

            redirect('/customer/booking.php');
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
    find_customer_booking_record(
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
        Book Event | <?= e(APP_NAME) ?>
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

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/booking_form.css')
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/customer_booking.css')
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

            <a href="<?= e(url('/customer/dashboard.php')) ?>">
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a href="<?= e(url('/customer/packages.php')) ?>">
                <i class="fa-solid fa-gift"></i>
                Browse Packages
            </a>

            <a href="<?= e(url('/customer/venues.php')) ?>">
                <i class="fa-solid fa-hotel"></i>
                Browse Venues
            </a>

            <a href="<?= e(url('/customer/gallery.php')) ?>">
                <i class="fa-solid fa-images"></i>
                Wedding Gallery
            </a>

            <a
                class="active"
                href="<?= e(url('/customer/booking.php')) ?>"
            >
                <i class="fa-solid fa-calendar-plus"></i>
                Book Event
            </a>

            <a href="<?= e(url('/customer/my_bookings.php')) ?>">
                <i class="fa-solid fa-calendar-check"></i>
                My Bookings
            </a>

            <a href="<?= e(url('/customer/feedback.php')) ?>">
                <i class="fa-solid fa-star"></i>
                Feedback
            </a>

            <a href="<?= e(url('/customer/profile.php')) ?>">
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

                    <h1>Book Your Event</h1>

                    <p>
                        Select your package, venue,
                        services and wedding-event details.
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

                <a href="<?= e(url('/customer/profile.php')) ?>">
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
                class="booking-form-alert <?= $flash['type'] === 'success'
                    ? 'booking-form-alert-success'
                    : 'booking-form-alert-danger' ?>"
            >
                <?= e($flash['message']) ?>

                <?php if ($flash['type'] === 'success'): ?>

                    <div class="customer-booking-success-actions">

                        <a href="<?= e(url('/customer/my_bookings.php')) ?>">
                            View My Bookings
                        </a>

                        <a href="<?= e(url('/customer/packages.php')) ?>">
                            Browse Packages
                        </a>

                    </div>

                <?php endif; ?>

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

        <?php if ($packages === [] || $venues === []): ?>

            <section class="booking-form-box">

                <div class="booking-data-empty">

                    <i class="fa-solid fa-triangle-exclamation"></i>

                    <h3>
                        Booking information is incomplete
                    </h3>

                    <p>
                        At least one active package and one
                        active venue are required before
                        you can submit a booking.
                    </p>

                </div>

            </section>

        <?php else: ?>

            <div class="booking-form-layout">

                <section class="booking-form-box">

                    <div class="booking-form-heading">

                        <h2>Wedding Booking Form</h2>

                        <p>
                            Your request will be saved as
                            Pending until it is reviewed by
                            the wedding planning team.
                        </p>

                    </div>

                    <div class="customer-booking-account">

                        <img
                            src="<?= e($customerImage) ?>"
                            alt="Customer account"
                        >

                        <div>

                            <h3>
                                <?= e($customer['full_name']) ?>
                            </h3>

                            <div class="customer-booking-account-details">

                                <span>
                                    <i class="fa-solid fa-envelope"></i>
                                    <?= e($customer['email']) ?>
                                </span>

                                <span>
                                    <i class="fa-solid fa-phone"></i>

                                    <?= e(
                                        (string) (
                                            $customer['phone']
                                            ?: 'Phone not provided'
                                        )
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    </div>

                    <div class="customer-booking-status-note">

                        <i class="fa-solid fa-circle-info"></i>

                        <span>
                            Customer bookings are submitted
                            with Pending status. The booking
                            team will review the selected
                            venue, date and event details
                            before confirming the booking.
                        </span>

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
                                    Select a venue and date.
                                    Availability will be
                                    checked when you submit.
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

                            <div class="booking-field full-width">

                                <label for="customer_address">
                                    Your Address
                                </label>

                                <textarea
                                    id="customer_address"
                                    name="customer_address"
                                    maxlength="1000"
                                    placeholder="Enter your complete address"
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
                                    placeholder="Write any special requests or important event details"
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
                                Submit Booking Request
                            </button>

                            <a
                                class="booking-cancel-button"
                                href="<?= e(
                                    url('/customer/packages.php')
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
                        All prices are calculated securely
                        from the database. Your booking
                        will initially be submitted with
                        Pending status.
                    </div>

                </aside>

            </div>

        <?php endif; ?>

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
            packageSelect
            && venueSelect
            && eventDateInput
            && guestCountInput
        ) {
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

                document.getElementById(
                    "summaryPackageName"
                ).textContent =
                    selectedPackage
                        ? selectedPackage.name
                        : "Not selected";

                document.getElementById(
                    "summaryPackagePrice"
                ).textContent =
                    formatMoney(packagePrice);

                document.getElementById(
                    "summaryVenueName"
                ).textContent =
                    selectedVenue
                        ? selectedVenue.name
                            + " — "
                            + selectedVenue.location
                        : "Not selected";

                document.getElementById(
                    "summaryVenuePrice"
                ).textContent =
                    formatMoney(venuePrice);

                document.getElementById(
                    "summaryServicesPrice"
                ).textContent =
                    formatMoney(servicesPrice);

                document.getElementById(
                    "summaryTotal"
                ).textContent =
                    formatMoney(total);

                document.getElementById(
                    "summaryPackageCapacity"
                ).textContent =
                    selectedPackage
                        ? selectedPackage.capacity
                        : 0;

                document.getElementById(
                    "summaryVenueCapacity"
                ).textContent =
                    selectedVenue
                        ? selectedVenue.capacity
                        : 0;

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
                    selectedPackageImage.src =
                        selectedPackage.image;

                    selectedPackageName.textContent =
                        selectedPackage.name;

                    selectedPackageCapacity.textContent =
                        "Capacity: "
                        + selectedPackage.capacity
                        + " guests";

                    selectedPackagePrice.textContent =
                        formatMoney(
                            selectedPackage.price
                        );
                } else {
                    selectedPackageImage.src =
                        "<?= e(
                            url(
                                '/assets/icons/icon-512.png'
                            )
                        ) ?>";

                    selectedPackageName.textContent =
                        "Select a package";

                    selectedPackageCapacity.textContent =
                        "Choose an active wedding package.";

                    selectedPackagePrice.textContent =
                        "Rs. 0";
                }

                let capacityMessage =
                    "Guest count must fit both the selected package and venue.";

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
                        "Maximum allowed guests for this combination: "
                        + allowedCapacity
                        + ".";

                    guestCountInput.max =
                        String(allowedCapacity);
                } else {
                    guestCountInput.removeAttribute(
                        "max"
                    );
                }

                document.getElementById(
                    "guestCapacityHelp"
                ).textContent =
                    capacityMessage;
            }

            function updateAvailabilityMessage() {
                const message =
                    document.getElementById(
                        "venueAvailabilityMessage"
                    );

                if (
                    venueSelect.value
                    && eventDateInput.value
                ) {
                    message.textContent =
                        "The selected venue will be checked for "
                        + eventDateInput.value
                        + " when you submit the booking.";
                } else {
                    message.textContent =
                        "Select a venue and date. Availability will be checked when you submit.";
                }

                message.classList.add(
                    "visible"
                );
            }

            packageSelect.addEventListener(
                "change",
                updateBookingSummary
            );

            venueSelect.addEventListener(
                "change",
                function () {
                    updateBookingSummary();
                    updateAvailabilityMessage();
                }
            );

            eventDateInput.addEventListener(
                "change",
                updateAvailabilityMessage
            );

            serviceCheckboxes.forEach(
                function (checkbox) {
                    checkbox.addEventListener(
                        "change",
                        updateBookingSummary
                    );
                }
            );

            updateBookingSummary();
            updateAvailabilityMessage();
        }
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>