<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/role_check.php';
require_once __DIR__ . '/notification_helpers.php';

if (
    !isset($notificationsRole)
    || !is_string($notificationsRole)
) {
    http_response_code(500);
    exit('Notification role was not configured.');
}

$allowedNotificationRoles = [
    'admin',
    'event_manager',
    'booking_manager',
];

if (
    !in_array(
        $notificationsRole,
        $allowedNotificationRoles,
        true
    )
) {
    http_response_code(403);
    exit('Invalid notification role.');
}

require_role($notificationsRole);

$connection = db();
$userId = (int) $_SESSION['user_id'];
$errors = [];
$flash = get_flash();

/*
|--------------------------------------------------------------------------
| Role configuration
|--------------------------------------------------------------------------
*/

$roleConfiguration = [
    'admin' => [
        'label' => 'System Administrator',
        'title' => 'Admin Notifications',
        'description' =>
            'View important system and management updates.',
        'notification_path' =>
            '/admin/notifications.php',
        'profile_path' =>
            '/admin/profile.php',

        'menu' => [
            [
                'Dashboard',
                'fa-house',
                '/admin/dashboard.php',
            ],
            [
                'Manage Bookings',
                'fa-calendar-check',
                '/admin/bookings.php',
            ],
            [
                'Manage Packages',
                'fa-gift',
                '/admin/packages.php',
            ],
            [
                'Manage Venues',
                'fa-hotel',
                '/admin/venues.php',
            ],
            [
                'Manage Services',
                'fa-bell-concierge',
                '/admin/services.php',
            ],
            [
                'Manage Staff',
                'fa-users-gear',
                '/admin/staff.php',
            ],
            [
                'View Gallery',
                'fa-images',
                '/admin/gallery.php',
            ],
            [
                'View Feedback',
                'fa-comment-dots',
                '/admin/feedback.php',
            ],
            [
                'Notifications',
                'fa-bell',
                '/admin/notifications.php',
            ],
            [
                'Manage Profile',
                'fa-user',
                '/admin/profile.php',
            ],
        ],
    ],

    'event_manager' => [
        'label' => 'Event Manager',
        'title' => 'Event Manager Notifications',
        'description' =>
            'View event, task and gallery updates.',
        'notification_path' =>
            '/event_manager/notifications.php',
        'profile_path' =>
            '/event_manager/profile.php',

        'menu' => [
            [
                'Dashboard',
                'fa-house',
                '/event_manager/dashboard.php',
            ],
            [
                'Assigned Tasks',
                'fa-list-check',
                '/event_manager/assigned_tasks.php',
            ],
            [
                'Notifications',
                'fa-bell',
                '/event_manager/notifications.php',
            ],
            [
                'Manage Profile',
                'fa-user',
                '/event_manager/profile.php',
            ],
            [
                'Gallery Management',
                'fa-images',
                '/event_manager/gallery.php',
            ],
            [
                'Feedback',
                'fa-comment-dots',
                '/event_manager/feedback.php',
            ],
        ],
    ],

    'booking_manager' => [
        'label' => 'Booking Manager',
        'title' => 'Booking Manager Notifications',
        'description' =>
            'View booking and venue availability updates.',
        'notification_path' =>
            '/booking_manager/notifications.php',
        'profile_path' =>
            '/booking_manager/profile.php',

        'menu' => [
            [
                'Dashboard',
                'fa-house',
                '/booking_manager/dashboard.php',
            ],
            [
                'Manage Bookings',
                'fa-calendar-check',
                '/booking_manager/bookings.php',
            ],
            [
                'Create Booking',
                'fa-calendar-plus',
                '/booking_manager/booking.php',
            ],
            [
                'View Services',
                'fa-bell-concierge',
                '/booking_manager/services.php',
            ],
            [
                'View Gallery',
                'fa-images',
                '/booking_manager/gallery.php',
            ],
            [
                'View Packages',
                'fa-gift',
                '/booking_manager/packages.php',
            ],
            [
                'View Venues',
                'fa-hotel',
                '/booking_manager/venues.php',
            ],
            [
                'Manage Profile',
                'fa-user',
                '/booking_manager/profile.php',
            ],
            [
                'View Notifications',
                'fa-bell',
                '/booking_manager/notifications.php',
            ],
        ],
    ],
];

