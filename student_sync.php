<?php
require_once 'auth.php';
require_once 'MoodleScraper.php';
$student = authStudent();

$synced = 0;
$error  = '';

try {
    set_time_limit(300);
    $cookieFile = COOKIES_DIR . 'sync_' . $student['id'] . '.txt';
    $scraper    = new MoodleScraper($cookieFile);

    if ($scraper->login($student['moodle_username'], $student['moodle_password'])) {
        $courses = $scraper->getCourses();
        foreach ($courses as $course) {
            foreach ($scraper->getCourseContent($course['id']) as $item) {
                getDB()->prepare("INSERT IGNORE INTO notifications_log (student_id,course_id,item_type,item_id,item_title,item_url) VALUES (?,?,?,?,?,?)")
                    ->execute([$student['id'], $course['id'], $item['type'], $item['id'], $item['title'], $item['url']]);
                $synced++;
            }
        }
        getDB()->prepare("UPDATE students SET last_check=NOW() WHERE id=?")->execute([$student['id']]);
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
<meta http-equiv="refresh" content="3;url=dashboard.php">
<title>مزامنة - Moodle Tracker</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #0073aa, #005580); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.card { background: #fff; border-radius: 16px; padding: 40px; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
.icon { font-size: 60px; margin-bottom: 15px; }
h2 { color: #0073aa; font-size: 20px; margin-bottom: 10px; }
p { color: #666; font-size: 14px; line-height: 1.8; }
.badge { display: inline-block; padding: 6px 18px; border-radius: 20px; font-size: 14px; font-weight: 600; margin: 12px 0; }
.badge-ok    { background: #d4edda; color: #155724; }
.badge-error { background: #f8d7da; color: #721c24; }
.redirect { color: #aaa; font-size: 12px; margin-top: 12px; }
.btn { display: inline-block; margin-top: 12px; padding: 10px 25px; background: #0073aa; color: #fff; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; }
</style>
</head>
<body>
<div class="card">
    <?php if (!$error): ?>
        <div class="icon">✅</div>
        <h2>تمت المزامنة بنجاح</h2>
        <div class="badge badge-ok">تم تسجيل <?= $synced ?> عنصر</div>
        <p>تم تحديث بيانات مقرراتك — من الآن ستصلك إشعارات فقط للمحتوى الجديد</p>
    <?php else: ?>
        <div class="icon">⚠️</div>
        <h2>تعذّرت المزامنة</h2>
        <div class="badge badge-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <p class="redirect">سيتم تحويلك للوحة التحكم خلال ثوانٍ...</p>
    <a href="dashboard.php" class="btn">← العودة للوحة التحكم</a>
</div>
</body>
</html>
