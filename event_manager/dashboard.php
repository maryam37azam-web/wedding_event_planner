<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('event_manager');

$connection = db();
$eventManagerId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Load Event Manager profile
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
    $eventManagerId,
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
| Dashboard statistics
|--------------------------------------------------------------------------
*/

$totalEvents = (int) $connection
    ->query(
        'SELECT COUNT(*)
         FROM bookings'
    )
    ->fetchColumn();

$upcomingEvents = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM bookings
         WHERE event_date >= CURDATE()
         AND booking_status NOT IN (
            'completed',
            'cancelled'
         )"
    )
    ->fetchColumn();

$assignedTaskStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM assigned_tasks
     WHERE assigned_to = ?'
);

$assignedTaskStatement->execute([
    $eventManagerId,
]);

$assignedTasks = (int) (
    $assignedTaskStatement->fetchColumn()
);

$completedEvents = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM bookings
         WHERE booking_status = 'completed'"
    )
    ->fetchColumn();

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$notificationStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE recipient_id = ?
     AND is_read = 0'
);

$notificationStatement->execute([
    $eventManagerId,
]);

$unreadNotifications = (int) (
    $notificationStatement->fetchColumn()
);

/*
|--------------------------------------------------------------------------
| Upcoming wedding events
|--------------------------------------------------------------------------
*/

$eventStatement = $connection->query(
    "SELECT
        bookings.id,
        bookings.booking_code,
        bookings.event_type,
        bookings.event_date,
        bookings.booking_status,

        users.full_name AS customer_name,
        users.phone AS customer_phone,

        packages.name AS package_name,
        packages.main_image AS package_image,

        venues.name AS venue_name,
        venues.location AS venue_location,
        venues.main_image AS venue_image

     FROM bookings

     INNER JOIN users
        ON users.id = bookings.customer_id

     LEFT JOIN packages
        ON packages.id = bookings.package_id

     LEFT JOIN venues
        ON venues.id = bookings.venue_id

     WHERE bookings.event_date >= CURDATE()

     AND bookings.booking_status NOT IN (
        'completed',
        'cancelled'
     )

     ORDER BY
        bookings.event_date ASC,
        bookings.created_at DESC

     LIMIT 4"
);

$upcomingEventList = $eventStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Display helpers
|--------------------------------------------------------------------------
*/

function event_manager_status_label(
    string $status
): string {
    return match ($status) {
        'in_progress' => 'In Progress',

        default => ucwords(
            str_replace('_', ' ', $status)
        ),
    };
}

function event_manager_status_class(
    string $status
): string {
    return match ($status) {
        'confirmed' => 'confirmed',
        'in_progress' => 'in-progress',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        default => 'pending',
    };
}

function event_manager_image_url(
    ?string $image
): string {
    $image = trim((string) $image);

    if ($image === '') {
        return url('/assets/icons/icon-512.png');
    }

    return url('/' . ltrim($image, '/'));
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
        Event Manager Dashboard | <?= e(APP_NAME) ?>
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
                '/assets/css/event_manager_dashboard.css'
            )
        ) ?>"
    >
</head>

