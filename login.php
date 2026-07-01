<?php

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input    = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$email    = trim(strtolower($input['email'] ?? ''));
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    sendJSON(['success' => false, 'message' => 'البريد الإلكتروني وكلمة المرور مطلوبان']);
}

try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.email, u.password_hash, u.role
        FROM user_account u
        WHERE u.email = :email
    ");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendJSON(['success' => false, 'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة']);
    }

    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];

    $extra = [];
    if ($user['role'] === 'Student') {
        $stmt = $pdo->prepare("SELECT department, current_borrow_count FROM student WHERE user_id = :id");
        $stmt->execute([':id' => $user['user_id']]);
        $extra = $stmt->fetch() ?: [];
    }

    sendJSON([
        'success' => true,
        'message' => 'تم تسجيل الدخول بنجاح',
        'user'    => [
            'user_id'             => $user['user_id'],
            'full_name'           => $user['full_name'],
            'email'               => $user['email'],
            'role'                => $user['role'],
            'department'          => $extra['department'] ?? null,
            'current_borrow_count'=> $extra['current_borrow_count'] ?? 0,
        ]
    ]);

} catch (PDOException $e) {
    sendJSON(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
}
?>
