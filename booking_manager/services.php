<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('booking_manager');

$connection = db();
$bookingManagerId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Load Booking Manager account
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

/*
|--------------------------------------------------------------------------
| Notification count
|--------------------------------------------------------------------------
*/

$notificationStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE recipient_id = ?
     AND is_read = 0'
);

$notificationStatement->execute([
    $bookingManagerId,
]);

$unreadNotifications = (int) (
    $notificationStatement->fetchColumn()
);

/*
|--------------------------------------------------------------------------
| Service statistics
|--------------------------------------------------------------------------
*/

$totalServices = (int) $connection
    ->query(
        'SELECT COUNT(*)
         FROM services'
    )
    ->fetchColumn();

$activeServices = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM services
         WHERE status = 'active'"
    )
    ->fetchColumn();

$averageServicePrice = (float) $connection
    ->query(
        "SELECT COALESCE(AVG(price), 0)
         FROM services
         WHERE status = 'active'"
    )
    ->fetchColumn();

/*
|--------------------------------------------------------------------------
| Load active services
|--------------------------------------------------------------------------
*/

$services = $connection
    ->query(
        "SELECT
            id,
            name,
            description,
            price,
            status,
            created_at
         FROM services
         WHERE status = 'active'
         ORDER BY name ASC"
    )
    ->fetchAll();

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
        View Services | <?= e(APP_NAME) ?>
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
                '/assets/css/booking_manager_dashboard.css'
            )
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/booking_manager_views.css'
            )
        ) ?>"
    >
</head>

<body class="booking-manager-page">

    <aside
        class="booking-sidebar"
        id="bookingSidebar"
    >

        <div class="booking-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="booking-sidebar-profile">

            <img
                src="<?= e($managerImage) ?>"
                alt="Booking Manager profile"
            >

            <h2><?= e($manager['full_name']) ?></h2>

            <p>Booking Manager</p>

            <div class="booking-online">
                ● Online
            </div>

        </div>

        <nav class="booking-menu">

            <a
                href="<?= e(
                    url('/booking_manager/dashboard.php')
                ) ?>"
            >
                <i class="fa-solid fa-gauge"></i>
                Dashboard
            </a>
<a
    href="<?= e(
        url('/booking_manager/bookings.php')
    ) ?>"
>
    <i class="fa-solid fa-calendar-check"></i>
    Manage Bookings
</a>

<a
    href="<?= e(
        url('/booking_manager/booking.php')
    ) ?>"
>
    <i class="fa-solid fa-calendar-plus"></i>
    Create Booking
