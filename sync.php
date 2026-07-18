<?php
set_time_limit(600);
require_once 'config.php';
require_once 'MoodleScraper.php';

$pdo = getDB();
$logs = [];

function markNotified($pdo, $studentId, $courseId, $type, $itemId, $title, $url = null) {
    $pdo->prepare("INSERT IGNORE INTO notifications_log (student_id,course_id,item_type,item_id,item_title,item_url) VALUES (?,?,?,?,?,?)")
        ->execute([$studentId, $courseId, $type, $itemId, $title, $url]);
}

$students = $pdo->query("SELECT * FROM students WHERE active=1")->fetchAll(PDO::FETCH_ASSOC);

if (empty($students)) {
    $logs[] = ['type' => 'warning', 'msg' => '⚠ لا يوجد طلاب نشطون'];
} else {
    foreach ($students as $student) {
        $logs[] = ['type' => 'info', 'msg' => "🔍 جاري مزامنة: <strong>{$student['name']}</strong>"];

        $cookieFile = COOKIES_DIR . 'session_' . $student['id'] . '.txt';
        $scraper = new MoodleScraper($cookieFile);

        if (!$scraper->login($student['moodle_username'], $student['moodle_password'])) {
            $logs[] = ['type' => 'error', 'msg' => '✗ فشل تسجيل الدخول'];
            continue;
        }

        $courses = $scraper->getCourses();
        $total = 0;

        foreach ($courses as $course) {
            $items = $scraper->getCourseContent($course['id']);
            foreach ($items as $item) {
                markNotified($pdo, $student['id'], $course['id'], $item['type'], $item['id'], $item['title'], $item['url']);
                $total++;
            }
        }

        $logs[] = ['type' => 'success', 'msg' => "✓ تم تسجيل <strong>$total</strong> عنصر كـ'مقروء' — لن يُرسل عنها إيميل"];
        $pdo->prepare("UPDATE students SET last_check=NOW() WHERE id=?")->execute([$student['id']]);
        if (file_exists($cookieFile)) @unlink($cookieFile);
    }
    $logs[] = ['type' => 'info', 'msg' => '✅ اكتملت المزامنة — من الآن سيُرسل إيميل فقط للمحتوى الجديد'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>مزامنة أولية - Moodle Tracker</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
.header { background: #6c757d; color: #fff; padding: 20px 30px; display: flex; align-items: center; gap: 15px; }
.header h1 { font-size: 20px; }
.container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
.card { background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.card h2 { font-size: 18px; color: #6c757d; margin-bottom: 20px; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; }
.log-item { padding: 10px 15px; border-radius: 6px; margin-bottom: 8px; font-size: 14px; }
.log-success { background: #d4edda; color: #155724; border-right: 4px solid #28a745; }
.log-error   { background: #f8d7da; color: #721c24; border-right: 4px solid #dc3545; }
.log-warning { background: #fff3cd; color: #856404; border-right: 4px solid #ffc107; }
.log-info    { background: #e8f4fd; color: #004085; border-right: 4px solid #0073aa; }
.btn { display: inline-block; padding: 10px 24px; background: #0073aa; color: #fff; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 600; margin-top: 20px; margin-left: 10px; }
</style>
</head>
<body>
<div class="header">
    <span style="font-size:26px">🔄</span>
    <div>
        <h1>مزامنة أولية</h1>
        <small>تسجيل المحتوى الحالي بدون إرسال إيميلات</small>
    </div>
</div>
<div class="container">
    <div class="card">
        <h2>📋 نتائج المزامنة</h2>
        <?php foreach ($logs as $log): ?>
            <div class="log-item log-<?= $log['type'] ?>"><?= $log['msg'] ?></div>
        <?php endforeach; ?>
        <a href="admin.php" class="btn">← الرئيسية</a>
        <a href="sync.php" class="btn" style="background:#28a745">▶ تشغيل الفحص</a>
    </div>
</div>
</body>
</html>
