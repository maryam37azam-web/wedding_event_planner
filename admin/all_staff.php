<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');

$connection = db();
$adminId = (int) $_SESSION['user_id'];

$flash = get_flash();
$editErrors = [];
$editModalOpen = false;

$allowedStaffRoles = [
    'event_manager',
    'booking_manager',
];

$editValues = [
    'id' => 0,
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'event_manager',
    'is_active' => true,
];

function all_staff_role_label(string $role): string
{
    return $role === 'event_manager'
        ? 'Event Manager'
        : 'Booking Manager';
}

function all_staff_role_class(string $role): string
{
    return $role === 'event_manager'
        ? 'event-manager'
        : 'booking-manager';
}

/*
|--------------------------------------------------------------------------
| Load administrator
|--------------------------------------------------------------------------
*/

$adminStatement = $connection->prepare(
    "SELECT
        full_name,
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
    ? url('/' . ltrim((string) $admin['profile_image'], '/'))
    : url('/assets/icons/icon-192.png');

/*
|--------------------------------------------------------------------------
| Process edit, status and delete
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

        redirect('/admin/all_staff.php');
    }

    if ($action === 'update') {
        $staffId = max(
            0,
            (int) ($_POST['staff_id'] ?? 0)
        );

        $editValues = [
            'id' => $staffId,
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

        $existingStatement = $connection->prepare(
            "SELECT id
             FROM users
             WHERE id = ?
             AND role IN (
                'event_manager',
                'booking_manager'
             )
             LIMIT 1"
        );

        $existingStatement->execute([$staffId]);

        if (!$existingStatement->fetch()) {
            $editErrors[] =
                'The selected staff account was not found.';
        }

        if (
            mb_strlen((string) $editValues['full_name']) < 3
            || mb_strlen((string) $editValues['full_name']) > 120
        ) {
            $editErrors[] =
                'Full name must contain between 3 and 120 characters.';
        }

        if (
            !filter_var(
                $editValues['email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $editErrors[] =
                'Enter a valid email address.';
        }

        if (
            $editValues['phone'] !== ''
            && !preg_match(
                '/^[0-9+\-\s()]{7,30}$/',
                (string) $editValues['phone']
            )
        ) {
            $editErrors[] =
                'Enter a valid phone number.';
        }

        if (
            !in_array(
                $editValues['role'],
                $allowedStaffRoles,
                true
            )
        ) {
            $editErrors[] =
                'Select a valid staff role.';
        }

        if (
            $password !== ''
            && (
                strlen($password) < 8
                || !preg_match('/[A-Za-z]/', $password)
                || !preg_match('/[0-9]/', $password)
            )
        ) {
            $editErrors[] =
                'Password must contain at least 8 characters, one letter and one number.';
        }

        if ($password !== $confirmPassword) {
            $editErrors[] =
                'Password and confirm password do not match.';
        }

        if ($editErrors === []) {
            $emailStatement = $connection->prepare(
                'SELECT id
                 FROM users
                 WHERE email = ?
                 AND id <> ?
                 LIMIT 1'
            );

            $emailStatement->execute([
                $editValues['email'],
                $staffId,
            ]);

            if ($emailStatement->fetch()) {
                $editErrors[] =
                    'Another account already uses this email address.';
            }
        }

        if ($editErrors === []) {
            try {
                if ($password !== '') {
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
                        $editValues['full_name'],
                        $editValues['email'],
                        $editValues['phone'] !== ''
                            ? $editValues['phone']
                            : null,
                        $editValues['role'],
                        $editValues['is_active'] ? 1 : 0,
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
                        $editValues['full_name'],
                        $editValues['email'],
                        $editValues['phone'] !== ''
                            ? $editValues['phone']
                            : null,
                        $editValues['role'],
                        $editValues['is_active'] ? 1 : 0,
                        $staffId,
                    ]);
                }

                set_flash(
                    'success',
                    'Staff account updated successfully.'
                );

                redirect('/admin/all_staff.php');
            } catch (Throwable $exception) {
                $editErrors[] = APP_DEBUG
                    ? 'Staff account could not be updated: '
                        . $exception->getMessage()
                    : 'Staff account could not be updated.';
            }
        }

        $editModalOpen = true;
    }

    if ($action === 'toggle_status') {
        $staffId = max(
            0,
            (int) ($_POST['staff_id'] ?? 0)
        );

        $staffStatement = $connection->prepare(
            "SELECT id, is_active
             FROM users
             WHERE id = ?
             AND role IN (
                'event_manager',
                'booking_manager'
             )
             LIMIT 1"
        );

        $staffStatement->execute([$staffId]);

        $staffAccount = $staffStatement->fetch();

        if (!$staffAccount) {
            set_flash(
                'error',
                'The selected staff account was not found.'
            );

            redirect('/admin/all_staff.php');
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

        redirect('/admin/all_staff.php');
    }

    if ($action === 'delete') {
        $staffId = max(
            0,
            (int) ($_POST['staff_id'] ?? 0)
        );

        try {
            $deleteStatement = $connection->prepare(
                "DELETE FROM users
                 WHERE id = ?
                 AND role IN (
                    'event_manager',
                    'booking_manager'
                 )"
            );

            $deleteStatement->execute([$staffId]);

            if ($deleteStatement->rowCount() < 1) {
                set_flash(
                    'error',
                    'The selected staff account was not found.'
                );
            } else {
                set_flash(
                    'success',
                    'Staff account deleted successfully.'
                );
            }
        } catch (Throwable $exception) {
            set_flash(
                'error',
                APP_DEBUG
                    ? 'Staff account could not be deleted: '
                        . $exception->getMessage()
                    : 'This staff account is connected to other records. Deactivate it instead.'
            );
        }

        redirect('/admin/all_staff.php');
    }
}

/*
|--------------------------------------------------------------------------
| Search and filters
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) ($_GET['q'] ?? '')
);

$roleFilter = (string) (
    $_GET['role'] ?? 'all'
);

$statusFilter = (string) (
    $_GET['status'] ?? 'all'
);

if (
    $roleFilter !== 'all'
    && !in_array(
        $roleFilter,
        $allowedStaffRoles,
        true
    )
) {
    $roleFilter = 'all';
}

if (
    !in_array(
        $statusFilter,
        ['all', 'active', 'inactive'],
        true
    )
) {
    $statusFilter = 'all';
}

$query = "
    SELECT
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
";

$queryParameters = [];

if ($search !== '') {
    $query .= "
        AND (
            full_name LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
            OR REPLACE(role, '_', ' ') LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $queryParameters[] = $searchValue;
    $queryParameters[] = $searchValue;
    $queryParameters[] = $searchValue;
    $queryParameters[] = $searchValue;
}

if ($roleFilter !== 'all') {
    $query .= ' AND role = ?';
    $queryParameters[] = $roleFilter;
}

if ($statusFilter === 'active') {
    $query .= ' AND is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $query .= ' AND is_active = 0';
}

$query .= ' ORDER BY created_at DESC';

$staffStatement = $connection->prepare($query);
$staffStatement->execute($queryParameters);

$staffMembers = $staffStatement->fetchAll();

$shownStaffCount = count($staffMembers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>View All Staff | <?= e(APP_NAME) ?></title>

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(url('/assets/css/staff_management.css')) ?>"
    >
</head>

<body class="staff-listing-page">

    <header class="staff-listing-header">

        <a
            class="staff-listing-brand"
            href="<?= e(url('/admin/dashboard.php')) ?>"
        >
            <img
                src="<?= e(url('/assets/icons/icon-192.png')) ?>"
                alt="Wedding Event Planner"
            >

            <div>
                <strong>Wedding</strong>
                <span>Event Planner</span>
            </div>
        </a>

        <a
            class="staff-listing-admin"
            href="<?= e(url('/admin/profile.php')) ?>"
        >
            <div>
                <strong>
                    <?= e((string) $admin['full_name']) ?>
                </strong>

                <span>
                    <?= e((string) $admin['about']) ?>
                </span>
            </div>

            <img
                src="<?= e($adminImage) ?>"
                alt="Administrator profile"
            >
        </a>

    </header>

    <main class="staff-listing-main">

        <section class="staff-listing-title-row">

            <div>
                <h1>View All Staff</h1>

                <p>
                    Search, filter and manage every staff account.
                </p>
            </div>

            <a
                class="staff-back-button"
                href="<?= e(url('/admin/staff.php')) ?>"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Manage Staff
            </a>

        </section>

        <?php if ($flash): ?>
            <div
                class="staff-alert <?= $flash['type'] === 'success'
                    ? 'staff-alert-success'
                    : 'staff-alert-danger' ?>"
            >
                <?= e((string) $flash['message']) ?>
            </div>
        <?php endif; ?>

        <section class="staff-filter-box">

            <form
                class="staff-filter-form"
                method="get"
                action="<?= e(url('/admin/all_staff.php')) ?>"
            >

                <div class="staff-search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input
                        type="search"
                        name="q"
                        value="<?= e($search) ?>"
                        placeholder="Search name, email, phone or role..."
                    >
                </div>

                <select name="role">
                    <option
                        value="all"
                        <?= $roleFilter === 'all'
                            ? 'selected'
                            : '' ?>
                    >
                        All Roles
                    </option>

                    <option
                        value="event_manager"
                        <?= $roleFilter === 'event_manager'
                            ? 'selected'
                            : '' ?>
                    >
                        Event Manager
                    </option>

                    <option
                        value="booking_manager"
                        <?= $roleFilter === 'booking_manager'
                            ? 'selected'
                            : '' ?>
                    >
                        Booking Manager
                    </option>
                </select>

                <select name="status">
                    <option
                        value="all"
                        <?= $statusFilter === 'all'
                            ? 'selected'
                            : '' ?>
                    >
                        All Statuses
                    </option>

                    <option
                        value="active"
                        <?= $statusFilter === 'active'
                            ? 'selected'
                            : '' ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $statusFilter === 'inactive'
                            ? 'selected'
                            : '' ?>
                    >
                        Inactive
                    </option>
                </select>

                <button
                    class="staff-search-button"
                    type="submit"
                >
                    <i class="fa-solid fa-filter"></i>
                    Apply Filter
                </button>

                <a
                    class="staff-clear-button"
                    href="<?= e(url('/admin/all_staff.php')) ?>"
                >
                    <i class="fa-solid fa-xmark"></i>
                    Clear
                </a>

            </form>

        </section>

        <section class="staff-listing-results-heading">

            <h2>Staff Directory</h2>

            <p>
                <?= e((string) $shownStaffCount) ?>
                matching staff account<?= $shownStaffCount === 1
                    ? ''
                    : 's' ?> found.
            </p>

        </section>

        <section class="staff-section-box staff-directory-table-box">

            <?php if ($staffMembers === []): ?>

                <div class="staff-empty-state">
                    <i class="fa-solid fa-user-slash"></i>

                    <h3>No matching staff accounts</h3>

                    <p>
                        Change the search or filter options and try again.
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
                                                all_staff_role_class(
                                                    (string) $staff['role']
                                                )
                                            ) ?>"
                                        >
                                            <?= e(
                                                all_staff_role_label(
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

                                            <button
                                                class="staff-edit-button js-edit-staff"
                                                type="button"
                                                data-staff-id="<?= e(
                                                    (string) $staff['id']
                                                ) ?>"
                                                data-full-name="<?= e(
                                                    (string) $staff['full_name']
                                                ) ?>"
                                                data-email="<?= e(
                                                    (string) $staff['email']
                                                ) ?>"
                                                data-phone="<?= e(
                                                    (string) (
                                                        $staff['phone'] ?? ''
                                                    )
                                                ) ?>"
                                                data-role="<?= e(
                                                    (string) $staff['role']
                                                ) ?>"
                                                data-active="<?= (int) $staff['is_active'] === 1
                                                    ? '1'
                                                    : '0' ?>"
                                            >
                                                <i class="fa-solid fa-pen"></i>
                                                Edit
                                            </button>

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

    </main>

    <div
        class="staff-modal <?= $editModalOpen
            ? 'open'
            : '' ?>"
        id="staffEditModal"
        aria-hidden="<?= $editModalOpen
            ? 'false'
            : 'true' ?>"
    >

        <div
            class="staff-modal-backdrop"
            data-close-edit-modal
        ></div>

        <div
            class="staff-modal-dialog"
            role="dialog"
            aria-modal="true"
        >

            <div class="staff-modal-header">

                <div>
                    <h2>Edit Staff Account</h2>

                    <p>
                        Update staff details or assign a new password.
                    </p>
                </div>

                <button
                    class="staff-modal-close"
                    type="button"
                    data-close-edit-modal
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>

            </div>

            <?php if ($editErrors !== []): ?>
                <div class="staff-alert staff-alert-danger">
                    <ul>
                        <?php foreach ($editErrors as $error): ?>
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
                    value="update"
                >

                <input
                    type="hidden"
                    id="editStaffId"
                    name="staff_id"
                    value="<?= e(
                        (string) $editValues['id']
                    ) ?>"
                >

                <div class="staff-form-grid">

                    <div class="staff-input-box">
                        <label for="editStaffFullName">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="editStaffFullName"
                            name="full_name"
                            value="<?= e(
                                (string) $editValues['full_name']
                            ) ?>"
                            required
                        >
                    </div>

                    <div class="staff-input-box">
                        <label for="editStaffEmail">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="editStaffEmail"
                            name="email"
                            value="<?= e(
                                (string) $editValues['email']
                            ) ?>"
                            required
                        >
                    </div>

                    <div class="staff-input-box">
                        <label for="editStaffPhone">
                            Phone Number
                        </label>

                        <input
                            type="text"
                            id="editStaffPhone"
                            name="phone"
                            value="<?= e(
                                (string) $editValues['phone']
                            ) ?>"
                        >
                    </div>

                    <div class="staff-input-box">
                        <label for="editStaffRole">
                            Staff Role
                        </label>

                        <select
                            id="editStaffRole"
                            name="role"
                        >
                            <option value="event_manager">
                                Event Manager
                            </option>

                            <option value="booking_manager">
                                Booking Manager
                            </option>
                        </select>
                    </div>

                    <div class="staff-input-box">
                        <label for="editStaffPassword">
                            New Password
                        </label>

                        <div class="staff-password-field">
                            <input
                                type="password"
                                id="editStaffPassword"
                                name="password"
                                minlength="8"
                            >

                            <button
                                class="staff-password-toggle"
                                type="button"
                                data-password-target="editStaffPassword"
                            >
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>

                        <span class="staff-help">
                            Leave empty to keep the current password.
                        </span>
                    </div>

                    <div class="staff-input-box">
                        <label for="editStaffConfirmPassword">
                            Confirm New Password
                        </label>

                        <div class="staff-password-field">
                            <input
                                type="password"
                                id="editStaffConfirmPassword"
                                name="confirm_password"
                                minlength="8"
                            >

                            <button
                                class="staff-password-toggle"
                                type="button"
                                data-password-target="editStaffConfirmPassword"
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
                                    id="editStaffActive"
                                    name="is_active"
                                    value="1"
                                    <?= $editValues['is_active']
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
                        Update Staff
                    </button>

                    <button
                        class="staff-cancel-button"
                        type="button"
                        data-close-edit-modal
                    >
                        Cancel
                    </button>

                </div>

            </form>

        </div>

    </div>

    <script>
        const editModal =
            document.getElementById("staffEditModal");

        const editStaffId =
            document.getElementById("editStaffId");

        const editStaffFullName =
            document.getElementById("editStaffFullName");

        const editStaffEmail =
            document.getElementById("editStaffEmail");

        const editStaffPhone =
            document.getElementById("editStaffPhone");

        const editStaffRole =
            document.getElementById("editStaffRole");

        const editStaffActive =
            document.getElementById("editStaffActive");

        function openEditModal(button) {
            editStaffId.value =
                button.dataset.staffId || "0";

            editStaffFullName.value =
                button.dataset.fullName || "";

            editStaffEmail.value =
                button.dataset.email || "";

            editStaffPhone.value =
                button.dataset.phone || "";

            editStaffRole.value =
                button.dataset.role || "event_manager";

            editStaffActive.checked =
                button.dataset.active === "1";

            document.getElementById(
                "editStaffPassword"
            ).value = "";

            document.getElementById(
                "editStaffConfirmPassword"
            ).value = "";

            editModal.classList.add("open");
            editModal.setAttribute("aria-hidden", "false");

            document.body.classList.add("staff-modal-open");
        }

        function closeEditModal() {
            editModal.classList.remove("open");
            editModal.setAttribute("aria-hidden", "true");

            document.body.classList.remove("staff-modal-open");
        }

        document
            .querySelectorAll(".js-edit-staff")
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        openEditModal(button);
                    }
                );
            });

        document
            .querySelectorAll("[data-close-edit-modal]")
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    closeEditModal
                );
            });

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

        <?php if ($editModalOpen): ?>
            document.body.classList.add("staff-modal-open");

            editStaffRole.value =
                <?= json_encode($editValues['role']) ?>;
        <?php endif; ?>
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>