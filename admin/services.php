<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');

$connection = db();
$adminId = (int) $_SESSION['user_id'];

$errors = [];
$flash = get_flash();

$editId = max(
    0,
    (int) ($_GET['edit'] ?? 0)
);

$editingService = null;

$formValues = [
    'name' => '',
    'description' => '',
    'price' => '',
    'status' => 'active',
];

/*
|--------------------------------------------------------------------------
| Load administrator information
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
| Unread notification count
|--------------------------------------------------------------------------
*/

$unreadStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE recipient_id = ?
     AND is_read = 0'
);

$unreadStatement->execute([$adminId]);

$unreadNotifications = (int) (
    $unreadStatement->fetchColumn()
);

/*
|--------------------------------------------------------------------------
| Process service actions
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $action = (string) (
        $_POST['action'] ?? ''
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

    /*
    |--------------------------------------------------------------------------
    | Delete service
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete' && $errors === []) {
        $serviceId = (int) (
            $_POST['service_id'] ?? 0
        );

        $serviceStatement = $connection->prepare(
            'SELECT id, name
             FROM services
             WHERE id = ?
             LIMIT 1'
        );

        $serviceStatement->execute([$serviceId]);

        $serviceToDelete =
            $serviceStatement->fetch();

        if (!$serviceToDelete) {
            set_flash(
                'error',
                'The selected service was not found.'
            );

            redirect('/admin/services.php');
        }

        $usageStatement = $connection->prepare(
            'SELECT COUNT(*)
             FROM booking_services
             WHERE service_id = ?'
        );

        $usageStatement->execute([$serviceId]);

        $serviceUsageCount = (int) (
            $usageStatement->fetchColumn()
        );

        if ($serviceUsageCount > 0) {
            set_flash(
                'error',
                'This service is already connected to one or more bookings. Deactivate it instead of deleting it.'
            );

            redirect('/admin/services.php');
        }

        try {
            $deleteStatement = $connection->prepare(
                'DELETE FROM services
                 WHERE id = ?'
            );

            $deleteStatement->execute([$serviceId]);

            set_flash(
                'success',
                'Service deleted successfully.'
            );
        } catch (Throwable $exception) {
            set_flash(
                'error',
                APP_DEBUG
                    ? 'Service deletion failed: '
                        . $exception->getMessage()
                    : 'Service deletion failed.'
            );
        }

        redirect('/admin/services.php');
    }

    /*
    |--------------------------------------------------------------------------
    | Activate or deactivate service
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'toggle_status'
        && $errors === []
    ) {
        $serviceId = (int) (
            $_POST['service_id'] ?? 0
        );

        $statusStatement = $connection->prepare(
            'SELECT status
             FROM services
             WHERE id = ?
             LIMIT 1'
        );

        $statusStatement->execute([$serviceId]);

        $currentStatus =
            $statusStatement->fetchColumn();

        if ($currentStatus === false) {
            set_flash(
                'error',
                'The selected service was not found.'
            );

            redirect('/admin/services.php');
        }

        $newStatus =
            $currentStatus === 'active'
                ? 'inactive'
                : 'active';

        $updateStatusStatement =
            $connection->prepare(
                'UPDATE services
                 SET status = ?
                 WHERE id = ?'
            );

        $updateStatusStatement->execute([
            $newStatus,
            $serviceId,
        ]);

        set_flash(
            'success',
            $newStatus === 'active'
                ? 'Service activated successfully.'
                : 'Service deactivated successfully.'
        );

        redirect('/admin/services.php');
    }

    /*
    |--------------------------------------------------------------------------
    | Create or update service
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $action,
            ['create', 'update'],
            true
        )
    ) {
        $serviceId = (int) (
            $_POST['service_id'] ?? 0
        );

        $existingService = null;

        if ($action === 'update') {
            $existingStatement =
                $connection->prepare(
                    'SELECT *
                     FROM services
                     WHERE id = ?
                     LIMIT 1'
                );

            $existingStatement->execute([
                $serviceId,
            ]);

            $existingService =
                $existingStatement->fetch();

            if (!$existingService) {
                $errors[] =
                    'The service being edited was not found.';
            } else {
                $editId = $serviceId;
                $editingService = $existingService;
            }
        }

        $name = trim(
            (string) ($_POST['name'] ?? '')
        );

        $description = trim(
            (string) (
                $_POST['description'] ?? ''
            )
        );

        $priceInput = trim(
            (string) ($_POST['price'] ?? '')
        );

        $priceClean = preg_replace(
            '/[^0-9.]/',
            '',
            $priceInput
        );

        $status =
            isset($_POST['activate_service'])
                ? 'active'
                : 'inactive';

        $formValues = [
            'name' => $name,
            'description' => $description,
            'price' => $priceInput,
            'status' => $status,
        ];

        if (
            mb_strlen($name) < 3
            || mb_strlen($name) > 150
        ) {
            $errors[] =
                'Service name must contain between 3 and 150 characters.';
        }

        if (
            mb_strlen($description) < 5
            || mb_strlen($description) > 2000
        ) {
            $errors[] =
                'Service description must contain between 5 and 2,000 characters.';
        }

        if (
            $priceClean === null
            || $priceClean === ''
            || !is_numeric($priceClean)
            || (float) $priceClean < 0
        ) {
            $errors[] =
                'Enter a valid service price.';
        }

        /*
         * Ensure service name is unique.
         */
        if ($errors === []) {
            $nameCheckStatement =
                $connection->prepare(
                    'SELECT id
                     FROM services
                     WHERE LOWER(name) = LOWER(?)
                     AND id <> ?
                     LIMIT 1'
                );

            $nameCheckStatement->execute([
                $name,
                $serviceId,
            ]);

            if ($nameCheckStatement->fetch()) {
                $errors[] =
                    'Another service already uses this name.';
            }
        }

        if ($errors === []) {
            try {
                if ($action === 'create') {
                    $saveStatement =
                        $connection->prepare(
                            'INSERT INTO services (
                                name,
                                description,
                                price,
                                status,
                                created_by
                             ) VALUES (?, ?, ?, ?, ?)'
                        );

                    $saveStatement->execute([
                        $name,
                        $description,
                        (float) $priceClean,
                        $status,
                        $adminId,
                    ]);
                } else {
                    $saveStatement =
                        $connection->prepare(
                            'UPDATE services
                             SET name = ?,
                                 description = ?,
                                 price = ?,
                                 status = ?
                             WHERE id = ?'
                        );

                    $saveStatement->execute([
                        $name,
                        $description,
                        (float) $priceClean,
                        $status,
                        $serviceId,
                    ]);
                }

                set_flash(
                    'success',
                    $action === 'create'
                        ? 'Service created successfully.'
                        : 'Service updated successfully.'
                );

                redirect('/admin/services.php');
            } catch (Throwable $exception) {
                $errors[] = APP_DEBUG
                    ? 'Service could not be saved: '
                        . $exception->getMessage()
                    : 'Service could not be saved.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load service for editing
|--------------------------------------------------------------------------
*/

if ($editId > 0 && $editingService === null) {
    $editStatement = $connection->prepare(
        'SELECT *
         FROM services
         WHERE id = ?
         LIMIT 1'
    );

    $editStatement->execute([$editId]);

    $editingService = $editStatement->fetch();

    if (!$editingService) {
        set_flash(
            'error',
            'The selected service was not found.'
        );

        redirect('/admin/services.php');
    }
}

$isServiceFormPost =
    is_post()
    && in_array(
        (string) ($_POST['action'] ?? ''),
        ['create', 'update'],
        true
    );

if (
    $editingService
    && !$isServiceFormPost
) {
    $formValues = [
        'name' =>
            (string) $editingService['name'],

        'description' =>
            (string) (
                $editingService['description']
                ?? ''
            ),

        'price' =>
            (string) $editingService['price'],

        'status' =>
            (string) $editingService['status'],
    ];
}

/*
|--------------------------------------------------------------------------
| Dashboard statistics
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

$totalServiceSelections = (int) $connection
    ->query(
        'SELECT COALESCE(SUM(quantity), 0)
         FROM booking_services'
    )
    ->fetchColumn();

$totalServiceValue = (float) $connection
    ->query(
        'SELECT COALESCE(
            SUM(quantity * price),
            0
         )
         FROM booking_services'
    )
    ->fetchColumn();

/*
|--------------------------------------------------------------------------
| Load services
|--------------------------------------------------------------------------
*/

$services = $connection
    ->query(
        'SELECT
            services.*,
            COALESCE(
                SUM(booking_services.quantity),
                0
            ) AS booking_count
         FROM services
         LEFT JOIN booking_services
            ON booking_services.service_id =
               services.id
         GROUP BY services.id
         ORDER BY services.created_at DESC'
    )
    ->fetchAll();
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
        Manage Services | <?= e(APP_NAME) ?>
    </title>

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/admin_dashboard.css')
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/service_management.css')
        ) ?>"
    >
