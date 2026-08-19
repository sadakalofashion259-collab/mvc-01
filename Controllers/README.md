# 🎮 Controllers/ — গ্লোবাল কন্ট্রোলার লেয়ার

> **ফোল্ডার:** `public_html/Controllers/`
> **সংস্করণ:** **v1.**
> **কাজ:** সিস্টেমের মূল কন্ট্রোলার (লগইন, অথ, ড্যাশবোর্ড, বিক্রয়, প্রোফাইল)

## 📄 ফাইল তালিকা

| ফাইল | লাইন | কাজ |
|------|------|-----|
| `AdminController.php` | ৮৪ | অ্যাডমিন অ্যাকশন (টাইমড ব্লক, ইউজার টগল, ব্রডকাস্ট, ডিলিট) |
| `AuthController.php` | ১৯২ | OTP/অথ কন্ট্রোল (কুলডাউন, OTP প্রসেস) |
| `DailyReportController.php` | ১২১ | দৈনিক রিপোর্ট AJAX + পেজ ডেটা |
| `DashboardController.php` | ১৩০ | ড্যাশবোর্ড সেশন/সিকিউরিটি/CSRF |
| `LoginController.php` | ২৯৯ | লগইন লজিক (LoginResult + প্রসেস) |
| `ProfileController_old.php` | ৪৭১ | প্রোফাইল (পুরনো ভার্সন — ImageResizer সহ) |
| `SalesController.php` | ২০৭ | বিক্রয় প্রসেস + ইমেইল রিপোর্ট + ছবি সেভ |
| `WebAuthnController.php` | ২৭৫ | ফিঙ্গারপ্রিন্ট/ফেস (WebAuthn) হ্যান্ডলিং |
| `.htaccess` | — | ডাইরেক্ট অ্যাক্সেস ব্লক |

---

## 🔍 ফাইলওয়াইজ লজিক

### `AdminController.php`
- `setTimedBlock()` — ইউজারকে নির্দিষ্ট সময়ের জন্য ব্লক
- `toggleUserStatus()` — active/inactive টগল
- `broadcastMessage()` — সবাইকে নোটিশ
- `deleteUser()` — ইউজার ডিলিট

### `AuthController.php`
- `processOtpRequest()` — OTP তৈরি/ভেরিফাই
- `hasActiveOtp()` — সক্রিয় OTP আছে কিনা
- `getOtpCooldownSeconds()` — পুনরায় OTP পাঠানোর অপেক্ষা সময়

### `DailyReportController.php`
- `handleAjaxRequest()` — রিপোর্ট AJAX (add/delete)
- `getPageData()` — পেজের জন্য ডেটা লোড

### `DashboardController.php`
- `bootSessionAndSecurity()` — সেশন + গার্ড বুট
- `generateCsrfToken()` / `verifyCsrfToken()` — CSRF সুরক্ষা
- `syncProfilePicToSession()` — প্রোফাইল ছবি সেশন সিঙ্ক

### `LoginController.php`
- `LoginResult` ক্লাস — লগইন ফলাফল (success/setupRequired)
- `LoginController` — ইউজার/ফোন দিয়ে খোঁজা, পাসওয়ার্ড যাচাই, সেশন স্টার্ট, অ্যাকাউন্ট লক

### `SalesController.php`
- `processSaleRequest()` — বিক্রয় এন্ট্রি (ট্রানজেকশন)
- `sendEmailReport()` — বিক্রয় ইমেইল রিপোর্ট
- `saveImageToFolder()` — ছবি আপলোড

### `WebAuthnController.php`
- `requireLogin()` — লগইন চেক
- `fireLoginAlert()` — লগইন সতর্কতা
- `handle()` — WebAuthn রেজিস্ট্রেশন/অথেনটিকেশন

---

*📦 Controllers — v1. · SADA KALO FASHION*
