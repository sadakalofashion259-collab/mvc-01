<?php
declare(strict_types=1);

/**
 * bootstrap.php — Single entry point for every page in the Customer module.
 *
 * Responsibilities:
 *   - Output buffering + security headers
 *   - Hardened session (HttpOnly, SameSite, Secure when HTTPS)
 *   - Login guard (redirects to the login page)
 *   - Unified auth enforcement via Core/AuthKernel.php:
 *       20-minute idle timeout (last_action_time + last_activity synced),
 *       single-active-session check (SessionGuard token),
 *       users.last_active heartbeat, account block check
 *   - CSRF token provisioning ($csrf_token)
 *   - Timezone, .env loading and PDO connection ($conn)
 */

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Database.php';

ob_start();

// ---------------------------------------------
// Security headers
// ---------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=utf-8');

// ---------------------------------------------
// Hardened session
// ---------------------------------------------
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

Env::load();
$loginPage = Env::get(['LOGIN_URL'], '/index.php');

// Login guard
if (!isset($_SESSION['loggedin'])) {
    header('Location: ' . $loginPage);
    exit;
}

// ---------------------------------------------
// Environment
// ---------------------------------------------
date_default_timezone_set(Env::get(['APP_TIMEZONE', 'TIMEZONE'], 'Asia/Dhaka'));

/** @var \PDO $conn */
$conn = Database::getConnection();

// Audit Logger (একবার)
require_once dirname(__DIR__) . '/Helpers/AuditInit.php';
AuditInit::boot($conn);

// ---------------------------------------------
// Unified auth enforcement (shared with Suppliers module)
// Replaces the previous hand-rolled idle-timeout block. AuthKernel mirrors
// db_connect.php lines 109-198: 20-min idle timeout (syncing the root
// last_action_time key with this module's last_activity key), the
// SessionGuard single-active-session token check (previously missing here),
// the users.last_active heartbeat and the account block check.
// ---------------------------------------------
require_once dirname(__DIR__, 2) . '/Core/AuthKernel.php';
AuthKernel::enforce($conn);

// Periodic session-ID rotation (every 30 minutes) — mitigates fixation/hijack.
if (!isset($_SESSION['sid_rotated_at'])) {
    $_SESSION['sid_rotated_at'] = time();
} elseif ((time() - (int)$_SESSION['sid_rotated_at']) > 1800) {
    session_regenerate_id(true);
    $_SESSION['sid_rotated_at'] = time();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
