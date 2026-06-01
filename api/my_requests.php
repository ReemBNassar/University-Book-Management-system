<?php
/**
 * my_requests.php  (طالب)
 * عرض طلبات الطالب الحالية مع تفاصيل الكتاب
 */
require_once __DIR__ . '/helpers.php';
require_login();

$student_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT r.request_id, r.status, r.request_date, r.return_deadline,
                b.book_id, b.title, b.author, b.department
         FROM borrowing_request r
         JOIN book b ON b.book_id = r.book_id
         WHERE r.borrower_id = :uid
         ORDER BY r.request_date DESC'
    );
    $stmt->execute([':uid' => $student_id]);
    $requests = $stmt->fetchAll();

    json_response(['success' => true, 'requests' => $requests]);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
