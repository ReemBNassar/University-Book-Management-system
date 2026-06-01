<?php
/**
 * request_cancel.php  (طالب)
 * إلغاء طلب استعارة معلّق (Pending فقط)
 * يستقبل: request_id
 */
require_once __DIR__ . '/helpers.php';
require_login();

$in = get_json_input();
$request_id = (int)($in['request_id'] ?? 0);
$student_id = $_SESSION['user_id'];

if ($request_id <= 0) {
    json_response(['success' => false, 'message' => 'معرّف الطلب مطلوب'], 422);
}

try {
    // نتأكد إن الطلب يخص نفس الطالب وحالته Pending
    $stmt = $pdo->prepare(
        "SELECT status FROM borrowing_request
         WHERE request_id = :rid AND borrower_id = :uid"
    );
    $stmt->execute([':rid' => $request_id, ':uid' => $student_id]);
    $req = $stmt->fetch();

    if (!$req) {
        json_response(['success' => false, 'message' => 'الطلب غير موجود'], 404);
    }
    if ($req['status'] !== 'Pending') {
        json_response(['success' => false, 'message' => 'لا يمكن إلغاء هذا الطلب (تمت معالجته بالفعل)'], 409);
    }

    $stmt = $pdo->prepare('DELETE FROM borrowing_request WHERE request_id = :rid');
    $stmt->execute([':rid' => $request_id]);

    json_response(['success' => true, 'message' => 'تم إلغاء الطلب']);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
