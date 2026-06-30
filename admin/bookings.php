<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');

$connection = db();
$adminId = (int) $_SESSION['user_id'];
$errors = [];
$flash = get_flash();

$allowedStatuses = [
    'pending',
    'confirmed',
    'in_progress',
    'completed',
    'cancelled',
];

$statusTransitions = [
    'pending' => [
        'confirmed',
        'cancelled',
    ],
    'confirmed' => [
        'in_progress',
        'cancelled',
    ],
    'in_progress' => [
        'completed',
    ],
    'completed' => [],
    'cancelled' => [],
];

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

function admin_booking_status_label(
    string $status
): string {
    return match ($status) {
        'in_progress' => 'In Progress',

        default => ucwords(
            str_replace('_', ' ', $status)
        ),
    };
}

function admin_booking_status_class(
    string $status
): string {
    return match ($status) {
        'confirmed' => 'confirmed',
        'in_progress' => 'in-progress',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        default => 'pending',
    };
}

function admin_booking_date(
    mixed $date
): string {
    $timestamp = strtotime((string) $date);

    if ($timestamp === false) {
        return 'Not available';
    }

    return date('d F Y', $timestamp);
}

function admin_booking_time(
    mixed $time
): string {
    $time = trim((string) $time);

    if ($time === '') {
        return 'Not selected';
    }

    $timestamp = strtotime($time);

    if ($timestamp === false) {
        return $time;
    }

    return date('h:i A', $timestamp);
}

function admin_filter_date_is_valid(
    string $date
): bool {
    if ($date === '') {
        return true;
    }

    $dateObject = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $date
    );

    $dateErrors = DateTimeImmutable::getLastErrors();

    return $dateObject !== false
        && (
            $dateErrors === false
            || (
                $dateErrors['warning_count'] === 0
                && $dateErrors['error_count'] === 0
            )
        )
        && $dateObject->format('Y-m-d') === $date;
}

function admin_create_customer_booking_notification(
    PDO $connection,
    int $customerId,
    int $adminId,
    int $bookingId,
    string $bookingCode,
    string $newStatus
): void {
    try {
        $columnRows = $connection
            ->query('SHOW COLUMNS FROM notifications')
            ->fetchAll(PDO::FETCH_ASSOC);

        $availableColumns = [];

        foreach ($columnRows as $columnRow) {
            $availableColumns[
                (string) $columnRow['Field']
            ] = true;
        }

        $recipientColumn = null;

        if (isset($availableColumns['recipient_id'])) {
            $recipientColumn = 'recipient_id';
        } elseif (isset($availableColumns['user_id'])) {
            $recipientColumn = 'user_id';
        }

        if ($recipientColumn === null) {
            return;
        }

        $statusLabel = admin_booking_status_label(
            $newStatus
        );

        $values = [
            $recipientColumn => $customerId,
        ];

        if (isset($availableColumns['sender_id'])) {
            $values['sender_id'] = $adminId;
        }

        if (isset($availableColumns['recipient_role'])) {
            $values['recipient_role'] = 'customer';
        }

        if (isset($availableColumns['user_role'])) {
            $values['user_role'] = 'customer';
        }

        if (isset($availableColumns['title'])) {
            $values['title'] =
                'Booking Status Updated';
        }

        if (isset($availableColumns['message'])) {
            $values['message'] =
                'Your booking '
                . $bookingCode
                . ' is now marked as '
                . $statusLabel
                . '.';
        }

        if (
            isset(
                $availableColumns[
                    'notification_type'
                ]
            )
        ) {
            $values['notification_type'] =
                'booking';
        }

        if (isset($availableColumns['type'])) {
            $values['type'] = 'booking';
        }

        if (
            isset(
                $availableColumns['related_id']
            )
        ) {
            $values['related_id'] =
                $bookingId;
        }

        if (
            isset(
                $availableColumns['booking_id']
            )
        ) {
            $values['booking_id'] =
                $bookingId;
        }

        $notificationLink =
            '/customer/my_bookings.php?booking_id='
            . $bookingId;

        foreach (
            [
                'target_url',
                'link',
                'url',
            ] as $linkColumn
        ) {
            if (
                isset(
                    $availableColumns[
                        $linkColumn
                    ]
                )
            ) {
                $values[$linkColumn] =
                    $notificationLink;
            }
        }

        if (
            isset(
                $availableColumns['is_read']
            )
        ) {
            $values['is_read'] = 0;
        }

        $now = date('Y-m-d H:i:s');

        if (
            isset(
                $availableColumns['created_at']
            )
        ) {
            $values['created_at'] = $now;
        }

        if (
            isset(
                $availableColumns['updated_at']
            )
        ) {
            $values['updated_at'] = $now;
        }

        $columns = array_keys($values);

        $quotedColumns = array_map(
            static fn (
                string $column
            ): string =>
                '`' . $column . '`',
            $columns
        );

        $placeholders = implode(
            ', ',
            array_fill(
                0,
                count($columns),
                '?'
            )
        );

        $insertStatement =
            $connection->prepare(
                'INSERT INTO notifications ('
                . implode(
                    ', ',
                    $quotedColumns
                )
                . ') VALUES ('
                . $placeholders
                . ')'
            );

        $insertStatement->execute(
            array_values($values)
        );
    } catch (Throwable $exception) {
        // Notification errors do not undo the status update.
    }
}

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $action = trim(
        (string) (
            $_POST['action'] ?? ''
        )
    );

    $bookingId = max(
        0,
        (int) (
            $_POST['booking_id'] ?? 0
        )
    );

    $newStatus = strtolower(
        trim(
            (string) (
                $_POST['booking_status']
                ?? ''
            )
        )
    );

    if (
        !verify_csrf(
            $submittedToken
        )
    ) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

    if ($action !== 'update_status') {
        $errors[] =
            'Invalid booking action.';
    }

    if ($bookingId < 1) {
        $errors[] =
            'Select a valid booking.';
    }

    if (
        !in_array(
            $newStatus,
            $allowedStatuses,
            true
        )
    ) {
        $errors[] =
            'Select a valid booking status.';
    }

    if ($errors === []) {
        $bookingCheckStatement =
            $connection->prepare(
                'SELECT
                    id,
                    booking_code,
                    customer_id,
                    booking_status
                 FROM bookings
                 WHERE id = ?
                 LIMIT 1'
            );

        $bookingCheckStatement->execute([
            $bookingId,
        ]);

        $bookingToUpdate =
            $bookingCheckStatement->fetch();

        if (!$bookingToUpdate) {
            $errors[] =
                'The selected booking was not found.';
        } else {
            $currentStatus = (string) (
                $bookingToUpdate[
                    'booking_status'
                ]
                ?? 'pending'
            );

            $allowedNextStatuses =
                $statusTransitions[
                    $currentStatus
                ]
                ?? [];

            if (
                $currentStatus
                === $newStatus
            ) {
                set_flash(
                    'success',
                    'Booking '
                    . (string) $bookingToUpdate[
                        'booking_code'
                    ]
                    . ' is already marked as '
                    . admin_booking_status_label(
                        $newStatus
                    )
                    . '.'
                );

                redirect(
                    '/admin/bookings.php?booking_id='
                    . $bookingId
                );
            }

            if (
                !in_array(
                    $newStatus,
                    $allowedNextStatuses,
                    true
                )
            ) {
                $errors[] =
                    'This status change is not allowed. '
                    . admin_booking_status_label(
                        $currentStatus
                    )
                    . ' bookings cannot be changed directly to '
                    . admin_booking_status_label(
                        $newStatus
                    )
                    . '.';
            } else {
                try {
                    $connection
                        ->beginTransaction();

                    $updateStatement =
                        $connection->prepare(
                            'UPDATE bookings
                             SET booking_status = ?
                             WHERE id = ?
                             AND booking_status = ?'
                        );

                    $updateStatement->execute([
                        $newStatus,
                        $bookingId,
                        $currentStatus,
                    ]);

                    if (
                        $updateStatement
                            ->rowCount()
                        !== 1
                    ) {
                        throw new RuntimeException(
                            'The booking status changed before your update was saved.'
                        );
                    }

                    admin_create_customer_booking_notification(
                        $connection,
                        (int) $bookingToUpdate[
                            'customer_id'
                        ],
                        $adminId,
                        $bookingId,
                        (string) $bookingToUpdate[
                            'booking_code'
                        ],
                        $newStatus
                    );

                    $connection->commit();

                    set_flash(
                        'success',
                        'Booking '
                        . (string) $bookingToUpdate[
                            'booking_code'
                        ]
                        . ' was updated to '
                        . admin_booking_status_label(
                            $newStatus
                        )
                        . '.'
                    );

                    redirect(
                        '/admin/bookings.php?booking_id='
                        . $bookingId
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

                    $errors[] = APP_DEBUG
                        ? 'Booking status could not be updated: '
                            . $exception
                                ->getMessage()
                        : 'Booking status could not be updated. Please try again.';
                }
            }
        }
    }
}

