# BankCard — Independent MVC Module

> **সংস্করণ:** **v1.** · SADA KALO FASHION
# BankCard — Independent MVC Module

> **সংস্করণ:** **v1.**
> **কাজ:** ক্রেডিট কার্ড ম্যানেজার (কার্ড CRUD + লেজার + এনক্রিপশন + বিলিং) — পূর্ণ MVC

Sada Kalo Fashion Credit Card Manager converted to full MVC architecture.
## Structure

```
BankCard/
├── index.php              # Front controller / entry
├── bootstrap.php          # Session, DB, Auth, constants
├── Controllers/
│   └── CardController.php # All request handling
├── Models/
│   ├── CreditCardModel.php
│   └── LedgerModel.php
├── Services/
│   ├── EncryptionService.php
│   ├── BillingService.php
│   └── Logger.php
├── Views/
│   ├── layout.php
│   ├── list.php
│   ├── view.php
│   └── partials/
│       ├── bottom_nav.php
│       └── modals.php
├── assets/
│   ├── css/app.css
│   ├── js/app.js
│   └── img/               # put logo.png here
├── uploads/
│   ├── credit_cards/
│   └── card_receipts/
└── Logs/
```

## Installation

1. Copy the entire `BankCard/` folder into `public_html/BankCard/`.
2. Ensure `public_html/db_connect.php` and (optional) `public_html/Core/AuthKernel.php` exist.
3. Make sure `uploads/` and `Logs/` are writable by the web server.
4. Access via: `https://yourdomain.com/BankCard/` or `/BankCard/index.php`
5. Admin-only (role === 'admin').

## Notes

- All card images & receipts are stored **inside** the module under `uploads/`.
- Encryption key still comes from root `.env` via `db_connect.php` (CARD_ENC_KEY).
- CSRF, password confirmation for sensitive actions, and original business logic are preserved.
- Theme (dark/light) and mobile-first UI unchanged.

## Migration from old credit_card.php

- Old uploads in root `uploads/credit_cards` can be moved into `BankCard/uploads/credit_cards` and DB paths updated if needed, or leave relative paths working after move.
