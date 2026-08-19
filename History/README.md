# History লেজার রিপোর্ট — MVC (Fixed)

## পাথ / অথ (আপনার সার্ভার অনুযায়ী)

আপনার স্ট্রাকচার:
```
public_html/
├── db_connect.php
├── index.php          (login)
├── dashboard.php
└── History/
    ├── index.php
    ├── ajax_handler.php
    ├── models/
    ├── views/
    └── helpers/
```

তাই:
- `require_once __DIR__ . '/../db_connect.php';`  ← এক লেভেল উপরে
- Auth: মূল history.php-এর মতো `$_SESSION['loggedin']` (Core\AuthKernel নেই)

## ইনস্টল
1. `History/` ফোল্ডার `public_html/History/` এ আপলোড করুন
2. নিশ্চিত করুন `public_html/db_connect.php` আছে
3. URL: `yoursite.com/History/index.php` বা `yoursite.com/History/`

## ঠিক করা সমস্যা
1. Sales/Collection/Expense → report_id + daily_reports JOIN
2. getUniqueDates → মূল স্কিমা
3. সম্পূর্ণ CSS/JS/UI
4. AJAX → ajax_handler.php
5. পাথ ../db_connect.php + session auth (AuthKernel সরানো)
