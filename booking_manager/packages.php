<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/package_helpers.php';

require_role('booking_manager');

$connection = db();
$bookingManagerId = (int) $_SESSION['user_id'];

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
    ? url('/' . ltrim((string) $manager['profile_image'], '/'))
    : url('/assets/icons/icon-192.png');

$managerAbout = trim((string) ($manager['about'] ?? ''));

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

$unreadNotifications = (int) $notificationStatement->fetchColumn();

$activePackages = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM packages
         WHERE status = 'active'"
    )
    ->fetchColumn();

$minimumPackagePrice = (float) $connection
    ->query(
        "SELECT COALESCE(MIN(price), 0)
         FROM packages
         WHERE status = 'active'"
    )
    ->fetchColumn();

$maximumGuestCapacity = (int) $connection
    ->query(
        "SELECT COALESCE(MAX(guest_capacity), 0)
         FROM packages
         WHERE status = 'active'"
    )
    ->fetchColumn();

$packages = $connection
    ->query(
        "SELECT *
         FROM packages
         WHERE status = 'active'
         ORDER BY created_at DESC, id DESC
         LIMIT 3"
    )
    ->fetchAll();

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

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(url('/assets/css/booking_manager_dashboard.css')) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(url('/assets/css/booking_manager_views.css')) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(url('/assets/css/booking_manager_packages.css')) ?>"
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
                <?= e((string) $manager['full_name']) ?>
            </h2>

            <p><?= e($managerAbout) ?></p>

            <div class="booking-online">
                ● Online
            </div>

        </div>

        <nav class="booking-menu">

            <a href="<?= e(url('/booking_manager/dashboard.php')) ?>">
                <i class="fa-solid fa-gauge"></i>
                Dashboard
            </a>

            <a href="<?= e(url('/booking_manager/bookings.php')) ?>">
                <i class="fa-solid fa-calendar-check"></i>
                Manage Bookings
            </a>

            <a href="<?= e(url('/booking_manager/booking.php')) ?>">
                <i class="fa-solid fa-calendar-plus"></i>
                Create Booking
            </a>

            <a href="<?= e(url('/booking_manager/services.php')) ?>">
                <i class="fa-solid fa-bell-concierge"></i>
                View Services
            </a>

            <a href="<?= e(url('/booking_manager/gallery.php')) ?>">
                <i class="fa-solid fa-images"></i>
                View Gallery
            </a>

            <a
                class="active"
                href="<?= e(url('/booking_manager/packages.php')) ?>"
            >
                <i class="fa-solid fa-gift"></i>
                View Packages
            </a>

            <a href="<?= e(url('/booking_manager/venues.php')) ?>">
                <i class="fa-solid fa-hotel"></i>
                View Venues
            </a>

            <a href="<?= e(url('/booking_manager/profile.php')) ?>">
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a href="<?= e(url('/booking_manager/notifications.php')) ?>">
                <i class="fa-solid fa-bell"></i>
                View Notifications
            </a>

            <a
                class="booking-logout"
                href="<?= e(url('/auth/logout.php')) ?>"
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

                    <h1>Wedding Packages</h1>

                    <p>
                        View active packages and create a package booking for a customer.
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
                    href="<?= e(url('/booking_manager/notifications.php')) ?>"
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

        <section class="manager-view-summary">

            <article class="manager-view-summary-card">

                <div class="manager-view-summary-icon">
                    <i class="fa-solid fa-gift"></i>
                </div>

                <div>
                    <h4>Active Packages</h4>
                    <h2><?= e((string) $activePackages) ?></h2>
                </div>

            </article>

            <article class="manager-view-summary-card">

                <div class="manager-view-summary-icon">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>

                <div>
                    <h4>Starting Price</h4>
                    <h2><?= e(format_package_price($minimumPackagePrice)) ?></h2>
                </div>

            </article>

            <article class="manager-view-summary-card">

                <div class="manager-view-summary-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <div>
                    <h4>Maximum Capacity</h4>
                    <h2><?= e(number_format($maximumGuestCapacity)) ?></h2>
                </div>

            </article>

        </section>

        <section class="manager-package-box">

            <div class="manager-package-heading">

                <div>
                    <h2>Available Wedding Packages</h2>

                    <p>
                        View package images, facilities, music options and current pricing.
                    </p>
                </div>

                <a
                    class="manager-package-view-all"
                    href="<?= e(url('/booking_manager/all_packages.php')) ?>"
                >
                    <i class="fa-solid fa-table-cells-large"></i>
                    View All Packages
                </a>

            </div>

            <?php if ($packages === []): ?>

                <div class="manager-package-empty">

                    <i class="fa-solid fa-gift"></i>

                    <h3>No active packages found</h3>

                    <p>
                        Packages activated by the Admin will appear here automatically.
                    </p>

                </div>

            <?php else: ?>

                <div class="manager-package-grid">

                    <?php foreach ($packages as $package): ?>
                        <?php
                        $packageId = (int) $package['id'];

                        $features = package_feature_lines(
                            $package['features'] ?? null
                        );

                        $musicOptions = [];

                        if ((int) ($package['basic_music'] ?? 0) === 1) {
                            $musicOptions[] = 'Basic Music';
                        }

                        if ((int) ($package['live_music'] ?? 0) === 1) {
                            $musicOptions[] = 'Live Music';
                        }

                        $musicText = $musicOptions !== []
                            ? implode(' and ', $musicOptions)
                            : 'No music selected';

                        $mainImage = package_image_url(
                            $package['main_image'] ?? null
                        );

                        $previewImages = [
                            [
                                'url' => $mainImage,
                                'is_main' => true,
                            ],
                        ];

                        foreach (
                            ['image_one', 'image_two', 'image_three']
                            as $imageColumn
                        ) {
                            $imagePath = trim(
                                (string) ($package[$imageColumn] ?? '')
                            );

                            if ($imagePath === '') {
                                continue;
                            }

                            $previewImages[] = [
                                'url' => package_image_url($imagePath),
                                'is_main' => false,
                            ];
                        }

                        $shortDescription = trim(
                            (string) ($package['short_description'] ?? '')
                        );

                        $fullDescription = trim(
                            (string) ($package['description'] ?? '')
                        );

                        if ($fullDescription === '') {
                            $fullDescription = $shortDescription;
                        }

                        if ($fullDescription === '') {
                            $fullDescription = 'Complete wedding-event package.';
                        }

                        $cateringMenu = trim(
                            (string) ($package['catering_menu'] ?? '')
                        );

                        $decorationType = trim(
                            (string) ($package['decoration_type'] ?? '')
                        );

                        $featureText = $features !== []
                            ? implode('||', $features)
                            : '';

                        $imageUrls = array_map(
                            static fn (array $image): string => (string) $image['url'],
                            $previewImages
                        );
                        ?>

                        <article class="manager-package-card">

                            <div class="manager-package-main-image-wrap">

                                <img
                                    class="manager-package-main-image"
                                    id="managerPackageCardMainImage<?= e((string) $packageId) ?>"
                                    src="<?= e($mainImage) ?>"
                                    alt="<?= e((string) $package['name']) ?>"
                                >

                                <span class="manager-package-main-badge">
                                    <i class="fa-regular fa-image"></i>
                                    Main Photo
                                </span>

                            </div>

                            <div class="manager-package-card-body">

                                <span class="manager-package-status">
                                    Available
                                </span>

                                <div class="manager-package-card-head">

                                    <h3>
                                        <?= e((string) $package['name']) ?>
                                    </h3>

                                    <strong class="manager-package-price">
                                        <?= e(
                                            format_package_price(
                                                (float) $package['price']
                                            )
                                        ) ?>
                                    </strong>

                                </div>

                                <p class="manager-package-description">
                                    <?= e(
                                        $shortDescription !== ''
                                            ? $shortDescription
                                            : package_card_description($package)
                                    ) ?>
                                </p>

                                <div class="manager-package-thumbnails">

                                    <?php foreach ($previewImages as $index => $previewImage): ?>

                                        <button
                                            class="manager-package-thumbnail <?= $index === 0
                                                ? 'active'
                                                : '' ?>"
                                            type="button"
                                            data-card-target="managerPackageCardMainImage<?= e((string) $packageId) ?>"
                                            data-card-image="<?= e((string) $previewImage['url']) ?>"
                                            data-card-is-main="<?= $previewImage['is_main']
                                                ? 'true'
                                                : 'false' ?>"
                                            aria-label="<?= $previewImage['is_main']
                                                ? 'Show original main package photo'
                                                : 'Show package gallery photo '
                                                    . e((string) $index) ?>"
                                        >
                                            <img
                                                src="<?= e((string) $previewImage['url']) ?>"
                                                alt="<?= $previewImage['is_main']
                                                    ? 'Original main package photo'
                                                    : 'Package gallery photo '
                                                        . e((string) $index) ?>"
                                            >

                                            <?php if ($previewImage['is_main']): ?>
                                                <span>Main</span>
                                            <?php endif; ?>
                                        </button>

                                    <?php endforeach; ?>

                                </div>

                                <ul class="manager-package-details">

                                    <li>
                                        <i class="fa-solid fa-users"></i>

                                        Up to
                                        <?= e(
                                            number_format(
                                                (int) (
                                                    $package['guest_capacity']
                                                    ?? 0
                                                )
                                            )
                                        ) ?>
                                        guests
                                    </li>

                                    <li>
                                        <i class="fa-solid fa-wand-magic-sparkles"></i>

                                        <?= e(
                                            $decorationType !== ''
                                                ? $decorationType
                                                : 'Wedding decoration included'
                                        ) ?>
                                    </li>

                                    <li>
                                        <i class="fa-solid fa-music"></i>
                                        <?= e($musicText) ?>
                                    </li>

                                </ul>

                                <div class="manager-package-actions">

                                    <button
                                        class="manager-package-details-button"
                                        type="button"
                                        data-package-details
                                        data-id="<?= e((string) $packageId) ?>"
                                        data-name="<?= e((string) $package['name']) ?>"
                                        data-price="<?= e(
                                            format_package_price(
                                                (float) $package['price']
                                            )
                                        ) ?>"
                                        data-description="<?= e($fullDescription) ?>"
                                        data-decoration="<?= e(
                                            $decorationType !== ''
                                                ? $decorationType
                                                : 'Not specified'
                                        ) ?>"
                                        data-guests="<?= e(
                                            number_format(
                                                (int) (
                                                    $package['guest_capacity']
                                                    ?? 0
                                                )
                                            )
                                        ) ?>"
                                        data-catering="<?= e(
                                            $cateringMenu !== ''
                                                ? $cateringMenu
                                                : 'Not specified'
                                        ) ?>"
                                        data-music="<?= e($musicText) ?>"
                                        data-features="<?= e($featureText) ?>"
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
                                                '/booking_manager/booking.php?package_id='
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
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <div
        class="manager-package-modal"
        id="managerPackageModal"
        aria-hidden="true"
    >

        <div class="manager-package-modal-content">

            <button
                class="manager-package-modal-close"
                id="managerPackageModalClose"
                type="button"
                aria-label="Close package details"
            >
                &times;
            </button>

            <div class="manager-package-modal-grid">

                <div>

                    <div class="manager-package-modal-image-wrap">

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
                            <i class="fa-regular fa-image"></i>
                            Main Photo
                        </span>

                    </div>

                    <div
                        class="manager-package-modal-thumbnails"
                        id="managerPackageModalThumbnails"
                    ></div>

                </div>

                <div class="manager-package-modal-info">

                    <h2 id="managerPackageModalName"></h2>

                    <div
                        class="manager-package-modal-price"
                        id="managerPackageModalPrice"
                    ></div>

                    <p
                        class="manager-package-modal-description"
                        id="managerPackageModalDescription"
                    ></p>

                    <div class="manager-package-information-row">
                        <strong>Decoration:</strong>
                        <span id="managerPackageModalDecoration"></span>
                    </div>

                    <div class="manager-package-information-row">
                        <strong>Guest capacity:</strong>
                        <span id="managerPackageModalGuests"></span>
                    </div>

                    <div class="manager-package-information-row">
                        <strong>Music:</strong>
                        <span id="managerPackageModalMusic"></span>
                    </div>

                    <div class="manager-package-information-row">
                        <strong>Catering menu:</strong>
                        <span id="managerPackageModalCatering"></span>
                    </div>

                    <div class="manager-package-feature-box">

                        <h3>Additional Features</h3>

                        <ul id="managerPackageModalFeatures"></ul>

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
            bookingSidebarOverlay?.classList.remove("open");
        }

        bookingMenuButton?.addEventListener(
            "click",
            function () {
                bookingSidebar?.classList.toggle("open");
                bookingSidebarOverlay?.classList.toggle("open");
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
                    ".manager-package-thumbnail"
                );

                if (!thumbnail) {
                    return;
                }

                const mainImage = document.getElementById(
                    thumbnail.dataset.cardTarget || ""
                );

                if (
                    !mainImage
                    || !thumbnail.dataset.cardImage
                ) {
                    return;
                }

                mainImage.src =
                    thumbnail.dataset.cardImage;

                const card = thumbnail.closest(
                    ".manager-package-card"
                );

                card
                    ?.querySelectorAll(
                        ".manager-package-thumbnail"
                    )
                    .forEach(function (item) {
                        item.classList.remove("active");
                    });

                thumbnail.classList.add("active");

                const badge = card?.querySelector(
                    ".manager-package-main-badge"
                );

                badge?.classList.toggle(
                    "hidden",
                    thumbnail.dataset.cardIsMain !== "true"
                );
            }
        );

        const packageModal = document.getElementById(
            "managerPackageModal"
        );

        const packageModalClose = document.getElementById(
            "managerPackageModalClose"
        );

        const packageMainImage = document.getElementById(
            "managerPackageModalMainImage"
        );

        const packageMainBadge = document.getElementById(
            "managerPackageModalMainBadge"
        );

        const packageThumbnails = document.getElementById(
            "managerPackageModalThumbnails"
        );

        const packageName = document.getElementById(
            "managerPackageModalName"
        );

        const packagePrice = document.getElementById(
            "managerPackageModalPrice"
        );

        const packageDescription = document.getElementById(
            "managerPackageModalDescription"
        );

        const packageDecoration = document.getElementById(
            "managerPackageModalDecoration"
        );

        const packageGuests = document.getElementById(
            "managerPackageModalGuests"
        );

        const packageMusic = document.getElementById(
            "managerPackageModalMusic"
        );

        const packageCatering = document.getElementById(
            "managerPackageModalCatering"
        );

        const packageFeatures = document.getElementById(
            "managerPackageModalFeatures"
        );

        const packageBookButton = document.getElementById(
            "managerPackageModalBook"
        );

        function renderModalImages(images) {
            packageThumbnails.innerHTML = "";

            images.forEach(function (imageUrl, index) {
                const button = document.createElement("button");
                const image = document.createElement("img");

                button.type = "button";
                button.className =
                    "manager-package-modal-thumbnail";

                if (index === 0) {
                    button.classList.add("active");
                }

                image.src = imageUrl;

                image.alt = index === 0
                    ? "Original main package photo"
                    : "Package gallery photo " + index;

                button.appendChild(image);

                if (index === 0) {
                    const label =
                        document.createElement("span");

                    label.textContent = "Main";

                    button.appendChild(label);
                }

                button.addEventListener(
                    "click",
                    function () {
                        packageMainImage.src = imageUrl;

                        packageThumbnails
                            .querySelectorAll(
                                ".manager-package-modal-thumbnail"
                            )
                            .forEach(function (item) {
                                item.classList.remove("active");
                            });

                        button.classList.add("active");

                        packageMainBadge.classList.toggle(
                            "hidden",
                            index !== 0
                        );
                    }
                );

                packageThumbnails.appendChild(button);
            });
        }

        document
            .querySelectorAll("[data-package-details]")
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        let images = [];

                        try {
                            images = JSON.parse(
                                button.dataset.images || "[]"
                            );
                        } catch (error) {
                            images = [];
                        }

                        packageName.textContent =
                            button.dataset.name || "Package";

                        packagePrice.textContent =
                            button.dataset.price || "";

                        packageDescription.textContent =
                            button.dataset.description || "";

                        packageDecoration.textContent =
                            button.dataset.decoration
                            || "Not specified";

                        packageGuests.textContent =
                            (button.dataset.guests || "0")
                            + " guests";

                        packageMusic.textContent =
                            button.dataset.music
                            || "Not specified";

                        packageCatering.textContent =
                            button.dataset.catering
                            || "Not specified";

                        packageBookButton.href =
                            "<?= e(
                                url(
                                    '/booking_manager/booking.php?package_id='
                                )
                            ) ?>"
                            + (button.dataset.id || "");

                        packageFeatures.innerHTML = "";

                        const features =
                            button.dataset.features
                                ? button.dataset.features.split("||")
                                : [];

                        if (features.length === 0) {
                            const item =
                                document.createElement("li");

                            item.textContent =
                                "No additional features listed.";

                            packageFeatures.appendChild(item);
                        } else {
                            features.forEach(function (feature) {
                                const item =
                                    document.createElement("li");

                                const icon =
                                    document.createElement("i");

                                const text =
                                    document.createElement("span");

                                icon.className =
                                    "fa-solid fa-check";

                                text.textContent = feature;

                                item.append(icon, text);

                                packageFeatures.appendChild(item);
                            });
                        }

                        if (images.length > 0) {
                            packageMainImage.src = images[0];

                            packageMainBadge.classList.remove(
                                "hidden"
                            );

                            renderModalImages(images);
                        }

                        packageModal.classList.add("open");

                        packageModal.setAttribute(
                            "aria-hidden",
                            "false"
                        );

                        document.body.classList.add(
                            "manager-package-modal-open"
                        );
                    }
                );
            });

        function closePackageModal() {
            packageModal.classList.remove("open");

            packageModal.setAttribute(
                "aria-hidden",
                "true"
            );

            document.body.classList.remove(
                "manager-package-modal-open"
            );
        }

        packageModalClose?.addEventListener(
            "click",
            closePackageModal
        );

        packageModal?.addEventListener(
            "click",
            function (event) {
                if (event.target === packageModal) {
                    closePackageModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (event.key === "Escape") {
                    closePackageModal();
                }
            }
        );
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>