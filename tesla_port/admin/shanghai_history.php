<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/lib/common.php';
require_once dirname(__DIR__).'/lib/customs_iopr.php';
tp_admin_required();
$pdo=tp_db();
if(empty($_SESSION['tp_csrf'])) $_SESSION['tp_csrf']=bin2hex(random_bytes(24));
$results=[];$errors=[];$scanned=0;$apiRows=0;

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='scan'){
    if(!hash_equals((string)$_SESSION['tp_csrf'],(string)($_POST['csrf']??''))) $errors[]='보안토큰이 올바르지 않습니다.';
    elseif(!defined('CUSTOMS_IOPR_API_KEY') || trim((string)CUSTOMS_IOPR_API_KEY)==='') $errors[]='CUSTOMS_IOPR_API_KEY가 비어 있습니다.';
    else{
        // 같은 호출부호 중복 제거. 최근 자동차운반선에서 최대 100개 호출부호를 검사한다.
        $st=$pdo->query("SELECT call_sign, MAX(vessel_name) vessel_name, MAX(COALESCE(eta,first_seen_at)) last_seen
                        FROM tp_vessels
                        WHERE is_vehicle_carrier=1 AND call_sign IS NOT NULL AND call_sign<>''
                        GROUP BY call_sign ORDER BY last_seen DESC LIMIT 100");
        foreach($st as $v){
            $scanned++;
            try{
                $ir=tp_iopr_request((string)$v['call_sign'],'10');
                $apiRows += count($ir['rows']);
                foreach($ir['rows'] as $r){
                    if(!tp_iopr_is_shanghai_port((string)($r['departure_port_name']??''),(string)($r['departure_port_code']??''))) continue;
                    $results[]=[
                        'call_sign'=>(string)$v['call_sign'],
                        'db_vessel'=>(string)$v['vessel_name'],
                        'ship_name'=>(string)($r['ship_name']??''),
                        'departure_port'=>(string)($r['departure_port_name']??''),
                        'departure_port_code'=>(string)($r['departure_port_code']??''),
                        'arrival_at'=>tp_iopr_digits_dt((string)($r['arrival_at']??'')) ?: (string)($r['arrival_at']??''),
                        'submission_no'=>(string)($r['submission_no']??''),
                        'customs_name'=>(string)($r['customs_name']??''),
                        'berth_name'=>(string)($r['berth_name']??''),
                    ];
                }
            }catch(Throwable $e){$errors[]=(string)$v['call_sign'].' / '.(string)$v['vessel_name'].' : '.$e->getMessage();}
        }
        usort($results,fn($a,$b)=>strcmp((string)$b['arrival_at'],(string)$a['arrival_at']));
    }
}
function hp(string $s): string {return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>상하이 출항 이력 찾기</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f5f6f8}.card{border:0;border-radius:16px}code{word-break:break-all}</style></head><body><div class="container py-4" style="max-width:1250px">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h2 class="fw-bold mb-1">상하이 출항 이력 찾기</h2><div class="text-secondary">v1.8.4 · API024 과거 입항이력에서 Shanghai 실제 사례 탐색</div></div><div><a class="btn btn-outline-dark" href="cargo_link.php">화물연결 진단</a> <a class="btn btn-outline-dark" href="index.php">관리자</a></div></div>
<div class="alert alert-info mt-4"><b>목적:</b> 현재 조회기간에 Shanghai 출항선이 없어도, DB에 수집된 자동차운반선 호출부호를 API024로 조회하여 <b>과거 입항이력 중 실제 전출항지가 Shanghai인 행만</b> 찾습니다. 이 결과는 테스트 표본 확보용이며 현재 입항건의 Tesla 후보 플래그를 변경하지 않습니다.</div>
<form method="post" class="mb-4" onsubmit="return confirm('자동차운반선 호출부호 최대 100개를 API024로 조회할까요? API 호출이 여러 번 발생합니다.');"><input type="hidden" name="action" value="scan"><input type="hidden" name="csrf" value="<?=hp($_SESSION['tp_csrf'])?>"><button class="btn btn-danger">Shanghai 과거 이력 지금 찾기</button></form>
<?php if($_SERVER['REQUEST_METHOD']==='POST'):?><div class="alert alert-secondary">호출부호 <b><?=number_format($scanned)?></b>개 검사 · API024 입항행 <b><?=number_format($apiRows)?></b>건 확인 · Shanghai 일치 <b><?=number_format(count($results))?></b>건</div><?php endif?>
<?php if($errors):?><div class="alert alert-warning"><b>일부 조회 오류 <?=count($errors)?>건</b><details class="mt-2"><summary>오류 보기</summary><div class="small mt-2"><?php foreach(array_slice($errors,0,20) as $e):?><div><?=hp($e)?></div><?php endforeach?></div></details></div><?php endif?>
<?php if($_SERVER['REQUEST_METHOD']==='POST' && !$results):?><div class="alert alert-light border">API024가 반환한 범위에서는 Shanghai 출항 이력을 찾지 못했습니다. 번호를 추정하거나 Tesla로 임의 판정하지 않습니다.</div><?php endif?>
<?php foreach($results as $r):?><div class="card shadow-sm mb-3"><div class="card-body"><div class="d-flex justify-content-between flex-wrap gap-2"><div><b class="fs-5"><?=hp($r['ship_name']?:$r['db_vessel'])?></b> <span class="badge text-bg-danger">Shanghai 실제 일치</span></div><div><?=hp($r['arrival_at']?:'-')?></div></div><div class="row mt-3 small"><div class="col-lg-2"><b>호출부호</b><br><?=hp($r['call_sign'])?></div><div class="col-lg-3"><b>API024 실제 전출항지</b><br><?=hp($r['departure_port']?:'-')?><?php if($r['departure_port_code']):?> <span class="text-secondary">(<?=hp($r['departure_port_code'])?>)</span><?php endif?></div><div class="col-lg-3"><b>MRN / 제출번호</b><br><code><?=hp($r['submission_no']?:'-')?></code></div><div class="col-lg-2"><b>세관</b><br><?=hp($r['customs_name']?:'-')?></div><div class="col-lg-2"><b>선석</b><br><?=hp($r['berth_name']?:'-')?></div></div><div class="mt-3 small"><b>다음 연결 표본:</b> 이 MRN은 API024가 실제 반환한 값입니다. 다만 <b>MRN만으로 MSN/HBL/cargMtNo를 생성하지 않습니다.</b></div><div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="mrn_bridge.php?mrn=<?=urlencode($r['submission_no'])?>&ship=<?=urlencode($r['ship_name']?:$r['db_vessel'])?>&arrival=<?=urlencode($r['arrival_at'])?>&departure=<?=urlencode(trim($r['departure_port'].' '.$r['departure_port_code']))?>">이 MRN으로 화물연결 실험</a></div></div></div><?php endforeach?>
</div></body></html>
