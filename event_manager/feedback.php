<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('event_manager');

$connection = db();
$managerId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function event_feedback_date(
    mixed $date,
    string $format = 'd F Y'
): string {
    $timestamp = strtotime((string) $date);

    if ($timestamp === false) {
        return 'Date unavailable';
    }

    return date($format, $timestamp);
}

function event_feedback_time(
    mixed $time
): string {
    $time = trim((string) $time);

    if ($time === '') {
        return 'Not selected';
    }

    $timestamp = strtotime($time);

    if ($timestamp === false) {
        return $time;
    }

    return date('h:i A', $timestamp);
}

function event_feedback_event_name(
    mixed $eventType
): string {
    $eventType = trim((string) $eventType);

    return $eventType !== ''
        ? $eventType
        : 'Wedding Event';
}

function event_feedback_status(
    mixed $status
): string {
    $status = strtolower(
        trim((string) $status)
    );

    return $status === 'hidden'
        ? 'hidden'
        : 'visible';
}

function event_feedback_rating_text(
    int $rating
): string {
    return match ($rating) {
        1 => 'Very Poor',
        2 => 'Poor',
        3 => 'Good',
        4 => 'Very Good',
        5 => 'Excellent',
        default => 'Not Rated',
    };
}

function event_feedback_initials(
    string $name
): string {
    $name = trim($name);

    if ($name === '') {
        return 'CU';
    }

    $parts = preg_split(
        '/\s+/',
        $name,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    if (!is_array($parts) || $parts === []) {
        return 'CU';
    }

    $firstInitial = mb_strtoupper(
        mb_substr(
            (string) $parts[0],
            0,
            1
        )
    );

    if (count($parts) === 1) {
        return $firstInitial;
    }

    $lastInitial = mb_strtoupper(
        mb_substr(
            (string) $parts[count($parts) - 1],
            0,
            1
        )
    );

    return $firstInitial . $lastInitial;
}

/*
|--------------------------------------------------------------------------
| Load Event Manager account
|--------------------------------------------------------------------------
*/

$managerStatement = $connection->prepare(
    'SELECT
        full_name,
        email,
        profile_image
     FROM users
     WHERE id = ?
     AND role = ?
     LIMIT 1'
);

$managerStatement->execute([
    $managerId,
    'event_manager',
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

/*
|--------------------------------------------------------------------------
| Search and filters
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) ($_GET['search'] ?? '')
);

if (mb_strlen($search) > 100) {
    $search = mb_substr(
        $search,
        0,
        100
    );
}

$ratingFilter = trim(
    (string) ($_GET['rating'] ?? 'all')
);

if (
    !in_array(
        $ratingFilter,
        [
            'all',
            '1',
            '2',
            '3',
            '4',
            '5',
        ],
        true
    )
) {
    $ratingFilter = 'all';
}

$statusFilter = strtolower(
    trim(
        (string) ($_GET['status'] ?? 'all')
    )
);

if (
    !in_array(
        $statusFilter,
        [
            'all',
            'visible',
            'hidden',
        ],
        true
    )
) {
    $statusFilter = 'all';
}

$requestedFeedbackId = max(
    0,
    (int) ($_GET['feedback_id'] ?? 0)
);

/*
|--------------------------------------------------------------------------
| Feedback statistics
|--------------------------------------------------------------------------
*/

$statistics = $connection
    ->query(
        "SELECT
            COUNT(*) AS total_feedback,

            COALESCE(
                SUM(rating >= 4),
                0
            ) AS positive_feedback,

            COALESCE(
                SUM(rating = 5),
                0
            ) AS five_star_feedback,

            COALESCE(
                ROUND(AVG(rating), 1),
                0
            ) AS average_rating

         FROM feedback"
    )
    ->fetch();

$totalFeedback = (int) (
    $statistics['total_feedback'] ?? 0
);

$positiveFeedback = (int) (
    $statistics['positive_feedback'] ?? 0
);

$fiveStarFeedback = (int) (
    $statistics['five_star_feedback'] ?? 0
);

$averageRating = (float) (
    $statistics['average_rating'] ?? 0
);

/*
|--------------------------------------------------------------------------
| Load customer feedback
|--------------------------------------------------------------------------
*/

$feedbackQuery =
    'SELECT
        feedback.id,
        feedback.rating,
        feedback.comments,
        feedback.status,
        feedback.created_at,

        bookings.booking_code,
        bookings.event_type,
        bookings.event_date,
        bookings.event_time,
        bookings.guest_count,
        bookings.total_amount,
        bookings.booking_status,

        customers.full_name AS customer_name,
        customers.email AS customer_email,
        customers.phone AS customer_phone,

        packages.name AS package_name,

        venues.name AS venue_name,
        venues.location AS venue_location

     FROM feedback

     INNER JOIN bookings
        ON bookings.id = feedback.booking_id

     LEFT JOIN users AS customers
        ON customers.id = feedback.customer_id

     LEFT JOIN packages
        ON packages.id = bookings.package_id

     LEFT JOIN venues
        ON venues.id = bookings.venue_id

     WHERE 1 = 1';

$feedbackParameters = [];

if ($ratingFilter !== 'all') {
    $feedbackQuery .=
        ' AND feedback.rating = ?';

    $feedbackParameters[] =
        (int) $ratingFilter;
}

if ($statusFilter !== 'all') {
    $feedbackQuery .=
        ' AND feedback.status = ?';

    $feedbackParameters[] =
        $statusFilter;
}

if ($search !== '') {
    $feedbackQuery .=
        ' AND (
            bookings.booking_code LIKE ?
            OR bookings.event_type LIKE ?
            OR customers.full_name LIKE ?
            OR customers.email LIKE ?
            OR packages.name LIKE ?
            OR venues.name LIKE ?
            OR venues.location LIKE ?
            OR feedback.comments LIKE ?
        )';

    $searchValue = '%' . $search . '%';

    for ($index = 0; $index < 8; $index++) {
        $feedbackParameters[] =
            $searchValue;
    }
}

$feedbackQuery .=
    ' ORDER BY
        feedback.created_at DESC,
        feedback.id DESC';

$feedbackStatement =
    $connection->prepare($feedbackQuery);

$feedbackStatement->execute(
    $feedbackParameters
);

$feedbackItems =
    $feedbackStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Prepare modal data
|--------------------------------------------------------------------------
*/

$modalFeedbackRecords = [];

foreach ($feedbackItems as $feedbackItem) {
    $feedbackId =
        (int) $feedbackItem['id'];

    $customerName = trim(
        (string) (
            $feedbackItem['customer_name']
            ?? ''
        )
    );

    if ($customerName === '') {
        $customerName =
            'Deleted Customer';
    }

    $customerEmail = trim(
        (string) (
            $feedbackItem['customer_email']
            ?? ''
        )
    );

    $customerPhone = trim(
        (string) (
            $feedbackItem['customer_phone']
            ?? ''
        )
    );

    $packageName = trim(
        (string) (
            $feedbackItem['package_name']
            ?? ''
        )
    );

    if ($packageName === '') {
        $packageName =
            'Package unavailable';
    }

    $venueName = trim(
        (string) (
            $feedbackItem['venue_name']
            ?? ''
        )
    );

    if ($venueName === '') {
        $venueName =
            'Venue unavailable';
    }

    $venueLocation = trim(
        (string) (
            $feedbackItem['venue_location']
            ?? ''
        )
    );

    $feedbackStatus =
        event_feedback_status(
            $feedbackItem['status']
            ?? ''
        );

    $rating = max(
        1,
        min(
            5,
            (int) (
                $feedbackItem['rating']
                ?? 1
            )
        )
    );

    $modalFeedbackRecords[
        (string) $feedbackId
    ] = [
        'id' => $feedbackId,

        'bookingCode' => (string) (
            $feedbackItem['booking_code']
            ?? ''
        ),

        'eventType' =>
            event_feedback_event_name(
                $feedbackItem['event_type']
                ?? ''
            ),

        'eventDate' =>
            event_feedback_date(
                $feedbackItem['event_date']
                ?? null
            ),

        'eventTime' =>
            event_feedback_time(
                $feedbackItem['event_time']
                ?? null
            ),

        'guestCount' => number_format(
            (int) (
                $feedbackItem['guest_count']
                ?? 0
            )
        ),

        'bookingStatus' => ucwords(
            str_replace(
                '_',
                ' ',
                (string) (
                    $feedbackItem[
                        'booking_status'
                    ]
                    ?? ''
                )
            )
        ),

        'totalAmount' => number_format(
            (float) (
                $feedbackItem['total_amount']
                ?? 0
            ),
            0
        ),

        'customerName' =>
            $customerName,

        'customerEmail' =>
            $customerEmail !== ''
                ? $customerEmail
                : 'Email unavailable',

        'customerPhone' =>
            $customerPhone !== ''
                ? $customerPhone
                : 'Phone unavailable',

        'packageName' =>
            $packageName,

        'venueName' =>
            $venueName
            . (
                $venueLocation !== ''
                    ? ' — ' . $venueLocation
                    : ''
            ),

        'rating' => $rating,

        'ratingText' =>
            event_feedback_rating_text(
                $rating
            ),

        'comments' => (string) (
            $feedbackItem['comments']
            ?? ''
        ),

        'status' =>
            $feedbackStatus,

        'statusLabel' => ucfirst(
            $feedbackStatus
        ),

        'submittedAt' =>
            event_feedback_date(
                $feedbackItem['created_at']
                ?? null,
                'd F Y, h:i A'
            ),
    ];
}

$modalFeedbackJson = json_encode(
    $modalFeedbackRecords,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);

if (!is_string($modalFeedbackJson)) {
    $modalFeedbackJson = '{}';
}

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
        Customer Feedback | <?= e(APP_NAME) ?>
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
                '/assets/css/event_manager_feedback.css'
            )
        ) ?>"
    >
