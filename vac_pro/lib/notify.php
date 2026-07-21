<?php
require_once __DIR__.'/../core/Notification.php';
function phone_clean($v){ return Notification::phone((string)$v); }
function send_alimtalk($event,$recipientType,$recipientId,$phone,array $vars): bool {
    $normalized=[];
    foreach($vars as $k=>$v){
        if(preg_match('/^var(\d+)$/',(string)$k,$m)) $normalized['var'.$m[1]]=$v;
        else $normalized[$k]=$v;
    }
    return Notification::dispatch((string)$event,(string)$recipientType,$recipientId!==null?(int)$recipientId:null,(string)$phone,$normalized);
}


/**
 * 기존 승인 처리 파일과의 호환용 래퍼입니다.
 * 실제 수신동의 직원 조회 및 발송은 Notification 클래스가 담당합니다.
 */
function send_final_approval_broadcast(array $request): array {
    return Notification::notifyAllStaffApproved($request);
}
