<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/package_helpers.php';

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

$buildPackagesQuery = static function (
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
        trim((string) $parameters['q']) === ''
    ) {
        unset($parameters['q']);
    }

    if (
        (string) $parameters['status'] === 'all'
    ) {
        unset($parameters['status']);
    }

    if (
        (int) $parameters['page'] <= 1
    ) {
        unset($parameters['page']);
    }

    return $parameters === []
        ? ''
        : '?' . http_build_query($parameters);
};

/*
|--------------------------------------------------------------------------
| Delete package
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $action = trim(
        (string) ($_POST['action'] ?? '')
    );

    $redirectQuery = trim(
        (string) ($_POST['redirect_query'] ?? '')
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

    if (!verify_csrf($submittedToken)) {
        set_flash(
            'error',
            'Your form session expired. Refresh the page and try again.'
        );

        redirect(
            '/admin/all_packages.php'
            . $redirectQuery
        );
    }

    if ($action === 'delete') {
        $packageId = max(
            0,
            (int) ($_POST['package_id'] ?? 0)
        );

        $packageStatement = $connection->prepare(
            'SELECT
                main_image,
                image_one,
                image_two,
                image_three,
                image_four
             FROM packages
             WHERE id = ?
             LIMIT 1'
        );

        $packageStatement->execute([
            $packageId,
        ]);

        $packageToDelete = $packageStatement->fetch();

        if (!$packageToDelete) {
            set_flash(
                'error',
                'The selected package was not found.'
            );

            redirect(
                '/admin/all_packages.php'
                . $redirectQuery
            );
        }

        try {
            $deleteStatement = $connection->prepare(
                'DELETE FROM packages
                 WHERE id = ?'
            );

            $deleteStatement->execute([
                $packageId,
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
                delete_package_image(
                    $packageToDelete[$column]
                    ?? null
                );
            }

            set_flash(
                'success',
                'Package deleted successfully.'
            );
        } catch (Throwable $exception) {
            set_flash(
                'error',
                APP_DEBUG
                    ? 'Package deletion failed: '
                        . $exception->getMessage()
                    : 'This package could not be deleted because it may be connected to a booking.'
            );
        }

        redirect(
            '/admin/all_packages.php'
            . $redirectQuery
        );
    }
}

/*
|--------------------------------------------------------------------------
| Build package query
|--------------------------------------------------------------------------
*/

$whereParts = [];
$queryParameters = [];

if ($search !== '') {
    $whereParts[] =
        '(
            name LIKE ?
            OR short_description LIKE ?
            OR description LIKE ?
            OR decoration_type LIKE ?
        )';

    $searchValue = '%' . $search . '%';

    $queryParameters[] = $searchValue;
    $queryParameters[] = $searchValue;
    $queryParameters[] = $searchValue;
    $queryParameters[] = $searchValue;
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
     FROM packages'
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

$packagesStatement = $connection->prepare(
    'SELECT *
     FROM packages'
    . $whereSql
    . " ORDER BY
            created_at DESC,
            id DESC
        LIMIT {$perPage}
        OFFSET {$offset}"
);

$packagesStatement->execute(
    $queryParameters
);

$packages = $packagesStatement->fetchAll();

