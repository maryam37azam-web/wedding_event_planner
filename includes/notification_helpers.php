<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Create one notification
|--------------------------------------------------------------------------
*/

function create_notification(
    ?int $userId,
    ?string $userRole,
    string $title,
    string $message,
    string $notificationType = 'general',
    ?string $targetUrl = null
): bool {
    $title = trim($title);
    $message = trim($message);
    $notificationType = strtolower(
        trim($notificationType)
    );

    $userRole = $userRole !== null
        ? strtolower(trim($userRole))
        : null;

    $targetUrl = $targetUrl !== null
        ? trim($targetUrl)
        : null;

    if ($title === '' || $message === '') {
        return false;
    }

    if ($notificationType === '') {
        $notificationType = 'general';
    }

    if ($targetUrl === '') {
        $targetUrl = null;
    }

    try {
        $statement = db()->prepare(
    'INSERT INTO notifications (
        recipient_id,
        user_id,
        user_role,
        title,
        message,
        notification_type,
        target_url,
        is_read,
        created_at,
        updated_at
     ) VALUES (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        0,
        NOW(),
        NOW()
     )'
);

return $statement->execute([
    $userId,
    $userId,
    $userRole,
    mb_substr($title, 0, 180),
    $message,
    mb_substr($notificationType, 0, 40),
    $targetUrl,
]);
    } catch (Throwable $exception) {
        if (
            defined('APP_DEBUG')
            && APP_DEBUG
        ) {
            error_log(
                'Notification creation failed: '
                . $exception->getMessage()
            );
        }

        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Notify every user with a particular role
|--------------------------------------------------------------------------
*/

function create_role_notifications(
    string $role,
    string $title,
    string $message,
    string $notificationType = 'general',
    ?string $targetUrl = null
): int {
    $role = strtolower(trim($role));

    $allowedRoles = [
        'admin',
        'event_manager',
        'booking_manager',
    ];

    if (
        !in_array(
            $role,
            $allowedRoles,
            true
        )
    ) {
        return 0;
    }

    try {
        $userStatement = db()->prepare(
            'SELECT id
             FROM users
             WHERE role = ?'
        );

        $userStatement->execute([
            $role,
        ]);

        $userIds = $userStatement->fetchAll(
            PDO::FETCH_COLUMN
        );

        $createdCount = 0;

        foreach ($userIds as $userId) {
            $created = create_notification(
                (int) $userId,
                $role,
                $title,
                $message,
                $notificationType,
                $targetUrl
            );

            if ($created) {
                $createdCount++;
            }
        }

        return $createdCount;
    } catch (Throwable $exception) {
        if (
            defined('APP_DEBUG')
            && APP_DEBUG
        ) {
            error_log(
                'Role notification failed: '
                . $exception->getMessage()
            );
        }

        return 0;
    }
}

/*
|--------------------------------------------------------------------------
| Count unread notifications
|--------------------------------------------------------------------------
*/

function unread_notification_count(
    int $userId,
    string $userRole
): int {
    try {
        $statement = db()->prepare(
            'SELECT COUNT(*)
             FROM notifications
             WHERE (
                user_id = ?
                OR (
                    user_id IS NULL
                    AND user_role = ?
                )
             )
             AND is_read = 0'
        );

        $statement->execute([
            $userId,
            $userRole,
        ]);

        return (int) $statement->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}