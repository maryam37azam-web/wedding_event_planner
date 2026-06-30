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

$editingStaff = null;

$formValues = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'event_manager',
    'is_active' => true,
];

/*
|--------------------------------------------------------------------------
| Load administrator
|--------------------------------------------------------------------------
*/

$adminStatement = $connection->prepare(
    'SELECT full_name, email, profile_image
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

$unreadStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE recipient_id = ?
     AND is_read = 0'
);

$unreadStatement->execute([$adminId]);

$unreadNotifications = (int) $unreadStatement->fetchColumn();

$allowedStaffRoles = [
    'event_manager',
    'booking_manager',
];

/*
|--------------------------------------------------------------------------
| Process actions
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
            'Your form session expired. Refresh and try again.';
    }

    /*
    |--------------------------------------------------------------------------
    | Activate or deactivate staff
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'toggle_status'
        && $errors === []
    ) {
        $staffId = (int) (
            $_POST['staff_id'] ?? 0
        );

        $staffStatement = $connection->prepare(
            'SELECT id, is_active
             FROM users
             WHERE id = ?
             AND role IN (?, ?)
             LIMIT 1'
        );

        $staffStatement->execute([
            $staffId,
            'event_manager',
            'booking_manager',
        ]);

        $staffAccount = $staffStatement->fetch();

        if (!$staffAccount) {
            set_flash(
                'error',
                'The selected staff account was not found.'
            );

            redirect('/admin/staff.php');
        }

        $newStatus =
            (int) $staffAccount['is_active'] === 1
                ? 0
                : 1;

        $updateStatement = $connection->prepare(
            'UPDATE users
             SET is_active = ?
             WHERE id = ?'
        );

        $updateStatement->execute([
            $newStatus,
            $staffId,
        ]);

        set_flash(
            'success',
            $newStatus === 1
                ? 'Staff account activated successfully.'
                : 'Staff account deactivated successfully.'
        );

        redirect('/admin/staff.php');
    }

    /*
    |--------------------------------------------------------------------------
    | Delete staff
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete' && $errors === []) {
        $staffId = (int) (
            $_POST['staff_id'] ?? 0
        );

        $staffStatement = $connection->prepare(
            'SELECT id, profile_image
             FROM users
             WHERE id = ?
             AND role IN (?, ?)
             LIMIT 1'
        );

        $staffStatement->execute([
            $staffId,
            'event_manager',
            'booking_manager',
        ]);

        $staffAccount = $staffStatement->fetch();

        if (!$staffAccount) {
            set_flash(
                'error',
                'The selected staff account was not found.'
            );

            redirect('/admin/staff.php');
        }

        $taskStatement = $connection->prepare(
            'SELECT COUNT(*)
             FROM assigned_tasks
             WHERE assigned_to = ?'
        );

        $taskStatement->execute([$staffId]);

        $assignedTaskCount = (int) (
            $taskStatement->fetchColumn()
        );

        if ($assignedTaskCount > 0) {
            set_flash(
                'error',
                'This staff account has assigned tasks. Deactivate it instead of deleting it.'
            );

            redirect('/admin/staff.php');
        }

        try {
            $deleteStatement = $connection->prepare(
                'DELETE FROM users
                 WHERE id = ?'
            );

            $deleteStatement->execute([$staffId]);

            $profileImage = (string) (
                $staffAccount['profile_image'] ?? ''
            );

            if (
                $profileImage !== ''
                && str_starts_with(
                    $profileImage,
                    'uploads/profiles/'
                )
            ) {
                $absoluteImage =
                    dirname(__DIR__)
                    . '/'
                    . $profileImage;

                if (is_file($absoluteImage)) {
                    unlink($absoluteImage);
                }
            }

            set_flash(
                'success',
                'Staff account deleted successfully.'
            );
        } catch (Throwable $exception) {
            set_flash(
                'error',
                APP_DEBUG
                    ? 'Staff deletion failed: '
                        . $exception->getMessage()
                    : 'Staff deletion failed. Deactivate the account instead.'
            );
        }

        redirect('/admin/staff.php');
    }

    /*
    |--------------------------------------------------------------------------
    | Create or update staff
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $action,
            ['create', 'update'],
            true
        )
    ) {
        $staffId = (int) (
            $_POST['staff_id'] ?? 0
        );

        $existingStaff = null;

        if ($action === 'update') {
            $existingStatement = $connection->prepare(
                'SELECT *
                 FROM users
                 WHERE id = ?
                 AND role IN (?, ?)
                 LIMIT 1'
            );

            $existingStatement->execute([
                $staffId,
                'event_manager',
                'booking_manager',
            ]);

            $existingStaff =
                $existingStatement->fetch();

            if (!$existingStaff) {
                $errors[] =
                    'The staff account being edited was not found.';
            } else {
                $editId = $staffId;
                $editingStaff = $existingStaff;
            }
        }

        $fullName = trim(
            (string) ($_POST['full_name'] ?? '')
        );

        $email = strtolower(
            trim((string) ($_POST['email'] ?? ''))
        );

        $phone = trim(
            (string) ($_POST['phone'] ?? '')
        );

        $role = (string) (
            $_POST['role'] ?? ''
        );

        $password = (string) (
            $_POST['password'] ?? ''
        );

        $isActive =
            isset($_POST['is_active'])
                ? 1
                : 0;

        $formValues = [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'is_active' => $isActive === 1,
        ];

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

        if (!in_array($role, $allowedStaffRoles, true)) {
            $errors[] =
                'Select Event Manager or Booking Manager.';
        }

        if ($action === 'create' && strlen($password) < 8) {
            $errors[] =
                'The temporary password must contain at least 8 characters.';
        }

        if (
            $password !== ''
            && (
                strlen($password) < 8
                || !preg_match('/[A-Za-z]/', $password)
                || !preg_match('/[0-9]/', $password)
            )
        ) {
            $errors[] =
                'Password must contain at least 8 characters, one letter and one number.';
        }

        if ($errors === []) {
            $emailStatement = $connection->prepare(
                'SELECT id
                 FROM users
                 WHERE email = ?
                 AND id <> ?
                 LIMIT 1'
            );

            $emailStatement->execute([
                $email,
                $staffId,
            ]);

            if ($emailStatement->fetch()) {
                $errors[] =
                    'Another account already uses this email address.';
            }
        }

        if ($errors === []) {
            try {
                if ($action === 'create') {
                    $insertStatement =
                        $connection->prepare(
                            'INSERT INTO users (
                                full_name,
                                email,
                                phone,
                                password,
                                role,
                                is_verified,
                                is_active,
                                email_verified_at,
                                created_by
                             ) VALUES (
                                ?, ?, ?, ?, ?, 1, ?, NOW(), ?
                             )'
                        );

                    $insertStatement->execute([
                        $fullName,
                        $email,
                        $phone !== '' ? $phone : null,
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        ),
                        $role,
                        $isActive,
                        $adminId,
                    ]);
                } elseif ($password !== '') {
                    $updateStatement =
                        $connection->prepare(
                            'UPDATE users
                             SET full_name = ?,
                                 email = ?,
                                 phone = ?,
                                 role = ?,
                                 is_active = ?,
                                 password = ?
                             WHERE id = ?'
                        );

                    $updateStatement->execute([
                        $fullName,
                        $email,
                        $phone !== '' ? $phone : null,
                        $role,
                        $isActive,
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        ),
                        $staffId,
                    ]);
                } else {
                    $updateStatement =
                        $connection->prepare(
                            'UPDATE users
                             SET full_name = ?,
                                 email = ?,
                                 phone = ?,
                                 role = ?,
                                 is_active = ?
                             WHERE id = ?'
                        );

                    $updateStatement->execute([
                        $fullName,
                        $email,
                        $phone !== '' ? $phone : null,
                        $role,
                        $isActive,
                        $staffId,
                    ]);
                }

                set_flash(
                    'success',
                    $action === 'create'
                        ? 'Staff account created successfully.'
                        : 'Staff account updated successfully.'
                );

                redirect('/admin/staff.php');
            } catch (Throwable $exception) {
                $errors[] = APP_DEBUG
                    ? 'Staff account could not be saved: '
                        . $exception->getMessage()
                    : 'Staff account could not be saved.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load account for editing
|--------------------------------------------------------------------------
*/

