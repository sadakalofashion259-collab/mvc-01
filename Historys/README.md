# 📊 Historys/ — লেজার রিপোর্ট (MVC)

> **ফোল্ডার:** `public_html/Historys/`
> **সংস্করণ:** **v1.**
> **কাজ:** সব লেজার রিপোর্ট (সেল, কালেকশন, খরচ, সাপ্লায়ার, স্টক, DPS, লোন) — MVC কাঠামো

## 📄 ফাইল তালিকা

| ফাইল | লাইন | কাজ |
|------|------|-----|
| `index.php` | ২৮ | এন্ট্রি পয়েন্ট (dispatch) |
| `Controller/HistorysController.php` | ১৯৭ | কন্ট্রোলার (AJAX dispatch, আইটেম অ্যাকশন) |
| `Model/HistorysModel.php` | ৪২৩ | মডেল (লেজার ডেটা, ডিলিট, এডিট) |
| `Views/historys_view.php` | ৭০৮ | UI (ফিল্টার + টেবিল + পেজিনেশন) |
| `Views/layout/header.php` | ৩৪২ | হেডার (থিম, নেভ) |
| `Views/layout/footer.php` | ১৭৮ | ফুটার (JS) |

---

## 🔍 মূল লজিক

### `HistorysController.php`
- `dispatch()` — URL/অ্যাকশন অনুযায়ী রাউট
- `handleAjax()` — AJAX রিকোয়েস্ট (ডেটা লোড)
- `ajaxItemAction()` — আইটেম এডিট/ডিলিট (অ্যাডমিন পাস ভেরিফাই)

### `HistorysModel.php`
- লেজার টাইপ (sale/collection/expense/supplier/stock/staff_expense/dps/loan/card) অনুযায়ী JOIN + SUM
- `verifyAdminPass()` — ডিলিটের আগে অ্যাডমিন পাসওয়ার্ড চেক
- `deleteItem()` / `editItem()` — রেকর্ড পরিবর্তন

### `Views/historys_view.php`
- তারিখ/মাস/টাইপ ফিল্টার
- `qDate()` / `qMonth()` — বাংলা তারিখ ফরম্যাট
- টেবিল + এক্সপোর্ট/প্রিন্ট

---

*📦 Historys — v1. · SADA KALO FASHION*
