<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/package_helpers.php';

require_role('booking_manager');

$connection = db();
$bookingManagerId = (int) ($_SESSION['user_id'] ?? 0);

$managerStatement = $connection->prepare(
    'SELECT full_name, email, profile_image, about
     FROM users
     WHERE id = ? AND role = ?
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

$totalPackages = (int) $connection
    ->query(
        'SELECT COUNT(*)
         FROM packages'
    )
    ->fetchColumn();

$activePackages = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM packages
         WHERE status = 'active'"
    )
    ->fetchColumn();

$inactivePackages = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM packages
         WHERE status = 'inactive'"
    )
    ->fetchColumn();

$popularPackage = $connection
    ->query(
        'SELECT
            p.id,
            p.name,
            (
                SELECT COUNT(*)
                FROM bookings b
                WHERE b.package_id = p.id
            ) AS booking_total
         FROM packages p
         ORDER BY
            booking_total DESC,
            p.created_at DESC,
            p.id DESC
         LIMIT 1'
    )
    ->fetch();

$popularPackageBookings = (int) (
    $popularPackage['booking_total']
    ?? 0
);

$popularPackageName = 'No booking yet';

if ($popularPackageBookings > 0) {
    $popularPackageName = trim(
        (string) (
            $popularPackage['name']
            ?? ''
        )
    );

    if ($popularPackageName === '') {
        $popularPackageName =
            'Popular package';
    }
}

$packages = $connection
    ->query(
        "SELECT *
         FROM packages
         WHERE status = 'active'
         ORDER BY created_at DESC, id DESC
         LIMIT 3"
    )
    ->fetchAll();

$sidebarLinks = [
    [
        '/booking_manager/dashboard.php',
        'fa-gauge',
        'Dashboard',
    ],
    [
        '/booking_manager/bookings.php',
        'fa-calendar-check',
        'Manage Bookings',
    ],
    [
        '/booking_manager/booking.php',
        'fa-calendar-plus',
        'Create Booking',
    ],
    [
        '/booking_manager/services.php',
        'fa-bell-concierge',
        'View Services',
    ],
    [
        '/booking_manager/gallery.php',
        'fa-images',
        'View Gallery',
    ],
    [
        '/booking_manager/packages.php',
        'fa-gift',
        'View Packages',
        true,
    ],
    [
        '/booking_manager/venues.php',
        'fa-hotel',
        'View Venues',
    ],
    [
        '/booking_manager/profile.php',
        'fa-user',
        'Manage Profile',
    ],
    [
        '/booking_manager/notifications.php',
        'fa-bell',
        'View Notifications',
    ],
];

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
        View Packages | <?= e(APP_NAME) ?>
    </title>

    <?php
    require __DIR__
        . '/../includes/pwa_head.php';
    ?>

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
            url(
                '/assets/css/booking_manager_views.css'
            )
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/package_management.css'
                . '?v=20260702-2'
            )
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/booking_manager_packages.css'
                . '?v=20260702-6'
            )
        ) ?>"
    >
</head>

<body
    class="booking-manager-page booking-manager-packages-page"
