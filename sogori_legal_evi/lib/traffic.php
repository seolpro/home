<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config.php';

function traffic_db(): PDO {
    static $pdo=null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo=new PDO(
        'mysql:host='.TRAFFIC_DB_HOST.';port='.TRAFFIC_DB_PORT.';dbname='.TRAFFIC_DB_NAME.';charset=utf8mb4',
        TRAFFIC_DB_USER, TRAFFIC_DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]
    );
    return $pdo;
}
function traffic_ip(): string { foreach(['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k){if(!empty($_SERVER[$k]))return trim(explode(',',(string)$_SERVER[$k])[0]);}return ''; }
function traffic_device(string $ua): string {$u=strtolower($ua);if(preg_match('/ipad|tablet|kindle/',$u))return 'Tablet';if(preg_match('/mobile|iphone|android/',$u))return 'Mobile';return 'PC';}
function traffic_browser(string $ua): string {foreach(['Edg'=>'Edge','OPR'=>'Opera','Chrome'=>'Chrome','Firefox'=>'Firefox','Safari'=>'Safari'] as $k=>$v)if(strpos($ua,$k)!==false)return $v;return 'Other';}
function traffic_os(string $ua): string {foreach(['Windows'=>'Windows','Android'=>'Android','iPhone'=>'iOS','iPad'=>'iPadOS','Macintosh'=>'macOS','Linux'=>'Linux'] as $k=>$v)if(strpos($ua,$k)!==false)return $v;return 'Other';}
function traffic_session_key(): string {if(session_status()!==PHP_SESSION_ACTIVE)@session_start();if(empty($_SESSION['traffic_sid']))$_SESSION['traffic_sid']=bin2hex(random_bytes(16));return (string)$_SESSION['traffic_sid'];}

function traffic_track_page(string $page): void {
    if(!TRAFFIC_MONITOR_ENABLED)return;
    try{
        $ua=(string)($_SERVER['HTTP_USER_AGENT']??'');
        $q=traffic_db()->prepare('INSERT INTO traffic_visits(visited_at,ip_address,session_key,page_url,referer,user_agent,device_type,browser,os) VALUES(NOW(),?,?,?,?,?,?,?,?)');
        $q->execute([traffic_ip(),traffic_session_key(),mb_substr($page,0,500),mb_substr((string)($_SERVER['HTTP_REFERER']??''),0,1000),mb_substr($ua,0,1000),traffic_device($ua),traffic_browser($ua),traffic_os($ua)]);
    }catch(Throwable $e){ error_log('[traffic] page: '.$e->getMessage()); }
}
function traffic_collect_resource(int $bytes,array $resources=[]): void {
    if(!TRAFFIC_MONITOR_ENABLED)return;
    $bytes=max(0,min($bytes,500*1024*1024));
    try{
        $q=traffic_db()->prepare('INSERT INTO traffic_resource_events(created_at,ip_address,session_key,transferred_bytes,resource_json) VALUES(NOW(),?,?,?,?)');
        $q->execute([traffic_ip(),traffic_session_key(),$bytes,json_encode(array_slice($resources,0,100),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        traffic_maybe_alert();
    }catch(Throwable $e){ error_log('[traffic] resource: '.$e->getMessage()); }
}
function traffic_today_observed_bytes(): int {
    $q=traffic_db()->query('SELECT COALESCE(SUM(transferred_bytes),0) b FROM traffic_resource_events WHERE created_at>=CURDATE() AND created_at<CURDATE()+INTERVAL 1 DAY');
    return (int)$q->fetchColumn();
}
function traffic_today_sync(): ?array {
    try{
        $q=traffic_db()->query('SELECT * FROM traffic_daily_sync WHERE sync_date=CURDATE() LIMIT 1');
        $r=$q->fetch(); return $r?:null;
    }catch(Throwable $e){ return null; }
}
function traffic_today_bytes(): int {
    $observed=traffic_today_observed_bytes();
    $sync=traffic_today_sync();
    if(!$sync) return $observed;
    return max(0,$observed+(int)$sync['offset_bytes']);
}
function traffic_sync_today_bytes(int $actualBytes): array {
    $actualBytes=max(0,$actualBytes);
    $observed=traffic_today_observed_bytes();
    $offset=$actualBytes-$observed;
    $pdo=traffic_db();
    $q=$pdo->prepare('INSERT INTO traffic_daily_sync(sync_date,actual_bytes_at_sync,observed_bytes_at_sync,offset_bytes,synced_at) VALUES(CURDATE(),?,?,?,NOW()) ON DUPLICATE KEY UPDATE actual_bytes_at_sync=VALUES(actual_bytes_at_sync),observed_bytes_at_sync=VALUES(observed_bytes_at_sync),offset_bytes=VALUES(offset_bytes),synced_at=NOW()');
    $q->execute([$actualBytes,$observed,$offset]);
    traffic_maybe_alert();
    return ['actual_bytes'=>$actualBytes,'observed_bytes'=>$observed,'offset_bytes'=>$offset,'effective_bytes'=>traffic_today_bytes()];
}
function traffic_stats(int $days=31): array {
    $days=max(1,min(365,$days));$from=date('Y-m-d',strtotime('-'.($days-1).' days'));$pdo=traffic_db();
    $q=$pdo->prepare('SELECT COUNT(*) pv,COUNT(DISTINCT ip_address) uv FROM traffic_visits WHERE visited_at>=?');$q->execute([$from]);$a=$q->fetch()?:[];
    $q=$pdo->prepare('SELECT COALESCE(SUM(transferred_bytes),0) b FROM traffic_resource_events WHERE created_at>=?');$q->execute([$from]);$bytes=(int)$q->fetchColumn();
    $group=function(string $col)use($pdo,$from){$allowed=['device_type','browser','page_url'];if(!in_array($col,$allowed,true))return[];$q=$pdo->prepare("SELECT $col k,COUNT(*) v FROM traffic_visits WHERE visited_at>=? GROUP BY $col ORDER BY v DESC LIMIT 100");$q->execute([$from]);$o=[];foreach($q as $r)$o[(string)($r['k']?:'Unknown')]=(int)$r['v'];return$o;};
    $daily=[];$q=$pdo->prepare('SELECT DATE(visited_at) d,COUNT(*) pv FROM traffic_visits WHERE visited_at>=? GROUP BY DATE(visited_at)');$q->execute([$from]);foreach($q as$r)$daily[$r['d']]['pv']=(int)$r['pv'];
    $q=$pdo->prepare('SELECT DATE(created_at) d,SUM(transferred_bytes) b FROM traffic_resource_events WHERE created_at>=? GROUP BY DATE(created_at)');$q->execute([$from]);foreach($q as$r)$daily[$r['d']]['bytes']=(int)$r['b'];ksort($daily);
    return ['pv'=>(int)($a['pv']??0),'uv'=>(int)($a['uv']??0),'bytes'=>$bytes,'devices'=>$group('device_type'),'browsers'=>$group('browser'),'pages'=>$group('page_url'),'daily'=>$daily];
}
function traffic_recent(int $limit=100): array {$limit=max(1,min(500,$limit));return traffic_db()->query('SELECT visited_at AS ts,ip_address AS ip,device_type AS device,os,browser,page_url AS page,referer FROM traffic_visits ORDER BY id DESC LIMIT '.$limit)->fetchAll();}
function traffic_sms_safe_text(string $text): string {$map=['•'=>'-','●'=>'-','▪'=>'-','▲'=>'+','▼'=>'-','↑'=>'+','↓'=>'-','→'=>'->','“'=>'"','”'=>'"','‘'=>"'",'’'=>"'",'…'=>'...','–'=>'-','—'=>'-','·'=>'/'];$text=strtr($text,$map);$text=preg_replace('/[^\P{C}\n\r\t]+/u','',$text)??$text;$e=@iconv('UTF-8','EUC-KR//IGNORE',$text);if($e!==false){$d=@iconv('EUC-KR','UTF-8//IGNORE',$e);if($d!==false)$text=$d;}return trim($text);}
function traffic_ppurio_token(): array {if(TRAFFIC_PPURIO_ACCOUNT===''||TRAFFIC_PPURIO_AUTH_KEY==='')return['ok'=>false,'message'=>'뿌리오 계정/인증키 미설정'];$ch=curl_init('https://message.ppurio.com/v1/token');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Authorization: Basic '.base64_encode(TRAFFIC_PPURIO_ACCOUNT.':'.TRAFFIC_PPURIO_AUTH_KEY),'Content-Type: application/json; charset=utf-8']]);$body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$j=json_decode((string)$body,true);$token=is_array($j)?($j['token']??null):null;return['ok'=>(bool)$token,'token'=>$token,'code'=>$code,'response'=>$j?:$body,'error'=>$err];}
function traffic_send_sms(string $message): array {if(!TRAFFIC_SMS_ENABLED)return['ok'=>false,'message'=>'트래픽 문자경보 비활성화'];$to=preg_replace('/\D/','',TRAFFIC_ADMIN_PHONE);$from=preg_replace('/\D/','',TRAFFIC_PPURIO_SENDER);if(!preg_match('/^01\d{8,9}$/',$to))return['ok'=>false,'message'=>'관리자 수신번호 형식 오류'];if($from==='')return['ok'=>false,'message'=>'뿌리오 발신번호 미설정'];$message=traffic_sms_safe_text($message);$tk=traffic_ppurio_token();if(empty($tk['ok']))return$tk;$e=mb_convert_encoding($message,'EUC-KR','UTF-8');$type=strlen($e)<=90?'SMS':'LMS';$payload=['account'=>TRAFFIC_PPURIO_ACCOUNT,'messageType'=>$type,'content'=>$message,'from'=>$from,'duplicateFlag'=>'Y','targetCount'=>1,'refKey'=>'traffic_'.date('YmdHis').'_'.bin2hex(random_bytes(4)),'targets'=>[['to'=>$to,'name'=>TRAFFIC_ADMIN_NAME,'changeWord'=>['var1'=>TRAFFIC_ADMIN_NAME]]]];$ch=curl_init('https://message.ppurio.com/v1/message');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tk['token'],'Content-Type: application/json; charset=utf-8']]);$body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$j=json_decode((string)$body,true);$apiCode=is_array($j)?(string)($j['code']??''):'';$ok=$code>=200&&$code<300&&($apiCode===''||in_array($apiCode,['1000','200'],true));return['ok'=>$ok,'http_code'=>$code,'api_code'=>$apiCode,'response'=>$j?:$body,'error'=>$err,'message'=>$ok?'문자 발송 요청 성공':'문자 발송 실패'];}
function traffic_maybe_alert(): void {$bytes=traffic_today_bytes();if(TRAFFIC_LIMIT_BYTES<=0)return;$pct=$bytes/TRAFFIC_LIMIT_BYTES*100;$pdo=traffic_db();foreach(TRAFFIC_ALERT_LEVELS as$level){if($pct<$level)continue;$q=$pdo->prepare('SELECT COUNT(*) FROM traffic_alert_logs WHERE alert_date=CURDATE() AND alert_level=? AND send_success=1');$q->execute([$level]);if((int)$q->fetchColumn()>0)continue;$msg='[소고리 증거자료 트래픽 경고]' . "\n".'오늘 관측 전송량: '.number_format($bytes/1024/1024,1).'MB / '.number_format(TRAFFIC_LIMIT_BYTES/1024/1024,1).'MB'."\n".'사용률: '.number_format($pct,1).'%'."\n".$level.'% 경고 기준에 도달했습니다.';$r=traffic_send_sms($msg);$q=$pdo->prepare('INSERT INTO traffic_alert_logs(alert_date,alert_level,used_bytes,limit_bytes,usage_percent,recipient,send_success,result_json,created_at) VALUES(CURDATE(),?,?,?,?,?,?,?,NOW())');$q->execute([$level,$bytes,TRAFFIC_LIMIT_BYTES,$pct,TRAFFIC_ADMIN_PHONE,!empty($r['ok'])?1:0,json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);}}
function traffic_cleanup(): void {if(TRAFFIC_RETENTION_DAYS<=0)return;$pdo=traffic_db();$pdo->exec('DELETE FROM traffic_visits WHERE visited_at < NOW() - INTERVAL '.(int)TRAFFIC_RETENTION_DAYS.' DAY');$pdo->exec('DELETE FROM traffic_resource_events WHERE created_at < NOW() - INTERVAL '.(int)TRAFFIC_RETENTION_DAYS.' DAY');}
