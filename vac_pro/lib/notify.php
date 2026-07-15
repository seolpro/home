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
