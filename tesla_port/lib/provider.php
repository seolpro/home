<?php
declare(strict_types=1);

function tp_xml_text($node, string $name): string {
    if (!$node || !isset($node->{$name})) return '';
    return trim((string)$node->{$name});
}
function tp_parse_dt(string $s): ?string {
    $s=trim($s); if($s==='') return null;
    try { return (new DateTime($s))->format('Y-m-d H:i:s'); } catch(Throwable $e) { return null; }
}
function tp_api_error_message(string $raw, int $http): string {
    $code=''; $msg='';
    if(preg_match('~<resultCode>(.*?)</resultCode>~s',$raw,$m)) $code=trim(strip_tags($m[1]));
    if(preg_match('~<resultMsg>(.*?)</resultMsg>~s',$raw,$m)) $msg=trim(strip_tags($m[1]));
    if($code==='' && preg_match('~<returnReasonCode>(.*?)</returnReasonCode>~s',$raw,$m)) $code=trim(strip_tags($m[1]));
    if($msg==='' && preg_match('~<returnAuthMsg>(.*?)</returnAuthMsg>~s',$raw,$m)) $msg=trim(strip_tags($m[1]));
    $map=['01'=>'APPLICATION_ERROR','10'=>'INVALID_REQUEST_PARAMETER / INVALID_SERVICE_KEY','11'=>'INVALID_REQUEST_PARAMETER_ERROR','12'=>'NO_OPENAPI_SERVICE_ERROR','20'=>'SERVICE_ACCESS_DENIED / SERVICE_KEY_IS_NULL','22'=>'LIMITED_NUMBER_OF_SERVICE_REQUESTS_EXCEEDS_ERROR','30'=>'SERVICE_KEY_IS_NOT_REGISTERED_ERROR','31'=>'DEADLINE_HAS_EXPIRED_ERROR','32'=>'UNREGISTERED_IP_ERROR','99'=>'UNKNOWN_ERROR'];
    $extra=$code!=='' ? ($map[$code]??'') : '';
    return '해수부 API HTTP 오류('.$http.')'.($code!==''?' / code='.$code:'').($msg!==''?' / '.$msg:'').($extra!==''?' / '.$extra:'');
}
function tp_api_request(array $params): array {
    if(DATA_GO_KR_SERVICE_KEY==='') throw new RuntimeException('DATA_GO_KR_SERVICE_KEY가 비어 있습니다.');
    // 인증키가 이미 URL-encoded 된 경우 이중 인코딩을 피하기 위해 serviceKey만 별도 결합
    $key=DATA_GO_KR_SERVICE_KEY;
    $query=http_build_query($params,'','&',PHP_QUERY_RFC3986);
    $url=MOF_VESSEL_API_URL.'?serviceKey='.$key.'&'.$query;
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_USERAGENT=>'TeslaPortMonitor/1.3']);
    $raw=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($raw===false) throw new RuntimeException('해수부 API 통신 실패: '.$err);
    if($http<200 || $http>=300) throw new RuntimeException(tp_api_error_message((string)$raw,$http)."\n".mb_substr(strip_tags((string)$raw),0,1000));
    $xml=@simplexml_load_string((string)$raw);
    if(!$xml) throw new RuntimeException('해수부 XML 파싱 실패: '.mb_substr(strip_tags((string)$raw),0,1000));
    $resultCode=trim((string)($xml->header->resultCode ?? ''));
    $resultMsg=trim((string)($xml->header->resultMsg ?? ''));
    if($resultCode!=='' && $resultCode!=='00') throw new RuntimeException('해수부 API 오류 '.$resultCode.': '.$resultMsg);
    return ['xml'=>$xml,'raw'=>(string)$raw,'url'=>$url,'http'=>$http];
}
function tp_normalize_item(SimpleXMLElement $x): array {
    $details=[];
    if(isset($x->details->detail)) foreach($x->details->detail as $d) $details[]=$d;
    $arrival=null;
    foreach($details as $d){ if(tp_xml_text($d,'etryndNm')==='입항'){ $arrival=$d; if(tp_xml_text($d,'reqstSeNm')==='최종') break; } }
    if(!$arrival && $details) $arrival=$details[0];
    $year=tp_xml_text($x,'etryptYear'); $co=tp_xml_text($x,'etryptCo');
    $voyage=trim($year.($year&&$co?'-':'').$co);
    $eta=$arrival ? tp_parse_dt(tp_xml_text($arrival,'etryptDt')) : null;
    $raw=json_decode(json_encode($x,JSON_UNESCAPED_UNICODE),true)?:[];
    return [
      'vessel_name'=>tp_xml_text($x,'vsslNm'), 'voyage_no'=>$voyage,
      'call_sign'=>tp_xml_text($x,'clsgn'), 'vessel_kind'=>tp_xml_text($x,'vsslKndNm'),
      'eta'=>$eta, 'port_code'=>tp_xml_text($x,'prtAgCd'), 'port_name'=>tp_xml_text($x,'prtAgNm'),
      'origin_port'=>tp_xml_text($x,'prvsDpmprtPrtNm') ?: tp_xml_text($x,'frstDpmprtPrtNm'),
      'origin_port_code'=>tp_xml_text($x,'prvsDpmprtNatPrtCd') ?: tp_xml_text($x,'frstDpmprtNatPrtCd'),
      'first_origin_port'=>tp_xml_text($x,'frstDpmprtPrtNm'), 'next_port'=>tp_xml_text($x,'nxlnptPrtNm'),
      'manifest_no'=>$arrival ? tp_xml_text($arrival,'mrNum') : '',
      'berth_name'=>$arrival ? tp_xml_text($arrival,'laidupFcltyNm') : '',
      'cargo_ton'=>$arrival ? (float)(tp_xml_text($arrival,'ldadngTon')?:0) : 0,
      'landing_ton'=>$arrival ? (float)(tp_xml_text($arrival,'landngFrghtTon')?:0) : 0,
      'report_company'=>$arrival ? tp_xml_text($arrival,'satmntEntrpsNm') : '',
      'raw'=>$raw
    ];
}
function tp_fetch_vessels(string $from,string $to, ?string $portCode=null): array {
    $portCode=$portCode ?: tp_port_code(); $page=1; $out=[]; $total=0;
    do {
      $res=tp_api_request(['prtAgCd'=>$portCode,'sde'=>str_replace('-','',$from),'ede'=>str_replace('-','',$to),'deGb'=>'I','pageNo'=>$page,'numOfRows'=>50]);
      $xml=$res['xml']; $items=$xml->body->items->item ?? [];
      foreach($items as $x){ $r=tp_normalize_item($x); if($r['vessel_name']!=='') $out[]=$r; }
      $total=(int)($xml->body->totalCount ?? count($out)); $page++;
    } while(count($out)<$total && $page<=100);
    return $out;
}
function tp_api_test(string $from,string $to,string $portCode): array {
    $res=tp_api_request(['prtAgCd'=>$portCode,'sde'=>str_replace('-','',$from),'ede'=>str_replace('-','',$to),'deGb'=>'I','pageNo'=>1,'numOfRows'=>50]);
    $xml=$res['xml']; $rows=[]; foreach($xml->body->items->item ?? [] as $x) $rows[]=tp_normalize_item($x);
    return ['rows'=>$rows,'total'=>(int)($xml->body->totalCount??count($rows)),'http'=>$res['http']];
}
