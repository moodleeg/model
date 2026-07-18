<?php
require_once 'auth.php';
require_once 'pseudo_cron.php';
if (!empty($_SESSION['student_id'])) { header('Location: dashboard.php'); exit; }
runPseudoCron();

$error = '';
$registered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (loginStudent(trim($_POST['username'] ?? ''), trim($_POST['password'] ?? ''))) {
        header('Location: dashboard.php');
        exit;
    }
    $error = 'الرقم الجامعي أو كلمة المرور غير صحيحة';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل الدخول - Moodle Tracker</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #0073aa, #005580); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.card { background: #fff; border-radius: 12px; padding: 40px; width: 100%; max-width: 400px; box-shadow: 0 10px 40px rgba(0,0,0,.2); }
.logo { text-align: center; margin-bottom: 30px; }
.logo h1 { font-size: 22px; color: #0073aa; margin-top: 10px; }
.logo p { color: #888; font-size: 13px; }
.form-group { margin-bottom: 18px; }
label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
input { width: 100%; padding: 11px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; transition: border .2s; }
input:focus { outline: none; border-color: #0073aa; box-shadow: 0 0 0 3px rgba(0,115,170,.1); }
.btn { width: 100%; padding: 12px; background: #0073aa; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; }
.btn:hover { background: #005580; }
.alert-error { background: #f8d7da; color: #721c24; padding: 12px 15px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
.links { text-align: center; margin-top: 20px; font-size: 13px; color: #888; }
.links a { color: #0073aa; text-decoration: none; font-weight: 600; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <span style="font-size:48px">🎓</span>
        <h1>Moodle Tracker</h1>
        <p>بوابة التعليم الإلكتروني - جامعة الأقصى</p>
    </div>

    <?php if ($registered): ?><div class="alert-success" style="background:#d4edda;color:#155724;padding:12px 15px;border-radius:8px;margin-bottom:18px;font-size:14px">✓ تم إنشاء حسابك بنجاح! سجّل دخولك الآن.</div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><?= $error ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>الرقم الجامعي</label>
            <input type="text" name="username" placeholder="1320XXXXXXX" required autofocus>
        </div>
        <div class="form-group">
            <label>كلمة المرور</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn">تسجيل الدخول</button>
    </form>

    <div class="links">
        ليس لديك حساب؟ <a href="register.php">إنشاء حساب جديد</a>
    </div>
</div>
</body>
</html>
