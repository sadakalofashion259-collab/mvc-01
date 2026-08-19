<?php
/**
 * ========================================================
 * Historys — Entry Point (Router)
 * ========================================================
 * MVC প্যাটার্ন:
 *   index.php  → HistorysController → HistorysModel → views/
 * ========================================================
 */
session_start();
date_default_timezone_set('Asia/Dhaka');

// ── Auth Check ──────────────────────────────────────────
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

// ── DB Connection ────────────────────────────────────────
require_once __DIR__ . '/../db_connect.php';   // $conn (PDO)

// ── MVC Layer Load ───────────────────────────────────────
require_once __DIR__ . '/Model/HistorysModel.php';
require_once __DIR__ . '/Controller/HistorysController.php';

// ── Dispatch ─────────────────────────────────────────────
$controller = new HistorysController($conn);
$controller->dispatch();
