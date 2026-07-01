<?php
// ==============================
// history.php - سجل الاستعارات
// ==============================

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

$user_id = $_SESSION['user_id'];

try {
    // جلب كل طلبات الاستعارة السابقة والمكتملة للطالب
    $stmt = $pdo->prepare("
        SELECT r.request_id, r.status, r.request_date, r.return_deadline,
               b.title AS book_title, b.author, b.department
        FROM borrowing_request r
        JOIN book b ON b.book_id = r.book_id
        WHERE r.borrower_id = :uid
        ORDER BY r.request_date DESC
    ");
    $stmt->execute([':uid' => $user_id]);
    $history = $stmt->fetchAll();

    sendJSON(['success' => true, 'history' => $history]);
} catch (PDOException $e) {
    sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
}
?>