if ($editId > 0 && $editingStaff === null) {
    $editStatement = $connection->prepare(
        'SELECT *
         FROM users
         WHERE id = ?
         AND role IN (?, ?)
         LIMIT 1'
    );

    $editStatement->execute([
        $editId,
        'event_manager',
        'booking_manager',
    ]);

    $editingStaff = $editStatement->fetch();

    if (!$editingStaff) {
        set_flash(
            'error',
            'The selected staff account was not found.'
        );

        redirect('/admin/staff.php');
    }
}

$isStaffFormPost =
    is_post()
    && in_array(
        (string) ($_POST['action'] ?? ''),
        ['create', 'update'],
        true
    );

if (
    $editingStaff
    && !$isStaffFormPost
) {
    $formValues = [
        'full_name' =>
            (string) $editingStaff['full_name'],

        'email' =>
            (string) $editingStaff['email'],

        'phone' =>
            (string) (
                $editingStaff['phone'] ?? ''
            ),

        'role' =>
            (string) $editingStaff['role'],

        'is_active' =>
            (int) $editingStaff['is_active'] === 1,
    ];
}

/*
|--------------------------------------------------------------------------
| Statistics
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

/*
|--------------------------------------------------------------------------
| Staff list
|--------------------------------------------------------------------------
*/

