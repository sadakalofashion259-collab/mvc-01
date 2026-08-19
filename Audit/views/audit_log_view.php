<?php
/**
 * public_html/Audit/views/audit_log_view.php
 *
 * Controller থেকে আসা variables:
 *   $result       → [ 'data'=>[], 'total'=>int, 'pages'=>int ]
 *   $filters      → sanitised filter array
 *   $modules      → string[]  (module dropdown)
 *   $actionCounts → ['CREATE'=>int, 'UPDATE'=>int, 'DELETE'=>int, 'EXPORT'=>int]
 */

$actionMeta = [
    'CREATE' => ['label' => 'তৈরি',      'color' => '#198754', 'icon' => 'plus-circle-fill'],
    'UPDATE' => ['label' => 'আপডেট',    'color' => '#fd7e14', 'icon' => 'pencil-fill'],
    'DELETE' => ['label' => 'ডিলিট',     'color' => '#dc3545', 'icon' => 'trash-fill'],
    'EXPORT' => ['label' => 'এক্সপোর্ট','color' => '#0d6efd', 'icon' => 'download'],
];

function auditUrl(array $f, int $page): string
{
    $q = array_filter(array_merge($f, ['page' => $page]), fn($v) => $v !== '' && $v !== null);
    return '/Audit/?' . http_build_query($q);
}

$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>সাদা কালো ফ্যাশন অডিট — Audit Log</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
/* ── Reset / base ── */
*, *::before, *::after { box-sizing: border-box; }
body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; font-size: .875rem; color: #1e293b; margin: 0; }

