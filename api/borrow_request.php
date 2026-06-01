<?php
/**
 * borrow_request.php  (طالب)
 * تقديم طلب استعارة لكتاب
 * يستقبل: book_id
 *
 * القواعد (حسب الريبورت + ملف الـ SQL):
 *  - الطلب يُقدَّم على كتاب حالته "Available" فقط.
 *  - لو الكتاب "Borrowed" يتم إضافة الطالب لقائمة الانتظار (waitlist) بدلاً من الرفض.
 *  - لا يُسمح للطالب بتجاوز 3 كتب (الـ trigger في قاعدة البيانات يحرس على ده عند الموافقة،
 *    لكننا نتحقق هنا مبكرًا لرسالة ألطف).
 */
require_once __DIR__ . '/helpers.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'Student') {
    json_response(['success' => false, 'message' => 'الاستعارة متاحة للطلاب فقط'], 403);
}

$in = get_json_input();
$book_id = (int)($in['book_id'] ?? 0);
$student_id = $_SESSION['user_id'];

if ($book_id <= 0) {
    json_response(['success' => false, 'message' => 'معرّف الكتاب مطلوب'], 422);
}

try {
    // حالة الكتاب
    $stmt = $pdo->prepare('SELECT status FROM book WHERE book_id = :id');
    $stmt->execute([':id' => $book_id]);
    $book = $stmt->fetch();

    if (!$book) {
        json_response(['success' => false, 'message' => 'الكتاب غير موجود'], 404);
    }

    // التحقق من عدد الكتب الحالية للطالب
    $stmt = $pdo->prepare('SELECT current_borrow_count FROM student WHERE user_id = :uid');
    $stmt->execute([':uid' => $student_id]);
    $count = (int)$stmt->fetchColumn();
    if ($count >= 3) {
        json_response(['success' => false, 'message' => 'وصلت للحد الأقصى (3 كتب). أعد كتابًا أولاً.'], 409);
    }

    // ===== الكتاب غير متاح -> قائمة انتظار =====
    if ($book['status'] !== 'Available') {
        // هل هو مسجّل بالفعل في الانتظار؟
        $stmt = $pdo->prepare('SELECT 1 FROM waitlist WHERE book_id = :bid AND user_id = :uid');
        $stmt->execute([':bid' => $book_id, ':uid' => $student_id]);
        if ($stmt->fetch()) {
            json_response(['success' => false, 'message' => 'أنت بالفعل في قائمة انتظار هذا الكتاب'], 409);
        }

        $stmt = $pdo->prepare('INSERT INTO waitlist (book_id, user_id) VALUES (:bid, :uid)');
        $stmt->execute([':bid' => $book_id, ':uid' => $student_id]);

        // إشعار للطالب
        $stmt = $pdo->prepare('INSERT INTO notification (user_id, message) VALUES (:uid, :msg)');
        $stmt->execute([
            ':uid' => $student_id,
            ':msg' => 'تمت إضافتك لقائمة انتظار الكتاب. سيتم إعلامك عند توفره.'
        ]);

        json_response(['success' => true, 'waitlisted' => true,
            'message' => 'الكتاب مُعار حاليًا. تمت إضافتك لقائمة الانتظار وسيتم إعلامك عند توفره.']);
    }

    // ===== الكتاب متاح -> إنشاء طلب استعارة (Pending) =====
    // منع تكرار طلب معلّق لنفس الكتاب من نفس الطالب
    $stmt = $pdo->prepare(
        "SELECT 1 FROM borrowing_request
         WHERE book_id = :bid AND borrower_id = :uid AND status = 'Pending'"
    );
    $stmt->execute([':bid' => $book_id, ':uid' => $student_id]);
    if ($stmt->fetch()) {
        json_response(['success' => false, 'message' => 'لديك طلب معلّق على هذا الكتاب بالفعل'], 409);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO borrowing_request (book_id, borrower_id, status)
         VALUES (:bid, :uid, 'Pending') RETURNING request_id"
    );
    $stmt->execute([':bid' => $book_id, ':uid' => $student_id]);
    $request_id = $stmt->fetchColumn();

    json_response(['success' => true, 'waitlisted' => false, 'request_id' => $request_id,
        'message' => 'تم إرسال طلب الاستعارة بنجاح. بانتظار موافقة المشرف.']);

} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()], 500);
}
