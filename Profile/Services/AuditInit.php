<?php
declare(strict_types=1);

/**
 * Profile মডিউলের জন্য Audit Logger বুটস্ট্র্যাপ।
 * index.php থেকে একবার AuditInit::boot($conn) কল করুন।
 */
final class AuditInit
{
    private static bool $ready = false;

    public static function boot(\PDO $conn): void
    {
        if (self::$ready) {
            return;
        }

        // public_html/Audit/
        $auditDir = dirname(__DIR__, 2) . '/Audit';

        if (!is_file($auditDir . '/AuditLogModel.php') || !is_file($auditDir . '/AuditLogger.php')) {
            error_log('AuditInit (Profile): Audit module not found at ' . $auditDir);
            return;
        }

        require_once $auditDir . '/AuditLogModel.php';
        require_once $auditDir . '/AuditLogger.php';
        AuditLogger::init($conn);
        self::$ready = true;
    }

    public static function isReady(): bool
    {
        return self::$ready;
    }
}
