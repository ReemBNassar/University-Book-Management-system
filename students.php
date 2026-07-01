<?php


require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

if ($_SESSION['role'] !== 'Admin') {
    sendJSON(['success' => false, 'message' => 'غير مصرح لك بالوصول لدليل الطلاب'], 403);
}

try {
    $stmt = $pdo->query("
        SELECT u.user_id, u.full_name, u.email, s.department, s.current_borrow_count
        FROM user_account u
        JOIN student s ON s.user_id = u.user_id
        WHERE u.role = 'Student'
        ORDER BY u.full_name ASC
    ");
    $students = $stmt->fetchAll();

    sendJSON(['success' => true, 'students' => $students]);
} catch (PDOException $e) {
    sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
}
?>
