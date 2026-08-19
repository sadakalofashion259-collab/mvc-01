<!-- views/ledger/table.php — গ্লোবাল লেজার (সম্পূর্ণ আলাদা টেবিল সিস্টেম, account card থেকে independent) -->
<div id="viewLedger" class="app-view d-none">
    <div class="section-head">
        <h6><i class="fas fa-book"></i> সম্পূর্ণ লেজার</h6>
        <div class="d-flex gap-2">
            <select id="ledgerAccFilter" class="form-select form-select-sm w-auto">
                <option value="all">সব অ্যাকাউন্ট</option>
            </select>
            <button id="printLedgerBtn" class="btn btn-sm btn-soft-primary compact-btn" title="প্রিন্ট করুন"><i class="fas fa-print"></i></button>
        </div>
    </div>

    <div class="table-responsive ledger-table-wrap">
        <table class="table table-sm align-middle mb-0 ledger-table">
            <thead>
                <tr>
                    <th>তারিখ</th>
                    <th>অ্যাকাউন্ট</th>
                    <th>বিবরণ</th>
                    <th class="text-end">জমা</th>
                    <th class="text-end">উত্তোলন</th>
                    <th class="text-end">ব্যালেন্স</th>
                    <th class="text-center">অ্যাকশন</th>
                </tr>
            </thead>
            <tbody id="globalLedgerBody">
                <tr><td colspan="7" class="empty-state"><i class="fas fa-spinner fa-spin"></i></td></tr>
            </tbody>
        </table>
    </div>

    <div class="ledger-pager">
        <button id="pagerNewerBtn" class="mini-act-wide"><i class="fas fa-chevron-left"></i> নতুন</button>
        <span id="pageLabel" class="page-label">১ / ১</span>
        <button id="pagerOlderBtn" class="mini-act-wide">পুরনো <i class="fas fa-chevron-right"></i></button>
    </div>
</div>
