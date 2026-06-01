<?php
/**
 * book_update_status.php  (أدمن فقط)
 * تغيير حالة الكتاب يدويًا (مثلاً وضعه Damaged)
 * يستقبل: book_id, status  (Available | Borrowed | Damaged)
 */
require_once __DIR__ . '/helpers.php';
require_admin();

$in = get_json_input();

$book_id = (int)($in['book_id'] ?? 0);
$status  = trim($in['status'] ?? '');

$allowed = ['Available', 'Borrowed', 'Damaged'];
if ($book_id <= 0 || !in_array($status, $allowed, true)) {
    json_response(['success' => false, 'message' => 'بيانات غير صحيحة'], 422);
}

try {
    $stmt = $pdo->prepare('UPDATE book SET status = :status WHERE book_id = :id');
    $stmt->execute([':status' => $status, ':id' => $book_id]);

    if ($stmt->rowCount() === 0) {
        json_response(['success' => false, 'message' => 'الكتاب غير موجود'], 404);
    }
    json_response(['success' => true, 'message' => 'تم تحديث حالة الكتاب']);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
