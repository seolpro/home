<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
$error=''; $success=false; $matches=[];
$name=trim((string)($_POST['recipient_name']??''));
$phone=phone_clean((string)($_POST['phone']??''));
$action=(string)($_POST['action']??'lookup');
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  check_csrf();
  if(mb_strlen($name)<2||mb_strlen($name)>80||!preg_match('/^01[0-9]{8,9}$/',$phone))throw new RuntimeException('수령자 성명과 연락처를 확인해 주세요.');
  $last=(int)($_SESSION['apple_as_last_attempt']??0);
  if(time()-$last<3)throw new RuntimeException('잠시 후 다시 시도해 주세요.');
  $_SESSION['apple_as_last_attempt']=time();
  $s=db()->prepare('SELECT id,order_no,created_at,product_name,quantity FROM apple_orders WHERE recipient_name=? AND REPLACE(REPLACE(phone,"-","")," ","")=? ORDER BY created_at DESC,id DESC LIMIT 20');
  $s->execute([$name,$phone]);$matches=$s->fetchAll();
  if(!$matches)throw new RuntimeException('일치하는 주문을 찾을 수 없습니다. 주문 당시 성명과 연락처를 확인해 주세요.');
  if($action==='submit'){
   $orderId=(int)($_POST['order_id']??0);$reason=(string)($_POST['reason']??'');$detail=trim((string)($_POST['detail']??''));
   $valid=false;foreach($matches as $m){if((int)$m['id']===$orderId){$valid=true;break;}}
   if(!$valid)throw new RuntimeException('접수할 주문을 다시 선택해 주세요.');
   if(!in_array($reason,['상품 파손','상품 품질','수량 누락','배송 문제','기타'],true))throw new RuntimeException('AS 유형을 선택해 주세요.');
   if(mb_strlen($detail)<5||mb_strlen($detail)>1000)throw new RuntimeException('상세내용을 5~1000자로 입력해 주세요.');
   $s=db()->prepare('INSERT INTO apple_as_requests (order_id,reason,detail,status,created_at) VALUES (?,?,?,"received",NOW())');
   $s->execute([$orderId,$reason,$detail]);
   $asId=(int)db()->lastInsertId();
   $success=true;
   // 접수 성공 후 관리자에게 발송. 발송 오류는 접수 결과에 영향 없음.
   try { require_once __DIR__.'/as_alimtalk.php'; apple_notify_as($asId); }
   catch(Throwable $notifyError){ error_log('[apple_as] notify error id='.$asId.' '.$notifyError->getMessage()); }

  }
 }catch(Throwable $e){$error=$e instanceof PDOException?'처리 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.':$e->getMessage();}
}
layout_start('사과농장 AS 신청'); ?>
<div class="card" style="max-width:650px;margin:auto">
<h1>🍎 사과농장 AS 신청</h1>
<p class="muted">주문 당시의 수령자 성명과 연락처로 주문을 확인한 후 AS를 신청할 수 있습니다. 주문번호는 필요하지 않습니다.</p>
<?php if($success):?><div class="notice">AS 신청이 접수되었습니다. 농장 확인 후 연락드리겠습니다.</div>
<?php else:?>
<?php if($error):?><div class="error"><?=h($error)?></div><?php endif;?>
<form method="post" id="asForm">
<input type="hidden" name="csrf" value="<?=h(csrf())?>">
<input type="hidden" name="action" id="action" value="lookup">
<label for="recipient_name">수령자 성명 *</label><input id="recipient_name" name="recipient_name" maxlength="80" required value="<?=h($name)?>" autocomplete="name">
<label for="phone">수령자 연락처 *</label><input id="phone" name="phone" type="tel" inputmode="tel" maxlength="20" required value="<?=h($phone)?>" placeholder="01012345678" autocomplete="tel">
<?php if($matches):?>
<label for="order_id">AS 신청할 주문 *</label>
<select id="order_id" name="order_id" required>
<option value="">주문을 선택해 주세요</option>
<?php foreach($matches as $m):?><option value="<?=(int)$m['id']?>" <?=((int)($_POST['order_id']??0)===(int)$m['id']?'selected':'')?>><?=h(substr((string)$m['created_at'],0,10).' · '.$m['product_name'].' × '.$m['quantity'])?></option><?php endforeach;?>
</select>
<label for="reason">AS 유형 *</label><select id="reason" name="reason" required><option value="">선택해 주세요</option><?php foreach(['상품 파손','상품 품질','수량 누락','배송 문제','기타'] as $r):?><option value="<?=h($r)?>" <?=($_POST['reason']??'')===$r?'selected':''?>><?=h($r)?></option><?php endforeach;?></select>
<label for="detail">상세 내용 *</label><textarea id="detail" name="detail" rows="5" minlength="5" maxlength="1000" required placeholder="상품 상태, 문제 수량, 요청사항 등을 입력해 주세요."><?=h($_POST['detail']??'')?></textarea>
<div class="actions"><button type="submit" onclick="document.getElementById('action').value='submit'">AS 접수하기</button><button type="submit" class="secondary" onclick="document.getElementById('action').value='lookup'">주문 다시 조회</button></div>
<?php else:?>
<div class="actions"><button type="submit">주문내역 확인</button></div>
<?php endif;?>
</form>
<p class="muted" style="font-size:13px">주문 확인과 AS 처리에만 입력정보를 사용합니다. 성명·전화번호가 타인에게 알려진 경우 주문일자와 상품명이 노출될 수 있으므로 운영 시 추가 본인확인을 권장합니다.</p>
<?php endif;?></div>
<?php layout_end();
