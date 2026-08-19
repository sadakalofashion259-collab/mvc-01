<?php
declare(strict_types=1);
if (!defined('SK_APP')) { http_response_code(403); exit('403 Forbidden'); }
/** @var bool $ready; @var array $cfg,$suppliers,$templates,$recent,$stats; @var string $csrf,$role,$maskedKey */
$typeLabel = (($cfg['trans_type'] ?? 'T') === 'P') ? 'Promotional' : 'Transactional';
$e = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<meta name="csrf-token" content="<?= $e($csrf) ?>">
<title>SMS ড্যাশবোর্ড — Sada Kalo Fashion</title>
<link rel="icon" href="/assets/logo.png">
<link href="/assets/css/sk-sms.css" rel="stylesheet">
</head>
<body data-role="<?= $e($role) ?>">
<div class="wrap">

  <div class="bar">
    <a href="/Suppliers/suppliers.php" class="ibtn" title="ফিরে যান">&larr;</a>
    <div class="ttl">SMS ড্যাশবোর্ড</div>
  </div>

  <!-- Gateway status -->
  <div class="hero <?= $ready ? 'on' : 'off' ?>">
    <div class="hero-top">
      <div class="orb <?= $ready ? 'on' : 'off' ?>"><?= $ready ? '✓' : '!' ?></div>
      <div>
        <div class="prov">MiMSMS গেটওয়ে</div>
        <div class="state <?= $ready ? 'on' : 'off' ?>"><?= $ready ? 'সচল ও প্রস্তুত' : 'কনফিগার করা হয়নি' ?></div>
      </div>
    </div>
    <div class="grid2">
      <div class="cell"><span class="k">API Key</span><span class="v"><?= $maskedKey !== '' ? $e($maskedKey) : '— .env-এ বসান —' ?></span></div>
      <div class="cell"><span class="k">Sender ID</span><span class="v"><?= $e($cfg['sender'] ?? '—') ?></span></div>
      <div class="cell"><span class="k">ইউজারনেম</span><span class="v sm"><?= $e($cfg['username'] ?? '—') ?></span></div>
      <div class="cell"><span class="k">টাইপ</span><span class="v"><?= $e($typeLabel) ?></span></div>
    </div>
    <div class="note">🔒 API Key ও ইউজারনেম নিরাপদে <code>.env</code> ভল্টে থাকে — ডাটাবেজে নয়। Sender ID ও টাইপ নিচ থেকে এডিট করা যায়।</div>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="st"><b class="grn"><?= (int)($stats['enabled'] ?? 0) ?></b><span>SMS চালু</span></div>
    <div class="st"><b class="gry"><?= (int)($stats['disabled'] ?? 0) ?></b><span>বন্ধ</span></div>
    <div class="st"><b class="blu"><?= (int)($stats['total'] ?? 0) ?></b><span>সাপ্লায়ার</span></div>
    <div class="st"><b class="grn"><?= (int)($stats['sent'] ?? 0) ?></b><span>সফল</span></div>
    <div class="st"><b class="red"><?= (int)($stats['failed'] ?? 0) ?></b><span>ব্যর্থ</span></div>
  </div>

