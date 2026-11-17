<?php
declare(strict_types=1);
ini_set('display_errors','0');
$configFile=__DIR__.'/config.php';
if(!is_file($configFile)){http_response_code(503);exit('config.php 설정이 필요합니다. README 참조');}
$cfg=require $configFile;
date_default_timezone_set('Asia/Seoul');
if(session_status()!==PHP_SESSION_ACTIVE){session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax']);session_start();}
function db():PDO {global $cfg;static $pdo=null;if(!$pdo){$d=$cfg['db'];$pdo=new PDO('mysql:host='.$d['host'].';dbname='.$d['name'].';charset=utf8mb4',$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}return $pdo;}
function h($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function csrf():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));return $_SESSION['csrf'];}
function verify_csrf():void{if(!hash_equals(csrf(),(string)($_POST['csrf']??''))){http_response_code(403);exit('잘못된 요청입니다.');}}
function logged():bool{return !empty($_SESSION['admin']);}
function admin_only():void{if(!logged()){header('Location: admin.php');exit;}}
function url($path=''):string{global $cfg;return rtrim($cfg['base_url'],'/').'/'.ltrim($path,'/');}
function valid_phone($v):bool{return (bool)preg_match('/^01[016789][0-9]{7,8}$/',preg_replace('/\D/','',$v));}
function phone($v):string{return preg_replace('/\D/','',(string)$v);}
function post($k):string{return trim((string)($_POST[$k]??''));}
function layout_start($title):void{echo '<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).'</title><link rel="stylesheet" href="style.css"></head><body><main class="wrap"><header><h1>'.h($title).'</h1></header>';}
function layout_end():void{echo '</main></body></html>';}
function ppurio_send(string $to,array $vars):array{
 global $cfg;
 $p=$cfg['ppurio']??[];
 $ref='DV'.bin2hex(random_bytes(10));
 foreach(['account','api_key','sender_profile','template_code'] as $k){
  if(empty($p[$k]))return ['ok'=>false,'message'=>'알림톡 설정 누락: '.$k,'ref_key'=>$ref];
 }
 if(!valid_phone(phone($to)))return ['ok'=>false,'message'=>'수신인 전화번호 오류','ref_key'=>$ref];
 if(!function_exists('curl_init'))return ['ok'=>false,'message'=>'PHP cURL 확장 필요','ref_key'=>$ref];
 $auth=base64_encode($p['account'].':'.$p['api_key']);
 $ch=curl_init('https://message.ppurio.com/v1/token');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Basic '.$auth],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15]);
 $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
 $tokenResult=json_decode((string)$raw,true);$tokenResult=is_array($tokenResult)?$tokenResult:[];
 $token=$tokenResult['token']??$tokenResult['accessToken']??'';
 if($http<200||$http>=300||!is_string($token)||$token===''){
  $reason=(string)($tokenResult['message']??$tokenResult['description']??$err);
  return ['ok'=>false,'message'=>mb_substr('토큰 발급 실패 (HTTP '.$http.'): '.$reason,0,255),'http'=>$http,'ref_key'=>$ref];
 }
 // 승인 템플릿의 [*1*]~[*4*]에 순서대로 치환됩니다.
 $target=[['to'=>phone($to),'name'=>(string)($vars['recipient_name']??'수신인'),
  'changeWord'=>['var1'=>(string)($vars['market']??''),
                 'var2'=>(string)($vars['visit_date']??''),
                 'var3'=>(string)($vars['vehicle_no']??''),
                 'var4'=>(string)($vars['created_at']??'')]]];
 $payload=[
  'account'=>$p['account'],
  'messageType'=>'ALT',
  'senderProfile'=>$p['sender_profile'],
  'templateCode'=>$p['template_code'],
  'duplicateFlag'=>'N',
  'targetCount'=>count($target),
  'targets'=>$target,
  'isResend'=>'N',
  'refKey'=>$ref,
 ];
 $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 $ch=curl_init('https://message.ppurio.com/v1/kakao');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json; charset=utf-8'],CURLOPT_POSTFIELDS=>$json,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20]);
 $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
 $result=json_decode((string)$raw,true);$result=is_array($result)?$result:[];
 $code=(string)($result['code']??$result['resultCode']??$result['errorCode']??'');
 $hasError=isset($result['errorCode'])||isset($result['error']);
 $ok=$http>=200&&$http<300&&!$hasError&&($code===''||in_array(strtoupper($code),['1000','0','SUCCESS'],true));
 $message=(string)($result['message']??$result['description']??$result['error']??$err);
 if($message==='')$message=$ok?'API 접수 응답 (최종 도착 미확인)':'API 오류 (응답 본문 확인 필요)';
 return ['ok'=>$ok,'message'=>mb_substr($message,0,255),'http'=>$http,'ref_key'=>$ref,
  'message_key'=>(string)($result['messageKey']??$result['messageId']??''),'result_code'=>$code];
}
function dv_record_attempt(int $requestId,array $result):void{
 $status=!empty($result['ok'])?'accepted':'failed';
 $pdo=db();$pdo->beginTransaction();
 try{
  $stmt=$pdo->prepare('INSERT INTO dv_send_attempts(request_id,send_status,send_message,send_http,ref_key,message_key,result_code) VALUES(?,?,?,?,?,?,?)');
  $stmt->execute([$requestId,$status,$result['message']??'',($result['http']??null),$result['ref_key']??null,$result['message_key']??null,$result['result_code']??null]);
  $stmt=$pdo->prepare('UPDATE dv_requests SET send_status=?,send_message=?,send_http=?,sent_at=IF(?="accepted",NOW(),NULL) WHERE id=?');
  $stmt->execute([$status,$result['message']??'',($result['http']??null),$status,$requestId]);
  $pdo->commit();
 }catch(Throwable $e){$pdo->rollBack();throw $e;}
}

// Bitly API: 호출은 관리자 POST에서만 수행합니다. 액세스 토큰은 서버에만 보관합니다.
function dv_bitly_shorten(string $longUrl):array {
 global $cfg;
 $accessToken=trim((string)($cfg['bitly']['access_token']??''));
 if($accessToken==='')return ['ok'=>false,'message'=>'config.php의 bitly.access_token을 설정해 주세요.'];
 if(!function_exists('curl_init'))return ['ok'=>false,'message'=>'PHP cURL 확장이 필요합니다.'];
 $payload=['long_url'=>$longUrl];
 $domain=trim((string)($cfg['bitly']['domain']??''));
 if($domain!=='')$payload['domain']=$domain;
 $ch=curl_init('https://api-ssl.bitly.com/v4/shorten');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$accessToken,'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20]);
 $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
 $response=json_decode((string)$raw,true);$response=is_array($response)?$response:[];
 $link=(string)($response['link']??'');
 if($http>=200&&$http<300&&preg_match('~^https://(?:[a-z0-9-]+\.)+[a-z]{2,}/[A-Za-z0-9_-]+$~i',$link))return ['ok'=>true,'link'=>$link];
 return ['ok'=>false,'message'=>'Bitly HTTP '.$http.': '.mb_substr((string)($response['message']??$response['description']??$err??'요청 실패'),0,180)];
}
