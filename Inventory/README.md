# 📦 SADA KALO FASHION — shop_@invantory (ইনভেন্টরি সিস্টেম)

> **ফোল্ডার:** `public_html/shop_@invantory/`
> **সংস্করণ:** **v1.** (Version One)
> **ধরন:** ইনভেন্টরি ম্যানেজমেন্ট + POS (Point of Sale) + রিটার্ন/অডিট সিস্টেম
> **স্টাইল:** লিগ্যাসি মোনোলিথ (PHP + jQuery + AJAX) — আলাদা MVC নেই
> **ভাষা:** PHP 8 (PDO) · MySQL · jQuery · HTML5 · CSS3
> **ডকুমেন্টেশন তারিখ:** ২০২৬-০৮-০৩

---

## 📌 এক নজরে — ফোল্ডারের সব ফাইল

```
shop_@invantory/
│
├── 📄 index.php                      ← এন্ট্রি গার্ড (লগইন/অ্যাডমিন চেক + ড্যাশবোর্ড)
├── 📄 db_connect.php                 ← ডাটাবেজ ব্রিজ (মূল ফোল্ডারের db_connect.php লিংক)
│
├── 📄 inventory.php                  ← নতুন প্রোডাক্ট + ক্যাটাগরি যোগ (ক্যামেরা/QR সহ)
├── 📄 Invantory_Items.php            ← ইনভেন্টরি আইটেম তালিকা (DataTable + এডিট)
├── 📄 inventory_dashboard.php        ← ইনভেন্টরি ড্যাশবোর্ড (স্ট্যাটিস্টিক্স + স্ট্যাগন্যান্ট)
├── 📄 inventory_pos.php              ← POS (পয়েন্ট অফ সেল) — বিক্রয়
├── 📄 inventory_sales_history.php    ← বিক্রয় হিস্ট্রি (মোডভিত্তিক ফিল্টার + রেট এডিট)
├── 📄 admin_inventory_control.php    ← 🛡️ অ্যাডমিন মাস্টার কন্ট্রোল (P&L, স্টক অ্যাডজাস্ট, ডিলিট)
├── 📄 admin_category_control.php     ← অ্যাডমিন ক্যাটাগরি নিয়ন্ত্রণ (টেবিল ভিউ)
├── 📄 category_mange.php             ← ক্যাটাগরি ম্যানেজ (কার্ড ভিউ + স্ট্যাট)
│
├── 📄 return_product.php             ← প্রোডাক্ট রিটার্ন (পেন্ডিং রিভিউ)
├── 📄 admin_return_history.php       ← রিটার্ন অ্যাপ্রুভ/রিজেক্ট/ডিলিট
├── 📄 out_of_stock.php               ← স্টক শেষ / প্রায় শেষ প্রোডাক্ট
├── 📄 unsold_inventory.php           ← ⏳ অনবিক্রিত (স্ট্যাগন্যান্ট) ইনভেন্টরি + মার্কিং
│
├── 📄 daily_activity.php             ← আজকের অ্যাক্টিভিটি (নতুন/বিক্রয়/রিটার্ন/অ্যাডজাস্ট)
├── 📄 product_edit_history.php       ← প্রোডাক্ট এডিট হিস্টরি (শেষ ২০০)
├── 📄 Audit_log.php                  ← অডিট লগ (লগইন/অ্যাকশন ডিলিট)
├── 📄 notification_dashboard.php     → নোটিফিকেশন ফিড (সেল/অ্যাড/রিস্টক + বেল পোলিং)
│
├── 📄 inventory_bottom_nav.php       ← 📱 বটম নেভিগেশন বার (মোবাইল)
├── 📄 theme.css                      ← 🎨 থিম (light/dark + Bootstrap-ফ্লেভারড UI)
├── 📄 theme-toggle.js                ← 🌗 লাইট/ডার্ক টগল (localStorage + ট্যাব সিঙ্ক)
│
├── 📄 টেক্সট.txt                     ← নোট/নির্দেশনা
└── 🖼️ banner.jpg · logo.png · sada_kalo_fashion.png · sada_kalo_fashion_banner.jpg (ব্র্যান্ড)
```

