<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

$conn = new mysqli("localhost", "root", "", "unibook_db"); 

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات");
}

$studentId = $_POST['student_id'];
$email = $_POST['email'];

$stmt = $conn->prepare("
SELECT user_id
FROM user_account
WHERE user_id = ? AND email = ?
");

$stmt->bind_param("is", $studentId, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("البريد الإلكتروني غير موجود.");
}

$user = $result->fetch_assoc();

$token = bin2hex(random_bytes(32));

$expire = date("Y-m-d H:i:s", strtotime("+1 hour"));

$stmt = $conn->prepare("UPDATE user_account SET reset_token=?, reset_token_expire=? WHERE user_id=?");
$stmt->bind_param("ssi", $token, $expire, $user['user_id']);
$stmt->execute();

$resetLink = "http://localhost/ReemNassar/reset-password.php?token=".$token;

$mail = new PHPMailer(true);
$mail->SMTPDebug = 2;
$mail->Debugoutput = 'html';
try {

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

  
    $mail->Username = 'r0599774396@gmail.com';

    $mail->Password = 'ixmueopahpfgnfgi';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->CharSet = 'UTF-8';

    $mail->setFrom('r0599774396@gmail.com', 'UniBook');

    $mail->addAddress($email);

    $mail->isHTML(true);

    $mail->Subject = 'Reset Password';

    $mail->Body = "
    <h2>إعادة تعيين كلمة المرور</h2>

    <p>اضغط على الرابط التالي لإعادة تعيين كلمة المرور:</p>

    <a href='$resetLink'>$resetLink</a>

    <br><br>

    <p>صلاحية الرابط ساعة واحدة.</p>
    ";

    $mail->send();

    echo "تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.";

} catch (Exception $e) {

    echo "فشل الإرسال: " . $mail->ErrorInfo;

}

$conn->close();

?>