<?php
declare(strict_types=1);require dirname(__DIR__).'/lib.php';admin_required();
$statuses=['received'=>'접수','processing'=>'처리중','resolved'=>'처리완료'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{check_csrf();$id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');$memo=trim((string)($_POST['memo']??''));
 if($id<1||!isset($statuses[$status])||mb_strlen($memo)>1000)throw new RuntimeException('입력값을 확인해 주세요.');
 $s=db()->prepare('UPDATE apple_as_requests SET status=?,admin_memo=?,resolved_at=CASE WHEN ?="resolved" THEN COALESCE(resolved_at,NOW()) ELSE NULL END WHERE id=?');$s->execute([$status,$memo,$status,$id]);$_SESSION['apple_as_flash']='AS 처리내용을 저장했습니다.';
 }catch(Throwable $e){$_SESSION['apple_as_flash']='저장 실패: 입력값을 확인해 주세요.';}
 header('Location: as.php');exit;
}
$filter=(string)($_GET['status']??'all');if($filter!=='all'&&!isset($statuses[$filter]))$filter='all';
$page=max(1,(int)($_GET['page']??1));$per=30;$where=$filter==='all'?'':' WHERE a.status=?';$params=$filter==='all'?[]:[$filter];
$s=db()->prepare('SELECT COUNT(*) FROM apple_as_requests a'.$where);$s->execute($params);$total=(int)$s->fetchColumn();
$s=db()->prepare('SELECT a.*,o.order_no,o.recipient_name,o.phone,o.product_name,o.quantity,o.road_address,o.detail_address,o.tracking_no FROM apple_as_requests a JOIN apple_orders o ON o.id=a.order_id'.$where.' ORDER BY a.created_at DESC,a.id DESC LIMIT '.$per.' OFFSET '.(($page-1)*$per));$s->execute($params);$rows=$s->fetchAll();
layout_start('사과농장 AS 관리'); ?>
<style>
.as-page{max-width:1100px;margin:0 auto}.as-page *{box-sizing:border-box}.as-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.as-head h1{margin:0 0 7px;font-size:clamp(23px,4vw,30px)}.as-nav{display:flex;flex-wrap:wrap;gap:8px;margin:18px 0}.as-nav a,.as-nav button{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;padding:10px 15px;border-radius:12px;border:1px solid #dce5dd;background:#fff;color:#284437;font-size:14px;cursor:pointer}.as-nav a.active{background:#246b43;color:#fff;border-color:#246b43}.as-items{display:grid;gap:16px}.as-item{border:1px solid #e1e8e2;border-radius:18px;padding:20px;background:#fff;box-shadow:0 4px 18px rgba(20,65,35,.045);min-width:0}.as-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px}.as-top strong{font-size:18px}.as-tag{font-size:13px;font-weight:700;border-radius:999px;padding:6px 12px;background:#f0f4f1;color:#476353}.as-tag.processing{background:#fff4df;color:#8b5700}.as-tag.resolved{background:#e5f5e9;color:#176a35}.as-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 24px}.as-field{min-width:0;overflow-wrap:anywhere}.as-label{font-size:12px;color:#66776c;margin-bottom:5px}.as-value{font-size:15px;color:#233b2c}.as-detail{background:#f6f8f5;border-radius:12px;padding:14px;margin-top:17px;white-space:pre-wrap;overflow-wrap:anywhere}.as-manage{margin-top:18px;border-top:1px solid #e7eee8;padding-top:16px}.as-manage-grid{display:grid;grid-template-columns:minmax(170px,240px) minmax(0,1fr) auto;gap:12px;align-items:end}.as-manage select,.as-manage textarea{display:block;width:100%;max-width:100%;min-width:0;margin:0;border:1px solid #d0dbd2;border-radius:10px;padding:10px;font:inherit;background:white}.as-manage textarea{min-height:84px;resize:vertical}.as-save{min-height:44px;padding:10px 20px;background:#216b3c;color:white;border:0;border-radius:10px;cursor:pointer}.as-pages{display:flex;gap:8px;margin-top:16px}.as-pages a{padding:10px 15px;border:1px solid #d5e0d7;border-radius:10px;text-decoration:none}.as-empty{padding:36px;text-align:center;background:#f7f9f7;border-radius:14px}.as-notice{padding:12px 14px;border-radius:10px;background:#edf6ef;margin-bottom:16px}
@media(max-width:720px){.as-item{padding:15px}.as-grid{grid-template-columns:1fr 1fr;gap:12px}.as-manage-grid{grid-template-columns:1fr}.as-save{width:100%}.as-nav a,.as-nav button{flex:1 1 auto}}
@media(max-width:420px){.as-grid{grid-template-columns:1fr}.as-top strong{font-size:16px}}
@media print{.as-nav,.as-manage,.as-pages,.as-noprint{display:none!important}.as-item{break-inside:avoid;box-shadow:none;border:1px solid #bbb;margin-bottom:12px}.as-page{max-width:none}.as-items{display:block}}
</style>
<div class="as-page">
 <div class="as-head"><div><h1>🍎 AS 접수 관리</h1><p class="muted">접수내용 확인 및 처리상태 관리 · 현재 조건 <?=number_format($total)?>건</p></div></div>
 <nav class="as-nav as-noprint"><a href="index.php">주문관리</a><a class="<?=$filter==='all'?'active':''?>" href="as.php?status=all">전체</a><?php foreach($statuses as $k=>$v):?><a class="<?=$filter===$k?'active':''?>" href="as.php?status=<?=h($k)?>"><?=h($v)?></a><?php endforeach;?><button type="button" onclick="window.print()">인쇄</button></nav>
 <?php if(!empty($_SESSION['apple_as_flash'])):?><div class="as-notice"><?=h($_SESSION['apple_as_flash'])?></div><?php unset($_SESSION['apple_as_flash']);endif;?>
 <div class="as-items">
 <?php foreach($rows as $r):?>
 <article class="as-item">
 <div class="as-top"><strong>AS #<?=(int)$r['id']?> <span style="font-size:13px;font-weight:400;color:#718075">· <?=h($r['created_at'])?></span></strong><span class="as-tag <?=h($r['status'])?>"><?=h($statuses[$r['status']]??$r['status'])?></span></div>
 <div class="as-grid">
 <div class="as-field"><div class="as-label">수령자</div><div class="as-value"><strong><?=h($r['recipient_name'])?></strong></div></div>
 <div class="as-field"><div class="as-label">연락처</div><div class="as-value"><a href="tel:<?=h(phone_clean($r['phone']))?>"><?=h($r['phone'])?></a></div></div>
 <div class="as-field"><div class="as-label">상품 / 수량</div><div class="as-value"><?=h($r['product_name'])?> × <?=number_format((int)$r['quantity'])?></div></div>
 <div class="as-field"><div class="as-label">주문번호</div><div class="as-value"><?=h($r['order_no'])?></div></div>
 <div class="as-field"><div class="as-label">CJ 송장번호</div><div class="as-value"><?=h($r['tracking_no']?:'미등록')?></div></div>
 <div class="as-field"><div class="as-label">처리완료 일시</div><div class="as-value"><?=h($r['resolved_at']?:'미완료')?></div></div>
 </div>
 <div class="as-detail"><div class="as-label">AS 유형 / 접수내용</div><strong><?=h($r['reason'])?></strong><div style="margin-top:6px"><?=h($r['detail'])?></div></div>
 <form class="as-manage" method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><div class="as-manage-grid"><div><label class="as-label" for="as-status-<?=(int)$r['id']?>">처리상태</label><select id="as-status-<?=(int)$r['id']?>" name="status"><?php foreach($statuses as $k=>$v):?><option value="<?=h($k)?>" <?=$r['status']===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select></div><div><label class="as-label" for="as-memo-<?=(int)$r['id']?>">관리자 처리메모</label><textarea id="as-memo-<?=(int)$r['id']?>" name="memo" maxlength="1000" rows="3" placeholder="고객 연락, 교환·재배송 등 처리내용"><?=h($r['admin_memo']??'')?></textarea></div><button class="as-save" type="submit">처리내용 저장</button></div></form>
 </article>
 <?php endforeach;?>
 <?php if(!$rows):?><div class="as-empty">해당 조건의 AS 접수내역이 없습니다.</div><?php endif;?>
 </div>
 <div class="as-pages as-noprint"><?php if($page>1):?><a href="?status=<?=h($filter)?>&page=<?=$page-1?>">이전</a><?php endif;?><?php if($page*$per<$total):?><a href="?status=<?=h($filter)?>&page=<?=$page+1?>">다음</a><?php endif;?></div>
</div>
<?php layout_end();
