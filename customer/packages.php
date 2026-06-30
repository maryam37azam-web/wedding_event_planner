<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/package_helpers.php';

require_role('customer');

$connection = db();
$customerId = (int) $_SESSION['user_id'];

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
| Package summary
|--------------------------------------------------------------------------
*/

$activePackageRows = $connection
    ->query(
        "SELECT *
         FROM packages
         WHERE status = 'active'
         ORDER BY created_at DESC, id DESC"
    )
    ->fetchAll();

$availablePackages = count(
    $activePackageRows
);

$startingPrice = 0.0;
$maximumCapacity = 0;

if ($activePackageRows !== []) {
    $packagePrices = array_values(
        array_filter(
            array_map(
                static fn (array $package): float =>
                    (float) ($package['price'] ?? 0),
                $activePackageRows
            ),
            static fn (float $price): bool =>
                $price > 0
        )
    );

    if ($packagePrices !== []) {
        $startingPrice = min(
            $packagePrices
        );
    }

    $maximumCapacity = max(
        array_map(
            static fn (array $package): int =>
                (int) (
                    $package['guest_capacity']
                    ?? 0
                ),
            $activePackageRows
        )
    );
}

$packages = array_slice(
    $activePackageRows,
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
        Browse Packages | <?= e(APP_NAME) ?>
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
            url('/assets/css/customer_packages.css')
        ) ?>"
    >
</head>