$search = trim(
    (string) (
        $_GET['search'] ?? ''
    )
);

if (mb_strlen($search) > 100) {
    $search = mb_substr(
        $search,
        0,
        100
    );
}

$statusFilter = strtolower(
    trim(
        (string) (
            $_GET['status'] ?? 'all'
        )
    )
);

if (
    $statusFilter !== 'all'
    && !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {
    $statusFilter = 'all';
}

$dateFrom = trim(
    (string) (
        $_GET['date_from'] ?? ''
    )
);

$dateTo = trim(
    (string) (
        $_GET['date_to'] ?? ''
    )
);

if (
    !admin_filter_date_is_valid(
        $dateFrom
    )
) {
    $dateFrom = '';
}

if (
    !admin_filter_date_is_valid(
        $dateTo
    )
) {
    $dateTo = '';
}

if (
    $dateFrom !== ''
    && $dateTo !== ''
    && $dateFrom > $dateTo
) {
    [
        $dateFrom,
        $dateTo,
    ] = [
        $dateTo,
        $dateFrom,
    ];
}

$requestedBookingId = max(
    0,
    (int) (
        $_GET['booking_id'] ?? 0
    )
);

$statistics = $connection
    ->query(
        "SELECT
            COUNT(*) AS total_bookings,

            COALESCE(
                SUM(
                    booking_status = 'pending'
                ),
                0
            ) AS pending_bookings,

            COALESCE(
                SUM(
                    booking_status = 'confirmed'
                ),
                0
            ) AS confirmed_bookings,

            COALESCE(
                SUM(
                    booking_status = 'in_progress'
                ),
                0
            ) AS progress_bookings,

            COALESCE(
                SUM(
                    booking_status = 'completed'
                ),
                0
            ) AS completed_bookings

         FROM bookings"
    )
    ->fetch();

$totalBookings = (int) (
    $statistics['total_bookings']
    ?? 0
);

$pendingBookings = (int) (
    $statistics['pending_bookings']
    ?? 0
);

$confirmedBookings = (int) (
    $statistics['confirmed_bookings']
    ?? 0
);

$progressBookings = (int) (
    $statistics['progress_bookings']
    ?? 0
);

$completedBookings = (int) (
    $statistics['completed_bookings']
    ?? 0
);

$bookingQuery =
    'SELECT
        bookings.id,
        bookings.booking_code,
        bookings.customer_id,
        bookings.package_id,
        bookings.venue_id,
        bookings.event_type,
        bookings.event_date,
        bookings.event_time,
        bookings.guest_count,
        bookings.customer_address,
        bookings.special_instructions,
        bookings.subtotal,
        bookings.total_amount,
        bookings.booking_status,
        bookings.created_by,
        bookings.created_at,

        customers.full_name
            AS customer_name,

        customers.email
            AS customer_email,

        customers.phone
            AS customer_phone,

        packages.name
            AS package_name,

        venues.name
            AS venue_name,

        venues.location
            AS venue_location,

        creators.full_name
            AS created_by_name,

        creators.role
            AS created_by_role

     FROM bookings

     LEFT JOIN users AS customers
        ON customers.id =
            bookings.customer_id

     LEFT JOIN packages
        ON packages.id =
            bookings.package_id

     LEFT JOIN venues
        ON venues.id =
            bookings.venue_id

     LEFT JOIN users AS creators
        ON creators.id =
            bookings.created_by

     WHERE 1 = 1';

$bookingParameters = [];

if ($statusFilter !== 'all') {
    $bookingQuery .=
        ' AND bookings.booking_status = ?';

    $bookingParameters[] =
        $statusFilter;
}

if ($dateFrom !== '') {
    $bookingQuery .=
        ' AND bookings.event_date >= ?';

    $bookingParameters[] =
        $dateFrom;
}

if ($dateTo !== '') {
    $bookingQuery .=
        ' AND bookings.event_date <= ?';

    $bookingParameters[] =
        $dateTo;
}

if ($search !== '') {
    $bookingQuery .=
        ' AND (
            bookings.booking_code LIKE ?
            OR bookings.event_type LIKE ?
            OR customers.full_name LIKE ?
            OR customers.email LIKE ?
            OR customers.phone LIKE ?
            OR packages.name LIKE ?
            OR venues.name LIKE ?
            OR venues.location LIKE ?
        )';

    $searchValue =
        '%' . $search . '%';

    for (
        $index = 0;
        $index < 8;
        $index++
    ) {
        $bookingParameters[] =
            $searchValue;
    }
}

