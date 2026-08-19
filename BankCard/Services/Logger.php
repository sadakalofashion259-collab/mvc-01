<?php
declare(strict_types=1);

final class Logger
{
    public static function error(string $message): void
    {
        $dir = MODULE_ROOT . '/Logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/error_log.txt';
        $time = date('Y-m-d H:i:s');
        file_put_contents($file, "[$time] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
