<?php

declare(strict_types=1);

require_once __DIR__ . '/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/gallery_helpers.php';

/*
|--------------------------------------------------------------------------
| Validate the selected Gallery role
|--------------------------------------------------------------------------
*/

$allowedGalleryRoles = [
    'admin',
    'booking_manager',
    'event_manager',
];

$galleryPageRole = isset($galleryPageRole)
    ? trim((string) $galleryPageRole)
    : '';

if (
    !in_array(
        $galleryPageRole,
        $allowedGalleryRoles,
        true
    )
) {
    redirect('/auth/staff_login.php');
}

require_role($galleryPageRole);

$connection = db();
$currentUserId = (int) $_SESSION['user_id'];
$canManageGallery =
    $galleryPageRole === 'event_manager';

$errors = [];
$flash = get_flash();

/*
|--------------------------------------------------------------------------
| Role-specific page settings
|--------------------------------------------------------------------------
*/

$roleSettings = [
    'admin' => [
        'page_title' => 'View Gallery',
        'page_description' =>
            'View all wedding-event images uploaded by the Event Manager.',

        'profile_label' => 'Admin',

        'profile_path' =>
            '/admin/profile.php',

        'notifications_path' =>
            '/admin/notifications.php',

        'dashboard_path' =>
            '/admin/dashboard.php',
    ],

    'booking_manager' => [
        'page_title' => 'Wedding Gallery',
        'page_description' =>
            'View wedding-event images uploaded and managed by the Event Manager.',

        'profile_label' => 'Booking Manager',

        'profile_path' =>
            '/booking_manager/profile.php',

        'notifications_path' =>
            '/booking_manager/notifications.php',

        'dashboard_path' =>
            '/booking_manager/dashboard.php',
    ],

    'event_manager' => [
        'page_title' => 'Gallery Management',
        'page_description' =>
            'Add, update and manage wedding-event images displayed on the website.',

        'profile_label' => 'Event Manager',

        'profile_path' =>
            '/event_manager/profile.php',

        'notifications_path' =>
            '/event_manager/notifications.php',

        'dashboard_path' =>
            '/event_manager/dashboard.php',
    ],
];

$currentRoleSettings =
    $roleSettings[$galleryPageRole];

$mainGalleryPath =
    gallery_page_path_for_role(
        $galleryPageRole
    );

/*
|--------------------------------------------------------------------------
| Sidebar menu items
|--------------------------------------------------------------------------
*/

$sidebarMenus = [
    'admin' => [
        [
            'label' => 'Dashboard',
            'icon' => 'fa-solid fa-house',
            'path' => '/admin/dashboard.php',
        ],
        [
            'label' => 'Manage Bookings',
            'icon' => 'fa-solid fa-calendar-check',
            'path' => '/admin/bookings.php',
        ],
        [
            'label' => 'Manage Packages',
            'icon' => 'fa-solid fa-gift',
            'path' => '/admin/packages.php',
        ],
        [
            'label' => 'Manage Venues',
            'icon' => 'fa-solid fa-hotel',
            'path' => '/admin/venues.php',
        ],
        [
            'label' => 'Manage Services',
            'icon' => 'fa-solid fa-bell-concierge',
            'path' => '/admin/services.php',
        ],
        [
            'label' => 'View Gallery',
            'icon' => 'fa-solid fa-images',
            'path' => '/admin/gallery.php',
            'active' => true,
        ],
        [
            'label' => 'View Feedback',
            'icon' => 'fa-solid fa-comment-dots',
            'path' => '/admin/feedback.php',
        ],
        [
            'label' => 'Manage Staff',
            'icon' => 'fa-solid fa-users-gear',
            'path' => '/admin/staff.php',
        ],
        [
            'label' => 'Notifications',
            'icon' => 'fa-solid fa-bell',
            'path' => '/admin/notifications.php',
        ],
        [
            'label' => 'Manage Profile',
            'icon' => 'fa-solid fa-user',
            'path' => '/admin/profile.php',
        ],
    ],

    'booking_manager' => [
        [
            'label' => 'Dashboard',
            'icon' => 'fa-solid fa-house',
            'path' => '/booking_manager/dashboard.php',
        ],
        [
            'label' => 'Manage Bookings',
            'icon' => 'fa-solid fa-calendar-check',
            'path' => '/booking_manager/bookings.php',
        ],
        [
            'label' => 'Create Booking',
            'icon' => 'fa-solid fa-calendar-plus',
            'path' => '/booking_manager/booking.php',
        ],
        [
            'label' => 'View Services',
            'icon' => 'fa-solid fa-bell-concierge',
            'path' => '/booking_manager/services.php',
        ],
        [
            'label' => 'View Gallery',
            'icon' => 'fa-solid fa-images',
            'path' => '/booking_manager/gallery.php',
            'active' => true,
        ],
        [
            'label' => 'View Packages',
            'icon' => 'fa-solid fa-gift',
            'path' => '/booking_manager/packages.php',
        ],
        [
            'label' => 'View Venues',
            'icon' => 'fa-solid fa-hotel',
            'path' => '/booking_manager/venues.php',
        ],
        [
            'label' => 'Manage Profile',
            'icon' => 'fa-solid fa-user',
            'path' => '/booking_manager/profile.php',
        ],
        [
            'label' => 'View Notifications',
            'icon' => 'fa-solid fa-bell',
            'path' => '/booking_manager/notifications.php',
        ],
    ],

    'event_manager' => [
        [
            'label' => 'Dashboard',
            'icon' => 'fa-solid fa-house',
            'path' => '/event_manager/dashboard.php',
        ],
        [
            'label' => 'Assigned Tasks',
            'icon' => 'fa-solid fa-list-check',
            'path' => '/event_manager/assigned_tasks.php',
        ],
        [
            'label' => 'Notifications',
            'icon' => 'fa-solid fa-bell',
            'path' => '/event_manager/notifications.php',
        ],
        [
            'label' => 'Manage Profile',
            'icon' => 'fa-solid fa-user',
            'path' => '/event_manager/profile.php',
        ],
        [
            'label' => 'Gallery Management',
            'icon' => 'fa-solid fa-images',
            'path' => '/event_manager/gallery.php',
            'active' => true,
        ],
        [
            'label' => 'Feedback',
            'icon' => 'fa-solid fa-comment-dots',
            'path' => '/event_manager/feedback.php',
        ],
    ],
];

/*
|--------------------------------------------------------------------------
| Load current staff account
|--------------------------------------------------------------------------
*/

$userStatement = $connection->prepare(
    'SELECT
        full_name,
        email,
        profile_image
     FROM users
     WHERE id = ?
     AND role = ?
     LIMIT 1'
);

$userStatement->execute([
    $currentUserId,
    $galleryPageRole,
]);

$currentUser = $userStatement->fetch();

if (!$currentUser) {
    redirect('/auth/logout.php');
}

$currentUserImage =
    !empty($currentUser['profile_image'])
        ? url(
            '/'
            . ltrim(
                (string) $currentUser['profile_image'],
                '/'
            )
        )
        : url('/assets/icons/icon-192.png');

/*
|--------------------------------------------------------------------------
| Notification count
|--------------------------------------------------------------------------
*/

$notificationStatement =
    $connection->prepare(
        'SELECT COUNT(*)
         FROM notifications
         WHERE recipient_id = ?
         AND is_read = 0'
    );

