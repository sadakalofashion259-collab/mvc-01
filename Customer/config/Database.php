<?php
declare(strict_types=1);

require_once __DIR__ . '/Env.php';

/**
 * Database — Central PDO connection factory for the Customer module.
 *
 * Priority:
 *   1. DB_HOST / DB_NAME / DB_USER / DB_PASS from /home/sadakalo/App/.env
 *   2. Fallback: legacy public_html/db_connect.php (must define $conn as PDO)
 *
 * Security defaults:
 *   - utf8mb4 charset enforced in the DSN (no separate "SET NAMES" needed)
 *   - Real prepared statements (emulation disabled)
 *   - Exceptions on every SQL error
 */
final class Database
{
    private static ?\PDO $connection = null;

    public static function getConnection(): \PDO
    {
        if (self::$connection instanceof \PDO) {
            return self::$connection;
        }

        Env::load();

        $host = Env::get(['DB_HOST', 'DATABASE_HOST']);
        $name = Env::get(['DB_NAME', 'DB_DATABASE', 'DATABASE_NAME']);
        $user = Env::get(['DB_USER', 'DB_USERNAME', 'DATABASE_USER']);
        $pass = Env::get(['DB_PASS', 'DB_PASSWORD', 'DATABASE_PASSWORD']);

        if ($host !== '' && $name !== '' && $user !== '') {
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            self::$connection = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
            return self::$connection;
        }

        // ---- Legacy fallback: public_html/db_connect.php ----
        $legacy = dirname(__DIR__, 2) . '/db_connect.php';
        if (is_file($legacy)) {
            require_once $legacy;
            if (isset($conn) && $conn instanceof \PDO) {
                $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $conn->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$connection = $conn;
                return self::$connection;
            }
        }

        throw new \RuntimeException(
            'Database connection could not be established. '
            . 'Add DB_HOST, DB_NAME, DB_USER, DB_PASS to App/.env '
            . 'or keep a valid db_connect.php in public_html.'
        );
    }
}
