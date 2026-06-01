<?php
/**
 * book_delete.php  (أدمن فقط)
 * حذف كتاب
 * يستقبل: book_id
 */
require_once __DIR__ . '/helpers.php';
require_admin();

$in = get_json_input();
$book_id = (int)($in['book_id'] ?? 0);

if ($book_id <= 0) {
    json_response(['success' => false, 'message' => 'معرّف الكتاب مطلوب'], 422);
}

try {
    $stmt = $pdo->prepare('DELETE FROM book WHERE book_id = :id');
    $stmt->execute([':id' => $book_id]);

    if ($stmt->rowCount() === 0) {
        json_response(['success' => false, 'message' => 'الكتاب غير موجود'], 404);
    }
    json_response(['success' => true, 'message' => 'تم حذف الكتاب']);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
