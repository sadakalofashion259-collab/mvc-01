<?php
declare(strict_types=1);
if (!defined('SK_APP')) { http_response_code(403); exit('403 Forbidden'); }

/**
 * PDO সংযোগ — সিঙ্গেলটন। সত্যিকারের prepared statement (emulate off),
 * exception mode, utf8mb4। ক্রেডেনশিয়াল আসে .env থেকে।
 */
final class Database
{
    private static ?\PDO $conn = null;

    public static function connect(): \PDO
    {
        if (self::$conn instanceof \PDO) {
            return self::$conn;
        }

        $host = sk_env('DB_HOST', 'localhost');
        $name = sk_env('DB_NAME');
        $user = sk_env('DB_USER');
        $pass = sk_env('DB_PASS');

        try {
            $pdo = new \PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES   => false,   // true prepared statements → SQLi hardening
                ]
            );
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            self::$conn = $pdo;
            return $pdo;
        } catch (\PDOException $e) {
            Logger::error('Database connection failed', $e);
            self::renderFatal('ডাটাবেজে সংযোগ করা যায়নি। সাময়িকভাবে কাজ বন্ধ রাখুন।');
        }
    }

    /** ক্লায়েন্টকে জেনেরিক এরর পেজ (কোনো অভ্যন্তরীণ তথ্য ফাঁস নয়) */
    public static function renderFatal(string $message): never
    {
        http_response_code(503);
        $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        exit('<!DOCTYPE html><html lang="bn"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Service Unavailable</title>'
            . '<style>body{background:#0f172a;margin:0;height:100vh;display:flex;align-items:center;'
            . 'justify-content:center;font-family:"Segoe UI",Tahoma,sans-serif}'
            . '.b{background:#1e293b;border:2px solid #ef4444;border-radius:12px;padding:36px;'
            . 'max-width:460px;width:90%;text-align:center;color:#cbd5e1}'
            . '.b h1{color:#ef4444;font-size:22px;margin:0 0 12px}</style></head><body>'
            . '<div class="b"><div style="font-size:56px">⚠️</div>'
            . '<h1>সাময়িক ত্রুটি</h1><p>' . $safe . '</p></div></body></html>');
    }
}
