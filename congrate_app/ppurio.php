<?php
declare(strict_types=1);
function cg_ppurio_send(string $to,string $name,string $content):array {
 $s=require __DIR__.'/ppurio_config.php';
 if(empty($s['enabled']))return ['ok'=>false,'detail'=>'뿌리오 발송 비활성'];
 foreach(['account','auth_key','sender'] as $k)if(empty($s[$k]))return ['ok'=>false,'detail'=>$k.' 설정 누락'];
 if(!function_exists('curl_init')||!function_exists('mb_convert_encoding'))return ['ok'=>false,'detail'=>'cURL/mbstring 필요'];
 $from=preg_replace('/\D/','',$s['sender']);
 if(!preg_match('/^01[0-9]{8,9}$/',$to)||!preg_match('/^0[0-9]{8,11}$/',$from))return ['ok'=>false,'detail'=>'전화번호 형식 오류'];
 $request=function(string $url,array $headers,?array $payload=null):array {
  $ch=curl_init($url);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>$headers];
  if($payload!==null)$opts[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  return ['http'=>$http,'json'=>json_decode((string)$raw,true),'error'=>$err];
 };
 $t=$request('https://message.ppurio.com/v1/token',['Authorization: Basic '.base64_encode($s['account'].':'.$s['auth_key']),'Content-Type: application/json; charset=utf-8']);
 $token=$t['json']['token']??'';if(!$token)return ['ok'=>false,'detail'=>'토큰 요청 실패','http'=>$t['http'],'response'=>$t['json'],'error'=>$t['error']];
 $type=strlen(mb_convert_encoding($content,'EUC-KR','UTF-8'))<=90?'SMS':'LMS';
 $payload=['account'=>$s['account'],'messageType'=>$type,'content'=>$content,'from'=>$from,'duplicateFlag'=>'Y','targetCount'=>1,'refKey'=>'cg_'.bin2hex(random_bytes(10)),'targets'=>[['to'=>$to,'name'=>$name,'changeWord'=>['var1'=>$name]]]];
 $r=$request('https://message.ppurio.com/v1/message',['Authorization: Bearer '.$token,'Content-Type: application/json; charset=utf-8'],$payload);
 $code=(string)($r['json']['code']??'');$ok=$r['http']>=200&&$r['http']<300&&($code===''||in_array($code,['1000','200'],true));
 return ['ok'=>$ok,'detail'=>$ok?'발송 접수':'발송 요청 실패','http'=>$r['http'],'response'=>$r['json'],'error'=>$r['error'],'type'=>$type];
}