$bookingQuery .=
    " ORDER BY
        CASE bookings.booking_status
            WHEN 'pending' THEN 1
            WHEN 'confirmed' THEN 2
            WHEN 'in_progress' THEN 3
            WHEN 'completed' THEN 4
            WHEN 'cancelled' THEN 5
            ELSE 6
        END ASC,

        CASE
            WHEN bookings.event_date >= CURDATE()
            THEN bookings.event_date
        END ASC,

        bookings.created_at DESC";

$bookingStatement =
    $connection->prepare(
        $bookingQuery
    );

$bookingStatement->execute(
    $bookingParameters
);

$bookings =
    $bookingStatement->fetchAll();

$servicesByBooking = [];
$serviceTotalsByBooking = [];

$bookingIds = array_map(
    static fn (
        array $booking
    ): int =>
        (int) $booking['id'],
    $bookings
);

if ($bookingIds !== []) {
    $bookingPlaceholders = implode(
        ',',
        array_fill(
            0,
            count($bookingIds),
            '?'
        )
    );

    $servicesStatement =
        $connection->prepare(
            "SELECT
                booking_services.booking_id,
                booking_services.quantity,
                booking_services.price,
                services.name

             FROM booking_services

             LEFT JOIN services
                ON services.id =
                    booking_services.service_id

             WHERE booking_services.booking_id
             IN ($bookingPlaceholders)

             ORDER BY
                booking_services.booking_id ASC,
                services.name ASC"
        );

    $servicesStatement->execute(
        $bookingIds
    );

    $bookingServices =
        $servicesStatement->fetchAll();

    foreach (
        $bookingServices
        as $service
    ) {
        $bookingId =
            (int) $service['booking_id'];

        $quantity = max(
            1,
            (int) (
                $service['quantity']
                ?? 1
            )
        );

        $price = (float) (
            $service['price']
            ?? 0
        );

        if (
            !isset(
                $servicesByBooking[
                    $bookingId
                ]
            )
        ) {
            $servicesByBooking[
                $bookingId
            ] = [];

            $serviceTotalsByBooking[
                $bookingId
            ] = 0.0;
        }

        $servicesByBooking[
            $bookingId
        ][] = [
            'name' => trim(
                (string) (
                    $service['name']
                    ?? 'Additional Service'
                )
            ),
            'quantity' => $quantity,
            'price' => $price,
        ];

        $serviceTotalsByBooking[
            $bookingId
        ] += $price * $quantity;
    }
}

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
        Manage Bookings | <?= e(APP_NAME) ?>
    </title>

    <?php
    require __DIR__
        . '/../includes/pwa_head.php';
    ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/admin_bookings.css'
            )
        ) ?>"
    >
</head>

