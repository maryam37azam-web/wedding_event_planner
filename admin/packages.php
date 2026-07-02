<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/package_helpers.php';

require_role('admin');

$connection = db();
$adminId = (int) $_SESSION['user_id'];
$errors = [];
$flash = get_flash();

$allowedFilters = [
    'latest',
    'all',
    'active',
    'inactive',
    'popular',
];

$filter = strtolower(
    trim((string) ($_GET['filter'] ?? 'latest'))
);

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'latest';
}

$returnTo = strtolower(
    trim((string) ($_GET['return_to'] ?? 'manage'))
);

$returnTo = $returnTo === 'all'
    ? 'all'
    : 'manage';

$editId = max(
    0,
    (int) ($_GET['edit'] ?? 0)
);

$showCreateModal =
    isset($_GET['add'])
    && $_GET['add'] === '1';

$editingPackage = null;
$isPackageFormPost = false;

$formValues = [
    'name' => '',
    'venue_name' => '',
    'venue_location' => '',
    'short_description' => '',
    'description' => '',
    'price' => '',
    'guest_capacity' => '',
    'decoration_type' => '',
    'catering_menu' => '',
    'features' => '',
    'basic_music' => false,
    'live_music' => false,
    'status' => 'active',
];

function admin_package_manage_path(
    string $filter = 'latest',
    array $extra = []
): string {
    $parameters = [];

    if ($filter !== 'latest') {
        $parameters['filter'] = $filter;
    }

    foreach ($extra as $key => $value) {
        $parameters[$key] = $value;
    }

    $path = '/admin/packages.php';

    if ($parameters !== []) {
        $path .= '?' . http_build_query($parameters);
    }

    return $path;
}

function admin_package_list_heading(
    string $filter
): string {
    return match ($filter) {
        'all' => 'All Wedding Packages',
        'active' => 'Active Wedding Packages',
        'inactive' => 'Inactive Wedding Packages',
        'popular' => 'Popular Wedding Package',
        default => 'Latest Wedding Packages',
    };
}

function admin_package_empty_message(
    string $filter
): string {
    return match ($filter) {
        'active' => 'No active packages are available.',
        'inactive' => 'No inactive packages are available.',
        'popular' => 'A popular package will appear after customers start making bookings.',
        default => 'Create the first wedding package using the Add New Package button.',
    };
}

function admin_package_redirect_path(
    string $returnTo,
    string $filter
): string {
    if ($returnTo === 'all') {
        return '/admin/all_packages.php';
    }

    return admin_package_manage_path($filter);
}

/*
|--------------------------------------------------------------------------
| Load administrator information
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
    ? url(
        '/'
        . ltrim(
            (string) $admin['profile_image'],
            '/'
        )
    )
    : url('/assets/icons/icon-192.png');

$unreadStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE recipient_id = ?
     AND is_read = 0'
);

$unreadStatement->execute([
    $adminId,
]);

$unreadNotifications =
    (int) $unreadStatement->fetchColumn();

/*
|--------------------------------------------------------------------------
| Package summary
|--------------------------------------------------------------------------
*/

$summary = $connection
    ->query(
        "SELECT
            COUNT(*) AS total_packages,
            COALESCE(
                SUM(status = 'active'),
                0
            ) AS active_packages,
            COALESCE(
                SUM(status = 'inactive'),
                0
            ) AS inactive_packages
         FROM packages"
    )
    ->fetch();

$totalPackages =
    (int) ($summary['total_packages'] ?? 0);

$activePackages =
    (int) ($summary['active_packages'] ?? 0);

$inactivePackages =
    (int) ($summary['inactive_packages'] ?? 0);

$popularPackageStatement = $connection->query(
    "SELECT
        packages.id,
        packages.name,
        COUNT(bookings.id) AS booking_total
     FROM packages
     INNER JOIN bookings
        ON bookings.package_id = packages.id
        AND bookings.booking_status <> 'cancelled'
     GROUP BY
        packages.id,
        packages.name,
        packages.created_at
     HAVING COUNT(bookings.id) > 0
     ORDER BY
        booking_total DESC,
        packages.created_at DESC
     LIMIT 1"
);

$popularPackage =
    $popularPackageStatement->fetch();

$popularPackageId = $popularPackage
    ? (int) $popularPackage['id']
    : 0;

$popularPackageName = $popularPackage
    ? (string) $popularPackage['name']
    : 'No booking data';

