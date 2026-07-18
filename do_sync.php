<?php
require_once 'config.php';
require_once 'MoodleScraper.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$studentId = (int)($_GET['id'] ?? 0);
if (!$studentId) { header('Location: login.php'); exit; }

$student = getDB()->query("SELECT * FROM students WHERE id=$studentId")->fetch(PDO::FETCH_ASSOC);
if (!$student) { header('Location: login.php'); exit; }

// Get credentials from session or DB
$username = $_SESSION['sync_user'] ?? $student['moodle_username'];
$password = $_SESSION['sync_pass'] ?? $student['moodle_password'];
unset($_SESSION['sync_user'], $_SESSION['sync_pass']);

// Run sync
$synced = 0;
$error  = '';
try {
    set_time_limit(300);
    $cookieFile = COOKIES_DIR . 'sync_' . $studentId . '.txt';
    $scraper    = new MoodleScraper($cookieFile);
    if ($scraper->login($username, $password)) {
        $courses = $scraper->getCourses();
        foreach ($courses as $course) {
            foreach ($scraper->getCourseContent($course['id']) as $item) {
                getDB()->prepare("INSERT IGNORE INTO notifications_log (student_id,course_id,item_type,item_id,item_title,item_url) VALUES (?,?,?,?,?,?)")
                    ->execute([$studentId, $course['id'], $item['type'], $item['id'], $item['title'], $item['url']]);
                $synced++;
            }
        }
        getDB()->prepare("UPDATE students SET last_check=NOW() WHERE id=?")->execute([$studentId]);
    } else {
        $error = 'فشل تسجيل الدخول إلى Moodle';
    }
    if (file_exists($cookieFile)) @unlink($cookieFile);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="3;url=login.php?registered=1">
<title>تم التسجيل - Moodle Tracker</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #0073aa, #005580); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.card { background: #fff; border-radius: 16px; padding: 40px; width: 100%; max-width: 420px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
.icon { font-size: 64px; margin-bottom: 15px; }
h2 { color: #0073aa; font-size: 22px; margin-bottom: 10px; }
p { color: #666; font-size: 14px; line-height: 1.8; margin-bottom: 5px; }
.badge { display: inline-block; background: #d4edda; color: #155724; border-radius: 20px; padding: 5px 16px; font-size: 14px; font-weight: 600; margin: 10px 0; }
.badge-error { background: #f8d7da; color: #721c24; }
.redirect { color: #aaa; font-size: 12px; margin-top: 15px; }
.btn { display: inline-block; margin-top: 15px; padding: 10px 25px; background: #0073aa; color: #fff; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; }
</style>
</head>
<body>
<div class="card">
    <?php if (!$error): ?>
        <div class="icon">✅</div>
        <h2>تم التسجيل بنجاح!</h2>
        <p>مرحباً <strong><?= htmlspecialchars($student['name']) ?></strong></p>
        <div class="badge">تم مزامنة <?= $synced ?> عنصر من مقرراتك</div>
        <p>من الآن ستصلك إشعارات عند إضافة أي محتوى جديد</p>
    <?php else: ?>
        <div class="icon">⚠️</div>
        <h2>تم التسجيل</h2>
        <div class="badge badge-error">تعذّرت المزامنة — ستتم لاحقاً</div>
    <?php endif; ?>
    <p class="redirect">سيتم تحويلك تلقائياً خلال ثوانٍ...</p>
    <a href="login.php?registered=1" class="btn">تسجيل الدخول الآن</a>
</div>
</body>
</html>
