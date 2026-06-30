<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

require_role('event_manager');

$connection = db();
$managerId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function assigned_task_status(
    mixed $status
): string {
    $status = strtolower(
        trim((string) $status)
    );

    $status = str_replace(
        [' ', '-'],
        '_',
        $status
    );

    return match ($status) {
        'in_progress',
        'progress' => 'in_progress',

        'completed',
        'complete',
        'done',
        'finished' => 'completed',

        default => 'pending',
    };
}

function assigned_task_status_label(
    string $status
): string {
    return match ($status) {
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        default => 'Pending',
    };
}

function assigned_task_status_class(
    string $status
): string {
    return match ($status) {
        'in_progress' => 'in-progress',
        'completed' => 'completed',
        default => 'pending',
    };
}

function assigned_task_date(
    mixed $date,
    string $format = 'd F Y'
): string {
    $date = trim((string) $date);

    if ($date === '') {
        return 'Not specified';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return 'Not specified';
    }

    return date($format, $timestamp);
}

function assigned_task_time(
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

function assigned_task_event_name(
    mixed $eventType
): string {
    $eventType = trim((string) $eventType);

    return $eventType !== ''
        ? $eventType
        : 'Wedding Event';
}

function assigned_task_is_overdue(
    mixed $dueDate,
    string $status
): bool {
    if ($status === 'completed') {
        return false;
    }

    $dueDate = trim((string) $dueDate);

    if ($dueDate === '') {
        return false;
    }

    $timestamp = strtotime($dueDate);

    if ($timestamp === false) {
        return false;
    }

    return date('Y-m-d', $timestamp)
        < date('Y-m-d');
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

$unreadNotifications =
    unread_notification_count(
        $managerId,
        'event_manager'
    );

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
            'pending',
            'in_progress',
            'completed',
        ],
        true
    )
) {
    $statusFilter = 'all';
}

$requestedTaskId = max(
    0,
    (int) ($_GET['task_id'] ?? 0)
);

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$statisticsStatement =
    $connection->prepare(
        "SELECT
            COUNT(*) AS total_tasks,

            COALESCE(
                SUM(
                    LOWER(
                        REPLACE(
                            REPLACE(
                                task_status,
                                '-',
                                '_'
                            ),
                            ' ',
                            '_'
                        )
                    ) = 'pending'
                ),
                0
            ) AS pending_tasks,

            COALESCE(
                SUM(
                    LOWER(
                        REPLACE(
                            REPLACE(
                                task_status,
                                '-',
                                '_'
                            ),
                            ' ',
                            '_'
                        )
                    ) IN (
                        'in_progress',
                        'progress'
                    )
                ),
                0
            ) AS progress_tasks,

            COALESCE(
                SUM(
                    LOWER(
                        REPLACE(
                            REPLACE(
                                task_status,
                                '-',
                                '_'
                            ),
                            ' ',
                            '_'
                        )
                    ) IN (
                        'completed',
                        'complete',
                        'done',
                        'finished'
                    )
                ),
                0
            ) AS completed_tasks

         FROM assigned_tasks

         WHERE event_manager_id = ?"
    );

$statisticsStatement->execute([
    $managerId,
]);

$statistics =
    $statisticsStatement->fetch();

$totalTasks = (int) (
    $statistics['total_tasks'] ?? 0
);

$pendingTasks = (int) (
    $statistics['pending_tasks'] ?? 0
);

$progressTasks = (int) (
    $statistics['progress_tasks'] ?? 0
);

$completedTasks = (int) (
    $statistics['completed_tasks'] ?? 0
);

/*
|--------------------------------------------------------------------------
| Load assigned tasks
|--------------------------------------------------------------------------
*/

