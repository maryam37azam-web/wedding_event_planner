<?php

declare(strict_types=1);

require_once __DIR__
    . '/../config/database.php';

require_once __DIR__
    . '/package_helpers.php';

require_once __DIR__
    . '/venue_helpers.php';

require_once __DIR__
    . '/booking_availability_helpers.php';

if (
    !isset($detailType)
    || !in_array(
        $detailType,
        [
            'package',
            'venue',
        ],
        true
    )
) {
    http_response_code(404);

    exit(
        'Page not found.'
    );
}

$connection = db();

$entityId = max(
    0,
    (int) (
        $_GET['id']
        ?? $_POST['entity_id']
        ?? 0
    )
);

$entity =
    booking_active_entity(
        $connection,
        $detailType,
        $entityId
    );

if ($entity === null) {
    http_response_code(404);

    exit(
        'The selected package or venue is not available.'
    );
}

$errors = [];
$showLoginPrompt = false;

$selectedDate = '';
$selectedStartTime = '';
$selectedEndTime = '';

$isCustomerLoggedIn =
    isset(
        $_SESSION['user_id'],
        $_SESSION['user_role']
    )
    && $_SESSION['user_role']
        === 'customer';

/*
|--------------------------------------------------------------------------
| Confirm selected slot and continue to booking
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token']
        ?? ''
    );

    $selectedDate = trim(
        (string) (
            $_POST['selected_date']
            ?? ''
        )
    );

    $selectedStartTime = trim(
        (string) (
            $_POST['start_time']
            ?? ''
        )
    );

    $selectedEndTime = trim(
        (string) (
            $_POST['end_time']
            ?? ''
        )
    );

    if (
        !verify_csrf(
            $submittedToken
        )
    ) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

    if (
        normalize_booking_date(
            $selectedDate
        ) === null
    ) {
        $errors[] =
            'Choose a valid available date.';
    }

    if (
        normalize_booking_time(
            $selectedStartTime
        ) === null
        || normalize_booking_time(
            $selectedEndTime
        ) === null
    ) {
        $errors[] =
            'Choose a valid start and end time.';
    } elseif (
        $selectedEndTime
        <= $selectedStartTime
    ) {
        $errors[] =
            'End time must be later than start time.';
    }

    /*
     * Check the selected time again before
     * sending the customer to the booking form.
     */
    if (
        $errors === []
        && !booking_slot_is_available(
            $connection,
            $detailType,
            $entityId,
            $selectedDate,
            $selectedStartTime,
            $selectedEndTime
        )
    ) {
        $errors[] =
            'That date and time is no longer available. Please choose another slot.';
    }

    if ($errors === []) {
        $bookingPath =
            booking_form_url(
                $detailType,
                $entityId,
                $selectedDate,
                $selectedStartTime,
                $selectedEndTime
            );

        /*
         * Preserve the complete selection through
         * registration and customer login.
         */
        $_SESSION[
            'pending_booking_selection'
        ] = [
            'type' =>
                $detailType,

            'entity_id' =>
                $entityId,

            'event_date' =>
                $selectedDate,

            'start_time' =>
                $selectedStartTime,

            'end_time' =>
                $selectedEndTime,
        ];

        if ($isCustomerLoggedIn) {
            redirect(
                $bookingPath
            );
        }

        $_SESSION[
            'redirect_after_login'
        ] = $bookingPath;

        $showLoginPrompt = true;
    }
}

/*
|--------------------------------------------------------------------------
| Prepare package or venue details
|--------------------------------------------------------------------------
*/

$isPackage =
    $detailType === 'package';

$entityName = (string) (
    $entity['name']
    ?? ''
);

$entityPrice = (float) (
    $entity['price']
    ?? 0
);

$entityDescription = trim(
    (string) (
        $entity['description']
        ?? ''
    )
);

if (
    $entityDescription === ''
    && $isPackage
) {
    $entityDescription = trim(
        (string) (
            $entity[
                'short_description'
            ]
            ?? ''
        )
    );
}

if ($entityDescription === '') {
    $entityDescription =
        $isPackage
            ? 'A complete wedding package prepared for a memorable celebration.'
            : 'A professional wedding venue prepared for your special event.';
}

$entityLocation =
    $isPackage
        ? trim(
            (string) (
                $entity[
                    'venue_location'
                ]
                ?? ''
            )
        )
        : trim(
            (string) (
                $entity['location']
                ?? ''
            )
        );

$entityCapacity = (int) (
    $isPackage
        ? (
            $entity[
                'guest_capacity'
            ]
            ?? 0
        )
        : (
            $entity['capacity']
            ?? 0
        )
);

$mainImage =
    $isPackage
        ? package_image_url(
            $entity['main_image']
            ?? null
        )
        : venue_image_url(
            $entity['main_image']
            ?? null
        );

