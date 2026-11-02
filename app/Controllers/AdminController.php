<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

final class AdminController
{
    public static function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $ip     = Auth::ip();
        $device = Auth::device();
        $error  = '';
        $info   = '';

        $sid = $_COOKIE['sid'] ?? '';
        if (!$sid || !Auth::validateSession($sid)) {
            header('Location: ' . BASE_PATH . '/login'); exit;
        }

        $currentUser = Auth::currentUser();
        if (($currentUser['role'] ?? '') !== 'admin') {
            header('Location: ' . BASE_PATH . '/tracking'); exit;
        }

        $adminOk = !empty($_SESSION['admin_verified'])
            && (time() - ($_SESSION['admin_verified_at'] ?? 0)) < SESSION_TTL;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (isset($_POST['admin_send_otp'])) {
                $geo      = Auth::geo($ip);
                $curUser  = Database::get()
                    ->prepare("SELECT name FROM users WHERE id = (SELECT user_id FROM sessions WHERE id = ? LIMIT 1)")
                    ->execute([$sid]);
                $curName  = Database::get()
                    ->prepare("SELECT name FROM users WHERE id = (SELECT user_id FROM sessions WHERE id = ? LIMIT 1)")
                    ->execute([$sid]) ? 'Admin' : 'Admin';
                // Fetch properly
                $stmt = Database::get()->prepare("SELECT u.name FROM sessions s JOIN users u ON u.id = s.user_id WHERE s.id = ? LIMIT 1");
                $stmt->execute([$sid]);
                $curName = $stmt->fetchColumn() ?: 'Admin';

                if (Auth::sendOtp(ADMIN_EMAIL, $ip, $device, $geo, 'admin', $curName)) {
                    $_SESSION['admin_otp_pending'] = true;
                    $info = 'OTP sent to admin email.';
                } else {
                    $error = 'Failed to send OTP.';
                }
            }

            elseif (isset($_POST['admin_verify_otp'])) {
                if (Auth::isBlocked($ip)) {
                    $error = 'IP blocked.';
                } else {
                    $otp = trim($_POST['otp'] ?? '');
                    if (Auth::verifyOtp(ADMIN_EMAIL, $otp, 'admin')) {
                        Auth::clearFails($ip);
                        $_SESSION['admin_verified']    = true;
                        $_SESSION['admin_verified_at'] = time();
                        unset($_SESSION['admin_otp_pending']);
                        $adminOk = true;
                    } else {
                        Auth::recordFail($ip);
                        $error = 'Invalid or expired OTP.';
                    }
                }
            }

            elseif (isset($_POST['terminate']) && $adminOk) {
                $tsid = $_POST['terminate'];
                Auth::terminateSession($tsid);
                if ($tsid === $sid) {
                    setcookie('sid', '', time() - 1, '/', '', false, true);
                    header('Location: ' . BASE_PATH . '/login'); exit;
                }
                $info = 'Session terminated.';
            }

            elseif (isset($_POST['terminate_all']) && $adminOk) {
                Database::get()->exec("UPDATE sessions SET terminated = 1 WHERE terminated = 0");
                setcookie('sid', '', time() - 1, '/', '', false, true);
                header('Location: ' . BASE_PATH . '/login'); exit;
            }

