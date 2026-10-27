<?php
declare(strict_types=1);
function cg_alt_send(string $phone, string $name, string $eventType, array $vars): array {
 $c=require __DIR__.'/ppurio_alimtalk_config.php';
 $e=require __DIR__.'/ppurio_event_config.php';
 if(empty($c['enabled']))return ['ok'=>false,'error'=>'알림톡 비활성'];
 foreach(['account','auth_key','sender_profile'] as $key){if(empty($c[$key]))return ['ok'=>false,'error'=>'설정 누락: '.$key];}
 if(!in_array($eventType,['happy','sad'],true))return ['ok'=>false,'error'=>'경조사 구분 오류'];
 $code=$eventType==='happy'?($e['happy_template_code']??''):($e['sad_template_code']??'');
 if(!$code||str_starts_with($code,'여기에_'))return ['ok'=>false,'error'=>'승인된 템플릿 코드가 설정되지 않았습니다'];
 $expected=$eventType==='happy'?4:5;
 $change=[];
 for($i=1;$i<=$expected;$i++){
  $key='var'.$i;$val=trim((string)($vars[$key]??''));
  if($val===''||mb_strlen($val)>250)return ['ok'=>false,'error'=>"{$key} 값 누락 또는 길이 초과"];
  $change[$key]=$val;
 }
 $phone=preg_replace('/\D/','',$phone);
 if(!preg_match('/^01\d{8,9}$/',$phone))return ['ok'=>false,'error'=>'전화번호 형식 오류'];
 $request=static function(string $url,array $headers,?array $payload=null):array{
  $ch=curl_init($url);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_HTTPHEADER=>$headers];
  if($payload!==null)$opts[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return ['http'=>$http,'json'=>json_decode((string)$raw,true),'error'=>$err];
 };
 $t=$request('https://message.ppurio.com/v1/token',['Authorization: Basic '.base64_encode($c['account'].':'.$c['auth_key']),'Content-Type: application/json; charset=utf-8']);
 $token=$t['json']['token']??$t['json']['accessToken']??$t['json']['accesstoken']??$t['json']['access_token']??'';
 if(!$token)return ['ok'=>false,'stage'=>'token','http'=>$t['http'],'response'=>$t['json'],'error'=>$t['error']];
 $ref='cg'.date('ymdHis').bin2hex(random_bytes(5));
 $payload=['account'=>$c['account'],'messageType'=>'ALT','senderProfile'=>$c['sender_profile'],'templateCode'=>$code,'duplicateFlag'=>'Y','isResend'=>'N','targetCount'=>1,'targets'=>[['to'=>$phone,'name'=>$name,'changeWord'=>$change]],'refKey'=>$ref];
 $r=$request('https://message.ppurio.com/v1/kakao',['Authorization: Bearer '.$token,'Content-Type: application/json; charset=utf-8'],$payload);
 return ['ok'=>$r['http']>=200&&$r['http']<300,'http'=>$r['http'],'response'=>$r['json'],'error'=>$r['error'],'refKey'=>$ref,'template_code'=>$code];
}
