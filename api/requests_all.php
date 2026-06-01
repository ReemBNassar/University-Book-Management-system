<?php
/**
 * requests_all.php  (أدمن فقط)
 * عرض كل طلبات الاستعارة في النظام مع بيانات الطالب والكتاب
 * GET param اختياري: status (لفلترة Pending مثلاً)
 */
require_once __DIR__ . '/helpers.php';
require_admin();

$status = trim($_GET['status'] ?? '');

$sql = 'SELECT r.request_id, r.status, r.request_date, r.return_deadline,
               b.book_id, b.title, b.author,
               u.user_id AS student_id, u.full_name AS student_name, u.email AS student_email
        FROM borrowing_request r
        JOIN book b ON b.book_id = r.book_id
        JOIN user_account u ON u.user_id = r.borrower_id';
$params = [];

if ($status !== '') {
    $sql .= ' WHERE r.status = :status';
    $params[':status'] = $status;
}
$sql .= ' ORDER BY r.request_date DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['success' => true, 'requests' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
