<?php
set_time_limit(600);
require_once 'config.php';
require_once 'MoodleScraper.php';
require_once 'Mailer.php';

$logs = [];

function isNotified($studentId, $type, $itemId) {
    $stmt = getDB()->prepare("SELECT id FROM notifications_log WHERE student_id=? AND item_type=? AND item_id=?");
    $stmt->execute([$studentId, $type, $itemId]);
    return $stmt->fetch() !== false;
}

function markNotified($studentId, $courseId, $type, $itemId, $title, $url = null) {
    getDB()->prepare("INSERT IGNORE INTO notifications_log (student_id,course_id,item_type,item_id,item_title,item_url) VALUES (?,?,?,?,?,?)")
        ->execute([$studentId, $courseId, $type, $itemId, $title, $url]);
}

$typeLabels = [
    'assignment'   => ['icon' => '📝', 'label' => 'واجب جديد'],
    'quiz'         => ['icon' => '📋', 'label' => 'اختبار جديد'],
    'announcement' => ['icon' => '📢', 'label' => 'إعلان جديد'],
    'lecture'      => ['icon' => '📚', 'label' => 'محتوى جديد'],
];

$students = getDB()->query("SELECT * FROM students WHERE active=1")->fetchAll(PDO::FETCH_ASSOC);

if (empty($students)) {
    $logs[] = ['type' => 'warning', 'msg' => '⚠ لا يوجد طلاب نشطون مسجلون'];
} else {
    foreach ($students as $student) {
        $logs[] = ['type' => 'info', 'msg' => "🔍 جاري فحص: <strong>{$student['name']}</strong> ({$student['email']})"];

        try {
            $cookieFile = COOKIES_DIR . 'session_' . $student['id'] . '.txt';
            $scraper = new MoodleScraper($cookieFile);

            if (!$scraper->login($student['moodle_username'], $student['moodle_password'])) {
                $logs[] = ['type' => 'error', 'msg' => "✗ فشل تسجيل الدخول — تحقق من اسم المستخدم وكلمة المرور"];
                continue;
            }

            $logs[] = ['type' => 'success', 'msg' => "✓ تم تسجيل الدخول بنجاح"];

            $courses = $scraper->getCourses();
            if (empty($courses)) {
                $logs[] = ['type' => 'warning', 'msg' => "⚠ لا توجد مقررات جارية"];
                continue;
            }

            $allCount = $scraper->getAllCoursesCount();
            $logs[] = ['type' => 'info', 'msg' => "📚 إجمالي المقررات: <strong>$allCount</strong> | الجارية: <strong>" . count($courses) . "</strong>"];

            // Fetch all course contents
            $allCourseItems = [];
            $totalItems = 0;
            foreach ($courses as $course) {
                $items = $scraper->getCourseContent($course['id']);
                $allCourseItems[$course['id']] = $items;
                $totalItems += count($items);
            }
            $logs[] = ['type' => 'info', 'msg' => "📋 إجمالي المهام في المقررات الجارية: <strong>$totalItems</strong> مهمة"];

            $totalNew = 0;
            foreach ($courses as $course) {
                $items    = $allCourseItems[$course['id']];
                $newCount = 0;

                foreach ($items as $item) {
                    if (isNotified($student['id'], $item['type'], $item['id'])) continue;

                    $t    = $typeLabels[$item['type']];
                    $body = buildEmailBody($student['name'], $course['name'], $item['type'], $item['title'], null, $item['url']);
                    $subj = "{$t['icon']} {$t['label']}: {$item['title']}";

                    if (sendEmail($student['email'], $student['name'], $subj, $body)) {
                        markNotified($student['id'], $course['id'], $item['type'], $item['id'], $item['title'], $item['url']);
                        $logs[] = ['type' => 'success', 'msg' => "✓ {$t['label']} أُرسل: <strong>{$item['title']}</strong> — {$course['name']}"];
                        $newCount++;
                        $totalNew++;
                    }
                }

                if ($newCount === 0) {
                    $logs[] = ['type' => 'info', 'msg' => "— لا جديد في: {$course['name']}"];
                }
            }

            $logs[] = ['type' => $totalNew > 0 ? 'success' : 'info',
                       'msg'  => "🆕 المهام الجديدة في هذا الفحص: <strong>$totalNew</strong> مهمة"];

            getDB()->prepare("UPDATE students SET last_check=NOW() WHERE id=?")->execute([$student['id']]);
            if (file_exists($cookieFile)) @unlink($cookieFile);

        } catch (Exception $e) {
            $logs[] = ['type' => 'error', 'msg' => "✗ خطأ: " . htmlspecialchars($e->getMessage())];
        }
    }

    $logs[] = ['type' => 'info', 'msg' => '✅ اكتمل الفحص في ' . date('Y-m-d H:i:s')];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تشغيل الفحص - Moodle Tracker</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
.header { background: #0073aa; color: #fff; padding: 20px 30px; display: flex; align-items: center; gap: 15px; }
.header h1 { font-size: 20px; }
.container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
.card { background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.card h2 { font-size: 18px; color: #0073aa; margin-bottom: 20px; border-bottom: 2px solid #e8f4fd; padding-bottom: 10px; }
.log-item { padding: 10px 15px; border-radius: 6px; margin-bottom: 8px; font-size: 14px; }
.log-success { background: #d4edda; color: #155724; border-right: 4px solid #28a745; }
.log-error   { background: #f8d7da; color: #721c24; border-right: 4px solid #dc3545; }
.log-warning { background: #fff3cd; color: #856404; border-right: 4px solid #ffc107; }
.log-info    { background: #e8f4fd; color: #004085; border-right: 4px solid #0073aa; }
.btn { display: inline-block; padding: 10px 24px; background: #0073aa; color: #fff; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 600; margin-top: 20px; margin-left: 10px; }
.btn-green { background: #28a745; }
</style>
</head>
<body>
<div class="header">
    <span style="font-size:26px">▶</span>
    <div>
        <h1>نتائج الفحص</h1>
        <small><?= date('Y-m-d H:i:s') ?></small>
    </div>
</div>
<div class="container">
    <div class="card">
        <h2>📋 سجل العمليات</h2>
        <?php foreach ($logs as $log): ?>
            <div class="log-item log-<?= $log['type'] ?>"><?= $log['msg'] ?></div>
        <?php endforeach; ?>
        <a href="admin.php" class="btn">← الرئيسية</a>
        <a href="run_checker.php" class="btn btn-green">🔄 إعادة الفحص</a>
    </div>
</div>
</body>
</html>
