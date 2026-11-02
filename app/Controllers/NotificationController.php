<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

/**
 * NotificationController — lightweight polling for real-time email open notifications.
 * 
 * Replaced SSE (which held a PHP worker for minutes) with a simple JSON poll endpoint.
 * The frontend polls every 10 seconds — much lighter on the server.
 */
final class NotificationController
{
    /**
     * GET /notifications/stream
     * 
     * Returns new notifications since the given `last_id`.
     * Frontend polls this every 10s instead of holding an SSE connection.
     */
    public static function stream(): void
    {
        Auth::requireAuth();
        Auth::requireRole(['admin', 'email_tracker', 'user']);

        $lastId = (int) ($_GET['last_id'] ?? 0);

        $db = Database::get();

        if ($lastId > 0) {
            $stmt = $db->prepare(
                "SELECT id, tracking_id, recipient_email, subject, opened_at, ip, created_at
                 FROM open_notifications
                 WHERE id > ?
                 ORDER BY id ASC LIMIT 20"
            );
            $stmt->execute([$lastId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            // First call: just return the latest ID as baseline
            $stmt = $db->query("SELECT MAX(id) as max_id FROM open_notifications");
            $maxId = (int) ($stmt->fetch()['max_id'] ?? 0);
            
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'data' => [], 'last_id' => $maxId]);
            exit;
        }

        $newLastId = $lastId;
        $formatted = [];
        foreach ($rows as $row) {
            $newLastId = max($newLastId, (int) $row['id']);
            $formatted[] = [
                'id'              => (int) $row['id'],
                'tracking_id'     => $row['tracking_id'],
                'recipient_email' => $row['recipient_email'],
                'subject'         => $row['subject'],
                'opened_at'       => (int) $row['opened_at'],
                'opened_at_fmt'   => date('Y-m-d h:i A', (int) $row['opened_at']),
                'ip'              => $row['ip'],
            ];
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => true, 'data' => $formatted, 'last_id' => $newLastId]);
        exit;
    }

    /**
     * Called from TrackController when a non-internal open is detected.
     * Inserts a row into open_notifications so the poll picks it up.
     */
    public static function queueNotification(
        string $trackingId,
        string $recipientEmail,
        string $subject,
        int $openedAt,
        string $ip
    ): void {
        try {
            Database::get()->prepare(
                "INSERT INTO open_notifications (tracking_id, recipient_email, subject, opened_at, ip, seen, created_at)
                 VALUES (?, ?, ?, ?, ?, 0, ?)"
            )->execute([$trackingId, $recipientEmail, $subject, $openedAt, $ip, time()]);
        } catch (\Throwable) {
            // Non-critical — don't break the tracking pixel if this fails
        }
    }

    /**
     * Cleanup old notifications (optional maintenance endpoint).
     */
    public static function cleanup(): void
    {
        Auth::requireAuth();
        Auth::requireRole(['admin']);

        $cutoff = time() - 86400;
        $deleted = Database::get()->prepare(
            "DELETE FROM open_notifications WHERE created_at < ?"
        );
        $deleted->execute([$cutoff]);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'cleaned' => $deleted->rowCount()]);
        exit;
    }
}
