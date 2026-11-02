<?php

declare(strict_types=1);

namespace App\Core;

final class Database
{
    private static ?\PDO $pdo = null;
    private static bool $migrated = false;

    public static function get(): \PDO
    {
        if (self::$pdo) return self::$pdo;

        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        $isNewDb = !file_exists(DB_PATH) || filesize(DB_PATH) === 0;

        self::$pdo = new \PDO('sqlite:' . DB_PATH);
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        // Enable WAL mode for better concurrency (readers don't block writers)
        self::$pdo->exec("PRAGMA journal_mode = WAL");
        // Reduce fsync calls — safe for non-critical data (small risk on power loss)
        self::$pdo->exec("PRAGMA synchronous = NORMAL");
        // Increase cache for fewer disk reads
        self::$pdo->exec("PRAGMA cache_size = -8000"); // 8MB
        // Busy timeout — wait up to 5s instead of failing immediately on lock
        self::$pdo->exec("PRAGMA busy_timeout = 5000");

        // Only run migrations once per process AND only when needed
        if (!self::$migrated) {
            if ($isNewDb) {
                // Brand new DB: run full migration
                self::migrate();
            } else {
                // Existing DB: check a version marker file to skip redundant migrations
                self::migrateIfNeeded();
            }
            self::$migrated = true;
        }

        return self::$pdo;
    }

    /**
     * Only run migrations if the schema version marker is outdated.
     * This avoids 40+ PRAGMA/CREATE calls on every single request.
     */
    private static function migrateIfNeeded(): void
    {
        $versionFile = dirname(DB_PATH) . '/.schema_version';
        $currentVersion = 12; // Bump this when you add new migrations

        $existingVersion = 0;
        if (file_exists($versionFile)) {
            $existingVersion = (int) file_get_contents($versionFile);
        }

        if ($existingVersion < $currentVersion) {
            self::migrate();
            file_put_contents($versionFile, (string) $currentVersion);
        }
    }

