<?php
/**
 * cron_tasks.php
 * مهمة مجدولة (تُشغَّل يوميًا عبر cron job)
 * ------------------------------------------------
 * 1) تذكير الطلاب قبل موعد الإرجاع بـ 24 ساعة.
 * 2) رصد الكتب المتأخرة (تجاوزت موعد الإرجاع) وإرسال تنبيه.
 *
 * طريقة التشغيل اليدوي للتجربة:
 *   php cron_tasks.php
 *
 * للجدولة اليومية على لينكس (مثال 8 صباحًا):
 *   0 8 * * * /usr/bin/php /path/to/api/cron_tasks.php
 */
require_once __DIR__ . '/db.php';

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

try {
    // ===== 1) تذكير قبل الإرجاع بيوم =====
    $stmt = $pdo->prepare(
        "SELECT r.request_id, r.borrower_id, b.title
         FROM borrowing_request r
         JOIN book b ON b.book_id = r.book_id
         WHERE r.status = 'Approved' AND r.return_deadline = :tomorrow"
    );
    $stmt->execute([':tomorrow' => $tomorrow]);
    $dueSoon = $stmt->fetchAll();

    foreach ($dueSoon as $row) {
        $ins = $pdo->prepare('INSERT INTO notification (user_id, message) VALUES (:uid, :msg)');
        $ins->execute([
            ':uid' => $row['borrower_id'],
            ':msg' => 'تذكير: موعد إرجاع كتاب "' . $row['title'] . '" غدًا. برجاء الإرجاع في الوقت المحدد.'
        ]);
    }

    // ===== 2) رصد المتأخرات =====
    $stmt = $pdo->prepare(
        "SELECT r.request_id, r.borrower_id, b.title
         FROM borrowing_request r
         JOIN book b ON b.book_id = r.book_id
         WHERE r.status = 'Approved' AND r.return_deadline < :today"
    );
    $stmt->execute([':today' => $today]);
    $overdue = $stmt->fetchAll();

    foreach ($overdue as $row) {
        $ins = $pdo->prepare('INSERT INTO notification (user_id, message) VALUES (:uid, :msg)');
        $ins->execute([
            ':uid' => $row['borrower_id'],
            ':msg' => 'تنبيه: كتاب "' . $row['title'] . '" متأخر عن موعد الإرجاع. برجاء إرجاعه في أقرب وقت.'
        ]);
    }

    echo "تم التذكير لـ " . count($dueSoon) . " طالب، ورصد " . count($overdue) . " كتاب متأخر.\n";

} catch (PDOException $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
