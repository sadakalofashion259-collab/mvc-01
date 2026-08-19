<?php
// ছবির পাথ root-absolute (এন্ট্রি stock.php বা Stock/index.php — দুটো থেকেই কাজ করবে)
function stockImgSrc(string $path): string {
    return '/' . ltrim($path, '/');
}
function stockImgExists(string $path): bool {
    return file_exists(dirname(__DIR__, 2) . '/' . ltrim($path, '/'));
}
?>
<!DOCTYPE html> 
<html lang="en"> 
<head>     
    <meta charset="UTF-8">     
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">     
    <title>Sada Kalo Fashion | Stock Analytics</title>     
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">     
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">     
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>     
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>     
    
    <style>         
        :root { 
            --bg-body: #f1f5f9; --bg-card: #ffffff; --text-main: #1e293b; --text-muted: #64748b; 
            --border-color: #e2e8f0; --primary: #2563eb; --primary-hover: #1d4ed8; 
            --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --info: #0ea5e9;
            --green-bg: rgba(16, 185, 129, 0.08); --red-bg: rgba(239, 68, 68, 0.06);
            --radius: 16px; --sidebar-width: 270px;
        }         
        body.dark-mode { 
            --bg-body: #0f172a; --bg-card: #1e293b; --text-main: #f8fafc; 
            --text-muted: #94a3b8; --border-color: #334155; --primary: #3b82f6;
            --green-bg: rgba(16, 185, 129, 0.12); --red-bg: rgba(239, 68, 68, 0.1);
        }         
        
        * { box-sizing: border-box; }
        body { font-family: var(--font-main); background: var(--bg-body); color: var(--text-main); margin: 0; padding: 0; overflow-x: hidden; transition: background 0.3s, color 0.3s; display: flex;}                  
        
        .sidebar { width: var(--sidebar-width); background: var(--bg-card); height: 100vh; position: fixed; left: 0; top: 0; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; z-index: 1000; transition: transform 0.3s ease; box-shadow: 4px 0 15px rgba(0,0,0,0.03); }
        .sidebar-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid var(--border-color); display: flex; flex-direction: column; align-items: center;}
        
        .brand-logo { font-family: var(--font-heading); font-size: 20px; font-weight: 800; color: var(--primary); letter-spacing: 1px; margin: 0; display: flex; align-items: center; gap: 8px;}
        .brand-subtitle { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 4px; letter-spacing: 2px; text-transform: uppercase;}

        .sidebar-menu { flex: 1; overflow-y: auto; padding: 20px 15px; }
        
        .sidebar-link { 
            display: flex; align-items: center; padding: 10px 18px; text-decoration: none; 
            font-weight: 800; font-size: 13px; border-radius: 50px; margin-bottom: 15px; 
            color: var(--text-main); background: var(--bg-card); border: 1px solid var(--border-color);
            box-shadow: 0 4px 0 var(--border-color), 0 5px 10px rgba(0,0,0,0.03);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-link i { 
            width: 32px; height: 32px; background: white; border-radius: 50%; display: flex; 
            align-items: center; justify-content: center; font-size: 14px; margin-right: 12px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.3s; 
        }
        
        .sb-home { background: linear-gradient(135deg, #eff6ff, #ffffff); border-color: #bfdbfe; }
        .sb-home i { color: #2563eb; }
        .sb-home:hover, .sb-home.active { box-shadow: 0 2px 0 #93c5fd, 0 5px 10px rgba(37,99,235,0.2); transform: translateY(2px); }

        .sb-analytics { background: linear-gradient(135deg, #fef3c7, #ffffff); border-color: #fcd34d; }
        .sb-analytics i { color: #d97706; }
        .sb-analytics:hover, .sb-analytics.active { box-shadow: 0 2px 0 #fbd38d, 0 5px 10px rgba(245,158,11,0.2); transform: translateY(2px); }

        .sb-supplier { background: linear-gradient(135deg, #ecfdf5, #ffffff); border-color: #a7f3d0; }
        .sb-supplier i { color: #059669; }
        .sb-supplier:hover, .sb-supplier.active { box-shadow: 0 2px 0 #6ee7b7, 0 5px 10px rgba(16,185,129,0.2); transform: translateY(2px); }

        .sb-customer { background: linear-gradient(135deg, #f5f3ff, #ffffff); border-color: #ddd6fe; }
        .sb-customer i { color: #7c3aed; }
        .sb-customer:hover, .sb-customer.active { box-shadow: 0 2px 0 #c4b5fd, 0 5px 10px rgba(139,92,246,0.2); transform: translateY(2px); }

        .sb-history { background: linear-gradient(135deg, #fdf2f8, #ffffff); border-color: #f9a8d4; }
        .sb-history i { color: #db2777; }
        .sb-history:hover, .sb-history.active { box-shadow: 0 2px 0 #f472b6, 0 5px 10px rgba(219,39,119,0.2); transform: translateY(2px); }
        
        .sidebar-link:active { transform: translateY(4px); box-shadow: 0 0px 0 transparent; }

        body.dark-mode .sidebar-link { background: linear-gradient(135deg, var(--bg-body), var(--bg-card)); border-color: var(--border-color); }
        body.dark-mode .sidebar-link i { background: var(--bg-body); }

        .lock-btn-3d {
            width: 100%; display: flex; justify-content: space-between; align-items: center;
            background: linear-gradient(145deg, var(--bg-card), var(--bg-body)); border: 1px solid var(--border-color); 
            padding: 10px 18px; border-radius: 50px; cursor: pointer; color: var(--text-main); 
            font-size: 12px; font-weight: 800; font-family: var(--font-main);
            box-shadow: 0 4px 0 var(--border-color), 0 5px 10px rgba(0,0,0,0.05);
            margin-bottom: 12px; transition: all 0.2s;
        }
        .lock-btn-3d:active { transform: translateY(4px); box-shadow: 0 0px 0 var(--border-color); }

        .sidebar-footer { padding: 15px; border-top: 1px solid var(--border-color); }
        .sidebar-close { display: none; position: absolute; top: 15px; right: 15px; color: var(--text-muted); font-size: 22px; cursor: pointer; }

        .main-wrapper { flex: 1; margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); min-height: 100vh; transition: margin-left 0.3s ease, width 0.3s ease; }
        
        .top-navbar { background: var(--bg-card); padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 900;}
        .nav-left { display: flex; align-items: center; gap: 15px; }
        .menu-toggle { font-size: 20px; color: var(--text-main); cursor: pointer; display: none; background: transparent; border: none; outline: none;}
        .page-title { margin: 0; font-family: var(--font-heading); font-size: 16px; font-weight: 600; color: var(--text-main); }
        .user-badge { display: flex; align-items: center; gap: 6px; background: var(--bg-body); padding: 5px 10px; border-radius: 20px; border: 1px solid var(--border-color); font-size: 12px; font-weight: 600;}

        .menu-3d-container {
            background: var(--bg-card); border-radius: 24px; padding: 20px; margin: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05), inset 0 2px 0 rgba(255,255,255,0.8);
            display: flex; flex-wrap: wrap; justify-content: center; gap: 12px;
            border: 1px solid var(--border-color);
        }

        .btn-3d {
            background: var(--bg-body); border: 2px solid transparent; border-radius: 18px;
            width: 75px; height: 75px; display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 800; font-family: var(--font-main); transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer; text-decoration: none; outline: none; position: relative;
        }
        .btn-3d i.main-icon { font-size: 22px; margin-bottom: 6px; transition: transform 0.2s;}
        
        .b-box { color: #2563eb; box-shadow: 0 6px 0 #1d4ed8, 0 12px 15px rgba(37,99,235,0.2); }
        .b-box:active { transform: translateY(6px); box-shadow: 0 0px 0 transparent; }
        
        .b-folder { color: #f59e0b; box-shadow: 0 6px 0 #b45309, 0 12px 15px rgba(245,158,11,0.2); }
        .b-folder:active { transform: translateY(6px); box-shadow: 0 0px 0 transparent; }
        
        .b-7day { color: #10b981; box-shadow: 0 6px 0 #047857, 0 12px 15px rgba(16,185,129,0.2); }
        .b-7day:active { transform: translateY(6px); box-shadow: 0 0px 0 transparent; }
        
        .b-entry { color: #8b5cf6; box-shadow: 0 6px 0 #5b21b6, 0 12px 15px rgba(139,92,246,0.2); }
        .b-entry:active { transform: translateY(6px); box-shadow: 0 0px 0 transparent; }
        
        .b-theme { color: #475569; box-shadow: 0 6px 0 #1e293b, 0 12px 15px rgba(71,85,105,0.2); }
        .b-theme:active { transform: translateY(6px); box-shadow: 0 0px 0 transparent; }

        body.dark-mode .btn-3d { background: #1e293b; border-color:#334155; }

        .dynamic-section { display: none; animation: fadeIn 0.4s ease forwards; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

        .placeholder-box {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            height: 40vh; color: var(--text-muted); opacity: 0.5; text-align: center; padding: 20px;
        }
        .placeholder-box i { font-size: 80px; margin-bottom: 15px; }
        .placeholder-box h2 { font-family: var(--font-heading); font-size: 24px; margin:0 0 5px 0; }

        .btn-corp { 
            padding: 8px 14px; font-size: 12px; border-radius: 6px; text-decoration: none; 
            color: white; border: none; cursor: pointer; font-weight: 600; font-family: var(--font-main); 
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-corp:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); filter: brightness(1.05);}
        .btn-primary { background: var(--primary); }
        .btn-success { background: var(--success); }
        .btn-danger { background: var(--danger); }
        .btn-outline { background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color); }
        .btn-block { width: 100%; padding: 12px 20px; font-size: 13px; margin: 10px 0; border-radius: 8px;}

        .content-container { padding: 10px 15px; max-width: 1400px; margin: auto; }
        .grid-panel { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 25px; }
        
        .metric-card {
            background: var(--bg-card); border-radius: var(--radius); padding: 18px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.05), 0 1px 3px rgba(0,0,0,0.1), inset 0 -3px 0 rgba(0,0,0,0.05);
            border: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: center; 
            position: relative; border-top: 4px solid var(--primary);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .metric-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .metric-card.border-success { border-top-color: var(--success); }
        .metric-card.border-warning { border-top-color: var(--warning); }
        .metric-card.border-danger { border-top-color: var(--danger); }
        .metric-card.border-info { border-top-color: var(--info); }
        
        .metric-icon { 
            position: absolute; right: 15px; top: 15px; font-size: 20px; color: var(--primary); opacity: 0.2; 
            background: var(--bg-body); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%;
        }
        .metric-card.border-success .metric-icon { color: var(--success); }
        .metric-card.border-danger .metric-icon { color: var(--danger); }
        .metric-card.border-warning .metric-icon { color: var(--warning); }
        
        .c-title { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;}
        .c-value { font-size: 20px; font-weight: 800; font-family: var(--font-heading); color: var(--text-main);}

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; color: var(--text-main); margin-bottom: 6px; font-weight: 600; }
        input { width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-body); color: var(--text-main); font-size: 14px; font-family: var(--font-main); transition: all 0.2s;}
        input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); transform: translateY(-1px);}
        .cam-frame { border: 2px dashed var(--border-color); border-radius: 8px; padding: 15px; text-align: center; background: var(--bg-body); margin-bottom: 15px;}

        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center;
            backdrop-filter: blur(4px); opacity: 0; transition: opacity 0.3s ease;
        }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-content {
            background: var(--bg-card); width: 92%; max-width: 450px; border-radius: var(--radius);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); padding: 25px; position: relative;
            max-height: 90vh; overflow-y: auto; transform: translateY(30px) scale(0.95); transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .modal-overlay.active .modal-content { transform: translateY(0) scale(1); }
        .modal-close {
            position: absolute; top: 15px; right: 15px; font-size: 20px; color: var(--text-muted); 
            cursor: pointer; background: var(--bg-body); width: 32px; height: 32px; 
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; border: 1px solid var(--border-color);
        }
        .modal-close:hover { color: white; background: var(--danger); border-color: transparent; transform: rotate(90deg);}

        .month-folder { margin-bottom: 25px; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 10px rgba(0,0,0,0.03); overflow: hidden;}
        .month-header { background: var(--bg-body); color: var(--text-main); padding: 15px 18px; font-size: 15px; font-weight: 800; font-family: var(--font-heading); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); cursor:pointer;}
        .month-header:hover { background: rgba(37,99,235,0.04); }
        
        .daily-group { background: var(--bg-body); margin: 0; border-bottom: 2px solid var(--border-color); }
        .daily-head { padding: 12px 15px; font-size: 13px; font-weight: 800; color: var(--text-main); border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.3s ease;}
        .daily-head:hover { background: rgba(37,99,235,0.06); }
        
        .table-responsive { width: 100%; overflow-x: hidden; background: var(--bg-card);}
        table { width: 100%; border-collapse: collapse; font-size: 12px; table-layout: fixed; min-width: 100%;}
        
        th { background: var(--bg-body); color: var(--text-muted); padding: 10px 8px; font-weight: 800; text-transform: uppercase; font-size: 10px; border-bottom: 1px solid var(--border-color); text-align: left; word-wrap: break-word;}
        td { padding: 12px 8px; border-bottom: 1px solid var(--border-color); vertical-align: middle; color: var(--text-main); font-weight: 500; word-wrap: break-word;}
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .row-in { background-color: var(--green-bg) !important; }
        .row-out { background-color: var(--red-bg) !important; }
        
        .badge { font-size: 10px; padding: 4px 6px; border-radius: 4px; font-weight: 700; display: inline-block; white-space:nowrap;}
        .badge-user { background: rgba(37, 99, 235, 0.1); color: var(--primary); margin-top: 4px; font-size: 9px;}
        
        .img-t { width: 35px; height: 35px; border-radius: 6px; object-fit: cover; border: 1px solid var(--border-color); cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; display:block; margin: 0 auto 5px auto;}
        .img-t:hover { transform: scale(1.5); box-shadow: 0 4px 10px rgba(0,0,0,0.2); border-color: var(--primary); position: relative; z-index: 10;}

        .stack-up { font-size:13px; font-weight:800; color:var(--success); line-height:1.2;}
        .stack-down { font-size:13px; font-weight:800; color:var(--danger); line-height:1.2;}
        .stack-line { border-top:1px dashed var(--border-color); margin:5px 0; }

        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .sidebar-close { display: block; }
            .main-wrapper { margin-left: 0; width: 100%; }
            .menu-toggle { display: block; }
            .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; backdrop-filter: blur(3px);}
            .overlay.active { display: block; }
        }
        
        @media (max-width: 768px) {
            .content-container { padding: 5px; } 
            .menu-3d-container { margin: 10px 5px; padding: 15px 10px;}
            .btn-3d { width: 60px; height: 60px; font-size: 9px;}
            .btn-3d i.main-icon { font-size: 18px;}
            
            .grid-panel { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .metric-card { padding: 12px; border-top-width: 3px;}
            .c-value { font-size: 16px; }
            
            .month-folder { border-radius: 8px; margin-bottom: 15px;}
            td { font-size: 11px; padding: 10px 5px;}
            th { padding: 8px 5px; font-size: 9px;}
            .img-t { width: 30px; height: 30px;}
        }
    </style> 
</head> 
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode' : ''; ?>">  

<div class="overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="sidebar">
    <i class="fas fa-times sidebar-close" onclick="toggleSidebar()"></i>
    <div class="sidebar-header">
        <h2 class="brand-logo"><i class="fas fa-cube"></i> Sada Kalo</h2>
        <div class="brand-subtitle">Stock System</div>
    </div>
    <div class="sidebar-menu">
        <a href="../dashboard.php" class="sidebar-link sb-home"><i class="fas fa-home"></i> <span>Main Dashboard</span></a>
        <a href="index.php" class="sidebar-link sb-analytics active"><i class="fas fa-chart-pie"></i> <span>Stock Analytics</span></a>
        <a href="../suppliers.php" class="sidebar-link sb-supplier"><i class="fas fa-truck"></i> <span>Supplier Panel</span></a>
        <a href="../customers.php" class="sidebar-link sb-customer"><i class="fas fa-users"></i> <span>Customer Panel</span></a>
        <a href="../shop_@invantory/inventory_dashboard.php" class="sidebar-link sb-invantory"><i class="fas fa-invantory"></i> <span>Inventory & Logs</span></a>
        
        <?php if($role == 'admin'): ?>
        <div style="background: var(--bg-body); border-radius: 12px; padding: 15px; margin-top: 15px; border: 1px solid var(--border-color); box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
            <div style="font-size: 11px; color: var(--primary); text-transform: uppercase; margin-bottom: 10px; font-weight: 800; display:flex; align-items:center; gap:5px;"><i class="fas fa-filter"></i> Smart Filter (Admin)</div>
            <form method="GET" style="display: flex; flex-direction: column; gap: 8px;">         
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($f_date); ?>" style="padding:10px; font-size: 12px; margin:0; border-radius:30px;">         
                <div style="text-align: center; font-size: 10px; font-weight: 700; color: var(--text-muted);">TO</div>
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($t_date); ?>" style="padding:10px; font-size: 12px; margin:0; border-radius:30px;">         
                <button type="submit" class="btn-corp btn-primary" style="padding: 10px; margin-top: 5px; width:100%; border-radius:30px;"><i class="fas fa-search"></i> Apply Filter</button>
            </form>
        </div>

        <div style="border-top: 1px dashed var(--border-color); margin: 20px 0 10px 0; padding-top: 15px;">
            <div style="font-size: 10px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 12px; padding-left: 5px; font-weight: 800; letter-spacing:1px;">Security Locks</div>
            
            <?php 
            $lock_options = [
                'box' => ['icon' => 'fa-th-large', 'name' => 'Box Section'],
                'folder' => ['icon' => 'fa-folder-open', 'name' => 'Monthly Folder'],
                '7day' => ['icon' => 'fa-calendar-alt', 'name' => '7-Day View'],
                'entry' => ['icon' => 'fa-plus-circle', 'name' => 'Entry Form']
            ];
            
            foreach($lock_options as $l_key => $l_data):
                $is_section_locked = $sys_locks[$l_key] ?? 0;
            ?>
            <form method="POST" style="margin-bottom:8px;">
                <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="toggle_specific_lock" value="<?php echo htmlspecialchars($l_key); ?>">
                <input type="hidden" name="current_state" value="<?php echo (int)$is_section_locked; ?>">
                <button type="submit" class="lock-btn-3d">
                    <span style="display:flex; align-items:center;"><i class="fas <?php echo htmlspecialchars($l_data['icon']); ?>" style="color:var(--primary); margin-right:8px; width:16px;"></i> <?php echo htmlspecialchars($l_data['name']); ?></span>
                    <i class="fas <?php echo $is_section_locked ? 'fa-lock' : 'fa-unlock'; ?>" style="color: <?php echo $is_section_locked ? 'var(--danger)' : 'var(--success)'; ?>; font-size:14px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);"></i>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn-corp btn-outline" style="width: 100%; border-color: var(--danger); color: var(--danger); border-radius:30px;"><i class="fas fa-power-off"></i> Logout System</a>
    </div>
</aside>

<div class="main-wrapper">
    
    <header class="top-navbar">
        <div class="nav-left">
            <button class="menu-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h2 class="page-title">Sada Kalo-স্টকের হিসাব-2026</h2>
        </div>
        <div class="nav-right">
            <div class="user-badge">
                <i class="fas fa-shield-alt" style="color: var(--primary); font-size: 14px;"></i>
                <?php echo htmlspecialchars($username); ?>
            </div>
        </div>
    </header>

    <img src="../banner.jpg" style="width:100%; max-height:120px; object-fit:cover; border-bottom: 2px solid var(--primary); box-shadow: 0 4px 6px rgba(0,0,0,0.1);" onerror="this.style.display='none'">

    <div class="menu-3d-container">
        <button class="btn-3d b-box" onclick="handleMenuClick('section-box', 'box')">
            <i class="fas fa-boxes main-icon"></i> বক্স
        </button>
        <button class="btn-3d b-folder" onclick="handleMenuClick('section-folder', 'folder')">
            <i class="fas fa-folder main-icon"></i> ফোল্ডার
        </button>
        <button class="btn-3d b-7day" onclick="handleMenuClick('section-7day', '7day')">
            <i class="fas fa-calendar-alt main-icon"></i> ৭ দিন
        </button>
        <button class="btn-3d b-entry" onclick="openEntryForm()">
            <i class="fas fa-plus-circle main-icon"></i> এন্ট্রি ফর্ম
        </button>
        <button class="btn-3d b-theme" onclick="toggleTheme()">
            <i class="fas fa-adjust main-icon"></i> থিম
        </button>
    </div>

    <div class="content-container">

        <div id="section-placeholder" class="placeholder-box">
            <i class="fas fa-cube" style="opacity:0.3;"></i>
            <h2 style="color:var(--text-main); opacity:0.4;">Sada Kalo Fashion</h2>
            <p>Select an option from the menu above</p>
        </div>

        <div id="section-box" class="dynamic-section">
            <div class="grid-panel">     
                <div class="metric-card">
                    <i class="fas fa-layer-group metric-icon"></i>
                    <div class="c-title">Current Stock</div>
                    <div class="c-value"><?php echo number_format((float)$metrics['cur_qty']); ?> Pcs</div>
                </div>
                <?php if($role == 'admin'): ?>
                <div class="metric-card">
                    <i class="fas fa-box-open metric-icon"></i>
                    <div class="c-title">Stock Value</div>
                    <div class="c-value">৳ <?php echo number_format((float)$metrics['stock_value']); ?></div>
                </div>
                <div class="metric-card border-success">
                    <i class="fas fa-money-bill-wave metric-icon"></i>
                    <div class="c-title">Total Sales</div>
                    <div class="c-value">৳ <?php echo number_format((float)$metrics['total_sell_val']); ?></div>
                </div>
                <div class="metric-card border-success">
                    <i class="fas fa-chart-line metric-icon"></i>
                    <div class="c-title">Monthly Profit</div>
                    <div class="c-value">৳ <?php echo number_format((float)$metrics['month_net_profit']); ?></div>
                </div>
                <div class="metric-card border-success">
                    <i class="fas fa-hand-holding-usd metric-icon"></i>
                    <div class="c-title">Today's Profit</div>
                    <div class="c-value">৳ <?php echo number_format((float)$metrics['today_net_profit']); ?></div>
                </div>
                <div class="metric-card border-warning">
                    <i class="fas fa-tags metric-icon"></i>
                    <div class="c-title">Average Buy Rate</div>
                    <div class="c-value">৳ <?php echo number_format((float)$avg_buy_price, 2); ?></div>
                </div>
                <div class="metric-card border-info">
                    <i class="fas fa-shopping-cart metric-icon"></i>
                    <div class="c-title">Total Brought</div>
                    <div class="c-value"><?php echo number_format((float)$metrics['total_buy_qty']); ?> Pcs</div>
                </div>
                <?php endif; ?>

                <div class="metric-card border-danger">
                    <i class="fas fa-share-square metric-icon"></i>
                    <div class="c-title">Today Sold</div>
                    <div class="c-value"><?php echo number_format((float)$metrics['today_sell_qty']); ?> Pcs</div>
                </div>
                <div class="metric-card border-primary">
                    <i class="fas fa-plus-circle metric-icon"></i>
                    <div class="c-title">Today Added</div>
                    <div class="c-value"><?php echo number_format((float)$metrics['today_add_qty']); ?> Pcs</div>
                </div>
            </div>
        </div>

        <div id="section-folder" class="dynamic-section">
            <?php if(empty($monthly_grouped)): ?>
                <div style="text-align:center; padding:20px; color:var(--text-muted);">কোনো ডাটা পাওয়া যায়নি।</div>
            <?php endif; ?>

            <?php foreach($monthly_grouped as $month_name => $entries): 
                $mq_in = 0; $mq_out = 0; $mb_in = 0; $mb_out = 0; $m_profit = 0;
                foreach($entries as $e) {
                    $is_in_calc = ($e['type'] == 'IN');
                    $calc_cost = $is_in_calc ? 0 : ((float)$e['q'] * (float)$avg_buy_price);
                    $calc_profit = $is_in_calc ? 0 : ((float)$e['b'] - $calc_cost);

                    if($is_in_calc) { $mq_in += (int)$e['q']; $mb_in += (float)$e['b']; } 
                    else { $mq_out += (int)$e['q']; $mb_out += (float)$e['b']; $m_profit += $calc_profit; }
                }
            ?>
            <div class="month-folder">
                <div class="month-header" onclick="toggleAccordion(this)">
                    <span><i class="fas fa-folder" style="margin-right:8px; color:var(--warning);"></i> <?php echo htmlspecialchars((string)$month_name); ?></span>
                    <i class="fas fa-chevron-down toggle-icon" style="color:var(--text-muted); transition: transform 0.3s;"></i>
                </div>
                
                <div class="folder-content" style="display: none;">
                    <div class="table-responsive">
                        <table>                 
                            <thead>                     
                                <tr>                         
                                    <th style="width:<?php echo $role=='admin' ? '18%' : '25%'; ?>;">Time/User</th>
                                    <th style="width:<?php echo $role=='admin' ? '30%' : '50%'; ?>;">Details</th>
                                    <th class="text-center" style="width:<?php echo $role=='admin' ? '12%' : '25%'; ?>;">Pic & Qty</th> 
                                    
                                    <?php if($role == 'admin'): ?>                        
                                        <th class="text-right" style="width:25%;">Finance</th>                         
                                        <th class="text-center" style="width:15%;">Act</th>                         
                                    <?php endif; ?>
                                </tr>                 
                            </thead>                 
                            <tbody>                     
                                <?php foreach($entries as $r): 
                                    $is_in = ($r['type'] == 'IN');
                                    $row_class = $is_in ? 'row-in' : 'row-out';
                                    $calculated_cost = $is_in ? 0 : ((float)$r['q'] * (float)$avg_buy_price);
                                    $profit = $is_in ? 0 : ((float)$r['b'] - $calculated_cost);
                                ?>                     
                                <tr class="<?php echo $row_class; ?>">                         
                                    <td>
                                        <div style="font-size:10px; font-weight:800; color:var(--text-main); margin-bottom:4px;"><?php echo date('d M, y', strtotime((string)$r['dt'])); ?></div>
                                        <div style="font-size:9px; font-weight:700; color:var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime((string)$r['sort_time'])); ?></div>                         
                                        <div class="badge badge-user"><i class="fas fa-user-edit" style="font-size:8px; margin-right:3px;"></i> <?php echo ltrim(htmlspecialchars((string)($r['eb'] ?? 'User')), '@'); ?></div>
                                    </td>
                                    <td>                             
                                        <div style="font-weight:700; font-size:12px; color:var(--text-main); line-height:1.3;">
                                            <?php echo htmlspecialchars((string)($r['info'] ?? '')); ?>
                                        </div>                             
                                        <?php if(!empty($r['memo'])): ?>
                                            <div style="font-size:9px; font-weight:700; color:white; background:var(--primary); margin-top: 5px; display: inline-block; padding:2px 5px; border-radius:4px;">#<?php echo htmlspecialchars((string)$r['memo']); ?></div>
                                        <?php endif; ?>
                                    </td>                         
                                    <td class="text-center">                             
                                        <?php if(!empty($r['img']) && file_exists((string)$r['img'])): ?>                                 
                                            <img src="/<?php echo ltrim(htmlspecialchars((string)$r['img']), '/'); ?>" class="img-t" onclick="viewImage(this.src)">                             
                                        <?php else: ?>
                                            <div style="width:30px; height:30px; margin:0 auto 5px auto; background:var(--bg-body); border-radius:4px; display:flex; align-items:center; justify-content:center; border:1px dashed var(--border-color); color:var(--text-muted); opacity:0.5;"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                        <div style="font-weight:800; font-size:13px; color: <?php echo $is_in ? 'var(--success)' : 'var(--danger)'; ?>;">
                                            <?php echo ($is_in ? '+' : '-') . (int)$r['q']; ?> p
                                        </div>                         
                                    </td> 
                                    
                                    <?php if($role == 'admin'): ?>                        
                                        <td class="text-right">                             
                                            <?php if($is_in): ?>
                                                <div style="font-size:11px; font-weight:700; color:var(--text-main);">Bill: ৳<?php echo number_format((float)$r['b']); ?></div>
                                            <?php else: ?>
                                                <div style="font-size:11px; font-weight:700; color:var(--text-main);">Sale: ৳<?php echo number_format((float)$r['b']); ?></div>
                                                <div style="font-size:11px; font-weight:800; color:<?php echo $profit>=0 ? 'var(--success)' : 'var(--danger)'; ?>; margin-top:4px;">
                                                    <?php echo $profit>=0 ? '+' : ''; ?>৳<?php echo number_format((float)$profit); ?>
                                                </div>
                                            <?php endif; ?>                         
                                        </td>
                                        <td class="text-center">                             
                                            <?php if($r['tbl'] == 'stocks'): ?>                                 
                                                <button onclick="confirmDelete(<?php echo (int)$r['id']; ?>)" class="btn-corp btn-outline" style="padding: 4px 8px; color: var(--danger); border-color: rgba(239,68,68,0.2);"><i class="fas fa-trash-alt" style="font-size:11px;"></i></button>                             
                                            <?php else: ?>
                                                <span style="color:var(--primary); font-size:9px; font-weight:800; background:rgba(37,99,235,0.1); padding:4px 6px; border-radius:4px;">AUTO</span>
                                            <?php endif; ?>                         
                                        </td>                         
                                    <?php endif; ?>                     
                                </tr>                     
                                <?php endforeach; ?>                 
                            </tbody>
                            
                            <tfoot style="background: rgba(37,99,235,0.04); border-top: 2px solid var(--border-color);">
                                <tr>
                                    <td colspan="2" class="text-right" style="font-weight:800; font-size:12px; color:var(--text-main); padding:12px 10px; vertical-align:top; text-transform:uppercase;">Monthly Summary:</td>
                                    <td class="text-center" style="padding:10px 6px; vertical-align:top;">
                                        <div class="stack-up"><?php echo $mq_in; ?>p IN</div>
                                        <div class="stack-line"></div>
                                        <div class="stack-down"><?php echo $mq_out; ?>p OUT</div>
                                    </td>
                                    <?php if($role == 'admin'): ?>
                                    <td class="text-right" style="padding:10px 8px; vertical-align:top;">
                                        <div style="font-size:11px; font-weight:700; color:var(--text-main); line-height:1.4;">In: ৳<?php echo number_format((float)$mb_in); ?></div>
                                        <div style="font-size:11px; font-weight:700; color:var(--text-main); line-height:1.4;">Out: ৳<?php echo number_format((float)$mb_out); ?></div>
                                        <div class="stack-line"></div>
                                        <div style="font-size:12px; font-weight:800; color:<?php echo $m_profit >= 0 ? 'var(--success)' : 'var(--danger)'; ?>; line-height:1.4;">
                                            Prof: <?php echo $m_profit >= 0 ? '+' : ''; ?>৳<?php echo number_format((float)$m_profit); ?>
                                        </div>
                                    </td>
                                    <td></td>
                                    <?php endif; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>     
            <?php endforeach; ?> 
        </div>

        <div id="section-7day" class="dynamic-section">
            <div style="margin-bottom:15px; font-size:12px; font-weight:700; color:var(--primary); text-align:center; text-transform:uppercase; letter-spacing:1px;"><i class="fas fa-calendar-week"></i> চলতি সপ্তাহের হিসাব (শনি - শুক্র)</div>
            
            <?php if(empty($weekly_grouped)): ?>
                <div style="text-align:center; padding:20px; color:var(--text-muted);">এই সপ্তাহে কোনো হিসাব নেই।</div>
            <?php endif; ?>

            <?php foreach($weekly_grouped as $date => $entries): 
                $dq_in = 0; $dq_out = 0; $db_in = 0; $db_out = 0; $d_profit = 0;
                foreach($entries as $e) {
                    $is_in_calc = ($e['type'] == 'IN');
                    $calc_cost = $is_in_calc ? 0 : ((float)$e['q'] * (float)$avg_buy_price);
                    $calc_profit = $is_in_calc ? 0 : ((float)$e['b'] - $calc_cost);

                    if($is_in_calc) { $dq_in += (int)$e['q']; $db_in += (float)$e['b']; } 
                    else { $dq_out += (int)$e['q']; $db_out += (float)$e['b']; $d_profit += $calc_profit; }
                }
            ?>     
            <div class="month-folder">         
                <div class="month-header" onclick="toggleAccordion(this)">
                    <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                        <span style="font-size: 14px; font-weight: 800;">
                            <i class="fas fa-calendar-day" style="color:var(--primary); margin-right:6px;"></i>
                            <?php echo date('d M (l)', strtotime((string)$date)); ?>
                        </span>
                        <div style="display:flex; gap:15px; align-items:center;">
                            <span style="font-size:11px; font-weight:700; background:var(--bg-body); padding:4px 8px; border-radius:6px;">
                                <strong style="color:var(--success);"><?php echo $dq_in; ?> In</strong> | <strong style="color:var(--danger);"><?php echo $dq_out; ?> Out</strong>
                            </span>
                            <i class="fas fa-chevron-down toggle-icon" style="color:var(--text-muted); transition: transform 0.3s;"></i>
                        </div>
                    </div>
                </div>                  
                
                <div class="folder-content" style="display: none;">
                    <div class="table-responsive">
                        <table>                 
                            <thead>                     
                                <tr>                         
                                    <th style="width:<?php echo $role=='admin' ? '18%' : '25%'; ?>;">Time/User</th>
                                    <th style="width:<?php echo $role=='admin' ? '30%' : '50%'; ?>;">Details</th>
                                    <th class="text-center" style="width:<?php echo $role=='admin' ? '12%' : '25%'; ?>;">Pic & Qty</th> 
                                    <?php if($role == 'admin'): ?>                        
                                        <th class="text-right" style="width:25%;">Finance</th>                         
                                        <th class="text-center" style="width:15%;">Act</th>                         
                                    <?php endif; ?>
                                </tr>                 
                            </thead>                 
                            <tbody>                     
                                <?php foreach($entries as $r): 
                                    $is_in = ($r['type'] == 'IN');
                                    $row_class = $is_in ? 'row-in' : 'row-out';
                                    $calculated_cost = $is_in ? 0 : ((float)$r['q'] * (float)$avg_buy_price);
                                    $profit = $is_in ? 0 : ((float)$r['b'] - $calculated_cost);
                                ?>                     
                                <tr class="<?php echo $row_class; ?>">                         
                                    <td>
                                        <div style="font-size:10px; font-weight:700; color:var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime((string)$r['sort_time'])); ?></div>                         
                                        <div class="badge badge-user"><i class="fas fa-user-edit" style="font-size:8px; margin-right:3px;"></i> <?php echo ltrim(htmlspecialchars((string)($r['eb'] ?? 'User')), '@'); ?></div>
                                    </td>
                                    <td>                             
                                        <div style="font-weight:700; font-size:12px; color:var(--text-main); line-height:1.2;">
                                            <?php echo htmlspecialchars((string)($r['info'] ?? '')); ?>
                                        </div>                             
                                        <?php if(!empty($r['memo'])): ?>
                                            <div style="font-size:9px; font-weight:700; color:white; background:var(--primary); margin-top: 4px; display: inline-block; padding:2px 5px; border-radius:4px;">#<?php echo htmlspecialchars((string)$r['memo']); ?></div>
                                        <?php endif; ?>
                                    </td>                         
                                    <td class="text-center">                             
                                        <?php if(!empty($r['img']) && file_exists((string)$r['img'])): ?>                                 
                                            <img src="/<?php echo ltrim(htmlspecialchars((string)$r['img']), '/'); ?>" class="img-t" onclick="viewImage(this.src)">                             
                                        <?php else: ?>
                                            <div style="width:30px; height:30px; margin:0 auto 5px auto; background:var(--bg-body); border-radius:4px; display:flex; align-items:center; justify-content:center; border:1px dashed var(--border-color); color:var(--text-muted); opacity:0.5;"><i class="fas fa-image"></i></div>
                                        <?php endif; ?>
                                        <div style="font-weight:800; font-size:13px; color: <?php echo $is_in ? 'var(--success)' : 'var(--danger)'; ?>;">
                                            <?php echo ($is_in ? '+' : '-') . (int)$r['q']; ?> p
                                        </div>                         
                                    </td> 
                                    
                                    <?php if($role == 'admin'): ?>                        
                                        <td class="text-right">                             
                                            <?php if($is_in): ?>
                                                <div style="font-size:11px; font-weight:700; color:var(--text-main);">Bill: ৳<?php echo number_format((float)$r['b']); ?></div>
                                            <?php else: ?>
                                                <div style="font-size:11px; font-weight:700; color:var(--text-main);">Sale: ৳<?php echo number_format((float)$r['b']); ?></div>
                                                <div style="font-size:11px; font-weight:800; color:<?php echo $profit>=0 ? 'var(--success)' : 'var(--danger)'; ?>; margin-top:2px;">
                                                    <?php echo $profit>=0 ? '+' : ''; ?>৳<?php echo number_format((float)$profit); ?>
                                                </div>
                                            <?php endif; ?>                         
                                        </td>
                                        <td class="text-center">                             
                                            <?php if($r['tbl'] == 'stocks'): ?>                                 
                                                <button onclick="confirmDelete(<?php echo (int)$r['id']; ?>)" class="btn-corp btn-outline" style="padding: 2px 6px; color: var(--danger); border-color: rgba(239,68,68,0.2);"><i class="fas fa-trash-alt" style="font-size:10px;"></i></button>                             
                                            <?php else: ?>
                                                <span style="color:var(--primary); font-size:8px; font-weight:800; background:rgba(37,99,235,0.1); padding:2px 4px; border-radius:4px;">AUTO</span>
                                            <?php endif; ?>                         
                                        </td>                         
                                    <?php endif; ?>                     
                                </tr>                     
                                <?php endforeach; ?>                 
                            </tbody>
                            
                            <tfoot style="background: rgba(37,99,235,0.04); border-top: 2px solid var(--border-color);">
                                <tr>
                                    <td colspan="2" class="text-right" style="font-weight:800; font-size:11px; color:var(--text-main); padding:10px 8px; vertical-align:top; text-transform:uppercase;">Daily Subtotal:</td>
                                    <td class="text-center" style="padding:8px 4px; vertical-align:top;">
                                        <div class="stack-up"><?php echo $dq_in; ?>p IN</div>
                                        <div class="stack-line"></div>
                                        <div class="stack-down"><?php echo $dq_out; ?>p OUT</div>
                                    </td>
                                    <?php if($role == 'admin'): ?>
                                    <td class="text-right" style="padding:8px 4px; vertical-align:top;">
                                        <div style="font-size:10px; font-weight:700; color:var(--text-main); line-height:1.3;">In: ৳<?php echo number_format((float)$db_in); ?></div>
                                        <div style="font-size:10px; font-weight:700; color:var(--text-main); line-height:1.3;">Out: ৳<?php echo number_format((float)$db_out); ?></div>
                                        <div class="stack-line"></div>
                                        <div style="font-size:11px; font-weight:800; color:<?php echo $d_profit >= 0 ? 'var(--success)' : 'var(--danger)'; ?>; line-height:1.3;">
                                            Prof: <?php echo $d_profit >= 0 ? '+' : ''; ?>৳<?php echo number_format((float)$d_profit); ?>
                                        </div>
                                    </td>
                                    <td></td>
                                    <?php endif; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>     
            <?php endforeach; ?> 
        </div>

    </div>
</div>

<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="delete_id" id="delete_id">
    <input type="hidden" name="admin_pass" id="admin_pass">
</form>

<div id="entryModalOverlay" class="modal-overlay" onclick="checkCloseModal(event)">
    <div class="modal-content">
        <div class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></div>
        
        <h3 style="margin:0 0 20px 0; color:var(--text-main); font-family:var(--font-heading); font-size:18px; border-bottom:2px solid var(--bg-body); padding-bottom:12px; display:flex; align-items:center; gap:8px;">
            <div style="background:var(--primary); color:white; width:30px; height:30px; display:flex; align-items:center; justify-content:center; border-radius:8px;"><i class="fas fa-box-open" style="font-size:14px;"></i></div> New Stock Entry
        </h3>
        
        <form method="POST" id="stockForm" autocomplete="off" onsubmit="return disableSubmitButton()">                          
            <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($csrf_token); ?>">             
            <input type="hidden" name="save_stock" value="1">                          
            
            <div class="form-group">
                <label>Details</label>
                <input type="text" name="item_name" placeholder="DOF CANDY Denim pants" required>             
            </div>
            
            <div style="display:flex; gap:12px; flex-wrap:wrap;">                 
                <div class="form-group" style="flex:1; min-width:140px;">
                    <label>Total-Pcs *</label>
                    <input type="number" name="qty" placeholder="0" required>                 
                </div>
                <div class="form-group" style="flex:1; min-width:140px;">
                    <label>Total-Bill</label>
                    <input type="number" name="total_bill" placeholder="0.00" required>             
                </div>
            </div>                          
            
            <div class="cam-frame">                 
                <video id="v" autoplay playsinline style="display:none; width:100%; max-width:160px; margin:auto; border-radius:8px; border:2px solid var(--primary); box-shadow:0 4px 10px rgba(0,0,0,0.1);"></video>                 
                <img id="p" style="display:none; width:100%; max-width:160px; margin:auto; border-radius:8px; border:3px solid var(--success); box-shadow:0 4px 10px rgba(0,0,0,0.1);">                 
                <input type="hidden" name="webcam_image" id="wi">                 
                <div style="margin-top:12px; display:flex; justify-content:center; gap:10px;">                     
                    <button type="button" id="sc" class="btn-corp btn-outline" style="border-color:var(--primary); color:var(--primary);"><i class="fas fa-camera"></i> Open Camera</button>                     
                    <button type="button" id="ca" class="btn-corp btn-success" style="display:none; box-shadow:0 4px 10px rgba(16,185,129,0.3);"><i class="fas fa-check-circle"></i> Capture Photo</button>                     
                    <button type="button" id="rt" class="btn-corp btn-outline" style="display:none;"><i class="fas fa-redo-alt"></i> Retake</button>                 
                </div>             
            </div>                          
            
            <button type="submit" id="saveButtonId" class="btn-corp btn-primary btn-block" style="margin-bottom:0; padding:14px; font-size:15px; text-transform:uppercase; letter-spacing:1px; border-radius:30px; box-shadow:0 4px 10px rgba(37,99,235,0.3);"><i class="fas fa-cloud-upload-alt"></i> Save to Database</button>         
        </form>
    </div>
</div>

<div id="fullImageModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.9); justify-content: center; align-items: center; flex-direction: column; backdrop-filter:blur(5px);">     
    <span onclick="closeImage()" style="position: absolute; top: 20px; right: 30px; color: white; font-size: 45px; cursor: pointer; transition:transform 0.2s;">&times;</span>     
    <img id="fullSizeImg" src="" style="max-width: 90%; max-height: 85%; border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); border: 4px solid white;"> 
</div>  

<script>
    const sysLocks = <?php echo json_encode([
        'box' => (int)($sys_locks['box'] ?? 0),
        'folder' => (int)($sys_locks['folder'] ?? 0),
        '7day' => (int)($sys_locks['7day'] ?? 0),
        'entry' => (int)($sys_locks['entry'] ?? 0)
    ]); ?>;
    const userRole = <?php echo json_encode($role ?? 'viewer'); ?>;

    function toggleSidebar() {
        let sb = document.getElementById('sidebar');
        let overlay = document.getElementById('sidebarOverlay');
        if (sb) sb.classList.toggle('active');
        if (overlay) overlay.classList.toggle('active');
    }

    function handleMenuClick(sectionId, lockKey) {
        try {
            if (sysLocks && sysLocks[lockKey] === 1 && userRole !== 'admin') {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error', title: 'Admin Locked!', text: ' (Admin Lock Active)', 
                        confirmButtonColor: '#ef4444', 
                        background: document.body.classList.contains('dark-mode') ? '#1e293b' : '#fff', 
                        color: document.body.classList.contains('dark-mode') ? '#fff' : '#000'
                    });
                } else {
                    alert('Admin Locked!');
                }
                return;
            }
            
            let sections = ['section-placeholder', 'section-box', 'section-folder', 'section-7day'];
            sections.forEach(id => {
                let el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });

            var target = document.getElementById(sectionId);
            if (target) target.style.display = 'block';
            
        } catch (e) {
            console.error("Button Click Error: ", e);
        }
    }

    function openEntryForm() {
        try {
            if(sysLocks && sysLocks['entry'] === 1 && userRole !== 'admin') {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error', title: 'Admin Locked!', text: ' (Admin Lock Active)', 
                        confirmButtonColor: '#ef4444', 
                        background: document.body.classList.contains('dark-mode') ? '#1e293b' : '#fff', 
                        color: document.body.classList.contains('dark-mode') ? '#fff' : '#000'
                    });
                } else {
                    alert('Admin Locked!');
                }
                return;
            }
            const modalOverlay = document.getElementById("entryModalOverlay");
            if (modalOverlay) modalOverlay.classList.add('active');
        } catch(e) {
            console.error("Modal Open Error: ", e);
        }
    }
    
    function closeModal() {
        const modalOverlay = document.getElementById("entryModalOverlay");
        if (modalOverlay) modalOverlay.classList.remove('active');
        if(sR) { sR.getTracks().forEach(t=>t.stop()); }
        let v = document.getElementById('v'), p = document.getElementById('p'), sc = document.getElementById('sc'), ca = document.getElementById('ca'), rt = document.getElementById('rt');
        if (v) v.style.display='none';
        if (p) p.style.display='none';
        if (sc) {
            sc.style.display='inline-flex';
            if (ca) ca.style.display='none';
            if (rt) rt.style.display='none';
        }
    }
    
    function checkCloseModal(e) { 
        const modalOverlay = document.getElementById("entryModalOverlay");
        if (e.target === modalOverlay) { closeModal(); } 
    }
    
    function toggleAccordion(element) {
        var content = element.nextElementSibling;
        var chevron = element.querySelector('.toggle-icon');
        if (content && chevron) {
            if (content.style.display === "none" || content.style.display === "") {
                content.style.display = "block";
                chevron.style.transform = "rotate(180deg)";
            } else {
                content.style.display = "none";
                chevron.style.transform = "rotate(0deg)";
            }
        }
    }

    function toggleTheme() {         
        document.body.classList.toggle('dark-mode');         
        const isDark = document.body.classList.contains('dark-mode');         
        document.cookie = "theme=" + (isDark ? 'dark' : 'light') + ";path=/";                  
    }      
    
    function viewImage(src) {         
        let modal = document.getElementById('fullImageModal');
        let img = document.getElementById('fullSizeImg');
        if (modal && img) {
            modal.style.display = 'flex';         
            img.src = src;     
        }
    }     
    function closeImage() { 
        let modal = document.getElementById('fullImageModal');
        if (modal) modal.style.display = 'none'; 
    }      
    
    function confirmDelete(id) {         
        if (typeof Swal !== 'undefined') {
            Swal.fire({             
                title: 'Delete Confirmation',             
                text: "Please enter your Action Password:",             
                input: 'password',
                icon: 'warning',             
                showCancelButton: true,             
                confirmButtonText: 'Delete Record',             
                cancelButtonText: 'Cancel',             
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                background: document.body.classList.contains('dark-mode') ? '#1e293b' : '#ffffff',
                color: document.body.classList.contains('dark-mode') ? '#f8fafc' : '#1e293b',
                preConfirm: (pass) => {                 
                    if (!pass) { Swal.showValidationMessage('Action Password is required!'); return false; }                 
                    return pass;
                }         
            }).then((result) => {             
                if (result.isConfirmed) { 
                    document.getElementById('delete_id').value = id;
                    document.getElementById('admin_pass').value = result.value;
                    document.getElementById('deleteForm').submit();
                }         
            });
        } else {
            let pass = prompt("Please enter your Action Password:");
            if (pass) {
                document.getElementById('delete_id').value = id;
                document.getElementById('admin_pass').value = pass;
                document.getElementById('deleteForm').submit();
            }
        }
    }      
    
    const v=document.getElementById('v'), p=document.getElementById('p'), sc=document.getElementById('sc'), ca=document.getElementById('ca'), rt=document.getElementById('rt'), wi=document.getElementById('wi');     
    let sR=null;          
    
    if(sc) {
        sc.onclick=function(){         
            navigator.mediaDevices.getUserMedia({video:{facingMode:"environment"}}).then(s=>{             
                sR=s; if(v) v.srcObject=s; if(v) v.style.display='block'; if(p) p.style.display='none'; sc.style.display='none'; if(ca) ca.style.display='inline-flex';         
            }).catch(()=>alert("Camera permission denied. Please allow access.")); 
        };          
        
        if (ca) ca.onclick=function(){         
            const c=document.createElement('canvas'); c.width=v.videoWidth; c.height=v.videoHeight;         
            c.getContext('2d').drawImage(v,0,0);         
            const d=c.toDataURL('image/jpeg',0.8);         
            if(wi) wi.value=d; if(p) p.src=d; if(p) p.style.display='block'; if(v) v.style.display='none'; ca.style.display='none'; if(rt) rt.style.display='inline-flex';         
            if(sR) sR.getTracks().forEach(t=>t.stop());     
        };          
        
        if (rt) rt.onclick=function(){ rt.style.display='none'; sc.click(); };   
    }
    
    function disableSubmitButton() {         
        var btn = document.getElementById("saveButtonId");         
        if (btn) {
            btn.disabled = true;         
            btn.innerHTML = "<i class='fas fa-circle-notch fa-spin'></i> Processing Data...";          
        }
        return true;      
    } 
</script>  

<?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?> 
<script>     
    document.addEventListener("DOMContentLoaded", function() {                  
        if (typeof Swal !== 'undefined') {
            Swal.fire({             
                title: 'Successfully Saved!',             
                icon: 'success',             
                toast: true,             
                position: 'top-end',             
                showConfirmButton: false,             
                timer: 3000,             
                background: '#10b981',
                color: '#fff'
            });          
        }
        
        if (typeof confetti === "function") {             
            confetti({ particleCount: 120, spread: 80, origin: { y: 0.6 }, zIndex: 9999, colors: ['#2563eb', '#10b981', '#f59e0b'] });         
        }          
        
        setTimeout(() => { window.history.replaceState(null, null, window.location.pathname); }, 1000);      
    }); 
</script> 
<?php elseif(isset($_GET['status']) && $_GET['status'] == 'deleted'): ?>
<script>
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Record Deleted Successfully', 
            icon: 'success',
            background: '#ef4444', 
            color: '#fff',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
    }
    window.history.replaceState(null, null, window.location.pathname );
</script>
<?php elseif(isset($_GET['status']) && $_GET['status'] == 'lock_updated'): ?>
<script>
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Lock Updated Successfully', 
            icon: 'info',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            background: '#2563eb',
            color: '#fff'
        });
    }
        window.history.replaceState(null, null, window.location.pathname);
</script>
<?php endif; ?> 
</body> 
</html>