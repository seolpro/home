<?php
require_once __DIR__.'/core/Notification.php';
header('Content-Type: application/json; charset=utf-8');
$key=(string)($_GET['key']??($_SERVER['HTTP_X_CRON_KEY']??''));
$expected=setting('notification_cron_key','');
if($expected===''||!hash_equals($expected,$key)){
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'Forbidden'],JSON_UNESCAPED_UNICODE);
    exit;
}
try{
    $limit=max(1,min(200,(int)($_GET['limit']??50)));
    $result=Notification::processPending($limit);
    set_setting('notification_cron_last_run',date('Y-m-d H:i:s'));
    set_setting('notification_cron_last_result',sprintf('대상 %d / 성공 %d / 실패 %d',(int)($result['total']??0),(int)($result['sent']??0),(int)($result['failed']??0)));
    echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
    set_setting('notification_cron_last_run',date('Y-m-d H:i:s'));
    set_setting('notification_cron_last_result','오류: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