**মোট:** ২৪টি ফাইল (২০ PHP + ১ JS + ১ CSS + ১ TXT + ৪ ছবি)

---

## 🗄️ ডাটাবেজ টেবিলসমূহ (এই মডিউল ব্যবহৃত)

| টেবিল | কাজ |
|-------|-----|
| `inventory` | মূল প্রোডাক্ট (product_code, name, category_id, pieces, buy_price, cost, cash_sell, image_path, mark_color, mark_note, status, added_by) |
| `categories` | প্রোডাক্ট ক্যাটাগরি (name, status) |
| `inventory_sales` | বিক্রয় হেডার (invoice_no, customer_name, total_pieces, total_sell_amount, total_profit, sold_by) |
| `inventory_sale_items` | বিক্রয় আইটেম (sale_id, product_code, buy_price, cost, sell_price, profit, pieces) |
| `inventory_returns` | রিটার্ন (invoice_no, product_code, return_pieces, refund_amount, return_profit, status, returned_by) |
| `inventory_adjustments` | স্টক অ্যাডজাস্টমেন্ট (adjustment_type, pieces, note, adjusted_by) |
| `product_edit_history` | প্রোডাক্ট এডিট লগ (changes_details, changed_by) |
| `audit_logs` | অডিট ট্রেইল (username, action, created_at) |
| `users` | ইউজার (role=admin চেক) |

---

# 📄 ফাইলওয়াইজ বিস্তারিত লজিক

## 1️⃣ `index.php` — এন্ট্রি গার্ড + অ্যাডমিন ড্যাশবোর্ড

**লজিক:**
1. `session_start()` + নো-ক্যাশ হেডার
2. `BusinessLogicException` ক্লাস ও `logSystemError()` ফাংশন — এরর `../Logs/error_log.txt`-এ লেখে
3. **সেশন টাইমআউট (২০ মিনিট):** `$_SESSION['last_activity']` > ১২০০ সেকেন্ড হলে সেশন ধ্বংস → AJAX হলে JSON `session_expired`, না হলে alert + redirect
4. **রোল চেক:** `loggedin === true` এবং `role === 'admin'` না হলে অ্যাক্সেস ডিনাইড পেজ (সুন্দর "হ্যাকার" মেসেজ + কন্টাক্ট + লগইন বাটন)
5. লগইন থাকলে সাদা-কালো অ্যাডমিন প্যানেল (welcome কার্ড) দেখায়

**কাজ:** পুরো `shop_@invantory` ফোল্ডারের প্রথম গেট — শুধু লগইনড অ্যাডমিন ঢুকতে পারে।

---

## 2️⃣ `db_connect.php` — ডাটাবেজ ব্রিজ

**লজিক:**
1. `DOCUMENT_ROOT` থেকে মূল ফোল্ডারের `db_connect.php` খোঁজে
2. পাওয়া গেলে `require_once` করে
3. না পেলে বাংলা এরর দেখিয়ে `die()`

**কাজ:** সাব-ফোল্ডার থেকে মূল ডাটাবেজ কানেকশন (PDO) পুনরায় ব্যবহার — ডুপ্লিকেট কানেকশন কোড এড়ায়।

---

## 3️⃣ `inventory.php` — প্রোডাক্ট/ক্যাটাগরি যোগ

**লজিক (AJAX অ্যাকশন):**
- `csrf_token` ভেরিফিকেশন (সব অ্যাকশনে)
- `check_duplicate` → `product_code` আগে আছে কিনা
- `add_category` → `categories` টেবিলে নতুন ক্যাটাগরি (status='active')
- `add_product` → নতুন প্রোডাক্ট:
  - `SKF-%` প্যাটার্নে **অটো সিকোয়েন্স** (সর্বশেষ নম্বর+১)
  - base64 ক্যামেরা ছবি সেভ → `image_path`
  - `inventory` টেবিলে INSERT (product_code, category_id, name, pieces, buy_price, cost, cash_sell, added_by)