$taskQuery =
    'SELECT
        assigned_tasks.id,
        assigned_tasks.event_manager_id,
        assigned_tasks.booking_id,
        assigned_tasks.task_title,
        assigned_tasks.task_description,
        assigned_tasks.task_status,
        assigned_tasks.due_date,
        assigned_tasks.created_at,
        assigned_tasks.updated_at,

        bookings.booking_code,
        bookings.event_type,
        bookings.event_date,
        bookings.event_time,
        bookings.guest_count,
        bookings.booking_status,

        customers.full_name AS customer_name,
        customers.phone AS customer_phone,

        packages.name AS package_name,

        venues.name AS venue_name,
        venues.location AS venue_location

     FROM assigned_tasks

     LEFT JOIN bookings
        ON bookings.id =
            assigned_tasks.booking_id

     LEFT JOIN users AS customers
        ON customers.id =
            bookings.customer_id

     LEFT JOIN packages
        ON packages.id =
            bookings.package_id

     LEFT JOIN venues
        ON venues.id =
            bookings.venue_id

     WHERE assigned_tasks.event_manager_id = ?';

$taskParameters = [
    $managerId,
];

if ($statusFilter !== 'all') {
    if ($statusFilter === 'pending') {
        $taskQuery .=
            " AND LOWER(
                REPLACE(
                    REPLACE(
                        assigned_tasks.task_status,
                        '-',
                        '_'
                    ),
                    ' ',
                    '_'
                )
              ) = 'pending'";
    }

    if ($statusFilter === 'in_progress') {
        $taskQuery .=
            " AND LOWER(
                REPLACE(
                    REPLACE(
                        assigned_tasks.task_status,
                        '-',
                        '_'
                    ),
                    ' ',
                    '_'
                )
              ) IN (
                'in_progress',
                'progress'
              )";
    }

    if ($statusFilter === 'completed') {
        $taskQuery .=
            " AND LOWER(
                REPLACE(
                    REPLACE(
                        assigned_tasks.task_status,
                        '-',
                        '_'
                    ),
                    ' ',
                    '_'
                )
              ) IN (
                'completed',
                'complete',
                'done',
                'finished'
              )";
    }
}

if ($search !== '') {
    $taskQuery .=
        ' AND (
            assigned_tasks.task_title LIKE ?
            OR assigned_tasks.task_description LIKE ?
            OR bookings.booking_code LIKE ?
            OR bookings.event_type LIKE ?
            OR customers.full_name LIKE ?
            OR packages.name LIKE ?
            OR venues.name LIKE ?
            OR venues.location LIKE ?
        )';

    $searchValue =
        '%' . $search . '%';

    for ($index = 0; $index < 8; $index++) {
        $taskParameters[] =
            $searchValue;
    }
}

$taskQuery .=
    " ORDER BY
        CASE
            WHEN LOWER(
                REPLACE(
                    REPLACE(
                        assigned_tasks.task_status,
                        '-',
                        '_'
                    ),
                    ' ',
                    '_'
                )
            ) = 'pending'
            THEN 1

            WHEN LOWER(
                REPLACE(
                    REPLACE(
                        assigned_tasks.task_status,
                        '-',
                        '_'
                    ),
                    ' ',
                    '_'
                )
            ) IN (
                'in_progress',
                'progress'
            )
            THEN 2

            WHEN LOWER(
                REPLACE(
                    REPLACE(
                        assigned_tasks.task_status,
                        '-',
                        '_'
                    ),
                    ' ',
                    '_'
                )
            ) IN (
                'completed',
                'complete',
                'done',
                'finished'
            )
            THEN 3

            ELSE 4
        END ASC,

        CASE
            WHEN assigned_tasks.due_date IS NULL
            THEN 1
            ELSE 0
        END ASC,

        assigned_tasks.due_date ASC,
        assigned_tasks.created_at DESC";

$taskStatement =
    $connection->prepare($taskQuery);

$taskStatement->execute(
    $taskParameters
);

$assignedTasks =
    $taskStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Prepare modal records
|--------------------------------------------------------------------------
*/

$taskModalRecords = [];

