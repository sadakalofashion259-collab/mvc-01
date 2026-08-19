<?php
declare(strict_types=1);

/**
 * ════════════════════════════════════════════════════════════════════════════
 *  Services/LoginEmailAlert.php — “লগইন ইমেইল অ্যালার্ট” (Login Email Alert)
 * ════════════════════════════════════════════════════════════════════════════
 *
 *  দায়িত্ব (একটাই — Single Responsibility):
 *  ─────────────────────────────────────────
 *  লগইন-সংক্রান্ত তিনটি ঘটনায় তাৎক্ষণিক স্বয়ংক্রিয় ইমেইল পাঠানো —
 *      ১) সফল লগইন                → LoginEmailAlert::success(...)
 *      ২) ব্যর্থ লগইন (ভুল পাসওয়ার্ড) → LoginEmailAlert::failed(...)
 *      ৩) ব্লক / লক করা অ্যাকাউন্ট   → LoginEmailAlert::blocked(...)
 *
 *  প্রতিটি ঘটনায় দুটি কপি যায়:  ১ কপি → ইউজারের ইমেইলে
 *                                ১ কপি → অ্যাডমিনের ইমেইলে (ADMIN_Mail)
 *
 *  সম্পূর্ণ স্বাধীন (Standalone):
 *  ─────────────────────────────
 *  • এই ফাইল প্রজেক্টের অন্য কোনো ফাইলের উপর নির্ভর করে না —
 *    db_connect.php / DeviceInfo.php / LoginLogger.php কিছুই লাগে না।
 *  • কোনো থার্ড-পার্টি লাইব্রেরি নেই (PHPMailer/Composer/SMTP সার্ভিস নয়) —
 *    শুধুমাত্র PHP কোরের mail() ব্যবহার করা হয়েছে।
 *  • mbstring, curl, json — কোনো অপশনাল এক্সটেনশনও বাধ্যতামূলক নয়।
 *
 *  নিরাপত্তা (Security by design):
 *  ───────────────────────────────
 *  • Email Header Injection বন্ধ  → প্রতিটি হেডার ভ্যালু থেকে CR/LF/NUL ছাঁটা।
 *  • XSS বন্ধ                     → প্রতিটি ডাইনামিক ভ্যালু htmlspecialchars()।
 *  • Log Injection বন্ধ           → লগ লেখার আগে control character মুছে ফেলা।
 *  • UTF-8 (বাংলা) নিরাপদে পাঠাতে → Subject ও Body দুটোই স্ট্যান্ডার্ড
 *                                   Base64 (RFC 2047 / RFC 2045) এনকোডেড।
 *  • ইউজারকে কোনো এরর দেখানো হয় না → সব এরর Logs/error_log.txt-এ যায়।
 *  • ব্যর্থ লগইনে ইউজার-এনুমারেশন হয় না → ইমেইল শুধু DB-তে থাকা প্রকৃত
 *    অ্যাকাউন্টের নিজের ঠিকানাতেই যায়, অজানা ইউজারনেমে কিছুই যায় না।
 *
 *  পারফরম্যান্স (Non-blocking):
 *  ────────────────────────────
 *  dispatch() ইমেইলকে কিউ করে রাখে, তারপর register_shutdown_function()-এ
 *  fastcgi_finish_request() / litespeed_finish_request() দিয়ে আগে ইউজারকে
 *  রেসপন্স/রিডাইরেক্ট পাঠিয়ে দেওয়া হয় — ইমেইল যায় তার পরে, নীরবে।
 *  ফলে লগইন এক মুহূর্তও ধীর হয় না।
 *
 *  .env (ভল্ট) কী:
 *  ───────────────
 *      #---------Login_Alert----#
 *      Login_Alart="info@sadakalofashion.com"     ; প্রেরক — সফল/ব্যর্থ লগইন
 *      Login_Block="info@sadakalofashion.com"     ; প্রেরক — ব্লক/লক অ্যালার্ট
 *      ADMIN_Mail="hisabkhata24@gmail.com"        ; অ্যাডমিন কপির প্রাপক
 *
 *  ঐচ্ছিক (না দিলে নিচের ডিফল্ট মানই ব্যবহৃত হবে):
 *      Login_Alert_Logo="https://sadakalofashion.com/logo.png"
 *      Login_Alert_Url="https://sadakalofashion.com/index.php"
 *      Login_Mail_Throttle="0"    ; 0 = প্রতিবার ইমেইল যাবে (আপনার নির্দেশ)
 *
 *  ব্যবহার:
 *  ────────
 *      require_once __DIR__ . '/Services/LoginEmailAlert.php';
 *
 *      LoginEmailAlert::success(
 *          accountName: $user['username'],
 *          userEmail:   $user['email'],
 *          phoneNumber: $user['phone']
 *      );
 *
 *  @author  Sada Kalo Fashion — Security Engineering
 *  @version 1.0.0
 *  @php     8.1+
 */

/* ═══════════════════════════════════════════════════════════════════════════
 *  ১. LoginAlertStatus — লগইন ইভেন্টের অবস্থা (PHP 8.1 Backed Enum)
 * ═══════════════════════════════════════════════════════════════════════════ */

enum LoginAlertStatus: string
{
    /** পাসওয়ার্ড মিলেছে, লগইন সম্পন্ন হয়েছে */
    case Success = 'SUCCESS';

    /** ভুল পাসওয়ার্ড / ভুল ইউজারনেম — লগইন হয়নি */
    case Failed = 'FAILED';

    /** পরপর ৩ বার ভুল পাসওয়ার্ডে ব্লক, অথবা অ্যাকাউন্ট আগে থেকেই লক */
    case Blocked = 'BLOCKED';

    /** স্ট্যাটাস ব্যানারে দেখানো বাংলা লেবেল */
    public function label(): string
    {
        return match ($this) {
            self::Success => 'লগইন সফল হয়েছে',
            self::Failed  => 'লগইন ব্যর্থ হয়েছে',
            self::Blocked => 'অ্যাকাউন্ট ব্লক / লক করা আছে',
        };
    }

