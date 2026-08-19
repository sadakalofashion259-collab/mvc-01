<?php
/**
 * Staff/Core/Logger.php — এরর লগ ফাংশন
 * সব লগ Staff/Logs/error_log.txt ফাইলে জমা হয়।
 */

if (!function_exists('staff_log')) {
    /**
     * @param string $tag     লগ ট্যাগ, যেমন: SMS, EMAIL, ATTENDANCE, EXPENSE, OTP_ERROR
     * @param string $message লগ বার্তা
     */
    function staff_log(string $tag, string $message): void
    {
        $logDir  = dirname(__DIR__) . '/Logs';
        $logFile = $logDir . '/error_log.txt';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents(
            $logFile,
            "[{$timestamp}] [{$tag}] {$message}" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

if (!function_exists('staff_log_exception')) {
    function staff_log_exception(string $tag, Throwable $e): void
    {
        staff_log($tag, $e->getMessage() . ' | ' . $e->getFile() . ' line ' . $e->getLine());
    }
}
