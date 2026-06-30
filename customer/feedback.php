<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('customer');

$connection = db();
$customerId = (int) $_SESSION['user_id'];
$errors = [];
$flash = get_flash();

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function customer_feedback_date(
    mixed $date
): string {
    $timestamp = strtotime((string) $date);

    if ($timestamp === false) {
        return 'Date unavailable';
    }

    return date('d F Y', $timestamp);
}

function customer_feedback_datetime(
    mixed $date
): string {
    $timestamp = strtotime((string) $date);

    if ($timestamp === false) {
        return 'Date unavailable';
    }

    return date('d F Y, h:i A', $timestamp);
}

function customer_feedback_event_name(
    mixed $eventType
): string {
    $eventType = trim((string) $eventType);

    return $eventType !== ''
        ? $eventType
        : 'Wedding Event';
}

/*
|--------------------------------------------------------------------------
| Load logged-in customer
|--------------------------------------------------------------------------
*/

$customerStatement = $connection->prepare(
    'SELECT
        full_name,
        email,
        profile_image
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

/*
|--------------------------------------------------------------------------
| Submit feedback
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $bookingId = max(
        0,
        (int) ($_POST['booking_id'] ?? 0)
    );

    $rating = (int) (
        $_POST['rating'] ?? 0
    );

    $comments = trim(
        (string) ($_POST['comments'] ?? '')
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

    if ($bookingId < 1) {
        $errors[] =
            'Please select a completed booking.';
    }

    if ($rating < 1 || $rating > 5) {
        $errors[] =
            'Please select a rating between 1 and 5 stars.';
    }

    if (
        mb_strlen($comments) < 10
        || mb_strlen($comments) > 2000
    ) {
        $errors[] =
            'Feedback comments must contain between 10 and 2,000 characters.';
    }

    if ($errors === []) {
        $bookingStatement =
            $connection->prepare(
                "SELECT
                    id,
                    booking_code,
                    event_type,
                    booking_status

                 FROM bookings

                 WHERE id = ?
                 AND customer_id = ?
                 LIMIT 1"
            );

        $bookingStatement->execute([
            $bookingId,
            $customerId,
        ]);

        $selectedBooking =
            $bookingStatement->fetch();

        if (!$selectedBooking) {
            $errors[] =
                'The selected booking was not found in your account.';
        } elseif (
            (string) $selectedBooking[
                'booking_status'
            ] !== 'completed'
        ) {
            $errors[] =
                'Feedback can only be submitted for a completed booking.';
        }
    }

    if ($errors === []) {
        $duplicateStatement =
            $connection->prepare(
                'SELECT id
                 FROM feedback
                 WHERE booking_id = ?
                 LIMIT 1'
            );

        $duplicateStatement->execute([
            $bookingId,
        ]);

        if ($duplicateStatement->fetch()) {
            $errors[] =
                'Feedback has already been submitted for this booking.';
        }
    }

    if ($errors === []) {
        try {
            $insertStatement =
                $connection->prepare(
                    'INSERT INTO feedback (
                        booking_id,
                        customer_id,
                        rating,
                        comments,
                        status,
                        created_at,
                        updated_at
                     ) VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        NOW(),
                        NOW()
                     )'
                );

            $insertStatement->execute([
                $bookingId,
                $customerId,
                $rating,
                $comments,
                'visible',
            ]);

            set_flash(
                'success',
                'Thank you. Your feedback was submitted successfully.'
            );

            redirect('/customer/feedback.php');
        } catch (Throwable $exception) {
            $errors[] = APP_DEBUG
                ? 'Feedback could not be submitted: '
                    . $exception->getMessage()
                : 'Feedback could not be submitted. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Customer feedback statistics
|--------------------------------------------------------------------------
*/

$statisticsStatement =
    $connection->prepare(
        "SELECT
            (
                SELECT COUNT(*)
                FROM bookings
                WHERE customer_id = ?
                AND booking_status = 'completed'
            ) AS completed_bookings,

            (
                SELECT COUNT(*)
                FROM feedback
                WHERE customer_id = ?
            ) AS submitted_feedback,

            (
                SELECT COALESCE(
                    ROUND(AVG(rating), 1),
                    0
                )
                FROM feedback
                WHERE customer_id = ?
            ) AS average_rating"
    );

$statisticsStatement->execute([
    $customerId,
    $customerId,
    $customerId,
]);

$statistics =
    $statisticsStatement->fetch();

$completedBookings = (int) (
    $statistics['completed_bookings'] ?? 0
);

$submittedFeedback = (int) (
    $statistics['submitted_feedback'] ?? 0
);

$pendingFeedback = max(
    0,
    $completedBookings - $submittedFeedback
);

$averageRating = (float) (
    $statistics['average_rating'] ?? 0
);

/*
|--------------------------------------------------------------------------
| Load eligible completed bookings
|--------------------------------------------------------------------------
*/

$eligibleStatement =
    $connection->prepare(
        "SELECT
            bookings.id,
            bookings.booking_code,
            bookings.event_type,
            bookings.event_date,
            packages.name AS package_name,
            venues.name AS venue_name,
            venues.location AS venue_location

         FROM bookings

         LEFT JOIN packages
            ON packages.id = bookings.package_id

         LEFT JOIN venues
            ON venues.id = bookings.venue_id

         LEFT JOIN feedback
            ON feedback.booking_id = bookings.id

         WHERE bookings.customer_id = ?
         AND bookings.booking_status = 'completed'
         AND feedback.id IS NULL

         ORDER BY
            bookings.event_date DESC,
            bookings.created_at DESC"
    );

$eligibleStatement->execute([
    $customerId,
]);

$eligibleBookings =
    $eligibleStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Load previously submitted feedback
|--------------------------------------------------------------------------
*/

$historyStatement =
    $connection->prepare(
        "SELECT
            feedback.id,
            feedback.rating,
            feedback.comments,
            feedback.status,
            feedback.admin_reply,
            feedback.replied_at,
            feedback.created_at,

            bookings.booking_code,
            bookings.event_type,
            bookings.event_date,

            packages.name AS package_name,

            venues.name AS venue_name

         FROM feedback

         INNER JOIN bookings
            ON bookings.id = feedback.booking_id

         LEFT JOIN packages
            ON packages.id = bookings.package_id

         LEFT JOIN venues
            ON venues.id = bookings.venue_id

         WHERE feedback.customer_id = ?

         ORDER BY feedback.created_at DESC"
    );

$historyStatement->execute([
    $customerId,
]);

$feedbackHistory =
    $historyStatement->fetchAll();

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
        Feedback | <?= e(APP_NAME) ?>
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
            url('/assets/css/customer_feedback.css')
        ) ?>"
    >