    /** ব্যানারের চিহ্ন — সফল ✔ , ব্যর্থ ⚠ , ব্লক ✖ (ক্রস চিহ্ন) */
    public function icon(): string
    {
        return match ($this) {
            self::Success => '✔',
            self::Failed  => '⚠',
            self::Blocked => '✖',
        };
    }

    /** ব্যানারের প্রধান রঙ — সফল সবুজ, ব্যর্থ লাল, ব্লক গাঢ় লাল */
    public function accentColor(): string
    {
        return match ($this) {
            self::Success => '#16a34a',
            self::Failed  => '#dc2626',
            self::Blocked => '#7f1d1d',
        };
    }

    /** গ্রেডিয়েন্টের নিচের শেড (প্রিমিয়াম লুক) */
    public function accentColorDeep(): string
    {
        return match ($this) {
            self::Success => '#15803d',
            self::Failed  => '#b91c1c',
            self::Blocked => '#450a0a',
        };
    }

    /** স্ট্যাটাস অনুযায়ী হালকা ব্যাকগ্রাউন্ড (ইনফো কার্ডের হেডলাইন) */
    public function softColor(): string
    {
        return match ($this) {
            self::Success => '#f0fdf4',
            self::Failed  => '#fef2f2',
            self::Blocked => '#fef2f2',
        };
    }

    /** ইমেইলের সাবজেক্ট লাইন (বাংলা) */
    public function subjectFor(string $accountName): string
    {
        return match ($this) {
            self::Success => 'লগইন সফল — ' . $accountName . ' | সাদা কালো ফ্যাশন',
            self::Failed  => 'সতর্কতা: ব্যর্থ লগইন চেষ্টা — ' . $accountName . ' | সাদা কালো ফ্যাশন',
            self::Blocked => 'জরুরি: অ্যাকাউন্ট ব্লক — ' . $accountName . ' | সাদা কালো ফ্যাশন',
        };
    }

    /** ইনবক্স প্রিভিউতে দেখানো এক লাইনের সারসংক্ষেপ */
    public function preheader(): string
    {
        return match ($this) {
            self::Success => 'আপনার অ্যাকাউন্টে একটি নতুন লগইন হয়েছে। এটি আপনি না করলে সাথে সাথে অ্যাডমিনকে জানান।',
            self::Failed  => 'আপনার অ্যাকাউন্টে একটি ব্যর্থ লগইন চেষ্টা হয়েছে। বিস্তারিত দেখে নিন।',
            self::Blocked => 'নিরাপত্তার কারণে অ্যাকাউন্টটি ব্লক করা হয়েছে। বিস্তারিত দেখে নিন।',
        };
    }

    /** ইউজারকে দেখানো ভূমিকা-বাক্য */
    public function userIntro(): string
    {
        return match ($this) {
            self::Success => 'আপনার অ্যাকাউন্টে নিচের ডিভাইস থেকে একটি সফল লগইন সম্পন্ন হয়েছে।',
            self::Failed  => 'আপনার অ্যাকাউন্টে নিচের ডিভাইস থেকে একটি ভুল পাসওয়ার্ড দিয়ে লগইনের চেষ্টা করা হয়েছে।',
            self::Blocked => 'নিরাপত্তার স্বার্থে আপনার অ্যাকাউন্টটি সাময়িকভাবে ব্লক/লক করা হয়েছে। বিস্তারিত নিচে দেওয়া হলো।',
        };
    }

    /** অ্যাডমিনকে দেখানো ভূমিকা-বাক্য */
    public function adminIntro(): string
    {
        return match ($this) {
            self::Success => 'সিস্টেমে একটি সফল লগইন রেকর্ড হয়েছে। বিস্তারিত নিচে দেওয়া হলো।',
            self::Failed  => 'সিস্টেমে একটি ব্যর্থ লগইন চেষ্টা রেকর্ড হয়েছে। বিস্তারিত নিচে দেওয়া হলো।',
            self::Blocked => 'একটি অ্যাকাউন্ট ব্লক/লক অবস্থায় প্রবেশের চেষ্টা করেছে অথবা ব্লক করা হয়েছে।',
        };
    }

