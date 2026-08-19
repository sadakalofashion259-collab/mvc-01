<!-- ===== Add Card Modal ===== -->
<div class="modal fade app-modal" id="addCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><span class="m-ic"><i class="fas fa-plus"></i></span> নতুন কার্ড প্রোফাইল</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_card">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
          <div class="fld"><label class="fld-lbl">কার্ড নাম *</label><input type="text" name="card_name" class="form-control" placeholder="উদা: সিটি ব্যাংক Amex" required></div>
          <div class="fld"><label class="fld-lbl">কার্ড নাম্বার *</label><input type="text" name="card_number" class="form-control" placeholder="4532 1234 5678 9012" required maxlength="25"><div class="fld-help">এনক্রিপ্ট হয়ে সেভ হবে।</div></div>
          <div class="fld-row">
            <div class="fld"><label class="fld-lbl">৬-ডিজিট পিন</label><input type="password" name="card_pin" class="form-control" placeholder="••••••" maxlength="6"></div>
            <div class="fld"><label class="fld-lbl">মেয়াদ (MM/YY)</label><input type="text" name="card_expiry" class="form-control" placeholder="12/28" maxlength="7"></div>
          </div>
          <div class="fld"><label class="fld-lbl">ক্রেডিট লিমিট *</label><input type="number" name="credit_limit" class="form-control" step="0.01" value="100000" required><div class="fld-help">সর্বোচ্চ কত ধার নেওয়া যাবে।</div></div>
          <div class="fld-row">
            <div class="fld"><label class="fld-lbl">বিলিং ডেট (১-৩১) *</label><input type="number" name="billing_date" class="form-control" min="1" max="31" value="1" required></div>
            <div class="fld"><label class="fld-lbl">গ্রেস পিরিয়ড (দিন) *</label><input type="number" name="grace_days" class="form-control" min="1" max="60" value="15" required><div class="fld-help">বিলের পর কতদিন সময়।</div></div>
          </div>
          <div class="fld"><label class="fld-lbl">কার্ডের ছবি</label><input type="file" name="card_image" class="form-control fld-file" accept="image/*"></div>
          <div class="fld"><label class="fld-lbl">নোটস</label><textarea name="notes" class="form-control" placeholder="অতিরিক্ত তথ্য…"></textarea></div>
          <div class="modal-foot">
            <button type="button" class="btn3d b-ghost" data-bs-dismiss="modal"><i class="fas fa-xmark"></i> বাতিল</button>
            <button type="submit" class="btn3d b-primary"><i class="fas fa-floppy-disk"></i> সংরক্ষণ</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($view_card): ?>
<!-- ===== Edit Card Modal ===== -->
<div class="modal fade app-modal" id="editCardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><span class="m-ic"><i class="fas fa-pen-to-square"></i></span> কার্ড প্রোফাইল এডিট</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="update_card">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
          <input type="hidden" name="card_id" value="<?php echo $view_card['id']; ?>">
          <div class="fld"><label class="fld-lbl">কার্ড নাম *</label><input type="text" name="card_name" class="form-control" value="<?php echo htmlspecialchars($view_card['card_name']); ?>" required></div>
          <div class="fld-row">
            <div class="fld"><label class="fld-lbl">মেয়াদ (MM/YY)</label><input type="text" name="card_expiry" class="form-control" value="<?php echo htmlspecialchars($view_card['card_expiry'] ?? ''); ?>" maxlength="7"></div>
            <div class="fld"><label class="fld-lbl">ক্রেডিট লিমিট *</label><input type="number" name="credit_limit" class="form-control" step="0.01" value="<?php echo $view_card['credit_limit']; ?>" required></div>
          </div>
          <div class="fld-row">
            <div class="fld"><label class="fld-lbl">বিলিং ডেট (১-৩১) *</label><input type="number" name="billing_date" class="form-control" min="1" max="31" value="<?php echo $view_card['billing_date']; ?>" required></div>
            <div class="fld"><label class="fld-lbl">গ্রেস পিরিয়ড (দিন) *</label><input type="number" name="grace_days" class="form-control" min="1" max="60" value="<?php echo $view_card['grace_days']; ?>" required></div>
          </div>
          <div class="fld"><label class="fld-lbl">কার্ডের ছবি (পরিবর্তন)</label><input type="file" name="card_image" class="form-control fld-file" accept="image/*"><div class="fld-help">খালি রাখলে পুরোনো ছবি বহাল থাকবে।</div></div>
          <div class="fld"><label class="fld-lbl">নোটস</label><textarea name="notes" class="form-control"><?php echo htmlspecialchars($view_card['notes'] ?? ''); ?></textarea></div>
          <div class="modal-foot">
            <button type="button" class="btn3d b-ghost" data-bs-dismiss="modal"><i class="fas fa-xmark"></i> বাতিল</button>
            <button type="submit" class="btn3d b-amber"><i class="fas fa-floppy-disk"></i> আপডেট</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ===== Transaction Modal ===== -->
