-- ============================================================
-- Moodle Tracker - قاعدة البيانات الكاملة
-- نفّذ هذا في phpMyAdmin بعد اختيار قاعدة البيانات
-- ============================================================

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    moodle_username VARCHAR(100) NOT NULL UNIQUE,
    moodle_password VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL DEFAULT '',
    active TINYINT(1) DEFAULT 1,
    last_check TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id VARCHAR(20) NOT NULL,
    item_type ENUM('lecture','assignment','quiz','announcement') NOT NULL,
    item_id VARCHAR(150) NOT NULL,
    item_title VARCHAR(500) NOT NULL,
    item_url VARCHAR(500) NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_notification (student_id, item_type, item_id)
);

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- إذا كانت الجداول موجودة مسبقاً، نفّذ هذا لإضافة الأعمدة الجديدة:
-- ============================================================

-- ALTER TABLE students ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NOT NULL DEFAULT '';
-- ALTER TABLE notifications_log ADD COLUMN IF NOT EXISTS item_url VARCHAR(500) NULL AFTER item_title;
