<?php
declare(strict_types=1);

/**
 * DeviceInfo — লগইনকারীর ডিভাইস ও নেটওয়ার্ক তথ্য বিশ্লেষণ (PHP 8.1+)।
 * ────────────────────────────────────────────────────────────────────────
 *  কী পাওয়া যায়:  IP address, Operating System, Browser + version,
 *                 device model (best-effort), raw User-Agent।
 *
 *  বাস্তবতা (সততা):  আধুনিক ব্রাউজার প্রাইভেসির কারণে ফোনের সঠিক model
 *                    সবসময় দেয় না। তাই আগে Client Hints (Sec-CH-UA-Model)
 *                    দেখা হয়, না পেলে User-Agent থেকে অনুমান করা হয়,
 *                    কিছুই না পেলে "Unknown" থাকে — কখনো ভুল দাবি করা হয় না।
 *
 *  readonly class ব্যবহার করা হয়েছে — একবার তৈরি হলে তথ্য অপরিবর্তনীয়।
 */
final readonly class DeviceInfo
{
    public function __construct(
        public string $ipAddress,
        public string $operatingSystem,
        public string $browser,
        public string $browserVersion,
        public string $deviceModel,
        public string $deviceType,
        public string $userAgent,
    ) {}

    /**
     * বর্তমান রিকোয়েস্ট থেকে DeviceInfo তৈরি করে।
     */
    public static function fromRequest(): self
    {
        $userAgent = self::readServer('HTTP_USER_AGENT');
        if ($userAgent === '') {
            $userAgent = 'Unknown';
        }
        if (strlen($userAgent) > 512) {
            $userAgent = substr($userAgent, 0, 512);
        }

        return new self(
            ipAddress:       self::resolveIpAddress(),
            operatingSystem: self::detectOperatingSystem($userAgent),
            browser:         self::detectBrowserName($userAgent),
            browserVersion:  self::detectBrowserVersion($userAgent),
            deviceModel:     self::detectDeviceModel($userAgent),
            deviceType:      self::detectDeviceType($userAgent),
            userAgent:       $userAgent,
        );
    }

    /**
     * লগ বা প্রদর্শনের জন্য এক লাইনের সংক্ষিপ্ত বিবরণ।
     */
    public function toSummaryLine(): string
    {
        return sprintf(
            'IP: %s | OS: %s | Browser: %s %s | Model: %s | Type: %s',
            $this->ipAddress,
            $this->operatingSystem,
            $this->browser,
            $this->browserVersion,
            $this->deviceModel,
            $this->deviceType,
        );
    }

    /**
     * স্ট্রাকচার্ড অ্যারে (JSON বা DB-তে সেভ করার জন্য)।
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'ip_address'       => $this->ipAddress,
            'operating_system' => $this->operatingSystem,
            'browser'          => $this->browser,
            'browser_version'  => $this->browserVersion,
            'device_model'     => $this->deviceModel,
            'device_type'      => $this->deviceType,
            'user_agent'       => $this->userAgent,
        ];
    }

    /* ───────────────────────── IP resolution ───────────────────────── */

    /**
     * নিরাপদ IP নির্ণয়। REMOTE_ADDR সবচেয়ে নির্ভরযোগ্য (spoof করা কঠিন)।
     * প্রক্সি/লোড-ব্যালান্সারের পেছনে থাকলে X-Forwarded-For-এর প্রথম বৈধ
     * পাবলিক IP দেখা হয়, কিন্তু সেটি ইউজার-নিয়ন্ত্রিত হেডার বলে গৌণ ধরা হয়।
     */
    private static function resolveIpAddress(): string
    {
        $forwarded = self::readServer('HTTP_X_FORWARDED_FOR');
        if ($forwarded !== '') {
            foreach (explode(',', $forwarded) as $candidate) {
                $candidate = trim($candidate);
                if (filter_var(
                    $candidate,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                ) !== false) {
                    return $candidate;
                }
            }
        }

        $remote = self::readServer('REMOTE_ADDR');
        if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_IP) !== false) {
            return $remote;
        }

        return 'Unknown';
    }

    /* ───────────────────────── OS detection ───────────────────────── */

    private static function detectOperatingSystem(string $ua): string
    {
        $clientHint = self::readServer('HTTP_SEC_CH_UA_PLATFORM');
        if ($clientHint !== '') {
            $clean = trim($clientHint, "\" ");
            if ($clean !== '' && strtolower($clean) !== 'unknown') {
                return $clean;
            }
        }

        $map = [
            '/windows nt 10/i'         => 'Windows 10/11',
            '/windows nt 6\.3/i'       => 'Windows 8.1',
            '/windows nt 6\.2/i'       => 'Windows 8',
            '/windows nt 6\.1/i'       => 'Windows 7',
            '/windows/i'               => 'Windows',
            '/android/i'               => 'Android',
            '/iphone|ipad|ipod/i'      => 'iOS',
            '/mac os x|macintosh/i'    => 'macOS',
            '/cros/i'                  => 'ChromeOS',
            '/linux/i'                 => 'Linux',
        ];
        foreach ($map as $pattern => $name) {
            if (preg_match($pattern, $ua) === 1) {
                return $name;
            }
        }
        return 'Unknown';
    }

    /* ───────────────────────── Browser name ───────────────────────── */

    private static function detectBrowserName(string $ua): string
    {
        // ক্রম গুরুত্বপূর্ণ: নির্দিষ্ট ব্রাউজার আগে, জেনেরিক (Chrome/Safari) পরে।
        $map = [
            '/edg\//i'                 => 'Edge',
            '/opr\/|opera/i'           => 'Opera',
            '/samsungbrowser/i'        => 'Samsung Internet',
            '/ucbrowser/i'             => 'UC Browser',
            '/firefox|fxios/i'         => 'Firefox',
            '/chrome|crios/i'          => 'Chrome',
            '/safari/i'                => 'Safari',
            '/msie|trident/i'          => 'Internet Explorer',
        ];
        foreach ($map as $pattern => $name) {
            if (preg_match($pattern, $ua) === 1) {
                return $name;
            }
        }
        return 'Unknown';
    }

    /* ───────────────────────── Browser version ───────────────────────── */

    private static function detectBrowserVersion(string $ua): string
    {
        $patterns = [
            '/edg\/([\d.]+)/i',
            '/opr\/([\d.]+)/i',
            '/samsungbrowser\/([\d.]+)/i',
            '/ucbrowser\/([\d.]+)/i',
            '/firefox\/([\d.]+)/i',
            '/fxios\/([\d.]+)/i',
            '/crios\/([\d.]+)/i',
            '/chrome\/([\d.]+)/i',
            '/version\/([\d.]+).*safari/i',
            '/msie ([\d.]+)/i',
            '/rv:([\d.]+)/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $ua, $m) === 1) {
                return $m[1];
            }
        }
        return '';
    }

    /* ───────────────────────── Device model ───────────────────────── */

    private static function detectDeviceModel(string $ua): string
    {
        // ১. সবচেয়ে নির্ভরযোগ্য: Client Hint (শুধু HTTPS + অনুমতি থাকলে আসে)।
        $hint = self::readServer('HTTP_SEC_CH_UA_MODEL');
        if ($hint !== '') {
            $clean = trim($hint, "\" ");
            if ($clean !== '' && strtolower($clean) !== 'unknown') {
                return $clean;
            }
        }

        // ২. Apple ডিভাইস — UA-তে নির্দিষ্ট মডেল থাকে না, শুধু ক্যাটেগরি।
        if (preg_match('/\biPhone\b/i', $ua) === 1) { return 'iPhone'; }
        if (preg_match('/\biPad\b/i', $ua)   === 1) { return 'iPad'; }
        if (preg_match('/\biPod\b/i', $ua)   === 1) { return 'iPod'; }

        // ৩. Android — "Build/" এর আগের অংশ থেকে মডেল বের করার চেষ্টা।
        //    উদাহরণ: "... (Linux; Android 13; SM-G991B Build/...)" → SM-G991B
        if (preg_match('/android[^;]*;\s*([^;)]+?)\s+build\//i', $ua, $m) === 1) {
            $model = trim($m[1]);
            if ($model !== '' && stripos($model, 'wv') !== 0) {
                return $model;
            }
        }
        // fallback: "Android 13; <model>)" (Build/ নেই এমন ক্ষেত্রে)
        if (preg_match('/android[\d.\s]*;\s*([^;)]+)\)/i', $ua, $m) === 1) {
            $model = trim($m[1]);
            $model = preg_replace('/\bBuild.*$/i', '', $model) ?? $model;
            $model = trim($model);
            if ($model !== '' && strtolower($model) !== 'k' && stripos($model, 'wv') !== 0) {
                return $model;
            }
        }

        return 'Unknown';
    }

    /* ───────────────────────── Device type ───────────────────────── */

    private static function detectDeviceType(string $ua): string
    {
        if (preg_match('/\biPad\b|tablet/i', $ua) === 1) {
            return 'Tablet';
        }
        if (preg_match('/mobile|android|iphone|ipod|windows phone/i', $ua) === 1) {
            return 'Mobile';
        }
        if (preg_match('/windows nt|macintosh|linux|cros/i', $ua) === 1) {
            return 'Desktop';
        }
        return 'Unknown';
    }

    /* ───────────────────────── helper ───────────────────────── */

    private static function readServer(string $key): string
    {
        $value = $_SERVER[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }
}
