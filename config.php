<?php
// ═══════════════════════════════════════════════
// قاعدة البيانات
// ═══════════════════════════════════════════════
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'moodle_tracker');

// ═══════════════════════════════════════════════
// إعدادات Gmail SMTP
// ═══════════════════════════════════════════════
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'qedrawfokhara@gmail.com');
define('SMTP_PASS', 'xrzf tkcc ytha rohm');
define('SMTP_FROM_NAME', 'Moodle Tracker - جامعة الأقصى');

// ═══════════════════════════════════════════════
// مجلد الـ Cookies
// ═══════════════════════════════════════════════
define('COOKIES_DIR', __DIR__ . '/cookies/');

// ═══════════════════════════════════════════════
// قاعدة البيانات - اتصال
// ═══════════════════════════════════════════════
function getDB() {
    static $pdo = null;
    try {
        if ($pdo !== null) $pdo->query('SELECT 1');
    } catch (Exception $e) {
        $pdo = null;
    }
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT            => 60,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone='+03:00'",
            ]
        );
    }
    return $pdo;
}

date_default_timezone_set('Asia/Gaza');
