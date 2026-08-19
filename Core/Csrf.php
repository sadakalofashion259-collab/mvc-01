<?php
declare(strict_types=1);

/**
 * Csrf.php — CSRF টোকেন ম্যানেজমেন্ট ক্লাস
 * 
 * ব্যবহার:
 *   Csrf::token()        → টোকেন জেনারেট/রিটার্ন
 *   Csrf::verify($token) → টোকেন ভেরিফাই
 *   Csrf::regenerate()   → নতুন টোকেন জেনারেট
 */
class Csrf
{
    /**
     * বর্তমান CSRF টোকেন রিটার্ন করে (যদি না থাকে, তৈরি করে)
     */
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * প্রদত্ত টোকেন সঠিক কিনা যাচাই করে (টাইমিং অ্যাটাক প্রতিরোধে hash_equals ব্যবহার)
     */
    public static function verify(?string $token): bool
    {
        if ($token === null) {
            return false;
        }
        return isset($_SESSION['csrf_token']) 
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * নতুন CSRF টোকেন জেনারেট করে (পুরনো টোকেন ওভাররাইট)
     */
    public static function regenerate(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /**
     * HTML ফর্মের জন্য হিডেন ইনপুট ফিল্ড রিটার্ন করে (সুবিধাজনক)
     */
    public static function hiddenField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::token() . '">';
    }

    /**
     * AJAX রিকোয়েস্টের জন্য JSON-এ টোকেন পাঠানোর হেল্পার
     */
    public static function getForAjax(): array
    {
        return ['csrf_token' => self::token()];
    }
}