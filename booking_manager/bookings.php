<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('booking_manager');

$connection = db();
$bookingManagerId = (int) $_SESSION['user_id'];

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

/*
|--------------------------------------------------------------------------
| Load Booking Manager
|--------------------------------------------------------------------------
*/

$managerStatement = $connection->prepare(
    'SELECT
        full_name,
        email,
        profile_image
     FROM users
     WHERE id = ?
     AND role = ?
     LIMIT 1'
);

$managerStatement->execute([
    $bookingManagerId,
    'booking_manager',
]);

$manager = $managerStatement->fetch();

if (!$manager) {
    redirect('/auth/logout.php');
}

$managerImage = !empty($manager['profile_image'])
    ? url(
        '/'
        . ltrim(
            (string) $manager['profile_image'],
            '/'
        )
    )
    : url('/assets/icons/icon-192.png');

/*
|--------------------------------------------------------------------------
| Notification count
|--------------------------------------------------------------------------
*/

$notificationStatement = $connection->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE recipient_id = ?
     AND is_read = 0'
);

$notificationStatement->execute([
    $bookingManagerId,
]);

$unreadNotifications = (int) (
    $notificationStatement->fetchColumn()
);

/*
|--------------------------------------------------------------------------
| Display helpers
|--------------------------------------------------------------------------
*/

function booking_manager_status_label(
    string $status
): string {
    return $status === 'in_progress'
        ? 'In Progress'
        : ucwords(
            str_replace(
                '_',
                ' ',
                $status
            )
        );
}