<div class="modal fade app-modal" id="txnModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><span class="m-ic"><i class="fas fa-arrow-right-arrow-left"></i></span> <span id="txnModalTitle">ট্রানজেকশন</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_transaction">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
          <input type="hidden" name="card_id" value="<?php echo $view_card['id']; ?>">
          <input type="hidden" name="txn_type" id="txnType" value="">
          <input type="hidden" name="current_page" value="<?php echo $current_page; ?>">
          <div class="txn-hint" id="txnDescBadge"></div>
          <div class="fld-row">
            <div class="fld"><label class="fld-lbl">তারিখ *</label><input type="date" name="txn_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
            <div class="fld"><label class="fld-lbl">অ্যামাউন্ট *</label><input type="number" name="amount" class="form-control" step="0.01" min="0.01" required></div>
          </div>
          <div class="fld" id="chargeGrp"><label class="fld-lbl">চার্জ অ্যামাউন্ট (যদি থাকে)</label><input type="number" name="charge_amount" class="form-control" step="0.01" min="0" value="0"></div>
          <div class="fld"><label class="fld-lbl">নোট / বিবরণ (ঐচ্ছিক)</label><textarea name="description" class="form-control" placeholder="এখানে কিছু লিখতে পারেন বা খালি রাখতে পারেন..."></textarea></div>
          <div class="fld"><label class="fld-lbl">রিসিট/বিলের ছবি</label><input type="file" name="receipt_image" class="form-control fld-file" accept="image/*,application/pdf"></div>
          <div class="modal-foot">
            <button type="button" class="btn3d b-ghost" data-bs-dismiss="modal"><i class="fas fa-xmark"></i> বাতিল</button>
            <button type="submit" class="btn3d b-green"><i class="fas fa-check"></i> এন্ট্রি করুন</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ===== Edit Transaction Modal ===== -->
<div class="modal fade app-modal" id="editTxnModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><span class="m-ic"><i class="fas fa-pen"></i></span> এডিট ট্রানজেকশন</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="update_transaction">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
          <input type="hidden" name="card_id" value="<?php echo $view_card['id']; ?>">
          <input type="hidden" name="ledger_id" id="editTxnId" value="">
          <input type="hidden" name="txn_type" id="editTxnType" value="">
          <input type="hidden" name="current_page" value="<?php echo $current_page; ?>">
          <div class="fld-row">
            <div class="fld"><label class="fld-lbl">তারিখ *</label><input type="date" name="txn_date" id="editTxnDate" class="form-control" required></div>
            <div class="fld"><label class="fld-lbl">অ্যামাউন্ট *</label><input type="number" name="amount" id="editTxnAmt" class="form-control" step="0.01" required></div>
          </div>
          <div class="fld"><label class="fld-lbl">চার্জ অ্যামাউন্ট</label><input type="number" name="charge_amount" id="editTxnCharge" class="form-control" step="0.01"></div>
          <div class="fld"><label class="fld-lbl">বিবরণ</label><textarea name="description" id="editTxnDesc" class="form-control"></textarea></div>
          <div class="modal-foot">
            <button type="button" class="btn3d b-ghost" data-bs-dismiss="modal"><i class="fas fa-xmark"></i> বাতিল</button>
            <button type="submit" class="btn3d b-amber"><i class="fas fa-floppy-disk"></i> আপডেট করুন</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ===== Unmask Modal ===== -->
<div class="modal fade app-modal" id="unmaskModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><span class="m-ic"><i class="fas fa-eye"></i></span> কার্ড নাম্বার ও পিন</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="reveal-box">
          <div class="reveal-lbl">কার্ড নাম্বার</div>
          <div id="unmaskNum" class="reveal-num">****</div>
          <div class="reveal-lbl">পিন</div>
          <div id="unmaskPin" class="reveal-pin">****</div>
        </div>
        <div class="modal-foot"><button type="button" class="btn3d b-ghost" data-bs-dismiss="modal"><i class="fas fa-xmark"></i> বন্ধ করুন</button></div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ===== Password Modal ===== -->
<div class="modal fade app-modal" id="pwModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body" style="text-align:center;padding:24px 22px">
        <div class="pw-ic"><i class="fas fa-shield-halved"></i></div>
        <div style="font-size:14px;font-weight:800;color:var(--txt)" id="pwTitle">সিকিউরিটি যাচাই</div>
        <div style="font-size:11px;color:var(--txt-2);margin:6px 0 14px;line-height:1.5" id="pwSub">Admin পাসওয়ার্ড দিন</div>
        <input type="password" id="pwInp" class="form-control pw-inp" placeholder="••••••••" autocomplete="off">
        <div class="pw-err" id="pwErr"></div>
        <div class="modal-foot">
          <button type="button" class="btn3d b-ghost" onclick="closePwModal()">বাতিল</button>
          <button type="button" class="btn3d b-danger" id="pwOkBtn" onclick="pwConfirm()">নিশ্চিত করুন</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== Image Viewer ===== -->
<div class="modal fade" id="imgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" style="display:flex;justify-content:center">
    <img id="bigImg" alt="">
  </div>
</div>

<!-- Bootstrap 5.3.8 JS -->