$staffMembers = $connection
    ->query(
        "SELECT
            id,
            full_name,
            email,
            phone,
            role,
            profile_image,
            is_active,
            last_login_at,
            created_at
         FROM users
         WHERE role IN (
            'event_manager',
            'booking_manager'
         )
         ORDER BY created_at DESC"
    )
    ->fetchAll();

function staff_role_label(string $role): string
{
    return $role === 'event_manager'
        ? 'Event Manager'
        : 'Booking Manager';
}

function staff_role_class(string $role): string
{
    return $role === 'event_manager'
        ? 'event-manager'
        : 'booking-manager';
}
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
        href="<?= e(url('/assets/css/admin_dashboard.css')) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(url('/assets/css/staff_management.css')) ?>"
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
        class="active"
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
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

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
                    href="<?= e(url('/admin/notifications.php')) ?>"
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
                class="staff-alert <?= $flash['type'] === 'success'
                    ? 'staff-alert-success'
                    : 'staff-alert-danger' ?>"
            >
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="staff-alert staff-alert-danger">
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
                    <i class="fa-solid fa-users"></i>
                </div>

                <div>
                    <h4>Total Staff</h4>
                    <h2><?= e((string) $totalStaff) ?></h2>
                    <p>All staff accounts</p>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon purple">
                    <i class="fa-solid fa-user-check"></i>
                </div>

                <div>
                    <h4>Active Staff</h4>
                    <h2><?= e((string) $activeStaff) ?></h2>
                    <p>Currently active accounts</p>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon orange">
                    <i class="fa-solid fa-list-check"></i>
                </div>

                <div>
                    <h4>Event Managers</h4>
                    <h2><?= e((string) $eventManagers) ?></h2>
                    <p>Event execution staff</p>
                </div>
            </article>

            <article class="summary-card">
                <div class="summary-icon blue">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>

                <div>
                    <h4>Booking Managers</h4>
                    <h2><?= e((string) $bookingManagers) ?></h2>
                    <p>Booking management staff</p>
                </div>
            </article>

        </section>

        <section class="staff-section-box">

            <div class="staff-section-heading">

                <div>
                    <h2>Staff Accounts</h2>

                    <p>
                        View staff roles, status and account information.
                    </p>
                </div>

                <a
                    class="staff-add-button"
                    href="#staffForm"
                >
                    Add New Staff
                </a>

            </div>

            <?php if ($staffMembers === []): ?>

                <div class="staff-empty-state">
                    <i class="fa-solid fa-users"></i>

                    <h3>No staff accounts found</h3>

                    <p>
                        Use the form below to create the first staff account.
                    </p>
                </div>

            <?php else: ?>

                <div class="staff-table-wrapper">

                    <table class="staff-table">

                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Role</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($staffMembers as $staff): ?>
                                <?php
                                $staffImage =
                                    !empty($staff['profile_image'])
                                        ? url(
                                            '/'
                                            . ltrim(
                                                (string) $staff['profile_image'],
                                                '/'
                                            )
                                        )
                                        : url('/assets/icons/icon-192.png');
                                ?>

                                <tr>

                                    <td>
                                        <div class="staff-user">
                                            <img
                                                src="<?= e($staffImage) ?>"
                                                alt="Staff profile"
                                            >

                                            <div>
                                                <strong>
                                                    <?= e(
                                                        (string) $staff['full_name']
                                                    ) ?>
                                                </strong>

                                                <span>
                                                    <?= e(
                                                        (string) $staff['email']
                                                    ) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span
                                            class="staff-role <?= e(
                                                staff_role_class(
                                                    (string) $staff['role']
                                                )
                                            ) ?>"
                                        >
                                            <?= e(
                                                staff_role_label(
                                                    (string) $staff['role']
                                                )
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= e(
                                            (string) (
                                                $staff['phone']
                                                ?: 'Not provided'
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <span
                                            class="staff-status <?= (int) $staff['is_active'] === 1
                                                ? 'active'
                                                : 'inactive' ?>"
                                        >
                                            <?= (int) $staff['is_active'] === 1
                                                ? 'Active'
                                                : 'Inactive' ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= !empty($staff['last_login_at'])
                                            ? e(
                                                date(
                                                    'd M Y, h:i A',
                                                    strtotime(
                                                        (string) $staff['last_login_at']
                                                    )
                                                )
                                            )
                                            : 'Never' ?>
                                    </td>

                                    <td>
                                        <div class="staff-actions">

                                            <a
                                                class="staff-edit-button"
                                                href="<?= e(
                                                    url(
                                                        '/admin/staff.php?edit='
                                                        . (int) $staff['id']
                                                        . '#staffForm'
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
                                                    name="staff_id"
                                                    value="<?= e(
                                                        (string) $staff['id']
                                                    ) ?>"
                                                >

                                                <button
                                                    class="staff-status-button"
                                                    type="submit"
                                                >
                                                    <?= (int) $staff['is_active'] === 1
                                                        ? 'Deactivate'
                                                        : 'Activate' ?>
                                                </button>
                                            </form>

                                            <form
                                                method="post"
                                                onsubmit="return confirm('Delete this staff account permanently?');"
                                            >
                                                <?= csrf_field() ?>

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="staff_id"
                                                    value="<?= e(
                                                        (string) $staff['id']
                                                    ) ?>"
                                                >

                                                <button
                                                    class="staff-delete-button"
                                                    type="submit"
                                                >
                                                    Delete
                                                </button>
                                            </form>

                                        </div>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

        <section
            class="staff-form-box"
            id="staffForm"
        >

            <div class="staff-form-heading">
                <h2>
                    <?= $editingStaff
                        ? 'Edit Staff Account'
                        : 'Add New Staff' ?>
                </h2>

                <p>
                    <?= $editingStaff
                        ? 'Update staff details or set a new password.'
                        : 'Create an Event Manager or Booking Manager account.' ?>
                </p>
            </div>

            <form method="post">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editingStaff
                        ? 'update'
                        : 'create' ?>"
                >

                <input
                    type="hidden"
                    name="staff_id"
                    value="<?= e(
                        (string) (
                            $editingStaff['id']
                            ?? 0
                        )
                    ) ?>"
                >

                <div class="staff-form-grid">

                    <div class="staff-input-box">
                        <label for="full_name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?= e($formValues['full_name']) ?>"
                            maxlength="120"
                            required
                        >
                    </div>

                    <div class="staff-input-box">
                        <label for="email">
                            Staff Email
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= e($formValues['email']) ?>"
                            maxlength="190"
                            required
                        >

                        <span class="staff-help">
                            This email can later be changed only by the Admin.
                        </span>
                    </div>

                    <div class="staff-input-box">
                        <label for="phone">
                            Phone Number
                        </label>

                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="<?= e($formValues['phone']) ?>"
                            maxlength="30"
                        >
                    </div>

                    <div class="staff-input-box">
                        <label for="role">
                            Staff Role
                        </label>

                        <select
                            id="role"
                            name="role"
                            required
                        >
                            <option
                                value="event_manager"
                                <?= $formValues['role'] === 'event_manager'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Event Manager
                            </option>

                            <option
                                value="booking_manager"
                                <?= $formValues['role'] === 'booking_manager'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Booking Manager
                            </option>
                        </select>
                    </div>

                    <div class="staff-input-box full-width">
                        <label for="password">
                            <?= $editingStaff
                                ? 'New Password'
                                : 'Temporary Password' ?>
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            <?= $editingStaff ? '' : 'required' ?>
                        >

                        <span class="staff-help">
                            <?= $editingStaff
                                ? 'Leave this empty to keep the current password.'
                                : 'Use at least 8 characters, one letter and one number.' ?>
                        </span>
                    </div>

                    <div class="staff-input-box full-width">
                        <label>Account Status</label>

                        <div class="staff-options">
                            <label>
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    <?= $formValues['is_active']
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
                        <?= $editingStaff
                            ? 'Update Staff'
                            : 'Create Staff Account' ?>
                    </button>

                    <?php if ($editingStaff): ?>
                        <a
                            class="staff-cancel-button"
                            href="<?= e(url('/admin/staff.php')) ?>"
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