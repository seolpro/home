<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config.php';require_once dirname(__DIR__).'/lib/common.php';require_once dirname(__DIR__).'/lib/provider.php';require_once dirname(__DIR__).'/lib/customs.php';require_once dirname(__DIR__).'/lib/customs_iopr.php';require_once dirname(__DIR__).'/lib/ppurio.php';
if(!hash_equals(APP_CRON_KEY,(string)($_GET['key']??''))){http_response_code(403);exit('forbidden');}header('Content-Type: application/json; charset=utf-8');
try{$pdo=tp_db();[$from,$to]=tp_window();$rows=tp_fetch_vessels($from,$to,tp_port_code());$new=0;$updated=0;$sent=0;$carriers=0;$shanghai=0;$customsChecked=0;$customsTesla=0;$ioprChecked=0;$ioprVerified=0;$manifestScanned=0;$manifestHits=0;$manifestTesla=0;
foreach($rows as $r){
  $vc=tp_is_vehicle_carrier($r)?1:0;if($vc)$carriers++;
  $uid=tp_uid($r);$st=$pdo->prepare('SELECT id FROM tp_vessels WHERE source_uid=?');$st->execute([$uid]);$id=$st->fetchColumn();
  $vals=[$r['vessel_name'],$r['voyage_no']?:null,$r['call_sign']?:null,$r['vessel_kind']?:null,$r['eta'],$r['port_code']?:null,$r['port_name']?:'미확인',$r['origin_port']?:null,$r['origin_port_code']?:null,$r['first_origin_port']?:null,$r['next_port']?:null,$r['manifest_no']?:null,$r['berth_name']?:null,$r['cargo_ton']?:null,$r['landing_ton']?:null,$r['report_company']?:null,$vc,json_encode($r['raw'],JSON_UNESCAPED_UNICODE)];
  // v1.8.2: 상하이 여부는 MOF 값으로 덮어쓰지 않고 API024 실제 전출항지만 권위값으로 사용
  if(!$id){$pdo->prepare('INSERT INTO tp_vessels(source_uid,vessel_name,voyage_no,call_sign,vessel_kind,eta,port_code,port_name,origin_port,origin_port_code,first_origin_port,next_port,manifest_no,berth_name,cargo_ton,landing_ton,report_company,is_vehicle_carrier,china_origin_flag,raw_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?)')->execute(array_merge([$uid],$vals));$new++;}
  else{$pdo->prepare('UPDATE tp_vessels SET vessel_name=?,voyage_no=?,call_sign=?,vessel_kind=?,eta=?,port_code=?,port_name=?,origin_port=?,origin_port_code=?,first_origin_port=?,next_port=?,manifest_no=?,berth_name=?,cargo_ton=?,landing_ton=?,report_company=?,is_vehicle_carrier=?,raw_json=? WHERE id=?')->execute(array_merge($vals,[$id]));$updated++;}
}
// v1.9.1 API024 우선 교차검증: 이번 MOF 조회기간의 자동차운반선은 캐시와 무관하게 먼저 확인
// 순서: MOF 자동차운반선 -> API024 실제 전출항지 -> Shanghai 판정 -> MRN -> API001
if(defined('CUSTOMS_IOPR_API_KEY') && trim((string)CUSTOMS_IOPR_API_KEY)!==''){
  $iq=$pdo->prepare("SELECT * FROM tp_vessels WHERE is_vehicle_carrier=1 AND call_sign IS NOT NULL AND call_sign<>'' AND eta>=? AND eta<DATE_ADD(?, INTERVAL 1 DAY) ORDER BY eta");
  $iq->execute([$from,$to]);
  foreach($iq as $iv){
    try{
      $ir=tp_iopr_request((string)$iv['call_sign'],'10');$m=tp_iopr_best_match($ir['rows'],$iv['eta']??null);$verified=0;
      if($m){$nameOk=mb_strtoupper(trim((string)$m['ship_name']))===mb_strtoupper(trim((string)$iv['vessel_name']));$timeOk=$m['time_diff_hours']===null||$m['time_diff_hours']<=72;$verified=($nameOk&&$timeOk)?1:0;}
      $api024Shanghai=($m && tp_iopr_is_shanghai_port((string)($m['departure_port_name']??''),(string)($m['departure_port_code']??'')))?1:0;
      $pdo->prepare('UPDATE tp_vessels SET customs_iopr_verified=?,customs_iopr_submission_no=?,customs_iopr_arrival_at=?,customs_iopr_departure_port=?,customs_iopr_customs_name=?,customs_iopr_berth_name=?,china_origin_flag=?,customs_iopr_last_checked_at=NOW(),customs_iopr_error=NULL,customs_iopr_raw_xml=? WHERE id=?')->execute([$verified,$m['submission_no']??null,$m['arrival_at_normalized']??null,$m['departure_port_name']??null,$m['customs_name']??null,$m['berth_name']??null,$api024Shanghai,$ir['raw'],$iv['id']]);
      $ioprChecked++;if($verified)$ioprVerified++;
    }catch(Throwable $e){$pdo->prepare('UPDATE tp_vessels SET customs_iopr_error=?,customs_iopr_last_checked_at=NOW() WHERE id=?')->execute([$e->getMessage(),$iv['id']]);}
  }
}
// v1.9 자동 화물탐색: API024 교차검증 + Shanghai + 실제 11자리 MRN인 자동차운반선만
// 알림톡과 독립적으로 동작하며 API001이 실제 응답한 화물만 DB에 저장한다.
if(tp_customs_key()!==''){
  $maxMsn=defined('CUSTOMS_MSN_SCAN_MAX')?(int)CUSTOMS_MSN_SCAN_MAX:20;
  $missLimit=defined('CUSTOMS_MSN_MISS_LIMIT')?(int)CUSTOMS_MSN_MISS_LIMIT:5;
  $mq=$pdo->query("SELECT * FROM tp_vessels WHERE is_vehicle_carrier=1 AND customs_iopr_verified=1 AND china_origin_flag=1 AND customs_iopr_submission_no REGEXP '^[A-Za-z0-9]{11}$' AND (manifest_scan_last_checked_at IS NULL OR manifest_scan_last_checked_at<DATE_SUB(NOW(),INTERVAL 6 HOUR))");
  foreach($mq as $mv){
    try{
      $scan=tp_customs_auto_scan_mrn((string)$mv['customs_iopr_submission_no'],$maxMsn,$missLimit);$manifestScanned+=$scan['scanned'];$hits=0;$teslaItems=[];
      foreach($scan['items'] as $it){
        $c=$it['result'];$hits++;$manifestHits++;$isTesla=!empty($c['tesla_match'])?1:0;if($isTesla){$manifestTesla++;$teslaItems[]=$c;}
        $pdo->prepare('INSERT INTO tp_manifest_items(vessel_id,mrn,msn,cargo_no,mbl_no,hbl_no,product_name,package_count,package_unit,total_weight,weight_unit,loading_port,progress_status,tesla_flag,raw_xml) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE mbl_no=VALUES(mbl_no),hbl_no=VALUES(hbl_no),product_name=VALUES(product_name),package_count=VALUES(package_count),package_unit=VALUES(package_unit),total_weight=VALUES(total_weight),weight_unit=VALUES(weight_unit),loading_port=VALUES(loading_port),progress_status=VALUES(progress_status),tesla_flag=VALUES(tesla_flag),raw_xml=VALUES(raw_xml),updated_at=NOW()')->execute([$mv['id'],$scan['mrn'],$it['msn'],$it['cargo_no'],$c['mbl_no']??null,$c['hbl_no']??null,$c['product_name']??null,$c['package_count']?:null,$c['package_unit']??null,$c['total_weight']?:null,$c['weight_unit']??null,$c['loading_port']??null,$c['progress_status']??null,$isTesla,$c['raw']??null]);
      }
      if($teslaItems){
        $qty=0;$kg=0.0;$status='';$first=$teslaItems[0];
        foreach($teslaItems as $t){$qty+=(int)($t['package_count']??0);$w=(float)($t['total_weight']??0);if(mb_strtoupper((string)($t['weight_unit']??''))==='KG')$kg+=$w;if(!$status)$status=(string)($t['progress_status']??'');}
        $guess=tp_model_guess($qty?:null,$kg?:null);
        $pdo->prepare('UPDATE tp_vessels SET tesla_flag=1,customs_cargo_no=?,customs_mbl_no=?,customs_product_name=?,customs_progress_status=?,customs_total_weight=?,customs_weight_unit=?,customs_package_count=?,vehicle_count=?,total_weight_kg=?,model_guess=?,cargo_status=?,customs_last_checked_at=NOW(),customs_error=NULL WHERE id=?')->execute([$first['cargo_no']??null,$first['mbl_no']??null,$first['product_name']??'TESLA CAR',$status,$kg?:null,'KG',$qty?:null,$qty?:null,$kg?:null,$guess,$status,$mv['id']]);
      }
      $pdo->prepare('UPDATE tp_vessels SET manifest_scan_last_msn=?,manifest_scan_hits=?,manifest_scan_complete=?,manifest_scan_last_checked_at=NOW(),manifest_scan_error=NULL WHERE id=?')->execute([$scan['last_msn'],$hits,$scan['complete']?1:0,$mv['id']]);
    }catch(Throwable $e){$pdo->prepare('UPDATE tp_vessels SET manifest_scan_last_checked_at=NOW(),manifest_scan_error=? WHERE id=?')->execute([$e->getMessage(),$mv['id']]);}
  }
}

// 관세청 자동조회: 공식 cargMtNo 형식(15~19자리)이 확보된 후보만 조회
if(tp_customs_key()!==''){
  $cq=$pdo->query("SELECT * FROM tp_vessels WHERE is_vehicle_carrier=1 AND customs_cargo_no IS NOT NULL AND CHAR_LENGTH(customs_cargo_no) BETWEEN 15 AND 19 AND (customs_last_checked_at IS NULL OR customs_last_checked_at<DATE_SUB(NOW(),INTERVAL 6 HOUR))");
  foreach($cq as $cv){try{$c=tp_customs_lookup_cargo((string)$cv['customs_cargo_no']);$pdo->prepare('UPDATE tp_vessels SET customs_product_name=?,customs_progress_status=?,customs_total_weight=?,customs_weight_unit=?,customs_package_count=?,customs_last_checked_at=NOW(),customs_error=NULL,customs_raw_xml=?,tesla_flag=IF(?,1,tesla_flag),cargo_status=IF(?=1,?,cargo_status) WHERE id=?')->execute([$c['product_name'],$c['progress_status'],$c['total_weight']?:null,$c['weight_unit'],$c['package_count']?:null,$c['raw'],$c['tesla_match'],$c['tesla_match'],$c['progress_status'],$cv['id']]);$customsChecked++;if($c['tesla_match'])$customsTesla++;}catch(Throwable $e){$pdo->prepare('UPDATE tp_vessels SET customs_error=?,customs_last_checked_at=NOW() WHERE id=?')->execute([$e->getMessage(),$cv['id']]);}}
}
// v1.9.2 알림 안전장치: PPURIO_ENABLED=false일 때는 발송/로그/알림완료 처리를 하지 않는다.
// OFF 상태에서 notified_at이 찍혀 나중에 실제 알림이 누락되는 문제를 방지한다.
if(defined('PPURIO_ENABLED') && PPURIO_ENABLED){
  $q=$pdo->query("SELECT * FROM tp_vessels WHERE tesla_flag=1 AND notified_at IS NULL AND (eta IS NULL OR eta>=DATE_SUB(NOW(),INTERVAL 3 DAY)) ORDER BY eta");
  foreach($q as $v){
    $subs=$pdo->query('SELECT * FROM tp_subscribers WHERE is_active=1')->fetchAll();
    $allOk=!empty($subs);
    // 알림 var5는 MOF 참고항이 아니라 API024 실제 전출항지를 우선 사용
    $v['origin_port']=trim((string)($v['customs_iopr_departure_port']??'')) ?: (string)($v['origin_port']??'');
    foreach($subs as $s){
      try{
        $res=tp_send_alimtalk($s['phone'],$v);
        $ok=(($res['http']??0)>=200&&($res['http']??0)<300);
        $pdo->prepare('INSERT IGNORE INTO tp_notifications(vessel_id,subscriber_id,success,result_json) VALUES(?,?,?,?)')->execute([$v['id'],$s['id'],$ok?1:0,json_encode($res,JSON_UNESCAPED_UNICODE)]);
        if($ok)$sent++; else $allOk=false;
      }catch(Throwable $e){$allOk=false;}
    }
    if($allOk)$pdo->prepare('UPDATE tp_vessels SET notified_at=NOW() WHERE id=?')->execute([$v['id']]);
  }
}
$shanghai=(int)$pdo->query("SELECT COUNT(*) FROM tp_vessels WHERE is_vehicle_carrier=1 AND customs_iopr_verified=1 AND china_origin_flag=1 AND (eta IS NULL OR eta>=DATE_SUB(NOW(),INTERVAL 3 DAY))")->fetchColumn();
$trackingTotal=(int)$pdo->query("SELECT COUNT(*) FROM tp_vessels WHERE is_vehicle_carrier=1 AND customs_iopr_verified=1 AND china_origin_flag=1 AND customs_iopr_submission_no REGEXP '^[A-Za-z0-9]{11}$'")->fetchColumn();
$trackingWaiting=(int)$pdo->query("SELECT COUNT(*) FROM tp_vessels WHERE is_vehicle_carrier=1 AND customs_iopr_verified=1 AND china_origin_flag=1 AND customs_iopr_submission_no REGEXP '^[A-Za-z0-9]{11}$' AND tesla_flag=0")->fetchColumn();
echo json_encode(['ok'=>true,'version'=>'2.0','period'=>[$from,$to],'checked_all_pages'=>count($rows),'vehicle_carriers'=>$carriers,'shanghai_origin_candidates'=>$shanghai,'api024_checked'=>$ioprChecked,'api024_verified'=>$ioprVerified,'manifest_api001_calls'=>$manifestScanned,'manifest_valid_cargo'=>$manifestHits,'manifest_tesla_items'=>$manifestTesla,'tracking_total'=>$trackingTotal,'tracking_waiting'=>$trackingWaiting,'customs_checked'=>$customsChecked,'customs_tesla_matches'=>$customsTesla,'new_vessels'=>$new,'updated'=>$updated,'alimtalk_sent'=>$sent,'at'=>date('c')],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'version'=>'2.0','error'=>$e->getMessage(),'at'=>date('c')],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);}