$config =
    $roleConfiguration[$notificationsRole];

$notificationPagePath =
    $config['notification_path'];

$profilePath =
    $config['profile_path'];

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function notifications_date(
    mixed $date
): string {
    $timestamp = strtotime((string) $date);

    if ($timestamp === false) {
        return 'Date unavailable';
    }

    $today = date('Y-m-d');
    $dateValue = date('Y-m-d', $timestamp);

    if ($dateValue === $today) {
        return 'Today, '
            . date('h:i A', $timestamp);
    }

    if (
        $dateValue
        === date(
            'Y-m-d',
            strtotime('-1 day')
        )
    ) {
        return 'Yesterday, '
            . date('h:i A', $timestamp);
    }

    return date(
        'd F Y, h:i A',
        $timestamp
    );
}

function notifications_type(
    mixed $type
): string {
    $type = strtolower(
        trim((string) $type)
    );

    $allowedTypes = [
        'general',
        'system',
        'booking',
        'task',
        'feedback',
        'gallery',
    ];

    return in_array(
        $type,
        $allowedTypes,
        true
    )
        ? $type
        : 'general';
}

function notifications_type_label(
    string $type
): string {
    return match ($type) {
        'system' => 'System',
        'booking' => 'Booking',
        'task' => 'Task',
        'feedback' => 'Feedback',
        'gallery' => 'Gallery',
        default => 'General',
    };
}

function notifications_icon(
    string $type
): string {
    return match ($type) {
        'system' => 'fa-gear',
        'booking' => 'fa-calendar-check',
        'task' => 'fa-list-check',
        'feedback' => 'fa-comment-dots',
        'gallery' => 'fa-images',
        default => 'fa-bell',
    };
}

function notifications_target_url(
    mixed $targetUrl
): ?string {
    $targetUrl = trim(
        (string) $targetUrl
    );

    if ($targetUrl === '') {
        return null;
    }

    if (
        preg_match(
            '/^https?:\/\//i',
            $targetUrl
        )
    ) {
        return $targetUrl;
    }

    return url(
        '/' . ltrim($targetUrl, '/')
    );
}

/*
|--------------------------------------------------------------------------
| Load account
|--------------------------------------------------------------------------
*/

$userStatement = $connection->prepare(
    'SELECT
        full_name,
        email,
        profile_image
     FROM users
     WHERE id = ?
     AND role = ?
     LIMIT 1'
);

$userStatement->execute([
    $userId,
    $notificationsRole,
]);

$user = $userStatement->fetch();

if (!$user) {
    redirect('/auth/logout.php');
}

$profileImage = !empty($user['profile_image'])
    ? url(
        '/'
        . ltrim(
            (string) $user['profile_image'],
            '/'
        )
    )
    : url('/assets/icons/icon-192.png');

