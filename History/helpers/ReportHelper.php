<?php
declare(strict_types=1);

class ReportHelper
{
    public static function formatCurrency(float $amount): string
    {
        return '৳' . number_format($amount);
    }

    public static function getCardTypeLabel(string $type): string
    {
        $map = [
            'bill_pay'     => 'বিল পরিশোধ',
            'min_pay'      => 'মিনিমাম বিল',
            'full_pay'     => 'ফুল পরিশোধ',
            'charge_pay'   => 'চার্জ পরিশোধ',
            'cash_advance' => 'ক্যাশ অ্যাডভান্স',
            'purchase'     => 'কেনাকাটা',
        ];
        return $map[$type] ?? $type;
    }

    public static function getCardTypeClass(string $type): string
    {
        $map = [
            'bill_pay'     => 'ctb-bill',
            'min_pay'      => 'ctb-min',
            'full_pay'     => 'ctb-full',
            'charge_pay'   => 'ctb-chg',
            'cash_advance' => 'ctb-adv',
            'purchase'     => 'ctb-pur',
        ];
        return $map[$type] ?? 'ctb-bill';
    }
}
