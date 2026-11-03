<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use PHPMailer\PHPMailer\PHPMailer;

final class TrackingController
{
    private const PER_PAGE = 100;

    public static function index(): void
    {
        Auth::requireAuth();
        Auth::requireRole(['admin', 'email_tracker', 'user']);

        $db = Database::get();

        // ── Filters from GET ──────────────────────────────────────────────────
        $today     = date('Y-m-d');
        $dateFrom  = $_GET['date_from']  ?? $today;
        $dateTo    = $_GET['date_to']    ?? $today;
        $search    = trim($_GET['search'] ?? '');
        $typeFilter = $_GET['type'] ?? 'all';   // all | sent | draft
        $openFilter = $_GET['opened'] ?? 'all'; // all | opened | unopened
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $export    = ($_GET['export'] ?? '') === 'csv';
        $exportDetail = $_GET['detail'] ?? '';
        
        $sortColumn = $_GET['sort'] ?? 'sent_at';
        $sortDir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        // Convert date strings to unix timestamps (start/end of day)
        $tsFrom = strtotime($dateFrom . ' 00:00:00') ?: strtotime($today . ' 00:00:00');
        $tsTo   = strtotime($dateTo   . ' 23:59:59') ?: strtotime($today . ' 23:59:59');

        // ── Build WHERE ───────────────────────────────────────────────────────
        $where  = ['s.sent_at BETWEEN ? AND ?'];
        $params = [$tsFrom, $tsTo];

        if ($search !== '') {
            $where[]  = '(s.recipients LIKE ? OR s.subject LIKE ? OR s.custom_id LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($typeFilter === 'sent')  { $where[] = 's.is_draft = 0'; }
        if ($typeFilter === 'draft') { $where[] = 's.is_draft = 1'; }

        if ($openFilter === 'opened')   { $where[] = '(COALESCE(o.open_count, 0) > 0 OR COALESCE(a.attach_open_count, 0) > 0)'; }
        if ($openFilter === 'unopened') { $where[] = '(COALESCE(o.open_count, 0) = 0 AND COALESCE(a.attach_open_count, 0) = 0)'; }
        if ($openFilter === 'closed')   { $where[] = 's.is_closed = 1'; }
        if ($openFilter === 'unclosed') { $where[] = '(s.is_closed = 0 OR s.is_closed IS NULL)'; }

        $whereSQL = 'WHERE ' . implode(' AND ', $where);

        $validSorts = [
            'sent_at'           => 's.sent_at',
            'last_opened_at'    => 'NULLIF(MAX(COALESCE(o.last_opened_at, 0), COALESCE(a.last_attach_opened_at, 0)), 0)',
            'recipients'        => 's.recipients',
            'subject'           => 's.subject',
            'open_count'        => '(CASE WHEN COALESCE(o.open_count, 0) > 0 THEN o.open_count WHEN COALESCE(a.attach_open_count, 0) > 0 THEN 1 ELSE 0 END)',
            'attach_open_count' => 'COALESCE(a.attach_open_count, 0)',
            'custom_id'         => 's.custom_id',
            'is_closed'         => 's.is_closed',
            'closed_status'     => 's.is_closed',
            'closed_at'         => 's.closed_at',
            'is_bounced'        => 's.is_bounced',
        ];
        $orderBy = $validSorts[$sortColumn] ?? 's.sent_at';
        $orderBy .= ' ' . $sortDir;

        // Base FROM clause with safe grouped joins to avoid cartesian products
        $fromSQL = "
            FROM email_sends s
            LEFT JOIN (
                SELECT tracking_id, COUNT(id) as open_count, MAX(opened_at) as last_opened_at 
                FROM email_opens WHERE is_internal = 0 GROUP BY tracking_id
            ) o ON o.tracking_id = s.tracking_id
            LEFT JOIN (
                SELECT tracking_id, COUNT(id) as attach_open_count, MAX(opened_at) as last_attach_opened_at, SUM(duration_ms) as total_attach_time_ms 
                FROM attachment_opens WHERE (recipient_email IS NULL OR recipient_email NOT IN (?, ?)) GROUP BY tracking_id
            ) a ON a.tracking_id = s.tracking_id
        ";
        $joinParams = [APP_EMAIL, ADMIN_EMAIL];
        $allParams = array_merge($joinParams, $params);

        // ── CSV export (no pagination) ────────────────────────────────────────
        if ($export && $exportDetail === 'attachment_events') {
            $dstmt = $db->prepare("SELECT s.tracking_id, l.filename, a.token, a.event_type, a.page, a.percent, a.duration_ms, a.opened_at, a.recipient_email
                                  FROM email_sends s
                                  JOIN attachment_opens a ON a.tracking_id = s.tracking_id
                                  LEFT JOIN attachment_links l ON l.token = a.token
                                  {$whereSQL}
                                  AND a.recipient_email NOT IN (?, ?)
                                  ORDER BY s.sent_at DESC, s.tracking_id, l.filename, a.opened_at DESC");
            $dstmt->execute(array_merge($params, [APP_EMAIL, ADMIN_EMAIL]));
            $data = $dstmt->fetchAll();

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="email_attachment_events_' . date('Ymd_His') . '.csv"');
            header('Pragma: no-cache');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tracking ID', 'Filename', 'Token', 'Event Type', 'Page', 'Percent', 'Duration (ms)', 'Opened At', 'Recipient Email']);
            foreach ($data as $r) {
                fputcsv($out, [
                    $r['tracking_id'],
                    $r['filename'],
                    $r['token'],
                    $r['event_type'],
                    $r['page'] === null ? '' : $r['page'],
                    $r['percent'] === null ? '' : $r['percent'],
                    $r['duration_ms'] === null ? '' : $r['duration_ms'],
                    $r['opened_at'] ? date('Y-m-d H:i:s', (int)$r['opened_at']) : '',
                    $r['recipient_email'],
                ]);
            }
            fclose($out);
            exit;
        }

        if ($export && $exportDetail === 'attachments') {
            // Detailed per-attachment (and per-page) export
            $dstmt = $db->prepare("SELECT s.tracking_id, l.filename, a.token, a.page,
                                       COUNT(a.id) AS events, SUM(COALESCE(a.duration_ms,0)) AS total_ms
                                  FROM email_sends s
                                  JOIN attachment_opens a ON a.tracking_id = s.tracking_id
                                  JOIN attachment_links l ON l.token = a.token
                                  {$whereSQL}
                                  AND a.recipient_email NOT IN (?, ?)
                                  GROUP BY s.tracking_id, l.token, a.page
                                  ORDER BY s.sent_at DESC, s.tracking_id, l.token, a.page");
            $dstmt->execute(array_merge($params, [APP_EMAIL, ADMIN_EMAIL]));
            $data = $dstmt->fetchAll();

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="email_attachments_pages_' . date('Ymd_His') . '.csv"');
            header('Pragma: no-cache');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tracking ID', 'Filename', 'Token', 'Page', 'Events', 'Time (s)']);
            foreach ($data as $r) {
                fputcsv($out, [
                    $r['tracking_id'],
                    $r['filename'],
                    $r['token'],
                    $r['page'] === null ? '' : $r['page'],
                    $r['events'],
                    isset($r['total_ms']) ? round(((int)$r['total_ms'])/1000, 2) : 0,
                ]);
            }
            fclose($out);
            exit;
        }

        if ($export && $exportDetail === 'unread_fwd') {
            $sql = "
                SELECT 
                    custom_id,
                    MAX(recipients) as recipients,
                    MAX(CASE WHEN subject NOT LIKE 'FW:%' AND subject NOT LIKE 'FW:-%' AND subject NOT LIKE 'FWD:%' AND subject NOT LIKE 'FWD:-%' THEN sent_at END) as orig_sent_at,
                    MAX(CASE WHEN subject LIKE 'FW:%' OR subject LIKE 'FW:-%' THEN sent_at END) as fw_sent_at,
                    MAX(CASE WHEN subject LIKE 'FWD:%' OR subject LIKE 'FWD:-%' THEN sent_at END) as fwd_sent_at,
                    MAX(CASE WHEN subject NOT LIKE 'FW:%' AND subject NOT LIKE 'FW:-%' AND subject NOT LIKE 'FWD:%' AND subject NOT LIKE 'FWD:-%' THEN open_count END) as orig_opens,
                    MAX(CASE WHEN subject LIKE 'FW:%' OR subject LIKE 'FW:-%' THEN open_count END) as fw_opens,
                    MAX(CASE WHEN subject LIKE 'FWD:%' OR subject LIKE 'FWD:-%' THEN open_count END) as fwd_opens
                FROM (
                    SELECT s.custom_id, s.subject, s.sent_at, s.recipients,
                           (SELECT COUNT(id) FROM email_opens o WHERE o.tracking_id = s.tracking_id AND o.is_internal = 0) as open_count
                    FROM email_sends s
                    WHERE s.custom_id IS NOT NULL AND s.custom_id != '' AND s.is_draft = 0
                ) t
                GROUP BY custom_id
                HAVING fw_sent_at IS NOT NULL OR fwd_sent_at IS NOT NULL
            ";
            $stmt = $db->query($sql);
            $rows = $stmt->fetchAll();

            $filtered = array_filter($rows, function($row) {
                if ($row['fwd_sent_at']) return $row['fwd_opens'] == 0;
                if ($row['fw_sent_at']) return $row['fw_opens'] == 0;
                return false;
            });

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="unread_fwd_report_' . date('Ymd_His') . '.csv"');
            header('Pragma: no-cache');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['Custom ID', 'Recipients', 'Original Sent Date', 'FW Date', 'FWD Date', 'Original Opens', 'FW Opens', 'FWD Opens']);
            foreach ($filtered as $r) {
                fputcsv($out, [
                    $r['custom_id'],
                    $r['recipients'],
                    $r['orig_sent_at'] ? date('Y-m-d H:i:s', (int)$r['orig_sent_at']) : 'N/A',
                    $r['fw_sent_at'] ? date('Y-m-d H:i:s', (int)$r['fw_sent_at']) : 'N/A',
                    $r['fwd_sent_at'] ? date('Y-m-d H:i:s', (int)$r['fwd_sent_at']) : 'N/A',
                    (int)$r['orig_opens'],
                    (int)$r['fw_opens'],
                    (int)$r['fwd_opens'],
                ]);
            }
            fclose($out);
            exit;
        }

        if ($export) {
            $rows = $db->prepare(" 
                SELECT s.tracking_id, s.recipients, s.subject, s.is_draft, s.sent_at,
                       s.is_closed, s.closed_at, s.closed_remark, s.closed_by, s.is_bounced,
                       (CASE WHEN COALESCE(o.open_count, 0) > 0 THEN o.open_count WHEN COALESCE(a.attach_open_count, 0) > 0 THEN 1 ELSE 0 END) AS open_count,
                       COALESCE(a.attach_open_count, 0) AS attach_open_count,
                       COALESCE(a.total_attach_time_ms, 0) AS total_attach_time_ms,
                       NULLIF(MAX(COALESCE(o.last_opened_at, 0), COALESCE(a.last_attach_opened_at, 0)), 0) AS last_opened_at
                {$fromSQL}
                {$whereSQL}
                ORDER BY {$orderBy}
            ");
            $rows->execute($allParams);
            $data = $rows->fetchAll();

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="email_tracking_' . date('Ymd_His') . '.csv"');
            header('Pragma: no-cache');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tracking ID', 'Recipients', 'Subject', 'Type', 'Sent At', 'Opens', 'Att. Opens', 'Att. Time (s)', 'Last Opened', 'Track ID', 'Closed Status', 'Closed At', 'Closed Remark', 'Bounced']);
            foreach ($data as $r) {
                fputcsv($out, [
                    $r['tracking_id'],
                    $r['recipients'],
                    $r['subject'],
                    $r['is_draft'] ? 'Draft' : 'Sent',
                    date('Y-m-d H:i:s', (int)$r['sent_at']),
                    $r['open_count'],
                    $r['attach_open_count'],
                    isset($r['total_attach_time_ms']) ? round(((int)$r['total_attach_time_ms'])/1000, 1) : 0,
                    $r['last_opened_at'] ? date('Y-m-d H:i:s', (int)$r['last_opened_at']) : '',
                    $r['custom_id'] ?? '',
                    !empty($r['is_closed']) ? 'Closed' : 'Open',
                    $r['closed_at'] ?? '',
                    $r['closed_remark'] ?? '',
                    !empty($r['is_bounced']) ? 'Yes' : 'No',
                ]);
            }
            fclose($out);
            exit;
        }

        // ── Count total for pagination ────────────────────────────────────────
        $countStmt = $db->prepare("SELECT COUNT(s.id) {$fromSQL} {$whereSQL}");
        $countStmt->execute($allParams);
        $total     = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * self::PER_PAGE;

        // ── Fetch page ────────────────────────────────────────────────────────
        $stmt = $db->prepare(" 
            SELECT s.tracking_id, s.recipients, s.subject, s.is_draft, s.sent_at, s.custom_id,
                   s.is_closed, s.closed_at, s.closed_remark, s.closed_by, s.is_bounced,
                   (CASE WHEN COALESCE(o.open_count, 0) > 0 THEN o.open_count WHEN COALESCE(a.attach_open_count, 0) > 0 THEN 1 ELSE 0 END) AS open_count,
                   COALESCE(a.attach_open_count, 0) AS attach_open_count,
                   COALESCE(a.total_attach_time_ms, 0) AS total_attach_time_ms,
                   NULLIF(MAX(COALESCE(o.last_opened_at, 0), COALESCE(a.last_attach_opened_at, 0)), 0) AS last_opened_at
            {$fromSQL}
            {$whereSQL}
            ORDER BY {$orderBy}
            LIMIT " . self::PER_PAGE . " OFFSET {$offset}
        ");
        $stmt->execute($allParams);
        $rows = $stmt->fetchAll();

        $tids = array_column($rows, 'tracking_id');
        $rowAttachments = [];
        if (!empty($tids)) {
            $inClause = str_repeat('?,', count($tids) - 1) . '?';
            $stmtAtt = $db->prepare("
                SELECT p.tracking_id, p.url, p.name, p.token,
                       (SELECT COUNT(id) FROM attachment_opens a WHERE a.token = p.token AND (a.recipient_email IS NULL OR a.recipient_email NOT IN ('" . APP_EMAIL . "', '" . ADMIN_EMAIL . "'))) as open_count
                FROM pdf_link_tokens p
                WHERE p.tracking_id IN ($inClause)
            ");
            $stmtAtt->execute($tids);
            foreach ($stmtAtt->fetchAll() as $att) {
                $rowAttachments[$att['tracking_id']][] = $att;
            }
        }

        // ── Load callback requests per tracking_id ────────────────────────────
        $rowCallbacks = [];
        if (!empty($tids)) {
            $inClause2 = str_repeat('?,', count($tids) - 1) . '?';
            $cbStmt = $db->prepare("
                SELECT id, tracking_id, recipient_name, callback_number, schedule_date, schedule_time, timezone, notes, status, created_at
                FROM callback_requests
                WHERE tracking_id IN ($inClause2)
                ORDER BY created_at DESC
            ");
            $cbStmt->execute($tids);
            foreach ($cbStmt->fetchAll() as $cb) {
                $rowCallbacks[$cb['tracking_id']][] = $cb;
            }
        }

        // ── Summary stats for current filter ─────────────────────────────────
        $statsStmt = $db->prepare(" 
            SELECT
                COUNT(s.id) AS total_sent,
                SUM(CASE WHEN (COALESCE(o.open_count, 0) > 0 OR COALESCE(a.attach_open_count, 0) > 0) THEN 1 ELSE 0 END) AS total_opened,
                SUM(CASE WHEN COALESCE(o.open_count, 0) > 0 THEN o.open_count WHEN COALESCE(a.attach_open_count, 0) > 0 THEN 1 ELSE 0 END) AS total_open_events,
                SUM(COALESCE(a.attach_open_count, 0)) AS total_attach_events,
                SUM(COALESCE(a.total_attach_time_ms, 0)) AS total_attach_time_ms
            {$fromSQL}
            {$whereSQL}
        ");
        $statsStmt->execute($allParams);
        $stats = $statsStmt->fetch();
        if (!$stats) {
            $stats = ['total_sent' => 0, 'total_opened' => 0, 'total_attach_time_ms' => 0, 'total_attach_events' => 0];
        }

        require __DIR__ . '/../../public/views/tracking.php';
    }

    public static function opens(): void
    {
        Auth::requireAuth();
        Auth::requireRole(['admin', 'email_tracker', 'user']);

        $tid = trim((string)($_GET['tid'] ?? ''));
        if ($tid === '') {
            http_response_code(400);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'message' => 'Missing tracking id']);
            exit;
        }

        $db = Database::get();

        // Fetch message metadata (without the heavy 'body' field first for speed)
        $metaStmt = $db->prepare("SELECT subject, recipients, sent_at, body, studio_fields, custom_id FROM email_sends WHERE tracking_id = ? LIMIT 1");
        $metaStmt->execute([$tid]);
        $meta = $metaStmt->fetch();

        // Fetch open events (limit to last 50 — no one needs hundreds)
        $stmt = $db->prepare("SELECT opened_at, recipient_email, ip, user_agent FROM email_opens WHERE tracking_id = ? AND is_internal = 0 ORDER BY opened_at DESC LIMIT 50");
        $stmt->execute([$tid]);
        $rows = $stmt->fetchAll();

        // Fetch attachment open events — only key fields, skip the huge 'meta' JSON blob, limit 100
        $astmt = $db->prepare("
            SELECT a.token, a.event_type, a.page, a.duration_ms, a.percent, a.opened_at, a.recipient_email, a.ip,
                   p.name, p.url
            FROM attachment_opens a
            LEFT JOIN pdf_link_tokens p ON a.token = p.token
            WHERE a.tracking_id = ? 
            ORDER BY a.opened_at DESC
            LIMIT 100
        ");
        $astmt->execute([$tid]);
        $attachRows = $astmt->fetchAll();

        // Aggregate per-attachment summary (total time, event count, filename)
        $sumStmt = $db->prepare("SELECT l.token, l.filename, COUNT(a.id) AS events, SUM(COALESCE(a.duration_ms,0)) AS total_ms
                     FROM attachment_opens a
                     JOIN attachment_links l ON l.token = a.token
                     WHERE a.tracking_id = ?
                     GROUP BY l.token, l.filename
                     ORDER BY total_ms DESC
                     LIMIT 20");
        $sumStmt->execute([$tid]);
        $attachSummary = $sumStmt->fetchAll();

        // Aggregate per-attachment per-page summary (page-level durations and counts)
        $pageStmt = $db->prepare("SELECT a.token, a.page, COUNT(a.id) AS events, SUM(COALESCE(a.duration_ms,0)) AS total_ms
                     FROM attachment_opens a
                     WHERE a.tracking_id = ? AND a.page IS NOT NULL
                     GROUP BY a.token, a.page
                     ORDER BY a.token, a.page
                     LIMIT 100");
        $pageStmt->execute([$tid]);
        $attachPageSummary = $pageStmt->fetchAll();

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => true,
            'meta' => $meta ?: (object)[],
            'data' => $rows,
            'attachments' => $attachRows,
            'attachment_summary' => $attachSummary,
            'attachment_page_summary' => $attachPageSummary,
        ]);
        exit;
    }

    public static function latestActivity(): void
    {
        Auth::requireAuth();
        Auth::requireRole(['admin', 'email_tracker', 'user']);

        $db = Database::get();

        $emailOpensStmt = $db->query("
            SELECT o.id, o.opened_at, o.recipient_email, o.ip, o.user_agent, 'email' as type, 
                   s.subject, s.recipients as sent_to, s.tracking_id
            FROM email_opens o
            JOIN email_sends s ON o.tracking_id = s.tracking_id
            WHERE o.is_internal = 0
            ORDER BY o.opened_at DESC LIMIT 10
        ");
        $emailOpens = $emailOpensStmt->fetchAll();

        $attachOpensStmt = $db->query("
            SELECT a.id, a.opened_at, a.recipient_email, a.ip, a.user_agent, 'attachment' as type, 
                   s.subject, s.recipients as sent_to, s.tracking_id,
                   COALESCE(p.name, l.filename, 'Document') as filename, a.page, a.duration_ms
            FROM attachment_opens a
            JOIN email_sends s ON a.tracking_id = s.tracking_id
            LEFT JOIN attachment_links l ON a.token = l.token
            LEFT JOIN pdf_link_tokens p ON a.token = p.token
            WHERE (a.recipient_email IS NULL OR a.recipient_email NOT IN ('" . APP_EMAIL . "', '" . ADMIN_EMAIL . "'))
            ORDER BY a.opened_at DESC LIMIT 10
        ");
        $attachOpens = $attachOpensStmt->fetchAll();

        $all = array_merge($emailOpens, $attachOpens);
        usort($all, fn($a, $b) => $b['opened_at'] <=> $a['opened_at']);
        $latest10 = array_slice($all, 0, 10);

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => true, 'data' => $latest10]);
        exit;
    }
    public static function resendEmail(): void
    {
        Auth::requireAuth();
        header('Content-Type: application/json');

        $tid = trim($_POST['tid'] ?? '');
        if (empty($tid)) {
            echo json_encode(['ok' => false, 'message' => 'Tracking ID missing.']);
            exit;
        }

        $db = Database::get();
        $stmt = $db->prepare("SELECT id, subject, recipients, recipient_email, body, studio_fields, is_bounced, custom_id FROM email_sends WHERE tracking_id = ? AND is_draft = 0 LIMIT 1");
        $stmt->execute([$tid]);
        $email = $stmt->fetch();

        if (!$email) {
            echo json_encode(['ok' => false, 'message' => 'Original sent email not found.']);
            exit;
        }

        if (!empty($email['is_bounced'])) {
            echo json_encode(['ok' => false, 'message' => 'Cannot resend because this email is marked as bounced.']);
            exit;
        }

        // Check limit from .env
        $env = parse_ini_file(dirname(__DIR__, 2) . '/.env');
        $limit = isset($env['RESEND_LIMIT']) ? (int)$env['RESEND_LIMIT'] : 3;

        $studioFields = [];
        if (!empty($email['studio_fields'])) {
            $decoded = json_decode($email['studio_fields'], true);
            if (is_array($decoded)) {
                $studioFields = $decoded;
            }
        }

        $resendLogs = $studioFields['resend_logs'] ?? [];
        if (count($resendLogs) >= $limit) {
            echo json_encode(['ok' => false, 'message' => "Resend limit ($limit) reached for this email."]);
            exit;
        }

        $newEmail = trim($_POST['new_email'] ?? '');
        $targetRecipient = '';
        if (!empty($newEmail) && filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $targetRecipient = $newEmail;
        } else {
            $rawRecips = array_map('trim', explode(',', $email['recipient_email'] ?: $email['recipients']));
            foreach ($rawRecips as $rec) {
                if (filter_var($rec, FILTER_VALIDATE_EMAIL)) {
                    $targetRecipient = $rec;
                    break;
                }
            }
        }

        if (empty($targetRecipient)) {
            echo json_encode(['ok' => false, 'message' => 'No valid recipient email address.']);
            exit;
        }

        // Always create a FRESH entry in email_sends with its own unique tracking ID
        $newTrackingId = bin2hex(random_bytes(16));
        $now = time();

        // Prepare child studio_fields referencing parent email
        $childFields = $studioFields;
        $childFields['parent_tracking_id'] = $tid;
        $childFields['parent_send_id']     = (int)$email['id'];
        $childFields['is_reminder']        = 1;
        $childFields['reminder_number']    = count($resendLogs) + 1;
        unset($childFields['resend_logs']); // Fresh reminder row starts with no reminders of its own

        $htmlContent = $email['body'];
        // Strip previous tracking pixel
        $htmlContent = preg_replace('/<img[^>]+src=["\'][^"\']*\/track\?tid=[^"\']*["\'][^>]*>/i', '', $htmlContent);
        // Replace callback ID if any
        $htmlContent = str_replace('{{TRACKING_ID}}', $newTrackingId, $htmlContent);
        $htmlContent = preg_replace('/([?&]tid=)[a-f0-9]{16,64}/i', '$1' . $newTrackingId, $htmlContent);

        // Insert fresh entry into email_sends
        $ins = $db->prepare("
            INSERT INTO email_sends (tracking_id, recipients, recipient_email, subject, sent_at, is_draft, body, studio_fields, custom_id)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)
        ");
        $ins->execute([
            $newTrackingId,
            $targetRecipient,
            $targetRecipient,
            $email['subject'],
            $now,
            $htmlContent,
            json_encode($childFields),
            $email['custom_id'] ?? null
        ]);
        $newSendId = (int)$db->lastInsertId();

        // Mint fresh PDF tracking tokens for the new tracking ID and new send ID
        $finalHtml = StudioController::rewritePdfTokensForRecipient($htmlContent, $newTrackingId, $newSendId);
        // Inject fresh tracking pixel for the new recipient and new tracking ID
        $finalHtml = StudioController::injectTrackingPixel($finalHtml, $newTrackingId, $targetRecipient);

        // Update the fresh entry with finalized body
        $db->prepare("UPDATE email_sends SET body = ? WHERE id = ?")->execute([$finalHtml, $newSendId]);

        // Use PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = APP_EMAIL;
            $mail->Password   = APP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->setFrom(APP_EMAIL, 'Roger');

            $mail->addAddress($targetRecipient);
            $mail->isHTML(true);
            $mail->Subject = $email['subject'];
            
            // Inline CIDs for images if needed
            $cidIndex = 0;
            $mailBody = preg_replace_callback('/<img([^>]+)>/i', function ($m) use ($mail, &$cidIndex) {
                $tag = $m[1];
                if (!preg_match('/src=["\']([^"\']*)["\']/', $tag, $srcMatch)) return $m[0];
                $src = $srcMatch[1];
                if (str_contains($src, '/track?tid=')) return $m[0];
                if (str_starts_with($src, 'data:')) return $m[0];
                if (!filter_var($src, FILTER_VALIDATE_URL)) {
                    $local = dirname(__DIR__, 2) . '/' . ltrim($src, '/');
                    if (file_exists($local)) {
                        $cid = 'img_cid_' . ($cidIndex++) . '_' . time();
                        $mail->addEmbeddedImage($local, $cid);
                        return '<img' . preg_replace('/src=["\'][^"\']*["\']/', 'src="cid:' . $cid . '"', $tag) . '>';
                    }
                }
                return $m[0];
            }, $finalHtml);

            $mail->Body = $mailBody;
            $mail->send();
            
            // Append clean copy via IMAP
            if (function_exists('imap_open')) {
                $cleanMime = $mail->getSentMIMEMessage();
                $mpath = '{imap.hostinger.com:993/imap/ssl}INBOX.Sent';
                $imap = @imap_open($mpath, APP_EMAIL, APP_PASSWORD);
                if ($imap) {
                    imap_append($imap, $mpath, $cleanMime, "\\Seen");
                    imap_close($imap);
                }
            }

            // Append to parent email's audit logs ONLY (without modifying its existing data or recipient)
            $user = Auth::currentUser();
            $username = $user['name'] ?? 'Unknown User';
            $remNum = count($resendLogs) + 1;
            $resendLogs[] = "{$username} sent reminder #{$remNum} to " . htmlspecialchars($targetRecipient) . " on " . date('M j, Y g:i A') . " (New ID: {$newTrackingId})";
            $studioFields['resend_logs'] = $resendLogs;
            
            $updStmt = $db->prepare("UPDATE email_sends SET studio_fields = ? WHERE id = ?");
            $updStmt->execute([json_encode($studioFields), $email['id']]);

            echo json_encode([
                'ok' => true,
                'message' => "Reminder sent successfully as a fresh tracking entry to {$targetRecipient}!",
                'new_tracking_id' => $newTrackingId,
                'orig_tracking_id' => $tid
            ]);
        } catch (\Exception $e) {
            // Clean up the freshly created entry if mail sending failed
            try {
                $db->prepare("DELETE FROM email_sends WHERE id = ?")->execute([$newSendId]);
                $db->prepare("DELETE FROM pdf_link_tokens WHERE send_id = ?")->execute([$newSendId]);
            } catch (\Throwable) {}
            echo json_encode(['ok' => false, 'message' => 'Mailer Error: ' . $mail->ErrorInfo]);
        }
    }

    public static function markClosed(): void
    {
        Auth::requireAuth();
        Auth::requireRole(['admin', 'email_tracker', 'user']);

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
            exit;
        }

        $customId = trim((string)($_POST['custom_id'] ?? ''));
        $trackingId = trim((string)($_POST['tracking_id'] ?? ''));
        $closedAt = trim((string)($_POST['closed_date'] ?? ''));
        $remark = trim((string)($_POST['remark'] ?? ''));

        if ($customId === '' && $trackingId === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Missing Track ID or Tracking ID']);
            exit;
        }

        if ($closedAt === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Missing Closing Date']);
            exit;
        }

        $db = Database::get();
        $user = Auth::currentUser();
        $username = $user['name'] ?? 'Unknown User';
        
        $fullRemark = "Closed by {$username}";
        if ($remark !== '') {
            $fullRemark .= " - " . $remark;
        }

        try {
            if ($customId !== '') {
                $stmt = $db->prepare("
                    UPDATE email_sends 
                    SET is_closed = 1, closed_at = ?, closed_remark = ?, closed_by = ? 
                    WHERE custom_id = ?
                ");
                $stmt->execute([$closedAt, $fullRemark, $username, $customId]);
            } else {
                $stmt = $db->prepare("
                    UPDATE email_sends 
                    SET is_closed = 1, closed_at = ?, closed_remark = ?, closed_by = ? 
                    WHERE tracking_id = ?
                ");
                $stmt->execute([$closedAt, $fullRemark, $username, $trackingId]);
            }
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['ok' => true, 'message' => 'Tracking entries marked as closed']);
            } else {
                echo json_encode(['ok' => false, 'message' => 'No matching records found']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Database error']);
        }
        
        exit;
    }

    public static function markBounced(): void
    {
        Auth::requireAuth();
        Auth::requireRole(['admin', 'email_tracker', 'user']);

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
            exit;
        }

        $trackingId = trim((string)($_POST['tracking_id'] ?? ''));

        if ($trackingId === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Missing Tracking ID']);
            exit;
        }

        $db = Database::get();

        try {
            $stmt = $db->prepare("
                UPDATE email_sends 
                SET is_bounced = 1
                WHERE tracking_id = ?
            ");
            $stmt->execute([$trackingId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['ok' => true, 'message' => 'Email marked as bounced.']);
            } else {
                echo json_encode(['ok' => false, 'message' => 'No matching records found.']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Database error']);
        }
        
        exit;
    }
}
