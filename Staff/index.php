<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
    session_unset(); session_destroy();
    echo "<script>alert('Session Expired! Auto Logout.'); window.location.href='../index.php';</script>"; exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo "<script>window.location.href='../index.php';</script>"; exit;
}

include __DIR__ . '/db_connect.php';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'admin';
date_default_timezone_set('Asia/Dhaka');

// নোটিফিকেশন টগল কনফিগ (SMS/Email ON-OFF)
require_once __DIR__ . '/Core/Config.php';
$is_admin_user = ($role === 'admin' || $role === 'Admin');

// =============================================
// AJAX: SMS/Email নোটিফিকেশন টগল সেভ (শুধু Admin)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_notification_settings') {
    header('Content-Type: application/json; charset=UTF-8');
    if (!$is_admin_user) {
        echo json_encode(['status' => 'error', 'message' => 'Access Denied! Admin Only.']); exit;
    }
    $sms   = ($_POST['sms_enabled']   ?? '') === '1';
    $email = ($_POST['email_enabled'] ?? '') === '1';
    $saved = staff_save_notification_settings($sms, $email, $_SESSION['username'] ?? 'Admin');
    if ($saved) {
        echo json_encode([
            'status'  => 'success',
            'message' => 'সেটিং সেভ হয়েছে! SMS: ' . ($sms ? 'ON' : 'OFF') . ' | Email: ' . ($email ? 'ON' : 'OFF'),
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'সেটিং ফাইল লেখা যায়নি! (Core/settings.json permission চেক করুন)']);
    }
    exit;
}

$notify_settings = staff_notification_settings();

