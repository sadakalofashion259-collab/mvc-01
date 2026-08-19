<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

/* ── Session Guard ── */
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
    session_unset(); session_destroy();
    echo "<script>alert('Session মেয়াদ শেষ!');window.location.href='../index.php';</script>"; exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    echo "<script>window.location.href='../index.php';</script>"; exit;
}

include '../db_connect.php';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrfToken = $_SESSION['csrf_token'];

function logError(string $msg): void {
    $d = __DIR__ . '/../logs';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    @file_put_contents($d . '/error_log.txt', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

/* ── DB Columns (auto-create if missing) ── */
try { $conn->exec("ALTER TABLE `inventory` ADD COLUMN `mark_color` ENUM('yellow','green','purple') NULL DEFAULT NULL"); } catch(Exception $e){}
try { $conn->exec("ALTER TABLE `inventory` ADD COLUMN `mark_note` VARCHAR(500) NULL DEFAULT NULL"); } catch(Exception $e){}

/* ════════════════════════════════════════
   AJAX HANDLERS
   ════════════════════════════════════════ */
if (isset($_POST['ajax_action'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    /* CSRF check */
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['status'=>'error','message'=>'CSRF টোকেন অমিল!']); exit;
    }

    /* ── বহু পণ্য একসাথে মার্ক ── */
    if ($_POST['ajax_action'] === 'bulk_mark') {
        $rawIds = $_POST['ids'] ?? '';
        $ids    = array_filter(array_map('intval', explode(',', $rawIds)));
        $color  = in_array($_POST['color'] ?? '', ['yellow','green','purple','']) ? ($_POST['color'] ?? '') : '';
        $note   = trim(substr($_POST['note'] ?? '', 0, 500));
        if (empty($ids)) { echo json_encode(['status'=>'error','message'=>'কোনো আইডি নেই!']); exit; }
        try {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $params = [];
            if ($color !== '') {
                $params = array_merge([$color ?: null, $note !== '' ? $note : null], $ids);
                $conn->prepare("UPDATE `inventory` SET `mark_color`=?, `mark_note`=? WHERE `id` IN ($ph)")->execute($params);
            } else {
                $params = $ids;
                $conn->prepare("UPDATE `inventory` SET `mark_color`=NULL, `mark_note`=NULL WHERE `id` IN ($ph)")->execute($params);
            }
            echo json_encode(['status'=>'success','updated'=>count($ids)]);
        } catch(Exception $e){ logError('bulk_mark: '.$e->getMessage()); echo json_encode(['status'=>'error','message'=>'DB এরর!']); }
        exit;
    }

    /* ── সব মার্ক ক্লিয়ার (পাসওয়ার্ড দিয়ে) ── */
    if ($_POST['ajax_action'] === 'clear_all') {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        try {
            $st = $conn->prepare("SELECT `password` FROM `users` WHERE `id`=? AND `role`='admin' LIMIT 1");
            $st->execute([$uid]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            if ($u && password_verify(trim($_POST['password'] ?? ''), $u['password'])) {
                $conn->exec("UPDATE `inventory` SET `mark_color`=NULL, `mark_note`=NULL WHERE `mark_color` IS NOT NULL");
                echo json_encode(['status'=>'success']);
            } else {
                echo json_encode(['status'=>'error','message'=>'পাসওয়ার্ড ভুল!']);
            }
        } catch(Exception $e){ logError('clear_all: '.$e->getMessage()); echo json_encode(['status'=>'error','message'=>'DB এরর!']); }
        exit;
    }
}

/* ════════════════════════════════════════
   QUERY
   ════════════════════════════════════════ */
$perPage     = 20;
$curPage     = max(1, (int)($_GET['page'] ?? 1));
$search      = trim($_GET['search'] ?? '');
$catFilter   = (int)($_GET['category'] ?? 0);
$colorFilter = trim($_GET['color_filter'] ?? '');
if (!in_array($colorFilter, ['yellow','green','purple',''])) $colorFilter = '';

$where  = ['i.`pieces` > 0'];
$params = [];
if ($search !== '') {
    $where[]  = '(i.`product_code` LIKE ? OR i.`name` LIKE ? OR c.`name` LIKE ?)';
    $params   = array_merge($params, ["%$search%","%$search%","%$search%"]);
}
if ($catFilter > 0)  { $where[] = 'i.`category_id`=?';  $params[] = $catFilter; }
if ($colorFilter!=='') { $where[] = 'i.`mark_color`=?'; $params[] = $colorFilter; }
$whereSQL = implode(' AND ', $where);

$totalItems=0; $totalPages=1; $items=[];
$totalProd=0;  $totalPcs=0;
$pagePcs=0;    $pageVal=0.0;
$categories=[];
$markCounts=['yellow'=>0,'green'=>0,'purple'=>0];

try {
    /* Grand totals */
    $gr = $conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(`pieces`),0) AS p FROM `inventory` WHERE `pieces`>0")->fetch(PDO::FETCH_ASSOC);
    $totalProd = (int)$gr['c'];
    $totalPcs  = (int)$gr['p'];

    /* Mark counts */
    $mc = $conn->query("SELECT `mark_color`, COUNT(*) AS n FROM `inventory` WHERE `mark_color` IS NOT NULL AND `pieces`>0 GROUP BY `mark_color`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($mc as $m) $markCounts[$m['mark_color']] = (int)$m['n'];

    /* Paginated count */
    $cs = $conn->prepare("SELECT COUNT(*) FROM `inventory` i LEFT JOIN `categories` c ON c.`id`=i.`category_id` WHERE $whereSQL");
    $cs->execute($params);
    $totalItems = (int)$cs->fetchColumn();
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    if ($curPage > $totalPages) $curPage = $totalPages;
    $offset = ($curPage - 1) * $perPage;

    /* Items */
    $ds = $conn->prepare("
        SELECT
            i.`id`,
            i.`product_code`,
            i.`name`,
            i.`image_path`,
            i.`pieces`,
            i.`buy_price`,
            i.`mark_color`,
            i.`mark_note`,
            c.`name` AS category_name
        FROM `inventory` i
        LEFT JOIN `categories` c ON c.`id` = i.`category_id`
        WHERE $whereSQL
        ORDER BY c.`name` ASC, i.`product_code` ASC
        LIMIT $perPage OFFSET $offset
    ");
    $ds->execute($params);
    $items = $ds->fetchAll(PDO::FETCH_ASSOC);

    $pagePcs = (int)array_sum(array_column($items,'pieces'));
    $pageVal = (float)array_sum(array_map(fn($i)=>(float)$i['buy_price']*(int)$i['pieces'],$items));

    $categories = $conn->query("SELECT `id`,`name` FROM `categories` WHERE `status`='active' ORDER BY `name` ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e){ logError('unsold_inventory.php: '.$e->getMessage()); }

/* ── AJAX: JSON list ── */
if (isset($_GET['ajax']) && $_GET['ajax']==='1') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    $sStart = ($curPage-1)*$perPage+1;
    $bgMap  = ['yellow'=>'#fef9c3','green'=>'#d1fae5','purple'=>'#ede9fe'];
    $bdMap  = ['yellow'=>'#f59e0b','green'=>'#10b981','purple'=>'#8b5cf6'];
    ob_start();
    if (count($items)>0):
        foreach ($items as $k=>$it):
            $sn  = $sStart+$k;
            $img = htmlspecialchars($it['image_path']??'');
            $cat = htmlspecialchars($it['category_name']??'—');
            $cod = htmlspecialchars($it['product_code']??'');
            $nam = htmlspecialchars($it['name']??'');
            $pcs = (int)$it['pieces'];
            $bp  = (float)$it['buy_price'];
            $mc  = $it['mark_color']??'';
            $mn  = htmlspecialchars($it['mark_note']??'');
            $id  = (int)$it['id'];
            $rs  = $mc ? 'background:'.$bgMap[$mc].';border-left:3.5px solid '.$bdMap[$mc].';' : '';
            $db  = $mc ? $bdMap[$mc] : '#e5e7eb';
?>
<div class="ur" data-id="<?=$id?>" data-color="<?=$mc?>" data-note="<?=$mn?>" onclick="toggleSelect(this)" style="<?=$rs?>">
  <div class="ur-sel"><div class="ur-dot" style="background:<?=$db?>"></div></div>
  <div class="ur-sn"><?=$sn?></div>
  <div class="ur-img-wrap"><?php if($img!==''):?><img src="<?=$img?>" class="ur-img" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><div class="ur-ph" style="display:none"><i class="fas fa-image"></i></div><?php else:?><div class="ur-ph"><i class="fas fa-image"></i></div><?php endif;?></div>
  <div class="ur-info">
    <span class="ur-cat"><?=$cat?></span>
    <span class="ur-code"><?=$cod?></span>
    <?php if($nam!==''&&$nam!==$cod):?><span class="ur-name"><?=$nam?></span><?php endif;?>
    <?php if($mn!==''):?><span class="ur-note"><i class="fas fa-sticky-note"></i> <?=mb_strlen($mn)>32?mb_substr($mn,0,32).'…':$mn?></span><?php endif;?>
  </div>
  <div class="ur-buy"><span class="ur-lbl">কেনা</span><span class="ur-price">৳<?=number_format($bp,0)?></span></div>
  <div class="ur-pcs-w"><span class="ur-lbl">পিস</span><span class="ur-pcs"><?=$pcs?></span></div>
</div>
<?php endforeach;
    else: echo '<div class="empty-msg"><i class="fas fa-search-minus"></i><p>কোনো পণ্য পাওয়া যায়নি</p></div>'; endif;
    $html = ob_get_clean();

    /* pager */
    ob_start();
    if($totalPages>1){
        $w=2; $rs2=max(1,$curPage-$w); $re2=min($totalPages,$curPage+$w);
        echo '<div class="us-pager">';
        if($curPage>1) echo '<a href="#" onclick="ajaxPage(1,event)"><i class="fas fa-angle-double-left"></i></a><a href="#" onclick="ajaxPage('.($curPage-1).',event)"><i class="fas fa-angle-left"></i></a>';
        if($rs2>1){ echo '<a href="#" onclick="ajaxPage(1,event)">1</a>'; if($rs2>2) echo '<span class="pdot">…</span>'; }
        for($p=$rs2;$p<=$re2;$p++){
            echo $p===$curPage?'<span class="pact">'.$p.'</span>':'<a href="#" onclick="ajaxPage('.$p.',event)">'.$p.'</a>';
        }
        if($re2<$totalPages){ if($re2<$totalPages-1) echo '<span class="pdot">…</span>'; echo '<a href="#" onclick="ajaxPage('.$totalPages.',event)">'.$totalPages.'</a>'; }
        if($curPage<$totalPages) echo '<a href="#" onclick="ajaxPage('.($curPage+1).',event)"><i class="fas fa-angle-right"></i></a><a href="#" onclick="ajaxPage('.$totalPages.',event)"><i class="fas fa-angle-double-right"></i></a>';
        echo '</div>';
        echo '<div class="pg-jump"><span>পেজ:</span><input type="number" id="pgJumpInput" min="1" max="'.$totalPages.'" value="'.$curPage.'"><button onclick="jumpToPage()">যান</button></div>';
    }
    $pagerHtml = ob_get_clean();

    $ss2 = $totalItems>0?($curPage-1)*$perPage+1:0;
    $se2 = min($curPage*$perPage,$totalItems);
    echo json_encode(['html'=>$html,'pager'=>$pagerHtml,'total'=>$totalItems,'pages'=>$totalPages,'curPage'=>$curPage,'ss'=>$ss2,'se'=>$se2,'pagePcs'=>$pagePcs,'pageVal'=>$pageVal,'cnt'=>count($items)]);
    exit;
}

/* ── Full Page ── */
$sStart = ($curPage-1)*$perPage+1;
$sEnd   = min($curPage*$perPage,$totalItems);
$showSt = $totalItems>0?$sStart:0;
$bgMap  = ['yellow'=>'#fef9c3','green'=>'#d1fae5','purple'=>'#ede9fe'];
$bdMap  = ['yellow'=>'#f59e0b','green'=>'#10b981','purple'=>'#8b5cf6'];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>অবিক্রীত পণ্য</title>
<meta name="theme-color" content="#6366f1">
<script>(function(){try{var t=localStorage.getItem('sk-theme');if(t)document.documentElement.setAttribute('data-theme',t);else if(window.matchMedia('(prefers-color-scheme:dark)').matches)document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="theme.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script defer src="theme-toggle.js"></script>
<style>
/* ── Reset / Base ── */
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
/* ── Stat Cards ── */
.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.7rem;}
.stat-card{border-radius:14px;padding:11px 13px;display:flex;align-items:center;gap:9px;}
.stat-icon{font-size:1.3rem;line-height:1;}
.stat-lbl{font-size:.63rem;color:#6b7280;font-weight:600;line-height:1.3;}
.stat-val{font-size:1.1rem;font-weight:900;color:#1f2937;line-height:1.2;}
.s-warn{background:linear-gradient(135deg,#fef3c7,#fde68a);}
.s-red {background:linear-gradient(135deg,#fee2e2,#fecaca);}

/* ── Search Box ── */
.srch-wrap{position:relative;margin-bottom:.55rem;}
.srch-wrap input{width:100%;padding:10px 38px 10px 38px;border-radius:12px;border:2px solid var(--sk-border,#e5e7eb);font-size:.9rem;font-family:inherit;background:var(--sk-surface,#fff);color:var(--sk-ink,#1f2937);outline:none;transition:border .2s;}
.srch-wrap input:focus{border-color:var(--sk-primary,#6366f1);}
.srch-wrap .si{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--sk-muted,#9ca3af);font-size:.85rem;pointer-events:none;}
.srch-wrap .ss{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--sk-primary,#6366f1);display:none;}

/* ── Filter Row ── */
.filter-row{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.55rem;align-items:center;}
.filter-row select{flex:1;min-width:120px;padding:8px 10px;border-radius:10px;border:1.5px solid var(--sk-border,#e5e7eb);font-size:.83rem;font-family:inherit;background:var(--sk-surface,#fff);color:var(--sk-ink,#1f2937);outline:none;}
.btn-reset{padding:7px 12px;border-radius:10px;border:1.5px solid var(--sk-border,#e5e7eb);background:var(--sk-surface,#fff);color:var(--sk-muted,#6b7280);font-size:.8rem;cursor:pointer;white-space:nowrap;font-family:inherit;}

/* ── Color Pill Filters ── */
.pill-row{display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:.6rem;}
.pill{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:20px;font-size:.72rem;font-weight:700;cursor:pointer;border:2px solid transparent;text-decoration:none;transition:all .15s;}
.pill.active{border-color:currentColor;transform:scale(1.04);}
.pill-all{background:var(--sk-surface2,#f3f4f6);color:var(--sk-muted,#6b7280);}
.pill-y  {background:#fef9c3;color:#92400e;}
.pill-g  {background:#d1fae5;color:#065f46;}
.pill-p  {background:#ede9fe;color:#5b21b6;}
.pill-clr{background:#fee2e2;color:#991b1b;cursor:pointer;border:none;font-family:inherit;}

/* ── Info Bar ── */
.info-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.3rem;font-size:.73rem;color:var(--sk-muted);padding:0 2px;margin-bottom:.45rem;}
.info-bar strong{color:var(--sk-ink,#1f2937);}
.info-pg{color:var(--sk-primary,#6366f1);font-weight:700;}

/* ── List Table ── */
.us-table{border-radius:12px;border:1.5px solid var(--sk-border,#e5e7eb);overflow:hidden;}
.us-thead{display:grid;grid-template-columns:12px 22px 46px 1fr 56px 40px;gap:0 5px;padding:7px 10px;background:var(--sk-surface2,#f3f4f6);border-bottom:2px solid var(--sk-border,#e5e7eb);align-items:center;}
.us-thead span{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px;color:var(--sk-muted);}
.tr{text-align:right;}

/* ── Row ── */
.ur{display:grid;grid-template-columns:12px 22px 46px 1fr 56px 40px;gap:0 5px;align-items:center;padding:7px 10px;border-bottom:1px solid var(--sk-border,#e5e7eb);cursor:pointer;transition:filter .12s;border-left:3.5px solid transparent;user-select:none;}
.ur:last-child{border-bottom:none;}
.ur:active{filter:brightness(.95);}
.ur.selected{outline:2.5px solid var(--sk-primary,#6366f1);outline-offset:-2px;background:var(--sk-surface2,#f0f4ff)!important;}
.ur-sel{display:flex;align-items:center;justify-content:center;}
.ur-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;transition:background .2s;}
.ur.selected .ur-dot{background:var(--sk-primary,#6366f1)!important;}
.ur-sn{font-size:.68rem;font-weight:700;color:var(--sk-muted);text-align:center;}
.ur-img-wrap{position:relative;}
.ur-img{width:46px;height:46px;object-fit:cover;border-radius:8px;border:1.5px solid var(--sk-border,#e5e7eb);display:block;}
.ur-ph{width:46px;height:46px;border-radius:8px;border:1.5px dashed var(--sk-border,#e5e7eb);background:var(--sk-surface2,#f3f4f6);display:flex;align-items:center;justify-content:center;color:#d1d5db;font-size:.85rem;}
.ur-info{min-width:0;display:flex;flex-direction:column;gap:1px;}
.ur-cat{font-size:.62rem;font-weight:600;color:var(--sk-muted,#6b7280);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3;}
.ur-code{font-family:'Courier New',monospace;font-size:.82rem;font-weight:800;color:var(--sk-primary,#6366f1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ur-name{font-size:.66rem;color:var(--sk-muted,#9ca3af);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ur-note{font-size:.63rem;color:#b45309;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ur-buy{text-align:right;white-space:nowrap;}
.ur-lbl{font-size:.58rem;color:var(--sk-muted);font-weight:600;display:block;line-height:1;}
.ur-price{font-size:.78rem;font-weight:800;color:#10b981;display:block;}
.ur-pcs-w{text-align:right;white-space:nowrap;}
.ur-pcs{font-size:.92rem;font-weight:900;color:#f59e0b;display:block;}

/* ── Subtotal ── */
.subtotal{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.3rem;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border-radius:0 0 12px 12px;padding:9px 13px;}
.sub-item{display:flex;flex-direction:column;}
.sub-lbl{font-size:.6rem;opacity:.8;font-weight:600;}
.sub-val{font-size:.92rem;font-weight:900;line-height:1.2;}
.sub-sep{width:1px;height:28px;background:rgba(255,255,255,.3);}

/* ── Pager ── */
.us-pager{display:flex;flex-wrap:wrap;gap:.3rem;justify-content:center;margin-top:.75rem;}
.us-pager a,.us-pager span{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 .4rem;border-radius:8px;font-size:.78rem;font-weight:700;text-decoration:none;transition:all .15s;}
.us-pager a{background:var(--sk-surface2,#f3f4f6);color:var(--sk-ink);border:1.5px solid var(--sk-border,#e5e7eb);}
.us-pager a:hover,.us-pager a:active{background:var(--sk-primary,#6366f1);color:#fff;border-color:var(--sk-primary,#6366f1);}
.pact{background:var(--sk-primary,#6366f1)!important;color:#fff!important;border:1.5px solid var(--sk-primary,#6366f1)!important;}
.pdot{color:var(--sk-muted);background:none!important;border:none!important;}
.pg-jump{display:flex;align-items:center;gap:.4rem;justify-content:center;margin-top:.4rem;}
.pg-jump span{font-size:.73rem;color:var(--sk-muted);}
.pg-jump input{width:48px;height:30px;text-align:center;border:1.5px solid var(--sk-border,#e5e7eb);border-radius:7px;font-size:.8rem;font-weight:700;background:var(--sk-surface,#fff);color:var(--sk-ink);font-family:inherit;outline:none;}
.pg-jump button{height:30px;padding:0 .7rem;border-radius:7px;font-size:.75rem;font-weight:700;background:var(--sk-primary,#6366f1);color:#fff;border:none;cursor:pointer;font-family:inherit;}

/* ── Empty ── */
.empty-msg{text-align:center;padding:2.5rem 1rem;color:var(--sk-muted);}
.empty-msg i{font-size:2rem;display:block;margin-bottom:.6rem;opacity:.5;}
.empty-msg p{font-size:.88rem;font-weight:600;}

/* ════════════════════════
   FLOATING MULTI-SELECT BAR
   ════════════════════════ */
.sel-bar{
    position:fixed;bottom:0;left:0;right:0;z-index:800;
    background:#fff;
    border-top:2.5px solid var(--sk-primary,#6366f1);
    box-shadow:0 -4px 24px rgba(99,102,241,.18);
    padding:10px 14px 14px;
    transform:translateY(100%);
    transition:transform .25s cubic-bezier(.4,0,.2,1);
    max-width:600px;margin:0 auto;
}
.sel-bar.open{transform:translateY(0);}
.sel-bar-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:9px;}
.sel-bar-title{font-size:.82rem;font-weight:800;color:var(--sk-ink,#1f2937);}
.sel-bar-cnt{background:var(--sk-primary,#6366f1);color:#fff;font-size:.72rem;font-weight:900;padding:2px 9px;border-radius:20px;}
.sel-bar-close{border:none;background:var(--sk-surface2,#f3f4f6);color:var(--sk-muted);width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center;}

.sel-bar-note{width:100%;padding:7px 11px;border-radius:9px;border:1.5px solid var(--sk-border,#e5e7eb);font-size:.82rem;font-family:inherit;background:var(--sk-surface,#fff);color:var(--sk-ink);outline:none;margin-bottom:9px;resize:none;}
.sel-bar-note:focus{border-color:var(--sk-primary,#6366f1);}

.color-btns{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:.4rem;}
.cb{padding:9px 4px;border-radius:11px;border:2.5px solid transparent;font-size:.72rem;font-weight:700;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:4px;font-family:inherit;transition:all .18s;}
.cb .c-dot{width:22px;height:22px;border-radius:50%;}
.cb.sel{transform:scale(1.06);box-shadow:0 3px 10px rgba(0,0,0,.15);}
.cb-none{background:var(--sk-surface2,#f3f4f6);color:var(--sk-muted);}
.cb-none .c-dot{background:#d1d5db;}
.cb-none.sel{border-color:#9ca3af;}
.cb-y{background:#fef9c3;color:#92400e;}
.cb-y .c-dot{background:#f59e0b;}
.cb-y.sel{border-color:#f59e0b;}
.cb-g{background:#d1fae5;color:#065f46;}
.cb-g .c-dot{background:#10b981;}
.cb-g.sel{border-color:#10b981;}
.cb-p{background:#ede9fe;color:#5b21b6;}
.cb-p .c-dot{background:#8b5cf6;}
.cb-p.sel{border-color:#8b5cf6;}

.btn-save{width:100%;margin-top:.6rem;padding:11px;border-radius:11px;border:none;background:var(--sk-primary,#6366f1);color:#fff;font-size:.9rem;font-weight:800;cursor:pointer;font-family:inherit;transition:opacity .15s;}
.btn-save:active{opacity:.85;}

/* ════════════════════════
   CLEAR ALL MODAL
   ════════════════════════ */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:900;align-items:center;justify-content:center;padding:1rem;}
.modal-overlay.open{display:flex;}
.modal-box{background:var(--sk-surface,#fff);border-radius:18px;padding:1.5rem 1.25rem;width:100%;max-width:340px;box-shadow:0 8px 40px rgba(0,0,0,.2);}
.modal-box h3{font-size:.95rem;font-weight:800;margin-bottom:.6rem;color:var(--sk-ink);}
.modal-box p{font-size:.8rem;color:var(--sk-muted);margin-bottom:.875rem;}
.modal-pw{width:100%;padding:10px 14px;border-radius:10px;border:2px solid var(--sk-border,#e5e7eb);font-size:.9rem;font-family:inherit;margin-bottom:.5rem;outline:none;background:var(--sk-surface,#fff);color:var(--sk-ink);}
.modal-pw:focus{border-color:#ef4444;}
.modal-err{color:#ef4444;font-size:.75rem;font-weight:600;display:none;margin-bottom:.5rem;}
.modal-btns{display:flex;gap:.5rem;}
.modal-btns button{flex:1;padding:10px;border-radius:10px;font-size:.85rem;font-weight:700;cursor:pointer;font-family:inherit;border:none;}
.btn-cancel{background:var(--sk-surface2,#f3f4f6);color:var(--sk-muted);}
.btn-del{background:#ef4444;color:#fff;}

/* ── Toast ── */
.toast{position:fixed;bottom:80px;left:50%;transform:translateX(-50%);padding:10px 20px;border-radius:13px;font-weight:700;font-size:.82rem;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.2);white-space:nowrap;pointer-events:none;transition:opacity .35s;}
</style>
</head>
<body>

<!-- ══ APP BAR ══ -->
<header class="sk-appbar">
    <div class="sk-appbar__left">
        <button class="sk-iconbtn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <a href="inventory_dashboard.php" class="sk-iconbtn"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="sk-appbar__title"><span class="dot"></span> অবিক্রীত পণ্য</div>
    <div class="sk-appbar__right">
        <a href="../logout.php" class="sk-iconbtn sk-iconbtn--danger"><i class="fas fa-power-off"></i></a>
    </div>
</header>

<!-- ══ SIDEBAR ══ -->
<div class="sk-overlay" id="myOverlay" onclick="toggleSidebar()"></div>
<aside class="sk-drawer" id="mySidebar">
    <div class="sk-drawer__head">
        <button class="sk-drawer__close" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
        <img src="logo.png" class="sk-drawer__logo" onerror="this.style.display='none'">
        <div class="sk-drawer__brand">SADA KALO</div>
        <div class="sk-drawer__sub">INVENTORY ADMIN</div>
    </div>
    <div class="sk-drawer__section">Quick Menu</div>
    <nav class="sk-drawer__grid">
        <a href="../dashboard.php"        class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-home"></i></div><span class="sk-drawer__label">হোম</span></a>
        <a href="inventory_dashboard.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-th-large"></i></div><span class="sk-drawer__label">Dashboard</span></a>
        <a href="inventory.php"           class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-plus"></i></div><span class="sk-drawer__label">Add Item</span></a>
        <a href="Invantory_Items.php"     class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-box-open"></i></div><span class="sk-drawer__label">Item List</span></a>
        <a href="inventory_pos.php"       class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-shopping-cart"></i></div><span class="sk-drawer__label">POS</span></a>
        <a href="admin_category_control.php" class="sk-drawer__item"><div class="sk-drawer__icon"><i class="fas fa-tags"></i></div><span class="sk-drawer__label">Category</span></a>
        <a href="unsold_inventory.php"    class="sk-drawer__item active"><div class="sk-drawer__icon"><i class="fas fa-archive"></i></div><span class="sk-drawer__label">অবিক্রীত</span></a>
    </nav>
</aside>

<!-- ══ MAIN ══ -->
<main class="sk-container">
<div class="sk-banner"><img src="banner.jpg" alt="" onerror="this.parentElement.style.display='none'"></div>

<!-- STATS -->
<div class="stat-grid">
    <div class="stat-card s-warn">
        <div class="stat-icon">📦</div>
        <div><div class="stat-lbl">অবিক্রীত আইটেম</div><div class="stat-val"><?php echo number_format($totalProd);?></div></div>
    </div>
    <div class="stat-card s-red">
        <div class="stat-icon">🧱</div>
        <div><div class="stat-lbl">মোট পিস</div><div class="stat-val"><?php echo number_format($totalPcs);?></div></div>
    </div>
</div>

<div class="sk-card sk-card--pad-lg">

<!-- AJAX SEARCH -->
<div class="srch-wrap">
    <i class="fas fa-search si"></i>
    <input type="text" id="srchInput" value="<?php echo htmlspecialchars($search);?>" placeholder="কোড / নাম / ক্যাটাগরি লিখুন..." autocomplete="off">
    <i class="fas fa-spinner fa-spin ss" id="srchSpin"></i>
</div>

<!-- CATEGORY FILTER -->
<form method="GET" action="unsold_inventory.php" id="filterForm">
<div class="filter-row">
    <select name="category" onchange="this.form.submit()">
        <option value="0">সব ক্যাটাগরি</option>
        <?php foreach($categories as $cat): ?>
        <option value="<?php echo(int)$cat['id'];?>" <?php echo $catFilter===(int)$cat['id']?'selected':'';?>>
            <?php echo htmlspecialchars($cat['name']);?>
        </option>
        <?php endforeach;?>
    </select>
    <?php if($catFilter>0||$colorFilter!==''||$search!==''):?>
    <button type="button" class="btn-reset" onclick="window.location.href='unsold_inventory.php'"><i class="fas fa-times"></i> রিসেট</button>
    <?php endif;?>
</div>
<input type="hidden" name="page" value="1">
<input type="hidden" name="search" id="hiddenSearch" value="<?php echo htmlspecialchars($search);?>">
<input type="hidden" name="color_filter" id="colorFilterHidden" value="<?php echo htmlspecialchars($colorFilter);?>">
</form>

<!-- COLOR PILLS -->
<div class="pill-row">
    <?php
    $base = array_filter(['category'=>$catFilter?:null,'search'=>$search?:null],fn($v)=>$v!==null&&$v!=='');
    function pUrl(array $extra,array $base):string{ return 'unsold_inventory.php?'.http_build_query(array_filter(array_merge($base,$extra),fn($v)=>$v!==null&&$v!==''&&$v!==0)); }
    ?>
    <a href="<?php echo pUrl([],$base);?>" class="pill pill-all <?php echo $colorFilter===''?'active':'';?>"><i class="fas fa-list"></i> সব</a>
    <a href="<?php echo pUrl(['color_filter'=>'yellow'],$base);?>" class="pill pill-y <?php echo $colorFilter==='yellow'?'active':'';?>">🟡 হলুদ&nbsp;<strong><?php echo $markCounts['yellow'];?></strong></a>
    <a href="<?php echo pUrl(['color_filter'=>'green'],$base);?>"  class="pill pill-g <?php echo $colorFilter==='green'?'active':'';?>">🟢 সবুজ&nbsp;<strong><?php echo $markCounts['green'];?></strong></a>
    <a href="<?php echo pUrl(['color_filter'=>'purple'],$base);?>" class="pill pill-p <?php echo $colorFilter==='purple'?'active':'';?>">🟣 বেগুনি&nbsp;<strong><?php echo $markCounts['purple'];?></strong></a>
    <button class="pill pill-clr" onclick="openClearAll()"><i class="fas fa-eraser"></i> ক্লিয়ার</button>
</div>

<!-- INFO BAR -->
<div class="info-bar" id="infoBar">
    <span>মোট <strong><?php echo $totalItems;?></strong> | দেখাচ্ছে <strong><?php echo $showSt;?></strong>–<strong><?php echo $sEnd;?></strong></span>
    <span class="info-pg">পেজ <?php echo $curPage;?>/<?php echo $totalPages;?></span>
</div>

<!-- TABLE -->
<div class="us-table">
    <div class="us-thead">
        <span></span>
        <span>#</span>
        <span>ছবি</span>
        <span>ক্যাটাগরি / কোড</span>
        <span class="tr">কেনা</span>
        <span class="tr">পিস</span>
    </div>
    <div id="ajaxList">
    <?php foreach($items as $k=>$it):
        $sn  = $sStart+$k;
        $img = htmlspecialchars($it['image_path']??'');
        $cat = htmlspecialchars($it['category_name']??'—');
        $cod = htmlspecialchars($it['product_code']??'');
        $nam = htmlspecialchars($it['name']??'');
        $pcs = (int)$it['pieces'];
        $bp  = (float)$it['buy_price'];
        $mc  = $it['mark_color']??'';
        $mn  = htmlspecialchars($it['mark_note']??'');
        $id  = (int)$it['id'];
        $rs  = $mc ? 'background:'.$bgMap[$mc].';border-left:3.5px solid '.$bdMap[$mc].';' : '';
        $db  = $mc ? $bdMap[$mc] : '#e5e7eb';
    ?>
    <div class="ur" data-id="<?=$id?>" data-color="<?=$mc?>" data-note="<?=$mn?>" onclick="toggleSelect(this)" style="<?=$rs?>">
        <div class="ur-sel"><div class="ur-dot" style="background:<?=$db?>"></div></div>
        <div class="ur-sn"><?=$sn?></div>
        <div class="ur-img-wrap">
            <?php if($img!==''):?>
            <img src="<?=$img?>" class="ur-img" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <div class="ur-ph" style="display:none"><i class="fas fa-image"></i></div>
            <?php else:?><div class="ur-ph"><i class="fas fa-image"></i></div><?php endif;?>
        </div>
        <div class="ur-info">
            <span class="ur-cat"><?=$cat?></span>
            <span class="ur-code"><?=$cod?></span>
            <?php if($nam!==''&&$nam!==$cod):?><span class="ur-name"><?=$nam?></span><?php endif;?>
            <?php if($mn!==''):?><span class="ur-note"><i class="fas fa-sticky-note"></i> <?=mb_strlen($mn)>32?mb_substr($mn,0,32).'…':$mn?></span><?php endif;?>
        </div>
        <div class="ur-buy"><span class="ur-lbl">কেনা</span><span class="ur-price">৳<?=number_format($bp,0)?></span></div>
        <div class="ur-pcs-w"><span class="ur-lbl">পিস</span><span class="ur-pcs"><?=$pcs?></span></div>
    </div>
    <?php endforeach;?>
    <?php if(count($items)===0):?><div class="empty-msg"><i class="fas fa-inbox"></i><p>কোনো পণ্য পাওয়া যায়নি</p></div><?php endif;?>
    </div><!-- /ajaxList -->

    <!-- SUBTOTAL -->
    <div class="subtotal" id="subtotalBar">
        <div class="sub-item"><span class="sub-lbl">আইটেম</span><span class="sub-val" id="stCnt"><?php echo count($items);?></span></div>
        <div class="sub-sep"></div>
        <div class="sub-item"><span class="sub-lbl">পিস</span><span class="sub-val" id="stPcs"><?php echo number_format($pagePcs);?></span></div>
        <div class="sub-sep"></div>
        <div class="sub-item"><span class="sub-lbl">কেনা মূল্য</span><span class="sub-val" id="stVal">৳<?php echo number_format($pageVal,0);?></span></div>
    </div>
</div><!-- /us-table -->

<!-- PAGER -->
<div id="pagerArea">
<?php if($totalPages>1):
    $w=2; $rs2=max(1,$curPage-$w); $re2=min($totalPages,$curPage+$w);
    echo '<div class="us-pager">';
    if($curPage>1) echo '<a href="#" onclick="ajaxPage(1,event)"><i class="fas fa-angle-double-left"></i></a><a href="#" onclick="ajaxPage('.($curPage-1).',event)"><i class="fas fa-angle-left"></i></a>';
    if($rs2>1){ echo '<a href="#" onclick="ajaxPage(1,event)">1</a>'; if($rs2>2) echo '<span class="pdot">…</span>'; }
    for($p=$rs2;$p<=$re2;$p++){
        echo $p===$curPage?'<span class="pact">'.$p.'</span>':'<a href="#" onclick="ajaxPage('.$p.',event)">'.$p.'</a>';
    }
    if($re2<$totalPages){ if($re2<$totalPages-1) echo '<span class="pdot">…</span>'; echo '<a href="#" onclick="ajaxPage('.$totalPages.',event)">'.$totalPages.'</a>'; }
    if($curPage<$totalPages) echo '<a href="#" onclick="ajaxPage('.($curPage+1).',event)"><i class="fas fa-angle-right"></i></a><a href="#" onclick="ajaxPage('.$totalPages.',event)"><i class="fas fa-angle-double-right"></i></a>';
    echo '</div>';
    echo '<div class="pg-jump"><span>পেজ:</span><input type="number" id="pgJumpInput" min="1" max="'.$totalPages.'" value="'.$curPage.'"><button onclick="jumpToPage()">যান</button></div>';
endif;?>
</div>

</div><!-- /sk-card -->
</main>

<!-- ══════════════════════════════
     FLOATING MULTI-SELECT BAR
     ══════════════════════════════ -->
<div class="sel-bar" id="selBar">
    <div class="sel-bar-top">
        <div>
            <span class="sel-bar-title">মার্ক করুন — </span>
            <span class="sel-bar-cnt" id="selCountBadge">0 টি</span>
        </div>
        <button class="sel-bar-close" onclick="clearSelection()"><i class="fas fa-times"></i></button>
    </div>

    <textarea id="selNote" class="sel-bar-note" rows="2" maxlength="500" placeholder="কারণ / নোট লিখুন (শুধু আপনি দেখতে পাবেন) — ঐচ্ছিক"></textarea>

    <div class="color-btns">
        <button class="cb cb-none sel" data-color="" onclick="pickColor(this)">
            <span class="c-dot"></span>মার্ক নেই
        </button>
        <button class="cb cb-y" data-color="yellow" onclick="pickColor(this)">
            <span class="c-dot"></span>হলুদ
        </button>
        <button class="cb cb-g" data-color="green" onclick="pickColor(this)">
            <span class="c-dot"></span>সবুজ
        </button>
        <button class="cb cb-p" data-color="purple" onclick="pickColor(this)">
            <span class="c-dot"></span>বেগুনি
        </button>
    </div>

    <button class="btn-save" onclick="saveMarks()">
        <i class="fas fa-save"></i> সেভ করুন
    </button>
</div>

<!-- ══ CLEAR ALL MODAL ══ -->
<div class="modal-overlay" id="clearModal">
<div class="modal-box">
    <h3><i class="fas fa-eraser" style="color:#ef4444"></i> সব মার্ক ক্লিয়ার</h3>
    <p>এডমিন পাসওয়ার্ড দিলে সকল কালার ও নোট ডাটাবেজ থেকে মুছে যাবে।</p>
    <input type="password" id="clearPw" class="modal-pw" placeholder="পাসওয়ার্ড লিখুন...">
    <div class="modal-err" id="clearErr"><i class="fas fa-exclamation-circle"></i> <span id="clearErrMsg"></span></div>
    <div class="modal-btns">
        <button class="btn-cancel" onclick="closeClearAll()">বাতিল</button>
        <button class="btn-del" id="clearBtn" onclick="submitClearAll()"><i class="fas fa-trash-alt"></i> ক্লিয়ার</button>
    </div>
</div>
</div>

<!-- ══════════ JAVASCRIPT ══════════ -->
<script>
const CSRF = '<?php echo $csrfToken;?>';
let curPage   = <?php echo $curPage;?>;
let curSearch = <?php echo json_encode($search);?>;
let curCat    = <?php echo $catFilter;?>;
let curColorF = <?php echo json_encode($colorFilter);?>;
let ajxTimer  = null;

/* Colors meta */
const BG = {yellow:'#fef9c3', green:'#d1fae5', purple:'#ede9fe'};
const BD = {yellow:'#f59e0b', green:'#10b981',  purple:'#8b5cf6'};

/* ════════════════════════
   MULTI-SELECT
   ════════════════════════ */
let selected = new Set(); /* Set of item IDs */
let chosenColor = '';

function toggleSelect(row) {
    const id = row.dataset.id;
    if (selected.has(id)) {
        selected.delete(id);
        row.classList.remove('selected');
    } else {
        selected.add(id);
        row.classList.add('selected');
    }
    updateSelBar();
}

function updateSelBar() {
    const bar = document.getElementById('selBar');
    const cnt = selected.size;
    document.getElementById('selCountBadge').textContent = cnt + ' টি';
    if (cnt > 0) { bar.classList.add('open'); }
    else         { bar.classList.remove('open'); }
}

function clearSelection() {
    selected.clear();
    document.querySelectorAll('.ur.selected').forEach(r => r.classList.remove('selected'));
    updateSelBar();
    /* reset color picker */
    chosenColor = '';
    document.querySelectorAll('.cb').forEach(b => b.classList.toggle('sel', b.dataset.color === ''));
}

function pickColor(btn) {
    chosenColor = btn.dataset.color;
    document.querySelectorAll('.cb').forEach(b => b.classList.toggle('sel', b === btn));
}

function saveMarks() {
    if (selected.size === 0) { showToast('কোনো পণ্য সিলেক্ট করা হয়নি!','#f59e0b'); return; }
    const note = document.getElementById('selNote').value.trim();
    const btn  = document.querySelector('.btn-save');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> সেভ হচ্ছে...';
    btn.disabled = true;

    fetch('unsold_inventory.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'ajax_action=bulk_mark&csrf_token='+encodeURIComponent(CSRF)
             +'&ids='+[...selected].join(',')
             +'&color='+encodeURIComponent(chosenColor)
             +'&note='+encodeURIComponent(note)
    }).then(r=>r.json()).then(d=>{
        if (d.status === 'success') {
            /* Update rows visually */
            selected.forEach(id => {
                const row = document.querySelector('.ur[data-id="'+id+'"]');
                if (!row) return;
                row.dataset.color = chosenColor;
                row.dataset.note  = note;
                if (chosenColor) {
                    row.style.background = BG[chosenColor];
                    row.style.borderLeft = '3.5px solid ' + BD[chosenColor];
                    row.querySelector('.ur-dot').style.background = BD[chosenColor];
                } else {
                    row.style.background = '';
                    row.style.borderLeft = '3.5px solid transparent';
                    row.querySelector('.ur-dot').style.background = '#e5e7eb';
                }
                /* note preview */
                let np = row.querySelector('.ur-note');
                if (note) {
                    if (!np) { np = document.createElement('span'); np.className='ur-note'; row.querySelector('.ur-info').appendChild(np); }
                    np.innerHTML = '<i class="fas fa-sticky-note"></i> ' + (note.length>32?note.substring(0,32)+'…':note);
                } else { if(np) np.remove(); }
                row.classList.remove('selected');
            });
            showToast('✅ ' + d.updated + ' টি পণ্য সেভ হয়েছে!', '#10b981');
            selected.clear();
            document.getElementById('selNote').value = '';
            updateSelBar();
        } else { showToast('❌ ' + (d.message||'এরর!'), '#ef4444'); }
        btn.innerHTML = '<i class="fas fa-save"></i> সেভ করুন';
        btn.disabled = false;
    }).catch(()=>{
        showToast('❌ সার্ভার এরর!','#ef4444');
        btn.innerHTML = '<i class="fas fa-save"></i> সেভ করুন';
        btn.disabled = false;
    });
}

/* ════════════════════════
   AJAX SEARCH + PAGE
   ════════════════════════ */
document.getElementById('srchInput').addEventListener('input', function(){
    clearTimeout(ajxTimer);
    const q = this.value.trim();
    ajxTimer = setTimeout(()=>{ ajaxLoad(1,q,curCat,curColorF); }, 450);
});
document.getElementById('srchInput').addEventListener('keydown', function(e){
    if(e.key==='Enter'){ e.preventDefault(); clearTimeout(ajxTimer); ajaxLoad(1,this.value.trim(),curCat,curColorF); }
});

function ajaxPage(pg,e){ if(e) e.preventDefault(); ajaxLoad(pg,curSearch,curCat,curColorF); }
function jumpToPage(){ const v=parseInt(document.getElementById('pgJumpInput').value); if(v>=1) ajaxLoad(v,curSearch,curCat,curColorF); }

function ajaxLoad(pg,srch,cat,cf){
    curPage=pg; curSearch=srch; curCat=cat; curColorF=cf;
    document.getElementById('srchSpin').style.display='block';
    clearSelection();
    const url='unsold_inventory.php?ajax=1&page='+pg+'&search='+encodeURIComponent(srch)+'&category='+cat+'&color_filter='+encodeURIComponent(cf||'');
    fetch(url).then(r=>r.json()).then(d=>{
        document.getElementById('ajaxList').innerHTML    = d.html;
        document.getElementById('pagerArea').innerHTML   = d.pager;
        document.getElementById('stCnt').textContent     = d.cnt;
        document.getElementById('stPcs').textContent     = Number(d.pagePcs).toLocaleString();
        document.getElementById('stVal').textContent     = '৳'+Math.round(d.pageVal).toLocaleString();
        document.getElementById('infoBar').innerHTML     =
            'মোট <strong>'+d.total+'</strong> | দেখাচ্ছে <strong>'+d.ss+'</strong>–<strong>'+d.se+'</strong>'
            +'<span class="info-pg">পেজ '+d.curPage+'/'+d.pages+'</span>';
        document.getElementById('srchSpin').style.display='none';
        window.scrollTo({top:document.querySelector('.us-table').offsetTop-80,behavior:'smooth'});
    }).catch(()=>{ document.getElementById('srchSpin').style.display='none'; showToast('লোড হয়নি!','#ef4444'); });
}

/* ════════════════════════
   CLEAR ALL
   ════════════════════════ */
function openClearAll(){ document.getElementById('clearModal').classList.add('open'); document.getElementById('clearPw').value=''; document.getElementById('clearErr').style.display='none'; setTimeout(()=>document.getElementById('clearPw').focus(),200); }
function closeClearAll(){ document.getElementById('clearModal').classList.remove('open'); }
document.getElementById('clearPw').addEventListener('keydown',e=>{ if(e.key==='Enter') submitClearAll(); });
document.getElementById('clearModal').addEventListener('click',function(e){ if(e.target===this) closeClearAll(); });

function submitClearAll(){
    const pw=document.getElementById('clearPw').value.trim();
    const btn=document.getElementById('clearBtn'),err=document.getElementById('clearErr');
    if(!pw){ err.style.display='block'; document.getElementById('clearErrMsg').textContent='পাসওয়ার্ড খালি!'; return; }
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>'; btn.disabled=true; err.style.display='none';
    fetch('unsold_inventory.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'ajax_action=clear_all&csrf_token='+encodeURIComponent(CSRF)+'&password='+encodeURIComponent(pw)
    }).then(r=>r.json()).then(d=>{
        if(d.status==='success'){
            closeClearAll();
            showToast('✅ সব মার্ক ক্লিয়ার!','#10b981');
            setTimeout(()=>location.reload(),700);
        } else {
            err.style.display='block'; document.getElementById('clearErrMsg').textContent=d.message||'পাসওয়ার্ড ভুল!';
            btn.innerHTML='<i class="fas fa-trash-alt"></i> ক্লিয়ার'; btn.disabled=false;
        }
    }).catch(()=>{ btn.innerHTML='<i class="fas fa-trash-alt"></i> ক্লিয়ার'; btn.disabled=false; });
}

/* ════════════════════════
   TOAST + SIDEBAR
   ════════════════════════ */
function showToast(msg,color){
    const t=document.createElement('div');
    t.className='toast'; t.textContent=msg;
    t.style.cssText='bottom:'+(document.getElementById('selBar').classList.contains('open')?'175':'76')+'px;background:'+color+';color:#fff;left:50%;transform:translateX(-50%);';
    document.body.appendChild(t);
    setTimeout(()=>{ t.style.opacity='0'; setTimeout(()=>t.remove(),350); },1900);
}
function toggleSidebar(){
    document.getElementById('mySidebar').classList.toggle('open');
    document.getElementById('myOverlay').classList.toggle('active');
}

/* Keyboard shortcut: Esc = clear selection */
document.addEventListener('keydown',e=>{ if(e.key==='Escape') clearSelection(); });
</script>
</body>
</html>