    /**
     * প্রেরকের ঠিকানা .env-এর কোন কী থেকে নেওয়া হবে (ক্রমানুসারে খোঁজা হয়)।
     * ‘Login_Alart’ বানানটি আপনার .env-এ যেভাবে আছে সেভাবেই সাপোর্ট করা হয়েছে,
     * পাশাপাশি শুদ্ধ বানান ‘Login_Alert’-ও চলবে — যেকোনো একটি থাকলেই হবে।
     *
     * @return array<int,string>
     */
    public function senderKeys(): array
    {
        return match ($this) {
            self::Blocked => ['Login_Block', 'Login_Alert', 'Login_Alart'],
            default       => ['Login_Alert', 'Login_Alart', 'Login_Block'],
        };
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
 *  ২. LoginAlertContext — একটি লগইন ইভেন্টের অপরিবর্তনীয় তথ্য (readonly)
 * ═══════════════════════════════════════════════════════════════════════════ */

final readonly class LoginAlertContext
{
    public function __construct(
        public LoginAlertStatus $status,
        public string $accountName,
        public string $userEmail,
        public string $phoneNumber,
        public string $browserName,
        public string $deviceModel,
        public string $ipAddress,
        public string $occurredAtBangla,
        public string $occurredAtIso,
        public string $note,
    ) {}
}

/* ═══════════════════════════════════════════════════════════════════════════
 *  ৩. LoginAlertTemplate — শুধুমাত্র HTML তৈরি করে (কোনো মেইল পাঠায় না)
 * ═══════════════════════════════════════════════════════════════════════════ */

final class LoginAlertTemplate
{
    /** ইমেইল ক্লায়েন্টে ওয়েব-ফন্ট লোড হয় না — সাইটের মতোই সিস্টেম ফন্ট স্ট্যাক */
    private const FONT_STACK = "'Segoe UI','Nirmala UI','Hind Siliguri','Noto Sans Bengali',Tahoma,Arial,sans-serif";

    public function __construct(
        private readonly string $logoUrl,
        private readonly string $loginPageUrl,
    ) {}

    /**
     * সম্পূর্ণ HTML ইমেইল তৈরি করে (মোবাইল রেসপন্সিভ, টেবিল-বেসড লেআউট)।
     *
     * @param bool $isAdminCopy অ্যাডমিন কপি হলে true — উপরে আলাদা ব্যাজ বসে।
     */
    public function render(LoginAlertContext $context, bool $isAdminCopy): string
    {
        $status     = $context->status;
        $accent     = $status->accentColor();
        $accentDeep = $status->accentColorDeep();
        $soft       = $status->softColor();
        $icon       = $this->esc($status->icon());
        $label      = $this->esc($status->label());
        $preheader  = $this->esc($status->preheader());
        $intro      = $this->esc($isAdminCopy ? $status->adminIntro() : $status->userIntro());
        $logoUrl    = $this->esc($this->logoUrl);
        $loginUrl   = $this->esc($this->loginPageUrl);
        $year       = $this->esc(LoginEmailAlert::toBanglaDigits(date('Y')));

        $adminBadge = $isAdminCopy
            ? '<div style="margin-top:10px;display:inline-block;background:#1e293b;border:1px solid #475569;color:#cbd5e1;'
              . 'font-size:11px;letter-spacing:1px;padding:4px 12px;border-radius:20px;">অ্যাডমিন কপি</div>'
            : '';

        $noteRow = $context->note !== ''
            ? $this->infoRow('মন্তব্য', $context->note)
            : '';

        $infoRows =
              $this->infoRow('ইউজার নাম', $context->accountName)
            . $this->infoRow('ইমেইল', $context->userEmail)
            . $this->infoRow('ফোন নম্বর', $context->phoneNumber)
            . $this->infoRow('ব্রাউজার', $context->browserName)
            . $this->infoRow('ডিভাইস মডেল', $context->deviceModel)
            . $this->infoRow('আইপি ঠিকানা', $context->ipAddress)
            . $this->infoRow('সময়', $context->occurredAtBangla)
            . $noteRow;

        $font = self::FONT_STACK;

        return <<<HTML
<!DOCTYPE html>
<html lang="bn" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<title>লগইন ইনফরমেশন</title>
<style type="text/css">
    body { margin:0; padding:0; width:100% !important; background:#0f172a; }
    img { border:0; outline:none; text-decoration:none; -ms-interpolation-mode:bicubic; }
    table { border-collapse:collapse !important; }
    a { text-decoration:none; }
    @media only screen and (max-width:620px) {
        .sk-shell   { width:100% !important; border-radius:0 !important; }
        .sk-pad     { padding-left:18px !important; padding-right:18px !important; }
        .sk-label   { display:block !important; width:100% !important; padding-bottom:2px !important; text-align:left !important; }
        .sk-value   { display:block !important; width:100% !important; padding-bottom:12px !important; }
        .sk-brand   { font-size:19px !important; }
        .sk-btn     { display:block !important; width:auto !important; }
        .sk-logo    { width:48px !important; height:48px !important; }
    }
</style>
</head>
<body style="margin:0;padding:0;background:#0f172a;font-family:{$font};">

<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;height:0;width:0;">{$preheader}</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0f172a;padding:24px 12px;">
  <tr>
    <td align="center">

      <table role="presentation" class="sk-shell" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 18px 45px rgba(0,0,0,0.45);">

        <!-- ═══ হেডার: পাশে লোগো, ডানে ব্র্যান্ড ও শিরোনাম ═══ -->
        <tr>
          <td class="sk-pad" style="background:#111111;padding:26px 30px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td width="72" valign="middle" style="width:72px;">
                  <img src="{$logoUrl}" alt="Sada Kalo Fashion" class="sk-logo" width="58" height="58" style="display:block;width:58px;height:58px;border-radius:12px;background:#ffffff;object-fit:contain;">
                </td>
                <td valign="middle" style="padding-left:16px;">
                  <div class="sk-brand" style="color:#ffffff;font-size:21px;font-weight:700;letter-spacing:.3px;line-height:1.3;">সাদা কালো ফ্যাশন</div>
                  <div style="color:#94a3b8;font-size:14px;font-weight:500;margin-top:4px;letter-spacing:.5px;">লগইন ইনফরমেশন</div>
                  {$adminBadge}
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ═══ স্ট্যাটাস ব্যানার: সফল সবুজ / ব্যর্থ লাল / ব্লক ক্রস ═══ -->
        <tr>
          <td align="center" style="background:{$accent};background-image:linear-gradient(to bottom,{$accent},{$accentDeep});padding:18px 20px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td valign="middle" style="padding-right:10px;">
                  <span style="display:inline-block;width:30px;height:30px;line-height:30px;text-align:center;border-radius:50%;background:rgba(255,255,255,0.22);color:#ffffff;font-size:16px;font-weight:700;">{$icon}</span>
                </td>
                <td valign="middle">
                  <span style="color:#ffffff;font-size:18px;font-weight:700;letter-spacing:.3px;">{$label}</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ═══ ভূমিকা ═══ -->
        <tr>
          <td class="sk-pad" style="background:{$soft};padding:16px 30px;border-bottom:1px solid #e2e8f0;">
            <p style="margin:0;color:#334155;font-size:14px;line-height:1.75;">{$intro}</p>
          </td>
        </tr>

        <!-- ═══ বিস্তারিত তথ্য ═══ -->
        <tr>
          <td class="sk-pad" style="background:#ffffff;padding:24px 30px 8px 30px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              {$infoRows}
            </table>
          </td>
        </tr>

        <!-- ═══ লগইন পেজে যাওয়ার বাটন ═══ -->
        <tr>
          <td align="center" class="sk-pad" style="background:#ffffff;padding:22px 30px 26px 30px;">
            <a href="{$loginUrl}" target="_blank" rel="noopener" class="sk-btn" style="display:inline-block;background:#111111;background-image:linear-gradient(to bottom,#1e293b,#0f172a);color:#ffffff;font-size:15px;font-weight:700;padding:14px 34px;border-radius:10px;letter-spacing:.4px;">লগইন পেজে যান</a>
            <div style="margin-top:12px;">
              <a href="{$loginUrl}" target="_blank" rel="noopener" style="color:#64748b;font-size:12px;">{$loginUrl}</a>
            </div>
          </td>
        </tr>

        <!-- ═══ সতর্কবার্তা ═══ -->
        <tr>
          <td class="sk-pad" style="background:#ffffff;padding:0 30px 26px 30px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fef2f2;border-left:4px solid #dc2626;border-radius:8px;">
              <tr>
                <td style="padding:16px 18px;">
                  <p style="margin:0;color:#7f1d1d;font-size:13.5px;line-height:1.8;font-weight:600;">
                    এই লগইনটি যদি আপনি করে না থাকেন, তাহলে অতি জরুরি ভিত্তিতে অ্যাডমিনকে জানান এবং সাথে সাথে পাসওয়ার্ড পরিবর্তন করুন।
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ═══ ফুটার ═══ -->
        <tr>
          <td align="center" class="sk-pad" style="background:#0f172a;padding:20px 30px;">
            <p style="margin:0;color:#94a3b8;font-size:11.5px;line-height:1.8;">
              এই বার্তাটি সিস্টেম থেকে স্বয়ংক্রিয়ভাবে পাঠানো হয়েছে — অনুগ্রহ করে এর উত্তর দেবেন না।<br>
              © {$year} সাদা কালো ফ্যাশন — সর্বস্বত্ব সংরক্ষিত।
            </p>
          </td>
        </tr>

      </table>

    </td>
  </tr>
</table>
</body>
</html>
HTML;
    }

    /**
     * তথ্য টেবিলের একটি সারি (লেবেল | মান)। মোবাইলে লেবেল ও মান স্ট্যাক হয়ে যায়।
     */
    private function infoRow(string $label, string $value): string
    {
        $safeLabel = $this->esc($label);
        $safeValue = $this->esc($value !== '' ? $value : LoginEmailAlert::UNKNOWN_VALUE);

        return <<<ROW
              <tr>
                <td class="sk-label" width="140" valign="top" style="width:140px;padding:0 12px 14px 0;color:#64748b;font-size:13px;font-weight:600;white-space:nowrap;">{$safeLabel}</td>
                <td class="sk-value" valign="top" style="padding:0 0 14px 0;color:#0f172a;font-size:14px;font-weight:600;word-break:break-word;border-bottom:1px solid #f1f5f9;">{$safeValue}</td>
              </tr>
ROW;
    }

    /** XSS প্রতিরোধ — প্রতিটি ডাইনামিক মান এনকোড করা হয় */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
 *  ৪. LoginEmailAlert — পাবলিক API, ভল্ট রিডিং, ডিভাইস শনাক্তকরণ ও প্রেরণ
 * ═══════════════════════════════════════════════════════════════════════════ */

final class LoginEmailAlert
{
    /** সিকিউরিটি ভল্ট (.env) — db_connect.php-এর VAULT_PATH-এর সাথে অভিন্ন */
    private const VAULT_PATH = '/home/sadakalo/App/.env';

    /** লগ ফোল্ডার — প্রজেক্ট রুটের Logs/ (LoginLogger-এর মতোই) */
    private const LOG_DIRECTORY = __DIR__ . '/../Logs';

    private const ERROR_LOG_FILE = 'error_log.txt';

    /** কোনো তথ্য শনাক্ত না হলে মিথ্যা দাবি না করে এটাই দেখানো হয় */
    public const UNKNOWN_VALUE = 'শনাক্ত করা যায়নি';

    private const DEFAULT_LOGO_URL  = 'https://sadakalofashion.com/logo.png';
    private const DEFAULT_LOGIN_URL = 'https://sadakalofashion.com/index.php';

    /** অ্যাডমিন ইমেইলের সম্ভাব্য কী (উপরেরটা আগে খোঁজা হয়) */
    private const ADMIN_KEYS = ['ADMIN_Mail', 'ADMIN_MAIL', 'ADMIN_EMAIL', 'ADMIN_Email'];

    private const TIMEZONE = 'Asia/Dhaka';

    /** মেইল পাঠাতে সর্বোচ্চ কত সেকেন্ড ব্যয় করা যাবে */
    private const MAIL_TIME_BUDGET = 30;

    /** @var array<int,LoginAlertContext> রেসপন্স শেষ হওয়ার পর পাঠানোর কিউ */
    private static array $pendingQueue = [];

    private static bool $shutdownHookRegistered = false;

    /** @var array<string,string>|null ভল্ট একবারই পড়া হয় (per-request cache) */
    private static ?array $vaultCache = null;

    /** ইনস্ট্যান্স বানানোর প্রয়োজন নেই — পুরোটাই স্ট্যাটিক সার্ভিস */
    private function __construct() {}

    /* ─────────────────────── পাবলিক API ─────────────────────── */

    /** সফল লগইনের অ্যালার্ট (সবুজ ✔) */
    public static function success(
        string $accountName,
        string $userEmail = '',
        string $phoneNumber = '',
        string $note = ''
    ): void {
        self::dispatch(LoginAlertStatus::Success, $accountName, $userEmail, $phoneNumber, $note);
    }

    /** ব্যর্থ লগইনের অ্যালার্ট (লাল ⚠) — ভুল পাসওয়ার্ডে প্রতিবার */
    public static function failed(
        string $accountName,
        string $userEmail = '',
        string $phoneNumber = '',
        string $note = ''
    ): void {
        self::dispatch(LoginAlertStatus::Failed, $accountName, $userEmail, $phoneNumber, $note);
    }

    /** ব্লক/লক অ্যাকাউন্টের অ্যালার্ট (ক্রস ✖) */
    public static function blocked(
        string $accountName,
        string $userEmail = '',
        string $phoneNumber = '',
        string $note = ''
    ): void {
        self::dispatch(LoginAlertStatus::Blocked, $accountName, $userEmail, $phoneNumber, $note);
    }

    /**
     * ইভেন্টটি কিউতে রাখে। রেসপন্স ইউজারের কাছে চলে যাওয়ার পর ইমেইল যায়।
     * কোনো অবস্থাতেই এই মেথড এক্সেপশন ছুঁড়বে না — লগইন প্রবাহ কখনো ভাঙবে না।
     */
    public static function dispatch(
        LoginAlertStatus $status,
        string $accountName,
        string $userEmail = '',
        string $phoneNumber = '',
        string $note = ''
    ): void {
        try {
            self::$pendingQueue[] = self::buildContext($status, $accountName, $userEmail, $phoneNumber, $note);

            if (!self::$shutdownHookRegistered) {
                self::$shutdownHookRegistered = true;
                register_shutdown_function([self::class, 'flushQueue']);
            }
        } catch (\Throwable $exception) {
            self::logError('QUEUE_FAILED', $exception->getMessage());
        }
    }

    /**
     * শাটডাউন হুক — আগে রেসপন্স ছেড়ে দেয়, তারপর কিউয়ের ইমেইলগুলো পাঠায়।
     * (পাবলিক রাখা বাধ্যতামূলক, কারণ PHP এটিকে callable হিসেবে ডাকে।)
     */
    public static function flushQueue(): void
    {
        if (self::$pendingQueue === []) {
            return;
        }

        $queue = self::$pendingQueue;
        self::$pendingQueue = [];

        self::releaseResponse();

        foreach ($queue as $context) {
            try {
                self::deliver($context);
            } catch (\Throwable $exception) {
                self::logError('DELIVERY_FAILED', $exception->getMessage());
            }
        }
    }

    /* ─────────────────────── কনটেক্সট তৈরি ─────────────────────── */

    private static function buildContext(
        LoginAlertStatus $status,
        string $accountName,
        string $userEmail,
        string $phoneNumber,
        string $note
    ): LoginAlertContext {
        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));

        $cleanEmail = trim($userEmail);
        if ($cleanEmail !== '' && filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) === false) {
            $cleanEmail = '';
        }

        return new LoginAlertContext(
            status:           $status,
            accountName:      self::sanitizeText($accountName, 80),
            userEmail:        self::sanitizeText($cleanEmail, 254),
            phoneNumber:      self::sanitizeText($phoneNumber, 30),
            browserName:      self::detectBrowser(),
            deviceModel:      self::detectDeviceModel(),
            ipAddress:        self::detectIpAddress(),
            occurredAtBangla: self::toBanglaDateTime($now),
            occurredAtIso:    $now->format('Y-m-d H:i:s T'),
            note:             self::sanitizeText($note, 160),
        );
    }

    /* ─────────────────────── প্রেরণ ─────────────────────── */

    private static function deliver(LoginAlertContext $context): void
    {
        $vault = self::readVault();

        $senderAddress = self::pickEmail($vault, $context->status->senderKeys());
        if ($senderAddress === '') {
            $senderAddress = self::fallbackSender();
        }

        $adminAddress = self::pickEmail($vault, self::ADMIN_KEYS);

        $template = new LoginAlertTemplate(
            logoUrl:      self::readVaultUrl($vault, 'Login_Alert_Logo', self::DEFAULT_LOGO_URL),
            loginPageUrl: self::readVaultUrl($vault, 'Login_Alert_Url', self::DEFAULT_LOGIN_URL),
        );

        $subject       = $context->status->subjectFor($context->accountName);
        $throttleLimit = self::readVaultInt($vault, 'Login_Mail_Throttle', 0);

        /* ─── ১ম কপি: ইউজারের কাছে ─── */
        if ($context->userEmail !== '') {
            if (self::isFlooding($context, $context->userEmail, $throttleLimit)) {
                self::logError('THROTTLED', 'ইউজার কপি বাদ (flood guard): ' . $context->userEmail);
            } else {
                self::send(
                    recipient: $context->userEmail,
                    subject:   $subject,
                    htmlBody:  $template->render($context, false),
                    sender:    $senderAddress,
                    context:   $context,
                );
            }
        } else {
            self::logError(
                'USER_COPY_SKIPPED',
                'ইউজারের বৈধ ইমেইল পাওয়া যায়নি | Account: ' . $context->accountName
                . ' | Status: ' . $context->status->value
            );
        }

        /* ─── ২য় কপি: অ্যাডমিনের কাছে ─── */
        if ($adminAddress === '') {
            self::logError('ADMIN_COPY_SKIPPED', 'ভল্টে ADMIN_Mail কী পাওয়া যায়নি।');
            return;
        }

        if (strcasecmp($adminAddress, $context->userEmail) === 0) {
            return; // ইউজার আর অ্যাডমিন একই ঠিকানা — ডুপ্লিকেট মেইল পাঠানো হবে না।
        }

        if (self::isFlooding($context, $adminAddress, $throttleLimit)) {
            self::logError('THROTTLED', 'অ্যাডমিন কপি বাদ (flood guard): ' . $adminAddress);
            return;
        }

        self::send(
            recipient: $adminAddress,
            subject:   $subject,
            htmlBody:  $template->render($context, true),
            sender:    $senderAddress,
            context:   $context,
        );
    }

    /**
     * PHP কোরের mail() দিয়ে একটি HTML ইমেইল পাঠায়।
     * বাংলা টেক্সট নিরাপদে পৌঁছাতে Subject → RFC 2047 Base64,
     * Body → RFC 2045 Base64 (chunked) এনকোড করা হয়।
     */
    private static function send(
        string $recipient,
        string $subject,
        string $htmlBody,
        string $sender,
        LoginAlertContext $context
    ): bool {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            self::logError('INVALID_RECIPIENT', $recipient);
            return false;
        }

        // Header Injection প্রতিরোধ — হেডারে যাওয়া প্রতিটি মান থেকে CR/LF ছাঁটা।
        $safeSubject   = self::sanitizeHeader($subject);
        $safeRecipient = self::sanitizeHeader($recipient);
        $safeSender    = self::sanitizeHeader($sender);

        $encodedSubject = '=?UTF-8?B?' . base64_encode($safeSubject) . '?=';
        $encodedBody    = chunk_split(base64_encode($htmlBody), 76, "\r\n");

        $headers = implode("\r\n", [
            'From: =?UTF-8?B?' . base64_encode('সাদা কালো ফ্যাশন') . '?= <' . $safeSender . '>',
            'Reply-To: ' . $safeSender,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-Mailer: SadaKaloFashion-LoginEmailAlert/1.0 (PHP/' . PHP_VERSION . ')',
            'X-Auto-Response-Suppress: All',
            'Auto-Submitted: auto-generated',
            'X-Priority: ' . ($context->status === LoginAlertStatus::Success ? '3' : '1'),
        ]);

        $accepted = false;

        try {
            // Envelope sender (-f) দিলে বাউন্স হ্যান্ডলিং ও ডেলিভারেবিলিটি ভালো হয়।
            // কিছু হোস্টে এটি নিষিদ্ধ — সেক্ষেত্রে সাধারণভাবে আবার চেষ্টা করা হয়।
            $accepted = @mail($safeRecipient, $encodedSubject, $encodedBody, $headers, '-f' . $safeSender);

            if (!$accepted) {
                $accepted = @mail($safeRecipient, $encodedSubject, $encodedBody, $headers);
            }
        } catch (\Throwable $exception) {
            self::logError('MAIL_EXCEPTION', $exception->getMessage() . ' | To: ' . $safeRecipient);
            return false;
        }

        if (!$accepted) {
            self::logError(
                'MAIL_REJECTED',
                'To: ' . $safeRecipient . ' | Status: ' . $context->status->value
                . ' | Account: ' . $context->accountName
            );
        }

        return $accepted;
    }

