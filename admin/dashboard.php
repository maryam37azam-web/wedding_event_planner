<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');

$connection = db();
$adminId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Load administrator details
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
    ? url('/' . ltrim((string) $admin['profile_image'], '/'))
    : url('/assets/icons/icon-192.png');

/*
|--------------------------------------------------------------------------
| Dashboard statistics
|--------------------------------------------------------------------------
*/

$totalCustomers = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM users
         WHERE role = 'customer'"
    )
    ->fetchColumn();

$totalBookings = (int) $connection
    ->query(
        'SELECT COUNT(*)
         FROM bookings'
    )
    ->fetchColumn();

$totalRevenue = (float) $connection
    ->query(
        "SELECT COALESCE(SUM(amount), 0)
         FROM payments
         WHERE payment_status = 'successful'"
    )
    ->fetchColumn();

$upcomingEvents = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM bookings
         WHERE event_date >= CURDATE()
         AND booking_status IN (
            'pending',
            'confirmed',
            'in_progress'
         )"
    )
    ->fetchColumn();

$unreadNotificationStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE recipient_id = ?
     AND is_read = 0'
);

$unreadNotificationStatement->execute([$adminId]);

$unreadNotifications = (int) (
    $unreadNotificationStatement->fetchColumn()
);

/*
|--------------------------------------------------------------------------
| Recent bookings
|--------------------------------------------------------------------------
*/

$recentBookingStatement = $connection->query(
    'SELECT
        bookings.id,
        bookings.booking_code,
        bookings.event_type,
        bookings.event_date,
        bookings.booking_status,
        users.full_name AS customer_name,
        users.profile_image AS customer_image
     FROM bookings
     INNER JOIN users
        ON users.id = bookings.customer_id
     ORDER BY bookings.created_at DESC
     LIMIT 5'
);

$recentBookings = $recentBookingStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Booking-status totals
|--------------------------------------------------------------------------
*/

$statusCounts = [
    'completed' => 0,
    'confirmed' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'cancelled' => 0,
];

$statusStatement = $connection->query(
    'SELECT
        booking_status,
        COUNT(*) AS total
     FROM bookings
     GROUP BY booking_status'
);

foreach ($statusStatement->fetchAll() as $statusRow) {
    $statusName = (string) $statusRow['booking_status'];

    if (array_key_exists($statusName, $statusCounts)) {
        $statusCounts[$statusName] =
            (int) $statusRow['total'];
    }
}

/*
|--------------------------------------------------------------------------
| Display helpers
|--------------------------------------------------------------------------
*/

function format_dashboard_revenue(float $amount): string
{
    if ($amount >= 10000000) {
        return 'PKR '
            . number_format($amount / 10000000, 1)
            . 'Cr';
    }

    if ($amount >= 100000) {
        return 'PKR '
            . number_format($amount / 100000, 1)
            . 'L';
    }

    if ($amount >= 1000) {
        return 'PKR '
            . number_format($amount / 1000, 1)
            . 'K';
    }

    return 'PKR ' . number_format($amount, 0);
}

function booking_status_label(string $status): string
{
    return match ($status) {
        'in_progress' => 'In Progress',
        default => ucwords(str_replace('_', ' ', $status)),
    };
}

