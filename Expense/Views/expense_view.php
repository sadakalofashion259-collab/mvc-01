<?php
$folder_data  = [];
$year_data    = [];
$current_year = date('Y');
foreach($folder_stats as $f) {
    if ($f['year'] == $current_year) { $folder_data[] = $f; } else { $year_data[$f['year']][] = $f; }
}
$folder_colors = ['#10a36e','#3a6ea5','#d39a2b','#7a5bb5','#0e9aa7','#13917d','#cf5e74','#c79a3e','#4e7d63','#2f8fa0','#9a7b3a','#b5607f'];
?>
<!DOCTYPE html>
<html lang="bn" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0a7d54">
    <title>MY SHOP EXPENSE LEDGER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,600&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
/* ============================================================
   MY SHOP EXPENSE LEDGER — "খাতা" Premium Emerald & Gold theme
   Bootstrap 5.3.8 + custom mobile-app skin (light / dark)
   ============================================================ */
:root{
  /* ---- SADA KALO — monochrome boutique-label palette + crimson thread accent ---- */
  --brand:#161616; --brand-deep:#000000; --brand-soft:#2e2e2e;
  --gold:#b3182f; --gold-deep:#87111f;   /* crimson thread — the single accent */
  --grad-brand:linear-gradient(150deg,#2e2e2e 0%,#000000 100%);
  --grad-gold:linear-gradient(150deg,#c8283f,#87111f);

  /* category tags — muted runway palette, no neon */
  --c-blue:#2b3a4a; --c-green:#374a3a; --c-amber:#7c5e28;
  --c-violet:#453551; --c-cyan:#2c4444; --c-teal:#2d443c; --c-rose:#7c2c3a;

  --pos:#374a3a;   /* saved */
  --neg:#b3182f;   /* expense amount — crimson */
  --warn:#7c5e28;
  --info:#2b3a4a;

  --font-disp:'Playfair Display','Hind Siliguri',serif;  /* boutique label serif */
  --font-mono:'JetBrains Mono',monospace;                  /* price-tag figures */

  --r-lg:2px; --r-md:2px; --r-sm:1px; --r-xs:1px; /* sharp label-card corners */
}

/* ---- LIGHT — white studio backdrop ---- */
[data-bs-theme="light"]{
  --app-bg:#fafaf8; --app-bg-2:#f0efec;
  --surface:#ffffff; --surface-2:#f5f4f1;
  --ink:#141414; --ink-soft:#3d3d3a; --muted:#8f8c85;
  --line:#dedad2; --line-soft:#ece9e2; --field:#f5f4f0;
  --hero-ink:#f7f5f0;
  --shadow:0 14px 30px -20px rgba(0,0,0,.28);
  --shadow-sm:0 7px 16px -12px rgba(0,0,0,.22);
  --card-ring:1px solid #e2ded4;
}

/* ---- DARK — atelier black ---- */
[data-bs-theme="dark"]{
  --app-bg:#0a0a0a; --app-bg-2:#050505;
  --surface:#141414; --surface-2:#1c1c1a;
  --ink:#f4f2ec; --ink-soft:#c9c6bd; --muted:#7a776e;
  --line:#2a2a26; --line-soft:#211f1c; --field:#101010;
  --hero-ink:#f7f4ec;
  --shadow:0 18px 36px -18px rgba(0,0,0,.72);
  --shadow-sm:0 10px 20px -14px rgba(0,0,0,.65);
  --card-ring:1px solid #262622;
}

* { -webkit-tap-highlight-color: transparent; }

body{
  font-family:'Hind Siliguri',system-ui,sans-serif;
  background:
    repeating-linear-gradient(115deg, transparent 0, transparent 26px, color-mix(in srgb,var(--line) 45%,transparent) 27px, transparent 28px),
    linear-gradient(180deg,var(--app-bg) 0%,var(--app-bg-2) 100%);
  background-attachment:fixed;
  color:var(--ink); margin:0; min-height:100vh; padding-bottom:112px;
  transition:background .4s ease, color .4s ease;
}

/* ---------- Shell ---------- */
.app-shell { width:100%; max-width:480px; margin:0 auto; padding:0 18px 0 26px; position:relative; }
/* crimson thread running down the spine — the tag-string motif */
.app-shell::before{
  content:""; position:absolute; top:0; bottom:0; left:14px; width:0;
  border-left:1.5px dashed color-mix(in srgb,var(--gold) 55%,transparent); pointer-events:none;
}
.app-shell::after{ content:""; display:none; }
@media print { .app-shell::before, .app-shell::after { display:none; } }

/* ---------- Marquee ---------- */
.ticker{
  background:#0a0a0a;
  color:#e8e4d8; font-size:12px; font-weight:600; font-family:var(--font-mono);
  overflow:hidden; white-space:nowrap; padding:8px 0; letter-spacing:.3px;
  border-bottom:2px solid var(--gold);
}
.ticker__t { display:inline-block; padding-left:100%; animation:ticker 22s linear infinite; }
@keyframes ticker { 0%{transform:translateX(0)} 100%{transform:translateX(-100%)} }

/* ---------- Hero (wallet card) ---------- */
.hero{
  position:relative; overflow:hidden;
  background:var(--grad-brand);
  border-radius:0; padding:18px 20px; color:var(--hero-ink);
  margin-top:12px; border:1px solid #000;
  box-shadow:0 16px 34px -24px rgba(0,0,0,.9);
  isolation:isolate;
}
.hero::before{
  content:""; position:absolute; inset:5px; z-index:-1; border:1px dashed color-mix(in srgb,var(--gold) 55%,transparent);
}
.hero::after{
  content:"SADA · KALO"; position:absolute; right:14px; top:10px; font-family:var(--font-disp);
  font-style:italic; font-size:11px; font-weight:600; letter-spacing:2px; color:color-mix(in srgb,var(--gold) 70%,transparent); z-index:1;
}
.hero__bar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:13px; }
.hero__brandrow{ display:flex; align-items:center; gap:13px; }
.hero__emblem{ width:46px; height:46px; flex:none; border-radius:0; background:var(--grad-gold); color:#fff; display:flex; align-items:center; justify-content:center; font-size:19px; box-shadow:0 8px 16px -9px rgba(0,0,0,.5); }
.hero__brandtext{ flex:1; min-width:0; }
.hero__hi { font-size:12.5px; font-weight:600; opacity:.85; margin:0; letter-spacing:.2px; }
.hero__title { font-family:var(--font-disp); font-style:italic; font-size:25px; font-weight:700; margin:2px 0 0; line-height:1.15; letter-spacing:.2px; }
.hero__date{
  display:inline-flex; align-items:center; gap:7px; white-space:nowrap; margin-top:14px;
  background:transparent; border:1px solid var(--gold);
  padding:6px 13px; border-radius:0; font-size:12px; font-weight:600; font-family:var(--font-mono); color:var(--gold);
}
.icon-btn{
  width:38px; height:38px; border-radius:0; border:1px solid rgba(255,255,255,.4);
  background:rgba(255,255,255,.16); color:#fff;
  display:flex; align-items:center; justify-content:center; font-size:17px; cursor:pointer;
  -webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px);
  transition:transform .18s ease, background .2s;
}
.icon-btn:hover { background:rgba(255,255,255,.26); }
.icon-btn:active { transform:scale(.9); }

/* ---------- Big centered entry button ---------- */
.entry-cta-wrap { display:flex; flex-direction:column; align-items:center; gap:10px; margin:24px 0 4px; }
.entry-cta{
  width:84px; height:84px; border-radius:0; border:2px solid var(--gold); cursor:pointer;
  background:var(--grad-brand); color:#fff;
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 16px 30px -16px rgba(0,0,0,.9);
  position:relative; transition:transform .2s ease;
}
.entry-cta::after{
  content:""; position:absolute; inset:-7px; border-radius:0;
  border:1px dashed color-mix(in srgb,var(--gold) 55%,transparent);
  animation:ctaRing 2.6s ease-out infinite;
}
@keyframes ctaRing { 0%{transform:scale(.85);opacity:.75} 100%{transform:scale(1.16);opacity:0} }
.entry-cta:active { transform:scale(.92); }
.entry-cta__ic { font-size:38px; line-height:1; }
.entry-cta__lbl { font-size:14px; font-weight:800; color:var(--ink); letter-spacing:.2px; }

/* ---------- Sidebar panel ---------- */
.side-mask{
  position:fixed; inset:0; background:rgba(6,12,9,.6); z-index:1200;
  opacity:0; visibility:hidden; transition:opacity .3s ease, visibility .3s ease;
  -webkit-backdrop-filter:blur(2px); backdrop-filter:blur(2px);
}
.side-mask.open { opacity:1; visibility:visible; }
.sidebar{
  position:fixed; top:0; left:0; height:100%; width:84%; max-width:330px; z-index:1300;
  background:var(--surface); border-right:2px solid var(--gold);
  box-shadow:24px 0 60px -28px rgba(0,0,0,.55);
  transform:translateX(-104%); transition:transform .32s cubic-bezier(.22,.61,.36,1);
  display:flex; flex-direction:column; padding:18px 16px 16px;
}
.sidebar.open { transform:translateX(0); }
.sidebar__head { display:flex; align-items:center; justify-content:space-between; padding-bottom:16px; border-bottom:1px solid var(--line); }
.sidebar__brand { display:flex; align-items:center; gap:12px; }
.sidebar__logo { width:42px; height:42px; border-radius:0; background:var(--grad-brand); color:#fff; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 8px 16px -10px rgba(0,0,0,.5); }
.sidebar__brand strong { display:block; font-family:var(--font-disp); font-style:italic; font-size:17px; font-weight:700; color:var(--ink); letter-spacing:.2px; }
.sidebar__brand small  { display:block; font-size:11px; color:var(--muted); font-weight:600; letter-spacing:.5px; text-transform:uppercase; }
.side-x { width:32px; height:32px; border-radius:0; border:none; background:var(--field); color:var(--ink); font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.sidebar__nav { display:flex; flex-direction:column; gap:8px; margin-top:16px; overflow-y:auto; flex:1; }
.side-link{
  display:flex; align-items:center; gap:14px; width:100%;
  background:var(--surface-2); border:1px solid var(--line-soft); border-left:2px solid transparent; border-radius:0;
  padding:12px 14px; cursor:pointer; text-decoration:none; text-align:left;
  transition:transform .15s ease, background .2s, border-color .2s;
}
.side-link:hover { transform:translateX(3px); border-left-color:var(--gold); }
.side-link:active { transform:scale(.98); }
.side-link__ic { width:36px; height:36px; flex:none; border-radius:0; color:#fff; display:flex; align-items:center; justify-content:center; font-size:14px; box-shadow:0 6px 12px -9px rgba(0,0,0,.5); }
.side-link__txt { flex:1; font-size:14px; font-weight:700; color:var(--ink); letter-spacing:.2px; }
.side-link__arr { color:var(--muted); font-size:12px; }
.sidebar__foot { border-top:1px dashed var(--line); padding-top:14px; margin-top:10px; display:flex; flex-direction:column; gap:12px; }
.side-theme { display:flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:12px; border:1px solid var(--gold); border-radius:0; background:transparent; color:var(--gold); font-weight:700; font-size:13px; cursor:pointer; letter-spacing:.5px; text-transform:uppercase; }
.sidebar__role { text-align:center; font-size:12px; color:var(--muted); font-weight:600; font-family:var(--font-mono); }
.sidebar__role strong { color:var(--ink); }

/* ---------- Section title ---------- */
.sec-head { display:flex; align-items:baseline; gap:11px; margin:24px 4px 13px; }
.sec-head h2 { font-family:var(--font-disp); font-style:italic; font-size:19px; font-weight:700; margin:0; color:var(--ink); white-space:nowrap; letter-spacing:.2px; }
.sec-head .dot { width:16px; height:2px; border-radius:0; background:var(--gold); align-self:center; }

/* ---------- Launcher tiles ---------- */
.tiles { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-top:4px; }
.tile{
  background:var(--surface); border:var(--card-ring); border-top:2px solid transparent; border-radius:0;
  padding:14px 6px 11px; display:flex; flex-direction:column; align-items:center; gap:8px;
  cursor:pointer; text-decoration:none; box-shadow:var(--shadow-sm);
  transition:transform .18s ease, box-shadow .2s ease, border-color .2s;
}
.tile:hover { transform:translateY(-2px); box-shadow:var(--shadow); border-top-color:var(--gold); }
.tile:active { transform:translateY(1px) scale(.98); }
.tile__ic{
  width:42px; height:42px; border-radius:0;
  display:flex; align-items:center; justify-content:center; color:#fff; font-size:17px;
  box-shadow:0 8px 14px -11px rgba(0,0,0,.5);
}
.tile__lbl { font-size:10.5px; font-weight:700; color:var(--ink-soft); text-align:center; line-height:1.1; letter-spacing:.2px; }
.bg-blue{background:var(--c-blue)} .bg-green{background:var(--c-green)}
.bg-amber{background:var(--c-amber)} .bg-violet{background:var(--c-violet)}
.bg-cyan{background:var(--c-cyan)} .bg-teal{background:var(--c-teal)}
.bg-rose{background:var(--c-rose)}

/* ---------- Quick menu (2-column launcher) ---------- */
.qmenu{ display:grid; grid-template-columns:repeat(2,1fr); gap:11px; margin-top:4px; }
.qtile{ display:flex; align-items:center; gap:12px; background:var(--surface); border:var(--card-ring); border-radius:0; border-left:2px solid var(--line); padding:13px 14px; cursor:pointer; text-decoration:none; box-shadow:var(--shadow-sm); transition:transform .18s ease, box-shadow .2s, border-color .2s; }
.qtile:hover{ transform:translateY(-2px); box-shadow:var(--shadow); border-left-color:var(--gold); }
.qtile:active{ transform:scale(.98); }
.qtile__ic{ width:38px; height:38px; flex:none; border-radius:0; color:#fff; display:flex; align-items:center; justify-content:center; font-size:15px; box-shadow:0 6px 12px -9px rgba(0,0,0,.5); }
.qtile__lbl{ font-size:13px; font-weight:700; color:var(--ink); line-height:1.15; font-family:var(--font-disp); font-style:italic; }

/* ---------- Card ---------- */
.card-soft{
  background:var(--surface); border:var(--card-ring); border-radius:0;
  box-shadow:var(--shadow); padding:18px; margin-bottom:16px; position:relative;
}
.card-soft::before{
  content:""; position:absolute; top:12px; left:-1px; width:7px; height:7px; border-radius:50%;
  background:var(--app-bg); border:1.5px solid var(--gold);
}
.card-soft__head{
  display:flex; align-items:center; gap:11px;
  margin:2px 0 15px; padding-bottom:14px; border-bottom:1px dashed var(--line);
}
.card-soft__head .badge-ic{
  width:36px; height:36px; border-radius:0; flex:none;
  display:flex; align-items:center; justify-content:center; color:#fff; font-size:15px;
}
.card-soft__head h3 { font-family:var(--font-disp); font-style:italic; font-size:17px; font-weight:700; margin:0; color:var(--ink); }
.card-soft__head small { display:block; font-size:11px; font-weight:600; color:var(--muted); letter-spacing:.3px; }

.collapsible { display:none; }
.collapsible.open { display:block; animation:slideIn .28s ease; }
@keyframes slideIn { from{opacity:0; transform:translateY(8px)} to{opacity:1; transform:none} }

/* ---------- Form ---------- */
.field-label { font-size:10.5px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; display:block; font-family:var(--font-mono); }
.form-control, .form-select{
  background:var(--field) !important; border:1.5px solid var(--line) !important; color:var(--ink) !important;
  border-radius:0 !important; padding:13px 14px !important; font-weight:600; font-size:15px;
}
.form-control::placeholder { color:var(--muted); font-weight:500; }
.form-control:focus, .form-select:focus{
  border-color:var(--gold) !important;
  box-shadow:0 0 0 1px var(--gold) !important;
}
.form-control[readonly] { opacity:.65; }
.amount-input { font-family:var(--font-mono); font-size:22px !important; font-weight:700 !important; color:var(--neg) !important; letter-spacing:.4px; }

/* ---------- Buttons ---------- */
.btn-pill{
  border:none; border-radius:0; padding:14px;
  font-weight:700; font-size:14px; width:100%; color:#fff; cursor:pointer;
  display:flex; align-items:center; justify-content:center; gap:8px;
  transition:transform .15s ease, filter .2s; letter-spacing:1px; text-transform:uppercase; font-family:'Hind Siliguri',sans-serif;
}
.btn-pill:active { transform:scale(.98); }
.btn-pill:hover { filter:brightness(1.15); }
.btn-save  { background:var(--grad-brand); box-shadow:0 12px 20px -14px rgba(0,0,0,.8); border-bottom:3px solid var(--gold); }
.btn-filter{ background:var(--grad-gold); color:#fff; box-shadow:0 12px 20px -14px rgba(179,24,47,.7); border-bottom:3px solid #000; }
.btn-add   { background:var(--c-violet); box-shadow:0 10px 18px -14px rgba(69,53,81,.9); white-space:nowrap; width:auto; padding:13px 18px; }

/* ---------- Camera ---------- */
.cam-box{
  border:2px dashed color-mix(in srgb,var(--gold) 55%,transparent);
  border-radius:0; padding:14px; text-align:center;
  background:var(--surface-2); margin-bottom:16px;
}
#webcam, #canvas { width:100%; border-radius:0; display:none; margin:0 auto 12px; }
.btn-cam{
  background:var(--brand); color:#fff; border:none; border-radius:0;
  padding:12px; font-weight:700; width:100%; cursor:pointer; font-size:13px; letter-spacing:.5px; text-transform:uppercase;
}

/* ---------- Day group / table ---------- */
.day-group { border:var(--card-ring); border-radius:0; overflow:hidden; margin-bottom:14px; background:var(--surface); border-left:2px solid var(--line); }
.day-head{
  background:var(--surface-2); padding:10px 14px;
  font-weight:700; font-size:11.5px; color:var(--ink); font-family:var(--font-mono);
  text-transform:uppercase; letter-spacing:.6px;
  display:flex; align-items:center; gap:8px; border-bottom:1px solid var(--gold);
}
.exp-table { width:100%; border-collapse:collapse; font-size:13px; }
.exp-table th { background:var(--field); padding:10px 12px; color:var(--muted); font-weight:700; font-size:10px; font-family:var(--font-mono); text-transform:uppercase; letter-spacing:.4px; text-align:left; border-bottom:1px solid var(--line); }
.exp-table td { padding:12px; border-bottom:1px dashed var(--line-soft); color:var(--ink); font-weight:600; vertical-align:top; }
.exp-table tr:last-child td { border-bottom:none; }
.exp-cat { font-size:13.5px; font-weight:700; color:var(--ink); font-family:var(--font-disp); font-style:italic; }
.exp-amt { font-family:var(--font-mono); color:var(--neg); font-weight:700; font-size:14px; white-space:nowrap; }
.exp-note { font-size:11px; color:var(--muted); margin-top:4px; font-style:italic; display:flex; gap:5px; align-items:flex-start; }
.exp-note i { font-size:9px; margin-top:3px; }
.exp-by { font-size:9px; color:var(--muted); background:var(--field); padding:2px 8px; border-radius:0; display:inline-block; margin-top:5px; font-weight:700; font-family:var(--font-mono); letter-spacing:.3px; }
.thumb { width:36px; height:36px; border-radius:0; object-fit:cover; cursor:pointer; border:1.5px solid var(--gold); }
.act-btn { background:none; border:none; cursor:pointer; font-size:16px; padding:6px; border-radius:0; transition:background .15s; }
.act-btn:hover { background:var(--field); }
.act-edit { color:var(--info); } .act-del { color:var(--neg); }

/* ---------- Filter total ---------- */
.total-bar{
  background:var(--grad-brand); color:#fff; border-radius:0;
  padding:15px 18px; margin-top:14px; border-left:3px solid var(--gold);
  display:flex; justify-content:space-between; align-items:center;
  font-weight:700; box-shadow:0 12px 22px -16px rgba(0,0,0,.7);
}
.total-bar span:last-child { font-family:var(--font-mono); font-size:16px; }

/* ---------- Folders ---------- */
.folder-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
.folder{
  position:relative; border-radius:0; padding:16px 6px;
  color:#fff; text-align:center; text-decoration:none;
  display:flex; flex-direction:column; justify-content:center; align-items:center; gap:6px;
  box-shadow:0 10px 18px -13px rgba(0,0,0,.5); cursor:pointer;
  transition:transform .2s; margin-top:11px; min-height:74px;
}
.folder::before { content:''; position:absolute; top:0; left:0; width:100%; height:2px; background:var(--gold); }
.folder:active { transform:translateY(2px); }
.folder-title { font-family:var(--font-disp); font-style:italic; font-size:11px; font-weight:700; text-transform:uppercase; line-height:1.1; }
.folder-amt { font-size:10px; font-weight:700; font-family:var(--font-mono); background:rgba(0,0,0,.3); padding:3px 8px; border-radius:0; }
.folder.year { background:linear-gradient(135deg,#2e2e2e,#0a0a0a) !important; border:1px solid var(--gold); }
.folder.year::before { background:var(--gold); }

/* ---------- Tabs (category modal) ---------- */
.seg { display:flex; border:1px solid var(--line); border-radius:0; overflow:hidden; margin-bottom:14px; }
.seg button { flex:1; padding:11px; border:none; cursor:pointer; font-weight:700; font-size:12px; background:var(--field); color:var(--muted); transition:.2s; display:flex; align-items:center; justify-content:center; gap:6px; letter-spacing:.5px; text-transform:uppercase; }
.seg button.on-green { background:var(--brand); color:#fff; }
.seg button.on-red { background:var(--neg); color:#fff; }
.count-pill { background:rgba(255,255,255,.25); border-radius:0; padding:1px 9px; font-size:11px; font-family:var(--font-mono); }

/* ---------- Modal ---------- */
.modal-mask { display:none; position:fixed; inset:0; background:rgba(0,0,0,.82); z-index:10000; align-items:center; justify-content:center; -webkit-backdrop-filter:blur(4px); backdrop-filter:blur(4px); padding:14px; }
.modal-mask.open { display:flex; }
.modal-sheet { background:var(--surface); border:1px solid var(--line); border-top:3px solid var(--gold); border-radius:0; width:100%; max-width:430px; padding:22px; position:relative; box-shadow:0 26px 50px -20px rgba(0,0,0,.7); max-height:88vh; overflow:auto; }
.modal-x { position:absolute; top:14px; right:16px; width:30px; height:30px; border-radius:0; border:none; background:var(--field); color:var(--neg); font-size:20px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.cat-row { display:flex; align-items:center; justify-content:space-between; padding:11px 6px; border-bottom:1px dashed var(--line-soft); }
.cat-row:last-child { border-bottom:none; }
.cat-name { font-weight:700; font-size:14px; color:var(--ink); }
.badge-off { font-size:9px; background:var(--neg); color:#fff; padding:2px 8px; border-radius:0; font-weight:700; margin-left:6px; }

/* ---------- FAB ---------- */
.fab{
  position:fixed; right:max(18px, calc(50% - 240px + 18px)); bottom:24px;
  width:56px; height:56px; border-radius:0; border:2px solid var(--gold);
  background:var(--grad-brand); color:#fff; font-size:22px; cursor:pointer;
  display:flex; align-items:center; justify-content:center; z-index:900;
  box-shadow:0 14px 26px -12px rgba(0,0,0,.85);
  transition:transform .2s; animation:fabPulse 2.8s ease-in-out infinite;
}
.fab:active { transform:scale(.9) rotate(45deg); }
@keyframes fabPulse { 0%,100%{box-shadow:0 14px 26px -12px rgba(0,0,0,.85),0 0 0 0 rgba(179,24,47,.4)} 60%{box-shadow:0 14px 26px -12px rgba(0,0,0,.85),0 0 0 12px rgba(179,24,47,0)} }

/* ---------- Empty / loading ---------- */
.empty-state { text-align:center; color:var(--muted); padding:26px 0; font-weight:700; font-family:var(--font-disp); font-style:italic; }
.empty-state i { font-size:34px; display:block; margin-bottom:10px; color:var(--line); }

.banner-img { width:100%; max-height:140px; object-fit:cover; display:block; border-radius:0; margin-top:14px; border:var(--card-ring); }

/* SweetAlert above everything */
.swal2-container { z-index:999999 !important; }

.foot-lottie { display:flex; justify-content:center; margin-top:10px; opacity:.9; }

/* ---------- Print ---------- */
@media print {
  .no-print, .fab, .ticker, .hero__tools, .hero__menu, .sidebar, .side-mask, .entry-cta-wrap, .foot-lottie { display:none !important; }
  body { background:#fff !important; padding:0 !important; }
  .collapsible { display:block !important; }
  .card-soft, .day-group { box-shadow:none !important; break-inside:avoid; }
}

/* ============================================================
   ss- namespaced additions: photo upload preview + actions
   ============================================================ */
.ss-photo-actions { display:flex; gap:8px; }
.ss-photo-actions .btn-cam { margin:0; flex:1; font-size:13px; padding:12px 8px; }
#photoPreview {
  width:100%; max-height:240px; object-fit:contain; border-radius:0;
  margin-bottom:12px; display:none; border:2px solid var(--gold);
}
    </style>
</head>
<body>

<div class="ticker"><div class="ticker__t">🕌 বিসমিল্লাহির রাহমানির রাহিম (بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ — পরম করুণাময় আল্লাহ যিনি আমাকে সৃষ্টি করেছেন) 🕌</div></div>

<div class="app-shell">

    <!-- ===== HERO / BANNER ===== -->
    <div class="hero">
        <div class="hero__bar no-print">
            <button onclick="openSidebar()" class="icon-btn" title="মেনু"><i class="fas fa-bars"></i></button>
            <button onclick="toggleTheme()" class="icon-btn" id="themeIcon" title="থিম পরিবর্তন"><i class="fas fa-sun"></i></button>
        </div>
        <div class="hero__brandrow">
            <span class="hero__emblem"><i class="fas fa-book-open"></i></span>
            <div class="hero__brandtext">
                <p class="hero__hi">আসসালামু আলাইকুম 👋</p>
                <h1 class="hero__title">খরচের খাতা</h1>
            </div>
        </div>
        <div class="hero__date"><i class="far fa-calendar-check"></i> <?php echo date('d M, Y', strtotime($today_date)); ?></div>
    </div>

    <!-- ===== QUICK MENU (2-column launcher) ===== -->
    <div class="sec-head"><span class="dot"></span><h2>দ্রুত মেনু</h2></div>
    <div class="qmenu">
        <button class="qtile" onclick="toggleView('entry-container')">
            <span class="qtile__ic bg-green"><i class="fas fa-plus"></i></span>
            <span class="qtile__lbl">নতুন এন্ট্রি</span>
        </button>
        <button class="qtile" onclick="toggleView('history-container')">
            <span class="qtile__ic bg-cyan"><i class="fas fa-clock-rotate-left"></i></span>
            <span class="qtile__lbl">খরচের হিস্ট্রি</span>
        </button>
        <?php if ($role === 'admin'): ?>
        <button class="qtile" onclick="toggleView('filter-container')">
            <span class="qtile__ic bg-amber"><i class="fas fa-magnifying-glass"></i></span>
            <span class="qtile__lbl">ফিল্টার</span>
        </button>
        <?php endif; ?>
        <button class="qtile" onclick="toggleFolders()">
            <span class="qtile__ic bg-teal"><i class="fas fa-folder-open"></i></span>
            <span class="qtile__lbl">মাসিক ফোল্ডার</span>
        </button>
    </div>

    <!-- ===== SIDEBAR PANEL ===== -->
    <div class="side-mask no-print" id="sideMask" onclick="closeSidebar()"></div>
    <aside class="sidebar no-print" id="sidebar">
        <div class="sidebar__head">
            <div class="sidebar__brand">
                <span class="sidebar__logo"><i class="fas fa-book"></i></span>
 <div>
    <div>
    </div> <strong>MY SHOP</strong>
                    <small>Expense Ledger</small>
                </div>
            </div>
            <button class="side-x" onclick="closeSidebar()"><i class="fas fa-xmark"></i></button>
        </div>

        <nav class="sidebar__nav">
            <button class="side-link" onclick="sideGo('entry-container')">
                <span class="side-link__ic bg-green"><i class="fas fa-plus"></i></span>
                <span class="side-link__txt">নতুন এন্ট্রি</span>
                <i class="fas fa-chevron-right side-link__arr"></i>
            </button>
            <button class="side-link" onclick="sideGo('history-container')">
                <span class="side-link__ic bg-cyan"><i class="fas fa-clock-rotate-left"></i></span>
                <span class="side-link__txt">খরচের হিস্ট্রি</span>
                <i class="fas fa-chevron-right side-link__arr"></i>
            </button>
            <?php if ($role === 'admin'): ?>
            <button class="side-link" onclick="sideGo('filter-container')">
                <span class="side-link__ic bg-amber"><i class="fas fa-magnifying-glass"></i></span>
                <span class="side-link__txt">ফিল্টার</span>
                <i class="fas fa-chevron-right side-link__arr"></i>
            </button>
            <?php endif; ?>
            <button class="side-link" onclick="closeSidebar(); toggleFolders();">
                <span class="side-link__ic bg-teal"><i class="fas fa-folder-open"></i></span>
                <span class="side-link__txt">মাসিক ফোল্ডার</span>
                <i class="fas fa-chevron-right side-link__arr"></i>
            </button>
            <button class="side-link" onclick="closeSidebar(); openCategoryModal();">
                <span class="side-link__ic bg-violet"><i class="fas fa-tags"></i></span>
                <span class="side-link__txt">ক্যাটাগরি</span>
                <i class="fas fa-chevron-right side-link__arr"></i>
            </button>
            <a class="side-link" href="../dashboard.php">
                <span class="side-link__ic bg-blue"><i class="fas fa-gauge-high"></i></span>
                <span class="side-link__txt">ড্যাশবোর্ড</span>
                <i class="fas fa-chevron-right side-link__arr"></i>
            </a>
        </nav>

        <div class="sidebar__foot">
            <button class="side-theme" onclick="toggleTheme()">
                <span id="sideThemeIcon"><i class="fas fa-moon"></i></span>
                <span>থিম পরিবর্তন</span>
            </button>
            <div class="sidebar__role">
                <i class="fas fa-user-shield"></i> Role: <strong><?php echo htmlspecialchars(ucfirst($role)); ?></strong>
            </div>
        </div>
    </aside>

    <!-- ===== EXPENSE ENTRY ===== -->
    <div class="collapsible" id="entry-container">
        <div class="sec-head"><span class="dot"></span><h2>নতুন খরচ এন্ট্রি</h2></div>
        <div class="card-soft">
            <div class="card-soft__head">
                <span class="badge-ic bg-green"><i class="fas fa-plus"></i></span>
                <div><h3>খরচ যোগ করুন</h3><small>খরচের তথ্য পূরণ করুন</small></div>
            </div>
            <?php if ($role != 'viewer'): ?>
            <form id="ajaxExpenseForm">
                <input type="hidden" name="ajax_action" value="save_expense">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="folder_month" value="<?php echo htmlspecialchars($folder_month); ?>">

                <label class="field-label">তারিখ</label>
                <input type="date" name="expense_date" class="form-control" value="<?php echo $today_date; ?>"
                    <?php
                        if ($role == 'manager')      { echo 'readonly'; }
                        elseif ($role != 'admin')    { echo 'min="'.$today_date.'" max="'.$today_date.'"'; }
                        else                         { echo 'max="'.$today_date.'"'; }
                    ?> required>

                <label class="field-label mt-3">ক্যাটাগরি</label>
                <select name="category" class="form-select" required>
                    <option value="">-- ক্যাটাগরি সিলেক্ট করুন --</option>
                    <?php foreach($active_categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category_name'] ?? ''); ?>"><?php echo htmlspecialchars($cat['category_name'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="field-label mt-3">টাকার পরিমাণ (৳)</label>
                <input type="number" name="amount" class="form-control amount-input" placeholder="0.00" step="0.01" max="99999999" required>

                <label class="field-label mt-3">বিবরণ <span style="font-weight:500;text-transform:none;color:var(--muted)">(ঐচ্ছিক)</span></label>
                <textarea name="expense_note" class="form-control" rows="2" placeholder="খরচের বিস্তারিত বিবরণ লিখুন..." maxlength="500"></textarea>

                <label class="field-label mt-3">ভাউচার ছবি <span style="font-weight:500;text-transform:none;color:var(--muted)">(ঐচ্ছিক)</span></label>
                <div class="cam-box mt-1">
                    <img id="photoPreview" alt="preview">
                    <video id="webcam" autoplay playsinline></video>
                    <canvas id="canvas"></canvas>
                    <input type="hidden" name="webcam_image" id="webcam_image">
                    <?php if ($role === 'admin'): ?>
                    <input type="file" id="fileInput" accept="image/*" style="display:none;">
                    <?php endif; ?>
                    <div class="ss-photo-actions">
                        <?php if ($role === 'admin'): ?>
                        <button type="button" id="pickBtn" class="btn-cam" onclick="document.getElementById('fileInput').click()"><i class="fas fa-image"></i> ছবি / গ্যালারি</button>
                        <?php endif; ?>
                        <button type="button" id="startCamBtn" class="btn-cam" style="background:var(--c-violet);"><i class="fas fa-camera"></i> লাইভ ক্যামেরা</button>
                    </div>
                    <button type="button" id="captureBtn" class="btn-cam mt-2" style="display:none;background:var(--c-rose);" onclick="captureImage()"><i class="fas fa-crop"></i> Capture</button>
                    <button type="button" id="retakeBtn" class="btn-cam mt-2" style="display:none;background:var(--c-amber);" onclick="retakeImage()"><i class="fas fa-rotate"></i> ছবি বদলান</button>
                    <?php if ($role !== 'admin'): ?>
                    <div style="font-size:11px; font-weight:600; color:var(--muted); margin-top:9px;"><i class="fas fa-circle-info"></i> শুধুমাত্র লাইভ ক্যামেরা দিয়ে ছবি তোলা যাবে (গ্যালারি আপলোড শুধু এডমিন)।</div>
                    <?php endif; ?>
                </div>

                <button type="submit" id="saveSubmitBtn" class="btn-pill btn-save"><i class="fas fa-circle-check"></i> খরচ সেভ করুন</button>
            </form>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-lock" style="color:var(--warn);"></i> আপনার খরচ এন্ট্রি করার অনুমতি নেই।</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== FILTER (admin) ===== -->
    <?php if ($role === 'admin'): ?>
    <div class="collapsible" id="filter-container">
        <div class="sec-head"><span class="dot"></span><h2>ফিল্টার</h2></div>
        <div class="card-soft">
            <div class="card-soft__head">
                <span class="badge-ic bg-amber"><i class="fas fa-magnifying-glass"></i></span>
                <div><h3>ক্যাটাগরি ও তারিখ ফিল্টার</h3><small>নির্দিষ্ট রেঞ্জের খরচ দেখুন</small></div>
            </div>

            <label class="field-label">ক্যাটাগরি সিলেক্ট করুন</label>
            <select id="filter-category" class="form-select">
                <option value="">-- সব ক্যাটাগরি --</option>
                <?php foreach($active_categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['category_name'] ?? ''); ?>"><?php echo htmlspecialchars($cat['category_name'] ?? ''); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="row g-2 mt-2">
                <div class="col-6"><label class="field-label">তারিখ থেকে</label><input type="date" id="filter-date-from" class="form-control" max="<?php echo date('Y-m-d'); ?>"></div>
                <div class="col-6"><label class="field-label">তারিখ পর্যন্ত</label><input type="date" id="filter-date-to" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>"></div>
            </div>

            <button onclick="runFilter()" class="btn-pill btn-filter mt-3"><i class="fas fa-magnifying-glass"></i> ফিল্টার করুন</button>

            <div id="filter-results" style="display:none;" class="mt-3">
                <div class="day-group">
                    <div class="day-head" style="color:var(--warn);justify-content:space-between;">
                        <span><i class="fas fa-list-ul"></i> ফিল্টার রেজাল্ট</span>
                        <span id="filter-result-count" class="count-pill" style="background:rgba(211,154,43,.2);color:var(--warn);"></span>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="exp-table">
                            <thead><tr><th>তারিখ</th><th>ক্যাটাগরি</th><th>By</th><th style="text-align:right;">টাকা</th></tr></thead>
                            <tbody id="filter-tbody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="total-bar"><span><i class="fas fa-calculator"></i> মোট খরচ</span><span id="filter-total" style="font-size:20px;">৳ 0</span></div>
            </div>
            <div id="filter-empty" class="empty-state" style="display:none;"><i class="fas fa-inbox"></i> কোনো ডেটা পাওয়া যায়নি</div>
            <div id="filter-loading" style="display:none;text-align:center;padding:25px 0;color:var(--warn);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== HISTORY ===== -->
    <div class="collapsible" id="history-container">
        <div class="sec-head"><span class="dot"></span><h2>খরচের হিস্ট্রি</h2></div>
        <div class="card-soft">
            <div class="card-soft__head">
                <span class="badge-ic bg-cyan"><i class="fas fa-clock-rotate-left"></i></span>
                <div><h3>সাম্প্রতিক খরচ</h3><small><?php echo empty($folder_month) ? 'গত ৭ দিনের রেকর্ড' : 'নির্বাচিত মাসের রেকর্ড'; ?></small></div>
            </div>
            <div id="historyListArea">
                <?php if(!empty($grouped_expenses)): ?>
                    <?php foreach($grouped_expenses as $date => $expenses): ?>
                        <div class="day-group">
                            <div class="day-head"><i class="far fa-calendar"></i> <?php echo date('d-M-Y', strtotime($date)); ?></div>
                            <div style="overflow-x:auto;">
                                <table class="exp-table">
                                    <tr><th>ক্যাটাগরি</th><th>টাকা</th><th style="text-align:center;">ছবি</th><th style="text-align:right;">অ্যাকশন</th></tr>
                                    <?php foreach($expenses as $exp):
                                        $safe_desc  = htmlspecialchars($exp['description'] ?? '');
                                        $safe_note  = htmlspecialchars($exp['note'] ?? '');
                                        $safe_amt   = number_format((float)($exp['amount'] ?? 0));
                                        $disp_photo = '';
                                        if (!empty($exp['photo'])) {
                                            $disp_photo = $exp['photo'];
                                            if (strpos($disp_photo, 'http') !== 0 && strpos($disp_photo, '../') !== 0) {
                                                $disp_photo = '../../' . ltrim($disp_photo, '/');
                                            }
                                            $disp_photo = htmlspecialchars($disp_photo);
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="exp-cat"><?php echo $safe_desc; ?></span>
                                            <?php if (!empty($safe_note)): ?>
                                                <div class="exp-note"><i class="fas fa-align-left"></i><span><?php echo $safe_note; ?></span></div>
                                            <?php endif; ?>
                                            <br><span class="exp-by">By: <?php echo htmlspecialchars($exp['entry_by'] ?? ''); ?></span>
                                        </td>
                                        <td><span class="exp-amt">৳<?php echo $safe_amt; ?></span></td>
                                        <td style="text-align:center;">
                                            <?php if(!empty($disp_photo)): ?>
                                                <img src="<?php echo $disp_photo; ?>" onclick="viewImage(this.src)" class="thumb">
                                            <?php else: ?><span style="color:var(--muted);">-</span><?php endif; ?>
                                        </td>
                                        <td style="text-align:right;white-space:nowrap;">
                                            <?php if ($role == 'admin'): ?>
                                                <button class="act-btn act-edit" onclick="triggerAjaxEdit(<?php echo $exp['id']; ?>, '<?php echo addslashes($safe_desc); ?>', <?php echo (float)($exp['amount'] ?? 0); ?>, '<?php echo addslashes($safe_note); ?>')"><i class="fas fa-pen"></i></button>
                                                <button class="act-btn act-del" onclick="triggerAjaxDelete(<?php echo $exp['id']; ?>)"><i class="fas fa-trash-can"></i></button>
                                            <?php else: ?>
                                                <i class="fas fa-lock" style="color:var(--muted); font-size:12px;"></i>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-receipt"></i> কোনো হিস্ট্রি ডাটা নেই।</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== FOLDERS ===== -->
    <div class="collapsible" id="folders-container">
        <div class="sec-head"><span class="dot"></span><h2>মাসিক ফোল্ডার</h2></div>
        <div class="card-soft">
            <div class="card-soft__head">
                <span class="badge-ic bg-teal"><i class="fas fa-folder-open"></i></span>
                <div><h3>মাস অনুযায়ী খরচ</h3><small>ফোল্ডারে ট্যাপ করুন</small></div>
            </div>
            <div class="folder-grid">
                <?php foreach($folder_data as $i => $f): $color = $folder_colors[$i % count($folder_colors)]; ?>
                    <?php if($role == 'admin'): ?>
                        <a href="?folder_month=<?php echo $f['year'].'-'.$f['month']; ?>&show_history=1" class="folder" style="background:linear-gradient(135deg,<?php echo $color; ?>cc,<?php echo $color; ?>);">
                    <?php else: ?>
                        <div class="folder" onclick="showLockedAlert()" style="background:linear-gradient(135deg,<?php echo $color; ?>cc,<?php echo $color; ?>);">
                    <?php endif; ?>
                        <div class="folder-title"><?php echo htmlspecialchars($f['month_name'] ?? ''); ?>-<?php echo substr($f['year'], 2); ?></div>
                        <div class="folder-amt">৳ <?php echo number_format((float)($f['total'] ?? 0)); ?></div>
                    <?php if($role == 'admin'): ?></a><?php else: ?></div><?php endif; ?>
                <?php endforeach; ?>

                <?php foreach($year_data as $year => $months): $total_year = array_sum(array_column($months, 'total')); ?>
                    <div class="folder year" onclick="<?php echo ($role=='admin') ? "toggleYearFolder('year_".htmlspecialchars($year)."')" : "showLockedAlert()"; ?>">
                        <div class="folder-title"><i class="fas fa-box-archive"></i> <?php echo htmlspecialchars((string)$year); ?></div>
                        <div class="folder-amt">৳ <?php echo number_format((float)$total_year); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php foreach($year_data as $year => $months): ?>
            <div id="year_<?php echo htmlspecialchars((string)$year); ?>" class="folder-grid" style="display:none; margin-top:14px; border-top:2px dashed var(--line); padding-top:14px;">
                <?php foreach($months as $i => $m): $color = $folder_colors[$i % count($folder_colors)]; ?>
                    <?php if($role == 'admin'): ?>
                        <a href="?folder_month=<?php echo $m['year'].'-'.$m['month']; ?>&show_history=1" class="folder" style="background:linear-gradient(135deg,<?php echo $color; ?>cc,<?php echo $color; ?>);">
                    <?php else: ?>
                        <div class="folder" onclick="showLockedAlert()" style="background:linear-gradient(135deg,<?php echo $color; ?>cc,<?php echo $color; ?>);">
                    <?php endif; ?>
                        <div class="folder-title"><?php echo htmlspecialchars($m['month_name'] ?? ''); ?>-<?php echo substr($m['year'], 2); ?></div>
                        <div class="folder-amt">৳ <?php echo number_format((float)($m['total'] ?? 0)); ?></div>
                    <?php if($role == 'admin'): ?></a><?php else: ?></div><?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="foot-lottie no-print">
        <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.10/dist/dotlottie-wc.js" type="module"></script>
        <dotlottie-wc src="https://lottie.host/1b30fa08-92da-4e38-81b5-e6236b92f63a/cGNSbBLSFu.lottie" style="width:200px;height:200px" autoplay loop></dotlottie-wc>
    </div>
</div>

<!-- ===== CATEGORY MODAL (admin only) ===== -->
<?php if ($role == 'admin'): ?>
<div id="catModal" class="modal-mask">
    <div class="modal-sheet">
        <button class="modal-x" onclick="closeModal('catModal')">&times;</button>
        <h3 style="color:var(--brand);margin:0 0 14px;font-weight:800;font-size:18px;border-bottom:1px solid var(--line);padding-bottom:12px;"><i class="fas fa-tags"></i> ক্যাটাগরি ম্যানেজমেন্ট</h3>

        <form method="POST" action="" style="display:flex;gap:8px;margin-bottom:16px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="text" name="add_new_category_name" class="form-control" placeholder="নতুন ক্যাটাগরি..." required maxlength="100" style="flex:1;">
            <button type="submit" name="add_new_category_submit" class="btn-pill btn-add"><i class="fas fa-plus"></i> Add</button>
        </form>

        <div class="seg">
            <button id="tab-active-btn" class="on-green" onclick="switchCatTab('active')"><i class="fas fa-toggle-on"></i> Active
                <span class="count-pill"><?php echo count(array_filter($all_categories, fn($c) => (int)($c['is_active'] ?? 1) === 1)); ?></span>
            </button>
            <button id="tab-inactive-btn" onclick="switchCatTab('inactive')"><i class="fas fa-toggle-off"></i> Inactive
                <span class="count-pill" style="background:rgba(214,87,69,.18);color:var(--neg);"><?php echo count(array_filter($all_categories, fn($c) => (int)($c['is_active'] ?? 1) === 0)); ?></span>
            </button>
        </div>

        <div id="tab-active" style="max-height:320px;overflow-y:auto;">
            <?php foreach($all_categories as $cat): if ((int)($cat['is_active'] ?? 1) !== 1) continue; ?>
            <div class="cat-row">
                <span class="cat-name"><?php echo htmlspecialchars($cat['category_name'] ?? ''); ?></span>
                <span style="white-space:nowrap;">
                    <button type="button" class="act-btn" style="color:var(--pos);font-size:18px;" title="Inactive করুন" onclick="toggleCategoryModal(<?php echo $cat['id']; ?>, 1, '<?php echo addslashes(htmlspecialchars($cat['category_name'] ?? '')); ?>')"><i class="fas fa-toggle-on"></i></button>
                    <button type="button" class="act-btn act-edit" onclick="editCategoryModal(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['category_name'] ?? ''); ?>')"><i class="fas fa-pen"></i></button>
                    <button type="button" class="act-btn act-del" onclick="deleteCategoryModal(<?php echo $cat['id']; ?>)"><i class="fas fa-trash-can"></i></button>
                </span>
            </div>
            <?php endforeach; ?>
        </div>

        <div id="tab-inactive" style="max-height:320px;overflow-y:auto;display:none;">
            <?php $inactive_cats = array_filter($all_categories, fn($c) => (int)($c['is_active'] ?? 1) === 0); ?>
            <?php if (empty($inactive_cats)): ?>
                <div class="empty-state"><i class="fas fa-circle-check" style="color:var(--pos);"></i> কোনো Inactive ক্যাটাগরি নেই</div>
            <?php else: ?>
                <?php foreach($all_categories as $cat): if ((int)($cat['is_active'] ?? 1) !== 0) continue; ?>
                <div class="cat-row" style="opacity:.72;">
                    <span class="cat-name" style="color:var(--muted);"><?php echo htmlspecialchars($cat['category_name'] ?? ''); ?> <span class="badge-off">INACTIVE</span></span>
                    <span style="white-space:nowrap;">
                        <button type="button" class="act-btn" style="color:var(--muted);font-size:18px;" title="Active করুন" onclick="toggleCategoryModal(<?php echo $cat['id']; ?>, 0, '<?php echo addslashes(htmlspecialchars($cat['category_name'] ?? '')); ?>')"><i class="fas fa-toggle-off"></i></button>
                        <button type="button" class="act-btn act-edit" onclick="editCategoryModal(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['category_name'] ?? ''); ?>')"><i class="fas fa-pen"></i></button>
                        <button type="button" class="act-btn act-del" onclick="deleteCategoryModal(<?php echo $cat['id']; ?>)"><i class="fas fa-trash-can"></i></button>
                    </span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="editCatForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="edit_cat_id"   id="edit_cat_id_input">
    <input type="hidden" name="edit_cat_name" id="edit_cat_name_input">
    <input type="hidden" name="edit_cat_submit" value="1">
</form>
<form id="deleteCatForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="csrf_token"    value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="delete_cat_id" id="delete_cat_id_input">
    <input type="hidden" name="delete_cat_submit" value="1">
</form>
<form id="toggleCatForm" method="POST" action="" style="display:none;">
    <input type="hidden" name="csrf_token"    value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="toggle_cat_id" id="toggle_cat_id_input">
    <input type="hidden" name="toggle_cat_submit" value="1">
</form>
<?php endif; ?>

<!-- Image viewer modal -->
<div id="imageViewerModal" class="modal-mask" onclick="closeModal('imageViewerModal')">
    <img id="popupImage" style="max-width:90%;max-height:80%;border-radius:14px;border:3px solid var(--brand);box-shadow:0 10px 25px rgba(0,0,0,.6);">
</div>

<!-- Floating Action Button -->
<button class="fab no-print" onclick="toggleView('entry-container')" title="নতুন এন্ট্রি"><i class="fas fa-plus"></i></button>

<script>
    var APP_CSRF_TOKEN     = '<?php echo htmlspecialchars($csrf_token); ?>';
    var userRole           = '<?php echo htmlspecialchars($role); ?>';
    var currentFolderMonth = '<?php echo htmlspecialchars($folder_month); ?>';
    <?php if(isset($_GET['folder_month']) || isset($_GET['show_history'])): ?>
    document.addEventListener("DOMContentLoaded", function () {
        var hist = document.getElementById('history-container');
        if (hist) { hist.classList.add('open'); setTimeout(function(){ hist.scrollIntoView({ behavior:'smooth', block:'start' }); }, 300); }
    });
    <?php endif; ?>
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ============================================================
   MY SHOP EXPENSE LEDGER — App Logic
   Expects globals: APP_CSRF_TOKEN, userRole, currentFolderMonth
   (defined inline by the PHP view before this script loads)
   ============================================================ */
(function () {
  if (typeof APP_CSRF_TOKEN === 'undefined') { window.APP_CSRF_TOKEN = ''; }
  if (typeof userRole === 'undefined')        { window.userRole = 'viewer'; }
  if (typeof currentFolderMonth === 'undefined'){ window.currentFolderMonth = ''; }
})();

/* ---------- Theme (light / dark, applies to every section) ---------- */
function applyTheme(mode) {
  document.documentElement.setAttribute('data-bs-theme', mode);
  document.body.classList.toggle('light-mode', mode === 'light');
  var cls = (mode === 'dark') ? 'fas fa-sun' : 'fas fa-moon';
  document.querySelectorAll('#themeIcon i, #themeIconDrawer i, #sideThemeIcon i').forEach(function (i) { i.className = cls; });
}
function toggleTheme() {
  var next = (document.documentElement.getAttribute('data-bs-theme') === 'dark') ? 'light' : 'dark';
  localStorage.setItem('exp_theme', next);
  applyTheme(next);
}
applyTheme(localStorage.getItem('exp_theme') === 'light' ? 'light' : 'dark');

/* ---------- Section toggles ---------- */
function toggleView(id) {
  var x = document.getElementById(id);
  if (!x) return;
  var isOpen = x.classList.contains('open');
  document.querySelectorAll('.collapsible').forEach(function (el) { el.classList.remove('open'); });
  if (!isOpen) {
    x.classList.add('open');
    setTimeout(function () { x.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 60);
  }
}
function toggleFolders()     { (userRole === 'admin') ? toggleView('folders-container') : showLockedAlert(); }
function openCategoryModal() { (userRole === 'admin') ? openModal('catModal') : showLockedAlert(); }
function showLockedAlert()   { Swal.fire({ icon:'error', title:'লকড!', text:'শুধুমাত্র এডমিন এই অপশন ব্যবহার করতে পারবেন।', confirmButtonColor:'#d65745' }); }

/* ---------- Sidebar ---------- */
function openSidebar()  { document.getElementById('sidebar').classList.add('open'); document.getElementById('sideMask').classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('sideMask').classList.remove('open'); document.body.style.overflow = ''; }
function sideGo(id)     { closeSidebar(); setTimeout(function () { toggleView(id); }, 280); }

/* ---------- Modals ---------- */
function openModal(id)  { var m = document.getElementById(id); if (m) m.classList.add('open'); }
function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('open'); }
function viewImage(src) { document.getElementById('popupImage').src = src; openModal('imageViewerModal'); }

/* ---------- AJAX core ---------- */
function sendAjaxRequest(formData) {
  formData.append('folder_month', currentFolderMonth);
  fetch(window.location.href, { method:'POST', body:formData })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (data.status === 'success') {
        Swal.fire({ icon:'success', title:'সফল!', text:data.msg, timer:1500, showConfirmButton:false });
        if (data.history_html) {
          document.getElementById('historyListArea').innerHTML = data.history_html;
          document.querySelectorAll('.collapsible').forEach(function (el) { el.classList.remove('open'); });
          var h = document.getElementById('history-container');
          h.classList.add('open');
          h.scrollIntoView({ behavior:'smooth', block:'start' });
        }
      } else {
        Swal.fire({ icon:'error', title:'ত্রুটি!', text:data.msg });
      }
    })
    .catch(function () { Swal.fire({ icon:'error', title:'নেটওয়ার্ক এরর!', text:'রিকোয়েস্ট ফেইল হয়েছে।' }); });
}

/* ---------- Expense form ---------- */
(function () {
  var ajaxForm = document.getElementById('ajaxExpenseForm');
  if (!ajaxForm) return;
  ajaxForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('saveSubmitBtn');
    var html = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> সেভ হচ্ছে...';
    btn.disabled = true;
    sendAjaxRequest(new FormData(this));
    var self = this;
    setTimeout(function () {
      btn.innerHTML = html; btn.disabled = false;
      self.reset(); retakeImage();
    }, 1000);
  });
})();

/* ---------- Edit / Delete expense ---------- */
async function triggerAjaxEdit(id, oldCat, oldAmt, oldNote) {
  const { value: fv } = await Swal.fire({
    title: 'খরচ আপডেট করুন',
    html:
      '<label style="text-align:left;display:block;font-size:11px;font-weight:800;color:#8b9189;margin-bottom:3px;text-transform:uppercase;">ক্যাটাগরি</label>' +
      '<input id="swal-cat" class="swal2-input" value="' + oldCat + '" maxlength="150" style="margin-bottom:8px;">' +
      '<label style="text-align:left;display:block;font-size:11px;font-weight:800;color:#8b9189;margin-bottom:3px;text-transform:uppercase;">টাকার পরিমাণ</label>' +
      '<input id="swal-amt" type="number" class="swal2-input" value="' + oldAmt + '" max="99999999" style="margin-bottom:8px;">' +
      '<label style="text-align:left;display:block;font-size:11px;font-weight:800;color:#8b9189;margin-bottom:3px;text-transform:uppercase;">বিবরণ (ঐচ্ছিক)</label>' +
      '<textarea id="swal-note" class="swal2-textarea" maxlength="500" style="height:75px;margin-bottom:8px;">' + (oldNote || '') + '</textarea>' +
      '<label style="text-align:left;display:block;font-size:11px;font-weight:800;color:#8b9189;margin-bottom:3px;text-transform:uppercase;">অ্যাডমিন পাসওয়ার্ড</label>' +
      '<input id="swal-pass" type="password" class="swal2-input" placeholder="পাসওয়ার্ড দিন">',
    focusConfirm: false, showCancelButton: true,
    confirmButtonText: 'আপডেট করুন', cancelButtonText: 'বাতিল', confirmButtonColor: '#10a36e',
    preConfirm: function () {
      var cat = document.getElementById('swal-cat').value, amt = document.getElementById('swal-amt').value,
          note = document.getElementById('swal-note').value, pass = document.getElementById('swal-pass').value;
      if (!cat || !amt || !pass) { Swal.showValidationMessage('ক্যাটাগরি, পরিমাণ ও পাসওয়ার্ড দিন!'); return false; }
      return { cat: cat, amt: amt, note: note, pass: pass };
    }
  });
  if (fv) {
    var fd = new FormData();
    fd.append('ajax_action','edit_expense'); fd.append('action_id', id);
    fd.append('edit_category', fv.cat); fd.append('edit_amount', fv.amt);
    fd.append('edit_note', fv.note); fd.append('admin_pass', fv.pass);
    fd.append('csrf_token', APP_CSRF_TOKEN);
    sendAjaxRequest(fd);
  }
}
async function triggerAjaxDelete(id) {
  const { value: pass } = await Swal.fire({
    title: 'খরচ ডিলিট', input: 'password', inputPlaceholder: 'অ্যাডমিন পাসওয়ার্ড দিন',
    showCancelButton: true, confirmButtonColor: '#d65745',
    confirmButtonText: 'ডিলিট করুন', cancelButtonText: 'বাতিল',
    inputValidator: function (v) { if (!v) return 'পাসওয়ার্ড দিতে হবে!'; }
  });
  if (pass) {
    var fd = new FormData();
    fd.append('ajax_action','delete_expense'); fd.append('action_id', id);
    fd.append('admin_pass', pass); fd.append('csrf_token', APP_CSRF_TOKEN);
    sendAjaxRequest(fd);
  }
}

/* ---------- Category modals ---------- */
async function editCategoryModal(id, oldName) {
  const { value: newName } = await Swal.fire({
    title: 'ক্যাটাগরি রিনেম', input: 'text', inputValue: oldName,
    showCancelButton: true, inputAttributes: { maxlength: 100 }, confirmButtonColor:'#10a36e',
    inputValidator: function (v) { if (!v) return 'নাম ফাঁকা রাখা যাবে না!'; }
  });
  if (newName) {
    document.getElementById('edit_cat_id_input').value = id;
    document.getElementById('edit_cat_name_input').value = newName;
    document.getElementById('editCatForm').submit();
  }
}
function deleteCategoryModal(id) {
  Swal.fire({
    title: 'আপনি কি নিশ্চিত?', text: 'আগের ডাটা সুরক্ষিত থাকবে, শুধু ক্যাটাগরি ডিলিট হবে।',
    icon: 'warning', showCancelButton: true, confirmButtonColor: '#d65745', confirmButtonText: 'হ্যাঁ, ডিলিট করুন!'
  }).then(function (r) {
    if (r.isConfirmed) {
      document.getElementById('delete_cat_id_input').value = id;
      document.getElementById('deleteCatForm').submit();
    }
  });
}
function toggleCategoryModal(id, isActive, catName) {
  var title = isActive ? 'Inactive করবেন?' : 'Active করবেন?';
  var text  = isActive ? '"' + catName + '" আর এন্ট্রি ফর্মে দেখা যাবে না।' : '"' + catName + '" আবার এন্ট্রি ফর্মে দেখা যাবে।';
  Swal.fire({ title:title, text:text, icon:'question', showCancelButton:true,
    confirmButtonColor: isActive ? '#d39a2b' : '#10a36e',
    confirmButtonText: isActive ? 'হ্যাঁ, Inactive করুন' : 'হ্যাঁ, Active করুন', cancelButtonText:'বাতিল'
  }).then(function (r) {
    if (r.isConfirmed) {
      document.getElementById('toggle_cat_id_input').value = id;
      document.getElementById('toggleCatForm').submit();
    }
  });
}
function switchCatTab(tab) {
  var at = document.getElementById('tab-active'), it = document.getElementById('tab-inactive');
  var ab = document.getElementById('tab-active-btn'), ib = document.getElementById('tab-inactive-btn');
  if (tab === 'active') {
    at.style.display='block'; it.style.display='none';
    ab.classList.add('on-green'); ib.classList.remove('on-red');
  } else {
    at.style.display='none'; it.style.display='block';
    ib.classList.add('on-red'); ab.classList.remove('on-green');
  }
}

/* ---------- Photo: file/gallery upload + optional live camera ---------- */
(function () {
  var video = document.getElementById('webcam');
  var canvas = document.getElementById('canvas');
  var hiddenInput = document.getElementById('webcam_image');
  var startBtn = document.getElementById('startCamBtn');
  var fileInput = document.getElementById('fileInput');
  var preview = document.getElementById('photoPreview');
  var captureBtn = document.getElementById('captureBtn');
  var retakeBtn = document.getElementById('retakeBtn');
  if (!hiddenInput) return; // non-admin / no form
  var streamRef = null;

  function setPhoto(dataUrl) {
    hiddenInput.value = dataUrl;
    if (preview) { preview.src = dataUrl; preview.style.display = 'block'; }
    if (video) video.style.display = 'none';
    if (canvas) canvas.style.display = 'none';
    if (captureBtn) captureBtn.style.display = 'none';
    if (startBtn) startBtn.style.display = 'none';
    var pb = document.getElementById('pickBtn'); if (pb) pb.style.display = 'none';
    if (retakeBtn) retakeBtn.style.display = 'block';
  }

  // File / gallery — works on HTTP and mobile; native chooser offers camera too
  if (fileInput) {
    fileInput.addEventListener('change', function (e) {
      var f = e.target.files && e.target.files[0];
      if (!f) return;
      if (!/^image\//.test(f.type)) { Swal.fire({ icon:'error', title:'ছবি নয়!', text:'অনুগ্রহ করে একটি ছবি ফাইল দিন।' }); return; }
      var reader = new FileReader();
      reader.onload = function (ev) {
        var img = new Image();
        img.onload = function () {
          var max = 1280, w = img.width, h = img.height;
          if (w > max || h > max) { if (w > h) { h = Math.round(h * max / w); w = max; } else { w = Math.round(w * max / h); h = max; } }
          var c = canvas || document.createElement('canvas');
          c.width = w; c.height = h;
          c.getContext('2d').drawImage(img, 0, 0, w, h);
          setPhoto(c.toDataURL('image/jpeg', 0.85));
        };
        img.onerror = function () { Swal.fire({ icon:'error', title:'এরর', text:'ছবি লোড করা যায়নি।' }); };
        img.src = ev.target.result;
      };
      reader.readAsDataURL(f);
    });
  }

  // Live camera (needs HTTPS)
  if (startBtn) {
    startBtn.onclick = function () {
      if (!window.isSecureContext && window.location.hostname !== 'localhost') {
        var hasPick = !!document.getElementById('pickBtn');
        Swal.fire({ icon:'info', title:'লাইভ ক্যামেরা', text: hasPick
          ? 'লাইভ ক্যামেরার জন্য HTTPS দরকার। "ছবি / গ্যালারি" বাটন দিয়ে আপলোড করুন — সেখান থেকেও ক্যামেরা খোলা যায়।'
          : 'লাইভ ক্যামেরা চালু করতে সাইটটি HTTPS-এ থাকতে হবে। অ্যাডমিনের সাথে যোগাযোগ করুন।' });
        return;
      }
      navigator.mediaDevices.getUserMedia({ video:{ facingMode:'environment' } })
        .then(function (stream) {
          streamRef = stream; video.srcObject = stream; video.style.display = 'block';
          if (preview) preview.style.display = 'none';
          startBtn.style.display = 'none';
          if (captureBtn) captureBtn.style.display = 'block';
        })
        .catch(function () { Swal.fire({ icon:'error', title:'ক্যামেরা!', text:'ক্যামেরা ওপেন করা যাচ্ছে না।' }); });
    };
  }

  window.captureImage = function () {
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    if (streamRef) streamRef.getTracks().forEach(function (t) { t.stop(); });
    setPhoto(canvas.toDataURL('image/jpeg', 0.85));
  };

  window.retakeImage = function () {
    if (hiddenInput) hiddenInput.value = '';
    if (preview) { preview.src = ''; preview.style.display = 'none'; }
    if (canvas) canvas.style.display = 'none';
    if (fileInput) fileInput.value = '';
    if (retakeBtn) retakeBtn.style.display = 'none';
    if (captureBtn) captureBtn.style.display = 'none';
    if (startBtn) startBtn.style.display = 'block';
    var pb = document.getElementById('pickBtn'); if (pb) pb.style.display = 'block';
    if (streamRef) streamRef.getTracks().forEach(function (t) { t.stop(); });
  };
})();
if (typeof window.retakeImage !== 'function') { window.retakeImage = function () {}; }

function toggleYearFolder(id) {
  var el = document.getElementById(id);
  el.style.display = (el.style.display === 'none' || !el.style.display) ? 'grid' : 'none';
}

/* ---------- Filter ---------- */
async function runFilter() {
  var category = document.getElementById('filter-category').value;
  var dateFrom = document.getElementById('filter-date-from').value;
  var dateTo   = document.getElementById('filter-date-to').value;
  if (!dateFrom || !dateTo) { Swal.fire({ icon:'warning', title:'তারিখ দিন!', text:'তারিখ থেকে এবং তারিখ পর্যন্ত — দুটোই দিতে হবে।', confirmButtonColor:'#d39a2b' }); return; }
  if (dateFrom > dateTo)    { Swal.fire({ icon:'warning', title:'ভুল তারিখ!', text:'"তারিখ থেকে" অবশ্যই ছোট হতে হবে।', confirmButtonColor:'#d39a2b' }); return; }

  document.getElementById('filter-results').style.display = 'none';
  document.getElementById('filter-empty').style.display = 'none';
  document.getElementById('filter-loading').style.display = 'block';

  var fd = new FormData();
  fd.append('ajax_action','filter_expenses'); fd.append('csrf_token', APP_CSRF_TOKEN);
  fd.append('filter_category', category); fd.append('filter_date_from', dateFrom); fd.append('filter_date_to', dateTo);

  try {
    var res = await fetch(window.location.href, { method:'POST', body:fd });
    var data = await res.json();
    document.getElementById('filter-loading').style.display = 'none';
    if (data.status === 'success' && data.rows && data.rows.length > 0) {
      var tbody = document.getElementById('filter-tbody'); tbody.innerHTML = '';
      var total = 0;
      data.rows.forEach(function (row) {
        var noteHtml = row.note ? '<div class="exp-note"><i class="fas fa-align-left"></i><span>' + row.note + '</span></div>' : '';
        tbody.innerHTML += '<tr><td style="font-size:11px;color:var(--muted);white-space:nowrap;">' + row.date + '</td>' +
          '<td><span class="exp-cat">' + row.category + '</span>' + noteHtml + '</td>' +
          '<td style="font-size:11px;color:var(--muted);">' + row.entry_by + '</td>' +
          '<td style="text-align:right;"><span class="exp-amt">৳' + row.amount_fmt + '</span></td></tr>';
        total += parseFloat(row.amount);
      });
      document.getElementById('filter-total').textContent = '৳ ' + total.toLocaleString('en-IN');
      document.getElementById('filter-result-count').textContent = data.rows.length + ' টি এন্ট্রি';
      document.getElementById('filter-results').style.display = 'block';
    } else {
      document.getElementById('filter-empty').style.display = 'block';
    }
  } catch (e) {
    document.getElementById('filter-loading').style.display = 'none';
    Swal.fire({ icon:'error', title:'এরর!', text:'ফিল্টার করা যায়নি।' });
  }
}

if (window.history.replaceState) { window.history.replaceState(null, null, window.location.href); }

</script>
</body>
</html>