<?php
/**
 * cash_bottom_nav.php — সাদাকালো ফ্যাশন · ক্যাশ বিক্রি পেজ · বটম নেভিগেশন বার
 *
 * ব্যবহার: cash_sale.php (বা অন্য পেজ) এর </body> এর ঠিক আগে —
 *   <?php include 'cash_bottom_nav.php'; ?>
 *
 * cash_sale.php-এর থিম টোকেন (--p, --card/--nav-bg, --border, --tx2/3),
 * Font Awesome আইকন ব্যবহার করে — light/dark দুটোতেই চলে।
 * সব বাটন গার্ডেড: ফাংশন না থাকলে সঠিক পেজে নিয়ে যায়।
 */
$__cur = basename($_SERVER['PHP_SELF']);
?>
<style>
  .cb-nav{position:fixed;bottom:0;left:0;right:0;z-index:930;display:flex;align-items:flex-end;justify-content:space-around;
    background:var(--nav-bg,#fff);border-top:1px solid var(--border,#CBD5E1);
    padding:8px 6px calc(9px + env(safe-area-inset-bottom,0px));box-shadow:0 -6px 22px -10px rgba(15,23,42,.35);}
  .cb-nav__item{flex:1;border:none;background:none;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:4px;
    padding:4px 0;text-decoration:none;color:var(--tx3,#94A3B8);font-family:inherit;}
  .cb-nav__item i{font-size:18px;line-height:1;}
  .cb-nav__item span{font-size:10px;font-weight:700;letter-spacing:.2px;}
  .cb-nav__item.on{color:var(--p,#2563EB);}
  .cb-nav__item.center i{width:52px;height:52px;border-radius:16px;background:linear-gradient(135deg,var(--p,#2563EB),var(--p-d,#1D4ED8));
    color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;
    box-shadow:var(--sh-p,0 4px 14px rgba(37,99,235,.28));border:3px solid var(--nav-bg,#fff);margin-top:-16px;}
  .cb-nav__item.center span{color:var(--p,#2563EB);}
  .cb-nav__item:active{transform:scale(.92);transition:transform .1s;}
</style>

<nav class="cb-nav">
  <a href="dashboard.php" class="cb-nav__item<?php echo $__cur==='dashboard.php' ? ' on' : ''; ?>">
    <i class="fas fa-home"></i><span>হোম</span>
  </a>

  <a href="cash_sale.php" class="cb-nav__item<?php echo $__cur==='cash_sale.php' ? ' on' : ''; ?>">
    <i class="fas fa-shopping-cart"></i><span>ক্যাশ বিক্রি</span>
  </a>

  <button type="button" class="cb-nav__item center"
          onclick="if(typeof addSaleRow==='function'){addSaleRow();window.scrollTo({top:document.body.scrollHeight,behavior:'smooth'});}else{window.location.href='cash_sale.php';}">
    <i class="fas fa-plus"></i><span>নতুন সারি</span>
  </button>

  <a href="daily_report.php" class="cb-nav__item<?php echo $__cur==='reports.php' ? ' on' : ''; ?>">
    <i class="fas fa-chart-bar"></i><span>রিপোর্ট</span>
  </a>

  <button type="button" class="cb-nav__item"
          onclick="if(typeof toggleDrawer==='function'){toggleDrawer();}else{window.location.href='dashboard.php';}">
    <i class="fas fa-bars"></i><span>মেনু</span>
  </button>
</nav>
