<?php
/**
 * ============================================================
 * Moodle Tracker - Checker API
 * ============================================================
 * Base URL: https://model.zya.me/checker_api.php
 *
 * Authentication:
 *   ?key=moodle_tracker_2024_secret  (required)
 *
 * Parameters:
 *   ?key=SECRET          — API key (required)
 *   ?student_id=1        — Check specific student only (optional)
 *   ?notify=0            — Check only, don't send emails (optional, default=1)
 *   ?format=simple       — Return simplified response (optional)
 *
 * Examples:
 *   Check all students:
 *     GET /checker_api.php?key=SECRET
 *
 *   Check one student:
 *     GET /checker_api.php?key=SECRET&student_id=1
 *
 *   Check without sending emails:
 *     GET /checker_api.php?key=SECRET&notify=0
 *
 *   Simple response format:
 *     GET /checker_api.php?key=SECRET&format=simple
 * ============================================================
 */

set_time_limit(0);
ini_set('max_execution_time', 0);
ignore_user_abort(true);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, HEAD');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle HEAD requests (uptime monitors)
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    http_response_code(200);
    exit;
}

// ── Auth ──────────────────────────────────────────────────────────────────
$validKey = 'moodle_tracker_2024_secret';
$inputKey = $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($inputKey !== $validKey) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized — provide ?key=YOUR_API_KEY',
    ]);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/MoodleScraper.php';
require_once __DIR__ . '/Mailer.php';

// ── Options ───────────────────────────────────────────────────────────────
$studentId  = (int)($_GET['student_id'] ?? 0);
$sendEmails = ($_GET['notify'] ?? '1') !== '0';
$format     = $_GET['format'] ?? 'full';

// ── Helpers ───────────────────────────────────────────────────────────────
function isNotified($studentId, $type, $itemId) {
    $stmt = getDB()->prepare("SELECT id FROM notifications_log WHERE student_id=? AND item_type=? AND item_id=?");
    $stmt->execute([$studentId, $type, $itemId]);
    return $stmt->fetch() !== false;
}

function markNotified($studentId, $courseId, $type, $itemId, $title, $url = null) {
    getDB()->prepare("INSERT IGNORE INTO notifications_log (student_id,course_id,item_type,item_id,item_title,item_url) VALUES (?,?,?,?,?,?)")
        ->execute([$studentId, $courseId, $type, $itemId, $title, $url]);
}

// ── Fetch students ────────────────────────────────────────────────────────
$where    = $studentId ? "WHERE active=1 AND id=$studentId" : "WHERE active=1";
$students = getDB()->query("SELECT * FROM students $where")->fetchAll(PDO::FETCH_ASSOC);

if (empty($students)) {
    echo json_encode(['success' => false, 'error' => 'No active students found']);
    exit;
}

$typeLabels = [
    'assignment'   => ['icon' => '📝', 'label' => 'واجب جديد'],
    'quiz'         => ['icon' => '📋', 'label' => 'اختبار جديد'],
    'announcement' => ['icon' => '📢', 'label' => 'إعلان جديد'],
    'lecture'      => ['icon' => '📚', 'label' => 'محتوى جديد'],
];

$results  = [];
$totalNew = 0;

// ── Process each student ──────────────────────────────────────────────────
foreach ($students as $student) {
    $result = [
        'student_id' => $student['id'],
        'student'    => $student['name'],
        'email'      => $student['email'],
        'status'     => 'ok',
        'new_count'  => 0,
        'new_items'  => [],
        'errors'     => [],
    ];

    try {
        $cookieFile = COOKIES_DIR . 'session_' . $student['id'] . '.txt';
        $scraper    = new MoodleScraper($cookieFile);

        if (!$scraper->login($student['moodle_username'], $student['moodle_password'])) {
            $result['status'] = 'login_failed';
            $results[] = $result;
            continue;
        }

        $courses = $scraper->getCourses();
        $result['courses_count'] = count($courses);

        foreach ($courses as $course) {
            foreach ($scraper->getCourseContent($course['id']) as $item) {
                if (isNotified($student['id'], $item['type'], $item['id'])) continue;

                // Always mark as notified first to prevent duplicates
                markNotified($student['id'], $course['id'], $item['type'], $item['id'], $item['title'], $item['url']);

                // Send email if enabled
                if ($sendEmails) {
                    $t    = $typeLabels[$item['type']];
                    $body = buildEmailBody($student['name'], $course['name'], $item['type'], $item['title'], null, $item['url']);
                    $subj = "{$t['icon']} {$t['label']}: {$item['title']}";
                    sendEmail($student['email'], $student['name'], $subj, $body);
                }

                $result['new_items'][] = [
                    'type'   => $item['type'],
                    'title'  => $item['title'],
                    'course' => $course['name'],
                    'url'    => $item['url'] ?? null,
                ];
                $result['new_count']++;
                $totalNew++;
            }
        }

        getDB()->prepare("UPDATE students SET last_check=NOW() WHERE id=?")->execute([$student['id']]);
        if (file_exists($cookieFile)) @unlink($cookieFile);

    } catch (Exception $e) {
        $result['status']   = 'error';
        $result['errors'][] = $e->getMessage();
    }

    $results[] = $result;
}

// ── Response ──────────────────────────────────────────────────────────────
if ($format === 'simple') {
    echo json_encode([
        'success'    => true,
        'checked_at' => date('Y-m-d H:i:s'),
        'total_new'  => $totalNew,
        'students'   => array_map(fn($r) => [
            'name'      => $r['student'],
            'status'    => $r['status'],
            'new_count' => $r['new_count'],
        ], $results),
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success'        => true,
        'checked_at'     => date('Y-m-d H:i:s'),
        'total_students' => count($results),
        'total_new'      => $totalNew,
        'emails_sent'    => $sendEmails,
        'students'       => $results,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
