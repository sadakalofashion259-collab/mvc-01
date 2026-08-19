<div class="sec-head">
  <div class="sec-title"><i class="fas fa-id-card"></i> কার্ড প্রোফাইল</div>
  <div class="sec-pill">ID #<?php echo $view_card['id']; ?></div>
</div>

<?php
  $hasCardImg = !empty($view_card['card_image']) && file_exists(MODULE_ROOT . '/' . ltrim($view_card['card_image'], '/'));
  $cardImgUrl = $hasCardImg ? ($moduleUrl . '/' . ltrim($view_card['card_image'], '/')) : '';
?>
<!-- Credit-card hero — পূর্ণ কার্ড ছবি -->
<div class="cc-hero <?php echo $view_summary['is_overlimit'] ? 'is-over' : ''; ?> <?php echo $hasCardImg ? 'has-photo' : ''; ?>">
  <?php if ($hasCardImg): ?>
    <img src="<?php echo htmlspecialchars($cardImgUrl); ?>" class="cc-full-img" alt="<?php echo htmlspecialchars($view_card['card_name']); ?>" onclick="showBig(this.src)">
    <div class="cc-photo-overlay">
      <div class="cc-lights" title="<?php echo $view_light['label']; ?>">
        <div class="lt <?php echo $view_light['color']==='green'?'on-green pulse':''; ?>"></div>
        <div class="lt <?php echo $view_light['color']==='yellow'?'on-yellow pulse':''; ?>"></div>
        <div class="lt <?php echo $view_light['color']==='red'?'on-red'.($view_light['pulse']?' pulse':''):''; ?>"></div>
      </div>
      <div class="cc-photo-meta">
        <div class="cc-name">
          <?php echo htmlspecialchars($view_card['card_name']); ?>
          <?php if($view_summary['is_overlimit']): ?><i class="fas fa-triangle-exclamation" style="color:#fecaca;font-size:12px"></i><?php endif; ?>
        </div>
        <div class="cc-num-sm">**** **** **** <?php echo $view_card['card_last4']; ?></div>
        <div class="cc-meta">বিলিং: <?php echo $view_card['billing_date']; ?> · গ্রেস: <?php echo $view_card['grace_days']; ?> দিন · <?php echo $view_light['label']; ?></div>
      </div>
    </div>
  <?php else: ?>
    <div class="cc-hero-top">
      <div class="cc-chip"></div>
      <div class="cc-lights" title="<?php echo $view_light['label']; ?>">
        <div class="lt <?php echo $view_light['color']==='green'?'on-green pulse':''; ?>"></div>
        <div class="lt <?php echo $view_light['color']==='yellow'?'on-yellow pulse':''; ?>"></div>
        <div class="lt <?php echo $view_light['color']==='red'?'on-red'.($view_light['pulse']?' pulse':''):''; ?>"></div>
      </div>
    </div>
    <div class="cc-num">**** **** **** <?php echo $view_card['card_last4']; ?></div>
    <div class="cc-bottom">
      <div style="min-width:0">
        <div class="cc-name">
          <?php echo htmlspecialchars($view_card['card_name']); ?>
          <?php if($view_summary['is_overlimit']): ?><i class="fas fa-triangle-exclamation" style="color:#fecaca;font-size:12px" title="লিমিট ক্রস করেছে!"></i><?php endif; ?>
        </div>
        <div class="cc-meta">বিলিং: <?php echo $view_card['billing_date']; ?> তারিখ · গ্রেস: <?php echo $view_card['grace_days']; ?> দিন</div>
        <div class="cc-statline" style="color:<?php echo $view_light['color']==='red'?'#fecaca':'#fff'; ?>">
          <i class="fas fa-circle" style="font-size:6px"></i> <?php echo $view_light['label']; ?>
        </div>
      </div>
      <div class="cc-avatar"><i class="fas fa-credit-card"></i></div>
    </div>
  <?php endif; ?>
</div>

