<?php


require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    requireLogin();

    $role    = $_SESSION['role'];
    $user_id = $_SESSION['user_id'];

    try {
        if ($role === 'Admin') {
            $stmt = $pdo->prepare("
                SELECT r.request_id, r.status, r.request_date, r.return_deadline, r.rejection_reason,
                       b.title AS book_title, b.author, b.department, b.book_id,
                       u.full_name AS student_name, u.email AS student_email, u.user_id AS student_id
                FROM borrowing_request r
                JOIN book b ON b.book_id = r.book_id
                JOIN user_account u ON u.user_id = r.borrower_id
                ORDER BY r.request_date DESC
            ");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("
                SELECT r.request_id, r.status, r.request_date, r.return_deadline, r.rejection_reason,
                       b.title AS book_title, b.author, b.department, b.book_id
                FROM borrowing_request r
                JOIN book b ON b.book_id = r.book_id
                WHERE r.borrower_id = :user_id
                ORDER BY r.request_date DESC
            ");
            $stmt->execute([':user_id' => $user_id]);
        }

        $requests = $stmt->fetchAll();
        sendJSON(['success' => true, 'requests' => $requests]);

    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($method === 'POST') {
    requireLogin();

    if ($_SESSION['role'] !== 'Student') {
        sendJSON(['success' => false, 'message' => 'الأدمن لا يمكنه إنشاء طلبات استعارة']);
    }

    $input   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $book_id = intval($input['book_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if (!$book_id) {
        sendJSON(['success' => false, 'message' => 'book_id مطلوب']);
    }

    try {
        $stmt = $pdo->prepare("SELECT current_borrow_count FROM student WHERE user_id = :id");
        $stmt->execute([':id' => $user_id]);
        $student = $stmt->fetch();

        if ($student && $student['current_borrow_count'] >= 3) {
            sendJSON(['success' => false, 'message' => 'وصلت للحد الأقصى (3 كتب مستعارة)']);
        }

        $stmt = $pdo->prepare("SELECT title, status FROM book WHERE book_id = :id");
        $stmt->execute([':id' => $book_id]);
        $book = $stmt->fetch();

        if (!$book) {
            sendJSON(['success' => false, 'message' => 'الكتاب غير موجود']);
        }

        if ($book['status'] !== 'Available') {
            sendJSON(['success' => false, 'message' => 'الكتاب غير متاح حالياً']);
        }

        $stmt = $pdo->prepare("
            SELECT request_id FROM borrowing_request
            WHERE borrower_id = :uid AND book_id = :bid AND status = 'Pending'
        ");
        $stmt->execute([':uid' => $user_id, ':bid' => $book_id]);
        if ($stmt->fetch()) {
            sendJSON(['success' => false, 'message' => 'لديك طلب معلق لهذا الكتاب مسبقاً']);
        }

        $return_deadline = date('Y-m-d', strtotime('+14 days'));
        $stmt = $pdo->prepare("
            INSERT INTO borrowing_request (book_id, borrower_id, return_deadline, status)
            VALUES (:book_id, :borrower_id, :deadline, 'Pending')
        ");
        $stmt->execute([
            ':book_id'    => $book_id,
            ':borrower_id'=> $user_id,
            ':deadline'   => $return_deadline,
        ]);
        $request_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO notification (user_id, message)
            VALUES (:uid, :msg)
        ");
        $stmt->execute([
            ':uid' => $user_id,
            ':msg' => 'تم إرسال طلب الاستعارة بنجاح، في انتظار موافقة الإدارة.',
        ]);

        $stmtAdmins = $pdo->query("SELECT user_id FROM user_account WHERE role = 'Admin'");
        $admins = $stmtAdmins->fetchAll();
        foreach ($admins as $adm) {
            $stmtNotif = $pdo->prepare("INSERT INTO notification (user_id, message) VALUES (:uid, :msg)");
            $stmtNotif->execute([
                ':uid' => $adm['user_id'],
                ':msg' => 'طلب استعارة جديد لكتاب: "' . $book['title'] . '" من الطالب: ' . $_SESSION['full_name']
            ]);
        }

        sendJSON([
            'success'    => true,
            'message'    => 'تم إرسال طلب الاستعارة بنجاح',
            'request_id' => $request_id,
        ]);

    } catch (PDOException $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

if ($method === 'PUT') {
    requireLogin();

    $input      = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $request_id = intval($input['request_id'] ?? 0);
    $new_status = trim($input['status'] ?? '');
    $reason     = isset($input['reason']) ? trim($input['reason']) : '';

    if (!$request_id || !$new_status) {
        sendJSON(['success' => false, 'message' => 'request_id والحالة الجديدة مطلوبان']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT r.*, b.title FROM borrowing_request r
            JOIN book b ON b.book_id = r.book_id
            WHERE r.request_id = :id
        ");
        $stmt->execute([':id' => $request_id]);
        $request = $stmt->fetch();

        if (!$request) {
            sendJSON(['success' => false, 'message' => 'الطلب غير موجود']);
        }

        $role    = $_SESSION['role'];
        $user_id = $_SESSION['user_id'];

        if ($role === 'Student') {
            if ($request['borrower_id'] != $user_id) {
                sendJSON(['success' => false, 'message' => 'هذا ليس طلبك']);
            }
            if ($new_status !== 'Rejected' || $request['status'] !== 'Pending') {
                sendJSON(['success' => false, 'message' => 'يمكنك فقط إلغاء الطلبات المعلقة']);
            }
        }

        $stmt = $pdo->prepare("
            UPDATE borrowing_request 
            SET status = :status, rejection_reason = :reason
            WHERE request_id = :id
        ");
        $stmt->execute([
            ':status' => $new_status, 
            ':reason' => ($new_status === 'Rejected' ? $reason : null), 
            ':id' => $request_id
        ]);

        $messages = [
            'Approved' => 'تمت الموافقة على طلب استعارة كتاب: ' . $request['title'],
            'Rejected' => 'تم رفض طلب استعارة كتاب: ' . $request['title'] . ($reason ? ' - السبب: ' . $reason : ''),
            'Returned' => 'تم تسجيل إعادة الكتاب: ' . $request['title'],
        ];
        if (isset($messages[$new_status])) {
            $stmt = $pdo->prepare("INSERT INTO notification (user_id, message) VALUES (:uid, :msg)");
            $stmt->execute([':uid' => $request['borrower_id'], ':msg' => $messages[$new_status]]);
        }

        if ($new_status === 'Returned') {
            $stmtWait = $pdo->prepare("SELECT user_id FROM waitlist WHERE book_id = :book_id");
            $stmtWait->execute([':book_id' => $request['book_id']]);
            $waitlisted = $stmtWait->fetchAll();
            foreach ($waitlisted as $wl) {
                $stmtNotif = $pdo->prepare("INSERT INTO notification (user_id, message) VALUES (:uid, :msg)");
                $stmtNotif->execute([
                    ':uid' => $wl['user_id'],
                    ':msg' => 'الكتاب الذي تنتظره أصبح متاحاً للاستعارة الآن: "' . $request['title'] . '"'
                ]);
            }
            $stmtDelWait = $pdo->prepare("DELETE FROM waitlist WHERE book_id = :book_id");
            $stmtDelWait->execute([':book_id' => $request['book_id']]);
        }

        sendJSON(['success' => true, 'message' => 'تم تحديث حالة الطلب بنجاح']);

    } catch (PDOException $e) {
        if ($e->getCode() == '45000' || strpos($e->getMessage(), '45000') !== false) {
            $msg = $e->getMessage();
            if (preg_match('/[0-9]{5}:?\s*(.*)/u', $msg, $matches)) {
                $msg = $matches[1];
            }
            sendJSON(['success' => false, 'message' => $msg]);
        }
        sendJSON(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
    }
}
?>