/*
|--------------------------------------------------------------------------
| Process package actions
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $action = trim(
        (string) ($_POST['action'] ?? '')
    );

    $returnFilter = strtolower(
        trim(
            (string) (
                $_POST['return_filter']
                ?? 'latest'
            )
        )
    );

    $returnTo = strtolower(
        trim(
            (string) (
                $_POST['return_to']
                ?? 'manage'
            )
        )
    );

    if (
        !in_array(
            $returnFilter,
            $allowedFilters,
            true
        )
    ) {
        $returnFilter = 'latest';
    }

    $returnTo = $returnTo === 'all'
        ? 'all'
        : 'manage';

    $returnPath = admin_package_redirect_path(
        $returnTo,
        $returnFilter
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

    /*
    |--------------------------------------------------------------------------
    | Delete package
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'delete'
        && $errors === []
    ) {
        $packageId = max(
            0,
            (int) ($_POST['package_id'] ?? 0)
        );

        $packageStatement =
            $connection->prepare(
                'SELECT
                    main_image,
                    image_one,
                    image_two,
                    image_three,
                    image_four
                 FROM packages
                 WHERE id = ?
                 LIMIT 1'
            );

        $packageStatement->execute([
            $packageId,
        ]);

        $packageToDelete =
            $packageStatement->fetch();

        if (!$packageToDelete) {
            set_flash(
                'error',
                'The selected package was not found.'
            );

            redirect($returnPath);
        }

        try {
            $deleteStatement =
                $connection->prepare(
                    'DELETE FROM packages
                     WHERE id = ?'
                );

            $deleteStatement->execute([
                $packageId,
            ]);

            foreach (
                [
                    'main_image',
                    'image_one',
                    'image_two',
                    'image_three',
                    'image_four',
                ] as $column
            ) {
                delete_package_image(
                    $packageToDelete[$column]
                    ?? null
                );
            }

            set_flash(
                'success',
                'Package deleted successfully.'
            );
        } catch (Throwable $exception) {
            set_flash(
                'error',
                APP_DEBUG
                    ? 'Package deletion failed: '
                        . $exception->getMessage()
                    : 'This package could not be deleted because it may be connected to a booking.'
            );
        }

        redirect($returnPath);
    }

    /*
    |--------------------------------------------------------------------------
    | Create or update package
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $action,
            ['create', 'update'],
            true
        )
    ) {
        $isPackageFormPost = true;

        $packageId = max(
            0,
            (int) ($_POST['package_id'] ?? 0)
        );

        $existingPackage = null;

        if ($action === 'update') {
            $existingStatement =
                $connection->prepare(
                    'SELECT *
                     FROM packages
                     WHERE id = ?
                     LIMIT 1'
                );

            $existingStatement->execute([
                $packageId,
            ]);

            $existingPackage =
                $existingStatement->fetch();

            if (!$existingPackage) {
                $errors[] =
                    'The package being edited was not found.';
            } else {
                $editId = $packageId;
                $editingPackage =
                    $existingPackage;
            }
        }

        $name = trim(
            (string) ($_POST['name'] ?? '')
        );

        $venueName = trim(
            (string) (
                $_POST['venue_name']
                ?? ''
            )
        );

        $venueLocation = trim(
            (string) (
                $_POST['venue_location']
                ?? ''
            )
        );

        $shortDescription = trim(
            (string) (
                $_POST['short_description']
                ?? ''
            )
        );

        $description = trim(
            (string) (
                $_POST['description']
                ?? ''
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

        $guestCapacity = (int) (
            $_POST['guest_capacity'] ?? 0
        );

        $decorationType = trim(
            (string) (
                $_POST['decoration_type']
                ?? ''
            )
        );

        $cateringMenu = trim(
            (string) (
                $_POST['catering_menu']
                ?? ''
            )
        );

        $featuresInput = trim(
            (string) (
                $_POST['features']
                ?? ''
            )
        );

        $musicOptions =
            $_POST['music_options'] ?? [];

        if (!is_array($musicOptions)) {
            $musicOptions = [];
        }

        $basicMusic = in_array(
            'basic_music',
            $musicOptions,
            true
        );

        $liveMusic = in_array(
            'live_music',
            $musicOptions,
            true
        );

        $status =
            isset($_POST['activate_on_website'])
                ? 'active'
                : 'inactive';

        $formValues = [
            'name' => $name,
            'venue_name' => $venueName,
            'venue_location' => $venueLocation,
            'short_description' =>
                $shortDescription,
            'description' => $description,
            'price' => $priceInput,
            'guest_capacity' =>
                $guestCapacity > 0
                    ? (string) $guestCapacity
                    : '',
            'decoration_type' =>
                $decorationType,
            'catering_menu' =>
                $cateringMenu,
            'features' =>
                $featuresInput,
            'basic_music' =>
                $basicMusic,
            'live_music' =>
                $liveMusic,
            'status' =>
                $status,
        ];

        if (
            mb_strlen($name) < 3
            || mb_strlen($name) > 150
        ) {
            $errors[] =
                'Package name must contain between 3 and 150 characters.';
        }

        if (
            mb_strlen($venueName) < 3
            || mb_strlen($venueName) > 150
        ) {
            $errors[] =
                'Venue name must contain between 3 and 150 characters.';
        }

        if (
            mb_strlen($venueLocation) < 3
            || mb_strlen($venueLocation) > 255
        ) {
            $errors[] =
                'Venue location must contain between 3 and 255 characters.';
        }

        if (
            mb_strlen($shortDescription) < 5
            || mb_strlen($shortDescription) > 255
        ) {
            $errors[] =
                'Short description must contain between 5 and 255 characters.';
        }

        if ($description === '') {
            $errors[] =
                'Enter the complete package description.';
        }

        if (
            $priceClean === null
            || $priceClean === ''
            || !is_numeric($priceClean)
            || (float) $priceClean < 0
        ) {
            $errors[] =
                'Enter a valid package price.';
        }

        if ($guestCapacity < 1) {
            $errors[] =
                'Guest capacity must be at least 1.';
        }

        if ($decorationType === '') {
            $errors[] =
                'Enter the package decoration type.';
        }

        if ($cateringMenu === '') {
            $errors[] =
                'Enter the catering menu details.';
        }

        if (!$basicMusic && !$liveMusic) {
            $errors[] =
                'Select Basic Music, Live Music or both.';
        }

        $imageValues = [
            'main_image' =>
                $existingPackage['main_image']
                ?? null,
            'image_one' =>
                $existingPackage['image_one']
                ?? null,
            'image_two' =>
                $existingPackage['image_two']
                ?? null,
            'image_three' =>
                $existingPackage['image_three']
                ?? null,
            'image_four' =>
                $existingPackage['image_four']
                ?? null,
        ];

        $newUploadedImages = [];
        $oldImagesToDelete = [];

        if ($errors === []) {
            try {
                foreach (
                    array_keys(
                        package_image_fields()
                    ) as $index => $column
                ) {
                    $file =
                        $_FILES[$column]
                        ?? [
                            'error' =>
                                UPLOAD_ERR_NO_FILE,
                        ];

                    $fileError = (int) (
                        $file['error']
                        ?? UPLOAD_ERR_NO_FILE
                    );

                    $hasNewFile =
                        $fileError
                        !== UPLOAD_ERR_NO_FILE;

                    $removeRequested =
                        $action === 'update'
                        && isset(
                            $_POST[
                                'remove_'
                                . $column
                            ]
                        );

                    if (
                        $action === 'create'
                        && !$hasNewFile
                    ) {
                        throw new RuntimeException(
                            'Upload one main image and all three package gallery images.'
                        );
                    }

                    if ($hasNewFile) {
                        $newPath =
                            upload_package_image(
                                $file,
                                'package_'
                                . (
                                    $packageId > 0
                                        ? $packageId
                                        : 'new'
                                )
                                . '_'
                                . ($index + 1)
                            );

                        if ($newPath !== null) {
                            $newUploadedImages[] =
                                $newPath;

                            if (
                                !empty(
                                    $imageValues[
                                        $column
                                    ]
                                )
                            ) {
                                $oldImagesToDelete[] =
                                    (string) $imageValues[
                                        $column
                                    ];
                            }

                            $imageValues[$column] =
                                $newPath;
                        }
                    } elseif ($removeRequested) {
                        if (
                            !empty(
                                $imageValues[$column]
                            )
                        ) {
                            $oldImagesToDelete[] =
                                (string) $imageValues[
                                    $column
                                ];
                        }

                        $imageValues[$column] = null;
                    }
                }

                if (
                    $action === 'update'
                    && !empty(
                        $imageValues['image_four']
                    )
                ) {
                    $oldImagesToDelete[] =
                        (string) $imageValues[
                            'image_four'
                        ];

                    $imageValues['image_four'] =
                        null;
                }
            } catch (Throwable $exception) {
                foreach (
                    $newUploadedImages
                    as $newImage
                ) {
                    delete_package_image(
                        $newImage
                    );
                }

                $newUploadedImages = [];
                $errors[] =
                    $exception->getMessage();
            }
        }

        if ($errors === []) {
            $normalisedFeatures = implode(
                PHP_EOL,
                package_feature_lines(
                    $featuresInput
                )
            );

            try {
                $connection->beginTransaction();

                if ($action === 'create') {
                    $saveStatement =
                        $connection->prepare(
                            'INSERT INTO packages (
                                name,
                                venue_name,
                                venue_location,
                                short_description,
                                description,
                                price,
                                guest_capacity,
                                decoration_type,
                                catering_menu,
                                features,
                                basic_music,
                                live_music,
                                main_image,
                                image_one,
                                image_two,
                                image_three,
                                image_four,
                                status,
                                created_by
                             ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, ?
                             )'
                        );

                    $saveStatement->execute([
                        $name,
                        $venueName,
                        $venueLocation,
                        $shortDescription,
                        $description,
                        (float) $priceClean,
                        $guestCapacity,
                        $decorationType,
                        $cateringMenu,
                        $normalisedFeatures !== ''
                            ? $normalisedFeatures
                            : null,
                        $basicMusic ? 1 : 0,
                        $liveMusic ? 1 : 0,
                        $imageValues[
                            'main_image'
                        ],
                        $imageValues[
                            'image_one'
                        ],
                        $imageValues[
                            'image_two'
                        ],
                        $imageValues[
                            'image_three'
                        ],
                        null,
                        $status,
                        $adminId,
                    ]);
                } else {
                    $saveStatement =
                        $connection->prepare(
                            'UPDATE packages
                             SET name = ?,
                                 venue_name = ?,
                                 venue_location = ?,
                                 short_description = ?,
                                 description = ?,
                                 price = ?,
                                 guest_capacity = ?,
                                 decoration_type = ?,
                                 catering_menu = ?,
                                 features = ?,
                                 basic_music = ?,
                                 live_music = ?,
                                 main_image = ?,
                                 image_one = ?,
                                 image_two = ?,
                                 image_three = ?,
                                 image_four = ?,
                                 status = ?
                             WHERE id = ?'
                        );

                    $saveStatement->execute([
                        $name,
                        $venueName,
                        $venueLocation,
                        $shortDescription,
                        $description,
                        (float) $priceClean,
                        $guestCapacity,
                        $decorationType,
                        $cateringMenu,
                        $normalisedFeatures !== ''
                            ? $normalisedFeatures
                            : null,
                        $basicMusic ? 1 : 0,
                        $liveMusic ? 1 : 0,
                        $imageValues[
                            'main_image'
                        ],
                        $imageValues[
                            'image_one'
                        ],
                        $imageValues[
                            'image_two'
                        ],
                        $imageValues[
                            'image_three'
                        ],
                        null,
                        $status,
                        $packageId,
                    ]);
                }

                $connection->commit();

                foreach (
                    array_unique(
                        $oldImagesToDelete
                    ) as $oldImage
                ) {
                    delete_package_image(
                        $oldImage
                    );
                }

                set_flash(
                    'success',
                    $action === 'create'
                        ? 'Package created successfully.'
                        : 'Package updated successfully.'
                );

                redirect($returnPath);
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                foreach (
                    $newUploadedImages
                    as $newImage
                ) {
                    delete_package_image(
                        $newImage
                    );
                }

                $errors[] = APP_DEBUG
                    ? 'Package could not be saved: '
                        . $exception->getMessage()
                    : 'Package could not be saved.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load package for editing
|--------------------------------------------------------------------------
*/

