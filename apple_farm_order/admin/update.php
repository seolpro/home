<?php
declare(strict_types=1);
require dirname(__DIR__).'/lib.php';
admin_required();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
try {
 check_csrf();
 $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
 $status=(string)($_POST['status']??'');
 if(!$id||!in_array($status,['received','shipped'],true))throw new RuntimeException('잘못된 요청');
 $pdo=db();$pdo->beginTransaction();
 $st=$pdo->prepare('SELECT * FROM apple_orders WHERE id=? FOR UPDATE');$st->execute([$id]);$order=$st->fetch();
 if(!$order)throw new RuntimeException('주문을 찾을 수 없습니다.');
 if($status==='shipped'){
  if($order['status']==='shipped')throw new RuntimeException('이미 배송처리된 주문입니다.');
  $tracking=trim((string)($_POST['tracking_no']??''));
  if(!preg_match('/^[0-9]{8,20}$/D',$tracking))throw new RuntimeException('송장번호는 숫자 8~20자리로 입력하세요.');
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
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$_SESSION['apple_flash']='처리 실패: '.$e->getMessage();}
header('Location: index.php');exit;
