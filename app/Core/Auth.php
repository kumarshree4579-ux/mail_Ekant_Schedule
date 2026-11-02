<?php

declare(strict_types=1);

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;

final class Auth
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) return trim(explode(',', $_SERVER[$key])[0]);
        }
        return '0.0.0.0';
    }

    public static function device(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        if (preg_match('/(iPhone|iPad|Android|Windows Phone)/i', $ua, $m)) return $m[1];
        if (preg_match('/(Windows|Macintosh|Linux)/i', $ua, $m)) return $m[1];
        return substr($ua, 0, 80);
    }

    public static function geo(string $ip): array
    {
        $empty = ['lat' => 0, 'lng' => 0, 'city' => '', 'country' => ''];
        // Skip lookup for private/local IPs (localhost, LAN)
        if (in_array($ip, ['127.0.0.1', '::1'], true) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return $empty;
        }
        // Non-blocking: use a very short timeout (1s) to avoid blocking the request
        $ctx  = stream_context_create(['http' => ['timeout' => 1]]);
        $data = @json_decode(@file_get_contents(
            "http://ip-api.com/json/{$ip}?fields=lat,lon,city,country", false, $ctx
        ), true);
        return [
            'lat'     => $data['lat']     ?? 0,
            'lng'     => $data['lon']     ?? 0,
            'city'    => $data['city']    ?? '',
            'country' => $data['country'] ?? '',
        ];
    }

    // ── Mailer ────────────────────────────────────────────────────────────────

    public static function mail(string $to, string $subject, string $body): bool
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = APP_EMAIL;
            $mail->Password   = APP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->Timeout    = 5;  // 5s connection timeout — don't hang the login page
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom(APP_EMAIL, 'Proposal Studio');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ── User ──────────────────────────────────────────────────────────────────

    public static function findUser(string $email): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM users WHERE email = ? AND active = 1");
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public static function currentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['path' => '/', 'samesite' => 'Lax']);
            session_start();
        }

        $sid = $_COOKIE['sid'] ?? '';
        if (!$sid) {
            return null;
        }

        // Skip re-validation if requireAuth() already validated this request
        // Just fetch the user directly from the session
        $stmt = Database::get()->prepare(
            "SELECT u.* FROM sessions s JOIN users u ON u.id = s.user_id WHERE s.id = ? AND s.terminated = 0 LIMIT 1"
        );
        $stmt->execute([$sid]);
        return $stmt->fetch() ?: null;
    }

    public static function requireRole(array $roles): void
    {
        $user = self::currentUser();
        if (!$user || !in_array($user['role'] ?? '', $roles, true)) {
            http_response_code(403);
            echo 'Access denied';
            exit;
        }
    }

    // ── Brute-force ───────────────────────────────────────────────────────────

    public static function isBlocked(string $ip): bool
    {
        $db   = Database::get();
        $stmt = $db->prepare("SELECT fail_count, blocked_at FROM blocked_ips WHERE ip = ?");
        $stmt->execute([$ip]);
        $r = $stmt->fetch();
        if (!$r) return false;
        if ($r['fail_count'] >= MAX_FAILS) {
            if (time() - $r['blocked_at'] < BLOCK_TIME) return true;
            $db->prepare("DELETE FROM blocked_ips WHERE ip = ?")->execute([$ip]);
        }
        return false;
    }

    public static function recordFail(string $ip): void
    {
        Database::get()->prepare("
            INSERT INTO blocked_ips (ip, fail_count, blocked_at) VALUES (?, 1, ?)
            ON CONFLICT(ip) DO UPDATE SET fail_count = fail_count + 1, blocked_at = ?
        ")->execute([$ip, time(), time()]);
    }

    public static function clearFails(string $ip): void
    {
        Database::get()->prepare("DELETE FROM blocked_ips WHERE ip = ?")->execute([$ip]);
    }

    // ── OTP ───────────────────────────────────────────────────────────────────

    /**
     * Send OTP email.
     * type=login  → delivered to ADMIN_EMAIL (central inbox)
     * type=admin  → delivered to ADMIN_EMAIL with session-management warning
     * OTP is keyed to user's own email so verify works correctly.
     */
    public static function sendOtp(
        string $email,
        string $ip,
        string $device,
        array  $geo,
        string $type = 'login',
        string $name = ''
    ): bool {
        $otp      = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash     = password_hash($otp, PASSWORD_BCRYPT);
        $tokenKey = $type . ':' . $email;

        Database::get()->prepare("
            INSERT INTO otp_store (token_key, otp_hash, expires_at, ip) VALUES (?, ?, ?, ?)
            ON CONFLICT(token_key) DO UPDATE
                SET otp_hash=excluded.otp_hash, expires_at=excluded.expires_at, ip=excluded.ip
        ")->execute([$tokenKey, $hash, time() + OTP_EXPIRY, $ip]);

        $userLine = $name ? "<strong>User:</strong> {$name}<br>" : '';

        if ($type === 'admin') {
            $subject = '🛡️ Admin Panel OTP — Proposal Studio';
            $banner  = '#7c3aed';
            $title   = '🛡️ Admin Panel Access OTP';
            $notice  = "
                <div style='background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;padding:12px;margin:16px 0;'>
                    <p style='margin:0;font-size:13px;color:#92400e;font-weight:600;'>
                        ⚠️ This OTP is for managing login sessions only.<br>
                        This is <u>NOT</u> a login OTP.
                    </p>
                </div>";
            $footer = 'This OTP grants access to the Admin Session Manager panel.';
        } else {
            $subject = '🔐 Login OTP — Proposal Studio';
            $banner  = '#2563eb';
            $title   = '🔐 Login OTP';
            $notice  = '';
            $footer  = 'Use this OTP to complete your login.';
        }

        $body = "
            <div style='font-family:Arial;max-width:500px;margin:auto;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;'>
                <div style='background:{$banner};padding:20px 24px;'>
                    <h2 style='margin:0;color:#fff;font-size:18px;'>{$title}</h2>
                </div>
                <div style='padding:24px;'>
                    {$notice}
                    <p style='font-size:14px;color:#334155;margin:0 0 8px;'>Your one-time password:</p>
                    <p style='font-size:36px;font-weight:bold;letter-spacing:10px;color:{$banner};margin:0 0 4px;'>{$otp}</p>
                    <p style='font-size:12px;color:#94a3b8;margin:0 0 20px;'>Expires in 5 minutes.</p>
                    <hr style='border:none;border-top:1px solid #e2e8f0;margin:16px 0;'>
                    <p style='font-size:12px;color:#64748b;margin:0;line-height:1.8;'>
                        {$userLine}
                        <strong>IP:</strong> {$ip}<br>
                        <strong>Device:</strong> {$device}<br>
                        <strong>Location:</strong> {$geo['city']}, {$geo['country']}<br>
                        <strong>Lat / Long:</strong> {$geo['lat']}, {$geo['lng']}
                    </p>
                    <p style='font-size:11px;color:#94a3b8;margin:16px 0 0;'>{$footer}</p>
                    <p style='font-size:11px;color:#ef4444;margin:4px 0 0;'>If this wasn't you, secure your account immediately.</p>
                </div>
            </div>";

        return self::mail(ADMIN_EMAIL, $subject, $body);
    }

    public static function verifyOtp(string $email, string $otp, string $type = 'login'): bool
    {
        $tokenKey = $type . ':' . $email;
        $db       = Database::get();
        $stmt     = $db->prepare("SELECT otp_hash, expires_at FROM otp_store WHERE token_key = ?");
        $stmt->execute([$tokenKey]);
        $r = $stmt->fetch();

        if (!$r || time() > $r['expires_at']) return false;
        if (!password_verify($otp, $r['otp_hash'])) return false;

        $db->prepare("DELETE FROM otp_store WHERE token_key = ?")->execute([$tokenKey]);
        return true;
    }

    // ── Sessions ──────────────────────────────────────────────────────────────

    public static function createSession(string $email, string $ip, string $device, array $geo): string
    {
        $sid  = bin2hex(random_bytes(32));
        $now  = time();
        $user = self::findUser($email);

        Database::get()->prepare("
            INSERT INTO sessions (id, user_id, email, ip, device, lat, lng, created_at, last_active, refresh_exp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $sid, $user['id'] ?? null, $email, $ip, $device,
            $geo['lat'], $geo['lng'], $now, $now, $now + REFRESH_TTL,
        ]);

        return $sid;
    }

    public static function validateSession(string $sid): bool
    {
        $db   = Database::get();
        $stmt = $db->prepare("SELECT last_active, refresh_exp, terminated FROM sessions WHERE id = ?");
        $stmt->execute([$sid]);
        $r = $stmt->fetch();

        if (!$r || $r['terminated']) return false;

        $now = time();
        if ($now > $r['refresh_exp'] || $now - $r['last_active'] > SESSION_TTL) {
            self::terminateSession($sid);
            return false;
        }

        // Only update last_active once per minute to reduce DB writes
        if ($now - $r['last_active'] > 60) {
            $db->prepare("UPDATE sessions SET last_active = ? WHERE id = ?")->execute([$now, $sid]);
        }
        return true;
    }

    public static function terminateSession(string $sid): void
    {
        Database::get()->prepare("UPDATE sessions SET terminated = 1 WHERE id = ?")->execute([$sid]);
    }

    public static function logLogin(string $email, string $ip, string $device, array $geo, bool $success): void
    {
        Database::get()->prepare("
            INSERT INTO login_log (email, ip, device, lat, lng, success, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$email, $ip, $device, $geo['lat'], $geo['lng'], $success ? 1 : 0, time()]);
    }

    // ── Gate ──────────────────────────────────────────────────────────────────

    public static function requireAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['path' => '/', 'samesite' => 'Lax']);
            session_start();
        }
        $sid = $_COOKIE['sid'] ?? '';
        if (!$sid || !self::validateSession($sid)) {
            setcookie('sid', '', time() - 1, '/', '', false, true);
            
            // Detect if this is likely an AJAX/Fetch request
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
                      (isset($_SERVER['HTTP_SEC_FETCH_MODE']) && $_SERVER['HTTP_SEC_FETCH_MODE'] === 'cors') ||
                      (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                      ($_SERVER['REQUEST_METHOD'] === 'POST');
                      
            // Also endpoints known to return JSON
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $isApiEndpoint = str_contains($uri, '/tracking/opens') || 
                             str_contains($uri, '/tracking/latest-activity') || 
                             str_contains($uri, '/tracking/resend') ||
                             (str_contains($uri, '/studio') && isset($_POST['action']));
            
            if ($isAjax || $isApiEndpoint) {
                header('HTTP/1.1 401 Unauthorized');
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'status' => 'error', 'message' => 'Session expired. Please log in again.']);
                exit;
            }

            header('Location: ' . BASE_PATH . '/login');
            exit;
        }
    }
}
