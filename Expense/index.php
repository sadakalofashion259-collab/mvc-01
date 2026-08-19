<?php
declare(strict_types=1);

/**
 * Expense Module — Entry Point
 * ─────────────────────────────────────────────────────────────
 * কাজ: লগইন ভেরিফাই করে, কনফিগ লোড করে, কন্ট্রোলার চালায়
 */

session_start();
@ini_set('memory_limit', '256M');

// ---- কনফিগ লোড (নতুন) ----
require_once __DIR__ . '/Config/config.php';

// Error logging
ini_set('log_errors', '1');
ini_set('display_errors', '');
if (!is_dir(__DIR__ . '/Logs')) {
    mkdir(__DIR__ . '/Logs', 0755, true);
}
ini_set('error_log', __DIR__ . '/Logs/expense_error_log.txt');

// ============================================================
//  🚫 কঠোর লগইন চেক — লগইন ছাড়া কিছুই চলবে না
// ============================================================
if (ENFORCE_LOGIN) {
    if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: /index.php');
        exit;
    }
}

// ---- ডাটাবেস ও অথ কের্নেল (শুধু লগইন থাকলেই) ----
require_once __DIR__ . '/../Core/Bootstrap.php'; // $conn পাওয়া যাবে

if (!empty($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    require_once __DIR__ . '/../Core/AuthKernel.php';
    AuthKernel::enforce($conn); // টাইমআউট, সিঙ্গেল সেশন, ব্লক চেক
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// MVC Components
require_once __DIR__ . '/Models/Interfaces/ExpenseRepoInterface.php';
require_once __DIR__ . '/Models/Repositories/ExpenseRepository.php';
require_once __DIR__ . '/Services/ImageService.php';
require_once __DIR__ . '/Services/AuditInit.php';
require_once __DIR__ . '/Controllers/ExpenseController.php';

AuditInit::boot($conn);

$repository   = new ExpenseRepository($conn);
$imageService = new ImageService();

$controller = new ExpenseController($repository, $imageService);
$controller->handleRequest();