</a>

            <a
                class="active"
                href="<?= e(
                    url('/booking_manager/services.php')
                ) ?>"
            >
                <i class="fa-solid fa-bell-concierge"></i>
                View Services
            </a>

            <a
                href="<?= e(
                    url('/booking_manager/gallery.php')
                ) ?>"
            >
                <i class="fa-solid fa-images"></i>
                View Gallery
            </a>

            <a
                href="<?= e(
                    url('/booking_manager/packages.php')
                ) ?>"
            >
                <i class="fa-solid fa-gift"></i>
                View Packages
            </a>

            <a
                href="<?= e(
                    url('/booking_manager/venues.php')
                ) ?>"
            >
                <i class="fa-solid fa-hotel"></i>
                View Venues
            </a>

            <a
                href="<?= e(
                    url('/booking_manager/profile.php')
                ) ?>"
            >
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a
                href="<?= e(
                    url(
                        '/booking_manager/notifications.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-bell"></i>
                View Notifications
            </a>

            <a
                class="booking-logout"
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
        class="booking-sidebar-overlay"
        id="bookingSidebarOverlay"
    ></div>

    <main class="booking-main">

        <header class="booking-topbar">

            <div class="booking-topbar-left">

                <button
                    class="booking-menu-button"
                    id="bookingMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="booking-heading">

                    <h1>Wedding Services</h1>

                    <p>
                        View active wedding services,
                        descriptions and current prices.
                    </p>

                </div>

            </div>

            <div class="booking-topbar-right">

                <div class="booking-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="booking-notification"
                    href="<?= e(
                        url(
                            '/booking_manager/notifications.php'
                        )
                    ) ?>"
                    aria-label="Open notifications"
                >
                    <i class="fa-solid fa-bell"></i>

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
                        url('/booking_manager/profile.php')
                    ) ?>"
                >
                    <img
                        class="booking-profile-image"
                        src="<?= e($managerImage) ?>"
                        alt="Booking Manager profile"
                    >
                </a>

            </div>

        </header>

        <section class="manager-view-summary">

            <article class="manager-view-summary-card">

                <div class="manager-view-summary-icon">
                    <i
                        class="fa-solid fa-bell-concierge"
                    ></i>
                </div>

                <div>
                    <h4>Total Services</h4>

                    <h2>
                        <?= e((string) $totalServices) ?>
                    </h2>
                </div>

            </article>

            <article class="manager-view-summary-card">

                <div class="manager-view-summary-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    <h4>Active Services</h4>

                    <h2>
                        <?= e((string) $activeServices) ?>
                    </h2>
                </div>

            </article>

            <article class="manager-view-summary-card">

                <div class="manager-view-summary-icon">
                    <i
                        class="fa-solid fa-money-bill-wave"
                    ></i>
                </div>

                <div>
                    <h4>Average Price</h4>

                    <h2>
                        Rs.
                        <?= e(
                            number_format(
                                $averageServicePrice,
                                0
                            )
                        ) ?>
                    </h2>
                </div>

            </article>

        </section>

        <section class="manager-view-box">

            <div class="manager-view-heading">

                <div>
                    <h2>Available Services</h2>

                    <p>
                        These services are currently active
                        and available for wedding bookings.
                    </p>
                </div>

                <div class="manager-view-notice">
                    View-only access
                </div>

            </div>

            <?php if ($services === []): ?>

                <div class="manager-empty-state">

                    <i
                        class="fa-solid fa-bell-concierge"
                    ></i>

                    <h3>No active services found</h3>

                    <p>
                        Services activated by the Admin will
                        appear here automatically.
                    </p>

                </div>

            <?php else: ?>

                <div class="manager-service-grid">

                    <?php foreach ($services as $service): ?>

                        <article class="manager-service-card">

                            <div class="manager-service-top">

                                <div class="manager-service-icon">
                                    <i
                                        class="fa-solid fa-bell-concierge"
                                    ></i>
                                </div>

                                <span
                                    class="manager-service-status"
                                >
                                    Available
                                </span>

                            </div>

                            <h3>
                                <?= e(
                                    (string) $service['name']
                                ) ?>
                            </h3>

                            <div class="manager-service-price">
                                Rs.
                                <?= e(
                                    number_format(
                                        (float) $service['price'],
                                        0
                                    )
                                ) ?>
                            </div>

                            <p
                                class="manager-service-description"
                            >
                                <?= e(
                                    (string) (
                                        $service['description']
                                        ?: 'No description provided.'
                                    )
                                ) ?>
                            </p>

                            <div class="manager-service-footer">

                                <i
                                    class="fa-solid fa-circle-info"
                                ></i>

                                This service can be selected
                                during the booking process.

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <footer class="manager-view-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <script>
        const bookingSidebar =
            document.getElementById("bookingSidebar");

        const bookingSidebarOverlay =
            document.getElementById(
                "bookingSidebarOverlay"
            );

        const bookingMenuButton =
            document.getElementById(
                "bookingMenuButton"
            );

        function closeBookingSidebar() {
            bookingSidebar.classList.remove("open");

            bookingSidebarOverlay.classList.remove(
                "open"
            );
        }

        bookingMenuButton.addEventListener(
            "click",
            function () {
                bookingSidebar.classList.toggle("open");

                bookingSidebarOverlay.classList.toggle(
                    "open"
                );
            }
        );

        bookingSidebarOverlay.addEventListener(
            "click",
            closeBookingSidebar
        );
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>
