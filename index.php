<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/package_helpers.php';
require_once __DIR__ . '/includes/venue_helpers.php';
require_once __DIR__ . '/includes/gallery_helpers.php';

$connection = db();

$currentRole = (string) (
    $_SESSION['role'] ?? ''
);

$isLoggedIn = isset(
    $_SESSION['user_id']
);

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

$gallerySql =
    "SELECT
        id,
        title,
        description,
        event_type,
        image,
        image_two
     FROM gallery
     WHERE status = 'active'
     ORDER BY created_at DESC
     LIMIT 4";

$galleryImages = $connection
    ->query($gallerySql)
    ->fetchAll();

$heroImage = url(
    '/assets/images/pink_wedding_hero.png'
);

$currentYear = date('Y');

function public_json(
    array $value
): string {
    $json = json_encode(
        $value,
        JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );

    return is_string($json)
        ? $json
        : '[]';
}

function public_package_music(
    array $package
): string {
    $music = [];

    if (
        (int) (
            $package['basic_music']
            ?? 0
        ) === 1
    ) {
        $music[] = 'Basic Music';
    }

    if (
        (int) (
            $package['live_music']
            ?? 0
        ) === 1
    ) {
        $music[] = 'Live Music';
    }

    return $music === []
        ? 'Music not included'
        : implode(
            ' and ',
            $music
        );
}

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
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Great+Vibes&family=Poppins:wght@400;500;600;700&display=swap"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/public_home.css?v=68'
            )
        ) ?>"
    >
</head>

