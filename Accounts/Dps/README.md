# DPS Dashboard — MVC রিফ্যাক্টর (আপডেট: "public" ফোল্ডার আর নেই)

## ফোল্ডার কোথায় বসাবেন

জিপ ফাইলের ভেতরে যা আছে (README.md, root-files-to-place/ ছাড়া বাকি সব) তা
সরাসরি আপনার এই ফোল্ডারের ভেতরে রাখুন:
```
public_html/Accounts/Dps/
```

extract করার পর গঠনটা এমন দেখতে হবে:
```
public_html/Accounts/Dps/
├── index.php          ← ব্রাউজারে এটাই খুলবেন
├── api.php
├── assets/
├── config/
├── core/
├── models/
├── controllers/
├── views/
├── uploads/
└── sql/
```
**কোনো "public" নামের সাব-ফোল্ডার নেই এখন** — সব সরাসরি `Dps/` এর ভিতরে।

ব্রাউজারে খুলবেন:
```
https://www.sadakalofashion.com/Accounts/Dps/index.php
```

## যাচাই/সমন্বয় করুন

1. **`config/config.php`** → `DPS_UPLOAD_URL` ইতিমধ্যে `/Accounts/Dps/uploads/accounts` সেট করা আছে — মিলিয়ে দেখুন।
2. **`index.php`** ও **`api.php`**-এর ভেতরে `$PUBLIC_HTML_ROOT = realpath(__DIR__ . '/../../')` — মডিউল যদি `Accounts/Dps/` থেকে ভিন্ন গভীরতায় বসান, তাহলে শুধু এই লাইনের `../../` সংখ্যা সমন্বয় করুন (Dps ফোল্ডার public_html থেকে যত ধাপ গভীরে, তত সংখ্যক `../`)।

## লগইন/সিকিউরিটি সিস্টেম কীভাবে সংযুক্ত হয়েছে

আপনার আসল `db_connect.php` ও `Core/AuthKernel.php` — কোনো ডুপ্লিকেট/ডামি অথ-লজিক তৈরি করা হয়নি:

- `index.php` ও `api.php` — দুটোই প্রথমে `public_html/db_connect.php` require করে (সেশন শুরু হয় secure cookie flags সহ, ভল্ট থেকে DB কানেক্ট হয়)
- তারপর `public_html/Core/AuthKernel.php` require করে `AuthKernel::enforce($conn)` কল করে — single-active-session, ২০-মিনিট idle-timeout, ব্লক-চেক প্রয়োগ করে

**⚠️ প্রয়োজনীয়:** `public_html/Core/AuthKernel.php` ফাইলটা সার্ভারে থাকা দরকার। জিপের ভেতরে `root-files-to-place/Core/AuthKernel.php`-এ রাখা আছে — `public_html/Core/` ফোল্ডারে এটা না থাকলে কপি করে দিন (আগে থেকে থাকলে কিছু করার দরকার নেই)।

## ডেটাবেজ মাইগ্রেশন
একবার phpMyAdmin-এ রান করুন:
```
sql/migration.sql
```
এটি `photo_path` কলাম ও `daily_cron_log` টেবিল (না থাকলে) যোগ করে।

## ফোল্ডার পারমিশন
- সব ফোল্ডার: 755
- সব ফাইল: 644
- `uploads/accounts/` — লেখার অনুমতি (PHP ছবি সেভ করবে), কিন্তু ভেতরের `.htaccess` স্ক্রিপ্ট এক্সিকিউশন বন্ধ রাখে — এটা সরাবেন না।

## গঠন
```
config/        → কনফিগারেশন (path, upload limit) — .htaccess দিয়ে ব্রাউজ-ব্লকড
core/          → Database, SecurityHelper, Router, ImageUploader, DateHelper, autoload — ব্লকড
models/        → DpsAccountModel, DpsLedgerModel, DpsReportModel — ব্লকড
controllers/   → AccountController, LedgerController, ReportController, DashboardController — ব্লকড
views/         → HTML partial (account/ledger/report/profile আলাদা)
assets/        → css/js/img (ব্রাউজার থেকে সরাসরি অ্যাক্সেসযোগ্য, দরকার অনুযায়ী)
uploads/       → আপলোড করা একাউন্ট ছবি (স্ক্রিপ্ট-এক্সিকিউশন বন্ধ)
sql/           → migration.sql
index.php      → মূল পেজ
api.php        → AJAX endpoint
```

## নতুন ফিচার যা যোগ করা হয়েছে
- অ্যাকাউন্ট/জমা/উত্তোলন/এডিট — সব আলাদা ফর্ম ও endpoint (MVC আলাদা)
- একাউন্ট প্রোফাইল ছবি: গ্যালারি বা সরাসরি ক্যামেরা, GD দিয়ে অটো-রিসাইজ, ১০MB হার্ড লিমিট
- মাসভিত্তিক ও সাপ্তাহিক রিপোর্ট
- আলাদা "প্রোফাইল" ভিউ
- কিস্তি বকেয়া/আজ হলে টপ-টোস্ট নোটিফিকেশন
- কম্প্যাক্ট বাটন, ক্লিন গ্রিড লেআউট
