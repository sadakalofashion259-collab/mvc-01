# 👥 Customer/ — কাস্টমার মডিউল (MVC)

> **ফোল্ডার:** `public_html/Customer/`
> **সংস্করণ:** **v1.1**
> **কাজ:** কাস্টমার ম্যানেজমেন্ট (CRUD + লেনদেন + SMS/Email + প্রোফাইল ছবি + Audit Log)

## 📄 ফাইল তালিকা

| ফাইল | লাইন | কাজ |
|------|------|-----|
| `customers.php` | ১২৪৯ | 📋 কাস্টমার লিস্ট (সার্চ, SMS, ফাইন্যান্সিয়াল সামারি) |
| `customer_profile.php` | ৮৪৩ | 👤 কাস্টমার প্রোফাইল (লেনদেন, কালেকশন SMS) |
| `customer_bottom_nav.php` | ৫৪ | মোবাইল বটম নেভ |
| `Controllers/CustomerController.php` | ৭০৩ | কন্ট্রোলার (CRUD + SMS + CSRF) |
| `Models/CustomerModel.php` | ৪৭২ | ডাটাবেজ মডেল |
| `Models/CustomerModelInterface.php` | ৩৪ | ইন্টারফেস |
| `Models/SettingsModel.php` | ৮০ | সেটিংস (table ensure, get/set) |
| `config/bootstrap.php` | ৯১ | বুটস্ট্র্যাপ |
| `config/Database.php` | ৬৪ | PDO কানেকশন |
| `config/Env.php` | ৮৫ | এনভায়রনমেন্ট ভারিয়েবল |
| `Helpers/ImageUploader.php` | ১০৬ | ছবি সেভ/ডিলিট |
| `Helpers/AuditInit.php` | — | 🆕 Audit Logger বুটস্ট্র্যাপ |
| `Services/MailService.php` | ৭৭ | লেনদেন ইমেইল নোটিশ |
| `Services/SmsService.php` | ১২৪ | SMS (normalisePhone, send, log) |
| `Customer. ভার্সন --01.txt` | — | ভার্সন নোট |

---

## 🔍 মূল লজিক

### `CustomerModel.php`
- `getCustomerFinancialSummary()` — কাস্টমার মোট ক্রয়/বাকি/পেইড
- `getActiveCustomers()` / `getInactiveCustomers()` — স্ট্যাটাস ফিল্টার
- `addCustomer()` / `updateCustomer()` — CRUD

### `CustomerController.php`
- `isCsrfValid()` — CSRF গার্ড
- `sendSms()` — কালেকশন SMS
- লেনদেন এন্ট্রি + প্রোফাইল ছবি আপলোড

### `customers.php`
- AJAX সার্চ + পেজিনেশন
- কাস্টমার কার্ডে বাকি টাকা ব্যাজ
- SMS গ্লোবাল টগল
- থিম টগল + টোস্ট নোটিফিকেশন

### `customer_profile.php`
- লেনদেন হিস্টরি (টাইমলাইন)
- কালেকশন SMS পাঠানোর বাটন
- `sendCollectionSms()` JS

---

## 🆕 Changelog — v1.1 (Audit Integration)

### নতুন
- `Helpers/AuditInit.php` — `AuditInit::boot($conn)`

### আপডেট
| ফাইল | পরিবর্তন |
|------|----------|
| `config/bootstrap.php` | AuditInit boot |
| `Controllers/CustomerController.php` | CRUD + লেনদেন AuditLogger |
| `Models/CustomerModel.php` | `addCustomer()` / `addTransaction()` → ID রিটার্ন |
| `Models/CustomerModelInterface.php` | সিগনেচার |

### Audit কভারেজ
| অ্যাকশন | Module | Action |
|---------|--------|--------|
| কাস্টমার যোগ/এডিট/ডিলিট | `customers` | CREATE/UPDATE/DELETE |
| Active টগল / বিল লক | `customers` | UPDATE |
| লেনদেন যোগ/এডিট/ডিলিট | `customer_transactions` | CREATE/UPDATE/DELETE |

> নির্ভরতা: `public_html/Audit/` — না থাকলে নীরবে স্কিপ।

---

*📦 Customer — v1.1 · SADA KALO FASHION*
