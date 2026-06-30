<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/gallery_helpers.php';

$allowedRoles = [
    'admin',
    'booking_manager',
    'event_manager',
];

require_role($allowedRoles);

$connection = db();
$currentUserId = (int) $_SESSION['user_id'];
$currentRole = (string) $_SESSION['user_role'];
$canManageGallery = $currentRole === 'event_manager';
$flash = get_flash();

$roleSettings = [
    'admin' => [
        'label' => 'Admin',
        'main_gallery_path' => '/admin/gallery.php',
        'dashboard_path' => '/admin/dashboard.php',
        'profile_path' => '/admin/profile.php',
    ],

    'booking_manager' => [
        'label' => 'Booking Manager',
        'main_gallery_path' => '/booking_manager/gallery.php',
        'dashboard_path' => '/booking_manager/dashboard.php',
        'profile_path' => '/booking_manager/profile.php',
    ],

    'event_manager' => [
        'label' => 'Event Manager',
        'main_gallery_path' => '/event_manager/gallery.php',
        'dashboard_path' => '/event_manager/dashboard.php',
        'profile_path' => '/event_manager/profile.php',
    ],
];

$currentRoleSettings = $roleSettings[$currentRole];

/*
|--------------------------------------------------------------------------
| Load current staff account
|--------------------------------------------------------------------------
*/

$userStatement = $connection->prepare(
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

$userStatement->execute([
    $currentUserId,
    $currentRole,
]);

$currentUser = $userStatement->fetch();

if (!$currentUser) {
    redirect('/auth/logout.php');
}

$currentUserImage = !empty($currentUser['profile_image'])
    ? url('/' . ltrim((string) $currentUser['profile_image'], '/'))
    : url('/assets/icons/icon-192.png');

$profileSubtitle = $currentRoleSettings['label'];

if ($currentRole === 'admin') {
    $savedAbout = trim(
        (string) ($currentUser['about'] ?? '')
    );

    $profileSubtitle = $savedAbout !== ''
        ? (string) preg_replace(
            '/\s+/u',
            ' ',
            $savedAbout
        )
        : 'System Administrator';
}

/*
|--------------------------------------------------------------------------
| Search, filter and pagination
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) ($_GET['q'] ?? '')
);

$statusFilter = strtolower(
    trim(
        (string) ($_GET['status'] ?? 'all')
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

if (
    !in_array(
        $statusFilter,
        ['all', 'active', 'inactive'],
        true
    )
) {
    $statusFilter = 'all';
}

$buildGalleryQuery = static function (
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

    if (
        trim(
            (string) $parameters['q']
        ) === ''
    ) {
        unset($parameters['q']);
    }

    if (
        (string) $parameters['status']
        === 'all'
    ) {
        unset($parameters['status']);
    }

    if (
        (int) $parameters['page']
        <= 1
    ) {
        unset($parameters['page']);
    }

    return $parameters === []
        ? ''
        : '?' . http_build_query($parameters);
};

/*
|--------------------------------------------------------------------------
| Event Manager delete action
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $redirectQuery = trim(
        (string) (
            $_POST['redirect_query']
            ?? ''
        )
    );

    if (
        $redirectQuery !== ''
        && !str_starts_with(
            $redirectQuery,
            '?'
        )
    ) {
        $redirectQuery = '';
    }

    if (!$canManageGallery) {
        set_flash(
            'error',
            'Only the Event Manager can change gallery records.'
        );

        redirect(
            '/gallery/all_gallery.php'
            . $redirectQuery
        );
    }

    $submittedToken = (string) (
        $_POST['csrf_token']
        ?? ''
    );

    $action = trim(
        (string) (
            $_POST['action']
            ?? ''
        )
    );

    if (!verify_csrf($submittedToken)) {
        set_flash(
            'error',
            'Your form session expired. Refresh the page and try again.'
        );

        redirect(
            '/gallery/all_gallery.php'
            . $redirectQuery
        );
    }

    if ($action === 'delete') {
        $galleryId = max(
            0,
            (int) (
                $_POST['gallery_id']
                ?? 0
            )
        );

        $galleryStatement = $connection->prepare(
            'SELECT
                id,
                image,
                image_two
             FROM gallery
             WHERE id = ?
             LIMIT 1'
        );

        $galleryStatement->execute([
            $galleryId,
        ]);

        $galleryToDelete =
            $galleryStatement->fetch();

        if (!$galleryToDelete) {
            set_flash(
                'error',
                'The selected gallery record was not found.'
            );

            redirect(
                '/gallery/all_gallery.php'
                . $redirectQuery
            );
        }

        try {
            $connection->beginTransaction();

            $deleteStatement =
                $connection->prepare(
                    'DELETE FROM gallery
                     WHERE id = ?'
                );

            $deleteStatement->execute([
                $galleryId,
            ]);

            $connection->commit();

            delete_gallery_image(
                $galleryToDelete['image']
                ?? null
            );

            delete_gallery_image(
                $galleryToDelete['image_two']
                ?? null
            );

            set_flash(
                'success',
                'Gallery record deleted successfully.'
            );
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            set_flash(
                'error',
                APP_DEBUG
                    ? 'Gallery deletion failed: '
                        . $exception->getMessage()
                    : 'The gallery record could not be deleted.'
            );
        }

        redirect(
            '/gallery/all_gallery.php'
            . $redirectQuery
        );
    }
}

/*
|--------------------------------------------------------------------------
| Build gallery query
|--------------------------------------------------------------------------
*/

$whereParts = [];
$queryParameters = [];

if ($search !== '') {
    $whereParts[] =
        '(
            gallery.title LIKE ?
            OR gallery.event_type LIKE ?
            OR gallery.description LIKE ?
        )';

    $searchValue =
        '%' . $search . '%';

    $queryParameters[] =
        $searchValue;

    $queryParameters[] =
        $searchValue;

    $queryParameters[] =
        $searchValue;
}

