<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Return package image fields used by the current interface.
 */
function package_image_fields(): array
{
    return [
        'main_image' => 'Main Package Image',
        'image_one' => 'Gallery Image 1',
        'image_two' => 'Gallery Image 2',
        'image_three' => 'Gallery Image 3',
    ];
}

/**
 * Convert a multi-line feature value into a clean array.
 */
function package_feature_lines(?string $features): array
{
    $features = trim((string) $features);

    if ($features === '') {
        return [];
    }

    $lines = preg_split('/\r\n|\r|\n/', $features);

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
 * Check whether an uploaded package image still exists.
 */
function package_image_exists(?string $path): bool
{
    $path = ltrim(trim((string) $path), '/');

    if ($path === '') {
        return false;
    }

    $absolutePath = dirname(__DIR__) . '/' . $path;

    return is_file($absolutePath);
}

/**
 * Return a safe image URL. Missing images use the application logo.
 */
function package_image_url(?string $path): string
{
    $path = ltrim(trim((string) $path), '/');

    if ($path === '' || !package_image_exists($path)) {
        return url('/assets/icons/icon-512.png');
    }

    return url('/' . $path);
}

/**
 * Convert a PHP upload error code into a helpful message.
 */
function package_upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE => 'The selected package image is larger than the server upload limit.',
        UPLOAD_ERR_PARTIAL => 'The package image upload was interrupted. Please select the image again.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload folder is missing.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the package image to disk.',
        UPLOAD_ERR_EXTENSION => 'A server extension stopped the package image upload.',
        default => 'The package image could not be uploaded.',
    };
}

/**
 * Upload and validate a package image.
 */
function upload_package_image(array $file, string $prefix): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(package_upload_error_message($error));
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $fileSize = (int) ($file['size'] ?? 0);

    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        throw new RuntimeException('The selected package image is invalid.');
    }

    if ($fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
        throw new RuntimeException('Each package image must be 5 MB or smaller.');
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
        throw new RuntimeException(
            'Package images must be valid JPG, PNG or WEBP files.'
        );
    }

    $uploadDirectory = dirname(__DIR__) . '/uploads/packages';

    if (
        !is_dir($uploadDirectory)
        && !mkdir($uploadDirectory, 0755, true)
    ) {
        throw new RuntimeException(
            'The package image folder could not be created.'
        );
    }

    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException(
            'The package image folder is not writable.'
        );
    }

    $safePrefix = preg_replace(
        '/[^a-z0-9_-]/i',
        '_',
        $prefix
    );

    if (!is_string($safePrefix) || $safePrefix === '') {
        $safePrefix = 'package';
    }

    $fileName = sprintf(
        '%s_%s.%s',
        $safePrefix,
        bin2hex(random_bytes(10)),
        $allowedTypes[$mimeType]
    );

    $destination = $uploadDirectory
        . DIRECTORY_SEPARATOR
        . $fileName;

    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException(
            'The package image could not be saved.'
        );
    }

    return 'uploads/packages/' . $fileName;
}

/**
 * Delete only package images stored inside uploads/packages.
 */
function delete_package_image(?string $path): void
{
    $path = ltrim(trim((string) $path), '/');

    if (
        $path === ''
        || !str_starts_with($path, 'uploads/packages/')
    ) {
        return;
    }

    $absolutePath = dirname(__DIR__) . '/' . $path;

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

/**
 * Format a package price.
 */
function format_package_price(float $price): string
{
    return 'Rs. ' . number_format($price, 0);
}

/**
 * Return the package venue name from any supported package column.
 */
function package_venue_name(array $package): string
{
    foreach (
        [
            'venue_name',
            'package_venue_name',
        ] as $column
    ) {
        $value = trim(
            (string) (
                $package[$column]
                ?? ''
            )
        );

        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/**
 * Return the package venue location from any supported package column.
 */
function package_venue_location(array $package): string
{
    foreach (
        [
            'venue_location',
            'package_venue_location',
            'location',
        ] as $column
    ) {
        $value = trim(
            (string) (
                $package[$column]
                ?? ''
            )
        );

        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/**
 * Return a clean customer-facing venue label for a package.
 */
function package_venue_display(array $package): string
{
    $venueName = package_venue_name(
        $package
    );

    $venueLocation = package_venue_location(
        $package
    );

    if (
        $venueName !== ''
        && $venueLocation !== ''
    ) {
        if (
            str_contains(
                mb_strtolower(
                    $venueLocation
                ),
                mb_strtolower(
                    $venueName
                )
            )
        ) {
            return $venueLocation;
        }

        return $venueName
            . ' — '
            . $venueLocation;
    }

    if ($venueLocation !== '') {
        return $venueLocation;
    }

    if ($venueName !== '') {
        return $venueName;
    }

    return '';
}

/**
 * Return the current Admin package-card page type.
 */
function admin_package_card_page_type(): string
{
    $scriptName = str_replace(
        '\\',
        '/',
        (string) ($_SERVER['SCRIPT_NAME'] ?? '')
    );

    if (str_ends_with($scriptName, '/admin/packages.php')) {
        return 'manage';
    }

    if (str_ends_with($scriptName, '/admin/all_packages.php')) {
        return 'all';
    }

    return '';
}

/**
 * Return the package description requested for each page.
 */
function package_card_description(array $package): string
{
    $description = trim((string) ($package['description'] ?? ''));

    if ($description === '') {
        $shortDescription = trim(
            (string) ($package['short_description'] ?? '')
        );

        $description = $shortDescription !== ''
            ? $shortDescription
            : 'No package description has been added yet.';
    }

    $pageType = admin_package_card_page_type();

    if ($pageType === '') {
        return $description;
    }

    $price = format_package_price(
        (float) ($package['price'] ?? 0)
    );

    return $price . "\n" . $description;
}
