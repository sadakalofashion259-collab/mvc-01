<?php if ($view_card): // ===== সিঙ্গেল কার্ড ভিউ ===== ?>
<div class="bottom-nav">
  <div class="bn-inner">
    <button class="bn" onclick="openTxnModal('purchase')" type="button">
      <span class="bn-ic g-pur"><i class="fas fa-bag-shopping"></i></span>
      <span class="bn-lbl">কেনাকাটা</span>
    </button>
    <button class="bn" onclick="openTxnModal('cash_advance')" type="button">
      <span class="bn-ic g-adv"><i class="fas fa-wallet"></i></span>
      <span class="bn-lbl">ক্যাশ অ্যাড</span>
    </button>
    <button class="bn" onclick="openTxnModal('bill_pay')" type="button">
      <span class="bn-ic g-bill"><i class="fas fa-money-bill-wave"></i></span>
      <span class="bn-lbl">বিল পে</span>
    </button>
    <button class="bn" onclick="toggleMoreMenu()" type="button">
      <span class="bn-ic g-more"><i class="fas fa-ellipsis"></i></span>
      <span class="bn-lbl">আরও</span>
    </button>
  </div>
</div>

<!-- Bottom sheet (More) -->
<div class="offcanvas offcanvas-bottom sheet" tabindex="-1" id="moreSheet" aria-labelledby="moreSheetLbl">
  <div class="grip"></div>
  <div class="sheet-title" id="moreSheetLbl">আরও অপশন</div>
  <div class="sheet-grid">
    <div class="sheet-item" onclick="closeMoreMenu();openTxnModal('min_pay')">
      <span class="sheet-ic ti-min"><i class="fas fa-receipt"></i></span>
      <span class="sheet-lbl">মিনিমাম পে</span>
    </div>
    <div class="sheet-item" onclick="closeMoreMenu();openTxnModal('full_pay')">
      <span class="sheet-ic ti-full"><i class="fas fa-check-double"></i></span>
      <span class="sheet-lbl">ফুল পে</span>
    </div>
    <div class="sheet-item" onclick="closeMoreMenu();openTxnModal('charge_pay')">
      <span class="sheet-ic ti-chg"><i class="fas fa-percent"></i></span>
      <span class="sheet-lbl">চার্জ পে</span>
    </div>
    <div class="sheet-item" onclick="closeMoreMenu();openEditCardModal()">
      <span class="sheet-ic" style="background:linear-gradient(135deg,#f59e0b,#b45309)"><i class="fas fa-pen-to-square"></i></span>
      <span class="sheet-lbl">এডিট প্রোফাইল</span>
    </div>
    <div class="sheet-item" onclick="closeMoreMenu();openUnmaskModal(<?php echo (int)$view_card['id']; ?>)">
      <span class="sheet-ic ti-full"><i class="fas fa-eye"></i></span>
      <span class="sheet-lbl">কার্ড নাম্বার</span>
    </div>
    <div class="sheet-item" onclick="closeMoreMenu();openToggleStatus(<?php echo (int)$view_card['id']; ?>)">
      <span class="sheet-ic" style="background:linear-gradient(135deg,#6366f1,#4338ca)"><i class="fas fa-power-off"></i></span>
      <span class="sheet-lbl"><?php echo $view_card['status']==='active'?'নিষ্ক্রিয় করুন':'সক্রিয় করুন'; ?></span>
    </div>
    <div class="sheet-item" onclick="closeMoreMenu();openDeleteCard(<?php echo (int)$view_card['id']; ?>)">
      <span class="sheet-ic" style="background:linear-gradient(135deg,#f43f5e,#9f1239)"><i class="fas fa-trash"></i></span>
      <span class="sheet-lbl">কার্ড ডিলিট</span>
    </div>
    <a href="<?php echo htmlspecialchars($moduleUrl); ?>/index.php" class="sheet-item" onclick="closeMoreMenu()">
      <span class="sheet-ic g-more"><i class="fas fa-arrow-left"></i></span>
      <span class="sheet-lbl">লিস্টে যান</span>
    </a>
  </div>
</div>

<?php else: // ===== মেইন কার্ড লিস্ট ===== ?>
<div class="bottom-nav">
  <div class="bn-inner" style="justify-content:space-around">
    <a href="/dashboard.php" class="bn" style="flex:0 0 auto">
      <span class="bn-ic g-home"><i class="fas fa-gauge-high"></i></span>
      <span class="bn-lbl">ড্যাশবোর্ড</span>
    </a>
    <button class="bn" style="flex:0 0 auto" onclick="openAddCardModal()" type="button">
      <span class="bn-ic g-add"><i class="fas fa-plus"></i></span>
      <span class="bn-lbl">নতুন কার্ড</span>
    </button>
    <a href="/dashboard.php" class="bn" style="flex:0 0 auto" title="পেছনে">
      <span class="bn-ic g-more"><i class="fas fa-arrow-left"></i></span>
      <span class="bn-lbl">পেছনে</span>
    </a>
  </div>
</div>
<?php endif; ?>
