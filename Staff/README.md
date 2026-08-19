# SADA KALO FASHION — Staff Management System

**Version 1** · Modular, self-contained staff module · Document root: `Staff/`

This module is a complete, independent restructuring of the legacy `Sadakalo_staff/` system into a clean modular architecture. It manages staff profiles, daily attendance, expenses/advances, payroll (payslips with OTP digital signature), and email verification — with SMS + Email notifications that the admin can switch on/off from the dashboard.

> **Scope guarantee:** Everything this module needs lives inside `Staff/`. The only outside dependency is the site-wide `public_html/db_connect.php` (loaded read-only through the local bridge `Staff/db_connect.php`) and the `.env` vault at `/home/sadakalo/App/.env`. Nothing outside `Staff/` is modified by this module.

---

## 1. Architecture Map

```
Staff/                              ← Document Root
│
├── index.php                       ← Dashboard (entry hub): quick-nav bar, menu grid,
│                                     Notification Settings (SMS/Email toggle), loan alerts
├── db_connect.php                  ← Database Connection (Common) — bridge to the root
│                                     public_html/db_connect.php (PDO + full auth stack)
├── README.md                       ← This file
│
├── Core/                           ← Core System Logic
│   ├── Config.php                  ← .env loader, SMS API config, Email routing,
│   │                                 notification toggle helpers
│   ├── Logger.php                  ← staff_log() / staff_log_exception() → Logs/error_log.txt
│   ├── SessionManager.php          ← Session timeout & security checks (page + AJAX guards)
│   └── settings.json               ← (auto-created) SMS/Email ON-OFF state
│
├── Services/                       ← SMS & Email Services
│   ├── SmsService.php              ← sendSMS(), sendExpenseSMS(), sendAttendanceSMS()
│   └── EmailService.php            ← sendAttendanceEmail(), sendExpenseEmail(),
│                                     staff_admin_bcc_header() (admin copies)
│
├── Modules/                        ← Independent Business Modules
│   │
│   ├── Staff/                      ← Staff Management Module
│   │   ├── Create.php              ← Add Staff Form (camera + gallery photo)
│   │   ├── SaveAjax.php            ← Save Staff Info AJAX (auto-verify + welcome SMS)
│   │   ├── List.php                ← Staff List (active/inactive cards, verify action)
│   │   ├── ProfileList.php         ← Profile List
│   │   ├── ProfileView.php         ← Single Profile View (stats, monthly nav, notes)
│   │   ├── InactiveList.php        ← Inactive Profile List
│   │   ├── InactiveHistory.php     ← Inactive Staff History (full ledger archive)
│   │   ├── AdminControl.php        ← Admin Management (full edit incl. photo,
│   │   │                             attendance correction, delete)
│   │   ├── ForceVerifyAjax.php     ← Admin Verify AJAX
│   │   └── NoteAjax.php            ← Save/Edit/Delete Note AJAX (photo notes)
│   │
│   ├── Attendance/                 ← Attendance Module
│   │   ├── Daily.php               ← Daily bulk attendance sheet
│   │   ├── History.php             ← Attendance history & filters
│   │   └── SaveAjax.php            ← Bulk save + carry-forward auto-apply + SMS/Email
│   │
│   ├── Expense/                    ← Expense Module
│   │   ├── Create.php              ← Expense/Advance entry form
│   │   ├── History.php             ← Expense history
│   │   ├── SaveAjax.php            ← Save + running-balance update + SMS/Email
│   │   └── DeleteAjax.php          ← Delete/Edit expense (balance re-adjusted)
│   │
│   ├── Payroll/                    ← Payslip & Settlement
│   │   ├── Payslip.php             ← Payslip builder + print + OTP signature panel
│   │   ├── SaveAjax.php            ← Save payslip / settlement + SMS
│   │   └── SignatureOtpAjax.php    ← Signature OTP send/verify (Email + SMS)
│   │
│   └── Verification/
│       └── EmailVerify.php         ← Token-based profile verify (OTP by Email + SMS)
│
├── Assets/
│   ├── css/
│   │   └── theme.css               ← Shared dark/light theme (all pages)
│   ├── js/
│   │   ├── app.js                  ← Shared theme-toggle helper
│   │   └── camera.js               ← Shared webcam capture helper (SkCamera)
│   └── images/                     ← logo.png, banner.jpg, brand images
│
├── Uploads/
│   ├── profiles/                   ← Staff profile pictures
│   └── notes/                      ← Note attachment photos
│
└── Logs/
    └── error_log.txt               ← Unified log (SMS/EMAIL/ATTENDANCE/EXPENSE/OTP tags)
```

---

## 2. How Each Piece Was Built

### 2.1 `db_connect.php` (Common Connection + AuthKernel)
Every Staff page, AJAX endpoint, and service route includes this single bridge, which runs three layers in order:

