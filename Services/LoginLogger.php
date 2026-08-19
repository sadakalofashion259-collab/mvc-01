<?php
declare(strict_types=1);

require_once __DIR__ . '/DeviceInfo.php';

/**
 * LoginLogger — প্রতিটি লগইন/লগআউট ইভেন্ট Logs/Login_Log.txt-এ নিরাপদে লেখে।
 * ────────────────────────────────────────────────────────────────────────
 *  • concurrency-safe: LOCK_EX দিয়ে ফাইল লক করা হয় (একসাথে দুই রিকোয়েস্ট
 *    এলেও লাইন ভাঙবে না)।
 *  • Logs/ ফোল্ডার না থাকলে তৈরি করে এবং .htaccess দিয়ে সরাসরি
 *    ব্রাউজার-অ্যাক্সেস বন্ধ করে দেয় (IP/ডিভাইস তথ্য PII — সুরক্ষা জরুরি)।
 *  • কোনো এরর হলে অ্যাপ থামে না — নীরবে error_log.txt-এ যায়।
 */
final class LoginLogger
{
    private const LOG_FILE = 'Login_Log.txt';

    private string $logDirectory;

    public function __construct(?string $logDirectory = null)
    {
        $this->logDirectory = $logDirectory ?? (__DIR__ . '/../Logs');
    }

    /**
     * একটি লগইন-সম্পর্কিত ইভেন্ট রেকর্ড করে।
     *
     * @param string     $event   যেমন: LOGIN_SUCCESS, BIOMETRIC_LOGIN, LOGOUT,
     *                            SESSION_REPLACED, LOGIN_FAILED
     * @param string     $account ইউজারনেম/আইডেন্টিফায়ার
     * @param DeviceInfo $device  ডিভাইস তথ্য
     * @param string     $note    ঐচ্ছিক অতিরিক্ত মন্তব্য
     */
    public function record(string $event, string $account, DeviceInfo $device, string $note = ''): void
    {
        try {
            $this->ensureDirectory();

            $line = sprintf(
                '[%s] %-16s | User: %-20s | %s%s%s',
                date('Y-m-d H:i:s'),
                $this->sanitize($event),
                $this->sanitize($account),
                $device->toSummaryLine(),
                ($note !== '' ? ' | Note: ' . $this->sanitize($note) : ''),
                PHP_EOL
            );

            @file_put_contents(
                $this->logDirectory . '/' . self::LOG_FILE,
                $line,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable $e) {
            // লগিং ব্যর্থ হলেও মূল লগইন প্রবাহ থামবে না।
            @error_log(
                '[' . date('Y-m-d H:i:s') . '] LOGIN_LOGGER_ERROR: ' . $e->getMessage() . PHP_EOL,
                3,
                $this->logDirectory . '/error_log.txt'
            );
        }
    }

    /* ───────────────────────── helpers ───────────────────────── */

    private function ensureDirectory(): void
    {
        if (!is_dir($this->logDirectory)) {
            @mkdir($this->logDirectory, 0755, true);
        }

        // Login_Log.txt-কে ওয়েব থেকে সরাসরি ডাউনলোড করা ঠেকাতে .htaccess।
        $htaccess = $this->logDirectory . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents(
                $htaccess,
                "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
            );
        }
    }

    /**
     * লগ-ইনজেকশন ঠেকাতে newline/control character সরিয়ে ফেলে।
     */
    private function sanitize(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;
        return trim($value);
    }
}
