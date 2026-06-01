<?php
/**
 * signup.php
 * تسجيل حساب طالب جديد
 * يستقبل: full_name, student_id, email, password, department (اختياري)
 */
require_once __DIR__ . '/helpers.php';

$in = get_json_input();

$full_name  = trim($in['full_name'] ?? '');
$email      = trim(strtolower($in['email'] ?? ''));
$password   = $in['password'] ?? '';
$department = trim($in['department'] ?? 'General'); // لو الفرونت مش بيبعت قسم، نحط افتراضي

// ===== تحقق من المدخلات =====
if ($full_name === '' || $email === '' || $password === '') {
    json_response(['success' => false, 'message' => 'كل الحقول مطلوبة'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'البريد الإلكتروني غير صحيح'], 422);
}
if (strlen($password) < 6) {
    json_response(['success' => false, 'message' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل'], 422);
}

try {
    // هل الإيميل مستخدم قبل كده؟
    $stmt = $pdo->prepare('SELECT user_id FROM user_account WHERE email = :email');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        json_response(['success' => false, 'message' => 'هذا البريد مستخدم بالفعل'], 409);
    }

    // تشفير كلمة المرور
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // نستخدم transaction لأننا بنكتب في جدولين
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO user_account (full_name, email, password_hash, role)
         VALUES (:name, :email, :hash, :role) RETURNING user_id'
    );
    $stmt->execute([
        ':name'  => $full_name,
        ':email' => $email,
        ':hash'  => $hash,
        ':role'  => 'Student',
    ]);
    $user_id = $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO student (user_id, department, current_borrow_count)
         VALUES (:uid, :dept, 0)'
    );
    $stmt->execute([':uid' => $user_id, ':dept' => $department]);

    $pdo->commit();

    json_response([
        'success' => true,
        'message' => 'تم إنشاء الحساب بنجاح',
        'user'    => ['user_id' => $user_id, 'full_name' => $full_name, 'role' => 'Student']
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