<body class="admin-bookings-page">

    <aside
        class="admin-bookings-sidebar"
        id="adminBookingsSidebar"
    >

        <div class="admin-bookings-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="admin-bookings-profile">

            <img
                src="<?= e($adminImage) ?>"
                alt="Administrator profile"
            >

            <h2>
                <?= e(
                    $admin['full_name']
                ) ?>
            </h2>

            <p>System Administrator</p>

            <div class="admin-bookings-online">
                ● Online
            </div>

        </div>

        <nav class="admin-bookings-menu">

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
                class="active"
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
                class="admin-bookings-logout"
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
        class="admin-bookings-sidebar-overlay"
        id="adminBookingsSidebarOverlay"
    ></div>

    <main class="admin-bookings-main">

        <header class="admin-bookings-topbar">

            <div class="admin-bookings-topbar-left">

                <button
                    class="admin-bookings-menu-button"
                    id="adminBookingsMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="admin-bookings-heading">

                    <h1>Manage Bookings</h1>

                    <p>
                        Review every customer booking and
                        update its current status.
                    </p>

                </div>

            </div>

            <div class="admin-bookings-topbar-right">

                <div class="admin-bookings-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="admin-bookings-home-link"
                    href="<?= e(
                        url('/index.php')
                    ) ?>"
                    aria-label="Open public website"
                >
                    <i class="fa-solid fa-globe"></i>
                </a>

                <a
                    href="<?= e(
                        url(
                            '/admin/profile.php'
                        )
                    ) ?>"
                >
                    <img
                        class="admin-bookings-profile-image"
                        src="<?= e($adminImage) ?>"
                        alt="Administrator profile"
                    >
                </a>

            </div>

        </header>

        <?php if ($flash): ?>

            <div
                class="admin-bookings-alert <?= $flash[
                    'type'
                ] === 'success'
                    ? 'success'
                    : 'danger' ?>"
            >
                <?= e(
                    $flash['message']
                ) ?>
            </div>

        <?php endif; ?>

        <?php if ($errors !== []): ?>

            <div class="admin-bookings-alert danger">

                <ul>
                    <?php foreach (
                        $errors as $error
                    ): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>

            </div>

        <?php endif; ?>

        <section class="admin-bookings-summary">

            <article class="admin-bookings-summary-card">

                <div
                    class="admin-bookings-summary-icon total"
                >
                    <i class="fa-solid fa-calendar-days"></i>
                </div>

                <div>
                    <h4>Total Bookings</h4>

                    <h2>
                        <?= e(
                            (string) $totalBookings
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="admin-bookings-summary-card">

                <div
                    class="admin-bookings-summary-icon pending"
                >
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div>
                    <h4>Pending</h4>

                    <h2>
                        <?= e(
                            (string) $pendingBookings
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="admin-bookings-summary-card">

                <div
                    class="admin-bookings-summary-icon confirmed"
                >
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div>
                    <h4>Confirmed</h4>

                    <h2>
                        <?= e(
                            (string) $confirmedBookings
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="admin-bookings-summary-card">

                <div
                    class="admin-bookings-summary-icon progress"
                >
                    <i class="fa-solid fa-spinner"></i>
                </div>

                <div>
                    <h4>In Progress</h4>

                    <h2>
                        <?= e(
                            (string) $progressBookings
                        ) ?>
                    </h2>
                </div>

            </article>

            <article class="admin-bookings-summary-card">

                <div
                    class="admin-bookings-summary-icon completed"
                >
                    <i class="fa-solid fa-trophy"></i>
                </div>

                <div>
                    <h4>Completed</h4>

                    <h2>
                        <?= e(
                            (string) $completedBookings
                        ) ?>
                    </h2>
                </div>

            </article>

        </section>

        <section class="admin-bookings-filter-box">

            <form
                class="admin-bookings-filter-form"
                method="get"
            >

                <div class="admin-bookings-filter-field">

                    <label for="search">
                        Search Bookings
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Reference, customer, package or venue"
                    >

                </div>

                <div class="admin-bookings-filter-field">

                    <label for="status">
                        Booking Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="all"
                            <?= $statusFilter === 'all'
                                ? 'selected'
                                : '' ?>
                        >
                            All Statuses
                        </option>

                        <?php foreach (
                            $allowedStatuses
                            as $status
                        ): ?>

                            <option
                                value="<?= e(
                                    $status
                                ) ?>"
                                <?= $statusFilter
                                    === $status
                                        ? 'selected'
                                        : '' ?>
                            >
                                <?= e(
                                    admin_booking_status_label(
                                        $status
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="admin-bookings-filter-field">

                    <label for="date_from">
                        Event Date From
                    </label>

                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="<?= e($dateFrom) ?>"
                    >

                </div>

                <div class="admin-bookings-filter-field">

                    <label for="date_to">
                        Event Date To
                    </label>

                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="<?= e($dateTo) ?>"
                    >

                </div>

                <button
                    class="admin-bookings-filter-button"
                    type="submit"
                >
                    Apply Filter
                </button>

                <a
                    class="admin-bookings-clear-button"
                    href="<?= e(
                        url(
                            '/admin/bookings.php'
                        )
                    ) ?>"
                >
                    Clear
                </a>

            </form>

        </section>

        <section class="admin-bookings-table-box">

            <div class="admin-bookings-table-heading">

                <div>
                    <h2>Customer Bookings</h2>

                    <p>
                        <?= e(
                            number_format(
                                count($bookings)
                            )
                        ) ?>
                        booking record(s) currently shown.
                    </p>
                </div>

                <a
                    class="admin-bookings-create-link"
                    href="<?= e(
                        url('/index.php')
                    ) ?>"
                >
                    <i class="fa-solid fa-globe"></i>
                    Open Website
                </a>

            </div>

            <?php if ($bookings === []): ?>

                <div class="admin-bookings-empty">

                    <i class="fa-regular fa-calendar-xmark"></i>

                    <h3>No bookings found</h3>

                    <p>
                        No booking matches the selected
                        search, status or date filters.
                    </p>

                    <a
                        href="<?= e(
                            url(
                                '/admin/bookings.php'
                            )
                        ) ?>"
                    >
                        View All Bookings
                    </a>

                </div>

            <?php else: ?>

                <div class="admin-bookings-table-wrapper">

                    <table class="admin-bookings-table">

                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Customer</th>
                                <th>Event</th>
                                <th>Package</th>
                                <th>Venue</th>
                                <th>Guests</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach (
                                $bookings
                                as $booking
                            ): ?>
                                <?php
                                $bookingId =
                                    (int) $booking['id'];

                                $bookingStatus =
                                    (string) (
                                        $booking[
                                            'booking_status'
                                        ]
                                        ?? 'pending'
                                    );

                                $customerName = trim(
                                    (string) (
                                        $booking[
                                            'customer_name'
                                        ]
                                        ?? ''
                                    )
                                );

                                if (
                                    $customerName === ''
                                ) {
                                    $customerName =
                                        'Deleted Customer';
                                }

                                $customerEmail = trim(
                                    (string) (
                                        $booking[
                                            'customer_email'
                                        ]
                                        ?? ''
                                    )
                                );

                                $customerPhone = trim(
                                    (string) (
                                        $booking[
                                            'customer_phone'
                                        ]
                                        ?? ''
                                    )
                                );

                                $eventType = trim(
                                    (string) (
                                        $booking[
                                            'event_type'
                                        ]
                                        ?? ''
                                    )
                                );

                                if ($eventType === '') {
                                    $eventType =
                                        'Wedding Event';
                                }

                                $packageName = trim(
                                    (string) (
                                        $booking[
                                            'package_name'
                                        ]
                                        ?? ''
                                    )
                                );

                                if (
                                    $packageName === ''
                                ) {
                                    $packageName =
                                        'Package unavailable';
                                }

                                $venueName = trim(
                                    (string) (
                                        $booking[
                                            'venue_name'
                                        ]
                                        ?? ''
                                    )
                                );

                                if ($venueName === '') {
                                    $venueName =
                                        'Venue unavailable';
                                }

                                $venueLocation = trim(
                                    (string) (
                                        $booking[
                                            'venue_location'
                                        ]
                                        ?? ''
                                    )
                                );

                                $customerAddress = trim(
                                    (string) (
                                        $booking[
                                            'customer_address'
                                        ]
                                        ?? ''
                                    )
                                );

                                $instructions = trim(
                                    (string) (
                                        $booking[
                                            'special_instructions'
                                        ]
                                        ?? ''
                                    )
                                );

                                $createdByName = trim(
                                    (string) (
                                        $booking[
                                            'created_by_name'
                                        ]
                                        ?? ''
                                    )
                                );

                                if (
                                    $createdByName === ''
                                ) {
                                    $createdByName =
                                        'System';
                                }

                                $createdByRole = trim(
                                    (string) (
                                        $booking[
                                            'created_by_role'
                                        ]
                                        ?? ''
                                    )
                                );

                                $bookingServices =
                                    $servicesByBooking[
                                        $bookingId
                                    ]
                                    ?? [];

                                $serviceTotal =
                                    (float) (
                                        $serviceTotalsByBooking[
                                            $bookingId
                                        ]
                                        ?? 0
                                    );

                                $subtotal = (float) (
                                    $booking['subtotal']
                                    ?? 0
                                );

                                $packageVenueTotal = max(
                                    0,
                                    $subtotal
                                    - $serviceTotal
                                );

                                $nextStatuses =
                                    $statusTransitions[
                                        $bookingStatus
                                    ]
                                    ?? [];
                                ?>

                                <tr>

                                    <td>
                                        <span
                                            class="admin-bookings-reference"
                                        >
                                            <?= e(
                                                (string) $booking[
                                                    'booking_code'
                                                ]
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div
                                            class="admin-bookings-customer"
                                        >

                                            <strong>
                                                <?= e(
                                                    $customerName
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= e(
                                                    $customerEmail !== ''
                                                        ? $customerEmail
                                                        : 'Email unavailable'
                                                ) ?>
                                            </span>

                                        </div>
                                    </td>

                                    <td>
                                        <div
                                            class="admin-bookings-event"
                                        >

                                            <strong>
                                                <?= e(
                                                    $eventType
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= e(
                                                    admin_booking_date(
                                                        $booking[
                                                            'event_date'
                                                        ]
                                                    )
                                                ) ?>

                                                ·

                                                <?= e(
                                                    admin_booking_time(
                                                        $booking[
                                                            'event_time'
                                                        ]
                                                        ?? null
                                                    )
                                                ) ?>
                                            </span>

                                        </div>
                                    </td>

                                    <td>
                                        <?= e(
                                            $packageName
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            $venueName
                                        ) ?>

                                        <?php if (
                                            $venueLocation !== ''
                                        ): ?>

                                            <br>

                                            <small>
                                                <?= e(
                                                    $venueLocation
                                                ) ?>
                                            </small>

                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            number_format(
                                                (int) (
                                                    $booking[
                                                        'guest_count'
                                                    ]
                                                    ?? 0
                                                )
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <span
                                            class="admin-bookings-amount"
                                        >
                                            Rs.
                                            <?= e(
                                                number_format(
                                                    (float) (
                                                        $booking[
                                                            'total_amount'
                                                        ]
                                                        ?? 0
                                                    ),
                                                    0
                                                )
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span
                                            class="admin-bookings-status <?= e(
                                                admin_booking_status_class(
                                                    $bookingStatus
                                                )
                                            ) ?>"
                                        >
                                            <?= e(
                                                admin_booking_status_label(
                                                    $bookingStatus
                                                )
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div
                                            class="admin-bookings-actions"
                                        >

                                            <?php if (
                                                $nextStatuses
                                                !== []
                                            ): ?>

                                                <form
                                                    class="admin-bookings-status-form"
                                                    method="post"
                                                >
                                                    <?= csrf_field() ?>

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="update_status"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="booking_id"
                                                        value="<?= e(
                                                            (string) $bookingId
                                                        ) ?>"
                                                    >

                                                    <select
                                                        name="booking_status"
                                                        aria-label="Next booking status"
                                                        required
                                                    >

                                                        <?php foreach (
                                                            $nextStatuses
                                                            as $status
                                                        ): ?>

                                                            <option
                                                                value="<?= e(
                                                                    $status
                                                                ) ?>"
                                                            >
                                                                <?= e(
                                                                    admin_booking_status_label(
                                                                        $status
                                                                    )
                                                                ) ?>
                                                            </option>

                                                        <?php endforeach; ?>

                                                    </select>

                                                    <button
                                                        class="admin-bookings-update-button"
                                                        type="submit"
                                                    >
                                                        Update
                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <span
                                                    class="admin-bookings-final-status"
                                                >
                                                    <i class="fa-solid fa-lock"></i>
                                                    Final Status
                                                </span>

                                            <?php endif; ?>

                                            <button
                                                class="admin-bookings-view-button"
                                                type="button"
                                                data-admin-booking-details

                                                data-booking-id="<?= e(
                                                    (string) $bookingId
                                                ) ?>"

                                                data-code="<?= e(
                                                    (string) $booking[
                                                        'booking_code'
                                                    ]
                                                ) ?>"

                                                data-event-type="<?= e(
                                                    $eventType
                                                ) ?>"

                                                data-event-date="<?= e(
                                                    admin_booking_date(
                                                        $booking[
                                                            'event_date'
                                                        ]
                                                    )
                                                ) ?>"

                                                data-event-time="<?= e(
                                                    admin_booking_time(
                                                        $booking[
                                                            'event_time'
                                                        ]
                                                        ?? null
                                                    )
                                                ) ?>"

                                                data-customer-name="<?= e(
                                                    $customerName
                                                ) ?>"

                                                data-customer-email="<?= e(
                                                    $customerEmail !== ''
                                                        ? $customerEmail
                                                        : 'Email unavailable'
                                                ) ?>"

                                                data-customer-phone="<?= e(
                                                    $customerPhone !== ''
                                                        ? $customerPhone
                                                        : 'Phone unavailable'
                                                ) ?>"

                                                data-package="<?= e(
                                                    $packageName
                                                ) ?>"

                                                data-venue="<?= e(
                                                    $venueName
                                                    . (
                                                        $venueLocation !== ''
                                                            ? ' — '
                                                                . $venueLocation
                                                            : ''
                                                    )
                                                ) ?>"

                                                data-guests="<?= e(
                                                    number_format(
                                                        (int) (
                                                            $booking[
                                                                'guest_count'
                                                            ]
                                                            ?? 0
                                                        )
                                                    )
                                                ) ?>"

                                                data-address="<?= e(
                                                    $customerAddress !== ''
                                                        ? $customerAddress
                                                        : 'Not provided'
                                                ) ?>"

                                                data-instructions="<?= e(
                                                    $instructions !== ''
                                                        ? $instructions
                                                        : 'No special instructions.'
                                                ) ?>"

                                                data-status="<?= e(
                                                    $bookingStatus
                                                ) ?>"

                                                data-status-label="<?= e(
                                                    admin_booking_status_label(
                                                        $bookingStatus
                                                    )
                                                ) ?>"

                                                data-next-statuses="<?= e(
                                                    json_encode(
                                                        $nextStatuses,
                                                        JSON_UNESCAPED_SLASHES
                                                    )
                                                ) ?>"

                                                data-created="<?= e(
                                                    date(
                                                        'd F Y, h:i A',
                                                        strtotime(
                                                            (string) $booking[
                                                                'created_at'
                                                            ]
                                                        )
                                                    )
                                                ) ?>"

                                                data-created-by="<?= e(
                                                    $createdByName
                                                    . (
                                                        $createdByRole !== ''
                                                            ? ' ('
                                                                . ucwords(
                                                                    str_replace(
                                                                        '_',
                                                                        ' ',
                                                                        $createdByRole
                                                                    )
                                                                )
                                                                . ')'
                                                            : ''
                                                    )
                                                ) ?>"

                                                data-package-venue-total="<?= e(
                                                    number_format(
                                                        $packageVenueTotal,
                                                        0
                                                    )
                                                ) ?>"

                                                data-services-total="<?= e(
                                                    number_format(
                                                        $serviceTotal,
                                                        0
                                                    )
                                                ) ?>"

                                                data-subtotal="<?= e(
                                                    number_format(
                                                        $subtotal,
                                                        0
                                                    )
                                                ) ?>"

                                                data-total="<?= e(
                                                    number_format(
                                                        (float) (
                                                            $booking[
                                                                'total_amount'
                                                            ]
                                                            ?? 0
                                                        ),
                                                        0
                                                    )
                                                ) ?>"

                                                data-services="<?= e(
                                                    json_encode(
                                                        $bookingServices,
                                                        JSON_UNESCAPED_UNICODE
                                                        | JSON_UNESCAPED_SLASHES
                                                    )
                                                ) ?>"
                                            >
                                                Details
                                            </button>

                                        </div>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

        <footer class="admin-bookings-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <div
        class="admin-bookings-modal"
        id="adminBookingModal"
    >

        <div class="admin-bookings-modal-content">

            <button
                class="admin-bookings-modal-close"
                id="adminBookingModalClose"
                type="button"
                aria-label="Close booking details"
            >
                &times;
            </button>

            <div class="admin-bookings-modal-header">

                <h2 id="adminModalEventType">
                    Wedding Event
                </h2>

                <div
                    class="admin-bookings-modal-reference"
                    id="adminModalBookingCode"
                ></div>

            </div>

            <div
                class="admin-bookings-modal-status-row"
            >

                <span>
                    Current Booking Status
                </span>

                <span
                    class="admin-bookings-status pending"
                    id="adminModalBookingStatus"
                >
                    Pending
                </span>

            </div>

            <div class="admin-bookings-modal-grid">

                <div class="admin-bookings-modal-item">
                    <strong>Customer Name</strong>
                    <span id="adminModalCustomerName"></span>
                </div>

                <div class="admin-bookings-modal-item">
                    <strong>Customer Email</strong>
                    <span id="adminModalCustomerEmail"></span>
                </div>

                <div class="admin-bookings-modal-item">
                    <strong>Customer Phone</strong>
                    <span id="adminModalCustomerPhone"></span>
                </div>

                <div class="admin-bookings-modal-item">
                    <strong>Event Date</strong>
                    <span id="adminModalEventDate"></span>
                </div>

                <div class="admin-bookings-modal-item">
                    <strong>Event Time</strong>
                    <span id="adminModalEventTime"></span>
                </div>

                <div class="admin-bookings-modal-item">
                    <strong>Total Guests</strong>
                    <span id="adminModalGuests"></span>
                </div>

                <div class="admin-bookings-modal-item">
                    <strong>Package</strong>
                    <span id="adminModalPackage"></span>
                </div>

                <div class="admin-bookings-modal-item">
                    <strong>Venue</strong>
                    <span id="adminModalVenue"></span>
                </div>

                <div class="admin-bookings-modal-item">
                    <strong>Created By</strong>
                    <span id="adminModalCreatedBy"></span>
                </div>

                <div class="admin-bookings-modal-item">
                    <strong>Created On</strong>
                    <span id="adminModalCreated"></span>
                </div>

            </div>

            <div class="admin-bookings-modal-section">

                <h3>Customer Address</h3>

                <div
                    class="admin-bookings-modal-text"
                    id="adminModalAddress"
                ></div>

            </div>

            <div class="admin-bookings-modal-section">

                <h3>Special Instructions</h3>

                <div
                    class="admin-bookings-modal-text"
                    id="adminModalInstructions"
                ></div>

            </div>

            <div class="admin-bookings-modal-section">

                <h3>Additional Services</h3>

                <ul
                    class="admin-bookings-services-list"
                    id="adminModalServices"
                ></ul>

            </div>

            <div class="admin-bookings-modal-price-box">

                <div class="admin-bookings-modal-price-row">
                    <span>Package + Venue</span>

                    <span id="adminModalPackageVenueTotal">
                        Rs. 0
                    </span>
                </div>

                <div class="admin-bookings-modal-price-row">
                    <span>Additional Services</span>

                    <span id="adminModalServicesTotal">
                        Rs. 0
                    </span>
                </div>

                <div class="admin-bookings-modal-price-row">
                    <span>Subtotal</span>

                    <span id="adminModalSubtotal">
                        Rs. 0
                    </span>
                </div>

                <div class="admin-bookings-modal-price-row total">
                    <span>Total Amount</span>

                    <span id="adminModalTotal">
                        Rs. 0
                    </span>
                </div>

            </div>

            <div
                class="admin-bookings-modal-update"
                id="adminModalUpdateSection"
            >

                <h3>Update Booking Status</h3>

                <form method="post">
                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="update_status"
                    >

                    <input
                        type="hidden"
                        name="booking_id"
                        id="adminModalBookingId"
                        value=""
                    >

                    <select
                        name="booking_status"
                        id="adminModalStatusSelect"
                        required
                    ></select>

                    <button type="submit">
                        Save Status
                    </button>
                </form>

            </div>

            <div
                class="admin-bookings-modal-final"
                id="adminModalFinalNote"
                hidden
            >
                <i class="fa-solid fa-lock"></i>

                This booking has reached a final status
                and cannot be changed again.
            </div>

        </div>

    </div>

    <script>
        const adminBookingsSidebar =
            document.getElementById(
                "adminBookingsSidebar"
            );

        const adminBookingsSidebarOverlay =
            document.getElementById(
                "adminBookingsSidebarOverlay"
            );

        const adminBookingsMenuButton =
            document.getElementById(
                "adminBookingsMenuButton"
            );

        function closeAdminBookingsSidebar() {
            adminBookingsSidebar.classList.remove(
                "open"
            );

            adminBookingsSidebarOverlay.classList.remove(
                "open"
            );
        }

        adminBookingsMenuButton.addEventListener(
            "click",
            function () {
                adminBookingsSidebar.classList.toggle(
                    "open"
                );

                adminBookingsSidebarOverlay.classList.toggle(
                    "open"
                );
            }
        );

        adminBookingsSidebarOverlay.addEventListener(
            "click",
            closeAdminBookingsSidebar
        );

        const bookingModal =
            document.getElementById(
                "adminBookingModal"
            );

        const bookingModalClose =
            document.getElementById(
                "adminBookingModalClose"
            );

        const modalUpdateSection =
            document.getElementById(
                "adminModalUpdateSection"
            );

        const modalFinalNote =
            document.getElementById(
                "adminModalFinalNote"
            );

        const modalStatusSelect =
            document.getElementById(
                "adminModalStatusSelect"
            );

        function formatBookingMoney(value) {
            return "Rs. " + (value || "0");
        }

        function openAdminBookingModal(button) {
            document.getElementById(
                "adminModalEventType"
            ).textContent =
                button.dataset.eventType;

            document.getElementById(
                "adminModalBookingCode"
            ).textContent =
                "Booking Reference: "
                + button.dataset.code;

            document.getElementById(
                "adminModalCustomerName"
            ).textContent =
                button.dataset.customerName;

            document.getElementById(
                "adminModalCustomerEmail"
            ).textContent =
                button.dataset.customerEmail;

            document.getElementById(
                "adminModalCustomerPhone"
            ).textContent =
                button.dataset.customerPhone;

            document.getElementById(
                "adminModalEventDate"
            ).textContent =
                button.dataset.eventDate;

            document.getElementById(
                "adminModalEventTime"
            ).textContent =
                button.dataset.eventTime;

            document.getElementById(
                "adminModalGuests"
            ).textContent =
                button.dataset.guests
                + " guests";

            document.getElementById(
                "adminModalPackage"
            ).textContent =
                button.dataset.package;

            document.getElementById(
                "adminModalVenue"
            ).textContent =
                button.dataset.venue;

            document.getElementById(
                "adminModalCreatedBy"
            ).textContent =
                button.dataset.createdBy;

            document.getElementById(
                "adminModalCreated"
            ).textContent =
                button.dataset.created;

            document.getElementById(
                "adminModalAddress"
            ).textContent =
                button.dataset.address;

            document.getElementById(
                "adminModalInstructions"
            ).textContent =
                button.dataset.instructions;

            document.getElementById(
                "adminModalPackageVenueTotal"
            ).textContent =
                formatBookingMoney(
                    button.dataset
                        .packageVenueTotal
                );

            document.getElementById(
                "adminModalServicesTotal"
            ).textContent =
                formatBookingMoney(
                    button.dataset
                        .servicesTotal
                );

            document.getElementById(
                "adminModalSubtotal"
            ).textContent =
                formatBookingMoney(
                    button.dataset.subtotal
                );

            document.getElementById(
                "adminModalTotal"
            ).textContent =
                formatBookingMoney(
                    button.dataset.total
                );

            const statusElement =
                document.getElementById(
                    "adminModalBookingStatus"
                );

            statusElement.textContent =
                button.dataset.statusLabel;

            statusElement.className =
                "admin-bookings-status "
                + (
                    button.dataset.status
                    === "in_progress"
                        ? "in-progress"
                        : button.dataset.status
                );

            document.getElementById(
                "adminModalBookingId"
            ).value =
                button.dataset.bookingId;

            let nextStatuses = [];

            try {
                nextStatuses = JSON.parse(
                    button.dataset.nextStatuses
                    || "[]"
                );
            } catch (error) {
                nextStatuses = [];
            }

            modalStatusSelect.innerHTML = "";

            if (nextStatuses.length === 0) {
                modalUpdateSection.hidden =
                    true;

                modalFinalNote.hidden =
                    false;
            } else {
                nextStatuses.forEach(
                    function (status) {
                        const option =
                            document.createElement(
                                "option"
                            );

                        option.value = status;

                        option.textContent =
                            status
                            === "in_progress"
                                ? "In Progress"
                                : status
                                    .replaceAll(
                                        "_",
                                        " "
                                    )
                                    .replace(
                                        /\b\w/g,
                                        function (
                                            letter
                                        ) {
                                            return letter
                                                .toUpperCase();
                                        }
                                    );

                        modalStatusSelect
                            .appendChild(
                                option
                            );
                    }
                );

                modalUpdateSection.hidden =
                    false;

                modalFinalNote.hidden =
                    true;
            }

            const servicesList =
                document.getElementById(
                    "adminModalServices"
                );

            servicesList.innerHTML = "";

            let services = [];

            try {
                services = JSON.parse(
                    button.dataset.services
                    || "[]"
                );
            } catch (error) {
                services = [];
            }

            if (services.length === 0) {
                const item =
                    document.createElement(
                        "li"
                    );

                const text =
                    document.createElement(
                        "span"
                    );

                text.textContent =
                    "No additional services selected.";

                item.appendChild(text);

                servicesList.appendChild(
                    item
                );
            } else {
                services.forEach(
                    function (service) {
                        const item =
                            document.createElement(
                                "li"
                            );

                        const name =
                            document.createElement(
                                "span"
                            );

                        const price =
                            document.createElement(
                                "span"
                            );

                        const quantity = Number(
                            service.quantity
                            || 1
                        );

                        name.textContent =
                            service.name
                            + (
                                quantity > 1
                                    ? " × "
                                        + quantity
                                    : ""
                            );

                        price.textContent =
                            "Rs. "
                            + Number(
                                Number(
                                    service.price
                                    || 0
                                )
                                * quantity
                            ).toLocaleString(
                                "en-PK",
                                {
                                    maximumFractionDigits:
                                        0
                                }
                            );

                        item.appendChild(name);
                        item.appendChild(price);

                        servicesList
                            .appendChild(item);
                    }
                );
            }

            bookingModal.classList.add(
                "open"
            );

            document.body.style.overflow =
                "hidden";
        }

        document
            .querySelectorAll(
                "[data-admin-booking-details]"
            )
            .forEach(
                function (button) {
                    button.addEventListener(
                        "click",
                        function () {
                            openAdminBookingModal(
                                button
                            );
                        }
                    );
                }
            );

        function closeAdminBookingModal() {
            bookingModal.classList.remove(
                "open"
            );

            document.body.style.overflow =
                "";
        }

        bookingModalClose.addEventListener(
            "click",
            closeAdminBookingModal
        );

        bookingModal.addEventListener(
            "click",
            function (event) {
                if (
                    event.target
                    === bookingModal
                ) {
                    closeAdminBookingModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (
                    event.key
                    === "Escape"
                ) {
                    closeAdminBookingModal();
                }
            }
        );

        const requestedBookingId =
            "<?= e(
                $requestedBookingId > 0
                    ? (string) $requestedBookingId
                    : ''
            ) ?>";

        if (requestedBookingId !== "") {
            const requestedButton =
                document.querySelector(
                    '[data-booking-id="'
                    + requestedBookingId
                    + '"]'
                );

            if (requestedButton) {
                openAdminBookingModal(
                    requestedButton
                );
            }
        }
    </script>

    <?php
    require __DIR__
        . '/../includes/pwa_scripts.php';
    ?>

</body>
</html>