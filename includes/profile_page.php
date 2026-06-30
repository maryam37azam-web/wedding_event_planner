<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/role_check.php';

if (
    !isset($profileRole)
    || !is_string($profileRole)
) {
    http_response_code(500);
    exit('Profile role was not configured.');
}

require_role($profileRole);

$userId = (int) $_SESSION['user_id'];

$roleLabels = [
    'admin' => 'Administrator',
    'event_manager' => 'Event Manager',
    'booking_manager' => 'Booking Manager',
    'customer' => 'Customer',
];

$roleLabel = $roleLabels[$profileRole] ?? 'User';

/*
|--------------------------------------------------------------------------
| Sidebar menu
|--------------------------------------------------------------------------
*/

$menus = [
    'admin' => [
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
            'Manage Staff',
            'fa-users-gear',
            '/admin/staff.php',
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

    'event_manager' => [
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

    'booking_manager' => [
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

    'customer' => [
        [
            'Dashboard',
            'fa-house',
            '/customer/dashboard.php',
        ],

        [
            'Book Event',
            'fa-calendar-check',
            '/customer/booking.php',
        ],

        [
            'My Bookings',
            'fa-book-open',
            '/customer/my_bookings.php',
        ],

        [
            'Packages',
            'fa-gift',
            '/customer/packages.php',
        ],

        [
            'Venues',
            'fa-hotel',
            '/customer/venues.php',
        ],

        [
            'Gallery',
            'fa-images',
            '/customer/gallery.php',
        ],

        [
            'Manage Profile',
            'fa-user',
            '/customer/profile.php',
        ],
    ],
];

$currentProfilePaths = [
    'admin' => '/admin/profile.php',
    'event_manager' => '/event_manager/profile.php',
    'booking_manager' =>
        '/booking_manager/profile.php',
    'customer' => '/customer/profile.php',
];

$currentProfilePath =
    $currentProfilePaths[$profileRole];

$canEditEmail = in_array(
    $profileRole,
    [
        'admin',
        'customer',
    ],
    true
);

$errors = [];
$flash = get_flash();

/*
|--------------------------------------------------------------------------
| Load current profile
|--------------------------------------------------------------------------
*/

$userStatement = db()->prepare(
    'SELECT
        id,
        full_name,
        email,
        phone,
        password,
        role,
        profile_image,
        about
     FROM users
     WHERE id = ?
     AND role = ?
     LIMIT 1'
);

$userStatement->execute([
    $userId,
    $profileRole,
]);

$user = $userStatement->fetch();

if (!$user) {
    session_destroy();

    redirect(
        '/auth/staff_login.php'
    );
}

/*
|--------------------------------------------------------------------------
| Update profile
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $fullName = trim(
        (string) (
            $_POST['full_name'] ?? ''
        )
    );

    $email = strtolower(
        trim(
            (string) (
                $_POST['email']
                ?? $user['email']
            )
        )
    );

    $phone = trim(
        (string) (
            $_POST['phone'] ?? ''
        )
    );

    $about = trim(
        (string) (
            $_POST['about'] ?? ''
        )
    );

    $currentPassword = (string) (
        $_POST['current_password'] ?? ''
    );

    $newPassword = (string) (
        $_POST['new_password'] ?? ''
    );

    $confirmPassword = (string) (
        $_POST['confirm_password'] ?? ''
    );

    if (
        !verify_csrf(
            $submittedToken
        )
    ) {
        $errors[] =
            'Your form session expired. Refresh and try again.';
    }

    if (
        mb_strlen($fullName) < 3
        || mb_strlen($fullName) > 120
    ) {
        $errors[] =
            'Full name must contain between 3 and 120 characters.';
    }

    if (
        $canEditEmail
        && !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $errors[] =
            'Enter a valid email address.';
    }

    if (
        $phone !== ''
        && !preg_match(
            '/^[0-9+\-\s()]{7,30}$/',
            $phone
        )
    ) {
        $errors[] =
            'Enter a valid phone number.';
    }

    if (
        mb_strlen($about) > 1000
    ) {
        $errors[] =
            'About information cannot exceed 1,000 characters.';
    }

    $emailChanged =
        $canEditEmail
        && $email
            !== (string) $user['email'];

    $passwordChangeRequested =
        $newPassword !== ''
        || $confirmPassword !== '';

    if (
        $emailChanged
        || $passwordChangeRequested
    ) {
        if (
            $currentPassword === ''
            || !password_verify(
                $currentPassword,
                (string) $user['password']
            )
        ) {
            $errors[] =
                'Enter your current password to change your email or password.';
        }
    }

    if ($passwordChangeRequested) {
        if (
            strlen($newPassword) < 8
        ) {
            $errors[] =
                'New password must contain at least 8 characters.';
        }

        if (
            !preg_match(
                '/[A-Za-z]/',
                $newPassword
            )
            || !preg_match(
                '/[0-9]/',
                $newPassword
            )
        ) {
            $errors[] =
                'New password must contain at least one letter and one number.';
        }

        if (
            $newPassword
            !== $confirmPassword
        ) {
            $errors[] =
                'New password confirmation does not match.';
        }
    }

    if (
        $emailChanged
        && $errors === []
    ) {
        $emailCheckStatement =
            db()->prepare(
                'SELECT id
                 FROM users
                 WHERE email = ?
                 AND id <> ?
                 LIMIT 1'
            );

        $emailCheckStatement->execute([
            $email,
            $userId,
        ]);

        if (
            $emailCheckStatement->fetch()
        ) {
            $errors[] =
                'Another account already uses this email address.';
        }
    }

    $newProfileImage = (string) (
        $user['profile_image'] ?? ''
    );

    $uploadedImagePath = null;

    if (
        isset(
            $_FILES['profile_image']
        )
        && (int) $_FILES[
            'profile_image'
        ]['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $upload =
            $_FILES['profile_image'];

        if (
            (int) $upload['error']
            !== UPLOAD_ERR_OK
        ) {
            $errors[] =
                'The profile image could not be uploaded.';
        } elseif (
            (int) $upload['size']
            > 2 * 1024 * 1024
        ) {
            $errors[] =
                'The profile image cannot exceed 2 MB.';
        } else {
            $finfo = new finfo(
                FILEINFO_MIME_TYPE
            );

            $mimeType =
                $finfo->file(
                    (string) $upload[
                        'tmp_name'
                    ]
                );

            $allowedImageTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            if (
                !is_string($mimeType)
                || !isset(
                    $allowedImageTypes[
                        $mimeType
                    ]
                )
            ) {
                $errors[] =
                    'Upload a JPG, PNG or WEBP profile image.';
            } else {
                $extension =
                    $allowedImageTypes[
                        $mimeType
                    ];

                $uploadDirectory =
                    dirname(__DIR__)
                    . '/uploads/profiles';

                if (
                    !is_dir(
                        $uploadDirectory
                    )
                    && !mkdir(
                        $uploadDirectory,
                        0755,
                        true
                    )
                ) {
                    $errors[] =
                        'The profile upload folder could not be created.';
                }

                if ($errors === []) {
                    $fileName = sprintf(
                        'profile_%d_%s.%s',
                        $userId,
                        bin2hex(
                            random_bytes(8)
                        ),
                        $extension
                    );

                    $absoluteDestination =
                        $uploadDirectory
                        . DIRECTORY_SEPARATOR
                        . $fileName;

                    if (
                        !move_uploaded_file(
                            (string) $upload[
                                'tmp_name'
                            ],
                            $absoluteDestination
                        )
                    ) {
                        $errors[] =
                            'The profile image could not be saved.';
                    } else {
                        $uploadedImagePath =
                            'uploads/profiles/'
                            . $fileName;

                        $newProfileImage =
                            $uploadedImagePath;
                    }
                }
            }
        }
    }

    if ($errors === []) {
        $connection = db();

        try {
            $connection
                ->beginTransaction();

            $updatedEmail =
                $canEditEmail
                    ? $email
                    : (string) $user[
                        'email'
                    ];

            if (
                $passwordChangeRequested
            ) {
                $updateStatement =
                    $connection->prepare(
                        'UPDATE users
                         SET full_name = ?,
                             email = ?,
                             phone = ?,
                             profile_image = ?,
                             about = ?,
                             password = ?
                         WHERE id = ?'
                    );

                $updateStatement->execute([
                    $fullName,
                    $updatedEmail,

                    $phone !== ''
                        ? $phone
                        : null,

                    $newProfileImage !== ''
                        ? $newProfileImage
                        : null,

                    $about !== ''
                        ? $about
                        : null,

                    password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    ),

                    $userId,
                ]);
            } else {
                $updateStatement =
                    $connection->prepare(
                        'UPDATE users
                         SET full_name = ?,
                             email = ?,
                             phone = ?,
                             profile_image = ?,
                             about = ?
                         WHERE id = ?'
                    );

                $updateStatement->execute([
                    $fullName,
                    $updatedEmail,

                    $phone !== ''
                        ? $phone
                        : null,

                    $newProfileImage !== ''
                        ? $newProfileImage
                        : null,

                    $about !== ''
                        ? $about
                        : null,

                    $userId,
                ]);
            }

            $connection->commit();

            $oldProfileImage = (string) (
                $user['profile_image']
                ?? ''
            );

            if (
                $uploadedImagePath !== null
                && $oldProfileImage !== ''
                && str_starts_with(
                    $oldProfileImage,
                    'uploads/profiles/'
                )
            ) {
                $oldAbsolutePath =
                    dirname(__DIR__)
                    . '/'
                    . $oldProfileImage;

                if (
                    is_file(
                        $oldAbsolutePath
                    )
                ) {
                    unlink(
                        $oldAbsolutePath
                    );
                }
            }

            $_SESSION['user_name'] =
                $fullName;

            $_SESSION['user_email'] =
                $updatedEmail;

            set_flash(
                'success',
                'Your profile was updated successfully.'
            );

            redirect(
                $currentProfilePath
            );
        } catch (
            Throwable $exception
        ) {
            if (
                $connection
                    ->inTransaction()
            ) {
                $connection
                    ->rollBack();
            }

            if (
                $uploadedImagePath !== null
            ) {
                $newAbsolutePath =
                    dirname(__DIR__)
                    . '/'
                    . $uploadedImagePath;

                if (
                    is_file(
                        $newAbsolutePath
                    )
                ) {
                    unlink(
                        $newAbsolutePath
                    );
                }
            }

            $errors[] = APP_DEBUG
                ? 'Profile update failed: '
                    . $exception
                        ->getMessage()
                : 'Profile update failed. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Reload profile after processing
|--------------------------------------------------------------------------
*/

$userStatement->execute([
    $userId,
    $profileRole,
]);

$user =
    $userStatement->fetch();

$profileImage = !empty(
    $user['profile_image']
)
    ? url(
        '/'
        . ltrim(
            (string) $user[
                'profile_image'
            ],
            '/'
        )
    )
    : url(
        '/assets/icons/icon-192.png'
    );
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
        Manage Profile | <?= e(APP_NAME) ?>
    </title>

    <?php require __DIR__ . '/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/profile.css?v=65'
            )
        ) ?>"
    >
</head>

<body class="profile-page">

    <aside
        class="profile-sidebar"
        id="profileSidebar"
    >

        <div class="sidebar-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="sidebar-profile">

            <img
                src="<?= e(
                    $profileImage
                ) ?>"
                alt="Profile image"
            >

            <strong>
                <?= e(
                    $user['full_name']
                ) ?>
            </strong>

            <?php if (
                $profileRole !== 'admin'
            ): ?>

                <span>
                    <?= e($roleLabel) ?>
                </span>

            <?php endif; ?>

        </div>

        <nav class="sidebar-menu">

            <?php foreach (
                $menus[$profileRole]
                as $menuItem
            ): ?>
                <?php
                [
                    $label,
                    $icon,
                    $path,
                ] = $menuItem;

                $isActive =
                    $path
                    === $currentProfilePath;
                ?>

                <a
                    class="<?= $isActive
                        ? 'active'
                        : '' ?>"

                    href="<?= e(
                        url($path)
                    ) ?>"
                >
                    <i
                        class="fa-solid <?= e(
                            $icon
                        ) ?>"
                    ></i>

                    <?= e($label) ?>
                </a>

            <?php endforeach; ?>

            <a
                class="logout-link"
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
        class="sidebar-overlay"
        id="sidebarOverlay"
    ></div>

    <main class="profile-main">

        <header class="profile-topbar">

            <div class="profile-topbar-left">

                <button
                    class="mobile-menu-button"
                    id="mobileMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>
                    <h1>Manage Profile</h1>

                    <p>
                        Update your personal account details.
                    </p>
                </div>

            </div>

        </header>

        <section class="profile-box">

            <div class="profile-header">

                <div class="profile-header-image">

                    <img
                        id="profileImagePreview"
                        src="<?= e(
                            $profileImage
                        ) ?>"
                        alt="Current profile image"
                    >

                </div>

                <div>
                    <h2>
                        <?= e($roleLabel) ?>
                        Profile
                    </h2>

                    <p>
                        Update your personal details and password.
                    </p>
                </div>

            </div>

            <?php if ($flash): ?>

                <div
                    class="profile-alert <?= $flash[
                        'type'
                    ] === 'success'
                        ? 'profile-alert-success'
                        : 'profile-alert-danger' ?>"
                >
                    <?= e(
                        $flash['message']
                    ) ?>
                </div>

            <?php endif; ?>

            <?php if (
                $errors !== []
            ): ?>

                <div
                    class="profile-alert profile-alert-danger"
                >

                    <ul>
                        <?php foreach (
                            $errors as $error
                        ): ?>

                            <li>
                                <?= e($error) ?>
                            </li>

                        <?php endforeach; ?>
                    </ul>

                </div>

            <?php endif; ?>

            <form
                method="post"
                enctype="multipart/form-data"
            >

                <?= csrf_field() ?>

                <div class="profile-form-grid">

                    <div class="profile-input-box">

                        <label for="full_name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?= e(
                                $user['full_name']
                            ) ?>"
                            maxlength="120"
                            required
                        >

                    </div>

                    <div class="profile-input-box">

                        <label for="email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= e(
                                $user['email']
                            ) ?>"
                            maxlength="190"
                            <?= $canEditEmail
                                ? ''
                                : 'readonly' ?>
                            required
                        >

                        <?php if (
                            !$canEditEmail
                        ): ?>

                            <span class="profile-help">
                                Staff email can only be
                                changed by the administrator.
                            </span>

                        <?php endif; ?>

                    </div>

                    <div class="profile-input-box">

                        <label for="phone">
                            Phone Number
                        </label>

                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="<?= e(
                                $user['phone']
                                ?? ''
                            ) ?>"
                            maxlength="30"
                        >

                    </div>

                    <div class="profile-input-box">

                        <label for="profile_image">
                            Profile Image
                        </label>

                        <input
                            type="file"
                            id="profile_image"
                            name="profile_image"
                            accept=".jpg,.jpeg,.png,.webp"
                        >

                        <span class="profile-help">
                            JPG, PNG or WEBP.
                            Maximum size: 2 MB.
                        </span>

                    </div>

                    <div
                        class="profile-input-box full-width"
                    >

                        <label for="about">
                            About
                        </label>

                        <textarea
                            id="about"
                            name="about"
                            maxlength="1000"
                            rows="3"
                        ><?= e(
                            $user['about']
                            ?? ''
                        ) ?></textarea>

                    </div>

                </div>

                <div class="password-section">

                    <h3>Account Security</h3>

                    <p>
                        Leave the new-password fields empty
                        when you do not want to change your
                        password.
                    </p>

                    <div class="profile-form-grid">

                        <div class="profile-input-box">

                            <label for="current_password">
                                Current Password
                            </label>

                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                autocomplete="current-password"
                            >

                        </div>

                        <div class="profile-input-box">

                            <label for="new_password">
                                New Password
                            </label>

                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                minlength="8"
                                autocomplete="new-password"
                            >

                        </div>

                        <div class="profile-input-box">

                            <label for="confirm_password">
                                Confirm New Password
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                minlength="8"
                                autocomplete="new-password"
                            >

                        </div>

                    </div>

                </div>

                <button
                    class="update-profile-button"
                    type="submit"
                >
                    Update Profile
                </button>

            </form>

        </section>

    </main>

    <script>
        const sidebar =
            document.getElementById(
                "profileSidebar"
            );

        const overlay =
            document.getElementById(
                "sidebarOverlay"
            );

        const menuButton =
            document.getElementById(
                "mobileMenuButton"
            );

        function closeSidebar() {
            sidebar.classList.remove(
                "open"
            );

            overlay.classList.remove(
                "open"
            );
        }

        menuButton.addEventListener(
            "click",
            function () {
                sidebar.classList.add(
                    "open"
                );

                overlay.classList.add(
                    "open"
                );
            }
        );

        overlay.addEventListener(
            "click",
            closeSidebar
        );

        const imageInput =
            document.getElementById(
                "profile_image"
            );

        const imagePreview =
            document.getElementById(
                "profileImagePreview"
            );

        let temporaryImageUrl = null;

        imageInput.addEventListener(
            "change",
            function () {
                const file =
                    imageInput.files[0];

                if (!file) {
                    return;
                }

                if (temporaryImageUrl) {
                    URL.revokeObjectURL(
                        temporaryImageUrl
                    );
                }

                temporaryImageUrl =
                    URL.createObjectURL(
                        file
                    );

                imagePreview.src =
                    temporaryImageUrl;
            }
        );
    </script>

    <?php require __DIR__ . '/pwa_scripts.php'; ?>

</body>
</html>