/* ── Topbar ── */
.topbar {
  background: #0f172a; padding: 13px 24px;
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 200;
  box-shadow: 0 2px 10px rgba(0,0,0,.4);
}
.topbar-brand { color: #fff; font-weight: 700; font-size: 1rem; text-decoration: none; display: flex; align-items: center; gap: 8px; }
.topbar-brand em { color: #38bdf8; font-style: normal; }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.topbar-user { color: #94a3b8; font-size: .8rem; }

/* ── Page content ── */
.wrap { padding: 24px; }

/* ── Cards ── */
.card { background: #fff; border: none; border-radius: 12px; box-shadow: 0 1px 6px rgba(0,0,0,.07); }
.card-hd { padding: 13px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 600; border-radius: 12px 12px 0 0; display: flex; align-items: center; justify-content: space-between; }
.card-bd { padding: 18px 20px; }

/* ── Stat boxes ── */
.stat-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 20px; }
@media(max-width:768px){ .stat-row { grid-template-columns: repeat(2,1fr); } }
.stat-box { border-radius: 12px; padding: 18px 20px; color: #fff; display: flex; align-items: center; gap: 14px; }
.stat-box .ico { font-size: 2rem; opacity: .8; }
.stat-box .num { font-size: 1.7rem; font-weight: 800; line-height: 1; }
.stat-box .lbl { font-size: .75rem; opacity: .85; margin-top: 3px; }

/* ── Filter ── */
.filter-form .form-control,
.filter-form .form-select { font-size: .82rem; border-radius: 8px; border-color: #cbd5e1; }
.filter-form .form-control:focus,
.filter-form .form-select:focus { border-color: #38bdf8; box-shadow: 0 0 0 3px rgba(56,189,248,.18); }
.btn-search { background: #0f172a; color: #fff; border: none; border-radius: 8px; padding: 7px 16px; }
.btn-search:hover { background: #1e293b; color: #fff; }
.btn-reset { border-radius: 8px; padding: 7px 12px; }

/* ── Table ── */
.audit-tbl { width: 100%; border-collapse: collapse; }
.audit-tbl thead th {
  background: #f8fafc; font-size: .72rem; text-transform: uppercase;
  letter-spacing: .06em; color: #64748b; padding: 10px 14px;
  border-bottom: 2px solid #e2e8f0; white-space: nowrap;
}
.audit-tbl tbody td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.audit-tbl tbody tr:last-child td { border-bottom: none; }
.audit-tbl tbody tr:hover { background: #f8fafc; }

/* আজকের নতুন রো — হালকা সবুজ */
.audit-tbl tbody tr.row-today {
  background: #f0fdf4;
}
.audit-tbl tbody tr.row-today:hover {
  background: #dcfce7;
}
.audit-tbl tbody tr.row-today td:first-child {
  border-left: 3px solid #22c55e;
}

/* ── Action badge ── */
.abadge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: .7rem; font-weight: 700; color: #fff; }

/* ── Eye button ── */
.btn-eye {
  width: 30px; height: 30px; border-radius: 8px;
  border: 1px solid #e2e8f0; background: #fff;
  display: inline-flex; align-items: center; justify-content: center;
  color: #64748b; cursor: pointer; transition: all .15s; font-size: .85rem;
}
.btn-eye:hover { background: #0f172a; color: #fff; border-color: #0f172a; }

/* ── Pagination ── */
.pg { display: flex; justify-content: center; padding: 14px; }
.pg-btn {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 32px; height: 32px; border-radius: 7px; margin: 0 2px;
  border: 1px solid #e2e8f0; background: #fff; color: #0f172a;
  font-size: .8rem; text-decoration: none; transition: all .12s;
}
.pg-btn:hover, .pg-btn.active { background: #0f172a; color: #fff; border-color: #0f172a; }
.pg-btn.disabled { pointer-events: none; color: #cbd5e1; }

/* ── Empty ── */
.empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
.empty i { font-size: 2.5rem; display: block; margin-bottom: 10px; }

/* ── Modal ── */
.modal-content { border: none; border-radius: 14px; }
.modal-header { background: #0f172a; border-radius: 14px 14px 0 0; border: none; }
.detail-row { display: flex; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
.detail-row:last-child { border-bottom: none; }
.dl { font-size: .7rem; font-weight: 700; color: #64748b; text-transform: uppercase; width: 130px; flex-shrink: 0; padding-top: 2px; }
.dv { font-size: .85rem; color: #1e293b; flex: 1; }
pre.json-box { background: #0f172a; color: #94a3b8; border-radius: 8px; padding: 12px; font-size: .75rem; max-height: 220px; overflow: auto; margin: 0; }

/* ── Diff colors ── */
.diff-wrap { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin-top: 6px; }
.diff-legend { display: flex; flex-wrap: wrap; gap: 10px; padding: 8px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: .72rem; font-weight: 600; }
.diff-legend span { display: inline-flex; align-items: center; gap: 5px; }
.diff-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
.diff-row { display: grid; grid-template-columns: 120px 1fr 1fr; gap: 0; border-bottom: 1px solid #f1f5f9; font-size: .8rem; }
.diff-row:last-child { border-bottom: none; }
.diff-key { padding: 8px 12px; background: #f8fafc; color: #64748b; font-weight: 700; font-size: .72rem; word-break: break-all; }
.diff-val { padding: 8px 12px; word-break: break-word; white-space: pre-wrap; }
.diff-val.same { color: #64748b; background: #fff; }
.diff-val.added { background: #dcfce7; color: #166534; font-weight: 600; }      /* সবুজ — নতুন */
.diff-val.changed { background: #fef9c3; color: #854d0e; font-weight: 600; }  /* হলুদ — এডিট */
.diff-val.removed { background: #fee2e2; color: #991b1b; font-weight: 600; }  /* লাল — ডিলিট */
.diff-hdr { display: grid; grid-template-columns: 120px 1fr 1fr; background: #0f172a; color: #94a3b8; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.diff-hdr span { padding: 7px 12px; }
@media (max-width: 576px) {
  .diff-row, .diff-hdr { grid-template-columns: 90px 1fr 1fr; }
  .diff-key, .diff-val, .diff-hdr span { padding: 6px 8px; font-size: .72rem; }
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <a class="topbar-brand" href="/index.php">
    <i class="bi bi-shield-check"></i>
    সাদা কালো <em>ফ্যাশন</em>
  </a>
  <div class="topbar-right">
    <span class="topbar-user">
      <i class="bi bi-person-circle me-1"></i><?= $h($_SESSION['username'] ?? '') ?>
    </span>
    <a href="/logout.php" class="btn btn-sm btn-outline-light" style="font-size:.78rem">
      <i class="bi bi-box-arrow-right me-1"></i>লগআউট
    </a>
  </div>
</div>

<div class="wrap">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h4 class="mb-1 fw-bold" style="font-size:1.35rem; color:#0f172a; letter-spacing:-0.02em;">
        <i class="bi bi-journal-text me-2 text-primary"></i>সাদা কালো ফ্যাশন অডিট
      </h4>
      <small class="text-muted" style="font-size:.8rem">
        সকল Create / Update / Delete / Export অ্যাকশনের রেকর্ড
        <?php if (!empty($filters['date_from']) && $filters['date_from'] === ($filters['date_to'] ?? '') && $filters['date_from'] === date('Y-m-d')): ?>
          · <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.72rem">আজকের লগ</span>
        <?php endif; ?>
      </small>
    </div>
    <a href="/Audit/?export=csv&<?= http_build_query(array_filter($filters)) ?>"
       class="btn btn-sm btn-outline-success">
      <i class="bi bi-filetype-csv me-1"></i>CSV Export
    </a>
  </div>

  <!-- Stats row -->
  <div class="stat-row">
    <?php
    $statStyles = [
      'CREATE' => 'linear-gradient(135deg,#198754,#20c997)',
      'UPDATE' => 'linear-gradient(135deg,#fd7e14,#ffc107)',
      'DELETE' => 'linear-gradient(135deg,#dc3545,#e35d6a)',
      'EXPORT' => 'linear-gradient(135deg,#0d6efd,#38bdf8)',
    ];
    foreach ($actionMeta as $key => $meta): ?>
    <div class="stat-box" style="background:<?= $statStyles[$key] ?>">
      <i class="bi bi-<?= $meta['icon'] ?> ico"></i>
      <div>
        <div class="num"><?= number_format($actionCounts[$key]) ?></div>
        <div class="lbl"><?= $meta['label'] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Filter card -->
  <div class="card mb-4">
    <div class="card-bd">
      <form method="GET" action="/Audit/" class="filter-form">
        <div class="row g-2 align-items-end">

          <div class="col-12 col-md-3">
            <label class="form-label mb-1 fw-semibold" style="font-size:.75rem">অনুসন্ধান</label>
            <input type="text" name="search" class="form-control"
                   placeholder="Username / Module / বিবরণ"
                   value="<?= $h($filters['search'] ?? '') ?>">
          </div>

          <div class="col-6 col-md-2">
            <label class="form-label mb-1 fw-semibold" style="font-size:.75rem">অ্যাকশন</label>
            <select name="action" class="form-select">
              <option value="">সব</option>
              <?php foreach ($actionMeta as $k => $m): ?>
                <option value="<?= $k ?>" <?= ($filters['action'] ?? '') === $k ? 'selected' : '' ?>>
                  <?= $m['label'] ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-2">
            <label class="form-label mb-1 fw-semibold" style="font-size:.75rem">মডিউল</label>
            <select name="module" class="form-select">
              <option value="">সব</option>
              <?php foreach ($modules as $mod): ?>
                <option value="<?= $h($mod) ?>" <?= ($filters['module'] ?? '') === $mod ? 'selected' : '' ?>>
                  <?= $h(ucfirst($mod)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-md-2">
            <label class="form-label mb-1 fw-semibold" style="font-size:.75rem">শুরু</label>
            <input type="date" name="date_from" class="form-control"
                   value="<?= $h($filters['date_from'] ?? '') ?>">
          </div>

          <div class="col-6 col-md-2">
            <label class="form-label mb-1 fw-semibold" style="font-size:.75rem">শেষ</label>
            <input type="date" name="date_to" class="form-control"
                   value="<?= $h($filters['date_to'] ?? '') ?>">
          </div>

          <div class="col-12 col-md-1 d-flex gap-1">
            <button type="submit" class="btn-search flex-fill">
              <i class="bi bi-search"></i>
            </button>
            <a href="/Audit/" class="btn btn-outline-secondary btn-reset">
              <i class="bi bi-x-lg"></i>
            </a>
          </div>

        </div>
      </form>
    </div>
  </div>

  <!-- Table card -->
  <div class="card">
    <div class="card-hd">
      <span>
        <i class="bi bi-list-ul me-1 text-primary"></i>
        মোট: <strong><?= number_format($result['total']) ?></strong> রেকর্ড
      </span>
      <small class="text-muted">
        পেজ <?= (int)($filters['page'] ?? 1) ?> / <?= max(1, $result['pages']) ?>
      </small>
    </div>

    <div class="table-responsive">
      <table class="audit-tbl">
        <thead>
          <tr>
            <th>#</th>
            <th>তারিখ / সময়</th>
            <th>ইউজার</th>
            <th>অ্যাকশন</th>
            <th>মডিউল</th>
            <th>Record ID</th>
            <th>বিবরণ</th>
            <th>IP</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($result['data'])): ?>
            <tr><td colspan="9"><div class="empty"><i class="bi bi-inbox"></i>কোনো লগ পাওয়া যায়নি।</div></td></tr>
          <?php else: foreach ($result['data'] as $row):
            $meta = $actionMeta[$row['action']] ?? ['label'=>$row['action'],'color'=>'#64748b'];
            $isToday = (date('Y-m-d', strtotime($row['created_at'])) === date('Y-m-d'));
          ?>
            <tr class="<?= $isToday ? 'row-today' : '' ?>">
              <td class="text-muted" style="font-size:.75rem"><?= (int)$row['id'] ?></td>
              <td style="white-space:nowrap">
                <div style="font-size:.8rem"><?= date('d M Y', strtotime($row['created_at'])) ?></div>
                <div class="text-muted" style="font-size:.73rem"><?= date('H:i:s', strtotime($row['created_at'])) ?></div>
              </td>
              <td><strong><?= $h($row['username'] ?? '—') ?></strong></td>
              <td>
                <span class="abadge" style="background:<?= $meta['color'] ?>">
                  <?= $meta['label'] ?>
                </span>
              </td>
              <td><?= $h(ucfirst($row['module'])) ?></td>
              <td class="text-muted" style="font-size:.78rem"><?= $h((string)($row['record_id'] ?? '—')) ?></td>
              <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                  title="<?= $h($row['description'] ?? '') ?>">
                <?= $h($row['description'] ?? '—') ?>
              </td>
              <td class="text-muted" style="font-size:.73rem"><?= $h($row['ip_address'] ?? '—') ?></td>
              <td>
                <button class="btn-eye js-eye" data-id="<?= (int)$row['id'] ?>" title="বিস্তারিত">
                  <i class="bi bi-eye-fill"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php
    $cur   = (int)($filters['page'] ?? 1);
    $total = max(1, $result['pages']);
    if ($total > 1):
      $range = 2;
    ?>
    <div class="pg">
      <?php if ($cur > 1): ?>
        <a class="pg-btn" href="<?= auditUrl($filters, $cur - 1) ?>"><i class="bi bi-chevron-left"></i></a>
      <?php endif; ?>

      <?php for ($p = 1; $p <= $total; $p++):
        if ($p === 1 || $p === $total || abs($p - $cur) <= $range): ?>
          <a class="pg-btn <?= $p === $cur ? 'active' : '' ?>" href="<?= auditUrl($filters, $p) ?>"><?= $p ?></a>
        <?php elseif (abs($p - $cur) === $range + 1): ?>
          <span class="pg-btn disabled">…</span>
        <?php endif;
      endfor; ?>

      <?php if ($cur < $total): ?>
        <a class="pg-btn" href="<?= auditUrl($filters, $cur + 1) ?>"><i class="bi bi-chevron-right"></i></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /wrap -->

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-white">
          <i class="bi bi-info-circle me-2"></i>Audit Log বিস্তারিত
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailBody">
        <div class="text-center py-5">
          <div class="spinner-border text-primary"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  'use strict';

  const modal  = new bootstrap.Modal(document.getElementById('detailModal'));
  const body   = document.getElementById('detailBody');

  const ACTIONS = {
    CREATE: { label:'তৈরি',      color:'#198754' },
    UPDATE: { label:'আপডেট',    color:'#fd7e14' },
    DELETE: { label:'ডিলিট',     color:'#dc3545' },
    EXPORT: { label:'এক্সপোর্ট',color:'#0d6efd' },
  };

  function esc(v) {
    return String(v ?? '—')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function drow(label, val) {
    return `<div class="detail-row"><span class="dl">${label}</span><span class="dv">${val}</span></div>`;
  }

  /** string / object → plain object (বা null) */
  function parseData(raw) {
    if (raw == null || raw === '') return null;
    if (typeof raw === 'object') return raw;
    try { return JSON.parse(raw); } catch (e) { return null; }
  }

  function fmtVal(v) {
    if (v === null || v === undefined) return '—';
    if (typeof v === 'object') return JSON.stringify(v, null, 2);
    return String(v);
  }

  function norm(v) {
    if (v === null || v === undefined) return '';
    if (typeof v === 'object') return JSON.stringify(v);
    // সংখ্যা "55800.00" vs 55800 সমান ধরা
    if (typeof v === 'number' || (typeof v === 'string' && v !== '' && !isNaN(Number(v)))) {
      const n = Number(v);
      if (!isNaN(n)) return String(n);
    }
    return String(v);
  }

  /**
   * রঙিন diff টেবিল
   *  সবুজ  = নতুন ফিল্ড / CREATE
   *  হলুদ  = পরিবর্তিত মান
   *  লাল   = মুছে যাওয়া ফিল্ড / DELETE
   */
  function renderDiff(oldRaw, newRaw, action) {
    const oldObj = parseData(oldRaw);
    const newObj = parseData(newRaw);

    // পার্স ব্যর্থ → পুরনো pre বক্স
    if (!oldObj && !newObj) {
      let fallback = '';
      if (oldRaw) fallback += `<p class="mb-1" style="font-size:.75rem;font-weight:700;color:#64748b">আগের ডেটা</p><pre class="json-box">${esc(oldRaw)}</pre>`;
      if (newRaw) fallback += `<p class="mb-1 mt-3" style="font-size:.75rem;font-weight:700;color:#64748b">পরের ডেটা</p><pre class="json-box">${esc(newRaw)}</pre>`;
      return fallback;
    }

    const o = oldObj || {};
    const n = newObj || {};
    const keys = Array.from(new Set([...Object.keys(o), ...Object.keys(n)])).sort();

    // CREATE → সব নতুন (সবুজ), DELETE → সব মুছে (লাল)
    const forceAdd = action === 'CREATE';
    const forceDel = action === 'DELETE';

    let rows = '';
    keys.forEach(function (k) {
      const hasO = Object.prototype.hasOwnProperty.call(o, k);
      const hasN = Object.prototype.hasOwnProperty.call(n, k);
      const ov = hasO ? o[k] : undefined;
      const nv = hasN ? n[k] : undefined;
      const same = hasO && hasN && norm(ov) === norm(nv);

      let clsO = 'same', clsN = 'same';
      if (forceAdd || (!hasO && hasN)) {
        clsN = 'added';
        clsO = 'same';
      } else if (forceDel || (hasO && !hasN)) {
        clsO = 'removed';
        clsN = 'same';
      } else if (!same) {
        clsO = 'changed';
        clsN = 'changed';
      }

      rows += `<div class="diff-row">
        <div class="diff-key">${esc(k)}</div>
        <div class="diff-val ${clsO}">${hasO ? esc(fmtVal(ov)) : '<span style="opacity:.4">—</span>'}</div>
        <div class="diff-val ${clsN}">${hasN ? esc(fmtVal(nv)) : '<span style="opacity:.4">—</span>'}</div>
      </div>`;
    });

    return `
      <div class="diff-legend">
        <span><i class="diff-dot" style="background:#22c55e"></i> নতুন / যোগ</span>
        <span><i class="diff-dot" style="background:#eab308"></i> পরিবর্তিত</span>
        <span><i class="diff-dot" style="background:#ef4444"></i> মুছে / ডিলিট</span>
      </div>
      <div class="diff-wrap">
        <div class="diff-hdr">
          <span>ফিল্ড</span>
          <span>আগে (Before)</span>
          <span>পরে (After)</span>
        </div>
        ${rows || '<div class="diff-val same" style="padding:14px;text-align:center">কোনো ফিল্ড নেই</div>'}
      </div>`;
  }

  function render(d) {
    const m = ACTIONS[d.action] ?? { label: d.action, color: '#64748b' };
    const badge = `<span style="background:${m.color};color:#fff;padding:2px 12px;
                   border-radius:20px;font-size:.72rem;font-weight:700">${m.label}</span>`;

    let html = `<div class="mb-3">
      ${drow('Log ID',       `<code>${esc(d.id)}</code>`)}
      ${drow('তারিখ / সময়', esc(d.created_at))}
      ${drow('ইউজার',        `<strong>${esc(d.username)}</strong> <span class="text-muted">(id: ${esc(d.user_id)})</span>`)}
      ${drow('অ্যাকশন',      badge)}
      ${drow('মডিউল',        esc(d.module))}
      ${drow('Record ID',    esc(d.record_id))}
      ${drow('IP Address',   esc(d.ip_address))}
      ${drow('বিবরণ',        esc(d.description))}
    </div>`;

    if (d.old_data || d.new_data) {
      html += `<p class="mb-1" style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase">ডেটা পরিবর্তন</p>`;
      html += renderDiff(d.old_data, d.new_data, d.action);
    } else {
      html += `<p class="text-muted mb-0" style="font-size:.82rem">
                 <i class="bi bi-info-circle me-1"></i>এই এন্ট্রিতে Data Snapshot নেই।
               </p>`;
    }
    return html;
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-eye');
    if (!btn) return;

    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    modal.show();

    fetch(`/Audit/?detail=${encodeURIComponent(btn.dataset.id)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(d  => { body.innerHTML = render(d); })
      .catch(err => {
        body.innerHTML = `<div class="alert alert-danger mb-0">লোড করা যায়নি (${err.message})</div>`;
      });
  });
})();
</script>
</body>
</html>
