<?php
/**
 * my_history.php  (طالب)
 * عرض سجل الكتب التي استعارها الطالب سابقًا
 */
require_once __DIR__ . '/helpers.php';
require_login();

$student_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT h.history_id, h.action_type, h.action_date, h.note,
                b.title, b.author
         FROM borrowing_history h
         LEFT JOIN book b ON b.book_id = h.book_id
         WHERE h.student_id = :uid
         ORDER BY h.action_date DESC'
    );
    $stmt->execute([':uid' => $student_id]);
    json_response(['success' => true, 'history' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