<!-- Stat tiles -->
<div class="stat-grid" style="margin-top:12px">
  <div class="stat stat-4">
    <div class="stat-ico"><i class="fas fa-coins"></i></div>
    <div class="stat-lbl">ক্রেডিট লিমিট</div>
    <div class="stat-val">৳<?php echo number_format($view_summary['limit']); ?></div>
  </div>
  <div class="stat stat-2">
    <div class="stat-ico"><i class="fas fa-unlock"></i></div>
    <div class="stat-lbl">ব্যবহারযোগ্য</div>
    <div class="stat-val" style="color:<?php echo $view_summary['is_overlimit']?'var(--txt-mut)':'var(--c-green)'; ?>">৳<?php echo number_format($view_summary['available_balance']); ?></div>
  </div>
  <div class="stat stat-1">
    <div class="stat-ico"><i class="fas fa-circle-exclamation"></i></div>
    <div class="stat-lbl"><?php echo $view_summary['is_overlimit'] ? 'ওভারলিমিট' : 'বর্তমান বকেয়া'; ?></div>
    <div class="stat-val" style="color:var(--c-red)">৳<?php echo number_format($view_summary['is_overlimit'] ? $view_summary['overlimit_amt'] : $view_summary['current_due']); ?></div>
  </div>
  <div class="stat stat-3">
    <div class="stat-ico"><i class="fas fa-circle-check"></i></div>
    <div class="stat-lbl">মোট পরিশোধ</div>
    <div class="stat-val" style="color:var(--c-amber)">৳<?php echo number_format($view_summary['total_paid']); ?></div>
  </div>
</div>

<!-- Collapsible profile -->
<div class="card-soft" style="margin-top:12px">
  <button class="acc-head" type="button" data-bs-toggle="collapse" data-bs-target="#ddProfile" aria-expanded="false">
    <i class="lead-ic fas fa-circle-info"></i>
    <span class="acc-t">কার্ড প্রোফাইল তথ্য</span>
    <i class="chev fas fa-chevron-down"></i>
  </button>
  <div class="collapse" id="ddProfile">
    <div class="info-grid">
      <div class="info-cell"><div class="info-k">কার্ড নাম</div><div class="info-v"><?php echo htmlspecialchars($view_card['card_name']); ?></div></div>
      <div class="info-cell"><div class="info-k">মেয়াদ</div><div class="info-v"><?php echo $view_card['card_expiry'] ?: '—'; ?></div></div>
      <div class="info-cell"><div class="info-k">স্ট্যাটাস</div><div class="info-v" style="color:<?php echo $view_card['status']==='active'?'var(--c-green)':'var(--txt-mut)'; ?>"><?php echo strtoupper($view_card['status']); ?></div></div>
      <div class="info-cell"><div class="info-k">বিলিং ও গ্রেস</div><div class="info-v"><?php echo $view_card['billing_date']; ?> তা. (+<?php echo $view_card['grace_days']; ?> দিন)</div></div>
      <div class="info-cell"><div class="info-k">অ্যাড করেছেন</div><div class="info-v"><?php echo htmlspecialchars($view_card['entry_by'] ?? 'admin'); ?></div></div>
    </div>
    <?php if (!empty($view_card['notes'])): ?>
    <div class="note-box">
      <div class="info-k" style="margin-bottom:5px">নোটস</div>
      <div style="font-size:11.5px;color:var(--txt-2);font-weight:500;line-height:1.5"><?php echo nl2br(htmlspecialchars($view_card['notes'])); ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Transaction history -->
<div class="txn-history-title">
  <i class="fas fa-clock-rotate-left"></i> লেনদেনের ইতিহাস
  <?php if($total_pages > 1): ?><small>(পেজ <?php echo $current_page; ?> / <?php echo $total_pages; ?>)</small><?php endif; ?>
</div>

<?php if (empty($grouped_ledger)): ?>
  <div class="empty">
    <div class="empty-ic"><i class="fas fa-inbox"></i></div>
    <div class="empty-tx">এখনো কোনো ট্রানজেকশন নেই।</div>
  </div>
