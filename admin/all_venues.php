<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/venue_helpers.php';

require_role('admin');

$connection = db();
$adminId = (int) $_SESSION['user_id'];
$flash = get_flash();

/*
|--------------------------------------------------------------------------
| Load administrator
|--------------------------------------------------------------------------
*/

$adminStatement = $connection->prepare(
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

$adminStatement->execute([
    $adminId,
    'admin',
]);

$admin = $adminStatement->fetch();

if (!$admin) {
    redirect('/auth/logout.php');
}

$adminImage = !empty($admin['profile_image'])
    ? url('/' . ltrim((string) $admin['profile_image'], '/'))
    : url('/assets/icons/icon-192.png');

$adminAbout = trim((string) ($admin['about'] ?? ''));

if ($adminAbout === '') {
    $adminAbout = 'System Administrator';
}

/*
|--------------------------------------------------------------------------
| Search and pagination
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['q'] ?? ''));

$statusFilter = strtolower(
    trim((string) ($_GET['status'] ?? 'all'))
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

if (
    !in_array(
        $statusFilter,
        ['all', 'active', 'inactive'],
        true
    )
) {
    $statusFilter = 'all';
}

$buildVenuesQuery = static function (
    array $changes = []
) use (
    $search,
    $statusFilter,
    &$page
): string {
    $parameters = [
        'q' => $search,
        'status' => $statusFilter,
        'page' => $page,
    ];

    foreach ($changes as $key => $value) {
        $parameters[$key] = $value;
    }

    if (trim((string) $parameters['q']) === '') {
        unset($parameters['q']);
    }

    if ((string) $parameters['status'] === 'all') {
        unset($parameters['status']);
    }

    if ((int) $parameters['page'] <= 1) {
        unset($parameters['page']);
    }

    return $parameters === []
        ? ''
        : '?' . http_build_query($parameters);
};

/*
|--------------------------------------------------------------------------
| Delete venue
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $action = trim((string) ($_POST['action'] ?? ''));

    $redirectQuery = trim(
        (string) ($_POST['redirect_query'] ?? '')
    );

    if (
        $redirectQuery !== ''
        && !str_starts_with($redirectQuery, '?')
    ) {
        $redirectQuery = '';
    }

    if (!verify_csrf($submittedToken)) {
        set_flash(
            'error',
            'Your form session expired. Refresh the page and try again.'
        );

        redirect(
            '/admin/all_venues.php'
            . $redirectQuery
        );
    }

    if ($action === 'delete') {
        $venueId = max(
            0,
            (int) ($_POST['venue_id'] ?? 0)
        );

        $venueStatement = $connection->prepare(
            'SELECT
                main_image,
                image_one,
                image_two,
                image_three,
                image_four
             FROM venues
             WHERE id = ?
             LIMIT 1'
        );

        $venueStatement->execute([
            $venueId,
        ]);

        $venueToDelete = $venueStatement->fetch();

        if (!$venueToDelete) {
            set_flash(
                'error',
                'The selected venue was not found.'
            );

            redirect(
                '/admin/all_venues.php'
                . $redirectQuery
            );
        }

        try {
            $deleteStatement = $connection->prepare(
                'DELETE FROM venues
                 WHERE id = ?'
            );

            $deleteStatement->execute([
                $venueId,
            ]);

            foreach (
                [
                    'main_image',
                    'image_one',
                    'image_two',
                    'image_three',
                    'image_four',
                ] as $column
            ) {
                delete_venue_image(
                    $venueToDelete[$column] ?? null
                );
            }

            set_flash(
                'success',
                'Venue deleted successfully.'
            );
        } catch (Throwable $exception) {
            set_flash(
                'error',
                APP_DEBUG
                    ? 'Venue deletion failed: '
                        . $exception->getMessage()
                    : 'This venue could not be deleted because it may be connected to a booking.'
            );
        }

        redirect(
            '/admin/all_venues.php'
            . $redirectQuery
        );
    }
}

/*
|--------------------------------------------------------------------------
| Build venue query
|--------------------------------------------------------------------------
*/

$whereParts = [];
$queryParameters = [];

if ($search !== '') {
    /*
     * Search using the visible venue name only.
     */
    $whereParts[] = 'name LIKE ?';
    $queryParameters[] = '%' . $search . '%';
}

if ($statusFilter !== 'all') {
    $whereParts[] = 'status = ?';
    $queryParameters[] = $statusFilter;
}

$whereSql = $whereParts === []
    ? ''
    : ' WHERE ' . implode(
        ' AND ',
        $whereParts
    );

$countStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM venues'
    . $whereSql
);

$countStatement->execute(
    $queryParameters
);

$totalResults = (int) $countStatement->fetchColumn();

