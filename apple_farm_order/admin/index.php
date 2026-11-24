<?php
require dirname(__DIR__).'/lib.php';
admin_required();
$status=(string)($_GET['status']??'all');
if(!in_array($status,['all','received','shipped'],true))$status='all';
$payment=(string)($_GET['payment']??'all');
if(!in_array($payment,['all','unpaid','paid'],true))$payment='all';
$q=trim((string)($_GET['q']??''));
$page=max(1,(int)($_GET['page']??1));
$print=(($_GET['print']??'')==='1');
$where=[];$args=[];
if($status!=='all'){$where[]='status=?';$args[]=$status;}
if($payment!=='all'){$where[]='payment_confirmed=?';$args[]=($payment==='paid'?1:0);}
if($q!==''){$where[]='(recipient_name LIKE ? OR phone LIKE ? OR order_no LIKE ? OR road_address LIKE ? OR referrer LIKE ?)';for($i=0;$i<5;$i++)$args[]='%'.$q.'%';}
$filter=$where?' WHERE '.implode(' AND ',$where):'';
$st=db()->prepare('SELECT COUNT(*) FROM apple_orders'.$filter);$st->execute($args);$total=(int)$st->fetchColumn();
$per=$print?5000:30;$offset=($page-1)*$per;
$st=db()->prepare('SELECT * FROM apple_orders'.$filter.' ORDER BY created_at DESC,id DESC LIMIT '.(int)$per.' OFFSET '.(int)$offset);
$st->execute($args);$rows=$st->fetchAll();
$qs=http_build_query(['q'=>$q,'status'=>$status,'payment'=>$payment]);
layout_start('사과농장 주문관리');
?>
<style>
@media screen and (min-width:1000px){main{max-width:1740px!important;width:calc(100% - 48px);padding:0!important}}
@media screen and (max-width:999px){main{max-width:none}}
.tablewrap{width:100%;overflow-x:auto}
.tablewrap table{min-width:1560px}
.tablewrap th,.tablewrap td{vertical-align:middle}
.tablewrap td:nth-child(5),.tablewrap td:nth-child(9){white-space:normal;min-width:170px;overflow-wrap:anywhere}
.tablewrap td:nth-child(6){white-space:normal;min-width:120px}
.tablewrap td:nth-child(11){min-width:220px}
.tablewrap td:nth-child(11) form input{min-width:0!important;width:100%}
.tablewrap td:nth-child(11) form button{white-space:nowrap}
.tablewrap td:nth-child(12){min-width:235px}
.bulk-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:12px 0;padding:10px;border:1px solid #dbe3ed;border-radius:9px}.bulk-tools button{white-space:nowrap}.bulk-select{width:19px;height:19px;cursor:pointer}.payment-form{display:flex;flex-direction:column;gap:7px}
.payment-form label{display:flex;align-items:center;gap:7px;white-space:nowrap;font-size:14px;cursor:pointer}
.payment-form input[type=checkbox]{width:18px;height:18px;flex:none;margin:0}
.payment-form input[type=text]{width:100%;min-width:0;box-sizing:border-box;padding:8px}
.payment-form button{white-space:nowrap}
.payment-date{font-size:12px;color:#64748b;white-space:normal}
.payment-badge{font-size:12px;font-weight:700}
.payment-badge.paid{color:#16703c}.payment-badge.unpaid{color:#b45309}
@media print{main{max-width:none!important;width:auto!important}.tablewrap{overflow:visible}.tablewrap table{min-width:0!important;font-size:10px}.tablewrap th,.tablewrap td{padding:4px!important}.no-print{display:none!important}}
.cj-tracking-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 7px 13px;
  background: #2563eb;
  color: #fff !important;
  border: 1px solid #2563eb;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none !important;
  white-space: nowrap;
  transition: background 0.15s ease;
}

.cj-tracking-btn:hover {
  background: #1d4ed8;
  border-color: #1d4ed8;
  color: #fff !important;
}

@media print {
  .cj-tracking-btn {
    display: none !important;
  }
}
</style>
<div class="card">
<div class="no-print">
<h1>🍎 농장 주문관리</h1>
<p class="muted">검색결과 <?=number_format($total)?>건 · 배송상태 / 입금확인 / 인쇄</p>
<?php if(!empty($_SESSION['apple_flash'])):?><p class="notice"><?=h($_SESSION['apple_flash'])?></p><?php unset($_SESSION['apple_flash']);endif;?>
<form method="get" class="grid">
<div><label>검색 (성명·전화·주문번호·주소·추천인)</label><input name="q" value="<?=h($q)?>"></div>
<div><label>배송상태</label><select name="status"><option value="all" <?=$status==='all'?'selected':''?>>전체</option><option value="received" <?=$status==='received'?'selected':''?>>배송대기</option><option value="shipped" <?=$status==='shipped'?'selected':''?>>배송처리</option></select></div>
<div><label>입금상태</label><select name="payment"><option value="all" <?=$payment==='all'?'selected':''?>>전체</option><option value="unpaid" <?=$payment==='unpaid'?'selected':''?>>미입금</option><option value="paid" <?=$payment==='paid'?'selected':''?>>입금완료</option></select></div>
<div class="actions"><button>검색</button><a class="btn secondary" href="index.php?<?=h($qs)?>&print=1" target="_blank" rel="noopener">인쇄용 목록</a><a class="btn secondary" href="export.php?<?=h($qs)?>">CSV 다운로드</a><a class="btn secondary" href="logout.php">로그아웃</a></div>
</form>
<?php if(!$print):?>
<form id="appleBulkPayment" method="post" action="bulk_payment.php" onsubmit="return appleCheckBulkPayment(this)">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="bulk_action" id="appleBulkAction" value="">
<input type="hidden" name="return_qs" value="<?=h($qs)?>">
<input type="hidden" name="return_page" value="<?=$page?>">
</form>
<div class="bulk-tools">
<label><input type="checkbox" class="bulk-select" id="appleSelectAll" onchange="appleToggleAll(this.checked)"> 현재 페이지 전체 선택 / 해제</label>
<strong id="appleSelectedCount">0건 선택</strong>
<button type="submit" form="appleBulkPayment" name="bulk_submit" value="paid" onclick="document.getElementById('appleBulkAction').value='paid'">선택 입금확인</button>
<button type="submit" form="appleBulkPayment" name="bulk_submit" value="unpaid" class="secondary" onclick="document.getElementById('appleBulkAction').value='unpaid'">선택 입금확인 해제</button>
<small class="muted">현재 페이지의 선택 주문만 일괄 변경 · 메모는 유지</small>
</div>
<?php endif;?></div>
<div class="tablewrap"><table><thead><tr><th class="no-print">선택</th><th>접수일시 / 주문번호</th><th>수령자</th><th>연락처</th><th>배송주소</th><th>상품</th><th>수량</th><th>추천인</th><th>배송요청</th><th>배송상태 / 처리일시 / 송장번호</th><th class="no-print">처리</th><th>입금확인 / 관리자 메모</th></tr></thead><tbody>
<?php foreach($rows as $r): $isPaid=((int)($r['payment_confirmed']??0)===1); ?>
<tr>
<td class="no-print"><input type="checkbox" class="bulk-select apple-bulk-row" name="ids[]" value="<?=(int)$r['id']?>" form="appleBulkPayment" onchange="appleUpdateSelected()" aria-label="<?=h($r['order_no'])?> 주문 선택"></td>
<td><?=h($r['created_at'])?><br><small><?=h($r['order_no'])?></small></td>
<td><?=h($r['recipient_name'])?></td>
<td><a href="tel:<?=h($r['phone'])?>"><?=h($r['phone'])?></a></td>
<td>(<?=h($r['postcode'])?>) <?=h($r['road_address'])?> <?=h($r['detail_address'])?></td>
<td><?=h($r['product_name'])?></td><td><?=number_format((int)$r['quantity'])?></td><td><?=h($r['referrer']??'')?></td>
<td style="white-space:normal;min-width:120px"><?=nl2br(h($r['delivery_request']))?></td>
<td><?=($r['status']==='shipped'?'배송처리':'배송대기')?><br><small><?=h($r['shipped_at']??'')?></small><?php if(!empty($r['tracking_no'])):?>
  <br><small>CJ <?=h($r['tracking_no'])?></small>

  <?php if(
    $r['status']==='shipped' &&
    preg_match('/^[0-9]{12}$/', (string)$r['tracking_no'])
  ):?>
    <div class="no-print" style="margin-top:7px;">
      <a class="cj-tracking-btn"
         href="https://trace.cjlogistics.com/next/tracking.html?wblNo=<?=h(rawurlencode((string)$r['tracking_no']))?>"
         target="_blank"
         rel="noopener noreferrer">
        🚚 배송조회 ↗
      </a>
    </div>
  <?php endif;?>

<?php endif;?></td>
<td class="no-print"><form method="post" action="update.php" onsubmit="return appleCheckShipping(this)"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="status" value="<?=$r['status']==='shipped'?'received':'shipped'?>">
<?php if($r['status']!=='shipped'):?><input name="tracking_no" inputmode="numeric" pattern="[0-9]{12}" minlength="12" maxlength="12" title="CJ대한통운 송장번호 숫자 12자리를 입력하세요" required placeholder="CJ 송장번호 (숫자 12자리)" style="min-width:175px;margin-bottom:6px" oninput="appleTrackingInput(this)" onblur="appleTrackingBlur(this)"><small class="tracking-help" aria-live="polite" style="display:block;margin-bottom:6px;color:#64748b">숫자 12자리를 입력해 주세요 (0/12)</small><button type="submit">배송처리 + 알림톡</button>
<?php else:?><button class="secondary" onclick="return confirm('배송처리를 취소하시겠습니까? 기존 발송 알림톡은 취소되지 않습니다.')">처리취소</button><?php endif;?></form></td>
<td>
<div class="payment-badge <?=$isPaid?'paid':'unpaid'?>"><?=$isPaid?'● 입금완료':'● 미입금'?></div>
<?php if($print):?>
<div class="payment-date"><?=h($r['payment_confirmed_at']??'')?></div><div><?=h($r['admin_memo']??'')?></div>
<?php else:?>
<form method="post" action="update.php" class="payment-form" onsubmit="return appleCheckPayment(this)">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="payment">
<label><input type="checkbox" name="payment_confirmed" value="1" <?=$isPaid?'checked':''?>> 입금확인</label>
<?php if($isPaid):?><div class="payment-date">확인일시: <?=h($r['payment_confirmed_at']??'')?></div><?php endif;?>
<input type="text" name="admin_memo" maxlength="100" value="<?=h($r['admin_memo']??'')?>" placeholder="관리자 메모 (100자 이내)" aria-label="관리자 메모">
<button type="submit" class="secondary">입금 / 메모 저장</button>
</form>
<?php endif;?></td>
</tr>
<?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="12">검색된 주문이 없습니다.</td></tr><?php endif;?>
</tbody></table></div>
<div class="actions no-print"><?php if($page>1):?><a class="btn secondary" href="?<?=h($qs)?>&page=<?=$page-1?>">이전</a><?php endif;?><?php if($page*$per<$total):?><a class="btn secondary" href="?<?=h($qs)?>&page=<?=$page+1?>">다음</a><?php endif;?></div>
</div>
<script>
function appleToggleAll(checked){document.querySelectorAll('.apple-bulk-row').forEach(el=>el.checked=checked);appleUpdateSelected();}
function appleUpdateSelected(){const rows=[...document.querySelectorAll('.apple-bulk-row')];const count=rows.filter(el=>el.checked).length;const all=document.getElementById('appleSelectAll');if(all){all.checked=rows.length>0&&count===rows.length;all.indeterminate=count>0&&count<rows.length;}const display=document.getElementById('appleSelectedCount');if(display)display.textContent=count+'건 선택';}
function appleCheckBulkPayment(form){const count=document.querySelectorAll('.apple-bulk-row:checked').length;if(!count){alert('일괄 처리할 주문을 선택해 주세요.');return false;}const action=form.querySelector('[name="bulk_action"]').value;if(!['paid','unpaid'].includes(action))return false;return confirm('선택한 '+count+'건을 '+(action==='paid'?'입금완료':'미입금')+' 상태로 일괄 변경하시겠습니까?\n관리자 메모는 변경되지 않습니다.');}
function appleTrackingMessage(input,force){const msg=input.parentElement.querySelector('.tracking-help');if(!msg)return;const n=input.value.length;if(n===12){msg.textContent='✓ 12자리 입력 완료';msg.style.color='#16703c';}else{msg.textContent=(force||n>0?'⚠ 송장번호는 숫자 12자리로 입력해 주세요. ':'숫자 12자리를 입력해 주세요. ')+`(${n}/12)`;msg.style.color=(force||n>0)?'#b42318':'#64748b';}}
function appleTrackingInput(input){input.value=input.value.replace(/\D/g,'').slice(0,12);appleTrackingMessage(input,false);}
function appleTrackingBlur(input){appleTrackingMessage(input,true);}
function appleCheckShipping(form){const input=form.querySelector('input[name="tracking_no"]');if(input){appleTrackingMessage(input,true);if(!/^[0-9]{12}$/.test(input.value)){alert('CJ대한통운 송장번호는 숫자 12자리로 입력해야 합니다. 현재 '+input.value.length+'자리입니다.');input.focus();return false;}if(!confirm('송장번호 '+input.value+'\n수령자 연락처를 확인하셨나요? 배송처리 후 알림톡 발송을 요청합니다.'))return false;form.querySelector('button[type="submit"]').disabled=true;}return true;}
function appleCheckPayment(form){const checked=form.querySelector('[name="payment_confirmed"]').checked;return confirm((checked?'입금확인 완료':'미입금 상태')+'로 저장하고 관리자 메모를 반영하시겠습니까?');}
</script>
<?php if($print):?><script>window.addEventListener('load',()=>window.print())</script><?php endif;layout_end();
