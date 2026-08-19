<?php
declare(strict_types=1);

/**
 * public_html/Audit/index.php  — Audit মডিউলের একমাত্র entry point
 *
 * URL:
 *   /Audit/                    → লগ লিস্ট (HTML)
 *   /Audit/?detail=123         → JSON (AJAX modal)
 *   /Audit/?export=csv         → CSV ডাউনলোড
 */

// বুটস্ট্র্যাপ: session, $conn, AuthKernel, AuditLogger, login guard
require_once __DIR__ . '/db_connect.php';

// ── শুধু admin দেখতে পারবে ──────────────────────────────────────────────
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('<div style="font-family:sans-serif;text-align:center;padding:80px 20px;color:#ef4444;">
            <h2>⛔ অনুমতি নেই</h2>
            <p>এই পেজ শুধুমাত্র অ্যাডমিনের জন্য।</p>
            <a href="/index.php">← ফিরে যান</a>
         </div>');
}

require_once __DIR__ . '/AuditLogController.php';
(new AuditLogController($conn))->handle();
