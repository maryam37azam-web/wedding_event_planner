<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

/**
 * Return the reusable PDO database connection.
 */
function db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = (string) env_value('DB_HOST', '127.0.0.1');
    $port = (string) env_value('DB_PORT', '3306');

    $database = (string) env_value(
        'DB_NAME',
        'wedding_event_planner'
    );

    $username = (string) env_value('DB_USER', 'root');
    $password = (string) env_value('DB_PASSWORD', '');

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );

    try {
        $connection = new PDO(
            $dsn,
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $connection;
    } catch (PDOException $exception) {
        if (APP_DEBUG) {
            exit(
                'Database connection failed: '
                . htmlspecialchars(
                    $exception->getMessage(),
                    ENT_QUOTES,
                    'UTF-8'
                )
            );
        }

        exit('Database connection failed.');
    }
}