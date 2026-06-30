<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shared read-only gallery helpers
|--------------------------------------------------------------------------
| These helpers support the gallery table names and column variations that
| may exist in the Wedding Event Planner database.
*/

function gallery_display_table(
    PDO $connection
): ?string {
    $allowedTables = [
        'gallery',
        'gallery_images',
        'gallery_items',
        'event_gallery',
        'galleries',
    ];

    try {
        $databaseTables = $connection
            ->query('SHOW TABLES')
            ->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $exception) {
        return null;
    }

    foreach ($allowedTables as $allowedTable) {
        if (
            in_array(
                $allowedTable,
                $databaseTables,
                true
            )
        ) {
            return $allowedTable;
        }
    }

    return null;
}

function gallery_display_value(
    array $row,
    array $possibleColumns,
    mixed $default = ''
): mixed {
    foreach ($possibleColumns as $column) {
        if (
            array_key_exists(
                $column,
                $row
            )
            && $row[$column] !== null
        ) {
            return $row[$column];
        }
    }

    return $default;
}

function gallery_display_is_active(
    mixed $status
): bool {
    if (is_bool($status)) {
        return $status;
    }

    if (is_numeric($status)) {
        return (int) $status === 1;
    }

    $normalizedStatus = strtolower(
        trim(
            (string) $status
        )
    );

    if ($normalizedStatus === '') {
        return true;
    }

    return in_array(
        $normalizedStatus,
        [
            'active',
            'visible',
            'published',
            'approved',
            'enabled',
            'yes',
            'true',
            '1',
        ],
        true
    );
}

function gallery_display_rows(
    PDO $connection
): array {
    $galleryTable =
        gallery_display_table(
            $connection
        );

    if ($galleryTable === null) {
        return [];
    }

    try {
        $rawRows = $connection
            ->query(
                'SELECT * FROM `'
                . $galleryTable
                . '`'
            )
            ->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }

    $galleryRows = [];

    foreach ($rawRows as $index => $row) {
        $recordId = (int) gallery_display_value(
            $row,
            [
                'id',
                'gallery_id',
                'image_id',
            ],
            $index + 1
        );

        $title = trim(
            (string) gallery_display_value(
                $row,
                [
                    'title',
                    'gallery_title',
                    'name',
                    'image_title',
                ]
            )
        );

        if ($title === '') {
            $title = 'Wedding Gallery Image';
        }

        $eventType = trim(
            (string) gallery_display_value(
                $row,
                [
                    'event_type',
                    'category',
                    'event_category',
                    'type',
                ]
            )
        );

        if ($eventType === '') {
            $eventType = 'Wedding Event';
        }

        $description = trim(
            (string) gallery_display_value(
                $row,
                [
                    'description',
                    'details',
                    'caption',
                    'image_description',
                ]
            )
        );

        $firstImage = trim(
            (string) gallery_display_value(
                $row,
                [
                    'image_one',
                    'first_image',
                    'main_image',
                    'image1',
                    'image',
                    'image_path',
                ]
            )
        );

        $secondImage = trim(
            (string) gallery_display_value(
                $row,
                [
                    'image_two',
                    'second_image',
                    'image2',
                ]
            )
        );

        $thirdImage = trim(
            (string) gallery_display_value(
                $row,
                [
                    'image_three',
                    'third_image',
                    'image3',
                ]
            )
        );

        $status = gallery_display_value(
            $row,
            [
                'status',
                'is_active',
                'active',
                'visibility',
            ],
            'active'
        );

        $createdAt = trim(
            (string) gallery_display_value(
                $row,
                [
                    'created_at',
                    'uploaded_at',
                    'date_added',
                    'created_on',
                    'upload_date',
                ]
            )
        );

        $uploadedBy = trim(
            (string) gallery_display_value(
                $row,
                [
                    'uploaded_by_name',
                    'creator_name',
                    'created_by_name',
                    'staff_name',
                ],
                'Event Manager'
            )
        );

        if ($uploadedBy === '') {
            $uploadedBy = 'Event Manager';
        }

        $galleryRows[] = [
            'id' => $recordId,
            'title' => $title,
            'event_type' => $eventType,
            'description' => $description,
            'image_one' => $firstImage,
            'image_two' => $secondImage,
            'image_three' => $thirdImage,
            'active' =>
                gallery_display_is_active(
                    $status
                ),
            'created_at' => $createdAt,
            'uploaded_by' => $uploadedBy,
        ];
    }

    usort(
        $galleryRows,
        static function (
            array $firstRow,
            array $secondRow
        ): int {
            $firstTime = strtotime(
                (string) (
                    $firstRow['created_at']
                    ?? ''
                )
            ) ?: 0;

            $secondTime = strtotime(
                (string) (
                    $secondRow['created_at']
                    ?? ''
                )
            ) ?: 0;

            if ($firstTime === $secondTime) {
                return (int) (
                    $secondRow['id']
                    ?? 0
                ) <=> (int) (
                    $firstRow['id']
                    ?? 0
                );
            }

            return $secondTime
                <=> $firstTime;
        }
    );

    return $galleryRows;
}

function gallery_display_image_url(
    ?string $imagePath
): string {
    $imagePath = trim(
        (string) $imagePath
    );

    if ($imagePath === '') {
        return url(
            '/assets/icons/icon-512.png'
        );
    }

    if (
        preg_match(
            '/^(https?:)?\/\//i',
            $imagePath
        )
        || str_starts_with(
            $imagePath,
            'data:'
        )
        || str_starts_with(
            $imagePath,
            'blob:'
        )
    ) {
        return $imagePath;
    }

    return url(
        '/'
        . ltrim(
            $imagePath,
            '/'
        )
    );
}

function gallery_display_images(
    array $galleryRow
): array {
    $images = [];

    foreach (
        [
            'image_one',
            'image_two',
            'image_three',
        ] as $imageColumn
    ) {
        $imagePath = trim(
            (string) (
                $galleryRow[$imageColumn]
                ?? ''
            )
        );

        if ($imagePath === '') {
            continue;
        }

        $imageUrl =
            gallery_display_image_url(
                $imagePath
            );

        if (
            !in_array(
                $imageUrl,
                $images,
                true
            )
        ) {
            $images[] = $imageUrl;
        }
    }

    if ($images === []) {
        $images[] =
            gallery_display_image_url(
                null
            );
    }

    return $images;
}

function gallery_display_date(
    ?string $dateValue,
    string $format = 'd M Y'
): string {
    $dateValue = trim(
        (string) $dateValue
    );

    if ($dateValue === '') {
        return 'Not available';
    }

    $timestamp = strtotime(
        $dateValue
    );

    if ($timestamp === false) {
        return $dateValue;
    }

    return date(
        $format,
        $timestamp
    );
}