# 📘 Expense Module — SADA KALO FASHION

> **সংস্করণ:** **v1.1** (Audit Log)
> **ফোল্ডার:** `public_html/Expense/` — খরচ ম্যানেজমেন্ট MVC মডিউল

## Overview

The **Expense Module** is a fully independent, standalone MVC module for managing all shop-related expenses (shop expenses, not staff expenses). It was migrated from scattered files across the root directory into a single, self-contained folder structure.

---

## 📂 Directory Tree Structure

```
Expense/
├── README.md                              ← This file
├── index.php                              ← Main entry point (front controller)
│
├── Config/
│   ├── config.php                         ← Module configuration settings
│   └── .htaccess                          ← Deny direct access
│
├── Controllers/
│   ├── ExpenseController.php              ← Handles all HTTP requests & business logic
│   └── .htaccess                          ← Deny direct access
│
├── Models/
│   ├── Interfaces/
│   │   └── ExpenseRepoInterface.php       ← Contract/interface for repository
│   ├── Repositories/
│   │   └── ExpenseRepository.php          ← Database operations (CRUD)
│   └── .htaccess                          ← Deny direct access
│
├── Views/
│   └── expense_view.php                   ← UI template (HTML + CSS + JS)
│
├── Services/
│   ├── ImageService.php                   ← Webcam image processing & upload
│   └── AuditInit.php                      ← Audit Logger bootstrap (v1.1)
│
├── Assets/
│   └── images/                            ← Module-specific images (shares global uploads)
│
└── Logs/
    ├── .htaccess                          ← Deny direct access
    └── expense_error_log.txt              ← Module error log file (auto-created)
```

---

## 📋 Complete List of Subdirectories

| # | Directory Path | Purpose |
|---|----------------|---------|
| 1 | `Expense/` | Module root |
| 2 | `Expense/Config/` | Application-level configuration |
| 3 | `Expense/Controllers/` | MVC Controller layer |
| 4 | `Expense/Models/` | MVC Model layer |
| 5 | `Expense/Models/Interfaces/` | Model contracts/interfaces |
| 6 | `Expense/Models/Repositories/` | Database repository implementations |
| 7 | `Expense/Views/` | MVC View layer (HTML templates) |
| 8 | `Expense/Services/` | Service layer (image processing) |
| 9 | `Expense/Assets/` | Static assets |
| 10 | `Expense/Assets/images/` | Module images (shared with global uploads) |
| 11 | `Expense/Logs/` | Error log files |

---

## 🧩 Module Architecture & Flow

### Entry Point → `Expense/index.php`

This is the **front controller** (gateway) for the entire module. When a user visits `Expense/index.php`, the following flow occurs:

1. **Session Initialization** — Starts secure session, sets memory limit, configures error logging
2. **CSRF Token** — Generates/validates a CSRF token for form security
3. **Database Connection** — Loads `db_connect.php` from the project root
4. **MVC Autoload** — Loads all required components in order:
   - Model Interface → Model Repository → Service → Controller
5. **Dependency Injection** — Instantiates `ExpenseRepository` (with PDO connection) and `ImageService`
6. **Controller Dispatch** — Passes dependencies to `ExpenseController::handleRequest()`

### Controller → `Expense/Controllers/ExpenseController.php`

This is the **brain** of the module. It handles:

| Method | Purpose |
|--------|---------|
| `handleRequest()` | Main dispatcher — reads `$_POST['ajax_action']` and routes to the appropriate handler |
| `sendJsonResponse()` | Sends JSON responses for AJAX calls |
| `renderAjaxHistoryRow()` | Re-renders the expense history table after save/edit/delete |

**AJAX Actions handled:**
- `save_expense` — Saves a new expense entry (with optional photo)
- `edit_expense` — Updates an existing expense (admin only, password verified)
- `delete_expense` — Deletes an expense and removes photo file (admin only)
- `filter_expenses` — Filters expenses by date range and category

**Form POST actions:**
- Category management (add, edit, delete, toggle status) — admin only

**View rendering:**
- Gathers all view data (role, categories, grouped expenses, folder stats)
- Extracts variables and includes the View template

### Model Layer

#### Interface → `Expense/Models/Interfaces/ExpenseRepoInterface.php`

Defines the **contract** that any expense repository must implement:

| Method | Description |
|--------|-------------|
| `getExpenses(date, folderMonth)` | Get expenses grouped by date |
| `getCategories()` | Get all expense categories |
| `getActiveCategories()` | Get only active categories |
| `getFolderStats(currentYear)` | Get monthly folder statistics |
| `saveExpense(...)` | Insert a new expense entry |
| `deleteExpense(id)` | Delete an expense by ID |
| `updateExpense(id, ...)` | Update expense amount, category, note |
| `addCategory(name)` | Add a new expense category |
| `updateCategory(id, name)` | Update category name |
| `deleteCategory(id)` | Delete a category |
| `toggleCategoryStatus(id)` | Enable/disable a category |
| `verifyAdmin(user, pass)` | Verify admin password for sensitive actions |
| `getPhotoPath(id)` | Get photo path for an expense |
| `filterExpenses(from, to, cat)` | Filter expenses by date range and category |

#### Repository → `Expense/Models/Repositories/ExpenseRepository.php`

Implements all interface methods using **PDO**. Key implementation details:

- **`saveExpense()`** — Uses database transactions. Creates a `daily_reports` entry if one doesn't exist for the date, then inserts the expense linked to that report.
- **`verifyAdmin()`** — Supports bcrypt (`password_verify`), MD5, and plain text password comparison for backward compatibility.
- **`filterExpenses()`** — Returns formatted rows with HTML-safe content.