</head>

<body class="admin-dashboard-page">

    <aside
        class="admin-sidebar"
        id="adminSidebar"
    >

        <div class="admin-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="admin-profile">
            <img
                src="<?= e($adminImage) ?>"
                alt="Administrator profile"
            >

            <h2><?= e($admin['full_name']) ?></h2>

            <p>System Administrator</p>

            <div class="online-status">
                ● Online
            </div>
        </div>

<nav class="admin-menu">

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
        class="active"
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
                    <h1>Manage Services</h1>

                    <p>
                        Create and manage additional
                        wedding-event services.
                    </p>
                </div>

            </div>

            <div class="admin-topbar-right">

                <a
                    class="notification-link"
                    href="<?= e(
                        url('/admin/notifications.php')
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

                <a href="<?= e(url('/admin/profile.php')) ?>">
                    <img
                        class="topbar-profile-image"
                        src="<?= e($adminImage) ?>"
                        alt="Administrator profile"
                    >
                </a>

            </div>

        </header>

        <?php if ($flash): ?>
            <div
                class="service-alert <?= $flash['type'] === 'success'
                    ? 'service-alert-success'
                    : 'service-alert-danger' ?>"
            >
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div
                class="service-alert service-alert-danger"
            >
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <section class="summary-cards">

            <article class="summary-card">
                <div class="summary-icon pink">
                    <i class="fa-solid fa-bell-concierge"></i>
                </div>

                <div>
                    <h4>Total Services</h4>

                    <h2>
                        <?= e((string) $totalServices) ?>
                    </h2>

                    <p>All created services</p>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon purple">
                    <i class="fa-solid fa-eye"></i>
                </div>

                <div>
                    <h4>Active Services</h4>

                    <h2>
                        <?= e((string) $activeServices) ?>
                    </h2>

                    <p>Available for selection</p>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon orange">
                    <i class="fa-solid fa-cart-plus"></i>
                </div>

                <div>
                    <h4>Service Selections</h4>

                    <h2>
                        <?= e(
                            (string) $totalServiceSelections
                        ) ?>
                    </h2>

                    <p>Services added to bookings</p>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon blue">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>

                <div>
                    <h4>Service Value</h4>

                    <h2>
                        Rs.
                        <?= e(
                            number_format(
                                $totalServiceValue,
                                0
                            )
                        ) ?>
                    </h2>

                    <p>Total booking-service value</p>
                </div>
            </article>

        </section>

        <section class="service-section-box">

            <div class="service-section-heading">

                <div>
                    <h2>Wedding Services</h2>

                    <p>
                        View service pricing, availability
                        and booking usage.
                    </p>
                </div>

                <a
                    class="service-add-button"
                    href="#serviceForm"
                >
                    Add New Service
                </a>

            </div>

            <?php if ($services === []): ?>

                <div class="service-empty-state">

                    <i class="fa-solid fa-bell-concierge"></i>

                    <h3>No services created yet</h3>

                    <p>
                        Use the form below to create the
                        first wedding-event service.
                    </p>

                </div>

            <?php else: ?>

                <div class="service-grid">

                    <?php foreach ($services as $service): ?>

                        <article class="service-card">

                            <div class="service-card-header">

                                <div class="service-icon">
                                    <i
                                        class="fa-solid fa-bell-concierge"
                                    ></i>
                                </div>

                                <span
                                    class="service-status <?= e(
                                        (string) $service['status']
                                    ) ?>"
                                >
                                    <?= e(
                                        (string) $service['status']
                                    ) ?>
                                </span>

                            </div>

                            <h3>
                                <?= e(
                                    (string) $service['name']
                                ) ?>
                            </h3>

                            <div class="service-price">
                                Rs.
                                <?= e(
                                    number_format(
                                        (float) $service['price'],
                                        0
                                    )
                                ) ?>
                            </div>

                            <p class="service-description">
                                <?= e(
                                    (string) (
                                        $service['description']
                                        ?? ''
                                    )
                                ) ?>
                            </p>

                            <div class="service-meta">
                                Selected in bookings:
                                <strong>
                                    <?= e(
                                        (string) $service[
                                            'booking_count'
                                        ]
                                    ) ?>
                                </strong>

                                <br>

                                Created:
                                <strong>
                                    <?= e(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                (string) $service[
                                                    'created_at'
                                                ]
                                            )
                                        )
                                    ) ?>
                                </strong>
                            </div>

                            <div class="service-actions">

                                <a
                                    class="service-edit-button"
                                    href="<?= e(
                                        url(
                                            '/admin/services.php?edit='
                                            . (int) $service['id']
                                            . '#serviceForm'
                                        )
                                    ) ?>"
                                >
                                    Edit
                                </a>

                                <form method="post">

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="toggle_status"
                                    >

                                    <input
                                        type="hidden"
                                        name="service_id"
                                        value="<?= e(
                                            (string) $service['id']
                                        ) ?>"
                                    >

                                    <button
                                        class="service-status-button"
                                        type="submit"
                                    >
                                        <?= $service['status']
                                            === 'active'
                                            ? 'Deactivate'
                                            : 'Activate' ?>
                                    </button>

                                </form>

                                <form
                                    method="post"
                                    onsubmit="return confirm('Delete this service permanently?');"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="service_id"
                                        value="<?= e(
                                            (string) $service['id']
                                        ) ?>"
                                    >

                                    <button
                                        class="service-delete-button"
                                        type="submit"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <section
            class="service-form-box"
            id="serviceForm"
        >

            <div class="service-form-heading">

                <h2>
                    <?= $editingService
                        ? 'Edit Service'
                        : 'Add New Service' ?>
                </h2>

                <p>
                    <?= $editingService
                        ? 'Update the selected service information and price.'
                        : 'Create a service customers can add to their wedding booking.' ?>
                </p>

            </div>

            <form method="post">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editingService
                        ? 'update'
                        : 'create' ?>"
                >

                <input
                    type="hidden"
                    name="service_id"
                    value="<?= e(
                        (string) (
                            $editingService['id']
                            ?? 0
                        )
                    ) ?>"
                >

                <div class="service-form-grid">

                    <div class="service-input-box">
                        <label for="name">
                            Service Name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?= e(
                                $formValues['name']
                            ) ?>"
                            maxlength="150"
                            placeholder="Example: Photography"
                            required
                        >
                    </div>

                    <div class="service-input-box">
                        <label for="price">
                            Service Price
                        </label>

                        <input
                            type="text"
                            id="price"
                            name="price"
                            value="<?= e(
                                $formValues['price']
                            ) ?>"
                            placeholder="Example: 25000"
                            required
                        >

                        <span class="service-help">
                            Enter 0 when the service is
                            included without an extra charge.
                        </span>
                    </div>

                    <div
                        class="service-input-box full-width"
                    >
                        <label for="description">
                            Service Description
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            maxlength="2000"
                            placeholder="Explain what this service includes"
                            required
                        ><?= e(
                            $formValues['description']
                        ) ?></textarea>
                    </div>

                    <div
                        class="service-input-box full-width"
                    >
                        <label>Service Settings</label>

                        <div class="service-options">

                            <label>
                                <input
                                    type="checkbox"
                                    name="activate_service"
                                    value="1"
                                    <?= $formValues['status']
                                        === 'active'
                                        ? 'checked'
                                        : '' ?>
                                >

                                Activate service for booking
                                selection
                            </label>

                        </div>
                    </div>

                </div>

                <div class="service-submit-row">

                    <button
                        class="service-submit-button"
                        type="submit"
                    >
                        <?= $editingService
                            ? 'Update Service'
                            : 'Add Service' ?>
                    </button>

                    <?php if ($editingService): ?>
                        <a
                            class="service-cancel-button"
                            href="<?= e(
                                url('/admin/services.php')
                            ) ?>"
                        >
                            Cancel Editing
                        </a>
                    <?php endif; ?>

                </div>

            </form>

        </section>

    </main>

    <script>
        const adminSidebar =
            document.getElementById("adminSidebar");

        const sidebarOverlay =
            document.getElementById("sidebarOverlay");

        const sidebarToggle =
            document.getElementById("sidebarToggle");

        function closeSidebar() {
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
            closeSidebar
        );
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>