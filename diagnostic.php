<?php
require_once 'auth.php';
authAdmin();

require_once 'pseudo_cron.php';
require_once 'MoodleScraper.php';
require_once 'Mailer.php';
runPseudoCron();

$pdo = getDB();

// Get settings
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (k VARCHAR(50) PRIMARY KEY, v VARCHAR(255))");
$settings = $pdo->query("SELECT k,v FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$lastCron     = (int)($settings['last_cron_run'] ?? 0);
$interval     = (int)($settings['check_interval'] ?? 1);
$nextRun      = $lastCron + ($interval * 60);
$secondsAgo   = time() - $lastCron;
$secondsLeft  = max(0, $nextRun - time());

// Get students last check
$students = $pdo->query("SELECT id, name, email, active, last_check FROM students ORDER BY last_check DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get recent notifications
$recent = $pdo->query("SELECT n.*, s.name as student_name FROM notifications_log n JOIN students s ON s.id=n.student_id ORDER BY n.sent_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Test DB connection
$dbOk = true;
try { $pdo->query("SELECT 1"); } catch(Exception $e) { $dbOk = false; }

// Test SMTP (just check config)
$smtpOk = defined('SMTP_USER') && SMTP_USER !== 'your_email@gmail.com';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="30">
<title>تشخيص النظام - Moodle Tracker</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
.header { background: #0073aa; color: #fff; padding: 18px 25px; display: flex; align-items: center; justify-content: space-between; }
.header h1 { font-size: 18px; }
.container { max-width: 900px; margin: 25px auto; padding: 0 20px; }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
.card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.07); }
.card h3 { font-size: 15px; color: #333; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #f0f0f0; }
.status-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f5f5; font-size: 14px; }
.status-row:last-child { border: none; }
.badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.ok    { background: #d4edda; color: #155724; }
.warn  { background: #fff3cd; color: #856404; }
.error { background: #f8d7da; color: #721c24; }
.big-num { font-size: 36px; font-weight: 700; color: #0073aa; text-align: center; margin: 10px 0 5px; }
.big-lbl { text-align: center; color: #888; font-size: 13px; }
.progress { height: 8px; background: #e9ecef; border-radius: 4px; margin-top: 10px; overflow: hidden; }
.progress-fill { height: 100%; background: #0073aa; border-radius: 4px; transition: width .5s; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th { background: #f8f9fa; padding: 8px 10px; text-align: right; color: #666; }
td { padding: 8px 10px; border-bottom: 1px solid #f0f0f0; }
.btn { display: inline-block; padding: 8px 18px; background: #0073aa; color: #fff; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; }
.refresh-note { color: #aaa; font-size: 12px; }
</style>
</head>
<body>

<div class="header">
    <h1>🔍 تشخيص النظام</h1>
    <div style="display:flex;align-items:center;gap:15px">
        <span class="refresh-note">يتحدث تلقائياً كل 30 ثانية</span>
        <a href="admin.php" class="btn">← الإدارة</a>
    </div>
</div>

<div class="container">

    <!-- Status Cards -->
    <div class="grid">

        <!-- Cron Status -->
        <div class="card">
            <h3>⏱ حالة الفحص التلقائي</h3>
            <?php
            $cronStatus = $lastCron === 0 ? 'لم يعمل بعد' : ($secondsAgo < 120 ? 'يعمل ✓' : 'متأخر ⚠');
            $cronClass  = $lastCron === 0 ? 'error' : ($secondsAgo < 120 ? 'ok' : 'warn');
            ?>
            <div class="big-num" id="lastCheckTime"><?= $lastCron ? date('H:i:s', $lastCron) : '--' ?></div>
            <div class="big-lbl">آخر فحص</div>
            <div class="progress">
                <div class="progress-fill" id="progressBar" style="width:<?= $lastCron ? max(5, 100 - ($secondsLeft / ($interval*60) * 100)) : 0 ?>%"></div>
            </div>
            <div style="margin-top:12px">
                <div class="status-row">
                    <span>الحالة</span>
                    <span class="badge <?= $cronClass ?>" id="cronStatus"><?= $cronStatus ?></span>
                </div>
                <div class="status-row">
                    <span>منذ آخر فحص</span>
                    <span id="secondsAgo"><?= $lastCron ? $secondsAgo . ' ثانية' : 'لم يعمل' ?></span>
                </div>
                <div class="status-row">
                    <span>الفحص القادم خلال</span>
                    <span id="secondsLeft"><?= $secondsLeft ?> ثانية</span>
                </div>
                <div class="status-row">
                    <span>الفترة المحددة</span>
                    <span><?= $interval ?> دقيقة</span>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="card">
            <h3>⚙️ حالة المكونات</h3>
            <div class="status-row">
                <span>قاعدة البيانات</span>
                <span class="badge <?= $dbOk ? 'ok' : 'error' ?>"><?= $dbOk ? 'متصلة ✓' : 'خطأ ✗' ?></span>
            </div>
            <div class="status-row">
                <span>إعدادات SMTP</span>
                <span class="badge <?= $smtpOk ? 'ok' : 'warn' ?>"><?= $smtpOk ? 'مضبوطة ✓' : 'تحقق من config.php' ?></span>
            </div>
            <div class="status-row">
                <span>مجلد Cookies</span>
                <span class="badge <?= is_writable(COOKIES_DIR) ? 'ok' : 'error' ?>"><?= is_writable(COOKIES_DIR) ? 'قابل للكتابة ✓' : 'غير قابل للكتابة ✗' ?></span>
            </div>
            <div class="status-row">
                <span>cURL</span>
                <span class="badge <?= function_exists('curl_init') ? 'ok' : 'error' ?>"><?= function_exists('curl_init') ? 'متاح ✓' : 'غير متاح ✗' ?></span>
            </div>
            <div class="status-row">
                <span>عدد الطلاب النشطين</span>
                <span class="badge ok"><?= count(array_filter($students, fn($s) => $s['active'])) ?> طالب</span>
            </div>
            <div class="status-row">
                <span>إجمالي الإشعارات</span>
                <span class="badge ok"><?= $pdo->query("SELECT COUNT(*) FROM notifications_log")->fetchColumn() ?></span>
            </div>
        </div>
    </div>

    <!-- Students Last Check -->
    <div class="card" style="margin-bottom:15px">
        <h3>👥 آخر فحص لكل طالب</h3>
        <table>
            <thead><tr><th>الطالب</th><th>البريد</th><th>الحالة</th><th>آخر فحص</th><th>منذ</th></tr></thead>
            <tbody>
            <?php foreach ($students as $s):
                $ago = $s['last_check'] ? max(0, time() - strtotime($s['last_check'])) : null;
                $cls = !$ago ? 'error' : ($ago < 300 ? 'ok' : ($ago < 3600 ? 'warn' : 'error'));
                $agoText = !$ago ? 'لم يعمل' : ($ago < 60 ? $ago . ' ثانية' : round($ago/60) . ' دقيقة');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td><span class="badge <?= $s['active'] ? 'ok' : 'error' ?>"><?= $s['active'] ? 'نشط' : 'متوقف' ?></span></td>
                <td><?= $s['last_check'] ? date('Y-m-d H:i:s', strtotime($s['last_check'])) : 'لم يُفحص بعد' ?></td>
                <td><span class="badge <?= $cls ?>"><?= $agoText ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Notifications -->
    <div class="card" style="margin-bottom:15px">
        <h3>🔔 آخر 5 إشعارات مُرسلة</h3>
        <?php if (empty($recent)): ?>
            <p style="color:#aaa;text-align:center;padding:20px;font-size:14px">لا توجد إشعارات بعد</p>
        <?php else: ?>
        <table>
            <thead><tr><th>الطالب</th><th>النوع</th><th>العنوان</th><th>وقت الإرسال</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $n): ?>
            <tr>
                <td><?= htmlspecialchars($n['student_name']) ?></td>
                <td><?= $n['item_type'] ?></td>
                <td><?= htmlspecialchars(mb_substr($n['item_title'], 0, 40)) ?>...</td>
                <td><?= date('Y-m-d H:i', strtotime($n['sent_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="run_checker.php" class="btn">▶ تشغيل فحص الآن</a>
        <a href="ping.php" class="btn" style="background:#28a745" target="_blank">🏓 اختبار Ping</a>
        <a href="diagnostic.php" class="btn" style="background:#6c757d">🔄 تحديث</a>
    </div>

</div>

<script>
let secondsAgo  = <?= $lastCron ? $secondsAgo : -1 ?>;
let secondsLeft = <?= $secondsLeft ?>;
const interval  = <?= $interval * 60 ?>;
const lastCron  = <?= $lastCron ?>;

function pad(n) { return String(n).padStart(2,'0'); }

function updateDisplay() {
    if (secondsAgo >= 0) {
        secondsAgo++;
        secondsLeft = Math.max(0, secondsLeft - 1);

        // Update last check time display (live clock)
        const d = new Date((lastCron + secondsAgo) * 1000);
        document.getElementById('lastCheckTime').textContent =
            pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());

        document.getElementById('secondsAgo').textContent  = secondsAgo + ' ثانية';
        document.getElementById('secondsLeft').textContent = secondsLeft + ' ثانية';

        // Progress bar
        const pct = Math.max(5, 100 - (secondsLeft / interval * 100));
        document.getElementById('progressBar').style.width = pct + '%';

        // Status
        if (secondsAgo < 120) {
            document.getElementById('cronStatus').textContent = 'يعمل ✓';
            document.getElementById('cronStatus').className = 'badge ok';
        } else {
            document.getElementById('cronStatus').textContent = 'متأخر ⚠';
            document.getElementById('cronStatus').className = 'badge warn';
        }

        // Auto reload when next check is due
        if (secondsLeft <= 0) {
            setTimeout(() => location.reload(), 3000);
        }
    }
}

setInterval(updateDisplay, 1000);
</script>

</body>
</html>