/*
|--------------------------------------------------------------------------
| Notification actions
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $action = trim(
        (string) ($_POST['action'] ?? '')
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

    if (
        !in_array(
            $action,
            [
                'mark_read',
                'mark_unread',
                'mark_all_read',
            ],
            true
        )
    ) {
        $errors[] =
            'Invalid notification action.';
    }

    if (
        $errors === []
        && $action === 'mark_all_read'
    ) {
        try {
            $markAllStatement =
                $connection->prepare(
                    'UPDATE notifications
                     SET is_read = 1,
                         read_at = COALESCE(
                            read_at,
                            NOW()
                         ),
                         updated_at = NOW()
                     WHERE (
                        user_id = ?
                        OR (
                            user_id IS NULL
                            AND user_role = ?
                        )
                     )
                     AND is_read = 0'
                );

            $markAllStatement->execute([
                $userId,
                $notificationsRole,
            ]);

            set_flash(
                'success',
                'All notifications were marked as read.'
            );

            redirect($notificationPagePath);
        } catch (Throwable $exception) {
            $errors[] = APP_DEBUG
                ? 'Notifications could not be updated: '
                    . $exception->getMessage()
                : 'Notifications could not be updated.';
        }
    }

    if (
        $errors === []
        && in_array(
            $action,
            [
                'mark_read',
                'mark_unread',
            ],
            true
        )
    ) {
        $notificationId = max(
            0,
            (int) (
                $_POST['notification_id']
                ?? 0
            )
        );

        if ($notificationId < 1) {
            $errors[] =
                'Select a valid notification.';
        }

        if ($errors === []) {
            try {
                if ($action === 'mark_read') {
                    $updateStatement =
                        $connection->prepare(
                            'UPDATE notifications
                             SET is_read = 1,
                                 read_at = NOW(),
                                 updated_at = NOW()
                             WHERE id = ?
                             AND (
                                user_id = ?
                                OR (
                                    user_id IS NULL
                                    AND user_role = ?
                                )
                             )'
                        );
                } else {
                    $updateStatement =
                        $connection->prepare(
                            'UPDATE notifications
                             SET is_read = 0,
                                 read_at = NULL,
                                 updated_at = NOW()
                             WHERE id = ?
                             AND (
                                user_id = ?
                                OR (
                                    user_id IS NULL
                                    AND user_role = ?
                                )
                             )'
                        );
                }

                $updateStatement->execute([
                    $notificationId,
                    $userId,
                    $notificationsRole,
                ]);

                set_flash(
                    'success',
                    $action === 'mark_read'
                        ? 'Notification marked as read.'
                        : 'Notification marked as unread.'
                );

                redirect($notificationPagePath);
            } catch (Throwable $exception) {
                $errors[] = APP_DEBUG
                    ? 'Notification could not be updated: '
                        . $exception->getMessage()
                    : 'Notification could not be updated.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Filters
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
            'unread',
            'read',
        ],
        true
    )
) {
    $statusFilter = 'all';
}

$typeFilter = strtolower(
    trim(
        (string) ($_GET['type'] ?? 'all')
    )
);

$allowedTypeFilters = [
    'all',
    'general',
    'system',
    'booking',
    'task',
    'feedback',
    'gallery',
];

if (
    !in_array(
        $typeFilter,
        $allowedTypeFilters,
        true
    )
) {
    $typeFilter = 'all';
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$statisticsStatement =
    $connection->prepare(
        'SELECT
            COUNT(*) AS total_notifications,

            COALESCE(
                SUM(is_read = 0),
                0
            ) AS unread_notifications,

            COALESCE(
                SUM(is_read = 1),
                0
            ) AS read_notifications,

            COALESCE(
                SUM(
                    DATE(created_at)
                    = CURDATE()
                ),
                0
            ) AS today_notifications

         FROM notifications

         WHERE (
            user_id = ?
            OR (
                user_id IS NULL
                AND user_role = ?
            )
         )'
    );

$statisticsStatement->execute([
    $userId,
    $notificationsRole,
]);

$statistics =
    $statisticsStatement->fetch();

$totalNotifications = (int) (
    $statistics['total_notifications']
    ?? 0
);

$unreadNotifications = (int) (
    $statistics['unread_notifications']
    ?? 0
);

$readNotifications = (int) (
    $statistics['read_notifications']
    ?? 0
);

$todayNotifications = (int) (
    $statistics['today_notifications']
    ?? 0
);

/*
|--------------------------------------------------------------------------
| Load notifications
|--------------------------------------------------------------------------
*/

$notificationQuery =
    'SELECT
        id,
        title,
        message,
        notification_type,
        target_url,
        is_read,
        read_at,
        created_at

     FROM notifications

     WHERE (
        user_id = ?
        OR (
            user_id IS NULL
            AND user_role = ?
        )
     )';

$notificationParameters = [
    $userId,
    $notificationsRole,
];

if ($statusFilter === 'unread') {
    $notificationQuery .=
        ' AND is_read = 0';
}

