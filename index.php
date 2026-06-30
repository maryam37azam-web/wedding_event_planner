<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/package_helpers.php';
require_once __DIR__ . '/includes/venue_helpers.php';
require_once __DIR__ . '/includes/gallery_helpers.php';

$connection = db();

/*
|--------------------------------------------------------------------------
| Current visitor links
|--------------------------------------------------------------------------
*/

$currentRole = (string) (
    $_SESSION['role'] ?? ''
);

$isLoggedIn = isset($_SESSION['user_id']);

$accountPath = match ($currentRole) {
    'admin' =>
        '/admin/dashboard.php',

    'event_manager' =>
        '/event_manager/dashboard.php',

    'booking_manager' =>
        '/booking_manager/dashboard.php',

    'customer' =>
        '/customer/dashboard.php',

    default =>
        '/auth/customer_login.php',
};

$accountLabel = $isLoggedIn
    ? 'Dashboard'
    : 'Login';

$bookingPath = $currentRole === 'customer'
    ? '/customer/booking.php'
    : '/auth/customer_login.php';

/*
|--------------------------------------------------------------------------
| Public website data
|--------------------------------------------------------------------------
*/

$packages = $connection
    ->query(
        "SELECT *
         FROM packages
         WHERE status = 'active'
         ORDER BY created_at DESC
         LIMIT 3"
    )
    ->fetchAll();

$venues = $connection
    ->query(
        "SELECT *
         FROM venues
         WHERE status = 'active'
         ORDER BY created_at DESC
         LIMIT 3"
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
         ORDER BY created_at DESC
         LIMIT 4"
    )
    ->fetchAll();

$showAllGallery =
    isset($_GET['gallery'])
    && $_GET['gallery'] === 'all';

$galleryQuery =
    "SELECT
        id,
        title,
        description,
        event_type,
        image,
        image_two
     FROM gallery
     WHERE status = 'active'
     ORDER BY created_at DESC";

if (!$showAllGallery) {
    $galleryQuery .= ' LIMIT 8';
}

$galleryImages = $connection
    ->query($galleryQuery)
    ->fetchAll();

/*
|--------------------------------------------------------------------------
| Hero background
|--------------------------------------------------------------------------
*/

$heroImage = url(
    '/assets/images/elegant_wedding_reception_in_grand_hall.png'
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

    <title><?= e(APP_NAME) ?></title>

    <?php require __DIR__ . '/includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/public_home.css?v=65')
        ) ?>"
    >
</head>