$totalPages = max(
    1,
    (int) ceil(
        $totalResults / $perPage
    )
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$venuesStatement = $connection->prepare(
    'SELECT *
     FROM venues'
    . $whereSql
    . " ORDER BY
            created_at DESC,
            id DESC
        LIMIT {$perPage}
        OFFSET {$offset}"
);

$venuesStatement->execute(
    $queryParameters
);

$venues = $venuesStatement->fetchAll();

$currentQuery = $buildVenuesQuery([
    'page' => $page,
]);

$firstResult = $totalResults === 0
    ? 0
    : $offset + 1;

$lastResult = min(
    $offset + $perPage,
    $totalResults
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
        View All Venues | <?= e(APP_NAME) ?>
    </title>

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(url('/assets/css/all_venues.css')) ?>"
    >
</head>

<body class="all-venues-page">

    <header class="all-venues-header">

        <a
            class="all-venues-brand"
            href="<?= e(url('/admin/dashboard.php')) ?>"
        >

            <img
                src="<?= e(url('/assets/icons/icon-192.png')) ?>"
                alt="Wedding Event Planner"
            >

            <div>
                <strong>Wedding</strong>
                <span>Event Planner</span>
            </div>

        </a>

        <a
            class="all-venues-admin"
            href="<?= e(url('/admin/profile.php')) ?>"
            aria-label="Open administrator profile"
        >

            <div>

                <strong>
                    <?= e((string) $admin['full_name']) ?>
                </strong>

                <span>
                    <?= e($adminAbout) ?>
                </span>

            </div>

            <img
                src="<?= e($adminImage) ?>"
                alt="Administrator profile"
            >

        </a>

    </header>

    <main class="all-venues-shell">

        <section class="all-venues-page-head">

            <div class="all-venues-heading-copy">

                <h1>View All Venues</h1>

                <p>
                    Manage all wedding venues from here.
                </p>

            </div>

            <div class="all-venues-heading-controls">

                <form
                    class="all-venues-search-form"
                    method="get"
                >

                    <div class="all-venues-search-box">

                        <input
                            type="search"
                            name="q"
                            value="<?= e($search) ?>"
                            placeholder="Search venues..."
                            aria-label="Search venues"
                        >

                        <button
                            class="all-venues-search-icon"
                            type="submit"
                            aria-label="Search"
                        >
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>

                    </div>

                    <label class="all-venues-filter-box">

                        <i class="fa-solid fa-filter"></i>

                        <select
                            name="status"
                            aria-label="Filter venues by status"
                            onchange="this.form.submit()"
                        >

                            <option
                                value="all"
                                <?= $statusFilter === 'all'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Filter
                            </option>

                            <option
                                value="active"
                                <?= $statusFilter === 'active'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Active
                            </option>

                            <option
                                value="inactive"
                                <?= $statusFilter === 'inactive'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Inactive
                            </option>

                        </select>

                    </label>

                    <?php if (
                        $search !== ''
                        || $statusFilter !== 'all'
                    ): ?>

                        <a
                            class="all-venues-clear-button"
                            href="<?= e(url('/admin/all_venues.php')) ?>"
                            title="Clear search and filter"
                            aria-label="Clear search and filter"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </a>

                    <?php endif; ?>

                </form>

                <a
                    class="all-venues-add-button"
                    href="<?= e(
                        url('/admin/venues.php?add=1&return_to=all')
                    ) ?>"
                >
                    <i class="fa-solid fa-plus"></i>
                    Add New Venue
                </a>

            </div>

        </section>

        <?php if ($flash): ?>

            <div
                class="all-venues-flash <?= $flash['type'] === 'success'
                    ? 'success'
                    : 'danger' ?>"
            >
                <?= e((string) $flash['message']) ?>
            </div>

        <?php endif; ?>

        <?php if ($venues === []): ?>

            <section class="all-venues-empty">

                <i class="fa-solid fa-hotel"></i>

                <h2>No venues found</h2>

                <p>
                    Try another search or clear the current filter.
                </p>

                <a href="<?= e(url('/admin/all_venues.php')) ?>">
                    Show All Venues
                </a>

            </section>

        <?php else: ?>

            <section class="all-venues-grid">

                <?php foreach ($venues as $venue): ?>
                    <?php
                    $venueId = (int) $venue['id'];

                    $status = strtolower(
                        trim(
                            (string) (
                                $venue['status']
                                ?? 'inactive'
                            )
                        )
                    );

                    if (
                        !in_array(
                            $status,
                            ['active', 'inactive'],
                            true
                        )
                    ) {
                        $status = 'inactive';
                    }

                    $mainImageUrl = venue_image_url(
                        $venue['main_image'] ?? null
                    );

                    $previewImages = [
                        [
                            'url' => $mainImageUrl,
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
                            'url' => venue_image_url($imagePath),
                            'is_main' => false,
                        ];
                    }
                    ?>

                    <article class="all-venue-card">

                        <div class="all-venue-main-image-wrap">

                            <img
                                class="all-venue-main-image"
                                id="allVenueMainImage<?= e((string) $venueId) ?>"
                                src="<?= e($mainImageUrl) ?>"
                                alt="<?= e(
                                    (string) (
                                        $venue['name']
                                        ?? 'Wedding venue'
                                    )
                                ) ?>"
                            >

                            <span
                                class="all-venue-image-status <?= e($status) ?>"
                            >
                                <?= $status === 'active'
                                    ? 'Active'
                                    : 'Inactive' ?>
                            </span>

                            <span class="venue-main-photo-badge">

                                <i class="fa-regular fa-image"></i>

                                <span>Main Photo</span>

                            </span>

                        </div>

                        <div class="all-venue-card-body">

                            <h2>
                                <?= e(
                                    (string) (
                                        $venue['name']
                                        ?? 'Untitled Venue'
                                    )
                                ) ?>
                            </h2>

                            <p class="all-venue-description">
                                <?= e(venue_card_description($venue)) ?>
                            </p>

                            <div class="all-venue-thumbnails">

                                <?php foreach (
                                    $previewImages as $index => $previewImage
                                ): ?>

                                    <button
                                        class="all-venue-thumbnail <?= $index === 0
                                            ? 'active venue-main-thumbnail'
                                            : '' ?>"
                                        type="button"
                                        data-venue-main="allVenueMainImage<?= e((string) $venueId) ?>"
                                        data-venue-image="<?= e((string) $previewImage['url']) ?>"
                                        data-venue-is-main="<?= $previewImage['is_main']
                                            ? 'true'
                                            : 'false' ?>"
                                        aria-label="<?= $previewImage['is_main']
                                            ? 'Show original main venue photo'
                                            : 'Show venue gallery photo '
                                                . e((string) $index) ?>"
                                    >

                                        <img
                                            src="<?= e((string) $previewImage['url']) ?>"
                                            alt="<?= $previewImage['is_main']
                                                ? 'Original main venue photo'
                                                : 'Venue gallery photo '
                                                    . e((string) $index) ?>"
                                        >

                                        <?php if ($previewImage['is_main']): ?>

                                            <span class="venue-main-thumbnail-label">
                                                Main
                                            </span>

                                        <?php endif; ?>

                                    </button>

                                <?php endforeach; ?>

                            </div>

                            <div class="all-venue-actions">

                                <a
                                    class="all-venue-edit-button"
                                    href="<?= e(
                                        url(
                                            '/admin/venues.php?edit='
                                            . $venueId
                                            . '&return_to=all'
                                        )
                                    ) ?>"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                    Edit
                                </a>

                                <form
                                    method="post"
                                    onsubmit="return confirm('Delete this venue permanently?');"
                                >
                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="venue_id"
                                        value="<?= e((string) $venueId) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="redirect_query"
                                        value="<?= e($currentQuery) ?>"
                                    >

                                    <button
                                        class="all-venue-delete-button"
                                        type="submit"
                                    >
                                        <i class="fa-solid fa-trash-can"></i>
                                        Delete
                                    </button>

                                </form>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </section>

            <section class="all-venues-pagination-row">

                <p class="all-venues-range">
                    Showing <?= e(number_format($firstResult)) ?>
                    to <?= e(number_format($lastResult)) ?>
                    of <?= e(number_format($totalResults)) ?>
                    venue<?= $totalResults === 1 ? '' : 's' ?>
                </p>

                <nav
                    class="all-venues-pagination"
                    aria-label="Venue pages"
                >

                    <a
                        class="<?= $page <= 1
                            ? 'disabled'
                            : '' ?>"
                        href="<?= $page <= 1
                            ? '#'
                            : e(
                                url(
                                    '/admin/all_venues.php'
                                    . $buildVenuesQuery([
                                        'page' => $page - 1,
                                    ])
                                )
                            ) ?>"
                        aria-label="Previous page"
                    >
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
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
                                    '/admin/all_venues.php'
                                    . $buildVenuesQuery([
                                        'page' => $pageNumber,
                                    ])
                                )
                            ) ?>"
                        >
                            <?= e((string) $pageNumber) ?>
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
                                    '/admin/all_venues.php'
                                    . $buildVenuesQuery([
                                        'page' => $page + 1,
                                    ])
                                )
                            ) ?>"
                        aria-label="Next page"
                    >
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                </nav>

                <span class="all-venues-per-page">

                    <?= e((string) $perPage) ?> per page

                    <i class="fa-solid fa-chevron-down"></i>

                </span>

            </section>

        <?php endif; ?>

        <footer class="all-venues-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner.
            All rights reserved.
        </footer>

    </main>

    <script>
        "use strict";

        document.addEventListener("click", function (event) {
            const button = event.target.closest(
                ".all-venue-thumbnail"
            );

            if (!button) {
                return;
            }

            const mainImage = document.getElementById(
                button.dataset.venueMain
            );

            if (
                !mainImage
                || !button.dataset.venueImage
            ) {
                return;
            }

            mainImage.src = button.dataset.venueImage;

            const card = button.closest(
                ".all-venue-card"
            );

            card?.querySelectorAll(
                ".all-venue-thumbnail"
            ).forEach(function (thumbnail) {
                thumbnail.classList.remove("active");
            });

            button.classList.add("active");

            const mainBadge = card?.querySelector(
                ".venue-main-photo-badge"
            );

            mainBadge?.classList.toggle(
                "is-hidden",
                button.dataset.venueIsMain !== "true"
            );
        });
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>