    private static function migrate(): void
    {
        $db = self::$pdo;

        // ── Create tables ──────────────────────────────────────────────────────
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                name         TEXT    NOT NULL,
                email        TEXT    NOT NULL UNIQUE,
                password     TEXT    DEFAULT NULL,
                role         TEXT    NOT NULL DEFAULT 'user',
                active       INTEGER NOT NULL DEFAULT 1,
                otp_required INTEGER NOT NULL DEFAULT 1,
                created_at   INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS sessions (
                id          TEXT    PRIMARY KEY,
                user_id     INTEGER,
                email       TEXT    NOT NULL,
                ip          TEXT,
                device      TEXT,
                lat         REAL,
                lng         REAL,
                created_at  INTEGER NOT NULL,
                last_active INTEGER NOT NULL,
                refresh_exp INTEGER NOT NULL,
                terminated  INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS login_log (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                email      TEXT,
                ip         TEXT,
                device     TEXT,
                lat        REAL,
                lng        REAL,
                success    INTEGER NOT NULL DEFAULT 0,
                created_at INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS blocked_ips (
                ip         TEXT    PRIMARY KEY,
                fail_count INTEGER NOT NULL DEFAULT 0,
                blocked_at INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS otp_store (
                token_key  TEXT    PRIMARY KEY,
                otp_hash   TEXT    NOT NULL,
                expires_at INTEGER NOT NULL,
                ip         TEXT
            );

            CREATE TABLE IF NOT EXISTS allowed_ips (
                ip         TEXT    PRIMARY KEY,
                added_at   INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS device_registration_tokens (
                token      TEXT    PRIMARY KEY,
                ip         TEXT,
                platform   TEXT,
                created_at INTEGER NOT NULL
            );
        ");

        // ── Email tracking + PDF/Asset tables ─────────────────────────────────
        $db->exec("
            CREATE TABLE IF NOT EXISTS email_sends (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                tracking_id     TEXT    NOT NULL UNIQUE,
                recipients      TEXT    NOT NULL,
                recipient_email TEXT    NOT NULL DEFAULT '',
                subject         TEXT    NOT NULL,
                sent_at         INTEGER NOT NULL,
                is_draft        INTEGER NOT NULL DEFAULT 0,
                body            TEXT    NOT NULL DEFAULT '',
                studio_fields   TEXT,
                custom_id       TEXT
            );
            CREATE TABLE IF NOT EXISTS email_opens (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                tracking_id     TEXT    NOT NULL,
                recipient_email TEXT,
                is_internal     INTEGER NOT NULL DEFAULT 0,
                opened_at       INTEGER NOT NULL,
                ip              TEXT,
                user_agent      TEXT
            );
            CREATE TABLE IF NOT EXISTS assets (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                url        TEXT    NOT NULL UNIQUE,
                public_id  TEXT    NOT NULL DEFAULT '',
                added_at   INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS pdf_assets (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                url        TEXT    NOT NULL UNIQUE,
                name       TEXT    NOT NULL DEFAULT '',
                added_at   INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS pdf_link_tokens (
                token       TEXT    PRIMARY KEY,
                url         TEXT    NOT NULL,
                name        TEXT    NOT NULL DEFAULT '',
                tracking_id TEXT,
                send_id     INTEGER,
                created_at  INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS studio_options (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                type            TEXT    NOT NULL,
                name            TEXT    NOT NULL,
                parent_provider TEXT    DEFAULT NULL,
                parent_system   TEXT    DEFAULT NULL,
                UNIQUE(type, name, parent_provider, parent_system)
            );
            CREATE TABLE IF NOT EXISTS scheduler_config (
                id                  INTEGER PRIMARY KEY CHECK (id = 1),
                scheduler_48h_time TEXT NOT NULL DEFAULT '10:00',
                scheduler_72h_time TEXT NOT NULL DEFAULT '14:30',
                last_run_48h       TEXT NOT NULL DEFAULT '',
                last_run_72h       TEXT NOT NULL DEFAULT ''
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS attachment_links (
                token       TEXT PRIMARY KEY,
                tracking_id TEXT,
                send_id     INTEGER,
                url         TEXT NOT NULL,
                filename    TEXT NOT NULL DEFAULT '',
                created_at  INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS attachment_opens (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                token           TEXT,
                tracking_id     TEXT,
                send_id         INTEGER,
                recipient_email TEXT,
                event_type      TEXT NOT NULL DEFAULT 'open',
                page            INTEGER,
                duration_ms     INTEGER,
                opened_at       INTEGER NOT NULL,
                ip              TEXT,
                user_agent      TEXT
            );
            CREATE INDEX IF NOT EXISTS idx_attach_token ON attachment_opens(token);
            CREATE INDEX IF NOT EXISTS idx_attach_tid   ON attachment_opens(tracking_id);
        ");

        // ── Bulk List Management ──────────────────────────────────────────────
        $db->exec("
            CREATE TABLE IF NOT EXISTS bulk_lists (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT    NOT NULL UNIQUE,
                created_at INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS bulk_list_items (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id    INTEGER NOT NULL,
                email      TEXT    NOT NULL,
                data       TEXT    NOT NULL,
                status     TEXT    NOT NULL DEFAULT 'pending',
                error_msg  TEXT,
                created_at INTEGER NOT NULL,
                FOREIGN KEY(list_id) REFERENCES bulk_lists(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_bli_list_id ON bulk_list_items(list_id);
        ");

        // ── Migrations for existing DBs ────────────────────────────────────────
        self::addColumnIfMissing('email_sends',  'recipient_email', "TEXT NOT NULL DEFAULT ''");
        self::addColumnIfMissing('email_sends',  'body', "TEXT NOT NULL DEFAULT ''");
        self::addColumnIfMissing('email_sends',  'studio_fields', "TEXT");
        self::addColumnIfMissing('email_sends',  'custom_id', "TEXT");
        self::addColumnIfMissing('users',      'role',         "TEXT NOT NULL DEFAULT 'user'");
        self::addColumnIfMissing('users',      'password',     'TEXT');
        self::addColumnIfMissing('users',      'otp_required', "INTEGER NOT NULL DEFAULT 1");
        self::addColumnIfMissing('sessions',   'user_id',      'INTEGER');
        self::addColumnIfMissing('email_opens','recipient_email', 'TEXT');
        self::addColumnIfMissing('email_opens','is_internal',    "INTEGER NOT NULL DEFAULT 0");
        self::addColumnIfMissing('email_opens','opened_at',      "INTEGER NOT NULL DEFAULT 0");
        self::addColumnIfMissing('email_opens','ip',             'TEXT');
        self::addColumnIfMissing('email_opens','user_agent',     'TEXT');
        self::addColumnIfMissing('email_opens','tracking_id',     'TEXT NOT NULL');

        // Ensure attachment_opens has columns for scroll percent and meta payload
        self::addColumnIfMissing('attachment_opens', 'percent', 'INTEGER');
        self::addColumnIfMissing('attachment_opens', 'meta', 'TEXT');
        
        // Add tracking_id and send_id to pdf_link_tokens
        self::addColumnIfMissing('pdf_link_tokens', 'tracking_id', 'TEXT');
        self::addColumnIfMissing('pdf_link_tokens', 'send_id', 'INTEGER');

        // Add pin support to pdf_assets
        self::addColumnIfMissing('pdf_assets', 'is_pinned', 'INTEGER NOT NULL DEFAULT 0');

        // Add closed status columns to email_sends
        self::addColumnIfMissing('email_sends', 'is_closed', 'INTEGER NOT NULL DEFAULT 0');
        self::addColumnIfMissing('email_sends', 'closed_at', 'TEXT');
        self::addColumnIfMissing('email_sends', 'closed_remark', 'TEXT');
        self::addColumnIfMissing('email_sends', 'closed_by', 'TEXT');

        // Add bounce status column to email_sends
        self::addColumnIfMissing('email_sends', 'is_bounced', 'INTEGER NOT NULL DEFAULT 0');

        $db->exec("CREATE INDEX IF NOT EXISTS idx_opens_tid ON email_opens(tracking_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_opens_recipient ON email_opens(recipient_email)");

        // Critical indexes for TrackingController performance
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sends_sent_at ON email_sends(sent_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sends_custom_id ON email_sends(custom_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_attach_opens_recipient ON attachment_opens(recipient_email)");

        // ── SSE Notification queue ────────────────────────────────────────────
        $db->exec("
            CREATE TABLE IF NOT EXISTS open_notifications (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                tracking_id     TEXT    NOT NULL,
                recipient_email TEXT    NOT NULL,
                subject         TEXT    NOT NULL DEFAULT '',
                opened_at       INTEGER NOT NULL,
                ip              TEXT,
                seen            INTEGER NOT NULL DEFAULT 0,
                created_at      INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_open_notif_seen ON open_notifications(seen, created_at);
        ");

        // ── Callback Requests ─────────────────────────────────────────────────
        $db->exec("
            CREATE TABLE IF NOT EXISTS callback_requests (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                tracking_id     TEXT,
                recipient_email TEXT,
                recipient_name  TEXT NOT NULL DEFAULT '',
                callback_number TEXT NOT NULL,
                schedule_date   TEXT NOT NULL,
                schedule_time   TEXT NOT NULL,
                timezone        TEXT NOT NULL DEFAULT 'EST',
                notes           TEXT NOT NULL DEFAULT '',
                status          TEXT NOT NULL DEFAULT 'pending',
                created_at      INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_callback_tid ON callback_requests(tracking_id);
            CREATE INDEX IF NOT EXISTS idx_callback_status ON callback_requests(status);
        ");

        // Drop otp_store if it has old 'email' column
        $cols = $db->query("PRAGMA table_info(otp_store)")->fetchAll();
        $hasTokenKey = array_filter($cols, fn($c) => $c['name'] === 'token_key');
        if (!empty($cols) && empty($hasTokenKey)) {
            $db->exec("DROP TABLE otp_store");
            $db->exec("CREATE TABLE otp_store (
                token_key  TEXT PRIMARY KEY,
                otp_hash   TEXT NOT NULL,
                expires_at INTEGER NOT NULL,
                ip         TEXT
            )");
        }

        // Seed default options if table is empty
        $count = (int) $db->query("SELECT COUNT(*) FROM studio_options")->fetchColumn();
        if ($count === 0) {
            $defaultOptions = [
                // Providers
                ['provider', 'Dish', null, null],
                ['provider', 'DirecTV', null, null],
                ['provider', 'StayLink', null, null],
                ['provider', 'TV Solution', null, null],

                // Systems
                // Dish
                ['system', 'Analog SmartBox', 'Dish', null],
                ['system', 'Digital Smartbox', 'Dish', null],
                ['system', 'Hybrid SmartBox', 'Dish', null],
                // DirecTV
                ['system', 'DRE', 'DirecTV', null],
                ['system', 'Analog Com 3000', 'DirecTV', null],
                ['system', 'HD Com 3000', 'DirecTV', null],
                ['system', 'Com 3000', 'DirecTV', null],
                ['system', 'AEP', 'DirecTV', null],
                // TV Solution
                ['system', 'Philips', 'TV Solution', null],

                // Default programming options (global — shown for all providers)
                ['programming', "America's Top 120", null, null],
                ['programming', 'HBO & AT120', null, null],
                ['programming', 'Starz Encore Movies & AT120', null, null],
                ['programming', 'ShowTime & AT120', null, null],
                ['programming', 'Entertainment', null, null],
                ['programming', 'HBO & Entertainment', null, null],
                ['programming', 'ShowTime & Entertainment', null, null],
                ['programming', 'Starz Encore Movies & Entertainment', null, null],

                // Dish-specific programming
                ['programming', 'Pro:Idiom - Smart TVs', 'Dish', null],
                ['programming', 'Pro:Idiom - Non:Smart TVs', 'Dish', null],
                ['programming', 'Pro:Idiom - Smart (No Netflix) TVs', 'Dish', null],
                ['programming', 'Non-Pro:Idiom - Smart TVs', 'Dish', null],
                ['programming', 'Non-Pro:Idiom - Non:Smart TVs', 'Dish', null],

                // DirecTV-specific programming
                ['programming', 'Select', 'DirecTV', null],
                ['programming', 'Choice', 'DirecTV', null],
                ['programming', 'Xtra', 'DirecTV', null],
                ['programming', 'Ultimate', 'DirecTV', null],
                ['programming', 'Premier', 'DirecTV', null],

                // TV Solution-specific programming
                ['programming', 'Basic Package', 'TV Solution', null],
                ['programming', 'Premium Package', 'TV Solution', null],
            ];
            $stmt = $db->prepare("INSERT OR IGNORE INTO studio_options (type, name, parent_provider, parent_system) VALUES (?, ?, ?, ?)");
            foreach ($defaultOptions as $opt) {
                $stmt->execute($opt);
            }
        }

        // Seed allowed IPs if empty
        $ipCount = (int) $db->query("SELECT COUNT(*) FROM allowed_ips")->fetchColumn();
        if ($ipCount === 0) {
            $defaultIps = ['115.246.109.187', '103.188.127.180', '::1'];
            $stmt = $db->prepare("INSERT OR IGNORE INTO allowed_ips (ip, added_at) VALUES (?, ?)");
            foreach ($defaultIps as $allowedIp) {
                $stmt->execute([$allowedIp, time()]);
            }
        }
    }

    private static function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $cols = self::$pdo->query("PRAGMA table_info({$table})")->fetchAll();
        if (empty($cols)) return;
        $exists = array_filter($cols, fn($c) => $c['name'] === $column);
        if (empty($exists)) {
            self::$pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }
}
