<?php
require_once 'auth.php';
authAdmin();

$pdo = getDB();
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="moodle_tracker_backup_' . date('Y-m-d_H-i') . '.sql"');

echo "-- Moodle Tracker Backup - " . date('Y-m-d H:i:s') . "\n";
echo "-- ============================================================\n\n";

// Export students
echo "TRUNCATE TABLE notifications_log;\nTRUNCATE TABLE students;\n\n";
$students = $pdo->query("SELECT * FROM students")->fetchAll(PDO::FETCH_ASSOC);
foreach ($students as $s) {
    echo "INSERT INTO students (id,name,email,moodle_username,moodle_password,password_hash,active,last_check,created_at) VALUES ("
        . $s['id'] . ","
        . $pdo->quote($s['name']) . ","
        . $pdo->quote($s['email']) . ","
        . $pdo->quote($s['moodle_username']) . ","
        . $pdo->quote($s['moodle_password']) . ","
        . $pdo->quote($s['password_hash']) . ","
        . $s['active'] . ","
        . ($s['last_check'] ? $pdo->quote($s['last_check']) : 'NULL') . ","
        . $pdo->quote($s['created_at'])
        . ");\n";
}

// Export notifications_log
echo "\n";
$logs = $pdo->query("SELECT * FROM notifications_log")->fetchAll(PDO::FETCH_ASSOC);
foreach ($logs as $n) {
    echo "INSERT INTO notifications_log (id,student_id,course_id,item_type,item_id,item_title,item_url,sent_at) VALUES ("
        . $n['id'] . ","
        . $n['student_id'] . ","
        . $pdo->quote($n['course_id']) . ","
        . $pdo->quote($n['item_type']) . ","
        . $pdo->quote($n['item_id']) . ","
        . $pdo->quote($n['item_title']) . ","
        . ($n['item_url'] ? $pdo->quote($n['item_url']) : 'NULL') . ","
        . $pdo->quote($n['sent_at'])
        . ");\n";
}

// Export admins
echo "\n";
$admins = $pdo->query("SELECT * FROM admins")->fetchAll(PDO::FETCH_ASSOC);
foreach ($admins as $a) {
    echo "INSERT IGNORE INTO admins (id,username,password_hash,created_at) VALUES ("
        . $a['id'] . ","
        . $pdo->quote($a['username']) . ","
        . $pdo->quote($a['password_hash']) . ","
        . $pdo->quote($a['created_at'])
        . ");\n";
}

echo "\n-- End of backup\n";
