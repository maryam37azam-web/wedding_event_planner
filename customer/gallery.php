<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/gallery_display_helpers.php';

require_role('customer');

$connection = db();
$customerId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Customer profile
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

$customerName = trim(
    (string) (
        $customer['full_name']
        ?? ''
    )
);

if ($customerName === '') {
    $customerName = 'Customer';
}

$customerAbout = trim(
    (string) (
        $customer['about']
        ?? ''
    )
);

if ($customerAbout === '') {
    $customerAbout =
        'Customer Account';
}

$customerImage = !empty(
    $customer['profile_image']
)
    ? gallery_display_image_url(
        (string) $customer['profile_image']
    )
    : url('/assets/icons/icon-192.png');

/*
|--------------------------------------------------------------------------
| Active customer gallery records
|--------------------------------------------------------------------------
*/

$allGalleryRows =
    gallery_display_rows(
        $connection
    );

$activeGalleryRows = array_values(
    array_filter(
        $allGalleryRows,
        static fn (
            array $galleryRow
        ): bool =>
            (bool) (
                $galleryRow['active']
                ?? false
            )
    )
);

$totalImages = count(
    $activeGalleryRows
);

$eventTypes = [];

foreach ($activeGalleryRows as $galleryRow) {
    $eventType = trim(
        (string) (
            $galleryRow['event_type']
            ?? ''
        )
    );

    if ($eventType !== '') {
        $eventTypes[] =
            mb_strtolower(
                $eventType
            );
    }
}

$totalEventTypes = count(
    array_unique(
        $eventTypes
    )
);

$latestUpload = $activeGalleryRows !== []
    ? gallery_display_date(
        (string) (
            $activeGalleryRows[0]['created_at']
            ?? ''
        ),
        'd M Y'
    )
    : 'No uploads';

$latestGalleryRows = array_slice(
    $activeGalleryRows,
    0,
    4
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
        Wedding Gallery | <?= e(APP_NAME) ?>
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
            url('/assets/css/customer_gallery.css')
        ) ?>"
    >
</head>

