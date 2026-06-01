<?php
/**
 * logout.php
 * تسجيل الخروج وإنهاء الجلسة
 */
require_once __DIR__ . '/helpers.php';

$_SESSION = [];
session_destroy();

json_response(['success' => true, 'message' => 'تم تسجيل الخروج']);
