<?php
/**
 * App Diagnostics — Single-page health check & performance profiler.
 * Access: /Mail/diagnose.php (or via route if added)
 * 
 * Checks: PHP config, SQLite connectivity, SMTP reachability, 
 *         disk I/O, DNS resolution, response time per component.
 * 
 * SECURITY: Delete this file after debugging, or protect it with IP check below.
 */
 
declare(strict_types=1);
 
// ── IP Restriction (only allow your known IPs) ──────────────────────────────
$allowedIps = ['115.246.109.187', '103.188.127.180', '::1', '127.0.0.1'];
$clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] 
    ?? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0] 
    ?? $_SERVER['REMOTE_ADDR'] 
    ?? '';
$clientIp = trim($clientIp);
 
if (!in_array($clientIp, $allowedIps, true)) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}
 
// ── Bootstrap (minimal) ─────────────────────────────────────────────────────
$startTime = microtime(true);
$results = [];
$errors = [];
 
function timeIt(string $label, callable $fn): array {
    $t0 = microtime(true);
    try {
        $result = $fn();
        $ms = round((microtime(true) - $t0) * 1000, 2);
        return ['label' => $label, 'status' => 'OK', 'ms' => $ms, 'detail' => $result];
    } catch (\Throwable $e) {
        $ms = round((microtime(true) - $t0) * 1000, 2);
        return ['label' => $label, 'status' => 'FAIL', 'ms' => $ms, 'detail' => $e->getMessage()];
    }
}
 
// ── 1. PHP Environment ──────────────────────────────────────────────────────
$results[] = [
    'label' => 'PHP Environment',
    'status' => 'OK',
    'ms' => 0,
    'detail' => implode(' | ', [
        'PHP ' . PHP_VERSION,
        'SAPI: ' . php_sapi_name(),
        'OS: ' . PHP_OS,
        'Memory Limit: ' . ini_get('memory_limit'),
        'Max Exec Time: ' . ini_get('max_execution_time') . 's',
        'OPcache: ' . (function_exists('opcache_get_status') && opcache_get_status() ? 'ON' : 'OFF'),
        'Timezone: ' . date_default_timezone_get(),
    ])
];
 
// ── 2. Required Extensions ──────────────────────────────────────────────────
$requiredExts = ['pdo', 'pdo_sqlite', 'openssl', 'mbstring', 'json'];
$missing = array_filter($requiredExts, fn($ext) => !extension_loaded($ext));
$results[] = [
    'label' => 'PHP Extensions',
    'status' => empty($missing) ? 'OK' : 'FAIL',
    'ms' => 0,
    'detail' => empty($missing) 
        ? 'All required extensions loaded: ' . implode(', ', $requiredExts)
        : 'MISSING: ' . implode(', ', $missing)
];
 
// ── 3. .env File ────────────────────────────────────────────────────────────
$envPath = dirname(__DIR__) . '/.env';
$results[] = timeIt('.env File', function() use ($envPath) {
    if (!file_exists($envPath)) return 'NOT FOUND at ' . $envPath;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $keys = [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key] = explode('=', $line, 2);
        $keys[] = trim($key);
    }
    return count($keys) . ' keys defined: ' . implode(', ', $keys);
});
 
// ── 4. SQLite Database ──────────────────────────────────────────────────────
$dbPath = dirname(__DIR__) . '/storage/app.sqlite';
$results[] = timeIt('SQLite Connection', function() use ($dbPath) {
    if (!file_exists($dbPath)) return 'DB NOT FOUND: ' . $dbPath;
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $mode = $pdo->query("PRAGMA journal_mode")->fetchColumn();
    $size = round(filesize($dbPath) / 1024 / 1024, 2);
    return "Connected | Size: {$size}MB | Journal: {$mode}";
});
 
// ── 5. SQLite WAL Mode ──────────────────────────────────────────────────────
$results[] = timeIt('SQLite WAL Enable', function() use ($dbPath) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $result = $pdo->query("PRAGMA journal_mode = WAL")->fetchColumn();
    return "journal_mode = {$result}";
});
 