function booking_manager_status_class(
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

function booking_manager_date(
    mixed $date
): string {
    $timestamp = strtotime(
        (string) $date
    );

    return $timestamp === false
        ? 'Not available'
        : date(
            'd F Y',
            $timestamp
        );
}

function booking_manager_time(
    mixed $time
): string {
    $time = trim(
        (string) $time
    );

    if ($time === '') {
        return 'Not selected';
    }

    $timestamp = strtotime($time);

    return $timestamp === false
        ? $time
        : date(
            'h:i A',
            $timestamp
        );
}

function booking_manager_filter_date_is_valid(
    string $date
): bool {
    if ($date === '') {
        return true;
    }

    $dateObject =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

    $dateErrors =
        DateTimeImmutable::getLastErrors();

    return $dateObject !== false
        && (
            $dateErrors === false
            || (
                $dateErrors['warning_count'] === 0
                && $dateErrors['error_count'] === 0
            )
        )
        && $dateObject->format('Y-m-d')
            === $date;
}

/*
|--------------------------------------------------------------------------
| Create customer notification
|--------------------------------------------------------------------------
|
| The helper checks which notification columns exist so it remains
| compatible with the current notifications table.
|
*/

function booking_manager_create_customer_notification(
    PDO $connection,
    int $customerId,
    int $bookingManagerId,
    int $bookingId,
    string $bookingCode,
    string $newStatus
): void {
    try {
        $columnRows = $connection
            ->query(
                'SHOW COLUMNS FROM notifications'
            )
            ->fetchAll(PDO::FETCH_ASSOC);

        $availableColumns = [];

        foreach ($columnRows as $columnRow) {
            $availableColumns[
                (string) $columnRow['Field']
            ] = true;
        }

        if (
            !isset(
                $availableColumns['recipient_id']
            )
        ) {
            return;
        }

        $statusLabel =
            booking_manager_status_label(
                $newStatus
            );

        $values = [
            'recipient_id' => $customerId,
        ];

        if (
            isset(
                $availableColumns['sender_id']
            )
        ) {
            $values['sender_id'] =
                $bookingManagerId;
        }

        if (
            isset(
                $availableColumns[
                    'recipient_role'
                ]
            )
        ) {
            $values['recipient_role'] =
                'customer';
        }

        if (
            isset(
                $availableColumns['title']
            )
        ) {
            $values['title'] =
                'Booking Status Updated';
        }

        if (
            isset(
                $availableColumns['message']
            )
        ) {
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

        if (
            isset(
                $availableColumns['type']
            )
        ) {
            $values['type'] =
                'booking';
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

        if (
            isset(
                $availableColumns['link']
            )
        ) {
            $values['link'] =
                $notificationLink;
        }

        if (
            isset(
                $availableColumns['url']
            )
        ) {
            $values['url'] =
                $notificationLink;
        }

        if (
            isset(
                $availableColumns['is_read']
            )
        ) {
            $values['is_read'] = 0;
        }

        if (
            isset(
                $availableColumns['created_at']
            )
        ) {
            $values['created_at'] =
                date('Y-m-d H:i:s');
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
        /*
         * Notification failure must not undo
         * a valid booking-status update.
         */
    }
}

/*
|--------------------------------------------------------------------------
| Update booking status
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $action = trim(
        (string) ($_POST['action'] ?? '')
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

    if (
        $action !== 'update_status'
    ) {
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
                ] ?? [];

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
                    . booking_manager_status_label(
                        $newStatus
                    )
                    . '.'
                );

                redirect(
                    '/booking_manager/bookings.php?booking_id='
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
                    . booking_manager_status_label(
                        $currentStatus
                    )
                    . ' bookings cannot be changed directly to '
                    . booking_manager_status_label(
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
                        $updateStatement->rowCount()
                        !== 1
                    ) {
                        throw new RuntimeException(
                            'The booking status changed before your update was saved.'
                        );
                    }

                    booking_manager_create_customer_notification(
                        $connection,
                        (int) $bookingToUpdate[
                            'customer_id'
                        ],
                        $bookingManagerId,
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
                        . booking_manager_status_label(
                            $newStatus
                        )
                        . '.'
                    );

                    redirect(
                        '/booking_manager/bookings.php?booking_id='
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

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim(
    (string) (
        $_GET['search'] ?? ''
    )
);

if (
    mb_strlen($search) > 100
) {
    $search = mb_substr(
        $search,
        0,
        100
    );
}

$statusFilter = strtolower(
    trim(
        (string) (
            $_GET['status']
            ?? 'all'
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
    !booking_manager_filter_date_is_valid(
        $dateFrom
    )
) {
    $dateFrom = '';
}

if (
    !booking_manager_filter_date_is_valid(
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
    [$dateFrom, $dateTo] = [
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

/*
|--------------------------------------------------------------------------
| Booking statistics
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Load bookings
|--------------------------------------------------------------------------
*/

$bookingQuery =
    'SELECT
        bookings.id,
        bookings.booking_code,
        bookings.customer_id,
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

if (
    $statusFilter !== 'all'
) {
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
            WHEN bookings.event_date
                >= CURDATE()
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

/*
|--------------------------------------------------------------------------
| Services for displayed bookings
|--------------------------------------------------------------------------
*/

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
        $bookingServices as $service
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

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/booking_manager_dashboard.css'
            )
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/booking_manager_bookings.css'
            )
        ) ?>"
    >
</head>

<body class="booking-manager-page">

    <aside
        class="booking-sidebar"
        id="bookingSidebar"
    >

        <div class="booking-logo">
            <h1>Wedding</h1>
            <p>Event Planner</p>
        </div>

        <div class="booking-sidebar-profile">

            <img
                src="<?= e($managerImage) ?>"
                alt="Booking Manager profile"
            >

            <h2>
                <?= e(
                    (string) $manager[
                        'full_name'
                    ]
                ) ?>
            </h2>

            <p>Booking Manager</p>

            <div class="booking-online">
                ● Online
            </div>

        </div>

        <nav class="booking-menu">

            <a
                href="<?= e(
                    url(
                        '/booking_manager/dashboard.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-gauge"></i>
                Dashboard
            </a>

            <a
                class="active"
                href="<?= e(
                    url(
                        '/booking_manager/bookings.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-calendar-check"></i>
                Manage Bookings
            </a>

            <a
                href="<?= e(
                    url(
                        '/booking_manager/booking.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-calendar-plus"></i>
                Create Booking
            </a>

            <a
                href="<?= e(
                    url(
                        '/booking_manager/services.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-bell-concierge"></i>
                View Services
            </a>

            <a
                href="<?= e(
                    url(
                        '/booking_manager/gallery.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-images"></i>
                View Gallery
            </a>

            <a
                href="<?= e(
                    url(
                        '/booking_manager/packages.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-gift"></i>
                View Packages
            </a>

            <a
                href="<?= e(
                    url(
                        '/booking_manager/venues.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-hotel"></i>
                View Venues
            </a>

            <a
                href="<?= e(
                    url(
                        '/booking_manager/profile.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-user"></i>
                Manage Profile
            </a>

            <a
                href="<?= e(
                    url(
                        '/booking_manager/notifications.php'
                    )
                ) ?>"
            >
                <i class="fa-solid fa-bell"></i>
                View Notifications
            </a>

            <a
                class="booking-logout"
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
        class="booking-sidebar-overlay"
        id="bookingSidebarOverlay"
    ></div>

    <main class="booking-main">

        <header class="booking-topbar">

            <div class="booking-topbar-left">

                <button
                    class="booking-menu-button"
                    id="bookingMenuButton"
                    type="button"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div class="booking-heading">

                    <h1>Manage Bookings</h1>

                    <p>
                        Manage all customer wedding
                        bookings easily.
                    </p>

                </div>

            </div>

            <div class="booking-topbar-right">

                <div class="booking-date">
                    <?= e(date('d F Y')) ?>
                    <br>
                    <?= e(date('l, h:i A')) ?>
                </div>

                <a
                    class="booking-notification"
                    href="<?= e(
                        url(
                            '/booking_manager/notifications.php'
                        )
                    ) ?>"
                    aria-label="Open notifications"
                >
                    <i class="fa-solid fa-bell"></i>

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
                            '/booking_manager/profile.php'
                        )
                    ) ?>"
                >
                    <img
                        class="booking-profile-image"
                        src="<?= e($managerImage) ?>"
                        alt="Booking Manager profile"
                    >
                </a>

            </div>

        </header>

        <?php if ($flash): ?>

            <div
                class="manager-bookings-alert <?= $flash['type'] === 'success'
                    ? 'success'
                    : 'danger' ?>"
            >
                <?= e($flash['message']) ?>
            </div>

        <?php endif; ?>

        <?php if ($errors !== []): ?>

            <div
                class="manager-bookings-alert danger"
            >
                <ul>
                    <?php foreach (
                        $errors as $error
                    ): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

        <?php endif; ?>

        <section class="manager-bookings-summary">

            <article
                class="manager-bookings-summary-card"
            >
                <div
                    class="manager-bookings-summary-icon total"
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

            <article
                class="manager-bookings-summary-card"
            >
                <div
                    class="manager-bookings-summary-icon pending"
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

            <article
                class="manager-bookings-summary-card"
            >
                <div
                    class="manager-bookings-summary-icon confirmed"
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

            <article
                class="manager-bookings-summary-card"
            >
                <div
                    class="manager-bookings-summary-icon progress"
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

            <article
                class="manager-bookings-summary-card"
            >
                <div
                    class="manager-bookings-summary-icon completed"
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

        <section class="manager-bookings-filter-box">

            <form
                class="manager-bookings-filter-form"
                method="get"
            >

                <div
                    class="manager-bookings-filter-field search"
                >
                    <label for="search">
                        Search Booking
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Reference, customer, event, package or venue"
                    >
                </div>

                <div
                    class="manager-bookings-filter-field"
                >
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
                            $allowedStatuses as $status
                        ): ?>

                            <option
                                value="<?= e($status) ?>"
                                <?= $statusFilter === $status
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e(
                                    booking_manager_status_label(
                                        $status
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>
                    </select>
                </div>

                <div
                    class="manager-bookings-filter-field"
                >
                    <label for="date_from">
                        Date From
                    </label>

                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="<?= e($dateFrom) ?>"
                    >
                </div>

                <div
                    class="manager-bookings-filter-field"
                >
                    <label for="date_to">
                        Date To
                    </label>

                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="<?= e($dateTo) ?>"
                    >
                </div>

                <button
                    class="manager-bookings-filter-button"
                    type="submit"
                >
                    Apply Filter
                </button>

                <a
                    class="manager-bookings-clear-button"
                    href="<?= e(
                        url(
                            '/booking_manager/bookings.php'
                        )
                    ) ?>"
                >
                    Clear
                </a>

            </form>

        </section>

        <section class="manager-bookings-box">

            <div class="manager-bookings-box-top">

                <div>
                    <h2>All Bookings</h2>

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
                    class="manager-bookings-create-button"
                    href="<?= e(
                        url(
                            '/booking_manager/booking.php'
                        )
                    ) ?>"
                >
                    <i class="fa-solid fa-plus"></i>
                    Create Booking
                </a>

            </div>

            <?php if ($bookings === []): ?>

                <div class="manager-bookings-empty">

                    <i
                        class="fa-regular fa-calendar-xmark"
                    ></i>

                    <h3>No bookings found</h3>

                    <p>
                        No booking matches the selected
                        search, status or event-date
                        filters.
                    </p>

                    <a
                        href="<?= e(
                            url(
                                '/booking_manager/bookings.php'
                            )
                        ) ?>"
                    >
                        View All Bookings
                    </a>

                </div>

            <?php else: ?>

                <div class="manager-bookings-grid">

                    <?php foreach (
                        $bookings as $booking
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
                            ] ?? [];

                        $serviceTotal =
                            (float) (
                                $serviceTotalsByBooking[
                                    $bookingId
                                ]
                                ?? 0
                            );

                        $subtotal =
                            (float) (
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
                            ] ?? [];
                        ?>

                        <article
                            class="manager-booking-card"
                        >

                            <div
                                class="manager-booking-tag <?= e(
                                    booking_manager_status_class(
                                        $bookingStatus
                                    )
                                ) ?>"
                            >
                                <?= e(
                                    booking_manager_status_label(
                                        $bookingStatus
                                    )
                                ) ?>
                            </div>

                            <h3>
                                <?= e($customerName) ?>
                            </h3>

                            <h4>
                                <?= e($eventType) ?>
                            </h4>

                            <div
                                class="manager-booking-reference"
                            >
                                <?= e(
                                    (string) $booking[
                                        'booking_code'
                                    ]
                                ) ?>
                            </div>

                            <ul
                                class="manager-booking-information"
                            >
                                <li>
                                    <i
                                        class="fa-solid fa-check"
                                    ></i>

                                    <span>
                                        <?= e(
                                            $venueName
                                        ) ?>

                                        <?php if (
                                            $venueLocation
                                            !== ''
                                        ): ?>

                                            —
                                            <?= e(
                                                $venueLocation
                                            ) ?>

                                        <?php endif; ?>
                                    </span>
                                </li>

                                <li>
                                    <i
                                        class="fa-solid fa-check"
                                    ></i>

                                    <span>
                                        <?= e(
                                            booking_manager_date(
                                                $booking[
                                                    'event_date'
                                                ]
                                            )
                                        ) ?>

                                        at

                                        <?= e(
                                            booking_manager_time(
                                                $booking[
                                                    'event_time'
                                                ]
                                                ?? null
                                            )
                                        ) ?>
                                    </span>
                                </li>

                                <li>
                                    <i
                                        class="fa-solid fa-check"
                                    ></i>

                                    <span>
                                        <?= e(
                                            $packageName
                                        ) ?>
                                    </span>
                                </li>

                                <li>
                                    <i
                                        class="fa-solid fa-check"
                                    ></i>

                                    <span>
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
                                </li>

                                <li>
                                    <i
                                        class="fa-solid fa-check"
                                    ></i>

                                    <span>
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

                                        guests
                                    </span>
                                </li>
                            </ul>

                            <div
                                class="manager-booking-buttons"
                            >

                                <button
                                    class="manager-booking-view-button"
                                    type="button"

                                    data-manager-booking-details

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
                                        booking_manager_date(
                                            $booking[
                                                'event_date'
                                            ]
                                        )
                                    ) ?>"

                                    data-event-time="<?= e(
                                        booking_manager_time(
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
                                            $venueLocation
                                            !== ''
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
                                        booking_manager_status_label(
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
                                            $createdByRole
                                            !== ''
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
                                    View
                                </button>

                                <button
                                    class="manager-booking-update-button"
                                    type="button"

                                    data-open-status-for="<?= e(
                                        (string) $bookingId
                                    ) ?>"

                                    <?= $nextStatuses === []
                                        ? 'disabled'
                                        : '' ?>
                                >
                                    <?= $nextStatuses === []
                                        ? 'Final Status'
                                        : 'Update Status' ?>
                                </button>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <footer class="booking-footer">
            © <?= e((string) $currentYear) ?>
            Wedding Event Planner. All rights reserved.
        </footer>

    </main>

    <div
        class="manager-booking-modal"
        id="managerBookingModal"
    >

        <div
            class="manager-booking-modal-content"
        >

            <button
                class="manager-booking-modal-close"
                id="managerBookingModalClose"
                type="button"
                aria-label="Close booking details"
            >
                &times;
            </button>

            <div
                class="manager-booking-modal-header"
            >

                <h2 id="managerModalEventType">
                    Wedding Event
                </h2>

                <div
                    class="manager-booking-modal-reference"
                    id="managerModalBookingCode"
                ></div>

            </div>

            <div
                class="manager-booking-modal-status-row"
            >

                <span>
                    Current Booking Status
                </span>

                <span
                    class="manager-booking-tag pending"
                    id="managerModalBookingStatus"
                >
                    Pending
                </span>

            </div>

            <div
                class="manager-booking-modal-grid"
            >

                <div
                    class="manager-booking-modal-item"
                >
                    <strong>Customer Name</strong>
                    <span id="managerModalCustomerName"></span>
                </div>

                <div
                    class="manager-booking-modal-item"
                >
                    <strong>Customer Email</strong>
                    <span id="managerModalCustomerEmail"></span>
                </div>

                <div
                    class="manager-booking-modal-item"
                >
                    <strong>Customer Phone</strong>
                    <span id="managerModalCustomerPhone"></span>
                </div>

                <div
                    class="manager-booking-modal-item"
                >
                    <strong>Event Date</strong>
                    <span id="managerModalEventDate"></span>
                </div>

                <div
                    class="manager-booking-modal-item"
                >
                    <strong>Event Time</strong>
                    <span id="managerModalEventTime"></span>
                </div>

                <div
                    class="manager-booking-modal-item"
                >
                    <strong>Total Guests</strong>
                    <span id="managerModalGuests"></span>
                </div>

                <div
                    class="manager-booking-modal-item"
                >
                    <strong>Package</strong>
                    <span id="managerModalPackage"></span>
                </div>

                <div
                    class="manager-booking-modal-item"
                >
                    <strong>Venue</strong>
                    <span id="managerModalVenue"></span>
                </div>

                <div
                    class="manager-booking-modal-item"
                >
                    <strong>Created By</strong>
                    <span id="managerModalCreatedBy"></span>
                </div>

                <div
                    class="manager-booking-modal-item"
                >
                    <strong>Created On</strong>
                    <span id="managerModalCreated"></span>
                </div>

            </div>

            <div
                class="manager-booking-modal-section"
            >
                <h3>Customer Address</h3>

                <div
                    class="manager-booking-modal-text"
                    id="managerModalAddress"
                ></div>
            </div>

            <div
                class="manager-booking-modal-section"
            >
                <h3>Special Instructions</h3>

                <div
                    class="manager-booking-modal-text"
                    id="managerModalInstructions"
                ></div>
            </div>

            <div
                class="manager-booking-modal-section"
            >
                <h3>Additional Services</h3>

                <ul
                    class="manager-booking-services-list"
                    id="managerModalServices"
                ></ul>
            </div>

            <div
                class="manager-booking-modal-price-box"
            >

                <div
                    class="manager-booking-modal-price-row"
                >
                    <span>Package + Venue</span>

                    <span
                        id="managerModalPackageVenueTotal"
                    >
                        Rs. 0
                    </span>
                </div>

                <div
                    class="manager-booking-modal-price-row"
                >
                    <span>Additional Services</span>

                    <span
                        id="managerModalServicesTotal"
                    >
                        Rs. 0
                    </span>
                </div>

                <div
                    class="manager-booking-modal-price-row"
                >
                    <span>Subtotal</span>

                    <span
                        id="managerModalSubtotal"
                    >
                        Rs. 0
                    </span>
                </div>

                <div
                    class="manager-booking-modal-price-row total"
                >
                    <span>Total Amount</span>

                    <span
                        id="managerModalTotal"
                    >
                        Rs. 0
                    </span>
                </div>

            </div>

            <div
                class="manager-booking-modal-update"
                id="managerModalUpdateSection"
            >

                <h3>Update Booking Status</h3>

                <p>
                    Select the next valid booking stage.
                </p>

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
                        id="managerModalBookingId"
                        value=""
                    >

                    <select
                        name="booking_status"
                        id="managerModalStatusSelect"
                        required
                    ></select>

                    <button type="submit">
                        Save Status
                    </button>

                </form>

            </div>

            <div
                class="manager-booking-final-note hidden"
                id="managerModalFinalNote"
            >

                <i
                    class="fa-solid fa-circle-info"
                ></i>

                <span>
                    This booking has reached a final
                    status and cannot be changed by the
                    Booking Manager.
                </span>

            </div>

        </div>

    </div>

    <script>
        const bookingSidebar =
            document.getElementById(
                "bookingSidebar"
            );

        const bookingSidebarOverlay =
            document.getElementById(
                "bookingSidebarOverlay"
            );

        const bookingMenuButton =
            document.getElementById(
                "bookingMenuButton"
            );

        if (
            bookingSidebar
            && bookingSidebarOverlay
            && bookingMenuButton
        ) {
            function closeBookingSidebar() {
                bookingSidebar.classList.remove(
                    "open"
                );

                bookingSidebarOverlay.classList.remove(
                    "open"
                );
            }

            bookingMenuButton.addEventListener(
                "click",
                function () {
                    bookingSidebar.classList.toggle(
                        "open"
                    );

                    bookingSidebarOverlay.classList.toggle(
                        "open"
                    );
                }
            );

            bookingSidebarOverlay.addEventListener(
                "click",
                closeBookingSidebar
            );
        }

        const bookingModal =
            document.getElementById(
                "managerBookingModal"
            );

        const bookingModalClose =
            document.getElementById(
                "managerBookingModalClose"
            );

        const modalStatusSelect =
            document.getElementById(
                "managerModalStatusSelect"
            );

        const modalUpdateSection =
            document.getElementById(
                "managerModalUpdateSection"
            );

        const modalFinalNote =
            document.getElementById(
                "managerModalFinalNote"
            );

        function statusLabel(status) {
            if (status === "in_progress") {
                return "In Progress";
            }

            return status
                .replaceAll("_", " ")
                .replace(
                    /\b\w/g,
                    function (letter) {
                        return letter.toUpperCase();
                    }
                );
        }

        function money(value) {
            return "Rs. " + (value || "0");
        }

        function openManagerBookingModal(
            button,
            openStatusSection
        ) {
            document.getElementById(
                "managerModalEventType"
            ).textContent =
                button.dataset.eventType;

            document.getElementById(
                "managerModalBookingCode"
            ).textContent =
                "Booking Reference: "
                + button.dataset.code;

            document.getElementById(
                "managerModalCustomerName"
            ).textContent =
                button.dataset.customerName;

            document.getElementById(
                "managerModalCustomerEmail"
            ).textContent =
                button.dataset.customerEmail;

            document.getElementById(
                "managerModalCustomerPhone"
            ).textContent =
                button.dataset.customerPhone;

            document.getElementById(
                "managerModalEventDate"
            ).textContent =
                button.dataset.eventDate;

            document.getElementById(
                "managerModalEventTime"
            ).textContent =
                button.dataset.eventTime;

            document.getElementById(
                "managerModalGuests"
            ).textContent =
                button.dataset.guests
                + " guests";

            document.getElementById(
                "managerModalPackage"
            ).textContent =
                button.dataset.package;

            document.getElementById(
                "managerModalVenue"
            ).textContent =
                button.dataset.venue;

            document.getElementById(
                "managerModalCreatedBy"
            ).textContent =
                button.dataset.createdBy;

            document.getElementById(
                "managerModalCreated"
            ).textContent =
                button.dataset.created;

            document.getElementById(
                "managerModalAddress"
            ).textContent =
                button.dataset.address;

            document.getElementById(
                "managerModalInstructions"
            ).textContent =
                button.dataset.instructions;

            document.getElementById(
                "managerModalPackageVenueTotal"
            ).textContent =
                money(
                    button.dataset.packageVenueTotal
                );

            document.getElementById(
                "managerModalServicesTotal"
            ).textContent =
                money(
                    button.dataset.servicesTotal
                );

            document.getElementById(
                "managerModalSubtotal"
            ).textContent =
                money(
                    button.dataset.subtotal
                );

            document.getElementById(
                "managerModalTotal"
            ).textContent =
                money(
                    button.dataset.total
                );

            const statusElement =
                document.getElementById(
                    "managerModalBookingStatus"
                );

            statusElement.textContent =
                button.dataset.statusLabel;

            statusElement.className =
                "manager-booking-tag "
                + (
                    button.dataset.status
                    === "in_progress"
                        ? "in-progress"
                        : button.dataset.status
                );

            document.getElementById(
                "managerModalBookingId"
            ).value =
                button.dataset.bookingId;

            const servicesList =
                document.getElementById(
                    "managerModalServices"
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
                servicesList.appendChild(item);
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
                            service.quantity || 1
                        );

                        name.textContent =
                            service.name
                            + (
                                quantity > 1
                                    ? " × " + quantity
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
                                    maximumFractionDigits: 0
                                }
                            );

                        item.appendChild(name);
                        item.appendChild(price);

                        servicesList.appendChild(
                            item
                        );
                    }
                );
            }

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
                modalUpdateSection.classList.add(
                    "hidden"
                );

                modalFinalNote.classList.remove(
                    "hidden"
                );
            } else {
                nextStatuses.forEach(
                    function (status) {
                        const option =
                            document.createElement(
                                "option"
                            );

                        option.value = status;

                        option.textContent =
                            statusLabel(status);

                        modalStatusSelect.appendChild(
                            option
                        );
                    }
                );

                modalUpdateSection.classList.remove(
                    "hidden"
                );

                modalFinalNote.classList.add(
                    "hidden"
                );
            }

            bookingModal.classList.add(
                "open"
            );

            document.body.style.overflow =
                "hidden";

            if (
                openStatusSection
                && nextStatuses.length > 0
            ) {
                window.setTimeout(
                    function () {
                        modalUpdateSection
                            .scrollIntoView({
                                behavior: "smooth",
                                block: "center"
                            });

                        modalStatusSelect.focus();
                    },
                    120
                );
            }
        }

        document
            .querySelectorAll(
                "[data-manager-booking-details]"
            )
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        openManagerBookingModal(
                            button,
                            false
                        );
                    }
                );
            });

        document
            .querySelectorAll(
                "[data-open-status-for]"
            )
            .forEach(function (button) {
                button.addEventListener(
                    "click",
                    function () {
                        const detailsButton =
                            document.querySelector(
                                '[data-booking-id="'
                                + button.dataset
                                    .openStatusFor
                                + '"]'
                            );

                        if (detailsButton) {
                            openManagerBookingModal(
                                detailsButton,
                                true
                            );
                        }
                    }
                );
            });

        function closeManagerBookingModal() {
            bookingModal.classList.remove(
                "open"
            );

            document.body.style.overflow =
                "";
        }

        bookingModalClose.addEventListener(
            "click",
            closeManagerBookingModal
        );

        bookingModal.addEventListener(
            "click",
            function (event) {
                if (
                    event.target
                    === bookingModal
                ) {
                    closeManagerBookingModal();
                }
            }
        );

        document.addEventListener(
            "keydown",
            function (event) {
                if (event.key === "Escape") {
                    closeManagerBookingModal();
                }
            }
        );

        const requestedBookingId =
            "<?= e(
                $requestedBookingId > 0
                    ? (string) $requestedBookingId
                    : ''
            ) ?>";

        if (
            requestedBookingId !== ""
        ) {
            const requestedButton =
                document.querySelector(
                    '[data-booking-id="'
                    + requestedBookingId
                    + '"]'
                );

            if (requestedButton) {
                openManagerBookingModal(
                    requestedButton,
                    false
                );
            }
        }
    </script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>