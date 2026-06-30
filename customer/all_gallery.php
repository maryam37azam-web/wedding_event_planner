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
| Search, filter and pagination
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) ($_GET['q'] ?? '')
);

$eventFilter = trim(
    (string) (
        $_GET['event_type']
        ?? 'all'
    )
);

$page = max(
    1,
    (int) ($_GET['page'] ?? 1)
);

$perPage = 8;

if (mb_strlen($search) > 100) {
    $search = mb_substr(
        $search,
        0,
        100
    );
}

$activeGalleryRows = array_values(
    array_filter(
        gallery_display_rows(
            $connection
        ),
        static fn (
            array $galleryRow
        ): bool =>
            (bool) (
                $galleryRow['active']
                ?? false
            )
    )
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
        $eventTypes[$eventType] =
            $eventType;
    }
}

natcasesort($eventTypes);

$filteredGalleryRows =
    $activeGalleryRows;

if ($search !== '') {
    $normalizedSearch =
        mb_strtolower(
            $search
        );

    $filteredGalleryRows = array_values(
        array_filter(
            $filteredGalleryRows,
            static function (
                array $galleryRow
            ) use (
                $normalizedSearch
            ): bool {
                $searchableText =
                    mb_strtolower(
                        implode(
                            ' ',
                            [
                                (string) (
                                    $galleryRow['title']
                                    ?? ''
                                ),

                                (string) (
                                    $galleryRow['event_type']
                                    ?? ''
                                ),

                                (string) (
                                    $galleryRow['description']
                                    ?? ''
                                ),
                            ]
                        )
                    );

                return str_contains(
                    $searchableText,
                    $normalizedSearch
                );
            }
        )
    );
}

if (
    $eventFilter !== ''
    && strtolower(
        $eventFilter
    ) !== 'all'
) {
    $filteredGalleryRows = array_values(
        array_filter(
            $filteredGalleryRows,
            static fn (
                array $galleryRow
            ): bool =>
                strcasecmp(
                    trim(
                        (string) (
                            $galleryRow['event_type']
                            ?? ''
                        )
                    ),
                    $eventFilter
                ) === 0
        )
    );
}

$totalResults = count(
    $filteredGalleryRows
);

$totalPages = max(
    1,
    (int) ceil(
        $totalResults / $perPage
    )
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset =
    ($page - 1) * $perPage;

$galleryRows = array_slice(
    $filteredGalleryRows,
    $offset,
    $perPage
);

$firstResult =
    $totalResults === 0
        ? 0
        : $offset + 1;

$lastResult = min(
    $offset + $perPage,
    $totalResults
);

$buildGalleryQuery = static function (
    array $changes = []
) use (
    $search,
    $eventFilter,
    &$page
): string {
    $queryParameters = [
        'q' => $search,
        'event_type' =>
            $eventFilter,
        'page' => $page,
    ];

    foreach ($changes as $key => $value) {
        $queryParameters[$key] =
            $value;
    }

    if (
        trim(
            (string) $queryParameters['q']
        ) === ''
    ) {
        unset(
            $queryParameters['q']
        );
    }

    if (
        strtolower(
            trim(
                (string) (
                    $queryParameters[
                        'event_type'
                    ] ?? ''
                )
            )
        ) === 'all'
        || trim(
            (string) (
                $queryParameters[
                    'event_type'
                ] ?? ''
            )
        ) === ''
    ) {
        unset(
            $queryParameters[
                'event_type'
            ]
        );
    }

    if (
        (int) (
            $queryParameters['page']
            ?? 1
        ) <= 1
    ) {
        unset(
            $queryParameters['page']
        );
    }

    return $queryParameters === []
        ? ''
        : '?'
            . http_build_query(
                $queryParameters
            );
};

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
        View All Gallery Images | <?= e(APP_NAME) ?>
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
                '/assets/css/customer_all_gallery.css'
            )
        ) ?>"
    >
</head>