$notificationStatement->execute([
    $currentUserId,
]);

$unreadNotifications =
    (int) $notificationStatement->fetchColumn();

/*
|--------------------------------------------------------------------------
| Page filter
|--------------------------------------------------------------------------
*/

$allowedFilters = [
    'latest',
    'all',
    'active',
    'inactive',
];

$filter = strtolower(
    trim(
        (string) (
            $_GET['filter']
            ?? 'latest'
        )
    )
);

if (
    !in_array(
        $filter,
        $allowedFilters,
        true
    )
) {
    $filter = 'latest';
}

$returnTo = strtolower(
    trim(
        (string) (
            $_GET['return_to']
            ?? 'main'
        )
    )
);

$returnTo = $returnTo === 'all'
    ? 'all'
    : 'main';

$editId = max(
    0,
    (int) ($_GET['edit'] ?? 0)
);

$showCreateModal =
    isset($_GET['add'])
    && $_GET['add'] === '1';

$editingGalleryItem = null;
$isGalleryFormPost = false;

$formValues = [
    'title' => '',
    'event_type' => '',
    'description' => '',
    'status' => 'active',
];

$removeMainImageSelected = false;
$removeSecondImageSelected = false;

/*
|--------------------------------------------------------------------------
| Local Gallery path helpers
|--------------------------------------------------------------------------
*/

function gallery_main_filter_path(
    string $basePath,
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

    if ($parameters === []) {
        return $basePath;
    }

    return $basePath
        . '?'
        . http_build_query($parameters);
}

function gallery_filter_heading(
    string $filter
): string {
    return match ($filter) {
        'all' =>
            'All Gallery Images',

        'active' =>
            'Active Gallery Images',

        'inactive' =>
            'Inactive Gallery Images',

        default =>
            'Latest Gallery Images',
    };
}

function gallery_filter_description(
    string $filter,
    bool $canManageGallery
): string {
    if ($filter === 'active') {
        return 'Images currently visible on the customer website.';
    }

    if ($filter === 'inactive') {
        return 'Images currently hidden from the customer website.';
    }

    if ($filter === 'all') {
        return $canManageGallery
            ? 'View, edit and manage every gallery record.'
            : 'View all wedding-event gallery records.';
    }

    return $canManageGallery
        ? 'Add, edit and manage recently uploaded gallery items.'
        : 'View the latest wedding-event gallery images.';
}

function gallery_redirect_after_action(
    string $returnTo,
    string $mainGalleryPath,
    string $filter
): string {
    if ($returnTo === 'all') {
        return '/gallery/all_gallery.php';
    }

    return gallery_main_filter_path(
        $mainGalleryPath,
        $filter
    );
}

/*
|--------------------------------------------------------------------------
| Process Event Manager Gallery actions
|--------------------------------------------------------------------------
*/

