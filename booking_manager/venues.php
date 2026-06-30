<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/venue_helpers.php';

require_role('booking_manager');

$connection = db();
$bookingManagerId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Venue value helpers
|--------------------------------------------------------------------------
*/

$venuePrice = static function (array $venue): float {
    foreach (
        [
            'price',
            'starting_price',
            'rental_price',
            'venue_price',
        ] as $column
    ) {
        if (
            isset($venue[$column])
            && is_numeric($venue[$column])
        ) {
            return (float) $venue[$column];
        }
    }

    return 0;
};

$venueCapacity = static function (array $venue): int {
    foreach (
        [
            'capacity',
            'guest_capacity',
            'maximum_capacity',
            'max_capacity',
        ] as $column
    ) {
        if (
            isset($venue[$column])
            && is_numeric($venue[$column])
        ) {
            return (int) $venue[$column];
        }
    }

    return 0;
};

$venueFacilities = static function (
    mixed $rawFacilities
): array {
    if (is_array($rawFacilities)) {
        $facilities = $rawFacilities;
    } else {
        $facilityText = trim(
            (string) $rawFacilities
        );

        if ($facilityText === '') {
            return [];
        }

        $decodedFacilities = json_decode(
            $facilityText,
            true
        );

        if (
            json_last_error() === JSON_ERROR_NONE
            && is_array($decodedFacilities)
        ) {
            $facilities = $decodedFacilities;
        } else {
            $facilities = preg_split(
                '/[\r\n,;|]+/u',
                $facilityText
            ) ?: [];
        }
    }

    $cleanFacilities = [];

    foreach ($facilities as $facility) {
        $facility = trim(
            (string) $facility
        );

        if ($facility === '') {
            continue;
        }

        $cleanFacilities[] = $facility;
    }

    return array_values(
        array_unique($cleanFacilities)
    );
};

$formatVenuePrice = static function (
    float $price
): string {
    return 'Rs. ' . number_format(
        $price,
        0
    );
};

/*
|--------------------------------------------------------------------------
| Load Booking Manager
|--------------------------------------------------------------------------
*/

