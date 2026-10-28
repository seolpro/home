<?php require dirname(__DIR__).'/lib.php';admin_required();$status=(string)($_GET['status']??'all');if(!in_array($status,['all','received','shipped'],true))$status='all';$q=trim((string)($_GET['q']??''));$page=max(1,(int)($_GET['page']??1));$where=[];$args=[];if($status!=='all'){$where[]='status=?';$args[]=$status;}if($q!==''){$where[]='(recipient_name LIKE ? OR phone LIKE ? OR order_no LIKE ? OR road_address LIKE ? OR referrer LIKE ?)';foreach(range(1,5) as $_)$args[]='%'.$q.'%';}$filter=$where?' WHERE '.implode(' AND ',$where):'';$st=db()->prepare('SELECT COUNT(*) FROM apple_orders'.$filter);$st->execute($args);$total=(int)$st->fetchColumn();$per=($_GET['print']??'')==='1'?5000:30;$offset=($page-1)*$per;$st=db()->prepare('SELECT * FROM apple_orders'.$filter.' ORDER BY created_at DESC,id DESC LIMIT '.(int)$per.' OFFSET '.(int)$offset);$st->execute($args);$rows=$st->fetchAll();$qs=http_build_query(['q'=>$q,'status'=>$status]);layout_start('사과농장 주문관리');?><style>
/* 주문관리 화면에만 적용: 공통 lib.php 및 고객 화면 변경 없음 */
@media screen and (min-width: 1000px) {
  main { max-width: 1540px !important; width: calc(100% - 48px); padding: 0 !important; }
}
@media screen and (max-width: 999px) { main { max-width: none; } }
.tablewrap { width: 100%; overflow-x: auto; }
.tablewrap table { min-width: 1320px; }
.tablewrap th, .tablewrap td { vertical-align: middle; }
.tablewrap td:nth-child(4), .tablewrap td:nth-child(8) { white-space: normal; min-width: 170px; overflow-wrap: anywhere; }
.tablewrap td:nth-child(5) { white-space: normal; min-width: 120px; }
.tablewrap td:last-child { min-width: 220px; }
.tablewrap td:last-child form input { min-width: 0 !important; width: 100%; }
.tablewrap td:last-child form button { white-space: nowrap; }
@media print { main { max-width: none !important; width: auto !important; } .tablewrap table { min-width: 0; } }
</style><div class="card"><div class="no-print"><h1>🍎 농장 주문관리</h1><p class="muted">전체 <?=number_format($total)?>건 · 배송상태 관리 / 인쇄</p><?php if(!empty($_SESSION['apple_flash'])):?><p class="notice"><?=h($_SESSION['apple_flash'])?></p><?php unset($_SESSION['apple_flash']);endif;?><form method="get" class="grid"><div><label>검색 (성명·전화·주문번호·주소·추천인)</label><input name="q" value="<?=h($q)?>"></div><div><label>배송상태</label><select name="status"><option value="all" <?=$status==='all'?'selected':''?>>전체</option><option value="received" <?=$status==='received'?'selected':''?>>배송대기</option><option value="shipped" <?=$status==='shipped'?'selected':''?>>배송처리</option></select></div><div class="actions"><button>검색</button><a class="btn secondary" href="index.php?<?=h($qs)?>&print=1" target="_blank" rel="noopener">인쇄용 목록</a><a class="btn secondary" href="export.php?<?=h($qs)?>">CSV 다운로드</a><a class="btn secondary" href="logout.php">로그아웃</a></div></form></div><div class="tablewrap"><table><thead><tr><th>접수일시 / 주문번호</th><th>수령자</th><th>연락처</th><th>배송주소</th><th>상품</th><th>수량</th><th>추천인</th><th>배송요청</th><th>배송상태 / 처리일시 / 송장번호</th><th class="no-print">처리</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=h($r['created_at'])?><br><small><?=h($r['order_no'])?></small></td><td><?=h($r['recipient_name'])?></td><td><a href="tel:<?=h($r['phone'])?>"><?=h($r['phone'])?></a></td><td>(<?=h($r['postcode'])?>) <?=h($r['road_address'])?> <?=h($r['detail_address'])?></td><td><?=h($r['product_name'])?></td><td><?=number_format((int)$r['quantity'])?></td><td><?=h($r['referrer']??'')?></td><td style="white-space:normal;min-width:120px"><?=nl2br(h($r['delivery_request']))?></td><td><?=($r['status']==='shipped'?'배송처리':'배송대기')?><br><small><?=h($r['shipped_at']??'')?></small><?php if(!empty($r['tracking_no'])):?><br><small>CJ <?=h($r['tracking_no'])?></small><?php endif;?></td><td class="no-print"><form method="post" action="update.php" onsubmit="return appleCheckShipping(this)"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="status" value="<?=$r['status']==='shipped'?'received':'shipped'?>"><?php if($r['status']!=='shipped'):?><input name="tracking_no" inputmode="numeric" pattern="[0-9]{12}" minlength="12" maxlength="12" title="CJ대한통운 송장번호 숫자 12자리를 입력하세요" required placeholder="CJ 송장번호 (숫자 12자리)" style="min-width:175px;margin-bottom:6px" oninput="appleTrackingInput(this)" onblur="appleTrackingBlur(this)"><small class="tracking-help" aria-live="polite" style="display:block;margin-bottom:6px;color:#64748b">숫자 12자리를 입력해 주세요 (0/12)</small><button type="submit">배송처리 + 알림톡</button><?php else:?><button class="secondary" onclick="return confirm('배송처리를 취소하시겠습니까? 기존 발송 알림톡은 취소되지 않습니다.')">처리취소</button><?php endif;?></form></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="10">검색된 주문이 없습니다.</td></tr><?php endif;?></tbody></table></div><div class="actions no-print"><?php if($page>1):?><a class="btn secondary" href="?<?=h($qs)?>&page=<?=$page-1?>">이전</a><?php endif;?><?php if($page*$per<$total):?><a class="btn secondary" href="?<?=h($qs)?>&page=<?=$page+1?>">다음</a><?php endif;?></div></div><script>
function appleTrackingMessage(input,force){
 const msg=input.parentElement.querySelector('.tracking-help');if(!msg)return;
 const n=input.value.length;
 if(n===12){msg.textContent='✓ 12자리 입력 완료';msg.style.color='#16703c';}
 else {msg.textContent=(force||n>0?'⚠ 송장번호는 숫자 12자리로 입력해 주세요. ':'숫자 12자리를 입력해 주세요. ')+`(${n}/12)`;msg.style.color=(force||n>0)?'#b42318':'#64748b';}
}
function appleTrackingInput(input){input.value=input.value.replace(/\D/g,'').slice(0,12);appleTrackingMessage(input,false);}
function appleTrackingBlur(input){appleTrackingMessage(input,true);}
function appleCheckShipping(form){
 const input=form.querySelector('input[name="tracking_no"]');
 if(input){
  appleTrackingMessage(input,true);
  if(!/^[0-9]{12}$/.test(input.value)){
   alert('CJ대한통운 송장번호는 숫자 12자리로 입력해야 합니다. 현재 '+input.value.length+'자리입니다.');input.focus();return false;
  }
  if(!confirm('송장번호 '+input.value+'\n수령자 연락처를 확인하셨나요? 배송처리 후 알림톡 발송을 요청합니다.'))return false;
  form.querySelector('button[type="submit"]').disabled=true;
 }
 return true;
}
</script><?php if(($_GET['print']??'')==='1'):?><script>window.addEventListener('load',()=>window.print())</script><?php endif;layout_end();
