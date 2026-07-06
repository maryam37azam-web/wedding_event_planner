<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');

$connection = db();
$adminId = (int) $_SESSION['user_id'];

$errors = [];
$flash = get_flash();

$allowedTypes = [
    'catering',
    'music',
];

$editId = max(
    0,
    (int) ($_GET['edit'] ?? 0)
);

$editingService = null;
$activePanel = 'catering';

$formValues = [
    'service_type' => 'catering',
    'name' => '',
    'category' => '',
    'description' => '',
    'price' => '',
    'status' => 'active',
];

/**
 * Return the correct page section.
 */
function service_panel_anchor(
    string $type
): string {
    return $type === 'music'
        ? '#music-panel'
        : '#catering-panel';
}

/**
 * Format service price.
 */
function service_price_label(
    float $price
): string {
    $decimalPlaces =
        floor($price) === $price
            ? 0
            : 2;

    return 'Rs. ' . number_format(
        $price,
        $decimalPlaces
    );
}

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

$adminImage = !empty(
    $admin['profile_image']
)
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
| Unread notifications
|--------------------------------------------------------------------------
*/

$unreadStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE recipient_id = ?
     AND is_read = 0'
);

$unreadStatement->execute([
    $adminId,
]);

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
        $_POST['csrf_token']
        ?? ''
    );

    $action = (string) (
        $_POST['action']
        ?? ''
    );

    $postedType = strtolower(
        trim(
            (string) (
                $_POST['service_type']
                ?? 'catering'
            )
        )
    );

    if (
        in_array(
            $postedType,
            $allowedTypes,
            true
        )
    ) {
        $activePanel = $postedType;
    }

    if (
        !verify_csrf(
            $submittedToken
        )
    ) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

    /*
    |--------------------------------------------------------------------------
    | Delete service
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'delete'
        && $errors === []
    ) {
        $serviceId = (int) (
            $_POST['service_id']
            ?? 0
        );

        $serviceStatement =
            $connection->prepare(
                'SELECT
                    id,
                    name,
                    service_type
                 FROM services
                 WHERE id = ?
                 LIMIT 1'
            );

        $serviceStatement->execute([
            $serviceId,
        ]);

        $serviceToDelete =
            $serviceStatement->fetch();

        if (!$serviceToDelete) {
            set_flash(
                'error',
                'The selected service was not found.'
            );

            redirect(
                '/admin/services.php'
            );
        }

        $serviceType = (string) (
            $serviceToDelete[
                'service_type'
            ]
            ?? 'catering'
        );

        $usageStatement =
            $connection->prepare(
                'SELECT COUNT(*)
                 FROM booking_services
                 WHERE service_id = ?'
            );

        $usageStatement->execute([
            $serviceId,
        ]);

        $usageCount = (int) (
            $usageStatement
                ->fetchColumn()
        );

        if ($usageCount > 0) {
            set_flash(
                'error',
                'This item is already connected to a booking. Turn off Active on Customer Website instead of deleting it.'
            );

            redirect(
                '/admin/services.php'
                . service_panel_anchor(
                    $serviceType
                )
            );
        }

        try {
            $deleteStatement =
                $connection->prepare(
                    'DELETE FROM services
                     WHERE id = ?'
                );

            $deleteStatement->execute([
                $serviceId,
            ]);

            set_flash(
                'success',
                $serviceType === 'music'
                    ? 'Music service deleted successfully.'
                    : 'Catering item deleted successfully.'
            );
        } catch (Throwable $exception) {
            set_flash(
                'error',
                APP_DEBUG
                    ? 'Deletion failed: '
                        . $exception
                            ->getMessage()
                    : 'The selected item could not be deleted.'
            );
        }

        redirect(
            '/admin/services.php'
            . service_panel_anchor(
                $serviceType
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Activate or hide on customer website
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'toggle_status'
        && $errors === []
    ) {
        $serviceId = (int) (
            $_POST['service_id']
            ?? 0
        );

        $statusStatement =
            $connection->prepare(
                'SELECT
                    status,
                    service_type
                 FROM services
                 WHERE id = ?
                 LIMIT 1'
            );

        $statusStatement->execute([
            $serviceId,
        ]);

        $serviceStatus =
            $statusStatement->fetch();

        if (!$serviceStatus) {
            set_flash(
                'error',
                'The selected service was not found.'
            );

            redirect(
                '/admin/services.php'
            );
        }

        $serviceType = (string) (
            $serviceStatus[
                'service_type'
            ]
            ?? 'catering'
        );

        $newStatus =
            $serviceStatus['status']
            === 'active'
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
                ? 'The item is now active on the customer website.'
                : 'The item has been hidden from the customer website.'
        );

        redirect(
            '/admin/services.php'
            . service_panel_anchor(
                $serviceType
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create or update service
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $action,
            [
                'create',
                'update',
            ],
            true
        )
    ) {
        $serviceId = (int) (
            $_POST['service_id']
            ?? 0
        );

        $serviceType = strtolower(
            trim(
                (string) (
                    $_POST['service_type']
                    ?? ''
                )
            )
        );

        $name = trim(
            (string) (
                $_POST['name']
                ?? ''
            )
        );

        $category = trim(
            (string) (
                $_POST['category']
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
            (string) (
                $_POST['price']
                ?? ''
            )
        );

        $priceClean = preg_replace(
            '/[^0-9.]/',
            '',
            $priceInput
        );

        $status =
            isset(
                $_POST[
                    'active_on_website'
                ]
            )
                ? 'active'
                : 'inactive';

        if (
            in_array(
                $serviceType,
                $allowedTypes,
                true
            )
        ) {
            $activePanel =
                $serviceType;
        }

        if (
            $serviceType
            === 'music'
        ) {
            $category =
                'Music Service';
        }

        $formValues = [
            'service_type' =>
                $serviceType,

            'name' =>
                $name,

            'category' =>
                $category,

            'description' =>
                $description,

            'price' =>
                $priceInput,

            'status' =>
                $status,
        ];

        if (
            !in_array(
                $serviceType,
                $allowedTypes,
                true
            )
        ) {
            $errors[] =
                'Choose a valid service section.';
        }

        if (
            mb_strlen($name) < 2
            || mb_strlen($name) > 150
        ) {
            $errors[] =
                $serviceType === 'music'
                    ? 'Music service name must contain between 2 and 150 characters.'
                    : 'Catering item name must contain between 2 and 150 characters.';
        }

        if (
            $serviceType
            === 'catering'
            && (
                mb_strlen(
                    $category
                ) < 2
                || mb_strlen(
                    $category
                ) > 100
            )
        ) {
            $errors[] =
                'Catering category must contain between 2 and 100 characters.';
        }

        if (
            $serviceType
            === 'music'
            && (
                mb_strlen(
                    $description
                ) < 5
                || mb_strlen(
                    $description
                ) > 1000
            )
        ) {
            $errors[] =
                'Music service details must contain between 5 and 1,000 characters.';
        }

        if (
            $description !== ''
            && mb_strlen(
                $description
            ) > 1000
        ) {
            $errors[] =
                'Description cannot contain more than 1,000 characters.';
        }

        if (
            $priceClean === null
            || $priceClean === ''
            || !is_numeric(
                $priceClean
            )
            || (float) $priceClean < 0
        ) {
            $errors[] =
                $serviceType === 'music'
                    ? 'Enter a valid fixed music price.'
                    : 'Enter a valid per-head catering price.';
        }

        /*
         * Validate record being edited.
         */
        if (
            $action === 'update'
            && $errors === []
        ) {
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
                $existingStatement
                    ->fetch();

            if (!$existingService) {
                $errors[] =
                    'The item being edited was not found.';
            } elseif (
                (string) $existingService[
                    'service_type'
                ]
                !== $serviceType
            ) {
                $errors[] =
                    'The service type cannot be changed while editing.';
            } else {
                $editId =
                    $serviceId;

                $editingService =
                    $existingService;
            }
        }

        /*
         * Name must be unique inside its own section.
         */
        if ($errors === []) {
            $nameCheckStatement =
                $connection->prepare(
                    'SELECT id
                     FROM services
                     WHERE service_type = ?
                     AND LOWER(name) = LOWER(?)
                     AND id <> ?
                     LIMIT 1'
                );

            $nameCheckStatement->execute([
                $serviceType,
                $name,
                $serviceId,
            ]);

            if (
                $nameCheckStatement
                    ->fetch()
            ) {
                $errors[] =
                    $serviceType === 'music'
                        ? 'Another music service already uses this name.'
                        : 'Another catering item already uses this name.';
            }
        }

        /*
         * Save item.
         */
        if ($errors === []) {
            try {
                if (
                    $action
                    === 'create'
                ) {
                    $saveStatement =
                        $connection->prepare(
                            'INSERT INTO services (
                                service_type,
                                name,
                                category,
                                description,
                                price,
                                status,
                                created_by
                             ) VALUES (
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?
                             )'
                        );

                    $saveStatement->execute([
                        $serviceType,
                        $name,

                        $category !== ''
                            ? $category
                            : null,

                        $description !== ''
                            ? $description
                            : null,

                        (float) $priceClean,
                        $status,
                        $adminId,
                    ]);
                } else {
                    $saveStatement =
                        $connection->prepare(
                            'UPDATE services
                             SET name = ?,
                                 category = ?,
                                 description = ?,
                                 price = ?,
                                 status = ?
                             WHERE id = ?
                             AND service_type = ?'
                        );

                    $saveStatement->execute([
                        $name,

                        $category !== ''
                            ? $category
                            : null,

                        $description !== ''
                            ? $description
                            : null,

                        (float) $priceClean,
                        $status,
                        $serviceId,
                        $serviceType,
                    ]);
                }

                set_flash(
                    'success',
                    $action === 'create'
                        ? (
                            $serviceType
                            === 'music'
                                ? 'Music service added successfully.'
                                : 'Catering item added successfully.'
                        )
                        : (
                            $serviceType
                            === 'music'
                                ? 'Music service updated successfully.'
                                : 'Catering item updated successfully.'
                        )
                );

                redirect(
                    '/admin/services.php'
                    . service_panel_anchor(
                        $serviceType
                    )
                );
            } catch (Throwable $exception) {
                $errors[] =
                    APP_DEBUG
                        ? 'The item could not be saved: '
                            . $exception
                                ->getMessage()
                        : 'The item could not be saved.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load item for editing
|--------------------------------------------------------------------------
*/

if (
    $editId > 0
    && $editingService === null
) {
    $editStatement =
        $connection->prepare(
            "SELECT *
             FROM services
             WHERE id = ?
             AND service_type
                IN (
                    'catering',
                    'music'
                )
             LIMIT 1"
        );

    $editStatement->execute([
        $editId,
    ]);

    $editingService =
        $editStatement->fetch();

    if (!$editingService) {
        set_flash(
            'error',
            'The selected service was not found.'
        );

        redirect(
            '/admin/services.php'
        );
    }

    $activePanel = (string) (
        $editingService[
            'service_type'
        ]
        ?? 'catering'
    );
}

$isServiceFormPost =
    is_post()
    && in_array(
        (string) (
            $_POST['action']
            ?? ''
        ),
        [
            'create',
            'update',
        ],
        true
    );

if (
    $editingService
    && !$isServiceFormPost
) {
    $formValues = [
        'service_type' =>
            (string) $editingService[
                'service_type'
            ],

        'name' =>
            (string) $editingService[
                'name'
            ],

        'category' =>
            (string) (
                $editingService[
                    'category'
                ]
                ?? ''
            ),

        'description' =>
            (string) (
                $editingService[
                    'description'
                ]
                ?? ''
            ),

        'price' =>
            (string) $editingService[
                'price'
            ],

        'status' =>
            (string) $editingService[
                'status'
            ],
    ];
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$statistics = $connection
    ->query(
        "SELECT
            SUM(
                service_type
                = 'catering'
            ) AS catering_total,

            SUM(
                service_type
                = 'catering'
                AND status
                = 'active'
            ) AS catering_active,

            SUM(
                service_type
                = 'music'
            ) AS music_total,

            SUM(
                service_type
                = 'music'
                AND status
                = 'active'
            ) AS music_active
         FROM services"
    )
    ->fetch();

$cateringTotal = (int) (
    $statistics[
        'catering_total'
    ]
    ?? 0
);

$cateringActive = (int) (
    $statistics[
        'catering_active'
    ]
    ?? 0
);

$musicTotal = (int) (
    $statistics[
        'music_total'
    ]
    ?? 0
);

$musicActive = (int) (
    $statistics[
        'music_active'
    ]
    ?? 0
);

/*
|--------------------------------------------------------------------------
| Load catering and music records
|--------------------------------------------------------------------------
*/

$serviceRows = $connection
    ->query(
        "SELECT
            services.*,

            COALESCE(
                SUM(
                    booking_services
                        .quantity
                ),
                0
            ) AS booking_count

         FROM services

         LEFT JOIN booking_services
            ON booking_services
                .service_id
            = services.id

         WHERE services
            .service_type
            IN (
                'catering',
                'music'
            )

         GROUP BY services.id

         ORDER BY
            services
                .service_type ASC,
            services
                .created_at DESC"
    )
    ->fetchAll();

$cateringServices = [];
$musicServices = [];

foreach (
    $serviceRows
    as $serviceRow
) {
    if (
        (
            $serviceRow[
                'service_type'
            ]
            ?? ''
        )
        === 'music'
    ) {
        $musicServices[] =
            $serviceRow;
    } else {
        $cateringServices[] =
            $serviceRow;
    }
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
            url(
                '/assets/css/admin_dashboard.css'
            )
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/service_management.css'
            )
        ) ?>"
    >
</head>

<body
    class="admin-dashboard-page service-management-page"
    data-initial-service-panel="<?= e(
        $activePanel
    ) ?>"
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
                alt="Administrator profile"
            >

            <h2>
                <?= e(
                    (string) $admin[
                        'full_name'
                    ]
                ) ?>
            </h2>

            <p>
                System Administrator
            </p>

            <div class="online-status">
                ● Online
            </div>

        </div>

        <nav class="admin-menu">

            <a
                href="<?= e(
                    url(
                        '/admin/dashboard.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a
                href="<?= e(
                    url(
                        '/admin/bookings.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-calendar-check"></i>
                Manage Bookings
            </a>

            <a
                href="<?= e(
                    url(
                        '/admin/packages.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-gift"></i>
                Manage Packages
            </a>

            <a
                href="<?= e(
                    url(
                        '/admin/venues.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-hotel"></i>
                Manage Venues
            </a>

            <a
                class="active"
                href="<?= e(
                    url(
                        '/admin/services.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-bell-concierge"></i>
                Manage Services
            </a>

            <a
                href="<?= e(
                    url(
                        '/admin/gallery.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-images"></i>
                View Gallery
            </a>

            <a
                href="<?= e(
                    url(
                        '/admin/feedback.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-comment-dots"></i>
                View Feedback
            </a>

            <a
                href="<?= e(
                    url(
                        '/admin/staff.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-users-gear"></i>
                Manage Staff
            </a>

            <a
                href="<?= e(
                    url(
                        '/admin/notifications.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-bell"></i>
                Notifications
            </a>

            <a
                href="<?= e(
                    url(
                        '/admin/profile.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a
                class="logout-link"
                href="<?= e(
                    url(
                        '/auth/logout.php'
                    )
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
                        Manage Services
                    </h1>

                    <p>
                        Manage customer-visible catering
                        menu items and music services.
                    </p>

                </div>

            </div>

            <div class="admin-topbar-right">

                <a
                    class="notification-link"
                    href="<?= e(
                        url(
                            '/admin/notifications.php'
                        )
                    ) ?>"
                    aria-label="Open notifications"
                >
                    <i class="fa-regular fa-bell"></i>

                    <?php if (
                        $unreadNotifications
                        > 0
                    ): ?>
                        <span>
                            <?= e(
                                $unreadNotifications
                                > 99
                                    ? '99+'
                                    : (string) $unreadNotifications
                            ) ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a
                    href="<?= e(
                        url(
                            '/admin/profile.php'
                        )
                    ) ?>"
                >
                    <img
                        class="topbar-profile-image"
                        src="<?= e(
                            $adminImage
                        ) ?>"
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
                <?= e(
                    $flash['message']
                ) ?>
            </div>

        <?php endif; ?>

        <?php if (
            $errors !== []
        ): ?>

            <div
                class="service-alert service-alert-danger"
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

        <section class="service-summary-grid">

            <article class="service-summary-card">

                <div
                    class="service-summary-icon catering"
                >
                    <i class="fa-solid fa-utensils"></i>
                </div>

                <div>
                    <span>
                        Catering Items
                    </span>

                    <strong>
                        <?= e(
                            (string) $cateringTotal
                        ) ?>
                    </strong>

                    <small>
                        <?= e(
                            (string) $cateringActive
                        ) ?>
                        active on website
                    </small>
                </div>

            </article>

            <article class="service-summary-card">

                <div
                    class="service-summary-icon music"
                >
                    <i class="fa-solid fa-music"></i>
                </div>

                <div>
                    <span>
                        Music Services
                    </span>

                    <strong>
                        <?= e(
                            (string) $musicTotal
                        ) ?>
                    </strong>

                    <small>
                        <?= e(
                            (string) $musicActive
                        ) ?>
                        active on website
                    </small>
                </div>

            </article>

            <article class="service-summary-note">

                <i class="fa-solid fa-circle-info"></i>

                <div>
                    <strong>
                        Customer website visibility
                    </strong>

                    <p>
                        Turn on Active on Customer Website
                        only for items customers should see
                        and select during booking.
                    </p>
                </div>

            </article>

        </section>

        <nav
            class="services-tabs"
            aria-label="Manage service sections"
        >

            <button
                class="service-tab-button <?= $activePanel === 'catering'
                    ? 'active'
                    : '' ?>"
                type="button"
                data-service-panel-target="catering"
            >
                <i class="fa-solid fa-utensils"></i>
                Catering Menu
            </button>

            <button
                class="service-tab-button <?= $activePanel === 'music'
                    ? 'active'
                    : '' ?>"
                type="button"
                data-service-panel-target="music"
            >
                <i class="fa-solid fa-music"></i>
                Music Services
            </button>

        </nav>

        <section
            class="service-panel <?= $activePanel === 'catering'
                ? 'active'
                : '' ?>"
            id="catering-panel"
            data-service-panel="catering"
        >

            <div class="service-panel-heading">

                <div>
                    <span>
                        Catering Management
                    </span>

                    <h2>
                        Manage Catering Menu Items
                    </h2>

                    <p>
                        Add dishes manually, assign a written
                        category and set the per-head price.
                    </p>
                </div>

                <div class="service-panel-badge">
                    <i class="fa-solid fa-user-group"></i>
                    Per-head pricing
                </div>

            </div>

            <div class="service-management-grid">

                <div
                    class="service-form-card"
                    id="cateringForm"
                >

                    <h3>
                        <?= $editingService
                            && $activePanel === 'catering'
                                ? 'Edit Catering Item'
                                : 'Add Catering Item' ?>
                    </h3>

                    <form method="post">

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="<?= $editingService
                                && $activePanel === 'catering'
                                    ? 'update'
                                    : 'create' ?>"
                        >

                        <input
                            type="hidden"
                            name="service_id"
                            value="<?= e(
                                (string) (
                                    $editingService
                                    && $activePanel === 'catering'
                                        ? $editingService['id']
                                        : 0
                                )
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="service_type"
                            value="catering"
                        >

                        <div class="service-field">

                            <label for="cateringName">
                                Dish / Item Name
                            </label>

                            <input
                                type="text"
                                id="cateringName"
                                name="name"
                                value="<?= e(
                                    $activePanel === 'catering'
                                        ? $formValues['name']
                                        : ''
                                ) ?>"
                                maxlength="150"
                                placeholder="e.g. Mutton Handi Shahi"
                                required
                            >

                        </div>

                        <div class="service-field">

                            <label for="cateringCategory">
                                Group Category Classification
                            </label>

                            <input
                                type="text"
                                id="cateringCategory"
                                name="category"
                                value="<?= e(
                                    $activePanel === 'catering'
                                        ? $formValues['category']
                                        : ''
                                ) ?>"
                                maxlength="100"
                                placeholder="e.g. Main Course"
                                required
                            >

                            <small>
                                Write the category manually.
                                No dropdown menu is used.
                            </small>

                        </div>

                        <div class="service-field">

                            <label for="cateringPrice">
                                Assigned Per-Head Price (Rs.)
                            </label>

                            <input
                                type="number"
                                id="cateringPrice"
                                name="price"
                                value="<?= e(
                                    $activePanel === 'catering'
                                        ? $formValues['price']
                                        : ''
                                ) ?>"
                                min="0"
                                step="0.01"
                                placeholder="e.g. 750"
                                required
                            >

                        </div>

                        <label
                            class="website-visibility-control"
                        >

                            <input
                                type="checkbox"
                                name="active_on_website"
                                value="1"
                                <?= $activePanel === 'catering'
                                    && $formValues['status'] === 'active'
                                        ? 'checked'
                                        : '' ?>
                            >

                            <span
                                class="website-visibility-switch"
                            ></span>

                            <span
                                class="website-visibility-copy"
                            >
                                <strong>
                                    Active on Customer Website
                                </strong>

                                <small>
                                    Customers can see and select
                                    this catering item.
                                </small>
                            </span>

                        </label>

                        <button
                            class="service-primary-button"
                            type="submit"
                        >
                            <i class="fa-solid fa-plus"></i>

                            <?= $editingService
                                && $activePanel === 'catering'
                                    ? 'Update Catering Item'
                                    : 'Insert Catering Item' ?>
                        </button>

                        <?php if (
                            $editingService
                            && $activePanel === 'catering'
                        ): ?>

                            <a
                                class="service-cancel-link"
                                href="<?= e(
                                    url(
                                        '/admin/services.php#catering-panel'
                                    )
                                ) ?>"
                            >
                                Cancel Editing
                            </a>

                        <?php endif; ?>

                    </form>

                </div>

                <div class="service-table-card">

                    <div class="service-table-heading">

                        <div>
                            <h3>
                                Saved Catering Items
                            </h3>

                            <p>
                                Website visibility can be
                                changed directly from the switch.
                            </p>
                        </div>

                        <span>
                            <?= e(
                                (string) count(
                                    $cateringServices
                                )
                            ) ?>
                            items
                        </span>

                    </div>

                    <?php if (
                        $cateringServices === []
                    ): ?>

                        <div class="service-empty-state">

                            <i class="fa-solid fa-utensils"></i>

                            <h4>
                                No catering items added yet
                            </h4>

                            <p>
                                Use the form to add the first
                                customer menu item.
                            </p>

                        </div>

                    <?php else: ?>

                        <div class="service-table-scroll">

                            <table class="service-data-table">

                                <thead>
                                    <tr>
                                        <th>Item Details</th>
                                        <th>Category</th>
                                        <th>Price / Head</th>
                                        <th>
                                            Active on Customer Website
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach (
                                        $cateringServices
                                        as $service
                                    ): ?>

                                        <tr>

                                            <td>
                                                <strong>
                                                    <?= e(
                                                        (string) $service[
                                                            'name'
                                                        ]
                                                    ) ?>
                                                </strong>

                                                <small>
                                                    Selected in
                                                    <?= e(
                                                        (string) $service[
                                                            'booking_count'
                                                        ]
                                                    ) ?>
                                                    booking(s)
                                                </small>
                                            </td>

                                            <td>
                                                <?= e(
                                                    (string) (
                                                        $service[
                                                            'category'
                                                        ]
                                                        ?: 'Uncategorised'
                                                    )
                                                ) ?>
                                            </td>

                                            <td
                                                class="service-table-price"
                                            >
                                                <?= e(
                                                    service_price_label(
                                                        (float) $service[
                                                            'price'
                                                        ]
                                                    )
                                                ) ?>
                                            </td>

                                            <td>

                                                <form
                                                    class="website-status-form"
                                                    method="post"
                                                >

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
                                                            (string) $service[
                                                                'id'
                                                            ]
                                                        ) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="service_type"
                                                        value="catering"
                                                    >

                                                    <label
                                                        class="table-visibility-toggle"
                                                        title="Change customer website visibility"
                                                    >

                                                        <input
                                                            type="checkbox"
                                                            <?= $service['status'] === 'active'
                                                                ? 'checked'
                                                                : '' ?>
                                                            onchange="this.form.submit()"
                                                        >

                                                        <span></span>

                                                        <strong>
                                                            <?= $service['status'] === 'active'
                                                                ? 'Active'
                                                                : 'Hidden' ?>
                                                        </strong>

                                                    </label>

                                                </form>

                                            </td>

                                            <td>

                                                <div class="service-row-actions">

                                                    <a
                                                        class="service-action-edit"
                                                        href="<?= e(
                                                            url(
                                                                '/admin/services.php?edit='
                                                                . (int) $service['id']
                                                                . '#cateringForm'
                                                            )
                                                        ) ?>"
                                                        aria-label="Edit catering item"
                                                    >
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>

                                                    <form
                                                        method="post"
                                                        onsubmit="return confirm('Delete this catering item permanently?');"
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
                                                                (string) $service[
                                                                    'id'
                                                                ]
                                                            ) ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="service_type"
                                                            value="catering"
                                                        >

                                                        <button
                                                            class="service-action-delete"
                                                            type="submit"
                                                            aria-label="Delete catering item"
                                                        >
                                                            <i class="fa-solid fa-trash-can"></i>
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

                </div>

            </div>

        </section>

        <section
            class="service-panel <?= $activePanel === 'music'
                ? 'active'
                : '' ?>"
            id="music-panel"
            data-service-panel="music"
        >

            <div class="service-panel-heading">

                <div>
                    <span>
                        Music Management
                    </span>

                    <h2>
                        Manage Music & Sound Services
                    </h2>

                    <p>
                        Add each music service manually
                        with its details and fixed price.
                    </p>
                </div>

                <div class="service-panel-badge">
                    <i class="fa-solid fa-tag"></i>
                    Fixed pricing
                </div>

            </div>

            <div class="service-management-grid">

                <div
                    class="service-form-card"
                    id="musicForm"
                >

                    <h3>
                        <?= $editingService
                            && $activePanel === 'music'
                                ? 'Edit Music Service'
                                : 'Add Music Service' ?>
                    </h3>

                    <form method="post">

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="<?= $editingService
                                && $activePanel === 'music'
                                    ? 'update'
                                    : 'create' ?>"
                        >

                        <input
                            type="hidden"
                            name="service_id"
                            value="<?= e(
                                (string) (
                                    $editingService
                                    && $activePanel === 'music'
                                        ? $editingService['id']
                                        : 0
                                )
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="service_type"
                            value="music"
                        >

                        <div class="service-field">

                            <label for="musicName">
                                Music Service Name
                            </label>

                            <input
                                type="text"
                                id="musicName"
                                name="name"
                                value="<?= e(
                                    $activePanel === 'music'
                                        ? $formValues['name']
                                        : ''
                                ) ?>"
                                maxlength="150"
                                placeholder="e.g. Live Music Setup"
                                required
                            >

                        </div>

                        <div class="service-field">

                            <label for="musicDescription">
                                Music Service Details
                            </label>

                            <textarea
                                id="musicDescription"
                                name="description"
                                maxlength="1000"
                                placeholder="e.g. Live band, stage equipment and sound system"
                                required
                            ><?= e(
                                $activePanel === 'music'
                                    ? $formValues['description']
                                    : ''
                            ) ?></textarea>

                            <small>
                                Write the music service manually.
                                No dropdown menu is used.
                            </small>

                        </div>

                        <div class="service-field">

                            <label for="musicPrice">
                                Fixed Music Price (Rs.)
                            </label>

                            <input
                                type="number"
                                id="musicPrice"
                                name="price"
                                value="<?= e(
                                    $activePanel === 'music'
                                        ? $formValues['price']
                                        : ''
                                ) ?>"
                                min="0"
                                step="0.01"
                                placeholder="e.g. 20000"
                                required
                            >

                        </div>

                        <label
                            class="website-visibility-control"
                        >

                            <input
                                type="checkbox"
                                name="active_on_website"
                                value="1"
                                <?= $activePanel === 'music'
                                    && $formValues['status'] === 'active'
                                        ? 'checked'
                                        : '' ?>
                            >

                            <span
                                class="website-visibility-switch"
                            ></span>

                            <span
                                class="website-visibility-copy"
                            >
                                <strong>
                                    Active on Customer Website
                                </strong>

                                <small>
                                    Customers can see and select
                                    this music service.
                                </small>
                            </span>

                        </label>

                        <button
                            class="service-primary-button"
                            type="submit"
                        >
                            <i class="fa-solid fa-plus"></i>

                            <?= $editingService
                                && $activePanel === 'music'
                                    ? 'Update Music Service'
                                    : 'Insert Music Service' ?>
                        </button>

                        <?php if (
                            $editingService
                            && $activePanel === 'music'
                        ): ?>

                            <a
                                class="service-cancel-link"
                                href="<?= e(
                                    url(
                                        '/admin/services.php#music-panel'
                                    )
                                ) ?>"
                            >
                                Cancel Editing
                            </a>

                        <?php endif; ?>

                    </form>

                </div>

                <div class="service-table-card">

                    <div class="service-table-heading">

                        <div>
                            <h3>
                                Saved Music Services
                            </h3>

                            <p>
                                Active services will later appear
                                in customer booking forms.
                            </p>
                        </div>

                        <span>
                            <?= e(
                                (string) count(
                                    $musicServices
                                )
                            ) ?>
                            services
                        </span>

                    </div>

                    <?php if (
                        $musicServices === []
                    ): ?>

                        <div class="service-empty-state">

                            <i class="fa-solid fa-music"></i>

                            <h4>
                                No music services added yet
                            </h4>

                            <p>
                                Use the form to add Basic Music,
                                Live Music or another sound service.
                            </p>

                        </div>

                    <?php else: ?>

                        <div class="service-table-scroll">

                            <table class="service-data-table">

                                <thead>
                                    <tr>
                                        <th>Music Service</th>
                                        <th>Details</th>
                                        <th>Fixed Price</th>
                                        <th>
                                            Active on Customer Website
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach (
                                        $musicServices
                                        as $service
                                    ): ?>

                                        <tr>

                                            <td>
                                                <strong>
                                                    <?= e(
                                                        (string) $service[
                                                            'name'
                                                        ]
                                                    ) ?>
                                                </strong>

                                                <small>
                                                    Selected in
                                                    <?= e(
                                                        (string) $service[
                                                            'booking_count'
                                                        ]
                                                    ) ?>
                                                    booking(s)
                                                </small>
                                            </td>

                                            <td
                                                class="service-detail-cell"
                                            >
                                                <?= e(
                                                    (string) (
                                                        $service[
                                                            'description'
                                                        ]
                                                        ?: 'No details added.'
                                                    )
                                                ) ?>
                                            </td>

                                            <td
                                                class="service-table-price"
                                            >
                                                <?= e(
                                                    service_price_label(
                                                        (float) $service[
                                                            'price'
                                                        ]
                                                    )
                                                ) ?>
                                            </td>

                                            <td>

                                                <form
                                                    class="website-status-form"
                                                    method="post"
                                                >

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
                                                            (string) $service[
                                                                'id'
                                                            ]
                                                        ) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="service_type"
                                                        value="music"
                                                    >

                                                    <label
                                                        class="table-visibility-toggle"
                                                        title="Change customer website visibility"
                                                    >

                                                        <input
                                                            type="checkbox"
                                                            <?= $service['status'] === 'active'
                                                                ? 'checked'
                                                                : '' ?>
                                                            onchange="this.form.submit()"
                                                        >

                                                        <span></span>

                                                        <strong>
                                                            <?= $service['status'] === 'active'
                                                                ? 'Active'
                                                                : 'Hidden' ?>
                                                        </strong>

                                                    </label>

                                                </form>

                                            </td>

                                            <td>

                                                <div class="service-row-actions">

                                                    <a
                                                        class="service-action-edit"
                                                        href="<?= e(
                                                            url(
                                                                '/admin/services.php?edit='
                                                                . (int) $service['id']
                                                                . '#musicForm'
                                                            )
                                                        ) ?>"
                                                        aria-label="Edit music service"
                                                    >
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>

                                                    <form
                                                        method="post"
                                                        onsubmit="return confirm('Delete this music service permanently?');"
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
                                                                (string) $service[
                                                                    'id'
                                                                ]
                                                            ) ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="service_type"
                                                            value="music"
                                                        >

                                                        <button
                                                            class="service-action-delete"
                                                            type="submit"
                                                            aria-label="Delete music service"
                                                        >
                                                            <i class="fa-solid fa-trash-can"></i>
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

                </div>

            </div>

        </section>

    </main>

    <script>
        "use strict";

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

        const serviceTabButtons =
            document.querySelectorAll(
                "[data-service-panel-target]"
            );

        const servicePanels =
            document.querySelectorAll(
                "[data-service-panel]"
            );

        function openServicePanel(
            panelName
        ) {
            serviceTabButtons.forEach(
                function (button) {
                    button.classList.toggle(
                        "active",
                        button.dataset
                            .servicePanelTarget
                            === panelName
                    );
                }
            );

            servicePanels.forEach(
                function (panel) {
                    panel.classList.toggle(
                        "active",
                        panel.dataset
                            .servicePanel
                            === panelName
                    );
                }
            );

            if (
                window.location.hash
                !== `#${panelName}-panel`
            ) {
                window.history.replaceState(
                    null,
                    "",
                    `#${panelName}-panel`
                );
            }
        }

        serviceTabButtons.forEach(
            function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        openServicePanel(
                            button.dataset
                                .servicePanelTarget
                        );
                    }
                );
            }
        );

        const requestedPanel =
            window.location.hash.includes(
                "music"
            )
                ? "music"
                : document.body.dataset
                    .initialServicePanel;

        openServicePanel(
            requestedPanel === "music"
                ? "music"
                : "catering"
        );
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>