<?php
/**
 * me.php
 * يرجّع بيانات المستخدم الحالي (لو مسجّل دخول)
 * الفرونت يستخدمه أول ما الصفحة تفتح عشان يتأكد إن فيه جلسة شغّالة
 */
require_once __DIR__ . '/helpers.php';

if (empty($_SESSION['user_id'])) {
    json_response(['success' => false, 'message' => 'لا توجد جلسة']);
}

json_response([
    'success' => true,
    'user' => [
        'user_id'   => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role'] ?? '',
    ]
]);
