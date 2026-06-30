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

$search = trim((string) ($_GET['q'] ?? ''));
$sort = strtolower(trim((string) ($_GET['sort'] ?? 'latest')));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 8;

if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}

$allowedSorts = [
    'latest',
    'price_low',
    'price_high',
    'capacity_high',
];

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'latest';
}

$orderBy = match ($sort) {
    'price_low' => 'price ASC, created_at DESC, id DESC',
    'price_high' => 'price DESC, created_at DESC, id DESC',
    'capacity_high' => 'guest_capacity DESC, created_at DESC, id DESC',
    default => 'created_at DESC, id DESC',
};

$buildPackagesQuery = static function (
    array $changes = []
) use (
    $search,
    $sort,
    &$page
): string {
    $parameters = [
        'q' => $search,
        'sort' => $sort,
        'page' => $page,
    ];

    foreach ($changes as $key => $value) {
        $parameters[$key] = $value;
    }

    if (trim((string) $parameters['q']) === '') {
        unset($parameters['q']);
    }

    if ((string) $parameters['sort'] === 'latest') {
        unset($parameters['sort']);
    }

    if ((int) $parameters['page'] <= 1) {
        unset($parameters['page']);
    }

    return $parameters === []
        ? ''
        : '?' . http_build_query($parameters);
};

$whereParts = ["status = 'active'"];
$queryParameters = [];

if ($search !== '') {
    $whereParts[] = '(
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

$whereSql = ' WHERE ' . implode(' AND ', $whereParts);

$countStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM packages'
    . $whereSql
);

$countStatement->execute($queryParameters);

$totalResults = (int) $countStatement->fetchColumn();

