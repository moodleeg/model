<?php
require_once 'auth.php';
if (!empty($_SESSION['admin_id'])) { header('Location: admin.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (loginAdmin(trim($_POST['username'] ?? ''), trim($_POST['password'] ?? ''))) {
        header('Location: admin.php');
        exit;
    }
    $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>دخول المشرف - Moodle Tracker</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #2c3e50, #34495e); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.card { background: #fff; border-radius: 12px; padding: 40px; width: 100%; max-width: 380px; box-shadow: 0 10px 40px rgba(0,0,0,.3); }
.logo { text-align: center; margin-bottom: 25px; }
.logo h1 { font-size: 20px; color: #2c3e50; margin-top: 8px; }
.logo p { color: #888; font-size: 13px; }
.form-group { margin-bottom: 16px; }
label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; }
input { width: 100%; padding: 11px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
input:focus { outline: none; border-color: #2c3e50; }
.btn { width: 100%; padding: 12px; background: #2c3e50; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn:hover { background: #1a252f; }
.alert-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
.back { text-align: center; margin-top: 15px; font-size: 13px; }
.back a { color: #0073aa; text-decoration: none; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <span style="font-size:40px">🔐</span>
        <h1>لوحة المشرف</h1>
        <p>Moodle Tracker Admin</p>
    </div>
    <?php if ($error): ?><div class="alert-error"><?= $error ?></div><?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>اسم المستخدم</label>
            <input type="text" name="username" placeholder="admin" required autofocus>
        </div>
        <div class="form-group">
            <label>كلمة المرور</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn">دخول</button>
    </form>
    <div class="back"><a href="login.php">← دخول الطلاب</a></div>
</div>
</body>
</html>