**কাজ:** নতুন প্রোডাক্ট এন্ট্রি + ক্যাটাগরি + QR/ক্যামেরা/বেস64 ছবি। JS-এ ক্যামেরা, QR স্ক্যানার, প্রাইস ক্যালকুলেটর আছে।

---

## 4️⃣ `Invantory_Items.php` — আইটেম তালিকা

**লজিক:**
- `inventory i LEFT JOIN categories c LEFT JOIN users u` → প্রোডাক্ট + ক্যাটাগরি নাম + এন্ট্রিকারী
- **DataTable AJAX:** সার্চ, পেজিনেশন, মোট pieces SUM
- প্রোডাক্ট এডিট:
  - পুরনো মান `SELECT ... FOR UPDATE` (লক)
  - পরিবর্তন হলে `product_edit_history`-এ লগ
  - `UPDATE inventory SET name, buy_price, cost, cash_sell`
- `WHERE pieces > ` ফিল্টার (স্টক থাকা আইটেম)

**কাজ:** ইনভেন্টরি ব্রাউজ/সার্চ + দাম/নাম এডিট + এডিট হিস্টরি সংরক্ষণ।

---

## 5️⃣ `inventory_dashboard.php` — ড্যাশবোর্ড

**লজিক:**
- **পেন্ডিং রিটার্ন কাউন্ট** (badge)
- **স্টক-শূন্য প্রোডাক্ট** (`pieces <=  AND status='active'`)
- **স্ট্যাগন্যান্ট প্রোডাক্ট:** সর্বশেষ বিক্রয়ের দিন থেকে কতদিন অলস (`COALESCE(MAX(s.created_at), created_at)`) → রঙিন টোন কার্ড
- **স্ট্যাট টাইল:** মোট স্টক (পিস + টাকা), মোট বিক্রীত (পিস + টাকা), আজকের নতুন/অ্যাডজাস্ট
- **বেল নোটিফিকেশন:** প্রতি ৩০ সেকেন্ডে `pollBell()` → localStorage-এ unseen কাউন্ট

**কাজ:** ইনভেন্টরির সামগ্রিক স্বাস্থ্য — কী বিক্রি হচ্ছে, কী আটকে আছে।

---

## 6️⃣ `inventory_pos.php` — POS (বিক্রয়)

**লজিক:**
- **ইনভয়েস নম্বর:** `SKF-INV-%` অটো সিকোয়েন্স
- সার্চ (product_code/name) → অটো-স্ক্যানার (QR)
- **কার্ট:** addToCart / updatePrice / updateQty / removeFromCart (JS)
- **সাবমিট সেল (ট্রানজেকশন):**
  1. `inventory_sales`-এ হেডার INSERT (invoice_no, customer_name, sold_by)
  2. প্রতিটি আইটেম `inventory_sale_items`-এ INSERT (buy/cost/sell/profit)
  3. `UPDATE inventory SET pieces = pieces - ?` (স্টক কমানো)
  4. হেডারের total_pieces / total_sell_amount / total_profit আপডেট

**কাজ:** দোকানের মূল বিক্রয় কাউন্টার — ক্যাশ সেল + QR স্ক্যান + কার্ট + অটো স্টক ডিডাকশন।

---

## 7️⃣ `inventory_sales_history.php` — বিক্রয় হিস্ট্রি

**লজিক (AJAX):**
- `update_sale_item_rate` → কোন বিক্রয় আইটেমের buy/cost/sell প্রাইস পরিবর্তন → নতুন profit হিসাব + সেলে টোটাল আপডেট
- `load_sales_history` → **মোড:** recent / date-range / folder (মাস)
- JOIN `inventory_sales` + `inventory_sale_items` + `inventory` + `users`
- **রিটার্ন পরিমাণ** সাবকোয়েরি: `COALESCE(SUM(return_pieces),)` — বিক্রয় থেকে রিটার্ন বাদ
- JS: EditRateModal, টোটাল প্রফিট টগল, ডেট ফিল্টার, AJAX পেজিনেশন

