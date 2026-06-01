<?php
/**
 * books.php
 * عرض الكتب مع البحث والفلترة
 * GET params:
 *   q          = كلمة بحث في العنوان أو المؤلف (اختياري)
 *   department = فلترة بالقسم (اختياري)
 *   status     = فلترة بالحالة Available/Borrowed/Damaged (اختياري)
 */
require_once __DIR__ . '/helpers.php';

$q          = trim($_GET['q'] ?? '');
$department = trim($_GET['department'] ?? '');
$status     = trim($_GET['status'] ?? '');

$sql    = 'SELECT book_id, title, author, department, status, created_at FROM book WHERE 1=1';
$params = [];

if ($q !== '') {
    // بحث في العنوان أو المؤلف (غير حساس لحالة الأحرف)
    $sql .= ' AND (title ILIKE :q OR author ILIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($department !== '') {
    $sql .= ' AND department = :dept';
    $params[':dept'] = $department;
}
if ($status !== '') {
    $sql .= ' AND status = :status';
    $params[':status'] = $status;
}

$sql .= ' ORDER BY created_at DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();

    json_response(['success' => true, 'count' => count($books), 'books' => $books]);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
