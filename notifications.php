<?php
require_once 'config.php';
$pdo = getDB();

$studentId = $_GET['student_id'] ?? null;
$typeFilter = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($studentId) {
    $where[] = 'n.student_id = ?';
    $params[] = $studentId;
}
if ($typeFilter) {
    $where[] = 'n.item_type = ?';
    $params[] = $typeFilter;
}

$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM notifications_log n WHERE $whereStr");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $pdo->prepare("
    SELECT n.*, s.name as student_name, s.email
    FROM notifications_log n
    JOIN students s ON s.id = n.student_id
    WHERE $whereStr
    ORDER BY n.sent_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$students = $pdo->query("SELECT id, name FROM students ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$typeLabels = [
    'lecture'      => ['label' => 'محاضرة', 'icon' => '📚', 'color' => '#0073aa'],
    'assignment'   => ['label' => 'واجب',   'icon' => '📝', 'color' => '#28a745'],
    'quiz'         => ['label' => 'اختبار', 'icon' => '📋', 'color' => '#dc3545'],
    'announcement' => ['label' => 'إعلان',  'icon' => '📢', 'color' => '#fd7e14'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>سجل الإشعارات - Moodle Tracker</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
.header { background: #0073aa; color: #fff; padding: 20px 30px; display: flex; align-items: center; gap: 15px; }
.header h1 { font-size: 20px; }
.container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
.card { background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 20px; }
.card h2 { font-size: 18px; color: #0073aa; margin-bottom: 20px; border-bottom: 2px solid #e8f4fd; padding-bottom: 10px; }
.filters { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.filters select, .filters a { padding: 9px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; background: #fff; cursor: pointer; text-decoration: none; color: #333; }
.filters select:focus { outline: none; border-color: #0073aa; }
.btn-filter { background: #0073aa; color: #fff; border: none; padding: 9px 20px; border-radius: 6px; font-size: 14px; cursor: pointer; }
table { width: 100%; border-collapse: collapse; }
th { background: #f8f9fa; padding: 12px; text-align: right; font-size: 13px; color: #666; border-bottom: 2px solid #e9ecef; }
td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
tr:hover td { background: #fafafa; }
.badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: #fff; }
.pagination { display: flex; gap: 6px; justify-content: center; margin-top: 20px; }
.pagination a, .pagination span { padding: 8px 14px; border-radius: 6px; text-decoration: none; font-size: 14px; border: 1px solid #ddd; color: #333; }
.pagination a:hover { background: #e8f4fd; }
.pagination .active { background: #0073aa; color: #fff; border-color: #0073aa; }
.empty { text-align: center; color: #888; padding: 40px; }
.back-btn { display: inline-block; padding: 9px 20px; background: #6c757d; color: #fff; border-radius: 6px; text-decoration: none; font-size: 14px; }
</style>
</head>
<body>

<div class="header">
    <span style="font-size:26px">🔔</span>
    <div>
        <h1>سجل الإشعارات المُرسلة</h1>
        <small>إجمالي: <?= $totalRows ?> إشعار</small>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2>🔍 تصفية النتائج</h2>
        <form method="GET" class="filters">
            <select name="student_id">
                <option value="">كل الطلاب</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $studentId == $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="type">
                <option value="">كل الأنواع</option>
                <?php foreach ($typeLabels as $key => $t): ?>
                    <option value="<?= $key ?>" <?= $typeFilter === $key ? 'selected' : '' ?>>
                        <?= $t['icon'] ?> <?= $t['label'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter">تصفية</button>
            <a href="notifications.php">إعادة تعيين</a>
        </form>
    </div>

    <div class="card">
        <h2>📋 الإشعارات</h2>
        <?php if (empty($notifications)): ?>
            <p class="empty">لا توجد إشعارات مطابقة</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>الطالب</th>
                    <th>النوع</th>
                    <th>العنوان</th>
                    <th>رقم المقرر</th>
                    <th>تاريخ الإرسال</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($notifications as $n):
                $t = $typeLabels[$n['item_type']] ?? ['label' => $n['item_type'], 'icon' => '📌', 'color' => '#888'];
            ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($n['student_name']) ?></strong><br>
                        <small style="color:#888"><?= htmlspecialchars($n['email']) ?></small>
                    </td>
                    <td>
                        <span class="badge" style="background:<?= $t['color'] ?>">
                            <?= $t['icon'] ?> <?= $t['label'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($n['item_title']) ?></td>
                    <td><small style="color:#888"><?= $n['course_id'] ?></small></td>
                    <td><?= date('Y-m-d H:i', strtotime($n['sent_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php
                $q = http_build_query(['student_id' => $studentId, 'type' => $typeFilter, 'page' => $i]);
                ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="notifications.php?<?= $q ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>

        <div style="margin-top:20px">
            <a href="admin.php" class="back-btn">← العودة للرئيسية</a>
        </div>
    </div>
</div>

</body>
</html>