    /* ─────────────────────── রেসপন্স রিলিজ (Non-blocking) ─────────────────────── */

    /**
     * ইউজারকে রেসপন্স/রিডাইরেক্ট আগে পাঠিয়ে দেয়, যাতে ইমেইল পাঠানোর সময়
     * ব্রাউজার অপেক্ষা না করে। PHP-FPM ও LiteSpeed — দুটোই সাপোর্ট করা হয়েছে।
     */
    private static function releaseResponse(): void
    {
        @ignore_user_abort(true);

        if (function_exists('set_time_limit')) {
            @set_time_limit(self::MAIL_TIME_BUDGET);
        }

        // সেশন লক ছেড়ে দেওয়া — নইলে ইউজারের পরের রিকোয়েস্ট আটকে থাকতে পারে।
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
            return;
        }

        if (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
            return;
        }

        // ফলব্যাক (mod_php): যতটা সম্ভব বাফার ছেড়ে দেওয়া হয়।
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }

    /* ─────────────────────── ভল্ট (.env) ─────────────────────── */

    /**
     * .env ফাইলটি কোথায় আছে তা খুঁজে বের করে — আগে VAULT_PATH, তারপর
     * প্রচলিত বিকল্প জায়গাগুলো। প্রথম যেটি পড়া যায় সেটিই ব্যবহৃত হয়।
     * (সার্ভারের ফোল্ডার-গঠন ভিন্ন হলেও ইমেইল এলার্ট নীরবে বন্ধ হবে না।)
     */
    private static function resolveVaultFile(): ?string
    {
        $candidatePaths = [
            self::VAULT_PATH,                  // /home/sadakalo/App/.env (db_connect.php-এর মতো)
            dirname(__DIR__, 2) . '/App/.env', // public_html/Services থেকে → /home/sadakalo/App/.env
            dirname(__DIR__) . '/.env',        // public_html/.env
            dirname(__DIR__, 2) . '/.env',     // /home/sadakalo/.env
            __DIR__ . '/.env',                 // Services/.env
        ];

        foreach ($candidatePaths as $candidatePath) {
            if (is_readable($candidatePath)) {
                return $candidatePath;
            }
        }

        return null;
    }

    /** @return array<string,string> */
    private static function readVault(): array
    {
        if (self::$vaultCache !== null) {
            return self::$vaultCache;
        }

        self::$vaultCache = [];

        $vaultFile = self::resolveVaultFile();

        if ($vaultFile === null) {
            self::logError('VAULT_UNREADABLE', self::VAULT_PATH . ' (বিকল্প কোনো পাথেও .env পাওয়া যায়নি)');
            return self::$vaultCache;
        }

        $parsed = @parse_ini_file($vaultFile, false, INI_SCANNER_RAW);
        if (!is_array($parsed)) {
            self::logError('VAULT_PARSE_FAILED', $vaultFile);
            return self::$vaultCache;
        }

        foreach ($parsed as $key => $value) {
            if (is_scalar($value)) {
                self::$vaultCache[(string) $key] = trim((string) $value, " \t\n\r\0\x0B\"'");
            }
        }

        return self::$vaultCache;
    }

    /**
     * তালিকার প্রথম বৈধ ইমেইল ঠিকানাটি ফিরিয়ে দেয়।
     *
     * @param array<string,string> $vault
     * @param array<int,string>    $keys
     */
    private static function pickEmail(array $vault, array $keys): string
    {
        foreach ($keys as $key) {
            $candidate = trim($vault[$key] ?? '');
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) {
                return $candidate;
            }
        }
        return '';
    }

    /** @param array<string,string> $vault */
    private static function readVaultUrl(array $vault, string $key, string $default): string
    {
        $candidate = trim($vault[$key] ?? '');

        if ($candidate === '' || filter_var($candidate, FILTER_VALIDATE_URL) === false) {
            return $default;
        }

        // শুধুমাত্র http/https — javascript: বা data: স্কিম ব্লক করা হয়।
        $scheme = strtolower((string) parse_url($candidate, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return $default;
        }

        return $candidate;
    }

    /** @param array<string,string> $vault */
    private static function readVaultInt(array $vault, string $key, int $default): int
    {
        $raw = trim($vault[$key] ?? '');
        if ($raw === '' || !ctype_digit($raw)) {
            return $default;
        }
        return (int) $raw;
    }

    /** ভল্টে প্রেরক না থাকলে ডোমেইন থেকে নিরাপদ ফলব্যাক তৈরি হয় */
    private static function fallbackSender(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'sadakalofashion.com');
        $host = (string) preg_replace('/[^a-z0-9.\-]/i', '', $host);

        if ($host === '' || !str_contains($host, '.')) {
            $host = 'sadakalofashion.com';
        }

        return 'no-reply@' . $host;
    }

    /* ─────────────────────── ফ্লাড গার্ড ─────────────────────── */

    /**
     * নিরাপত্তা ভালভ: বট দিয়ে একটানা লগইন চেষ্টা হলে হাজার হাজার মেইল গিয়ে
     * ডোমেইন ব্ল্যাকলিস্ট হতে পারে। .env-এ Login_Mail_Throttle="60" দিলে একই
     * প্রাপক+স্ট্যাটাসে ৬০ সেকেন্ডে একটির বেশি মেইল যাবে না।
     * ডিফল্ট 0 = বন্ধ, অর্থাৎ আপনার নির্দেশ মতো প্রতিবারই মেইল যাবে।
     */
    private static function isFlooding(LoginAlertContext $context, string $recipient, int $windowSeconds): bool
    {
        if ($windowSeconds <= 0) {
            return false;
        }

        $directory = self::LOG_DIRECTORY;
        if (!self::ensureLogDirectory()) {
            return false;
        }

        $fingerprint = substr(
            hash('sha256', strtolower($recipient) . '|' . $context->status->value . '|' . $context->ipAddress),
            0,
            32
        );
        $guardFile = $directory . '/.mail_guard_' . $fingerprint;

        if (is_file($guardFile)) {
            $lastSent = (int) @filemtime($guardFile);
            if ($lastSent > 0 && (time() - $lastSent) < $windowSeconds) {
                return true;
            }
        }

        @touch($guardFile);
        self::pruneGuardFiles($directory);

        return false;
    }

    /** পুরোনো গার্ড ফাইল মাঝে মাঝে পরিষ্কার করা হয় (২% রিকোয়েস্টে) */
    private static function pruneGuardFiles(string $directory): void
    {
        try {
            if (random_int(1, 50) !== 1) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $files = @glob($directory . '/.mail_guard_*');
        if (!is_array($files)) {
            return;
        }

        $expiry = time() - 86400;
        foreach ($files as $file) {
            if (is_file($file) && (int) @filemtime($file) < $expiry) {
                @unlink($file);
            }
        }
    }

    /* ─────────────────────── ডিভাইস শনাক্তকরণ ─────────────────────── */

    /** ব্রাউজারের নাম ও ভার্সন — যেমন "Chrome 126" */
    private static function detectBrowser(): string
    {
        $userAgent = self::readServer('HTTP_USER_AGENT');
        if ($userAgent === '') {
            return self::UNKNOWN_VALUE;
        }

        $nameMap = [
            '/edg\//i'          => 'Edge',
            '/opr\/|opera/i'    => 'Opera',
            '/samsungbrowser/i' => 'Samsung Internet',
            '/ucbrowser/i'      => 'UC Browser',
            '/firefox|fxios/i'  => 'Firefox',
            '/chrome|crios/i'   => 'Chrome',
            '/safari/i'         => 'Safari',
            '/msie|trident/i'   => 'Internet Explorer',
        ];

        $browserName = self::UNKNOWN_VALUE;
        foreach ($nameMap as $pattern => $label) {
            if (preg_match($pattern, $userAgent) === 1) {
                $browserName = $label;
                break;
            }
        }

        if ($browserName === self::UNKNOWN_VALUE) {
            return self::UNKNOWN_VALUE;
        }

        $versionPatterns = [
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

        foreach ($versionPatterns as $pattern) {
            if (preg_match($pattern, $userAgent, $matches) === 1) {
                $majorVersion = explode('.', $matches[1])[0];
                $operatingSystem = self::detectOperatingSystem($userAgent);

                return $operatingSystem === self::UNKNOWN_VALUE
                    ? $browserName . ' ' . $majorVersion
                    : $browserName . ' ' . $majorVersion . ' (' . $operatingSystem . ')';
            }
        }

        return $browserName;
    }

    private static function detectOperatingSystem(string $userAgent): string
    {
        $platformHint = trim(self::readServer('HTTP_SEC_CH_UA_PLATFORM'), "\" ");
        if ($platformHint !== '' && strtolower($platformHint) !== 'unknown') {
            return $platformHint;
        }

        $map = [
            '/windows nt 10/i'      => 'Windows 10/11',
            '/windows nt 6\.3/i'    => 'Windows 8.1',
            '/windows nt 6\.1/i'    => 'Windows 7',
            '/windows/i'            => 'Windows',
            '/android/i'            => 'Android',
            '/iphone|ipad|ipod/i'   => 'iOS',
            '/mac os x|macintosh/i' => 'macOS',
            '/cros/i'               => 'ChromeOS',
            '/linux/i'              => 'Linux',
        ];

        foreach ($map as $pattern => $label) {
            if (preg_match($pattern, $userAgent) === 1) {
                return $label;
            }
        }

        return self::UNKNOWN_VALUE;
    }

    /**
     * ডিভাইস মডেল — আধুনিক ব্রাউজার প্রাইভেসির কারণে সবসময় পাওয়া যায় না।
     * তাই আগে Client Hint দেখা হয়, না পেলে User-Agent, কিছুই না পেলে
     * "শনাক্ত করা যায়নি" — কখনো ভুল দাবি করা হয় না।
     */
    private static function detectDeviceModel(): string
    {
        $modelHint = trim(self::readServer('HTTP_SEC_CH_UA_MODEL'), "\" ");
        if ($modelHint !== '' && strtolower($modelHint) !== 'unknown') {
            return $modelHint;
        }

        $userAgent = self::readServer('HTTP_USER_AGENT');
        if ($userAgent === '') {
            return self::UNKNOWN_VALUE;
        }

        if (preg_match('/\biPhone\b/i', $userAgent) === 1) { return 'iPhone'; }
        if (preg_match('/\biPad\b/i', $userAgent) === 1)   { return 'iPad'; }
        if (preg_match('/\biPod\b/i', $userAgent) === 1)   { return 'iPod'; }

        // উদাহরণ: "(Linux; Android 13; SM-G991B Build/...)" → SM-G991B
        if (preg_match('/android[^;]*;\s*([^;)]+?)\s+build\//i', $userAgent, $matches) === 1) {
            $model = trim($matches[1]);
            if ($model !== '' && stripos($model, 'wv') !== 0) {
                return $model;
            }
        }

        if (preg_match('/android[\d.\s]*;\s*([^;)]+)\)/i', $userAgent, $matches) === 1) {
            $model = trim((string) preg_replace('/\bBuild.*$/i', '', $matches[1]));
            if ($model !== '' && strtolower($model) !== 'k' && stripos($model, 'wv') !== 0) {
                return $model;
            }
        }

        if (preg_match('/windows nt|macintosh|linux|cros/i', $userAgent) === 1) {
            return 'ডেস্কটপ / ল্যাপটপ';
        }

        return self::UNKNOWN_VALUE;
    }

    /**
     * নিরাপদ IP নির্ণয়। REMOTE_ADDR সবচেয়ে নির্ভরযোগ্য (spoof করা কঠিন);
     * X-Forwarded-For ইউজার-নিয়ন্ত্রিত হেডার বলে শুধু বৈধ পাবলিক IP গ্রহণ করা হয়।
     */
    private static function detectIpAddress(): string
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

        $remoteAddress = self::readServer('REMOTE_ADDR');
        if ($remoteAddress !== '' && filter_var($remoteAddress, FILTER_VALIDATE_IP) !== false) {
            return $remoteAddress;
        }

        return self::UNKNOWN_VALUE;
    }

    private static function readServer(string $key): string
    {
        $value = $_SERVER[$key] ?? '';
        if (!is_string($value)) {
            return '';
        }
        return trim(substr($value, 0, 512));
    }

    /* ─────────────────────── বাংলা তারিখ ও সময় ─────────────────────── */

    /** ইংরেজি সংখ্যা → বাংলা সংখ্যা (০-৯) */
    public static function toBanglaDigits(string $text): string
    {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'],
            $text
        );
    }

    /** যেমন: "বৃহস্পতিবার, ১৬ জুলাই ২০২৬ — বিকাল ০৫:৫৪ (বাংলাদেশ সময়)" */
    private static function toBanglaDateTime(\DateTimeImmutable $moment): string
    {
        $weekdays = [
            'Sunday'    => 'রবিবার',
            'Monday'    => 'সোমবার',
            'Tuesday'   => 'মঙ্গলবার',
            'Wednesday' => 'বুধবার',
            'Thursday'  => 'বৃহস্পতিবার',
            'Friday'    => 'শুক্রবার',
            'Saturday'  => 'শনিবার',
        ];

        $months = [
            'January'   => 'জানুয়ারি',
            'February'  => 'ফেব্রুয়ারি',
            'March'     => 'মার্চ',
            'April'     => 'এপ্রিল',
            'May'       => 'মে',
            'June'      => 'জুন',
            'July'      => 'জুলাই',
            'August'    => 'আগস্ট',
            'September' => 'সেপ্টেম্বর',
            'October'   => 'অক্টোবর',
            'November'  => 'নভেম্বর',
            'December'  => 'ডিসেম্বর',
        ];

        $hour = (int) $moment->format('G');
        $dayPart = match (true) {
            $hour <= 3  => 'রাত',
            $hour <= 5  => 'ভোর',
            $hour <= 11 => 'সকাল',
            $hour <= 14 => 'দুপুর',
            $hour <= 17 => 'বিকাল',
            $hour <= 19 => 'সন্ধ্যা',
            default     => 'রাত',
        };

        $formatted = sprintf(
            '%s, %s %s %s — %s %s (বাংলাদেশ সময়)',
            $weekdays[$moment->format('l')],
            $moment->format('j'),
            $months[$moment->format('F')],
            $moment->format('Y'),
            $dayPart,
            $moment->format('h:i')
        );

        return self::toBanglaDigits($formatted);
    }

    /* ─────────────────────── স্যানিটাইজেশন ও লগিং ─────────────────────── */

    /** কন্ট্রোল ক্যারেক্টার সরিয়ে নিরাপদ, নির্দিষ্ট দৈর্ঘ্যের টেক্সট ফেরত দেয় */
    private static function sanitizeText(string $value, int $maxLength): string
    {
        $value = str_replace(["\r", "\n", "\t", "\0"], ' ', $value);
        $value = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($value === '') {
            return '';
        }

        // UTF-8 নিরাপদভাবে কাটা (mbstring ছাড়াই — মাল্টিবাইট অক্ষর ভাঙে না)।
        if (strlen($value) > $maxLength) {
            $value = (string) preg_replace('/(?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+)*$/u', '', substr($value, 0, $maxLength));
            $value = trim($value);
        }

        return $value;
    }

    /** ইমেইল হেডার ইনজেকশন প্রতিরোধ — CR / LF / NUL সম্পূর্ণ মুছে ফেলা হয় */
    private static function sanitizeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0", '%0a', '%0d', '%0A', '%0D'], '', $value));
    }

    private static function ensureLogDirectory(): bool
    {
        $directory = self::LOG_DIRECTORY;

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        // লগে IP/ডিভাইস তথ্য (PII) থাকে — ব্রাউজার থেকে সরাসরি অ্যাক্সেস বন্ধ।
        $htaccess = $directory . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents(
                $htaccess,
                "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
            );
        }

        return true;
    }

    /**
     * সব এরর নীরবে Logs/error_log.txt-এ যায় — ইউজার কখনো কিছু দেখে না।
     * এই মেথড নিজে কখনো এক্সেপশন ছোঁড়ে না।
     */
    private static function logError(string $event, string $message): void
    {
        try {
            if (!self::ensureLogDirectory()) {
                return;
            }

            $line = sprintf(
                '[%s] LOGIN_EMAIL_ALERT | %-18s | %s%s',
                date('Y-m-d H:i:s'),
                self::sanitizeText($event, 30),
                self::sanitizeText($message, 400),
                PHP_EOL
            );

            @file_put_contents(
                self::LOG_DIRECTORY . '/' . self::ERROR_LOG_FILE,
                $line,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable) {
            // লগ লিখতে না পারলেও অ্যাপ কখনো থামবে না।
        }
    }
}
