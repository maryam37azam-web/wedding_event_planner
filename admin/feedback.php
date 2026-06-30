<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');

$connection = db();
$adminId = (int) $_SESSION['user_id'];
$errors = [];
$flash = get_flash();

$allowedStatuses = [
    'visible',
    'hidden',
];

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function admin_feedback_date(
    mixed $date,
    string $format = 'd F Y'
): string {
    $timestamp = strtotime((string) $date);

    if ($timestamp === false) {
        return 'Date unavailable';
    }

    return date($format, $timestamp);
}

function admin_feedback_event_name(
    mixed $eventType
): string {
    $eventType = trim((string) $eventType);

    return $eventType !== ''
        ? $eventType
        : 'Wedding Event';
}

function admin_feedback_status(
    mixed $status
): string {
    $status = strtolower(
        trim((string) $status)
    );

    return $status === 'hidden'
        ? 'hidden'
        : 'visible';
}

function admin_feedback_rating_text(
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

/*
|--------------------------------------------------------------------------
| Load administrator
|--------------------------------------------------------------------------
*/

$adminStatement = $connection->prepare(
    'SELECT
        full_name,
        email,
        profile_image
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
    ? url(
        '/'
        . ltrim(
            (string) $admin['profile_image'],
            '/'
        )
    )
    : url('/assets/icons/icon-192.png');

/*
|--------------------------------------------------------------------------
| Handle administrator actions
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $action = trim(
        (string) ($_POST['action'] ?? '')
    );

    $feedbackId = max(
        0,
        (int) ($_POST['feedback_id'] ?? 0)
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

    if ($feedbackId < 1) {
        $errors[] =
            'Select a valid feedback record.';
    }

    if (
        !in_array(
            $action,
            [
                'update_status',
                'save_reply',
            ],
            true
        )
    ) {
        $errors[] =
            'Invalid feedback action.';
    }

    $feedbackToUpdate = null;

    if ($errors === []) {
        $feedbackCheckStatement =
            $connection->prepare(
                'SELECT
                    id,
                    booking_id,
                    customer_id,
                    status,
                    admin_reply
                 FROM feedback
                 WHERE id = ?
                 LIMIT 1'
            );

        $feedbackCheckStatement->execute([
            $feedbackId,
        ]);

        $feedbackToUpdate =
            $feedbackCheckStatement->fetch();

        if (!$feedbackToUpdate) {
            $errors[] =
                'The selected feedback record was not found.';
        }
    }

    if (
        $errors === []
        && $action === 'update_status'
    ) {
        $newStatus = strtolower(
            trim(
                (string) (
                    $_POST['feedback_status']
                    ?? ''
                )
            )
        );

        if (
            !in_array(
                $newStatus,
                $allowedStatuses,
                true
            )
        ) {
            $errors[] =
                'Select a valid feedback visibility status.';
        }

        if ($errors === []) {
            try {
                $statusStatement =
                    $connection->prepare(
                        'UPDATE feedback
                         SET status = ?,
                             updated_at = NOW()
                         WHERE id = ?'
                    );

                $statusStatement->execute([
                    $newStatus,
                    $feedbackId,
                ]);

                set_flash(
                    'success',
                    'Feedback visibility was updated successfully.'
                );

                redirect(
                    '/admin/feedback.php?feedback_id='
                    . $feedbackId
                );
            } catch (Throwable $exception) {
                $errors[] = APP_DEBUG
                    ? 'Feedback status could not be updated: '
                        . $exception->getMessage()
                    : 'Feedback status could not be updated.';
            }
        }
    }

    if (
        $errors === []
        && $action === 'save_reply'
    ) {
        $adminReply = trim(
            (string) ($_POST['admin_reply'] ?? '')
        );

        if (
            mb_strlen($adminReply) < 2
            || mb_strlen($adminReply) > 2000
        ) {
            $errors[] =
                'The administrator reply must contain between 2 and 2,000 characters.';
        }

        if ($errors === []) {
            try {
                $replyStatement =
                    $connection->prepare(
                        'UPDATE feedback
                         SET admin_reply = ?,
                             replied_by = ?,
                             replied_at = NOW(),
                             updated_at = NOW()
                         WHERE id = ?'
                    );

                $replyStatement->execute([
                    $adminReply,
                    $adminId,
                    $feedbackId,
                ]);

                set_flash(
                    'success',
                    'Your reply was saved successfully.'
                );

                redirect(
                    '/admin/feedback.php?feedback_id='
                    . $feedbackId
                );
            } catch (Throwable $exception) {
                $errors[] = APP_DEBUG
                    ? 'The reply could not be saved: '
                        . $exception->getMessage()
                    : 'The reply could not be saved.';
            }
        }
    }
}

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

$replyFilter = strtolower(
    trim(
        (string) ($_GET['reply'] ?? 'all')
    )
);

if (
    !in_array(
        $replyFilter,
        [
            'all',
            'replied',
            'unanswered',
        ],
        true
    )
) {
    $replyFilter = 'all';
}

$requestedFeedbackId = max(
    0,
    (int) ($_GET['feedback_id'] ?? 0)
);

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$statistics = $connection
    ->query(
        "SELECT
            COUNT(*) AS total_feedback,

            COALESCE(
                SUM(status = 'visible'),
                0
            ) AS visible_feedback,

            COALESCE(
                SUM(status = 'hidden'),
                0
            ) AS hidden_feedback,

            COALESCE(
                ROUND(AVG(rating), 1),
                0
            ) AS average_rating,

            COALESCE(
                SUM(
                    admin_reply IS NOT NULL
                    AND TRIM(admin_reply) <> ''
                ),
                0
            ) AS replied_feedback

         FROM feedback"
    )
    ->fetch();

$totalFeedback = (int) (
    $statistics['total_feedback'] ?? 0
);

$visibleFeedback = (int) (
    $statistics['visible_feedback'] ?? 0
);

$hiddenFeedback = (int) (
    $statistics['hidden_feedback'] ?? 0
);

$averageRating = (float) (
    $statistics['average_rating'] ?? 0
);

$repliedFeedback = (int) (
    $statistics['replied_feedback'] ?? 0
);

/*
|--------------------------------------------------------------------------
| Load feedback records
|--------------------------------------------------------------------------
*/

$feedbackQuery =
    'SELECT
        feedback.id,
        feedback.booking_id,
        feedback.customer_id,
        feedback.rating,
        feedback.comments,
        feedback.status,
        feedback.admin_reply,
        feedback.replied_at,
        feedback.created_at,
        feedback.updated_at,

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
        venues.location AS venue_location,

        repliers.full_name AS replied_by_name

     FROM feedback

     INNER JOIN bookings
        ON bookings.id = feedback.booking_id

     LEFT JOIN users AS customers
        ON customers.id = feedback.customer_id

     LEFT JOIN packages
        ON packages.id = bookings.package_id

     LEFT JOIN venues
        ON venues.id = bookings.venue_id

     LEFT JOIN users AS repliers
        ON repliers.id = feedback.replied_by

     WHERE 1 = 1';

$feedbackParameters = [];

if ($statusFilter !== 'all') {
    $feedbackQuery .=
        ' AND feedback.status = ?';

    $feedbackParameters[] =
        $statusFilter;
}

if ($ratingFilter !== 'all') {
    $feedbackQuery .=
        ' AND feedback.rating = ?';

    $feedbackParameters[] =
        (int) $ratingFilter;
}

if ($replyFilter === 'replied') {
    $feedbackQuery .=
        " AND feedback.admin_reply IS NOT NULL
          AND TRIM(feedback.admin_reply) <> ''";
}

if ($replyFilter === 'unanswered') {
    $feedbackQuery .=
        " AND (
            feedback.admin_reply IS NULL
            OR TRIM(feedback.admin_reply) = ''
        )";
}

if ($search !== '') {
    $feedbackQuery .=
        ' AND (
            bookings.booking_code LIKE ?
            OR bookings.event_type LIKE ?
            OR customers.full_name LIKE ?
            OR customers.email LIKE ?
            OR customers.phone LIKE ?
            OR packages.name LIKE ?
            OR venues.name LIKE ?
            OR venues.location LIKE ?
            OR feedback.comments LIKE ?
            OR feedback.admin_reply LIKE ?
        )';

    $searchValue = '%' . $search . '%';

    for ($index = 0; $index < 10; $index++) {
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
    $feedbackId = (int) $feedbackItem['id'];

    $customerName = trim(
        (string) (
            $feedbackItem['customer_name']
            ?? ''
        )
    );

    if ($customerName === '') {
        $customerName = 'Deleted Customer';
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
        $packageName = 'Package unavailable';
    }

    $venueName = trim(
        (string) (
            $feedbackItem['venue_name']
            ?? ''
        )
    );

    if ($venueName === '') {
        $venueName = 'Venue unavailable';
    }

    $venueLocation = trim(
        (string) (
            $feedbackItem['venue_location']
            ?? ''
        )
    );

    $adminReply = trim(
        (string) (
            $feedbackItem['admin_reply']
            ?? ''
        )
    );

    $repliedByName = trim(
        (string) (
            $feedbackItem['replied_by_name']
            ?? ''
        )
    );

    $feedbackStatus =
        admin_feedback_status(
            $feedbackItem['status']
            ?? ''
        );

    $rating = max(
        1,
        min(
            5,
            (int) $feedbackItem['rating']
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
            admin_feedback_event_name(
                $feedbackItem['event_type']
                ?? ''
            ),

        'eventDate' =>
            admin_feedback_date(
                $feedbackItem['event_date']
                ?? null
            ),

        'eventTime' =>
            !empty($feedbackItem['event_time'])
                ? admin_feedback_date(
                    $feedbackItem['event_time'],
                    'h:i A'
                )
                : 'Not selected',

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

        'customerName' => $customerName,

        'customerEmail' =>
            $customerEmail !== ''
                ? $customerEmail
                : 'Email unavailable',

        'customerPhone' =>
            $customerPhone !== ''
                ? $customerPhone
                : 'Phone unavailable',

        'packageName' => $packageName,

        'venueName' =>
            $venueName
            . (
                $venueLocation !== ''
                    ? ' — ' . $venueLocation
                    : ''
            ),

        'rating' => $rating,

        'ratingText' =>
            admin_feedback_rating_text(
                $rating
            ),

        'comments' => (string) (
            $feedbackItem['comments']
            ?? ''
        ),

        'status' => $feedbackStatus,

        'statusLabel' =>
            ucfirst($feedbackStatus),

        'submittedAt' =>
            admin_feedback_date(
                $feedbackItem['created_at']
                ?? null,
                'd F Y, h:i A'
            ),

        'adminReply' => $adminReply,

        'repliedBy' =>
            $repliedByName !== ''
                ? $repliedByName
                : 'Administrator',

        'repliedAt' =>
            !empty($feedbackItem['replied_at'])
                ? admin_feedback_date(
                    $feedbackItem['replied_at'],
                    'd F Y, h:i A'
                )
                : '',
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
        Manage Feedback | <?= e(APP_NAME) ?>
    </title>

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/admin_feedback.css')
        ) ?>"
    >
</head>

<body class="admin-feedback-page">

    <aside
        class="admin-feedback-sidebar"
        id="adminFeedbackSidebar"
    >

        <div class="admin-feedback-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="admin-feedback-profile">

            <img
                src="<?= e($adminImage) ?>"
                alt="Administrator profile"
            >

            <h2>
                <?= e($admin['full_name']) ?>
            </h2>

            <p>System Administrator</p>

            <div class="admin-feedback-online">
                ● Online
            </div>

        </div>

<nav class="admin-feedback-menu">

    <a
        href="<?= e(
            url('/admin/dashboard.php')
        ) ?>"
    >
        <i class="fa-solid fa-house"></i>
        Dashboard
    </a>

    <a
        href="<?= e(
            url('/admin/bookings.php')
        ) ?>"
    >
        <i class="fa-solid fa-calendar-check"></i>
        Manage Bookings
    </a>

    <a
        href="<?= e(
            url('/admin/packages.php')
        ) ?>"
    >
        <i class="fa-solid fa-gift"></i>
        Manage Packages
    </a>

    <a
        href="<?= e(
            url('/admin/venues.php')
        ) ?>"
    >
        <i class="fa-solid fa-hotel"></i>
        Manage Venues
    </a>

    <a
        href="<?= e(
            url('/admin/services.php')
        ) ?>"
    >
        <i class="fa-solid fa-bell-concierge"></i>
        Manage Services
    </a>

    <a
        href="<?= e(
            url('/admin/gallery.php')
        ) ?>"
    >
        <i class="fa-solid fa-images"></i>
        View Gallery
    </a>

    <a
        class="active"
        href="<?= e(
            url('/admin/feedback.php')
        ) ?>"
    >
        <i class="fa-solid fa-comment-dots"></i>
        View Feedback
    </a>

    <a
        href="<?= e(
            url('/admin/staff.php')
        ) ?>"
    >
        <i class="fa-solid fa-users-gear"></i>
        Manage Staff
    </a>

    <a
        href="<?= e(
            url('/admin/notifications.php')
        ) ?>"
    >
        <i class="fa-solid fa-bell"></i>
        Notifications
    </a>

    <a
        href="<?= e(
            url('/admin/profile.php')
        ) ?>"
    >
        <i class="fa-solid fa-user"></i>
        Manage Profile
    </a>

    <a
        class="admin-feedback-logout"
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
        class="admin-feedback-sidebar-overlay"
        id="adminFeedbackSidebarOverlay"
    ></div>

    <main class="admin-feedback-main">

        <header class="admin-feedback-topbar">

            <div class="admin-feedback-topbar-left">

                <button
                    class="admin-feedback-menu-button"
                    id="adminFeedbackMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="admin-feedback-heading">

                    <h1>Customer Feedback</h1>

                    <p>
                        Review customer experiences, manage
                        visibility and respond to feedback.
                    </p>

                </div>

            </div>

            <div class="admin-feedback-topbar-right">

                <div class="admin-feedback-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="admin-feedback-home-link"
                    href="<?= e(url('/index.php')) ?>"
                    aria-label="Open public website"
                >
                    <i class="fa-solid fa-globe"></i>
                </a>

                <a
                    href="<?= e(
                        url('/admin/profile.php')
                    ) ?>"
                >
                    <img
                        class="admin-feedback-profile-image"
                        src="<?= e($adminImage) ?>"
                        alt="Administrator profile"
                    >
                </a>

            </div>

        </header>

        <?php if ($flash): ?>

            <div
                class="admin-feedback-alert <?= $flash['type'] === 'success'
                    ? 'success'
                    : 'danger' ?>"
            >
                <?= e($flash['message']) ?>
            </div>

        <?php endif; ?>

        <?php if ($errors !== []): ?>

            <div class="admin-feedback-alert danger">

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>

            </div>

        <?php endif; ?>

        <section class="admin-feedback-summary">

            <article class="admin-feedback-summary-card">

                <div
                    class="admin-feedback-summary-icon total"
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

            <article class="admin-feedback-summary-card">

                <div
                    class="admin-feedback-summary-icon visible"
                >
                    <i class="fa-solid fa-eye"></i>
                </div>

                <div>
                    <h4>Visible Feedback</h4>

                    <h2>
                        <?= e(
                            number_format($visibleFeedback)
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="admin-feedback-summary-card">

                <div
                    class="admin-feedback-summary-icon hidden"
                >
                    <i class="fa-solid fa-eye-slash"></i>
                </div>

                <div>
                    <h4>Hidden Feedback</h4>

                    <h2>
                        <?= e(
                            number_format($hiddenFeedback)
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="admin-feedback-summary-card">

                <div
                    class="admin-feedback-summary-icon rating"
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

            <article class="admin-feedback-summary-card">

                <div
                    class="admin-feedback-summary-icon replied"
                >
                    <i class="fa-solid fa-reply"></i>
                </div>

                <div>
                    <h4>Admin Replies</h4>

                    <h2>
                        <?= e(
                            number_format($repliedFeedback)
                        ) ?>
                    </h2>
                </div>

            </article>

        </section>

        <section class="admin-feedback-filter-box">

            <form
                class="admin-feedback-filter-form"
                method="get"
            >

                <div class="admin-feedback-filter-field">

                    <label for="search">
                        Search Feedback
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Customer, booking, package or comment"
                    >

                </div>

                <div class="admin-feedback-filter-field">

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

                <div class="admin-feedback-filter-field">

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

                <div class="admin-feedback-filter-field">

                    <label for="reply">
                        Reply Status
                    </label>

                    <select
                        id="reply"
                        name="reply"
                    >
                        <option
                            value="all"
                            <?= $replyFilter === 'all'
                                ? 'selected'
                                : '' ?>
                        >
                            All Feedback
                        </option>

                        <option
                            value="replied"
                            <?= $replyFilter === 'replied'
                                ? 'selected'
                                : '' ?>
                        >
                            Replied
                        </option>

                        <option
                            value="unanswered"
                            <?= $replyFilter === 'unanswered'
                                ? 'selected'
                                : '' ?>
                        >
                            Awaiting Reply
                        </option>
                    </select>

                </div>

                <button
                    class="admin-feedback-filter-button"
                    type="submit"
                >
                    Apply Filter
                </button>

                <a
                    class="admin-feedback-clear-button"
                    href="<?= e(
                        url('/admin/feedback.php')
                    ) ?>"
                >
                    Clear
                </a>

            </form>

        </section>

        <section class="admin-feedback-table-box">

            <div class="admin-feedback-table-heading">

                <div>
                    <h2>Customer Reviews</h2>

                    <p>
                        <?= e(
                            number_format(
                                count($feedbackItems)
                            )
                        ) ?>
                        feedback record(s) currently shown.
                    </p>
                </div>

                <a
                    class="admin-feedback-public-link"
                    href="<?= e(url('/index.php')) ?>"
                >
                    <i class="fa-solid fa-globe"></i>
                    Open Website
                </a>

            </div>

            <?php if ($feedbackItems === []): ?>

                <div class="admin-feedback-empty">

                    <i class="fa-regular fa-comments"></i>

                    <h3>No feedback found</h3>

                    <p>
                        No customer feedback matches the
                        selected search and filter options.
                    </p>

                    <a
                        href="<?= e(
                            url('/admin/feedback.php')
                        ) ?>"
                    >
                        View All Feedback
                    </a>

                </div>

            <?php else: ?>

                <div class="admin-feedback-table-wrapper">

                    <table class="admin-feedback-table">

                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Customer</th>
                                <th>Event</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Submitted</th>
                                <th>Visibility</th>
                                <th>Reply</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach (
                                $feedbackItems
                                as $feedbackItem
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
                                            $feedbackItem[
                                                'rating'
                                            ]
                                            ?? 1
                                        )
                                    )
                                );

                                $feedbackStatus =
                                    admin_feedback_status(
                                        $feedbackItem[
                                            'status'
                                        ]
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

                                $customerEmail = trim(
                                    (string) (
                                        $feedbackItem[
                                            'customer_email'
                                        ]
                                        ?? ''
                                    )
                                );

                                $eventType =
                                    admin_feedback_event_name(
                                        $feedbackItem[
                                            'event_type'
                                        ]
                                        ?? ''
                                    );

                                $comments = trim(
                                    (string) (
                                        $feedbackItem[
                                            'comments'
                                        ]
                                        ?? ''
                                    )
                                );

                                $hasReply =
                                    trim(
                                        (string) (
                                            $feedbackItem[
                                                'admin_reply'
                                            ]
                                            ?? ''
                                        )
                                    ) !== '';
                                ?>

                                <tr>

                                    <td>
                                        <span
                                            class="admin-feedback-reference"
                                        >
                                            <?= e(
                                                (string) (
                                                    $feedbackItem[
                                                        'booking_code'
                                                    ]
                                                    ?? ''
                                                )
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div
                                            class="admin-feedback-customer"
                                        >
                                            <strong>
                                                <?= e(
                                                    $customerName
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= e(
                                                    $customerEmail !== ''
                                                        ? $customerEmail
                                                        : 'Email unavailable'
                                                ) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        <div
                                            class="admin-feedback-event"
                                        >
                                            <strong>
                                                <?= e($eventType) ?>
                                            </strong>

                                            <span>
                                                <?= e(
                                                    admin_feedback_date(
                                                        $feedbackItem[
                                                            'event_date'
                                                        ]
                                                        ?? null
                                                    )
                                                ) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="admin-feedback-stars">

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

                                            <span
                                                class="admin-feedback-rating-number"
                                            >
                                                <?= e(
                                                    (string) $rating
                                                ) ?>
                                                / 5
                                            </span>

                                        </div>
                                    </td>

                                    <td>
                                        <div
                                            class="admin-feedback-comment-preview"
                                            title="<?= e($comments) ?>"
                                        >
                                            <?= e($comments) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?= e(
                                            admin_feedback_date(
                                                $feedbackItem[
                                                    'created_at'
                                                ]
                                                ?? null,
                                                'd M Y'
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <span
                                            class="admin-feedback-status <?= e(
                                                $feedbackStatus
                                            ) ?>"
                                        >
                                            <?= e(
                                                ucfirst(
                                                    $feedbackStatus
                                                )
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span
                                            class="admin-feedback-reply-status <?= $hasReply
                                                ? 'replied'
                                                : 'unanswered' ?>"
                                        >
                                            <?= $hasReply
                                                ? 'Replied'
                                                : 'Awaiting Reply' ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div
                                            class="admin-feedback-actions"
                                        >

                                            <form
                                                class="admin-feedback-status-form"
                                                method="post"
                                            >
                                                <?= csrf_field() ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="update_status"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="feedback_id"
                                                    value="<?= e(
                                                        (string) $feedbackId
                                                    ) ?>"
                                                >

                                                <select
                                                    name="feedback_status"
                                                    aria-label="Feedback visibility"
                                                >
                                                    <option
                                                        value="visible"
                                                        <?= $feedbackStatus
                                                            === 'visible'
                                                                ? 'selected'
                                                                : '' ?>
                                                    >
                                                        Visible
                                                    </option>

                                                    <option
                                                        value="hidden"
                                                        <?= $feedbackStatus
                                                            === 'hidden'
                                                                ? 'selected'
                                                                : '' ?>
                                                    >
                                                        Hidden
                                                    </option>
                                                </select>

                                                <button
                                                    class="admin-feedback-update-button"
                                                    type="submit"
                                                >
                                                    Update
                                                </button>
                                            </form>

                                            <button
                                                class="admin-feedback-view-button"
                                                type="button"
                                                data-feedback-id="<?= e(
                                                    (string) $feedbackId
                                                ) ?>"
                                            >
                                                Details
                                            </button>

                                        </div>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

        <footer class="admin-feedback-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <div
        class="admin-feedback-modal"
        id="adminFeedbackModal"
    >

        <div class="admin-feedback-modal-content">

            <button
                class="admin-feedback-modal-close"
                id="adminFeedbackModalClose"
                type="button"
                aria-label="Close feedback details"
            >
                &times;
            </button>

            <div class="admin-feedback-modal-header">

                <h2 id="adminFeedbackModalTitle">
                    Customer Feedback
                </h2>

                <div
                    class="admin-feedback-modal-reference"
                    id="adminFeedbackModalReference"
                ></div>

            </div>

            <div
                class="admin-feedback-modal-status-row"
            >

                <div class="admin-feedback-modal-badges">

                    <span
                        class="admin-feedback-status visible"
                        id="adminFeedbackModalStatus"
                    >
                        Visible
                    </span>

                    <span
                        class="admin-feedback-reply-status unanswered"
                        id="adminFeedbackModalReplyStatus"
                    >
                        Awaiting Reply
                    </span>

                </div>

                <span id="adminFeedbackModalSubmitted"></span>

            </div>

            <div class="admin-feedback-modal-grid">

                <div class="admin-feedback-modal-item">
                    <strong>Customer Name</strong>
                    <span id="adminFeedbackCustomerName"></span>
                </div>

                <div class="admin-feedback-modal-item">
                    <strong>Customer Email</strong>
                    <span id="adminFeedbackCustomerEmail"></span>
                </div>

                <div class="admin-feedback-modal-item">
                    <strong>Customer Phone</strong>
                    <span id="adminFeedbackCustomerPhone"></span>
                </div>

                <div class="admin-feedback-modal-item">
                    <strong>Event Date</strong>
                    <span id="adminFeedbackEventDate"></span>
                </div>

                <div class="admin-feedback-modal-item">
                    <strong>Event Time</strong>
                    <span id="adminFeedbackEventTime"></span>
                </div>

                <div class="admin-feedback-modal-item">
                    <strong>Guests</strong>
                    <span id="adminFeedbackGuests"></span>
                </div>

                <div class="admin-feedback-modal-item">
                    <strong>Package</strong>
                    <span id="adminFeedbackPackage"></span>
                </div>

                <div class="admin-feedback-modal-item">
                    <strong>Venue</strong>
                    <span id="adminFeedbackVenue"></span>
                </div>

                <div class="admin-feedback-modal-item">
                    <strong>Booking Status</strong>
                    <span id="adminFeedbackBookingStatus"></span>
                </div>

                <div class="admin-feedback-modal-item">
                    <strong>Booking Total</strong>
                    <span id="adminFeedbackBookingTotal"></span>
                </div>

            </div>

            <div class="admin-feedback-modal-section">

                <h3>
                    Customer Rating:
                    <span id="adminFeedbackRatingText"></span>
                </h3>

                <div
                    class="admin-feedback-modal-stars"
                    id="adminFeedbackModalStars"
                ></div>

            </div>

            <div class="admin-feedback-modal-section">

                <h3>Customer Comments</h3>

                <div
                    class="admin-feedback-modal-text"
                    id="adminFeedbackComments"
                ></div>

            </div>

            <div
                class="admin-feedback-modal-section"
                id="adminFeedbackExistingReplySection"
            >

                <h3>Current Administrator Reply</h3>

                <div
                    class="admin-feedback-existing-reply"
                >
                    <p
                        id="adminFeedbackExistingReply"
                    ></p>

                    <small
                        id="adminFeedbackReplyInformation"
                    ></small>
                </div>

            </div>

            <div class="admin-feedback-modal-form-box">

                <h3>Update Visibility</h3>

                <form
                    class="admin-feedback-visibility-form"
                    method="post"
                >
                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="update_status"
                    >

                    <input
                        type="hidden"
                        name="feedback_id"
                        id="adminFeedbackStatusId"
                        value=""
                    >

                    <select
                        name="feedback_status"
                        id="adminFeedbackStatusSelect"
                        required
                    >
                        <option value="visible">
                            Visible
                        </option>

                        <option value="hidden">
                            Hidden
                        </option>
                    </select>

                    <button type="submit">
                        Save Visibility
                    </button>
                </form>

            </div>

            <div class="admin-feedback-modal-form-box">

                <h3>Reply to Customer</h3>

                <form
                    class="admin-feedback-reply-form"
                    method="post"
                >
                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="save_reply"
                    >

                    <input
                        type="hidden"
                        name="feedback_id"
                        id="adminFeedbackReplyId"
                        value=""
                    >

                    <textarea
                        name="admin_reply"
                        id="adminFeedbackReplyText"
                        minlength="2"
                        maxlength="2000"
                        placeholder="Write a professional response to the customer."
                        required
                    ></textarea>

                    <button type="submit">
                        Save Administrator Reply
                    </button>
                </form>

            </div>

        </div>

    </div>

    <script>
        const adminFeedbackRecords =
            <?= $modalFeedbackJson ?>;

        const adminFeedbackSidebar =
            document.getElementById(
                "adminFeedbackSidebar"
            );

        const adminFeedbackSidebarOverlay =
            document.getElementById(
                "adminFeedbackSidebarOverlay"
            );

        const adminFeedbackMenuButton =
            document.getElementById(
                "adminFeedbackMenuButton"
            );

        function closeAdminFeedbackSidebar() {
            adminFeedbackSidebar.classList.remove(
                "open"
            );

            adminFeedbackSidebarOverlay.classList.remove(
                "open"
            );
        }

        adminFeedbackMenuButton.addEventListener(
            "click",
            function () {
                adminFeedbackSidebar.classList.toggle(
                    "open"
                );

                adminFeedbackSidebarOverlay.classList.toggle(
                    "open"
                );
            }
        );

        adminFeedbackSidebarOverlay.addEventListener(
            "click",
            closeAdminFeedbackSidebar
        );

        const feedbackModal =
            document.getElementById(
                "adminFeedbackModal"
            );

        const feedbackModalClose =
            document.getElementById(
                "adminFeedbackModalClose"
            );

        function createFeedbackStars(rating) {
            const starsContainer =
                document.getElementById(
                    "adminFeedbackModalStars"
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

                starsContainer.appendChild(icon);
            }
        }

        function openAdminFeedbackModal(
            feedbackId
        ) {
            const record =
                adminFeedbackRecords[
                    String(feedbackId)
                ];

            if (!record) {
                return;
            }

            document.getElementById(
                "adminFeedbackModalTitle"
            ).textContent =
                record.eventType;

            document.getElementById(
                "adminFeedbackModalReference"
            ).textContent =
                "Booking Reference: "
                + record.bookingCode;

            document.getElementById(
                "adminFeedbackCustomerName"
            ).textContent =
                record.customerName;

            document.getElementById(
                "adminFeedbackCustomerEmail"
            ).textContent =
                record.customerEmail;

            document.getElementById(
                "adminFeedbackCustomerPhone"
            ).textContent =
                record.customerPhone;

            document.getElementById(
                "adminFeedbackEventDate"
            ).textContent =
                record.eventDate;

            document.getElementById(
                "adminFeedbackEventTime"
            ).textContent =
                record.eventTime;

            document.getElementById(
                "adminFeedbackGuests"
            ).textContent =
                record.guestCount + " guests";

            document.getElementById(
                "adminFeedbackPackage"
            ).textContent =
                record.packageName;

            document.getElementById(
                "adminFeedbackVenue"
            ).textContent =
                record.venueName;

            document.getElementById(
                "adminFeedbackBookingStatus"
            ).textContent =
                record.bookingStatus;

            document.getElementById(
                "adminFeedbackBookingTotal"
            ).textContent =
                "Rs. " + record.totalAmount;

            document.getElementById(
                "adminFeedbackRatingText"
            ).textContent =
                record.rating
                + " / 5 — "
                + record.ratingText;

            document.getElementById(
                "adminFeedbackComments"
            ).textContent =
                record.comments;

            document.getElementById(
                "adminFeedbackModalSubmitted"
            ).textContent =
                "Submitted "
                + record.submittedAt;

            const statusBadge =
                document.getElementById(
                    "adminFeedbackModalStatus"
                );

            statusBadge.textContent =
                record.statusLabel;

            statusBadge.className =
                "admin-feedback-status "
                + record.status;

            const hasReply =
                record.adminReply.trim() !== "";

            const replyStatus =
                document.getElementById(
                    "adminFeedbackModalReplyStatus"
                );

            replyStatus.textContent =
                hasReply
                    ? "Replied"
                    : "Awaiting Reply";

            replyStatus.className =
                "admin-feedback-reply-status "
                + (
                    hasReply
                        ? "replied"
                        : "unanswered"
                );

            const replySection =
                document.getElementById(
                    "adminFeedbackExistingReplySection"
                );

            if (hasReply) {
                replySection.style.display =
                    "block";

                document.getElementById(
                    "adminFeedbackExistingReply"
                ).textContent =
                    record.adminReply;

                document.getElementById(
                    "adminFeedbackReplyInformation"
                ).textContent =
                    "Replied by "
                    + record.repliedBy
                    + (
                        record.repliedAt
                            ? " on "
                                + record.repliedAt
                            : ""
                    );
            } else {
                replySection.style.display =
                    "none";
            }

            document.getElementById(
                "adminFeedbackStatusId"
            ).value =
                record.id;

            document.getElementById(
                "adminFeedbackReplyId"
            ).value =
                record.id;

            document.getElementById(
                "adminFeedbackStatusSelect"
            ).value =
                record.status;

            document.getElementById(
                "adminFeedbackReplyText"
            ).value =
                record.adminReply;

            createFeedbackStars(
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
                        openAdminFeedbackModal(
                            button.dataset.feedbackId
                        );
                    }
                );
            });

        function closeAdminFeedbackModal() {
            feedbackModal.classList.remove(
                "open"
            );

            document.body.style.overflow =
                "";
        }

        feedbackModalClose.addEventListener(
            "click",
            closeAdminFeedbackModal
        );

        feedbackModal.addEventListener(
            "click",
            function (event) {
                if (event.target === feedbackModal) {
                    closeAdminFeedbackModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (event.key === "Escape") {
                    closeAdminFeedbackModal();
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
            openAdminFeedbackModal(
                requestedFeedbackId
            );
        }
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>