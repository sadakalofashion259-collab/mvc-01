# 🗄️ Models/ — গ্লোবাল মডেল লেয়ার

> **ফোল্ডার:** `public_html/Models/`
> **সংস্করণ:** **v1.**
> **কাজ:** ডাটাবেজ অ্যাক্সেস মডেল (ইউজার, লগইন, সেলস, ড্যাশবোর্ড, SMS/Email)

## 📄 ফাইল তালিকা

| ফাইল | লাইন | কাজ |
|------|------|-----|
| `UserModel.php` | ৬৭ | ইউজার খোঁজা, OTP তৈরি/ভেরিফাই |
| `LoginModel.php` | ১৪৯ | লগইন ডেটা (ফোন/আইডি, লক, অ্যাটেম্পট) |
| `LoginLogModel.php` | ২২২ | লগইন লগ রেকর্ড + ফিল্টার + কাউন্ট |
| `SalesModel.php` | ৯১ | বিক্রয় ট্রানজেকশন (begin/commit/rollback) |
| `SalesModelInterface.php` | ১৪ | SalesModel ইন্টারফেস |
| `DashboardModel.php` | ১৩৫ | মেমো নম্বর, কাস্টমার, নোটিফিকেশন, অ্যালার্ট |
| `DashboardModelInterface.php` | ১৩ | DashboardModel ইন্টারফেস |
| `DailyReportModel.php` | ১২২ | দৈনিক রিপোর্ট/স্টাফ/ডিলিট |
| `BiometricModel.php` | ৮২ | বায়োমেট্রিক ক্রেডেনশিয়াল সেভ/গেট |
| `MailService.php` | ১২৬ | ইমেইল পাঠানো (OTP সহ) |
| `SmsService.php` | ৪৪ | SMS OTP পাঠানো |
| `NotificationService.php` | ৩৫ | পুশ নোটিফিকেশন |
| `ProfileModel_old.php` | ১৩৭ | প্রোফাইল (পুরনো) |
| `ProfileModelInterface_old.php` | ১৬ | পুরনো প্রোফাইল ইন্টারফেস |
| `StockModel.php.bak_old` / `StockModelInterface.php.bak_old` | — | স্টক মডেল ব্যাকআপ (অব্যবহৃত) |
| `index.php` | ১৮২ | ফোল্ডার গার্ড (সেশন + এরর লগ) |
| `inject_premium.css` | — | স্টাইল |
| `Interfaces/` | — | সব ইন্টারফেস (Biometric, Captcha, Login, Dashboard, Profile) |

---

## 🔍 মূল মডেল লজিক

### `LoginModel.php`
- `findByIdentifier()` — ইউজারনেম/ফোনে ইউজার খোঁজে
- `incrementFailedAttempts()` — ভুল পাসওয়ার্ড কাউন্ট
- `lockAccount()` — বারবার ভুল হলে লক
- `autoUnblockIfExpired()` — নির্দিষ্ট সময় পর আনলক

### `SalesModel.php`
- `beginTransaction()` / `commitTransaction()` / `rollBackTransaction()` — বিক্রয় এটমিক
- `getOrCreateDailyReportId()` — দিনের রিপোর্ট আইডি (না থাকলে তৈরি)
- `getNextMemoNumber()` — পরের মেমো নম্বর

### `DashboardModel.php`
- `getActiveCustomers()` — সক্রিয় কাস্টমার
- `getTodayCollectionAlerts()` — আজকের ক্যাশ কালেকশন অ্যালার্ট
- `saveAdminNotification()` / `getBroadcastNotices()` — নোটিশ
- `getPendingNotificationCount()` — অপঠিত কাউন্ট

### `BiometricModel.php`
- `saveCredential()` — WebAuthn ক্রেডেনশিয়াল সেভ
- `getByCredentialId()` / `updateSignCount()` — লগইন যাচাই

### `Interfaces/` (ইন্টারফেস কন্ট্রাক্ট)
- `LoginModelInterface` → findByIdentifier, lockAccount, autoUnblockIfExpired...
- `CaptchaServiceInterface` → verify, getErrorMessage
- `DashboardModelInterface` → getNextMemoNumber, getActiveCustomers...
- `BiometricModelInterface` → saveCredential, touchLastUsed, deleteCredential

---

*📦 Models — v1. · SADA KALO FASHION*
