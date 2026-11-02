<?php
/**
 * Gmail OAuth2 Refresh Token Generator
 * 
 * USAGE:
 * 1. Add GMAIL_CLIENT_ID and GMAIL_CLIENT_SECRET to your .env
 * 2. Add this URL as Authorized Redirect URI in Google Cloud Console:
 *    https://mail.tvserviceforhotel.com/Mail/get_gmail_token.php
 * 3. Visit this URL in your browser
 * 4. Sign in with roger@tvserviceforhotel.com
 * 5. Copy the refresh token and add it to .env as GMAIL_REFRESH_TOKEN
 * 6. DELETE this file after getting the token!
 */

// Load .env
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$clientId     = $_ENV['GMAIL_CLIENT_ID'] ?? '';
$clientSecret = $_ENV['GMAIL_CLIENT_SECRET'] ?? '';

if (!$clientId || !$clientSecret) {
    die('ERROR: Add GMAIL_CLIENT_ID and GMAIL_CLIENT_SECRET to your .env file first.');
}

// This script's URL (used as redirect URI)
$redirectUri = 'https://mail.e-voxx.com/Mail/get_gmail_token.php';

// Step 1: If no code in URL, redirect to Google's consent screen
if (!isset($_GET['code'])) {
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'https://mail.google.com/',
        'access_type'   => 'offline',
        'prompt'        => 'consent', // Forces refresh token generation
        'login_hint'    => $_ENV['APP_EMAIL'] ?? '',
    ]);
    header('Location: ' . $authUrl);
    exit;
}

// Step 2: Exchange authorization code for tokens
$code = $_GET['code'];

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $code,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

// Display result
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head><title>Gmail OAuth2 Token</title>
<style>
    body { font-family: -apple-system, sans-serif; max-width: 700px; margin: 50px auto; padding: 20px; background: #f1f5f9; }
    .card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .success { color: #059669; }
    .error { color: #dc2626; }
    code { background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 14px; word-break: break-all; }
    .token-box { background: #ecfdf5; border: 2px solid #059669; border-radius: 8px; padding: 15px; margin: 15px 0; }
    .env-line { background: #1e293b; color: #86efac; padding: 12px 16px; border-radius: 8px; font-family: monospace; margin: 10px 0; }
    .warning { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 12px; margin-top: 20px; }
</style>
</head>
<body>
<div class="card">
<?php if ($httpCode === 200 && isset($data['refresh_token'])): ?>
    <h2 class="success">&#10004; Refresh Token Obtained!</h2>
    <p>Add this line to your <code>.env</code> file:</p>
    <div class="env-line">GMAIL_REFRESH_TOKEN=<?= htmlspecialchars($data['refresh_token']) ?></div>
    
    <div class="token-box">
        <strong>Refresh Token:</strong><br>
        <code><?= htmlspecialchars($data['refresh_token']) ?></code>
    </div>
    
    <p><strong>Access Token</strong> (for testing, expires in ~1hr):<br>
    <code style="font-size:11px;"><?= htmlspecialchars($data['access_token'] ?? '') ?></code></p>
    
    <div class="warning">
        <strong>&#9888; DELETE this file after copying the token!</strong><br>
        It's a security risk to leave it accessible.
    </div>

<?php elseif ($httpCode === 200 && !isset($data['refresh_token'])): ?>
    <h2 class="error">&#9888; No Refresh Token Returned</h2>
    <p>Google only returns a refresh token on first authorization or when <code>prompt=consent</code> is used.</p>
    <p>Try revoking access at <a href="https://myaccount.google.com/permissions" target="_blank">Google Account Permissions</a> and try again.</p>
    <p>Response: <code><?= htmlspecialchars(json_encode($data)) ?></code></p>

<?php else: ?>
    <h2 class="error">&#10008; Error</h2>
    <p>HTTP <?= $httpCode ?></p>
    <p>Response: <code><?= htmlspecialchars($response) ?></code></p>
<?php endif; ?>
</div>
</body>
</html>
