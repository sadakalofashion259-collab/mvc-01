<?php
declare(strict_types=1);

final class DateHelper
{
    public static function advanceByFrequency(string $date, string $frequency): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            $ts = time();
        }
        return match ($frequency) {
            'daily'  => date('Y-m-d', strtotime('+1 day', $ts)),
            'weekly' => date('Y-m-d', strtotime('+7 days', $ts)),
            default  => date('Y-m-d', strtotime('+1 month', $ts)),
        };
    }
}
