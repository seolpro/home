<?php
declare(strict_types=1);
require_once __DIR__.'/order_alimtalk.php';
function apple_notify_shipping(int $id,string $tracking):string {
 $settings=cfg()['shipping_alimtalk']??[];
 if(empty($settings['enabled']))return '배송 알림톡 비활성화';
 $q=db()->prepare('SELECT recipient_name,phone,tracking_no,status,order_no FROM apple_orders WHERE id=?');$q->execute([$id]);$o=$q->fetch();
 if(!$o||$o['status']!=='shipped'||$o['tracking_no']!==$tracking)throw new RuntimeException('주문 배송상태 불일치');
 $to=phone_clean((string)$o['phone']);if(!preg_match('/^01[0-9]{8,9}$/D',$to))throw new RuntimeException('수령자 휴대전화번호 오류');
 $account=trim((string)($settings['account']??''));$key=trim((string)($settings['auth_key']??''));$profile=trim((string)($settings['sender_profile']??''));$template=trim((string)($settings['template_code']??''));
 if(!$account||!$key||!$profile||!$template)throw new RuntimeException('배송 알림톡 설정 누락');
 // 템플릿 [*이름*] -> name, [*1*] -> var1 (승인 템플릿 규격 확인)
 $targets=[['to'=>$to,'name'=>(string)$o['recipient_name'],'changeWord'=>['var1'=>$tracking]]];
 $auth=base64_encode($account.':'.$key);
 $r=apple_ppurio_post('https://message.ppurio.com/v1/token',null,['Authorization: Basic '.$auth]);
 $token=(string)($r['data']['token']??$r['data']['accessToken']??$r['data']['access_token']??'');
 if(!$token)throw new RuntimeException('토큰 발급 실패 HTTP '.$r['http']);
 $payload=['account'=>$account,'messageType'=>'ALT','senderProfile'=>$profile,'templateCode'=>$template,'duplicateFlag'=>'Y','isResend'=>'N','targetCount'=>1,'targets'=>$targets,'refKey'=>'as'.date('ymdHis').bin2hex(random_bytes(4))];
 $sent=apple_ppurio_post('https://message.ppurio.com/v1/kakao',$payload,['Authorization: Bearer '.$token]);
 $ok=$sent['http']>=200&&$sent['http']<300;
 try{$st=db()->prepare('INSERT INTO apple_shipping_alimtalk_logs(order_id,tracking_no,success,http_code,result_json,created_at) VALUES(?,?,?,?,?,NOW())');$st->execute([$id,$tracking,$ok?1:0,$sent['http'],json_encode($sent['data'],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)]);}catch(Throwable $e){error_log('[apple shipping log] '.$e->getMessage());}
 return $ok?'알림톡 API 접수 성공 (실제 수신은 별도 확인)':'알림톡 API 접수 실패 HTTP '.$sent['http'];
}
