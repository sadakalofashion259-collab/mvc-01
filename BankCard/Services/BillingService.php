<?php
declare(strict_types=1);

/**
 * Billing cycle helpers (billing_date based, e.g. 27th)
 */
final class BillingService
{
    public static function getBillingCycle(string $txnDate, int $billingDate): string
    {
        $ts = strtotime($txnDate);
        $y  = (int) date('Y', $ts);
        $m  = (int) date('n', $ts);
        $d  = (int) date('j', $ts);

        if ($d >= $billingDate) {
            $nextMonth = strtotime('+1 month', strtotime("$y-$m-01"));
            return date('Y-m', $nextMonth);
        }
        return date('Y-m', $ts);
    }

    public static function getBillingCycleLabel(string $cycleStr, int $billingDate): string
    {
        $endTs   = strtotime($cycleStr . '-' . str_pad((string) $billingDate, 2, '0', STR_PAD_LEFT));
        $startTs = strtotime('-1 month + 1 day', $endTs);
        return date('d M', $startTs) . ' — ' . date('d M Y', $endTs);
    }
}