if (
    $editId > 0
    && $editingPackage === null
) {
    $editStatement =
        $connection->prepare(
            'SELECT *
             FROM packages
             WHERE id = ?
             LIMIT 1'
        );

    $editStatement->execute([
        $editId,
    ]);

    $editingPackage =
        $editStatement->fetch();

    if (!$editingPackage) {
        set_flash(
            'error',
            'The selected package was not found.'
        );

        redirect(
            admin_package_redirect_path(
                $returnTo,
                $filter
            )
        );
    }
}

if (
    $editingPackage
    && !$isPackageFormPost
) {
    $formValues = [
        'name' =>
            (string) $editingPackage['name'],
        'venue_name' =>
            (string) (
                $editingPackage[
                    'venue_name'
                ]
                ?? ''
            ),
        'venue_location' =>
            (string) (
                $editingPackage[
                    'venue_location'
                ]
                ?? ''
            ),
        'short_description' =>
            (string) (
                $editingPackage[
                    'short_description'
                ]
                ?? ''
            ),
        'description' =>
            (string) (
                $editingPackage['description']
                ?? ''
            ),
        'price' =>
            (string) $editingPackage['price'],
        'guest_capacity' =>
            (string) (
                $editingPackage[
                    'guest_capacity'
                ]
                ?? ''
            ),
        'decoration_type' =>
            (string) (
                $editingPackage[
                    'decoration_type'
                ]
                ?? ''
            ),
        'catering_menu' =>
            (string) (
                $editingPackage[
                    'catering_menu'
                ]
                ?? ''
            ),
        'features' =>
            (string) (
                $editingPackage['features']
                ?? ''
            ),
        'basic_music' =>
            (int) $editingPackage[
                'basic_music'
            ] === 1,
        'live_music' =>
            (int) $editingPackage[
                'live_music'
            ] === 1,
        'status' =>
            (string) $editingPackage[
                'status'
            ],
    ];
}