// ── 6. SQLite Read Performance ──────────────────────────────────────────────
$results[] = timeIt('SQLite Read (email_sends COUNT)', function() use ($dbPath) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->exec("PRAGMA busy_timeout = 5000");
    $count = $pdo->query("SELECT COUNT(*) FROM email_sends")->fetchColumn();
    return "{$count} rows in email_sends";
});
 
// ── 7. SQLite Write Performance ─────────────────────────────────────────────
$results[] = timeIt('SQLite Write (temp insert + delete)', function() use ($dbPath) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->exec("PRAGMA journal_mode = WAL");
    $pdo->exec("PRAGMA busy_timeout = 5000");
    $pdo->exec("CREATE TABLE IF NOT EXISTS _diag_test (id INTEGER PRIMARY KEY, ts INTEGER)");
    $pdo->exec("INSERT INTO _diag_test (ts) VALUES (" . time() . ")");
    $pdo->exec("DELETE FROM _diag_test");
    $pdo->exec("DROP TABLE _diag_test");
    return "Write OK";
});
 
// ── 8. SQLite Lock Test ─────────────────────────────────────────────────────
$results[] = timeIt('SQLite Lock Contention', function() use ($dbPath) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->exec("PRAGMA busy_timeout = 3000");
    $pdo->exec("PRAGMA journal_mode = WAL");
    // Try a write that could contend with other processes
    $pdo->beginTransaction();
    $pdo->exec("CREATE TABLE IF NOT EXISTS _lock_test (id INTEGER PRIMARY KEY)");
    $pdo->exec("INSERT OR REPLACE INTO _lock_test VALUES (1)");
    $pdo->commit();
    $pdo->exec("DROP TABLE IF EXISTS _lock_test");
    return "No lock contention detected";
});
 
// ── 9. Schema Version Check ─────────────────────────────────────────────────
$versionFile = dirname(__DIR__) . '/storage/.schema_version';
$results[] = [
    'label' => 'Schema Version File',
    'status' => file_exists($versionFile) ? 'OK' : 'WARN',
    'ms' => 0,
    'detail' => file_exists($versionFile) 
        ? 'Version: ' . trim(file_get_contents($versionFile))
        : 'NOT FOUND — migrations will run on every request until this is created!'
];
 
// ── 10. DNS Resolution ──────────────────────────────────────────────────────
$results[] = timeIt('DNS: smtp.gmail.com', function() {
    $ip = gethostbyname('smtp.gmail.com');
    if ($ip === 'smtp.gmail.com') return 'FAILED to resolve';
    return "Resolved to {$ip}";
});
 
$results[] = timeIt('DNS: smtp-relay.brevo.com', function() {
    $ip = gethostbyname('smtp-relay.brevo.com');
    if ($ip === 'smtp-relay.brevo.com') return 'FAILED to resolve';
    return "Resolved to {$ip}";
});
 
// ── 11. SMTP Connectivity (no auth, just TCP connect) ───────────────────────
$results[] = timeIt('TCP: smtp.gmail.com:587', function() {
    $sock = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 3);
    if (!$sock) return "FAILED: {$errstr} (errno {$errno})";
    $banner = fgets($sock, 256);
    fclose($sock);
    return "Connected — Banner: " . trim($banner);
});
 
$results[] = timeIt('TCP: smtp-relay.brevo.com:587', function() {
    $sock = @fsockopen('smtp-relay.brevo.com', 587, $errno, $errstr, 3);
    if (!$sock) return "FAILED: {$errstr} (errno {$errno})";
    $banner = fgets($sock, 256);
    fclose($sock);
    return "Connected — Banner: " . trim($banner);
});
 
// ── 12. External HTTP (ip-api.com used by Auth::geo) ────────────────────────
$results[] = timeIt('HTTP: ip-api.com (geo lookup)', function() {
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $resp = @file_get_contents('http://ip-api.com/json/8.8.8.8?fields=status', false, $ctx);
    if (!$resp) return 'FAILED or timed out (>2s)';
    return "Response: {$resp}";
});
 