$imageResolver =
    $isPackage
        ? 'package_image_url'
        : 'venue_image_url';

$imageFields = [
    'main_image',
    'image_one',
    'image_two',
    'image_three',
    'image_four',
];

$galleryImages = [];

foreach (
    $imageFields as $imageField
) {
    $imagePath = trim(
        (string) (
            $entity[$imageField]
            ?? ''
        )
    );

    if ($imagePath === '') {
        continue;
    }

    $resolvedImage =
        $imageResolver(
            $imagePath
        );

    if (
        !in_array(
            $resolvedImage,
            $galleryImages,
            true
        )
    ) {
        $galleryImages[] =
            $resolvedImage;
    }
}

if ($galleryImages === []) {
    $galleryImages[] =
        $mainImage;
}

$detailGroups = [];

if ($isPackage) {
    $cateringMenu = trim(
        (string) (
            $entity[
                'catering_menu'
            ]
            ?? ''
        )
    );

    $decorationType = trim(
        (string) (
            $entity[
                'decoration_type'
            ]
            ?? ''
        )
    );

    $features =
        package_feature_lines(
            $entity['features']
            ?? null
        );

    $music = [];

    if (
        (int) (
            $entity[
                'basic_music'
            ]
            ?? 0
        ) === 1
    ) {
        $music[] =
            'Basic Music';
    }

    if (
        (int) (
            $entity[
                'live_music'
            ]
            ?? 0
        ) === 1
    ) {
        $music[] =
            'Live Music';
    }

    $detailGroups[] = [
        'title' =>
            'Package Overview',

        'items' =>
            array_values(
                array_filter([
                    $entityCapacity > 0
                        ? 'Guest capacity up to '
                            . number_format(
                                $entityCapacity
                            )
                            . ' people'
                        : null,

                    $entityLocation !== ''
                        ? 'Event location: '
                            . $entityLocation
                        : null,

                    $decorationType !== ''
                        ? 'Decoration: '
                            . $decorationType
                        : 'Wedding decoration included',
                ])
            ),

        'icon' =>
            'fa-gift',
    ];

    $detailGroups[] = [
        'title' =>
            'Catering Specifications',

        'items' =>
            $cateringMenu !== ''
                ? (
                    preg_split(
                        '/\r\n|\r|\n|,/',
                        $cateringMenu
                    )
                    ?: []
                )
                : [
                    'Catering details will be confirmed during booking.',
                ],

        'icon' =>
            'fa-utensils',
    ];

    $detailGroups[] = [
        'title' =>
            'Music & Additional Features',

        'items' =>
            array_values(
                array_filter([
                    $music !== []
                        ? implode(
                            ' and ',
                            $music
                        )
                        : 'Music selection available during booking',

                    ...$features,
                ])
            ),

        'icon' =>
            'fa-music',
    ];
} else {
    $facilities =
        venue_facility_lines(
            $entity['facilities']
            ?? null
        );

    $detailGroups[] = [
        'title' =>
            'Venue Overview',

        'items' =>
            array_values(
                array_filter([
                    $entityLocation !== ''
                        ? 'Location: '
                            . $entityLocation
                        : null,

                    $entityCapacity > 0
                        ? 'Capacity: '
                            . number_format(
                                $entityCapacity
                            )
                            . ' guests'
                        : null,

                    'Venue price includes standard decoration.',
                ])
            ),

        'icon' =>
            'fa-hotel',
    ];

    $detailGroups[] = [
        'title' =>
            'Venue Facilities',

        'items' =>
            $facilities !== []
                ? $facilities
                : [
                    'Professional wedding-event facilities available.',
                ],

        'icon' =>
            'fa-circle-check',
    ];
}

$months =
    booking_allowed_months();

$accountPath =
    $isCustomerLoggedIn
        ? '/customer/dashboard.php'
        : '/auth/customer_login.php';

$accountLabel =
    $isCustomerLoggedIn
        ? 'My Account'
        : 'Login';

$pageTitle =
    (
        $isPackage
            ? 'Package Details'
            : 'Venue Details'
    )
    . ' | '
    . APP_NAME;
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
        <?= e($pageTitle) ?>
    </title>

    <?php require __DIR__ . '/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Poppins:wght@400;500;600;700&display=swap"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/public_booking_details.css'
            )
        ) ?>"
    >
</head>

<body
    class="public-booking-detail-page"
    data-login-prompt="<?= $showLoginPrompt
        ? 'true'
        : 'false' ?>"
