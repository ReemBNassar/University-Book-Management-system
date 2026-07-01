<?php


require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];


if ($method === 'GET') {
    requireLogin();

    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.full_name, u.email, u.role,
                   s.department, s.current_borrow_count
            FROM user_account u
            LEFT JOIN student s ON s.user_id = u.user_id
            WHERE u.user_id = :id
        ");
        $stmt->execute([':id' => $user_id]);
        $profile = $stmt->fetch();

        sendJSON(['success' => true, 'profile' => $profile]);
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($method === 'PUT') {
    requireLogin();

    $input    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $user_id  = $_SESSION['user_id'];
    $fullname = trim($input['full_name'] ?? '');
    $password = $input['password'] ?? '';

    try {
        if ($fullname) {
            $pdo->prepare("UPDATE user_account SET full_name = :name WHERE user_id = :id")
                ->execute([':name' => $fullname, ':id' => $user_id]);
            $_SESSION['full_name'] = $fullname;
        }

        if ($password) {
            if (strlen($password) < 6) {
                sendJSON(['success' => false, 'message' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل']);
            }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE user_account SET password_hash = :hash WHERE user_id = :id")
                ->execute([':hash' => $hash, ':id' => $user_id]);
        }

        sendJSON(['success' => true, 'message' => 'تم تحديث الملف الشخصي بنجاح']);
    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>
