<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;

final class AuthController
{
    public static function login(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['path' => '/', 'samesite' => 'Lax']);
            session_start();
        }

        $ip     = Auth::ip();
// --- IP Whitelist Check ---
// Device also
        $allowedIps = [];
        try {
            $db = \App\Core\Database::get();
            $stmt = $db->query("SELECT ip FROM allowed_ips");
            $allowedIps = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            // Fallback if DB fails
            $allowedIps = ['115.246.109.187', '103.188.127.180', '::1'];
        }

        if (!in_array($ip, $allowedIps, true)) {
            http_response_code(404);
            $notFoundView = __DIR__ . '/../../public/views/404.php';
            if (file_exists($notFoundView)) {
                // echo $ip . " is not allowed to access this page.";
                require $notFoundView;
            } else {
                echo "404 Not Found";
            }
            exit;
        }
        $device = Auth::device();
        $step   = $_SESSION['auth_step'] ?? 'email';
        $error  = '';
        $info   = '';

        // Already authenticated
        $sid = $_COOKIE['sid'] ?? '';
        if ($sid && Auth::validateSession($sid)) {
            header('Location: ' . BASE_PATH . '/tracking'); exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (isset($_POST['send_otp'])) {
                if (Auth::isBlocked($ip)) {
                    $error = 'Too many failed attempts. Try again in 15 minutes.';
                } else {
                    $email = strtolower(trim($_POST['email'] ?? ''));
                    $user  = Auth::findUser($email);
                    if (!$user) {
                        Auth::recordFail($ip);
                        $error = 'Unauthorized email address.';
                    } else {
                        $geo = Auth::geo($ip);
                        if (($user['otp_required'] ?? 1) == 0) {
                            $password = trim((string)($_POST['password'] ?? ''));
                            if ($password === '' || !password_verify($password, (string)($user['password'] ?? ''))) {
                                Auth::recordFail($ip);
                                $error = 'Invalid password.';
                            } else {
                                Auth::clearFails($ip);
                                Auth::logLogin($email, $ip, $device, $geo, true);
                                $newSid = Auth::createSession($email, $ip, $device, $geo);
                                setcookie('sid', $newSid, [
                                    'expires'  => time() + REFRESH_TTL,
                                    'path'     => '/',
                                    'httponly' => true,
                                    'samesite' => 'Lax',
                                ]);
                                $redirect = BASE_PATH . '/tracking';
                                header('Location: ' . $redirect); exit;
                            }
                        } elseif (Auth::sendOtp($email, $ip, $device, $geo, 'login', $user['name'] ?? 'User')) {
                            $_SESSION['auth_step']  = 'otp';
                            $_SESSION['auth_email'] = $email;
                            $_SESSION['auth_name']  = $user['name'] ?? 'User';
                            $_SESSION['auth_geo']   = $geo;
                            $step = 'otp';
                            $info = 'OTP sent. Valid for 5 minutes.';
                        } else {
                            $error = 'Failed to send OTP. Check SMTP config in .env';
                        }
                    }
                }
            }

            elseif (isset($_POST['verify_otp'])) {
                if (Auth::isBlocked($ip)) {
                    $error = 'IP blocked. Try again later.';
                    $step  = 'otp';
                } else {
                    $otp   = trim($_POST['otp'] ?? '');
                    $email = $_SESSION['auth_email'] ?? '';
                    $geo   = $_SESSION['auth_geo']   ?? ['lat' => 0, 'lng' => 0, 'city' => '', 'country' => ''];

                    if (empty($email)) {
                        $step  = 'email';
                        $error = 'Session expired. Please start again.';
                    } elseif (Auth::verifyOtp($email, $otp, 'login')) {
                        Auth::clearFails($ip);
                        Auth::logLogin($email, $ip, $device, $geo, true);
                        $user = Auth::findUser($email);
                        $newSid = Auth::createSession($email, $ip, $device, $geo);
                        setcookie('sid', $newSid, [
                            'expires'  => time() + REFRESH_TTL,
                            'path'     => '/',
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                        unset($_SESSION['auth_step'], $_SESSION['auth_email'], $_SESSION['auth_name'], $_SESSION['auth_geo']);
                        $redirect = BASE_PATH . '/tracking';
                        header('Location: ' . $redirect); exit;
                    } else {
                        Auth::recordFail($ip);
                        Auth::logLogin($email, $ip, $device, $geo, false);
                        $error = 'Invalid or expired OTP.';
                        $step  = 'otp';
                    }
                }
            }

            elseif (isset($_POST['resend_otp'])) {
                $email = $_SESSION['auth_email'] ?? '';
                if (empty($email)) {
                    $step = 'email';
                } else {
                    $geo = Auth::geo($ip);
                    if (Auth::sendOtp($email, $ip, $device, $geo, 'login', $_SESSION['auth_name'] ?? '')) {
                        $_SESSION['auth_geo'] = $geo;
                        $step = 'otp';
                        $info = 'New OTP sent.';
                    } else {
                        $error = 'Could not resend OTP.';
                        $step  = 'otp';
                    }
                }
            }

            elseif (isset($_POST['back_to_email'])) {
                unset($_SESSION['auth_step'], $_SESSION['auth_email'], $_SESSION['auth_name'], $_SESSION['auth_geo']);
                $step = 'email';
            }
        }

        require __DIR__ . '/../../public/views/login.php';
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['path' => '/', 'samesite' => 'Lax']);
            session_start();
        }
        $sid = $_COOKIE['sid'] ?? '';
        if ($sid) Auth::terminateSession($sid);
        setcookie('sid', '', time() - 1, '/', '', false, true);
        session_destroy();
        header('Location: ' . BASE_PATH . '/login');
        exit;
    }
}