>

    <header class="booking-detail-navbar">

        <a
            class="booking-detail-brand"
            href="<?= e(
                url('/')
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

            <span>
                <strong>
                    Wedding Planner
                </strong>

                <small>
                    Perfect events, beautiful memories
                </small>
            </span>
        </a>

        <nav>
            <a href="<?= e(
                url('/#packages')
            ) ?>">
                Packages
            </a>

            <a href="<?= e(
                url('/#venues')
            ) ?>">
                Venues
            </a>

            <a href="<?= e(
                url('/#services')
            ) ?>">
                Services
            </a>
        </nav>

        <a
            class="booking-detail-account"
            href="<?= e(
                url($accountPath)
            ) ?>"
        >
            <i class="fa-regular fa-user"></i>
            <?= e($accountLabel) ?>
        </a>

    </header>

    <main class="booking-detail-layout">

        <section class="booking-detail-showcase">

            <div class="booking-detail-featured-image">

                <img
                    id="bookingDetailFeaturedImage"
                    src="<?= e($mainImage) ?>"
                    alt="<?= e($entityName) ?>"
                >

                <span>
                    <i class="fa-regular fa-image"></i>

                    <?= $isPackage
                        ? 'Package Gallery'
                        : 'Venue Gallery' ?>
                </span>

            </div>

            <div class="booking-detail-thumbnails">

                <?php foreach (
                    $galleryImages
                    as $imageIndex
                    => $galleryImage
                ): ?>

                    <button
                        class="booking-detail-thumbnail<?= $imageIndex === 0
                            ? ' active'
                            : '' ?>"
                        type="button"
                        data-booking-detail-image="<?= e(
                            $galleryImage
                        ) ?>"
                        aria-label="Show image <?= e(
                            (string) (
                                $imageIndex + 1
                            )
                        ) ?>"
                    >
                        <img
                            src="<?= e(
                                $galleryImage
                            ) ?>"
                            alt="<?= e(
                                $entityName
                                . ' image '
                                . (
                                    $imageIndex
                                    + 1
                                )
                            ) ?>"
                        >
                    </button>

                <?php endforeach; ?>

            </div>

            <span class="booking-detail-tag">
                <?= $isPackage
                    ? 'Premium Wedding Package'
                    : 'Wedding Venue' ?>
            </span>

            <h1>
                <?= e($entityName) ?>
            </h1>

            <?php if (
                $entityLocation !== ''
            ): ?>

                <p class="booking-detail-location">
                    <i class="fa-solid fa-location-dot"></i>
                    <?= e($entityLocation) ?>
                </p>

            <?php endif; ?>

            <div class="booking-detail-price">

                <?= e(
                    $isPackage
                        ? format_package_price(
                            $entityPrice
                        )
                        : format_venue_price(
                            $entityPrice
                        )
                ) ?>

                <?php if (!$isPackage): ?>
                    <small>
                        per event day
                    </small>
                <?php endif; ?>

            </div>

            <p class="booking-detail-description">
                <?= e(
                    $entityDescription
                ) ?>
            </p>

            <?php foreach (
                $detailGroups
                as $detailGroup
            ): ?>

                <section
                    class="booking-detail-information-group"
                >

                    <h2>
                        <i
                            class="fa-solid <?= e(
                                (string) $detailGroup[
                                    'icon'
                                ]
                            ) ?>"
                        ></i>

                        <?= e(
                            (string) $detailGroup[
                                'title'
                            ]
                        ) ?>
                    </h2>

                    <div
                        class="booking-detail-feature-list"
                    >

                        <?php foreach (
                            $detailGroup['items']
                            as $detailItem
                        ): ?>

                            <?php if (
                                trim(
                                    (string) $detailItem
                                ) !== ''
                            ): ?>

                                <div>
                                    <i class="fa-solid fa-check"></i>

                                    <span>
                                        <?= e(
                                            trim(
                                                (string) $detailItem
                                            )
                                        ) ?>
                                    </span>
                                </div>

                            <?php endif; ?>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endforeach; ?>

        </section>

        <aside class="booking-availability-card">

            <div class="booking-availability-heading">

                <span>
                    <i class="fa-regular fa-calendar-check"></i>
                </span>

                <div>
                    <small>
                        Real-time availability
                    </small>

                    <h2>
                        Choose Your Event Schedule
                    </h2>

                    <p>
                        Select a month and time range to
                        see every available date.
                    </p>
                </div>

            </div>

            <?php if (
                $errors !== []
            ): ?>

                <div
                    class="booking-detail-alert booking-detail-alert-danger"
                >

                    <?php foreach (
                        $errors as $error
                    ): ?>

                        <p>
                            <?= e($error) ?>
                        </p>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <form
                class="booking-availability-search-form"
                id="bookingAvailabilitySearchForm"
                data-availability-url="<?= e(
                    url(
                        '/availability.php'
                    )
                ) ?>"
                data-entity-type="<?= e(
                    $detailType
                ) ?>"
                data-entity-id="<?= e(
                    (string) $entityId
                ) ?>"
            >

                <div class="booking-detail-form-group">

                    <label for="bookingMonth">
                        Choose Month
                    </label>

                    <select
                        id="bookingMonth"
                        name="month"
                        required
                    >
                        <option value="">
                            -- Select Month --
                        </option>

                        <?php foreach (
                            $months as $month
                        ): ?>

                            <option
                                value="<?= e(
                                    (string) $month[
                                        'value'
                                    ]
                                ) ?>"
                            >
                                <?= e(
                                    (string) $month[
                                        'label'
                                    ]
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="booking-detail-time-grid">

                    <div class="booking-detail-form-group">

                        <label for="bookingStartTime">
                            Start Time
                        </label>

                        <input
                            type="time"
                            id="bookingStartTime"
                            name="start_time"
                            required
                        >

                    </div>

                    <div class="booking-detail-form-group">

                        <label for="bookingEndTime">
                            End Time
                        </label>

                        <input
                            type="time"
                            id="bookingEndTime"
                            name="end_time"
                            required
                        >

                    </div>

                </div>

                <button
                    class="booking-availability-search-button"
                    type="submit"
                >
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Search Available Dates
                </button>

            </form>

            <div
                class="booking-availability-feedback"
                id="bookingAvailabilityFeedback"
                hidden
            ></div>

            <section
                class="booking-availability-results"
                id="bookingAvailabilityResults"
                hidden
            >

                <div
                    class="booking-availability-results-heading"
                >

                    <div>
                        <small>
                            Calendar status
                        </small>

                        <h3
                            id="bookingAvailabilityMonthLabel"
                        >
                            Available Dates
                        </h3>
                    </div>

                    <span
                        id="bookingAvailabilityTimeLabel"
                    ></span>

                </div>

                <div class="booking-availability-legend">

                    <span>
                        <i class="available"></i>
                        Available
                    </span>

                    <span>
                        <i class="booked"></i>
                        Booked / unavailable
                    </span>

                </div>

                <div
                    class="booking-availability-dates-grid"
                    id="bookingAvailabilityDatesGrid"
                ></div>

            </section>

            <form
                method="post"
                class="booking-slot-confirm-form"
                id="bookingSlotConfirmForm"
                hidden
            >
                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="entity_id"
                    value="<?= e(
                        (string) $entityId
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="selected_date"
                    id="bookingSelectedDate"
                    value="<?= e(
                        $selectedDate
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="start_time"
                    id="bookingSelectedStartTime"
                    value="<?= e(
                        $selectedStartTime
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="end_time"
                    id="bookingSelectedEndTime"
                    value="<?= e(
                        $selectedEndTime
                    ) ?>"
                >

                <div
                    class="booking-selected-slot-summary"
                >
                    <i class="fa-solid fa-circle-check"></i>

                    <div>
                        <small>
                            Selected schedule
                        </small>

                        <strong
                            id="bookingSelectedSlotText"
                        ></strong>
                    </div>
                </div>

                <button
                    class="booking-confirm-button"
                    type="submit"
                >
                    Continue to Booking Form
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </form>

            <div class="booking-availability-note">

                <i class="fa-solid fa-shield-heart"></i>

                <p>
                    Pending and confirmed bookings reserve
                    their selected time. Cancelled bookings
                    do not block availability.
                </p>

            </div>

        </aside>

    </main>

    <div
        class="booking-login-modal"
        id="bookingLoginModal"
        aria-hidden="<?= $showLoginPrompt
            ? 'false'
            : 'true' ?>"
    >

        <div
            class="booking-login-modal-backdrop"
            data-close-booking-login
        ></div>

        <section
            class="booking-login-modal-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="bookingLoginModalTitle"
        >

            <button
                class="booking-login-modal-close"
                type="button"
                data-close-booking-login
                aria-label="Close login message"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="booking-login-modal-icon">
                <i class="fa-solid fa-user-lock"></i>
            </div>

            <h2 id="bookingLoginModalTitle">
                Login Required for Booking
            </h2>

            <p>
                Your selected date and time have been
                saved. Please log in with your customer
                account, or create an account first, to
                continue automatically to the booking form.
            </p>

            <div class="booking-login-modal-actions">

                <a
                    class="primary"
                    href="<?= e(
                        url(
                            '/auth/customer_login.php'
                        )
                    ) ?>"
                >
                    Login to Continue
                </a>

                <a
                    class="secondary"
                    href="<?= e(
                        url(
                            '/auth/register.php'
                        )
                    ) ?>"
                >
                    Create Account
                </a>

            </div>

            <small>
                Registration does not log you in
                automatically. After creating the account,
                log in with the same credentials to continue.
            </small>

        </section>

    </div>

    <script
        src="<?= e(
            url(
                '/assets/js/public_booking_details.js'
            )
        ) ?>"
        defer
    ></script>

    <?php require __DIR__ . '/pwa_scripts.php'; ?>

</body>
</html>