**কাজ:** দিন/মাসভিত্তিক বিক্রয় রিপোর্ট + ভুল দাম সংশোধনের সুযোগ।

---

## 8️⃣ `admin_inventory_control.php` — 🛡️ অ্যাডমিন মাস্টার কন্ট্রোল (সবচেয়ে বড়, ৭৮৩ লাইন)

**লজিক (Repository প্যাটার্ন):**
- `AdminRepositoryInterface` + `AdminRepository` (PDO)
- **P&L লেজার:** `getPnlLedger()` — সার্চ + মাস ফিল্টার + পেজিনেশন, লাভ/লস হিসাব
- **স্ট্যাটস:**
  - `getPendingReturnsCount()` — পেন্ডিং রিটার্ন
  - `getEverNetProfit()` — মোট লাভ (বিক্রয় প্রফিট − রিটার্ন প্রফিট)
  - `getPeriodicStats()` — নির্দিষ্ট সময়ের বিক্রয়/রিটার্ন
  - `getCurrentInventoryStats()` — বর্তমান স্টক (buy/cost/total)
- **অ্যাকশন:**
  - `deleteInvoice()` — ইনভয়েস ডিলিট → স্টক ফেরত + আইটেম/সেল ডিলিট (রিটার্ন থাকলে ব্লক)
  - `updateSaleItemPrice()` — সেল আইটেম দাম এডিট
  - `updateProduct()` — প্রোডাক্ট আপডেট
  - `adjustStock()` — স্টক ইনক্রিজ/ডিক্রিজ + `inventory_adjustments` লগ
  - `approveReturn()` / `rejectReturn()` — রিটার্ন অনুমোদন (স্টক ফেরত)
  - `toggleProductStatus()` — প্রোডাক্ট active/inactive
- JS: ট্যাব (pnl_ledger, pending_returns...), মোডাল, AJAX

**কাজ:** অ্যাডমিনের "সব-কিছু নিয়ন্ত্রণ" প্যানেল — P&L, স্টক ফিক্স, ইনভয়েস মুছা, রিটার্ন অনুমোদন।

---

## 9️⃣ `admin_category_control.php` — অ্যাডমিন ক্যাটাগরি (টেবিল)

**লজিক:**
- ক্যাটাগরি + স্টক যোগফল + বিক্রয় যোগফল JOIN (subquery)
- ক্যাটাগরি status টগল (active/inactive), নাম এডিট
- কোলাম না থাকলে ফলব্যাক (catch)

**কাজ:** ক্যাটাগরি ব্যবস্থাপনার অ্যাডমিন সংস্করণ — টেবিল ভিউতে।

---

## 🔟 `category_mange.php` — ক্যাটাগরি ম্যানেজ (কার্ড)

**লজিক:**
- **স্ট্যাট টাইল:** মোট রেমেনিং, মোট বিক্রীত, আজকের যোগ/বিক্রয়
- ক্যাটাগরি কার্ড: প্রতিটিতে রেমেনিং + বিক্রীত (LEFT JOIN subquery)
- অক্যাটাগোরাইজড (`category_id=/NULL`) আলাদা সারি
- status টগল + এডিট মোডাল + QR স্ক্যানার + পেজিনেশন (sessionStorage)

**কাজ:** দোকানদার-বান্ধব ক্যাটাগরি ভিউ — কার্ডে কার্ডে স্টক অবস্থা।

---

## 1️⃣1️⃣ `return_product.php` — প্রোডাক্ট রিটার্ন

**লজিক:**
- ইনভয়েস নম্বর + প্রোডাক্ট কোড দিয়ে `verifyInvoice` → বিক্রয় আইটেম খুঁজে (sell_price, profit, invoice_no)
- **রিটার্ন ভ্যালিডেশন:** আগের রিটার্নের যোগফল (status != rejected) + নতুন ≤ বিক্রীত পরিমাণ
- `submitReturn`:
  1. `inventory_returns`-এ INSERT (return_pieces, refund_amount, return_profit, status='pending')
  2. `UPDATE inventory SET pieces = pieces + ?` — স্টক সাথে সাথে ফেরত
