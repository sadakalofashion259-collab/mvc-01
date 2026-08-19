# 📦 Stock/ — স্টক মডিউল (MVC)

> **ফোল্ডার:** `public_html/Stock/`
> **সংস্করণ:** **v1.**
> **কাজ:** স্টক এন্ট্রি + ছবি + সিস্টেম লক + অ্যাডমিন অ্যাকশন

## 📄 ফাইল তালিকা

| ফাইল | লাইন | কাজ |
|------|------|-----|
| `index.php` | ৩৪ | এন্ট্রি পয়েন্ট |
| `Controllers/StockController.php` | ১৬০ | কন্ট্রোলার (handleRequests, getViewData) |
| `Models/Repositories/StockRepository.php` | ১৫৬ | রিপোজিটরি |
| `Models/Interfaces/StockModelInterface.php` | ১৩ | ইন্টারফেস |
| `Services/StockImageService.php` | ৪৭ | ছবি সেভ |
| `Views/stock_view.php` | ৯৭৪ | UI (ফর্ম, তালিকা, লক মোডাল) |
| `Uploads/stock/` | — | স্টক ছবি স্টোরেজ |

---

## 🔍 মূল লজিক

### `StockRepository.php`
- `getSystemLocks()` — কোন ফিচার লক
- `toggleSystemLock()` — লক অন/অফ
- `insertStockEntry()` — স্টক এন্ট্রি (ট্রানজেকশন)
- `deleteStockEntry()` — ডিলিট (অ্যাডমিন পাস চেক)
- `verifyAdminActionPassword()` — অ্যাডমিন অ্যাকশন পাস

### `StockController.php`
- `handleRequests()` — AJAX/GET/পোস্ট রাউট
- `redirectWithError()` — এরর হ্যান্ডলিং

### `Views/stock_view.php`
- `stockImgSrc()` / `stockImgExists()` — ছবি নিরাপদে দেখানো
- `openEntryForm()` — এন্ট্রি মোডাল
- `handleMenuClick()` — নেভ

### `StockImageService.php`
- `saveBase()` — base64 ছবি সেভ (জাল পাথ প্রতিরোধ)

---

*📦 Stock — v1. · SADA KALO FASHION*
