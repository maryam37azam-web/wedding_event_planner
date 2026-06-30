<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Check whether a gallery image exists inside the project.
 */
function gallery_image_exists(?string $imagePath): bool
{
    $imagePath = ltrim(trim((string) $imagePath), '/');

    if ($imagePath === '') {
        return false;
    }

    return is_file(dirname(__DIR__) . '/' . $imagePath);
}

/**
 * Return a safe gallery image URL.
 */
function gallery_image_url(?string $imagePath): string
{
    $imagePath = ltrim(trim((string) $imagePath), '/');

    if (
        $imagePath === ''
        || !gallery_image_exists($imagePath)
    ) {
        return url('/assets/icons/icon-512.png');
    }

    return url('/' . $imagePath);
}

/**
 * Convert a PHP upload error code into a helpful message.
 */
function gallery_upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE =>
            'The selected gallery image is larger than the server upload limit.',

        UPLOAD_ERR_PARTIAL =>
            'The gallery image upload was interrupted. Please select the image again.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'The server temporary upload folder is missing.',

        UPLOAD_ERR_CANT_WRITE =>
            'The server could not write the gallery image to disk.',

        UPLOAD_ERR_EXTENSION =>
            'A server extension stopped the gallery image upload.',

        default =>
            'The gallery image could not be uploaded.',
    };
}

/**
 * Upload and validate a gallery image.
 */
function upload_gallery_image(
    array $file,
    string $prefix = 'gallery'
): ?string {
    $uploadError = (int) (
        $file['error']
        ?? UPLOAD_ERR_NO_FILE
    );

    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            gallery_upload_error_message(
                $uploadError
            )
        );
    }

    $temporaryPath = (string) (
        $file['tmp_name']
        ?? ''
    );

    $fileSize = (int) (
        $file['size']
        ?? 0
    );

    if (
        $temporaryPath === ''
        || !is_uploaded_file($temporaryPath)
    ) {
        throw new RuntimeException(
            'The selected gallery image is invalid.'
        );
    }

    if (
        $fileSize < 1
        || $fileSize > 5 * 1024 * 1024
    ) {
        throw new RuntimeException(
            'Each gallery image must be 5 MB or smaller.'
        );
    }

    $mimeDetector = new finfo(
        FILEINFO_MIME_TYPE
    );

    $mimeType = $mimeDetector->file(
        $temporaryPath
    );

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (
        !is_string($mimeType)
        || !isset($allowedTypes[$mimeType])
        || @getimagesize($temporaryPath) === false
    ) {
        throw new RuntimeException(
            'Gallery images must be valid JPG, PNG or WEBP files.'
        );
    }

    $uploadDirectory =
        dirname(__DIR__)
        . '/uploads/gallery';

    if (
        !is_dir($uploadDirectory)
        && !mkdir(
            $uploadDirectory,
            0755,
            true
        )
    ) {
        throw new RuntimeException(
            'The gallery upload folder could not be created.'
        );
    }

    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException(
            'The gallery upload folder is not writable.'
        );
    }

    $safePrefix = preg_replace(
        '/[^a-z0-9_-]/i',
        '_',
        $prefix
    );

    if (
        !is_string($safePrefix)
        || $safePrefix === ''
    ) {
        $safePrefix = 'gallery';
    }

    $fileName = sprintf(
        '%s_%s.%s',
        $safePrefix,
        bin2hex(random_bytes(10)),
        $allowedTypes[$mimeType]
    );

    $destination =
        $uploadDirectory
        . DIRECTORY_SEPARATOR
        . $fileName;

    if (
        !move_uploaded_file(
            $temporaryPath,
            $destination
        )
    ) {
        throw new RuntimeException(
            'The gallery image could not be saved.'
        );
    }

    return 'uploads/gallery/' . $fileName;
}

/**
 * Delete only uploaded files from uploads/gallery.
 */
function delete_gallery_image(
    ?string $imagePath
): void {
    $imagePath = ltrim(
        trim((string) $imagePath),
        '/'
    );

    if (
        $imagePath === ''
        || !str_starts_with(
            $imagePath,
            'uploads/gallery/'
        )
    ) {
        return;
    }

    $absolutePath =
        dirname(__DIR__)
        . '/'
        . $imagePath;

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

/**
 * Return a safe active/inactive value.
 */
function gallery_status_value(
    mixed $status
): string {
    return strtolower(
        trim((string) $status)
    ) === 'active'
        ? 'active'
        : 'inactive';
}

/**
 * Return the number of usable photos for one record.
 */
function gallery_photo_count(
    array $galleryItem
): int {
    $count = gallery_image_exists(
        $galleryItem['image']
        ?? null
    )
        ? 1
        : 0;

    if (
        gallery_image_exists(
            $galleryItem['image_two']
            ?? null
        )
    ) {
        $count++;
    }

    return max(1, $count);
}

/**
 * Format a gallery date safely.
 */
function gallery_display_date(
    mixed $date
): string {
    $timestamp = strtotime(
        (string) $date
    );

    return $timestamp === false
        ? 'Date unavailable'
        : date('d M Y', $timestamp);
}

/**
 * Return the main gallery page for a staff role.
 */
function gallery_page_path_for_role(
    string $role
): string {
    return match ($role) {
        'admin' =>
            '/admin/gallery.php',

        'booking_manager' =>
            '/booking_manager/gallery.php',

        'event_manager' =>
            '/event_manager/gallery.php',

        default =>
            '/auth/staff_login.php',
    };
}

/**
 * Return a readable staff role label.
 */
function gallery_role_label(
    string $role
): string {
    return match ($role) {
        'admin' =>
            'Admin',

        'booking_manager' =>
            'Booking Manager',

        'event_manager' =>
            'Event Manager',

        default =>
            'Staff',
    };
}