<body class="event-manager-page">

    <aside
        class="event-sidebar"
        id="eventSidebar"
    >

        <div class="event-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="event-profile">

            <img
                src="<?= e($managerImage) ?>"
                alt="Event Manager profile"
            >

            <h2>
                <?= e($manager['full_name']) ?>
            </h2>

            <p>Wedding Coordinator</p>

            <div class="event-online">
                ● Online
            </div>

        </div>

        <nav class="event-menu">

            <a
                class="active"
                href="<?= e(
                    url('/event_manager/dashboard.php')
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
                    url('/event_manager/profile.php')
                ) ?>"
            >
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a
                href="<?= e(
                    url('/event_manager/gallery.php')
                ) ?>"
            >
                <i class="fa-solid fa-images"></i>
                Gallery Management
            </a>

            <a
                href="<?= e(
                    url('/event_manager/feedback.php')
                ) ?>"
            >
                <i class="fa-solid fa-comment-dots"></i>
                Feedback
            </a>

            <a
                class="event-logout"
                href="<?= e(url('/auth/logout.php')) ?>"
            >
                <i
                    class="fa-solid fa-right-from-bracket"
                ></i>

                Logout
            </a>

        </nav>

    </aside>

    <div
        class="event-sidebar-overlay"
        id="eventSidebarOverlay"
    ></div>

    <main class="event-main">

        <header class="event-topbar">

            <div class="event-topbar-left">

                <button
                    class="event-menu-toggle"
                    id="eventMenuToggle"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="event-heading">

                    <h1>Event Manager Dashboard</h1>

                    <p>
                        Manage wedding events and assigned
                        responsibilities professionally.
                    </p>

                </div>

            </div>

            <div class="event-topbar-right">

                <div class="event-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="event-notification"
                    href="<?= e(
                        url(
                            '/event_manager/notifications.php'
                        )
                    ) ?>"
                    aria-label="Open notifications"
                >
                    <i class="fa-regular fa-bell"></i>

                    <?php if ($unreadNotifications > 0): ?>
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
                    href="<?= e(
                        url('/event_manager/profile.php')
                    ) ?>"
                >
                    <img
                        class="event-top-profile"
                        src="<?= e($managerImage) ?>"
                        alt="Event Manager profile"
                    >
                </a>

            </div>

        </header>

        <section class="event-summary">

            <article class="event-summary-card">

                <div class="event-summary-icon pink">
                    <i
                        class="fa-solid fa-calendar-check"
                    ></i>
                </div>

                <div>
                    <h4>Total Events</h4>

                    <h2>
                        <?= e((string) $totalEvents) ?>
                    </h2>
                </div>

            </article>

            <article class="event-summary-card">

                <div class="event-summary-icon blue">
                    <i
                        class="fa-solid fa-calendar-days"
                    ></i>
                </div>

                <div>
                    <h4>Upcoming Events</h4>

                    <h2>
                        <?= e((string) $upcomingEvents) ?>
                    </h2>
                </div>

            </article>

            <article class="event-summary-card">

                <div class="event-summary-icon orange">
                    <i class="fa-solid fa-list-check"></i>
                </div>

                <div>
                    <h4>Assigned Tasks</h4>

                    <h2>
                        <?= e((string) $assignedTasks) ?>
                    </h2>
                </div>

            </article>

            <article class="event-summary-card">

                <div class="event-summary-icon green">
                    <i
                        class="fa-solid fa-circle-check"
                    ></i>
                </div>

                <div>
                    <h4>Completed Events</h4>

                    <h2>
                        <?= e((string) $completedEvents) ?>
                    </h2>
                </div>

            </article>

        </section>

        <section class="event-section">

            <div class="event-section-top">

                <div>
                    <h2>Upcoming Wedding Events</h2>

                    <p>
                        Latest confirmed and pending wedding
                        events from customer bookings.
                    </p>
                </div>

                <a
                    class="event-view-button"
                    href="<?= e(
                        url(
                            '/event_manager/assigned_tasks.php'
                        )
                    ) ?>"
                >
                    View Assigned Tasks
                </a>

            </div>

            <?php if ($upcomingEventList === []): ?>

                <div class="event-empty">

                    <i
                        class="fa-regular fa-calendar-xmark"
                    ></i>

                    <h3>No upcoming events</h3>

                    <p>
                        New customer wedding bookings will
                        appear here automatically.
                    </p>

                </div>

            <?php else: ?>

                <div class="event-grid">

                    <?php foreach (
                        $upcomingEventList as $event
                    ): ?>
                        <?php
                        $eventType = trim(
                            (string) (
                                $event['event_type'] ?? ''
                            )
                        );

                        if ($eventType === '') {
                            $eventType = 'Wedding Event';
                        }

                        $customerPhone = trim(
                            (string) (
                                $event['customer_phone']
                                ?? ''
                            )
                        );

                        $packageName = trim(
                            (string) (
                                $event['package_name']
                                ?? ''
                            )
                        );

                        $venueName = trim(
                            (string) (
                                $event['venue_name']
                                ?? ''
                            )
                        );

                        $venueLocation = trim(
                            (string) (
                                $event['venue_location']
                                ?? ''
                            )
                        );

                        $packageImage =
                            event_manager_image_url(
                                $event['package_image']
                                ?? null
                            );

                        $venueImage =
                            event_manager_image_url(
                                $event['venue_image']
                                ?? null
                            );

                        $eventStatus = (string) (
                            $event['booking_status']
                            ?? 'pending'
                        );
                        ?>

                        <article class="event-card">

                            <div class="event-card-head">

                                <div>
                                    <h3>
                                        <?= e(
                                            (string) $event[
                                                'customer_name'
                                            ]
                                        ) ?>
                                    </h3>

                                    <div
                                        class="event-reference"
                                    >
                                        <?= e(
                                            (string) $event[
                                                'booking_code'
                                            ]
                                        ) ?>
                                    </div>
                                </div>

                                <span
                                    class="event-status <?= e(
                                        event_manager_status_class(
                                            $eventStatus
                                        )
                                    ) ?>"
                                >
                                    <?= e(
                                        event_manager_status_label(
                                            $eventStatus
                                        )
                                    ) ?>
                                </span>

                            </div>

                            <div class="event-detail-box">

                                <h4>Customer Details</h4>

                                <p>
                                    <i
                                        class="fa-solid fa-user"
                                    ></i>

                                    <?= e(
                                        (string) $event[
                                            'customer_name'
                                        ]
                                    ) ?>

                                    <br>

                                    <i
                                        class="fa-solid fa-phone"
                                    ></i>

                                    <?= e(
                                        $customerPhone !== ''
                                            ? $customerPhone
                                            : 'Not provided'
                                    ) ?>
                                </p>

                            </div>

                            <div class="event-detail-box">

                                <h4>
                                    Event Booking Details
                                </h4>

                                <p>
                                    <i
                                        class="fa-solid fa-heart"
                                    ></i>

                                    Event:
                                    <?= e($eventType) ?>

                                    <br>

                                    <i
                                        class="fa-solid fa-calendar"
                                    ></i>

                                    Date:
                                    <?= e(
                                        date(
                                            'd F Y',
                                            strtotime(
                                                (string) $event[
                                                    'event_date'
                                                ]
                                            )
                                        )
                                    ) ?>

                                    <br>

                                    <i
                                        class="fa-solid fa-hotel"
                                    ></i>

                                    Venue:
                                    <?= e(
                                        $venueName !== ''
                                            ? $venueName
                                            : 'Not selected'
                                    ) ?>

                                    <?php if (
                                        $venueLocation !== ''
                                    ): ?>
                                        —
                                        <?= e($venueLocation) ?>
                                    <?php endif; ?>

                                    <br>

                                    <i
                                        class="fa-solid fa-gift"
                                    ></i>

                                    Package:
                                    <?= e(
                                        $packageName !== ''
                                            ? $packageName
                                            : 'Not selected'
                                    ) ?>
                                </p>

                            </div>

                            <div class="event-detail-box">

                                <h4>
                                    Selected Package and Venue
                                </h4>

                                <div class="event-image-grid">

                                    <div
                                        class="event-image-item"
                                    >
                                        <img
                                            src="<?= e(
                                                $packageImage
                                            ) ?>"
                                            alt="Selected package"
                                        >

                                        <span
                                            class="event-image-label"
                                        >
                                            Package
                                        </span>
                                    </div>

                                    <div
                                        class="event-image-item"
                                    >
                                        <img
                                            src="<?= e(
                                                $venueImage
                                            ) ?>"
                                            alt="Selected venue"
                                        >

                                        <span
                                            class="event-image-label"
                                        >
                                            Venue
                                        </span>
                                    </div>

                                </div>

                            </div>

                            <a
                                class="event-card-button"
                                href="<?= e(
                                    url(
                                        '/event_manager/assigned_tasks.php'
                                    )
                                ) ?>"
                            >
                                View Assigned Tasks
                            </a>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <footer class="event-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <script>
        const eventSidebar =
            document.getElementById("eventSidebar");

        const eventSidebarOverlay =
            document.getElementById(
                "eventSidebarOverlay"
            );

        const eventMenuToggle =
            document.getElementById(
                "eventMenuToggle"
            );

        function closeEventSidebar() {
            eventSidebar.classList.remove("open");
            eventSidebarOverlay.classList.remove("open");
        }

        eventMenuToggle.addEventListener(
            "click",
            function () {
                eventSidebar.classList.toggle("open");
                eventSidebarOverlay.classList.toggle(
                    "open"
                );
            }
        );

        eventSidebarOverlay.addEventListener(
            "click",
            closeEventSidebar
        );
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>
