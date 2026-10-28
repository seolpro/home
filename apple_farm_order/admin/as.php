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
<div class="card"><div class="no-print"><h1>🍎 AS 접수 관리</h1><p class="muted">전체 <?=number_format($total)?>건 · 접수내용 확인 및 처리상태 관리</p><div class="actions"><a class="btn secondary" href="index.php">주문관리</a><a class="btn secondary" href="as.php?status=all">전체</a><?php foreach($statuses as $k=>$v):?><a class="btn secondary" href="as.php?status=<?=h($k)?>"><?=h($v)?></a><?php endforeach;?><button onclick="window.print()" class="secondary">인쇄</button></div><?php if(!empty($_SESSION['apple_as_flash'])):?><p class="notice"><?=h($_SESSION['apple_as_flash'])?></p><?php unset($_SESSION['apple_as_flash']);endif;?></div>
<div class="tablewrap"><table><thead><tr><th>접수일시</th><th>주문번호 / 수령자</th><th>연락처</th><th>상품 / 송장</th><th>AS 유형 / 내용</th><th>상태 / 완료일시</th><th class="no-print">관리</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=h($r['created_at'])?></td><td><?=h($r['order_no'])?><br><?=h($r['recipient_name'])?></td><td><a href="tel:<?=h(phone_clean($r['phone']))?>"><?=h($r['phone'])?></a></td><td><?=h($r['product_name'])?> × <?=number_format((int)$r['quantity'])?><br><small><?=h($r['tracking_no']??'')?></small></td><td><strong><?=h($r['reason'])?></strong><br><div style="white-space:pre-wrap;min-width:200px"><?=h($r['detail'])?></div></td><td><?=h($statuses[$r['status']]??$r['status'])?><br><small><?=h($r['resolved_at']??'')?></small></td><td class="no-print"><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><select name="status"><?php foreach($statuses as $k=>$v):?><option value="<?=h($k)?>" <?=$r['status']===$k?'selected':''?>><?=h($v)?></option><?php endforeach;?></select><textarea name="memo" maxlength="1000" rows="2" placeholder="관리자 처리메모"><?=h($r['admin_memo']??'')?></textarea><button>저장</button></form></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="7">접수된 AS가 없습니다.</td></tr><?php endif;?></tbody></table></div><div class="actions no-print"><?php if($page>1):?><a class="btn secondary" href="?status=<?=h($filter)?>&page=<?=$page-1?>">이전</a><?php endif;?><?php if($page*$per<$total):?><a class="btn secondary" href="?status=<?=h($filter)?>&page=<?=$page+1?>">다음</a><?php endif;?></div></div>
<?php layout_end();
