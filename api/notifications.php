<?php
/**
 * notifications.php  (أي مستخدم مسجّل)
 * GET  : عرض إشعارات المستخدم الحالي
 * POST : تعليم إشعار كمقروء  { "notification_id": 5 }
 */
require_once __DIR__ . '/helpers.php';
require_login();

$user_id = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = get_json_input();
        $nid = (int)($in['notification_id'] ?? 0);
        if ($nid <= 0) {
            json_response(['success' => false, 'message' => 'معرّف الإشعار مطلوب'], 422);
        }
        $stmt = $pdo->prepare(
            'UPDATE notification SET is_read = TRUE WHERE notification_id = :nid AND user_id = :uid'
        );
        $stmt->execute([':nid' => $nid, ':uid' => $user_id]);
        json_response(['success' => true, 'message' => 'تم تعليم الإشعار كمقروء']);
    }

    // GET
    $stmt = $pdo->prepare(
        'SELECT notification_id, message, is_read, sent_at
         FROM notification WHERE user_id = :uid ORDER BY sent_at DESC'
    );
    $stmt->execute([':uid' => $user_id]);
    $rows = $stmt->fetchAll();

    $unread = 0;
    foreach ($rows as $r) { if (!$r['is_read']) $unread++; }

    json_response(['success' => true, 'unread' => $unread, 'notifications' => $rows]);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
