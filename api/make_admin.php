<?php
/**
 * make_admin.php  —  صفحة لمرة واحدة فقط لإنشاء حساب المشرف
 * ------------------------------------------------------------
 * طريقة الاستخدام:
 *   1) شغّلي السيرفر وافتحي في المتصفح:
 *        http://localhost:8000/api/make_admin.php
 *   2) هتنشأ حساب أدمن بالبيانات اللي تحت.
 *   3) ‼️ بعد نجاحها، احذفي هذا الملف فورًا (أو غيّري السطر $ENABLED لـ false).
 *
 * غيّري البيانات دي قبل التشغيل:
 */
$ENABLED   = true;                    // غيّريها false بعد الاستخدام
$ADMIN_NAME  = 'Admin';
$ADMIN_EMAIL = 'admin@unibook.com';
$ADMIN_PASS  = 'admin123';            // ‼️ غيّري كلمة السر دي

// ------------------------------------------------------------
require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');

if (!$ENABLED) {
    exit('هذه الصفحة معطّلة. غيّري $ENABLED لـ true لتفعيلها مؤقتًا.');
}

try {
    // موجود قبل كده؟
    $stmt = $pdo->prepare('SELECT user_id FROM user_account WHERE email = :e');
    $stmt->execute([':e' => $ADMIN_EMAIL]);
    if ($stmt->fetch()) {
        exit('⚠️ يوجد حساب بهذا البريد بالفعل: ' . htmlspecialchars($ADMIN_EMAIL));
    }

    $hash = password_hash($ADMIN_PASS, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "INSERT INTO user_account (full_name, email, password_hash, role)
         VALUES (:n, :e, :h, 'Admin') RETURNING user_id"
    );
    $stmt->execute([':n' => $ADMIN_NAME, ':e' => $ADMIN_EMAIL, ':h' => $hash]);
    $uid = $stmt->fetchColumn();

    $stmt = $pdo->prepare('INSERT INTO admin_user (user_id) VALUES (:u)');
    $stmt->execute([':u' => $uid]);
    $pdo->commit();

    echo '<div style="font-family:sans-serif;padding:30px;direction:rtl">';
    echo '<h2 style="color:#2e7d32">✅ تم إنشاء حساب المشرف بنجاح</h2>';
    echo '<p>البريد: <b>' . htmlspecialchars($ADMIN_EMAIL) . '</b></p>';
    echo '<p>كلمة السر: <b>' . htmlspecialchars($ADMIN_PASS) . '</b></p>';
    echo '<p style="color:#c62828"><b>‼️ مهم جدًا: احذفي ملف make_admin.php الآن لأسباب أمنية.</b></p>';
    echo '</div>';
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo 'خطأ: ' . htmlspecialchars($e->getMessage());
}