</head>

<body class="customer-dashboard-page">

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
                <?= e($customer['full_name']) ?>
            </h2>

            <p>Customer Account</p>

            <div class="customer-online">
                ● Online
            </div>

        </div>

        <nav class="customer-menu">

            <a
                href="<?= e(
                    url('/customer/dashboard.php')
                ) ?>"
            >
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a
                href="<?= e(
                    url('/customer/packages.php')
                ) ?>"
            >
                <i class="fa-solid fa-gift"></i>
                Browse Packages
            </a>

            <a
                href="<?= e(
                    url('/customer/venues.php')
                ) ?>"
            >
                <i class="fa-solid fa-hotel"></i>
                Browse Venues
            </a>

            <a
                href="<?= e(
                    url('/customer/gallery.php')
                ) ?>"
            >
                <i class="fa-solid fa-images"></i>
                Wedding Gallery
            </a>

            <a
                href="<?= e(
                    url('/customer/booking.php')
                ) ?>"
            >
                <i class="fa-solid fa-calendar-plus"></i>
                Book Event
            </a>

            <a
                href="<?= e(
                    url('/customer/my_bookings.php')
                ) ?>"
            >
                <i class="fa-solid fa-calendar-check"></i>
                My Bookings
            </a>

            <a
                class="active"
                href="<?= e(
                    url('/customer/feedback.php')
                ) ?>"
            >
                <i class="fa-solid fa-star"></i>
                Feedback
            </a>

            <a
                href="<?= e(
                    url('/customer/profile.php')
                ) ?>"
            >
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a
                class="customer-logout"
                href="<?= e(url('/auth/logout.php')) ?>"
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

        <header class="customer-topbar">

            <div class="customer-topbar-left">

                <button
                    class="customer-menu-button"
                    id="customerMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="customer-heading">

                    <h1>Customer Feedback</h1>

                    <p>
                        Share your experience after your
                        wedding event has been completed.
                    </p>

                </div>

            </div>

            <div class="customer-topbar-right">

                <div class="customer-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="customer-home-link"
                    href="<?= e(url('/index.php')) ?>"
                    aria-label="Open public website"
                >
                    <i class="fa-solid fa-globe"></i>
                </a>

                <a
                    href="<?= e(
                        url('/customer/profile.php')
                    ) ?>"
                >
                    <img
                        class="customer-profile-image"
                        src="<?= e($customerImage) ?>"
                        alt="Customer profile"
                    >
                </a>

            </div>

        </header>

        <?php if ($flash): ?>

            <div
                class="customer-feedback-alert <?= $flash['type'] === 'success'
                    ? 'success'
                    : 'danger' ?>"
            >
                <?= e($flash['message']) ?>
            </div>

        <?php endif; ?>

        <?php if ($errors !== []): ?>

            <div
                class="customer-feedback-alert danger"
            >
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

        <?php endif; ?>

        <section class="customer-feedback-summary">

            <article
                class="customer-feedback-summary-card"
            >
                <div
                    class="customer-feedback-summary-icon completed"
                >
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    <h4>Completed Events</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $completedBookings
                            )
                        ) ?>
                    </h2>
                </div>
            </article>

            <article
                class="customer-feedback-summary-card"
            >
                <div
                    class="customer-feedback-summary-icon submitted"
                >
                    <i class="fa-solid fa-comments"></i>
                </div>

                <div>
                    <h4>Feedback Submitted</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $submittedFeedback
                            )
                        ) ?>
                    </h2>
                </div>
            </article>

            <article
                class="customer-feedback-summary-card"
            >
                <div
                    class="customer-feedback-summary-icon pending"
                >
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div>
                    <h4>Awaiting Feedback</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $pendingFeedback
                            )
                        ) ?>
                    </h2>
                </div>
            </article>

            <article
                class="customer-feedback-summary-card"
            >
                <div
                    class="customer-feedback-summary-icon rating"
                >
                    <i class="fa-solid fa-star"></i>
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

        <section class="customer-feedback-layout">

            <div class="customer-feedback-box">

                <div
                    class="customer-feedback-box-heading"
                >
                    <h2>Submit Feedback</h2>

                    <p>
                        Select a completed booking, choose
                        your rating and share your comments.
                    </p>
                </div>

                <?php if ($eligibleBookings === []): ?>

                    <div class="customer-feedback-empty">

                        <i
                            class="fa-regular fa-face-smile"
                        ></i>

                        <h3>
                            No booking currently requires
                            feedback
                        </h3>

                        <p>
                            Feedback becomes available after
                            the Admin marks your booking as
                            completed. Each completed booking
                            can be reviewed once.
                        </p>

                    </div>

                <?php else: ?>

                    <form method="post">

                        <?= csrf_field() ?>

                        <div
                            class="customer-feedback-form-field"
                        >
                            <label for="booking_id">
                                Completed Booking
                            </label>

                            <select
                                id="booking_id"
                                name="booking_id"
                                required
                            >
                                <option value="">
                                    Select a completed booking
                                </option>

                                <?php foreach (
                                    $eligibleBookings
                                    as $eligibleBooking
                                ): ?>
                                    <?php
                                    $eligibleId =
                                        (int) $eligibleBooking[
                                            'id'
                                        ];

                                    $eligibleEvent =
                                        customer_feedback_event_name(
                                            $eligibleBooking[
                                                'event_type'
                                            ]
                                            ?? ''
                                        );

                                    $eligiblePackage = trim(
                                        (string) (
                                            $eligibleBooking[
                                                'package_name'
                                            ]
                                            ?? ''
                                        )
                                    );

                                    if (
                                        $eligiblePackage === ''
                                    ) {
                                        $eligiblePackage =
                                            'Package unavailable';
                                    }

                                    $eligibleVenue = trim(
                                        (string) (
                                            $eligibleBooking[
                                                'venue_name'
                                            ]
                                            ?? ''
                                        )
                                    );

                                    if (
                                        $eligibleVenue === ''
                                    ) {
                                        $eligibleVenue =
                                            'Venue unavailable';
                                    }

                                    $eligibleLocation = trim(
                                        (string) (
                                            $eligibleBooking[
                                                'venue_location'
                                            ]
                                            ?? ''
                                        )
                                    );
                                    ?>

                                    <option
                                        value="<?= e(
                                            (string) $eligibleId
                                        ) ?>"

                                        data-code="<?= e(
                                            (string) $eligibleBooking[
                                                'booking_code'
                                            ]
                                        ) ?>"

                                        data-event="<?= e(
                                            $eligibleEvent
                                        ) ?>"

                                        data-date="<?= e(
                                            customer_feedback_date(
                                                $eligibleBooking[
                                                    'event_date'
                                                ]
                                                ?? null
                                            )
                                        ) ?>"

                                        data-package="<?= e(
                                            $eligiblePackage
                                        ) ?>"

                                        data-venue="<?= e(
                                            $eligibleVenue
                                            . (
                                                $eligibleLocation !== ''
                                                    ? ' — '
                                                        . $eligibleLocation
                                                    : ''
                                            )
                                        ) ?>"

                                        <?= (string) (
                                            $_POST['booking_id']
                                            ?? ''
                                        ) === (string) $eligibleId
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e(
                                            (string) $eligibleBooking[
                                                'booking_code'
                                            ]
                                            . ' — '
                                            . $eligibleEvent
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>
                        </div>

                        <div
                            class="customer-feedback-booking-preview"
                            id="customerFeedbackBookingPreview"
                        >
                            <h3>Selected Booking</h3>

                            <div
                                class="customer-feedback-booking-preview-row"
                            >
                                <strong>Reference</strong>

                                <span
                                    id="feedbackPreviewCode"
                                ></span>
                            </div>

                            <div
                                class="customer-feedback-booking-preview-row"
                            >
                                <strong>Event</strong>

                                <span
                                    id="feedbackPreviewEvent"
                                ></span>
                            </div>

                            <div
                                class="customer-feedback-booking-preview-row"
                            >
                                <strong>Date</strong>

                                <span
                                    id="feedbackPreviewDate"
                                ></span>
                            </div>

                            <div
                                class="customer-feedback-booking-preview-row"
                            >
                                <strong>Package</strong>

                                <span
                                    id="feedbackPreviewPackage"
                                ></span>
                            </div>

                            <div
                                class="customer-feedback-booking-preview-row"
                            >
                                <strong>Venue</strong>

                                <span
                                    id="feedbackPreviewVenue"
                                ></span>
                            </div>
                        </div>

                        <div
                            class="customer-feedback-form-field"
                        >
                            <label>
                                Your Rating
                            </label>

                            <div
                                class="customer-feedback-rating"
                            >

                                <?php for (
                                    $star = 5;
                                    $star >= 1;
                                    $star--
                                ): ?>

                                    <input
                                        type="radio"
                                        id="rating_<?= e(
                                            (string) $star
                                        ) ?>"
                                        name="rating"
                                        value="<?= e(
                                            (string) $star
                                        ) ?>"

                                        <?= (int) (
                                            $_POST['rating']
                                            ?? 0
                                        ) === $star
                                            ? 'checked'
                                            : '' ?>

                                        required
                                    >

                                    <label
                                        for="rating_<?= e(
                                            (string) $star
                                        ) ?>"
                                        title="<?= e(
                                            (string) $star
                                        ) ?> stars"
                                    >
                                        <i
                                            class="fa-solid fa-star"
                                        ></i>
                                    </label>

                                <?php endfor; ?>

                            </div>

                            <div
                                class="customer-feedback-rating-text"
                                id="customerFeedbackRatingText"
                            ></div>
                        </div>

                        <div
                            class="customer-feedback-form-field"
                        >
                            <label for="comments">
                                Feedback Comments
                            </label>

                            <textarea
                                id="comments"
                                name="comments"
                                minlength="10"
                                maxlength="2000"
                                placeholder="Tell us about the planning, venue, package, services and overall event experience."
                                required
                            ><?= e(
                                (string) (
                                    $_POST['comments']
                                    ?? ''
                                )
                            ) ?></textarea>

                            <span
                                class="customer-feedback-help"
                            >
                                Minimum 10 and maximum 2,000
                                characters.
                            </span>
                        </div>

                        <button
                            class="customer-feedback-submit-button"
                            type="submit"
                        >
                            Submit Feedback
                        </button>

                    </form>

                <?php endif; ?>

            </div>

            <div class="customer-feedback-box">

                <div
                    class="customer-feedback-box-heading"
                >
                    <h2>Your Feedback History</h2>

                    <p>
                        Review the ratings and comments you
                        previously submitted.
                    </p>
                </div>

                <?php if ($feedbackHistory === []): ?>

                    <div class="customer-feedback-empty">

                        <i class="fa-regular fa-comments"></i>

                        <h3>No feedback submitted yet</h3>

                        <p>
                            Your submitted feedback and any
                            response from the administrator
                            will appear here.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="customer-feedback-history">

                        <?php foreach (
                            $feedbackHistory
                            as $feedbackItem
                        ): ?>
                            <?php
                            $feedbackRating = max(
                                1,
                                min(
                                    5,
                                    (int) $feedbackItem[
                                        'rating'
                                    ]
                                )
                            );

                            $feedbackStatus = strtolower(
                                trim(
                                    (string) (
                                        $feedbackItem[
                                            'status'
                                        ]
                                        ?? 'visible'
                                    )
                                )
                            );

                            if (
                                !in_array(
                                    $feedbackStatus,
                                    ['visible', 'hidden'],
                                    true
                                )
                            ) {
                                $feedbackStatus =
                                    'visible';
                            }

                            $historyEvent =
                                customer_feedback_event_name(
                                    $feedbackItem[
                                        'event_type'
                                    ]
                                    ?? ''
                                );

                            $historyPackage = trim(
                                (string) (
                                    $feedbackItem[
                                        'package_name'
                                    ]
                                    ?? ''
                                )
                            );

                            if ($historyPackage === '') {
                                $historyPackage =
                                    'Package unavailable';
                            }

                            $historyVenue = trim(
                                (string) (
                                    $feedbackItem[
                                        'venue_name'
                                    ]
                                    ?? ''
                                )
                            );

                            if ($historyVenue === '') {
                                $historyVenue =
                                    'Venue unavailable';
                            }

                            $adminReply = trim(
                                (string) (
                                    $feedbackItem[
                                        'admin_reply'
                                    ]
                                    ?? ''
                                )
                            );
                            ?>

                            <article
                                class="customer-feedback-card"
                            >

                                <div
                                    class="customer-feedback-card-top"
                                >
                                    <div>
                                        <h3>
                                            <?= e(
                                                $historyEvent
                                            ) ?>
                                        </h3>

                                        <div
                                            class="customer-feedback-reference"
                                        >
                                            <?= e(
                                                (string) $feedbackItem[
                                                    'booking_code'
                                                ]
                                            ) ?>
                                        </div>
                                    </div>

                                    <div
                                        class="customer-feedback-card-date"
                                    >
                                        <?= e(
                                            customer_feedback_datetime(
                                                $feedbackItem[
                                                    'created_at'
                                                ]
                                                ?? null
                                            )
                                        ) ?>
                                    </div>
                                </div>

                                <div
                                    class="customer-feedback-stars"
                                    aria-label="<?= e(
                                        (string) $feedbackRating
                                    ) ?> out of 5 stars"
                                >
                                    <?php for (
                                        $star = 1;
                                        $star <= 5;
                                        $star++
                                    ): ?>

                                        <i
                                            class="fa-solid fa-star <?= $star > $feedbackRating
                                                ? 'empty-star'
                                                : '' ?>"
                                        ></i>

                                    <?php endfor; ?>
                                </div>

                                <p
                                    class="customer-feedback-comment"
                                ><?= e(
                                    (string) $feedbackItem[
                                        'comments'
                                    ]
                                ) ?></p>

                                <div
                                    class="customer-feedback-details"
                                >

                                    <div
                                        class="customer-feedback-detail"
                                    >
                                        <strong>
                                            Event Date
                                        </strong>

                                        <?= e(
                                            customer_feedback_date(
                                                $feedbackItem[
                                                    'event_date'
                                                ]
                                                ?? null
                                            )
                                        ) ?>
                                    </div>

                                    <div
                                        class="customer-feedback-detail"
                                    >
                                        <strong>
                                            Package
                                        </strong>

                                        <?= e(
                                            $historyPackage
                                        ) ?>
                                    </div>

                                    <div
                                        class="customer-feedback-detail"
                                    >
                                        <strong>
                                            Venue
                                        </strong>

                                        <?= e(
                                            $historyVenue
                                        ) ?>
                                    </div>

                                </div>

                                <div
                                    style="margin-top: 12px;"
                                >
                                    <span
                                        class="customer-feedback-status <?= e(
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

                                <?php if (
                                    $adminReply !== ''
                                ): ?>

                                    <div
                                        class="customer-feedback-admin-reply"
                                    >
                                        <strong>
                                            Administrator Response
                                        </strong>

                                        <p><?= e(
                                            $adminReply
                                        ) ?></p>

                                        <?php if (
                                            !empty(
                                                $feedbackItem[
                                                    'replied_at'
                                                ]
                                            )
                                        ): ?>

                                            <small>
                                                Replied
                                                <?= e(
                                                    customer_feedback_datetime(
                                                        $feedbackItem[
                                                            'replied_at'
                                                        ]
                                                    )
                                                ) ?>
                                            </small>

                                        <?php endif; ?>
                                    </div>

                                <?php endif; ?>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <footer class="customer-feedback-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <script>
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
            customerSidebar.classList.remove(
                "open"
            );

            customerSidebarOverlay.classList.remove(
                "open"
            );
        }

        customerMenuButton.addEventListener(
            "click",
            function () {
                customerSidebar.classList.toggle(
                    "open"
                );

                customerSidebarOverlay.classList.toggle(
                    "open"
                );
            }
        );

        customerSidebarOverlay.addEventListener(
            "click",
            closeCustomerSidebar
        );

        const bookingSelect =
            document.getElementById(
                "booking_id"
            );

        const bookingPreview =
            document.getElementById(
                "customerFeedbackBookingPreview"
            );

        function updateBookingPreview() {
            if (
                !bookingSelect
                || !bookingPreview
            ) {
                return;
            }

            const selectedOption =
                bookingSelect.options[
                    bookingSelect.selectedIndex
                ];

            if (
                !selectedOption
                || !selectedOption.value
            ) {
                bookingPreview.classList.remove(
                    "visible"
                );

                return;
            }

            document.getElementById(
                "feedbackPreviewCode"
            ).textContent =
                selectedOption.dataset.code || "";

            document.getElementById(
                "feedbackPreviewEvent"
            ).textContent =
                selectedOption.dataset.event || "";

            document.getElementById(
                "feedbackPreviewDate"
            ).textContent =
                selectedOption.dataset.date || "";

            document.getElementById(
                "feedbackPreviewPackage"
            ).textContent =
                selectedOption.dataset.package || "";

            document.getElementById(
                "feedbackPreviewVenue"
            ).textContent =
                selectedOption.dataset.venue || "";

            bookingPreview.classList.add(
                "visible"
            );
        }

        if (bookingSelect) {
            bookingSelect.addEventListener(
                "change",
                updateBookingPreview
            );

            updateBookingPreview();
        }

        const ratingInputs =
            document.querySelectorAll(
                'input[name="rating"]'
            );

        const ratingText =
            document.getElementById(
                "customerFeedbackRatingText"
            );

        const ratingLabels = {
            1: "Very Poor",
            2: "Poor",
            3: "Good",
            4: "Very Good",
            5: "Excellent"
        };

        function updateRatingText() {
            if (!ratingText) {
                return;
            }

            const selectedRating =
                document.querySelector(
                    'input[name="rating"]:checked'
                );

            ratingText.textContent =
                selectedRating
                    ? selectedRating.value
                        + " / 5 — "
                        + ratingLabels[
                            selectedRating.value
                        ]
                    : "Select your rating";
        }

        ratingInputs.forEach(
            function (ratingInput) {
                ratingInput.addEventListener(
                    "change",
                    updateRatingText
                );
            }
        );

        updateRatingText();
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>