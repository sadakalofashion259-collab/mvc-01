<?php
/**
 * core/Database.php
 * ─────────────────────────────────────────
 * এখন আর নিজে db_connect.php খুঁজে require করে না। বরং bootstrap (public/index.php,
 * public/api.php) আসল db_connect.php (আপনার লগইন/সিকিউরিটি সিস্টেম) require করে
 * $conn তৈরি করে, তারপর Database::useConnection($conn) দিয়ে এখানে রেজিস্টার করে।
 * এভাবে db_connect.php-এর নিজস্ব session_start() (secure cookie flags সহ) ও
 * ভল্ট (.env) লজিক ঠিকভাবে, ঠিক সময়ে একবারই চলে।
 */
declare(strict_types=1);

final class Database
{
    private static ?PDO $instance = null;

    public static function useConnection(PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    public static function connection(): PDO
    {
        if (!self::$instance instanceof PDO) {
            throw new RuntimeException('Database ব্যবহারের আগে Database::useConnection($conn) কল করা হয়নি।');
        }
        return self::$instance;
    }
}