// ── 13. Disk I/O ────────────────────────────────────────────────────────────
$results[] = timeIt('Disk Write (storage dir)', function() use ($dbPath) {
    $testFile = dirname($dbPath) . '/_diag_write_test.tmp';
    $data = str_repeat('x', 1024 * 100); // 100KB
    file_put_contents($testFile, $data);
    $readBack = file_get_contents($testFile);
    unlink($testFile);
    if (strlen($readBack) !== 102400) return 'MISMATCH in read-back';
    return "100KB write+read OK";
});
 
// ── 14. File Permissions ────────────────────────────────────────────────────
$results[] = [
    'label' => 'File Permissions',
    'status' => is_writable(dirname($dbPath)) ? 'OK' : 'FAIL',
    'ms' => 0,
    'detail' => implode(' | ', [
        'storage/ writable: ' . (is_writable(dirname($dbPath)) ? 'YES' : 'NO'),
        'app.sqlite writable: ' . (is_writable($dbPath) ? 'YES' : 'NO'),
        'Owner: ' . (function_exists('posix_getpwuid') ? (posix_getpwuid(fileowner($dbPath))['name'] ?? 'unknown') : 'N/A'),
    ])
];
 
// ── 15. fastcgi_finish_request availability ─────────────────────────────────
$results[] = [
    'label' => 'fastcgi_finish_request()',
    'status' => function_exists('fastcgi_finish_request') ? 'OK' : 'WARN',
    'ms' => 0,
    'detail' => function_exists('fastcgi_finish_request')
        ? 'Available — async SMTP after response close will work'
        : 'NOT available — tracking pixel may still block on SMTP (consider using php-fpm)'
];
 
// ── 16. Active DB Connections (sessions) ────────────────────────────────────
$results[] = timeIt('Active Sessions', function() use ($dbPath) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $active = $pdo->query("SELECT COUNT(*) FROM sessions WHERE terminated = 0")->fetchColumn();
    $total = $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn();
    return "Active: {$active} | Total: {$total}";
});
 
// ── 17. Table Sizes ─────────────────────────────────────────────────────────
$results[] = timeIt('Table Row Counts', function() use ($dbPath) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $tables = ['email_sends', 'email_opens', 'attachment_opens', 'open_notifications', 'sessions', 'login_log'];
    $counts = [];
    foreach ($tables as $t) {
        try {
            $c = $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
            $counts[] = "{$t}: {$c}";
        } catch (\Throwable) {
            $counts[] = "{$t}: ERROR";
        }
    }
    return implode(' | ', $counts);
});
 
// ── 18. Slow Query Simulation (tracking page main query) ────────────────────
$results[] = timeIt('Tracking Page Query (today)', function() use ($dbPath) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->exec("PRAGMA journal_mode = WAL");
    $pdo->exec("PRAGMA busy_timeout = 5000");
    $pdo->exec("PRAGMA cache_size = -8000");
    
    $today = strtotime(date('Y-m-d') . ' 00:00:00');
    $tonight = strtotime(date('Y-m-d') . ' 23:59:59');
    
    $stmt = $pdo->prepare("
        SELECT COUNT(s.id)
        FROM email_sends s
        LEFT JOIN (
            SELECT tracking_id, COUNT(id) as open_count FROM email_opens WHERE is_internal = 0 GROUP BY tracking_id
        ) o ON o.tracking_id = s.tracking_id
        LEFT JOIN (
            SELECT tracking_id, COUNT(id) as attach_open_count FROM attachment_opens GROUP BY tracking_id
        ) a ON a.tracking_id = s.tracking_id
        WHERE s.sent_at BETWEEN ? AND ?
    ");
    $stmt->execute([$today, $tonight]);
    $count = $stmt->fetchColumn();
    return "Query returned {$count} results for today";
});
 
// ── 19. Memory Usage ────────────────────────────────────────────────────────
$results[] = [
    'label' => 'Memory Usage',
    'status' => 'OK',
    'ms' => 0,
    'detail' => 'Current: ' . round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB | Peak: ' . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
];
 
// ── Total Time ──────────────────────────────────────────────────────────────
$totalMs = round((microtime(true) - $startTime) * 1000, 2);
 
