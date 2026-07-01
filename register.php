<?php

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$full_name  = trim($input['full_name'] ?? '');
$email      = trim(strtolower($input['email'] ?? ''));
$password   = $input['password'] ?? '';
$department = trim($input['department'] ?? '');
$user_id    = intval($input['user_id'] ?? 0); // Student ID

if (empty($full_name) || empty($email) || empty($password) || empty($user_id) || empty($department)) {
    sendJSON(['success' => false, 'message' => 'جميع الحقول مطلوبة، بما في ذلك الرقم الجامعي والقسم الهندسي']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJSON(['success' => false, 'message' => 'البريد الإلكتروني غير صحيح']);
}

if (strlen($password) < 6) {
    sendJSON(['success' => false, 'message' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل']);
}

try {
    $stmt = $pdo->prepare("SELECT user_id FROM user_account WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        sendJSON(['success' => false, 'message' => 'البريد الإلكتروني مستخدم مسبقاً']);
    }

    $stmt = $pdo->prepare("SELECT user_id FROM user_account WHERE user_id = :id");
    $stmt->execute([':id' => $user_id]);
    if ($stmt->fetch()) {
        sendJSON(['success' => false, 'message' => 'الرقم الجامعي مستخدم مسبقاً']);
    }

    $pdo->beginTransaction();

    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("
        INSERT INTO user_account (user_id, full_name, email, password_hash, role)
        VALUES (:user_id, :full_name, :email, :password_hash, 'Student')
    ");
    $stmt->execute([
        ':user_id'       => $user_id,
        ':full_name'     => $full_name,
        ':email'         => $email,
        ':password_hash' => $password_hash,
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO student (user_id, department)
        VALUES (:user_id, :department)
    ");
    $stmt->execute([
        ':user_id'    => $user_id,
        ':department' => $department,
    ]);

    $pdo->commit();

    sendJSON([
        'success' => true,
        'message' => 'تم إنشاء الحساب بنجاح',
        'user'    => [
            'user_id'   => $user_id,
            'full_name' => $full_name,
            'email'     => $email,
            'role'      => 'Student',
        ]
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendJSON(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
}
?>