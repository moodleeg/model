<?php
require_once 'auth.php';
require_once 'MoodleScraper.php';
if (!empty($_SESSION['student_id'])) { header('Location: dashboard.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if (!$name || !$username || !$password || !$email) {
        $error = 'جميع الحقول مطلوبة';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'البريد الإلكتروني غير صحيح';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } else {
        // Verify Moodle credentials first
        try {
            $testCookie = COOKIES_DIR . 'verify_' . time() . '.txt';
            $testScraper = new MoodleScraper($testCookie);
            $moodleOk = $testScraper->login($username, $password);
            if (file_exists($testCookie)) @unlink($testCookie);
        } catch (Exception $e) {
            $moodleOk = false;
        }

        if (!$moodleOk) {
            $error = 'الرقم الجامعي أو كلمة المرور غير صحيحة — تحقق من بيانات Moodle';
        } else {
        try {
            getDB()->prepare("INSERT INTO students (name, email, moodle_username, moodle_password, password_hash) VALUES (?,?,?,?,?)")
                ->execute([$name, $email, $username, $password, password_hash($password, PASSWORD_DEFAULT)]);

            $studentId = getDB()->lastInsertId();
            // Pass credentials via session for sync
            $_SESSION['sync_user'] = $username;
            $_SESSION['sync_pass'] = $password;
            header('Location: do_sync.php?id=' . $studentId . '&registered=1');
            exit;
        } catch (Exception $e) {
            $error = 'الرقم الجامعي أو البريد الإلكتروني مسجل مسبقاً';
        }
        } // end moodleOk
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل طالب جديد - Moodle Tracker</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #0073aa, #005580); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.card { background: #fff; border-radius: 12px; padding: 40px; width: 100%; max-width: 440px; box-shadow: 0 10px 40px rgba(0,0,0,.2); }
.logo { text-align: center; margin-bottom: 25px; }
.logo h1 { font-size: 22px; color: #0073aa; margin-top: 10px; }
.logo p { color: #888; font-size: 13px; }
.form-group { margin-bottom: 18px; }
label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
input { width: 100%; padding: 11px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; transition: border .2s; }
input:focus { outline: none; border-color: #0073aa; box-shadow: 0 0 0 3px rgba(0,115,170,.1); }
.btn { width: 100%; padding: 12px; background: #0073aa; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background .2s; }
.btn:hover { background: #005580; }
.alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
.alert-error   { background: #f8d7da; color: #721c24; }
.alert-success { background: #d4edda; color: #155724; }
.links { text-align: center; margin-top: 20px; font-size: 13px; color: #888; }
.links a { color: #0073aa; text-decoration: none; font-weight: 600; }
.hint { font-size: 11px; color: #aaa; margin-top: 4px; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <span style="font-size:48px">🎓</span>
        <h1>Moodle Tracker</h1>
        <p>إنشاء حساب جديد</p>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
        <div class="form-group">
            <label>الاسم الكامل</label>
            <input type="text" name="name" placeholder="أحمد محمد علي" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>الرقم الجامعي</label>
            <input type="text" name="username" placeholder="1320XXXXXXX" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            <p class="hint">نفس اسم المستخدم في Moodle جامعة الأقصى</p>
        </div>
        <div class="form-group">
            <label>كلمة المرور</label>
            <input type="password" name="password" placeholder="كلمة مرور Moodle" required>
            <p class="hint">نفس كلمة المرور التي تستخدمها في Moodle</p>
        </div>
        <div class="form-group">
            <label>البريد الإلكتروني</label>
            <input type="email" name="email" placeholder="example@gmail.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <p class="hint">سيصلك الإشعارات على هذا البريد</p>
        </div>
        <button type="submit" class="btn" id="submitBtn" onclick="this.innerHTML='⏳ جاري التحقق من بيانات Moodle...';this.disabled=true;this.form.submit()">إنشاء الحساب</button>
    </form>
    <?php endif; ?>

    <div class="links">
        لديك حساب؟ <a href="login.php">تسجيل الدخول</a>
    </div>
</div>
</body>
</html>