<body class="customer-package-page">

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

            <a
                class="active"
                href="<?= e(
                    url('/customer/packages.php')
                ) ?>"
            >
                <i class="fa-solid fa-gift"></i>
                Browse Packages
            </a>

            <a href="<?= e(
                url('/customer/venues.php')
            ) ?>">
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

        <header class="customer-package-topbar">

            <div class="customer-package-topbar-left">

                <button
                    class="customer-menu-button"
                    id="customerMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>

                    <h1>Wedding Packages</h1>

                    <p>
                        Compare active packages and select the right option for your event.
                    </p>

                </div>

            </div>

            <div class="customer-package-topbar-right">

                <div class="customer-package-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="customer-package-public-link"
                    href="<?= e(url('/index.php')) ?>"
                    aria-label="Open public website"
                >
                    <i class="fa-solid fa-globe"></i>
                </a>

                <a href="<?= e(
                    url('/customer/profile.php')
                ) ?>">
                    <img
                        class="customer-package-profile-image"
                        src="<?= e($customerImage) ?>"
                        alt="Customer profile"
                    >
                </a>

            </div>

        </header>

        <section class="customer-package-summary">

            <article class="customer-package-summary-card">

                <div class="customer-package-summary-icon">
                    <i class="fa-solid fa-gift"></i>
                </div>

                <div>
                    <h4>Available Packages</h4>

                    <h2>
                        <?= e(
                            (string) $availablePackages
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="customer-package-summary-card">

                <div class="customer-package-summary-icon">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>

                <div>
                    <h4>Starting Price</h4>

                    <h2>
                        <?= e(
                            format_package_price(
                                $startingPrice
                            )
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="customer-package-summary-card">

                <div class="customer-package-summary-icon">
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

        <section class="customer-package-section">

            <div class="customer-package-section-heading">

                <div>

                    <h2>
                        Available Wedding Packages
                    </h2>

                    <p>
                        View package images, pricing, decoration, catering, music and included features.
                    </p>

                </div>

                <a
                    class="customer-package-view-all"
                    href="<?= e(
                        url('/customer/all_packages.php')
                    ) ?>"
                >
                    <i class="fa-solid fa-table-cells-large"></i>
                    View All Packages
                </a>

            </div>

            <?php if ($packages === []): ?>

                <div class="customer-package-empty">

                    <i class="fa-solid fa-gift"></i>

                    <h3>No active packages found</h3>

                    <p>
                        Packages activated by the Admin will appear here automatically.
                    </p>

                </div>

            <?php else: ?>

                <div class="customer-package-grid">

                    <?php foreach ($packages as $package): ?>
                        <?php
                        $packageId =
                            (int) $package['id'];

                        $packageName = trim(
                            (string) (
                                $package['name']
                                ?? ''
                            )
                        );

                        if ($packageName === '') {
                            $packageName =
                                'Untitled Package';
                        }

                        $mainImage =
                            package_image_url(
                                $package['main_image']
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
                                    $package[$imageColumn]
                                    ?? ''
                                )
                            );

                            if ($imagePath === '') {
                                continue;
                            }

                            $previewImages[] = [
                                'url' =>
                                    package_image_url(
                                        $imagePath
                                    ),

                                'is_main' => false,
                            ];
                        }

                        $shortDescription = trim(
                            (string) (
                                $package['short_description']
                                ?? ''
                            )
                        );

                        $fullDescription = trim(
                            (string) (
                                $package['description']
                                ?? ''
                            )
                        );

                        if ($fullDescription === '') {
                            $fullDescription =
                                $shortDescription;
                        }

                        if ($fullDescription === '') {
                            $fullDescription =
                                'Complete wedding-event package.';
                        }

                        if ($shortDescription === '') {
                            $shortDescription =
                                package_card_description(
                                    $package
                                );
                        }

                        $decorationType = trim(
                            (string) (
                                $package['decoration_type']
                                ?? ''
                            )
                        );

                        if ($decorationType === '') {
                            $decorationType =
                                'Wedding decoration included';
                        }

                        $cateringMenu = trim(
                            (string) (
                                $package['catering_menu']
                                ?? ''
                            )
                        );

                        if ($cateringMenu === '') {
                            $cateringMenu =
                                'Not specified';
                        }

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

                        $features =
                            package_feature_lines(
                                $package['features']
                                ?? null
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

                        <article class="customer-package-card">

                            <div class="customer-package-main-wrap">

                                <img
                                    class="customer-package-main-image"
                                    id="customerPackageMain<?= e(
                                        (string) $packageId
                                    ) ?>"
                                    src="<?= e($mainImage) ?>"
                                    alt="<?= e($packageName) ?>"
                                >

                                <span class="customer-package-main-badge">
                                    <i class="fa-regular fa-image"></i>
                                    Main Photo
                                </span>

                            </div>

                            <div class="customer-package-card-body">

                                <span class="customer-package-status">
                                    Available
                                </span>

                                <div class="customer-package-title-row">

                                    <h3>
                                        <?= e($packageName) ?>
                                    </h3>

                                    <strong>
                                        <?= e(
                                            format_package_price(
                                                (float) (
                                                    $package['price']
                                                    ?? 0
                                                )
                                            )
                                        ) ?>
                                    </strong>

                                </div>

                                <p class="customer-package-description">
                                    <?= e($shortDescription) ?>
                                </p>

                                <div class="customer-package-thumbnails">

                                    <?php foreach (
                                        $previewImages as
                                        $index => $previewImage
                                    ): ?>

                                        <button
                                            class="customer-package-thumbnail <?= $index === 0
                                                ? 'active'
                                                : '' ?>"
                                            type="button"
                                            data-package-target="customerPackageMain<?= e(
                                                (string) $packageId
                                            ) ?>"
                                            data-package-image="<?= e(
                                                (string) $previewImage['url']
                                            ) ?>"
                                            data-package-is-main="<?= $previewImage['is_main']
                                                ? 'true'
                                                : 'false' ?>"
                                            aria-label="<?= $previewImage['is_main']
                                                ? 'Show original main package photo'
                                                : 'Show package gallery photo '
                                                    . e((string) $index) ?>"
                                        >

                                            <img
                                                src="<?= e(
                                                    (string) $previewImage['url']
                                                ) ?>"
                                                alt="<?= $previewImage['is_main']
                                                    ? 'Original main package photo'
                                                    : 'Package gallery photo '
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

                                <ul class="customer-package-details">

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
                                        <?= e($decorationType) ?>
                                    </li>

                                    <li>
                                        <i class="fa-solid fa-music"></i>
                                        <?= e($musicText) ?>
                                    </li>

                                </ul>

                                <div class="customer-package-actions">

                                    <button
                                        class="customer-package-details-button"
                                        type="button"
                                        data-package-details
                                        data-id="<?= e(
                                            (string) $packageId
                                        ) ?>"
                                        data-name="<?= e($packageName) ?>"
                                        data-price="<?= e(
                                            format_package_price(
                                                (float) (
                                                    $package['price']
                                                    ?? 0
                                                )
                                            )
                                        ) ?>"
                                        data-description="<?= e(
                                            $fullDescription
                                        ) ?>"
                                        data-decoration="<?= e(
                                            $decorationType
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
                                            $cateringMenu
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
                                        class="customer-package-book-button"
                                        href="<?= e(
                                            url(
                                                '/customer/booking.php?package_id='
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

        <footer class="customer-package-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <div
        class="customer-package-modal"
        id="customerPackageModal"
        aria-hidden="true"
    >

        <div class="customer-package-modal-content">

            <button
                class="customer-package-modal-close"
                id="customerPackageModalClose"
                type="button"
                aria-label="Close package details"
            >
                &times;
            </button>

            <div class="customer-package-modal-grid">

                <div>

                    <div class="customer-package-modal-image-wrap">

                        <img
                            id="customerPackageModalMainImage"
                            src=""
                            alt="Package image"
                        >

                        <span id="customerPackageModalMainBadge">
                            <i class="fa-regular fa-image"></i>
                            Main Photo
                        </span>

                    </div>

                    <div id="customerPackageModalThumbnails"></div>

                </div>

                <div class="customer-package-modal-info">

                    <h2 id="customerPackageModalName"></h2>

                    <div id="customerPackageModalPrice"></div>

                    <p id="customerPackageModalDescription"></p>

                    <div class="customer-package-information-row">
                        <strong>Decoration:</strong>
                        <span id="customerPackageModalDecoration"></span>
                    </div>

                    <div class="customer-package-information-row">
                        <strong>Guest capacity:</strong>
                        <span id="customerPackageModalGuests"></span>
                    </div>

                    <div class="customer-package-information-row">
                        <strong>Music:</strong>
                        <span id="customerPackageModalMusic"></span>
                    </div>

                    <div class="customer-package-information-row">
                        <strong>Catering menu:</strong>
                        <span id="customerPackageModalCatering"></span>
                    </div>

                    <div class="customer-package-feature-box">

                        <h3>Additional Features</h3>

                        <ul id="customerPackageModalFeatures"></ul>

                    </div>

                    <a
                        id="customerPackageModalBook"
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
                        ".customer-package-thumbnail"
                    );

                if (!thumbnail) {
                    return;
                }

                const mainImage =
                    document.getElementById(
                        thumbnail.dataset.packageTarget
                        || ""
                    );

                if (
                    !mainImage
                    || !thumbnail.dataset.packageImage
                ) {
                    return;
                }

                mainImage.src =
                    thumbnail.dataset.packageImage;

                const card =
                    thumbnail.closest(
                        ".customer-package-card"
                    );

                card
                    ?.querySelectorAll(
                        ".customer-package-thumbnail"
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
                        ".customer-package-main-badge"
                    );

                badge?.classList.toggle(
                    "hidden",
                    thumbnail.dataset.packageIsMain
                        !== "true"
                );
            }
        );

        const packageModal =
            document.getElementById(
                "customerPackageModal"
            );

        const packageModalClose =
            document.getElementById(
                "customerPackageModalClose"
            );

        const modalMainImage =
            document.getElementById(
                "customerPackageModalMainImage"
            );

        const modalMainBadge =
            document.getElementById(
                "customerPackageModalMainBadge"
            );

        const modalThumbnails =
            document.getElementById(
                "customerPackageModalThumbnails"
            );

        const modalName =
            document.getElementById(
                "customerPackageModalName"
            );

        const modalPrice =
            document.getElementById(
                "customerPackageModalPrice"
            );

        const modalDescription =
            document.getElementById(
                "customerPackageModalDescription"
            );

        const modalDecoration =
            document.getElementById(
                "customerPackageModalDecoration"
            );

        const modalGuests =
            document.getElementById(
                "customerPackageModalGuests"
            );

        const modalMusic =
            document.getElementById(
                "customerPackageModalMusic"
            );

        const modalCatering =
            document.getElementById(
                "customerPackageModalCatering"
            );

        const modalFeatures =
            document.getElementById(
                "customerPackageModalFeatures"
            );

        const modalBook =
            document.getElementById(
                "customerPackageModalBook"
            );

        function renderModalImages(images) {
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
                        "customer-package-modal-thumbnail";

                    if (index === 0) {
                        button.classList.add(
                            "active"
                        );
                    }

                    image.src = imageUrl;

                    image.alt = index === 0
                        ? "Original main package photo"
                        : "Package gallery photo "
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
                                    ".customer-package-modal-thumbnail"
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
                "[data-package-details]"
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
                            || "Package";

                        modalPrice.textContent =
                            button.dataset.price
                            || "";

                        modalDescription.textContent =
                            button.dataset.description
                            || "";

                        modalDecoration.textContent =
                            button.dataset.decoration
                            || "Not specified";

                        modalGuests.textContent =
                            (
                                button.dataset.guests
                                || "0"
                            )
                            + " guests";

                        modalMusic.textContent =
                            button.dataset.music
                            || "Not specified";

                        modalCatering.textContent =
                            button.dataset.catering
                            || "Not specified";

                        modalBook.href =
                            "<?= e(
                                url(
                                    '/customer/booking.php?package_id='
                                )
                            ) ?>"
                            + (
                                button.dataset.id
                                || ""
                            );

                        modalFeatures.innerHTML = "";

                        const features =
                            button.dataset.features
                                ? button.dataset.features.split(
                                    "||"
                                )
                                : [];

                        if (features.length === 0) {
                            const item =
                                document.createElement(
                                    "li"
                                );

                            item.textContent =
                                "No additional features listed.";

                            modalFeatures.appendChild(
                                item
                            );
                        } else {
                            features.forEach(
                                function (feature) {
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
                                        feature;

                                    item.append(
                                        icon,
                                        text
                                    );

                                    modalFeatures.appendChild(
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

                            renderModalImages(
                                images
                            );
                        }

                        packageModal.classList.add(
                            "open"
                        );

                        packageModal.setAttribute(
                            "aria-hidden",
                            "false"
                        );

                        document.body.classList.add(
                            "customer-package-modal-open"
                        );
                    }
                );
            });

        function closePackageModal() {
            packageModal.classList.remove(
                "open"
            );

            packageModal.setAttribute(
                "aria-hidden",
                "true"
            );

            document.body.classList.remove(
                "customer-package-modal-open"
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