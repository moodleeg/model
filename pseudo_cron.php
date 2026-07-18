<?php
function runPseudoCron() {
    try {
        $pdo = getDB();
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k VARCHAR(50) PRIMARY KEY, v VARCHAR(255))");

        $rows     = $pdo->query("SELECT k,v FROM settings WHERE k IN ('check_interval','last_cron_run')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $interval = (int)($rows['check_interval'] ?? 1);
        $lastRun  = (int)($rows['last_cron_run']  ?? 0);

        if (time() < $lastRun + ($interval * 60)) return;

        // Lock immediately to prevent parallel runs
        $pdo->prepare("INSERT INTO settings (k,v) VALUES ('last_cron_run',?) ON DUPLICATE KEY UPDATE v=?")
            ->execute([time(), time()]);

        // Run checker directly (no HTTP request)
        _runChecker($pdo);

    } catch (Exception $e) {}
}

function _runChecker($pdo) {
    if (!class_exists('MoodleScraper'))      require_once __DIR__ . '/MoodleScraper.php';
    if (!function_exists('buildEmailBody'))  require_once __DIR__ . '/Mailer.php';

    $students = $pdo->query("SELECT * FROM students WHERE active=1")->fetchAll(PDO::FETCH_ASSOC);

    $typeLabels = [
        'assignment'   => ['icon' => '📝', 'label' => 'واجب جديد'],
        'quiz'         => ['icon' => '📋', 'label' => 'اختبار جديد'],
        'announcement' => ['icon' => '📢', 'label' => 'إعلان جديد'],
        'lecture'      => ['icon' => '📚', 'label' => 'محتوى جديد'],
    ];

    foreach ($students as $student) {
        try {
            $cookieFile = COOKIES_DIR . 'session_' . $student['id'] . '.txt';
            $scraper    = new MoodleScraper($cookieFile);

            if (!$scraper->login($student['moodle_username'], $student['moodle_password'])) continue;

            foreach ($scraper->getCourses() as $course) {
                foreach ($scraper->getCourseContent($course['id']) as $item) {
                    // Check if already notified
                    $s = $pdo->prepare("SELECT id FROM notifications_log WHERE student_id=? AND item_type=? AND item_id=?");
                    $s->execute([$student['id'], $item['type'], $item['id']]);
                    if ($s->fetch()) continue;

                    // Mark first to prevent duplicates
                    $pdo->prepare("INSERT IGNORE INTO notifications_log (student_id,course_id,item_type,item_id,item_title,item_url) VALUES (?,?,?,?,?,?)")
                        ->execute([$student['id'], $course['id'], $item['type'], $item['id'], $item['title'], $item['url']]);

                    // Send email
                    $t    = $typeLabels[$item['type']];
                    $body = buildEmailBody($student['name'], $course['name'], $item['type'], $item['title'], null, $item['url']);
                    sendEmail($student['email'], $student['name'], "{$t['icon']} {$t['label']}: {$item['title']}", $body);
                }
            }

            $pdo->prepare("UPDATE students SET last_check=NOW() WHERE id=?")->execute([$student['id']]);
            if (file_exists($cookieFile)) @unlink($cookieFile);

        } catch (Exception $e) {}
    }
}