if (is_post()) {
    if (!$canManageGallery) {
        set_flash(
            'error',
            'Only the Event Manager can change Gallery records.'
        );

        redirect($mainGalleryPath);
    }

    $submittedToken = (string) (
        $_POST['csrf_token']
        ?? ''
    );

    $action = trim(
        (string) (
            $_POST['action']
            ?? ''
        )
    );

    $returnFilter = strtolower(
        trim(
            (string) (
                $_POST['return_filter']
                ?? 'latest'
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

    $returnTo = strtolower(
        trim(
            (string) (
                $_POST['return_to']
                ?? 'main'
            )
        )
    );

    $returnTo = $returnTo === 'all'
        ? 'all'
        : 'main';

    $returnPath =
        gallery_redirect_after_action(
            $returnTo,
            $mainGalleryPath,
            $returnFilter
        );

    if (!verify_csrf($submittedToken)) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

    /*
    |--------------------------------------------------------------------------
    | Delete complete Gallery record
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'delete'
        && $errors === []
    ) {
        $galleryId = max(
            0,
            (int) (
                $_POST['gallery_id']
                ?? 0
            )
        );

        $galleryStatement =
            $connection->prepare(
                'SELECT
                    id,
                    image,
                    image_two
                 FROM gallery
                 WHERE id = ?
                 LIMIT 1'
            );

        $galleryStatement->execute([
            $galleryId,
        ]);

        $galleryToDelete =
            $galleryStatement->fetch();

        if (!$galleryToDelete) {
            set_flash(
                'error',
                'The selected Gallery record was not found.'
            );

            redirect($returnPath);
        }

        try {
            $connection->beginTransaction();

            $deleteStatement =
                $connection->prepare(
                    'DELETE FROM gallery
                     WHERE id = ?'
                );

            $deleteStatement->execute([
                $galleryId,
            ]);

            $connection->commit();

            delete_gallery_image(
                $galleryToDelete['image']
                ?? null
            );

            delete_gallery_image(
                $galleryToDelete['image_two']
                ?? null
            );

            set_flash(
                'success',
                'Gallery record deleted successfully.'
            );
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            set_flash(
                'error',
                APP_DEBUG
                    ? 'Gallery deletion failed: '
                        . $exception->getMessage()
                    : 'The Gallery record could not be deleted.'
            );
        }

        redirect($returnPath);
    }

    /*
    |--------------------------------------------------------------------------
    | Create or update Gallery record
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $action,
            ['create', 'update'],
            true
        )
    ) {
        $isGalleryFormPost = true;

        $galleryId = max(
            0,
            (int) (
                $_POST['gallery_id']
                ?? 0
            )
        );

        $existingGalleryItem = null;

        if ($action === 'update') {
            $existingStatement =
                $connection->prepare(
                    'SELECT *
                     FROM gallery
                     WHERE id = ?
                     LIMIT 1'
                );

            $existingStatement->execute([
                $galleryId,
            ]);

            $existingGalleryItem =
                $existingStatement->fetch();

            if (!$existingGalleryItem) {
                $errors[] =
                    'The Gallery record being edited was not found.';
            } else {
                $editId = $galleryId;
                $editingGalleryItem =
                    $existingGalleryItem;
            }
        }

        $title = trim(
            (string) (
                $_POST['title']
                ?? ''
            )
        );

        $eventType = trim(
            (string) (
                $_POST['event_type']
                ?? ''
            )
        );

        $description = trim(
            (string) (
                $_POST['description']
                ?? ''
            )
        );

        $status =
            isset($_POST['active_on_website'])
                ? 'active'
                : 'inactive';

        $removeMainImageSelected =
            isset($_POST['remove_image'])
            && (string) $_POST['remove_image'] === '1';

        $removeSecondImageSelected =
            isset($_POST['remove_image_two'])
            && (string) $_POST['remove_image_two'] === '1';

        $formValues = [
            'title' => $title,
            'event_type' => $eventType,
            'description' => $description,
            'status' => $status,
        ];

        if (
            mb_strlen($title) < 3
            || mb_strlen($title) > 150
        ) {
            $errors[] =
                'Gallery title must contain between 3 and 150 characters.';
        }

        if (
            mb_strlen($eventType) < 3
            || mb_strlen($eventType) > 100
        ) {
            $errors[] =
                'Event type must contain between 3 and 100 characters.';
        }

        if (mb_strlen($description) > 1000) {
            $errors[] =
                'Description cannot exceed 1,000 characters.';
        }

        $currentMainImage =
            $existingGalleryItem['image']
            ?? null;

        $currentSecondImage =
            $existingGalleryItem['image_two']
            ?? null;

        $oldMainImage =
            $currentMainImage;

        $oldSecondImage =
            $currentSecondImage;

        $newMainImage = null;
        $newSecondImage = null;

        $mainImageFile =
            $_FILES['image']
            ?? [
                'error' => UPLOAD_ERR_NO_FILE,
            ];

        $secondImageFile =
            $_FILES['image_two']
            ?? [
                'error' => UPLOAD_ERR_NO_FILE,
            ];

        $hasNewMainImage =
            (int) (
                $mainImageFile['error']
                ?? UPLOAD_ERR_NO_FILE
            ) !== UPLOAD_ERR_NO_FILE;

        $hasNewSecondImage =
            (int) (
                $secondImageFile['error']
                ?? UPLOAD_ERR_NO_FILE
            ) !== UPLOAD_ERR_NO_FILE;

        if (
            $action === 'create'
            && !$hasNewMainImage
        ) {
            $errors[] =
                'Select the first Gallery image.';
        }

        if (
            $errors === []
            && $hasNewMainImage
        ) {
            try {
                $newMainImage =
                    upload_gallery_image(
                        $mainImageFile,
                        'gallery_main_'
                        . (
                            $galleryId > 0
                                ? $galleryId
                                : 'new'
                        )
                    );

                if ($newMainImage !== null) {
                    $currentMainImage =
                        $newMainImage;

                    /*
                     * A newly selected first image
                     * takes priority over removal.
                     */
                    $removeMainImageSelected = false;
                }
            } catch (Throwable $exception) {
                $errors[] =
                    $exception->getMessage();
            }
        }

        if (
            $errors === []
            && $hasNewSecondImage
        ) {
            try {
                $newSecondImage =
                    upload_gallery_image(
                        $secondImageFile,
                        'gallery_second_'
                        . (
                            $galleryId > 0
                                ? $galleryId
                                : 'new'
                        )
                    );

                if ($newSecondImage !== null) {
                    $currentSecondImage =
                        $newSecondImage;

                    /*
                     * A newly selected second image
                     * takes priority over removal.
                     */
                    $removeSecondImageSelected = false;
                }
            } catch (Throwable $exception) {
                $errors[] =
                    $exception->getMessage();
            }
        }

        if (
            $action === 'update'
            && $removeMainImageSelected
            && !$hasNewMainImage
        ) {
            $currentMainImage = null;
        }

        if (
            $action === 'update'
            && $removeSecondImageSelected
            && !$hasNewSecondImage
        ) {
            $currentSecondImage = null;
        }

        $currentMainImage =
            trim((string) $currentMainImage) !== ''
                ? (string) $currentMainImage
                : null;

        $currentSecondImage =
            trim((string) $currentSecondImage) !== ''
                ? (string) $currentSecondImage
                : null;

        /*
         * A Gallery record must always keep one image.
         * When the first image is removed while the second
         * image remains, promote the second image to first.
         */
        if (
            $currentMainImage === null
            && $currentSecondImage !== null
        ) {
            $currentMainImage =
                $currentSecondImage;

            $currentSecondImage = null;
        }

        if ($currentMainImage === null) {
            $errors[] =
                'At least one Gallery image must remain. Upload a replacement image before removing both current images.';
        }

        if ($errors === []) {
            try {
                $connection->beginTransaction();

                if ($action === 'create') {
                    $saveStatement =
                        $connection->prepare(
                            'INSERT INTO gallery (
                                title,
                                description,
                                event_type,
                                image,
                                image_two,
                                status,
                                created_by
                             ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?
                             )'
                        );

                    $saveStatement->execute([
                        $title,

                        $description !== ''
                            ? $description
                            : null,

                        $eventType,
                        $currentMainImage,
                        $currentSecondImage,
                        $status,
                        $currentUserId,
                    ]);
                } else {
                    $saveStatement =
                        $connection->prepare(
                            'UPDATE gallery
                             SET title = ?,
                                 description = ?,
                                 event_type = ?,
                                 image = ?,
                                 image_two = ?,
                                 status = ?
                             WHERE id = ?'
                        );

                    $saveStatement->execute([
                        $title,

                        $description !== ''
                            ? $description
                            : null,

                        $eventType,
                        $currentMainImage,
                        $currentSecondImage,
                        $status,
                        $galleryId,
                    ]);
                }

                $connection->commit();

                if ($action === 'update') {
                    $oldImagePaths = [];
                    $finalImagePaths = [];

                    foreach (
                        [
                            $oldMainImage,
                            $oldSecondImage,
                        ] as $oldImagePath
                    ) {
                        $oldImagePath = trim(
                            (string) $oldImagePath
                        );

                        if (
                            $oldImagePath !== ''
                            && !in_array(
                                $oldImagePath,
                                $oldImagePaths,
                                true
                            )
                        ) {
                            $oldImagePaths[] =
                                $oldImagePath;
                        }
                    }

                    foreach (
                        [
                            $currentMainImage,
                            $currentSecondImage,
                        ] as $finalImagePath
                    ) {
                        $finalImagePath = trim(
                            (string) $finalImagePath
                        );

                        if (
                            $finalImagePath !== ''
                            && !in_array(
                                $finalImagePath,
                                $finalImagePaths,
                                true
                            )
                        ) {
                            $finalImagePaths[] =
                                $finalImagePath;
                        }
                    }

                    foreach (
                        $oldImagePaths
                        as $oldImagePath
                    ) {
                        if (
                            !in_array(
                                $oldImagePath,
                                $finalImagePaths,
                                true
                            )
                        ) {
                            delete_gallery_image(
                                $oldImagePath
                            );
                        }
                    }
                }

                set_flash(
                    'success',
                    $action === 'create'
                        ? 'Gallery record added successfully.'
                        : 'Gallery record updated successfully.'
                );

                redirect($returnPath);
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                if ($newMainImage !== null) {
                    delete_gallery_image(
                        $newMainImage
                    );
                }

                if ($newSecondImage !== null) {
                    delete_gallery_image(
                        $newSecondImage
                    );
                }

                $errors[] = APP_DEBUG
                    ? 'Gallery record could not be saved: '
                        . $exception->getMessage()
                    : 'Gallery record could not be saved.';
            }
        } else {
            if ($newMainImage !== null) {
                delete_gallery_image(
                    $newMainImage
                );
            }

            if ($newSecondImage !== null) {
                delete_gallery_image(
                    $newSecondImage
                );
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load Gallery record for editing
|--------------------------------------------------------------------------
*/

if (
    $canManageGallery
    && $editId > 0
    && $editingGalleryItem === null
) {
    $editStatement =
        $connection->prepare(
            'SELECT *
             FROM gallery
             WHERE id = ?
             LIMIT 1'
        );

    $editStatement->execute([
        $editId,
    ]);

    $editingGalleryItem =
        $editStatement->fetch();

    if (!$editingGalleryItem) {
        set_flash(
            'error',
            'The selected Gallery record was not found.'
        );

        redirect($mainGalleryPath);
    }
}

if (
    $editingGalleryItem
    && !$isGalleryFormPost
) {
    $formValues = [
        'title' =>
            (string) (
                $editingGalleryItem['title']
                ?? ''
            ),

        'event_type' =>
            (string) (
                $editingGalleryItem['event_type']
                ?? ''
            ),

        'description' =>
            (string) (
                $editingGalleryItem['description']
                ?? ''
            ),

        'status' =>
            gallery_status_value(
                $editingGalleryItem['status']
                ?? 'inactive'
            ),
    ];
}

$showGalleryModal =
    $canManageGallery
    && (
        $showCreateModal
        || $editingGalleryItem !== null
        || (
            $isGalleryFormPost
            && $errors !== []
        )
    );

/*
|--------------------------------------------------------------------------
| Gallery summary
|--------------------------------------------------------------------------
*/

$summaryStatement =
    $connection->query(
        "SELECT
            COUNT(*) AS total_images,

            COALESCE(
                SUM(status = 'active'),
                0
            ) AS active_images,

            COALESCE(
                SUM(status = 'inactive'),
                0
            ) AS inactive_images

         FROM gallery"
    );

$summary =
    $summaryStatement->fetch();

$totalImages =
    (int) (
        $summary['total_images']
        ?? 0
    );

$activeImages =
    (int) (
        $summary['active_images']
        ?? 0
    );

$inactiveImages =
    (int) (
        $summary['inactive_images']
        ?? 0
    );

/*
|--------------------------------------------------------------------------
| Load Gallery records based on selected summary filter
|--------------------------------------------------------------------------
*/

$galleryQuery =
    'SELECT
        gallery.id,
        gallery.title,
        gallery.description,
        gallery.event_type,
        gallery.image,
        gallery.image_two,
        gallery.status,
        gallery.created_at,
        gallery.updated_at,
        users.full_name AS uploader_name

     FROM gallery

     LEFT JOIN users
        ON users.id = gallery.created_by';

$galleryParameters = [];

if ($filter === 'active') {
    $galleryQuery .=
        ' WHERE gallery.status = ?';

    $galleryParameters[] =
        'active';
} elseif ($filter === 'inactive') {
    $galleryQuery .=
        ' WHERE gallery.status = ?';

    $galleryParameters[] =
        'inactive';
}

$galleryQuery .=
    ' ORDER BY
        gallery.created_at DESC,
        gallery.id DESC';

if ($filter === 'latest') {
    $galleryQuery .=
        ' LIMIT 8';
}

$galleryStatement =
    $connection->prepare(
        $galleryQuery
    );

$galleryStatement->execute(
    $galleryParameters
);

$galleryItems =
    $galleryStatement->fetchAll();

$closeModalPath =
    $returnTo === 'all'
        ? '/gallery/all_gallery.php'
        : gallery_main_filter_path(
            $mainGalleryPath,
            $filter
        );

$addGalleryPath =
    gallery_main_filter_path(
        $mainGalleryPath,
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
        <?= e(
            $currentRoleSettings['page_title']
        ) ?>
        | <?= e(APP_NAME) ?>
    </title>

    <?php require __DIR__ . '/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('/assets/css/gallery_management.css')
        ) ?>"
    >
</head>

<body
    class="gallery-role-page gallery-role-<?= e(
        $galleryPageRole
    ) ?> <?= $showGalleryModal
        ? 'gallery-modal-lock'
        : '' ?>"
>

    <aside
        class="gallery-sidebar"
        id="gallerySidebar"
    >

        <div class="gallery-sidebar-logo">

            <h1>Wedding</h1>

            <p>Event Planner</p>

        </div>

        <div class="gallery-sidebar-profile">

            <img
                src="<?= e($currentUserImage) ?>"
                alt="<?= e(
                    $currentRoleSettings[
                        'profile_label'
                    ]
                ) ?> profile"
            >

            <h2>
                <?= e(
                    (string) $currentUser[
                        'full_name'
                    ]
                ) ?>
            </h2>

            <p>
                <?= e(
                    $currentRoleSettings[
                        'profile_label'
                    ]
                ) ?>
            </p>

            <div class="gallery-online-status">
                ● Online
            </div>

        </div>

        <nav class="gallery-sidebar-menu">

            <?php foreach (
                $sidebarMenus[$galleryPageRole]
                as $menuItem
            ): ?>

                <a
                    class="<?= !empty(
                        $menuItem['active']
                    )
                        ? 'active'
                        : '' ?>"
                    href="<?= e(
                        url(
                            $menuItem['path']
                        )
                    ) ?>"
                >
                    <i
                        class="<?= e(
                            $menuItem['icon']
                        ) ?>"
                    ></i>

                    <?= e(
                        $menuItem['label']
                    ) ?>
                </a>

            <?php endforeach; ?>

            <a
                class="gallery-logout-link"
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
        class="gallery-sidebar-overlay"
        id="gallerySidebarOverlay"
    ></div>

    <main class="gallery-main-content">

        <header class="gallery-topbar">

            <div class="gallery-topbar-left">

                <button
                    class="gallery-mobile-menu-button"
                    id="galleryMobileMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="gallery-page-heading">

                    <h1>
                        <?= e(
                            $currentRoleSettings[
                                'page_title'
                            ]
                        ) ?>
                    </h1>

                    <p>
                        <?= e(
                            $currentRoleSettings[
                                'page_description'
                            ]
                        ) ?>
                    </p>

                </div>

            </div>

            <div class="gallery-topbar-right">

                <div class="gallery-current-date">

                    <?= e(date('d F Y')) ?>

                    <br>

                    <?= e(date('l, h:i A')) ?>

                </div>

                <a
                    class="gallery-notification-link"
                    href="<?= e(
                        url(
                            $currentRoleSettings[
                                'notifications_path'
                            ]
                        )
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
                        url(
                            $currentRoleSettings[
                                'profile_path'
                            ]
                        )
                    ) ?>"
                >
                    <img
                        class="gallery-top-profile-image"
                        src="<?= e(
                            $currentUserImage
                        ) ?>"
                        alt="Profile"
                    >
                </a>

            </div>

        </header>

        <?php if ($flash): ?>

            <div
                class="gallery-alert <?= $flash['type'] === 'success'
                    ? 'gallery-alert-success'
                    : 'gallery-alert-danger' ?>"
            >
                <?= e(
                    (string) $flash[
                        'message'
                    ]
                ) ?>
            </div>

        <?php endif; ?>

        <?php if (
            $errors !== []
            && !$showGalleryModal
        ): ?>

            <div
                class="gallery-alert gallery-alert-danger"
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

        <section class="gallery-summary-grid">

            <a
                class="gallery-summary-card <?= $filter === 'all'
                    ? 'selected'
                    : '' ?>"
                href="<?= e(
                    url(
                        gallery_main_filter_path(
                            $mainGalleryPath,
                            'all'
                        )
                    )
                    . '#galleryList'
                ) ?>"
            >

                <div
                    class="gallery-summary-icon total"
                >
                    <i class="fa-solid fa-images"></i>
                </div>

                <div>

                    <h4>Total Images</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $totalImages
                            )
                        ) ?>
                    </h2>

                    <p>Click to show all</p>

                </div>

            </a>

            <a
                class="gallery-summary-card <?= $filter === 'active'
                    ? 'selected'
                    : '' ?>"
                href="<?= e(
                    url(
                        gallery_main_filter_path(
                            $mainGalleryPath,
                            'active'
                        )
                    )
                    . '#galleryList'
                ) ?>"
            >

                <div
                    class="gallery-summary-icon active"
                >
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>

                    <h4>Active Images</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $activeImages
                            )
                        ) ?>
                    </h2>

                    <p>Visible on website</p>

                </div>

            </a>

            <a
                class="gallery-summary-card <?= $filter === 'inactive'
                    ? 'selected'
                    : '' ?>"
                href="<?= e(
                    url(
                        gallery_main_filter_path(
                            $mainGalleryPath,
                            'inactive'
                        )
                    )
                    . '#galleryList'
                ) ?>"
            >

                <div
                    class="gallery-summary-icon inactive"
                >
                    <i class="fa-solid fa-eye-slash"></i>
                </div>

                <div>

                    <h4>Inactive Images</h4>

                    <h2>
                        <?= e(
                            number_format(
                                $inactiveImages
                            )
                        ) ?>
                    </h2>

                    <p>Hidden from website</p>

                </div>

            </a>

        </section>

        <section
            class="gallery-list-section"
            id="galleryList"
        >

            <div class="gallery-list-heading">

                <div>

                    <h2>
                        <?= e(
                            gallery_filter_heading(
                                $filter
                            )
                        ) ?>
                    </h2>

                    <p>
                        <?= e(
                            gallery_filter_description(
                                $filter,
                                $canManageGallery
                            )
                        ) ?>
                    </p>

                </div>

                <div class="gallery-heading-actions">

                    <?php if ($canManageGallery): ?>

                        <a
                            class="gallery-add-button"
                            id="openGalleryFormButton"
                            href="<?= e(
                                url($addGalleryPath)
                            ) ?>"
                        >
                            <i class="fa-solid fa-plus"></i>
                            Add New Image
                        </a>

                    <?php endif; ?>

                    <a
                        class="gallery-view-all-button"
                        href="<?= e(
                            url(
                                '/gallery/all_gallery.php'
                            )
                        ) ?>"
                    >
                        <i class="fa-solid fa-border-all"></i>
                        View All Images
                    </a>

                </div>

            </div>

            <?php if ($filter !== 'latest'): ?>

                <div class="gallery-current-filter">

                    <span>
                        Showing:
                        <strong>
                            <?= e(
                                gallery_filter_heading(
                                    $filter
                                )
                            ) ?>
                        </strong>
                    </span>

                    <a
                        href="<?= e(
                            url(
                                $mainGalleryPath
                            )
                        ) ?>#galleryList"
                    >
                        Show Latest Images
                    </a>

                </div>

            <?php endif; ?>

            <?php if ($galleryItems === []): ?>

                <div class="gallery-empty-state">

                    <i class="fa-regular fa-images"></i>

                    <h3>No Gallery images found</h3>

                    <p>
                        <?php if (
                            $canManageGallery
                            && $filter === 'latest'
                        ): ?>

                            Add the first Gallery image using
                            the Add New Image button.

                        <?php else: ?>

                            No images match the selected Gallery filter.

                        <?php endif; ?>
                    </p>

                </div>

            <?php else: ?>

                <div class="gallery-card-grid">

                    <?php foreach (
                        $galleryItems
                        as $galleryItem
                    ): ?>
                        <?php
                        $galleryId =
                            (int) $galleryItem['id'];

                        $status =
                            gallery_status_value(
                                $galleryItem['status']
                                ?? 'inactive'
                            );

                        $mainImageUrl =
                            gallery_image_url(
                                $galleryItem['image']
                                ?? null
                            );

                        $secondImagePath =
                            trim(
                                (string) (
                                    $galleryItem[
                                        'image_two'
                                    ]
                                    ?? ''
                                )
                            );

                        $secondImageUrl =
                            $secondImagePath !== ''
                                ? gallery_image_url(
                                    $secondImagePath
                                )
                                : '';

                        $galleryImagesForPreview = [
                            $mainImageUrl,
                        ];

                        if ($secondImageUrl !== '') {
                            $galleryImagesForPreview[] =
                                $secondImageUrl;
                        }

                        $editPath =
                            gallery_main_filter_path(
                                $mainGalleryPath,
                                $filter,
                                [
                                    'edit' =>
                                        (string) $galleryId,

                                    'return_to' =>
                                        'main',
                                ]
                            );
                        ?>

                        <article class="gallery-record-card">

                            <div class="gallery-record-image-box">

                                <button
                                    class="gallery-preview-button"
                                    type="button"
                                    data-gallery-images="<?= e(
                                        json_encode(
                                            $galleryImagesForPreview,
                                            JSON_UNESCAPED_SLASHES
                                        )
                                    ) ?>"
                                    data-gallery-title="<?= e(
                                        (string) (
                                            $galleryItem[
                                                'title'
                                            ]
                                            ?? ''
                                        )
                                    ) ?>"
                                    data-gallery-description="<?= e(
                                        (string) (
                                            $galleryItem[
                                                'description'
                                            ]
                                            ?? ''
                                        )
                                    ) ?>"
                                    aria-label="Preview Gallery images"
                                >

                                    <img
                                        src="<?= e(
                                            $mainImageUrl
                                        ) ?>"
                                        alt="<?= e(
                                            (string) (
                                                $galleryItem[
                                                    'title'
                                                ]
                                                ?? 'Gallery image'
                                            )
                                        ) ?>"
                                    >

                                </button>

                                <span
                                    class="gallery-record-status <?= e(
                                        $status
                                    ) ?>"
                                >
                                    <?= $status === 'active'
                                        ? 'Active'
                                        : 'Inactive' ?>
                                </span>

                                <span class="gallery-photo-count">

                                    <i class="fa-solid fa-images"></i>

                                    <?= e(
                                        (string) gallery_photo_count(
                                            $galleryItem
                                        )
                                    ) ?>

                                    Photo<?= gallery_photo_count(
                                        $galleryItem
                                    ) === 1
                                        ? ''
                                        : 's' ?>

                                </span>

                            </div>

                            <div class="gallery-record-body">

                                <h3>
                                    <?= e(
                                        (string) (
                                            $galleryItem[
                                                'title'
                                            ]
                                            ?? 'Untitled image'
                                        )
                                    ) ?>
                                </h3>

                                <?php if (
                                    trim(
                                        (string) (
                                            $galleryItem[
                                                'event_type'
                                            ]
                                            ?? ''
                                        )
                                    ) !== ''
                                ): ?>

                                    <div class="gallery-event-type">

                                        <i class="fa-solid fa-heart"></i>

                                        <?= e(
                                            (string) $galleryItem[
                                                'event_type'
                                            ]
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                                <p class="gallery-record-description">
                                    <?= e(
                                        trim(
                                            (string) (
                                                $galleryItem[
                                                    'description'
                                                ]
                                                ?? ''
                                            )
                                        ) !== ''
                                            ? (string) $galleryItem[
                                                'description'
                                            ]
                                            : 'No description has been added.'
                                    ) ?>
                                </p>

                                <div class="gallery-record-meta">

                                    <span>
                                        <i class="fa-regular fa-calendar"></i>

                                        <?= e(
                                            gallery_display_date(
                                                $galleryItem[
                                                    'created_at'
                                                ]
                                                ?? null
                                            )
                                        ) ?>
                                    </span>

                                    <span>
                                        <i class="fa-regular fa-user"></i>

                                        <?= e(
                                            trim(
                                                (string) (
                                                    $galleryItem[
                                                        'uploader_name'
                                                    ]
                                                    ?? ''
                                                )
                                            ) !== ''
                                                ? (string) $galleryItem[
                                                    'uploader_name'
                                                ]
                                                : 'Event Manager'
                                        ) ?>
                                    </span>

                                </div>

                                <?php if (
                                    $canManageGallery
                                ): ?>

                                    <div class="gallery-record-actions">

                                        <a
                                            class="gallery-edit-button"
                                            href="<?= e(
                                                url($editPath)
                                            ) ?>"
                                        >
                                            <i class="fa-solid fa-pen-to-square"></i>
                                            Edit
                                        </a>

                                        <form
                                            method="post"
                                            onsubmit="return confirm('Delete this complete Gallery record and its images?');"
                                        >
                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="gallery_id"
                                                value="<?= e(
                                                    (string) $galleryId
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
                                                value="main"
                                            >

                                            <button
                                                class="gallery-delete-button"
                                                type="submit"
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                                Delete
                                            </button>

                                        </form>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <footer class="gallery-page-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner.
            All rights reserved.
        </footer>

    </main>

    <?php if ($canManageGallery): ?>

        <div
            class="gallery-form-modal <?= $showGalleryModal
                ? 'open'
                : '' ?>"
            id="galleryFormModal"
            aria-hidden="<?= $showGalleryModal
                ? 'false'
                : 'true' ?>"
        >

            <div
                class="gallery-form-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="galleryFormTitle"
            >

                <button
                    class="gallery-form-close"
                    id="galleryFormClose"
                    type="button"
                    aria-label="Close Gallery form"
                >
                    &times;
                </button>

                <div class="gallery-form-heading">

                    <h2 id="galleryFormTitle">
                        <?= $editingGalleryItem
                            ? 'Edit Gallery Image'
                            : 'Add New Gallery Image' ?>
                    </h2>

                    <p>
                        <?= $editingGalleryItem
                            ? 'Update details, replace images or remove a current image.'
                            : 'Upload one required image and an optional second image.' ?>
                    </p>

                </div>

                <?php if ($errors !== []): ?>

                    <div
                        class="gallery-alert gallery-alert-danger gallery-form-errors"
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
                    id="galleryEditorForm"
                >
                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="<?= $editingGalleryItem
                            ? 'update'
                            : 'create' ?>"
                    >

                    <input
                        type="hidden"
                        name="gallery_id"
                        value="<?= e(
                            (string) (
                                $editingGalleryItem[
                                    'id'
                                ]
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

                    <div class="gallery-form-grid">

                        <div class="gallery-input-box">

                            <label for="galleryTitle">
                                Gallery Title
                            </label>

                            <input
                                type="text"
                                id="galleryTitle"
                                name="title"
                                value="<?= e(
                                    $formValues[
                                        'title'
                                    ]
                                ) ?>"
                                maxlength="150"
                                placeholder="Enter image title"
                                required
                            >

                        </div>

                        <div class="gallery-input-box">

                            <label for="galleryEventType">
                                Event Type
                            </label>

                            <input
                                type="text"
                                id="galleryEventType"
                                name="event_type"
                                value="<?= e(
                                    $formValues[
                                        'event_type'
                                    ]
                                ) ?>"
                                maxlength="100"
                                placeholder="Example: Nikkah"
                                required
                            >

                        </div>

                        <div
                            class="gallery-input-box gallery-span-2"
                        >

                            <label for="galleryDescription">
                                Description
                            </label>

                            <textarea
                                class="gallery-description-input"
                                id="galleryDescription"
                                name="description"
                                maxlength="1000"
                                placeholder="Write a short image description"
                            ><?= e(
                                $formValues[
                                    'description'
                                ]
                            ) ?></textarea>

                        </div>

                        <div class="gallery-image-input-box">

                            <label for="galleryMainImage">
                                <?= $editingGalleryItem
                                    ? 'Replace First Image'
                                    : 'First Image' ?>
                            </label>

                            <input
                                class="gallery-file-input"
                                type="file"
                                id="galleryMainImage"
                                name="image"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                data-preview="galleryMainPreview"
                                <?= $editingGalleryItem
                                    ? ''
                                    : 'required' ?>
                            >

                            <p class="gallery-file-help">
                                Required for a new record.
                                JPG, PNG or WEBP, maximum 5 MB.
                            </p>

                            <div
                                class="gallery-current-image"
                                id="galleryMainPreviewBox"
                            >

                                <img
                                    id="galleryMainPreview"
                                    src="<?= e(
                                        gallery_image_url(
                                            $editingGalleryItem[
                                                'image'
                                            ]
                                            ?? null
                                        )
                                    ) ?>"
                                    data-original-src="<?= e(
                                        gallery_image_url(
                                            $editingGalleryItem[
                                                'image'
                                            ]
                                            ?? null
                                        )
                                    ) ?>"
                                    alt="First Gallery image preview"
                                >

                                <span id="galleryMainPreviewLabel">
                                    <?= $editingGalleryItem
                                        ? 'Current first image'
                                        : 'First image preview' ?>
                                </span>

                            </div>

                            <?php if (
                                $editingGalleryItem
                                && !empty(
                                    $editingGalleryItem[
                                        'image'
                                    ]
                                )
                            ): ?>

                                <label
                                    class="gallery-remove-image-option"
                                    for="removeMainImageCheckbox"
                                >
                                    <input
                                        type="checkbox"
                                        id="removeMainImageCheckbox"
                                        name="remove_image"
                                        value="1"
                                        <?= $removeMainImageSelected
                                            ? 'checked'
                                            : '' ?>
                                    >

                                    <span>
                                        Remove current image
                                    </span>
                                </label>

                            <?php endif; ?>

                        </div>

                        <div class="gallery-image-input-box">

                            <label for="gallerySecondImage">
                                <?= $editingGalleryItem
                                    ? 'Replace Second Image'
                                    : 'Second Image' ?>
                            </label>

                            <input
                                class="gallery-file-input"
                                type="file"
                                id="gallerySecondImage"
                                name="image_two"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                data-preview="gallerySecondPreview"
                            >

                            <p class="gallery-file-help">
                                Optional. Add a second view
                                of the same event.
                            </p>

                            <div
                                class="gallery-current-image gallery-second-preview <?= empty(
                                    $editingGalleryItem[
                                        'image_two'
                                    ]
                                )
                                    ? 'empty'
                                    : '' ?>"
                                id="gallerySecondPreviewBox"
                            >

                                <img
                                    id="gallerySecondPreview"
                                    src="<?= e(
                                        gallery_image_url(
                                            $editingGalleryItem[
                                                'image_two'
                                            ]
                                            ?? null
                                        )
                                    ) ?>"
                                    data-original-src="<?= e(
                                        gallery_image_url(
                                            $editingGalleryItem[
                                                'image_two'
                                            ]
                                            ?? null
                                        )
                                    ) ?>"
                                    alt="Second Gallery image preview"
                                >

                                <span id="gallerySecondPreviewLabel">
                                    <?= !empty(
                                        $editingGalleryItem[
                                            'image_two'
                                        ]
                                    )
                                        ? 'Current second image'
                                        : 'No second image selected' ?>
                                </span>

                            </div>

                            <?php if (
                                $editingGalleryItem
                                && !empty(
                                    $editingGalleryItem[
                                        'image_two'
                                    ]
                                )
                            ): ?>

                                <label
                                    class="gallery-remove-image-option"
                                    for="removeSecondImageCheckbox"
                                >
                                    <input
                                        type="checkbox"
                                        id="removeSecondImageCheckbox"
                                        name="remove_image_two"
                                        value="1"
                                        <?= $removeSecondImageSelected
                                            ? 'checked'
                                            : '' ?>
                                    >

                                    <span>
                                        Remove current image
                                    </span>
                                </label>

                            <?php endif; ?>

                        </div>

                        <div
                            class="gallery-input-box gallery-span-2"
                        >

                            <label>
                                Website Visibility
                            </label>

                            <div class="gallery-visibility-option">

                                <label>

                                    <input
                                        type="checkbox"
                                        name="active_on_website"
                                        value="1"
                                        <?= $formValues[
                                            'status'
                                        ] === 'active'
                                            ? 'checked'
                                            : '' ?>
                                    >

                                    <span>
                                        Active on website
                                    </span>

                                </label>

                            </div>

                        </div>

                    </div>

                    <div class="gallery-form-actions">

                        <button
                            class="gallery-save-button"
                            type="submit"
                        >
                            <?= $editingGalleryItem
                                ? 'Update Gallery Image'
                                : 'Add Gallery Image' ?>
                        </button>

                        <button
                            class="gallery-cancel-button"
                            id="galleryFormCancel"
                            type="button"
                        >
                            Cancel
                        </button>

                    </div>

                </form>

            </div>

        </div>

    <?php endif; ?>

    <div
        class="gallery-preview-modal"
        id="galleryPreviewModal"
        aria-hidden="true"
    >

        <div class="gallery-preview-dialog">

            <button
                class="gallery-preview-close"
                id="galleryPreviewClose"
                type="button"
                aria-label="Close image preview"
            >
                &times;
            </button>

            <div class="gallery-preview-image-area">

                <button
                    class="gallery-preview-arrow gallery-preview-arrow-left"
                    id="galleryPreviewPrevious"
                    type="button"
                    aria-label="Previous image"
                >
                    <i class="fa-solid fa-chevron-left"></i>
                </button>

                <img
                    class="gallery-preview-image"
                    id="galleryPreviewImage"
                    src=""
                    alt="Gallery preview"
                >

                <button
                    class="gallery-preview-arrow gallery-preview-arrow-right"
                    id="galleryPreviewNext"
                    type="button"
                    aria-label="Next image"
                >
                    <i class="fa-solid fa-chevron-right"></i>
                </button>

                <div
                    class="gallery-preview-counter"
                    id="galleryPreviewCounter"
                ></div>

            </div>

            <div class="gallery-preview-information">

                <h3 id="galleryPreviewTitle"></h3>

                <p id="galleryPreviewDescription"></p>

            </div>

        </div>

    </div>

    <script>
        const gallerySidebar =
            document.getElementById(
                "gallerySidebar"
            );

        const gallerySidebarOverlay =
            document.getElementById(
                "gallerySidebarOverlay"
            );

        const galleryMobileMenuButton =
            document.getElementById(
                "galleryMobileMenuButton"
            );

        function closeGallerySidebar() {
            gallerySidebar?.classList.remove(
                "open"
            );

            gallerySidebarOverlay?.classList.remove(
                "open"
            );
        }

        galleryMobileMenuButton?.addEventListener(
            "click",
            function () {
                gallerySidebar?.classList.toggle(
                    "open"
                );

                gallerySidebarOverlay?.classList.toggle(
                    "open"
                );
            }
        );

        gallerySidebarOverlay?.addEventListener(
            "click",
            closeGallerySidebar
        );

        /*
        |--------------------------------------------------------------------------
        | Event Manager add/edit modal
        |--------------------------------------------------------------------------
        */

        const galleryFormModal =
            document.getElementById(
                "galleryFormModal"
            );

        const openGalleryFormButton =
            document.getElementById(
                "openGalleryFormButton"
            );

        const galleryFormClose =
            document.getElementById(
                "galleryFormClose"
            );

        const galleryFormCancel =
            document.getElementById(
                "galleryFormCancel"
            );

        const galleryCloseUrl =
            <?= json_encode(
                url($closeModalPath),
                JSON_UNESCAPED_SLASHES
            ) ?>;

        function openGalleryFormModal() {
            galleryFormModal?.classList.add(
                "open"
            );

            galleryFormModal?.setAttribute(
                "aria-hidden",
                "false"
            );

            document.body.classList.add(
                "gallery-modal-lock"
            );
        }

        function closeGalleryFormModal() {
            galleryFormModal?.classList.remove(
                "open"
            );

            galleryFormModal?.setAttribute(
                "aria-hidden",
                "true"
            );

            document.body.classList.remove(
                "gallery-modal-lock"
            );

            window.location.href =
                galleryCloseUrl;
        }

        openGalleryFormButton?.addEventListener(
            "click",
            function (event) {
                event.preventDefault();
                openGalleryFormModal();
            }
        );

        galleryFormClose?.addEventListener(
            "click",
            closeGalleryFormModal
        );

        galleryFormCancel?.addEventListener(
            "click",
            closeGalleryFormModal
        );

        galleryFormModal?.addEventListener(
            "click",
            function (event) {
                if (
                    event.target
                    === galleryFormModal
                ) {
                    closeGalleryFormModal();
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | New file previews
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                ".gallery-file-input"
            )
            .forEach(function (input) {
                input.addEventListener(
                    "change",
                    function () {
                        const selectedFile =
                            input.files
                            && input.files[0];

                        const previewImage =
                            document.getElementById(
                                input.dataset.preview
                            );

                        if (
                            !selectedFile
                            || !previewImage
                        ) {
                            return;
                        }

                        const fileReader =
                            new FileReader();

                        fileReader.addEventListener(
                            "load",
                            function () {
                                previewImage.src =
                                    fileReader.result;

                                const previewBox =
                                    previewImage.closest(
                                        ".gallery-current-image"
                                    );

                                previewBox?.classList.remove(
                                    "empty"
                                );

                                const previewLabel =
                                    previewBox?.querySelector(
                                        "span"
                                    );

                                if (previewLabel) {
                                    previewLabel.textContent =
                                        "New selected image";
                                }

                                const removalCheckboxId =
                                    input.id
                                    === "galleryMainImage"
                                        ? "removeMainImageCheckbox"
                                        : input.id
                                            === "gallerySecondImage"
                                            ? "removeSecondImageCheckbox"
                                            : "";

                                const removalCheckbox =
                                    removalCheckboxId !== ""
                                        ? document.getElementById(
                                            removalCheckboxId
                                        )
                                        : null;

                                if (removalCheckbox) {
                                    removalCheckbox.checked =
                                        false;
                                }

                                previewBox?.classList.remove(
                                    "marked-for-removal"
                                );
                            }
                        );

                        fileReader.readAsDataURL(
                            selectedFile
                        );
                    }
                );
            });

        /*
        |--------------------------------------------------------------------------
        | Remove current Gallery images
        |--------------------------------------------------------------------------
        */

        const galleryImageRemovalControls = [
            {
                checkbox:
                    document.getElementById(
                        "removeMainImageCheckbox"
                    ),

                fileInput:
                    document.getElementById(
                        "galleryMainImage"
                    ),

                previewBox:
                    document.getElementById(
                        "galleryMainPreviewBox"
                    ),

                previewImage:
                    document.getElementById(
                        "galleryMainPreview"
                    ),

                previewLabel:
                    document.getElementById(
                        "galleryMainPreviewLabel"
                    ),

                currentLabel:
                    "Current first image"
            },
            {
                checkbox:
                    document.getElementById(
                        "removeSecondImageCheckbox"
                    ),

                fileInput:
                    document.getElementById(
                        "gallerySecondImage"
                    ),

                previewBox:
                    document.getElementById(
                        "gallerySecondPreviewBox"
                    ),

                previewImage:
                    document.getElementById(
                        "gallerySecondPreview"
                    ),

                previewLabel:
                    document.getElementById(
                        "gallerySecondPreviewLabel"
                    ),

                currentLabel:
                    "Current second image"
            }
        ];

        galleryImageRemovalControls.forEach(
            function (control) {
                control.checkbox?.addEventListener(
                    "change",
                    function () {
                        if (
                            control.checkbox.checked
                        ) {
                            if (control.fileInput) {
                                control.fileInput.value =
                                    "";
                            }

                            if (
                                control.previewImage
                                && control.previewImage.dataset.originalSrc
                            ) {
                                control.previewImage.src =
                                    control.previewImage.dataset.originalSrc;
                            }

                            control.previewBox?.classList.add(
                                "marked-for-removal"
                            );

                            if (control.previewLabel) {
                                control.previewLabel.textContent =
                                    "This image will be removed after update";
                            }
                        } else {
                            control.previewBox?.classList.remove(
                                "marked-for-removal"
                            );

                            if (
                                control.previewImage
                                && control.previewImage.dataset.originalSrc
                            ) {
                                control.previewImage.src =
                                    control.previewImage.dataset.originalSrc;
                            }

                            if (control.previewLabel) {
                                control.previewLabel.textContent =
                                    control.currentLabel;
                            }
                        }
                    }
                );

                if (
                    control.checkbox?.checked
                ) {
                    control.previewBox?.classList.add(
                        "marked-for-removal"
                    );

                    if (control.previewLabel) {
                        control.previewLabel.textContent =
                            "This image will be removed after update";
                    }
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Gallery image preview modal
        |--------------------------------------------------------------------------
        */

        const galleryPreviewModal =
            document.getElementById(
                "galleryPreviewModal"
            );

        const galleryPreviewImage =
            document.getElementById(
                "galleryPreviewImage"
            );

        const galleryPreviewTitle =
            document.getElementById(
                "galleryPreviewTitle"
            );

        const galleryPreviewDescription =
            document.getElementById(
                "galleryPreviewDescription"
            );

        const galleryPreviewCounter =
            document.getElementById(
                "galleryPreviewCounter"
            );

        const galleryPreviewPrevious =
            document.getElementById(
                "galleryPreviewPrevious"
            );

        const galleryPreviewNext =
            document.getElementById(
                "galleryPreviewNext"
            );

        const galleryPreviewClose =
            document.getElementById(
                "galleryPreviewClose"
            );

        let previewImages = [];
        let previewImageIndex = 0;

        function updateGalleryPreview() {
            if (
                previewImages.length === 0
                || !galleryPreviewImage
            ) {
                return;
            }

            galleryPreviewImage.src =
                previewImages[
                    previewImageIndex
                ];

            if (galleryPreviewCounter) {
                galleryPreviewCounter.textContent =
                    (previewImageIndex + 1)
                    + " / "
                    + previewImages.length;
            }

            const hasMultipleImages =
                previewImages.length > 1;

            if (galleryPreviewPrevious) {
                galleryPreviewPrevious.hidden =
                    !hasMultipleImages;
            }

            if (galleryPreviewNext) {
                galleryPreviewNext.hidden =
                    !hasMultipleImages;
            }

            if (galleryPreviewCounter) {
                galleryPreviewCounter.hidden =
                    !hasMultipleImages;
            }
        }

        function openGalleryPreview(button) {
            try {
                previewImages =
                    JSON.parse(
                        button.dataset.galleryImages
                        || "[]"
                    );
            } catch (error) {
                previewImages = [];
            }

            previewImages =
                previewImages.filter(
                    function (imageUrl) {
                        return typeof imageUrl
                            === "string"
                            && imageUrl !== "";
                    }
                );

            if (previewImages.length === 0) {
                return;
            }

            previewImageIndex = 0;

            if (galleryPreviewTitle) {
                galleryPreviewTitle.textContent =
                    button.dataset.galleryTitle
                    || "Gallery Image";
            }

            if (galleryPreviewDescription) {
                galleryPreviewDescription.textContent =
                    button.dataset.galleryDescription
                    || "No description has been added.";
            }

            updateGalleryPreview();

            galleryPreviewModal?.classList.add(
                "open"
            );

            galleryPreviewModal?.setAttribute(
                "aria-hidden",
                "false"
            );

            document.body.classList.add(
                "gallery-preview-lock"
            );
        }

        function closeGalleryPreview() {
            galleryPreviewModal?.classList.remove(
                "open"
            );

            galleryPreviewModal?.setAttribute(
                "aria-hidden",
                "true"
            );

            document.body.classList.remove(
                "gallery-preview-lock"
            );

            if (galleryPreviewImage) {
                galleryPreviewImage.src =
                    "";
            }

            previewImages = [];
            previewImageIndex = 0;
        }

        document
            .querySelectorAll(
                ".gallery-preview-button"
            )
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        openGalleryPreview(
                            button
                        );
                    }
                );
            });

        galleryPreviewPrevious?.addEventListener(
            "click",
            function () {
                if (
                    previewImages.length < 2
                ) {
                    return;
                }

                previewImageIndex =
                    (
                        previewImageIndex
                        - 1
                        + previewImages.length
                    )
                    % previewImages.length;

                updateGalleryPreview();
            }
        );

        galleryPreviewNext?.addEventListener(
            "click",
            function () {
                if (
                    previewImages.length < 2
                ) {
                    return;
                }

                previewImageIndex =
                    (
                        previewImageIndex
                        + 1
                    )
                    % previewImages.length;

                updateGalleryPreview();
            }
        );

        galleryPreviewClose?.addEventListener(
            "click",
            closeGalleryPreview
        );

        galleryPreviewModal?.addEventListener(
            "click",
            function (event) {
                if (
                    event.target
                    === galleryPreviewModal
                ) {
                    closeGalleryPreview();
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Escape key closes open modals
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            "keydown",
            function (event) {
                if (event.key !== "Escape") {
                    return;
                }

                if (
                    galleryPreviewModal?.classList.contains(
                        "open"
                    )
                ) {
                    closeGalleryPreview();
                    return;
                }

                if (
                    galleryFormModal?.classList.contains(
                        "open"
                    )
                ) {
                    closeGalleryFormModal();
                }
            }
        );
    </script>

    <?php require __DIR__ . '/pwa_scripts.php'; ?>

</body>
</html>