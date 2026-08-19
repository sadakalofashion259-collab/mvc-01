# 👤 Profile/ — প্রোফাইল মডিউল (MVC)

> **ফোল্ডার:** `public_html/Profile/`
> **সংস্করণ:** **v1.1**
> **কাজ:** ইউজার প্রোফাইল (তথ্য আপডেট, পাসওয়ার্ড, ছবি + Audit Log)

## 📄 ফাইল তালিকা

| ফাইল | লাইন | কাজ |
|------|------|-----|
| `index.php` | ২৭৭ | এন্ট্রি পয়েন্ট (xss হেল্পার + dispatch) |
| `Controllers/ProfileController.php` | ৩৮৮ | কন্ট্রোলার (ProfileActionResult, আপডেট) |
| `Models/ProfileModel.php` | ১৩৩ | মডেল (getUserById, isEmailTaken, verifyCurrentPassword...) |
| `Models/Interfaces/ProfileModelInterface.php` | ১২ | ইন্টারফেস |
| `Services/ImageResizer.php` | ৮৩ | ছবি রিসাইজ (calculateNewDimensions) |
| `Services/AuditInit.php` | — | 🆕 Audit Logger বুটস্ট্র্যাপ |
| `Views/profile_hero.php` | ৭৯ | প্রোফাইল হেডার |
| `Views/profile_tabs.php` | ৩৬৬ | প্রোফাইল ট্যাব (তথ্য/পাস/ছবি) |
| `Views/profile_scripts.php` | ১৪৮ | প্রোফাইল JS |

---

## 🔍 মূল লজিক

### `ProfileController.php`
- `tabIndex()` — অ্যাক্টিভ ট্যাব
- `ProfileActionResult` — ফলাফল (সফল/এরর)
- বেসিক তথ্য আপডেট, পাসওয়ার্ড পরিবর্তন, প্রোফাইল ছবি (resize)

### `ProfileModel.php`
- `getUserById()` — ইউজার ডেটা
- `isEmailTaken()` — ডুপ্লিকেট ইমেইল চেক
- `verifyCurrentPassword()` — পুরনো পাস যাচাই
- `changePassword()` / `updateProfilePicture()`

### `ImageResizer.php`
- `resizeAndSave()` — ছবি রিসাইজ + সেভ
- `calculateNewDimensions()` — অনুপাত বজায় রেখে আকার

### `Views/profile_tabs.php`
- ট্যাব: প্রোফাইল তথ্য / পাসওয়ার্ড / ছবি
- পাসওয়ার্ড স্ট্রেংথ + ম্যাচ চেক

---

## 🆕 Changelog — v1.1 (Audit Integration)

### নতুন
- `Services/AuditInit.php` — `AuditInit::boot($conn)`

### আপডেট
| ফাইল | পরিবর্তন |
|------|----------|
| `index.php` | AuditInit boot |
| `Controllers/ProfileController.php` | basic / password / picture-এ AuditLogger |

### Audit কভারেজ
| অ্যাকশন | Module | Action | নোট |
|---------|--------|--------|------|
| প্রোফাইল তথ্য | `profile` | UPDATE | email, phone, mobile, address |
| পাসওয়ার্ড | `profile` | UPDATE | শুধু `[CHANGED]` — হ্যাশ/প্লেইন লগ হয় না |
| প্রোফাইল ছবি | `profile` | UPDATE | পুরনো/নতুন path |

> নির্ভরতা: `public_html/Audit/` — না থাকলে নীরবে স্কিপ।

---

*📦 Profile — v1.1 · SADA KALO FASHION*