- QR স্ক্যানার + refund অটো-ক্যালকুলেশন

**কাজ:** ক্রেতা প্রোডাক্ট ফেরত দিলে এন্ট্রি — পেন্ডিং থাকে যতক্ষণ অ্যাডমিন অনুমোদন না করে।

---

## 1️⃣2️⃣ `admin_return_history.php` — রিটার্ন অ্যাপ্রুভ/রিজেক্ট

**লজিক (AJAX অ্যাকশন):**
- `approve` → status='approved' + স্টক যোগ
- `reject` → status='rejected'
- `delete` → সম্পূর্ণ রিভার্স:
  - স্টক ফেরত (যদি approved না হয়)
  - `inventory_sale_items` থেকে পিস কমায়/ডিলিট
  - `inventory_sales`-এর total_pieces/sell_amount/profit কমায়
  - সব খালি হলে সেল ডিলিট
  - রিটার্ন রেকর্ড ডিলিট
- তালিকা: `inventory_returns LEFT JOIN inventory LEFT JOIN users`

**কাজ:** রিটার্ন রিকোয়েস্টের চূড়ান্ত সিদ্ধান্ত + সম্পূর্ণ অ্যাকাউন্টিং রিভার্সাল।

---

## 1️⃣3️⃣ `out_of_stock.php` — স্টক শেষ/প্রায় শেষ

**লজিক:**
- `WHERE pieces = ` বা কম-স্টক ফিল্টার (ক্যাটাগরি + সার্চ)
- `last_sold_date` সাবকোয়েরি — শেষ কবে বিক্রি হয়েছে (নতুন প্রথমে)
- পেজিনেশন + অ্যাকশন বাটন (অ্যাডজাস্ট স্টক)

**কাজ:** কোন প্রোডাক্ট রিস্টক করতে হবে তা দ্রুত দেখা।

---

## 1️⃣4️⃣ `unsold_inventory.php` — ⏳ অনবিক্রিত/স্ট্যাগন্যান্ট (৮০৪ লাইন)

**লজিক:**
- **মার্কিং সিস্টেম:** `mark_color` (রঙ) + `mark_note` (নোট) — একাধিক প্রোডাক্ট সিলেক্ট করে রঙ দিন
- **Clear All:** অ্যাডমিন পাসওয়ার্ড ভেরিফাই করে সব মার্ক মুছে
- **ফিল্টার:** সার্চ + ক্যাটাগরি + রঙ ফিল্টার + AJAX পেজিনেশন
- **স্ট্যাট:** মোট স্টক পিস/টাকা, মার্কড কাউন্ট
- JS: multi-select বার, pickColor, saveMarks, ajaxLoad, পেজ জাম্প

**কাজ:** দীর্ঘদিন বিক্রি হয়নি এমন মাল চিহ্নিত করা — রঙিন ট্যাগ দিয়ে সিদ্ধান্ত নেওয়া সহজ।

---

## 1️⃣5️⃣ `daily_activity.php` — আজকের অ্যাক্টিভিটি

**লজিক:**
- **স্ট্যাট:** আজকের নতুন প্রোডাক্ট (count + pcs), বিক্রয় (count + pcs), রিটার্ন count, অ্যাডজাস্ট (increase/decrease)
- **সেকশন:**
  - নতুন যোগ: `inventory WHERE DATE(created_at)=today`
  - বিক্রয়: `inventory_sale_items JOIN inventory_sales WHERE DATE(created_at)=today`
  - রিটার্ন: `inventory_returns WHERE DATE(created_at)=today`
  - স্টক ইনক্রিজ/ডিক্রিজ: `inventory_adjustments`
- প্রতিটিতে ছবি + ইউজার নাম + সময়
- JS: ট্যাব স্যুইচ + লাইটবক্স

**কাজ:** "আজ দোকানে কী কী হলো" — এক পেজে সব লেনদেনের সারসংক্ষেপ।

