<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../config/database.php';

require_role('admin');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $type = strtolower(
        trim(
            (string) (
                $_GET['type']
                ?? 'catering'
            )
        )
    );

    if (!in_array($type, ['catering', 'music'], true)) {
        throw new InvalidArgumentException(
            'Invalid service type.'
        );
    }

    $statement = db()->prepare(
        'SELECT
            id,
            name,
            category,
            description,
            price
         FROM services
         WHERE service_type = ?
         AND status = ?
         ORDER BY
            category ASC,
            name ASC'
    );

    $statement->execute([
        $type,
        'active',
    ]);

    echo json_encode(
        [
            'success' => true,
            'items' => $statement->fetchAll(),
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode(
        [
            'success' => false,
            'message' => $exception->getMessage(),
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );
} catch (Throwable $exception) {
    http_response_code(500);

    echo json_encode(
        [
            'success' => false,

            'message' => APP_DEBUG
                ? 'Service catalogue failed: '
                    . $exception->getMessage()
                : 'Service catalogue could not be loaded.',
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );
}