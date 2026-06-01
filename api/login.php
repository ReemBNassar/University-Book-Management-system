<?php
/**
 * login.php
 * تسجيل الدخول
 * يستقبل: email, password
 * يرجّع: بيانات المستخدم + الدور (Student / Admin) عشان الفرونت يوجّه للصفحة الصح
 */
require_once __DIR__ . '/helpers.php';

$in = get_json_input();

$email    = trim(strtolower($in['email'] ?? ''));
$password = $in['password'] ?? '';

if ($email === '' || $password === '') {
    json_response(['success' => false, 'message' => 'البريد وكلمة المرور مطلوبان'], 422);
}

try {
    $stmt = $pdo->prepare(
        'SELECT user_id, full_name, email, password_hash, role
         FROM user_account WHERE email = :email'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // نفس رسالة الخطأ سواء الإيميل غلط أو الباسوورد غلط (أأمن)
    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_response(['success' => false, 'message' => 'البريد أو كلمة المرور غير صحيحة'], 401);
    }

    // تخزين الجلسة
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];

    json_response([
        'success'  => true,
        'message'  => 'تم تسجيل الدخول',
        'user'     => [
            'user_id'   => $user['user_id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
        ],
        // الفرونت يستخدم ده عشان يوجّه: Admin -> admin_dashboard.html , Student -> student.html
        'redirect' => $user['role'] === 'Admin' ? 'admin_dashboard.html' : 'student.html'
    ]);

} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
