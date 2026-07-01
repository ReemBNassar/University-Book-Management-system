<?php


require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];


if ($method === 'GET') {
    requireLogin();

    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT notification_id, message, is_read, sent_at
            FROM notification
            WHERE user_id = :uid
            ORDER BY sent_at DESC
            LIMIT 20
        ");
        $stmt->execute([':uid' => $user_id]);
        $notifications = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE user_id = :uid AND is_read = FALSE");
        $stmt->execute([':uid' => $user_id]);
        $unread_count = $stmt->fetchColumn();

        sendJSON([
            'success'       => true,
            'notifications' => $notifications,
            'unread_count'  => $unread_count,
        ]);
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
    }
}


if ($method === 'PUT') {
    requireLogin();

    $user_id = $_SESSION['user_id'];

    try {
        $pdo->prepare("UPDATE notification SET is_read = TRUE WHERE user_id = :uid")
            ->execute([':uid' => $user_id]);
        sendJSON(['success' => true, 'message' => 'تم تعليم جميع الإشعارات كمقروءة']);
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>
