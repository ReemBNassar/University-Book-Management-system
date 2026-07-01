<?php


require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];


if ($method === 'POST') {
    requireLogin();

    if ($_SESSION['role'] !== 'Student') {
        sendJSON(['success' => false, 'message' => 'الأدمن لا يمكنه الانضمام لقائمة الانتظار']);
    }

    $input   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $book_id = intval($input['book_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if (!$book_id) {
        sendJSON(['success' => false, 'message' => 'book_id مطلوب']);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO waitlist (book_id, user_id)
            VALUES (:book_id, :user_id)
        ");
        $stmt->execute([':book_id' => $book_id, ':user_id' => $user_id]);
        
        sendJSON(['success' => true, 'message' => 'سيتم إشعارك فور توفر هذا الكتاب']);
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($method === 'DELETE') {
    requireLogin();

    $input   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $book_id = intval($input['book_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if (!$book_id) {
        sendJSON(['success' => false, 'message' => 'book_id مطلوب']);
    }

    try {
        $stmt = $pdo->prepare("
            DELETE FROM waitlist
            WHERE book_id = :book_id AND user_id = :user_id
        ");
        $stmt->execute([':book_id' => $book_id, ':user_id' => $user_id]);

        sendJSON(['success' => true, 'message' => 'تم إلغاء التنبيه لهذا الكتاب']);
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>