function booking_status_class(string $status): string
{
    return match ($status) {
        'completed' => 'status-completed',
        'confirmed' => 'status-confirmed',
        'pending' => 'status-pending',
        'in_progress' => 'status-in-progress',
        'cancelled' => 'status-cancelled',
        default => 'status-pending',
    };
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

    <title>Admin Dashboard | <?= e(APP_NAME) ?></title>

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(url('/assets/css/admin_dashboard.css')) ?>"
    >
</head>

<body class="admin-dashboard-page">

    <aside class="admin-sidebar" id="adminSidebar">

        <div class="admin-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="admin-profile">
            <img
                src="<?= e($adminImage) ?>"
                alt="Administrator profile image"
            >

            <h2><?= e($admin['full_name']) ?></h2>
            <p>System Administrator</p>

            <div class="online-status">
                ● Online
            </div>
        </div>

<nav class="admin-menu">

    <a
        class="active"
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
        class="logout-link"
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
        class="sidebar-overlay"
        id="sidebarOverlay"
    ></div>

    <main class="admin-main">

        <header class="admin-topbar">

            <div class="admin-topbar-left">

                <button
                    class="sidebar-toggle"
                    id="sidebarToggle"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="admin-welcome">
                    <h1>
                        Welcome back,
                        <?= e($admin['full_name']) ?>! 👋
                    </h1>

                    <p>
                        Here is what is happening with your
                        wedding events today.
                    </p>
                </div>

            </div>

            <div class="admin-topbar-right">

                <div class="current-date">
                    <span id="currentDate">
                        <?= e(date('d F Y')) ?>
                    </span>

                    <br>

                    <span id="currentDayTime">
                        <?= e(date('l, h:i A')) ?>
                    </span>
                </div>

                <a
                    class="notification-link"
                    href="<?= e(url('/admin/notifications.php')) ?>"
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

                <a href="<?= e(url('/admin/profile.php')) ?>">
                    <img
                        class="topbar-profile-image"
                        src="<?= e($adminImage) ?>"
                        alt="Administrator profile image"
                    >
                </a>

            </div>

        </header>

        <section class="summary-cards">

            <article class="summary-card">
                <div class="summary-icon pink">
                    <i class="fa-regular fa-calendar"></i>
                </div>

                <div>
                    <h4>Total Bookings</h4>
                    <h2><?= e((string) $totalBookings) ?></h2>
                    <p>All wedding-event bookings</p>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon purple">
                    <i class="fa-solid fa-users"></i>
                </div>

                <div>
                    <h4>Total Customers</h4>
                    <h2><?= e((string) $totalCustomers) ?></h2>
                    <p>Registered customer accounts</p>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon orange">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>

                <div>
                    <h4>Total Revenue</h4>
                    <h2>
                        <?= e(
                            format_dashboard_revenue(
                                $totalRevenue
                            )
                        ) ?>
                    </h2>
                    <p>Successful payments</p>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon blue">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>

                <div>
                    <h4>Upcoming Events</h4>
                    <h2><?= e((string) $upcomingEvents) ?></h2>

                    <a href="#recentBookings">
                        View recent bookings
                    </a>
                </div>
            </article>

        </section>

        <section class="dashboard-content">

            <div
                class="dashboard-box"
                id="recentBookings"
            >

                <div class="dashboard-box-header">
                    <h2>Recent Bookings</h2>

                    <a href="#recentBookings">
                        Latest <?= e((string) count($recentBookings)) ?>
                    </a>
                </div>

                <?php if ($recentBookings === []): ?>

                    <div class="empty-dashboard-state">
                        <i class="fa-regular fa-calendar-xmark"></i>

                        <h3>No bookings yet</h3>

                        <p>
                            Recent customer bookings will appear
                            here after a booking is created.
                        </p>
                    </div>

                <?php else: ?>

                    <?php foreach ($recentBookings as $booking): ?>
                        <?php
                        $customerImage =
                            !empty($booking['customer_image'])
                                ? url(
                                    '/'
                                    . ltrim(
                                        (string) $booking['customer_image'],
                                        '/'
                                    )
                                )
                                : url('/assets/icons/icon-192.png');

                        $eventName =
                            trim(
                                (string) (
                                    $booking['event_type']
                                    ?? ''
                                )
                            );

                        if ($eventName === '') {
                            $eventName = 'Wedding Event';
                        }
                        ?>

                        <article class="recent-booking">

                            <div class="booking-customer">

                                <img
                                    src="<?= e($customerImage) ?>"
                                    alt="Customer profile image"
                                >

                                <div class="booking-customer-details">

                                    <h3>
                                        <?= e(
                                            (string) $booking['customer_name']
                                        ) ?>
                                    </h3>

                                    <p>
                                        <?= e($eventName) ?>
                                        ·
                                        <?= e(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    (string) $booking['event_date']
                                                )
                                            )
                                        ) ?>
                                    </p>

                                    <div class="booking-reference">
                                        <?= e(
                                            (string) $booking['booking_code']
                                        ) ?>
                                    </div>

                                </div>

                            </div>

                            <span
                                class="booking-badge <?= e(
                                    booking_status_class(
                                        (string) $booking['booking_status']
                                    )
                                ) ?>"
                            >
                                <?= e(
                                    booking_status_label(
                                        (string) $booking['booking_status']
                                    )
                                ) ?>
                            </span>

                        </article>
                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <div class="dashboard-box">

                <div class="dashboard-box-header">
                    <h2>Booking Status</h2>
                </div>

                <div class="status-list">

                    <div class="status-row">
                        <div class="status-name">
                            <span
                                class="status-dot completed"
                            ></span>

                            Completed Bookings
                        </div>

                        <span class="status-count">
                            <?= e(
                                (string) $statusCounts['completed']
                            ) ?>
                        </span>
                    </div>

                    <div class="status-row">
                        <div class="status-name">
                            <span
                                class="status-dot confirmed"
                            ></span>

                            Confirmed Bookings
                        </div>

                        <span class="status-count">
                            <?= e(
                                (string) $statusCounts['confirmed']
                            ) ?>
                        </span>
                    </div>

                    <div class="status-row">
                        <div class="status-name">
                            <span
                                class="status-dot pending"
                            ></span>

                            Pending Bookings
                        </div>

                        <span class="status-count">
                            <?= e(
                                (string) $statusCounts['pending']
                            ) ?>
                        </span>
                    </div>

                    <div class="status-row">
                        <div class="status-name">
                            <span
                                class="status-dot in-progress"
                            ></span>

                            In Progress
                        </div>

                        <span class="status-count">
                            <?= e(
                                (string) $statusCounts['in_progress']
                            ) ?>
                        </span>
                    </div>

                    <div class="status-row">
                        <div class="status-name">
                            <span
                                class="status-dot cancelled"
                            ></span>

                            Cancelled Bookings
                        </div>

                        <span class="status-count">
                            <?= e(
                                (string) $statusCounts['cancelled']
                            ) ?>
                        </span>
                    </div>

                </div>

            </div>

        </section>

        <footer class="admin-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <script>
        const adminSidebar =
            document.getElementById("adminSidebar");

        const sidebarOverlay =
            document.getElementById("sidebarOverlay");

        const sidebarToggle =
            document.getElementById("sidebarToggle");

        function closeAdminSidebar() {
            adminSidebar.classList.remove("open");
            sidebarOverlay.classList.remove("open");
        }

        sidebarToggle.addEventListener(
            "click",
            function () {
                adminSidebar.classList.toggle("open");
                sidebarOverlay.classList.toggle("open");
            }
        );

        sidebarOverlay.addEventListener(
            "click",
            closeAdminSidebar
        );

        function updateDashboardTime() {
            const now = new Date();

            const dateOptions = {
                day: "2-digit",
                month: "long",
                year: "numeric"
            };

            const dayTimeOptions = {
                weekday: "long",
                hour: "2-digit",
                minute: "2-digit"
            };

            document.getElementById("currentDate")
                .textContent = now.toLocaleDateString(
                    "en-GB",
                    dateOptions
                );

            document.getElementById("currentDayTime")
                .textContent = now.toLocaleDateString(
                    "en-GB",
                    dayTimeOptions
                );
        }

        updateDashboardTime();

        window.setInterval(
            updateDashboardTime,
            60000
        );
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>