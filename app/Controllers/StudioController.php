<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\GmailApi;
use App\Core\SchedulerConfig;
use PHPMailer\PHPMailer\PHPMailer;

final class StudioController
{
    private const MAX_BODY_BYTES   = 2_097_152;
    private const MAX_ATTACH_BYTES  = 26_214_400; // 25 MB
    private const SEND_RATE_LIMIT   = 450;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function csrf(): string
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private static function verifyCsrf(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
            exit;
        }
    }

    private static function safePath(string $dir, string $name): ?string
    {
        $normalizedDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR);
        $normalizedName = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $name);
        
        $realDir = realpath($normalizedDir);
        if ($realDir === false) {
            return null;
        }

        $realDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $realDir), DIRECTORY_SEPARATOR);
        
        if (strpos($normalizedName, '..') !== false) {
            return null;
        }

        return $realDir . DIRECTORY_SEPARATOR . $normalizedName;
    }

    private static function trackingId(): string
    {
        return bin2hex(random_bytes(16)); // 32-char hex, URL-safe
    }

    private static function normalizeTemplateName(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['\\', '/'], '', $value);
        $value = preg_replace('/[<>:"|?*\x00-\x1F]/', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value);
        return $value;
    }

    private static function humanizeFieldName(string $fieldKey): string
    {
        $labelMap = [
            'Hotel_Name' => 'Hotel Name',
            'Address' => 'Address',
            'City' => 'City',
            'State' => 'State',
            'Zip' => 'Zip',
            'Rooms' => '# of Rooms',
            'Hotel_Number' => 'Hotel Phone',
            'Contact_Person' => 'Contact Person',
            'First_Name' => 'First Name',
            'Email' => 'Contact Email',
            'Service_Provider' => 'Service Provider',
            'offeredProgramming' => 'Programming',
            'System' => 'System',
            'Bill' => 'Monthly Bill ($)',
            'Upfront' => 'Upfront Cost ($)',
        ];

        if (isset($labelMap[$fieldKey])) {
            return $labelMap[$fieldKey];
        }

        $label = preg_replace('/[_-]+/', ' ', $fieldKey) ?? $fieldKey;
        $label = trim($label);
        return $label === '' ? $fieldKey : ucwords($label);
    }

    private static function normalizeFieldType(string $type): string
    {
        $type = strtolower(trim($type));
        $map = [
            'mail' => 'email',
            'email_address' => 'email',
            'phone' => 'tel',
            'mobile' => 'tel',
            'currency' => 'number',
            'money' => 'number',
            'integer' => 'number',
            'int' => 'number',
            'float' => 'number',
            'date' => 'date',
            'textarea' => 'textarea',
            'multiline' => 'textarea',
            'dropdown' => 'text',
            'select' => 'text',
            'url' => 'url',
        ];

        return $map[$type] ?? $type;
    }

    private static function getTemplateFieldDefinitions(string $templateDir): array
    {
        $definitions = [];
        $templateFiles = glob($templateDir . '/*.html');
        if ($templateFiles === false) {
            return $definitions;
        }

        foreach ($templateFiles as $templateFile) {
            $content = file_get_contents($templateFile);
            if ($content === false) {
                continue;
            }

            preg_match_all('/<!--\s*FIELD\s*:\s*([A-Za-z0-9_]+)\s*(?:\|\s*([A-Za-z0-9_-]+))?(?:\|\s*([^>]+?))?\s*-->/i', $content, $declaredFields, PREG_SET_ORDER);
            $hasDeclaredFields = count($declaredFields) > 0;
            foreach ($declaredFields as $fieldMatch) {
                $fieldKey = $fieldMatch[1];
                if ($fieldKey === 'TRACKING_ID') {
                    continue;
                }

                $fieldType = $fieldMatch[2] ?? 'text';
                $fieldLabel = trim((string) ($fieldMatch[3] ?? ''));

                $normalizedType = self::normalizeFieldType($fieldType);
                $definitions[$fieldKey] = [
                    'label' => $fieldLabel !== '' ? $fieldLabel : self::humanizeFieldName($fieldKey),
                    'type' => $normalizedType,
                ];
            }

            if ($hasDeclaredFields) {
                continue;
            }

            preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $content, $placeholderMatches);
            foreach ($placeholderMatches[1] as $fieldKey) {
                if ($fieldKey === 'TRACKING_ID') {
                    continue;
                }

                if (isset($definitions[$fieldKey])) {
                    continue;
                }

                $definitions[$fieldKey] = [
                    'label' => self::humanizeFieldName($fieldKey),
                    'type' => 'text',
                ];
            }
        }

        return $definitions;
    }

    private static function saveSendRecord(
        string $trackingId,
        array  $recipients,
        string $subject,
        bool   $isDraft,
        string $body = '',
        ?string $studioFields = null,
        ?string $customId = null
    ): ?int {
        try {
            $stmt = Database::get()->prepare(
                'INSERT INTO email_sends (tracking_id, recipients, recipient_email, subject, sent_at, is_draft, body, studio_fields, custom_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $recipientStr = implode(', ', $recipients);
            $stmt->execute([
                $trackingId,
                $recipientStr,
                $recipients[0] ?? '',
                $subject,
                time(),
                $isDraft ? 1 : 0,
                $body,
                $studioFields,
                $customId
            ]);
            $id = (int) Database::get()->lastInsertId();
            return $id > 0 ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function saveSendRecordPerRecipient(
        string $trackingId,
        string $recipientEmail,
        string $subject,
        bool   $isDraft,
        string $body = '',
        ?string $studioFields = null,
        ?string $customId = null
    ): ?int {
        try {
            $stmt = Database::get()->prepare(
                'INSERT INTO email_sends (tracking_id, recipients, recipient_email, subject, sent_at, is_draft, body, studio_fields, custom_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $trackingId,
                $recipientEmail,
                $recipientEmail,
                $subject,
                time(),
                $isDraft ? 1 : 0,
                $body,
                $studioFields,
                $customId
            ]);
            $id = (int) Database::get()->lastInsertId();
            return $id > 0 ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function injectTrackingPixel(string $emailBody, string $trackingId, string $recipientEmail): string
    {
        // Must be absolute URL — email clients have no concept of relative paths
        $pixelUrl = APP_URL . '/track?tid=' . urlencode($trackingId) . '&to=' . urlencode($recipientEmail);
        $pixel    = '<img src="' . $pixelUrl . '" width="1" height="1" '
                  . 'style="display:block;width:1px;height:1px;border:0;mso-hide:all;" alt="">';
        // Inject just before </body> of the final wrapper
        if (stripos($emailBody, '</body>') !== false) {
            return str_ireplace('</body>', $pixel . '</body>', $emailBody);
        }
        return $emailBody . $pixel;
    }

    /**
     * Mint a fresh pdf_link_tokens row and return the token.
     * Called once per recipient so every viewer link is unique.
     */
    private static function mintPdfToken(string $url, string $name, string $trackingId, int $sendId): string
    {
        $token = bin2hex(random_bytes(24));
        Database::get()->prepare(
            'INSERT INTO pdf_link_tokens (token, url, name, created_at, tracking_id, send_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$token, $url, $name ?: 'Document', time(), $trackingId, $sendId]);
        return $token;
    }

    /**
     * For every <a data-pdf-url="..."> button in the body, replace the href
     * with a freshly-minted per-recipient token URL pointing to Viewer/index.php,
     * and remove the data attribute.
     */
    private static function rewritePdfTokensForRecipient(
        string $html,
        string $trackingId,
        int    $sendId
    ): string {
        return preg_replace_callback(
            '/<a([^>]+data-pdf-url=["\']([^"\']+)["\'][^>]*)>/i',
            function (array $m) use ($trackingId, $sendId): string {
                $attrs   = $m[1];
                $pdfUrl  = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Extract display name from data-pdf-name if present
                $name = '';
                if (preg_match('/data-pdf-name=["\']([^"\']*)["\']/', $attrs, $nm)) {
                    $name = html_entity_decode($nm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                $token = self::mintPdfToken($pdfUrl, $name, $trackingId, $sendId);
                // Point directly to Viewer/index.php with the PDF URL + tracking token
                $viewerBase = defined('VIEWER_URL') ? rtrim((string)VIEWER_URL, '/') . '/' : APP_URL . '/Viewer/';
                $viewerHref = $viewerBase . 'index.php?url=' . urlencode($pdfUrl) . '&token=' . urlencode($token);
                // Replace href and strip data-pdf-url / data-pdf-name attributes
                $attrs = preg_replace('/\s*href=["\'"][^"\']*["\']/', '', $attrs);
                $attrs = preg_replace('/\s*data-pdf-url=["\'"][^"\']*["\']/', '', $attrs);
                $attrs = preg_replace('/\s*data-pdf-name=["\'"][^"\']*["\']/', '', $attrs);
                return '<a href="' . htmlspecialchars($viewerHref, ENT_QUOTES) . '"' . $attrs . '>';
            },
            $html
        ) ?? $html;
    }

    private static function associatePdfLinkTokens(string $trackingId, int $sendId, string $htmlBody): void
    {
        if ($htmlBody === '') {
            return;
        }

        // Match both legacy /pdf-viewer?token= and new Viewer/index.php?url=...&token= patterns
        preg_match_all('/(?:(?:pdf-viewer|index\.php)[?&%](?:3F)?token[=%]3D|[?&]token=)([^"&\'\s]+)/i', $htmlBody, $matches);
        $tokens = [];
        foreach (($matches[1] ?? []) as $token) {
            $token = trim(urldecode((string) $token));
            if ($token !== '' && !isset($tokens[$token])) {
                $tokens[$token] = true;
            }
        }

        foreach (array_keys($tokens) as $token) {
            try {
                Database::get()->prepare(
                    'UPDATE pdf_link_tokens SET tracking_id = ?, send_id = ? WHERE token = ?'
                )->execute([$trackingId, $sendId, $token]);
            } catch (\Throwable) {}
        }
    }

    private static function checkSendRate(): bool
    {
        $since = time() - 86400;
        $count = (int) Database::get()
            ->prepare('SELECT COUNT(*) FROM email_sends WHERE sent_at > ? AND is_draft = 0')
            ->execute([$since]) ? Database::get()
            ->prepare('SELECT COUNT(*) FROM email_sends WHERE sent_at > ? AND is_draft = 0')
            ->execute([$since]) : 0;
        // Simpler inline query
        $stmt = Database::get()->prepare(
            'SELECT COUNT(*) FROM email_sends WHERE sent_at > ? AND is_draft = 0'
        );
        $stmt->execute([$since]);
        return (int) $stmt->fetchColumn() < self::SEND_RATE_LIMIT;
    }

    // ── Main ──────────────────────────────────────────────────────────────────

    public static function index(): void
    {
        Auth::requireAuth();

        $user = Auth::currentUser();
        if (($user['role'] ?? '') === 'email_tracker') {
            header('Location: ' . BASE_PATH . '/tracking'); exit;
        }

        $templateDir = dirname(__DIR__, 2) . '/templates/proposals';
        if (!file_exists($templateDir)) mkdir($templateDir, 0777, true);

        $statusMessage = '';
        $csrfToken     = self::csrf();

        // ── Check Track ID for existing records / remarks (AJAX) ───────────────
        if (($_GET['action'] ?? '') === 'check_track_id' || ($_POST['action'] ?? '') === 'check_track_id') {
            self::checkTrackId();
        }

        // ── Get studio options (AJAX) ─────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_studio_options') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            try {
                $db = Database::get();
                $rows = $db->query("SELECT type, name, parent_provider, parent_system FROM studio_options ORDER BY name ASC")->fetchAll();
                echo json_encode(['status' => 'success', 'options' => $rows]);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }

        // ── Add studio option (AJAX) ──────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_studio_option') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $type = trim($_POST['type'] ?? '');
            if ($type === 'programming') $type = 'offeredProgramming';
            
            $name = trim($_POST['name'] ?? '');
            $parentProvider = !empty($_POST['parent_provider']) ? trim($_POST['parent_provider']) : null;
            $parentSystem = !empty($_POST['parent_system']) ? trim($_POST['parent_system']) : null;

            if ($type === '' || $name === '') {
                echo json_encode(['status' => 'error', 'message' => 'Type and name are required.']);
                exit;
            }

            try {
                $db = Database::get();
                $stmt = $db->prepare("INSERT OR IGNORE INTO studio_options (type, name, parent_provider, parent_system) VALUES (?, ?, ?, ?)");
                $stmt->execute([$type, $name, $parentProvider, $parentSystem]);
                echo json_encode(['status' => 'success', 'message' => 'Option added successfully.']);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }

        // ── Delete studio option (AJAX) ───────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_studio_option') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $type = trim($_POST['type'] ?? '');
            if ($type === 'programming') $type = 'offeredProgramming';
            
            $name = trim($_POST['name'] ?? '');
            $parentProvider = !empty($_POST['parent_provider']) ? trim($_POST['parent_provider']) : null;

            if ($type === '' || $name === '') {
                echo json_encode(['status' => 'error', 'message' => 'Type and name are required.']);
                exit;
            }

            try {
                $db = Database::get();
                if ($parentProvider !== null) {
                    $stmt = $db->prepare("DELETE FROM studio_options WHERE type = ? AND name = ? AND parent_provider = ?");
                    $stmt->execute([$type, $name, $parentProvider]);
                } else {
                    $stmt = $db->prepare("DELETE FROM studio_options WHERE type = ? AND name = ?");
                    $stmt->execute([$type, $name]);
                }
                echo json_encode(['status' => 'success', 'message' => 'Option deleted successfully.']);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }

        // ── Upload template ───────────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_template') {
            if (isset($_FILES['template_file']) && $_FILES['template_file']['error'] === UPLOAD_ERR_OK) {
                $name = basename($_FILES['template_file']['name']);
                $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $mime = mime_content_type($_FILES['template_file']['tmp_name']);
                if ($ext === 'html' && in_array($mime, ['text/html', 'text/plain'], true)) {
                    $content = file_get_contents($_FILES['template_file']['tmp_name']);
                    // Strip PHP tags from uploaded templates
                    $content = preg_replace('/<\?(?:php|=).*?\?>/si', '', $content);
                    $safeName = self::normalizeTemplateName($name);
                    if ($safeName === '') {
                        $statusMessage = '❌ Error: Invalid file name.';
                    } else {
                        $dest = self::safePath($templateDir, $safeName);
                        if ($dest) {
                            if (!preg_match('/<!--\s*EMAIL_SUBJECT:\s*.*?\s*-->/is', $content)) {
                                $content = '<!-- EMAIL_SUBJECT: Dish Network Exclusive Discount Offer for {{Hotel_Name}} {{City}}, {{State}} {{Zip}} -->' . ltrim($content);
                            }
                            file_put_contents($dest, $content);
                            $statusMessage = "✅ Template '{$safeName}' uploaded successfully!";
                        } else {
                            $statusMessage = '❌ Error: Invalid file path.';
                        }
                    }
                } else {
                    $statusMessage = '❌ Error: Only .html files are allowed.';
                }
            }
        }

        // ── Save new template (AJAX) ──────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_new_template') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $name = self::normalizeTemplateName($_POST['template_name'] ?? '');
            if (!$name) { echo json_encode(['status' => 'error', 'message' => 'Template name required.']); exit; }
            $dest = self::safePath($templateDir, $name . '.html');
            if (!$dest) { echo json_encode(['status' => 'error', 'message' => 'Invalid path.']); exit; }
            $content = $_POST['html_content'] ?? '';
            $saveMode = strtolower(trim((string) ($_POST['save_mode'] ?? 'values')));
            $emailSubject = trim(strip_tags(html_entity_decode(
                $_POST['email_subject'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'
            )));
            $emailSubject = str_replace(["\r", "\n", "\0"], '', $emailSubject);

            $content = preg_replace('/<!--\s*EMAIL_SUBJECT:\s*.*?\s*-->/is', '', $content);
            $content = trim($content);

            if ($saveMode === 'placeholder') {
                // Keep placeholder tokens stable and avoid saving resolved values.
                $content = preg_replace('/{{\s*([A-Za-z0-9_]+)\s*}}/u', '{{${1}}}', $content);
            }

            $content = "<!-- EMAIL_SUBJECT: " . $emailSubject . " -->\n" . ltrim($content);

            if (strlen($content) > self::MAX_BODY_BYTES) {
                echo json_encode(['status' => 'error', 'message' => 'Content too large (max 2 MB).']); exit;
            }
            if (!is_dir($templateDir)) {
                mkdir($templateDir, 0777, true);
            }
            $written = file_put_contents($dest, $content);
            echo $written !== false
                ? json_encode(['status' => 'success', 'message' => "Template '{$name}' saved!", 'template_name' => $name])
                : json_encode(['status' => 'error', 'message' => 'Failed to write template file.']);
            exit;
        }


                // ── BULK LIST MANAGEMENT (AJAX) ───────────────────────────────────
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_mapped_list') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $listName = trim($_POST['list_name'] ?? '');
            $listId = $_POST['list_id'] ?? null;
            $csvData = json_decode($_POST['csv_data'] ?? '[]', true);
            
            try {
                $db = Database::get();
                $db->beginTransaction();
                
                if (!$listId) {
                    if (!$listName) { echo json_encode(['status' => 'error', 'message' => 'Invalid list name.']); exit; }
                    $stmt = $db->prepare("INSERT INTO bulk_lists (name, created_at) VALUES (?, ?)");
                    $stmt->execute([$listName, time()]);
                    $listId = $db->lastInsertId();
                }
                
                if (!empty($csvData)) {
                    $stmtItem = $db->prepare("INSERT INTO bulk_list_items (list_id, email, data, status, created_at) VALUES (?, ?, ?, 'pending', ?)");
                    foreach ($csvData as $row) {
                        $email = $row['Email'] ?? '';
                        unset($row['Email']);
                        if ($email) {
                            $stmtItem->execute([$listId, $email, json_encode($row), time()]);
                        }
                    }
                }
                
                $db->commit();
                echo json_encode(['status' => 'success', 'message' => 'Chunk saved', 'list_id' => $listId]);
            } catch (\Throwable $e) {
                if (isset($db)) $db->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
            }
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_saved_lists') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $storageDir = dirname(__DIR__, 2) . '/storage/lists';
            $lists = [];
            if (is_dir($storageDir)) {
                $files = glob($storageDir . '/*.csv');
                foreach ($files as $file) {
                    $name = basename($file, '.csv');
                    if ($name === 'temp' || strpos($name, 'temp_') === 0) continue;
                    
                    // count rows
                    $count = 0;
                    $fp = fopen($file, 'r');
                    if ($fp) {
                        while(fgetcsv($fp)) $count++;
                        fclose($fp);
                    }
                    
                    $lists[] = ['name' => $name, 'rows' => max(0, $count - 1)];
                }
            }
            echo json_encode(['status' => 'success', 'lists' => $lists]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_list_range') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $listName = trim(preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['list_name'] ?? ''));
            $start = (int) ($_POST['start'] ?? 1);
            $end = (int) ($_POST['end'] ?? 10);
            
            $file = dirname(__DIR__, 2) . '/storage/lists/' . $listName . '.csv';
            if (!file_exists($file)) { echo json_encode(['status' => 'error', 'message' => 'List not found.']); exit; }
            
            $rows = [];
            if (($handle = fopen($file, "r")) !== FALSE) {
                $headers = fgetcsv($handle);
                $rowIdx = 1;
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if ($rowIdx >= $start && $rowIdx <= $end) {
                        $rowAssoc = [];
                        foreach ($headers as $i => $h) {
                            $rowAssoc[$h] = $data[$i] ?? '';
                        }
                        $rowAssoc['_row_idx'] = $rowIdx; // Pass the row index so JS knows which one to log
                        $rows[] = $rowAssoc;
                    } elseif ($rowIdx > $end) {
                        break;
                    }
                    $rowIdx++;
                }
                fclose($handle);
            }
            
            echo json_encode(['status' => 'success', 'data' => $rows]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finalize_list_campaign') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $listName = trim(preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['list_name'] ?? ''));
            $storageDir = dirname(__DIR__, 2) . '/storage/lists';
            $listFile = $storageDir . '/' . $listName . '.csv';
            $logFile = $storageDir . '/' . $listName . '_actions.log';
            
            if (!file_exists($listFile)) { echo json_encode(['status' => 'error', 'message' => 'List not found.']); exit; }
            
            if (file_exists($logFile)) {
                $actions = []; 
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $parts = explode('|', $line);
                    $idx = (int)$parts[0];
                    $action = $parts[1] ?? '';
                    $remark = $parts[2] ?? '';
                    $actions[$idx] = ['action' => $action, 'remark' => $remark];
                }
                
                $temp = $storageDir . '/temp_finalize_' . uniqid() . '.csv';
                $in = fopen($listFile, 'r');
                $out = fopen($temp, 'w');
                
                if ($in && $out) {
                    $headers = fgetcsv($in);
                    
                    $hasRemarkCol = in_array('Remark', $headers);
                    $needsRemarkCol = false;
                    foreach ($actions as $a) if ($a['action'] === 'remark') $needsRemarkCol = true;
                    
                    if ($needsRemarkCol && !$hasRemarkCol) {
                        $headers[] = 'Remark';
                        $hasRemarkCol = true;
                    }
                    fputcsv($out, $headers);
                    
                    $rowIdx = 1;
                    while (($row = fgetcsv($in)) !== FALSE) {
                        if (isset($actions[$rowIdx])) {
                            if ($actions[$rowIdx]['action'] === 'delete') {
                                $rowIdx++;
                                continue; 
                            } elseif ($actions[$rowIdx]['action'] === 'remark') {
                                if ($needsRemarkCol) {
                                    if (count($row) < count($headers)) {
                                        $row[] = $actions[$rowIdx]['remark'];
                                    } else {
                                        $remarkIdx = array_search('Remark', $headers);
                                        if ($remarkIdx !== false) $row[$remarkIdx] = $actions[$rowIdx]['remark'];
                                    }
                                }
                            }
                        }
                        fputcsv($out, $row);
                        $rowIdx++;
                    }
                    fclose($in);
                    fclose($out);
                    rename($temp, $listFile);
                }
                unlink($logFile);
            }
            
            echo json_encode(['status' => 'success', 'download_url' => '?action=download_list&list=' . $listName]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'download_list') {
            $listName = trim(preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['list'] ?? ''));
            $file = dirname(__DIR__, 2) . '/storage/lists/' . $listName . '.csv';
            if (file_exists($file)) {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $listName . '.csv"');
                readfile($file);
                exit;
            } else {
                echo "List not found."; exit;
            }
        }

        // ── Get DB Bulk Lists (AJAX) ──────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_db_list') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            try {
                $db = Database::get();
                $listId = (int)($_POST['list_id'] ?? 0);
                $db->prepare("DELETE FROM bulk_list_items WHERE list_id = ?")->execute([$listId]);
                $db->prepare("DELETE FROM bulk_lists WHERE id = ?")->execute([$listId]);
                echo json_encode(['status' => 'success']);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_db_list') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            try {
                $db = Database::get();
                $listId = (int)($_POST['list_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                if (!$name) throw new \Exception('Name required');
                $db->prepare("UPDATE bulk_lists SET name = ? WHERE id = ?")->execute([$name, $listId]);
                echo json_encode(['status' => 'success']);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_db_list_item') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            try {
                $db = Database::get();
                $itemId = (int)($_POST['item_id'] ?? 0);
                $db->prepare("DELETE FROM bulk_list_items WHERE id = ?")->execute([$itemId]);
                echo json_encode(['status' => 'success']);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_db_list_item') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            try {
                $db = Database::get();
                $itemId = (int)($_POST['item_id'] ?? 0);
                $email = trim($_POST['email'] ?? '');
                if (!$email) throw new \Exception('Email required');
                $db->prepare("UPDATE bulk_list_items SET email = ? WHERE id = ?")->execute([$email, $itemId]);
                echo json_encode(['status' => 'success']);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_db_lists') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            try {
                $db = Database::get();
                $rows = $db->query("SELECT l.id, l.name, l.created_at, COUNT(i.id) as item_count FROM bulk_lists l LEFT JOIN bulk_list_items i ON l.id = i.list_id GROUP BY l.id ORDER BY l.created_at DESC")->fetchAll();
                echo json_encode(['status' => 'success', 'lists' => $rows]);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_db_list_items') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            try {
                $db = Database::get();
                $listId = (int)($_POST['list_id'] ?? 0);
                $offset = max(0, (int)($_POST['offset'] ?? 0));
                $limit = 25;

                $countStmt = $db->prepare('SELECT COUNT(*) FROM bulk_list_items WHERE list_id = ?');
                $countStmt->execute([$listId]);
                $total = (int) $countStmt->fetchColumn();

                $stmt = $db->prepare('SELECT * FROM bulk_list_items WHERE list_id = ? ORDER BY id ASC LIMIT ? OFFSET ?');
                $stmt->bindValue(1, $listId, \PDO::PARAM_INT);
                $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
                $stmt->bindValue(3, $offset, \PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll();
                
                // Parse the JSON data so frontend can use it easily
                foreach ($rows as &$r) {
                    $r['data'] = json_decode($r['data'] ?? '{}', true);
                }
                
                echo json_encode(['status' => 'success', 'items' => $rows, 'total' => $total]);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }

        // ── Get sent emails (AJAX) ───────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_sent_emails') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            try {
                $db = Database::get();
                $searchQuery = trim($_POST['search_query'] ?? '');
                
                if ($searchQuery !== '') {
                    $stmt = $db->prepare("SELECT id, tracking_id, recipient_email, subject, sent_at, body, studio_fields FROM email_sends WHERE is_draft = 0 AND (recipient_email LIKE ? OR subject LIKE ?) ORDER BY sent_at DESC LIMIT 1000");
                    $like = '%' . $searchQuery . '%';
                    $stmt->execute([$like, $like]);
                    $rows = $stmt->fetchAll();
                } else {
                    $rows = $db->query("SELECT id, tracking_id, recipient_email, subject, sent_at, body, studio_fields FROM email_sends WHERE is_draft = 0 ORDER BY sent_at DESC LIMIT 100")->fetchAll();
                }
                
                echo json_encode(['status' => 'success', 'emails' => $rows]);
            } catch (\Throwable $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }

        // ── Process email / draft (AJAX) ──────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'process_email') {
            header('Content-Type: application/json');
            self::verifyCsrf();

            // Parse recipients
            $rawEmails = [];
            if (isset($_POST['client_email']) && is_array($_POST['client_email'])) {
                $rawEmails = $_POST['client_email'];
            } elseif (!empty($_POST['client_email'])) {
                $rawEmails = array_map('trim', explode(',', (string) $_POST['client_email']));
            }
            $clientEmails = array_values(array_filter(
                array_map(fn($e) => filter_var(trim($e), FILTER_SANITIZE_EMAIL), $rawEmails),
                fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)
            ));
            if (empty($clientEmails)) {
                echo json_encode(['status' => 'error', 'message' => 'No valid recipient email(s) provided.']); exit;
            }

            // Subject — strip newlines to prevent header injection
            $emailSubject = trim(strip_tags(html_entity_decode(
                $_POST['email_subject'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'
            )));
            $emailSubject = str_replace(["\r", "\n", "\0"], '', $emailSubject) ?: 'Custom Proposal';

            $htmlContent = $_POST['html_content'] ?? '';
            // Strip any existing tracking pixels (e.g. if loaded from a previous sent copy)
            $htmlContent = preg_replace('/<img[^>]+src=["\'][^"\']*\/track\?tid=[^"\']*["\'][^>]*>/i', '', $htmlContent);
            // Never send the internal subject marker as email content.
            $htmlContent = preg_replace('/<!--\s*EMAIL_SUBJECT:\s*.*?\s*-->/is', '', $htmlContent);
            $isDraft     = ($_POST['is_draft'] ?? '') === '1';

            // Body size cap
            if (strlen($htmlContent) > self::MAX_BODY_BYTES) {
                echo json_encode(['status' => 'error', 'message' => 'Email body too large (max 2 MB). Reduce image count or content.']); exit;
            }

            // Rate limit (only for actual sends)
            if (!$isDraft && !self::checkSendRate()) {
                echo json_encode(['status' => 'error', 'message' => 'Daily send limit reached (' . self::SEND_RATE_LIMIT . '). Try again tomorrow.']); exit;
            }

            // Attachment validation
            $uploadedAttachments = [];
            if (isset($_FILES['attachment']) && is_array($_FILES['attachment']['name'])) {
                foreach ($_FILES['attachment']['name'] as $index => $name) {
                    if (!empty($name) && $_FILES['attachment']['error'][$index] === UPLOAD_ERR_OK) {
                        $uploadedAttachments[] = [
                            'name' => $name,
                            'tmp_name' => $_FILES['attachment']['tmp_name'][$index],
                            'size' => $_FILES['attachment']['size'][$index],
                            'error' => $_FILES['attachment']['error'][$index],
                        ];
                    }
                }
            } elseif (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadedAttachments[] = [
                    'name' => $_FILES['attachment']['name'],
                    'tmp_name' => $_FILES['attachment']['tmp_name'],
                    'size' => $_FILES['attachment']['size'],
                    'error' => $_FILES['attachment']['error'],
                ];
            }

            if (!empty($uploadedAttachments)) {
                $disallowedExt = [
                    'exe', 'bat', 'cmd', 'sh', 'php', 'phtml', 'cgi', 'pl', 'py', 'vbs', 'scr', 'msi', 'com', 'js', 'jar', 'apk'
                ];

                foreach ($uploadedAttachments as $file) {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, $disallowedExt, true)) {
                        echo json_encode(['status' => 'error', 'message' => "Files with .{$ext} extension are not allowed for security reasons."]); exit;
                    }
                    if ((int) $file['size'] > self::MAX_ATTACH_BYTES) {
                        echo json_encode(['status' => 'error', 'message' => 'Attachment too large (max 25 MB).']); exit;
                    }
                }
            }

            // Generate tracking ID — pixel injected into final body AFTER wrapper is built
            $trackingId = self::trackingId();

            // Process images (regex, no DOMDocument to avoid entity re-encoding)
            // Skip tracking pixel URLs so they are never mangled
            $cidIndex = 0;
            $mail     = new PHPMailer(true);
            if (!empty($htmlContent)) {
                $htmlContent = preg_replace_callback('/<img([^>]+)>/i', function ($m) use ($mail, &$cidIndex) {
                    $tag = $m[1];
                    if (!preg_match('/src=["\']([^"\']*)["\']/', $tag, $srcMatch)) return $m[0];
                    $src = $srcMatch[1];
                    // Never touch the tracking pixel
                    if (str_contains($src, '/track?tid=')) return $m[0];
                    if (str_starts_with($src, 'data:')) {
                        return '<img' . preg_replace('/src=["\'][^"\']*["\']/', 'src="" alt="[Image removed]"', $tag) . '>';
                    }
                    if (!filter_var($src, FILTER_VALIDATE_URL)) {
                        $local = dirname(__DIR__, 2) . '/' . ltrim($src, '/');
                        if (file_exists($local)) {
                            $cid = 'img_cid_' . ($cidIndex++) . '_' . time();
                            $mail->addEmbeddedImage($local, $cid);
                            return '<img' . preg_replace('/src=["\'][^"\']*["\']/', 'src="cid:' . $cid . '"', $tag) . '>';
                        }
                    }
                    return $m[0];
                }, $htmlContent);
            }

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

                if (!empty($uploadedAttachments)) {
                    foreach ($uploadedAttachments as $file) {
                        $mail->addAttachment($file['tmp_name'], basename($file['name']));
                    }
                }

                $mail->isHTML(true);
                $mail->Subject = $emailSubject;
                $finalBody =
                    '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"'
                    . ' "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
                    . '<html xmlns="http://www.w3.org/1999/xhtml"><head>'
                    . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>'
                    . '<meta name="viewport" content="width=device-width, initial-scale=1.0"/>'
                    . '<style>body{margin:0;padding:0;background-color:#f8fafc;font-family:-apple-system,'
                    . 'BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;}'
                    . 'img{border:0;height:auto;max-width:100%;}</style>'
                    . '</head><body style="margin:0;padding:0;background-color:#f8fafc;">'
                    . '<table border="0" cellpadding="0" cellspacing="0" width="100%"'
                    . ' style="background-color:#f8fafc;"><tr><td align="center" style="padding:24px;">'
                    . '<table border="0" cellpadding="0" cellspacing="0" width="100%"'
                    . ' style="background-color:#ffffff;border:1px solid #cbd5e1;border-radius:12px;'
                    . 'margin:0 auto;max-width:900px;"><tr><td style="padding:36px;font-family:Arial,'
                    . 'sans-serif;font-size:15px;line-height:1.6;color:#334155;">'
                    . $htmlContent
                    . '</td></tr></table></td></tr></table></body></html>';

                $mail->Body = $finalBody;
                if ($isDraft) {
                    // Replace {{TRACKING_ID}} placeholder in callback links for drafts
                    $mail->Body = str_replace('{{TRACKING_ID}}', $trackingId, $mail->Body);
                    $mail->Body = self::injectTrackingPixel($mail->Body, $trackingId, $clientEmails[0] ?? '');
                }

                if ($isDraft) {
                    foreach ($clientEmails as $em) {
                        $mail->addAddress($em);
                    }

                    if (!function_exists('imap_open')) {
                        echo json_encode(['status' => 'error', 'message' => 'PHP IMAP extension not enabled.']); exit;
                    }
                    $mail->preSend();
                    $mime  = $mail->getSentMIMEMessage();
                    $mpath = '{imap.gmail.com:993/imap/ssl}[Gmail]/Drafts';
                    $imap  = @imap_open($mpath, APP_EMAIL, APP_PASSWORD);
                        if ($imap) {
                        imap_append($imap, $mpath, $mime, '\\Draft');
                        imap_close($imap);
                        $sendId = self::saveSendRecord(
                            $trackingId,
                            $clientEmails,
                            $emailSubject,
                            true,
                            $mail->Body,
                            $_POST['studio_fields'] ?? null,
                            $_POST['custom_id'] ?? null
                        );
                        if ($sendId) {
                            self::associatePdfLinkTokens($trackingId, $sendId, $mail->Body);
                        }
                        echo json_encode(['status' => 'success', 'message' => 'Saved to Gmail Drafts!', 'tracking_id' => $trackingId]);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'IMAP connection failed.']);
                    }
                } else {
                    $lastTrackingId = $trackingId;
                    foreach ($clientEmails as $recipient) {
                        $recipientTrackingId = self::trackingId();
                        $lastTrackingId = $recipientTrackingId;
                        // Save record first so we have a sendId for token minting
                        $sendId = self::saveSendRecordPerRecipient(
                            $recipientTrackingId,
                            $recipient,
                            $emailSubject,
                            false,
                            $finalBody,
                            $_POST['studio_fields'] ?? null,
                            $_POST['custom_id'] ?? null
                        );
                        // Rewrite any PDF link buttons with a fresh per-recipient token
                        $recipientBody = $sendId
                            ? self::rewritePdfTokensForRecipient($finalBody, $recipientTrackingId, $sendId)
                            : $finalBody;
                        // Replace {{TRACKING_ID}} placeholder in callback links
                        $recipientBody = str_replace('{{TRACKING_ID}}', $recipientTrackingId, $recipientBody);
                        $mail->clearAddresses();
                        $mail->addAddress($recipient);
                        
                        // Generate custom Message-ID
                        $msgIdStr = sprintf('<%s@%s>', bin2hex(random_bytes(16)), 'e-voxx.com');
                        $mail->MessageID = $msgIdStr;
                        
                        // Set the clean body (without tracking pixels, and with direct PDF URLs) BEFORE cloning
                        $mail->Body = preg_replace_callback(
                            '/<a([^>]+data-pdf-url=["\']([^"\']+)["\'][^>]*)>/i',
                            function (array $m): string {
                                $attrs  = $m[1];
                                $pdfUrl = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                $attrs = preg_replace('/\s*href=["\'"][^"\']*["\']/', '', $attrs);
                                $attrs = preg_replace('/\s*data-pdf-url=["\'"][^"\']*["\']/', '', $attrs);
                                $attrs = preg_replace('/\s*data-pdf-name=["\'"][^"\']*["\']/', '', $attrs);
                                return '<a href="' . htmlspecialchars($pdfUrl, ENT_QUOTES) . '"' . $attrs . '>';
                            },
                            $finalBody
                        );
                        
                        // ── STRATEGY: Send tracked via Gmail API (returns message ID),
                        // then trash that copy and append clean via IMAP.
                        
                        // Generate clean MIME for Gmail Sent box (direct PDF URLs, no pixel)
                        $cleanMail = clone $mail;
                        $cleanMail->MessageID = sprintf('<%s@%s>', bin2hex(random_bytes(16)), 'e-voxx.com');
                        $cleanMail->preSend();
                        $cleanMime = $cleanMail->getSentMIMEMessage();
                        
                        // Build tracked MIME and send via Gmail API
                        $mail->Body = self::injectTrackingPixel($recipientBody, $recipientTrackingId, $recipient);
                        $imapLogs = [];
                        $imapSuccess = false;

                        if (GmailApi::isConfigured()) {
                            // Send via Gmail API — returns the message ID directly
                            $mail->preSend();
                            $trackedMime = $mail->getSentMIMEMessage();
                            $gmailMsgId = GmailApi::sendRawEmail($trackedMime);
                            $apiError = GmailApi::$lastError;

                            if ($gmailMsgId) {
                                $imapLogs[] = "Gmail API: sent (id: {$gmailMsgId}).";
                                
                                // Permanently delete the tracked copy from Sent immediately
                                if (GmailApi::deleteMessage($gmailMsgId)) {
                                    $imapLogs[] = "Gmail API: permanently deleted tracked copy.";
                                } else {
                                    // Fallback to trash if permanent delete fails
                                    if (GmailApi::trashMessage($gmailMsgId)) {
                                        $imapLogs[] = "Gmail API: delete failed, but successfully trashed tracked copy.";
                                    } else {
                                        $imapLogs[] = "Gmail API: both delete and trash failed.";
                                    }
                                }

                                // Append clean copy via IMAP
                                if (function_exists('imap_open')) {
                                    $mpath = '{imap.hostinger.com:993/imap/ssl}INBOX.Sent';
                                    $imap = @imap_open($mpath, APP_EMAIL, APP_PASSWORD);
                                    if ($imap) {
                                        if (imap_append($imap, $mpath, $cleanMime, "\\Seen")) {
                                            $imapLogs[] = "Appended clean copy to Sent.";
                                            $imapSuccess = true;
                                        } else {
                                            $imapLogs[] = "Failed to append clean copy.";
                                        }
                                        imap_close($imap);
                                    } else {
                                        $imapLogs[] = "IMAP connection failed for append.";
                                    }
                                    @imap_errors();
                                    @imap_alerts();
                                }
                            } else {
                                $imapLogs[] = "Gmail API: send failed ({$apiError}), falling back to SMTP.";
                                $mail->send();
                                $imapSuccess = true;
                            }
                        } else {
                            // Fallback: send via SMTP if Gmail API not configured
                            $mail->send();
                            $imapLogs[] = "Sent via SMTP.";
                            
                            // Append clean copy via IMAP
                            if (function_exists('imap_open')) {
                                $mpath = '{imap.hostinger.com:993/imap/ssl}INBOX.Sent';
                                $imap = @imap_open($mpath, APP_EMAIL, APP_PASSWORD);
                                if ($imap) {
                                    if (imap_append($imap, $mpath, $cleanMime, "\\Seen")) {
                                        $imapLogs[] = "Appended clean copy to Sent via SMTP.";
                                        $imapSuccess = true;
                                    } else {
                                        $imapLogs[] = "Failed to append clean copy.";
                                    }
                                    imap_close($imap);
                                } else {
                                    $imapLogs[] = "IMAP connection failed for append.";
                                }
                                @imap_errors();
                                @imap_alerts();
                            }
                            $imapSuccess = true;
                        }
                        if ($sendId) {
                            self::associatePdfLinkTokens($recipientTrackingId, $sendId, $mail->Body);
                            try {
                                Database::get()->prepare('UPDATE email_sends SET body = ? WHERE id = ?')->execute([$mail->Body, $sendId]);
                            } catch (\Throwable) {}
                        }
                    }
                    echo json_encode([
                        'status'      => 'success',
                        'message'     => 'Proposal sent to ' . implode(', ', $clientEmails),
                        'tracking_id' => $lastTrackingId,
                        'imap_logs'   => $imapLogs,
                        'imap_success'=> $imapSuccess
                    ]);
                }
            } catch (\Throwable $e) {
                $err = !empty($mail->ErrorInfo) ? $mail->ErrorInfo : $e->getMessage();
                echo json_encode(['status' => 'error', 'message' => 'Error: ' . $err]);
            }
            exit;
        }

        // ── Delete template (AJAX) ────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_template') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $name  = self::normalizeTemplateName($_POST['template_name'] ?? '');
            $files = glob($templateDir . '/*.html');
            if (count($files) <= 1) {
                echo json_encode(['status' => 'error', 'message' => 'Cannot delete the last template.']); exit;
            }
            $dest = self::safePath($templateDir, $name . '.html');
            if (!$dest) { echo json_encode(['status' => 'error', 'message' => 'Invalid path.']); exit; }
            echo (file_exists($dest) && unlink($dest))
                ? json_encode(['status' => 'success', 'message' => 'Deleted.'])
                : json_encode(['status' => 'error', 'message' => 'File not found.']);
            exit;
        }

        // ── Serve template content via AJAX ───────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_template'])) {
            header('Content-Type: text/html; charset=UTF-8');
            $key  = self::normalizeTemplateName($_GET['get_template'] ?? '');
            $dest = self::safePath($templateDir, $key . '.html');
            echo ($dest && file_exists($dest)) ? file_get_contents($dest) : '';
            exit;
        }

        // ── Asset gallery AJAX ─────────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_assets') {
            header('Content-Type: application/json');
            $rows = Database::get()->query('SELECT id, url, public_id FROM assets ORDER BY added_at DESC')->fetchAll();
            echo json_encode($rows);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_asset') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $url      = trim($_POST['url']      ?? '');
            $publicId = trim($_POST['public_id'] ?? '');
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid URL']); exit;
            }
            Database::get()->prepare(
                'INSERT INTO assets (url, public_id, added_at) VALUES (?, ?, ?)
                 ON CONFLICT(url) DO UPDATE SET public_id=excluded.public_id, added_at=excluded.added_at'
            )->execute([$url, $publicId, time()]);
            $id = (int) Database::get()->lastInsertId();
            echo json_encode(['status' => 'success', 'id' => $id]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_asset') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            Database::get()->prepare('DELETE FROM assets WHERE id = ?')->execute([$id]);
            echo json_encode(['status' => 'success']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_pdf_link_token') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $url  = trim($_POST['url'] ?? '');
            $name = trim($_POST['name'] ?? '');
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid PDF URL']); exit;
            }
            $token = bin2hex(random_bytes(24));
            Database::get()->prepare(
                'INSERT INTO pdf_link_tokens (token, url, name, created_at) VALUES (?, ?, ?, ?)'
            )->execute([$token, $url, $name ?: 'Document', time()]);
            echo json_encode(['status' => 'success', 'token' => $token]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cloudinary_sign') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            if (!CLD_CLOUD || !CLD_API_KEY || !CLD_API_SECRET) {
                echo json_encode(['status' => 'error', 'message' => 'Cloudinary API key and secret are not configured.']);
                exit;
            }

            $uploadPreset = trim($_POST['upload_preset'] ?? '');
            $timestamp = time();
            $params = ['timestamp' => $timestamp];
            if ($uploadPreset !== '') {
                $params['upload_preset'] = $uploadPreset;
            }

            ksort($params);
            $toSign = implode('&', array_map(static fn(string $key, string $value): string => $key . '=' . $value, array_keys($params), $params));
            $signature = sha1($toSign . CLD_API_SECRET);

            echo json_encode([
                'status' => 'success',
                'api_key' => CLD_API_KEY,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cloudinary_verify') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            if (!CLD_CLOUD || !CLD_API_KEY || !CLD_API_SECRET) {
                echo json_encode(['status' => 'error', 'message' => 'Cloudinary cloud name, API key and secret are required.']);
                exit;
            }

            $verifyUrl = 'https://api.cloudinary.com/v1_1/' . rawurlencode(CLD_CLOUD);
            if (CLD_PRESET) {
                $verifyUrl .= '/upload_presets/' . rawurlencode(CLD_PRESET);
            } else {
                $verifyUrl .= '/resources/image/upload?max_results=1';
            }

            $ch = curl_init($verifyUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => CLD_API_KEY . ':' . CLD_API_SECRET,
                CURLOPT_FAILONERROR => false,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_TIMEOUT => 15,
            ]);
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                echo json_encode(['status' => 'error', 'message' => 'Cloudinary request failed: ' . $curlErr]);
                exit;
            }

            if ($httpCode === 200) {
                $message = CLD_PRESET
                    ? 'Cloudinary credentials are valid and upload preset exists.'
                    : 'Cloudinary credentials are valid.';
                echo json_encode(['status' => 'success', 'message' => $message]);
                exit;
            }

            if ($httpCode === 401) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid Cloudinary API key or secret.']);
                exit;
            }

            if ($httpCode === 404 && CLD_PRESET) {
                echo json_encode(['status' => 'error', 'message' => 'Cloudinary upload preset not found: ' . CLD_PRESET]);
                exit;
            }

            $details = $body ?: 'HTTP ' . $httpCode;
            echo json_encode(['status' => 'error', 'message' => 'Cloudinary verification failed: ' . $details]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_pdf_assets') {
            header('Content-Type: application/json');
            $rows = Database::get()->query('SELECT id, url, name, is_pinned FROM pdf_assets ORDER BY is_pinned DESC, added_at DESC')->fetchAll();
            echo json_encode($rows);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_pdf_asset') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $url  = trim($_POST['url']  ?? '');
            $name = trim($_POST['name'] ?? '');
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid URL']); exit;
            }
            Database::get()->prepare(
                'INSERT INTO pdf_assets (url, name, added_at) VALUES (?, ?, ?)
                 ON CONFLICT(url) DO UPDATE SET name=excluded.name, added_at=excluded.added_at'
            )->execute([$url, $name, time()]);
            $id = (int) Database::get()->lastInsertId();
            echo json_encode(['status' => 'success', 'id' => $id]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_pdf_asset') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            Database::get()->prepare('DELETE FROM pdf_assets WHERE id = ?')->execute([$id]);
            echo json_encode(['status' => 'success']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_pin_pdf_asset') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $db = Database::get();
            $stmt = $db->prepare('SELECT is_pinned FROM pdf_assets WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'PDF not found']);
                exit;
            }
            $newPinned = $row['is_pinned'] ? 0 : 1;
            $db->prepare('UPDATE pdf_assets SET is_pinned = ? WHERE id = ?')->execute([$newPinned, $id]);
            echo json_encode(['status' => 'success', 'is_pinned' => $newPinned]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rename_pdf_asset') {
            header('Content-Type: application/json');
            self::verifyCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($id <= 0 || $name === '') {
                echo json_encode(['status' => 'error', 'message' => 'Invalid name or id']);
                exit;
            }
            // Cap length to avoid overflow
            $name = mb_substr($name, 0, 200);
            Database::get()->prepare('UPDATE pdf_assets SET name = ? WHERE id = ?')->execute([$name, $id]);
            echo json_encode(['status' => 'success', 'name' => $name]);
            exit;
        }

        // ── Load templates for view (titles only) ─────────────────────────────
        $availableTemplates = [];
        foreach (glob($templateDir . '/*.html') as $file) {
            $key = basename($file, '.html');
            $availableTemplates[$key] = [
                'title'   => ucwords(str_replace('_', ' ', $key)),
                'content' => '',
            ];
        }

        $templateFieldDefinitions = self::getTemplateFieldDefinitions($templateDir);
        $placeholderFields = [];
        foreach ($templateFieldDefinitions as $fieldKey => $fieldMeta) {
            $placeholderFields[$fieldKey] = $fieldMeta['label'];
        }
        $placeholderFieldTypes = [];
        foreach ($templateFieldDefinitions as $fieldKey => $fieldMeta) {
            $placeholderFieldTypes[$fieldKey] = $fieldMeta['type'];
        }

        require __DIR__ . '/../../public/views/studio.php';
    }

    public static function checkTrackId(): void
    {
        Auth::requireAuth();
        header('Content-Type: application/json; charset=UTF-8');

        $customId = trim((string)($_GET['custom_id'] ?? $_POST['custom_id'] ?? ''));
        if ($customId === '') {
            echo json_encode(['ok' => true, 'found' => false]);
            exit;
        }

        $db = Database::get();
        // Fetch the most recent or closed record with this custom_id
        $stmt = $db->prepare('
            SELECT id, tracking_id, custom_id, is_closed, closed_at, closed_remark, closed_by, recipients, subject, sent_at
            FROM email_sends
            WHERE custom_id = ? AND custom_id != ""
            ORDER BY is_closed DESC, sent_at DESC
            LIMIT 1
        ');
        $stmt->execute([$customId]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['ok' => true, 'found' => false]);
            exit;
        }

        $cntStmt = $db->prepare('SELECT COUNT(*) FROM email_sends WHERE custom_id = ?');
        $cntStmt->execute([$customId]);
        $totalCount = (int)$cntStmt->fetchColumn();

        echo json_encode([
            'ok'            => true,
            'found'         => true,
            'is_closed'     => (bool)$row['is_closed'],
            'closed_at'     => $row['closed_at'] ?? '',
            'closed_remark' => $row['closed_remark'] ?? '',
            'closed_by'     => $row['closed_by'] ?? '',
            'sent_at'       => (int)($row['sent_at'] ?? 0),
            'recipients'    => $row['recipients'] ?? '',
            'subject'       => $row['subject'] ?? '',
            'count'         => $totalCount,
        ]);
        exit;
    }

    public static function schedulerConfig(): void
    {
        Auth::requireAuth();
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $db = Database::get();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $time48 = SchedulerConfig::validTime(is_array($data) ? ($data['scheduler_48h_time'] ?? null) : null);
                $time72 = SchedulerConfig::validTime(is_array($data) ? ($data['scheduler_72h_time'] ?? null) : null);
                if ($time48 === null || $time72 === null) {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'message' => 'Enter valid times in HH:MM format.']);
                    exit;
                }

                SchedulerConfig::saveTimes($db, $time48, $time72);
                echo json_encode(['ok' => true]);
                exit;
            }

            echo json_encode(SchedulerConfig::load($db, dirname(__DIR__, 2)));
        } catch (\PDOException | \RuntimeException $e) {
            error_log('Scheduler configuration error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Failed to load or save scheduler configuration.']);
        }
    }
}
