<?php
/** History View — MVC template (fixed to match original schema + full UI) */
$from_date = $from_date ?? date('Y-m-01');
$to_date   = $to_date ?? date('Y-m-d');
$role      = $role ?? 'viewer';
$all_dates = $all_dates ?? [];
$daily_data = $daily_data ?? [];
$total_dates = count($all_dates);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>লেজার রিপোর্ট — Sada Kalo Fashion</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{max-width:100vw;overflow-x:hidden}

:root{
    --bg-void:#070b14; --bg-deep:#0a1020; --bg-card:#101c2e;
    --bg-nav:#090f1c;  --bg-el:#19253a;   --bg-inp:#0d1525;
    --cyan:#00c2ff;    --cyan-d:rgba(0,194,255,.12); --cyan-g:rgba(0,194,255,.3);
    --gold:#f0a500;    --ruby:#ff3d6e;    --amber:#ffb800;
    --violet:#a78bfa;  --sky:#38bdf8;     --white:#eaf0ff;
    --green:#10b981;   --green-g:rgba(16,185,129,.4);
    --np:#00c2ff; --nn:#ff3d6e; --nb:#f0a500;
    --nq:#38bdf8; --nw:#eaf0ff; --na:#ffb800; --nv:#a78bfa;
    --t1:#eaf0ff; --t2:#92a8cc; --tm:#4e657f;
    --b1:rgba(0,194,255,.09); --b2:rgba(255,255,255,.045); --b3:rgba(0,194,255,.22);
    --shd:0 8px 32px rgba(0,0,0,.5);
    --r1:8px; --r2:12px; --r3:16px;
    --font:'Outfit',sans-serif; --mono:'JetBrains Mono',monospace;
    --ease:cubic-bezier(.4,0,.2,1);
}
body.light-mode{
    --bg-void:#dde4ed; --bg-deep:#f0f4f8; --bg-card:#fff;
    --bg-nav:#fff; --bg-el:#dde4ed; --bg-inp:#f0f4f8;
    --b1:rgba(15,23,42,.12); --b2:rgba(15,23,42,.07); --b3:rgba(15,23,42,.28);
    --t1:#0f172a; --t2:#1e293b; --tm:#475569; --shd:0 4px 15px rgba(0,0,0,.07);
}
body.light-mode .stat-item,body.light-mode .balance-item{background:#1e293b!important;border-color:rgba(255,255,255,.08)!important}
body.light-mode .stat-label,body.light-mode .balance-label{color:#94a3b8!important}
body.light-mode .summary-panel{background:#1e293b!important;border-color:rgba(255,255,255,.08)!important}
body.light-mode .summary-title{color:#94a3b8!important}
body.light-mode .ds-box{background:#1e293b!important;border-color:rgba(255,255,255,.08)!important}
body.light-mode .ds-lbl{color:#94a3b8!important}
body.light-mode .ds-row{border-color:rgba(255,255,255,.06)!important}
body.light-mode .sec-name{color:#fff!important}
body.light-mode .date-day{color:#fff!important}
body.light-mode .dt td{color:#0f172a}
body.light-mode .dt th{background:rgba(0,0,0,.35)!important;color:rgba(255,255,255,.92)!important}
body.light-mode .dri{background:#1e293b;border-color:rgba(255,255,255,.08);color:#94a3b8}
body.light-mode .top-sum-box{background:#1e293b!important;border-color:rgba(255,255,255,.08)!important}
body.light-mode .top-sum-lbl{color:#94a3b8!important}

body{font-family:var(--font);background:var(--bg-deep);color:var(--t1);padding-bottom:90px;transition:background .3s,color .3s}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,194,255,.013) 1px,transparent 1px),linear-gradient(90deg,rgba(0,194,255,.013) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;z-index:0}
body.light-mode::before{display:none}
body::after{content:'';position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:300px;height:300px;background:url('logo.png') center/contain no-repeat;opacity:.03;pointer-events:none;z-index:0;filter:grayscale(1)}

/* Ticker */
.ticker{background:var(--bg-nav);border-bottom:1px solid var(--b1);padding:5px 0;overflow:hidden;white-space:nowrap;position:relative;z-index:1001}
.ticker::before,.ticker::after{content:'';position:absolute;top:0;bottom:0;width:50px;z-index:2}
.ticker::before{left:0;background:linear-gradient(to right,var(--bg-nav),transparent)}
.ticker::after{right:0;background:linear-gradient(to left,var(--bg-nav),transparent)}
.t-lbl{position:absolute;left:8px;top:50%;transform:translateY(-50%);z-index:3;background:var(--gold);color:#000;font-size:8px;font-weight:800;padding:2px 7px;border-radius:3px}
.t-txt{display:inline-block;padding-left:100%;animation:tickrun 22s linear infinite;font-size:10px;color:var(--t2);font-weight:600}
@keyframes tickrun{0%{transform:translateX(0)}100%{transform:translateX(-100%)}}

/* Navbar */
.navbar{display:flex;align-items:center;justify-content:space-between;background:var(--bg-nav);padding:0 14px;height:56px;position:sticky;top:0;z-index:1000;border-bottom:1px solid var(--b1)}
.navbar::after{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--cyan),transparent);opacity:.3}
.nv-l,.nv-r{display:flex;align-items:center;gap:8px}
.nv-c{flex:1;display:flex;align-items:center;justify-content:center;gap:10px}
.nv-back{width:34px;height:34px;border-radius:var(--r1);border:1px solid var(--b1);background:var(--bg-card);color:var(--t2);font-size:13px;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:all .2s}
.nv-back:hover{border-color:var(--cyan);color:var(--cyan);background:var(--cyan-d)}
.nv-logo{width:32px;height:32px;border-radius:8px;border:1.5px solid var(--b3);object-fit:cover}
.brand-t{font-size:13px;font-weight:800;letter-spacing:2px;text-transform:uppercase;background:linear-gradient(90deg,var(--cyan),#fff,var(--gold));background-size:200%;-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;animation:shimmer 4s linear infinite;line-height:1}
@keyframes shimmer{0%{background-position:0%}100%{background-position:200%}}
.brand-s{font-size:9px;font-weight:700;color:var(--tm);letter-spacing:2px;text-transform:uppercase;margin-top:2px;font-family:var(--mono)}
.ic-btn{width:32px;height:32px;border-radius:var(--r1);border:1px solid var(--b1);background:var(--bg-card);color:var(--t2);font-size:12px;display:flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;transition:all .2s}
.ic-btn:hover{border-color:var(--cyan);color:var(--cyan);background:var(--cyan-d)}

/* Layout */
.wrap{max-width:860px;margin:0 auto;padding:0 12px;position:relative;z-index:1}

/* Alert */
.alert{display:flex;align-items:center;justify-content:space-between;padding:9px 13px;border-radius:var(--r1);font-size:12px;font-weight:700;margin:10px 0}
.alert-ok{background:rgba(0,194,255,.1);border:1px solid rgba(0,194,255,.3);color:var(--cyan)}
.alert-err{background:rgba(255,61,110,.1);border:1px solid rgba(255,61,110,.3);color:var(--ruby)}
.alert-x{cursor:pointer;opacity:.6;font-size:15px}

/* Page header */
.ph{display:flex;align-items:center;justify-content:space-between;padding:12px 0 8px}
.ph-title{font-size:12px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:var(--t1);display:flex;align-items:center;gap:7px}
.ph-dot{width:6px;height:6px;border-radius:50%;background:var(--cyan)}
.ph-badge{font-family:var(--mono);font-size:9px;font-weight:700;color:var(--tm);background:var(--bg-card);border:1px solid var(--b1);border-radius:20px;padding:3px 11px}

/* ============================================================
   Top 4 Summary Boxes (চার্টের বদলে)
   ============================================================ */
.top-sum-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:12px}
@media(max-width:600px){.top-sum-grid{grid-template-columns:repeat(2,1fr)}}
.top-sum-box{background:var(--bg-card);border:1px solid var(--b1);border-radius:18px;padding:11px 8px;text-align:center;box-shadow:var(--shd);position:relative;overflow:hidden;transition:transform .15s}
.top-sum-box:hover{transform:translateY(-2px)}
.top-sum-box::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
.tsb-1::before{background:linear-gradient(90deg,var(--cyan),#0ea5e9)}
.tsb-2::before{background:linear-gradient(90deg,var(--ruby),#ff6b35)}
.tsb-3::before{background:linear-gradient(90deg,var(--amber),var(--gold))}
.tsb-4::before{background:linear-gradient(90deg,var(--green),#059669)}
.top-sum-ico{font-size:15px;margin-bottom:3px}
.tsb-1 .top-sum-ico{color:var(--cyan)} .tsb-2 .top-sum-ico{color:var(--ruby)}
.tsb-3 .top-sum-ico{color:var(--amber)} .tsb-4 .top-sum-ico{color:var(--green)}
.top-sum-lbl{font-size:7.5px;font-weight:800;color:var(--tm);letter-spacing:1px;text-transform:uppercase;margin-bottom:3px;font-family:var(--mono)}
.top-sum-val{font-size:12px;font-weight:900;font-family:var(--mono)}
.tsb-1 .top-sum-val{color:var(--cyan)} .tsb-2 .top-sum-val{color:var(--ruby)}
.tsb-3 .top-sum-val{color:var(--amber)} .tsb-4 .top-sum-val{color:var(--green)}

/* Filter */
.filter-box{display:none;background:var(--bg-card);border:1px solid var(--b1);border-radius:var(--r2);padding:14px;margin-bottom:12px;animation:pIn .25s var(--ease)}
@keyframes pIn{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:translateY(0)}}
.flbl{font-size:9px;font-weight:800;color:var(--tm);letter-spacing:1.5px;text-transform:uppercase;display:block;margin-bottom:4px;font-family:var(--mono)}
.finp{width:100%;background:var(--bg-inp);color:var(--t1);border:1px solid var(--b1);border-radius:var(--r1);padding:8px 12px;font-family:var(--font);font-size:12px;font-weight:700;outline:none;margin-bottom:8px;transition:all .2s;text-align:center}
.finp:focus{border-color:var(--cyan);box-shadow:0 0 0 3px var(--cyan-d)}
.btn-srch{width:100%;background:var(--cyan);color:#000;border:none;padding:10px;border-radius:var(--r1);font-family:var(--font);font-size:13px;font-weight:800;cursor:pointer;transition:all .2s}
.btn-srch:hover{background:#33d0ff}
.fq-row{display:flex;gap:5px;margin-top:8px}
.fqb{flex:1;padding:7px 0;border-radius:var(--r1);font-size:10px;font-weight:800;border:1px solid var(--b1);background:var(--bg-card);color:var(--t2);cursor:pointer;transition:all .18s;font-family:var(--font);text-align:center}
.fqb:hover{border-color:var(--cyan);color:var(--cyan);background:var(--cyan-d)}

/* Date range info */
.dri{display:flex;align-items:center;gap:8px;padding:8px 13px;margin-bottom:10px;background:var(--bg-card);border:1px solid var(--b1);border-radius:var(--r2);font-size:11px;font-weight:700;color:var(--t2);font-family:var(--mono)}
.dri i{color:var(--cyan);font-size:11px}
.dri-cnt{margin-left:auto;background:var(--cyan-d);color:var(--cyan);padding:2px 9px;border-radius:10px;font-size:9px;font-weight:900;border:1px solid var(--b3)}

/* Dual + Triple Summary */
.multi-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:12px}
@media(max-width:600px){.multi-row{grid-template-columns:1fr 1fr}}
@media(max-width:400px){.multi-row{grid-template-columns:1fr}}
.dual-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px}
@media(max-width:480px){.dual-row{grid-template-columns:1fr}}
.ds-box{background:var(--bg-card);border:1px solid var(--b1);border-radius:var(--r2);padding:12px;box-shadow:var(--shd);position:relative;overflow:hidden}
.ds-box::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
.ds-loan::before{background:linear-gradient(90deg,var(--ruby),#ff6b35)}
.ds-dps::before{background:linear-gradient(90deg,var(--amber),var(--cyan))}
.ds-card::before{background:linear-gradient(90deg,#1e3a8a,var(--cyan))}
.ds-ttl{font-size:9px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:8px;display:flex;align-items:center;gap:5px;font-family:var(--mono)}
.ds-loan .ds-ttl{color:var(--ruby)} .ds-dps .ds-ttl{color:var(--amber)} .ds-card .ds-ttl{color:var(--sky)}
.ds-row{display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid var(--b2)}
.ds-row:last-child{border-bottom:none}
.ds-lbl{font-size:9px;font-weight:700;color:var(--tm)}
.ds-val{font-size:12px;font-weight:900;font-family:var(--mono)}

/* Period Summary */
.summary-panel{background:var(--bg-card);border:1px solid var(--b1);border-radius:var(--r2);padding:12px;margin-bottom:12px;box-shadow:var(--shd)}
.summary-title{font-size:9px;font-weight:800;color:var(--tm);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:8px;display:flex;align-items:center;gap:5px;font-family:var(--mono)}
.summary-title::after{content:'';flex:1;height:1px;background:var(--b2)}
.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:5px;margin-bottom:8px}
@media(max-width:400px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
.stat-item{background:var(--bg-void);border:1px solid var(--b2);border-radius:var(--r1);padding:7px 4px;text-align:center}
.stat-label{font-size:7.5px;font-weight:800;color:var(--tm);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;font-family:var(--mono)}
.stat-value{font-size:12px;font-weight:800;font-family:var(--mono)}
.bal-row{display:grid;grid-template-columns:repeat(3,1fr);gap:5px;border-top:1px solid var(--b2);padding-top:8px}
@media(max-width:400px){.bal-row{grid-template-columns:repeat(2,1fr)}}
.balance-item{background:var(--bg-el);border:1px solid var(--b1);border-radius:var(--r1);padding:9px 6px;text-align:center}
.balance-label{font-size:7.5px;font-weight:800;color:var(--tm);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;font-family:var(--mono)}
.balance-value{font-size:14px;font-weight:900;font-family:var(--mono)}

/* Date Card */
.date-card{background:var(--bg-card);border:1px solid var(--b1);border-radius:var(--r3);margin-bottom:16px;overflow:hidden;box-shadow:var(--shd)}
.date-hdr{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:linear-gradient(135deg,#0a1628,#0d1f3c);border-bottom:2px solid var(--cyan)}
.date-hdr-l{display:flex;align-items:center;gap:8px}
.date-dot{width:8px;height:8px;border-radius:50%;background:var(--cyan);box-shadow:0 0 8px var(--cyan-g);flex-shrink:0}
.date-day{font-size:15px;font-weight:900;color:#fff;letter-spacing:.3px}
.date-wday{font-size:9px;font-weight:800;color:#fff;font-family:var(--mono);background:rgba(0,194,255,.2);padding:2px 9px;border-radius:4px;border:1px solid rgba(0,194,255,.35);text-transform:uppercase;letter-spacing:1px}

/* Date Card — Daily Summary Strip */
.date-strip{display:flex;gap:6px;padding:8px 12px;border-bottom:1px solid var(--b1);overflow-x:auto;scrollbar-width:none}
.date-strip::-webkit-scrollbar{display:none}
.ds-chip{flex:0 0 auto;display:flex;align-items:center;gap:4px;padding:4px 9px;border-radius:20px;font-size:9px;font-weight:800;font-family:var(--mono);border:1px solid}
.dsc-in{background:rgba(0,194,255,.1);border-color:rgba(0,194,255,.3);color:var(--cyan)}
.dsc-out{background:rgba(255,61,110,.1);border-color:rgba(255,61,110,.3);color:var(--ruby)}

/* Section Headers */
.sec-hdr{display:flex;align-items:center;gap:8px;padding:6px 12px;border:none;width:100%;margin:0}
.sec-ico{width:22px;height:22px;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff;flex-shrink:0;background:rgba(255,255,255,.2)}
.sec-name{font-size:10px;font-weight:900;letter-spacing:1.2px;text-transform:uppercase;color:#fff;flex:1;font-family:var(--font)}
.sec-badge{font-size:8px;font-weight:900;font-family:var(--mono);padding:2px 8px;border-radius:10px;background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.28)}

/* Unique banner colors */
.s-sale  {background:#065f46}
.s-col   {background:#0369a1}
.s-exp   {background:#991b1b}
.s-cust  {background:#4c1d95}
.s-sup   {background:#92400e}
.s-stk   {background:#0c4a6e}
.s-nstk  {background:#164e63}
.s-staff {background:#312e81}
.s-dps   {background:#7f1d1d}
.s-loan  {background:#713f12}
.s-card-out {background:#7c2d12}
.s-card-in  {background:#155e75}

/* Row tints */
.s-sale+.twrap{background:rgba(6,95,70,.05)}
.s-col+.twrap{background:rgba(3,105,161,.05)}
.s-exp+.twrap{background:rgba(153,27,27,.05)}
.s-cust+.twrap{background:rgba(76,29,149,.05)}
.s-sup+.twrap{background:rgba(146,64,14,.05)}
.s-stk+.twrap{background:rgba(12,74,110,.05)}
.s-nstk+.twrap{background:rgba(22,78,99,.05)}
.s-staff+.twrap{background:rgba(49,46,129,.05)}
.s-dps+.twrap{background:rgba(127,29,29,.05)}
.s-loan+.twrap{background:rgba(113,63,18,.05)}
.s-card-out+.twrap{background:rgba(124,45,18,.05)}
.s-card-in+.twrap{background:rgba(21,94,117,.05)}

/* Table */
.twrap{overflow-x:auto;padding-bottom:2px}
.twrap::-webkit-scrollbar{height:3px}
.twrap::-webkit-scrollbar-thumb{background:var(--b3);border-radius:10px}
.dt{width:100%;min-width:460px;border-collapse:collapse;font-size:11px}
.dt th{background:rgba(0,0,0,.3);color:rgba(255,255,255,.88);font-family:var(--mono);font-size:8px;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding:7px 6px;border-bottom:1px solid rgba(255,255,255,.1);text-align:center;white-space:nowrap}
.dt td{padding:6px 6px;border-bottom:1px solid rgba(255,255,255,.05);text-align:center;font-weight:700;color:var(--t1);vertical-align:middle;background:transparent}
.dt tbody tr:hover{background:rgba(255,255,255,.04)}
.dt tfoot td{background:rgba(0,0,0,.22);border-top:1px solid rgba(255,255,255,.1);font-family:var(--mono);font-size:10px;font-weight:800;padding:7px 6px;color:rgba(255,255,255,.78);text-align:center}

/* Card type badge */
.card-type-badge{display:inline-block;padding:2px 7px;border-radius:10px;font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.5px;font-family:var(--mono);border:1px solid}
.ctb-bill{background:rgba(16,185,129,.15);color:#10b981;border-color:rgba(16,185,129,.4)}
.ctb-min{background:rgba(250,204,21,.15);color:#facc15;border-color:rgba(250,204,21,.4)}
.ctb-full{background:rgba(14,165,233,.15);color:#0ea5e9;border-color:rgba(14,165,233,.4)}
.ctb-chg{background:rgba(245,158,11,.15);color:#f59e0b;border-color:rgba(245,158,11,.4)}
.ctb-adv{background:rgba(167,139,250,.15);color:#a78bfa;border-color:rgba(167,139,250,.4)}
.ctb-pur{background:rgba(255,61,110,.15);color:#ff3d6e;border-color:rgba(255,61,110,.4)}

/* Number helpers */
.np{color:var(--np)!important;font-family:var(--mono)}
.nn{color:var(--nn)!important;font-family:var(--mono)}
.nb{color:var(--nb)!important;font-family:var(--mono)}
.nq{color:var(--nq)!important;font-family:var(--mono)}
.nw{color:var(--nw)!important;font-family:var(--mono)}
.na{color:var(--na)!important;font-family:var(--mono)}
.nv{color:var(--nv)!important}
.fw9{font-weight:800!important}
.lft{text-align:left!important}
.mt{color:var(--tm)!important;font-size:10px}

/* Row animations */
@keyframes rDue{0%,100%{background:transparent}50%{background:rgba(255,184,0,.07)}}
@keyframes rPend{0%,100%{background:transparent}50%{background:rgba(0,194,255,.06)}}
.row-due{animation:rDue 2.5s ease-in-out infinite}
.row-pend{animation:rPend 2.5s ease-in-out infinite}

/* Misc */
.thumb{width:26px;height:26px;object-fit:cover;border-radius:5px;border:1px solid var(--b1);cursor:pointer;transition:transform .15s;display:block;margin:0 auto}
.thumb:hover{transform:scale(1.1)}
.ubadge{display:inline-block;background:var(--bg-el);border:1px solid var(--b3);border-radius:4px;padding:2px 5px;font-size:8px;font-weight:800;color:var(--tm);font-family:var(--mono)}
.abt{width:21px;height:21px;border:none;border-radius:4px;cursor:pointer;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:9px;margin:0 1px;transition:all .15s}
.abt:hover{transform:scale(1.1)}
.a-edit{background:rgba(240,165,0,.25);color:var(--gold);border:1px solid rgba(240,165,0,.4)}
.a-del{background:rgba(255,61,110,.25);color:var(--ruby);border:1px solid rgba(255,61,110,.4)}
.a-wa{background:rgba(34,197,94,.25);color:#22c55e;border:1px solid rgba(34,197,94,.4)}

.empty{text-align:center;padding:50px 20px;background:var(--bg-card);border:1px solid var(--b1);border-radius:var(--r3)}
.empty-ico{font-size:38px;color:var(--tm);margin-bottom:10px;display:block}
.empty-txt{font-size:13px;color:var(--tm);font-weight:700}

/* Image modal */
#imgModal{display:none;position:fixed;inset:0;z-index:10000;background:rgba(4,8,16,.92);backdrop-filter:blur(8px);align-items:center;justify-content:center}
#imgModal.show{display:flex}
#bigImg{max-width:88%;max-height:88%;border-radius:var(--r2);border:1px solid var(--b3);box-shadow:0 20px 60px rgba(0,0,0,.8)}

/* Password Modal */
.pw-modal{display:none;position:fixed;inset:0;z-index:9999;background:rgba(4,8,16,.92);backdrop-filter:blur(8px);align-items:center;justify-content:center}
.pw-modal.show{display:flex}
.pw-box{background:var(--bg-card);border:1px solid var(--ruby);border-radius:var(--r3);padding:22px;max-width:310px;width:90%;text-align:center}
.pw-ico{font-size:26px;color:var(--ruby);margin-bottom:10px;display:block}
.pw-ttl{font-size:13px;font-weight:800;color:var(--t1);margin-bottom:4px}
.pw-sub{font-size:10px;color:var(--tm);margin-bottom:12px;line-height:1.5}
.pw-inp{width:100%;background:var(--bg-inp);color:var(--t1);border:1px solid var(--b1);border-radius:var(--r1);padding:9px 12px;font-family:var(--font);font-size:12px;font-weight:700;outline:none;margin-bottom:6px;text-align:center;letter-spacing:2px}
.pw-inp:focus{border-color:var(--ruby);box-shadow:0 0 0 3px rgba(255,61,110,.15)}
.pw-err{font-size:10px;color:var(--ruby);min-height:14px;margin-bottom:8px;font-weight:700}
.pw-btns{display:flex;gap:8px}
.pw-cancel{flex:1;background:var(--bg-el);color:var(--t2);border:1px solid var(--b1);border-radius:var(--r1);padding:9px;font-family:var(--font);font-size:11px;font-weight:800;cursor:pointer}
.pw-ok{flex:1;background:var(--ruby);color:#fff;border:none;border-radius:var(--r1);padding:9px;font-family:var(--font);font-size:11px;font-weight:800;cursor:pointer}

/* Toast */
.toast-el{position:fixed;bottom:100px;left:50%;transform:translateX(-50%);z-index:99999;padding:9px 18px;border-radius:8px;font-size:12px;font-weight:800;color:#fff;box-shadow:0 4px 20px rgba(0,0,0,.4);animation:toastIn .3s var(--ease);white-space:nowrap}
@keyframes toastIn{from{opacity:0;transform:translateX(-50%) translateY(8px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}

/* Bottom Navigation Bar */
.bottom-nav{position:fixed;left:0;right:0;bottom:0;z-index:999;background:var(--bg-nav);border-top:1px solid var(--b1);box-shadow:0 -8px 24px rgba(0,0,0,.4);padding:8px 4px;backdrop-filter:blur(10px)}
.bottom-nav::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--cyan),transparent);opacity:.4}
.bn-row{display:flex;align-items:center;justify-content:space-around;max-width:860px;margin:0 auto;gap:2px}
.bn-btn{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;border:none;background:transparent;cursor:pointer;color:var(--t2);font-family:var(--font);font-weight:700;text-decoration:none;padding:6px 2px;border-radius:10px;transition:all .15s var(--ease);min-width:0}
.bn-btn:hover{background:var(--cyan-d);color:var(--cyan)}
.bn-btn:active{transform:scale(.95)}
.bn-icon{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;box-shadow:0 2px 0 rgba(0,0,0,.3),0 3px 6px rgba(0,0,0,.25);transition:all .15s}
.bn-btn:hover .bn-icon{transform:translateY(-2px)}
.bn-lbl{font-size:8px;font-weight:800;letter-spacing:.2px;text-align:center;line-height:1;color:var(--t2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:56px}
.bn-btn:hover .bn-lbl{color:var(--cyan)}
.bn-home .bn-icon{background:linear-gradient(135deg,#06b6d4,#0e7490)}
.bn-cust .bn-icon{background:linear-gradient(135deg,#a78bfa,#6d28d9)}
.bn-sup  .bn-icon{background:linear-gradient(135deg,#f59e0b,#b45309)}
.bn-stk  .bn-icon{background:linear-gradient(135deg,#0ea5e9,#1e40af)}
.bn-card .bn-icon{background:linear-gradient(135deg,#1e3a8a,#0c4a6e)}
.bn-filt .bn-icon{background:linear-gradient(135deg,#10b981,#047857)}
.bn-prnt .bn-icon{background:linear-gradient(135deg,#475569,#1e293b)}
@media(max-width:480px){
    .bn-icon{width:26px;height:26px;font-size:11px}
    .bn-lbl{font-size:7px}
}

@media print{.no-print{display:none!important}body{background:#fff;color:#000;padding-bottom:0}body::before,body::after{display:none}.bottom-nav{display:none}}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-thumb{background:var(--b3);border-radius:10px}

</style>
</head>
<body>

<!-- Ticker -->
<div class="ticker no-print">
    <span class="t-lbl">🤲 বিসমিল্লাহ</span>
    <span class="t-txt">🌿 بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ — পরম করুণাময় আল্লাহর নামে 🍃 &nbsp;&nbsp;&nbsp; আলহামদুলিল্লাহ — সমস্ত প্রশংসা আল্লাহর ❤️ &nbsp;&nbsp;&nbsp; সুবহানাল্লাহ 🍂</span>
</div>

<!-- Navbar -->
<nav class="navbar no-print">
    <div class="nv-l">
        <a href="../dashboard.php" class="nv-back"><i class="fas fa-home"></i></a>
    </div>
    <div class="nv-c">
        <img src="../logo.png" class="nv-logo" alt="SKF" onerror="this.style.display='none'">
        <div style="text-align:center">
            <div class="brand-t">SADA KALO FASHION</div>
            <div class="brand-s">লেজার রিপোর্ট</div>
        </div>
    </div>
    <div class="nv-r">
        <div class="ic-btn no-print" onclick="window.print()"><i class="fas fa-print"></i></div>
        <div class="ic-btn no-print" onclick="toggleTheme()"><i id="themeIco" class="fas fa-moon"></i></div>
    </div>
</nav>

<!-- Image Modal -->
<div id="imgModal" onclick="this.classList.remove('show')"><img id="bigImg"></div>

<!-- Password Modal -->
<div class="pw-modal" id="pwModal">
    <div class="pw-box">
        <i class="fas fa-shield-alt pw-ico"></i>
        <div class="pw-ttl" id="pwTitle">এন্ট্রি ডিলিট করবেন?</div>
        <div class="pw-sub" id="pwSub">Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small></div>
        <input type="password" id="pwInp" class="pw-inp" placeholder="••••••••" autocomplete="off">
        <div class="pw-err" id="pwErr"></div>
        <div class="pw-btns">
            <button class="pw-cancel" onclick="closePwModal()">বাতিল</button>
            <button class="pw-ok" id="pwOkBtn" onclick="pwConfirm()">নিশ্চিত করুন</button>
        </div>
    </div>
</div>

<div class="wrap">

<?php if (!empty($success_msg)): ?>
<div class="alert alert-ok no-print"><span><i class="fas fa-check-circle" style="margin-right:6px"></i><?= htmlspecialchars($success_msg) ?></span><span class="alert-x" onclick="this.parentElement.remove()">×</span></div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
<div class="alert alert-err no-print"><span><i class="fas fa-exclamation-circle" style="margin-right:6px"></i><?= htmlspecialchars($error_msg) ?></span><span class="alert-x" onclick="this.parentElement.remove()">×</span></div>
<?php endif; ?>

<div class="ph no-print">
    <div class="ph-title"><span class="ph-dot"></span> লেজার হিস্ট্রি</div>
    <div class="ph-badge"><?= htmlspecialchars($from_date) ?> → <?= htmlspecialchars($to_date) ?></div>
</div>

<!-- Top 4 Summary -->
<div class="top-sum-grid">
    <div class="top-sum-box tsb-1">
        <div class="top-sum-ico"><i class="fas fa-arrow-down"></i></div>
        <div class="top-sum-lbl">Period IN</div>
        <div class="top-sum-val">৳<?= number_format($total_in ?? 0) ?></div>
    </div>
    <div class="top-sum-box tsb-2">
        <div class="top-sum-ico"><i class="fas fa-arrow-up"></i></div>
        <div class="top-sum-lbl">Period OUT</div>
        <div class="top-sum-val">৳<?= number_format($total_out ?? 0) ?></div>
    </div>
    <div class="top-sum-box tsb-3">
        <div class="top-sum-ico"><i class="fas fa-balance-scale"></i></div>
        <div class="top-sum-lbl">Net Cash</div>
        <div class="top-sum-val" style="color:<?= ($net_cash ?? 0) >= 0 ? 'var(--amber)' : 'var(--ruby)' ?>">৳<?= number_format($net_cash ?? 0) ?></div>
    </div>
    <div class="top-sum-box tsb-4">
        <div class="top-sum-ico"><i class="fas fa-wallet"></i></div>
        <div class="top-sum-lbl">Closing Balance</div>
        <div class="top-sum-val" style="color:<?= ($closing ?? 0) >= 0 ? 'var(--green)' : 'var(--ruby)' ?>">৳<?= number_format($closing ?? 0) ?></div>
    </div>
</div>

<!-- Filter -->
<?php if ($role === 'admin'): ?>
<div class="filter-box" id="filterBox">
    <form method="GET" action="index.php">
        <label class="flbl">শুরুর তারিখ</label>
        <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="finp">
        <label class="flbl">শেষের তারিখ</label>
        <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="finp">
        <button type="submit" class="btn-srch"><i class="fas fa-search"></i> রেকর্ড খুঁজুন</button>
        <div class="fq-row">
            <button type="button" class="fqb" onclick="qDate(7)">৭ দিন</button>
            <button type="button" class="fqb" onclick="qDate(15)">১৫ দিন</button>
            <button type="button" class="fqb" onclick="qDate(30)">৩০ দিন</button>
            <button type="button" class="fqb" onclick="qMonth()">এই মাস</button>
            <button type="button" class="fqb" onclick="qAll()">সব</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Loan / DPS / Card dual boxes -->
<div class="multi-row no-print">
    <div class="ds-box ds-loan">
        <div class="ds-ttl"><i class="fas fa-university"></i> লোন সামারি</div>
        <div class="ds-row"><span class="ds-lbl">পিরিয়ডে নেওয়া (+)</span><span class="ds-val np">৳<?= number_format($gt_loan_in ?? 0) ?></span></div>
        <div class="ds-row"><span class="ds-lbl">পিরিয়ডে পরিশোধ (−)</span><span class="ds-val nn">৳<?= number_format($gt_loan_out ?? 0) ?></span></div>
        <div class="ds-row"><span class="ds-lbl">মোট বকেয়া</span><span class="ds-val nb">৳<?= number_format($loan_outstanding ?? 0) ?></span></div>
    </div>
    <div class="ds-box ds-dps">
        <div class="ds-ttl"><i class="fas fa-piggy-bank"></i> DPS সামারি</div>
        <div class="ds-row"><span class="ds-lbl">পিরিয়ডে জমা (−)</span><span class="ds-val nn">৳<?= number_format($gt_dps_dep ?? 0) ?></span></div>
        <div class="ds-row"><span class="ds-lbl">উত্তোলন (+)</span><span class="ds-val np">৳<?= number_format($gt_dps_wth ?? 0) ?></span></div>
        <div class="ds-row"><span class="ds-lbl">তহবিলে মোট জমা</span><span class="ds-val nb">৳<?= number_format($dps_total ?? 0) ?></span></div>
    </div>
    <div class="ds-box ds-card">
        <div class="ds-ttl"><i class="fas fa-credit-card"></i> কার্ড সামারি</div>
        <div class="ds-row"><span class="ds-lbl">পিরিয়ডে পেমেন্ট (−)</span><span class="ds-val nn">৳<?= number_format($gt_card_cash_out ?? 0) ?></span></div>
        <div class="ds-row"><span class="ds-lbl">কার্ড থেকে ক্যাশ (+)</span><span class="ds-val np">৳<?= number_format($gt_card_advance ?? 0) ?></span></div>
        <div class="ds-row"><span class="ds-lbl">মোট বকেয়া</span><span class="ds-val nb">৳<?= number_format($gt_card_outstanding ?? 0) ?></span></div>
    </div>
</div>

<!-- Period Summary Panel -->
<div class="summary-panel no-print">
    <div class="summary-title"><i class="fas fa-chart-pie" style="color:var(--cyan)"></i> Period Summary</div>
    <div class="stat-grid">
        <div class="stat-item"><div class="stat-label">Cash Sales</div><div class="stat-value np"><?= number_format($gt_sale_cash ?? 0) ?></div></div>
        <div class="stat-item"><div class="stat-label">Due Sales</div><div class="stat-value nn"><?= number_format($gt_sale_due ?? 0) ?></div></div>
        <div class="stat-item"><div class="stat-label">Collection</div><div class="stat-value np"><?= number_format($gt_coll ?? 0) ?></div></div>
        <div class="stat-item"><div class="stat-label">Expense</div><div class="stat-value nn"><?= number_format($gt_exp ?? 0) ?></div></div>
        <div class="stat-item"><div class="stat-label">Cust. Rcv</div><div class="stat-value np"><?= number_format($gt_cust_rcv ?? 0) ?></div></div>
        <div class="stat-item"><div class="stat-label">Sup. Paid</div><div class="stat-value nn"><?= number_format($gt_sup_pay ?? 0) ?></div></div>
        <div class="stat-item"><div class="stat-label">Staff Paid</div><div class="stat-value nn"><?= number_format($gt_staff ?? 0) ?></div></div>
        <div class="stat-item" style="border-color:rgba(0,194,255,.2)"><div class="stat-label">লোন নেওয়া (+)</div><div class="stat-value np"><?= number_format($gt_loan_in ?? 0) ?></div></div>
        <div class="stat-item" style="border-color:rgba(255,61,110,.2)"><div class="stat-label">লোন পরিশোধ (−)</div><div class="stat-value nn"><?= number_format($gt_loan_out ?? 0) ?></div></div>
        <div class="stat-item" style="border-color:rgba(255,61,110,.2)"><div class="stat-label">DPS জমা (−)</div><div class="stat-value nn"><?= number_format($gt_dps_dep ?? 0) ?></div></div>
        <div class="stat-item" style="border-color:rgba(0,194,255,.2)"><div class="stat-label">DPS উত্তোলন (+)</div><div class="stat-value np"><?= number_format($gt_dps_wth ?? 0) ?></div></div>
        <?php if (($card_active_count ?? 0) > 0 || ($gt_card_cash_out ?? 0) > 0): ?>
        <div class="stat-item" style="border-color:rgba(124,45,18,.4)"><div class="stat-label">কার্ড পেমেন্ট (−)</div><div class="stat-value nn"><?= number_format($gt_card_cash_out ?? 0) ?></div></div>
        <?php if (($gt_card_advance ?? 0) > 0): ?>
        <div class="stat-item" style="border-color:rgba(21,94,117,.4)"><div class="stat-label">কার্ড ক্যাশ (+)</div><div class="stat-value np"><?= number_format($gt_card_advance ?? 0) ?></div></div>
        <?php endif; endif; ?>
    </div>
    <div class="bal-row">
        <div class="balance-item"><div class="balance-label">Period IN</div><div class="balance-value np">৳ <?= number_format($total_in ?? 0) ?></div></div>
        <div class="balance-item"><div class="balance-label">Period OUT</div><div class="balance-value nn">৳ <?= number_format($total_out ?? 0) ?></div></div>
        <div class="balance-item" style="border-color:var(--b3)">
            <div class="balance-label">Net Cash</div>
            <div class="balance-value" style="color:<?= ($net_cash ?? 0) >= 0 ? 'var(--cyan)' : 'var(--ruby)' ?>">৳ <?= number_format($net_cash ?? 0) ?></div>
        </div>
    </div>
    <div class="bal-row" style="margin-top:5px">
        <div class="balance-item">
            <div class="balance-label">Opening Balance</div>
            <div class="balance-value nb">৳ <?= number_format($ob ?? 0) ?></div>
        </div>
        <div class="balance-item" style="grid-column:span 2;border-color:var(--b3)">
            <div class="balance-label">Closing Balance (Final)</div>
            <div class="balance-value" style="font-size:17px;color:<?= ($closing ?? 0) >= 0 ? 'var(--cyan)' : 'var(--ruby)' ?>">৳ <?= number_format($closing ?? 0) ?></div>
        </div>
    </div>
</div>

<?php if (empty($all_dates)): ?>
<div class="empty">
    <span class="empty-ico"><i class="fas fa-inbox"></i></span>
    <div class="empty-txt">এই সময়ে কোনো ডাটা পাওয়া যায়নি।</div>
</div>
<?php else: ?>

<div class="dri no-print">
    <i class="fas fa-calendar-alt"></i>
    <span><?= date('d M Y', strtotime($from_date)) ?> — <?= date('d M Y', strtotime($to_date)) ?></span>
    <span class="dri-cnt"><?= $total_dates ?> দিনের ডাটা</span>
</div>

<?php foreach ($all_dates as $cdate):
    $day = $daily_data[$cdate] ?? [];
    $sales   = $day['sales']   ?? [];
    $colls   = $day['colls']   ?? [];
    $exps    = $day['exps']    ?? [];
    $custT   = $day['custT']   ?? [];
    $supT    = $day['supT']    ?? [];
    $staffT  = $day['staffT']  ?? [];
    $dpsT    = $day['dpsT']    ?? [];
    $loanT   = $day['loanT']   ?? [];
    $cardOutT = $day['cardOut'] ?? [];
    $cardInT  = $day['cardIn']  ?? [];
    $ostkT   = $day['ostkT']   ?? [];
    $nstkT   = $day['stocks']  ?? [];

    $day_in = 0.0; $day_out = 0.0;
    foreach ($sales as $s)  { $day_in  += floatval($s['paid_amount'] ?? 0); }
    foreach ($colls as $c)  { $day_in  += floatval($c['total_deposit'] ?? 0); }
    foreach ($custT as $ct) { $day_in  += floatval($ct['received_amount'] ?? 0); }
    foreach ($exps as $e)   { $day_out += floatval($e['amount'] ?? 0); }
    foreach ($supT as $st)  { $day_out += floatval($st['payment_given'] ?? 0); }
    foreach ($staffT as $sf){ $day_out += floatval($sf['amount'] ?? 0); }
    foreach ($cardOutT as $co){ $day_out += abs(floatval($co['cash_impact'] ?? 0)); }
    foreach ($cardInT as $ci) { $day_in  += floatval($ci['cash_impact'] ?? 0); }
    foreach ($dpsT as $dl) {
        $dep = floatval($dl['deposit_amount'] ?? 0);
        $wth = floatval($dl['withdraw_amount'] ?? 0);
        if (!stristr($dl['description'] ?? '', 'মুনাফা') && !stristr($dl['description'] ?? '', 'Opening')) $day_out += $dep;
        $day_in += $wth;
    }
    foreach ($loanT as $ll) {
        if (!stristr($ll['description'] ?? '', 'মুনাফা')) $day_in += floatval($ll['debit_amount'] ?? 0);
        $day_out += floatval($ll['credit_amount'] ?? 0);
    }
    $day_net = $day_in - $day_out;
?>
<div class="date-card">
    <div class="date-hdr">
        <div class="date-hdr-l">
            <div class="date-dot"></div>
            <div class="date-day"><?= date('d M Y', strtotime($cdate)) ?></div>
        </div>
        <div class="date-wday"><?= date('l', strtotime($cdate)) ?></div>
    </div>

    <div class="date-strip no-print">
        <div class="ds-chip dsc-in"><i class="fas fa-arrow-up"></i> IN: ৳<?= number_format($day_in) ?></div>
        <div class="ds-chip dsc-out"><i class="fas fa-arrow-down"></i> OUT: ৳<?= number_format($day_out) ?></div>
        <div class="ds-chip" style="background:<?= $day_net>=0?'rgba(0,194,255,.1)':'rgba(255,61,110,.1)' ?>;border-color:<?= $day_net>=0?'rgba(0,194,255,.3)':'rgba(255,61,110,.3)' ?>;color:<?= $day_net>=0?'var(--cyan)':'var(--ruby)' ?>">
            <i class="fas fa-balance-scale"></i> Net: ৳<?= number_format($day_net) ?>
        </div>
    </div>

<?php if (!empty($sales)): $tb=0;$tp=0;$td=0;$tq=0; ?>
    <div class="sec-hdr s-sale">
        <div class="sec-ico"><i class="fas fa-shopping-cart"></i></div>
        <div class="sec-name">Sales Entry</div>
        <span class="sec-badge"><?= count($sales) ?> entries</span>
        <button onclick="exportCSV(this)" class="abt a-edit no-print" style="margin-left:auto;background:rgba(16,185,129,.25);color:#10b981;border-color:rgba(16,185,129,.4)" title="CSV"><i class="fas fa-file-download"></i></button>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th>MEMO</th><th>CUSTOMER</th><th>QTY</th><th>BILL</th><th>PAID</th><th>DUE</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($sales as $s): $tb+=$s['total_bill'];$tp+=$s['paid_amount'];$td+=$s['due_amount'];$tq+=$s['quantity']; ?>
        <tr class="<?= ($s['due_amount']??0)>0?'row-due':'' ?>">
            <td class="fw9 nw" style="font-family:var(--mono)"><?= htmlspecialchars((string)($s['memo_no']??'')) ?></td>
            <td class="lft"><?= htmlspecialchars($s['customer_name']??'') ?></td>
            <td class="nq fw9"><?= $s['quantity']??0 ?></td>
            <td class="nw" style="font-family:var(--mono)"><?= number_format($s['total_bill']??0) ?></td>
            <td class="np fw9"><?= number_format($s['paid_amount']??0) ?></td>
            <td class="nn fw9"><?= number_format($s['due_amount']??0) ?></td>
            <td><?= !empty($s['photo']) ? "<img src='".htmlspecialchars($s['photo'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>" ?></td>
            <td><span class="ubadge"><?= htmlspecialchars($s['entry_by']??'N/A') ?></span></td>
            <td class="no-print"><?php if ($role==='admin'): ?><button onclick="openPw('edit','sales_entries',<?= (int)$s['id'] ?>,'total_bill',<?= (float)$s['total_bill'] ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button><button onclick="openPw('delete','sales_entries',<?= (int)$s['id'] ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="nq"><?= $tq ?></td><td class="nw"><?= number_format($tb) ?></td><td class="np"><?= number_format($tp) ?></td><td class="nn"><?= number_format($td) ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
<?php endif; ?>

<?php if (!empty($colls)): $tc=0; ?>
    <div class="sec-hdr s-col">
        <div class="sec-ico"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="sec-name">Collection</div>
        <span class="sec-badge"><?= count($colls) ?> entries</span>
        <button onclick="exportCSV(this)" class="abt a-edit no-print" style="margin-left:auto;background:rgba(16,185,129,.25);color:#10b981;border-color:rgba(16,185,129,.4)" title="CSV"><i class="fas fa-file-download"></i></button>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th>CUSTOMER</th><th>CASH</th><th>BKASH</th><th>TOTAL</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($colls as $c): $tc+=$c['total_deposit']; ?>
        <tr>
            <td class="lft"><?= htmlspecialchars($c['payer_name']??'') ?></td>
            <td class="nw"><?= number_format($c['cash_amount']??0) ?></td>
            <td class="nw"><?= number_format($c['bkash_amount']??0) ?></td>
            <td class="np fw9"><?= number_format($c['total_deposit']??0) ?></td>
            <td><span class="ubadge"><?= htmlspecialchars($c['entry_by']??'N/A') ?></span></td>
            <td class="no-print"><?php if ($role==='admin'): ?><button onclick="openPw('edit','collection_entries',<?= (int)$c['id'] ?>,'total_deposit',<?= (float)$c['total_deposit'] ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button><button onclick="openPw('delete','collection_entries',<?= (int)$c['id'] ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="3" style="text-align:right">Sub-Total</td><td class="np"><?= number_format($tc) ?></td><td colspan="2"></td></tr></tfoot>
    </table></div>
<?php endif; ?>

<?php if (!empty($exps)): $te=0; ?>
    <div class="sec-hdr s-exp">
        <div class="sec-ico"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="sec-name">Expense Ledger</div>
        <span class="sec-badge"><?= count($exps) ?> entries</span>
        <button onclick="exportCSV(this)" class="abt a-edit no-print" style="margin-left:auto;background:rgba(16,185,129,.25);color:#10b981;border-color:rgba(16,185,129,.4)" title="CSV"><i class="fas fa-file-download"></i></button>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th>বিবরণ</th><th>পরিমাণ</th><th>ভাউচার</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($exps as $e): $te+=$e['amount']; ?>
        <tr>
            <td class="lft"><?= htmlspecialchars($e['description']??'') ?></td>
            <td class="nn fw9"><?= number_format($e['amount']??0) ?></td>
            <td><?= !empty($e['photo']) ? "<img src='".htmlspecialchars($e['photo'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>" ?></td>
            <td><span class="ubadge"><?= htmlspecialchars($e['entry_by']??'N/A') ?></span></td>
            <td class="no-print"><?php if ($role==='admin'): ?><button onclick="openPw('edit','expense_entries',<?= (int)$e['id'] ?>,'amount',<?= (float)$e['amount'] ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button><button onclick="openPw('delete','expense_entries',<?= (int)$e['id'] ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td style="text-align:right">Sub-Total</td><td class="nn"><?= number_format($te) ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
<?php endif; ?>

<?php if (!empty($staffT)): $tsf=0; ?>
    <div class="sec-hdr s-staff">
        <div class="sec-ico"><i class="fas fa-user-tie"></i></div>
        <div class="sec-name">Staff Expense</div>
        <span class="sec-badge"><?= count($staffT) ?> entries</span>
        <button onclick="exportCSV(this)" class="abt a-edit no-print" style="margin-left:auto;background:rgba(16,185,129,.25);color:#10b981;border-color:rgba(16,185,129,.4)" title="CSV"><i class="fas fa-file-download"></i></button>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">স্টাফ</th><th>নোট</th><th>সময়</th><th>পরিমাণ</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($staffT as $sf): $tsf+=$sf['amount']; ?>
        <tr>
            <td class="lft"><div class="np fw9"><?= htmlspecialchars($sf['name']??'') ?></div></td>
            <td class="mt"><?= htmlspecialchars($sf['note']??$sf['expense_type']??'—') ?></td>
            <td><span class="ubadge"><?= isset($sf['expense_time']) ? date('h:i A', strtotime($sf['expense_time'])) : '—' ?></span></td>
            <td class="nn fw9">৳ <?= number_format($sf['amount']??0) ?></td>
            <td><span class="ubadge"><?= htmlspecialchars($sf['entry_by']??'N/A') ?></span></td>
            <td class="no-print"><?php if ($role==='admin'): ?><button onclick="openPw('delete','staff_expenses',<?= (int)$sf['id'] ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="3" style="text-align:right">Sub-Total</td><td class="nn">৳ <?= number_format($tsf) ?></td><td colspan="2"></td></tr></tfoot>
    </table></div>
<?php endif; ?>

<?php if (!empty($custT)): $tcb=0;$tcr=0; ?>
    <div class="sec-hdr s-cust">
        <div class="sec-ico"><i class="fas fa-users"></i></div>
        <div class="sec-name">Customer Ledger</div>
        <span class="sec-badge"><?= count($custT) ?> entries</span>
        <button onclick="exportCSV(this)" class="abt a-edit no-print" style="margin-left:auto;background:rgba(16,185,129,.25);color:#10b981;border-color:rgba(16,185,129,.4)" title="CSV"><i class="fas fa-file-download"></i></button>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">গ্রাহক</th><th>বিবরণ</th><th>বিল</th><th>প্রাপ্ত</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($custT as $ct): $tcb+=$ct['bill_amount'];$tcr+=$ct['received_amount']; ?>
        <tr class="<?= ($ct['bill_amount']??0)>($ct['received_amount']??0)?'row-due':'' ?>">
            <td class="lft">
                <div class="nv fw9" style="font-size:11px"><?= htmlspecialchars($ct['shop_name']??'') ?></div>
                <div class="mt" style="font-size:9px;margin-top:1px"><?= htmlspecialchars($ct['customer_name']??'') ?></div>
            </td>
            <td class="mt"><?= htmlspecialchars($ct['description']??'N/A') ?></td>
            <td class="nn fw9"><?= ($ct['bill_amount']??0)>0 ? number_format($ct['bill_amount']) : '—' ?></td>
            <td class="np fw9"><?= ($ct['received_amount']??0)>0 ? number_format($ct['received_amount']) : '—' ?></td>
            <td><?= !empty($ct['image_path']) ? "<img src='".htmlspecialchars($ct['image_path'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>" ?></td>
            <td><span class="ubadge"><?= htmlspecialchars($ct['entry_by']??'N/A') ?></span></td>
            <td class="no-print">
                <a href="https://wa.me/88<?= htmlspecialchars($ct['phone']??'') ?>?text=<?= urlencode('তারিখ: '.date('d M Y',strtotime($cdate))."\nবিল: ৳".($ct['bill_amount']??0)."\nপ্রাপ্ত: ৳".($ct['received_amount']??0)) ?>" target="_blank" class="abt a-wa"><i class="fab fa-whatsapp"></i></a>
                <?php if ($role==='admin'): ?><button onclick="openPw('edit','customer_transactions',<?= (int)$ct['id'] ?>,'bill_amount',<?= (float)($ct['bill_amount']??0) ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button><button onclick="openPw('delete','customer_transactions',<?= (int)$ct['id'] ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="nn"><?= number_format($tcb) ?></td><td class="np"><?= number_format($tcr) ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
<?php endif; ?>

<?php if (!empty($supT)): $tsb=0;$tsp=0;$tsq=0; ?>
    <div class="sec-hdr s-sup">
        <div class="sec-ico"><i class="fas fa-truck"></i></div>
        <div class="sec-name">Supplier Ledger</div>
        <span class="sec-badge"><?= count($supT) ?> entries</span>
        <button onclick="exportCSV(this)" class="abt a-edit no-print" style="margin-left:auto;background:rgba(16,185,129,.25);color:#10b981;border-color:rgba(16,185,129,.4)" title="CSV"><i class="fas fa-file-download"></i></button>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">সাপ্লায়ার</th><th>মেমো</th><th>বিল</th><th>QTY</th><th>পেমেন্ট</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($supT as $st):
            $q = $st['quantity'] ?? $st['pcs'] ?? 0;
            $tsb+=$st['bill_received']??0; $tsp+=$st['payment_given']??0; $tsq+=$q;
        ?>
        <tr class="<?= ($st['bill_received']??0)>($st['payment_given']??0)?'row-pend':'' ?>">
            <td class="lft">
                <div class="na fw9"><?= htmlspecialchars($st['shop_name']??'') ?></div>
                <div class="mt" style="font-size:9px;margin-top:1px"><?= htmlspecialchars($st['name']??'') ?></div>
            </td>
            <td class="nw" style="font-family:var(--mono);font-size:10px"><?= htmlspecialchars($st['memo_no']??'') ?></td>
            <td class="nn fw9"><?= ($st['bill_received']??0)>0 ? number_format($st['bill_received']) : '—' ?></td>
            <td class="nq"><?= $q>0 ? $q : '—' ?></td>
            <td class="np fw9"><?= ($st['payment_given']??0)>0 ? number_format($st['payment_given']) : '—' ?></td>
            <td><?= !empty($st['photo']) ? "<img src='".htmlspecialchars($st['photo'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>" ?></td>
            <td><span class="ubadge"><?= htmlspecialchars($st['entry_by']??'N/A') ?></span></td>
            <td class="no-print"><?php if ($role==='admin'): ?><button onclick="openPw('edit','supplier_transactions',<?= (int)$st['id'] ?>,'bill_received',<?= (float)($st['bill_received']??0) ?>)" class="abt a-edit"><i class="fas fa-edit"></i></button><button onclick="openPw('delete','supplier_transactions',<?= (int)$st['id'] ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="nn"><?= number_format($tsb) ?></td><td class="nq"><?= $tsq ?></td><td class="np"><?= number_format($tsp) ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
<?php endif; ?>

<?php if (!empty($ostkT)): $osi=0;$oso=0;$osb=0; ?>
    <div class="sec-hdr s-stk">
        <div class="sec-ico"><i class="fas fa-boxes"></i></div>
        <div class="sec-name">Stock History</div>
        <span class="sec-badge"><?= count($ostkT) ?> entries</span>
        <button onclick="exportCSV(this)" class="abt a-edit no-print" style="margin-left:auto;background:rgba(16,185,129,.25);color:#10b981;border-color:rgba(16,185,129,.4)" title="CSV"><i class="fas fa-file-download"></i></button>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">বিবরণ</th><th>IN</th><th>OUT</th><th>বিল</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($ostkT as $sk):
            $inq=$sk['stock_in']??0; $outq=$sk['stock_out']??0; $osi+=$inq;$oso+=$outq;$osb+=$sk['total_bill']??0;
        ?>
        <tr>
            <td class="lft fw9"><?= htmlspecialchars($sk['description']??'') ?></td>
            <td class="np fw9"><?= $inq>0?$inq:'—' ?></td>
            <td class="nn fw9"><?= $outq>0?$outq:'—' ?></td>
            <td class="nb"><?= ($sk['total_bill']??0)>0 ? number_format($sk['total_bill']) : '—' ?></td>
            <td><?= !empty($sk['image']) ? "<img src='".htmlspecialchars($sk['image'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>" ?></td>
            <td><span class="ubadge"><?= htmlspecialchars($sk['entry_by']??'N/A') ?></span></td>
            <td class="no-print"><?php if ($role==='admin'): ?><button onclick="openPw('delete','stock_entries',<?= (int)$sk['id'] ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td style="text-align:right">Sub-Total</td><td class="np"><?= $osi ?></td><td class="nn"><?= $oso ?></td><td class="nb"><?= number_format($osb) ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
<?php endif; ?>

<?php if (!empty($nstkT)): $nsi=0;$nso=0;$nsb=0; ?>
    <div class="sec-hdr s-nstk">
        <div class="sec-ico"><i class="fas fa-cart-plus"></i></div>
        <div class="sec-name">Stock Added</div>
        <span class="sec-badge"><?= count($nstkT) ?> entries</span>
        <button onclick="exportCSV(this)" class="abt a-edit no-print" style="margin-left:auto;background:rgba(16,185,129,.25);color:#10b981;border-color:rgba(16,185,129,.4)" title="CSV"><i class="fas fa-file-download"></i></button>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">বিবরণ</th><th>IN</th><th>OUT</th><th>বিল</th><th>PIC</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($nstkT as $sk):
            $inq=$sk['in_qty']??0; $outq=$sk['out_qty']??0; $nsi+=$inq;$nso+=$outq;$nsb+=$sk['total_bill']??0;
        ?>
        <tr>
            <td class="lft fw9"><?= htmlspecialchars($sk['description']??'') ?></td>
            <td class="np fw9"><?= $inq>0?$inq:'—' ?></td>
            <td class="nn fw9"><?= $outq>0?$outq:'—' ?></td>
            <td class="nb"><?= ($sk['total_bill']??0)>0 ? number_format($sk['total_bill']) : '—' ?></td>
            <td><?= !empty($sk['image']) ? "<img src='".htmlspecialchars($sk['image'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>" ?></td>
            <td><span class="ubadge"><?= htmlspecialchars($sk['entry_by']??'N/A') ?></span></td>
            <td class="no-print"><?php if ($role==='admin'): ?><button onclick="openPw('delete','stocks',<?= (int)$sk['id'] ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td style="text-align:right">Sub-Total</td><td class="np"><?= $nsi ?></td><td class="nn"><?= $nso ?></td><td class="nb"><?= number_format($nsb) ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
<?php endif; ?>

<?php if (!empty($dpsT)): $ddt=0;$dwt=0; ?>
    <div class="sec-hdr s-dps">
        <div class="sec-ico"><i class="fas fa-piggy-bank"></i></div>
        <div class="sec-name">DPS / FDR লেজার</div>
        <span class="sec-badge"><?= count($dpsT) ?> entries</span>
        <button onclick="exportCSV(this)" class="abt a-edit no-print" style="margin-left:auto;background:rgba(16,185,129,.25);color:#10b981;border-color:rgba(16,185,129,.4)" title="CSV"><i class="fas fa-file-download"></i></button>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">A/C ও গ্রাহক</th><th>বিবরণ</th><th>জমা (+)</th><th>উত্তোলন (−)</th><th>ব্যালেন্স</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($dpsT as $dl):
            $dd=floatval($dl['deposit_amount']??0); $dw=floatval($dl['withdraw_amount']??0);
            $ddt+=$dd; $dwt+=$dw;
            $isO=stripos($dl['description']??'','Opening')!==false;
            $accNo=!empty($dl['account_number'])?$dl['account_number']:(($dl['account_type']??'').'-'.(1000+intval($dl['acc_id']??0)));
        ?>
        <tr>
            <td class="lft">
                <div class="np fw9" style="font-size:11px"><?= htmlspecialchars($accNo) ?></div>
                <div class="mt" style="font-size:9px;margin-top:1px"><?= htmlspecialchars($dl['client_name']??'') ?></div>
            </td>
            <td class="mt" style="font-size:10px"><?= htmlspecialchars($dl['description']??'') ?></td>
            <td class="np fw9"><?= $dd>0?'৳'.number_format($dd):'—' ?></td>
            <td class="nn fw9"><?= $dw>0?'৳'.number_format($dw):'—' ?></td>
            <td class="nb fw9">৳<?= number_format(floatval($dl['current_balance']??0)) ?></td>
            <td class="no-print"><?php if ($role==='admin' && !$isO): ?><button onclick="openPwDps(<?= (int)$dl['id'] ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="np">৳<?= number_format($ddt) ?></td><td class="nn">৳<?= number_format($dwt) ?></td><td colspan="2"></td></tr></tfoot>
    </table></div>
<?php endif; ?>

<?php if (!empty($loanT)): $ldt=0;$lct=0; ?>
    <div class="sec-hdr s-loan">
        <div class="sec-ico"><i class="fas fa-university"></i></div>
        <div class="sec-name">লোন লেজার (NGO / Bank)</div>
        <span class="sec-badge"><?= count($loanT) ?> entries</span>
        <button onclick="exportCSV(this)" class="abt a-edit no-print" style="margin-left:auto;background:rgba(16,185,129,.25);color:#10b981;border-color:rgba(16,185,129,.4)" title="CSV"><i class="fas fa-file-download"></i></button>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">গ্রাহক</th><th>বিবরণ</th><th>ডেবিট (+)</th><th>পরিশোধ (−)</th><th>ব্যালেন্স</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($loanT as $ll):
            $ld=floatval($ll['debit_amount']??0); $lc=floatval($ll['credit_amount']??0);
            $ldt+=$ld; $lct+=$lc;
        ?>
        <tr>
            <td class="lft">
                <div class="nn fw9" style="font-size:11px"><?= htmlspecialchars($ll['borrower_name']??'') ?></div>
                <div class="mt" style="font-size:9px;margin-top:1px"><?= strtoupper($ll['loan_category']??'') ?></div>
            </td>
            <td class="mt" style="font-size:10px"><?= htmlspecialchars($ll['description']??'') ?></td>
            <td class="nn fw9"><?= $ld>0?'৳'.number_format($ld):'—' ?></td>
            <td class="np fw9"><?= $lc>0?'−৳'.number_format($lc):'—' ?></td>
            <td class="nb fw9">৳<?= number_format(floatval($ll['balance']??0)) ?></td>
            <td class="no-print"><?php if ($role==='admin'): ?><button onclick="openPwLoan(<?= (int)$ll['id'] ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2" style="text-align:right">Sub-Total</td><td class="nn">৳<?= number_format($ldt) ?></td><td class="np">−৳<?= number_format($lct) ?></td><td colspan="2"></td></tr></tfoot>
    </table></div>
<?php endif; ?>

<?php if (!empty($cardOutT)): $cot=0; ?>
    <div class="sec-hdr s-card-out">
        <div class="sec-ico"><i class="fas fa-credit-card"></i></div>
        <div class="sec-name">ক্রেডিট কার্ড পেমেন্ট (−)</div>
        <span class="sec-badge"><?= count($cardOutT) ?> entries</span>
        <button onclick="exportCSV(this)" class="abt a-edit no-print" style="margin-left:auto;background:rgba(16,185,129,.25);color:#10b981;border-color:rgba(16,185,129,.4)" title="CSV"><i class="fas fa-file-download"></i></button>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">কার্ড</th><th>টাইপ</th><th>অ্যামাউন্ট</th><th>চার্জ</th><th>ক্যাশ কাটা</th><th>রিসিট</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($cardOutT as $co):
            $cot += abs(floatval($co['cash_impact']??0));
            $tlbl = ReportHelper::getCardTypeLabel($co['txn_type']??'');
            $tcls = ReportHelper::getCardTypeClass($co['txn_type']??'');
        ?>
        <tr>
            <td class="lft">
                <div class="fw9" style="font-size:11px;color:var(--sky)"><?= htmlspecialchars($co['card_name']??'') ?></div>
                <div class="mt" style="font-size:9px">**** <?= htmlspecialchars($co['card_last4']??'') ?></div>
            </td>
            <td><span class="card-type-badge <?= $tcls ?>"><?= htmlspecialchars($tlbl) ?></span></td>
            <td class="nn fw9">৳<?= number_format($co['amount']??0) ?></td>
            <td class="<?= ($co['charge_amount']??0)>0?'nn':'mt' ?>"><?= ($co['charge_amount']??0)>0?'৳'.number_format($co['charge_amount']):'—' ?></td>
            <td class="nn fw9">−৳<?= number_format(abs($co['cash_impact']??0)) ?></td>
            <td><?= !empty($co['receipt_image']) ? "<img src='".htmlspecialchars($co['receipt_image'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>" ?></td>
            <td><span class="ubadge"><?= htmlspecialchars($co['entry_by']??'admin') ?></span></td>
            <td class="no-print"><?php if ($role==='admin'): ?><button onclick="openPwCard(<?= (int)$co['id'] ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="4" style="text-align:right">মোট ক্যাশ কাটা</td><td class="nn">−৳<?= number_format($cot) ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
<?php endif; ?>

<?php if (!empty($cardInT)): $cit=0; ?>
    <div class="sec-hdr s-card-in">
        <div class="sec-ico"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="sec-name">ক্রেডিট কার্ড ক্যাশ (+)</div>
        <span class="sec-badge"><?= count($cardInT) ?> entries</span>
        <button onclick="exportCSV(this)" class="abt a-edit no-print" style="margin-left:auto;background:rgba(16,185,129,.25);color:#10b981;border-color:rgba(16,185,129,.4)" title="CSV"><i class="fas fa-file-download"></i></button>
    </div>
    <div class="twrap"><table class="dt">
        <thead><tr><th class="lft">কার্ড</th><th>টাইপ</th><th>অ্যামাউন্ট</th><th>চার্জ</th><th>ক্যাশ যোগ</th><th>রিসিট</th><th>BY</th><th class="no-print">ACT</th></tr></thead>
        <tbody>
        <?php foreach ($cardInT as $ci):
            $cit += floatval($ci['cash_impact']??0);
            $tlbl = ReportHelper::getCardTypeLabel($ci['txn_type']??'');
            $tcls = ReportHelper::getCardTypeClass($ci['txn_type']??'');
        ?>
        <tr>
            <td class="lft">
                <div class="fw9" style="font-size:11px;color:var(--sky)"><?= htmlspecialchars($ci['card_name']??'') ?></div>
                <div class="mt" style="font-size:9px">**** <?= htmlspecialchars($ci['card_last4']??'') ?></div>
            </td>
            <td><span class="card-type-badge <?= $tcls ?>"><?= htmlspecialchars($tlbl) ?></span></td>
            <td class="nw fw9">৳<?= number_format($ci['amount']??0) ?></td>
            <td class="<?= ($ci['charge_amount']??0)>0?'nn':'mt' ?>"><?= ($ci['charge_amount']??0)>0?'৳'.number_format($ci['charge_amount']):'—' ?></td>
            <td class="np fw9">+৳<?= number_format($ci['cash_impact']??0) ?></td>
            <td><?= !empty($ci['receipt_image']) ? "<img src='".htmlspecialchars($ci['receipt_image'])."' loading='lazy' class='thumb' onclick='showBig(this.src)'>" : "<span class='mt'>—</span>" ?></td>
            <td><span class="ubadge"><?= htmlspecialchars($ci['entry_by']??'admin') ?></span></td>
            <td class="no-print"><?php if ($role==='admin'): ?><button onclick="openPwCard(<?= (int)$ci['id'] ?>)" class="abt a-del"><i class="fas fa-trash"></i></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="4" style="text-align:right">মোট ক্যাশ যোগ</td><td class="np">+৳<?= number_format($cit) ?></td><td colspan="3"></td></tr></tfoot>
    </table></div>
<?php endif; ?>

</div><!-- /date-card -->
<?php endforeach; ?>
<?php endif; ?>

</div><!-- /wrap -->

<div class="bottom-nav no-print">
    <div class="bn-row">
        <a href="../dashboard.php" class="bn-btn bn-home">
            <div class="bn-icon"><i class="fas fa-home"></i></div>
            <span class="bn-lbl">ড্যাশবোর্ড</span>
        </a>
        <a href="../Customer/customers.php" class="bn-btn bn-cust">
            <div class="bn-icon"><i class="fas fa-users"></i></div>
            <span class="bn-lbl">কাস্টমার</span>
        </a>
        <a href="../Supplier/suppliers.php" class="bn-btn bn-sup">
            <div class="bn-icon"><i class="fas fa-truck"></i></div>
            <span class="bn-lbl">সাপ্লায়ার</span>
        </a>
        <a href="../Stock/index.php" class="bn-btn bn-stk">
            <div class="bn-icon"><i class="fas fa-boxes"></i></div>
            <span class="bn-lbl">স্টক</span>
        </a>
        <a href="../credit_card.php" class="bn-btn bn-card">
            <div class="bn-icon"><i class="fas fa-credit-card"></i></div>
            <span class="bn-lbl">কার্ড</span>
        </a>
        <button class="bn-btn bn-filt" onclick="toggleFilter()">
            <div class="bn-icon"><i class="fas fa-sliders-h"></i></div>
            <span class="bn-lbl">ফিল্টার</span>
        </button>
        <button class="bn-btn bn-prnt" onclick="window.print()">
            <div class="bn-icon"><i class="fas fa-print"></i></div>
            <span class="bn-lbl">প্রিন্ট</span>
        </button>
    </div>
</div>

<script>

// ── Theme ──
function toggleTheme(){
    document.body.classList.toggle('light-mode');
    const d=!document.body.classList.contains('light-mode');
    localStorage.setItem('theme',d?'dark':'light');
    document.getElementById('themeIco').className=d?'fas fa-sun':'fas fa-moon';
}
(function(){
    if(localStorage.getItem('theme')==='light'){
        document.body.classList.add('light-mode');
        const i=document.getElementById('themeIco');
        if(i)i.className='fas fa-moon';
    }
})();

// ── Image Zoom ──
function showBig(s){
    document.getElementById('bigImg').src=s;
    document.getElementById('imgModal').classList.add('show');
}

// ── Filter Toggle ──
function toggleFilter(){
    const b=document.getElementById('filterBox');
    if(!b)return;
    b.style.display=b.style.display==='block'?'none':'block';
}

// ── Date Quick Filter ──
function qDate(d){
    const t=new Date(),f=new Date();
    f.setDate(t.getDate()-(d-1));
    location.href='index.php?from_date='+fmt(f)+'&to_date='+fmt(t);
}
function qMonth(){
    const t=new Date(),f=new Date(t.getFullYear(),t.getMonth(),1);
    location.href='index.php?from_date='+fmt(f)+'&to_date='+fmt(t);
}
function qAll(){
    location.href='index.php?from_date=2020-01-01&to_date='+fmt(new Date());
}
function fmt(d){return d.toISOString().split('T')[0]}

// ── Password Modal State ──
let pwState={type:'',table:'',id:0,field:'',val:'',mode:''};

// ── General Edit/Delete (Sales, Expense, Supplier, Stock, Customer, Collection, Staff) ──
function openPw(type,table,id,field,val){
    pwState={type,table,id,field:field||'',val:val||'',mode:'item'};
    document.getElementById('pwTitle').textContent=type==='delete'?'এন্ট্রি ডিলিট করবেন?':'এন্ট্রি এডিট করবেন?';
    document.getElementById('pwSub').innerHTML='Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small>';
    document.getElementById('pwOkBtn').textContent=type==='delete'?'ডিলিট করুন':'আপডেট করুন';
    _openModal();
}

// ── DPS Delete ──
function openPwDps(id){
    pwState={type:'',table:'',id,field:'',val:'',mode:'dps'};
    document.getElementById('pwTitle').textContent='DPS এন্ট্রি ডিলিট করবেন?';
    document.getElementById('pwSub').innerHTML='Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small>';
    document.getElementById('pwOkBtn').textContent='ডিলিট করুন';
    _openModal();
}

// ── Loan Delete ──
function openPwLoan(id){
    pwState={type:'',table:'',id,field:'',val:'',mode:'loan'};
    document.getElementById('pwTitle').textContent='লোন এন্ট্রি ডিলিট করবেন?';
    document.getElementById('pwSub').innerHTML='Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small>';
    document.getElementById('pwOkBtn').textContent='ডিলিট করুন';
    _openModal();
}

// ── Credit Card Ledger Delete ──
function openPwCard(id){
    pwState={type:'',table:'',id,field:'',val:'',mode:'card'};
    document.getElementById('pwTitle').textContent='কার্ড এন্ট্রি ডিলিট করবেন?';
    document.getElementById('pwSub').innerHTML='Admin পাসওয়ার্ড দিন<br><small style="color:var(--ruby)">ডাটাবেজ থেকে যাচাই হবে</small>';
    document.getElementById('pwOkBtn').textContent='ডিলিট করুন';
    _openModal();
}

function _openModal(){
    document.getElementById('pwInp').value='';
    document.getElementById('pwErr').textContent='';
    document.getElementById('pwModal').classList.add('show');
    setTimeout(()=>document.getElementById('pwInp').focus(),200);
}
function closePwModal(){document.getElementById('pwModal').classList.remove('show')}

// ── Password Confirm & AJAX ──
function pwConfirm(){
    const pass=document.getElementById('pwInp').value.trim();
    const btn=document.getElementById('pwOkBtn');
    const err=document.getElementById('pwErr');
    if(!pass){err.textContent='পাসওয়ার্ড লিখুন।';return}

    if(pwState.mode==='item' && pwState.type==='edit'){
        const nv=prompt('নতুন মান লিখুন (বর্তমান: '+pwState.val+'):',pwState.val);
        if(nv===null){return}
        pwState.newVal=nv;
    }

    btn.textContent='যাচাই হচ্ছে…';btn.disabled=true;

    const fd=new FormData();
    if(pwState.mode==='item'){
        fd.append('ajax_action','item_action');
        fd.append('type',pwState.type);
        fd.append('table',pwState.table);
        fd.append('id',pwState.id);
        fd.append('field',pwState.field);
        fd.append('val',pwState.newVal||pwState.val);
    } else if(pwState.mode==='dps'){
        fd.append('ajax_action','delete_dps');
        fd.append('id',pwState.id);
    } else if(pwState.mode==='loan'){
        fd.append('ajax_action','delete_loan');
        fd.append('id',pwState.id);
    } else if(pwState.mode==='card'){
        fd.append('ajax_action','delete_card_ledger');
        fd.append('id',pwState.id);
    }
    fd.append('pass',pass);

    fetch('ajax_handler.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(res=>{
            btn.textContent='নিশ্চিত করুন';btn.disabled=false;
            if(res.status==='success'){
                closePwModal();
                showToast(res.message,true);
                setTimeout(()=>location.reload(),1200);
            } else {
                err.textContent=res.message;
            }
        })
        .catch(()=>{
            btn.textContent='নিশ্চিত করুন';btn.disabled=false;
            err.textContent='সার্ভার সমস্যা হয়েছে।';
        });
}

// ── Keyboard Support ──
document.addEventListener('keydown',e=>{
    if(e.key==='Enter' && document.getElementById('pwModal').classList.contains('show')) pwConfirm();
    if(e.key==='Escape' && document.getElementById('pwModal').classList.contains('show')) closePwModal();
    if(e.key==='Escape') document.getElementById('imgModal').classList.remove('show');
});

// ── Toast Notification ──
function showToast(msg,ok){
    const t=document.createElement('div');
    t.className='toast-el';
    t.textContent=msg;
    t.style.background=ok?'#0369a1':'#dc2626';
    document.body.appendChild(t);
    setTimeout(()=>t.remove(),2500);
}


// CSV Export (per section) — fixed to use correct date card
function exportCSV(btn) {
    const header = btn.closest('.sec-hdr');
    if (!header) return;
    const wrap = header.nextElementSibling;
    if (wrap && wrap.classList.contains('twrap')) {
        const table = wrap.querySelector('table.dt');
        if (!table) return;
        const csv = [];
        const rows = table.querySelectorAll('tr');
        for (const row of rows) {
            const cols = row.querySelectorAll('th, td');
            const rowData = [];
            for (const col of cols) {
                if (col.classList.contains('no-print')) continue;
                const text = col.innerText.trim();
                rowData.push('"' + text.replace(/"/g, '""') + '"');
            }
            if (rowData.length > 0) csv.push(rowData.join(','));
        }
        const blob = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.href = url;
        const ledgerName = header.querySelector('.sec-name')?.innerText.trim() || 'ledger';
        const card = header.closest('.date-card');
        const dateLabel = card?.querySelector('.date-day')?.innerText.trim() || 'report';
        link.download = `${ledgerName}_${dateLabel}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } else {
        alert('এই সেকশনে কোনো টেবিল নেই!');
    }
}
</script>
</body>
</html>
