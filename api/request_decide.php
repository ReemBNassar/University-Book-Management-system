<?php
/**
 * request_decide.php  (أدمن فقط)
 * معالجة طلب الاستعارة: موافقة / رفض / تأكيد إرجاع
 * يستقبل: request_id, action  (approve | reject | return)
 *
 * ملاحظة مهمة عن قاعدة البيانات:
 *  عندكم trigger اسمه handle_borrowing_limit بيشتغل تلقائيًا عند تغيير الحالة:
 *    - Pending  -> Approved : يزود عدّاد الطالب + يحوّل الكتاب لـ Borrowed (ويرفض لو وصل 3).
 *    - Approved -> Returned : ينقّص العدّاد + يرجّع الكتاب Available.
 *  فإحنا هنا بنغيّر حالة الطلب فقط، والـ trigger بيكمّل الباقي.
 *  لكن إشعارات قائمة الانتظار وتحديد deadline مش جزء من الـ trigger، فبنعملها هنا.
 */
require_once __DIR__ . '/helpers.php';
require_admin();

$in = get_json_input();
$request_id = (int)($in['request_id'] ?? 0);
$action     = trim($in['action'] ?? '');
$admin_id   = $_SESSION['user_id'];

if ($request_id <= 0 || !in_array($action, ['approve', 'reject', 'return'], true)) {
    json_response(['success' => false, 'message' => 'بيانات غير صحيحة'], 422);
}

try {
    // نجيب الطلب الحالي
    $stmt = $pdo->prepare('SELECT * FROM borrowing_request WHERE request_id = :rid');
    $stmt->execute([':rid' => $request_id]);
    $req = $stmt->fetch();

    if (!$req) {
        json_response(['success' => false, 'message' => 'الطلب غير موجود'], 404);
    }

    // ===== موافقة =====
    if ($action === 'approve') {
        if ($req['status'] !== 'Pending') {
            json_response(['success' => false, 'message' => 'يمكن الموافقة على الطلبات المعلّقة فقط'], 409);
        }

        // مهلة إرجاع = 14 يوم من اليوم
        $deadline = date('Y-m-d', strtotime('+14 days'));

        // تغيير الحالة لـ Approved -> الـ trigger هيزود العدّاد ويحوّل الكتاب Borrowed
        // (لو الطالب وصل 3، الـ trigger هيرمي exception وهنمسكها تحت)
        $stmt = $pdo->prepare(
            "UPDATE borrowing_request
             SET status = 'Approved', admin_id = :aid, return_deadline = :dl
             WHERE request_id = :rid"
        );
        $stmt->execute([':aid' => $admin_id, ':dl' => $deadline, ':rid' => $request_id]);

        // سجل في التاريخ + إشعار للطالب
        log_history($pdo, $req['book_id'], $req['borrower_id'], 'Borrowed', 'تمت الموافقة على الاستعارة');
        notify($pdo, $req['borrower_id'], 'تمت الموافقة على طلب الاستعارة. موعد الإرجاع: ' . $deadline);

        json_response(['success' => true, 'message' => 'تمت الموافقة على الطلب', 'return_deadline' => $deadline]);
    }

    // ===== رفض =====
    if ($action === 'reject') {
        if ($req['status'] !== 'Pending') {
            json_response(['success' => false, 'message' => 'يمكن رفض الطلبات المعلّقة فقط'], 409);
        }
        $reason = trim($in['reason'] ?? '');

        $stmt = $pdo->prepare(
            "UPDATE borrowing_request SET status = 'Rejected', admin_id = :aid WHERE request_id = :rid"
        );
        $stmt->execute([':aid' => $admin_id, ':rid' => $request_id]);

        $msg = 'تم رفض طلب الاستعارة.' . ($reason !== '' ? ' السبب: ' . $reason : '');
        notify($pdo, $req['borrower_id'], $msg);

        json_response(['success' => true, 'message' => 'تم رفض الطلب']);
    }

    // ===== تأكيد إرجاع =====
    if ($action === 'return') {
        if ($req['status'] !== 'Approved') {
            json_response(['success' => false, 'message' => 'يمكن إرجاع الكتب المُعارة فقط'], 409);
        }

        // تغيير الحالة لـ Returned -> الـ trigger ينقّص العدّاد ويرجّع الكتاب Available
        $stmt = $pdo->prepare(
            "UPDATE borrowing_request SET status = 'Returned' WHERE request_id = :rid"
        );
        $stmt->execute([':rid' => $request_id]);

        log_history($pdo, $req['book_id'], $req['borrower_id'], 'Returned', 'تم إرجاع الكتاب');

        // إعلام أول شخص في قائمة الانتظار إن وُجد
        notify_waitlist($pdo, $req['book_id']);

        json_response(['success' => true, 'message' => 'تم تأكيد الإرجاع']);
    }

} catch (PDOException $e) {
    // الـ trigger في قاعدة البيانات بيرمي RAISE EXCEPTION وكوده P0001
    // ودي بتحصل لما الطالب يكون وصل للحد الأقصى (3 كتب)
    $sqlState = $e->getCode();
    $msg = $e->getMessage();
    if ($sqlState === 'P0001' || strpos($msg, 'الحد الأقصى') !== false) {
        json_response(['success' => false, 'message' => 'لا يمكن الموافقة: الطالب وصل للحد الأقصى (3 كتب). يجب أن يعيد كتابًا أولاً.'], 409);
    }
    json_response(['success' => false, 'message' => 'خطأ في الخادم: ' . $msg], 500);
}

// ===== دوال مساعدة =====
function log_history($pdo, $book_id, $student_id, $type, $note) {
    $stmt = $pdo->prepare(
        'INSERT INTO borrowing_history (book_id, student_id, action_type, note)
         VALUES (:bid, :sid, :type, :note)'
    );
    $stmt->execute([':bid' => $book_id, ':sid' => $student_id, ':type' => $type, ':note' => $note]);
}

function notify($pdo, $user_id, $message) {
    $stmt = $pdo->prepare('INSERT INTO notification (user_id, message) VALUES (:uid, :msg)');
    $stmt->execute([':uid' => $user_id, ':msg' => $message]);
}

function notify_waitlist($pdo, $book_id) {
    // أقدم شخص في قائمة الانتظار
    $stmt = $pdo->prepare(
        'SELECT user_id FROM waitlist WHERE book_id = :bid ORDER BY added_at ASC LIMIT 1'
    );
    $stmt->execute([':bid' => $book_id]);
    $next = $stmt->fetch();
    if ($next) {
        notify($pdo, $next['user_id'], 'الكتاب الذي تنتظره أصبح متاحًا الآن! سارع بطلب استعارته.');
        // نشيله من قائمة الانتظار بعد إعلامه
        $stmt = $pdo->prepare('DELETE FROM waitlist WHERE book_id = :bid AND user_id = :uid');
        $stmt->execute([':bid' => $book_id, ':uid' => $next['user_id']]);
    }
}
