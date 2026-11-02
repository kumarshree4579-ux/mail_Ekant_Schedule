<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;

final class AttachmentController
{
    public static function view(): void
    {
        $token = trim((string)($_GET['token'] ?? ''));
        if ($token === '') {
            http_response_code(400);
            echo 'Missing token';
            exit;
        }

        $db = Database::get();
        $stmt = $db->prepare('SELECT token, tracking_id, send_id, url, filename FROM attachment_links WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }

        $token = $row['token'];
        $url = $row['url'];
        $filename = $row['filename'] ?? '';
        $trackingId = $row['tracking_id'] ?? '';
        $sendId = $row['send_id'] ?? null;

        require __DIR__ . '/../../public/views/attachment_viewer.php';
    }

    public static function track(): void
    {
        // Accept JSON body or form-encoded
        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $token = trim((string)($payload['token'] ?? ''));
        if ($token === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Missing token']);
            exit;
        }

        $event = $payload['event_type'] ?? 'open';
        $page = isset($payload['page']) ? (int)$payload['page'] : null;
        $duration = isset($payload['duration_ms']) ? (int)$payload['duration_ms'] : null;

        $db = Database::get();
        // Try to resolve tracking_id and send_id if available
        $link = $db->prepare('
            SELECT l.tracking_id, l.send_id, l.filename as name, s.sent_at, s.recipients, s.subject
            FROM attachment_links l
            LEFT JOIN email_sends s ON l.send_id = s.id
            WHERE l.token = ? LIMIT 1
        ');
        $link->execute([$token]);
        $linkRow = $link->fetch() ?: [];

        if (empty($linkRow)) {
            $pdfLink = $db->prepare('
                SELECT p.tracking_id, p.send_id, p.name, s.sent_at, s.recipients, s.subject
                FROM pdf_link_tokens p
                LEFT JOIN email_sends s ON p.send_id = s.id
                WHERE p.token = ? LIMIT 1
            ');
            $pdfLink->execute([$token]);
            $linkRow = $pdfLink->fetch() ?: [];
        }

        $recipient = trim((string)($payload['recipient_email'] ?? ''));
        if ($recipient === '' && !empty($linkRow['recipients'])) {
            $recipient = $linkRow['recipients'];
        }
        // Ignore internal sender/administrative opens
        if ($recipient !== '' && in_array($recipient, [APP_EMAIL, ADMIN_EMAIL], true)) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'skipped' => 'internal_recipient']);
            exit;
        }

        try {
            $db->prepare('INSERT INTO attachment_opens (token, tracking_id, send_id, recipient_email, event_type, page, duration_ms, percent, meta, opened_at, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([
                    $token,
                    $linkRow['tracking_id'] ?? null,
                    $linkRow['send_id'] ?? null,
                    $recipient,
                    $event,
                    $page,
                    $duration,
                    isset($payload['percent']) ? (int)$payload['percent'] : null,
                    json_encode($payload),
                    time(),
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]);
        } catch (\Throwable $e) {
            // swallow — don't break viewer
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => true]);
        exit;
    }

    public static function raw(): void
    {
        $token = trim((string)($_GET['token'] ?? ''));
        if ($token === '') {
            http_response_code(400);
            echo 'Missing token';
            exit;
        }

        $db = Database::get();
        $stmt = $db->prepare('SELECT url, filename FROM attachment_links WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }

        $path = $row['url'];
        $filename = $row['filename'] ?? '';
        $forcePdf = false;
        if (preg_match('#\.pdf(?:\?|$)#i', $path) || preg_match('#\.pdf(?:\?|$)#i', $filename)) {
            $forcePdf = true;
        }

        // If stored as a filesystem path (relative), resolve
        if (!preg_match('#^https?://#i', $path)) {
            $fs = dirname(__DIR__, 2) . '/' . ltrim($path, '/');
        } else {
            // If URL, proxy it
            $ch = curl_init($path);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => true,
                CURLOPT_BINARYTRANSFER => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0 Safari/537.36',
                CURLOPT_HTTPHEADER => ['Accept: application/pdf, */*;q=0.9'],
            ]);
            $data = curl_exec($ch);
            $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
            curl_close($ch);

            if ($forcePdf || stripos($ctype, 'application/pdf') !== false || stripos($filename, '.pdf') !== false) {
                $ctype = 'application/pdf';
            }
            header('Content-Type: ' . $ctype);
            header('Content-Disposition: inline; filename="' . basename($filename ?: $path) . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            echo $data;
            exit;
        }

        if (!file_exists($fs) || !is_readable($fs)) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $ctype = finfo_file($finfo, $fs) ?: 'application/octet-stream';
        finfo_close($finfo);

        if ($forcePdf || stripos($ctype, 'application/pdf') !== false) {
            $ctype = 'application/pdf';
        }
        header('Content-Type: ' . $ctype);
        header('Content-Disposition: inline; filename="' . basename($filename ?: $fs) . '"');
        header('Content-Length: ' . filesize($fs));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($fs);
        exit;
    }
}
