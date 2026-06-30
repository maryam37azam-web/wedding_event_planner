<?php

declare(strict_types=1);

use Dotenv\Dotenv;

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(ROOT_PATH);
$dotenv->safeLoad();

/**
 * Read a value from the .env file.
 */
if (!function_exists('env_value')) {
    function env_value(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key]
            ?? $_SERVER[$key]
            ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!defined('APP_NAME')) {
    define(
        'APP_NAME',
        (string) env_value('APP_NAME', 'Wedding Event Planner')
    );
}

if (!defined('APP_URL')) {
    define(
        'APP_URL',
        rtrim(
            (string) env_value(
                'APP_URL',
                'http://localhost/wedding_event_planner'
            ),
            '/'
        )
    );
}

if (!defined('APP_DEBUG')) {
    define(
        'APP_DEBUG',
        (bool) env_value('APP_DEBUG', false)
    );
}

date_default_timezone_set(
    (string) env_value('APP_TIMEZONE', 'Asia/Karachi')
);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';

    session_name(
        (string) env_value(
            'SESSION_NAME',
            'wedding_event_session'
        )
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}