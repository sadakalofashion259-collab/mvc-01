# 🖥️ Views/ — গ্লোবাল ভিউ লেয়ার

> **ফোল্ডার:** `public_html/Views/`
> **সংস্করণ:** **v1.**
> **কাজ:** UI টেমপ্লেট (লগইন, ড্যাশবোর্ড, প্রোফাইল, পার্শিয়াল)

## 📄 ফাইল তালিকা

| ফাইল | লাইন | কাজ |
|------|------|-----|
| `index.php` | ১৮৪ | ফোল্ডার গার্ড |
| `daily_report_view.php` | ৩১৮ | দৈনিক রিপোর্ট ভিউ |
| `login/` | — | লগইন পেজ পার্টস |
| `dashboard/` | — | ড্যাশবোর্ড পার্টস |
| `profile/` | — | প্রোফাইল পার্টস |
| `partials/` | — | শেয়ার্ড কম্পোনেন্ট |

---

## 🔍 ফোল্ডারওয়াইজ লজিক

### `login/`
| ফাইল | কাজ |
|------|-----|
| `login_form.php` | লগইন ফর্ম (ইউজার + পাস + ক্যাপচা + বায়ো) |
| `image_slider.php` | লগইন পেজে ইমেজ স্লাইডার |
| `notice_bar.php` | নোটিশ বার |
| `action_buttons.php` | দ্রুত অ্যাকশন বাটন |
| `login_scripts.php` | বায়োমেট্রিক লগইন JS (runBioLogin, Turnstile) |

### `dashboard/`
| ফাইল | কাজ |
|------|-----|
| `welcome_banner.php` | স্বাগতম ব্যানার |
| `collection_alerts.php` | আজকের কালেকশন অ্যালার্ট (৭০ লাইন) |
| `notification_modal.php` | নোটিফিকেশন মোডাল (১০৫ লাইন) |

### `partials/` (শেয়ার্ড কম্পোনেন্ট)
| ফাইল | কাজ |
|------|-----|
| `sidebar.php` | সাইডবার (৩১৬ লাইন) |
| `top_navbar.php` | উপরের নেভবার |
| `bottom_nav.php` | মোবাইল বটম নেভ (২১০ লাইন) |
| `ticker_bar.php` | টিকার বার |
| `flash_message.php` | ফ্ল্যাশ মেসেজ |
| `app_scripts.php` | গ্লোবাল JS (থিম টগল, লাইভ ঘড়ি, সাইডবার) |
| `profile_scripts.php` | প্রোফাইল JS (পাসওয়ার্ড স্ট্রেংথ, ছবি ড্র্যাগ) |
| `collection_scroll_script.php` | অটো-স্ক্রল |
| `section_theme_toggle.php` | সেকশন থিম টগল |

### `profile/`
| ফাইল | কাজ |
|------|-----|
| `profile_hero.php` | প্রোফাইল হেডার (ছবি + নাম) |
| `profile_tabs.php` | প্রোফাইল ট্যাব (৩৬৬ লাইন) |

---

*📦 Views — v1. · SADA KALO FASHION*
