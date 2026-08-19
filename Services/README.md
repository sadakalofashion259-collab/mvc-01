# 🔧 Services/ — সার্ভিস লেয়ার

> **ফোল্ডার:** `public_html/Services/`
> **সংস্করণ:** **v1.**
> **কাজ:** সিকিউরিটি/লগ/সার্ভিস ক্লাস (সেশন, ক্যাপচা, ডিভাইস, SMS, WebAuthn)

## 📄 ফাইল তালিকা

| ফাইল | লাইন | কাজ |
|------|------|-----|
| `SessionGuard.php` | ১২৩ | সিঙ্গেল-অ্যাক্টিভ-সেশন গার্ড |
| `CaptchaService.php` | ১১৮ | Turnstile ক্যাপচা ভেরিফিকেশন |
| `DeviceInfo.php` | ২৫৯ | ডিভাইস/ব্রাউজার/IP তথ্য |
| `LoginLogger.php` | ৯২ | লগইন লগ ফাইল লেখা |
| `LoginEmailAlert.php` | ১২২০ | লগইন সতর্কতা ইমেইল (HTML টেমপ্লেট) |
| `WebAuthnService.php` | ২০৫ | WebAuthn (ফিঙ্গারপ্রিন্ট/ফেস) সার্ভিস |
| `Animation/Robot_Files.php` | ১৮৩ | Lottie অ্যানিমেশন প্লেয়ার |
| `index.php` | ১৮২ | ফোল্ডার গার্ড |

---

## 🔍 মূল সার্ভিস লজিক

### `SessionGuard.php`
- `issueToken()` — সেশনে টোকেন ইস্যু
- `enforce()` — অন্য ডিভাইস লগইন হলে আগেরটা লগআউট
- `clearToken()` / `forceLogout()` — সেশন শেষ

### `CaptchaService.php`
- `loadSecretKey()` — গোপন কী লোড
- `verify()` — Turnstile API কল করে ভেরিফাই
- `callApi()` — cURL রিকোয়েস্ট

### `DeviceInfo.php`
- `fromRequest()` — ইউজার-এজেন্ট, IP, ডিভাইস পার্স
- `resolveIpAddress()` — প্রকৃত IP বের করা (প্রক্সি সহ)
- `toSummaryLine()` — সংক্ষিপ্ত বিবরণ লাইন

### `LoginLogger.php`
- `record()` — লগইন সফল/ব্যর্থ ফাইলে লেখে
- `sanitize()` — লগ ইনজেকশন প্রতিরোধ

### `LoginEmailAlert.php` (১২২০ লাইন — বড়)
- প্রতিটি ইভেন্টের জন্য HTML ইমেইল টেমপ্লেট
- `label()`, `icon()`, `accentColor()` — ইভেন্ট স্টাইলিং
- `subjectFor()` — ইমেইল সাবজেক্ট

### `WebAuthnService.php`
- `newChallenge()` — র্যান্ডম চ্যালেঞ্জ
- `cbor()` — CBOR ডিকোড
- রেজিস্ট্রেশন/অথেনটিকেশন ভেরিফিকেশন

### `Animation/Robot_Files.php`
- `loadDotLottieScript()` — Lottie লাইব্রেরি লোড
- `mountPlayer()` — অ্যানিমেশন মাউন্ট
- `getCached()` / `saveCache()` — ক্যাশিং

---

*📦 Services — v1. · SADA KALO FASHION*
