<?php
declare(strict_types=1);
require dirname(__DIR__).'/lib.php';
admin_required();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
$redirect='index.php';
try{
 check_csrf();
 $query=(string)($_POST['return_qs']??'');
 parse_str($query,$params);
 $allowed=[];
 foreach(['q','status','payment'] as $key){if(isset($params[$key])&&is_string($params[$key]))$allowed[$key]=$params[$key];}
 $allowed['page']=max(1,(int)($_POST['return_page']??1));
 $redirect.='?'.http_build_query($allowed);
 $action=(string)($_POST['bulk_action']??'');
 if(!in_array($action,['paid','unpaid'],true))throw new RuntimeException('올바르지 않은 일괄처리 요청입니다.');
 $raw=$_POST['ids']??[];
 if(!is_array($raw))throw new RuntimeException('주문 선택값이 올바르지 않습니다.');
 $ids=[];
 foreach($raw as $item){if(!is_scalar($item)||!ctype_digit((string)$item)||(int)$item<=0)throw new RuntimeException('잘못된 주문번호가 포함되어 있습니다.');$ids[]=(int)$item;}
 $ids=array_values(array_unique($ids));
 if(!$ids)throw new RuntimeException('선택된 주문이 없습니다.');
 if(count($ids)>30)throw new RuntimeException('일괄처리는 현재 페이지 최대 30건까지 가능합니다.');
 $placeholders=implode(',',array_fill(0,count($ids),'?'));
 $pdo=db();$pdo->beginTransaction();
 if($action==='paid'){
  $sql="UPDATE apple_orders SET payment_confirmed_at=CASE WHEN payment_confirmed=0 THEN NOW() ELSE payment_confirmed_at END, payment_confirmed=1 WHERE id IN ($placeholders)";
 }else{
  $sql="UPDATE apple_orders SET payment_confirmed=0, payment_confirmed_at=NULL WHERE id IN ($placeholders)";
 }
 $st=$pdo->prepare($sql);$st->execute($ids);$changed=$st->rowCount();$pdo->commit();
 $_SESSION['apple_flash']=count($ids).'건 선택 · '.($action==='paid'?'입금확인':'입금확인 해제').' 일괄처리 완료 (DB 반영 '.$changed.'건). 관리자 메모는 유지되었습니다.';
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$_SESSION['apple_flash']='일괄처리 실패: '.$e->getMessage();}
header('Location: '.$redirect);exit;