$loan_staffs = [];
if ($role === 'admin' || $role === 'Admin') {
    try {
        $loanQuery = $conn->query("
            SELECT s.id, s.staff_name, s.profile_pic, s.staff_salary,
                COALESCE(exp_sub.total_exp,0) AS total_expenses,
                COALESCE(att_sub.payable_days,0)*(s.staff_salary/30.0) AS total_earned,
                COALESCE(exp_sub.total_exp,0)-COALESCE(att_sub.payable_days,0)*(s.staff_salary/30.0) AS real_balance
            FROM staff_info s
            LEFT JOIN (SELECT staff_id, SUM(amount) AS total_exp FROM staff_expenses GROUP BY staff_id) exp_sub ON s.id=exp_sub.staff_id
            LEFT JOIN (SELECT staff_id, SUM(CASE WHEN status='Present' THEN 1.0 WHEN status='Half' THEN 0.5 ELSE 0 END) AS payable_days FROM staff_attendance GROUP BY staff_id) att_sub ON s.id=att_sub.staff_id
            WHERE s.staff_status='Active' AND s.email_verified=1
            HAVING real_balance>0 ORDER BY real_balance DESC
        ");
        if ($loanQuery) $loan_staffs = $loanQuery->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Staff Dashboard — SADA KALO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="Assets/css/theme.css">
    <style>
        /* Dashboard header */
        .dash-header {
            background: linear-gradient(135deg, #1a0940 0%, #0D0B22 60%, #0A0918 100%);
            padding: 20px 16px 28px;
            position: relative;
            overflow: hidden;
        }
        .dash-header::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 160px; height: 160px;
            background: radial-gradient(circle, rgba(124,92,252,0.3) 0%, transparent 70%);
            border-radius: 50%;
        }
        .dash-header::after {
            content: '';
            position: absolute;
            bottom: -30px; left: -20px;
            width: 120px; height: 120px;
            background: radial-gradient(circle, rgba(0,206,136,0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        .brand-logo {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #7C5CFC, #A07AF0);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: #fff;
            box-shadow: 0 4px 16px rgba(124,92,252,0.4);
            flex-shrink: 0;
        }
        .brand-name {
            font-size: 16px;
            font-weight: 900;
            letter-spacing: 1px;
            color: #fff;
            line-height: 1.1;
        }
        .brand-sub {
            font-size: 9px;
            font-weight: 700;
            color: rgba(255,255,255,0.5);
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .date-chip {
            background: rgba(124,92,252,0.15);
            border: 1px solid rgba(124,92,252,0.25);
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 11px;
            font-weight: 700;
            color: #A07AF0;
        }

        /* Menu Grid */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 20px 0;
        }
        @media (max-width: 380px) { .menu-grid { grid-template-columns: repeat(2,1fr); gap: 10px; } }
        @media (min-width: 500px) { .menu-grid { grid-template-columns: repeat(4,1fr); } }

        .menu-btn {
            background: var(--sk-card);
            border: 1px solid var(--sk-border);
            border-radius: 16px;
            padding: 18px 8px 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-decoration: none !important;
            transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .menu-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.2s;
            border-radius: 16px;
        }
        .menu-btn:hover { transform: translateY(-4px); box-shadow: 0 8px 32px rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.12); }
        .menu-btn:hover::before { opacity: 0.05; }
        .menu-btn:active { transform: scale(0.94); }

        .menu-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            color: #fff;
            flex-shrink: 0;
        }
        .menu-label {
            font-size: 11px;
            font-weight: 800;
            text-align: center;
            color: var(--sk-text);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.2;
        }

        /* Icon gradients */
        .gi-add    { background: linear-gradient(135deg,#00CE88,#009966); box-shadow: 0 4px 14px rgba(0,206,136,0.4); }
        .gi-list   { background: linear-gradient(135deg,#2196F3,#0052D4); box-shadow: 0 4px 14px rgba(33,150,243,0.4); }
        .gi-att    { background: linear-gradient(135deg,#FFB347,#FF7742); box-shadow: 0 4px 14px rgba(255,179,71,0.4); }
        .gi-his    { background: linear-gradient(135deg,#7C5CFC,#A855F7); box-shadow: 0 4px 14px rgba(124,92,252,0.4); }
        .gi-exp    { background: linear-gradient(135deg,#FF4A6A,#CC1133); box-shadow: 0 4px 14px rgba(255,74,106,0.4); }
        .gi-ehis   { background: linear-gradient(135deg,#F953C6,#B91D73); box-shadow: 0 4px 14px rgba(249,83,198,0.4); }
        .gi-prof   { background: linear-gradient(135deg,#00C9FF,#0072FF); box-shadow: 0 4px 14px rgba(0,201,255,0.4); }
        .gi-inact  { background: linear-gradient(135deg,#74748A,#404060); box-shadow: 0 4px 14px rgba(116,116,138,0.4); }
        .gi-pay    { background: linear-gradient(135deg,#11998E,#38EF7D); box-shadow: 0 4px 14px rgba(56,239,125,0.35); }
        .gi-adm    { background: linear-gradient(135deg,#E44D26,#F16529); box-shadow: 0 4px 14px rgba(228,77,38,0.4); }

        /* Loan Alert */
        .loan-section {
            background: rgba(255,74,106,0.06);
            border: 1px solid rgba(255,74,106,0.2);
            border-radius: 16px;
            padding: 16px;
            margin-top: 4px;
        }
        .loan-header {
            display: flex; align-items: center; gap: 8px;
            font-size: 11px; font-weight: 900;
            text-transform: uppercase; letter-spacing: 1px;
            color: #FF4A6A;
            margin-bottom: 12px;
        }
        .loan-item {
            display: flex; align-items: center; justify-content: space-between;
            background: var(--sk-card);
            border: 1px solid rgba(255,74,106,0.15);
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 8px;
        }
        .loan-item:last-child { margin-bottom: 0; }
        .loan-amount {
            font-size: 14px; font-weight: 900;
            color: #FF4A6A;
        }

        /* Bottom Fixed Navigation Bar (Mobile App Style) */
        .bottom-nav {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1000;
            display: flex;
            align-items: stretch;
            background: var(--sk-card, #16142B);
            border-top: 1px solid var(--sk-border, #2A2744);
            box-shadow: 0 -6px 24px rgba(0,0,0,0.35);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            padding: 6px 4px calc(6px + env(safe-area-inset-bottom, 0px));
        }
        .bottom-nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            padding: 6px 2px;
            border-radius: 14px;
            text-decoration: none !important;
            color: var(--sk-text-muted, #8B87A5);
            transition: all 0.2s;
            min-width: 0;
        }
        .bottom-nav-item i { font-size: 17px; }
        .bottom-nav-item span {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }
        .bottom-nav-item:hover { color: var(--sk-text, #EAE8F4); }
        .bottom-nav-item.active {
            color: #A07AF0;
            background: rgba(124,92,252,0.14);
        }
        .bottom-nav-item:active { transform: scale(0.94); }
        [data-bs-theme="light"] .bottom-nav {
            background: #ffffff;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
        }
        body { padding-bottom: 84px; } /* ফিক্সড বটম নেভবারের জন্য জায়গা */

        /* Notification Settings (Admin toggle) */
        .settings-section {
            background: var(--sk-card);
            border: 1px solid var(--sk-border);
            border-radius: 16px;
            padding: 16px;
            margin: 16px 0 4px;
        }
        .settings-header {
            display: flex; align-items: center; gap: 8px;
            font-size: 11px; font-weight: 900;
            text-transform: uppercase; letter-spacing: 1px;
            color: var(--sk-primary, #7C5CFC);
            margin-bottom: 12px;
        }
        .setting-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 4px;
            border-bottom: 1px dashed var(--sk-border);
        }
        .setting-row:last-of-type { border-bottom: none; }
        .setting-label { display: flex; align-items: center; gap: 10px; }
        .setting-icon {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; color: #fff; flex-shrink: 0;
        }
        .setting-name { font-size: 13px; font-weight: 800; }
        .setting-sub { font-size: 10px; color: var(--sk-text-muted); }
        .sk-switch { position: relative; display: inline-block; width: 52px; height: 28px; flex-shrink: 0; }
        .sk-switch input { opacity: 0; width: 0; height: 0; }
        .sk-slider {
            position: absolute; cursor: pointer; inset: 0;
            background: #3d3a55; border-radius: 28px; transition: 0.3s;
        }
        .sk-slider:before {
            content: ''; position: absolute;
            width: 22px; height: 22px; left: 3px; top: 3px;
            background: #fff; border-radius: 50%; transition: 0.3s;
        }
        .sk-switch input:checked + .sk-slider { background: var(--sk-success, #00CE88); }
        .sk-switch input:checked + .sk-slider:before { transform: translateX(24px); }
        #settingsMsg { font-size: 11px; font-weight: 800; margin-top: 8px; display: none; }

        /* Light mode adjustments */
        [data-bs-theme="light"] .dash-header {
            background: linear-gradient(135deg, #5b3fcf 0%, #7C5CFC 100%);
        }
        [data-bs-theme="light"] .menu-btn { box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        [data-bs-theme="light"] .loan-section { background: rgba(255,74,106,0.04); }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="sk-navbar">
    <a href="../index.php" class="btn-back"><i class="fa fa-chevron-left"></i> বের হন</a>
    <div class="sk-navbar-title">Staff Dashboard<span class="sk-navbar-subtitle">SADA KALO FASHION</span></div>
    <button class="btn-theme" onclick="toggleTheme()"><i id="themeIcon" class="fas fa-sun"></i></button>
</nav>


<!-- Dashboard Header -->
<div class="dash-header">
    <div style="position:relative;z-index:1;">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="brand-logo"><i class="fa fa-store"></i></div>
            <div>
                <div class="brand-name">SADA KALO</div>
                <div class="brand-sub">Fashion Staff System</div>
            </div>
        </div>
        <div class="date-chip"><i class="fa fa-calendar-day me-1"></i><?php echo date('d F, Y — D'); ?></div>
    </div>
</div>

<!-- Page Content -->
<div class="page-wrap mt-3">

    <!-- Banner -->
    <div class="sk-banner animate-in">
        <img src="Assets/images/banner.jpg" alt="Banner" onerror="this.parentElement.style.background='linear-gradient(135deg,#7C5CFC,#A07AF0)'; this.style.display='none'">
    </div>

    <!-- Menu Grid -->
    <div class="menu-grid animate-in delay-1">

        <a href="Modules/Staff/Create.php" class="menu-btn">
            <div class="menu-icon gi-add"><i class="fa fa-user-plus"></i></div>
            <span class="menu-label">Add Staff</span>
        </a>

        <a href="Modules/Staff/List.php" class="menu-btn">
            <div class="menu-icon gi-list"><i class="fa fa-users"></i></div>
            <span class="menu-label">Staff List</span>
        </a>

        <a href="Modules/Attendance/Daily.php" class="menu-btn">
            <div class="menu-icon gi-att"><i class="fa fa-fingerprint"></i></div>
            <span class="menu-label">Attendance</span>
        </a>

        <a href="Modules/Attendance/History.php" class="menu-btn">
            <div class="menu-icon gi-his"><i class="fa fa-calendar-alt"></i></div>
            <span class="menu-label">Att. History</span>
        </a>

        <a href="Modules/Expense/Create.php" class="menu-btn">
            <div class="menu-icon gi-exp"><i class="fa fa-hand-holding-dollar"></i></div>
            <span class="menu-label">Expense</span>
        </a>

        <a href="Modules/Expense/History.php" class="menu-btn">
            <div class="menu-icon gi-ehis"><i class="fa fa-file-invoice-dollar"></i></div>
            <span class="menu-label">Exp. History</span>
        </a>

        <a href="Modules/Staff/ProfileList.php" class="menu-btn">
            <div class="menu-icon gi-prof"><i class="fa fa-id-card"></i></div>
            <span class="menu-label">Staff Profile</span>
        </a>

        <a href="Modules/Staff/InactiveList.php" class="menu-btn">
            <div class="menu-icon gi-inact"><i class="fa fa-archive"></i></div>
            <span class="menu-label">Inactive</span>
        </a>

        <a href="Modules/Payroll/Payslip.php" class="menu-btn">
            <div class="menu-icon gi-pay"><i class="fa fa-money-check-dollar"></i></div>
            <span class="menu-label">Payslip</span>
        </a>

        <?php if ($role === 'admin' || $role === 'Admin'): ?>
        <a href="Modules/Staff/AdminControl.php" class="menu-btn">
            <div class="menu-icon gi-adm"><i class="fa fa-user-shield"></i></div>
            <span class="menu-label">Admin Control</span>
        </a>
        <?php endif; ?>

    </div>

    <?php if ($is_admin_user): ?>
    <!-- Notification Settings (SMS/Email ON-OFF) -->
    <div class="settings-section animate-in delay-2">
        <div class="settings-header">
            <i class="fa fa-sliders"></i>
            <span>Notification Settings — SMS / Email নিয়ন্ত্রণ</span>
        </div>

        <div class="setting-row">
            <div class="setting-label">
                <div class="setting-icon" style="background:linear-gradient(135deg,#00CE88,#009966);"><i class="fa fa-comment-sms"></i></div>
                <div>
                    <div class="setting-name">SMS Notifications</div>
                    <div class="setting-sub">হাজিরা, খরচ, OTP ও ওয়েলকাম SMS পাঠানো হবে কি না</div>
                </div>
            </div>
            <label class="sk-switch">
                <input type="checkbox" id="toggleSms" <?php echo $notify_settings['sms_enabled'] ? 'checked' : ''; ?>>
                <span class="sk-slider"></span>
            </label>
        </div>

        <div class="setting-row">
            <div class="setting-label">
                <div class="setting-icon" style="background:linear-gradient(135deg,#2196F3,#0052D4);"><i class="fa fa-envelope"></i></div>
                <div>
                    <div class="setting-name">Email Notifications</div>
                    <div class="setting-sub">হাজিরা, খরচ, OTP ইমেইল ও অ্যাডমিন কপি পাঠানো হবে কি না</div>
                </div>
            </div>
            <label class="sk-switch">
                <input type="checkbox" id="toggleEmail" <?php echo $notify_settings['email_enabled'] ? 'checked' : ''; ?>>
                <span class="sk-slider"></span>
            </label>
        </div>

        <div id="settingsMsg"></div>
    </div>
    <?php endif; ?>

    <!-- Loan Alerts -->
    <?php if (!empty($loan_staffs)): ?>
    <div class="loan-section animate-in delay-3">
        <div class="loan-header">
            <i class="fa fa-triangle-exclamation"></i>
            <span>Loan / Advance Alerts — <?php echo count($loan_staffs); ?> জন</span>
        </div>
        <?php foreach($loan_staffs as $ls): ?>
        <div class="loan-item">
            <div class="d-flex align-items-center gap-3">
                <img src="Uploads/profiles/<?php echo htmlspecialchars($ls['profile_pic'] ?: 'default.png'); ?>"
                     class="sk-avatar" style="border-color:rgba(255,74,106,0.4);"
                     onerror="this.src='Uploads/profiles/default.png'">
                <div>
                    <div style="font-size:13px;font-weight:800;"><?php echo htmlspecialchars($ls['staff_name']); ?></div>
                    <div style="font-size:10px;color:var(--sk-text-muted);">অ্যাডভান্স / ঋণ বাকি</div>
                </div>
            </div>
            <div class="loan-amount">Tk. <?php echo number_format($ls['real_balance'],2); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="height:20px;"></div>
</div>

<!-- Bottom Fixed Navigation Bar (হোম · স্টাফ · হাজিরা · খরচ · পেস্লিপ) -->
<?php $nav_base = ''; include 'staff_bottom_nav.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    const t = localStorage.getItem('sk_theme') || 'dark';
    document.documentElement.setAttribute('data-bs-theme', t);
    document.addEventListener('DOMContentLoaded', function(){
        const icon = document.getElementById('themeIcon');
        if(icon) icon.className = t==='dark' ? 'fas fa-sun' : 'fas fa-moon';
    });
})();
function toggleTheme(){
    const cur = document.documentElement.getAttribute('data-bs-theme');
    const next = cur==='dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', next);
    localStorage.setItem('sk_theme', next);
    document.getElementById('themeIcon').className = next==='dark' ? 'fas fa-sun' : 'fas fa-moon';
}

/* Notification Settings টগল সেভ (Admin) */
(function(){
    const sms = document.getElementById('toggleSms');
    const email = document.getElementById('toggleEmail');
    if (!sms || !email) return;

    function saveSettings(){
        const msg = document.getElementById('settingsMsg');
        const fd = new FormData();
        fd.append('action', 'save_notification_settings');
        fd.append('sms_enabled', sms.checked ? '1' : '0');
        fd.append('email_enabled', email.checked ? '1' : '0');
        fetch('index.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                msg.style.display = 'block';
                if (res.status === 'success') {
                    msg.style.color = 'var(--sk-success)';
                    msg.textContent = '✅ ' + res.message;
                } else {
                    msg.style.color = 'var(--sk-danger)';
                    msg.textContent = '❌ ' + res.message;
                }
                setTimeout(() => { msg.style.display = 'none'; }, 4000);
            })
            .catch(() => {
                msg.style.display = 'block';
                msg.style.color = 'var(--sk-danger)';
                msg.textContent = '❌ সার্ভার এরর! সেটিং সেভ হয়নি।';
            });
    }
    sms.addEventListener('change', saveSettings);
    email.addEventListener('change', saveSettings);
})();
</script>
</body>
</html>
