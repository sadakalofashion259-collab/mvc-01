<?php
declare(strict_types=1);

/**
 * public_html/Audit/db_connect.php  — Audit মডিউলের বুটস্ট্র্যাপ
 * ─────────────────────────────────────────────────────────────────
 * প্রতিটি Audit পেজের একদম প্রথম লাইনে লেখো:
 *
 *   require_once __DIR__ . '/db_connect.php';
 *
 * এই ফাইল যা করে (ক্রমানুসারে):
 *   ১) রুটের db_connect.php  → session, $conn (PDO), timezone
 *   ২) AuthKernel::enforce()  → idle-timeout, single-session, block-check
 *   ৩) Audit ক্লাস লোড       → AuditLogModel, AuditLogger
 *   ৪) AuditLogger::init()   → $conn পাস করে ready করা
 *   ৫) Login guard            → লগইন না থাকলে /index.php-তে redirect
 */

// ── ১. রুট পাথ ──────────────────────────────────────────────────────────
$root = dirname(__DIR__);   // public_html/

// ── ২. রুটের বুটস্ট্র্যাপ (session শুরু, $conn তৈরি, SessionGuard লোড) ─
require_once $root . '/db_connect.php';

// ── ৩. AuthKernel (idle-timeout + single-session + block-check) ─────────
//       AuthKernel নিজেই SessionGuard লোড করে, তাই আলাদা করতে হবে না।
require_once $root . '/Core/AuthKernel.php';
AuthKernel::enforce($conn);

// ── ৪. Audit ক্লাস অটোলোড ───────────────────────────────────────────────
require_once __DIR__ . '/AuditLogModel.php';
require_once __DIR__ . '/AuditLogger.php';

// ── ৫. AuditLogger প্রস্তুত করা ─────────────────────────────────────────
//       $conn রুটের db_connect.php থেকে এসেছে — নতুন connection নয়।
AuditLogger::init($conn);

// ── ৬. Login guard ───────────────────────────────────────────────────────
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /index.php', true, 303);
    exit;
}
