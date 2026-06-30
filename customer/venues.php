<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/venue_helpers.php';

require_role('customer');

$connection = db();
$customerId = (int) $_SESSION['user_id'];

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

        if ($facility !== '') {
            $cleanFacilities[] = $facility;
        }
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
| Load customer profile
|--------------------------------------------------------------------------
*/

$customerStatement = $connection->prepare(
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

$customerAbout = trim(
    (string) ($customer['about'] ?? '')
);

if ($customerAbout === '') {
    $customerAbout = 'Customer Account';
}

/*
|--------------------------------------------------------------------------
| Active venue records and summary
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
    $positivePrices = array_values(
        array_filter(
            array_map(
                $venuePrice,
                $activeVenueRows
            ),
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
        Browse Venues | <?= e(APP_NAME) ?>
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
            url('/assets/css/customer_venues.css')
        ) ?>"
    >
</head>

<body class="customer-venue-page">

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
                    (string) $customer['full_name']
                ) ?>
            </h2>

            <p><?= e($customerAbout) ?></p>

            <div class="customer-online">
                ● Online
            </div>

        </div>

        <nav class="customer-menu">

            <a href="<?= e(
                url('/customer/dashboard.php')
            ) ?>">
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a href="<?= e(
                url('/customer/packages.php')
            ) ?>">
                <i class="fa-solid fa-gift"></i>
                Browse Packages
            </a>

            <a
                class="active"
                href="<?= e(
                    url('/customer/venues.php')
                ) ?>"
            >
                <i class="fa-solid fa-hotel"></i>
                Browse Venues
            </a>

            <a href="<?= e(
                url('/customer/gallery.php')
            ) ?>">
                <i class="fa-solid fa-images"></i>
                Wedding Gallery
            </a>

            <a href="<?= e(
                url('/customer/booking.php')
            ) ?>">
                <i class="fa-solid fa-calendar-plus"></i>
                Book Event
            </a>

            <a href="<?= e(
                url('/customer/my_bookings.php')
            ) ?>">
                <i class="fa-solid fa-calendar-check"></i>
                My Bookings
            </a>

            <a href="<?= e(
                url('/customer/feedback.php')
            ) ?>">
                <i class="fa-solid fa-star"></i>
                Feedback
            </a>

            <a href="<?= e(
                url('/customer/profile.php')
            ) ?>">
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a
                class="customer-logout"
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
        class="customer-sidebar-overlay"
        id="customerSidebarOverlay"
    ></div>

    <main class="customer-main">

        <header class="customer-venue-topbar">

            <div class="customer-venue-topbar-left">

                <button
                    class="customer-menu-button"
                    id="customerMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>

                    <h1>Wedding Venues</h1>

                    <p>
                        Explore venues, compare prices and check availability for your event date.
                    </p>

                </div>

            </div>

            <div class="customer-venue-topbar-right">

                <div class="customer-venue-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="customer-venue-public-link"
                    href="<?= e(url('/index.php')) ?>"
                    aria-label="Open public website"
                >
                    <i class="fa-solid fa-globe"></i>
                </a>

                <a href="<?= e(
                    url('/customer/profile.php')
                ) ?>">
                    <img
                        class="customer-venue-profile-image"
                        src="<?= e($customerImage) ?>"
                        alt="Customer profile"
                    >
                </a>

            </div>

        </header>

        <section class="customer-venue-summary">

            <article class="customer-venue-summary-card">

                <div class="customer-venue-summary-icon">
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

            <article class="customer-venue-summary-card">

                <div class="customer-venue-summary-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>

                <div>
                    <h4>Date Availability</h4>
                    <h2>Live Check</h2>
                </div>

            </article>

            <article class="customer-venue-summary-card">

                <div class="customer-venue-summary-icon">
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

            <article class="customer-venue-summary-card">

                <div class="customer-venue-summary-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <div>
                    <h4>Maximum Capacity</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $maximumCapacity
                            )
                        ) ?>
                    </h2>
                </div>

            </article>

        </section>

        <section class="customer-venue-section">

            <div class="customer-venue-section-heading">

                <div>

                    <h2>
                        Available Wedding Venues
                    </h2>

                    <p>
                        View venue images, locations, capacity and facilities, then select a venue for your booking.
                    </p>

                </div>

                <a
                    class="customer-venue-view-all"
                    href="<?= e(
                        url('/customer/all_venues.php')
                    ) ?>"
                >
                    <i class="fa-solid fa-table-cells-large"></i>
                    View All Venues
                </a>

            </div>

            <?php if ($venues === []): ?>

                <div class="customer-venue-empty">

                    <i class="fa-solid fa-hotel"></i>

                    <h3>No active venues found</h3>

                    <p>
                        Venues activated by the Admin will appear here automatically.
                    </p>

                </div>

            <?php else: ?>

                <div class="customer-venue-grid">

                    <?php foreach ($venues as $venue): ?>
                        <?php
                        $venueId =
                            (int) $venue['id'];

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

                        <article class="customer-venue-card">

                            <div class="customer-venue-main-wrap">

                                <img
                                    class="customer-venue-main-image"
                                    id="customerVenueMain<?= e(
                                        (string) $venueId
                                    ) ?>"
                                    src="<?= e($mainImage) ?>"
                                    alt="<?= e($venueName) ?>"
                                >

                                <span class="customer-venue-main-badge">
                                    <i class="fa-regular fa-image"></i>
                                    Main Photo
                                </span>

                            </div>

                            <div class="customer-venue-card-body">

                                <span class="customer-venue-status">
                                    Date Based
                                </span>

                                <div class="customer-venue-title-row">

                                    <h3>
                                        <?= e($venueName) ?>
                                    </h3>

                                    <strong>
                                        <?= e(
                                            $formatVenuePrice(
                                                $price
                                            )
                                        ) ?>
                                    </strong>

                                </div>

                                <div class="customer-venue-location">

                                    <i class="fa-solid fa-location-dot"></i>

                                    <span>
                                        <?= e($location) ?>
                                    </span>

                                </div>

                                <p class="customer-venue-description">
                                    <?= e($description) ?>
                                </p>

                                <div class="customer-venue-thumbnails">

                                    <?php foreach (
                                        $previewImages as
                                        $index => $previewImage
                                    ): ?>

                                        <button
                                            class="customer-venue-thumbnail <?= $index === 0
                                                ? 'active'
                                                : '' ?>"
                                            type="button"
                                            data-venue-target="customerVenueMain<?= e(
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

                                                <span>Main</span>

                                            <?php endif; ?>

                                        </button>

                                    <?php endforeach; ?>

                                </div>

                                <ul class="customer-venue-details">

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

                                <div class="customer-venue-actions">

                                    <button
                                        class="customer-venue-details-button"
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
                                        class="customer-venue-book-button"
                                        href="<?= e(
                                            url(
                                                '/customer/booking.php?venue_id='
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

        <footer class="customer-venue-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <div
        class="customer-venue-modal"
        id="customerVenueModal"
        aria-hidden="true"
    >

        <div class="customer-venue-modal-content">

            <button
                class="customer-venue-modal-close"
                id="customerVenueModalClose"
                type="button"
                aria-label="Close venue details"
            >
                &times;
            </button>

            <div class="customer-venue-modal-grid">

                <div>

                    <div class="customer-venue-modal-image-wrap">

                        <img
                            id="customerVenueModalMainImage"
                            src=""
                            alt="Venue image"
                        >

                        <span id="customerVenueModalMainBadge">
                            <i class="fa-regular fa-image"></i>
                            Main Photo
                        </span>

                    </div>

                    <div id="customerVenueModalThumbnails"></div>

                </div>

                <div class="customer-venue-modal-info">

                    <h2 id="customerVenueModalName"></h2>

                    <div id="customerVenueModalPrice"></div>

                    <p id="customerVenueModalDescription"></p>

                    <div class="customer-venue-information-row">
                        <strong>Location:</strong>
                        <span id="customerVenueModalLocation"></span>
                    </div>

                    <div class="customer-venue-information-row">
                        <strong>Capacity:</strong>
                        <span id="customerVenueModalCapacity"></span>
                    </div>

                    <div class="customer-venue-information-row">
                        <strong>Availability:</strong>
                        <span>Date-based availability check</span>
                    </div>

                    <div class="customer-venue-facilities-box">

                        <h3>Venue Facilities</h3>

                        <ul id="customerVenueModalFacilities"></ul>

                    </div>

                    <a
                        id="customerVenueModalBook"
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
            customerSidebar?.classList.remove(
                "open"
            );

            customerSidebarOverlay?.classList.remove(
                "open"
            );
        }

        customerMenuButton?.addEventListener(
            "click",
            function () {
                customerSidebar?.classList.toggle(
                    "open"
                );

                customerSidebarOverlay?.classList.toggle(
                    "open"
                );
            }
        );

        customerSidebarOverlay?.addEventListener(
            "click",
            closeCustomerSidebar
        );

        document.addEventListener(
            "click",
            function (event) {
                const thumbnail =
                    event.target.closest(
                        ".customer-venue-thumbnail"
                    );

                if (!thumbnail) {
                    return;
                }

                const mainImage =
                    document.getElementById(
                        thumbnail.dataset.venueTarget
                        || ""
                    );

                if (
                    !mainImage
                    || !thumbnail.dataset.venueImage
                ) {
                    return;
                }

                mainImage.src =
                    thumbnail.dataset.venueImage;

                const card =
                    thumbnail.closest(
                        ".customer-venue-card"
                    );

                card
                    ?.querySelectorAll(
                        ".customer-venue-thumbnail"
                    )
                    .forEach(function (item) {
                        item.classList.remove(
                            "active"
                        );
                    });

                thumbnail.classList.add(
                    "active"
                );

                const badge =
                    card?.querySelector(
                        ".customer-venue-main-badge"
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
                "customerVenueModal"
            );

        const venueModalClose =
            document.getElementById(
                "customerVenueModalClose"
            );

        const modalMainImage =
            document.getElementById(
                "customerVenueModalMainImage"
            );

        const modalMainBadge =
            document.getElementById(
                "customerVenueModalMainBadge"
            );

        const modalThumbnails =
            document.getElementById(
                "customerVenueModalThumbnails"
            );

        const modalName =
            document.getElementById(
                "customerVenueModalName"
            );

        const modalPrice =
            document.getElementById(
                "customerVenueModalPrice"
            );

        const modalDescription =
            document.getElementById(
                "customerVenueModalDescription"
            );

        const modalLocation =
            document.getElementById(
                "customerVenueModalLocation"
            );

        const modalCapacity =
            document.getElementById(
                "customerVenueModalCapacity"
            );

        const modalFacilities =
            document.getElementById(
                "customerVenueModalFacilities"
            );

        const modalBook =
            document.getElementById(
                "customerVenueModalBook"
            );

        function renderVenueModalImages(images) {
            modalThumbnails.innerHTML = "";

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
                        "customer-venue-modal-thumbnail";

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
                            modalMainImage.src =
                                imageUrl;

                            modalThumbnails
                                .querySelectorAll(
                                    ".customer-venue-modal-thumbnail"
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

                            modalMainBadge.classList.toggle(
                                "hidden",
                                index !== 0
                            );
                        }
                    );

                    modalThumbnails.appendChild(
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

                        modalName.textContent =
                            button.dataset.name
                            || "Venue";

                        modalPrice.textContent =
                            button.dataset.price
                            || "";

                        modalDescription.textContent =
                            button.dataset.description
                            || "";

                        modalLocation.textContent =
                            button.dataset.location
                            || "Not specified";

                        modalCapacity.textContent =
                            (
                                button.dataset.capacity
                                || "0"
                            )
                            + " guests";

                        modalBook.href =
                            "<?= e(
                                url(
                                    '/customer/booking.php?venue_id='
                                )
                            ) ?>"
                            + (
                                button.dataset.id
                                || ""
                            );

                        modalFacilities.innerHTML = "";

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

                            modalFacilities.appendChild(
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

                                    modalFacilities.appendChild(
                                        item
                                    );
                                }
                            );
                        }

                        if (images.length > 0) {
                            modalMainImage.src =
                                images[0];

                            modalMainBadge.classList.remove(
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
                            "customer-venue-modal-open"
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
                "customer-venue-modal-open"
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