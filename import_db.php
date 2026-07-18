<?php
require_once 'auth.php';
authAdmin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = ['type' => 'error', 'text' => 'فشل رفع الملف'];
    } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'sql') {
        $message = ['type' => 'error', 'text' => 'يجب أن يكون الملف بصيغة .sql'];
    } else {
        $sql = file_get_contents($file['tmp_name']);
        if (empty($sql)) {
            $message = ['type' => 'error', 'text' => 'الملف فارغ'];
        } else {
            try {
                $pdo = getDB();
                // Split SQL into individual statements
                $statements = array_filter(
                    array_map('trim', explode(";\n", $sql)),
                    fn($s) => !empty($s) && !str_starts_with($s, '--')
                );

                $count = 0;
                $pdo->beginTransaction();
                foreach ($statements as $stmt) {
                    if (empty(trim($stmt))) continue;
                    $pdo->exec($stmt);
                    $count++;
                }
                $pdo->commit();
                $message = ['type' => 'success', 'text' => "✓ تم استيراد قاعدة البيانات بنجاح — تم تنفيذ $count أمر SQL"];
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = ['type' => 'error', 'text' => 'خطأ: ' . $e->getMessage()];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>استيراد قاعدة البيانات - Moodle Tracker</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
.header { background: #0073aa; color: #fff; padding: 20px 30px; display: flex; align-items: center; gap: 15px; }
.header h1 { font-size: 20px; }
.container { max-width: 700px; margin: 30px auto; padding: 0 20px; }
.card { background: #fff; border-radius: 10px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 20px; }
.card h2 { font-size: 18px; color: #0073aa; margin-bottom: 20px; border-bottom: 2px solid #e8f4fd; padding-bottom: 10px; }
.alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.upload-area { border: 2px dashed #0073aa; border-radius: 10px; padding: 40px; text-align: center; cursor: pointer; transition: background .2s; margin-bottom: 20px; }
.upload-area:hover { background: #e8f4fd; }
.upload-area input[type=file] { display: none; }
.upload-area .icon { font-size: 48px; margin-bottom: 10px; }
.upload-area p { color: #666; font-size: 14px; }
.upload-area .filename { color: #0073aa; font-weight: 600; margin-top: 8px; font-size: 14px; }
.btn { display: inline-block; padding: 11px 28px; background: #0073aa; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; }
.btn:hover { opacity: .85; }
.btn-secondary { background: #6c757d; }
.warning-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; font-size: 13px; color: #856404; line-height: 1.8; }
</style>
</head>
<body>

<div class="header">
    <span style="font-size:26px">📥</span>
    <div>
        <h1>استيراد قاعدة البيانات</h1>
        <small>رفع ملف SQL لاستعادة البيانات</small>
    </div>
</div>

<div class="container">

    <?php if ($message): ?>
    <div class="alert alert-<?= $message['type'] ?>"><?= $message['text'] ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>📤 رفع ملف SQL</h2>

        <div class="warning-box" style="margin-bottom:20px">
            ⚠️ <strong>تحذير:</strong> سيؤدي الاستيراد إلى استبدال البيانات الحالية بالبيانات الموجودة في الملف.<br>
            تأكد من أخذ نسخة احتياطية قبل الاستيراد.
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="upload-area" onclick="document.getElementById('sqlFile').click()">
                <input type="file" id="sqlFile" name="sql_file" accept=".sql" onchange="showFileName(this)">
                <div class="icon">📁</div>
                <p>اضغط لاختيار ملف SQL</p>
                <p class="filename" id="fileName">لم يتم اختيار ملف</p>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn" onclick="return confirm('هل أنت متأكد من استيراد قاعدة البيانات؟ سيتم استبدال البيانات الحالية.')">
                    📥 استيراد
                </button>
                <a href="admin.php" class="btn btn-secondary">← رجوع</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>📋 تعليمات</h2>
        <ol style="padding-right:20px;line-height:2.2;font-size:14px;color:#555">
            <li>اذهب لـ <strong>💾 تصدير قاعدة البيانات</strong> لتنزيل نسخة احتياطية أولاً</li>
            <li>ارفع ملف الـ <code>.sql</code> المُصدَّر من هذا النظام</li>
            <li>اضغط <strong>استيراد</strong> وانتظر رسالة النجاح</li>
        </ol>
    </div>

</div>

<script>
function showFileName(input) {
    document.getElementById('fileName').textContent = input.files[0]?.name || 'لم يتم اختيار ملف';
}
</script>
</body>
</html>
