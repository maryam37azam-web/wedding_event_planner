<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Return venue image fields used by the current interface.
 */
function venue_image_fields(): array
{
    return [
        'main_image' => 'Main Venue Image',
        'image_one' => 'Gallery Image 1',
        'image_two' => 'Gallery Image 2',
        'image_three' => 'Gallery Image 3',
    ];
}

/**
 * Convert venue facilities into clean unique lines.
 */
function venue_facility_lines(?string $facilities): array
{
    $facilities = trim((string) $facilities);

    if ($facilities === '') {
        return [];
    }

    $lines = preg_split('/\r\n|\r|\n/', $facilities);

    if (!is_array($lines)) {
        return [];
    }

    $cleanLines = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line !== '') {
            $cleanLines[] = $line;
        }
    }

    return array_values(array_unique($cleanLines));
}

/**
 * Check whether an uploaded venue image still exists.
 */
function venue_image_exists(?string $path): bool
{
    $path = ltrim(trim((string) $path), '/');

    if ($path === '') {
        return false;
    }

    return is_file(dirname(__DIR__) . '/' . $path);
}

/**
 * Return a safe venue image URL. Missing images use the app icon.
 */
function venue_image_url(?string $path): string
{
    $path = ltrim(trim((string) $path), '/');

    if ($path === '' || !venue_image_exists($path)) {
        return url('/assets/icons/icon-512.png');
    }

    return url('/' . $path);
}

/**
 * Convert a PHP upload error into a useful message.
 */
function venue_upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE => 'The selected venue image is larger than the server upload limit.',
        UPLOAD_ERR_PARTIAL => 'The venue image upload was interrupted. Please select it again.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload folder is missing.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the venue image to disk.',
        UPLOAD_ERR_EXTENSION => 'A server extension stopped the venue image upload.',
        default => 'The venue image could not be uploaded.',
    };
}

/**
 * Upload and validate a venue image.
 */
function upload_venue_image(array $file, string $prefix): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(venue_upload_error_message($error));
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $fileSize = (int) ($file['size'] ?? 0);

    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        throw new RuntimeException('The selected venue image is invalid.');
    }

    if ($fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
        throw new RuntimeException('Each venue image must be 5 MB or smaller.');
    }

    $mimeDetector = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $mimeDetector->file($temporaryPath);

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
        throw new RuntimeException('Venue images must be valid JPG, PNG or WEBP files.');
    }

    $uploadDirectory = dirname(__DIR__) . '/uploads/venues';

    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
        throw new RuntimeException('The venue image folder could not be created.');
    }

    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException('The venue image folder is not writable.');
    }

    $safePrefix = preg_replace('/[^a-z0-9_-]/i', '_', $prefix);

    if (!is_string($safePrefix) || $safePrefix === '') {
        $safePrefix = 'venue';
    }

    $fileName = sprintf(
        '%s_%s.%s',
        $safePrefix,
        bin2hex(random_bytes(10)),
        $allowedTypes[$mimeType]
    );

    $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('The venue image could not be saved.');
    }

    return 'uploads/venues/' . $fileName;
}

/**
 * Delete only files stored inside uploads/venues.
 */
function delete_venue_image(?string $path): void
{
    $path = ltrim(trim((string) $path), '/');

    if ($path === '' || !str_starts_with($path, 'uploads/venues/')) {
        return;
    }

    $absolutePath = dirname(__DIR__) . '/' . $path;

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

/**
 * Format venue price.
 */
function format_venue_price(float $price): string
{
    return 'Rs. ' . number_format($price, 0);
}

/**
 * Return the complete venue description for management cards.
 */
function venue_card_description(array $venue): string
{
    $description = trim((string) ($venue['description'] ?? ''));

    return $description !== ''
        ? $description
        : 'No venue description has been added yet.';
}