<body class="public-home-page">

    <nav
        class="public-navbar"
        id="publicNavbar"
    >

        <a
            class="public-logo"
            href="<?= e(
                url('/index.php')
            ) ?>"
        >
            <img
                src="<?= e(
                    url(
                        '/assets/icons/icon-192.png'
                    )
                ) ?>"
                alt="Wedding Event Planner"
            >

            <span class="public-logo-text">

                <strong>
                    Wedding Planner
                </strong>

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
                <a
                    class="active"
                    href="#home"
                    data-public-nav-link
                >
                    Home
                </a>
            </li>

            <li>
                <a
                    href="#packages"
                    data-public-nav-link
                >
                    Packages
                </a>
            </li>

            <li>
                <a
                    href="#venues"
                    data-public-nav-link
                >
                    Venues
                </a>
            </li>

            <li>
                <a
                    href="#services"
                    data-public-nav-link
                >
                    Services
                </a>
            </li>

            <li>
                <a
                    href="#gallery"
                    data-public-nav-link
                >
                    Gallery
                </a>
            </li>

            <li>
                <a
                    href="#about"
                    data-public-nav-link
                >
                    About Us
                </a>
            </li>

            <li>
                <a
                    href="#contact"
                    data-public-nav-link
                >
                    Contact
                </a>
            </li>
        </ul>

        <div class="public-nav-actions">

            <a
                class="public-login-button"
                href="<?= e(
                    url($accountPath)
                ) ?>"
            >
                <i class="fa-regular fa-user"></i>

                <?= e($accountLabel) ?>
            </a>

            <a
                class="public-book-button"
                href="<?= e(
                    url($bookingPath)
                ) ?>"
            >
                <i class="fa-regular fa-calendar-check"></i>

                Book Event
            </a>

        </div>

    </nav>

    <header
        class="public-hero"
        id="home"
        style="--hero-image: url('<?= e(
            $heroImage
        ) ?>');"
    >

        <div class="public-hero-content">

            <div class="public-hero-badge">

                <span></span>

                We Plan, You Celebrate

                <span></span>

            </div>

            <h1>

                <span class="public-hero-heading-line">
                    We Make Your
                </span>

                <span class="public-hero-script">
                    Dream Wedding
                </span>

                <span class="public-hero-heading-line">
                    Come True
                </span>

            </h1>

            <p>
                From elegant venues to stunning décor —
                we take care of every detail to make your
                big day unforgettable.
            </p>

            <div class="public-hero-actions">

                <a
                    class="public-primary-button"
                    href="#packages"
                    data-public-section-link="packages"
                >
                    Explore Packages

                    <i class="fa-solid fa-arrow-right"></i>
                </a>

                <a
                    class="public-secondary-button"
                    href="<?= e(
                        url($bookingPath)
                    ) ?>"
                >
                    Book Your Event

                    <i class="fa-regular fa-heart"></i>
                </a>

            </div>

        </div>

    </header>

    <section
        class="public-section public-section-white public-services-overview"
        id="services"
    >

        <span
            class="public-scroll-anchor"
            id="about"
            aria-hidden="true"
        ></span>

        <div class="public-section-heading">

            <span>
                Why choose us
            </span>

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

            <button
                class="public-feature-card"
                type="button"
                data-public-service="decor"
                aria-haspopup="dialog"
            >

                <div class="public-feature-icon">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>

                <h3>
                    Elegant Decorations
                </h3>

                <p>
                    Beautiful stage, entrance and venue
                    setups designed around your wedding
                    vision.
                </p>

                <span class="public-feature-action">
                    View Service Details
                    <i class="fa-solid fa-arrow-right"></i>
                </span>

            </button>

            <button
                class="public-feature-card"
                type="button"
                data-public-service="catering"
                aria-haspopup="dialog"
            >

                <div class="public-feature-icon">
                    <i class="fa-solid fa-utensils"></i>
                </div>

                <h3>
                    Premium Catering
                </h3>

                <p>
                    Delicious food, professional catering
                    teams and menu options customised for
                    your guests.
                </p>

                <span class="public-feature-action">
                    View Service Details
                    <i class="fa-solid fa-arrow-right"></i>
                </span>

            </button>

            <button
                class="public-feature-card"
                type="button"
                data-public-service="music"
                aria-haspopup="dialog"
            >

                <div class="public-feature-icon">
                    <i class="fa-solid fa-music"></i>
                </div>

                <h3>
                    Music and Atmosphere
                </h3>

                <p>
                    Choose background or live music to
                    create the perfect atmosphere for your
                    celebration.
                </p>

                <span class="public-feature-action">
                    View Service Details
                    <i class="fa-solid fa-arrow-right"></i>
                </span>

            </button>

            <button
                class="public-feature-card"
                type="button"
                data-public-service="management"
                aria-haspopup="dialog"
            >

                <div class="public-feature-icon">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>

                <h3>
                    Complete Management
                </h3>

                <p>
                    Our team manages the entire process
                    from preparation through final event
                    execution.
                </p>

                <span class="public-feature-action">
                    View Service Details
                    <i class="fa-solid fa-arrow-right"></i>
                </span>

            </button>

        </div>

    </section>

    <section
        class="public-section"
        id="packages"
    >

        <div class="public-section-heading">

            <span>
                Wedding packages
            </span>

            <h2>
                Choose Your Perfect Package
            </h2>

            <p>
                Explore our active wedding packages,
                prices and complete details.
            </p>

            <div class="public-section-heading-actions">

                <a
                    class="public-view-all"
                    href="<?= e(
                        url(
                            '/customer/all_packages.php'
                        )
                    ) ?>"
                >
                    <i class="fa-solid fa-border-all"></i>
                    View All Packages
                </a>

            </div>

        </div>

        <?php if ($packages === []): ?>

            <div class="public-empty">

                <i class="fa-solid fa-gift"></i>

                <h3>
                    No packages available
                </h3>

                <p>
                    Active packages will appear here when
                    added by the Admin.
                </p>

            </div>

        <?php else: ?>

            <div class="public-package-grid">

                <?php foreach (
                    $packages as $package
                ): ?>
                    <?php
                    $packageId =
                        (int) $package['id'];

                    $packageDescription = trim(
                        (string) (
                            $package[
                                'short_description'
                            ]
                            ?? $package[
                                'description'
                            ]
                            ?? ''
                        )
                    );

                    $fullDescription = trim(
                        (string) (
                            $package[
                                'description'
                            ]
                            ?? $packageDescription
                        )
                    );

                    $mainImage =
                        package_image_url(
                            $package[
                                'main_image'
                            ]
                            ?? null
                        );

                    $packageImages = [
                        $mainImage,

                        package_image_url(
                            $package[
                                'image_one'
                            ]
                            ?? null
                        ),

                        package_image_url(
                            $package[
                                'image_two'
                            ]
                            ?? null
                        ),

                        package_image_url(
                            $package[
                                'image_three'
                            ]
                            ?? null
                        ),
                    ];

                    $decoration = trim(
                        (string) (
                            $package[
                                'decoration_type'
                            ]
                            ?? ''
                        )
                    );

                    $packageVenue =
                        package_venue_display(
                            $package
                        );

                    if ($packageVenue === '') {
                        $packageVenue =
                            'Venue location not specified';
                    }
                    ?>

                    <article class="public-package-card">

                        <div class="public-card-gallery">

                            <img
                                class="public-card-main-image"
                                id="packageImage<?= e(
                                    (string) $packageId
                                ) ?>"
                                src="<?= e(
                                    $mainImage
                                ) ?>"
                                alt="<?= e(
                                    (string) $package[
                                        'name'
                                    ]
                                ) ?>"
                            >

                            <div class="public-card-thumbnails">

                                <?php foreach (
                                    $packageImages as
                                    $imageIndex =>
                                    $imageUrl
                                ): ?>

                                    <button
                                        class="public-thumbnail-button<?= $imageIndex === 0
                                            ? ' active'
                                            : '' ?>"
                                        type="button"
                                        data-image-target="packageImage<?= e(
                                            (string) $packageId
                                        ) ?>"
                                        data-image-url="<?= e(
                                            $imageUrl
                                        ) ?>"
                                        aria-label="Show package image <?= e(
                                            (string) (
                                                $imageIndex
                                                + 1
                                            )
                                        ) ?>"
                                    >
                                        <img
                                            src="<?= e(
                                                $imageUrl
                                            ) ?>"
                                            alt=""
                                        >

                                        <?php if (
                                            $imageIndex === 0
                                        ): ?>

                                            <span class="public-main-thumbnail-badge">
                                                Main
                                            </span>

                                        <?php endif; ?>

                                    </button>

                                <?php endforeach; ?>

                            </div>

                        </div>

                        <div class="public-card-body">

                            <h3>
                                <?= e(
                                    (string) $package[
                                        'name'
                                    ]
                                ) ?>
                            </h3>

                            <div class="public-card-location">

                                <i class="fa-solid fa-location-dot"></i>

                                <?= e(
                                    $packageVenue
                                ) ?>

                            </div>

                            <div class="public-card-price">

                                <?= e(
                                    format_package_price(
                                        (float) $package[
                                            'price'
                                        ]
                                    )
                                ) ?>

                            </div>

                            <p class="public-card-description">

                                <?= e(
                                    $packageDescription
                                    !== ''
                                        ? $packageDescription
                                        : 'Complete professional wedding package.'
                                ) ?>

                            </p>

                            <button
                                class="public-detail-button"
                                type="button"
                                data-public-detail

                                data-name="<?= e(
                                    (string) $package[
                                        'name'
                                    ]
                                ) ?>"

                                data-price="<?= e(
                                    format_package_price(
                                        (float) $package[
                                            'price'
                                        ]
                                    )
                                ) ?>"

                                data-description="<?= e(
                                    $fullDescription !== ''
                                        ? $fullDescription
                                        : 'Complete professional wedding package.'
                                ) ?>"

                                data-images="<?= e(
                                    public_json(
                                        $packageImages
                                    )
                                ) ?>"

                                data-detail-one="<?= e(
                                    'Venue: '
                                    . $packageVenue
                                ) ?>"

                                data-detail-two="<?= e(
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

                                data-detail-three="<?= e(
                                    'Decoration: '
                                    . (
                                        $decoration !== ''
                                            ? $decoration
                                            : 'Included'
                                    )
                                    . ' | Music: '
                                    . public_package_music(
                                        $package
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

    <section
        class="public-section public-section-white"
        id="venues"
    >

        <div class="public-section-heading">

            <span>
                Wedding venues
            </span>

            <h2>
                Beautiful Venues for Your Event
            </h2>

            <p>
                Explore beautiful venues, locations,
                prices and event details.
            </p>

            <div class="public-section-heading-actions">

                <a
                    class="public-view-all"
                    href="<?= e(
                        url(
                            '/customer/all_venues.php'
                        )
                    ) ?>"
                >
                    <i class="fa-solid fa-border-all"></i>
                    View All Venues
                </a>

            </div>

        </div>

        <?php if ($venues === []): ?>

            <div class="public-empty">

                <i class="fa-solid fa-hotel"></i>

                <h3>
                    No venues available
                </h3>

                <p>
                    Active venues will appear here when
                    added by the Admin.
                </p>

            </div>

        <?php else: ?>

            <div class="public-venue-grid">

                <?php foreach (
                    $venues as $venue
                ): ?>
                    <?php
                    $venueId =
                        (int) $venue['id'];

                    $venueDescription = trim(
                        (string) (
                            $venue[
                                'description'
                            ]
                            ?? ''
                        )
                    );

                    $mainImage =
                        venue_image_url(
                            $venue[
                                'main_image'
                            ]
                            ?? null
                        );

                    $venueImages = [
                        $mainImage,

                        venue_image_url(
                            $venue[
                                'image_one'
                            ]
                            ?? null
                        ),

                        venue_image_url(
                            $venue[
                                'image_two'
                            ]
                            ?? null
                        ),

                        venue_image_url(
                            $venue[
                                'image_three'
                            ]
                            ?? null
                        ),
                    ];

                    $facilities =
                        venue_facility_lines(
                            $venue[
                                'facilities'
                            ]
                            ?? null
                        );
                    ?>

                    <article class="public-venue-card">

                        <div class="public-card-gallery">

                            <img
                                class="public-card-main-image"
                                id="venueImage<?= e(
                                    (string) $venueId
                                ) ?>"
                                src="<?= e(
                                    $mainImage
                                ) ?>"
                                alt="<?= e(
                                    (string) $venue[
                                        'name'
                                    ]
                                ) ?>"
                            >

                            <div class="public-card-thumbnails">

                                <?php foreach (
                                    $venueImages as
                                    $imageIndex =>
                                    $imageUrl
                                ): ?>

                                    <button
                                        class="public-thumbnail-button<?= $imageIndex === 0
                                            ? ' active'
                                            : '' ?>"
                                        type="button"
                                        data-image-target="venueImage<?= e(
                                            (string) $venueId
                                        ) ?>"
                                        data-image-url="<?= e(
                                            $imageUrl
                                        ) ?>"
                                        aria-label="Show venue image <?= e(
                                            (string) (
                                                $imageIndex
                                                + 1
                                            )
                                        ) ?>"
                                    >
                                        <img
                                            src="<?= e(
                                                $imageUrl
                                            ) ?>"
                                            alt=""
                                        >

                                        <?php if (
                                            $imageIndex === 0
                                        ): ?>

                                            <span class="public-main-thumbnail-badge">
                                                Main
                                            </span>

                                        <?php endif; ?>

                                    </button>

                                <?php endforeach; ?>

                            </div>

                        </div>

                        <div class="public-card-body">

                            <h3>
                                <?= e(
                                    (string) $venue[
                                        'name'
                                    ]
                                ) ?>
                            </h3>

                            <div class="public-card-location">

                                <i class="fa-solid fa-location-dot"></i>

                                <?= e(
                                    (string) $venue[
                                        'location'
                                    ]
                                ) ?>

                            </div>

                            <div class="public-card-price">

                                <?= e(
                                    format_venue_price(
                                        (float) $venue[
                                            'price'
                                        ]
                                    )
                                ) ?>

                            </div>

                            <p class="public-card-description">

                                <?= e(
                                    $venueDescription !== ''
                                        ? $venueDescription
                                        : 'Professional wedding-event venue.'
                                ) ?>

                            </p>

                            <button
                                class="public-detail-button"
                                type="button"
                                data-public-detail

                                data-name="<?= e(
                                    (string) $venue[
                                        'name'
                                    ]
                                ) ?>"

                                data-price="<?= e(
                                    format_venue_price(
                                        (float) $venue[
                                            'price'
                                        ]
                                    )
                                ) ?>"

                                data-description="<?= e(
                                    $venueDescription !== ''
                                        ? $venueDescription
                                        : 'Professional wedding-event venue.'
                                ) ?>"

                                data-images="<?= e(
                                    public_json(
                                        $venueImages
                                    )
                                ) ?>"

                                data-detail-one="<?= e(
                                    'Location: '
                                    . (string) $venue[
                                        'location'
                                    ]
                                ) ?>"

                                data-detail-two="<?= e(
                                    'Guest capacity: '
                                    . number_format(
                                        (int) (
                                            $venue[
                                                'capacity'
                                            ]
                                            ?? 0
                                        )
                                    )
                                ) ?>"

                                data-detail-three="<?= e(
                                    'Facilities: '
                                    . (
                                        $facilities !== []
                                            ? implode(
                                                ', ',
                                                $facilities
                                            )
                                            : 'Not specified'
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

    <section
        class="public-section public-section-white"
        id="gallery"
    >

        <div class="public-section-heading">

            <span>
                Wedding gallery
            </span>

            <h2>
                Memorable Events and Beautiful Setups
            </h2>

            <p>
                View active wedding images uploaded by
                our Event Manager.
            </p>

            <div class="public-section-heading-actions">

                <a
                    class="public-view-all"
                    href="<?= e(
                        url(
                            '/customer/all_gallery.php'
                        )
                    ) ?>"
                >
                    <i class="fa-solid fa-images"></i>
                    View All Images
                </a>

            </div>

        </div>

        <?php if (
            $galleryImages === []
        ): ?>

            <div class="public-empty">

                <i class="fa-regular fa-images"></i>

                <h3>
                    No gallery images available
                </h3>

                <p>
                    Active wedding images will appear
                    here automatically.
                </p>

            </div>

        <?php else: ?>

            <div class="public-gallery-grid">

                <?php foreach (
                    $galleryImages as
                    $galleryImage
                ): ?>
                    <?php
                    $galleryTitle = trim(
                        (string) (
                            $galleryImage[
                                'title'
                            ]
                            ?? ''
                        )
                    );

                    $galleryTitle =
                        $galleryTitle !== ''
                            ? $galleryTitle
                            : 'Wedding Event';

                    $eventType = trim(
                        (string) (
                            $galleryImage[
                                'event_type'
                            ]
                            ?? ''
                        )
                    );

                    $eventType =
                        $eventType !== ''
                            ? $eventType
                            : 'Wedding Event';

                    $description = trim(
                        (string) (
                            $galleryImage[
                                'description'
                            ]
                            ?? ''
                        )
                    );

                    $description =
                        $description !== ''
                            ? $description
                            : 'No description provided.';

                    $galleryItems = [
                        gallery_image_url(
                            $galleryImage[
                                'image'
                            ]
                            ?? null
                        ),
                    ];

                    $secondImage = trim(
                        (string) (
                            $galleryImage[
                                'image_two'
                            ]
                            ?? ''
                        )
                    );

                    if (
                        $secondImage !== ''
                    ) {
                        $galleryItems[] =
                            gallery_image_url(
                                $secondImage
                            );
                    }
                    ?>

                    <button
                        class="public-gallery-item"
                        type="button"
                        data-public-gallery

                        data-images="<?= e(
                            public_json(
                                $galleryItems
                            )
                        ) ?>"

                        data-title="<?= e(
                            $galleryTitle
                        ) ?>"

                        data-event-type="<?= e(
                            $eventType
                        ) ?>"

                        data-description="<?= e(
                            $description
                        ) ?>"

                        aria-label="Open <?= e(
                            $galleryTitle
                        ) ?> gallery preview"
                    >

                        <img
                            src="<?= e(
                                $galleryItems[0]
                            ) ?>"
                            alt="<?= e(
                                $galleryTitle
                            ) ?>"
                        >

                        <?php if (
                            count(
                                $galleryItems
                            ) > 1
                        ): ?>

                            <span class="public-gallery-photo-count">

                                <i class="fa-solid fa-images"></i>

                                <?= e(
                                    (string) count(
                                        $galleryItems
                                    )
                                ) ?>

                                Photos

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
                                        $eventType
                                    ) ?>
                                </small>

                            </span>

                        </span>

                    </button>

                <?php endforeach; ?>

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
                    Create an account or log in to choose
                    a package, venue, additional services
                    and event date.
                </p>

            </div>

            <div class="public-contact-actions">

                <a
                    class="public-contact-primary"
                    href="<?= e(
                        url($bookingPath)
                    ) ?>"
                >
                    Book an Event
                </a>

                <?php if (
                    !$isLoggedIn
                ): ?>

                    <a
                        class="public-contact-secondary"
                        href="<?= e(
                            url(
                                '/auth/register.php'
                            )
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

                <h3>
                    Wedding Event Planner
                </h3>

                <p>
                    Making every wedding beautiful,
                    organised and memorable.
                </p>

            </div>

            <div class="public-footer-links">

                <a
                    href="#packages"
                    data-public-section-link="packages"
                >
                    Packages
                </a>

                <a
                    href="#venues"
                    data-public-section-link="venues"
                >
                    Venues
                </a>

                <a
                    href="#services"
                    data-public-section-link="services"
                >
                    Services
                </a>

                <a
                    href="#gallery"
                    data-public-section-link="gallery"
                >
                    Gallery
                </a>

                <a href="<?= e(
                    url($accountPath)
                ) ?>">
                    <?= e($accountLabel) ?>
                </a>

            </div>

        </div>

        <div class="public-footer-copyright">

            © <?= e($currentYear) ?>

            Wedding Event Planner.
            All rights reserved.

        </div>

    </footer>

    <div
        class="public-service-modal"
        id="publicServiceModal"
        aria-hidden="true"
    >

        <div
            class="public-service-modal-content"
            role="dialog"
            aria-modal="true"
            aria-labelledby="publicServiceTitle"
        >

            <button
                class="public-service-modal-close"
                id="publicServiceClose"
                type="button"
                aria-label="Close service details"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="public-service-modal-header">

                <div
                    class="public-service-modal-icon"
                    id="publicServiceIcon"
                >
                    <i class="fa-solid fa-heart"></i>
                </div>

                <span>
                    Professional Wedding Service
                </span>

                <h2 id="publicServiceTitle">
                    Service Details
                </h2>

            </div>

            <div class="public-service-modal-body">

                <p
                    class="public-service-modal-description"
                    id="publicServiceDescription"
                ></p>

                <ul
                    class="public-service-list"
                    id="publicServiceFeatures"
                ></ul>

            </div>

        </div>

    </div>

    <div
        class="public-modal"
        id="publicDetailsModal"
        aria-hidden="true"
    >

        <div
            class="public-modal-content"
            role="dialog"
            aria-modal="true"
        >

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

                    <div
                        class="public-modal-detail"
                        id="publicDetailOne"
                    ></div>

                    <div
                        class="public-modal-detail"
                        id="publicDetailTwo"
                    ></div>

                    <div
                        class="public-modal-detail"
                        id="publicDetailThree"
                    ></div>

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

        const body =
            document.body;

        const mobileButton =
            document.getElementById(
                "publicMobileButton"
            );

        const navigation =
            document.getElementById(
                "publicNavLinks"
            );

        const navigationLinks =
            Array.from(
                document.querySelectorAll(
                    "[data-public-nav-link]"
                )
            );

        const serviceModal =
            document.getElementById(
                "publicServiceModal"
            );

        const serviceClose =
            document.getElementById(
                "publicServiceClose"
            );

        const serviceIcon =
            document.getElementById(
                "publicServiceIcon"
            );

        const serviceTitle =
            document.getElementById(
                "publicServiceTitle"
            );

        const serviceDescription =
            document.getElementById(
                "publicServiceDescription"
            );

        const serviceFeatures =
            document.getElementById(
                "publicServiceFeatures"
            );

        const serviceDetailsData = {
            decor: {
                title:
                    "Customized Decoration Services",

                icon:
                    "fa-solid fa-wand-magic-sparkles",

                description:
                    "Make your special moments unforgettable with our customized decoration services. We create elegant themes for Engagement, Mehndi, Mayo, Nikkah, Barat, and Walima events, designed to match your style, colors, and budget.",

                features: [
                    "Elegant Stage Decoration",
                    "Fresh Floral Arrangements",
                    "Venue & Hall Decoration",
                    "Entrance & Walkway Décor",
                    "Table & Chair Styling",
                    "Budget-Friendly Packages",
                    "Premium Luxury Decoration",
                    "Complete Wedding Decoration Solutions"
                ]
            },

            catering: {
                title:
                    "Premium Catering Services",

                icon:
                    "fa-solid fa-utensils",

                description:
                    "We provide delicious cuisine and customized menus for every wedding event, ensuring quality, taste, and exceptional service for all your guests.",

                features: [
                    "Customized Menu Selection",
                    "Traditional & Continental Cuisine",
                    "Fresh & Hygienic Food",
                    "Professional Catering Staff",
                    "Desserts & Sweet Dishes",
                    "Beverages & Refreshments",
                    "Quality Food Preparation"
                ]
            },

            music: {
                title:
                    "Music and Atmosphere",

                icon:
                    "fa-solid fa-music",

                description:
                    "We manage standard audio systems to maintain an appropriate, lively, yet respectable environment for your family events:",

                features: [
                    "Background Sound Systems: Perfect for soft music or traditional songs during family entry ceremonies.",
                    "Clarity Mic Setup: Dedicated high-quality wireless microphones for Nikkah Khutba, prayers, and announcements.",
                    "Volume Level Optimization: Carefully balanced sound profiles so that elderly guests and children remain completely comfortable."
                ]
            },

            management: {
                title:
                    "Complete Event Management",

                icon:
                    "fa-solid fa-clipboard-check",

                description:
                    "Our specialized staff coordinates every logistical detail seamlessly from the start of the event until your last guest departs comfortably:",

                features: [
                    "Multi-Event Coordination: Full end-to-end management for Duay Khair, Engagement, Mayo, Mehndi, Nikkah, Barat, and Walima.",
                    "Strict Punctuality: Ensuring the gates open on schedule and dinner is served strictly at the requested time.",
                    "Guest Assistance Setup: Seamless crowd management and structured seating coordination for immediate family circles.",
                    "Power Failure Protection: Continuous heavy-duty backup generators to ensure completely uninterrupted lighting and cooling systems."
                ]
            }
        };

        let lastServiceTrigger = null;

        const detailsModal =
            document.getElementById(
                "publicDetailsModal"
            );

        const detailsImage =
            document.getElementById(
                "publicDetailsImage"
            );

        const detailsThumbnails =
            document.getElementById(
                "publicDetailsThumbnails"
            );

        const imageModal =
            document.getElementById(
                "publicImageModal"
            );

        const imagePreview =
            document.getElementById(
                "publicImagePreview"
            );

        const imageCounter =
            document.getElementById(
                "publicImageCounter"
            );

        const imagePrevious =
            document.getElementById(
                "publicImagePrevious"
            );

        const imageNext =
            document.getElementById(
                "publicImageNext"
            );

        let galleryImages = [];
        let galleryIndex = 0;

        function parseImages(value) {
            try {
                const images =
                    JSON.parse(
                        value || "[]"
                    );

                return Array.isArray(
                    images
                )
                    ? images.filter(Boolean)
                    : [];
            } catch (error) {
                return [];
            }
        }

        function setPageLocked(locked) {
            body.style.overflow =
                locked
                    ? "hidden"
                    : "";
        }

        function closeServiceModal() {
            serviceModal?.classList.remove(
                "open"
            );

            serviceModal?.setAttribute(
                "aria-hidden",
                "true"
            );

            if (serviceFeatures) {
                serviceFeatures.innerHTML =
                    "";
            }

            setPageLocked(false);

            lastServiceTrigger?.focus();

            lastServiceTrigger = null;
        }

        function openServiceModal(
            trigger
        ) {
            const serviceKey =
                trigger.dataset
                    .publicService
                || "";

            const serviceData =
                serviceDetailsData[
                    serviceKey
                ];

            if (
                !serviceData
                || !serviceModal
                || !serviceTitle
                || !serviceDescription
                || !serviceFeatures
                || !serviceIcon
            ) {
                return;
            }

            lastServiceTrigger =
                trigger;

            serviceTitle.textContent =
                serviceData.title;

            serviceDescription.textContent =
                serviceData.description;

            serviceIcon.innerHTML =
                "";

            const icon =
                document.createElement(
                    "i"
                );

            icon.className =
                serviceData.icon;

            serviceIcon.appendChild(
                icon
            );

            serviceFeatures.innerHTML =
                "";

            serviceFeatures.classList.toggle(
                "compact",
                serviceData.features.length > 5
            );

            serviceData.features.forEach(
                function (feature) {
                    const listItem =
                        document.createElement(
                            "li"
                        );

                    const checkIcon =
                        document.createElement(
                            "i"
                        );

                    const text =
                        document.createElement(
                            "span"
                        );

                    checkIcon.className =
                        "fa-solid fa-circle-check";

                    text.textContent =
                        feature;

                    listItem.append(
                        checkIcon,
                        text
                    );

                    serviceFeatures
                        .appendChild(
                            listItem
                        );
                }
            );

            serviceModal.classList.add(
                "open"
            );

            serviceModal.setAttribute(
                "aria-hidden",
                "false"
            );

            setPageLocked(true);

            window.requestAnimationFrame(
                function () {
                    serviceClose?.focus();
                }
            );
        }

        document
            .querySelectorAll(
                "[data-public-service]"
            )
            .forEach(
                function (button) {
                    button.addEventListener(
                        "click",
                        function () {
                            openServiceModal(
                                button
                            );
                        }
                    );
                }
            );

        serviceClose?.addEventListener(
            "click",
            closeServiceModal
        );

        serviceModal?.addEventListener(
            "click",
            function (event) {
                if (
                    event.target
                    === serviceModal
                ) {
                    closeServiceModal();
                }
            }
        );

        function setActiveNavigation(
            sectionId
        ) {
            navigationLinks.forEach(
                function (link) {
                    const linkTarget =
                        link
                            .getAttribute(
                                "href"
                            )
                            ?.replace(
                                "#",
                                ""
                            );

                    link.classList.toggle(
                        "active",
                        linkTarget === sectionId
                    );
                }
            );
        }

        function setNavigationFromHash() {
            const sectionId =
                window.location.hash
                    .replace(
                        "#",
                        ""
                    );

            const validSection =
                navigationLinks.some(
                    function (link) {
                        return (
                            link.getAttribute(
                                "href"
                            )
                            === `#${sectionId}`
                        );
                    }
                );

            setActiveNavigation(
                validSection
                    ? sectionId
                    : "home"
            );
        }

        mobileButton?.addEventListener(
            "click",
            function () {
                navigation?.classList.toggle(
                    "open"
                );
            }
        );

        navigationLinks.forEach(
            function (link) {
                link.addEventListener(
                    "click",
                    function () {
                        const sectionId =
                            link
                                .getAttribute(
                                    "href"
                                )
                                ?.replace(
                                    "#",
                                    ""
                                );

                        if (sectionId) {
                            setActiveNavigation(
                                sectionId
                            );
                        }

                        navigation?.classList.remove(
                            "open"
                        );
                    }
                );
            }
        );

        document
            .querySelectorAll(
                "[data-public-section-link]"
            )
            .forEach(
                function (link) {
                    link.addEventListener(
                        "click",
                        function () {
                            const sectionId =
                                link.dataset
                                    .publicSectionLink;

                            if (sectionId) {
                                setActiveNavigation(
                                    sectionId
                                );
                            }
                        }
                    );
                }
            );

        window.addEventListener(
            "hashchange",
            setNavigationFromHash
        );

        window.addEventListener(
            "load",
            setNavigationFromHash
        );

        setNavigationFromHash();

        document
            .querySelectorAll(
                "[data-image-target]"
            )
            .forEach(
                function (button) {
                    button.addEventListener(
                        "click",
                        function () {
                            const target =
                                document.getElementById(
                                    button.dataset
                                        .imageTarget
                                    || ""
                                );

                            const imageUrl =
                                button.dataset
                                    .imageUrl
                                || "";

                            if (
                                !target
                                || !imageUrl
                            ) {
                                return;
                            }

                            target.style.opacity =
                                "0.35";

                            window.setTimeout(
                                function () {
                                    target.src =
                                        imageUrl;

                                    target.style.opacity =
                                        "1";
                                },
                                120
                            );

                            button
                                .parentElement
                                ?.querySelectorAll(
                                    ".public-thumbnail-button"
                                )
                                .forEach(
                                    function (item) {
                                        item
                                            .classList
                                            .remove(
                                                "active"
                                            );
                                    }
                                );

                            button.classList.add(
                                "active"
                            );
                        }
                    );
                }
            );

        function closeDetailsModal() {
            detailsModal?.classList.remove(
                "open"
            );

            detailsModal?.setAttribute(
                "aria-hidden",
                "true"
            );

            detailsThumbnails.innerHTML =
                "";

            detailsImage.src = "";

            setPageLocked(false);
        }

        document
            .querySelectorAll(
                "[data-public-detail]"
            )
            .forEach(
                function (button) {
                    button.addEventListener(
                        "click",
                        function () {
                            const images =
                                parseImages(
                                    button.dataset
                                        .images
                                );

                            document
                                .getElementById(
                                    "publicDetailsName"
                                )
                                .textContent =
                                    button.dataset
                                        .name
                                    || "";

                            document
                                .getElementById(
                                    "publicDetailsPrice"
                                )
                                .textContent =
                                    button.dataset
                                        .price
                                    || "";

                            document
                                .getElementById(
                                    "publicDetailsDescription"
                                )
                                .textContent =
                                    button.dataset
                                        .description
                                    || "";

                            document
                                .getElementById(
                                    "publicDetailOne"
                                )
                                .textContent =
                                    button.dataset
                                        .detailOne
                                    || "";

                            document
                                .getElementById(
                                    "publicDetailTwo"
                                )
                                .textContent =
                                    button.dataset
                                        .detailTwo
                                    || "";

                            document
                                .getElementById(
                                    "publicDetailThree"
                                )
                                .textContent =
                                    button.dataset
                                        .detailThree
                                    || "";

                            detailsImage.src =
                                images[0]
                                || "";

                            detailsThumbnails.innerHTML =
                                "";

                            images.forEach(
                                function (
                                    imageUrl,
                                    index
                                ) {
                                    const thumbnail =
                                        document
                                            .createElement(
                                                "button"
                                            );

                                    const image =
                                        document
                                            .createElement(
                                                "img"
                                            );

                                    thumbnail.type =
                                        "button";

                                    thumbnail.className =
                                        "public-modal-thumbnail-button";

                                    thumbnail.classList.toggle(
                                        "active",
                                        index === 0
                                    );

                                    image.src =
                                        imageUrl;

                                    image.alt =
                                        `Wedding image ${index + 1}`;

                                    thumbnail.appendChild(
                                        image
                                    );

                                    if (
                                        index === 0
                                    ) {
                                        const badge =
                                            document
                                                .createElement(
                                                    "span"
                                                );

                                        badge.className =
                                            "public-main-thumbnail-badge";

                                        badge.textContent =
                                            "Main";

                                        thumbnail.appendChild(
                                            badge
                                        );
                                    }

                                    thumbnail.addEventListener(
                                        "click",
                                        function () {
                                            detailsImage.src =
                                                imageUrl;

                                            detailsThumbnails
                                                .querySelectorAll(
                                                    "button"
                                                )
                                                .forEach(
                                                    function (
                                                        item
                                                    ) {
                                                        item
                                                            .classList
                                                            .remove(
                                                                "active"
                                                            );
                                                    }
                                                );

                                            thumbnail
                                                .classList
                                                .add(
                                                    "active"
                                                );
                                        }
                                    );

                                    detailsThumbnails
                                        .appendChild(
                                            thumbnail
                                        );
                                }
                            );

                            detailsModal?.classList.add(
                                "open"
                            );

                            detailsModal?.setAttribute(
                                "aria-hidden",
                                "false"
                            );

                            setPageLocked(true);
                        }
                    );
                }
            );

        document
            .getElementById(
                "publicDetailsClose"
            )
            ?.addEventListener(
                "click",
                closeDetailsModal
            );

        detailsModal?.addEventListener(
            "click",
            function (event) {
                if (
                    event.target
                    === detailsModal
                ) {
                    closeDetailsModal();
                }
            }
        );

        function renderGalleryImage() {
            if (
                galleryImages.length
                === 0
            ) {
                return;
            }

            imagePreview.src =
                galleryImages[
                    galleryIndex
                ];

            imageCounter.textContent =
                `${galleryIndex + 1} / ${galleryImages.length}`;

            const showArrows =
                galleryImages.length > 1;

            imagePrevious.hidden =
                !showArrows;

            imageNext.hidden =
                !showArrows;

            imageCounter.hidden =
                !showArrows;
        }

        function closeGalleryModal() {
            imageModal?.classList.remove(
                "open"
            );

            imageModal?.setAttribute(
                "aria-hidden",
                "true"
            );

            imagePreview.src = "";

            galleryImages = [];
            galleryIndex = 0;

            setPageLocked(false);
        }

        function showPreviousImage() {
            if (
                galleryImages.length < 2
            ) {
                return;
            }

            galleryIndex =
                (
                    galleryIndex
                    - 1
                    + galleryImages.length
                )
                % galleryImages.length;

            renderGalleryImage();
        }

        function showNextImage() {
            if (
                galleryImages.length < 2
            ) {
                return;
            }

            galleryIndex =
                (
                    galleryIndex
                    + 1
                )
                % galleryImages.length;

            renderGalleryImage();
        }

        document
            .querySelectorAll(
                "[data-public-gallery]"
            )
            .forEach(
                function (button) {
                    button.addEventListener(
                        "click",
                        function () {
                            galleryImages =
                                parseImages(
                                    button.dataset
                                        .images
                                );

                            if (
                                galleryImages.length
                                === 0
                            ) {
                                return;
                            }

                            galleryIndex = 0;

                            document
                                .getElementById(
                                    "publicImageTitle"
                                )
                                .textContent =
                                    button.dataset
                                        .title
                                    || "";

                            document
                                .getElementById(
                                    "publicImageEventType"
                                )
                                .textContent =
                                    button.dataset
                                        .eventType
                                    || "";

                            document
                                .getElementById(
                                    "publicImageDescription"
                                )
                                .textContent =
                                    button.dataset
                                        .description
                                    || "";

                            renderGalleryImage();

                            imageModal?.classList.add(
                                "open"
                            );

                            imageModal?.setAttribute(
                                "aria-hidden",
                                "false"
                            );

                            setPageLocked(true);
                        }
                    );
                }
            );

        imagePrevious?.addEventListener(
            "click",
            showPreviousImage
        );

        imageNext?.addEventListener(
            "click",
            showNextImage
        );

        document
            .getElementById(
                "publicImageClose"
            )
            ?.addEventListener(
                "click",
                closeGalleryModal
            );

        imageModal?.addEventListener(
            "click",
            function (event) {
                if (
                    event.target
                    === imageModal
                ) {
                    closeGalleryModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (
                    serviceModal
                        ?.classList
                        .contains(
                            "open"
                        )
                ) {
                    if (
                        event.key
                        === "Escape"
                    ) {
                        closeServiceModal();
                    }

                    return;
                }

                if (
                    imageModal
                        ?.classList
                        .contains(
                            "open"
                        )
                ) {
                    if (
                        event.key
                        === "ArrowLeft"
                    ) {
                        showPreviousImage();
                    } else if (
                        event.key
                        === "ArrowRight"
                    ) {
                        showNextImage();
                    } else if (
                        event.key
                        === "Escape"
                    ) {
                        closeGalleryModal();
                    }

                    return;
                }

                if (
                    event.key
                    === "Escape"
                    && detailsModal
                        ?.classList
                        .contains(
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