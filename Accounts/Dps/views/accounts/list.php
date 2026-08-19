<!-- views/accounts/list.php — অ্যাকাউন্ট কার্ড লিস্ট (ledger/report থেকে সম্পূর্ণ আলাদা view) -->
<div id="viewAccounts" class="app-view">
    <div class="dps-tab-bar">
        <button class="dps-tab on" data-status="active">সক্রিয়</button>
        <button class="dps-tab" data-status="inactive">নিষ্ক্রিয়</button>
    </div>
    <div id="accountGrid" class="acc-grid">
        <div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div>
    </div>
</div>

<!-- ── অ্যাকাউন্ট বিস্তারিত (detail) — নিজস্ব ledger window সহ, কিন্তু আলাদা container ── -->
<div class="sheet-overlay" id="sheetAccDetail">
    <div class="bottom-sheet bottom-sheet-lg">
        <div class="sheet-handle"></div>
        <div id="accDetailHeader" class="acc-detail-header"></div>

        <div class="stat3" id="accDetailStats"></div>

        <div class="detail-actions">
            <button class="btn btn-sm btn-soft-success compact-btn" onclick="openDepositForAccount(currentDetailId)"><i class="fas fa-arrow-down"></i> জমা করুন</button>
            <button class="btn btn-sm btn-soft-primary compact-btn" onclick="openEditAccount(currentDetailId)"><i class="fas fa-pen"></i> তথ্য এডিট</button>
            <button class="btn btn-sm btn-soft-danger compact-btn" onclick="toggleDpsStatus(currentDetailId)"><i class="fas fa-power-off"></i> স্ট্যাটাস</button>
        </div>

        <div class="section-head" style="padding-top:2px;">
            <h6 style="font-size:.8rem;"><i class="fas fa-list"></i> এই একাউন্টের লেজার</h6>
            <button id="printDetailLedgerBtn" class="btn btn-sm btn-soft-primary compact-btn" title="প্রিন্ট করুন"><i class="fas fa-print"></i></button>
        </div>

        <div class="table-responsive ledger-table-wrap mt-2">
            <table class="table table-sm align-middle mb-0 ledger-table">
                <thead>
                    <tr>
                        <th>তারিখ</th><th>বিবরণ</th><th class="text-end">জমা</th>
                        <th class="text-end">উত্তোলন</th><th class="text-end">ব্যালেন্স</th><th class="text-center">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody id="detailLedgerBody"></tbody>
            </table>
        </div>
        <div class="ledger-pager">
            <button id="detailNewerBtn" class="mini-act-wide"><i class="fas fa-chevron-left"></i> নতুন</button>
            <span id="detailPageLabel" class="page-label">১ / ১</span>
            <button id="detailOlderBtn" class="mini-act-wide">পুরনো <i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</div>
