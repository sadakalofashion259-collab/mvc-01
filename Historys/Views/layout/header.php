<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>লেজার রিপোর্ট — Sada Kalo Fashion</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
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
    --font:'Hind Siliguri','Outfit',sans-serif;
    --mono:'JetBrains Mono',monospace;
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

.clean-text{font-family:'Hind Siliguri',sans-serif;}
.clean-text-bold{font-family:'Hind Siliguri',sans-serif;font-weight:700;}

body{background:var(--bg-void);color:var(--t1);font-family:var(--font);padding-bottom:90px;transition:background .25s,color .25s}

/* Ticker */
.ticker{background:linear-gradient(90deg,rgba(0,194,255,.08),rgba(240,165,0,.08));border-bottom:1px solid var(--b1);padding:6px 12px;display:flex;align-items:center;gap:8px;font-size:10px;font-weight:700;overflow:hidden;white-space:nowrap}
.t-lbl{color:var(--gold);flex-shrink:0}
.t-txt{color:var(--t2);animation:tickScroll 30s linear infinite}
@keyframes tickScroll{0%{transform:translateX(100%)}100%{transform:translateX(-100%)}}

/* Navbar */
.navbar{background:var(--bg-nav);border-bottom:1px solid var(--b1);padding:10px 14px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;backdrop-filter:blur(16px);box-shadow:var(--shd)}
.nv-l,.nv-r{display:flex;align-items:center;gap:6px}
.nv-c{display:flex;align-items:center;gap:10px}
.nv-back{width:32px;height:32px;background:var(--bg-el);border:1px solid var(--b1);border-radius:var(--r1);display:flex;align-items:center;justify-content:center;color:var(--cyan);font-size:13px;text-decoration:none;transition:all .15s}
.nv-back:hover{background:var(--cyan-d);transform:scale(1.05)}
.nv-logo{width:32px;height:32px;border-radius:8px;object-fit:cover;border:1px solid var(--b3)}
.brand-t{font-size:12px;font-weight:900;color:var(--t1);letter-spacing:.5px}
.brand-s{font-size:9px;color:var(--tm);font-weight:700;letter-spacing:1px}
.ic-btn{width:32px;height:32px;background:var(--bg-el);border:1px solid var(--b1);border-radius:var(--r1);display:flex;align-items:center;justify-content:center;color:var(--t2);font-size:12px;cursor:pointer;transition:all .15s}
.ic-btn:hover{color:var(--cyan);background:var(--cyan-d)}

/* Wrap */
.wrap{max-width:860px;margin:0 auto;padding:12px 10px}

