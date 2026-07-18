<?php
require_once 'auth.php';
require_once 'pseudo_cron.php';
authAdmin();
runPseudoCron();

$pdo = getDB();
// Read flash message from redirect
$msgs = [
    'added'     => ['success', 'تم إضافة الطالب بنجاح ✓'],
    'duplicate' => ['error',   'البريد الإلكتروني مسجل مسبقاً'],
    'updated'   => ['success', 'تم تحديث الحالة ✓'],
    'deleted'   => ['success', 'تم حذف الطالب ✓'],
];
$msgKey = $_GET['msg'] ?? '';
$message = isset($msgs[$msgKey]) ? ['type' => $msgs[$msgKey][0], 'text' => $msgs[$msgKey][1]] : '';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        try {
            $pdo->prepare("INSERT INTO students (name, email, moodle_username, moodle_password, password_hash) VALUES (?,?,?,?,?)")
                ->execute([trim($_POST['name']), trim($_POST['email']), trim($_POST['moodle_username']), trim($_POST['moodle_password']), password_hash(trim($_POST['moodle_password']), PASSWORD_DEFAULT)]);
            header('Location: admin.php?msg=added');
            exit;
        } catch (Exception $e) {
            header('Location: admin.php?msg=duplicate');
            exit;
        }
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE students SET active = !active WHERE id=?")->execute([$_POST['id']]);
        header('Location: admin.php?msg=updated');
        exit;
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$_POST['id']]);
        header('Location: admin.php?msg=deleted');
        exit;
    }
}

