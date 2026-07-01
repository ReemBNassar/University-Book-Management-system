<?php


require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search     = trim($_GET['search'] ?? '');
    $department = trim($_GET['department'] ?? '');

    try {
        $query = "SELECT * FROM book WHERE 1=1";
        $params = [];
        $role = $_SESSION['role'] ?? 'Student';
        if ($role === 'Student') {
            $query .= " AND status != 'Pending'";
        }

        if ($search !== '') {
            $query .= " AND (title LIKE :search OR author LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($department !== '') {
            $query .= " AND department = :department";
            $params[':department'] = $department;
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $books = $stmt->fetchAll();

        sendJSON(['success' => true, 'books' => $books]);
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
if ($method === 'POST') {
    requireLogin();

    $input      = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $title      = trim($input['title'] ?? '');
    $author     = trim($input['author'] ?? '');
    $department = trim($input['department'] ?? 'Computer Engineering');

    if (empty($title) || empty($author)) {
        sendJSON(['success' => false, 'message' => 'عنوان الكتاب والمؤلف مطلوبان']);
    }

    try {
        $role = $_SESSION['role'] ?? 'Student';
        $status = ($role === 'Admin') ? 'Available' : 'Pending';

        $stmt = $pdo->prepare("
            INSERT INTO book (title, author, department, status)
            VALUES (:title, :author, :department, :status)
        ");
        $stmt->execute([
            ':title'      => $title,
            ':author'     => $author,
            ':department' => $department,
            ':status'     => $status
        ]);

        $book_id = $pdo->lastInsertId();

        if ($role === 'Student') {
            $stmtAdmins = $pdo->query("SELECT user_id FROM user_account WHERE role = 'Admin'");
            $admins = $stmtAdmins->fetchAll();
            foreach ($admins as $adm) {
                $stmtNotif = $pdo->prepare("INSERT INTO notification (user_id, message) VALUES (:uid, :msg)");
                $stmtNotif->execute([
                    ':uid' => $adm['user_id'],
                    ':msg' => 'طلب إضافة كتاب جديد للمكتبة بانتظار الموافقة: "' . $title . '" للمؤلف: ' . $author . ' من الطالب: ' . $_SESSION['full_name']
                ]);
            }
        }

        sendJSON(['success' => true, 'message' => ($role === 'Admin' ? 'تم إضافة الكتاب بنجاح' : 'تم إرسال طلب إضافة الكتاب بنجاح وبانتظار موافقة الإدارة'), 'book_id' => $book_id]);
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($method === 'PUT') {
    requireLogin();

    if ($_SESSION['role'] !== 'Admin') {
        sendJSON(['success' => false, 'message' => 'غير مصرح لك بتعديل الكتب'], 403);
    }

    $input   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $book_id = intval($input['book_id'] ?? 0);
    $status  = trim($input['status'] ?? 'Available');

    if (!$book_id) {
        sendJSON(['success' => false, 'message' => 'book_id مطلوب']);
    }

    try {
        $stmt = $pdo->prepare("UPDATE book SET status = :status WHERE book_id = :id");
        $stmt->execute([':status' => $status, ':id' => $book_id]);

        sendJSON(['success' => true, 'message' => 'تم تحديث حالة الكتاب ونشره بنجاح']);
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($method === 'DELETE') {
    requireLogin();

    if ($_SESSION['role'] !== 'Admin') {
        sendJSON(['success' => false, 'message' => 'غير مصرح لك بحذف الكتب'], 403);
    }

    $input   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $book_id = intval($input['book_id'] ?? 0);

    if (!$book_id) {
        sendJSON(['success' => false, 'message' => 'book_id مطلوب']);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM book WHERE book_id = :id");
        $stmt->execute([':id' => $book_id]);

        sendJSON(['success' => true, 'message' => 'تم حذف الكتاب بنجاح']);
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>
