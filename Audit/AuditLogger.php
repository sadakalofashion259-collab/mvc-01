<?php
declare(strict_types=1);

/**
 * public_html/Audit/AuditLogger.php
 *
 * যেকোনো কন্ট্রোলার থেকে এক লাইনে লগ করার static ফাসাদ।
 *
 * ─── ব্যবহার ────────────────────────────────────────────────────────────
 *
 * Audit/db_connect.php ইতিমধ্যে init() করে দেয়।
 * অন্য মডিউল (Sales, Expenses…) থেকে লগ করতে হলে:
 *
 *   require_once $root . '/Audit/AuditLogModel.php';
 *   require_once $root . '/Audit/AuditLogger.php';
 *   AuditLogger::init($conn);   // $conn আগে থেকেই আছে
 *
 *   AuditLogger::create('sales',    $newId,  null,    $newRow);
 *   AuditLogger::update('expenses', $id,     $oldRow, $newRow);
 *   AuditLogger::delete('customers',$id,     $oldRow);
 *   AuditLogger::export('sales',    null,    null,    null, 'CSV exported');
 * ─────────────────────────────────────────────────────────────────────────
 */
class AuditLogger
{
    private static ?AuditLogModel $model = null;

    /** বুটস্ট্র্যাপে একবার — একাধিকবার call করলেও নিরাপদ */
    public static function init(PDO $pdo): void
    {
        if (self::$model === null) {
            self::$model = new AuditLogModel($pdo);
        }
    }

    // ── Public API ────────────────────────────────────────────────────────

    public static function create(
        string      $module,
        string|int  $recordId,
        ?array      $oldData     = null,
        ?array      $newData     = null,
        ?string     $description = null
    ): void {
        self::write('CREATE', $module, $recordId, $oldData, $newData,
            $description ?? "{$module}-এ নতুন রেকর্ড তৈরি (id: {$recordId})");
    }

    public static function update(
        string      $module,
        string|int  $recordId,
        ?array      $oldData     = null,
        ?array      $newData     = null,
        ?string     $description = null
    ): void {
        self::write('UPDATE', $module, $recordId, $oldData, $newData,
            $description ?? "{$module} #{$recordId} আপডেট হয়েছে");
    }

    public static function delete(
        string      $module,
        string|int  $recordId,
        ?array      $oldData     = null,
        ?string     $description = null
    ): void {
        self::write('DELETE', $module, $recordId, $oldData, null,
            $description ?? "{$module} থেকে #{$recordId} ডিলিট হয়েছে");
    }

    public static function export(
        string          $module,
        string|int|null $recordId    = null,
        ?array          $oldData     = null,
        ?array          $newData     = null,
        ?string         $description = null
    ): void {
        self::write('EXPORT', $module, $recordId, $oldData, $newData,
            $description ?? "{$module} এক্সপোর্ট করা হয়েছে");
    }

    // ── Internal ──────────────────────────────────────────────────────────

    private static function write(
        string          $action,
        string          $module,
        string|int|null $recordId,
        ?array          $oldData,
        ?array          $newData,
        string          $description
    ): void {
        if (self::$model === null) {
            error_log("AuditLogger: init() কল হয়নি। {$action} on {$module} লগ হয়নি।");
            return;
        }
        try {
            self::$model->log([
                'action'      => $action,
                'module'      => $module,
                'record_id'   => $recordId,
                'old_data'    => $oldData,
                'new_data'    => $newData,
                'description' => $description,
            ]);
        } catch (Throwable $e) {
            // অডিট fail হলেও মূল operation বন্ধ হবে না
            error_log("AuditLogger write failed: " . $e->getMessage());
        }
    }
}
