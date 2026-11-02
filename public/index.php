<?php

declare(strict_types=1);

// ── Autoloader ────────────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $base = dirname(__DIR__);
    $file = $base . '/' . str_replace(['App\\', '\\'], ['app/', '/'], $class) . '.php';
    if (file_exists($file)) require $file;
});

// ── PHPMailer (lazy-loaded) ────────────────────────────────────────────────
// PHPMailer files are loaded on-demand when mail is actually sent,
// not on every request. See autoloader registration below.
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'PHPMailer\\PHPMailer\\')) {
        $file = dirname(__DIR__) . '/vendor/PHPMailer/src/' . substr($class, strrpos($class, '\\') + 1) . '.php';
        if (file_exists($file)) require $file;
    }
});

// ── Bootstrap ─────────────────────────────────────────────────────────────────
use App\Config\Env;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\StudioController;
use App\Controllers\TrackController;
use App\Controllers\TrackingController;
use App\Controllers\PdfViewerController;
use App\Controllers\NotificationController;
use App\Controllers\DeviceController;
use App\Controllers\CallbackController;

Env::load(dirname(__DIR__) . '/.env');

// Ensure server uses IST (Asia/Kolkata) for all date/time functions
date_default_timezone_set('Asia/Kolkata');

// ── Routes ────────────────────────────────────────────────────────────────────
$router = new Router();

$router->get('/',        fn() => header('Location: ' . BASE_PATH . '/login'));
$router->any('/login',   [AuthController::class, 'login']);
$router->get('/logout',  [AuthController::class, 'logout']);
$router->any('/admin',   [AdminController::class, 'index']);
$router->any('/studio',  [StudioController::class, 'index']);
$router->any('/studio/check-track-id', [StudioController::class, 'checkTrackId']);
$router->get('/track',      [TrackController::class,     'pixel']);
$router->any('/tracking',   [TrackingController::class,  'index']);
$router->get('/tracking/opens', [TrackingController::class, 'opens']);
$router->get('/tracking/latest-activity', [TrackingController::class, 'latestActivity']);
$router->post('/tracking/resend', [TrackingController::class, 'resendEmail']);

// Alias routes that bypass ad-blockers (they block URLs with "tracking" in them)
$router->get('/mail/details', [TrackingController::class, 'opens']);
$router->get('/mail/activity', [TrackingController::class, 'latestActivity']);
// Viewer tracking endpoint — receives open/close/active events from Viewer/index.php
$router->post('/pdf-viewer/track', [PdfViewerController::class, 'track']);

// SSE stream for real-time email open notifications
$router->get('/notifications/stream', [NotificationController::class, 'stream']);
$router->post('/notifications/cleanup', [NotificationController::class, 'cleanup']);

// Device Registration & Whitelisting
$router->post('/api/register', [DeviceController::class, 'register']);
$router->get('/whitelist', [DeviceController::class, 'whitelist']);

// Tracking History Actions
$router->post('/tracking/close', [TrackingController::class, 'markClosed']);
$router->post('/tracking/bounced', [TrackingController::class, 'markBounced']);

// Callback Request (public form for recipients)
$router->get('/callback',  [CallbackController::class, 'form']);
$router->post('/callback', [CallbackController::class, 'submit']);
$router->get('/api/callbacks', [CallbackController::class, 'list']);
$router->post('/api/callbacks/status', [CallbackController::class, 'updateStatus']);
$router->any('/api/scheduler/config', [StudioController::class, 'schedulerConfig']);

$router->any('/test-brevo', function() {
    require __DIR__ . '/test_brevo.php';
    exit;
});

$router->dispatch();
