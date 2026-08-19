<?php
declare(strict_types=1);

/**
 * BankCard Module Bootstrap
 * Independent modular MVC under public_html/BankCard/
 */

define('MODULE_ROOT', __DIR__);
define('MODULE_URL', '/BankCard'); // change if installed elsewhere

// App root (public_html)
$appRoot = dirname(__DIR__);

// Load core DB + session + auth (idle timeout, single session, block check)
require_once $appRoot . '/db_connect.php';

// Prefer AuthKernel when available (modules)
$authKernel = $appRoot . '/Core/AuthKernel.php';
if (is_file($authKernel)) {
    require_once $authKernel;
    if (class_exists('AuthKernel', false) && isset($conn) && $conn instanceof PDO) {
        AuthKernel::enforce($conn);
    }
}

// Module-level admin-only gate
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /index.php', true, 303);
    exit;
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /dashboard.php', true, 303);
    exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ensure upload dirs exist
foreach (['uploads/credit_cards', 'uploads/card_receipts', 'Logs'] as $d) {
    $path = MODULE_ROOT . '/' . $d;
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}


// CARD_ENC_KEY ডিফাইন করা না থাকলে ফলব্যাক ব্যবহারের বদলে এক্সেপশন থ্রো করুন
if (!defined('CARD_ENC_KEY')) {
    throw new Exception("CRITICAL ERROR: CARD_ENC_KEY is not defined in environment variables.");
}

// Prevent browser resubmit / back-button form replay on GET
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
