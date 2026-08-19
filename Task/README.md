# Task Module — SADA KALO FASHION

## 📋 Overview
This is a **complete MVC-structured Daily Task/Routine Management System** built with PHP, PDO, MySQL, and modern JavaScript (Web Speech API).

## 🏗️ Folder Structure
```
Task/
├── Config/
│   └── database.php          # Database connection configuration
├── Controllers/
│   └── TaskController.php     # Request handler (CSRF + Role validation)
├── Models/
│   ├── TaskModelInterface.php # Contract/Interface
│   └── TaskModel.php          # Data access layer (PDO)
├── Views/
│   └── routine_manager.php    # UI template (Bengali + RTL ready)
├── Logs/
│   ├── error_log.txt          # Database errors
│   └── action_log.txt         # Successful actions
├── index.php                  # Entry point (Front Controller)
└── README.md                  # This file
```

## 🚀 Features
- ✅ **MVC Architecture** — Clean separation of concerns
- ✅ **Role-Based Access** — Admin vs Regular user permissions
- ✅ **CSRF Protection** — Token-based security
- ✅ **PDO Prepared Statements** — SQL injection prevention
- ✅ **XSS Prevention** — `htmlspecialchars()` on all output
- ✅ **Transaction Safety** — All writes in `beginTransaction/commit/rollback`
- ✅ **PRG Pattern** — Post/Redirect/Get prevents double submissions
- ✅ **Bengali UI** — Full Bengali language interface
- ✅ **Voice Typing** — Bengali voice-to-text (Web Speech API)
- ✅ **Text-to-Speech** — Read aloud Bengali text
- ✅ **Dark/Light Mode** — With localStorage persistence
- ✅ **4 Color Themes** — Indigo, Emerald, Sunset, Ocean
- ✅ **Responsive Design** — Mobile-first CSS

## 🛠️ Requirements
- PHP 8.+
- MySQL 5.7+
- PDO MySQL extension
- Web browser with SpeechRecognition API (Chrome/Edge recommended)

## 📦 Installation

### Option 1: Standalone (with own database)
1. Edit `Config/database.php` — set `$useMainProjectDb = false`
2. Fill in your database credentials in `$DB_CONFIG` array
3. Run the SQL schema (see `-- Database Schema --` below)

### Option 2: Integrate with main project
1. Ensure `db_connect.php` exists in the project root
2. `Config/database.php` will auto-detect and use it
3. The module shares the main project's database connection

## 🗄️ Database Schema
```sql
CREATE TABLE IF NOT EXISTS `daily_tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_title` VARCHAR(255) NOT NULL,
    `task_description` TEXT NULL,
    `task_date` DATE NOT NULL,
    `task_time` TIME NOT NULL,
    `assigned_to` VARCHAR(500) NOT NULL COMMENT 'Comma-separated usernames or "all"',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `completed_by` VARCHAR(100) NULL,
    `completed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_date` (`task_date`),
    INDEX `idx_assigned` (`assigned_to`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

The module also expects a `users` table with `username` and `role` columns for user listing.

## 🔧 Usage
1. Place the `Task/` folder in your project root
2. Access via: `https://yourdomain.com/Task/`
3. Login is required (shared with main project)
4. Admin users can create/edit/delete tasks
5. Regular users can mark tasks as done

## 🌐 API Reference

### TaskModelInterface methods:
| Method | Parameters | Description |
|--------|------------|-------------|
| `getAllUsers()` | — | Returns all system users |
| `getTasks()` | `$username, $role` | Returns user-specific tasks |
| `addTask()` | `$title, $date, $time, $assignedTo, $description` | Create new task |
| `markTaskAsDone()` | `$id, $username` | Mark task as completed |
| `toggleTaskStatus()` | `$id, $newStatus` | Toggle active/inactive |
| `deleteTask()` | `$id` | Delete a task |

### TaskController actions (POST):
| `routine_action` | Required Role | Description |
|-----------------|--------------|-------------|
| `add` | Admin | Create new task |
| `mark_done` | Any | Mark task completed |
| `toggle` | Admin | Change task status |
| `delete` | Admin | Remove task |

## 🔒 Security
- CSRF tokens validated on all POST requests
- Role verification (`admin` vs regular) for sensitive actions
- PDO prepared statements prevent SQL injection
- `htmlspecialchars()` with `ENT_QUOTES` prevents XSS
- Session-based authentication with timeout
- Secure log storage in `Logs/` directory

## 📝 License
Proprietary — SADA KALO FASHION Internal Use Only
