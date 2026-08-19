<?php
declare(strict_types=1);
if (!defined('SK_APP')) { http_response_code(403); exit('403 Forbidden'); }

/**
 * ফাইল-ভিত্তিক লগার। সব লগ Suppliers/Logs/-এ যায় (ব্রাউজার থেকে ব্লকড)।
 */
final class Logger
{
    public static function error(string $message, ?\Throwable $e = null): void
    {
        $detail = $e ? ' | ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : '';
        self::write('error_log.txt', 'ERROR: ' . $message . $detail);
    }

    public static func,tion sms(string $line): void
    {
        self::write('sms_log.txt', $line);
    }

    private static function write(string $file, string $line): void
    {
        if (!is_dir(SK_LOGS) && !@mkdir(SK_LOGS, 0755, true) && !is_dir(SK_LOGS)) {
            return;
        }
        $entry = '[' . date('Y-m-d H:i:s') . '] '
            . str_replace(["\r", "\n"], ' ', $line) . PHP_EOL;
        @file_put_contents(SK_LOGS . '/' . $file, $entry, FILE_APPEND | LOCK_EX);
    }
}
