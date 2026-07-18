<?php
set_time_limit(600);
header('Content-Type: application/json; charset=utf-8');

$validKey = 'moodle_tracker_2024_secret';
if (($_GET['key'] ?? '') !== $validKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/MoodleScraper.php';

$studentId = (int)($_GET['id'] ?? 0);
$where = $studentId ? "WHERE id=$studentId AND active=1" : "WHERE active=1";
$students = getDB()->query("SELECT * FROM students $where")->fetchAll(PDO::FETCH_ASSOC);

$results = [];
foreach ($students as $student) {
    $cookieFile = COOKIES_DIR . 'sync_' . $student['id'] . '.txt';
    $scraper = new MoodleScraper($cookieFile);
    $total = 0;

    if ($scraper->login($student['moodle_username'], $student['moodle_password'])) {
        foreach ($scraper->getCourses() as $course) {
            foreach ($scraper->getCourseContent($course['id']) as $item) {
                getDB()->prepare("INSERT IGNORE INTO notifications_log (student_id,course_id,item_type,item_id,item_title,item_url) VALUES (?,?,?,?,?,?)")
                    ->execute([$student['id'], $course['id'], $item['type'], $item['id'], $item['title'], $item['url']]);
                $total++;
            }
        }
        getDB()->prepare("UPDATE students SET last_check=NOW() WHERE id=?")->execute([$student['id']]);
        $results[] = ['student' => $student['name'], 'synced' => $total, 'status' => 'ok'];
    } else {
        $results[] = ['student' => $student['name'], 'synced' => 0, 'status' => 'login_failed'];
    }

    if (file_exists($cookieFile)) @unlink($cookieFile);
}

echo json_encode(['success' => true, 'synced_at' => date('Y-m-d H:i:s'), 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