foreach ($assignedTasks as $assignedTask) {
    $taskId =
        (int) $assignedTask['id'];

    $taskTitle = trim(
        (string) (
            $assignedTask['task_title']
            ?? ''
        )
    );

    if ($taskTitle === '') {
        $taskTitle =
            'Assigned Wedding Task';
    }

    $taskDescription = trim(
        (string) (
            $assignedTask['task_description']
            ?? ''
        )
    );

    if ($taskDescription === '') {
        $taskDescription =
            'No additional task instructions were provided.';
    }

    $taskStatus =
        assigned_task_status(
            $assignedTask['task_status']
            ?? ''
        );

    $bookingCode = trim(
        (string) (
            $assignedTask['booking_code']
            ?? ''
        )
    );

    if ($bookingCode === '') {
        $bookingCode =
            'No linked booking';
    }

    $customerName = trim(
        (string) (
            $assignedTask['customer_name']
            ?? ''
        )
    );

    if ($customerName === '') {
        $customerName =
            'Not available';
    }

    $customerPhone = trim(
        (string) (
            $assignedTask['customer_phone']
            ?? ''
        )
    );

    if ($customerPhone === '') {
        $customerPhone =
            'Not available';
    }

    $packageName = trim(
        (string) (
            $assignedTask['package_name']
            ?? ''
        )
    );

    if ($packageName === '') {
        $packageName =
            'Not available';
    }

    $venueName = trim(
        (string) (
            $assignedTask['venue_name']
            ?? ''
        )
    );

    if ($venueName === '') {
        $venueName =
            'Not available';
    }

    $venueLocation = trim(
        (string) (
            $assignedTask['venue_location']
            ?? ''
        )
    );

    $taskModalRecords[
        (string) $taskId
    ] = [
        'id' => $taskId,

        'title' => $taskTitle,

        'description' =>
            $taskDescription,

        'status' => $taskStatus,

        'statusLabel' =>
            assigned_task_status_label(
                $taskStatus
            ),

        'statusClass' =>
            assigned_task_status_class(
                $taskStatus
            ),

        'dueDate' =>
            assigned_task_date(
                $assignedTask['due_date']
                ?? null
            ),

        'createdAt' =>
            assigned_task_date(
                $assignedTask['created_at']
                ?? null,
                'd F Y, h:i A'
            ),

        'bookingCode' => $bookingCode,

        'eventType' =>
            assigned_task_event_name(
                $assignedTask['event_type']
                ?? ''
            ),

        'eventDate' =>
            assigned_task_date(
                $assignedTask['event_date']
                ?? null
            ),

        'eventTime' =>
            assigned_task_time(
                $assignedTask['event_time']
                ?? null
            ),

        'guestCount' => number_format(
            (int) (
                $assignedTask['guest_count']
                ?? 0
            )
        ),

        'bookingStatus' => ucwords(
            str_replace(
                '_',
                ' ',
                (string) (
                    $assignedTask[
                        'booking_status'
                    ]
                    ?? 'Not available'
                )
            )
        ),

        'customerName' =>
            $customerName,

        'customerPhone' =>
            $customerPhone,

        'packageName' =>
            $packageName,

        'venueName' =>
            $venueName
            . (
                $venueLocation !== ''
                    ? ' — ' . $venueLocation
                    : ''
            ),
    ];
}

$taskModalJson = json_encode(
    $taskModalRecords,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);

if (!is_string($taskModalJson)) {
    $taskModalJson = '{}';
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
        Assigned Tasks | <?= e(APP_NAME) ?>
    </title>

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/assigned_tasks.css')
        ) ?>"
    >
</head>

