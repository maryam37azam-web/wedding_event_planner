<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Return the supported availability entity configuration.
 */
function booking_entity_configuration(
    string $type
): ?array {
    return match ($type) {
        'package' => [
            'table' => 'packages',
            'booking_column' => 'package_id',
            'label' => 'package',
            'title_column' => 'name',
        ],

        'venue' => [
            'table' => 'venues',
            'booking_column' => 'venue_id',
            'label' => 'venue',
            'title_column' => 'name',
        ],

        default => null,
    };
}

/**
 * Return the current month and the next eleven months.
 */
function booking_allowed_months(
    int $monthCount = 12
): array {
    $monthCount = max(
        1,
        min(
            24,
            $monthCount
        )
    );

    $firstMonth =
        new DateTimeImmutable(
            'first day of this month'
        );

    $months = [];

    for (
        $index = 0;
        $index < $monthCount;
        $index++
    ) {
        $month =
            $firstMonth->modify(
                '+' . $index . ' months'
            );

        $months[] = [
            'value' =>
                $month->format(
                    'Y-m'
                ),

            'label' =>
                $month->format(
                    'F Y'
                ),
        ];
    }

    return $months;
}

/**
 * Validate that a month belongs to the permitted booking window.
 */
function booking_month_object(
    string $month
): ?DateTimeImmutable {
    if (
        !preg_match(
            '/^\d{4}-\d{2}$/',
            $month
        )
    ) {
        return null;
    }

    $monthObject =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $month . '-01'
        );

    $dateErrors =
        DateTimeImmutable::getLastErrors();

    if (
        $monthObject === false
        || (
            is_array(
                $dateErrors
            )
            && (
                $dateErrors[
                    'warning_count'
                ] > 0
                || $dateErrors[
                    'error_count'
                ] > 0
            )
        )
        || $monthObject->format(
            'Y-m'
        ) !== $month
    ) {
        return null;
    }

    $firstAllowedMonth =
        new DateTimeImmutable(
            'first day of this month'
        );

    $lastAllowedMonth =
        $firstAllowedMonth
            ->modify(
                '+11 months'
            );

    if (
        $monthObject
        < $firstAllowedMonth
        || $monthObject
        > $lastAllowedMonth
    ) {
        return null;
    }

    return $monthObject;
}

/**
 * Validate a 24-hour time value.
 */
function normalize_booking_time(
    string $time
): ?string {
    $time = trim($time);

    if (
        !preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            $time
        )
    ) {
        return null;
    }

    return $time;
}

/**
 * Validate a booking date.
 */
function normalize_booking_date(
    string $date
): ?string {
    $dateObject =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

    $dateErrors =
        DateTimeImmutable::getLastErrors();

    if (
        $dateObject === false
        || (
            is_array(
                $dateErrors
            )
            && (
                $dateErrors[
                    'warning_count'
                ] > 0
                || $dateErrors[
                    'error_count'
                ] > 0
            )
        )
        || $dateObject->format(
            'Y-m-d'
        ) !== $date
    ) {
        return null;
    }

    $today =
        new DateTimeImmutable(
            'today'
        );

    $lastAllowedDate =
        (
            new DateTimeImmutable(
                'first day of this month'
            )
        )
            ->modify(
                '+11 months'
            )
            ->modify(
                'last day of this month'
            );

    if (
        $dateObject < $today
        || $dateObject
        > $lastAllowedDate
    ) {
        return null;
    }

    return $date;
}

/**
 * Confirm that the selected package or venue exists and is active.
 */
function booking_active_entity(
    PDO $connection,
    string $type,
    int $entityId
): ?array {
    $configuration =
        booking_entity_configuration(
            $type
        );

    if (
        $configuration === null
        || $entityId < 1
    ) {
        return null;
    }

    $sql = sprintf(
        "SELECT *
         FROM %s
         WHERE id = ?
         AND status = 'active'
         LIMIT 1",
        $configuration['table']
    );

    $statement =
        $connection->prepare(
            $sql
        );

    $statement->execute([
        $entityId,
    ]);

    $entity =
        $statement->fetch();

    return is_array($entity)
        ? $entity
        : null;
}

/**
 * Check whether a package or venue is available for one date and time.
 *
 * Historical bookings without start/end times block their entire date.
 */
function booking_slot_is_available(
    PDO $connection,
    string $type,
    int $entityId,
    string $date,
    string $startTime,
    string $endTime
): bool {
    $configuration =
        booking_entity_configuration(
            $type
        );

    $normalizedDate =
        normalize_booking_date(
            $date
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
        $configuration === null
        || $entityId < 1
        || $normalizedDate === null
        || $normalizedStart === null
        || $normalizedEnd === null
        || $normalizedEnd
            <= $normalizedStart
    ) {
        return false;
    }

    $today = date('Y-m-d');

    if (
        $normalizedDate === $today
        && $normalizedStart
            <= date('H:i')
    ) {
        return false;
    }

    $sql = sprintf(
        "SELECT COUNT(*)
         FROM bookings
         WHERE %s = :entity_id
         AND event_date = :event_date
         AND booking_status IN (
             'pending',
             'confirmed',
             'in_progress',
             'completed'
         )
         AND (
             start_time IS NULL
             OR end_time IS NULL
             OR (
                 start_time < :requested_end
                 AND end_time > :requested_start
             )
         )",
        $configuration[
            'booking_column'
        ]
    );

    $statement =
        $connection->prepare(
            $sql
        );

    $statement->execute([
        'entity_id' =>
            $entityId,

        'event_date' =>
            $normalizedDate,

        'requested_start' =>
            $normalizedStart
            . ':00',

        'requested_end' =>
            $normalizedEnd
            . ':00',
    ]);

    return (int) (
        $statement->fetchColumn()
    ) === 0;
}

