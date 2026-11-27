<?php
require_once __DIR__.'/lib.php';
function rr_settings(){return db()->query('SELECT * FROM lease_rent_settings WHERE id=1')->fetch();}
function rr_generate(){
 $now=new DateTimeImmutable(); $base=$now->modify('first day of this month')->setTime(0,0);
 foreach(db()->query('SELECT * FROM lease_rent_reminders WHERE active=1')->fetchAll() as $r){
  for($i=0;$i<=1;$i++){
   $m=$base->modify("+$i month"); $due=$m->setDate((int)$m->format('Y'),(int)$m->format('m'),min((int)$r['pay_day'],(int)$m->format('t'))); $d=$due->format('Y-m-d');
   if($d<$r['start_date']||$d>$r['end_date']||$d<$now->format('Y-m-d')){db()->prepare("DELETE FROM lease_rent_send_queue WHERE reminder_id=? AND rent_month=? AND status='pending'")->execute([$r['id'],$m->format('Y-m')]);continue;}
   $at=$due->modify('-'.(int)$r['lead_days'].' days')->format('Y-m-d').' '.$r['send_time'];
   $q=db()->prepare("INSERT INTO lease_rent_send_queue(reminder_id,rent_month,due_date,scheduled_at,snapshot) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE due_date=IF(status='pending',VALUES(due_date),due_date),scheduled_at=IF(status='pending',VALUES(scheduled_at),scheduled_at),snapshot=IF(status='pending',VALUES(snapshot),snapshot)");
   $q->execute([$r['id'],$m->format('Y-m'),$d,$at,json_encode($r,JSON_UNESCAPED_UNICODE)]);
  }
 }
}
function rr_vars($r,$due){return ['var1'=>$r['tenant'],'var2'=>$r['property'],'var3'=>$due,'var4'=>number_format($r['rent']),'var5'=>$r['bank'],'var6'=>$r['account_no'],'var7'=>$r['holder'],'var8'=>(string)$r['late_rate']];}
function rr_send($r,$due,$phone,$ref){
 $s=rr_settings(); if(!$s||!$s['template_code'])return ['status'=>'failed','message'=>'템플릿 코드 미설정'];
 $phone=preg_replace('/\D/','',(string)$phone);
 if(!preg_match('/^01[0-9]{8,9}$/D',$phone))return ['status'=>'failed','message'=>'수신번호 형식 오류'];
 $account=trim((string)cfg('sms.account'));
 if($account==='')return ['status'=>'failed','message'=>'뿌리오 계정 미설정'];
 $vars=array_map('strval',rr_vars($r,$due));
 foreach($vars as $key=>$value){if(trim($value)==='')return ['status'=>'failed','message'=>'템플릿 변수 누락: '.$key];}
 $tk=ppurio_token(); if(empty($tk['ok']))return ['status'=>'failed','http'=>$tk['code']??0,'message'=>$tk['message']??'토큰 발급 실패'];
 $p=['account'=>$account,'templateCode'=>trim($s['template_code']),'messageType'=>'alt','isResend'=>'N','duplicateFlag'=>'N','targetCount'=>1,'refKey'=>$ref,'targets'=>[['to'=>$phone,'name'=>$r['tenant'],'changeWord'=>(object)$vars]]];
 if(trim((string)($s['sender_profile']??''))!=='')$p['senderProfile']=trim($s['sender_profile']);
 $json=json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 if($json===false)return ['status'=>'failed','message'=>'JSON 변환 오류: '.json_last_error_msg()];
 $ch=curl_init('https://message.ppurio.com/v1/kakao');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tk['token'],'Content-Type: application/json; charset=utf-8'],CURLOPT_POSTFIELDS=>$json]);
 $body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$j=json_decode((string)$body,true);$code=(string)($j['code']??'');
 $status=($http>=200&&$http<300&&$code==='1000')?'accepted':(($err||$http>=500||($http>=200&&$http<300&&$code===''))?'unknown':'failed');
 return ['status'=>$status,'http'=>$http,'code'=>$code,'response'=>$j??substr((string)$body,0,2000),'error'=>$err];
}
function rr_dispatch($id,$testPhone=null){
 $q=db()->prepare('SELECT q.*,r.active,r.start_date,r.end_date FROM lease_rent_send_queue q JOIN lease_rent_reminders r ON r.id=q.reminder_id WHERE q.id=?');$q->execute([$id]);$v=$q->fetch();if(!$v)throw new RuntimeException('발송건 없음');
 if(!$testPhone){if(!$v['active']||$v['due_date']<$v['start_date']||$v['due_date']>$v['end_date']||$v['due_date']<date('Y-m-d'))throw new RuntimeException('발송 가능 기간 아님');$q=db()->prepare("UPDATE lease_rent_send_queue SET status='sending' WHERE id=? AND status IN ('pending','failed')");$q->execute([$id]);if(!$q->rowCount())throw new RuntimeException('이미 처리된 건입니다.');}
 $ref='rent_'.bin2hex(random_bytes(12));$r=json_decode($v['snapshot'],true);try{$res=rr_send($r,$v['due_date'],$testPhone?:$r['phone'],$ref);}catch(Throwable $ex){$res=['status'=>'unknown','message'=>'발송 처리 예외: 공급자 확인 필요'];}
 db()->beginTransaction();try{db()->prepare('INSERT INTO lease_rent_send_logs(queue_id,ref_key,status,result_json,created_at) VALUES(?,?,?,?,?)')->execute([$id,$ref,($testPhone?'test_':'').$res['status'],json_encode($res,JSON_UNESCAPED_UNICODE),date('Y-m-d H:i:s')]);if(!$testPhone)db()->prepare('UPDATE lease_rent_send_queue SET status=? WHERE id=?')->execute([$res['status'],$id]);db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}return $res['status'];
}
