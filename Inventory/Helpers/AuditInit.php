<?php
declare(strict_types=1);

/**
 * একবারই আপলোড করুন — বারবার রিপ্লেস করার দরকার নেই।
 *
 * যেকোনো পেজ থেকে:
 *   require_once __DIR__ . '/Helpers/AuditInit.php';  // বা সঠিক relative path
 *   AuditInit::boot($conn);
 *
 * তারপর:
 *   if (class_exists('AuditLogger')) {
 *       AuditLogger::create(...);  // অ্যাড
 *       AuditLogger::update(...);  // এডিট
 *       AuditLogger::delete(...);  // ডিলিট
 *   }
 *
 * - মডিউল/ফোল্ডারের নাম হার্ডকোড নেই (Inventory নাম বদলালেও চলবে)
 * - উপরের দিকে হাঁটতে হাঁটতে Audit/ খুঁজে নেয়
 * - Audit/ না থাকলে নীরবে স্কিপ — মূল কাজ বন্ধ হয় না
 */
final class AuditInit
{
    private static bool $ready = false;

    public static function boot(\PDO $conn): void
    {
        // ইতিমধ্যে লোড থাকলে শুধু init refresh
        if (class_exists('AuditLogger', false)) {
            if (method_exists('AuditLogger', 'init')) {
                AuditLogger::init($conn);
            }
            self::$ready = true;
            return;
        }

        if (self::$ready) {
            return;
        }

        $auditDir = self::findAuditDir();
        if ($auditDir === null) {
            return;
        }

        $model  = $auditDir . DIRECTORY_SEPARATOR . 'AuditLogModel.php';
        $logger = $auditDir . DIRECTORY_SEPARATOR . 'AuditLogger.php';

        if (!is_file($model) || !is_file($logger)) {
            return;
        }

        require_once $model;
        require_once $logger;

        if (class_exists('AuditLogger') && method_exists('AuditLogger', 'init')) {
            AuditLogger::init($conn);
            self::$ready = true;
        }
    }

    public static function isReady(): bool
    {
        return self::$ready || class_exists('AuditLogger', false);
    }

    /**
     * __DIR__ থেকে উপরে উঠে Audit/AuditLogger.php খুঁজে।
     * ফোল্ডার নাম (Inventory ইত্যাদি) নির্ভর করে না।
     */
    private static function findAuditDir(): ?string
    {
        $dir = __DIR__;
        for ($i = 0; $i < 6; $i++) {
            $candidate = $dir . DIRECTORY_SEPARATOR . 'Audit';
            if (is_file($candidate . DIRECTORY_SEPARATOR . 'AuditLogger.php')) {
                return $candidate;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
        return null;
    }
}
