<?php
declare(strict_types=1);

function tp_customs_api_url(): string {
    return defined('CUSTOMS_API_URL') ? (string)CUSTOMS_API_URL : 'https://unipass.customs.go.kr:38010/ext/rest/cargCsclPrgsInfoQry/retrieveCargCsclPrgsInfo';
}
function tp_customs_key(): string { return defined('CUSTOMS_API_KEY') ? trim((string)CUSTOMS_API_KEY) : ''; }
function tp_customs_valid_carg_no(string $no): bool { return (bool)preg_match('/^[A-Za-z0-9]{15,19}$/', trim($no)); }
function tp_customs_valid_bl(string $no): bool { return (bool)preg_match('/^[A-Za-z0-9._\/-]{3,20}$/', trim($no)); }
function tp_customs_request(array $params): array {
    $key=tp_customs_key(); if($key==='') throw new RuntimeException('CUSTOMS_API_KEY가 비어 있습니다.');
    $params=array_merge(['crkyCn'=>$key],$params);
    $url=tp_customs_api_url().'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_USERAGENT=>'TeslaPortMonitor/1.8.6',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $raw=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($raw===false) throw new RuntimeException('관세청 API 통신 실패: '.$err);
    if($http<200||$http>=300) throw new RuntimeException('관세청 API HTTP 오류('.$http.'): '.mb_substr(strip_tags((string)$raw),0,800));
    $xml=@simplexml_load_string((string)$raw); if(!$xml) throw new RuntimeException('관세청 XML 파싱 실패: '.mb_substr(strip_tags((string)$raw),0,800));
    $notice=trim((string)($xml->ntceInfo??'')); $cnt=(int)($xml->tCnt??0);
    if($cnt===-1) throw new RuntimeException('관세청 API 오류: '.($notice?:'알 수 없는 오류'));
    return ['xml'=>$xml,'raw'=>(string)$raw,'http'=>$http,'notice'=>$notice,'count'=>$cnt];
}
function tp_customs_vo_to_row(SimpleXMLElement $v, string $raw=''): array {
    $g=function(string $n)use($v){return trim((string)($v->{$n}??''));};
    $row=[
      'cargo_no'=>$g('cargMtNo'),'product_name'=>$g('prnm'),'progress_status'=>$g('prgsStts'),'customs_status'=>$g('csclPrgsStts'),
      'total_weight'=>(float)($g('ttwg')?:0),'weight_unit'=>$g('wghtUt'),'package_count'=>(int)($g('pckGcnt')?:0),'package_unit'=>$g('pckUt'),
      'ship_name'=>$g('shipNm'),'loading_port'=>$g('ldprNm'),'discharge_port'=>$g('dsprNm'),'entry_date'=>$g('etprDt'),
      'mbl_no'=>$g('mblNo'),'hbl_no'=>$g('hblNo'),'shipping_company'=>$g('shcoFlco'),'raw'=>$raw
    ];
    // 오탐 방지를 위해 선박명이 아니라 공식 품명(prnm)에서만 TESLA 판정
    $row['tesla_match']=str_contains(mb_strtoupper($row['product_name']),'TESLA') ? 1 : 0;
    return $row;
}
function tp_customs_parse(array $res): array {
    $xml=$res['xml']; $detail=[]; $candidates=[];
    if(isset($xml->cargCsclPrgsInfoQryVo)) $detail=tp_customs_vo_to_row($xml->cargCsclPrgsInfoQryVo,$res['raw']);
    // 다건 조회 시 가이드의 목록 노드명 차이를 견고하게 처리: cargMtNo를 가진 자식 노드를 후보로 수집
    foreach($xml->xpath('//*[cargMtNo]') ?: [] as $node){
        $r=tp_customs_vo_to_row($node,$res['raw']);
        if($r['cargo_no']!=='' && (!isset($detail['cargo_no']) || $r['cargo_no']!==$detail['cargo_no'])) $candidates[$r['cargo_no']]=$r;
    }
    return ['detail'=>$detail,'candidates'=>array_values($candidates),'notice'=>$res['notice'],'count'=>$res['count'],'raw'=>$res['raw']];
}
function tp_customs_lookup_cargo(string $cargoNo): array {
    $cargoNo=trim($cargoNo); if(!tp_customs_valid_carg_no($cargoNo)) throw new InvalidArgumentException('관세청 화물관리번호(cargMtNo)는 15~19자리입니다.');
    $p=tp_customs_parse(tp_customs_request(['cargMtNo'=>$cargoNo]));
    if(!$p['detail']) return array_merge(['cargo_no'=>$cargoNo,'notice'=>$p['notice'],'count'=>$p['count']],$p);
    return array_merge($p['detail'],['notice'=>$p['notice'],'count'=>$p['count'],'candidates'=>$p['candidates']]);
}
function tp_customs_lookup_bl(string $type,string $blNo,string $year): array {
    $type=strtolower(trim($type)); $blNo=trim($blNo); $year=trim($year);
    if(!in_array($type,['mbl','hbl'],true)) throw new InvalidArgumentException('B/L 유형이 올바르지 않습니다.');
    if(!tp_customs_valid_bl($blNo)) throw new InvalidArgumentException('B/L 번호 형식을 확인해 주세요.');
    if(!preg_match('/^20\d{2}$/',$year)) throw new InvalidArgumentException('B/L 조회년도는 YYYY 형식이어야 합니다.');
    $params=['blYy'=>$year,$type==='mbl'?'mblNo':'hblNo'=>$blNo];
    return tp_customs_parse(tp_customs_request($params));
}