<body class="customer-all-gallery-page">

    <header class="customer-all-gallery-header">

        <a
            class="customer-all-gallery-brand"
            href="<?= e(
                url('/customer/dashboard.php')
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

            <div>
                <strong>Wedding</strong>
                <span>Event Planner</span>
            </div>

        </a>

        <a
            class="customer-all-gallery-user"
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

    </header>

    <main class="customer-all-gallery-shell">

        <section class="customer-all-gallery-page-head">

            <div class="customer-all-gallery-heading-copy">

                <h1>
                    View All Gallery Images
                </h1>

                <p>
                    Search and view all active wedding-event gallery images.
                </p>

            </div>

            <div class="customer-all-gallery-heading-controls">

                <form
                    class="customer-all-gallery-search-form"
                    method="get"
                >

                    <div class="customer-all-gallery-search-box">

                        <input
                            type="search"
                            name="q"
                            value="<?= e($search) ?>"
                            placeholder="Search gallery images..."
                            aria-label="Search gallery images"
                        >

                        <button
                            type="submit"
                            aria-label="Search gallery images"
                        >
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>

                    </div>

                    <label class="customer-all-gallery-filter-box">

                        <i class="fa-solid fa-filter"></i>

                        <select
                            name="event_type"
                            aria-label="Filter by event type"
                            onchange="this.form.submit()"
                        >

                            <option value="all">
                                All Event Types
                            </option>

                            <?php foreach (
                                $eventTypes as
                                $eventType
                            ): ?>

                                <option
                                    value="<?= e(
                                        $eventType
                                    ) ?>"
                                    <?= strcasecmp(
                                        $eventFilter,
                                        $eventType
                                    ) === 0
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= e($eventType) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </label>

                    <?php if (
                        $search !== ''
                        || (
                            $eventFilter !== ''
                            && strtolower(
                                $eventFilter
                            ) !== 'all'
                        )
                    ): ?>

                        <a
                            class="customer-all-gallery-clear"
                            href="<?= e(
                                url(
                                    '/customer/all_gallery.php'
                                )
                            ) ?>"
                            title="Clear search and filter"
                            aria-label="Clear search and filter"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </a>

                    <?php endif; ?>

                </form>

                <a
                    class="customer-all-gallery-back"
                    href="<?= e(
                        url('/customer/gallery.php')
                    ) ?>"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Gallery
                </a>

            </div>

        </section>

        <?php if ($galleryRows === []): ?>

            <section class="customer-all-gallery-empty">

                <i class="fa-regular fa-images"></i>

                <h2>No gallery images found</h2>

                <p>
                    Try another search or clear the current event-type filter.
                </p>

                <a href="<?= e(
                    url(
                        '/customer/all_gallery.php'
                    )
                ) ?>">
                    Show All Images
                </a>

            </section>

        <?php else: ?>

            <section class="customer-all-gallery-grid">

                <?php foreach (
                    $galleryRows as
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

                    $description = trim(
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

                    <article class="customer-all-gallery-card">

                        <button
                            class="customer-all-gallery-image"
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

                            <span class="customer-all-gallery-active">
                                Active
                            </span>

                            <span class="customer-all-gallery-count">
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

                        <div class="customer-all-gallery-body">

                            <h2>
                                <?= e($galleryTitle) ?>
                            </h2>

                            <div class="customer-all-gallery-event">

                                <i class="fa-solid fa-heart"></i>

                                <span>
                                    <?= e($eventType) ?>
                                </span>

                            </div>

                            <p>
                                <?= e($description) ?>
                            </p>

                            <div class="customer-all-gallery-meta">

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
                                class="customer-all-gallery-view"
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

            </section>

        <?php endif; ?>

        <section class="customer-all-gallery-pagination">

            <p>
                Showing
                <?= e(
                    number_format(
                        $firstResult
                    )
                ) ?>
                to
                <?= e(
                    number_format(
                        $lastResult
                    )
                ) ?>
                of
                <?= e(
                    number_format(
                        $totalResults
                    )
                ) ?>
                image<?= $totalResults === 1
                    ? ''
                    : 's' ?>
            </p>

            <nav aria-label="Gallery pages">

                <a
                    class="<?= $page <= 1
                        ? 'disabled'
                        : '' ?>"
                    href="<?= $page <= 1
                        ? '#'
                        : e(
                            url(
                                '/customer/all_gallery.php'
                                . $buildGalleryQuery([
                                    'page' =>
                                        $page - 1,
                                ])
                            )
                        ) ?>"
                    aria-label="Previous page"
                >
                    <i class="fa-solid fa-chevron-left"></i>
                </a>

                <?php
                $startPage = max(
                    1,
                    $page - 2
                );

                $endPage = min(
                    $totalPages,
                    $page + 2
                );
                ?>

                <?php for (
                    $pageNumber = $startPage;
                    $pageNumber <= $endPage;
                    $pageNumber++
                ): ?>

                    <a
                        class="<?= $pageNumber === $page
                            ? 'active'
                            : '' ?>"
                        href="<?= e(
                            url(
                                '/customer/all_gallery.php'
                                . $buildGalleryQuery([
                                    'page' =>
                                        $pageNumber,
                                ])
                            )
                        ) ?>"
                    >
                        <?= e(
                            (string) $pageNumber
                        ) ?>
                    </a>

                <?php endfor; ?>

                <a
                    class="<?= $page >= $totalPages
                        ? 'disabled'
                        : '' ?>"
                    href="<?= $page >= $totalPages
                        ? '#'
                        : e(
                            url(
                                '/customer/all_gallery.php'
                                . $buildGalleryQuery([
                                    'page' =>
                                        $page + 1,
                                ])
                            )
                        ) ?>"
                    aria-label="Next page"
                >
                    <i class="fa-solid fa-chevron-right"></i>
                </a>

            </nav>

            <span>
                <?= e(
                    (string) $perPage
                ) ?>
                per page

                <i class="fa-solid fa-chevron-down"></i>
            </span>

        </section>

        <footer class="customer-all-gallery-footer">
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