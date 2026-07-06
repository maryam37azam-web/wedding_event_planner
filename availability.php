<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__
    . '/includes/booking_availability_helpers.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

$response = [
    'success' => false,

    'message' =>
        'The availability request could not be completed.',
];

try {
    $type = strtolower(
        trim(
            (string) (
                $_GET['type']
                ?? ''
            )
        )
    );

    $entityId = max(
        0,
        (int) (
            $_GET['id']
            ?? 0
        )
    );

    $month = trim(
        (string) (
            $_GET['month']
            ?? ''
        )
    );

    $startTime = trim(
        (string) (
            $_GET['start_time']
            ?? ''
        )
    );

    $endTime = trim(
        (string) (
            $_GET['end_time']
            ?? ''
        )
    );

    $entity =
        booking_active_entity(
            db(),
            $type,
            $entityId
        );

    if ($entity === null) {
        throw new InvalidArgumentException(
            'The selected package or venue is not available.'
        );
    }

    $availability =
        booking_month_availability(
            db(),
            $type,
            $entityId,
            $month,
            $startTime,
            $endTime
        );

    $response = [
        'success' => true,

        'message' =>
            'Availability loaded successfully.',

        'entity' => [
            'id' =>
                $entityId,

            'type' =>
                $type,

            'name' =>
                (string) (
                    $entity['name']
                    ?? ''
                ),
        ],

        'availability' =>
            $availability,
    ];
} catch (
    InvalidArgumentException $exception
) {
    http_response_code(422);

    $response['message'] =
        $exception->getMessage();
} catch (Throwable $exception) {
    http_response_code(500);

    $response['message'] =
        APP_DEBUG
            ? 'Availability check failed: '
                . $exception
                    ->getMessage()
            : 'Availability could not be checked. Please try again.';
}

echo json_encode(
    $response,
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_THROW_ON_ERROR
);