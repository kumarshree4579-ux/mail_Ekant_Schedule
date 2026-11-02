<?php

declare(strict_types=1);

namespace App\Core;

final class SchedulerConfig
{
    private const DEFAULT_48H_TIME = '10:00';
    private const DEFAULT_72H_TIME = '14:30';

    public static function load(\PDO $db, string $base): array
    {
        $config = $db->query('SELECT * FROM scheduler_config WHERE id = 1')->fetch();
        if ($config !== false) {
            return $config;
        }

        $legacy = [];
        $legacyPath = $base . '/data/scheduler.json';
        if (is_file($legacyPath)) {
            $contents = @file_get_contents($legacyPath);
            if ($contents === false) {
                throw new \RuntimeException('Unable to read legacy scheduler configuration: ' . $legacyPath);
            }

            $decoded = json_decode($contents, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('Legacy scheduler configuration is invalid: ' . $legacyPath);
            }
            $legacy = $decoded;
        }

        $stmt = $db->prepare(
            'INSERT OR IGNORE INTO scheduler_config
                (id, scheduler_48h_time, scheduler_72h_time, last_run_48h, last_run_72h)
             VALUES (1, ?, ?, ?, ?)'
        );
        $stmt->execute([
            self::validTime($legacy['scheduler_48h_time'] ?? null) ?? self::DEFAULT_48H_TIME,
            self::validTime($legacy['scheduler_72h_time'] ?? null) ?? self::DEFAULT_72H_TIME,
            is_string($legacy['last_run_48h'] ?? null) ? $legacy['last_run_48h'] : '',
            is_string($legacy['last_run_72h'] ?? null) ? $legacy['last_run_72h'] : '',
        ]);

        $config = $db->query('SELECT * FROM scheduler_config WHERE id = 1')->fetch();
        if ($config === false) {
            throw new \RuntimeException('Unable to initialize scheduler configuration.');
        }

        return $config;
    }

    public static function saveTimes(\PDO $db, string $time48, string $time72): void
    {
        $db->prepare(
            'INSERT OR IGNORE INTO scheduler_config (id) VALUES (1)'
        )->execute();
        $db->prepare(
            'UPDATE scheduler_config
             SET scheduler_48h_time = ?, scheduler_72h_time = ?
             WHERE id = 1'
        )->execute([$time48, $time72]);
    }

    public static function markRun(\PDO $db, string $type, string $date): void
    {
        if ($type === '48h') {
            $column = 'last_run_48h';
        } elseif ($type === '72h') {
            $column = 'last_run_72h';
        } else {
            throw new \InvalidArgumentException('Unknown scheduler type: ' . $type);
        }

        $db->prepare("UPDATE scheduler_config SET {$column} = ? WHERE id = 1")->execute([$date]);
    }

    public static function validTime(mixed $value): ?string
    {
        if (!is_string($value) || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
