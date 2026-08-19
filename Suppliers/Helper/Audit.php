<?php
declare(strict_types=1);
if (!defined('SK_APP')) { http_response_code(403); exit('403 Forbidden'); }

/**
 * Audit Logger বুটস্ট্র্যাপ — Suppliers মডিউলের জন্য।
 *
 * ব্যবহার:
 *   Audit::init($conn);   // একবার (Controller বা entry পেজে)
 *
 * এরপর যেকোনো জায়গায়:
 *   AuditLogger::create('suppliers', $id, null, $newData);
 *   AuditLogger::update('suppliers', $id, $old, $new);
 *   AuditLogger::delete('suppliers', $id, $old);
 *   AuditLogger::export('suppliers', null, null, null, 'CSV exported');
 */
final class Audit
{
    private static bool $ready = false;

    /** একবার কল করলেই যথেষ্ট — একাধিকবার নিরাপদ */
    public static function init(\PDO $conn): void
    {
        if (self::$ready) {
            return;
        }

        // public_html/Audit/
        $auditDir = SK_ROOT . '/Audit';

        if (!is_file($auditDir . '/AuditLogModel.php') || !is_file($auditDir . '/AuditLogger.php')) {
            // Audit মডিউল না থাকলে নীরবে স্কিপ (মূল কাজ বন্ধ হবে না)
            error_log('Audit::init — Audit module files not found at ' . $auditDir);
            return;
        }

        require_once $auditDir . '/AuditLogModel.php';
        require_once $auditDir . '/AuditLogger.php';

        AuditLogger::init($conn);
        self::$ready = true;
    }

    /** Audit প্রস্তুত আছে কিনা চেক */
    public static function isReady(): bool
    {
        return self::$ready;
    }
}