1. **Idle-timeout pre-check** — if the session has been idle ≥ 20 minutes, it redirects to the **absolute** `/index.php?status=timeout` *before* the root connector runs (the root connector's relative `Location: index.php` would 404 / loop from nested `Modules/<X>/` folders).
2. **Root connector** — `require_once dirname(__DIR__) . '/db_connect.php';` provides PDO `$conn` (credentials from the `.env` vault, never hardcoded) plus the legacy session/auth stack, completely unchanged.
3. **Unified AuthKernel enforcement** — `require_once dirname(__DIR__) . '/Core/AuthKernel.php';` then `AuthKernel::enforce($conn)` for every logged-in session:
   - 20-minute idle timeout (syncs `last_action_time` + `last_activity` so root and module timers never diverge)
   - **Single Active Session** (`users.active_session_token` verified, timing-safe)
   - `users.last_active` heartbeat
   - Account block check (403 page for blocked non-admins)
   - All redirects are absolute (`/index.php`) — safe from any sub-folder depth, no redirect loops.

This is the **same security pattern as the Suppliers, Customer, and Sales modules** — one kernel, one behaviour. It also closes a legacy gap: the root connector skips SessionGuard/block checks for any file named `index.php`, but AuthKernel enforces them on the Staff dashboard too.

Include paths by depth:
- from `Staff/` root → `include __DIR__ . '/db_connect.php';`
- from `Modules/<X>/` → `include __DIR__ . '/../../db_connect.php';`

### 2.2 `Core/`
- **Config.php** — loads `/home/sadakalo/App/.env` once (`staff_env()` helper) and defines all SMS/Email constants dynamically. Also owns the notification-toggle helpers (`staff_sms_enabled()`, `staff_email_enabled()`, `staff_save_notification_settings()`), backed by `Core/settings.json`.
- **Logger.php** — `staff_log($tag, $message)` appends tagged, timestamped lines to `Logs/error_log.txt`.
- **SessionManager.php** — `SessionManager::guardPage()` (20-min timeout, login check, optional admin-only), `SessionManager::guardAjax()` (JSON `Unauthorized` response), and `SessionManager::enforceKernel($conn)` (direct delegation to the root `Core/AuthKernel` for custom entry points). Module pages currently keep their legacy-identical inline guards for 1:1 behavioural parity; the DB-backed guards run via AuthKernel inside the `db_connect.php` bridge.

### 2.3 `Services/`
- **SmsService.php** — all legacy SMS functions preserved verbatim (`sendSMS`, `sendExpenseSMS`, `sendAttendanceSMS_Core`, `sendAttendanceSMS`), now reading credentials from Config (.env) and **checking the SMS toggle before every dispatch**. Disabled sends are logged as `Skipped (SMS notifications OFF)` — never treated as errors.
- **EmailService.php** — legacy HTML email templates preserved (`sendAttendanceEmail`, `sendExpenseEmail`), **checking the Email toggle before every dispatch**, and adding an **admin copy (Bcc)** on every message via `staff_admin_bcc_header()`:

| Email type | Copy sent to (.env key) |
|---|---|
| Attendance alert | `STAFF_ATT` + `ADMIN_Mail` |
| Expense/Advance alert | `STAFF_EXPENS` + `ADMIN_Mail` |
| Payslip signature OTP | `PAY_SLIP` + `ADMIN_Mail` |
| Profile verification OTP | `ADMIN_Mail` |

Duplicates and invalid addresses are filtered automatically; if a category key is missing, `ADMIN_Mail` is the fallback.

### 2.4 `Modules/`
Each module is self-contained: pages guard their own session, include the common `db_connect.php` bridge, and call `Services/` for notifications. All business logic, SQL, and AJAX contracts are identical to the legacy system — only paths and service wiring were modernised. Highlights:
- **Staff/AdminControl.php** — admin can edit a staff member's **complete details**: name, email, phone, salary, join date, address, Active/Inactive status, **and profile picture** (live preview; uploaded to `Uploads/profiles/`; the previous photo is deleted automatically, `default.png` protected). Also: attendance correction with automatic running-balance recalculation, and full staff deletion.
- **Attendance/SaveAjax.php** — bulk save with double-entry protection, carry-forward debt auto-applied as an advance on first attendance, then SMS + Email per staff.
- **Expense/SaveAjax.php** — transactional insert + running-balance update, then SMS + Email.
- **Payroll** — payslip calculation and settlement save; OTP digital signature over Email + SMS.

### 2.5 Dashboard (`index.php`)
- **Quick-nav button bar** — sticky, horizontally scrollable button bar under the top navbar linking every module page (Admin Control button visible to admins only), plus the original menu grid.
- **Notification Settings panel** (admin only) — two toggle switches (SMS / Email). Changes save instantly via AJAX (`action=save_notification_settings`) to `Core/settings.json`; the endpoint is admin-guarded.
- Loan/advance alert list (admin only) — unchanged legacy logic.

---

## 3. Configuration

### 3.1 `.env` vault (`/home/sadakalo/App/.env`)
Add these keys (parsed with `parse_ini_file`; values in quotes). **No credentials are hardcoded anywhere in `Staff/`** — everything below is read dynamically at runtime by `Core/Config.php`:

```ini
# SMS Configurations
S_USERNAME="your_sms_api_username"
SMS_APIKEY="your_sms_api_key"
SMS_SENDER="your_sender_id"
SMS_API_URL="https://api.mimsms.com/api/SmsSending/SMS"

# Email Configurations
STAFF_ATTD_FROM="STAFF@sadakalofashion.com"       # From address of attendance emails
PAY_SLIP_FROM="PAY@sadakalofashion.com"           # From address of payslip/OTP emails
STAFF_EXPENS_FROM="STAFFEXPENSE@sadakalofashion.com"  # From address of expense emails
ADMIN_Mail_TO="info@sadakalofashion.com"          # Admin copy (Bcc) recipient of every email
```

Key → constant mapping (`Core/Config.php`):

| .env key | Constant | Used for |
|---|---|---|
| `S_USERNAME` | `SMS_API_USERNAME` | SMS API username |
| `SMS_APIKEY` | `SMS_API_KEY` | SMS API key |
| `SMS_SENDER` | `SMS_SENDER_NAME` | SMS sender ID |
| `SMS_API_URL` | `SMS_API_URL` | SMS API endpoint |
| `STAFF_ATTD_FROM` | `MAIL_FROM_ATTEND` | From: header of attendance emails |
| `PAY_SLIP_FROM` | `MAIL_FROM_PAYSLIP` | From: header of payslip signature OTP emails |
| `STAFF_EXPENS_FROM` | `MAIL_FROM_EXPENSE` | From: header of expense/advance emails |
| `ADMIN_Mail_TO` | `MAIL_ADMIN_TO` | Bcc admin copy on **every** outgoing email |

Fail-safe behaviour when a key is missing:
- **SMS** — if any of the four SMS keys is empty, `staff_sms_configured()` returns false and every SMS path skips safely with a `Skipped (SMS credentials missing in .env)` log line (nothing is sent with blank credentials).
- **Email From** — an empty/invalid From address simply omits the `From:` header (server default applies); an empty/invalid `ADMIN_Mail_TO` omits the Bcc copy. Addresses must be valid emails (no spaces) to be used.
- DB keys (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `CARD_ENC_KEY`) are consumed by the root connector as before.

### 3.2 SMS / Email ON-OFF toggle
- Open **Dashboard → Notification Settings** (visible to admin only).
- Flip **SMS Notifications** or **Email Notifications**; the state saves immediately to `Core/settings.json`:

```json
{
    "sms_enabled": true,
    "email_enabled": true,
    "updated_by": "admin",
    "updated_at": "2026-07-22 18:45:00"
}
```

- When OFF, **every** dispatch path is suppressed (attendance, expense, welcome SMS, payslip SMS, OTP send, verification email) and a `Skipped (... notifications OFF)` line is written to `Logs/error_log.txt` for audit.
- If the file is missing, both channels default to **ON**.

### 3.3 File permissions
Writable by PHP: `Uploads/profiles/`, `Uploads/notes/`, `Logs/`, and `Core/` (for `settings.json`).

---

## 4. Security Model

1. **Authentication (AuthKernel)** — every request that includes `Staff/db_connect.php` (i.e. every page, AJAX endpoint, and service route) is enforced by the unified `Core/AuthKernel`: login session, 20-min idle timeout, single active session, heartbeat, and block check — identical to the Suppliers/Customer/Sales modules, with absolute redirects that are loop-safe from nested folders.
2. **Authorisation** — HTML pages are admin-only (legacy behaviour); AJAX endpoints require a logged-in session; the settings endpoint additionally requires the admin role.
3. **Uploads** — profile photo replacement validates the extension whitelist (`jpg/jpeg/png/gif/webp`) before moving files into `Uploads/profiles/`.
4. **SQL** — 100% prepared statements (PDO), transactions on multi-step writes (attendance, expense, payslip).
5. **Output** — user data escaped with `htmlspecialchars` throughout.

---

## 5. Version History

| Version | Date | Changes |
|---|---|---|
| **1.0** | 2026-07-22 | Initial modular release: full port of legacy `Sadakalo_staff/` into `Staff/` MVC-style modules; unified SMS/Email services; dynamic `.env` configuration; admin SMS/Email ON-OFF toggles; quick-nav button bar; full staff editing incl. profile photo; unified logging. |
