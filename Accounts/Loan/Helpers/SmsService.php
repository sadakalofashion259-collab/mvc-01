<?php

declare(strict_types=1);

/**
 * SmsService — MiMSMS API v2 দিয়ে SMS পাঠানো।
 *
 * ক্রেডেনশিয়াল .env থেকে আসে:
 *   MIMSMS_USERNAME=আপনার@ইমেইল.com
 *   MIMSMS_API_KEY=আপনার_API_KEY
 *   MIMSMS_SENDER_ID=আপনার_SenderID
 *
 * ব্যবহার:
 *   SmsService::send('017XXXXXXXX', 'মেসেজ টেক্সট');
 *   SmsService::sendDueReminder($loan);   // due date রিমাইন্ডার
 *   SmsService::sendPaymentConfirm(...);  // শোধের কনফার্মেশন
 */
final class SmsService
{
    private const API_URL = 'https://api.mimsms.com/api/V2/SMS';

    /**
     * একক নম্বরে Transactional SMS পাঠায়।
     *
     * @param string $mobile  01XXXXXXXXX বা 8801XXXXXXXXX
     * @param string $message SMS টেক্সট
     * @return array{success:bool, response:?array, error:?string}
     */
    public static function send(string $mobile, string $message): array
    {
        $mobile = self::formatNumber($mobile);
        if ($mobile === null) {
            return ['success' => false, 'response' => null, 'error' => 'Invalid mobile number'];
        }

        $userName = self::env('MIMSMS_USERNAME');
        $apiKey   = self::env('MIMSMS_API_KEY');
        $sender   = self::env('MIMSMS_SENDER_ID');

        if ($userName === '' || $apiKey === '' || $sender === '') {
            Logger::warning('SmsService: MIMSMS credentials missing in .env');
            return ['success' => false, 'response' => null, 'error' => 'SMS credentials not configured'];
        }

        $payload = [
            'apiKey'          => $apiKey,
            'userName'        => $userName,
            'senderName'      => $sender,
            'transactionType' => 'T',
            'mobileNumber'    => $mobile,
            'message'         => $message,
        ];

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $raw   = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0) {
            Logger::error('SmsService cURL error: ' . $error);
            return ['success' => false, 'response' => null, 'error' => $error];
        }

        $data = json_decode((string)$raw, true);
        $ok   = is_array($data)
            && isset($data['status'])
            && strcasecmp((string)$data['status'], 'Success') === 0;

        if (!$ok) {
            Logger::warning('SmsService failed: ' . substr((string)$raw, 0, 500));
        }

        return [
            'success'  => $ok,
            'response' => is_array($data) ? $data : null,
            'error'    => $ok ? null : (is_array($data) ? ($data['responseResult'] ?? 'Unknown error') : 'Invalid response'),
        ];
    }

    /**
     * কিস্তির due date রিমাইন্ডার।
     * $loan অ্যারেতে অন্তত: borrower_name, installment_amount, due_date, mobile (বা phone)
     */
    public static function sendDueReminder(array $loan): array
    {
        $mobile = (string)($loan['mobile'] ?? $loan['phone'] ?? '');
        if ($mobile === '') {
            return ['success' => false, 'response' => null, 'error' => 'No mobile on loan'];
        }

        $name   = (string)($loan['borrower_name'] ?? 'পাওনাদার');
        $amount = Money::format((float)($loan['installment_amount'] ?? 0));
        $due    = (string)($loan['due_date'] ?? '');
        $days   = null;

        if ($due !== '' && Security::isValidDate($due)) {
            try {
                $today = new DateTimeImmutable(date('Y-m-d'));
                $d     = new DateTimeImmutable($due);
                $days  = (int)$today->diff($d)->format('%r%a');
            } catch (Throwable $e) {
                // ignore
            }
        }

        if ($days !== null && $days < 0) {
            $when = abs($days) . ' দিন পার হয়ে গেছে';
        } elseif ($days === 0) {
            $when = 'আজ';
        } elseif ($days === 1) {
            $when = 'কাল';
        } elseif ($days !== null) {
            $when = $days . ' দিন পর';
        } else {
            $when = $due !== '' ? $due : 'শীঘ্রই';
        }

        $msg = "সাদা কালো: {$name} এর কিস্তি {$when} শোধ করতে হবে। পরিমাণ: {$amount}। ধন্যবাদ।";

        return self::send($mobile, $msg);
    }

    /**
     * কিস্তি শোধের কনফার্মেশন SMS।
     */
    public static function sendPaymentConfirm(
        string $mobile,
        string $lenderName,
        float $amount,
        float $remaining
    ): array {
        if ($mobile === '') {
            return ['success' => false, 'response' => null, 'error' => 'No mobile'];
        }

        $msg = sprintf(
            'সাদা কালো: %s এ ৳%s শোধ হয়েছে। এখনো বাকি: ৳%s। ধন্যবাদ।',
            $lenderName,
            number_format($amount, 2),
            number_format($remaining, 2)
        );

        return self::send($mobile, $msg);
    }

    /**
     * নতুন লোন খোলার নোটিফিকেশন SMS।
     */
    public static function sendLoanOpened(
        string $mobile,
        string $lenderName,
        float $principal,
        string $accountNumber
    ): array {
        if ($mobile === '') {
            return ['success' => false, 'response' => null, 'error' => 'No mobile'];
        }

        $msg = sprintf(
            'সাদা কালো: %s থেকে ৳%s লোন নেওয়া হয়েছে। অ্যাকাউন্ট: %s।',
            $lenderName,
            number_format($principal, 2),
            $accountNumber
        );

        return self::send($mobile, $msg);
    }

    /** 01XXXXXXXXX → 8801XXXXXXXXX */
    private static function formatNumber(string $number): ?string
    {
        $number = preg_replace('/[^0-9]/', '', $number) ?? '';

        if (strlen($number) === 11 && str_starts_with($number, '01')) {
            return '88' . $number;
        }
        if (strlen($number) === 13 && str_starts_with($number, '8801')) {
            return $number;
        }

        return null;
    }

    private static function env(string $key): string
    {
        // ১) getenv (Apache/Nginx SetEnv বা php-fpm env)
        $v = getenv($key);
        if (is_string($v) && $v !== '') {
            return trim($v);
        }

        // ২) $_ENV / $_SERVER
        if (!empty($_ENV[$key]) && is_string($_ENV[$key])) {
            return trim($_ENV[$key]);
        }
        if (!empty($_SERVER[$key]) && is_string($_SERVER[$key])) {
            return trim($_SERVER[$key]);
        }

        // ৩) .env ফাইল (যদি project root-এ থাকে)
        static $envCache = null;
        if ($envCache === null) {
            $envCache = [];
            $candidates = [];
            if (defined('APP_ROOT')) {
                $candidates[] = APP_ROOT . '/../.env';
                $candidates[] = APP_ROOT . '/.env';
                $candidates[] = dirname(APP_ROOT) . '/.env';
            }
            // সাধারণ পাথ (যেমন /home/sadakalo/App/.env)
            $candidates[] = '/home/sadakalo/App/.env';

            foreach ($candidates as $path) {
                if (is_file($path) && is_readable($path)) {
                    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if ($lines === false) {
                        continue;
                    }
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                            continue;
                        }
                        [$k, $val] = explode('=', $line, 2);
                        $k   = trim($k);
                        $val = trim($val, " \t\"'");
                        $envCache[$k] = $val;
                    }
                    break;
                }
            }
        }

        return (string)($envCache[$key] ?? '');
    }
}