$openPackageModal =
    $showCreateModal
    || $editingPackage !== null
    || (
        $isPackageFormPost
        && $errors !== []
    );

/*
|--------------------------------------------------------------------------
| Load package cards
|--------------------------------------------------------------------------
*/

$packageQuery =
    'SELECT * FROM packages';

$packageParameters = [];

if ($filter === 'active') {
    $packageQuery .=
        ' WHERE status = ?';

    $packageParameters[] =
        'active';
} elseif ($filter === 'inactive') {
    $packageQuery .=
        ' WHERE status = ?';

    $packageParameters[] =
        'inactive';
} elseif ($filter === 'popular') {
    if ($popularPackageId > 0) {
        $packageQuery .=
            ' WHERE id = ?';

        $packageParameters[] =
            $popularPackageId;
    } else {
        $packageQuery .=
            ' WHERE 1 = 0';
    }
}

$packageQuery .=
    ' ORDER BY created_at DESC, id DESC';

if ($filter === 'latest') {
    $packageQuery .= ' LIMIT 3';
}

$packageStatement =
    $connection->prepare(
        $packageQuery
    );

$packageStatement->execute(
    $packageParameters
);

$packages =
    $packageStatement->fetchAll();

$closeModalPath =
    $returnTo === 'all'
        ? '/admin/all_packages.php'
        : admin_package_manage_path(
            $filter
        );

$addPackagePath =
    admin_package_manage_path(
        $filter,
        [
            'add' => '1',
            'return_to' => $returnTo,
        ]
    );

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
        Manage Packages | <?= e(APP_NAME) ?>
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
            url('/assets/css/package_management.css')
        ) ?>"
    >
</head>

<body
    class="admin-dashboard-page <?= $openPackageModal
        ? 'package-modal-lock'
        : '' ?>"