<?php else: ?>
  <?php
    $txn_icons = ['bill_pay'=>'fa-money-bill-wave','min_pay'=>'fa-receipt','full_pay'=>'fa-check-double','charge_pay'=>'fa-percent','cash_advance'=>'fa-wallet','purchase'=>'fa-bag-shopping'];
    $txn_ic_cls = ['bill_pay'=>'ti-bill','min_pay'=>'ti-min','full_pay'=>'ti-full','charge_pay'=>'ti-chg','cash_advance'=>'ti-adv','purchase'=>'ti-pur'];
  ?>
  <?php foreach ($grouped_ledger as $cycle => $ledgers): ?>
    <div class="cycle-head">
      <span class="cy-l"><i class="fas fa-calendar-days"></i> <?php echo BillingService::getBillingCycleLabel($cycle, $view_card['billing_date']); ?></span>
      <span class="cy-b"><?php echo count($ledgers); ?> টি এন্ট্রি</span>
    </div>
    <?php foreach ($ledgers as $l):
      $tlbl = $type_labels[$l['txn_type']] ?? $l['txn_type'];
      $ic   = $txn_icons[$l['txn_type']] ?? 'fa-arrow-right-arrow-left';
      $iccl = $txn_ic_cls[$l['txn_type']] ?? 'ti-bill';
    ?>
    <div class="txn">
      <div class="txn-ic <?php echo $iccl; ?>"><i class="fas <?php echo $ic; ?>"></i></div>
      <div class="txn-main">
        <div class="txn-r1">
          <span class="txn-type"><?php echo $tlbl; ?></span>
          <span class="txn-amt">৳<?php echo number_format($l['amount']); ?></span>
        </div>
        <div class="txn-r2">
          <span class="txn-date"><i class="fas fa-calendar" style="opacity:.6"></i> <?php echo date('d M Y', strtotime($l['txn_date'])); ?></span>
          <?php if($l['charge_amount']>0): ?><span class="chiplet chip-warn">চার্জ ৳<?php echo number_format($l['charge_amount']); ?></span><?php endif; ?>
          <span class="chiplet <?php echo $l['card_due_impact']<0?'chip-pos':'chip-neg'; ?>">বকেয়া <?php echo ($l['card_due_impact']>=0?'+':'').'৳'.number_format($l['card_due_impact']); ?></span>
          <?php if($l['cash_impact']!=0): ?><span class="chiplet <?php echo $l['cash_impact']<0?'chip-neg':'chip-pos'; ?>">ক্যাশ <?php echo ($l['cash_impact']>0?'+':'').'৳'.number_format($l['cash_impact']); ?></span><?php endif; ?>
        </div>
        <?php if(!empty($l['description'])): ?><div class="txn-note"><i class="fas fa-quote-left" style="opacity:.4;font-size:8px"></i> <?php echo htmlspecialchars($l['description']); ?></div><?php endif; ?>
      </div>
      <div class="txn-side">
        <?php if(!empty($l['receipt_image'])): ?><img src="<?php echo $moduleUrl . '/' . ltrim($l['receipt_image'], '/'); ?>" loading="lazy" class="txn-thumb" onclick="showBig(this.src)" alt="রিসিট"><?php endif; ?>
        <div class="txn-act">
          <button class="mini-btn mb-edit" title="এডিট" onclick="openEditTxnModal(<?php echo $l['id']; ?>, '<?php echo $l['txn_type']; ?>', '<?php echo $l['txn_date']; ?>', <?php echo $l['amount']; ?>, <?php echo $l['charge_amount']; ?>, '<?php echo addslashes(htmlspecialchars($l['description'])); ?>')"><i class="fas fa-pen"></i></button>
          <button class="mini-btn mb-del" title="ডিলিট" onclick="openDeleteLedger(<?php echo $l['id']; ?>)"><i class="fas fa-trash"></i></button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <?php if ($total_pages > 1): ?>
  <div class="pager">
    <?php if ($current_page > 1): ?>
      <a href="<?php echo $moduleUrl; ?>/index.php?view=<?php echo $vid; ?>&page=1" class="pg" title="প্রথম পেজ"><i class="fas fa-angles-left"></i></a>
      <a href="<?php echo $moduleUrl; ?>/index.php?view=<?php echo $vid; ?>&page=<?php echo $current_page-1; ?>" class="pg" title="আগের মাস"><i class="fas fa-angle-left"></i></a>
    <?php endif; ?>
    <span class="pg-txt">পেজ <?php echo $current_page; ?> / <?php echo $total_pages; ?></span>
    <?php if ($current_page < $total_pages): ?>
      <a href="<?php echo $moduleUrl; ?>/index.php?view=<?php echo $vid; ?>&page=<?php echo $current_page+1; ?>" class="pg" title="পরের মাস"><i class="fas fa-angle-right"></i></a>
      <a href="<?php echo $moduleUrl; ?>/index.php?view=<?php echo $vid; ?>&page=<?php echo $total_pages; ?>" class="pg" title="শেষ পেজ"><i class="fas fa-angles-right"></i></a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

<?php endif; ?>

