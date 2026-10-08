<?php
declare(strict_types=1);
function tp_ppurio_token(): string {
 $basic=base64_encode(PPURIO_ACCOUNT.':'.PPURIO_AUTH_KEY);
 $ch=curl_init('https://message.ppurio.com/v1/token'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Basic '.$basic,'Content-Type: application/json; charset=utf-8']]);
 $raw=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $j=json_decode((string)$raw,true);
 if($http<200||$http>=300||empty($j['token'])) throw new RuntimeException('뿌리오 토큰 발급 실패: '.$raw); return $j['token'];
}
function tp_send_alimtalk(string $phone,array $vars,bool $force=false): array {
 if(!$force && !PPURIO_ENABLED) return ['skipped'=>true,'reason'=>'disabled'];
 $token=tp_ppurio_token();
 // 승인 템플릿: var1 입항일, var2 선박명, var3 수량, var4 모델, var5 출항지
 // 기본형(텍스트형) 알림톡: 뿌리오 v1/kakao의 messageType은 ALT 사용.
 // 주의: from/content는 최상위 필드가 아니며, from은 대체발송(resend) 내부에서만 사용합니다.
 $body=[
  'account'=>PPURIO_ACCOUNT,
  'messageType'=>'ALT',
  'senderProfile'=>PPURIO_SENDER_PROFILE,
  'templateCode'=>PPURIO_TEMPLATE_TESLA_PORT,
  'duplicateFlag'=>'N',
  'targetCount'=>1,
  'targets'=>[[
   'to'=>preg_replace('/\D/','',$phone),
   'name'=>'Tesla Port',
   'changeWord'=>[
    'var1'=>$vars['eta'],
    'var2'=>$vars['vessel_name'],
    'var3'=>(string)$vars['vehicle_count'],
    'var4'=>$vars['model_guess'],
    'var5'=>$vars['origin_port'],
   ],
  ]],
  'isResend'=>'N',
  'refKey'=>'TP'.date('YmdHis').bin2hex(random_bytes(3)),
 ];
 $ch=curl_init('https://message.ppurio.com/v1/kakao'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json; charset=utf-8']]);
 $raw=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); return ['http'=>$http,'raw'=>$raw,'request'=>$body];
}
