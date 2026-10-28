<?php
declare(strict_types=1);
/** AS 접수 완료 후 농장주에게 알림톡 발송 */
function apple_notify_as(int $asId): void {
    $c=cfg(); $a=$c['as_alimtalk']??[];
    if(empty($a['enabled']))return;
    $to=preg_replace('/\D+/', '', (string)($a['recipient']??'01050640027'));
    if(!preg_match('/^01\d{8,9}$/',$to))throw new RuntimeException('AS 수신번호 오류');
    foreach(['account','auth_key','sender_profile','template_code'] as $k){if(empty($a[$k]))throw new RuntimeException('AS 알림톡 설정 누락: '.$k);}
    $db=db();
    $st=$db->prepare('SELECT a.id,a.reason,a.created_at,o.recipient_name,o.product_name,o.quantity FROM apple_as_requests a JOIN apple_orders o ON o.id=a.order_id WHERE a.id=? LIMIT 1');
    $st->execute([$asId]);$r=$st->fetch();if(!$r)return;
    // 재시도 시 중복 방지: 발송 시도 이력이 있으면 자동 재발송하지 않음
    $st=$db->prepare('INSERT IGNORE INTO apple_as_alimtalk_logs (as_id,recipient,success,http_code,result_json,created_at) VALUES (?,?,0,0,?,NOW())');
    $st->execute([$asId,$to,'pending']);if($st->rowCount()===0)return;
    require_once __DIR__.'/order_alimtalk.php'; // 기존 Bizppurio v1 HTTP 함수 재사용
    $auth=base64_encode((string)$a['account'].':'.(string)$a['auth_key']);
    try {
        $tok=apple_ppurio_post('https://message.ppurio.com/v1/token',null,['Authorization: Basic '.$auth]);
        $token=(string)($tok['data']['token']??$tok['data']['accessToken']??$tok['data']['access_token']??'');
        if(!$token)throw new RuntimeException('토큰 발급 실패 HTTP '.$tok['http']);
        $payload=['account'=>(string)$a['account'],'messageType'=>'ALT','senderProfile'=>(string)$a['sender_profile'],
          'templateCode'=>(string)$a['template_code'],'duplicateFlag'=>'Y','isResend'=>'N','targetCount'=>1,
          'targets'=>[['to'=>$to,'name'=>'농장관리자','changeWord'=>[
             'var1'=>(string)$r['recipient_name'],'var2'=>(string)$r['product_name'],
             'var3'=>(string)$r['reason'],'var4'=>(string)$asId
          ]]],'refKey'=>'as'.date('ymdHis').bin2hex(random_bytes(4))];
        $sent=apple_ppurio_post('https://message.ppurio.com/v1/kakao',$payload,['Authorization: Bearer '.$token]);
        $ok=$sent['http']>=200&&$sent['http']<300;
        $raw=json_encode($sent['data'],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        $db->prepare('UPDATE apple_as_alimtalk_logs SET success=?,http_code=?,result_json=? WHERE as_id=?')->execute([$ok?1:0,$sent['http'],mb_substr((string)$raw,0,2000),$asId]);
        if(!$ok)error_log('[apple_as] alimtalk rejected id='.$asId.' HTTP '.$sent['http']);
    } catch(Throwable $e) {
        $db->prepare('UPDATE apple_as_alimtalk_logs SET result_json=? WHERE as_id=?')->execute(['send_error',$asId]);
        throw $e;
    }
}
