<?php

declare(strict_types=1);

namespace App\Config;

final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) return;

        if (!file_exists($path)) {
            throw new \RuntimeException(".env file not found at: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }

        self::$loaded = true;

        // Validate required keys
        $required = ['APP_EMAIL', 'APP_PASSWORD', 'ADMIN_EMAIL'];
        foreach ($required as $key) {
            if (empty($_ENV[$key])) {
                throw new \RuntimeException("Missing required .env key: {$key}");
            }
        }

        // Define typed constants
        define('APP_EMAIL',   self::get('APP_EMAIL'));
        define('APP_PASSWORD',self::get('APP_PASSWORD'));
        define('ADMIN_EMAIL', self::get('ADMIN_EMAIL'));
        define('OTP_EXPIRY',  (int) self::get('OTP_EXPIRY',  '300'));
        define('SESSION_TTL', (int) self::get('SESSION_TTL', '1800'));
        define('REFRESH_TTL', (int) self::get('REFRESH_TTL', '604800'));
        define('MAX_FAILS',   (int) self::get('MAX_FAILS',   '5'));
        define('BLOCK_TIME',  (int) self::get('BLOCK_TIME',  '900'));
        define('DB_PATH',     dirname(__DIR__, 2) . '/storage/app.sqlite');
        define('CLD_CLOUD',  self::get('CLOUDINARY_CLOUD',  ''));
        define('CLD_PRESET', self::get('CLOUDINARY_PRESET', ''));
        define('CLD_API_KEY',    self::get('CLOUDINARY_API_KEY', ''));
        define('CLD_API_SECRET', self::get('CLOUDINARY_API_SECRET', ''));
        define('APP_URL',    rtrim(self::get('APP_URL', 'http://localhost/Mail'), '/'));
        define('BASE_PATH',  rtrim(self::get('BASE_PATH', '/Mail'), '/'));
        define('VIEWER_URL', rtrim(self::get('VIEWER_URL', 'http://localhost/Mail/Viewer/'), '/') . '/');
    }

    public static function get(string $key, string $default = ''): string
    {
        return $_ENV[$key] ?? $default;
    }
}