<body class="public-home-page">

    <nav class="public-navbar">

        <a
            class="public-logo"
            href="<?= e(url('/index.php')) ?>"
        >
            <img
                src="<?= e(
                    url('/assets/icons/icon-192.png')
                ) ?>"
                alt="Wedding Event Planner"
            >

            <span class="public-logo-text">

                <strong>Wedding Planner</strong>

                <span>
                    Perfect events, beautiful memories
                </span>

            </span>
        </a>

        <button
            class="public-mobile-button"
            id="publicMobileButton"
            type="button"
            aria-label="Open navigation menu"
        >
            <i class="fa-solid fa-bars"></i>
        </button>

        <ul
            class="public-nav-links"
            id="publicNavLinks"
        >
            <li>
                <a href="#home">Home</a>
            </li>

            <li>
                <a href="#packages">Packages</a>
            </li>

            <li>
                <a href="#venues">Venues</a>
            </li>

            <li>
                <a href="#services">Services</a>
            </li>

            <li>
                <a href="#gallery">Gallery</a>
            </li>

            <li>
                <a href="#contact">Contact</a>
            </li>
        </ul>

        <div class="public-nav-actions">

            <a
                class="public-login-button"
                href="<?= e(url($accountPath)) ?>"
            >
                <?= e($accountLabel) ?>
            </a>

            <a
                class="public-book-button"
                href="<?= e(url($bookingPath)) ?>"
            >
                Book Event
            </a>

        </div>

    </nav>

    <header
        class="public-hero"
        id="home"
        style="--hero-image: url('<?= e($heroImage) ?>');"
    >

        <div class="public-hero-content">

            <div class="public-hero-badge">
                <i class="fa-solid fa-heart"></i>
                Complete Wedding Event Planning
            </div>

            <h1>
                Create Your Dream Wedding
            </h1>

            <p>
                Plan your perfect wedding with elegant
                venues, beautiful decorations, premium
                catering, music and complete professional
                event management.
            </p>

            <div class="public-hero-actions">

                <a
                    class="public-primary-button"
                    href="<?= e(url($bookingPath)) ?>"
                >
                    Book Your Event
                </a>

                <a
                    class="public-secondary-button"
                    href="#packages"
                >
                    Explore Packages
                </a>

            </div>

        </div>

    </header>

    <section class="public-section public-section-white">

        <div class="public-section-heading">

            <span>Why choose us</span>

            <h2>
                Everything You Need for a Perfect Wedding
            </h2>

            <p>
                We provide professional wedding planning
                services from the initial booking through
                complete event execution.
            </p>

        </div>

        <div class="public-features-grid">

            <article class="public-feature-card">

                <div class="public-feature-icon">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>

                <h3>Elegant Decorations</h3>

                <p>
                    Beautiful and luxurious stage,
                    entrance and venue setups designed
                    around your wedding vision.
                </p>

            </article>

            <article class="public-feature-card">

                <div class="public-feature-icon">
                    <i class="fa-solid fa-utensils"></i>
                </div>

                <h3>Premium Catering</h3>

                <p>
                    Delicious food, professional catering
                    teams and menu options that can be
                    customised for your guests.
                </p>

            </article>

            <article class="public-feature-card">

                <div class="public-feature-icon">
                    <i class="fa-solid fa-music"></i>
                </div>

                <h3>Music and Atmosphere</h3>

                <p>
                    Choose soft background music or live
                    music to create the perfect atmosphere
                    for your celebration.
                </p>

            </article>

            <article class="public-feature-card">

                <div class="public-feature-icon">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>

                <h3>Complete Management</h3>

                <p>
                    Our team manages the complete process
                    from booking and preparation through
                    the final event execution.
                </p>

            </article>

        </div>

    </section>

    <section
        class="public-section"
        id="packages"
    >

        <div class="public-section-heading">

            <span>Wedding packages</span>

            <h2>Choose Your Perfect Package</h2>

            <p>
                Browse our active wedding packages and
                select the option that best suits your
                event size, style and budget.
            </p>

        </div>

        <?php if ($packages === []): ?>

            <div class="public-empty">

                <i class="fa-solid fa-gift"></i>

                <h3>No packages available</h3>

                <p>
                    Active wedding packages will appear
                    here when they are added by the Admin.
                </p>

            </div>

        <?php else: ?>

            <div class="public-package-grid">

                <?php foreach ($packages as $package): ?>
                    <?php
                    $packageId = (int) $package['id'];

                    $packageFeatures =
                        package_feature_lines(
                            $package['features']
                            ?? null
                        );

                    $packageDescription = trim(
                        (string) (
                            $package['short_description']
                            ?? $package['description']
                            ?? ''
                        )
                    );

                    $fullDescription = trim(
                        (string) (
                            $package['description']
                            ?? $packageDescription
                        )
                    );

                    $musicOptions = [];

                    if (
                        (int) (
                            $package['basic_music']
                            ?? 0
                        ) === 1
                    ) {
                        $musicOptions[] = 'Basic Music';
                    }

                    if (
                        (int) (
                            $package['live_music']
                            ?? 0
                        ) === 1
                    ) {
                        $musicOptions[] = 'Live Music';
                    }

                    $musicText =
                        $musicOptions !== []
                            ? implode(
                                ' and ',
                                $musicOptions
                            )
                            : 'Music not included';

                    $mainPackageImage =
                        package_image_url(
                            $package['main_image']
                            ?? null
                        );

                    $packageGalleryImages = [
                        package_image_url(
                            $package['image_one']
                            ?? null
                        ),

                        package_image_url(
                            $package['image_two']
                            ?? null
                        ),

                        package_image_url(
                            $package['image_three']
                            ?? null
                        ),
                    ];

                    /*
                     * The genuine main image is included as
                     * the first thumbnail so it can always
                     * be restored after another image is shown.
                     */
                    $packageCardImages = [
                        $mainPackageImage,
                        ...$packageGalleryImages,
                    ];
                    ?>

                    <article class="public-package-card">

                        <div class="public-package-gallery">

                            <img
                                class="public-package-image"
                                id="publicPackageMainImage<?= e(
                                    (string) $packageId
                                ) ?>"
                                src="<?= e($mainPackageImage) ?>"
                                alt="<?= e(
                                    (string) $package['name']
                                ) ?>"
                            >

                            <div class="public-package-thumbnails">

                                <?php foreach (
                                    $packageCardImages as
                                    $imageIndex => $galleryImage
                                ): ?>
                                    <?php
                                    $isMainImage =
                                        $imageIndex === 0;
                                    ?>

                                    <button
                                        class="public-package-thumbnail-button<?= $isMainImage
                                            ? ' active public-main-thumbnail'
                                            : '' ?>"
                                        type="button"

                                        data-public-package-main="publicPackageMainImage<?= e(
                                            (string) $packageId
                                        ) ?>"

                                        data-public-package-image="<?= e(
                                            $galleryImage
                                        ) ?>"

                                        aria-label="<?= e(
                                            $isMainImage
                                                ? 'Restore main package image'
                                                : 'Show package image '
                                                    . $imageIndex
                                        ) ?>"
                                    >
                                        <img
                                            src="<?= e(
                                                $galleryImage
                                            ) ?>"
                                            alt="<?= e(
                                                $isMainImage
                                                    ? 'Main image for '
                                                        . (string) $package['name']
                                                    : 'Additional image for '
                                                        . (string) $package['name']
                                            ) ?>"
                                        >

                                        <?php if ($isMainImage): ?>

                                            <span class="public-main-thumbnail-badge">
                                                Main
                                            </span>

                                        <?php endif; ?>

                                    </button>

                                <?php endforeach; ?>

                            </div>

                        </div>

                        <div class="public-package-body">

                            <div class="public-package-top">

                                <h3>
                                    <?= e(
                                        (string) $package['name']
                                    ) ?>
                                </h3>

                                <span class="public-active-badge">
                                    Available
                                </span>

                            </div>

                            <div class="public-package-price">

                                <?= e(
                                    format_package_price(
                                        (float) $package['price']
                                    )
                                ) ?>

                            </div>

                            <p class="public-package-description">

                                <?= e(
                                    $packageDescription !== ''
                                        ? $packageDescription
                                        : 'Complete professional wedding package.'
                                ) ?>

                            </p>

                            <ul class="public-package-features">

                                <li>
                                    <i class="fa-solid fa-check"></i>

                                    Up to

                                    <?= e(
                                        number_format(
                                            (int) (
                                                $package[
                                                    'guest_capacity'
                                                ]
                                                ?? 0
                                            )
                                        )
                                    ) ?>

                                    guests
                                </li>

                                <li>
                                    <i class="fa-solid fa-check"></i>
                                    <?= e($musicText) ?>
                                </li>

                                <?php foreach (
                                    array_slice(
                                        $packageFeatures,
                                        0,
                                        2
                                    ) as $feature
                                ): ?>

                                    <li>
                                        <i class="fa-solid fa-check"></i>
                                        <?= e($feature) ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                            <div class="public-card-actions">

                                <button
                                    class="public-detail-button"
                                    type="button"
                                    data-public-detail
                                    data-detail-type="package"

                                    data-name="<?= e(
                                        (string) $package['name']
                                    ) ?>"

                                    data-price="<?= e(
                                        format_package_price(
                                            (float) $package['price']
                                        )
                                    ) ?>"

                                    data-description="<?= e(
                                        $fullDescription !== ''
                                            ? $fullDescription
                                            : 'Complete professional wedding package.'
                                    ) ?>"

                                    data-image="<?= e(
                                        $mainPackageImage
                                    ) ?>"

                                    data-image-one="<?= e(
                                        $packageGalleryImages[0]
                                    ) ?>"

                                    data-image-two="<?= e(
                                        $packageGalleryImages[1]
                                    ) ?>"

                                    data-image-three="<?= e(
                                        $packageGalleryImages[2]
                                    ) ?>"

                                    data-book-url="<?= e(
                                        url($bookingPath)
                                    ) ?>"

                                    data-detail-one="<?= e(
                                        'Guest capacity: '
                                        . number_format(
                                            (int) (
                                                $package[
                                                    'guest_capacity'
                                                ]
                                                ?? 0
                                            )
                                        )
                                    ) ?>"

                                    data-detail-two="<?= e(
                                        'Decoration: '
                                        . (
                                            trim(
                                                (string) (
                                                    $package[
                                                        'decoration_type'
                                                    ]
                                                    ?? ''
                                                )
                                            ) !== ''
                                                ? (string) $package[
                                                    'decoration_type'
                                                ]
                                                : 'Included'
                                        )
                                    ) ?>"

                                    data-detail-three="<?= e(
                                        'Music: ' . $musicText
                                    ) ?>"
                                >
                                    View Details
                                </button>

                                <a
                                    class="public-card-book-button"
                                    href="<?= e(
                                        url($bookingPath)
                                    ) ?>"
                                >
                                    Book Now
                                </a>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <section
        class="public-section public-section-white"
        id="venues"
    >

        <div class="public-section-heading">

            <span>Wedding venues</span>

            <h2>Beautiful Venues for Your Event</h2>

            <p>
                Explore Admin-activated wedding venues,
                compare facilities and guest capacities,
                and check date availability during booking.
            </p>

        </div>

        <?php if ($venues === []): ?>

            <div class="public-empty">

                <i class="fa-solid fa-hotel"></i>

                <h3>No venues available</h3>

                <p>
                    Venues activated by the Admin will
                    appear here automatically.
                </p>

            </div>

        <?php else: ?>

            <div class="public-venue-grid">

                <?php foreach ($venues as $venue): ?>
                    <?php
                    $venueId = (int) $venue['id'];

                    $venueFacilities =
                        venue_facility_lines(
                            $venue['facilities']
                            ?? null
                        );

                    $venueDescription = trim(
                        (string) (
                            $venue['description']
                            ?? ''
                        )
                    );

                    $mainVenueImage =
                        venue_image_url(
                            $venue['main_image']
                            ?? null
                        );

                    $venueGalleryImages = [
                        venue_image_url(
                            $venue['image_one']
                            ?? null
                        ),

                        venue_image_url(
                            $venue['image_two']
                            ?? null
                        ),

                        venue_image_url(
                            $venue['image_three']
                            ?? null
                        ),
                    ];

                    /*
                     * The venue main image is also shown
                     * as the first thumbnail.
                     */
                    $venueCardImages = [
                        $mainVenueImage,
                        ...$venueGalleryImages,
                    ];

                    $venueBookingPath =
                        $currentRole === 'customer'
                            ? '/customer/booking.php?venue_id='
                                . $venueId
                            : '/auth/customer_login.php';
                    ?>

                    <article class="public-venue-card">

                        <div class="public-venue-gallery">

                            <img
                                class="public-venue-image"
                                id="publicVenueMainImage<?= e(
                                    (string) $venueId
                                ) ?>"
                                src="<?= e($mainVenueImage) ?>"
                                alt="<?= e(
                                    (string) $venue['name']
                                ) ?>"
                            >

                            <div class="public-venue-thumbnails">

                                <?php foreach (
                                    $venueCardImages as
                                    $imageIndex => $galleryImage
                                ): ?>
                                    <?php
                                    $isMainImage =
                                        $imageIndex === 0;
                                    ?>

                                    <button
                                        class="public-venue-thumbnail-button<?= $isMainImage
                                            ? ' active public-main-thumbnail'
                                            : '' ?>"
                                        type="button"

                                        data-public-venue-main="publicVenueMainImage<?= e(
                                            (string) $venueId
                                        ) ?>"

                                        data-public-venue-image="<?= e(
                                            $galleryImage
                                        ) ?>"

                                        aria-label="<?= e(
                                            $isMainImage
                                                ? 'Restore main venue image'
                                                : 'Show venue image '
                                                    . $imageIndex
                                        ) ?>"
                                    >
                                        <img
                                            src="<?= e(
                                                $galleryImage
                                            ) ?>"
                                            alt="<?= e(
                                                $isMainImage
                                                    ? 'Main image for '
                                                        . (string) $venue['name']
                                                    : 'Additional image for '
                                                        . (string) $venue['name']
                                            ) ?>"
                                        >

                                        <?php if ($isMainImage): ?>

                                            <span class="public-main-thumbnail-badge">
                                                Main
                                            </span>

                                        <?php endif; ?>

                                    </button>

                                <?php endforeach; ?>

                            </div>

                        </div>

                        <div class="public-venue-body">

                            <div class="public-venue-title">

                                <h3>
                                    <?= e(
                                        (string) $venue['name']
                                    ) ?>
                                </h3>

                                <span class="public-venue-status">
                                    Date Based
                                </span>

                            </div>

                            <div class="public-venue-location">

                                <i class="fa-solid fa-location-dot"></i>

                                <?= e(
                                    (string) $venue['location']
                                ) ?>

                            </div>

                            <div class="public-venue-price">

                                <?= e(
                                    format_venue_price(
                                        (float) $venue['price']
                                    )
                                ) ?>

                            </div>

                            <p class="public-venue-description">

                                <?= e(
                                    $venueDescription !== ''
                                        ? $venueDescription
                                        : 'Professional wedding-event venue.'
                                ) ?>

                            </p>

                            <ul class="public-venue-features">

                                <li>
                                    <i class="fa-solid fa-users"></i>

                                    Up to

                                    <?= e(
                                        number_format(
                                            (int) (
                                                $venue['capacity']
                                                ?? 0
                                            )
                                        )
                                    ) ?>

                                    guests
                                </li>

                                <?php foreach (
                                    array_slice(
                                        $venueFacilities,
                                        0,
                                        2
                                    ) as $facility
                                ): ?>

                                    <li>
                                        <i class="fa-solid fa-check"></i>
                                        <?= e($facility) ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                            <div class="public-card-actions">

                                <button
                                    class="public-detail-button"
                                    type="button"
                                    data-public-detail
                                    data-detail-type="venue"

                                    data-name="<?= e(
                                        (string) $venue['name']
                                    ) ?>"

                                    data-price="<?= e(
                                        format_venue_price(
                                            (float) $venue['price']
                                        )
                                    ) ?>"

                                    data-description="<?= e(
                                        $venueDescription !== ''
                                            ? $venueDescription
                                            : 'Professional wedding-event venue.'
                                    ) ?>"

                                    data-image="<?= e(
                                        $mainVenueImage
                                    ) ?>"

                                    data-image-one="<?= e(
                                        $venueGalleryImages[0]
                                    ) ?>"

                                    data-image-two="<?= e(
                                        $venueGalleryImages[1]
                                    ) ?>"

                                    data-image-three="<?= e(
                                        $venueGalleryImages[2]
                                    ) ?>"

                                    data-book-url="<?= e(
                                        url($venueBookingPath)
                                    ) ?>"

                                    data-detail-one="<?= e(
                                        'Location: '
                                        . (string) $venue['location']
                                    ) ?>"

                                    data-detail-two="<?= e(
                                        'Guest capacity: '
                                        . number_format(
                                            (int) (
                                                $venue['capacity']
                                                ?? 0
                                            )
                                        )
                                    ) ?>"

                                    data-detail-three="<?= e(
                                        'Facilities: '
                                        . (
                                            $venueFacilities !== []
                                                ? implode(
                                                    ', ',
                                                    $venueFacilities
                                                )
                                                : 'Not specified'
                                        )
                                    ) ?>"
                                >
                                    View Details
                                </button>

                                <a
                                    class="public-card-book-button"
                                    href="<?= e(
                                        url($venueBookingPath)
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

    <section
        class="public-section"
        id="services"
    >

        <div class="public-section-heading">

            <span>Additional services</span>

            <h2>Complete Your Wedding Experience</h2>

            <p>
                Add professional services to your booking
                according to the needs of your wedding
                event.
            </p>

        </div>

        <?php if ($services === []): ?>

            <div class="public-empty">

                <i class="fa-solid fa-bell-concierge"></i>

                <h3>No services available</h3>

                <p>
                    Active services will appear here when
                    they are created by the Admin.
                </p>

            </div>

        <?php else: ?>

            <div class="public-services-grid">

                <?php foreach ($services as $service): ?>

                    <article class="public-service-card">

                        <div class="public-service-icon">
                            <i class="fa-solid fa-bell-concierge"></i>
                        </div>

                        <h3>
                            <?= e(
                                (string) $service['name']
                            ) ?>
                        </h3>

                        <div class="public-service-price">

                            Rs.

                            <?= e(
                                number_format(
                                    (float) $service['price'],
                                    0
                                )
                            ) ?>

                        </div>

                        <p>
                            <?= e(
                                (string) (
                                    $service['description']
                                    ?: 'Professional wedding-event service.'
                                )
                            ) ?>
                        </p>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <section
        class="public-section public-section-white"
        id="gallery"
    >

        <div class="public-section-heading">

            <span>Wedding gallery</span>

            <h2>
                Memorable Events and Beautiful Setups
            </h2>

            <p>
                View active gallery images uploaded by our
                professional Event Manager.
            </p>

        </div>

        <?php if ($galleryImages === []): ?>

            <div class="public-empty">

                <i class="fa-regular fa-images"></i>

                <h3>No gallery images available</h3>

                <p>
                    Active wedding-event images will appear
                    here automatically.
                </p>

            </div>

        <?php else: ?>

            <div class="public-gallery-grid">

                <?php foreach (
                    $galleryImages as $galleryImage
                ): ?>
                    <?php
                    $galleryTitle = trim(
                        (string) (
                            $galleryImage['title']
                            ?? ''
                        )
                    );

                    if ($galleryTitle === '') {
                        $galleryTitle = 'Wedding Event';
                    }

                    $galleryEventType = trim(
                        (string) (
                            $galleryImage['event_type']
                            ?? ''
                        )
                    );

                    if ($galleryEventType === '') {
                        $galleryEventType = 'Wedding Event';
                    }

                    $galleryDescription = trim(
                        (string) (
                            $galleryImage['description']
                            ?? ''
                        )
                    );

                    if ($galleryDescription === '') {
                        $galleryDescription =
                            'No description provided.';
                    }

                    $firstGalleryImage =
                        gallery_image_url(
                            $galleryImage['image']
                            ?? null
                        );

                    $secondGalleryImagePath = trim(
                        (string) (
                            $galleryImage['image_two']
                            ?? ''
                        )
                    );

                    $secondGalleryImage =
                        $secondGalleryImagePath !== ''
                            ? gallery_image_url(
                                $secondGalleryImagePath
                            )
                            : '';
                    ?>

                    <button
                        class="public-gallery-item"
                        type="button"

                        data-public-gallery-item

                        data-image-one="<?= e(
                            $firstGalleryImage
                        ) ?>"

                        data-image-two="<?= e(
                            $secondGalleryImage
                        ) ?>"

                        data-title="<?= e(
                            $galleryTitle
                        ) ?>"

                        data-event-type="<?= e(
                            $galleryEventType
                        ) ?>"

                        data-description="<?= e(
                            $galleryDescription
                        ) ?>"

                        aria-label="Open <?= e(
                            $galleryTitle
                        ) ?> gallery preview"
                    >

                        <img
                            src="<?= e(
                                $firstGalleryImage
                            ) ?>"
                            alt="<?= e(
                                $galleryTitle
                            ) ?>"
                        >

                        <?php if (
                            $secondGalleryImage !== ''
                        ): ?>

                            <span class="public-gallery-photo-count">

                                <i class="fa-solid fa-images"></i>

                                <span>2 Photos</span>

                            </span>

                        <?php endif; ?>

                        <span class="public-gallery-overlay">

                            <span>

                                <strong>
                                    <?= e(
                                        $galleryTitle
                                    ) ?>
                                </strong>

                                <small>
                                    <?= e(
                                        $galleryEventType
                                    ) ?>
                                </small>

                            </span>

                        </span>

                    </button>

                <?php endforeach; ?>

            </div>

            <div class="public-section-footer">

                <a
                    class="public-view-all"
                    href="<?= e(
                        url(
                            $showAllGallery
                                ? '/index.php#gallery'
                                : '/index.php?gallery=all#gallery'
                        )
                    ) ?>"
                >
                    <i class="fa-solid <?= $showAllGallery
                        ? 'fa-arrow-left'
                        : 'fa-images' ?>"></i>

                    <?= $showAllGallery
                        ? 'Show Latest Images'
                        : 'View All Images' ?>
                </a>

            </div>

        <?php endif; ?>

    </section>

    <section
        class="public-section"
        id="contact"
    >

        <div class="public-contact-box">

            <div class="public-contact-content">

                <h2>
                    Ready to Start Planning Your Wedding?
                </h2>

                <p>
                    Create your customer account or log in
                    to select a wedding package, venue,
                    additional services and event date.
                </p>

            </div>

            <div class="public-contact-actions">

                <a
                    class="public-contact-primary"
                    href="<?= e(url($bookingPath)) ?>"
                >
                    Book an Event
                </a>

                <?php if (!$isLoggedIn): ?>

                    <a
                        class="public-contact-secondary"
                        href="<?= e(
                            url('/auth/register.php')
                        ) ?>"
                    >
                        Create Account
                    </a>

                <?php else: ?>

                    <a
                        class="public-contact-secondary"
                        href="<?= e(
                            url($accountPath)
                        ) ?>"
                    >
                        Open Dashboard
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </section>

    <footer class="public-footer">

        <div class="public-footer-content">

            <div class="public-footer-logo">

                <h3>Wedding Event Planner</h3>

                <p>
                    Making every wedding beautiful,
                    organised and memorable.
                </p>

            </div>

            <div class="public-footer-links">

                <a href="#packages">Packages</a>

                <a href="#venues">Venues</a>

                <a href="#services">Services</a>

                <a href="#gallery">Gallery</a>

                <a href="<?= e(url($accountPath)) ?>">
                    <?= e($accountLabel) ?>
                </a>

            </div>

        </div>

        <div class="public-footer-copyright">

            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.

        </div>

    </footer>

    <div
        class="public-modal"
        id="publicDetailsModal"
    >

        <div class="public-modal-content">

            <button
                class="public-modal-close"
                id="publicDetailsClose"
                type="button"
                aria-label="Close details"
            >
                &times;
            </button>

            <div class="public-modal-grid">

                <div class="public-modal-media">

                    <img
                        class="public-modal-image"
                        id="publicDetailsImage"
                        src=""
                        alt="Wedding details"
                    >

                    <div
                        class="public-modal-thumbnails"
                        id="publicDetailsThumbnails"
                    ></div>

                </div>

                <div class="public-modal-info">

                    <h2 id="publicDetailsName"></h2>

                    <div
                        class="public-modal-price"
                        id="publicDetailsPrice"
                    ></div>

                    <p
                        class="public-modal-description"
                        id="publicDetailsDescription"
                    ></p>

                    <div class="public-modal-detail">
                        <strong id="publicDetailOne"></strong>
                    </div>

                    <div class="public-modal-detail">
                        <strong id="publicDetailTwo"></strong>
                    </div>

                    <div class="public-modal-detail">
                        <strong id="publicDetailThree"></strong>
                    </div>

                    <a
                        class="public-modal-book"
                        id="publicDetailsBook"
                        href="<?= e(url($bookingPath)) ?>"
                    >
                        Book This Event
                    </a>

                </div>

            </div>

        </div>

    </div>

    <div
        class="public-modal"
        id="publicImageModal"
        aria-hidden="true"
    >

        <div
            class="public-image-modal-content"
            role="dialog"
            aria-modal="true"
            aria-label="Wedding gallery preview"
        >

            <button
                class="public-image-modal-close"
                id="publicImageClose"
                type="button"
                aria-label="Close image preview"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

            <button
                class="public-image-modal-arrow public-image-modal-arrow-left"
                id="publicImagePrevious"
                type="button"
                aria-label="Previous image"
            >
                <i class="fa-solid fa-chevron-left"></i>
            </button>

            <div class="public-image-modal-image-area">

                <img
                    id="publicImagePreview"
                    src=""
                    alt="Wedding gallery preview"
                >

            </div>

            <button
                class="public-image-modal-arrow public-image-modal-arrow-right"
                id="publicImageNext"
                type="button"
                aria-label="Next image"
            >
                <i class="fa-solid fa-chevron-right"></i>
            </button>

            <div
                class="public-image-modal-counter"
                id="publicImageCounter"
            >
                1 / 1
            </div>

            <div class="public-image-modal-information">

                <h3 id="publicImageTitle"></h3>

                <p
                    class="public-image-modal-event-type"
                    id="publicImageEventType"
                ></p>

                <p
                    class="public-image-modal-description"
                    id="publicImageDescription"
                ></p>

            </div>

        </div>

    </div>

    <script>
        "use strict";

        const mobileButton =
            document.getElementById(
                "publicMobileButton"
            );

        const navigationLinks =
            document.getElementById(
                "publicNavLinks"
            );

        mobileButton?.addEventListener(
            "click",
            function () {
                navigationLinks?.classList.toggle(
                    "open"
                );
            }
        );

        navigationLinks
            ?.querySelectorAll("a")
            .forEach(function (link) {
                link.addEventListener(
                    "click",
                    function () {
                        navigationLinks.classList.remove(
                            "open"
                        );
                    }
                );
            });

        /*
         * Package card thumbnails
         */

        document
            .querySelectorAll(
                "[data-public-package-main]"
            )
            .forEach(function (thumbnailButton) {
                thumbnailButton.addEventListener(
                    "click",
                    function () {
                        const mainImage =
                            document.getElementById(
                                thumbnailButton.dataset
                                    .publicPackageMain
                            );

                        const selectedImage =
                            thumbnailButton.dataset
                                .publicPackageImage;

                        if (
                            !mainImage
                            || !selectedImage
                        ) {
                            return;
                        }

                        mainImage.style.opacity =
                            "0.35";

                        window.setTimeout(
                            function () {
                                mainImage.src =
                                    selectedImage;

                                mainImage.style.opacity =
                                    "1";
                            },
                            120
                        );

                        const thumbnailRow =
                            thumbnailButton.closest(
                                ".public-package-thumbnails"
                            );

                        thumbnailRow
                            ?.querySelectorAll(
                                ".public-package-thumbnail-button"
                            )
                            .forEach(
                                function (button) {
                                    button.classList.remove(
                                        "active"
                                    );
                                }
                            );

                        thumbnailButton.classList.add(
                            "active"
                        );
                    }
                );
            });

        /*
         * Venue card thumbnails
         */

        document
            .querySelectorAll(
                "[data-public-venue-main]"
            )
            .forEach(function (thumbnailButton) {
                thumbnailButton.addEventListener(
                    "click",
                    function () {
                        const mainImage =
                            document.getElementById(
                                thumbnailButton.dataset
                                    .publicVenueMain
                            );

                        const selectedImage =
                            thumbnailButton.dataset
                                .publicVenueImage;

                        if (
                            !mainImage
                            || !selectedImage
                        ) {
                            return;
                        }

                        mainImage.style.opacity =
                            "0.35";

                        window.setTimeout(
                            function () {
                                mainImage.src =
                                    selectedImage;

                                mainImage.style.opacity =
                                    "1";
                            },
                            120
                        );

                        const thumbnailRow =
                            thumbnailButton.closest(
                                ".public-venue-thumbnails"
                            );

                        thumbnailRow
                            ?.querySelectorAll(
                                ".public-venue-thumbnail-button"
                            )
                            .forEach(
                                function (button) {
                                    button.classList.remove(
                                        "active"
                                    );
                                }
                            );

                        thumbnailButton.classList.add(
                            "active"
                        );
                    }
                );
            });

        /*
         * Package and venue details modal
         */

        const detailsModal =
            document.getElementById(
                "publicDetailsModal"
            );

        const detailsClose =
            document.getElementById(
                "publicDetailsClose"
            );

        const detailsImage =
            document.getElementById(
                "publicDetailsImage"
            );

        const detailsThumbnails =
            document.getElementById(
                "publicDetailsThumbnails"
            );

        const detailsBook =
            document.getElementById(
                "publicDetailsBook"
            );

        document
            .querySelectorAll(
                "[data-public-detail]"
            )
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        document.getElementById(
                            "publicDetailsName"
                        ).textContent =
                            button.dataset.name;

                        document.getElementById(
                            "publicDetailsPrice"
                        ).textContent =
                            button.dataset.price;

                        document.getElementById(
                            "publicDetailsDescription"
                        ).textContent =
                            button.dataset.description;

                        detailsImage.src =
                            button.dataset.image;

                        document.getElementById(
                            "publicDetailOne"
                        ).textContent =
                            button.dataset.detailOne;

                        document.getElementById(
                            "publicDetailTwo"
                        ).textContent =
                            button.dataset.detailTwo;

                        document.getElementById(
                            "publicDetailThree"
                        ).textContent =
                            button.dataset.detailThree;

                        detailsThumbnails.innerHTML = "";

                        /*
                         * The genuine main image is now the
                         * first modal thumbnail.
                         */
                        const detailImages = [
                            {
                                imageUrl:
                                    button.dataset.image,
                                isMain: true
                            },

                            {
                                imageUrl:
                                    button.dataset.imageOne,
                                isMain: false
                            },

                            {
                                imageUrl:
                                    button.dataset.imageTwo,
                                isMain: false
                            },

                            {
                                imageUrl:
                                    button.dataset.imageThree,
                                isMain: false
                            }
                        ].filter(
                            function (imageData) {
                                return Boolean(
                                    imageData.imageUrl
                                );
                            }
                        );

                        if (detailImages.length === 0) {
                            detailsThumbnails.classList.add(
                                "hidden"
                            );
                        } else {
                            detailsThumbnails.classList.remove(
                                "hidden"
                            );

                            detailImages.forEach(
                                function (
                                    imageData,
                                    imageIndex
                                ) {
                                    const thumbnailButton =
                                        document.createElement(
                                            "button"
                                        );

                                    const thumbnailImage =
                                        document.createElement(
                                            "img"
                                        );

                                    thumbnailButton.type =
                                        "button";

                                    thumbnailButton.className =
                                        "public-modal-thumbnail-button";

                                    if (imageIndex === 0) {
                                        thumbnailButton
                                            .classList
                                            .add("active");
                                    }

                                    thumbnailButton.setAttribute(
                                        "aria-label",
                                        imageData.isMain
                                            ? "Restore main image"
                                            : "Show image "
                                                + imageIndex
                                    );

                                    thumbnailImage.src =
                                        imageData.imageUrl;

                                    thumbnailImage.alt =
                                        imageData.isMain
                                            ? "Main wedding image"
                                            : "Additional wedding image";

                                    thumbnailButton.appendChild(
                                        thumbnailImage
                                    );

                                    if (imageData.isMain) {
                                        const mainBadge =
                                            document.createElement(
                                                "span"
                                            );

                                        mainBadge.className =
                                            "public-main-thumbnail-badge";

                                        mainBadge.textContent =
                                            "Main";

                                        thumbnailButton.appendChild(
                                            mainBadge
                                        );
                                    }

                                    thumbnailButton.addEventListener(
                                        "click",
                                        function () {
                                            detailsImage.style.opacity =
                                                "0.35";

                                            window.setTimeout(
                                                function () {
                                                    detailsImage.src =
                                                        imageData.imageUrl;

                                                    detailsImage.style.opacity =
                                                        "1";
                                                },
                                                120
                                            );

                                            detailsThumbnails
                                                .querySelectorAll(
                                                    ".public-modal-thumbnail-button"
                                                )
                                                .forEach(
                                                    function (item) {
                                                        item.classList.remove(
                                                            "active"
                                                        );
                                                    }
                                                );

                                            thumbnailButton.classList.add(
                                                "active"
                                            );
                                        }
                                    );

                                    detailsThumbnails.appendChild(
                                        thumbnailButton
                                    );
                                }
                            );
                        }

                        detailsBook.textContent =
                            button.dataset.detailType
                                === "package"
                                ? "Book This Package"
                                : "Book This Venue";

                        detailsBook.href =
                            button.dataset.bookUrl
                            || <?= json_encode(
                                url($bookingPath),
                                JSON_HEX_TAG
                                | JSON_HEX_AMP
                                | JSON_HEX_APOS
                                | JSON_HEX_QUOT
                            ) ?>;

                        detailsModal.classList.add(
                            "open"
                        );

                        document.body.style.overflow =
                            "hidden";
                    }
                );
            });

        function closeDetailsModal() {
            detailsModal.classList.remove(
                "open"
            );

            detailsThumbnails.innerHTML = "";

            detailsThumbnails.classList.add(
                "hidden"
            );

            detailsImage.src = "";

            document.body.style.overflow = "";
        }

        detailsClose?.addEventListener(
            "click",
            closeDetailsModal
        );

        detailsModal?.addEventListener(
            "click",
            function (event) {
                if (event.target === detailsModal) {
                    closeDetailsModal();
                }
            }
        );

        /*
         * Public wedding gallery modal
         */

        const imageModal =
            document.getElementById(
                "publicImageModal"
            );

        const imageClose =
            document.getElementById(
                "publicImageClose"
            );

        const imagePreview =
            document.getElementById(
                "publicImagePreview"
            );

        const imagePrevious =
            document.getElementById(
                "publicImagePrevious"
            );

        const imageNext =
            document.getElementById(
                "publicImageNext"
            );

        const imageCounter =
            document.getElementById(
                "publicImageCounter"
            );

        const imageTitle =
            document.getElementById(
                "publicImageTitle"
            );

        const imageEventType =
            document.getElementById(
                "publicImageEventType"
            );

        const imageDescription =
            document.getElementById(
                "publicImageDescription"
            );

        let publicGalleryImages = [];
        let publicGalleryIndex = 0;

        function renderPublicGalleryImage() {
            if (
                publicGalleryImages.length === 0
            ) {
                return;
            }

            imagePreview.src =
                publicGalleryImages[
                    publicGalleryIndex
                ];

            imageCounter.textContent =
                `${publicGalleryIndex + 1} / ${publicGalleryImages.length}`;

            const hasMultipleImages =
                publicGalleryImages.length > 1;

            imagePrevious.hidden =
                !hasMultipleImages;

            imageNext.hidden =
                !hasMultipleImages;

            imageCounter.hidden =
                !hasMultipleImages;
        }

        function openPublicGalleryModal(
            images,
            title,
            eventType,
            description
        ) {
            publicGalleryImages =
                images.filter(
                    function (image) {
                        return Boolean(image);
                    }
                );

            if (
                publicGalleryImages.length === 0
            ) {
                return;
            }

            publicGalleryIndex = 0;

            imageTitle.textContent =
                title;

            imageEventType.textContent =
                eventType;

            imageDescription.textContent =
                description;

            renderPublicGalleryImage();

            imageModal.classList.add(
                "open"
            );

            imageModal.setAttribute(
                "aria-hidden",
                "false"
            );

            document.body.style.overflow =
                "hidden";
        }

        function closeImageModal() {
            imageModal.classList.remove(
                "open"
            );

            imageModal.setAttribute(
                "aria-hidden",
                "true"
            );

            imagePreview.src = "";
            imageTitle.textContent = "";
            imageEventType.textContent = "";
            imageDescription.textContent = "";

            publicGalleryImages = [];
            publicGalleryIndex = 0;

            document.body.style.overflow = "";
        }

        function showPreviousPublicGalleryImage() {
            if (
                publicGalleryImages.length < 2
            ) {
                return;
            }

            publicGalleryIndex =
                (
                    publicGalleryIndex
                    - 1
                    + publicGalleryImages.length
                )
                % publicGalleryImages.length;

            renderPublicGalleryImage();
        }

        function showNextPublicGalleryImage() {
            if (
                publicGalleryImages.length < 2
            ) {
                return;
            }

            publicGalleryIndex =
                (
                    publicGalleryIndex
                    + 1
                )
                % publicGalleryImages.length;

            renderPublicGalleryImage();
        }

        document
            .querySelectorAll(
                "[data-public-gallery-item]"
            )
            .forEach(function (galleryItem) {
                galleryItem.addEventListener(
                    "click",
                    function () {
                        openPublicGalleryModal(
                            [
                                galleryItem.dataset.imageOne,
                                galleryItem.dataset.imageTwo
                            ],

                            galleryItem.dataset.title,

                            galleryItem.dataset.eventType,

                            galleryItem.dataset.description
                        );
                    }
                );
            });

        imagePrevious?.addEventListener(
            "click",
            showPreviousPublicGalleryImage
        );

        imageNext?.addEventListener(
            "click",
            showNextPublicGalleryImage
        );

        imageClose?.addEventListener(
            "click",
            closeImageModal
        );

        imageModal?.addEventListener(
            "click",
            function (event) {
                if (event.target === imageModal) {
                    closeImageModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (
                    imageModal.classList.contains(
                        "open"
                    )
                ) {
                    if (event.key === "ArrowLeft") {
                        showPreviousPublicGalleryImage();
                        return;
                    }

                    if (event.key === "ArrowRight") {
                        showNextPublicGalleryImage();
                        return;
                    }

                    if (event.key === "Escape") {
                        closeImageModal();
                        return;
                    }
                }

                if (
                    event.key === "Escape"
                    && detailsModal.classList.contains(
                        "open"
                    )
                ) {
                    closeDetailsModal();
                }
            }
        );
    </script>

    <?php require __DIR__ . '/includes/pwa_scripts.php'; ?>

</body>
</html>