>

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
                alt="Admin profile"
            >

            <h2>
                <?= e(
                    (string) $admin['full_name']
                ) ?>
            </h2>

            <p>Admin</p>

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
                class="active"
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

    <main class="admin-main package-admin-main">

        <header class="admin-topbar">

            <div class="admin-topbar-left">

                <button
                    class="sidebar-toggle package-mobile-menu"
                    id="sidebarToggle"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="admin-welcome">

                    <h1>Manage Packages</h1>

                    <p>
                        Create and manage wedding-event
                        packages easily.
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
                    href="<?= e(
                        url('/admin/profile.php')
                    ) ?>"
                >
                    <img
                        class="topbar-profile-image"
                        src="<?= e($adminImage) ?>"
                        alt="Admin profile"
                    >
                </a>

            </div>

        </header>

        <?php if ($flash): ?>

            <div
                class="package-flash <?= $flash['type'] === 'success'
                    ? 'package-flash-success'
                    : 'package-flash-danger' ?>"
            >
                <?= e(
                    (string) $flash['message']
                ) ?>
            </div>

        <?php endif; ?>

        <section
            class="summary-cards package-summary-cards"
        >

            <a
                class="summary-card package-summary-card"
                href="<?= e(
                    url('/admin/all_packages.php')
                ) ?>"
            >

                <div class="summary-icon pink">
                    <i class="fa-solid fa-gift"></i>
                </div>

                <div>
                    <h4>Total Packages</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $totalPackages
                            )
                        ) ?>
                    </h2>

                    <p>Click to show all</p>
                </div>

            </a>

            <a
                class="summary-card package-summary-card <?= $filter === 'active'
                    ? 'selected'
                    : '' ?>"
                href="<?= e(
                    url(
                        admin_package_manage_path(
                            'active'
                        )
                    ) . '#packageList'
                ) ?>"
            >

                <div class="summary-icon purple">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    <h4>Active Packages</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $activePackages
                            )
                        ) ?>
                    </h2>

                    <p>Visible on website</p>
                </div>

            </a>

            <a
                class="summary-card package-summary-card <?= $filter === 'inactive'
                    ? 'selected'
                    : '' ?>"
                href="<?= e(
                    url(
                        admin_package_manage_path(
                            'inactive'
                        )
                    ) . '#packageList'
                ) ?>"
            >

                <div class="summary-icon orange">
                    <i class="fa-solid fa-circle-pause"></i>
                </div>

                <div>
                    <h4>Inactive Packages</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $inactivePackages
                            )
                        ) ?>
                    </h2>

                    <p>Hidden from website</p>
                </div>

            </a>

            <a
                class="summary-card package-summary-card <?= $filter === 'popular'
                    ? 'selected'
                    : '' ?>"
                href="<?= e(
                    url(
                        admin_package_manage_path(
                            'popular'
                        )
                    ) . '#packageList'
                ) ?>"
            >

                <div class="summary-icon blue">
                    <i class="fa-solid fa-star"></i>
                </div>

                <div class="package-popular-summary">

                    <h4>Popular Package</h4>

                    <h2>
                        <?= e(
                            $popularPackageName
                        ) ?>
                    </h2>

                    <p>Based on bookings</p>

                </div>

            </a>

        </section>

        <section
            class="package-section-box"
            id="packageList"
        >

            <div class="package-section-heading">

                <div>

                    <h2>
                        <?= e(
                            admin_package_list_heading(
                                $filter
                            )
                        ) ?>
                    </h2>

                    <p>
                        Main image, website status,
                        complete description and gallery images.
                    </p>

                </div>

                <div class="package-heading-actions">

                    <a
                        class="package-add-button"
                        id="openCreatePackage"
                        href="<?= e(
                            url($addPackagePath)
                        ) ?>"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Add New Package
                    </a>

                    <a
                        class="package-view-button"
                        href="<?= e(
                            url('/admin/all_packages.php')
                        ) ?>"
                    >
                        <i class="fa-solid fa-border-all"></i>
                        View All Packages
                    </a>

                </div>

            </div>

            <?php if ($filter !== 'latest'): ?>

                <div class="package-filter-row">

                    <span>
                        Showing:
                        <strong>
                            <?= e(
                                admin_package_list_heading(
                                    $filter
                                )
                            ) ?>
                        </strong>
                    </span>

                    <a
                        href="<?= e(
                            url('/admin/packages.php')
                        ) ?>#packageList"
                    >
                        Show Latest Packages
                    </a>

                </div>

            <?php endif; ?>

            <?php if ($packages === []): ?>

                <div class="package-empty-state">

                    <i class="fa-solid fa-gift"></i>

                    <h3>No packages found</h3>

                    <p>
                        <?= e(
                            admin_package_empty_message(
                                $filter
                            )
                        ) ?>
                    </p>

                </div>

            <?php else: ?>

                <div class="package-grid">

                    <?php foreach (
                        $packages as $package
                    ): ?>
                        <?php
                        $packageId =
                            (int) $package['id'];

                        $thumbnailPaths = [
                            $package['image_one']
                                ?? null,
                            $package['image_two']
                                ?? null,
                            $package['image_three']
                                ?? null,
                        ];

                        $editPath =
                            admin_package_manage_path(
                                $filter,
                                [
                                    'edit' =>
                                        (string) $packageId,
                                    'return_to' =>
                                        'manage',
                                ]
                            );

                        $status = (string) (
                            $package['status']
                            ?? 'inactive'
                        );
                        ?>

                        <article class="package-card">

                            <div
                                class="package-card-main-image-wrap"
                            >
                                <img
                                    class="package-card-main-image"
                                    id="packageMainImage<?= e(
                                        (string) $packageId
                                    ) ?>"
                                    src="<?= e(
                                        package_image_url(
                                            $package[
                                                'main_image'
                                            ]
                                            ?? null
                                        )
                                    ) ?>"
                                    alt="<?= e(
                                        (string) $package[
                                            'name'
                                        ]
                                    ) ?>"
                                >
                            </div>

                            <div
                                class="package-card-content"
                            >

                                <span
                                    class="package-website-status <?= e(
                                        $status
                                    ) ?>"
                                >
                                    <?= $status === 'active'
                                        ? 'Active On Website'
                                        : 'Inactive On Website' ?>
                                </span>

                                <h3>
                                    <?= e(
                                        (string) $package[
                                            'name'
                                        ]
                                    ) ?>
                                </h3>

                                <p
                                    class="package-card-description"
                                >
                                    <?= nl2br(
                                        e(
                                            package_card_description(
                                                $package
                                            )
                                        )
                                    ) ?>
                                </p>

                                <div
                                    class="package-thumbnail-row"
                                >

                                    <?php foreach (
                                        $thumbnailPaths
                                        as $index => $imagePath
                                    ): ?>

                                        <button
                                            class="package-thumbnail-button"
                                            type="button"
                                            data-package-main="packageMainImage<?= e(
                                                (string) $packageId
                                            ) ?>"
                                            data-package-image="<?= e(
                                                package_image_url(
                                                    $imagePath
                                                )
                                            ) ?>"
                                            aria-label="Show gallery image <?= e(
                                                (string) (
                                                    $index + 1
                                                )
                                            ) ?>"
                                        >
                                            <img
                                                src="<?= e(
                                                    package_image_url(
                                                        $imagePath
                                                    )
                                                ) ?>"
                                                alt="Package gallery image <?= e(
                                                    (string) (
                                                        $index + 1
                                                    )
                                                ) ?>"
                                            >
                                        </button>

                                    <?php endforeach; ?>

                                </div>

                                <div class="package-actions">

                                    <a
                                        class="package-edit-button"
                                        href="<?= e(
                                            url($editPath)
                                        ) ?>"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                        Edit
                                    </a>

                                    <form
                                        method="post"
                                        onsubmit="return confirm('Delete this package permanently?');"
                                    >
                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="package_id"
                                            value="<?= e(
                                                (string) $packageId
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="return_filter"
                                            value="<?= e(
                                                $filter
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="return_to"
                                            value="manage"
                                        >

                                        <button
                                            class="package-delete-button"
                                            type="submit"
                                        >
                                            <i class="fa-solid fa-trash"></i>
                                            Delete
                                        </button>

                                    </form>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <footer class="admin-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner.
            All rights reserved.
        </footer>

    </main>

    <div
        class="package-modal <?= $openPackageModal
            ? 'open'
            : '' ?>"
        id="packageFormModal"
        aria-hidden="<?= $openPackageModal
            ? 'false'
            : 'true' ?>"
    >

        <div
            class="package-modal-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="packageFormTitle"
        >

            <button
                class="package-modal-close"
                id="packageModalClose"
                type="button"
                aria-label="Close package form"
            >
                &times;
            </button>

            <div class="package-form-heading">

                <h2 id="packageFormTitle">
                    <?= $editingPackage
                        ? 'Edit Package'
                        : 'Create New Package' ?>
                </h2>

                <p>
                    <?= $editingPackage
                        ? 'Change package details, replace images or remove existing images.'
                        : 'Enter the package details and upload one main image with three gallery images.' ?>
                </p>

            </div>

            <?php if ($errors !== []): ?>

                <div
                    class="package-flash package-flash-danger package-modal-errors"
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
                id="packageEditorForm"
            >
                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editingPackage
                        ? 'update'
                        : 'create' ?>"
                >

                <input
                    type="hidden"
                    name="package_id"
                    value="<?= e(
                        (string) (
                            $editingPackage['id']
                            ?? 0
                        )
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="return_filter"
                    value="<?= e($filter) ?>"
                >

                <input
                    type="hidden"
                    name="return_to"
                    value="<?= e($returnTo) ?>"
                >

                <div class="package-form-grid">

                    <div class="package-input-box">

                        <label for="name">
                            Package Name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?= e(
                                $formValues['name']
                            ) ?>"
                            maxlength="150"
                            placeholder="Enter package name"
                            required
                        >

                    </div>

                    <div class="package-input-box">

                        <label for="price">
                            Package Price
                        </label>

                        <input
                            type="text"
                            id="price"
                            name="price"
                            value="<?= e(
                                $formValues['price']
                            ) ?>"
                            placeholder="Example: 150000"
                            required
                        >

                    </div>

                    <div class="package-input-box">

                        <label for="venue_name">
                            Venue Name
                        </label>

                        <input
                            type="text"
                            id="venue_name"
                            name="venue_name"
                            value="<?= e(
                                $formValues[
                                    'venue_name'
                                ]
                            ) ?>"
                            maxlength="150"
                            placeholder="Enter venue name"
                            required
                        >

                    </div>

                    <div class="package-input-box">

                        <label for="venue_location">
                            Venue Location
                        </label>

                        <input
                            type="text"
                            id="venue_location"
                            name="venue_location"
                            value="<?= e(
                                $formValues[
                                    'venue_location'
                                ]
                            ) ?>"
                            maxlength="255"
                            placeholder="Enter venue location"
                            required
                        >

                    </div>

                    <div class="package-input-box">

                        <label for="guest_capacity">
                            Total Guests
                        </label>

                        <input
                            type="number"
                            id="guest_capacity"
                            name="guest_capacity"
                            value="<?= e(
                                $formValues[
                                    'guest_capacity'
                                ]
                            ) ?>"
                            min="1"
                            placeholder="Guest capacity"
                            required
                        >

                    </div>

                    <div class="package-input-box">

                        <label for="decoration_type">
                            Decoration Type
                        </label>

                        <input
                            type="text"
                            id="decoration_type"
                            name="decoration_type"
                            value="<?= e(
                                $formValues[
                                    'decoration_type'
                                ]
                            ) ?>"
                            maxlength="150"
                            placeholder="Elegant floral decoration"
                            required
                        >

                    </div>

                    <div
                        class="package-input-box package-span-2"
                    >

                        <label for="short_description">
                            Short Description
                        </label>

                        <input
                            type="text"
                            id="short_description"
                            name="short_description"
                            value="<?= e(
                                $formValues[
                                    'short_description'
                                ]
                            ) ?>"
                            maxlength="255"
                            placeholder="A short package summary"
                            required
                        >

                    </div>

                    <div
                        class="package-input-box package-span-2"
                    >

                        <label>Music Options</label>

                        <div class="package-options">

                            <label>
                                <input
                                    type="checkbox"
                                    name="music_options[]"
                                    value="basic_music"
                                    <?= $formValues[
                                        'basic_music'
                                    ]
                                        ? 'checked'
                                        : '' ?>
                                >

                                <span>
                                    🎵 Basic Music
                                </span>
                            </label>

                            <label>
                                <input
                                    type="checkbox"
                                    name="music_options[]"
                                    value="live_music"
                                    <?= $formValues[
                                        'live_music'
                                    ]
                                        ? 'checked'
                                        : '' ?>
                                >

                                <span>
                                    🎶 Live Music
                                </span>
                            </label>

                        </div>

                    </div>

                    <div
                        class="package-input-box package-textarea-box"
                    >

                        <label for="catering_menu">
                            Catering Menu
                        </label>

                        <textarea
                            id="catering_menu"
                            name="catering_menu"
                            placeholder="Write catering menu details"
                            required
                        ><?= e(
                            $formValues[
                                'catering_menu'
                            ]
                        ) ?></textarea>

                    </div>

                    <div
                        class="package-input-box package-textarea-box"
                    >

                        <label for="features">
                            Additional Features
                        </label>

                        <textarea
                            id="features"
                            name="features"
                            placeholder="Enter one feature per line"
                        ><?= e(
                            $formValues['features']
                        ) ?></textarea>

                    </div>

                    <div
                        class="package-input-box package-textarea-box package-span-2"
                    >

                        <label for="description">
                            Complete Description
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            placeholder="Write complete package details"
                            required
                        ><?= e(
                            $formValues['description']
                        ) ?></textarea>

                    </div>

                    <div
                        class="package-input-box package-span-2"
                    >

                        <div
                            class="package-image-section-title"
                        >
                            <h3>Package Images</h3>

                            <p>
                                JPG, PNG or WEBP.
                                Maximum 5 MB for each image.
                            </p>
                        </div>

                        <div
                            class="package-image-input-grid"
                        >

                            <?php foreach (
                                package_image_fields()
                                as $fieldName => $fieldLabel
                            ): ?>
                                <?php
                                $hasCurrentImage =
                                    $editingPackage
                                    && !empty(
                                        $editingPackage[
                                            $fieldName
                                        ]
                                    );
                                ?>

                                <div
                                    class="package-image-input-box"
                                >

                                    <label
                                        for="<?= e(
                                            $fieldName
                                        ) ?>"
                                    >
                                        <?= e(
                                            $fieldLabel
                                        ) ?>
                                    </label>

                                    <input
                                        class="package-file-input"
                                        type="file"
                                        id="<?= e(
                                            $fieldName
                                        ) ?>"
                                        name="<?= e(
                                            $fieldName
                                        ) ?>"
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                        data-preview="preview_<?= e(
                                            $fieldName
                                        ) ?>"
                                        <?= $editingPackage
                                            ? ''
                                            : 'required' ?>
                                    >

                                    <div
                                        class="package-current-image <?= $hasCurrentImage
                                            ? ''
                                            : 'empty' ?>"
                                    >

                                        <img
                                            id="preview_<?= e(
                                                $fieldName
                                            ) ?>"
                                            src="<?= e(
                                                package_image_url(
                                                    $editingPackage[
                                                        $fieldName
                                                    ]
                                                    ?? null
                                                )
                                            ) ?>"
                                            alt="<?= e(
                                                $fieldLabel
                                            ) ?> preview"
                                        >

                                        <span>
                                            <?= $hasCurrentImage
                                                ? 'Current image'
                                                : 'No current image' ?>
                                        </span>

                                    </div>

                                    <?php if (
                                        $editingPackage
                                    ): ?>

                                        <label
                                            class="package-remove-image-option"
                                        >
                                            <input
                                                type="checkbox"
                                                name="remove_<?= e(
                                                    $fieldName
                                                ) ?>"
                                                value="1"
                                            >

                                            <span>
                                                Remove current image
                                            </span>
                                        </label>

                                    <?php endif; ?>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                    <div
                        class="package-input-box package-span-2"
                    >

                        <label>
                            Website Visibility
                        </label>

                        <div
                            class="package-options package-visibility-option"
                        >

                            <label>

                                <input
                                    type="checkbox"
                                    name="activate_on_website"
                                    value="1"
                                    <?= $formValues['status']
                                        === 'active'
                                        ? 'checked'
                                        : '' ?>
                                >

                                <span>
                                    Activate package on customer website
                                </span>

                            </label>

                        </div>

                    </div>

                </div>

                <div class="package-submit-row">

                    <button
                        class="package-submit-button"
                        type="submit"
                    >
                        <?= $editingPackage
                            ? 'Update Package'
                            : 'Create Package' ?>
                    </button>

                    <button
                        class="package-cancel-button"
                        id="packageModalCancel"
                        type="button"
                    >
                        Cancel
                    </button>

                </div>

            </form>

        </div>

    </div>

    <script>
        const adminSidebar =
            document.getElementById(
                "adminSidebar"
            );

        const sidebarOverlay =
            document.getElementById(
                "sidebarOverlay"
            );

        const sidebarToggle =
            document.getElementById(
                "sidebarToggle"
            );

        function closeSidebar() {
            adminSidebar?.classList.remove(
                "open"
            );

            sidebarOverlay?.classList.remove(
                "open"
            );
        }

        sidebarToggle?.addEventListener(
            "click",
            function () {
                adminSidebar?.classList.toggle(
                    "open"
                );

                sidebarOverlay?.classList.toggle(
                    "open"
                );
            }
        );

        sidebarOverlay?.addEventListener(
            "click",
            closeSidebar
        );

        const packageModal =
            document.getElementById(
                "packageFormModal"
            );

        const openCreatePackage =
            document.getElementById(
                "openCreatePackage"
            );

        const packageModalClose =
            document.getElementById(
                "packageModalClose"
            );

        const packageModalCancel =
            document.getElementById(
                "packageModalCancel"
            );

        const packageCloseUrl =
            <?= json_encode(
                url($closeModalPath),
                JSON_UNESCAPED_SLASHES
            ) ?>;

        function openPackageModal() {
            packageModal?.classList.add(
                "open"
            );

            packageModal?.setAttribute(
                "aria-hidden",
                "false"
            );

            document.body.classList.add(
                "package-modal-lock"
            );
        }

        function closePackageModal() {
            packageModal?.classList.remove(
                "open"
            );

            packageModal?.setAttribute(
                "aria-hidden",
                "true"
            );

            document.body.classList.remove(
                "package-modal-lock"
            );

            window.location.href =
                packageCloseUrl;
        }

        openCreatePackage?.addEventListener(
            "click",
            function (event) {
                event.preventDefault();
                openPackageModal();
            }
        );

        packageModalClose?.addEventListener(
            "click",
            closePackageModal
        );

        packageModalCancel?.addEventListener(
            "click",
            closePackageModal
        );

        packageModal?.addEventListener(
            "click",
            function (event) {
                if (
                    event.target
                    === packageModal
                ) {
                    closePackageModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (
                    event.key === "Escape"
                    && packageModal?.classList.contains(
                        "open"
                    )
                ) {
                    closePackageModal();
                }
            }
        );

        document
            .querySelectorAll(
                ".package-thumbnail-button"
            )
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        const mainImage =
                            document.getElementById(
                                button.dataset.packageMain
                            );

                        if (
                            !mainImage
                            || !button.dataset.packageImage
                        ) {
                            return;
                        }

                        mainImage.src =
                            button.dataset.packageImage;

                        const row = button.closest(
                            ".package-thumbnail-row"
                        );

                        row?.querySelectorAll(
                            ".package-thumbnail-button"
                        ).forEach(
                            function (thumbnail) {
                                thumbnail.classList.remove(
                                    "active"
                                );
                            }
                        );

                        button.classList.add(
                            "active"
                        );
                    }
                );
            });

        document
            .querySelectorAll(
                ".package-file-input"
            )
            .forEach(function (input) {
                input.addEventListener(
                    "change",
                    function () {
                        const file =
                            input.files
                            && input.files[0];

                        const preview =
                            document.getElementById(
                                input.dataset.preview
                            );

                        if (!file || !preview) {
                            return;
                        }

                        const reader =
                            new FileReader();

                        reader.addEventListener(
                            "load",
                            function () {
                                preview.src =
                                    reader.result;

                                preview
                                    .closest(
                                        ".package-current-image"
                                    )
                                    ?.classList.remove(
                                        "empty"
                                    );

                                const label =
                                    preview
                                        .closest(
                                            ".package-current-image"
                                        )
                                        ?.querySelector(
                                            "span"
                                        );

                                if (label) {
                                    label.textContent =
                                        "New selected image";
                                }
                            }
                        );

                        reader.readAsDataURL(
                            file
                        );
                    }
                );
            });
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>