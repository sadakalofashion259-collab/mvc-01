<?php
/**
 * inventory_bottom_nav.php — SADA KALO ইনভেন্টরি হাব · বটম নেভিগেশন বার
 * ব্যবহার: শুধু inventory_dashboard.php এ </body> এর আগে
 *   <?php include 'inventory_bottom_nav.php'; ?>
 * (body তে padding-bottom:76px রাখুন)
 * থিম টোকেন (theme.css) ব্যবহার করে — light/dark দুটোতেই চলে।
 */
$__cur = basename($_SERVER['PHP_SELF']);
$__items = [
    ['file'=>'inventory_dashboard.php',   'icon'=>'fa-th-large',    'label'=>'ড্যাশবোর্ড'],
    ['file'=>'Invantory_Items.php',       'icon'=>'fa-box-open',    'label'=>'আইটেম'],
    ['file'=>'inventory_pos.php',          'icon'=>'fa-shopping-cart','label'=>'POS', 'center'=>true],
    ['file'=>'inventory_sales_history.php','icon'=>'fa-receipt',     'label'=>'হিস্ট্রি'],
    ['menu'=>true,                         'icon'=>'fa-bars',        'label'=>'মেনু'],
];
?>
<style>
  .sk-bnav{position:fixed;bottom:0;left:0;right:0;z-index:940;display:flex;align-items:flex-end;justify-content:space-around;
    background:#000000;border-top:1px solid rgba(255,255,255,0.08);
    padding:8px 6px calc(8px + env(safe-area-inset-bottom,0px));box-shadow:0 -6px 20px rgba(0,0,0,.08);}
  .sk-bnav__item{flex:1;border:none;background:none;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:4px;
    padding:4px 0;text-decoration:none;color:rgba(255,255,255,0.5);font-family:inherit;}
  .sk-bnav__item i{font-size:18px;line-height:1;}
  .sk-bnav__item span{font-size:9.5px;font-weight:800;letter-spacing:.3px;}
  .sk-bnav__item.on{color:#fff;}
  .sk-bnav__item.center i{width:50px;height:50px;border-radius:16px;background:var(--sk-grad-brand);color:#fff;
    display:flex;align-items:center;justify-content:center;font-size:19px;box-shadow:var(--sk-shadow-brand);
    border:3px solid #000000;margin-top:-14px;}
  .sk-bnav__item.center span{color:#fff;}
</style>
<nav class="sk-bnav">
<?php foreach($__items as $it):
    $center = !empty($it['center']) ? ' center' : '';
    if (!empty($it['menu'])): ?>
    <button type="button" class="sk-bnav__item" onclick="if(typeof toggleSidebar==='function'){toggleSidebar();}else{window.location.href='inventory_dashboard.php';}">
        <i class="fas <?php echo $it['icon']; ?>"></i><span><?php echo $it['label']; ?></span>
    </button>
    <?php else:
        $on = ($__cur === $it['file']) ? ' on' : ''; ?>
    <a href="<?php echo $it['file']; ?>" class="sk-bnav__item<?php echo $center.$on; ?>">
        <i class="fas <?php echo $it['icon']; ?>"></i><span><?php echo $it['label']; ?></span>
    </a>
    <?php endif; ?>
<?php endforeach; ?>
</nav>
