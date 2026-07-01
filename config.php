<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'unibook_db';
$charset = 'utf8mb4';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function sendJSON($data, $status = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireLogin($requiredRole = null) {
    if (!isset($_SESSION['user_id'])) {
        sendJSON(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً'], 401);
    }

    $expectedRole = $_GET['expected_role'] ?? $_POST['expected_role'] ?? null;
    if ($expectedRole === null) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['expected_role'])) {
            $expectedRole = $input['expected_role'];
        }
    }

    if ($expectedRole !== null) {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $expectedRole) {
            sendJSON(['success' => false, 'message' => 'Session conflict detected. Please login again.'], 401);
        }
    }

    $expectedUserId = $_GET['expected_user_id'] ?? $_POST['expected_user_id'] ?? null;
    if ($expectedUserId === null) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['expected_user_id'])) {
            $expectedUserId = $input['expected_user_id'];
        }
    }

    if ($expectedUserId !== null) {
        if (intval($_SESSION['user_id']) !== intval($expectedUserId)) {
            sendJSON(['success' => false, 'message' => 'User session mismatch. Please login again.'], 401);
        }
    }

    if ($requiredRole !== null) {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
            sendJSON(['success' => false, 'message' => 'غير مصرح لك بالوصول'], 403);
        }
    }
}

try {
    $dsn = "mysql:host=$host;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);

    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db'");
    if (!$stmt->fetch()) {
        $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo->exec("USE `$db`");
        
        $sqlPath = __DIR__ . '/test.sql';
        if (file_exists($sqlPath)) {
            $sql = file_get_contents($sqlPath);
            executeSQLScript($pdo, $sql);
        }
    } else {
        $pdo->exec("USE `$db`");
    }

    $stmt = $pdo->prepare("SELECT user_id FROM user_account WHERE email = :email");
    $stmt->execute([':email' => 'engclub@gmail.com']);
    if (!$stmt->fetch()) {
        $pdo->beginTransaction();
        
        $admin_hash = password_hash('qwer!1234', PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("
            INSERT INTO user_account (full_name, email, password_hash, role)
            VALUES (:name, :email, :hash, 'Admin')
        ");
        $stmt->execute([
            ':name'  => 'Engineering Club Admin',
            ':email' => 'engclub@gmail.com',
            ':hash'  => $admin_hash
        ]);
        
        $admin_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("
            INSERT INTO admin_user (user_id)
            VALUES (:id)
        ");
        $stmt->execute([':id' => $admin_id]);
        
        $pdo->commit();
    }

} catch (PDOException $e) {
    sendJSON([
        'success' => false,
        'message' => 'فشل الاتصال بقاعدة البيانات أو التهيئة: ' . $e->getMessage()
    ], 500);
}

function executeSQLScript($pdo, $sql) {
    $sql = preg_replace('/--.*$/m', '', $sql);
    
    $parts = preg_split('/\bDELIMITER\s+([^\\s]+)/i', $sql);
    
    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;
        
        if (stripos($part, 'CREATE TRIGGER') !== false) {
            $part = rtrim($part, '$');
            $part = trim($part);
            if (!empty($part)) {
                $pdo->exec($part);
            }
        } else {
            $queries = explode(';', $part);
            foreach ($queries as $query) {
                $query = trim($query);
                if (empty($query)) continue;
                $pdo->exec($query);
            }
        }
    }
}