if ($statusFilter !== 'all') {
    $whereParts[] =
        'gallery.status = ?';

    $queryParameters[] =
        $statusFilter;
}

$whereSql = $whereParts === []
    ? ''
    : ' WHERE '
        . implode(
            ' AND ',
            $whereParts
        );

$countStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM gallery'
    . $whereSql
);

$countStatement->execute(
    $queryParameters
);

$totalResults =
    (int) $countStatement->fetchColumn();

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

$galleryStatement = $connection->prepare(
    'SELECT
        gallery.id,
        gallery.title,
        gallery.description,
        gallery.event_type,
        gallery.image,
        gallery.image_two,
        gallery.status,
        gallery.created_at,
        gallery.updated_at,
        users.full_name AS uploader_name

     FROM gallery

     LEFT JOIN users
        ON users.id = gallery.created_by'
    . $whereSql
    . " ORDER BY
            gallery.created_at DESC,
            gallery.id DESC
        LIMIT {$perPage}
        OFFSET {$offset}"
);

$galleryStatement->execute(
    $queryParameters
);

$galleryItems =
    $galleryStatement->fetchAll();

$currentQuery =
    $buildGalleryQuery([
        'page' => $page,
    ]);

$firstResult =
    $totalResults === 0
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
            url('/assets/css/all_gallery.css')
        ) ?>"
    >
</head>

