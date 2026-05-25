<?php
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/feature-schema.php';

function create_notification(PDO $db, int $userId, string $type, string $title, string $message): void
{
    ensure_notification_schema($db);

    $stmt = $db->prepare('
        INSERT INTO NOTIFICATION (UserID, Type, Title, Message)
        VALUES (:uid, :type, :title, :msg)
    ');
    $stmt->execute([':uid' => $userId, ':type' => $type, ':title' => $title, ':msg' => $message]);
}

function notify(PDO $db, int $userId, string $type, string $title, string $message): void
{
    create_notification($db, $userId, $type, $title, $message);

    $row = $db->prepare('SELECT Email, Name FROM USER WHERE UserID = :id');
    $row->execute([':id' => $userId]);
    $user = $row->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['Email'])) {
        send_gym_email($user['Email'], $user['Name'], $title, $message);
    }
}