### View → `Expense/Views/expense_view.php`

A single-page application with:

- **Hero Banner** — Shows greeting, date, and brand identity
- **Quick Menu** — 4 grid buttons: New Entry, History, Filter (admin), Monthly Folders
- **Sidebar Panel** — Full navigation with links to all module sections
- **Entry Form** — Date picker, category dropdown, amount input, webcam photo capture
- **History Table** — Day-grouped expense entries with edit/delete actions for admin
- **Filter Section** — Date range and category filters
- **Folder View** — Month-by-month expense summary folders
- **Category Management Modal** — Add/edit/delete categories
- **Theme Toggle** — Dark/Light mode with local storage persistence
- **FAB (Floating Action Button)** — Quick access to new entry form

**CSS Architecture:**
- Uses CSS custom properties (variables) for theming
- Premium Emerald & Gold color scheme
- Fully responsive (max-width 480px shell)
- Print-optimized styles

**JavaScript Features:**
- AJAX form submission with CSRF protection
- SweetAlert2 for confirmations
- Live clock, theme toggle, sidebar control
- Webcam photo capture and preview
- Expense filtering without page reload

### Service — Image Processing

#### `Expense/Services/ImageService.php`

Handles webcam photo capture and processing:

1. Decodes base64 image data
2. Validates MIME type (JPEG, PNG, WebP)
3. Resizes to max 800px width (maintaining aspect ratio)
4. Saves as JPEG (85% quality) to `uploads/expense/YYYY-MM/` directory
5. Returns the relative web path for database storage

---

## 🔗 Routing & Navigation

### Sidebar Link (Updated)

The main sidebar link in `Views/partials/sidebar.php` has been updated:

```
Before: <a href="expense.php" class="action-item">
After:  <a href="Expense/index.php" class="action-item">
```

### Old File Renaming

All previous expense files have been renamed with `_old` suffix for review:

| Original Path | Renamed To |
|---------------|------------|
| `public_html/expense.php` | `public_html/expense_old.php` |
| `public_html/Controllers/ExpenseController.php` | `public_html/Controllers/ExpenseController_old.php` |
| `public_html/Views/expense_view.php` | `public_html/Views/expense_view_old.php` |
| `public_html/Models/Interfaces/ExpenseRepoInterface.php` | `public_html/Models/Interfaces/ExpenseRepoInterface_old.php` |
| `public_html/Models/Repositories/ExpenseRepository.php` | `public_html/Models/Repositories/ExpenseRepository_old.php` |
| `public_html/Services/ImageService.php` | `public_html/Services/ImageService_old.php` |

> **Note:** Staff expense files located in `Staff/Modules/Expense/` were **not** migrated. They remain in their original location and are managed separately.

---

## 🖼️ Image Handling

- **Existing images** remain in `public_html/uploads/expense/YYYY-MM/`
- The `ImageService` in the new module saves to the **same** directory (`public_html/uploads/expense/`)
- The View renders images with `../../uploads/expense/...` path (relative from `Expense/Views/`)
- All existing photos continue to render properly without any changes

---

## 🔐 Security Features

- **CSRF Protection** — Token validated on all POST/AJAX requests
- **Role-based Access** — Viewer (read-only), Manager (today only), Admin (full access)
- **Admin Password Verification** — Required for delete/edit operations
- **Input Sanitization** — `htmlspecialchars`, `strip_tags`, type casting
- **SQL Injection Prevention** — Prepared statements (PDO)
- **Session Security** — HTTP-only cookies, strict mode, session timeouts
- **Error Logging** — All errors logged to `Expense/Logs/expense_error_log.txt`
- **Directory Protection** — `.htaccess` files block direct access to backend directories

---

## 🚀 How to Access

Access the module at: `/Expense/index.php`

Or click the **খরচ** button in the sidebar from the dashboard.

---

## 🛠 Maintenance

To add new features:

1. **New database queries** — Add methods to `ExpenseRepoInterface.php` and implement in `ExpenseRepository.php`
2. **New API endpoints** — Add cases in `ExpenseController::handleRequest()` under the `ajax_action` switch
3. **UI changes** — Edit `Expense/Views/expense_view.php`
4. **New services** — Add classes to `Expense/Services/` and inject them in `Expense/index.php`

---

*Last updated: July 2026 — Sada Kalo Fashion™*

---

## 🆕 Changelog — v1.1 (Audit Integration)

### নতুন
- `Services/AuditInit.php` — `AuditInit::boot($conn)` একবার কল

### আপডেট
| ফাইল | পরিবর্তন |
|------|----------|
| `index.php` | AuditInit load + boot |
| `Controllers/ExpenseController.php` | save/edit/delete + category-এ AuditLogger |
| `Models/Repositories/ExpenseRepository.php` | `saveExpense(): int`, `getExpenseById()`, `getCategoryById()` |
| `Models/Interfaces/ExpenseRepoInterface.php` | নতুন মেথড সিগনেচার |

### Audit কভারেজ
| অ্যাকশন | Module | Action |
|---------|--------|--------|
| খরচ সেভ | `expenses` | CREATE |
| খরচ এডিট | `expenses` | UPDATE |
| খরচ ডিলিট | `expenses` | DELETE |
| ক্যাটাগরি add/edit/delete/toggle | `expense_categories` | CREATE/UPDATE/DELETE |

> নির্ভরতা: `public_html/Audit/` মডিউল। না থাকলে নীরবে স্কিপ।


*Last updated: August 2026 — Sada Kalo Fashion™*
