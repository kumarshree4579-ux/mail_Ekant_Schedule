<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

/**
 * PdfViewerController — tracking endpoint only.
 * Documents are served via /Viewer/index.php (image-based renderer).
 */
final class PdfViewerController
{
    /**
     * POST /pdf-viewer/track
     * Accepts open/closed/active/inactive events from Viewer/index.php
     * and records them in the attachment_opens table.
     */
    public static function track(): void
    {
        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $token   = trim((string)($payload['token'] ?? ''));

        if ($token === '') {
            http_response_code(400);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'message' => 'Token required']);
            exit;
        }

        // ── Send response immediately, do heavy work after ────────────────────
        self::closeConnectionWithJson(['ok' => true]);

        // ── Background: DB + SMTP work ────────────────────────────────────────
        $db   = Database::get();
        $link = $db->prepare('
            SELECT p.tracking_id, p.send_id, p.name, s.sent_at, s.recipients, s.subject
            FROM pdf_link_tokens p
            LEFT JOIN email_sends s ON p.send_id = s.id
            WHERE p.token = ? LIMIT 1
        ');
        $link->execute([$token]);
        $row = $link->fetch() ?: [];

        if (empty($row)) {
            $attLink = $db->prepare('
                SELECT l.tracking_id, l.send_id, l.filename as name, s.sent_at, s.recipients, s.subject
                FROM attachment_links l
                LEFT JOIN email_sends s ON l.send_id = s.id
                WHERE l.token = ? LIMIT 1
            ');
            $attLink->execute([$token]);
            $row = $attLink->fetch() ?: [];
        }

        $recipient = trim((string)($payload['recipient_email'] ?? ''));
        if ($recipient === '' && !empty($row['recipients'])) {
            $recipient = $row['recipients'];
        }
        // Skip internal/self opens
        if ($recipient !== '' && in_array($recipient, [(string)APP_EMAIL, (string)ADMIN_EMAIL], true)) {
            return;
        }

        $eventType = $payload['event_type'] ?? 'open';
        $now = time();

        try {
            $db->prepare(
                'INSERT INTO attachment_opens
                 (token, tracking_id, send_id, recipient_email, event_type, page, duration_ms, percent, meta, opened_at, ip, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $token,
                $row['tracking_id'] ?? null,
                $row['send_id']     ?? null,
                $recipient ?: null,
                $eventType,
                isset($payload['page'])        ? (int)$payload['page']        : null,
                isset($payload['duration_ms']) ? (int)$payload['duration_ms'] : null,
                isset($payload['percent'])     ? (int)$payload['percent']     : null,
                json_encode($payload),
                $now,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (\Throwable) {}

        // Notify on document open (only initial open event with a known recipient)
        if ($eventType === 'open' && $recipient !== '') {
            $docName = !empty($row['name']) ? $row['name'] : 'Document';
            if (!empty($row['tracking_id'])) {
                try {
                    NotificationController::queueNotification(
                        $row['tracking_id'],
                        $recipient,
                        '📎 Attachment: ' . $docName . ' (' . ($row['subject'] ?? '') . ')',
                        $now,
                        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                    );
                } catch (\Throwable) {}
            }

            try {
                BrevoNotifier::notifyAttachmentOpen(
                    $recipient,
                    $docName,
                    (string)($row['subject'] ?? 'Unknown Subject'),
                    (string)($row['sent_at'] ?? 'Unknown'),
                    $now
                );
            } catch (\Throwable) {}
        }
    }

    /**
     * Send JSON response and close the HTTP connection.
     * PHP continues executing in the background for DB/SMTP work.
     */
    private static function closeConnectionWithJson(array $data): void
    {
        ignore_user_abort(true);
        set_time_limit(30);

        $json = json_encode($data);

        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Length: ' . strlen($json));
        header('Connection: close');

        // Kill all output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        ob_start();
        echo $json;
        ob_end_flush();
        flush();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
            return;
        }
    }
}
