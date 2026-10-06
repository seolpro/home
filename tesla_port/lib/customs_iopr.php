<?php
declare(strict_types=1);

function tp_iopr_api_url(): string {
    return defined('CUSTOMS_IOPR_API_URL') ? (string)CUSTOMS_IOPR_API_URL : 'https://unipass.customs.go.kr:38010/ext/rest/ioprRprtQry/retrieveIoprRprtBrkd';
}
function tp_iopr_request(string $callSign, string $direction='10', string $customsCode=''): array {
    $key=defined('CUSTOMS_IOPR_API_KEY') ? trim((string)CUSTOMS_IOPR_API_KEY) : '';
    if($key==='') throw new RuntimeException('CUSTOMS_IOPR_API_KEY(API024 인증키)가 비어 있습니다.');
    $callSign=strtoupper(trim($callSign));
    if($callSign==='' || !preg_match('/^[A-Z0-9-]{2,12}$/',$callSign)) throw new InvalidArgumentException('선박 호출부호 형식을 확인해 주세요.');
    if(!in_array($direction,['10','11'],true)) throw new InvalidArgumentException('입출항구분은 10(입항) 또는 11(출항)입니다.');
    $params=['crkyCn'=>$key,'shipCallImoNo'=>$callSign,'seaFlghIoprTpcd'=>$direction];
    if($direction==='11'){
        $customsCode=trim($customsCode);
        if(!preg_match('/^\d{3}$/',$customsCode)) throw new InvalidArgumentException('출항조회에는 세관부호 3자리가 필요합니다.');
        $params['cstmSgn']=$customsCode;
    }
    $url=tp_iopr_api_url().'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_USERAGENT=>'TeslaPortMonitor/1.8.3',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $raw=curl_exec($ch);$err=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($raw===false) throw new RuntimeException('관세청 API024 통신 실패: '.$err);
    if($http<200||$http>=300) throw new RuntimeException('관세청 API024 HTTP 오류('.$http.'): '.mb_substr(strip_tags((string)$raw),0,800));
    $xml=@simplexml_load_string((string)$raw);
    if(!$xml) throw new RuntimeException('관세청 API024 XML 파싱 실패: '.mb_substr(strip_tags((string)$raw),0,800));
    $notice=trim((string)($xml->ntceInfo??''));$cnt=(int)($xml->tCnt??0);
    if($cnt===-1) throw new RuntimeException('관세청 API024 오류: '.($notice?:'알 수 없는 오류'));
    $rows=[];
    foreach($xml->xpath('//*[self::etprRprtQryBrkdQryVo or self::tkofRprtQryBrkdQryVo]') ?: [] as $v){
        $g=fn(string $n)=>trim((string)($v->{$n}??''));
        $rows[]=['customs_code'=>$g('cstmSgn'),'customs_name'=>$g('cstmNm'),'departure_port_code'=>$g('dptrPortAirptCd'),'departure_port_name'=>$g('dptrPortAirptNm'),'arrival_at'=>$g('etprDttm'),'departure_at'=>$g('tkofDttm'),'ship_name'=>$g('shipFlgtNm'),'submission_no'=>$g('ioprSbmtNo'),'ship_country_code'=>$g('shipAirCntyCd'),'ship_country_name'=>$g('shipAirCntyNm'),'berth_code'=>$g('shipLamrPlcCd'),'berth_name'=>$g('shipLamrPlcNm')];
    }
    // 문서와 실제 응답의 노드명이 달라도 ioprSbmtNo가 있는 행은 수집
    if(!$rows){foreach($xml->xpath('//*[ioprSbmtNo]') ?: [] as $v){$g=fn(string $n)=>trim((string)($v->{$n}??''));$rows[]=['customs_code'=>$g('cstmSgn'),'customs_name'=>$g('cstmNm'),'departure_port_code'=>$g('dptrPortAirptCd'),'departure_port_name'=>$g('dptrPortAirptNm'),'arrival_at'=>$g('etprDttm'),'departure_at'=>$g('tkofDttm'),'ship_name'=>$g('shipFlgtNm'),'submission_no'=>$g('ioprSbmtNo'),'ship_country_code'=>$g('shipAirCntyCd'),'ship_country_name'=>$g('shipAirCntyNm'),'berth_code'=>$g('shipLamrPlcCd'),'berth_name'=>$g('shipLamrPlcNm')];}}
    return ['rows'=>$rows,'count'=>$cnt,'notice'=>$notice,'raw'=>(string)$raw,'http'=>$http];
}
function tp_iopr_digits_dt(string $s): ?string {
    $s=preg_replace('/\D/','',$s); if(strlen($s)<8)return null;
    $fmt=strlen($s)>=12?'YmdHi':'Ymd';$s=substr($s,0,$fmt==='YmdHi'?12:8);
    $d=DateTime::createFromFormat($fmt,$s);return $d?$d->format('Y-m-d H:i:s'):null;
}
function tp_iopr_best_match(array $rows, ?string $eta): ?array {
    if(!$rows)return null;$target=$eta?strtotime($eta):false;$best=null;$score=PHP_INT_MAX;
    foreach($rows as $r){$dt=tp_iopr_digits_dt((string)($r['arrival_at']??''));$ts=$dt?strtotime($dt):false;$s=($target!==false&&$ts!==false)?abs($ts-$target):0;if($best===null||$s<$score){$best=$r;$best['arrival_at_normalized']=$dt;$score=$s;}}
    if($best!==null)$best['time_diff_hours']=($target!==false&&!empty($best['arrival_at_normalized']))?round(abs(strtotime($best['arrival_at_normalized'])-$target)/3600,1):null;
    return $best;
}


function tp_iopr_is_shanghai_port(string $portName, string $portCode=''): bool {
    $v=mb_strtoupper(trim($portName.' '.$portCode),'UTF-8');
    if($v==='') return false;
    foreach(['SHANGHAI','上海','상하이'] as $k){
        if(mb_strpos($v,mb_strtoupper($k,'UTF-8'))!==false) return true;
    }
    return false;
}
// 하위호환용. v1.8.1부터 Tesla 추적 후보는 상하이 출항만 의미합니다.
function tp_iopr_is_china_port(string $portName, string $portCode=''): bool {
    return tp_iopr_is_shanghai_port($portName,$portCode);
}
