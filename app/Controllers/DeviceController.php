<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

final class DeviceController
{
    public static function register(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $ip = $input['ip'] ?? '';
        $platform = $input['platform'] ?? 'Unknown';

        if (empty($ip)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'IP address is required.']);
            exit;
        }

        $db = Database::get();

        // Check if IP is already allowed
        $stmt = $db->prepare("SELECT ip FROM allowed_ips WHERE ip = ?");
        $stmt->execute([$ip]);
        if ($stmt->fetch()) {
            echo json_encode(['ok' => true, 'message' => 'IP is already whitelisted.']);
            exit;
        }

        $token = bin2hex(random_bytes(16));
        $createdAt = time();

        $stmt = $db->prepare("
            INSERT INTO device_registration_tokens (token, ip, platform, created_at)
            VALUES (?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([$token, $ip, $platform, $createdAt]);
            
            // Build the approval URL (assumes index.php routes /whitelist to this controller)
            // Determine host from environment or server variables
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $parsedHost = parse_url($_ENV['APP_URL'] ?? '', PHP_URL_HOST);
            $host = $_SERVER['HTTP_HOST'] ?? ($parsedHost ?: 'localhost');
            $whitelistUrl = $protocol . $host . BASE_PATH . '/whitelist?token=' . $token;

            $subject = 'Action Required: New Device Registration Request';
            $body = "
                <div style='font-family:Arial;max-width:500px;margin:auto;border-radius:10px;border:1px solid #e2e8f0;padding:20px;'>
                    <h2 style='color:#2563eb;'>New Device Registration</h2>
                    <p>A new device is trying to access the login page.</p>
                    <p><strong>IP Address:</strong> {$ip}</p>
                    <p><strong>Platform:</strong> {$platform}</p>
                    <br>
                    <a href='{$whitelistUrl}' style='background:#2563eb;color:#fff;padding:10px 15px;text-decoration:none;border-radius:5px;font-weight:bold;'>Whitelist this IP</a>
                    <p style='font-size:12px;color:#94a3b8;margin-top:20px;'>If you don't recognize this request, you can safely ignore this email.</p>
                </div>
            ";

            if (Auth::mail(ADMIN_EMAIL, $subject, $body)) {
                echo json_encode(['ok' => true, 'message' => 'Registration request sent to admin.']);
            } else {
                http_response_code(500);
                echo json_encode(['ok' => false, 'message' => 'Failed to send registration email to admin.']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Failed to register device.']);
        }
        
        exit;
    }

    public static function whitelist(): void
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            echo "Invalid token.";
            exit;
        }

        $db = Database::get();
        $stmt = $db->prepare("SELECT ip FROM device_registration_tokens WHERE token = ?");
        $stmt->execute([$token]);
        $record = $stmt->fetch();

        if (!$record) {
            echo "Token is invalid or has already been used.";
            exit;
        }

        $ip = $record['ip'];

        // Add to allowed_ips
        $insertStmt = $db->prepare("INSERT OR IGNORE INTO allowed_ips (ip, added_at) VALUES (?, ?)");
        $insertStmt->execute([$ip, time()]);

        // Delete token
        $deleteStmt = $db->prepare("DELETE FROM device_registration_tokens WHERE token = ?");
        $deleteStmt->execute([$token]);

        echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>
                <h1 style='color:green;'>Success!</h1>
                <p>The IP address <strong>{$ip}</strong> has been successfully whitelisted.</p>
              </div>";
        exit;
    }
}