---

## 1️⃣6️⃣ `product_edit_history.php` — এডিট হিস্টরি

**লজিক:**
- `product_edit_history h LEFT JOIN users u` → শেষ ২০০টি এডিট রেকর্ড
- changes_details JSON দেখায় (কী থেকে কী হয়েছে)

**কাজ:** কে, কখন, কোন প্রোডাক্টে কী পরিবর্তন করেছে — অডিট ট্রেইল।

---

## 1️⃣7️⃣ `Audit_log.php` — অডিট লগ

**লজিক:**
- `SELECT * FROM audit_logs WHERE DATE(created_at) BETWEEN ? AND ?` — তারিখ রেঞ্জ
- **ডিলিট:** `DELETE FROM audit_logs WHERE username=? AND DATE(created_at)=?` (আজকের লগ মুছে)
- JS: confirmDelete(user, date)

**কাজ:** ইউজার অ্যাকশন লগ দেখা + পুরনো/ভুল লগ মুছা।

---

## 1️⃣8️⃣ `notification_dashboard.php` — নোটিফিকেশন ফিড (১,০৫৩ লাইন)

**লজিক:**
- **ইউনিয়ন ফিড (৩ ধরনের নোটিফিকেশন):**
  - `sold` — বিক্রয় (inventory_sale_items JOIN inventory_sales)
  - `added` — নতুন প্রোডাক্ট (inventory)
  - `restock` — স্টক অ্যাডজাস্ট (inventory_adjustments)
- এক UNION SQL-এ সব মিলিয়ে feed (WHERE + LIMIT)
- **কাউন্টার:** মোট রেকর্ড + আজকের রেকর্ড + প্রতিটি টাইপের সংখ্যা
- **বেল API:** ৩০ সেকেন্ড পরপর পোলিং → localStorage-এ seen epoch → unseen ব্যাজ
- বাংলা সংখ্যা (`bnNum`), বাংলা সময় (`bnTimeAgo`), নিরাপদ ছবি পাথ (`safeImagePath`)

**কাজ:** ইনভেন্টরির লাইভ অ্যাক্টিভিটি ফিড — নতুন যোগ, বিক্রয়, রিস্টক সব এক জায়গায়।

---

## 1️⃣9️⃣ `inventory_bottom_nav.php` — 📱 বটম নেভিগেশন

**লজিক:**
- `basename($_SERVER['PHP_SELF'])` দিয়ে কারেন্ট পেজ চেক → `on` ক্লাস (হাইলাইট)
- ৫টি আইটেম: ড্যাশবোর্ড / আইটেম / **POS (মাঝে বড়)** / হিস্ট্রি / মেনু
- মেনু → `toggleSidebar()` কল
- black থিম + `env(safe-area-inset-bottom)` (iPhone হোম-বার সাপোর্ট)

**কাজ:** মোবাইলে দ্রুত নেভিগেশন — অন্য পেজে `include` করে বসানো যায়।

---

## 2️⃣️⃣ `theme.css` — 🎨 থিম (৮২৮ লাইন)

**লজিক:**
- `:root` CSS ভেরিয়েবল (light) + `[data-theme="dark"]` (dark mode)
- Bootstrap 5.3-ফ্লেভারড কম্পোনেন্ট:
  - App Bar, Icon Button, Side Drawer (Offcanvas-style)
  - Card, Stat Tile, Button, Form, Search Bar, Table
  - Badge, Modal, Pagination, Bottom Nav, Scanner, Memo/Receipt, Print
- `--sk-grad-brand`, `--sk-shadow-brand` ইত্যাদি ব্র্যান্ড টোকেন

**কাজ:** পুরো মডিউলের ইউনিফর্ম UI — light/dark দুই মোডে।

---

## 2️⃣1️⃣ `theme-toggle.js` — 🌗 লাইট/ডার্ক টগল (৯১ লাইন)

