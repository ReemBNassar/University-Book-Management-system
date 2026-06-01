<?php
/**
 * book_add.php  (أدمن فقط)
 * إضافة كتاب جديد
 * يستقبل: title, author, department
 */
require_once __DIR__ . '/helpers.php';
require_admin();

$in = get_json_input();

$title      = trim($in['title'] ?? '');
$author     = trim($in['author'] ?? '');
$department = trim($in['department'] ?? '');

if ($title === '' || $author === '') {
    json_response(['success' => false, 'message' => 'العنوان والمؤلف مطلوبان'], 422);
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO book (title, author, department, status)
         VALUES (:title, :author, :dept, 'Available') RETURNING book_id"
    );
    $stmt->execute([
        ':title'  => $title,
        ':author' => $author,
        ':dept'   => $department ?: null,
    ]);
    $book_id = $stmt->fetchColumn();

    json_response(['success' => true, 'message' => 'تمت إضافة الكتاب', 'book_id' => $book_id]);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