<body class="customer-gallery-page">

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
                <?= e($customerName) ?>
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

            <a href="<?= e(
                url('/customer/venues.php')
            ) ?>">
                <i class="fa-solid fa-hotel"></i>
                Browse Venues
            </a>

            <a
                class="active"
                href="<?= e(
                    url('/customer/gallery.php')
                ) ?>"
            >
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

    <main class="customer-main customer-gallery-main">

        <header class="customer-gallery-topbar">

            <div class="customer-gallery-topbar-left">

                <button
                    class="customer-menu-button"
                    id="customerMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>

                    <h1>Wedding Gallery</h1>

                    <p>
                        Explore beautiful wedding setups, decorations and completed events.
                    </p>

                </div>

            </div>

            <div class="customer-gallery-topbar-right">

                <div class="customer-gallery-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="customer-gallery-public-link"
                    href="<?= e(url('/index.php')) ?>"
                    aria-label="Open public website"
                >
                    <i class="fa-solid fa-globe"></i>
                </a>

                <a
                    class="customer-gallery-topbar-user"
                    href="<?= e(
                        url('/customer/profile.php')
                    ) ?>"
                >

                    <div>

                        <strong>
                            <?= e($customerName) ?>
                        </strong>

                        <span title="<?= e(
                            $customerAbout
                        ) ?>">
                            <?= e($customerAbout) ?>
                        </span>

                    </div>

                    <img
                        src="<?= e($customerImage) ?>"
                        alt="Customer profile"
                    >

                </a>

            </div>

        </header>

        <section class="customer-gallery-summary">

            <a
                class="customer-gallery-summary-card customer-gallery-summary-link"
                href="<?= e(
                    url('/customer/all_gallery.php')
                ) ?>"
            >

                <div class="customer-gallery-summary-icon pink">
                    <i class="fa-solid fa-images"></i>
                </div>

                <div>

                    <h4>Total Images</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $totalImages
                            )
                        ) ?>
                    </h2>

                    <span>Click to show all</span>

                </div>

            </a>

            <article class="customer-gallery-summary-card">

                <div class="customer-gallery-summary-icon green">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>

                    <h4>Active Images</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $totalImages
                            )
                        ) ?>
                    </h2>

                    <span>Visible in gallery</span>

                </div>

            </article>

            <article class="customer-gallery-summary-card">

                <div class="customer-gallery-summary-icon blue">
                    <i class="fa-solid fa-heart"></i>
                </div>

                <div>

                    <h4>Event Categories</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $totalEventTypes
                            )
                        ) ?>
                    </h2>

                    <span>
                        Latest:
                        <?= e($latestUpload) ?>
                    </span>

                </div>

            </article>

        </section>

        <section class="customer-gallery-section">

            <div class="customer-gallery-section-heading">

                <div>

                    <h2>Latest Gallery Images</h2>

                    <p>
                        View the latest active wedding-event gallery images.
                    </p>

                </div>

                <a
                    class="customer-gallery-view-all"
                    href="<?= e(
                        url('/customer/all_gallery.php')
                    ) ?>"
                >
                    <i class="fa-solid fa-table-cells-large"></i>
                    View All Images
                </a>

            </div>

            <?php if (
                $latestGalleryRows === []
            ): ?>

                <div class="customer-gallery-empty">

                    <i class="fa-regular fa-images"></i>

                    <h3>No gallery images available</h3>

                    <p>
                        Active wedding-event images will appear here automatically.
                    </p>

                </div>

            <?php else: ?>

                <div class="customer-gallery-grid">

                    <?php foreach (
                        $latestGalleryRows as
                        $galleryRow
                    ): ?>
                        <?php
                        $galleryImages =
                            gallery_display_images(
                                $galleryRow
                            );

                        $galleryTitle =
                            (string) (
                                $galleryRow['title']
                                ?? 'Wedding Gallery Image'
                            );

                        $eventType =
                            (string) (
                                $galleryRow['event_type']
                                ?? 'Wedding Event'
                            );

                        $description =
                            trim(
                                (string) (
                                    $galleryRow['description']
                                    ?? ''
                                )
                            );

                        if ($description === '') {
                            $description =
                                'Wedding-event gallery image.';
                        }

                        $createdDate =
                            gallery_display_date(
                                (string) (
                                    $galleryRow['created_at']
                                    ?? ''
                                )
                            );
                        ?>

                        <article class="customer-gallery-card">

                            <button
                                class="customer-gallery-card-image"
                                type="button"
                                data-gallery-open
                                data-gallery-title="<?= e(
                                    $galleryTitle
                                ) ?>"
                                data-gallery-event="<?= e(
                                    $eventType
                                ) ?>"
                                data-gallery-description="<?= e(
                                    $description
                                ) ?>"
                                data-gallery-date="<?= e(
                                    $createdDate
                                ) ?>"
                                data-gallery-images="<?= e(
                                    (string) json_encode(
                                        $galleryImages,
                                        JSON_UNESCAPED_SLASHES
                                        | JSON_UNESCAPED_UNICODE
                                    )
                                ) ?>"
                            >

                                <img
                                    src="<?= e(
                                        $galleryImages[0]
                                    ) ?>"
                                    alt="<?= e(
                                        $galleryTitle
                                    ) ?>"
                                >

                                <span class="customer-gallery-active-badge">
                                    Active
                                </span>

                                <span class="customer-gallery-photo-count">
                                    <i class="fa-regular fa-images"></i>

                                    <?= e(
                                        number_format(
                                            count(
                                                $galleryImages
                                            )
                                        )
                                    ) ?>

                                    Photo<?= count(
                                        $galleryImages
                                    ) === 1
                                        ? ''
                                        : 's' ?>
                                </span>

                            </button>

                            <div class="customer-gallery-card-body">

                                <h3>
                                    <?= e($galleryTitle) ?>
                                </h3>

                                <div class="customer-gallery-event-type">

                                    <i class="fa-solid fa-heart"></i>

                                    <span>
                                        <?= e($eventType) ?>
                                    </span>

                                </div>

                                <p>
                                    <?= e($description) ?>
                                </p>

                                <div class="customer-gallery-card-footer">

                                    <span>
                                        <i class="fa-regular fa-calendar"></i>
                                        <?= e($createdDate) ?>
                                    </span>

                                    <span>
                                        <i class="fa-regular fa-user"></i>
                                        Event Manager
                                    </span>

                                </div>

                                <button
                                    class="customer-gallery-view-button"
                                    type="button"
                                    data-gallery-open
                                    data-gallery-title="<?= e(
                                        $galleryTitle
                                    ) ?>"
                                    data-gallery-event="<?= e(
                                        $eventType
                                    ) ?>"
                                    data-gallery-description="<?= e(
                                        $description
                                    ) ?>"
                                    data-gallery-date="<?= e(
                                        $createdDate
                                    ) ?>"
                                    data-gallery-images="<?= e(
                                        (string) json_encode(
                                            $galleryImages,
                                            JSON_UNESCAPED_SLASHES
                                            | JSON_UNESCAPED_UNICODE
                                        )
                                    ) ?>"
                                >
                                    <i class="fa-regular fa-eye"></i>
                                    View Images
                                </button>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <footer class="customer-gallery-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <div
        class="customer-gallery-modal"
        id="customerGalleryModal"
        aria-hidden="true"
    >

        <div class="customer-gallery-modal-content">

            <button
                class="customer-gallery-modal-close"
                id="customerGalleryModalClose"
                type="button"
                aria-label="Close gallery preview"
            >
                &times;
            </button>

            <div class="customer-gallery-modal-layout">

                <div>

                    <img
                        id="customerGalleryModalMainImage"
                        src=""
                        alt="Gallery preview"
                    >

                    <div id="customerGalleryModalThumbnails"></div>

                </div>

                <div class="customer-gallery-modal-information">

                    <span id="customerGalleryModalEvent"></span>

                    <h2 id="customerGalleryModalTitle"></h2>

                    <p id="customerGalleryModalDescription"></p>

                    <div>
                        <i class="fa-regular fa-calendar"></i>
                        <span id="customerGalleryModalDate"></span>
                    </div>

                    <div>
                        <i class="fa-regular fa-user"></i>
                        <span>Uploaded by Event Manager</span>
                    </div>

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
            function () {
                customerSidebar?.classList.remove(
                    "open"
                );

                customerSidebarOverlay?.classList.remove(
                    "open"
                );
            }
        );
    </script>

    <script
        src="<?= e(
            url(
                '/assets/js/customer_gallery_view.js'
            )
        ) ?>"
    ></script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>