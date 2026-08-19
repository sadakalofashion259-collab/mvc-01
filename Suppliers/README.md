# 🤝 Suppliers/ — সাপ্লায়ার মডিউল (হাইব্রিড MVC)

> **ফোল্ডার:** `public_html/Suppliers/`
> **সংস্করণ:** **v1.1**
> **কাজ:** সাপ্লায়ার ম্যানেজমেন্ট (CRUD + মেমো + SMS + লেনদেন + প্রোফাইল + Audit Log)

## 📄 ফাইল তালিকা

| ফাইল | লাইন | কাজ |
|------|------|-----|
| `suppliers.php` | ৬০১ | 📋 সাপ্লায়ার লিস্ট (সার্চ, থিম, SMS) |
| `supplier_profile.php` | ৬৫৭ | 👤 প্রোফাইল (লেনদেন হিস্টরি, WhatsApp শেয়ার) |
| `config.php` | ৬৪ | sk_env + অটোলোডার (+ Audit) |
| `Controllers/SupplierController.php` | ৫৮৩ | কন্ট্রোলার + AuditLogger কল |
| `Models/SupplierModel.php` | ৫২৩ | মডেল (hasTable, CRUD, ফাইন্যান্স) |
| `Models/SupplierModelInterface.php` | ৪০ | ইন্টারফেস |
| `Helper/Audit.php` | ৪৯ | 🆕 Audit Logger বুটস্ট্র্যাপ |
| `Helper/Database.php` | ৬০ | PDO কানেকশন |
| `Helper/ImageUploader.php` | ৮৭ | ছবি সেভ/ডিলিট |
| `Helper/Logger.php` | ৩০ | এরর/SMS লগ |
| `Helper/Security.php` | ১০৮ | boot, requireLogin, role |
| `Ajax/supplier_actions.php` | ২৫ | AJAX রাউটার |
| `Ajax/profile_actions.php` | ৩২ | প্রোফাইল AJAX |
| `Sms/SmsGateway.php` | ১৭২ | SMS গেটওয়ে (fromDb, config) |
| `Sms/SmsService.php` | ১৬১ | SMS সার্ভিস (টেমপ্লেট) |
| `Sms/index.php` | ১০৫ | SMS পেজ |
| `Views/sms_dashboard.view.php` | ১৫০ | SMS ড্যাশবোর্ড |
| `Img/memos/2026/07,08/` | — | মেমো ছবি |
| `Logs/` | — | error_log + sms_log |

---

## 🆕 Changelog — v1.1 (Audit Integration)

### নতুন ফাইল
| ফাইল | বিবরণ |
|------|--------|
| `Helper/Audit.php` | `Audit::init($conn)` — একবার কল করলেই `AuditLogger` প্রস্তুত |

### আপডেট করা ফাইল
| ফাইল | পরিবর্তন |
|------|----------|
| `config.php` | Autoloader-এ `Audit` ক্লাস যোগ |
| `Controllers/SupplierController.php` | Constructor-এ `Audit::init($conn)` + সব CRUD-এ `AuditLogger` কল |
| `Models/SupplierModel.php` | `addSupplier()` এখন `int` রিটার্ন করে (নতুন ID / ০) |
| `Models/SupplierModelInterface.php` | `addSupplier(): int` সিগনেচার |

### Audit লগ কভারেজ

| অ্যাকশন | Module | Action |
|---------|--------|--------|
| নতুন সাপ্লায়ার | `suppliers` | CREATE |
| সাপ্লায়ার এডিট | `suppliers` | UPDATE |
| স্ট্যাটাস টগল | `suppliers` | UPDATE |
| SMS টগল | `suppliers` | UPDATE |
| সাপ্লায়ার ডিলিট | `suppliers` | DELETE |
| নতুন লেনদেন | `supplier_transactions` | CREATE |
| লেনদেন এডিট | `supplier_transactions` | UPDATE |
| লেনদেন ডিলিট | `supplier_transactions` | DELETE |

### ব্যবহার
```php
// Controller constructor-এ স্বয়ংক্রিয় init
Audit::init($conn);

// এরপর যেকোনো জায়গায়:
AuditLogger::create('suppliers', $id, null, $newData);
AuditLogger::update('suppliers', $id, $old, $new);
AuditLogger::delete('suppliers', $id, $old);
```

> নির্ভরতা: রুট `public_html/Audit/` মডিউল থাকতে হবে। না থাকলে নীরবে স্কিপ — মূল কাজ বন্ধ হয় না।

---

## 🔍 মূল লজিক

### `SupplierModel.php`
- `getActiveSuppliers()` / `getInactiveSuppliers()`
- `getSupplierById()` — প্রোফাইল ডেটা
- `addSupplier()` → নতুন ID রিটার্ন (`int`)
- লেনদেন (দেওয়া/নেওয়া) হিসাব
- মেমো CRUD

### `SupplierController.php`
- `isEntryRole()` — এন্ট্রি রোল চেক
- `jsonExit()` — JSON রেসপন্স
- সাপ্লায়ার add/edit/delete + মেমো আপলোড
- `Audit::init()` + AuditLogger (CREATE / UPDATE / DELETE)

### `Helper/Audit.php`
- `Audit::init(PDO $conn)` — একবার; `AuditLogModel` + `AuditLogger` লোড
- `Audit::isReady()` — প্রস্তুত কিনা চেক

### `SmsGateway.php`
- `fromDb()` — DB থেকে গেটওয়ে কনফিগ
- `config()` — API এন্ডপয়েন্ট/কী

### `Helper/Security.php`
- `requireLogin()` — সেশন গার্ড
- `role()` — রোল চেক

### `suppliers.php`
- সার্চ + পেজিনেশন + SMS সেন্ড
- `toggleSearch()`, `doSearch()` JS

### `supplier_profile.php`
- লেনদেন টাইমলাইন
- `shareHistoryWA()` — WhatsApp-এ হিস্টরি শেয়ার

---

*📦 Suppliers — v1.1 · SADA KALO FASHION*
