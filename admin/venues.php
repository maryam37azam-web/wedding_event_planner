<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/venue_helpers.php';

require_role('admin');

$connection = db();
$adminId = (int) $_SESSION['user_id'];
$errors = [];
$flash = get_flash();

$allowedFilters = ['latest', 'all', 'active', 'inactive', 'top'];
$filter = strtolower(trim((string) ($_GET['filter'] ?? 'latest')));

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'latest';
}

$returnTo = strtolower(trim((string) ($_GET['return_to'] ?? 'manage')));
$returnTo = $returnTo === 'all' ? 'all' : 'manage';

$editId = max(0, (int) ($_GET['edit'] ?? 0));
$showCreateModal = isset($_GET['add']) && $_GET['add'] === '1';
$editingVenue = null;
$isVenueFormPost = false;

$formValues = [
    'name' => '',
    'location' => '',
    'capacity' => '',
    'price' => '',
    'facilities' => '',
    'description' => '',
    'status' => 'active',
];

function admin_venue_manage_path(string $filter = 'latest', array $extra = []): string
{
    $parameters = [];

    if ($filter !== 'latest') {
        $parameters['filter'] = $filter;
    }

    foreach ($extra as $key => $value) {
        $parameters[$key] = $value;
    }

    $path = '/admin/venues.php';

    if ($parameters !== []) {
        $path .= '?' . http_build_query($parameters);
    }

    return $path;
}

function admin_venue_list_heading(string $filter): string
{
    return match ($filter) {
        'all' => 'All Wedding Venues',
        'active' => 'Active Wedding Venues',
        'inactive' => 'Inactive Wedding Venues',
        'top' => 'Top Wedding Venue',
        default => 'Latest Wedding Venues',
    };
}

function admin_venue_empty_message(string $filter): string
{
    return match ($filter) {
        'active' => 'No active venues are available.',
        'inactive' => 'No inactive venues are available.',
        'top' => 'A top venue will appear after customers start making bookings.',
        default => 'Create the first wedding venue using the Add New Venue button.',
    };
}

function admin_venue_redirect_path(string $returnTo, string $filter): string
{
    if ($returnTo === 'all') {
        return '/admin/all_venues.php';
    }

    return admin_venue_manage_path($filter);
}

/*
|--------------------------------------------------------------------------
| Load administrator information
|--------------------------------------------------------------------------
*/

