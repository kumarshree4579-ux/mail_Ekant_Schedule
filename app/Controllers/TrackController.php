<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Controllers\BrevoNotifier;
use App\Controllers\NotificationController;

final class TrackController
{
    // Your office/VPN egress IPs — self-opens from your desk will match these
    private const INTERNAL_IPS = [
        '115.246.109.187', '103.188.127.180'
    ];

    // Ignore opens within N seconds of send — catches sentbox preview on send
    private const GRACE_PERIOD_SECONDS = 120;

    // If the same tid fires more than this many times within DEDUP_WINDOW_SECONDS
    // from the same IP, treat extras as internal (catches repeated sentbox loads)
    private const DEDUP_WINDOW_SECONDS = 45;
    private const DEDUP_MAX_REAL       = 1;

    public static function pixel(): void
    {
        $tid = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($_GET['tid'] ?? ''));
        $ip = self::ip();
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $openTimestamp = time();

        // ── STEP 1: Emit the pixel and CLOSE the HTTP connection immediately ──
        // The client (email client/browser) gets the image in <5ms.
        // Everything below runs in the background AFTER the response is sent.
        self::closeConnectionWithPixel();

        if ($tid === '') {
            return;
        }

        // ── STEP 2: DB work (fast — local SQLite) ─────────────────────────────
        $stmt = Database::get()->prepare(
            'SELECT recipient_email, sent_at, subject FROM email_sends WHERE tracking_id = ? LIMIT 1'
        );
        $stmt->execute([$tid]);
        $send = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$send) {
            return;
        }

        $recipientEmail = strtolower(trim($send['recipient_email']));
        $sentTs = (int)$send['sent_at'];
        $subject = (string)$send['subject'];

        $internalEmails = [
            strtolower(trim((string) APP_EMAIL)),
            strtolower(trim((string) ADMIN_EMAIL)),
        ];

        $isSelfEmail = in_array($recipientEmail, $internalEmails, true);
        $isSelfIp    = in_array($ip, self::INTERNAL_IPS, true);
        $isTooSoon   = $sentTs > 0 && ($openTimestamp - $sentTs) < self::GRACE_PERIOD_SECONDS;
        $isDuplicate = self::isDuplicateOpen($tid, $ip, $openTimestamp);

        $isInternal = ($isSelfEmail || $isSelfIp || $isTooSoon || $isDuplicate) ? 1 : 0;

        try {
            Database::get()->prepare(
                'INSERT INTO email_opens (tracking_id, recipient_email, is_internal, opened_at, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$tid, $recipientEmail, $isInternal, $openTimestamp, $ip, $userAgent]);
        } catch (\Throwable) {}

        // ── STEP 3: Brevo notification (slow — external SMTP) ─────────────────
        if ($isInternal === 0 && $recipientEmail !== '') {
            // Queue SSE notification (fast, local DB insert)
            NotificationController::queueNotification($tid, $recipientEmail, $subject, $openTimestamp, $ip);
            
            // Send Brevo email notification (this is the slow part, but connection is already closed)
            try {
                BrevoNotifier::notifyOpen($recipientEmail, $subject, (string)$sentTs, $openTimestamp);
            } catch (\Throwable) {}
        }
    }

    private static function isDuplicateOpen(string $tid, string $ip, int $now): bool
    {
        $stmt = Database::get()->prepare(
            'SELECT COUNT(*) FROM email_opens
             WHERE tracking_id = ? AND ip = ? AND is_internal = 0
               AND opened_at >= ?'
        );
        $stmt->execute([$tid, $ip, $now - self::DEDUP_WINDOW_SECONDS]);
        return (int) $stmt->fetchColumn() >= self::DEDUP_MAX_REAL;
    }

    /**
     * Send the 1x1 GIF and close the HTTP connection.
     * Works with PHP-FPM (fastcgi_finish_request), Apache mod_php, and LiteSpeed.
     */
    private static function closeConnectionWithPixel(): void
    {
        // Allow PHP to continue after client disconnects
        ignore_user_abort(true);
        // Remove any execution time limit for background work
        set_time_limit(30);

        // The 1x1 transparent GIF
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        // Headers to close connection
        header('Content-Type: image/gif');
        header('Content-Length: ' . strlen($pixel));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Connection: close');

        // Kill all output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Start fresh buffer, write pixel, flush, close
        ob_start();
        echo $pixel;
        $size = ob_get_length();
        header('Content-Length: ' . $size);
        ob_end_flush();
        flush();

        // PHP-FPM: explicitly finish the request (best method)
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }

        // LiteSpeed: use litespeed_finish_request if available
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
            return;
        }

        // Apache mod_php fallback: connection is closed via headers above.
        // The client will see the response complete, PHP continues in background.
    }

    private static function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) return trim(explode(',', $_SERVER[$k])[0]);
        }
        return '0.0.0.0';
    }
}