// ── Render ──────────────────────────────────────────────────────────────────
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Diagnostics</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, 'Segoe UI', Roboto, monospace; background: #0f172a; color: #e2e8f0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { font-size: 24px; margin-bottom: 8px; color: #f1f5f9; }
        .subtitle { color: #94a3b8; margin-bottom: 24px; font-size: 14px; }
        .summary { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .summary-card { background: #1e293b; border-radius: 8px; padding: 16px 20px; flex: 1; min-width: 150px; border: 1px solid #334155; }
        .summary-card .value { font-size: 28px; font-weight: bold; }
        .summary-card .label { font-size: 12px; color: #94a3b8; margin-top: 4px; }
        .ok .value { color: #4ade80; }
        .warn .value { color: #fbbf24; }
        .fail .value { color: #f87171; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 8px; overflow: hidden; border: 1px solid #334155; }
        th { background: #334155; color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 12px; text-align: left; }
        td { padding: 10px 12px; border-top: 1px solid #334155; font-size: 13px; vertical-align: top; }
        tr:hover td { background: #253349; }
        .status-ok { color: #4ade80; font-weight: 600; }
        .status-fail { color: #f87171; font-weight: 600; }
        .status-warn { color: #fbbf24; font-weight: 600; }
        .ms { color: #94a3b8; font-size: 12px; }
        .ms-slow { color: #fbbf24; }
        .ms-critical { color: #f87171; }
        .detail { color: #cbd5e1; word-break: break-all; max-width: 500px; }
        .total { margin-top: 16px; text-align: right; color: #94a3b8; font-size: 13px; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #334155; color: #64748b; font-size: 12px; text-align: center; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-ok { background: #064e3b; color: #4ade80; }
        .badge-fail { background: #450a0a; color: #f87171; }
        .badge-warn { background: #451a03; color: #fbbf24; }
    </style>
</head>
<body>
<div class="container">
    <h1>&#9881; App Diagnostics</h1>
    <p class="subtitle">
        Server: <?= gethostname() ?> | IP: <?= $clientIp ?> | 
        Time: <?= date('Y-m-d H:i:s T') ?> | Total: <?= $totalMs ?>ms
    </p>
 
    <?php
    $okCount = count(array_filter($results, fn($r) => $r['status'] === 'OK'));
    $failCount = count(array_filter($results, fn($r) => $r['status'] === 'FAIL'));
    $warnCount = count(array_filter($results, fn($r) => $r['status'] === 'WARN'));
    ?>
    <div class="summary">
        <div class="summary-card ok"><div class="value"><?= $okCount ?></div><div class="label">Passed</div></div>
        <div class="summary-card warn"><div class="value"><?= $warnCount ?></div><div class="label">Warnings</div></div>
        <div class="summary-card fail"><div class="value"><?= $failCount ?></div><div class="label">Failed</div></div>
        <div class="summary-card"><div class="value"><?= $totalMs ?>ms</div><div class="label">Total Time</div></div>
    </div>
 
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Check</th>
                <th>Status</th>
                <th>Time</th>
                <th>Detail</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $i => $r): ?>
            <?php
            $statusClass = match($r['status']) {
                'OK' => 'status-ok',
                'FAIL' => 'status-fail',
                'WARN' => 'status-warn',
                default => '',
            };
            $badgeClass = match($r['status']) {
                'OK' => 'badge-ok',
                'FAIL' => 'badge-fail',
                'WARN' => 'badge-warn',
                default => '',
            };
            $msClass = '';
            if ($r['ms'] > 1000) $msClass = 'ms-critical';
            elseif ($r['ms'] > 200) $msClass = 'ms-slow';
            ?>
            <tr>
                <td style="color:#64748b"><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($r['label']) ?></strong></td>
                <td><span class="badge <?= $badgeClass ?>"><?= $r['status'] ?></span></td>
                <td class="ms <?= $msClass ?>"><?= $r['ms'] > 0 ? $r['ms'] . 'ms' : '—' ?></td>
                <td class="detail"><?= htmlspecialchars((string)$r['detail']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
 
    <div class="footer">
        &#9888; Delete this file after debugging. It exposes server internals.<br>
        Restricted to IPs: <?= implode(', ', $allowedIps) ?>
    </div>
</div>
</body>
</html>