**লজিক:**
1. `localStorage`-এ `sk-theme` কী চেক (light/dark)
2. OS প্রেফারেন্স fallback (`prefers-color-scheme`)
3. `document.documentElement.setAttribute('data-theme', theme)`
4. meta `theme-color` আপডেট
5. **ট্যাব সিঙ্ক:** `storage` ইভেন্ট শুনে অন্য ট্যাবেও থিম বদলায়

**কাজ:** প্রতিটি `.sk-appbar__right`-এ টগল পিল — পছন্দ সেভ + সব ট্যাবে একই থিম।

---

## 2️⃣2️⃣ `টেক্সট.txt` — নির্দেশনা নোট

**কাজ:** বটম নেভবার কীভাবে অন্য পেজে বসাতে হয় তার বাংলা নির্দেশনা (include + padding-bottom + toggleSidebar নোট)।

---

## 🖼️ ইমেজ ফাইল (ব্র্যান্ড)

| ফাইল | কাজ |
|------|-----|
| `banner.jpg` | হেডার ব্যানার |
| `logo.png` | ব্র্যান্ড লোগো |
| `sada_kalo_fashion.png` | ব্র্যান্ড আইকন |
| `sada_kalo_fashion_banner.jpg` | ব্র্যান্ড ব্যানার |

---

## 🔄 ওয়ার্কফ্লো — সিস্টেম কীভাবে চলে

```
[লগইন index.php → admin চেক]
        │
        ▼
[inventory.php]─── নতুন প্রোডাক্ট/ক্যাটাগরি (ছবি+QR)
        │
        ▼
[inventory_pos.php]─── বিক্রয় (SKF-INV-xxxx ইনভয়েস, স্টক −)
        │
        ├──► [inventory_sales_history.php] ── রিপোর্ট/দাম এডিট
        ├──► [return_product.php] ── রিটার্ন (স্টক +)
        │         └──► [admin_return_history.php] ── approve/reject/delete
        │
        ▼
[admin_inventory_control.php] ── P&L, স্টক অ্যাডজাস্ট, ইনভয়েস ডিলিট, অনুমোদন
        │
        ├──► [inventory_dashboard.php] ── স্ট্যাট + স্ট্যাগন্যান্ট + বেল
        ├──► [out_of_stock.php] / [unsold_inventory.php] ── রিস্টক সিদ্ধান্ত
        ├──► [daily_activity.php] / [notification_dashboard.php] ── লাইভ ফিড
        └──► [Audit_log.php] / [product_edit_history.php] ── অডিট
```

---

## 🔐 নিরাপত্তা (এই মডিউলে)

- ✅ **CSRF টোকেন** — সব AJAX অ্যাকশনে (`csrf_token` চেক)
- ✅ **সেশন টাইমআউট** — ২০ মিনিট নিষ্ক্রিয়তা → লগআউট
- ✅ **রোল গার্ড** — শুধু `role='admin'` ঢুকতে পারে (index.php)
- ✅ **PDO Prepared Statement** — SQL ইনজেকশন প্রতিরোধ
- ✅ **`FOR UPDATE` লক** — প্রোডাক্ট এডিটে রেস-কন্ডিশন প্রতিরোধ
- ✅ **স্ট্রিক্ট টাইপ** (`declare(strict_types=1)`)
- ✅ **ব্ল্যাকলিস্ট হুঁশিয়ারি** — অ্যাক্সেস ডিনাইড পেজে

---

## ⚠️ দুর্বলতা/নোট (ভবিষ্যত উন্নতির জন্য)

- সব ফাইল মোনোলিথ (এক ফাইলে PHP + HTML + JS) — MVC-তে ভাগ করা যায়
- `users.password` চেক (unsold_inventory) — md5 লিগ্যাসি, আপগ্রেডযোগ্য
- `Audit_log` DELETE — রোল-বেসড পারমিশন আরও শক্ত করা যায়
- ছবি path হেল্পার `safeImagePath()` শুধু notification-এ, অন্যত্র inline

---

*📦 shop_@invantory — v1. ডকুমেন্টেশন · SADA KALO FASHION*
