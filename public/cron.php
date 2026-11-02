<?php

declare(strict_types=1);

// Boot the application
$base = dirname(__DIR__);
require $base . '/app/Config/Env.php';
require $base . '/app/Core/Database.php';
require $base . '/app/Core/GmailApi.php';

// PHPMailer (lazy-loaded)
spl_autoload_register(function (string $class) use ($base): void {
    if (str_starts_with($class, 'PHPMailer\\PHPMailer\\')) {
        $file = $base . '/vendor/PHPMailer/src/' . substr($class, strrpos($class, '\\') + 1) . '.php';
        if (file_exists($file)) require $file;
    }
});
spl_autoload_register(function (string $class) use ($base): void {
    $file = $base . '/' . str_replace(['App\\', '\\'], ['app/', '/'], $class) . '.php';
    if (file_exists($file)) require $file;
});

use App\Config\Env;
use App\Core\Database;
use App\Core\GmailApi;
use App\Core\SchedulerConfig;
use PHPMailer\PHPMailer\PHPMailer;

Env::load($base . '/.env');
date_default_timezone_set('Asia/Kolkata');

$db = Database::get();

// Load scheduler settings from the shared application database.
$config = SchedulerConfig::load($db, $base);

$time48 = $config['scheduler_48h_time'] ?? '10:00';
$time72 = $config['scheduler_72h_time'] ?? '14:30';
$lastRun48 = $config['last_run_48h'] ?? '';
$lastRun72 = $config['last_run_72h'] ?? '';

$currentDate = date('Y-m-d');
$currentTime = date('H:i');
$now = time();

echo "Running Cron... Current Time: $currentTime (Date: $currentDate)\n";

