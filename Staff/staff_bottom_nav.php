<?php
/**
 * staff_bottom_nav.php — SADA KALO · শেয়ার্ড বটম নেভিগেশন বার (Modules স্ট্রাকচার)
 *
 * ব্যবহার: প্রতি পেজে </body> এর আগে include করুন।
 *   root পেজ (index.php):        $nav_base='';      then include 'staff_bottom_nav.php';
 *   Modules/X/*.php পেজ:          $nav_base='../../'; then include __DIR__.'/../../staff_bottom_nav.php';
 *
 * $nav_base = রুট পর্যন্ত ফেরার relative পাথ ('' = root, '../../' = Modules/X/)।
 * body এ padding-bottom:84px রাখুন।
 */
$__base = isset($nav_base) ? $nav_base : '';
$__cur  = basename($_SERVER['PHP_SELF']);
$__items = [
    ['file'=>'index.php',                    'icon'=>'fa-house',              'label'=>'হোম'],
    ['file'=>'Modules/Staff/List.php',       'icon'=>'fa-users',              'label'=>'স্টাফ'],
    ['file'=>'Modules/Attendance/Daily.php', 'icon'=>'fa-fingerprint',        'label'=>'হাজিরা', 'center'=>true],
    ['file'=>'Modules/Expense/Create.php',   'icon'=>'fa-hand-holding-dollar','label'=>'খরচ'],
    ['file'=>'Modules/Payroll/Payslip.php',  'icon'=>'fa-money-check-dollar', 'label'=>'পেস্লিপ'],
];
?>
<style>
  .sk-bnav{position:fixed;bottom:0;left:0;right:0;z-index:1000;display:flex;align-items:flex-end;justify-content:space-around;
    background:rgba(10,9,24,0.97);border-top:1px solid rgba(255,255,255,0.07);backdrop-filter:blur(16px);
    padding:9px 6px 11px;max-width:520px;margin:0 auto;}
  .sk-bnav a{flex:1;text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:5px;padding:3px 0;color:#5A5A80;}
  .sk-bnav a i{font-size:19px;line-height:1;}
  .sk-bnav a span{font-size:9.5px;font-weight:800;color:#7A7AA0;}
  .sk-bnav a.on i,.sk-bnav a.on span{color:#A07AF0;}
  .sk-bnav a.center i{width:52px;height:52px;border-radius:16px;background:linear-gradient(135deg,#7C5CFC,#5A3DD4);color:#fff;
    display:flex;align-items:center;justify-content:center;box-shadow:0 8px 20px rgba(124,92,252,0.5);border:3px solid #0A0918;margin-top:-16px;}
  .sk-bnav a.center.on i{color:#fff;}
  [data-bs-theme="light"] .sk-bnav{background:rgba(255,255,255,0.97);border-top-color:rgba(0,0,0,0.08);}
  [data-bs-theme="light"] .sk-bnav a{color:#9898b0;}
  [data-bs-theme="light"] .sk-bnav a span{color:#9898b0;}
  [data-bs-theme="light"] .sk-bnav a.on i,[data-bs-theme="light"] .sk-bnav a.on span{color:#7C5CFC;}
  [data-bs-theme="light"] .sk-bnav a.center i{border-color:#fff;}
</style>
<nav class="sk-bnav">
<?php foreach($__items as $it):
    $on = ($__cur === basename($it['file'])) ? ' on' : '';
    $center = !empty($it['center']) ? ' center' : '';
?>
    <a href="<?php echo $__base . $it['file']; ?>" class="<?php echo trim($center.$on); ?>">
        <i class="fa <?php echo $it['icon']; ?>"></i>
        <span><?php echo $it['label']; ?></span>
    </a>
<?php endforeach; ?>
</nav>
