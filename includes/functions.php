<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

/**
 * Escape text before displaying it in HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Create a complete application URL.
 */
function url(string $path = ''): string
{
    if ($path === '') {
        return APP_URL;
    }

    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * Redirect to another project page.
 */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Check whether the current request is POST.
 */
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Generate or return the current CSRF token.
 */
function csrf_token(): string
{
    if (
        empty($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Return a hidden CSRF form input.
 */
function csrf_field(): string
{
    return sprintf(
        '<input type="hidden" name="csrf_token" value="%s">',
        e(csrf_token())
    );
}

/**
 * Validate a submitted CSRF token.
 */
function verify_csrf(?string $token): bool
{
    if (
        empty($token)
        || empty($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
    ) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Save a temporary message in the session.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Read and remove a temporary message.
 */
function get_flash(): ?array
{
    if (empty($_SESSION['flash_message'])) {
        return null;
    }

    $message = $_SESSION['flash_message'];

    unset($_SESSION['flash_message']);

    return is_array($message) ? $message : null;
}

/**
 * Hide part of an email address.
 */
function mask_email(string $email): string
{
    $parts = explode('@', $email, 2);

    if (count($parts) !== 2) {
        return $email;
    }

    [$username, $domain] = $parts;

    $visibleLength = min(2, strlen($username));
    $visible = substr($username, 0, $visibleLength);

    $hiddenLength = max(3, strlen($username) - $visibleLength);

    return $visible
        . str_repeat('*', $hiddenLength)
        . '@'
        . $domain;
}