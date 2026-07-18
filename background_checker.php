<?php require_once 'auth.php'; authAdmin(); ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تفعيل الفحص التلقائي</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #0073aa, #005580); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.card { background: #fff; border-radius: 16px; padding: 35px; width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,.3); text-align: center; }
.icon { font-size: 56px; margin-bottom: 15px; }
h2 { color: #0073aa; font-size: 20px; margin-bottom: 8px; }
p { color: #666; font-size: 14px; line-height: 1.8; margin-bottom: 15px; }
.status { padding: 12px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; margin: 15px 0; }
.status.ok    { background: #d4edda; color: #155724; }
.status.warn  { background: #fff3cd; color: #856404; }
.status.error { background: #f8d7da; color: #721c24; }
.btn { display: inline-block; padding: 12px 28px; background: #0073aa; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; margin: 5px; }
.btn-red { background: #dc3545; }
.btn-gray { background: #6c757d; }
.log { background: #f8f9fa; border-radius: 8px; padding: 12px; max-height: 150px; overflow-y: auto; text-align: right; margin-top: 15px; }
.log-item { font-size: 12px; padding: 3px 0; color: #555; border-bottom: 1px solid #eee; }
.log-item:last-child { border: none; }
.log-item.new { color: #28a745; font-weight: 600; }
</style>
</head>
<body>
<div class="card">
    <div class="icon">⚙️</div>
    <h2>الفحص التلقائي في الخلفية</h2>
    <p>بعد التفعيل، سيعمل الفحص تلقائياً كل دقيقة حتى بعد إغلاق هذه الصفحة — طالما المتصفح مفتوح على أي تبويب.</p>

    <div id="status" class="status warn">⏳ جاري التحقق...</div>

    <div id="btns">
        <button class="btn" onclick="activate()">✅ تفعيل الفحص التلقائي</button>
        <button class="btn btn-red" onclick="deactivate()">⏹ إيقاف</button>
    </div>

    <div class="log" id="log"><div class="log-item">في انتظار التفعيل...</div></div>

    <div style="margin-top:15px">
        <a href="admin.php" class="btn btn-gray">← الإدارة</a>
    </div>
</div>

<script>
const API = 'checker_api.php?key=moodle_tracker_2024_secret';
let worker = null;

function addLog(msg, type = '') {
    const log = document.getElementById('log');
    const d = document.createElement('div');
    d.className = 'log-item ' + type;
    d.textContent = '[' + new Date().toLocaleTimeString('ar') + '] ' + msg;
    log.insertBefore(d, log.firstChild);
    if (log.children.length > 20) log.removeChild(log.lastChild);
}

async function runCheck() {
    try {
        const res  = await fetch(API);
        const data = await res.json();
        if (data.total_new > 0) {
            addLog('🆕 تم إرسال ' + data.total_new + ' إشعار جديد', 'new');
        } else {
            addLog('✓ لا يوجد محتوى جديد');
        }
    } catch(e) {
        addLog('✗ خطأ: ' + e.message);
    }
}

function activate() {
    if (worker) clearInterval(worker);

    // Run immediately
    runCheck();

    // Then every 60 seconds
    worker = setInterval(runCheck, 60000);

    // Register service worker for background
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').then(() => {
            addLog('✓ Service Worker مُفعَّل — يعمل في الخلفية');
        }).catch(() => {
            addLog('⚠ Service Worker غير متاح — يعمل فقط مع المتصفح المفتوح');
        });
    }

    document.getElementById('status').className = 'status ok';
    document.getElementById('status').textContent = '✅ الفحص التلقائي مُفعَّل — كل دقيقة';
    localStorage.setItem('checker_active', '1');
    addLog('✓ تم تفعيل الفحص التلقائي');
}

function deactivate() {
    if (worker) clearInterval(worker);
    worker = null;
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(regs => regs.forEach(r => r.unregister()));
    }
    document.getElementById('status').className = 'status warn';
    document.getElementById('status').textContent = '⏸ الفحص التلقائي متوقف';
    localStorage.removeItem('checker_active');
    addLog('⏹ تم إيقاف الفحص التلقائي');
}

// Auto-activate if was active before
if (localStorage.getItem('checker_active')) {
    activate();
} else {
    document.getElementById('status').className = 'status warn';
    document.getElementById('status').textContent = '⏸ غير مُفعَّل — اضغط تفعيل';
}
</script>
</body>
</html>
