<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');

$connection = db();
$adminId = (int) $_SESSION['user_id'];

$flash = get_flash();
$addErrors = [];

$addValues = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'event_manager',
    'is_active' => true,
];

/*
|--------------------------------------------------------------------------
| Staff helper functions
|--------------------------------------------------------------------------
*/

function staff_role_label(string $role): string
{
    return $role === 'event_manager'
        ? 'Event Manager'
        : 'Booking Manager';
}

function validate_new_staff(
    PDO $connection,
    array $values,
    string $password,
    string $confirmPassword
): array {
    $errors = [];

    $fullName = trim(
        (string) ($values['full_name'] ?? '')
    );

    $email = strtolower(
        trim((string) ($values['email'] ?? ''))
    );

    $phone = trim(
        (string) ($values['phone'] ?? '')
    );

    $role = (string) (
        $values['role'] ?? ''
    );

    if (
        mb_strlen($fullName) < 3
        || mb_strlen($fullName) > 120
    ) {
        $errors[] =
            'Full name must contain between 3 and 120 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] =
            'Enter a valid staff email address.';
    }

    if (
        $phone !== ''
        && !preg_match('/^[0-9+\-\s()]{7,30}$/', $phone)
    ) {
        $errors[] =
            'Enter a valid phone number.';
    }

    if (
        !in_array(
            $role,
            [
                'event_manager',
                'booking_manager',
            ],
            true
        )
    ) {
        $errors[] =
            'Select Event Manager or Booking Manager.';
    }

    if ($password === '') {
        $errors[] =
            'Enter a temporary password for the staff account.';
    } elseif (
        strlen($password) < 8
        || !preg_match('/[A-Za-z]/', $password)
        || !preg_match('/[0-9]/', $password)
    ) {
        $errors[] =
            'Password must contain at least 8 characters, one letter and one number.';
    }

    if ($password !== $confirmPassword) {
        $errors[] =
            'Password and confirm password do not match.';
    }

    if ($errors === []) {
        $emailStatement = $connection->prepare(
            'SELECT id
             FROM users
             WHERE email = ?
             LIMIT 1'
        );

        $emailStatement->execute([$email]);

        if ($emailStatement->fetch()) {
            $errors[] =
                'Another account already uses this email address.';
        }
    }

    return $errors;
}

/*
|--------------------------------------------------------------------------
| Load administrator
|--------------------------------------------------------------------------
*/

$adminStatement = $connection->prepare(
    "SELECT
        full_name,
        email,
        profile_image,
        COALESCE(
            NULLIF(TRIM(about), ''),
            'System Administrator'
        ) AS about
     FROM users
     WHERE id = ?
     AND role = 'admin'
     LIMIT 1"
);

$adminStatement->execute([$adminId]);

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
| Notification count
|--------------------------------------------------------------------------
*/

$unreadStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE recipient_id = ?
     AND is_read = 0'
);

$unreadStatement->execute([$adminId]);

$unreadNotifications =
    (int) $unreadStatement->fetchColumn();

/*
|--------------------------------------------------------------------------
| Create staff account
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken =
        (string) ($_POST['csrf_token'] ?? '');

    $action =
        (string) ($_POST['action'] ?? '');

    if (!verify_csrf($submittedToken)) {
        set_flash(
            'error',
            'Your form session expired. Refresh the page and try again.'
        );

        redirect('/admin/staff.php');
    }

    if ($action === 'create') {
        $addValues = [
            'full_name' => trim(
                (string) ($_POST['full_name'] ?? '')
            ),
            'email' => strtolower(
                trim((string) ($_POST['email'] ?? ''))
            ),
            'phone' => trim(
                (string) ($_POST['phone'] ?? '')
            ),
            'role' => (string) (
                $_POST['role'] ?? 'event_manager'
            ),
            'is_active' => isset($_POST['is_active']),
        ];

        $password =
            (string) ($_POST['password'] ?? '');

        $confirmPassword =
            (string) ($_POST['confirm_password'] ?? '');

        $addErrors = validate_new_staff(
            $connection,
            $addValues,
            $password,
            $confirmPassword
        );

        if ($addErrors === []) {
            try {
                $defaultAbout = staff_role_label(
                    (string) $addValues['role']
                );

                $insertStatement = $connection->prepare(
                    'INSERT INTO users (
                        full_name,
                        email,
                        phone,
                        password,
                        role,
                        profile_image,
                        about,
                        is_verified,
                        is_active,
                        email_verified_at,
                        created_by
                     ) VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        NULL,
                        ?,
                        1,
                        ?,
                        NOW(),
                        ?
                     )'
                );

                $insertStatement->execute([
                    $addValues['full_name'],
                    $addValues['email'],
                    $addValues['phone'] !== ''
                        ? $addValues['phone']
                        : null,
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    ),
                    $addValues['role'],
                    $defaultAbout,
                    $addValues['is_active'] ? 1 : 0,
                    $adminId,
                ]);

                set_flash(
                    'success',
                    'Staff account created successfully.'
                );

                redirect('/admin/staff.php#addStaffForm');
            } catch (Throwable $exception) {
                $addErrors[] = APP_DEBUG
                    ? 'Staff account could not be created: '
                        . $exception->getMessage()
                    : 'Staff account could not be created.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Staff statistics
|--------------------------------------------------------------------------
*/

