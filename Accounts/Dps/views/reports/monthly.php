<!-- views/reports/monthly.php + weekly.php একসাথে একটি ট্যাব-ভিত্তিক view-এ -->
<div id="viewReports" class="app-view d-none">
    <div class="section-head">
        <h6><i class="fas fa-chart-column"></i> রিপোর্ট</h6>
        <select id="reportAccFilter" class="form-select form-select-sm w-auto">
            <option value="all">সব অ্যাকাউন্ট</option>
        </select>
    </div>

    <div class="dps-tab-bar">
        <button class="report-tab on" data-mode="monthly">মাসভিত্তিক</button>
        <button class="report-tab" data-mode="weekly">সাপ্তাহিক</button>
    </div>

    <div class="table-responsive ledger-table-wrap">
        <table class="table table-sm align-middle mb-0 ledger-table">
            <thead>
                <tr>
                    <th id="reportPeriodHead">মাস</th>
                    <th>অ্যাকাউন্ট</th>
                    <th class="text-end">জমা</th>
                    <th class="text-end">মুনাফা</th>
                    <th class="text-end">উত্তোলন</th>
                    <th class="text-center">এন্ট্রি</th>
                </tr>
            </thead>
            <tbody id="reportBody">
                <tr><td colspan="6" class="empty-state"><i class="fas fa-spinner fa-spin"></i></td></tr>
            </tbody>
        </table>
    </div>

    <div class="ledger-pager">
        <button id="reportNewerBtn" class="mini-act-wide"><i class="fas fa-chevron-left"></i> নতুন</button>
        <span id="reportPageLabel" class="page-label">১ / ১</span>
        <button id="reportOlderBtn" class="mini-act-wide">পুরনো <i class="fas fa-chevron-right"></i></button>
    </div>
</div>