</head>

<body class="event-feedback-page">

    <aside
        class="event-feedback-sidebar"
        id="eventFeedbackSidebar"
    >

        <div class="event-feedback-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="event-feedback-profile">

            <img
                src="<?= e($managerImage) ?>"
                alt="Event Manager profile"
            >

            <h2>
                <?= e($manager['full_name']) ?>
            </h2>

            <p>Event Manager</p>

            <div class="event-feedback-online">
                ● Online
            </div>

        </div>

        <nav class="event-feedback-menu">

            <a
                href="<?= e(
                    url(
                        '/event_manager/dashboard.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a
                href="<?= e(
                    url(
                        '/event_manager/assigned_tasks.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-list-check"></i>
                Assigned Tasks
            </a>

            <a
                href="<?= e(
                    url(
                        '/event_manager/notifications.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-bell"></i>
                Notifications
            </a>

            <a
                href="<?= e(
                    url(
                        '/event_manager/profile.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a
                href="<?= e(
                    url(
                        '/event_manager/gallery.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-images"></i>
                Gallery Management
            </a>

            <a
                class="active"
                href="<?= e(
                    url(
                        '/event_manager/feedback.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-comment-dots"></i>
                Feedback
            </a>

            <a
                class="event-feedback-logout"
                href="<?= e(url('/auth/logout.php')) ?>"
            >
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </nav>

    </aside>

    <div
        class="event-feedback-sidebar-overlay"
        id="eventFeedbackSidebarOverlay"
    ></div>

    <main class="event-feedback-main">

        <header class="event-feedback-topbar">

            <div class="event-feedback-topbar-left">

                <button
                    class="event-feedback-menu-button"
                    id="eventFeedbackMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="event-feedback-heading">

                    <h1>Customer Feedback</h1>

                    <p>
                        View customer ratings and comments
                        for completed wedding events.
                    </p>

                </div>

            </div>

            <div class="event-feedback-topbar-right">

                <div class="event-feedback-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="event-feedback-notification"
                    href="<?= e(
                        url(
                            '/event_manager/notifications.php'
                        )
                    ) ?>"
                    aria-label="Open notifications"
                >
                    <i class="fa-solid fa-bell"></i>
                </a>

                <a
                    class="event-feedback-home-link"
                    href="<?= e(url('/index.php')) ?>"
                    aria-label="Open public website"
                >
                    <i class="fa-solid fa-globe"></i>
                </a>

                <a
                    href="<?= e(
                        url(
                            '/event_manager/profile.php'
                        )
                    ) ?>"
                >
                    <img
                        class="event-feedback-profile-image"
                        src="<?= e($managerImage) ?>"
                        alt="Event Manager profile"
                    >
                </a>

            </div>

        </header>

        <div class="event-feedback-notice">

            <i class="fa-solid fa-circle-info"></i>

            <span>
                This page is for viewing customer feedback.
                Feedback management remains with the
                administrator.
            </span>

        </div>

        <section class="event-feedback-summary">

            <article class="event-feedback-summary-card">

                <div
                    class="event-feedback-summary-icon total"
                >
                    <i class="fa-solid fa-comments"></i>
                </div>

                <div>
                    <h4>Total Feedback</h4>

                    <h2>
                        <?= e(
                            number_format($totalFeedback)
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="event-feedback-summary-card">

                <div
                    class="event-feedback-summary-icon positive"
                >
                    <i class="fa-solid fa-face-smile"></i>
                </div>

                <div>
                    <h4>Positive Reviews</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $positiveFeedback
                            )
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="event-feedback-summary-card">

                <div
                    class="event-feedback-summary-icon five-star"
                >
                    <i class="fa-solid fa-star"></i>
                </div>

                <div>
                    <h4>Five-Star Reviews</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $fiveStarFeedback
                            )
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="event-feedback-summary-card">

                <div
                    class="event-feedback-summary-icon average"
                >
                    <i class="fa-solid fa-chart-line"></i>
                </div>

                <div>
                    <h4>Average Rating</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $averageRating,
                                1
                            )
                        ) ?>
                        / 5
                    </h2>
                </div>

            </article>

        </section>

        <section class="event-feedback-filter-box">

            <form
                class="event-feedback-filter-form"
                method="get"
            >

                <div class="event-feedback-filter-field">

                    <label for="search">
                        Search Feedback
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Customer, booking, event, package or venue"
                    >

                </div>

                <div class="event-feedback-filter-field">

                    <label for="rating">
                        Rating
                    </label>

                    <select
                        id="rating"
                        name="rating"
                    >

                        <option
                            value="all"
                            <?= $ratingFilter === 'all'
                                ? 'selected'
                                : '' ?>
                        >
                            All Ratings
                        </option>

                        <?php for (
                            $rating = 5;
                            $rating >= 1;
                            $rating--
                        ): ?>

                            <option
                                value="<?= e(
                                    (string) $rating
                                ) ?>"
                                <?= $ratingFilter
                                    === (string) $rating
                                        ? 'selected'
                                        : '' ?>
                            >
                                <?= e(
                                    (string) $rating
                                ) ?>

                                Star<?= $rating === 1
                                    ? ''
                                    : 's' ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>

                <div class="event-feedback-filter-field">

                    <label for="status">
                        Visibility
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="all"
                            <?= $statusFilter === 'all'
                                ? 'selected'
                                : '' ?>
                        >
                            All Statuses
                        </option>

                        <option
                            value="visible"
                            <?= $statusFilter === 'visible'
                                ? 'selected'
                                : '' ?>
                        >
                            Visible
                        </option>

                        <option
                            value="hidden"
                            <?= $statusFilter === 'hidden'
                                ? 'selected'
                                : '' ?>
                        >
                            Hidden
                        </option>

                    </select>

                </div>

                <button
                    class="event-feedback-filter-button"
                    type="submit"
                >
                    Apply Filter
                </button>

                <a
                    class="event-feedback-clear-button"
                    href="<?= e(
                        url(
                            '/event_manager/feedback.php'
                        )
                    ) ?>"
                >
                    Clear
                </a>

            </form>

        </section>

        <section class="event-feedback-box">

            <div class="event-feedback-box-heading">

                <div>
                    <h2>Wedding Event Reviews</h2>

                    <p>
                        <?= e(
                            number_format(
                                count($feedbackItems)
                            )
                        ) ?>
                        feedback record(s) currently shown.
                    </p>
                </div>

            </div>

            <?php if ($feedbackItems === []): ?>

                <div class="event-feedback-empty">

                    <i class="fa-regular fa-comments"></i>

                    <h3>No feedback found</h3>

                    <p>
                        No customer feedback matches the
                        selected search and filter options.
                    </p>

                    <a
                        href="<?= e(
                            url(
                                '/event_manager/feedback.php'
                            )
                        ) ?>"
                    >
                        View All Feedback
                    </a>

                </div>

            <?php else: ?>

                <div class="event-feedback-grid">

                    <?php foreach (
                        $feedbackItems as $feedbackItem
                    ): ?>
                        <?php
                        $feedbackId = (int) (
                            $feedbackItem['id']
                        );

                        $rating = max(
                            1,
                            min(
                                5,
                                (int) (
                                    $feedbackItem['rating']
                                    ?? 1
                                )
                            )
                        );

                        $feedbackStatus =
                            event_feedback_status(
                                $feedbackItem['status']
                                ?? ''
                            );

                        $customerName = trim(
                            (string) (
                                $feedbackItem[
                                    'customer_name'
                                ]
                                ?? ''
                            )
                        );

                        if ($customerName === '') {
                            $customerName =
                                'Deleted Customer';
                        }

                        $eventType =
                            event_feedback_event_name(
                                $feedbackItem[
                                    'event_type'
                                ]
                                ?? ''
                            );

                        $packageName = trim(
                            (string) (
                                $feedbackItem[
                                    'package_name'
                                ]
                                ?? ''
                            )
                        );

                        if ($packageName === '') {
                            $packageName =
                                'Package unavailable';
                        }

                        $venueName = trim(
                            (string) (
                                $feedbackItem[
                                    'venue_name'
                                ]
                                ?? ''
                            )
                        );

                        if ($venueName === '') {
                            $venueName =
                                'Venue unavailable';
                        }

                        $comments = trim(
                            (string) (
                                $feedbackItem['comments']
                                ?? ''
                            )
                        );
                        ?>

                        <article class="event-feedback-card">

                            <div class="event-feedback-card-top">

                                <div class="event-feedback-customer">

                                    <div class="event-feedback-avatar">
                                        <?= e(
                                            event_feedback_initials(
                                                $customerName
                                            )
                                        ) ?>
                                    </div>

                                    <div>
                                        <h3>
                                            <?= e($customerName) ?>
                                        </h3>

                                        <p>
                                            <?= e(
                                                (string) (
                                                    $feedbackItem[
                                                        'booking_code'
                                                    ]
                                                    ?? ''
                                                )
                                            ) ?>

                                            · <?= e($eventType) ?>
                                        </p>
                                    </div>

                                </div>

                                <span
                                    class="event-feedback-status <?= e(
                                        $feedbackStatus
                                    ) ?>"
                                >
                                    <?= e(
                                        ucfirst(
                                            $feedbackStatus
                                        )
                                    ) ?>
                                </span>

                            </div>

                            <div class="event-feedback-stars">

                                <?php for (
                                    $star = 1;
                                    $star <= 5;
                                    $star++
                                ): ?>

                                    <i
                                        class="fa-solid fa-star <?= $star > $rating
                                            ? 'empty'
                                            : '' ?>"
                                    ></i>

                                <?php endfor; ?>

                            </div>

                            <p class="event-feedback-comment">
                                <?= e(
                                    mb_strlen($comments) > 190
                                        ? mb_substr(
                                            $comments,
                                            0,
                                            190
                                        ) . '...'
                                        : $comments
                                ) ?>
                            </p>

                            <div
                                class="event-feedback-card-details"
                            >

                                <div class="event-feedback-detail">
                                    <strong>Event Date</strong>

                                    <?= e(
                                        event_feedback_date(
                                            $feedbackItem[
                                                'event_date'
                                            ]
                                            ?? null
                                        )
                                    ) ?>
                                </div>

                                <div class="event-feedback-detail">
                                    <strong>Submitted</strong>

                                    <?= e(
                                        event_feedback_date(
                                            $feedbackItem[
                                                'created_at'
                                            ]
                                            ?? null,
                                            'd M Y'
                                        )
                                    ) ?>
                                </div>

                                <div class="event-feedback-detail">
                                    <strong>Package</strong>

                                    <?= e($packageName) ?>
                                </div>

                                <div class="event-feedback-detail">
                                    <strong>Venue</strong>

                                    <?= e($venueName) ?>
                                </div>

                            </div>

                            <button
                                class="event-feedback-view-button"
                                type="button"
                                data-feedback-id="<?= e(
                                    (string) $feedbackId
                                ) ?>"
                            >
                                View Full Details
                            </button>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <footer class="event-feedback-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <div
        class="event-feedback-modal"
        id="eventFeedbackModal"
    >

        <div class="event-feedback-modal-content">

            <button
                class="event-feedback-modal-close"
                id="eventFeedbackModalClose"
                type="button"
                aria-label="Close feedback details"
            >
                &times;
            </button>

            <div class="event-feedback-modal-header">

                <h2 id="eventFeedbackModalTitle">
                    Customer Feedback
                </h2>

                <div
                    class="event-feedback-modal-reference"
                    id="eventFeedbackModalReference"
                ></div>

            </div>

            <div class="event-feedback-modal-topline">

                <div
                    class="event-feedback-modal-stars"
                    id="eventFeedbackModalStars"
                ></div>

                <div
                    class="event-feedback-modal-submitted"
                    id="eventFeedbackModalSubmitted"
                ></div>

            </div>

            <div class="event-feedback-modal-grid">

                <div class="event-feedback-modal-item">
                    <strong>Customer Name</strong>
                    <span id="eventFeedbackCustomerName"></span>
                </div>

                <div class="event-feedback-modal-item">
                    <strong>Customer Email</strong>
                    <span id="eventFeedbackCustomerEmail"></span>
                </div>

                <div class="event-feedback-modal-item">
                    <strong>Customer Phone</strong>
                    <span id="eventFeedbackCustomerPhone"></span>
                </div>

                <div class="event-feedback-modal-item">
                    <strong>Event Date</strong>
                    <span id="eventFeedbackEventDate"></span>
                </div>

                <div class="event-feedback-modal-item">
                    <strong>Event Time</strong>
                    <span id="eventFeedbackEventTime"></span>
                </div>

                <div class="event-feedback-modal-item">
                    <strong>Guests</strong>
                    <span id="eventFeedbackGuests"></span>
                </div>

                <div class="event-feedback-modal-item">
                    <strong>Package</strong>
                    <span id="eventFeedbackPackage"></span>
                </div>

                <div class="event-feedback-modal-item">
                    <strong>Venue</strong>
                    <span id="eventFeedbackVenue"></span>
                </div>

                <div class="event-feedback-modal-item">
                    <strong>Booking Status</strong>
                    <span id="eventFeedbackBookingStatus"></span>
                </div>

                <div class="event-feedback-modal-item">
                    <strong>Booking Total</strong>
                    <span id="eventFeedbackBookingTotal"></span>
                </div>

                <div class="event-feedback-modal-item">
                    <strong>Rating</strong>
                    <span id="eventFeedbackRatingText"></span>
                </div>

                <div class="event-feedback-modal-item">
                    <strong>Visibility</strong>
                    <span id="eventFeedbackVisibility"></span>
                </div>

            </div>

            <div class="event-feedback-modal-section">

                <h3>Customer Comments</h3>

                <div
                    class="event-feedback-modal-text"
                    id="eventFeedbackComments"
                ></div>

            </div>

        </div>

    </div>

    <script>
        const eventFeedbackRecords =
            <?= $modalFeedbackJson ?>;

        const eventFeedbackSidebar =
            document.getElementById(
                "eventFeedbackSidebar"
            );

        const eventFeedbackSidebarOverlay =
            document.getElementById(
                "eventFeedbackSidebarOverlay"
            );

        const eventFeedbackMenuButton =
            document.getElementById(
                "eventFeedbackMenuButton"
            );

        function closeEventFeedbackSidebar() {
            eventFeedbackSidebar.classList.remove(
                "open"
            );

            eventFeedbackSidebarOverlay.classList.remove(
                "open"
            );
        }

        eventFeedbackMenuButton.addEventListener(
            "click",
            function () {
                eventFeedbackSidebar.classList.toggle(
                    "open"
                );

                eventFeedbackSidebarOverlay.classList.toggle(
                    "open"
                );
            }
        );

        eventFeedbackSidebarOverlay.addEventListener(
            "click",
            closeEventFeedbackSidebar
        );

        const feedbackModal =
            document.getElementById(
                "eventFeedbackModal"
            );

        const feedbackModalClose =
            document.getElementById(
                "eventFeedbackModalClose"
            );

        function createEventFeedbackStars(
            rating
        ) {
            const starsContainer =
                document.getElementById(
                    "eventFeedbackModalStars"
                );

            starsContainer.innerHTML = "";

            for (
                let star = 1;
                star <= 5;
                star++
            ) {
                const icon =
                    document.createElement("i");

                icon.className =
                    "fa-solid fa-star"
                    + (
                        star > rating
                            ? " empty"
                            : ""
                    );

                starsContainer.appendChild(
                    icon
                );
            }
        }

        function openEventFeedbackModal(
            feedbackId
        ) {
            const record =
                eventFeedbackRecords[
                    String(feedbackId)
                ];

            if (!record) {
                return;
            }

            document.getElementById(
                "eventFeedbackModalTitle"
            ).textContent =
                record.eventType;

            document.getElementById(
                "eventFeedbackModalReference"
            ).textContent =
                "Booking Reference: "
                + record.bookingCode;

            document.getElementById(
                "eventFeedbackModalSubmitted"
            ).textContent =
                "Submitted "
                + record.submittedAt;

            document.getElementById(
                "eventFeedbackCustomerName"
            ).textContent =
                record.customerName;

            document.getElementById(
                "eventFeedbackCustomerEmail"
            ).textContent =
                record.customerEmail;

            document.getElementById(
                "eventFeedbackCustomerPhone"
            ).textContent =
                record.customerPhone;

            document.getElementById(
                "eventFeedbackEventDate"
            ).textContent =
                record.eventDate;

            document.getElementById(
                "eventFeedbackEventTime"
            ).textContent =
                record.eventTime;

            document.getElementById(
                "eventFeedbackGuests"
            ).textContent =
                record.guestCount + " guests";

            document.getElementById(
                "eventFeedbackPackage"
            ).textContent =
                record.packageName;

            document.getElementById(
                "eventFeedbackVenue"
            ).textContent =
                record.venueName;

            document.getElementById(
                "eventFeedbackBookingStatus"
            ).textContent =
                record.bookingStatus;

            document.getElementById(
                "eventFeedbackBookingTotal"
            ).textContent =
                "Rs. " + record.totalAmount;

            document.getElementById(
                "eventFeedbackRatingText"
            ).textContent =
                record.rating
                + " / 5 — "
                + record.ratingText;

            document.getElementById(
                "eventFeedbackVisibility"
            ).textContent =
                record.statusLabel;

            document.getElementById(
                "eventFeedbackComments"
            ).textContent =
                record.comments;

            createEventFeedbackStars(
                Number(record.rating)
            );

            feedbackModal.classList.add(
                "open"
            );

            document.body.style.overflow =
                "hidden";
        }

        document
            .querySelectorAll(
                "[data-feedback-id]"
            )
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        openEventFeedbackModal(
                            button.dataset.feedbackId
                        );
                    }
                );
            });

        function closeEventFeedbackModal() {
            feedbackModal.classList.remove(
                "open"
            );

            document.body.style.overflow =
                "";
        }

        feedbackModalClose.addEventListener(
            "click",
            closeEventFeedbackModal
        );

        feedbackModal.addEventListener(
            "click",
            function (event) {
                if (event.target === feedbackModal) {
                    closeEventFeedbackModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (event.key === "Escape") {
                    closeEventFeedbackModal();
                }
            }
        );

        const requestedFeedbackId =
            "<?= e(
                $requestedFeedbackId > 0
                    ? (string) $requestedFeedbackId
                    : ''
            ) ?>";

        if (requestedFeedbackId !== "") {
            openEventFeedbackModal(
                requestedFeedbackId
            );
        }
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>