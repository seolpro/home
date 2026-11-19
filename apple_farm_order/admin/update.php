<?php
declare(strict_types=1);
require dirname(__DIR__).'/lib.php';
admin_required();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
try {
 check_csrf();
 $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
 if(!$id)throw new RuntimeException('잘못된 주문번호입니다.');
 $action=(string)($_POST['action']??'shipping');
 $pdo=db();
 if($action==='payment'){
  $paid=isset($_POST['payment_confirmed'])&&$_POST['payment_confirmed']==='1'?1:0;
  $memo=trim((string)($_POST['admin_memo']??''));
  if(mb_strlen($memo,'UTF-8')>100)throw new RuntimeException('관리자 메모는 최대 100자까지 입력할 수 있습니다.');
  $pdo->beginTransaction();
  $st=$pdo->prepare('SELECT payment_confirmed FROM apple_orders WHERE id=? FOR UPDATE');$st->execute([$id]);$old=$st->fetch(PDO::FETCH_ASSOC);
  if(!$old)throw new RuntimeException('주문을 찾을 수 없습니다.');
  // 이미 입금확인된 건을 다시 저장할 때 기존 확인일시는 유지합니다.
  $sql='UPDATE apple_orders SET payment_confirmed=?, admin_memo=?, payment_confirmed_at=CASE WHEN ?=0 THEN NULL WHEN payment_confirmed=0 THEN NOW() ELSE payment_confirmed_at END WHERE id=?';
  $st=$pdo->prepare($sql);$st->execute([$paid,$memo,$paid,$id]);
  $pdo->commit();
  $_SESSION['apple_flash']=$paid?'입금확인 및 관리자 메모를 저장했습니다.':'미입금 상태 및 관리자 메모를 저장했습니다.';
 }elseif($action==='shipping'){
  $status=(string)($_POST['status']??'');
  if(!in_array($status,['received','shipped'],true))throw new RuntimeException('잘못된 배송상태 요청');
  $pdo->beginTransaction();
  $st=$pdo->prepare('SELECT * FROM apple_orders WHERE id=? FOR UPDATE');$st->execute([$id]);$order=$st->fetch();
  if(!$order)throw new RuntimeException('주문을 찾을 수 없습니다.');
  if($status==='shipped'){
   if($order['status']==='shipped')throw new RuntimeException('이미 배송처리된 주문입니다.');
   $tracking=trim((string)($_POST['tracking_no']??''));
   if(!preg_match('/^[0-9]{12}$/',$tracking))throw new RuntimeException('송장번호는 숫자 정확히 12자리로 입력하세요.');
   $st=$pdo->prepare('UPDATE apple_orders SET status="shipped",tracking_no=?,shipped_at=NOW() WHERE id=?');$st->execute([$tracking,$id]);
  }else{
   if($order['status']!=='shipped')throw new RuntimeException('배송대기 상태입니다.');
   $st=$pdo->prepare('UPDATE apple_orders SET status="received",tracking_no=NULL,shipped_at=NULL WHERE id=?');$st->execute([$id]);
  }
  $pdo->commit();
  $_SESSION['apple_flash']=$status==='shipped'?'배송처리 완료 (알림톡 발송 요청 중)':'배송처리를 취소했습니다. 이미 발송된 알림톡은 취소되지 않습니다.';
  if($status==='shipped'){
   require_once dirname(__DIR__).'/shipping_alimtalk.php';
   try{$result=apple_notify_shipping((int)$id,$tracking);$_SESSION['apple_flash']='배송처리 완료 · '.$result;}
   catch(Throwable $e){error_log('[apple shipping] '.$e->getMessage());$_SESSION['apple_flash']='배송처리 완료 · 알림톡 발송 요청 실패 (서버 로그 확인)';}
  }
 }else{throw new RuntimeException('지원하지 않는 처리 유형입니다.');}
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$_SESSION['apple_flash']='처리 실패: '.$e->getMessage();}
header('Location: index.php');exit;