if ($statusFilter === 'read') {
    $notificationQuery .=
        ' AND is_read = 1';
}

if ($typeFilter !== 'all') {
    $notificationQuery .=
        ' AND notification_type = ?';

    $notificationParameters[] =
        $typeFilter;
}

if ($search !== '') {
    $notificationQuery .=
        ' AND (
            title LIKE ?
            OR message LIKE ?
        )';

    $searchValue =
        '%' . $search . '%';

    $notificationParameters[] =
        $searchValue;

    $notificationParameters[] =
        $searchValue;
}

$notificationQuery .=
    ' ORDER BY
        is_read ASC,
        created_at DESC,
        id DESC
      LIMIT 100';

$notificationStatement =
    $connection->prepare(
        $notificationQuery
    );

$notificationStatement->execute(
    $notificationParameters
);

$notifications =
    $notificationStatement->fetchAll();

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
        <?= e($config['title']) ?>
        | <?= e(APP_NAME) ?>
    </title>

    <?php require __DIR__ . '/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/notifications.css')
        ) ?>"
    >
</head>

<body class="notifications-page">

    <aside
        class="notifications-sidebar"
        id="notificationsSidebar"
    >

        <div class="notifications-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="notifications-profile">

            <img
                src="<?= e($profileImage) ?>"
                alt="Profile image"
            >

            <h2>
                <?= e($user['full_name']) ?>
            </h2>

            <p>
                <?= e($config['label']) ?>
            </p>

            <div class="notifications-online">
                ● Online
            </div>

        </div>

        <nav class="notifications-menu">

            <?php foreach (
                $config['menu'] as $menuItem
            ): ?>
                <?php
                [$label, $icon, $path] =
                    $menuItem;

                $isActive =
                    $path
                    === $notificationPagePath;
                ?>

                <a
                    class="<?= $isActive
                        ? 'active'
                        : '' ?>"
                    href="<?= e(url($path)) ?>"
                >
                    <i
                        class="fa-solid <?= e($icon) ?>"
                    ></i>

                    <?= e($label) ?>
                </a>

            <?php endforeach; ?>

            <a
                class="notifications-logout"
                href="<?= e(
                    url('/auth/logout.php')
                ) ?>"
            >
                <i
                    class="fa-solid fa-right-from-bracket"
                ></i>

                Logout
            </a>

        </nav>

    </aside>

    <div
        class="notifications-sidebar-overlay"
        id="notificationsSidebarOverlay"
    ></div>

    <main class="notifications-main">

        <header class="notifications-topbar">

            <div class="notifications-topbar-left">

                <button
                    class="notifications-menu-button"
                    id="notificationsMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="notifications-heading">

                    <h1>
                        <?= e($config['title']) ?>
                    </h1>

                    <p>
                        <?= e($config['description']) ?>
                    </p>

                </div>

            </div>

            <div class="notifications-topbar-right">

                <div class="notifications-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="notifications-home-link"
                    href="<?= e(url('/index.php')) ?>"
                    aria-label="Open public website"
                >
                    <i class="fa-solid fa-globe"></i>
                </a>

                <a href="<?= e(url($profilePath)) ?>">

                    <img
                        class="notifications-profile-image"
                        src="<?= e($profileImage) ?>"
                        alt="Profile image"
                    >

                </a>

            </div>

        </header>

        <?php if ($flash): ?>

            <div
                class="notifications-alert <?= $flash['type'] === 'success'
                    ? 'success'
                    : 'danger' ?>"
            >
                <?= e($flash['message']) ?>
            </div>

        <?php endif; ?>

        <?php if ($errors !== []): ?>

            <div class="notifications-alert danger">

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>

            </div>

        <?php endif; ?>

        <section class="notifications-summary">

            <article class="notifications-summary-card">

                <div
                    class="notifications-summary-icon total"
                >
                    <i class="fa-solid fa-bell"></i>
                </div>

                <div>
                    <h4>Total Notifications</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $totalNotifications
                            )
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="notifications-summary-card">

                <div
                    class="notifications-summary-icon unread"
                >
                    <i class="fa-solid fa-envelope"></i>
                </div>

                <div>
                    <h4>Unread</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $unreadNotifications
                            )
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="notifications-summary-card">

                <div
                    class="notifications-summary-icon read"
                >
                    <i
                        class="fa-solid fa-envelope-open"
                    ></i>
                </div>

                <div>
                    <h4>Read</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $readNotifications
                            )
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="notifications-summary-card">

                <div
                    class="notifications-summary-icon today"
                >
                    <i
                        class="fa-solid fa-calendar-day"
                    ></i>
                </div>

                <div>
                    <h4>Received Today</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $todayNotifications
                            )
                        ) ?>
                    </h2>
                </div>

            </article>

        </section>

        <section class="notifications-filter-box">

            <form
                class="notifications-filter-form"
                method="get"
            >

                <div class="notifications-filter-field">

                    <label for="search">
                        Search Notifications
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Search title or message"
                    >

                </div>

                <div class="notifications-filter-field">

                    <label for="status">
                        Read Status
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
                            All Notifications
                        </option>

                        <option
                            value="unread"
                            <?= $statusFilter === 'unread'
                                ? 'selected'
                                : '' ?>
                        >
                            Unread
                        </option>

                        <option
                            value="read"
                            <?= $statusFilter === 'read'
                                ? 'selected'
                                : '' ?>
                        >
                            Read
                        </option>
                    </select>

                </div>

                <div class="notifications-filter-field">

                    <label for="type">
                        Notification Type
                    </label>

                    <select
                        id="type"
                        name="type"
                    >
                        <option
                            value="all"
                            <?= $typeFilter === 'all'
                                ? 'selected'
                                : '' ?>
                        >
                            All Types
                        </option>

                        <?php foreach (
                            [
                                'general',
                                'system',
                                'booking',
                                'task',
                                'feedback',
                                'gallery',
                            ] as $notificationType
                        ): ?>

                            <option
                                value="<?= e(
                                    $notificationType
                                ) ?>"
                                <?= $typeFilter
                                    === $notificationType
                                        ? 'selected'
                                        : '' ?>
                            >
                                <?= e(
                                    notifications_type_label(
                                        $notificationType
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>
                    </select>

                </div>

                <button
                    class="notifications-filter-button"
                    type="submit"
                >
                    Apply Filter
                </button>

                <a
                    class="notifications-clear-button"
                    href="<?= e(
                        url($notificationPagePath)
                    ) ?>"
                >
                    Clear
                </a>

            </form>

        </section>

        <section class="notifications-box">

            <div class="notifications-box-heading">

                <div>
                    <h2>Notification Centre</h2>

                    <p>
                        <?= e(
                            number_format(
                                count($notifications)
                            )
                        ) ?>
                        notification(s) currently shown.
                    </p>
                </div>

                <?php if (
                    $unreadNotifications > 0
                ): ?>

                    <form
                        class="notifications-mark-all-form"
                        method="post"
                    >
                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="mark_all_read"
                        >

                        <button type="submit">
                            <i
                                class="fa-solid fa-check-double"
                            ></i>

                            Mark All as Read
                        </button>
                    </form>

                <?php endif; ?>

            </div>

            <?php if ($notifications === []): ?>

                <div class="notifications-empty">

                    <i class="fa-regular fa-bell-slash"></i>

                    <h3>No notifications found</h3>

                    <p>
                        No notifications match the selected
                        search and filter options.
                    </p>

                    <a
                        href="<?= e(
                            url($notificationPagePath)
                        ) ?>"
                    >
                        View All Notifications
                    </a>

                </div>

            <?php else: ?>

                <div class="notifications-list">

                    <?php foreach (
                        $notifications
                        as $notification
                    ): ?>
                        <?php
                        $notificationId = (int) (
                            $notification['id']
                        );

                        $isRead = (int) (
                            $notification['is_read']
                            ?? 0
                        ) === 1;

                        $notificationType =
                            notifications_type(
                                $notification[
                                    'notification_type'
                                ]
                                ?? ''
                            );

                        $notificationTitle = trim(
                            (string) (
                                $notification['title']
                                ?? ''
                            )
                        );

                        if (
                            $notificationTitle === ''
                        ) {
                            $notificationTitle =
                                'Notification';
                        }

                        $notificationMessage = trim(
                            (string) (
                                $notification['message']
                                ?? ''
                            )
                        );

                        if (
                            $notificationMessage === ''
                        ) {
                            $notificationMessage =
                                'No additional information was provided.';
                        }

                        $targetUrl =
                            notifications_target_url(
                                $notification[
                                    'target_url'
                                ]
                                ?? null
                            );
                        ?>

                        <article
                            class="notification-card <?= $isRead
                                ? ''
                                : 'unread' ?>"
                        >

                            <?php if (!$isRead): ?>
                                <span
                                    class="notification-unread-dot"
                                    aria-label="Unread notification"
                                ></span>
                            <?php endif; ?>

                            <div
                                class="notification-icon <?= e(
                                    $notificationType
                                ) ?>"
                            >
                                <i
                                    class="fa-solid <?= e(
                                        notifications_icon(
                                            $notificationType
                                        )
                                    ) ?>"
                                ></i>
                            </div>

                            <div class="notification-content">

                                <h3>
                                    <?= e(
                                        $notificationTitle
                                    ) ?>
                                </h3>

                                <p><?= e(
                                    $notificationMessage
                                ) ?></p>

                                <div class="notification-meta">

                                    <span
                                        class="notification-type-label"
                                    >
                                        <?= e(
                                            notifications_type_label(
                                                $notificationType
                                            )
                                        ) ?>
                                    </span>

                                    <span>
                                        <i
                                            class="fa-regular fa-clock"
                                        ></i>

                                        <?= e(
                                            notifications_date(
                                                $notification[
                                                    'created_at'
                                                ]
                                                ?? null
                                            )
                                        ) ?>
                                    </span>

                                    <span>
                                        <?= $isRead
                                            ? 'Read'
                                            : 'Unread' ?>
                                    </span>

                                </div>

                            </div>

                            <div class="notification-actions">

                                <form method="post">
                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?= e(
                                            (string) $notificationId
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="<?= $isRead
                                            ? 'mark_unread'
                                            : 'mark_read' ?>"
                                    >

                                    <button
                                        class="notification-action-button"
                                        type="submit"
                                    >
                                        <i
                                            class="fa-solid <?= $isRead
                                                ? 'fa-envelope'
                                                : 'fa-envelope-open' ?>"
                                        ></i>

                                        <?= $isRead
                                            ? 'Mark Unread'
                                            : 'Mark Read' ?>
                                    </button>
                                </form>

                                <?php if (
                                    $targetUrl !== null
                                ): ?>

                                    <a
                                        class="notification-open-link"
                                        href="<?= e($targetUrl) ?>"
                                    >
                                        <i
                                            class="fa-solid fa-arrow-up-right-from-square"
                                        ></i>

                                        Open Details
                                    </a>

                                <?php endif; ?>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <footer class="notifications-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <script>
        const notificationsSidebar =
            document.getElementById(
                "notificationsSidebar"
            );

        const notificationsSidebarOverlay =
            document.getElementById(
                "notificationsSidebarOverlay"
            );

        const notificationsMenuButton =
            document.getElementById(
                "notificationsMenuButton"
            );

        function closeNotificationsSidebar() {
            notificationsSidebar.classList.remove(
                "open"
            );

            notificationsSidebarOverlay.classList.remove(
                "open"
            );
        }

        notificationsMenuButton.addEventListener(
            "click",
            function () {
                notificationsSidebar.classList.toggle(
                    "open"
                );

                notificationsSidebarOverlay.classList.toggle(
                    "open"
                );
            }
        );

        notificationsSidebarOverlay.addEventListener(
            "click",
            closeNotificationsSidebar
        );
    </script>

    <?php require __DIR__ . '/pwa_scripts.php'; ?>

</body>
</html>