$currentQuery = $buildPackagesQuery([
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
        View All Packages | <?= e(APP_NAME) ?>
    </title>

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/all_packages.css')
        ) ?>"
    >
</head>

<body class="all-packages-page">

    <header class="all-packages-header">

        <a
            class="all-packages-brand"
            href="<?= e(
                url('/admin/dashboard.php')
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
            class="all-packages-admin"
            href="<?= e(
                url('/admin/profile.php')
            ) ?>"
            aria-label="Open administrator profile"
        >

            <div>

                <strong>
                    <?= e(
                        (string) $admin['full_name']
                    ) ?>
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

    <main class="all-packages-shell">

        <section class="all-packages-page-head">

            <div class="all-packages-heading-copy">

                <h1>View All Packages</h1>

                <p>
                    Manage all wedding packages from here.
                </p>

            </div>

            <div class="all-packages-heading-controls">

                <form
                    class="all-packages-search-form"
                    method="get"
                >

                    <div class="all-packages-search-box">

                        <input
                            type="search"
                            name="q"
                            value="<?= e($search) ?>"
                            placeholder="Search packages..."
                            aria-label="Search packages"
                        >

                        <button
                            class="all-packages-search-icon"
                            type="submit"
                            aria-label="Search"
                        >
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>

                    </div>

                    <label class="all-packages-filter-box">

                        <i class="fa-solid fa-filter"></i>

                        <select
                            name="status"
                            aria-label="Filter packages by status"
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
                            class="all-packages-clear-button"
                            href="<?= e(
                                url('/admin/all_packages.php')
                            ) ?>"
                            title="Clear search and filter"
                            aria-label="Clear search and filter"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </a>

                    <?php endif; ?>

                </form>

                <a
                    class="all-packages-add-button"
                    href="<?= e(
                        url(
                            '/admin/packages.php?add=1&return_to=all'
                        )
                    ) ?>"
                >
                    <i class="fa-solid fa-plus"></i>
                    Add New Package
                </a>

            </div>

        </section>

        <?php if ($flash): ?>

            <div
                class="all-packages-flash <?= $flash['type'] === 'success'
                    ? 'success'
                    : 'danger' ?>"
            >
                <?= e(
                    (string) $flash['message']
                ) ?>
            </div>

        <?php endif; ?>

        <?php if ($packages === []): ?>

            <section class="all-packages-empty">

                <i class="fa-solid fa-gift"></i>

                <h2>No packages found</h2>

                <p>
                    Try another search or clear the current filter.
                </p>

                <a
                    href="<?= e(
                        url('/admin/all_packages.php')
                    ) ?>"
                >
                    Show All Packages
                </a>

            </section>

        <?php else: ?>

            <section class="all-packages-grid">

                <?php foreach (
                    $packages as $package
                ): ?>
                    <?php
                    $packageId = (int) $package['id'];

                    $status = strtolower(
                        trim(
                            (string) (
                                $package['status']
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

                    $mainImageUrl = package_image_url(
                        $package['main_image']
                        ?? null
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
                                $package[$imageColumn]
                                ?? ''
                            )
                        );

                        if ($imagePath === '') {
                            continue;
                        }

                        $previewImages[] = [
                            'url' => package_image_url(
                                $imagePath
                            ),
                            'is_main' => false,
                        ];
                    }
                    ?>

                    <article class="all-package-card">

                        <div class="all-package-main-image-wrap">

                            <img
                                class="all-package-main-image"
                                id="allPackageMainImage<?= e(
                                    (string) $packageId
                                ) ?>"
                                src="<?= e($mainImageUrl) ?>"
                                alt="<?= e(
                                    (string) (
                                        $package['name']
                                        ?? 'Wedding package'
                                    )
                                ) ?>"
                            >

                            <span
                                class="all-package-image-status <?= e(
                                    $status
                                ) ?>"
                            >
                                <?= $status === 'active'
                                    ? 'Active'
                                    : 'Inactive' ?>
                            </span>

                            <span class="package-main-photo-badge">

                                <i class="fa-regular fa-image"></i>

                                <span>Main Photo</span>

                            </span>

                        </div>

                        <div class="all-package-card-body">

                            <h2>
                                <?= e(
                                    (string) (
                                        $package['name']
                                        ?? 'Untitled Package'
                                    )
                                ) ?>
                            </h2>

                            <p class="all-package-description">
                                <?= e(
                                    package_card_description(
                                        $package
                                    )
                                ) ?>
                            </p>

                            <div class="all-package-thumbnails">

                                <?php foreach (
                                    $previewImages as $index => $previewImage
                                ): ?>

                                    <button
                                        class="all-package-thumbnail <?= $index === 0
                                            ? 'active package-main-thumbnail'
                                            : '' ?>"
                                        type="button"
                                        data-package-main="allPackageMainImage<?= e(
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

                                            <span class="package-main-thumbnail-label">
                                                Main
                                            </span>

                                        <?php endif; ?>

                                    </button>

                                <?php endforeach; ?>

                            </div>

                            <div class="all-package-actions">

                                <a
                                    class="all-package-edit-button"
                                    href="<?= e(
                                        url(
                                            '/admin/packages.php?edit='
                                            . $packageId
                                            . '&return_to=all'
                                        )
                                    ) ?>"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                    Edit
                                </a>

                                <form
                                    method="post"
                                    onsubmit="return confirm('Delete this package permanently?');"
                                >
                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="package_id"
                                        value="<?= e(
                                            (string) $packageId
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
                                        class="all-package-delete-button"
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

            <section class="all-packages-pagination-row">

                <p class="all-packages-range">

                    Showing <?= e(
                        number_format($firstResult)
                    ) ?>

                    to <?= e(
                        number_format($lastResult)
                    ) ?>

                    of <?= e(
                        number_format($totalResults)
                    ) ?>

                    package<?= $totalResults === 1
                        ? ''
                        : 's' ?>

                </p>

                <nav
                    class="all-packages-pagination"
                    aria-label="Package pages"
                >

                    <a
                        class="<?= $page <= 1
                            ? 'disabled'
                            : '' ?>"
                        href="<?= $page <= 1
                            ? '#'
                            : e(
                                url(
                                    '/admin/all_packages.php'
                                    . $buildPackagesQuery([
                                        'page' => $page - 1,
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
                                    '/admin/all_packages.php'
                                    . $buildPackagesQuery([
                                        'page' => $pageNumber,
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
                                    '/admin/all_packages.php'
                                    . $buildPackagesQuery([
                                        'page' => $page + 1,
                                    ])
                                )
                            ) ?>"
                        aria-label="Next page"
                    >
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                </nav>

                <span class="all-packages-per-page">

                    <?= e(
                        (string) $perPage
                    ) ?> per page

                    <i class="fa-solid fa-chevron-down"></i>

                </span>

            </section>

        <?php endif; ?>

        <footer class="all-packages-footer">

            © <?= e(
                (string) $currentYear
            ) ?>

            Wedding Event Planner.
            All rights reserved.

        </footer>

    </main>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>