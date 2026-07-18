<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function authStudent() {
    if (empty($_SESSION['student_id'])) {
        header('Location: login.php');
        exit;
    }
    $student = getDB()->query("SELECT * FROM students WHERE id=" . (int)$_SESSION['student_id'])->fetch(PDO::FETCH_ASSOC);
    if (!$student || !$student['active']) {
        session_destroy();
        header('Location: login.php?msg=account_inactive');
        exit;
    }
    return $student;
}

function authAdmin() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: admin_login.php');
        exit;
    }
}

function loginStudent($username, $password) {
    $stmt = getDB()->prepare("SELECT * FROM students WHERE moodle_username=? AND active=1");
    $stmt->execute([$username]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student && password_verify($password, $student['password_hash'])) {
        $_SESSION['student_id'] = $student['id'];
        return true;
    }
    return false;
}

function loginAdmin($username, $password) {
    $stmt = getDB()->prepare("SELECT * FROM admins WHERE username=?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        return true;
    }
    return false;
}
