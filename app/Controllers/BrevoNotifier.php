<?php

declare(strict_types=1);

namespace App\Controllers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

final class BrevoNotifier
{
    /**
     * Sends an SMTP notification via Brevo when an attachment is opened.
     */
    public static function notifyAttachmentOpen(
        string $recipientEmail,
        string $docName,
        string $subject,
        string $sentTimestamp,
        int $openTimestamp
    ): void {
        $smtpKey = $_ENV['BREVO_SMTP_KEY'] ?? '';
        $adminEmail = $_ENV['APP_EMAIL'] ?? 'roger@tvserviceforhotel.com';

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp-relay.brevo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['BREVO_SMTP_LOGIN'] ?? ($_ENV['APP_EMAIL'] ?? 'sales@e-voxx.com');
            $mail->Password   = $smtpKey;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->Timeout    = 5;
            $mail->SMTPKeepAlive = false;

            $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? 'tvservicehotel@gmail.com';
            $mail->setFrom($senderEmail, 'Email Tracker');
            $mail->addAddress($adminEmail);

            $openedTime = date('Y-m-d H:i:s', $openTimestamp);
            $sentTime = is_numeric($sentTimestamp) 
                ? date('Y-m-d H:i:s', (int)$sentTimestamp) 
                : 'Unknown';

            $mail->Subject = "Attachment Opened: {$docName} (Email: {$subject})";
            $mail->isHTML(true);
            
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 20px; }
                    .email-wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e1e5e8; }
                    .header { background-color: #059669; padding: 20px; text-align: center; }
                    .header h2 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 500; letter-spacing: 0.5px; }
                    .content { padding: 30px; color: #333333; }
                    .content p { font-size: 15px; line-height: 1.6; margin-top: 0; }
                    .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    .data-table th, .data-table td { padding: 12px 15px; border-bottom: 1px solid #eeeeee; text-align: left; font-size: 14px; }
                    .data-table th { background-color: #f8f9fa; color: #666666; width: 35%; font-weight: 600; border-right: 1px solid #eeeeee; }
                    .data-table td { color: #222222; word-break: break-word; }
                </style>
            </head>
            <body>
                <div class='email-wrapper'>
                    <div class='header'>
                        <h2>📄 Attachment Tracking Alert</h2>
                    </div>
                    <div class='content'>
                        <p>Hello,</p>
                        <p>A document attached to your email has been opened.</p>
                        
                        <table class='data-table'>
                            <tr><th>Document</th><td><strong>{$docName}</strong></td></tr>
                            <tr><th>Email Subject</th><td>{$subject}</td></tr>
                            <tr><th>Recipient</th><td>{$recipientEmail}</td></tr>
                            <tr><th>Sent At</th><td>{$sentTime}</td></tr>
                            <tr><th>Opened At</th><td>{$openedTime}</td></tr>
                        </table>
                    </div>
                </div>
            </body>
            </html>";

            $mail->AltBody = "Attachment Tracking Alert\n\n"
                           . "Document: {$docName}\n"
                           . "Email Subject: {$subject}\n"
                           . "Recipient: {$recipientEmail}\n"
                           . "Sent At: {$sentTime}\n"
                           . "Opened At: {$openedTime}\n";

            $mail->send();
        } catch (Exception $e) {
            error_log("Brevo SMTP Error (Attachment): {$mail->ErrorInfo}");
        }
    }

    /**
     * Sends an SMTP notification via Brevo when a callback request is submitted.
     */
    public static function notifyCallbackRequest(
        string $recipientName,
        string $callbackNumber,
        string $scheduleDate,
        string $scheduleTime,
        string $timezone,
        string $recipientEmail,
        string $notes,
        string $trackingId
    ): void {
        $smtpKey = $_ENV['BREVO_SMTP_KEY'] ?? '';
        $adminEmail = $_ENV['APP_EMAIL'] ?? 'roger@tvserviceforhotel.com';

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp-relay.brevo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['BREVO_SMTP_LOGIN'] ?? ($_ENV['APP_EMAIL'] ?? 'sales@e-voxx.com');
            $mail->Password   = $smtpKey;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->Timeout    = 5;
            $mail->SMTPKeepAlive = false;

            $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? 'tvservicehotel@gmail.com';
            $mail->setFrom($senderEmail, 'Callback Tracker');
            $mail->addAddress($adminEmail);

            $mail->Subject = "📞 Callback Request: {$recipientName} — {$scheduleDate} {$scheduleTime} {$timezone}";
            $mail->isHTML(true);

            $notesHtml = $notes ? "<tr><th>Notes</th><td>{$notes}</td></tr>" : '';
            $tidHtml = $trackingId ? "<tr><th>Tracking ID</th><td>{$trackingId}</td></tr>" : '';

            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 20px; }
                    .email-wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e1e5e8; }
                    .header { background-color: #7c3aed; padding: 20px; text-align: center; }
                    .header h2 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 500; }
                    .content { padding: 30px; color: #333333; }
                    .content p { font-size: 15px; line-height: 1.6; margin-top: 0; }
                    .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    .data-table th, .data-table td { padding: 12px 15px; border-bottom: 1px solid #eeeeee; text-align: left; font-size: 14px; }
                    .data-table th { background-color: #f8f9fa; color: #666666; width: 35%; font-weight: 600; border-right: 1px solid #eeeeee; }
                    .data-table td { color: #222222; word-break: break-word; }
                    .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #888888; border-top: 1px solid #e1e5e8; }
                </style>
            </head>
            <body>
                <div class='email-wrapper'>
                    <div class='header'>
                        <h2>📞 New Callback Request</h2>
                    </div>
                    <div class='content'>
                        <p>A recipient has requested a callback. Details below:</p>
                        <table class='data-table'>
                            <tr><th>Name</th><td><strong>{$recipientName}</strong></td></tr>
                            <tr><th>Phone</th><td><strong>{$callbackNumber}</strong></td></tr>
                            <tr><th>Email</th><td>{$recipientEmail}</td></tr>
                            <tr><th>Schedule</th><td>{$scheduleDate} at {$scheduleTime} <strong>{$timezone}</strong></td></tr>
                            {$notesHtml}
                            {$tidHtml}
                        </table>
                    </div>
                    <div class='footer'>
                        Automated alert from Proposal Studio — Callback Tracker
                    </div>
                </div>
            </body>
            </html>";

            $mail->AltBody = "New Callback Request\n\n"
                           . "Name: {$recipientName}\n"
                           . "Phone: {$callbackNumber}\n"
                           . "Email: {$recipientEmail}\n"
                           . "Schedule: {$scheduleDate} at {$scheduleTime} {$timezone}\n"
                           . ($notes ? "Notes: {$notes}\n" : '')
                           . ($trackingId ? "Tracking ID: {$trackingId}\n" : '');

            $mail->send();
        } catch (Exception $e) {
            error_log("Brevo SMTP Error (Callback): {$mail->ErrorInfo}");
        }
    }

    /**
     * Sends an SMTP notification via Brevo when an email is opened.
     */
    public static function notifyOpen(
        string $recipientEmail,
        string $subject,
        string $sentTimestamp,
        int $openTimestamp
    ): void {
        $smtpKey = $_ENV['BREVO_SMTP_KEY'] ?? '';
        $adminEmail = $_ENV['APP_EMAIL'] ?? 'roger@tvserviceforhotel.com';

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp-relay.brevo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['BREVO_SMTP_LOGIN'] ?? ($_ENV['APP_EMAIL'] ?? 'sales@e-voxx.com');
            $mail->Password   = $smtpKey;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->Timeout    = 5;
            $mail->SMTPKeepAlive = false;

            $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? 'tvservicehotel@gmail.com';
            $mail->setFrom($senderEmail, 'Email Tracker');
            $mail->addAddress($adminEmail);

            $openedTime = date('Y-m-d H:i:s', $openTimestamp);
            $sentTime = is_numeric($sentTimestamp) 
                ? date('Y-m-d H:i:s', (int)$sentTimestamp) 
                : 'Unknown';

            $mail->Subject = "Email Opened: {$subject}";
            $mail->isHTML(true);

            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 20px; }
                    .email-wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e1e5e8; }
                    .header { background-color: #0056b3; padding: 20px; text-align: center; }
                    .header h2 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 500; letter-spacing: 0.5px; }
                    .content { padding: 30px; color: #333333; }
                    .content p { font-size: 15px; line-height: 1.6; margin-top: 0; }
                    .data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    .data-table th, .data-table td { padding: 12px 15px; border-bottom: 1px solid #eeeeee; text-align: left; font-size: 14px; }
                    .data-table th { background-color: #f8f9fa; color: #666666; width: 35%; font-weight: 600; border-right: 1px solid #eeeeee; }
                    .data-table td { color: #222222; word-break: break-word; }
                    .data-table tr:last-child th, .data-table tr:last-child td { border-bottom: none; }
                    .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #888888; border-top: 1px solid #e1e5e8; }
                </style>
            </head>
            <body>
                <div class='email-wrapper'>
                    <div class='header'>
                        <h2>📬 Email Tracking Alert</h2>
                    </div>
                    <div class='content'>
                        <p>Hello,</p>
                        <p>An email you sent has just been opened. Here are the tracking details:</p>
                        
                        <table class='data-table'>
                            <tr>
                                <th>Email Subject</th>
                                <td><strong>{$subject}</strong></td>
                            </tr>
                            <tr>
                                <th>Recipient</th>
                                <td><strong>{$recipientEmail}</strong></td>
                            </tr>
                            <tr>
                                <th>Sent At</th>
                                <td>{$sentTime}</td>
                            </tr>
                            <tr>
                                <th>Opened At</th>
                                <td>{$openedTime}</td>
                            </tr>
                        </table>
                    </div>
                    <div class='footer'>
                        This is an automated alert generated by your Email Tracker.
                    </div>
                </div>
            </body>
            </html>";

            $mail->AltBody = "Tracking Alert: An email you sent has been opened.\n\n"
                           . "Email Subject: {$subject}\n"
                           . "Recipient: {$recipientEmail}\n"
                           . "Sent At: {$sentTime}\n"
                           . "Opened At: {$openedTime}\n";

            $mail->send();
        } catch (Exception $e) {
            error_log("Brevo SMTP Error: {$mail->ErrorInfo}");
        }
    }
}
