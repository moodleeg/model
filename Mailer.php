<?php
require_once 'config.php';

function sendEmail($to, $toName, $subject, $body) {
    $host    = SMTP_HOST;
    $port    = SMTP_PORT;
    $user    = SMTP_USER;
    $pass    = SMTP_PASS;
    $fromName = SMTP_FROM_NAME;

    // Connect to SMTP
    $socket = fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) {
        error_log("SMTP connect failed: $errstr ($errno)");
        return false;
    }

    stream_set_timeout($socket, 10);

    $read = fgets($socket, 515);
    if (substr($read, 0, 3) !== '220') { fclose($socket); return false; }

    // EHLO
    fwrite($socket, "EHLO localhost\r\n");
    while ($line = fgets($socket, 515)) {
        if (substr($line, 3, 1) === ' ') break;
    }

    // STARTTLS
    fwrite($socket, "STARTTLS\r\n");
    $read = fgets($socket, 515);
    if (substr($read, 0, 3) !== '220') { fclose($socket); return false; }

    // Upgrade to TLS
    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

    // EHLO again after TLS
    fwrite($socket, "EHLO localhost\r\n");
    while ($line = fgets($socket, 515)) {
        if (substr($line, 3, 1) === ' ') break;
    }

    // AUTH LOGIN
    fwrite($socket, "AUTH LOGIN\r\n");
    fgets($socket, 515);
    fwrite($socket, base64_encode($user) . "\r\n");
    fgets($socket, 515);
    fwrite($socket, base64_encode($pass) . "\r\n");
    $auth = fgets($socket, 515);
    if (substr($auth, 0, 3) !== '235') {
        error_log("SMTP auth failed: $auth");
        fclose($socket);
        return false;
    }

    // MAIL FROM
    fwrite($socket, "MAIL FROM:<$user>\r\n");
    fgets($socket, 515);

    // RCPT TO
    fwrite($socket, "RCPT TO:<$to>\r\n");
    $rcpt = fgets($socket, 515);
    if (substr($rcpt, 0, 3) !== '250') {
        error_log("SMTP RCPT failed: $rcpt");
        fclose($socket);
        return false;
    }

    // DATA
    fwrite($socket, "DATA\r\n");
    fgets($socket, 515);

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFrom    = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $encodedTo      = '=?UTF-8?B?' . base64_encode($toName) . '?=';
    $date           = date('r');

    $message  = "Date: $date\r\n";
    $message .= "From: $encodedFrom <$user>\r\n";
    $message .= "To: $encodedTo <$to>\r\n";
    $message .= "Subject: $encodedSubject\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "\r\n";
    $message .= chunk_split(base64_encode($body));
    $message .= "\r\n.\r\n";

    fwrite($socket, $message);
    $sent = fgets($socket, 515);

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    if (substr($sent, 0, 3) !== '250') {
        error_log("SMTP send failed: $sent");
        return false;
    }

    return true;
}

function buildEmailBody($studentName, $courseName, $type, $title, $dueDate = null, $url = null) {
    $typeLabels = [
        'lecture'      => ['label' => '📚 محتوى جديد',   'color' => '#0073aa'],
        'assignment'   => ['label' => '📝 واجب جديد',    'color' => '#28a745'],
        'quiz'         => ['label' => '📋 اختبار جديد',  'color' => '#dc3545'],
        'announcement' => ['label' => '📢 إعلان جديد',   'color' => '#fd7e14'],
    ];
    $t     = $typeLabels[$type] ?? ['label' => '🔔 تحديث جديد', 'color' => '#6c757d'];
    $due   = $dueDate ? "<p><strong>الموعد النهائي:</strong> " . date('Y-m-d H:i', $dueDate) . "</p>" : '';
    $link  = $url ? "<p style='margin-top:15px'><a href='$url' style='background:{$t['color']};color:#fff;padding:10px 22px;text-decoration:none;border-radius:6px;font-size:14px'>عرض التفاصيل</a></p>" : '';

    return "
    <div dir='rtl' style='font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #ddd;border-radius:10px;overflow:hidden'>
        <div style='background:{$t['color']};color:#fff;padding:20px;text-align:center'>
            <h2 style='margin:0;font-size:20px'>{$t['label']}</h2>
        </div>
        <div style='padding:25px'>
            <p>مرحباً <strong>" . htmlspecialchars($studentName) . "</strong>،</p>
            <p>يوجد تحديث جديد في مقرر:</p>
            <p style='background:#f8f9fa;padding:10px;border-radius:6px;font-weight:bold'>" . htmlspecialchars($courseName) . "</p>
            <p><strong>العنوان:</strong> " . htmlspecialchars($title) . "</p>
            $due
            $link
        </div>
        <div style='background:#f5f5f5;padding:12px;text-align:center;font-size:12px;color:#888'>
            Moodle Tracker — بوابة التعليم الإلكتروني جامعة الأقصى
        </div>
    </div>";
}
