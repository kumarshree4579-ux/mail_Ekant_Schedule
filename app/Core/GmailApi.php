<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight Gmail REST API wrapper using cURL.
 * Uses OAuth2 refresh token to obtain access tokens.
 * 
 * Required .env keys:
 *   GMAIL_CLIENT_ID
 *   GMAIL_CLIENT_SECRET
 *   GMAIL_REFRESH_TOKEN
 */
final class GmailApi
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_BASE  = 'https://gmail.googleapis.com/gmail/v1/users/me';

    private static ?string $accessToken = null;
    private static int $tokenExpiry = 0;

    /**
     * Get a valid access token, refreshing if necessary.
     */
    private static function getAccessToken(): ?string
    {
        if (self::$accessToken && time() < self::$tokenExpiry) {
            return self::$accessToken;
        }

        $clientId     = $_ENV['GMAIL_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV['GMAIL_CLIENT_SECRET'] ?? '';
        $refreshToken = $_ENV['GMAIL_REFRESH_TOKEN'] ?? '';

        if (!$clientId || !$clientSecret || !$refreshToken) {
            self::$lastError = 'No access token - missing env: '
                . (!$clientId ? 'CLIENT_ID ' : '')
                . (!$clientSecret ? 'CLIENT_SECRET ' : '')
                . (!$refreshToken ? 'REFRESH_TOKEN' : '');
            return null;
        }

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type'    => 'refresh_token',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT    => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            self::$lastError = "No access token - cURL: {$curlErr}";
            return null;
        }

        if ($httpCode !== 200 || !$response) {
            $errData = json_decode($response ?: '{}', true);
            $errMsg = $errData['error_description'] ?? $errData['error'] ?? "HTTP {$httpCode}";
            self::$lastError = "No access token - refresh failed: {$errMsg}";
            return null;
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            self::$lastError = 'No access token - missing in response';
            return null;
        }

        self::$accessToken = $data['access_token'];
        self::$tokenExpiry = time() + (int)($data['expires_in'] ?? 3500) - 60;
        self::$lastError = '';

        return self::$accessToken;
    }

    /**
     * Search for a message in Sent by Message-ID header.
     * Returns the Gmail message ID (string) or null.
     */
    public static function findSentByMessageId(string $messageId, int $retries = 4): ?string
    {
        $token = self::getAccessToken();
        if (!$token) return null;

        // Gmail search uses rfc822msgid with angle brackets included
        $query = urlencode("in:sent rfc822msgid:" . $messageId);

        $url = self::API_BASE . "/messages?q={$query}&maxResults=1";

        while ($retries > 0) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (!empty($data['messages'][0]['id'])) {
                    return $data['messages'][0]['id'];
                }
            }

            $retries--;
            if ($retries > 0) sleep(3);
        }

        return null;
    }

    /**
     * Permanently delete a message by its Gmail message ID.
     * This bypasses Trash — the message is gone forever.
     */
    public static function deleteMessage(string $gmailMsgId): bool
    {
        $token = self::getAccessToken();
        if (!$token) return false;

        $url = self::API_BASE . "/messages/{$gmailMsgId}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 204 = deleted, 404 = already gone (both are success)
        return in_array($httpCode, [204, 200, 404], true);
    }

    /**
     * Trash a message (move to Trash) by its Gmail message ID.
     * Safer than permanent delete — can be recovered within 30 days.
     */
    public static function trashMessage(string $gmailMsgId): bool
    {
        $token = self::getAccessToken();
        if (!$token) return false;

        $url = self::API_BASE . "/messages/{$gmailMsgId}/trash";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Length: 0',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Send a raw MIME email via Gmail API.
     * Returns the Gmail message ID on success (for immediate trash/delete), or null on failure.
     * The message is automatically saved to Sent by Gmail.
     */
    public static function sendRawEmail(string $rawMime): ?string
    {
        $token = self::getAccessToken();
        if (!$token) {
            // $lastError already set by getAccessToken with details
            if (!self::$lastError) self::$lastError = 'No access token';
            return null;
        }

        // Gmail API requires URL-safe base64 encoding of the raw MIME
        $encoded = rtrim(strtr(base64_encode($rawMime), '+/', '-_'), '=');

        $url = self::API_BASE . '/messages/send';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['raw' => $encoded]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            self::$lastError = "cURL: {$curlErr}";
            return null;
        }

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            self::$lastError = '';
            return $data['id'] ?? null;
        }

        // Capture error details
        $errData = json_decode($response ?: '{}', true);
        $errMsg  = $errData['error']['message'] ?? "HTTP {$httpCode}";
        self::$lastError = $errMsg;
        return null;
    }

    /** @var string Last error message for debugging */
    public static string $lastError = '';

    /**
     * Get the last error message.
     */
    public static function getLastError(): string
    {
        return self::$lastError;
    }

    /**
     * Check if Gmail API credentials are configured.
     */
    public static function isConfigured(): bool
    {
        return !empty($_ENV['GMAIL_CLIENT_ID'])
            && !empty($_ENV['GMAIL_CLIENT_SECRET'])
            && !empty($_ENV['GMAIL_REFRESH_TOKEN']);
    }
}