$totalStaff = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM users
         WHERE role IN (
            'event_manager',
            'booking_manager'
         )"
    )
    ->fetchColumn();

$activeStaff = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM users
         WHERE role IN (
            'event_manager',
            'booking_manager'
         )
         AND is_active = 1"
    )
    ->fetchColumn();

$eventManagers = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM users
         WHERE role = 'event_manager'"
    )
    ->fetchColumn();

$bookingManagers = (int) $connection
    ->query(
        "SELECT COUNT(*)
         FROM users
         WHERE role = 'booking_manager'"
    )
    ->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Staff | <?= e(APP_NAME) ?></title>

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
            url('/assets/css/admin_consistency.css')
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/staff_management.css')
        ) ?>"
    >
</head>

<body class="admin-dashboard-page staff-management-page">

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

            <h2>
                <?= e((string) $admin['full_name']) ?>
            </h2>

            <p>
                <?= e((string) $admin['about']) ?>
            </p>

            <div class="online-status">
                ● Online
            </div>

        </div>

        <nav class="admin-menu">

            <a href="<?= e(
                url('/admin/dashboard.php')
            ) ?>">
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a href="<?= e(
                url('/admin/bookings.php')
            ) ?>">
                <i class="fa-solid fa-calendar-check"></i>
                Manage Bookings
            </a>

            <a href="<?= e(
                url('/admin/packages.php')
            ) ?>">
                <i class="fa-solid fa-gift"></i>
                Manage Packages
            </a>

            <a href="<?= e(
                url('/admin/venues.php')
            ) ?>">
                <i class="fa-solid fa-hotel"></i>
                Manage Venues
            </a>

            <a href="<?= e(
                url('/admin/services.php')
            ) ?>">
                <i class="fa-solid fa-bell-concierge"></i>
                Manage Services
            </a>

            <a href="<?= e(
                url('/admin/gallery.php')
            ) ?>">
                <i class="fa-solid fa-images"></i>
                View Gallery
            </a>

            <a href="<?= e(
                url('/admin/feedback.php')
            ) ?>">
                <i class="fa-solid fa-comment-dots"></i>
                View Feedback
            </a>

            <a
                class="active"
                href="<?= e(
                    url('/admin/staff.php')
                ) ?>"
            >
                <i class="fa-solid fa-users-gear"></i>
                Manage Staff
            </a>

            <a href="<?= e(
                url('/admin/notifications.php')
            ) ?>">
                <i class="fa-solid fa-bell"></i>
                Notifications
            </a>

            <a href="<?= e(
                url('/admin/profile.php')
            ) ?>">
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

                <div class="admin-welcome">

                    <h1>Manage Staff</h1>

                    <p>
                        Create and manage Event Manager and
                        Booking Manager accounts.
                    </p>

                </div>

            </div>

            <div class="admin-topbar-right">

                <a
                    class="notification-link"
                    href="<?= e(
                        url('/admin/notifications.php')
                    ) ?>"
                    aria-label="Notifications"
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
                        url('/admin/profile.php')
                    ) ?>"
                    aria-label="Manage profile"
                >
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
                class="staff-alert <?= $flash['type'] === 'success'
                    ? 'staff-alert-success'
                    : 'staff-alert-danger' ?>"
            >
                <?= e((string) $flash['message']) ?>
            </div>
        <?php endif; ?>

        <section class="summary-cards">

            <article class="summary-card">

                <div class="summary-icon pink">
                    <i class="fa-solid fa-users"></i>
                </div>

                <div>
                    <h4>Total Staff</h4>

                    <h2>
                        <?= e((string) $totalStaff) ?>
                    </h2>

                    <p>All staff accounts</p>
                </div>

            </article>

            <article class="summary-card">

                <div class="summary-icon purple">
                    <i class="fa-solid fa-user-check"></i>
                </div>

                <div>
                    <h4>Active Staff</h4>

                    <h2>
                        <?= e((string) $activeStaff) ?>
                    </h2>

                    <p>Currently active accounts</p>
                </div>

            </article>

            <article class="summary-card">

                <div class="summary-icon orange">
                    <i class="fa-solid fa-list-check"></i>
                </div>

                <div>
                    <h4>Event Managers</h4>

                    <h2>
                        <?= e((string) $eventManagers) ?>
                    </h2>

                    <p>Event execution staff</p>
                </div>

            </article>

            <article class="summary-card">

                <div class="summary-icon blue">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>

                <div>
                    <h4>Booking Managers</h4>

                    <h2>
                        <?= e((string) $bookingManagers) ?>
                    </h2>

                    <p>Booking management staff</p>
                </div>

            </article>

        </section>

        <section
            class="staff-form-box"
            id="addStaffForm"
        >

            <div
                class="staff-form-heading
                       staff-form-heading-with-action"
            >

                <div>

                    <h2>Add New Staff</h2>

                    <p>
                        Create a new Event Manager or
                        Booking Manager account.
                    </p>

                </div>

                <a
                    class="staff-view-all-button"
                    href="<?= e(
                        url('/admin/all_staff.php')
                    ) ?>"
                >
                    <i class="fa-solid fa-table-list"></i>
                    View All Staff
                </a>

            </div>

            <?php if ($addErrors !== []): ?>
                <div class="staff-alert staff-alert-danger">
                    <ul>
                        <?php foreach ($addErrors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="create"
                >

                <div class="staff-form-grid">

                    <div class="staff-input-box">

                        <label for="addStaffFullName">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="addStaffFullName"
                            name="full_name"
                            value="<?= e(
                                (string) $addValues['full_name']
                            ) ?>"
                            maxlength="120"
                            required
                        >

                    </div>

                    <div class="staff-input-box">

                        <label for="addStaffEmail">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="addStaffEmail"
                            name="email"
                            value="<?= e(
                                (string) $addValues['email']
                            ) ?>"
                            maxlength="190"
                            required
                        >

                    </div>

                    <div class="staff-input-box">

                        <label for="addStaffPhone">
                            Phone Number
                        </label>

                        <input
                            type="text"
                            id="addStaffPhone"
                            name="phone"
                            value="<?= e(
                                (string) $addValues['phone']
                            ) ?>"
                            maxlength="30"
                        >

                    </div>

                    <div class="staff-input-box">

                        <label for="addStaffRole">
                            Staff Role
                        </label>

                        <select
                            id="addStaffRole"
                            name="role"
                            required
                        >

                            <option
                                value="event_manager"
                                <?= $addValues['role']
                                    === 'event_manager'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Event Manager
                            </option>

                            <option
                                value="booking_manager"
                                <?= $addValues['role']
                                    === 'booking_manager'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Booking Manager
                            </option>

                        </select>

                    </div>

                    <div class="staff-input-box">

                        <label for="addStaffPassword">
                            Temporary Password
                        </label>

                        <div class="staff-password-field">

                            <input
                                type="password"
                                id="addStaffPassword"
                                name="password"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                class="staff-password-toggle"
                                type="button"
                                data-password-target="addStaffPassword"
                                aria-label="Show password"
                            >
                                <i class="fa-regular fa-eye"></i>
                            </button>

                        </div>

                        <span class="staff-help">
                            Use at least 8 characters, one letter
                            and one number.
                        </span>

                    </div>

                    <div class="staff-input-box">

                        <label for="addStaffConfirmPassword">
                            Confirm Password
                        </label>

                        <div class="staff-password-field">

                            <input
                                type="password"
                                id="addStaffConfirmPassword"
                                name="confirm_password"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                class="staff-password-toggle"
                                type="button"
                                data-password-target="addStaffConfirmPassword"
                                aria-label="Show password"
                            >
                                <i class="fa-regular fa-eye"></i>
                            </button>

                        </div>

                    </div>

                    <div class="staff-input-box full-width">

                        <label>Account Status</label>

                        <div class="staff-options">

                            <label>

                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    <?= $addValues['is_active']
                                        ? 'checked'
                                        : '' ?>
                                >

                                Staff account is active

                            </label>

                        </div>

                    </div>

                </div>

                <div class="staff-submit-row">

                    <button
                        class="staff-submit-button"
                        type="submit"
                    >
                        <i class="fa-solid fa-user-plus"></i>
                        Add New Staff
                    </button>

                </div>

            </form>

        </section>

    </main>

    <script>
        document
            .querySelectorAll("[data-password-target]")
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        const target =
                            document.getElementById(
                                button.dataset.passwordTarget
                            );

                        const icon =
                            button.querySelector("i");

                        const shouldShow =
                            target.type === "password";

                        target.type =
                            shouldShow
                                ? "text"
                                : "password";

                        icon.className =
                            shouldShow
                                ? "fa-regular fa-eye-slash"
                                : "fa-regular fa-eye";
                    }
                );
            });
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>