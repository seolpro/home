<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/lib/common.php';
require_once dirname(__DIR__).'/lib/customs_iopr.php';
tp_admin_required();
$pdo=tp_db();
if(empty($_SESSION['tp_csrf'])) $_SESSION['tp_csrf']=bin2hex(random_bytes(24));
$msg='';$err='';
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='reverify_api024'){
    if(!hash_equals((string)$_SESSION['tp_csrf'],(string)($_POST['csrf']??''))) {$err='보안토큰이 올바르지 않습니다.';}
    elseif(!defined('CUSTOMS_IOPR_API_KEY') || trim((string)CUSTOMS_IOPR_API_KEY)==='') {$err='CUSTOMS_IOPR_API_KEY가 비어 있습니다.';}
    else{
        $checked=0;$verified=0;$shanghai=0;$failed=0;
        $q=$pdo->query("SELECT * FROM tp_vessels WHERE is_vehicle_carrier=1 AND call_sign IS NOT NULL AND call_sign<>'' ORDER BY COALESCE(eta,first_seen_at) DESC LIMIT 100");
        foreach($q as $v){
            try{
                $ir=tp_iopr_request((string)$v['call_sign'],'10');
                $m=tp_iopr_best_match($ir['rows'],$v['eta']??null);$ok=0;$isShanghai=0;
                if($m){
                    $nameOk=mb_strtoupper(trim((string)$m['ship_name']))===mb_strtoupper(trim((string)$v['vessel_name']));
                    $timeOk=$m['time_diff_hours']===null||$m['time_diff_hours']<=72;
                    $ok=($nameOk&&$timeOk)?1:0;
                    // 후보 판정은 API024에서 매칭된 실제 전출항지 하나만 사용
                    $isShanghai=($ok && tp_iopr_is_shanghai_port((string)($m['departure_port_name']??''),(string)($m['departure_port_code']??'')))?1:0;
                }
                $pdo->prepare('UPDATE tp_vessels SET customs_iopr_verified=?,customs_iopr_submission_no=?,customs_iopr_arrival_at=?,customs_iopr_departure_port=?,customs_iopr_customs_name=?,customs_iopr_berth_name=?,china_origin_flag=?,customs_iopr_last_checked_at=NOW(),customs_iopr_error=NULL,customs_iopr_raw_xml=? WHERE id=?')->execute([$ok,$m['submission_no']??null,$m['arrival_at_normalized']??null,$m['departure_port_name']??null,$m['customs_name']??null,$m['berth_name']??null,$isShanghai,$ir['raw'],$v['id']]);
                $checked++;if($ok)$verified++;if($isShanghai)$shanghai++;
            }catch(Throwable $e){$failed++;$pdo->prepare('UPDATE tp_vessels SET china_origin_flag=0,customs_iopr_error=?,customs_iopr_last_checked_at=NOW() WHERE id=?')->execute([$e->getMessage(),$v['id']]);}
        }
        $msg="API024 즉시 재검증 완료: {$checked}건 조회 / {$verified}건 일치 / 상하이 출항 {$shanghai}건 / 오류 {$failed}건";
    }
}
$rows=$pdo->query("SELECT * FROM tp_vessels WHERE is_vehicle_carrier=1 ORDER BY china_origin_flag DESC, COALESCE(eta,first_seen_at) DESC LIMIT 300")->fetchAll();
function tp_mrn_parts(string $mrn): array {$m=strtoupper(trim($mrn));if(!preg_match('/^([0-9]{2})([A-Z0-9]{4})([A-Z0-9]{4})([A-Z0-9])$/',$m,$x))return ['valid'=>false,'mrn'=>$m];return ['valid'=>true,'mrn'=>$m,'year'=>$x[1],'carrier'=>$x[2],'serial'=>$x[3],'check'=>$x[4]];}
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>화물연결 진단</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f5f6f8}.card{border:0;border-radius:16px}code{word-break:break-all}.step{font-size:.9rem}</style></head><body><div class="container py-4" style="max-width:1250px">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h2 class="fw-bold mb-1">화물연결 진단</h2><div class="text-secondary">v1.8.3 · API024 실제 전출항지 → Shanghai 전용 판정</div></div><div><a class="btn btn-danger" href="shanghai_history.php">상하이 출항 이력찾기</a> <a class="btn btn-outline-dark" href="carriers.php">자동차운반선</a> <a class="btn btn-outline-dark" href="customs_test.php">API001 테스트</a> <a class="btn btn-outline-dark" href="index.php">관리자</a></div></div>
<div class="alert alert-info mt-4"><b>판정 원칙:</b> Tesla 추적 후보는 MOF의 출발항/최초출항지가 아니라 <b>API024에서 해당 입항건과 교차검증된 실제 전출항지</b>가 Shanghai(상하이)일 때만 표시합니다.</div>
<div class="alert alert-warning"><b>v1.8.3 진단:</b> MRN 11자리만으로 API001의 cargMtNo를 임의 생성하지 않습니다. 다음 연결 목표는 승인된 데이터 경로에서 MSN/B/L 또는 완성된 cargMtNo를 확보하는 것입니다.</div>
<?php if($msg):?><div class="alert alert-success"><?=h($msg)?></div><?php endif?><?php if($err):?><div class="alert alert-danger"><?=h($err)?></div><?php endif?>
<form method="post" class="mb-4" onsubmit="return confirm('자동차운반선을 API024로 지금 다시 조회하여 상하이 출항 여부를 재판정할까요?');"><input type="hidden" name="action" value="reverify_api024"><input type="hidden" name="csrf" value="<?=h($_SESSION['tp_csrf'])?>"><button class="btn btn-danger">API024 지금 재검증 · 상하이 후보 초기화</button> <span class="text-secondary small ms-2">12시간 대기 없이 최근 자동차운반선 최대 100건을 재검증합니다.</span></form>
<?php foreach($rows as $r):$p=tp_mrn_parts((string)($r['manifest_no']??''));$verified=!empty($r['customs_iopr_verified']);$linked=!empty($r['customs_cargo_no']);$dep=(string)($r['customs_iopr_departure_port']??'');$candidate=$verified&&!empty($r['china_origin_flag'])&&tp_iopr_is_shanghai_port($dep);?>
<div class="card shadow-sm mb-3"><div class="card-body"><div class="d-flex justify-content-between flex-wrap gap-2"><div><b class="fs-5"><?=h($r['vessel_name'])?></b> <?php if($candidate):?><span class="badge text-bg-danger">상하이 출항 · Tesla 추적</span><?php else:?><span class="badge text-bg-light border text-dark">추적대상 아님</span><?php endif?> <?php if($linked):?><span class="badge text-bg-success">API001 연결됨</span><?php else:?><span class="badge text-bg-secondary">화물번호 미확보</span><?php endif?></div><div><?=h($r['eta'])?></div></div>
<div class="row mt-3 small"><div class="col-lg-3"><b>API024 실제 전출항지</b><br><?=h($dep?:'-')?></div><div class="col-lg-3"><b>MOF 참고 출발항</b><br><?=h($r['origin_port']?:'-')?></div><div class="col-lg-3"><b>MRN / 제출번호</b><br><code><?=h($r['manifest_no']?:'-')?></code></div><div class="col-lg-3"><b>API024 판정</b><br><?=$verified?'✓ 교차검증 완료':'△ 미확인'?> · <?=$candidate?'<b class="text-danger">Shanghai 일치</b>':'Shanghai 아님'?></div></div>
<?php if($p['valid']):?><div class="border rounded p-3 mt-3 bg-light step"><b>MRN 분해:</b> 제출년도 <code><?=h($p['year'])?></code> · 선사부호 <code><?=h($p['carrier'])?></code> · 일련번호 <code><?=h($p['serial'])?></code> · 검증부호 <code><?=h($p['check'])?></code><br><span class="text-danger">MSN/HSN은 이 MRN 안에 포함되어 있지 않으므로 추정하지 않습니다.</span></div><?php endif?>
<div class="mt-3 step"><b>연결 단계:</b> 해수부 <span class="text-success">✓</span> → API024 <span class="text-success"><?=$verified?'✓':'△'?></span> → Shanghai <?=$candidate?'<span class="text-success">✓</span>':'<span class="text-secondary">×</span>'?> → MRN <span class="text-success"><?=$p['valid']?'✓':'△'?></span> → <b>MSN/B/L 확보 <?=$linked?'<span class="text-success">✓</span>':'<span class="text-danger">미해결</span>'?></b> → API001 <?=$linked?'<span class="text-success">✓</span>':'대기'?></div>
<?php if(!empty($r['customs_iopr_error'])):?><div class="alert alert-danger mt-3 mb-0">API024 오류: <?=h($r['customs_iopr_error'])?></div><?php endif?>
</div></div><?php endforeach?></div></body></html>