<?php if ($role === 'admin'): ?>
  <!-- Gateway editor (admin) -->
  <div class="sec">গেটওয়ে সেটিং</div>
  <div class="card">
    <label class="lbl">Sender ID</label>
    <input id="gwSender" class="inp" value="<?= $e($cfg['sender'] ?? '') ?>">
    <div class="row2">
      <div><label class="lbl">টাইপ</label>
        <select id="gwType" class="inp">
          <option value="T" <?= ($cfg['trans_type'] ?? 'T') === 'T' ? 'selected' : '' ?>>Transactional</option>
          <option value="P" <?= ($cfg['trans_type'] ?? 'T') === 'P' ? 'selected' : '' ?>>Promotional</option>
        </select>
      </div>
      <div><label class="lbl">অবস্থা</label>
        <select id="gwEnabled" class="inp">
          <option value="1" <?= !empty($cfg['enabled']) ? 'selected' : '' ?>>চালু</option>
          <option value="0" <?= empty($cfg['enabled']) ? 'selected' : '' ?>>বন্ধ</option>
        </select>
      </div>
    </div>
    <button class="btn" onclick="saveGateway(this)">গেটওয়ে সেভ করুন</button>
    <div id="gwMsg" class="fmsg"></div>
  </div>

  <!-- Template editor (admin) — ডাটাবেজ থেকে SMS বার্তা এডিট -->
  <div class="sec">SMS মেসেজ টেমপ্লেট</div>
  <div class="tpl-help">প্লেসহোল্ডার: <code>{shop}</code> <code>{date}</code> <code>{memo}</code> <code>{bill}</code> <code>{pay}</code> <code>{due}</code> <code>{user}</code></div>
  <?php foreach ($templates as $t): ?>
  <div class="card tpl" data-id="<?= (int)$t['id'] ?>">
    <div class="tpl-head">
      <input class="inp tpl-title" value="<?= $e($t['title']) ?>">
      <label class="sw"><input type="checkbox" class="tpl-active" <?= (int)$t['is_active'] === 1 ? 'checked' : '' ?>><span></span></label>
    </div>
    <div class="tpl-key"><?= $e($t['template_key']) ?></div>
    <textarea class="inp tpl-body" rows="6"><?= $e($t['body']) ?></textarea>
    <button class="btn sm" onclick="saveTemplate(this)">সেভ</button>
    <span class="tpl-msg fmsg"></span>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

  <!-- Test SMS -->
  <div class="sec">টেস্ট SMS</div>
  <div class="card">
    <input id="tPhone" class="inp" type="tel" placeholder="মোবাইল নম্বর (01XXXXXXXXX)">
    <textarea id="tMsg" class="inp" rows="3" placeholder="বার্তা লিখুন (খালি রাখলে ডিফল্ট টেস্ট বার্তা যাবে)"></textarea>
    <button class="btn grn-btn" id="tBtn" onclick="sendTest()" <?= $ready ? '' : 'disabled' ?>>
      <?= $ready ? 'টেস্ট SMS পাঠান' : 'গেটওয়ে কনফিগার করুন' ?>
    </button>
    <div id="tRes" class="fmsg"></div>
  </div>

  <!-- Per-supplier toggle -->
  <div class="sec">সাপ্লায়ার অনুযায়ী SMS <span class="cnt"><?= count($suppliers) ?></span></div>
  <?php if (empty($suppliers)): ?>
    <div class="empty">কোনো সাপ্লায়ার নেই।</div>
  <?php else: ?>
    <div class="search"><input id="supSearch" placeholder="দোকান বা নাম খুঁজুন..." oninput="filterSup()"></div>
    <div id="supList">
    <?php foreach ($suppliers as $sp):
      $sid = (int)$sp['id']; $on = (int)($sp['sms_enabled'] ?? 1) === 1;
      $hue = ($sid * 55) % 360; $letter = mb_substr((string)$sp['shop_name'], 0, 1, 'UTF-8'); ?>
      <div class="sup" data-search="<?= $e(mb_strtolower($sp['shop_name'].' '.$sp['name'].' '.$sp['phone'])) ?>">
        <div class="ava" style="background:hsl(<?= $hue ?>,48%,42%)"><?= $e($letter) ?></div>
        <div class="sup-m">
          <div class="sup-s"><?= $e($sp['shop_name']) ?></div>
          <div class="sup-u"><?= $e($sp['name']) ?> · <?= $e($sp['phone']) ?></div>
        </div>
        <span class="sup-st <?= $on ? 'on' : 'off' ?>" id="st-<?= $sid ?>"><?= $on ? 'চালু' : 'বন্ধ' ?></span>
        <button class="tgl <?= $on ? 'on' : '' ?>" id="tg-<?= $sid ?>" onclick="toggleSup(<?= $sid ?>,this)"></button>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Recent log -->
  <div class="sec">সাম্প্রতিক SMS <span class="cnt"><?= count($recent) ?></span></div>
  <?php if (empty($recent)): ?>
    <div class="empty">এখনো কোনো SMS পাঠানো হয়নি।</div>
  <?php else: ?>
    <div>
    <?php foreach ($recent as $lg):
      $st = ((string)($lg['status'] ?? '') === 'sent') ? 'sent' : 'failed';
      $shop = trim((string)($lg['shop_name'] ?? '')) !== '' ? (string)$lg['shop_name'] : 'টেস্ট / সরাসরি';
      $when = !empty($lg['created_at']) ? date('d M, g:i A', strtotime((string)$lg['created_at'])) : ''; ?>
      <div class="log">
        <span class="dot <?= $st ?>"></span>
        <div class="log-m"><div class="log-s"><?= $e($shop) ?></div><div class="log-p"><?= $e($lg['phone'] ?? '') ?></div></div>
        <div class="log-t"><span class="<?= $st ?>"><?= $st === 'sent' ? 'সফল' : 'ব্যর্থ' ?></span><br><?= $e($when) ?></div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>
<script src="/assets/js/sk-sms.js"></script>
</body>
</html>
