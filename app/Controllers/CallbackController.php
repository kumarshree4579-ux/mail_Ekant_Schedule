<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Controllers\BrevoNotifier;
use App\Controllers\NotificationController;

final class CallbackController
{
    /**
     * GET /callback — Show the request callback form.
     * Query params: ?tid=...&email=...&name=...
     */
    public static function form(): void
    {
        $trackingId = trim($_GET['tid'] ?? '');
        $email = trim($_GET['email'] ?? '');
        $name = trim($_GET['name'] ?? '');
        $proposalInfo = null;

        // If tracking_id is provided, look up the associated email/proposal info
        if ($trackingId !== '') {
            try {
                $db = Database::get();
                $stmt = $db->prepare("SELECT subject, recipients, sent_at FROM email_sends WHERE tracking_id = ? LIMIT 1");
                $stmt->execute([$trackingId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $proposalInfo = [
                        'subject' => $row['subject'],
                        'recipients' => $row['recipients'],
                        'sent_at' => date('M j, Y', (int)$row['sent_at']),
                    ];
                }
            } catch (\Throwable) {}
        }

        require __DIR__ . '/../../public/views/callback.php';
    }

    /**
     * POST /callback — Handle form submission.
     */
    public static function submit(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $trackingId     = trim($_POST['tracking_id'] ?? '');
        $email          = trim($_POST['recipient_email'] ?? '');
        $name           = trim($_POST['recipient_name'] ?? '');
        $callbackNumber = trim($_POST['callback_number'] ?? '');
        $scheduleDate   = trim($_POST['schedule_date'] ?? '');
        $scheduleTime   = trim($_POST['schedule_time'] ?? '');
        $timezone       = trim($_POST['timezone'] ?? 'EST');
        $notes          = trim($_POST['notes'] ?? '');

        // Validate required fields
        if ($callbackNumber === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Callback number is required.']);
            return;
        }
        if ($scheduleDate === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Schedule date is required.']);
            return;
        }
        if ($scheduleTime === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Schedule time is required.']);
            return;
        }

        $allowedTimezones = ['CST', 'MST', 'PST', 'EST'];
        if (!in_array($timezone, $allowedTimezones, true)) {
            $timezone = 'EST';
        }

        // Basic phone validation (allow digits, spaces, dashes, parens, plus)
        if (!preg_match('/^[\d\s\-\(\)\+\.]{7,20}$/', $callbackNumber)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Please enter a valid phone number.']);
            return;
        }

        // Insert into database
        try {
            $db = Database::get();
            $stmt = $db->prepare("
                INSERT INTO callback_requests 
                    (tracking_id, recipient_email, recipient_name, callback_number, schedule_date, schedule_time, timezone, notes, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $trackingId ?: null,
                $email,
                $name,
                $callbackNumber,
                $scheduleDate,
                $scheduleTime,
                $timezone,
                $notes,
                time()
            ]);

            // Queue SSE notification for real-time browser push
            try {
                NotificationController::queueNotification(
                    $trackingId ?: 'callback',
                    $email ?: ($name ?: 'Unknown'),
                    "📞 Callback Request: {$name} — {$scheduleDate} {$scheduleTime} {$timezone}",
                    time(),
                    ''
                );
            } catch (\Throwable) {}

            // Send Brevo email notification (background-safe, non-blocking)
            try {
                BrevoNotifier::notifyCallbackRequest(
                    $name ?: 'Unknown',
                    $callbackNumber,
                    $scheduleDate,
                    $scheduleTime,
                    $timezone,
                    $email,
                    $notes,
                    $trackingId
                );
            } catch (\Throwable) {}

            echo json_encode(['ok' => true, 'message' => 'Callback request submitted successfully! We will call you at the scheduled time.']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Something went wrong. Please try again.']);
        }
    }

    /**
     * GET /api/callbacks — Return all callback requests as JSON (for Studio).
     */
    public static function list(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $db = Database::get();
            $rows = $db->query("
                SELECT * FROM callback_requests 
                ORDER BY created_at DESC 
                LIMIT 200
            ")->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Failed to load callbacks.']);
        }
    }

    /**
     * POST /api/callbacks/status — Update callback status (pending/confirmed/completed/cancelled).
     */
    public static function updateStatus(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id = (int) ($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];
        if ($id <= 0 || !in_array($status, $allowed, true)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
            return;
        }

        try {
            $db = Database::get();
            $stmt = $db->prepare("UPDATE callback_requests SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);

            echo json_encode(['ok' => true, 'message' => 'Status updated.']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Update failed.']);
        }
    }
}