$students = $pdo->query("SELECT s.*, COUNT(n.id) as notif_count FROM students s LEFT JOIN notifications_log n ON s.id=n.student_id GROUP BY s.id ORDER BY s.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Add last_check column display support
$hasLastCheck = in_array('last_check', array_keys($students[0] ?? ['last_check' => null]));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Moodle Tracker - إدارة الطلاب</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; color: #333; }
.header { background: #0073aa; color: #fff; padding: 20px 30px; display: flex; align-items: center; gap: 15px; }
.header h1 { font-size: 22px; }
.container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
.card { background: #fff; border-radius: 10px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.card h2 { font-size: 18px; margin-bottom: 20px; color: #0073aa; border-bottom: 2px solid #e8f4fd; padding-bottom: 10px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.full { grid-column: 1 / -1; }
label { font-size: 13px; font-weight: 600; color: #555; }
input { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; transition: border .2s; }
input:focus { outline: none; border-color: #0073aa; }
.btn { padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; transition: opacity .2s; }
.btn:hover { opacity: .85; }
.btn-primary { background: #0073aa; color: #fff; }
.btn-danger { background: #dc3545; color: #fff; padding: 6px 14px; font-size: 12px; }
.btn-warning { background: #ffc107; color: #333; padding: 6px 14px; font-size: 12px; }
.btn-success { background: #28a745; color: #fff; padding: 6px 14px; font-size: 12px; }
table { width: 100%; border-collapse: collapse; }
th { background: #f8f9fa; padding: 12px; text-align: right; font-size: 13px; color: #666; border-bottom: 2px solid #e9ecef; }
td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; vertical-align: middle; }
tr:hover td { background: #fafafa; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.badge-active { background: #d4edda; color: #155724; }
.badge-inactive { background: #f8d7da; color: #721c24; }
.badge-count { background: #cce5ff; color: #004085; }
.alert { padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.actions { display: flex; gap: 6px; }
.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
.stat-box { background: #fff; border-radius: 10px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.stat-box .num { font-size: 32px; font-weight: 700; color: #0073aa; }
.stat-box .lbl { font-size: 13px; color: #888; margin-top: 5px; }
.run-btn { display: inline-flex; align-items: center; gap: 8px; }
@media(max-width:600px) { .form-grid { grid-template-columns: 1fr; } .stats { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<div class="header">
    <span style="font-size:28px">🎓</span>
    <div>
        <h1>Moodle Tracker</h1>
        <small>نظام متابعة المقررات الدراسية</small>
    </div>
    <div style="margin-right:auto;display:flex;gap:10px">
        <a href="notifications.php" class="btn btn-primary" style="text-decoration:none;background:#17a2b8">🔔 سجل الإشعارات</a>
        <a href="admin_logout.php" class="btn btn-primary" style="text-decoration:none;background:#dc3545">خروج</a>
    </div>
</div>

<div class="container">

<?php if ($message): ?>
<div class="alert alert-<?= $message['type'] ?>"><?= $message['text'] ?></div>
<?php endif; ?>

<!-- Stats -->
<?php
$total = count($students);
$active = count(array_filter($students, fn($s) => $s['active']));
$totalNotif = array_sum(array_column($students, 'notif_count'));
?>
<div class="stats">
    <div class="stat-box"><div class="num"><?= $total ?></div><div class="lbl">إجمالي الطلاب</div></div>
    <div class="stat-box"><div class="num"><?= $active ?></div><div class="lbl">طلاب نشطون</div></div>
    <div class="stat-box"><div class="num"><?= $totalNotif ?></div><div class="lbl">إشعارات مُرسلة</div></div>
</div>

<!-- Add Student Form -->
<div class="card">
    <h2>➕ إضافة طالب جديد</h2>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
            <div class="form-group">
                <label>الاسم الكامل</label>
                <input type="text" name="name" placeholder="أحمد محمد" required>
            </div>
            <div class="form-group">
                <label>البريد الإلكتروني</label>
                <input type="email" name="email" placeholder="student@example.com" required>
            </div>
            <div class="form-group">
                <label>اسم المستخدم في Moodle</label>
                <input type="text" name="moodle_username" placeholder="اسم المستخدم" required>
            </div>
            <div class="form-group">
                <label>كلمة المرور في Moodle</label>
                <input type="password" name="moodle_password" placeholder="كلمة المرور" required>
            </div>
        </div>
        <div style="margin-top:15px">
            <button type="submit" class="btn btn-primary">إضافة الطالب</button>
        </div>
    </form>
</div>

<!-- Students Table -->
<div class="card">
    <h2>👥 قائمة الطلاب</h2>
    <?php if (empty($students)): ?>
        <p style="text-align:center;color:#888;padding:30px">لا يوجد طلاب مسجلون بعد</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>الاسم</th>
                <th>البريد الإلكتروني</th>
                <th>اسم المستخدم</th>
                <th>الحالة</th>
                <th>الإشعارات</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td><?= htmlspecialchars($s['moodle_username']) ?></td>
                <td>
                    <span class="badge <?= $s['active'] ? 'badge-active' : 'badge-inactive' ?>">
                        <?= $s['active'] ? 'نشط' : 'متوقف' ?>
                    </span>
                </td>
                <td><span class="badge badge-count"><?= $s['notif_count'] ?> إشعار</span></td>
                <td>
                    <div class="actions">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn <?= $s['active'] ? 'btn-warning' : 'btn-success' ?>">
                                <?= $s['active'] ? 'إيقاف' : 'تفعيل' ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn btn-danger">حذف</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="card">
    <h2>⚡ إجراءات سريعة</h2>
    <div style="display:flex;flex-wrap:wrap;gap:12px">
        <a href="run_checker.php" class="btn btn-primary" style="text-decoration:none">▶ تشغيل الفحص الآن</a>
        <a href="background_checker.php" class="btn btn-primary" style="text-decoration:none;background:#28a745">⚙️ الفحص التلقائي</a>
        <a href="diagnostic.php" class="btn btn-primary" style="text-decoration:none;background:#17a2b8">🔍 تشخيص النظام</a>
        <a href="sync.php" class="btn btn-primary" style="text-decoration:none;background:#6c757d">🔄 مزامنة أولية</a>
        <a href="export_db.php" class="btn btn-primary" style="text-decoration:none;background:#17a2b8">💾 تصدير</a>
        <a href="import_db.php" class="btn btn-primary" style="text-decoration:none;background:#fd7e14">📥 استيراد</a>
    </div>
</div>

<div class="card" style="background:#fffbf0;border:1px solid #ffe58f">
    <h2>ℹ️ ملاحظة مهمة</h2>
    <p style="font-size:14px;line-height:2">
        <strong>بوابة التعليم الإلكتروني - جامعة الأقصى</strong><br>
        نظام إشعارات المودل
    </p>
</div>

</div>
</body>
</html>