            elseif (isset($_POST['add_user']) && $adminOk) {
                $name         = trim($_POST['new_name'] ?? '');
                $email        = strtolower(trim($_POST['new_email'] ?? ''));
                $password     = trim((string)($_POST['new_password'] ?? ''));
                $role         = in_array($_POST['new_role'] ?? '', ['admin', 'user', 'email_tracker']) ? $_POST['new_role'] : 'user';
                $otpRequired  = isset($_POST['new_otp_required']) ? 1 : 0;
                if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && ($role === 'email_tracker' ? $password !== '' : true)) {
                    try {
                        $hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
                        Database::get()->prepare("INSERT INTO users (name, email, password, role, active, otp_required, created_at) VALUES (?,?,?,?,1,?,?)")
                            ->execute([$name, $email, $hash, $role, $otpRequired, time()]);
                        $info = "User '{$name}' added as {$role}.";
                    } catch (\Exception $e) {
                        $error = 'Email already exists.';
                    }
                } else {
                    $error = 'Valid name, email, and password for tracker users are required.';
                }
            }

            elseif (isset($_POST['toggle_user']) && $adminOk) {
                Database::get()->prepare("UPDATE users SET active = CASE WHEN active=1 THEN 0 ELSE 1 END WHERE id=?")
                    ->execute([(int) $_POST['toggle_user']]);
            }

            elseif (isset($_POST['delete_user']) && $adminOk) {
                Database::get()->prepare("DELETE FROM users WHERE id=?")
                    ->execute([(int) $_POST['delete_user']]);
            }

            elseif (isset($_POST['toggle_otp_required']) && $adminOk) {
                $userId = (int)($_POST['toggle_otp_required'] ?? 0);
                $otpRequired = (int)($_POST['otp_required'] ?? 0);
                if ($userId > 0) {
                    Database::get()->prepare("UPDATE users SET otp_required = ? WHERE id = ?")
                        ->execute([$otpRequired, $userId]);
                }
            }
        }

        $requestedTab = $_GET['tab'] ?? 'sessions';
        $tab = in_array($requestedTab, ['sessions', 'login-attempts', 'login-users'], true)
            ? $requestedTab
            : 'sessions';

        $sessionPage = max(1, (int)($_GET['session_page'] ?? 1));
        $loginPage = max(1, (int)($_GET['login_page'] ?? 1));
        $perPage = 20;

        $sessionDateFrom = trim((string)($_GET['session_date_from'] ?? ''));
        $sessionDateTo   = trim((string)($_GET['session_date_to'] ?? ''));
        $loginDateFrom   = trim((string)($_GET['login_date_from'] ?? ''));
        $loginDateTo     = trim((string)($_GET['login_date_to'] ?? ''));

        $sessionWhere = [];
        $sessionParams = [];
        if ($sessionDateFrom !== '') {
            $sessionWhere[] = 's.created_at >= ?';
            $sessionParams[] = strtotime($sessionDateFrom . ' 00:00:00');
        }
        if ($sessionDateTo !== '') {
            $sessionWhere[] = 's.created_at <= ?';
            $sessionParams[] = strtotime($sessionDateTo . ' 23:59:59');
        }

        if ($adminOk) {
            $sessionSql = "
                SELECT s.*, u.name as user_name, u.role as user_role
                FROM sessions s LEFT JOIN users u ON u.id = s.user_id
            ";
            if ($sessionWhere) {
                $sessionSql .= ' WHERE ' . implode(' AND ', $sessionWhere);
            }
            $sessionSql .= ' ORDER BY s.created_at DESC';

            $sessionCountStmt = Database::get()->prepare('SELECT COUNT(*) FROM (' . $sessionSql . ')');
            $sessionCountStmt->execute($sessionParams);
            $sessionTotal = (int) $sessionCountStmt->fetchColumn();
            $sessionTotalPages = max(1, (int) ceil($sessionTotal / $perPage));
            $sessionPage = min($sessionPage, $sessionTotalPages);
            $sessionOffset = ($sessionPage - 1) * $perPage;

            $sessions = Database::get()->prepare($sessionSql . ' LIMIT ' . $perPage . ' OFFSET ' . $sessionOffset);
            $sessions->execute($sessionParams);
            $sessions = $sessions->fetchAll();
        } else {
            $sessions = [];
            $sessionTotalPages = 1;
            $sessionPage = 1;
        }

        $loginWhere = [];
        $loginParams = [];
        if ($loginDateFrom !== '') {
            $loginWhere[] = 'created_at >= ?';
            $loginParams[] = strtotime($loginDateFrom . ' 00:00:00');
        }
        if ($loginDateTo !== '') {
            $loginWhere[] = 'created_at <= ?';
            $loginParams[] = strtotime($loginDateTo . ' 23:59:59');
        }

        if ($adminOk) {
            $loginSql = 'SELECT * FROM login_log';
            if ($loginWhere) {
                $loginSql .= ' WHERE ' . implode(' AND ', $loginWhere);
            }
            $loginSql .= ' ORDER BY created_at DESC';

            $loginCountStmt = Database::get()->prepare('SELECT COUNT(*) FROM (' . $loginSql . ')');
            $loginCountStmt->execute($loginParams);
            $loginTotal = (int) $loginCountStmt->fetchColumn();
            $loginTotalPages = max(1, (int) ceil($loginTotal / $perPage));
            $loginPage = min($loginPage, $loginTotalPages);
            $loginOffset = ($loginPage - 1) * $perPage;

            $logs = Database::get()->prepare($loginSql . ' LIMIT ' . $perPage . ' OFFSET ' . $loginOffset);
            $logs->execute($loginParams);
            $logs = $logs->fetchAll();
        } else {
            $logs = [];
            $loginTotalPages = 1;
            $loginPage = 1;
        }

        $users = $adminOk ? Database::get()->query("
            SELECT * FROM users ORDER BY created_at DESC
        ")->fetchAll() : [];

        require __DIR__ . '/../../public/views/admin.php';
    }
}