$totalPages = max(
    1,
    (int) ceil($totalResults / $perPage)
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$packagesStatement = $connection->prepare(
    'SELECT *
     FROM packages'
    . $whereSql
    . " ORDER BY {$orderBy}
        LIMIT {$perPage}
        OFFSET {$offset}"
);

$packagesStatement->execute($queryParameters);

$packages = $packagesStatement->fetchAll();

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
        href="<?= e(url('/assets/css/booking_manager_all_packages.css')) ?>"
    >
</head>

<body class="manager-all-packages-page">

    <header class="manager-all-packages-header">

        <a
            class="manager-all-packages-brand"
            href="<?= e(url('/booking_manager/dashboard.php')) ?>"
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
            class="manager-all-packages-user"
            href="<?= e(url('/booking_manager/profile.php')) ?>"
            aria-label="Open Booking Manager profile"
        >
            <div>
                <strong><?= e((string) $manager['full_name']) ?></strong>

                <span title="<?= e($managerAbout) ?>">
                    <?= e($managerAbout) ?>
                </span>
            </div>

            <img
                src="<?= e($managerImage) ?>"
                alt="Booking Manager profile"
            >
        </a>

    </header>

    <main class="manager-all-packages-shell">

        <section class="manager-all-packages-page-head">

            <div class="manager-all-packages-heading-copy">
                <h1>View All Packages</h1>

                <p>
                    Search available wedding packages and create customer bookings.
                </p>
            </div>

            <div class="manager-all-packages-heading-controls">

                <form
                    class="manager-all-packages-search-form"
                    method="get"
                >
                    <div class="manager-all-packages-search-box">
                        <input
                            type="search"
                            name="q"
                            value="<?= e($search) ?>"
                            placeholder="Search packages..."
                            aria-label="Search packages"
                        >

                        <button
                            type="submit"
                            aria-label="Search"
                        >
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </div>

                    <label class="manager-all-packages-filter-box">
                        <i class="fa-solid fa-filter"></i>

                        <select
                            name="sort"
                            aria-label="Sort packages"
                            onchange="this.form.submit()"
                        >
                            <option
                                value="latest"
                                <?= $sort === 'latest'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Filter
                            </option>

                            <option
                                value="price_low"
                                <?= $sort === 'price_low'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Price: Low to High
                            </option>

                            <option
                                value="price_high"
                                <?= $sort === 'price_high'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Price: High to Low
                            </option>

                            <option
                                value="capacity_high"
                                <?= $sort === 'capacity_high'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Highest Capacity
                            </option>
                        </select>
                    </label>

                    <?php if ($search !== '' || $sort !== 'latest'): ?>

                        <a
                            class="manager-all-packages-clear"
                            href="<?= e(url('/booking_manager/all_packages.php')) ?>"
                            aria-label="Clear search and filter"
                            title="Clear search and filter"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </a>

                    <?php endif; ?>
                </form>

                <a
                    class="manager-all-packages-back"
                    href="<?= e(url('/booking_manager/packages.php')) ?>"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Packages
                </a>

            </div>

        </section>

        <?php if ($packages === []): ?>

            <section class="manager-all-packages-empty">
                <i class="fa-solid fa-gift"></i>

                <h2>No packages found</h2>

                <p>
                    Try another search term or clear the current filter.
                </p>

                <a href="<?= e(url('/booking_manager/all_packages.php')) ?>">
                    Show All Packages
                </a>
            </section>

        <?php else: ?>

            <section class="manager-all-packages-grid">

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
                        $fullDescription =
                            'Complete wedding-event package.';
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
                        static fn (array $image): string =>
                            (string) $image['url'],
                        $previewImages
                    );
                    ?>

                    <article class="manager-all-package-card">

                        <div class="manager-all-package-main-wrap">

                            <img
                                class="manager-all-package-main-image"
                                id="managerAllPackageMain<?= e((string) $packageId) ?>"
                                src="<?= e($mainImage) ?>"
                                alt="<?= e((string) $package['name']) ?>"
                            >

                            <span class="manager-all-package-status">
                                Available
                            </span>

                            <span class="manager-all-package-main-badge">
                                <i class="fa-regular fa-image"></i>
                                Main Photo
                            </span>

                        </div>

                        <div class="manager-all-package-body">

                            <div class="manager-all-package-title-row">
                                <h2><?= e((string) $package['name']) ?></h2>

                                <strong>
                                    <?= e(
                                        format_package_price(
                                            (float) $package['price']
                                        )
                                    ) ?>
                                </strong>
                            </div>

                            <p class="manager-all-package-description">
                                <?= e(
                                    $shortDescription !== ''
                                        ? $shortDescription
                                        : package_card_description($package)
                                ) ?>
                            </p>

                            <div class="manager-all-package-thumbnails">

                                <?php foreach ($previewImages as $index => $previewImage): ?>

                                    <button
                                        class="manager-all-package-thumbnail <?= $index === 0
                                            ? 'active'
                                            : '' ?>"
                                        type="button"
                                        data-card-target="managerAllPackageMain<?= e((string) $packageId) ?>"
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

                            <div class="manager-all-package-mini-details">
                                <span>
                                    <i class="fa-solid fa-users"></i>

                                    <?= e(
                                        number_format(
                                            (int) (
                                                $package['guest_capacity']
                                                ?? 0
                                            )
                                        )
                                    ) ?>
                                    guests
                                </span>

                                <span>
                                    <i class="fa-solid fa-music"></i>
                                    <?= e($musicText) ?>
                                </span>
                            </div>

                            <div class="manager-all-package-actions">

                                <button
                                    class="manager-all-package-details"
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
                                    class="manager-all-package-book"
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

            </section>

        <?php endif; ?>

        <section class="manager-all-packages-pagination-row">

            <p>
                Showing <?= e(number_format($firstResult)) ?>
                to <?= e(number_format($lastResult)) ?>
                of <?= e(number_format($totalResults)) ?>
                package<?= $totalResults === 1 ? '' : 's' ?>
            </p>

            <nav aria-label="Package pages">

                <a
                    class="<?= $page <= 1 ? 'disabled' : '' ?>"
                    href="<?= $page <= 1
                        ? '#'
                        : e(
                            url(
                                '/booking_manager/all_packages.php'
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
                                '/booking_manager/all_packages.php'
                                . $buildPackagesQuery([
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
                                '/booking_manager/all_packages.php'
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

            <span>
                <?= e((string) $perPage) ?> per page
                <i class="fa-solid fa-chevron-down"></i>
            </span>

        </section>

        <footer class="manager-all-packages-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <div
        class="manager-all-package-modal"
        id="managerAllPackageModal"
        aria-hidden="true"
    >

        <div class="manager-all-package-modal-content">

            <button
                class="manager-all-package-modal-close"
                id="managerAllPackageModalClose"
                type="button"
                aria-label="Close package details"
            >
                &times;
            </button>

            <div class="manager-all-package-modal-grid">

                <div>

                    <div class="manager-all-package-modal-image-wrap">

                        <img
                            id="managerAllPackageModalMainImage"
                            src=""
                            alt="Package image"
                        >

                        <span id="managerAllPackageModalMainBadge">
                            <i class="fa-regular fa-image"></i>
                            Main Photo
                        </span>

                    </div>

                    <div id="managerAllPackageModalThumbnails"></div>

                </div>

                <div class="manager-all-package-modal-info">

                    <h2 id="managerAllPackageModalName"></h2>

                    <div id="managerAllPackageModalPrice"></div>

                    <p id="managerAllPackageModalDescription"></p>

                    <div class="manager-all-package-information-row">
                        <strong>Decoration:</strong>
                        <span id="managerAllPackageModalDecoration"></span>
                    </div>

                    <div class="manager-all-package-information-row">
                        <strong>Guest capacity:</strong>
                        <span id="managerAllPackageModalGuests"></span>
                    </div>

                    <div class="manager-all-package-information-row">
                        <strong>Music:</strong>
                        <span id="managerAllPackageModalMusic"></span>
                    </div>

                    <div class="manager-all-package-information-row">
                        <strong>Catering menu:</strong>
                        <span id="managerAllPackageModalCatering"></span>
                    </div>

                    <div class="manager-all-package-feature-box">
                        <h3>Additional Features</h3>
                        <ul id="managerAllPackageModalFeatures"></ul>
                    </div>

                    <a
                        id="managerAllPackageModalBook"
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

        document.addEventListener(
            "click",
            function (event) {
                const thumbnail = event.target.closest(
                    ".manager-all-package-thumbnail"
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
                    ".manager-all-package-card"
                );

                card
                    ?.querySelectorAll(
                        ".manager-all-package-thumbnail"
                    )
                    .forEach(function (item) {
                        item.classList.remove("active");
                    });

                thumbnail.classList.add("active");

                const badge = card?.querySelector(
                    ".manager-all-package-main-badge"
                );

                badge?.classList.toggle(
                    "hidden",
                    thumbnail.dataset.cardIsMain !== "true"
                );
            }
        );

        const modal = document.getElementById(
            "managerAllPackageModal"
        );

        const modalClose = document.getElementById(
            "managerAllPackageModalClose"
        );

        const modalMainImage = document.getElementById(
            "managerAllPackageModalMainImage"
        );

        const modalMainBadge = document.getElementById(
            "managerAllPackageModalMainBadge"
        );

        const modalThumbnails = document.getElementById(
            "managerAllPackageModalThumbnails"
        );

        const modalName = document.getElementById(
            "managerAllPackageModalName"
        );

        const modalPrice = document.getElementById(
            "managerAllPackageModalPrice"
        );

        const modalDescription = document.getElementById(
            "managerAllPackageModalDescription"
        );

        const modalDecoration = document.getElementById(
            "managerAllPackageModalDecoration"
        );

        const modalGuests = document.getElementById(
            "managerAllPackageModalGuests"
        );

        const modalMusic = document.getElementById(
            "managerAllPackageModalMusic"
        );

        const modalCatering = document.getElementById(
            "managerAllPackageModalCatering"
        );

        const modalFeatures = document.getElementById(
            "managerAllPackageModalFeatures"
        );

        const modalBook = document.getElementById(
            "managerAllPackageModalBook"
        );

        function renderModalImages(images) {
            modalThumbnails.innerHTML = "";

            images.forEach(function (imageUrl, index) {
                const button =
                    document.createElement("button");

                const image =
                    document.createElement("img");

                button.type = "button";

                button.className =
                    "manager-all-package-modal-thumbnail";

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
                        modalMainImage.src = imageUrl;

                        modalThumbnails
                            .querySelectorAll(
                                ".manager-all-package-modal-thumbnail"
                            )
                            .forEach(function (item) {
                                item.classList.remove("active");
                            });

                        button.classList.add("active");

                        modalMainBadge.classList.toggle(
                            "hidden",
                            index !== 0
                        );
                    }
                );

                modalThumbnails.appendChild(button);
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

                        modalName.textContent =
                            button.dataset.name || "Package";

                        modalPrice.textContent =
                            button.dataset.price || "";

                        modalDescription.textContent =
                            button.dataset.description || "";

                        modalDecoration.textContent =
                            button.dataset.decoration
                            || "Not specified";

                        modalGuests.textContent =
                            (button.dataset.guests || "0")
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
                                    '/booking_manager/booking.php?package_id='
                                )
                            ) ?>"
                            + (button.dataset.id || "");

                        modalFeatures.innerHTML = "";

                        const features =
                            button.dataset.features
                                ? button.dataset.features.split("||")
                                : [];

                        if (features.length === 0) {
                            const item =
                                document.createElement("li");

                            item.textContent =
                                "No additional features listed.";

                            modalFeatures.appendChild(item);
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

                                modalFeatures.appendChild(item);
                            });
                        }

                        if (images.length > 0) {
                            modalMainImage.src = images[0];

                            modalMainBadge.classList.remove(
                                "hidden"
                            );

                            renderModalImages(images);
                        }

                        modal.classList.add("open");

                        modal.setAttribute(
                            "aria-hidden",
                            "false"
                        );

                        document.body.classList.add(
                            "manager-all-package-modal-open"
                        );
                    }
                );
            });

        function closeModal() {
            modal.classList.remove("open");

            modal.setAttribute(
                "aria-hidden",
                "true"
            );

            document.body.classList.remove(
                "manager-all-package-modal-open"
            );
        }

        modalClose?.addEventListener(
            "click",
            closeModal
        );

        modal?.addEventListener(
            "click",
            function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (event.key === "Escape") {
                    closeModal();
                }
            }
        );
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>