<?php
/**
 * customer_bottom_nav.php — সাদা-কালো ফ্যাশন · কাস্টমার পেজ বটম নেভিগেশন বার
 *
 * ব্যবহার: কাস্টমার পেজের </body> এর ঠিক আগে —
 *   <?php include 'customer_bottom_nav.php'; ?>
 * (body তে padding-bottom:90px আগে থেকেই আছে; কম হলে যোগ করুন)
 *
 * customers.php-এর থিম টোকেন (--brand-1, --card-bg, --soft-bd, --txt-mut),
 * Bootstrap Icons এবং Hind Siliguri ফন্ট ব্যবহার করে — light/dark দুটোতেই চলে।
 * সব বাটন গার্ডেড: ফাংশন/মডাল না থাকলে সঠিক পেজে নিয়ে যায়।
 */
$__cur = basename($_SERVER['PHP_SELF']);
?>
<style>
  .ck-bnav{position:fixed;bottom:0;left:0;right:0;z-index:940;display:flex;align-items:flex-end;justify-content:space-around;
    background:var(--card-bg,#fff);border-top:1px solid var(--soft-bd,#E6E7EA);
    padding:8px 6px calc(9px + env(safe-area-inset-bottom,0px));box-shadow:0 -6px 22px -8px rgba(0,0,0,.25);}
  .ck-bnav__item{flex:1;border:none;background:none;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:4px;
    padding:4px 0;text-decoration:none;color:var(--txt-mut,#71717A);font-family:'Hind Siliguri',sans-serif;}
  .ck-bnav__item i{font-size:19px;line-height:1;}
  .ck-bnav__item span{font-size:10px;font-weight:800;letter-spacing:.2px;}
  .ck-bnav__item.on{color:var(--brand-1,#E5242A);}
  .ck-bnav__item.center i{width:52px;height:52px;border-radius:16px;background:var(--grad-brand,linear-gradient(135deg,#E5242A,#A50F14));
    color:#fff;display:flex;align-items:center;justify-content:center;font-size:21px;
    box-shadow:0 8px 20px -6px rgba(229,36,42,.7);border:3px solid var(--card-bg,#fff);margin-top:-16px;}
  .ck-bnav__item.center span{color:var(--brand-1,#E5242A);}
  .ck-bnav__item:active{transform:scale(.92);transition:transform .1s;}
</style>

<nav class="ck-bnav">
  <a href="/dashboard.php" class="ck-bnav__item">
    <i class="bi bi-house-fill"></i><span>হোম</span>
  </a>

  <a href="customers.php" class="ck-bnav__item<?php echo $__cur==='customers.php' ? ' on' : ''; ?>">
    <i class="bi bi-people-fill"></i><span>কাস্টমার</span>
  </a>

  <button type="button" class="ck-bnav__item center"
          onclick="if(typeof openSheet==='function'){openSheet('add');}else{window.location.href='customers.php';}">
    <i class="bi bi-person-plus-fill"></i><span>যোগ</span>
  </button>

  <button type="button" class="ck-bnav__item"
          onclick="var s=document.getElementById('srchInp');if(s){window.scrollTo({top:0,behavior:'smooth'});s.focus();}else{window.location.href='customers.php';}">
    <i class="bi bi-search"></i><span>খুঁজুন</span>
  </button>

  <button type="button" class="ck-bnav__item"
          onclick="if(typeof toggleTheme==='function'){toggleTheme();}">
    <i class="bi bi-moon-stars-fill"></i><span>থিম</span>
  </button>
</nav>