// Function to process resends
function processScheduler(string $type, \PDO $db, string $newSubjectPrefix, string $replaceSubjectPrefix = ''): void {
    global $now;
    global $base;
    
    if ($type === '48h') {
        // 48h to 72h ago
        $minTime = $now - (72 * 3600);
        $maxTime = $now - (48 * 3600);
        // Subject NOT starting with FW
        $subjectCondition = "AND subject NOT LIKE 'FW%' AND subject NOT LIKE 'FWD%'";
    } else {
        // 72h to 96h ago
        $minTime = $now - (96 * 3600);
        $maxTime = $now - (72 * 3600);
        // Subject starts with FW: or FW:-
        $subjectCondition = "AND (subject LIKE 'FW:%' OR subject LIKE 'FW:-%')";
    }

    $sql = "
        SELECT s.* 
        FROM email_sends s
        LEFT JOIN email_opens o ON s.tracking_id = o.tracking_id
        WHERE s.is_draft = 0 
          AND s.is_bounced = 0
          AND o.id IS NULL 
          AND s.sent_at > ? 
          AND s.sent_at <= ?
          $subjectCondition
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$minTime, $maxTime]);
    $emails = $stmt->fetchAll();
    
    echo "Found " . count($emails) . " emails for $type scheduler.\n";

    foreach ($emails as $email) {
        // Prepare new subject
        $oldSubject = $email['subject'];
        $newSubject = $oldSubject;
        if ($replaceSubjectPrefix && str_starts_with($oldSubject, $replaceSubjectPrefix)) {
            $newSubject = $newSubjectPrefix . substr($oldSubject, strlen($replaceSubjectPrefix));
        } else {
            $newSubject = $newSubjectPrefix . $oldSubject;
        }

        // Generate new tracking ID
        $newTrackingId = bin2hex(random_bytes(16));
        
        // Update body tracking pixel and links
        $oldTrackingId = $email['tracking_id'];
        // Replace old tracking ID where it explicitly exists (like tid=)
        $newBody = str_replace('tid=' . $oldTrackingId, 'tid=' . $newTrackingId, $email['body']);
        
        // Also replace literal tracking ID if it was used raw
        $newBody = str_replace($oldTrackingId, $newTrackingId, $newBody);

        // If the body still has {{TRACKING_ID}} (from raw save), replace it
        $newBody = str_replace('{{TRACKING_ID}}', $newTrackingId, $newBody);

        // Insert new record
        $insertStmt = $db->prepare("
            INSERT INTO email_sends (tracking_id, recipients, recipient_email, subject, sent_at, is_draft, body, studio_fields, custom_id)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)
        ");
        $insertStmt->execute([
            $newTrackingId,
            $email['recipients'],
            $email['recipient_email'],
            $newSubject,
            $now,
            $newBody,
            $email['studio_fields'],
            $email['custom_id']
        ]);
        $newSendId = (int)$db->lastInsertId();

        // Duplicate PDF tokens or parse raw ones
        $tokensStmt = $db->prepare("SELECT * FROM pdf_link_tokens WHERE tracking_id = ?");
        $tokensStmt->execute([$oldTrackingId]);
        $tokens = $tokensStmt->fetchAll();

        if (str_contains($newBody, 'data-pdf-url=')) {
            $newBody = preg_replace_callback(
                '/<a([^>]+data-pdf-url=["\']([^"\']+)["\'][^>]*)>/i',
                function (array $m) use ($db, $now, $newTrackingId, $newSendId): string {
                    $attrs   = $m[1];
                    $pdfUrl  = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $name = '';
                    if (preg_match('/data-pdf-name=["\']([^"\']*)["\']/', $attrs, $nm)) {
                        $name = html_entity_decode($nm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    $newTokenStr = bin2hex(random_bytes(24));
                    $db->prepare("INSERT INTO pdf_link_tokens (token, url, name, created_at, tracking_id, send_id) VALUES (?, ?, ?, ?, ?, ?)")
                       ->execute([$newTokenStr, $pdfUrl, $name ?: 'Document', $now, $newTrackingId, $newSendId]);
                    
                    $appUrl = defined('APP_URL') ? APP_URL : ($_ENV['APP_URL'] ?? '');
                    $viewerBase = defined('VIEWER_URL') ? rtrim((string)VIEWER_URL, '/') . '/' : $appUrl . '/Viewer/';
                    $viewerHref = $viewerBase . 'index.php?url=' . urlencode($pdfUrl) . '&token=' . urlencode($newTokenStr);
                    
                    $attrs = preg_replace('/\s*href=["\'"][^"\']*["\']/', '', $attrs);
                    $attrs = preg_replace('/\s*data-pdf-url=["\'"][^"\']*["\']/', '', $attrs);
                    $attrs = preg_replace('/\s*data-pdf-name=["\'"][^"\']*["\']/', '', $attrs);
                    return '<a href="' . htmlspecialchars($viewerHref, ENT_QUOTES) . '"' . $attrs . '>';
                },
                $newBody
            );
        } else {
            foreach ($tokens as $token) {
                // Re-mint token for the new send
                $newTokenStr = bin2hex(random_bytes(24));
                $db->prepare("INSERT INTO pdf_link_tokens (token, url, name, created_at, tracking_id, send_id) VALUES (?, ?, ?, ?, ?, ?)")
                   ->execute([$newTokenStr, $token['url'], $token['name'], $now, $newTrackingId, $newSendId]);
                // Replace old token in the body with the new token
                $newBody = str_replace($token['token'], $newTokenStr, $newBody);
            }
        }

        // Ensure tracking pixel exists
        if (!str_contains($newBody, '/track?tid=')) {
            $appUrl = defined('APP_URL') ? APP_URL : ($_ENV['APP_URL'] ?? '');
            $pixelUrl = $appUrl . '/track?tid=' . urlencode($newTrackingId) . '&to=' . urlencode($email['recipient_email']);
            $pixel = '<img src="' . $pixelUrl . '" width="1" height="1" style="display:block;width:1px;height:1px;border:0;mso-hide:all;" alt="">';
            
            if (stripos($newBody, '</body>') !== false) {
                $newBody = str_ireplace('</body>', $pixel . '</body>', $newBody);
            } else {
                $newBody .= $pixel;
            }
        }

        // Always update body since we may have modified it heavily
        $db->prepare("UPDATE email_sends SET body = ? WHERE id = ?")->execute([$newBody, $newSendId]);

        // Send Email
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = APP_EMAIL;
            $mail->Password   = APP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom(APP_EMAIL, 'Roger');
            
            $recips = array_map('trim', explode(',', $email['recipients']));
            foreach ($recips as $rec) {
                if (filter_var($rec, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($rec);
                }
            }
            
            $mail->isHTML(true);
            $mail->Subject = $newSubject;
            $mail->Body = $newBody;
            
            // Re-inline images if any (since the body might reference local paths)
            $cidIndex = 0;
            $mail->Body = preg_replace_callback('/<img([^>]+)>/i', function ($m) use ($mail, &$cidIndex, $base) {
                $tag = $m[1];
                if (!preg_match('/src=["\']([^"\']*)["\']/', $tag, $srcMatch)) return $m[0];
                $src = $srcMatch[1];
                if (str_contains($src, '/track?tid=')) return $m[0];
                if (str_starts_with($src, 'data:')) return $m[0];
                if (!filter_var($src, FILTER_VALIDATE_URL)) {
                    $local = $base . '/' . ltrim($src, '/');
                    if (file_exists($local)) {
                        $cid = 'img_cid_' . ($cidIndex++) . '_' . time();
                        $mail->addEmbeddedImage($local, $cid);
                        return '<img' . preg_replace('/src=["\'][^"\']*["\']/', 'src="cid:' . $cid . '"', $tag) . '>';
                    }
                }
                return $m[0];
            }, $mail->Body);
            
            // Try Gmail API first if possible, else SMTP
            $sentViaApi = false;
            if (defined('GMAIL_CLIENT_ID') && GMAIL_CLIENT_ID) {
                $mail->preSend();
                $mime = $mail->getSentMIMEMessage();
                try {
                    GmailApi::sendRawEmail($mime);
                    $sentViaApi = true;
                } catch (\Exception $e) {
                    // fallback
                }
            }
            
            if (!$sentViaApi) {
                $mail->send();
            }
            
            echo "Sent email to {$email['recipients']} (New Subject: $newSubject)\n";

        } catch (\Exception $e) {
            echo "Failed to send email to {$email['recipients']}: " . $e->getMessage() . "\n";
        }
    }
}

// Check 48h Scheduler
if ($lastRun48 !== $currentDate && $currentTime >= $time48) {
    echo "Executing 48h scheduler...\n";
    processScheduler('48h', $db, 'FW:- ', '');
    SchedulerConfig::markRun($db, '48h', $currentDate);
}

// Check 72h Scheduler
if ($lastRun72 !== $currentDate && $currentTime >= $time72) {
    echo "Executing 72h scheduler...\n";
    processScheduler('72h', $db, 'FWD:- ', 'FW:- ');
    SchedulerConfig::markRun($db, '72h', $currentDate);
}

echo "Done.\n";