/**
 * Build the availability matrix for one complete month.
 */
function booking_month_availability(
    PDO $connection,
    string $type,
    int $entityId,
    string $month,
    string $startTime,
    string $endTime
): array {
    $configuration =
        booking_entity_configuration(
            $type
        );

    $monthObject =
        booking_month_object(
            $month
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
        $configuration === null
        || $monthObject === null
    ) {
        throw new InvalidArgumentException(
            'Choose a month from the available booking period.'
        );
    }

    if (
        $normalizedStart === null
        || $normalizedEnd === null
    ) {
        throw new InvalidArgumentException(
            'Enter a valid start and end time.'
        );
    }

    if (
        $normalizedEnd
        <= $normalizedStart
    ) {
        throw new InvalidArgumentException(
            'End time must be later than start time on the same day.'
        );
    }

    if (
        booking_active_entity(
            $connection,
            $type,
            $entityId
        ) === null
    ) {
        throw new InvalidArgumentException(
            'The selected package or venue is no longer available.'
        );
    }

    $monthStart =
        $monthObject->format(
            'Y-m-01'
        );

    $monthEnd =
        $monthObject
            ->modify(
                'last day of this month'
            )
            ->format(
                'Y-m-d'
            );

    $sql = sprintf(
        "SELECT
            event_date,
            start_time,
            end_time
         FROM bookings
         WHERE %s = :entity_id
         AND event_date
            BETWEEN :month_start
            AND :month_end
         AND booking_status IN (
             'pending',
             'confirmed',
             'in_progress',
             'completed'
         )",
        $configuration[
            'booking_column'
        ]
    );

    $statement =
        $connection->prepare(
            $sql
        );

    $statement->execute([
        'entity_id' =>
            $entityId,

        'month_start' =>
            $monthStart,

        'month_end' =>
            $monthEnd,
    ]);

    $blockedDates = [];

    foreach (
        $statement->fetchAll()
        as $booking
    ) {
        $bookingDate = (string) (
            $booking[
                'event_date'
            ]
            ?? ''
        );

        $bookingStart =
            $booking[
                'start_time'
            ]
            ?? null;

        $bookingEnd =
            $booking[
                'end_time'
            ]
            ?? null;

        if ($bookingDate === '') {
            continue;
        }

        /*
         * Older bookings without times reserve
         * the whole event date.
         */
        if (
            $bookingStart === null
            || $bookingEnd === null
        ) {
            $blockedDates[
                $bookingDate
            ] = true;

            continue;
        }

        $bookingStart = substr(
            (string) $bookingStart,
            0,
            5
        );

        $bookingEnd = substr(
            (string) $bookingEnd,
            0,
            5
        );

        if (
            $bookingStart
                < $normalizedEnd
            && $bookingEnd
                > $normalizedStart
        ) {
            $blockedDates[
                $bookingDate
            ] = true;
        }
    }

    $totalDays = (int) (
        $monthObject->format(
            't'
        )
    );

    $today = date('Y-m-d');
    $currentTime = date('H:i');

    $days = [];

    for (
        $day = 1;
        $day <= $totalDays;
        $day++
    ) {
        $dateObject =
            $monthObject->setDate(
                (int) $monthObject
                    ->format('Y'),

                (int) $monthObject
                    ->format('m'),

                $day
            );

        $date =
            $dateObject->format(
                'Y-m-d'
            );

        $available = true;
        $reason = 'Open';

        if ($date < $today) {
            $available = false;
            $reason = 'Past';
        } elseif (
            $date === $today
            && $normalizedStart
                <= $currentTime
        ) {
            $available = false;
            $reason = 'Passed';
        } elseif (
            isset(
                $blockedDates[$date]
            )
        ) {
            $available = false;
            $reason = 'Booked';
        }

        $days[] = [
            'date' => $date,
            'day' => $day,

            'weekday' =>
                $dateObject->format(
                    'D'
                ),

            'available' =>
                $available,

            'status' =>
                $reason,
        ];
    }

    return [
        'month' =>
            $monthObject->format(
                'Y-m'
            ),

        'month_label' =>
            $monthObject->format(
                'F Y'
            ),

        'start_time' =>
            $normalizedStart,

        'end_time' =>
            $normalizedEnd,

        'days' =>
            $days,
    ];
}

/**
 * Build the booking form URL with the selected schedule.
 */
function booking_form_url(
    string $type,
    int $entityId,
    string $date,
    string $startTime,
    string $endTime
): string {
    $query = [
        'booking_type' =>
            $type,

        'event_date' =>
            $date,

        'start_time' =>
            $startTime,

        'end_time' =>
            $endTime,

        /*
         * Existing booking form currently uses event_time.
         */
        'event_time' =>
            $startTime,
    ];

    if ($type === 'package') {
        $query['package_id'] =
            $entityId;
    } else {
        $query['venue_id'] =
            $entityId;
    }

    return
        '/customer/booking.php?'
        . http_build_query(
            $query
        );
}