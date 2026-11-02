<?php

declare(strict_types=1);

// ── Autoloader ────────────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $base = dirname(__DIR__);
    $file = $base . '/' . str_replace(['App\\', '\\'], ['app/', '/'], $class) . '.php';
    if (file_exists($file)) require $file;
});

// ── PHPMailer (lazy-loaded) ────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'PHPMailer\\PHPMailer\\')) {
        $file = dirname(__DIR__) . '/vendor/PHPMailer/src/' . substr($class, strrpos($class, '\\') + 1) . '.php';
        if (file_exists($file)) require $file;
    }
});

use App\Config\Env;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Env
try {
    Env::load(dirname(__DIR__) . '/.env');
} catch (\Throwable $e) {
    die("Failed to load environment variables: " . $e->getMessage());
}

$smtpKey = $_ENV['BREVO_SMTP_KEY'] ?? '';
$adminEmail = $_ENV['APP_EMAIL'] ?? 'sales@e-voxx.com';
$smtpLogin = $_ENV['BREVO_SMTP_LOGIN'] ?? $adminEmail;

echo "<h1>Brevo SMTP Test</h1>";
echo "<p><strong>Target Email / Sender (APP_EMAIL):</strong> " . htmlspecialchars($adminEmail) . "</p>";
echo "<p><strong>SMTP Login Username:</strong> " . htmlspecialchars($smtpLogin) . "</p>";
echo "<p><strong>SMTP Key configured:</strong> " . ($smtpKey ? 'Yes' : 'No') . "</p>";
echo "<hr/>";

$mail = new PHPMailer(true);

try {
    // Enable verbose debug output
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        echo nl2br(htmlspecialchars("Debug level $level; message: $str\n"));
    };

    $mail->isSMTP();
    $mail->Host       = 'smtp-relay.brevo.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpLogin;
    $mail->Password   = $smtpKey;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->Timeout    = 10;
    $mail->SMTPKeepAlive = false;

    // Sender
    $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? 'tvservicehotel@gmail.com';
    $mail->setFrom($senderEmail, 'Brevo Test Script');
    // Recipient
    $mail->addAddress($adminEmail);

    $mail->Subject = 'Brevo SMTP Test Email';
    $mail->isHTML(true);
    $mail->Body    = '<b>Success!</b> Your Brevo SMTP settings are working correctly.';
    $mail->AltBody = 'Success! Your Brevo SMTP settings are working correctly.';

    echo "<h3>Attempting to send...</h3>";
    echo "<div style='background:#f4f4f4; padding:10px; border:1px solid #ccc; font-family:monospace;'>";
    $mail->send();
    echo "</div>";

    echo "<h2 style='color:green;'>✅ Email sent successfully!</h2>";

} catch (Exception $e) {
    echo "</div>";
    echo "<h2 style='color:red;'>❌ Failed to send email</h2>";
    echo "<strong>Mailer Error:</strong> " . htmlspecialchars($mail->ErrorInfo) . "<br/>";
    echo "<strong>Exception:</strong> " . htmlspecialchars($e->getMessage());
}
