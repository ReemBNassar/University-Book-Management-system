<?php
/**
 * reports.php  (أدمن فقط)
 * تقارير وتحليلات (حسب قسم Reports & Analytics في الريبورت):
 *   1) الكتب الأكثر استعارة
 *   2) الكتب ذات أطول قوائم انتظار
 *   3) إحصائيات عامة سريعة
 */
require_once __DIR__ . '/helpers.php';
require_admin();

try {
    // 1) الأكثر استعارة (نعدّ الطلبات الموافق عليها أو المرتجعة)
    $mostBorrowed = $pdo->query(
        "SELECT b.book_id, b.title, b.author, b.department, COUNT(*) AS borrow_count
         FROM borrowing_request r
         JOIN book b ON b.book_id = r.book_id
         WHERE r.status IN ('Approved', 'Returned')
         GROUP BY b.book_id, b.title, b.author, b.department
         ORDER BY borrow_count DESC
         LIMIT 10"
    )->fetchAll();

    // 2) أطول قوائم انتظار
    $longWaitlists = $pdo->query(
        "SELECT b.book_id, b.title, b.author, COUNT(w.waitlist_id) AS waiting
         FROM waitlist w
         JOIN book b ON b.book_id = w.book_id
         GROUP BY b.book_id, b.title, b.author
         ORDER BY waiting DESC
         LIMIT 10"
    )->fetchAll();

    // 3) إحصائيات سريعة
    $stats = [
        'total_books'      => (int)$pdo->query('SELECT COUNT(*) FROM book')->fetchColumn(),
        'available_books'  => (int)$pdo->query("SELECT COUNT(*) FROM book WHERE status = 'Available'")->fetchColumn(),
        'borrowed_books'   => (int)$pdo->query("SELECT COUNT(*) FROM book WHERE status = 'Borrowed'")->fetchColumn(),
        'total_students'   => (int)$pdo->query('SELECT COUNT(*) FROM student')->fetchColumn(),
        'pending_requests' => (int)$pdo->query("SELECT COUNT(*) FROM borrowing_request WHERE status = 'Pending'")->fetchColumn(),
    ];

    json_response([
        'success' => true,
        'stats' => $stats,
        'most_borrowed' => $mostBorrowed,
        'long_waitlists' => $longWaitlists,
    ]);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
