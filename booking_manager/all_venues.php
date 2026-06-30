<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/venue_helpers.php';

require_role('booking_manager');

$connection = db();
$bookingManagerId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Venue helpers
|--------------------------------------------------------------------------
*/

$venuePrice = static function (array $venue): float {
    foreach (
        [
            'price',
            'starting_price',
            'rental_price',
            'venue_price',
        ] as $column
    ) {
        if (
            isset($venue[$column])
            && is_numeric($venue[$column])
        ) {
            return (float) $venue[$column];
        }
    }

    return 0;
};

$venueCapacity = static function (array $venue): int {
    foreach (
        [
            'capacity',
            'guest_capacity',
            'maximum_capacity',
            'max_capacity',
        ] as $column
    ) {
        if (
            isset($venue[$column])
            && is_numeric($venue[$column])
        ) {
            return (int) $venue[$column];
        }
    }

    return 0;
};

$venueFacilities = static function (
    mixed $rawFacilities
): array {
    if (is_array($rawFacilities)) {
        $facilities = $rawFacilities;
    } else {
        $facilityText = trim(
            (string) $rawFacilities
        );

        if ($facilityText === '') {
            return [];
        }

        $decodedFacilities = json_decode(
            $facilityText,
            true
        );

        if (
            json_last_error() === JSON_ERROR_NONE
            && is_array($decodedFacilities)
        ) {
            $facilities = $decodedFacilities;
        } else {
            $facilities = preg_split(
                '/[\r\n,;|]+/u',
                $facilityText
            ) ?: [];
        }
    }

    $cleanFacilities = [];

    foreach ($facilities as $facility) {
        $facility = trim(
            (string) $facility
        );

        if ($facility !== '') {
            $cleanFacilities[] = $facility;
        }
    }

    return array_values(
        array_unique($cleanFacilities)
    );
};

$formatVenuePrice = static function (
    float $price
): string {
    return 'Rs. ' . number_format(
        $price,
        0
    );
};

/*
|--------------------------------------------------------------------------
| Load Booking Manager
|--------------------------------------------------------------------------
*/

