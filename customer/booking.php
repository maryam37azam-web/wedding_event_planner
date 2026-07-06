<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/package_helpers.php';
require_once __DIR__ . '/../includes/venue_helpers.php';
require_once __DIR__ . '/../includes/booking_availability_helpers.php';

require_role('customer');

$connection = db();
$customerId = (int) $_SESSION['user_id'];

$errors = [];
$flash = get_flash();

$allowedEventTypes = [
    'Wedding',
    'Nikah',
    'Mehndi',
    'Walima',
    'Engagement',
    'Reception',
    'Barat',
    'Mayo',
];

$allowedPaymentMethods = [
    'Online Bank Transfer',
    'Cash Deposit at Office',
    'Crossed Bank Cheque',
];

function checkout_format_money(
    float $amount
): string {
    return 'Rs. ' . number_format(
        $amount,
        0
    );
}

function checkout_clean_lines(
    ?string $value
): array {
    $value = trim(
        (string) $value
    );

    if ($value === '') {
        return [];
    }

    $parts = preg_split(
        '/\r\n|\r|\n|,/',
        $value
    );

    if (!is_array($parts)) {
        return [];
    }

    $lines = [];

    foreach ($parts as $part) {
        $part = trim($part);

        if ($part !== '') {
            $lines[] = $part;
        }
    }

    return array_values(
        array_unique($lines)
    );
}

