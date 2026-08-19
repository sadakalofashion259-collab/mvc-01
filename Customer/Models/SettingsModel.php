<?php
declare(strict_types=1);

/**
 * SettingsModel — Tiny key/value store for application-level settings
 * (e.g. the global SMS on/off switch).
 *
 * The backing table is created automatically on first use:
 *   app_settings (setting_key PK, setting_value)
 */
class SettingsModel
{
    public const KEY_SMS_ENABLED = 'sms_enabled';

    private \PDO $dbConnection;

    public function __construct(\PDO $dbConnection)
    {
        $this->dbConnection = $dbConnection;
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        try {
            $this->dbConnection->exec(
                'CREATE TABLE IF NOT EXISTS app_settings (
                    setting_key   VARCHAR(64)  NOT NULL PRIMARY KEY,
                    setting_value VARCHAR(255) NOT NULL
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\PDOException) {
            // Table creation failure is non-fatal; reads fall back to defaults.
        }
    }

    public function get(string $key, string $default = ''): string
    {
        try {
            $stmt = $this->dbConnection->prepare(
                'SELECT setting_value FROM app_settings WHERE setting_key = ?'
            );
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value === false ? $default : (string)$value;
        } catch (\PDOException) {
            return $default;
        }
    }

    public function set(string $key, string $value): bool
    {
        try {
            $stmt = $this->dbConnection->prepare(
                'INSERT INTO app_settings (setting_key, setting_value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            return $stmt->execute([$key, $value]);
        } catch (\PDOException) {
            return false;
        }
    }

    // ---- Convenience wrappers for the SMS switch ----

    /** SMS is ON by default when no row exists yet. */
    public function isSmsEnabled(): bool
    {
        return $this->get(self::KEY_SMS_ENABLED, '1') === '1';
    }

    /** Flip the switch and return the new state (1 = on, 0 = off). */
    public function toggleSmsEnabled(): int
    {
        $newState = $this->isSmsEnabled() ? 0 : 1;
        $this->set(self::KEY_SMS_ENABLED, (string)$newState);
        return $newState;
    }
}