$managerStatement = $connection->prepare(
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

/*
|--------------------------------------------------------------------------
| Search, sorting and pagination
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) ($_GET['q'] ?? '')
);

$sort = strtolower(
    trim(
        (string) ($_GET['sort'] ?? 'latest')
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

$allowedSorts = [
    'latest',
    'price_low',
    'price_high',
    'capacity_high',
];

if (
    !in_array(
        $sort,
        $allowedSorts,
        true
    )
) {
    $sort = 'latest';
}

$buildVenueQuery = static function (
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

    if (
        trim(
            (string) $parameters['q']
        ) === ''
    ) {
        unset($parameters['q']);
    }

    if (
        (string) $parameters['sort']
        === 'latest'
    ) {
        unset($parameters['sort']);
    }

    if (
        (int) $parameters['page']
        <= 1
    ) {
        unset($parameters['page']);
    }

    return $parameters === []
        ? ''
        : '?'
            . http_build_query(
                $parameters
            );
};

/*
|--------------------------------------------------------------------------
| Load all active venues
|--------------------------------------------------------------------------
*/

$allVenueStatement = $connection->query(
    "SELECT *
     FROM venues
     WHERE status = 'active'
     ORDER BY created_at DESC, id DESC"
);

$filteredVenues =
    $allVenueStatement->fetchAll();

if ($search !== '') {
    $searchValue = mb_strtolower(
        $search
    );

    $filteredVenues = array_values(
        array_filter(
            $filteredVenues,
            static function (
                array $venue
            ) use (
                $searchValue,
                $venueFacilities
            ): bool {
                $searchableValues = [
                    (string) (
                        $venue['name']
                        ?? ''
                    ),

                    (string) (
                        $venue['location']
                        ?? ''
                    ),

                    (string) (
                        $venue['description']
                        ?? ''
                    ),

                    implode(
                        ' ',
                        $venueFacilities(
                            $venue['facilities']
                            ?? ''
                        )
                    ),
                ];

                $searchableText =
                    mb_strtolower(
                        implode(
                            ' ',
                            $searchableValues
                        )
                    );

                return str_contains(
                    $searchableText,
                    $searchValue
                );
            }
        )
    );
}

usort(
    $filteredVenues,
    static function (
        array $firstVenue,
        array $secondVenue
    ) use (
        $sort,
        $venuePrice,
        $venueCapacity
    ): int {
        if ($sort === 'price_low') {
            return $venuePrice($firstVenue)
                <=> $venuePrice($secondVenue);
        }

        if ($sort === 'price_high') {
            return $venuePrice($secondVenue)
                <=> $venuePrice($firstVenue);
        }

        if ($sort === 'capacity_high') {
            return $venueCapacity($secondVenue)
                <=> $venueCapacity($firstVenue);
        }

        $firstCreated = strtotime(
            (string) (
                $firstVenue['created_at']
                ?? ''
            )
        ) ?: 0;

        $secondCreated = strtotime(
            (string) (
                $secondVenue['created_at']
                ?? ''
            )
        ) ?: 0;

        if ($firstCreated === $secondCreated) {
            return (int) (
                $secondVenue['id']
                ?? 0
            ) <=> (int) (
                $firstVenue['id']
                ?? 0
            );
        }

        return $secondCreated
            <=> $firstCreated;
    }
);

$totalResults = count(
    $filteredVenues
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

$venues = array_slice(
    $filteredVenues,
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
        href="<?= e(
            url('/assets/css/booking_manager_all_venues.css')
        ) ?>"
    >
</head>

<body class="manager-all-venues-page">

    <header class="manager-all-venues-header">

        <a
            class="manager-all-venues-brand"
            href="<?= e(
                url('/booking_manager/dashboard.php')
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
            class="manager-all-venues-user"
            href="<?= e(
                url('/booking_manager/profile.php')
            ) ?>"
            aria-label="Open Booking Manager profile"
        >

            <div>

                <strong>
                    <?= e(
                        (string) $manager['full_name']
                    ) ?>
                </strong>

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

    <main class="manager-all-venues-shell">

        <section class="manager-all-venues-page-head">

            <div class="manager-all-venues-heading-copy">

                <h1>View All Venues</h1>

                <p>
                    Search available wedding venues and create customer bookings.
                </p>

            </div>

            <div class="manager-all-venues-heading-controls">

                <form
                    class="manager-all-venues-search-form"
                    method="get"
                >

                    <div class="manager-all-venues-search-box">

                        <input
                            type="search"
                            name="q"
                            value="<?= e($search) ?>"
                            placeholder="Search venues..."
                            aria-label="Search venues"
                        >

                        <button
                            type="submit"
                            aria-label="Search"
                        >
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>

                    </div>

                    <label class="manager-all-venues-filter-box">

                        <i class="fa-solid fa-filter"></i>

                        <select
                            name="sort"
                            aria-label="Sort venues"
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

                    <?php if (
                        $search !== ''
                        || $sort !== 'latest'
                    ): ?>

                        <a
                            class="manager-all-venues-clear"
                            href="<?= e(
                                url('/booking_manager/all_venues.php')
                            ) ?>"
                            title="Clear search and filter"
                            aria-label="Clear search and filter"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </a>

                    <?php endif; ?>

                </form>

                <a
                    class="manager-all-venues-back"
                    href="<?= e(
                        url('/booking_manager/venues.php')
                    ) ?>"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Venues
                </a>

            </div>

        </section>

        <?php if ($venues === []): ?>

            <section class="manager-all-venues-empty">

                <i class="fa-solid fa-hotel"></i>

                <h2>No venues found</h2>

                <p>
                    Try another search term or clear the current filter.
                </p>

                <a href="<?= e(
                    url('/booking_manager/all_venues.php')
                ) ?>">
                    Show All Venues
                </a>

            </section>

        <?php else: ?>

            <section class="manager-all-venues-grid">

                <?php foreach ($venues as $venue): ?>
                    <?php
                    $venueId =
                        (int) $venue['id'];

                    $mainImage =
                        venue_image_url(
                            $venue['main_image']
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
                                $venue[$imageColumn]
                                ?? ''
                            )
                        );

                        if ($imagePath === '') {
                            continue;
                        }

                        $previewImages[] = [
                            'url' =>
                                venue_image_url(
                                    $imagePath
                                ),

                            'is_main' => false,
                        ];
                    }

                    $venueName = trim(
                        (string) (
                            $venue['name']
                            ?? ''
                        )
                    );

                    if ($venueName === '') {
                        $venueName =
                            'Untitled Venue';
                    }

                    $location = trim(
                        (string) (
                            $venue['location']
                            ?? ''
                        )
                    );

                    if ($location === '') {
                        $location =
                            'Location not specified';
                    }

                    $description = trim(
                        (string) (
                            $venue['description']
                            ?? ''
                        )
                    );

                    if ($description === '') {
                        $description =
                            venue_card_description(
                                $venue
                            );
                    }

                    if ($description === '') {
                        $description =
                            'Wedding venue available for customer bookings.';
                    }

                    $price =
                        $venuePrice($venue);

                    $capacity =
                        $venueCapacity($venue);

                    $facilities =
                        $venueFacilities(
                            $venue['facilities']
                            ?? ''
                        );

                    $facilityText =
                        $facilities !== []
                            ? implode(
                                '||',
                                $facilities
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

                    <article class="manager-all-venue-card">

                        <div class="manager-all-venue-main-wrap">

                            <img
                                class="manager-all-venue-main-image"
                                id="managerAllVenueMain<?= e(
                                    (string) $venueId
                                ) ?>"
                                src="<?= e($mainImage) ?>"
                                alt="<?= e($venueName) ?>"
                            >

                            <span class="manager-all-venue-status">
                                Available
                            </span>

                            <span class="manager-all-venue-main-badge">

                                <i class="fa-regular fa-image"></i>
                                Main Photo

                            </span>

                        </div>

                        <div class="manager-all-venue-body">

                            <h2>
                                <?= e($venueName) ?>
                            </h2>

                            <div class="manager-all-venue-location">

                                <i class="fa-solid fa-location-dot"></i>

                                <?= e($location) ?>

                            </div>

                            <strong class="manager-all-venue-price">

                                <?= e(
                                    $formatVenuePrice(
                                        $price
                                    )
                                ) ?>

                            </strong>

                            <p class="manager-all-venue-description">
                                <?= e($description) ?>
                            </p>

                            <div class="manager-all-venue-thumbnails">

                                <?php foreach (
                                    $previewImages as
                                    $index => $previewImage
                                ): ?>

                                    <button
                                        class="manager-all-venue-thumbnail <?= $index === 0
                                            ? 'active'
                                            : '' ?>"
                                        type="button"
                                        data-venue-target="managerAllVenueMain<?= e(
                                            (string) $venueId
                                        ) ?>"
                                        data-venue-image="<?= e(
                                            (string) $previewImage['url']
                                        ) ?>"
                                        data-venue-is-main="<?= $previewImage['is_main']
                                            ? 'true'
                                            : 'false' ?>"
                                        aria-label="<?= $previewImage['is_main']
                                            ? 'Show original main venue photo'
                                            : 'Show venue gallery photo '
                                                . e((string) $index) ?>"
                                    >

                                        <img
                                            src="<?= e(
                                                (string) $previewImage['url']
                                            ) ?>"
                                            alt="<?= $previewImage['is_main']
                                                ? 'Original main venue photo'
                                                : 'Venue gallery photo '
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

                            <div class="manager-all-venue-mini-details">

                                <span>

                                    <i class="fa-solid fa-users"></i>

                                    <?= e(
                                        number_format(
                                            $capacity
                                        )
                                    ) ?>
                                    guests

                                </span>

                                <span>

                                    <i class="fa-solid fa-calendar-check"></i>

                                    Date-based availability

                                </span>

                            </div>

                            <div class="manager-all-venue-actions">

                                <button
                                    class="manager-all-venue-details"
                                    type="button"
                                    data-venue-details
                                    data-id="<?= e(
                                        (string) $venueId
                                    ) ?>"
                                    data-name="<?= e(
                                        $venueName
                                    ) ?>"
                                    data-location="<?= e(
                                        $location
                                    ) ?>"
                                    data-price="<?= e(
                                        $formatVenuePrice(
                                            $price
                                        )
                                    ) ?>"
                                    data-capacity="<?= e(
                                        number_format(
                                            $capacity
                                        )
                                    ) ?>"
                                    data-description="<?= e(
                                        $description
                                    ) ?>"
                                    data-facilities="<?= e(
                                        $facilityText
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
                                    class="manager-all-venue-book"
                                    href="<?= e(
                                        url(
                                            '/booking_manager/booking.php?venue_id='
                                            . $venueId
                                        )
                                    ) ?>"
                                >
                                    Book Venue
                                </a>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </section>

        <?php endif; ?>

        <section class="manager-all-venues-pagination-row">

            <p>

                Showing <?= e(
                    number_format(
                        $firstResult
                    )
                ) ?>

                to <?= e(
                    number_format(
                        $lastResult
                    )
                ) ?>

                of <?= e(
                    number_format(
                        $totalResults
                    )
                ) ?>

                venue<?= $totalResults === 1
                    ? ''
                    : 's' ?>

            </p>

            <nav aria-label="Venue pages">

                <a
                    class="<?= $page <= 1
                        ? 'disabled'
                        : '' ?>"
                    href="<?= $page <= 1
                        ? '#'
                        : e(
                            url(
                                '/booking_manager/all_venues.php'
                                . $buildVenueQuery([
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
                                '/booking_manager/all_venues.php'
                                . $buildVenueQuery([
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
                                '/booking_manager/all_venues.php'
                                . $buildVenueQuery([
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
                ) ?> per page

                <i class="fa-solid fa-chevron-down"></i>

            </span>

        </section>

        <footer class="manager-all-venues-footer">

            © <?= e(
                (string) $currentYear
            ) ?>

            Wedding Event Planner.
            All rights reserved.

        </footer>

    </main>

    <div
        class="manager-all-venue-modal"
        id="managerAllVenueModal"
        aria-hidden="true"
    >

        <div class="manager-all-venue-modal-content">

            <button
                class="manager-all-venue-modal-close"
                id="managerAllVenueModalClose"
                type="button"
                aria-label="Close venue details"
            >
                &times;
            </button>

            <div class="manager-all-venue-modal-grid">

                <div>

                    <div class="manager-all-venue-modal-image-wrap">

                        <img
                            id="managerAllVenueModalMainImage"
                            src=""
                            alt="Venue image"
                        >

                        <span id="managerAllVenueModalMainBadge">

                            <i class="fa-regular fa-image"></i>
                            Main Photo

                        </span>

                    </div>

                    <div id="managerAllVenueModalThumbnails"></div>

                </div>

                <div class="manager-all-venue-modal-info">

                    <h2 id="managerAllVenueModalName"></h2>

                    <div id="managerAllVenueModalPrice"></div>

                    <p id="managerAllVenueModalDescription"></p>

                    <div class="manager-all-venue-information-row">

                        <strong>Location:</strong>

                        <span id="managerAllVenueModalLocation"></span>

                    </div>

                    <div class="manager-all-venue-information-row">

                        <strong>Capacity:</strong>

                        <span id="managerAllVenueModalCapacity"></span>

                    </div>

                    <div class="manager-all-venue-information-row">

                        <strong>Availability:</strong>

                        <span>
                            Date-based availability check
                        </span>

                    </div>

                    <div class="manager-all-venue-facilities-box">

                        <h3>Venue Facilities</h3>

                        <ul id="managerAllVenueModalFacilities"></ul>

                    </div>

                    <a
                        id="managerAllVenueModalBook"
                        href="#"
                    >
                        Book This Venue
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
                    ".manager-all-venue-thumbnail"
                );

                if (!thumbnail) {
                    return;
                }

                const mainImage = document.getElementById(
                    thumbnail.dataset.venueTarget
                    || ""
                );

                if (
                    !mainImage
                    || !thumbnail.dataset.venueImage
                ) {
                    return;
                }

                mainImage.src =
                    thumbnail.dataset.venueImage;

                const card = thumbnail.closest(
                    ".manager-all-venue-card"
                );

                card
                    ?.querySelectorAll(
                        ".manager-all-venue-thumbnail"
                    )
                    .forEach(function (item) {
                        item.classList.remove(
                            "active"
                        );
                    });

                thumbnail.classList.add(
                    "active"
                );

                const badge = card?.querySelector(
                    ".manager-all-venue-main-badge"
                );

                badge?.classList.toggle(
                    "hidden",
                    thumbnail.dataset.venueIsMain
                        !== "true"
                );
            }
        );

        const venueModal =
            document.getElementById(
                "managerAllVenueModal"
            );

        const venueModalClose =
            document.getElementById(
                "managerAllVenueModalClose"
            );

        const venueModalMainImage =
            document.getElementById(
                "managerAllVenueModalMainImage"
            );

        const venueModalMainBadge =
            document.getElementById(
                "managerAllVenueModalMainBadge"
            );

        const venueModalThumbnails =
            document.getElementById(
                "managerAllVenueModalThumbnails"
            );

        const venueModalName =
            document.getElementById(
                "managerAllVenueModalName"
            );

        const venueModalPrice =
            document.getElementById(
                "managerAllVenueModalPrice"
            );

        const venueModalDescription =
            document.getElementById(
                "managerAllVenueModalDescription"
            );

        const venueModalLocation =
            document.getElementById(
                "managerAllVenueModalLocation"
            );

        const venueModalCapacity =
            document.getElementById(
                "managerAllVenueModalCapacity"
            );

        const venueModalFacilities =
            document.getElementById(
                "managerAllVenueModalFacilities"
            );

        const venueModalBook =
            document.getElementById(
                "managerAllVenueModalBook"
            );

        function renderVenueImages(images) {
            venueModalThumbnails.innerHTML = "";

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
                        "manager-all-venue-modal-thumbnail";

                    if (index === 0) {
                        button.classList.add(
                            "active"
                        );
                    }

                    image.src = imageUrl;

                    image.alt = index === 0
                        ? "Original main venue photo"
                        : "Venue gallery photo "
                            + index;

                    button.appendChild(
                        image
                    );

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
                            venueModalMainImage.src =
                                imageUrl;

                            venueModalThumbnails
                                .querySelectorAll(
                                    ".manager-all-venue-modal-thumbnail"
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

                            venueModalMainBadge.classList.toggle(
                                "hidden",
                                index !== 0
                            );
                        }
                    );

                    venueModalThumbnails.appendChild(
                        button
                    );
                }
            );
        }

        document
            .querySelectorAll(
                "[data-venue-details]"
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

                        venueModalName.textContent =
                            button.dataset.name
                            || "Venue";

                        venueModalPrice.textContent =
                            button.dataset.price
                            || "";

                        venueModalDescription.textContent =
                            button.dataset.description
                            || "";

                        venueModalLocation.textContent =
                            button.dataset.location
                            || "Not specified";

                        venueModalCapacity.textContent =
                            (
                                button.dataset.capacity
                                || "0"
                            )
                            + " guests";

                        venueModalBook.href =
                            "<?= e(
                                url(
                                    '/booking_manager/booking.php?venue_id='
                                )
                            ) ?>"
                            + (
                                button.dataset.id
                                || ""
                            );

                        venueModalFacilities.innerHTML =
                            "";

                        const facilities =
                            button.dataset.facilities
                                ? button.dataset.facilities.split(
                                    "||"
                                )
                                : [];

                        if (facilities.length === 0) {
                            const item =
                                document.createElement(
                                    "li"
                                );

                            item.textContent =
                                "No facilities have been listed.";

                            venueModalFacilities.appendChild(
                                item
                            );
                        } else {
                            facilities.forEach(
                                function (facility) {
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
                                        facility;

                                    item.append(
                                        icon,
                                        text
                                    );

                                    venueModalFacilities.appendChild(
                                        item
                                    );
                                }
                            );
                        }

                        if (images.length > 0) {
                            venueModalMainImage.src =
                                images[0];

                            venueModalMainBadge.classList.remove(
                                "hidden"
                            );

                            renderVenueImages(
                                images
                            );
                        }

                        venueModal.classList.add(
                            "open"
                        );

                        venueModal.setAttribute(
                            "aria-hidden",
                            "false"
                        );

                        document.body.classList.add(
                            "manager-all-venue-modal-open"
                        );
                    }
                );
            });

        function closeVenueModal() {
            venueModal.classList.remove(
                "open"
            );

            venueModal.setAttribute(
                "aria-hidden",
                "true"
            );

            document.body.classList.remove(
                "manager-all-venue-modal-open"
            );
        }

        venueModalClose?.addEventListener(
            "click",
            closeVenueModal
        );

        venueModal?.addEventListener(
            "click",
            function (event) {
                if (event.target === venueModal) {
                    closeVenueModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (event.key === "Escape") {
                    closeVenueModal();
                }
            }
        );
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>