<body class="assigned-tasks-page">

    <aside
        class="assigned-tasks-sidebar"
        id="assignedTasksSidebar"
    >

        <div class="assigned-tasks-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="assigned-tasks-profile">

            <img
                src="<?= e($managerImage) ?>"
                alt="Event Manager profile"
            >

            <h2>
                <?= e($manager['full_name']) ?>
            </h2>

            <p>Event Manager</p>

            <div class="assigned-tasks-online">
                ● Online
            </div>

        </div>

        <nav class="assigned-tasks-menu">

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
                class="active"
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
                class="assigned-tasks-logout"
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
        class="assigned-tasks-sidebar-overlay"
        id="assignedTasksSidebarOverlay"
    ></div>

    <main class="assigned-tasks-main">

        <header class="assigned-tasks-topbar">

            <div class="assigned-tasks-topbar-left">

                <button
                    class="assigned-tasks-menu-button"
                    id="assignedTasksMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="assigned-tasks-heading">

                    <h1>Assigned Tasks</h1>

                    <p>
                        View wedding-event tasks assigned
                        to your Event Manager account.
                    </p>

                </div>

            </div>

            <div class="assigned-tasks-topbar-right">

                <div class="assigned-tasks-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="assigned-tasks-notification"
                    href="<?= e(
                        url(
                            '/event_manager/notifications.php'
                        )
                    ) ?>"
                    aria-label="Open notifications"
                >
                    <i class="fa-solid fa-bell"></i>

                    <?php if (
                        $unreadNotifications > 0
                    ): ?>

                        <span>
                            <?= e(
                                $unreadNotifications > 99
                                    ? '99+'
                                    : (string) $unreadNotifications
                            ) ?>
                        </span>

                    <?php endif; ?>
                </a>

                <a
                    class="assigned-tasks-home-link"
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
                        class="assigned-tasks-profile-image"
                        src="<?= e($managerImage) ?>"
                        alt="Event Manager profile"
                    >
                </a>

            </div>

        </header>

        <div class="assigned-tasks-notice">

            <i class="fa-solid fa-circle-info"></i>

            <span>
                This page displays only tasks assigned to
                your Event Manager account. It does not
                allow tasks belonging to another manager
                to be viewed.
            </span>

        </div>

        <section class="assigned-tasks-summary">

            <article class="assigned-tasks-summary-card">

                <div
                    class="assigned-tasks-summary-icon total"
                >
                    <i class="fa-solid fa-list-check"></i>
                </div>

                <div>
                    <h4>Total Assigned Tasks</h4>

                    <h2>
                        <?= e(
                            number_format($totalTasks)
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="assigned-tasks-summary-card">

                <div
                    class="assigned-tasks-summary-icon pending"
                >
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div>
                    <h4>Pending Tasks</h4>

                    <h2>
                        <?= e(
                            number_format($pendingTasks)
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="assigned-tasks-summary-card">

                <div
                    class="assigned-tasks-summary-icon progress"
                >
                    <i class="fa-solid fa-spinner"></i>
                </div>

                <div>
                    <h4>In Progress</h4>

                    <h2>
                        <?= e(
                            number_format($progressTasks)
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="assigned-tasks-summary-card">

                <div
                    class="assigned-tasks-summary-icon completed"
                >
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    <h4>Completed Tasks</h4>

                    <h2>
                        <?= e(
                            number_format($completedTasks)
                        ) ?>
                    </h2>
                </div>

            </article>

        </section>

        <section class="assigned-tasks-filter-box">

            <form
                class="assigned-tasks-filter-form"
                method="get"
            >

                <div class="assigned-tasks-filter-field">

                    <label for="search">
                        Search Assigned Tasks
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Task, booking, customer, package or venue"
                    >

                </div>

                <div class="assigned-tasks-filter-field">

                    <label for="status">
                        Task Status
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
                            All Tasks
                        </option>

                        <option
                            value="pending"
                            <?= $statusFilter === 'pending'
                                ? 'selected'
                                : '' ?>
                        >
                            Pending
                        </option>

                        <option
                            value="in_progress"
                            <?= $statusFilter === 'in_progress'
                                ? 'selected'
                                : '' ?>
                        >
                            In Progress
                        </option>

                        <option
                            value="completed"
                            <?= $statusFilter === 'completed'
                                ? 'selected'
                                : '' ?>
                        >
                            Completed
                        </option>
                    </select>

                </div>

                <button
                    class="assigned-tasks-filter-button"
                    type="submit"
                >
                    Apply Filter
                </button>

                <a
                    class="assigned-tasks-clear-button"
                    href="<?= e(
                        url(
                            '/event_manager/assigned_tasks.php'
                        )
                    ) ?>"
                >
                    Clear
                </a>

            </form>

        </section>

        <section class="assigned-tasks-box">

            <div class="assigned-tasks-box-heading">

                <div>
                    <h2>Your Wedding Tasks</h2>

                    <p>
                        <?= e(
                            number_format(
                                count($assignedTasks)
                            )
                        ) ?>
                        assigned task(s) currently shown.
                    </p>
                </div>

            </div>

            <?php if ($assignedTasks === []): ?>

                <div class="assigned-tasks-empty">

                    <i
                        class="fa-solid fa-clipboard-check"
                    ></i>

                    <h3>No assigned tasks found</h3>

                    <p>
                        No task currently matches your
                        account and selected filters.
                    </p>

                    <a
                        href="<?= e(
                            url(
                                '/event_manager/assigned_tasks.php'
                            )
                        ) ?>"
                    >
                        View All Assigned Tasks
                    </a>

                </div>

            <?php else: ?>

                <div class="assigned-tasks-grid">

                    <?php foreach (
                        $assignedTasks
                        as $assignedTask
                    ): ?>
                        <?php
                        $taskId =
                            (int) $assignedTask['id'];

                        $taskTitle = trim(
                            (string) (
                                $assignedTask[
                                    'task_title'
                                ]
                                ?? ''
                            )
                        );

                        if ($taskTitle === '') {
                            $taskTitle =
                                'Assigned Wedding Task';
                        }

                        $taskDescription = trim(
                            (string) (
                                $assignedTask[
                                    'task_description'
                                ]
                                ?? ''
                            )
                        );

                        if ($taskDescription === '') {
                            $taskDescription =
                                'No additional task instructions were provided.';
                        }

                        $taskStatus =
                            assigned_task_status(
                                $assignedTask[
                                    'task_status'
                                ]
                                ?? ''
                            );

                        $bookingCode = trim(
                            (string) (
                                $assignedTask[
                                    'booking_code'
                                ]
                                ?? ''
                            )
                        );

                        if ($bookingCode === '') {
                            $bookingCode =
                                'No linked booking';
                        }

                        $eventType =
                            assigned_task_event_name(
                                $assignedTask[
                                    'event_type'
                                ]
                                ?? ''
                            );

                        $customerName = trim(
                            (string) (
                                $assignedTask[
                                    'customer_name'
                                ]
                                ?? ''
                            )
                        );

                        if ($customerName === '') {
                            $customerName =
                                'Not available';
                        }

                        $venueName = trim(
                            (string) (
                                $assignedTask[
                                    'venue_name'
                                ]
                                ?? ''
                            )
                        );

                        if ($venueName === '') {
                            $venueName =
                                'Not available';
                        }

                        $isOverdue =
                            assigned_task_is_overdue(
                                $assignedTask[
                                    'due_date'
                                ]
                                ?? null,
                                $taskStatus
                            );
                        ?>

                        <article class="assigned-task-card">

                            <div
                                class="assigned-task-card-top"
                            >

                                <div>
                                    <h3>
                                        <?= e($taskTitle) ?>
                                    </h3>

                                    <div
                                        class="assigned-task-reference"
                                    >
                                        <?= e($bookingCode) ?>
                                    </div>
                                </div>

                                <span
                                    class="assigned-task-status <?= e(
                                        assigned_task_status_class(
                                            $taskStatus
                                        )
                                    ) ?>"
                                >
                                    <?= e(
                                        assigned_task_status_label(
                                            $taskStatus
                                        )
                                    ) ?>
                                </span>

                            </div>

                            <p
                                class="assigned-task-description"
                            >
                                <?= e(
                                    mb_strlen(
                                        $taskDescription
                                    ) > 190
                                        ? mb_substr(
                                            $taskDescription,
                                            0,
                                            190
                                        ) . '...'
                                        : $taskDescription
                                ) ?>
                            </p>

                            <div
                                class="assigned-task-information"
                            >

                                <div
                                    class="assigned-task-detail"
                                >
                                    <strong>Event</strong>

                                    <?= e($eventType) ?>
                                </div>

                                <div
                                    class="assigned-task-detail"
                                >
                                    <strong>Customer</strong>

                                    <?= e($customerName) ?>
                                </div>

                                <div
                                    class="assigned-task-detail"
                                >
                                    <strong>Event Date</strong>

                                    <?= e(
                                        assigned_task_date(
                                            $assignedTask[
                                                'event_date'
                                            ]
                                            ?? null
                                        )
                                    ) ?>
                                </div>

                                <div
                                    class="assigned-task-detail"
                                >
                                    <strong>Venue</strong>

                                    <?= e($venueName) ?>
                                </div>

                            </div>

                            <div
                                class="assigned-task-due <?= $isOverdue
                                    ? 'overdue'
                                    : '' ?>"
                            >
                                <i
                                    class="fa-regular fa-calendar"
                                ></i>

                                Due:
                                <?= e(
                                    assigned_task_date(
                                        $assignedTask[
                                            'due_date'
                                        ]
                                        ?? null
                                    )
                                ) ?>

                                <?= $isOverdue
                                    ? ' — Overdue'
                                    : '' ?>
                            </div>

                            <button
                                class="assigned-task-view-button"
                                type="button"
                                data-task-id="<?= e(
                                    (string) $taskId
                                ) ?>"
                            >
                                View Task Details
                            </button>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <footer class="assigned-tasks-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <div
        class="assigned-task-modal"
        id="assignedTaskModal"
    >

        <div class="assigned-task-modal-content">

            <button
                class="assigned-task-modal-close"
                id="assignedTaskModalClose"
                type="button"
                aria-label="Close task details"
            >
                &times;
            </button>

            <div class="assigned-task-modal-header">

                <h2 id="assignedTaskModalTitle">
                    Assigned Task
                </h2>

                <div
                    class="assigned-task-modal-reference"
                    id="assignedTaskModalReference"
                ></div>

            </div>

            <div class="assigned-task-modal-topline">

                <span
                    class="assigned-task-status pending"
                    id="assignedTaskModalStatus"
                >
                    Pending
                </span>

                <span
                    class="assigned-task-modal-due"
                    id="assignedTaskModalDue"
                ></span>

            </div>

            <div class="assigned-task-modal-grid">

                <div class="assigned-task-modal-item">
                    <strong>Event Type</strong>
                    <span id="assignedTaskEventType"></span>
                </div>

                <div class="assigned-task-modal-item">
                    <strong>Event Date</strong>
                    <span id="assignedTaskEventDate"></span>
                </div>

                <div class="assigned-task-modal-item">
                    <strong>Event Time</strong>
                    <span id="assignedTaskEventTime"></span>
                </div>

                <div class="assigned-task-modal-item">
                    <strong>Customer</strong>
                    <span id="assignedTaskCustomer"></span>
                </div>

                <div class="assigned-task-modal-item">
                    <strong>Customer Phone</strong>
                    <span id="assignedTaskPhone"></span>
                </div>

                <div class="assigned-task-modal-item">
                    <strong>Guests</strong>
                    <span id="assignedTaskGuests"></span>
                </div>

                <div class="assigned-task-modal-item">
                    <strong>Package</strong>
                    <span id="assignedTaskPackage"></span>
                </div>

                <div class="assigned-task-modal-item">
                    <strong>Venue</strong>
                    <span id="assignedTaskVenue"></span>
                </div>

                <div class="assigned-task-modal-item">
                    <strong>Booking Status</strong>
                    <span id="assignedTaskBookingStatus"></span>
                </div>

                <div class="assigned-task-modal-item">
                    <strong>Task Created</strong>
                    <span id="assignedTaskCreated"></span>
                </div>

            </div>

            <div class="assigned-task-modal-section">

                <h3>Task Instructions</h3>

                <div
                    class="assigned-task-modal-text"
                    id="assignedTaskDescription"
                ></div>

            </div>

        </div>

    </div>

    <script>
        const assignedTaskRecords =
            <?= $taskModalJson ?>;

        const assignedTasksSidebar =
            document.getElementById(
                "assignedTasksSidebar"
            );

        const assignedTasksSidebarOverlay =
            document.getElementById(
                "assignedTasksSidebarOverlay"
            );

        const assignedTasksMenuButton =
            document.getElementById(
                "assignedTasksMenuButton"
            );

        function closeAssignedTasksSidebar() {
            assignedTasksSidebar.classList.remove(
                "open"
            );

            assignedTasksSidebarOverlay.classList.remove(
                "open"
            );
        }

        assignedTasksMenuButton.addEventListener(
            "click",
            function () {
                assignedTasksSidebar.classList.toggle(
                    "open"
                );

                assignedTasksSidebarOverlay.classList.toggle(
                    "open"
                );
            }
        );

        assignedTasksSidebarOverlay.addEventListener(
            "click",
            closeAssignedTasksSidebar
        );

        const assignedTaskModal =
            document.getElementById(
                "assignedTaskModal"
            );

        const assignedTaskModalClose =
            document.getElementById(
                "assignedTaskModalClose"
            );

        function openAssignedTaskModal(taskId) {
            const record =
                assignedTaskRecords[
                    String(taskId)
                ];

            if (!record) {
                return;
            }

            document.getElementById(
                "assignedTaskModalTitle"
            ).textContent =
                record.title;

            document.getElementById(
                "assignedTaskModalReference"
            ).textContent =
                "Booking Reference: "
                + record.bookingCode;

            const statusElement =
                document.getElementById(
                    "assignedTaskModalStatus"
                );

            statusElement.textContent =
                record.statusLabel;

            statusElement.className =
                "assigned-task-status "
                + record.statusClass;

            document.getElementById(
                "assignedTaskModalDue"
            ).textContent =
                "Due: " + record.dueDate;

            document.getElementById(
                "assignedTaskEventType"
            ).textContent =
                record.eventType;

            document.getElementById(
                "assignedTaskEventDate"
            ).textContent =
                record.eventDate;

            document.getElementById(
                "assignedTaskEventTime"
            ).textContent =
                record.eventTime;

            document.getElementById(
                "assignedTaskCustomer"
            ).textContent =
                record.customerName;

            document.getElementById(
                "assignedTaskPhone"
            ).textContent =
                record.customerPhone;

            document.getElementById(
                "assignedTaskGuests"
            ).textContent =
                record.guestCount + " guests";

            document.getElementById(
                "assignedTaskPackage"
            ).textContent =
                record.packageName;

            document.getElementById(
                "assignedTaskVenue"
            ).textContent =
                record.venueName;

            document.getElementById(
                "assignedTaskBookingStatus"
            ).textContent =
                record.bookingStatus;

            document.getElementById(
                "assignedTaskCreated"
            ).textContent =
                record.createdAt;

            document.getElementById(
                "assignedTaskDescription"
            ).textContent =
                record.description;

            assignedTaskModal.classList.add(
                "open"
            );

            document.body.style.overflow =
                "hidden";
        }

        document
            .querySelectorAll(
                "[data-task-id]"
            )
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        openAssignedTaskModal(
                            button.dataset.taskId
                        );
                    }
                );
            });

        function closeAssignedTaskModal() {
            assignedTaskModal.classList.remove(
                "open"
            );

            document.body.style.overflow =
                "";
        }

        assignedTaskModalClose.addEventListener(
            "click",
            closeAssignedTaskModal
        );

        assignedTaskModal.addEventListener(
            "click",
            function (event) {
                if (
                    event.target
                    === assignedTaskModal
                ) {
                    closeAssignedTaskModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (event.key === "Escape") {
                    closeAssignedTaskModal();
                }
            }
        );

        const requestedTaskId =
            "<?= e(
                $requestedTaskId > 0
                    ? (string) $requestedTaskId
                    : ''
            ) ?>";

        if (requestedTaskId !== "") {
            openAssignedTaskModal(
                requestedTaskId
            );
        }
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>