/* Alert */
.alert{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:var(--r2);margin-bottom:10px;font-size:11px;font-weight:800;animation:fadeIn .3s var(--ease)}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.alert-ok{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#10b981}
.alert-err{background:rgba(255,61,110,.1);border:1px solid rgba(255,61,110,.3);color:var(--ruby)}
.alert-x{cursor:pointer;font-size:14px;opacity:.7}

/* Page Header */
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.ph-title{display:flex;align-items:center;gap:8px;font-size:16px;font-weight:900;color:var(--t1)}
.ph-dot{width:8px;height:8px;background:var(--cyan);border-radius:50%;box-shadow:0 0 8px var(--cyan)}
.ph-badge{font-size:9px;font-weight:800;color:var(--tm);background:var(--bg-el);border:1px solid var(--b1);border-radius:20px;padding:3px 10px;font-family:var(--mono)}

/* Top 4 Summary Boxes */
.top-sum-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:14px}
@media(max-width:560px){.top-sum-grid{grid-template-columns:repeat(2,1fr)}}
.top-sum-box{background:var(--bg-card);border:1px solid var(--b1);border-radius:var(--r2);padding:12px 10px;text-align:center;transition:transform .15s}
.top-sum-box:hover{transform:translateY(-2px)}
.tsb-1{border-top:2px solid var(--np)}
.tsb-2{border-top:2px solid var(--nn)}
.tsb-3{border-top:2px solid var(--amber)}
.tsb-4{border-top:2px solid var(--green)}
.top-sum-ico{font-size:18px;margin-bottom:4px}
.tsb-1 .top-sum-ico{color:var(--np)}
.tsb-2 .top-sum-ico{color:var(--nn)}
.tsb-3 .top-sum-ico{color:var(--amber)}
.tsb-4 .top-sum-ico{color:var(--green)}
.top-sum-lbl{font-size:8px;font-weight:800;color:var(--tm);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.top-sum-val{font-size:14px;font-weight:900;color:var(--t1);font-family:var(--mono)}

/* Filter Box */
.filter-box{background:var(--bg-card);border:1px solid var(--b1);border-radius:var(--r2);padding:14px;margin-bottom:14px;display:none}
.flbl{display:block;font-size:9px;font-weight:800;color:var(--tm);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;margin-top:8px}
.flbl:first-child{margin-top:0}
.finp{width:100%;background:var(--bg-inp);color:var(--t1);border:1px solid var(--b1);border-radius:var(--r1);padding:8px 10px;font-family:var(--font);font-size:12px;font-weight:700;outline:none;transition:border .15s}
.finp:focus{border-color:var(--cyan)}
.btn-srch{width:100%;margin-top:10px;background:var(--cyan);color:#000;border:none;border-radius:var(--r1);padding:10px;font-family:var(--font);font-size:12px;font-weight:900;cursor:pointer;transition:all .15s}
.btn-srch:hover{background:#00a8db;transform:scale(1.01)}
.fq-row{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.fqb{flex:1;min-width:60px;background:var(--bg-el);color:var(--t2);border:1px solid var(--b1);border-radius:var(--r1);padding:6px 4px;font-family:var(--font);font-size:10px;font-weight:800;cursor:pointer;transition:all .15s}
.fqb:hover{background:var(--cyan-d);color:var(--cyan);border-color:var(--cyan-g)}

/* Multi-row (Loan + DPS + Card) */
.multi-row{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px}
@media(max-width:600px){.multi-row{grid-template-columns:1fr}}
.ds-box{background:var(--bg-card);border:1px solid var(--b1);border-radius:var(--r2);padding:12px}
.ds-ttl{font-size:10px;font-weight:900;color:var(--t1);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.ds-row{display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--b1)}
.ds-row:last-child{border-bottom:none}
.ds-lbl{font-size:9px;font-weight:700;color:var(--tm)}
.ds-val{font-size:11px;font-weight:900;font-family:var(--mono)}
.ds-loan{border-top:2px solid var(--amber)}
.ds-dps{border-top:2px solid var(--ruby)}
.ds-card{border-top:2px solid var(--sky)}

/* Period Summary Panel */
.summary-panel{background:var(--bg-card);border:1px solid var(--b1);border-radius:var(--r2);padding:14px;margin-bottom:14px}
.summary-title{font-size:11px;font-weight:900;color:var(--t1);margin-bottom:10px;display:flex;align-items:center;gap:6px}
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px}
@media(max-width:480px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
.stat-item{background:var(--bg-el);border:1px solid var(--b1);border-radius:var(--r1);padding:8px;text-align:center}
.stat-label{font-size:8px;font-weight:800;color:var(--tm);text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px}
.stat-value{font-size:12px;font-weight:900;font-family:var(--mono)}
.balance-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:10px;padding-top:10px;border-top:1px solid var(--b1)}
@media(max-width:480px){.balance-grid{grid-template-columns:1fr}}
.balance-item{background:var(--bg-el);border:1px solid var(--b1);border-radius:var(--r1);padding:8px;text-align:center}
.balance-label{font-size:8px;font-weight:800;color:var(--tm);text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px}
.balance-value{font-size:13px;font-weight:900;font-family:var(--mono)}

/* Date Range Info */
.dri{background:var(--bg-card);border:1px solid var(--b1);border-radius:var(--r2);padding:10px 14px;margin-bottom:10px;display:flex;align-items:center;gap:10px;font-size:10px;font-weight:800;color:var(--t2)}
.dri-cnt{margin-left:auto;background:var(--bg-el);border:1px solid var(--b1);border-radius:20px;padding:2px 10px;font-size:9px;color:var(--cyan);font-family:var(--mono)}

/* Date Card */
.date-card{background:var(--bg-deep);border:1px solid var(--b1);border-radius:var(--r3);margin-bottom:16px;overflow:hidden;box-shadow:var(--shd)}
.date-hdr{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:linear-gradient(135deg,rgba(0,194,255,.06),rgba(167,139,250,.04));border-bottom:1px solid var(--b1)}
.date-hdr-l{display:flex;align-items:center;gap:8px}
.date-dot{width:8px;height:8px;background:var(--cyan);border-radius:50%;box-shadow:0 0 8px var(--cyan);flex-shrink:0}
.date-day{font-size:14px;font-weight:900;color:var(--t1)}
.date-wday{font-size:9px;font-weight:800;color:var(--tm);font-family:var(--mono);text-transform:uppercase;letter-spacing:1px}

/* Daily Cash Strip */
.date-strip{display:flex;gap:6px;flex-wrap:wrap;padding:8px 14px;background:rgba(0,0,0,.15);border-bottom:1px solid var(--b1)}
.ds-chip{display:flex;align-items:center;gap:4px;font-size:9px;font-weight:900;padding:4px 10px;border-radius:20px;border:1px solid;font-family:var(--mono)}
.dsc-in{background:rgba(0,194,255,.1);border-color:rgba(0,194,255,.3);color:var(--cyan)}
.dsc-out{background:rgba(255,61,110,.1);border-color:rgba(255,61,110,.3);color:var(--ruby)}

/* Section Header */
.sec-hdr{display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:11px;font-weight:900}
.sec-ico{width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:10px;color:#fff;flex-shrink:0}
.sec-name{color:#fff;font-weight:900;letter-spacing:.3px}
.sec-badge{font-size:8px;font-weight:900;font-family:var(--mono);padding:2px 8px;border-radius:10px;background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.28);margin-left:auto}

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
.s-card-out{background:#7c2d12}
.s-card-in {background:#155e75}

.s-sale+.twrap  {background:rgba(6,95,70,.05)}
.s-col+.twrap   {background:rgba(3,105,161,.05)}
.s-exp+.twrap   {background:rgba(153,27,27,.05)}
.s-cust+.twrap  {background:rgba(76,29,149,.05)}
.s-sup+.twrap   {background:rgba(146,64,14,.05)}
.s-stk+.twrap   {background:rgba(12,74,110,.05)}
.s-nstk+.twrap  {background:rgba(22,78,99,.05)}
.s-staff+.twrap {background:rgba(49,46,129,.05)}
.s-dps+.twrap   {background:rgba(127,29,29,.05)}
.s-loan+.twrap  {background:rgba(113,63,18,.05)}
.s-card-out+.twrap{background:rgba(124,45,18,.05)}
.s-card-in+.twrap {background:rgba(21,94,117,.05)}

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

/* Image Modal */
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

/* Bottom Navigation */
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
        <a href="../../dashboard.php" class="nv-back"><i class="fas fa-home"></i></a>
    </div>
    <div class="nv-c">
        <img src="../../logo.png" class="nv-logo" alt="SKF" onerror="this.src='https://via.placeholder.com/32?text=SK'">
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
<div id="imgModal" onclick="this.classList.remove('show')"><img id="bigImg" alt="receipt"></div>

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