<body class="all-gallery-page">

    <header class="all-gallery-header">

        <a
            class="all-gallery-brand"
            href="<?= e(
                url(
                    $currentRoleSettings[
                        'dashboard_path'
                    ]
                )
            ) ?>"
        >

            <img
                src="<?= e(
                    url('/assets/icons/icon-192.png')
                ) ?>"
                alt="Wedding Event Planner"
            >

            <div>
                <strong>Wedding</strong>
                <span>Event Planner</span>
            </div>

        </a>

        <a
            class="all-gallery-user"
            href="<?= e(
                url(
                    $currentRoleSettings[
                        'profile_path'
                    ]
                )
            ) ?>"
            aria-label="Open profile"
        >

            <div>

                <strong>
                    <?= e(
                        (string) $currentUser[
                            'full_name'
                        ]
                    ) ?>
                </strong>

                <span>
                    <?= e($profileSubtitle) ?>
                </span>

            </div>

            <img
                src="<?= e($currentUserImage) ?>"
                alt="<?= e(
                    $currentRoleSettings[
                        'label'
                    ]
                ) ?> profile"
            >

        </a>

    </header>

    <main class="all-gallery-shell">

        <section class="all-gallery-page-head">

            <div class="all-gallery-heading-copy">

                <h1>View All Gallery Images</h1>

                <p>
                    <?= $canManageGallery
                        ? 'Search and manage all wedding-event gallery images.'
                        : 'Search and view all wedding-event gallery images.' ?>
                </p>

            </div>

            <div class="all-gallery-heading-controls">

                <form
                    class="all-gallery-search-form"
                    method="get"
                >

                    <div class="all-gallery-search-box">

                        <input
                            type="search"
                            name="q"
                            value="<?= e($search) ?>"
                            placeholder="Search gallery images..."
                            aria-label="Search gallery images"
                        >

                        <button
                            class="all-gallery-search-icon"
                            type="submit"
                            aria-label="Search"
                        >
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>

                    </div>

                    <label class="all-gallery-filter-box">

                        <i class="fa-solid fa-filter"></i>

                        <select
                            name="status"
                            aria-label="Filter gallery images by status"
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
                            class="all-gallery-clear-button"
                            href="<?= e(
                                url('/gallery/all_gallery.php')
                            ) ?>"
                            title="Clear search and filter"
                            aria-label="Clear search and filter"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </a>

                    <?php endif; ?>

                </form>

                <?php if ($canManageGallery): ?>

                    <a
                        class="all-gallery-add-button"
                        href="<?= e(
                            url(
                                '/event_manager/gallery.php?add=1&return_to=all'
                            )
                        ) ?>"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Add New Image
                    </a>

                <?php endif; ?>

                <a
                    class="all-gallery-back-button"
                    href="<?= e(
                        url(
                            $currentRoleSettings[
                                'main_gallery_path'
                            ]
                        )
                    ) ?>"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Gallery
                </a>

            </div>

        </section>

        <?php if ($flash): ?>

            <div
                class="all-gallery-alert <?= $flash['type'] === 'success'
                    ? 'success'
                    : 'danger' ?>"
            >
                <?= e(
                    (string) $flash['message']
                ) ?>
            </div>

        <?php endif; ?>

        <?php if ($galleryItems === []): ?>

            <section class="all-gallery-empty-state">

                <i class="fa-regular fa-images"></i>

                <h2>No gallery images found</h2>

                <p>
                    Try another search term or clear the current filter.
                </p>

                <a
                    href="<?= e(
                        url('/gallery/all_gallery.php')
                    ) ?>"
                >
                    Show All Images
                </a>

            </section>

        <?php else: ?>

            <section class="all-gallery-grid">

                <?php foreach (
                    $galleryItems as $galleryItem
                ): ?>
                    <?php
                    $galleryId =
                        (int) $galleryItem['id'];

                    $status =
                        gallery_status_value(
                            $galleryItem['status']
                            ?? 'inactive'
                        );

                    $mainImageUrl =
                        gallery_image_url(
                            $galleryItem['image']
                            ?? null
                        );

                    $secondImagePath = trim(
                        (string) (
                            $galleryItem[
                                'image_two'
                            ]
                            ?? ''
                        )
                    );

                    $previewImages = [
                        $mainImageUrl,
                    ];

                    if ($secondImagePath !== '') {
                        $previewImages[] =
                            gallery_image_url(
                                $secondImagePath
                            );
                    }

                    $photoCount =
                        gallery_photo_count(
                            $galleryItem
                        );
                    ?>

                    <article class="all-gallery-card">

                        <div class="all-gallery-image-box">

                            <button
                                class="all-gallery-preview-button"
                                type="button"
                                data-gallery-images="<?= e(
                                    json_encode(
                                        $previewImages,
                                        JSON_UNESCAPED_SLASHES
                                    )
                                ) ?>"
                                data-gallery-title="<?= e(
                                    (string) (
                                        $galleryItem[
                                            'title'
                                        ]
                                        ?? ''
                                    )
                                ) ?>"
                                data-gallery-description="<?= e(
                                    (string) (
                                        $galleryItem[
                                            'description'
                                        ]
                                        ?? ''
                                    )
                                ) ?>"
                                aria-label="Preview gallery images"
                            >

                                <img
                                    src="<?= e($mainImageUrl) ?>"
                                    alt="<?= e(
                                        (string) (
                                            $galleryItem[
                                                'title'
                                            ]
                                            ?? 'Gallery image'
                                        )
                                    ) ?>"
                                >

                            </button>

                            <span
                                class="all-gallery-status <?= e(
                                    $status
                                ) ?>"
                            >
                                <?= $status === 'active'
                                    ? 'Active'
                                    : 'Inactive' ?>
                            </span>

                            <span class="all-gallery-photo-count">

                                <i class="fa-solid fa-images"></i>

                                <?= e(
                                    (string) $photoCount
                                ) ?>

                                Photo<?= $photoCount === 1
                                    ? ''
                                    : 's' ?>

                            </span>

                        </div>

                        <div class="all-gallery-card-body">

                            <h2>
                                <?= e(
                                    (string) (
                                        $galleryItem[
                                            'title'
                                        ]
                                        ?? 'Untitled image'
                                    )
                                ) ?>
                            </h2>

                            <?php if (
                                trim(
                                    (string) (
                                        $galleryItem[
                                            'event_type'
                                        ]
                                        ?? ''
                                    )
                                ) !== ''
                            ): ?>

                                <div class="all-gallery-event-type">

                                    <i class="fa-solid fa-heart"></i>

                                    <?= e(
                                        (string) $galleryItem[
                                            'event_type'
                                        ]
                                    ) ?>

                                </div>

                            <?php endif; ?>

                            <p class="all-gallery-description">
                                <?= e(
                                    trim(
                                        (string) (
                                            $galleryItem[
                                                'description'
                                            ]
                                            ?? ''
                                        )
                                    ) !== ''
                                        ? (string) $galleryItem[
                                            'description'
                                        ]
                                        : 'No description has been added.'
                                ) ?>
                            </p>

                            <div class="all-gallery-meta">

                                <span>

                                    <i class="fa-regular fa-calendar"></i>

                                    <?= e(
                                        gallery_display_date(
                                            $galleryItem[
                                                'created_at'
                                            ]
                                            ?? null
                                        )
                                    ) ?>

                                </span>

                                <span>

                                    <i class="fa-regular fa-user"></i>

                                    <?= e(
                                        trim(
                                            (string) (
                                                $galleryItem[
                                                    'uploader_name'
                                                ]
                                                ?? ''
                                            )
                                        ) !== ''
                                            ? (string) $galleryItem[
                                                'uploader_name'
                                            ]
                                            : 'Event Manager'
                                    ) ?>

                                </span>

                            </div>

                            <?php if ($canManageGallery): ?>

                                <div class="all-gallery-actions">

                                    <a
                                        class="all-gallery-edit-button"
                                        href="<?= e(
                                            url(
                                                '/event_manager/gallery.php?edit='
                                                . $galleryId
                                                . '&return_to=all'
                                            )
                                        ) ?>"
                                    >
                                        <i class="fa-solid fa-pen"></i>
                                        Edit
                                    </a>

                                    <form
                                        method="post"
                                        onsubmit="return confirm('Delete this gallery record and its images?');"
                                    >
                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="gallery_id"
                                            value="<?= e(
                                                (string) $galleryId
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="redirect_query"
                                            value="<?= e(
                                                $currentQuery
                                            ) ?>"
                                        >

                                        <button
                                            class="all-gallery-delete-button"
                                            type="submit"
                                        >
                                            <i class="fa-solid fa-trash-can"></i>
                                            Delete
                                        </button>

                                    </form>

                                </div>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </section>

        <?php endif; ?>

        <section class="all-gallery-pagination-row">

            <p class="all-gallery-range">

                Showing <?= e(
                    number_format($firstResult)
                ) ?>

                to <?= e(
                    number_format($lastResult)
                ) ?>

                of <?= e(
                    number_format($totalResults)
                ) ?>

                image<?= $totalResults === 1
                    ? ''
                    : 's' ?>

            </p>

            <nav
                class="all-gallery-pagination"
                aria-label="Gallery pages"
            >

                <a
                    class="<?= $page <= 1
                        ? 'disabled'
                        : '' ?>"
                    href="<?= $page <= 1
                        ? '#'
                        : e(
                            url(
                                '/gallery/all_gallery.php'
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
                                '/gallery/all_gallery.php'
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
                                '/gallery/all_gallery.php'
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

            <span class="all-gallery-per-page">

                <?= e(
                    (string) $perPage
                ) ?> per page

                <i class="fa-solid fa-chevron-down"></i>

            </span>

        </section>

        <footer class="all-gallery-footer">

            © <?= e(
                (string) $currentYear
            ) ?>

            Wedding Event Planner.
            All rights reserved.

        </footer>

    </main>

    <div
        class="all-gallery-preview-modal"
        id="allGalleryPreviewModal"
        aria-hidden="true"
    >

        <div
            class="all-gallery-preview-backdrop"
            data-close-preview
        ></div>

        <section
            class="all-gallery-preview-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="allGalleryPreviewTitle"
        >

            <button
                class="all-gallery-preview-close"
                type="button"
                data-close-preview
                aria-label="Close image preview"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="all-gallery-preview-stage">

                <button
                    class="all-gallery-preview-navigation previous"
                    id="allGalleryPreviewPrevious"
                    type="button"
                    aria-label="Previous image"
                >
                    <i class="fa-solid fa-chevron-left"></i>
                </button>

                <img
                    id="allGalleryPreviewImage"
                    src=""
                    alt="Gallery preview"
                >

                <button
                    class="all-gallery-preview-navigation next"
                    id="allGalleryPreviewNext"
                    type="button"
                    aria-label="Next image"
                >
                    <i class="fa-solid fa-chevron-right"></i>
                </button>

            </div>

            <div class="all-gallery-preview-copy">

                <h2 id="allGalleryPreviewTitle">
                    Gallery Image
                </h2>

                <p id="allGalleryPreviewDescription"></p>

                <span id="allGalleryPreviewCounter"></span>

            </div>

        </section>

    </div>

    <script>
        "use strict";

        document.addEventListener("DOMContentLoaded", function () {
            const modal = document.getElementById(
                "allGalleryPreviewModal"
            );

            const previewImage = document.getElementById(
                "allGalleryPreviewImage"
            );

            const previewTitle = document.getElementById(
                "allGalleryPreviewTitle"
            );

            const previewDescription = document.getElementById(
                "allGalleryPreviewDescription"
            );

            const previewCounter = document.getElementById(
                "allGalleryPreviewCounter"
            );

            const previousButton = document.getElementById(
                "allGalleryPreviewPrevious"
            );

            const nextButton = document.getElementById(
                "allGalleryPreviewNext"
            );

            let images = [];
            let currentIndex = 0;

            const renderPreview = function () {
                if (images.length === 0) {
                    return;
                }

                previewImage.src =
                    images[currentIndex];

                previewCounter.textContent =
                    (currentIndex + 1)
                    + " of "
                    + images.length;

                const hasMultipleImages =
                    images.length > 1;

                previousButton.hidden =
                    !hasMultipleImages;

                nextButton.hidden =
                    !hasMultipleImages;
            };

            const openPreview = function (button) {
                try {
                    images = JSON.parse(
                        button.dataset.galleryImages
                        || "[]"
                    );
                } catch (error) {
                    images = [];
                }

                if (
                    !Array.isArray(images)
                    || images.length === 0
                ) {
                    return;
                }

                currentIndex = 0;

                previewTitle.textContent =
                    button.dataset.galleryTitle
                    || "Gallery Image";

                previewDescription.textContent =
                    button.dataset.galleryDescription
                    || "No description has been added.";

                renderPreview();

                modal.classList.add("open");

                modal.setAttribute(
                    "aria-hidden",
                    "false"
                );

                document.body.classList.add(
                    "all-gallery-preview-lock"
                );
            };

            const closePreview = function () {
                modal.classList.remove("open");

                modal.setAttribute(
                    "aria-hidden",
                    "true"
                );

                document.body.classList.remove(
                    "all-gallery-preview-lock"
                );

                previewImage.src = "";
                images = [];
            };

            document.addEventListener(
                "click",
                function (event) {
                    const previewButton =
                        event.target.closest(
                            ".all-gallery-preview-button"
                        );

                    if (previewButton) {
                        openPreview(previewButton);
                        return;
                    }

                    if (
                        event.target.closest(
                            "[data-close-preview]"
                        )
                    ) {
                        closePreview();
                    }
                }
            );

            previousButton.addEventListener(
                "click",
                function () {
                    if (images.length < 2) {
                        return;
                    }

                    currentIndex =
                        (
                            currentIndex
                            - 1
                            + images.length
                        )
                        % images.length;

                    renderPreview();
                }
            );

            nextButton.addEventListener(
                "click",
                function () {
                    if (images.length < 2) {
                        return;
                    }

                    currentIndex =
                        (
                            currentIndex
                            + 1
                        )
                        % images.length;

                    renderPreview();
                }
            );

            document.addEventListener(
                "keydown",
                function (event) {
                    if (
                        !modal.classList.contains(
                            "open"
                        )
                    ) {
                        return;
                    }

                    if (event.key === "Escape") {
                        closePreview();
                    }

                    if (event.key === "ArrowLeft") {
                        previousButton.click();
                    }

                    if (event.key === "ArrowRight") {
                        nextButton.click();
                    }
                }
            );
        });
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>