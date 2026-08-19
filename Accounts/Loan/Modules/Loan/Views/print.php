<?php
/**
 * প্রিন্ট স্টেটমেন্ট — একটি লোনের (নেওয়া) সম্পূর্ণ হিসাব।
 * Available: $loan, $totals, $ledger, $csrfToken, $baseUrl
 */
$e = static fn($v) => Security::e((string)$v);
$m = static fn($v) => Money::format((float)$v);

$totalDebit  = 0.0;
$totalCredit = 0.0;
foreach ($ledger as $row) {
    $totalDebit  += (float)$row['debit_amount'];
    $totalCredit += (float)$row['credit_amount'];
}
$statusLabel = ($loan['status'] ?? '') === 'active' ? 'চলমান' : 'পরিশোধিত';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>স্টেটমেন্ট — <?= $e($loan['borrower_name']) ?></title>
    <link rel="stylesheet" href="<?= $e($baseUrl) ?>/assets/vendor/fonts.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Hind Siliguri', sans-serif; color: #1a1a1a; background: #f5f5f5; padding: 20px; }
        .sheet { max-width: 800px; margin: 0 auto; background: #fff; padding: 32px; box-shadow: 0 2px 20px rgba(0,0,0,.1); }
        .head { text-align: center; border-bottom: 3px solid #6d5efc; padding-bottom: 16px; margin-bottom: 20px; }
        .head h1 { font-size: 1.5rem; color: #6d5efc; }
        .head p { font-size: .8rem; color: #666; margin-top: 4px; letter-spacing: 1px; }
        .meta { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; font-size: .85rem; }
        .meta .box { flex: 1; min-width: 200px; background: #f8f8fc; border: 1px solid #eee; border-radius: 8px; padding: 12px 14px; }
        .meta .box b { color: #6d5efc; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 24px; }
        .summary .card { border: 1px solid #eee; border-radius: 10px; padding: 14px; text-align: center; }
        .summary .card .l { font-size: .62rem; color: #888; text-transform: uppercase; font-weight: 700; letter-spacing: .5px; }
        .summary .card .v { font-size: 1.05rem; font-weight: 800; margin-top: 4px; }
        .c-red { color: #e11d48; } .c-green { color: #059669; } .c-purple { color: #7c3aed; } .c-blue { color: #2563eb; }
        table { width: 100%; border-collapse: collapse; font-size: .78rem; margin-bottom: 16px; }
        th, td { border: 1px solid #e5e5e5; padding: 8px 10px; text-align: left; }
        th { background: #6d5efc; color: #fff; font-weight: 700; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        tr:nth-child(even) td { background: #fafafa; }
        tfoot td { font-weight: 800; background: #f0f0f8; }
        .sign { display: flex; justify-content: space-between; margin-top: 50px; }
        .sign div { text-align: center; border-top: 1.5px solid #333; padding-top: 6px; width: 200px; font-size: .8rem; }
        .actions { text-align: center; margin: 20px 0; }
        .actions button { padding: 10px 22px; margin: 0 6px; border: none; border-radius: 8px; font-family: inherit;
            font-weight: 700; font-size: .85rem; cursor: pointer; color: #fff; background: #6d5efc; }
        .actions .sec { background: #64748b; }
        @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; max-width: 100%; } .actions { display: none; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="head">
        <h1>সাদা কালো — লোন স্টেটমেন্ট</h1>
        <p>SADA KALO LOAN MANAGEMENT</p>
    </div>

    <div class="meta">
        <div class="box">
            <div><b>পাওনাদার:</b> <?= $e($loan['borrower_name']) ?></div>
            <div><b>অ্যাকাউন্ট:</b> <?= $e($loan['account_number']) ?></div>
            <div><b>অবস্থা:</b> <?= $e($statusLabel) ?></div>
        </div>
        <div class="box">
            <div><b>সুদের হার:</b> <?= $e($loan['interest_rate']) ?>%</div>
            <div><b>কিস্তির ধরন:</b> <?= $e($loan['frequency']) ?> — <?= $e($loan['total_installments']) ?> কিস্তি</div>
            <div><b>পরবর্তী কিস্তি:</b> <?= $loan['due_date'] ? $e($loan['due_date']) : '—' ?></div>
        </div>
    </div>

    <div class="summary">
        <div class="card"><div class="l">কত টাকা নিলাম</div><div class="v c-blue"><?= $m($loan['principal_amount']) ?></div></div>
        <div class="card"><div class="l">কত শোধ করতে হবে</div><div class="v c-purple"><?= $m($loan['total_payable']) ?></div></div>
        <div class="card"><div class="l">এখনো বাকি</div><div class="v c-red"><?= $m($loan['current_balance']) ?></div></div>
        <div class="card"><div class="l">সুদ দিয়েছি</div><div class="v c-green"><?= $m($totals['interestPaid']) ?></div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th><th>তারিখ</th><th>বিবরণ</th>
                <th style="text-align:right">দায় (+)</th>
                <th style="text-align:right">শোধ (−)</th>
                <th style="text-align:right">বাকি</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($ledger)): ?>
                <tr><td colspan="6" style="text-align:center;color:#999;">কোনো লেনদেন নেই।</td></tr>
            <?php else: $i = 1; foreach ($ledger as $row): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= $e($row['txn_date']) ?></td>
                    <td><?= $e($row['description']) ?>
                        <?php if (!empty($row['note'])): ?><br><small style="color:#888;"><?= $e($row['note']) ?></small><?php endif; ?>
                    </td>
                    <td class="num"><?= (float)$row['debit_amount'] > 0 ? $m($row['debit_amount']) : '—' ?></td>
                    <td class="num"><?= (float)$row['credit_amount'] > 0 ? $m($row['credit_amount']) : '—' ?></td>
                    <td class="num"><?= $m($row['balance']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right">মোট</td>
                <td class="num"><?= $m($totalDebit) ?></td>
                <td class="num"><?= $m($totalCredit) ?></td>
                <td class="num"><?= $m($loan['current_balance']) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="sign">
        <div>ঋণগ্রহীতার স্বাক্ষর</div>
        <div>কর্তৃপক্ষের স্বাক্ষর</div>
    </div>

    <div class="actions">
        <button onclick="window.print()">প্রিন্ট করুন</button>
        <button class="sec" onclick="window.location.href='<?= $e($baseUrl) ?>/loan/dashboard'">ফিরে যান</button>
    </div>
</div>
</body>
</html>