/**
 * v1.8.6 diagnostic only: test a bounded set of REAL API001 cargMtNo candidates
 * formed as MRN(11) + four-digit MSN. This is NOT proof that every MRN uses
 * contiguous MSN values. Only API001-successful responses are treated as valid.
 */
function tp_customs_scan_msn(string $mrn, int $from=1, int $to=5): array {
    $mrn=strtoupper(trim($mrn));
    if(!preg_match('/^[A-Z0-9]{11}$/',$mrn)) throw new InvalidArgumentException('MRN은 영문/숫자 11자리여야 합니다.');
    $from=max(1,$from); $to=min(20,max($from,$to));
    $rows=[];
    for($i=$from;$i<=$to;$i++){
        $msn=str_pad((string)$i,4,'0',STR_PAD_LEFT);
        $cargo=$mrn.$msn;
        try{
            $r=tp_customs_lookup_cargo($cargo);
            $has=trim((string)($r['product_name']??''))!=='' || trim((string)($r['mbl_no']??''))!=='' || trim((string)($r['ship_name']??''))!=='';
            $rows[]=['msn'=>$msn,'cargo_no'=>$cargo,'valid'=>$has,'result'=>$r,'error'=>''];
        }catch(Throwable $e){
            $rows[]=['msn'=>$msn,'cargo_no'=>$cargo,'valid'=>false,'result'=>[],'error'=>$e->getMessage()];
        }
    }
    return $rows;
}

/** v1.9: bounded automatic MRN -> MSN scan. Only API001-confirmed cargo is valid. */
function tp_customs_auto_scan_mrn(string $mrn, int $maxMsn=20, int $missLimit=5): array {
    $mrn=strtoupper(trim($mrn));
    if(!preg_match('/^[A-Z0-9]{11}$/',$mrn)) throw new InvalidArgumentException('MRN은 영문/숫자 11자리여야 합니다.');
    $maxMsn=max(5,min(50,$maxMsn)); $missLimit=max(3,min(10,$missLimit));
    $items=[]; $scanned=0; $misses=0; $last=0;
    for($i=1;$i<=$maxMsn;$i++){
        $msn=str_pad((string)$i,4,'0',STR_PAD_LEFT); $cargo=$mrn.$msn; $scanned++; $last=$i;
        try{
            $r=tp_customs_lookup_cargo($cargo);
            $has=trim((string)($r['product_name']??''))!=='' || trim((string)($r['mbl_no']??''))!=='' || trim((string)($r['ship_name']??''))!=='';
            if($has){$misses=0;$items[]=['msn'=>$msn,'cargo_no'=>$cargo,'result'=>$r];}
            else{$misses++;}
        }catch(Throwable $e){$misses++;}
        // 연속 미응답으로 종료. 유효화물 뒤 5연속 미응답 또는 시작부터 5연속 미응답.
        if($misses >= $missLimit) break;
    }
    return ['mrn'=>$mrn,'scanned'=>$scanned,'last_msn'=>$last,'items'=>$items,'complete'=>($misses >= $missLimit)];
}
