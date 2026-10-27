<?php
declare(strict_types=1);
/** 주문 저장 이후 호출. 템플릿 승인 후 활성화하세요. */
function apple_notify_order(string $orderNo): void {
    $settings=cfg()['order_alimtalk']??[];
    if(empty($settings['enabled'])) return;
    $account=trim((string)($settings['account']??''));
    $key=trim((string)($settings['auth_key']??''));
    $profile=trim((string)($settings['sender_profile']??''));
    $template=trim((string)($settings['template_code']??''));
    $recipients=$settings['recipients']??[];
    if(!$account||!$key||!$profile||!$template||!is_array($recipients))throw new RuntimeException('알림톡 설정 누락');
    $q=db()->prepare('SELECT order_no,recipient_name,product_name,quantity,created_at FROM apple_orders WHERE order_no=? LIMIT 1');
    $q->execute([$orderNo]);$order=$q->fetch();if(!$order)return;
    $targets=[];
    foreach($recipients as $recipient){
        $to=phone_clean((string)$recipient);
        if(!preg_match('/^01\d{8,9}$/',$to))continue;
        if(isset($targets[$to]))continue;
        $targets[$to]=['to'=>$to,'name'=>'농장관리자','changeWord'=>[
            'var1'=>(string)$order['recipient_name'],
            'var2'=>(string)$order['product_name'],
            'var3'=>(string)$order['quantity'],
            'var4'=>(string)$order['order_no']
        ]];
    }
    if(!$targets)return;
    $targets=array_values($targets);
    $auth=base64_encode($account.':'.$key);
    $tokenResponse=apple_ppurio_post('https://message.ppurio.com/v1/token',null,['Authorization: Basic '.$auth]);
    $token=(string)($tokenResponse['data']['token']??$tokenResponse['data']['accessToken']??$tokenResponse['data']['access_token']??'');
    if(!$token)throw new RuntimeException('뿌리오 토큰 발급 실패 HTTP '.$tokenResponse['http']);
    $payload=['account'=>$account,'messageType'=>'ALT','senderProfile'=>$profile,
      'templateCode'=>$template,'duplicateFlag'=>'Y','isResend'=>'N',
      'targetCount'=>count($targets),'targets'=>$targets,
      'refKey'=>'ap'.date('ymdHis').bin2hex(random_bytes(4))];
    $sent=apple_ppurio_post('https://message.ppurio.com/v1/kakao',$payload,['Authorization: Bearer '.$token]);
    $ok=$sent['http']>=200&&$sent['http']<300;
    // 주문번호, 발송결과만 보관 (API 인증정보 및 개인정보 로그 기록 금지)
    try{
        $log=db()->prepare('INSERT INTO apple_order_alimtalk_logs(order_no,recipient_count,success,http_code,result_json,created_at) VALUES(?,?,?,?,?,NOW())');
        $log->execute([$orderNo,count($targets),$ok?1:0,$sent['http'],json_encode($sent['data'],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)]);
    }catch(Throwable $e){error_log('[apple_order] notification log: '.$e->getMessage());}
    if(!$ok)error_log('[apple_order] notification rejected HTTP '.$sent['http'].' order '.$orderNo);
}
function apple_ppurio_post(string $url,?array $payload,array $headers):array{
    if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL 확장 필요');
    $ch=curl_init($url);
    $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,
      CURLOPT_HTTPHEADER=>array_merge($headers,['Content-Type: application/json; charset=utf-8'])];
    if($payload!==null)$opts[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    curl_setopt_array($ch,$opts);$body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    if($body===false)throw new RuntimeException('뿌리오 연결 실패: '.$err);
    $data=json_decode((string)$body,true);
    return ['http'=>$http,'data'=>is_array($data)?$data:[]];
}
