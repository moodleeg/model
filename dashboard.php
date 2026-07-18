<?php
require_once 'auth.php';
require_once 'pseudo_cron.php';
$student = authStudent();
runPseudoCron();

$pdo = getDB();
$notifCount = $pdo->prepare("SELECT COUNT(*) FROM notifications_log WHERE student_id=?");
$notifCount->execute([$student['id']]);
$total = $notifCount->fetchColumn();

$filter = $_GET['filter'] ?? 'all';
$validFilters = ['all', 'assignment', 'quiz', 'lecture', 'announcement'];
if (!in_array($filter, $validFilters)) $filter = 'all';

$sql = "SELECT * FROM notifications_log WHERE student_id=?";
$params = [$student['id']];
if ($filter !== 'all') { $sql .= " AND item_type=?"; $params[] = $filter; }
$sql .= " ORDER BY sent_at DESC";
$recent = $pdo->prepare($sql);
$recent->execute($params);
$notifications = $recent->fetchAll(PDO::FETCH_ASSOC);

$typeInfo = [
    'lecture'      => ['icon' => '📚', 'color' => '#0073aa', 'label' => 'محتوى'],
    'assignment'   => ['icon' => '📝', 'color' => '#28a745', 'label' => 'واجب'],
    'quiz'         => ['icon' => '📋', 'color' => '#dc3545', 'label' => 'اختبار'],
    'announcement' => ['icon' => '📢', 'color' => '#fd7e14', 'label' => 'إعلان'],
];

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>لوحة التحكم - <?= htmlspecialchars($student['name']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
.header { background: #0073aa; color: #fff; padding: 15px 25px; display: flex; align-items: center; justify-content: space-between; }
.header h1 { font-size: 18px; }
.header .user { display: flex; align-items: center; gap: 12px; font-size: 14px; }
.header a { color: #fff; text-decoration: none; background: rgba(255,255,255,.2); padding: 6px 14px; border-radius: 6px; font-size: 13px; }
.container { max-width: 900px; margin: 25px auto; padding: 0 20px; }
.welcome { background: linear-gradient(135deg, #0073aa, #005580); color: #fff; border-radius: 12px; padding: 25px; margin-bottom: 20px; }
.welcome h2 { font-size: 20px; margin-bottom: 5px; }
.welcome p { opacity: .85; font-size: 14px; }
.stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 25px; }
.stat { background: #fff; border-radius: 10px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.stat .num { font-size: 28px; font-weight: 700; }
.stat .lbl { font-size: 12px; color: #888; margin-top: 4px; }
.card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px; }
.card h3 { font-size: 16px; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
.notif-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
.notif-item:last-child { border-bottom: none; }
.notif-icon { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.notif-body { flex: 1; }
.notif-title { font-size: 14px; font-weight: 600; color: #333; }
.notif-meta { font-size: 12px; color: #888; margin-top: 3px; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; color: #fff; }
.empty { text-align: center; color: #aaa; padding: 30px; font-size: 14px; }
.info-box { background: #e8f4fd; border-radius: 8px; padding: 15px; font-size: 13px; color: #004085; }
@media(max-width:600px) { .stats { grid-template-columns: repeat(3,1fr); } }
</style>
</head>
<body>

<div class="header">
    <h1>🎓 Moodle Tracker</h1>
    <div class="user">
        <span><?= htmlspecialchars($student['name']) ?></span>
        <a href="student_sync.php" style="background:rgba(255,255,255,.3)">🔄 مزامنة</a>
        <a href="?logout=1">تسجيل الخروج</a>
    </div>
</div>

<div class="container">
    <div class="welcome">
        <h2>مرحباً، <?= htmlspecialchars(explode(' ', $student['name'])[0]) ?> 👋</h2>
        <p>رقمك الجامعي: <?= htmlspecialchars($student['moodle_username']) ?> | البريد: <?= htmlspecialchars($student['email']) ?></p>
        <?php if ($student['last_check']): ?>
        <p style="margin-top:5px;opacity:.7">آخر فحص: <?= date('Y-m-d H:i', strtotime($student['last_check'])) ?></p>
        <?php endif; ?>
    </div>

    <?php
    $counts = ['lecture'=>0,'assignment'=>0,'quiz'=>0,'announcement'=>0];
    foreach ($notifications as $n) $counts[$n['item_type']] = ($counts[$n['item_type']] ?? 0) + 1;
    $allCounts = $pdo->prepare("SELECT item_type, COUNT(*) as c FROM notifications_log WHERE student_id=? GROUP BY item_type");
    $allCounts->execute([$student['id']]);
    foreach ($allCounts->fetchAll(PDO::FETCH_ASSOC) as $r) $counts[$r['item_type']] = $r['c'];
    ?>

    <div class="stats">
        <div class="stat"><div class="num" style="color:#0073aa"><?= $total ?></div><div class="lbl">إجمالي الإشعارات</div></div>
        <div class="stat"><div class="num" style="color:#28a745"><?= $counts['assignment'] ?></div><div class="lbl">واجبات</div></div>
        <div class="stat"><div class="num" style="color:#dc3545"><?= $counts['quiz'] ?></div><div class="lbl">اختبارات</div></div>
        <div class="stat"><div class="num" style="color:#0073aa"><?= $counts['lecture'] ?></div><div class="lbl">محاضرات</div></div>
        <div class="stat"><div class="num" style="color:#fd7e14"><?= $counts['announcement'] ?></div><div class="lbl">إعلانات</div></div>
    </div>

    <div class="card">
        <h3>🔔 آخر الإشعارات <small style="color:#888;font-size:12px;font-weight:400">(<?= count($notifications) ?> إشعار)</small></h3>
        <!-- Filter tabs -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:15px">
            <?php
            $tabs = ['all'=>'الكل','assignment'=>'📝 واجبات','quiz'=>'📋 اختبارات','lecture'=>'📚 محاضرات','announcement'=>'📢 إعلانات'];
            foreach ($tabs as $key => $label):
                $active = $filter === $key;
            ?>
            <a href="?filter=<?= $key ?>" style="padding:6px 14px;border-radius:20px;text-decoration:none;font-size:13px;font-weight:600;background:<?= $active ? '#0073aa' : '#f0f2f5' ?>;color:<?= $active ? '#fff' : '#555' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <!-- Notifications list with scroll -->
        <div id="notifList" style="max-height:400px;overflow-y:auto;padding-left:4px">
        <?php
        $shown = 10;
        $total_notifs = count($notifications);
        ?>
        <?php if (empty($notifications)): ?>
            <p class="empty">لا توجد إشعارات بعد — سيصلك إشعار عند إضافة أي محتوى جديد</p>
        <?php else: ?>
            <?php foreach ($notifications as $i => $n):
                $t = $typeInfo[$n['item_type']] ?? ['icon'=>'📌','color'=>'#888','label'=>''];
            ?>
            <div class="notif-item" style="<?= $i >= $shown ? 'display:none' : '' ?>" data-index="<?= $i ?>">
                <div class="notif-icon" style="background:<?= $t['color'] ?>22"><?= $t['icon'] ?></div>
                <div class="notif-body">
                    <a href="<?= htmlspecialchars($n['item_url'] ?? '#') ?>" target="_blank" style="text-decoration:none;color:inherit">
                        <div class="notif-title"><?= htmlspecialchars($n['item_title']) ?></div>
                    </a>
                    <div class="notif-meta">
                        <span class="badge" style="background:<?= $t['color'] ?>"><?= $t['label'] ?></span>
                        &nbsp;<?= date('Y-m-d H:i', strtotime($n['sent_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if ($total_notifs > $shown): ?>
            <div id="showMoreBtn" style="text-align:center;padding:12px">
                <button onclick="showAll()" style="padding:8px 20px;background:#f0f2f5;border:none;border-radius:20px;font-size:13px;font-weight:600;color:#0073aa;cursor:pointer">
                    عرض الكل (<?= $total_notifs ?> إشعار)
                </button>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>

<script>
function showAll() {
    document.querySelectorAll('.notif-item').forEach(el => el.style.display = 'flex');
    document.getElementById('showMoreBtn').style.display = 'none';
}
</script>
    </div>

    <div class="info-box">
        🔒 بياناتك محفوظة بأمان — يتم الفحص تلقائياً كل دقيقة وسيصلك إيميل فور إضافة أي محتوى جديد في مقرراتك.
    </div>
</div>
</body>
</html>
