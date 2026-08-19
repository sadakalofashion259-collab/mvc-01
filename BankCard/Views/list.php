<div class="sec-head">
  <div class="sec-title"><i class="fas fa-wallet"></i> ক্রেডিট কার্ড ম্যানেজার</div>
  <div class="sec-pill"><?php echo count($active_cards); ?> এক্টিভ · <?php echo count($inactive_cards); ?> নিষ্ক্রিয়</div>
</div>

<div class="stat-grid">
  <div class="stat stat-1"><div class="stat-ico"><i class="fas fa-circle-exclamation"></i></div><div class="stat-lbl">মোট বকেয়া</div><div class="stat-val" style="color:var(--c-red)">৳<?php echo number_format($g_total_due); ?></div></div>
  <div class="stat stat-2"><div class="stat-ico"><i class="fas fa-circle-check"></i></div><div class="stat-lbl">মোট পরিশোধ</div><div class="stat-val" style="color:var(--c-green)">৳<?php echo number_format($g_total_paid); ?></div></div>
  <div class="stat stat-3"><div class="stat-ico"><i class="fas fa-percent"></i></div><div class="stat-lbl">মোট চার্জ</div><div class="stat-val" style="color:var(--c-amber)">৳<?php echo number_format($g_total_charge); ?></div></div>
  <div class="stat stat-4"><div class="stat-ico"><i class="fas fa-coins"></i></div><div class="stat-lbl">মোট ব্যবহার</div><div class="stat-val" style="color:var(--brand-2)">৳<?php echo number_format($g_total_used); ?></div></div>
</div>

<?php if (empty($active_cards) && empty($inactive_cards)): ?>
<div class="empty" style="margin-top:16px">
  <div class="empty-ic"><i class="fas fa-credit-card"></i></div>
  <div class="empty-tx">এখনো কোনো কার্ড অ্যাড করা হয়নি।<br>নিচে "নতুন কার্ড" বাটনে ক্লিক করুন।</div>
</div>
<?php else: ?>

<?php if (!empty($active_cards)): ?>
<div class="sub-head"><span class="dot" style="background:var(--c-green)"></span> এক্টিভ কার্ডসমূহ <span class="cnt"><?php echo count($active_cards); ?></span></div>
<?php foreach ($active_cards as $c): $light = $c['light']; $sum = $c['summary']; 
  $imgAbs = !empty($c['card_image']) ? MODULE_ROOT . '/' . ltrim($c['card_image'], '/') : '';
  $imgUrl = !empty($c['card_image']) ? $moduleUrl . '/' . ltrim($c['card_image'], '/') : '';
?>
<div class="card-row <?php echo $sum['is_overlimit']?'overlimit':''; ?>" onclick="location.href='<?php echo $moduleUrl; ?>/index.php?view=<?php echo $c['id']; ?>'">
  <?php if ($imgAbs && file_exists($imgAbs)): ?>
    <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="card-av" alt="">
  <?php else: ?>
    <div class="card-av-def"><i class="fas fa-credit-card"></i></div>
  <?php endif; ?>
  <div class="card-body-x">
    <div class="card-r1">
      <div class="card-nm"><?php echo htmlspecialchars($c['card_name']); ?></div>
      <div class="card-mask">**<?php echo $c['card_last4']; ?></div>
    </div>
    <div class="card-r2">
      <span class="k-lbl"><?php echo $sum['is_overlimit']?'ওভারলিমিট:':'বকেয়া:'; ?></span>
      <span class="k-due">৳<?php echo number_format($sum['is_overlimit'] ? $sum['overlimit_amt'] : $sum['current_due']); ?></span>
      <span class="k-lbl" style="margin-left:4px">অ্যাভেইলেবল:</span>
      <span class="k-avail" style="color:<?php echo $sum['is_overlimit']?'var(--txt-mut)':'var(--c-green)'; ?>">৳<?php echo number_format($sum['available_balance']); ?></span>
    </div>
  </div>
  <div class="card-lights" title="<?php echo $light['label']; ?>">
    <div class="lt <?php echo $light['color']==='green'?'on-green pulse':''; ?>"></div>
    <div class="lt <?php echo $light['color']==='yellow'?'on-yellow pulse':''; ?>"></div>
    <div class="lt <?php echo $light['color']==='red'?'on-red'.($light['pulse']?' pulse':''):''; ?>"></div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($inactive_cards)): ?>
<div class="sub-head"><span class="dot" style="background:var(--txt-mut)"></span> নিষ্ক্রিয় কার্ডসমূহ <span class="cnt"><?php echo count($inactive_cards); ?></span></div>
<?php foreach ($inactive_cards as $c): $sum = $c['summary']; ?>
<div class="card-row inactive" onclick="location.href='<?php echo $moduleUrl; ?>/index.php?view=<?php echo $c['id']; ?>'">
  <div class="card-av-def dead"><i class="fas fa-ban"></i></div>
  <div class="card-body-x">
    <div class="card-r1">
      <div class="card-nm"><?php echo htmlspecialchars($c['card_name']); ?></div>
      <div class="card-mask">**<?php echo $c['card_last4']; ?></div>
    </div>
    <div class="card-r2">
      <span class="k-lbl">বকেয়া:</span>
      <span class="k-due">৳<?php echo number_format($sum['current_due']); ?></span>
      <span class="k-lbl" style="margin-left:auto">নিষ্ক্রিয়</span>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php endif; ?>