function checkout_music_type_from_name(
    string $name
): ?string {
    $normalized = strtolower($name);

    if (
        str_contains(
            $normalized,
            'live'
        )
    ) {
        return 'live_music';
    }

    if (
        str_contains(
            $normalized,
            'basic'
        )
        || str_contains(
            $normalized,
            'sound'
        )
    ) {
        return 'basic_music';
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| Customer profile
|--------------------------------------------------------------------------
*/

$customerStatement = $connection->prepare(
    'SELECT
        full_name,
        email,
        phone,
        city,
        address,
        profile_image
     FROM users
     WHERE id = ?
     AND role = ?
     AND is_active = 1
     LIMIT 1'
);

$customerStatement->execute([
    $customerId,
    'customer',
]);

$customer = $customerStatement->fetch();

if (!$customer) {
    redirect('/auth/logout.php');
}

/*
|--------------------------------------------------------------------------
| Booking selection
|--------------------------------------------------------------------------
*/

$bookingType = strtolower(
    trim(
        (string) (
            $_POST['booking_type']
            ?? $_GET['booking_type']
            ?? ''
        )
    )
);

if (
    !in_array(
        $bookingType,
        [
            'package',
            'venue',
        ],
        true
    )
) {
    $bookingType =
        isset($_GET['venue_id'])
            ? 'venue'
            : 'package';
}

$isPackage =
    $bookingType === 'package';

$entityId =
    $isPackage
        ? max(
            0,
            (int) (
                $_POST['package_id']
                ?? $_GET['package_id']
                ?? 0
            )
        )
        : max(
            0,
            (int) (
                $_POST['venue_id']
                ?? $_GET['venue_id']
                ?? 0
            )
        );

$eventDate = trim(
    (string) (
        $_POST['event_date']
        ?? $_GET['event_date']
        ?? ''
    )
);

$startTime = trim(
    (string) (
        $_POST['start_time']
        ?? $_GET['start_time']
        ?? $_GET['event_time']
        ?? ''
    )
);

$endTime = trim(
    (string) (
        $_POST['end_time']
        ?? $_GET['end_time']
        ?? ''
    )
);

$selectedEntity = booking_active_entity(
    $connection,
    $bookingType,
    $entityId
);

$normalizedDate =
    normalize_booking_date(
        $eventDate
    );

$normalizedStart =
    normalize_booking_time(
        $startTime
    );

$normalizedEnd =
    normalize_booking_time(
        $endTime
    );

if (
    $selectedEntity === null
    || $normalizedDate === null
    || $normalizedStart === null
    || $normalizedEnd === null
    || $normalizedEnd <= $normalizedStart
) {
    set_flash(
        'error',
        'Please select an available date and time from the package or venue details page before opening the booking form.'
    );

    redirect(
        $bookingType === 'venue'
            ? '/customer/venues.php'
            : '/customer/packages.php'
    );
}

$eventDate = $normalizedDate;
$startTime = $normalizedStart;
$endTime = $normalizedEnd;

$maximumGuestCapacity = $isPackage
    ? (int) (
        $selectedEntity['guest_capacity']
        ?? 0
    )
    : (int) (
        $selectedEntity['capacity']
        ?? 0
    );

/*
|--------------------------------------------------------------------------
| Venue services
|--------------------------------------------------------------------------
*/

$cateringServices = [];
$musicServices = [];

if (!$isPackage) {
    $servicesStatement = $connection->query(
        "SELECT
            id,
            service_type,
            name,
            category,
            description,
            price
         FROM services
         WHERE status = 'active'
         AND service_type IN (
             'catering',
             'music'
         )
         ORDER BY
            service_type ASC,
            category ASC,
            name ASC"
    );

    foreach (
        $servicesStatement->fetchAll()
        as $service
    ) {
        if (
            (string) $service[
                'service_type'
            ] === 'music'
        ) {
            $musicServices[] = $service;
        } else {
            $cateringServices[] = $service;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Form values
|--------------------------------------------------------------------------
*/

$formValues = [
    'customer_name' =>
        (string) $customer['full_name'],

    'customer_email' =>
        (string) $customer['email'],

    'customer_phone' =>
        (string) (
            $customer['phone']
            ?? ''
        ),

    'customer_city' =>
        (string) (
            $customer['city']
            ?? ''
        ),

    'customer_address' =>
        (string) (
            $customer['address']
            ?? ''
        ),

    'event_type' =>
        'Wedding',

    'guest_count' =>
        '',

    'payment_method' =>
        'Online Bank Transfer',

    'catering_service_ids' =>
        [],

    'music_service_id' =>
        '',

    'decoration_requirements' =>
        '',

    'special_instructions' =>
        '',
];

$selectedCateringServices = [];
$selectedMusicService = null;

$basePrice = (float) (
    $selectedEntity['price']
    ?? 0
);

$cateringPerHead = 0.0;
$cateringTotal = 0.0;
$musicTotal = 0.0;
$totalAmount = $basePrice;

$advanceAmount = round(
    $totalAmount * 0.25,
    2
);

/*
|--------------------------------------------------------------------------
| Create booking
|--------------------------------------------------------------------------
*/

if (is_post()) {
    $submittedToken = (string) (
        $_POST['csrf_token']
        ?? ''
    );

    if (
        !verify_csrf(
            $submittedToken
        )
    ) {
        $errors[] =
            'Your form session expired. Refresh the page and try again.';
    }

    $formValues['customer_name'] = trim(
        (string) (
            $_POST['customer_name']
            ?? ''
        )
    );

    $formValues['customer_email'] = strtolower(
        trim(
            (string) (
                $_POST['customer_email']
                ?? ''
            )
        )
    );

    $formValues['customer_phone'] = trim(
        (string) (
            $_POST['customer_phone']
            ?? ''
        )
    );

    $formValues['customer_city'] = trim(
        (string) (
            $_POST['customer_city']
            ?? ''
        )
    );

    $formValues['customer_address'] = trim(
        (string) (
            $_POST['customer_address']
            ?? ''
        )
    );

    $formValues['event_type'] = trim(
        (string) (
            $_POST['event_type']
            ?? ''
        )
    );

    $formValues['guest_count'] = trim(
        (string) (
            $_POST['guest_count']
            ?? ''
        )
    );

    $formValues['payment_method'] = trim(
        (string) (
            $_POST['payment_method']
            ?? ''
        )
    );

    $formValues['music_service_id'] = trim(
        (string) (
            $_POST['music_service_id']
            ?? ''
        )
    );

    $formValues['decoration_requirements'] = trim(
        (string) (
            $_POST['decoration_requirements']
            ?? ''
        )
    );

    $formValues['special_instructions'] = trim(
        (string) (
            $_POST['special_instructions']
            ?? ''
        )
    );

    $postedCateringIds =
        $_POST['catering_service_ids']
        ?? [];

    if (
        !is_array(
            $postedCateringIds
        )
    ) {
        $postedCateringIds = [];
    }

    $formValues['catering_service_ids'] =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        $postedCateringIds
                    ),
                    static fn (
                        int $id
                    ): bool => $id > 0
                )
            )
        );

    if (
        mb_strlen(
            $formValues['customer_name']
        ) < 3
        || mb_strlen(
            $formValues['customer_name']
        ) > 120
    ) {
        $errors[] =
            'Full name must be between 3 and 120 characters.';
    }

    if (
        !filter_var(
            $formValues['customer_email'],
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $errors[] =
            'Enter a valid email address.';
    }

    if (
        !preg_match(
            '/^[0-9+\-\s()]{7,30}$/',
            $formValues['customer_phone']
        )
    ) {
        $errors[] =
            'Enter a valid contact number.';
    }

    if (
        mb_strlen(
            $formValues['customer_city']
        ) < 2
        || mb_strlen(
            $formValues['customer_city']
        ) > 120
    ) {
        $errors[] =
            'City must be between 2 and 120 characters.';
    }

    if (
        mb_strlen(
            $formValues['customer_address']
        ) < 5
        || mb_strlen(
            $formValues['customer_address']
        ) > 1000
    ) {
        $errors[] =
            'Address must be between 5 and 1,000 characters.';
    }

    if (
        !in_array(
            $formValues['event_type'],
            $allowedEventTypes,
            true
        )
    ) {
        $errors[] =
            'Select a valid event type.';
    }

    if (
        !in_array(
            $formValues['payment_method'],
            $allowedPaymentMethods,
            true
        )
    ) {
        $errors[] =
            'Select a valid payment method.';
    }

    if (
        mb_strlen(
            $formValues[
                'decoration_requirements'
            ]
        ) > 1500
        || mb_strlen(
            $formValues[
                'special_instructions'
            ]
        ) > 2000
    ) {
        $errors[] =
            'One of the instruction fields is too long.';
    }

    $guestCount = (int) (
        $formValues['guest_count']
    );

    if ($guestCount < 1) {
        $errors[] =
            'Enter the confirmed number of guests.';
    } elseif (
        $maximumGuestCapacity > 0
        && $guestCount
            > $maximumGuestCapacity
    ) {
        $errors[] =
            'Guest count cannot exceed the maximum capacity of '
            . number_format(
                $maximumGuestCapacity
            )
            . ' guests.';
    }

    if (
        !booking_slot_is_available(
            $connection,
            $bookingType,
            $entityId,
            $eventDate,
            $startTime,
            $endTime
        )
    ) {
        $errors[] =
            'The selected date and time is no longer available. Return to the details page and choose another slot.';
    }

    /*
    |--------------------------------------------------------------------------
    | Venue catering and music calculation
    |--------------------------------------------------------------------------
    */

    if (!$isPackage) {
        if (
            $formValues[
                'catering_service_ids'
            ] !== []
        ) {
            $placeholders = implode(
                ',',
                array_fill(
                    0,
                    count(
                        $formValues[
                            'catering_service_ids'
                        ]
                    ),
                    '?'
                )
            );

            $cateringStatement =
                $connection->prepare(
                    "SELECT
                        id,
                        name,
                        category,
                        price
                     FROM services
                     WHERE status = 'active'
                     AND service_type = 'catering'
                     AND id IN ($placeholders)"
                );

            $cateringStatement->execute(
                $formValues[
                    'catering_service_ids'
                ]
            );

            $selectedCateringServices =
                $cateringStatement->fetchAll();

            if (
                count(
                    $selectedCateringServices
                )
                !== count(
                    $formValues[
                        'catering_service_ids'
                    ]
                )
            ) {
                $errors[] =
                    'One or more selected catering items are no longer active.';
            }
        }

        $musicServiceId = (int) (
            $formValues[
                'music_service_id'
            ]
        );

        if ($musicServiceId > 0) {
            $musicStatement =
                $connection->prepare(
                    "SELECT
                        id,
                        name,
                        description,
                        price
                     FROM services
                     WHERE id = ?
                     AND status = 'active'
                     AND service_type = 'music'
                     LIMIT 1"
                );

            $musicStatement->execute([
                $musicServiceId,
            ]);

            $selectedMusicService =
                $musicStatement->fetch()
                ?: null;

            if (
                $selectedMusicService
                === null
            ) {
                $errors[] =
                    'The selected music service is no longer active.';
            }
        }

        foreach (
            $selectedCateringServices
            as $service
        ) {
            $cateringPerHead +=
                (float) $service['price'];
        }

        $cateringTotal =
            $guestCount > 0
                ? $cateringPerHead
                    * $guestCount
                : 0.0;

        $musicTotal =
            $selectedMusicService
                ? (float) $selectedMusicService[
                    'price'
                ]
                : 0.0;

        $totalAmount =
            $basePrice
            + $cateringTotal
            + $musicTotal;

        $advanceAmount = round(
            $totalAmount * 0.25,
            2
        );
    } else {
        $totalAmount = $basePrice;

        $advanceAmount = round(
            $totalAmount * 0.25,
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Save booking
    |--------------------------------------------------------------------------
    */

    if ($errors === []) {
        $bookingCode =
            'WEP-'
            . date('ymd')
            . '-'
            . strtoupper(
                bin2hex(
                    random_bytes(3)
                )
            );

        $specialInstructionParts = [];

        if (
            $formValues[
                'decoration_requirements'
            ] !== ''
        ) {
            $specialInstructionParts[] =
                'Decoration requirements: '
                . $formValues[
                    'decoration_requirements'
                ];
        }

        if (
            $formValues[
                'special_instructions'
            ] !== ''
        ) {
            $specialInstructionParts[] =
                'Additional instructions: '
                . $formValues[
                    'special_instructions'
                ];
        }

        $combinedInstructions =
            $specialInstructionParts !== []
                ? implode(
                    "\n\n",
                    $specialInstructionParts
                )
                : null;

        $musicType =
            $selectedMusicService
                ? checkout_music_type_from_name(
                    (string) $selectedMusicService[
                        'name'
                    ]
                )
                : null;

        try {
            $connection->beginTransaction();

            if (
                !booking_slot_is_available(
                    $connection,
                    $bookingType,
                    $entityId,
                    $eventDate,
                    $startTime,
                    $endTime
                )
            ) {
                throw new RuntimeException(
                    'The selected date and time was booked by another customer. Please choose another slot.'
                );
            }

            $bookingStatement =
                $connection->prepare(
                    'INSERT INTO bookings (
                        booking_code,
                        customer_id,
                        customer_name,
                        customer_email,
                        customer_phone,
                        customer_city,
                        package_id,
                        venue_id,
                        event_date,
                        event_time,
                        start_time,
                        end_time,
                        event_type,
                        guest_count,
                        customer_address,
                        special_instructions,
                        subtotal,
                        music_type,
                        total_amount,
                        advance_amount,
                        payment_method,
                        booking_status,
                        payment_status,
                        created_by
                     ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                     )'
                );

            $bookingStatement->execute([
                $bookingCode,
                $customerId,

                $formValues[
                    'customer_name'
                ],

                $formValues[
                    'customer_email'
                ],

                $formValues[
                    'customer_phone'
                ],

                $formValues[
                    'customer_city'
                ],

                $isPackage
                    ? $entityId
                    : null,

                !$isPackage
                    ? $entityId
                    : null,

                $eventDate,
                $startTime . ':00',
                $startTime . ':00',
                $endTime . ':00',

                $formValues[
                    'event_type'
                ],

                $guestCount,

                $formValues[
                    'customer_address'
                ],

                $combinedInstructions,
                $totalAmount,
                $musicType,
                $totalAmount,
                $advanceAmount,

                $formValues[
                    'payment_method'
                ],

                'pending',
                'unpaid',
                $customerId,
            ]);

            $bookingId = (int) (
                $connection
                    ->lastInsertId()
            );

            if (!$isPackage) {
                $bookingServiceStatement =
                    $connection->prepare(
                        'INSERT INTO booking_services (
                            booking_id,
                            service_id,
                            quantity,
                            price
                         ) VALUES (?, ?, ?, ?)'
                    );

                foreach (
                    $selectedCateringServices
                    as $service
                ) {
                    $bookingServiceStatement
                        ->execute([
                            $bookingId,

                            (int) $service[
                                'id'
                            ],

                            $guestCount,

                            (float) $service[
                                'price'
                            ],
                        ]);
                }

                if (
                    $selectedMusicService
                    !== null
                ) {
                    $bookingServiceStatement
                        ->execute([
                            $bookingId,

                            (int) $selectedMusicService[
                                'id'
                            ],

                            1,

                            (float) $selectedMusicService[
                                'price'
                            ],
                        ]);
                }
            }

            $paymentStatement =
                $connection->prepare(
                    'INSERT INTO payments (
                        booking_id,
                        amount,
                        payment_method,
                        payment_status
                     ) VALUES (?, ?, ?, ?)'
                );

            $paymentStatement->execute([
                $bookingId,
                $advanceAmount,

                $formValues[
                    'payment_method'
                ],

                'pending',
            ]);

            $connection->commit();

            unset(
                $_SESSION[
                    'pending_booking_selection'
                ],
                $_SESSION[
                    'redirect_after_login'
                ]
            );

            set_flash(
                'success',
                'Your booking request was created successfully. Booking reference: '
                . $bookingCode
                . '. Please submit the 25% advance payment according to the selected payment method.'
            );

            redirect(
                '/customer/my_bookings.php?booking_id='
                . $bookingId
            );
        } catch (Throwable $exception) {
            if (
                $connection->inTransaction()
            ) {
                $connection->rollBack();
            }

            $errors[] =
                APP_DEBUG
                    ? 'Booking could not be created: '
                        . $exception->getMessage()
                    : 'Booking could not be created. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Display information
|--------------------------------------------------------------------------
*/

$entityName = (string) (
    $selectedEntity['name']
    ?? ''
);

$entityLocation =
    $isPackage
        ? trim(
            (string) (
                $selectedEntity[
                    'venue_location'
                ]
                ?? ''
            )
        )
        : trim(
            (string) (
                $selectedEntity[
                    'location'
                ]
                ?? ''
            )
        );

$entityImage =
    $isPackage
        ? package_image_url(
            $selectedEntity[
                'main_image'
            ]
            ?? null
        )
        : venue_image_url(
            $selectedEntity[
                'main_image'
            ]
            ?? null
        );

$entityDescription = trim(
    (string) (
        $selectedEntity['description']
        ?? ''
    )
);

if (
    $entityDescription === ''
    && $isPackage
) {
    $entityDescription = trim(
        (string) (
            $selectedEntity[
                'short_description'
            ]
            ?? ''
        )
    );
}

$packageMenuItems =
    $isPackage
        ? checkout_clean_lines(
            $selectedEntity[
                'catering_menu'
            ]
            ?? null
        )
        : [];

$packageFeatures =
    $isPackage
        ? package_feature_lines(
            $selectedEntity[
                'features'
            ]
            ?? null
        )
        : [];

$venueFacilities =
    !$isPackage
        ? venue_facility_lines(
            $selectedEntity[
                'facilities'
            ]
            ?? null
        )
        : [];

$packageMusic = [];

if ($isPackage) {
    if (
        (int) (
            $selectedEntity[
                'basic_music'
            ]
            ?? 0
        ) === 1
    ) {
        $packageMusic[] =
            'Basic Music';
    }

    if (
        (int) (
            $selectedEntity[
                'live_music'
            ]
            ?? 0
        ) === 1
    ) {
        $packageMusic[] =
            'Live Music';
    }
}

$selectedDateLabel =
    (
        new DateTimeImmutable(
            $eventDate
        )
    )->format(
        'F j, Y'
    );

$selectedTimeLabel =
    date(
        'h:i A',
        strtotime(
            $startTime
        )
    )
    . ' - '
    . date(
        'h:i A',
        strtotime(
            $endTime
        )
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
        <?= $isPackage
            ? 'Package Booking'
            : 'Venue Booking' ?>
        | <?= e(APP_NAME) ?>
    </title>

    <?php require __DIR__ . '/../includes/pwa_head.php'; ?>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Poppins:wght@400;500;600;700&display=swap"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url(
                '/assets/css/customer_checkout.css?v=20260705-2'
            )
        ) ?>"
    >
</head>

<body
    class="customer-checkout-page"
    data-booking-type="<?= e(
        $bookingType
    ) ?>"
    data-base-price="<?= e(
        (string) $basePrice
    ) ?>"
>

    <header class="checkout-navbar">

        <a
            class="checkout-brand"
            href="<?= e(url('/')) ?>"
        >
            <img
                src="<?= e(
                    url(
                        '/assets/icons/icon-192.png'
                    )
                ) ?>"
                alt="Wedding Planner"
            >

            <span>
                <strong>
                    Wedding Planner
                </strong>

                <small>
                    Perfect events, beautiful memories
                </small>
            </span>
        </a>

        <div class="checkout-navbar-actions">

            <a
                href="<?= e(
                    url(
                        '/customer/my_bookings.php'
                    )
                ) ?>"
            >
                <i class="fa-regular fa-calendar-check"></i>
                My Bookings
            </a>

            <a
                href="<?= e(
                    url(
                        '/customer/profile.php'
                    )
                ) ?>"
            >
                <i class="fa-regular fa-user"></i>
                My Account
            </a>

        </div>

    </header>

    <?php if ($flash): ?>

        <div
            class="checkout-alert <?= $flash['type'] === 'success'
                ? 'success'
                : 'danger' ?>"
        >
            <?= e(
                $flash['message']
            ) ?>
        </div>

    <?php endif; ?>

    <?php if (
        $errors !== []
    ): ?>

        <div class="checkout-alert danger">
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

    <main class="checkout-main-container">

        <section class="checkout-details-side">

            <div class="checkout-image-wrapper">

                <img
                    src="<?= e(
                        $entityImage
                    ) ?>"
                    alt="<?= e(
                        $entityName
                    ) ?>"
                >

            </div>

            <span class="checkout-package-tag">

                <?= $isPackage
                    ? 'Your Selected Package'
                    : 'Your Selected Venue' ?>

            </span>

            <h1 class="checkout-package-title">
                <?= e($entityName) ?>
            </h1>

            <?php if (
                $entityLocation !== ''
            ): ?>

                <p class="checkout-location">
                    <i class="fa-solid fa-location-dot"></i>
                    <?= e(
                        $entityLocation
                    ) ?>
                </p>

            <?php endif; ?>

            <div class="checkout-package-price">

                <span>
                    <?= $isPackage
                        ? 'Package Price'
                        : 'Base Venue Rental' ?>
                </span>

                <strong>
                    <?= e(
                        checkout_format_money(
                            $basePrice
                        )
                    ) ?>
                </strong>

            </div>

            <?php if (!$isPackage): ?>

                <div class="checkout-venue-price-note">

                    <i class="fa-solid fa-circle-info"></i>

                    <span>
                        This is the per-day venue rental price
                        including standard decoration. Catering,
                        music and selected additional services
                        are charged separately.
                    </span>

                </div>

            <?php endif; ?>

            <?php if (
                $entityDescription !== ''
            ): ?>

                <p class="checkout-description">
                    <?= e(
                        $entityDescription
                    ) ?>
                </p>

            <?php endif; ?>

            <?php if ($isPackage): ?>

                <section class="checkout-detail-group">

                    <h2>
                        Pre-Selected Catering Configuration
                    </h2>

                    <div class="checkout-feature-list">

                        <?php if (
                            $packageMenuItems !== []
                        ): ?>

                            <?php foreach (
                                $packageMenuItems
                                as $item
                            ): ?>

                                <div class="checkout-feature-item">

                                    <i class="fa-solid fa-circle-check"></i>

                                    <span>
                                        <?= e($item) ?>
                                    </span>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="checkout-feature-item">

                                <i class="fa-solid fa-circle-check"></i>

                                <span>
                                    Catering details will be confirmed by the booking team.
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>

                </section>

                <section class="checkout-detail-group">

                    <h2>
                        Decoration and Music Included
                    </h2>

                    <div class="checkout-feature-list">

                        <div class="checkout-feature-item">

                            <i class="fa-solid fa-wand-magic-sparkles"></i>

                            <span>
                                <?= e(
                                    (string) (
                                        $selectedEntity[
                                            'decoration_type'
                                        ]
                                        ?: 'Wedding decoration included'
                                    )
                                ) ?>
                            </span>

                        </div>

                        <?php foreach (
                            $packageMusic as $music
                        ): ?>

                            <div class="checkout-feature-item">

                                <i class="fa-solid fa-music"></i>

                                <span>
                                    <?= e($music) ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

                <?php if (
                    $packageFeatures !== []
                ): ?>

                    <section class="checkout-detail-group">

                        <h2>
                            Additional Package Features
                        </h2>

                        <div class="checkout-feature-list">

                            <?php foreach (
                                $packageFeatures
                                as $feature
                            ): ?>

                                <div class="checkout-feature-item">

                                    <i class="fa-solid fa-star"></i>

                                    <span>
                                        <?= e($feature) ?>
                                    </span>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </section>

                <?php endif; ?>

                <div class="checkout-capacity-note">

                    <i class="fa-solid fa-users"></i>

                    <span>
                        Maximum package capacity
                    </span>

                    <strong>
                        <?= e(
                            number_format(
                                $maximumGuestCapacity
                            )
                        ) ?>
                        guests
                    </strong>

                </div>

            <?php else: ?>

                <section class="checkout-detail-group">

                    <h2>
                        Venue Inclusions
                    </h2>

                    <div class="checkout-feature-list">

                        <div class="checkout-feature-item">

                            <i class="fa-solid fa-users"></i>

                            <span>
                                Seating capacity up to
                                <?= e(
                                    number_format(
                                        $maximumGuestCapacity
                                    )
                                ) ?>
                                guests
                            </span>

                        </div>

                        <?php foreach (
                            $venueFacilities
                            as $facility
                        ): ?>

                            <div class="checkout-feature-item">

                                <i class="fa-solid fa-circle-check"></i>

                                <span>
                                    <?= e(
                                        $facility
                                    ) ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>

        </section>

        <section class="checkout-form-side">

            <div class="checkout-form-heading">

                <span>
                    <?= $isPackage
                        ? 'Package Checkout'
                        : 'Venue Checkout' ?>
                </span>

                <h2>
                    <?= $isPackage
                        ? 'Confirm Reservation'
                        : 'Configure Venue Booking' ?>
                </h2>

                <p>
                    Your registered details have been filled automatically.
                    You may update them for this booking.
                </p>

            </div>

            <div class="checkout-advance-notice-card">

                <i class="fa-solid fa-wallet"></i>

                <div>

                    <strong>
                        25% Advance Payment Required
                    </strong>

                    <p>
                        A 25% advance is required to reserve the
                        selected date and time. The booking remains
                        pending until the payment is verified.
                    </p>

                    <span>
                        Advance Deposit:

                        <b id="checkoutAdvanceNoticeAmount">
                            <?= e(
                                checkout_format_money(
                                    $advanceAmount
                                )
                            ) ?>
                        </b>
                    </span>

                </div>

            </div>

            <form
                method="post"
                id="customerCheckoutForm"
            >

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="booking_type"
                    value="<?= e(
                        $bookingType
                    ) ?>"
                >

                <?php if ($isPackage): ?>

                    <input
                        type="hidden"
                        name="package_id"
                        value="<?= e(
                            (string) $entityId
                        ) ?>"
                    >

                <?php else: ?>

                    <input
                        type="hidden"
                        name="venue_id"
                        value="<?= e(
                            (string) $entityId
                        ) ?>"
                    >

                <?php endif; ?>

                <input
                    type="hidden"
                    name="event_date"
                    value="<?= e(
                        $eventDate
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="start_time"
                    value="<?= e(
                        $startTime
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="end_time"
                    value="<?= e(
                        $endTime
                    ) ?>"
                >

                <div class="checkout-form-row">

                    <div class="checkout-form-group">

                        <label for="customer_name">
                            <i class="fa-regular fa-user"></i>
                            Full Name
                        </label>

                        <input
                            class="checkout-profile-field"
                            type="text"
                            id="customer_name"
                            name="customer_name"
                            value="<?= e(
                                $formValues[
                                    'customer_name'
                                ]
                            ) ?>"
                            maxlength="120"
                            required
                        >

                    </div>

                    <div class="checkout-form-group">

                        <label for="customer_phone">
                            <i class="fa-solid fa-phone"></i>
                            Contact Number
                        </label>

                        <input
                            class="checkout-profile-field"
                            type="tel"
                            id="customer_phone"
                            name="customer_phone"
                            value="<?= e(
                                $formValues[
                                    'customer_phone'
                                ]
                            ) ?>"
                            maxlength="30"
                            required
                        >

                    </div>

                </div>

                <div class="checkout-form-row">

                    <div class="checkout-form-group">

                        <label for="customer_email">
                            <i class="fa-regular fa-envelope"></i>
                            Email Address
                        </label>

                        <input
                            class="checkout-profile-field"
                            type="email"
                            id="customer_email"
                            name="customer_email"
                            value="<?= e(
                                $formValues[
                                    'customer_email'
                                ]
                            ) ?>"
                            maxlength="190"
                            required
                        >

                    </div>

                    <div class="checkout-form-group">

                        <label for="customer_city">
                            <i class="fa-regular fa-building"></i>
                            Event City
                        </label>

                        <input
                            class="checkout-profile-field"
                            type="text"
                            id="customer_city"
                            name="customer_city"
                            value="<?= e(
                                $formValues[
                                    'customer_city'
                                ]
                            ) ?>"
                            maxlength="120"
                            required
                        >

                    </div>

                </div>

                <div class="checkout-form-group">

                    <label for="customer_address">
                        <i class="fa-solid fa-location-dot"></i>
                        Complete Address
                    </label>

                    <input
                        class="checkout-profile-field"
                        type="text"
                        id="customer_address"
                        name="customer_address"
                        value="<?= e(
                            $formValues[
                                'customer_address'
                            ]
                        ) ?>"
                        maxlength="1000"
                        required
                    >

                </div>

                <div class="checkout-form-row">

                    <div class="checkout-form-group">

                        <label for="selected_event_date">
                            <i class="fa-regular fa-calendar-days"></i>
                            Selected Event Date
                        </label>

                        <input
                            type="text"
                            id="selected_event_date"
                            value="<?= e(
                                $selectedDateLabel
                            ) ?>"
                            readonly
                        >

                    </div>

                    <div class="checkout-form-group">

                        <label for="selected_event_time">
                            <i class="fa-regular fa-clock"></i>
                            Selected Booking Time
                        </label>

                        <input
                            type="text"
                            id="selected_event_time"
                            value="<?= e(
                                $selectedTimeLabel
                            ) ?>"
                            readonly
                        >

                    </div>

                </div>

                <?php if ($isPackage): ?>

                    <div class="checkout-form-row">

                        <div class="checkout-form-group">

                            <label for="event_type">
                                <i class="fa-regular fa-calendar"></i>
                                Event Type
                            </label>

                            <select
                                id="event_type"
                                name="event_type"
                                required
                            >

                                <?php foreach (
                                    $allowedEventTypes
                                    as $eventType
                                ): ?>

                                    <option
                                        value="<?= e(
                                            $eventType
                                        ) ?>"
                                        <?= $formValues[
                                            'event_type'
                                        ] === $eventType
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e(
                                            $eventType
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="checkout-form-group">

                            <label for="guest_count">
                                <i class="fa-solid fa-users"></i>
                                Confirmed Guests Count
                            </label>

                            <input
                                type="number"
                                id="guest_count"
                                name="guest_count"
                                value="<?= e(
                                    $formValues[
                                        'guest_count'
                                    ]
                                ) ?>"
                                min="1"
                                max="<?= e(
                                    (string) $maximumGuestCapacity
                                ) ?>"
                                placeholder="Maximum <?= e(
                                    (string) $maximumGuestCapacity
                                ) ?> guests"
                                required
                            >

                        </div>

                    </div>

                    <div class="checkout-form-group">

                        <label for="payment_method">
                            <i class="fa-regular fa-credit-card"></i>
                            Payment Mode
                        </label>

                        <select
                            id="payment_method"
                            name="payment_method"
                            required
                        >

                            <?php foreach (
                                $allowedPaymentMethods
                                as $paymentMethod
                            ): ?>

                                <option
                                    value="<?= e(
                                        $paymentMethod
                                    ) ?>"
                                    <?= $formValues[
                                        'payment_method'
                                    ] === $paymentMethod
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= e(
                                        $paymentMethod
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                <?php else: ?>

                    <div class="checkout-form-row">

                        <div class="checkout-form-group">

                            <label for="event_type">
                                <i class="fa-regular fa-calendar"></i>
                                Event Type
                            </label>

                            <select
                                id="event_type"
                                name="event_type"
                                required
                            >

                                <?php foreach (
                                    $allowedEventTypes
                                    as $eventType
                                ): ?>

                                    <option
                                        value="<?= e(
                                            $eventType
                                        ) ?>"
                                        <?= $formValues[
                                            'event_type'
                                        ] === $eventType
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e(
                                            $eventType
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="checkout-form-group">

                            <label for="payment_method">
                                <i class="fa-regular fa-credit-card"></i>
                                Payment Mode
                            </label>

                            <select
                                id="payment_method"
                                name="payment_method"
                                required
                            >

                                <?php foreach (
                                    $allowedPaymentMethods
                                    as $paymentMethod
                                ): ?>

                                    <option
                                        value="<?= e(
                                            $paymentMethod
                                        ) ?>"
                                        <?= $formValues[
                                            'payment_method'
                                        ] === $paymentMethod
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e(
                                            $paymentMethod
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>

                    <div class="checkout-venue-options">

                        <div class="checkout-form-group">

                            <label for="guest_count">
                                <i class="fa-solid fa-users"></i>
                                Expected Guests
                            </label>

                            <input
                                type="number"
                                id="guest_count"
                                name="guest_count"
                                value="<?= e(
                                    $formValues[
                                        'guest_count'
                                    ]
                                ) ?>"
                                min="1"
                                max="<?= e(
                                    (string) $maximumGuestCapacity
                                ) ?>"
                                placeholder="Maximum <?= e(
                                    (string) $maximumGuestCapacity
                                ) ?>"
                                required
                            >

                        </div>

                        <div class="checkout-form-group">

                            <label for="music_service_id">
                                <i class="fa-solid fa-music"></i>
                                Music Service
                            </label>

                            <select
                                id="music_service_id"
                                name="music_service_id"
                            >
                                <option
                                    value=""
                                    data-price="0"
                                >
                                    No Music Service (Rs. 0)
                                </option>

                                <?php foreach (
                                    $musicServices
                                    as $musicService
                                ): ?>

                                    <option
                                        value="<?= e(
                                            (string) $musicService[
                                                'id'
                                            ]
                                        ) ?>"
                                        data-price="<?= e(
                                            (string) (
                                                (float) $musicService[
                                                    'price'
                                                ]
                                            )
                                        ) ?>"
                                        <?= (string) $musicService[
                                            'id'
                                        ] === $formValues[
                                            'music_service_id'
                                        ]
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e(
                                            (string) $musicService[
                                                'name'
                                            ]
                                        ) ?>

                                        (<?= e(
                                            checkout_format_money(
                                                (float) $musicService[
                                                    'price'
                                                ]
                                            )
                                        ) ?>)
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="checkout-form-group">

                            <label>
                                <i class="fa-solid fa-utensils"></i>
                                Catering Menu
                            </label>

                            <button
                                class="checkout-menu-trigger"
                                id="checkoutMenuTrigger"
                                type="button"
                                disabled
                            >
                                <i class="fa-solid fa-folder-open"></i>
                                Open Menu Sheet
                            </button>

                            <small id="checkoutMenuHelp">
                                Enter guest count first.
                            </small>

                        </div>

                    </div>

                    <div class="checkout-form-group">

                        <label>
                            Selected Catering Items
                        </label>

                        <div
                            class="checkout-selection-display-box"
                            id="checkoutMenuPreview"
                        >
                            No catering items selected yet.
                        </div>

                    </div>

                    <div class="checkout-form-group">

                        <label for="decoration_requirements">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                            Decoration and Theme Requirements
                        </label>

                        <textarea
                            id="decoration_requirements"
                            name="decoration_requirements"
                            rows="3"
                            maxlength="1500"
                            placeholder="Write preferred colours, theme or stage requirements."
                        ><?= e(
                            $formValues[
                                'decoration_requirements'
                            ]
                        ) ?></textarea>

                    </div>

                <?php endif; ?>

                <div class="checkout-form-group">

                    <label for="special_instructions">
                        <i class="fa-regular fa-comment-dots"></i>
                        Additional Event Details
                    </label>

                    <textarea
                        id="special_instructions"
                        name="special_instructions"
                        rows="3"
                        maxlength="2000"
                        placeholder="Write any additional booking instructions."
                    ><?= e(
                        $formValues[
                            'special_instructions'
                        ]
                    ) ?></textarea>

                </div>

                <section class="checkout-invoice-summary-box">

                    <div class="checkout-summary-heading">

                        <i class="fa-solid fa-file-invoice-dollar"></i>

                        <div>
                            <span>
                                Payment Summary
                            </span>

                            <h3>
                                Booking Amount
                            </h3>
                        </div>

                    </div>

                    <div class="checkout-invoice-line">

                        <span>
                            <?= $isPackage
                                ? 'Full Package Price'
                                : 'Base Venue Rental' ?>
                        </span>

                        <strong id="checkoutBasePrice">
                            <?= e(
                                checkout_format_money(
                                    $basePrice
                                )
                            ) ?>
                        </strong>

                    </div>

                    <?php if (!$isPackage): ?>

                        <div class="checkout-invoice-line">

                            <span>
                                Catering Total
                            </span>

                            <strong id="checkoutCateringTotal">
                                <?= e(
                                    checkout_format_money(
                                        $cateringTotal
                                    )
                                ) ?>
                            </strong>

                        </div>

                        <div class="checkout-invoice-line">

                            <span>
                                Music Charges
                            </span>

                            <strong id="checkoutMusicTotal">
                                <?= e(
                                    checkout_format_money(
                                        $musicTotal
                                    )
                                ) ?>
                            </strong>

                        </div>

                    <?php endif; ?>

                    <div class="checkout-invoice-line checkout-grand-total">

                        <span>
                            Full Total Amount
                        </span>

                        <strong id="checkoutGrandTotal">
                            <?= e(
                                checkout_format_money(
                                    $totalAmount
                                )
                            ) ?>
                        </strong>

                    </div>

                    <div class="checkout-invoice-line checkout-advance-total">

                        <span>
                            25% Advance Payment
                        </span>

                        <strong id="checkoutAdvanceAmount">
                            <?= e(
                                checkout_format_money(
                                    $advanceAmount
                                )
                            ) ?>
                        </strong>

                    </div>

                </section>

                <div class="checkout-payment-instructions">

                    <i class="fa-solid fa-shield-halved"></i>

                    <div>

                        <strong>
                            Advance Payment Instructions
                        </strong>

                        <p>
                            Submit the booking request first. The booking
                            team will verify your request and provide the
                            approved payment details. Pay the displayed
                            25% advance within the communicated time to
                            secure your selected date and booking time.
                        </p>

                    </div>

                </div>

                <button
                    class="checkout-submit-button"
                    type="submit"
                    id="checkoutSubmitButton"
                >
                    <i class="fa-solid fa-lock"></i>

                    <span>
                        Secure Booking with 25% Advance
                    </span>

                    <strong id="checkoutSubmitAdvance">
                        <?= e(
                            checkout_format_money(
                                $advanceAmount
                            )
                        ) ?>
                    </strong>
                </button>

            </form>

        </section>

    </main>

    <?php if (!$isPackage): ?>

        <div
            class="checkout-menu-modal"
            id="checkoutMenuModal"
            aria-hidden="true"
        >

            <div
                class="checkout-menu-backdrop"
                data-close-checkout-menu
            ></div>

            <section
                class="checkout-menu-modal-card"
                role="dialog"
                aria-modal="true"
                aria-labelledby="checkoutMenuModalTitle"
            >

                <header class="checkout-menu-modal-header">

                    <div>

                        <h2 id="checkoutMenuModalTitle">
                            Select Catering Menu Items
                        </h2>

                        <p>
                            Every displayed price is charged per guest.
                        </p>

                    </div>

                    <button
                        type="button"
                        data-close-checkout-menu
                        aria-label="Close menu sheet"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>

                </header>

                <div class="checkout-menu-modal-body">

                    <?php if (
                        $cateringServices === []
                    ): ?>

                        <div class="checkout-menu-empty">
                            No active catering items are currently available.
                        </div>

                    <?php else: ?>

                        <?php $lastCategory = null; ?>

                        <?php foreach (
                            $cateringServices
                            as $service
                        ): ?>

                            <?php
                            $serviceCategory = trim(
                                (string) (
                                    $service[
                                        'category'
                                    ]
                                    ?: 'Other Items'
                                )
                            );
                            ?>

                            <?php if (
                                $serviceCategory
                                !== $lastCategory
                            ): ?>

                                <h3 class="checkout-menu-category-title">
                                    <?= e(
                                        $serviceCategory
                                    ) ?>
                                </h3>

                                <?php
                                $lastCategory =
                                    $serviceCategory;
                                ?>

                            <?php endif; ?>

                            <label class="checkout-menu-item-row">

                                <span>

                                    <input
                                        class="checkout-catering-checkbox"
                                        type="checkbox"
                                        name="catering_service_ids[]"
                                        form="customerCheckoutForm"
                                        value="<?= e(
                                            (string) $service[
                                                'id'
                                            ]
                                        ) ?>"
                                        data-name="<?= e(
                                            (string) $service[
                                                'name'
                                            ]
                                        ) ?>"
                                        data-price="<?= e(
                                            (string) (
                                                (float) $service[
                                                    'price'
                                                ]
                                            )
                                        ) ?>"
                                        <?= in_array(
                                            (int) $service[
                                                'id'
                                            ],
                                            $formValues[
                                                'catering_service_ids'
                                            ],
                                            true
                                        )
                                            ? 'checked'
                                            : '' ?>
                                    >

                                    <span>

                                        <strong>
                                            <?= e(
                                                (string) $service[
                                                    'name'
                                                ]
                                            ) ?>
                                        </strong>

                                        <?php if (
                                            trim(
                                                (string) (
                                                    $service[
                                                        'description'
                                                    ]
                                                    ?? ''
                                                )
                                            ) !== ''
                                        ): ?>

                                            <small>
                                                <?= e(
                                                    (string) $service[
                                                        'description'
                                                    ]
                                                ) ?>
                                            </small>

                                        <?php endif; ?>

                                    </span>

                                </span>

                                <strong>
                                    <?= e(
                                        checkout_format_money(
                                            (float) $service[
                                                'price'
                                            ]
                                        )
                                    ) ?>
                                    / head
                                </strong>

                            </label>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

                <footer class="checkout-menu-modal-footer">

                    <strong id="checkoutMenuPerHeadTotal">
                        Total: Rs. 0 / Head
                    </strong>

                    <button
                        type="button"
                        id="checkoutMenuSaveButton"
                    >
                        Save and Use Menu
                    </button>

                </footer>

            </section>

        </div>

    <?php endif; ?>

    <script
        src="<?= e(
            url(
                '/assets/js/customer_checkout.js?v=20260705-2'
            )
        ) ?>"
        defer
    ></script>

    <?php require __DIR__ . '/../includes/pwa_scripts.php'; ?>

</body>
</html>