# 🏦 Accounts/ — DPS + Loan সাব-মডিউল হাব

> **ফোল্ডার:** `public_html/Accounts/`
> **সংস্করণ:** **v1.**
> **কাজ:** DPS (ডিপিএস) + Loan (লোন) — ২টি পূর্ণাঙ্গ MVC সাব-মডিউল

## 📄 ফাইল তালিকা (টপ-লেভেল)

| ফাইল | লাইন | কাজ |
|------|------|-----|
| `index.php` | ১৮২ | ফোল্ডার গার্ড (সেশন + এরর লগ) |
| `central_cron.php` | ১৭৭ | ⏰ কেন্দ্রীয় ক্রন (লোন/DPS অটো প্রসেস) |
| `styles.css` | — | শেয়ার্ড স্টাইল |
| `logo.png` + ব্যানার | — | ব্র্যান্ড |
| `Logs/error_log.txt` | — | এরর লগ |
| `Dps/` | — | DPS মডিউল (পূর্ণ MVC) |
| `Loan/` | — | লোন মডিউল (MVC) |

---

## 🔍 মূল লজিক

### `central_cron.php`
- `CronAutomationService` ক্লাস
- `runCron()` — নির্দিষ্ট সময়ে চালায়
- `processLoans()` — লোনের মাসিক সুদ/কিস্তি অটো
- DPS মাসিক সুদ যোগ

### `Dps/` — DPS মডিউল (API + MVC)
| ফাইল | কাজ |
|------|-----|
| `index.php` | DPS ড্যাশবোর্ড এন্ট্রি |
| `dps_dashboard.php` | DPS ওভারভিউ |
| `api.php` | AJAX API (ডিপোজিট/উইথড্র)
| `cron_dps_interest.php` | মাসিক সুদ ক্রন |
| `core/` | autoload, Database, Router, SecurityHelper, ImageUploader, DateHelper |
| `controllers/` | Account, Ledger, Report, Dashboard কন্ট্রোলার |
| `models/` | DpsAccountModel, DpsLedgerModel, DpsReportModel |
| `views/` | dashboard, accounts(form/list/edit), ledger(deposit/withdraw/table), reports/monthly |
| `sql/migration.sql` | টেবিল স্কিমা |
| `README.md` | নিজস্ব ডক (v1) |

### `Loan/` — লোন মডিউল (MVC)
| ফাইল | কাজ |
|------|-----|
| `index.php` | লোন ড্যাশবোর্ড |
| `cron_sms_reminder.php` | কিস্তি রিমাইন্ডার SMS ক্রন |
| `Core/` | Router, BaseController, BaseModel, Enums |
| `Helpers/` | helper, ImageUpload, SmsService |
| `Modules/Loan/` | লোন CRUD + print |
| `Modules/Repayment/` | কিস্তি পরিশোধ |
| `Modules/Report/` | লোন রিপোর্ট |
| `Loan-V-3.01_README.md` | নিজস্ব ডক (v3.01) |

---

*📦 Accounts — v1. · SADA KALO FASHION*