$adminStatement = $connection->prepare(
    'SELECT full_name, email, profile_image
     FROM users
     WHERE id = ? AND role = ?
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

$unreadStatement->execute([
    $adminId,
]);

$unreadNotifications = (int) $unreadStatement->fetchColumn();

/*
|--------------------------------------------------------------------------
| Venue summary
|--------------------------------------------------------------------------
*/

$summary = $connection->query(
    "SELECT
        COUNT(*) AS total_venues,
        COALESCE(SUM(status = 'active'), 0) AS active_venues,
        COALESCE(SUM(status = 'inactive'), 0) AS inactive_venues
     FROM venues"
)->fetch();

$totalVenues = (int) ($summary['total_venues'] ?? 0);
$activeVenues = (int) ($summary['active_venues'] ?? 0);
$inactiveVenues = (int) ($summary['inactive_venues'] ?? 0);

$topVenueStatement = $connection->query(
    "SELECT
        venues.id,
        venues.name,
        COUNT(bookings.id) AS booking_total
     FROM venues
     INNER JOIN bookings
        ON bookings.venue_id = venues.id
        AND bookings.booking_status <> 'cancelled'
     GROUP BY
        venues.id,
        venues.name,
        venues.created_at
     HAVING COUNT(bookings.id) > 0
     ORDER BY
        booking_total DESC,
        venues.created_at DESC
     LIMIT 1"
);

$topVenue = $topVenueStatement->fetch();
$topVenueId = $topVenue ? (int) $topVenue['id'] : 0;
$topVenueName = $topVenue ? (string) $topVenue['name'] : 'No booking data';

/*
|--------------------------------------------------------------------------
| Process venue actions
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $action = trim((string) ($_POST['action'] ?? ''));

    $returnFilter = strtolower(
        trim((string) ($_POST['return_filter'] ?? 'latest'))
    );

    $returnTo = strtolower(
        trim((string) ($_POST['return_to'] ?? 'manage'))
    );

    if (!in_array($returnFilter, $allowedFilters, true)) {
        $returnFilter = 'latest';
    }

    $returnTo = $returnTo === 'all' ? 'all' : 'manage';

    $returnPath = admin_venue_redirect_path(
        $returnTo,
        $returnFilter
    );

    if (!verify_csrf($submittedToken)) {
        $errors[] = 'Your form session expired. Refresh the page and try again.';
    }

    /*
    |--------------------------------------------------------------------------
    | Delete venue
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete' && $errors === []) {
        $venueId = max(
            0,
            (int) ($_POST['venue_id'] ?? 0)
        );

        $venueStatement = $connection->prepare(
            'SELECT
                main_image,
                image_one,
                image_two,
                image_three,
                image_four
             FROM venues
             WHERE id = ?
             LIMIT 1'
        );

        $venueStatement->execute([
            $venueId,
        ]);

        $venueToDelete = $venueStatement->fetch();

        if (!$venueToDelete) {
            set_flash(
                'error',
                'The selected venue was not found.'
            );

            redirect($returnPath);
        }

        try {
            $deleteStatement = $connection->prepare(
                'DELETE FROM venues
                 WHERE id = ?'
            );

            $deleteStatement->execute([
                $venueId,
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
                delete_venue_image(
                    $venueToDelete[$column] ?? null
                );
            }

            set_flash(
                'success',
                'Venue deleted successfully.'
            );
        } catch (Throwable $exception) {
            set_flash(
                'error',
                APP_DEBUG
                    ? 'Venue deletion failed: ' . $exception->getMessage()
                    : 'This venue could not be deleted because it may be connected to a booking.'
            );
        }

        redirect($returnPath);
    }

    /*
    |--------------------------------------------------------------------------
    | Create or update venue
    |--------------------------------------------------------------------------
    */

    if (in_array($action, ['create', 'update'], true)) {
        $isVenueFormPost = true;

        $venueId = max(
            0,
            (int) ($_POST['venue_id'] ?? 0)
        );

        $existingVenue = null;

        if ($action === 'update') {
            $existingStatement = $connection->prepare(
                'SELECT *
                 FROM venues
                 WHERE id = ?
                 LIMIT 1'
            );

            $existingStatement->execute([
                $venueId,
            ]);

            $existingVenue = $existingStatement->fetch();

            if (!$existingVenue) {
                $errors[] = 'The venue being edited was not found.';
            } else {
                $editId = $venueId;
                $editingVenue = $existingVenue;
            }
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $capacity = (int) ($_POST['capacity'] ?? 0);
        $priceInput = trim((string) ($_POST['price'] ?? ''));

        $priceClean = preg_replace(
            '/[^0-9.]/',
            '',
            $priceInput
        );

        $facilitiesInput = trim(
            (string) ($_POST['facilities'] ?? '')
        );

        $description = trim(
            (string) ($_POST['description'] ?? '')
        );

        $status = isset($_POST['activate_on_website'])
            ? 'active'
            : 'inactive';

        $formValues = [
            'name' => $name,
            'location' => $location,
            'capacity' => $capacity > 0 ? (string) $capacity : '',
            'price' => $priceInput,
            'facilities' => $facilitiesInput,
            'description' => $description,
            'status' => $status,
        ];

        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
            $errors[] = 'Venue name must contain between 3 and 150 characters.';
        }

        if (mb_strlen($location) < 3 || mb_strlen($location) > 255) {
            $errors[] = 'Venue location must contain between 3 and 255 characters.';
        }

        if ($capacity < 1) {
            $errors[] = 'Venue capacity must be at least 1.';
        }

        if (
            $priceClean === null
            || $priceClean === ''
            || !is_numeric($priceClean)
            || (float) $priceClean < 0
        ) {
            $errors[] = 'Enter a valid venue price.';
        }

        if ($facilitiesInput === '') {
            $errors[] = 'Enter at least one venue facility.';
        }

        if ($description === '') {
            $errors[] = 'Enter the complete venue description.';
        }

        $imageValues = [
            'main_image' => $existingVenue['main_image'] ?? null,
            'image_one' => $existingVenue['image_one'] ?? null,
            'image_two' => $existingVenue['image_two'] ?? null,
            'image_three' => $existingVenue['image_three'] ?? null,
            'image_four' => $existingVenue['image_four'] ?? null,
        ];

        $newUploadedImages = [];
        $oldImagesToDelete = [];

        if ($errors === []) {
            try {
                foreach (
                    array_keys(venue_image_fields()) as $index => $column
                ) {
                    $file = $_FILES[$column] ?? [
                        'error' => UPLOAD_ERR_NO_FILE,
                    ];

                    $fileError = (int) (
                        $file['error'] ?? UPLOAD_ERR_NO_FILE
                    );

                    $hasNewFile = $fileError !== UPLOAD_ERR_NO_FILE;

                    $removeRequested =
                        $action === 'update'
                        && isset($_POST['remove_' . $column]);

                    if ($action === 'create' && !$hasNewFile) {
                        throw new RuntimeException(
                            'Upload one main image and all three venue gallery images.'
                        );
                    }

                    if ($hasNewFile) {
                        $newPath = upload_venue_image(
                            $file,
                            'venue_'
                            . ($venueId > 0 ? $venueId : 'new')
                            . '_'
                            . ($index + 1)
                        );

                        if ($newPath !== null) {
                            $newUploadedImages[] = $newPath;

                            if (!empty($imageValues[$column])) {
                                $oldImagesToDelete[] =
                                    (string) $imageValues[$column];
                            }

                            $imageValues[$column] = $newPath;
                        }
                    } elseif ($removeRequested) {
                        if (!empty($imageValues[$column])) {
                            $oldImagesToDelete[] =
                                (string) $imageValues[$column];
                        }

                        $imageValues[$column] = null;
                    }
                }

                /*
                 * The new interface uses only three gallery images.
                 */
                if (
                    $action === 'update'
                    && !empty($imageValues['image_four'])
                ) {
                    $oldImagesToDelete[] =
                        (string) $imageValues['image_four'];

                    $imageValues['image_four'] = null;
                }
            } catch (Throwable $exception) {
                foreach ($newUploadedImages as $newImage) {
                    delete_venue_image($newImage);
                }

                $newUploadedImages = [];
                $errors[] = $exception->getMessage();
            }
        }

        if ($errors === []) {
            $normalisedFacilities = implode(
                PHP_EOL,
                venue_facility_lines($facilitiesInput)
            );

            try {
                $connection->beginTransaction();

                if ($action === 'create') {
                    $saveStatement = $connection->prepare(
                        'INSERT INTO venues (
                            name,
                            location,
                            capacity,
                            price,
                            facilities,
                            description,
                            main_image,
                            image_one,
                            image_two,
                            image_three,
                            image_four,
                            availability_status,
                            status,
                            created_by
                         ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?
                         )'
                    );

                    $saveStatement->execute([
                        $name,
                        $location,
                        $capacity,
                        (float) $priceClean,
                        $normalisedFacilities !== ''
                            ? $normalisedFacilities
                            : null,
                        $description,
                        $imageValues['main_image'],
                        $imageValues['image_one'],
                        $imageValues['image_two'],
                        $imageValues['image_three'],
                        null,
                        'available',
                        $status,
                        $adminId,
                    ]);
                } else {
                    $saveStatement = $connection->prepare(
                        'UPDATE venues
                         SET name = ?,
                             location = ?,
                             capacity = ?,
                             price = ?,
                             facilities = ?,
                             description = ?,
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
                        $location,
                        $capacity,
                        (float) $priceClean,
                        $normalisedFacilities !== ''
                            ? $normalisedFacilities
                            : null,
                        $description,
                        $imageValues['main_image'],
                        $imageValues['image_one'],
                        $imageValues['image_two'],
                        $imageValues['image_three'],
                        null,
                        $status,
                        $venueId,
                    ]);
                }

                $connection->commit();

                foreach (array_unique($oldImagesToDelete) as $oldImage) {
                    delete_venue_image($oldImage);
                }

                set_flash(
                    'success',
                    $action === 'create'
                        ? 'Venue created successfully.'
                        : 'Venue updated successfully.'
                );

                redirect($returnPath);
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                foreach ($newUploadedImages as $newImage) {
                    delete_venue_image($newImage);
                }

                $errors[] = APP_DEBUG
                    ? 'Venue could not be saved: ' . $exception->getMessage()
                    : 'Venue could not be saved.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load venue for editing
|--------------------------------------------------------------------------
*/

if ($editId > 0 && $editingVenue === null) {
    $editStatement = $connection->prepare(
        'SELECT *
         FROM venues
         WHERE id = ?
         LIMIT 1'
    );

    $editStatement->execute([
        $editId,
    ]);

    $editingVenue = $editStatement->fetch();

    if (!$editingVenue) {
        set_flash(
            'error',
            'The selected venue was not found.'
        );

        redirect(
            admin_venue_redirect_path(
                $returnTo,
                $filter
            )
        );
    }
}

if ($editingVenue && !$isVenueFormPost) {
    $formValues = [
        'name' => (string) $editingVenue['name'],
        'location' => (string) ($editingVenue['location'] ?? ''),
        'capacity' => (string) ($editingVenue['capacity'] ?? ''),
        'price' => (string) $editingVenue['price'],
        'facilities' => (string) ($editingVenue['facilities'] ?? ''),
        'description' => (string) ($editingVenue['description'] ?? ''),
        'status' => (string) $editingVenue['status'],
    ];
}

$openVenueModal =
    $showCreateModal
    || $editingVenue !== null
    || ($isVenueFormPost && $errors !== []);

/*
|--------------------------------------------------------------------------
| Load venue cards
|--------------------------------------------------------------------------
*/

$venueQuery = 'SELECT * FROM venues';
$venueParameters = [];

if ($filter === 'active') {
    $venueQuery .= ' WHERE status = ?';
    $venueParameters[] = 'active';
} elseif ($filter === 'inactive') {
    $venueQuery .= ' WHERE status = ?';
    $venueParameters[] = 'inactive';
} elseif ($filter === 'top') {
    if ($topVenueId > 0) {
        $venueQuery .= ' WHERE id = ?';
        $venueParameters[] = $topVenueId;
    } else {
        $venueQuery .= ' WHERE 1 = 0';
    }
}

$venueQuery .= ' ORDER BY created_at DESC, id DESC';

if ($filter === 'latest') {
    $venueQuery .= ' LIMIT 3';
}

$venueStatement = $connection->prepare($venueQuery);
$venueStatement->execute($venueParameters);
$venues = $venueStatement->fetchAll();

$closeModalPath = $returnTo === 'all'
    ? '/admin/all_venues.php'
    : admin_venue_manage_path($filter);

$addVenuePath = admin_venue_manage_path(
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
        Manage Venues | <?= e(APP_NAME) ?>
    </title>

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
        href="<?= e(url('/assets/css/venue_management.css')) ?>"
    >
</head>

<body
    class="admin-dashboard-page <?= $openVenueModal
        ? 'venue-modal-lock'
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
                <?= e((string) $admin['full_name']) ?>
            </h2>

            <p>Admin</p>

            <div class="online-status">
                ● Online
            </div>

        </div>

        <nav class="admin-menu">

            <a href="<?= e(url('/admin/dashboard.php')) ?>">
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a href="<?= e(url('/admin/bookings.php')) ?>">
                <i class="fa-solid fa-calendar-check"></i>
                Manage Bookings
            </a>

            <a href="<?= e(url('/admin/packages.php')) ?>">
                <i class="fa-solid fa-gift"></i>
                Manage Packages
            </a>

            <a
                class="active"
                href="<?= e(url('/admin/venues.php')) ?>"
            >
                <i class="fa-solid fa-hotel"></i>
                Manage Venues
            </a>

            <a href="<?= e(url('/admin/services.php')) ?>">
                <i class="fa-solid fa-bell-concierge"></i>
                Manage Services
            </a>

            <a href="<?= e(url('/admin/gallery.php')) ?>">
                <i class="fa-solid fa-images"></i>
                View Gallery
            </a>

            <a href="<?= e(url('/admin/feedback.php')) ?>">
                <i class="fa-solid fa-comment-dots"></i>
                View Feedback
            </a>

            <a href="<?= e(url('/admin/staff.php')) ?>">
                <i class="fa-solid fa-users-gear"></i>
                Manage Staff
            </a>

            <a href="<?= e(url('/admin/notifications.php')) ?>">
                <i class="fa-solid fa-bell"></i>
                Notifications
            </a>

            <a href="<?= e(url('/admin/profile.php')) ?>">
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a
                class="logout-link"
                href="<?= e(url('/auth/logout.php')) ?>"
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

    <main class="admin-main venue-admin-main">

        <header class="admin-topbar">

            <div class="admin-topbar-left">

                <button
                    class="sidebar-toggle venue-mobile-menu"
                    id="sidebarToggle"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="admin-welcome">

                    <h1>Manage Venues</h1>

                    <p>
                        Create and manage wedding-event
                        venues easily.
                    </p>

                </div>

            </div>

            <div class="admin-topbar-right">

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
                        alt="Admin profile"
                    >

                </a>

            </div>

        </header>

        <?php if ($flash): ?>

            <div
                class="venue-flash <?= $flash['type'] === 'success'
                    ? 'venue-flash-success'
                    : 'venue-flash-danger' ?>"
            >
                <?= e((string) $flash['message']) ?>
            </div>

        <?php endif; ?>

        <section
            class="summary-cards venue-summary-cards"
        >

            <a
                class="summary-card venue-summary-card"
                href="<?= e(url('/admin/all_venues.php')) ?>"
            >

                <div class="summary-icon pink">
                    <i class="fa-solid fa-hotel"></i>
                </div>

                <div>
                    <h4>Total Venues</h4>

                    <h2>
                        <?= e(number_format($totalVenues)) ?>
                    </h2>

                    <p>Click to show all</p>
                </div>

            </a>

            <a
                class="summary-card venue-summary-card <?= $filter === 'active'
                    ? 'selected'
                    : '' ?>"
                href="<?= e(
                    url(admin_venue_manage_path('active'))
                    . '#venueList'
                ) ?>"
            >

                <div class="summary-icon purple">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    <h4>Active Venues</h4>

                    <h2>
                        <?= e(number_format($activeVenues)) ?>
                    </h2>

                    <p>Visible on website</p>
                </div>

            </a>

            <a
                class="summary-card venue-summary-card <?= $filter === 'inactive'
                    ? 'selected'
                    : '' ?>"
                href="<?= e(
                    url(admin_venue_manage_path('inactive'))
                    . '#venueList'
                ) ?>"
            >

                <div class="summary-icon orange">
                    <i class="fa-solid fa-circle-pause"></i>
                </div>

                <div>
                    <h4>Inactive Venues</h4>

                    <h2>
                        <?= e(number_format($inactiveVenues)) ?>
                    </h2>

                    <p>Hidden from website</p>
                </div>

            </a>

            <a
                class="summary-card venue-summary-card <?= $filter === 'top'
                    ? 'selected'
                    : '' ?>"
                href="<?= e(
                    url(admin_venue_manage_path('top'))
                    . '#venueList'
                ) ?>"
            >

                <div class="summary-icon blue">
                    <i class="fa-solid fa-star"></i>
                </div>

                <div class="venue-top-summary">

                    <h4>Top Venue</h4>

                    <h2>
                        <?= e($topVenueName) ?>
                    </h2>

                    <p>Based on bookings</p>

                </div>

            </a>

        </section>

        <section
            class="venue-section-box"
            id="venueList"
        >

            <div class="venue-section-heading">

                <div>

                    <h2>
                        <?= e(
                            admin_venue_list_heading($filter)
                        ) ?>
                    </h2>

                    <p>
                        Main image, website status,
                        complete description and three gallery images.
                    </p>

                </div>

                <div class="venue-heading-actions">

                    <a
                        class="venue-add-button"
                        id="openCreateVenue"
                        href="<?= e(url($addVenuePath)) ?>"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Add New Venue
                    </a>

                    <a
                        class="venue-view-button"
                        href="<?= e(url('/admin/all_venues.php')) ?>"
                    >
                        <i class="fa-solid fa-border-all"></i>
                        View All Venues
                    </a>

                </div>

            </div>

            <?php if ($filter !== 'latest'): ?>

                <div class="venue-filter-row">

                    <span>
                        Showing:
                        <strong>
                            <?= e(
                                admin_venue_list_heading($filter)
                            ) ?>
                        </strong>
                    </span>

                    <a
                        href="<?= e(url('/admin/venues.php')) ?>#venueList"
                    >
                        Show Latest Venues
                    </a>

                </div>

            <?php endif; ?>

            <?php if ($venues === []): ?>

                <div class="venue-empty-state">

                    <i class="fa-solid fa-hotel"></i>

                    <h3>No venues found</h3>

                    <p>
                        <?= e(admin_venue_empty_message($filter)) ?>
                    </p>

                </div>

            <?php else: ?>

                <div class="venue-grid">

                    <?php foreach ($venues as $venue): ?>
                        <?php
                        $venueId = (int) $venue['id'];

                        $mainImageUrl = venue_image_url(
                            $venue['main_image'] ?? null
                        );

                        $thumbnailPaths = [
                            $venue['image_one'] ?? null,
                            $venue['image_two'] ?? null,
                            $venue['image_three'] ?? null,
                        ];

                        $editPath = admin_venue_manage_path(
                            $filter,
                            [
                                'edit' => (string) $venueId,
                                'return_to' => 'manage',
                            ]
                        );

                        $status = (string) (
                            $venue['status'] ?? 'inactive'
                        );
                        ?>

                        <article class="venue-card">

                            <div class="venue-card-main-image-wrap">

                                <img
                                    class="venue-card-main-image"
                                    id="venueMainImage<?= e((string) $venueId) ?>"
                                    src="<?= e($mainImageUrl) ?>"
                                    data-original-image="<?= e($mainImageUrl) ?>"
                                    alt="<?= e((string) $venue['name']) ?>"
                                >

                                <button
                                    class="venue-main-reset-button"
                                    type="button"
                                    data-venue-main="venueMainImage<?= e((string) $venueId) ?>"
                                    data-venue-original="<?= e($mainImageUrl) ?>"
                                    aria-label="Show original main venue image"
                                >
                                    <i class="fa-regular fa-image"></i>
                                    Main Photo
                                </button>

                            </div>

                            <div class="venue-card-content">

                                <span
                                    class="venue-website-status <?= e($status) ?>"
                                >
                                    <?= $status === 'active'
                                        ? 'Active On Website'
                                        : 'Inactive On Website' ?>
                                </span>

                                <h3>
                                    <?= e((string) $venue['name']) ?>
                                </h3>

                                <p class="venue-card-description">
                                    <?= nl2br(
                                        e(venue_card_description($venue))
                                    ) ?>
                                </p>

                                <div class="venue-thumbnail-row">

                                    <?php foreach (
                                        $thumbnailPaths as $index => $imagePath
                                    ): ?>

                                        <button
                                            class="venue-thumbnail-button"
                                            type="button"
                                            data-venue-main="venueMainImage<?= e((string) $venueId) ?>"
                                            data-venue-image="<?= e(
                                                venue_image_url($imagePath)
                                            ) ?>"
                                            aria-label="Show gallery image <?= e(
                                                (string) ($index + 1)
                                            ) ?>"
                                        >

                                            <img
                                                src="<?= e(
                                                    venue_image_url($imagePath)
                                                ) ?>"
                                                alt="Venue gallery image <?= e(
                                                    (string) ($index + 1)
                                                ) ?>"
                                            >

                                        </button>

                                    <?php endforeach; ?>

                                </div>

                                <div class="venue-actions">

                                    <a
                                        class="venue-edit-button"
                                        href="<?= e(url($editPath)) ?>"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                        Edit
                                    </a>

                                    <form
                                        method="post"
                                        onsubmit="return confirm('Delete this venue permanently?');"
                                    >
                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="venue_id"
                                            value="<?= e((string) $venueId) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="return_filter"
                                            value="<?= e($filter) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="return_to"
                                            value="manage"
                                        >

                                        <button
                                            class="venue-delete-button"
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
        class="venue-modal <?= $openVenueModal ? 'open' : '' ?>"
        id="venueFormModal"
        aria-hidden="<?= $openVenueModal ? 'false' : 'true' ?>"
    >

        <div
            class="venue-modal-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="venueFormTitle"
        >

            <button
                class="venue-modal-close"
                id="venueModalClose"
                type="button"
                aria-label="Close venue form"
            >
                &times;
            </button>

            <div class="venue-form-heading">

                <h2 id="venueFormTitle">
                    <?= $editingVenue
                        ? 'Edit Venue'
                        : 'Create New Venue' ?>
                </h2>

                <p>
                    <?= $editingVenue
                        ? 'Change venue details, replace images or remove existing images.'
                        : 'Enter the venue details and upload one main image with three gallery images.' ?>
                </p>

            </div>

            <?php if ($errors !== []): ?>

                <div
                    class="venue-flash venue-flash-danger venue-modal-errors"
                >
                    <ul>

                        <?php foreach ($errors as $error): ?>

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
                id="venueEditorForm"
            >
                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editingVenue
                        ? 'update'
                        : 'create' ?>"
                >

                <input
                    type="hidden"
                    name="venue_id"
                    value="<?= e(
                        (string) ($editingVenue['id'] ?? 0)
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

                <div class="venue-form-grid">

                    <div class="venue-input-box">

                        <label for="name">
                            Venue Name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?= e($formValues['name']) ?>"
                            maxlength="150"
                            placeholder="Enter venue name"
                            required
                        >

                    </div>

                    <div class="venue-input-box">

                        <label for="location">
                            Venue Location
                        </label>

                        <input
                            type="text"
                            id="location"
                            name="location"
                            value="<?= e($formValues['location']) ?>"
                            maxlength="255"
                            placeholder="Enter complete venue location"
                            required
                        >

                    </div>

                    <div class="venue-input-box">

                        <label for="capacity">
                            Guest Capacity
                        </label>

                        <input
                            type="number"
                            id="capacity"
                            name="capacity"
                            value="<?= e($formValues['capacity']) ?>"
                            min="1"
                            placeholder="Guest capacity"
                            required
                        >

                    </div>

                    <div class="venue-input-box">

                        <label for="price">
                            Venue Price
                        </label>

                        <input
                            type="text"
                            id="price"
                            name="price"
                            value="<?= e($formValues['price']) ?>"
                            placeholder="Example: 150000"
                            required
                        >

                    </div>

                    <div
                        class="venue-input-box venue-textarea-box"
                    >

                        <label for="facilities">
                            Venue Facilities
                        </label>

                        <textarea
                            id="facilities"
                            name="facilities"
                            placeholder="Enter one facility per line"
                            required
                        ><?= e($formValues['facilities']) ?></textarea>

                    </div>

                    <div
                        class="venue-input-box venue-textarea-box"
                    >

                        <label for="description">
                            Complete Description
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            placeholder="Write complete venue details"
                            required
                        ><?= e($formValues['description']) ?></textarea>

                    </div>

                    <div
                        class="venue-input-box venue-span-2"
                    >

                        <div class="venue-image-section-title">

                            <h3>Venue Images</h3>

                            <p>
                                JPG, PNG or WEBP.
                                Maximum 5 MB for each image.
                            </p>

                        </div>

                        <div class="venue-image-input-grid">

                            <?php foreach (
                                venue_image_fields() as $fieldName => $fieldLabel
                            ): ?>
                                <?php
                                $hasCurrentImage =
                                    $editingVenue
                                    && !empty($editingVenue[$fieldName]);
                                ?>

                                <div class="venue-image-input-box">

                                    <label for="<?= e($fieldName) ?>">
                                        <?= e($fieldLabel) ?>
                                    </label>

                                    <input
                                        class="venue-file-input"
                                        type="file"
                                        id="<?= e($fieldName) ?>"
                                        name="<?= e($fieldName) ?>"
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                        data-preview="preview_<?= e($fieldName) ?>"
                                        <?= $editingVenue ? '' : 'required' ?>
                                    >

                                    <div
                                        class="venue-current-image <?= $hasCurrentImage
                                            ? ''
                                            : 'empty' ?>"
                                    >

                                        <img
                                            id="preview_<?= e($fieldName) ?>"
                                            src="<?= e(
                                                venue_image_url(
                                                    $editingVenue[$fieldName] ?? null
                                                )
                                            ) ?>"
                                            alt="<?= e($fieldLabel) ?> preview"
                                        >

                                        <span>
                                            <?= $hasCurrentImage
                                                ? 'Current image'
                                                : 'No current image' ?>
                                        </span>

                                    </div>

                                    <?php if ($editingVenue): ?>

                                        <label class="venue-remove-image-option">

                                            <input
                                                type="checkbox"
                                                name="remove_<?= e($fieldName) ?>"
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
                        class="venue-input-box venue-span-2"
                    >

                        <label>
                            Website Visibility
                        </label>

                        <div
                            class="venue-options venue-visibility-option"
                        >

                            <label>

                                <input
                                    type="checkbox"
                                    name="activate_on_website"
                                    value="1"
                                    <?= $formValues['status'] === 'active'
                                        ? 'checked'
                                        : '' ?>
                                >

                                <span>
                                    Activate venue on customer website
                                </span>

                            </label>

                        </div>

                    </div>

                </div>

                <div class="venue-submit-row">

                    <button
                        class="venue-submit-button"
                        type="submit"
                    >
                        <?= $editingVenue
                            ? 'Update Venue'
                            : 'Create Venue' ?>
                    </button>

                    <button
                        class="venue-cancel-button"
                        id="venueModalCancel"
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
            document.getElementById("adminSidebar");

        const sidebarOverlay =
            document.getElementById("sidebarOverlay");

        const sidebarToggle =
            document.getElementById("sidebarToggle");

        function closeSidebar() {
            adminSidebar?.classList.remove("open");
            sidebarOverlay?.classList.remove("open");
        }

        sidebarToggle?.addEventListener(
            "click",
            function () {
                adminSidebar?.classList.toggle("open");
                sidebarOverlay?.classList.toggle("open");
            }
        );

        sidebarOverlay?.addEventListener(
            "click",
            closeSidebar
        );

        const venueModal =
            document.getElementById("venueFormModal");

        const openCreateVenue =
            document.getElementById("openCreateVenue");

        const venueModalClose =
            document.getElementById("venueModalClose");

        const venueModalCancel =
            document.getElementById("venueModalCancel");

        const venueCloseUrl =
            <?= json_encode(
                url($closeModalPath),
                JSON_UNESCAPED_SLASHES
            ) ?>;

        function openVenueModal() {
            venueModal?.classList.add("open");
            venueModal?.setAttribute(
                "aria-hidden",
                "false"
            );

            document.body.classList.add(
                "venue-modal-lock"
            );
        }

        function closeVenueModal() {
            venueModal?.classList.remove("open");
            venueModal?.setAttribute(
                "aria-hidden",
                "true"
            );

            document.body.classList.remove(
                "venue-modal-lock"
            );

            window.location.href = venueCloseUrl;
        }

        openCreateVenue?.addEventListener(
            "click",
            function (event) {
                event.preventDefault();
                openVenueModal();
            }
        );

        venueModalClose?.addEventListener(
            "click",
            closeVenueModal
        );

        venueModalCancel?.addEventListener(
            "click",
            closeVenueModal
        );

        venueModal?.addEventListener(
            "click",
            function (event) {
                if (event.target === venueModal) {
                    closeVenueModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (
                    event.key === "Escape"
                    && venueModal?.classList.contains("open")
                ) {
                    closeVenueModal();
                }
            }
        );

        function clearActiveVenueThumbnail(mainImageId) {
            document
                .querySelectorAll(
                    '[data-venue-main="' + mainImageId + '"]'
                )
                .forEach(function (control) {
                    if (
                        control.classList.contains(
                            "venue-thumbnail-button"
                        )
                    ) {
                        control.classList.remove("active");
                    }
                });
        }

        document
            .querySelectorAll(".venue-thumbnail-button")
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        const mainImage =
                            document.getElementById(
                                button.dataset.venueMain
                            );

                        if (
                            !mainImage
                            || !button.dataset.venueImage
                        ) {
                            return;
                        }

                        mainImage.src =
                            button.dataset.venueImage;

                        clearActiveVenueThumbnail(
                            button.dataset.venueMain
                        );

                        button.classList.add("active");
                    }
                );
            });

        document
            .querySelectorAll(".venue-main-reset-button")
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        const mainImage =
                            document.getElementById(
                                button.dataset.venueMain
                            );

                        if (
                            !mainImage
                            || !button.dataset.venueOriginal
                        ) {
                            return;
                        }

                        mainImage.src =
                            button.dataset.venueOriginal;

                        clearActiveVenueThumbnail(
                            button.dataset.venueMain
                        );
                    }
                );
            });

        document
            .querySelectorAll(".venue-file-input")
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

                        const reader = new FileReader();

                        reader.addEventListener(
                            "load",
                            function () {
                                preview.src = reader.result;

                                preview
                                    .closest(".venue-current-image")
                                    ?.classList.remove("empty");

                                const label =
                                    preview
                                        .closest(".venue-current-image")
                                        ?.querySelector("span");

                                if (label) {
                                    label.textContent =
                                        "New selected image";
                                }
                            }
                        );

                        reader.readAsDataURL(file);
                    }
                );
            });
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>