$managerStatement = $connection->prepare(
    'SELECT
        full_name,
        email,
        profile_image,
        about
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

$managerAbout = trim(
    (string) ($manager['about'] ?? '')
);

if ($managerAbout === '') {
    $managerAbout = 'Booking Manager';
}

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

$unreadNotifications =
    (int) $notificationStatement->fetchColumn();

/*
|--------------------------------------------------------------------------
| Active venue records
|--------------------------------------------------------------------------
*/

$activeVenueStatement = $connection->query(
    "SELECT *
     FROM venues
     WHERE status = 'active'
     ORDER BY created_at DESC, id DESC"
);

$activeVenueRows =
    $activeVenueStatement->fetchAll();

$activeVenues = count(
    $activeVenueRows
);

$startingPrice = 0.0;
$maximumCapacity = 0;

if ($activeVenueRows !== []) {
    $venuePrices = array_map(
        $venuePrice,
        $activeVenueRows
    );

    $positivePrices = array_values(
        array_filter(
            $venuePrices,
            static fn (float $price): bool =>
                $price > 0
        )
    );

    if ($positivePrices !== []) {
        $startingPrice = min(
            $positivePrices
        );
    }

    $maximumCapacity = max(
        array_map(
            $venueCapacity,
            $activeVenueRows
        )
    );
}

$venues = array_slice(
    $activeVenueRows,
    0,
    3
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
        View Venues | <?= e(APP_NAME) ?>
    </title>

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/booking_manager_dashboard.css')
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/booking_manager_views.css')
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/booking_manager_venues.css')
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
                <?= e(
                    (string) $manager['full_name']
                ) ?>
            </h2>

            <p>
                <?= e($managerAbout) ?>
            </p>

            <div class="booking-online">
                ● Online
            </div>

        </div>

        <nav class="booking-menu">

            <a href="<?= e(
                url('/booking_manager/dashboard.php')
            ) ?>">
                <i class="fa-solid fa-gauge"></i>
                Dashboard
            </a>

            <a href="<?= e(
                url('/booking_manager/bookings.php')
            ) ?>">
                <i class="fa-solid fa-calendar-check"></i>
                Manage Bookings
            </a>

            <a href="<?= e(
                url('/booking_manager/booking.php')
            ) ?>">
                <i class="fa-solid fa-calendar-plus"></i>
                Create Booking
            </a>

            <a href="<?= e(
                url('/booking_manager/services.php')
            ) ?>">
                <i class="fa-solid fa-bell-concierge"></i>
                View Services
            </a>

            <a href="<?= e(
                url('/booking_manager/gallery.php')
            ) ?>">
                <i class="fa-solid fa-images"></i>
                View Gallery
            </a>

            <a href="<?= e(
                url('/booking_manager/packages.php')
            ) ?>">
                <i class="fa-solid fa-gift"></i>
                View Packages
            </a>

            <a
                class="active"
                href="<?= e(
                    url('/booking_manager/venues.php')
                ) ?>"
            >
                <i class="fa-solid fa-hotel"></i>
                View Venues
            </a>

            <a href="<?= e(
                url('/booking_manager/profile.php')
            ) ?>">
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a href="<?= e(
                url('/booking_manager/notifications.php')
            ) ?>">
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

                    <h1>Wedding Venues</h1>

                    <p>
                        View venues, facilities, capacity and date-based availability.
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
                        url('/booking_manager/notifications.php')
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

                <a href="<?= e(
                    url('/booking_manager/profile.php')
                ) ?>">
                    <img
                        class="booking-profile-image"
                        src="<?= e($managerImage) ?>"
                        alt="Booking Manager profile"
                    >
                </a>

            </div>

        </header>

        <section class="manager-view-summary">

            <article class="manager-view-summary-card">

                <div class="manager-view-summary-icon">
                    <i class="fa-solid fa-hotel"></i>
                </div>

                <div>
                    <h4>Active Venues</h4>

                    <h2>
                        <?= e(
                            (string) $activeVenues
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="manager-view-summary-card">

                <div class="manager-view-summary-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>

                <div>
                    <h4>Date Availability</h4>
                    <h2>Live Check</h2>
                </div>

            </article>

            <article class="manager-view-summary-card">

                <div class="manager-view-summary-icon">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>

                <div>
                    <h4>Starting Price</h4>

                    <h2>
                        <?= e(
                            $formatVenuePrice(
                                $startingPrice
                            )
                        ) ?>
                    </h2>
                </div>

            </article>

        </section>

        <section class="manager-venue-box">

            <div class="manager-venue-heading">

                <div>

                    <h2>
                        Available Wedding Venues
                    </h2>

                    <p>
                        Maximum venue capacity:
                        <?= e(
                            number_format(
                                $maximumCapacity
                            )
                        ) ?>
                        guests. Select a venue to view its complete details.
                    </p>

                </div>

                <a
                    class="manager-venue-view-all"
                    href="<?= e(
                        url('/booking_manager/all_venues.php')
                    ) ?>"
                >
                    <i class="fa-solid fa-table-cells-large"></i>
                    View All Venues
                </a>

            </div>

            <?php if ($venues === []): ?>

                <div class="manager-venue-empty">

                    <i class="fa-solid fa-hotel"></i>

                    <h3>No active venues found</h3>

                    <p>
                        Venues activated by the Admin will appear here automatically.
                    </p>

                </div>

            <?php else: ?>

                <div class="manager-venue-grid">

                    <?php foreach ($venues as $venue): ?>
                        <?php
                        $venueId =
                            (int) $venue['id'];

                        $mainImage =
                            venue_image_url(
                                $venue['main_image']
                                ?? null
                            );

                        $previewImages = [
                            [
                                'url' => $mainImage,
                                'is_main' => true,
                            ],
                        ];

                        foreach (
                            [
                                'image_one',
                                'image_two',
                                'image_three',
                            ] as $imageColumn
                        ) {
                            $imagePath = trim(
                                (string) (
                                    $venue[$imageColumn]
                                    ?? ''
                                )
                            );

                            if ($imagePath === '') {
                                continue;
                            }

                            $previewImages[] = [
                                'url' =>
                                    venue_image_url(
                                        $imagePath
                                    ),

                                'is_main' => false,
                            ];
                        }

                        $venueName = trim(
                            (string) (
                                $venue['name']
                                ?? ''
                            )
                        );

                        if ($venueName === '') {
                            $venueName =
                                'Untitled Venue';
                        }

                        $location = trim(
                            (string) (
                                $venue['location']
                                ?? ''
                            )
                        );

                        if ($location === '') {
                            $location =
                                'Location not specified';
                        }

                        $description = trim(
                            (string) (
                                $venue['description']
                                ?? ''
                            )
                        );

                        if ($description === '') {
                            $description =
                                venue_card_description(
                                    $venue
                                );
                        }

                        if ($description === '') {
                            $description =
                                'Wedding venue available for customer bookings.';
                        }

                        $price =
                            $venuePrice($venue);

                        $capacity =
                            $venueCapacity($venue);

                        $facilities =
                            $venueFacilities(
                                $venue['facilities']
                                ?? ''
                            );

                        $facilityText =
                            $facilities !== []
                                ? implode(
                                    '||',
                                    $facilities
                                )
                                : '';

                        $imageUrls = array_map(
                            static fn (
                                array $image
                            ): string =>
                                (string) $image['url'],

                            $previewImages
                        );
                        ?>

                        <article class="manager-venue-card">

                            <div class="manager-venue-main-image-wrap">

                                <img
                                    class="manager-venue-main-image"
                                    id="managerVenueMain<?= e(
                                        (string) $venueId
                                    ) ?>"
                                    src="<?= e($mainImage) ?>"
                                    alt="<?= e($venueName) ?>"
                                >

                                <span class="manager-venue-main-badge">
                                    <i class="fa-regular fa-image"></i>
                                    Main Photo
                                </span>

                            </div>

                            <div class="manager-venue-card-body">

                                <span class="manager-venue-status">
                                    Date Based
                                </span>

                                <h3>
                                    <?= e($venueName) ?>
                                </h3>

                                <div class="manager-venue-location">

                                    <i class="fa-solid fa-location-dot"></i>

                                    <span>
                                        <?= e($location) ?>
                                    </span>

                                </div>

                                <strong class="manager-venue-price">

                                    <?= e(
                                        $formatVenuePrice(
                                            $price
                                        )
                                    ) ?>

                                </strong>

                                <p class="manager-venue-description">
                                    <?= e($description) ?>
                                </p>

                                <div class="manager-venue-thumbnails">

                                    <?php foreach (
                                        $previewImages as
                                        $index => $previewImage
                                    ): ?>

                                        <button
                                            class="manager-venue-thumbnail <?= $index === 0
                                                ? 'active'
                                                : '' ?>"
                                            type="button"
                                            data-venue-target="managerVenueMain<?= e(
                                                (string) $venueId
                                            ) ?>"
                                            data-venue-image="<?= e(
                                                (string) $previewImage['url']
                                            ) ?>"
                                            data-venue-is-main="<?= $previewImage['is_main']
                                                ? 'true'
                                                : 'false' ?>"
                                            aria-label="<?= $previewImage['is_main']
                                                ? 'Show original main venue photo'
                                                : 'Show venue gallery photo '
                                                    . e((string) $index) ?>"
                                        >

                                            <img
                                                src="<?= e(
                                                    (string) $previewImage['url']
                                                ) ?>"
                                                alt="<?= $previewImage['is_main']
                                                    ? 'Original main venue photo'
                                                    : 'Venue gallery photo '
                                                        . e((string) $index) ?>"
                                            >

                                            <?php if (
                                                $previewImage['is_main']
                                            ): ?>

                                                <span>
                                                    Main
                                                </span>

                                            <?php endif; ?>

                                        </button>

                                    <?php endforeach; ?>

                                </div>

                                <ul class="manager-venue-details">

                                    <li>
                                        <i class="fa-solid fa-users"></i>

                                        Capacity:
                                        <?= e(
                                            number_format(
                                                $capacity
                                            )
                                        ) ?>
                                        guests
                                    </li>

                                    <?php if (
                                        $facilities !== []
                                    ): ?>

                                        <?php foreach (
                                            array_slice(
                                                $facilities,
                                                0,
                                                2
                                            ) as $facility
                                        ): ?>

                                            <li>
                                                <i class="fa-solid fa-check"></i>
                                                <?= e($facility) ?>
                                            </li>

                                        <?php endforeach; ?>

                                    <?php else: ?>

                                        <li>
                                            <i class="fa-solid fa-check"></i>
                                            Wedding facilities available
                                        </li>

                                    <?php endif; ?>

                                </ul>

                                <div class="manager-venue-actions">

                                    <button
                                        class="manager-venue-details-button"
                                        type="button"
                                        data-venue-details
                                        data-id="<?= e(
                                            (string) $venueId
                                        ) ?>"
                                        data-name="<?= e(
                                            $venueName
                                        ) ?>"
                                        data-location="<?= e(
                                            $location
                                        ) ?>"
                                        data-price="<?= e(
                                            $formatVenuePrice(
                                                $price
                                            )
                                        ) ?>"
                                        data-capacity="<?= e(
                                            number_format(
                                                $capacity
                                            )
                                        ) ?>"
                                        data-description="<?= e(
                                            $description
                                        ) ?>"
                                        data-facilities="<?= e(
                                            $facilityText
                                        ) ?>"
                                        data-images="<?= e(
                                            (string) json_encode(
                                                $imageUrls,
                                                JSON_UNESCAPED_SLASHES
                                                | JSON_UNESCAPED_UNICODE
                                            )
                                        ) ?>"
                                    >
                                        View Details
                                    </button>

                                    <a
                                        class="manager-venue-book-button"
                                        href="<?= e(
                                            url(
                                                '/booking_manager/booking.php?venue_id='
                                                . $venueId
                                            )
                                        ) ?>"
                                    >
                                        Book Venue
                                    </a>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <footer class="manager-view-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner.
            All rights reserved.
        </footer>

    </main>

    <div
        class="manager-venue-modal"
        id="managerVenueModal"
        aria-hidden="true"
    >

        <div class="manager-venue-modal-content">

            <button
                class="manager-venue-modal-close"
                id="managerVenueModalClose"
                type="button"
                aria-label="Close venue details"
            >
                &times;
            </button>

            <div class="manager-venue-modal-grid">

                <div>

                    <div class="manager-venue-modal-image-wrap">

                        <img
                            id="managerVenueModalMainImage"
                            src=""
                            alt="Venue image"
                        >

                        <span id="managerVenueModalMainBadge">

                            <i class="fa-regular fa-image"></i>
                            Main Photo

                        </span>

                    </div>

                    <div id="managerVenueModalThumbnails"></div>

                </div>

                <div class="manager-venue-modal-info">

                    <h2 id="managerVenueModalName"></h2>

                    <div id="managerVenueModalPrice"></div>

                    <p id="managerVenueModalDescription"></p>

                    <div class="manager-venue-information-row">

                        <strong>Location:</strong>

                        <span id="managerVenueModalLocation"></span>

                    </div>

                    <div class="manager-venue-information-row">

                        <strong>Capacity:</strong>

                        <span id="managerVenueModalCapacity"></span>

                    </div>

                    <div class="manager-venue-information-row">

                        <strong>Availability:</strong>

                        <span>
                            Date-based availability check
                        </span>

                    </div>

                    <div class="manager-venue-facilities-box">

                        <h3>Venue Facilities</h3>

                        <ul id="managerVenueModalFacilities"></ul>

                    </div>

                    <a
                        id="managerVenueModalBook"
                        href="#"
                    >
                        Book This Venue
                    </a>

                </div>

            </div>

        </div>

    </div>

    <script>
        "use strict";

        const bookingSidebar = document.getElementById(
            "bookingSidebar"
        );

        const bookingSidebarOverlay = document.getElementById(
            "bookingSidebarOverlay"
        );

        const bookingMenuButton = document.getElementById(
            "bookingMenuButton"
        );

        function closeBookingSidebar() {
            bookingSidebar?.classList.remove("open");

            bookingSidebarOverlay?.classList.remove(
                "open"
            );
        }

        bookingMenuButton?.addEventListener(
            "click",
            function () {
                bookingSidebar?.classList.toggle(
                    "open"
                );

                bookingSidebarOverlay?.classList.toggle(
                    "open"
                );
            }
        );

        bookingSidebarOverlay?.addEventListener(
            "click",
            closeBookingSidebar
        );

        document.addEventListener(
            "click",
            function (event) {
                const thumbnail = event.target.closest(
                    ".manager-venue-thumbnail"
                );

                if (!thumbnail) {
                    return;
                }

                const mainImage = document.getElementById(
                    thumbnail.dataset.venueTarget || ""
                );

                if (
                    !mainImage
                    || !thumbnail.dataset.venueImage
                ) {
                    return;
                }

                mainImage.src =
                    thumbnail.dataset.venueImage;

                const card = thumbnail.closest(
                    ".manager-venue-card"
                );

                card
                    ?.querySelectorAll(
                        ".manager-venue-thumbnail"
                    )
                    .forEach(function (item) {
                        item.classList.remove(
                            "active"
                        );
                    });

                thumbnail.classList.add("active");

                const badge = card?.querySelector(
                    ".manager-venue-main-badge"
                );

                badge?.classList.toggle(
                    "hidden",
                    thumbnail.dataset.venueIsMain
                        !== "true"
                );
            }
        );

        const venueModal =
            document.getElementById(
                "managerVenueModal"
            );

        const venueModalClose =
            document.getElementById(
                "managerVenueModalClose"
            );

        const venueModalMainImage =
            document.getElementById(
                "managerVenueModalMainImage"
            );

        const venueModalMainBadge =
            document.getElementById(
                "managerVenueModalMainBadge"
            );

        const venueModalThumbnails =
            document.getElementById(
                "managerVenueModalThumbnails"
            );

        const venueModalName =
            document.getElementById(
                "managerVenueModalName"
            );

        const venueModalPrice =
            document.getElementById(
                "managerVenueModalPrice"
            );

        const venueModalDescription =
            document.getElementById(
                "managerVenueModalDescription"
            );

        const venueModalLocation =
            document.getElementById(
                "managerVenueModalLocation"
            );

        const venueModalCapacity =
            document.getElementById(
                "managerVenueModalCapacity"
            );

        const venueModalFacilities =
            document.getElementById(
                "managerVenueModalFacilities"
            );

        const venueModalBook =
            document.getElementById(
                "managerVenueModalBook"
            );

        function renderVenueModalImages(images) {
            venueModalThumbnails.innerHTML = "";

            images.forEach(
                function (imageUrl, index) {
                    const button =
                        document.createElement(
                            "button"
                        );

                    const image =
                        document.createElement(
                            "img"
                        );

                    button.type = "button";

                    button.className =
                        "manager-venue-modal-thumbnail";

                    if (index === 0) {
                        button.classList.add(
                            "active"
                        );
                    }

                    image.src = imageUrl;

                    image.alt = index === 0
                        ? "Original main venue photo"
                        : "Venue gallery photo "
                            + index;

                    button.appendChild(image);

                    if (index === 0) {
                        const label =
                            document.createElement(
                                "span"
                            );

                        label.textContent =
                            "Main";

                        button.appendChild(
                            label
                        );
                    }

                    button.addEventListener(
                        "click",
                        function () {
                            venueModalMainImage.src =
                                imageUrl;

                            venueModalThumbnails
                                .querySelectorAll(
                                    ".manager-venue-modal-thumbnail"
                                )
                                .forEach(
                                    function (item) {
                                        item.classList.remove(
                                            "active"
                                        );
                                    }
                                );

                            button.classList.add(
                                "active"
                            );

                            venueModalMainBadge.classList.toggle(
                                "hidden",
                                index !== 0
                            );
                        }
                    );

                    venueModalThumbnails.appendChild(
                        button
                    );
                }
            );
        }

        document
            .querySelectorAll(
                "[data-venue-details]"
            )
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        let images = [];

                        try {
                            images = JSON.parse(
                                button.dataset.images
                                || "[]"
                            );
                        } catch (error) {
                            images = [];
                        }

                        venueModalName.textContent =
                            button.dataset.name
                            || "Venue";

                        venueModalPrice.textContent =
                            button.dataset.price
                            || "";

                        venueModalDescription.textContent =
                            button.dataset.description
                            || "";

                        venueModalLocation.textContent =
                            button.dataset.location
                            || "Not specified";

                        venueModalCapacity.textContent =
                            (
                                button.dataset.capacity
                                || "0"
                            )
                            + " guests";

                        venueModalBook.href =
                            "<?= e(
                                url(
                                    '/booking_manager/booking.php?venue_id='
                                )
                            ) ?>"
                            + (
                                button.dataset.id
                                || ""
                            );

                        venueModalFacilities.innerHTML =
                            "";

                        const facilities =
                            button.dataset.facilities
                                ? button.dataset.facilities.split(
                                    "||"
                                )
                                : [];

                        if (facilities.length === 0) {
                            const item =
                                document.createElement(
                                    "li"
                                );

                            item.textContent =
                                "No facilities have been listed.";

                            venueModalFacilities.appendChild(
                                item
                            );
                        } else {
                            facilities.forEach(
                                function (facility) {
                                    const item =
                                        document.createElement(
                                            "li"
                                        );

                                    const icon =
                                        document.createElement(
                                            "i"
                                        );

                                    const text =
                                        document.createElement(
                                            "span"
                                        );

                                    icon.className =
                                        "fa-solid fa-check";

                                    text.textContent =
                                        facility;

                                    item.append(
                                        icon,
                                        text
                                    );

                                    venueModalFacilities.appendChild(
                                        item
                                    );
                                }
                            );
                        }

                        if (images.length > 0) {
                            venueModalMainImage.src =
                                images[0];

                            venueModalMainBadge.classList.remove(
                                "hidden"
                            );

                            renderVenueModalImages(
                                images
                            );
                        }

                        venueModal.classList.add(
                            "open"
                        );

                        venueModal.setAttribute(
                            "aria-hidden",
                            "false"
                        );

                        document.body.classList.add(
                            "manager-venue-modal-open"
                        );
                    }
                );
            });

        function closeVenueModal() {
            venueModal.classList.remove(
                "open"
            );

            venueModal.setAttribute(
                "aria-hidden",
                "true"
            );

            document.body.classList.remove(
                "manager-venue-modal-open"
            );
        }

        venueModalClose?.addEventListener(
            "click",
            closeVenueModal
        );

        venueModal?.addEventListener(
            "click",
            function (event) {
                if (event.target === venueModal) {
                    closeVenueModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (event.key === "Escape") {
                    closeVenueModal();
                }
            }
        );
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>