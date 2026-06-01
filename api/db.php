<?php
/**
 * db.php
 * الاتصال بقاعدة بيانات PostgreSQL باستخدام PDO
 * ------------------------------------------------
 * غيّري القيم اللي تحت حسب إعدادات السيرفر عندك.
 */

// ===== إعدادات قاعدة البيانات =====
// القيم الحقيقية في ملف config.php (غير مرفوع على GitHub لأسباب أمنية).
// لو الملف مش موجود، انسخي config.sample.php وسمّيها config.php وعدّلي القيم.
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'ملف الإعدادات config.php غير موجود. انسخي config.sample.php وسمّيها config.php وعدّلي بياناتها.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$config = require $configPath;

$DB_HOST = $config['DB_HOST'];
$DB_PORT = $config['DB_PORT'];
$DB_NAME = $config['DB_NAME'];
$DB_USER = $config['DB_USER'];
$DB_PASS = $config['DB_PASS'];

// ===== الاتصال =====
try {
    $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'فشل الاتصال بقاعدة البيانات: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * دالة مساعدة ترجع رد JSON وتقفل التنفيذ
 */
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * دالة مساعدة تقرأ بيانات JSON الجاية من الفرونت
 */
function get_json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// بدء الجلسة لكل الطلبات
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