>

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

            <?php foreach (
                $sidebarLinks as $link
            ): ?>

                <a
                    class="<?= !empty($link[3])
                        ? 'active'
                        : '' ?>"
                    href="<?= e(
                        url($link[0])
                    ) ?>"
                >
                    <i
                        class="fa-solid <?= e(
                            $link[1]
                        ) ?>"
                    ></i>

                    <?= e($link[2]) ?>
                </a>

            <?php endforeach; ?>

            <a
                class="booking-logout"
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
                    <i
                        class="fa-solid fa-bars"
                    ></i>
                </button>

                <div class="booking-heading">

                    <h1>Wedding Packages</h1>

                    <p>
                        View active packages and create a
                        package booking for a customer.
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
                    <i
                        class="fa-solid fa-bell"
                    ></i>

                    <?php if (
                        $unreadNotifications > 0
                    ): ?>

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
                        url(
                            '/booking_manager/profile.php'
                        )
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

        <section
            class="summary-cards package-summary-cards manager-package-summary"
            aria-label="Package summary"
        >

            <a
                class="summary-card package-summary-card manager-package-summary-card total"
                href="<?= e(
                    url(
                        '/booking_manager/all_packages.php'
                        . '?status=all'
                    )
                ) ?>"
            >

                <div
                    class="summary-icon pink manager-package-summary-icon"
                >
                    <i
                        class="fa-solid fa-gift"
                    ></i>
                </div>

                <div
                    class="manager-package-summary-copy"
                >
                    <h4>Total Packages</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $totalPackages
                            )
                        ) ?>
                    </h2>

                    <p>Click to show all</p>
                </div>

            </a>

            <a
                class="summary-card package-summary-card manager-package-summary-card active"
                href="<?= e(
                    url(
                        '/booking_manager/all_packages.php'
                        . '?status=active'
                    )
                ) ?>"
            >

                <div
                    class="summary-icon purple manager-package-summary-icon"
                >
                    <i
                        class="fa-solid fa-circle-check"
                    ></i>
                </div>

                <div
                    class="manager-package-summary-copy"
                >
                    <h4>Active Packages</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $activePackages
                            )
                        ) ?>
                    </h2>

                    <p>Visible on website</p>
                </div>

            </a>

            <a
                class="summary-card package-summary-card manager-package-summary-card inactive"
                href="<?= e(
                    url(
                        '/booking_manager/all_packages.php'
                        . '?status=inactive'
                    )
                ) ?>"
            >

                <div
                    class="summary-icon orange manager-package-summary-icon"
                >
                    <i
                        class="fa-solid fa-circle-pause"
                    ></i>
                </div>

                <div
                    class="manager-package-summary-copy"
                >
                    <h4>Inactive Packages</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $inactivePackages
                            )
                        ) ?>
                    </h2>

                    <p>Hidden from website</p>
                </div>

            </a>

            <a
                class="summary-card package-summary-card manager-package-summary-card popular"
                href="<?= e(
                    url(
                        '/booking_manager/all_packages.php'
                        . '?status=all&sort=popular'
                    )
                ) ?>"
            >

                <div
                    class="summary-icon blue manager-package-summary-icon"
                >
                    <i
                        class="fa-solid fa-star"
                    ></i>
                </div>

                <div
                    class="package-popular-summary manager-package-summary-copy"
                >
                    <h4>Popular Package</h4>

                    <h2
                        title="<?= e(
                            $popularPackageName
                        ) ?>"
                    >
                        <?= e(
                            $popularPackageName
                        ) ?>
                    </h2>

                    <p>
                        <?=
                        $popularPackageBookings > 0
                            ? e(
                                number_format(
                                    $popularPackageBookings
                                )
                                . ' booking'
                                . (
                                    $popularPackageBookings === 1
                                        ? ''
                                        : 's'
                                )
                            )
                            : 'Based on bookings'
                        ?>
                    </p>
                </div>

            </a>

        </section>

        <section class="manager-package-box">

            <div class="manager-package-heading">

                <div>
                    <h2>
                        Available Wedding Packages
                    </h2>

                    <p>
                        View package images, facilities,
                        music options and current pricing.
                    </p>
                </div>

                <a
                    class="manager-package-view-all"
                    href="<?= e(
                        url(
                            '/booking_manager/all_packages.php'
                        )
                    ) ?>"
                >
                    <i
                        class="fa-solid fa-table-cells-large"
                    ></i>

                    View All Packages
                </a>

            </div>

            <?php if ($packages === []): ?>

                <div class="manager-package-empty">

                    <i
                        class="fa-solid fa-gift"
                    ></i>

                    <h3>
                        No active packages found
                    </h3>

                    <p>
                        Packages activated by the Admin
                        will appear here automatically.
                    </p>

                </div>

            <?php else: ?>

                <div class="manager-package-grid">

                    <?php foreach (
                        $packages as $package
                    ): ?>

                        <?php

                        $packageId = (int) (
                            $package['id']
                        );

                        $features =
                            package_feature_lines(
                                $package['features']
                                ?? null
                            );

                        $musicOptions = [];

                        if (
                            (int) (
                                $package['basic_music']
                                ?? 0
                            ) === 1
                        ) {
                            $musicOptions[] =
                                'Basic Music';
                        }

                        if (
                            (int) (
                                $package['live_music']
                                ?? 0
                            ) === 1
                        ) {
                            $musicOptions[] =
                                'Live Music';
                        }

                        $musicText =
                            $musicOptions !== []
                                ? implode(
                                    ' and ',
                                    $musicOptions
                                )
                                : 'No music selected';

                        $mainImage =
                            package_image_url(
                                $package['main_image']
                                ?? null
                            );

                        $previewImages = [
                            [
                                'url' =>
                                    $mainImage,
                                'is_main' =>
                                    true,
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
                                    $package[
                                        $imageColumn
                                    ]
                                    ?? ''
                                )
                            );

                            if (
                                $imagePath !== ''
                            ) {
                                $previewImages[] = [
                                    'url' =>
                                        package_image_url(
                                            $imagePath
                                        ),
                                    'is_main' =>
                                        false,
                                ];
                            }
                        }

                        $shortDescription = trim(
                            (string) (
                                $package[
                                    'short_description'
                                ]
                                ?? ''
                            )
                        );

                        $fullDescription = trim(
                            (string) (
                                $package[
                                    'description'
                                ]
                                ?? ''
                            )
                        );

                        if (
                            $fullDescription === ''
                        ) {
                            $fullDescription =
                                $shortDescription;
                        }

                        if (
                            $fullDescription === ''
                        ) {
                            $fullDescription =
                                'Complete wedding-event package.';
                        }

                        $cateringMenu = trim(
                            (string) (
                                $package[
                                    'catering_menu'
                                ]
                                ?? ''
                            )
                        );

                        $decorationType = trim(
                            (string) (
                                $package[
                                    'decoration_type'
                                ]
                                ?? ''
                            )
                        );

                        $featureText =
                            $features !== []
                                ? implode(
                                    '||',
                                    $features
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

                        <article
                            class="manager-package-card"
                        >

                            <div
                                class="manager-package-main-image-wrap"
                            >

                                <img
                                    class="manager-package-main-image"
                                    id="managerPackageCardMainImage<?= e(
                                        (string) $packageId
                                    ) ?>"
                                    src="<?= e(
                                        $mainImage
                                    ) ?>"
                                    alt="<?= e(
                                        (string) (
                                            $package['name']
                                        )
                                    ) ?>"
                                >

                                <span
                                    class="manager-package-main-badge"
                                >
                                    <i
                                        class="fa-regular fa-image"
                                    ></i>

                                    Main Photo
                                </span>

                            </div>

                            <div
                                class="manager-package-card-body"
                            >

                                <span
                                    class="manager-package-status"
                                >
                                    Available
                                </span>

                                <div
                                    class="manager-package-card-head"
                                >

                                    <h3>
                                        <?= e(
                                            (string) (
                                                $package[
                                                    'name'
                                                ]
                                            )
                                        ) ?>
                                    </h3>

                                    <strong
                                        class="manager-package-price"
                                    >
                                        <?= e(
                                            format_package_price(
                                                (float) (
                                                    $package[
                                                        'price'
                                                    ]
                                                )
                                            )
                                        ) ?>
                                    </strong>

                                </div>

                                <p
                                    class="manager-package-description"
                                >
                                    <?= e(
                                        $shortDescription
                                        !== ''
                                            ? $shortDescription
                                            : package_card_description(
                                                $package
                                            )
                                    ) ?>
                                </p>

                                <div
                                    class="manager-package-thumbnails"
                                >

                                    <?php foreach (
                                        $previewImages
                                        as $index =>
                                            $previewImage
                                    ): ?>

                                        <button
                                            class="manager-package-thumbnail <?= $index === 0
                                                ? 'active'
                                                : '' ?>"
                                            type="button"
                                            data-card-target="managerPackageCardMainImage<?= e(
                                                (string) $packageId
                                            ) ?>"
                                            data-card-image="<?= e(
                                                (string) (
                                                    $previewImage[
                                                        'url'
                                                    ]
                                                )
                                            ) ?>"
                                            data-card-is-main="<?= $previewImage[
                                                'is_main'
                                            ]
                                                ? 'true'
                                                : 'false' ?>"
                                            aria-label="<?= $previewImage[
                                                'is_main'
                                            ]
                                                ? 'Show original main package photo'
                                                : 'Show package gallery photo '
                                                    . e(
                                                        (string) $index
                                                    ) ?>"
                                        >
                                            <img
                                                src="<?= e(
                                                    (string) (
                                                        $previewImage[
                                                            'url'
                                                        ]
                                                    )
                                                ) ?>"
                                                alt="<?= $previewImage[
                                                    'is_main'
                                                ]
                                                    ? 'Original main package photo'
                                                    : 'Package gallery photo '
                                                        . e(
                                                            (string) $index
                                                        ) ?>"
                                            >

                                            <?php if (
                                                $previewImage[
                                                    'is_main'
                                                ]
                                            ): ?>

                                                <span>
                                                    Main
                                                </span>

                                            <?php endif; ?>
                                        </button>

                                    <?php endforeach; ?>

                                </div>

                                <ul
                                    class="manager-package-details"
                                >

                                    <li>
                                        <i
                                            class="fa-solid fa-users"
                                        ></i>

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
                                        <i
                                            class="fa-solid fa-wand-magic-sparkles"
                                        ></i>

                                        <?= e(
                                            $decorationType
                                            !== ''
                                                ? $decorationType
                                                : 'Wedding decoration included'
                                        ) ?>
                                    </li>

                                    <li>
                                        <i
                                            class="fa-solid fa-music"
                                        ></i>

                                        <?= e(
                                            $musicText
                                        ) ?>
                                    </li>

                                </ul>

                                <div
                                    class="manager-package-actions"
                                >

                                    <button
                                        class="manager-package-details-button"
                                        type="button"
                                        data-package-details
                                        data-id="<?= e(
                                            (string) $packageId
                                        ) ?>"
                                        data-name="<?= e(
                                            (string) (
                                                $package[
                                                    'name'
                                                ]
                                            )
                                        ) ?>"
                                        data-price="<?= e(
                                            format_package_price(
                                                (float) (
                                                    $package[
                                                        'price'
                                                    ]
                                                )
                                            )
                                        ) ?>"
                                        data-description="<?= e(
                                            $fullDescription
                                        ) ?>"
                                        data-decoration="<?= e(
                                            $decorationType
                                            !== ''
                                                ? $decorationType
                                                : 'Not specified'
                                        ) ?>"
                                        data-guests="<?= e(
                                            number_format(
                                                (int) (
                                                    $package[
                                                        'guest_capacity'
                                                    ]
                                                    ?? 0
                                                )
                                            )
                                        ) ?>"
                                        data-catering="<?= e(
                                            $cateringMenu
                                            !== ''
                                                ? $cateringMenu
                                                : 'Not specified'
                                        ) ?>"
                                        data-music="<?= e(
                                            $musicText
                                        ) ?>"
                                        data-features="<?= e(
                                            $featureText
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
                                        class="manager-package-book-button"
                                        href="<?= e(
                                            url(
                                                '/booking_manager/booking.php'
                                                . '?package_id='
                                                . $packageId
                                            )
                                        ) ?>"
                                    >
                                        Book Package
                                    </a>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <footer class="manager-view-footer">
            © <?= e(
                (string) $currentYear
            ) ?>
            Wedding Event Planner.
            All rights reserved.
        </footer>

    </main>

    <div
        class="manager-package-modal"
        id="managerPackageModal"
        data-booking-base="<?= e(
            url(
                '/booking_manager/booking.php'
                . '?package_id='
            )
        ) ?>"
        aria-hidden="true"
    >

        <div
            class="manager-package-modal-content"
        >

            <button
                class="manager-package-modal-close"
                id="managerPackageModalClose"
                type="button"
                aria-label="Close package details"
            >
                &times;
            </button>

            <div
                class="manager-package-modal-grid"
            >

                <div>

                    <div
                        class="manager-package-modal-image-wrap"
                    >

                        <img
                            class="manager-package-modal-main-image"
                            id="managerPackageModalMainImage"
                            src=""
                            alt="Package image"
                        >

                        <span
                            class="manager-package-modal-main-badge"
                            id="managerPackageModalMainBadge"
                        >
                            <i
                                class="fa-regular fa-image"
                            ></i>

                            Main Photo
                        </span>

                    </div>

                    <div
                        class="manager-package-modal-thumbnails"
                        id="managerPackageModalThumbnails"
                    ></div>

                </div>

                <div
                    class="manager-package-modal-info"
                >

                    <h2
                        id="managerPackageModalName"
                    ></h2>

                    <div
                        class="manager-package-modal-price"
                        id="managerPackageModalPrice"
                    ></div>

                    <p
                        class="manager-package-modal-description"
                        id="managerPackageModalDescription"
                    ></p>

                    <div
                        class="manager-package-information-row"
                    >
                        <strong>
                            Decoration:
                        </strong>

                        <span
                            id="managerPackageModalDecoration"
                        ></span>
                    </div>

                    <div
                        class="manager-package-information-row"
                    >
                        <strong>
                            Guest capacity:
                        </strong>

                        <span
                            id="managerPackageModalGuests"
                        ></span>
                    </div>

                    <div
                        class="manager-package-information-row"
                    >
                        <strong>
                            Music:
                        </strong>

                        <span
                            id="managerPackageModalMusic"
                        ></span>
                    </div>

                    <div
                        class="manager-package-information-row"
                    >
                        <strong>
                            Catering menu:
                        </strong>

                        <span
                            id="managerPackageModalCatering"
                        ></span>
                    </div>

                    <div
                        class="manager-package-feature-box"
                    >

                        <h3>
                            Additional Features
                        </h3>

                        <ul
                            id="managerPackageModalFeatures"
                        ></ul>

                    </div>

                    <a
                        class="manager-package-modal-book"
                        id="managerPackageModalBook"
                        href="#"
                    >
                        Book This Package
                    </a>

                </div>

            </div>

        </div>

    </div>

    <script
        src="<?= e(
            url(
                '/assets/js/booking_manager_packages.js'
                . '?v=20260702-5'
            )
        ) ?>"
        defer
    ></script>

    <?php
    require __DIR__
        . '/../includes/pwa_scripts.php';
    ?>

</body>
</html>