<?php
/**
 * helpers.php
 * دوال مشتركة: السماح بالطلبات + التحقق من تسجيل الدخول والصلاحيات
 */

// السماح للفرونت بالاتصال (مهم لو الفرونت والباك على نفس السيرفر برضه يفضل وجودها)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// طلب الـ preflight بتاع المتصفح
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

/** التأكد إن المستخدم سجّل دخول */
function require_login() {
    if (empty($_SESSION['user_id'])) {
        json_response(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً'], 401);
    }
}

/** التأكد إن المستخدم أدمن */
function require_admin() {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'Admin') {
        json_response(['success' => false, 'message' => 'هذه الصفحة مخصصة للمشرفين فقط'], 403);
    }
}
